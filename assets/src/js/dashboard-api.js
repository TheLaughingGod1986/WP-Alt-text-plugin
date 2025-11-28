/**
 * Optti Dashboard API Module
 * Centralizes all dashboard API calls
 */

export async function getDashboard() {
    try {
        const token = opttiApi?.token ?? '';
        const headers = {
            'Content-Type': 'application/json',
        };
        
        // Add Authorization header if token is available
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const res = await fetch(`${opttiApi.baseUrl}/dashboard`, {
            method: 'GET',
            headers: headers
        });
        
        const data = await res.json();
        
        // Check if response is successful
        if (res.ok && data.success) {
            return {
                ok: true,
                installations: data.data?.installations ?? [],
                subscription: data.data?.subscription ?? null,
                usage: data.data?.usage ?? {
                    monthlyImages: 0,
                    dailyImages: 0,
                    totalImages: 0
                }
            };
        }
        
        // If 401, user needs to log in
        if (res.status === 401) {
            return {
                ok: false,
                error: 'unauthorized',
                message: 'Please log in',
                installations: [],
                subscription: null,
                usage: {
                    monthlyImages: 0,
                    dailyImages: 0,
                    totalImages: 0
                }
            };
        }
        
        // Other errors
        return {
            ok: false,
            error: 'api_error',
            message: data.message || 'Unable to load dashboard',
            installations: [],
            subscription: null,
            usage: {
                monthlyImages: 0,
                dailyImages: 0,
                totalImages: 0
            }
        };
    } catch (err) {
        console.error('Dashboard API error:', err);
        return {
            ok: false,
            error: 'network_error',
            message: 'Unable to load dashboard. Please check your connection.',
            installations: [],
            subscription: null,
            usage: {
                monthlyImages: 0,
                dailyImages: 0,
                totalImages: 0
            }
        };
    }
}

/**
 * Get dashboard charts data from backend
 * Returns all chart data in a single request for instant loading
 */
export async function getDashboardCharts() {
    try {
        const token = opttiApi?.token ?? '';
        const headers = {
            'Content-Type': 'application/json',
        };
        
        // Add Authorization header if token is available
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Use WordPress REST API endpoint
        const restUrl = opttiApi?.restUrl ?? '/wp-json/bbai/v1/dashboard/charts';
        const res = await fetch(restUrl, {
            method: 'GET',
            headers: headers
        });
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        
        // Return structured data matching backend response
        return {
            ok: true,
            coverage: data.coverage ?? {
                total: 0,
                with_alt: 0,
                missing: 0,
                percentage: 0
            },
            usage: data.usage ?? {
                used: 0,
                limit: 0,
                remaining: 0,
                percentage: 0
            },
            time_saved: data.time_saved ?? 0,
            quality: data.quality ?? {
                average: 0,
                total_reviewed: 0
            }
        };
    } catch (err) {
        console.error('Dashboard charts API error:', err);
        // Return empty data structure on error
        return {
            ok: false,
            error: 'network_error',
            message: 'Unable to load dashboard charts. Please check your connection.',
            coverage: {
                total: 0,
                with_alt: 0,
                missing: 0,
                percentage: 0
            },
            usage: {
                used: 0,
                limit: 0,
                remaining: 0,
                percentage: 0
            },
            time_saved: 0,
            quality: {
                average: 0,
                total_reviewed: 0
            }
        };
    }
}

// For backward compatibility, expose globally
if (typeof window !== 'undefined') {
    window.getDashboard = getDashboard;
    window.getDashboardCharts = getDashboardCharts;
}

