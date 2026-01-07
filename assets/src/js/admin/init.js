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
        console.log('[AI Alt Text] Admin JavaScript loaded');
        console.log('[AI Alt Text] Config:', window.bbaiAdminConfig);
        console.log('[AI Alt Text] Has bulk config:', window.bbaiHasBulkConfig);
        console.log('[AI Alt Text] bbai_ajax:', window.bbai_ajax);

        // Count regenerate buttons
        var regenButtons = $('[data-action="regenerate-single"]');
        console.log('[AI Alt Text] Found ' + regenButtons.length + ' regenerate buttons');

        // Handle generate missing button
        $(document).on('click', '[data-action="generate-missing"]', handleGenerateMissing);

        // Handle regenerate all button
        $(document).on('click', '[data-action="regenerate-all"]', handleRegenerateAll);

        // Handle individual regenerate buttons
        $(document).on('click', '[data-action="regenerate-single"]', function(e) {
            console.log('[AI Alt Text] Regenerate button click event fired!');
            handleRegenerateSingle.call(this, e);
        });

        // License management handlers
        $('#license-activation-form').on('submit', handleLicenseActivation);
        $(document).on('click', '[data-action="deactivate-license"]', handleLicenseDeactivation);

        console.log('[AI Alt Text] Admin handlers registered');
    });

})(jQuery);
