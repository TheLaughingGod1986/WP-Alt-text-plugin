import React from 'react';

const DEFAULT_BULLETS = [
  '1,000 credits/month - Optimise hundreds of images automatically.',
  'Bulk processing - Fix your entire library in one click.',
  'Priority queue - Skip the line, generate 3x faster.'
];

const GrowthCtaBanner = ({
  badgeLabel = 'Growth',
  title = 'Stop Losing SEO Traffic to Missing Alt Text',
  description = 'Growth gives you 20x more credits and bulk processing to optimise your entire media library in minutes - not months.',
  bullets = DEFAULT_BULLETS,
  primaryCtaLabel = 'Start 14-day free trial',
  onStartTrial = null,
  footnoteItems = ['14 days free', 'No credit card', 'Cancel anytime.'],
  onComparePlans = null,
  comparePlansLabel = 'Compare all plans',
  footerLines = [],
  className = ''
}) => {
  const handleStartTrial = () => {
    if (onStartTrial && typeof onStartTrial === 'function') {
      onStartTrial();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.bbaiShowUpgradeModal === 'function') {
      window.bbaiShowUpgradeModal();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.openPricingModal === 'function') {
      window.openPricingModal('enterprise');
    }
  };

  const handleComparePlans = () => {
    if (onComparePlans && typeof onComparePlans === 'function') {
      onComparePlans();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.openPricingModal === 'function') {
      window.openPricingModal('enterprise');
    }
  };

  return (
    <section
      className={`rounded-3xl bg-gradient-to-br from-sky-500 via-sky-500 to-indigo-500 text-white shadow-xl px-6 py-7 md:px-8 md:py-8 ${className}`}
    >
      <div className="max-w-2xl space-y-3">
        <span className="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase">
          {badgeLabel}
        </span>
        <h2 className="mt-2 text-xl md:text-2xl font-semibold leading-snug">{title}</h2>
        <p className="text-sm md:text-base text-sky-50/90">{description}</p>
        <ul className="mt-3 space-y-2 text-sm md:text-[15px]">
          {bullets.map((item) => (
            <li key={item} className="flex items-start gap-3">
              <span className="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-xs font-semibold">
                &#10003;
              </span>
              <span>{item}</span>
            </li>
          ))}
        </ul>
        {footerLines.length > 0 ? (
          <div className="space-y-1 text-[11px] md:text-xs text-sky-50/85">
            {footerLines.map((line) => (
              <p key={line}>{line}</p>
            ))}
          </div>
        ) : null}
        <button
          type="button"
          data-action="show-upgrade-modal"
          onClick={handleStartTrial}
          className="mt-4 w-full rounded-full bg-white px-6 py-3 text-sm md:text-base font-semibold text-sky-700 shadow-lg shadow-sky-900/20 hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-sky-500 transition"
        >
          {primaryCtaLabel}
        </button>
        <p className="mt-2 text-[11px] md:text-xs text-sky-50/85">
          {footnoteItems.map((item, index) => (
            <span key={item}>
              {item}
              {index < footnoteItems.length - 1 ? <span className="mx-1">&middot;</span> : null}
            </span>
          ))}
        </p>
        {onComparePlans ? (
          <button
            type="button"
            onClick={handleComparePlans}
            className="mt-1 text-[11px] md:text-xs font-medium text-sky-50 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-sky-500"
          >
            {comparePlansLabel} &rarr;
          </button>
        ) : null}
      </div>
    </section>
  );
};

export default GrowthCtaBanner;
