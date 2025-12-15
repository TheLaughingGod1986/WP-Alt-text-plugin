<?php
/**
 * Handle plugin uninstall routine.
 *
 * This file runs when the plugin is deleted through WordPress admin.
 * It removes all plugin data including options, transients, and custom tables.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete all beepbeepai_ options
$beepbeepai_options = array(
	'beepbeepai_settings',
	'beepbeepai_jwt_token',
	'beepbeepai_user_data',
	'beepbeepai_site_id',
	'beepbeepai_license_key',
	'beepbeepai_license_data',
	'beepbeepai_logs_ready',
	'beepbeepai_checkout_prices',
	'beepbeepai_remote_price_ids',
	'beepbeepai_last_upgrade_click',
);

foreach ( $beepbeepai_options as $option ) {
	delete_option( $option );
}

// Delete all bbai_ options (legacy and current)
$bbai_options = array(
	'bbai_settings',
	'bbai_jwt_token',
	'bbai_user_data',
	'bbai_site_id',
	'bbai_logs_ready',
	'bbai_checkout_prices',
	'bbai_remote_price_ids',
	'wp_alt_text_api_notice_dismissed',
);

// Delete optti_ prefixed options (unified framework)
$optti_options = array(
	'optti_jwt_token',
	'optti_user_data',
	'optti_site_id',
	'optti_license_key',
	'optti_license_data',
	'optti_license_last_check',
	'optti_license_snapshot',
);

foreach ( $bbai_options as $option ) {
	delete_option( $option );
}

// Delete optti_ prefixed options
foreach ( $optti_options as $option ) {
	delete_option( $option );
}

// Delete all legacy options
$legacy_options = array(
	'beepbeepai_settings',
	'beepbeepai_settings',
	'beepbeepai_jwt_token',
	'beepbeepai_user_data',
	'beepbeepai_site_id',
	'beepbeepai_logs_ready',
	'opptibbai_settings',
	'opptibbai_user_data',
	'opptibbai_site_id',
);

foreach ( $legacy_options as $option ) {
	delete_option( $option );
}

// Delete all beepbeepai_ transients
$beepbeepai_transients = array(
	'beepbeepai_usage_cache',
	'beepbeepai_token_notice',
	'beepbeepai_token_last_check',
	'beepbeepai_remote_price_ids',
	'beepbeepai_upgrade_dismissed',
	'beepbeepai_usage_refresh_lock',
);

foreach ( $beepbeepai_transients as $transient ) {
	delete_transient( $transient );
}

// Delete all bbai_ transients
$bbai_transients = array(
	'bbai_token_notice',
	'bbai_token_last_check',
	'bbai_usage_cache',
	'bbai_remote_price_ids',
	'bbai_usage_refresh_lock',
);

foreach ( $bbai_transients as $transient ) {
	delete_transient( $transient );
}

// Delete all legacy transients
$legacy_transients = array(
	'beepbeepai_token_notice',
	'beepbeepai_token_notice',
	'beepbeepai_limit_notice',
	'beepbeepai_token_last_check',
	'opptibbai_usage_cache',
	'opptibbai_token_last_check',
);

foreach ( $legacy_transients as $transient ) {
	delete_transient( $transient );
}

// Clear scheduled cron hooks
// Clear framework queue hook
if ( class_exists( '\Optti\Framework\Queue' ) ) {
	\Optti\Framework\Queue::init( 'bbai' );
	wp_clear_scheduled_hook( \Optti\Framework\Queue::get_cron_hook() );
}
// Also clear legacy hooks for backward compatibility
wp_clear_scheduled_hook( 'bbai_process_queue' );
wp_clear_scheduled_hook( 'beepbeepai_process_queue' );
wp_clear_scheduled_hook( 'bbai_daily_identity_sync' );

// Remove custom capability from administrator role
$role = get_role( 'administrator' );
if ( $role && $role->has_cap( 'manage_bbai_alt_text' ) ) {
	$role->remove_cap( 'manage_bbai_alt_text' );
}
if ( $role && $role->has_cap( 'manage_bbbbai_text' ) ) {
	$role->remove_cap( 'manage_bbbbai_text' );
}

// Delete all post meta with beepbeepai_ or ai_alt_ prefix
$meta_keys_to_delete = array(
	'_beepbeepai_source',
	'_beepbeepai_model',
	'_beepbeepai_generated_at',
	'_beepbeepai_tokens_prompt',
	'_beepbeepai_tokens_completion',
	'_beepbeepai_tokens_total',
	'_beepbeepai_image_reference',
	'_beepbeepai_review_score',
	'_beepbeepai_review_status',
	'_beepbeepai_review_grade',
	'_beepbeepai_review_summary',
	'_beepbeepai_review_issues',
	'_beepbeepai_review_model',
	'_beepbeepai_reviewed_at',
	'_beepbeepai_review_alt_hash',
	'_beepbeepai_review_error',
	'_beepbeepai_last_prompt',
	'_beepbeepai_original',
	'_beepbeepai_usage',
	// Legacy ai_alt_ keys
	'_ai_alt_source',
	'_ai_alt_model',
	'_ai_alt_generated_at',
	'_ai_alt_tokens_prompt',
	'_ai_alt_tokens_completion',
	'_ai_alt_tokens_total',
	'_ai_alt_image_reference',
	'_ai_alt_review_score',
	'_ai_alt_review_status',
	'_ai_alt_review_grade',
	'_ai_alt_review_summary',
	'_ai_alt_review_issues',
	'_ai_alt_review_model',
	'_ai_alt_reviewed_at',
	'_ai_alt_review_alt_hash',
	'_ai_alt_review_error',
	'_ai_alt_last_prompt',
	'_ai_alt_original',
	'_ai_alt_usage',
);

// Delete post meta using SQL for efficiency
foreach ( $meta_keys_to_delete as $meta_key ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
			$meta_key
		)
	);
}

// Drop custom tables
$table_prefix = $wpdb->prefix;

// Queue table
$queue_table      = $table_prefix . 'bbai_queue';
$queue_table_safe = esc_sql( $queue_table );
$wpdb->query( "DROP TABLE IF EXISTS `{$queue_table_safe}`" );

// Logs table
$logs_table      = $table_prefix . 'bbai_logs';
$logs_table_safe = esc_sql( $logs_table );
$wpdb->query( "DROP TABLE IF EXISTS `{$logs_table_safe}`" );

// Legacy table names (if they exist)
$legacy_queue_table      = $table_prefix . 'beepbeepai_queue';
$legacy_queue_table_safe = esc_sql( $legacy_queue_table );
$wpdb->query( "DROP TABLE IF EXISTS `{$legacy_queue_table_safe}`" );

$legacy_logs_table      = $table_prefix . 'beepbeepai_logs';
$legacy_logs_table_safe = esc_sql( $legacy_logs_table );
$wpdb->query( "DROP TABLE IF EXISTS `{$legacy_logs_table_safe}`" );
