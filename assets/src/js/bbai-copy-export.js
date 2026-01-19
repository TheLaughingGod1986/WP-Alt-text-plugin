/**
 * BeepBeep AI Copy to Clipboard and Export Features
 * Copy alt text to clipboard and export data in various formats
 */

(function() {
    'use strict';

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
                this.showToast('No alt text to copy.', 'error');
                return;
            }

            // Use modern Clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text)
                    .then(() => {
                        this.showToast('Alt text copied to clipboard!', 'success');
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
                    this.showToast('Alt text copied to clipboard!', 'success');
                    this.trackCopy(attachmentId);
                } else {
                    this.showToast('Failed to copy. Please select and copy manually.', 'error');
                }
            } catch (err) {
                console.error('Fallback copy failed:', err);
                this.showToast('Failed to copy. Please select and copy manually.', 'error');
            }
            
            document.body.removeChild(textarea);
        },

        /**
         * Copy selected alt text
         */
        copySelectedAltText: function() {
            const selectedRows = document.querySelectorAll('.bbai-library-row-check:checked');
            if (selectedRows.length === 0) {
                this.showToast('Please select at least one image.', 'error');
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
                this.showToast('No alt text found in selected images.', 'error');
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
                this.showToast('No data to export.', 'error');
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
            const headers = ['ID', 'Filename', 'Alt Text', 'Status', 'Last Updated'];
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
            this.showToast('Alt text exported as CSV!', 'success');
        },

        /**
         * Export as JSON
         */
        exportJSON: function(data) {
            const jsonContent = JSON.stringify(data, null, 2);
            this.downloadFile(jsonContent, 'beepbeep-ai-alt-text.json', 'application/json');
            this.showToast('Alt text exported as JSON!', 'success');
        },

        /**
         * Export as TXT
         */
        exportTXT: function(data) {
            const txtContent = data.map(item => {
                return `Filename: ${item.filename}\nAlt Text: ${item.altText}\nStatus: ${item.status}\nLast Updated: ${item.lastUpdated}\n${'='.repeat(50)}`;
            }).join('\n\n');

            this.downloadFile(txtContent, 'beepbeep-ai-alt-text.txt', 'text/plain');
            this.showToast('Alt text exported as TXT!', 'success');
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
                window.bbaiAccessibility.announce('Alt text copied to clipboard');
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
