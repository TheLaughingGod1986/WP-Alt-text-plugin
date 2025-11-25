/**
 * Email Events Handler
 * Handles plugin signup emails, upgrade welcome emails, and waitlist submissions
 */

// Use postJson from apiClient (loaded globally or via window)
let postJson;
if (typeof window.postJson === 'function') {
    postJson = window.postJson;
} else {
    // Fallback implementation if apiClient not loaded
    postJson = function(path, body) {
        const apiUrl = window.bbai_ajax?.api_url;
        if (!apiUrl) {
            return Promise.reject(new Error('API URL not configured'));
        }
        const endpoint = path.startsWith('/') ? path : '/' + path;
        return fetch(`${apiUrl}${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(new Error(err.error || `HTTP error! status: ${response.status}`)));
            }
            return response.json();
        });
    };
}

/**
 * Send plugin signup email to backend
 * @param {string} email - User email address
 * @param {string} siteUrl - Site URL
 * @param {string} pluginName - Plugin name (default: 'alt-text')
 * @returns {Promise<object>} Response from backend
 */
function sendPluginSignupEmail(email, siteUrl, pluginName = 'alt-text') {
    if (!email || !email.includes('@')) {
        return Promise.reject(new Error('Invalid email address'));
    }
    
    return postJson('/email/plugin-signup', {
        email: email,
        siteUrl: siteUrl || window.location.origin,
        pluginName: pluginName
    });
}

/**
 * Send dashboard welcome email after upgrade
 * @param {string} email - User email address
 * @param {string} plan - Plan name (e.g., 'pro', 'agency')
 * @returns {Promise<object>} Response from backend
 */
function sendDashboardWelcomeEmail(email, plan) {
    if (!email || !email.includes('@')) {
        return Promise.reject(new Error('Invalid email address'));
    }
    
    return postJson('/email/dashboard-welcome', {
        email: email,
        plan: plan || 'pro',
        source: 'upgrade'
    });
}

/**
 * Submit email to waitlist
 * @param {string} email - User email address
 * @param {string} plugin - Plugin identifier (default: 'alt-text')
 * @param {string} source - Source of signup (default: 'wp-plugin-settings')
 * @returns {Promise<object>} Response from backend
 */
function submitToWaitlist(email, plugin = 'alt-text', source = 'wp-plugin-settings') {
    if (!email || !email.includes('@')) {
        return Promise.reject(new Error('Invalid email address'));
    }
    
    return postJson('/waitlist/submit', {
        email: email,
        plugin: plugin,
        source: source
    });
}

// Make available globally for non-module usage
if (typeof window !== 'undefined') {
    window.sendPluginSignupEmail = sendPluginSignupEmail;
    window.sendDashboardWelcomeEmail = sendDashboardWelcomeEmail;
    window.submitToWaitlist = submitToWaitlist;
}

