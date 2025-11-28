/**
 * Optti Billing API Module
 * Provides standardized billing functions for all Optti plugins
 */

export async function createCheckoutSession({ email, plugin = opttiApi.plugin, priceId }) {
    try {
        const res = await fetch(`${opttiApi.baseUrl}/billing/create-checkout`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, plugin, priceId })
        });
        const data = await res.json();
        return data;
    } catch (err) {
        console.error("Checkout session failed:", err);
        return { ok: false };
    }
}

export async function createPortalSession({ email }) {
    try {
        const res = await fetch(`${opttiApi.baseUrl}/billing/create-portal`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email })
        });
        const data = await res.json();
        return data;
    } catch (err) {
        console.error("Portal session failed:", err);
        return { ok: false };
    }
}

export async function fetchSubscriptions({ email }) {
    try {
        const res = await fetch(`${opttiApi.baseUrl}/billing/subscriptions`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email })
        });
        const data = await res.json();
        return data;
    } catch (err) {
        console.error("Get subscriptions failed:", err);
        return { ok: false };
    }
}

// Global fallback
if (typeof window !== 'undefined') {
    window.opttiBilling = {
        createCheckoutSession,
        createPortalSession,
        fetchSubscriptions
    };
}

