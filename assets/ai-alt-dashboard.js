/**
 * AiAlt AI Alt Text Generator - Gamified Dashboard JS
 * Let's make alt text generation FUN! 🎮✨
 */

(function($) {
    'use strict';

    
    // Test: Add a simple click handler to see if JS is working
    $(document).on('click', 'button', function() {
    });
    
    // Ensure progress elements are hidden on page load
    $(document).ready(function() {
        $('.ai-alt-dashboard__status').hide();
        $('#ai-alt-bulk-progress').hide();
    });

    const apiFetch = (window.wp && window.wp.apiFetch) ? window.wp.apiFetch : null;

    function getRestConfig() {
        return window.AI_ALT_GPT_DASH || window.AI_ALT_GPT || window.AI_ALT_GPT_CONFIG || {};
    }

    function getRestRoot(config) {
        if (!config) { return (window.wpApiSettings && window.wpApiSettings.root) || ''; }
        return config.restRoot || (window.wpApiSettings && window.wpApiSettings.root) || '';
    }

    function toApiPath(config, url) {
        if (!url) { return ''; }
        const root = getRestRoot(config);
        let path = url;
        if (root && path.indexOf(root) === 0) {
            path = path.slice(root.length);
        }
        if (path.charAt(0) === '/') {
            path = path.slice(1);
        }
        return path;
    }

    function apiRequest(config, url, options = {}) {
        if (!url) {
            return Promise.reject(new Error('Missing REST URL'));
        }

        const method = options.method || 'GET';
        const data = options.data || null;
        const parse = options.parse !== false;
        const path = toApiPath(config, url);

        if (apiFetch && path) {
            const requestArgs = { path, method };
            if (data) {
                requestArgs.data = data;
            }
            if (!parse) {
                requestArgs.parse = false;
            }
            return apiFetch(requestArgs);
        }

        const fetchUrl = url;
        const headers = {};
        const nonce = config && config.nonce ? config.nonce : (window.wpApiSettings && window.wpApiSettings.nonce);
        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }
        if (data) {
            headers['Content-Type'] = 'application/json';
        }

        return fetch(fetchUrl, {
            method,
            credentials: 'include',
            headers,
            body: data ? JSON.stringify(data) : undefined
        }).then(response => {
            if (!response.ok) {
                return response.json().catch(() => ({})).then(errBody => {
                    const error = new Error(errBody?.message || response.statusText || 'Request failed');
                    error.response = errBody;
                    error.status = response.status;
                    throw error;
                });
            }
            if (!parse) {
                return response;
            }
            return response.json();
        });
    }

    function formatNumber(value) {
        const formatter = new Intl.NumberFormat();
        return formatter.format(value || 0);
    }

    function formatError(error) {
        if (!error) { return 'Unknown error'; }
        if (typeof error === 'string') { return error; }
        if (error.message) { return error.message; }
        if (error.response && error.response.message) { return error.response.message; }
        return 'Request failed';
    }

    function addQueryParam(url, key, value) {
        if (!url) { return ''; }
        const separator = url.indexOf('?') === -1 ? '?' : '&';
        return `${url}${separator}${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ========================================
    // 🎮 GAMIFICATION SYSTEM
    // ========================================
    const AiAltGamification = {
        /**
         * Calculate level based on images processed
         */
        calculateLevel(imagesProcessed) {
            // Level up every 50 images
            return Math.floor(imagesProcessed / 50) + 1;
        },

        /**
         * Calculate XP progress to next level
         */
        calculateXP(imagesProcessed) {
            const currentLevelImages = imagesProcessed % 50;
            const percentage = (currentLevelImages / 50) * 100;
            return {
                current: currentLevelImages,
                total: 50,
                percentage: Math.round(percentage)
            };
        },

        /**
         * Get level title
         */
        getLevelTitle(level) {
            if (level >= 20) return 'Accessibility Legend';
            if (level >= 15) return 'Alt Text Virtuoso';
            if (level >= 10) return 'Description Master';
            if (level >= 8) return 'Caption Champion';
            if (level >= 5) return 'Alt Text Expert';
            if (level >= 3) return 'Image Wizard';
            return 'Alt Text Apprentice';
        },

        /**
         * Get level emoji
         */
        getLevelEmoji(level) {
            if (level >= 20) return '👑';
            if (level >= 15) return '🏆';
            if (level >= 10) return '⭐';
            if (level >= 8) return '🎖️';
            if (level >= 5) return '🎯';
            if (level >= 3) return '🚀';
            return '🌱';
        },

        /**
         * Achievement definitions
         */
        achievements: [
            {
                id: 'first_steps',
                title: 'First Steps',
                description: 'Generate your first alt text',
                emoji: '👶',
                requirement: 1,
                check: (stats) => stats.generated >= 1
            },
            {
                id: 'getting_started',
                title: 'Getting Started',
                description: 'Generate 10 alt texts',
                emoji: '🎯',
                requirement: 10,
                check: (stats) => stats.generated >= 10
            },
            {
                id: 'on_a_roll',
                title: 'On a Roll',
                description: 'Generate 50 alt texts',
                emoji: '🔥',
                requirement: 50,
                check: (stats) => stats.generated >= 50
            },
            {
                id: 'century',
                title: 'Century Club',
                description: 'Generate 100 alt texts',
                emoji: '💯',
                requirement: 100,
                check: (stats) => stats.generated >= 100
            },
            {
                id: 'productivity_beast',
                title: 'Productivity Beast',
                description: 'Generate 250 alt texts',
                emoji: '🦁',
                requirement: 250,
                check: (stats) => stats.generated >= 250
            },
            {
                id: 'legendary',
                title: 'Legendary',
                description: 'Generate 500+ alt texts',
                emoji: '👑',
                requirement: 500,
                check: (stats) => stats.generated >= 500
            },
            {
                id: 'perfectionist',
                title: 'Perfectionist',
                description: 'Reach 100% coverage',
                emoji: '✨',
                requirement: 100,
                check: (stats) => stats.coverage >= 100
            },
            {
                id: 'quality_assurance',
                title: 'Quality Assurance',
                description: 'All images rated Good or Excellent',
                emoji: '🏅',
                requirement: 1,
                check: (stats) => stats.coverage === 100 && stats.missing === 0
            }
        ],

        /**
         * Check which achievements are unlocked
         */
        checkAchievements(stats) {
            return this.achievements.map(achievement => ({
                ...achievement,
                unlocked: achievement.check(stats),
                progress: Math.min(100, (stats.generated / achievement.requirement) * 100)
            }));
        }
    };

    // ========================================
    // 🎊 CELEBRATION EFFECTS
    // ========================================
    const AiAltCelebration = {
        /**
         * Create confetti effect
         */
        confetti() {
            const container = $('<div class="ai-alt-confetti"></div>');
            $('body').append(container);

            const colors = ['#667eea', '#f5576c', '#4facfe', '#43e97b', '#ffd700', '#fa709a'];
            
            for (let i = 0; i < 50; i++) {
                const confetti = $('<div class="ai-alt-confetti-piece"></div>');
                confetti.css({
                    left: Math.random() * 100 + '%',
                    background: colors[Math.floor(Math.random() * colors.length)],
                    animationDelay: Math.random() * 0.5 + 's',
                    animationDuration: (Math.random() * 2 + 2) + 's'
                });
                container.append(confetti);
            }

            setTimeout(() => container.remove(), 5000);
        },

        /**
         * Create sparkle effect at position
         */
        sparkle(x, y) {
            const sparkle = $('<div class="ai-alt-sparkle">✨</div>');
            sparkle.css({
                left: x + 'px',
                top: y + 'px',
                fontSize: '2rem'
            });
            $('body').append(sparkle);
            setTimeout(() => sparkle.remove(), 1000);
        },

        /**
         * Level up animation
         */
        levelUp(level) {
            this.confetti();
            AiAltToast.show({
                type: 'achievement',
                title: '🎉 Level Up!',
                message: `You've reached Level ${level}!`,
                duration: 5000
            });
            
            // Play a sound if available
            if (window.Audio) {
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTcIGGi67OifTRAMUKfj8LZjHAY4kdfyzHksBSR3x/DdkEAKFF606+uoVRQKRp/g8r5sIQUrgsvy2Yk3CBhpuuzpn00QDFA=');
                    audio.volume = 0.3;
                    audio.play().catch(() => {});
                } catch(e) {}
            }
        },

        /**
         * Achievement unlocked animation
         */
        achievementUnlocked(achievement) {
            AiAltToast.show({
                type: 'achievement',
                title: `${achievement.emoji} Achievement Unlocked!`,
                message: achievement.title,
                duration: 5000
            });
            
            // Add sparkles around the achievement
            setTimeout(() => {
                const $achievement = $(`.ai-alt-achievement[data-achievement-id="${achievement.id}"]`);
                if ($achievement.length) {
                    const offset = $achievement.offset();
                    this.sparkle(offset.left, offset.top);
                    this.sparkle(offset.left + 100, offset.top);
                }
            }, 100);
        },

        /**
         * Full coverage celebration
         */
        fullCoverage() {
            this.confetti();
            AiAltToast.show({
                type: 'success',
                title: '🎉 Perfect Score!',
                message: 'All your images now have alt text! Amazing work!',
                duration: 7000
            });
        }
    };

    // ========================================
    // 🔔 TOAST NOTIFICATION SYSTEM
    // ========================================
    const AiAltToast = {
        container: null,

        /**
         * Initialize toast container
         */
        init() {
            if (!this.container) {
                this.container = $('<div class="ai-alt-toast-container"></div>');
                $('body').append(this.container);
            }
        },

        /**
         * Show toast notification
         */
        show(options) {
            this.init();
            
            const defaults = {
                type: 'info', // success, error, info, achievement
                title: '',
                message: '',
                duration: 4000
            };
            
            const settings = $.extend({}, defaults, options);
            
            const icons = {
                success: '✅',
                error: '❌',
                info: 'ℹ️',
                achievement: '🏆'
            };
            
            const toast = $(`
                <div class="ai-alt-toast ${settings.type}">
                    <div class="ai-alt-toast-icon">${icons[settings.type] || icons.info}</div>
                    <div class="ai-alt-toast-content">
                        ${settings.title ? `<div class="ai-alt-toast-title">${settings.title}</div>` : ''}
                        <div class="ai-alt-toast-message">${settings.message}</div>
                    </div>
                    <button class="ai-alt-toast-close" aria-label="Close">×</button>
                </div>
            `);
            
            this.container.append(toast);
            
            // Close button
            toast.find('.ai-alt-toast-close').on('click', function() {
                toast.fadeOut(300, function() { $(this).remove(); });
            });
            
            // Auto dismiss
            if (settings.duration > 0) {
                setTimeout(() => {
                    toast.fadeOut(300, function() { $(this).remove(); });
                }, settings.duration);
            }
        }
    };

    // ========================================
    // 📊 DASHBOARD INITIALIZATION
    // ========================================
    function getRestConfig() {
        return window.AI_ALT_GPT_DASH || window.AI_ALT_GPT || window.AI_ALT_GPT_CONFIG || {};
    }

    const AiAltDashboard = {
        stats: null,
        previousStats: null,
        usage: null,
        queue: null,
        queueRefreshTimer: null,
        queueControlsBound: false,
        billingControlsBound: false,
        isQueueRefreshing: false,
        bulkButtonBound: false,
        isBulkProcessing: false,
        isRefreshing: false,
        config: null,
        bulkTotal: 0,
        bulkProcessed: 0,
        bulkSuccess: 0,
        bulkFailures: 0,

        /**
         * Initialize dashboard
         */
        init() {
            this.loadStats();
            this.config = getRestConfig();
            this.usage = (this.config && this.config.initialUsage) ? this.config.initialUsage : null;
            this.renderUsage();
            this.renderStatsToDom();
            this.updateSavingsCopy();
            this.togglePostOptimizeBanner();
            this.initializeGamification();
            this.initializeActionButtons();
            this.initializeChart();
            this.checkForNewAchievements();
            this.initializeBulkButton();
            this.cacheProgressEls();
            this.updateBulkButtonState();
            this.bindBillingControls();
            this.bindQueueControls();
            this.refreshStats();
            this.refreshQueueStatus();
            this.startQueuePolling();
        },

        /**
         * Load stats from data attribute
         */
        loadStats() {
            const $dashboard = $('.ai-alt-dashboard--primary');
            if ($dashboard.length) {
                try {
                    this.stats = JSON.parse($dashboard.attr('data-stats'));
                    // Load previous stats from localStorage
                    const savedStats = localStorage.getItem('farlo_previous_stats');
                    if (savedStats) {
                        this.previousStats = JSON.parse(savedStats);
                    }
                } catch(e) {
                }
            }
        },

        /**
         * Initialize gamification UI
         */
        initializeGamification() {
            if (!this.stats) return;

            const generated = parseInt(this.stats.generated) || 0;
            const level = AiAltGamification.calculateLevel(generated);
            const xp = AiAltGamification.calculateXP(generated);
            const levelTitle = AiAltGamification.getLevelTitle(level);
            const levelEmoji = AiAltGamification.getLevelEmoji(level);

            // Create gamification header
            const header = $(`
                <div class="ai-alt-gamification-header">
                    <div class="ai-alt-level-badge">
                        <span class="ai-alt-level-badge-icon">${levelEmoji}</span>
                        Level ${level} - ${levelTitle}
                    </div>
                    <div class="ai-alt-xp-container">
                        <div class="ai-alt-xp-label">
                            <span>Progress to Level ${level + 1}</span>
                            <span>${xp.current}/${xp.total} images</span>
                        </div>
                        <div class="ai-alt-xp-bar">
                            <div class="ai-alt-xp-progress" style="width: ${xp.percentage}%">
                                ${xp.percentage}%
                            </div>
                        </div>
                    </div>
                </div>
            `);

            // Insert before dashboard intro
            $('.ai-alt-dashboard__intro').before(header);

            // Check for level up
            if (this.previousStats) {
                const previousLevel = AiAltGamification.calculateLevel(
                    parseInt(this.previousStats.generated) || 0
                );
                if (level > previousLevel) {
                    setTimeout(() => {
                        AiAltCelebration.levelUp(level);
                    }, 500);
                }
            }

            // Add achievements section
            this.renderAchievements();
        },

        /**
         * Render achievements
         */
        renderAchievements() {
            if (!this.stats) return;

            const achievements = AiAltGamification.checkAchievements(this.stats);
            
            const container = $('<div class="ai-alt-achievements-container"></div>');
            
            achievements.forEach(achievement => {
                const $achievement = $(`
                    <div class="ai-alt-achievement ${achievement.unlocked ? 'unlocked' : 'locked'}" 
                         data-achievement-id="${achievement.id}"
                         title="${achievement.description}">
                        <div class="ai-alt-achievement-icon">${achievement.emoji}</div>
                        <div class="ai-alt-achievement-title">${achievement.title}</div>
                        <div class="ai-alt-achievement-description">${achievement.description}</div>
                        ${!achievement.unlocked ? `<div class="ai-alt-achievement-progress">${Math.round(achievement.progress)}%</div>` : ''}
                    </div>
                `);
                container.append($achievement);
            });

            // Insert after gamification header
            $('.ai-alt-gamification-header').after(container);

            // Check for newly unlocked achievements
            if (this.previousStats) {
                const previousAchievements = AiAltGamification.checkAchievements(this.previousStats);
                achievements.forEach((achievement, index) => {
                    if (achievement.unlocked && !previousAchievements[index].unlocked) {
                        setTimeout(() => {
                            AiAltCelebration.achievementUnlocked(achievement);
                        }, 1000 + (index * 500));
                    }
                });
            }
        },

        /**
         * Initialize action buttons
         */
        initializeActionButtons() {
            const self = this;

            // Enhance existing action cards with new styling
            $('.ai-alt-action-card').each(function(index) {
                const gradients = ['card-purple', 'card-green', 'card-blue'];
                $(this).addClass(gradients[index % 3]);
            });

            // Add gradient classes to microcards
            $('.ai-alt-microcard').each(function(index) {
                const gradients = ['gradient-purple', 'gradient-pink', 'gradient-blue', 'gradient-green'];
                $(this).addClass(gradients[index % 4]);
            });

            // Add icons to microcards if not present
            const icons = ['📊', '✅', '❌', '🎯'];
            $('.ai-alt-microcard').each(function(index) {
                if (!$(this).find('.ai-alt-microcard__icon').length) {
                    $(`<div class="ai-alt-microcard__icon">${icons[index % 4]}</div>`)
                        .prependTo($(this));
                }
            });

            // Replace existing buttons with ai-alt-btn styling
            $('.ai-alt-action-card button, .ai-alt-action-card .button').each(function(index) {
                const gradients = ['ai-alt-btn-primary', 'ai-alt-btn-success', 'ai-alt-btn-info'];
                $(this).addClass('ai-alt-btn ' + gradients[index % 3]);
            });
        },

        /**
         * Initialize coverage chart
         */
        initializeChart() {
            const canvas = $('#ai-alt-coverage');
            if (!canvas.length || !this.stats) return;

            // Check if Chart.js is loaded (it should be from the existing code)
            if (typeof Chart === 'undefined') {
                return;
            }

            const with_alt = parseInt(this.stats.with_alt) || 0;
            const missing = parseInt(this.stats.missing) || 0;

            const ctx = canvas[0].getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['With ALT', 'Missing'],
                    datasets: [{
                        data: [with_alt, missing],
                        backgroundColor: [
                            'rgba(67, 233, 123, 0.8)',
                            'rgba(245, 87, 108, 0.8)'
                        ],
                        borderColor: [
                            'rgba(67, 233, 123, 1)',
                            'rgba(245, 87, 108, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(45, 55, 72, 0.95)',
                            padding: 12,
                            cornerRadius: 8,
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            }
                        }
                    }
                }
            });

            // Check for 100% coverage celebration
            if (parseFloat(this.stats.coverage) === 100 && missing === 0) {
                if (!this.previousStats || parseFloat(this.previousStats.coverage) < 100) {
                    setTimeout(() => {
                        AiAltCelebration.fullCoverage();
                    }, 1500);
                }
            }
        },

        /**
         * Check for new achievements
         */
        checkForNewAchievements() {
            // Save current stats for next visit
            if (this.stats) {
                localStorage.setItem('farlo_previous_stats', JSON.stringify(this.stats));
            }
        },

        /**
         * Update stats after processing
         */
        updateStats(newStats, options = {}) {
            this.previousStats = this.stats;
            this.stats = newStats;

            // Save to localStorage
            localStorage.setItem('farlo_previous_stats', JSON.stringify(this.previousStats));

            // Re-render gamification elements
            $('.ai-alt-gamification-header').remove();
            $('.ai-alt-achievements-container').remove();
            this.initializeGamification();

            this.renderStatsToDom();
            this.updateSavingsCopy();
            this.togglePostOptimizeBanner();
            this.updateBulkButtonState();

            if (!options.silent) {
                AiAltToast.show({
                    type: 'success',
                    title: '✨ Great Work!',
                    message: 'Alt text generated successfully!',
                    duration: 3000
                });
            }
        },

        initializeBulkButton() {
            if (this.bulkButtonBound) {
                this.updateBulkButtonState();
                return;
            }

            const $button = $('[data-action="generate-missing"]');

            const self = this;
            $(document)
                .off('click.aiAltBulk', '[data-action="generate-missing"]')
                .on('click.aiAltBulk', '[data-action="generate-missing"]', function(e) {
                    e.preventDefault();
                    self.handleBulkButtonClick($(this));
                });

            this.bulkButtonBound = true;
            const $progressContainer = $('[data-bulk-progress]');
            if (this.usage && this.usage.remaining <= 0) {
                if ($progressContainer.length) {
                    $progressContainer.prop('hidden', true);
                }
                this.hideProgressLog();
            } else if ($progressContainer.length && !this.isBulkProcessing) {
                $progressContainer.prop('hidden', false);
            }

            this.updateBulkButtonState();
        },

        handleBulkButtonClick($button) {
            if (this.isBulkProcessing || !$button.length || $button.prop('disabled')) {
                return;
            }

            // Check usage limits first
            if (this.usage && this.usage.remaining <= 0) {
                AiAltToast.show({
                    type: 'warning',
                    title: 'Usage limit reached',
                    message: 'You\'ve used all your free generations. Upgrade to Pro for unlimited AI generations.',
                    duration: 5000
                });
                return;
            }

            const config = getRestConfig();

            if (!config || !config.restMissing || !config.rest || !config.nonce) {
                AiAltToast.show({
                    type: 'error',
                    title: 'Configuration error',
                    message: 'Bulk generation endpoints unavailable.',
                    duration: 3500
                });
                return;
            }

            this.config = config;

            this.isBulkProcessing = true;
            $button.prop('disabled', true).addClass('loading').text('Processing Images...');
            
            // Update button state to ensure it's properly disabled
            this.updateBulkButtonState();

            // Show detailed progress log
            if (typeof this.showProgressLog === 'function') {
                this.showProgressLog();
            } else {
                console.error('showProgressLog method not found on this object:', this);
            }

            if (typeof this.addProgressLog === 'function') {
                this.addProgressLog('info', 'Starting bulk optimization...');
                this.addProgressLog('info', 'Collecting images without ALT text...');
            } else {
                console.error('addProgressLog method not found on this object:', this);
            }

            const self = this;

            apiRequest(config, config.restMissing)
                .then(function(response) {
                    const ids = Array.isArray(response?.ids) ? response.ids : [];

                    if (!ids.length) {
                        self.addProgressLog('info', 'No images found without ALT text');
                        self.addProgressLog('success', 'All images already have alt text!');
                        setTimeout(() => {
                            self.hideProgressLog();
                            self.finishBulk($button);
                            self.refreshStats();
                        }, 2000);
                        return;
                    }

                    // Update progress stats
                    self.progressStats.total = ids.length;
                    self.updateProgressStats();
                    
                    self.addProgressLog('info', `Found ${ids.length} images without ALT text`);
                    self.addProgressLog('info', 'Starting AI generation process...');

                    self.startBulkGeneration(ids)
                        .always(function(result) {
                            self.finishBulk($button);
                            self.refreshStats();
                        });
                })
                .catch(function(error) {
                    const message = formatError(error);
                    AiAltToast.show({
                        type: 'error',
                        title: 'Request failed',
                        message,
                        duration: 3500
                    });
                    self.finishBulk($button);
                });
        },

        finishBulk($button) {
            this.isBulkProcessing = false;
            if ($button && $button.length) {
                $button.prop('disabled', false).removeClass('loading');
            }
            // Note: AiAltProgressIndicator removed to prevent fadeOut errors
            this.updateBulkButtonState();
            
            // Refresh usage stats to update the remaining credits display
            this.refreshUsageStats({ silentToast: true });
            
            // Add completion log entry and hide after delay
            if (this.progressStats) {
                const { success, errors } = this.progressStats;
                if (typeof this.addProgressLog === 'function') {
                    this.addProgressLog('success', `Bulk optimization completed! ${success} successful, ${errors} errors`);
                }

                if (success > 0) {
                    AiAltToast.show({
                        type: 'success',
                        title: 'Bulk complete',
                        message: `✅ ${success} alt texts generated successfully!`,
                        duration: 4400
                    });
                }

                if (errors > 0) {
                    AiAltToast.show({
                        type: 'error',
                        title: 'Check results',
                        message: `${errors} images need attention. Review the progress log for details.`,
                        duration: 5200
                    });
                }
                
                setTimeout(() => {
                    if (typeof this.hideProgressLog === 'function') {
                        this.hideProgressLog();
                    }
                }, 3000);
            }
        },

        updateBulkButtonState() {
            const $button = $('.alttextai-bulk-btn');
            if (!$button.length) {
                return;
            }

            const missing = parseInt(this.stats?.missing || 0, 10);
            const usageRemaining = this.usage && typeof this.usage.remaining !== 'undefined'
                ? parseInt(this.usage.remaining, 10)
                : null;

            if (this.isBulkProcessing) {
                $button
                    .prop('disabled', true)
                    .addClass('loading')
                    .removeClass('ai-alt-btn-disabled')
                    .text('Processing Images...');
                return;
            }

            if (usageRemaining !== null && usageRemaining <= 0) {
                const waitingText = missing > 0
                    ? `Upgrade to Unlock (${missing} images waiting) →`
                    : 'Upgrade to Unlock →';

                $button
                    .prop('disabled', false)
                    .removeClass('loading ai-alt-btn-disabled')
                    .addClass('alttextai-bulk-btn--limit')
                    .attr('data-action', 'show-upgrade-modal')
                    .text(waitingText);
                return;
            }

            const shouldEnable = missing > 0;
            const label = shouldEnable
                ? `Optimize ${missing} Remaining Images`
                : 'All Images Optimized!';

            $button
                .attr('data-action', 'generate-missing')
                .removeClass('alttextai-bulk-btn--limit loading')
                .prop('disabled', !shouldEnable)
                .toggleClass('ai-alt-btn-disabled', !shouldEnable)
                .text(label);
        },

        cacheProgressEls() {
            this.bulkProgress = {
                container: $('[data-bulk-progress]'),
                label: $('[data-bulk-progress-label]'),
                counts: $('[data-bulk-progress-counts]'),
                bar: $('[data-bulk-progress-bar]')
            };
        },

        startProgress(total) {
            if (!this.bulkProgress) {
                this.cacheProgressEls();
            }
            this.bulkTotal = Math.max(parseInt(total, 10) || 0, 0);
            this.bulkProcessed = 0;
            this.bulkSuccess = 0;
            this.bulkFailures = 0;
            if (this.bulkProgress && this.bulkProgress.container.length) {
                this.bulkProgress.container.prop('hidden', false);
            }
            this.updateProgressUI('Starting bulk optimization…');
        },

        updateProgressUI(message) {
            if (!this.bulkProgress) { return; }
            const total = this.bulkTotal || 0;
            const safeTotal = Math.max(total, 1);
            const percent = Math.min(100, Math.round((this.bulkProcessed / safeTotal) * 100));
            if (this.bulkProgress.label && this.bulkProgress.label.length) {
                this.bulkProgress.label.text(message);
            }
            if (this.bulkProgress.counts && this.bulkProgress.counts.length) {
                const totalText = total > 0 ? `${this.bulkProcessed}/${total}` : `${this.bulkProcessed}`;
                this.bulkProgress.counts.text(`${totalText} · ${this.bulkSuccess} done · ${this.bulkFailures} failed`);
            }
            if (this.bulkProgress.bar && this.bulkProgress.bar.length) {
                this.bulkProgress.bar.css('width', `${percent}%`);
            }
        },

        noteSuccess(id) {
            this.bulkProcessed += 1;
            this.bulkSuccess += 1;
            this.updateProgressUI(`Optimized image #${id}`);
        },

        noteFailure(id, message) {
            this.bulkProcessed += 1;
            this.bulkFailures += 1;
            this.updateProgressUI(`Image #${id} failed: ${message}`);
        },

        stopProgress() {
            if (this.bulkProgress && this.bulkProgress.container.length) {
                const container = this.bulkProgress.container;
                setTimeout(() => container.prop('hidden', true), 1500);
            }
            this.bulkTotal = this.bulkProcessed = this.bulkSuccess = this.bulkFailures = 0;
        },

        renderUsage() {
            if (!this.usage) { return; }
            const usage = this.usage;
            const $text = $('[data-usage-text]');
            if ($text.length) {
                $text.text(`${usage.used} of ${usage.limit} free generations used`);
            }

            const $bar = $('[data-usage-bar]');
            const rawLimit = parseInt(usage.limit || usage.max || 0, 10);
            const safeLimit = rawLimit > 0 ? rawLimit : 1;
            const percent = Math.min(100, Math.max(0, Math.round((parseInt(usage.used || 0, 10) / safeLimit) * 100)));
            this.usage.percentage = percent;

            if ($bar.length) {
                $bar
                    .css('width', `${percent}%`)
                    .toggleClass('alttextai-progress-bar-fill--limit-reached', usage.remaining <= 0);
            }

            const $alert = $('.alttextai-usage-alert');
            if ($alert.length) {
                if (usage.remaining <= 0) {
                    $alert.text('🎯 You’ve reached your free limit — upgrade for more AI power!');
                } else {
                    $alert.text(`${usage.remaining} credits remain this cycle. Keep the momentum going!`);
                }
            }

            const $resetInfo = $('.alttextai-reset-info');
            if ($resetInfo.length) {
                if (usage.remaining <= 0) {
                    const days = parseInt(usage.days_until_reset || 0, 10);
                    const resetDate = usage.reset_date || '';
                    let copy = '';
                    if (days > 0 && resetDate) {
                        copy = `Resets in ${days} days (${resetDate})`;
                    } else if (resetDate) {
                        copy = `Resets ${resetDate}`;
                    }
                    $resetInfo.removeClass('hidden').text(copy);
                } else {
                    $resetInfo.addClass('hidden').text('');
                }
            }

            const $progressContainer = $('[data-bulk-progress]');
            if (usage.remaining <= 0) {
                if ($progressContainer.length) {
                    $progressContainer.prop('hidden', true);
                }
                this.hideProgressLog();
            } else if ($progressContainer.length && !this.isBulkProcessing) {
                $progressContainer.prop('hidden', false);
            }

            this.updateBulkButtonState();
        },

        bindBillingControls() {
            if (this.billingControlsBound) { return; }
            $(document).on('click', '[data-action="open-billing-portal"]', function(e){
                e.preventDefault();
                const portalUrl = (window.AltTextAI && window.AltTextAI.billingPortalUrl) || '';
                const upgradeUrl = (window.AltTextAI && window.AltTextAI.upgradeUrl) || '';
                if (portalUrl) {
                    AiAltDashboard.trackUpgrade('billing-portal');
                    window.open(portalUrl, '_blank', 'noopener');
                    return;
                }
                if (upgradeUrl) {
                    AiAltDashboard.trackUpgrade('billing-fallback');
                    window.open(upgradeUrl, '_blank', 'noopener');
                    return;
                }
                AiAltToast.show({
                    type: 'info',
                    title: 'Billing',
                    message: 'Billing portal is not configured yet.',
                    duration: 3600
                });
            });
            this.billingControlsBound = true;
        },

        bindQueueControls() {
            if (this.queueControlsBound) { return; }
            const self = this;

            $(document).on('click', '[data-action="refresh-queue"]', function(e){
                e.preventDefault();
                const $btn = $(this);
                $btn.prop('disabled', true).addClass('is-refreshing');
                self.refreshQueueStatus({ force: true }).finally(() => {
                    $btn.prop('disabled', false).removeClass('is-refreshing');
                });
            });

            $(document).on('click', '[data-action="retry-failed"]', function(e){
                e.preventDefault();
                const $btn = $(this);
                self.performQueueAction(
                    { action: 'alttextai_queue_retry_failed' },
                    $btn,
                    'Retrying failed jobs…',
                    'Failed jobs re-queued.',
                    'Unable to retry failed jobs.'
                );
            });

            $(document).on('click', '[data-action="retry-job"]', function(e){
                e.preventDefault();
                const $btn = $(this);
                const jobId = parseInt($btn.data('jobId'), 10);
                if (!jobId) { return; }
                self.performQueueAction(
                    { action: 'alttextai_queue_retry_job', job_id: jobId },
                    $btn,
                    'Retrying job…',
                    'Job re-queued.',
                    'Unable to retry this job.'
                );
            });

            $(document).on('click', '[data-action="clear-completed"]', function(e){
                e.preventDefault();
                const $btn = $(this);
                self.performQueueAction(
                    { action: 'alttextai_queue_clear_completed' },
                    $btn,
                    'Clearing completed jobs…',
                    'Cleared completed jobs.',
                    'Unable to clear completed jobs.'
                );
            });

            this.queueControlsBound = true;
        },

        performQueueAction(payload, $button, workingMessage, successMessage, errorMessage) {
            const ajaxConfig = window.alttextai_ajax || {};
            if (!$button) { $button = $(); }

            if (!ajaxConfig.ajaxurl || !ajaxConfig.nonce) {
                AiAltToast.show({
                    type: 'error',
                    title: 'Queue action failed',
                    message: 'AJAX configuration missing.',
                    duration: 4000
                });
                return $.Deferred().reject().promise();
            }

            const requestData = Object.assign({ nonce: ajaxConfig.nonce }, payload || {});
            if (!requestData.action) {
                AiAltToast.show({
                    type: 'error',
                    title: 'Queue action failed',
                    message: 'No action specified.',
                    duration: 4000
                });
                return $.Deferred().reject().promise();
            }

            if ($button.length) {
                $button.prop('disabled', true).addClass('is-refreshing');
            }

            if (workingMessage) {
                AiAltToast.show({
                    type: 'info',
                    title: 'Queue',
                    message: workingMessage,
                    duration: 2000
                });
            }

            return $.post(ajaxConfig.ajaxurl, requestData)
            .done((resp) => {
                if (resp && resp.success) {
                    AiAltToast.show({
                        type: 'success',
                        title: 'Queue',
                        message: successMessage || 'Action completed.',
                        duration: 3200
                    });
                    this.refreshQueueStatus({ force: true, silent: true });
                } else {
                    const message = resp?.data?.message || resp?.data || errorMessage || 'Action failed.';
                    AiAltToast.show({
                        type: 'error',
                        title: 'Queue',
                        message,
                        duration: 4200
                    });
                }
            })
            .fail(() => {
                AiAltToast.show({
                    type: 'error',
                    title: 'Queue',
                    message: errorMessage || 'Network error while performing queue action.',
                    duration: 4200
                });
            })
            .always(() => {
                if ($button.length) {
                    $button.prop('disabled', false).removeClass('is-refreshing');
                }
            });
        },

        startQueuePolling() {
            this.stopQueuePolling();
            const config = this.config || getRestConfig();
            if (!config || !config.restQueue) { return; }
            this.queueRefreshTimer = setInterval(() => this.refreshQueueStatus({ silent: true }), 60000);
        },

        stopQueuePolling() {
            if (this.queueRefreshTimer) {
                clearInterval(this.queueRefreshTimer);
                this.queueRefreshTimer = null;
            }
        },

        refreshQueueStatus(options = {}) {
            const config = this.config || getRestConfig();
            if (!config || !config.restQueue) { return Promise.resolve(); }
            if (this.isQueueRefreshing && !options.force) { return Promise.resolve(); }

            this.isQueueRefreshing = true;
            const request = apiRequest(config, config.restQueue)
                .then(data => {
                    this.queue = data || null;
                    this.renderQueue();
                })
                .catch(error => {
                    if (!options.silent) {
                        AiAltToast.show({
                            type: 'error',
                            title: 'Queue status',
                            message: formatError(error),
                            duration: 4200
                        });
                    }
                })
                .finally(() => {
                    this.isQueueRefreshing = false;
                });

            return request;
        },

        renderQueue() {
            const $card = $('.alttextai-queue-card');
            if (!$card.length) { return; }

            const data = this.queue || {};
            const stats = data.stats || {};
            const format = (value) => formatNumber(parseInt(value || 0, 10));

            $('[data-queue-pending]').text(format(stats.pending));
            $('[data-queue-processing]').text(format(stats.processing));
            $('[data-queue-failed]').text(format(stats.failed));
            const completedValue = (typeof stats.completed_recent !== 'undefined') ? stats.completed_recent : stats.completed;
            $('[data-queue-completed]').text(format(completedValue));

            $card.toggleClass('has-failures', (stats.failed || 0) > 0);

            const $failWrapper = $('[data-queue-failures-wrapper]');
            const $failList = $('[data-queue-failures]');
            const failures = Array.isArray(data.failures) ? data.failures : [];
            if (failures.length) {
                $failList.html(failures.map(job => this.renderQueueItem(job, true)).join(''));
                $failWrapper.show();
            } else {
                $failList.empty();
                $failWrapper.hide();
            }

            const $recent = $('[data-queue-recent]');
            const recent = Array.isArray(data.recent) ? data.recent : [];
            if (recent.length) {
                $recent.html(recent.map(job => this.renderQueueItem(job, false)).join(''));
            } else {
                $recent.html('<p class="alttextai-queue-empty">Jobs will appear here when the queue runs.</p>');
            }
        },

        renderQueueItem(job, isFailure = false) {
            const id = parseInt(job.id || job.attachment_id || 0, 10);
            const attachmentId = parseInt(job.attachment_id || 0, 10);
            const title = job.attachment_title || `Attachment #${attachmentId || id}`;
            const status = (job.status || '').toUpperCase();
            const attempts = parseInt(job.attempts || 0, 10);
            const source = job.source ? job.source.toUpperCase() : '';
            const enqueued = this.formatQueueDate(job.enqueued_at);
            const locked = this.formatQueueDate(job.locked_at);
            const completed = this.formatQueueDate(job.completed_at);
            const editUrl = job.edit_url ? escapeHtml(job.edit_url) : '';

           const metaParts = [];
           if (status) { metaParts.push(`Status: ${status}`); }
           if (source) { metaParts.push(`Source: ${source}`); }
           if (attempts) { metaParts.push(`Attempts: ${attempts}`); }
           if (enqueued) { metaParts.push(`Queued: ${enqueued}`); }
           if (locked) { metaParts.push(`Locked: ${locked}`); }
           if (completed) { metaParts.push(`Completed: ${completed}`); }
           if (!metaParts.length && (id || attachmentId)) {
               metaParts.push(`Job #${id || attachmentId}`);
           }
           const metaHtml = metaParts.length ? metaParts.map(part => escapeHtml(part)).join(' • ') : '';

            const error = job.last_error ? `<p class="alttextai-queue-item__error">${escapeHtml(job.last_error)}</p>` : '';
            const link = editUrl ? `<a class="alttextai-queue-item__link" href="${editUrl}" target="_blank" rel="noopener noreferrer">View attachment</a>` : '';
            const retryBtn = isFailure && id
                ? `<button type="button" class="alttextai-queue-btn alttextai-queue-btn--ghost" data-action="retry-job" data-job-id="${id}">Retry job</button>`
                : '';

            return `
                <div class="alttextai-queue-item ${isFailure ? 'alttextai-queue-item--failed' : ''}">
                    <p class="alttextai-queue-item__title">${escapeHtml(title)}</p>
                    <div class="alttextai-queue-item__meta">${metaHtml}</div>
                    ${link}
                    ${retryBtn}
                    ${error}
                </div>
            `;
        },

        trackUpgrade(source = 'dashboard') {
            const ajaxConfig = window.alttextai_ajax || {};
            if (!ajaxConfig.ajaxurl || !ajaxConfig.nonce) { return; }
            $.post(ajaxConfig.ajaxurl, {
                action: 'alttextai_track_upgrade',
                nonce: ajaxConfig.nonce,
                source
            });
        },

        formatQueueDate(value) {
            if (!value) { return ''; }
            const normalised = String(value).replace(' ', 'T');
            const date = new Date(normalised);
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return date.toLocaleString();
        },

        renderStatsToDom() {
            if (!this.stats) { return; }
            const formatter = value => formatNumber(parseInt(value || 0, 10));
            $('[data-stat-with-alt]').text(formatter(this.stats.with_alt));
            $('[data-stat-missing]').text(formatter(this.stats.missing));
        },

        updateSavingsCopy() {
            if (!this.stats) { return; }
            const $savings = $('[data-savings-copy]');
            if (!$savings.length) { return; }
            const optimized = parseInt(this.stats.with_alt || 0, 10);
            const minutesSaved = optimized * 2;
            const hoursSaved = minutesSaved / 60;
            const precision = hoursSaved >= 10 ? 0 : 1;
            const formatted = hoursSaved.toFixed(precision);
            $savings.text(`You’ve saved ${formatted} hours of manual work! (est. 2 min/image)`);
        },

        togglePostOptimizeBanner() {
            const $banner = $('[data-post-optimize-banner]');
            if (!$banner.length || !this.stats) {
                return;
            }

            const missing = parseInt(this.stats.missing || 0, 10);
            const optimized = parseInt(this.stats.with_alt || 0, 10);
            if (missing === 0 && optimized > 0) {
                $banner.removeClass('hidden').attr('aria-hidden', 'false');
            } else {
                $banner.addClass('hidden').attr('aria-hidden', 'true');
            }
        },

        refreshStats() {
            const config = this.config || getRestConfig();
            if (!config || !config.restStats) {
                this.updateBulkButtonState();
                return;
            }

            if (this.isRefreshing) { return; }

            this.isRefreshing = true;
            const freshStatsUrl = addQueryParam(config.restStats, 'fresh', '1');
            const statsPromise = apiRequest(config, freshStatsUrl);
            const usagePromise = config.restUsage ? apiRequest(config, config.restUsage) : Promise.resolve(null);

            Promise.all([statsPromise, usagePromise])
                .then(([stats, usage]) => {
                    if (stats && !stats.code) {
                        this.updateStats(stats, { silent: true });
                    }
                    if (usage && !usage.code) {
                        this.usage = usage;
                        this.renderUsage();
                    }
                })
                .catch(() => {})
                .finally(() => {
                    this.isRefreshing = false;
                    this.updateBulkButtonState();
                });
        },

        startBulkGeneration(queue) {
            const ids = Array.isArray(queue) ? queue.slice() : [];
            if (!ids.length) {
                return $.Deferred().resolve().promise();
            }

            const self = this;
            const deferred = $.Deferred();
            const total = ids.length;
            let processed = 0;
            let successes = 0;
            let failures = 0;
            let timeoutId = null;

            const config = this.config || getRestConfig();
            const generateBase = config && config.rest ? config.rest.replace(/\/$/, '') : '';

            const processNext = function() {
                if (!ids.length) {
                    // Update progress stats
                    self.progressStats.processed = processed;
                    self.progressStats.success = successes;
                    self.progressStats.errors = failures;
                    self.updateProgressStats();
                    
                    const summaryMessage = `Bulk run completed: ${successes} success · ${failures} failed.`;
                    self.addProgressLog('success', summaryMessage);
                    
                    deferred.resolve({ successes, failures });
                    return;
                }

                const id = ids.shift();
                const generateUrl = `${generateBase}/${id}`;
                
                // Get image filename for logging
                const imageName = self.getImageName ? self.getImageName(id) : `Image ID ${id}`;
                self.addProgressLog('info', `Processing ${imageName}...`);

                // Set timeout for each request (30 seconds)
                timeoutId = setTimeout(() => {
                    self.addProgressLog('error', `⏰ Timeout processing ${imageName} - skipping`);
                    failures += 1;
                    processed += 1;
                    self.progressStats.errors = failures;
                    self.progressStats.processed = processed;
                    self.updateProgressStats();
                    setTimeout(processNext, 100);
                }, 30000);

                apiRequest(config, generateUrl, { method: 'POST' })
                    .then((response) => {
                        clearTimeout(timeoutId);
                        successes += 1;
                        processed += 1;
                        self.progressStats.success = successes;
                        self.progressStats.processed = processed;
                        self.updateProgressStats();
                        self.addProgressLog('success', `✅ Successfully generated alt text for ${imageName}`);
                        self.noteSuccess(id);
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        failures += 1;
                        processed += 1;
                        self.progressStats.errors = failures;
                        self.progressStats.processed = processed;
                        self.updateProgressStats();
                        const message = formatError(error);
                        self.addProgressLog('error', `❌ Failed to process ${imageName}: ${message}`);
                        self.noteFailure(id, message);

                        const isLimit = error && error.response && (error.response.code === 'limit_reached' || error.response.code === 'LIMIT_REACHED');
                        
                        if (isLimit) {
                            self.addProgressLog('warning', '⚠️ Monthly limit reached. Stopping bulk optimization.');
                            ids.length = 0; // stop processing remaining ids
                        }
                    })
                    .finally(() => {
                        clearTimeout(timeoutId);
                        processed += 1;
                        self.progressStats.processed = processed;
                        self.updateProgressStats();
                        
                        // Small delay to prevent overwhelming the API
                        setTimeout(processNext, 500);
                    });
            };

            processNext();
            return deferred.promise();
        },

        /**
         * Show detailed progress log
         */
        showProgressLog() {
            if (this.usage && parseInt(this.usage.remaining || 0, 10) <= 0) {
                this.hideProgressLog();
                return;
            }

            const $progressLog = $('#ai-alt-bulk-progress');
            const $overlay = $('<div class="alttextai-progress-overlay"></div>');
            
            if ($progressLog.length) {
                $('body').append($overlay);
                $progressLog.show();
                
                // Also show the old progress bar for compatibility
                $('.ai-alt-dashboard__status').show();
                
                // Reset progress stats
                this.progressStats = {
                    total: 0,
                    processed: 0,
                    success: 0,
                    errors: 0
                };
                this.updateProgressStats();
            }
        },

        /**
         * Hide progress log
         */
        hideProgressLog() {
            const $progressLog = $('#ai-alt-bulk-progress');
            const $overlay = $('.alttextai-progress-overlay');
            
            if ($progressLog.length) {
                $progressLog.hide();
                $overlay.remove();
            }
            
            // Also hide the old progress bar
            $('.ai-alt-dashboard__status').hide();
        },

        /**
         * Add entry to progress log
         */
        addProgressLog(type, message) {
            const $logContent = $('.alttextai-progress-log-content');
            if (!$logContent.length) return;
            
            const time = new Date().toLocaleTimeString();
            const entryClass = `alttextai-progress-log-entry--${type}`;
            
            const $entry = $(`
                <div class="alttextai-progress-log-entry ${entryClass}">
                    <span class="alttextai-progress-log-time">${time}</span>
                    <span class="alttextai-progress-log-message">${message}</span>
                </div>
            `);
            
            $logContent.append($entry);
            
            // Auto-scroll to bottom
            $logContent.scrollTop($logContent[0].scrollHeight);
        },

        /**
         * Update progress statistics
         */
        updateProgressStats() {
            if (!this.progressStats) return;
            
            const { total, processed, success, errors } = this.progressStats;
            const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
            
            // Update percentage
            $('.alttextai-progress-stat__percentage').text(`${percentage}%`);
            
            // Update counts
            $('.alttextai-progress-stat__count').text(processed);
            $('.alttextai-progress-stat__success').text(success);
            $('.alttextai-progress-stat__errors').text(errors);
            
            // Update progress bar
            $('.alttextai-progress-bar-fill').css('width', `${percentage}%`);
        }
    };

    // ========================================
    // 🎨 INTERACTIVE ENHANCEMENTS
    // ========================================
    const AiAltEnhancements = {
        /**
         * Add sparkle effect on click
         */
        addSparkleOnClick() {
            $(document).on('click', '.ai-alt-btn, .ai-alt-achievement.unlocked, .ai-alt-microcard', function(e) {
                AiAltCelebration.sparkle(e.pageX, e.pageY);
            });
        },

        /**
         * Add hover sound effects (optional)
         */
        addSoundEffects() {
            // Placeholder for future sound effect implementation
        },

        /**
         * Add keyboard shortcuts
         */
        addKeyboardShortcuts() {
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + G = Generate missing
                if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                    e.preventDefault();
                    $('[data-action="generate-missing"]').first().click();
                }
            });
        },

        /**
         * Add tooltip enhancements
         */
        enhanceTooltips() {
            $('[title]').each(function() {
                $(this).attr('data-tooltip', $(this).attr('title'));
                $(this).removeAttr('title');
            });
        },

        /**
         * Bind upgrade modal functionality
         */
        bindUpgradeModal() {
            const enhancements = AiAltDashboard;
            const dashboard = AiAltDashboard;

            const handleRegenerate = function(e) {
                e.preventDefault();

                const $button = $(this);
                const attachmentId = $button.data('attachment-id');

                if (!attachmentId) {
                    AiAltToast.show({
                        type: 'error',
                        title: 'Missing ID',
                        message: 'We could not find that attachment. Please refresh and try again.',
                        duration: 3200
                    });
                    return;
                }

                const originalHtml = $button.html();
                $button.prop('disabled', true).addClass('loading').text('Regenerating…');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alttextai_regenerate_single',
                        attachment_id: attachmentId,
                        nonce: alttextai_ajax.nonce
                    }
                }).done(function(response) {
                    if (!response || !response.success) {
                        if (response?.data?.code === 'limit_reached') {
                            dashboard.trackUpgrade('limit-reached');
                            enhancements.showUpgradeModal();
                            return;
                        }
                        const message = response?.data?.message || 'Failed to regenerate alt text';
                        AiAltToast.show({
                            type: 'error',
                            title: 'Regeneration failed',
                            message,
                            duration: 3800
                        });
                        return;
                    }

                    const altText = response.data.alt_text || '';
                    const escapedAlt = escapeHtml(altText);
                    const $row = $button.closest('tr');
                    const $newCell = $row.find('.alttextai-table__cell--new');
                    const copyMarkup = `
                        <button type="button" class="alttextai-copy-trigger" data-copy-alt="${escapedAlt}" aria-label="Copy alt text to clipboard">
                            <span class="alttextai-copy-text">${escapedAlt}</span>
                            <span class="alttextai-copy-icon" aria-hidden="true">📋</span>
                        </button>
                    `;
                    $newCell.html(copyMarkup);
                    $row.attr('data-status', 'regenerated');

                    const $statusBadge = $row.find('.alttextai-status-badge');
                    $statusBadge
                        .removeClass('alttextai-status-badge--optimized alttextai-status-badge--missing')
                        .addClass('alttextai-status-badge--regenerated')
                        .text('🔁 Regenerated');

                    AiAltToast.show({
                        type: 'success',
                        title: '⚡ Regenerated',
                        message: 'Regenerated alt text based on new context.',
                        duration: 3200
                    });

                    AiAltDashboard.refreshUsageStats({ silentToast: true });
                    AiAltDashboard.refreshStats();
                }).fail(function() {
                    AiAltToast.show({
                        type: 'error',
                        title: 'Network error',
                        message: 'Something went wrong. Please try again.',
                        duration: 3800
                    });
                }).always(function() {
                    $button.prop('disabled', false).removeClass('loading').html(originalHtml);
                });
            };

            $(document)
                .off('click.alttextaiRegenerate')
                .on('click.alttextaiRegenerate', '.alttextai-btn-regenerate, [data-action="regenerate-single"]', handleRegenerate);

            $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
                e.preventDefault();
                const source = $(this).data('upgradeSource') || 'dashboard';
                dashboard.trackUpgrade(source);
                enhancements.showUpgradeModal();
            });

            $(document).on('click', '[data-action="close-modal"]', function(e) {
                e.preventDefault();
                enhancements.hideUpgradeModal();
            });

            $(document).on('click', '.alttextai-modal-backdrop', function(e) {
                if (e.target === this) {
                    enhancements.hideUpgradeModal();
                }
            });

            $(document).on('keydown.alttextaiModal', function(e) {
                if (e.key === 'Escape') {
                    enhancements.hideUpgradeModal();
                }
            });

            $(document).on('click', '[data-action="refresh-usage"]', function(e) {
                e.preventDefault();
                AiAltDashboard.refreshUsageStats({ showToast: true });
            });

            $(document).on('click', '.alttextai-copy-trigger', function(e) {
                e.preventDefault();
                const textToCopy = $(this).data('copy-alt') || '';
                if (!textToCopy) {
                    return;
                }

                const copyPromise = navigator.clipboard && navigator.clipboard.writeText
                    ? navigator.clipboard.writeText(textToCopy)
                    : new Promise((resolve, reject) => {
                        const textarea = document.createElement('textarea');
                        textarea.value = textToCopy;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.focus();
                        textarea.select();
                        try {
                            const successful = document.execCommand('copy');
                            document.body.removeChild(textarea);
                            successful ? resolve() : reject();
                        } catch (err) {
                            document.body.removeChild(textarea);
                            reject(err);
                        }
                    });

                copyPromise.then(() => {
                    AiAltToast.show({
                        type: 'success',
                        title: 'Copied',
                        message: 'Alt text copied to your clipboard.',
                        duration: 2200
                    });
                }).catch(() => {
                    AiAltToast.show({
                        type: 'error',
                        title: 'Copy failed',
                        message: 'Please press ⌘+C / Ctrl+C to copy manually.',
                        duration: 3200
                    });
                });
            });

            $(document).on('click', '.alttextai-progress-close', function(e) {
                e.preventDefault();
                enhancements.hideProgressLog();
            });

            $(document).on('click', '.alttextai-progress-overlay', function(e) {
                if (e.target === this) {
                    enhancements.hideProgressLog();
                }
            });

            $(document).on('click', '.alttextai-progress-cancel', function(e) {
                e.preventDefault();
                if (dashboard.isBulkProcessing) {
                    dashboard.addProgressLog('warning', '🛑 Cancelling bulk optimization...');
                    dashboard.isBulkProcessing = false;
                    dashboard.finishBulk($('[data-action="generate-missing"]'));
                }
            });

            $(document).on('click', '[data-action="upgrade-plan"], [data-action="buy-credits"]', function(e) {
                e.preventDefault();
                const checkoutUrl = $(this).data('checkout-url');
                if (checkoutUrl) {
                    window.location.href = checkoutUrl;
                }
            });
        },

        /**
         * Show upgrade modal
         */
        showUpgradeModal() {
            const modal = $('#alttextai-upgrade-modal');
            if (modal.length) {
                modal.fadeIn(300);
                document.body.style.overflow = 'hidden';
            }
        },

        /**
         * Hide upgrade modal
         */
        hideUpgradeModal() {
            const modal = $('#alttextai-upgrade-modal');
            if (modal.length) {
                modal.fadeOut(300);
                document.body.style.overflow = '';
            }
        },

        /**
         * Show toast notification
         */
        showToast(message, type = 'info', title = null) {
            // Remove existing toasts
            $('.alttextai-toast').remove();
            
            const toastClass = type === 'success' ? 'alttextai-toast--success' : 
                              type === 'error' ? 'alttextai-toast--error' : 
                              'alttextai-toast--info';
            
            // Set default titles based on type
            if (!title) {
                title = type === 'success' ? 'Success!' : 
                       type === 'error' ? 'Error' : 
                       'Info';
            }
            
            // Set icons based on type
            const icon = type === 'success' ? '✓' : 
                        type === 'error' ? '⚠' : 
                        'ℹ';
            
            const toast = $(`
                <div class="alttextai-toast ${toastClass}">
                    <div class="alttextai-toast__icon">${icon}</div>
                    <div class="alttextai-toast__content">
                        <div class="alttextai-toast__title">${title}</div>
                        <div class="alttextai-toast__message">${message}</div>
                    </div>
                    <button class="alttextai-toast__close" aria-label="Close notification">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 8.707l3.646 3.647.708-.707L8.707 8l3.647-3.646-.707-.708L8 7.293 4.354 3.646l-.707.708L7.293 8l-3.646 3.646.707.708L8 8.707z"/>
                        </svg>
                    </button>
                </div>
            `);
            
            $('body').append(toast);
            
            // Show toast with animation
            setTimeout(() => toast.addClass('alttextai-toast--show'), 100);
            
            // Auto-hide after 5 seconds for success, 7 seconds for errors
            const hideDelay = type === 'error' ? 7000 : 5000;
            setTimeout(() => {
                toast.removeClass('alttextai-toast--show').addClass('alttextai-toast--hide');
                setTimeout(() => toast.remove(), 300);
            }, hideDelay);
            
            // Close button handler
            toast.find('.alttextai-toast__close').on('click', function() {
                toast.removeClass('alttextai-toast--show').addClass('alttextai-toast--hide');
                setTimeout(() => toast.remove(), 300);
            });
        },

        /**
         * Refresh usage stats
         */
        refreshUsageStats(options = {}) {
            const settings = $.extend({
                showToast: false,
                silentToast: false
            }, options);

            const $buttons = $('[data-action="refresh-usage"]');
            $buttons.each(function() {
                const $btn = $(this);
                if (!$btn.data('alttextai-original')) {
                    $btn.data('alttextai-original', $btn.html());
                }
                $btn.prop('disabled', true)
                    .addClass('is-refreshing')
                    .html('<span aria-hidden="true">🔄</span> Refreshing…');
            });

            const self = this;
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alttextai_refresh_usage',
                    nonce: alttextai_ajax.nonce
                }
            }).done(function(response) {
                if (response && response.success) {
                    self.usage = response.data;
                    self.renderUsage();
                    if (settings.showToast && !settings.silentToast) {
                        AiAltToast.show({
                            type: 'success',
                            title: 'Usage updated',
                            message: 'Usage data refreshed successfully.',
                            duration: 2600
                        });
                    }
                    return;
                }

                const message = response?.data?.message || 'Could not refresh usage data.';
                AiAltToast.show({
                    type: 'error',
                    title: 'Refresh failed',
                    message,
                    duration: 4200
                });
            }).fail(function() {
                AiAltToast.show({
                    type: 'error',
                    title: 'Network error',
                    message: 'We couldn’t refresh usage right now. Please try again.',
                    duration: 4200
                });
            }).always(function() {
                $buttons.each(function() {
                    const $btn = $(this);
                    const original = $btn.data('alttextai-original');
                    if (typeof original !== 'undefined') {
                        $btn.html(original);
                    }
                    $btn.prop('disabled', false).removeClass('is-refreshing');
                });
                self.updateBulkButtonState();
            });
        },

        /**
         * Add entry to progress log
         */
        addProgressLog(type, message) {
            const $logContent = $('.alttextai-progress-log-content');
            if (!$logContent.length) return;
            
            const time = new Date().toLocaleTimeString();
            const entryClass = `alttextai-progress-log-entry--${type}`;
            
            const $entry = $(`
                <div class="alttextai-progress-log-entry ${entryClass}">
                    <span class="alttextai-progress-log-time">${time}</span>
                    <span class="alttextai-progress-log-message">${message}</span>
                </div>
            `);
            
            $logContent.append($entry);
            
            // Auto-scroll to bottom
            $logContent.scrollTop($logContent[0].scrollHeight);
        },

        /**
         * Update progress statistics
         */
        updateProgressStats() {
            if (!this.progressStats) {
                return;
            }
            
            const { total, processed, success, errors } = this.progressStats;
            const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
            
            // Check if elements exist
            const $percentage = $('.alttextai-progress-percentage');
            const $count = $('.alttextai-progress-count');
            const $success = $('.alttextai-progress-success');
            const $errors = $('.alttextai-progress-errors');
            const $bar = $('.alttextai-progress-bar-fill');
            
            // Update percentage
            if ($percentage.length) {
                $percentage.text(`${percentage}%`);
            }
            
            // Update counts
            if ($count.length) {
                $count.text(`${processed} / ${total}`);
            }
            if ($success.length) {
                $success.text(success);
            }
            if ($errors.length) {
                $errors.text(errors);
            }
            
            // Update progress bar
            if ($bar.length) {
                $bar.css('width', `${percentage}%`);
            }
        },

        /**
         * Get image name for logging
         */
        getImageName(attachmentId) {
            // Try to get filename from the page data
            const $row = $(`[data-attachment-id="${attachmentId}"]`).closest('tr');
            if ($row.length) {
                const $img = $row.find('img');
                if ($img.length) {
                    const src = $img.attr('src');
                    if (src) {
                        const filename = src.split('/').pop();
                        return filename || `Image ID ${attachmentId}`;
                    }
                }
            }
            return `Image ID ${attachmentId}`;
        },

        /**
         * Initialize countdown timer
         */
        initCountdown() {
            const $countdown = $('.alttextai-countdown');
            if (!$countdown.length) { return; }

            const secondsUntilReset = parseInt($countdown.data('countdown') || 0, 10);
            if (secondsUntilReset <= 0) {
                $countdown.find('[data-days]').text('0');
                $countdown.find('[data-hours]').text('0');
                $countdown.find('[data-minutes]').text('0');
                return;
            }

            let remainingSeconds = secondsUntilReset;

            function updateCountdown() {
                if (remainingSeconds <= 0) {
                    $countdown.find('[data-days]').text('0');
                    $countdown.find('[data-hours]').text('0');
                    $countdown.find('[data-minutes]').text('0');
                    return;
                }

                const days = Math.floor(remainingSeconds / (24 * 60 * 60));
                const hours = Math.floor((remainingSeconds % (24 * 60 * 60)) / (60 * 60));
                const minutes = Math.floor((remainingSeconds % (60 * 60)) / 60);

                $countdown.find('[data-days]').text(days);
                $countdown.find('[data-hours]').text(hours);
                $countdown.find('[data-minutes]').text(minutes);

                remainingSeconds -= 60; // Decrease by 1 minute (60 seconds)
            }

            updateCountdown();
            setInterval(updateCountdown, 60000); // Update every minute
        },

        /**
         * Check usage state on page load and set button state accordingly
         */
        checkUsageStateOnLoad() {
            // Get current usage from the page data
            const $usageText = $('.alttextai-usage-text');
            if (!$usageText.length) return;

            const usageText = $usageText.text();
            const match = usageText.match(/(\d+) of (\d+)/);
            if (!match) return;

            const used = parseInt(match[1], 10);
            const limit = parseInt(match[2], 10);

            // If limit is reached, disable the button and show limit reached state
            if (used >= limit) {
                const $bulkBtn = $('.alttextai-bulk-btn');
                $bulkBtn.addClass('alttextai-bulk-btn--limit');
                $bulkBtn.append('<span class="alttextai-bulk-btn__badge">LIMIT REACHED</span>');
                $bulkBtn.prop('disabled', true);
                
                // Hide any progress bars when limit is reached
                $('.ai-alt-dashboard__status').hide();
                $('#ai-alt-bulk-progress').hide();
                $('.alttextai-progress-overlay').remove();
            }
        }
    };

    // ========================================
    // 🚀 INITIALIZATION
    // ========================================
    $(document).ready(function() {
        // Only initialize on dashboard page
        if ($('.alttextai-clean-dashboard').length) {
            AiAltDashboard.init();
            AiAltEnhancements.addSparkleOnClick();
            AiAltEnhancements.addKeyboardShortcuts();
            AiAltEnhancements.bindUpgradeModal();
            AiAltEnhancements.initCountdown();
            
            // Check usage state on page load and set button state accordingly
            // Use setTimeout to ensure the object is fully initialized
            setTimeout(() => {
                if (typeof AiAltDashboard.checkUsageStateOnLoad === 'function') {
                    AiAltDashboard.checkUsageStateOnLoad();
                }
            }, 100);
            // Show welcome toast
            setTimeout(() => {
                AiAltToast.show({
                    type: 'info',
                    title: '👋 Welcome back!',
                    message: 'Keep up the great work on your accessibility journey!',
                    duration: 4000
                });
            }, 500);
        }

        // Hook into existing batch processing if available
        if (typeof window.AI_ALT_GPT !== 'undefined') {
            // Monitor for successful generations
            const originalSuccess = window.AI_ALT_GPT.onGenerationSuccess;
            window.AI_ALT_GPT.onGenerationSuccess = function(data) {
                if (originalSuccess) originalSuccess.call(this, data);
                
                // Show progress toast
                AiAltToast.show({
                    type: 'success',
                    title: '✨ Alt Text Generated',
                    message: `Successfully processed image #${data.id || 'unknown'}`,
                    duration: 2000
                });
            };
        }
    });

    if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
        window.addEventListener('ai-alt-stats-update', function(event) {
            if (!event || !event.detail) { return; }
            const stats = event.detail.stats || event.detail;
            const usage = event.detail.usage || null;
            if (stats && typeof stats === 'object') {
                AiAltDashboard.updateStats(stats, { silent: true });
            }
            if (usage && typeof usage === 'object') {
                AiAltDashboard.usage = usage;
                AiAltDashboard.renderUsage();
            }
        });
        window.addEventListener('beforeunload', function() {
            if (window.AiAltDashboard && typeof window.AiAltDashboard.stopQueuePolling === 'function') {
                window.AiAltDashboard.stopQueuePolling();
            }
        });
    }

    // Export for global access
    window.AiAltGamification = AiAltGamification;
    window.AiAltCelebration = AiAltCelebration;
    window.AiAltToast = AiAltToast;
    window.AiAltDashboard = AiAltDashboard;
    window.AiAltEnhancements = AiAltEnhancements;

    // ========================================
    // 🌐 GLOBAL FUNCTIONS
    // ========================================
    // Make upgrade modal functions globally available
    window.showUpgradeModal = function() {
        AiAltEnhancements.showUpgradeModal();
    };
    
    window.hideUpgradeModal = function() {
        AiAltEnhancements.hideUpgradeModal();
    };

})(jQuery);
