import React, { useMemo, useState } from 'react';
import KpiTile from '../shared/KpiTile';
import GrowthCtaBanner from '../shared/GrowthCtaBanner';

const ArrowUpRightIcon = ({ className = '' }) => (
  <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M5 19L19 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    <path d="M9 5H19V15" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
  </svg>
);

const ImageCardIcon = ({ className = '' }) => (
  <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <rect x="3" y="4" width="18" height="14" rx="3" stroke="currentColor" strokeWidth="1.8" />
    <circle cx="9" cy="9" r="2" fill="currentColor" />
    <path d="M4.5 16l5-5 4 4 3-3 3 4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
  </svg>
);

const ClockIcon = ({ className = '' }) => (
  <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.8" />
    <path d="M12 7v5l3 2" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
  </svg>
);

const SparkIcon = ({ className = '' }) => (
  <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M12 3l1.8 4.8L19 9l-5.2 1.2L12 15l-1.8-4.8L5 9l5.2-1.2L12 3Z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);

const CoverageChart = ({ data = [] }) => {
  const points = useMemo(() => {
    if (!Array.isArray(data) || data.length === 0) {
      return [];
    }
    const mapped = data.map((item, index) => {
      const label = item.label || item.date || item.week || `Week ${index + 1}`;
      const value = Number.isFinite(Number(item.value))
        ? Number(item.value)
        : Number.isFinite(Number(item.coverage))
        ? Number(item.coverage)
        : 0;
      return { label, value };
    });
    return mapped;
  }, [data]);

  if (!points.length) {
    return (
      <div className="mt-4 flex h-56 items-center justify-center rounded-2xl bg-slate-50 px-4 py-4 text-center text-sm text-slate-500">
        No coverage data yet. Generate alt text to see trends.
      </div>
    );
  }

  const width = 640;
  const height = 240;
  const padding = { top: 16, right: 56, bottom: 40, left: 40 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const maxValue = Math.max(100, ...points.map((point) => point.value));

  const plottedPoints = points.map((point, index) => {
    const x = padding.left + (chartWidth / Math.max(1, points.length - 1)) * index;
    const y = padding.top + chartHeight - (point.value / maxValue) * chartHeight;
    return { ...point, x, y };
  });

  const linePath = plottedPoints
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
    .join(' ');

  const areaPath = [
    `M ${plottedPoints[0].x} ${padding.top + chartHeight}`,
    ...plottedPoints.map((point) => `L ${point.x} ${point.y}`),
    `L ${plottedPoints[plottedPoints.length - 1].x} ${padding.top + chartHeight}`,
    'Z'
  ].join(' ');

  const lastPoint = plottedPoints[plottedPoints.length - 1];

  return (
    <div className="mt-4 rounded-2xl bg-slate-50 px-4 py-4">
      <svg className="h-56 w-full" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" aria-hidden="true">
        <defs>
          <linearGradient id="bbai-coverage-fill" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stopColor="#60a5fa" stopOpacity="0.28" />
            <stop offset="100%" stopColor="#60a5fa" stopOpacity="0.04" />
          </linearGradient>
        </defs>

        {Array.from({ length: 5 }).map((_, index) => {
          const y = padding.top + (chartHeight / 4) * index;
          return (
            <line
              key={`grid-${index}`}
              x1={padding.left}
              y1={y}
              x2={width - padding.right}
              y2={y}
              stroke="#e2e8f0"
              strokeWidth="1"
            />
          );
        })}

        <path d={areaPath} fill="url(#bbai-coverage-fill)" />
        <path d={linePath} fill="none" stroke="#60a5fa" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />

        {plottedPoints.map((point, index) => (
          <circle key={`point-${index}`} cx={point.x} cy={point.y} r="4" fill="#60a5fa" />
        ))}

        {plottedPoints.map((point, index) => (
          <text
            key={`label-${index}`}
            x={point.x}
            y={height - padding.bottom + 20}
            textAnchor="middle"
            fontSize="12"
            fill="#64748b"
          >
            {point.label}
          </text>
        ))}

        {[0, 20, 40, 60, 80, 100].map((tick) => {
          const y = padding.top + chartHeight - (tick / maxValue) * chartHeight;
          return (
            <text
              key={`tick-${tick}`}
              x={padding.left - 8}
              y={y + 4}
              textAnchor="end"
              fontSize="11"
              fill="#94a3b8"
            >
              {tick}%
            </text>
          );
        })}

        <text
          x={lastPoint.x + 12}
          y={lastPoint.y + 4}
          fontSize="12"
          fontWeight="600"
          fill="#0f172a"
        >
          {Math.round(lastPoint.value)}%
        </text>
      </svg>
    </div>
  );
};

const ActivityIcon = ({ type }) => {
  if (type === 'warning' || type === 'reoptimize') {
    return (
      <span className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-600">
        <ClockIcon className="h-4 w-4" />
      </span>
    );
  }
  if (type === 'positive') {
    return (
      <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
        <ArrowUpRightIcon className="h-4 w-4" />
      </span>
    );
  }
  return (
    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-sky-600">
      <SparkIcon className="h-4 w-4" />
    </span>
  );
};

const normalizeNumber = (value, fallback = 0) => {
  if (value === null || value === undefined) return fallback;
  const parsed = typeof value === 'string' ? Number(value.replace(/[^0-9.-]/g, '')) : Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const formatNumber = (value, digits = 0) => {
  const numberValue = normalizeNumber(value, 0);
  return numberValue.toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
};

const getAnalyticsFallback = () => {
  if (typeof window === 'undefined') {
    return {};
  }
  const raw = window.bbaiAnalyticsData || window.BBAI_ANALYTICS_DATA || {};
  const coverage = Array.isArray(raw.coverage) ? raw.coverage : [];
  return {
    coverageHistory: coverage.map((item) => ({
      label: item.date || item.label || '',
      value: item.coverage ?? item.value ?? 0
    })),
    usage: raw.usage || raw.usageStats || {},
    plan: raw.plan || raw.planSlug || 'free'
  };
};

const Analytics = ({ analytics = {}, onStartGrowthTrial = null, onComparePlans = null }) => {
  const resolvedAnalytics = useMemo(() => {
    if (analytics && Object.keys(analytics).length > 0) {
      return analytics;
    }
    return getAnalyticsFallback();
  }, [analytics]);

  const [activePeriod, setActivePeriod] = useState('30d');

  const usage = resolvedAnalytics.usage || {};
  const coverageSource = resolvedAnalytics.coverageHistory || resolvedAnalytics.coverage || [];
  const coverageHistory = useMemo(() => {
    if (Array.isArray(coverageSource)) {
      return coverageSource;
    }
    if (coverageSource && typeof coverageSource === 'object') {
      return coverageSource[activePeriod] || [];
    }
    return [];
  }, [coverageSource, activePeriod]);

  const seoLiftPercent = normalizeNumber(
    resolvedAnalytics.seoLiftPercent ??
      resolvedAnalytics.seo_lift_percent ??
      resolvedAnalytics.coverageImprovementPercent ??
      resolvedAnalytics.beforeAfter?.improvementPercent ??
      0
  );
  const totalImagesOptimized = normalizeNumber(
    resolvedAnalytics.totalImagesOptimized ??
      resolvedAnalytics.imagesOptimized ??
      resolvedAnalytics.afterImagesOptimized ??
      resolvedAnalytics.beforeAfter?.after ??
      0
  );
  const timeSavedHours = normalizeNumber(
    resolvedAnalytics.timeSavedHours ?? resolvedAnalytics.time_saved_hours ?? usage.timeSavedHours ?? 0
  );

  const creditsUsed = normalizeNumber(resolvedAnalytics.creditsUsed ?? usage.used ?? 0);
  const creditsTotal = normalizeNumber(resolvedAnalytics.creditsTotal ?? usage.total ?? usage.limit ?? 0);
  const remainingCredits = normalizeNumber(
    resolvedAnalytics.remainingCredits ?? usage.remaining ?? Math.max(creditsTotal - creditsUsed, 0)
  );
  const avgPerDay = normalizeNumber(resolvedAnalytics.avgPerDay ?? usage.avgPerDay ?? 0);
  const imagesThisPeriod = normalizeNumber(resolvedAnalytics.imagesThisPeriod ?? usage.images ?? 0);
  const imagesDeltaPercent = normalizeNumber(resolvedAnalytics.imagesDeltaPercent ?? usage.imagesDeltaPercent ?? 0);

  const beforeImagesWithoutAlt = normalizeNumber(
    resolvedAnalytics.beforeImagesWithoutAlt ?? resolvedAnalytics.beforeAfter?.before ?? 0
  );
  const afterImagesOptimized = normalizeNumber(
    resolvedAnalytics.afterImagesOptimized ?? resolvedAnalytics.beforeAfter?.after ?? totalImagesOptimized
  );
  const coverageImprovementPercent = normalizeNumber(
    resolvedAnalytics.coverageImprovementPercent ?? resolvedAnalytics.beforeAfter?.improvementPercent ?? 0
  );

  const activityItems = Array.isArray(resolvedAnalytics.activity) ? resolvedAnalytics.activity : [];
  const plan = String(resolvedAnalytics.plan || 'free').toLowerCase();
  const showGrowthCta = plan === 'free';

  const handleComparePlans = () => {
    if (onComparePlans && typeof onComparePlans === 'function') {
      onComparePlans();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.openPricingModal === 'function') {
      window.openPricingModal('enterprise');
    }
  };

  const seoLiftDisplay = seoLiftPercent > 0 ? `+${formatNumber(seoLiftPercent)}%` : `${formatNumber(seoLiftPercent)}%`;

  const periodOptions = [
    { label: 'Last 30 Days', value: '30d' },
    { label: 'Last 90 Days', value: '90d' },
    { label: 'YTD', value: 'ytd' }
  ];

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-6 md:px-6">
      <div className="mx-auto max-w-6xl space-y-6 md:space-y-8">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold text-slate-900">Analytics</h1>
          <p className="text-sm text-slate-600">
            Track your alt text coverage trends, usage and automation impact.
          </p>
        </header>

        <section className="rounded-3xl bg-white shadow-xl px-6 py-4 md:px-7 md:py-5">
          <div className="grid gap-4 md:grid-cols-3">
            <KpiTile
              variant="analytics"
              value={seoLiftDisplay}
              description="Estimated lift in search visibility due to generated alt text."
              icon={<ArrowUpRightIcon className="h-5 w-5" />}
              iconClassName="bg-emerald-50 text-emerald-600"
              valueClassName="text-emerald-600"
            />
            <KpiTile
              variant="analytics"
              value={formatNumber(totalImagesOptimized)}
              description="Total images with generated or improved alt text."
              icon={<ImageCardIcon className="h-5 w-5" />}
              iconClassName="bg-indigo-50 text-indigo-600"
            />
            <KpiTile
              variant="analytics"
              value={`${formatNumber(timeSavedHours)} hrs`}
              description="Estimated time saved vs manual alt text creation."
              icon={<ClockIcon className="h-5 w-5" />}
              iconClassName="bg-sky-50 text-sky-600"
            />
          </div>
        </section>

        <section className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-base font-semibold text-slate-900">Coverage Trends</h2>
            <div className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-1 py-1 text-xs text-slate-600">
              {periodOptions.map((option) => {
                const isActive = option.value === activePeriod;
                return (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => setActivePeriod(option.value)}
                    aria-pressed={isActive}
                    className={`rounded-full px-3 py-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white ${
                      isActive ? 'bg-white text-slate-900 shadow-sm' : 'hover:bg-white/70'
                    }`}
                  >
                    {option.label}
                  </button>
                );
              })}
            </div>
          </div>

          <CoverageChart data={coverageHistory} />

          <p className="mt-2 text-xs text-slate-500">Coverage %</p>
        </section>

        <section className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
          <h2 className="text-base font-semibold text-slate-900">Usage Breakdown</h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="rounded-2xl bg-slate-50 px-4 py-3">
              <p className="text-xs text-slate-500">Used</p>
              <p className="text-lg font-semibold text-slate-900">
                {formatNumber(creditsUsed)} of {formatNumber(creditsTotal)}
              </p>
              <p className="text-xs text-slate-500">Used</p>
            </div>
            <div className="rounded-2xl bg-slate-50 px-4 py-3">
              <p className="text-xs text-slate-500">Remaining</p>
              <p className="text-lg font-semibold text-slate-900">{formatNumber(remainingCredits)}</p>
              <p className="text-xs text-slate-500">credits left</p>
            </div>
            <div className="rounded-2xl bg-slate-50 px-4 py-3">
              <p className="text-xs text-slate-500">Avg Per Day</p>
              <p className="text-lg font-semibold text-slate-900">{formatNumber(avgPerDay, 1)}</p>
              <p className="text-xs text-slate-500">generations</p>
            </div>
            <div className="rounded-2xl bg-slate-50 px-4 py-3">
              <p className="text-xs text-slate-500">Images</p>
              <p className="text-lg font-semibold text-slate-900">{formatNumber(imagesThisPeriod)}</p>
              {imagesDeltaPercent > 0 ? (
                <p className="text-xs font-semibold text-emerald-600">+{formatNumber(imagesDeltaPercent)}%</p>
              ) : null}
            </div>
          </div>
        </section>

        <section className="grid gap-4 md:grid-cols-2">
          <div className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
            <h2 className="text-base font-semibold text-slate-900">Before &amp; After</h2>
            <div className="mt-4 grid gap-6 md:grid-cols-2 items-start">
              <div className="space-y-2">
                <p className="text-xs text-slate-500">Before</p>
                <p className="text-3xl font-semibold text-slate-900">{formatNumber(beforeImagesWithoutAlt)}</p>
                <p className="text-sm text-slate-600">images without alt text</p>
              </div>
              <div className="space-y-2">
                <p className="text-xs text-slate-500">After BeepBeep AI</p>
                <p className="text-3xl font-semibold text-slate-900">{formatNumber(afterImagesOptimized)}</p>
                <p className="text-sm text-slate-600">images optimized</p>
                {coverageImprovementPercent > 0 ? (
                  <p className="text-xs text-emerald-600">
                    &uarr; {formatNumber(coverageImprovementPercent)}% improvement
                  </p>
                ) : (
                  <p className="text-xs text-slate-500">No improvement recorded yet.</p>
                )}
              </div>
            </div>
          </div>

          <div className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
            <h2 className="text-base font-semibold text-slate-900">Recent Activity</h2>
            <div className="mt-4 space-y-3 text-sm">
              {activityItems.length > 0 ? (
                activityItems.slice(0, 5).map((activity, index) => (
                  <div key={`${activity.id || activity.title || 'activity'}-${index}`} className="flex items-start gap-3">
                    <ActivityIcon type={activity.type} />
                    <div className="flex-1 space-y-1">
                      <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <p className="text-slate-800">
                          {activity.title || activity.message || activity.text || 'Recent activity'}
                        </p>
                        {activity.timeAgo ? (
                          <span className="text-xs text-slate-500">{activity.timeAgo}</span>
                        ) : null}
                      </div>
                      {activity.description ? (
                        <p className="text-xs text-slate-500">{activity.description}</p>
                      ) : null}
                    </div>
                  </div>
                ))
              ) : (
                <div className="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                  No recent activity yet. Generate alt text to see activity here.
                </div>
              )}
            </div>
            <button
              type="button"
              onClick={handleComparePlans}
              className="mt-3 text-xs font-medium text-sky-600 hover:text-sky-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            >
              Compare all plans &rarr;
            </button>
          </div>
        </section>

        {showGrowthCta ? (
          <GrowthCtaBanner
            badgeLabel="GROWTH"
            bullets={['1,000 credits/month', 'Bulk processing', 'Priority queue']}
            onStartTrial={onStartGrowthTrial}
          />
        ) : null}
      </div>
    </div>
  );
};

export default Analytics;
