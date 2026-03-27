/**
 * BeepBeep AI — Phase 12 product telemetry (client batching + DOM hooks).
 *
 * @package BeepBeep_AI
 */
(function ($) {
    'use strict';

    var cfg = window.BBAI_TELEMETRY || {};
    var queue = [];
    var flushTimer = null;
    var pageViewSent = false;
    var navSent = {};
    var posthogAllowlist = {
        plugin_opened: true,
        dashboard_viewed: true,
        guest_dashboard_viewed: true,
        alt_library_viewed: true,
        analytics_viewed: true,
        usage_viewed: true,
        settings_viewed: true,
        trial_cta_clicked: true,
        trial_started: true,
        trial_generation_started: true,
        trial_generation_completed: true,
        trial_exhausted: true,
        signup_cta_clicked: true,
        login_cta_clicked: true,
        login_modal_opened: true,
        login_submitted: true,
        login_succeeded: true,
        login_failed: true,
        signup_started: true,
        signup_succeeded: true,
        scan_started: true,
        scan_completed: true,
        generation_started: true,
        generation_completed: true,
        generation_failed: true,
        review_alt_clicked: true,
        review_filter_applied: true,
        alt_library_item_opened: true,
        alt_library_edit_started: true,
        alt_library_edit_saved: true,
        upgrade_cta_clicked: true,
        upgrade_modal_opened: true,
        upgrade_modal_closed: true,
        upgrade_started: true,
        low_credits_banner_shown: true,
        out_of_credits_banner_shown: true,
        needs_attention_banner_shown: true,
        milestone_banner_shown: true,
        trial_complete_state_shown: true,
        logged_out_conversion_state_shown: true
    };

    function baseProps() {
        var c = cfg.context || {};
        return {
            page: c.page_variant || c.page || 'unknown',
            client_page: c.page || 'unknown',
            page_variant: c.page_variant || c.page || 'unknown',
            plan_type: c.plan_type,
            plugin_version: c.plugin_version
        };
    }

    function getUiSource(node) {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.resolveSource === 'function') {
            return window.bbaiAnalytics.resolveSource(node);
        }

        return (cfg.context && (cfg.context.page_variant || cfg.context.page)) || 'dashboard';
    }

    function buildPostHogProps(props) {
        var payload = $.extend({}, props || {});
        delete payload.client_page;
        delete payload.page_variant;
        return payload;
    }

    function track(eventName, properties) {
        if (!eventName || !/^[a-z0-9_]{1,80}$/.test(eventName)) {
            return;
        }
        var props = $.extend({}, baseProps(), properties || {});
        if (posthogAllowlist[eventName] && window.bbaiTrack && typeof window.bbaiTrack === 'function') {
            try {
                window.bbaiTrack(eventName, buildPostHogProps(props));
            } catch (posthogError) {
                // Ignore PostHog wrapper failures.
            }
        }
        queue.push({ event: eventName, properties: props });
        scheduleFlush();
    }

    function scheduleFlush() {
        if (flushTimer) {
            return;
        }
        flushTimer = window.setTimeout(flushNow, 1200);
    }

    function flushNow() {
        flushTimer = null;
        if (!queue.length || !cfg.ajaxUrl || !cfg.nonce) {
            return;
        }
        var batch = queue.splice(0, 25);
        $.post(cfg.ajaxUrl, {
            action: cfg.action || 'beepbeepai_telemetry',
            nonce: cfg.nonce,
            events: JSON.stringify(batch)
        }).fail(function () {
            queue = batch.concat(queue);
        });
    }

    function mapPageToViewEvent(pageKey, pageVariant) {
        if (pageKey === 'onboarding') {
            return 'onboarding_viewed';
        }

        var map = {
            dashboard: 'dashboard_viewed',
            guest_dashboard: 'guest_dashboard_viewed',
            alt_library: 'alt_library_viewed',
            analytics: 'analytics_viewed',
            usage: 'usage_viewed',
            settings: 'settings_viewed',
            help: 'settings_viewed'
        };

        if (pageVariant === 'guest_dashboard') {
            return 'guest_dashboard_viewed';
        }

        return map[pageKey] || 'dashboard_viewed';
    }

    function sendPageView() {
        if (pageViewSent) {
            return;
        }
        pageViewSent = true;
        var c = cfg.context || {};
        var pk = c.page || 'unknown';
        var pageVariant = c.page_variant || pk;
        track('plugin_opened', {
            navigation: 'direct',
            page: pageVariant
        });
        track(mapPageToViewEvent(pk, pageVariant), {
            navigation: 'direct',
            page: pageVariant
        });
        if ((c.days_since_last_active || 0) > 0) {
            track('returning_user_session', {
                days_since_last_active: c.days_since_last_active,
                images_processed_per_session: c.images_processed_session || 0
            });
        }
    }

    function bindReviewNavigation() {
        $(document).on('click', '[data-bbai-navigation="review-results"], [data-bbai-quick-action="review-weak"], [data-bbai-workflow-review-cta], [data-bbai-review-scroll="1"], a[href*="page=bbai-library"][href*="status=needs_review"]', function () {
            track('review_alt_clicked', {
                source: getUiSource(this)
            });
        });
    }

    function mapBannerStateToEvent(state) {
        var normalizedState = (state || '').toLowerCase();
        if (normalizedState === 'low_credits') {
            return 'low_credits_banner_shown';
        }
        if (normalizedState === 'out_of_credits') {
            return 'out_of_credits_banner_shown';
        }
        if (normalizedState === 'needs_attention') {
            return 'needs_attention_banner_shown';
        }
        if (normalizedState === 'first_success') {
            return 'milestone_banner_shown';
        }
        return '';
    }

    function getPrimaryBannerState(hero) {
        if (!hero) {
            return '';
        }

        return hero.getAttribute('data-bbai-primary-banner-state')
            || hero.getAttribute('data-bbai-banner-state')
            || hero.getAttribute('data-hero-variant')
            || hero.getAttribute('data-state')
            || '';
    }

    function bindBannerTelemetry() {
        var hero = document.querySelector('[data-bbai-primary-banner-state], [data-bbai-analytics-hero], .bbai-command-hero, .bbai-status-banner');
        if (!hero || !window.IntersectionObserver) {
            return;
        }
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var variant = getPrimaryBannerState(hero) || 'unknown';
                var mappedEvent = mapBannerStateToEvent(variant);
                track('banner_shown', {
                    banner_state: variant,
                    source: getUiSource(hero)
                });
                if (mappedEvent) {
                    track(mappedEvent, {
                        source: getUiSource(hero)
                    });
                }
                obs.disconnect();
            });
        }, { threshold: 0.25 });
        obs.observe(hero);
        $(document).on('click', '.bbai-command-hero a, .bbai-status-banner a, [data-bbai-banner-cta]', function () {
            var h = document.querySelector('[data-bbai-primary-banner-state], .bbai-command-hero, .bbai-status-banner');
            var variant = getPrimaryBannerState(h) || 'unknown';
            track('banner_cta_clicked', {
                banner_state: variant,
                source: getUiSource(this)
            });
        });
    }

    function bindNavCapture() {
        $(document).on('click', 'a[href*="page=bbai"]', function () {
            var href = $(this).attr('href') || '';
            var from = (cfg.context && cfg.context.page) || 'unknown';
            var to = 'unknown';
            if (href.indexOf('page=bbai-library') !== -1) {
                to = 'alt_library';
            } else if (href.indexOf('page=bbai-analytics') !== -1) {
                to = 'analytics';
            } else if (href.indexOf('page=bbai-credit-usage') !== -1) {
                to = 'usage';
            } else if (href.indexOf('page=bbai-settings') !== -1 || href.indexOf('page=bbai-debug') !== -1) {
                to = 'settings';
            } else if (href.indexOf('page=bbai') !== -1) {
                to = 'dashboard';
            }
            var key = from + '>' + to;
            if (navSent[key]) {
                return;
            }
            navSent[key] = true;
            track('navigation_click', { from_page: from, to_page: to });
            if (from === 'dashboard' && to === 'alt_library') {
                track('navigation_transition', { from: 'dashboard', to: 'alt_library' });
            }
        });
    }

    function bindUpgradeUi() {
        $(document).on('click', '[data-action="show-upgrade-modal"]', function () {
            var el = this;
            track('upgrade_cta_clicked', {
                source: getUiSource(el),
                trigger: el.getAttribute('data-bbai-locked-source') || 'ui'
            });
        });
        $(document).on('click', 'a[href*="beepbeep.ai"], a[href*="stripe"], a[href*="pricing"]', function () {
            var t = this;
            track('upgrade_cta_clicked', {
                source: getUiSource(t),
                trigger: (t.getAttribute('class') || '').indexOf('bbai') !== -1 ? 'bbai_link' : 'generic'
            });
        });
    }

    function bindLibraryFilters() {
        // Review filter telemetry is emitted from the library workspace controller so
        // programmatic filter changes and CTA-driven filter jumps share one event path.
    }

    function bindRowActions() {
        $(document).on('click', '[data-action="generate-missing"], [data-action="regenerate-selected"], button[id="bbai-batch-regenerate"]', function () {
            track('alt_generate_clicked', { scope: 'bulk_control' });
        });
        $(document).on('click', '[data-action="regenerate"], [data-action="inline-generate"], .bbai-regenerate-alt', function () {
            track('row_action_clicked', { action: 'regenerate' });
            track('alt_generate_clicked', { scope: 'row', action: 'regenerate' });
        });
        $(document).on('click', '.bbai-library-pagination a, .bbai-pagination a, .tablenav-pages a', function () {
            track('pagination_used', { area: 'library_or_table' });
        });
    }

    function bindRetentionTelemetry() {
        var nodes = document.querySelectorAll('[data-bbai-retention-strip]');
        if (!nodes.length || !window.IntersectionObserver) {
            return;
        }
        nodes.forEach(function (strip) {
            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting || strip.getAttribute('data-bbai-retention-tel-sent')) {
                        return;
                    }
                    strip.setAttribute('data-bbai-retention-tel-sent', '1');
                    var surface = strip.getAttribute('data-bbai-telemetry-retention') || 'unknown';
                    var props = {};
                    try {
                        props = JSON.parse(strip.getAttribute('data-bbai-retention-telemetry') || '{}') || {};
                    } catch (e) {
                        props = {};
                    }
                    track('retention_reentry_strip_viewed', $.extend({ surface: surface }, props));
                    obs.disconnect();
                });
            }, { threshold: 0.2 });
            obs.observe(strip);
        });
        $(document).on('click', '.bbai-retention-strip a, .bbai-retention-library-nudge a', function () {
            var strip = this.closest('[data-bbai-retention-strip]');
            var surface = strip ? (strip.getAttribute('data-bbai-telemetry-retention') || 'unknown') : 'unknown';
            var trigger = strip ? (strip.getAttribute('data-bbai-retention-trigger') || '') : '';
            var label = ($(this).text() || '').trim().slice(0, 120);
            track('retention_strip_cta_clicked', {
                surface: surface,
                trigger: trigger || 'library_nudge',
                cta_label: label
            });
        });
    }

    function bindDismiss() {
        $(document).on('click', '.notice.is-dismissible .notice-dismiss, [data-bbai-dismiss-banner]', function () {
            track('banner_dismissed', { area: 'admin_notice' });
        });
    }

    function bindManualEditSignal() {
        $(document).on('blur', 'textarea.bbai-alt-editor, textarea[name*="alt"], .bbai-inline-alt-field textarea', function () {
            var $t = $(this);
            if ($t.data('bbaiTelemetryEdit')) {
                return;
            }
            var orig = $t.data('bbaiOrigAlt');
            if (typeof orig === 'undefined') {
                $t.data('bbaiOrigAlt', $t.val());
                return;
            }
            if (String($t.val()) !== String(orig)) {
                $t.data('bbaiTelemetryEdit', 1);
                track('manual_edit_used', { context: 'alt_field' });
            }
        });
    }

    function bindCustomEvents() {
        document.addEventListener('bbai:analytics', function (e) {
            var d = e.detail || {};
            var eventName = d.event;
            if (!d.event) {
                return;
            }
            delete d.event;
            track(eventName, d);
        });
    }

    window.bbaiTelemetry = {
        track: track,
        flush: flushNow
    };

    $(function () {
        try {
            var nav = window.performance && window.performance.timing;
            var loadMs = 0;
            if (nav && nav.navigationStart && nav.loadEventEnd && nav.loadEventEnd > nav.navigationStart) {
                loadMs = nav.loadEventEnd - nav.navigationStart;
            }
            if (loadMs > 0 && loadMs < 120000) {
                track('page_load_time', { load_time_ms: loadMs });
            }
        } catch (e) {
            // ignore
        }
        sendPageView();
        bindNavCapture();
        bindUpgradeUi();
        bindLibraryFilters();
        bindRowActions();
        bindReviewNavigation();
        bindRetentionTelemetry();
        bindBannerTelemetry();
        bindDismiss();
        bindManualEditSignal();
        bindCustomEvents();
    });

    $(window).on('beforeunload', function () {
        flushNow();
    });
})(jQuery);
