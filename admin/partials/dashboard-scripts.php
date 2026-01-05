<?php
/**
 * Dashboard tab scripts (progress rings + animation helpers).
 *
 * Expects jQuery to be available; included from dashboard tab.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Circular Progress Animation Script -->
<script>
(function() {
    function initProgressRings() {
        var rings = document.querySelectorAll('.bbai-circular-progress-bar[data-offset]');
        rings.forEach(function(ring) {
            var circumference = parseFloat(ring.getAttribute('data-circumference'));
            var targetOffset = parseFloat(ring.getAttribute('data-offset'));
            
            if (!isNaN(circumference) && !isNaN(targetOffset)) {
                // Start from full (hidden)
                ring.style.strokeDashoffset = circumference;
                ring.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1)';
                
                // Animate to target
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
</script>

<!-- Dashboard UI Animations -->
<script>
(function($) {
    'use strict';
    
    /**
     * Animate a single number element
     * Marks element as animated to prevent re-animation
     */
    function animateNumberElement($el, delay) {
        // Skip if already animated
        if ($el.data('bbai-animated')) return;
        // Mark as animated immediately to prevent re-animation
        $el.data('bbai-animated', true);
        
        var originalValue = $el.text().trim();
        
        // Skip if empty
        if (!originalValue) return;
        
        // Extract numeric value - handle percentages, decimals, and "hrs" suffix
        var numericMatch = originalValue.match(/[\d,]+\.?\d*/);
        if (!numericMatch) return;
        
        var finalValue = parseFloat(numericMatch[0].replace(/,/g, ''));
        if (isNaN(finalValue)) return;
        
        // Store original formatting
        var hasPercent = originalValue.indexOf('%') !== -1;
        var hasHrs = originalValue.toLowerCase().indexOf('hrs') !== -1;
        var suffix = '';
        if (hasPercent) suffix = '%';
        else if (hasHrs) suffix = ' hrs';
        
        // Animate from 0 to final value
        var duration = 1200; // 1.2 seconds
        var startTime = null;
        var startValue = 0;
        if (hasHrs) {
            // If original value was hours, start from a small non-zero to show animation
            startValue = 0;
        }
        
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min(1, (timestamp - startTime) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = startValue + (finalValue - startValue) * eased;
            
            // Preserve decimals if present
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
    
    /**
     * Animate numbers for all elements with data attributes
     */
    function animateNumbers() {
        var $numbers = $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong');
        $numbers.each(function(index, el) {
            animateNumberElement($(el), index * 40);
        });
    }
    
    /**
     * Initiate scroll-triggered number animations with IntersectionObserver
     */
    function initScrollAnimations() {
        // Skip if observer not available
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
                    // Unobserve after animation triggers
                    observer.unobserve(entry.target);
                }
            });
        }, {
            // Trigger when element is 10% visible
            threshold: 0.1,
            // Start observing slightly before element enters viewport
            rootMargin: '0px 0px -50px 0px'
        });
        
        // Observe all number elements
        $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').each(function() {
            observer.observe(this);
        });
    }
    
    /**
     * Animate numbers when they come into view (for compatibility)
     */
    function animateNumbersFallback() {
        // Use the new scroll-triggered animation system
        initScrollAnimations();
    }
    
    /**
     * Add glow classes to CTA buttons on page load
     */
    function initGlowButtons() {
        // Ensure main CTA buttons have glow
        $('.bbai-upsell-cta--large').addClass('bbai-cta-glow-green');
        $('.bbai-optimization-btn-secondary').addClass('bbai-cta-glow-blue');
        $('.button.button-primary').addClass('bbai-cta-glow');
    }
    
    // Track if animations have been initialized for this page load
    var animationsInitialized = false;
    
    // Run on document ready (only once per page load)
    $(document).ready(function() {
        // Only initialize if page is visible (not in background tab)
        if (typeof document !== 'undefined' && !document.hidden) {
            // Small delay to ensure DOM is ready
            setTimeout(function() {
                if (!animationsInitialized) {
                    animateNumbersFallback();
                    initGlowButtons();
                    animationsInitialized = true;
                }
            }, 100);
        }
    });
    
    // Prevent re-animation when switching browser tabs
    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', function() {
            // If page becomes hidden, mark that we should not re-animate
            if (document.hidden) {
                // Page is now hidden - don't do anything
                return;
            }
            // Page became visible again - but don't re-animate if already initialized
            if (!animationsInitialized) {
                // Only initialize if it's the first time (page was loaded in background)
                setTimeout(function() {
                    animateNumbersFallback();
                    initGlowButtons();
                    animationsInitialized = true;
                }, 100);
            }
        });
    }
    
    // Re-run on plugin tab navigation (internal tab switch within plugin)
    $(document).on('click', '.bbai-nav-link', function() {
        setTimeout(function() {
            // Only reset animation flag for elements in the NEW tab content
            // Find the tab container to scope the reset
            var $targetTab = $('.bbai-tab-content[data-tab]');
            if ($targetTab.length) {
                // Reset animation flags only for elements in visible/new tab
                $targetTab.find('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').removeData('bbai-animated');
                // Reinitialize scroll animations for new tab content
                initScrollAnimations();
                initGlowButtons();
            }
        }, 300);
    });
    
    // Also run when page loads with dashboard tab active (initial load only)
    if (window.location.search.indexOf('tab=dashboard') !== -1 || !window.location.search) {
        setTimeout(function() {
            if (!animationsInitialized) {
                initScrollAnimations();
                initGlowButtons();
                animationsInitialized = true;
            }
        }, 200);
    }
    
    // Reinitialize on window resize (in case layout changes) - only for unanimated elements
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Only reinitialize if numbers haven't been animated yet
            var $unanimatedNumbers = $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').filter(function() {
                return !$(this).data('bbai-animated');
            });
            if ($unanimatedNumbers.length > 0) {
                initScrollAnimations();
            }
        }, 250);
    });
})(jQuery);
</script>
