/**
 * BeepBeep AI — State-aware micro-motion system.
 *
 * Subscribes to bbaiJobState and orchestrates coordinated visual feedback
 * across CTA, donut, inline messaging, and success states.
 *
 * All animation uses transform/opacity via CSS classes.
 * JS only toggles classes and updates text — no direct style animation.
 *
 * @package BeepBeep_AI
 * @since   5.1.0
 */
(function (global) {
    'use strict';

    /* ── Feature detect ──────────────────────────────────────────────────── */

    var reducedMotion = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── Selectors ───────────────────────────────────────────────────────── */

    var SEL = {
        primaryCta:       '[data-action="generate-missing"], [data-bbai-action="generate_missing"]',
        donutPercent:     '.bbai-circular-progress-percent',
        donutContainer:   '.bbai-circular-progress',
        statusMetric:     '[data-bbai-status-metric]',
        metricValue:      '.bbai-metric-value',
        heroBlock:        '.bbai-command-hero-host .bbai-banner, #bbai-dashboard-main .bbai-banner',
        usageBar:         '.bbai-command-meter__fill',
        usageGrowth:      '.bbai-command-meter__fill--growth',
        upgradeCta:       '.bbai-upsell-cta, [data-action="show-upgrade-modal"]',
        beforeAfter:      '.bbai-before-after-showcase'
    };

    /* ── State ───────────────────────────────────────────────────────────── */

    var processState = {
        active: false,
        total: 0,
        processed: 0,
        startPercent: 0,      // donut % when process began
        targetPercent: 0,     // estimated donut % at completion
        ctaEl: null,
        ctaOriginalText: '',
        messageEl: null,
        unsubscribe: null,
        mode: '',
        // Live count tracking (snapshot at start of generation)
        startCounts: null,    // { missing, weak, optimized, total }
        prevCounts: null      // previous tick's counts for delta detection
    };

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function qsa(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }

    function easeOutQuad(t) {
        return 1 - (1 - t) * (1 - t);
    }

    /**
     * Animate a numeric text element from current value to target.
     * Uses requestAnimationFrame for smooth counting.
     */
    function animateCount(el, to, suffix, duration) {
        if (!el) return;
        suffix = suffix || '';

        if (reducedMotion) {
            el.textContent = to + suffix;
            return;
        }

        var from = parseInt(el.textContent, 10) || 0;
        if (from === to) { el.textContent = to + suffix; return; }

        var diff = to - from;
        duration = duration || 600;
        var start = null;

        function step(ts) {
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            el.textContent = Math.round(from + diff * easeOutQuad(progress)) + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    /**
     * Create or update an inline system message above/below an anchor.
     */
    function upsertMessage(anchor, text, opts) {
        if (!anchor) return null;
        opts = opts || {};

        var id = opts.id || 'bbai-process-message';
        var existing = document.getElementById(id);

        if (existing) {
            // Cross-fade text update
            if (existing._text !== text) {
                existing._text = text;
                if (reducedMotion) {
                    existing.querySelector('.bbai-process-msg__text').textContent = text;
                } else {
                    var inner = existing.querySelector('.bbai-process-msg__text');
                    inner.style.opacity = '0';
                    setTimeout(function () {
                        inner.textContent = text;
                        inner.style.opacity = '1';
                    }, 80);
                }
            }
            return existing;
        }

        var el = document.createElement('div');
        el.id = id;
        el.className = 'bbai-process-msg' + (opts.type ? ' bbai-process-msg--' + opts.type : '');
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el._text = text;
        el.innerHTML = '<span class="bbai-process-msg__text">' + escapeHtml(text) + '</span>';

        // Insert before the CTA's parent container
        var insertTarget = anchor.parentNode;
        if (opts.position === 'after') {
            insertTarget.parentNode.insertBefore(el, insertTarget.nextSibling);
        } else {
            insertTarget.parentNode.insertBefore(el, insertTarget);
        }

        return el;
    }

    function removeMessage(id) {
        var el = document.getElementById(id || 'bbai-process-message');
        if (!el) return;
        if (reducedMotion) { el.remove(); return; }
        el.classList.add('bbai-process-msg--exiting');
        setTimeout(function () { if (el.parentNode) el.remove(); }, 200);
    }

    function escapeHtml(text) {
        if (global.bbaiEscapeHtml) return global.bbaiEscapeHtml(text);
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    /* ── CTA Morphing ────────────────────────────────────────────────────── */

    function ctaStartLoading(btn, loadingText) {
        if (!btn || btn.classList.contains('is-loading')) return;

        // Lock width
        var rect = btn.getBoundingClientRect();
        btn.style.minWidth = rect.width + 'px';

        processState.ctaOriginalText = btn.textContent;
        processState.ctaEl = btn;

        btn.setAttribute('disabled', 'disabled');
        btn.classList.add('is-loading');

        if (loadingText) {
            btn.setAttribute('data-loading-text', loadingText);
        }
    }

    /**
     * Morph CTA to a new label with opacity crossfade.
     */
    function ctaMorph(btn, newText) {
        if (!btn) return;
        btn.classList.remove('is-loading');
        btn.removeAttribute('data-loading-text');

        if (reducedMotion) {
            btn.textContent = newText;
            return;
        }

        btn.style.opacity = '0';
        setTimeout(function () {
            btn.textContent = newText;
            btn.style.opacity = '1';
        }, 120);
    }

    function ctaRestore(btn) {
        if (!btn) return;
        btn.classList.remove('is-loading');
        btn.removeAttribute('data-loading-text');
        btn.removeAttribute('disabled');
        btn.style.minWidth = '';
        btn.style.opacity = '';
        if (processState.ctaOriginalText) {
            btn.textContent = processState.ctaOriginalText;
        }
    }

    /* ── Donut ───────────────────────────────────────────────────────────── */

    function readDonutPercent() {
        var el = qs(SEL.donutPercent);
        return el ? (parseInt(el.textContent, 10) || 0) : 0;
    }

    function animateDonutPercent(target) {
        var el = qs(SEL.donutPercent);
        if (!el) return;
        animateCount(el, Math.round(target), '%', 800);
    }

    function donutComplete() {
        var container = qs(SEL.donutContainer);
        if (!container || reducedMotion) return;
        container.classList.add('is-complete');
        setTimeout(function () { container.classList.remove('is-complete'); }, 500);
    }

    /* ── Metric Flash ────────────────────────────────────────────────────── */

    function flashMetrics() {
        qsa(SEL.metricValue).forEach(function (el) {
            el.classList.remove('is-updated');
            void el.offsetWidth; // force reflow for re-trigger
            el.classList.add('is-updated');
        });
    }

    /* ── Live Count + Donut Sync ────────────────────────────────────────── */

    /**
     * Read current counts from the dashboard root data attributes.
     */
    function readRootCounts() {
        var root = document.querySelector('[data-bbai-dashboard-root="1"]');
        if (!root) return null;
        return {
            missing:   Math.max(0, parseInt(root.getAttribute('data-bbai-missing-count'), 10) || 0),
            weak:      Math.max(0, parseInt(root.getAttribute('data-bbai-weak-count'), 10) || 0),
            optimized: Math.max(0, parseInt(root.getAttribute('data-bbai-optimized-count'), 10) || 0),
            total:     Math.max(0, parseInt(root.getAttribute('data-bbai-total-count'), 10) || 0)
        };
    }

    /**
     * Write counts to the dashboard root and trigger hero/status-row sync.
     * Also updates the donut gradient in the same cycle.
     */
    function applyLiveCounts(counts) {
        var root = document.querySelector('[data-bbai-dashboard-root="1"]');
        if (!root) return;

        root.setAttribute('data-bbai-missing-count', String(counts.missing));
        root.setAttribute('data-bbai-weak-count', String(counts.weak));
        root.setAttribute('data-bbai-optimized-count', String(counts.optimized));
        root.setAttribute('data-bbai-total-count', String(counts.total));
        root.setAttribute('data-bbai-actionable-state', counts.missing > 0 ? 'missing' : (counts.weak > 0 ? 'review' : 'complete'));
        root.setAttribute('data-bbai-actionable-count', String(Math.max(0, counts.missing + counts.weak)));

        // Sync donut gradient immediately
        syncDonutGradient(counts);

        // Trigger hero/status-row sync via the global sync function
        if (typeof global.bbaiSyncDashboardStateRoot === 'function') {
            global.bbaiSyncDashboardStateRoot();
        }
    }

    /**
     * Build and apply a donut conic-gradient from counts.
     */
    function syncDonutGradient(counts) {
        var donut = document.querySelector('[data-bbai-status-donut]');
        if (!donut || !counts.total) return;

        var optimizedEnd = (360 * counts.optimized / counts.total);
        var weakEnd = optimizedEnd + (360 * counts.weak / counts.total);
        var missingEnd = weakEnd + (360 * counts.missing / counts.total);

        donut.style.background = 'conic-gradient(' +
            '#22c55e 0deg ' + optimizedEnd.toFixed(3) + 'deg, ' +
            '#f59e0b ' + optimizedEnd.toFixed(3) + 'deg ' + weakEnd.toFixed(3) + 'deg, ' +
            '#ef4444 ' + weakEnd.toFixed(3) + 'deg ' + missingEnd.toFixed(3) + 'deg, ' +
            '#d7dee8 ' + missingEnd.toFixed(3) + 'deg 360deg)';
    }

    /**
     * Derive estimated counts for the current tick during generation.
     * Assumes each successful generation moves one image from missing → optimized.
     * Failed items move missing → weak (needs review).
     */
    function deriveTickCounts(jobState) {
        if (!processState.startCounts) return null;

        var start = processState.startCounts;
        var successes = jobState.successes || 0;
        var failures = jobState.failures || 0;
        var mode = String(processState.mode || jobState.mode || '');

        if (mode === 'regenerate-weak') {
            return {
                missing: start.missing,
                weak: Math.max(0, start.weak - successes),
                optimized: start.optimized + successes,
                total: start.total
            };
        }

        if (mode === 'fix-all-issues') {
            var processed = successes + failures;
            var processedMissing = Math.min(processed, start.missing);
            var successMissing = Math.min(successes, processedMissing);
            var successWeak = Math.max(0, successes - successMissing);
            var failureMissing = Math.max(0, processedMissing - successMissing);

            return {
                missing: Math.max(0, start.missing - processedMissing),
                weak: Math.max(0, start.weak - successWeak) + failureMissing,
                optimized: start.optimized + successes,
                total: start.total
            };
        }

        // Each success: missing -1, optimized +1
        // Each failure: missing -1, weak +1 (needs review)
        var processed = successes + failures;
        var missingDelta = Math.min(processed, start.missing);

        var newMissing   = Math.max(0, start.missing - missingDelta);
        var newOptimized = start.optimized + Math.min(successes, start.missing);
        var newWeak      = start.weak + Math.min(failures, Math.max(0, start.missing - successes));

        return {
            missing:   newMissing,
            weak:      newWeak,
            optimized: newOptimized,
            total:     start.total
        };
    }

    /**
     * Animate status-row pill counts with delta-aware pulse/fade classes.
     * Source (decreasing) gets .is-count-leaving, destination gets .is-count-entering.
     */
    function animateStatusRowDeltas(prevCounts, newCounts) {
        if (!prevCounts || !newCounts) return;

        var segments = ['missing', 'weak', 'optimized', 'all'];
        var countMap = {
            missing: newCounts.missing,
            weak: newCounts.weak,
            optimized: newCounts.optimized,
            all: newCounts.total
        };
        var prevMap = {
            missing: prevCounts.missing,
            weak: prevCounts.weak,
            optimized: prevCounts.optimized,
            all: prevCounts.total
        };

        var row = document.querySelector('[data-bbai-dashboard-status-nav]');
        if (!row) return;

        segments.forEach(function (seg) {
            var pill = row.querySelector('[data-bbai-status-segment="' + seg + '"]');
            if (!pill) return;

            var countNode = pill.querySelector('[data-bbai-dashboard-status-count]');
            var prev = prevMap[seg];
            var next = countMap[seg];

            if (prev === next) return;

            // Animate the count text
            if (countNode) {
                animateCount(countNode, next, '', 260);
            }

            if (reducedMotion) return;

            // Apply directional pulse classes
            if (next < prev) {
                pill.classList.remove('is-count-leaving');
                void pill.offsetWidth;
                pill.classList.add('is-count-leaving');
                setTimeout(function () { pill.classList.remove('is-count-leaving'); }, 260);
            } else if (next > prev) {
                pill.classList.remove('is-count-entering');
                void pill.offsetWidth;
                pill.classList.add('is-count-entering');
                setTimeout(function () { pill.classList.remove('is-count-entering'); }, 260);
            }
        });
    }

    /**
     * Auto-switch active status filter when missing reaches 0 during generation.
     */
    function autoSwitchActiveFilter(counts) {
        var row = document.querySelector('[data-bbai-dashboard-status-nav]');
        var card;
        if (!row) return;

        var currentActive = row.getAttribute('data-bbai-status-active-segment');
        if (currentActive !== 'missing') return;
        if (counts.missing > 0) return;

        // Missing reached 0 — switch to next priority
        var nextSegment = counts.weak > 0 ? 'weak' : 'optimized';
        card = typeof row.closest === 'function'
            ? row.closest('[data-bbai-dashboard-status-card]')
            : null;
        row.setAttribute('data-bbai-status-active-segment', nextSegment);
        row.setAttribute('data-bbai-status-selected-segment', nextSegment);
        if (card) {
            card.setAttribute('data-bbai-status-active-segment', nextSegment);
            card.setAttribute('data-bbai-status-selected-segment', nextSegment);
        }

        Array.prototype.forEach.call(row.querySelectorAll('[data-bbai-dashboard-status-pill]'), function (pill) {
            var seg = pill.getAttribute('data-bbai-status-segment');
            var isActive = seg === nextSegment;
            pill.classList.toggle('bbai-filter-group__item--active', isActive);
            pill.classList.toggle('is-active', isActive);
            if (isActive) {
                pill.setAttribute('aria-current', 'true');
            } else {
                pill.removeAttribute('aria-current');
            }
        });
    }

    /* ── Card Glow ───────────────────────────────────────────────────────── */

    function cardGlow(cardEl) {
        if (!cardEl || reducedMotion) return;
        cardEl.classList.add('is-success-glow');
        setTimeout(function () { cardEl.classList.remove('is-success-glow'); }, 1200);
    }

    /* ── Success Inline ──────────────────────────────────────────────────── */

    function showSuccessInline(anchor, message, ttl) {
        if (!anchor) return null;

        var existing = anchor.parentNode.querySelector('.bbai-success-inline');
        if (existing) existing.remove();

        var el = document.createElement('div');
        el.className = 'bbai-success-inline';
        el.innerHTML =
            '<span class="bbai-success-inline__icon" aria-hidden="true">' +
            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3a1 1 0 0 1 0 1.4l-6 6a1 1 0 0 1-1.4 0l-3-3a1 1 0 1 1 1.4-1.4L6.6 9.6l5.3-5.3a1 1 0 0 1 1.4 0z" fill="#059669"/></svg>' +
            '</span> ' +
            '<span class="bbai-success-inline__text">' + escapeHtml(message) + '</span>';

        anchor.parentNode.insertBefore(el, anchor.nextSibling);

        if (ttl !== 0) {
            setTimeout(function () {
                if (!el.parentNode) return;
                el.style.opacity = '0';
                el.style.transition = 'opacity 240ms ease';
                setTimeout(function () { if (el.parentNode) el.remove(); }, 260);
            }, ttl || 6000);
        }
        return el;
    }

    /* ── Card Content Transition ─────────────────────────────────────────── */

    function transitionCard(card, updateFn) {
        if (!card || reducedMotion) { updateFn(); return; }
        card.classList.add('is-transitioning');
        setTimeout(function () {
            updateFn();
            void card.offsetHeight;
            card.classList.remove('is-transitioning');
        }, 200);
    }

    /* ══════════════════════════════════════════════════════════════════════
       FLOW-AWARE PROCESS ORCHESTRATION
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Start a tracked generation process.
     * Wires into the CTA, inline messaging, and donut.
     *
     * @param {number} total  Total images to process.
     * @param {object} [opts]
     * @param {HTMLElement} [opts.ctaEl]  The CTA button that triggered this.
     */
    function startProcess(total, opts) {
        opts = opts || {};
        var cta = opts.ctaEl || qs(SEL.primaryCta);

        processState.active = true;
        processState.total = total;
        processState.processed = 0;
        processState.startPercent = readDonutPercent();
        processState.mode = String(opts.mode || '');

        // Snapshot current counts for delta tracking
        processState.startCounts = readRootCounts();
        processState.prevCounts = processState.startCounts
            ? { missing: processState.startCounts.missing, weak: processState.startCounts.weak,
                optimized: processState.startCounts.optimized, total: processState.startCounts.total }
            : null;

        // CTA → loading
        ctaStartLoading(cta, 'Optimising\u2026');

        // Inline system message above CTA
        upsertMessage(cta, 'Optimising ' + total + ' remaining\u2026');
    }

    /**
     * Called as each item completes. Drives staggered donut + message updates.
     *
     * @param {number} remaining  Items still to process.
     */
    function updateProcess(remaining, jobState) {
        if (!processState.active) return;

        processState.processed = processState.total - remaining;

        // Update inline message
        var cta = processState.ctaEl || qs(SEL.primaryCta);
        var completed = processState.processed;
        if (remaining > 0) {
            upsertMessage(cta, 'Optimising ' + remaining + ' remaining\u2026');
        } else {
            upsertMessage(cta, 'Finalising results\u2026');
        }

        // ── Live count updates ──────────────────────────────────────────
        // Derive new counts from job state and apply to dashboard root
        var js = jobState || (global.bbaiJobState ? global.bbaiJobState.getState() : null);
        if (js && processState.startCounts) {
            var newCounts = deriveTickCounts(js);
            if (newCounts) {
                // Animate status-row deltas before applying
                animateStatusRowDeltas(processState.prevCounts, newCounts);

                // Auto-switch filter if missing hit 0
                autoSwitchActiveFilter(newCounts);

                // Apply to root + sync donut gradient + hero
                applyLiveCounts(newCounts);

                // Store for next delta
                processState.prevCounts = {
                    missing: newCounts.missing, weak: newCounts.weak,
                    optimized: newCounts.optimized, total: newCounts.total
                };
            }
        }

        // ── Donut percentage ────────────────────────────────────────────
        var fraction = processState.total > 0
            ? processState.processed / processState.total
            : 0;
        var gap = 100 - processState.startPercent;
        var estimated = processState.startPercent + Math.round(gap * fraction);
        estimated = Math.min(estimated, 99); // never show 100% until confirmed

        animateDonutPercent(estimated);
    }

    /**
     * Complete the process. Triggers the full success reward sequence.
     *
     * @param {object} [result]
     * @param {number} [result.successes]
     * @param {number} [result.failures]
     * @param {number} [result.finalPercent]  Actual donut % from server.
     */
    function completeProcess(result) {
        if (!processState.active) return;
        result = result || {};
        processState.active = false;

        var cta = processState.ctaEl || qs(SEL.primaryCta);
        var successes = result.successes !== undefined ? result.successes : processState.processed;
        var failures = result.failures !== undefined ? result.failures : 0;
        var finalPct = result.finalPercent !== undefined ? result.finalPercent : null;
        var startCounts = processState.startCounts;
        var mode = String(result.mode || processState.mode || '');

        // ── Final count state ───────────────────────────────────────────
        // Apply final derived counts before the reward sequence
        if (startCounts) {
            var finalCounts;

            if (mode === 'regenerate-weak') {
                finalCounts = {
                    missing: startCounts.missing,
                    weak: Math.max(0, startCounts.weak - successes),
                    optimized: startCounts.optimized + successes,
                    total: startCounts.total
                };
            } else if (mode === 'fix-all-issues') {
                var processedFix = successes + failures;
                var processedMissingFix = Math.min(processedFix, startCounts.missing);
                var successMissingFix = Math.min(successes, processedMissingFix);
                var successWeakFix = Math.max(0, successes - successMissingFix);
                var failureMissingFix = Math.max(0, processedMissingFix - successMissingFix);

                finalCounts = {
                    missing: Math.max(0, startCounts.missing - processedMissingFix),
                    weak: Math.max(0, startCounts.weak - successWeakFix) + failureMissingFix,
                    optimized: startCounts.optimized + successes,
                    total: startCounts.total
                };
            } else {
                finalCounts = {
                    missing:   Math.max(0, startCounts.missing - successes - failures),
                    weak:      startCounts.weak + failures,
                    optimized: startCounts.optimized + successes,
                    total:     startCounts.total
                };
            }

            animateStatusRowDeltas(processState.prevCounts, finalCounts);
            autoSwitchActiveFilter(finalCounts);
            applyLiveCounts(finalCounts);
        }

        // ── Sequenced success reward ────────────────────────────────────
        // Step 1 (0ms): Resolve CTA loading → morph to final action
        var ctaLabel = cta && cta.textContent ? String(cta.textContent) : 'Fix all images';
        ctaMorph(cta, ctaLabel);
        if (cta) {
            cta.removeAttribute('disabled');
            cta.removeAttribute('aria-busy');
        }

        // Step 2 (200ms): Update donut to final value
        setTimeout(function () {
            if (finalPct !== null) {
                animateDonutPercent(finalPct);
            } else if (startCounts && startCounts.total > 0) {
                var pct = Math.round(((startCounts.optimized + successes) / startCounts.total) * 100);
                animateDonutPercent(pct);
            }
        }, 200);

        // Step 3 (600ms): Donut completion pulse
        setTimeout(function () {
            donutComplete();
        }, 600);

        // Step 4 (800ms): Flash metric values
        setTimeout(function () {
            flashMetrics();
        }, 800);

        // Step 5 (1000ms): Card glow on hero
        setTimeout(function () {
            var heroCard = qs('[data-bbai-funnel-hero]') ||
                           qs('.bbai-command-hero-host .bbai-banner') ||
                           qs('#bbai-dashboard-main .bbai-banner');
            cardGlow(heroCard);
        }, 1000);

        // Step 6 (1200ms): Remove process message, show success inline
        setTimeout(function () {
            removeMessage('bbai-process-message');

            var msg = 'All missing ALT text generated';
            if (mode === 'fix-all-issues' && failures === 0) {
                msg = successes + ' image' + (successes !== 1 ? 's' : '') + ' optimized';
            } else if (successes > 0 && failures > 0) {
                msg = successes + ' image' + (successes !== 1 ? 's' : '') + ' optimised, ' +
                      failures + ' need' + (failures !== 1 ? '' : 's') + ' review';
            }

            showSuccessInline(cta, msg, 8000);
        }, 1200);

        // Clean up start counts
        processState.mode = '';
        processState.startCounts = null;
        processState.prevCounts = null;
    }

    /* ══════════════════════════════════════════════════════════════════════
       UPGRADE TRIGGER SYSTEM
       ══════════════════════════════════════════════════════════════════════ */

    var upgradePulseInterval = null;

    /**
     * Show low-credits warning near usage bar.
     *
     * @param {number} remaining  Credits remaining.
     */
    function checkCreditsWarning(remaining) {
        var existingWarn = document.getElementById('bbai-credits-warning');

        if (remaining > 10 || remaining === null || remaining === undefined) {
            // Clear any existing warning
            if (existingWarn) {
                existingWarn.classList.add('bbai-process-msg--exiting');
                setTimeout(function () { if (existingWarn.parentNode) existingWarn.remove(); }, 200);
            }
            if (upgradePulseInterval) {
                clearInterval(upgradePulseInterval);
                upgradePulseInterval = null;
            }
            qsa(SEL.upgradeCta).forEach(function (el) { el.classList.remove('is-pulsing'); });
            return;
        }

        var usageBar = qs(SEL.usageBar);
        if (!usageBar) return;

        if (remaining > 0 && remaining <= 10) {
            // Low credits
            if (!existingWarn) {
                var warn = document.createElement('div');
                warn.id = 'bbai-credits-warning';
                warn.className = 'bbai-credits-warning';
                warn.setAttribute('role', 'status');
                warn.innerHTML = '<span class="bbai-credits-warning__icon" aria-hidden="true">\u26A1</span> ' +
                    '<span class="bbai-credits-warning__text">You\u2019re running low \u2014 upgrade to process your full library in one go</span>';
                var track = usageBar.closest('.bbai-command-meter') || usageBar.parentNode;
                if (track && track.parentNode) {
                    track.parentNode.insertBefore(warn, track.nextSibling);
                }
            }
        }

        if (remaining === 0) {
            // Limit reached: pulse upgrade CTA every 6s
            if (!upgradePulseInterval) {
                upgradePulseInterval = setInterval(function () {
                    qsa(SEL.upgradeCta).forEach(function (el) {
                        el.classList.remove('is-pulsing');
                        void el.offsetWidth;
                        el.classList.add('is-pulsing');
                    });
                }, 6000);
                // Fire immediately too
                qsa(SEL.upgradeCta).forEach(function (el) { el.classList.add('is-pulsing'); });
            }

            // Update warning text
            if (existingWarn) {
                var textEl = existingWarn.querySelector('.bbai-credits-warning__text');
                if (textEl) {
                    textEl.textContent = 'You\u2019ve reached your limit \u2014 upgrade to continue optimising';
                }
            }
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       JOB STATE INTEGRATION
       ══════════════════════════════════════════════════════════════════════

       Auto-wire into bbaiJobState if present.
       This makes the motion system reactive to the existing generation flow
       without requiring changes to bulk-operations or inline-generate.
       ══════════════════════════════════════════════════════════════════════ */

    var lastJobStatus = 'idle';
    var jobStateWired = false;

    function wireJobState() {
        if (jobStateWired || !global.bbaiJobState) return;
        jobStateWired = true;

        global.bbaiJobState.subscribe(function (state) {
            // Processing started
            if (state.status === 'processing' && lastJobStatus !== 'processing') {
                startProcess(state.total, { mode: state.mode || '' });
            }

            // Tick: update remaining with job state for live count derivation
            if (state.status === 'processing' && state.total > 0) {
                var remaining = Math.max(0, state.total - state.progress);
                updateProcess(remaining, state);
            }

            // Completed
            if ((state.status === 'complete' || state.status === 'error' || state.status === 'quota') &&
                lastJobStatus === 'processing') {
                completeProcess({
                    successes: state.successes || 0,
                    failures: state.failures || 0,
                    mode: state.mode || ''
                });
            }

            lastJobStatus = state.status;
        });
    }

    /* ── Usage event integration ─────────────────────────────────────────── */

    function wireUsageEvents() {
        // Listen for usage updates to check credit warnings
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('bbai:usage-updated.bbaiMotion', function (event, payload) {
                var usage = (payload && payload.usage) || (event && event.detail && event.detail.usage) || null;
                if (!usage) return;
                var remaining = parseInt(usage.remaining || usage.credits_remaining || usage.creditsRemaining, 10);
                if (!isNaN(remaining)) {
                    checkCreditsWarning(remaining);
                }
            });
        }

        // Also listen on native events
        document.addEventListener('bbai:usage-updated', function (event) {
            var usage = event && event.detail ? event.detail.usage || event.detail : null;
            if (!usage) return;
            var remaining = parseInt(usage.remaining || usage.credits_remaining || usage.creditsRemaining, 10);
            if (!isNaN(remaining)) {
                checkCreditsWarning(remaining);
            }
        });
    }

    /* ── Stats event integration (donut sync on server refresh) ──────────── */

    function wireStatsEvents() {
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('bbai:stats-updated.bbaiMotion', function (event, payload) {
                var stats = (payload && payload.stats) || (event && event.detail && event.detail.stats) || null;
                if (!stats || processState.active) return;

                // If we just completed a process and stats arrive, update the donut
                // to the true server value
                var pct = stats.coverage_pct || stats.optimized_pct;
                if (pct !== undefined && pct !== null) {
                    animateDonutPercent(Math.round(parseFloat(pct)));
                }
            });
        }
    }

    /* ── Before/After Entry ──────────────────────────────────────────────── */

    function initBeforeAfterEntry() {
        var showcase = qs(SEL.beforeAfter);
        if (!showcase || showcase.hasAttribute('data-bbai-entered')) return;

        if (reducedMotion) {
            showcase.setAttribute('data-bbai-entered', '1');
            return;
        }

        if ('IntersectionObserver' in global) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        showcase.setAttribute('data-bbai-entered', '1');
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(showcase);
        } else {
            showcase.setAttribute('data-bbai-entered', '1');
        }
    }

    /* ── Init ────────────────────────────────────────────────────────────── */

    function init() {
        initBeforeAfterEntry();
        wireJobState();
        wireUsageEvents();
        wireStatsEvents();

        // bbaiJobState may load after us — retry once
        if (!jobStateWired) {
            setTimeout(wireJobState, 500);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ── Public API ──────────────────────────────────────────────────────── */

    global.bbaiMotion = {
        // Flow orchestration
        startProcess: startProcess,
        updateProcess: updateProcess,
        completeProcess: completeProcess,

        // Component-level (for direct use)
        ctaStartLoading: ctaStartLoading,
        ctaMorph: ctaMorph,
        ctaRestore: ctaRestore,
        showSuccessInline: showSuccessInline,
        transitionCard: transitionCard,
        animateDonutPercent: animateDonutPercent,
        donutComplete: donutComplete,
        animateCount: animateCount,
        flashMetrics: flashMetrics,
        cardGlow: cardGlow,
        checkCreditsWarning: checkCreditsWarning,

        // Live dashboard sync
        applyLiveCounts: applyLiveCounts,
        syncDonutGradient: syncDonutGradient,
        animateStatusRowDeltas: animateStatusRowDeltas
    };

})(window);
