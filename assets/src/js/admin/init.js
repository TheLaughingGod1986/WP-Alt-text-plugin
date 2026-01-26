/**
 * Admin Initialization
 * Main entry point for admin functionality
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        if (window.BBAI_DEBUG) {
            console.log('[AI Alt Text] Admin JavaScript loaded');
            console.log('[AI Alt Text] Config:', window.bbaiAdminConfig);
        }

        // Handle generate missing button
        $(document).on('click', '[data-action="generate-missing"]', function(e) {
            if (typeof window.handleGenerateMissing === 'function') {
                window.handleGenerateMissing.call(this, e);
            } else if (typeof handleGenerateMissing === 'function') {
                handleGenerateMissing.call(this, e);
            } else {
                console.error('[AI Alt Text] handleGenerateMissing function not found');
            }
        });

        // Handle regenerate all button
        $(document).on('click', '[data-action="regenerate-all"]', function(e) {
            if (typeof window.handleRegenerateAll === 'function') {
                window.handleRegenerateAll.call(this, e);
            } else if (typeof handleRegenerateAll === 'function') {
                handleRegenerateAll.call(this, e);
            } else {
                console.error('[AI Alt Text] handleRegenerateAll function not found');
            }
        });

        // Handle individual regenerate buttons
        $(document).on('click', '[data-action="regenerate-single"]', function(e) {
            if (typeof window.handleRegenerateSingle === 'function') {
                window.handleRegenerateSingle.call(this, e);
            } else if (typeof handleRegenerateSingle === 'function') {
                handleRegenerateSingle.call(this, e);
            } else {
                console.error('[AI Alt Text] handleRegenerateSingle function not found');
            }
        });

        // License management handlers
        $('#license-activation-form').on('submit', handleLicenseActivation);
        $(document).on('click', '[data-action="deactivate-license"]', handleLicenseDeactivation);
    });

})(jQuery);
