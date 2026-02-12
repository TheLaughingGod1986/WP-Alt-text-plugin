/**
 * BeepBeep AI Copy to Clipboard and Export Features
 * Copy alt text to clipboard and export data in various formats
 */

(function() {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;
    const sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : (format) => format;

    const bbaiCopyExport = {
        init: function() {
            this.setupCopyButtons();
            this.setupExportButtons();
            this.setupBulkCopy();
        },

        /**
         * Setup copy to clipboard buttons
         */
        setupCopyButtons: function() {
            const self = this; // Store reference to avoid 'this' binding issues
            
            // Copy buttons for individual alt text
            document.addEventListener('click', function(e) {
                const copyBtn = e.target.closest('[data-action="copy-alt-text"]');
                if (copyBtn) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent event bubbling
                    const altText = copyBtn.dataset.altText || '';
                    const attachmentId = copyBtn.dataset.attachmentId || '';
                    self.copyToClipboard(altText, attachmentId);
                }
            });

            // Copy all alt text from selected rows
            document.addEventListener('click', function(e) {
                const copyAllBtn = e.target.closest('[data-action="copy-selected-alt"]');
                if (copyAllBtn) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent event bubbling
                    self.copySelectedAltText();
                }
            });
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text, attachmentId = '') {
            if (!text) {
                this.showToast(__('No alt text to copy.', 'beepbeep-ai-alt-text-generator'), 'error');
                return;
            }

            // Use modern Clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text)
                    .then(() => {
                        this.showToast(__('Alt text copied to clipboard!', 'beepbeep-ai-alt-text-generator'), 'success');
                        this.trackCopy(attachmentId);
                    })
                    .catch(err => {
                        console.error('Failed to copy:', err);
                        this.fallbackCopy(text, attachmentId);
                    });
            } else {
                this.fallbackCopy(text, attachmentId);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function(text, attachmentId) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.left = '-999999px';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    this.showToast(__('Alt text copied to clipboard!', 'beepbeep-ai-alt-text-generator'), 'success');
                    this.trackCopy(attachmentId);
                } else {
                    this.showToast(__('Failed to copy. Please select and copy manually.', 'beepbeep-ai-alt-text-generator'), 'error');
                }
            } catch (err) {
                console.error('Fallback copy failed:', err);
                this.showToast(__('Failed to copy. Please select and copy manually.', 'beepbeep-ai-alt-text-generator'), 'error');
            }
            
            document.body.removeChild(textarea);
        },

        /**
         * Copy selected alt text
         */
        copySelectedAltText: function() {
            const selectedRows = document.querySelectorAll('.bbai-library-row-check:checked');
            if (selectedRows.length === 0) {
                this.showToast(__('Please select at least one image.', 'beepbeep-ai-alt-text-generator'), 'error');
                return;
            }

            const altTexts = [];
            selectedRows.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (row) {
                    const altTextCell = row.querySelector('.bbai-alt-text-preview');
                    if (altTextCell) {
                        const altText = altTextCell.textContent.trim();
                        const filename = row.querySelector('.bbai-library-filename')?.textContent.trim() || '';
                        if (altText) {
                            altTexts.push(`${filename}: ${altText}`);
                        }
                    }
                }
            });

            if (altTexts.length === 0) {
                this.showToast(__('No alt text found in selected images.', 'beepbeep-ai-alt-text-generator'), 'error');
                return;
            }

            const textToCopy = altTexts.join('\n\n');
            this.copyToClipboard(textToCopy);
        },

        /**
         * Setup export buttons
         */
        setupExportButtons: function() {
            // Handle export button clicks
            document.addEventListener('click', (e) => {
                const exportBtn = e.target.closest('[data-action="export-alt-text"]');
                if (exportBtn) {
                    e.preventDefault();
                    const format = exportBtn.dataset.format || 'csv';
                    
                    // Close dropdown if clicking on dropdown item
                    const dropdown = exportBtn.closest('.bbai-dropdown');
                    if (dropdown) {
                        const menu = dropdown.querySelector('.bbai-dropdown-menu');
                        if (menu) {
                            menu.style.display = 'none';
                        }
                    }
                    
                    this.exportAltText(format);
                }
            });

            // Toggle dropdown menu
            document.addEventListener('click', (e) => {
                const dropdownBtn = e.target.closest('.bbai-dropdown > button[data-action="export-alt-text"]');
                if (dropdownBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = dropdownBtn.closest('.bbai-dropdown');
                    const menu = dropdown?.querySelector('.bbai-dropdown-menu');
                    if (menu) {
                        const isVisible = menu.style.display !== 'none';
                        menu.style.display = isVisible ? 'none' : 'block';
                    }
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.bbai-dropdown')) {
                    document.querySelectorAll('.bbai-dropdown-menu').forEach(menu => {
                        menu.style.display = 'none';
                    });
                }
            });
        },

        /**
         * Export alt text data
         */
        exportAltText: function(format = 'csv') {
            const rows = document.querySelectorAll('.bbai-library-row');
            if (rows.length === 0) {
                this.showToast(__('No data to export.', 'beepbeep-ai-alt-text-generator'), 'error');
                return;
            }

            const data = [];
            rows.forEach(row => {
                const attachmentId = row.dataset.attachmentId || '';
                const filename = row.querySelector('.bbai-library-filename')?.textContent.trim() || '';
                const altText = row.querySelector('.bbai-alt-text-preview')?.textContent.trim() || '';
                const status = row.dataset.status || '';
                const date = row.querySelector('td:last-of-type')?.textContent.trim() || '';

                data.push({
                    id: attachmentId,
                    filename: filename,
                    altText: altText,
                    status: status,
                    lastUpdated: date
                });
            });

            switch (format) {
                case 'csv':
                    this.exportCSV(data);
                    break;
                case 'json':
                    this.exportJSON(data);
                    break;
                case 'txt':
                    this.exportTXT(data);
                    break;
                default:
                    this.exportCSV(data);
            }
        },

        /**
         * Export as CSV
         */
        exportCSV: function(data) {
            const headers = [
                __('ID', 'beepbeep-ai-alt-text-generator'),
                __('Filename', 'beepbeep-ai-alt-text-generator'),
                __('Alt Text', 'beepbeep-ai-alt-text-generator'),
                __('Status', 'beepbeep-ai-alt-text-generator'),
                __('Last Updated', 'beepbeep-ai-alt-text-generator'),
            ];
            const rows = data.map(item => [
                item.id,
                `"${item.filename.replace(/"/g, '""')}"`,
                `"${item.altText.replace(/"/g, '""')}"`,
                item.status,
                `"${item.lastUpdated}"`
            ]);

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.join(','))
            ].join('\n');

            this.downloadFile(csvContent, 'beepbeep-ai-alt-text.csv', 'text/csv');
            this.showToast(__('Alt text exported as CSV!', 'beepbeep-ai-alt-text-generator'), 'success');
        },

        /**
         * Export as JSON
         */
        exportJSON: function(data) {
            const jsonContent = JSON.stringify(data, null, 2);
            this.downloadFile(jsonContent, 'beepbeep-ai-alt-text.json', 'application/json');
            this.showToast(__('Alt text exported as JSON!', 'beepbeep-ai-alt-text-generator'), 'success');
        },

        /**
         * Export as TXT
         */
        exportTXT: function(data) {
            const txtContent = data.map(item => {
                return sprintf(
                    /* translators: 1: filename, 2: alt text, 3: status, 4: last updated */
                    __('Filename: %1$s\nAlt Text: %2$s\nStatus: %3$s\nLast Updated: %4$s', 'beepbeep-ai-alt-text-generator'),
                    item.filename,
                    item.altText,
                    item.status,
                    item.lastUpdated
                ) + '\n' + '='.repeat(50);
            }).join('\n\n');

            this.downloadFile(txtContent, 'beepbeep-ai-alt-text.txt', 'text/plain');
            this.showToast(__('Alt text exported as TXT!', 'beepbeep-ai-alt-text-generator'), 'success');
        },

        /**
         * Download file
         */
        downloadFile: function(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            if (window.bbaiPushToast) {
                window.bbaiPushToast(type, message, { duration: 3000 });
            } else if (window.bbaiToast) {
                window.bbaiToast[type](message, { duration: 3000 });
            } else {
                alert(message);
            }
        },

        /**
         * Track copy action
         */
        trackCopy: function(attachmentId) {
            // Could send analytics event here
            if (window.bbaiAccessibility && typeof window.bbaiAccessibility.announce === 'function') {
                window.bbaiAccessibility.announce(__('Alt text copied to clipboard', 'beepbeep-ai-alt-text-generator'));
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiCopyExport.init());
    } else {
        bbaiCopyExport.init();
    }

    // Expose globally
    window.bbaiCopyExport = bbaiCopyExport;
})();
