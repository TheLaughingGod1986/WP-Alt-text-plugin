import React, { useState, useEffect, useMemo } from 'react';
import GrowthCtaBanner from '../shared/GrowthCtaBanner';

const DEFAULT_FILTERS = {
  user_id: '',
  date_from: '',
  date_to: '',
  action_type: '',
  page: 1,
  per_page: 50,
};

const formatUsageDate = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const getDateRangeDays = (start, end) => {
  if (!start || !end) return null;
  const startDate = new Date(start);
  const endDate = new Date(end);
  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
    return null;
  }
  const diffDays = Math.abs((endDate - startDate) / 86400000);
  return Math.max(1, diffDays);
};

const SummaryTile = ({ label, value, description, valueClassName = 'text-slate-900' }) => (
  <div className="rounded-2xl bg-slate-50 px-4 py-4">
    <p className="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase">{label}</p>
    <p className={`mt-2 text-2xl font-semibold ${valueClassName}`}>{value}</p>
    <p className="mt-1 text-xs text-slate-500">{description}</p>
  </div>
);

const GrowthPanel = ({ onStartTrial, onComparePlans }) => (
  <GrowthCtaBanner
    onStartTrial={onStartTrial}
    onComparePlans={onComparePlans}
    footerLines={[
      'Trusted by 10,000+ WordPress sites - WCAG and GDPR ready',
      'Free: 50 images/month - Growth: 1,000 images/month with bulk optimisation'
    ]}
  />
);

/**
 * MultiUserUsageTab Component
 * Admin-only tab showing full per-user usage dashboard
 */
const MultiUserUsageTab = ({ apiUrl, nonce }) => {
  const [usageData, setUsageData] = useState(null);
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filters
  const [filters, setFilters] = useState(() => ({ ...DEFAULT_FILTERS }));

  useEffect(() => {
    fetchUsageData();
    fetchEvents();
  }, [filters]);

  const fetchUsageData = async () => {
    try {
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
        users: Array.isArray(users) ? users : [],
      });
    } catch (err) {
      console.error('Error fetching usage data:', err);
    }
  };

  const fetchEvents = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      if (filters.user_id) params.append('user_id', filters.user_id);
      if (filters.date_from) params.append('from', filters.date_from);
      if (filters.date_to) params.append('to', filters.date_to);
      if (filters.action_type) params.append('action_type', filters.action_type);
      params.append('page', filters.page);
      params.append('per_page', filters.per_page);

      const response = await fetch(`${apiUrl}bbai/v1/usage/events?${params.toString()}`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch events');
      }

      const data = await response.json();
      setEvents(data.events || []);
      setError(null);
    } catch (err) {
      setError(err.message);
      console.error('Error fetching events:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
      page: 1,
    }));
  };

  const handleApplyFilters = () => {
    fetchUsageData();
    fetchEvents();
  };

  const handleClearFilters = () => {
    setFilters({ ...DEFAULT_FILTERS });
  };

  const handleStartGrowthTrial = () => {
    if (typeof window !== 'undefined' && typeof window.bbaiShowUpgradeModal === 'function') {
      window.bbaiShowUpgradeModal();
      return;
    }
    if (typeof window !== 'undefined' && typeof window.alttextaiShowUpgradeModal === 'function') {
      window.alttextaiShowUpgradeModal();
    }
  };

  const handleComparePlans = () => {
    if (typeof window !== 'undefined' && typeof window.openPricingModal === 'function') {
      window.openPricingModal('enterprise');
      return;
    }
    handleStartGrowthTrial();
  };

  const users = usageData?.users || [];
  const summary = useMemo(() => {
    const totalAllocated = Number(usageData?.total_allowed ?? 0);
    const totalUsed = Number(usageData?.total_used ?? 0);
    const remaining = Math.max(0, totalAllocated - totalUsed);
    const usagePercent = totalAllocated > 0 ? Math.round((totalUsed / totalAllocated) * 100) : 0;

    return {
      totalAllocated,
      totalUsed,
      remaining,
      usagePercent,
    };
  }, [usageData]);

  const usageRows = useMemo(() => {
    if (!Array.isArray(events) || events.length === 0) {
      return [];
    }

    const rows = new Map();
    events.forEach((event) => {
      const userId = event.user_id ?? event.username ?? event.display_name ?? 'system';
      const userName = event.display_name || event.username || 'System';
      const tokensUsed = Number(event.tokens_used) || 0;
      const lastActivityRaw = event.created_at || null;

      if (!rows.has(userId)) {
        rows.set(userId, {
          userId,
          userName,
          creditsUsed: 0,
          lastActivityRaw: null,
        });
      }

      const current = rows.get(userId);
      current.creditsUsed += tokensUsed;

      if (lastActivityRaw) {
        const lastDate = current.lastActivityRaw ? new Date(current.lastActivityRaw) : null;
        const nextDate = new Date(lastActivityRaw);
        if (!lastDate || nextDate > lastDate) {
          current.lastActivityRaw = lastActivityRaw;
        }
      }
    });

    const daysInRange = getDateRangeDays(filters.date_from, filters.date_to);

    return Array.from(rows.values())
      .map((row) => ({
        userId: row.userId,
        userName: row.userName,
        creditsUsed: row.creditsUsed,
        lastActivity: formatUsageDate(row.lastActivityRaw),
        avgPerDay: daysInRange ? (row.creditsUsed / daysInRange).toFixed(1) : '-',
      }))
      .sort((a, b) => b.creditsUsed - a.creditsUsed);
  }, [events, filters.date_from, filters.date_to]);

  const inputClassName =
    'w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white md:text-sm';

  const emptyStateMessage = loading
    ? 'Loading usage data...'
    : error
      ? `Error loading usage data: ${error}`
      : 'No usage data found for the selected filters.';

  const emptyStateClassName = error ? 'mt-4 text-xs text-rose-600' : 'mt-4 text-xs text-slate-500';

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-6 md:px-6">
      <div className="mx-auto max-w-6xl space-y-6 md:space-y-8">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold text-slate-900">Credit Usage</h1>
          <p className="text-sm text-slate-600">
            Track your credit usage, view detailed activity by user, and monitor your monthly quota.
          </p>
        </header>

        <section className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <SummaryTile
              label="Total Credits Allocated"
              value={summary.totalAllocated.toLocaleString()}
              description="Plan quota this month"
            />
            <SummaryTile
              label="Total Credits Used"
              value={summary.totalUsed.toLocaleString()}
              description="Generations this month"
            />
            <SummaryTile
              label="Credits Remaining"
              value={summary.remaining.toLocaleString()}
              description="Image descriptions left"
              valueClassName="text-emerald-600"
            />
            <SummaryTile
              label="Usage Percentage"
              value={`${summary.usagePercent}%`}
              description="of monthly credits used"
            />
          </div>
        </section>

        <section className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
          <h2 className="text-base font-semibold text-slate-900">Filter usage</h2>
          <div className="mt-4 grid gap-3 md:grid-cols-4 text-xs md:text-sm">
            <div className="space-y-2">
              <label className="text-xs font-semibold text-slate-500 uppercase tracking-[0.18em]">
                Date From
              </label>
              <input
                type="date"
                value={filters.date_from}
                onChange={(e) => handleFilterChange('date_from', e.target.value)}
                className={inputClassName}
              />
            </div>
            <div className="space-y-2">
              <label className="text-xs font-semibold text-slate-500 uppercase tracking-[0.18em]">
                Date To
              </label>
              <input
                type="date"
                value={filters.date_to}
                onChange={(e) => handleFilterChange('date_to', e.target.value)}
                className={inputClassName}
              />
            </div>
            <div className="space-y-2">
              <label className="text-xs font-semibold text-slate-500 uppercase tracking-[0.18em]">
                Source
              </label>
              <select
                value={filters.action_type}
                onChange={(e) => handleFilterChange('action_type', e.target.value)}
                className={inputClassName}
              >
                <option value="">All</option>
                <option value="generate">Generate</option>
                <option value="regenerate">Regenerate</option>
                <option value="bulk">Bulk Generate</option>
                <option value="api">API Call</option>
              </select>
            </div>
            <div className="space-y-2">
              <label className="text-xs font-semibold text-slate-500 uppercase tracking-[0.18em]">
                User
              </label>
              <select
                value={filters.user_id}
                onChange={(e) => handleFilterChange('user_id', e.target.value)}
                className={inputClassName}
              >
                <option value="">All Users</option>
                {users.map((user) => (
                  <option key={user.user_id} value={user.user_id}>
                    {user.display_name} ({user.username})
                  </option>
                ))}
              </select>
            </div>
          </div>
          <div className="mt-4 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={handleApplyFilters}
              className="inline-flex items-center justify-center rounded-full bg-sky-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2"
            >
              Filter
            </button>
            <button
              type="button"
              onClick={handleClearFilters}
              className="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-medium text-slate-900 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2"
            >
              Clear
            </button>
          </div>
        </section>

        <section className="rounded-3xl bg-white shadow-xl px-6 py-5 md:px-7 md:py-6">
          <h2 className="text-base font-semibold text-slate-900">Usage by User</h2>
          <div className="mt-3 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600 flex gap-2">
            <span className="mt-[2px] inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px]">
              i
            </span>
            <p>
              The summary cards above show your current usage from the backend. The table below shows historical
              WordPress user activity for the selected filters.
            </p>
          </div>

          <div className="bbai-card bbai-mt-4">
            <div className="bbai-table-wrap">
              <table className="bbai-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Credits Used</th>
                    <th>Last Activity</th>
                    <th>Avg Per Day</th>
                  </tr>
                </thead>
                <tbody>
                  {usageRows.length > 0 ? (
                    usageRows.map((row) => (
                    <tr key={row.userId}>
                        <td className="bbai-text-base">{row.userName}</td>
                        <td className="bbai-text-base">{row.creditsUsed.toLocaleString()}</td>
                        <td className="bbai-text-muted">{row.lastActivity || '—'}</td>
                        <td className="bbai-text-muted">{row.avgPerDay || '—'}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="4" className="bbai-text-center bbai-text-muted bbai-text-sm">
                        {emptyStateMessage}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <GrowthPanel onStartTrial={handleStartGrowthTrial} onComparePlans={handleComparePlans} />
      </div>
    </div>
  );
};

export default MultiUserUsageTab;
