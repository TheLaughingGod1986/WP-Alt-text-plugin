/**
 * Usage Components Bridge
 * Initializes React components for multi-user token visualization
 */

(function() {
    'use strict';

    // Check if React and ReactDOM are available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        console.warn('[BeepBeep AI] React is not loaded. Usage components require React and ReactDOM.');
        return;
    }

    // Wait for DOM to be ready
    function initComponents() {
        const apiUrl = window.BBAI?.restRoot || '/wp-json/';
        const nonce = window.BBAI?.nonce || '';

        // Initialize MultiUserTokenBar in dashboard
        const tokenBarContainer = document.getElementById('bbai-multiuser-token-bar-root');
        if (tokenBarContainer) {
            try {
                // Dynamic import of React components
                // Note: In production, these should be bundled with webpack/build tool
                // For now, we'll create a simple initialization
                console.log('[BeepBeep AI] Initializing multi-user token bar...');
                
                // The actual React components should be loaded via webpack/build process
                // This is a placeholder that will be replaced with actual component loading
                if (window.bbaiUsageComponents) {
                    const { MultiUserTokenBar } = window.bbaiUsageComponents;
                    if (MultiUserTokenBar) {
                        const root = ReactDOM.createRoot(tokenBarContainer);
                        root.render(React.createElement(MultiUserTokenBar, {
                            apiUrl: apiUrl,
                            nonce: nonce
                        }));
                    }
                }
            } catch (error) {
                console.error('[BeepBeep AI] Error initializing token bar:', error);
            }
        }

        // Initialize MultiUserUsageTab in team-usage tab
        const usageTabContainer = document.getElementById('bbai-multiuser-usage-tab-root');
        if (usageTabContainer) {
            try {
                console.log('[BeepBeep AI] Initializing team usage tab...');
                
                if (window.bbaiUsageComponents) {
                    const { MultiUserUsageTab } = window.bbaiUsageComponents;
                    if (MultiUserUsageTab) {
                        const root = ReactDOM.createRoot(usageTabContainer);
                        root.render(React.createElement(MultiUserUsageTab, {
                            apiUrl: apiUrl,
                            nonce: nonce
                        }));
                    }
                }
            } catch (error) {
                console.error('[BeepBeep AI] Error initializing usage tab:', error);
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

