(function(window, document) {
    'use strict';

    var cfg = window.BBAI_POSTHOG || {};
    var runtimeContext = {};
    var instanceName = cfg.instanceName || 'bbaiPosthog';
    var clientBootstrapped = false;

    function isObject(value) {
        return !!value && Object.prototype.toString.call(value) === '[object Object]';
    }

    function extend(target) {
        var output = isObject(target) ? target : {};
        var i;
        var source;
        var key;

        for (i = 1; i < arguments.length; i++) {
            source = arguments[i];
            if (!isObject(source)) {
                continue;
            }

            for (key in source) {
                if (Object.prototype.hasOwnProperty.call(source, key)) {
                    output[key] = source[key];
                }
            }
        }

        return output;
    }

    function sanitizeEventName(eventName) {
        var name = eventName === undefined || eventName === null ? '' : String(eventName);
        return /^[a-z0-9_]{1,80}$/.test(name) ? name : '';
    }

    function sanitizeProperties(input) {
        var output = {};
        var key;
        var value;
        var nested;
        var nestedKey;

        if (!isObject(input)) {
            return output;
        }

        for (key in input) {
            if (!Object.prototype.hasOwnProperty.call(input, key)) {
                continue;
            }

            value = input[key];
            if (value === undefined || value === null) {
                continue;
            }

            if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
                output[key] = value;
                continue;
            }

            if (Array.isArray(value)) {
                output[key] = value.filter(function(item) {
                    return typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean';
                });
                continue;
            }

            if (!isObject(value)) {
                continue;
            }

            nested = {};
            for (nestedKey in value) {
                if (!Object.prototype.hasOwnProperty.call(value, nestedKey)) {
                    continue;
                }

                if (
                    typeof value[nestedKey] === 'string' ||
                    typeof value[nestedKey] === 'number' ||
                    typeof value[nestedKey] === 'boolean'
                ) {
                    nested[nestedKey] = value[nestedKey];
                }
            }

            if (Object.keys(nested).length) {
                output[key] = nested;
            }
        }

        return output;
    }

    function getContext() {
        return extend({}, sanitizeProperties(cfg.context || {}), runtimeContext);
    }

    function getPageContext() {
        var context = getContext();
        if (context.page === 'guest_dashboard') {
            return 'dashboard';
        }
        if (context.page === 'alt_library') {
            return 'library';
        }
        return context.page || 'dashboard';
    }

    function getClient() {
        return window[instanceName] || window.posthog || null;
    }

    function ensureSnippet() {
        if (window.__BBAI_POSTHOG_SNIPPET_LOADED || (window.posthog && window.posthog.__SV)) {
            return;
        }

        window.__BBAI_POSTHOG_SNIPPET_LOADED = true;

        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group identify setPersonProperties setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags resetGroups onFeatureFlags addFeatureFlagsHandler onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
    }

    function registerContext() {
        var client = getClient();
        if (!client || typeof client.register !== 'function') {
            return;
        }

        try {
            client.register(getContext());
        } catch (error) {
            // Ignore PostHog registration failures.
        }
    }

    function ensureClient() {
        if (!cfg.enabled || !cfg.apiKey || !cfg.apiHost) {
            return null;
        }

        ensureSnippet();

        if (!clientBootstrapped && window.posthog && typeof window.posthog.init === 'function') {
            clientBootstrapped = true;
            try {
                window.posthog.init(cfg.apiKey, {
                    api_host: cfg.apiHost,
                    defaults: cfg.defaults || '2026-01-30',
                    autocapture: false,
                    capture_pageview: false,
                    capture_pageleave: false,
                    disable_session_recording: true
                }, instanceName);
            } catch (error) {
                // Ignore PostHog boot failures and no-op safely.
            }
        }

        registerContext();

        return getClient();
    }

    function track(eventName, properties) {
        var safeName = sanitizeEventName(eventName);
        var client;
        var payload;

        if (!safeName) {
            return;
        }

        client = ensureClient();
        if (!client || typeof client.capture !== 'function') {
            return;
        }

        payload = extend({}, getContext(), sanitizeProperties(properties || {}));

        try {
            client.capture(safeName, payload);
        } catch (error) {
            // Ignore PostHog capture failures.
        }
    }

    function identify(distinctId, properties) {
        var safeId = distinctId === undefined || distinctId === null ? '' : String(distinctId);
        var client = ensureClient();
        var safeProperties = sanitizeProperties(properties || {});

        if (!safeId || !client || typeof client.identify !== 'function') {
            return;
        }

        try {
            client.identify(safeId);
        } catch (error) {
            // Ignore identify failures.
        }

        if (typeof client.setPersonProperties === 'function' && Object.keys(safeProperties).length) {
            try {
                client.setPersonProperties(safeProperties);
            } catch (personError) {
                // Ignore person property failures.
            }
        }

        registerContext();
    }

    function pageView(pageName, properties) {
        var page = pageName === undefined || pageName === null ? '' : String(pageName);
        var mappedEvent = isObject(cfg.pageViewEvents) ? cfg.pageViewEvents[page] : '';
        var eventName = sanitizeEventName(mappedEvent) ? mappedEvent : sanitizeEventName(page + '_viewed');

        if (!eventName) {
            eventName = 'dashboard_viewed';
        }

        track(eventName, extend({ page: page || getContext().page || 'dashboard' }, properties || {}));
    }

    function updateContext(properties) {
        runtimeContext = extend({}, runtimeContext, sanitizeProperties(properties || {}));
        registerContext();
    }

    function resolveSource(element) {
        var node = element && typeof element.closest === 'function' ? element : null;
        var page = getPageContext();

        if (node && node.closest('.bbai-upgrade-modal, [data-bbai-upgrade-modal=\"1\"], #bbai-feature-unlock-modal, #bbai-bulk-progress-modal, .alttext-auth-modal')) {
            return 'modal';
        }
        if (node && node.closest('.bbai-status-banner, .bbai-command-hero, [data-bbai-banner], [data-bbai-status-banner]')) {
            return 'banner';
        }
        if (node && node.closest('.bbai-onboarding, [data-bbai-onboarding-step], [data-bbai-onboarding-action]')) {
            return 'onboarding';
        }
        if (node && node.closest('.bbai-library-workspace, #bbai-library-table-body, .bbai-library-row, [data-bbai-library-root]')) {
            return 'library';
        }
        if (node && node.closest('[data-bbai-analytics-hero], .bbai-analytics-page')) {
            return 'analytics';
        }
        if (node && node.closest('.bbai-dashboard-review-overlay')) {
            return 'modal';
        }
        return page;
    }

    function extractUsageContext(usage) {
        if (!isObject(usage)) {
            return {};
        }

        return sanitizeProperties({
            plan_type: usage.plan_type || usage.plan,
            quota_remaining: usage.remaining,
            quota_limit: usage.limit,
            remaining_free_images: usage.remaining_free_images
        });
    }

    function extractStatsContext(stats) {
        if (!isObject(stats)) {
            return {};
        }

        return sanitizeProperties({
            missing_alt_count: stats.images_missing_alt !== undefined ? stats.images_missing_alt : stats.missing,
            needs_review_count: stats.needs_review_count,
            optimized_count: stats.optimized_count
        });
    }

    function resolveIdentifyId(user) {
        if (!isObject(user)) {
            return '';
        }

        if (user.id) {
            return 'user:' + String(user.id);
        }
        if (user._id) {
            return 'user:' + String(user._id);
        }
        if (isObject(user.organization) && user.organization.id) {
            return 'org:' + String(user.organization.id);
        }
        if (isObject(user.organization) && user.organization._id) {
            return 'org:' + String(user.organization._id);
        }

        return '';
    }

    function buildPersonProperties(user) {
        if (!isObject(user)) {
            return sanitizeProperties((cfg.identify && cfg.identify.person_properties) || {});
        }

        return extend(
            {},
            sanitizeProperties((cfg.identify && cfg.identify.person_properties) || {}),
            sanitizeProperties({
                plan_type: user.plan || user.plan_type || user.planSlug || (user.organization && user.organization.plan),
                plugin_version: getContext().plugin_version,
                site_hash: getContext().site_hash
            })
        );
    }

    function bindContextListeners() {
        document.addEventListener('bbai:stats-updated', function(event) {
            var detail = event && event.detail ? event.detail : {};
            updateContext(extractStatsContext(detail.stats));
        });

        window.addEventListener('bbai-stats-update', function(event) {
            var detail = event && event.detail ? event.detail : {};
            updateContext(extractStatsContext(detail.stats));
            updateContext(extractUsageContext(detail.usage));
        });

        document.addEventListener('alttext:auth-success', function(event) {
            var detail = event && event.detail ? event.detail : {};
            var user = detail.user || {};
            var identifyId = resolveIdentifyId(user);

            updateContext({ is_logged_in: true });
            updateContext(extractUsageContext(user));

            if (identifyId) {
                identify(identifyId, buildPersonProperties(user));
            }
        });
    }

    window.bbaiAnalytics = {
        getClient: ensureClient,
        getContext: getContext,
        resolveSource: resolveSource,
        updateContext: updateContext,
        track: track,
        identify: identify,
        pageView: pageView
    };
    window.bbaiTrack = track;
    window.bbaiIdentify = identify;
    window.bbaiPageView = pageView;

    ensureClient();
    bindContextListeners();

    if (cfg.identify && cfg.identify.id) {
        identify(cfg.identify.id, cfg.identify.person_properties || {});
    }
})(window, document);
