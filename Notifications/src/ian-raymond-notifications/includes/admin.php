<?php
/** Listener Updates admin: Subscribers, Announcements, Replies, Settings. Administrators only. */
defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Listener Updates', 'Listener Updates', 'manage_options', 'irn', 'irn_admin_page', 'dashicons-megaphone', 58 );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_irn' === $hook ) {
			wp_enqueue_style( 'irn-admin', plugin_dir_url( __DIR__ ) . 'assets/admin.css', array(), IRN_VERSION );
		}
	}
);

/** The paused-queue warning appears on every admin screen until someone resumes sending. */
add_action(
	'admin_notices',
	function () {
		$p = irn_paused();
		if ( ! $p || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Listener Updates: sending is paused.</strong> ' . esc_html( $p['reason'] )
			. '</p><p>Fix the provider settings, then <a href="' . esc_url( admin_url( 'admin.php?page=irn&tab=settings' ) ) . '">resume sending</a>. Unsent messages are waiting and will go out after you resume.</p></div>';
	}
);

function irn_admin_url( $tab, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'irn', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
}

function irn_action_form( $do, $fields, $label, $class = 'button', $confirm = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	wp_nonce_field( 'irn_' . $do );
	echo '<input type="hidden" name="action" value="irn"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<button class="' . esc_attr( $class ) . '"' . ( $confirm ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>' . esc_html( $label ) . '</button></form> ';
}

function irn_local_time( $utc ) {
	return $utc ? wp_date( 'M j, Y g:i a', strtotime( $utc . ' UTC' ) ) : '';
}

// ---- actions ----
add_action(
	'admin_post_irn',
	function () {
		$do = sanitize_key( (string) ( $_POST['do'] ?? '' ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'irn_' . $do );
		global $wpdb;
		$id  = absint( $_POST['id'] ?? 0 );
		$tab = 'announcements';
		$msg = '';
		switch ( $do ) {
			case 'reply':
				$key = sanitize_text_field( wp_unslash( (string) ( $_POST['request_key'] ?? '' ) ) );
				check_admin_referer( 'irn_reply_' . $id . '_' . $key, 'request_signature' );
				$body = sanitize_textarea_field( wp_unslash( (string) ( $_POST['body'] ?? '' ) ) );
				$result = irn_queue_reply( $id, $body, $key );
				$msg = is_wp_error( $result ) ? $result->get_error_message() : $result;
				if ( is_wp_error( $result ) ) {
					set_transient( 'irn_reply_draft_' . get_current_user_id(), array( 'id' => $id, 'body' => $body ), HOUR_IN_SECONDS );
				} else {
					delete_transient( 'irn_reply_draft_' . get_current_user_id() );
				}
				$tab = 'replies';
				break;
			case 'save':
				$fields = wp_unslash( array_intersect_key( (array) $_POST, array_flip( array( 'email_subject', 'headline', 'message', 'action_url', 'sms_body', 'date', 'venue', 'location' ) ) ) );
				$msg    = irn_update_announcement( $id, $fields ) ? 'Saved. Nothing was sent.' : 'This announcement can no longer be edited.';
				break;
			case 'regenerate':
				$msg = irn_regenerate_announcement( $id ) ? 'New copy generated. Nothing was sent.' : 'This announcement can no longer be changed.';
				break;
			case 'discard':
				$msg = irn_discard_announcement( $id ) ? 'Discarded.' : 'This announcement was already approved or discarded.';
				break;
			case 'approve':
				$n   = irn_approve_announcement( $id );
				$msg = false === $n ? 'This announcement was already approved or discarded — nothing new was queued.' : sprintf( 'Approved. %d message(s) queued; they send in the background.', $n );
				break;
			case 'pause_drafts':
				update_option( 'irn_announcements_paused', empty( $_POST['paused'] ) ? '0' : '1', false );
				$msg = empty( $_POST['paused'] ) ? 'New releases and shows will create announcements again.' : 'Paused: new releases and shows will not create announcements.';
				break;
			case 'delete_subscriber':
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . irn_table( 'subscribers' ) . ' WHERE id = %d', $id ) );
				$tab = 'subscribers';
				$msg = 'Subscriber deleted.';
				break;
			case 'settings':
				$saved = get_option( 'irn_settings', array() );
				$saved = is_array( $saved ) ? $saved : array();
				foreach ( array_keys( irn_setting_fields() ) as $key ) {
					$value = trim( sanitize_text_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) ) );
					if ( in_array( $key, IRN_SECRETS, true ) && '' === $value ) {
						continue; // blank secret field keeps the saved value
					}
					$saved[ $key ] = in_array( $key, array( 'terms_url', 'privacy_url' ), true ) ? esc_url_raw( $value ) : $value;
				}
				update_option( 'irn_settings', $saved, false );
				$tab = 'settings';
				$msg = 'Settings saved.';
				break;
			case 'resume':
				irn_resume();
				$tab = 'settings';
				$msg = 'Sending resumed.';
				break;
		}
		set_transient( 'irn_msg_' . get_current_user_id(), $msg, 60 );
		wp_safe_redirect( irn_admin_url( $tab, 'reply' === $do ? array( 'conversation' => $id ) : array() ) );
		exit;
	}
);

// ---- screens ----
function irn_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = array( 'subscribers' => 'Subscribers', 'announcements' => 'Announcements', 'replies' => 'Replies', 'settings' => 'Settings' );
	$tab  = sanitize_key( (string) ( $_GET['tab'] ?? 'subscribers' ) );
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'subscribers';
	echo '<div class="wrap irn"><h1>Listener Updates</h1>';
	$msg = get_transient( 'irn_msg_' . get_current_user_id() );
	if ( $msg ) {
		delete_transient( 'irn_msg_' . get_current_user_id() );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( irn_admin_url( $k ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	call_user_func( 'irn_tab_' . $tab );
	echo '</div>';
}

function irn_tab_subscribers() {
	global $wpdb;
	$subs  = irn_table( 'subscribers' );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $subs" );
	$since = sanitize_text_field( (string) ( $_GET['since'] ?? '' ) );
	$since = preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $since ) ? $since : wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
	$since_utc = get_gmt_from_date( $since . ' 00:00:00' );
	$new   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $subs WHERE created_at >= %s", $since_utc ) );
	$page  = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, channel, destination, created_at FROM $subs ORDER BY id DESC LIMIT 50 OFFSET %d", ( $page - 1 ) * 50 ) );
	?>
	<div class="irn-stats">
		<div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> current subscribers</div>
		<form method="get"><input type="hidden" name="page" value="irn"><input type="hidden" name="tab" value="subscribers">
			<strong><?php echo esc_html( number_format_i18n( $new ) ); ?></strong> new since
			<input type="date" name="since" value="<?php echo esc_attr( $since ); ?>" onchange="this.form.submit()">
		</form>
	</div>
	<p class="description">Email subscribers who unsubscribe through SendGrid's link stay in this list and count (SendGrid still blocks delivery to them). Texting STOP removes a number.</p>
	<table class="widefat striped"><thead><tr><th>Contact</th><th>Channel</th><th>Signed up</th><th></th></tr></thead><tbody>
	<?php
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->destination ) . '</td><td>' . ( 'sms' === $r->channel ? 'Text' : 'Email' ) . '</td><td>' . esc_html( irn_local_time( $r->created_at ) ) . '</td><td>';
		irn_action_form( 'delete_subscriber', array( 'id' => $r->id ), 'Delete', 'button-link-delete', 'Delete ' . $r->destination . ' from the list?' );
		echo '</td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr><td colspan="4">No subscribers yet.</td></tr>';
	}
	echo '</tbody></table>';
	if ( $total > 50 ) {
		echo '<p>';
		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( irn_admin_url( 'subscribers', array( 'paged' => $page - 1, 'since' => $since ) ) ) . '">← Newer</a> ';
		}
		if ( $page * 50 < $total ) {
			echo '<a class="button" href="' . esc_url( irn_admin_url( 'subscribers', array( 'paged' => $page + 1, 'since' => $since ) ) ) . '">Older →</a>';
		}
		echo '</p>';
	}
}

function irn_tab_announcements() {
	global $wpdb;
	$ann   = irn_table( 'announcements' );
	$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . irn_table( 'subscribers' ) );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="irn-pause">';
	wp_nonce_field( 'irn_pause_drafts' );
	echo '<input type="hidden" name="action" value="irn"><input type="hidden" name="do" value="pause_drafts">'
		. '<label><input type="checkbox" name="paused" value="1" onchange="this.form.submit()"' . checked( irn_announcements_paused(), true, false ) . '> <strong>Pause new announcements</strong></label>'
		. ' <span class="description">While checked, adding releases or publishing shows creates no drafts (use it while renaming or re-adding things). Welcome messages and approved announcements still send.</span></form>';

	$drafts = $wpdb->get_results( "SELECT * FROM $ann WHERE state = 'awaiting' ORDER BY id DESC" );
	echo '<h2>Awaiting approval</h2>';
	if ( ! $drafts ) {
		echo '<p>No announcements are waiting. A draft appears here when you add a new release in the Music Player or publish a new show.</p>';
	}
	foreach ( $drafts as $a ) {
		$f = json_decode( (string) $a->facts, true );
		echo '<div class="irn-card"><p class="irn-event">' . esc_html( $a->source_event ) . ' · ' . esc_html( irn_local_time( $a->created_at ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'irn_save' );
		echo '<input type="hidden" name="action" value="irn"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="' . esc_attr( $a->id ) . '">';
		if ( 'show' === $a->source_type ) {
			echo '<div class="irn-row">';
			foreach ( array( 'date' => 'Date / time', 'venue' => 'Venue', 'location' => 'Location' ) as $k => $label ) {
				echo '<label>' . esc_html( $label ) . '<input type="text" name="' . esc_attr( $k ) . '" value="' . esc_attr( $f[ $k ] ?? '' ) . '"></label>';
			}
			echo '</div>';
		} else {
			echo '<p><strong>' . esc_html( ucfirst( $f['type'] ) . ': ' . $f['title'] ) . '</strong></p>';
		}
		echo '<label>Email subject<input type="text" name="email_subject" value="' . esc_attr( $a->email_subject ) . '"></label>'
			. '<label>Email headline<input type="text" name="headline" value="' . esc_attr( $a->headline ) . '"></label>'
			. '<label>Email message<textarea name="message" rows="4">' . esc_textarea( $a->message ) . '</textarea></label>'
			. '<label>' . ( 'show' === $a->source_type ? 'Show details' : 'Listen now' ) . ' link<input type="url" name="action_url" value="' . esc_attr( $a->action_url ) . '"></label>'
			. '<label>Text message<textarea name="sms_body" rows="3">' . esc_textarea( $a->sms_body ) . '</textarea></label>'
			. '<p><button class="button">Save edits</button></p></form><p class="irn-actions">';
		irn_action_form( 'regenerate', array( 'id' => $a->id ), 'Request new copy' );
		irn_action_form( 'approve', array( 'id' => $a->id ), 'Approve and send', 'button button-primary', sprintf( 'Send this announcement to all %d current subscribers? Save any edits first.', $count ) );
		irn_action_form( 'discard', array( 'id' => $a->id ), 'Discard', 'button-link-delete', 'Discard this announcement permanently?' );
		echo '</p><p class="description">The email uses your branded SendGrid template with these values; preview the layout in SendGrid.</p></div>';
	}

	$out  = irn_table( 'outbox' );
	$past = $wpdb->get_results( "SELECT a.*, SUM(o.state = 'submitted') AS submitted, SUM(o.state = 'failed') AS failed, SUM(o.state = 'pending') AS pending FROM $ann a LEFT JOIN $out o ON o.announcement_id = a.id WHERE a.state <> 'awaiting' GROUP BY a.id ORDER BY a.id DESC LIMIT 20" );
	if ( $past ) {
		echo '<h2>Earlier</h2><table class="widefat striped"><thead><tr><th>Announcement</th><th>Status</th><th>Messages</th></tr></thead><tbody>';
		foreach ( $past as $a ) {
			$status = array( 'sending' => 'Sending', 'sent' => 'Sent', 'discarded' => 'Discarded' )[ $a->state ] ?? $a->state;
			$when   = 'sent' === $a->state ? $a->sent_at : ( 'sending' === $a->state ? $a->approved_at : '' );
			$counts = 'discarded' === $a->state ? '' : sprintf( '%d submitted, %d failed%s', $a->submitted, $a->failed, $a->pending ? ', ' . (int) $a->pending . ' waiting' : '' );
			echo '<tr><td>' . esc_html( $a->email_subject ) . '<br><span class="description">' . esc_html( $a->source_event ) . '</span></td><td>' . esc_html( trim( $status . ' ' . irn_local_time( $when ) ) ) . '</td><td>' . esc_html( $counts ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

function irn_tab_replies() {
	global $wpdb;
	if ( ! empty( $_GET['conversation'] ) ) {
		irn_render_conversation( absint( $_GET['conversation'] ) );
		return;
	}
	$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . irn_table( 'replies' ) . ' ORDER BY id DESC LIMIT 50 OFFSET %d', ( $page - 1 ) * 50 ) );
	echo '<p class="description">Incoming messages, newest first. Open a text conversation to reply here. Email replies are read-only.</p>';
	echo '<table class="widefat striped"><thead><tr><th>From</th><th>Received</th><th>Channel</th><th>Message</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->sender ) . '</td><td>' . esc_html( irn_local_time( $r->received_at ) ) . '</td><td>' . ( 'sms' === $r->channel ? 'Text' : 'Email' ) . '</td><td>'
			. ( '' !== $r->subject ? '<strong>' . esc_html( $r->subject ) . '</strong><br>' : '' ) . nl2br( esc_html( $r->body ) ) . ( 'sms' === $r->channel ? '<p><a class="button" href="' . esc_url( irn_admin_url( 'replies', array( 'conversation' => $r->id ) ) ) . '">Open conversation / Reply</a></p>' : '' ) . '</td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr><td colspan="4">No replies yet.</td></tr>';
	}
	echo '</tbody></table><p>';
	if ( $page > 1 ) {
		echo '<a class="button" href="' . esc_url( irn_admin_url( 'replies', array( 'paged' => $page - 1 ) ) ) . '">← Newer</a> ';
	}
	if ( count( $rows ) === 50 ) {
		echo '<a class="button" href="' . esc_url( irn_admin_url( 'replies', array( 'paged' => $page + 1 ) ) ) . '">Older →</a>';
	}
	echo '</p>';
}

function irn_tab_settings() {
	echo '<h2>Configuration checks</h2><ul>';
	$issues = irn_sms_setup_issues();
	foreach ( array( 'sendgrid_key', 'email_from', 'tpl_welcome', 'tpl_music', 'tpl_show', 'unsubscribe_group' ) as $key ) {
		if ( '' === irn_setting( $key ) ) {
			$issues[] = 'Missing: ' . irn_setting_fields()[ $key ];
		}
	}
	foreach ( $issues as $issue ) {
		echo '<li>' . esc_html( $issue ) . '</li>';
	}
	echo '</ul><p>' . ( $issues ? 'Complete the settings below.' : 'Required settings are present.' ) . ' These checks do not verify provider credentials, sender approval, templates, or webhook configuration.</p>';
	echo '<p>For two-way SMS, configure your Twilio number’s incoming-message webhook to the exact POST address below. If using a Messaging Service, configure incoming messages to use that webhook or defer to the number’s webhook. Use an SMS-capable number in the same account.</p>';
	$p = irn_paused();
	if ( $p ) {
		echo '<div class="irn-card irn-paused"><p><strong>Sending is paused</strong> since ' . esc_html( irn_local_time( $p['at'] ) ) . ': ' . esc_html( $p['reason'] ) . '</p><p>Correct the settings below, save, then resume. Waiting messages keep their place in the queue.</p>';
		irn_action_form( 'resume', array(), 'Resume sending', 'button button-primary' );
		echo '</div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'irn_settings' );
	echo '<input type="hidden" name="action" value="irn"><input type="hidden" name="do" value="settings"><table class="form-table">';
	foreach ( irn_setting_fields() as $key => $label ) {
		$value  = irn_setting( $key );
		$secret = in_array( $key, IRN_SECRETS, true );
		echo '<tr><th scope="row"><label for="irn-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( irn_setting_from_config( $key ) ) {
			echo '<em>Set in wp-config.php</em>' . ( $secret ? '' : ': <code>' . esc_html( $value ) . '</code>' );
		} elseif ( $secret ) {
			echo '<input type="password" class="regular-text" id="irn-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( irn_mask( $value ) ) . '"> <span class="description">Leave blank to keep the saved value.</span>';
		} else {
			echo '<input type="text" class="regular-text" id="irn-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '</td></tr>';
	}
	echo '</table>';
	submit_button( 'Save settings' );
	echo '</form><h2>Webhook addresses</h2><table class="form-table">'
		. '<tr><th>Twilio: “A message comes in” (HTTP POST)</th><td><code>' . esc_html( irn_twilio_webhook_url() ) . '</code></td></tr>'
		. '<tr><th>SendGrid Inbound Parse destination URL</th><td><code>' . esc_html( irn_email_webhook_url() ) . '</code><p class="description">Keep this address private; its key is what authorizes inbound email.</p></td></tr>'
		. '</table>';
}
