/**
 * BeepBeep AI Context-Aware Upgrade Prompts
 * Smart upgrade prompts based on user behavior
 */

(function() {
    'use strict';

    const bbaiContextUpgrades = {
        upgradePromptShown: false,
        exitIntentDetected: false,
        lastBulkOperation: null,

        init: function() {
            this.trackBulkOperations();
            this.initExitIntent();
            this.trackUsage();
            this.trackLibraryViews();
            this.trackSingleGenerations();
            this.trackScrollBehavior();
            this.trackTimeOnPage();
            this.trackHoverBehavior();
        },

        /**
         * Track bulk operations for context-aware prompts
         */
        trackBulkOperations: function() {
            // Listen for bulk operation completion
            document.addEventListener('bbai:bulk:complete', (e) => {
                const data = e.detail || {};
                this.lastBulkOperation = {
                    count: data.count || 0,
                    timestamp: Date.now()
                };

                // Show upgrade prompt after successful bulk operation (if free user)
                if (this.isFreeUser() && data.count > 0) {
                    setTimeout(() => {
                        this.showPostBulkUpgradePrompt(data.count);
                    }, 2000);
                }
            });

            // Listen for generation success
            document.addEventListener('bbai:generation:success', (e) => {
                const count = e.detail?.count || 0;
                if (count > 0 && this.isFreeUser()) {
                    this.trackGenerationSuccess(count);
                }
            });
        },

        /**
         * Initialize exit-intent detection
         */
        initExitIntent: function() {
            // Only for free users
            if (!this.isFreeUser()) return;

            let exitIntentTriggered = false;

            document.addEventListener('mouseout', (e) => {
                // Check if mouse is leaving the viewport from the top
                if (!e.toElement && !e.relatedTarget && e.clientY < 10) {
                    if (!exitIntentTriggered && !this.upgradePromptShown) {
                        exitIntentTriggered = true;
                        this.showExitIntentPrompt();
                    }
                }
            });
        },

        /**
         * Track usage for smart messaging
         */
        trackUsage: function() {
            // Check usage stats periodically
            if (typeof BBAI_DASH !== 'undefined' && BBAI_DASH.initialUsage) {
                const usage = BBAI_DASH.initialUsage;
                const percent = usage.limit > 0 ? (usage.used / usage.limit) * 100 : 0;

                // Show upgrade prompt when approaching limit
                if (percent >= 80 && percent < 95 && this.isFreeUser()) {
                    this.showApproachingLimitPrompt(usage);
                }
            }
        },

        /**
         * Show upgrade prompt after bulk operation
         */
        showPostBulkUpgradePrompt: function(count) {
            if (this.upgradePromptShown) return;

            let message = '';
            if (count > 500) {
                message = `You just optimized ${count} images! Looks like you have a large library. Bulk optimization is available in Growth.`;
            } else if (count > 100) {
                message = `Great job optimizing ${count} images! Upgrade to Growth for faster processing and more credits.`;
            } else {
                message = `You've optimized ${count} images! Upgrade to Growth for 1,000 monthly credits.`;
            }

            this.showUpgradeToast(message, {
                action: () => {
                    const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                    if (upgradeBtn) {
                        upgradeBtn.click();
                    }
                },
                actionLabel: 'View Plans'
            });

            this.upgradePromptShown = true;
        },

        /**
         * Show exit-intent prompt
         */
        showExitIntentPrompt: function() {
            // Only show once per session
            if (this.exitIntentDetected) return;
            this.exitIntentDetected = true;

            const message = 'Wait! Upgrade to Growth and unlock 1,000 monthly credits, bulk processing, and priority queues.';
            
            if (window.bbaiToast) {
                window.bbaiToast.info(message, {
                    duration: 10000,
                    action: () => {
                        const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                        if (upgradeBtn) {
                            upgradeBtn.click();
                        }
                    },
                    actionLabel: 'Upgrade Now',
                    persistent: false
                });
            }
        },

        /**
         * Show approaching limit prompt
         */
        showApproachingLimitPrompt: function(usage) {
            const remaining = usage.limit - usage.used;
            const message = `You're running low on credits (${remaining} remaining). Upgrade to Growth for 1,000 monthly credits.`;

            if (window.bbaiToast) {
                window.bbaiToast.warning(message, {
                    duration: 8000,
                    action: () => {
                        const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                        if (upgradeBtn) {
                            upgradeBtn.click();
                        }
                    },
                    actionLabel: 'Upgrade Now'
                });
            }
        },

        /**
         * Show upgrade toast with action
         */
        showUpgradeToast: function(message, options = {}) {
            if (window.bbaiToast) {
                window.bbaiToast.info(message, {
                    duration: options.duration || 8000,
                    action: options.action,
                    actionLabel: options.actionLabel || 'Upgrade',
                    persistent: false
                });
            }
        },

        /**
         * Track generation success for analytics
         */
        trackGenerationSuccess: function(count) {
            // Could send to analytics here
            if (count > 50 && this.isFreeUser()) {
                // Large batch - might want to upgrade
                setTimeout(() => {
                    if (!this.upgradePromptShown) {
                        this.showPostBulkUpgradePrompt(count);
                    }
                }, 3000);
            }
        },

        /**
         * Track library views for context-aware prompts
         */
        trackLibraryViews: function() {
            // Check if we're on library tab
            const libraryTab = document.querySelector('[data-tab="library"]');
            if (!libraryTab) return;

            // Count images in library
            const imageRows = document.querySelectorAll('.bbai-library-container tbody tr');
            const imageCount = imageRows.length;

            // Show upgrade prompt if library has many images
            if (imageCount > 100 && this.isFreeUser() && !this.upgradePromptShown) {
                setTimeout(() => {
                    this.showLargeLibraryPrompt(imageCount);
                }, 5000); // Show after 5 seconds on library tab
            }
        },

        /**
         * Track single image generations
         */
        trackSingleGenerations: function() {
            let singleGenCount = 0;
            const maxSingleGens = 10;

            document.addEventListener('bbai:generation:success', (e) => {
                const type = e.detail?.type || '';
                if (type === 'single' || !type) {
                    singleGenCount++;
                    
                    // After multiple single generations, suggest bulk
                    if (singleGenCount >= maxSingleGens && this.isFreeUser() && !this.upgradePromptShown) {
                        this.showBulkSuggestionPrompt();
                        singleGenCount = 0; // Reset counter
                    }
                }
            });
        },

        /**
         * Track scroll behavior
         */
        trackScrollBehavior: function() {
            let scrollDepth = 0;
            const scrollThreshold = 0.75; // 75% of page

            window.addEventListener('scroll', () => {
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight;
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const currentDepth = scrollTop / (documentHeight - windowHeight);

                if (currentDepth >= scrollThreshold && scrollDepth < scrollThreshold && this.isFreeUser() && !this.upgradePromptShown) {
                    this.showScrollDepthPrompt();
                    scrollDepth = currentDepth;
                }
            });
        },

        /**
         * Track time on page
         */
        trackTimeOnPage: function() {
            const timeThreshold = 5 * 60 * 1000; // 5 minutes
            let timeOnPage = 0;

            setInterval(() => {
                timeOnPage += 30000; // Check every 30 seconds

                if (timeOnPage >= timeThreshold && this.isFreeUser() && !this.upgradePromptShown) {
                    this.showTimeBasedPrompt();
                    timeOnPage = 0; // Reset to avoid showing multiple times
                }
            }, 30000);
        },

        /**
         * Track hover behavior on upgrade buttons
         */
        trackHoverBehavior: function() {
            const upgradeButtons = document.querySelectorAll('[data-action="show-upgrade-modal"]');
            
            upgradeButtons.forEach(btn => {
                let hoverCount = 0;
                
                btn.addEventListener('mouseenter', () => {
                    hoverCount++;
                    
                    // If user hovers multiple times but doesn't click, show helpful prompt
                    if (hoverCount >= 3 && this.isFreeUser()) {
                        setTimeout(() => {
                            if (!btn.matches(':hover')) {
                                this.showHoverPrompt();
                            }
                        }, 2000);
                    }
                });
            });
        },

        /**
         * Show large library prompt
         */
        showLargeLibraryPrompt: function(count) {
            const message = `You have ${count} images in your library. Upgrade to Growth for bulk processing and faster optimization.`;
            this.showUpgradeToast(message, {
                action: () => {
                    const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                    if (upgradeBtn) upgradeBtn.click();
                },
                actionLabel: 'Upgrade Now',
                duration: 10000
            });
            this.upgradePromptShown = true;
        },

        /**
         * Show bulk suggestion prompt
         */
        showBulkSuggestionPrompt: function() {
            const message = 'You\'ve been generating alt text one by one. Upgrade to Growth for bulk processing and save time!';
            this.showUpgradeToast(message, {
                action: () => {
                    const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                    if (upgradeBtn) upgradeBtn.click();
                },
                actionLabel: 'View Plans',
                duration: 8000
            });
        },

        /**
         * Show scroll depth prompt
         */
        showScrollDepthPrompt: function() {
            const message = 'You\'re exploring BeepBeep AI! Upgrade to Growth to unlock all features and maximize your SEO impact.';
            this.showUpgradeToast(message, {
                action: () => {
                    const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                    if (upgradeBtn) upgradeBtn.click();
                },
                actionLabel: 'Learn More',
                duration: 8000
            });
        },

        /**
         * Show time-based prompt
         */
        showTimeBasedPrompt: function() {
            const message = 'You\'ve been using BeepBeep AI for a while. Upgrade to Growth for unlimited monthly credits!';
            this.showUpgradeToast(message, {
                action: () => {
                    const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                    if (upgradeBtn) upgradeBtn.click();
                },
                actionLabel: 'Upgrade Now',
                duration: 10000
            });
        },

        /**
         * Show hover prompt
         */
        showHoverPrompt: function() {
            const message = 'Interested in upgrading? Growth plan includes 1,000 monthly credits, bulk processing, and priority queues.';
            this.showUpgradeToast(message, {
                action: () => {
                    const upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                    if (upgradeBtn) upgradeBtn.click();
                },
                actionLabel: 'View Plans',
                duration: 8000
            });
        },

        /**
         * Check if user is on free plan
         */
        isFreeUser: function() {
            // Check from usage stats or plan data
            if (typeof BBAI_DASH !== 'undefined' && BBAI_DASH.initialUsage) {
                const plan = BBAI_DASH.initialUsage.plan || 'free';
                return plan === 'free';
            }

            // Fallback: check from page elements
            const upgradeLinks = document.querySelectorAll('[data-action="show-upgrade-modal"]');
            return upgradeLinks.length > 0;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiContextUpgrades.init());
    } else {
        bbaiContextUpgrades.init();
    }

    // Expose globally
    window.bbaiContextUpgrades = bbaiContextUpgrades;
})();
