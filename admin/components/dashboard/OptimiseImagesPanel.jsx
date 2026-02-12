import React, { useState } from 'react';

/**
 * Optimise Images Panel
 * Shows queue stats and action buttons for image optimization
 * Includes helpful empty states and loading states
 */
const OptimiseImagesPanel = ({
  inQueue = 0,
  runToday = 0,
  needsAttention = 0,
  canGenerate = true,
  onOptimiseNew = null,
  onOptimiseAll = null,
  onOpenLibrary = null,
  isLoading = false
}) => {
  const [isGenerating, setIsGenerating] = useState(false);

  const handleOptimiseNew = async () => {
    if (!onOptimiseNew || isLoading || isGenerating) return;
    setIsGenerating(true);
    try {
      await onOptimiseNew();
    } finally {
      setTimeout(() => setIsGenerating(false), 2000); // Reset after 2s
    }
  };

  const handleOptimiseAll = async () => {
    if (!onOptimiseAll || isLoading || isGenerating) return;
    setIsGenerating(true);
    try {
      await onOptimiseAll();
    } finally {
      setTimeout(() => setIsGenerating(false), 2000);
    }
  };

  const hasNoActivity = inQueue === 0 && runToday === 0 && needsAttention === 0;
  const isProcessing = isLoading || isGenerating;

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
      {/* Header */}
      <div className="mb-6">
        <h2 className="text-xl font-bold text-slate-900 mb-2">Optimise Images</h2>
        <p className="text-sm text-slate-600">
          Run AI alt text on new uploads or bulk optimise from your ALT Library.
        </p>
      </div>

      {/* Queue Stats */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="text-center">
          <div className="text-2xl font-bold text-slate-900 mb-1">
            {inQueue.toLocaleString()}
          </div>
          <div className="text-xs text-slate-600">In Queue</div>
        </div>
        <div className="text-center">
          <div className="text-2xl font-bold text-slate-900 mb-1">
            {runToday.toLocaleString()}
          </div>
          <div className="text-xs text-slate-600">Run Today</div>
        </div>
        <div className="text-center">
          <div className={`text-2xl font-bold mb-1 ${needsAttention > 0 ? 'text-amber-600' : 'text-slate-900'}`}>
            {needsAttention.toLocaleString()}
          </div>
          <div className="text-xs text-slate-600">Needs Attention</div>
        </div>
      </div>

      {/* Empty State Message */}
      {hasNoActivity && !isProcessing && (
        <div className="bg-slate-50 rounded-lg p-4 mb-6 border border-slate-200">
          <p className="text-sm text-slate-600 mb-2">
            We will list images that need AI alt text here. Start by scanning your library.
          </p>
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex flex-wrap gap-3">
        <button
          onClick={handleOptimiseNew}
          disabled={!canGenerate || isProcessing}
          {...((!canGenerate || isProcessing) && {
            'data-bbai-tooltip': isProcessing
              ? 'Processing, please wait...'
              : 'Upgrade to unlock more generations',
            'data-bbai-tooltip-position': 'top'
          })}
          className={`
            flex-1 min-w-[160px] px-6 py-3 rounded-lg font-semibold transition-all
            ${canGenerate && !isProcessing
              ? 'bg-gradient-to-r from-teal-600 to-blue-600 text-white shadow-md hover:shadow-lg hover:-translate-y-0.5'
              : 'bg-slate-200 text-slate-400 cursor-not-allowed'
            }
          `}
        >
          {isProcessing ? (
            <span className="flex items-center justify-center">
              <svg className="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
              </svg>
              Processing...
            </span>
          ) : (
            'Optimise New Images'
          )}
        </button>

        <button
          onClick={handleOptimiseAll}
          disabled={!canGenerate || isProcessing}
          {...((!canGenerate || isProcessing) && {
            'data-bbai-tooltip': isProcessing
              ? 'Processing, please wait...'
              : 'Upgrade to unlock more generations',
            'data-bbai-tooltip-position': 'top'
          })}
          className={`
            flex-1 min-w-[160px] px-6 py-3 rounded-lg font-semibold border-2 transition-all
            ${canGenerate && !isProcessing
              ? 'border-slate-300 text-slate-700 hover:border-slate-400 hover:bg-slate-50'
              : 'border-slate-200 text-slate-400 cursor-not-allowed'
            }
          `}
        >
          Optimise All Images Again
        </button>

        {onOpenLibrary && (
          <button
            onClick={onOpenLibrary}
            className="px-6 py-3 rounded-lg font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 transition-colors"
          >
            Open ALT Library
          </button>
        )}
      </div>

      {/* Disabled State Message */}
      {!canGenerate && (
        <p className="text-xs text-amber-600 mt-3 flex items-center">
          <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          You've reached your quota limit. Upgrade to continue optimising.
        </p>
      )}
    </div>
  );
};

export default OptimiseImagesPanel;