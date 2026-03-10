/**
 * ALT Library Smart Review Filters
 * Client-side filtering of table rows by data attributes.
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */
(function () {
    'use strict';

    const FILTER_ALL = 'all';
    const FILTER_NEEDS_REVIEW = 'needs-review';
    const FILTER_MISSING = 'missing';
    const FILTER_AUTO_GENERATED = 'auto-generated';
    const FILTER_OPTIMIZED = 'optimized';

    const ROW_SELECTOR = '.bbai-library-row';
    const BODY_SELECTOR = '#bbai-library-table-body';
    const EMPTY_SELECTOR = '#bbai-library-filter-empty';
    const BTN_SELECTOR = '.bbai-alt-review-filters__btn';

    function getTableBody() {
        return document.querySelector(BODY_SELECTOR);
    }

    function getRows() {
        const body = getTableBody();
        return body ? Array.from(body.querySelectorAll(ROW_SELECTOR)) : [];
    }

    function getEmptyEl() {
        return document.querySelector(EMPTY_SELECTOR);
    }

    function createEmptyMessage() {
        const body = getTableBody();
        if (!body) return null;
        let el = getEmptyEl();
        if (!el) {
            el = document.createElement('tr');
            el.id = EMPTY_SELECTOR.replace('#', '');
            el.className = 'bbai-library-filter-empty';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            const td = document.createElement('td');
            td.colSpan = 7;
            td.className = 'bbai-library-filter-empty__cell';
            var container = document.querySelector('.bbai-library-container');
            td.textContent = (container && container.getAttribute('data-bbai-empty-filter')) || 'No images match this filter.';
            el.appendChild(td);
        }
        return el;
    }

    function rowMatchesFilter(row, filter) {
        if (filter === FILTER_ALL) return true;
        const status = row.getAttribute('data-status') || '';
        const aiSource = row.getAttribute('data-ai-source') || '';
        const altMissing = row.getAttribute('data-alt-missing') || 'false';
        const quality = row.getAttribute('data-quality') || '';

        switch (filter) {
            case FILTER_NEEDS_REVIEW:
                return quality === 'needs-review' || quality === 'poor';
            case FILTER_MISSING:
                return altMissing === 'true' || status === 'missing';
            case FILTER_AUTO_GENERATED:
                return aiSource === 'ai';
            case FILTER_OPTIMIZED:
                return status === 'optimized' && (quality === 'excellent' || quality === 'good');
            default:
                return true;
        }
    }

    function applyFilter(filter) {
        const rows = getRows();
        const body = getTableBody();
        if (!body) return;

        let visibleCount = 0;
        rows.forEach(function (row) {
            const match = rowMatchesFilter(row, filter);
            row.classList.toggle('bbai-library-row--hidden', !match);
            row.setAttribute('aria-hidden', match ? 'false' : 'true');
            if (match) visibleCount++;
        });

        let emptyEl = getEmptyEl();
        if (visibleCount === 0 && rows.length > 0) {
            emptyEl = createEmptyMessage();
            if (emptyEl && !body.contains(emptyEl)) {
                body.appendChild(emptyEl);
            }
            if (emptyEl) emptyEl.classList.remove('bbai-library-filter-empty--hidden');
        } else if (emptyEl) {
            emptyEl.classList.add('bbai-library-filter-empty--hidden');
        }

        updateActiveButton(filter);
    }

    function updateActiveButton(filter) {
        const btns = document.querySelectorAll(BTN_SELECTOR);
        btns.forEach(function (btn) {
            const btnFilter = btn.getAttribute('data-filter');
            const isActive = btnFilter === filter;
            btn.classList.toggle('bbai-alt-review-filters__btn--active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function init() {
        const container = document.querySelector('.bbai-alt-review-filters');
        if (!container) return;

        const btns = container.querySelectorAll(BTN_SELECTOR);
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const filter = btn.getAttribute('data-filter');
                if (filter) applyFilter(filter);
            });
        });

        applyFilter(FILTER_ALL);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
