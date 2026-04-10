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
    function buildCreditSupportingLine(creditsRemaining, issueCount, options) {
        options = options || {};
        var credits = Math.max(0, parseInt(creditsRemaining, 10) || 0);
        var issues = Math.max(0, parseInt(issueCount, 10) || 0);
        var isAnonymousTrial = !!options.isAnonymousTrial;
        var freePlanOffer = Math.max(0, parseInt(options.freePlanOffer, 10) || 50);
        var creditsSegment = '';

        if (isAnonymousTrial) {
            if (credits <= 0) {
                creditsSegment = sprintf(
                    __('Free trial complete. Create a free account to unlock %d generations per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                    freePlanOffer
                );
            } else {
                creditsSegment = sprintf(
                    _n(
                        '%1$s trial generation left. Create a free account to unlock %2$d generations per month.',
                        '%1$s trial generations left. Create a free account to unlock %2$d generations per month.',
                        credits,
                        'beepbeep-ai-alt-text-generator'
                    ),
                    formatCount(credits),
                    freePlanOffer
                );
            }
        } else if (credits <= 0) {
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
            return __('1 image needs attention');
        }
        return sprintf(
            _n('%s image needs attention', '%s images need attention', n),
            formatCount(n)
        );
    }

    /**
     * Shared command-hero icon markup by tone.
     * Mirrors includes/admin/command-hero.php.
     *
     * @param {string} tone
     * @returns {string}
     */
    function getSharedCommandHeroIconMarkup(tone) {
        if (tone === 'setup') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"></circle><path d="M20 20L16.2 16.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        if (tone === 'attention') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><path d="M12 3L21 19H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="17" r="1" fill="currentColor"></circle></svg>';
        }

        if (tone === 'paused') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle><path d="M10 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M14 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><path d="M22 11.08V12A10 10 0 1 1 12 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
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
        var authState = String(args.authState != null ? args.authState : args.auth_state || '').toLowerCase();
        var quotaType = String(args.quotaType != null ? args.quotaType : args.quota_type || '').toLowerCase();
        var isAnonymousTrial = authState === 'anonymous' || quotaType === 'trial' || !!args.isTrial || !!args.isGuestTrial;
        var freePlanOffer = Math.max(0, parseInt(args.freePlanOffer != null ? args.freePlanOffer : args.free_plan_offer, 10) || 50);

        var title = isAnonymousTrial
            ? (creditsRemaining === 0
                ? __('Free trial complete')
                : __('Your free trial is almost used'))
            : (creditsRemaining === 0
                ? __('You’ve used this month’s free allowance')
                : __('You’re close to this month’s allowance'));

        var supportingLine = buildCreditSupportingLine(creditsRemaining, issueCount, {
            isAnonymousTrial: isAnonymousTrial,
            freePlanOffer: freePlanOffer
        });

        return {
            title: title,
            supportingLine: supportingLine,
            line1: title,
            line2: supportingLine
        };
    }

    /**
     * Shared top-banner state for Dashboard + ALT Library command heroes.
     * Mirrors the common branch logic in includes/admin/banner-system.php.
     *
     * @param {{
     *   totalImages?: number,
     *   total_images?: number,
     *   missingCount?: number,
     *   missing_count?: number,
     *   weakCount?: number,
     *   weak_count?: number,
     *   needsReviewCount?: number,
     *   needs_review_count?: number,
     *   creditsRemaining?: number,
     *   credits_remaining?: number,
     *   isPro?: boolean,
     *   is_pro?: boolean,
     *   lowCreditThreshold?: number,
     *   low_credit_threshold?: number,
     *   libraryUrl?: string,
     *   library_url?: string,
     *   usageUrl?: string,
     *   usage_url?: string,
     *   guideUrl?: string,
     *   guide_url?: string,
     *   settingsUrl?: string,
     *   settings_url?: string,
     *   needsReviewLibraryUrl?: string,
     *   needs_review_library_url?: string
     * }} args
     * @returns {{
     *   state: string,
     *   surfaceState: string,
     *   dashboardState: string,
     *   tone: string,
     *   bannerVariant: string,
     *   pageHeroVariant: string,
     *   attentionVariant: string|null,
     *   headline: string,
     *   subtext: string,
     *   nextStep: string,
     *   note: string,
     *   primaryAction: Object|null,
     *   secondaryAction: Object|null,
     *   tertiaryAction: Object|null
     * }}
     */
    function buildSharedCommandHeroState(args) {
        args = args || {};

        var totalImages = Math.max(0, parseInt(args.totalImages != null ? args.totalImages : args.total_images, 10) || 0);
        var missingCount = Math.max(0, parseInt(args.missingCount != null ? args.missingCount : args.missing_count, 10) || 0);
        var weakCount = Math.max(
            0,
            parseInt(
                args.weakCount != null
                    ? args.weakCount
                    : (args.weak_count != null ? args.weak_count : args.needs_review_count),
                10
            ) || 0
        );
        var totalIssues = missingCount + weakCount;
        var creditsRemaining = Math.max(
            0,
            parseInt(args.creditsRemaining != null ? args.creditsRemaining : args.credits_remaining, 10) || 0
        );
        var creditsLimit = Math.max(
            1,
            parseInt(args.creditsLimit != null ? args.creditsLimit : args.credits_limit, 10) || 50
        );
        var isPro = !!(args.isPro != null ? args.isPro : args.is_pro);
        var authState = String(args.authState != null ? args.authState : args.auth_state || '').toLowerCase();
        var quotaType = String(args.quotaType != null ? args.quotaType : args.quota_type || '').toLowerCase();
        var freePlanOffer = Math.max(0, parseInt(args.freePlanOffer != null ? args.freePlanOffer : args.free_plan_offer, 10) || 50);
        var isAnonymousTrial = authState === 'anonymous' || quotaType === 'trial' || !!args.isTrial || !!args.isGuestTrial;
        var pageContext = String(args.pageContext || args.page_context || '').toLowerCase();
        var lowCreditThreshold = Math.max(
            0,
            parseInt(args.lowCreditThreshold != null ? args.lowCreditThreshold : args.low_credit_threshold, 10) || (
                isAnonymousTrial ? Math.min(2, Math.max(1, creditsLimit - 1)) : 10
            )
        );
        var libraryUrl = String(args.libraryUrl || args.library_url || '#');
        var usageUrl = String(args.usageUrl || args.usage_url || '');
        var guideUrl = String(args.guideUrl || args.guide_url || '#');
        var settingsUrl = String(args.settingsUrl || args.settings_url || '#');
        var needsReviewLibraryUrl = String(args.needsReviewLibraryUrl || args.needs_review_library_url || libraryUrl || '#');
        var message;

        if (creditsRemaining === 0) {
            message = buildBannerMessage({
                creditsRemaining: 0,
                issueCount: totalIssues,
                authState: authState,
                quotaType: quotaType,
                freePlanOffer: freePlanOffer,
                isTrial: isAnonymousTrial
            });

            return {
                state: 'out_of_credits',
                surfaceState: 'out_of_credits',
                dashboardState: 'out-of-credits',
                tone: 'paused',
                bannerVariant: 'warning',
                pageHeroVariant: 'warning',
                attentionVariant: null,
                headline: message.title,
                subtext: message.supportingLine,
                nextStep: '',
                note: '',
                primaryAction: isAnonymousTrial
                    ? {
                          label: __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator'),
                          action: 'show-auth-modal',
                          attributes: {
                              'data-auth-tab': 'register'
                          }
                      }
                    : {
                          label: __('Upgrade to Growth'),
                          action: 'show-upgrade-modal'
                      },
                secondaryAction: isAnonymousTrial
                    ? (libraryUrl
                        ? {
                              label: __('Open ALT Library'),
                              href: libraryUrl
                          }
                        : null)
                    : needsReviewLibraryUrl && needsReviewLibraryUrl !== '#'
                    ? {
                          label: __('Review ALT text'),
                          href: needsReviewLibraryUrl
                      }
                    : libraryUrl && libraryUrl !== '#'
                    ? {
                          label: __('Review ALT text'),
                          href: libraryUrl
                      }
                    : null,
                tertiaryAction: null
            };
        }

        if (creditsRemaining > 0 && creditsRemaining <= lowCreditThreshold) {
            message = buildBannerMessage({
                creditsRemaining: creditsRemaining,
                issueCount: totalIssues,
                authState: authState,
                quotaType: quotaType,
                freePlanOffer: freePlanOffer,
                isTrial: isAnonymousTrial
            });

            return {
                state: 'low_credits',
                surfaceState: 'low_credits',
                dashboardState: 'low-credits',
                tone: 'attention',
                bannerVariant: 'warning',
                pageHeroVariant: 'warning',
                attentionVariant: null,
                headline: message.title,
                subtext: message.supportingLine,
                nextStep: '',
                note: '',
                primaryAction: isAnonymousTrial
                    ? {
                          label: __('Continue fixing images', 'beepbeep-ai-alt-text-generator'),
                          action: 'show-auth-modal',
                          attributes: {
                              'data-auth-tab': 'register'
                          }
                      }
                    : {
                          label: __('Upgrade to Growth'),
                          action: 'show-upgrade-modal'
                      },
                secondaryAction: isAnonymousTrial
                    ? (libraryUrl
                        ? {
                              label: __('Open ALT Library'),
                              href: libraryUrl
                          }
                        : null)
                    : needsReviewLibraryUrl && needsReviewLibraryUrl !== '#'
                    ? {
                          label: __('Review ALT text'),
                          href: needsReviewLibraryUrl
                      }
                    : libraryUrl && libraryUrl !== '#'
                    ? {
                          label: __('Review ALT text'),
                          href: libraryUrl
                      }
                    : null,
                tertiaryAction: null
            };
        }

        if (totalIssues > 0) {
            var onLibraryPage = pageContext === 'library';
            var missingHeadline = sprintf(
                _n('%s image missing ALT text', '%s images missing ALT text', missingCount),
                formatCount(missingCount)
            );
            return {
                state: 'needs_attention',
                surfaceState: missingCount > 0 ? 'missing' : 'weak',
                dashboardState: 'incomplete',
                tone: 'attention',
                bannerVariant: 'warning',
                pageHeroVariant: 'warning',
                attentionVariant: missingCount > 0 ? (weakCount > 0 ? 'mixed' : 'missing') : 'weak',
                headline: missingCount > 0 && onLibraryPage ? missingHeadline : __('Your library needs attention'),
                subtext:
                    missingCount > 0 && onLibraryPage
                        ? __('Generate descriptions for every image that still needs ALT text.')
                        : __('Some images are missing ALT text or need a stronger description.'),
                nextStep: onLibraryPage && missingCount > 0 ? '' : buildIssueAttentionMessage(totalIssues),
                note: '',
                primaryAction: missingCount > 0
                    ? {
                          label: onLibraryPage ? __('Optimize all missing') : __('Generate missing ALT text'),
                          action: 'generate-missing',
                          bbaiAction: 'generate_missing'
                      }
                    : {
                          label: __('Review ALT text'),
                          href: needsReviewLibraryUrl
                      },
                secondaryAction:
                    onLibraryPage && missingCount > 0
                        ? null
                        : libraryUrl
                          ? {
                                label: __('Open ALT Library'),
                                href: libraryUrl
                            }
                          : null,
                tertiaryAction: null
            };
        }

        if (totalImages > 0) {
            return {
                state: 'healthy',
                surfaceState: 'healthy',
                dashboardState: isPro ? 'healthy-pro' : 'healthy-free',
                tone: 'healthy',
                bannerVariant: 'success',
                pageHeroVariant: 'success',
                attentionVariant: null,
                headline: __('Your library is in great shape'),
                subtext: __('All images are optimized and up to date.'),
                nextStep: '',
                note: '',
                primaryAction: isAnonymousTrial
                    ? (pageContext === 'library'
                        ? {
                              label: __('Rescan media library'),
                              action: 'rescan-media-library'
                          }
                        : libraryUrl
                        ? {
                              label: __('Open ALT Library'),
                              href: libraryUrl
                          }
                        : {
                              label: __('Continue fixing images'),
                              action: 'show-auth-modal',
                              attributes: {
                                  'data-auth-tab': 'register'
                              }
                          })
                    : isPro
                    ? {
                          label: __('Enable auto-optimization'),
                          href: settingsUrl
                      }
                    : {
                          label: __('Enable auto-optimization'),
                          action: 'show-upgrade-modal'
                      },
                secondaryAction: isAnonymousTrial
                    ? {
                          label: __('Continue fixing images'),
                          action: 'show-auth-modal',
                          attributes: {
                              'data-auth-tab': 'register'
                          }
                      }
                    : {
                          label: __('Review ALT text'),
                          action: 'rescan-media-library'
                      },
                tertiaryAction: null
            };
        }

        return {
            state: 'first_run',
            surfaceState: 'empty',
            dashboardState: 'first-run',
            tone: 'setup',
            bannerVariant: 'success',
            pageHeroVariant: 'neutral',
            attentionVariant: null,
            headline: __('Get started with your media library'),
            subtext: __('Scan your library to find missing ALT text and improve accessibility.'),
            nextStep: __('Start by scanning your media library.'),
            note: '',
            primaryAction: {
                label: __('Scan media library'),
                bbaiAction: 'scan-opportunity'
            },
            secondaryAction: guideUrl
                ? {
                      label: __('Learn how'),
                      href: guideUrl
                  }
                : null,
            tertiaryAction: null
        };
    }

    global.bbaiBuildBannerMessage = buildBannerMessage;
    global.bbaiBuildCreditSupportingLine = buildCreditSupportingLine;
    global.bbaiBuildIssueAttentionMessage = buildIssueAttentionMessage;
    global.bbaiBuildSharedCommandHeroState = buildSharedCommandHeroState;
    global.bbaiGetSharedCommandHeroIconMarkup = getSharedCommandHeroIconMarkup;
    global.bbaiGetActiveBanner = getActiveBanner;
})(typeof window !== 'undefined' ? window : this);
