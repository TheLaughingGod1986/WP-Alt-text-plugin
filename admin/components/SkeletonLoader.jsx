import React from 'react';

/**
 * Skeleton Loader Component
 * Provides loading placeholder animations
 */
export const SkeletonBox = ({ className = '' }) => (
  <div
    className={`animate-pulse bg-slate-200 rounded ${className}`}
    role="status"
    aria-label="Loading"
  >
    <span className="sr-only">Loading...</span>
  </div>
);

export default SkeletonBox;

