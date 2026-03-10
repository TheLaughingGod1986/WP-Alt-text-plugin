/**
 * BeepBeep AI - Safe Logger
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 * @since 4.4.2
 */

(function(window) {
    'use strict';

    var noop = function() {};
    var consoleRef = window && window.console ? window.console : null;

    function isEnabled() {
        var localizedDebug = !!(
            window.bbaiConfig &&
            window.bbaiConfig.debug === true
        );

        return window.bbaiDebug === true ||
            window.BBAI_DEBUG === true ||
            window.alttextaiDebug === true ||
            localizedDebug;
    }

    function emit(level, argsLike) {
        if (!isEnabled() || !consoleRef) {
            return;
        }

        var writer = consoleRef[level];
        if (typeof writer !== 'function') {
            return;
        }

        var args = Array.prototype.slice.call(argsLike);
        writer.apply(consoleRef, args);
    }

    var logger = {
        enabled: false,
        refresh: function() {
            this.enabled = isEnabled();
            return this.enabled;
        },
        log: function() {
            emit('log', arguments);
        },
        info: function() {
            emit('info', arguments);
        },
        warn: function() {
            emit('warn', arguments);
        },
        error: function() {
            emit('error', arguments);
        },
        debug: function() {
            emit('debug', arguments);
        }
    };

    logger.refresh();

    window.BBAI_LOG = logger;
    window.bbaiLogger = logger;
    window.bbaiLog = function() {
        logger.log.apply(logger, arguments);
    };
    window.bbaiWarn = function() {
        logger.warn.apply(logger, arguments);
    };
    window.bbaiError = function() {
        logger.error.apply(logger, arguments);
    };

    window.BBAI_LOG_NOOP = {
        log: noop,
        info: noop,
        warn: noop,
        error: noop,
        debug: noop
    };
})(window);
