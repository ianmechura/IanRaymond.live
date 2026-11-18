<?php
/**
 * Persistent outbox. Nothing is sent to a provider from a web request: rows are committed here and one
 * WordPress-cron worker (one database lock) sends small batches. A credential/account problem pauses the
 * whole queue until an administrator clicks Resume sending.
 */
defined( 'ABSPATH' ) || exit;

const IRN_BATCH        = 25;
const IRN_TIME_BUDGET  = 20;      // seconds per worker run
const IRN_CLAIM_DELAY  = 1800;    // an in-flight row is not retried for 30 minutes if the run dies mid-send
const IRN_RETENTION    = 7776000; // 90 days for submitted/failed rows

add_action( 'irn_worker', 'irn_run_worker' );
add_action( 'irn_worker_hourly', 'irn_run_worker' );

/** Commit one message. The unique (announcement_id, subscriber_id) key makes repeats a no-op. Returns true if a row was added. */
function irn_enqueue( $kind, $announcement_id, $subscriber_id, $channel, $destination, $payload ) {
	global $wpdb;
	$now = irn_now();
	$wpdb->query(
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . irn_table( 'outbox' ) . ' (kind, announcement_id, subscriber_id, channel, destination, payload, state, attempts, next_attempt_at, created_at) VALUES (%s, %d, %d, %s, %s, %s, %s, 0, %s, %s)',
			$kind, $announcement_id, $subscriber_id, $channel, $destination, wp_json_encode( $payload ), 'pending', $now, $now
		)
	);
	return 1 === (int) $wpdb->rows_affected;
}

/** Ask WordPress cron to run the worker soon (it starts on this or the next page request). */
function irn_kick_worker( $when = null ) {
	$when = null === $when ? time() : (int) $when;
	$next = wp_next_scheduled( 'irn_worker' );
	if ( ! $next || $next > $when ) {
		if ( $next ) {
			wp_unschedule_event( $next, 'irn_worker' );
		}
		wp_schedule_single_event( $when, 'irn_worker' );
	}
}

function irn_paused() {
	$p = get_option( 'irn_paused' );
	return is_array( $p ) ? $p : null;
}

function irn_pause( $reason ) {
	update_option( 'irn_paused', array( 'reason' => $reason, 'at' => irn_now() ), false );
}

function irn_resume() {
	delete_option( 'irn_paused' );
	irn_kick_worker();
}

/** Delay before the next try of a temporary failure: 5m, 15m, 1h, 3h, then every 6h. Timeouts wait at least 30m. */
function irn_retry_delay( $attempts, $timeout ) {
	$steps = array( 300, 900, 3600, 10800, 21600 );
	$delay = $steps[ min( max( $attempts, 1 ), count( $steps ) ) - 1 ];
	return $timeout ? max( $delay, IRN_CLAIM_DELAY ) : $delay;
}

function irn_send_row( $row ) {
	$payload = json_decode( $row->payload, true );
	if ( ! is_array( $payload ) ) {
		return irn_result( 'fail', 'Unreadable message payload.' );
	}
	if ( 'sms' === $row->channel ) {
		if ( 'reply' === $row->kind && irn_sms_blocked( $row->destination ) ) {
			return irn_result( 'fail', 'Listener opted out. Reply was not sent.' );
		}
		return irn_send_sms( $row->destination, (string) ( $payload['body'] ?? '' ), 'reply' === $row->kind ? (string) ( $payload['from'] ?? '' ) : '' );
	}
	return irn_send_email( $row->destination, (string) ( $payload['template'] ?? '' ), (array) ( $payload['data'] ?? array() ) );
}

function irn_run_worker() {
	global $wpdb;
	if ( irn_paused() ) {
		return;
	}
	$lock = $wpdb->prefix . 'irn_worker';
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
		return; // another run holds the queue
	}
	try {
		irn_cleanup();
		$out   = irn_table( 'outbox' );
		$start = time();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $out WHERE state = 'pending' AND next_attempt_at <= %s ORDER BY id LIMIT %d", irn_now(), IRN_BATCH ) );
		foreach ( $rows as $row ) {
			if ( time() - $start > IRN_TIME_BUDGET ) {
				break;
			}
			// Claim first: if this run dies after the provider accepts, the row waits instead of resending at once.
			$wpdb->query( $wpdb->prepare( "UPDATE $out SET attempts = attempts + 1, next_attempt_at = %s WHERE id = %d", irn_now( IRN_CLAIM_DELAY ), $row->id ) );
			$attempts = (int) $row->attempts + 1;
			$r        = irn_send_row( $row );
			$message  = substr( (string) $r['message'], 0, 1000 );
			if ( 'ok' === $r['status'] ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $out SET state = 'submitted', provider_id = %s, last_result = %s, completed_at = %s WHERE id = %d", substr( $r['id'], 0, 100 ), $message, irn_now(), $row->id ) );
			} elseif ( 'retry' === $r['status'] ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $out SET next_attempt_at = %s, last_result = %s WHERE id = %d", irn_now( irn_retry_delay( $attempts, $r['timeout'] ) ), $message, $row->id ) );
			} elseif ( 'pause' === $r['status'] ) {
				// Not the recipient's fault: undo the attempt, keep it pending, stop everything.
				$wpdb->query( $wpdb->prepare( "UPDATE $out SET attempts = %d, next_attempt_at = %s, last_result = %s WHERE id = %d", (int) $row->attempts, irn_now(), $message, $row->id ) );
				irn_pause( $message );
				break;
			} else {
				$wpdb->query( $wpdb->prepare( "UPDATE $out SET state = 'failed', last_result = %s, completed_at = %s WHERE id = %d", $message, irn_now(), $row->id ) );
				if ( 'unsubscribed' === $r['status'] ) {
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . irn_table( 'subscribers' ) . " WHERE channel = 'sms' AND destination = %s", $row->destination ) );
				}
			}
		}
		irn_mark_sent();
		if ( ! irn_paused() ) {
			$next = $wpdb->get_var( "SELECT MIN(next_attempt_at) FROM $out WHERE state = 'pending'" );
			if ( $next ) {
				irn_kick_worker( max( time(), strtotime( $next . ' UTC' ) ) );
			}
		}
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/** An approved announcement is sent once none of its messages are still pending. */
function irn_mark_sent() {
	global $wpdb;
	$ann = irn_table( 'announcements' ); $out = irn_table( 'outbox' );
	$wpdb->query( $wpdb->prepare( "UPDATE $ann SET state = 'sent', sent_at = %s WHERE state = 'sending' AND NOT EXISTS (SELECT 1 FROM $out WHERE $out.announcement_id = $ann.id AND $out.state = 'pending')", irn_now() ) );
}

/** Once a day: delete submitted/failed rows finished more than 90 days ago. Pending rows are never deleted by age. */
function irn_cleanup() {
	global $wpdb;
	if ( (int) get_option( 'irn_last_cleanup', 0 ) > time() - DAY_IN_SECONDS ) {
		return;
	}
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . irn_table( 'outbox' ) . " WHERE state IN ('submitted', 'failed') AND completed_at < %s", irn_now( -IRN_RETENTION ) ) );
	update_option( 'irn_last_cleanup', time(), false );
}
