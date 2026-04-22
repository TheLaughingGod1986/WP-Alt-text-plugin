<?php
/**
 * Logged-in surface: MissingAltTable (MISSING_ALT state).
 *
 * Renders a table shell via the shared image-table component.
 * Rows are populated by the JS controller via REST GET /bbai/v1/list?scope=missing.
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

$bbai_it_component   = 'MissingAltTable';
$bbai_it_scope       = 'missing';
$bbai_it_heading     = __( 'Ready to generate', 'beepbeep-ai-alt-text-generator' );
$bbai_it_subheading  = __( 'These images have no ALT text. Generate now or open the library to manage individually.', 'beepbeep-ai-alt-text-generator' );
$bbai_it_loading     = __( 'Loading images…', 'beepbeep-ai-alt-text-generator' );
$bbai_it_alt_col     = __( 'ALT text', 'beepbeep-ai-alt-text-generator' );
$bbai_it_primary_cta = [
	'label'  => __( 'Generate all', 'beepbeep-ai-alt-text-generator' ),
	'href'   => '#',
	'action' => 'generate-missing',
];
$bbai_it_library_url = add_query_arg(
	[ 'page' => 'bbai-library', 'status' => 'missing', 'filter' => 'missing' ],
	admin_url( 'admin.php' )
);

include __DIR__ . '/components/image-table.php';
