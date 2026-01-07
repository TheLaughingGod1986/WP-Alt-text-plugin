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
        $(document).on('click', '[data-action="generate-missing"]', handleGenerateMissing);

        // Handle regenerate all button
        $(document).on('click', '[data-action="regenerate-all"]', handleRegenerateAll);

        // Handle individual regenerate buttons
        $(document).on('click', '[data-action="regenerate-single"]', function(e) {
            handleRegenerateSingle.call(this, e);
        });

        // License management handlers
        $('#license-activation-form').on('submit', handleLicenseActivation);
        $(document).on('click', '[data-action="deactivate-license"]', handleLicenseDeactivation);
    });

})(jQuery);
