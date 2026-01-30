import React from 'react';

/**
 * SingleTokenEvent Component
 * Row component for showing detailed logs
 */
const SingleTokenEvent = ({ event, isExpanded, onToggle }) => {
  const formatTimestamp = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString();
  };

  const formatActionType = (actionType) => {
    const actions = {
      'generate': 'Generate',
      'regenerate': 'Regenerate',
      'bulk': 'Bulk Generate',
      'bulk-generate': 'Bulk Generate',
      'api': 'API Call',
    };
    return actions[actionType] || actionType;
  };

  const env = window.bbai_env || {};
  const uploadUrl = env.upload_url || (env.admin_url ? `${env.admin_url}upload.php` : 'upload.php');
  const postUrl = env.admin_url ? `${env.admin_url}post.php` : 'post.php';

  return (
    <tr 
      className={`hover:bg-slate-50 transition-colors cursor-pointer ${isExpanded ? 'bg-blue-50' : ''}`}
      onClick={onToggle}
    >
      <td className="px-4 py-3 text-sm">
        <div className="font-medium text-slate-900">{event.display_name}</div>
        <div className="text-slate-500 text-xs">({event.username})</div>
      </td>
      <td className="px-4 py-3 text-sm text-right font-medium text-slate-900">
        {event.tokens_used.toLocaleString()}
      </td>
      <td className="px-4 py-3 text-sm text-slate-700">
        {formatActionType(event.action_type)}
      </td>
      <td className="px-4 py-3 text-sm text-slate-600">
        {event.image_id ? (
          <a
            href={`${uploadUrl}?item=${event.image_id}`}
            className="text-blue-600 hover:text-blue-700"
            onClick={(e) => e.stopPropagation()}
          >
            #{event.image_id}
          </a>
        ) : (
          <span className="text-slate-400">—</span>
        )}
      </td>
      <td className="px-4 py-3 text-sm text-slate-600">
        {event.post_id ? (
          <a
            href={`${postUrl}?post=${event.post_id}&action=edit`}
            className="text-blue-600 hover:text-blue-700"
            onClick={(e) => e.stopPropagation()}
          >
            #{event.post_id}
          </a>
        ) : (
          <span className="text-slate-400">—</span>
        )}
      </td>
      <td className="px-4 py-3 text-sm text-slate-600">
        {formatTimestamp(event.created_at)}
      </td>
    </tr>
  );
};

export default SingleTokenEvent;
