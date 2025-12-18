/**
 * Optti Shared API Module
 * Provides standardized API functions for all Optti plugins
 */

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

export async function sendPluginSignup({ email, plugin = opttiApi.plugin, site = opttiApi.site }) {
    return opttiPost("/email/plugin-signup", { email, plugin, site });
}

// For backward compatibility, expose globally
if (typeof window !== 'undefined') {
    window.opttiPost = opttiPost;
    window.sendPluginSignup = sendPluginSignup;
}

