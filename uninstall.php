<?php
/**
 * Handle plugin uninstall routine.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-queue.php';
require_once __DIR__ . '/includes/class-debug-log.php';

delete_option( 'opptiai_alt_settings' );
delete_option( 'opptiai_settings' );
delete_option( 'opptiai_alt_jwt_token' );
delete_option( 'opptiai_alt_user_data' );
delete_option( 'opptiai_alt_site_id' ); // Site-based licensing identifier
delete_option( 'opptiai_alt_logs_ready' );
delete_option( 'wp_alt_text_api_notice_dismissed' ); // External API notice dismissal

delete_transient( 'opptiai_alt_token_notice' );
delete_transient( 'opptiai_token_notice' );
delete_transient( 'opptiai_limit_notice' );
delete_transient( 'opptiai_alt_token_last_check' );

wp_clear_scheduled_hook( AltText_AI_Queue::CRON_HOOK );

$role = get_role( 'administrator' );
if ( $role && $role->has_cap( 'manage_ai_alt_text' ) ) {
	$role->remove_cap( 'manage_ai_alt_text' );
}

global $wpdb;
$table = AltText_AI_Queue::table();

if ( is_string( $table ) && preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$log_table = AltText_AI_Debug_Log::table();
if ( is_string( $log_table ) && preg_match( '/^[A-Za-z0-9_]+$/', $log_table ) ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$log_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

