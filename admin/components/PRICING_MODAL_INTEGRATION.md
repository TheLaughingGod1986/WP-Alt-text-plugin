# Pricing Modal Integration Guide

## Overview

The EnterprisePricingModal is a React-based pricing modal that can be integrated into the WordPress plugin dashboard. It supports fetching user plans from the backend API and disabling upgrade buttons for users already on a plan.

## Components

### React Components (admin/components/)
- `EnterprisePricingModal.jsx` - Main modal container
- `PricingCard.jsx` - Individual plan cards with disabled state support
- `PricingModalEntry.jsx` - React entry point with API integration
- `TrustBadges.jsx` - Trust indicators
- `ComparisonTable.jsx` - Feature comparison table
- `CreditsPack.jsx` - One-time credit packs
- `MultiUserNotice.jsx` - Multi-user information panel
- `SkeletonLoader.jsx` - Loading placeholders
- `pricing-modal.css` - Modal animations and styles

### JavaScript Bridge
- `pricing-modal-bridge.js` - Vanilla JS bridge for WordPress integration

## Integration Steps

### 1. Include React and ReactDOM

The modal requires React and ReactDOM to be loaded. Add to your WordPress plugin:

```php
// In your plugin's admin_enqueue_scripts hook
wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18.2.0', true);
wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', ['react'], '18.2.0', true);
```

### 2. Enqueue the Bridge Script

```php
wp_enqueue_script(
    'bbai-pricing-modal-bridge',
    plugin_dir_url(__FILE__) . 'admin/components/pricing-modal-bridge.js',
    ['react', 'react-dom'],
    '1.0.0',
    true
);
```

### 3. Enqueue the React Components

You'll need to bundle the React components. Options:
- Use a bundler (Webpack, Vite, etc.) to create a single bundle
- Or use a module bundler in WordPress context

### 4. Include CSS

```php
wp_enqueue_style(
    'bbai-pricing-modal',
    plugin_dir_url(__FILE__) . 'admin/components/pricing-modal.css',
    [],
    '1.0.0'
);
```

### 5. Use in JavaScript

The modal is accessible via the global function:

```javascript
// Open the modal
openPricingModal('enterprise');

// Close the modal
closePricingModal();

// Set callback for plan selection
setPricingModalCallback(function(planId) {
    console.log('Plan selected:', planId);
    // Integrate with Stripe checkout or other payment flow
});
```

### 6. Replace Existing Triggers

All existing upgrade triggers with `[data-action="show-upgrade-modal"]` will automatically use the new modal. The bridge script replaces the old modal triggers.

## API Integration

### Fetching User Plan

The modal automatically fetches the user's current plan from `/api/user/plan`. The endpoint should return:

```json
{
    "plan": "pro",
    "status": "active"
}
```

Or:
```json
{
    "success": true,
    "data": {
        "plan": "pro"
    }
}
```

The modal handles both response formats.

### Plan Selection Callback

Set a callback to handle plan selection:

```javascript
setPricingModalCallback(function(planId) {
    // planId will be 'pro', 'agency', or 'enterprise'
    // Integrate with your checkout flow
    window.location.href = '/checkout?plan=' + planId;
});
```

## Features

### Disabled State
- Users already on a plan will see their plan's button disabled
- Button shows "Current Plan" instead of "Select [Plan]"
- Button is visually distinct (grayed out)

### Keyboard Accessibility
- ESC key closes modal
- Tab navigation through all interactive elements
- Focus trap keeps focus within modal
- WCAG 2.1 AA compliant contrast ratios

### Animations
- Fade-in and scale transitions on open
- Hover lift effect on pricing cards
- Ripple/press feedback on CTAs
- Skeleton loading states

### WordPress iframe Support
- Works in WordPress admin iframe environments
- Handles cross-origin API requests
- Supports WordPress REST API fallback

## WordPress REST API Endpoint

If using WordPress REST API instead of backend API, register:

```php
register_rest_route('bbai/v1', '/user/plan', [
    'methods' => 'GET',
    'callback' => 'get_user_plan',
    'permission_callback' => 'is_user_logged_in',
]);

function get_user_plan() {
    // Fetch user plan from backend API or database
    return [
        'plan' => 'pro', // or 'free', 'agency', 'enterprise'
        'status' => 'active',
    ];
}
```

## Troubleshooting

### Modal doesn't open
- Check that React and ReactDOM are loaded
- Verify the bridge script is enqueued
- Check browser console for errors

### User plan not detected
- Verify `/api/user/plan` endpoint is accessible
- Check authentication token is available in `window.bbai_ajax.jwt_token`
- Verify CORS settings if using external API

### Buttons not disabled
- Check that API returns the correct plan format
- Verify `currentPlan` prop is being passed correctly
- Check browser console for API errors

