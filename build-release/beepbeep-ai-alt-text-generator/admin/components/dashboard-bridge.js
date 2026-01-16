/**
 * Dashboard Bridge
 * Initializes React Dashboard component in WordPress admin
 * Fetches data from WordPress REST API and renders the dashboard
 */

(function() {
    'use strict';

    // Check if React and ReactDOM are available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        console.warn('[BeepBeep AI] React is not loaded. Dashboard requires React and ReactDOM.');
        return;
    }

    // Wait for DOM to be ready
    function initDashboard() {
        const dashboardContainer = document.getElementById('bbai-dashboard-root');
        if (!dashboardContainer) {
            return;
        }

        const apiUrl = window.BBAI?.restRoot || '/wp-json/bbai/v1';
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
                    console.warn('[BeepBeep AI] Could not fetch queue stats:', e);
                }

                // Determine plan
                let plan = 'free';
                if (usageStats.plan) {
                    plan = usageStats.plan.toLowerCase();
                } else if (usageStats.limit >= 10000) {
                    plan = 'agency';
                } else if (usageStats.limit >= 1000) {
                    plan = 'growth';
                }

                return {
                    plan,
                    usageStats: {
                        used: usageStats.used || 0,
                        limit: usageStats.limit || (plan === 'free' ? 50 : plan === 'growth' ? 1000 : 10000),
                        remaining: usageStats.remaining || 0,
                        resetDate: usageStats.reset_date || usageStats.resetDate || ''
                    },
                    stats: {
                        total: stats.total || 0,
                        with_alt: stats.with_alt || stats.withAlt || 0,
                        missing: stats.missing || 0
                    },
                    queueStats
                };
            } catch (error) {
                console.error('[BeepBeep AI] Error fetching dashboard data:', error);
                // Return defaults on error
                return {
                    plan: 'free',
                    usageStats: { used: 0, limit: 50, remaining: 50, resetDate: '' },
                    stats: { total: 0, with_alt: 0, missing: 0 },
                    queueStats: { pending: 0, completed_recent: 0, failed: 0 }
                };
            }
        }

        // Callback functions
        function handleUpgradeClick() {
            if (typeof window.bbaiShowUpgradeModal === 'function') {
                window.bbaiShowUpgradeModal();
            } else if (typeof window.alttextaiShowUpgradeModal === 'function') {
                window.alttextaiShowUpgradeModal();
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
                    console.warn('[BeepBeep AI] Dashboard component not found. Make sure dashboard components are loaded.');
                    dashboardContainer.innerHTML = '<p>Loading dashboard...</p>';
                }
            } catch (error) {
                console.error('[BeepBeep AI] Error rendering dashboard:', error);
                dashboardContainer.innerHTML = '<p>Error loading dashboard. Please refresh the page.</p>';
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