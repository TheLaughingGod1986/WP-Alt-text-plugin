import React from 'react';

const VARIANTS = {
  dashboard: {
    container: 'rounded-3xl bg-white shadow-sm px-6 py-5 flex flex-col gap-2',
    value: 'text-3xl font-semibold text-slate-900',
    label: 'text-[15px] font-semibold text-slate-900',
    description: 'text-[13px] leading-relaxed text-slate-500'
  },
  library: {
    container: 'rounded-3xl bg-white shadow-sm px-5 py-4 flex flex-col gap-2',
    value: 'text-2xl font-semibold text-slate-900',
    label: 'text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase',
    description: 'text-xs text-slate-500'
  },
  analytics: {
    container: 'flex items-start gap-3',
    value: 'text-2xl font-semibold text-slate-900',
    label: 'text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase',
    description: 'text-xs text-slate-500'
  }
};

const KpiTile = ({
  label,
  value,
  description,
  icon = null,
  variant = 'dashboard',
  className = '',
  iconClassName = '',
  valueClassName = '',
  labelClassName = '',
  descriptionClassName = ''
}) => {
  const styles = VARIANTS[variant] || VARIANTS.dashboard;

  if (variant === 'analytics') {
    return (
      <div className={`${styles.container} ${className}`}>
        {icon ? (
          <span
            className={`flex h-10 w-10 items-center justify-center rounded-xl ${iconClassName}`}
            aria-hidden="true"
          >
            {icon}
          </span>
        ) : null}
        <div className="flex-1 space-y-1">
          {label !== null && label !== undefined && label !== '' ? (
            <p className={`${styles.label} ${labelClassName}`}>{label}</p>
          ) : null}
          <p className={`${styles.value} ${valueClassName}`}>{value}</p>
          {description ? (
            <p className={`${styles.description} ${descriptionClassName}`}>{description}</p>
          ) : null}
        </div>
      </div>
    );
  }

  return (
    <div className={`${styles.container} ${className}`}>
      <p className={`${styles.value} ${valueClassName}`}>{value}</p>
      <p className={`${styles.label} ${labelClassName}`}>{label}</p>
      {description ? (
        <p className={`${styles.description} ${descriptionClassName}`}>{description}</p>
      ) : null}
    </div>
  );
};

export default KpiTile;
