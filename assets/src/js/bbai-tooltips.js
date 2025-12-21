/**
 * BeepBeep AI - Tooltip System
 * Accessible, lightweight tooltips for user guidance
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since 4.3.0
 */

(function($) {
    'use strict';

    /**
     * Initialize tooltips on elements with data-bbai-tooltip attribute
     */
    function initTooltips() {
        // Remove existing tooltips
        $('.bbai-tooltip').remove();

        // Find all elements with tooltip data
        $('[data-bbai-tooltip]').each(function() {
            var $element = $(this);
            var tooltipText = $element.attr('data-bbai-tooltip');
            var position = $element.attr('data-bbai-tooltip-position') || 'top';

            if (!tooltipText) return;

            // Add aria-label for accessibility
            if (!$element.attr('aria-label')) {
                $element.attr('aria-label', tooltipText);
            }

            // Add class for styling
            if (!$element.hasClass('bbai-has-tooltip')) {
                $element.addClass('bbai-has-tooltip');
            }

            // Create tooltip element
            var $tooltip = $('<div class="bbai-tooltip bbai-tooltip--' + position + '" role="tooltip">')
                .text(tooltipText)
                .hide();

            // Append to body
            $('body').append($tooltip);

            // Show on hover
            $element.on('mouseenter focus', function(e) {
                var offset = $element.offset();
                var elementWidth = $element.outerWidth();
                var elementHeight = $element.outerHeight();
                var tooltipWidth = $tooltip.outerWidth();
                var tooltipHeight = $tooltip.outerHeight();

                var top, left;

                switch (position) {
                    case 'bottom':
                        top = offset.top + elementHeight + 8;
                        left = offset.left + (elementWidth / 2) - (tooltipWidth / 2);
                        break;
                    case 'left':
                        top = offset.top + (elementHeight / 2) - (tooltipHeight / 2);
                        left = offset.left - tooltipWidth - 8;
                        break;
                    case 'right':
                        top = offset.top + (elementHeight / 2) - (tooltipHeight / 2);
                        left = offset.left + elementWidth + 8;
                        break;
                    default: // top
                        top = offset.top - tooltipHeight - 8;
                        left = offset.left + (elementWidth / 2) - (tooltipWidth / 2);
                }

                $tooltip.css({
                    top: top + 'px',
                    left: left + 'px'
                }).fadeIn(150);
            });

            // Hide on mouse leave or blur
            $element.on('mouseleave blur', function() {
                $tooltip.fadeOut(150);
            });
        });
    }

    /**
     * Add tooltip to element programmatically
     */
    function addTooltip(selector, text, position) {
        position = position || 'top';
        $(selector).attr({
            'data-bbai-tooltip': text,
            'data-bbai-tooltip-position': position
        });
        initTooltips();
    }

    /**
     * Remove tooltip from element
     */
    function removeTooltip(selector) {
        $(selector).removeAttr('data-bbai-tooltip data-bbai-tooltip-position')
                   .removeClass('bbai-has-tooltip')
                   .off('mouseenter mouseleave focus blur');
    }

    // Initialize on document ready
    $(document).ready(function() {
        initTooltips();

        // Re-initialize after AJAX updates
        $(document).on('bbai:updated', initTooltips);
    });

    // Export API
    window.bbaiTooltips = {
        init: initTooltips,
        add: addTooltip,
        remove: removeTooltip
    };

})(jQuery);
