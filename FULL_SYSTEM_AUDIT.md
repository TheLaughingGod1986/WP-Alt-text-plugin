# Full System Audit & Refactoring Plan

## 1. Button Component Audit

### Issues Found:
- Multiple button style definitions across files:
  - `assets/css/components/button.css` - Old button styles
  - `assets/src/css/bbai-dashboard.css` - Duplicate button definitions
  - `assets/src/css/unified/_buttons.css` - Current unified system
  - `assets/src/css/modern-style.css` - More duplicate definitions
  - `assets/src/css/unified/_upgrade-modal.css` - Pricing card buttons

### Action Items:
1. ✅ Upgrade modal buttons now use `bbai-btn` classes (completed)
2. ⏳ Audit all partial files for button class usage
3. ⏳ Remove old button CSS files or consolidate
4. ⏳ Ensure all buttons use unified `_buttons.css` system

## 2. Card Component Audit

### Files Using Cards:
- `dashboard-body.php` - Usage card, upsell card, metric cards
- `library-tab.php` - Empty state card, metric cards
- `analytics-tab.php` - Chart cards
- `credit-usage-content.php` - Summary cards
- `guide-tab.php` - Guide cards
- `metric-cards.php` - Reusable metric component ✅
- `bottom-upsell-cta.php` - Reusable upsell component ✅

### Action Items:
1. ✅ Metric cards consolidated into `metric-cards.php` (completed)
2. ⏳ Verify all cards use `bbai-card` base class
3. ⏳ Check for duplicate card styling

## 3. Badge Component Audit

### Files Using Badges:
- `dashboard-body.php` - Plan badges (FREE, GROWTH, PRO)
- `library-tab.php` - Status badges
- `guide-tab.php` - Step badges
- `debug-tab.php` - Feature badges

### Action Items:
1. ⏳ Create reusable badge component
2. ⏳ Standardize badge classes

## 4. CTA Banner Audit

### Files Using CTAs:
- `dashboard-body.php` - Multiple upgrade CTAs
- `bottom-upsell-cta.php` - Reusable bottom CTA ✅
- `library-tab.php` - Includes bottom CTA ✅
- `analytics-tab.php` - Includes bottom CTA ✅
- `guide-tab.php` - Includes bottom CTA ✅
- `settings-tab.php` - Includes bottom CTA ✅

### Action Items:
1. ✅ Bottom upsell CTA is reusable (completed)
2. ⏳ Verify all tabs include bottom CTA
3. ⏳ Check for duplicate CTA implementations

## 5. Functional Testing Checklist

### Dashboard Tab:
- [ ] Generate Missing button works
- [ ] Re-optimize All button works
- [ ] Upgrade buttons open modal
- [ ] Compare plans link works
- [ ] Metric cards display correctly
- [ ] Usage stats update correctly

### ALT Library Tab:
- [ ] Upload images works
- [ ] Generate/Regenerate buttons work
- [ ] Filters work
- [ ] Table displays correctly
- [ ] Empty state displays correctly

### Analytics Tab:
- [ ] Chart renders correctly
- [ ] Period selector works
- [ ] Data displays correctly
- [ ] Empty state works

### Credit Usage Tab:
- [ ] Summary cards display
- [ ] Filters work
- [ ] Table displays correctly

### Settings Tab:
- [ ] Login/Logout works
- [ ] Account info displays
- [ ] Settings save correctly

### Upgrade Modal:
- [ ] Opens correctly
- [ ] All plan buttons work
- [ ] Checkout flow works
- [ ] Close button works

## 6. Code Quality Issues

### Dead Code:
- [ ] Remove unused CSS files
- [ ] Remove unused JavaScript
- [ ] Remove unused PHP functions

### Unused Imports:
- [ ] Check all PHP files for unused includes
- [ ] Check all JS files for unused imports
- [ ] Check all CSS files for unused imports

### Naming Consistency:
- [ ] Verify all classes use `bbai-` prefix
- [ ] Verify consistent naming patterns
- [ ] Fix any inconsistencies

## 7. Styling Normalization

### Issues:
- Multiple CSS files with overlapping styles
- Inconsistent use of design tokens
- Duplicate utility classes

### Action Items:
1. ⏳ Audit all CSS files
2. ⏳ Consolidate duplicate styles
3. ⏳ Ensure consistent use of design tokens
4. ⏳ Remove dead CSS
