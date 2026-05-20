<?php
/**
 * Standalone preview shim for nAi screens. Used by tests/e2e/nai-design.spec.ts
 * to render the redesign in isolation without a WordPress install.
 *
 * Not loaded by WordPress at runtime — relied on by Playwright integration tests
 * that start a `php -S` server from the repo root.
 */

namespace BeepBeepAI\AltTextGenerator\Admin {
	if ( ! class_exists( __NAMESPACE__ . '\Plan_Helpers', false ) ) {
		class Plan_Helpers {
			public static function get_plan_data() {
				return array(
					'is_pro'    => isset( $_GET['plan'] ) && 'pro' === $_GET['plan'],
					'is_agency' => false,
					'plan_slug' => isset( $_GET['plan'] ) && 'pro' === $_GET['plan'] ? 'pro' : 'free',
				);
			}
		}
	}
}

namespace BeepBeepAI\AltTextGenerator\Services {
	if ( ! class_exists( __NAMESPACE__ . '\Usage_Helper', false ) ) {
		class Usage_Helper {
			public static function get_usage( $client, $connected ) {
				$pro = isset( $_GET['plan'] ) && 'pro' === $_GET['plan'];
				return array(
					'used'          => $pro ? 487 : 6,
					'limit'         => $pro ? 1000 : 50,
					'credits_used'  => $pro ? 487 : 6,
					'credits_total' => $pro ? 1000 : 50,
					'remaining'     => $pro ? 513 : 44,
					'reset_date'    => '2026-06-01',
				);
			}
		}
	}
}

namespace {

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}
if ( ! defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
	define( 'BEEPBEEP_AI_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

$plan          = isset( $_GET['plan'] ) && 'pro' === $_GET['plan'] ? 'pro' : 'free';
$screen        = isset( $_GET['screen'] ) ? $_GET['screen'] : 'dashboard';
$library_state = isset( $_GET['state'] ) ? $_GET['state'] : 'mid';

$states = array(
	'fresh' => array( 'total' => 412, 'optimised' => 28,  'missing' => 384, 'weak' => 0,  'coverage' => 7  ),
	'mid'   => array( 'total' => 412, 'optimised' => 247, 'missing' => 142, 'weak' => 23, 'coverage' => 60 ),
	'near'  => array( 'total' => 412, 'optimised' => 389, 'missing' => 11,  'weak' => 12, 'coverage' => 94 ),
	'done'  => array( 'total' => 412, 'optimised' => 412, 'missing' => 0,   'weak' => 0,  'coverage' => 100 ),
);
$s = $states[ $library_state ] ?? $states['mid'];

$bbai_dashboard_state = array(
	'totalImages'           => $s['total'],
	'optimizedCount'        => $s['optimised'],
	'missingCount'          => $s['missing'],
	'weakCount'             => $s['weak'],
	'coveragePercent'       => $s['coverage'],
	'isProPlan'             => ( 'pro' === $plan ),
	'creditsUsed'           => 'pro' === $plan ? 312 : 27,
	'creditsLimit'          => 'pro' === $plan ? 1000 : 50,
	'creditsRemaining'      => 'pro' === $plan ? 688 : 23,
	'daysUntilReset'        => 4,
	'libraryUrl'            => '#library',
	'missingLibraryUrl'     => '#library-missing',
	'needsReviewLibraryUrl' => '#library-review',
	'settingsUrl'           => '#settings',
	'usageUrl'              => '#usage',
);
$bbai_state_is_pro_plan = ( 'pro' === $plan );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s )  { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function __( $s, $d = null ) { return $s; }
function _e( $s, $d = null ) { echo $s; }
function _n( $sing, $plur, $n, $d = null ) { return $n === 1 ? $sing : $plur; }
function apply_filters( $tag, $value ) { return $value; }
function number_format_i18n( $n, $decimals = 0 ) { return number_format( (float) $n, (int) $decimals ); }
function get_option( $key, $default = array() ) {
	if ( 'bbai_options' === $key ) {
		return array(
			'auto_generate'       => isset( $_GET['plan'] ) && 'pro' === $_GET['plan'],
			'weekly_digest'       => isset( $_GET['plan'] ) && 'pro' === $_GET['plan'],
			'generation_style'    => 'descriptive',
			'generation_length'   => 'medium',
			'custom_instructions' => '',
		);
	}
	return $default;
}
function admin_url( $path = '' ) { return '/wp-admin/' . ltrim( $path, '/' ); }
function add_query_arg( ...$args ) {
	if ( count( $args ) === 1 && is_array( $args[0] ) ) {
		$params = $args[0]; $url = '';
	} elseif ( count( $args ) >= 3 ) {
		$url    = $args[2];
		$params = array( $args[0] => $args[1] );
	} else {
		$url    = '';
		$params = is_array( $args[0] ) ? $args[0] : array();
	}
	$sep = strpos( $url, '?' ) === false ? '?' : '&';
	$qs  = http_build_query( $params );
	return $url . $sep . $qs;
}
function sanitize_email( $s ) { return filter_var( (string) $s, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) { return false; }
function get_attached_file( $id ) { return false; }
function wp_get_attachment_image_url( $id, $size = 'full' ) { return false; }
function wp_get_attachment_metadata( $id ) { return array(); }
function wp_html_excerpt( $str, $count, $more = '' ) {
	$s = (string) $str;
	return strlen( $s ) > $count ? substr( $s, 0, $count ) . $more : $s;
}
function date_i18n( $format, $ts = null ) { return date( $format, $ts ?: time() ); }
function size_format( $bytes ) { return number_format( (float) $bytes / 1024, 1 ) . ' KB'; }
function absint( $v ) { return abs( (int) $v ); }

$css_href = '/assets/css/nai-dashboard.css';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>nAi <?php echo esc_html( ucfirst( $screen ) ); ?> preview</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_attr( $css_href ); ?>">
<style>body { margin: 0; background: #F6F8FB; }</style>
</head>
<body>
<?php
switch ( $screen ) {
	case 'autopilot':
		require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-autopilot.php';
		break;
	case 'settings':
		require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-settings.php';
		break;
	case 'library':
		// Library partial expects locals from library-tab.php; provide a
		// minimal sample dataset so the preview can render in isolation.
		$bbai_cov_total        = $s['total'];
		$bbai_cov_optimized    = $s['optimised'];
		$bbai_cov_needs_review = $s['weak'];
		$bbai_cov_missing      = $s['missing'];
		$bbai_cov_opt_pct      = $s['coverage'];
		$bbai_total_images     = $s['total'];
		$bbai_is_pro           = ( 'pro' === $plan );
		$bbai_limit_reached_state  = false;
		$bbai_default_review_filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';
		$bbai_current_page     = 1;
		$bbai_total_pages      = 1;
		$bbai_all_images       = array();
		$bbai_library_row_states = array();
		// Fabricate a handful of mock image rows for the preview.
		$nai_preview_mock = array(
			array( 'name' => 'hero-spring-collection.jpg', 'status' => 'missing'   ),
			array( 'name' => 'team-portrait-2026.jpg',     'status' => 'weak'      ),
			array( 'name' => 'blog-cover-seo-guide.png',   'status' => 'optimized' ),
			array( 'name' => 'product-shot-coffee-04.jpg', 'status' => 'missing'   ),
			array( 'name' => 'testimonial-jane-d.jpg',     'status' => 'weak'      ),
			array( 'name' => 'feature-launch-2026.jpg',    'status' => 'optimized' ),
		);
		foreach ( $nai_preview_mock as $i => $m ) {
			$row = new stdClass();
			$row->ID            = 1000 + $i;
			$row->post_title    = $m['name'];
			$row->post_modified = '2026-05-15 09:30:00';
			$row->alt_text      = 'missing' === $m['status'] ? '' : 'A short sample description';
			$bbai_all_images[]  = $row;
			$bbai_library_row_states[ $i ] = array( 'status' => $m['status'] );
		}
		require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-library.php';
		break;
	default:
		require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-dashboard.php';
}
?>
</body>
</html>
<?php
} // namespace
