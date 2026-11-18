<?php
/**
 * Plugin Name: Ian Raymond — Listener Notifications
 * Description: Listener signup (email or text), approved new-music and live-show announcements through Twilio SendGrid and Twilio SMS. Manage under Listener Updates.
 * Version: 1.1.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Ian Raymond
 */
defined( 'ABSPATH' ) || exit;

const IRN_VERSION    = '1.1.1';
const IRN_DB_VERSION = '2';

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/providers.php';
require_once __DIR__ . '/includes/queue.php';
require_once __DIR__ . '/includes/signup.php';
require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/inbound.php';
require_once __DIR__ . '/includes/conversations.php';
require_once __DIR__ . '/includes/pages.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

function irn_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'irn_' . $name;
}

/** Current UTC time in MySQL format; every stored time is UTC. */
function irn_now( $offset = 0 ) {
	return gmdate( 'Y-m-d H:i:s', time() + $offset );
}

/** Create or upgrade the tables. Safe to run repeatedly (dbDelta). */
function irn_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE " . irn_table( 'subscribers' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  channel varchar(5) NOT NULL,
  destination varchar(191) NOT NULL,
  created_at datetime NOT NULL,
  source varchar(255) NOT NULL DEFAULT '',
  consent_version varchar(20) NOT NULL DEFAULT '',
  consent_at datetime NULL,
  consent_ip varchar(45) NOT NULL DEFAULT '',
  consent_user_agent varchar(255) NOT NULL DEFAULT '',
  consent_text text NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY destination (destination),
  KEY created_at (created_at)
) $c;" );
	dbDelta( "CREATE TABLE " . irn_table( 'announcements' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source_type varchar(10) NOT NULL,
  source_id varchar(100) NOT NULL,
  source_event varchar(255) NOT NULL DEFAULT '',
  facts longtext NULL,
  email_subject varchar(255) NOT NULL DEFAULT '',
  headline varchar(255) NOT NULL DEFAULT '',
  message text NULL,
  action_url varchar(500) NOT NULL DEFAULT '',
  sms_body text NULL,
  variant int(11) NOT NULL DEFAULT 0,
  state varchar(10) NOT NULL DEFAULT 'awaiting',
  created_at datetime NOT NULL,
  approved_at datetime NULL,
  sent_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source (source_type,source_id),
  KEY state (state)
) $c;" );
	dbDelta( "CREATE TABLE " . irn_table( 'outbox' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kind varchar(12) NOT NULL,
  announcement_id bigint(20) unsigned NOT NULL DEFAULT 0,
  subscriber_id bigint(20) unsigned NULL,
  request_key varchar(64) NULL,
  channel varchar(5) NOT NULL,
  destination varchar(191) NOT NULL,
  payload longtext NOT NULL,
  state varchar(10) NOT NULL DEFAULT 'pending',
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  next_attempt_at datetime NOT NULL,
  provider_id varchar(100) NOT NULL DEFAULT '',
  last_result text NULL,
  created_at datetime NOT NULL,
  completed_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY message (announcement_id,subscriber_id),
  UNIQUE KEY request_key (request_key),
  KEY conversation (destination,kind),
  KEY due (state,next_attempt_at),
  KEY completed (completed_at)
) $c;" );
	dbDelta( "CREATE TABLE " . irn_table( 'replies' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  channel varchar(5) NOT NULL,
  provider_id varchar(191) NOT NULL,
  sender varchar(255) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  recipient varchar(32) NOT NULL DEFAULT '',
  opt_out varchar(5) NOT NULL DEFAULT '',
  body text NULL,
  received_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY provider_id (provider_id),
  KEY received_at (received_at),
  KEY sender (sender(32),id)
) $c;" );
	irn_record_show_baseline();
	update_option( 'irn_db_version', IRN_DB_VERSION, false );
}

register_activation_hook(
	__FILE__,
	function () {
		irn_install();
		irn_create_policy_pages();
		if ( ! wp_next_scheduled( 'irn_worker_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'irn_worker_hourly' );
		}
	}
);
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'irn_worker' );
		wp_clear_scheduled_hook( 'irn_worker_hourly' );
	}
);
add_action(
	'plugins_loaded',
	function () {
		if ( IRN_DB_VERSION !== get_option( 'irn_db_version' ) ) {
			irn_install();
		}
		if ( (int) get_option( 'irn_pages_created' ) < IRN_PAGES_VERSION ) {
			irn_create_policy_pages(); // also runs after an upload that replaces the plugin (no activation hook)
		}
	}
);
