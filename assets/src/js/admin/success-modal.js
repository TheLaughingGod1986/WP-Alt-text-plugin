/**
 * Success Modal
 * Displays success information after bulk operations complete
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    var escapeHtml = window.bbaiEscapeHtml || function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Create and show success modal
     */
    function createSuccessModal() {
        var adminBaseUrl = (window.bbai_ajax && window.bbai_ajax.admin_url) || '';
        var libraryUrl = adminBaseUrl ? (adminBaseUrl + '?page=bbai&tab=library') : '#';
        var modalHtml =
            '<div id="bbai-modal-success" class="bbai-modal-success" role="dialog" aria-modal="true" aria-labelledby="bbai-modal-success-title">' +
            '    <div class="bbai-modal-success__overlay"></div>' +
            '    <div class="bbai-modal-success__content">' +
            '        <button type="button" class="bbai-modal-success__close" aria-label="' + escapeHtml('Close') + '">×</button>' +
            '        <div class="bbai-modal-success__header">' +
            '            <div class="bbai-modal-success__badge">' +
            '                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
            '                    <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
            '                </svg>' +
            '            </div>' +
            '            <h2 id="bbai-modal-success-title" class="bbai-modal-success__title">Alt Text Generated Successfully</h2>' +
            '            <p class="bbai-modal-success__subtitle">Your images have been processed and are ready to review.</p>' +
            '        </div>' +
            '        <div class="bbai-modal-success__stats">' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="processed">0</div>' +
            '                <div class="bbai-modal-success__stat-label">Images Processed</div>' +
            '            </div>' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="time">0</div>' +
            '                <div class="bbai-modal-success__stat-label">Time Saved</div>' +
            '            </div>' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="confidence">0%</div>' +
            '                <div class="bbai-modal-success__stat-label">AI Confidence</div>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-modal-success__summary" data-summary-type="success">' +
            '            <div class="bbai-modal-success__summary-icon">✓</div>' +
            '            <div class="bbai-modal-success__summary-text">All images were processed successfully.</div>' +
            '        </div>' +
            '        <div class="bbai-modal-success__actions">' +
            '            <a href="' + escapeHtml(libraryUrl) + '" class="bbai-modal-success__btn bbai-modal-success__btn--primary">View ALT Library →</a>' +
            '            <button type="button" class="bbai-modal-success__btn bbai-modal-success__btn--secondary" data-action="view-warnings" style="display: none;">View Warnings</button>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        var $existing = $('#bbai-modal-success');
        if ($existing.length) {
            $existing.remove();
        }

        $('body').append(modalHtml);
        var $modal = $('#bbai-modal-success');

        $modal.find('.bbai-modal-success__close, .bbai-modal-success__overlay').on('click', function() {
            hideSuccessModal();
        });

        $modal.find('[data-action="view-warnings"]').on('click', function() {
            hideSuccessModal();
            if (libraryUrl && libraryUrl !== '#') {
                window.location.href = libraryUrl;
            }
        });

        $(document).on('keydown.bbai-success-modal', function(e) {
            if (e.keyCode === 27 && $modal.hasClass('active')) {
                hideSuccessModal();
            }
        });

        return $modal;
    }

    /**
     * Show success modal with stats
     */
    window.showSuccessModal = function(data) {
        var $modal = $('#bbai-modal-success');
        if (!$modal.length) {
            $modal = createSuccessModal();
        }

        $modal.find('[data-stat="processed"]').text(data.processed || 0);
        $modal.find('[data-stat="time"]').text(data.timeSaved || '0 hours');
        $modal.find('[data-stat="confidence"]').text((data.confidence || 0) + '%');

        var $summary = $modal.find('.bbai-modal-success__summary');
        var $warningsBtn = $modal.find('[data-action="view-warnings"]');

        if (data.failures > 0) {
            $summary.attr('data-summary-type', 'warning');
            $summary.find('.bbai-modal-success__summary-icon').text('⚠');
            $summary.find('.bbai-modal-success__summary-text').text('Some images generated with warnings — review details below.');
            $warningsBtn.show();
        } else {
            $summary.attr('data-summary-type', 'success');
            $summary.find('.bbai-modal-success__summary-icon').text('✓');
            $summary.find('.bbai-modal-success__summary-text').text('All images were processed successfully.');
            $warningsBtn.hide();
        }

        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        setTimeout(function() {
            $modal.find('.bbai-modal-success__close').focus();
        }, 100);
    };

    /**
     * Hide success modal
     */
    window.hideSuccessModal = function() {
        var $modal = $('#bbai-modal-success');
        if ($modal.length) {
            $modal.removeClass('active');
            $('body').css('overflow', '');
            $(document).off('keydown.bbai-success-modal');
        }
    };

})(jQuery);
