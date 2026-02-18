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
	$bbai_action_input = isset( $_POST['bbai_action'] ) ? sanitize_key( wp_unslash( $_POST['bbai_action'] ) ) : '';
	$bbai_action = in_array( $bbai_action_input, [ 'create_credit_table' ], true ) ? $bbai_action_input : '';
	if ( $bbai_action === 'create_credit_table' ) {
		check_admin_referer( 'bbai_create_credit_table', 'bbai_create_table_nonce' );
		Credit_Usage_Logger::create_table();
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Credit usage table created successfully!', 'beepbeep-ai-alt-text-generator' ) . '</p></div>';
	}

		// Get filter parameters
		$date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		$user_id_input = isset($_GET['user_id']) ? absint(wp_unslash($_GET['user_id'])) : 0;
		$user_id     = $user_id_input;
		$source_input  = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : '';
		$allowed_sources = [ '', 'ajax', 'dashboard', 'bulk', 'bulk-regenerate', 'upload', 'metadata', 'update', 'save', 'queue', 'onboarding', 'library', 'manual', 'unknown' ];
		$source      = in_array($source_input, $allowed_sources, true) ? $source_input : '';
		$page_input    = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
		$page        = max(1, $page_input);
		$view_input    = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'summary';
		$allowed_views = [ 'summary', 'user_detail' ];
		$view        = in_array($view_input, $allowed_views, true) ? $view_input : 'summary'; // 'summary' or 'user_detail'

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
				$backend_user_activity = self::enrich_backend_user_activity($backend_result);
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

	/**
	 * Resolve backend hero rows to WordPress users when possible.
	 *
	 * @param array $backend_result Raw backend usage response.
	 * @return array
	 */
	private static function enrich_backend_user_activity($backend_result) {
		if (!is_array($backend_result) || empty($backend_result['users']) || !is_array($backend_result['users'])) {
			return is_array($backend_result) ? $backend_result : [];
		}

		foreach ($backend_result['users'] as &$hero) {
			if (!is_array($hero)) {
				continue;
			}

			$wp_user = self::resolve_backend_hero_wp_user($hero);
			if ($wp_user instanceof \WP_User) {
				$hero['display_name'] = $wp_user->display_name ?: $wp_user->user_login;
				if (empty($hero['user_email']) && !empty($wp_user->user_email)) {
					$hero['user_email'] = $wp_user->user_email;
				}
				$hero['wp_user_id'] = absint($wp_user->ID);
				continue;
			}

			// If resolve_backend_hero_wp_user failed but we have a user_id, try direct database lookup
			// (in case get_user_by fails due to caching or other issues)
			$potential_user_id = null;
			foreach (['wp_user_id', 'wordpress_user_id', 'wordpress_id', 'wp_id', 'user_id', 'site_user_id', 'local_user_id'] as $id_key) {
				if (isset($hero[$id_key]) && absint($hero[$id_key]) > 0) {
					$potential_user_id = absint($hero[$id_key]);
					break;
				}
			}
			if ($potential_user_id && $potential_user_id > 0) {
				global $wpdb;
				$db_user = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID, user_login, display_name, user_email FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
						$potential_user_id
					)
				);
				if ($db_user) {
					$hero['display_name'] = $db_user->display_name ?: $db_user->user_login;
					if (empty($hero['user_email']) && !empty($db_user->user_email)) {
						$hero['user_email'] = $db_user->user_email;
					}
					$hero['wp_user_id'] = absint($db_user->ID);
					continue;
				}
			}

			// Try to get display_name from backend data fields
			if (empty($hero['display_name'])) {
				foreach (['user_name', 'name', 'username', 'user_email', 'email'] as $name_key) {
					if (!empty($hero[$name_key]) && is_string($hero[$name_key])) {
						$hero['display_name'] = $hero[$name_key];
						break;
					}
				}
			}

			// If still no display_name, try to get it from usage logs table or database
			if (empty($hero['display_name'])) {
				$user_id = null;
				foreach (['wp_user_id', 'wordpress_user_id', 'wordpress_id', 'wp_id', 'user_id', 'site_user_id', 'local_user_id'] as $id_key) {
					if (isset($hero[$id_key]) && absint($hero[$id_key]) > 0) {
						$user_id = absint($hero[$id_key]);
						break;
					}
				}

				if ($user_id && $user_id > 0) {
					// Try multiple methods to get the username
					
					// Method 1: Try to get user from usage logs enrichment
					require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
					$usage_events = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_usage_events([
						'user_id' => $user_id,
						'per_page' => 1,
						'skip_site_filter' => true,
					]);
					if (!empty($usage_events['events'][0]['display_name']) && $usage_events['events'][0]['display_name'] !== __('System', 'beepbeep-ai-alt-text-generator')) {
						$hero['display_name'] = $usage_events['events'][0]['display_name'];
					} elseif (!empty($usage_events['events'][0]['username']) && $usage_events['events'][0]['username'] !== __('System', 'beepbeep-ai-alt-text-generator')) {
						$hero['display_name'] = $usage_events['events'][0]['username'];
					} else {
						// Method 2: Try get_user_by
						$wp_user_retry = get_user_by('ID', $user_id);
						if ($wp_user_retry instanceof \WP_User) {
							$hero['display_name'] = $wp_user_retry->display_name ?: $wp_user_retry->user_login;
							if (empty($hero['user_email']) && !empty($wp_user_retry->user_email)) {
								$hero['user_email'] = $wp_user_retry->user_email;
							}
						} else {
							// Method 3: Query database directly (in case get_user_by fails due to caching)
							global $wpdb;
							$db_user = $wpdb->get_row(
								$wpdb->prepare(
									"SELECT ID, user_login, display_name, user_email FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
									$user_id
								)
							);
							if ($db_user) {
								$hero['display_name'] = $db_user->display_name ?: $db_user->user_login;
								if (empty($hero['user_email']) && !empty($db_user->user_email)) {
									$hero['user_email'] = $db_user->user_email;
								}
							} else {
								// Method 4: Backend user_id doesn't match WordPress - try to find WordPress user by matching activity
								// Find WordPress user with most activity (likely corresponds to this backend user)
								$table_name = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
								$matching_wp_user_id = $wpdb->get_var(
									"SELECT user_id FROM `{$table_name}` WHERE user_id > 0 GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 1"
								);
								if ($matching_wp_user_id && absint($matching_wp_user_id) > 0) {
									$matching_user = get_user_by('ID', absint($matching_wp_user_id));
									if ($matching_user instanceof \WP_User) {
										$hero['display_name'] = $matching_user->display_name ?: $matching_user->user_login;
										if (empty($hero['user_email']) && !empty($matching_user->user_email)) {
											$hero['user_email'] = $matching_user->user_email;
										}
										// Store the matched WordPress user ID
										$hero['wp_user_id'] = absint($matching_user->ID);
									} else {
										// Query database directly for this user_id
										$db_user_direct = $wpdb->get_row(
											$wpdb->prepare(
												"SELECT ID, user_login, display_name, user_email FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
												absint($matching_wp_user_id)
											)
										);
										if ($db_user_direct) {
											$hero['display_name'] = $db_user_direct->display_name ?: $db_user_direct->user_login;
											if (empty($hero['user_email']) && !empty($db_user_direct->user_email)) {
												$hero['user_email'] = $db_user_direct->user_email;
											}
										} else {
											// Final fallback: show user ID
											/* translators: %d: WordPress user ID for a deleted/missing contributor. */
											$hero['display_name'] = sprintf(__('User #%d', 'beepbeep-ai-alt-text-generator'), $user_id);
										}
									}
								} else {
									// Final fallback: show user ID
									/* translators: %d: WordPress user ID for a deleted/missing contributor. */
									$hero['display_name'] = sprintf(__('User #%d', 'beepbeep-ai-alt-text-generator'), $user_id);
								}
							}
						}
					}
				}
			}

			// Final fallback: if still empty, use email or show generic message
			if (empty($hero['display_name'])) {
				if (!empty($hero['user_email']) || !empty($hero['email'])) {
					$hero['display_name'] = $hero['user_email'] ?? $hero['email'];
				} else {
					$hero['display_name'] = __('Contributor', 'beepbeep-ai-alt-text-generator');
				}
			}
		}
		unset($hero);

		return $backend_result;
	}

	/**
	 * Attempt to find a matching WordPress user for a backend hero row.
	 *
	 * @param array $hero Backend user row.
	 * @return \WP_User|null
	 */
	private static function resolve_backend_hero_wp_user(array $hero) {
		// First, try direct WordPress user ID match
		$user_id_keys = ['wp_user_id', 'wordpress_user_id', 'wordpress_id', 'wp_id', 'user_id', 'site_user_id', 'local_user_id'];
		foreach ($user_id_keys as $id_key) {
			if (!isset($hero[$id_key])) {
				continue;
			}

			$user_id = absint($hero[$id_key]);
			if ($user_id <= 0) {
				continue;
			}

			$wp_user = get_user_by('ID', $user_id);
			if ($wp_user instanceof \WP_User) {
				return $wp_user;
			}
		}

		// Second, try email match
		$email_keys = ['user_email', 'email'];
		foreach ($email_keys as $email_key) {
			if (empty($hero[$email_key]) || !is_string($hero[$email_key])) {
				continue;
			}

			$email = sanitize_email($hero[$email_key]);
			if (empty($email) || !is_email($email)) {
				continue;
			}

			$wp_user = get_user_by('email', $email);
			if ($wp_user instanceof \WP_User) {
				return $wp_user;
			}
		}

		// Third, try to find WordPress user from usage logs by matching backend user activity
		// If backend returns a backend_user_id, look up which WordPress user_id has matching activity
		$backend_user_id_keys = ['backend_user_id', 'id', 'user_id'];
		foreach ($backend_user_id_keys as $id_key) {
			if (!isset($hero[$id_key])) {
				continue;
			}

			$backend_user_id = absint($hero[$id_key]);
			if ($backend_user_id <= 0) {
				continue;
			}

			// Look up in usage logs - find WordPress user_id with most activity matching this backend user
			// This works if backend tracks which WordPress user made requests
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
			global $wpdb;
			$table_name = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
			
			// Try to find WordPress user_id by looking at recent activity
			// Get the most active WordPress user_id from usage logs (they likely correspond to backend users)
			$wp_user_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM `{$table_name}` WHERE user_id > 0 ORDER BY created_at DESC LIMIT 1"
				)
			);
			
			if ($wp_user_id && absint($wp_user_id) > 0) {
				$wp_user = get_user_by('ID', absint($wp_user_id));
				if ($wp_user instanceof \WP_User) {
					return $wp_user;
				}
			}
		}

		// Fourth, try username match if available
		$username_keys = ['username', 'user_name', 'user_login', 'login'];
		foreach ($username_keys as $username_key) {
			if (empty($hero[$username_key]) || !is_string($hero[$username_key])) {
				continue;
			}

			$username = sanitize_user($hero[$username_key]);
			if (empty($username)) {
				continue;
			}

			$wp_user = get_user_by('login', $username);
			if ($wp_user instanceof \WP_User) {
				return $wp_user;
			}
		}

		return null;
	}

	}
