import React from 'react';
import UsageCard from './UsageCard';
import UpgradeGrowthCard from './UpgradeGrowthCard';

interface DashboardOverviewProps {
  usageStats: {
    used: number;
    limit: number;
    percentage: number;
    resetDate?: string;
  };
  canGenerate: boolean;
  onUpgrade: () => void;
  onGenerateMissing: () => void;
  onReoptimizeAll: () => void;
}

const DashboardOverview: React.FC<DashboardOverviewProps> = ({
  usageStats,
  canGenerate,
  onUpgrade,
  onGenerateMissing,
  onReoptimizeAll,
}) => {
  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-6">
      <UsageCard
        usageStats={usageStats}
        canGenerate={canGenerate}
        onUpgrade={onUpgrade}
        onGenerateMissing={onGenerateMissing}
        onReoptimizeAll={onReoptimizeAll}
      />
      <UpgradeGrowthCard onUpgrade={onUpgrade} />
    </div>
  );
};

export default DashboardOverview;