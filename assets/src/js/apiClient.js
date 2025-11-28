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
    const apiUrl = opttiApi?.baseUrl || window.bbai_ajax?.api_url;
    
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
            // Check for subscription error (402 Payment Required)
            if (response.status === 402) {
                return response.json()
                    .then(err => {
                        // Return subscription error object for handling by caller
                        return { 
                            ok: false, 
                            subscriptionError: err.error || err.code || 'subscription_required',
                            message: err.message || 'Subscription required',
                            status: 402
                        };
                    })
                    .catch(() => {
                        return { 
                            ok: false, 
                            subscriptionError: 'subscription_required',
                            message: 'Subscription required',
                            status: 402
                        };
                    });
            }
            
            // Try to parse error response
            return response.json()
                .then(err => {
                    // Check for NO_ACCESS error code
                    if (err.code === 'NO_ACCESS') {
                        // Determine error context
                        const credits = err.credits !== undefined ? parseInt(err.credits, 10) : null;
                        const subscriptionExpired = err.subscription_expired === true;
                        
                        let errorCode = 'no_access';
                        if (subscriptionExpired) {
                            errorCode = 'subscription_expired';
                        } else if (credits !== null && credits === 0) {
                            errorCode = 'out_of_credits';
                        }
                        
                        // Return NO_ACCESS error for handling by caller
                        return {
                            ok: false,
                            noAccess: true,
                            code: 'NO_ACCESS',
                            errorCode: errorCode,
                            credits: credits,
                            subscriptionExpired: subscriptionExpired,
                            message: err.message || 'Access denied',
                            status: response.status
                        };
                    }
                    
                    // Check for quota exceeded error
                    if (err.error === 'quota_exceeded') {
                        if (typeof showUpgradeModal === 'function') {
                            showUpgradeModal();
                        } else if (typeof window.showUpgradeModal === 'function') {
                            window.showUpgradeModal();
                        }
                        return Promise.reject(new Error(err.error || err.message || `HTTP error! status: ${response.status}`));
                    }
                    return Promise.reject(new Error(err.error || err.message || `HTTP error! status: ${response.status}`));
                })
                .catch(() => Promise.reject(new Error(`HTTP error! status: ${response.status}`)));
        }
        return response.json().then(data => {
            // Check for NO_ACCESS error code in response
            if (data.code === 'NO_ACCESS' || (data.ok === false && data.code === 'NO_ACCESS')) {
                // Determine error context
                const credits = data.credits !== undefined ? parseInt(data.credits, 10) : null;
                const subscriptionExpired = data.subscription_expired === true;
                
                let errorCode = 'no_access';
                if (subscriptionExpired) {
                    errorCode = 'subscription_expired';
                } else if (credits !== null && credits === 0) {
                    errorCode = 'out_of_credits';
                }
                
                // Return NO_ACCESS error for handling by caller
                return {
                    ok: false,
                    noAccess: true,
                    code: 'NO_ACCESS',
                    errorCode: errorCode,
                    credits: credits,
                    subscriptionExpired: subscriptionExpired,
                    message: data.message || 'Access denied'
                };
            }
            
            // Check for quota exceeded in successful response
            if (data.error === 'quota_exceeded') {
                if (typeof showUpgradeModal === 'function') {
                    showUpgradeModal();
                } else if (typeof window.showUpgradeModal === 'function') {
                    window.showUpgradeModal();
                }
            }
            return data;
        });
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

