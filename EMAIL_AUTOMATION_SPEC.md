# Email Automation System - Implementation Specification

**Date:** 2025-11-03
**Status:** 🚧 IN PROGRESS
**Version:** 1.0.0

---

## Executive Summary

This document specifies the complete implementation of marketing and retention email automation for the AltText AI WordPress plugin using Resend.com. The system captures user emails with GDPR-compliant opt-in, tracks usage events, and triggers automated transactional + drip campaigns.

---

## System Architecture

```
┌─────────────────────┐
│  WordPress Plugin   │
│  ┌──────────────┐   │
│  │ Auth Modal   │───┼──> Email Capture (opt-in checkbox)
│  │ + Opt-in     │   │
│  └──────────────┘   │
│                     │
│  ┌──────────────┐   │
│  │ Usage Event  │───┼──> Track: signup, usage, upgrade, inactivity
│  │ Tracker      │   │
│  └──────────────┘   │
└─────────┬───────────┘
          │
          │ Webhook / API
          ▼
┌─────────────────────┐
│  Backend Service    │
│  (Node/FastAPI)     │
│  ┌──────────────┐   │
│  │ Email Event  │   │
│  │ Handler      │───┼──> Evaluate triggers
│  └──────────────┘   │
│         │           │
│         ▼           │
│  ┌──────────────┐   │
│  │ Resend API   │   │
│  │ Integration  │───┼──> Send emails
│  └──────────────┘   │
└─────────────────────┘
```

---

## Phase 1: Email Capture (WordPress Plugin)

### 1.1 Add Opt-in Checkbox to Signup Modal

**File:** `assets/auth-modal.js`

**Implementation:**
```javascript
// Add after line 194 (register form)
<div class="alttext-form-group alttext-checkbox-group">
    <label class="alttext-checkbox-label">
        <input type="checkbox" id="register-marketing-optin" name="marketingOptIn" value="1">
        <span>Send me tips, updates, and exclusive offers (optional)</span>
    </label>
    <small class="alttext-privacy-notice">
        We respect your privacy. Unsubscribe anytime.
        <a href="https://alttextai.com/privacy" target="_blank">Privacy Policy</a>
    </small>
</div>
```

**CSS:** `assets/auth-modal.css`
```css
.alttext-checkbox-group {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(var(--alttextai-info-rgb), 0.05);
    border-radius: 8px;
}

.alttext-checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    gap: 0.75rem;
}

.alttext-checkbox-label input[type="checkbox"] {
    margin-top: 0.25rem;
    cursor: pointer;
}

.alttext-checkbox-label span {
    font-size: 14px;
    line-height: 1.5;
}

.alttext-privacy-notice {
    display: block;
    margin-top: 0.5rem;
    margin-left: 1.75rem;
    font-size: 12px;
    color: var(--alttextai-gray-600);
}

.alttext-privacy-notice a {
    color: var(--alttextai-primary);
    text-decoration: underline;
}
```

### 1.2 Capture Email Data on Registration

**File:** `assets/auth-modal.js` (modify `handleRegister` method)

```javascript
async handleRegister(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const email = formData.get('email');
    const password = formData.get('password');
    const confirmPassword = formData.get('confirmPassword');
    const marketingOptIn = formData.get('marketingOptIn') === '1'; // NEW

    // ... existing validation ...

    try {
        const response = await fetch(`${this.apiUrl}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email,
                password,
                marketingOptIn,  // NEW
                source: 'wordpress-plugin'
            })
        });

        const data = await response.json();

        if (data.success) {
            // Store token
            this.setToken(data.token);

            // Fire WordPress action hook for email capture
            if (marketingOptIn && window.wp?.hooks) {
                wp.hooks.doAction('alttextai_user_opted_in', {
                    email: data.user.email,
                    name: data.user.name || email.split('@')[0],
                    userId: data.user.id,
                    plan: data.user.plan || 'free'
                });
            }

            // ... existing success handling ...
        }
    } catch (error) {
        // ... error handling ...
    }
}
```

### 1.3 WordPress Database Schema

**Table:** `wp_alttextai_email_subscribers`

```sql
CREATE TABLE IF NOT EXISTS wp_alttextai_email_subscribers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    install_id VARCHAR(100) NOT NULL,
    wp_user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NULL,
    plan VARCHAR(50) DEFAULT 'free',
    opt_in_status ENUM('opted_in', 'opted_out', 'bounced') DEFAULT 'opted_in',
    opt_in_date DATETIME NOT NULL,
    opt_out_date DATETIME NULL,
    resend_contact_id VARCHAR(100) NULL,
    resend_audience_id VARCHAR(100) NULL,
    last_sync_at DATETIME NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    UNIQUE KEY email_install (email, install_id),
    KEY install_id (install_id),
    KEY wp_user_id (wp_user_id),
    KEY opt_in_status (opt_in_status),
    KEY resend_contact_id (resend_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Table:** `wp_alttextai_email_events`

```sql
CREATE TABLE IF NOT EXISTS wp_alttextai_email_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    install_id VARCHAR(100) NOT NULL,
    wp_user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    event_type VARCHAR(50) NOT NULL, -- signup, usage_70, usage_100, upgrade, inactive_30d
    event_data JSON NULL,
    triggered_at DATETIME NOT NULL,
    email_sent_at DATETIME NULL,
    resend_email_id VARCHAR(100) NULL,
    status ENUM('pending', 'sent', 'failed', 'skipped') DEFAULT 'pending',
    error_message TEXT NULL,

    KEY install_user (install_id, wp_user_id),
    KEY email (email),
    KEY event_type (event_type),
    KEY status (status),
    KEY triggered_at (triggered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Phase 2: Backend Event Tracking

### 2.1 WordPress Email Subscriber Manager

**File:** `includes/class-email-subscriber-manager.php`

```php
<?php
/**
 * Email Subscriber Manager
 * Handles email capture, opt-in/out, and Resend sync
 */

if (!defined('ABSPATH')) { exit; }

class AltText_AI_Email_Subscriber_Manager {
    const TABLE_SUBSCRIBERS = 'alttextai_email_subscribers';
    const TABLE_EVENTS = 'alttextai_email_events';

    private $api_client;

    public function __construct($api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Subscribers table
        $sql_subscribers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_SUBSCRIBERS . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            install_id VARCHAR(100) NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NULL,
            plan VARCHAR(50) DEFAULT 'free',
            opt_in_status ENUM('opted_in', 'opted_out', 'bounced') DEFAULT 'opted_in',
            opt_in_date DATETIME NOT NULL,
            opt_out_date DATETIME NULL,
            resend_contact_id VARCHAR(100) NULL,
            resend_audience_id VARCHAR(100) NULL,
            last_sync_at DATETIME NULL,
            metadata JSON NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY email_install (email, install_id),
            KEY install_id (install_id),
            KEY wp_user_id (wp_user_id),
            KEY opt_in_status (opt_in_status),
            KEY resend_contact_id (resend_contact_id)
        ) $charset_collate;";

        // Email events table
        $sql_events = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_EVENTS . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            install_id VARCHAR(100) NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data JSON NULL,
            triggered_at DATETIME NOT NULL,
            email_sent_at DATETIME NULL,
            resend_email_id VARCHAR(100) NULL,
            status ENUM('pending', 'sent', 'failed', 'skipped') DEFAULT 'pending',
            error_message TEXT NULL,
            KEY install_user (install_id, wp_user_id),
            KEY email (email),
            KEY event_type (event_type),
            KEY status (status),
            KEY triggered_at (triggered_at)
        ) $charset_collate;";

        dbDelta($sql_subscribers);
        dbDelta($sql_events);
    }

    /**
     * Add or update subscriber
     */
    public function subscribe($email, $wp_user_id, $name = null, $plan = 'free', $metadata = []) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $install_id = AltText_AI_Usage_Event_Tracker::get_install_id();

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE email = %s AND install_id = %s",
            $email,
            $install_id
        ), ARRAY_A);

        $now = current_time('mysql');

        if ($existing) {
            // Update existing subscriber (re-opt-in)
            $wpdb->update(
                $table,
                [
                    'opt_in_status' => 'opted_in',
                    'opt_in_date' => $now,
                    'opt_out_date' => null,
                    'name' => $name ?: $existing['name'],
                    'plan' => $plan,
                    'metadata' => json_encode($metadata),
                    'updated_at' => $now
                ],
                ['id' => $existing['id']],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            return $existing['id'];
        } else {
            // Insert new subscriber
            $wpdb->insert(
                $table,
                [
                    'install_id' => $install_id,
                    'wp_user_id' => $wp_user_id,
                    'email' => $email,
                    'name' => $name,
                    'plan' => $plan,
                    'opt_in_status' => 'opted_in',
                    'opt_in_date' => $now,
                    'metadata' => json_encode($metadata),
                    'created_at' => $now,
                    'updated_at' => $now
                ],
                ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            $subscriber_id = $wpdb->insert_id;

            // Trigger sync to Resend
            $this->sync_to_resend($subscriber_id);

            // Fire action hook
            do_action('alttextai_subscriber_added', $subscriber_id, $email, $wp_user_id);

            return $subscriber_id;
        }
    }

    /**
     * Unsubscribe user
     */
    public function unsubscribe($email, $reason = null) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $install_id = AltText_AI_Usage_Event_Tracker::get_install_id();

        $metadata = [];
        if ($reason) {
            $metadata['unsubscribe_reason'] = $reason;
        }

        $wpdb->update(
            $table,
            [
                'opt_in_status' => 'opted_out',
                'opt_out_date' => current_time('mysql'),
                'metadata' => json_encode($metadata),
                'updated_at' => current_time('mysql')
            ],
            [
                'email' => $email,
                'install_id' => $install_id
            ],
            ['%s', '%s', '%s', '%s'],
            ['%s', '%s']
        );

        do_action('alttextai_subscriber_unsubscribed', $email);
    }

    /**
     * Sync subscriber to Resend
     */
    private function sync_to_resend($subscriber_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;

        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $subscriber_id
        ), ARRAY_A);

        if (!$subscriber) {
            return false;
        }

        try {
            // Send to backend for Resend sync
            $response = $this->api_client->post('/email/subscribe', [
                'email' => $subscriber['email'],
                'name' => $subscriber['name'],
                'plan' => $subscriber['plan'],
                'install_id' => $subscriber['install_id'],
                'wp_user_id' => $subscriber['wp_user_id'],
                'opt_in_date' => $subscriber['opt_in_date'],
                'metadata' => json_decode($subscriber['metadata'], true)
            ]);

            if ($response['success']) {
                // Update with Resend IDs
                $wpdb->update(
                    $table,
                    [
                        'resend_contact_id' => $response['contact_id'] ?? null,
                        'resend_audience_id' => $response['audience_id'] ?? null,
                        'last_sync_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $subscriber_id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
            }

            return $response['success'];
        } catch (Exception $e) {
            error_log('AltText AI: Failed to sync subscriber to Resend - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log email event
     */
    public function log_event($email, $wp_user_id, $event_type, $event_data = []) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_EVENTS;
        $install_id = AltText_AI_Usage_Event_Tracker::get_install_id();

        // Check if subscriber is opted in
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_SUBSCRIBERS . "
             WHERE email = %s AND install_id = %s AND opt_in_status = 'opted_in'",
            $email,
            $install_id
        ), ARRAY_A);

        if (!$subscriber) {
            return false; // Don't log events for non-subscribers
        }

        // Check if event already logged recently (prevent duplicates)
        $recent_event = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE email = %s AND event_type = %s AND triggered_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT 1",
            $email,
            $event_type
        ));

        if ($recent_event) {
            return false; // Skip duplicate event
        }

        $wpdb->insert(
            $table,
            [
                'install_id' => $install_id,
                'wp_user_id' => $wp_user_id,
                'email' => $email,
                'event_type' => $event_type,
                'event_data' => json_encode($event_data),
                'triggered_at' => current_time('mysql'),
                'status' => 'pending'
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $event_id = $wpdb->insert_id;

        // Trigger email send via backend
        $this->trigger_email($event_id);

        return $event_id;
    }

    /**
     * Trigger email send via backend
     */
    private function trigger_email($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_EVENTS;

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $event_id
        ), ARRAY_A);

        if (!$event) {
            return false;
        }

        try {
            // Send to backend for email trigger
            $response = $this->api_client->post('/email/trigger', [
                'event_id' => $event_id,
                'email' => $event['email'],
                'event_type' => $event['event_type'],
                'event_data' => json_decode($event['event_data'], true),
                'install_id' => $event['install_id']
            ]);

            if ($response['success']) {
                $wpdb->update(
                    $table,
                    [
                        'status' => 'sent',
                        'email_sent_at' => current_time('mysql'),
                        'resend_email_id' => $response['email_id'] ?? null
                    ],
                    ['id' => $event_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                $wpdb->update(
                    $table,
                    [
                        'status' => 'failed',
                        'error_message' => $response['error'] ?? 'Unknown error'
                    ],
                    ['id' => $event_id],
                    ['%s', '%s'],
                    ['%d']
                );
            }

            return $response['success'];
        } catch (Exception $e) {
            error_log('AltText AI: Failed to trigger email - ' . $e->getMessage());

            $wpdb->update(
                $table,
                [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ],
                ['id' => $event_id],
                ['%s', '%s'],
                ['%d']
            );

            return false;
        }
    }
}
```

### 2.2 WordPress Usage Event Integration

**File:** `admin/class-opptiai-alt-core.php` (add to constructor)

```php
// Hook into usage tracking events
add_action('alttextai_after_log_event', [$this, 'check_usage_thresholds'], 10, 3);
add_action('alttextai_subscriber_added', [$this, 'send_welcome_email'], 10, 3);

// Initialize email subscriber manager
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-email-subscriber-manager.php';
$this->email_subscriber_manager = new AltText_AI_Email_Subscriber_Manager($this->api_client);
```

**Add method to check usage thresholds:**

```php
/**
 * Check usage thresholds and trigger email events
 */
public function check_usage_thresholds($event_id, $args, $install_id) {
    $wp_user_id = $args['wp_user_id'];

    // Get user's total usage
    $usage_stats = $this->usage_event_tracker->get_dashboard_stats($wp_user_id);
    $total_tokens = $usage_stats['totals']['total_tokens'] ?? 0;

    // Get user plan
    $user_data = $this->api_client->get_user_data();
    $plan = $user_data['plan'] ?? 'free';

    // Free plan limits: 20 tokens (configurable)
    $plan_limits = [
        'free' => 20,
        'pro' => 10000,
        'agency' => 100000
    ];

    $limit = $plan_limits[$plan] ?? 20;
    $usage_pct = ($limit > 0) ? ($total_tokens / $limit) * 100 : 0;

    // Get user email
    $wp_user = get_user_by('id', $wp_user_id);
    if (!$wp_user) {
        return;
    }

    // Trigger email events based on thresholds
    if ($plan === 'free') {
        if ($usage_pct >= 100) {
            $this->email_subscriber_manager->log_event(
                $wp_user->user_email,
                $wp_user_id,
                'usage_100',
                [
                    'tokens_used' => $total_tokens,
                    'limit' => $limit,
                    'plan' => $plan
                ]
            );
        } elseif ($usage_pct >= 70 && $usage_pct < 100) {
            $this->email_subscriber_manager->log_event(
                $wp_user->user_email,
                $wp_user_id,
                'usage_70',
                [
                    'tokens_used' => $total_tokens,
                    'limit' => $limit,
                    'plan' => $plan,
                    'percentage' => round($usage_pct, 1)
                ]
            );
        }
    }
}

/**
 * Send welcome email on subscriber opt-in
 */
public function send_welcome_email($subscriber_id, $email, $wp_user_id) {
    $this->email_subscriber_manager->log_event(
        $email,
        $wp_user_id,
        'welcome',
        ['subscriber_id' => $subscriber_id]
    );
}
```

---

## Phase 3: Backend Service Implementation

### 3.1 Email Event Handler (Node.js)

**File:** `alttext-ai-backend-clone/services/emailEventHandler.js`

```javascript
const Resend = require('resend');

const resend = new Resend(process.env.RESEND_API_KEY);

const EMAIL_TEMPLATES = {
    welcome: 'template_welcome',
    usage_70: 'template_usage_alert_70',
    usage_100: 'template_out_of_tokens',
    upgrade: 'template_upgrade_success',
    inactive_30d: 'template_reactivation'
};

class EmailEventHandler {
    /**
     * Subscribe user to Resend audience
     */
    async subscribe(data) {
        const { email, name, plan, install_id, wp_user_id, metadata } = data;

        try {
            // Add contact to Resend audience
            const contact = await resend.contacts.create({
                email,
                firstName: name || email.split('@')[0],
                audienceId: process.env.RESEND_AUDIENCE_ID,
                unsubscribed: false
            });

            // Store contact ID for future reference
            return {
                success: true,
                contact_id: contact.id,
                audience_id: process.env.RESEND_AUDIENCE_ID
            };
        } catch (error) {
            console.error('[Email] Failed to subscribe:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Trigger email based on event type
     */
    async triggerEmail(data) {
        const { email, event_type, event_data, install_id } = data;

        const templateId = EMAIL_TEMPLATES[event_type];
        if (!templateId) {
            return {
                success: false,
                error: `Unknown event type: ${event_type}`
            };
        }

        try {
            const emailData = this.buildEmailData(event_type, event_data);

            const result = await resend.emails.send({
                from: 'AltText AI <noreply@alttextai.com>',
                to: email,
                subject: emailData.subject,
                react: templateId,
                ...emailData.templateData
            });

            return {
                success: true,
                email_id: result.id
            };
        } catch (error) {
            console.error('[Email] Failed to send:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Build email data based on event type
     */
    buildEmailData(eventType, eventData) {
        switch (eventType) {
            case 'welcome':
                return {
                    subject: 'Welcome to AltText AI! 🎉',
                    templateData: {
                        userName: eventData.name || 'there',
                        ctaUrl: 'https://alttextai.com/docs/getting-started',
                        ctaText: 'Get Started'
                    }
                };

            case 'usage_70':
                return {
                    subject: `You've used ${eventData.percentage}% of your free tokens`,
                    templateData: {
                        tokensUsed: eventData.tokens_used,
                        tokensLimit: eventData.limit,
                        percentage: eventData.percentage,
                        upgradeUrl: 'https://alttextai.com/pricing'
                    }
                };

            case 'usage_100':
                return {
                    subject: 'You\'re out of free tokens - Upgrade to keep generating!',
                    templateData: {
                        tokensUsed: eventData.tokens_used,
                        upgradeUrl: 'https://alttextai.com/pricing',
                        plan: eventData.plan
                    }
                };

            case 'upgrade':
                return {
                    subject: 'Welcome to AltText AI Pro! 🚀',
                    templateData: {
                        plan: eventData.plan,
                        features: this.getPlanFeatures(eventData.plan)
                    }
                };

            case 'inactive_30d':
                return {
                    subject: 'We miss you! Here\'s what you\'re missing...',
                    templateData: {
                        daysSinceLastActivity: eventData.days_inactive,
                        reactivateUrl: eventData.dashboard_url
                    }
                };

            default:
                return {
                    subject: 'AltText AI Notification',
                    templateData: {}
                };
        }
    }

    /**
     * Get plan features for email
     */
    getPlanFeatures(plan) {
        const features = {
            pro: [
                '10,000 tokens per month',
                'Priority support',
                'Advanced SEO features',
                'Bulk processing'
            ],
            agency: [
                '100,000 tokens per month',
                'White-label options',
                'Dedicated account manager',
                'API access'
            ]
        };

        return features[plan] || [];
    }

    /**
     * Check for inactive users (cron job)
     */
    async checkInactiveUsers() {
        // Query database for users inactive for 30+ days
        const inactiveUsers = await db.query(`
            SELECT DISTINCT u.email, u.wp_user_id, u.install_id
            FROM wp_alttextai_email_subscribers s
            JOIN wp_alttextai_usage_events u ON s.wp_user_id = u.wp_user_id
            WHERE s.opt_in_status = 'opted_in'
            AND u.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM wp_alttextai_usage_events e
                WHERE e.wp_user_id = u.wp_user_id
                AND e.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            )
        `);

        for (const user of inactiveUsers) {
            await this.triggerEmail({
                email: user.email,
                event_type: 'inactive_30d',
                event_data: {
                    days_inactive: 30,
                    dashboard_url: `${process.env.PLUGIN_URL}/wp-admin/admin.php?page=ai-alt-text`
                },
                install_id: user.install_id
            });
        }
    }
}

module.exports = new EmailEventHandler();
```

### 3.2 API Endpoints

**File:** `alttext-ai-backend-clone/routes/email.js`

```javascript
const express = require('express');
const router = express.Router();
const emailEventHandler = require('../services/emailEventHandler');
const { authenticate } = require('../middleware/auth');

/**
 * Subscribe user to email list
 * POST /api/email/subscribe
 */
router.post('/subscribe', authenticate, async (req, res) => {
    try {
        const result = await emailEventHandler.subscribe(req.body);
        res.json(result);
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Trigger email event
 * POST /api/email/trigger
 */
router.post('/trigger', authenticate, async (req, res) => {
    try {
        const result = await emailEventHandler.triggerEmail(req.body);
        res.json(result);
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Unsubscribe user
 * POST /api/email/unsubscribe
 */
router.post('/unsubscribe', async (req, res) => {
    try {
        const { email, token } = req.body;

        // Verify unsubscribe token
        const valid = verifyUnsubscribeToken(token, email);
        if (!valid) {
            return res.status(400).json({
                success: false,
                error: 'Invalid unsubscribe token'
            });
        }

        // Remove from Resend audience
        await resend.contacts.remove({
            email,
            audienceId: process.env.RESEND_AUDIENCE_ID
        });

        res.json({ success: true });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

module.exports = router;
```

### 3.3 Cron Job for Inactive Users

**File:** `alttext-ai-backend-clone/jobs/checkInactiveUsers.js`

```javascript
const cron = require('node-cron');
const emailEventHandler = require('../services/emailEventHandler');

// Run daily at 10:00 AM
cron.schedule('0 10 * * *', async () => {
    console.log('[Cron] Checking for inactive users...');

    try {
        await emailEventHandler.checkInactiveUsers();
        console.log('[Cron] Inactive user check completed');
    } catch (error) {
        console.error('[Cron] Failed to check inactive users:', error);
    }
});
```

---

## Phase 4: Resend Email Templates

### 4.1 Template List

1. **Welcome Email** (`template_welcome`)
   - Subject: "Welcome to AltText AI! 🎉"
   - Content: Getting started guide, tips, link to documentation

2. **Usage Alert 70%** (`template_usage_alert_70`)
   - Subject: "You've used 70% of your free tokens"
   - Content: Usage stats, upgrade CTA, pricing comparison

3. **Out of Tokens** (`template_out_of_tokens`)
   - Subject: "You're out of free tokens - Upgrade to keep generating!"
   - Content: Upgrade benefits, pricing, testimonials

4. **Upgrade Success** (`template_upgrade_success`)
   - Subject: "Welcome to AltText AI Pro! 🚀"
   - Content: Thank you, feature overview, next steps

5. **Reactivation** (`template_reactivation`)
   - Subject: "We miss you! Here's what you're missing..."
   - Content: New features, success stories, special offer

### 4.2 Template Structure (React Email)

**File:** `emails/Welcome.tsx`

```tsx
import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Preview,
  Section,
  Text,
} from '@react-email/components';

interface WelcomeEmailProps {
  userName?: string;
  ctaUrl?: string;
}

export const WelcomeEmail = ({
  userName = 'there',
  ctaUrl = 'https://alttextai.com',
}: WelcomeEmailProps) => (
  <Html>
    <Head />
    <Preview>Welcome to AltText AI - Start generating alt text today!</Preview>
    <Body style={main}>
      <Container style={container}>
        <Heading style={h1}>Welcome to AltText AI! 🎉</Heading>

        <Text style={text}>Hi {userName},</Text>

        <Text style={text}>
          Thanks for signing up! You're now ready to generate AI-powered alt text
          for all your images.
        </Text>

        <Section style={section}>
          <Text style={text}>
            <strong>Here's how to get started:</strong>
          </Text>
          <Text style={text}>
            1. Upload an image to your WordPress Media Library<br />
            2. Click "Generate Alt Text (AI)" button<br />
            3. Watch as AI creates SEO-optimized alt text instantly!
          </Text>
        </Section>

        <Button style={button} href={ctaUrl}>
          Get Started
        </Button>

        <Text style={text}>
          Need help? Reply to this email or check out our{' '}
          <a href="https://alttextai.com/docs">documentation</a>.
        </Text>

        <Text style={footer}>
          AltText AI - Making the web more accessible, one image at a time.
        </Text>
      </Container>
    </Body>
  </Html>
);

const main = {
  backgroundColor: '#f6f9fc',
  fontFamily: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Ubuntu,sans-serif',
};

const container = {
  backgroundColor: '#ffffff',
  margin: '0 auto',
  padding: '20px 0 48px',
  marginBottom: '64px',
};

const h1 = {
  color: '#333',
  fontSize: '24px',
  fontWeight: 'bold',
  margin: '40px 0',
  padding: '0',
  textAlign: 'center' as const,
};

const text = {
  color: '#333',
  fontSize: '16px',
  lineHeight: '26px',
};

const section = {
  padding: '24px',
  border: 'solid 1px #dedede',
  borderRadius: '5px',
  margin: '24px 0',
};

const button = {
  backgroundColor: '#14b8a6',
  borderRadius: '5px',
  color: '#fff',
  fontSize: '16px',
  fontWeight: 'bold',
  textDecoration: 'none',
  textAlign: 'center' as const,
  display: 'block',
  width: '200px',
  padding: '12px',
  margin: '24px auto',
};

const footer = {
  color: '#8898aa',
  fontSize: '12px',
  lineHeight: '16px',
  marginTop: '48px',
  textAlign: 'center' as const,
};
```

---

## Phase 5: Testing & Deployment

### 5.1 Testing Checklist

- [ ] Email capture form displays correctly
- [ ] Opt-in checkbox works (checked/unchecked states)
- [ ] Data saves to WordPress database
- [ ] Resend API integration successful
- [ ] Welcome email triggers on signup
- [ ] Usage threshold emails trigger correctly
- [ ] Unsubscribe link works
- [ ] Email templates render correctly across clients
- [ ] GDPR compliance verified
- [ ] Spam score acceptable (<5/10)

### 5.2 Environment Variables

```bash
# Backend (.env)
RESEND_API_KEY=re_123456789
RESEND_AUDIENCE_ID=aud_123456789
PLUGIN_URL=https://your-wordpress-site.com
```

### 5.3 Deployment Steps

1. **WordPress Plugin:**
   - Add email subscriber manager class
   - Update auth modal with opt-in checkbox
   - Run database migrations
   - Test locally first

2. **Backend Service:**
   - Add Resend dependency: `npm install resend`
   - Add email event handler
   - Deploy API endpoints
   - Set up cron job
   - Test with Resend sandbox mode

3. **Resend Configuration:**
   - Create account at resend.com
   - Get API key
   - Create audience
   - Build email templates
   - Verify domain (for production)

---

## Compliance & Best Practices

### GDPR Compliance

✅ **Opt-in Required:** Checkbox unchecked by default
✅ **Clear Consent:** User explicitly checks box
✅ **Transparent:** Privacy policy linked
✅ **Unsubscribe:** One-click unsubscribe in every email
✅ **Data Minimization:** Only collect necessary data
✅ **Right to Erasure:** Delete user data on request

### Email Best Practices

- Use double opt-in for critical campaigns
- Include clear unsubscribe link
- Respect unsubscribe requests immediately
- Monitor bounce rates and spam complaints
- Use transactional emails for critical notifications
- Test emails across major clients (Gmail, Outlook, Apple Mail)
- Keep subject lines under 50 characters
- Maintain text-to-image ratio (60:40 or better)

---

## Metrics & Monitoring

### Key Metrics

- **Opt-in Rate:** % of signups who opt in to emails
- **Open Rate:** Target >20%
- **Click-through Rate:** Target >2%
- **Unsubscribe Rate:** Keep <0.5%
- **Bounce Rate:** Keep <2%
- **Conversion Rate:** % who upgrade after email

### Monitoring Dashboard

Track in backend admin panel:
- Total subscribers
- Email events triggered
- Emails sent vs. failed
- Resend API usage
- Conversion funnel

---

## Future Enhancements

- [ ] A/B testing for email templates
- [ ] Drip campaigns (multi-email sequences)
- [ ] Personalized recommendations
- [ ] Dynamic content based on usage patterns
- [ ] Integration with analytics (Google Analytics, Mixpanel)
- [ ] SMS notifications (via Twilio)
- [ ] In-app notifications
- [ ] Slack/Discord integration

---

**Document Version:** 1.0.0
**Last Updated:** 2025-11-03
**Status:** Implementation Ready
**Next Step:** Begin Phase 1 - Email Capture Implementation
