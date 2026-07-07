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
    var upgradeEventDedup = {
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
        plugin_installed: true,
        plugin_activated: true,
        plugin_updated: true,
        plugin_opened: true,
        dashboard_viewed: true,
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
        alt_generated: true,
        generation_failed_timeout: true,
        generation_failed_api: true,
        generation_failed_auth: true,
        generation_failed_no_credits: true,
        generation_failed_invalid_image: true,
        generation_failed_rate_limit: true,
        generation_failed_network: true,
        generation_failed_unknown: true,
        batch_generation_completed: true,
        batch_generation_quota_limit_hit: true,
        batch_generation_partial_quota_stop: true,
        batch_generation_cta_shown: true,
        batch_generation_cta_clicked: true,
        review_alt_clicked: true,
        review_filter_applied: true,
        alt_library_item_opened: true,
        alt_library_edit_started: true,
	        alt_library_edit_saved: true,
        feature_used: true,
        entitlement_state_loaded: true,
        paywall_shown: true,
        generation_blocked_no_credits: true,
        review_completed: true,
        library_state_conflict_detected: true,
        first_run_completed: true,
        settings_saved: true,
        bulk_generation_cancelled: true,
        upgrade_cta_clicked: true,
        checkout_completed: true,
        subscription_started: true,
        subscription_cancelled: true,
        trial_expired: true,
        credits_purchased: true,
        image_regenerated: true,
        manual_alt_edit: true,
        review_queue_opened: true,
        support_clicked: true,
        documentation_opened: true,
        upgrade_clicked: true,
        checkout_started: true,
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

    function isDebugEnabled() {
        return !!(
            window.BBAI_DEBUG_POSTHOG === true ||
            cfg.debug_posthog === true
        );
    }

    window.bbaiTelemetrySeen = window.bbaiTelemetrySeen || new Set();
    window.bbaiCurrentGenerationRunId = window.bbaiCurrentGenerationRunId || '';
    window.bbaiTelemetrySessionId = window.bbaiTelemetrySessionId || resolveTelemetrySessionId();

    function resolveTelemetrySessionId() {
        var key = 'bbai_telemetry_session_id';
        var existing = '';

        try {
            existing = window.sessionStorage ? window.sessionStorage.getItem(key) || '' : '';
        } catch (e) {
            existing = '';
        }

        if (!/^[a-z0-9_-]{8,80}$/i.test(existing)) {
            existing = 'bbai_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12);
            try {
                if (window.sessionStorage) {
                    window.sessionStorage.setItem(key, existing);
                }
            } catch (e2) {}
        }

        try {
            document.cookie = 'bbai_session_id=' + encodeURIComponent(existing) + '; path=/; SameSite=Lax';
        } catch (e3) {}

        return existing;
    }

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

    function normalizePlanValue(value, fallbackForConnected) {
        var plan = readString(value).toLowerCase().replace(/[^a-z0-9_:-]+/g, '_');

        if (plan === 'free' || plan === 'trial' || plan === 'pro' || plan === 'agency') {
            return plan;
        }
        if (plan === 'anonymous_trial') {
            return 'trial';
        }
        if (plan === 'starter' || plan === 'growth' || plan === 'enterprise') {
            return 'pro';
        }
        return fallbackForConnected ? 'free' : 'unknown';
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

        return normalizePlanValue(plan, !!(context.account_id || context.user_id || context.license_key_present));
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
            site_install_id: identity.site_install_id || '',
            site_id: identity.site_id || '',
            site_hash: identity.site_hash || '',
            site_url: context.site_url || (cfg.context && cfg.context.site_url) || '',
            site_host: context.site_host || (cfg.context && cfg.context.site_host) || '',
            current_plan: normalizePlanValue(getCurrentPlan()),
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
        var siteInstallId = context.site_install_id || context.siteInstallId || context.install_id || context.installId || context.site_hash || context.site_id || '';

        return {
            account_id: context.account_id || '',
            user_id: context.user_id || '',
            license_key_present: !!context.license_key_present,
            site_install_id: siteInstallId,
            site_id: context.site_id || '',
            site_hash: context.site_hash || '',
            site_url: context.site_url || (cfg.context && cfg.context.site_url) || '',
            site_host: context.site_host || (cfg.context && cfg.context.site_host) || '',
            wordpress_user_id: context.wordpress_user_id || ''
        };
    }

    function normalizeHostValue(value) {
        var host = readString(value).trim();
        if (!host) {
            return '';
        }
        if (/^https?:\/\//i.test(host)) {
            try {
                host = new URL(host).hostname;
            } catch (e) {
                host = host.replace(/^https?:\/\//i, '').replace(/\/.*$/, '');
            }
        } else {
            host = host.replace(/\/.*$/, '');
        }
        return host.toLowerCase();
    }

    function getCanonicalContext() {
        var c = cfg.context || {};
        var runtime = getTelemetryRuntimeContext();
        var host = normalizeHostValue(runtime.site_host || c.host || c.site_host || '');
        if (!host && (runtime.site_url || c.site_url)) {
            host = normalizeHostValue(runtime.site_url || c.site_url);
        }
        return {
            site_install_id: runtime.site_install_id || c.site_install_id || '',
            site_id: runtime.site_id || c.site_id || '',
            site_hash: runtime.site_hash || c.site_hash || '',
            site_url: runtime.site_url || c.site_url || '',
            site_host: host,
            host: host,
            plugin_version: runtime.plugin_version || c.plugin_version || '',
            plugin_slug: readString(c.plugin_slug || 'beepbeep-ai-alt-text-generator'),
            telemetry_version: readString(c.telemetry_version || '1'),
            wp_version: readString(c.wp_version || ''),
            wordpress_version: readString(c.wordpress_version || c.wp_version || ''),
            php_version: readString(c.php_version || ''),
            environment: readString(c.environment || 'production'),
            plan: normalizePlanValue(runtime.current_plan || c.plan || c.plan_type),
            plan_type: normalizePlanValue(runtime.current_plan || c.plan_type || c.plan),
            quota_state: readString(c.quota_state || ''),
            license_state: readString(c.license_state || (c.is_logged_in ? 'connected' : 'guest'))
        };
    }

    function baseProps() {
        var c = cfg.context || {};
        var runtime = getTelemetryRuntimeContext();
        var canonical = getCanonicalContext();
        return $.extend({
            page: c.page_variant || c.page || 'unknown',
            client_page: c.page || 'unknown',
            page_variant: c.page_variant || c.page || 'unknown',
            plan_type: canonical.plan_type,
            plan: canonical.plan,
            user_state: c.is_logged_in === true ? 'signed_in' : 'guest',
            journey_id: canonical.site_install_id || c.site_install_id || '',
            session_id: window.bbaiTelemetrySessionId || '',
            site_url: canonical.site_url,
            site_host: canonical.site_host,
            host: canonical.host,
            plugin_version: canonical.plugin_version,
            plugin_slug: canonical.plugin_slug,
            telemetry_version: canonical.telemetry_version,
            wp_version: canonical.wp_version,
            wordpress_version: canonical.wordpress_version,
            php_version: canonical.php_version,
            environment: canonical.environment,
            quota_state: canonical.quota_state,
            license_state: canonical.license_state,
            country: c.country || '',
            is_logged_in: c.is_logged_in === true,
            credits_remaining: runtime.remaining_credits,
            is_first_generation: false,
            is_returning_user: (c.days_since_last_active || 0) > 0
        }, getPostHogIdentityContext());
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
            cleaned[key] = payload[key];
        });
        return cleaned;
    }

    function normalizeFailureCode(value) {
        return readString(value).toLowerCase().replace(/[^a-z0-9_:-]+/g, '_').slice(0, 120);
    }

    function inferGenerationFailureEvent(props) {
        var errorCode = normalizeFailureCode(
            props.error_code ||
            props.code ||
            props.error ||
            props.status_code ||
            props.http_status ||
            ''
        );
        var message = readString(props.error_message || props.message || props.rawMessage || '').toLowerCase();
        var combined = [errorCode, message].join(' ');

        if (/timeout|timed_out|deadline|504|gateway_timeout/.test(combined)) {
            return 'generation_failed_timeout';
        }
        if (/auth|unauthori[sz]ed|forbidden|invalid[_ -]?key|session|login|log_in|401|403/.test(combined)) {
            return 'generation_failed_auth';
        }
        if (/credit|quota|limit[_ -]?reached|insufficient|exhausted|no[_ -]?credits|payment_required|402/.test(combined)) {
            return 'generation_failed_no_credits';
        }
        if (/invalid[_ -]?(image|attachment|mime)|unsupported|corrupt|too[_ -]?large|file[_ -]?type|media/.test(combined)) {
            return 'generation_failed_invalid_image';
        }
        if (/rate[_ -]?limit|too[_ -]?many|429/.test(combined)) {
            return 'generation_failed_rate_limit';
        }
        if (/network|offline|fetch|connection|dns|ssl|abort/.test(combined)) {
            return 'generation_failed_network';
        }
        if (/api|provider|openai|server|5\d\d|bad_gateway|service_unavailable/.test(combined)) {
            return 'generation_failed_api';
        }

        return 'generation_failed_unknown';
    }

    function normalizeTelemetryEvent(eventName, props) {
        var name = readString(eventName);
        var payload = $.extend({}, props || {});

        if (name === 'generation_failed') {
            name = inferGenerationFailureEvent(payload);
            payload.error_code = normalizeFailureCode(payload.error_code || payload.code || payload.error || name);
            payload.response_time = payload.response_time || payload.response_time_ms || payload.processing_time_ms || payload.generation_latency_ms || '';
            payload.provider = payload.provider || 'unknown';
            payload.retry_attempt = payload.retry_attempt || payload.retry_count || 0;
        } else if (name === 'guest_dashboard_viewed') {
            name = 'dashboard_viewed';
            payload.user_state = payload.user_state || 'guest';
        } else if (name === 'upgrade_clicked' || name === 'upgrade_started') {
            name = 'upgrade_cta_clicked';
        } else if (name === 'upgrade_completed') {
            name = 'checkout_completed';
        } else if (name === 'checkout_session_created') {
            name = 'checkout_started';
        } else if (name === 'account_created') {
            name = 'signup_succeeded';
        } else if (name === 'first_alt_generated') {
            name = 'first_run_completed';
        } else if (name === 'manual_edit_used') {
            name = 'manual_alt_edit';
        } else if (name === 'alt_generated_failed') {
            name = 'generation_failed_unknown';
            payload.error_code = normalizeFailureCode(payload.error_code || 'partial_generation_failed');
            payload.provider = payload.provider || 'unknown';
            payload.retry_attempt = payload.retry_attempt || 0;
        } else if (name === 'alt_generated_success') {
            name = 'generation_completed';
        }

        if (name === 'feature_used') {
            payload.feature_name = normalizeFeatureName(payload.feature_name || payload.feature);
            if (!payload.feature_name) {
                payload.__drop_event = true;
            }
            delete payload.feature;
        }

        return {
            eventName: name,
            properties: payload
        };
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
            return 'woocommerce';
        }

        if (
            (!isNaN(requestedCount) && requestedCount > 1) ||
            /bulk|batch|selected|generate-missing|generate_missing|regenerate-selected|reoptimize|fix-all-issues/.test(mode)
        ) {
            return 'bulk_generation';
        }

        return 'single_generation';
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

        if (normalized === 'alt_generation' || normalized === 'alt-generation' || normalized === 'single_generation' || normalized === 'single-generation' || normalized === 'generation' || normalized === 'generate') {
            return 'single_generation';
        }
        if (normalized === 'bulk_generation' || normalized === 'bulk-generation' || normalized === 'bulk') {
            return 'bulk_generation';
        }
        if (normalized === 'review_workflow' || normalized === 'review-workflow' || normalized === 'review') {
            return 'review';
        }
        if (normalized === 'analytics' || normalized === 'statistics' || normalized === 'stats') {
            return 'statistics';
        }
        if (normalized === 'alt_library' || normalized === 'alt-library' || normalized === 'library') {
            return 'library';
        }
        if (normalized === 'account' || normalized === 'settings' || normalized === 'billing' || normalized === 'dashboard') {
            return normalized;
        }
        if (normalized === 'login' || normalized === 'signup' || normalized === 'quota' || normalized === 'review_queue') {
            return normalized;
        }
        if (
            normalized === 'woocommerce_optimisation' ||
            normalized === 'woocommerce-optimisation' ||
            normalized === 'woocommerce_optimization' ||
            normalized === 'woocommerce-optimization' ||
            normalized === 'woocommerce'
        ) {
            return 'woocommerce';
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
            return 'woocommerce';
        }
        if (/analytic|coverage|trend|chart|progress/.test(signal)) {
            return 'statistics';
        }
        if (/review|approve|manual|edit|weak/.test(signal)) {
            return 'review';
        }
        if (
            /bulk|batch|generate[_ -]?missing|reoptimi[sz]e[_ -]?all|regenerate[_ -]?(all|selected)|selected|selection|queue/.test(signal)
        ) {
            return 'bulk_generation';
        }
        if (/automation|generate|regenerate|upload|alt text|improve/.test(signal)) {
            return 'single_generation';
        }
        if (!allowPageFallback) {
            return '';
        }
        if (context === 'analytics' || sourcePage === 'analytics') {
            return 'statistics';
        }
        if (context === 'woocommerce' || sourcePage === 'woocommerce') {
            return 'woocommerce';
        }
        if (context === 'alt_library' || sourcePage === 'alt_library') {
            return 'library';
        }

        return 'single_generation';
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
            feature: props.feature_name || props.feature || '',
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
            feature_name: feature,
            feature_context: normalizeFeatureContext(
                properties && properties.feature_context,
                properties && properties.source_page
            ),
            source_page: getSourcePage(properties),
            current_plan: runtime.current_plan,
            plugin_version: runtime.plugin_version
        }, properties || {}));

        props.feature_name = normalizeFeatureName(props.feature_name);
        if (!props.feature_name) {
            return;
        }
        if (!shouldSendFeatureUsage(props.feature_name, props.feature_context, props.source_page)) {
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

        if (eventName === 'signup_started' || eventName === 'signup_succeeded') {
            trackFeatureUsed('signup', {
                feature_context: normalizeFeatureContext(props.source || props.location, getSourcePage(props)),
                source_page: getSourcePage(props)
            });
            return;
        }

        if (eventName === 'login_succeeded') {
            trackFeatureUsed('login', {
                feature_context: normalizeFeatureContext(props.source || props.location, getSourcePage(props)),
                source_page: getSourcePage(props)
            });
            return;
        }

        if (eventName === 'review_queue_opened') {
            trackFeatureUsed('review_queue', {
                feature_context: 'alt_library',
                source_page: getSourcePage(props)
            });
            return;
        }

        if (eventName === 'generation_blocked_no_credits') {
            trackFeatureUsed('quota', {
                feature_context: normalizeFeatureContext(props.surface || props.source, getSourcePage(props)),
                source_page: getSourcePage(props)
            });
            return;
        }

        if (eventName === 'settings_saved') {
            trackFeatureUsed('settings', {
                feature_context: 'settings',
                source_page: 'settings'
            });
            return;
        }

        if (
            eventName === 'review_alt_clicked' ||
            eventName === 'alt_library_edit_started' ||
            eventName === 'alt_library_edit_saved'
        ) {
            trackFeatureUsed('review', {
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
            site_install_id: runtime.site_install_id || '',
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
        var normalized = normalizeTelemetryEvent(eventName, properties || {});

        eventName = normalized.eventName;
        properties = normalized.properties;

        if (properties.__drop_event) {
            return;
        }

        if (!eventName || !/^[a-z0-9_]{1,80}$/.test(eventName)) {
            return;
        }
        var props = $.extend({}, baseProps(), properties || {});

        // Generation events: enforce once-per-generation-run.
        if (eventName === 'generation_started') {
            window.bbaiCurrentGenerationRunId = String(
                props.generation_run_id ||
                ('bbai_gen_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8))
            );
            props.generation_run_id = window.bbaiCurrentGenerationRunId;
            try {
                if (window.bbaiTelemetrySeen.has('generation_started:' + props.generation_run_id)) {
                    return;
                }
                window.bbaiTelemetrySeen.add('generation_started:' + props.generation_run_id);
            } catch (e) {}
        }
        if (eventName === 'generation_completed' || eventName.indexOf('generation_failed_') === 0) {
            var runId = props.generation_run_id || window.bbaiCurrentGenerationRunId || '';
            if (!runId) {
                window.bbaiCurrentGenerationRunId = 'bbai_gen_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
                runId = window.bbaiCurrentGenerationRunId;
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
        if (eventName === 'generation_started' || eventName === 'generation_completed' || eventName === 'alt_generated' || eventName.indexOf('generation_failed_') === 0 || eventName.indexOf('batch_generation_') === 0) {
            props.generation_type = props.generation_type || props.generation_mode || 'single';
            props.generation_mode = props.generation_mode || props.generation_type;
            if (!props.feature_name) {
                props.feature_name = props.generation_mode === 'bulk' ? 'bulk_generation' : 'single_generation';
            }
        }
        if ((eventName === 'signup_started' || eventName === 'signup_succeeded') && !props.feature_name) {
            props.feature_name = 'signup';
        }
        if (eventName === 'login_succeeded' && !props.feature_name) {
            props.feature_name = 'login';
        }

        if (eventName === 'feature_used') {
            props = cleanupProps($.extend({}, props, {
                source_page: getSourcePage(props),
                current_plan: normalizePlanValue(props.current_plan || getCurrentPlan()),
                plan: normalizePlanValue(props.plan || props.plan_type || getCurrentPlan()),
                plan_type: normalizePlanValue(props.plan_type || props.plan || getCurrentPlan()),
                feature_context: normalizeFeatureContext(props.feature_context || props.source || props.location, props.source_page || props.page),
                plugin_version: props.plugin_version || getTelemetryRuntimeContext().plugin_version
            }));
            props.feature_name = normalizeFeatureName(props.feature_name);
            if (!props.feature_name) {
                return;
            }
            updateLastFeatureUsage(props);
        } else if (eventName === 'upgrade_cta_clicked' || eventName === 'upgrade_clicked' || eventName === 'checkout_started') {
            props = enrichUpgradeAttribution(eventName, props);
        }
        if (eventName === 'upgrade_cta_clicked' || eventName === 'upgrade_clicked' || eventName === 'checkout_started') {
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
                site_install_id: props.site_install_id || '',
                site_id: props.site_id || '',
                site_hash: props.site_hash || ''
            });
        }
        if (posthogAllowlist[eventName]) {
            bbaiTrack(eventName, buildPostHogProps(props));
        }
        queue.push({ event: eventName, properties: props });
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
            guest_dashboard: 'dashboard_viewed',
            alt_library: 'alt_library_viewed',
            analytics: 'analytics_viewed',
            usage: 'usage_viewed',
            settings: 'settings_viewed',
            help: 'settings_viewed'
        };

        if (pageVariant === 'guest_dashboard') {
            return 'dashboard_viewed';
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
        var sessionOpenKey = 'bbai_plugin_opened_sent:' + (window.bbaiTelemetrySessionId || '');
        var pluginOpenedSent = false;
        try {
            pluginOpenedSent = !!(window.sessionStorage && window.sessionStorage.getItem(sessionOpenKey));
        } catch (e) {
            pluginOpenedSent = false;
        }
        if (!pluginOpenedSent) {
            try {
                if (window.sessionStorage) {
                    window.sessionStorage.setItem(sessionOpenKey, '1');
                }
            } catch (e2) {}
            bbaiTrackOnce('plugin_opened', {
                navigation: 'direct',
                page: pageVariant
            }, sessionOpenKey);
        }
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
            track('review_queue_opened', {
                source: getUiSource(this),
                source_page: getSourcePage({ source_page: resolveNodeSourcePage(this) })
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
                track('manual_alt_edit', { context: 'alt_field' });
            }
        });
    }

    function bindAnalyticsUsage() {
        if (analyticsFeatureBound) {
            return;
        }

        analyticsFeatureBound = true;
        $(document).on('click', '#bbai-coverage-chart, .bbai-analytics-page canvas', function () {
            trackFeatureUsed('statistics', {
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
            trackFeatureUsed('woocommerce', {
                feature_context: 'woocommerce',
                source_page: getSourcePage()
            });
        });
    }

    function bindFounderSignalEvents() {
        $(document).on('submit', 'form', function () {
            var page = getSourcePage();
            if (page === 'settings' || this.closest('.nai-settings, .bbai-settings, [data-bbai-settings]')) {
                track('settings_saved', {
                    source_page: 'settings'
                });
            }
        });

        $(document).on('click', 'a[href]', function () {
            var href = String(this.getAttribute('href') || '').toLowerCase();
            var label = String(this.getAttribute('aria-label') || this.getAttribute('title') || this.textContent || '').trim().slice(0, 120);

            if (/support|contact|mailto:/.test(href)) {
                track('support_clicked', {
                    source_page: resolveNodeSourcePage(this),
                    cta_label: label
                });
            } else if (/docs|documentation|guide|help/.test(href)) {
                track('documentation_opened', {
                    source_page: resolveNodeSourcePage(this),
                    cta_label: label
                });
            }
        });

        $(document).on('click', '[data-bbai-action*="cancel"], [data-action*="cancel"], [data-bbai-generation-cancel], .bbai-cancel-generation', function () {
            var signal = getNodeSignal(this);
            if (!/generation|bulk|batch|queue|job/.test(signal)) {
                return;
            }
            track('bulk_generation_cancelled', {
                source_page: resolveNodeSourcePage(this),
                generation_mode: /single/.test(signal) ? 'single' : 'bulk'
            });
        });
    }

    function bindNaiShellNavigation() {
        $(document).on('click', '.nai-topbar__link[href]', function () {
            var href = String($(this).attr('href') || '');
            var feature = '';
            var sourcePage = getSourcePage();

            if ($(this).attr('aria-current') === 'page') {
                return;
            }

            if (href.indexOf('page=bbai-library') !== -1) {
                feature = 'library';
                sourcePage = 'alt_library';
            } else if (href.indexOf('page=bbai-settings') !== -1 || href.indexOf('page=bbai-debug') !== -1) {
                feature = 'settings';
                sourcePage = 'settings';
            } else if (href.indexOf('page=bbai-analytics') !== -1) {
                feature = 'statistics';
                sourcePage = 'analytics';
            } else if (href.indexOf('page=bbai-credit-usage') !== -1) {
                feature = 'billing';
                sourcePage = 'usage';
            } else if (href.indexOf('page=bbai-autopilot') !== -1 || href.indexOf('page=bbai') !== -1) {
                feature = 'dashboard';
                sourcePage = 'dashboard';
            }

            if (!feature) {
                return;
            }

            trackFeatureUsed(feature, {
                feature_context: sourcePage,
                source_page: sourcePage
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
        bindReviewNavigation();
        bindRetentionTelemetry();
        bindBannerTelemetry();
        bindDismiss();
        bindManualEditSignal();
        bindAnalyticsUsage();
        bindWooCommerceUsage();
        bindFounderSignalEvents();
        bindNaiShellNavigation();
        bindCustomEvents();
    });

    $(window).on('beforeunload', function () {
        flushNow();
    });
})(jQuery);
