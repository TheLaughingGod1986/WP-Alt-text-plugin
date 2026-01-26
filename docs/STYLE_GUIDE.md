# BeepBeep AI CSS Style Guide

This document outlines the CSS architecture, design tokens, and component patterns used in the BeepBeep AI Alt Text Generator WordPress plugin.

## Table of Contents

1. [Design Tokens](#design-tokens)
2. [Typography](#typography)
3. [Colors](#colors)
4. [Spacing](#spacing)
5. [Components](#components)
6. [Utility Classes](#utility-classes)
7. [Dark Mode](#dark-mode)
8. [Naming Conventions](#naming-conventions)

---

## Design Tokens

All design values are defined as CSS custom properties in `_tokens.css`. Never use hard-coded values.

### Usage

```css
/* Good */
.my-element {
    color: var(--bbai-text);
    padding: var(--bbai-space-4);
}

/* Bad */
.my-element {
    color: #1f2937;
    padding: 16px;
}
```

---

## Typography

### Font Family

| Token | Value |
|-------|-------|
| `--bbai-font` | System font stack (sans-serif) |
| `--bbai-font-mono` | Monospace font stack |

### Font Sizes

| Token | Size |
|-------|------|
| `--bbai-text-xs` | 0.75rem (12px) |
| `--bbai-text-sm` | 0.875rem (14px) |
| `--bbai-text-base` | 1rem (16px) |
| `--bbai-text-lg` | 1.125rem (18px) |
| `--bbai-text-xl` | 1.25rem (20px) |
| `--bbai-text-2xl` | 1.5rem (24px) |
| `--bbai-text-3xl` | 1.875rem (30px) |

### Font Weights

| Token | Weight |
|-------|--------|
| `--bbai-font-normal` | 400 |
| `--bbai-font-medium` | 500 |
| `--bbai-font-semibold` | 600 |
| `--bbai-font-bold` | 700 |

---

## Colors

### Brand Colors

| Token | Light Mode | Usage |
|-------|------------|-------|
| `--bbai-primary` | #6366f1 | Primary actions, links |
| `--bbai-primary-hover` | #4f46e5 | Hover states |
| `--bbai-primary-light` | #eef2ff | Backgrounds |
| `--bbai-accent` | #14b8a6 | Secondary emphasis |

### Status Colors

| Status | Main | Light | Background |
|--------|------|-------|------------|
| Success | `--bbai-success` (#10b981) | `--bbai-success-light` | `--bbai-success-bg` |
| Warning | `--bbai-warning` (#f59e0b) | `--bbai-warning-light` | `--bbai-warning-bg` |
| Error | `--bbai-error` (#ef4444) | `--bbai-error-light` | `--bbai-error-bg` |
| Info | `--bbai-info` (#3b82f6) | `--bbai-info-light` | `--bbai-info-bg` |

### Semantic Colors

| Token | Usage |
|-------|-------|
| `--bbai-text` | Primary text |
| `--bbai-text-secondary` | Secondary text |
| `--bbai-text-muted` | Muted/placeholder text |
| `--bbai-bg` | Page background |
| `--bbai-bg-secondary` | Card/section backgrounds |
| `--bbai-border` | Default borders |

### Gray Scale

`--bbai-gray-50` through `--bbai-gray-900` (50, 100, 200, 300, 400, 500, 600, 700, 800, 900)

---

## Spacing

| Token | Value |
|-------|-------|
| `--bbai-space-1` | 0.25rem (4px) |
| `--bbai-space-2` | 0.5rem (8px) |
| `--bbai-space-3` | 0.75rem (12px) |
| `--bbai-space-4` | 1rem (16px) |
| `--bbai-space-5` | 1.25rem (20px) |
| `--bbai-space-6` | 1.5rem (24px) |
| `--bbai-space-8` | 2rem (32px) |
| `--bbai-space-10` | 2.5rem (40px) |
| `--bbai-space-12` | 3rem (48px) |
| `--bbai-space-16` | 4rem (64px) |

---

## Components

### Buttons

Base class: `.bbai-btn`

#### Variants

```html
<!-- Primary (default) -->
<button class="bbai-btn bbai-btn-primary">Primary Action</button>

<!-- Secondary -->
<button class="bbai-btn bbai-btn-secondary">Secondary Action</button>

<!-- Ghost/Outline -->
<button class="bbai-btn bbai-btn-ghost">Ghost Button</button>

<!-- Danger -->
<button class="bbai-btn bbai-btn-danger">Delete</button>

<!-- With Icon -->
<button class="bbai-btn bbai-btn-primary bbai-btn-icon">
    <svg>...</svg>
    <span>Button Text</span>
</button>
```

#### Sizes

```html
<button class="bbai-btn bbai-btn-sm">Small</button>
<button class="bbai-btn">Default</button>
<button class="bbai-btn bbai-btn-lg">Large</button>
```

---

### Cards

Base class: `.bbai-card`

#### Modifiers

```html
<!-- Basic Card -->
<div class="bbai-card">
    <div class="bbai-card-header">
        <h3 class="bbai-card-title">Title</h3>
    </div>
    <div class="bbai-card-body">Content</div>
</div>

<!-- Stat Card -->
<div class="bbai-card bbai-card--stat">
    <div class="bbai-card-icon">...</div>
    <div class="bbai-card-value">42</div>
    <div class="bbai-card-label">Images</div>
</div>

<!-- Table Card -->
<div class="bbai-card bbai-card--table">
    <table>...</table>
</div>

<!-- Upsell Card -->
<div class="bbai-card bbai-card--upsell">...</div>

<!-- State Cards -->
<div class="bbai-card bbai-card--success">...</div>
<div class="bbai-card bbai-card--warning">...</div>
<div class="bbai-card bbai-card--error">...</div>

<!-- Clickable Card -->
<div class="bbai-card bbai-card--clickable">...</div>
```

---

### Badges

#### Status Badges

```html
<span class="bbai-badge bbai-badge-success">Active</span>
<span class="bbai-badge bbai-badge-warning">Pending</span>
<span class="bbai-badge bbai-badge-error">Failed</span>
<span class="bbai-badge bbai-badge-info">Processing</span>
```

#### SEO Quality Badges

```html
<!-- Unified badge showing grade + character count -->
<span class="bbai-seo-unified-badge bbai-seo-unified-badge--excellent"
      data-bbai-tooltip="SEO breakdown...">
    A <span class="bbai-seo-unified-badge__chars">(84)</span>
</span>
```

Grade variants: `--excellent`, `--good`, `--fair`, `--poor`, `--needs-work`, `--empty`

#### Plan Badges

```html
<span class="bbai-plan-badge bbai-plan-badge--free">Free</span>
<span class="bbai-plan-badge bbai-plan-badge--pro">Pro</span>
<span class="bbai-plan-badge bbai-plan-badge--agency">Agency</span>
```

---

### Forms

```html
<!-- Text Input -->
<input type="text" class="bbai-input" placeholder="Enter value...">

<!-- Select -->
<select class="bbai-select">
    <option>Option 1</option>
</select>

<!-- Textarea -->
<textarea class="bbai-textarea"></textarea>

<!-- Checkbox/Radio -->
<label class="bbai-checkbox">
    <input type="checkbox">
    <span>Label text</span>
</label>

<!-- Form Group -->
<div class="bbai-form-group">
    <label class="bbai-label">Field Label</label>
    <input class="bbai-input">
    <p class="bbai-form-hint">Helper text here</p>
</div>
```

---

### Alerts

```html
<div class="bbai-alert bbai-alert-success">Success message</div>
<div class="bbai-alert bbai-alert-warning">Warning message</div>
<div class="bbai-alert bbai-alert-error">Error message</div>
<div class="bbai-alert bbai-alert-info">Info message</div>
```

---

### Loading Skeletons

```html
<!-- Text skeleton -->
<div class="bbai-skeleton bbai-skeleton--text"></div>
<div class="bbai-skeleton bbai-skeleton--text-sm"></div>
<div class="bbai-skeleton bbai-skeleton--text-lg"></div>

<!-- Image thumbnail -->
<div class="bbai-skeleton bbai-skeleton--thumbnail"></div>

<!-- Badge -->
<div class="bbai-skeleton bbai-skeleton--badge"></div>

<!-- Button -->
<div class="bbai-skeleton bbai-skeleton--button"></div>

<!-- Skeleton card -->
<div class="bbai-skeleton-card">
    <div class="bbai-skeleton-card__header">
        <div class="bbai-skeleton bbai-skeleton--avatar"></div>
        <div class="bbai-skeleton bbai-skeleton--text-lg"></div>
    </div>
    <div class="bbai-skeleton-card__body">
        <div class="bbai-skeleton bbai-skeleton--text"></div>
        <div class="bbai-skeleton bbai-skeleton--text-sm"></div>
    </div>
</div>
```

---

## Utility Classes

### Spacing

```html
<!-- Margin -->
<div class="bbai-mt-4">Margin top</div>
<div class="bbai-mb-6">Margin bottom</div>
<div class="bbai-mr-2">Margin right</div>
<div class="bbai-ml-2">Margin left</div>

<!-- Padding -->
<div class="bbai-p-4">Padding all</div>
<div class="bbai-py-2">Padding vertical</div>
<div class="bbai-px-4">Padding horizontal</div>
```

### Display

```html
<div class="bbai-hidden">Hidden</div>
<div class="bbai-flex">Flexbox</div>
<div class="bbai-grid">Grid</div>
```

### Text

```html
<span class="bbai-text-sm">Small text</span>
<span class="bbai-text-muted">Muted text</span>
<span class="bbai-text-center">Centered text</span>
```

---

## Dark Mode

Dark mode is automatically enabled via `prefers-color-scheme: dark` media query. All color tokens are redefined for dark mode in `_tokens.css`.

### Key Changes in Dark Mode

- Gray scale is inverted (50 becomes darkest, 900 becomes lightest)
- Status colors are slightly brighter for readability
- Shadows use higher opacity
- Backgrounds use darker base colors

### Testing Dark Mode

```css
/* Force dark mode for testing */
@media (prefers-color-scheme: dark) {
    :root { /* dark mode tokens */ }
}
```

---

## Naming Conventions

### BEM Pattern

We use a modified BEM (Block Element Modifier) naming convention:

```
.bbai-[block]
.bbai-[block]__[element]
.bbai-[block]--[modifier]
```

### Examples

```css
/* Block */
.bbai-card { }

/* Element */
.bbai-card__header { }
.bbai-card__body { }

/* Modifier */
.bbai-card--featured { }
.bbai-card--compact { }
```

### Prefix

All classes use the `bbai-` prefix to avoid conflicts with WordPress admin styles.

---

## File Structure

```
assets/src/css/unified/
├── _tokens.css        # Design tokens (variables)
├── _base.css          # Reset and base styles
├── _layout.css        # Page layouts
├── _cards.css         # Card components
├── _buttons.css       # Button variants
├── _badges.css        # Badge components
├── _forms.css         # Form elements
├── _tables.css        # Table styles
├── _modals.css        # Modal dialogs
├── _pages.css         # Page-specific styles
├── _utilities.css     # Utility classes + skeletons
├── _animations.css    # Keyframes and transitions
├── _header.css        # Header component
├── _alerts.css        # Alert messages
├── _auth-modal.css    # Auth modal specific
├── _upgrade-modal.css # Upgrade modal specific
├── _bulk-progress.css # Bulk progress modal
├── _api-notice.css    # API notice modal
└── _misc.css          # Miscellaneous styles
```

---

## Build Process

CSS is built using the build script:

```bash
node scripts/build-css.js
```

This concatenates all partials and generates:
- `assets/css/unified.css` (development)
- `assets/css/unified.min.css` (production, minified)

---

## Best Practices

1. **Use tokens** - Never hard-code colors, spacing, or sizes
2. **Prefer utilities** - Use utility classes for one-off spacing/layout
3. **Follow BEM** - Keep class names descriptive and consistent
4. **Test dark mode** - Ensure all components work in both themes
5. **Avoid !important** - Specificity issues indicate architecture problems
6. **Keep specificity low** - Prefer single class selectors
7. **Document changes** - Update this guide when adding new patterns
