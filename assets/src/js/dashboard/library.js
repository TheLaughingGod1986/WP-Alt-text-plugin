/**
 * Library Search and Filter
 * Search and filter functionality for alt text library
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

bbaiRunWithJQuery(function($) {
    'use strict';

    $(document).ready(function() {
        // Search functionality
        $('#bbai-library-search').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            filterLibraryRows(searchTerm, getActiveFilter());
        });

        // Filter buttons
        $('.bbai-filter-btn').on('click', function() {
            var $btn = $(this);
            var filter = $btn.attr('data-filter');

            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
                filterLibraryRows($('#bbai-library-search').val().toLowerCase(), null);
            } else {
                $('.bbai-filter-btn').removeClass('active');
                $btn.addClass('active');
                filterLibraryRows($('#bbai-library-search').val().toLowerCase(), filter);
            }
        });

        /**
         * Get currently active filter
         */
        function getActiveFilter() {
            var $activeBtn = $('.bbai-filter-btn.active');
            return $activeBtn.length ? $activeBtn.attr('data-filter') : null;
        }

        /**
         * Filter library table rows
         */
        function filterLibraryRows(searchTerm, filter) {
            var visibleCount = 0;

            $('.bbai-library-row').each(function() {
                var $row = $(this);
                var status = $row.attr('data-status');
                var rowText = $row.text().toLowerCase();

                var matchesSearch = !searchTerm || rowText.includes(searchTerm);

                var matchesFilter = true;
                if (filter) {
                    if (filter === 'missing') {
                        matchesFilter = status === 'missing';
                    } else if (filter === 'has-alt') {
                        matchesFilter = status === 'optimized' || status === 'regenerated';
                    } else if (filter === 'regenerated') {
                        matchesFilter = status === 'regenerated';
                    } else if (filter === 'recent') {
                        matchesFilter = true;
                    }
                }

                if (matchesSearch && matchesFilter) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            if (visibleCount === 0 && $('.bbai-library-table tbody tr').length > 1) {
                console.log('[AltText AI] No matching rows found');
            }
        }
    });

    /**
     * Character Counter for alt text
     */
    window.bbaiCharCounter = {
        create: function(text, options) {
            options = options || {};
            var charCount = text ? text.length : 0;
            var maxChars = options.maxChars || 125;
            var isOptimal = charCount <= maxChars;
            var isEmpty = charCount === 0;

            var stateClass = isEmpty ? 'bbai-char-counter--empty' :
                           (isOptimal ? 'bbai-char-counter--optimal' : 'bbai-char-counter--warning');

            var icon = isEmpty ? '' :
                      (isOptimal ?
                       '<svg class="bbai-char-counter__icon" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M3.5 6L5.5 8L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
                       '<svg class="bbai-char-counter__icon" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M6 3v3.5M6 8.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');

            var message = isEmpty ? 'No alt text' :
                         (isOptimal ? 'Optimal for SEO' : 'Too long for optimal SEO');

            var tooltip = isEmpty ? 'Add alt text for SEO' :
                         (isOptimal ?
                          'Alt text length is optimal for Google Images (≤125 characters recommended)' :
                          'Consider shortening to 125 characters or less for optimal Google Images SEO');

            return '<span class="bbai-char-counter ' + stateClass + '" title="' + tooltip + '">' +
                   icon +
                   '<span class="bbai-char-counter__number">' + charCount + '</span>' +
                   '<span class="bbai-char-counter__label">/' + maxChars + '</span>' +
                   '</span>';
        },

        init: function() {
            $('.bbai-library-alt-text').each(function() {
                var $altText = $(this);
                var text = $altText.text().trim();

                if ($altText.next('.bbai-char-counter').length === 0) {
                    var counterHTML = window.bbaiCharCounter.create(text);
                    $altText.after(counterHTML);
                }
            });
        },

        update: function($element, newText) {
            var $counter = $element.next('.bbai-char-counter');
            if ($counter.length) {
                var newCounterHTML = this.create(newText);
                $counter.replaceWith(newCounterHTML);
                $element.next('.bbai-char-counter').addClass('bbai-char-counter--updating');
                setTimeout(function() {
                    $element.next('.bbai-char-counter').removeClass('bbai-char-counter--updating');
                }, 300);
            }
        }
    };

    // Initialize character counters
    $(document).ready(function() {
        if (typeof window.bbaiCharCounter !== 'undefined') {
            window.bbaiCharCounter.init();
        }

        $(document).on('bbai:alttext:updated', function() {
            if (typeof window.bbaiCharCounter !== 'undefined') {
                window.bbaiCharCounter.init();
            }
        });
    });
});
