<?php
/**
 * is_test is computed locally from the environment type, the home host and the
 * admin / connected account email. Only the boolean is sent.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetryIsTestFlagTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			if ( ! function_exists( 'home_url' ) ) {
				function home_url( $path = '' ) {
					return ( $GLOBALS['bbai_t_home'] ?? 'https://www.real-bakery.co.uk' ) . $path;
				}
			}
			if ( ! function_exists( 'wp_get_environment_type' ) ) {
				function wp_get_environment_type() {
					return $GLOBALS['bbai_t_env'] ?? 'production';
				}
			}
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			$GLOBALS['bbai_test_remote_posts'] = array();
		}

		private function flag( string $home, string $env = 'production', string $admin_email = 'owner@real-bakery.co.uk', $account_email = null ): bool {
			$GLOBALS['bbai_t_home'] = $home;
			$GLOBALS['bbai_t_env']  = $env;
			$GLOBALS['bbai_test_options']['admin_email'] = $admin_email;
			if ( null === $account_email ) {
				unset( $GLOBALS['bbai_test_options']['beepbeepai_user_data'] );
			} else {
				$GLOBALS['bbai_test_options']['beepbeepai_user_data'] = array( 'email' => $account_email, 'plan' => 'free' );
			}
			return BBAI_Telemetry::is_test_site();
		}

		public static function is_test_cases(): array {
			return array(
				'env local'               => array( true, 'https://www.real-bakery.co.uk', 'local' ),
				'env development'         => array( true, 'https://www.real-bakery.co.uk', 'development' ),
				'env staging'             => array( true, 'https://www.real-bakery.co.uk', 'staging' ),
				'localhost'               => array( true, 'http://localhost:8888' ),
				'loopback ip'             => array( true, 'http://127.0.0.1:8080' ),
				'ipv6 loopback'           => array( true, 'http://[::1]:8080' ),
				'.local'                  => array( true, 'https://bakery.local' ),
				'.test'                   => array( true, 'https://bakery.test' ),
				'.localhost'              => array( true, 'http://bakery.localhost' ),
				'staging. prefix'         => array( true, 'https://staging.real-bakery.co.uk' ),
				'stg. prefix'             => array( true, 'https://stg.real-bakery.co.uk' ),
				'wp engine staging'       => array( true, 'https://bakerystg.wpengine.com' ),
				'godaddy staging'         => array( true, 'https://staging-abc.godaddysites.com' ),
				'godaddy temp url'        => array( true, 'https://abc.myftpupload.com' ),
				'instawp'                 => array( true, 'https://brave-cat.instawp.xyz' ),
				'tastewp'                 => array( true, 'https://hungryfish.s1-tastewp.com' ),
				'playground'              => array( true, 'https://playground.wordpress.net' ),
				'qa admin email'          => array( true, 'https://www.real-bakery.co.uk', 'production', 'ops@qa.oppti.dev' ),
				'qa account email'        => array( true, 'https://www.real-bakery.co.uk', 'production', 'owner@real-bakery.co.uk', 'Tester+1@QA.oppti.dev' ),
				'production site'         => array( false, 'https://www.real-bakery.co.uk' ),
				'production wpengine'     => array( false, 'https://realbakery.wpengine.com' ),
				'production godaddy'      => array( false, 'https://realbakery.godaddysites.com' ),
				'word staging in name'    => array( false, 'https://stagingcoach.com' ),
				'similar email domain'    => array( false, 'https://www.real-bakery.co.uk', 'production', 'owner@notqa.oppti.dev.example.com' ),
				'other oppti email'       => array( false, 'https://www.real-bakery.co.uk', 'production', 'ben@oppti.dev', 'user@gmail.com' ),
			);
		}

		#[DataProvider( 'is_test_cases' )]
		public function test_is_test_flag( bool $expected, string $home, string $env = 'production', string $admin_email = 'owner@real-bakery.co.uk', $account_email = null ): void {
			$this->assertSame( $expected, $this->flag( $home, $env, $admin_email, $account_email ) );
		}

		public function test_only_the_boolean_reaches_posthog(): void {
			$this->flag( 'https://staging.real-bakery.co.uk', 'production', 'ops@qa.oppti.dev', 'tester@qa.oppti.dev' );
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::capture_posthog_event( 'plugin_opened', 'test_site_install_id_0123456789ab', array( '$set' => array( 'plan' => 'free' ) ) );
			$found = false;
			foreach ( $GLOBALS['bbai_test_remote_posts'] as $call ) {
				$body = (string) $call['args']['body'];
				$this->assertStringNotContainsString( 'real-bakery', $body );
				$this->assertStringNotContainsString( 'qa.oppti.dev', $body );
				$event = json_decode( $body, true );
				if ( 'plugin_opened' === $event['event'] ) {
					$found = true;
					$this->assertTrue( $event['properties']['is_test'] );
					$this->assertTrue( $event['properties']['$set']['is_test'] );
				}
			}
			$this->assertTrue( $found );

			$GLOBALS['bbai_test_remote_posts'] = array();
			$this->flag( 'https://www.real-bakery.co.uk' );
			BBAI_Telemetry::capture_posthog_event( 'plugin_opened', 'test_site_install_id_0123456789ab', array() );
			$event = json_decode( (string) end( $GLOBALS['bbai_test_remote_posts'] )['args']['body'], true );
			$this->assertFalse( $event['properties']['is_test'] );
		}
	}
}
