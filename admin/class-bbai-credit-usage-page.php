<?php
/**
 * Credit Usage Admin Page for BeepBeep AI
 * Displays per-user credit usage breakdown and detailed statistics
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

class Credit_Usage_Page {

	/**
	 * Register admin menu page (deprecated - now integrated as a tab).
	 * Kept for backward compatibility.
	 */
	public static function register_admin_page() {
		// This is now handled as a tab in the main plugin page
		// Keeping method for backward compatibility but not registering menu
		// add_submenu_page(
		// 	'upload.php', // Parent: Media menu
		// 	__('Credit Usage', 'beepbeep-ai-alt-text-generator'),
		// 	__('Credit Usage', 'beepbeep-ai-alt-text-generator'),
		// 	'manage_options',
		// 	'bbai-credit-usage',
		// 	[__CLASS__, 'render_page']
		// );
	}

	/**
	 * Render the credit usage content (for use in tabs).
	 */
	public static function render_page_content() {
	if (!current_user_can('manage_options')) {
		echo '<div class="notice notice-error"><p>' . esc_html__('You do not have permission to access this page.', 'beepbeep-ai-alt-text-generator') . '</p></div>';
		return;
	}

	// Handle table creation request - sanitize and verify nonce first.
	$bbai_action_raw = isset( $_POST['bbai_action'] ) ? wp_unslash( $_POST['bbai_action'] ) : '';
	$bbai_action     = is_string( $bbai_action_raw ) ? sanitize_text_field( $bbai_action_raw ) : '';
	if ( $bbai_action === 'create_credit_table' ) {
		check_admin_referer( 'bbai_create_credit_table', 'bbai_create_table_nonce' );
		Credit_Usage_Logger::create_table();
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Credit usage table created successfully!', 'beepbeep-ai-alt-text-generator' ) . '</p></div>';
	}

		// Get filter parameters
		$date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		$user_id_raw = isset($_GET['user_id']) ? wp_unslash($_GET['user_id']) : 0;
		$user_id     = absint($user_id_raw);
		$source_raw  = isset($_GET['source']) ? wp_unslash($_GET['source']) : '';
		$source      = is_string($source_raw) ? sanitize_key($source_raw) : '';
		$page_raw    = isset($_GET['paged']) ? wp_unslash($_GET['paged']) : 1;
		$page        = max(1, absint($page_raw));
		$view_raw    = isset($_GET['view']) ? wp_unslash($_GET['view']) : 'summary';
		$view        = is_string($view_raw) ? sanitize_key($view_raw) : 'summary'; // 'summary' or 'user_detail'

	// Build query args
	$query_args = [
		'date_from' => $date_from,
		'date_to'   => $date_to,
		'source'    => $source,
		'user_id'   => $user_id > 0 ? $user_id : null,
		'per_page'  => 50,
		'page'      => $page,
	];

	// Get site-wide usage summary
	$site_usage = Credit_Usage_Logger::get_site_usage($query_args);

	// Get current usage stats - use Token Quota Service for accurate site-wide quota
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
	$quota = \BeepBeepAI\AltTextGenerator\Token_Quota_Service::get_site_quota();
	if (is_wp_error($quota)) {
		// Fallback to usage tracker
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		$current_usage = Usage_Tracker::get_stats_display();
	} else {
		$used = max(0, intval($quota['used'] ?? 0));
		$limit = max(1, intval($quota['limit'] ?? 50));
		// Always calculate remaining from limit - used for accuracy
		$remaining = max(0, $limit - $used);
		$current_usage = [
			'used' => $used,
			'limit' => $limit,
			'remaining' => $remaining,
			'percentage' => $limit > 0 ? round(($used / $limit) * 100) : 0,
		];
	}

	// Ensure credit usage table exists - create if missing
	if (!Credit_Usage_Logger::table_exists()) {
		Credit_Usage_Logger::create_table();
	}
	
	// Get usage by user (local WordPress data)
	$usage_by_user = Credit_Usage_Logger::get_usage_by_user($query_args);

	// Get backend user activity (SEO Heroes - shows who's contributing most)
	$backend_user_activity = [];
	try {
		$api_client = \BeepBeepAI\AltTextGenerator\Api_Client_V2::get_instance();
		if ($api_client && method_exists($api_client, 'get_backend_user_usage')) {
			$backend_result = $api_client->get_backend_user_usage();
			if (!is_wp_error($backend_result) && !empty($backend_result['users'])) {
				$backend_user_activity = $backend_result;
			}
		}
	} catch (\Exception $e) {
		// Silently fail - backend data is optional enhancement
	}
	
		// Diagnostic: Check if table exists and has any data
		$table_exists = Credit_Usage_Logger::table_exists();
		$debug_info = [];
		
				if ($table_exists) {
					global $wpdb;
					$table = esc_sql( Credit_Usage_Logger::table() );
				
				// Get total row count
				$total_rows = $wpdb->get_var(
					$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE %d = %d",
					1,
					1
				)
				);
		$debug_info['total_rows'] = absint($total_rows);
		
		// Get distinct user count
				$distinct_users = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(DISTINCT user_id) FROM `{$table}` WHERE %d = %d",
							1,
							1
						)
				);
		$debug_info['distinct_users'] = absint($distinct_users);
		
		// Get sample of actual data (for debugging) - raw query without filters
			if ($total_rows > 0) {
					$sample_data = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT user_id, attachment_id, credits_used, source, generated_at FROM `{$table}` ORDER BY generated_at DESC LIMIT %d",
								10
							),
						ARRAY_A
					);
			$debug_info['sample_data'] = $sample_data;
			
			// Get user breakdown summary
					$user_breakdown = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT user_id, COUNT(*) as count, SUM(credits_used) as total_credits FROM `{$table}` WHERE %d = %d GROUP BY user_id ORDER BY total_credits DESC",
								1,
								1
							),
				ARRAY_A
			);
			$debug_info['user_breakdown'] = $user_breakdown;
			} else {
				$debug_info['sample_data'] = [];
				$debug_info['user_breakdown'] = [];
			}
	} else {
		$debug_info['table_exists'] = false;
	}
	
	// Additional check: verify query returned results
	$debug_info['query_returned_users'] = count($usage_by_user['users'] ?? []);
	$debug_info['query_total'] = $usage_by_user['total'] ?? 0;

	// Get all users for filter dropdown
	$all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);

	// Get user details if viewing specific user
	$user_details = null;
	if ($view === 'user_detail' && $user_id > 0) {
		$user_details = Credit_Usage_Logger::get_user_details($user_id, $query_args);
	}

	$partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/credit-usage-content.php';
	if (file_exists($partial)) {
		include $partial;
		} else {
			esc_html_e('Credit usage content unavailable.', 'beepbeep-ai-alt-text-generator');
		}
		}

}
