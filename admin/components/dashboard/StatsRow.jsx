import React from 'react';

/**
 * Stats Row Component
 * Displays key metrics in compact stat cards
 * Supports empty states with helpful messaging
 */
const StatCard = ({ 
  icon, 
  value, 
  label, 
  description, 
  isEmpty = false,
  emptyMessage = '',
  emptyAction = null,
  emptyActionLabel = ''
}) => {
  const hasData = !isEmpty && (value !== 0 || value !== '0');

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
      {/* Icon */}
      <div className="text-slate-400 mb-4">
        {icon}
      </div>

      {/* Value */}
      <div className="text-3xl font-bold text-slate-900 mb-1">
        {typeof value === 'number' ? value.toLocaleString() : value}
      </div>

      {/* Label */}
      <div className="text-sm font-semibold text-slate-700 mb-1">
        {label}
      </div>

      {/* Description or Empty State */}
      {isEmpty && emptyMessage ? (
        <div className="text-xs text-slate-500 mt-2">
          <p className="mb-2">{emptyMessage}</p>
          {emptyAction && emptyActionLabel && (
            <button
              onClick={emptyAction}
              className="text-xs font-semibold text-blue-600 underline underline-offset-2 transition-colors hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white rounded"
            >
              {emptyActionLabel}
            </button>
          )}
        </div>
      ) : (
        <div className="text-xs text-slate-500">
          {description}
        </div>
      )}
    </div>
  );
};

/**
 * Stats Row
 * Grid of stat cards showing key metrics
 */
const StatsRow = ({ 
  altTextGenerated = 0,
  imagesOptimized = 0,
  timeSaved = 0,
  altTextCoverage = null, // Percentage 0-100
  onOptimizeClick = null
}) => {
  const hasNoData = altTextGenerated === 0 && imagesOptimized === 0 && timeSaved === 0;

  // Icons
  const iconStack = (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 2L2 7L12 12L22 7L12 2Z" strokeLinecap="round" strokeLinejoin="round"/>
      <path d="M2 17L12 22L22 17" strokeLinecap="round" strokeLinejoin="round"/>
      <path d="M2 12L12 17L22 12" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
  );

  const iconGrid = (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="3" y="3" width="18" height="18" rx="2"/>
      <path d="M3 9H21" strokeLinecap="round"/>
      <path d="M9 21V9" strokeLinecap="round"/>
    </svg>
  );

  const iconClock = (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10"/>
      <path d="M12 6V12L16 14" strokeLinecap="round"/>
    </svg>
  );

  const iconPercent = (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10"/>
      <path d="M8 8L16 16M16 8L8 16" strokeLinecap="round"/>
    </svg>
  );

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {/* AI Alt Text Generated */}
      <StatCard
        icon={iconStack}
        value={altTextGenerated}
        label="AI Alt Text Generated"
        description="This Month"
        isEmpty={altTextGenerated === 0 && hasNoData}
        emptyMessage="No alt text generated yet. Run your first optimisation to see results here."
        emptyAction={onOptimizeClick}
        emptyActionLabel="Optimise my first image"
      />

      {/* Images Optimised */}
      <StatCard
        icon={iconGrid}
        value={imagesOptimized}
        label="Images Optimised"
        description="Total"
        isEmpty={imagesOptimized === 0 && hasNoData}
        emptyMessage="No images optimised yet. Start by scanning your library."
        emptyAction={onOptimizeClick}
        emptyActionLabel="Scan my library"
      />

      {/* Time Saved */}
      <StatCard
        icon={iconClock}
        value={timeSaved}
        label="Time Saved"
        description="Estimated hours you did not spend writing alt text yourself"
        isEmpty={timeSaved === 0 && hasNoData}
      />

      {/* Alt Text Coverage (if available) */}
      {altTextCoverage !== null ? (
        <StatCard
          icon={iconPercent}
          value={`${Math.round(altTextCoverage)}%`}
          label="Alt Text Coverage"
          description={`${Math.round(altTextCoverage)}% of your images now have AI powered descriptions`}
          isEmpty={altTextCoverage === 0 && hasNoData}
        />
      ) : (
        // Fallback: Show empty card if coverage not available
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 opacity-50">
          <div className="text-slate-500 mb-4">
            {iconPercent}
          </div>
          <div className="text-3xl font-bold text-slate-500 mb-1">—</div>
          <div className="text-sm font-semibold text-slate-500 mb-1">Coverage</div>
          <div className="text-xs text-slate-500">Calculating...</div>
        </div>
      )}
    </div>
  );
};

export default StatsRow;
