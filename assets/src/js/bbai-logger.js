/**
 * BeepBeep AI - Debug Logger
 * Centralized logging utility that respects debug mode
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since 4.3.0
 */

(function(window) {
    'use strict';

    /**
     * Check if debug mode is enabled
     */
    function isDebugEnabled() {
        return window.alttextaiDebug === true ||
               window.BBAI_DEBUG === true ||
               (window.location && window.location.search && window.location.search.indexOf('bbai_debug=1') !== -1);
    }

    /**
     * Logger class
     */
    var BBaiLogger = {
        /**
         * Log debug message (only in debug mode)
         */
        log: function() {
            if (isDebugEnabled() && console && console.log) {
                console.log.apply(console, arguments);
            }
        },

        /**
         * Log info message (only in debug mode)
         */
        info: function() {
            if (isDebugEnabled() && console && console.info) {
                console.info.apply(console, arguments);
            }
        },

        /**
         * Log warning (always shown)
         */
        warn: function() {
            if (console && console.warn) {
                console.warn.apply(console, arguments);
            }
        },

        /**
         * Log error (always shown)
         */
        error: function() {
            if (console && console.error) {
                console.error.apply(console, arguments);
            }
        },

        /**
         * Log with custom prefix
         */
        logWithPrefix: function(prefix) {
            if (!isDebugEnabled()) return;

            var args = Array.prototype.slice.call(arguments, 1);
            args.unshift('[' + prefix + ']');

            if (console && console.log) {
                console.log.apply(console, args);
            }
        },

        /**
         * Group logs (only in debug mode)
         */
        group: function(label) {
            if (isDebugEnabled() && console && console.group) {
                console.group(label);
            }
        },

        /**
         * End group
         */
        groupEnd: function() {
            if (isDebugEnabled() && console && console.groupEnd) {
                console.groupEnd();
            }
        },

        /**
         * Log table (only in debug mode)
         */
        table: function(data) {
            if (isDebugEnabled() && console && console.table) {
                console.table(data);
            }
        },

        /**
         * Performance timing
         */
        time: function(label) {
            if (isDebugEnabled() && console && console.time) {
                console.time(label);
            }
        },

        /**
         * End performance timing
         */
        timeEnd: function(label) {
            if (isDebugEnabled() && console && console.timeEnd) {
                console.timeEnd(label);
            }
        }
    };

    // Export to global scope
    window.bbaiLogger = BBaiLogger;

    // Also create shorthand
    window.bbaiLog = BBaiLogger.log;
    window.bbaiWarn = BBaiLogger.warn;
    window.bbaiError = BBaiLogger.error;

})(window);
