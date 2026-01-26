/**
 * Dashboard Utilities
 * jQuery wrapper, DOM caching, and helper functions
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

// Debug mode check (define early so it can be used in functions)
var alttextaiDebug = (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false;

/**
 * Safe jQuery wrapper that checks for jQuery availability
 */
var bbaiRunWithJQuery = (function() {
    var warned = false;
    return function(callback) {
        var jq = window.jQuery || window.$;
        if (typeof jq !== 'function') {
            if (!warned) {
                console.warn('[AltText AI] jQuery not found; dashboard scripts not run.');
                warned = true;
            }
            return;
        }
        callback(jq);
    };
})();

/**
 * DOM element cache for performance
 */
var $cachedElements = {};

function getCachedElement(selector) {
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return null;

    if (!$cachedElements[selector]) {
        $cachedElements[selector] = $(selector);
    }
    return $cachedElements[selector];
}

/**
 * Clear element cache (useful after AJAX updates)
 */
function clearElementCache() {
    $cachedElements = {};
}

// Global app object to avoid collisions
var bbaiApp = bbaiApp || {};

// Export utilities
window.bbaiRunWithJQuery = bbaiRunWithJQuery;
window.getCachedElement = getCachedElement;
window.clearElementCache = clearElementCache;
