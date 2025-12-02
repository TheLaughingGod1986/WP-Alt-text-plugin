<?php
/**
 * Settings Page
 *
 * @package Optti\Admin\Pages
 */

namespace Optti\Admin\Pages;

use Optti\Admin\Page_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check permissions.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'beepbeep-ai-alt-text-generator' ) );
}

// Redirect to BeepBeep AI settings instead
wp_safe_redirect( admin_url( 'upload.php?page=bbai&tab=settings' ) );
exit;
