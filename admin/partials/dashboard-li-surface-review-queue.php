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
$bbai_it_heading     = __( 'Awaiting your approval', 'beepbeep-ai-alt-text-generator' );
$bbai_it_subheading  = __( 'ALT text has been generated. Approve to publish, or edit individual items in the library.', 'beepbeep-ai-alt-text-generator' );
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

include __DIR__ . '/components/image-table.php';
