/**
 * BeepBeep AI Bulk Edit Functionality
 * Select multiple images, edit alt text in bulk, undo last action
 */

bbaiRunWithJQuery(function($) {
    'use strict';

    const bulkEdit = {
        selectedIds: [],
        lastAction: null,
        lastActionData: null,

        init: function() {
            this.bindEvents();
            this.updateSelectionBar();
        },

        bindEvents: function() {
            // Select all checkbox
            $(document).on('change', '#bbai-select-all', (e) => {
                const checked = $(e.target).is(':checked');
                $('.bbai-image-checkbox').prop('checked', checked);
                this.updateSelection();
            });

            // Individual checkboxes
            $(document).on('change', '.bbai-image-checkbox', () => {
                this.updateSelection();
                this.updateSelectAllState();
            });

            // Bulk edit button
            $(document).on('click', '[data-action="bulk-edit"]', (e) => {
                e.preventDefault();
                if (this.selectedIds.length === 0) {
                    if (window.bbaiPushToast) {
                        bbaiPushToast('info', 'Please select at least one image to edit.');
                    }
                    return;
                }
                this.showBulkEditModal();
            });

            // Clear selection
            $(document).on('click', '[data-action="clear-selection"]', (e) => {
                e.preventDefault();
                this.clearSelection();
            });

            // Apply bulk edit
            $(document).on('click', '[data-action="apply-bulk-edit"]', (e) => {
                e.preventDefault();
                this.applyBulkEdit();
            });

            // Undo last action
            $(document).on('click', '[data-action="undo-last-action"]', (e) => {
                e.preventDefault();
                this.undoLastAction();
            });

            // Close modal
            $(document).on('click', '.bbai-bulk-edit-modal__close, .bbai-bulk-edit-modal__overlay', (e) => {
                if (e.target === e.currentTarget || $(e.target).hasClass('bbai-bulk-edit-modal__close')) {
                    this.closeBulkEditModal();
                }
            });

            // Escape key to close modal
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && $('.bbai-bulk-edit-modal').hasClass('show')) {
                    this.closeBulkEditModal();
                }
            });
        },

        updateSelection: function() {
            this.selectedIds = [];
            $('.bbai-image-checkbox:checked').each(function() {
                const id = $(this).data('attachment-id') || $(this).closest('tr').data('attachment-id');
                if (id) {
                    bulkEdit.selectedIds.push(parseInt(id));
                }
            });
            this.updateSelectionBar();
        },

        updateSelectAllState: function() {
            const totalCheckboxes = $('.bbai-image-checkbox').length;
            const checkedCheckboxes = $('.bbai-image-checkbox:checked').length;
            $('#bbai-select-all').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
        },

        updateSelectionBar: function() {
            const $bar = $('.bbai-bulk-selection-bar');
            const count = this.selectedIds.length;

            if (count > 0) {
                $bar.addClass('active');
                $bar.find('.bbai-bulk-selection-count').text(count);
            } else {
                $bar.removeClass('active');
            }
        },

        clearSelection: function() {
            $('.bbai-image-checkbox, #bbai-select-all').prop('checked', false);
            this.selectedIds = [];
            this.updateSelectionBar();
        },

        showBulkEditModal: function() {
            const $modal = $('.bbai-bulk-edit-modal');
            if (!$modal.length) {
                this.createBulkEditModal();
            }

            const $modalContent = $('.bbai-bulk-edit-modal');
            $modalContent.addClass('show');
            $('body').css('overflow', 'hidden');

            // Load preview of selected images
            this.loadPreview();
        },

        createBulkEditModal: function() {
            const modalHtml = `
                <div class="bbai-bulk-edit-modal">
                    <div class="bbai-bulk-edit-modal__overlay"></div>
                    <div class="bbai-bulk-edit-modal__content">
                        <div class="bbai-bulk-edit-modal__header">
                            <h2 class="bbai-bulk-edit-modal__title">Bulk Edit Alt Text</h2>
                            <button type="button" class="bbai-bulk-edit-modal__close" aria-label="Close">×</button>
                        </div>
                        <div class="bbai-bulk-edit-modal__body">
                            <div class="bbai-bulk-edit-form-group">
                                <label class="bbai-bulk-edit-form-label">
                                    New Alt Text (will be applied to all <span class="bbai-bulk-edit-count">${this.selectedIds.length}</span> selected images)
                                </label>
                                <textarea 
                                    class="bbai-bulk-edit-form-textarea" 
                                    id="bbai-bulk-edit-textarea"
                                    placeholder="Enter alt text to apply to all selected images..."
                                ></textarea>
                                <p class="bbai-text-sm bbai-text-muted bbai-mt-2">
                                    This will replace the alt text for all selected images. You can undo this action if needed.
                                </p>
                            </div>
                            <div class="bbai-bulk-edit-preview">
                                <div class="bbai-bulk-edit-preview-title">Selected Images:</div>
                                <div class="bbai-bulk-edit-preview-list" id="bbai-bulk-edit-preview-list">
                                    <!-- Preview items will be loaded here -->
                                </div>
                            </div>
                        </div>
                        <div class="bbai-bulk-edit-modal__footer">
                            <button type="button" class="bbai-btn bbai-btn-secondary" data-action="close-bulk-edit-modal">
                                Cancel
                            </button>
                            <button type="button" class="bbai-btn bbai-btn-primary" data-action="apply-bulk-edit">
                                Apply to All Selected
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
        },

        loadPreview: function() {
            const $previewList = $('#bbai-bulk-edit-preview-list');
            $previewList.empty();

            // Load preview for first 5 images
            const previewIds = this.selectedIds.slice(0, 5);
            previewIds.forEach(id => {
                const $row = $(`tr[data-attachment-id="${id}"]`);
                if ($row.length) {
                    const filename = $row.find('td:nth-child(3)').text().trim() || `Image ${id}`;
                    $previewList.append(`<div class="bbai-bulk-edit-preview-item">${this.escapeHtml(filename)}</div>`);
                }
            });

            if (this.selectedIds.length > 5) {
                $previewList.append(`<div class="bbai-bulk-edit-preview-item bbai-text-muted">...and ${this.selectedIds.length - 5} more</div>`);
            }
        },

        applyBulkEdit: function() {
            const newAltText = $('#bbai-bulk-edit-textarea').val().trim();

            if (!newAltText) {
                if (window.bbaiPushToast) {
                    bbaiPushToast('error', 'Please enter alt text before applying.');
                }
                return;
            }

            // Store last action for undo
            this.storeLastAction();

            // Show loading state
            const $applyBtn = $('[data-action="apply-bulk-edit"]');
            const originalText = $applyBtn.text();
            $applyBtn.prop('disabled', true).text('Applying...');

            // Apply to all selected images
            let completed = 0;
            let failed = 0;
            const total = this.selectedIds.length;

            const updateProgress = () => {
                if (completed + failed >= total) {
                    $applyBtn.prop('disabled', false).text(originalText);
                    this.closeBulkEditModal();
                    this.clearSelection();

                    if (window.bbaiPushToast) {
                        if (failed === 0) {
                            bbaiPushToast('success', `Successfully updated alt text for ${completed} image(s).`);
                        } else {
                            bbaiPushToast('error', `Updated ${completed} image(s), but ${failed} failed.`);
                        }
                    }

                    // Show undo notification
                    this.showUndoNotification();
                }
            };

            this.selectedIds.forEach(id => {
                this.updateImageAltText(id, newAltText)
                    .then(() => {
                        completed++;
                        updateProgress();
                    })
                    .catch(() => {
                        failed++;
                        updateProgress();
                    });
            });
        },

        updateImageAltText: function(imageId, altText) {
            return new Promise((resolve, reject) => {
                const config = window.BBAI_DASH || window.BBAI || {};
                const restUrl = config.rest || '/wp-json/wp/v2';
                const nonce = config.nonce || '';

                $.ajax({
                    url: `${restUrl}/media/${imageId}`,
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        alt_text: altText
                    }),
                    success: function(response) {
                        // Update the UI
                        const $row = $(`tr[data-attachment-id="${imageId}"]`);
                        if ($row.length) {
                            $row.find('.bbai-alt-text-cell').text(altText);
                            $row.find('.bbai-status-badge').removeClass('bbai-status-badge--missing').addClass('bbai-status-badge--optimized').text('Optimized');
                        }
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to update image alt text:', error);
                        reject(error);
                    }
                });
            });
        },

        storeLastAction: function() {
            // Store current alt text values for undo
            const lastData = {};
            this.selectedIds.forEach(id => {
                const $row = $(`tr[data-attachment-id="${id}"]`);
                if ($row.length) {
                    const currentAltText = $row.find('.bbai-alt-text-cell').text().trim();
                    lastData[id] = currentAltText;
                }
            });

            this.lastAction = 'bulk-edit';
            this.lastActionData = {
                ids: [...this.selectedIds],
                previousAltTexts: lastData
            };
        },

        undoLastAction: function() {
            if (!this.lastAction || !this.lastActionData) {
                if (window.bbaiPushToast) {
                    bbaiPushToast('info', 'No action to undo.');
                }
                return;
            }

            const { ids, previousAltTexts } = this.lastActionData;
            let completed = 0;
            let failed = 0;

            ids.forEach(id => {
                const previousAltText = previousAltTexts[id] || '';
                this.updateImageAltText(id, previousAltText)
                    .then(() => {
                        completed++;
                        if (completed + failed >= ids.length) {
                            if (window.bbaiPushToast) {
                                bbaiPushToast('success', `Undone: Restored alt text for ${completed} image(s).`);
                            }
                            this.lastAction = null;
                            this.lastActionData = null;
                        }
                    })
                    .catch(() => {
                        failed++;
                        if (completed + failed >= ids.length) {
                            if (window.bbaiPushToast) {
                                bbaiPushToast('error', `Failed to undo changes for ${failed} image(s).`);
                            }
                        }
                    });
            });
        },

        showUndoNotification: function() {
            const notificationHtml = `
                <div class="bbai-undo-notification">
                    <span class="bbai-undo-notification__message">Alt text updated successfully</span>
                    <button type="button" class="bbai-undo-notification__button" data-action="undo-last-action">
                        Undo
                    </button>
                </div>
            `;
            const $notification = $(notificationHtml);
            $('body').append($notification);

            // Auto-hide after 10 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 10000);
        },

        closeBulkEditModal: function() {
            $('.bbai-bulk-edit-modal').removeClass('show');
            $('body').css('overflow', '');
            $('#bbai-bulk-edit-textarea').val('');
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        bulkEdit.init();
    });

    // Expose globally
    window.bbaiBulkEdit = bulkEdit;
});
