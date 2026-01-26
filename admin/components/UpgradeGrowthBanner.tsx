import React from 'react';

const UpgradeGrowthBanner: React.FC = () => {
  return (
    <section
      aria-label="Upgrade to Growth"
      className="w-full rounded-3xl bg-gradient-to-br from-sky-600 via-sky-500 to-indigo-500 p-6 md:p-8"
    >
      <div className="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
        {/* Left Column - Content */}
        <div className="flex flex-col gap-4 lg:max-w-[60%]">
          {/* Badge */}
          <div className="flex">
            <span className="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white">
              GROWTH
            </span>
          </div>

          {/* Heading */}
          <h2 className="text-[24px] font-semibold text-white md:text-[26px]">
            Upgrade to Growth
          </h2>

          {/* Supporting Sentence */}
          <p className="text-[15px] leading-relaxed text-slate-100">
            Unlock bulk processing, more credits, and priority queues to keep every upload SEO-ready.
          </p>

          {/* Benefit List */}
          <ul className="flex flex-col gap-3">
            <li className="flex items-center gap-3">
              <div className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/20">
                <svg
                  className="h-4 w-4 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth="2.5"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <span className="text-[15px] text-slate-100">
                1,000 AI alt texts per month
              </span>
            </li>
            <li className="flex items-center gap-3">
              <div className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/20">
                <svg
                  className="h-4 w-4 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth="2.5"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <span className="text-[15px] text-slate-100">
                Bulk processing for entire image libraries
              </span>
            </li>
            <li className="flex items-center gap-3">
              <div className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/20">
                <svg
                  className="h-4 w-4 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth="2.5"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <span className="text-[15px] text-slate-100">
                Priority queues for faster results
              </span>
            </li>
          </ul>
        </div>

        {/* Right Column - CTA */}
        <div className="flex flex-col items-center gap-3 lg:items-end lg:max-w-[40%]">
          {/* Primary Button */}
          <button
            type="button"
            className="w-full min-h-[44px] rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-slate-900 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-sky-600 active:translate-y-0 lg:w-auto"
            aria-label="Upgrade to Growth plan"
          >
            Upgrade to Growth
          </button>

          {/* Microcopy */}
          <p className="text-center text-[12px] text-slate-200 lg:text-right">
            Includes 1,000 AI alt texts per month. Cancel anytime.
          </p>

          {/* Optional Compare Plans Link */}
          <a
            href="#"
            className="text-center text-[15px] font-semibold text-white lg:text-right inline-flex items-center justify-center rounded-full border border-white/40 px-6 py-3 transition duration-200 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-sky-600"
          >
            Compare plans
          </a>
        </div>
      </div>
    </section>
  );
};

export default UpgradeGrowthBanner;
