/**
 * BeepBeep AI Celebrations System
 * Success celebrations, confetti, and milestone tracking
 */

(function() {
    'use strict';

    const bbaiCelebrations = {
        milestones: [10, 50, 100, 500, 1000],
        currentMilestone: null,

        init: function() {
            this.checkMilestones();
            this.bindEvents();
        },

        /**
         * Check for milestone achievements
         */
        checkMilestones: function() {
            // Get current count from dashboard stats
            const optimizedCount = this.getOptimizedCount();
            if (!optimizedCount) return;

            // Check if user has reached a new milestone
            const achievedMilestones = this.milestones.filter(m => optimizedCount >= m);
            if (achievedMilestones.length === 0) return;

            // Get the highest achieved milestone
            const highestMilestone = Math.max(...achievedMilestones);

            // Check if this milestone was already celebrated
            if (typeof bbai_ajax !== 'undefined' && bbai_ajax.ajaxurl) {
                jQuery.ajax({
                    url: bbai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bbai_check_milestone',
                        milestone: highestMilestone,
                        nonce: bbai_ajax.nonce || ''
                    },
                    success: (response) => {
                        if (response && response.success && response.data.new_milestone) {
                            this.celebrateMilestone(highestMilestone, optimizedCount);
                        }
                    }
                });
            }
        },

        /**
         * Get optimized count from page
         */
        getOptimizedCount: function() {
            // Try to get from dashboard stats
            const statElement = document.querySelector('.bbai-metric-value');
            if (statElement) {
                const text = statElement.textContent.trim();
                const match = text.match(/(\d+)/);
                if (match) {
                    return parseInt(match[1], 10);
                }
            }

            // Try to get from BBAI_DASH stats if available
            if (typeof BBAI_DASH !== 'undefined' && BBAI_DASH.stats) {
                return BBAI_DASH.stats.with_alt || 0;
            }

            return 0;
        },

        /**
         * Celebrate milestone
         */
        celebrateMilestone: function(milestone, count) {
            // Show confetti
            this.showConfetti();

            // Show milestone notification
            this.showMilestoneNotification(milestone, count);

            // Track milestone
            this.trackMilestone(milestone);
        },

        /**
         * Show confetti animation
         */
        showConfetti: function() {
            const confetti = document.createElement('div');
            confetti.className = 'bbai-confetti-container';
            confetti.innerHTML = this.generateConfettiHTML();
            document.body.appendChild(confetti);

            // Remove after animation
            setTimeout(() => {
                if (confetti.parentNode) {
                    confetti.parentNode.removeChild(confetti);
                }
            }, 3000);
        },

        /**
         * Generate confetti HTML
         */
        generateConfettiHTML: function() {
            const colors = ['#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#EF4444'];
            let html = '';

            for (let i = 0; i < 50; i++) {
                const color = colors[Math.floor(Math.random() * colors.length)];
                const left = Math.random() * 100;
                const delay = Math.random() * 2;
                const duration = 2 + Math.random() * 2;
                html += `
                    <div class="bbai-confetti-piece" style="
                        left: ${left}%;
                        background: ${color};
                        animation-delay: ${delay}s;
                        animation-duration: ${duration}s;
                    "></div>
                `;
            }

            return html;
        },

        /**
         * Show milestone notification
         */
        showMilestoneNotification: function(milestone, count) {
            if (window.bbaiToast) {
                const message = `🎉 Milestone achieved! You've optimized ${count} images. Keep up the great work!`;
                window.bbaiToast.success(message, {
                    duration: 8000,
                    persistent: false
                });
            }

            // Show milestone badge modal
            this.showMilestoneBadge(milestone);
        },

        /**
         * Show milestone badge
         */
        showMilestoneBadge: function(milestone) {
            const badge = document.createElement('div');
            badge.className = 'bbai-milestone-badge';
            badge.innerHTML = `
                <div class="bbai-milestone-badge-content">
                    <div class="bbai-milestone-badge-icon">🎉</div>
                    <h3 class="bbai-milestone-badge-title">Milestone Achieved!</h3>
                    <p class="bbai-milestone-badge-message">You've optimized ${milestone} images</p>
                    <button type="button" class="bbai-milestone-badge-close">Awesome!</button>
                </div>
            `;

            document.body.appendChild(badge);

            // Show with animation
            setTimeout(() => {
                badge.classList.add('show');
            }, 100);

            // Close button
            const closeBtn = badge.querySelector('.bbai-milestone-badge-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    badge.classList.remove('show');
                    setTimeout(() => {
                        if (badge.parentNode) {
                            badge.parentNode.removeChild(badge);
                        }
                    }, 300);
                });
            }

            // Auto-close after 5 seconds
            setTimeout(() => {
                if (badge.parentNode) {
                    badge.classList.remove('show');
                    setTimeout(() => {
                        if (badge.parentNode) {
                            badge.parentNode.removeChild(badge);
                        }
                    }, 300);
                }
            }, 5000);
        },

        /**
         * Track milestone in backend
         */
        trackMilestone: function(milestone) {
            if (typeof bbai_ajax !== 'undefined' && bbai_ajax.ajaxurl) {
                jQuery.ajax({
                    url: bbai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bbai_track_milestone',
                        milestone: milestone,
                        nonce: bbai_ajax.nonce || ''
                    }
                });
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Listen for generation success events
            document.addEventListener('bbai:generation:success', (e) => {
                const count = e.detail?.count || 0;
                if (count > 0) {
                    // Check milestones after a short delay
                    setTimeout(() => {
                        this.checkMilestones();
                    }, 1000);
                }
            });
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiCelebrations.init());
    } else {
        bbaiCelebrations.init();
    }

    // Expose globally
    window.bbaiCelebrations = bbaiCelebrations;
})();
