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
    const BTN_SELECTOR = '#bbai-review-filter-tabs button[data-filter]';

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
            const container = document.querySelector('.bbai-library-container');
            const titleText =
                (container && container.getAttribute('data-bbai-empty-filter')) || 'No images match this filter.';
            const hintText = (container && container.getAttribute('data-bbai-empty-filter-hint')) || '';

            const stateWrap = document.createElement('div');
            stateWrap.className = 'bbai-state bbai-state--empty bbai-state--compact bbai-library-filter-empty__state';
            const titleEl = document.createElement('h3');
            titleEl.className = 'bbai-state__title';
            titleEl.textContent = titleText;
            stateWrap.appendChild(titleEl);
            if (hintText) {
                const bodyEl = document.createElement('p');
                bodyEl.className = 'bbai-state__body';
                bodyEl.textContent = hintText;
                stateWrap.appendChild(bodyEl);
            }

            const isTbody = body.tagName === 'TBODY';
            if (isTbody) {
                el = document.createElement('tr');
                el.id = EMPTY_SELECTOR.replace('#', '');
                el.className =
                    'bbai-library-filter-empty bbai-table-empty bbai-table-empty--inline bbai-state-row';
                el.setAttribute('role', 'status');
                el.setAttribute('aria-live', 'polite');
                const td = document.createElement('td');
                td.colSpan = 7;
                td.className = 'bbai-library-filter-empty__cell';
                td.appendChild(stateWrap);
                el.appendChild(td);
            } else {
                el = document.createElement('div');
                el.id = EMPTY_SELECTOR.replace('#', '');
                el.className = 'bbai-library-filter-empty bbai-table-empty bbai-table-empty--inline';
                el.setAttribute('role', 'status');
                el.setAttribute('aria-live', 'polite');
                const cell = document.createElement('div');
                cell.className = 'bbai-library-filter-empty__cell';
                cell.appendChild(stateWrap);
                el.appendChild(cell);
            }
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
            case 'weak':
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
            const isActive = btnFilter === filter || (filter === 'weak' && btnFilter === 'needs-review');
            btn.classList.toggle('bbai-alt-review-filters__btn--active', isActive);
            btn.classList.toggle('bbai-filter-group__item--active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function init() {
        const container = document.getElementById('bbai-review-filter-tabs');
        if (!container) return;

        const btns = container.querySelectorAll(BTN_SELECTOR);
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const filter = btn.getAttribute('data-filter');
                if (filter) applyFilter(filter);
            });
        });

        let initial = String(container.getAttribute('data-bbai-default-filter') || FILTER_ALL).toLowerCase();
        if (initial === 'needs_review' || initial === 'needs-review') {
            initial = 'weak';
        }
        applyFilter(initial || FILTER_ALL);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
