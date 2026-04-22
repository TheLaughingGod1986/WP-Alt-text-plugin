<?php
/**
 * Logged-in dashboard surface dispatch.
 *
 * Reads the resolver state and includes the correct surface partial.
 * For table states (MISSING_ALT, NEEDS_REVIEW) it fetches rows server-side
 * so the first paint is fully populated — no JS required for visibility.
 * JS controller still wires up actions and polling after load.
 *
 * Required in parent scope:
 *   $bbai_li_state  array   Full DashboardState from Logged_In_Dashboard_Resolver.
 *   $this           object  BBAI_Core instance (provides get_missing_attachment_ids etc.)
 *
 * @package BeepBeep_AI
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $bbai_li_state ) || ! is_array( $bbai_li_state ) ) {
	return;
}

$bbai_surf_state = $bbai_li_state['state'] ?? '';

// ── Helper: build row data from attachment IDs ────────────────────────────────

/**
 * @param int[]  $ids
 * @param string $scope  'missing' | 'needs_review'
 * @return array<array{id:int,filename:string,thumb_url:string,scope:string}>
 */
$bbai_surf_build_rows = static function ( array $ids, string $scope ) : array {
	$rows = [];
	foreach ( $ids as $id ) {
		$id = absint( $id );
		if ( $id <= 0 ) { continue; }

		$filename = '';
		$attached = get_attached_file( $id );
		if ( is_string( $attached ) && '' !== $attached ) {
			$filename = wp_basename( $attached );
		}
		if ( '' === $filename ) {
			$filename = (string) get_the_title( $id );
		}

		$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );

		$rows[] = [
			'id'        => $id,
			'filename'  => sanitize_text_field( $filename ),
			'thumb_url' => ( $thumb && is_string( $thumb ) ) ? esc_url_raw( $thumb ) : '',
			'scope'     => $scope,
		];
	}
	return $rows;
};

// ── Dispatch ─────────────────────────────────────────────────────────────────

switch ( $bbai_surf_state ) {

	case 'MISSING_ALT':
		$bbai_surf_ids = ( isset( $this ) && is_object( $this ) && method_exists( $this, 'get_missing_attachment_ids' ) )
			? $this->get_missing_attachment_ids( 20, 0 )
			: [];

		$bbai_it_component   = 'MissingAltTable';
		$bbai_it_scope       = 'missing';
		$bbai_it_heading     = __( 'Images missing ALT text', 'beepbeep-ai-alt-text-generator' );
		$bbai_it_loading     = __( 'Loading images…', 'beepbeep-ai-alt-text-generator' );
		$bbai_it_alt_col     = __( 'ALT text', 'beepbeep-ai-alt-text-generator' );
		$bbai_it_primary_cta = [
			'label'  => __( 'Generate all missing', 'beepbeep-ai-alt-text-generator' ),
			'href'   => '#',
			'action' => 'generate-missing',
		];
		$bbai_it_library_url = add_query_arg(
			[ 'page' => 'bbai-library', 'status' => 'missing', 'filter' => 'missing' ],
			admin_url( 'admin.php' )
		);
		$bbai_it_server_render = true;
		$bbai_it_rows          = $bbai_surf_build_rows( (array) $bbai_surf_ids, 'missing' );
		$bbai_it_empty_message = __( 'No images missing ALT text — great job!', 'beepbeep-ai-alt-text-generator' );

		include __DIR__ . '/components/image-table.php';
		break;

	case 'NEEDS_REVIEW':
		$bbai_surf_ids = ( isset( $this ) && is_object( $this ) && method_exists( $this, 'get_needs_review_attachment_ids' ) )
			? $this->get_needs_review_attachment_ids( 20, 0 )
			: [];

		$bbai_it_component   = 'ReviewQueue';
		$bbai_it_scope       = 'needs_review';
		$bbai_it_heading     = __( 'Review queue', 'beepbeep-ai-alt-text-generator' );
		$bbai_it_loading     = __( 'Loading review queue…', 'beepbeep-ai-alt-text-generator' );
		$bbai_it_alt_col     = __( 'Generated ALT text', 'beepbeep-ai-alt-text-generator' );
		$bbai_it_primary_cta = [
			'label'  => __( 'Approve all', 'beepbeep-ai-alt-text-generator' ),
			'href'   => '#',
			'action' => 'approve-all',
		];
		$bbai_it_library_url = add_query_arg(
			[ 'page' => 'bbai-library', 'status' => 'needs_review', 'filter' => 'weak' ],
			admin_url( 'admin.php' )
		);
		$bbai_it_server_render = true;
		$bbai_it_rows          = $bbai_surf_build_rows( (array) $bbai_surf_ids, 'needs_review' );
		$bbai_it_empty_message = __( 'No images in the review queue.', 'beepbeep-ai-alt-text-generator' );

		include __DIR__ . '/components/image-table.php';
		break;

	case 'NO_IMAGES':
		include __DIR__ . '/dashboard-li-surface-quick-start.php';
		break;

	case 'PROCESSING':
		include __DIR__ . '/dashboard-li-surface-activity-log.php';
		break;

	case 'QUEUED':
		include __DIR__ . '/dashboard-li-surface-activity-log.php';
		break;

	case 'ERROR':
		include __DIR__ . '/dashboard-li-surface-error-detail.php';
		break;

	case 'QUOTA_EXHAUSTED':
		include __DIR__ . '/dashboard-li-surface-credit-topup.php';
		break;

	case 'ALL_CLEAR':
		include __DIR__ . '/dashboard-li-surface-recent-activity.php';
		break;

	default:
		// Unknown state — render nothing rather than a broken surface.
		break;
}
