/**
 * Optti Analytics Module
 * Lightweight event logging helper for plugin analytics
 * All failures are silent and do not break plugin functionality
 * 
 * This module now uses the batched logging from optti-api.js
 * Since scripts are loaded as regular scripts (not ES modules),
 * we rely on window.logEvent being set by optti-api.js
 */

// optti-api.js is loaded as a dependency and sets window.logEvent
// This file is kept for backward compatibility and documentation
// The actual logEvent function is defined in optti-api.js with batching support

