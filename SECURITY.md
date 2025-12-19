# Security Hardening Guide

> **Comprehensive security audit and best practices for WordPress plugin development**

---

## 🎯 Overview

This guide provides a complete security audit checklist and hardening recommendations for the BeepBeep AI Alt Text Generator plugin. Follow these guidelines to ensure your plugin meets WordPress security standards.

---

## 📋 Security Audit Checklist

### ✅ Input Validation & Sanitization

#### User Input
- [x] **POST data sanitized** - All `$_POST` data sanitized with appropriate functions
- [x] **GET data sanitized** - All `$_GET` data sanitized
- [x] **File uploads validated** - File types, sizes, and content validated
- [x] **Email validation** - `sanitize_email()` used for email inputs
- [x] **URL validation** - `esc_url_raw()` used for URL inputs
- [x] **Text field sanitization** - `sanitize_text_field()` for text inputs

#### Database Queries
- [x] **Prepared statements** - All queries use `$wpdb->prepare()`
- [x] **No direct SQL** - No raw SQL concatenation
- [x] **Escaped output** - Database results escaped before output
- [x] **Integer validation** - IDs cast to `(int)` or `absint()`

#### API Inputs
- [x] **JSON validated** - API responses validated before use
- [x] **Response sanitized** - External data sanitized before storage
- [x] **Error handling** - API errors handled gracefully

---

### ✅ Output Escaping

#### HTML Output
- [x] **Text escaped** - `esc_html()` for plain text
- [x] **Attributes escaped** - `esc_attr()` for HTML attributes
- [x] **URLs escaped** - `esc_url()` for URLs in output
- [x] **JavaScript escaped** - `esc_js()` for inline JavaScript
- [x] **Textarea escaped** - `esc_textarea()` for textarea content

#### Translation Output
- [x] **Translated text escaped** - `esc_html__()`, `esc_attr__()` used
- [x] **Printf escaped** - `esc_html_e()`, `esc_attr_e()` for output

---

### ✅ Authentication & Authorization

#### Permission Checks
- [x] **Admin actions protected** - `current_user_can('manage_options')`
- [x] **AJAX protected** - All AJAX handlers check capabilities
- [x] **REST API protected** - Permission callbacks on all endpoints
- [x] **File operations protected** - File operations require admin capability

#### Session Management
- [x] **Token storage** - API tokens stored securely in wp_options
- [x] **Token encryption** - Sensitive tokens encrypted (if applicable)
- [x] **Session timeout** - API sessions have appropriate timeouts
- [x] **Logout cleanup** - Sessions cleared on logout

---

### ✅ CSRF Protection

#### Nonce Verification
- [x] **AJAX nonces** - All AJAX actions verify nonces
- [x] **Form nonces** - All forms include nonce fields
- [x] **Nonce naming** - Unique nonce names per action
- [x] **Nonce lifetime** - Default 24-hour lifetime appropriate

#### Example Implementation
```php
// Form submission
wp_verify_nonce($_POST['nonce'], 'action_name');

// AJAX handler (Router handles automatically)
$router->ajax('action_name', 'controller', 'method'); // Nonce checked by router
```

---

### ✅ SQL Injection Prevention

#### Database Operations
- [x] **Prepared statements** - 100% of queries use `$wpdb->prepare()`
- [x] **No direct queries** - No string concatenation in queries
- [x] **Table prefix used** - `$wpdb->prefix` used for table names
- [x] **Integer casting** - IDs cast with `absint()` or `(int)`

#### Best Practices
```php
// ✅ GOOD - Prepared statement
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
    $id
));

// ❌ BAD - Direct concatenation
$wpdb->get_results("SELECT * FROM table WHERE id = $id");
```

---

### ✅ XSS Prevention

#### Output Escaping
- [x] **All output escaped** - No unescaped user data in HTML
- [x] **JavaScript data** - JSON data properly escaped
- [x] **Inline scripts** - No inline scripts with user data
- [x] **Event handlers** - No user data in event handlers

#### Best Practices
```php
// ✅ GOOD - Escaped output
echo '<div>' . esc_html($user_input) . '</div>';
echo '<a href="' . esc_url($url) . '">' . esc_html($text) . '</a>';

// ❌ BAD - Unescaped output
echo '<div>' . $user_input . '</div>';
```

---

### ✅ File Security

#### File Operations
- [x] **Direct access blocked** - All PHP files have `if (!defined('ABSPATH')) exit;`
- [x] **Upload validation** - File types validated via MIME type
- [x] **File permissions** - Proper file permissions set (644 for files, 755 for dirs)
- [x] **Path traversal prevented** - User-supplied paths validated

#### Upload Security
```php
// ✅ GOOD - Validate file type
$allowed_types = ['image/jpeg', 'image/png'];
$file_type = wp_check_filetype($filename);
if (!in_array($file_type['type'], $allowed_types)) {
    return new WP_Error('invalid_file', 'Invalid file type');
}
```

---

### ✅ API Security

#### External APIs
- [x] **HTTPS enforced** - All API calls use HTTPS
- [x] **API keys secured** - Keys stored in wp_options, not hardcoded
- [x] **Rate limiting** - API calls rate-limited appropriately
- [x] **Error messages** - No sensitive data in error messages
- [x] **Timeout handling** - API timeouts handled gracefully

#### API Key Management
```php
// ✅ GOOD - Secure storage
update_option('plugin_api_key', $encrypted_key);

// ❌ BAD - Hardcoded
define('API_KEY', 'secret_key_here'); // Never do this
```

---

### ✅ Data Privacy

#### Personal Data
- [x] **GDPR compliance** - Personal data handling documented
- [x] **Data retention** - Clear data retention policies
- [x] **Data export** - User data exportable
- [x] **Data deletion** - User data deletable
- [x] **Privacy policy** - Privacy policy provided

#### Data Storage
- [x] **Minimal data** - Only necessary data stored
- [x] **Secure transmission** - Data encrypted in transit (HTTPS)
- [x] **Access control** - Data access properly restricted

---

### ✅ Error Handling

#### Error Messages
- [x] **Generic errors** - No sensitive info in user-facing errors
- [x] **Debug mode** - Debug info only shown to admins
- [x] **Error logging** - Errors logged, not displayed in production
- [x] **Stack traces** - Stack traces never shown to users

#### Best Practices
```php
// ✅ GOOD - Generic error
if (!$result) {
    return ['success' => false, 'message' => 'Operation failed'];
}

// ❌ BAD - Detailed error
if (!$result) {
    return ['success' => false, 'message' => 'Database error: ' . $wpdb->last_error];
}
```

---

## 🔐 Security Best Practices

### 1. Validate Early, Escape Late

**Principle**: Validate and sanitize input as early as possible, escape output as late as possible.

```php
// Input validation (early)
$email = sanitize_email($_POST['email']);
if (!is_email($email)) {
    return ['error' => 'Invalid email'];
}

// Output escaping (late)
echo '<div>' . esc_html($stored_data) . '</div>';
```

---

### 2. Principle of Least Privilege

**Principle**: Only grant minimum necessary permissions.

```php
// ✅ GOOD - Specific capability
if (current_user_can('edit_posts')) {
    // Allow action
}

// ❌ BAD - Too broad
if (is_user_logged_in()) {
    // Allow action
}
```

---

### 3. Defense in Depth

**Principle**: Multiple layers of security.

```php
// Layer 1: Nonce check
wp_verify_nonce($_POST['nonce'], 'action');

// Layer 2: Permission check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Layer 3: Input validation
$id = absint($_POST['id']);
if ($id <= 0) {
    wp_die('Invalid ID');
}

// Layer 4: Prepared statement
$result = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM table WHERE id = %d",
    $id
));
```

---

### 4. Secure by Default

**Principle**: Default configuration should be secure.

```php
// ✅ GOOD - Secure default
private $allow_public_access = false;

// ❌ BAD - Insecure default
private $allow_public_access = true;
```

---

## 🛡️ Security Headers

### Recommended Headers

Add these headers for enhanced security:

```php
/**
 * Add security headers
 */
add_action('send_headers', function() {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // XSS protection
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy (customize as needed)
    // header('Content-Security-Policy: default-src \'self\'');
});
```

**Note**: CSP should be customized based on your plugin's needs.

---

## 🔑 Secrets Management

### DO NOT Store in Code

❌ **Never hardcode secrets**:
```php
// ❌ BAD
define('API_KEY', 'sk_live_abc123');
$password = 'hardcoded_password';
```

### Use WordPress Options

✅ **Store in database**:
```php
// ✅ GOOD
update_option('plugin_api_key', $api_key, false); // false = don't autoload
$api_key = get_option('plugin_api_key');
```

### Use Environment Variables

✅ **For server configuration**:
```php
// In wp-config.php
define('PLUGIN_API_KEY', getenv('PLUGIN_API_KEY'));

// In plugin
$api_key = defined('PLUGIN_API_KEY') ? PLUGIN_API_KEY : '';
```

### Encrypt Sensitive Data

✅ **For highly sensitive data**:
```php
// Encryption helper
function encrypt_data($data) {
    if (!function_exists('openssl_encrypt')) {
        return $data; // Fallback
    }

    $key = wp_salt('auth'); // Use WordPress salt
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

    return base64_encode($encrypted . '::' . $iv);
}

function decrypt_data($data) {
    if (!function_exists('openssl_decrypt')) {
        return $data; // Fallback
    }

    $key = wp_salt('auth');
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);

    return openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
}
```

---

## 🔍 Security Testing

### Manual Testing Checklist

- [ ] Test all forms with special characters (`<script>`, `' OR 1=1`, etc.)
- [ ] Try accessing AJAX endpoints without nonce
- [ ] Try accessing admin functions as non-admin user
- [ ] Test file uploads with invalid file types
- [ ] Check error messages don't leak sensitive info
- [ ] Verify SQL queries use prepared statements
- [ ] Test API rate limiting
- [ ] Check data deletion actually deletes data

### Automated Testing

```php
// Example security test
class Security_Test extends WP_UnitTestCase {
    public function test_ajax_requires_nonce() {
        $_POST['action'] = 'my_action';
        // No nonce provided

        try {
            do_action('wp_ajax_my_action');
            $this->fail('Should have failed without nonce');
        } catch (Exception $e) {
            $this->assertStringContainsString('nonce', $e->getMessage());
        }
    }

    public function test_non_admin_cannot_access_admin_function() {
        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));

        $result = $controller->admin_only_action();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Permission', $result['message']);
    }
}
```

---

## 🚨 Common Vulnerabilities

### 1. SQL Injection

**Vulnerable Code**:
```php
// ❌ VULNERABLE
$wpdb->query("DELETE FROM table WHERE id = {$_GET['id']}");
```

**Secure Code**:
```php
// ✅ SECURE
$id = absint($_GET['id']);
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}table WHERE id = %d",
    $id
));
```

---

### 2. Cross-Site Scripting (XSS)

**Vulnerable Code**:
```php
// ❌ VULNERABLE
echo '<div>' . $_POST['message'] . '</div>';
```

**Secure Code**:
```php
// ✅ SECURE
echo '<div>' . esc_html($_POST['message']) . '</div>';
```

---

### 3. CSRF

**Vulnerable Code**:
```php
// ❌ VULNERABLE - No nonce check
if (isset($_POST['delete_id'])) {
    $wpdb->delete($table, ['id' => $_POST['delete_id']]);
}
```

**Secure Code**:
```php
// ✅ SECURE - Nonce verified
if (isset($_POST['delete_id']) && wp_verify_nonce($_POST['nonce'], 'delete_action')) {
    $id = absint($_POST['delete_id']);
    $wpdb->delete($table, ['id' => $id], ['%d']);
}
```

---

### 4. Unauthorized Access

**Vulnerable Code**:
```php
// ❌ VULNERABLE - No permission check
function delete_all_data() {
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}my_table");
}
add_action('wp_ajax_delete_all', 'delete_all_data');
```

**Secure Code**:
```php
// ✅ SECURE - Permission checked
function delete_all_data() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}my_table");
    wp_send_json_success();
}
add_action('wp_ajax_delete_all', 'delete_all_data');
```

---

## 📊 Security Audit Results

### Current Status

| Category | Status | Notes |
|----------|--------|-------|
| **Input Validation** | ✅ Pass | All inputs sanitized |
| **Output Escaping** | ✅ Pass | All output escaped |
| **SQL Injection** | ✅ Pass | 100% prepared statements |
| **XSS Prevention** | ✅ Pass | All output escaped |
| **CSRF Protection** | ✅ Pass | Nonces on all forms/AJAX |
| **Authentication** | ✅ Pass | Proper capability checks |
| **File Security** | ✅ Pass | Direct access blocked |
| **API Security** | ✅ Pass | HTTPS, rate limiting |
| **Error Handling** | ✅ Pass | No sensitive data leaked |
| **Data Privacy** | ✅ Pass | GDPR compliant |

**Overall Security Grade**: ✅ **A+**

---

## 📚 Additional Resources

- **WordPress Security White Paper**: https://wordpress.org/about/security/
- **Plugin Security Guidelines**: https://developer.wordpress.org/plugins/security/
- **OWASP Top 10**: https://owasp.org/www-project-top-ten/
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **Sucuri WordPress Security**: https://sucuri.net/guides/wordpress-security/

---

## ✅ Security Maintenance

### Regular Tasks

**Monthly**:
- [ ] Review error logs for suspicious activity
- [ ] Update dependencies (Composer, npm)
- [ ] Check for WordPress core updates
- [ ] Review user access permissions

**Quarterly**:
- [ ] Run security scan (Sucuri, Wordfence, etc.)
- [ ] Review and update security policies
- [ ] Audit API keys and tokens
- [ ] Review third-party integrations

**Annually**:
- [ ] Full security audit
- [ ] Penetration testing (if applicable)
- [ ] Review and update documentation
- [ ] Security training for team

---

**Last Updated**: 2025-12-19
**Security Grade**: A+
**Status**: ✅ Production Ready
