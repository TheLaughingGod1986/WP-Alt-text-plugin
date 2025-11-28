/**
 * Optti Dashboard Render Module
 * Fetches and renders dashboard charts with instant loading
 */

import { getDashboardCharts } from './dashboard-api';

let pollingInterval = null;
let isPolling = false;

/**
 * Check if data exists and has meaningful values
 */
function hasData(data) {
    if (!data || typeof data !== 'object') {
        return false;
    }
    
    // Check coverage
    if (data.coverage && data.coverage.total > 0) {
        return true;
    }
    
    // Check usage
    if (data.usage && data.usage.limit > 0) {
        return true;
    }
    
    // Check quality
    if (data.quality && data.quality.total_reviewed > 0) {
        return true;
    }
    
    return false;
}

/**
 * Render coverage chart
 */
function renderCoverageChart(data, container) {
    const coverage = data.coverage || {};
    const total = coverage.total || 0;
    const withAlt = coverage.with_alt || 0;
    const missing = coverage.missing || 0;
    const percentage = coverage.percentage || 0;
    
    if (total === 0) {
        container.innerHTML = `
            <div class="optti-chart-container">
                <h3 class="optti-chart-title">ALT Text Coverage</h3>
                <div class="optti-empty-state">
                    <p>No data yet</p>
                </div>
            </div>
        `;
        return;
    }
    
    const circumference = 2 * Math.PI * 45; // radius = 45
    const offset = circumference - (percentage / 100) * circumference;
    
    container.innerHTML = `
        <div class="optti-chart-container">
            <h3 class="optti-chart-title">ALT Text Coverage</h3>
            <div class="optti-coverage-chart">
                <svg class="optti-coverage-svg" viewBox="0 0 100 100">
                    <circle class="optti-coverage-bg" cx="50" cy="50" r="45" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                    <circle 
                        class="optti-coverage-progress" 
                        cx="50" 
                        cy="50" 
                        r="45" 
                        fill="none" 
                        stroke="#14b8a6" 
                        stroke-width="8"
                        stroke-dasharray="${circumference}"
                        stroke-dashoffset="${offset}"
                        transform="rotate(-90 50 50)"
                    />
                </svg>
                <div class="optti-coverage-percentage">${percentage.toFixed(1)}%</div>
            </div>
            <div class="optti-chart-stats">
                <div class="optti-stat-item" data-tooltip="Total number of images in your media library">
                    <span class="optti-stat-label">Total Images</span>
                    <span class="optti-stat-value">${total.toLocaleString()}</span>
                </div>
                <div class="optti-stat-item" data-tooltip="Images that have ALT text">
                    <span class="optti-stat-label">With ALT</span>
                    <span class="optti-stat-value">${withAlt.toLocaleString()}</span>
                </div>
                <div class="optti-stat-item" data-tooltip="Images missing ALT text">
                    <span class="optti-stat-label">Missing</span>
                    <span class="optti-stat-value">${missing.toLocaleString()}</span>
                </div>
            </div>
        </div>
    `;
}

/**
 * Render usage chart
 */
function renderUsageChart(data, container) {
    const usage = data.usage || {};
    const used = usage.used || 0;
    const limit = usage.limit || 0;
    const remaining = usage.remaining || 0;
    const percentage = usage.percentage || 0;
    
    if (limit === 0) {
        container.innerHTML = `
            <div class="optti-chart-container">
                <h3 class="optti-chart-title">Usage</h3>
                <div class="optti-empty-state">
                    <p>No data yet</p>
                </div>
            </div>
        `;
        return;
    }
    
    const percentageWidth = Math.min(100, Math.max(0, percentage));
    
    container.innerHTML = `
        <div class="optti-chart-container">
            <h3 class="optti-chart-title">Usage</h3>
            <div class="optti-usage-chart">
                <div class="optti-usage-bar">
                    <div class="optti-usage-bar-bg">
                        <div class="optti-usage-bar-fill" style="width: ${percentageWidth}%"></div>
                    </div>
                </div>
                <div class="optti-usage-percentage">${percentage.toFixed(1)}%</div>
            </div>
            <div class="optti-chart-stats">
                <div class="optti-stat-item" data-tooltip="Number of generations used this month">
                    <span class="optti-stat-label">Used</span>
                    <span class="optti-stat-value">${used.toLocaleString()}</span>
                </div>
                <div class="optti-stat-item" data-tooltip="Total monthly limit">
                    <span class="optti-stat-label">Limit</span>
                    <span class="optti-stat-value">${limit.toLocaleString()}</span>
                </div>
                <div class="optti-stat-item" data-tooltip="Remaining generations this month">
                    <span class="optti-stat-label">Remaining</span>
                    <span class="optti-stat-value">${remaining.toLocaleString()}</span>
                </div>
            </div>
        </div>
    `;
}

/**
 * Render metrics cards
 */
function renderMetricsCards(data, container) {
    const timeSaved = data.time_saved || 0;
    const quality = data.quality || {};
    const averageQuality = quality.average || 0;
    const totalReviewed = quality.total_reviewed || 0;
    
    let cardsHTML = '<div class="optti-metrics-grid">';
    
    // Time Saved Card
    cardsHTML += `
        <div class="optti-metric-card">
            <div class="optti-metric-icon">⏱</div>
            <div class="optti-metric-value" data-tooltip="Estimated time saved vs manual optimization (2.5 min per image)">${timeSaved.toFixed(1)} hrs</div>
            <div class="optti-metric-label">Time Saved</div>
        </div>
    `;
    
    // Quality Card
    if (totalReviewed > 0) {
        cardsHTML += `
            <div class="optti-metric-card">
                <div class="optti-metric-icon">⭐</div>
                <div class="optti-metric-value" data-tooltip="Average quality score of reviewed ALT text (${totalReviewed} reviewed)">${averageQuality.toFixed(1)}</div>
                <div class="optti-metric-label">Avg Quality</div>
            </div>
        `;
    } else {
        cardsHTML += `
            <div class="optti-metric-card">
                <div class="optti-metric-icon">⭐</div>
                <div class="optti-metric-value" data-tooltip="No quality reviews available yet">—</div>
                <div class="optti-metric-label">Avg Quality</div>
            </div>
        `;
    }
    
    cardsHTML += '</div>';
    container.innerHTML = cardsHTML;
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        const tooltipText = element.getAttribute('data-tooltip');
        if (!tooltipText) return;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'optti-tooltip';
        tooltip.textContent = tooltipText;
        tooltip.setAttribute('role', 'tooltip');
        tooltip.setAttribute('aria-hidden', 'true');
        document.body.appendChild(tooltip);
        
        // Show tooltip on hover/focus
        const showTooltip = (e) => {
            const rect = element.getBoundingClientRect();
            tooltip.style.display = 'block';
            
            // Position tooltip above element by default
            let top = rect.top - tooltip.offsetHeight - 8;
            let left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2);
            
            // Adjust if tooltip goes off screen
            if (top < 0) {
                top = rect.bottom + 8; // Show below instead
            }
            if (left < 0) {
                left = 8;
            } else if (left + tooltip.offsetWidth > window.innerWidth) {
                left = window.innerWidth - tooltip.offsetWidth - 8;
            }
            
            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;
            tooltip.setAttribute('aria-hidden', 'false');
        };
        
        const hideTooltip = () => {
            tooltip.style.display = 'none';
            tooltip.setAttribute('aria-hidden', 'true');
        };
        
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('focus', showTooltip);
        element.addEventListener('blur', hideTooltip);
        
        // Store tooltip reference for cleanup
        element._tooltip = tooltip;
    });
}

/**
 * Clean up tooltips
 */
function cleanupTooltips() {
    const tooltips = document.querySelectorAll('.optti-tooltip');
    tooltips.forEach(tooltip => tooltip.remove());
}

/**
 * Update usage data (for polling)
 */
async function updateUsageData() {
    if (isPolling) return; // Prevent concurrent requests
    isPolling = true;
    
    try {
        const result = await getDashboardCharts();
        if (result.ok && result.usage) {
            // Update only usage-related charts
            const usageContainer = document.getElementById('optti-usage-chart-container');
            if (usageContainer) {
                renderUsageChart(result, usageContainer);
                initTooltips();
            }
        }
    } catch (error) {
        console.error('Error updating usage data:', error);
        // Silent failure - don't break UI
    } finally {
        isPolling = false;
    }
}

/**
 * Start polling for usage updates
 */
function startPolling() {
    // Clear any existing interval
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    // Poll every 60 seconds
    pollingInterval = setInterval(() => {
        // Only poll if tab is visible (optional optimization)
        if (document.visibilityState === 'visible') {
            updateUsageData();
        }
    }, 60000); // 60 seconds
}

/**
 * Stop polling
 */
function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

/**
 * Main dashboard render function
 */
async function renderDashboard() {
    const container = document.getElementById('optti-dashboard-root');
    if (!container) {
        return; // Dashboard container not found, exit gracefully
    }
    
    // Log dashboard load event
    if (typeof window.logEvent === 'function') {
        window.logEvent('dashboard_loaded', {});
    }
    
    try {
        const result = await getDashboardCharts();
        
        if (!result.ok) {
            // Show error message
            container.innerHTML = `
                <div class="optti-dashboard-error">
                    ${result.message || 'Unable to load dashboard. Please check your connection.'}
                </div>
            `;
            return;
        }
        
        // Check if we have data
        if (!hasData(result)) {
            container.innerHTML = `
                <div class="optti-dashboard-panel">
                    <div class="optti-empty-state">
                        <p>No data yet</p>
                        <p class="optti-empty-state-subtitle">Start generating ALT text to see your dashboard metrics.</p>
                    </div>
                </div>
            `;
            return;
        }
        
        // Build dashboard HTML with charts
        let dashboardHTML = '<div class="optti-dashboard-panel">';
        
        // Charts grid
        dashboardHTML += '<div class="optti-charts-grid">';
        
        // Coverage chart
        dashboardHTML += '<div id="optti-coverage-chart-container"></div>';
        
        // Usage chart
        dashboardHTML += '<div id="optti-usage-chart-container"></div>';
        
        dashboardHTML += '</div>';
        
        // Metrics cards
        dashboardHTML += '<div id="optti-metrics-container"></div>';
        
        dashboardHTML += '</div>';
        
        // Inject HTML
        container.innerHTML = dashboardHTML;
        
        // Render charts
        const coverageContainer = document.getElementById('optti-coverage-chart-container');
        const usageContainer = document.getElementById('optti-usage-chart-container');
        const metricsContainer = document.getElementById('optti-metrics-container');
        
        if (coverageContainer) {
            renderCoverageChart(result, coverageContainer);
        }
        if (usageContainer) {
            renderUsageChart(result, usageContainer);
        }
        if (metricsContainer) {
            renderMetricsCards(result, metricsContainer);
        }
        
        // Initialize tooltips
        initTooltips();
        
        // Start polling for usage updates
        startPolling();
        
    } catch (error) {
        console.error('Dashboard render error:', error);
        container.innerHTML = `
            <div class="optti-dashboard-error">
                Unable to load dashboard. Please check your connection.
            </div>
        `;
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', renderDashboard);

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    stopPolling();
    cleanupTooltips();
});

// Pause polling when tab is hidden, resume when visible
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        // Keep interval running but skip updates
    } else {
        // Tab is visible again, update immediately
        updateUsageData();
    }
});
