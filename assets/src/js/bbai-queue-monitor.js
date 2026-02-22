(function($) {
    'use strict';

    var config = window.BBAI_DASH || window.BBAI || {};

    var QueueMonitor = {
        initialized: false,
        isLoading: false,
        refreshTimer: null,

        init: function() {
            if (this.initialized) {
                return;
            }

            this.cacheDom();

            if (!this.$card.length) {
                return;
            }

            if (!this.getRestNonce() || !this.getRestUrl()) {
                if (window.console) {
                    console.warn('[AI Alt Text] Queue monitor skipped – REST details missing.');
                }
                return;
            }

            this.initialized = true;
            this.bindEvents();
            this.refresh(true);
        },

        cacheDom: function() {
            this.$card = $('.bbai-queue-card');

            if (!this.$card.length) {
                return;
            }

            this.$pending = this.$card.find('[data-queue-pending]');
            this.$processing = this.$card.find('[data-queue-processing]');
            this.$failed = this.$card.find('[data-queue-failed]');
            this.$completed = this.$card.find('[data-queue-completed]');
            this.$recent = this.$card.find('[data-queue-recent]');
            this.$failuresWrapper = this.$card.find('[data-queue-failures-wrapper]');
            this.$failures = this.$card.find('[data-queue-failures]');

            this.emptyRecentHtml = this.$recent.html();
        },

        bindEvents: function() {
            var self = this;

            $(document).on('click', '[data-action="refresh-queue"]', function(event) {
                event.preventDefault();
                self.refresh(true, { userInitiated: true, reveal: true });
            });

            $(document).on('click', '[data-action="retry-failed"]', function(event) {
                event.preventDefault();
                self.runQueueAction('alttextai_queue_retry_failed', {}, 'Retry scheduled for failed jobs.');
            });

            $(document).on('click', '[data-action="clear-completed"]', function(event) {
                event.preventDefault();
                self.runQueueAction('alttextai_queue_clear_completed', {}, 'Cleared completed jobs.');
            });

            $(document).on('click', '[data-action="retry-job"]', function(event) {
                event.preventDefault();
                var jobId = parseInt($(this).attr('data-job-id'), 10);
                if (!jobId) {
                    return;
                }
                self.runQueueAction('alttextai_queue_retry_job', { job_id: jobId }, 'Job re-queued.');
            });

            $(document).on('alttextai:queue:refresh', function(_event, detail) {
                self.refresh(true, detail || {});
            });
        },

        refresh: function(force, options) {
            if (!this.$card.length || !this.getRestUrl()) {
                return;
            }

            options = options || {};

            this.setLoading(true);

            var url = this.getRestUrl();
            if (force) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
            }

            var self = this;
            $.ajax({
                url: url,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.getRestNonce()
                }
            })
            .done(function(response) {
                if (!response) {
                    return;
                }

                self.updateStats(response.stats || {});
                self.renderRecent(response.recent || []);
                self.renderFailures(response.failures || []);
                self.scheduleAutoRefresh(response.stats || {});

                if (options.reveal) {
                    self.reveal();
                }
            })
            .fail(function(xhr) {
                if (window.console) {
                    console.error('[AI Alt Text] Queue status request failed', xhr);
                }

                if (options.userInitiated) {
                    self.showNotification('Unable to refresh the queue right now. Please try again shortly.', 'error');
                }
            })
            .always(function() {
                self.setLoading(false);
            });
        },

        updateStats: function(stats) {
            this.$pending.text(this.formatNumber(stats.pending));
            this.$processing.text(this.formatNumber(stats.processing));
            this.$failed.text(this.formatNumber(stats.failed));
            this.$completed.text(this.formatNumber(stats.completed_recent));
        },

        renderRecent: function(items) {
            this.$recent.empty();

            if (!items.length) {
                if (this.emptyRecentHtml) {
                    this.$recent.html(this.emptyRecentHtml);
                } else {
                    this.$recent.append(
                        $('<p/>', {
                            'class': 'bbai-queue-empty',
                            text: 'Jobs will appear here when the queue runs.'
                        })
                    );
                }
                return;
            }

            var self = this;
            items.forEach(function(job) {
                self.$recent.append(self.buildQueueItem(job));
            });
        },

        renderFailures: function(items) {
            this.$failures.empty();

            if (!items.length) {
                this.$failuresWrapper.hide();
                return;
            }

            this.$failuresWrapper.show();
            var self = this;

            items.forEach(function(job) {
                var $item = self.buildQueueItem(job, true);

                var $actions = $('<div/>', { 'class': 'bbai-queue-item__meta' });
                $('<button/>', {
                    type: 'button',
                    'class': 'bbai-queue-btn bbai-queue-btn--inline',
                    'data-action': 'retry-job',
                    'data-job-id': job.id,
                    text: 'Retry now'
                }).appendTo($actions);

                $item.append($actions);
                self.$failures.append($item);
            });
        },

        buildQueueItem: function(job, isFailure) {
            var status = (job.status || '').toLowerCase();
            var $item = $('<div/>', {
                'class': 'bbai-queue-item' + (status === 'failed' ? ' bbai-queue-item--failed' : '')
            });

            var title = job.attachment_title || 'Image #' + job.attachment_id;
            var $title = $('<p/>', { 'class': 'bbai-queue-item__title' });

            if (job.edit_url) {
                $('<a/>', {
                    href: job.edit_url,
                    'class': 'bbai-queue-item__link',
                    text: title
                }).appendTo($title);
            } else {
                $title.text(title);
            }

            $item.append($title);

            var metaParts = [];
            if (status) {
                metaParts.push(this.humanizeStatus(status));
            }
            if (job.source) {
                metaParts.push('Source: ' + this.humanizeSource(job.source));
            }
            if (job.enqueued_at) {
                metaParts.push('Queued ' + this.formatRelative(job.enqueued_at));
            }
            if (job.locked_at && status === 'processing') {
                metaParts.push('Claimed ' + this.formatRelative(job.locked_at));
            }
            if (job.completed_at) {
                metaParts.push('Completed ' + this.formatRelative(job.completed_at));
            }
            if (job.attempts && job.attempts > 1) {
                metaParts.push('Attempts: ' + job.attempts);
            }

            if (metaParts.length) {
                var $meta = $('<div/>', { 'class': 'bbai-queue-item__meta' });
                metaParts.forEach(function(part) {
                    $('<span/>').text(part).appendTo($meta);
                });
                $item.append($meta);
            }

            if ((isFailure || status === 'failed') && job.last_error) {
                $('<p/>', {
                    'class': 'bbai-queue-item__error',
                    text: job.last_error
                }).appendTo($item);
            }

            return $item;
        },

        runQueueAction: function(action, extraData, successMessage) {
            var ajaxUrl = this.getAjaxUrl();
            if (!ajaxUrl) {
                return;
            }

            var data = $.extend({
                action: action,
                nonce: this.getAjaxNonce()
            }, extraData || {});

            var self = this;
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: data,
                dataType: 'json'
            })
            .done(function(response) {
                if (response && response.success) {
                    if (successMessage) {
                        self.showNotification(successMessage, 'success');
                    }
                    self.refresh(true, { reveal: true });
                } else {
                    var message = response && response.data && response.data.message ?
                        response.data.message :
                        'Queue action failed. Please try again.';
                    self.showNotification(message, 'error');
                }
            })
            .fail(function(xhr) {
                if (window.console) {
                    console.error('[AI Alt Text] Queue action failed', xhr);
                }
                self.showNotification('Queue action failed. Please try again.', 'error');
            });
        },

        scheduleAutoRefresh: function(stats) {
            clearTimeout(this.refreshTimer);

            if (!stats) {
                return;
            }

            if ((stats.pending || 0) > 0 || (stats.processing || 0) > 0) {
                var self = this;
                this.refreshTimer = setTimeout(function() {
                    self.refresh(false);
                }, 20000);
            }
        },

        reveal: function() {
            if (!this.$card.length) {
                return;
            }

            var node = this.$card.get(0);
            if (node && typeof node.scrollIntoView === 'function') {
                node.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            this.$card.addClass('bbai-queue--flash');
            var self = this;
            setTimeout(function() {
                self.$card.removeClass('bbai-queue--flash');
            }, 1200);
        },

        setLoading: function(state) {
            this.isLoading = state;
            this.$card.toggleClass('bbai-queue--loading', !!state);
            this.$card.attr('aria-busy', state ? 'true' : 'false');
        },

        getRestUrl: function() {
            if (config.restQueue) {
                return config.restQueue;
            }
            if (config.restRoot) {
                return config.restRoot + 'bbai/v1/queue';
            }
            return null;
        },

        getRestNonce: function() {
            if (config.nonce) {
                return config.nonce;
            }
            if (window.alttextai_ajax && window.alttextai_ajax.nonce) {
                return window.alttextai_ajax.nonce;
            }
            return null;
        },

        getAjaxUrl: function() {
            if (window.bbai_ajax) {
                if (window.bbai_ajax.ajax_url) {
                    return window.bbai_ajax.ajax_url;
                }
                if (window.bbai_ajax.ajaxurl) {
                    return window.bbai_ajax.ajaxurl;
                }
            }
            return null;
        },

        getAjaxNonce: function() {
            if (window.bbai_ajax && window.bbai_ajax.nonce) {
                return window.bbai_ajax.nonce;
            }
            return config.nonce || '';
        },

        showNotification: function(message, type) {
            type = type || 'info';
            var $notice = $('<div/>', {
                'class': 'notice notice-' + type + ' is-dismissible bbai-queue-notice'
            }).append(
                $('<p/>').text(message)
            );

            $('.wrap').first().prepend($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        humanizeStatus: function(status) {
            var map = {
                pending: 'Pending',
                processing: 'Processing',
                failed: 'Failed',
                completed: 'Completed'
            };
            return map[status] || status.charAt(0).toUpperCase() + status.slice(1);
        },

        humanizeSource: function(source) {
            var map = {
                bulk: 'Bulk',
                'bulk-regenerate': 'Bulk regenerate',
                upload: 'Upload',
                ajax: 'Dashboard',
                queue: 'Queue',
                auto: 'Automatic'
            };
            return map[source] || source.replace(/-/g, ' ');
        },

        formatRelative: function(dateString) {
            if (!dateString) {
                return '';
            }

            var normalised = dateString.replace(' ', 'T');
            var date = new Date(normalised);
            if (isNaN(date.getTime())) {
                date = new Date(dateString);
            }
            if (isNaN(date.getTime())) {
                return dateString;
            }

            var diff = Date.now() - date.getTime();
            var future = diff < 0;
            diff = Math.abs(diff);

            var units = [
                { limit: 60, divisor: 1000, label: 'second' },
                { limit: 3600, divisor: 60000, label: 'minute' },
                { limit: 86400, divisor: 3600000, label: 'hour' },
                { limit: 2592000, divisor: 86400000, label: 'day' },
                { limit: 31104000, divisor: 2592000000, label: 'month' }
            ];

            for (var i = 0; i < units.length; i++) {
                if (diff < units[i].limit * 1000 || i === units.length - 1) {
                    var value = Math.round(diff / units[i].divisor);
                    var label = units[i].label + (value !== 1 ? 's' : '');
                    return future ?
                        'in ' + value + ' ' + label :
                        value + ' ' + label + ' ago';
                }
            }

            return date.toLocaleString();
        },

        formatNumber: function(value) {
            value = parseInt(value, 10) || 0;
            return value.toLocaleString();
        }
    };

    $(document).ready(function() {
        QueueMonitor.init();
    });

    // Expose for debugging
    window.BeepBeepV2QueueMonitor = QueueMonitor;

})(jQuery);
