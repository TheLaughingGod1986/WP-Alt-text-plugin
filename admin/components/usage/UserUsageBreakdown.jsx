import React, { useState, useEffect } from 'react';

/**
 * UserUsageBreakdown Component
 * Collapsible panel showing per-user breakdown
 */
const UserUsageBreakdown = ({ apiUrl, nonce }) => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchUserData();
  }, []);

  const fetchUserData = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${apiUrl}bbai/v1/usage/by-user`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch user usage data');
      }

      const data = await response.json();
      setUsers(Array.isArray(data) ? data : []);
      setError(null);
    } catch (err) {
      setError(err.message);
      console.error('Error fetching user usage data:', err);
    } finally {
      setLoading(false);
    }
  };

  const formatTimeAgo = (dateString) => {
    if (!dateString) return 'Never';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} ${diffMins === 1 ? 'minute' : 'minutes'} ago`;
    if (diffHours < 24) return `${diffHours} ${diffHours === 1 ? 'hour' : 'hours'} ago`;
    if (diffDays === 1) return 'yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    
    return date.toLocaleDateString();
  };

  if (loading) {
    return (
      <div className="mt-4 animate-pulse">
        <div className="h-4 bg-slate-200 rounded w-1/4 mb-3"></div>
        <div className="space-y-2">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-12 bg-slate-100 rounded"></div>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
        <p className="text-red-600 text-sm">Error loading user data: {error}</p>
      </div>
    );
  }

  if (users.length === 0) {
    return (
      <div className="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-lg text-center">
        <p className="text-slate-600 text-sm">No usage recorded yet.</p>
      </div>
    );
  }

  return (
    <div className="mt-4 border border-slate-200 rounded-lg overflow-hidden">
      <div className="bg-slate-50 px-4 py-3 border-b border-slate-200">
        <h4 className="text-sm font-semibold text-slate-900">Usage by User</h4>
      </div>
      
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">
                User
              </th>
              <th className="px-4 py-3 text-right text-xs font-semibold text-slate-700 uppercase tracking-wider">
                Credits Used
              </th>
              <th className="px-4 py-3 text-right text-xs font-semibold text-slate-700 uppercase tracking-wider">
                Last Use
              </th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-slate-200">
            {users.map((user, index) => (
              <tr key={user.user_id || index} className="hover:bg-slate-50 transition-colors">
                <td className="px-4 py-3 text-sm">
                  <div className="font-medium text-slate-900">{user.display_name}</div>
                  <div className="text-slate-500 text-xs">({user.username})</div>
                </td>
                <td className="px-4 py-3 text-sm text-right font-medium text-slate-900">
                  {(user.credits_used ?? user.tokens_used ?? 0).toLocaleString()}
                </td>
                <td className="px-4 py-3 text-sm text-right text-slate-600">
                  {formatTimeAgo(user.last_used)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default UserUsageBreakdown;
