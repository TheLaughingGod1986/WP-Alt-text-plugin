<?php
/**
 * Handle plugin uninstall routine.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-queue.php';
require_once __DIR__ . '/includes/class-debug-log.php';

delete_option( 'bbai_settings' );
delete_option( 'beepbeepai_settings' );
delete_option( 'beepbeepai_settings' );
delete_option( 'bbai_jwt_token' );
delete_option( 'beepbeepai_jwt_token' );
delete_option( 'bbai_user_data' );
delete_option( 'beepbeepai_user_data' );
delete_option( 'bbai_site_id' ); // Site-based licensing identifier
delete_option( 'beepbeepai_site_id' );
delete_option( 'bbai_logs_ready' );
delete_option( 'beepbeepai_logs_ready' );
delete_option( 'wp_alt_text_api_notice_dismissed' ); // External API notice dismissal

delete_transient( 'bbai_token_notice' );
delete_transient( 'beepbeepai_token_notice' );
delete_transient( 'beepbeepai_token_notice' );
delete_transient( 'beepbeepai_limit_notice' );
delete_transient( 'bbai_token_last_check' );
delete_transient( 'beepbeepai_token_last_check' );

wp_clear_scheduled_hook( BbAI_Queue::CRON_HOOK );

$role = get_role( 'administrator' );
if ( $role && $role->has_cap( 'manage_bbai_alt_text' ) ) {
	$role->remove_cap( 'manage_bbai_alt_text' );
}

global $wpdb;
$table = BbAI_Queue::table();

if ( is_string( $table ) && preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$log_table = BbAI_Debug_Log::table();
if ( is_string( $log_table ) && preg_match( '/^[A-Za-z0-9_]+$/', $log_table ) ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$log_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

