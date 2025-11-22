import React from 'react';

/**
 * Multi-User Notice Component
 * Displays information about multi-user and team features
 */
const MultiUserNotice = () => {
  return (
    <div className="bg-slate-50 border border-slate-200 rounded-xl p-6">
      <div className="flex items-start gap-4">
        <div className="flex-shrink-0 mt-0.5">
          <svg
            className="w-6 h-6 text-blue-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div className="flex-1">
          <h4 className="text-lg font-semibold text-slate-900 mb-2 font-inter text-left">
            Managing Multiple Sites or Teams?
          </h4>
          <p className="text-slate-600 font-normal text-sm font-inter leading-relaxed mb-4 text-left">
            Agency and Enterprise plans include multi-site management, user
            roles, and team collaboration features. Perfect for agencies
            managing multiple client sites or organizations with multiple
            WordPress installations.
          </p>
          <ul className="space-y-2 mb-4">
            <li className="flex items-start">
              <svg
                className="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0"
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
              <span className="text-slate-700 font-normal text-sm font-inter">Manage unlimited sites from one dashboard</span>
            </li>
            <li className="flex items-start">
              <svg
                className="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0"
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
              <span className="text-slate-700 font-normal text-sm font-inter">Role-based access control</span>
            </li>
            <li className="flex items-start">
              <svg
                className="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0"
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
              <span className="text-slate-700 font-normal text-sm font-inter">Centralized billing and usage analytics</span>
            </li>
          </ul>
          <button
            className="text-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded px-2 py-1 font-medium text-sm font-inter transition-colors"
            tabIndex={0}
            aria-label="Learn more about multi-site features"
          >
            Learn more about multi-site features →
          </button>
        </div>
      </div>
    </div>
  );
};

export default MultiUserNotice;

