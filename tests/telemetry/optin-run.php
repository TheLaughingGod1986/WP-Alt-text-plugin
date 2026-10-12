<?php
/**
 * Dev-only: PostHog usage stats are opt-in only, and the payload is anonymous.
 * Run: php tests/telemetry/optin-run.php (no network; requests are recorded).
 */
require __DIR__ . '/stubs.php';
require BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';

use BeepBeepAI\AltTextGenerator\BBAI_Telemetry as T;

function reset_state( array $opts ) { $GLOBALS['o'] = $opts; $GLOBALS['http'] = array(); $GLOBALS['caps'] = true; $GLOBALS['nonce'] = true; }
function admin_init() { T::maybe_clear_legacy_consent(); T::flush_queued_lifecycle_events(); T::maybe_record_plugin_update(); }
function exercise_all() {
	T::capture_posthog_event( 'alt_generated', 'acct_123', array( 'plan' => 'free', 'generation_mode' => 'single' ) );
	T::capture_posthog_event( 'auditor_deeplink_opened', 'site_x', array( 'source' => 'auditor', 'finding' => 'missing_alt' ) );
	T::queue_lifecycle_event( 'plugin_deactivated' );
	T::record_activation_lifecycle();
	T::flush_queued_lifecycle_events();
}
$site_id = 'test_site_install_id_0123456789ab';

// 1. Upgrade from 4.6.140: legacy 'yes' auto-saved on activation, old queued event.
reset_state( array(
	'beepbeepai_site_id'          => $site_id,
	T::CONSENT_OPTION             => 'yes',
	T::CURRENT_VERSION_OPTION     => '4.6.140',
	T::LEGACY_VERSION_OPTION      => '4.6.140',
	T::INSTALLED_MARKER_OPTION    => '2026-01-01T00:00:00+00:00',
	T::ACTIVATION_MARKER_OPTION   => '2026-01-01T00:00:00+00:00',
	T::LIFECYCLE_QUEUE_OPTION     => array( array( 'event' => 'plugin_activated', 'distinct_id' => $site_id, 'properties' => array( 'site_url' => 'https://customer-site.example/' ) ) ),
) );
check( false === T::has_telemetry_consent(), 'legacy yes is not consent (before upgrade step)' );
T::capture_posthog_event( 'alt_generated', 'acct_123', array() ); // e.g. cron before any admin_init
admin_init();
exercise_all();
admin_init();
check( array() === posthog_requests(), 'upgrade from 4.6.140 with legacy yes: zero PostHog requests' );
check( ! array_key_exists( T::CONSENT_OPTION, $GLOBALS['o'] ), 'legacy consent option deleted on upgrade' );
check( ! array_key_exists( T::LIFECYCLE_QUEUE_OPTION, $GLOBALS['o'] ), 'old queued events dropped, nothing new queued' );
check( ! array_key_exists( T::OPTIN_OPTION, $GLOBALS['o'] ), 'upgrade does not write the opt-in option' );
check( '4.6.141' === $GLOBALS['o'][ T::CURRENT_VERSION_OPTION ], 'version bookkeeping still runs' );
// Legacy value re-appearing later (e.g. restored backup) is still ignored.
$GLOBALS['o'][ T::CONSENT_OPTION ] = 'yes';
exercise_all();
check( array() === posthog_requests(), 'legacy yes never counts' );

// 2. Fresh install.
reset_state( array( 'beepbeepai_site_id' => $site_id ) );
T::record_activation_lifecycle();
admin_init();
exercise_all();
check( array() === posthog_requests(), 'fresh install: zero PostHog requests' );
check( ! array_key_exists( T::LIFECYCLE_QUEUE_OPTION, $GLOBALS['o'] ), 'fresh install queues nothing' );
check( ! array_key_exists( T::CONSENT_OPTION, $GLOBALS['o'] ), 'activation no longer saves consent' );
check( ! array_key_exists( T::ANON_ID_OPTION, $GLOBALS['o'] ), 'no anon id created without consent' );

// 3. Toggle: junk / missing / explicit no all store 'no' and send nothing.
foreach ( array( '', '0', 'no', 'true', 'on', 'YES', ' 1', null, array( '1' ), 1 ) as $raw ) {
	reset_state( array( 'beepbeepai_site_id' => $site_id ) );
	check( 'no' === T::save_optin( $raw ), 'junk toggle value stores no: ' . var_export( $raw, true ) );
	exercise_all();
	check( array() === posthog_requests(), 'junk toggle sends nothing: ' . var_export( $raw, true ) );
}
// Toggle handler: capability and nonce are enforced.
reset_state( array() );
$_POST = array( 'bbai_usage_stats' => '1' );
$GLOBALS['caps'] = false;
try { T::handle_optin_save(); } catch ( Bbai_Test_Exit $e ) {}
check( ! isset( $GLOBALS['o'][ T::OPTIN_OPTION ] ), 'non-admin cannot opt in' );
$GLOBALS['caps'] = true; $GLOBALS['nonce'] = false;
try { T::handle_optin_save(); } catch ( Bbai_Test_Exit $e ) {}
check( ! isset( $GLOBALS['o'][ T::OPTIN_OPTION ] ), 'bad nonce cannot opt in' );
$GLOBALS['nonce'] = true;
try { T::handle_optin_save(); } catch ( Bbai_Test_Exit $e ) {}
check( 'yes' === ( $GLOBALS['o'][ T::OPTIN_OPTION ] ?? '' ), 'admin + nonce + 1 opts in' );
$_POST = array();
try { T::handle_optin_save(); } catch ( Bbai_Test_Exit $e ) {}
check( 'no' === ( $GLOBALS['o'][ T::OPTIN_OPTION ] ?? '' ), 'unticked checkbox (field absent) opts out' );

// 4. Toggle on: sends, and the payload is anonymous.
reset_state( array(
	'beepbeepai_site_id'       => $site_id,
	'beepbeepai_jwt_token'     => 'jwt-secret',
	'beepbeepai_license_key'   => 'lic-secret',
	T::CURRENT_VERSION_OPTION  => '4.6.141',
) );
T::save_optin( '1' );
$GLOBALS['_COOKIE']['bbai_session_id'] = 'bbai_sess_12345678';
$evil = array(
	'account_id' => 'acct_999', 'user_id' => 42, 'license_id' => 'lic_1', 'email' => 'person@qa.oppti.dev',
	'email_hash' => hash( 'sha256', 'person@qa.oppti.dev' ), 'site_id' => $site_id, 'site_install_id' => $site_id,
	'site_hash' => hash( 'sha256', $site_id ), 'install_id' => $site_id, 'site_url' => 'https://customer-site.example/',
	'host' => 'customer-site.example', 'site_host' => 'customer-site.example', 'url' => 'https://customer-site.example/?p=5',
	'$current_url' => 'https://customer-site.example/wp-admin/', 'referrer' => 'https://google.com/?q=x', '$referrer' => 'https://google.com/',
	'landing_page' => '/wp-admin/admin.php', 'attachment_id' => 77, 'image_id' => 77, 'post_id' => 5, 'post_title' => 'Secret Post',
	'search' => 'private term', 'error_message' => 'Failed in /var/www/x.php', 'journey_id' => $site_id,
	'wordpress_user_id' => 3, 'utm_source' => 'newsletter',
	'plan' => 'free', 'generation_mode' => 'single', 'model' => 'gpt-4o-mini', 'source' => 'https://evil.example/',
	'tab' => 'library', 'finding' => 'missing_alt', 'auditor_source' => 'auditor', '$insert_id' => 'alt_generated:' . md5( 'x' ),
);
T::capture_posthog_event( 'alt_generated', 'acct_999', $evil );
T::queue_lifecycle_event( 'plugin_deactivated' );
$reqs = posthog_requests();
check( 2 === count( $reqs ), 'opted in: capture + lifecycle sent (' . count( $reqs ) . ')' );
check( ! isset( $GLOBALS['o'][ T::LIFECYCLE_QUEUE_OPTION ] ), 'queue flushed after send' );
$blob = json_encode( $reqs );
foreach ( array( 'customer-site', 'qa.oppti.dev', hash( 'sha256', 'person@qa.oppti.dev' ), $site_id, hash( 'sha256', $site_id ), 'acct_999', 'lic_1', 'jwt-secret', 'lic-secret', 'google.com', 'Secret Post', 'private term', '/var/www', 'evil.example', 'wp-admin', 'newsletter', md5( 'x' ) ) as $needle ) {
	check( false === stripos( $blob, $needle ), "payload has no '$needle'" );
}
$p0 = json_decode( $reqs[0]['body'], true );
$p1 = json_decode( $reqs[1]['body'], true );
check( 1 === preg_match( '/^anon_[0-9a-f-]{36}$/', $p0['distinct_id'] ), 'distinct_id is anon_<uuid>' );
check( $p0['distinct_id'] === $p1['distinct_id'], 'distinct_id stable per install' );
check( 'anon_' . $GLOBALS['o'][ T::ANON_ID_OPTION ] === $p0['distinct_id'], 'anon id stored in its own option' );
foreach ( array( 'account_id', 'user_id', 'license_id', 'email', 'email_hash', 'site_id', 'site_install_id', 'site_hash', 'install_id', 'site_url', 'host', 'site_host', 'url', '$current_url', 'referrer', '$referrer', 'landing_page', 'attachment_id', 'image_id', 'post_id', 'post_title', 'search', 'error_message', 'journey_id', 'wordpress_user_id', 'utm_source', 'source' ) as $k ) {
	check( ! array_key_exists( $k, $p0['properties'] ), "payload has no $k key" );
}
foreach ( array( 'plan' => 'free', 'generation_mode' => 'single', 'model' => 'gpt-4o-mini', 'tab' => 'library', 'finding' => 'missing_alt', 'plugin_version' => '4.6.141', 'wp_version' => '6.8.1' ) as $k => $v ) {
	check( ( $p0['properties'][ $k ] ?? null ) === $v, "payload keeps $k" );
}
check( false === $p0['properties']['$process_person_profile'], 'no person profile' );
check( 0 === strpos( $p0['properties']['$insert_id'], 'bbai_' ), 'insert id re-hashed' );
foreach ( $p0['properties'] as $k => $v ) {
	check( is_bool( $v ) || is_int( $v ) || ( is_string( $v ) && preg_match( '/^[A-Za-z0-9_.\-]{1,64}$/', $v ) ), "token-shaped value for $k" );
}

// 5. Browser properties are restricted to the allowlist.
$browser = T::filter_posthog_properties( array( 'tab' => 'library?x=1', 'plan' => 'a b', 'trigger' => 'user@qa.oppti.dev', 'error_code' => 'rate_limited', 'page_count' => 3, 'feature_name' => str_repeat( 'a', 65 ), 'session_id' => 'bbai_abc_def', 'site_hash' => 'abc', 'unknown_key' => 'ok' ) );
check( array( 'session_id' => 'bbai_abc_def', 'trigger' => null ) !== $browser, 'sanity' );
check( array( 'session_id', 'error_code', 'page_count' ) === array_keys( $browser ), 'browser allowlist keeps only safe keys/values: ' . json_encode( $browser ) );

// 6. Opt out again: nothing more is sent.
T::save_optin( '0' );
$GLOBALS['http'] = array();
exercise_all();
check( array() === posthog_requests(), 'opt out: zero PostHog requests' );

if ( $GLOBALS['fail'] ) { exit( 1 ); }
echo "OK: usage stats are opt-in only; payload anonymous\n";
