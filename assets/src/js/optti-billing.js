/**
 * Optti Billing API Module
 * Provides standardized billing functions for all Optti plugins
 * Browser-compatible version (IIFE)
 */

(function() {
    'use strict';

    async function createCheckoutSession({ email, plugin, priceId }) {
        try {
            if (!window.opttiApi || !window.opttiApi.baseUrl) {
                console.error('[Optti Billing] opttiApi.baseUrl not configured');
                return { ok: false, message: 'API not configured' };
            }
            
            const res = await fetch(`${window.opttiApi.baseUrl}/billing/create-checkout`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ 
                    email: email || '', 
                    plugin: plugin || window.opttiApi.plugin || 'beepbeep-ai', 
                    priceId: priceId 
                })
            });
            const data = await res.json();
            return data;
        } catch (err) {
            console.error("[Optti Billing] Checkout session failed:", err);
            return { ok: false, message: err.message };
        }
    }

    async function createPortalSession({ email }) {
        try {
            if (!window.opttiApi || !window.opttiApi.baseUrl) {
                console.error('[Optti Billing] opttiApi.baseUrl not configured');
                return { ok: false, message: 'API not configured' };
            }
            
            const res = await fetch(`${window.opttiApi.baseUrl}/billing/create-portal`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email: email || '' })
            });
            const data = await res.json();
            return data;
        } catch (err) {
            console.error("[Optti Billing] Portal session failed:", err);
            return { ok: false, message: err.message };
        }
    }

    async function fetchSubscriptions({ email }) {
        try {
            if (!window.opttiApi || !window.opttiApi.baseUrl) {
                console.error('[Optti Billing] opttiApi.baseUrl not configured');
                return { ok: false, message: 'API not configured' };
            }
            
            const res = await fetch(`${window.opttiApi.baseUrl}/billing/subscriptions`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email: email || '' })
            });
            const data = await res.json();
            return data;
        } catch (err) {
            console.error("[Optti Billing] Get subscriptions failed:", err);
            return { ok: false, message: err.message };
        }
    }

    // Expose to global window object
    window.opttiBilling = {
        createCheckoutSession: createCheckoutSession,
        createPortalSession: createPortalSession,
        fetchSubscriptions: fetchSubscriptions
    };

    console.log('[Optti Billing] Module loaded');
})();
