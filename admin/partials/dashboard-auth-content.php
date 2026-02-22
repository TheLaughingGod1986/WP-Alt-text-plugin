<?php
if (!defined('ABSPATH')) { exit; }

$hero = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-hero.php';
$body = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-body.php';
if (file_exists($hero)) {
    include $hero;
}
if (file_exists($body)) {
    include $body;
}
?>
