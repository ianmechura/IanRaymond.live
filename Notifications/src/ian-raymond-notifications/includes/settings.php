<?php
/** Provider configuration. Any key can instead be set in wp-config.php as IRN_<KEY> (e.g. IRN_SENDGRID_KEY), which wins. */
defined( 'ABSPATH' ) || exit;

const IRN_SECRETS = array( 'twilio_token', 'sendgrid_key' );

function irn_setting_fields() {
	return array(
		'twilio_sid'        => 'Twilio Account SID',
		'twilio_token'      => 'Twilio Auth Token',
		'sms_from'          => 'SMS sender (your Twilio number in +1 format, or a Messaging Service SID starting with MG)',
		'sendgrid_key'      => 'SendGrid API key (Mail Send permission)',
		'email_from'        => 'From email address (on your authenticated domain)',
		'email_from_name'   => 'From name',
		'email_reply_to'    => 'Reply-to address (on the Inbound Parse reply subdomain)',
		'tpl_welcome'       => 'SendGrid template ID — Welcome',
		'tpl_music'         => 'SendGrid template ID — New music',
		'tpl_show'          => 'SendGrid template ID — New live show',
		'unsubscribe_group' => 'SendGrid unsubscribe group ID',
		'terms_url'         => 'SMS Terms page URL',
		'privacy_url'       => 'Privacy Policy page URL',
	);
}

function irn_setting( $key ) {
	$const = 'IRN_' . strtoupper( $key );
	if ( defined( $const ) ) {
		return (string) constant( $const );
	}
	$all = get_option( 'irn_settings', array() );
	$value = is_array( $all ) && isset( $all[ $key ] ) ? (string) $all[ $key ] : '';
	if ( '' === $value && 'privacy_url' === $key && function_exists( 'get_privacy_policy_url' ) ) {
		$value = (string) get_privacy_policy_url();
	}
	return $value;
}

function irn_setting_from_config( $key ) {
	return defined( 'IRN_' . strtoupper( $key ) );
}

/** Secret shown in the admin as a hint only, never the value. */
function irn_mask( $value ) {
	return '' === $value ? '' : 'saved ••••' . substr( $value, -4 );
}

/** Random key in the SendGrid Inbound Parse URL; created once. */
function irn_inbound_key() {
	$key = get_option( 'irn_inbound_key' );
	if ( ! $key ) {
		$key = wp_generate_password( 32, false );
		update_option( 'irn_inbound_key', $key, false );
	}
	return $key;
}

/** SMS consent wording; bump the version whenever the wording changes. */
const IRN_CONSENT_VERSION = 'sms-v1';
function irn_consent_text() {
	return 'I agree to receive recurring automated text messages from Ian Raymond about new music and live shows at the number provided. '
		. 'Message frequency varies. Msg & data rates may apply. Reply HELP for help, STOP to cancel. Consent is not a condition of purchase.';
}
