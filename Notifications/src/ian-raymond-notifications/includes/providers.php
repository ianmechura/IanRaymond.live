<?php
/**
 * Twilio SMS and Twilio SendGrid email. Each send returns one of:
 *   ok           accepted by the provider (id = Message SID / X-Message-Id)
 *   retry        temporary: network, timeout, rate limit, provider outage
 *   fail         permanent for this recipient (invalid number, trial restriction, suppressed…)
 *   unsubscribed Twilio 21610: the number opted out — fail and remove the subscriber
 *   pause        credentials/account/configuration problem — stop all sending until fixed
 */
defined( 'ABSPATH' ) || exit;

function irn_result( $status, $message, $id = '', $timeout = false ) {
	return array( 'status' => $status, 'message' => $message, 'id' => $id, 'timeout' => $timeout );
}

function irn_send_sms( $to, $body, $reply_from = '' ) {
	$sid = irn_setting( 'twilio_sid' ); $token = irn_setting( 'twilio_token' ); $from = irn_setting( 'sms_from' );
	if ( '' === $sid || '' === $token || '' === $from ) {
		return irn_result( 'pause', 'Twilio SMS settings are incomplete (Account SID, Auth Token and SMS sender are required).' );
	}
	$args = array( 'To' => $to, 'Body' => $body );
	if ( '' !== $reply_from ) {
		$from = $reply_from; // verified recipient of the inbound SMS, keeps the same conversation number
	}
	$args[ 0 === strpos( $from, 'MG' ) ? 'MessagingServiceSid' : 'From' ] = $from;
	$res = wp_remote_post(
		'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
		array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ) ), 'body' => $args )
	);
	return irn_classify_twilio( $res );
}

function irn_classify_twilio( $res ) {
	if ( is_wp_error( $res ) ) {
		return irn_result( 'retry', 'Network error: ' . $res->get_error_message(), '', true );
	}
	$http = (int) wp_remote_retrieve_response_code( $res );
	$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$code = (int) ( $json['code'] ?? 0 );
	$text = 'Twilio ' . ( $code ? $code : $http ) . ': ' . ( $json['message'] ?? 'HTTP ' . $http );
	if ( $http >= 200 && $http < 300 ) {
		if ( empty( $json['sid'] ) ) {
			return irn_result( 'retry', 'Twilio returned no message ID.', '', true );
		}
		return irn_result( 'ok', 'Accepted by Twilio', (string) $json['sid'] );
	}
	if ( 401 === $http || 403 === $http || in_array( $code, array( 20003, 20005, 21212, 21606 ), true ) ) {
		return irn_result( 'pause', $text );
	}
	if ( 21610 === $code ) {
		return irn_result( 'unsubscribed', $text );
	}
	if ( 429 === $http || $http >= 500 || in_array( $code, array( 20429, 14107 ), true ) ) {
		return irn_result( 'retry', $text );
	}
	return irn_result( 'fail', $text ); // e.g. 21211 invalid number, 21608 trial: unverified number, 21614 not mobile
}

function irn_send_email( $to, $template, $data ) {
	$key = irn_setting( 'sendgrid_key' ); $from = irn_setting( 'email_from' );
	$template_id = irn_setting( 'tpl_' . $template );
	if ( '' === $key || '' === $from || '' === $template_id ) {
		return irn_result( 'pause', 'SendGrid settings are incomplete (API key, From address and the ' . $template . ' template ID are required).' );
	}
	$mail = array(
		'personalizations' => array( array( 'to' => array( array( 'email' => $to ) ), 'dynamic_template_data' => (object) $data ) ),
		'from'             => array_filter( array( 'email' => $from, 'name' => irn_setting( 'email_from_name' ) ) ),
		'template_id'      => $template_id,
	);
	if ( '' !== irn_setting( 'email_reply_to' ) ) {
		$mail['reply_to'] = array( 'email' => irn_setting( 'email_reply_to' ) );
	}
	if ( preg_match( '/^\d+$/D', irn_setting( 'unsubscribe_group' ) ) ) {
		$mail['asm'] = array( 'group_id' => (int) irn_setting( 'unsubscribe_group' ) );
	}
	$res = wp_remote_post(
		'https://api.sendgrid.com/v3/mail/send',
		array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $mail ) )
	);
	return irn_classify_sendgrid( $res );
}

function irn_classify_sendgrid( $res ) {
	if ( is_wp_error( $res ) ) {
		return irn_result( 'retry', 'Network error: ' . $res->get_error_message(), '', true );
	}
	$http = (int) wp_remote_retrieve_response_code( $res );
	if ( $http >= 200 && $http < 300 ) {
		$id = (string) wp_remote_retrieve_header( $res, 'x-message-id' );
		return irn_result( 'ok', 'Accepted by SendGrid', '' !== $id ? $id : 'accepted' );
	}
	$json   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$errors = isset( $json['errors'] ) && is_array( $json['errors'] ) ? $json['errors'] : array();
	$text   = 'SendGrid ' . $http . ': ' . implode( '; ', array_filter( array_map( function ( $e ) { return is_array( $e ) ? ( $e['message'] ?? '' ) : ''; }, $errors ) ) );
	if ( 401 === $http || 403 === $http ) {
		return irn_result( 'pause', $text ); // bad key, missing permission, unverified sender, credits exhausted
	}
	foreach ( $errors as $e ) {
		if ( is_array( $e ) && in_array( $e['field'] ?? '', array( 'template_id', 'from', 'from.email', 'reply_to', 'asm.group_id' ), true ) ) {
			return irn_result( 'pause', $text ); // configuration, not the recipient
		}
	}
	if ( 429 === $http || $http >= 500 ) {
		return irn_result( 'retry', $text );
	}
	return irn_result( 'fail', $text );
}
