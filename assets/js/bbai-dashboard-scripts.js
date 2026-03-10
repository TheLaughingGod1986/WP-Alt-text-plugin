/**
 * Dashboard UI helpers (progress rings, equal-height cards, number animations)
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

(function() {
    'use strict';

    function initProgressRings() {
        var rings = document.querySelectorAll('.bbai-circular-progress-bar[data-offset], .bbai-circular-progress-bar[data-target-offset]');
        rings.forEach(function(ring) {
            var circumference = parseFloat(ring.getAttribute('data-circumference'));
            var targetOffset = parseFloat(ring.getAttribute('data-target-offset') || ring.getAttribute('data-offset'));

            if (!isNaN(circumference) && !isNaN(targetOffset)) {
                ring.style.strokeDashoffset = String(circumference);
                ring.style.transition = 'stroke-dashoffset 0.8s ease';

                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        ring.style.strokeDashoffset = String(targetOffset);
                    });
                });
            }
        });
    }

    function initUsageBannerProgress() {
        var fills = document.querySelectorAll('[data-bbai-banner-progress][data-bbai-banner-progress-target]');
        fills.forEach(function(fill) {
            var target = parseFloat(fill.getAttribute('data-bbai-banner-progress-target'));
            if (!isNaN(target) && target >= 0) {
                if (fill.getAttribute('data-bbai-banner-progress-initialized') === '1') {
                    fill.style.width = target + '%';
                    return;
                }

                fill.style.width = '0%';
                fill.style.transition = 'none';
                fill.offsetWidth;

                setTimeout(function() {
                    fill.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                    fill.style.width = target + '%';
                    fill.setAttribute('data-bbai-banner-progress-initialized', '1');
                }, 60);
            }
        });
    }

    function initUsageMarkerProgress() {
        var markers = document.querySelectorAll('[data-bbai-marker-progress][data-bbai-marker-progress-target]');
        markers.forEach(function(marker) {
            var target = parseFloat(marker.getAttribute('data-bbai-marker-progress-target'));
            if (!isNaN(target) && target >= 0) {
                if (marker.getAttribute('data-bbai-marker-progress-initialized') === '1') {
                    marker.style.setProperty('--bbai-marker-left', target + '%');
                    return;
                }

                marker.style.setProperty('--bbai-marker-left', '0%');
                marker.style.transition = 'none';
                marker.offsetWidth;

                setTimeout(function() {
                    marker.style.transition = 'left 1s cubic-bezier(0.4, 0, 0.2, 1)';
                    marker.style.setProperty('--bbai-marker-left', target + '%');
                    marker.setAttribute('data-bbai-marker-progress-initialized', '1');
                }, 200);
            }
        });
    }

    function initOnLoad() {
        initProgressRings();
        initUsageBannerProgress();
        initUsageMarkerProgress();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnLoad);
    } else {
        setTimeout(initOnLoad, 50);
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

    var numberSelector = '.bbai-number-counting, .bbai-number-animate, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-donut-value, .bbai-circular-progress-percent, .bbai-usage-text strong, .bbai-usage-count-main, .bbai-upgrade-compare-item strong, .bbai-banner-headline-number, .bbai-banner-usage-used, .bbai-banner-usage-limit';

    function getNumberTargets() {
        return $(numberSelector);
    }

    function parseNumericTemplate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var regex = /(\d[\d,]*(?:\.\d+)?)/g;
        var parts = [];
        var tokens = [];
        var match;
        var lastIndex = 0;

        while ((match = regex.exec(value)) !== null) {
            var rawToken = match[1];
            var normalized = rawToken.replace(/,/g, '');
            var parsed = parseFloat(normalized);
            if (isNaN(parsed)) {
                continue;
            }

            parts.push(value.slice(lastIndex, match.index));
            tokens.push({
                final: parsed,
                decimals: normalized.indexOf('.') !== -1 ? (normalized.split('.')[1] || '').length : 0
            });
            lastIndex = match.index + rawToken.length;
        }

        if (!tokens.length) {
            return null;
        }

        parts.push(value.slice(lastIndex));

        return {
            parts: parts,
            tokens: tokens
        };
    }

    function formatAnimatedToken(value, decimals) {
        var safeValue = Math.max(0, value);
        if (decimals > 0) {
            var fixed = safeValue.toFixed(decimals).split('.');
            fixed[0] = parseInt(fixed[0], 10).toLocaleString();
            return fixed.join('.');
        }

        return Math.round(safeValue).toLocaleString();
    }

    function buildAnimatedValue(template, progress) {
        var output = template.parts[0];

        for (var i = 0; i < template.tokens.length; i++) {
            var token = template.tokens[i];
            var current = token.final * progress;
            output += formatAnimatedToken(current, token.decimals);
            output += template.parts[i + 1];
        }

        return output;
    }

    function animateNumberElement($el, delay) {
        if ($el.data('bbai-animated')) {
            return;
        }
        $el.data('bbai-animated', true);

        var originalValue = $el.text().trim();
        if (!originalValue) {
            return;
        }

        if ($el.children().length > 0 && !$el.hasClass('bbai-number-animate')) {
            return;
        }

        var template = parseNumericTemplate(originalValue);
        if (!template) {
            return;
        }

        var duration = 1200;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) {
                startTime = timestamp;
            }
            var progress = Math.min(1, (timestamp - startTime) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            $el.text(buildAnimatedValue(template, eased));

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        setTimeout(function() {
            requestAnimationFrame(step);
        }, delay || 0);
    }

    function animateNumbers() {
        var $numbers = getNumberTargets();
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

        getNumberTargets().each(function() {
            observer.observe(this);
        });

        // Safety net for cases where observer callbacks are skipped.
        setTimeout(function() {
            animateNumbers();
        }, 700);
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
                $targetTab.find(numberSelector).removeData('bbai-animated');
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
            var $unanimatedNumbers = getNumberTargets().filter(function() {
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
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Cannot refresh usage: REST endpoint not available', config);
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
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Invalid usage response format:', response);
                }
            },
            error: function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to refresh usage:', {
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
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Invalid usage data for display:', response);
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
            var percentage = Math.min(100, Math.round((response.used / response.limit) * 100));
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

            var $pct = $('.bbai-circular-progress-percent');
            if ($pct.find('.bbai-donut-value').length) {
                $pct.find('.bbai-donut-value').text(percentage);
            } else {
                $pct.text(percentage + '%');
            }

            var circumference46 = 2 * Math.PI * 46;
            var offset46 = circumference46 - (percentage / 100) * circumference46;
            $('.bbai-progress-ring__value').each(function() {
                var ring = this;
                if (ring.style) {
                    ring.style.strokeDashoffset = offset46;
                }
                $(ring).attr('data-progress', percentage);
            });
            $('.bbai-progress-ring__value-text').text(percentage + '%');
            $('.bbai-progress-ring[role="progressbar"]').attr('aria-valuenow', percentage);
            $('.bbai-progress-bar__fill').css('width', percentage + '%');
            $('[data-bbai-banner-progress]').each(function() {
                var $el = $(this);
                $el.css('width', percentage + '%');
                $el.attr('data-bbai-banner-progress-target', percentage);
            });
            $('.bbai-progress-meta__complete').each(function() {
                var $el = $(this);
                var text = $el.text();
                if (text.indexOf('%') !== -1) {
                    $el.text(percentage + '% ' + text.replace(/^\d+%\s*/i, '').trim());
                }
            });
        }

        var usedNum = parseInt(used, 10) || 0;
        var limitNum = parseInt(limit, 10) || 0;
        $('.bbai-usage-count-main').text(usedNum.toLocaleString() + ' of ' + limitNum.toLocaleString() + ' images processed this month');
        $('.bbai-progress-card .bbai-card__subtitle').text(
            usedNum.toLocaleString() + ' of ' + limitNum.toLocaleString() + ' images used this month'
        );
        $('.bbai-donut-value, .bbai-circular-progress-percent, .bbai-usage-count-main, .bbai-upgrade-compare-item strong, .bbai-banner-headline-number, .bbai-banner-usage-used, .bbai-banner-usage-limit').removeData('bbai-animated');
        setTimeout(function() {
            animateNumbers();
        }, 30);

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
