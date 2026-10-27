<?php
namespace BeepBeepAI\AltTextGenerator {
    function get_site_identifier() { return 'random_install_fixture_12345'; }
}
namespace {
    define('ABSPATH', __DIR__);
    $GLOBALS['options'] = [];
    $GLOBALS['env'] = 'production';
    function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
    function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
    function apply_filters($tag, $value) { return $value; }
    function wp_get_environment_type() { return $GLOBALS['env']; }
    function check($ok, $label) { if (!$ok) { throw new \RuntimeException($label); } echo "ok - $label\n"; }
    require dirname(__DIR__, 2) . '/includes/class-bbai-telemetry.php';
    use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
    check(['X-Telemetry-Consent' => 'no'] === BBAI_Telemetry::request_telemetry_headers(), 'unset consent sends no telemetry identity');
    $GLOBALS['options'][BBAI_Telemetry::CONSENT_OPTION] = 'yes';
    check(['X-Telemetry-Consent' => 'no'] === BBAI_Telemetry::request_telemetry_headers(), 'legacy implied consent never grants capture');
    $GLOBALS['options'][BBAI_Telemetry::CONSENT_SOURCE_OPTION] = 'settings';
    $h = BBAI_Telemetry::request_telemetry_headers();
    check($h['X-Telemetry-Install-ID'] === 'random_install_fixture_12345' && $h['X-Telemetry-Consent'] === 'yes', 'explicit consent uses stable installation attribution');
    check($h['X-Environment'] === 'production' && $h['X-Telemetry-Test'] === 'no', 'production context');
    $GLOBALS['env'] = 'staging';
    check(BBAI_Telemetry::request_telemetry_headers()['X-Telemetry-Test'] === 'yes', 'QA explicitly classified');
    $GLOBALS['options'][BBAI_Telemetry::CONSENT_OPTION] = 'no';
    check(['X-Telemetry-Consent' => 'no'] === BBAI_Telemetry::request_telemetry_headers(), 'revocation immediately suppresses identity');
}
