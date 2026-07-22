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
						$plan = isset( $_GET['plan'] ) ? sanitize_key( (string) $_GET['plan'] ) : 'free';
						$is_paid = in_array( $plan, array( 'starter', 'pro', 'growth' ), true );
						$can_autopilot = self::plan_can_use_autopilot( $plan );
						$has_subscription = isset( $_GET['subscription'] ) ? 'active' === (string) $_GET['subscription'] : $is_paid;
						return array(
							'is_pro'                  => $can_autopilot,
							'is_paid'                 => $is_paid,
							'is_agency'               => false,
							'can_autopilot'           => $can_autopilot,
							'plan_slug'               => $is_paid ? $plan : 'free',
							'stripe_subscription_id'  => $has_subscription ? 'sub_preview_123' : '',
							'subscription_status'     => $has_subscription ? 'active' : 'none',
							'has_active_subscription' => $has_subscription,
						);
					}

				public static function plan_can_use_autopilot( $plan ) {
					return in_array( sanitize_key( (string) $plan ), array( 'pro', 'growth', 'agency', 'enterprise' ), true );
				}
			}
		}
	}

namespace BeepBeepAI\AltTextGenerator\Services {
	if ( ! class_exists( __NAMESPACE__ . '\Usage_Helper', false ) ) {
		class Usage_Helper {
				public static function get_usage( $client, $connected ) {
					$plan = isset( $_GET['plan'] ) ? sanitize_key( (string) $_GET['plan'] ) : 'free';
					$pro = in_array( $plan, array( 'pro', 'growth' ), true );
					$starter = 'starter' === $plan;
					$is_paid = $pro || $starter;
					$has_subscription = isset( $_GET['subscription'] ) ? 'active' === (string) $_GET['subscription'] : $is_paid;
					return array(
						'used'                   => $pro ? 487 : ( $starter ? 42 : 6 ),
						'limit'                  => $pro ? 1000 : ( $starter ? 100 : 50 ),
						'credits_used'           => $pro ? 487 : ( $starter ? 42 : 6 ),
						'credits_total'          => $pro ? 1000 : ( $starter ? 100 : 50 ),
						'remaining'              => $pro ? 513 : ( $starter ? 58 : 44 ),
						'reset_date'             => '2026-06-01',
						'stripe_subscription_id' => $has_subscription ? 'sub_preview_123' : '',
						'subscription_status'    => $has_subscription ? 'active' : 'none',
						'entitlement_state'      => array(
							'stripe_subscription_id'  => $has_subscription ? 'sub_preview_123' : '',
							'subscription_status'     => $has_subscription ? 'active' : 'none',
							'has_active_subscription' => $has_subscription,
						),
						);
					}

				public static function plan_can_use_autopilot( $plan ) {
					return in_array( sanitize_key( (string) $plan ), array( 'pro', 'growth', 'agency', 'enterprise' ), true );
				}
			}
		}
	if ( ! class_exists( __NAMESPACE__ . '\Upgrade_Cta_Resolver', false ) ) {
		class Upgrade_Cta_Resolver {
			const STATE_LOGGED_OUT = 'logged_out';

			public static function resolve( $context ) {
				$has_connected = ! empty( $context['has_connected_account'] );
				return array(
					'state'                  => $has_connected ? 'upgrade' : self::STATE_LOGGED_OUT,
					'modal_mode'             => $has_connected ? 'upgrade' : 'signup_first',
					'primary_label'          => $has_connected ? 'Create free account' : 'Create free account',
					'modal_primary_label'    => $has_connected ? 'Upgrade' : 'Create free account',
					'tooltip_locked'         => 'Create your account to continue.',
					'show_credit_pack'       => true,
					'modal_title_default'    => 'Choose a plan',
					'modal_subtitle_default' => 'Compare available plans.',
				);
			}
		}
	}
}

namespace BeepBeepAI\AltTextGenerator {
	if ( ! class_exists( __NAMESPACE__ . '\Auth_State', false ) ) {
		class Auth_State {
			public static function resolve( $api_client ) {
				return array( 'has_connected_account' => true );
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
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$requested_plan = isset( $_GET['plan'] ) ? sanitize_key( (string) $_GET['plan'] ) : 'free';
$plan          = in_array( $requested_plan, array( 'starter', 'pro', 'growth' ), true ) ? $requested_plan : 'free';
	$screen        = isset( $_GET['screen'] ) ? $_GET['screen'] : 'dashboard';
	$library_state = isset( $_GET['state'] ) ? $_GET['state'] : 'mid';
	$locked_state  = isset( $_GET['locked'] ) && '1' === (string) $_GET['locked'];
	$exhausted_state = $locked_state || ( isset( $_GET['credits'] ) && '0' === (string) $_GET['credits'] );
	$trial_state = isset( $_GET['trial'] ) && '1' === (string) $_GET['trial'];
	$daily_exhausted_state = $trial_state && ! $exhausted_state && isset( $_GET['daily'] ) && 'exhausted' === (string) $_GET['daily'];

$states = array(
	'fresh' => array( 'total' => 412, 'optimised' => 28,  'missing' => 384, 'weak' => 0,  'coverage' => 7  ),
	'mid'   => array( 'total' => 412, 'optimised' => 247, 'missing' => 142, 'weak' => 23, 'coverage' => 60 ),
	'near'  => array( 'total' => 412, 'optimised' => 389, 'missing' => 11,  'weak' => 12, 'coverage' => 94 ),
	'done'  => array( 'total' => 412, 'optimised' => 412, 'missing' => 0,   'weak' => 0,  'coverage' => 100 ),
);
$s = $states[ $library_state ] ?? $states['mid'];
$preview_pass_items = ( 'fresh' === $library_state || 'done' === $library_state ) ? array() : array(
	array( 'id' => 101, 'name' => 'hero-spring-collection.jpg', 'signal' => 'Missing ALT', 'tone' => 'danger', 'hue' => 0, 'thumb_url' => 'https://via.placeholder.com/88/f9e4e4/7f1d1d?text=%231' ),
	array( 'id' => 102, 'name' => 'team-portrait-2026.jpg', 'signal' => 'Missing ALT', 'tone' => 'danger', 'hue' => 70, 'thumb_url' => 'https://via.placeholder.com/88/e1f3f8/164e63?text=%232' ),
	array( 'id' => 103, 'name' => 'blog-cover-seo-guide.png', 'signal' => 'Needs review', 'tone' => 'warn', 'hue' => 140, 'thumb_url' => 'https://via.placeholder.com/88/e9f8ec/14532d?text=%233' ),
);
	$preview_daily_used = $daily_exhausted_state ? 5 : ( 'fresh' === $library_state ? 0 : ( 'near' === $library_state ? 1 : 3 ) );
	$preview_daily_limit = in_array( $plan, array( 'starter', 'pro', 'growth' ), true ) ? 200 : 5;
	$preview_daily_remaining = max( 0, $preview_daily_limit - $preview_daily_used );
	$bbai_preview_is_growth_plan = in_array( $plan, array( 'pro', 'growth' ), true );
	$bbai_preview_is_paid_plan = in_array( $plan, array( 'starter', 'pro', 'growth' ), true );

	$bbai_dashboard_state = array(
		'plan'                  => $plan,
		'planSlug'              => 'pro' === $plan ? 'growth' : $plan,
		'plan_type'             => 'pro' === $plan ? 'growth' : $plan,
		'totalImages'           => $s['total'],
	'optimizedCount'        => $s['optimised'],
	'missingCount'          => $s['missing'],
	'weakCount'             => $s['weak'],
	'coveragePercent'       => $s['coverage'],
	'todaysPassItems'       => $preview_pass_items,
	'dailyCreditsUsed'      => $preview_daily_used,
	'dailyCreditsLimit'     => $preview_daily_limit,
	'dailyCreditsRemaining' => $preview_daily_remaining,
	'dailyResetDate'        => '2026-05-27T00:00:00Z',
	'editingStreak'         => 4,
		'isProPlan'             => $bbai_preview_is_growth_plan,
	'creditsUsed'           => in_array( $plan, array( 'pro', 'growth' ), true ) ? 312 : ( 'starter' === $plan ? 42 : $preview_daily_used ),
	'creditsLimit'          => in_array( $plan, array( 'pro', 'growth' ), true ) ? 1000 : ( 'starter' === $plan ? 100 : 50 ),
	'creditsRemaining'      => in_array( $plan, array( 'pro', 'growth' ), true ) ? 688 : ( 'starter' === $plan ? 58 : 23 ),
	'daysUntilReset'        => 4,
	'libraryUrl'            => '#library',
	'missingLibraryUrl'     => '#library-missing',
	'needsReviewLibraryUrl' => '#library-review',
	'settingsUrl'           => '#settings',
	'usageUrl'              => '#usage',
);
	$bbai_state_is_pro_plan = $bbai_preview_is_growth_plan;
	$bbai_preview_has_subscription = isset( $_GET['subscription'] ) ? 'active' === (string) $_GET['subscription'] : $bbai_preview_is_paid_plan;
$bbai_preview_entitlement_state = array(
	'plan'                   => $plan,
	'plan_type'              => $plan,
	'token_limit'            => in_array( $plan, array( 'pro', 'growth' ), true ) ? 1000 : ( 'starter' === $plan ? 100 : 50 ),
	'tokens_used_this_month' => $exhausted_state ? 50 : ( in_array( $plan, array( 'pro', 'growth' ), true ) ? 312 : ( 'starter' === $plan ? 42 : 27 ) ),
	'total_tokens_used'      => $exhausted_state ? 50 : ( in_array( $plan, array( 'pro', 'growth' ), true ) ? 312 : ( 'starter' === $plan ? 42 : 27 ) ),
	'tokens_remaining'       => $exhausted_state ? 0 : ( in_array( $plan, array( 'pro', 'growth' ), true ) ? 688 : ( 'starter' === $plan ? 58 : 23 ) ),
	'daily_generation_limit' => $preview_daily_limit,
	'daily_generations_used' => $preview_daily_used,
	'daily_generations_remaining' => $preview_daily_remaining,
	'daily_reset_date'       => '2026-05-27T00:00:00Z',
	'can_generate'           => in_array( $plan, array( 'starter', 'pro', 'growth' ), true ) || ( ! $exhausted_state && ! $daily_exhausted_state ),
		'can_autopilot'          => $bbai_preview_is_growth_plan,
	'is_logged_in'           => true,
		'is_trial'               => $trial_state,
		'is_unlimited'           => $bbai_preview_is_growth_plan,
	'upgrade_required'       => ! in_array( $plan, array( 'starter', 'pro', 'growth' ), true ) && ( $exhausted_state || $daily_exhausted_state ),
	'quota_state'            => $exhausted_state ? 'exhausted' : ( $daily_exhausted_state ? 'daily_exhausted' : 'active' ),
	'message'                => $exhausted_state ? 'Monthly credits exhausted.' : ( $daily_exhausted_state ? 'Daily free generation limit reached.' : '' ),
	'stripe_subscription_id'  => $bbai_preview_has_subscription ? 'sub_preview_123' : '',
	'subscription_status'    => $bbai_preview_has_subscription ? 'active' : 'none',
);

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $s )  { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_js( $s ) { return str_replace( array( "\r", "\n", '</' ), array( '\r', '\n', '<\/' ), addslashes( (string) $s ) ); }
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function __( $s, $d = null ) { return $s; }
function _e( $s, $d = null ) { echo $s; }
function _n( $sing, $plur, $n, $d = null ) { return $n === 1 ? $sing : $plur; }
function apply_filters( $tag, $value ) { return $value; }
function is_wp_error( $value ) { return false; }
function number_format_i18n( $n, $decimals = 0 ) { return number_format( (float) $n, (int) $decimals ); }
function get_option( $key, $default = array() ) {
	if ( 'bbai_options' === $key ) {
		return array(
				'auto_generate'       => isset( $_GET['plan'] ) && in_array( sanitize_key( (string) $_GET['plan'] ), array( 'pro', 'growth' ), true ),
				'weekly_digest'       => isset( $_GET['plan'] ) && in_array( sanitize_key( (string) $_GET['plan'] ), array( 'pro', 'growth' ), true ),
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
	} elseif ( count( $args ) === 2 ) {
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$url    = $args[1];
		} else {
			$url    = '';
			$params = array( $args[0] => $args[1] );
		}
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
	function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
	function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	return array( 'https://via.placeholder.com/88?text=' . rawurlencode( (string) $id ), 88, 88 );
}
function get_attached_file( $id ) { return false; }
function wp_get_attachment_image_url( $id, $size = 'full' ) { return false; }
function wp_get_attachment_metadata( $id ) { return array(); }
function wp_cache_get( $key, $group = '' ) { return false; }
function wp_cache_set( $key, $value, $group = '', $expire = 0 ) { return true; }
function get_post_meta( $id, $key = '', $single = false ) { return $single ? '' : array(); }
function current_time( $type, $gmt = 0 ) { return 'timestamp' === $type ? time() : date( 'Y-m-d H:i:s' ); }
if ( ! class_exists( 'WP_Query', false ) ) {
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) {}
	}
}
$GLOBALS['wpdb'] = new class {
	public $prefix = 'wp_';
	public function get_var( $query = null ) { return null; }
	public function prepare( $query, ...$args ) { return $query; }
};
function wp_html_excerpt( $str, $count, $more = '' ) {
	$s = (string) $str;
	return strlen( $s ) > $count ? substr( $s, 0, $count ) . $more : $s;
}
function date_i18n( $format, $ts = null ) { return date( $format, $ts ?: time() ); }
function wp_date( $format, $ts = null ) { return date( $format, $ts ?: time() ); }
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
<script>window.bbaiInitialEntitlementState = <?php echo wp_json_encode( $bbai_preview_entitlement_state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test-only JSON fixture. ?>;</script>
<script src="/assets/js/bbai-entitlements.js" defer></script>
<script src="/assets/js/nai-dashboard.js" defer></script>
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
		$bbai_is_pro           = in_array( $plan, array( 'pro', 'growth' ), true );
		$bbai_limit_reached_state  = $exhausted_state;
		$bbai_default_review_filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';
		$bbai_current_page     = isset( $_GET['alt_page'] ) ? max( 1, (int) $_GET['alt_page'] ) : 1;
		$bbai_total_pages      = isset( $_GET['pages'] ) ? max( 1, (int) $_GET['pages'] ) : 1;
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
		if ( $bbai_total_pages > 1 ) {
			$nai_preview_mock[] = array( 'name' => 'gallery-event-2026.jpg', 'status' => 'missing' );
			$nai_preview_mock[] = array( 'name' => 'case-study-hero.png', 'status' => 'optimized' );
		}
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
if ( isset( $_GET['modal'] ) && 'upgrade' === (string) $_GET['modal'] ) {
	$checkout_prices = array(
		'starter' => 'price_starter_preview',
		'pro'     => 'price_growth_preview',
		'growth'  => 'price_growth_preview',
		'credits' => 'price_credits_preview',
	);
	$bbai_usage_data = array(
		'plan'              => $plan,
		'plan_type'         => $plan,
		'used'              => $bbai_preview_entitlement_state['tokens_used_this_month'],
		'limit'             => $bbai_preview_entitlement_state['token_limit'],
		'remaining'         => $bbai_preview_entitlement_state['tokens_remaining'],
		'entitlement_state' => $bbai_preview_entitlement_state,
	);
	require BEEPBEEP_AI_PLUGIN_DIR . 'templates/upgrade-modal.php';
}
?>
</body>
</html>
<?php
} // namespace
