<?php
/**
 * Credit Usage Admin Page for BeepBeep AI
 * Displays per-user credit usage breakdown and detailed statistics
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$user_id_input = isset($_GET['user_id']) ? absint(wp_unslash($_GET['user_id'])) : 0;
		$user_id     = $user_id_input;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$source_input  = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : '';
		$allowed_sources = [ '', 'ajax', 'dashboard', 'bulk', 'bulk-regenerate', 'upload', 'metadata', 'update', 'save', 'queue', 'onboarding', 'library', 'manual', 'unknown' ];
		$source      = in_array($source_input, $allowed_sources, true) ? $source_input : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$page_input    = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
		$page        = max(1, $page_input);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
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

	// Get current usage stats directly from the authoritative backend usage snapshot.
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
	$current_usage = Usage_Tracker::get_stats_display(true);

	$bbai_missing_images = self::get_missing_images_count();
	$bbai_settings_automation_url = admin_url( 'admin.php?page=bbai-settings#bbai-enable-on-upload' );

	// Table is created by DB_Schema on activation/upgrade.
	// Get usage by user (local WordPress data)
	$usage_by_user = Credit_Usage_Logger::get_usage_by_user($query_args);
	$usage_activity = Credit_Usage_Logger::get_activity_rows( $query_args );
	$has_usage_activity = Credit_Usage_Logger::get_site_generation_count() > 0;
	$has_filtered_results = ! empty( $usage_activity['items'] );
	$source_options = self::get_source_options();
	$billing_portal_url = Usage_Tracker::get_billing_portal_url();
	$usage_surface = self::build_usage_surface_state(
		$current_usage,
		$bbai_missing_images,
		$has_usage_activity,
		$has_filtered_results,
		$bbai_settings_automation_url,
		$billing_portal_url
	);

	$bbai_cycle_range         = self::get_billing_cycle_local_mysql_range( $current_usage );
	$bbai_cycle_source_rows   = Credit_Usage_Logger::get_credit_totals_by_source(
		[
			'date_from' => $bbai_cycle_range['from'],
			'date_to'   => $bbai_cycle_range['to'],
			'source'    => null,
		]
	);
	$bbai_cycle_site_usage    = Credit_Usage_Logger::get_site_usage(
		[
			'date_from' => $bbai_cycle_range['from'],
			'date_to'   => $bbai_cycle_range['to'],
			'source'    => null,
		]
	);
	$usage_insights           = self::build_usage_insights_payload(
		$current_usage,
		$usage_surface,
		$bbai_cycle_source_rows,
		$bbai_cycle_site_usage
	);

	// Optional backend contributor snapshot (Usage page: "Top contributors").
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
					$cached_diag = BBAI_Cache::get( 'credit_usage', 'diag' );
					if ( false !== $cached_diag && is_array( $cached_diag ) ) {
						$debug_info = $cached_diag;
					} else {
					global $wpdb;
					$table = esc_sql( Credit_Usage_Logger::table() );
				
				// Get total row count
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$total_rows = $wpdb->get_var(
					$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM `{$table}` WHERE %d = %d",
					1,
					1
				)
				);
		$debug_info['total_rows'] = absint($total_rows);
		
		// Get distinct user count
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$distinct_users = $wpdb->get_var(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							"SELECT COUNT(DISTINCT user_id) FROM `{$table}` WHERE %d = %d",
							1,
							1
						)
				);
		$debug_info['distinct_users'] = absint($distinct_users);
		
		// Get sample of actual data (for debugging) - raw query without filters
			if ($total_rows > 0) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$sample_data = $wpdb->get_results(
							$wpdb->prepare(
								// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								"SELECT user_id, attachment_id, credits_used, source, generated_at FROM `{$table}` ORDER BY generated_at DESC LIMIT %d",
								10
							),
						ARRAY_A
					);
			$debug_info['sample_data'] = $sample_data;
			
			// Get user breakdown summary
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$user_breakdown = $wpdb->get_results(
							$wpdb->prepare(
								// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

					BBAI_Cache::set( 'credit_usage', 'diag', $debug_info, BBAI_Cache::DEFAULT_TTL );
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
	 * Count attachments that are still missing alt text.
	 *
	 * @return int
	 */
	/**
	 * ALT coverage snapshot for shared status-banner (aligned with Dashboard / Library).
	 *
	 * @return array{missing:int,weak:int,optimized:int,total:int}
	 */
	private static function get_library_coverage_counts_for_banner(): array {
		if ( ! function_exists( 'bbai_get_attention_counts' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
		}
		$a   = bbai_get_attention_counts();
		$tot = max(
			$a['total_images'],
			$a['missing'] + $a['needs_review'] + $a['optimized_count']
		);

		return [
			'missing'   => $a['missing'],
			'weak'      => $a['needs_review'],
			'optimized' => $a['optimized_count'],
			'total'     => $tot,
		];
	}

	private static function get_missing_images_count() {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like('image/') . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lightweight count for credit usage banner/context
		$missing = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted core table names, values are prepared
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = %s
				WHERE p.post_type = %s
					AND p.post_status = %s
					AND p.post_mime_type LIKE %s
					AND (m.meta_id IS NULL OR TRIM(m.meta_value) = %s)",
				'_wp_attachment_image_alt',
				'attachment',
				'inherit',
				$image_mime_like,
				''
			)
		);

		return max(0, $missing);
	}

	/**
	 * Build usage-aware messaging and state for the Usage page.
	 *
	 * @param array  $current_usage Current quota usage.
	 * @param int    $missing_images Current missing image count.
	 * @param bool   $has_usage_activity Whether the site has any logged usage activity.
	 * @param bool   $has_filtered_results Whether the current filters returned results.
	 * @param string $automation_settings_url Settings URL for automation.
	 * @param string $billing_portal_url Billing portal URL when available.
	 * @return array<string,mixed>
	 */
	private static function build_usage_surface_state($current_usage, $missing_images, $has_usage_activity, $has_filtered_results, $automation_settings_url, $billing_portal_url) {
		$lib_counts = self::get_library_coverage_counts_for_banner();
		$bc_miss    = (int) $lib_counts['missing'];
		$bc_weak    = (int) $lib_counts['weak'];
		$bc_opt     = (int) $lib_counts['optimized'];
		$bc_tot     = (int) $lib_counts['total'];
		if ($bc_tot <= 0) {
			$bc_tot = $bc_opt + $bc_weak + $bc_miss;
		}
		if ($bc_tot <= 0 && $missing_images > 0) {
			$bc_miss = max($bc_miss, (int) $missing_images);
			$bc_tot  = $bc_miss;
		}

		$credits_used = max( 0, intval( $current_usage['used'] ?? 0 ) );
		$credits_limit = max( 0, intval( $current_usage['limit'] ?? 0 ) );
		$credits_remaining = isset( $current_usage['remaining'] )
			? max( 0, intval( $current_usage['remaining'] ) )
			: max( 0, $credits_limit - $credits_used );
		$days_until_reset = isset( $current_usage['days_until_reset'] ) && is_numeric( $current_usage['days_until_reset'] )
			? max( 0, intval( $current_usage['days_until_reset'] ) )
			: 0;
		$usage_percent = $credits_limit > 0 ? (int) round( ( $credits_used / $credits_limit ) * 100 ) : 0;
		$usage_percent = min( 100, max( 0, $usage_percent ) );
		$plan_slug = sanitize_key( (string) ( $current_usage['plan'] ?? 'free' ) );
		$plan_label = sanitize_text_field( (string) ( $current_usage['plan_label'] ?? ucfirst( $plan_slug ?: 'free' ) ) );
		$is_pro_plan = in_array( $plan_slug, [ 'pro', 'growth', 'agency', 'enterprise' ], true );
		$is_low_credits = $credits_remaining < 10 && $credits_remaining > 0;
		$is_out_of_credits = 0 === $credits_remaining;

		$pace = self::calculate_average_credits_per_day(
			$credits_used,
			intval( $current_usage['reset_timestamp'] ?? 0 ),
			$days_until_reset
		);
		$average_credits_per_day = (float) $pace['average'];
		$elapsed_days = intval( $pace['elapsed_days'] );
		$estimated_days_remaining = $average_credits_per_day > 0 && $credits_remaining > 0
			? (int) floor( $credits_remaining / $average_credits_per_day )
			: null;
		$has_predictive_signal = $average_credits_per_day > 0 && $credits_used >= 5 && $elapsed_days >= 3;

		$summary_tone = 'healthy';
		if ( $is_out_of_credits ) {
			$summary_tone = 'danger';
		} elseif ( $is_low_credits || $usage_percent >= 80 ) {
			$summary_tone = 'warning';
		}

		if ( $is_out_of_credits ) {
			$summary_position_copy = __( 'You have reached 100% of your monthly allowance.', 'beepbeep-ai-alt-text-generator' );
			$summary_remaining_copy = __( 'No credits are left in this billing period.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $credits_used > 0 ) {
			$summary_position_copy = sprintf(
				/* translators: %d: percentage of monthly allowance used. */
				__( 'You have used %d%% of your monthly allowance so far.', 'beepbeep-ai-alt-text-generator' ),
				$usage_percent
			);
			if ( $is_low_credits ) {
				$summary_remaining_copy = sprintf(
					/* translators: %s: remaining credit count. */
					__( 'Only %s credits are still available in this billing period.', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $credits_remaining )
				);
			} else {
				$summary_remaining_copy = sprintf(
					/* translators: %s: remaining credit count. */
					__( '%s credits are still available in this billing period.', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $credits_remaining )
				);
			}
		} else {
			$summary_position_copy = __( 'Your monthly allowance is still fully available.', 'beepbeep-ai-alt-text-generator' );
			$summary_remaining_copy = sprintf(
				/* translators: %s: remaining credit count. */
				__( '%s credits are ready to use in this billing period.', 'beepbeep-ai-alt-text-generator' ),
				number_format_i18n( $credits_remaining )
			);
		}

		$summary_reset_copy = self::build_reset_copy( $days_until_reset, (string) ( $current_usage['reset_date'] ?? '' ) );

		if ( $is_out_of_credits ) {
			$insight_copy = __( 'You have used all credits for this cycle. Upgrade to continue generating ALT text.', 'beepbeep-ai-alt-text-generator' );
			$insight_tone = 'danger';
		} elseif ( $is_low_credits && $has_predictive_signal && null !== $estimated_days_remaining && $estimated_days_remaining <= 0 ) {
			$insight_copy = __( 'At your current pace, your remaining credits may run out today.', 'beepbeep-ai-alt-text-generator' );
			$insight_tone = 'warning';
		} elseif ( $has_predictive_signal && null !== $estimated_days_remaining ) {
			if ( $days_until_reset > 0 && $estimated_days_remaining >= $days_until_reset ) {
				$insight_copy = __( 'At your current pace, your remaining credits should last through this billing cycle.', 'beepbeep-ai-alt-text-generator' );
				$insight_tone = 'healthy';
			} else {
				$insight_copy = sprintf(
					/* translators: %d: estimated days remaining at current usage pace. */
					_n(
						'At your current pace, your remaining credits should last about %d more day.',
						'At your current pace, your remaining credits should last about %d more days.',
						max( 1, intval( $estimated_days_remaining ) ),
						'beepbeep-ai-alt-text-generator'
					),
					max( 1, intval( $estimated_days_remaining ) )
				);
				$insight_tone = $is_low_credits ? 'warning' : 'healthy';
			}
		} elseif ( $is_low_credits ) {
			$insight_copy = __( 'You are getting close to your monthly limit.', 'beepbeep-ai-alt-text-generator' );
			$insight_tone = 'warning';
		} elseif ( $credits_used <= 0 || $usage_percent <= 25 ) {
			$insight_copy = __( 'You still have plenty of credits available this cycle.', 'beepbeep-ai-alt-text-generator' );
			$insight_tone = 'healthy';
		} else {
			$insight_copy = __( 'Your current usage is tracking comfortably for this cycle.', 'beepbeep-ai-alt-text-generator' );
			$insight_tone = 'healthy';
		}

		$automation_copy = $is_pro_plan
			? __( 'On-upload automation is available in Settings and can keep new uploads covered between manual cleanup passes.', 'beepbeep-ai-alt-text-generator' )
			: __( 'Growth can keep new uploads optimized automatically, reducing manual work each month.', 'beepbeep-ai-alt-text-generator' );

		if ( $is_pro_plan ) {
			$upgrade_context = __( 'Your current plan supports automation for future uploads.', 'beepbeep-ai-alt-text-generator' );
			$upgrade_eyebrow = __( 'Current plan', 'beepbeep-ai-alt-text-generator' );
			$upgrade_title = __( 'Keep future uploads covered', 'beepbeep-ai-alt-text-generator' );
			$upgrade_description = $missing_images > 0
				? sprintf(
					/* translators: %s: count of images still missing ALT text. */
					__( 'Use bulk tools to clear the remaining %s images faster, then let automation keep new uploads from rebuilding the backlog.', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $missing_images )
				)
				: __( 'Review your on-upload automation settings so new media keeps flowing through without another manual scan.', 'beepbeep-ai-alt-text-generator' );
			$upgrade_cta_label = __( 'Open automation settings', 'beepbeep-ai-alt-text-generator' );
			$upgrade_cta_type = 'link';
			$upgrade_cta_url = $automation_settings_url;
			$upgrade_footer = __( 'Growth automation is already available on your current plan.', 'beepbeep-ai-alt-text-generator' );
		} else {
			if ( $is_out_of_credits ) {
				$upgrade_context = __( 'You have reached your plan limit. Upgrade to continue.', 'beepbeep-ai-alt-text-generator' );
			} elseif ( $is_low_credits ) {
				$upgrade_context = __( 'You are close to this month’s allowance.', 'beepbeep-ai-alt-text-generator' );
			} elseif ( $credits_used > 0 && $credits_remaining > 0 ) {
				$upgrade_context = sprintf(
					/* translators: %s: credits used this cycle. */
					__( 'You have used %s credits this cycle. Scale without interruption.', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $credits_used )
				);
			} else {
				$upgrade_context = __( 'Automate your ALT workflow before usage becomes manual overhead.', 'beepbeep-ai-alt-text-generator' );
			}

			$upgrade_eyebrow = __( 'Growth · £12.99/month', 'beepbeep-ai-alt-text-generator' );
			$upgrade_title = __( 'Automate your ALT workflow', 'beepbeep-ai-alt-text-generator' );
			$upgrade_description = $missing_images > 0
				? sprintf(
					/* translators: %s: count of images still missing ALT text. */
					__( 'Automatically generate ALT text for future uploads and move through the remaining %s images faster.', 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $missing_images )
				)
				: __( 'Automatically generate ALT text for future uploads, optimize larger libraries faster, and reduce manual scanning each month.', 'beepbeep-ai-alt-text-generator' );
			$upgrade_cta_label = __( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' );
			$upgrade_cta_type = 'upgrade';
			$upgrade_cta_url = $billing_portal_url;
			$upgrade_footer = __( '£12.99/month • Cancel anytime', 'beepbeep-ai-alt-text-generator' );
		}

		return [
			'missingCount'           => max( 0, (int) $missing_images ),
			'weakCount'              => $bc_weak,
			'bannerMissingCount'     => $bc_miss,
			'bannerWeakCount'        => $bc_weak,
			'bannerTotalImages'      => $bc_tot,
			'creditsUsed'            => $credits_used,
			'creditsLimit'           => $credits_limit,
			'creditsRemaining'       => $credits_remaining,
			'daysUntilReset'         => $days_until_reset,
			'averageCreditsPerDay'   => $average_credits_per_day,
			'estimatedDaysRemaining' => $estimated_days_remaining,
			'usagePercent'           => $usage_percent,
			'isLowCredits'           => $is_low_credits,
			'isOutOfCredits'         => $is_out_of_credits,
			'isProPlan'              => $is_pro_plan,
			'hasUsageActivity'       => (bool) $has_usage_activity,
			'hasFilteredResults'     => (bool) $has_filtered_results,
			'planLabel'              => $plan_label,
			'summaryTone'            => $summary_tone,
			'summaryPositionCopy'    => $summary_position_copy,
			'summaryRemainingCopy'   => $summary_remaining_copy,
			'summaryResetCopy'       => $summary_reset_copy,
			'insightCopy'            => $insight_copy,
			'insightTone'            => $insight_tone,
			'automationCopy'         => $automation_copy,
			'automationUrl'          => $automation_settings_url,
			'upgradeContext'         => $upgrade_context,
			'upgradeEyebrow'         => $upgrade_eyebrow,
			'upgradeTitle'           => $upgrade_title,
			'upgradeDescription'     => $upgrade_description,
			'upgradeBenefits'        => [
				__( 'Automatic ALT generation for future uploads', 'beepbeep-ai-alt-text-generator' ),
				__( 'Bulk optimization', 'beepbeep-ai-alt-text-generator' ),
				__( 'Priority processing', 'beepbeep-ai-alt-text-generator' ),
				__( 'WooCommerce image support', 'beepbeep-ai-alt-text-generator' ),
				__( 'Multilingual SEO support', 'beepbeep-ai-alt-text-generator' ),
			],
			'upgradeCtaLabel'        => $upgrade_cta_label,
			'upgradeCtaType'         => $upgrade_cta_type,
			'upgradeCtaUrl'          => $upgrade_cta_url,
			'upgradeFooter'          => $upgrade_footer,
		];
	}

	/**
	 * Calculate a rough current-cycle usage pace.
	 *
	 * @param int $credits_used Credits used this cycle.
	 * @param int $reset_timestamp Reset timestamp.
	 * @param int $days_until_reset Days remaining in current cycle.
	 * @return array{average:float,elapsed_days:int}
	 */
	private static function calculate_average_credits_per_day($credits_used, $reset_timestamp, $days_until_reset) {
		$credits_used = max( 0, intval( $credits_used ) );
		if ( $credits_used <= 0 ) {
			return [
				'average'      => 0.0,
				'elapsed_days' => 0,
			];
		}

		$current_timestamp = (int) current_time( 'timestamp' );
		$elapsed_days = 0;
		$reset_timestamp = absint( $reset_timestamp );

		if ( $reset_timestamp > $current_timestamp ) {
			$cycle_start_timestamp = strtotime( '-1 month', $reset_timestamp );
			if ( $cycle_start_timestamp && $cycle_start_timestamp < $current_timestamp ) {
				$elapsed_days = max( 1, (int) ceil( ( $current_timestamp - $cycle_start_timestamp ) / DAY_IN_SECONDS ) );
			}
		}

		if ( $elapsed_days <= 0 && $days_until_reset > 0 ) {
			$elapsed_days = max( 1, 30 - max( 0, intval( $days_until_reset ) ) );
		}

		if ( $elapsed_days <= 0 ) {
			return [
				'average'      => 0.0,
				'elapsed_days' => 0,
			];
		}

		return [
			'average'      => round( $credits_used / $elapsed_days, 2 ),
			'elapsed_days' => $elapsed_days,
		];
	}

	/**
	 * Build human-readable reset copy.
	 *
	 * @param int    $days_until_reset Days until reset.
	 * @param string $reset_date Fallback reset date.
	 * @return string
	 */
	private static function build_reset_copy($days_until_reset, $reset_date) {
		$days_until_reset = max( 0, intval( $days_until_reset ) );
		$reset_date = sanitize_text_field( $reset_date );

		if ( $days_until_reset > 0 ) {
			return sprintf(
				/* translators: %d: days until the plan resets. */
				_n(
					'Your plan resets in %d day.',
					'Your plan resets in %d days.',
					$days_until_reset,
					'beepbeep-ai-alt-text-generator'
				),
				$days_until_reset
			);
		}

		if ( '' !== $reset_date ) {
			return sprintf(
				/* translators: %s: reset date. */
				__( 'Your plan resets on %s.', 'beepbeep-ai-alt-text-generator' ),
				$reset_date
			);
		}

		return __( 'Your plan resets with the next billing cycle.', 'beepbeep-ai-alt-text-generator' );
	}

	/**
	 * Approximate local MySQL datetimes for the current billing window (matches pace logic).
	 *
	 * @param array<string,mixed> $current_usage Quota row.
	 * @return array{from:string,to:string}
	 */
	private static function get_billing_cycle_local_mysql_range( array $current_usage ): array {
		$reset_ts   = absint( $current_usage['reset_timestamp'] ?? 0 );
		$days_until = max( 0, (int) ( $current_usage['days_until_reset'] ?? 0 ) );
		$now_ts     = (int) current_time( 'timestamp' );

		$start_ts = null;

		if ( $reset_ts > $now_ts ) {
			$cycle_start_ts = strtotime( '-1 month', $reset_ts );
			if ( $cycle_start_ts && $cycle_start_ts < $now_ts ) {
				$start_ts = (int) $cycle_start_ts;
			}
		}

		if ( null === $start_ts && $days_until > 0 ) {
			$elapsed_days = max( 1, 30 - $days_until );
			$start_ts     = $now_ts - ( $elapsed_days * (int) DAY_IN_SECONDS );
		}

		if ( null === $start_ts ) {
			$start_ts = $now_ts - (int) DAY_IN_SECONDS;
		}

		return [
			'from' => date( 'Y-m-d H:i:s', $start_ts ),
			'to'   => date( 'Y-m-d H:i:s', $now_ts ),
		];
	}

	/**
	 * @param array<string,mixed> $current_usage
	 * @param array<string,mixed> $surface       From build_usage_surface_state().
	 * @param list<array{source:string,total_credits:int,event_count:int}> $by_source
	 * @param array<string,mixed> $cycle_site_usage From get_site_usage() for the cycle window.
	 * @return array{forecast:string,driver:string,recommend:string,tone:string,chips:list<array{label:string}>}
	 */
	private static function build_usage_insights_payload( array $current_usage, array $surface, array $by_source, array $cycle_site_usage ): array {
		$credits_used      = max( 0, (int) ( $current_usage['used'] ?? 0 ) );
		$credits_limit     = max( 1, (int) ( $current_usage['limit'] ?? 1 ) );
		$credits_remaining = isset( $current_usage['remaining'] )
			? max( 0, (int) $current_usage['remaining'] )
			: max( 0, $credits_limit - $credits_used );
		$days_until_reset  = max( 0, (int) ( $current_usage['days_until_reset'] ?? 0 ) );
		$usage_percent     = min( 100, max( 0, (int) ( $surface['usagePercent'] ?? 0 ) ) );
		$is_pro            = ! empty( $surface['isProPlan'] );
		$is_out            = 0 === $credits_remaining;
		$is_low            = ! empty( $surface['isLowCredits'] );

		$pace                  = self::calculate_average_credits_per_day(
			$credits_used,
			(int) ( $current_usage['reset_timestamp'] ?? 0 ),
			$days_until_reset
		);
		$average_per_day       = (float) $pace['average'];
		$elapsed_days          = max( 0, (int) $pace['elapsed_days'] );
		$estimated_days_left   = ( $average_per_day > 0 && $credits_remaining > 0 )
			? (int) floor( $credits_remaining / $average_per_day )
			: null;
		$has_predictive_signal = $average_per_day > 0 && $credits_used >= 5 && $elapsed_days >= 3;

		$runs_out_before_reset = false;
		if ( ! $is_out && $has_predictive_signal && null !== $estimated_days_left && $days_until_reset > 0 ) {
			$runs_out_before_reset = $estimated_days_left < $days_until_reset;
		}

		// 1) Forecast
		if ( $is_out ) {
			$forecast = __( 'Credits are exhausted for this cycle; usage will resume after your plan resets.', 'beepbeep-ai-alt-text-generator' );
			$tone     = 'danger';
		} elseif ( $runs_out_before_reset ) {
			$forecast = __( 'At your current pace, your current plan may run out before reset.', 'beepbeep-ai-alt-text-generator' );
			$tone     = 'warning';
		} elseif ( $has_predictive_signal && null !== $estimated_days_left && $credits_remaining > 0 ) {
			if ( $days_until_reset > 0 && $estimated_days_left >= $days_until_reset ) {
				$forecast = __( 'At your current pace, your remaining credits should last through this billing cycle.', 'beepbeep-ai-alt-text-generator' );
				$tone     = 'healthy';
			} else {
				$est = max( 1, $estimated_days_left );
				$forecast = sprintf(
					/* translators: %d: estimated whole days remaining at current pace */
					_n(
						'At your current pace, your remaining credits may last about %d more day.',
						'At your current pace, your remaining credits may last about %d more days.',
						$est,
						'beepbeep-ai-alt-text-generator'
					),
					$est
				);
				$tone = $is_low ? 'warning' : 'healthy';
			}
		} elseif ( $credits_used <= 0 ) {
			$forecast = __( 'No credits have been used yet this cycle. Your allowance is ready when you start optimizing.', 'beepbeep-ai-alt-text-generator' );
			$tone     = 'healthy';
		} else {
			$forecast = __( 'Usage is steady and within plan limits this cycle.', 'beepbeep-ai-alt-text-generator' );
			$tone     = 'healthy';
		}

		// 2) Driver summary
		$logged_total = 0;
		foreach ( $by_source as $row ) {
			$logged_total += (int) ( $row['total_credits'] ?? 0 );
		}
		$top = isset( $by_source[0] ) && is_array( $by_source[0] ) ? $by_source[0] : null;

		if ( $logged_total < 1 ) {
			if ( $credits_used > 0 ) {
				$driver = __( 'Quota usage is tracked on your plan; detailed per-action sources will show here once events are logged locally.', 'beepbeep-ai-alt-text-generator' );
			} else {
				$driver = __( 'Once you generate ALT text, this summary highlights where credits are going.', 'beepbeep-ai-alt-text-generator' );
			}
		} elseif ( $top && ! empty( $top['source'] ) ) {
			$bucket = self::map_source_to_usage_driver_bucket( (string) $top['source'] );
			switch ( $bucket ) {
				case 'bulk':
					$driver = __( 'Bulk optimization is driving the highest usage in your logged activity this cycle.', 'beepbeep-ai-alt-text-generator' );
					break;
				case 'automation':
					$driver = __( 'Automation and queued jobs account for most of your logged credit usage this cycle.', 'beepbeep-ai-alt-text-generator' );
					break;
				case 'manual':
					$driver = __( 'Most logged credit usage this cycle came from manual ALT generation in the admin.', 'beepbeep-ai-alt-text-generator' );
					break;
				default:
					$driver = sprintf(
						/* translators: %s: formatted source label */
						__( 'Largest logged source this cycle: %s.', 'beepbeep-ai-alt-text-generator' ),
						Credit_Usage_Logger::format_source_label( (string) $top['source'] )
					);
			}
		} else {
			$driver = __( 'Usage is spread across several sources this cycle.', 'beepbeep-ai-alt-text-generator' );
		}

		// 3) Recommendation
		if ( $is_out ) {
			$recommend = __( 'Review usage activity below, then upgrade or wait for reset to continue without limits.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $runs_out_before_reset ) {
			$recommend = __( 'If this pace continues, upgrading before reset may avoid interruption.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( ! $is_pro && ( $is_low || $usage_percent >= 75 ) ) {
			$recommend = __( 'Upgrade to Growth if you want automatic coverage without monitoring credits closely.', 'beepbeep-ai-alt-text-generator' );
		} else {
			$recommend = __( 'Review usage activity below to see which actions are consuming credits fastest.', 'beepbeep-ai-alt-text-generator' );
		}

		// Optional chips (insight-only; avoid repeating headline quota numbers)
		$chips = [];
		if ( $has_predictive_signal && ! $is_out && null !== $estimated_days_left && $estimated_days_left > 0 ) {
			$chip_days = max( 1, $estimated_days_left );
			$chips[]   = [
				'label' => sprintf(
					/* translators: %d: approximate days */
					_n(
						'~%d day at current pace',
						'~%d days at current pace',
						$chip_days,
						'beepbeep-ai-alt-text-generator'
					),
					$chip_days
				),
			];
		}
		if ( $top && $logged_total > 0 ) {
			$chips[] = [
				'label' => sprintf(
					/* translators: %s: human-readable source name */
					__( 'Top source: %s', 'beepbeep-ai-alt-text-generator' ),
					Credit_Usage_Logger::format_source_label( (string) $top['source'] )
				),
			];
		}
		$cycle_images = max( 0, (int) ( $cycle_site_usage['total_images'] ?? 0 ) );
		if ( $cycle_images > 0 ) {
			$chips[] = [
				'label' => sprintf(
					/* translators: %s: count of distinct images in log */
					_n(
						'%s image in activity log (this window)',
						'%s images in activity log (this window)',
						$cycle_images,
						'beepbeep-ai-alt-text-generator'
					),
					number_format_i18n( $cycle_images )
				),
			];
		}
		$chips = array_slice( $chips, 0, 3 );

		return [
			'forecast'  => $forecast,
			'driver'    => $driver,
			'recommend' => $recommend,
			'tone'      => $tone,
			'chips'     => $chips,
		];
	}

	/**
	 * Group logger source slugs for driver copy.
	 */
	private static function map_source_to_usage_driver_bucket( string $source ): string {
		$source = sanitize_key( $source );
		if ( in_array( $source, [ 'bulk', 'bulk-regenerate' ], true ) ) {
			return 'bulk';
		}
		if ( in_array( $source, [ 'upload', 'queue', 'metadata' ], true ) ) {
			return 'automation';
		}
		if ( in_array( $source, [ 'manual', 'library', 'dashboard', 'ajax', 'save', 'update', 'onboarding' ], true ) ) {
			return 'manual';
		}
		return 'other';
	}

	/**
	 * Usage source options for the activity filter.
	 *
	 * @return array<string,string>
	 */
	private static function get_source_options() {
		return [
			''                => __( 'All sources', 'beepbeep-ai-alt-text-generator' ),
			'manual'          => Credit_Usage_Logger::format_source_label( 'manual' ),
			'dashboard'       => Credit_Usage_Logger::format_source_label( 'dashboard' ),
			'library'         => Credit_Usage_Logger::format_source_label( 'library' ),
			'bulk'            => Credit_Usage_Logger::format_source_label( 'bulk' ),
			'bulk-regenerate' => Credit_Usage_Logger::format_source_label( 'bulk-regenerate' ),
			'upload'          => Credit_Usage_Logger::format_source_label( 'upload' ),
			'queue'           => Credit_Usage_Logger::format_source_label( 'queue' ),
			'ajax'            => Credit_Usage_Logger::format_source_label( 'ajax' ),
			'onboarding'      => Credit_Usage_Logger::format_source_label( 'onboarding' ),
			'metadata'        => Credit_Usage_Logger::format_source_label( 'metadata' ),
			'update'          => Credit_Usage_Logger::format_source_label( 'update' ),
			'save'            => Credit_Usage_Logger::format_source_label( 'save' ),
		];
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
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback user lookup
				$db_user = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback user lookup
							$db_user = $wpdb->get_row(
								$wpdb->prepare(
									// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time user matching, table from static method
								$matching_wp_user_id = $wpdb->get_var(
									// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
										// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback user lookup
										$db_user_direct = $wpdb->get_row(
											$wpdb->prepare(
												// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-time user matching, table from static method
			$wp_user_id = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
