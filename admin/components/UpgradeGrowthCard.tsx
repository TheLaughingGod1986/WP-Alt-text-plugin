import React from 'react';

interface UpgradeGrowthCardProps {
  onUpgrade: () => void;
  onComparePlans?: () => void;
}

const UpgradeGrowthCard: React.FC<UpgradeGrowthCardProps> = ({ onUpgrade, onComparePlans }) => {
  return (
    <section className="rounded-3xl bg-gradient-to-br from-sky-600 via-sky-500 to-indigo-500 text-white shadow-xl px-6 py-6 md:px-8 md:py-7">
      <div className="w-full">
        <span className="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white">
          GROWTH
        </span>

        <h2 className="mt-3 text-[24px] font-semibold leading-snug text-white md:text-[26px]">
          Stop losing SEO traffic to missing alt text
        </h2>

        <p className="mt-2 text-[15px] leading-relaxed text-slate-100 max-w-xl">
          Growth turns missing alt text into measurable SEO lift with automated coverage, bulk processing, and faster queues.
        </p>

        <ul className="mt-4 space-y-2 text-[15px] text-slate-100">
          <li className="flex items-start gap-3">
            <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
              <svg
                className="h-4 w-4 text-white"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </span>
            <span>
              <span className="font-semibold">1,000 credits/month</span> to close coverage gaps fast
            </span>
          </li>
          <li className="flex items-start gap-3">
            <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
              <svg
                className="h-4 w-4 text-white"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </span>
            <span>
              <span className="font-semibold">Bulk processing</span> to clear backlogs in minutes
            </span>
          </li>
          <li className="flex items-start gap-3">
            <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
              <svg
                className="h-4 w-4 text-white"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M13 4L6 11L3 8" />
              </svg>
            </span>
            <span>
              <span className="font-semibold">Priority queue</span> to keep new uploads visible
            </span>
          </li>
        </ul>

        <p className="mt-3 text-[12px] text-slate-200">
          Trusted by 10,000+ WordPress sites - WCAG &amp; GDPR ready
        </p>

        <p className="mt-1 text-[12px] text-slate-200">
          Free: 50 images/month - Growth: 1,000 images/month with bulk optimisation
        </p>

        <button
          type="button"
          onClick={onUpgrade}
          className="mt-5 w-full rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-slate-900 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-sky-600"
        >
          Start free trial
        </button>

        <p className="mt-2 text-[12px] text-slate-200">
          14 days free. No credit card. Cancel anytime.
        </p>

        <button
          type="button"
          onClick={onComparePlans}
          className="mt-3 inline-flex items-center justify-center rounded-full border border-white/40 px-6 py-3 text-[15px] font-semibold text-white transition duration-200 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-sky-600"
        >
          Compare all plans
        </button>
      </div>
    </section>
  );
};

export default UpgradeGrowthCard;
