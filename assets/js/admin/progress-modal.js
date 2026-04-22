/**
 * Progress Modal
 * Bulk progress modal for tracking generation status
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    /**
     * Show bulk progress modal with detailed tracking
     */
    window.showBulkProgress = function(label, total, current) {
        var $modal = $('#bbai-bulk-progress-modal');

        if (!$modal.length) {
            $modal = createBulkProgressModal();
        }

        $modal.data('startTime', Date.now());
        $modal.data('total', total);
        $modal.data('current', current || 0);

        $modal.find('.bbai-bulk-progress__title').text(label || 'Processing Images...');
        $modal.find('.bbai-bulk-progress__total').text(total);
        $modal.find('.bbai-bulk-progress__log').empty();

        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        updateBulkProgress(current || 0, total);
    };

    /**
     * Create bulk progress modal HTML
     */
    function createBulkProgressModal() {
        var modalHtml =
            '<div id="bbai-bulk-progress-modal" class="bbai-bulk-progress-modal">' +
            '    <div class="bbai-bulk-progress-modal__overlay"></div>' +
            '    <div class="bbai-bulk-progress-modal__content">' +
            '        <div class="bbai-bulk-progress__header">' +
            '            <div class="bbai-bulk-progress__header-text">' +
            '                <h2 class="bbai-bulk-progress__title">Processing Images...</h2>' +
            '                <p class="bbai-bulk-progress__helper" hidden></p>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__header-actions">' +
            '                <button type="button" class="bbai-bulk-progress__close" aria-label="Close">&times;</button>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-bulk-progress__body">' +
            '            <div class="bbai-bulk-progress__stats">' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">Progress</span>' +
            '                    <span class="bbai-bulk-progress__stat-value">' +
            '                        <span class="bbai-bulk-progress__current">0</span> / ' +
            '                        <span class="bbai-bulk-progress__total">0</span>' +
            '                    </span>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">Percentage</span>' +
            '                    <span class="bbai-bulk-progress__stat-value bbai-bulk-progress__percentage">0%</span>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">Estimated Time</span>' +
            '                    <span class="bbai-bulk-progress__stat-value bbai-bulk-progress__eta">Calculating...</span>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__bar-container">' +
            '                <div class="bbai-bulk-progress__bar">' +
            '                    <div class="bbai-bulk-progress__bar-fill" style="width: 0%"></div>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__log-container">' +
            '                <h3 class="bbai-bulk-progress__log-title">Processing Log</h3>' +
            '                <div class="bbai-bulk-progress__log"></div>' +
            '            </div>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        var $modal = $('#bbai-bulk-progress-modal');

        // Close hides the modal without cancelling the job.
        $modal.find('.bbai-bulk-progress__close').on('click', function() {
            minimizeBulkProgress();
        });

        // Clicking overlay also minimizes (not cancel)
        $modal.find('.bbai-bulk-progress-modal__overlay').on('click', function () {
            minimizeBulkProgress();
        });

        return $modal;
    }

    /**
     * Update bulk progress bar with detailed stats
     */
    window.updateBulkProgress = function(current, total, imageTitle) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        var startTime = $modal.data('startTime') || Date.now();
        var elapsed = (Date.now() - startTime) / 1000;

        var eta = 'Waiting for first image…';
        if (current > 0 && elapsed > 0) {
            var avgTimePerImage = elapsed / current;
            var remaining = total - current;
            var etaSeconds = remaining * avgTimePerImage;

            if (etaSeconds < 60) {
                eta = Math.ceil(etaSeconds) + 's';
            } else if (etaSeconds < 3600) {
                eta = Math.ceil(etaSeconds / 60) + 'm';
            } else {
                var hours = Math.floor(etaSeconds / 3600);
                var mins = Math.ceil((etaSeconds % 3600) / 60);
                eta = hours + 'h ' + mins + 'm';
            }
        }

        $modal.find('.bbai-bulk-progress__current').text(current);
        $modal.find('.bbai-bulk-progress__percentage').text(percentage + '%');
        $modal.find('.bbai-bulk-progress__eta').text(eta);
        $modal.find('.bbai-bulk-progress__bar-fill').css('width', percentage + '%');

        if (imageTitle) {
            var timestamp = new Date().toLocaleTimeString();
            var escapeHtml = window.bbaiEscapeHtml || function(t) { return t; };
            var logEntry =
                '<div class="bbai-bulk-progress__log-entry">' +
                '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
                '    <span class="bbai-bulk-progress__log-icon">✓</span>' +
                '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(imageTitle) + '</span>' +
                '</div>';

            var $log = $modal.find('.bbai-bulk-progress__log');
            $log.append(logEntry);
            $log.scrollTop($log[0].scrollHeight);
        }
    };

    /**
     * Add error log entry
     */
    window.logBulkProgressError = function(errorMessage) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var timestamp = new Date().toLocaleTimeString();
        var escapeHtml = window.bbaiEscapeHtml || function(t) { return t; };
        var logEntry =
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--error">' +
            '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
            '    <span class="bbai-bulk-progress__log-icon">✗</span>' +
            '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(errorMessage || 'An error occurred') + '</span>' +
            '</div>';

        var $log = $modal.find('.bbai-bulk-progress__log');
        $log.append(logEntry);
        $log.scrollTop($log[0].scrollHeight);
    };

    /**
     * Add success log entry
     */
    window.logBulkProgressSuccess = function(successMessage) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var timestamp = new Date().toLocaleTimeString();
        var escapeHtml = window.bbaiEscapeHtml || function(t) { return t; };
        var logEntry =
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--success">' +
            '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
            '    <span class="bbai-bulk-progress__log-icon">✓</span>' +
            '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(successMessage || 'Success') + '</span>' +
            '</div>';

        var $log = $modal.find('.bbai-bulk-progress__log');
        $log.append(logEntry);
        $log.scrollTop($log[0].scrollHeight);
    };

    /**
     * Update bulk progress modal title
     */
    window.updateBulkProgressTitle = function(title) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        $modal.find('.bbai-bulk-progress__title').text(title);
    };

    /**
     * Update bulk progress modal subtitle (e.g. time expectation)
     */
    window.updateBulkProgressSubtitle = function(text) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var $sub = $modal.find('.bbai-bulk-progress__helper');
        if (!$sub.length) return;
        if (text) {
            $sub.text(text).prop('hidden', false);
        } else {
            $sub.text('').prop('hidden', true);
        }
    };

    /**
     * Show completion state inside the progress modal.
     * Replaces the live-progress body with a success summary and CTAs.
     * All messaging derives from the canonical window.bbaiGenerationResult object.
     *
     * @param {number} successes Images generated successfully.
     * @param {number} failures  Images that failed.
     * @param {number} total     Total images attempted.
     */
    window.showBulkProgressComplete = function(successes, failures, total) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        // Mark modal as completed so minimizeBulkProgress knows to reload.
        $modal.data('bbaiComplete', true);

        total = total || (successes + failures);
        var result = window.bbaiGenerationResult || {
            status:    failures > 0 ? (successes > 0 ? 'partial' : 'error') : (successes > 0 ? 'success' : 'no_changes'),
            attempted: total,
            updated:   successes,
            failed:    failures,
        };

        var adminBase = (window.bbai_ajax && window.bbai_ajax.admin_url) ||
            (window.bbai_env && window.bbai_env.admin_url) || '';
        if (adminBase) {
            adminBase = String(adminBase).replace(/admin\.php.*$/i, '');
            if (adminBase.charAt(adminBase.length - 1) !== '/') {
                adminBase += '/';
            }
        }
        var libraryUrl = adminBase ? adminBase + 'admin.php?page=bbai-library' : 'admin.php?page=bbai-library';
        var reviewResultsUrl = adminBase
            ? adminBase + 'admin.php?page=bbai-library&status=needs_review&filter=needs-review#bbai-review-filter-tabs'
            : 'admin.php?page=bbai-library&status=needs_review&filter=needs-review#bbai-review-filter-tabs';

        // ── Derive messaging from the single result object ───────────────────
        var titleText, messageText, primaryLabel, primaryUrl;

        if (result.status === 'success') {
            var n = result.updated;
            titleText    = n === 1 ? '1 image updated successfully' : n + ' images updated successfully';
            messageText  = 'Your ALT text is ready to review in the library.';
            primaryLabel = 'View results';
            primaryUrl   = reviewResultsUrl;
        } else if (result.status === 'partial') {
            titleText    = result.updated + ' of ' + result.attempted + ' images updated';
            messageText  = result.failed + ' image' + (result.failed !== 1 ? 's' : '') + ' could not be processed. Check the library for details.';
            primaryLabel = 'Review results';
            primaryUrl   = reviewResultsUrl;
        } else if (result.status === 'no_changes') {
            titleText    = 'No new images required updates';
            messageText  = 'Your library is already up to date.';
            primaryLabel = 'View library';
            primaryUrl   = libraryUrl;
        } else {
            // error
            titleText    = 'Generation completed with issues';
            messageText  = 'Some images could not be processed. Check the library for details.';
            primaryLabel = 'View library';
            primaryUrl   = libraryUrl;
        }
        // ─────────────────────────────────────────────────────────────────────

        var completionHtml =
            '<div class="bbai-bulk-progress__complete">' +
            '    <p class="bbai-bulk-progress__complete-title">' + titleText + '</p>' +
            '    <p class="bbai-bulk-progress__complete-message">' + messageText + '</p>' +
            '    <div class="bbai-bulk-progress__complete-actions">' +
            '        <a href="' + primaryUrl + '" class="button button-primary bbai-bulk-progress__complete-review">' + primaryLabel + '</a>' +
            '        <a href="' + libraryUrl + '" class="button bbai-bulk-progress__complete-library">Open library</a>' +
            '    </div>' +
            '</div>';

        $modal.find('.bbai-bulk-progress__title').text(titleText);
        $modal.find('.bbai-bulk-progress__helper').prop('hidden', true);
        $modal.find('.bbai-bulk-progress__body').html(completionHtml);

        // Navigating via a CTA reloads naturally (full page navigation).
        // No extra click handler needed — the hrefs handle it.
    };

    /**
     * Minimize or dismiss the bulk progress modal.
     *
     * During generation: hides the modal so the job continues behind the scenes
     * and the job-widget becomes visible (existing behaviour).
     *
     * After generation is complete: reloads the page so the SSR dashboard
     * renders the correct final state (ALL_CLEAR / NEEDS_REVIEW / etc.).
     * This is the only reliable way to keep the logged-in hero consistent —
     * it is server-rendered and has no in-page update path.
     */
    function minimizeBulkProgress() {
        var $modal = $('#bbai-bulk-progress-modal');
        var isComplete = $modal.length && !!$modal.data('bbaiComplete');

        if (isComplete) {
            // Generation finished — reload so the SSR dashboard shows the real state.
            window.location.reload();
            return;
        }

        // Still in progress — just hide the modal.
        if ($modal.length) {
            $modal.removeClass('active');
            $('body').css('overflow', '');
        }
        if (window.bbaiJobState) {
            window.bbaiJobState.update({ modalVisible: false });
        }
    }
    window.minimizeBulkProgress = minimizeBulkProgress;

    /**
     * Hide bulk progress bar (legacy — now delegates to minimize).
     */
    window.hideBulkProgress = function() {
        minimizeBulkProgress();
    };

})(jQuery);
