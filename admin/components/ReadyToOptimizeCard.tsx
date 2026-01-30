import React from 'react';

interface ReadyToOptimizeCardProps {
  onGoToMediaLibrary?: () => void;
  onLearnMore?: () => void;
}

const ReadyToOptimizeCard: React.FC<ReadyToOptimizeCardProps> = ({
  onGoToMediaLibrary,
  onLearnMore,
}) => {
  const handleGoToMediaLibrary = () => {
    if (onGoToMediaLibrary) {
      onGoToMediaLibrary();
    } else {
      const env = (window as any).bbai_env || {};
      const uploadUrl = env.upload_url || (env.admin_url ? `${env.admin_url}upload.php` : 'upload.php');
      if (uploadUrl) {
        window.location.href = uploadUrl;
      }
    }
  };

  const handleLearnMore = () => {
    if (onLearnMore) {
      onLearnMore();
    } else {
      const guideTab = document.querySelector('[data-tab="guide"]');
      if (guideTab) {
        (guideTab as HTMLElement).click();
      }
    }
  };

  return (
    <div className="rounded-3xl bg-white shadow-xl p-10">
      {/* Badge */}
      <div className="flex mb-6">
        <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-gray-600">
          GETTING STARTED
        </span>
      </div>

      {/* Main Title */}
      <h3 className="text-[28px] font-semibold leading-tight text-gray-900 mb-4">
        You're ready to optimise your images
      </h3>

      {/* Description */}
      <p className="text-[15px] leading-relaxed text-gray-700 mb-6">
        Generate SEO-friendly, WCAG-compliant alt text automatically. Boost discoverability, rankings, and compliance in a single workflow.
      </p>

      {/* 3-Tile Workflow Cards */}
      <div className="grid grid-cols-3 gap-4 mb-8">
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm p-4 text-center">
          <div className="w-12 h-12 flex items-center justify-center mx-auto mb-3 rounded-lg bg-blue-50">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-blue-600">
              <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M14 2V8H20" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M16 13H8" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M16 17H8" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M10 9H8" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <div className="text-[13px] font-semibold text-gray-900 mb-1">Editorial</div>
          <div className="text-[11px] font-normal text-gray-600">SEO workflows</div>
        </div>
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm p-4 text-center">
          <div className="w-12 h-12 flex items-center justify-center mx-auto mb-3 rounded-lg bg-purple-50">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-purple-600">
              <path d="M12 2L2 7L12 12L22 7L12 2Z" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M2 17L12 22L22 17" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M2 12L12 17L22 12" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <div className="text-[13px] font-semibold text-gray-900 mb-1">Ecommerce</div>
          <div className="text-[11px] font-normal text-gray-600">Catalogue media</div>
        </div>
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm p-4 text-center">
          <div className="w-12 h-12 flex items-center justify-center mx-auto mb-3 rounded-lg bg-green-50">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-green-600">
              <path d="M3 3V21H21" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M7 16L12 11L16 15L21 10" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M21 10V3H7" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <div className="text-[13px] font-semibold text-gray-900 mb-1">Reporting</div>
          <div className="text-[11px] font-normal text-gray-600">Client SEO metrics</div>
        </div>
      </div>

      {/* Benefits Section */}
      <div className="space-y-3 mb-6">
        <div className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
            <svg
              className="h-4 w-4 text-green-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M13 4L6 11L3 8" />
            </svg>
          </div>
          <div>
            <div className="text-[14px] font-semibold text-gray-900">Save 12+ hours/month</div>
            <div className="text-[13px] font-normal text-gray-600">on alt text & compliance tasks</div>
          </div>
        </div>
        <div className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
            <svg
              className="h-4 w-4 text-green-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M13 4L6 11L3 8" />
            </svg>
          </div>
          <div>
            <div className="text-[14px] font-semibold text-gray-900">Improve search & Google Images visibility</div>
            <div className="text-[13px] font-normal text-gray-600">for better rankings and traffic</div>
          </div>
        </div>
        <div className="flex items-start gap-3">
          <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
            <svg
              className="h-4 w-4 text-green-600"
              fill="none"
              viewBox="0 0 16 16"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M13 4L6 11L3 8" />
            </svg>
          </div>
          <div>
            <div className="text-[14px] font-semibold text-gray-900">WCAG/ADA compliant alt text coverage</div>
            <div className="text-[13px] font-normal text-gray-600">to support accessibility standards</div>
          </div>
        </div>
      </div>

      {/* Example Alt Text Box */}
      <div className="rounded-2xl bg-emerald-50 border-l-4 border-emerald-400 px-4 py-3 mb-6">
        <div className="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
          EXAMPLE ALT TEXT
        </div>
        <p className="text-[14px] leading-relaxed text-gray-800 italic">
          A vibrant sunset over a calm ocean with orange and pink hues reflecting on the water
        </p>
      </div>

      {/* CTA Buttons */}
      <div className="flex flex-col sm:flex-row gap-3 mb-3">
        <button
          onClick={handleGoToMediaLibrary}
          className="flex-1 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-3 text-[15px] font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:translate-y-0 flex items-center justify-center gap-2"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <path d="M8 2V14M2 8H14" />
          </svg>
          <span>+ Go to Media Library</span>
        </button>
        <button
          onClick={handleLearnMore}
          className="flex-1 sm:flex-initial rounded-full bg-white border-2 border-gray-300 px-6 py-3 text-[15px] font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-4 focus:ring-gray-300/30 active:translate-y-0"
        >
          Learn More
        </button>
      </div>

      {/* Footer Text */}
      <p className="text-[12px] font-normal text-gray-500 leading-relaxed text-center">
        It only takes one upload to start improving SEO visibility.
      </p>
    </div>
  );
};

export default ReadyToOptimizeCard;
