/**
 * Dashboard Bridge
 * Initializes React Dashboard component in WordPress admin
 * Fetches data from WordPress REST API and renders the dashboard
 */

(function() {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;

    function readUsageNumber(source, keys) {
        if (!source || typeof source !== 'object') {
            return NaN;
        }

        for (const key of keys) {
            const value = source[key];
            if (value === undefined || value === null || value === '') {
                continue;
            }

            const parsed = parseInt(value, 10);
            if (!Number.isNaN(parsed)) {
                return parsed;
            }
        }

        return NaN;
    }

    function readUsageString(source, keys) {
        if (!source || typeof source !== 'object') {
            return '';
        }

        for (const key of keys) {
            const value = source[key];
            if (typeof value === 'string' && value.trim() !== '') {
                return value;
            }
        }

        return '';
    }

    function normalizeUsage(usageStats) {
        const usage = usageStats && usageStats.data && typeof usageStats.data === 'object'
            ? usageStats.data
            : usageStats;
        const quota = usage && usage.quota && typeof usage.quota === 'object' ? usage.quota : {};
        if (!usage || typeof usage !== 'object') {
            return null;
        }

        let used = readUsageNumber(usage, ['used', 'credits_used', 'creditsUsed']);
        if (Number.isNaN(used)) {
            used = readUsageNumber(quota, ['used', 'credits_used', 'creditsUsed']);
        }
        used = Number.isNaN(used) ? 0 : Math.max(0, used);

        let limit = readUsageNumber(usage, ['limit', 'credits_total', 'creditsTotal', 'creditsLimit', 'total_limit', 'monthly_limit']);
        if (Number.isNaN(limit)) {
            limit = readUsageNumber(quota, ['limit', 'credits_total', 'creditsTotal', 'creditsLimit', 'monthly_limit']);
        }
        limit = Number.isNaN(limit) || limit <= 0 ? 1 : limit;

        let remaining = readUsageNumber(usage, ['remaining', 'credits_remaining', 'creditsRemaining']);
        if (Number.isNaN(remaining)) {
            remaining = readUsageNumber(quota, ['remaining', 'credits_remaining', 'creditsRemaining']);
        }
        if (Number.isNaN(remaining)) {
            remaining = Math.max(0, limit - used);
        }
        remaining = Math.max(0, remaining);

        const resetDate = readUsageString(usage, ['resetDate']) || readUsageString(usage, ['reset_date']) || readUsageString(quota, ['reset_date']);
        let resetTimestamp = readUsageNumber(usage, ['reset_timestamp', 'resetTimestamp', 'reset_ts']);
        if (Number.isNaN(resetTimestamp)) {
            resetTimestamp = readUsageNumber(quota, ['reset_timestamp']);
        }
        let daysUntilReset = readUsageNumber(usage, ['days_until_reset', 'daysUntilReset']);
        if (Number.isNaN(daysUntilReset)) {
            daysUntilReset = readUsageNumber(quota, ['days_until_reset']);
        }

        const planType = readUsageString(usage, ['plan_type', 'plan']) || readUsageString(quota, ['plan_type', 'plan']) || '';

        return {
            ...usage,
            used,
            limit,
            remaining,
            resetDate: resetDate || '',
            reset_date: readUsageString(usage, ['reset_date']) || resetDate || '',
            reset_timestamp: Number.isNaN(resetTimestamp) ? 0 : resetTimestamp,
            days_until_reset: Number.isNaN(daysUntilReset) ? null : Math.max(0, daysUntilReset),
            plan: planType,
            plan_type: planType,
            creditsUsed: used,
            creditsTotal: limit,
            creditsLimit: limit,
            creditsRemaining: remaining,
            quota: {
                used,
                limit,
                remaining,
                reset_date: readUsageString(usage, ['reset_date']) || resetDate || '',
                reset_timestamp: Number.isNaN(resetTimestamp) ? 0 : resetTimestamp,
                plan_type: planType
            }
        };
    }

    function getRootUsageFallback() {
        const root = document.querySelector('[data-bbai-dashboard-root="1"]');
        if (!root) {
            return null;
        }

        return normalizeUsage({
            used: root.getAttribute('data-bbai-credits-used') || 0,
            limit: root.getAttribute('data-bbai-credits-total') || 1,
            remaining: root.getAttribute('data-bbai-credits-remaining') || 0,
            plan: root.getAttribute('data-bbai-is-premium') === '1' ? 'growth' : '',
            plan_type: root.getAttribute('data-bbai-is-premium') === '1' ? 'growth' : '',
            plan_label: root.getAttribute('data-bbai-plan-label') || '',
            source: 'dom_root_fallback'
        });
    }

    // Check if React and ReactDOM are available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        window.BBAI_LOG && window.BBAI_LOG.warn('[BeepBeep AI] React is not loaded. Dashboard requires React and ReactDOM.');
        return;
    }

    // Wait for DOM to be ready
    function initDashboard() {
        const dashboardContainer = document.getElementById('bbai-dashboard-root');
        if (!dashboardContainer) {
            return;
        }

	        const restRoot = window.BBAI?.restRoot || ((window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : '');
	        if (!restRoot) {
	            window.BBAI_LOG && window.BBAI_LOG.warn('[BeepBeep AI] REST root is not available. Dashboard bridge will not initialize.');
	            return;
	        }
	        const apiUrl = `${String(restRoot).replace(/\/$/, '')}/bbai/v1`;
	        const nonce = window.BBAI?.nonce || '';

        // Fetch dashboard data
        async function fetchDashboardData() {
            try {
                // Fetch usage stats
                const usageResponse = await fetch(`${apiUrl}/usage`, {
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Content-Type': 'application/json'
                    }
                });
                const usageStats = await usageResponse.json();

                // Fetch image stats
                const statsResponse = await fetch(`${apiUrl}/stats`, {
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Content-Type': 'application/json'
                    }
                });
                const stats = await statsResponse.json();

                // Fetch queue stats
                let queueStats = { pending: 0, completed_recent: 0, failed: 0 };
                try {
                    const queueResponse = await fetch(`${apiUrl}/queue/stats`, {
                        headers: {
                            'X-WP-Nonce': nonce,
                            'Content-Type': 'application/json'
                        }
                    });
                    if (queueResponse.ok) {
                        queueStats = await queueResponse.json();
                    }
                } catch (e) {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[BeepBeep AI] Could not fetch queue stats:', e);
                }

                const usage = normalizeUsage(usageStats) || getRootUsageFallback() || {
                    used: 0,
                    limit: 1,
                    remaining: 0,
                    resetDate: '',
                    reset_date: '',
                    reset_timestamp: 0,
                    days_until_reset: null,
                    plan: '',
                    plan_type: '',
                    source: 'usage_unavailable',
                    creditsUsed: 0,
                    creditsTotal: 1,
                    creditsLimit: 1,
                    creditsRemaining: 0,
                    quota: {
                        used: 0,
                        limit: 1,
                        remaining: 0,
                        reset_date: '',
                        reset_timestamp: 0,
                        plan_type: ''
                    }
                };
                const plan = usage.plan_type || usage.plan || '';
                // Ensure handleRegenerateAll / handleGenerateMissing can read usage when out of credits
                if (typeof window !== 'undefined') {
                    window.BBAI_DASH = window.BBAI_DASH || {};
                    window.BBAI_DASH.usage = usage;
                    window.BBAI_DASH.initialUsage = usage;
                    window.BBAI_DASH.quota = usage.quota;
                    if (window.BBAI) {
                        window.BBAI.usage = usage;
                        window.BBAI.initialUsage = usage;
                        window.BBAI.quota = usage.quota;
                    }
                }
                return {
                    plan,
                    usageStats: usage,
                    stats: {
                        total: stats.total || 0,
                        with_alt: stats.with_alt || stats.withAlt || 0,
                        missing: stats.missing || 0
                    },
                    queueStats
                };
            } catch (error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[BeepBeep AI] Error fetching dashboard data:', error);
                const fallbackUsage = getRootUsageFallback();
                if (fallbackUsage) {
                    return {
                        plan: fallbackUsage.plan_type || fallbackUsage.plan || '',
                        usageStats: fallbackUsage,
                        stats: { total: 0, with_alt: 0, missing: 0 },
                        queueStats: { pending: 0, completed_recent: 0, failed: 0 }
                    };
                }

                return {
                    plan: '',
                    usageStats: {
                        used: 0,
                        limit: 1,
                        remaining: 0,
                        resetDate: '',
                        reset_date: '',
                        reset_timestamp: 0,
                        days_until_reset: null,
                        plan: '',
                        plan_type: '',
                        source: 'usage_unavailable',
                        creditsUsed: 0,
                        creditsTotal: 1,
                        creditsLimit: 1,
                        creditsRemaining: 0,
                        quota: {
                            used: 0,
                            limit: 1,
                            remaining: 0,
                            reset_date: '',
                            reset_timestamp: 0,
                            plan_type: ''
                        }
                    },
                    stats: { total: 0, with_alt: 0, missing: 0 },
                    queueStats: { pending: 0, completed_recent: 0, failed: 0 }
                };
            }
        }

        // Callback functions
        function handleUpgradeClick() {
            if (typeof window.showUpgradeModal === 'function') {
                window.showUpgradeModal();
                return;
            }
            if (typeof window.openPricingModal === 'function') {
                window.openPricingModal('enterprise');
                return;
            }
            if (typeof window.alttextaiShowModal === 'function') {
                window.alttextaiShowModal();
                return;
            }
            if (typeof window.bbaiApp !== 'undefined' && typeof window.bbaiApp.showModal === 'function') {
                window.bbaiApp.showModal();
                return;
            }
            // Fallback: trigger upgrade modal via DOM
            const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
            if (upgradeBtn) {
                upgradeBtn.click();
            }
        }

        function handleOptimiseNew() {
            return new Promise((resolve, reject) => {
                if (typeof window.bbaiGenerateMissing === 'function') {
                    try {
                        window.bbaiGenerateMissing();
                        resolve();
                    } catch (e) {
                        reject(e);
                    }
                } else {
                    // Fallback: try to trigger via jQuery if available
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).trigger('bbai:generate-missing');
                        resolve();
                    } else {
                        reject(new Error('No optimise function available'));
                    }
                }
            });
        }

        function handleOptimiseAll() {
            return new Promise((resolve, reject) => {
                if (typeof window.bbaiRegenerateAll === 'function') {
                    try {
                        window.bbaiRegenerateAll();
                        resolve();
                    } catch (e) {
                        reject(e);
                    }
                } else {
                    // Fallback: try to trigger via jQuery if available
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).trigger('bbai:regenerate-all');
                        resolve();
                    } else {
                        reject(new Error('No regenerate function available'));
                    }
                }
            });
        }

        function handleOpenLibrary() {
            const libraryUrl = window.location.href.split('?')[0] + '?page=bbai&tab=library';
            window.location.href = libraryUrl;
        }

        function handleManageBilling() {
            const billingUrl = window.location.href.split('?')[0] + '?page=bbai-billing';
            window.location.href = billingUrl;
        }

        // Initialize dashboard
        async function renderDashboard() {
            try {
                const data = await fetchDashboardData();

                // Check if Dashboard component is available
                if (window.bbaiDashboardComponents && window.bbaiDashboardComponents.Dashboard) {
                    const { Dashboard } = window.bbaiDashboardComponents;
                    const root = ReactDOM.createRoot(dashboardContainer);
                    
                    root.render(React.createElement(Dashboard, {
                        plan: data.plan,
                        usageStats: data.usageStats,
                        stats: data.stats,
                        queueStats: data.queueStats,
                        onUpgradeClick: handleUpgradeClick,
                        onOptimiseNew: handleOptimiseNew,
                        onOptimiseAll: handleOptimiseAll,
                        onOpenLibrary: handleOpenLibrary,
                        onManageBilling: handleManageBilling,
                        apiUrl: apiUrl,
                        nonce: nonce
                    }));
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[BeepBeep AI] Dashboard component not found. Make sure dashboard components are loaded.');
                    dashboardContainer.innerHTML = '<p>' + __('Loading dashboard...', 'beepbeep-ai-alt-text-generator') + '</p>';
                }
            } catch (error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[BeepBeep AI] Error rendering dashboard:', error);
                dashboardContainer.innerHTML = '<p>' + __('Error loading dashboard. Please refresh the page.', 'beepbeep-ai-alt-text-generator') + '</p>';
            }
        }

        // Start rendering
        renderDashboard();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
})();
