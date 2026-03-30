(function(window, document) {
    'use strict';

    var cfg = window.BBAI_POSTHOG || {};
    var runtimeContext = {};
    var instanceName = cfg.instanceName || 'bbaiPosthog';
    var loaderState = window.__BBAI_POSTHOG_STATE || {};

    window.__BBAI_POSTHOG_STATE = loaderState;
    loaderState.scriptId = loaderState.scriptId || 'bbai-posthog-loader';
    loaderState.scriptRequested = !!loaderState.scriptRequested;
    loaderState.scriptLoaded = !!loaderState.scriptLoaded;
    loaderState.scriptFailed = !!loaderState.scriptFailed;
    loaderState.initStarted = !!loaderState.initStarted;
    loaderState.ready = !!loaderState.ready;
    loaderState.readyCallbacks = Array.isArray(loaderState.readyCallbacks) ? loaderState.readyCallbacks : [];
    loaderState.loadCallbacks = Array.isArray(loaderState.loadCallbacks) ? loaderState.loadCallbacks : [];
    loaderState.waitingForReady = !!loaderState.waitingForReady;
    loaderState.readyLogSent = !!loaderState.readyLogSent;
    loaderState.timeoutLogSent = !!loaderState.timeoutLogSent;
    loaderState.identifyState = isObject(loaderState.identifyState) ? loaderState.identifyState : {
        id: '',
        propsKey: ''
    };

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

    function serializeForCompare(value) {
        if (Array.isArray(value)) {
            return '[' + value.map(serializeForCompare).join(',') + ']';
        }

        if (!isObject(value)) {
            return JSON.stringify(value);
        }

        return '{' + Object.keys(value).sort().map(function(key) {
            return JSON.stringify(key) + ':' + serializeForCompare(value[key]);
        }).join(',') + '}';
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

    function logToConsole(level) {
        var fn;
        var args;

        if (!window.console || typeof window.console[level] !== 'function') {
            return;
        }

        args = Array.prototype.slice.call(arguments, 1);
        args.unshift('[BBAI]');
        fn = window.console[level];
        fn.apply(window.console, args);
    }

    function isDebugEnabled() {
        return !!(cfg.debug || window.alttextaiDebug);
    }

    function getClient() {
        if (instanceName !== 'posthog' && window[instanceName]) {
            return window[instanceName];
        }

        return window.posthog || null;
    }

    function getLibraryClient() {
        return window.posthog || null;
    }

    function getAssetUrl() {
        var explicitUrl = cfg.assetUrl ? String(cfg.assetUrl) : '';
        var apiHost;

        if (explicitUrl) {
            return explicitUrl;
        }

        apiHost = cfg.apiHost ? String(cfg.apiHost).replace(/\/+$/, '') : '';
        if (!apiHost) {
            return '';
        }

        return apiHost.replace('.i.posthog.com', '-assets.i.posthog.com') + '/static/array.js';
    }

    function hasLibraryApi(client) {
        return !!client && typeof client.init === 'function';
    }

    function isClientReady(client) {
        return !!client && client.__loaded === true && typeof client.capture === 'function' && !!client.config;
    }

    function isLibraryReady() {
        return hasLibraryApi(getLibraryClient());
    }

    function syncNamedInstance(client) {
        if (instanceName === 'posthog' || !client) {
            return client;
        }

        window[instanceName] = client;
        return client;
    }

    function flushReadyCallbacks(client) {
        var callbacks = loaderState.readyCallbacks.slice(0);
        var i;

        loaderState.readyCallbacks = [];

        for (i = 0; i < callbacks.length; i++) {
            try {
                callbacks[i](client);
            } catch (callbackError) {
                // Ignore callback failures.
            }
        }
    }

    function markReady(client) {
        if (!isClientReady(client)) {
            return;
        }

        syncNamedInstance(client);
        loaderState.ready = true;
        loaderState.scriptLoaded = true;
        loaderState.scriptFailed = false;

        if (!loaderState.readyLogSent) {
            loaderState.readyLogSent = true;
            logToConsole('log', 'PostHog ready', {
                instance: instanceName,
                loaded: client.__loaded === true,
                hasConfig: !!client.config
            });
        }

        registerContext();
        flushReadyCallbacks(client);
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

    function findExistingScript(assetUrl) {
        var scripts;
        var i;

        if (!assetUrl) {
            return null;
        }

        scripts = document.getElementsByTagName('script');
        for (i = 0; i < scripts.length; i++) {
            if (scripts[i].id === loaderState.scriptId || scripts[i].src === assetUrl) {
                return scripts[i];
            }
        }

        return null;
    }

    function flushLoadCallbacks() {
        var callbacks = loaderState.loadCallbacks.slice(0);
        var i;

        loaderState.loadCallbacks = [];

        for (i = 0; i < callbacks.length; i++) {
            try {
                callbacks[i]();
            } catch (callbackError) {
                // Ignore callback failures.
            }
        }
    }

    function bindScriptEvents(script, assetUrl) {
        if (!script || script.getAttribute('data-bbai-posthog-bound') === '1') {
            return;
        }

        script.setAttribute('data-bbai-posthog-bound', '1');
        script.addEventListener('load', function() {
            loaderState.scriptLoaded = true;
            loaderState.scriptRequested = true;
            logToConsole('log', 'PostHog script onload', assetUrl, {
                libraryApiReady: isLibraryReady()
            });
            flushLoadCallbacks();
        });
        script.addEventListener('error', function() {
            loaderState.scriptFailed = true;
            loaderState.loadCallbacks = [];
            logToConsole('warn', 'PostHog script failed to load', assetUrl);
        });
    }

    function ensureScriptLoaded(callback) {
        var assetUrl = getAssetUrl();
        var script;
        var anchor;

        if (typeof callback === 'function') {
            loaderState.loadCallbacks.push(callback);
        }

        if (!cfg.enabled || !cfg.apiKey || !cfg.apiHost) {
            loaderState.loadCallbacks = [];
            return;
        }

        if (isLibraryReady()) {
            loaderState.scriptLoaded = true;
            flushLoadCallbacks();
            return;
        }

        if (!assetUrl) {
            loaderState.loadCallbacks = [];
            logToConsole('warn', 'PostHog asset URL missing');
            return;
        }

        script = findExistingScript(assetUrl);
        if (script) {
            loaderState.scriptRequested = true;
            bindScriptEvents(script, assetUrl);
            return;
        }

        if (loaderState.scriptRequested) {
            return;
        }

        loaderState.scriptRequested = true;
        loaderState.scriptFailed = false;

        if (!window.posthog || (typeof window.posthog !== 'object' && typeof window.posthog !== 'function')) {
            window.posthog = [];
        }

        logToConsole('log', 'PostHog script injection start', assetUrl);

        script = document.createElement('script');
        script.id = loaderState.scriptId;
        script.async = true;
        script.src = assetUrl;
        script.setAttribute('data-bbai-posthog-loader', '1');
        bindScriptEvents(script, assetUrl);

        anchor = document.getElementsByTagName('script')[0];
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(script, anchor);
        } else if (document.head) {
            document.head.appendChild(script);
        }
    }

    function waitForClientReady(attempts) {
        var remaining = typeof attempts === 'number' ? attempts : 20;
        var client = getClient();
        var library = getLibraryClient();

        if (!client && library && isClientReady(library)) {
            syncNamedInstance(library);
            client = getClient();
        }

        if (isClientReady(client)) {
            loaderState.waitingForReady = false;
            markReady(client);
            return;
        }

        if (loaderState.waitingForReady) {
            return;
        }

        loaderState.waitingForReady = true;

        (function poll(left) {
            var activeClient = getClient();
            var activeLibrary = getLibraryClient();

            if (!activeClient && activeLibrary && isClientReady(activeLibrary)) {
                syncNamedInstance(activeLibrary);
                activeClient = getClient();
            }

            if (isClientReady(activeClient)) {
                loaderState.waitingForReady = false;
                markReady(activeClient);
                return;
            }

            if (left <= 0) {
                loaderState.waitingForReady = false;
                loaderState.readyCallbacks = [];
                if (!loaderState.timeoutLogSent) {
                    loaderState.timeoutLogSent = true;
                    logToConsole('warn', 'PostHog readiness timed out', {
                        libraryApiReady: isLibraryReady(),
                        rootLoaded: !!(activeLibrary && activeLibrary.__loaded),
                        rootHasConfig: !!(activeLibrary && activeLibrary.config),
                        namedClientLoaded: !!(activeClient && activeClient.__loaded),
                        namedClientHasConfig: !!(activeClient && activeClient.config)
                    });
                }
                return;
            }

            window.setTimeout(function() {
                poll(left - 1);
            }, 250);
        })(remaining);
    }

    function initPosthog(attempts) {
        var remaining = typeof attempts === 'number' ? attempts : 20;
        var client = getClient();
        var library = getLibraryClient();

        if (!cfg.enabled || !cfg.apiKey || !cfg.apiHost) {
            return null;
        }

        if (isClientReady(client)) {
            markReady(client);
            return client;
        }

        if (isClientReady(library)) {
            syncNamedInstance(library);
            markReady(library);
            return getClient();
        }

        if (isLibraryReady() && library && !loaderState.initStarted) {
            loaderState.initStarted = true;
            logToConsole('log', 'PostHog init run', {
                instance: 'posthog',
                apiHost: cfg.apiHost
            });
            try {
                library.init(cfg.apiKey, {
                    api_host: cfg.apiHost,
                    defaults: cfg.defaults || '2026-01-30',
                    autocapture: false,
                    capture_pageview: false,
                    capture_pageleave: false,
                    disable_session_recording: true
                });
                syncNamedInstance(library);
            } catch (error) {
                logToConsole('warn', 'PostHog init failed', error);
            }
            waitForClientReady(remaining);
            return getClient();
        }

        ensureScriptLoaded(function() {
            initPosthog(remaining);
        });

        waitForClientReady(remaining);
        return getClient();
    }

    function whenPosthogReady(callback, attempts) {
        var remaining = typeof attempts === 'number' ? attempts : 20;
        var safeCallback = typeof callback === 'function' ? callback : null;
        var client = getClient();

        if (!safeCallback) {
            return;
        }

        if (isClientReady(client)) {
            markReady(client);
            safeCallback(client);
            return;
        }

        loaderState.readyCallbacks.push(safeCallback);
        initPosthog(remaining);
    }

    function track(eventName, properties) {
        var safeName = sanitizeEventName(eventName);
        var payload;

        if (!safeName) {
            return;
        }

        payload = extend({}, getContext(), sanitizeProperties(properties || {}));
        whenPosthogReady(function(client) {
            logToConsole('log', 'bbaiTrack send', safeName, payload);
            if (safeName === 'checkout_started' && isDebugEnabled()) {
                logToConsole('log', 'checkout_started identity context', {
                    identifier: loaderState.identifyState.id || '',
                    account_id: payload.account_id || '',
                    user_id: payload.user_id || '',
                    license_key_present: !!payload.license_key,
                    site_id: payload.site_id || '',
                    site_hash: payload.site_hash || ''
                });
            }
            try {
                client.capture(safeName, payload);
            } catch (error) {
                // Ignore PostHog capture failures.
            }
        });
    }

    function identify(distinctId, properties) {
        var safeId = distinctId === undefined || distinctId === null ? '' : String(distinctId);
        var safeProperties = sanitizeProperties(properties || {});

        if (!safeId) {
            return;
        }

        whenPosthogReady(function(client) {
            var propsKey = serializeForCompare(safeProperties);

            if (loaderState.identifyState.id === safeId && loaderState.identifyState.propsKey === propsKey) {
                return;
            }

            if (isDebugEnabled()) {
                logToConsole('log', 'posthog identify called', {
                    identifier: safeId,
                    properties: safeProperties
                });
            }

            try {
                if (typeof client.identify === 'function') {
                    client.identify(safeId);
                }
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

            loaderState.identifyState = {
                id: safeId,
                propsKey: propsKey
            };
            registerContext();
        });
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

    function buildIdentityContext(user) {
        var baseContext = sanitizeProperties(cfg.context || {});
        var source = isObject(user) ? sanitizeProperties(user) : {};
        var sourceSite = isObject(source.site) ? source.site : {};

        return sanitizeProperties({
            account_id: source.account_id || source.id || source._id || baseContext.account_id || '',
            user_id: source.user_id || source.id || source._id || baseContext.user_id || baseContext.account_id || '',
            license_key: source.license_key || baseContext.license_key || '',
            site_id: source.site_id || sourceSite.id || sourceSite._id || baseContext.site_id || '',
            site_hash: source.site_hash || baseContext.site_hash || '',
            email: source.email || baseContext.email || '',
            plan: source.plan || source.plan_type || source.planSlug || baseContext.plan || baseContext.plan_type || '',
            plan_type: source.plan_type || source.plan || source.planSlug || baseContext.plan_type || baseContext.plan || '',
            wordpress_user_id: source.wordpress_user_id || baseContext.wordpress_user_id || '',
            plugin_version: baseContext.plugin_version || source.plugin_version || ''
        });
    }

    function resolveIdentifyId(user) {
        var identity = buildIdentityContext(user);
        var priority = ['account_id', 'user_id', 'license_key', 'site_id', 'site_hash'];
        var i;

        for (i = 0; i < priority.length; i++) {
            if (identity[priority[i]]) {
                return String(identity[priority[i]]);
            }
        }

        return '';
    }

    function buildPersonProperties(user) {
        return extend(
            {},
            sanitizeProperties((cfg.identify && cfg.identify.person_properties) || {}),
            buildIdentityContext(user)
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
            updateContext(buildIdentityContext(user));
            updateContext(extractUsageContext(user));

            if (identifyId) {
                identify(identifyId, buildPersonProperties(user));
            }
        });
    }

    window.bbaiAnalytics = {
        getClient: getClient,
        init: initPosthog,
        getContext: getContext,
        resolveSource: resolveSource,
        updateContext: updateContext,
        whenPosthogReady: whenPosthogReady,
        track: track,
        identify: identify,
        pageView: pageView
    };
    window.bbaiInitPosthog = initPosthog;
    window.bbaiWhenPosthogReady = whenPosthogReady;
    window.bbaiTrack = track;
    window.bbaiIdentify = identify;
    window.bbaiPageView = pageView;

    initPosthog();
    bindContextListeners();

    if (cfg.identify && cfg.identify.id) {
        identify(cfg.identify.id, cfg.identify.person_properties || {});
    }
})(window, document);
