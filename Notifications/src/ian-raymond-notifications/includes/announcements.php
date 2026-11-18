<?php
/**
 * Announcement drafts: created by a newly added release (player catalog save) or the first publish of a
 * new show (the theme's ir_show posts), never sent until approved. One draft per release/show ID.
 */
defined( 'ABSPATH' ) || exit;

function irn_announcements_paused() {
	return '1' === get_option( 'irn_announcements_paused', '0' );
}

/** Returns the new draft's ID, or 0 if one already exists for this source (or drafts are paused). */
function irn_create_announcement( $type, $source_id, $event, $facts ) {
	global $wpdb;
	if ( irn_announcements_paused() ) {
		return 0;
	}
	$copy = irn_build_copy( $type, $facts, 0 );
	$wpdb->query(
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . irn_table( 'announcements' ) . ' (source_type, source_id, source_event, facts, email_subject, headline, message, action_url, sms_body, variant, state, created_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 0, %s, %s)',
			$type, (string) $source_id, substr( $event, 0, 255 ), wp_json_encode( $facts ), $copy['subject'], $copy['headline'], $copy['message'], $facts['url'], $copy['sms'], 'awaiting', irn_now()
		)
	);
	return 1 === (int) $wpdb->rows_affected ? (int) $wpdb->insert_id : 0;
}

// ---- release trigger (hook fired by the Music Player after a successful catalog save) ----
add_action( 'irl_catalog_saved', 'irn_on_catalog_saved', 10, 2 );
function irn_on_catalog_saved( $catalog, $previous ) {
	$before = array();
	foreach ( (array) ( $previous['releases'] ?? array() ) as $r ) {
		$before[ (string) ( $r['id'] ?? '' ) ] = true;
	}
	foreach ( (array) ( $catalog['releases'] ?? array() ) as $r ) {
		$id = (string) ( $r['id'] ?? '' );
		if ( '' === $id || isset( $before[ $id ] ) ) {
			continue; // already on the site before this save: an edit, not a new release
		}
		$facts = array(
			'title'       => (string) ( $r['title'] ?? '' ),
			'type'        => 'album' === ( $r['type'] ?? '' ) ? 'album' : 'single',
			'artwork_url' => irn_release_artwork( (string) ( $r['artwork'] ?? '' ) ),
			'url'         => home_url( '/listen/' ),
		);
		irn_create_announcement( 'release', $id, sprintf( 'New %s “%s” added in the Music Player', $facts['type'], $facts['title'] ), $facts );
	}
}

function irn_release_artwork( $art ) {
	if ( preg_match( '#^https?://#i', $art ) ) {
		return $art;
	}
	return 0 === strpos( $art, 'content/' ) ? plugins_url( 'ian-raymond-listen/player/' . $art ) : '';
}

// ---- show trigger: first time an ir_show post is published (after its fields are saved) ----
add_action( 'wp_after_insert_post', 'irn_on_show_saved', 10, 4 );
function irn_on_show_saved( $post_id, $post, $update, $post_before ) {
	if ( 'ir_show' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}
	if ( $post_before && 'publish' === $post_before->post_status ) {
		return; // an edit of a published show
	}
	if ( in_array( (int) $post_id, (array) get_option( 'irn_show_baseline', array() ), true ) ) {
		return; // published before this plugin was installed
	}
	$facts = irn_show_facts( $post );
	irn_create_announcement( 'show', (string) $post_id, sprintf( 'Show “%s” published', $facts['title'] ), $facts );
}

/** Shows already published when the plugin is installed never get an announcement. */
function irn_record_show_baseline() {
	global $wpdb;
	if ( false !== get_option( 'irn_show_baseline' ) ) {
		return;
	}
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ir_show' AND post_status = 'publish'" ) );
	add_option( 'irn_show_baseline', $ids, '', false );
}

/** Date, venue and location from the show's fields (matched by field name), editable before approval. */
function irn_show_facts( $post ) {
	$facts = array( 'title' => get_the_title( $post ), 'date' => '', 'venue' => '', 'location' => '', 'url' => get_permalink( $post ) );
	$patterns = array( 'date' => '/date|start|when|time/i', 'venue' => '/venue|place/i', 'location' => '/location|city|address|where/i' );
	foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
		$value = is_array( $values ) ? (string) reset( $values ) : (string) $values;
		if ( '_' === $key[0] || '' === trim( $value ) || is_serialized( $value ) ) {
			continue;
		}
		foreach ( $patterns as $field => $re ) {
			if ( '' === $facts[ $field ] && preg_match( $re, $key ) ) {
				$facts[ $field ] = 'date' === $field ? irn_format_show_date( $value ) : sanitize_text_field( $value );
				break;
			}
		}
	}
	return $facts;
}

function irn_format_show_date( $value ) {
	$value = trim( $value );
	$ts    = preg_match( '/^\d+$/D', $value ) ? (int) $value : strtotime( $value );
	if ( ! $ts ) {
		return sanitize_text_field( $value );
	}
	if ( preg_match( '/^\d+$/D', $value ) ) {
		return wp_date( 'l, F j, Y g:i a', $ts );
	}
	// A plain date/time string is local site time; don't shift it through UTC.
	$has_time = (bool) preg_match( '/\d{1,2}:\d{2}/', $value );
	return gmdate( $has_time ? 'l, F j, Y g:i a' : 'l, F j, Y', strtotime( $value . ' UTC' ) );
}

// ---- local copy variations (no AI, no external service) ----
const IRN_VARIANTS = 3;

function irn_build_copy( $type, $f, $variant ) {
	$v = ( (int) $variant ) % IRN_VARIANTS;
	if ( 'release' === $type ) {
		$t = $f['title']; $k = $f['type']; $K = ucfirst( $k ); $u = $f['url'];
		$all = array(
			array( "New $K: $t", 'New music from Ian Raymond', "My new $k, “{$t}”, is out now. Give it a listen and let me know what you think.", "Ian Raymond: My new $k \"$t\" is out now. Listen: $u" ),
			array( "$t is out now", "“{$t}” is here", "“{$t}” just landed. Press play, turn it up, and share it with somebody who needs a true story told straight.", "Ian Raymond here. \"$t\" is out now. Hear it first: $u" ),
			array( "Just released: $t", 'Fresh off the truck', "I just released a new $k, “{$t}”. It's streaming now on my site.", "New from Ian Raymond: \"$t\" ($k). Listen now: $u" ),
		);
	} else {
		$t = $f['title']; $u = $f['url'];
		$details = implode( ' · ', array_filter( array( $f['date'], $f['venue'], $f['location'] ) ) );
		$sms_details = implode( ', ', array_filter( array( $f['date'], $f['venue'], $f['location'] ) ) );
		$d  = '' === $details ? '' : " — $details";
		$sd = '' === $sms_details ? '' : " - $sms_details";
		$all = array(
			array( "New show: $t", 'Come see me play live', "I just added a show: {$t}{$d}. Come out, say hello, and hear these songs the way they were written.", "Ian Raymond live: {$t}{$sd}. Details: $u" ),
			array( '' === $f['date'] ? $t : "$t — {$f['date']}", $t, "I'll be playing {$t}" . ( '' === $f['date'] ? '' : " on {$f['date']}" ) . ( '' === $f['venue'] ? '' : " at {$f['venue']}" ) . ( '' === $f['location'] ? '' : " in {$f['location']}" ) . ". I'd love to see you there.", "Ian Raymond: I'm playing {$t}{$sd}. Info: $u" ),
			array( '' === $f['venue'] ? 'See me live' : "See me live at {$f['venue']}", 'New live show added', "Mark your calendar: {$t}{$d}. Bring a friend.", "New Ian Raymond show: {$t}{$sd}. $u" ),
		);
	}
	list( $subject, $headline, $message, $sms ) = $all[ $v ];
	return compact( 'subject', 'headline', 'message', 'sms' );
}

// ---- admin actions (capability and nonce checks are in admin.php) ----
function irn_get_announcement( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . irn_table( 'announcements' ) . ' WHERE id = %d', $id ) );
}

/** Save edited copy/facts; only while awaiting approval. */
function irn_update_announcement( $id, $fields ) {
	global $wpdb;
	$a = irn_get_announcement( $id );
	if ( ! $a || 'awaiting' !== $a->state ) {
		return false;
	}
	$facts = json_decode( (string) $a->facts, true );
	if ( 'show' === $a->source_type ) {
		foreach ( array( 'date', 'venue', 'location' ) as $k ) {
			if ( isset( $fields[ $k ] ) ) {
				$facts[ $k ] = sanitize_text_field( $fields[ $k ] );
			}
		}
	}
	$url = esc_url_raw( (string) ( $fields['action_url'] ?? $a->action_url ) );
	return false !== $wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . irn_table( 'announcements' ) . " SET email_subject = %s, headline = %s, message = %s, action_url = %s, sms_body = %s, facts = %s WHERE id = %d AND state = 'awaiting'",
			sanitize_text_field( $fields['email_subject'] ?? $a->email_subject ), sanitize_text_field( $fields['headline'] ?? $a->headline ),
			sanitize_textarea_field( $fields['message'] ?? $a->message ), '' !== $url ? $url : $a->action_url,
			sanitize_textarea_field( $fields['sms_body'] ?? $a->sms_body ), wp_json_encode( $facts ), $id
		)
	);
}

/** Replace the unsent proposal with the next local variation. Contacts nobody. */
function irn_regenerate_announcement( $id ) {
	global $wpdb;
	$a = irn_get_announcement( $id );
	if ( ! $a || 'awaiting' !== $a->state ) {
		return false;
	}
	$facts   = json_decode( (string) $a->facts, true );
	$facts['url'] = $a->action_url;
	$variant = ( (int) $a->variant + 1 ) % IRN_VARIANTS;
	$c       = irn_build_copy( $a->source_type, $facts, $variant );
	return false !== $wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . irn_table( 'announcements' ) . " SET email_subject = %s, headline = %s, message = %s, sms_body = %s, variant = %d WHERE id = %d AND state = 'awaiting'",
			$c['subject'], $c['headline'], $c['message'], $c['sms'], $variant, $id
		)
	);
}

function irn_discard_announcement( $id ) {
	global $wpdb;
	return 1 === (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . irn_table( 'announcements' ) . " SET state = 'discarded' WHERE id = %d AND state = 'awaiting'", $id ) );
}

/** Per-channel payloads, frozen at approval. */
function irn_announcement_payloads( $a ) {
	$f    = json_decode( (string) $a->facts, true );
	$data = array( 'subject' => $a->email_subject, 'headline' => $a->headline, 'message' => $a->message, 'action_url' => $a->action_url, 'site_url' => home_url( '/' ) );
	if ( 'release' === $a->source_type ) {
		$data += array( 'release_title' => $f['title'], 'release_type' => ucfirst( $f['type'] ), 'artwork_url' => $f['artwork_url'], 'action_label' => 'Listen now' );
		$template = 'music';
	} else {
		$data += array( 'show_title' => $f['title'], 'show_date' => $f['date'], 'venue' => $f['venue'], 'location' => $f['location'], 'action_label' => 'Show details' );
		$template = 'show';
	}
	return array(
		'email' => array( 'template' => $template, 'data' => array_filter( $data, 'strlen' ) ),
		'sms'   => array( 'body' => $a->sms_body ),
	);
}

/**
 * Approve: in one transaction, lock the draft, queue one outbox row per current subscriber (unique key:
 * a repeat is a no-op) and mark it sending. A double-click or reload finds it no longer awaiting.
 * Returns the number of messages queued, or false if it was not awaiting approval.
 */
function irn_approve_announcement( $id ) {
	global $wpdb;
	$ann = irn_table( 'announcements' );
	$wpdb->query( 'START TRANSACTION' );
	$a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ann WHERE id = %d FOR UPDATE", $id ) );
	if ( ! $a || 'awaiting' !== $a->state ) {
		$wpdb->query( 'ROLLBACK' );
		return false;
	}
	$p   = irn_announcement_payloads( $a );
	$now = irn_now();
	$ok  = false !== $wpdb->query(
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . irn_table( 'outbox' ) . " (kind, announcement_id, subscriber_id, channel, destination, payload, state, attempts, next_attempt_at, created_at)
			 SELECT 'announcement', %d, id, channel, destination, CASE channel WHEN 'sms' THEN %s ELSE %s END, 'pending', 0, %s, %s FROM " . irn_table( 'subscribers' ),
			$id, wp_json_encode( $p['sms'] ), wp_json_encode( $p['email'] ), $now, $now
		)
	);
	$queued = (int) $wpdb->rows_affected;
	if ( ! $ok || false === $wpdb->query( $wpdb->prepare( "UPDATE $ann SET state = 'sending', approved_at = %s WHERE id = %d AND state = 'awaiting'", $now, $id ) ) ) {
		$wpdb->query( 'ROLLBACK' );
		return false;
	}
	$wpdb->query( 'COMMIT' );
	irn_mark_sent(); // nobody subscribed yet: it is sent immediately
	irn_kick_worker();
	return $queued;
}
