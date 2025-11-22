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
      pro: '1,000',
      agency: '10,000',
      enterprise: 'Unlimited',
    },
    {
      name: 'Priority queue',
      pro: true,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Bulk optimisation',
      pro: true,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Advanced SEO scoring',
      pro: true,
      agency: true,
      enterprise: true,
    },
    {
      name: 'API access',
      pro: true,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Bulk queue unlimited images',
      pro: false,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Faster model tier',
      pro: false,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Priority support',
      pro: false,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Multi-user support',
      pro: false,
      agency: true,
      enterprise: true,
    },
    {
      name: 'Dedicated queue',
      pro: false,
      agency: false,
      enterprise: true,
    },
    {
      name: 'SLA (99.9%)',
      pro: false,
      agency: false,
      enterprise: true,
    },
    {
      name: 'Dedicated support engineer',
      pro: false,
      agency: false,
      enterprise: true,
    },
    {
      name: 'API + Webhooks',
      pro: false,
      agency: false,
      enterprise: true,
    },
    {
      name: 'Custom credits',
      pro: false,
      agency: false,
      enterprise: true,
    },
    {
      name: 'Onboarding session',
      pro: false,
      agency: false,
      enterprise: true,
    },
    {
      name: 'SSO + SCIM (coming soon)',
      pro: false,
      agency: false,
      enterprise: 'Coming soon',
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
              Pro
            </th>
            <th className="text-center py-4 px-6 font-semibold text-slate-900 font-inter text-sm">
              Agency
            </th>
            <th className="text-center py-4 px-6 font-semibold text-slate-900 font-inter text-sm">
              Enterprise
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
              <td className="py-4 px-6">{renderFeatureValue(feature.pro)}</td>
              <td className="py-4 px-6">{renderFeatureValue(feature.agency)}</td>
              <td className="py-4 px-6">{renderFeatureValue(feature.enterprise)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default ComparisonTable;

