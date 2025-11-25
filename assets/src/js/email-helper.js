/**
 * Email Helper Utility for AltText AI
 * Shared function for sending welcome emails to backend
 * Updated to use apiClient helper
 */

// Use postJson from apiClient if available, otherwise fallback
let postJson;
if (typeof window.postJson === 'function') {
    postJson = window.postJson;
} else if (typeof window.bbai_ajax?.api_url !== 'undefined') {
    // Fallback implementation if apiClient not loaded
    postJson = function(path, body) {
        const apiUrl = window.bbai_ajax.api_url;
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
} else {
    postJson = function() {
        return Promise.reject(new Error('API client not available'));
    };
}

/**
 * Send welcome email via backend API
 * @param {string} email - User email address
 * @returns {Promise<Object>} Response from backend
 */
async function sendWelcomeEmail(email) {
    if (!email || !email.includes('@')) {
        throw new Error('Invalid email address');
    }

    try {
        // Use /email/dashboard endpoint for welcome emails
        return await postJson('/email/dashboard', {
            email: email,
            plugin: 'alt-text-ai',
            source: 'welcome'
        });
    } catch (error) {
        // Log error but don't expose internal details to user
        console.error('[AltText AI] Welcome email error:', error);
        throw error;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { sendWelcomeEmail };
}

// Make available globally
window.sendWelcomeEmail = sendWelcomeEmail;

