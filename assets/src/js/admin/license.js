/**
 * License Management
 * Handles license activation and deactivation
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    /**
     * Handle license activation form submission
     */
    window.handleLicenseActivation = function(e) {
        e.preventDefault();

        var $form = $('#license-activation-form');
        var $input = $('#license-key-input');
        var $button = $('#activate-license-btn');
        var $status = $('#license-activation-status');
        var nonce = $('#license-nonce').val();

        var licenseKey = $input.val().trim();

        if (!licenseKey) {
            showLicenseStatus('error', 'Please enter a license key');
            return;
        }

        $button.prop('disabled', true).text('Activating...');
        $input.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_activate_license',
                nonce: nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    showLicenseStatus('success', response.data.message || 'License activated successfully!');

                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showLicenseStatus('error', response.data.message || 'Failed to activate license');
                    $button.prop('disabled', false).text('Activate License');
                    $input.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                showLicenseStatus('error', 'Network error: ' + error);
                $button.prop('disabled', false).text('Activate License');
                $input.prop('disabled', false);
            }
        });
    };

    /**
     * Handle license deactivation
     */
    window.handleLicenseDeactivation = function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to deactivate this license? You will need to reactivate it to continue using the shared quota.')) {
            return;
        }

        var $button = $(this);
        var nonce = $('#license-nonce').val();

        $button.prop('disabled', true).text('Deactivating...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_deactivate_license',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    window.bbaiModal.show({
                        type: 'success',
                        title: 'Success',
                        message: response.data.message || 'License deactivated successfully',
                        onClose: function() {
                            window.location.reload();
                        }
                    });
                } else {
                    window.bbaiModal.error('Error: ' + (response.data.message || 'Failed to deactivate license'));
                    $button.prop('disabled', false).text('Deactivate License');
                }
            },
            error: function(xhr, status, error) {
                window.bbaiModal.error('Network error: ' + error);
                $button.prop('disabled', false).text('Deactivate License');
            }
        });
    };

    /**
     * Show status message in license activation form
     */
    function showLicenseStatus(type, message) {
        var $status = $('#license-activation-status');
        var iconHtml = '';
        var bgColor = '';
        var textColor = '';

        if (type === 'success') {
            iconHtml = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            bgColor = '#d1fae5';
            textColor = '#065f46';
        } else {
            iconHtml = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            bgColor = '#fee2e2';
            textColor = '#991b1b';
        }

        $status.html(
            '<div style="padding: 12px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 4px; font-size: 14px;">' +
            iconHtml + message +
            '</div>'
        ).show();
    }

})(jQuery);
