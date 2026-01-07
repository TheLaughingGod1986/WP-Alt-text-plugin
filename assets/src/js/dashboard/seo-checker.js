/**
 * SEO Quality Checker
 * Validates alt text quality for SEO best practices
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

window.bbaiSEOChecker = {
    /**
     * Check if alt text starts with redundant phrases
     */
    hasRedundantPrefix: function(text) {
        if (!text) return false;
        var lowerText = text.toLowerCase().trim();
        var redundantPrefixes = [
            'image of',
            'picture of',
            'photo of',
            'photograph of',
            'graphic of',
            'illustration of',
            'image showing',
            'picture showing',
            'photo showing'
        ];
        return redundantPrefixes.some(function(prefix) {
            return lowerText.startsWith(prefix);
        });
    },

    /**
     * Check if alt text is just a filename
     */
    isJustFilename: function(text) {
        if (!text) return false;
        var filenamePatterns = [
            /^IMG[-_]\d+/i,
            /^DSC[-_]\d+/i,
            /^\d{8}[-_]\d+/i,
            /^screenshot[-_]/i,
            /^image[-_]\d+/i,
            /\.(jpg|jpeg|png|gif|webp)$/i
        ];
        return filenamePatterns.some(function(pattern) {
            return pattern.test(text.trim());
        });
    },

    /**
     * Check if alt text has meaningful content
     */
    hasDescriptiveContent: function(text) {
        if (!text) return false;
        var words = text.trim().split(/\s+/);
        return words.length >= 3 && words.some(function(word) {
            return word.length > 3;
        });
    },

    /**
     * Calculate SEO quality score
     */
    calculateQuality: function(text) {
        var issues = [];
        var score = 100;

        if (!text || text.trim().length === 0) {
            return {
                score: 0,
                grade: 'F',
                issues: ['No alt text provided'],
                badge: 'missing'
            };
        }

        if (text.length > 125) {
            issues.push('Too long (>' + text.length + ' chars). Aim for ≤125 for optimal Google Images SEO');
            score -= 25;
        }

        if (this.hasRedundantPrefix(text)) {
            issues.push('Starts with "image of" or similar. Remove redundant prefix');
            score -= 20;
        }

        if (this.isJustFilename(text)) {
            issues.push('Appears to be a filename. Use descriptive text instead');
            score -= 30;
        }

        if (!this.hasDescriptiveContent(text)) {
            issues.push('Too short or lacks descriptive keywords');
            score -= 15;
        }

        var grade = 'F';
        var badge = 'needs-work';
        if (score >= 90) {
            grade = 'A';
            badge = 'excellent';
        } else if (score >= 75) {
            grade = 'B';
            badge = 'good';
        } else if (score >= 60) {
            grade = 'C';
            badge = 'fair';
        } else if (score >= 40) {
            grade = 'D';
            badge = 'poor';
        }

        return {
            score: Math.max(0, score),
            grade: grade,
            issues: issues,
            badge: badge
        };
    },

    /**
     * Create SEO quality badge HTML
     */
    createBadge: function(text) {
        var quality = this.calculateQuality(text);

        if (quality.badge === 'missing') {
            return '';
        }

        var badgeClass = 'bbai-seo-badge bbai-seo-badge--' + quality.badge;
        var icon = quality.grade === 'A' ?
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1 5l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
            quality.grade === 'B' ?
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M5 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>' :
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 2l6 6M2 8l6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

        var tooltip = quality.issues.length > 0 ?
            'SEO Quality: ' + quality.grade + ' (' + quality.score + '/100)\n' + quality.issues.join('\n') :
            'SEO Quality: ' + quality.grade + ' (' + quality.score + '/100) - Excellent!';

        return '<span class="' + badgeClass + '" title="' + tooltip.replace(/"/g, '&quot;') + '">' +
               icon +
               'SEO: ' + quality.grade +
               '</span>';
    },

    /**
     * Initialize SEO quality badges
     */
    init: function() {
        var self = this;
        var $ = window.jQuery || window.$;
        if (typeof $ !== 'function') return;

        $('.bbai-library-alt-text').each(function() {
            var $altText = $(this);
            var text = $altText.attr('data-full-text') || $altText.text().trim();

            if ($altText.parent().find('.bbai-seo-badge').length === 0) {
                var badgeHTML = self.createBadge(text);
                if (badgeHTML) {
                    var $counter = $altText.next('.bbai-char-counter');
                    if ($counter.length) {
                        $counter.after(badgeHTML);
                    }
                }
            }
        });
    }
};

// Initialize SEO checker when ready
bbaiRunWithJQuery(function($) {
    $(document).ready(function() {
        if (typeof window.bbaiSEOChecker !== 'undefined') {
            window.bbaiSEOChecker.init();
        }

        $(document).on('bbai:alttext:updated', function() {
            if (typeof window.bbaiSEOChecker !== 'undefined') {
                window.bbaiSEOChecker.init();
            }
        });
    });
});
