# Hooks and Filters Reference

Complete reference for all WordPress hooks and filters available in the usage tracking system.

---

## Filters

### `alttextai_usage_thresholds`

Filter usage threshold percentages.

**Parameters**:
- `$thresholds` (array) - Threshold percentages

**Default**:
```php
[
    'warning' => 75,
    'critical' => 90,
    'block' => 100,
]
```

**Example**:
```php
add_filter('alttextai_usage_thresholds', function($thresholds) {
    return [
        'warning' => 80,   // Warn at 80%
        'critical' => 95,  // Critical at 95%
        'block' => 100,    // Block at 100%
    ];
});
```

---

### `alttextai_rate_limit_max_requests`

Filter maximum requests per rate limit window.

**Parameters**:
- `$max_requests` (int) - Maximum requests

**Default**: `100`

**Example**:
```php
add_filter('alttextai_rate_limit_max_requests', function($max) {
    return 200; // Increase to 200 requests
});
```

---

### `alttextai_rate_limit_max_tokens`

Filter maximum tokens per rate limit window.

**Parameters**:
- `$max_tokens` (int) - Maximum tokens

**Default**: `50000`

**Example**:
```php
add_filter('alttextai_rate_limit_max_tokens', function($max) {
    return 100000; // Increase to 100k tokens
});
```

---

### `alttextai_rate_limit_window`

Filter rate limit window duration in seconds.

**Parameters**:
- `$window` (int) - Window in seconds

**Default**: `300` (5 minutes)

**Example**:
```php
add_filter('alttextai_rate_limit_window', function($window) {
    return 600; // Increase to 10 minutes
});
```

---

### `alttextai_plan_limit`

Filter usage limit for a plan.

**Parameters**:
- `$limit` (int) - Usage limit
- `$plan` (string) - Plan name

**Default Limits**:
- `free`: 50
- `pro`: 10000
- `agency`: 100000
- `credits`: 0 (unlimited)

**Example**:
```php
add_filter('alttextai_plan_limit', function($limit, $plan) {
    if ($plan === 'pro') {
        return 20000; // Increase pro plan to 20k
    }
    return $limit;
}, 10, 2);
```

---

### `alttextai_before_log_event`

Filter event data before logging.

**Parameters**:
- `$args` (array) - Event arguments
- `$install_id` (string) - Installation ID

**Example**:
```php
add_filter('alttextai_before_log_event', function($args, $install_id) {
    // Add custom context
    $args['context']['custom_field'] = 'value';
    return $args;
}, 10, 2);
```

---

### `alttextai_before_sync_events`

Filter sync payload before sending to backend.

**Parameters**:
- `$payload` (array) - Sync payload
- `$events` (array) - Events being synced

**Example**:
```php
add_filter('alttextai_before_sync_events', function($payload, $events) {
    // Add metadata
    $payload['custom_metadata'] = 'value';
    return $payload;
}, 10, 2);
```

---

## Actions

### `alttextai_after_log_event`

Fired after a usage event is logged.

**Parameters**:
- `$event_id` (int) - Database event ID
- `$args` (array) - Event arguments
- `$install_id` (string) - Installation ID

**Example**:
```php
add_action('alttextai_after_log_event', function($event_id, $args, $install_id) {
    // Send to external analytics
    wp_remote_post('https://analytics.example.com/events', [
        'body' => json_encode([
            'event_id' => $event_id,
            'tokens' => $args['total_tokens'],
        ])
    ]);
}, 10, 3);
```

---

### `alttextai_after_sync_events`

Fired after syncing events to backend.

**Parameters**:
- `$response` (array) - Backend response
- `$events` (array) - Events that were synced

**Example**:
```php
add_action('alttextai_after_sync_events', function($response, $events) {
    if ($response['success']) {
        error_log('Successfully synced ' . count($events) . ' events');
    } else {
        error_log('Sync failed: ' . $response['error']);
    }
}, 10, 2);
```

---

### `alttextai_usage_threshold_reached`

Fired when a usage threshold is reached.

**Parameters**:
- `$level` (string) - Threshold level (warning, critical, block)
- `$used` (int) - Current usage
- `$limit` (int) - Usage limit

**Example**:
```php
add_action('alttextai_usage_threshold_reached', function($level, $used, $limit) {
    if ($level === 'critical') {
        // Send urgent notification
        wp_mail('admin@example.com', 'Critical Usage Alert', 
            "Usage at {$used}/{$limit}");
    }
}, 10, 3);
```

---

### `alttextai_rate_limit_exceeded`

Fired when rate limit is exceeded.

**Parameters**:
- `$user_id` (int) - User ID
- `$rate_limit_data` (array) - Rate limit information

**Example**:
```php
add_action('alttextai_rate_limit_exceeded', function($user_id, $data) {
    error_log("User {$user_id} exceeded rate limit: {$data['current_requests']} requests");
}, 10, 2);
```

---

## Usage Examples

### Custom Thresholds

```php
// Set custom thresholds
add_filter('alttextai_usage_thresholds', function($thresholds) {
    return [
        'warning' => 50,   // Earlier warning
        'critical' => 80,  // Earlier critical
        'block' => 100,
    ];
});
```

### Adjust Rate Limits

```php
// More lenient rate limits for VIP users
add_filter('alttextai_rate_limit_max_requests', function($max, $user_id) {
    $user = get_userdata($user_id);
    if ($user && in_array('vip', $user->roles)) {
        return 500; // 5x normal limit
    }
    return $max;
});
```

### Log to External Service

```php
// Send events to external analytics
add_action('alttextai_after_log_event', function($event_id, $args, $install_id) {
    wp_remote_post('https://api.example.com/events', [
        'body' => json_encode([
            'event_id' => $event_id,
            'install_id' => $install_id,
            'tokens' => $args['total_tokens'],
            'timestamp' => current_time('mysql'),
        ]),
    ]);
}, 10, 3);
```

### Modify Plan Limits

```php
// Custom plan limits based on subscription
add_filter('alttextai_plan_limit', function($limit, $plan) {
    // Get custom limit from subscription system
    $subscription = get_user_meta(get_current_user_id(), 'subscription_limit', true);
    if ($subscription) {
        return intval($subscription);
    }
    return $limit;
}, 10, 2);
```

---

*For more examples, see the main documentation files.*

