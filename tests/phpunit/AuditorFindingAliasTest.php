<?php
/**
 * Auditor v1 renamed check ids: old and new ids resolve identically via
 * finding= and auditor_source=; unknown ids are dropped.
 */
declare(strict_types=1);

namespace {
	use PHPUnit\Framework\TestCase;
	use function BeepBeepAI\AltTextGenerator\auditor_attribution_from_request;
	use function BeepBeepAI\AltTextGenerator\normalize_auditor_finding;
	use function BeepBeepAI\AltTextGenerator\with_auditor_args;

	final class AuditorFindingAliasTest extends TestCase {
		protected function setUp(): void {
			if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }
			if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ) ); } }
			if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
			if ( ! function_exists( 'add_action' ) ) { function add_action( ...$a ) { return true; } }
			if ( ! function_exists( 'add_query_arg' ) ) {
				function add_query_arg( array $args, string $url ) {
					$parts = explode( '?', $url, 2 );
					$q     = array();
					if ( isset( $parts[1] ) ) { parse_str( $parts[1], $q ); }
					return $parts[0] . '?' . http_build_query( array_merge( $q, $args ) );
				}
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-auditor-attribution.php';
			$_GET = $_REQUEST = array();
		}

		private function finding( array $req ): ?string {
			$_GET = $_REQUEST = $req;
			$a = auditor_attribution_from_request();
			return null === $a ? null : $a['finding'];
		}

		public static function pairs(): array {
			return array(
				array( 'search_indexing', 'search_engines_discouraged' ),
				array( 'https', 'https_not_enforced' ),
				array( 'sitemap', 'sitemap_missing' ),
				array( 'canonical', 'canonical_missing' ),
				array( 'open_graph', 'og_tags_missing' ),
				array( 'missing_alt', 'missing_alt' ),
			);
		}

		/** @dataProvider pairs */
		public function test_old_and_new_ids_resolve_identically( string $old, string $new ): void {
			foreach ( array( 'finding', 'auditor_source' ) as $p ) {
				$this->assertSame( $new, $this->finding( array( 'source' => 'auditor', $p => $old ) ), "old via $p" );
				$this->assertSame( $new, $this->finding( array( 'source' => 'auditor', $p => $new ) ), "new via $p" );
			}
		}

		public function test_both_params_finding_wins_and_unknown_finding_falls_back(): void {
			$this->assertSame( 'sitemap_missing', $this->finding( array( 'source' => 'auditor', 'finding' => 'sitemap', 'auditor_source' => 'sitemap_missing' ) ) );
			$this->assertSame( 'missing_alt', $this->finding( array( 'source' => 'auditor', 'finding' => 'missing_alt', 'auditor_source' => 'https' ) ) );
			$this->assertSame( 'https_not_enforced', $this->finding( array( 'source' => 'auditor', 'finding' => 'bogus', 'auditor_source' => 'https' ) ) );
		}

		public function test_unknown_and_hostile_ids_are_ignored(): void {
			$this->assertSame( '', $this->finding( array( 'source' => 'auditor', 'finding' => 'not_a_check' ) ) );
			$this->assertSame( '', $this->finding( array( 'source' => 'auditor', 'auditor_source' => 'auditor' ) ) );
			$this->assertSame( '', $this->finding( array( 'source' => 'auditor', 'finding' => str_repeat( 'a', 5000 ) ) ) );
			$this->assertSame( '', $this->finding( array( 'source' => 'auditor', 'finding' => array( 'missing_alt' ) ) ) );
			$this->assertSame( 'missing_alt', normalize_auditor_finding( 'Missing_Alt<>' ) );
			$this->assertSame( '', normalize_auditor_finding( 'missing_alt' . str_repeat( 'x', 80 ) ) );
			$this->assertSame( 'sitemap_missing', normalize_auditor_finding( 'SITEMAP' ) );
		}

		public function test_requires_source_auditor(): void {
			$this->assertNull( $this->finding( array( 'finding' => 'missing_alt' ) ) );
			$this->assertNull( $this->finding( array( 'auditor_source' => 'missing_alt' ) ) );
			$this->assertNull( $this->finding( array( 'source' => 'email', 'finding' => 'https' ) ) );
		}

		public function test_redirect_carries_normalised_id_in_both_params(): void {
			$_GET = $_REQUEST = array( 'source' => 'auditor', 'finding' => 'open_graph' );
			parse_str( (string) parse_url( with_auditor_args( 'https://example.com/wp-admin/admin.php?page=bbai' ), PHP_URL_QUERY ), $q );
			$this->assertSame( 'og_tags_missing', $q['finding'] );
			$this->assertSame( 'og_tags_missing', $q['auditor_source'] );
		}
	}
}
