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
    var upgradeClickFallbackBound = false;
    var analyticsFeatureBound = false;
    var wooFeatureBound = false;
    var generateCtaVisibilityBound = false;
    var generateCtaShownSent = false;
    var generationRunStartedAt = 0;
    var upgradeEventDedup = {
        key: '',
        at: 0
    };
    var ctaEventDedup = {
        key: '',
        at: 0
    };
    var featureEventDedup = {};
    var lastFeatureUsage = {
        feature: '',
        feature_context: '',
        source_page: '',
        at: 0
    };
    var upgradeAttributionState = {
        trigger_feature: 'unknown',
        trigger_location: 'unknown',
        source_page: 'unknown',
        current_plan: '',
        remaining_credits: '',
        target_plan: '',
        at: 0
    };
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
        signup_failed: true,
        email_sent: true,
        email_opened: true,
        email_clicked: true,
        scan_started: true,
        scan_completed: true,
        generation_cta_clicked: true,
        generation_request_started: true,
        generation_job_created: true,
        generation_started: true,
        generate_cta_shown: true,
        generate_cta_clicked: true,
        generation_click_noop: true,
        generation_progress_updated: true,
        generation_backgrounded: true,
        generation_resumed: true,
        generation_resume_success: true,
        generation_recovery_failed: true,
        generation_completed: true,
        generation_stuck: true,
        generation_recovered: true,
        generation_stuck_recovered: true,
        generation_failed: true,
        batch_generation_started: true,
        batch_generation_completed: true,
        bulk_generation_poll_started: true,
        bulk_generation_first_item_completed: true,
        batch_generation_quota_limit_hit: true,
        batch_generation_partial_quota_stop: true,
        review_started: true,
        review_queue_opened: true,
        review_item_approved: true,
        review_item_rejected: true,
        review_saved: true,
        alt_regenerated: true,
        alt_copied: true,
        alt_edited_manually: true,
        review_workflow_opened: true,
        review_alt_text_clicked: true,
        review_filter_applied: true,
        alt_library_item_opened: true,
        alt_library_generate_clicked: true,
        alt_library_regenerate_clicked: true,
        alt_library_edit_started: true,
        alt_library_edit_saved: true,
        alt_library_copy_clicked: true,
        alt_library_filter_clicked: true,
        feature_used: true,
        upgrade_clicked: true,
        upgrade_banner_shown: true,
        checkout_started: true,
        checkout_failed: true,
        payment_succeeded: true,
        pricing_viewed: true,
        upgrade_cta_clicked: true,
        upgrade_modal_opened: true,
        upgrade_modal_closed: true,
        upgrade_started: true,
        low_credits_banner_shown: true,
        out_of_credits_banner_shown: true,
        needs_attention_banner_shown: true,
        milestone_banner_shown: true,
        trial_complete_state_shown: true,
        success_state_shown: true,
        fully_optimised_state_shown: true,
        quota_exhausted_state_shown: true,
        logged_out_conversion_state_shown: true,
        dead_click_detected: true,
        rage_click_detected: true,
        ui_loading_timeout: true,
        ajax_request_failed: true,
        polling_failed: true,
        quota_blocked: true,
        auth_expired: true,
        stale_generation_state: true,
        frontend_error_captured: true,
        long_task_detected: true,
        replay_marker: true
    };

    function isDebugEnabled() {
        return !!(
            window.BBAI_DEBUG_POSTHOG === true ||
            cfg.debug_posthog === true ||
            cfg.debug === true ||
            /^(localhost|127\.0\.0\.1|\[::1\])$/.test(window.location.hostname || '')
        );
    }

    window.bbaiTelemetrySeen = window.bbaiTelemetrySeen || new Set();
    window.bbaiCurrentGenerationRunId = window.bbaiCurrentGenerationRunId || '';

    function bbaiTrackOnce(eventName, props, key) {
        var seenKey = String(key || eventName || '');
        if (!seenKey) {
            return;
        }
        try {
            if (window.bbaiTelemetrySeen.has(seenKey)) {
                return;
            }
            window.bbaiTelemetrySeen.add(seenKey);
        } catch (e) {
            // Fail open if Set is unavailable.
        }
        track(eventName, props || {});
    }

    function getAnalyticsContext() {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.getContext === 'function') {
            return window.bbaiAnalytics.getContext() || {};
        }

        if (window.BBAI_POSTHOG && window.BBAI_POSTHOG.context) {
            return window.BBAI_POSTHOG.context || {};
        }

        return {};
    }

    function readNumber(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? NaN : parsed;
    }

    function readString(value) {
        return value === undefined || value === null ? '' : String(value);
    }

    function normalizeEventName(eventName) {
        var name = readString(eventName);
        var map = {
            generation_start_failed: 'generation_failed',
            generation_batch_progress: 'generation_progress_updated',
            bulk_generation_stalled_fallback_shown: 'generation_stuck',
            bulk_generation_minimized: 'generation_backgrounded',
            bulk_generation_started: 'batch_generation_started',
            bulk_generation_completed: 'batch_generation_completed',
            review_alt_clicked: 'review_alt_text_clicked',
            alt_generate_clicked: 'alt_library_generate_clicked',
            row_action_clicked: 'alt_library_regenerate_clicked',
            review_filter_applied: 'alt_library_filter_clicked',
            pricing_viewed_logged_out: 'pricing_viewed',
            pricing_viewed_authenticated: 'pricing_viewed',
            pricing_viewed_from_trial: 'pricing_viewed',
            upgrade_completed: 'payment_succeeded'
        };

        return map[name] || name;
    }

    function isSensitiveKey(key) {
        var raw = readString(key);
        var normalized = raw.toLowerCase();
        var compact = normalized.replace(/[^a-z0-9]/g, '');

        if (normalized === 'license_key_present' || compact === 'licensekeypresent') {
            return false;
        }

        return (
            /(^|_)(password|token|jwt|secret|nonce|api[_-]?key|license[_-]?key|authorization|auth_header|email|site[_-]?url)($|_)/.test(normalized) ||
            /^(password|token|jwt|secret|nonce|apikey|licensekey|authorization|authheader|email|siteurl)$/.test(compact) ||
            /email$/.test(compact)
        );
    }

    function isLicenseKeyName(key) {
        var normalized = readString(key).toLowerCase();
        return normalized === 'license_key' || normalized.replace(/[^a-z0-9]/g, '') === 'licensekey';
    }

    function sanitizeScalarValue(value) {
        if (typeof value !== 'string') {
            return value;
        }

        return value
            .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[redacted_email]')
            .replace(/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g, '[redacted_jwt]')
            .replace(/https?:\/\/[^\s"'<>]+/gi, '[redacted_url]');
    }

    function sanitizeAnalyticsPayload(input) {
        var output = {};

        Object.keys(input || {}).forEach(function(key) {
            var value = input[key];

            if (value === undefined || value === null || value === '') {
                return;
            }

            if (isSensitiveKey(key)) {
                if (isLicenseKeyName(key) && value) {
                    output.license_key_present = true;
                }
                return;
            }

            output[key] = sanitizeScalarValue(value);
        });

        return output;
    }

    function normalizePageName(pageName) {
        var normalized = readString(pageName).toLowerCase();

        if (!normalized) {
            return 'unknown';
        }

        if (normalized === 'guest_dashboard') {
            return 'dashboard';
        }

        if (normalized === 'library') {
            return 'alt_library';
        }

        return normalized;
    }

    function getRuntimeUsage() {
        var candidates = [
            window.BBAI_DASH && (window.BBAI_DASH.usage || window.BBAI_DASH.initialUsage),
            window.BBAI && (window.BBAI.usage || window.BBAI.initialUsage),
            window.BBAI_UPGRADE && window.BBAI_UPGRADE.usage
        ];
        var i;
        var usage;

        for (i = 0; i < candidates.length; i++) {
            usage = candidates[i];
            if (usage && typeof usage === 'object') {
                return usage;
            }
        }

        return null;
    }

    function getRuntimeStats() {
        var context = getAnalyticsContext();
        var candidates = [
            window.BBAI_DASH && (window.BBAI_DASH.stats || window.BBAI_DASH.initialStats),
            window.BBAI && (window.BBAI.stats || window.BBAI.initialStats),
            window.BBAI_UPGRADE && window.BBAI_UPGRADE.stats
        ];
        var i;
        var stats;

        for (i = 0; i < candidates.length; i++) {
            stats = candidates[i];
            if (stats && typeof stats === 'object') {
                return stats;
            }
        }

        return context || {};
    }

    function queryNumberAttr(selectors, attrs) {
        var nodes = document.querySelectorAll(selectors);
        var i;
        var j;
        var value;

        for (i = 0; i < nodes.length; i++) {
            for (j = 0; j < attrs.length; j++) {
                value = readNumber(nodes[i].getAttribute(attrs[j]));
                if (!isNaN(value)) {
                    return Math.max(0, value);
                }
            }
        }

        return NaN;
    }

    function getRemainingCredits() {
        var context = getAnalyticsContext();
        var usage = getRuntimeUsage();
        var remaining = readNumber(context.quota_remaining);

        if (isNaN(remaining) && usage) {
            remaining = readNumber(usage.remaining);
            if (isNaN(remaining) && usage.quota) {
                remaining = readNumber(usage.quota.remaining);
            }
            if (isNaN(remaining)) {
                var limit = readNumber(usage.limit);
                var used = readNumber(usage.used);
                if (!isNaN(limit) && !isNaN(used)) {
                    remaining = Math.max(0, limit - used);
                }
            }
        }

        if (isNaN(remaining) && cfg.context) {
            remaining = readNumber(cfg.context.quota_remaining);
        }

        return isNaN(remaining) ? '' : Math.max(0, remaining);
    }

    function getCreditsUsed() {
        var context = getAnalyticsContext();
        var usage = getRuntimeUsage();
        var used = readNumber(context.credits_used || context.quota_used);

        if (isNaN(used) && usage) {
            used = readNumber(usage.used);
            if (isNaN(used) && usage.quota) {
                used = readNumber(usage.quota.used);
            }
        }

        if (isNaN(used) && cfg.context) {
            used = readNumber(cfg.context.credits_used || cfg.context.quota_used);
        }

        return isNaN(used) ? '' : Math.max(0, used);
    }

    function getMonthlyLimit() {
        var context = getAnalyticsContext();
        var usage = getRuntimeUsage();
        var limit = readNumber(context.monthly_limit || context.quota_limit);

        if (isNaN(limit) && usage) {
            limit = readNumber(usage.limit);
            if (isNaN(limit) && usage.quota) {
                limit = readNumber(usage.quota.limit);
            }
        }

        if (isNaN(limit) && cfg.context) {
            limit = readNumber(cfg.context.monthly_limit || cfg.context.quota_limit);
        }

        return isNaN(limit) ? '' : Math.max(0, limit);
    }

    function getDailyUsageValue(key) {
        var context = getAnalyticsContext();
        var usage = getRuntimeUsage();
        var value = readNumber(context[key]);

        if (isNaN(value) && usage) {
            value = readNumber(usage[key] || (usage.daily && usage.daily[key.replace('daily_', '')]));
        }

        if (isNaN(value) && cfg.context) {
            value = readNumber(cfg.context[key]);
        }

        return isNaN(value) ? '' : Math.max(0, value);
    }

    function getCurrentPlan() {
        var context = getAnalyticsContext();
        var usage = getRuntimeUsage();
        var plan = readString(context.plan_type || context.plan);

        if (!plan && usage) {
            plan = readString(usage.plan_type || usage.plan || (usage.quota && usage.quota.plan_type));
        }

        if (!plan && cfg.context) {
            plan = readString(cfg.context.plan_type || cfg.context.plan);
        }

        return plan || 'unknown';
    }

    function getCountValue(primaryKeys, attrNames) {
        var context = getAnalyticsContext();
        var stats = getRuntimeStats();
        var i;
        var value;

        for (i = 0; i < primaryKeys.length; i++) {
            value = readNumber(context[primaryKeys[i]]);
            if (!isNaN(value)) {
                return Math.max(0, value);
            }
            value = readNumber(stats[primaryKeys[i]]);
            if (!isNaN(value)) {
                return Math.max(0, value);
            }
        }

        value = queryNumberAttr(
            '[data-bbai-dashboard-root="1"], [data-bbai-library-workspace-root="1"], [data-bbai-li-initial-state]',
            attrNames
        );

        return isNaN(value) ? '' : Math.max(0, value);
    }

    function buildActivationSnapshot() {
        var context = getAnalyticsContext();
        var remaining = getRemainingCredits();
        var dailyUsed = getDailyUsageValue('daily_used');
        var dailyRemaining = getDailyUsageValue('daily_remaining');

        return sanitizeAnalyticsPayload({
            current_page: normalizePageName((cfg.context && (cfg.context.page_variant || cfg.context.page)) || context.page || getSourcePage()),
            user_plan: getCurrentPlan(),
            is_logged_in: !!(context.is_logged_in || (cfg.context && cfg.context.is_logged_in)),
            is_trial_user: !!(context.trial_exhausted !== undefined || context.remaining_free_images !== undefined || (cfg.context && (cfg.context.trial_exhausted !== undefined || cfg.context.remaining_free_images !== undefined))),
            credits_used: getCreditsUsed(),
            credits_remaining: remaining,
            daily_used: dailyUsed,
            daily_remaining: dailyRemaining,
            monthly_limit: getMonthlyLimit(),
            missing_count: getCountValue(['missing_count', 'missing_alt_count', 'images_missing_alt'], ['data-bbai-missing-count', 'data-bbai-images-missing-alt']),
            needs_review_count: getCountValue(['needs_review_count', 'review_count', 'ready_for_review_count'], ['data-bbai-weak-count', 'data-bbai-needs-review-count', 'data-bbai-ready-for-review-count']),
            optimized_count: getCountValue(['optimized_count', 'images_optimized'], ['data-bbai-optimized-count']),
            site_hash: context.site_hash || (cfg.context && cfg.context.site_hash) || '',
            wp_version: context.wp_version || (cfg.context && cfg.context.wp_version) || '',
            plugin_version: context.plugin_version || (cfg.context && cfg.context.plugin_version) || ''
        });
    }

    function getSourcePage(properties) {
        var props = properties && typeof properties === 'object' ? properties : {};
        return normalizePageName(
            props.source_page ||
            props.page ||
            props.page_variant ||
            (cfg.context && (cfg.context.page_variant || cfg.context.page)) ||
            getAnalyticsContext().page
        );
    }

    function getTelemetryRuntimeContext() {
        var context = getAnalyticsContext();
        var identity = getPostHogIdentityContext();

        return {
            account_id: identity.account_id || '',
            user_id: identity.user_id || '',
            license_key_present: !!identity.license_key_present,
            site_id: identity.site_id || '',
            site_hash: identity.site_hash || '',
            current_plan: getCurrentPlan(),
            remaining_credits: getRemainingCredits(),
            source_page: getSourcePage(),
            plugin_version: readString(
                context.plugin_version ||
                (cfg.context && cfg.context.plugin_version)
            )
        };
    }

    function getPostHogIdentityContext() {
        var context = getAnalyticsContext();

        return {
            account_id: context.account_id || '',
            user_id: context.user_id || '',
            license_key_present: !!context.license_key_present,
            site_id: context.site_id || '',
            site_hash: context.site_hash || '',
            wordpress_user_id: context.wordpress_user_id || ''
        };
    }

    function baseProps() {
        var c = cfg.context || {};
        var runtime = getTelemetryRuntimeContext();
        return $.extend({
            page: c.page_variant || c.page || 'unknown',
            client_page: c.page || 'unknown',
            page_variant: c.page_variant || c.page || 'unknown',
            plan_type: runtime.current_plan || c.plan_type,
            plugin_version: runtime.plugin_version || c.plugin_version
        }, getPostHogIdentityContext());
    }

    function getUiSource(node) {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.resolveSource === 'function') {
            return window.bbaiAnalytics.resolveSource(node);
        }

        return (cfg.context && (cfg.context.page_variant || cfg.context.page)) || 'dashboard';
    }

    function buildPostHogProps(props) {
        var payload = sanitizeAnalyticsPayload($.extend({}, props || {}));
        delete payload.client_page;
        delete payload.page_variant;
        return payload;
    }

    function bbaiTrack(event, props) {
        try {
            if (window.bbaiTrack && window.bbaiTrack !== bbaiTrack && typeof window.bbaiTrack === 'function') {
                window.bbaiTrack(event, props || {});
            }
        } catch (e) {
            if (isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[BBAI] PostHog tracking failed', e);
            }
        }
    }

    function cleanupProps(payload) {
        var cleaned = {};
        Object.keys(payload || {}).forEach(function(key) {
            if (payload[key] === undefined || payload[key] === null || payload[key] === '') {
                return;
            }
            if (isSensitiveKey(key)) {
                if (isLicenseKeyName(key) && payload[key]) {
                    cleaned.license_key_present = true;
                }
                return;
            }
            cleaned[key] = sanitizeScalarValue(payload[key]);
        });
        return cleaned;
    }

    function normalizeFeatureContext(value, fallbackPage) {
        var signal = readString(value).toLowerCase();
        var pageFallback = normalizePageName(fallbackPage || getSourcePage());

        if (
            pageFallback === 'modal' ||
            pageFallback === 'banner' ||
            pageFallback === 'onboarding' ||
            pageFallback === 'settings' ||
            pageFallback === 'usage' ||
            pageFallback === 'help' ||
            pageFallback === 'other' ||
            pageFallback === 'unknown'
        ) {
            pageFallback = 'dashboard';
        }

        if (!signal || signal === 'modal' || signal === 'banner') {
            return pageFallback;
        }
        if (signal === 'library') {
            return 'alt_library';
        }
        if (signal.indexOf('woo') !== -1 || signal.indexOf('product') !== -1 || signal.indexOf('gallery') !== -1) {
            return 'woocommerce';
        }
        if (signal.indexOf('analytic') !== -1) {
            return 'analytics';
        }
        if (
            signal.indexOf('review') !== -1 ||
            signal.indexOf('edit') !== -1 ||
            signal.indexOf('approve') !== -1 ||
            signal.indexOf('manual') !== -1 ||
            signal.indexOf('weak') !== -1
        ) {
            return 'alt_library';
        }
        if (signal.indexOf('media') !== -1) {
            return 'media_library';
        }
        if (signal.indexOf('library') !== -1) {
            return 'alt_library';
        }
        if (
            signal.indexOf('queue') !== -1 ||
            signal.indexOf('selection') !== -1 ||
            signal.indexOf('batch') !== -1
        ) {
            return pageFallback === 'alt_library' ? 'alt_library' : pageFallback;
        }
        if (signal.indexOf('dashboard') !== -1) {
            return 'dashboard';
        }

        return pageFallback;
    }

    function inferGenerationFeature(props) {
        var requestedCount = readNumber(props.requested_count);
        var mode = readString(props.generation_mode || props.trigger || props.reason).toLowerCase();
        var featureContext = normalizeFeatureContext(props.feature_context || props.source || props.location, props.source_page || props.page);

        if (featureContext === 'woocommerce') {
            return 'woocommerce_optimisation';
        }

        if (
            (!isNaN(requestedCount) && requestedCount > 1) ||
            /bulk|batch|selected|generate-missing|generate_missing|regenerate-selected|reoptimize|fix-all-issues/.test(mode)
        ) {
            return 'bulk_generation';
        }

        return 'alt_generation';
    }

    function getFeatureSignal(props) {
        return [
            props.trigger_feature,
            props.trigger_location,
            props.feature_context,
            props.location,
            props.reason,
            props.trigger,
            props.source,
            props.generation_mode
        ].map(readString).join(' ').toLowerCase();
    }

    function normalizeFeatureName(value) {
        var normalized = readString(value).toLowerCase();

        if (!normalized) {
            return '';
        }

        if (normalized === 'alt_generation' || normalized === 'alt-generation' || normalized === 'generation' || normalized === 'generate') {
            return 'alt_generation';
        }
        if (normalized === 'bulk_generation' || normalized === 'bulk-generation' || normalized === 'bulk') {
            return 'bulk_generation';
        }
        if (normalized === 'review_workflow' || normalized === 'review-workflow' || normalized === 'review') {
            return 'review_workflow';
        }
        if (normalized === 'analytics') {
            return 'analytics';
        }
        if (
            normalized === 'woocommerce_optimisation' ||
            normalized === 'woocommerce-optimisation' ||
            normalized === 'woocommerce_optimization' ||
            normalized === 'woocommerce-optimization' ||
            normalized === 'woocommerce'
        ) {
            return 'woocommerce_optimisation';
        }

        return '';
    }

    function getNodeSignal(node) {
        if (!node || typeof node.getAttribute !== 'function') {
            return '';
        }

        return [
            node.getAttribute('data-bbai-trigger-feature'),
            node.getAttribute('data-bbai-feature'),
            node.getAttribute('data-bbai-feature-context'),
            node.getAttribute('data-bbai-lock-reason'),
            node.getAttribute('data-bbai-locked-source'),
            node.getAttribute('data-bbai-upgrade-location'),
            node.getAttribute('data-bbai-generation-source'),
            node.getAttribute('data-bbai-regenerate-scope'),
            node.getAttribute('data-bbai-review-action'),
            node.getAttribute('data-bbai-action'),
            node.getAttribute('data-action'),
            node.getAttribute('aria-label'),
            node.getAttribute('title'),
            node.getAttribute('href'),
            node.getAttribute('id'),
            readString(node.className),
            readString(node.textContent)
        ].map(readString).join(' ').toLowerCase();
    }

    function getButtonText(node) {
        if (!node || typeof node.getAttribute !== 'function') {
            return '';
        }

        return readString(
            node.getAttribute('data-bbai-analytics-label') ||
            node.getAttribute('aria-label') ||
            node.getAttribute('title') ||
            node.textContent ||
            node.value ||
            ''
        ).replace(/\s+/g, ' ').trim().slice(0, 160);
    }

    function getButtonLocation(node) {
        if (!node || typeof node.getAttribute !== 'function') {
            return getSourcePage();
        }

        return readString(
            node.getAttribute('data-bbai-button-location') ||
            node.getAttribute('data-bbai-upgrade-location') ||
            node.getAttribute('data-bbai-locked-source') ||
            node.getAttribute('data-bbai-generation-source') ||
            getUiSource(node)
        ) || getSourcePage();
    }

    function getDisabledReason(node) {
        var signal = getNodeSignal(node);

        if (!node) {
            return '';
        }
        if (node.disabled || node.getAttribute('disabled') !== null || node.getAttribute('aria-disabled') === 'true') {
            if (/credit|quota|limit|exhaust/.test(signal)) {
                return 'no_credits';
            }
            if (/missing|complete|all clear|no images|no image/.test(signal)) {
                return 'no_missing_images';
            }
            if (/nonce/.test(signal)) {
                return 'missing_nonce';
            }
            return 'disabled_button';
        }
        if (/credit|quota|limit|exhaust/.test(signal)) {
            return 'no_credits';
        }
        if (/missing|complete|all clear|no images|no image/.test(signal)) {
            return 'no_missing_images';
        }

        return '';
    }

    function buildCtaProps(node, overrides) {
        var props = $.extend({}, buildActivationSnapshot(), {
            button_text: getButtonText(node),
            button_location: getButtonLocation(node),
            source: getUiSource(node),
            current_page: resolveNodeSourcePage(node)
        }, overrides || {});

        return cleanupProps(props);
    }

    function buildGenerationDefaults(props) {
        var payload = props || {};
        var now = Date.now();
        var duration = readNumber(payload.duration_ms);

        if (payload.credits_before === undefined || payload.credits_before === null || payload.credits_before === '') {
            payload.credits_before = payload.credits_remaining !== undefined ? payload.credits_remaining : getRemainingCredits();
        }
        if (payload.credits_after === undefined || payload.credits_after === null || payload.credits_after === '') {
            payload.credits_after = payload.credits_remaining !== undefined ? payload.credits_remaining : getRemainingCredits();
        }
        if (isNaN(duration) && generationRunStartedAt > 0) {
            duration = Math.max(0, now - generationRunStartedAt);
        }

        return cleanupProps($.extend({
            generation_mode: 'batch',
            requested_count: 0,
            processed_count: 0,
            failed_count: 0,
            credits_before: getRemainingCredits(),
            credits_after: getRemainingCredits(),
            duration_ms: isNaN(duration) ? 0 : duration,
            endpoint: payload.endpoint || payload.ajax_action || '',
            response_status: payload.response_status || '',
            error_code: payload.error_code || '',
            error_message: payload.error_message || ''
        }, payload));
    }

    function resolveNodeSourcePage(node) {
        var sourcePage = normalizePageName(getUiSource(node));

        if (
            !sourcePage ||
            sourcePage === 'modal' ||
            sourcePage === 'banner' ||
            sourcePage === 'onboarding' ||
            sourcePage === 'other' ||
            sourcePage === 'unknown'
        ) {
            sourcePage = getSourcePage();
        }

        return normalizePageName(sourcePage || getSourcePage());
    }

    function inferFeatureFromSignal(signal, fallbackPage, featureContext, allowPageFallback) {
        var sourcePage = normalizePageName(fallbackPage || getSourcePage());
        var context = normalizeFeatureContext(featureContext || signal, sourcePage);

        if (/woo|product|variation|catalog|gallery/.test(signal)) {
            return 'woocommerce_optimisation';
        }
        if (/analytic|coverage|trend|chart|progress/.test(signal)) {
            return 'analytics';
        }
        if (/review|approve|manual|edit|weak/.test(signal)) {
            return 'review_workflow';
        }
        if (
            /bulk|batch|generate[_ -]?missing|reoptimi[sz]e[_ -]?all|regenerate[_ -]?(all|selected)|selected|selection|queue/.test(signal)
        ) {
            return 'bulk_generation';
        }
        if (/automation|generate|regenerate|upload|alt text|improve/.test(signal)) {
            return 'alt_generation';
        }
        if (!allowPageFallback) {
            return '';
        }
        if (context === 'analytics' || sourcePage === 'analytics') {
            return 'analytics';
        }
        if (context === 'woocommerce' || sourcePage === 'woocommerce') {
            return 'woocommerce_optimisation';
        }
        if (context === 'alt_library' || sourcePage === 'alt_library') {
            return 'bulk_generation';
        }

        return 'alt_generation';
    }

    function inferFeatureFromNode(node, fallbackPage, featureContext) {
        var explicitFeature = '';

        if (node && typeof node.getAttribute === 'function') {
            explicitFeature = normalizeFeatureName(
                node.getAttribute('data-bbai-trigger-feature') ||
                node.getAttribute('data-bbai-feature') ||
                ''
            );
        }

        if (explicitFeature) {
            return explicitFeature;
        }

        return inferFeatureFromSignal(getNodeSignal(node), fallbackPage, featureContext, true);
    }

    function getRecentFeatureUsage(maxAgeMs) {
        var age = Date.now() - lastFeatureUsage.at;

        if (!lastFeatureUsage.feature || age > (maxAgeMs || (15 * 60 * 1000))) {
            return null;
        }

        return lastFeatureUsage;
    }

    function inferTriggerFeature(props, prior) {
        var signal = getFeatureSignal(props);
        var recentFeature = getRecentFeatureUsage();
        var explicitFeature = normalizeFeatureName(props.trigger_feature) || readString(props.trigger_feature);
        var sourcePage = getSourcePage(props);
        var featureContext = normalizeFeatureContext(props.feature_context || signal, sourcePage);
        var signalledFeature = inferFeatureFromSignal(signal, sourcePage, featureContext, false);

        if (explicitFeature) {
            return explicitFeature;
        }

        if (signalledFeature) {
            return signalledFeature;
        }
        if (prior && prior.trigger_feature && prior.trigger_feature !== 'unknown') {
            return prior.trigger_feature;
        }
        if (recentFeature && recentFeature.feature) {
            return recentFeature.feature;
        }

        return inferFeatureFromSignal(signal, sourcePage, featureContext, true) || 'unknown';
    }

    function updateLastFeatureUsage(props) {
        lastFeatureUsage = {
            feature: props.feature || '',
            feature_context: props.feature_context || '',
            source_page: props.source_page || '',
            at: Date.now()
        };
    }

    function shouldSendFeatureUsage(feature, featureContext, sourcePage) {
        var key = [feature, featureContext, sourcePage].join('|');
        var now = Date.now();
        var lastSentAt = featureEventDedup[key] || 0;

        if (lastSentAt && (now - lastSentAt) < 30000) {
            return false;
        }

        featureEventDedup[key] = now;
        return true;
    }

    function trackFeatureUsed(feature, properties) {
        var runtime = getTelemetryRuntimeContext();
        var props = cleanupProps($.extend({
            feature: feature,
            feature_context: normalizeFeatureContext(
                properties && properties.feature_context,
                properties && properties.source_page
            ),
            source_page: getSourcePage(properties),
            current_plan: runtime.current_plan,
            plugin_version: runtime.plugin_version
        }, properties || {}));

        if (!props.feature || !shouldSendFeatureUsage(props.feature, props.feature_context, props.source_page)) {
            return;
        }

        updateLastFeatureUsage(props);

        if (isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[BBAI] feature_used send', props);
        }

        track('feature_used', props);
    }

    function maybeEmitFeatureUsageFromEvent(eventName, props) {
        var runtime = getTelemetryRuntimeContext();

        if (eventName === 'generation_started') {
            trackFeatureUsed(inferGenerationFeature(props), {
                feature_context: normalizeFeatureContext(props.source || props.location, runtime.source_page),
                source_page: getSourcePage(props)
            });
            return;
        }

        if (
            eventName === 'review_alt_clicked' ||
            eventName === 'alt_library_edit_started' ||
            eventName === 'alt_library_edit_saved'
        ) {
            trackFeatureUsed('review_workflow', {
                feature_context: 'alt_library',
                source_page: getSourcePage(props)
            });
        }
    }

    function getStoredUpgradeAttribution() {
        return $.extend({}, upgradeAttributionState);
    }

    function storeUpgradeAttribution(props) {
        upgradeAttributionState = $.extend({}, upgradeAttributionState, cleanupProps({
            trigger_feature: props.trigger_feature || 'unknown',
            trigger_location: props.trigger_location || 'unknown',
            source_page: props.source_page || 'unknown',
            current_plan: props.current_plan || '',
            remaining_credits: props.remaining_credits,
            target_plan: props.target_plan || '',
            at: Date.now()
        }));
    }

    function enrichUpgradeAttribution(eventName, props) {
        var runtime = getTelemetryRuntimeContext();
        var prior = getStoredUpgradeAttribution();
        var targetPlan = readString(props.target_plan || props.plan || prior.target_plan);
        var triggerLocation = eventName === 'checkout_started'
            ? readString(props.trigger_location || prior.trigger_location || props.location || props.source || 'unknown')
            : readString(props.trigger_location || props.location || props.source || prior.trigger_location || 'unknown');
        var enriched = cleanupProps($.extend({}, props, {
            trigger_location: triggerLocation || 'unknown',
            source_page: getSourcePage($.extend({}, props, {
                source_page: props.source_page || prior.source_page || runtime.source_page
            })),
            current_plan: readString(props.current_plan || prior.current_plan || runtime.current_plan || 'unknown'),
            remaining_credits: props.remaining_credits !== undefined && props.remaining_credits !== null && props.remaining_credits !== ''
                ? props.remaining_credits
                : (prior.remaining_credits !== undefined && prior.remaining_credits !== null && prior.remaining_credits !== ''
                    ? prior.remaining_credits
                    : runtime.remaining_credits),
            target_plan: eventName === 'checkout_started' ? (targetPlan || 'unknown') : targetPlan
        }));

        enriched.trigger_feature = inferTriggerFeature(enriched, prior);

        storeUpgradeAttribution(enriched);

        if (isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[BBAI] ' + eventName + ' attribution', {
                trigger_feature: enriched.trigger_feature,
                trigger_location: enriched.trigger_location,
                source_page: enriched.source_page,
                current_plan: enriched.current_plan,
                target_plan: enriched.target_plan || '',
                remaining_credits: enriched.remaining_credits
            });
        }

        return enriched;
    }

    function buildCheckoutRequestAttribution(overrides) {
        var runtime = getTelemetryRuntimeContext();
        var stored = getStoredUpgradeAttribution();
        var payload = cleanupProps($.extend({
            account_id: runtime.account_id || '',
            user_id: runtime.user_id || '',
            license_key_present: !!runtime.license_key_present,
            site_id: runtime.site_id || '',
            site_hash: runtime.site_hash || '',
            trigger_feature: stored.trigger_feature || 'unknown',
            trigger_location: stored.trigger_location || 'unknown',
            source_page: stored.source_page || runtime.source_page || 'unknown',
            target_plan: stored.target_plan || '',
            current_plan: stored.current_plan || runtime.current_plan || '',
            source: 'app'
        }, overrides || {}));

        if (isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[BBAI] checkout request payload', payload);
        }

        return payload;
    }

    function track(eventName, properties) {
        eventName = normalizeEventName(eventName);
        if (!eventName || !/^[a-z0-9_]{1,80}$/.test(eventName)) {
            return;
        }
        var props = sanitizeAnalyticsPayload($.extend({}, baseProps(), properties || {}));
        var isGenerationEvent = /^(generation_cta_clicked|generation_request_started|generation_job_created|generation_started|generation_progress_updated|generation_backgrounded|generation_resumed|generation_resume_success|generation_recovery_failed|generation_failed|generation_completed|generation_stuck|generation_recovered|batch_generation_started|batch_generation_completed|bulk_generation_poll_started|bulk_generation_first_item_completed)$/.test(eventName);

        if (/_clicked$/.test(eventName) || eventName === 'generate_cta_shown' || eventName === 'generation_click_noop') {
            props = cleanupProps($.extend({}, buildActivationSnapshot(), props));
        }

        if (/_clicked$/.test(eventName)) {
            var ctaDedupeKey = [
                eventName,
                props.current_page || props.page || '',
                props.button_location || props.location || props.source || '',
                props.button_text || props.trigger || props.action || ''
            ].join('|');
            var ctaNow = Date.now();

            if (ctaEventDedup.key === ctaDedupeKey && (ctaNow - ctaEventDedup.at) < 700) {
                return;
            }

            ctaEventDedup.key = ctaDedupeKey;
            ctaEventDedup.at = ctaNow;
        }

        if (isGenerationEvent) {
            if (eventName === 'generation_started' || eventName === 'batch_generation_started') {
                generationRunStartedAt = Date.now();
            }
            props = cleanupProps($.extend({}, buildActivationSnapshot(), buildGenerationDefaults(props)));
        }

        // Generation events: enforce once-per-generation-run.
        if (eventName === 'generation_started') {
            if (!window.bbaiCurrentGenerationRunId) {
                window.bbaiCurrentGenerationRunId = String(Date.now());
            }
            props.generation_run_id = props.generation_run_id || window.bbaiCurrentGenerationRunId;
            try {
                if (window.bbaiTelemetrySeen.has('generation_started:' + props.generation_run_id)) {
                    return;
                }
                window.bbaiTelemetrySeen.add('generation_started:' + props.generation_run_id);
            } catch (e) {}
        }
        if (eventName === 'generation_completed' || eventName === 'generation_failed') {
            var runId = props.generation_run_id || window.bbaiCurrentGenerationRunId || '';
            if (!runId && eventName === 'generation_failed') {
                runId = String(Date.now());
                window.bbaiCurrentGenerationRunId = runId;
            }
            if (!runId) {
                return;
            }
            props.generation_run_id = runId;
            try {
                var runKey = eventName + ':' + runId;
                if (window.bbaiTelemetrySeen.has(runKey)) {
                    return;
                }
                window.bbaiTelemetrySeen.add(runKey);
            } catch (e2) {}
        }
        if (eventName === 'batch_generation_started' || eventName === 'batch_generation_completed') {
            props.generation_run_id = props.generation_run_id || window.bbaiCurrentGenerationRunId || String(Date.now());
        }

        if (eventName === 'feature_used') {
            props = cleanupProps($.extend({}, props, {
                source_page: getSourcePage(props),
                current_plan: props.current_plan || getCurrentPlan(),
                feature_context: normalizeFeatureContext(props.feature_context || props.source || props.location, props.source_page || props.page),
                plugin_version: props.plugin_version || getTelemetryRuntimeContext().plugin_version
            }));
            updateLastFeatureUsage(props);
        } else if (eventName === 'upgrade_clicked' || eventName === 'checkout_started') {
            props = enrichUpgradeAttribution(eventName, props);
        }
        if (eventName === 'upgrade_clicked' || eventName === 'checkout_started') {
            var dedupeKey = [
                eventName,
                props.page || '',
                props.trigger_location || props.location || '',
                props.trigger_feature || props.trigger || ''
            ].join('|');
            var now = Date.now();

            if (upgradeEventDedup.key === dedupeKey && (now - upgradeEventDedup.at) < 800) {
                return;
            }

            upgradeEventDedup.key = dedupeKey;
            upgradeEventDedup.at = now;
        }
        if (eventName === 'checkout_started' && isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[BBAI] checkout_started identity context', {
                account_id: props.account_id || '',
                user_id: props.user_id || '',
                license_key_present: !!props.license_key_present,
                site_id: props.site_id || '',
                site_hash: props.site_hash || ''
            });
        }
        if (isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[BBAI analytics] ' + eventName, sanitizeAnalyticsPayload(props));
        }
        if (posthogAllowlist[eventName]) {
            bbaiTrack(eventName, buildPostHogProps(props));
        }
        queue.push({ event: eventName, properties: sanitizeAnalyticsPayload(props) });
        scheduleFlush();
        if (eventName !== 'feature_used') {
            maybeEmitFeatureUsageFromEvent(eventName, props);
        }
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
        bbaiTrackOnce('plugin_opened', {
            navigation: 'direct',
            page: pageVariant
        }, 'plugin_opened');
        bbaiTrackOnce(mapPageToViewEvent(pk, pageVariant), {
            navigation: 'direct',
            page: pageVariant
        }, 'dashboard_viewed:' + String(pageVariant || pk || 'unknown'));
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
                bbaiTrackOnce('banner_shown', {
                    banner_state: variant,
                    source: getUiSource(hero)
                }, 'banner_shown:' + variant);
                if (mappedEvent) {
                    if (mappedEvent === 'needs_attention_banner_shown' || mappedEvent === 'low_credits_banner_shown') {
                        bbaiTrackOnce(mappedEvent, { source: getUiSource(hero) }, mappedEvent);
                    } else {
                        track(mappedEvent, { source: getUiSource(hero) });
                    }
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
        var upgradeFallbackSelectors = [
            '[data-action="show-upgrade-modal"]',
            '[data-action="checkout-plan"]',
            '[data-bbai-action="open-upgrade"]',
            '[data-bbai-locked-modal-upgrade="1"]',
            '[data-upgrade-trigger="true"]',
            '.bbai-header-upgrade-btn',
            '.bbai-upgrade-cta',
            '.bbai-compare-link',
            '.bbai-upgrade-panel__btn',
            '.bbai-smart-upgrade-prompt__cta',
            '.bbai-pricing-card__btn'
        ];

        function getUpgradeBannerState(node) {
            var banner = node && typeof node.closest === 'function'
                ? node.closest('[data-bbai-primary-banner-state], [data-bbai-banner-state], .bbai-command-hero, .bbai-status-banner')
                : null;
            return banner ? (getPrimaryBannerState(banner) || '').toLowerCase() : '';
        }

        function getUpgradeLocation(node) {
            var explicitLocation = node.getAttribute('data-bbai-upgrade-location')
                || node.getAttribute('data-bbai-locked-source')
                || '';
            var bannerState = getUpgradeBannerState(node);

            if (explicitLocation) {
                return explicitLocation;
            }

            if (node.closest('#bbai-upgrade-modal')) {
                return 'upgrade_modal';
            }

            if (node.closest('#bbai-locked-upgrade-modal')) {
                return 'locked_upgrade_modal';
            }

            if (node.closest('#bbai-feature-unlock-modal')) {
                return 'feature_unlock_modal';
            }

            if (bannerState) {
                return bannerState + '_banner';
            }

            if (node.closest('[data-bbai-upgrade-panel="1"], .bbai-upgrade-panel')) {
                return 'upgrade_panel';
            }

            return getUiSource(node);
        }

        function getUpgradeTrigger(node) {
            var action = (node.getAttribute('data-action') || '').toLowerCase();
            var bbaiAction = (node.getAttribute('data-bbai-action') || '').toLowerCase();
            var plan = (node.getAttribute('data-plan') || '').toLowerCase();
            var href = (node.getAttribute('href') || '').toLowerCase();
            var intendedAction = (node.getAttribute('data-bbai-intended-action') || '').toLowerCase();
            var lockReason = (node.getAttribute('data-bbai-lock-reason') || '').toLowerCase();

            if (action === 'checkout-plan') {
                return plan === 'credits' ? 'buy_credits' : 'checkout_plan';
            }

            if (node.getAttribute('data-bbai-locked-modal-upgrade') === '1') {
                return 'locked_modal_upgrade';
            }

            if (bbaiAction === 'open-upgrade') {
                return 'locked_upgrade_cta';
            }

            if (action === 'show-upgrade-modal') {
                return 'open_upgrade_modal';
            }

            if (href.indexOf('stripe') !== -1 || href.indexOf('pricing') !== -1 || href.indexOf('beepbeep.ai') !== -1) {
                return 'pricing_link';
            }

            return intendedAction || lockReason || 'upgrade_cta';
        }

        function getUpgradeReason(node) {
            var lockReason = (node.getAttribute('data-bbai-lock-reason') || '').toLowerCase();
            var bannerState = getUpgradeBannerState(node);
            var plan = (node.getAttribute('data-plan') || '').toLowerCase();

            if (lockReason) {
                return lockReason;
            }

            if (bannerState) {
                return bannerState;
            }

            if (node.closest('#bbai-locked-upgrade-modal')) {
                return 'upgrade_required';
            }

            if (plan === 'credits') {
                return 'buy_credits';
            }

            return 'default';
        }

        function getUpgradePlan(node) {
            return (node.getAttribute('data-plan') || '')
                || (cfg.context && cfg.context.plan_type)
                || '';
        }

        function isCheckoutTarget(node) {
            var action = (node.getAttribute('data-action') || '').toLowerCase();
            var href = (node.getAttribute('href') || '').toLowerCase();

            if (action === 'checkout-plan') {
                return true;
            }

            return href.indexOf('buy.stripe.com') !== -1 ||
                href.indexOf('/checkout') !== -1 ||
                href.indexOf('checkout.stripe.com') !== -1;
        }

        function buildUpgradeIntentProps(node, overrides) {
            var location = getUpgradeLocation(node);
            var props = $.extend({
                source: getUiSource(node),
                location: location,
                trigger: getUpgradeTrigger(node),
                reason: getUpgradeReason(node),
                plan: getUpgradePlan(node),
                source_page: resolveNodeSourcePage(node),
                current_plan: getCurrentPlan(),
                remaining_credits: getRemainingCredits()
            }, overrides || {});
            var sourcePage = getSourcePage({ source_page: props.source_page || resolveNodeSourcePage(node) });
            var featureContext = normalizeFeatureContext(
                props.feature_context || getNodeSignal(node),
                sourcePage
            );

            props.source_page = sourcePage;
            props.feature_context = featureContext;
            props.trigger_location = readString(props.trigger_location || props.location || location || 'unknown');
            props.trigger_feature = normalizeFeatureName(props.trigger_feature) || inferFeatureFromNode(node, sourcePage, featureContext);

            if (!props.plan) {
                delete props.plan;
            }

            return props;
        }

        function trackUpgradeIntent(node, extraProps, includeLegacyEvent) {
            var props = buildUpgradeIntentProps(node, extraProps || {});

            track('upgrade_clicked', props);

            if (includeLegacyEvent) {
                track('upgrade_cta_clicked', props);
            }
        }

        function trackCheckoutStarted(node, extraProps) {
            track('checkout_started', buildUpgradeIntentProps(node, extraProps || {}));
        }

        $(document).on('click', '[data-bbai-analytics-upgrade]', function() {
            var name = ($(this).attr('data-bbai-analytics-upgrade') || '').trim();
            if (!name) {
                return;
            }
            track(name, buildUpgradeIntentProps(this, { upgrade_path_event: name }));
        });

        function findFallbackUpgradeTarget(startNode) {
            var target = startNode && typeof startNode.closest === 'function' ? startNode : null;
            var i;
            var matched;
            var clickable;
            var signal;

            if (!target) {
                return null;
            }

            for (i = 0; i < upgradeFallbackSelectors.length; i++) {
                matched = target.closest(upgradeFallbackSelectors[i]);
                if (matched) {
                    return {
                        node: matched,
                        selector: upgradeFallbackSelectors[i]
                    };
                }
            }

            clickable = target.closest('a, button');
            if (!clickable) {
                return null;
            }

            signal = [
                clickable.getAttribute('aria-label') || '',
                clickable.getAttribute('title') || '',
                clickable.getAttribute('href') || '',
                clickable.getAttribute('data-action') || '',
                clickable.getAttribute('data-bbai-action') || '',
                clickable.className || '',
                clickable.textContent || ''
            ].join(' ').toLowerCase();

            if (
                /\b(upgrade|buy credits|buy more credits|view pricing|plans? ?& ?pricing|see plans|unlock more|unlock full|enable auto-optimisation|enable automatic optimisation)\b/.test(signal)
            ) {
                return {
                    node: clickable,
                    selector: 'text_or_attribute_fallback'
                };
            }

            return null;
        }

        function bindUpgradeFallbackListener() {
            if (upgradeClickFallbackBound) {
                return;
            }

            upgradeClickFallbackBound = true;
            window.addEventListener('click', function(event) {
                var match = findFallbackUpgradeTarget(event.target);

                if (!match || !match.node) {
                    return;
                }

                var props = buildUpgradeIntentProps(match.node, {
                    trigger: match.selector === 'text_or_attribute_fallback'
                        ? 'delegated_fallback'
                        : getUpgradeTrigger(match.node)
                });

                var eventName = isCheckoutTarget(match.node) ? 'checkout_started' : 'upgrade_clicked';

                if (isDebugEnabled() && window.console && typeof window.console.debug === 'function') {
                    window.console.debug('[BBAI] upgrade click detected via delegated fallback', {
                        event: eventName,
                        selector: match.selector,
                        location: props.location,
                        trigger: props.trigger,
                        page: props.page
                    });
                }

                track(eventName, props);
            }, true);
        }

        $(document).on('click', '[data-bbai-locked-modal-upgrade="1"]', function () {
            trackUpgradeIntent(this, {}, true);
        });

        $(document).on('click', 'a[href*="beepbeep.ai"], a[href*="stripe"], a[href*="pricing"]', function () {
            var t = this;

            if (
                t.closest('[data-action="show-upgrade-modal"]') ||
                t.closest('[data-bbai-action="open-upgrade"]') ||
                t.closest('[data-action="checkout-plan"]') ||
                t.closest('[data-bbai-locked-modal-upgrade="1"]')
            ) {
                return;
            }

            if (isCheckoutTarget(t)) {
                trackCheckoutStarted(t, {
                    trigger: 'checkout_plan'
                });
                return;
            }

            trackUpgradeIntent(t, {
                trigger: 'pricing_link'
            }, true);
        });

        bindUpgradeFallbackListener();
    }

    function bindLibraryFilters() {
        // Review filter telemetry is emitted from the library workspace controller so
        // programmatic filter changes and CTA-driven filter jumps share one event path.
    }

    function bindRowActions() {
        $(document).on('click', '[data-action="generate-missing"], [data-bbai-action="generate_missing"]', function () {
            var sourcePage = resolveNodeSourcePage(this);
            track(sourcePage === 'alt_library' ? 'alt_library_generate_clicked' : 'generation_cta_clicked', buildCtaProps(this, {
                generation_mode: 'batch'
            }));
        });
        $(document).on('click', '[data-action="regenerate-selected"], button[id="bbai-batch-regenerate"]', function () {
            track('alt_library_regenerate_clicked', buildCtaProps(this, {
                scope: 'bulk_control',
                generation_mode: 'regenerate'
            }));
        });
        $(document).on('click', '[data-action="regenerate"], [data-action="inline-generate"], .bbai-regenerate-alt', function () {
            track('alt_library_regenerate_clicked', buildCtaProps(this, {
                scope: 'row',
                action: 'regenerate',
                generation_mode: 'regenerate'
            }));
        });
        $(document).on('click', '[data-action="copy-alt-text"], [data-bbai-action="copy-alt-text"], .bbai-copy-alt, [data-clipboard-target]', function () {
            track('alt_library_copy_clicked', buildCtaProps(this, {
                source_page: 'alt_library'
            }));
        });
        $(document).on('click', '.bbai-library-pagination a, .bbai-pagination a, .tablenav-pages a', function () {
            track('pagination_used', { area: 'library_or_table' });
        });
    }

    function bindActivationJourneyTelemetry() {
        $(document).on('click', '[data-action="rescan-media-library"], [data-bbai-action="rescan-media-library"]', function () {
            track('rescan_clicked', buildCtaProps(this));
        });
        $(document).on('click', '[data-bbai-daily-pass-cta], [data-action="daily-pass"], [data-bbai-action="daily_pass"]', function () {
            track('daily_pass_cta_clicked', buildCtaProps(this, {
                generation_mode: 'daily_pass'
            }));
        });
        $(document).on('click', '[data-bbai-navigation="review-results"], [data-bbai-quick-action="review-weak"], [data-bbai-workflow-review-cta], [data-bbai-review-scroll="1"], a[href*="page=bbai-library"][href*="status=needs_review"]', function () {
            track('review_workflow_opened', buildCtaProps(this));
        });
        $(document).on('change click', '[data-bbai-review-filter], [data-bbai-library-filter], #bbai-library-status-filter, #bbai-library-search, .bbai-library-filter, .bbai-filter-group button', function () {
            track('alt_library_filter_clicked', buildCtaProps(this, {
                filter_value: this.value || this.getAttribute('data-filter') || this.getAttribute('data-status') || ''
            }));
        });
    }

    function findMainGenerateCta() {
        return document.querySelector([
            '[data-bbai-main-generate-cta="1"]',
            '[data-bbai-fix-dashboard="1"][data-action="generate-missing"]',
            '.bbai-generate-button[data-action="generate-missing"]',
            '[data-action="generate-missing"]',
            '[data-bbai-action="generate_missing"]'
        ].join(','));
    }

    function isElementVisible(node) {
        return !!(node && node.offsetParent !== null && node.getClientRects && node.getClientRects().length);
    }

    function maybeTrackGenerateCtaShown() {
        var node;
        var disabledReason;

        if (generateCtaShownSent) {
            return true;
        }

        node = findMainGenerateCta();
        if (!node || !isElementVisible(node)) {
            return false;
        }

        disabledReason = getDisabledReason(node);
        generateCtaShownSent = true;
        track('generate_cta_shown', buildCtaProps(node, {
            disabled: !!disabledReason,
            disabled_reason: disabledReason
        }));
        return true;
    }

    function bindGenerateCtaVisibility() {
        var observer;
        var stopTimer;

        if (generateCtaVisibilityBound) {
            return;
        }
        generateCtaVisibilityBound = true;

        if (maybeTrackGenerateCtaShown()) {
            return;
        }
        if (!window.MutationObserver) {
            return;
        }

        observer = new MutationObserver(function() {
            if (maybeTrackGenerateCtaShown()) {
                observer.disconnect();
                if (stopTimer) {
                    window.clearTimeout(stopTimer);
                }
            }
        });
        observer.observe(document.body || document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class', 'hidden', 'disabled', 'aria-disabled']
        });
        stopTimer = window.setTimeout(function() {
            observer.disconnect();
        }, 15000);
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

    function bindAnalyticsUsage() {
        if (analyticsFeatureBound) {
            return;
        }

        analyticsFeatureBound = true;
        $(document).on('click', '#bbai-coverage-chart, .bbai-analytics-page canvas', function () {
            trackFeatureUsed('analytics', {
                feature_context: 'analytics',
                source_page: 'analytics'
            });
        });
    }

    function bindWooCommerceUsage() {
        if (wooFeatureBound) {
            return;
        }

        wooFeatureBound = true;
        $(document).on('click change', '[data-wizard-action="continue-woo"], [data-wizard-action="enable-woo-step"], [data-wizard-field="woo_context"]', function () {
            trackFeatureUsed('woocommerce_optimisation', {
                feature_context: 'woocommerce',
                source_page: getSourcePage()
            });
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

    function bindPhase3StateTelemetry() {
        if (document.querySelector('[data-bbai-review-queue="1"], [data-bbai-li-surface="ReviewQueue"]')) {
            bbaiTrackOnce('review_queue_opened', {
                source_page: getSourcePage(),
                ready_for_review_count: getCountValue(['needs_review_count', 'review_count', 'ready_for_review_count'], ['data-bbai-weak-count', 'data-bbai-needs-review-count', 'data-bbai-ready-for-review-count'])
            }, 'review_queue_opened:' + window.location.pathname + window.location.search);
        }

        var hero = document.querySelector('[data-bbai-li-hero="1"]');
        var root = document.querySelector('[data-bbai-dashboard-root="1"]');
        var state = hero ? readString(hero.getAttribute('data-bbai-li-state')) : readString(root && (root.getAttribute('data-state') || root.getAttribute('data-bbai-li-state')));
        var snapshot = buildActivationSnapshot();

        if (state === 'ALL_CLEAR' || state === 'DONE') {
            bbaiTrackOnce('fully_optimised_state_shown', snapshot, 'fully_optimised_state_shown:' + (snapshot.site_hash || '') + ':' + (snapshot.optimized_count || ''));
        } else if (state === 'QUOTA_EXHAUSTED') {
            bbaiTrackOnce('quota_exhausted_state_shown', snapshot, 'quota_exhausted_state_shown:' + (snapshot.site_hash || '') + ':' + (snapshot.missing_count || ''));
        } else if (state === 'NEEDS_REVIEW') {
            bbaiTrackOnce('success_state_shown', snapshot, 'success_state_shown:' + (snapshot.site_hash || '') + ':' + (snapshot.needs_review_count || ''));
        }
    }

    window.bbaiTelemetry = {
        track: track,
        flush: flushNow,
        getUpgradeAttribution: getStoredUpgradeAttribution,
        buildCheckoutAttribution: buildCheckoutRequestAttribution
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
        bindActivationJourneyTelemetry();
        bindGenerateCtaVisibility();
        bindReviewNavigation();
        bindRetentionTelemetry();
        bindBannerTelemetry();
        bindDismiss();
        bindManualEditSignal();
        bindAnalyticsUsage();
        bindWooCommerceUsage();
        bindCustomEvents();
        bindPhase3StateTelemetry();
    });

    $(window).on('beforeunload', function () {
        flushNow();
    });
})(jQuery);
