import React, { useState, useEffect } from 'react';
import SingleTokenEvent from './SingleTokenEvent';

/**
 * MultiUserUsageTab Component
 * Admin-only tab showing full per-user usage dashboard
 */
const MultiUserUsageTab = ({ apiUrl, nonce }) => {
  const [usageData, setUsageData] = useState(null);
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [expandedEvent, setExpandedEvent] = useState(null);
  
  // Filters
  const [filters, setFilters] = useState({
    user_id: '',
    date_from: '',
    date_to: '',
    action_type: '',
    page: 1,
    per_page: 50,
  });

  const [pagination, setPagination] = useState({
    total: 0,
    pages: 0,
    page: 1,
  });

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
      setPagination({
        total: data.total || 0,
        pages: data.pages || 0,
        page: data.page || 1,
      });
      setError(null);
    } catch (err) {
      setError(err.message);
      console.error('Error fetching events:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key, value) => {
    setFilters(prev => ({
      ...prev,
      [key]: value,
      page: 1, // Reset to first page on filter change
    }));
  };

  const handlePageChange = (newPage) => {
    setFilters(prev => ({
      ...prev,
      page: newPage,
    }));
  };

  const toggleEvent = (eventId) => {
    setExpandedEvent(expandedEvent === eventId ? null : eventId);
  };

  // Get all users for filter dropdown
  const users = usageData?.users || [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-900 mb-2">Team Usage</h2>
        <p className="text-slate-600">Monitor token usage across all team members</p>
      </div>

      {/* Usage Summary Cards */}
      {usageData && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-white border border-slate-200 rounded-lg p-4">
            <div className="text-sm text-slate-600 mb-1">Total Used</div>
            <div className="text-2xl font-bold text-slate-900">
              {usageData.total_used.toLocaleString()}
            </div>
          </div>
          <div className="bg-white border border-slate-200 rounded-lg p-4">
            <div className="text-sm text-slate-600 mb-1">Total Allowed</div>
            <div className="text-2xl font-bold text-slate-900">
              {usageData.total_allowed.toLocaleString()}
            </div>
          </div>
          <div className="bg-white border border-slate-200 rounded-lg p-4">
            <div className="text-sm text-slate-600 mb-1">Remaining</div>
            <div className="text-2xl font-bold text-green-600">
              {Math.max(0, usageData.total_allowed - usageData.total_used).toLocaleString()}
            </div>
          </div>
        </div>
      )}

      {/* Per-User Bar Chart */}
      {usageData && usageData.users && usageData.users.length > 0 && (
        <div className="bg-white border border-slate-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-slate-900 mb-4">Usage by User</h3>
          <div className="space-y-3">
            {usageData.users.map((user, index) => {
              const maxTokens = Math.max(...usageData.users.map(u => u.tokens_used), 1);
              const percentage = (user.tokens_used / maxTokens) * 100;
              
              return (
                <div key={user.user_id || index}>
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-sm font-medium text-slate-900">
                      {user.display_name} ({user.username})
                    </span>
                    <span className="text-sm text-slate-600">
                      {user.tokens_used.toLocaleString()} tokens
                    </span>
                  </div>
                  <div className="w-full h-4 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all duration-500"
                      style={{ width: `${percentage}%` }}
                    ></div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white border border-slate-200 rounded-lg p-4">
        <h3 className="text-lg font-semibold text-slate-900 mb-4">Filters</h3>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">
              User
            </label>
            <select
              value={filters.user_id}
              onChange={(e) => handleFilterChange('user_id', e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Users</option>
              {users.map((user) => (
                <option key={user.user_id} value={user.user_id}>
                  {user.display_name} ({user.username})
                </option>
              ))}
            </select>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">
              Date From
            </label>
            <input
              type="date"
              value={filters.date_from}
              onChange={(e) => handleFilterChange('date_from', e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">
              Date To
            </label>
            <input
              type="date"
              value={filters.date_to}
              onChange={(e) => handleFilterChange('date_to', e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">
              Action Type
            </label>
            <select
              value={filters.action_type}
              onChange={(e) => handleFilterChange('action_type', e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Actions</option>
              <option value="generate">Generate</option>
              <option value="regenerate">Regenerate</option>
              <option value="bulk">Bulk Generate</option>
              <option value="api">API Call</option>
            </select>
          </div>
        </div>
        
        <div className="mt-4 flex gap-2">
          <button
            onClick={() => setFilters({
              user_id: '',
              date_from: '',
              date_to: '',
              action_type: '',
              page: 1,
              per_page: 50,
            })}
            className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
          >
            Clear Filters
          </button>
        </div>
      </div>

      {/* Events Table */}
      <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200">
          <h3 className="text-lg font-semibold text-slate-900">Event Log</h3>
        </div>
        
        {loading ? (
          <div className="p-8 text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p className="mt-2 text-sm text-slate-600">Loading events...</p>
          </div>
        ) : error ? (
          <div className="p-8 text-center">
            <p className="text-red-600">Error loading events: {error}</p>
          </div>
        ) : events.length === 0 ? (
          <div className="p-8 text-center">
            <div className="text-slate-400 mb-2">
              <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <p className="text-slate-600">No usage recorded yet.</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">
                      Username
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-semibold text-slate-700 uppercase tracking-wider">
                      Tokens Used
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">
                      Action
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">
                      Image ID
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">
                      Post ID
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">
                      Timestamp
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-slate-200">
                  {events.map((event) => (
                    <SingleTokenEvent
                      key={event.id}
                      event={event}
                      isExpanded={expandedEvent === event.id}
                      onToggle={() => toggleEvent(event.id)}
                    />
                  ))}
                </tbody>
              </table>
            </div>
            
            {/* Pagination */}
            {pagination.pages > 1 && (
              <div className="px-6 py-4 border-t border-slate-200 flex items-center justify-between">
                <div className="text-sm text-slate-600">
                  Showing page {pagination.page} of {pagination.pages} ({pagination.total} total events)
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handlePageChange(pagination.page - 1)}
                    disabled={pagination.page <= 1}
                    className="px-3 py-1 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => handlePageChange(pagination.page + 1)}
                    disabled={pagination.page >= pagination.pages}
                    className="px-3 py-1 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default MultiUserUsageTab;

