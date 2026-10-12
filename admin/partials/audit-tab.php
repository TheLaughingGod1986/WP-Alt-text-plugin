<?php
/**
 * Image Audit tab.
 *
 * Answers, in order: how healthy is my image accessibility, what is wrong, and
 * what do I do next. Everything renders server-side; JavaScript only drives the
 * scan itself.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Audit\Audit_Entitlement_Service;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Filters;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Progress_Service;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Telemetry_Service;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Service;
use BeepBeepAI\AltTextGenerator\Audit\Image_Discovery_Service;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view routing, not form processing.
$bbai_audit_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
$bbai_audit_view = 'issues' === $bbai_audit_view ? 'issues' : 'summary';

$bbai_audit_service = new Image_Audit_Service();
$bbai_audit_state   = $bbai_audit_service->describe( Audit_Progress_Service::get_state() );
$bbai_audit_ready   = Image_Audit_Repository::table_exists();
$bbai_audit_summary = $bbai_audit_ready ? $bbai_audit_service->get_summary() : array();

$bbai_audit_last_at = max( 0, (int) ( $bbai_audit_summary['last_completed_at'] ?? 0 ) );
$bbai_audit_scanned = max( 0, (int) ( $bbai_audit_summary['scanned_images'] ?? 0 ) );
$bbai_audit_has_run = $bbai_audit_ready && $bbai_audit_last_at > 0 && $bbai_audit_scanned > 0;

$bbai_audit_summary_url = add_query_arg( array( 'page' => 'bbai-audit' ), admin_url( 'admin.php' ) );
$bbai_audit_issues_url  = add_query_arg(
	array(
		'page' => 'bbai-audit',
		'view' => 'issues',
	),
	admin_url( 'admin.php' )
);
?>
<div class="bbai-audit-page" data-bbai-audit-page>

	<header class="bbai-audit-page__header">
		<h1 class="bbai-audit-page__title">
			<?php
			echo 'issues' === $bbai_audit_view
				? esc_html__( 'Image Audit findings', 'beepbeep-ai-alt-text-generator' )
				: esc_html__( 'Image Audit', 'beepbeep-ai-alt-text-generator' );
			?>
		</h1>
		<p class="bbai-audit-page__intro">
			<?php
			echo 'issues' === $bbai_audit_view
				? esc_html__( 'Images the audit flagged, most serious first. Every action here uses the same generation and review workflow as the rest of the plugin.', 'beepbeep-ai-alt-text-generator' )
				: esc_html__( 'Check every image in your Media Library for missing, weak or duplicated alt text, then fix what matters most first.', 'beepbeep-ai-alt-text-generator' );
			?>
		</p>
		<?php if ( 'issues' === $bbai_audit_view ) : ?>
			<p class="bbai-audit-page__back">
				<a href="<?php echo esc_url( $bbai_audit_summary_url ); ?>">
					<?php esc_html_e( '&larr; Back to audit summary', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( ! $bbai_audit_ready ) : ?>

		<div class="bbai-audit-page__state bbai-audit-page__state--error bbai-dashboard-surface-card bbai-card" role="alert">
			<h2 class="bbai-audit-page__state-title"><?php esc_html_e( 'Audit storage is not ready', 'beepbeep-ai-alt-text-generator' ); ?></h2>
			<p>
				<?php esc_html_e( 'The database table that stores audit results is missing, so the audit cannot run. Deactivating and reactivating OpptiAI Alt Text will create it.', 'beepbeep-ai-alt-text-generator' ); ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
					<?php esc_html_e( 'Go to Plugins', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</p>
		</div>

	<?php elseif ( 'issues' === $bbai_audit_view ) : ?>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only table filtering, not form processing.
		$bbai_audit_filter = Audit_Filters::sanitize( isset( $_GET['audit_filter'] ) ? wp_unslash( $_GET['audit_filter'] ) : '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination, not form processing.
		$bbai_audit_page_num = isset( $_GET['audit_page'] ) ? max( 1, absint( wp_unslash( $_GET['audit_page'] ) ) ) : 1;

		$bbai_audit_per_page = 20;
		$bbai_audit_limit    = Audit_Entitlement_Service::result_limit();

		// A capped plan cannot page past its allowance — enforced here, not just
		// hidden in the markup, so the limit holds however the URL is edited.
		if ( $bbai_audit_limit > 0 ) {
			$bbai_audit_max_page = max( 1, (int) ceil( $bbai_audit_limit / $bbai_audit_per_page ) );
			$bbai_audit_page_num = min( $bbai_audit_page_num, $bbai_audit_max_page );
		}

		$bbai_audit_results = Image_Audit_Repository::query_issues(
			array(
				'filter'   => $bbai_audit_filter,
				'page'     => $bbai_audit_page_num,
				'per_page' => $bbai_audit_per_page,
			)
		);

		if ( $bbai_audit_limit > 0 ) {
			$bbai_audit_seen = ( $bbai_audit_results['page'] - 1 ) * $bbai_audit_per_page;
			$bbai_audit_room = max( 0, $bbai_audit_limit - $bbai_audit_seen );

			if ( count( $bbai_audit_results['rows'] ) > $bbai_audit_room ) {
				$bbai_audit_results['rows'] = array_slice( $bbai_audit_results['rows'], 0, $bbai_audit_room );
			}

			$bbai_audit_results['total_pages'] = min(
				$bbai_audit_results['total_pages'],
				(int) ceil( $bbai_audit_limit / $bbai_audit_per_page )
			);
		}

		// Attachment titles, thumbnails and edit links are resolved only for the
		// rows on screen — never for the whole library.
		$bbai_audit_ids = array();
		foreach ( $bbai_audit_results['rows'] as $bbai_audit_row ) {
			$bbai_audit_ids[] = (int) $bbai_audit_row['attachment_id'];
		}

		$bbai_audit_discovery = new Image_Discovery_Service();

		Audit_Telemetry_Service::emit(
			Audit_Telemetry_Service::RESULTS_VIEWED,
			array(
				'score_bucket'       => Audit_Telemetry_Service::score_bucket( (int) ( $bbai_audit_summary['score'] ?? 0 ) ),
				'issue_count_bucket' => Audit_Telemetry_Service::issue_bucket( (int) $bbai_audit_results['total'] ),
				'filter'             => $bbai_audit_filter,
				'is_limited'         => Audit_Entitlement_Service::is_limited( (int) $bbai_audit_results['total'] ),
			)
		);

		if ( Audit_Filters::ALL !== $bbai_audit_filter ) {
			Audit_Telemetry_Service::emit(
				Audit_Telemetry_Service::FILTER_USED,
				array(
					'filter'              => $bbai_audit_filter,
					'result_count_bucket' => Audit_Telemetry_Service::issue_bucket( (int) $bbai_audit_results['total'] ),
				)
			);
		}

		bbai_ui_render(
			'audit-issues-table',
			array(
				'results'  => $bbai_audit_results,
				'counts'   => Image_Audit_Repository::count_by_filter(),
				'display'  => $bbai_audit_discovery->get_display_data( $bbai_audit_ids ),
				'filter'   => $bbai_audit_filter,
				'base_url' => $bbai_audit_issues_url,
			)
		);
		?>

	<?php else : ?>

		<?php
		bbai_ui_render(
			'audit-score-card',
			array(
				'score'             => (int) ( $bbai_audit_summary['score'] ?? 0 ),
				'scanned'           => $bbai_audit_scanned,
				'total'             => max(
					(int) ( $bbai_audit_summary['total_images'] ?? 0 ),
					(int) ( $bbai_audit_state['total_images'] ?? 0 )
				),
				'passing'           => (int) ( $bbai_audit_summary['images_passing'] ?? 0 ),
				'attention'         => (int) ( $bbai_audit_summary['images_needing_attention'] ?? $bbai_audit_summary['images_with_issues'] ?? 0 ),
				'has_run'           => $bbai_audit_has_run,
				'last_completed_at' => $bbai_audit_last_at,
			)
		);

		bbai_ui_render(
			'audit-progress',
			array(
				'state'   => $bbai_audit_state,
				'has_run' => $bbai_audit_has_run,
			)
		);
		?>

		<?php if ( $bbai_audit_has_run ) : ?>

			<?php
			bbai_ui_render(
				'audit-stat-grid',
				array(
					'stats' => array(
						array(
							'label' => __( 'Images scanned', 'beepbeep-ai-alt-text-generator' ),
							'value' => $bbai_audit_scanned,
							'tone'  => 'neutral',
						),
						array(
							'label' => __( 'No issues detected', 'beepbeep-ai-alt-text-generator' ),
							'value' => (int) ( $bbai_audit_summary['images_passing'] ?? 0 ),
							'tone'  => 'success',
						),
						array(
							'label' => __( 'No alt text', 'beepbeep-ai-alt-text-generator' ),
							'value' => (int) ( $bbai_audit_summary['missing_alt_count'] ?? 0 ),
							'tone'  => 'danger',
						),
						array(
							'label' => __( 'Weak alt text', 'beepbeep-ai-alt-text-generator' ),
							'value' => (int) ( $bbai_audit_summary['weak_alt_count'] ?? 0 ),
							'tone'  => 'warning',
						),
						array(
							'label' => __( 'Duplicated alt text', 'beepbeep-ai-alt-text-generator' ),
							'value' => (int) ( $bbai_audit_summary['duplicate_alt_count'] ?? 0 ),
							'tone'  => 'warning',
						),
						array(
							'label' => __( 'Empty alt text to review', 'beepbeep-ai-alt-text-generator' ),
							'value' => (int) ( $bbai_audit_summary['empty_alt_count'] ?? 0 ),
							'tone'  => 'info',
							'help'  => __( 'Decorative images may correctly have empty alt text.', 'beepbeep-ai-alt-text-generator' ),
						),
					),
				)
			);
			?>

			<div class="bbai-audit-page__next bbai-dashboard-surface-card bbai-card">
				<div>
					<h2 class="bbai-audit-page__state-title"><?php esc_html_e( 'Review the findings', 'beepbeep-ai-alt-text-generator' ); ?></h2>
					<p>
						<?php esc_html_e( 'Work through the images the audit flagged, filtered by the kind of issue.', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( $bbai_audit_issues_url ); ?>">
					<?php esc_html_e( 'View issues', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</div>

			<?php if ( 0 === (int) ( $bbai_audit_summary['images_needing_attention'] ?? $bbai_audit_summary['images_with_issues'] ?? 0 ) ) : ?>
				<div class="bbai-audit-page__state bbai-audit-page__state--empty bbai-dashboard-surface-card bbai-card">
					<h2 class="bbai-audit-page__state-title"><?php esc_html_e( 'No issues detected by the current audit rules', 'beepbeep-ai-alt-text-generator' ); ?></h2>
					<p>
						<?php esc_html_e( 'Every scanned image passed the checks available in this plugin. New uploads are not audited automatically yet, so run the audit again after adding images.', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
				</div>
			<?php endif; ?>

		<?php else : ?>

			<div class="bbai-audit-page__state bbai-audit-page__state--first-run bbai-dashboard-surface-card bbai-card">
				<h2 class="bbai-audit-page__state-title"><?php esc_html_e( 'What the audit checks', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<ul class="bbai-audit-page__checklist">
					<li><?php esc_html_e( 'Images with no alt text stored at all.', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><?php esc_html_e( 'Alt text that repeats the file name, or names the medium rather than the subject.', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><?php esc_html_e( 'The same alt text reused across several images.', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><?php esc_html_e( 'Alt text that is very short, very long, or reads as a keyword list.', 'beepbeep-ai-alt-text-generator' ); ?></li>
					<li><?php esc_html_e( 'Empty alt text that needs a person to decide whether the image is decorative.', 'beepbeep-ai-alt-text-generator' ); ?></li>
				</ul>
				<p class="bbai-audit-page__note">
					<?php esc_html_e( 'The audit only reads your Media Library — it does not change any alt text. Scanning works through images in batches, so you can leave it running and cancel at any point.', 'beepbeep-ai-alt-text-generator' ); ?>
				</p>
			</div>

		<?php endif; ?>

	<?php endif; ?>
</div>
