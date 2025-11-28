/**
 * Optti Shared API Module
 * Provides standardized API functions for all Optti plugins
 */

// Analytics event queue for batching
let eventQueue = [];
let flushTimer = null;
const BATCH_SIZE = 10;
const FLUSH_DELAY = 5000; // 5 seconds

/**
 * Enhanced opttiPost with JWT token support
 */
export async function opttiPost (path, payload, customHeaders = {}) {
    try {
        const headers = {
            "Content-Type": "application/json",
            ...customHeaders
        };
        
        // Include JWT token if available
        if (typeof opttiApi !== 'undefined' && opttiApi.token) {
            headers["Authorization"] = `Bearer ${opttiApi.token}`;
        }
        
        const res = await fetch(`${opttiApi.baseUrl}${path}`, {
            method: "POST",
            headers: headers,
            body: JSON.stringify(payload)
        });
        return await res.json();
    } catch (err) {
        console.error("Optti API error:", err);
        return { ok: false };
    }
}

/**
 * Queue an analytics event for batched sending
 */
export function logEvent(event, payload = {}) {
    // Add event to queue
    eventQueue.push({
        event,
        payload,
        plugin: opttiApi.plugin,
        site: opttiApi.site,
        ts: Date.now()
    });
    
    // Flush immediately if batch size reached
    if (eventQueue.length >= BATCH_SIZE) {
        sendAnalyticsBatch();
    } else {
        // Schedule flush after delay
        scheduleFlush();
    }
}

/**
 * Schedule a delayed flush of the event queue
 */
function scheduleFlush() {
    // Clear existing timer
    if (flushTimer) {
        clearTimeout(flushTimer);
    }
    
    // Schedule new flush
    flushTimer = setTimeout(() => {
        if (eventQueue.length > 0) {
            sendAnalyticsBatch();
        }
    }, FLUSH_DELAY);
}

/**
 * Send batched analytics events to backend
 */
export async function sendAnalyticsBatch() {
    // Clear flush timer
    if (flushTimer) {
        clearTimeout(flushTimer);
        flushTimer = null;
    }
    
    // If queue is empty, nothing to send
    if (eventQueue.length === 0) {
        return;
    }
    
    // Copy current queue and clear it
    const eventsToSend = [...eventQueue];
    eventQueue = [];
    
    try {
        const payload = {
            events: eventsToSend,
            plugin: opttiApi.plugin,
            site: opttiApi.site
        };
        
        // Send to /analytics/events endpoint (plural, matches framework)
        const result = await opttiPost("/analytics/events", payload);
        
        if (!result || !result.ok) {
            // Silently fail - don't break plugin functionality
            console.warn("Analytics batch send failed (non-critical)");
        }
    } catch (err) {
        // Silently fail - analytics should never break plugin
        console.warn("Analytics batch send error (non-critical):", err);
    }
}

/**
 * Flush any pending events immediately (useful on page unload)
 */
export function flushAnalytics() {
    if (eventQueue.length > 0) {
        sendAnalyticsBatch();
    }
}

export async function sendPluginSignup({ email, plugin = opttiApi.plugin, site = opttiApi.site }) {
    return opttiPost("/email/plugin-signup", { email, plugin, site });
}

// For backward compatibility, expose globally
if (typeof window !== 'undefined') {
    window.opttiPost = opttiPost;
    window.sendPluginSignup = sendPluginSignup;
    window.logEvent = logEvent;
    window.flushAnalytics = flushAnalytics;
    
    // Flush analytics on page unload
    window.addEventListener('beforeunload', flushAnalytics);
}

