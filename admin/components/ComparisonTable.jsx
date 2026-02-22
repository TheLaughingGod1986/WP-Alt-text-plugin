import React, { useState, useEffect } from 'react';
import { SkeletonBox } from './SkeletonLoader';

/**
 * Comparison Table Component
 * Displays feature comparison across all plans
 */
const ComparisonTable = ({ plans, isLoading = false }) => {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    // Simulate loading state
    const timer = setTimeout(() => setMounted(true), 500);
    return () => clearTimeout(timer);
  }, []);

  if (isLoading || !mounted) {
    return (
      <div className="overflow-x-auto">
        <table className="w-full border-collapse">
          <thead>
            <tr className="border-b-2 border-slate-200">
              <th className="text-left py-4 px-6">
                <SkeletonBox className="h-5 w-24" />
              </th>
              {[1, 2, 3].map((i) => (
                <th key={i} className="text-center py-4 px-6">
                  <SkeletonBox className="h-5 w-20 mx-auto" />
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {[1, 2, 3, 4, 5, 6].map((row) => (
              <tr key={row} className="border-b border-slate-100">
                <td className="py-4 px-6">
                  <SkeletonBox className="h-4 w-48" />
                </td>
                {[1, 2, 3].map((cell) => (
                  <td key={cell} className="py-4 px-6 text-center">
                    <SkeletonBox className="h-5 w-5 mx-auto" />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  }
  const features = [
    {
      name: 'AI-generations per month',
      free: '50',
      growth: '1,000',
      agency: '10,000+',
    },
    {
      name: 'Priority queue',
      free: false,
      growth: true,
      agency: true,
    },
    {
      name: 'Bulk optimisation',
      free: false,
      growth: true,
      agency: true,
    },
    {
      name: 'Advanced SEO scoring',
      free: false,
      growth: true,
      agency: true,
    },
    {
      name: 'API access',
      free: false,
      growth: true,
      agency: true,
    },
    {
      name: 'Bulk queue unlimited images',
      free: false,
      growth: false,
      agency: true,
    },
    {
      name: 'Faster model tier',
      free: false,
      growth: false,
      agency: true,
    },
    {
      name: 'Priority support',
      free: false,
      growth: false,
      agency: true,
    },
    {
      name: 'Multi-user support',
      free: false,
      growth: false,
      agency: true,
    },
    {
      name: 'Dedicated queue',
      free: false,
      growth: false,
      agency: false,
    },
    {
      name: 'SLA (99.9%)',
      free: false,
      growth: false,
      agency: false,
    },
    {
      name: 'Dedicated support engineer',
      free: false,
      growth: false,
      agency: false,
    },
    {
      name: 'API + Webhooks',
      free: false,
      growth: false,
      agency: false,
    },
    {
      name: 'Custom credits',
      free: false,
      growth: false,
      agency: false,
    },
    {
      name: 'Onboarding session',
      free: false,
      growth: false,
      agency: false,
    },
    {
      name: 'SSO + SCIM (coming soon)',
      free: false,
      growth: false,
      agency: false,
    },
  ];

  const renderFeatureValue = (value) => {
    if (value === true) {
      return (
        <svg
          className="w-5 h-5 text-emerald-500 mx-auto"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M5 13l4 4L19 7"
          />
        </svg>
      );
    }
    if (value === false) {
      return (
        <svg
          className="w-5 h-5 text-slate-300 mx-auto"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M6 18L18 6M6 6l12 12"
          />
        </svg>
      );
    }
    // Handle "Coming soon" with special styling
    if (value === 'Coming soon') {
      return (
        <span className="text-slate-500 font-normal text-xs font-inter text-center italic">
          Coming soon
        </span>
      );
    }
    return (
      <span className="text-slate-600 font-normal text-sm font-inter text-center">
        {value}
      </span>
    );
  };

  return (
    <div className="overflow-x-auto -mx-2 px-2">
      <table className="w-full border-collapse min-w-full">
        <thead>
          <tr className="border-b-2 border-slate-200">
            <th className="text-left py-4 px-6 font-semibold text-slate-900 font-inter text-sm">
              Features
            </th>
            <th className="text-center py-4 px-6 font-semibold text-slate-900 font-inter text-sm">
              Free
            </th>
            <th className="text-center py-4 px-6 font-semibold text-slate-900 font-inter text-sm">
              Growth
            </th>
            <th className="text-center py-4 px-6 font-semibold text-slate-900 font-inter text-sm">
              Agency
            </th>
          </tr>
        </thead>
        <tbody>
          {features.map((feature, index) => (
            <tr
              key={index}
              className="border-b border-slate-200 hover:bg-slate-50 transition-colors"
            >
              <td className="py-4 px-6 text-slate-700 font-normal text-sm font-inter text-left">
                {feature.name}
              </td>
              <td className="py-4 px-6">{renderFeatureValue(feature.free)}</td>
              <td className="py-4 px-6">{renderFeatureValue(feature.growth)}</td>
              <td className="py-4 px-6">{renderFeatureValue(feature.agency)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default ComparisonTable;
