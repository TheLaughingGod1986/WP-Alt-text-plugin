<?php
if (!defined('ABSPATH')) { exit; }

$bbai_body = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-body.php';
if (file_exists($bbai_body)) {
    include $bbai_body;
}
?>
