/**
 * BeepBeep AI Analytics Dashboard
 * Charts, timelines, and data visualization
 */

(function() {
    'use strict';

    const bbaiAnalytics = {
        chart: null,

        init: function() {
            this.initChart();
            this.loadActivityTimeline();
            this.bindEvents();
        },

        /**
         * Initialize coverage chart
         */
        initChart: function() {
            const canvas = document.getElementById('bbai-coverage-chart');
            if (!canvas || !window.bbaiAnalyticsData) return;

            const data = window.bbaiAnalyticsData.coverage || [];
            if (data.length === 0) return;

            const container = canvas.parentElement;
            const containerWidth = container ? container.clientWidth : (canvas.width || 800);
            const containerHeight = 300;
            const dpr = window.devicePixelRatio || 1;

            canvas.width = containerWidth * dpr;
            canvas.height = containerHeight * dpr;
            canvas.style.width = containerWidth + 'px';
            canvas.style.height = containerHeight + 'px';

            const ctx = canvas.getContext('2d');
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(dpr, dpr);

            const width = containerWidth;
            const height = containerHeight;
            const padding = 40;
            const chartWidth = width - (padding * 2);
            const chartHeight = height - (padding * 2);

            ctx.clearRect(0, 0, width, height);

            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 1;
            const gridLines = 5;
            for (let i = 0; i <= gridLines; i++) {
                const y = padding + (chartHeight / gridLines) * i;
                ctx.beginPath();
                ctx.moveTo(padding, y);
                ctx.lineTo(width - padding, y);
                ctx.stroke();
            }

            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(padding, padding);
            ctx.lineTo(padding, height - padding);
            ctx.lineTo(width - padding, height - padding);
            ctx.stroke();

            const maxValue = Math.max(100, Math.max.apply(null, data.map(item => item.coverage || 0)));
            const points = data.map((item, index) => {
                const x = padding + (chartWidth / Math.max(1, data.length - 1)) * index;
                const y = height - padding - ((item.coverage || 0) / maxValue) * chartHeight;
                return { x, y, label: item.date || '', value: item.coverage || 0 };
            });

            const gradient = ctx.createLinearGradient(0, padding, 0, height - padding);
            gradient.addColorStop(0, 'rgba(96, 165, 250, 0.28)');
            gradient.addColorStop(1, 'rgba(96, 165, 250, 0.04)');
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.moveTo(points[0].x, height - padding);
            points.forEach(point => ctx.lineTo(point.x, point.y));
            ctx.lineTo(points[points.length - 1].x, height - padding);
            ctx.closePath();
            ctx.fill();

            ctx.strokeStyle = '#60a5fa';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            for (let i = 1; i < points.length; i++) {
                ctx.lineTo(points[i].x, points[i].y);
            }
            ctx.stroke();

            points.forEach(point => {
                ctx.fillStyle = '#60a5fa';
                ctx.beginPath();
                ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, Math.PI * 2);
                ctx.fill();
            });

            ctx.fillStyle = '#64748b';
            ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            points.forEach(point => {
                ctx.fillText(point.label, point.x, height - padding + 16);
            });

            const tickValues = maxValue >= 80 ? [80, 70, 60, 50, 0] : [100, 75, 50, 25, 0];
            ctx.fillStyle = '#94a3b8';
            ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            tickValues.forEach((value) => {
                const y = height - padding - (value / maxValue) * chartHeight;
                ctx.fillText(value + '%', padding - 10, y);
            });

            const lastPoint = points[points.length - 1];
            if (lastPoint) {
                ctx.setLineDash([4, 4]);
                ctx.strokeStyle = 'rgba(96, 165, 250, 0.4)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(lastPoint.x, lastPoint.y);
                ctx.lineTo(lastPoint.x, height - padding);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.fillStyle = '#0f172a';
                ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(lastPoint.value + '%', lastPoint.x + 12, lastPoint.y);
            }
        },

        /**
         * Load activity timeline
         */
        loadActivityTimeline: function() {
            const timeline = document.getElementById('bbai-activity-timeline');
            if (!timeline) return;

            this.setActivityLoading(true);
            this.showActivityEmpty(false);

            const bbaiAjax = (typeof window !== 'undefined' && window.bbai_ajax) ? window.bbai_ajax : (typeof bbai_ajax !== 'undefined' ? bbai_ajax : null);
            const ajaxUrl = bbaiAjax ? (bbaiAjax.ajax_url || bbaiAjax.ajaxurl) : '';

            if (!ajaxUrl) {
                this.setActivityLoading(false);
                this.showActivityEmpty(true);
                return;
            }

            this.setRefreshButtonSpinning(true);

            if (typeof jQuery !== 'undefined' && jQuery.ajax) {
                jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bbai_get_activity',
                        nonce: bbaiAjax ? (bbaiAjax.nonce || '') : ''
                    },
                    success: (response) => {
                        this.setRefreshButtonSpinning(false);
                        if (response && response.success && response.data && response.data.length > 0) {
                            this.renderActivityTimeline(response.data);
                        } else {
                            this.showActivityEmpty(true);
                        }
                        this.setActivityLoading(false);
                    },
                    error: () => {
                        this.setRefreshButtonSpinning(false);
                        this.setActivityLoading(false);
                        this.showActivityEmpty(true);
                    }
                });
            } else if (typeof fetch !== 'undefined') {
                const formData = new FormData();
                formData.append('action', 'bbai_get_activity');
                formData.append('nonce', bbaiAjax ? (bbaiAjax.nonce || '') : '');

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    this.setRefreshButtonSpinning(false);
                    if (data && data.success && data.data && data.data.length > 0) {
                        this.renderActivityTimeline(data.data);
                    } else {
                        this.showActivityEmpty(true);
                    }
                    this.setActivityLoading(false);
                })
                .catch(() => {
                    this.setRefreshButtonSpinning(false);
                    this.setActivityLoading(false);
                    this.showActivityEmpty(true);
                });
            }
        },

        /**
         * Render activity timeline
         */
        renderActivityTimeline: function(activities) {
            const timeline = document.getElementById('bbai-activity-timeline');
            if (!timeline) return;

            this.setActivityLoading(false);
            this.showActivityEmpty(false);

            timeline.querySelectorAll('.bbai-activity-item, .bbai-activity-toggle').forEach((el) => {
                el.remove();
            });

            if (!Array.isArray(activities) || activities.length === 0) {
                this.showActivityEmpty(true);
                return;
            }

            const maxVisible = 3;
            const hasOverflow = activities.length > maxVisible;

            activities.forEach((activity, index) => {
                const item = document.createElement('div');
                const type = (activity.type || activity.action || '').toLowerCase();
                let iconClass = 'bg-sky-100 text-sky-600';
                let iconSvg = '<path d="M12 3l1.8 4.8L19 9l-5.2 1.2L12 15l-1.8-4.8L5 9l5.2-1.2L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>';

                if (type.includes('reopt') || type.includes('retry') || type.includes('warning')) {
                    iconClass = 'bg-amber-100 text-amber-600';
                    iconSvg = '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>';
                } else if (type.includes('generate') || type.includes('new') || type.includes('success') || type.includes('optim')) {
                    iconClass = 'bg-emerald-100 text-emerald-600';
                    iconSvg = '<path d="M5 19L19 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 5H19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
                }

                const title = this.escapeHtml(activity.title || activity.description || '');
                const description = activity.details ? this.escapeHtml(activity.details) : '';
                const timeLabel = activity.timeAgo ? this.escapeHtml(activity.timeAgo) : this.formatTime(activity.timestamp);

                item.className = 'bbai-activity-item flex items-start gap-3';
                if (hasOverflow && index >= maxVisible) {
                    item.classList.add('bbai-activity-item--extra', 'is-hidden');
                }
                item.innerHTML = `
                    <span class="flex h-8 w-8 items-center justify-center rounded-full ${iconClass}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">${iconSvg}</svg>
                    </span>
                    <div class="flex-1 space-y-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm text-slate-800">${title}</p>
                            <span class="text-xs text-slate-500">${timeLabel}</span>
                        </div>
                        ${description ? `<p class="text-xs text-slate-500">${description}</p>` : ''}
                    </div>
                `;
                timeline.appendChild(item);
            });

            if (hasOverflow) {
                const hiddenCount = activities.length - maxVisible;
                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'bbai-activity-toggle';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.innerHTML = `
                    <span class="bbai-toggle-label">Show ${hiddenCount} more</span>
                    <span class="bbai-toggle-icon" aria-hidden="true">v</span>
                `;

                toggle.addEventListener('click', () => {
                    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                    const nextExpanded = !isExpanded;
                    const extraItems = timeline.querySelectorAll('.bbai-activity-item--extra');

                    extraItems.forEach((item) => {
                        item.classList.toggle('is-hidden', !nextExpanded);
                    });

                    toggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
                    toggle.classList.toggle('is-expanded', nextExpanded);
                    const label = toggle.querySelector('.bbai-toggle-label');
                    if (label) {
                        label.textContent = nextExpanded ? 'Show less' : `Show ${hiddenCount} more`;
                    }
                });

                timeline.appendChild(toggle);
            }
        },

        setRefreshButtonSpinning: function(isSpinning) {
            const refreshBtn = document.getElementById('bbai-refresh-activity');
            if (!refreshBtn) return;

            const icon = refreshBtn.querySelector('svg');
            if (icon) {
                icon.classList.toggle('bbai-animate-spin', !!isSpinning);
            }
            refreshBtn.disabled = !!isSpinning;
        },

        setActivityLoading: function(isLoading) {
            const loading = document.getElementById('bbai-activity-loading');
            if (loading) {
                loading.style.display = isLoading ? 'flex' : 'none';
            }
        },

        showActivityEmpty: function(show) {
            const empty = document.getElementById('bbai-activity-empty');
            if (empty) {
                empty.style.display = show ? 'block' : 'none';
            }
        },

        /**
         * Format timestamp
         */
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (minutes < 60) {
                return minutes <= 1 ? 'Just now' : `${minutes} minutes ago`;
            } else if (hours < 24) {
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            } else if (days < 7) {
                return `${days} day${days > 1 ? 's' : ''} ago`;
            } else {
                return date.toLocaleDateString();
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Period selector
            const periodSelect = document.getElementById('bbai-analytics-period');
            if (periodSelect) {
                periodSelect.addEventListener('change', () => {
                    // Reload chart with new period
                    this.loadChartData(periodSelect.value);
                });

                periodSelect.addEventListener('click', (event) => {
                    const button = event.target instanceof Element ? event.target.closest('button[data-period]') : null;
                    if (!button) {
                        return;
                    }

                    const period = button.getAttribute('data-period') || '30d';
                    periodSelect.querySelectorAll('button[data-period]').forEach((btn) => {
                        const isActive = btn === button;
                        btn.classList.toggle('bg-slate-50', isActive);
                        btn.classList.toggle('text-slate-900', isActive);
                        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });

                    this.loadChartData(period);
                });
            }

            // Export report
            const exportBtn = document.getElementById('bbai-export-report');
            if (exportBtn) {
                exportBtn.addEventListener('click', () => {
                    this.exportReport();
                });
            }

            const refreshBtn = document.getElementById('bbai-refresh-activity');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    this.loadActivityTimeline();
                });
            }

            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    this.initChart();
                }, 150);
            });
        },

        /**
         * Load chart data for period
         */
        loadChartData: function(period) {
            // Would fetch data from backend based on period
            // For now, just re-render with existing data
            this.initChart();
        },

        /**
         * Export report
         */
        exportReport: function() {
            const bbaiAjax = (typeof window !== 'undefined' && window.bbai_ajax) ? window.bbai_ajax : (typeof bbai_ajax !== 'undefined' ? bbai_ajax : null);
            const ajaxUrl = bbaiAjax ? (bbaiAjax.ajax_url || bbaiAjax.ajaxurl) : '';
            if (!ajaxUrl) {
                return;
            }
            const url = ajaxUrl + '?action=bbai_export_analytics&nonce=' + (bbaiAjax.nonce || '');
            window.open(url, '_blank');
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiAnalytics.init());
    } else {
        bbaiAnalytics.init();
    }

    // Expose globally
    window.bbaiAnalytics = bbaiAnalytics;
})();
