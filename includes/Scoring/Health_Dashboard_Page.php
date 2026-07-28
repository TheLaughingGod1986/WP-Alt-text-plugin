<?php
/**
 * Self-contained "dashboard-first" health page for OptiAI Alt Text.
 *
 * Deliberately independent of the existing admin/class-bbai-core.php render
 * pipeline (a large, single-dispatch legacy admin surface) so this new
 * surface carries zero risk of regressing the existing dashboard. It uses
 * plain PHP + vanilla JS (no build step) and talks to admin-ajax.php.
 *
 * Registers its own top-level menu entry; once this pattern is proven out,
 * a follow-up pass can fold it into (or replace) the primary dashboard tab.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator\Scoring;

use OptiAI\Core\Health_Score;
use OptiAI\Core\Scan\Scan_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Health_Dashboard_Page {

	const MODULE   = 'alt_text';
	const PAGE_SLUG = 'bbai-health';
	const NONCE_ACTION = 'bbai_health_nonce';

	const ONBOARDING_OPTION = 'optiai_alt_text_onboarding_complete';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 20 );
		add_action( 'wp_ajax_bbai_health_get', array( __CLASS__, 'ajax_get_health' ) );
		add_action( 'wp_ajax_bbai_health_priorities', array( __CLASS__, 'ajax_get_priorities' ) );
		add_action( 'wp_ajax_bbai_health_items', array( __CLASS__, 'ajax_get_items' ) );
		add_action( 'wp_ajax_bbai_health_scan', array( __CLASS__, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_bbai_health_complete_onboarding', array( __CLASS__, 'ajax_complete_onboarding' ) );
	}

	public static function add_menu() {
		add_menu_page(
			__( 'Alt Text Health', 'beepbeep-ai-alt-text-generator' ),
			__( 'OptiAI Alt Text', 'beepbeep-ai-alt-text-generator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-visibility',
			31
		);
	}

	// ------------------------------------------------------------------
	// AJAX handlers
	// ------------------------------------------------------------------

	private static function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'beepbeep-ai-alt-text-generator' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	public static function ajax_get_health() {
		self::verify_request();
		$repo    = new Scan_Repository( self::MODULE );
		$summary = $repo->get_summary();
		$prev    = $repo->get_previous_score();

		wp_send_json_success( array(
			'score'               => $summary['score'],
			'status'              => $summary['status'],
			'label'               => Health_Score::label( $summary['score'] ),
			'trend'               => Health_Score::trend( $summary['score'], $prev ),
			'items_scanned'       => $summary['total'],
			'critical_issues'     => $summary['critical'],
			'optimised_this_week' => $summary['optimised_this_week'],
			'by_status'           => $summary['by_status'],
			'last_scanned_at'     => $summary['last_scanned_at'],
			'disclaimer'          => Health_Score::disclaimer(),
		) );
	}

	public static function ajax_get_priorities() {
		self::verify_request();
		$repo    = new Scan_Repository( self::MODULE );
		$issues  = $repo->get_priority_issues( 10 );
		$summary = $repo->get_summary();

		wp_send_json_success( array(
			'priorities'            => $issues,
			'estimated_health_gain' => min( 100 - $summary['score'], array_sum( array_column( $issues, 'estimated_gain' ) ) ),
			'current_score'         => $summary['score'],
		) );
	}

	public static function ajax_get_items() {
		self::verify_request();
		$repo = new Scan_Repository( self::MODULE );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via check_ajax_referer() in verify_request().
		$issue_code = isset( $_POST['issue'] ) ? sanitize_text_field( wp_unslash( $_POST['issue'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		$result = $repo->get_items( array(
			'status'   => $status,
			'sort'     => 'lowest-score',
			'per_page' => 50,
		) );

		if ( '' !== $issue_code ) {
			$result['items'] = array_values( array_filter( $result['items'], static function ( $item ) use ( $issue_code ) {
				foreach ( $item['issues'] as $issue ) {
					if ( ( $issue['code'] ?? '' ) === $issue_code ) {
						return true;
					}
				}
				return false;
			} ) );
		}

		$result['items'] = array_map( static function ( $item ) {
			$id           = (int) $item['site_item_id'];
			$current      = json_decode( (string) $item['current_value'], true ) ?: array();
			$item['alt']  = $current['alt'] ?? '';
			$item['thumb'] = $current['thumb'] ?? wp_get_attachment_image_url( $id, 'thumbnail' );
			$item['name'] = get_the_title( $id ) ?: ( $current['filename'] ?? '' );
			$item['edit_url'] = get_edit_post_link( $id, 'raw' );
			return $item;
		}, $result['items'] );

		wp_send_json_success( $result );
	}

	public static function ajax_run_scan() {
		self::verify_request();
		$result = ( new Alt_Text_Scan_Service() )->run();
		wp_send_json_success( $result );
	}

	public static function ajax_complete_onboarding() {
		self::verify_request();
		update_option( self::ONBOARDING_OPTION, true );
		wp_send_json_success( array( 'complete' => true ) );
	}

	// ------------------------------------------------------------------
	// Page shell (plain PHP + vanilla JS, no build step)
	// ------------------------------------------------------------------

	public static function render_page() {
		$nonce            = wp_create_nonce( self::NONCE_ACTION );
		$ajaxUrl          = admin_url( 'admin-ajax.php' );
		$onboarding_done  = (bool) get_option( self::ONBOARDING_OPTION, false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Alt Text Health', 'beepbeep-ai-alt-text-generator' ); ?></h1>
			<div
				id="bbai-health-root"
				data-nonce="<?php echo esc_attr( $nonce ); ?>"
				data-ajax-url="<?php echo esc_url( $ajaxUrl ); ?>"
				data-onboarding-done="<?php echo $onboarding_done ? '1' : '0'; ?>"
			>
				<p><?php esc_html_e( 'Loading…', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
		</div>
		<style>
			.bbai-h-onboard-overlay { position: fixed; inset: 0; background: rgba(30,30,40,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center; }
			.bbai-h-onboard-modal { background: #fff; border-radius: 10px; width: 560px; max-width: 92vw; max-height: 88vh; overflow: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
			.bbai-h-onboard-steps { display: flex; gap: 6px; padding: 20px 24px 0; }
			.bbai-h-onboard-steps span { flex: 1; height: 3px; border-radius: 999px; background: #e2e2e2; }
			.bbai-h-onboard-steps span.done { background: #1e1e1e; }
			.bbai-h-onboard-body { padding: 16px 24px 24px; }
			.bbai-h-onboard-body h2 { font-size: 22px; margin: 0 0 8px; }
			.bbai-h-onboard-body p { font-size: 14px; color: #50575e; line-height: 1.55; margin: 0 0 16px; }
			.bbai-h-onboard-actions { display: flex; justify-content: space-between; margin-top: 20px; }
			.bbai-h-onboard-feature { display: flex; align-items: center; gap: 8px; font-size: 13px; margin: 6px 0; }
			#bbai-health-root { max-width: 960px; }
			.bbai-h-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
			.bbai-h-hero { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }
			.bbai-h-score-ring { width: 96px; height: 96px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 700; flex-shrink: 0; border: 8px solid #e5e5e5; }
			.bbai-h-score-ring.tone-ok { border-color: #00a32a; color: #00a32a; }
			.bbai-h-score-ring.tone-warn { border-color: #dba617; color: #b26200; }
			.bbai-h-score-ring.tone-danger { border-color: #d63638; color: #d63638; }
			.bbai-h-eyebrow { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #757575; margin-bottom: 6px; }
			.bbai-h-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px; color: #3c434a; margin-top: 8px; }
			.bbai-h-buttons { margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
			.bbai-h-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 16px; }
			.bbai-h-summary .bbai-h-card { margin-bottom: 0; padding: 12px 14px; }
			.bbai-h-summary-label { font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: #757575; margin-bottom: 6px; }
			.bbai-h-summary-value { font-size: 22px; font-weight: 700; }
			.bbai-h-priority-card { display: flex; gap: 12px; align-items: flex-start; }
			.bbai-h-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; flex-shrink: 0; }
			.bbai-h-pill.critical { background: #fcf0f1; color: #d63638; }
			.bbai-h-pill.warning { background: #fcf9e8; color: #b26200; }
			.bbai-h-pill.review { background: #fcf9e8; color: #b26200; }
			.bbai-h-priority-body { flex: 1; min-width: 220px; }
			.bbai-h-item-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-top: 1px solid #f0f0f1; }
			.bbai-h-item-row img { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; background: #f0f0f1; }
			.bbai-h-today { border-left: 4px solid #d63638; }
		</style>
		<script>
		(function () {
			var root = document.getElementById('bbai-health-root');
			var nonce = root.dataset.nonce;
			var ajaxUrl = root.dataset.ajaxUrl;
			var onboardingDone = root.dataset.onboardingDone === '1';

			function post(action, data) {
				var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce }, data || {}));
				return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) { return json.success ? json.data : Promise.reject(json); });
			}

			var toneFor = function (status) {
				if (status === 'excellent' || status === 'good') return 'ok';
				if (status === 'critical') return 'danger';
				return 'warn';
			};

			var actionCopy = function (p) {
				var n = p.count, plural = n === 1 ? '' : 's';
				switch (p.code) {
					case 'missing_alt_text': return 'Fix ' + n + ' missing alt text' + (n === 1 ? '' : ' attributes');
					case 'filename_alt_text': return 'Fix ' + n + ' image' + plural + ' using file names as alt text';
					case 'placeholder_alt_text': return 'Fix ' + n + ' placeholder alt text value' + plural;
					case 'generic_alt_text': return 'Improve ' + n + ' generic alt text value' + plural;
					case 'duplicate_alt_text': return 'Review ' + n + ' duplicated alt text value' + plural;
					case 'alt_too_short': return 'Improve ' + n + ' alt text value' + plural + ' that ' + (n === 1 ? 'is' : 'are') + ' too short';
					case 'alt_too_long': return 'Improve ' + n + ' alt text value' + plural + ' that ' + (n === 1 ? 'is' : 'are') + ' too long';
					case 'keyword_stuffing': return 'Review ' + n + ' image' + plural + ' with repeated words';
					default: return 'Improve ' + n + ' image' + plural;
				}
			};

			function renderAll(health, priorities) {
				var tone = toneFor(health.status);
				var today = priorities.priorities.slice(0, 3);

				var html = '';

				if (today.length) {
					html += '<div class="bbai-h-card bbai-h-today">';
					html += '<div class="bbai-h-eyebrow">Today\'s Priorities</div>';
					today.forEach(function (p) {
						var dot = p.severity === 'critical' ? '\u{1F534}' : (p.severity === 'warning' ? '\u{1F7E0}' : '\u{1F7E1}');
						html += '<div style="margin:4px 0;">' + dot + ' ' + actionCopy(p) + '</div>';
					});
					if (priorities.estimated_health_gain > 0) {
						html += '<div style="margin-top:8px;font-size:12.5px;color:#3c434a;">Estimated Health Improvement: <strong>+' + priorities.estimated_health_gain + ' points</strong></div>';
					}
					html += '</div>';
				}

				html += '<div class="bbai-h-card bbai-h-hero">';
				html += '<div class="bbai-h-score-ring tone-' + tone + '">' + health.score + '</div>';
				html += '<div>';
				html += '<div class="bbai-h-eyebrow">Alt Text Health</div>';
				html += '<div style="font-size:18px;font-weight:600;">' + health.label + '</div>';
				html += '<div class="bbai-h-meta">';
				html += '<span><strong>' + health.items_scanned + '</strong> images scanned</span>';
				html += '<span><strong>' + health.critical_issues + '</strong> critical issues</span>';
				html += '<span>Last scan <strong>' + (health.last_scanned_at ? health.last_scanned_at.split(' ')[0] : 'Never') + '</strong></span>';
				html += '</div>';
				html += '<div class="bbai-h-buttons">';
				html += '<button class="button" id="bbai-h-quick-scan">Quick Scan</button>';
				html += '<button class="button button-primary" id="bbai-h-optimise-critical"' + (health.critical_issues > 0 ? '' : ' disabled') + '>Optimise Critical Issues</button>';
				html += '</div>';
				html += '</div></div>';

				var byStatus = health.by_status || {};
				var missing = (priorities.priorities.find(function (p) { return p.code === 'missing_alt_text'; }) || {}).count || 0;
				var filename = (priorities.priorities.find(function (p) { return p.code === 'filename_alt_text'; }) || {}).count || 0;
				var duplicate = (priorities.priorities.find(function (p) { return p.code === 'duplicate_alt_text'; }) || {}).count || 0;

				html += '<div class="bbai-h-summary">';
				[
					['Images Scanned', health.items_scanned],
					['Missing Alt Text', missing],
					['File Names as Alt Text', filename],
					['Duplicate Alt Text', duplicate],
					['Excellent Images', byStatus.excellent || 0],
				].forEach(function (row) {
					html += '<div class="bbai-h-card"><div class="bbai-h-summary-label">' + row[0] + '</div><div class="bbai-h-summary-value">' + row[1] + '</div></div>';
				});
				html += '</div>';

				html += '<h2>Priority Action Centre</h2>';
				if (!priorities.priorities.length) {
					html += '<div class="bbai-h-card">Nothing needs attention — every scanned image looks healthy.</div>';
				}
				priorities.priorities.forEach(function (p) {
					html += '<div class="bbai-h-card bbai-h-priority-card" data-issue="' + p.code + '">';
					html += '<span class="bbai-h-pill ' + p.severity + '">' + (p.severity === 'critical' ? 'Critical' : p.severity === 'warning' ? 'High' : 'Medium') + '</span>';
					html += '<div class="bbai-h-priority-body">';
					html += '<div style="font-weight:600;margin-bottom:4px;">' + actionCopy(p) + '</div>';
					html += '<div style="font-size:12.5px;color:#3c434a;">' + p.message + '</div>';
					if (p.estimated_gain > 0) {
						html += '<div style="font-size:12px;color:#00a32a;font-weight:600;margin-top:4px;">Estimated improvement: +' + p.estimated_gain + ' score</div>';
					}
					html += '<div class="bbai-h-items" style="display:none;margin-top:10px;"></div>';
					html += '</div>';
					html += '<div style="flex-shrink:0;display:flex;gap:8px;">';
					html += '<button class="button bbai-h-review" data-issue="' + p.code + '">Review</button>';
					html += '<button class="button button-primary bbai-h-optimise-issue" data-issue="' + p.code + '">Optimise All</button>';
					html += '</div></div>';
				});

				root.innerHTML = html;
				wireEvents(health, priorities);
			}

			function wireEvents(health, priorities) {
				var quickBtn = document.getElementById('bbai-h-quick-scan');
				if (quickBtn) quickBtn.addEventListener('click', function () {
					quickBtn.textContent = 'Scanning…';
					post('bbai_health_scan').then(load).catch(load);
				});

				var criticalBtn = document.getElementById('bbai-h-optimise-critical');
				if (criticalBtn) criticalBtn.addEventListener('click', function () {
					alert('Optimising critical issues uses OptiAI service credits — hook this into the existing bulk-generate flow.');
				});

				root.querySelectorAll('.bbai-h-review').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var card = btn.closest('.bbai-h-priority-card');
						var box = card.querySelector('.bbai-h-items');
						if (box.style.display !== 'none') { box.style.display = 'none'; return; }
						box.style.display = 'block';
						box.innerHTML = 'Loading…';
						post('bbai_health_items', { issue: btn.dataset.issue }).then(function (res) {
							if (!res.items.length) { box.innerHTML = 'No items found.'; return; }
							box.innerHTML = res.items.map(function (item) {
								return '<div class="bbai-h-item-row">' +
									(item.thumb ? '<img src="' + item.thumb + '">' : '') +
									'<div style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (item.name || '#' + item.site_item_id) + '</div>' +
									'<span style="font-size:11.5px;color:#757575;">Score ' + item.score + '</span>' +
									'<a class="button button-small" href="' + (item.edit_url || '#') + '">Edit</a>' +
									'</div>';
							}).join('');
						});
					});
				});

				root.querySelectorAll('.bbai-h-optimise-issue').forEach(function (btn) {
					btn.addEventListener('click', function () {
						alert('Optimising this issue group uses OptiAI service credits — hook this into the existing bulk-generate flow for issue "' + btn.dataset.issue + '".');
					});
				});
			}

			function load() {
				Promise.all([post('bbai_health_get'), post('bbai_health_priorities')]).then(function (results) {
					renderAll(results[0], results[1]);
				}).catch(function () {
					root.innerHTML = '<div class="bbai-h-card">Could not load health data. Try running a scan from the Dashboard tab first.</div>';
				});
			}

			/* ── 4-screen onboarding: Welcome -> scan scope -> free health check -> results ── */
			function renderOnboarding() {
				var overlay = document.createElement('div');
				overlay.className = 'bbai-h-onboard-overlay';
				var state = { step: 0, score: 0, issuesFound: 0, itemsScanned: 0 };

				function stepsHtml() {
					var out = '';
					for (var i = 0; i < 4; i++) out += '<span class="' + (i <= state.step ? 'done' : '') + '"></span>';
					return out;
				}

				function render() {
					var body = '';
					if (state.step === 0) {
						body = '<h2>Improve your website continuously with OptiAI</h2>' +
							'<p>OptiAI scans your media library, identifies alt text issues and helps you improve them with AI-powered recommendations. The health check is free — you only spend credits when you choose to fix something.</p>' +
							'<div class="bbai-h-onboard-feature">✓ Free health score — scanning never uses credits</div>' +
							'<div class="bbai-h-onboard-feature">✓ Priority Action Centre shows what to fix first</div>' +
							'<div class="bbai-h-onboard-feature">✓ Continuous optimisation keeps new uploads covered</div>' +
							'<div class="bbai-h-onboard-actions"><span></span><button class="button button-primary" id="bbai-ob-next-0">Let\'s go</button></div>';
					} else if (state.step === 1) {
						body = '<h2>Choose what to scan</h2>' +
							'<p>OptiAI will scan every image in your Media Library, including featured images and WooCommerce product images where present. You can re-scan anytime from this dashboard.</p>' +
							'<div class="bbai-h-onboard-actions"><button class="button" id="bbai-ob-back-1">Back</button><button class="button button-primary" id="bbai-ob-next-1">Continue</button></div>';
					} else if (state.step === 2) {
						body = '<h2>Run your free health check</h2>' +
							'<p>We\'ll scan every image for missing, weak, duplicate and filename-style alt text. This is completely free — no credits are used.</p>' +
							'<div style="padding:28px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;text-align:center;">' +
							(state.scanning
								? '<div>Scanning your media library…</div>'
								: '<button class="button button-primary button-hero" id="bbai-ob-run-scan">Run My Free Health Check</button>') +
							'</div>' +
							'<div class="bbai-h-onboard-actions"><button class="button" id="bbai-ob-back-2"' + (state.scanning ? ' disabled' : '') + '>Back</button><span></span></div>';
					} else {
						body = '<h2>Your site score is ' + state.score + '</h2>' +
							'<p>' + (state.issuesFound > 0
								? 'We found <strong>' + state.issuesFound + '</strong> optimisation opportunit' + (state.issuesFound === 1 ? 'y' : 'ies') + ' across <strong>' + state.itemsScanned + '</strong> images.'
								: 'Nothing needs attention right now — your images already look healthy.') + '</p>' +
							'<div class="bbai-h-onboard-feature">✓ Free health checks whenever you want them</div>' +
							'<div class="bbai-h-onboard-feature">✓ Fix issues one at a time or in bulk</div>' +
							'<div class="bbai-h-onboard-actions"><span></span><button class="button button-primary" id="bbai-ob-finish">View Recommendations</button></div>';
					}
					overlay.innerHTML = '<div class="bbai-h-onboard-modal"><div class="bbai-h-onboard-steps">' + stepsHtml() + '</div><div class="bbai-h-onboard-body">' + body + '</div></div>';
					wire();
				}

				function wire() {
					var next0 = document.getElementById('bbai-ob-next-0');
					if (next0) next0.addEventListener('click', function () { state.step = 1; render(); });
					var back1 = document.getElementById('bbai-ob-back-1');
					if (back1) back1.addEventListener('click', function () { state.step = 0; render(); });
					var next1 = document.getElementById('bbai-ob-next-1');
					if (next1) next1.addEventListener('click', function () { state.step = 2; render(); });
					var back2 = document.getElementById('bbai-ob-back-2');
					if (back2) back2.addEventListener('click', function () { state.step = 1; render(); });
					var runScan = document.getElementById('bbai-ob-run-scan');
					if (runScan) runScan.addEventListener('click', function () {
						state.scanning = true;
						render();
						post('bbai_health_scan').then(function (res) {
							state.score = res.average_score || 0;
							state.issuesFound = res.issues_found || 0;
							state.itemsScanned = res.items_scanned || 0;
							state.scanning = false;
							state.step = 3;
							render();
						}).catch(function () {
							state.scanning = false;
							state.step = 3;
							render();
						});
					});
					var finish = document.getElementById('bbai-ob-finish');
					if (finish) finish.addEventListener('click', function () {
						post('bbai_health_complete_onboarding').finally(function () {
							document.body.removeChild(overlay);
							load();
						});
					});
				}

				document.body.appendChild(overlay);
				render();
			}

			if (onboardingDone) {
				load();
			} else {
				renderOnboarding();
			}
		})();
		</script>
		<?php
	}
}
