<?php
/**
 * Usage Helpers for BeepBeep AI
 * Helper functions for recording and retrieving usage data
 */

namespace BeepBeepAI\AltTextGenerator\Usage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Record a usage event.
 *
 * @param int    $user_id User ID (0 for system/anonymous).
 * @param int    $tokens_used Number of tokens used.
 * @param string $action_type Action type (generate, regenerate, bulk, api).
 * @param array  $context Optional context data (image_id, post_id, etc.).
 * @return int|false The ID of the inserted record, or false on failure.
 */
function record_usage_event( $user_id, $tokens_used, $action_type = 'generate', $context = array() ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
	return Usage_Logs::record_usage_event( $user_id, $tokens_used, $action_type, $context );
}

/**
 * Get monthly total usage for current month.
 *
 * @return int Total tokens used this month.
 */
function get_monthly_total_usage() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
	return Usage_Logs::get_monthly_total_usage();
}

/**
 * Get monthly usage breakdown by user.
 *
 * @return array Array of user usage data.
 */
function get_monthly_usage_by_user() {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
	return Usage_Logs::get_monthly_usage_by_user();
}

/**
 * Get usage events with filters.
 *
 * @param array $filters Filter options (user_id, date_from, date_to, action_type, per_page, page).
 * @return array Events with pagination info.
 */
function get_usage_events( $filters = array() ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
	return Usage_Logs::get_usage_events( $filters );
}
