<?php
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['options'][$k] ?? $d; }
function add_option( $k, $v, $d = '', $a = false ) {
    if ( array_key_exists( $k, $GLOBALS['options'] ) ) { return false; }
    $GLOBALS['options'][$k] = $v; return true;
}
function apply_filters( $tag, $v ) { return $v; }
function check( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } echo "ok - $label\n"; }
require dirname( __DIR__, 2 ) . '/includes/class-bbai-telemetry.php';
use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
check( [] === BBAI_Telemetry::first_value_properties() && [] === $GLOBALS['options'], 'unset consent stores no first-value state' );
$GLOBALS['options'][BBAI_Telemetry::CONSENT_OPTION] = 'no';
check( [] === BBAI_Telemetry::first_value_properties(), 'opt-out does not claim first value' );
$GLOBALS['options'][BBAI_Telemetry::CONSENT_OPTION] = 'yes';
$GLOBALS['options'][BBAI_Telemetry::ACTIVATION_MARKER_OPTION] = gmdate( 'c', time() - 60 );
$first = BBAI_Telemetry::first_value_properties();
$repeat = BBAI_Telemetry::first_value_properties();
check( true === $first['is_first_generation'], 'first generation claims installation marker' );
check( false === $repeat['is_first_generation'], 'later user or cron generation does not reclaim marker' );
check( $first['time_to_first_value_seconds'] >= 60, 'ISO activation marker produces elapsed seconds' );
check( ! isset( $repeat['time_to_first_value_seconds'] ), 'only first value carries timing' );
