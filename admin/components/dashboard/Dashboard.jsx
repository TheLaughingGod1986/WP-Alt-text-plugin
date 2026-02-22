/**
 * Dashboard Component
 *
 * Main dashboard screen for BeepBeep AI Alt Text Generator
 */

import React, { useState } from 'react';
import KpiTile from '../shared/KpiTile';
import GrowthCtaBanner from '../shared/GrowthCtaBanner';
import './dashboard.css';

const ShortcutsIcon = ({ className = '' }) => (
  <svg
    className={className}
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
  >
    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" strokeWidth="1.5" fill="none" />
    <path d="M6 6H10M6 10H10" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
  </svg>
);

const CheckIcon = ({ className = '' }) => (
  <svg
    className={className}
    viewBox="0 0 16 16"
    fill="none"
    stroke="currentColor"
    strokeWidth="2.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <path d="M13 4L6 11L3 8" />
  </svg>
);

const ArrowIcon = ({ className = '', direction = 'right' }) => (
  <svg
    className={className}
    viewBox="0 0 20 20"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    {direction === 'left' ? <path d="M12.5 4.5L7.5 10l5 5.5" /> : <path d="M7.5 4.5L12.5 10l-5 5.5" />}
  </svg>
);

const FreeUsageCard = ({
  used,
  total,
  resetDate,
  usagePercent,
  onUpgradeClick,
  onGenerateMissing,
  onReoptimizeAll,
  isLoading,
  canGenerate,
  missingImages: missingImagesProp = 0
}) => {
  const missingImages = parseInt(missingImagesProp, 10) || 0;
  const percentage = Number.isFinite(usagePercent) ? Math.max(0, Math.min(100, usagePercent)) : 0;
  const radius = 38;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (percentage / 100) * circumference;
  const resetLabel = resetDate ? `Resets ${resetDate}` : 'Resets soon';

  return (
    <section className="rounded-3xl bg-white shadow-xl px-6 py-6 md:px-7 md:py-7 flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
          FREE
        </span>
      </div>

      <h2 className="text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase">
        Alt Text Progress This Month
      </h2>

      <div className="flex items-center gap-4">
        <div className="relative h-24 w-24">
          <svg className="h-24 w-24" viewBox="0 0 96 96" aria-hidden="true">
            <circle cx="48" cy="48" r={radius} fill="none" stroke="#E2E8F0" strokeWidth="10" />
            <circle
              cx="48"
              cy="48"
              r={radius}
              fill="none"
              stroke="#22C55E"
              strokeWidth="10"
              strokeLinecap="round"
              strokeDasharray={circumference}
              strokeDashoffset={offset}
              transform="rotate(-90 48 48)"
            />
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <span className="text-xl font-semibold text-slate-900">{Math.round(percentage)}%</span>
            <span className="text-[10px] font-semibold tracking-[0.18em] uppercase text-slate-500">used</span>
          </div>
        </div>
        <div className="space-y-1">
          <p className="text-[15px] leading-relaxed text-slate-700">
            <span className="font-semibold text-slate-900">{used}</span> of{' '}
            <span className="font-semibold text-slate-900">{total}</span> images used this month
          </p>
          <p className="text-[12px] text-slate-500">{resetLabel}</p>
        </div>
      </div>

      <button
        type="button"
        data-action="show-upgrade-modal"
        onClick={onUpgradeClick}
        className="w-full rounded-full bg-emerald-600 px-6 py-3 text-[15px] font-semibold text-white shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-emerald-700 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
      >
        Upgrade to 1,000 images/month
      </button>

      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          data-action="generate-missing"
          onClick={onGenerateMissing}
          disabled={!canGenerate || isLoading || missingImages <= 0}
          {...((!canGenerate || isLoading || missingImages <= 0) && {
            'data-bbai-tooltip': isLoading
              ? 'Processing, please wait...'
              : missingImages <= 0
              ? 'All images already have alt text'
              : 'Upgrade to unlock more generations',
            'data-bbai-tooltip-position': 'top'
          })}
          className={`flex-1 rounded-full px-4 py-2.5 text-[13px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-white ${
            !canGenerate || isLoading || missingImages <= 0
              ? 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-50'
              : 'bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500/20 focus-visible:ring-indigo-500'
          }`}
        >
          Generate Missing
        </button>
        <button
          type="button"
          data-action="regenerate-all"
          onClick={onReoptimizeAll}
          disabled={!canGenerate || isLoading}
          {...((!canGenerate || isLoading) && {
            'data-bbai-tooltip': isLoading
              ? 'Processing, please wait...'
              : 'Upgrade to unlock more generations',
            'data-bbai-tooltip-position': 'top'
          })}
          className="flex-1 rounded-full bg-orange-500/10 px-4 py-2.5 text-[13px] font-semibold text-orange-600 transition hover:bg-orange-500/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
        >
          Re-optimise All
        </button>
      </div>

      <p className="text-[12px] text-slate-500">
        Free plan includes 50 images per month. Growth includes 1,000 images per month.
      </p>
    </section>
  );
};

const GrowthCard = ({ onUpgradeClick, onComparePlans }) => (
  <section className="rounded-3xl bg-gradient-to-br from-sky-500 via-sky-500 to-indigo-500 text-white shadow-xl px-6 py-6 md:px-7 md:py-7">
    <div className="space-y-4">
      <span className="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white">
        GROWTH
      </span>

      <div className="space-y-2">
        <h2 className="text-[24px] font-semibold text-white">Upgrade to Growth</h2>
        <p className="text-[15px] leading-relaxed text-slate-100">
          Automate alt text generation and scale image optimisation each month.
        </p>
      </div>

      <ul className="space-y-2 text-[15px] leading-relaxed text-slate-100">
        <li className="flex items-start gap-3">
          <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
            <CheckIcon className="h-4 w-4 text-white" />
          </span>
          <span>1,000 AI alt texts per month</span>
        </li>
        <li className="flex items-start gap-3">
          <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
            <CheckIcon className="h-4 w-4 text-white" />
          </span>
          <span>Bulk processing for the media library</span>
        </li>
        <li className="flex items-start gap-3">
          <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
            <CheckIcon className="h-4 w-4 text-white" />
          </span>
          <span>Priority queue for faster results</span>
        </li>
        <li className="flex items-start gap-3">
          <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
            <CheckIcon className="h-4 w-4 text-white" />
          </span>
          <span>Multilingual support for global SEO</span>
        </li>
      </ul>

      <div className="space-y-2">
        <button
          type="button"
          data-action="show-upgrade-modal"
          onClick={onUpgradeClick}
          className="w-full rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-slate-900 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-sky-500"
          aria-label="Upgrade to Growth"
        >
          Upgrade to Growth
        </button>
        <p className="text-[12px] text-slate-200">Includes 1,000 AI alt texts per month. Cancel anytime.</p>
        <button
          type="button"
          onClick={onComparePlans}
          className="inline-flex items-center justify-center rounded-full border border-white/40 px-6 py-3 text-[15px] font-semibold text-white transition duration-200 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-sky-500"
        >
          Compare plans
        </button>
      </div>
    </div>
  </section>
);

const OnboardingCard = ({ onOpenLibrary, onLearnMore }) => (
  <section className="rounded-3xl bg-white shadow-xl px-6 py-6 md:px-8 md:py-7 space-y-5">
    <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
      GETTING STARTED
    </span>

    <div className="space-y-2">
      <h2 className="text-[24px] font-semibold text-slate-900">You're ready to optimise your images</h2>
      <p className="text-[15px] leading-relaxed text-slate-700">
        Generate SEO-friendly, WCAG-compliant alt text automatically to lift rankings, accessibility, and image search visibility.
      </p>
    </div>

    <div className="grid gap-3 md:grid-cols-3">
      <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
        <p className="text-[13px] font-semibold text-slate-900">Editorial</p>
        <p className="text-[11px] text-slate-500">Rank faster in search results</p>
      </div>
      <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
        <p className="text-[13px] font-semibold text-slate-900">Ecommerce</p>
        <p className="text-[11px] text-slate-500">Optimize catalog coverage</p>
      </div>
      <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
        <p className="text-[13px] font-semibold text-slate-900">Reporting</p>
        <p className="text-[11px] text-slate-500">Track SEO impact quickly</p>
      </div>
    </div>

    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">ROI BENEFITS</p>
      <div className="space-y-2">
        <div className="flex items-center gap-2 text-[15px] leading-relaxed text-slate-700">
          <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100">
            <CheckIcon className="h-4 w-4 text-emerald-600" />
          </span>
          Save hours each month on manual alt text
        </div>
        <div className="flex items-center gap-2 text-[15px] leading-relaxed text-slate-700">
          <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100">
            <CheckIcon className="h-4 w-4 text-emerald-600" />
          </span>
          Improve visibility in Google Images
        </div>
        <div className="flex items-center gap-2 text-[15px] leading-relaxed text-slate-700">
          <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100">
            <CheckIcon className="h-4 w-4 text-emerald-600" />
          </span>
          Meet WCAG and ADA compliance targets
        </div>
      </div>
    </div>

    <div className="rounded-2xl border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-700">EXAMPLE ALT TEXT</p>
      <p className="mt-2 text-[15px] leading-relaxed text-slate-700">
        "Woman working on a laptop next to a sunlit window in a modern office."
      </p>
    </div>

    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <p className="text-[12px] text-slate-500">Start with your media library to generate missing alt text.</p>
      <div className="flex flex-col gap-3 sm:flex-row">
        <button
          type="button"
          onClick={onOpenLibrary}
          className="inline-flex items-center justify-center gap-2 rounded-full bg-sky-600 px-6 py-3 text-[15px] font-semibold text-white shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-sky-700 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
        >
          <span>+ Go to Media Library</span>
        </button>
        <button
          type="button"
          onClick={onLearnMore}
          className="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-6 py-3 text-[15px] font-semibold text-slate-700 transition duration-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
        >
          Learn More
        </button>
      </div>
    </div>
  </section>
);

const TrustStrip = () => (
  <div className="rounded-3xl bg-white shadow-sm px-5 py-4">
    <div className="flex flex-wrap items-center gap-3">
      <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
        WCAG Compliant
      </span>
      <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
        GDPR Ready
      </span>
      <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
        99.9% Uptime
      </span>
      <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">
        Join 10,000+ sites using BeepBeep AI
      </span>
    </div>
  </div>
);

const TestimonialsCarousel = ({ testimonials }) => {
  const [index, setIndex] = useState(0);
  const total = testimonials.length;
  const active = testimonials[index];

  const handlePrev = () => {
    setIndex((prev) => (prev - 1 + total) % total);
  };

  const handleNext = () => {
    setIndex((prev) => (prev + 1) % total);
  };

  return (
    <div className="rounded-3xl bg-white shadow-sm px-6 py-6 md:px-8 md:py-7 flex flex-col gap-4">
      <div className="space-y-3">
        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">TESTIMONIALS</p>
        <blockquote className="text-[15px] leading-relaxed text-slate-700">"{active.quote}"</blockquote>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-[14px] font-semibold text-slate-900">{active.name}</p>
          <p className="text-[12px] text-slate-500">{active.role}</p>
          <div className="mt-2 flex items-center gap-1 text-amber-500" aria-label="Rated 5 out of 5">
            {Array.from({ length: active.rating }).map((_, idx) => (
              <svg
                key={`star-${idx}`}
                className="h-4 w-4"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
              >
                <path d="M10 15l-5.09 3.09L6.18 12 1.5 8.91l5.9-.86L10 2.5l2.6 5.55 5.9.86L13.82 12l1.27 6.09L10 15z" />
              </svg>
            ))}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handlePrev}
            className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            aria-label="Previous testimonial"
          >
            <ArrowIcon direction="left" className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={handleNext}
            className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            aria-label="Next testimonial"
          >
            <ArrowIcon className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
};

const BottomGrowthPanel = ({ onUpgradeClick, onComparePlans }) => (
  <GrowthCtaBanner
    onStartTrial={onUpgradeClick}
    onComparePlans={onComparePlans}
    footerLines={[
      'Trusted by 10,000+ WordPress sites - WCAG and GDPR ready.',
      'Free: 50 images/month - Growth: 1,000 images/month with bulk optimisation'
    ]}
  />
);

const testimonialsData = [
  {
    name: 'Jessica M.',
    role: 'Marketing Director',
    quote:
      'I was skeptical at first, but after running it on our blog images the descriptions were actually better than what we were writing manually.',
    rating: 5
  },
  {
    name: 'Ryan K.',
    role: 'Freelance Developer',
    quote:
      'Installed it for a client who needed WCAG compliance fast. Did 300+ images overnight. Client was happy, I looked like a hero.',
    rating: 5
  },
  {
    name: 'Maria Santos',
    role: 'Store Owner',
    quote:
      'My WooCommerce shop had zero alt text on products. Now everything is tagged and showing up in Google image searches.',
    rating: 4
  }
];

const Dashboard = ({
  // Plan and usage
  plan = 'free',
  usageStats = {},
  // Image stats
  stats: imageStats = {},
  // Queue stats
  queueStats = {},
  // Callbacks
  onUpgradeClick = null,
  onOptimiseNew = null,
  onOptimiseAll = null,
  onOpenLibrary = null,
  onManageBilling = null,
  // API endpoints (if needed)
  apiUrl = '',
  nonce = ''
}) => {
  const [isLoading, setIsLoading] = useState(false);

  // Extract values with defaults
  const used = usageStats.used ?? 0;
  const limit = usageStats.limit ?? (plan === 'free' ? 50 : plan === 'growth' || plan === 'pro' ? 1000 : 10000);
  const remaining = usageStats.remaining ?? (limit - used);
  const resetDate = usageStats.resetDate ?? usageStats.reset_date ?? '';

  // Calculate usage percentage
  const usagePercent = limit > 0 ? Math.min(100, (used / limit) * 100) : 0;

  // Plan detection
  const isFree = plan === 'free';
  const isGrowth = plan === 'growth' || plan === 'pro';
  const isAgency = plan === 'agency';
  const planName = isFree ? 'Free Plan' : isGrowth ? 'Growth Plan' : 'Agency Plan';

  // Image stats
  const totalImages = imageStats.total ?? imageStats.totalImages ?? 0;
  const optimizedImages = imageStats.imagesOptimized ?? imageStats.with_alt ?? imageStats.withAlt ?? 0;
  const missingImages = parseInt(imageStats.missing ?? imageStats.missingImages ?? 0, 10);
  
  // Debug: Log missing images count (remove in production if needed)
  if (typeof window !== 'undefined' && window.console && window.console.log) {
    console.log('[BBAI Dashboard] Missing images:', missingImages, 'Image stats:', imageStats);
  }

  // Calculate alt text coverage
  const computedCoverage = totalImages > 0 ? (optimizedImages / totalImages) * 100 : null;
  const altTextCoverage = computedCoverage ?? imageStats.seoCoverage ?? imageStats.altTextCoverage ?? null;

  // Calculate time saved (2.5 minutes per alt text)
  const minutesPerAltText = 2.5;
  const timeSaved = round((used * minutesPerAltText) / 60, 1);

  const timeSavedHours = usageStats.timeSavedHours ?? usageStats.time_saved_hours ?? imageStats.timeSavedHours ?? timeSaved ?? 0;
  const imagesOptimizedValue = imageStats.imagesOptimized ?? optimizedImages ?? 0;
  const seoImpactScoreValue =
    imageStats.seoImpactScore ??
    imageStats.seo_impact_score ??
    (typeof altTextCoverage === 'number' ? Math.round(altTextCoverage) : altTextCoverage) ??
    0;

  const stats = {
    timeSavedHours: timeSavedHours ?? 0,
    imagesOptimized: imagesOptimizedValue ?? 0,
    seoImpactScore: seoImpactScoreValue ?? 0
  };

  // Queue stats
  const inQueue = queueStats.pending ?? 0;
  const runToday = queueStats.completed_recent ?? 0;
  const needsAttention = queueStats.failed ?? 0;

  // Can generate check
  const hasQuota = remaining > 0;
  const isPremium = isGrowth || isAgency;
  const canGenerate = hasQuota || isPremium;

  // Handle upgrade click
  const handleUpgradeClick = () => {
    if (onUpgradeClick && typeof onUpgradeClick === 'function') {
      onUpgradeClick();
    } else if (typeof window !== 'undefined' && window.bbaiShowUpgradeModal) {
      window.bbaiShowUpgradeModal();
    }
  };

  const handleComparePlans = () => {
    if (typeof window !== 'undefined' && typeof window.openPricingModal === 'function') {
      window.openPricingModal('enterprise');
      return;
    }
    handleUpgradeClick();
  };

  const handleShowShortcuts = () => {
    if (typeof window !== 'undefined' && window.bbaiTooltips && typeof window.bbaiTooltips.showShortcutsModal === 'function') {
      window.bbaiTooltips.showShortcutsModal();
    }
  };

  const handleOpenGuide = () => {
    if (typeof window !== 'undefined' && window.location) {
      const guideUrl = window.location.href.split('?')[0] + '?page=bbai&tab=guide';
      window.location.href = guideUrl;
    }
  };

  // Handle optimise new images
  const handleOptimiseNew = async () => {
    if (onOptimiseNew && typeof onOptimiseNew === 'function') {
      setIsLoading(true);
      try {
        await onOptimiseNew();
      } finally {
        setIsLoading(false);
      }
    } else if (typeof window !== 'undefined' && window.bbaiGenerateMissing) {
      setIsLoading(true);
      try {
        await window.bbaiGenerateMissing();
      } finally {
        setIsLoading(false);
      }
    }
  };

  // Handle optimise all images
  const handleOptimiseAll = async () => {
    if (onOptimiseAll && typeof onOptimiseAll === 'function') {
      setIsLoading(true);
      try {
        await onOptimiseAll();
      } finally {
        setIsLoading(false);
      }
    } else if (typeof window !== 'undefined' && window.bbaiRegenerateAll) {
      setIsLoading(true);
      try {
        await window.bbaiRegenerateAll();
      } finally {
        setIsLoading(false);
      }
    }
  };

  // Handle open library
  const handleOpenLibrary = () => {
    if (onOpenLibrary && typeof onOpenLibrary === 'function') {
      onOpenLibrary();
    } else if (typeof window !== 'undefined' && window.location) {
      // Try to navigate to library page
      const libraryUrl = window.location.href.split('?')[0] + '?page=bbai-library';
      window.location.href = libraryUrl;
    }
  };

  // Helper function for rounding
  function round(value, decimals) {
    return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
  }

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-6 md:px-6">
      <div className="mx-auto max-w-6xl space-y-10">
        <header className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-[28px] font-semibold text-slate-900 md:text-[32px]">Dashboard</h1>
            <p className="mt-2 text-[15px] leading-relaxed text-slate-700">
              Automated, accessible alt text generation for your WordPress media library.
            </p>
          </div>
          <button
            type="button"
            onClick={handleShowShortcuts}
            data-bbai-tooltip="Press ? to view all keyboard shortcuts"
            data-bbai-tooltip-position="left"
            className="inline-flex items-center gap-2 rounded-full px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-50"
          >
            <ShortcutsIcon className="h-4 w-4" />
            Shortcuts
          </button>
        </header>

        <section className="grid gap-6 md:grid-cols-2">
          <FreeUsageCard
            used={used}
            total={limit}
            resetDate={resetDate}
            usagePercent={usagePercent}
            onUpgradeClick={handleUpgradeClick}
            onGenerateMissing={handleOptimiseNew}
            onReoptimizeAll={handleOptimiseAll}
            isLoading={isLoading}
            canGenerate={canGenerate}
            missingImages={missingImages}
          />
          <GrowthCard onUpgradeClick={handleUpgradeClick} onComparePlans={handleComparePlans} />
        </section>

        <section className="grid gap-4 md:grid-cols-3">
          <KpiTile
            label="Hours Saved"
            value={stats.timeSavedHours.toFixed ? stats.timeSavedHours.toFixed(0) : stats.timeSavedHours}
            description="Estimated time saved vs manual alt text creation."
          />
          <KpiTile
            label="Images Optimized"
            value={stats.imagesOptimized}
            description="Total images with generated or improved alt text."
          />
          <KpiTile
            label="SEO Impact"
            value={`${stats.seoImpactScore}${typeof stats.seoImpactScore === 'number' ? '%' : ''}`}
            description="Estimated improvement based on alt text coverage."
          />
        </section>

        <OnboardingCard onOpenLibrary={handleOpenLibrary} onLearnMore={handleOpenGuide} />

        <section className="space-y-4">
          <TrustStrip />
          <TestimonialsCarousel testimonials={testimonialsData} />
        </section>

        <BottomGrowthPanel onUpgradeClick={handleUpgradeClick} onComparePlans={handleComparePlans} />
      </div>
    </div>
  );
};

export default Dashboard;
