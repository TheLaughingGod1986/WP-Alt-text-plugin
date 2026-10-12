<?php
/**
 * Dev-only: with usage stats OFF, the sign-up request to the backend still
 * carries signup_source plus the Website Auditor finding / auditor_source,
 * and nothing goes to PostHog. Run: php tests/telemetry/signup-body-run.php
 */
require __DIR__ . '/stubs.php';
require BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
require BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-auditor-attribution.php';
require BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';

use BeepBeepAI\AltTextGenerator\API_Client_V2;
use BeepBeepAI\AltTextGenerator\BBAI_Telemetry as T;

function register_body( array $signup ): array {
	$GLOBALS['http'] = array();
	$client = ( new ReflectionClass( API_Client_V2::class ) )->newInstanceWithoutConstructor();
	$prop   = new ReflectionProperty( API_Client_V2::class, 'api_url' );
	$prop->setAccessible( true );
	$prop->setValue( $client, 'https://backend.test' );
	$client->register( 'person@qa.oppti.dev', 'pw-123456', $signup );
	$reg = array_values( array_filter( $GLOBALS['http'], function ( $r ) { return false !== strpos( $r['url'], '/auth/register' ); } ) );
	check( 1 === count( $reg ), 'one register request' );
	return $reg ? (array) json_decode( $reg[0]['body'], true ) : array();
}

foreach ( array( 'consent never saved' => array(), 'legacy yes (4.6.140)' => array( T::CONSENT_OPTION => 'yes' ), 'opted out' => array( T::OPTIN_OPTION => 'no' ), 'opted in' => array( T::OPTIN_OPTION => 'yes' ) ) as $label => $opts ) {
	$GLOBALS['o']  = $opts + array( 'beepbeepai_site_id' => 'test_site_install_id_0123456789ab' );
	$GLOBALS['tr'] = array();
	// User arrived from the Website Auditor (remembered deep-link).
	$_GET = $_REQUEST = array( 'page' => 'bbai', 'source' => 'auditor', 'finding' => 'missing_alt' );
	\BeepBeepAI\AltTextGenerator\remember_auditor_attribution();
	$_GET = $_REQUEST = array();

	$body = register_body( array( 'signup_source' => 'wporg' ) );
	check( 'wporg' === ( $body['signup_source'] ?? '' ), "$label: signup_source in register body" );
	check( 'auditor' === ( $body['auditor_source'] ?? '' ), "$label: auditor_source in register body" );
	check( 'missing_alt' === ( $body['finding'] ?? '' ), "$label: finding in register body" );
	if ( 'opted in' !== $label ) {
		check( array() === posthog_requests(), "$label: nothing sent to PostHog" );
	}
}

// No Auditor deep-link: no auditor fields, signup_source still sent.
$GLOBALS['o'] = array( 'beepbeepai_site_id' => 'test_site_install_id_0123456789ab' ); $GLOBALS['tr'] = array();
$body = register_body( array( 'signup_source' => 'friend' ) );
check( 'friend' === ( $body['signup_source'] ?? '' ), 'plain sign-up keeps signup_source' );
check( ! isset( $body['auditor_source'] ) && ! isset( $body['finding'] ), 'plain sign-up has no auditor fields' );

if ( $GLOBALS['fail'] ) { exit( 1 ); }
echo "OK: sign-up body carries signup_source + Auditor attribution regardless of consent\n";
