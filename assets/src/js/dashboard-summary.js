/**
 * Dashboard Summary Cards
 * Renders optional summary cards above the dashboard iframe
 */

import { getDashboardCharts, getDashboard } from './dashboard-api';

/**
 * Format renewal date for display
 */
function formatRenewalDate(renewsOn) {
    if (!renewsOn) return '';
    
    try {
        const date = new Date(renewsOn);
        if (isNaN(date.getTime())) return '';
        
        const day = date.getDate();
        const month = date.toLocaleDateString('en-GB', { month: 'short' });
        return `${day} ${month}`;
    } catch (e) {
        return '';
    }
}

/**
 * Render summary cards using data from /dashboard/charts endpoint
 */
export async function renderSummaryCards() {
    const container = document.getElementById('bbai-dashboard-summary-cards');
    if (!container) {
        return;
    }

    try {
        const result = await getDashboardCharts();
        const dashboardResult = await getDashboard();
        
        if (!result.ok) {
            // Hide summary cards on error
            container.style.display = 'none';
            return;
        }

        const { coverage, usage, time_saved, quality } = result;
        
        // Get credit balance from dashboard response (credits.balance)
        const credits = dashboardResult.ok && dashboardResult.credits ? dashboardResult.credits : null;
        const creditBalance = credits?.balance ?? null;
        const subscription = dashboardResult.ok ? dashboardResult.subscription : null;
        
        // Only show cards if there's meaningful data
        const hasData = coverage.total > 0 || usage.limit > 0 || creditBalance !== null;
        if (!hasData) {
            container.style.display = 'none';
            return;
        }

        let cardsHTML = '';
        
        // Credit Balance Card
        if (creditBalance !== null) {
            const subscriptionStatus = subscription?.status || 'none';
            const subscriptionActive = subscriptionStatus === 'active';
            const renewsOn = subscription?.renewsOn || null;
            const renewalText = renewsOn ? ` (Renews on ${formatRenewalDate(renewsOn)})` : '';
            
            cardsHTML += `
                <div class="bbai-summary-card">
                    <h3 class="bbai-summary-card-title">Credits Remaining</h3>
                    <p class="bbai-summary-card-value">${creditBalance}</p>
                    <p class="bbai-summary-card-description">
                        Subscription: ${subscriptionActive ? 'Active' + renewalText : 'None'}
                    </p>
                </div>
            `;
        }

        // Coverage Card
        if (coverage.total > 0) {
            cardsHTML += `
                <div class="bbai-summary-card">
                    <h3 class="bbai-summary-card-title">ALT Coverage</h3>
                    <p class="bbai-summary-card-value">${coverage.percentage.toFixed(0)}%</p>
                    <p class="bbai-summary-card-description">${coverage.with_alt} of ${coverage.total} images</p>
                </div>
            `;
        }

        // Usage Card
        if (usage.limit > 0) {
            cardsHTML += `
                <div class="bbai-summary-card">
                    <h3 class="bbai-summary-card-title">Monthly Usage</h3>
                    <p class="bbai-summary-card-value">${usage.used} / ${usage.limit}</p>
                    <p class="bbai-summary-card-description">${usage.remaining} remaining</p>
                </div>
            `;
        }

        // Time Saved Card
        if (time_saved > 0) {
            cardsHTML += `
                <div class="bbai-summary-card">
                    <h3 class="bbai-summary-card-title">Time Saved</h3>
                    <p class="bbai-summary-card-value">${time_saved} hrs</p>
                    <p class="bbai-summary-card-description">vs manual optimization</p>
                </div>
            `;
        }

        // Quality Card (if available)
        if (quality.average > 0 && quality.total_reviewed > 0) {
            cardsHTML += `
                <div class="bbai-summary-card">
                    <h3 class="bbai-summary-card-title">Avg Quality</h3>
                    <p class="bbai-summary-card-value">${quality.average.toFixed(1)}</p>
                    <p class="bbai-summary-card-description">from ${quality.total_reviewed} reviews</p>
                </div>
            `;
        }

        if (cardsHTML) {
            container.innerHTML = cardsHTML;
            container.style.display = 'grid';
        } else {
            container.style.display = 'none';
        }
    } catch (error) {
        console.error('Error rendering summary cards:', error);
        container.style.display = 'none';
    }
}

// Auto-render on DOM ready if container exists
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderSummaryCards);
    } else {
        renderSummaryCards();
    }
}

