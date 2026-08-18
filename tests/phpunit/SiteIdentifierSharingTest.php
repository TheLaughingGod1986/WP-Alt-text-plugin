<?php
/**
 * Regression tests for site identifier sharing between the two OpptiAI plugins.
 *
 * Credits are pooled per backend canonical site, resolved from the site
 * identifier, so OpptiAI Alt Text and OpptiAI Titles must agree on one value
 * per WordPress site. Titles adopts our `beepbeepai_site_id` when we register
 * first; these tests cover the reverse direction, which previously did not
 * exist and left a Titles-first site with two identifiers, two canonical sites
 * and two separate free allowances.
 */

declare(strict_types=1);

use function BeepBeepAI\AltTextGenerator\get_site_identifier;
use PHPUnit\Framework\TestCase;

final class SiteIdentifierSharingTest extends TestCase {

	private const SIBLING_KEY = 'beepti_site_id';
	private const OWN_KEY     = 'beepbeepai_site_id';

	protected function setUp(): void {
		// The bootstrap seeds an id for other suites; start from a clean slate
		// so each ordering can be exercised explicitly.
		unset(
			$GLOBALS['bbai_test_options'][ self::OWN_KEY ],
			$GLOBALS['bbai_test_options'][ self::SIBLING_KEY ]
		);
	}

	protected function tearDown(): void {
		$GLOBALS['bbai_test_options'][ self::OWN_KEY ] = 'test_site_install_id_0123456789ab';
		unset( $GLOBALS['bbai_test_options'][ self::SIBLING_KEY ] );
	}

	/**
	 * Titles installed first: we must adopt its id rather than mint our own,
	 * so both plugins resolve to the same canonical site and share one wallet.
	 */
	public function test_adopts_titles_site_id_when_titles_registered_first(): void {
		$titles_id = str_repeat( 'a1b2c3d4', 4 );

		$GLOBALS['bbai_test_options'][ self::SIBLING_KEY ] = $titles_id;

		$this->assertSame( $titles_id, get_site_identifier() );
	}

	/**
	 * Adoption must persist under our own option key, so the shared value
	 * survives without re-reading the sibling plugin on every call.
	 */
	public function test_adopted_id_is_cached_under_own_option(): void {
		$titles_id = str_repeat( 'f0e1d2c3', 4 );

		$GLOBALS['bbai_test_options'][ self::SIBLING_KEY ] = $titles_id;
		get_site_identifier();

		$this->assertSame( $titles_id, $GLOBALS['bbai_test_options'][ self::OWN_KEY ] );
	}

	/**
	 * Our own id always wins. Adoption is only for sites that have no
	 * identifier yet — an established site must never be moved onto a
	 * different wallet, which would orphan its recorded usage.
	 */
	public function test_own_site_id_is_never_replaced_by_sibling(): void {
		$own_id    = str_repeat( '01234567', 4 );
		$titles_id = str_repeat( '89abcdef', 4 );

		$GLOBALS['bbai_test_options'][ self::OWN_KEY ]     = $own_id;
		$GLOBALS['bbai_test_options'][ self::SIBLING_KEY ] = $titles_id;

		$this->assertSame( $own_id, get_site_identifier() );
	}

	/**
	 * With no sibling present we fall through to generating our own id, so a
	 * standalone install is unaffected by the adoption branch.
	 */
	public function test_generates_own_id_when_no_sibling_exists(): void {
		$generated = get_site_identifier();

		$this->assertSame( 32, strlen( $generated ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $generated );
		$this->assertSame( $generated, $GLOBALS['bbai_test_options'][ self::OWN_KEY ] );
	}

	/**
	 * A junk sibling value must not be adopted — we would be pooling against
	 * an identifier the backend cannot resolve.
	 */
	public function test_ignores_too_short_sibling_value(): void {
		$GLOBALS['bbai_test_options'][ self::SIBLING_KEY ] = 'short';

		$generated = get_site_identifier();

		$this->assertNotSame( 'short', $generated );
		$this->assertSame( 32, strlen( $generated ) );
	}
}
