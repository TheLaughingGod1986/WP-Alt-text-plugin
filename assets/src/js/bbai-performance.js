/**
 * BeepBeep AI Performance Optimizations
 * Lazy loading, caching, and query optimization helpers
 */

(function() {
    'use strict';

    const bbaiPerformance = {
        imageObserver: null,
        cache: new Map(),
        debounceTimers: {},

        init: function() {
            this.initLazyLoading();
            this.initIntersectionObserver();
            this.initDebounceHelpers();
        },

        /**
         * Initialize lazy loading for images
         */
        initLazyLoading: function() {
            // Use native lazy loading where supported
            const images = document.querySelectorAll('img.bbai-library-thumbnail:not([loading])');
            images.forEach(img => {
                if ('loading' in HTMLImageElement.prototype) {
                    img.loading = 'lazy';
                    img.decoding = 'async';
                } else {
                    // Fallback for older browsers
                    this.lazyLoadFallback(img);
                }
            });
        },

        /**
         * Fallback lazy loading for older browsers
         */
        lazyLoadFallback: function(img) {
            if (this.imageObserver) {
                this.imageObserver.observe(img);
            }
        },

        /**
         * Initialize Intersection Observer for lazy loading
         */
        initIntersectionObserver: function() {
            if ('IntersectionObserver' in window) {
                this.imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                            }
                            this.imageObserver.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '50px'
                });
            }
        },

        /**
         * Debounce helper for performance
         */
        debounce: function(func, wait, key) {
            const timerKey = key || 'default';
            return (...args) => {
                clearTimeout(this.debounceTimers[timerKey]);
                this.debounceTimers[timerKey] = setTimeout(() => {
                    func.apply(this, args);
                }, wait);
            };
        },

        /**
         * Initialize debounce helpers
         */
        initDebounceHelpers: function() {
            // Debounce search input
            const searchInputs = document.querySelectorAll('.bbai-search-input, input[type="search"]');
            searchInputs.forEach(input => {
                const debouncedSearch = this.debounce((e) => {
                    // Trigger search event
                    const event = new CustomEvent('bbai:search', {
                        detail: { query: e.target.value }
                    });
                    document.dispatchEvent(event);
                }, 300, 'search');
                input.addEventListener('input', debouncedSearch);
            });

            // Debounce scroll handlers
            const debouncedScroll = this.debounce(() => {
                // Trigger scroll event for analytics
                const event = new CustomEvent('bbai:scroll', {
                    detail: { 
                        scrollY: window.scrollY,
                        scrollPercent: (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
                    }
                });
                document.dispatchEvent(event);
            }, 100, 'scroll');
            window.addEventListener('scroll', debouncedScroll, { passive: true });
        },

        /**
         * Cache API responses
         */
        cacheResponse: function(key, data, ttl = 300000) { // 5 minutes default
            this.cache.set(key, {
                data: data,
                expires: Date.now() + ttl
            });
        },

        /**
         * Get cached response
         */
        getCachedResponse: function(key) {
            const cached = this.cache.get(key);
            if (!cached) return null;
            
            if (Date.now() > cached.expires) {
                this.cache.delete(key);
                return null;
            }
            
            return cached.data;
        },

        /**
         * Clear cache
         */
        clearCache: function(key) {
            if (key) {
                this.cache.delete(key);
            } else {
                this.cache.clear();
            }
        },

        /**
         * Optimize table rendering
         */
        optimizeTableRender: function(tableSelector) {
            const table = document.querySelector(tableSelector);
            if (!table) return;

            // Use requestAnimationFrame for smooth rendering
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 50) {
                // Virtual scrolling for large tables
                this.initVirtualScrolling(table);
            }
        },

        /**
         * Initialize virtual scrolling for large tables
         */
        initVirtualScrolling: function(table) {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr'));
            const rowHeight = 60; // Approximate row height
            const visibleRows = Math.ceil(window.innerHeight / rowHeight) + 5; // Buffer
            
            let startIndex = 0;
            let endIndex = Math.min(visibleRows, rows.length);

            const renderVisibleRows = () => {
                // Hide all rows
                rows.forEach(row => row.style.display = 'none');
                
                // Show visible rows
                for (let i = startIndex; i < endIndex; i++) {
                    if (rows[i]) {
                        rows[i].style.display = '';
                    }
                }
            };

            // Initial render
            renderVisibleRows();

            // Update on scroll
            const container = table.closest('.bbai-table-wrap');
            if (container) {
                container.addEventListener('scroll', () => {
                    const scrollTop = container.scrollTop;
                    startIndex = Math.floor(scrollTop / rowHeight);
                    endIndex = Math.min(startIndex + visibleRows, rows.length);
                    renderVisibleRows();
                }, { passive: true });
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiPerformance.init());
    } else {
        bbaiPerformance.init();
    }

    // Expose globally
    window.bbaiPerformance = bbaiPerformance;
})();
