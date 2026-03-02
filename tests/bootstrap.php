<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('BEEPBEEP_AI_PLUGIN_DIR')) {
    define('BEEPBEEP_AI_PLUGIN_DIR', __DIR__ . '/fixtures/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

$GLOBALS['bbai_test_options'] = [];
$GLOBALS['bbai_test_transients'] = [];

if (!function_exists('__')) {
    function __(string $text): string {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number): string {
        return 1 === $number ? $single : $plural;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value) {
        return $value;
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql') {
        return 'mysql' === $type ? '2026-01-01 00:00:00' : time();
    }
}

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false) {
        return array_key_exists($name, $GLOBALS['bbai_test_options']) ? $GLOBALS['bbai_test_options'][$name] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $name, $value): bool {
        $GLOBALS['bbai_test_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $name): bool {
        unset($GLOBALS['bbai_test_options'][$name]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $name) {
        return array_key_exists($name, $GLOBALS['bbai_test_transients']) ? $GLOBALS['bbai_test_transients'][$name] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $name, $value): bool {
        $GLOBALS['bbai_test_transients'][$name] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $name): bool {
        unset($GLOBALS['bbai_test_transients'][$name]);
        return true;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail(string $to, string $subject, string $message): bool {
        return !empty($to) && !empty($subject) && !empty($message);
    }
}

if (!function_exists('is_email')) {
    function is_email(string $email): bool {
        return false !== filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($value): string {
        $raw = is_scalar($value) ? strtolower((string) $value) : '';
        return preg_replace('/[^a-z0-9_\-]/', '', $raw) ?? '';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int {
        return abs((int) $value);
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return '/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $query, string $url): string {
        $separator = false !== strpos($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($query);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 1;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        return 'manage_options' === $capability || 'edit_post' === $capability;
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(int $attachment_id): string {
        return 'https://example.test/uploads/' . $attachment_id . '.jpg';
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null): string {
        return gmdate($format, $timestamp ?? time());
    }
}

if (!function_exists('bbai_debug_log')) {
    function bbai_debug_log(string $message, array $context = []): void {
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        private $data;

        public function __construct(string $code = '', string $message = '', $data = null) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool {
        return $value instanceof WP_Error;
    }
}

if (!class_exists('BbAI_API_Client_V2')) {
    class BbAI_API_Client_V2 {
        public bool $hasActiveLicense = false;
        public bool $hasReachedLimit = false;
        public bool $isAuthenticated = true;
        public string $token = '';
        public string $licenseKey = '';
        public array $usage = ['used' => 0, 'limit' => 50, 'remaining' => 50, 'plan' => 'free'];
        public $registerResult = ['user' => ['email' => 'test@example.com']];
        public $loginResult = ['user' => ['email' => 'test@example.com']];
        public array $licenseData = [];
        public $userInfo = ['id' => 1];

        public function has_active_license(): bool {
            return $this->hasActiveLicense;
        }

        public function has_reached_limit(): bool {
            return $this->hasReachedLimit;
        }

        public function get_usage() {
            return $this->usage;
        }

        public function get_token(): string {
            return $this->token;
        }

        public function register(string $email, string $password) {
            return $this->registerResult;
        }

        public function login(string $email, string $password) {
            return $this->loginResult;
        }

        public function clear_token(): void {
            $this->token = '';
        }

        public function clear_license_key(): void {
            $this->licenseKey = '';
        }

        public function get_license_key(): string {
            return $this->licenseKey;
        }

        public function is_authenticated(): bool {
            return $this->isAuthenticated;
        }

        public function get_license_data(): array {
            return $this->licenseData;
        }

        public function get_user_info() {
            return $this->userInfo;
        }
    }
}

if (!class_exists('BbAI_Core')) {
    class BbAI_Core {
        public $nextGenerateResult = 'Generated alt text';

        public function generate_and_save(int $attachment_id, string $source = 'manual', int $retry_count = 0, array $feedback = [], bool $regenerate = false) {
            return $this->nextGenerateResult;
        }
    }
}

if (!class_exists('BbAI_Usage_Tracker')) {
    class BbAI_Usage_Tracker {
        public static array $statsDisplay = ['used' => 0, 'limit' => 50, 'remaining' => 50];
        public static array $cachedUsage = ['used' => 0, 'limit' => 50, 'remaining' => 50];

        public static function clear_cache(): void {
        }

        public static function get_stats_display(): array {
            return self::$statsDisplay;
        }

        public static function get_cached_usage(bool $force = false): array {
            return self::$cachedUsage;
        }

        public static function update_usage(array $usage): void {
            self::$cachedUsage = $usage;
            self::$statsDisplay = $usage;
        }
    }
}

$pluginRoot = dirname(__DIR__);
require_once $pluginRoot . '/includes/core/class-event-bus.php';
require_once $pluginRoot . '/includes/services/class-authentication-service.php';
require_once $pluginRoot . '/includes/services/class-generation-service.php';
require_once $pluginRoot . '/includes/services/class-usage-service.php';
