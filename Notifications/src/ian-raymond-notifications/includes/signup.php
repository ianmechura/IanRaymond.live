<?php
/** Public signup: the delayed modal on every public page and its REST route. */
defined( 'ABSPATH' ) || exit;

const IRN_RATE_LIMIT  = 5;   // signups per IP…
const IRN_RATE_WINDOW = 900; // …per 15 minutes

/** Lowercased, trimmed email, or '' if invalid. */
function irn_normalize_email( $value ) {
	$email = strtolower( trim( (string) $value ) );
	return strlen( $email ) <= 191 && is_email( $email ) ? $email : '';
}

/**
 * US mobile number in E.164 (+1XXXXXXXXXX). Returns '' for anything else, or 'toll-free' for
 * toll-free/premium area codes, which are never texted.
 */
function irn_normalize_phone( $value ) {
	$digits = preg_replace( '/\D+/', '', (string) $value );
	if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
		$digits = substr( $digits, 1 );
	}
	if ( ! preg_match( '/^[2-9]\d{2}[2-9]\d{6}$/D', $digits ) ) {
		return '';
	}
	if ( in_array( substr( $digits, 0, 3 ), array( '800', '833', '844', '855', '866', '877', '888', '900' ), true ) ) {
		return 'toll-free';
	}
	return '+1' . $digits;
}

function irn_welcome_payload( $channel ) {
	if ( 'sms' === $channel ) {
		return array( 'body' => "Ian Raymond: You're signed up for texts about new music and live shows. Reply here to reach Ian. Msg frequency varies. Msg & data rates may apply. Reply HELP for help, STOP to cancel." );
	}
	return array(
		'template' => 'welcome',
		'data'     => array(
			'subject'    => "You're on the list",
			'headline'   => "You're on the list",
			'message'    => "Thanks for signing up. You'll be the first to know when I release new music or add a live show. Every email has an unsubscribe link at the bottom if you ever want to stop.",
			'action_url' => home_url( '/listen/' ),
			'action_label' => 'Listen now',
			'site_url'   => home_url( '/' ),
		),
	);
}

/**
 * Add or refresh a subscriber and, only for a new one, commit the welcome message — in one transaction.
 * Returns the subscriber ID.
 */
function irn_upsert_subscriber( $channel, $destination, $source, $consent = array() ) {
	global $wpdb;
	$subs = irn_table( 'subscribers' );
	$now  = irn_now();
	$c    = wp_parse_args( $consent, array( 'version' => '', 'text' => '', 'ip' => '', 'ua' => '' ) );
	$wpdb->query( 'START TRANSACTION' );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO $subs (channel, destination, created_at, source, consent_version, consent_at, consent_ip, consent_user_agent, consent_text) VALUES (%s, %s, %s, %s, %s, NULLIF(%s, ''), %s, %s, %s)",
			$channel, $destination, $now, $source, $c['version'], 'sms' === $channel ? $now : '', $c['ip'], $c['ua'], $c['text']
		)
	);
	if ( 1 === (int) $wpdb->rows_affected ) {
		$id = (int) $wpdb->insert_id;
		irn_enqueue( 'welcome', 0, $id, $channel, $destination, irn_welcome_payload( $channel ) );
	} else {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $subs WHERE destination = %s", $destination ) );
		if ( 'sms' === $channel ) { // keep the newest affirmative opt-in as the consent evidence
			$wpdb->query( $wpdb->prepare( "UPDATE $subs SET consent_version = %s, consent_at = %s, consent_ip = %s, consent_user_agent = %s, consent_text = %s WHERE id = %d", $c['version'], $now, $c['ip'], $c['ua'], $c['text'], $id ) );
		}
	}
	$wpdb->query( 'COMMIT' );
	return $id;
}

function irn_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/** Simple fixed-window limit per IP address. */
function irn_rate_limited() {
	$key   = 'irn_rl_' . md5( irn_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= IRN_RATE_LIMIT ) {
		return true;
	}
	set_transient( $key, $count + 1, IRN_RATE_WINDOW );
	return false;
}

function irn_subscribe( WP_REST_Request $req ) {
	$thanks = array( 'ok' => true, 'message' => "Thanks! You're on the list." );
	if ( '' !== trim( (string) $req->get_param( 'website' ) ) ) {
		return $thanks; // bot trap: look successful, store nothing
	}
	if ( irn_rate_limited() ) {
		return new WP_Error( 'irn_rate', 'Too many signups from this connection. Please try again in a few minutes.', array( 'status' => 429 ) );
	}
	$channel = (string) $req->get_param( 'channel' );
	if ( 'email' === $channel ) {
		$destination = irn_normalize_email( $req->get_param( 'contact' ) );
		if ( '' === $destination ) {
			return new WP_Error( 'irn_email', 'Enter a valid email address.', array( 'status' => 400 ) );
		}
		$consent = array();
	} elseif ( 'sms' === $channel ) {
		$destination = irn_normalize_phone( $req->get_param( 'contact' ) );
		if ( 'toll-free' === $destination ) {
			return new WP_Error( 'irn_phone', 'Toll-free numbers can’t receive these texts. Enter a US mobile number.', array( 'status' => 400 ) );
		}
		if ( '' === $destination ) {
			return new WP_Error( 'irn_phone', 'Enter a US mobile number, like (214) 555-0123.', array( 'status' => 400 ) );
		}
		if ( true !== rest_sanitize_boolean( $req->get_param( 'sms_consent' ) ) ) {
			return new WP_Error( 'irn_consent', 'Check the box to agree to receive texts.', array( 'status' => 400 ) );
		}
		$consent = array(
			'version' => IRN_CONSENT_VERSION,
			'text'    => irn_consent_text(),
			'ip'      => irn_client_ip(),
			'ua'      => substr( sanitize_text_field( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
		);
	} else {
		return new WP_Error( 'irn_channel', 'Choose email or mobile.', array( 'status' => 400 ) );
	}
	$source = esc_url_raw( (string) $req->get_param( 'source' ) );
	if ( wp_parse_url( $source, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$source = home_url( '/' );
	}
	irn_upsert_subscriber( $channel, $destination, substr( $source, 0, 255 ), $consent );
	irn_kick_worker();
	return $thanks;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'irn/v1',
			'/subscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'irn_subscribe',
			)
		);
	}
);

// ---- the modal: public pages through wp_enqueue_scripts; /listen (the player's own page) by adding tags before </body> ----
function irn_modal_config() {
	return array(
		'api'        => esc_url_raw( rest_url( 'irn/v1/subscribe' ) ),
		'delay'      => 20000,
		'consent'    => irn_consent_text(),
		'termsUrl'   => esc_url_raw( irn_setting( 'terms_url' ) ),
		'privacyUrl' => esc_url_raw( irn_setting( 'privacy_url' ) ),
	);
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$base = plugin_dir_url( __DIR__ ) . 'assets/';
		wp_enqueue_style( 'irn-signup', $base . 'signup.css', array(), IRN_VERSION );
		wp_enqueue_script( 'irn-signup', $base . 'signup.js', array(), IRN_VERSION, true );
		wp_add_inline_script( 'irn-signup', 'window.IRN_SIGNUP=' . wp_json_encode( irn_modal_config() ) . ';', 'before' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( get_query_var( 'irl_listen' ) ) {
			ob_start( 'irn_add_modal_to_page' );
		}
	},
	5
);

function irn_add_modal_to_page( $html ) {
	if ( false === stripos( $html, '</body>' ) ) {
		return $html;
	}
	$base = plugin_dir_url( __DIR__ ) . 'assets/';
	$tags = '<link rel="stylesheet" href="' . esc_url( $base . 'signup.css?ver=' . IRN_VERSION ) . '">'
		. '<script>window.IRN_SIGNUP=' . wp_json_encode( irn_modal_config(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';</script>'
		. '<script src="' . esc_url( $base . 'signup.js?ver=' . IRN_VERSION ) . '" defer></script>';
	$pos = strripos( $html, '</body>' );
	return substr( $html, 0, $pos ) . $tags . substr( $html, $pos );
}

/** A server-rendered consent step that is publicly reviewable without opening the modal. */
function irn_signup_shortcode() {
	ob_start();
	?>
	<div id="irn-public-signup" class="irn-inline irn-modal">
		<p>Get Ian Raymond's new music releases and live show announcements by text. US mobile numbers only.</p>
		<form>
			<label for="irn-public-phone">US mobile number</label>
			<input id="irn-public-phone" type="tel" name="contact" autocomplete="tel" required>
			<label class="irn-consent"><input type="checkbox" name="sms_consent" required value="1"><span><?php echo esc_html( irn_consent_text() ); ?> <a href="<?php echo esc_url( irn_setting( 'terms_url' ) ); ?>">Terms &amp; Conditions</a> · <a href="<?php echo esc_url( irn_setting( 'privacy_url' ) ); ?>">Privacy Policy</a></span></label>
			<input class="irn-trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
			<p class="irn-error" role="alert"></p>
			<button class="irn-submit" type="submit">Sign me up</button>
			<p class="irn-thanks" role="status"></p>
		</form>
		<noscript>Enable JavaScript to submit this form.</noscript>
		<p class="irn-note">For help, email <a href="mailto:<?php echo esc_attr( IRN_CONTACT_EMAIL ); ?>"><?php echo esc_html( IRN_CONTACT_EMAIL ); ?></a>.</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'irn_signup', 'irn_signup_shortcode' );
