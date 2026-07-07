<?php
/**
 * Regression tests for canonical telemetry property enrichment.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
use function BeepBeepAI\AltTextGenerator\bbai_telemetry_emit;
use PHPUnit\Framework\TestCase;

final class TelemetryCanonicalTest extends TestCase {

	protected function setUp(): void {
		remove_all_actions( 'bbai_telemetry_event' );
	}

	/**
	 * @return list<string>
	 */
	private function required_canonical_keys(): array {
		return array(
			'site_install_id',
			'site_url',
			'host',
			'plugin_version',
			'plugin_slug',
			'wordpress_version',
			'php_version',
			'plan',
			'environment',
			'telemetry_version',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function capture_emit_properties( string $event_name, array $properties = array() ): array {
		$envelope = array();
		add_action(
			'bbai_telemetry_event',
			static function ( array $payload ) use ( &$envelope ) {
				$envelope = $payload;
			}
		);

		bbai_telemetry_emit( $event_name, $properties );

		$this->assertNotEmpty( $envelope, "Event {$event_name} was not emitted" );

		return is_array( $envelope['properties'] ?? null ) ? $envelope['properties'] : array();
	}

	public function test_feature_used_requires_feature_name(): void {
		$properties = $this->capture_emit_properties(
			'feature_used',
			array(
				'feature_name' => 'dashboard',
			)
		);

		$this->assertSame( 'dashboard', $properties['feature_name'] );
	}

	public function test_feature_used_rejects_invalid_feature_name_via_emit_guard(): void {
		$captured = array();
		add_action(
			'bbai_telemetry_event',
			static function ( array $envelope ) use ( &$captured ) {
				$captured[] = $envelope;
			}
		);

		bbai_telemetry_emit( 'feature_used', array( 'feature_name' => 'not_a_real_feature' ) );

		$this->assertCount( 0, $captured );
	}

	public function test_plugin_activated_contains_site_install_id(): void {
		$properties = $this->capture_emit_properties( 'plugin_activated' );

		$this->assertNotEmpty( $properties['site_install_id'] );
		$this->assertSame( $properties['site_install_id'], $properties['site_id'] );
	}

	public function test_plugin_updated_contains_plugin_version(): void {
		$properties = $this->capture_emit_properties(
			'plugin_updated',
			array(
				'previous_version' => '4.6.108',
			)
		);

		$this->assertSame( BEEPBEEP_AI_VERSION, $properties['plugin_version'] );
	}

	public function test_signup_succeeded_contains_canonical_properties(): void {
		$properties = $this->capture_emit_properties(
			'signup_succeeded',
			array(
				'source' => 'ajax_register',
			)
		);

		foreach ( $this->required_canonical_keys() as $key ) {
			$this->assertArrayHasKey( $key, $properties, "Missing canonical property: {$key}" );
			$this->assertNotSame( '', $properties[ $key ], "Empty canonical property: {$key}" );
		}

		$this->assertSame( 'signup', $properties['feature_name'] ?? '' );
	}

	public function test_generation_completed_contains_canonical_payload(): void {
		$properties = $this->capture_emit_properties(
			'generation_completed',
			array(
				'generation_mode' => 'bulk',
				'generation_run_id' => 'run_test_1',
			)
		);

		foreach ( $this->required_canonical_keys() as $key ) {
			$this->assertArrayHasKey( $key, $properties, "Missing canonical property: {$key}" );
		}

		$this->assertSame( 'bulk', $properties['generation_type'] ?? '' );
		$this->assertSame( 'bulk_generation', $properties['feature_name'] ?? '' );
	}

	public function test_host_normalization_strips_scheme_and_path(): void {
		$enriched = BBAI_Telemetry::enrich_properties(
			array(
				'host' => 'https://Example.com/wp-admin/',
			)
		);

		$this->assertSame( 'example.com', $enriched['host'] );
		$this->assertSame( 'example.com', $enriched['site_host'] );
	}

	public function test_login_succeeded_infers_feature_name_and_license_state(): void {
		$GLOBALS['bbai_test_options']['beepbeepai_jwt_token'] = 'token';
		$properties = $this->capture_emit_properties(
			'login_succeeded',
			array(
				'source' => 'modal',
			)
		);
		unset( $GLOBALS['bbai_test_options']['beepbeepai_jwt_token'] );

		$this->assertSame( 'login', $properties['feature_name'] ?? '' );
		$this->assertSame( 'connected', $properties['license_state'] ?? '' );
	}

	public function test_plugin_slug_matches_directory_name(): void {
		$this->assertSame( 'beepbeep-ai-alt-text-generator', BBAI_Telemetry::get_plugin_slug() );
	}
}
