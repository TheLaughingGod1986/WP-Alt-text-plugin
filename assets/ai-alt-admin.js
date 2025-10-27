/**
 * AiAlt AI Alt Text Generator - Admin JS
 * Media Library Integration with Gamification 🎮
 */

(function($) {
    'use strict';

    // ========================================
    // 🎨 MINI TOAST FOR MEDIA LIBRARY
    // ========================================
    const MiniToast = {
        show(message, type = 'success') {
            // Use main toast system (AiAltToast is globally available)
            if (typeof window.AiAltToast !== 'undefined') {
                window.AiAltToast.show({
                    type: type,
                    message: message,
                    duration: 3000
                });
            }
        }
    };

    // ========================================
    // ✨ SPARKLE ANIMATION
    // ========================================
    function addSparkle(x, y) {
        const sparkle = $('<div style="position: fixed; pointer-events: none; z-index: 999999; font-size: 1.5rem; animation: sparkleAnim 1s ease-out forwards;">✨</div>');
        sparkle.css({ left: x + 'px', top: y + 'px' });
        $('body').append(sparkle);
        setTimeout(() => sparkle.remove(), 1000);
    }

    // Add sparkle animation CSS if not already present
    if (!$('#ai-alt-sparkle-animation').length) {
        $('<style id="ai-alt-sparkle-animation">@keyframes sparkleAnim { 0% { opacity: 0; transform: scale(0) rotate(0deg); } 50% { opacity: 1; transform: scale(1) rotate(180deg); } 100% { opacity: 0; transform: scale(0.5) rotate(360deg); } }</style>').appendTo('head');
        $('<style id="ai-alt-slide-in">@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }</style>').appendTo('head');
    }

    const LibraryQueueIndicator = {
        config: null,
        $badge: null,
        timer: null,

        init(config) {
            if (!config || !config.restQueue) { return; }
            this.config = config;
            if (this.$badge && this.$badge.length) { return; }

            this.$badge = $(
                '<button type="button" class="ai-alt-queue-mini" data-action="refresh-queue-badge">' +
                '<span class="ai-alt-queue-mini__dot"></span>' +
                '<span class="ai-alt-queue-mini__label">Queue</span>' +
                '<span class="ai-alt-queue-mini__counts"><span data-queue-badge-pending>0</span> / <span data-queue-badge-processing>0</span></span>' +
                '</button>'
            );

            const $header = $('#wpbody-content').find('.wrap h1').first();
            if ($header.length) {
                $header.append(this.$badge);
            } else {
                $('#wpbody-content .wrap').first().prepend(this.$badge);
            }

            const self = this;
            this.$badge.on('click', function(e){
                e.preventDefault();
                self.refresh(true);
            });

            this.refresh(true);
            this.start();
        },

        start() {
            this.stop();
            if (!this.config || !this.config.restQueue) { return; }
            this.timer = setInterval(() => this.refresh(true), 45000);
        },

        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        refresh(silent = false) {
            if (!this.config || !this.config.restQueue) { return; }
            const headers = {};
            const nonce = this.config.nonce || (window.wpApiSettings ? wpApiSettings.nonce : '');
            if (nonce) { headers['X-WP-Nonce'] = nonce; }

            fetch(this.config.restQueue, {
                method: 'GET',
                credentials: 'same-origin',
                headers
            })
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(data => {
                const stats = data && data.stats ? data.stats : {};
                const pending = parseInt(stats.pending || 0, 10);
                const processing = parseInt(stats.processing || 0, 10);
                const failed = parseInt(stats.failed || 0, 10);
                this.updateBadge(pending, processing, failed);
            })
            .catch(() => {
                if (!silent) {
                    MiniToast.show('Unable to refresh queue status.', 'warning');
                }
            });
        },

        updateBadge(pending, processing, failed) {
            if (!this.$badge || !this.$badge.length) { return; }
            this.$badge.toggleClass('ai-alt-queue-mini--busy', (pending + processing) > 0);
            this.$badge.toggleClass('ai-alt-queue-mini--error', failed > 0);
            this.$badge.find('[data-queue-badge-pending]').text(pending);
            this.$badge.find('[data-queue-badge-processing]').text(processing);
        }
    };

    // ========================================
    // 📋 ALT PREVIEW MODAL
    // ========================================
    const AltPreviewModal = (function() {
        const MODAL_ID = 'ai-alt-preview-modal';
        let currentAttachmentId = null;
        let currentMeta = null;

        function escapeHtml(text) {
            return $('<div>').text(text || '').html();
        }

        function buildModal(payload) {
            const stats = payload.meta.analysis || {};
            const grade = stats.grade || '';
            const score = typeof stats.score === 'number' ? stats.score : null;
            const issues = Array.isArray(stats.issues) ? stats.issues : [];
            const duplicateNotice = payload.duplicate
                ? '<div class="ai-alt-modal__notice">This matches the existing ALT text. You can tweak it below and save.</div>'
                : '';

            return $(`
                <div class="ai-alt-modal-overlay" id="${MODAL_ID}" role="dialog" aria-modal="true">
                    <div class="ai-alt-modal" role="document">
                        <button type="button" class="ai-alt-modal__close" aria-label="Close">×</button>
                        <h2 class="ai-alt-modal__title">Fresh ALT text ready</h2>
                        <p class="ai-alt-modal__subtitle">Fine-tune the description or apply it as-is.</p>
                        ${duplicateNotice}
                        <label class="ai-alt-modal__label" for="ai-alt-modal-textarea">ALT text</label>
                        <textarea id="ai-alt-modal-textarea" class="ai-alt-modal__textarea" name="ai_alt_modal_text" rows="4">${escapeHtml(payload.alt)}</textarea>
                        <div class="ai-alt-modal__actions">
                            <button type="button" class="button button-primary ai-alt-modal__apply">Save ALT text</button>
                            <button type="button" class="button ai-alt-modal__copy">Copy ALT text</button>
                            <button type="button" class="button ai-alt-modal__dismiss">Close</button>
                        </div>
                        <div class="ai-alt-modal__meta">
                            ${score !== null ? `<span class="ai-alt-modal__badge">Score: ${score}</span>` : ''}
                            ${grade ? `<span class="ai-alt-modal__badge">${grade}</span>` : ''}
                        </div>
                        ${issues.length ? `<ul class="ai-alt-modal__issues">${issues.map(issue => `<li>${escapeHtml(issue)}</li>`).join('')}</ul>` : ''}
                    </div>
                </div>
            `);
        }

        function copyAltText($modal) {
            const $textarea = $modal.find('textarea[name="ai_alt_modal_text"]');
            const altText = ($textarea.val() || '').trim();
            if (!altText) {
                MiniToast.show('Nothing to copy yet.', 'warning');
                return;
            }
            const temp = $('<textarea style="position:absolute;left:-9999px;"></textarea>').val(altText);
            $('body').append(temp);
            temp[0].select();
            try { document.execCommand('copy'); } catch (e) {}
            temp.remove();
            MiniToast.show('Copied ALT text to clipboard ✔️', 'success');
        }

        function submitAlt($modal) {
            if (!currentAttachmentId || !AI_ALT_GPT.restAlt) {
                MiniToast.show('ALT endpoint unavailable.', 'error');
                return;
            }

            const $textarea = $modal.find('textarea[name="ai_alt_modal_text"]');
            const newAlt = ($textarea.val() || '').trim();

            if (newAlt === '') {
                MiniToast.show('ALT text cannot be empty.', 'error');
                return;
            }

            const $apply = $modal.find('.ai-alt-modal__apply');
            const originalLabel = $apply.text();
            $apply.prop('disabled', true).text('Saving…');

            $.ajax({
                url: AI_ALT_GPT.restAlt + currentAttachmentId,
                method: 'POST',
                data: { alt: newAlt },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', AI_ALT_GPT.nonce);
                }
            }).done(function(response) {
                if (!response || !response.alt) {
                    MiniToast.show('Something went wrong saving ALT text.', 'error');
                    return;
                }

                MiniToast.show('ALT text updated successfully!', 'success');

                const $row = findLibraryRow(response.id);
                if ($row && $row.length) {
                    updateLibraryRow($row, response.meta || { alt: response.alt });
                    $row.addClass('ai-alt-library__row--updated');
                    setTimeout(() => $row.removeClass('ai-alt-library__row--updated'), 2000);
                }

                if (response.stats) {
                    updateGlobalStats(response.stats);
                }

                close();
            }).fail(function(xhr) {
                const message = xhr && xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Failed to save ALT text';
                MiniToast.show('❌ ' + message, 'error');
            }).always(function() {
                $apply.prop('disabled', false).text(originalLabel);
            });
        }

        function wireEvents($modal) {
            $modal.on('click', '.ai-alt-modal__close, .ai-alt-modal__dismiss', function() {
                close();
            });

            $modal.on('click', '.ai-alt-modal__copy', function() {
                copyAltText($modal);
            });

            $modal.on('click', '.ai-alt-modal__apply', function() {
                submitAlt($modal);
            });

            $modal.on('click', function(e) {
                if ($(e.target).is($modal)) {
                    close();
                }
            });
        }

        function open(payload = {}) {
            close();

            currentAttachmentId = payload.id || null;
            currentMeta = payload.meta || {};

            const altText = payload.alt || '';
            const modalPayload = {
                alt: altText,
                meta: currentMeta,
                duplicate: !!payload.duplicate,
            };

            const $modal = buildModal(modalPayload);
            wireEvents($modal);
            $('body').append($modal);
            setTimeout(() => {
                $modal.addClass('is-visible');
                $modal.find('textarea[name="ai_alt_modal_text"]').trigger('focus').select();
            }, 10);
        }

        function close() {
            const $modal = $('#' + MODAL_ID);
            if ($modal.length) {
                $modal.removeClass('is-visible');
                setTimeout(() => $modal.remove(), 150);
            }
            currentAttachmentId = null;
            currentMeta = null;
        }

        function handleKeydown(e) {
            if (e.key === 'Escape') {
                close();
            }
        }

        $(document).on('keydown', handleKeydown);

        return { open, close };
    })();

    // ========================================
    // 🎮 MEDIA LIBRARY ROW ACTION
    // ========================================
    function initRowActions() {
        $('.ai-alt-generate-row-action').on('click', function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const postId = $link.data('post-id');
            const $row = $link.closest('tr');
            
            if (!postId) {
                MiniToast.show('Invalid attachment ID', 'error');
                return;
            }
            
            // Add loading state
            $link.text('Generating... ✨');
            $link.css('pointer-events', 'none');
            
            // Add sparkle effect
            const offset = $link.offset();
            addSparkle(offset.left, offset.top);
            
            // Make API request
            $.ajax({
                url: AI_ALT_GPT.rest + postId,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', AI_ALT_GPT.nonce);
                },
                success: function(response) {
                    if (response && response.alt) {
                        MiniToast.show('🎉 Alt text generated successfully!', 'success');
                        $link.text('Regenerate Alt Text (AI)');
                        
                        // Highlight the row briefly
                        $row.css('background', '#d4edda');
                        setTimeout(() => {
                            $row.css('background', '');
                        }, 2000);
                        
                        // Update stats if available
                        updateGlobalStats();
                    } else {
                        MiniToast.show('⚠️ Generated, but no alt text returned', 'warning');
                        $link.text('Generate Alt Text (AI)');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Failed to generate alt text';
                    MiniToast.show('❌ ' + message, 'error');
                    $link.text('Generate Alt Text (AI)');
                },
                complete: function() {
                    $link.css('pointer-events', '');
                }
            });
        });
    }

    // ========================================
    // 🔁 ALT LIBRARY + DASHBOARD REGENERATE
    // ========================================
    function setLoadingState($btn, isLoading) {
        if (isLoading) {
            if (!$btn.data('original-text')) {
                $btn.data('original-text', $.trim($btn.text()));
            }
            $btn.prop('disabled', true).addClass('loading').text('Regenerating…');
        } else {
            const original = $btn.data('original-text');
            if (original) {
                $btn.text(original);
            }
            $btn.prop('disabled', false).removeClass('loading');
        }
    }

    function formatNumber(value) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return '0';
        }
        try {
            return value.toLocaleString();
        } catch (e) {
            return String(value);
        }
    }

    function recalcLibrarySummary() {
        const $rows = $('.ai-alt-library__table tbody tr');
        if (!$rows.length) {
            return;
        }

        let total = 0;
        let scoreSum = 0;
        let healthy = 0;
        let review = 0;
        let critical = 0;

        $rows.each(function() {
            const $row = $(this);
            const $scoreCell = $row.find('.ai-alt-library__score');
            if (!$scoreCell.length) {
                return;
            }
            total += 1;

            const badgeText = $.trim($scoreCell.find('.ai-alt-score-badge').text());
            const numericScore = parseInt(badgeText, 10);
            if (!isNaN(numericScore)) {
                scoreSum += numericScore;
            }

            const status = $scoreCell.attr('data-status');
            if (status === 'great' || status === 'good') {
                healthy += 1;
            } else if (status === 'review') {
                review += 1;
            } else {
                critical += 1;
            }
        });

        const average = total > 0 ? Math.round(scoreSum / total) : 0;
        $('[data-library-summary-average]').text(formatNumber(average));
        $('[data-library-summary-healthy]').text(formatNumber(healthy));
        $('[data-library-summary-review]').text(formatNumber(review));
        $('[data-library-summary-critical]').text(formatNumber(critical));
        $('[data-library-summary-total]').text(formatNumber(total));
    }

    function updateLibraryRow($row, meta) {
        if (!$row || !$row.length || !meta) {
            return;
        }

        const analysis = meta.analysis || {};
        const score = typeof analysis.score === 'number' ? analysis.score : meta.score;
        const status = analysis.status || meta.score_status || 'review';
        const grade = analysis.grade || meta.score_grade || '';
        const altText = (meta.alt || '').trim();

        const $altCell = $row.find('.ai-alt-library__alt');
        if ($altCell.length) {
            $altCell.empty();
            if (altText) {
                $altCell.text(altText);
            } else {
                $altCell.text('(empty)');
                $('<span class="ai-alt-library__flag"></span>')
                    .text('Needs ALT review')
                    .appendTo($altCell);
            }
        }

        $row.toggleClass('ai-alt-library__row--missing', !altText);

        const $scoreCell = $row.find('.ai-alt-library__score');
        if ($scoreCell.length) {
            $scoreCell.attr('data-status', status);
            $scoreCell.attr('data-score', typeof score === 'number' ? score : '');

            const $badge = $scoreCell.find('.ai-alt-score-badge');
            if ($badge.length) {
                $badge
                    .removeClass(function(index, className) {
                        return (className || '').split(' ')
                            .filter(cls => cls.indexOf('ai-alt-score-badge--') === 0)
                            .join(' ');
                    })
                    .addClass('ai-alt-score-badge--' + status)
                    .text(typeof score === 'number' ? score : '—');
            }

            const $label = $scoreCell.find('.ai-alt-score-label');
            if ($label.length) {
                $label.text(grade || '');
            }

            const $issuesList = $scoreCell.find('.ai-alt-score-issues');
            if (Array.isArray(analysis.issues) && analysis.issues.length) {
                let $list = $issuesList;
                if (!$list.length) {
                    $list = $('<ul class="ai-alt-score-issues"></ul>').appendTo($scoreCell);
                } else {
                    $list.empty();
                }
                analysis.issues.forEach(issue => {
                    $('<li></li>').text(issue).appendTo($list);
                });
            } else if ($issuesList.length) {
                $issuesList.remove();
            }
        }

        recalcLibrarySummary();
    }

    function findLibraryRow(id, $btnContext) {
        if ($btnContext && $btnContext.length) {
            const $contextRow = $btnContext.closest('tr');
            if ($contextRow.length && parseInt($contextRow.data('id'), 10) === parseInt(id, 10)) {
                return $contextRow;
            }
        }
        const selector = `.ai-alt-library__table tbody tr[data-id="${id}"]`;
        return $(selector).first();
    }

    function updateDashboardRow($btn, meta) {
        if (!$btn.length || !meta) {
            return;
        }

        const altText = (meta.alt || '').trim();
        const $cell = $('.new-alt-cell-' + meta.id);
        if ($cell.length) {
            $cell.empty();
            if (altText) {
                $('<strong></strong>').text(altText).appendTo($cell);
            } else {
                $('<span class="text-muted"></span>')
                    .text('No alt text returned')
                    .appendTo($cell);
            }
        }
    }

    function handleRegenerateClick(e) {
        e.preventDefault();
        const $btn = $(this);
        const attachmentId = parseInt($btn.data('id') || $btn.data('attachment-id'), 10);

        if (!attachmentId) {
            MiniToast.show('Attachment ID missing', 'error');
            return;
        }

        setLoadingState($btn, true);

        const offset = $btn.offset();
        if (offset) {
            addSparkle(offset.left + ($btn.outerWidth() / 2), offset.top);
        }

        $.ajax({
            url: AI_ALT_GPT.rest + attachmentId,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AI_ALT_GPT.nonce);
            }
        }).done(function(response) {
            let handled = false;

            if (response && response.alt) {
                MiniToast.show('✨ Alt text regenerated successfully!', 'success');
                handled = true;
                AltPreviewModal.open({
                    id: response.id,
                    alt: response.alt,
                    meta: response.meta || response.analysis || {}
                });

                if ($btn.hasClass('ai-alt-regenerate-single')) {
                    const $row = findLibraryRow(response.id, $btn);
                    updateLibraryRow($row, response.meta || {
                        alt: response.alt,
                        analysis: response.analysis || {}
                    });
                    if ($row && $row.length) {
                        $row.addClass('ai-alt-library__row--updated');
                        setTimeout(() => $row.removeClass('ai-alt-library__row--updated'), 2000);
                    }
                } else if ($btn.hasClass('alttextai-btn-regenerate')) {
                    updateDashboardRow($btn, response.meta || {});
                }

                if (response.stats) {
                    updateGlobalStats(response.stats);
                } else {
                    updateGlobalStats();
                }
            } else if (response && response.code === 'duplicate_alt') {
                const existing = response.data && response.data.existing ? response.data.existing : '(none)';
                MiniToast.show(`ℹ️ Alt text already matches: “${existing}”`, 'warning');
                handled = true;
            }

            if (!handled) {
                MiniToast.show('⚠️ No alt text was returned. Try again.', 'warning');
            }
        }).fail(function(xhr) {
            const payload = xhr && xhr.responseJSON ? xhr.responseJSON : null;

            if (payload && payload.code === 'duplicate_alt') {
                const existing = payload.data && payload.data.existing ? payload.data.existing : '(no change)';
                MiniToast.show(`ℹ️ Alt text already matches: “${existing}”`, 'info');

                const $row = findLibraryRow(attachmentId, $btn);
                if ($row && $row.length) {
                    $row.addClass('ai-alt-library__row--updated');
                    setTimeout(() => $row.removeClass('ai-alt-library__row--updated'), 2000);
                }
                AltPreviewModal.open({
                    id: attachmentId,
                    alt: existing,
                    meta: payload.data || {},
                    duplicate: true
                });
                setLoadingState($btn, false);
                return;
            }

            const message = payload && payload.message
                ? payload.message
                : 'Failed to regenerate alt text';

            MiniToast.show('❌ ' + message, 'error');
        }).always(function() {
            setLoadingState($btn, false);
        });
    }

    function initLibraryRegenerate() {
        $(document).on('click', '.ai-alt-regenerate-single, .alttextai-btn-regenerate', handleRegenerateClick);
    }

    // ========================================
    // 📦 BULK ACTION HANDLER
    // ========================================
    function initBulkAction() {
        // Monitor bulk action dropdown
        $('select[name="action"], select[name="action2"]').on('change', function() {
            const action = $(this).val();
            if (action === 'generate_alt_text_ai') {
                // Add visual indicator
                $(this).css('border-color', '#14b8a6');
            }
        });
    }

    // ========================================
    // 🎨 ATTACHMENT DETAILS MODAL
    // ========================================
    function initAttachmentModal() {
        // Hook into WordPress media modal
        if (typeof wp !== 'undefined' && wp.media) {
            wp.media.view.Attachment.Details.prototype.on('ready', function() {
                setTimeout(initModalButton, 100);
            });
        }
        
        // Also try direct button injection
        setTimeout(initModalButton, 1000);
        
        // Watch for modal changes
        $(document).on('DOMNodeInserted', '.attachment-details', function() {
            setTimeout(initModalButton, 100);
        });
    }

    function initModalButton() {
        const $button = $('.ai-alt-generate-button');
        if ($button.length && !$button.data('initialized')) {
            $button.data('initialized', true);
            
            // Style the button
            $button.css({
                'background': 'linear-gradient(135deg, #14b8a6 0%, #84cc16 100%)',
                'color': 'white',
                'border': 'none',
                'padding': '8px 16px',
                'border-radius': '8px',
                'font-weight': '700',
                'cursor': 'pointer',
                'transition': 'all 0.3s ease',
                'box-shadow': '0 2px 4px rgba(0,0,0,0.1)'
            });
            
            $button.on('mouseenter', function() {
                $(this).css('transform', 'translateY(-2px)');
                $(this).css('box-shadow', '0 4px 8px rgba(102, 126, 234, 0.3)');
            });
            
            $button.on('mouseleave', function() {
                $(this).css('transform', 'translateY(0)');
                $(this).css('box-shadow', '0 2px 4px rgba(0,0,0,0.1)');
            });
            
            $button.on('click', function(e) {
                e.preventDefault();
                const offset = $button.offset();
                addSparkle(offset.left + 50, offset.top);
            });
        }
    }

    // ========================================
    // 📊 STATS UPDATE
    // ========================================
    function updateGlobalStats(forcedStats) {
        const applyStats = function(stats) {
            if (typeof window.AiAltDashboard !== 'undefined' && stats) {
                window.AiAltDashboard.updateStats(stats);
            }

            $('.ai-alt-stats-counter').each(function() {
                const $counter = $(this);
                const metric = $counter.data('metric');

                if (metric && stats && typeof stats[metric] !== 'undefined') {
                    const numeric = parseInt(stats[metric], 10);
                    $counter.text(formatNumber(isNaN(numeric) ? 0 : numeric));
                }

                $counter.css({
                    'transform': 'scale(1.2)',
                    'color': '#43e97b'
                });
                setTimeout(() => {
                    $counter.css({
                        'transform': 'scale(1)',
                        'color': ''
                    });
                }, 300);
            });

            $(document).trigger('ai-alt:statsUpdated', [stats]);
        };

        if (forcedStats) {
            applyStats(forcedStats);
            return;
        }

        if (AI_ALT_GPT.restStats) {
            $.get(AI_ALT_GPT.restStats, function(stats) {
                applyStats(stats);
            });
        } else {
            applyStats(null);
        }
    }

    // ========================================
    // 🎯 PROGRESS INDICATOR
    // ========================================
    const ProgressIndicator = {
        $indicator: null,
        
        init() {
            if (!this.$indicator) {
                this.$indicator = $(`
                    <div class="ai-alt-progress-indicator" style="
                        position: fixed;
                        bottom: 32px;
                        right: 32px;
                        background: white;
                        border-radius: 12px;
                        padding: 16px 24px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        z-index: 999998;
                        display: none;
                        min-width: 300px;
                    ">
                        <div class="ai-alt-progress-title" style="font-weight: 700; margin-bottom: 8px; color: #2d3748;">Processing...</div>
                        <div class="ai-alt-progress-bar" style="
                            height: 8px;
                            background: #e2e8f0;
                            border-radius: 4px;
                            overflow: hidden;
                        ">
                            <div class="ai-alt-progress-fill" style="
                                height: 100%;
                                background: linear-gradient(135deg, #14b8a6 0%, #84cc16 100%);
                                width: 0%;
                                transition: width 0.3s ease;
                            "></div>
                        </div>
                        <div class="ai-alt-progress-text" style="font-size: 12px; color: #718096; margin-top: 8px;">0 / 0</div>
                    </div>
                `);
                $('body').append(this.$indicator);
            }
        },
        
        show(total) {
            this.init();
            this.total = total;
            this.current = 0;
            this.$indicator.find('.ai-alt-progress-text').text(`0 / ${total}`);
            this.$indicator.find('.ai-alt-progress-fill').css('width', '0%');
            this.$indicator.fadeIn(300);
        },
        
        update(current) {
            this.current = current;
            const percentage = (current / this.total) * 100;
            this.$indicator.find('.ai-alt-progress-fill').css('width', percentage + '%');
            this.$indicator.find('.ai-alt-progress-text').text(`${current} / ${this.total}`);
            
            if (current >= this.total) {
                setTimeout(() => this.hide(), 1000);
            }
        },
        
        hide() {
            this.$indicator.fadeOut(300);
        }
    };

    // ========================================
    // 🎊 BATCH COMPLETION CELEBRATION
    // ========================================
    function celebrateBatchCompletion(count) {
        // Create mini confetti effect
        const colors = ['#14b8a6', '#84cc16', '#4facfe', '#ffd700'];
        for (let i = 0; i < 20; i++) {
            const confetti = $('<div></div>').css({
                position: 'fixed',
                width: '6px',
                height: '6px',
                background: colors[Math.floor(Math.random() * colors.length)],
                top: '50%',
                left: '50%',
                'z-index': '999999',
                'border-radius': '50%',
                'pointer-events': 'none'
            });
            
            $('body').append(confetti);
            
            confetti.animate({
                top: Math.random() * 100 + '%',
                left: Math.random() * 100 + '%',
                opacity: 0
            }, 2000, function() {
                $(this).remove();
            });
        }
        
        MiniToast.show(`🎉 ${count} images processed successfully!`, 'success');
    }

    // ========================================
    // 💾 LOCAL STORAGE ACHIEVEMENTS
    // ========================================
    const Achievements = {
        increment(key) {
            const current = parseInt(localStorage.getItem(key) || '0');
            const newValue = current + 1;
            localStorage.setItem(key, newValue.toString());
            
            // Check for milestone achievements
            if (newValue === 1) {
                MiniToast.show('🎯 First alt text generated!', 'success');
            } else if (newValue === 10) {
                MiniToast.show('🔥 10 alt texts generated!', 'success');
            } else if (newValue === 50) {
                MiniToast.show('💯 50 alt texts generated! On fire!', 'success');
            } else if (newValue === 100) {
                MiniToast.show('🏆 Century club! 100 alt texts!', 'success');
            }
            
            return newValue;
        },
        
        get(key) {
            return parseInt(localStorage.getItem(key) || '0');
        }
    };

    // ========================================
    // ⌨️ KEYBOARD SHORTCUTS
    // ========================================
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Alt + G = Generate alt text for selected items
            if (e.altKey && e.key === 'g') {
                e.preventDefault();
                const $selected = $('.media-frame input[name="media[]"]:checked').first();
                if ($selected.length) {
                    const postId = $selected.val();
                    const $generateBtn = $(`.ai-alt-generate-row-action[data-post-id="${postId}"]`);
                    if ($generateBtn.length) {
                        $generateBtn.click();
                    }
                }
            }
        });
    }

    // ========================================
    // 🎨 VISUAL ENHANCEMENTS
    // ========================================
    function addVisualEnhancements() {
        // Add gradient to admin notices
        $('.notice.notice-success').css({
            'border-left': '4px solid #43e97b',
            'box-shadow': '0 2px 4px rgba(0,0,0,0.05)'
        });
        
        // Enhance existing buttons
        $('.ai-alt-generate-button, .ai-alt-generate-row-action').css({
            'transition': 'all 0.3s ease'
        });
        
        // Add hover effects to media grid items
        $('.attachment').on('mouseenter', function() {
            $(this).css('transform', 'scale(1.02)');
        }).on('mouseleave', function() {
            $(this).css('transform', 'scale(1)');
        });
    }

    // ========================================
    // 🚀 INITIALIZATION
    // ========================================
    $(document).ready(function() {
        
        // Initialize all features
        initRowActions();
        initBulkAction();
        initAttachmentModal();
        initLibraryRegenerate();
        initKeyboardShortcuts();
        addVisualEnhancements();
        
        const config = window.AI_ALT_GPT || {};
        LibraryQueueIndicator.init(config);
        
        $(window).on('beforeunload', function(){
            LibraryQueueIndicator.stop();
        });
        
        // Add sparkle on any AI Alt button click
        $(document).on('click', '[class*="ai-alt"]', function(e) {
            const offset = $(this).offset();
            addSparkle(offset.left + 20, offset.top);
        });
        
        // Monitor for batch processing
        if (typeof wp !== 'undefined' && wp.media) {
            // Hook into media library batch actions
            $(document).on('click', '#doaction, #doaction2', function() {
                const action = $('select[name="action"]').val() || $('select[name="action2"]').val();
                if (action === 'generate_alt_text_ai') {
                    const count = $('.media-frame input[name="media[]"]:checked').length;
                    if (count > 0) {
                        setTimeout(() => {
                            ProgressIndicator.show(count);
                            MiniToast.show(`🚀 Processing ${count} images...`, 'info');
                        }, 500);
                    }
                }
            });
        }
    });

    // Export for global access
    window.AiAltMiniToast = MiniToast;
    window.AiAltProgressIndicator = ProgressIndicator;
    window.AiAltAchievements = Achievements;
    window.updateLibraryRow = updateLibraryRow;
    window.findLibraryRow = findLibraryRow;

})(jQuery);

