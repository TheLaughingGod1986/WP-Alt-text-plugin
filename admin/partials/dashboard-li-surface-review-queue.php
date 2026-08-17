<?php
/**
 * Logged-in surface: ReviewQueue (NEEDS_REVIEW state).
 *
 * Renders a table shell via the shared image-table component.
 * Rows populated by JS from /list?scope=needs_review.
 *
 * Required in parent scope:
 *   $bbai_li_state  array  The full DashboardState object.
 *
 * @package BeepBeep_AI
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_it_component   = 'ReviewQueue';
$bbai_it_scope       = 'needs_review';
$bbai_review_ready_count = isset( $bbai_li_state['donut']['segments']['weak'] )
	? max( 0, (int) $bbai_li_state['donut']['segments']['weak'] )
	: 0;
$bbai_it_heading     = $bbai_review_ready_count > 0
	? sprintf(
		/* translators: %s: number of images ready for review */
		_n( '%s image ready for review', '%s images ready for review', $bbai_review_ready_count, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_review_ready_count )
	)
	: __( 'Images ready for review', 'beepbeep-ai-alt-text-generator' );
$bbai_it_subheading  = __( 'Approve AI suggestions when they look right, or open an item to edit before publishing.', 'beepbeep-ai-alt-text-generator' );
$bbai_it_loading     = __( 'Loading review queue…', 'beepbeep-ai-alt-text-generator' );
$bbai_it_alt_col     = __( 'Generated ALT text', 'beepbeep-ai-alt-text-generator' );
$bbai_it_primary_cta = [
	'label'  => __( 'Approve AI suggestions', 'beepbeep-ai-alt-text-generator' ),
	'href'   => '#',
	'action' => 'approve-all',
];
$bbai_it_library_url = add_query_arg(
	[ 'page' => 'bbai-library', 'status' => 'needs_review', 'filter' => 'weak' ],
	admin_url( 'admin.php' )
);

include __DIR__ . '/components/image-table.php';
