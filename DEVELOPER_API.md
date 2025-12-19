# Developer API Documentation

> **Extend and customize the BeepBeep AI Alt Text Generator plugin**

---

## 🎯 Overview

This guide provides comprehensive documentation for developers who want to extend, customize, or integrate with the BeepBeep AI Alt Text Generator plugin.

---

## 📋 Table of Contents

1. [Event Bus API](#event-bus-api)
2. [WordPress Hooks](#wordpress-hooks)
3. [Service Container](#service-container)
4. [REST API Endpoints](#rest-api-endpoints)
5. [Extension Examples](#extension-examples)
6. [Contributing](#contributing)

---

## 🔌 Event Bus API

The plugin uses an internal Event Bus for decoupled communication between components.

### Subscribing to Events

```php
<?php
// Get the event bus
$event_bus = bbai_service( 'event_bus' );

// Subscribe to event
$event_bus->on( 'user.registered', function( $data ) {
    // Your code here
    error_log( 'User registered: ' . $data['email'] );
}, 10 ); // Priority (optional, default: 10)

// Subscribe once (auto-unsubscribes after first trigger)
$event_bus->once( 'plugin.activated', function( $data ) {
    // Runs only once
});
```

### Available Events

#### Authentication Events

**`user.registered`**
```php
// Fired when new user registers
$event_bus->on( 'user.registered', function( $data ) {
    // $data = ['email' => '', 'user_id' => 0, 'plan' => 'free']
});
```

**`user.logged_in`**
```php
// Fired when user logs in
$event_bus->on( 'user.logged_in', function( $data ) {
    // $data = ['email' => '', 'user_id' => 0]
});
```

**`user.logged_out`**
```php
// Fired when user logs out
$event_bus->on( 'user.logged_out', function( $data ) {
    // $data = ['user_id' => 0]
});
```

#### License Events

**`license.activated`**
```php
// Fired when license is activated
$event_bus->on( 'license.activated', function( $data ) {
    // $data = ['license_key' => '', 'plan' => 'pro', 'quota' => 1000]
});
```

**`license.deactivated`**
```php
// Fired when license is deactivated
$event_bus->on( 'license.deactivated', function( $data ) {
    // $data = ['license_key' => '']
});
```

#### Generation Events

**`generation.started`**
```php
// Fired when alt text generation starts
$event_bus->on( 'generation.started', function( $data ) {
    // $data = ['image_id' => 0, 'type' => 'single|bulk']
});
```

**`generation.completed`**
```php
// Fired when alt text generation completes
$event_bus->on( 'generation.completed', function( $data ) {
    // $data = [
    //     'image_id' => 0,
    //     'alt_text' => '',
    //     'duration' => 1.23,
    //     'tokens_used' => 50
    // ]
});
```

**`generation.failed`**
```php
// Fired when generation fails
$event_bus->on( 'generation.failed', function( $data ) {
    // $data = [
    //     'image_id' => 0,
    //     'error' => '',
    //     'reason' => 'quota|api_error|invalid_image'
    // ]
});
```

#### Queue Events

**`queue.job_added`**
```php
// Fired when job added to queue
$event_bus->on( 'queue.job_added', function( $data ) {
    // $data = ['job_id' => 0, 'type' => '', 'priority' => 10]
});
```

**`queue.job_completed`**
```php
// Fired when queue job completes
$event_bus->on( 'queue.job_completed', function( $data ) {
    // $data = ['job_id' => 0, 'duration' => 1.23, 'result' => []]
});
```

**`queue.job_failed`**
```php
// Fired when queue job fails
$event_bus->on( 'queue.job_failed', function( $data ) {
    // $data = ['job_id' => 0, 'error' => '', 'retry_count' => 3]
});
```

### Emitting Custom Events

```php
<?php
// Get event bus
$event_bus = bbai_service( 'event_bus' );

// Emit synchronous event
$event_bus->emit( 'my_custom_event', array(
    'key' => 'value',
    'data' => array( 1, 2, 3 ),
));

// Emit asynchronous event (uses WordPress Action Scheduler)
$event_bus->emit_async( 'heavy_processing', array(
    'data' => $large_dataset,
));
```

---

## 🪝 WordPress Hooks

### Actions

#### `bbai_before_generation`

**Description**: Runs before alt text generation starts.

```php
add_action( 'bbai_before_generation', function( $image_id ) {
    // Log generation attempt
    error_log( "Generating alt text for image: {$image_id}" );
}, 10, 1 );
```

#### `bbai_after_generation`

**Description**: Runs after alt text generation completes.

```php
add_action( 'bbai_after_generation', function( $image_id, $alt_text, $metadata ) {
    // Post-process the alt text
    // Send notification
    // Update analytics
}, 10, 3 );
```

#### `bbai_quota_exceeded`

**Description**: Runs when user quota is exceeded.

```php
add_action( 'bbai_quota_exceeded', function( $user_id, $quota_info ) {
    // Send email notification
    // Log event
    // Show upgrade message
}, 10, 2 );
```

#### `bbai_queue_processed`

**Description**: Runs after queue batch is processed.

```php
add_action( 'bbai_queue_processed', function( $jobs_processed, $duration ) {
    // Log batch completion
    error_log( "Processed {$jobs_processed} jobs in {$duration}s" );
}, 10, 2 );
```

### Filters

#### `bbai_generated_alt_text`

**Description**: Filter generated alt text before saving.

```php
add_filter( 'bbai_generated_alt_text', function( $alt_text, $image_id ) {
    // Add custom suffix
    $alt_text .= ' - AI Generated';

    // Uppercase
    $alt_text = strtoupper( $alt_text );

    return $alt_text;
}, 10, 2 );
```

#### `bbai_api_request_args`

**Description**: Filter API request arguments.

```php
add_filter( 'bbai_api_request_args', function( $args, $endpoint ) {
    // Add custom headers
    $args['headers']['X-Custom-Header'] = 'value';

    // Modify timeout
    $args['timeout'] = 60;

    return $args;
}, 10, 2 );
```

#### `bbai_queue_job_priority`

**Description**: Filter queue job priority.

```php
add_filter( 'bbai_queue_job_priority', function( $priority, $job_type, $job_data ) {
    // Prioritize featured images
    if ( isset( $job_data['is_featured'] ) && $job_data['is_featured'] ) {
        return 1; // Highest priority
    }

    return $priority;
}, 10, 3 );
```

#### `bbai_max_batch_size`

**Description**: Filter maximum batch size for bulk operations.

```php
add_filter( 'bbai_max_batch_size', function( $size ) {
    // Increase for powerful servers
    return 100; // Default: 50
});
```

#### `bbai_cache_duration`

**Description**: Filter cache duration for API responses.

```php
add_filter( 'bbai_cache_duration', function( $duration, $cache_key ) {
    // Longer cache for usage data
    if ( strpos( $cache_key, 'usage' ) !== false ) {
        return HOUR_IN_SECONDS;
    }

    return $duration;
}, 10, 2 );
```

---

## 📦 Service Container

Access plugin services via the dependency injection container.

### Getting Services

```php
<?php
// Get container
$container = bbai_container();

// Get service
$auth_service = $container->get( 'service.auth' );
$license_service = $container->get( 'service.license' );
$generation_service = $container->get( 'service.generation' );

// Or use helper
$event_bus = bbai_service( 'event_bus' );
```

### Available Services

| Service Name | Class | Description |
|--------------|-------|-------------|
| `event_bus` | `Event_Bus` | Event system |
| `router` | `Router` | HTTP router |
| `service.auth` | `Authentication_Service` | User authentication |
| `service.license` | `License_Service` | License management |
| `service.usage` | `Usage_Service` | Usage tracking |
| `service.generation` | `Generation_Service` | Alt text generation |
| `service.queue` | `Queue_Service` | Queue management |
| `controller.auth` | `Auth_Controller` | Auth HTTP handler |
| `controller.license` | `License_Controller` | License HTTP handler |
| `controller.generation` | `Generation_Controller` | Generation HTTP handler |
| `controller.queue` | `Queue_Controller` | Queue HTTP handler |

### Registering Custom Services

```php
<?php
// In your plugin/theme
add_action( 'myplugin_initialized', function( $container ) {
    // Register your service
    $container->singleton( 'my_service', function( $c ) {
        return new My_Custom_Service(
            $c->get( 'event_bus' )
        );
    });
}, 20 ); // Priority > 10
```

---

## 🌐 REST API Endpoints

### Base URL

```
https://yoursite.com/wp-json/bbai/v1/
```

### Authentication

All endpoints require WordPress authentication (logged-in user with appropriate capabilities).

### Endpoints

#### GET `/usage`

**Description**: Get current usage statistics.

**Permissions**: `read`

**Response**:
```json
{
  "success": true,
  "data": {
    "used": 45,
    "limit": 100,
    "percentage": 45,
    "plan": "pro",
    "period": "monthly"
  }
}
```

**Example**:
```javascript
fetch('/wp-json/bbai/v1/usage', {
    credentials: 'include',
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(res => res.json())
.then(data => console.log(data));
```

#### POST `/generate`

**Description**: Generate alt text for an image.

**Permissions**: `edit_posts`

**Body**:
```json
{
  "image_id": 123
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "alt_text": "A beautiful sunset over the ocean",
    "confidence": 0.95,
    "tokens_used": 50
  }
}
```

#### POST `/queue/add`

**Description**: Add images to generation queue.

**Permissions**: `edit_posts`

**Body**:
```json
{
  "image_ids": [123, 456, 789]
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "queued": 3,
    "job_ids": [1, 2, 3]
  }
}
```

#### GET `/queue/stats`

**Description**: Get queue statistics.

**Permissions**: `read`

**Response**:
```json
{
  "success": true,
  "data": {
    "pending": 10,
    "processing": 2,
    "completed": 500,
    "failed": 5,
    "total": 517
  }
}
```

---

## 🔧 Extension Examples

### Example 1: Custom Alt Text Post-Processing

```php
<?php
/**
 * Plugin Name: BeepBeep AI - Custom Processor
 * Description: Post-process generated alt text
 */

add_filter( 'bbai_generated_alt_text', 'custom_process_alt_text', 10, 2 );

function custom_process_alt_text( $alt_text, $image_id ) {
    // Get image metadata
    $metadata = wp_get_attachment_metadata( $image_id );

    // Add dimensions if available
    if ( isset( $metadata['width'], $metadata['height'] ) ) {
        $alt_text .= sprintf(
            ' (%dx%d)',
            $metadata['width'],
            $metadata['height']
        );
    }

    // Add custom watermark for brand
    if ( is_brand_image( $image_id ) ) {
        $alt_text = '[Brand] ' . $alt_text;
    }

    return $alt_text;
}

function is_brand_image( $image_id ) {
    // Your logic to detect brand images
    $terms = wp_get_post_terms( $image_id, 'image_category' );
    return in_array( 'brand', wp_list_pluck( $terms, 'slug' ) );
}
```

### Example 2: Usage Analytics

```php
<?php
/**
 * Plugin Name: BeepBeep AI - Analytics
 * Description: Track generation analytics
 */

// Listen to generation events
add_action( 'init', 'bbai_analytics_init' );

function bbai_analytics_init() {
    $event_bus = bbai_service( 'event_bus' );

    // Track successful generations
    $event_bus->on( 'generation.completed', function( $data ) {
        bbai_log_analytic( 'generation_success', array(
            'image_id'    => $data['image_id'],
            'duration'    => $data['duration'],
            'tokens_used' => $data['tokens_used'],
            'timestamp'   => current_time( 'mysql' ),
        ));
    });

    // Track failures
    $event_bus->on( 'generation.failed', function( $data ) {
        bbai_log_analytic( 'generation_failed', array(
            'image_id' => $data['image_id'],
            'reason'   => $data['reason'],
            'error'    => $data['error'],
            'timestamp' => current_time( 'mysql' ),
        ));
    });
}

function bbai_log_analytic( $event, $data ) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'bbai_analytics',
        array(
            'event' => $event,
            'data'  => json_encode( $data ),
            'recorded_at' => current_time( 'mysql' ),
        ),
        array( '%s', '%s', '%s' )
    );
}
```

### Example 3: Custom Queue Priority

```php
<?php
/**
 * Plugin Name: BeepBeep AI - Priority Queue
 * Description: Prioritize certain images in queue
 */

add_filter( 'bbai_queue_job_priority', 'custom_queue_priority', 10, 3 );

function custom_queue_priority( $priority, $job_type, $job_data ) {
    // Priority for featured images
    if ( isset( $job_data['image_id'] ) ) {
        $image_id = $job_data['image_id'];

        // Check if featured image
        if ( is_featured_image( $image_id ) ) {
            return 1; // Highest priority
        }

        // Check if recent upload (last 24 hours)
        $post = get_post( $image_id );
        if ( $post && strtotime( $post->post_date ) > time() - DAY_IN_SECONDS ) {
            return 5; // High priority
        }
    }

    return $priority; // Default
}

function is_featured_image( $image_id ) {
    global $wpdb;

    $result = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta}
         WHERE meta_key = '_thumbnail_id'
         AND meta_value = %d",
        $image_id
    ));

    return $result > 0;
}
```

### Example 4: Notification System

```php
<?php
/**
 * Plugin Name: BeepBeep AI - Notifications
 * Description: Send notifications on events
 */

add_action( 'init', 'bbai_notifications_init' );

function bbai_notifications_init() {
    $event_bus = bbai_service( 'event_bus' );

    // Notify on quota exceeded
    $event_bus->on( 'generation.failed', function( $data ) {
        if ( $data['reason'] === 'quota' ) {
            bbai_send_quota_notification( $data );
        }
    });

    // Notify on bulk completion
    $event_bus->on( 'queue.job_completed', function( $data ) {
        if ( $data['type'] === 'bulk_generation' ) {
            bbai_send_bulk_completion_notification( $data );
        }
    });
}

function bbai_send_quota_notification( $data ) {
    $user = wp_get_current_user();

    wp_mail(
        $user->user_email,
        'BeepBeep AI - Quota Exceeded',
        sprintf(
            'Your alt text generation quota has been exceeded.\n\n' .
            'Upgrade to Pro for unlimited generations:\n' .
            '%s',
            admin_url( 'admin.php?page=bbai-settings' )
        )
    );
}

function bbai_send_bulk_completion_notification( $data ) {
    $user = wp_get_current_user();

    wp_mail(
        $user->user_email,
        'BeepBeep AI - Bulk Generation Complete',
        sprintf(
            'Your bulk generation is complete!\n\n' .
            'Processed: %d images\n' .
            'Duration: %.2f seconds\n\n' .
            'View results: %s',
            $data['images_processed'],
            $data['duration'],
            admin_url( 'admin.php?page=bbai-dashboard' )
        )
    );
}
```

### Example 5: Integration with Other Plugins

```php
<?php
/**
 * Plugin Name: BeepBeep AI - WooCommerce Integration
 * Description: Auto-generate alt text for product images
 */

// Auto-generate for new products
add_action( 'woocommerce_new_product', 'bbai_woo_new_product' );

function bbai_woo_new_product( $product_id ) {
    // Get product featured image
    $image_id = get_post_thumbnail_id( $product_id );

    if ( ! $image_id ) {
        return;
    }

    // Queue for generation
    $queue_service = bbai_service( 'service.queue' );
    $queue_service->add_job( 'generate_alt_text', array(
        'image_id' => $image_id,
        'priority' => 5, // High priority for product images
        'context'  => 'woocommerce_product',
    ));
}

// Auto-generate for product galleries
add_action( 'woocommerce_product_set_gallery_images', 'bbai_woo_gallery_images', 10, 2 );

function bbai_woo_gallery_images( $product_id, $image_ids ) {
    $queue_service = bbai_service( 'service.queue' );

    foreach ( $image_ids as $image_id ) {
        $queue_service->add_job( 'generate_alt_text', array(
            'image_id' => $image_id,
            'priority' => 7, // Normal priority for gallery
            'context'  => 'woocommerce_gallery',
        ));
    }
}
```

---

## 👥 Contributing

### Development Setup

```bash
# Clone repository
git clone https://github.com/user/beepbeep-ai-alt-text-generator.git
cd beepbeep-ai-alt-text-generator

# Install dependencies
composer install
npm install

# Run tests
vendor/bin/phpunit

# Build assets
npm run build
```

### Code Standards

Follow WordPress Coding Standards:

```bash
# Check PHP code
vendor/bin/phpcs

# Fix PHP code
vendor/bin/phpcbf

# Check JavaScript
npm run lint

# Fix JavaScript
npm run lint:fix
```

### Pull Request Process

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for new functionality
4. Ensure all tests pass
5. Follow code standards
6. Commit changes (`git commit -m 'Add amazing feature'`)
7. Push to branch (`git push origin feature/amazing-feature`)
8. Open Pull Request

### Testing Guidelines

```php
<?php
// Example test for custom functionality
class My_Feature_Test extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Setup
    }

    public function test_custom_alt_text_processing() {
        $alt_text = 'Original text';
        $image_id = 123;

        $result = apply_filters( 'bbai_generated_alt_text', $alt_text, $image_id );

        $this->assertStringContainsString( 'Original text', $result );
    }
}
```

---

## 📚 Additional Resources

- **Plugin Framework Guide**: `PLUGIN_FRAMEWORK_ARCHITECTURE.md`
- **Quick Start**: `PLUGIN_FRAMEWORK_QUICKSTART.md`
- **Security Guide**: `SECURITY.md`
- **Performance Guide**: `PERFORMANCE.md`
- **Release Workflow**: `RELEASE_WORKFLOW.md`

---

## 🆘 Support

- **Issues**: https://github.com/user/repo/issues
- **Discussions**: https://github.com/user/repo/discussions
- **WordPress Forums**: https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/

---

**Last Updated**: 2025-12-19
**API Version**: 1.0.0
**Status**: ✅ Stable
