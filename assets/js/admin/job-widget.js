/**
 * Floating Job Widget
 *
 * Persistent mini progress indicator visible while the modal is minimised.
 * Reads from window.bbaiJobState.
 *
 * @package BeepBeep_AI
 * @since 5.1.0
 */
(function ($) {
    'use strict';

    var WIDGET_ID = 'bbai-job-widget';
    var OPEN_ON_LOAD_KEY = 'bbai_open_generation_progress_on_load';
    var MAX_PROCESSING_HEARTBEAT_AGE_MS = 2 * 60 * 1000;
    var $widget = null;
    var $inlineProgress = null;
    var created = false;
    var indicatorShownTracked = false;

    function emitAnalytics(eventName, payload) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: Object.assign({ event: eventName, timestamp: Date.now() }, payload || {})
            }));
        } catch (e) {}
    }

    function escapeHtml(text) {
        var fn = window.bbaiEscapeHtml || function (t) {
            var d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        };
        return fn(text);
    }

    function createWidget() {
        if (created) return;
        created = true;

        var html =
            '<div id="' + WIDGET_ID + '" class="bbai-job-widget" hidden aria-live="polite">' +
            '  <div class="bbai-job-widget__header">' +
            '    <span class="bbai-job-widget__status"><span class="bbai-job-widget__pulse" aria-hidden="true"></span><span class="bbai-job-widget__status-text">Processing\u2026</span></span>' +
            '    <button type="button" class="bbai-job-widget__close" aria-label="Dismiss">&times;</button>' +
            '  </div>' +
            '  <div class="bbai-job-widget__body">' +
            '    <div class="bbai-job-widget__counts">' +
            '      <span class="bbai-job-widget__progress-text">0 / 0</span>' +
            '      <span class="bbai-job-widget__eta"></span>' +
            '    </div>' +
            '    <div class="bbai-job-widget__bar">' +
            '      <div class="bbai-job-widget__bar-fill" style="width:0%"></div>' +
            '    </div>' +
            '  </div>' +
            '  <button type="button" class="bbai-job-widget__view">View</button>' +
            '</div>';

        $('body').append(html);
        $widget = $('#' + WIDGET_ID);

        // View → reopen modal
        $widget.on('click', '.bbai-job-widget__view', function (event) {
            var opened = false;
            var state = window.bbaiJobState && window.bbaiJobState.getState ? window.bbaiJobState.getState() : {};
            var total = Math.max(0, parseInt(state.total, 10) || 0);
            var progress = Math.max(0, parseInt(state.progress, 10) || 0);
            var status = String(state.status || 'processing');
            event.preventDefault();
            event.stopPropagation();
            emitAnalytics('generation_modal_reopened', {
                source: 'global_job_indicator'
            });
            if (typeof window.bbaiOpenBulkProgressFromBackground === 'function') {
                opened = !!window.bbaiOpenBulkProgressFromBackground();
            }
            if (opened) {
                return;
            }

            if (typeof window.showBulkProgress === 'function') {
                window.showBulkProgress(
                    state && state.label ? state.label : 'Generation continues in background',
                    total,
                    progress
                );
                if (typeof window.updateBulkProgress === 'function') {
                    window.updateBulkProgress(progress, total);
                }
                if (
                    (status === 'complete' || status === 'partial' || status === 'error' || status === 'quota') &&
                    typeof window.showBulkProgressComplete === 'function'
                ) {
                    window.showBulkProgressComplete(
                        Math.max(0, parseInt(state.successes, 10) || 0),
                        Math.max(0, parseInt(state.failures, 10) || 0),
                        total
                    );
                }
                opened = $('#bbai-bulk-progress-modal.active').length > 0;
            }
            if (opened) {
                if (window.bbaiJobState) {
                    window.bbaiJobState.update({ modalVisible: true });
                }
                return;
            }

            var $modal = $('#bbai-bulk-progress-modal');
            if ($modal.length) {
                $modal.addClass('active');
                $('body').css('overflow', 'hidden');
                if (window.bbaiJobState) {
                    window.bbaiJobState.update({ modalVisible: true });
                }
                return;
            }

            if (window.bbaiJobWidget && window.bbaiJobWidget.dashboardUrl) {
                try {
                    window.localStorage.setItem(OPEN_ON_LOAD_KEY, '1');
                } catch (e) {}
                window.location.href = window.bbaiJobWidget.dashboardUrl;
            }
        });

        // Close → dismiss/acknowledge widget (does NOT cancel a running job)
        $widget.on('click', '.bbai-job-widget__close', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (window.bbaiJobState) {
                var state = window.bbaiJobState.getState ? window.bbaiJobState.getState() : {};
                if (state && !state.running && (state.status === 'complete' || state.status === 'partial' || state.status === 'error' || state.status === 'quota')) {
                    window.bbaiJobState.reset();
                    $widget.prop('hidden', true);
                    return;
                }
            }
            $widget.prop('hidden', true);
        });
    }

    function render(state) {
        renderInlineProgress(state);

        if (!$widget) return;

        if (
            state &&
            state.status === 'processing' &&
            state.updatedAt &&
            Date.now() - parseInt(state.updatedAt, 10) > MAX_PROCESSING_HEARTBEAT_AGE_MS
        ) {
            if (window.bbaiJobState && typeof window.bbaiJobState.reset === 'function') {
                window.bbaiJobState.reset();
            }
            $widget.prop('hidden', true);
            return;
        }

        if (state.modalVisible && !$('#bbai-bulk-progress-modal.active').length) {
            if (window.bbaiJobState) {
                window.bbaiJobState.update({ modalVisible: false });
            }
            state = Object.assign({}, state, { modalVisible: false });
        }

        syncOpenProgressModal(state);

        // Show widget when: job running but modal hidden, OR job just completed
        var shouldShow = !state.modalVisible && (
            state.status === 'processing' ||
            state.status === 'complete' ||
            state.status === 'partial' ||
            state.status === 'error' ||
            state.status === 'quota'
        );

        // Hide when idle or modal is visible during processing
        if (state.status === 'idle') {
            $widget.prop('hidden', true);
            return;
        }

        if (state.modalVisible) {
            $widget.prop('hidden', true);
            return;
        }

        $widget.prop('hidden', !shouldShow);
        if (!shouldShow) return;

        if (!indicatorShownTracked) {
            indicatorShownTracked = true;
            emitAnalytics('generation_global_indicator_shown', {
                status: state.status || 'processing',
                requested_count: state.total || 0,
                completed_count: state.progress || 0,
                failed_count: state.failures || 0
            });
        }

        // Status text
        var statusEl = $widget.find('.bbai-job-widget__status-text');
        var viewBtn = $widget.find('.bbai-job-widget__view');
        var closeBtn = $widget.find('.bbai-job-widget__close');

        if (state.status === 'complete') {
            closeBtn.prop('hidden', false);
            statusEl.text(state.successes + ' image' + (state.successes !== 1 ? 's' : '') + ' optimised');
            $widget.find('.bbai-job-widget__progress-text').text(
                'Review suggestions.'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--error')
                   .addClass('bbai-job-widget--complete');
            viewBtn.text('Review images');
        } else if (state.status === 'partial') {
            closeBtn.prop('hidden', false);
            statusEl.text(state.successes + ' of ' + state.total + ' images optimised');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.failures + ' could not be processed. Review the results for details.'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('Review results');
        } else if (state.status === 'quota') {
            closeBtn.prop('hidden', false);
            statusEl.text('Credits exhausted');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' processed, ' + state.skipped + ' skipped' +
                (state.failures > 0 ? ', ' + state.failures + ' failed' : '')
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('View');
        } else if (state.status === 'error') {
            closeBtn.prop('hidden', false);
            statusEl.text('Generation paused or failed');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' succeeded, ' + state.failures + ' failed'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('Resume status check');
        } else {
            var progress = Math.max(0, parseInt(state.progress, 10) || 0);
            var total = Math.max(0, parseInt(state.total, 10) || 0);
            var percent = total > 0 ? Math.min(100, Math.round((progress / total) * 100)) : Math.max(0, parseInt(state.percentage, 10) || 0);
            closeBtn.prop('hidden', false);
            statusEl.text('Generating ALT text…');
            $widget.find('.bbai-job-widget__progress-text').text(
                total > 0
                    ? progress + '/' + total + ' complete'
                    : 'Preparing images'
            );
            $widget.find('.bbai-job-widget__eta').text(state.eta ? 'About ' + state.eta + ' left' : (progress > 0 ? 'Calculating time left' : 'Estimating time left'));
            $widget.find('.bbai-job-widget__bar-fill').css('width', percent + '%');
            $widget.removeClass('bbai-job-widget--complete bbai-job-widget--error')
                   .addClass('bbai-job-widget--processing');
            viewBtn.text('View progress');
        }
    }

    function mountInlineProgress() {
        var $progress = $('[data-bbai-hero-generation-progress="1"]').first();
        var $visibleDailyActions = $(
            '.bbai-logged-in-dashboard--daily-pass .bbai-daily-hero-actions:visible'
        ).first();

        if (
            $progress.length &&
            $visibleDailyActions.length &&
            !$progress.closest('.bbai-daily-hero-card').length
        ) {
            $progress.detach().insertAfter($visibleDailyActions);
        }

        return $progress;
    }

    function renderInlineProgress(state) {
        if (!$inlineProgress || !$inlineProgress.length || !state) {
            return;
        }

        var status = String(state.status || 'idle');
        var visible = status === 'processing' ||
            status === 'complete' ||
            status === 'partial' ||
            status === 'error' ||
            status === 'quota';

        $inlineProgress.prop('hidden', !visible);
        if (!visible) {
            return;
        }

        var done = Math.max(0, parseInt(state.progress, 10) || 0);
        var total = Math.max(0, parseInt(state.total, 10) || 0);
        var percentage = total > 0
            ? Math.max(0, Math.min(100, Math.round((done / total) * 100)))
            : Math.max(0, Math.min(100, parseInt(state.percentage, 10) || 0));
        var label = 'Generating ALT text\u2026';
        var count = total > 0 ? done + ' / ' + total + ' complete' : 'Starting\u2026';
        var actionLabel = 'View detailed progress';
        var stateClass = 'bbai-hero-generation-progress--processing';
        var hideMeter = false;

        var monthlyRemainingAttr = document.querySelector('[data-bbai-daily-limit-note="1"]');
        var monthlyRemaining = monthlyRemainingAttr
            ? parseInt(monthlyRemainingAttr.getAttribute('data-bbai-monthly-remaining'), 10)
            : NaN;
        var monthlyExhausted = !isNaN(monthlyRemaining) && monthlyRemaining <= 0;
        var isDailyLimit = !monthlyExhausted && !!(state.isDailyLimit || window.bbaiDailyLimitActive);

        if (status === 'complete') {
            done = Math.max(done, parseInt(state.successes, 10) || 0);
            percentage = 100;
            if (monthlyExhausted) {
                // Right panel already owns monthly usage — keep a compact notice, no extra bar.
                label = 'Monthly free allowance used';
                count = '';
                actionLabel = 'Upgrade for more generations';
                stateClass = 'bbai-hero-generation-progress--complete';
                hideMeter = true;
            } else if (isDailyLimit) {
                label = "Today's daily limit reached";
                count = 'More available tomorrow';
                actionLabel = 'Review generated ALT text';
                stateClass = 'bbai-hero-generation-progress--complete';
                hideMeter = true;
            } else {
                label = 'Generation complete';
                count = total > 0 ? done + ' / ' + total + ' optimised' : done + ' optimised';
                actionLabel = 'Review generated ALT text';
                stateClass = 'bbai-hero-generation-progress--complete';
            }
        } else if (status === 'partial' || status === 'error' || status === 'quota') {
            if (monthlyExhausted) {
                label = 'Monthly free allowance used';
                count = '';
                actionLabel = 'Upgrade for more generations';
                hideMeter = true;
            } else if (status === 'quota' && isDailyLimit) {
                label = "Today's daily limit reached";
                count = 'More available tomorrow';
                actionLabel = 'Review generated ALT text';
                hideMeter = true;
            } else {
                label = status === 'quota' ? 'Generation paused' : 'Generation needs attention';
                count = total > 0 ? done + ' / ' + total + ' processed' : done + ' processed';
                actionLabel = 'View generation status';
            }
            stateClass = 'bbai-hero-generation-progress--error';
        }

        $inlineProgress
            .removeClass(
                'bbai-hero-generation-progress--processing ' +
                'bbai-hero-generation-progress--complete ' +
                'bbai-hero-generation-progress--error ' +
                'bbai-hero-generation-progress--notice'
            )
            .addClass(stateClass)
            .toggleClass('bbai-hero-generation-progress--notice', hideMeter);
        $inlineProgress.find('[data-bbai-hero-generation-progress-label]').text(label);
        $inlineProgress.find('[data-bbai-hero-generation-progress-count]').text(count).prop('hidden', !count);
        $inlineProgress.find('[data-bbai-hero-generation-progress-track]').prop('hidden', hideMeter);
        $inlineProgress.find('[data-bbai-hero-generation-progress-fill]').css('width', hideMeter ? '0%' : (percentage + '%'));
        $inlineProgress.find('[data-bbai-hero-generation-progress-track]')
            .attr('aria-valuenow', hideMeter ? 0 : percentage)
            .attr('aria-valuetext', count || label);
        $inlineProgress.find('[data-bbai-hero-progress-view]').text(actionLabel);
    }

    function openInlineProgress(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        var $viewButton = $widget ? $widget.find('.bbai-job-widget__view') : $();
        if ($viewButton.length) {
            $viewButton.trigger('click');
        }
    }

    function syncOpenProgressModal(state) {
        var $modal = $('#bbai-bulk-progress-modal.active');
        var total;
        var progress;
        var status;

        if (!$modal.length || !state) {
            return;
        }

        total = Math.max(0, parseInt(state.total, 10) || 0);
        progress = Math.max(0, parseInt(state.progress, 10) || 0);
        status = String(state.status || 'processing');

        if (typeof window.updateBulkProgress === 'function') {
            window.updateBulkProgress(progress, total);
        }
        if (typeof window.updateBulkProgressTitle === 'function' && state.label) {
            window.updateBulkProgressTitle(state.label);
        }
        if (
            (status === 'complete' || status === 'partial' || status === 'error' || status === 'quota') &&
            !$modal.data('bbaiComplete') &&
            typeof window.showBulkProgressComplete === 'function'
        ) {
            window.showBulkProgressComplete(
                Math.max(0, parseInt(state.successes, 10) || 0),
                Math.max(0, parseInt(state.failures, 10) || 0),
                total
            );
        }
    }

    // Init when DOM ready
    $(function () {
        if (!window.bbaiJobState) return;

        createWidget();
        $inlineProgress = mountInlineProgress();
        $(document).on('click', '[data-bbai-hero-progress-view="1"]', openInlineProgress);
        window.bbaiJobState.subscribe(render);

        // Render initial state
        render(window.bbaiJobState.getState());
    });

})(jQuery);
