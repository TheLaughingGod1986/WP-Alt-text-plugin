<?php
/**
 * Credit Usage Admin Page for BeepBeep AI
 * Displays per-user credit usage breakdown and detailed statistics
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
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
		// 'upload.php', // Parent: Media menu
		// __('Credit Usage', 'beepbeep-ai-alt-text-generator'),
		// __('Credit Usage', 'beepbeep-ai-alt-text-generator'),
		// 'manage_options',
		// 'bbai-credit-usage',
		// [__CLASS__, 'render_page']
		// );
	}

	/**
	 * Render the credit usage content (for use in tabs).
	 */
	public static function render_page_content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to access this page.', 'beepbeep-ai-alt-text-generator' ) . '</p></div>';
			return;
		}

		// Handle table creation request
		if ( isset( $_POST['bbai_action'] ) && $_POST['bbai_action'] === 'create_credit_table' ) {
			check_admin_referer( 'bbai_create_credit_table', 'bbai_create_table_nonce' );
			Credit_Usage_Logger::create_table();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Credit usage table created successfully!', 'beepbeep-ai-alt-text-generator' ) . '</p></div>';
		}

		// Handle fix credit counts request (fix OpenAI tokens -> 1 credit per generation)
		if ( isset( $_POST['bbai_action'] ) && $_POST['bbai_action'] === 'fix_credit_counts' ) {
			check_admin_referer( 'bbai_fix_credit_counts', 'bbai_fix_credits_nonce' );
			global $wpdb;
			$table = Credit_Usage_Logger::table();
			if ( Credit_Usage_Logger::table_exists() ) {
				$updated = $wpdb->query( "UPDATE `{$table}` SET credits_used = 1 WHERE credits_used != 1" );
				echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
					/* translators: %d is the number of records fixed */
					esc_html__( 'Fixed %d credit usage records. Each generation now correctly counts as 1 credit.', 'beepbeep-ai-alt-text-generator' ),
					intval( $updated )
				) . '</p></div>';
			}
		}

		// Get filter parameters
		// Default to current billing period (first of month) if no date_from is specified
		$period_start_default = gmdate( 'Y-m-01' ); // First day of current month
		$date_from_raw = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_from = ! empty( $date_from_raw ) ? $date_from_raw : $period_start_default;
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$user_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$source    = isset( $_GET['source'] ) ? sanitize_key( $_GET['source'] ) : '';
		$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$view      = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'summary'; // 'summary' or 'user_detail'
		$show_all_time = isset( $_GET['show_all'] ) && $_GET['show_all'] === '1';

		// Build query args - use current period by default, unless show_all is set
		$query_args = array(
			'date_from' => $show_all_time ? '' : $date_from,
			'date_to'   => $date_to,
			'source'    => $source,
			'user_id'   => $user_id > 0 ? $user_id : null,
			'per_page'  => 10,
			'page'      => $page,
		);

		// Use the SAME data source as the Dashboard - Usage_Tracker::get_stats_display()
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		$usage_stats = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display( true ); // Force refresh from API
		
		// Get raw values - same way as Dashboard
		$used      = max( 0, intval( $usage_stats['used'] ?? 0 ) );
		$limit     = max( 1, intval( $usage_stats['limit'] ?? 50 ) );
		$remaining = max( 0, $limit - $used );
		$plan      = $usage_stats['plan'] ?? 'free';
		
		// Cap used at limit to prevent showing > 100%
		if ( $used > $limit ) {
			$used      = $limit;
			$remaining = 0;
		}
		
		// Calculate percentage - same way as Dashboard
		$percentage = $limit > 0 ? round( ( $used / $limit ) * 100 ) : 0;

		// Calculate current billing period (first day of current month) for table filtering
		$period_start = gmdate( 'Y-m-01' );
		
		$current_usage = array(
			'used'            => $used,
			'limit'           => $limit,
			'remaining'       => $remaining,
			'percentage'      => $percentage,
			'plan'            => $plan,
			'reset_date'      => $usage_stats['reset_date'] ?? gmdate( 'F j, Y', strtotime( 'first day of next month' ) ),
		);

		// Also get local usage for the per-user breakdown table
		$site_usage = Credit_Usage_Logger::get_site_usage( $query_args );

		// Ensure credit usage table exists - create if missing
		if ( ! Credit_Usage_Logger::table_exists() ) {
			Credit_Usage_Logger::create_table();
		}

		// Get usage by user
		$usage_by_user = Credit_Usage_Logger::get_usage_by_user( $query_args );

		// Diagnostic: Check if table exists and has any data
		$table_exists = Credit_Usage_Logger::table_exists();
		$debug_info   = array();

		if ( $table_exists ) {
			global $wpdb;
			$table_name         = Credit_Usage_Logger::table();
			$table_name_escaped = esc_sql( $table_name );

			// Get total row count
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
			$total_rows               = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name_escaped}`" );
			$debug_info['total_rows'] = absint( $total_rows );

			// Get distinct user count
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
			$distinct_users               = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM `{$table_name_escaped}`" );
			$debug_info['distinct_users'] = absint( $distinct_users );

			// Get sample of actual data (for debugging) - raw query without filters
			if ( $total_rows > 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
				$sample_data               = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT user_id, attachment_id, credits_used, source, generated_at FROM `{$table_name_escaped}` ORDER BY generated_at DESC LIMIT %d",
						10
					),
					ARRAY_A
				);
				$debug_info['sample_data'] = $sample_data;

				// Get user breakdown summary
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
				$user_breakdown               = $wpdb->get_results(
					"SELECT user_id, COUNT(*) as count, SUM(credits_used) as total_credits FROM `{$table_name_escaped}` GROUP BY user_id ORDER BY total_credits DESC",
					ARRAY_A
				);
				$debug_info['user_breakdown'] = $user_breakdown;
			} else {
				$debug_info['sample_data']    = array();
				$debug_info['user_breakdown'] = array();
			}
		} else {
			$debug_info['table_exists'] = false;
		}

		// Additional check: verify query returned results
		$debug_info['query_returned_users'] = count( $usage_by_user['users'] ?? array() );
		$debug_info['query_total']          = $usage_by_user['total'] ?? 0;

		// Get all users for filter dropdown
		$all_users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );

		// Get user details if viewing specific user
		$user_details = null;
		if ( $view === 'user_detail' && $user_id > 0 ) {
			$user_details = Credit_Usage_Logger::get_user_details( $user_id, $query_args );
		}

		?>
		<div class="wrap" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			<h1 style="font-size: 28px; font-weight: 600; color: #0F172A; margin-bottom: 24px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>



			<?php if ( $view === 'user_detail' && $user_id > 0 ) : ?>
				<p style="margin-bottom: 24px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=optti&tab=credit-usage' ) ); ?>"
						class="button"
						style="background: #2563EB; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: background 0.15s; display: inline-block;"
						onmouseover="this.style.background='#1D4ED8'"
						onmouseout="this.style.background='#2563EB'">
						<?php esc_html_e( '← Back to Summary', 'beepbeep-ai-alt-text-generator' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<!-- Usage Summary Cards -->
			<div class="bbai-usage-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin: 32px 0;">
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e( 'Total Credits Allocated', 'beepbeep-ai-alt-text-generator' ); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html( number_format_i18n( $current_usage['limit'] ) ); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php
						$plan = isset( $current_usage['plan'] ) && ! empty( $current_usage['plan'] ) ? $current_usage['plan'] : 'free';
						echo esc_html( ucfirst( $plan ) );
						?>
						<?php esc_html_e( 'Plan', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
				</div>
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e( 'Total Credits Used', 'beepbeep-ai-alt-text-generator' ); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html( number_format_i18n( $current_usage['used'] ) ); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php
						$percentage_display = isset( $current_usage['percentage_display'] ) ? $current_usage['percentage_display'] : ( isset( $current_usage['percentage'] ) ? number_format_i18n( $current_usage['percentage'], 0 ) : '0' );
						echo esc_html( $percentage_display );
						?>
						% <?php esc_html_e( 'of limit', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
				</div>
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e( 'Credits Remaining', 'beepbeep-ai-alt-text-generator' ); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #10B981; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html( number_format_i18n( $current_usage['remaining'] ) ); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php
						esc_html_e( 'Resets', 'beepbeep-ai-alt-text-generator' );
						$reset_date = isset( $current_usage['reset_date'] ) && ! empty( $current_usage['reset_date'] )
							? $current_usage['reset_date']
							: ( isset( $current_usage['resetDate'] ) && ! empty( $current_usage['resetDate'] )
							? gmdate( 'F j, Y', strtotime( $current_usage['resetDate'] ) )
							: gmdate( 'F j, Y', strtotime( 'first day of next month' ) ) );
						echo ' ' . esc_html( $reset_date );
						?>
					</p>
				</div>
				<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
					<h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e( 'Active Users', 'beepbeep-ai-alt-text-generator' ); ?>
					</h3>
					<p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
						<?php echo esc_html( number_format_i18n( $usage_by_user['total'] ) ); ?>
					</p>
					<p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
						<?php esc_html_e( 'Users with credit usage', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
				</div>
			</div>

			<!-- Enhanced Multi-User Token Visualization (React Component) -->
			<div id="bbai-multiuser-token-bar-root" style="margin: 32px 0;"></div>

			<!-- Filters -->
			<div class="bbai-usage-filters" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; margin: 32px 0;">
				<form method="get" action="">
					<input type="hidden" name="page" value="bbai">
					<input type="hidden" name="tab" value="credit-usage">
					<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
						<div>
							<label for="date_from" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
								<?php esc_html_e( 'Date From', 'beepbeep-ai-alt-text-generator' ); ?>
							</label>
							<input type="date" 
									id="date_from" 
									name="date_from" 
									value="<?php echo esc_attr( $date_from ); ?>" 
									class="regular-text"
									style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
						</div>
						<div>
							<label for="date_to" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
								<?php esc_html_e( 'Date To', 'beepbeep-ai-alt-text-generator' ); ?>
							</label>
							<input type="date" 
									id="date_to" 
									name="date_to" 
									value="<?php echo esc_attr( $date_to ); ?>" 
									class="regular-text"
									style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
						</div>
						<div>
							<label for="user_id_filter" style="display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
								<?php esc_html_e( 'Filter by User', 'beepbeep-ai-alt-text-generator' ); ?>
							</label>
							<select id="user_id_filter" 
									name="user_id" 
									class="regular-text"
									style="width: 100%; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A; background: #FFFFFF; transition: border-color 0.15s;">
								<option value=""><?php esc_html_e( 'All Users', 'beepbeep-ai-alt-text-generator' ); ?></option>
								<?php foreach ( $all_users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_id, $user->ID ); ?>>
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="submit" style="display: flex; gap: 12px; margin-top: 20px;">
						<input type="submit"
								class="button button-primary"
								value="<?php esc_attr_e( 'Filter', 'beepbeep-ai-alt-text-generator' ); ?>"
								style="background: #2563EB; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; cursor: pointer; transition: background 0.15s;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=optti&tab=credit-usage' ) ); ?>"
							class="button"
							style="background: #FFFFFF; color: #2563EB; border: 1px solid #2563EB; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: background 0.15s; display: inline-block;">
							<?php esc_html_e( 'Clear Filters', 'beepbeep-ai-alt-text-generator' ); ?>
						</a>
					</div>
				</form>
			</div>

			<?php if ( $view === 'user_detail' && $user_id > 0 && $user_details ) : ?>
				<!-- User Detail View -->
				<?php
				$wp_user      = get_user_by( 'ID', $user_id );
				$display_name = $wp_user ? $wp_user->display_name : __( 'Unknown User', 'beepbeep-ai-alt-text-generator' );
				?>
				<div style="margin-top: 40px; width: 100%; max-width: 100%; box-sizing: border-box;">
					<h2 style="font-size: 24px; font-weight: 600; color: #0F172A; margin: 0 0 20px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: left;">
						<?php printf( esc_html__( 'Credit Usage Details: %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $display_name ) ); ?>
					</h2>

					<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); width: 100%; max-width: 100%; display: block; box-sizing: border-box;">
						<table class="wp-list-table widefat fixed striped" style="margin: 0; border-collapse: collapse; width: 100%; table-layout: auto; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
							<thead>
								<tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'Image', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'Credits Used', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'Generated At', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $user_details['items'] ) ) : ?>
									<?php foreach ( $user_details['items'] as $item ) : ?>
										<tr style="border-bottom: 1px solid #E2E8F0; transition: background-color 0.15s; cursor: default;" onmouseover="this.style.backgroundColor='#F8FAFC'" onmouseout="this.style.backgroundColor=''">
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php if ( ! empty( $item['attachment_url'] ) ) : ?>
													<a href="<?php echo esc_url( $item['attachment_url'] ); ?>" 
														target="_blank" 
														style="color: #2563EB; text-decoration: none; font-weight: 500; transition: color 0.15s;" 
														onmouseover="this.style.color='#1D4ED8'; this.style.textDecoration='underline'" 
														onmouseout="this.style.color='#2563EB'; this.style.textDecoration='none'"
														onfocus="this.style.outline='2px solid #93C5FD'; this.style.outlineOffset='2px'; this.style.borderRadius='4px'"
														onblur="this.style.outline='none'">
														<?php echo esc_html( $item['attachment_title'] ?: $item['attachment_filename'] ?: 'Image #' . $item['attachment_id'] ); ?>
													</a>
												<?php else : ?>
													<span style="color: #64748B;">
														<?php echo esc_html( 'Image #' . $item['attachment_id'] ); ?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-weight: 500; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html( number_format_i18n( $item['credits_used'] ) ); ?>
												<?php if ( isset( $item['generation_count'] ) && intval( $item['generation_count'] ) > 1 ) : ?>
													<span style="background: #E0E7FF; color: #4338CA; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-left: 6px; font-weight: 500;">
														<?php
														printf(
															/* translators: %d is the number of times the image was generated */
															esc_html__( '%d generations', 'beepbeep-ai-alt-text-generator' ),
															intval( $item['generation_count'] )
														);
														?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['generated_at'] ) ) ); ?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="3" style="padding: 48px; text-align: center; color: #64748B; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
											<?php esc_html_e( 'No usage records found for this user.', 'beepbeep-ai-alt-text-generator' ); ?>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>

					<!-- Pagination -->
					<?php if ( $user_details['pages'] > 1 ) : ?>
						<?php
						$base_url = add_query_arg(
							array(
								'view'    => 'user_detail',
								'user_id' => $user_id,
								'tab'     => 'credit-usage',
							),
							admin_url( 'admin.php?page=optti' )
						);
						$current_pg = $user_details['page'];
						$total_pgs  = $user_details['pages'];
						$start_item = ( ( $current_pg - 1 ) * $user_details['per_page'] ) + 1;
						$end_item   = min( $current_pg * $user_details['per_page'], $user_details['total'] );
						?>
						<div class="bbai-pagination">
							<div class="bbai-pagination-info">
								<?php
								printf(
									/* translators: %1$d is start, %2$d is end, %3$d is total */
									esc_html__( 'Showing %1$d-%2$d of %3$d images', 'beepbeep-ai-alt-text-generator' ),
									$start_item,
									$end_item,
									$user_details['total']
								);
								?>
							</div>
							<div class="bbai-pagination-controls">
								<?php if ( $current_pg > 1 ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>" class="bbai-pagination-btn" title="<?php esc_attr_e( 'First page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'First', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
									<a href="<?php echo esc_url( add_query_arg( 'paged', $current_pg - 1, $base_url ) ); ?>" class="bbai-pagination-btn" title="<?php esc_attr_e( 'Previous page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
								<?php else : ?>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'First', 'beepbeep-ai-alt-text-generator' ); ?></span>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php endif; ?>
								
								<div class="bbai-pagination-pages">
									<?php for ( $i = 1; $i <= $total_pgs; $i++ ) : ?>
										<?php if ( $i === $current_pg ) : ?>
											<span class="bbai-pagination-btn bbai-pagination-btn--current"><?php echo esc_html( $i ); ?></span>
										<?php else : ?>
											<a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="bbai-pagination-btn"><?php echo esc_html( $i ); ?></a>
										<?php endif; ?>
									<?php endfor; ?>
								</div>
								
								<?php if ( $current_pg < $total_pgs ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'paged', $current_pg + 1, $base_url ) ); ?>" class="bbai-pagination-btn" title="<?php esc_attr_e( 'Next page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
									<a href="<?php echo esc_url( add_query_arg( 'paged', $total_pgs, $base_url ) ); ?>" class="bbai-pagination-btn" title="<?php esc_attr_e( 'Last page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'Last', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
								<?php else : ?>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?></span>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'Last', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

			<?php else : ?>
				<!-- User Summary View -->
				<div style="margin-top: 40px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
						<h2 style="font-size: 24px; font-weight: 600; color: #0F172A; margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: left;">
							<?php esc_html_e( 'Usage by User', 'beepbeep-ai-alt-text-generator' ); ?>
						</h2>
						<?php
						// Check if credits need fixing (any record with credits_used > 1)
						if ( Credit_Usage_Logger::table_exists() ) {
							global $wpdb;
							$table           = Credit_Usage_Logger::table();
							$table_escaped   = esc_sql( $table );
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
							$needs_fix_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_escaped}` WHERE credits_used > 1" ) );
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
							$total_credits   = intval( $wpdb->get_var( "SELECT SUM(credits_used) FROM `{$table_escaped}`" ) );
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
							$total_records   = intval( $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_escaped}`" ) );

							// Show fix button if total credits != total records (should be 1 credit per record)
							if ( $needs_fix_count > 0 || ( $total_credits > 0 && $total_credits !== $total_records ) ) :
								?>
								<form method="post" action="" style="display: inline-block; margin-right: 10px;">
									<?php wp_nonce_field( 'bbai_fix_credit_counts', 'bbai_fix_credits_nonce' ); ?>
									<input type="hidden" name="bbai_action" value="fix_credit_counts">
									<button type="submit" 
											class="button" 
											style="background: #F59E0B; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; cursor: pointer; transition: background 0.15s;"
											onmouseover="this.style.background='#D97706'"
											onmouseout="this.style.background='#F59E0B'"
											title="<?php printf( esc_attr__( 'Total: %1$d credits for %2$d records. Should be %2$d.', 'beepbeep-ai-alt-text-generator' ), $total_credits, $total_records ); ?>">
										🔧 <?php esc_html_e( 'Fix Credit Counts', 'beepbeep-ai-alt-text-generator' ); ?>
										<span style="background: rgba(255,255,255,0.3); padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-size: 11px;">
											<?php echo esc_html( $total_credits ); ?> → <?php echo esc_html( $total_records ); ?>
										</span>
									</button>
								</form>
								<?php
							endif;

						}
						?>
					</div>

					<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); width: 100%; max-width: 100%; box-sizing: border-box;">
						<table class="wp-list-table widefat fixed striped" style="margin: 0; border-collapse: collapse; width: 100%; table-layout: auto; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
							<thead>
								<tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'User', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'Images Processed', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'Last Activity', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
									<th style="padding: 16px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform: uppercase; letter-spacing: 0.025em;">
										<?php esc_html_e( 'Actions', 'beepbeep-ai-alt-text-generator' ); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $usage_by_user['users'] ) ) : ?>
									<?php foreach ( $usage_by_user['users'] as $user_data ) : ?>
										<tr style="border-bottom: 1px solid #E2E8F0; transition: background-color 0.15s; cursor: default;" onmouseover="this.style.backgroundColor='#F8FAFC'" onmouseout="this.style.backgroundColor=''">
											<td style="padding: 18px 20px;">
												<strong style="font-size: 14px; font-weight: 600; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: block; line-height: 1.5;">
													<?php echo esc_html( $user_data['display_name'] ); ?>
												</strong>
												<?php if ( ! empty( $user_data['user_email'] ) ) : ?>
													<span style="color: #64748B; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: block; margin-top: 4px; line-height: 1.5;">
														<?php echo esc_html( $user_data['user_email'] ); ?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px; font-size: 14px; color: #334155; font-weight: 500; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php echo esc_html( number_format_i18n( $user_data['total_images'] ) ); ?>
											</td>
											<td style="padding: 18px 20px;">
												<?php if ( $user_data['last_activity'] ) : ?>
													<span style="display: block; font-size: 14px; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.5;">
														<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user_data['last_activity'] ) ) ); ?>
													</span>
													<span style="display: block; color: #64748B; font-size: 13px; margin-top: 2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.5;">
														<?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $user_data['last_activity'] ) ) ); ?>
													</span>
												<?php else : ?>
													<span style="color: #94A3B8; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e( 'N/A', 'beepbeep-ai-alt-text-generator' ); ?>
													</span>
												<?php endif; ?>
											</td>
											<td style="padding: 18px 20px;">
												<a href="
												<?php
												echo esc_url(
													add_query_arg(
														array(
															'view' => 'user_detail',
															'user_id' => $user_data['user_id'],
															'tab'  => 'credit-usage',
														),
														admin_url( 'admin.php?page=optti' )
													)
												);
												?>
															"
													class="button button-small"
													style="background: #2563EB; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: all 0.15s; display: inline-block; white-space: nowrap;"
													onmouseover="this.style.background='#1D4ED8'; this.style.boxShadow='0 1px 3px 0 rgba(0, 0, 0, 0.1)'"
													onmouseout="this.style.background='#2563EB'; this.style.boxShadow='none'"
													onfocus="this.style.outline='2px solid #93C5FD'; this.style.outlineOffset='2px'"
													onblur="this.style.outline='none'">
													<?php esc_html_e( 'View Details', 'beepbeep-ai-alt-text-generator' ); ?>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="6" style="padding: 32px; text-align: center;">
									<p style="color: #64748B; font-size: 14px; margin: 0 0 16px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
										<?php esc_html_e( 'No credit usage data found.', 'beepbeep-ai-alt-text-generator' ); ?>
									</p>
									
									<?php if ( current_user_can( 'manage_options' ) && isset( $debug_info ) ) : ?>
										<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-top: 16px; text-align: left; max-width: 800px; margin-left: auto; margin-right: auto;">
											<strong style="font-size: 14px; font-weight: 600; color: #0F172A; display: block; margin-bottom: 12px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
												<?php esc_html_e( '🔍 Diagnostic Information', 'beepbeep-ai-alt-text-generator' ); ?>
											</strong>
											
											<div style="margin-bottom: 16px;">
												<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
													<?php esc_html_e( 'Table Status:', 'beepbeep-ai-alt-text-generator' ); ?>
												</strong> 
												<?php if ( $table_exists ) : ?>
													<span style="color: #10B981; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														✓ <?php esc_html_e( 'Exists', 'beepbeep-ai-alt-text-generator' ); ?>
													</span>
												<?php else : ?>
													<span style="color: #EF4444; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														✗ <?php esc_html_e( 'Missing', 'beepbeep-ai-alt-text-generator' ); ?>
													</span>
													<br><br>
													<form method="post" action="" style="display: inline-block;">
														<?php wp_nonce_field( 'bbai_create_credit_table', 'bbai_create_table_nonce' ); ?>
														<input type="hidden" name="bbai_action" value="create_credit_table">
														<button type="submit" 
																class="button button-primary" 
																style="background: #2563EB; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; cursor: pointer; transition: background 0.15s; margin-top: 8px;">
															<?php esc_html_e( 'Create Table Now', 'beepbeep-ai-alt-text-generator' ); ?>
														</button>
													</form>
													<p style="margin-top: 12px; color: #64748B; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e( 'The credit usage tracking table is missing. Click the button above to create it. Future credit usage will be tracked automatically.', 'beepbeep-ai-alt-text-generator' ); ?>
													</p>
												<?php endif; ?>
											</div>
											
											<?php if ( $table_exists ) : ?>
												<div style="margin-bottom: 12px;">
													<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e( 'Total Records:', 'beepbeep-ai-alt-text-generator' ); ?>
													</strong> 
													<span style="color: #0F172A; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php echo esc_html( number_format_i18n( $debug_info['total_rows'] ?? 0 ) ); ?>
													</span>
												</div>
												
												<div style="margin-bottom: 12px;">
													<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e( 'Distinct Users:', 'beepbeep-ai-alt-text-generator' ); ?>
													</strong> 
													<span style="color: #0F172A; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php echo esc_html( number_format_i18n( $debug_info['distinct_users'] ?? 0 ) ); ?>
													</span>
												</div>
												
												<div style="margin-bottom: 12px;">
													<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php esc_html_e( 'Query Results:', 'beepbeep-ai-alt-text-generator' ); ?>
													</strong> 
													<span style="color: #0F172A; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
														<?php echo esc_html( number_format_i18n( $debug_info['query_returned_users'] ?? 0 ) ); ?> users, 
														<?php echo esc_html( number_format_i18n( $debug_info['query_total'] ?? 0 ) ); ?> total
													</span>
												</div>
												
												<?php if ( ! empty( $debug_info['user_breakdown'] ) ) : ?>
													<div style="margin-bottom: 16px;">
														<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
															<?php esc_html_e( 'User Breakdown (by credits):', 'beepbeep-ai-alt-text-generator' ); ?>
														</strong>
														<div style="margin-top: 8px; padding-left: 16px;">
															<?php foreach ( $debug_info['user_breakdown'] as $user ) : ?>
																<div style="margin-bottom: 6px; font-size: 13px; color: #64748B; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">
																	User ID <?php echo esc_html( $user['user_id'] ); ?>: 
																	<?php echo esc_html( number_format_i18n( $user['total_credits'] ) ); ?> credits 
																	(<?php echo esc_html( $user['count'] ); ?> operations)
																	<?php if ( $user['user_id'] > 0 ) : ?>
																		<?php
																		$wp_user = get_user_by( 'ID', $user['user_id'] );
																		if ( $wp_user ) :
																			?>
																			- <?php echo esc_html( $wp_user->display_name ); ?>
																		<?php endif; ?>
																	<?php else : ?>
																		- System
																	<?php endif; ?>
																</div>
															<?php endforeach; ?>
														</div>
													</div>
												<?php endif; ?>
												
												<?php if ( ! empty( $debug_info['sample_data'] ) ) : ?>
													<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
														<strong style="font-size: 14px; font-weight: 500; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
															<?php esc_html_e( 'Recent Activity (last 10 entries):', 'beepbeep-ai-alt-text-generator' ); ?>
														</strong>
														<div style="margin-top: 8px; padding-left: 16px; font-family: 'Courier New', 'SF Mono', Monaco, 'Cascadia Code', monospace; font-size: 12px; background: #F8FAFC; padding: 12px; border-radius: 6px; border: 1px solid #E2E8F0;">
															<?php foreach ( $debug_info['sample_data'] as $sample ) : ?>
																<div style="margin-bottom: 6px; color: #64748B; line-height: 1.6;">
																	User: <?php echo esc_html( $sample['user_id'] ); ?> | 
																	Image: <?php echo esc_html( $sample['attachment_id'] ); ?> | 
																	Credits: <?php echo esc_html( $sample['credits_used'] ); ?> | 
																	Source: <?php echo esc_html( $sample['source'] ); ?> | 
																	<?php echo esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $sample['generated_at'] ) ) ); ?>
																</div>
															<?php endforeach; ?>
														</div>
													</div>
												<?php endif; ?>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									
									<?php if ( $site_usage['total_credits'] > 0 ) : ?>
										<p style="color: #64748B; margin-top: 16px; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">
											<?php
											printf(
												esc_html__( 'Note: Site-wide usage shows %d credits used, but no per-user breakdown is available. This may be because: (1) usage occurred before per-user tracking was enabled, (2) all generations were marked as system/anonymous, or (3) the tracking table needs to be populated. Future generations will be tracked per-user.', 'beepbeep-ai-alt-text-generator' ),
												esc_html( number_format_i18n( $site_usage['total_credits'] ) )
											);
											?>
										</p>
									<?php endif; ?>
									
									<?php if ( $table_exists && ( $debug_info['total_rows'] ?? 0 ) === 0 ) : ?>
										<p style="color: #EF4444; margin-top: 16px; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
											<strong><?php esc_html_e( 'No data has been logged yet.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
											<span style="color: #64748B;"><?php esc_html_e( 'Generate alt text for an image while logged in to see usage tracking in action.', 'beepbeep-ai-alt-text-generator' ); ?></span>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
						</tbody>
					</table>

					<?php if ( $usage_by_user['pages'] > 1 ) : ?>
						<div class="tablenav bottom">
							<?php
							$pagination_args = array(
								'total'   => $usage_by_user['pages'],
								'current' => $usage_by_user['page'],
								'base'    => add_query_arg(
									array(
										'paged' => '%#%',
										'tab'   => 'credit-usage',
									),
									admin_url( 'admin.php?page=optti' )
								),
							);
							echo wp_kses_post( paginate_links( $pagination_args ) );
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		
		<?php
		// Add inline styles for enterprise dashboard
		$credit_usage_css = '
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
		';
		wp_add_inline_style( 'bbai-components', $credit_usage_css );

		// Add inline script for credit usage animation
		$credit_usage_script = "(function(\$) {
			'use strict';
			
			// Animate numbers when tab becomes visible
			function animateCreditUsageNumbers() {
				var \$numberElements = \$('.bbai-card-value');
				
				\$numberElements.each(function(index) {
					var \$el = \$(this);
					var originalValue = \$el.text().trim();
					
					// Extract numeric value
					var numericMatch = originalValue.match(/[\\d,]+\.?\\d*/);
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
						
						\$el.text(formatted);
						
						if (progress < 1) {
							requestAnimationFrame(animate);
						} else {
							\$el.text(originalValue);
						}
					}
					
					if (\$el.is(':visible')) {
						setTimeout(function() {
							requestAnimationFrame(animate);
						}, index * 100); // Stagger animations
					}
				});
			}
			
			\$(document).ready(function() {
				// Check if we're on the credit-usage tab
				if (window.location.search.indexOf('tab=credit-usage') !== -1 || 
				    \$('.bbai-credit-usage-tab-content').is(':visible')) {
					setTimeout(animateCreditUsageNumbers, 200);
				}
			});
			
			// Re-run when tab switches to credit-usage
			\$(document).on('click', '.bbai-nav-link[href*=\"credit-usage\"]', function() {
				setTimeout(animateCreditUsageNumbers, 300);
			});
		})(jQuery);";
		wp_add_inline_script( 'jquery', $credit_usage_script );
	}
}

