/**
 * Shared product banner copy — mirrors includes/admin/banner-system.php
 * (bbai_banner_build_credit_supporting_line + credit banner titles).
 *
 * @package BeepBeep_AI
 */
(function (global) {
    'use strict';

    var domain = 'beepbeep-ai-alt-text-generator';

    function getI18n() {
        return global.wp && global.wp.i18n ? global.wp.i18n : null;
    }

    function __(text) {
        var i18n = getI18n();
        return i18n ? i18n.__(text, domain) : text;
    }

    function sprintf(format) {
        var i18n = getI18n();
        var args = Array.prototype.slice.call(arguments, 1);
        if (i18n && typeof i18n.sprintf === 'function') {
            return i18n.sprintf.apply(null, [format].concat(args));
        }
        return format.replace(/%s/g, function () {
            return args.length ? String(args.shift()) : '';
        });
    }

    function _n(single, plural, number) {
        var i18n = getI18n();
        if (i18n && typeof i18n._n === 'function') {
            return i18n._n(single, plural, number, domain);
        }
        return number === 1 ? single : plural;
    }

    function formatCount(n) {
        var num = Math.max(0, parseInt(n, 10) || 0);
        try {
            return num.toLocaleString();
        } catch (e) {
            return String(num);
        }
    }

    /**
     * @param {number} creditsRemaining
     * @param {number} issueCount
     * @returns {string}
     */
    function buildCreditSupportingLine(creditsRemaining, issueCount) {
        var credits = Math.max(0, parseInt(creditsRemaining, 10) || 0);
        var issues = Math.max(0, parseInt(issueCount, 10) || 0);
        var creditsSegment = '';

        if (credits <= 0) {
            creditsSegment = __('You can still review your existing ALT text. Upgrade to continue generating.');
        } else {
            creditsSegment = sprintf(
                _n(
                    '%s credit left this month. Keep your library moving.',
                    '%s credits left this month. Keep your library moving.',
                    credits
                ),
                formatCount(credits)
            );
        }

        if (issues <= 0) {
            return creditsSegment;
        }

        var issuesSegment =
            issues === 1
                ? __('1 image needs attention')
                : sprintf(__('%s images need attention'), formatCount(issues));

        return creditsSegment + ' • ' + issuesSegment;
    }

    /**
     * @param {number} issueCount
     * @returns {string}
     */
    function buildIssueAttentionMessage(issueCount) {
        var n = Math.max(0, parseInt(issueCount, 10) || 0);
        if (n <= 0) {
            return __('All images are optimized.');
        }
        if (n === 1) {
            return __('1 image still needs attention.');
        }
        return sprintf(
            _n('%s image still needs attention.', '%s images still need attention.', n),
            formatCount(n)
        );
    }

    /**
     * Low / out-of-credits command hero copy (short title + supporting line only).
     *
     * @param {{ creditsRemaining?: number, credits_remaining?: number, issueCount?: number, issue_count?: number }} args
     * @returns {{ title: string, supportingLine: string, line1: string, line2: string }}
     */
    /**
     * Single active top-banner slot (strict priority). Mirrors PHP bbai_get_active_banner_slot().
     *
     * @param {{ lowCredits?: boolean, missingAltCount?: number, optimizedCount?: number, showMilestone?: boolean }} state
     * @returns {'lowCredits'|'missingAlt'|'milestone'|null}
     */
    function getActiveBanner(state) {
        state = state || {};
        if (state.lowCredits) {
            return 'lowCredits';
        }
        var missingAltCount = Math.max(0, parseInt(state.missingAltCount, 10) || 0);
        if (missingAltCount > 0) {
            return 'missingAlt';
        }
        var optimizedCount = Math.max(0, parseInt(state.optimizedCount, 10) || 0);
        if (optimizedCount > 0 && state.showMilestone) {
            return 'milestone';
        }
        return null;
    }

    function buildBannerMessage(args) {
        args = args || {};
        var creditsRemaining = Math.max(
            0,
            parseInt(args.creditsRemaining != null ? args.creditsRemaining : args.credits_remaining, 10) || 0
        );
        var issueCount = Math.max(
            0,
            parseInt(args.issueCount != null ? args.issueCount : args.issue_count, 10) || 0
        );

        var title =
            creditsRemaining === 0
                ? __('You’ve used this month’s free allowance')
                : __('You’re close to this month’s allowance');

        var supportingLine = buildCreditSupportingLine(creditsRemaining, issueCount);

        return {
            title: title,
            supportingLine: supportingLine,
            line1: title,
            line2: supportingLine
        };
    }

    global.bbaiBuildBannerMessage = buildBannerMessage;
    global.bbaiBuildCreditSupportingLine = buildCreditSupportingLine;
    global.bbaiBuildIssueAttentionMessage = buildIssueAttentionMessage;
    global.bbaiGetActiveBanner = getActiveBanner;
})(typeof window !== 'undefined' ? window : this);
