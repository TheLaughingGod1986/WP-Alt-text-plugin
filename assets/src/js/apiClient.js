/**
 * API Client Helper
 * Provides a simple interface for making POST requests to the backend API
 */

/**
 * Send a POST request with JSON body to the backend API
 * @param {string} path - API endpoint path (e.g., '/email/plugin-signup')
 * @param {object} body - Request body object
 * @returns {Promise<object>} Response data
 */
function postJson(path, body) {
    const apiUrl = window.bbai_ajax?.api_url;
    
    if (!apiUrl) {
        return Promise.reject(new Error('API URL not configured'));
    }
    
    // Ensure path starts with /
    const endpoint = path.startsWith('/') ? path : '/' + path;
    const url = `${apiUrl}${endpoint}`;
    
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    })
    .then(response => {
        if (!response.ok) {
            // Try to parse error response
            return response.json()
                .then(err => Promise.reject(new Error(err.error || err.message || `HTTP error! status: ${response.status}`)))
                .catch(() => Promise.reject(new Error(`HTTP error! status: ${response.status}`)));
        }
        return response.json();
    })
    .catch(error => {
        // Log error for debugging
        console.error('[API Client] Request failed:', error);
        throw error;
    });
}

// Make available globally for non-module usage
if (typeof window !== 'undefined') {
    window.postJson = postJson;
}

