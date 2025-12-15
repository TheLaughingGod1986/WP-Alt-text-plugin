<?php
/**
 * Analytics Page
 *
 * @package Optti\Admin\Pages
 */

namespace Optti\Admin\Pages;

use Optti\Admin\Page_Renderer;
use Optti\Framework\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check permissions.
if ( ! current_user_can( 'manage_options' ) ) {
	$message = is_textdomain_loaded( 'beepbeep-ai-alt-text-generator' )
		? esc_html__( 'You do not have sufficient permissions to access this page.', 'beepbeep-ai-alt-text-generator' )
		: 'You do not have sufficient permissions to access this page.';
	wp_die( $message );
}

// Redirect to BeepBeep AI dashboard instead
wp_safe_redirect( admin_url( 'admin.php?page=optti' ) );
exit;
