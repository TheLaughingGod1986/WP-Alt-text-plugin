/**
 * Single Regenerate
 * Handles single image regeneration with preview modal
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    /**
     * Regenerate alt text for a single image - shows modal with preview
     */
    window.handleRegenerateSingle = function(e) {
        e.preventDefault();

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Regenerate button clicked');
        var $btn = $(this);
        var attachmentIdRaw = $btn.data('attachment-id') || $btn.data('attachmentId') || $btn.attr('data-attachment-id') || '';
        var attachmentId = parseInt(attachmentIdRaw, 10);

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Attachment ID:', attachmentId);

        if (!attachmentId || attachmentId <= 0 || $btn.prop('disabled')) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Cannot regenerate - missing ID or button disabled');
            return false;
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).addClass('regenerating');
        $btn.text('Processing...');

        var $row = $btn.closest('tr');
        var imageTitle = $row.find('.bbai-table__cell--title').text().trim() || 'Image';
        var imageSrc = $row.find('img').attr('src') || '';

        // Show skeleton in alt text cell while processing
        var $altCell = $row.find('.bbai-library-cell--alt-text');
        var originalCellContent = $altCell.html();
        $altCell.data('original-content', originalCellContent);
        $altCell.html(
            '<div class="bbai-alt-text-content">' +
                '<div class="bbai-skeleton bbai-skeleton--text" style="width: 90%; margin-bottom: 8px;"></div>' +
                '<div class="bbai-skeleton bbai-skeleton--text-sm" style="width: 60%;"></div>' +
            '</div>'
        );

        showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalText);
    };

    /**
     * Show regenerate modal and start generation
     */
    function showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalBtnText) {
        var $modal = $('#bbai-regenerate-modal');
        if (!$modal.length) {
            $modal = createRegenerateModal();
        }

        $modal.find('.bbai-regenerate-modal__image-title').text(imageTitle);
        $modal.find('.bbai-regenerate-modal__thumbnail').attr('src', imageSrc);

        $modal.find('.bbai-regenerate-modal__loading').addClass('active');
        $modal.find('.bbai-regenerate-modal__result').removeClass('active');
        $modal.find('.bbai-regenerate-modal__error').removeClass('active');

        $modal.find('.bbai-regenerate-modal__btn--accept').prop('disabled', true);

        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        // Prevent stale responses from previous regenerate requests from mutating the active modal.
        var requestKey = 'regen-' + attachmentId + '-' + Date.now();
        $modal.data('bbai-request-key', requestKey);

        var ajaxUrl = (window.bbai_ajax && window.bbai_ajax.ajaxurl) || '';
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) ||
                       (window.BBAI && window.BBAI.nonce) ||
                       '';
        if (!ajaxUrl) {
            showModalError($modal, 'AJAX endpoint unavailable.');
            reenableButton($btn, originalBtnText);
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'beepbeepai_regenerate_single',
                attachment_id: attachmentId,
                request_key: requestKey,
                nonce: nonceValue
            }
        })
        .done(function(response) {
            if ($modal.data('bbai-request-key') !== requestKey) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Ignoring stale regenerate response');
                return;
            }

            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');

            if (response && response.success) {
                var newAltText = (response.data && response.data.altText) || (response.data && response.data.alt_text) || '';

                if (newAltText) {
                    $modal.find('.bbai-regenerate-modal__alt-text').text(newAltText);
                    $modal.find('.bbai-regenerate-modal__result').addClass('active');

                    $modal.find('.bbai-regenerate-modal__btn--accept')
                        .prop('disabled', false)
                        .off('click')
                        .on('click', function() {
                            acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal);
                        });
                } else {
                    showModalError($modal, 'No alt text was generated. Please try again.', function() {
                        closeRegenerateModal($modal);
                        reenableButton($btn, originalBtnText);
                        $btn.trigger('click');
                    });
                    reenableButton($btn, originalBtnText);
                }
            } else {
                var errorData = response && response.data ? response.data : {};
                var errorMessage = errorData.message || errorData.error || 'Failed to regenerate alt text';
                var errorMessageLower = errorMessage.toLowerCase();
                
                // Check for trial exhausted first
                var isTrialExhausted = (typeof window.bbaiIsTrialExhausted === 'function' && window.bbaiIsTrialExhausted(errorData)) ||
                                       errorData.code === 'bbai_trial_exhausted';

                if (isTrialExhausted) {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);
                    if (typeof window.bbaiHandleTrialExhausted === 'function') {
                        window.bbaiHandleTrialExhausted(errorData);
                    }
                    return;
                }

                // Check for quota exceeded errors (limit_reached or quota-related messages)
                var isQuotaError = errorData.code === 'limit_reached' ||
                                  errorMessageLower.includes('quota exceeded') ||
                                  errorMessageLower.includes('quota exhausted') ||
                                  errorMessageLower.includes('limit reached') ||
                                  errorMessageLower.includes('monthly limit');

                if (isQuotaError) {
                    // Reuse shared quota flow when available.
                    if (typeof window.bbaiHandleLimitReached === 'function') {
                        closeRegenerateModal($modal);
                        reenableButton($btn, originalBtnText);
                        setTimeout(function() {
                            window.bbaiHandleLimitReached(errorData);
                        }, 120);
                        return;
                    }

                    // Show error message in modal first
                    var quotaMessage = errorMessage || 'Your monthly quota has been exceeded. Upgrade to continue generating alt text.';
                    showModalError($modal, quotaMessage);
                    
                    // Disable accept button since quota is exceeded
                    $modal.find('.bbai-regenerate-modal__btn--accept').prop('disabled', true);
                    
                    // Wait 2.5 seconds for user to read the message, then close modal and show upgrade popup
                    setTimeout(function() {
                        closeRegenerateModal($modal);
                        reenableButton($btn, originalBtnText);
                        
                        // Small delay to ensure regenerate modal is fully closed before showing upgrade modal
                        setTimeout(function() {
                            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Showing upgrade modal after quota exceeded');
                            
                            // Function to show modal with retry logic
                            function tryShowUpgradeModal(retries) {
                                retries = retries || 0;
                                var maxRetries = 5;
                                
                                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Attempting to show upgrade modal (attempt ' + (retries + 1) + ')');
                                
                                // Method 1: Directly manipulate DOM element (most reliable - same as dashboard.js)
                                var upgradeModal = document.getElementById('bbai-upgrade-modal');
                                
                                // Also try finding by class if ID doesn't exist
                                if (!upgradeModal) {
                                    var modals = document.querySelectorAll('.bbai-modal-backdrop, .bbai-upgrade-modal');
                                    if (modals.length > 0) {
                                        upgradeModal = modals[0];
                                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found modal by class:', upgradeModal);
                                        if (!upgradeModal.id) {
                                            upgradeModal.id = 'bbai-upgrade-modal';
                                        }
                                    }
                                }
                                
                                if (upgradeModal) {
                                    window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found upgrade modal element, showing directly...');
                                    upgradeModal.removeAttribute('style');
                                    upgradeModal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important;';
                                    upgradeModal.setAttribute('aria-hidden', 'false');
                                    upgradeModal.classList.add('active');
                                    document.body.style.overflow = 'hidden';

                                    // Also ensure modal content is visible (CSS has opacity: 0 by default)
                                    var modalContent = upgradeModal.querySelector('.bbai-upgrade-modal__content');
                                    if (modalContent) {
                                        modalContent.style.cssText = 'opacity: 1 !important; transform: translateY(0) scale(1) !important;';
                                    }

                                    // Verify it's actually visible
                                    setTimeout(function() {
                                        var computedStyle = window.getComputedStyle(upgradeModal);
                                        if (computedStyle.display === 'flex' || computedStyle.display === 'block') {
                                            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Upgrade modal shown successfully');
                                        } else {
                                            window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Modal style applied but still not visible. Display:', computedStyle.display);
                                            if (retries < maxRetries) {
                                                setTimeout(function() { tryShowUpgradeModal(retries + 1); }, 200);
                                            }
                                        }
                                    }, 100);
                                    return;
                                }
                    
                    // Method 2: Try finding modal by class name
                    var modalByClass = document.querySelector('.bbai-modal-backdrop');
                    if (modalByClass && !modalByClass.id) {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found modal by class, assigning ID and showing...');
                        modalByClass.id = 'bbai-upgrade-modal';
                        modalByClass.removeAttribute('style');
                        modalByClass.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important;';
                        modalByClass.classList.add('active');
                        modalByClass.setAttribute('aria-hidden', 'false');
                        document.body.style.overflow = 'hidden';

                        // Also ensure modal content is visible
                        var modalContent2 = modalByClass.querySelector('.bbai-upgrade-modal__content');
                        if (modalContent2) {
                            modalContent2.style.cssText = 'opacity: 1 !important; transform: translateY(0) scale(1) !important;';
                        }
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Upgrade modal shown successfully (by class)');
                        return;
                    }
                    
                    // Method 3: Try React pricing modal
                    if (typeof window.openPricingModal === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Using React openPricingModal...');
                        try {
                            window.openPricingModal('enterprise');
                            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] React modal opened');
                            return;
                        } catch (e) {
                            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error calling openPricingModal:', e);
                        }
                    }
                    
                    // Method 4: Use jQuery to find and click upgrade button
                    var $upgradeBtn = $('[data-action="show-upgrade-modal"]');
                    if ($upgradeBtn.length > 0) {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found upgrade button, clicking...');
                        $upgradeBtn.first().trigger('click');
                        return;
                    }
                    
                    // Method 5: Try window.alttextaiShowModal
                    if (typeof window.alttextaiShowModal === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Using alttextaiShowModal...');
                        try {
                            window.alttextaiShowModal();
                            return;
                        } catch (e) {
                            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error calling alttextaiShowModal:', e);
                        }
                    }
                    
                    // Method 6: Try window.bbaiHandleLimitReached
                    if (typeof window.bbaiHandleLimitReached === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Using bbaiHandleLimitReached...');
                        try {
                            window.bbaiHandleLimitReached(errorData);
                            return;
                        } catch (e) {
                            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error calling bbaiHandleLimitReached:', e);
                        }
                    }
                    
                    // Method 7: Try triggering jQuery event
                    window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Triggering jQuery event as last resort...');
                    $(document).trigger('alttextai:show-upgrade-modal', [errorData.usage || null]);
                    
                                // Final fallback: Redirect to settings page or show alert
                                setTimeout(function() {
                                    var upgradeModalCheck = document.getElementById('bbai-upgrade-modal');
                                    var stillNoModal = !upgradeModalCheck || 
                                                      (upgradeModalCheck && window.getComputedStyle(upgradeModalCheck).display === 'none');
                                    if (stillNoModal) {
                                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Could not show upgrade modal - no method worked');
                                        // Try redirecting to settings page with upgrade section
                                        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                                            window.bbaiModal.show({
                                                type: 'warning',
                                                title: 'Monthly quota exceeded',
                                                message: 'Your monthly quota has been exceeded. Upgrade to continue generating alt text.',
                                                buttons: [
                                                    {
                                                        text: 'Upgrade now',
                                                        primary: true,
                                                        action: function() {
                                                            window.bbaiModal.close();
                                                            var settingsUrl = window.location.href.split('?')[0] + '?page=bbai&tab=settings';
                                                            window.location.href = settingsUrl;
                                                        }
                                                    },
                                                    {
                                                        text: 'Maybe later',
                                                        primary: false,
                                                        action: function() {
                                                            window.bbaiModal.close();
                                                        }
                                                    }
                                                ]
                                            });
                                        } else {
                                            var settingsUrl = window.location.href.split('?')[0] + '?page=bbai&tab=settings';
                                            window.location.href = settingsUrl;
                                        }
                                    }
                                }, 1000);
                            }
                            
                            // Start trying to show the modal
                            tryShowUpgradeModal(0);
                        }, 100); // Small delay after closing regenerate modal
                    }, 2500); // 2.5 second delay for user to read quota message
                    
                } else if (errorData.code === 'auth_required' || (errorMessageLower.includes('authentication required'))) {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);

                    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                        window.authModal.show();
                        window.authModal.showLoginForm();
                    } else if (typeof showAuthModal === 'function') {
                        showAuthModal('login');
                    } else if (typeof showAuthLogin === 'function') {
                        showAuthLogin();
                    } else {
                        showModalError($modal, 'Please log in to regenerate alt text.');
                    }
                } else {
                    var retryAfter = parseInt(errorData.retry_after || (errorData.data && errorData.data.retry_after), 10);
                    var canRetry = !!errorData.retryable || isRetryableError(errorData.code);
                    showModalError(
                        $modal,
                        errorMessage,
                        canRetry ? function() {
                            closeRegenerateModal($modal);
                            reenableButton($btn, originalBtnText);
                            $btn.trigger('click');
                        } : null,
                        isNaN(retryAfter) ? 0 : retryAfter
                    );
                    reenableButton($btn, originalBtnText);
                }
            }
        })
        .fail(function(xhr, status, error) {
            if ($modal.data('bbai-request-key') !== requestKey) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Ignoring stale regenerate failure response');
                return;
            }

            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to regenerate:', error, xhr);

            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');

            var errorData = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
            var errorMessage = errorData.message || errorData.error || 'Failed to regenerate alt text. Please try again.';
            var errorMessageLower = errorMessage.toLowerCase();
            
            // Check for trial exhausted first
            var isTrialExhaustedFail = (typeof window.bbaiIsTrialExhausted === 'function' && window.bbaiIsTrialExhausted(errorData)) ||
                                       errorData.code === 'bbai_trial_exhausted';

            if (isTrialExhaustedFail) {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
                if (typeof window.bbaiHandleTrialExhausted === 'function') {
                    window.bbaiHandleTrialExhausted(errorData);
                }
            } else
            // Check for quota exceeded errors
            if (errorData.code === 'limit_reached' ||
                              errorMessageLower.includes('quota exceeded') ||
                              errorMessageLower.includes('quota exhausted') ||
                              errorMessageLower.includes('limit reached') ||
                              errorMessageLower.includes('monthly limit')) {
                // Reuse shared quota flow when available.
                if (typeof window.bbaiHandleLimitReached === 'function') {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);
                    setTimeout(function() {
                        window.bbaiHandleLimitReached(errorData);
                    }, 120);
                    return;
                }

                // Show error message in modal first
                var quotaMessage = errorMessage || 'Your monthly quota has been exceeded. Upgrade to continue generating alt text.';
                showModalError($modal, quotaMessage);

                // Disable accept button since quota is exceeded
                $modal.find('.bbai-regenerate-modal__btn--accept').prop('disabled', true);

                // Wait 2.5 seconds for user to read the message, then close modal and show upgrade popup
                setTimeout(function() {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);

                    // Small delay to ensure regenerate modal is fully closed before showing upgrade modal
                    setTimeout(function() {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Showing upgrade modal after quota exceeded (fail handler)');

                        // Function to show modal with retry logic (same as success handler)
                        function tryShowUpgradeModalFail(retries) {
                            retries = retries || 0;
                            var maxRetries = 5;
                            
                            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Attempting to show upgrade modal (fail handler, attempt ' + (retries + 1) + ')');
                            
                            // Method 1: Directly manipulate DOM element (most reliable - same as dashboard.js)
                            var upgradeModal = document.getElementById('bbai-upgrade-modal');
                            
                            // Also try finding by class if ID doesn't exist
                            if (!upgradeModal) {
                                var modals = document.querySelectorAll('.bbai-modal-backdrop, .bbai-upgrade-modal');
                                if (modals.length > 0) {
                                    upgradeModal = modals[0];
                                    window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found modal by class:', upgradeModal);
                                    if (!upgradeModal.id) {
                                        upgradeModal.id = 'bbai-upgrade-modal';
                                    }
                                }
                            }
                            
                            if (upgradeModal) {
                                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found upgrade modal element, showing directly...');
                                upgradeModal.removeAttribute('style');
                                upgradeModal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important;';
                                upgradeModal.setAttribute('aria-hidden', 'false');
                                upgradeModal.classList.add('active');
                                document.body.style.overflow = 'hidden';
                                
                                // Verify it's actually visible
                                setTimeout(function() {
                                    var computedStyle = window.getComputedStyle(upgradeModal);
                                    if (computedStyle.display === 'flex' || computedStyle.display === 'block') {
                                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Upgrade modal shown successfully');
                                    } else {
                                        window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Modal style applied but still not visible. Display:', computedStyle.display);
                                        if (retries < maxRetries) {
                                            setTimeout(function() { tryShowUpgradeModalFail(retries + 1); }, 200);
                                        }
                                    }
                                }, 100);
                                return;
                            }
                            
                            // If modal not found and we have retries left, try again
                            if (retries < maxRetries) {
                                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Modal not found yet, retrying in 200ms...');
                                setTimeout(function() { tryShowUpgradeModalFail(retries + 1); }, 200);
                                return;
                            }
                            
                            // All retries exhausted, try other methods
                            window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Upgrade modal element not found after ' + (retries + 1) + ' attempts');
                            
                            // Method 2: Try React pricing modal
                    
                    // Method 2: Try finding modal by class name
                    var modalByClass = document.querySelector('.bbai-modal-backdrop');
                    if (modalByClass && !modalByClass.id) {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found modal by class, assigning ID and showing...');
                        modalByClass.id = 'bbai-upgrade-modal';
                        modalByClass.removeAttribute('style');
                        modalByClass.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important;';
                        modalByClass.classList.add('active');
                        modalByClass.setAttribute('aria-hidden', 'false');
                        document.body.style.overflow = 'hidden';

                        // Also ensure modal content is visible
                        var modalContent2 = modalByClass.querySelector('.bbai-upgrade-modal__content');
                        if (modalContent2) {
                            modalContent2.style.cssText = 'opacity: 1 !important; transform: translateY(0) scale(1) !important;';
                        }
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Upgrade modal shown successfully (by class)');
                        return;
                    }
                    
                    // Method 3: Try React pricing modal
                    if (typeof window.openPricingModal === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Using React openPricingModal...');
                        try {
                            window.openPricingModal('enterprise');
                            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] React modal opened');
                            return;
                        } catch (e) {
                            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error calling openPricingModal:', e);
                        }
                    }
                    
                    // Method 4: Use jQuery to find and click upgrade button
                    var $upgradeBtn = $('[data-action="show-upgrade-modal"]');
                    if ($upgradeBtn.length > 0) {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found upgrade button, clicking...');
                        $upgradeBtn.first().trigger('click');
                        return;
                    }
                    
                    // Method 5: Try window.alttextaiShowModal
                    if (typeof window.alttextaiShowModal === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Using alttextaiShowModal...');
                        try {
                            window.alttextaiShowModal();
                            return;
                        } catch (e) {
                            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error calling alttextaiShowModal:', e);
                        }
                    }
                    
                    // Method 6: Try window.bbaiHandleLimitReached
                    if (typeof window.bbaiHandleLimitReached === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Using bbaiHandleLimitReached...');
                        try {
                            window.bbaiHandleLimitReached(errorData);
                            return;
                        } catch (e) {
                            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error calling bbaiHandleLimitReached:', e);
                        }
                    }
                    
                    // Method 7: Try triggering jQuery event
                    window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Triggering jQuery event as last resort...');
                    $(document).trigger('alttextai:show-upgrade-modal', [errorData.usage || null]);
                    
                            // Final fallback: Redirect to settings page or show alert
                            setTimeout(function() {
                                var upgradeModalCheck = document.getElementById('bbai-upgrade-modal');
                                var stillNoModal = !upgradeModalCheck || 
                                                  (upgradeModalCheck && window.getComputedStyle(upgradeModalCheck).display === 'none');
                                if (stillNoModal) {
                                    window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Could not show upgrade modal - no method worked');
                                    // Try redirecting to settings page with upgrade section
                                    if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                                        window.bbaiModal.show({
                                            type: 'warning',
                                            title: 'Monthly quota exceeded',
                                            message: 'Your monthly quota has been exceeded. Upgrade to continue generating alt text.',
                                            buttons: [
                                                {
                                                    text: 'Upgrade now',
                                                    primary: true,
                                                    action: function() {
                                                        window.bbaiModal.close();
                                                        var settingsUrl = window.location.href.split('?')[0] + '?page=bbai&tab=settings';
                                                        window.location.href = settingsUrl;
                                                    }
                                                },
                                                {
                                                    text: 'Maybe later',
                                                    primary: false,
                                                    action: function() {
                                                        window.bbaiModal.close();
                                                    }
                                                }
                                            ]
                                        });
                                    } else {
                                        var settingsUrl = window.location.href.split('?')[0] + '?page=bbai&tab=settings';
                                        window.location.href = settingsUrl;
                                    }
                                }
                            }, 1000);
                        }
                        
                        // Start trying to show the modal
                        tryShowUpgradeModalFail(0);
                    }, 100); // Small delay after closing regenerate modal
                }, 2500); // 2.5 second delay for user to read quota message
                
            } else if (errorData.code === 'auth_required') {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);

                if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                }
            } else {
                var failRetryAfter = parseInt(errorData.retry_after || (errorData.data && errorData.data.retry_after), 10);
                var failCanRetry = !!errorData.retryable || isRetryableError(errorData.code);
                showModalError(
                    $modal,
                    errorMessage,
                    failCanRetry ? function() {
                        closeRegenerateModal($modal);
                        reenableButton($btn, originalBtnText);
                        $btn.trigger('click');
                    } : null,
                    isNaN(failRetryAfter) ? 0 : failRetryAfter
                );
                reenableButton($btn, originalBtnText);
            }
        });

        $modal.find('.bbai-regenerate-modal__btn--cancel')
            .off('click')
            .on('click', function() {
                $modal.removeData('bbai-request-key');
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
            });
    }

    /**
     * Create the regenerate modal HTML
     */
    function createRegenerateModal() {
        var modalHtml =
            '<div id="bbai-regenerate-modal" class="bbai-regenerate-modal">' +
            '    <div class="bbai-regenerate-modal__content">' +
            '        <div class="bbai-regenerate-modal__header">' +
            '            <h2 class="bbai-regenerate-modal__title">Regenerate Alt Text</h2>' +
            '            <p class="bbai-regenerate-modal__subtitle">Review the new alt text before applying</p>' +
            '        </div>' +
            '        <div class="bbai-regenerate-modal__body">' +
            '            <div class="bbai-regenerate-modal__image-preview">' +
            '                <img src="" alt="" class="bbai-regenerate-modal__thumbnail">' +
            '                <div class="bbai-regenerate-modal__image-info">' +
            '                    <p class="bbai-regenerate-modal__image-title"></p>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-regenerate-modal__error"></div>' +
            '            <div class="bbai-regenerate-modal__loading">' +
            '                <div class="bbai-regenerate-modal__spinner"></div>' +
            '                <p class="bbai-regenerate-modal__loading-text">Generating new alt text...</p>' +
            '            </div>' +
            '            <div class="bbai-regenerate-modal__result">' +
            '                <p class="bbai-regenerate-modal__alt-text-label">New Alt Text:</p>' +
            '                <p class="bbai-regenerate-modal__alt-text"></p>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-regenerate-modal__footer">' +
            '            <button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--cancel">Cancel</button>' +
            '            <button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--accept" disabled>Accept & Apply</button>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        return $('#bbai-regenerate-modal');
    }

    /**
     * Show error in modal
     */
    function showModalError($modal, message, retryCallback, retryAfterSeconds) {
        var $errorDiv = $modal.find('.bbai-regenerate-modal__error');
        var hasRetry = typeof retryCallback === 'function';
        var isQuotaError = message.toLowerCase().includes('quota') || 
                          message.toLowerCase().includes('limit reached') ||
                          message.toLowerCase().includes('limit exceeded');
        
        // Add error icon and better styling for quota errors
        var errorHtml = '<div style="display: flex; align-items: center; gap: 12px;">' +
            '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="flex-shrink: 0;">' +
            '<circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>' +
            '<path d="M10 6V10M10 14H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
            '</svg>' +
            '<span style="flex: 1;">' + $('<div>').text(message).html() + '</span>' +
            (hasRetry ? '<button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--retry" data-action="retry-regenerate">Retry</button>' : '') +
            '</div>';
        
        $errorDiv.html(errorHtml).addClass('active');

        if (hasRetry) {
            var $retryButton = $errorDiv.find('[data-action="retry-regenerate"]');
            var retryDelay = Math.max(0, parseInt(retryAfterSeconds, 10) || 0);

            if (retryDelay > 0) {
                $retryButton.prop('disabled', true).text('Retry in ' + retryDelay + 's');
                var retryTimer = window.setInterval(function() {
                    retryDelay -= 1;
                    if (retryDelay <= 0) {
                        window.clearInterval(retryTimer);
                        $retryButton.prop('disabled', false).text('Retry');
                        return;
                    }
                    $retryButton.text('Retry in ' + retryDelay + 's');
                }, 1000);
            }

            $retryButton.off('click').on('click', function() {
                if ($retryButton.prop('disabled')) {
                    return;
                }
                retryCallback();
            });
        }
        
        // Add special class for quota errors for enhanced styling
        if (isQuotaError) {
            $errorDiv.addClass('bbai-regenerate-modal__error--quota');
        } else {
            $errorDiv.removeClass('bbai-regenerate-modal__error--quota');
        }
        
        // Hide loading and result sections
        $modal.find('.bbai-regenerate-modal__loading').removeClass('active');
        $modal.find('.bbai-regenerate-modal__result').removeClass('active');
    }

    function isRetryableError(errorCode) {
        var retryableErrors = [
            'api_timeout',
            'api_unreachable',
            'network_error',
            'server_error',
            'quota_check_mismatch',
            'missing_alt_text',
            'api_response_invalid'
        ];
        return retryableErrors.indexOf(errorCode) !== -1;
    }

    /**
     * Close regenerate modal
     */
    function closeRegenerateModal($modal) {
        $modal.removeData('bbai-request-key');
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Re-enable the regenerate button and restore cell content if cancelled
     */
    function reenableButton($btn, originalText) {
        $btn.prop('disabled', false).removeClass('regenerating');
        $btn.text(originalText);

        // Restore original cell content if it was saved (user cancelled)
        var $row = $btn.closest('tr');
        var $altCell = $row.find('.bbai-library-cell--alt-text');
        var originalContent = $altCell.data('original-content');
        if (originalContent && $altCell.find('.bbai-skeleton').length) {
            $altCell.html(originalContent);
            $altCell.removeData('original-content');
        }
    }

    /**
     * Calculate SEO quality score for alt text
     */
    function calculateSeoQuality(text) {
        if (!text || text.trim() === '') {
            return { score: 0, grade: 'F', badge: 'missing' };
        }

        var score = 100;
        var textLength = text.length;

        if (textLength > 125) {
            score -= 25;
        }

        var lowerText = text.toLowerCase().trim();
        var redundantPrefixes = ['image of', 'picture of', 'photo of', 'photograph of', 'graphic of', 'illustration of'];
        for (var i = 0; i < redundantPrefixes.length; i++) {
            if (lowerText.indexOf(redundantPrefixes[i]) === 0) {
                score -= 20;
                break;
            }
        }

        if (/^IMG[-_]\d+/i.test(text) || /^DSC[-_]\d+/i.test(text) || /\.(jpg|jpeg|png|gif|webp)$/i.test(text)) {
            score -= 30;
        }

        var words = text.trim().split(/\s+/);
        if (words.length < 3) {
            score -= 15;
        }

        // Placeholder / non-descriptive check
        var nondescriptiveWords = ['test', 'testing', 'tested', 'tests', 'asdf', 'qwerty', 'placeholder', 'sample', 'example', 'demo', 'foo', 'bar', 'abc', 'xyz', 'temp', 'tmp', 'crap', 'stuff', 'thing', 'things', 'something', 'anything', 'whatever', 'blah', 'meh', 'idk', 'nada', 'random', 'garbage', 'junk', 'dummy', 'fake', 'lorem', 'ipsum'];
        var lowerWords = words.map(function(w) { return w.toLowerCase(); });
        var badCount = 0;
        for (var i = 0; i < nondescriptiveWords.length; i++) {
            if (lowerWords.indexOf(nondescriptiveWords[i]) !== -1) badCount++;
        }
        if (badCount >= 1 && (badCount >= 2 || words.length <= 4)) {
            score -= 50;
        }

        score = Math.max(0, score);

        var grade, badge;
        if (score >= 90) { grade = 'A'; badge = 'excellent'; }
        else if (score >= 75) { grade = 'B'; badge = 'good'; }
        else if (score >= 60) { grade = 'C'; badge = 'fair'; }
        else if (score >= 40) { grade = 'D'; badge = 'poor'; }
        else { grade = 'F'; badge = 'needs-work'; }

        return { score: score, grade: grade, badge: badge };
    }

    /**
     * Accept regenerated alt text and update the UI
     */
    function acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal) {
        var $row = $btn.closest('tr');

        if (newAltText) {
            var $altCell = $row.find('.bbai-library-cell--alt-text');

            if ($altCell.length) {
                var safeAlt = $('<div>').text(newAltText).html();
                var truncated = newAltText.length > 80 ? newAltText.substring(0, 77) + '…' : newAltText;
                var safeTruncated = $('<div>').text(truncated).html();
                var charCount = newAltText.length;
                var isOptimal = charCount <= 125;
                var counterClass = isOptimal ? 'bbai-char-counter--optimal' : 'bbai-char-counter--warning';
                var counterTooltip = isOptimal ? 'Optimal length for Google Images SEO' : 'Consider shortening to 125 chars or less';

                var seoQuality = calculateSeoQuality(newAltText);
                var seoBadgeHtml = '';
                if (seoQuality.badge !== 'missing') {
                    seoBadgeHtml = '<span class="bbai-meta-separator">○</span>' +
                        '<span class="bbai-seo-badge bbai-seo-badge--' + seoQuality.badge + '" data-bbai-tooltip="SEO Score: ' + seoQuality.grade + ' (' + seoQuality.score + '/100)" data-bbai-tooltip-position="top">SEO: ' + seoQuality.grade + '</span>' +
                        '<span class="bbai-meta-separator">○</span>';
                }

                var cellHtml =
                    '<div class="bbai-alt-text-content">' +
                        '<div class="bbai-alt-text-preview" title="' + safeAlt + '">' + safeTruncated + '</div>' +
                        '<div class="bbai-alt-text-meta">' +
                            '<span class="' + counterClass + '" data-bbai-tooltip="' + counterTooltip + '" data-bbai-tooltip-position="top">' + charCount + '/125</span>' +
                            seoBadgeHtml +
                        '</div>' +
                    '</div>';

                $altCell.html(cellHtml);

                var $statusCell = $row.find('.bbai-library-cell--status span');
                if ($statusCell.length) {
                    $statusCell
                        .removeClass()
                        .addClass('bbai-status-badge bbai-status-badge--regenerated')
                        .text('Regenerated');
                }

                $row.attr('data-status', 'regenerated');
            }
        }

        closeRegenerateModal($modal);
        reenableButton($btn, originalBtnText);

        if (typeof showNotification === 'function') {
            showNotification('Alt text updated successfully!', 'success');
        }

        if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
        }
    }

})(jQuery);
