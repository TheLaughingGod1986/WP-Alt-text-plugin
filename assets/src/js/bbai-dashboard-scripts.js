/**
 * Dashboard UI helpers (progress rings, equal-height cards, number animations)
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

(function() {
    'use strict';

    function initProgressRings() {
        var rings = document.querySelectorAll('.bbai-circular-progress-bar[data-offset]');
        rings.forEach(function(ring) {
            var circumference = parseFloat(ring.getAttribute('data-circumference'));
            var targetOffset = parseFloat(ring.getAttribute('data-offset'));

            if (!isNaN(circumference) && !isNaN(targetOffset)) {
                ring.style.strokeDashoffset = circumference;
                ring.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1)';

                requestAnimationFrame(function() {
                    ring.style.strokeDashoffset = targetOffset;
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProgressRings);
    } else {
        setTimeout(initProgressRings, 50);
    }
})();

(function() {
    'use strict';

    function equalizeCardHeights() {
        var grid = document.querySelector('.bbai-premium-stats-grid');
        if (!grid) {
            return;
        }

        var usageCard = grid.querySelector('.bbai-usage-card');
        var upsellCard = grid.querySelector('.bbai-upsell-card');

        if (usageCard && upsellCard) {
            usageCard.style.height = 'auto';
            upsellCard.style.height = 'auto';

            var usageHeight = usageCard.offsetHeight;
            var upsellHeight = upsellCard.offsetHeight;
            var maxHeight = Math.max(usageHeight, upsellHeight);

            if (maxHeight > 0) {
                usageCard.style.height = maxHeight + 'px';
                upsellCard.style.height = maxHeight + 'px';
            }
        }
    }

    function initEqualHeights() {
        equalizeCardHeights();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function() {
                setTimeout(equalizeCardHeights, 100);
            });
        }

        setTimeout(equalizeCardHeights, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEqualHeights);
    } else {
        initEqualHeights();
    }

    window.addEventListener('resize', function() {
        setTimeout(equalizeCardHeights, 100);
    });
})();

(function($) {
    'use strict';

    function animateNumberElement($el, delay) {
        if ($el.data('bbai-animated')) {
            return;
        }
        $el.data('bbai-animated', true);

        var originalValue = $el.text().trim();
        if (!originalValue) {
            return;
        }

        var numericMatch = originalValue.match(/[\d,]+\.?\d*/);
        if (!numericMatch) {
            return;
        }

        var finalValue = parseFloat(numericMatch[0].replace(/,/g, ''));
        if (isNaN(finalValue)) {
            return;
        }

        var hasPercent = originalValue.indexOf('%') !== -1;
        var hasHrs = originalValue.toLowerCase().indexOf('hrs') !== -1;
        var suffix = '';
        if (hasPercent) {
            suffix = '%';
        } else if (hasHrs) {
            suffix = ' hrs';
        }

        var duration = 1200;
        var startTime = null;
        var startValue = 0;
        if (hasHrs) {
            startValue = 0;
        }

        function step(timestamp) {
            if (!startTime) {
                startTime = timestamp;
            }
            var progress = Math.min(1, (timestamp - startTime) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = startValue + (finalValue - startValue) * eased;

            var formatted;
            if (hasPercent) {
                formatted = Math.round(current) + suffix;
            } else if (hasHrs) {
                formatted = current.toFixed(2) + suffix;
            } else if (Number.isInteger(finalValue)) {
                formatted = Math.round(current).toLocaleString();
            } else {
                formatted = current.toFixed(1).toLocaleString();
            }

            $el.text(formatted);

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        setTimeout(function() {
            requestAnimationFrame(step);
        }, delay || 0);
    }

    function animateNumbers() {
        var $numbers = $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong');
        $numbers.each(function(index, el) {
            animateNumberElement($(el), index * 40);
        });
    }

    function initScrollAnimations() {
        if (!('IntersectionObserver' in window)) {
            animateNumbers();
            return;
        }

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var $el = $(entry.target);
                    var delay = $el.data('bbai-delay') || 0;
                    animateNumberElement($el, delay);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').each(function() {
            observer.observe(this);
        });
    }

    function animateNumbersFallback() {
        initScrollAnimations();
    }

    function initGlowButtons() {
        $('.bbai-upsell-cta--large').addClass('bbai-cta-glow-green');
        $('.bbai-optimization-btn-secondary').addClass('bbai-cta-glow-blue');
        $('.button.button-primary').addClass('bbai-cta-glow');
    }

    var animationsInitialized = false;

    $(document).ready(function() {
        if (typeof document !== 'undefined' && !document.hidden) {
            setTimeout(function() {
                if (!animationsInitialized) {
                    animateNumbersFallback();
                    initGlowButtons();
                    animationsInitialized = true;
                }
            }, 100);
        }
    });

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                return;
            }
            if (!animationsInitialized) {
                setTimeout(function() {
                    animateNumbersFallback();
                    initGlowButtons();
                    animationsInitialized = true;
                }, 100);
            }
        });
    }

    $(document).on('click', '.bbai-nav-link', function() {
        setTimeout(function() {
            var $targetTab = $('.bbai-tab-content[data-tab]');
            if ($targetTab.length) {
                $targetTab.find('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').removeData('bbai-animated');
                initScrollAnimations();
                initGlowButtons();
            }
        }, 300);
    });

    if (window.location.search.indexOf('tab=dashboard') !== -1 || !window.location.search) {
        setTimeout(function() {
            if (!animationsInitialized) {
                initScrollAnimations();
                initGlowButtons();
                animationsInitialized = true;
            }
        }, 200);
    }

    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var $unanimatedNumbers = $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').filter(function() {
                return !$(this).data('bbai-animated');
            });
            if ($unanimatedNumbers.length > 0) {
                initScrollAnimations();
            }
        }, 250);
    });

    window.alttextai_refresh_usage = function(usageData) {
        if (usageData && typeof usageData === 'object') {
            updateUsageDisplay(usageData);
            return;
        }

        var config = window.BBAI_DASH || window.BBAI || {};
        var usageUrl = config.restUsage;
        var nonce = config.nonce || '';

        if (!usageUrl) {
            console.warn('[AltText AI] Cannot refresh usage: REST endpoint not available', config);
            return;
        }

        $.ajax({
            url: usageUrl,
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    updateUsageDisplay(response);
                } else {
                    console.warn('[AltText AI] Invalid usage response format:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[AltText AI] Failed to refresh usage:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    };

    function updateUsageDisplay(response) {
        if (!response || typeof response !== 'object') {
            console.warn('[AltText AI] Invalid usage data for display:', response);
            return;
        }

        if (window.BBAI_DASH) {
            window.BBAI_DASH.usage = response;
            window.BBAI_DASH.initialUsage = response;
        }
        if (window.BBAI) {
            window.BBAI.usage = response;
        }

        var used = response.used !== undefined ? response.used : 0;
        var limit = response.limit !== undefined ? response.limit : 50;
        var remaining = response.remaining !== undefined ? response.remaining : (limit - used);

        $('.bbai-usage-stat-item').each(function() {
            var $item = $(this);
            var label = $item.find('.bbai-usage-stat-label').text().trim().toLowerCase();
            var $value = $item.find('.bbai-usage-stat-value');

            if ($value.length) {
                var newValue = null;
                if (label.includes('generated') || label.includes('used')) {
                    newValue = parseInt(used, 10).toLocaleString();
                } else if (label.includes('limit') || label.includes('monthly')) {
                    newValue = parseInt(limit, 10).toLocaleString();
                } else if (label.includes('remaining')) {
                    newValue = parseInt(remaining, 10).toLocaleString();
                }

                if (newValue !== null) {
                    $value.removeData('bbai-animated');
                    var oldValue = $value.text();
                    $value.text(newValue);
                    if (typeof animateNumberElement === 'function' && oldValue !== newValue) {
                        animateNumberElement($value, 0);
                    }
                }
            }
        });

        $('.bbai-number-counting').each(function() {
            var $el = $(this);
            var text = $el.text().trim();
            if (/^\d[\d,]*$/.test(text.replace(/,/g, ''))) {
                var parentText = $el.closest('.bbai-usage-card-stats, .bbai-usage-stat-item').text().toLowerCase();
                if (parentText.includes('generated') || parentText.includes('used')) {
                    var newValue = parseInt(used, 10).toLocaleString();
                    if ($el.text() !== newValue) {
                        $el.removeData('bbai-animated');
                        $el.text(newValue);
                        if (typeof animateNumberElement === 'function') {
                            animateNumberElement($el, 0);
                        }
                    }
                } else if (parentText.includes('limit') || parentText.includes('monthly')) {
                    var newValue = parseInt(limit, 10).toLocaleString();
                    if ($el.text() !== newValue) {
                        $el.removeData('bbai-animated');
                        $el.text(newValue);
                        if (typeof animateNumberElement === 'function') {
                            animateNumberElement($el, 0);
                        }
                    }
                } else if (parentText.includes('remaining')) {
                    var newValue = parseInt(remaining, 10).toLocaleString();
                    if ($el.text() !== newValue) {
                        $el.removeData('bbai-animated');
                        $el.text(newValue);
                        if (typeof animateNumberElement === 'function') {
                            animateNumberElement($el, 0);
                        }
                    }
                }
            }
        });

        if (response.limit && response.limit > 0) {
            var percentage = Math.min(100, (response.used / response.limit) * 100);
            var circumference = 2 * Math.PI * 45;
            var offset = circumference - (percentage / 100) * circumference;

            $('.bbai-circular-progress-bar').each(function() {
                var $ring = $(this);
                $ring.attr('data-offset', offset);
                var ring = this;
                if (ring.style) {
                    ring.style.strokeDashoffset = circumference;
                    setTimeout(function() {
                        ring.style.strokeDashoffset = offset;
                    }, 50);
                }
            });
        }

        var currentTab = window.location.hash.replace('#', '') || 'dashboard';
        if (currentTab !== 'dashboard' && currentTab !== '') {
            var $dashboardLink = $('.bbai-nav-link[href*="dashboard"], .bbai-nav-link[data-tab="dashboard"]');
            if ($dashboardLink.length) {
                // Update happens automatically; no redirect needed.
            }
        }
    }

    if (typeof window.refreshUsageStats === 'undefined') {
        window.refreshUsageStats = window.alttextai_refresh_usage;
    }
})(jQuery);
