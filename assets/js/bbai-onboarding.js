/**
 * BeepBeep AI Onboarding System
 * Handles welcome modal and interactive tour
 */

(function() {
    'use strict';

    const bbaiOnboarding = {
        currentStep: 1,
        totalSteps: 3,
        modal: null,

        init: function() {
            // Check if onboarding should be shown
            this.checkOnboardingStatus();
            this.bindEvents();
        },

        /**
         * Check if onboarding should be shown
         */
        checkOnboardingStatus: function() {
            // Check via AJAX if onboarding is completed
            if (typeof bbai_ajax !== 'undefined' && bbai_ajax.ajaxurl) {
                jQuery.ajax({
                    url: bbai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bbai_check_onboarding',
                        nonce: bbai_ajax.nonce || ''
                    },
                    success: (response) => {
                        if (response && response.success && !response.data.completed) {
                            this.show();
                        }
                    },
                    error: () => {
                        // On error, show onboarding (safe default)
                        this.show();
                    }
                });
            } else {
                // Fallback: show if modal exists
                const modal = document.getElementById('bbai-onboarding-modal');
                if (modal) {
                    this.modal = modal;
                    this.show();
                }
            }
        },

        /**
         * Show onboarding modal
         */
        show: function() {
            if (!this.modal) {
                this.modal = document.getElementById('bbai-onboarding-modal');
            }

            if (!this.modal) return;

            this.modal.style.display = 'block';
            setTimeout(() => {
                this.modal.classList.add('show');
            }, 10);

            // Focus trap
            const firstButton = this.modal.querySelector('button');
            if (firstButton) {
                firstButton.focus();
            }

            this.updateProgress();
        },

        /**
         * Hide onboarding modal
         */
        hide: function() {
            if (!this.modal) return;

            this.modal.classList.remove('show');
            setTimeout(() => {
                this.modal.style.display = 'none';
            }, 300);
        },

        /**
         * Go to next step
         */
        next: function() {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateStep();
            }
        },

        /**
         * Go to previous step
         */
        prev: function() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.updateStep();
            }
        },

        /**
         * Update current step display
         */
        updateStep: function() {
            if (!this.modal) return;

            // Hide all steps
            const steps = this.modal.querySelectorAll('.bbai-onboarding-step');
            steps.forEach(step => {
                step.classList.remove('bbai-onboarding-step--active');
            });

            // Show current step
            const currentStepEl = this.modal.querySelector(`[data-step="${this.currentStep}"]`);
            if (currentStepEl) {
                currentStepEl.classList.add('bbai-onboarding-step--active');
            }

            this.updateProgress();
        },

        /**
         * Update progress indicator
         */
        updateProgress: function() {
            if (!this.modal) return;

            const percentage = (this.currentStep / this.totalSteps) * 100;
            const fill = this.modal.querySelector('.bbai-onboarding-progress-fill');
            if (fill) {
                fill.style.width = percentage + '%';
            }

            // Update step indicators
            const stepIndicators = this.modal.querySelectorAll('.bbai-onboarding-progress-step');
            stepIndicators.forEach((indicator, index) => {
                if (index + 1 <= this.currentStep) {
                    indicator.classList.add('bbai-onboarding-progress-step--active');
                } else {
                    indicator.classList.remove('bbai-onboarding-progress-step--active');
                }
            });
        },

        /**
         * Complete onboarding
         */
        complete: function() {
            // Mark as completed via AJAX
            if (typeof bbai_ajax !== 'undefined' && bbai_ajax.ajaxurl) {
                jQuery.ajax({
                    url: bbai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bbai_complete_onboarding',
                        nonce: bbai_ajax.nonce || ''
                    },
                    success: () => {
                        this.hide();
                        // Show success toast if available
                        if (window.bbaiToast) {
                            window.bbaiToast.success('Welcome to BeepBeep AI! You\'re all set.');
                        }
                    },
                    error: () => {
                        // Hide anyway on error
                        this.hide();
                    }
                });
            } else {
                this.hide();
            }
        },

        /**
         * Skip onboarding
         */
        skip: function() {
            this.complete();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            document.addEventListener('click', (e) => {
                const action = e.target.closest('[data-onboarding-action]');
                if (!action) return;

                const actionType = action.getAttribute('data-onboarding-action');
                switch (actionType) {
                    case 'next':
                        this.next();
                        break;
                    case 'prev':
                        this.prev();
                        break;
                    case 'complete':
                        this.complete();
                        break;
                    case 'skip':
                        this.skip();
                        break;
                }
            });

            // Close on overlay click
            if (this.modal) {
                const overlay = this.modal.querySelector('.bbai-onboarding-modal-overlay');
                if (overlay) {
                    overlay.addEventListener('click', () => {
                        this.skip();
                    });
                }
            }

            // Close on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal && this.modal.classList.contains('show')) {
                    this.skip();
                }
            });
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiOnboarding.init());
    } else {
        bbaiOnboarding.init();
    }

    // Expose globally
    window.bbaiOnboarding = bbaiOnboarding;
})();
