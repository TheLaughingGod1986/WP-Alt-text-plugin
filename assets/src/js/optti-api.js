/**
 * Optti Shared API Module
 * Provides standardized API functions for all Optti plugins
 */

export async function opttiPost (path, payload) {
    try {
        const res = await fetch(`${opttiApi.baseUrl}${path}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
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

