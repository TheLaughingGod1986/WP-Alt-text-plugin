/**
 * Dashboard Components Index
 * Exports all dashboard components for bundling
 */

import Dashboard from './Dashboard';
import PlanStatusCard from './PlanStatusCard';
import UpgradeCard from './UpgradeCard';
import StatsRow from './StatsRow';
import OptimiseImagesPanel from './OptimiseImagesPanel';

// Export for global access
if (typeof window !== 'undefined') {
    window.bbaiDashboardComponents = {
        Dashboard,
        PlanStatusCard,
        UpgradeCard,
        StatsRow,
        OptimiseImagesPanel
    };
}

export {
    Dashboard,
    PlanStatusCard,
    UpgradeCard,
    StatsRow,
    OptimiseImagesPanel
};