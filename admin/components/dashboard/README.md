# Dashboard Components

## Overview

Modern React-based dashboard for BeepBeep AI Alt Text Generator. Built with React, Tailwind CSS utility classes, and designed for conversion-focused user experience.

## Structure

### Components

- **Dashboard.jsx** - Main dashboard container component
- **PlanStatusCard.jsx** - Circular usage indicator showing plan quota usage
- **UpgradeCard.jsx** - Dynamic upgrade card that adapts to current plan
- **StatsRow.jsx** - Grid of stat cards showing key metrics
- **OptimiseImagesPanel.jsx** - Queue stats and action buttons for image optimization

### Files

- **dashboard.css** - Complete styling with Tailwind-like utility classes
- **dashboard-bridge.js** - WordPress integration bridge
- **index.js** - Component exports

## Features

### 1. Three-Zone Layout
- **Top Zone**: Plan status (left) + Upgrade card (right)
- **Middle Zone**: Key metrics (3-4 stat cards)
- **Bottom Zone**: Optimise Images panel with actions

### 2. Empty States
- Helpful messaging when no data exists
- Inline CTAs to encourage first action
- Subtle styling that doesn't dominate

### 3. Gamification
- Alt text coverage percentage with progress bar
- Encouragement messages when approaching 100% coverage
- Visual progress indicators

### 4. Upgrade Messaging
- Dynamic messaging based on plan type
- Urgent messaging when approaching quota limits (>=80%)
- Clear upgrade paths (Free → Growth → Agency)

### 5. Responsive Design
- Desktop: 2-column layout for top zone, 4-column for stats
- Tablet: Adjusted grid columns
- Mobile: Stacked layout with comfortable spacing

## Integration

### Step 1: Enqueue React and ReactDOM

```php
// In your plugin's admin_enqueue_scripts hook
wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18.2.0', true);
wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', ['react'], '18.2.0', true);
```

### Step 2: Bundle React Components

You'll need to bundle the dashboard components using a bundler (Webpack, Vite, etc.). The components use ES6 imports and need to be transpiled.

**Example Webpack config:**

```javascript
module.exports = {
  entry: './admin/components/dashboard/index.js',
  output: {
    path: __dirname + '/admin/components',
    filename: 'dashboard-bundle.js',
    library: 'bbaiDashboardComponents',
    libraryTarget: 'window'
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-react']
          }
        }
      }
    ]
  },
  externals: {
    'react': 'React',
    'react-dom': 'ReactDOM'
  }
};
```

### Step 3: Enqueue Dashboard Assets

```php
// Enqueue dashboard CSS
wp_enqueue_style(
    'bbai-dashboard',
    plugin_dir_url(__FILE__) . 'admin/components/dashboard/dashboard.css',
    [],
    '1.0.0'
);

// Enqueue bundled dashboard components
wp_enqueue_script(
    'bbai-dashboard-components',
    plugin_dir_url(__FILE__) . 'admin/components/dashboard-bundle.js',
    ['react', 'react-dom'],
    '1.0.0',
    true
);

// Enqueue dashboard bridge
wp_enqueue_script(
    'bbai-dashboard-bridge',
    plugin_dir_url(__FILE__) . 'admin/components/dashboard-bridge.js',
    ['react', 'react-dom', 'bbai-dashboard-components'],
    '1.0.0',
    true
);

// Localize script with REST API info
wp_localize_script('bbai-dashboard-bridge', 'BBAI', [
    'restRoot' => esc_url_raw(rest_url('bbai/v1/')),
    'nonce' => wp_create_nonce('wp_rest')
]);
```

### Step 4: Add React Root Container

The PHP template (`admin/partials/dashboard-body.php`) already includes:

```php
<div id="bbai-dashboard-root"></div>
```

This is where the React dashboard will render. The bridge script will automatically detect this container and render the dashboard.

## API Endpoints

The dashboard expects these REST API endpoints:

- `GET /wp-json/bbai/v1/usage` - Returns usage statistics
- `GET /wp-json/bbai/v1/stats` - Returns image statistics
- `GET /wp-json/bbai/v1/queue/stats` - Returns queue statistics

## Callbacks

The dashboard supports these callback functions (can be passed as props or defined globally):

- `onUpgradeClick()` - Shows upgrade modal
- `onOptimiseNew()` - Optimises new images
- `onOptimiseAll()` - Optimises all images again
- `onOpenLibrary()` - Navigates to ALT Library page
- `onManageBilling()` - Navigates to billing page

## Fallback Behavior

If React components are not loaded, the PHP fallback dashboard will display. The bridge script checks for component availability before rendering.

## Styling

The dashboard uses Tailwind-like utility classes defined in `dashboard.css`. All styles are scoped to the dashboard component and won't conflict with WordPress admin styles.

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11 not supported (uses ES6 features)

## Development

To develop locally:

1. Install dependencies:
```bash
npm install --save-dev webpack webpack-cli babel-loader @babel/core @babel/preset-react
```

2. Build:
```bash
npx webpack --mode development --watch
```

3. Production build:
```bash
npx webpack --mode production
```