<?php
/** SMS replies share the durable outbox; no provider call occurs in an admin request. */
defined( 'ABSPATH' ) || exit;

function irn_sms_blocked( $sender ) {
	global $wpdb;
	$opt = $wpdb->get_var( $wpdb->prepare( 'SELECT opt_out FROM ' . irn_table( 'replies' ) . " WHERE channel = 'sms' AND sender = %s AND opt_out IN ('STOP','START') ORDER BY id DESC LIMIT 1", $sender ) );
	return 'STOP' === $opt;
}

function irn_queue_reply( $id, $body, $request_key ) {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'irn_permission', 'Not allowed.' );
	}
	$body = trim( sanitize_textarea_field( $body ) );
	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $body, 'UTF-8' ) : preg_match_all( '/./us', $body );
	if ( '' === $body || false === $length || $length > 1600 || ! preg_match( '/^[a-f0-9-]{36}$/D', $request_key ) ) {
		return new WP_Error( 'irn_body', 'Enter a message between 1 and 1,600 characters.' );
	}
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . irn_table( 'replies' ) . " WHERE id = %d AND channel = 'sms'", $id ) );
	if ( ! $r || ! preg_match( '/^\+[1-9]\d{7,14}$/D', $r->sender ) ) {
		return new WP_Error( 'irn_recipient', 'This SMS conversation is unavailable.' );
	}
	if ( irn_sms_blocked( $r->sender ) ) {
		return new WP_Error( 'irn_stopped', 'This listener opted out. They must text START before you can reply.' );
	}
	if ( irn_sms_setup_issues() ) {
		return new WP_Error( 'irn_settings', 'Complete the SMS settings before replying.' );
	}
	$result = $wpdb->query( $wpdb->prepare(
		'INSERT IGNORE INTO ' . irn_table( 'outbox' ) . ' (kind, announcement_id, subscriber_id, request_key, channel, destination, payload, state, attempts, next_attempt_at, created_at) VALUES (%s, 0, NULL, %s, %s, %s, %s, %s, 0, %s, %s)',
		'reply', $request_key, 'sms', $r->sender,
		wp_json_encode( array( 'body' => $body, 'from' => $r->recipient ?? '', 'reply_to' => (int) $r->id, 'author' => get_current_user_id() ) ),
		'pending', irn_now(), irn_now()
	) );
	if ( false === $result ) {
		return new WP_Error( 'irn_storage', 'Your reply could not be saved. Please try again.' );
	}
	irn_kick_worker();
	return $result ? 'Reply queued. It will send in the background.' : 'This reply was already queued; no duplicate was added.';
}

function irn_sms_setup_issues() {
	$issues = array();
	if ( ! preg_match( '/^AC[a-f0-9]{32}$/iD', irn_setting( 'twilio_sid' ) ) ) {
		$issues[] = 'Enter a valid Twilio Account SID.';
	}
	if ( '' === irn_setting( 'twilio_token' ) ) {
		$issues[] = 'Enter the Twilio Auth Token for that account.';
	}
	if ( ! preg_match( '/^(\+[1-9]\d{7,14}|MG[a-f0-9]{32})$/iD', irn_setting( 'sms_from' ) ) ) {
		$issues[] = 'Enter your SMS-capable Twilio number or Messaging Service SID.';
	}
	return $issues;
}

function irn_render_conversation( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . irn_table( 'replies' ) . " WHERE id = %d AND channel = 'sms'", $id ) );
	if ( ! $r ) {
		echo '<p>Conversation not found.</p>';
		return;
	}
	echo '<p><a href="' . esc_url( irn_admin_url( 'replies' ) ) . '">← All replies</a></p><h2>Text conversation with ' . esc_html( $r->sender ) . '</h2>';
	$messages = $wpdb->get_results( $wpdb->prepare(
		"SELECT 'in' AS direction, body, received_at AS happened_at, '' AS state, '' AS last_result, id FROM " . irn_table( 'replies' ) . " WHERE channel = 'sms' AND sender = %s UNION ALL SELECT 'out' AS direction, payload AS body, created_at AS happened_at, state, last_result, id FROM " . irn_table( 'outbox' ) . " WHERE channel = 'sms' AND destination = %s ORDER BY happened_at DESC, id DESC LIMIT 100", $r->sender, $r->sender
	) );
	echo '<div class="irn-conversation">';
	foreach ( array_reverse( $messages ) as $m ) {
		$incoming = 'in' === $m->direction;
		$payload = $incoming ? array() : json_decode( $m->body, true );
		$body = $incoming ? $m->body : ( $payload['body'] ?? '' );
		$status = $incoming ? 'Listener' : 'You · ' . ( array( 'pending' => 'Queued', 'submitted' => 'Accepted by Twilio', 'failed' => 'Failed' )[ $m->state ] ?? $m->state );
		echo '<article class="irn-card"><strong>' . esc_html( $status ) . '</strong> · ' . esc_html( irn_local_time( $m->happened_at ) ) . '<p>' . nl2br( esc_html( $body ) ) . '</p>';
		if ( ! $incoming && 'submitted' !== $m->state && $m->last_result ) {
			echo '<p class="description">' . esc_html( $m->last_result ) . '</p>';
		}
		echo '</article>';
	}
	echo '</div><p class="description">Latest 100 messages. Completed outbound messages are kept for 90 days. Accepted by Twilio does not confirm delivery. Refresh to see new replies and updated send status.</p>';
	if ( irn_sms_blocked( $r->sender ) ) {
		echo '<p><strong>This listener opted out. They must text START before you can reply.</strong></p>';
		return;
	}
	if ( irn_sms_setup_issues() ) {
		echo '<p>Complete the <a href="' . esc_url( irn_admin_url( 'settings' ) ) . '">SMS settings</a> before replying.</p>';
		return;
	}
	if ( irn_paused() ) {
		echo '<p><strong>Sending is paused. Your reply will wait until sending resumes.</strong></p>';
	}
	$draft = get_transient( 'irn_reply_draft_' . get_current_user_id() );
	$body = is_array( $draft ) && (int) $draft['id'] === (int) $id ? $draft['body'] : '';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="irn-card">';
	wp_nonce_field( 'irn_reply' );
	$key = wp_generate_uuid4();
	echo '<input type="hidden" name="action" value="irn"><input type="hidden" name="do" value="reply"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="request_key" value="' . esc_attr( $key ) . '"><input type="hidden" name="request_signature" value="' . esc_attr( wp_create_nonce( 'irn_reply_' . $id . '_' . $key ) ) . '">';
	echo '<label for="irn-reply-body">Your text reply</label><textarea id="irn-reply-body" name="body" rows="4" maxlength="1600" required>' . esc_textarea( $body ) . '</textarea><p class="description">Sent from your Twilio number. Long messages may use multiple SMS segments.</p><button class="button button-primary">Send reply</button></form>';
}
