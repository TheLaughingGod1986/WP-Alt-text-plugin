/**
 * BeepBeep AI Social Proof Widget
 * Handles testimonials carousel
 */

(function() {
    'use strict';

    const bbaiSocialProof = {
        currentIndex: 0,
        testimonials: [],
        dots: [],
        track: null,
        viewport: null,
        autoPlayInterval: null,
        autoPlayDelay: 5000, // 5 seconds
        scrollRaf: null,

        init: function() {
            this.track = document.getElementById('bbai-testimonials-track');
            if (!this.track) return;

            this.viewport = document.getElementById('bbai-testimonials-viewport') || this.track.parentElement;
            this.testimonials = Array.from(this.track.querySelectorAll('.bbai-testimonial-card'));
            if (this.testimonials.length === 0) return;

            this.dots = Array.from(document.querySelectorAll('.bbai-testimonials-dot'));

            this.bindEvents();
            this.updateDisplay({ skipAutoPlayReset: true });
            this.startAutoPlay();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const prevBtn = document.querySelector('.bbai-testimonials-prev');
            const nextBtn = document.querySelector('.bbai-testimonials-next');

            if (prevBtn) {
                prevBtn.addEventListener('click', () => this.prev());
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => this.next());
            }

            this.dots.forEach((dot, index) => {
                dot.addEventListener('click', () => this.goTo(index));
            });

            if (this.viewport) {
                this.viewport.addEventListener('scroll', () => this.handleScroll());
            }

            window.addEventListener('resize', () => this.handleResize());

            // Pause on hover
            const carousel = document.querySelector('.bbai-testimonials-carousel');
            if (carousel) {
                carousel.addEventListener('mouseenter', () => this.stopAutoPlay());
                carousel.addEventListener('mouseleave', () => this.startAutoPlay());
            }
        },

        /**
         * Handle scroll updates
         */
        handleScroll: function() {
            if (this.scrollRaf) return;
            this.scrollRaf = window.requestAnimationFrame(() => {
                const closestIndex = this.getClosestIndex();
                if (closestIndex !== this.currentIndex) {
                    this.currentIndex = closestIndex;
                    this.updateDisplay({ skipAutoPlayReset: true });
                }
                this.scrollRaf = null;
            });
        },

        /**
         * Handle resize events
         */
        handleResize: function() {
            this.scrollToIndex(this.currentIndex, 'auto');
        },

        /**
         * Get the closest card index to the left edge of the viewport
         */
        getClosestIndex: function() {
            if (!this.viewport) return this.currentIndex;

            const scrollLeft = this.viewport.scrollLeft;
            let closestIndex = 0;
            let closestDistance = Infinity;

            this.testimonials.forEach((card, index) => {
                const distance = Math.abs(card.offsetLeft - scrollLeft);
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestIndex = index;
                }
            });

            return closestIndex;
        },

        /**
         * Scroll to a specific card index
         */
        scrollToIndex: function(index, behavior) {
            if (!this.viewport || !this.testimonials[index]) return;

            this.viewport.scrollTo({
                left: this.testimonials[index].offsetLeft,
                behavior: behavior || 'smooth'
            });
        },

        /**
         * Go to next testimonial
         */
        next: function() {
            const nextIndex = (this.currentIndex + 1) % this.testimonials.length;
            this.goTo(nextIndex);
        },

        /**
         * Go to previous testimonial
         */
        prev: function() {
            const prevIndex = (this.currentIndex - 1 + this.testimonials.length) % this.testimonials.length;
            this.goTo(prevIndex);
        },

        /**
         * Go to specific testimonial
         */
        goTo: function(index) {
            if (index >= 0 && index < this.testimonials.length) {
                this.currentIndex = index;
                this.scrollToIndex(index);
                this.updateDisplay();
            }
        },

        /**
         * Update display
         */
        updateDisplay: function(options) {
            this.testimonials.forEach((card, index) => {
                card.classList.toggle('active', index === this.currentIndex);
            });

            this.dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === this.currentIndex);
            });

            if (!options || !options.skipAutoPlayReset) {
                this.resetAutoPlay();
            }
        },

        /**
         * Start autoplay
         */
        startAutoPlay: function() {
            this.stopAutoPlay();
            if (this.testimonials.length > 1) {
                this.autoPlayInterval = setInterval(() => {
                    this.next();
                }, this.autoPlayDelay);
            }
        },

        /**
         * Stop autoplay
         */
        stopAutoPlay: function() {
            if (this.autoPlayInterval) {
                clearInterval(this.autoPlayInterval);
                this.autoPlayInterval = null;
            }
        },

        /**
         * Reset autoplay
         */
        resetAutoPlay: function() {
            this.stopAutoPlay();
            this.startAutoPlay();
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bbaiSocialProof.init());
    } else {
        bbaiSocialProof.init();
    }

    // Expose globally
    window.bbaiSocialProof = bbaiSocialProof;
})();
