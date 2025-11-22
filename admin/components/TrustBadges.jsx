import React, { useState, useEffect } from 'react';
import { SkeletonBox } from './SkeletonLoader';

/**
 * Trust Badges Component
 * Displays trust indicators and security badges
 */
const TrustBadges = ({ isLoading = false }) => {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    // Simulate loading state
    const timer = setTimeout(() => setMounted(true), 300);
    return () => clearTimeout(timer);
  }, []);

  if (isLoading || !mounted) {
    return (
      <div className="flex flex-wrap items-center gap-8">
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="flex items-center gap-3">
            <SkeletonBox className="w-6 h-6" />
            <SkeletonBox className="w-32 h-5" />
          </div>
        ))}
      </div>
    );
  }
  const badges = [
    {
      id: 'stripe-secure',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
          />
        </svg>
      ),
      text: 'Stripe Secure Payments',
    },
    {
      id: 'sca',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
          />
        </svg>
      ),
      text: 'SCA Compliant',
    },
    {
      id: 'uptime',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M13 10V3L4 14h7v7l9-11h-7z"
          />
        </svg>
      ),
      text: '99.9% Uptime',
    },
    {
      id: 'support',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
          />
        </svg>
      ),
      text: '24/7 Support',
    },
    {
      id: 'money-back',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      ),
      text: '30-Day Money Back',
    },
  ];

  return (
    <div className="flex flex-wrap items-center gap-8">
      {badges.map((badge) => (
        <div
          key={badge.id}
          className="flex items-center gap-3"
        >
          <div className="text-slate-600">{badge.icon}</div>
          <span className="text-sm text-slate-700 font-medium font-inter">{badge.text}</span>
        </div>
      ))}
    </div>
  );
};

export default TrustBadges;

