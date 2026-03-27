/**
 * BeepBeep AI Loading States Manager
 * Handles skeleton loaders, progress bars, and loading indicators
 */

(function() {
    'use strict';

    const bbaiLoading = {
        /**
         * Show skeleton loader for an element
         */
        showSkeleton: function(element, type = 'text') {
            if (!element) return;

            const skeleton = document.createElement('div');
            skeleton.className = `bbai-skeleton bbai-skeleton--${type}`;
            skeleton.setAttribute('aria-hidden', 'true');

            // Store original content
            if (!element.dataset.originalContent) {
                element.dataset.originalContent = element.innerHTML;
            }

            element.classList.add('bbai-loading');
            element.innerHTML = '';
            element.appendChild(skeleton);
        },

        /**
         * Hide skeleton loader
         */
        hideSkeleton: function(element) {
            if (!element) return;

            element.classList.remove('bbai-loading');
            const originalContent = element.dataset.originalContent;
            if (originalContent) {
                element.innerHTML = originalContent;
                delete element.dataset.originalContent;
            }
        },

        /**
         * Show progress bar with ETA
         */
        showProgress: function(container, options = {}) {
            const {
                current = 0,
                total = 100,
                label = '',
                showETA = true,
                showPercentage = true
            } = options;

            const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
            const remaining = total - current;
            const estimatedTime = this.calculateETA(current, total, options.startTime);

            const progressHTML = `
                <div class="bbai-progress-bar-container">
                    <div class="bbai-progress-bar-header">
                        ${label ? `<span class="bbai-progress-label">${label}</span>` : ''}
                        ${showPercentage ? `<span class="bbai-progress-percentage">${percentage}%</span>` : ''}
                    </div>
                    <div class="bbai-progress-bar">
                        <div class="bbai-progress-bar-fill" style="width: ${percentage}%;"></div>
                    </div>
                    <div class="bbai-progress-bar-footer">
                        <span class="bbai-progress-count">${current} / ${total}</span>
                        ${showETA && estimatedTime ? `<span class="bbai-progress-eta">${estimatedTime}</span>` : ''}
                    </div>
                </div>
            `;

            if (container) {
                container.innerHTML = progressHTML;
                container.classList.add('bbai-progress-active');
            }

            return {
                update: (newCurrent, newTotal) => {
                    this.updateProgress(container, newCurrent, newTotal || total, options);
                },
                complete: () => {
                    this.hideProgress(container);
                }
            };
        },

        /**
         * Update progress bar
         */
        updateProgress: function(container, current, total, options = {}) {
            if (!container) return;

            const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
            const fill = container.querySelector('.bbai-progress-bar-fill');
            const percentageEl = container.querySelector('.bbai-progress-percentage');
            const countEl = container.querySelector('.bbai-progress-count');
            const etaEl = container.querySelector('.bbai-progress-eta');

            if (fill) {
                fill.style.width = percentage + '%';
            }

            if (percentageEl) {
                percentageEl.textContent = percentage + '%';
            }

            if (countEl) {
                countEl.textContent = `${current} / ${total}`;
            }

            if (etaEl && options.showETA) {
                const estimatedTime = this.calculateETA(current, total, options.startTime);
                if (estimatedTime) {
                    etaEl.textContent = estimatedTime;
                }
            }
        },

        /**
         * Hide progress bar
         */
        hideProgress: function(container) {
            if (!container) return;
            container.classList.remove('bbai-progress-active');
            container.innerHTML = '';
        },

        /**
         * Calculate ETA based on current progress
         */
        calculateETA: function(current, total, startTime) {
            if (!startTime || current === 0) return null;

            const elapsed = (Date.now() - startTime) / 1000; // seconds
            const rate = current / elapsed; // items per second
            const remaining = total - current;

            if (rate <= 0) return null;

            const etaSeconds = Math.ceil(remaining / rate);

            if (etaSeconds < 60) {
                return `${etaSeconds}s remaining`;
            } else if (etaSeconds < 3600) {
                const minutes = Math.floor(etaSeconds / 60);
                return `${minutes}m remaining`;
            } else {
                const hours = Math.floor(etaSeconds / 3600);
                const minutes = Math.floor((etaSeconds % 3600) / 60);
                return `${hours}h ${minutes}m remaining`;
            }
        },

        /**
         * Show loading spinner
         */
        showSpinner: function(element, size = 'md') {
            if (!element) return;

            const spinner = document.createElement('div');
            spinner.className = `bbai-spinner bbai-spinner-${size}`;
            spinner.setAttribute('aria-label', 'Loading...');
            spinner.setAttribute('role', 'status');

            element.classList.add('bbai-loading');
            element.appendChild(spinner);

            return spinner;
        },

        /**
         * Hide loading spinner
         */
        hideSpinner: function(element) {
            if (!element) return;

            const spinner = element.querySelector('.bbai-spinner');
            if (spinner) {
                spinner.remove();
            }
            element.classList.remove('bbai-loading');
        },

        /**
         * Show button loading state
         */
        setButtonLoading: function(button, loading = true, text = null) {
            if (!button) return;

            if (loading) {
                button.disabled = true;
                button.classList.add('is-loading');

                if (text) {
                    button.dataset.originalText = button.textContent;
                    button.innerHTML = `<span class="bbai-spinner bbai-spinner-sm"></span> ${text}`;
                } else {
                    const spinner = document.createElement('span');
                    spinner.className = 'bbai-spinner bbai-spinner-sm';
                    button.insertBefore(spinner, button.firstChild);
                }
            } else {
                button.disabled = false;
                button.classList.remove('is-loading');

                const spinner = button.querySelector('.bbai-spinner');
                if (spinner) {
                    spinner.remove();
                }

                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                    delete button.dataset.originalText;
                }
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.bbaiLoading = bbaiLoading;
        });
    } else {
        window.bbaiLoading = bbaiLoading;
    }
})();
