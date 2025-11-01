<?php
/**
 * Handle plugin uninstall routine.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-queue.php';

delete_option( 'ai_alt_gpt_settings' );
delete_option( 'alttextai_jwt_token' );
delete_option( 'alttextai_user_data' );

delete_transient( 'ai_alt_gpt_token_notice' );
delete_transient( 'alttextai_token_last_check' );

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
