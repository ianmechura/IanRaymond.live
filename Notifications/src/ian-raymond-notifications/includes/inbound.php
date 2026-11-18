<?php
/**
 * Replies: Twilio inbound SMS (signature-verified) and SendGrid Inbound Parse email (secret key in the URL).
 * Both are stored for the Replies view. A texted STOP removes that number from the subscriber list.
 */
defined( 'ABSPATH' ) || exit;

const IRN_STOP_WORDS = array( 'STOP', 'STOPALL', 'STOP ALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'OPT OUT', 'REVOKE' );

function irn_twilio_webhook_url() {
	return rest_url( 'irn/v1/twilio' );
}

function irn_email_webhook_url() {
	return add_query_arg( 'key', irn_inbound_key(), rest_url( 'irn/v1/email' ) );
}

/** Twilio's X-Twilio-Signature: base64(HMAC-SHA1(url + sorted key/value pairs, auth token)). */
function irn_twilio_signature( $url, $params, $token ) {
	ksort( $params, SORT_STRING );
	$data = $url;
	foreach ( $params as $k => $v ) {
		$data .= $k . ( is_array( $v ) ? implode( '', $v ) : $v );
	}
	return base64_encode( hash_hmac( 'sha1', $data, $token, true ) );
}

function irn_store_reply( $channel, $provider_id, $sender, $subject, $body, $recipient = '', $opt_out = '' ) {
	global $wpdb;
	return $wpdb->query(
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . irn_table( 'replies' ) . ' (channel, provider_id, sender, subject, body, received_at, recipient, opt_out) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)',
			$channel, substr( $provider_id, 0, 191 ), substr( $sender, 0, 255 ), substr( $subject, 0, 255 ), substr( $body, 0, 20000 ), irn_now(), $recipient, $opt_out
		)
	);
}

function irn_twilio_inbound( WP_REST_Request $req ) {
	global $wpdb;
	$token  = irn_setting( 'twilio_token' );
	$params = $req->get_body_params();
	$given  = (string) $req->get_header( 'x_twilio_signature' );
	if ( '' === $token || '' === $given || ! hash_equals( irn_twilio_signature( irn_twilio_webhook_url(), $params, $token ), $given ) ) {
		return new WP_Error( 'irn_signature', 'Invalid signature.', array( 'status' => 403 ) );
	}
	$sid  = sanitize_text_field( (string) ( $params['MessageSid'] ?? $params['SmsSid'] ?? '' ) );
	$from = sanitize_text_field( (string) ( $params['From'] ?? '' ) );
	$body = sanitize_textarea_field( (string) ( $params['Body'] ?? '' ) );
	$to = (string) ( $params['To'] ?? '' );
	if ( ! preg_match( '/^SM[a-f0-9]{32}$/iD', $sid ) || ! preg_match( '/^\+[1-9]\d{7,14}$/D', $from ) || ! preg_match( '/^\+[1-9]\d{7,14}$/D', $to ) || ( $params['AccountSid'] ?? '' ) !== irn_setting( 'twilio_sid' ) ) {
		return new WP_Error( 'irn_message', 'Invalid SMS message.', array( 'status' => 400 ) );
	}
	$word = strtoupper( trim( preg_replace( '/\s+/', ' ', $body ) ) );
	$opt = strtoupper( (string) ( $params['OptOutType'] ?? '' ) );
	if ( ! in_array( $opt, array( 'STOP', 'START', 'HELP' ), true ) ) {
		$opt = in_array( $word, IRN_STOP_WORDS, true ) ? 'STOP' : ( in_array( $word, array( 'START', 'UNSTOP' ), true ) ? 'START' : '' );
	}
	if ( false === irn_store_reply( 'sms', $sid, $from, '', $body, $to, $opt ) ) {
		return new WP_Error( 'irn_storage', 'Unable to save message. Please retry.', array( 'status' => 503 ) );
	}
	if ( 'STOP' === $opt ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . irn_table( 'subscribers' ) . " WHERE channel = 'sms' AND destination = %s", $from ) );
	}

	return new WP_REST_Response( '<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200 ); // Twilio sends the STOP/HELP replies itself
}

function irn_email_inbound( WP_REST_Request $req ) {
	if ( ! hash_equals( irn_inbound_key(), (string) $req->get_param( 'key' ) ) ) {
		return new WP_Error( 'irn_key', 'Invalid key.', array( 'status' => 403 ) );
	}
	$p       = $req->get_body_params();
	$from    = sanitize_text_field( (string) ( $p['from'] ?? '' ) );
	$subject = sanitize_text_field( (string) ( $p['subject'] ?? '' ) );
	$text    = (string) ( $p['text'] ?? '' );
	if ( '' === trim( $text ) ) {
		$text = wp_strip_all_tags( (string) ( $p['html'] ?? '' ) );
	}
	$body = sanitize_textarea_field( $text );
	$id   = preg_match( '/^Message-ID:\s*(\S+)/mi', (string) ( $p['headers'] ?? '' ), $m ) ? $m[1] : 'sha1:' . sha1( $from . "\n" . $subject . "\n" . $body );
	if ( false === irn_store_reply( 'email', $id, $from, $subject, $body ) ) {
		return new WP_Error( 'irn_storage', 'Unable to save message. Please retry.', array( 'status' => 503 ) );
	}
	return array( 'ok' => true );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'irn/v1', '/twilio', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'irn_twilio_inbound' ) );
		register_rest_route( 'irn/v1', '/email', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'irn_email_inbound' ) );
	}
);

/** Twilio expects TwiML (XML), not the JSON the REST API would send. */
add_filter(
	'rest_pre_serve_request',
	function ( $served, $result, $request ) {
		if ( '/irn/v1/twilio' !== $request->get_route() || ! ( $result instanceof WP_REST_Response ) || 200 !== $result->get_status() ) {
			return $served;
		}
		header( 'Content-Type: text/xml; charset=utf-8' );
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed TwiML
		return true;
	},
	10,
	3
);
