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

		// Handle table creation request
		if (isset($_POST['bbai_action']) && $_POST['bbai_action'] === 'create_credit_table') {
			check_admin_referer('bbai_create_credit_table', 'bbai_create_table_nonce');
			Credit_Usage_Logger::create_table();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Credit usage table created successfully!', 'beepbeep-ai-alt-text-generator') . '</p></div>';
		}

		// Get filter parameters
		$date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
		$source = isset($_GET['source']) ? sanitize_key($_GET['source']) : '';
		$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'summary'; // 'summary' or 'user_detail'

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
		
		// Get usage by user
		$usage_by_user = Credit_Usage_Logger::get_usage_by_user($query_args);
		
		// Diagnostic: Check if table exists and has any data
		$table_exists = Credit_Usage_Logger::table_exists();
		$debug_info = [];
		
		if ($table_exists) {
			global $wpdb;
			$table_name = Credit_Usage_Logger::table();
			$table_name_escaped = esc_sql($table_name);
			
			// Get total row count
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
			$total_rows = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name_escaped}`");
			$debug_info['total_rows'] = absint($total_rows);
			
			// Get distinct user count
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
			$distinct_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM `{$table_name_escaped}`");
			$debug_info['distinct_users'] = absint($distinct_users);
			
			// Get sample of actual data (for debugging) - raw query without filters
			if ($total_rows > 0) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
				$sample_data = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT user_id, attachment_id, credits_used, source, generated_at FROM `{$table_name_escaped}` ORDER BY generated_at DESC LIMIT %d",
						10
					),
					ARRAY_A
				);
				$debug_info['sample_data'] = $sample_data;
				
				// Get user breakdown summary
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
				$user_breakdown = $wpdb->get_results(
					"SELECT user_id, COUNT(*) as count, SUM(credits_used) as total_credits FROM `{$table_name_escaped}` GROUP BY user_id ORDER BY total_credits DESC",
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

		?>
		<div class="wrap" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			<h1 style="font-size: 28px; font-weight: 600; color: #0F172A; margin-bottom: 24px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
				<?php echo esc_html(get_admin_page_title()); ?>
			</h1>



			<?php if ($view === 'user_detail' && $user_id > 0) : ?>
				<p style="margin-bottom: 24px;">
					<a href="<?php echo esc_url(admin_url('upload.php?page=bbai-credit-usage')); ?>" 
					   class="button" 
					   style="background: #2563EB; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: background 0.15s; display: inline-block;"
					   onmouseover="this.style.background='#1D4ED8'" 
					   onmouseout="this.style.background='#2563EB'">
						<?php esc_html_e('← Back to Summary', 'beepbeep-ai-alt-text-generator'); ?>
					</a>
				</p>
			<?php endif; ?>

			<!-- Usage Summary Cards -->
			<div class="bbai-usage-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin: 32px 0;">
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e('Total Credits Allocated', 'beepbeep-ai-alt-text-generator'); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html(number_format_i18n($current_usage['limit'])); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php 
						$plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
						echo esc_html(ucfirst($plan)); 
						?> <?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?>
					</p>
				</div>
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e('Total Credits Used', 'beepbeep-ai-alt-text-generator'); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html(number_format_i18n($current_usage['used'])); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php 
						$percentage_display = isset($current_usage['percentage_display']) ? $current_usage['percentage_display'] : (isset($current_usage['percentage']) ? number_format_i18n($current_usage['percentage'], 0) : '0');
						echo esc_html($percentage_display); 
						?>% <?php esc_html_e('of limit', 'beepbeep-ai-alt-text-generator'); ?>
					</p>
				</div>
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #10B981; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html(number_format_i18n($current_usage['remaining'])); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php 
						esc_html_e('Resets', 'beepbeep-ai-alt-text-generator'); 
						$reset_date = isset($current_usage['reset_date']) && !empty($current_usage['reset_date']) 
							? $current_usage['reset_date'] 
							: (isset($current_usage['resetDate']) && !empty($current_usage['resetDate']) 
								? date('F j, Y', strtotime($current_usage['resetDate'])) 
								: date('F j, Y', strtotime('first day of next month')));
						echo ' ' . esc_html($reset_date); 
						?>
					</p>
				</div>
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e('Active Users', 'beepbeep-ai-alt-text-generator'); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html(number_format_i18n($usage_by_user['total'])); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e('Users with credit usage', 'beepbeep-ai-alt-text-generator'); ?>
					</p>
				</div>
			</div>

			<!-- Enhanced Multi-User Token Visualization (React Component) -->
			<div id="bbai-multiuser-token-bar-root" style="margin: 32px 0;"></div>

			<!-- Filters -->
			<div class="bbai-usage-filters" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; margin: 32px 0;">
				<form method="get" action="">
					<input type="hidden" name="page" value="bbai-credit-usage">
					<input type="hidden" name="view" value="<?php echo esc_attr($view); ?>">
					<?php if ($user_id > 0) : ?>
						<input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
					<?php endif; ?>

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
						<div>
							<label for="date_from" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
								<?php esc_html_e('Date From', 'beepbeep-ai-alt-text-generator'); ?>
							</label>
							<input type="date" 
								   id="date_from" 
								   name="date_from" 
								   value="<?php echo esc_attr($date_from); ?>" 
								   class="regular-text"
								   style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
						</div>
						<div>
							<label for="date_to" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
								<?php esc_html_e('Date To', 'beepbeep-ai-alt-text-generator'); ?>
							</label>
							<input type="date" 
								   id="date_to" 
								   name="date_to" 
								   value="<?php echo esc_attr($date_to); ?>" 
								   class="regular-text"
								   style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
						</div>
						<?php if ($view !== 'user_detail') : ?>
							<div>
								<label for="user_id_filter" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
									<?php esc_html_e('Filter by User', 'beepbeep-ai-alt-text-generator'); ?>
								</label>
								<select id="user_id_filter" 
										name="user_id" 
										class="regular-text"
										style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
									<option value=""><?php esc_html_e('All Users', 'beepbeep-ai-alt-text-generator'); ?></option>
									<?php foreach ($all_users as $user) : ?>
										<option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
											<?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div>
								<label for="source_filter" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
									<?php esc_html_e('Filter by Source', 'beepbeep-ai-alt-text-generator'); ?>
								</label>
								<select id="source_filter" 
										name="source" 
										class="regular-text"
										style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
									<option value=""><?php esc_html_e('All Sources', 'beepbeep-ai-alt-text-generator'); ?></option>
									<option value="manual" <?php selected($source, 'manual'); ?>><?php esc_html_e('Manual', 'beepbeep-ai-alt-text-generator'); ?></option>
									<option value="auto" <?php selected($source, 'auto'); ?>><?php esc_html_e('Auto Upload', 'beepbeep-ai-alt-text-generator'); ?></option>
									<option value="bulk" <?php selected($source, 'bulk'); ?>><?php esc_html_e('Bulk', 'beepbeep-ai-alt-text-generator'); ?></option>
									<option value="inline" <?php selected($source, 'inline'); ?>><?php esc_html_e('Inline', 'beepbeep-ai-alt-text-generator'); ?></option>
									<option value="queue" <?php selected($source, 'queue'); ?>><?php esc_html_e('Queue', 'beepbeep-ai-alt-text-generator'); ?></option>
								</select>
							</div>
						<?php endif; ?>
					</div>

					<div class="submit" style="display: flex; gap: 12px; margin-top: 20px;">
						<input type="submit" 
							   class="button button-primary" 
							   value="<?php esc_attr_e('Filter', 'beepbeep-ai-alt-text-generator'); ?>"
							   style="background: #2563EB; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; cursor: pointer; transition: background 0.15s;">
						<a href="<?php echo esc_url(admin_url('upload.php?page=bbai-credit-usage')); ?>" 
						   class="button"
						   style="background: #FFFFFF; color: #2563EB; border: 1px solid #2563EB; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: background 0.15s; display: inline-block;">
							<?php esc_html_e('Clear Filters', 'beepbeep-ai-alt-text-generator'); ?>
						</a>
					</div>
				</form>
			</div>

			<?php if ($view === 'user_detail' && $user_id > 0 && $user_details) : ?>
				<!-- User Detail View -->
				<?php
				$wp_user = get_user_by('ID', $user_id);
				$display_name = $wp_user ? $wp_user->display_name : __('Unknown User', 'beepbeep-ai-alt-text-generator');
				?>
				<div style="margin-top: 40px; width: 100%;">
					<h2 style="font-size: 24px; font-weight: 600; color: #0F172A; margin: 0 0 20px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: left;">
						<?php printf(esc_html__('Credit Usage Details: %s', 'beepbeep-ai-alt-text-generator'), esc_html($display_name)); ?>
					</h2>

					<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); width: 100%; display: block;">
						<table class="wp-list-table widefat fixed striped" style="margin: 0; border-collapse: collapse; width: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
							<thead>
								<tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Image', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Cost', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Model', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Source', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Generated At', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php if (!empty($user_details['items'])) : ?>
									<?php foreach ($user_details['items'] as $item) : ?>
										<tr style="border-bottom: 1px solid #E2E8F0; transition: background-color 0.15s; cursor: default;" onmouseover="this.style.backgroundColor='#F8FAFC'" onmouseout="this.style.backgroundColor=''">
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php if (!empty($item['attachment_url'])) : ?>
													<a href="<?php echo esc_url($item['attachment_url']); ?>" 
													   target="_blank" 
													   style="color: #2563EB; text-decoration: none; font-weight: 500; transition: color 0.15s;" 
													   onmouseover="this.style.color='#1D4ED8'; this.style.textDecoration='underline'" 
													   onmouseout="this.style.color='#2563EB'; this.style.textDecoration='none'"
													   onfocus="this.style.outline='2px solid #93C5FD'; this.style.outlineOffset='2px'; this.style.borderRadius='4px'"
													   onblur="this.style.outline='none'">
														<?php echo esc_html($item['attachment_title'] ?: $item['attachment_filename'] ?: 'Image #' . $item['attachment_id']); ?>
													</a>
												<?php else : ?>
													<span style="color: #64748B;">
														<?php echo esc_html('Image #' . $item['attachment_id']); ?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-weight: 500; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html(number_format_i18n($item['credits_used'])); ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #64748B; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php if ($item['token_cost'] !== null) : ?>
													$<?php echo esc_html(number_format_i18n($item['token_cost'], 6)); ?>
												<?php else : ?>
													<span style="color: #94A3B8;"><?php esc_html_e('N/A', 'beepbeep-ai-alt-text-generator'); ?></span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html($item['model'] ?: '-'); ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<span style="text-transform: capitalize;">
													<?php echo esc_html(ucfirst($item['source'])); ?>
												</span>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['generated_at']))); ?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="6" style="padding: 48px; text-align: center; color: #64748B; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
											<?php esc_html_e('No usage records found for this user.', 'beepbeep-ai-alt-text-generator'); ?>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>

					<?php if ($user_details['pages'] > 1) : ?>
						<div class="tablenav bottom">
							<?php
							$pagination_args = [
								'total' => $user_details['pages'],
								'current' => $user_details['page'],
								'base' => add_query_arg(['paged' => '%#%', 'view' => 'user_detail', 'user_id' => $user_id], admin_url('upload.php?page=bbai-credit-usage')),
							];
							echo wp_kses_post(paginate_links($pagination_args));
							?>
						</div>
					<?php endif; ?>
				</div>

			<?php else : ?>
				<!-- User Summary View -->
				<div style="margin-top: 40px;">
					<h2 style="font-size: 24px; font-weight: 600; color: #0F172A; margin: 0 0 20px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: left;">
						<?php esc_html_e('Usage by User', 'beepbeep-ai-alt-text-generator'); ?>
					</h2>

					<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); width: 100%;">
						<table class="wp-list-table widefat fixed striped" style="margin: 0; border-collapse: collapse; width: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
							<thead>
								<tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Images Processed', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Total Cost', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Last Activity', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e('Actions', 'beepbeep-ai-alt-text-generator'); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php if (!empty($usage_by_user['users'])) : ?>
									<?php foreach ($usage_by_user['users'] as $user_data) : ?>
										<tr style="border-bottom: 1px solid #E2E8F0; transition: background-color 0.15s; cursor: default;" onmouseover="this.style.backgroundColor='#F8FAFC'" onmouseout="this.style.backgroundColor=''">
											<td style="padding: 18px 20px;">
												<strong style="font-size: 14px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: block; line-height: 1.5;">
													<?php echo esc_html($user_data['display_name']); ?>
												</strong>
												<?php if (!empty($user_data['user_email'])) : ?>
													<span style="color: #64748B; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: block; margin-top: 4px; line-height: 1.5;">
														<?php echo esc_html($user_data['user_email']); ?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-weight: 500; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html(number_format_i18n($user_data['total_credits'])); ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-weight: 500; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html(number_format_i18n($user_data['total_images'])); ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #64748B; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php if ($user_data['total_cost'] > 0) : ?>
													$<?php echo esc_html(number_format_i18n($user_data['total_cost'], 4)); ?>
												<?php else : ?>
													<span style="color: #94A3B8;"><?php esc_html_e('N/A', 'beepbeep-ai-alt-text-generator'); ?></span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px;">
												<?php if ($user_data['last_activity']) : ?>
													<span style="display: block; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.5;">
														<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user_data['last_activity']))); ?>
													</span>
													<span style="display: block; color: #64748B; font-size: 13px; margin-top: 2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.5;">
														<?php echo esc_html(date_i18n(get_option('time_format'), strtotime($user_data['last_activity']))); ?>
													</span>
												<?php else : ?>
													<span style="color: #94A3B8; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e('N/A', 'beepbeep-ai-alt-text-generator'); ?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px;">
												<a href="<?php echo esc_url(add_query_arg(['view' => 'user_detail', 'user_id' => $user_data['user_id']], admin_url('upload.php?page=bbai-credit-usage'))); ?>" 
												   class="button button-small"
												   style="background: #2563EB; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: all 0.15s; display: inline-block; white-space: nowrap;"
												   onmouseover="this.style.background='#1D4ED8'; this.style.boxShadow='0 1px 3px 0 rgba(0, 0, 0, 0.1)'" 
												   onmouseout="this.style.background='#2563EB'; this.style.boxShadow='none'"
												   onfocus="this.style.outline='2px solid #93C5FD'; this.style.outlineOffset='2px'"
												   onblur="this.style.outline='none'">
													<?php esc_html_e('View Details', 'beepbeep-ai-alt-text-generator'); ?>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="6" style="padding: 32px; text-align: center;">
									<p style="color: #64748B; font-size: 14px; margin: 0 0 16px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
										<?php esc_html_e('No credit usage data found.', 'beepbeep-ai-alt-text-generator'); ?>
									</p>
									
									<?php if (current_user_can('manage_options') && isset($debug_info)) : ?>
										<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-top: 16px; text-align: left; max-width: 800px; margin-left: auto; margin-right: auto;">
											<strong style="font-size: 14px; font-weight: 600; color: #0F172A; display: block; margin-bottom: 12px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php esc_html_e('🔍 Diagnostic Information', 'beepbeep-ai-alt-text-generator'); ?>
											</strong>
											
											<div style="margin-bottom: 16px;">
												<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
													<?php esc_html_e('Table Status:', 'beepbeep-ai-alt-text-generator'); ?>
												</strong> 
												<?php if ($table_exists) : ?>
													<span style="color: #10B981; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														✓ <?php esc_html_e('Exists', 'beepbeep-ai-alt-text-generator'); ?>
													</span>
												<?php else : ?>
													<span style="color: #EF4444; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														✗ <?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?>
													</span>
													<br><br>
													<form method="post" action="" style="display: inline-block;">
														<?php wp_nonce_field('bbai_create_credit_table', 'bbai_create_table_nonce'); ?>
														<input type="hidden" name="bbai_action" value="create_credit_table">
														<button type="submit" 
																class="button button-primary" 
																style="background: #2563EB; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; cursor: pointer; transition: background 0.15s; margin-top: 8px;">
															<?php esc_html_e('Create Table Now', 'beepbeep-ai-alt-text-generator'); ?>
														</button>
													</form>
													<p style="margin-top: 12px; color: #64748B; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e('The credit usage tracking table is missing. Click the button above to create it. Future credit usage will be tracked automatically.', 'beepbeep-ai-alt-text-generator'); ?>
													</p>
												<?php endif; ?>
											</div>
											
											<?php if ($table_exists) : ?>
												<div style="margin-bottom: 12px;">
													<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e('Total Records:', 'beepbeep-ai-alt-text-generator'); ?>
													</strong> 
													<span style="color: #0F172A; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php echo esc_html(number_format_i18n($debug_info['total_rows'] ?? 0)); ?>
													</span>
												</div>
												
												<div style="margin-bottom: 12px;">
													<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e('Distinct Users:', 'beepbeep-ai-alt-text-generator'); ?>
													</strong> 
													<span style="color: #0F172A; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php echo esc_html(number_format_i18n($debug_info['distinct_users'] ?? 0)); ?>
													</span>
												</div>
												
												<div style="margin-bottom: 12px;">
													<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e('Query Results:', 'beepbeep-ai-alt-text-generator'); ?>
													</strong> 
													<span style="color: #0F172A; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php echo esc_html(number_format_i18n($debug_info['query_returned_users'] ?? 0)); ?> users, 
														<?php echo esc_html(number_format_i18n($debug_info['query_total'] ?? 0)); ?> total
													</span>
												</div>
												
												<?php if (!empty($debug_info['user_breakdown'])) : ?>
													<div style="margin-bottom: 16px;">
														<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
															<?php esc_html_e('User Breakdown (by credits):', 'beepbeep-ai-alt-text-generator'); ?>
														</strong>
														<div style="margin-top: 8px; padding-left: 16px;">
															<?php foreach ($debug_info['user_breakdown'] as $user) : ?>
																<div style="margin-bottom: 6px; font-size: 13px; color: #64748B; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">
																	User ID <?php echo esc_html($user['user_id']); ?>: 
																	<?php echo esc_html(number_format_i18n($user['total_credits'])); ?> credits 
																	(<?php echo esc_html($user['count']); ?> operations)
																	<?php if ($user['user_id'] > 0) : ?>
																		<?php 
																		$wp_user = get_user_by('ID', $user['user_id']); 
																		if ($wp_user) : 
																		?>
																			- <?php echo esc_html($wp_user->display_name); ?>
																		<?php endif; ?>
																	<?php else : ?>
																		- System
																	<?php endif; ?>
																</div>
															<?php endforeach; ?>
														</div>
													</div>
												<?php endif; ?>
												
												<?php if (!empty($debug_info['sample_data'])) : ?>
													<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
														<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
															<?php esc_html_e('Recent Activity (last 10 entries):', 'beepbeep-ai-alt-text-generator'); ?>
														</strong>
														<div style="margin-top: 8px; padding-left: 16px; font-family: 'Courier New', 'SF Mono', Monaco, 'Cascadia Code', monospace; font-size: 12px; background: #F8FAFC; padding: 12px; border-radius: 6px; border: 1px solid #E2E8F0;">
															<?php foreach ($debug_info['sample_data'] as $sample) : ?>
																<div style="margin-bottom: 6px; color: #64748B; line-height: 1.6;">
																	User: <?php echo esc_html($sample['user_id']); ?> | 
																	Image: <?php echo esc_html($sample['attachment_id']); ?> | 
																	Credits: <?php echo esc_html($sample['credits_used']); ?> | 
																	Source: <?php echo esc_html($sample['source']); ?> | 
																	<?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($sample['generated_at']))); ?>
																</div>
															<?php endforeach; ?>
														</div>
													</div>
												<?php endif; ?>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									
									<?php if ($site_usage['total_credits'] > 0) : ?>
										<p style="color: #64748B; margin-top: 16px; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">
											<?php 
											printf(
												esc_html__('Note: Site-wide usage shows %d credits used, but no per-user breakdown is available. This may be because: (1) usage occurred before per-user tracking was enabled, (2) all generations were marked as system/anonymous, or (3) the tracking table needs to be populated. Future generations will be tracked per-user.', 'beepbeep-ai-alt-text-generator'),
												esc_html(number_format_i18n($site_usage['total_credits']))
											);
											?>
										</p>
									<?php endif; ?>
									
									<?php if ($table_exists && ($debug_info['total_rows'] ?? 0) === 0) : ?>
										<p style="color: #EF4444; margin-top: 16px; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
											<strong><?php esc_html_e('No data has been logged yet.', 'beepbeep-ai-alt-text-generator'); ?></strong>
											<span style="color: #64748B;"><?php esc_html_e('Generate alt text for an image while logged in to see usage tracking in action.', 'beepbeep-ai-alt-text-generator'); ?></span>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
						</tbody>
					</table>

					<?php if ($usage_by_user['pages'] > 1) : ?>
						<div class="tablenav bottom">
							<?php
							$pagination_args = [
								'total' => $usage_by_user['pages'],
								'current' => $usage_by_user['page'],
								'base' => add_query_arg(['paged' => '%#%'], admin_url('upload.php?page=bbai-credit-usage')),
							];
							echo wp_kses_post(paginate_links($pagination_args));
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		
		<style>
		/* Enterprise Dashboard Styles */
		.bbai-usage-summary-cards .card:hover {
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
		}
		
		.bbai-usage-filters input[type="date"]:focus,
		.bbai-usage-filters select:focus {
			outline: 2px solid #2563EB;
			outline-offset: 2px;
			border-color: #2563EB;
		}
		
		.bbai-usage-filters .button-primary:hover {
			background: #1D4ED8 !important;
		}
		
		.bbai-usage-filters .button:not(.button-primary):hover {
			background: #F8FAFC !important;
		}
		
		.wp-list-table tbody tr:hover {
			background-color: #F8FAFC !important;
		}
		
		.wp-list-table thead th {
			background: #F8FAFC;
			border-bottom: 2px solid #E2E8F0 !important;
		}
		
		.button-small:hover {
			background: #1D4ED8 !important;
		}
		
		/* Ensure full-width table cards */
		.wrap > div > .card,
		.wrap .wp-list-table-container {
			width: 100% !important;
			max-width: 100% !important;
			box-sizing: border-box;
		}
		
		.wp-list-table {
			width: 100% !important;
			table-layout: auto;
		}
		
		/* Override WordPress default wrap constraints for full width */
		.wrap[style*="font-family"] {
			max-width: 100% !important;
			width: 100% !important;
		}
		</style>
		
		<script>
		(function($) {
			'use strict';
			
			// Animate numbers when tab becomes visible
			function animateCreditUsageNumbers() {
				var $numberElements = $('.bbai-card-value');
				
				$numberElements.each(function(index) {
					var $el = $(this);
					var originalValue = $el.text().trim();
					
					// Extract numeric value
					var numericMatch = originalValue.match(/[\d,]+\.?\d*/);
					if (!numericMatch) return;
					
					var finalValue = parseFloat(numericMatch[0].replace(/,/g, ''));
					if (isNaN(finalValue)) return;
					
					// Animate from 0 to final value
					var duration = 1000;
					var startTime = null;
					var startValue = 0;
					
					function animate(currentTime) {
						if (!startTime) startTime = currentTime;
						var progress = Math.min((currentTime - startTime) / duration, 1);
						
						// Easing function (ease-out)
						var easeProgress = 1 - Math.pow(1 - progress, 3);
						var currentValue = startValue + (finalValue - startValue) * easeProgress;
						
						// Format number
						var formatted = originalValue.replace(numericMatch[0], 
							finalValue % 1 === 0 ? 
								Math.round(currentValue).toLocaleString() : 
								currentValue.toFixed(1).toLocaleString()
						);
						
						$el.text(formatted);
						
						if (progress < 1) {
							requestAnimationFrame(animate);
						} else {
							$el.text(originalValue);
						}
					}
					
					if ($el.is(':visible')) {
						setTimeout(function() {
							requestAnimationFrame(animate);
						}, index * 100); // Stagger animations
					}
				});
			}
			
			$(document).ready(function() {
				// Check if we're on the credit-usage tab
				if (window.location.search.indexOf('tab=credit-usage') !== -1 || 
				    $('.bbai-credit-usage-tab-content').is(':visible')) {
					setTimeout(animateCreditUsageNumbers, 200);
				}
			});
			
			// Re-run when tab switches to credit-usage
			$(document).on('click', '.bbai-nav-link[href*="credit-usage"]', function() {
				setTimeout(animateCreditUsageNumbers, 300);
			});
		})(jQuery);
		</script>
		<?php
	}
}

