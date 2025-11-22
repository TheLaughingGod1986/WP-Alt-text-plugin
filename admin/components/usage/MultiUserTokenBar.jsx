import React, { useState, useEffect } from 'react';
import UserUsageBreakdown from './UserUsageBreakdown';

/**
 * MultiUserTokenBar Component
 * Visual dashboard component showing shared token usage
 */
const MultiUserTokenBar = ({ apiUrl, nonce }) => {
  const [usageData, setUsageData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showBreakdown, setShowBreakdown] = useState(false);

  useEffect(() => {
    fetchUsageData();
  }, []);

  const fetchUsageData = async () => {
    try {
      setLoading(true);
      
      // Fetch usage summary and user breakdown separately
      const [summaryResponse, usersResponse] = await Promise.all([
        fetch(`${apiUrl}bbai/v1/usage/summary`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
        fetch(`${apiUrl}bbai/v1/usage/by-user`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
      ]);

      if (!summaryResponse.ok || !usersResponse.ok) {
        throw new Error('Failed to fetch usage data');
      }

      const summary = await summaryResponse.json();
      const users = await usersResponse.json();
      
      setUsageData({
        total_used: summary.total_used || 0,
        total_allowed: summary.total_limit || 50,
        users: users || [],
      });
      setError(null);
    } catch (err) {
      setError(err.message);
      console.error('Error fetching usage data:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="bg-white border border-slate-200 rounded-xl p-6 animate-pulse">
        <div className="h-4 bg-slate-200 rounded w-1/3 mb-4"></div>
        <div className="h-8 bg-slate-200 rounded mb-2"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white border border-red-200 rounded-xl p-6">
        <p className="text-red-600">Error loading usage data: {error}</p>
      </div>
    );
  }

  if (!usageData) {
    return null;
  }

  const { total_used = 0, total_allowed = 50 } = usageData;
  const percentage = total_allowed > 0 ? Math.round((total_used / total_allowed) * 100) : 0;
  const remaining = Math.max(0, total_allowed - total_used);

  return (
    <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
      {/* Progress Bar */}
      <div className="mb-4">
        <div className="flex items-center justify-between mb-2">
          <h3 className="text-lg font-semibold text-slate-900">
            Monthly Token Usage
          </h3>
          <span className="text-sm font-medium text-slate-600">
            {percentage}% of monthly tokens used
          </span>
        </div>
        
        {/* Large Progress Bar */}
        <div className="w-full h-8 bg-slate-100 rounded-full overflow-hidden">
          <div
            className="h-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all duration-500 ease-out flex items-center justify-end pr-2"
            style={{ width: `${Math.min(percentage, 100)}%` }}
          >
            {percentage > 10 && (
              <span className="text-xs font-semibold text-white">
                {total_used} / {total_allowed}
              </span>
            )}
          </div>
        </div>
        
        <div className="flex items-center justify-between mt-2 text-sm text-slate-600">
          <span>{total_used} used</span>
          <span>{remaining} remaining</span>
        </div>
      </div>

      {/* Shared Quota Notice */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
        <p className="text-sm text-blue-800">
          <strong>Shared Quota:</strong> This monthly quota is shared across all users on <strong>this WordPress site</strong>. Upgrade to Pro for 1,000 generations per month.
        </p>
      </div>

      {/* Toggle Link */}
      <button
        onClick={() => setShowBreakdown(!showBreakdown)}
        className="text-blue-600 hover:text-blue-700 font-medium text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded px-2 py-1"
      >
        {showBreakdown ? 'Hide' : 'View'} usage by user →
      </button>

      {/* User Breakdown */}
      {showBreakdown && (
        <div className="mt-4">
          <UserUsageBreakdown apiUrl={apiUrl} nonce={nonce} />
        </div>
      )}
    </div>
  );
};

export default MultiUserTokenBar;

