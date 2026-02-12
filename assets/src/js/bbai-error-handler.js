/**
 * BeepBeep AI Enhanced Error Handling
 * User-friendly error messages with retry options
 */

(function() {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;
    const sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : (format) => format;

    const bbaiErrorHandler = {
        retryAttempts: new Map(),
        maxRetries: 3,

        init: function() {
            this.interceptAjaxErrors();
            this.interceptFetchErrors();
            this.setupGlobalErrorHandlers();
        },

        /**
         * Intercept jQuery AJAX errors
         */
        interceptAjaxErrors: function() {
            if (typeof jQuery === 'undefined') return;

            // Store original ajaxError handler
            const originalAjaxError = jQuery.ajaxSetup ? jQuery.ajaxSetup() : null;

            // Override ajaxError globally
            jQuery(document).ajaxError((event, xhr, settings, error) => {
                this.handleAjaxError(xhr, settings, error);
            });
        },

        /**
         * Intercept fetch errors
         */
        interceptFetchErrors: function() {
            const originalFetch = window.fetch;
            window.fetch = (...args) => {
                return originalFetch.apply(this, args)
                    .then(response => {
                        if (!response.ok) {
                            return this.handleFetchError(response, args);
                        }
                        return response;
                    })
                    .catch(error => {
                        this.handleFetchError(error, args);
                        throw error;
                    });
            };
        },

        /**
         * Setup global error handlers
         */
        setupGlobalErrorHandlers: function() {
            window.addEventListener('error', (event) => {
                this.handleGlobalError(event.error || event.message);
            });

            window.addEventListener('unhandledrejection', (event) => {
                this.handlePromiseRejection(event.reason);
            });
        },

        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, settings, error) {
            const url = settings.url || '';
            const method = settings.method || settings.type || 'GET';
            
            // Skip if it's a non-critical request
            if (this.shouldIgnoreError(url, method)) {
                return;
            }

            const errorData = this.parseError(xhr, error);
            this.showErrorWithRetry(errorData, () => {
                return this.retryRequest(settings);
            });
        },

        /**
         * Handle fetch errors
         */
        handleFetchError: function(response, args) {
            const url = args[0] || '';
            const options = args[1] || {};
            
            if (this.shouldIgnoreError(url, options.method || 'GET')) {
                return Promise.reject(response);
            }

            const errorData = this.parseFetchError(response);
            this.showErrorWithRetry(errorData, () => {
                return fetch(...args);
            });

            return Promise.reject(response);
        },

        /**
         * Parse error from AJAX response
         */
        parseError: function(xhr, error) {
            let message = __('An error occurred. Please try again.', 'beepbeep-ai-alt-text-generator');
            let code = 'unknown_error';
            let details = null;

            // Try to parse JSON response
            try {
                const response = JSON.parse(xhr.responseText || '{}');
                if (response.message) {
                    message = response.message;
                }
                if (response.code) {
                    code = response.code;
                }
                if (response.data) {
                    details = response.data;
                }
            } catch (e) {
                // Use default message
            }

            // Map HTTP status codes to user-friendly messages
            if (xhr.status === 0) {
                message = __('Network error. Please check your internet connection.', 'beepbeep-ai-alt-text-generator');
                code = 'network_error';
            } else if (xhr.status === 401) {
                message = __('Your session has expired. Please log in again.', 'beepbeep-ai-alt-text-generator');
                code = 'unauthorized';
            } else if (xhr.status === 403) {
                message = __("You don't have permission to perform this action.", 'beepbeep-ai-alt-text-generator');
                code = 'forbidden';
            } else if (xhr.status === 404) {
                message = __('The requested resource was not found.', 'beepbeep-ai-alt-text-generator');
                code = 'not_found';
            } else if (xhr.status === 429) {
                message = __('Too many requests. Please wait a moment and try again.', 'beepbeep-ai-alt-text-generator');
                code = 'rate_limited';
            } else if (xhr.status >= 500) {
                message = __('Server error. Our team has been notified. Please try again later.', 'beepbeep-ai-alt-text-generator');
                code = 'server_error';
            }

            return {
                message: message,
                code: code,
                status: xhr.status,
                details: details,
                originalError: error
            };
        },

        /**
         * Parse fetch error
         */
        parseFetchError: function(response) {
            let message = __('An error occurred. Please try again.', 'beepbeep-ai-alt-text-generator');
            let code = 'unknown_error';

            if (response.status === 0) {
                message = __('Network error. Please check your internet connection.', 'beepbeep-ai-alt-text-generator');
                code = 'network_error';
            } else if (response.status === 401) {
                message = __('Your session has expired. Please log in again.', 'beepbeep-ai-alt-text-generator');
                code = 'unauthorized';
            } else if (response.status === 403) {
                message = __("You don't have permission to perform this action.", 'beepbeep-ai-alt-text-generator');
                code = 'forbidden';
            } else if (response.status === 429) {
                message = __('Too many requests. Please wait a moment and try again.', 'beepbeep-ai-alt-text-generator');
                code = 'rate_limited';
            } else if (response.status >= 500) {
                message = __('Server error. Our team has been notified. Please try again later.', 'beepbeep-ai-alt-text-generator');
                code = 'server_error';
            }

            return {
                message: message,
                code: code,
                status: response.status
            };
        },

        /**
         * Show error with retry option
         */
        showErrorWithRetry: function(errorData, retryCallback) {
            const retryKey = errorData.code + '_' + Date.now();
            const attempts = this.retryAttempts.get(retryKey) || 0;

            if (attempts >= this.maxRetries) {
                this.showError(errorData, false);
                return;
            }

            // Show error toast with retry button
            if (window.bbaiPushToast) {
                const canRetry = this.canRetry(errorData.code);
                
                window.bbaiPushToast('error', errorData.message, {
                    duration: canRetry ? 10000 : 5000,
                    actionText: canRetry ? __('Retry', 'beepbeep-ai-alt-text-generator') : null,
                    onAction: canRetry ? () => {
                        this.retryAttempts.set(retryKey, attempts + 1);
                        retryCallback();
                    } : null,
                    dismissible: true
                });
            } else {
                // Fallback to alert
                alert(errorData.message + (this.canRetry(errorData.code) ? '\n\n' + __('Click OK to retry.', 'beepbeep-ai-alt-text-generator') : ''));
                if (this.canRetry(errorData.code) && attempts < this.maxRetries) {
                    this.retryAttempts.set(retryKey, attempts + 1);
                    setTimeout(() => retryCallback(), 1000);
                }
            }
        },

        /**
         * Show error without retry
         */
        showError: function(errorData, showHelpLink = true) {
            if (window.bbaiPushToast) {
                let message = errorData.message;
                if (showHelpLink && errorData.code === 'server_error') {
                    message += ' ' + __('Need help? Contact support.', 'beepbeep-ai-alt-text-generator');
                }
                
                window.bbaiPushToast('error', message, {
                    duration: 8000,
                    dismissible: true
                });
            } else {
                alert(errorData.message);
            }
        },

        /**
         * Check if error can be retried
         */
        canRetry: function(errorCode) {
            const retryableErrors = ['network_error', 'server_error', 'rate_limited', 'unknown_error'];
            return retryableErrors.includes(errorCode);
        },

        /**
         * Retry request
         */
        retryRequest: function(settings) {
            if (typeof jQuery !== 'undefined') {
                return jQuery.ajax(settings);
            }
            return Promise.reject(new Error('jQuery not available'));
        },

        /**
         * Check if error should be ignored
         */
	        shouldIgnoreError: function(url, method) {
	            // Ignore certain endpoints
	            const ignoredPatterns = [
	                '/heartbeat'
	            ];
	            const env = (window.bbai_env || {});
	            const restRoot = env.rest_root || ((window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : '');
	            if (restRoot) {
	                const escapedRoot = String(restRoot).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	                ignoredPatterns.push(escapedRoot + 'wp/v2/users/me');
	            }
	            if (env.ajax_url) {
	                const escaped = env.ajax_url.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	                ignoredPatterns.push(escaped + '.*action=heartbeat');
	            }

            return ignoredPatterns.some(pattern => {
                const regex = new RegExp(pattern);
                return regex.test(url);
            });
        },

        /**
         * Handle global errors
         */
        handleGlobalError: function(error) {
            // Only show user-friendly errors, not all JavaScript errors
            if (error && typeof error === 'object' && error.userFriendly) {
                this.showError({
                    message: error.message || __('An unexpected error occurred.', 'beepbeep-ai-alt-text-generator'),
                    code: 'javascript_error'
                });
            }
        },

        /**
         * Handle promise rejections
         */
        handlePromiseRejection: function(reason) {
            if (reason && typeof reason === 'object' && reason.userFriendly) {
                this.showError({
                    message: reason.message || __('An error occurred while processing your request.', 'beepbeep-ai-alt-text-generator'),
                    code: 'promise_rejection'
                });
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiErrorHandler.init());
    } else {
        bbaiErrorHandler.init();
    }

    // Expose globally
    window.bbaiErrorHandler = bbaiErrorHandler;
})();
