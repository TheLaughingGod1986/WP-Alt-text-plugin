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
    var $widget = null;
    var $inlineProgress = null;
    var created = false;

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
            '<div id="' + WIDGET_ID + '" class="bbai-job-widget" hidden>' +
            '  <div class="bbai-job-widget__header">' +
            '    <span class="bbai-job-widget__status">Processing\u2026</span>' +
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

        $widget.on('click', '.bbai-job-widget__view', openProgress);

        // Close → dismiss widget (does NOT cancel job).
        // Persist the dismissed state via bbaiBackgroundJob.dismiss() so it
        // survives page navigation.  Fall back to local hide if the module
        // hasn't loaded yet (defensive).
        $widget.on('click', '.bbai-job-widget__close', function () {
            $widget.prop('hidden', true);
            if (window.bbaiBackgroundJob && typeof window.bbaiBackgroundJob.dismiss === 'function') {
                window.bbaiBackgroundJob.dismiss();
            }
        });
    }

    function openProgress() {
        var state = window.bbaiJobState ? window.bbaiJobState.getState() : null;

        // Complete state → navigate to library for review.
        if (state && state.status === 'complete') {
            var adminUrl = (window.bbai_ajax && window.bbai_ajax.admin_url) ||
                           (window.bbai_env  && window.bbai_env.admin_url)  || '';
            if (adminUrl) {
                window.location.assign(adminUrl + '?page=bbai&tab=library');
            }
            return;
        }

        // Processing / error → reopen progress modal if present.
        if (window.bbaiJobState) {
            window.bbaiJobState.update({ modalVisible: true });
        }
        var $modal = $('#bbai-bulk-progress-modal');
        if ($modal.length) {
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
        }
    }

    function renderInlineProgress(state) {
        if (!$inlineProgress || !$inlineProgress.length) return;

        var visible = state.status === 'processing' ||
            state.status === 'complete' ||
            state.status === 'error' ||
            state.status === 'quota';

        $inlineProgress.prop('hidden', !visible);
        if (!visible) return;

        var done = Math.max(0, parseInt(state.progress, 10) || 0);
        var total = Math.max(0, parseInt(state.total, 10) || 0);
        var percentage = total > 0
            ? Math.max(0, Math.min(100, Math.round((done / total) * 100)))
            : Math.max(0, Math.min(100, parseInt(state.percentage, 10) || 0));
        var label = 'Generating ALT text\u2026';
        var count = total > 0 ? done + ' / ' + total + ' complete' : 'Starting\u2026';
        var actionLabel = 'View detailed progress';
        var stateClass = 'bbai-hero-generation-progress--processing';

        if (state.status === 'complete') {
            done = Math.max(done, parseInt(state.successes, 10) || 0);
            percentage = 100;
            label = 'Generation complete';
            count = total > 0 ? done + ' / ' + total + ' optimised' : done + ' optimised';
            actionLabel = 'Review generated ALT text';
            stateClass = 'bbai-hero-generation-progress--complete';
        } else if (state.status === 'error' || state.status === 'quota') {
            label = state.status === 'quota' ? 'Generation paused' : 'Generation needs attention';
            count = total > 0 ? done + ' / ' + total + ' processed' : done + ' processed';
            actionLabel = 'View generation status';
            stateClass = 'bbai-hero-generation-progress--error';
        }

        $inlineProgress
            .removeClass(
                'bbai-hero-generation-progress--processing ' +
                'bbai-hero-generation-progress--complete ' +
                'bbai-hero-generation-progress--error'
            )
            .addClass(stateClass);
        $inlineProgress.find('[data-bbai-hero-generation-progress-label]').text(label);
        $inlineProgress.find('[data-bbai-hero-generation-progress-count]').text(count);
        $inlineProgress.find('[data-bbai-hero-generation-progress-fill]').css('width', percentage + '%');
        $inlineProgress.find('[data-bbai-hero-generation-progress-track]')
            .attr('aria-valuenow', percentage)
            .attr('aria-valuetext', count);
        $inlineProgress.find('[data-bbai-hero-progress-view]').text(actionLabel);
    }

    function render(state) {
        renderInlineProgress(state);

        if (!$widget) return;

        // Show widget when: job running but modal hidden, OR job just completed/failed
        var shouldShow = !state.modalVisible && (
            state.status === 'processing' ||
            state.status === 'complete' ||
            state.status === 'error' ||
            state.status === 'quota'
        );

        // Hide when idle or modal is open during processing
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

        var statusEl = $widget.find('.bbai-job-widget__status');
        var viewBtn  = $widget.find('.bbai-job-widget__view');

        if (state.status === 'complete') {
            var count = state.successes || state.progress || 0;
            statusEl.text(count + ' image' + (count !== 1 ? 's' : '') + ' optimised');
            $widget.find('.bbai-job-widget__progress-text').text('Generation complete');
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--error')
                   .addClass('bbai-job-widget--complete');
            viewBtn.text('Review images');
        } else if (state.status === 'quota') {
            statusEl.text('Generation paused or failed.');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' processed' +
                (state.failures > 0 ? ', ' + state.failures + ' failed' : '')
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('Resume status check');
        } else if (state.status === 'error') {
            statusEl.text('Generation paused or failed.');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' succeeded, ' + state.failures + ' failed'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('Resume status check');
        } else {
            var done  = state.progress || 0;
            var total = state.total    || 0;
            statusEl.text('Generating ALT text\u2026 ' + done + '/' + total + ' complete');
            $widget.find('.bbai-job-widget__progress-text').text(
                done + ' / ' + total
            );
            $widget.find('.bbai-job-widget__eta').text(state.eta ? 'ETA ' + state.eta : '');
            $widget.find('.bbai-job-widget__bar-fill').css('width', state.percentage + '%');
            $widget.removeClass('bbai-job-widget--complete bbai-job-widget--error')
                   .addClass('bbai-job-widget--processing');
            viewBtn.text('View progress');
        }
    }

    // Init when DOM ready
    $(function () {
        if (!window.bbaiJobState) return;

        createWidget();
        $inlineProgress = $('[data-bbai-hero-generation-progress="1"]');
        $(document).on('click', '[data-bbai-hero-progress-view="1"]', openProgress);
        window.bbaiJobState.subscribe(render);

        // Render initial state
        render(window.bbaiJobState.getState());
    });

})(jQuery);
