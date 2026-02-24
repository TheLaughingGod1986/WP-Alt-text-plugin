<?php
/**
 * Handle plugin uninstall routine.
 * 
 * This file runs when the plugin is deleted through WordPress admin.
 * It removes all plugin data including options, transients, and custom tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete all beepbeepai_ options
$beepbeepai_options = [
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
];

foreach ( $beepbeepai_options as $bbai_option ) {
	delete_option( $bbai_option );
}

// Delete all bbai_ options (legacy and current)
$bbai_options = [
	'bbai_settings',
	'bbai_jwt_token',
	'bbai_user_data',
	'bbai_site_id',
	'bbai_logs_ready',
	'bbai_checkout_prices',
	'bbai_remote_price_ids',
	'bbai_api_notice_dismissed',
	'wp_alt_text_api_notice_dismissed',
	'bbai_db_version',
	'bbai_cache_gen_logs',
	'bbai_cache_gen_queue',
	'bbai_cache_gen_credit_usage',
	'bbai_cache_gen_usage_logs',
	'bbai_cache_gen_contact',
	'bbai_cache_gen_library',
	'bbai_cache_gen_stats',
];

foreach ( $bbai_options as $bbai_option ) {
	delete_option( $bbai_option );
}

// Delete all legacy options
$bbai_legacy_options = [
	'beepbeepai_jwt_token',
	'beepbeepai_user_data',
	'beepbeepai_site_id',
	'beepbeepai_logs_ready',
	'opptibbai_settings',
	'opptibbai_user_data',
	'opptibbai_site_id',
];

foreach ( $bbai_legacy_options as $bbai_option ) {
	delete_option( $bbai_option );
}

// Delete all beepbeepai_ transients
$beepbeepai_transients = [
	'beepbeepai_usage_cache',
	'beepbeepai_token_notice',
	'beepbeepai_token_last_check',
	'beepbeepai_remote_price_ids',
	'beepbeepai_upgrade_dismissed',
	'beepbeepai_usage_refresh_lock',
];

foreach ( $beepbeepai_transients as $bbai_transient ) {
	delete_transient( $bbai_transient );
}

// Delete all bbai_ transients
$bbai_transients = [
	'bbai_token_notice',
	'bbai_token_last_check',
	'bbai_usage_cache',
	'bbai_remote_price_ids',
	'bbai_usage_refresh_lock',
	'bbai_stats_v3',
];

foreach ( $bbai_transients as $bbai_transient ) {
	delete_transient( $bbai_transient );
}

// Delete all legacy transients
$bbai_legacy_transients = [
	'beepbeepai_limit_notice',
	'beepbeepai_token_last_check',
	'opptibbai_usage_cache',
	'opptibbai_token_last_check',
];

foreach ( $bbai_legacy_transients as $bbai_transient ) {
	delete_transient( $bbai_transient );
}

// Clear scheduled cron hooks
wp_clear_scheduled_hook( 'bbai_process_queue' );
wp_clear_scheduled_hook( 'beepbeepai_process_queue' );
wp_clear_scheduled_hook( \BeepBeepAI\AltTextGenerator\Queue::CRON_HOOK );

// Remove custom capability from administrator role
$bbai_role = get_role( 'administrator' );
if ( $bbai_role && $bbai_role->has_cap( 'manage_bbai_alt_text' ) ) {
	$bbai_role->remove_cap( 'manage_bbai_alt_text' );
}
if ( $bbai_role && $bbai_role->has_cap( 'manage_bbbbai_text' ) ) {
	$bbai_role->remove_cap( 'manage_bbbbai_text' );
}

// Delete all post meta with beepbeepai_ or ai_alt_ prefix
$bbai_meta_keys_to_delete = [
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
];

// Delete post meta using prepared statements for security.
foreach ( $bbai_meta_keys_to_delete as $bbai_meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
			$bbai_meta_key
		)
	);
}

// Drop custom tables.
$bbai_queue_table_safe              = esc_sql( $wpdb->prefix . 'bbai_queue' );
$bbai_logs_table_safe               = esc_sql( $wpdb->prefix . 'bbai_logs' );
$bbai_credit_usage_table_safe       = esc_sql( $wpdb->prefix . 'bbai_credit_usage' );
$bbai_usage_logs_table_safe         = esc_sql( $wpdb->prefix . 'bbai_usage_logs' );
$bbai_contact_submissions_table_safe = esc_sql( $wpdb->prefix . 'bbai_contact_submissions' );
$bbai_legacy_queue_table_safe       = esc_sql( $wpdb->prefix . 'beepbeepai_queue' );
$bbai_legacy_logs_table_safe        = esc_sql( $wpdb->prefix . 'beepbeepai_logs' );

$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_queue_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_logs_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_credit_usage_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_usage_logs_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_contact_submissions_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_legacy_queue_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `' . $bbai_legacy_logs_table_safe . '` /* %d */', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Table name is schema-controlled and escaped with esc_sql().
