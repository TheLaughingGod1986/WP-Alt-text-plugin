/**
 * BeepBeep AI Analytics Dashboard
 * Lightweight coverage chart with hover emphasis.
 */

(function() {
    'use strict';

    const bbaiAnalytics = {
        chartState: null,
        resizeTimer: null,

        init: function() {
            this.initChart();
            this.bindEvents();
        },

        /**
         * Smooth cubic segments through points (Catmull-Rom style controls).
         *
         * @param {CanvasRenderingContext2D} ctx
         * @param {Array<{x:number,y:number}>} points
         * @param {boolean} skipMoveTo When true, continue from current point (e.g. after lineTo first point).
         */
        addSmoothPath: function(ctx, points, skipMoveTo) {
            if (!points || points.length < 2) {
                return;
            }
            if (!skipMoveTo) {
                ctx.moveTo(points[0].x, points[0].y);
            }
            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[Math.max(0, i - 1)];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[Math.min(points.length - 1, i + 2)];
                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = p1.y + (p2.y - p0.y) / 6;
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = p2.y - (p3.y - p1.y) / 6;
                ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
            }
        },

        initChart: function() {
            const canvas = document.getElementById('bbai-coverage-chart');
            if (!canvas || !window.bbaiAnalyticsData) {
                this.chartState = null;
                return;
            }

            const data = Array.isArray(window.bbaiAnalyticsData.coverage) ? window.bbaiAnalyticsData.coverage : [];
            if (data.length === 0) {
                this.chartState = null;
                return;
            }

            const container = canvas.parentElement;
            const width = container ? container.clientWidth : 800;
            const height = 188;
            const dpr = window.devicePixelRatio || 1;
            const padTop = 16;
            const padLeft = 36;
            const padRight = 18;
            const padBottom = 30;
            const chartWidth = width - padLeft - padRight;
            const chartHeight = height - padTop - padBottom;
            const maxValue = Math.max(100, Math.max.apply(null, data.map((item) => item.coverage || 0)));

            canvas.width = width * dpr;
            canvas.height = height * dpr;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';

            const ctx = canvas.getContext('2d');
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(dpr, dpr);
            ctx.imageSmoothingEnabled = true;
            if (typeof ctx.imageSmoothingQuality === 'string') {
                ctx.imageSmoothingQuality = 'high';
            }

            const points = data.map((item, index) => {
                const x = padLeft + (chartWidth / Math.max(1, data.length - 1)) * index;
                const y = height - padBottom - ((item.coverage || 0) / maxValue) * chartHeight;
                return {
                    x,
                    y,
                    label: item.date || '',
                    value: item.coverage || 0,
                };
            });

            this.chartState = {
                canvas,
                ctx,
                width,
                height,
                padTop,
                padLeft,
                padRight,
                padBottom,
                chartHeight,
                maxValue,
                points,
                activeIndex: null,
            };

            this.renderChart();

            canvas.onmousemove = (event) => {
                const rect = canvas.getBoundingClientRect();
                const mouseX = event.clientX - rect.left;
                const mouseY = event.clientY - rect.top;
                const activeIndex = this.getNearestPointIndex(mouseX, mouseY);

                if (this.chartState && this.chartState.activeIndex !== activeIndex) {
                    this.chartState.activeIndex = activeIndex;
                    this.renderChart();
                }

                canvas.style.cursor = activeIndex !== null ? 'pointer' : 'default';
            };

            canvas.onmouseleave = () => {
                if (this.chartState && this.chartState.activeIndex !== null) {
                    this.chartState.activeIndex = null;
                    this.renderChart();
                }
                canvas.style.cursor = 'default';
            };
        },

        getNearestPointIndex: function(mouseX, mouseY) {
            if (!this.chartState) {
                return null;
            }

            let closestIndex = null;
            let closestDistance = Infinity;

            this.chartState.points.forEach((point, index) => {
                const dx = point.x - mouseX;
                const dy = point.y - mouseY;
                const distance = Math.sqrt((dx * dx) + (dy * dy));
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestIndex = index;
                }
            });

            return closestDistance <= 24 ? closestIndex : null;
        },

        renderChart: function() {
            if (!this.chartState) {
                return;
            }

            const {
                ctx,
                width,
                height,
                padTop,
                padLeft,
                padRight,
                padBottom,
                chartHeight,
                maxValue,
                points,
                activeIndex,
            } = this.chartState;

            ctx.clearRect(0, 0, width, height);

            const gridLines = 4;
            ctx.strokeStyle = '#edf2f7';
            ctx.lineWidth = 1;
            for (let i = 0; i <= gridLines; i++) {
                const y = padTop + (chartHeight / gridLines) * i;
                ctx.beginPath();
                ctx.moveTo(padLeft, y);
                ctx.lineTo(width - padRight, y);
                ctx.stroke();
            }

            const gradient = ctx.createLinearGradient(0, padTop, 0, height - padBottom);
            gradient.addColorStop(0, 'rgba(5, 150, 105, 0.18)');
            gradient.addColorStop(1, 'rgba(5, 150, 105, 0.02)');
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.moveTo(points[0].x, height - padBottom);
            ctx.lineTo(points[0].x, points[0].y);
            this.addSmoothPath(ctx, points, true);
            ctx.lineTo(points[points.length - 1].x, height - padBottom);
            ctx.closePath();
            ctx.fill();

            ctx.strokeStyle = '#065f46';
            ctx.lineWidth = 3.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            this.addSmoothPath(ctx, points, false);
            ctx.stroke();

            points.forEach((point, index) => {
                const isLast = index === points.length - 1;
                const isActive = activeIndex === index;
                const outerRadius = isActive ? 9 : (isLast ? 8 : 0);
                const dotRadius = isActive ? 5.25 : (isLast ? 4.5 : 3.5);
                const innerRadius = isActive ? 2.25 : (isLast ? 2 : 1.75);

                if (outerRadius > 0) {
                    ctx.strokeStyle = isActive ? 'rgba(4, 120, 87, 0.28)' : 'rgba(5, 150, 105, 0.18)';
                    ctx.lineWidth = isActive ? 4 : 3;
                    ctx.beginPath();
                    ctx.arc(point.x, point.y, outerRadius, 0, Math.PI * 2);
                    ctx.stroke();
                }

                ctx.fillStyle = isActive ? '#047857' : '#059669';
                ctx.beginPath();
                ctx.arc(point.x, point.y, dotRadius, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(point.x, point.y, innerRadius, 0, Math.PI * 2);
                ctx.fill();
            });

            ctx.fillStyle = '#6b7280';
            ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            points.forEach((point) => {
                ctx.fillText(point.label, point.x, height - padBottom + 6);
            });

            const yTicks = [0, 50, 100];
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            yTicks.forEach((value) => {
                const y = height - padBottom - (value / maxValue) * chartHeight;
                ctx.fillText(value + '%', padLeft - 8, y);
            });

            const focusedPoint = activeIndex !== null ? points[activeIndex] : points[points.length - 1];
            if (focusedPoint) {
                ctx.fillStyle = '#065f46';
                ctx.font = '600 11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(focusedPoint.value + '%', focusedPoint.x + 8, focusedPoint.y);
            }
        },

        bindEvents: function() {
            window.addEventListener('resize', () => {
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => {
                    this.initChart();
                }, 150);
            });
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiAnalytics.init());
    } else {
        bbaiAnalytics.init();
    }

    window.bbaiAnalyticsDashboard = bbaiAnalytics;
    if (!window.bbaiAnalytics || typeof window.bbaiAnalytics.getContext !== 'function') {
        window.bbaiAnalytics = bbaiAnalytics;
    }
})();
