import React, { useState } from 'react';

const TrustBadges = () => {
  const testimonials = [
    {
      name: 'Jessica M.',
      role: 'Marketing Director, Bloom & Co',
      quote:
        'I was skeptical at first, but after running it on our blog images the descriptions were actually better than what we were writing manually.',
      rating: 5,
    },
    {
      name: 'Ryan K.',
      role: 'Freelance Developer',
      quote:
        'Installed it for a client who needed WCAG compliance fast. Did 300+ images overnight. Client was happy, I looked like a hero.',
      rating: 5,
    },
    {
      name: 'Maria Santos',
      role: 'Store Owner, Coastal Living Decor',
      quote:
        'My WooCommerce shop had zero alt text on products. Now everything is tagged and I am actually showing up in Google image searches.',
      rating: 4,
    },
    {
      name: 'Tom H.',
      role: 'Agency Owner, Pixel Perfect',
      quote:
        'We use this on every client site now. The bulk feature alone pays for itself. Would be nice to have more language options though.',
      rating: 4,
    },
  ];

  const [activeIndex, setActiveIndex] = useState(0);
  const activeTestimonial = testimonials[activeIndex] || testimonials[0];

  const handleNext = () => {
    setActiveIndex((prev) => (prev + 1) % testimonials.length);
  };

  const handlePrev = () => {
    setActiveIndex((prev) => (prev - 1 + testimonials.length) % testimonials.length);
  };

  return (
    <section className="rounded-3xl bg-white shadow-xl p-8">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 2l7 3v6c0 5-3 9-7 11-4-2-7-6-7-11V5l7-3z" />
            </svg>
          </span>
          <div className="leading-tight">
            <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">WCAG Compliant</div>
            <div className="text-[11px] text-slate-500">Accessibility ready</div>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M9 12l2 2 4-4" strokeLinecap="round" strokeLinejoin="round" />
              <circle cx="12" cy="12" r="9" />
            </svg>
          </span>
          <div className="leading-tight">
            <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">GDPR Ready</div>
            <div className="text-[11px] text-slate-500">Privacy-first by design</div>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M13 2L3 14h7l-1 8 12-14h-7l-1-6z" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </span>
          <div className="leading-tight">
            <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">99.9% Uptime</div>
            <div className="text-[11px] text-slate-500">Reliable infrastructure</div>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" strokeLinecap="round" strokeLinejoin="round" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 00-3-3.87" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M16 3.13a4 4 0 010 7.75" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </span>
          <div className="leading-tight">
            <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-600">Join 10,000+ Sites</div>
            <div className="text-[11px] text-slate-500">Using BeepBeep AI</div>
          </div>
        </div>
      </div>

      <div className="mt-5 mb-4 border-t border-slate-100" />

      <div className="flex flex-col gap-6 md:flex-row md:items-start">
        <div className="md:pr-6 md:w-7/12">
          <div className="text-4xl text-slate-200 leading-none">&ldquo;</div>
          <p className="text-[16px] md:text-[17px] text-slate-700 leading-relaxed">
            {activeTestimonial.quote}
          </p>
        </div>
        <div className="flex flex-col gap-3 md:w-5/12">
          <div>
            <div className="text-sm font-semibold text-slate-900">{activeTestimonial.name}</div>
            <div className="text-xs text-slate-500">{activeTestimonial.role}</div>
          </div>
          <div className="flex items-center gap-1 text-amber-500" aria-label={`Rated ${activeTestimonial.rating || 5} out of 5`}>
            {Array.from({ length: 5 }).map((_, index) => (
              <svg
                key={`star-${index}`}
                className={`h-4 w-4 ${index < (activeTestimonial.rating || 5) ? '' : 'opacity-30'}`}
                viewBox="0 0 16 16"
                fill="currentColor"
              >
                <path d="M8 1L10 5L14 6L11 9L11.5 13L8 11L4.5 13L5 9L2 6L6 5L8 1Z" />
              </svg>
            ))}
          </div>
          <p className="text-xs text-slate-500">
            Alt text automation for SEO and accessibility teams.
          </p>
          <div className="mt-3 flex items-center gap-2">
            <button
              type="button"
              onClick={handlePrev}
              className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition duration-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              aria-label="Previous testimonial"
            >
              <svg className="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 15L7 10L12 5" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            </button>
            <button
              type="button"
              onClick={handleNext}
              className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition duration-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              aria-label="Next testimonial"
            >
              <svg className="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M8 5L13 10L8 15" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </section>
  );
};

export default TrustBadges;
