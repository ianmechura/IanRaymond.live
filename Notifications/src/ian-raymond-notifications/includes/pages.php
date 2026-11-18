<?php
/**
 * On first activation, publish the pages the SMS consent links to (and carriers review for 10DLC):
 * Text Message Terms, and a Privacy Policy if the site has none. Existing pages are never changed.
 */
defined( 'ABSPATH' ) || exit;

const IRN_CONTACT_EMAIL = 'Ian@IanRaymond.live';
const IRN_PAGES_VERSION = 3; // 3: add a public SMS signup page

function irn_terms_content( $privacy_url ) {
	$e = IRN_CONTACT_EMAIL;
	$p = esc_url( $privacy_url );
	return "<!-- wp:paragraph --><p><strong>Program:</strong> Ian Raymond updates. Text messages announcing Ian Raymond's new music releases and live show dates.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>How you join:</strong> by entering your mobile number in the signup form on ianraymond.live and checking the box agreeing to receive texts. You'll get one confirmation text when you join.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>Message frequency varies</strong>, typically a few messages a month or fewer, only when there is new music or a new show.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>Message and data rates may apply.</strong> Carriers are not liable for delayed or undelivered messages.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>To stop:</strong> reply <strong>STOP</strong> to any message. You'll receive one final message confirming you've been unsubscribed and no further texts will be sent. To rejoin after STOP, reply <strong>START</strong> to unblock texts, then sign up again on the site to restore announcements.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>For help:</strong> reply <strong>HELP</strong> to any message, or email <a href=\"mailto:$e\">$e</a>.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Consent to receive texts is not a condition of any purchase. US mobile numbers only.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>Privacy:</strong> mobile numbers and text-message consent are never sold or shared with third parties or affiliates for marketing purposes. See the <a href=\"$p\">Privacy Policy</a>.</p><!-- /wp:paragraph -->";
}

function irn_privacy_content() {
	$e = IRN_CONTACT_EMAIL;
	return "<!-- wp:paragraph --><p>This policy explains what ianraymond.live collects and how it is used. Questions: <a href=\"mailto:$e\">$e</a>.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Email and text updates</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>If you sign up for updates, we store the email address or mobile number you enter, when you signed up, and the page you signed up from. For text messages we also store the consent wording you agreed to, the time, your IP address and browser type as a record of your opt-in. This information is used only to send announcements about Ian Raymond's new music and live shows.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Messages are delivered by Twilio (text) and Twilio SendGrid (email), which process your contact details only to deliver them. <strong>Mobile numbers and text-message opt-in data and consent are not shared with or sold to third parties or affiliates for marketing or promotional purposes.</strong></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>You can stop at any time: use the unsubscribe link in any email, or reply STOP to any text. Replies you send are stored so Ian can read them. To have your information deleted, email <a href=\"mailto:$e\">$e</a>.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Signup reminder</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>The signup window remembers in your browser's local storage that you signed up or asked not to see it again. It is not used to track you and is not shared.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Site statistics</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>The site counts page visits in aggregate without identifying individual visitors.</p><!-- /wp:paragraph -->";
}

function irn_create_policy_pages() {
	if ( (int) get_option( 'irn_pages_created' ) >= IRN_PAGES_VERSION ) {
		return;
	}
	$settings = get_option( 'irn_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();
	$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( ! $privacy_id || 'publish' !== get_post_status( $privacy_id ) ) {
		$existing = get_page_by_path( 'privacy-policy' );
		if ( $existing && 'publish' !== get_post_status( $existing->ID ) ) {
			// WordPress's unpublished starter draft: fill and publish it (a published page is never touched).
			wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish', 'post_title' => 'Privacy Policy', 'post_content' => irn_privacy_content() ) );
		}
		$privacy_id = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy-policy', 'post_title' => 'Privacy Policy', 'post_content' => irn_privacy_content() ) );
		if ( $privacy_id && ! is_wp_error( $privacy_id ) ) {
			update_option( 'wp_page_for_privacy_policy', (int) $privacy_id );
		}
	}
	$terms    = get_page_by_path( 'sms-terms' );
	$terms_id = $terms ? $terms->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'sms-terms', 'post_title' => 'Text Message Terms', 'post_content' => irn_terms_content( get_permalink( $privacy_id ) ) ) );
	if ( $terms_id && ! is_wp_error( $terms_id ) && empty( $settings['terms_url'] ) ) {
		$settings['terms_url'] = get_permalink( $terms_id );
	}
	update_option( 'irn_settings', $settings, false );
	$signup = get_page_by_path( 'text-updates' );
	if ( ! $signup ) {
		$signup_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'text-updates', 'post_title' => 'Ian Raymond Text Updates', 'post_content' => '<!-- wp:shortcode -->[irn_signup]<!-- /wp:shortcode -->' ), true );
		if ( ! $signup_id || is_wp_error( $signup_id ) ) {
			return; // Retry page creation on the next request.
		}
	}
	update_option( 'irn_pages_created', IRN_PAGES_VERSION, false );
}
