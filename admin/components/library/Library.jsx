/**
 * Image ALT Text Library Component
 *
 * Matches Dashboard design system using Tailwind utility classes.
 */

import React from 'react';
import KpiTile from '../shared/KpiTile';
import GrowthCtaBanner from '../shared/GrowthCtaBanner';

const LibraryIcon = ({ className = '' }) => (
  <svg className={className} viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <rect x="6" y="10" width="36" height="28" rx="6" stroke="currentColor" strokeWidth="2" />
    <circle cx="17" cy="20" r="3" fill="currentColor" />
    <path d="M12 32l7-7 6 6 6-5 9 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
  </svg>
);

const EmptyStateCard = ({ onUploadImages, onSyncLibrary }) => (
  <section className="rounded-3xl bg-white shadow-xl px-6 py-8 md:px-10 md:py-10 flex flex-col items-center text-center gap-5">
    <div className="flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-sky-50 via-white to-indigo-100 text-sky-500 shadow-inner">
      <LibraryIcon className="h-10 w-10" />
    </div>

    <div className="space-y-2">
      <h2 className="text-xl font-semibold text-slate-900">No images yet</h2>
      <p className="text-sm text-slate-600">
        Upload images to start automating SEO &amp; accessibility compliance.
      </p>
    </div>

    <ul className="mt-2 space-y-1 text-sm text-slate-700">
      <li className="flex items-center justify-center gap-2">
        <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-50 text-[11px] text-emerald-600">
          &#10003;
        </span>
        <span>WCAG-compliant alt text for accessibility</span>
      </li>
      <li className="flex items-center justify-center gap-2">
        <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-50 text-[11px] text-emerald-600">
          &#10003;
        </span>
        <span>Optimised for Google Images &amp; SEO insights</span>
      </li>
      <li className="flex items-center justify-center gap-2">
        <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-50 text-[11px] text-emerald-600">
          &#10003;
        </span>
        <span>Bulk processing &amp; AI-powered automation</span>
      </li>
    </ul>

    <div className="flex flex-col items-center">
      <button
        type="button"
        onClick={onUploadImages}
        className="mt-5 inline-flex items-center justify-center rounded-full bg-emerald-500 px-6 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
      >
        + Upload Images
      </button>
      <button
        type="button"
        onClick={onSyncLibrary}
        className="mt-2 text-xs font-medium text-sky-600 hover:text-sky-700"
      >
        Or sync from Media Library automatically &rsaquo;
      </button>
    </div>
  </section>
);

const ImageAltTextLibrary = ({
  images = [],
  usageStats = {},
  stats: imageStats = {},
  timeSavedHours,
  imagesOptimized,
  seoImpactScore,
  searchValue = '',
  statusLabel = 'Status: All',
  dateRangeLabel = 'Date Range',
  onSearchChange = null,
  onStatusFilterClick = null,
  onDateFilterClick = null,
  onClearFilters = null,
  onUploadImages = null,
  onSyncLibrary = null,
  onUpgradeClick = null,
  onComparePlans = null,
  tableContent = null,
  children = null
}) => {
  function round(value, decimals) {
    return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
  }

  const used = usageStats.used ?? 0;
  const totalImages = imageStats.total ?? imageStats.totalImages ?? 0;
  const optimizedImages = imageStats.imagesOptimized ?? imageStats.with_alt ?? imageStats.withAlt ?? 0;
  const coveragePercent = totalImages > 0 ? (optimizedImages / totalImages) * 100 : 0;

  const minutesPerAltText = 2.5;
  const computedTimeSaved = round((used * minutesPerAltText) / 60, 1);

  const timeSavedHoursValue = timeSavedHours ?? usageStats.timeSavedHours ?? usageStats.time_saved_hours ?? computedTimeSaved ?? 0;
  const imagesOptimizedValue = imagesOptimized ?? optimizedImages ?? 0;
  const seoImpactScoreValue =
    seoImpactScore ?? imageStats.seoImpactScore ?? imageStats.seo_impact_score ?? Math.round(coveragePercent) ?? 0;

  const stats = {
    timeSavedHours: timeSavedHoursValue ?? 0,
    imagesOptimized: imagesOptimizedValue ?? 0,
    seoImpactScore: seoImpactScoreValue ?? 0
  };

  const hasImages = Array.isArray(images) && images.length > 0;
  const resolvedTableContent = tableContent ?? children;

  const handleUpgradeClick = () => {
    if (onUpgradeClick && typeof onUpgradeClick === 'function') {
      onUpgradeClick();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.bbaiShowUpgradeModal === 'function') {
      window.bbaiShowUpgradeModal();
    }
  };

  const handleComparePlans = () => {
    if (onComparePlans && typeof onComparePlans === 'function') {
      onComparePlans();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.openPricingModal === 'function') {
      window.openPricingModal('enterprise');
      return;
    }
    handleUpgradeClick();
  };

  const handleSearchChange = (event) => {
    if (onSearchChange && typeof onSearchChange === 'function') {
      onSearchChange(event);
    }
  };

  return (
    <div className="bbai-library-container min-h-screen bg-slate-50 px-4 py-6 md:px-6">
      <div className="mx-auto max-w-6xl space-y-6 md:space-y-8">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold text-slate-900">Image ALT Text Library</h1>
          <p className="text-sm text-slate-600">
            Search, review, and regenerate AI alt text for images on this site.
          </p>
        </header>

        <section className="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex-1">
            <input
              id="bbai-library-search"
              type="search"
              value={searchValue}
              onChange={handleSearchChange}
              placeholder="Search images or alt text"
              className="w-full rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
            />
          </div>

          <div className="flex flex-wrap gap-2 text-xs">
            <button
              type="button"
              id="bbai-status-filter-btn"
              onClick={onStatusFilterClick}
              className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
            >
              {statusLabel}
            </button>
            <button
              type="button"
              id="bbai-date-filter-btn"
              onClick={onDateFilterClick}
              className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
            >
              {dateRangeLabel}
            </button>
            <button
              type="button"
              id="bbai-clear-filters"
              onClick={onClearFilters}
              className="rounded-full px-3 py-2 text-xs font-semibold text-slate-500 transition hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
            >
              &times; Clear filters
            </button>
          </div>

          <select id="bbai-status-filter" className="bbai-library-filter-select hidden">
            <option value="all">Status: All</option>
            <option value="optimized">Optimized</option>
            <option value="missing">Missing</option>
            <option value="needs-work">Needs work</option>
          </select>
          <select id="bbai-date-filter" className="bbai-library-filter-select hidden">
            <option value="all">Date Range</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
          </select>
        </section>

        {hasImages ? (
          <section className="rounded-3xl bg-white shadow-xl">{resolvedTableContent}</section>
        ) : (
          <EmptyStateCard onUploadImages={onUploadImages} onSyncLibrary={onSyncLibrary} />
        )}

        <section className="grid gap-4 md:grid-cols-3">
          <KpiTile
            variant="library"
            label="HRS SAVED"
            value={stats.timeSavedHours.toFixed ? stats.timeSavedHours.toFixed(0) : stats.timeSavedHours}
            description="Estimated vs manual alt text creation."
          />
          <KpiTile
            variant="library"
            label="IMAGES OPTIMIZED"
            value={stats.imagesOptimized}
            description="Total images with generated or improved alt text."
          />
          <KpiTile
            variant="library"
            label="SEO IMPACT"
            value={`${stats.seoImpactScore}${typeof stats.seoImpactScore === 'number' ? '%' : ''}`}
            description="Estimated improvement based on alt text coverage."
          />
        </section>

        <GrowthCtaBanner
          onStartTrial={handleUpgradeClick}
          onComparePlans={handleComparePlans}
          footerLines={[
            'Trusted by 10,000+ WordPress sites - WCAG and GDPR ready',
            'Free: 50 images/month - Growth: 1,000 images/month with bulk optimisation'
          ]}
        />
      </div>
    </div>
  );
};

export default ImageAltTextLibrary;
