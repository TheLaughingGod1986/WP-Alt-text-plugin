/**
 * Usage Components Bridge
 * Initializes React components for multi-user token visualization
 */

(function() {
    'use strict';

    const reactRuntime = window.React || (window.wp && window.wp.element ? window.wp.element : null);
    const reactDomRuntime = window.ReactDOM || (window.wp && window.wp.element && typeof window.wp.element.createRoot === 'function' ? window.wp.element : null);

    // Check if React runtime is available
    if (!reactRuntime || !reactDomRuntime || typeof reactDomRuntime.createRoot !== 'function') {
        window.BBAI_LOG && window.BBAI_LOG.warn('[BeepBeep AI] React is not loaded. Usage components require React and ReactDOM.');
        return;
    }

    // Wait for DOM to be ready
    function initComponents() {
        const apiRoot = (window.BBAI && window.BBAI.restRoot) || (window.wpApiSettings && window.wpApiSettings.root) || '';
        if (!apiRoot) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[BeepBeep AI] REST API root is not available (missing localized rest root).');
            return;
        }

        const apiUrl = apiRoot.endsWith('/') ? apiRoot : (apiRoot + '/');
        const nonce = (window.BBAI && window.BBAI.nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || '';

        // Initialize MultiUserTokenBar in dashboard
        const tokenBarContainer = document.getElementById('bbai-multiuser-token-bar-root');
        if (tokenBarContainer) {
            try {
                // Dynamic import of React components
                // Note: In production, these should be bundled with webpack/build tool
                // For now, we'll create a simple initialization
                window.BBAI_LOG && window.BBAI_LOG.log('[BeepBeep AI] Initializing multi-user token bar...');
                
                // The actual React components should be loaded via webpack/build process
                // This is a placeholder that will be replaced with actual component loading
                if (window.bbaiUsageComponents) {
                    const { MultiUserTokenBar } = window.bbaiUsageComponents;
                    if (MultiUserTokenBar) {
                        const root = reactDomRuntime.createRoot(tokenBarContainer);
                        root.render(reactRuntime.createElement(MultiUserTokenBar, {
                            apiUrl: apiUrl,
                            nonce: nonce
                        }));
                    }
                }
            } catch (error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[BeepBeep AI] Error initializing token bar:', error);
            }
        }

        // Initialize MultiUserUsageTab in team-usage tab
        const usageTabContainer = document.getElementById('bbai-multiuser-usage-tab-root');
        if (usageTabContainer) {
            try {
                window.BBAI_LOG && window.BBAI_LOG.log('[BeepBeep AI] Initializing team usage tab...');
                
                if (window.bbaiUsageComponents) {
                    const { MultiUserUsageTab } = window.bbaiUsageComponents;
                    if (MultiUserUsageTab) {
                        const root = reactDomRuntime.createRoot(usageTabContainer);
                        root.render(reactRuntime.createElement(MultiUserUsageTab, {
                            apiUrl: apiUrl,
                            nonce: nonce
                        }));
                    }
                }
            } catch (error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[BeepBeep AI] Error initializing usage tab:', error);
            }
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComponents);
    } else {
        initComponents();
    }

    // Re-initialize when tab changes (for SPA-like behavior)
    document.addEventListener('click', function(e) {
        const tabLink = e.target.closest('.bbai-nav-link');
        if (tabLink) {
            setTimeout(initComponents, 100);
        }
    });
})();
