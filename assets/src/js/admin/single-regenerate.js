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

        console.log('[AI Alt Text] Regenerate button clicked');
        var $btn = $(this);
        var attachmentId = $btn.data('attachment-id');

        console.log('[AI Alt Text] Attachment ID:', attachmentId);

        if (!attachmentId || $btn.prop('disabled')) {
            console.warn('[AI Alt Text] Cannot regenerate - missing ID or button disabled');
            return false;
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).addClass('regenerating');
        $btn.text('Processing...');

        var $row = $btn.closest('tr');
        var imageTitle = $row.find('.bbai-table__cell--title').text().trim() || 'Image';
        var imageSrc = $row.find('img').attr('src') || '';

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

        var ajaxUrl = (window.bbai_ajax && window.bbai_ajax.ajaxurl) ||
                     (window.BBAI && window.BBAI.restRoot ? window.BBAI.restRoot.replace(/\/$/, '') + '/admin-ajax.php' : null) ||
                     (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) ||
                       (window.BBAI && window.BBAI.nonce) ||
                       '';

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'beepbeepai_regenerate_single',
                attachment_id: attachmentId,
                nonce: nonceValue
            }
        })
        .done(function(response) {
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
                    showModalError($modal, 'No alt text was generated. Please try again.');
                    reenableButton($btn, originalBtnText);
                }
            } else {
                var errorData = response && response.data ? response.data : {};
                if (errorData.code === 'limit_reached') {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);
                    window.bbaiHandleLimitReached(errorData);
                } else if (errorData.code === 'auth_required' || (errorData.message && errorData.message.toLowerCase().includes('authentication required'))) {
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
                    var message = errorData.message || 'Failed to regenerate alt text';
                    showModalError($modal, message);
                    reenableButton($btn, originalBtnText);
                }
            }
        })
        .fail(function(xhr, status, error) {
            console.error('[AI Alt Text] Failed to regenerate:', error, xhr);

            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');

            var errorData = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
            if (errorData.code === 'limit_reached') {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
                window.bbaiHandleLimitReached(errorData);
            } else if (errorData.code === 'auth_required') {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);

                if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                }
            } else {
                var message = errorData.message || 'Failed to regenerate alt text. Please try again.';
                showModalError($modal, message);
                reenableButton($btn, originalBtnText);
            }
        });

        $modal.find('.bbai-regenerate-modal__btn--cancel')
            .off('click')
            .on('click', function() {
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
    function showModalError($modal, message) {
        $modal.find('.bbai-regenerate-modal__error').text(message).addClass('active');
    }

    /**
     * Close regenerate modal
     */
    function closeRegenerateModal($modal) {
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Re-enable the regenerate button
     */
    function reenableButton($btn, originalText) {
        $btn.prop('disabled', false).removeClass('regenerating');
        $btn.text(originalText);
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
