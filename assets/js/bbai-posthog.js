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
    loaderState.globalHandlersBound = !!loaderState.globalHandlersBound;
    loaderState.replayStarted = !!loaderState.replayStarted;
    loaderState.replayForced = !!loaderState.replayForced;
    loaderState.replayContextId = loaderState.replayContextId || '';
    loaderState.lifecycle = isObject(loaderState.lifecycle) ? loaderState.lifecycle : {};
    loaderState.dedupe = isObject(loaderState.dedupe) ? loaderState.dedupe : {};
    loaderState.identifyState = isObject(loaderState.identifyState) ? loaderState.identifyState : {
        id: '',
        propsKey: ''
    };
    loaderState.aliasState = isObject(loaderState.aliasState) ? loaderState.aliasState : {};

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

    function isSensitiveKey(key) {
        var raw = key === undefined || key === null ? '' : String(key);
        var normalized = raw.toLowerCase();
        var compact = normalized.replace(/[^a-z0-9]/g, '');

        if (normalized === 'site_url' || compact === 'siteurl') {
            return false;
        }

        return (
            /(^|_)(password|token|jwt|secret|nonce|api[_-]?key|license[_-]?key|authorization|auth_header|email|wordpress_user_id)($|_)/i.test(raw) ||
            /^(password|token|jwt|secret|nonce|apikey|licensekey|authorization|authheader|email|wordpressuserid)$/.test(compact) ||
            /email$/.test(compact)
        );
    }

    function sanitizeScalarValue(value) {
        var output;

        if (typeof value !== 'string') {
            return value;
        }

        output = value
            .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[redacted_email]')
            .replace(/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g, '[redacted_jwt]')
            .replace(/https?:\/\/[^\s"'<>]+/gi, '[redacted_url]');

        return output;
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
            if (isSensitiveKey(key)) {
                continue;
            }

            if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
                output[key] = key === 'site_url' ? value : sanitizeScalarValue(value);
                continue;
            }

            if (Array.isArray(value)) {
                output[key] = value.filter(function(item) {
                    return typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean';
                }).map(function(item) {
                    return sanitizeScalarValue(item);
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
                if (isSensitiveKey(nestedKey)) {
                    continue;
                }

                if (
                    typeof value[nestedKey] === 'string' ||
                    typeof value[nestedKey] === 'number' ||
                    typeof value[nestedKey] === 'boolean'
                ) {
                    nested[nestedKey] = nestedKey === 'site_url' ? value[nestedKey] : sanitizeScalarValue(value[nestedKey]);
                }
            }

            if (Object.keys(nested).length) {
                output[key] = nested;
            }
        }

        return output;
    }

    function stripSuperPropertyKeys(input) {
        var output = {};
        var key;
        var normalized;
        var compact;

        if (!isObject(input)) {
            return output;
        }

        for (key in input) {
            if (!Object.prototype.hasOwnProperty.call(input, key)) {
                continue;
            }

            normalized = String(key || '').toLowerCase();
            compact = normalized.replace(/[^a-z0-9]/g, '');
            if (
                normalized === 'license_key' ||
                normalized === 'email' ||
                normalized === 'wordpress_user_id' ||
                compact === 'licensekey' ||
                compact === 'email' ||
                compact === 'wordpressuserid'
            ) {
                continue;
            }
            output[key] = input[key];
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
        var viewport = {};
        var nav = window.navigator || {};

        try {
            viewport = {
                viewport_width: window.innerWidth || 0,
                viewport_height: window.innerHeight || 0
            };
        } catch (error) {
            viewport = {};
        }

        return stripSuperPropertyKeys(extend({}, sanitizeProperties(cfg.context || {}), viewport, {
            current_screen: runtimeContext.page || (cfg.context && cfg.context.page) || 'dashboard',
            browser: nav.userAgent ? String(nav.userAgent).slice(0, 180) : '',
            environment: cfg.environment || ''
        }, runtimeContext));
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

        if (!isDebugEnabled()) {
            return;
        }

        if (!window.console || typeof window.console[level] !== 'function') {
            return;
        }

        args = Array.prototype.slice.call(arguments, 1);
        args.unshift('[BBAI]');
        fn = window.console[level];
        fn.apply(window.console, args);
    }

    function isDebugEnabled() {
        return !!(
            window.BBAI_DEBUG_POSTHOG === true ||
            cfg.debug_posthog === true ||
            cfg.debug === true ||
            /^(localhost|127\.0\.0\.1|\[::1\])$/.test(window.location.hostname || '')
        );
    }

    function hasPosthogConfig() {
        return !!(cfg.enabled && cfg.apiKey && cfg.apiHost);
    }

    function nowMs() {
        return Date.now ? Date.now() : new Date().getTime();
    }

    function shouldDedupe(key, ttlMs) {
        var fullKey = String(key || '');
        var ttl = typeof ttlMs === 'number' ? ttlMs : 1500;
        var last = loaderState.dedupe[fullKey] || 0;
        var now = nowMs();

        if (last && now - last < ttl) {
            return true;
        }

        loaderState.dedupe[fullKey] = now;
        return false;
    }

    function randomReplayBucket() {
        var key = 'bbai_posthog_replay_sample_v1';
        var value = '';

        try {
            value = window.localStorage ? window.localStorage.getItem(key) : '';
            if (!value && window.localStorage) {
                value = String(Math.random());
                window.localStorage.setItem(key, value);
            }
        } catch (error) {
            value = String(Math.random());
        }

        return parseFloat(value || '1');
    }

    function shouldSampleReplay() {
        var rate = parseFloat(cfg.replaySampleRate);
        if (isNaN(rate)) {
            rate = 0.25;
        }
        return randomReplayBucket() < Math.max(0, Math.min(1, rate));
    }

    function shouldForceReplayEverySession() {
        return cfg.forceReplay === true || cfg.forceReplayEverySession === true;
    }

    function shouldUseManualReplayOnly() {
        return cfg.manualReplayOnly === true && !shouldForceReplayEverySession();
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

    function getUrlHost(url) {
        try {
            return new window.URL(String(url || ''), window.location.href).hostname || '';
        } catch (error) {
            return '';
        }
    }

    function isPosthogHost(host) {
        var normalized = String(host || '').toLowerCase();
        var apiHost = getUrlHost(cfg.apiHost || '');
        var assetHost = getUrlHost(getAssetUrl());

        if (!normalized) {
            return false;
        }

        return (
            normalized === apiHost ||
            normalized === assetHost ||
            /(^|\.)posthog\.com$/.test(normalized) ||
            /(^|\.)i\.posthog\.com$/.test(normalized)
        );
    }

    function shouldIgnoreObservedRequest(url) {
        var raw = url === undefined || url === null ? '' : String(url);
        var host;

        if (!raw) {
            return false;
        }

        if (/^(about|data|blob|chrome-extension|moz-extension|safari-extension):/i.test(raw)) {
            return true;
        }

        host = getUrlHost(raw);
        return isPosthogHost(host);
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
            client.register(stripSuperPropertyKeys(getContext()));
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

        if (!hasPosthogConfig()) {
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

        if (!hasPosthogConfig()) {
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
                    capture_pageview: true,
                    capture_pageleave: true,
                    advanced_disable_feature_flags: true,
                    advanced_disable_feature_flags_on_first_load: true,
                    persistence: 'localStorage+cookie',
                    disable_session_recording: shouldUseManualReplayOnly(),
                    rageclick: false,
                    property_denylist: [
                        '$elements',
                        '$el_text',
                        'password',
                        'token',
                        'secret',
                        'nonce',
                        'api_key',
                        'license_key',
                        'authorization'
                    ],
                    mask_all_text: false,
                    mask_all_element_attributes: false,
                    session_recording: {
                        maskAllInputs: true,
                        maskInputOptions: {
                            password: true,
                            email: true,
                            text: false,
                            textarea: false,
                            number: false,
                            search: false,
                            tel: true,
                            url: false
                        },
                        maskTextSelector: [
                            '[data-bbai-mask]',
                            '[data-bbai-sensitive]',
                            '.bbai-sensitive',
                            'input[name*="password"]',
                            'input[name*="api"]',
                            'input[name*="token"]',
                            'input[name*="secret"]',
                            'input[name*="license"]',
                            'input[name*="key"]',
                            '[autocomplete="cc-number"]',
                            '[autocomplete="cc-csc"]',
                            '[autocomplete="cc-exp"]'
                        ].join(','),
                        blockSelector: [
                            '[data-bbai-replay-block]',
                            'iframe[src*="stripe"]',
                            '.StripeElement'
                        ].join(','),
                        captureConsoleLog: true
                    },
                    capture_console_log: true,
                    capture_performance: true,
                    loaded: function(loadedClient) {
                        tryStartReplay(shouldForceReplayEverySession() ? 'admin_session' : 'sampled_session', shouldForceReplayEverySession());
                        markReady(loadedClient);
                    }
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

        if (!hasPosthogConfig()) {
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

        payload = stripSuperPropertyKeys(extend({}, getContext(), sanitizeProperties(properties || {})));
        if (shouldDedupe(safeName + ':' + serializeForCompare(payload), 250)) {
            return;
        }
        whenPosthogReady(function(client) {
            logToConsole('debug', 'bbaiTrack send', safeName, payload);
            if (safeName === 'checkout_started' && isDebugEnabled()) {
                logToConsole('debug', 'checkout_started identity context', {
                    identifier: loaderState.identifyState.id || '',
                    license_id: payload.license_id || '',
                    account_id: payload.account_id || '',
                    user_id: payload.user_id || '',
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

    function addReplayMarker(name, properties) {
        var safeName = sanitizeEventName(name) || 'replay_marker';
        var payload = extend({ marker: safeName }, sanitizeProperties(properties || {}));

        track('replay_marker', payload);
        whenPosthogReady(function(client) {
            try {
                if (typeof client.capture === 'function') {
                    client.capture('$snapshot', {
                        $snapshot_source: 'bbai_marker',
                        marker: safeName,
                        bbai_context: payload
                    });
                }
            } catch (error) {
                // Marker support varies; the event above is the durable breadcrumb.
            }
        });
    }

    function tryStartReplay(reason, force) {
        var shouldForce = force === true;

        if (!cfg.replayEnabled) {
            return;
        }
        if (loaderState.replayStarted && (!shouldForce || loaderState.replayForced)) {
            return;
        }
        if (!shouldForce && !shouldSampleReplay()) {
            return;
        }

        whenPosthogReady(function(client) {
            try {
                if (typeof client.startSessionRecording === 'function') {
                    client.startSessionRecording(shouldForce);
                    loaderState.replayStarted = true;
                    loaderState.replayForced = loaderState.replayForced || shouldForce;
                    if (typeof client.register === 'function') {
                        client.register({
                            bbai_replay_reason: reason || (shouldForce ? 'forced' : 'sampled'),
                            bbai_replay_forced: shouldForce
                        });
                    }
                    logToConsole('log', 'PostHog replay started', {
                        reason: reason || '',
                        forced: shouldForce,
                        session_id: getSessionId()
                    });
                }
            } catch (error) {
                logToConsole('warn', 'PostHog replay start failed', error);
            }
        });
    }

    function getSessionId() {
        var id = '';
        var client = getClient();
        try {
            if (client && typeof client.get_session_id === 'function') {
                id = client.get_session_id();
            } else if (client && typeof client.getSessionId === 'function') {
                id = client.getSessionId();
            }
        } catch (error) {
            id = '';
        }
        return id || '';
    }

    function startReplayContext(properties) {
        var contextId = 'ctx_' + nowMs() + '_' + Math.floor(Math.random() * 100000);
        var payload = extend({
            replay_context_id: contextId,
            posthog_session_id: getSessionId()
        }, sanitizeProperties(properties || {}));

        loaderState.replayContextId = contextId;
        updateContext(payload);
        tryStartReplay(payload.reason || 'critical_context', true);
        addReplayMarker('replay_context_started', payload);

        if (isDebugEnabled()) {
            logToConsole('debug', 'Replay context', payload);
        }

        return contextId;
    }

    function captureError(error, context) {
        var err = error || {};
        var payload = sanitizeProperties(extend({
            error_name: err.name || '',
            error_message: err.message || String(err || ''),
            error_code: err.code || '',
            source: 'frontend',
            stack: isDebugEnabled() && err.stack ? String(err.stack).slice(0, 2000) : ''
        }, context || {}));

        tryStartReplay('frontend_error', true);
        track('frontend_error_captured', payload);
        addReplayMarker('frontend_error', payload);
    }

    function trackGenerationLifecycle(eventName, properties, options) {
        var safeName = sanitizeEventName(eventName);
        var props = sanitizeProperties(properties || {});
        var opts = isObject(options) ? options : {};
        var jobId = props.job_id || props.jobId || '';
        var key = jobId || props.generation_run_id || 'default';

        if (!safeName) {
            return;
        }

        loaderState.lifecycle[key] = extend({}, loaderState.lifecycle[key] || {}, {
            last_event: safeName,
            last_event_at: nowMs(),
            job_id: jobId
        }, props);

        if (
            safeName === 'generation_request_started' ||
            safeName === 'generation_started' ||
            safeName === 'generation_job_created' ||
            safeName === 'generation_resumed' ||
            safeName === 'generation_resume_success' ||
            safeName === 'checkout_started' ||
            safeName === 'upgrade_clicked' ||
            safeName === 'signup_started'
        ) {
            tryStartReplay(safeName, true);
        }

        if (
            safeName === 'generation_failed' ||
            safeName === 'generation_stuck' ||
            safeName === 'generation_recovery_failed' ||
            safeName === 'generation_click_noop' ||
            safeName === 'checkout_failed' ||
            safeName === 'ajax_request_failed' ||
            safeName === 'polling_failed'
        ) {
            tryStartReplay(safeName, true);
            addReplayMarker(safeName, props);
        }

        if (
            safeName === 'review_queue_opened' ||
            safeName === 'review_item_approved' ||
            safeName === 'review_item_rejected' ||
            safeName === 'success_state_shown' ||
            safeName === 'fully_optimised_state_shown' ||
            safeName === 'quota_exhausted_state_shown'
        ) {
            addReplayMarker(safeName, props);
        }

        if (jobId) {
            updateContext({ generation_job_id: jobId });
        }

        if (isDebugEnabled()) {
            logToConsole('debug', 'generation lifecycle', safeName, loaderState.lifecycle[key]);
        }

        if (!opts.observeOnly) {
            track(safeName, props);
        }
    }

    function bindGlobalObservability() {
        var clickTimes = [];
        var lastLoading = {};
        var ajaxErrorBound = false;

        if (loaderState.globalHandlersBound) {
            return;
        }
        loaderState.globalHandlersBound = true;

        window.addEventListener('error', function(event) {
            captureError(event.error || new Error(event.message || 'Uncaught error'), {
                type: 'uncaught_exception',
                filename: event.filename || '',
                line: event.lineno || 0,
                column: event.colno || 0
            });
        });

        window.addEventListener('unhandledrejection', function(event) {
            captureError(event.reason || new Error('Unhandled promise rejection'), {
                type: 'promise_rejection'
            });
        });

        if (window.fetch && !window.fetch.__bbaiObserved) {
            (function(originalFetch) {
                var observedFetch = function(input, init) {
                    var started = nowMs();
                    var url = typeof input === 'string' ? input : (input && input.url) || '';
                    var method = (init && init.method) || (input && input.method) || 'GET';

                    return originalFetch.apply(window, arguments).then(function(response) {
                        if (response && response.status >= 400 && !shouldIgnoreObservedRequest(url)) {
                            track(response.status === 403 ? 'auth_expired' : 'ajax_request_failed', {
                                endpoint: String(url).slice(0, 180),
                                http_status: response.status,
                                request_type: method,
                                duration_ms: nowMs() - started,
                                current_screen: getPageContext()
                            });
                        }
                        return response;
                    }).catch(function(error) {
                        if (shouldIgnoreObservedRequest(url)) {
                            throw error;
                        }
                        if (error && error.name === 'AbortError') {
                            throw error;
                        }
                        track('ajax_request_failed', {
                            endpoint: String(url).slice(0, 180),
                            request_type: method,
                            duration_ms: nowMs() - started,
                            error_message: error && error.message ? error.message : String(error || ''),
                            current_screen: getPageContext()
                        });
                        throw error;
                    });
                };
                observedFetch.__bbaiObserved = true;
                window.fetch = observedFetch;
            })(window.fetch);
        }

        document.addEventListener('click', function(event) {
            var target = event.target && event.target.closest ? event.target.closest('button, a, [role="button"], [data-action], [data-bbai-action]') : null;
            var now = nowMs();
            var signal;

            if (!target) {
                return;
            }

            clickTimes = clickTimes.filter(function(item) {
                return now - item.at < 1200;
            });
            signal = [
                target.getAttribute('data-action') || '',
                target.getAttribute('data-bbai-action') || '',
                target.getAttribute('aria-label') || '',
                target.textContent || ''
            ].join('|').replace(/\s+/g, ' ').trim().slice(0, 160);
            clickTimes.push({ at: now, signal: signal });

            if (clickTimes.length >= 3 && clickTimes.filter(function(item) { return item.signal === signal; }).length >= 3) {
                track('rage_click_detected', {
                    click_signal: signal,
                    current_screen: getPageContext()
                });
                tryStartReplay('rage_click_detected', true);
            }

            if (target.disabled || target.getAttribute('aria-disabled') === 'true') {
                track('dead_click_detected', {
                    click_signal: signal,
                    current_screen: getPageContext(),
                    reason: 'disabled_control'
                });
                tryStartReplay('dead_click_detected', true);
            }
        }, true);

        window.setInterval(function() {
            var nodes = document.querySelectorAll('[aria-busy="true"], .is-loading, .bbai-btn-loading, [data-bbai-loading="true"]');
            var seen = {};
            var i;
            var id;

            for (i = 0; i < nodes.length; i++) {
                id = nodes[i].id || nodes[i].getAttribute('data-action') || nodes[i].getAttribute('data-bbai-action') || nodes[i].className || 'loading';
                id = String(id).slice(0, 120);
                seen[id] = true;
                if (!lastLoading[id]) {
                    lastLoading[id] = nowMs();
                    continue;
                }
                if (nowMs() - lastLoading[id] > 10000 && !shouldDedupe('loading:' + id, 30000)) {
                    track('ui_loading_timeout', {
                        loading_target: id,
                        duration_ms: nowMs() - lastLoading[id],
                        current_screen: getPageContext()
                    });
                    tryStartReplay('ui_loading_timeout', true);
                }
            }

            Object.keys(lastLoading).forEach(function(key) {
                if (!seen[key]) {
                    delete lastLoading[key];
                }
            });
        }, 2500);

        if (window.PerformanceObserver) {
            try {
                new window.PerformanceObserver(function(list) {
                    list.getEntries().forEach(function(entry) {
                        if (entry.duration > 250 && !shouldDedupe('longtask:' + Math.round(entry.startTime), 10000)) {
                            track('long_task_detected', {
                                duration_ms: Math.round(entry.duration),
                                current_screen: getPageContext()
                            });
                        }
                    });
                }).observe({ entryTypes: ['longtask'] });
            } catch (error) {
                // Long task support varies by browser.
            }
        }

        (function waitForJqueryAjaxError(attempts) {
            if (ajaxErrorBound) {
                return;
            }
            if (window.jQuery && typeof window.jQuery === 'function') {
                ajaxErrorBound = true;
                window.jQuery(document).ajaxError(function(_event, xhr, settings, thrownError) {
                    var endpoint = settings && settings.data
                        ? String(settings.data).match(/action=([^&]+)/)
                        : null;
                    var action = endpoint && endpoint[1] ? decodeURIComponent(endpoint[1]) : '';
                    var status = xhr && xhr.status ? xhr.status : 0;
                    var eventName = status === 403 ? 'auth_expired' : 'ajax_request_failed';

                    track(eventName, {
                        endpoint: action || (settings && settings.url ? String(settings.url).slice(0, 160) : ''),
                        http_status: status,
                        request_type: settings && settings.type ? settings.type : '',
                        error_code: status === 403 ? 'nonce_or_auth_failed' : '',
                        error_message: thrownError ? String(thrownError).slice(0, 240) : '',
                        current_screen: getPageContext()
                    });
                    if (status === 403 || /beepbeepai_|bbai_/.test(action)) {
                        tryStartReplay(eventName, true);
                    }
                });
                return;
            }
            if (attempts > 0) {
                window.setTimeout(function() {
                    waitForJqueryAjaxError(attempts - 1);
                }, 500);
            }
        })(20);
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
                logToConsole('debug', 'posthog identify called', {
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

    function getAliasStorageKey(aliasId, siteHash) {
        return 'bbai_posthog_alias_v1:' + String(siteHash || '') + ':' + String(aliasId || '');
    }

    function hasAliasGuard(aliasId, siteHash) {
        var key = getAliasStorageKey(aliasId, siteHash);
        if (!aliasId || !siteHash || aliasId === siteHash) {
            return true;
        }
        if (loaderState.aliasState[key]) {
            return true;
        }
        try {
            return window.localStorage && window.localStorage.getItem(key) === '1';
        } catch (error) {
            return false;
        }
    }

    function setAliasGuard(aliasId, siteHash) {
        var key = getAliasStorageKey(aliasId, siteHash);
        loaderState.aliasState[key] = true;
        try {
            if (window.localStorage) {
                window.localStorage.setItem(key, '1');
            }
        } catch (error) {
            // Ignore storage failures; in-memory guard still prevents loops.
        }
    }

    function aliasAccount(aliasId, siteHash) {
        var safeAlias = aliasId === undefined || aliasId === null ? '' : String(aliasId);
        var safeSite = siteHash === undefined || siteHash === null ? '' : String(siteHash);

        if (hasAliasGuard(safeAlias, safeSite)) {
            return;
        }

        whenPosthogReady(function(client) {
            if (hasAliasGuard(safeAlias, safeSite)) {
                return;
            }
            try {
                if (typeof client.alias === 'function') {
                    client.alias(safeAlias, safeSite);
                    setAliasGuard(safeAlias, safeSite);
                    logToConsole('debug', 'posthog alias called', {
                        alias_id: safeAlias,
                        site_hash: safeSite
                    });
                }
            } catch (error) {
                // Ignore alias failures; the guard only persists after success.
            }
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
        runtimeContext = stripSuperPropertyKeys(extend({}, runtimeContext, sanitizeProperties(properties || {})));
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
            license_id: source.license_id || source.licenseId || baseContext.license_id || '',
            account_id: source.account_id || source.id || source._id || baseContext.account_id || '',
            user_id: source.user_id || source.id || source._id || baseContext.user_id || baseContext.account_id || '',
            site_id: source.site_id || sourceSite.id || sourceSite._id || baseContext.site_id || '',
            site_hash: source.site_hash || baseContext.site_hash || '',
            plan: source.plan || source.plan_type || source.planSlug || baseContext.plan || baseContext.plan_type || '',
            plan_type: source.plan_type || source.plan || source.planSlug || baseContext.plan_type || baseContext.plan || '',
            site_url: source.site_url || baseContext.site_url || '',
            is_trial: source.is_trial !== undefined ? !!source.is_trial : !!baseContext.is_trial,
            is_internal: source.is_internal !== undefined ? !!source.is_internal : !!baseContext.is_internal,
            plugin_version: baseContext.plugin_version || source.plugin_version || '',
            first_seen_at: source.first_seen_at || baseContext.first_seen_at || '',
            signup_source: source.signup_source || baseContext.signup_source || '',
            wp_admin_page: baseContext.page || source.wp_admin_page || ''
        });
    }

    function resolveIdentifyId(user) {
        var identity = buildIdentityContext(user);
        var priority = ['license_id', 'account_id', 'site_hash'];
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
            var identityContext = buildIdentityContext(user);
            var aliasId = identityContext.license_id || identityContext.account_id || '';
            var siteHash = identityContext.site_hash || (cfg.context && cfg.context.site_hash) || '';

            updateContext({ is_logged_in: true });
            updateContext(identityContext);
            updateContext(extractUsageContext(user));

            if (aliasId && siteHash) {
                aliasAccount(aliasId, siteHash);
            }
            if (identifyId) {
                identify(identifyId, buildPersonProperties(user));
            }
        });

        document.addEventListener('bbai:analytics', function(event) {
            var detail = event && event.detail ? extend({}, event.detail) : {};
            var eventName = detail.event || '';
            if (!eventName) {
                return;
            }
            delete detail.event;
            if (
                /^generation_/.test(eventName) ||
                eventName === 'checkout_started' ||
                eventName === 'checkout_failed' ||
                eventName === 'upgrade_clicked' ||
                eventName === 'signup_started' ||
                eventName === 'signup_failed' ||
                eventName === 'ajax_request_failed' ||
                eventName === 'polling_failed'
            ) {
                trackGenerationLifecycle(eventName, detail);
                return;
            }

            if (sanitizeEventName(eventName)) {
                track(eventName, detail);
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
        aliasAccount: aliasAccount,
        pageView: pageView,
        captureError: captureError,
        startReplayContext: startReplayContext,
        trackGenerationLifecycle: trackGenerationLifecycle,
        startReplay: tryStartReplay,
        getSessionId: getSessionId
    };
    window.bbaiInitPosthog = initPosthog;
    window.bbaiWhenPosthogReady = whenPosthogReady;
    window.bbaiTrack = track;
    window.bbaiIdentify = identify;
    window.bbaiAliasAccount = aliasAccount;
    window.bbaiCaptureError = captureError;
    window.bbaiStartReplayContext = startReplayContext;
    window.bbaiTrackGenerationLifecycle = trackGenerationLifecycle;
    window.bbaiPageView = pageView;

    bindContextListeners();
    if (hasPosthogConfig()) {
        initPosthog();
        bindGlobalObservability();
    }

    if (hasPosthogConfig() && cfg.identify && cfg.identify.id) {
        if (cfg.context && cfg.context.site_hash && cfg.identify.id !== cfg.context.site_hash) {
            aliasAccount(cfg.identify.id, cfg.context.site_hash);
        }
        identify(cfg.identify.id, cfg.identify.person_properties || {});
    }
})(window, document);
