<?php
if (!defined('ABSPATH')) { exit; }

$bbai_hero = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-hero.php';
$bbai_body = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-body.php';
if (file_exists($bbai_hero)) {
    include $bbai_hero;
}
if (file_exists($bbai_body)) {
    include $bbai_body;
}
?>
