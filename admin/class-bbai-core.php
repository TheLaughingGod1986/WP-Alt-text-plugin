<?php
/**
 * Core implementation for the Alt Text AI plugin.
 *
 * This file contains the original plugin implementation and is loaded
 * by the WordPress Plugin Boilerplate friendly bootstrap.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

// Constants should be defined in main plugin file, but provide minimal fallbacks.
$bbai_plugin_file = '';
if (defined('BEEPBEEP_AI_PLUGIN_FILE') && is_string(BEEPBEEP_AI_PLUGIN_FILE) && BEEPBEEP_AI_PLUGIN_FILE !== '') {
    $bbai_plugin_file = BEEPBEEP_AI_PLUGIN_FILE;
} elseif (defined('BBAI_PLUGIN_FILE') && is_string(BBAI_PLUGIN_FILE) && BBAI_PLUGIN_FILE !== '') {
    $bbai_plugin_file = BBAI_PLUGIN_FILE;
}
if ($bbai_plugin_file === '') {
    $bbai_plugin_file = __FILE__;
}

if (!defined('BBAI_PLUGIN_FILE')) {
    define('BBAI_PLUGIN_FILE', $bbai_plugin_file);
}

if (!defined('BBAI_PLUGIN_DIR')) {
    $bbai_dir = (defined('BEEPBEEP_AI_PLUGIN_DIR') && is_string(BEEPBEEP_AI_PLUGIN_DIR) && BEEPBEEP_AI_PLUGIN_DIR !== '')
        ? BEEPBEEP_AI_PLUGIN_DIR
        : plugin_dir_path($bbai_plugin_file);
    define('BBAI_PLUGIN_DIR', $bbai_dir);
}

if (!defined('BBAI_PLUGIN_URL')) {
    $bbai_url = (defined('BEEPBEEP_AI_PLUGIN_URL') && is_string(BEEPBEEP_AI_PLUGIN_URL) && BEEPBEEP_AI_PLUGIN_URL !== '')
        ? BEEPBEEP_AI_PLUGIN_URL
        : plugin_dir_url($bbai_plugin_file);
    define('BBAI_PLUGIN_URL', $bbai_url);
}

if (!defined('BBAI_PLUGIN_BASENAME')) {
    $bbai_plugin_basename = (defined('BEEPBEEP_AI_PLUGIN_BASENAME') && is_string(BEEPBEEP_AI_PLUGIN_BASENAME) && BEEPBEEP_AI_PLUGIN_BASENAME !== '')
        ? BEEPBEEP_AI_PLUGIN_BASENAME
        : '';
    if ($bbai_plugin_basename === '') {
        $bbai_plugin_basename = plugin_basename($bbai_plugin_file);
    }
    if ($bbai_plugin_basename === '' || !is_string($bbai_plugin_basename)) {
        $bbai_plugin_basename = 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
    }
    define('BBAI_PLUGIN_BASENAME', $bbai_plugin_basename);
}

if (!defined('BBAI_VERSION')) {
    define('BBAI_VERSION', defined('BEEPBEEP_AI_VERSION') ? BEEPBEEP_AI_VERSION : '4.5.21');
}

// Load API clients, usage tracker, and queue infrastructure
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-onboarding.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';

// Load Core class traits
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-auth.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-license.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-billing.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-media.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-generation.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-review.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-assets.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-export.php';

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\API_Client_V2;
use BeepBeepAI\AltTextGenerator\Services\Usage_Helper;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_Auth;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_License;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_Billing;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_Queue;
use BeepBeepAI\AltTextGenerator\Traits\Core_Media;
use BeepBeepAI\AltTextGenerator\Traits\Core_Generation;
use BeepBeepAI\AltTextGenerator\Traits\Core_Review;
use BeepBeepAI\AltTextGenerator\Traits\Core_Assets;
use BeepBeepAI\AltTextGenerator\Traits\Core_Export;

class Core {
    // Use traits for modular functionality
    use Core_Ajax_Auth;
    use Core_Ajax_License;
    use Core_Ajax_Billing;
    use Core_Ajax_Queue;
    use Core_Media;
    use Core_Generation;
    use Core_Review;
    use Core_Export;
    use Core_Assets {
        enqueue_admin as private trait_enqueue_admin;
    }
    const OPTION_KEY = 'bbai_settings';
    const NONCE_KEY  = 'bbai_nonce';
    const CAPABILITY = 'manage_bbbbai_text';
    private const MENU_SLUG_DASHBOARD = 'bbai';
    private const MENU_SLUG_LIBRARY   = 'bbai-library';
    private const MENU_SLUG_ONBOARDING = 'bbai-onboarding';
    private const ALT_COVERAGE_TRANSIENT_KEY = 'bbai_alt_coverage_scan_v4';
    private const ALT_COVERAGE_TRANSIENT_TTL = 86400;
    private const ALT_COVERAGE_SCAN_JOB_PREFIX = 'bbai_alt_coverage_job_';
    private const ALT_COVERAGE_SCAN_JOB_TTL = 900;
    private const ALT_COVERAGE_SCAN_BATCH_SIZE = 150;
    private const FREE_PLAN_IMAGE_LIMIT = 50;
    private const MEDIA_UPLOAD_TRIGGER_TRANSIENT_PREFIX = 'bbai_upgrade_upload_';

    private const DEFAULT_CHECKOUT_PRICE_IDS = [
        'pro'     => 'price_1SMrxaJl9Rm418cMM4iikjlJ',
        'growth'  => 'price_1SMrxaJl9Rm418cMM4iikjlJ', // alias for pro
        'agency'  => 'price_1SMrxaJl9Rm418cMnJTShXSY',
        'credits' => 'price_1SMrxbJl9Rm418cM0gkzZQZt',
    ];

    /**
     * Stripe Payment Link URLs (direct buy links that bypass checkout session creation).
     */
    private const DEFAULT_STRIPE_LINKS = [
        'pro'     => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
        'growth'  => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
        'agency'  => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
        'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00',
    ];

    private $stats_cache = null;
    private $token_notice = null;
    private $api_client = null;
    private $checkout_price_cache = null;
    private $debug_bootstrap = null;
    private $account_summary = null;

    /**
     * Whether regenerate flow debug logging is enabled.
     *
     * Define `BBAI_DEBUG_REGENERATE_FLOW` as true in wp-config.php to enable.
     */
    private function is_regenerate_debug_enabled(): bool {
        return defined('BBAI_DEBUG_REGENERATE_FLOW') && (bool) BBAI_DEBUG_REGENERATE_FLOW;
    }

    /**
     * Log regenerate flow details when debug flag is enabled.
     *
     * @param string $message Log message.
     * @param array  $context Optional structured context.
     */
    private function maybe_log_regenerate_debug(string $message, array $context = []): void {
        if (!$this->is_regenerate_debug_enabled()) {
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', $message, $context, 'generation');
        }
    }

    /**
     * Build the same stable identity context used by the PostHog client.
     *
     * @return array<string,mixed>
     */
    private function build_generation_posthog_identity_context(): array {
        $usage_data = Usage_Tracker::get_stats_display();
        $user_data = isset($this->api_client) ? $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data()) : [];
        $license_data = (isset($this->api_client) && method_exists($this->api_client, 'get_license_data'))
            ? $this->api_client->get_license_data()
            : [];
        $site_hash = $this->get_posthog_site_hash();

        $plan_type = sanitize_key(
            (string) (
                $usage_data['plan']
                ?? $usage_data['plan_type']
                ?? ( is_array($user_data) ? ( $user_data['planSlug'] ?? $user_data['plan'] ?? $user_data['plan_type'] ?? '' ) : '' )
                ?? ( is_array($license_data) ? ( $license_data['organization']['plan'] ?? '' ) : '' )
            )
        );

        if ('' === $plan_type) {
            $plan_type = 'free';
        }

        return $this->get_posthog_identity_context(
            is_array($user_data) ? $user_data : [],
            is_array($license_data) ? $license_data : [],
            $site_hash,
            $plan_type
        );
    }

    private function resolve_alt_generation_mode(string $source): string {
        $source = sanitize_key($source);

        if (in_array($source, ['bulk', 'bulk-regenerate'], true)) {
            return 'bulk';
        }

        if (in_array($source, ['auto', 'upload', 'metadata', 'update', 'save', 'queue'], true)) {
            return 'auto';
        }

        return 'single';
    }

    private function resolve_alt_generation_source_page(string $source): string {
        $page = $this->get_telemetry_page_key();
        if (!empty($page) && !in_array($page, ['unknown', 'other'], true)) {
            return $page;
        }

        $mode = $this->resolve_alt_generation_mode($source);
        if ('bulk' === $mode) {
            return 'alt_library';
        }

        if ('auto' === $mode) {
            return 'automation';
        }

        if ('wpcli' === sanitize_key($source)) {
            return 'wpcli';
        }

        return 'dashboard';
    }

    /**
     * Convert generation context into a simple analytics-friendly segment.
     *
     * @param array<string,mixed> $context
     */
    private function infer_alt_image_context(array $context): ?string {
        if (!empty($context['product_name']) || !empty($context['product_title']) || !empty($context['price'])) {
            return 'woocommerce_product';
        }

        if (!empty($context['post_title'])) {
            return 'post_context';
        }

        if (!empty($context['caption'])) {
            return 'caption_only';
        }

        if (!empty($context['title']) || !empty($context['filename'])) {
            return 'image_only';
        }

        return null;
    }

    /**
     * Emit a trustworthy value-delivery event only after ALT text has been persisted.
     *
     * @param int                 $attachment_id Attachment ID.
     * @param string              $alt_text      Generated ALT text.
     * @param string              $source        Generation source.
     * @param string              $model         Model identifier.
     * @param array<string,mixed> $context       Generation context.
     * @param array<string,mixed> $response_meta Backend response meta.
     */
    private function maybe_emit_alt_generated_event(int $attachment_id, string $alt_text, string $source, string $model, array $context = [], array $response_meta = []): void {
        $identity_context = $this->build_generation_posthog_identity_context();
        $distinct_id = $this->resolve_posthog_identify_id($identity_context);
        if ('' === $distinct_id) {
            return;
        }

        $generated_at = (string) get_post_meta($attachment_id, '_bbai_generated_at', true);
        $fingerprint = md5(implode('|', [
            (string) $attachment_id,
            sanitize_key($source),
            $generated_at,
            sanitize_text_field($model),
            md5($alt_text),
        ]));
        $transient_key = 'bbai_alt_generated_' . substr($fingerprint, 0, 32);
        if (get_transient($transient_key)) {
            return;
        }
        set_transient($transient_key, 1, DAY_IN_SECONDS);

        $wordpress_user_id = get_current_user_id();
        $is_first_generation = false;
        if ($wordpress_user_id > 0 && !get_user_meta($wordpress_user_id, 'bbai_telemetry_first_alt_at', true)) {
            update_user_meta($wordpress_user_id, 'bbai_telemetry_first_alt_at', time());
            $is_first_generation = true;
        }

        $properties = [
            'account_id'          => $identity_context['account_id'] ?? '',
            'license_key'         => $identity_context['license_key'] ?? '',
            'site_id'             => $identity_context['site_id'] ?? '',
            'site_hash'           => $identity_context['site_hash'] ?? '',
            'user_id'             => $identity_context['user_id'] ?? '',
            'plan'                => $identity_context['plan'] ?? 'free',
            'attachment_id'       => $attachment_id,
            'image_id'            => $attachment_id,
            'source_page'         => $this->resolve_alt_generation_source_page($source),
            'generation_mode'     => $this->resolve_alt_generation_mode($source),
            'provider'            => 'openai',
            'model'               => sanitize_text_field($model),
            'success'             => true,
            'plugin_version'      => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
            'wordpress_user_id'   => $wordpress_user_id > 0 ? $wordpress_user_id : null,
            'is_first_generation' => $is_first_generation,
            '$insert_id'          => 'alt_generated:' . $fingerprint,
        ];

        $image_context = $this->infer_alt_image_context($context);
        if (null !== $image_context) {
            $properties['image_context'] = $image_context;
        }

        if (isset($response_meta['generation_time_ms']) && is_numeric($response_meta['generation_time_ms'])) {
            $properties['generation_latency_ms'] = (int) round($response_meta['generation_time_ms']);
        }

        $properties = array_filter(
            $properties,
            static function ($value) {
                return null !== $value && '' !== $value;
            }
        );

        if (function_exists('bbai_telemetry_emit')) {
            bbai_telemetry_emit('alt_generated', $properties);
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\BBAI_Telemetry')) {
            \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::capture_posthog_event(
                'alt_generated',
                $distinct_id,
                $properties
            );
        }
    }

    public function user_can_manage(){
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public function __construct() {
        // Use Phase 2 API client (JWT-based authentication)
        $this->api_client = API_Client_V2::get_instance();
        // Soft-migrate legacy options to new prefixed keys
        $current = get_option(self::OPTION_KEY, null);
        if ($current === null) {
            foreach (['bbai_gpt_settings', 'beepbeepai_settings', 'opptibbai_settings', 'bbai_settings'] as $legacy_key) {
                $legacy_value = get_option($legacy_key, null);
                if ($legacy_value !== null) {
                    update_option(self::OPTION_KEY, $legacy_value, false);
                    break;
                }
            }
        }
        $this->ensure_upload_generation_default();

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            // Log initialization (table is created by DB_Schema on activation/upgrade).
            Debug_Log::log('info', 'AI Alt Text plugin initialized', [
                'version' => BEEPBEEP_AI_VERSION,
                'authenticated' => $this->api_client->is_authenticated() ? 'yes' : 'no',
            ], 'core');

            update_option('bbai_logs_ready', true, false);
        }

        // Initialize credit usage logger hooks
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::init_hooks();

        // Check for migration on admin init
        add_action('admin_init', [__CLASS__, 'maybe_run_migration'], 5);
        // One-time legacy cache key cleanup (consolidate to bbai_usage_cache / bbai_quota_cache)
        add_action('init', [__CLASS__, 'maybe_clean_legacy_cache_keys'], 1);
        // One-time legacy settings options cleanup (after migration to bbai_settings)
        add_action('init', [__CLASS__, 'maybe_clean_legacy_settings_options'], 2);
    }

    /**
     * One-time cleanup of migrated legacy settings options. Deletes bbai_gpt_settings,
     * beepbeepai_settings, opptibbai_settings after confirming bbai_settings has data.
     */
    public static function maybe_clean_legacy_settings_options() {
        if (get_option('bbai_legacy_settings_cleaned', false)) {
            return;
        }
        $current = get_option(self::OPTION_KEY, null);
        if ($current === null || $current === false) {
            return;
        }
        foreach (['bbai_gpt_settings', 'beepbeepai_settings', 'opptibbai_settings'] as $legacy_key) {
            delete_option($legacy_key);
        }
        update_option('bbai_legacy_settings_cleaned', true, false);
    }

    /**
     * One-time cleanup of legacy usage cache keys. Consolidates to bbai_usage_cache and bbai_quota_cache.
     */
    public static function maybe_clean_legacy_cache_keys() {
        if (get_option('bbai_legacy_cache_cleaned', false)) {
            return;
        }
        delete_transient('opptibbai_usage_cache');
        delete_transient('beepbeep_ai_usage_cache');
        delete_option('beepbeep_ai_usage_cache');
        update_option('bbai_legacy_cache_cleaned', true, false);
    }

    /**
     * Check and run migration if needed.
     */
    public static function maybe_run_migration() {
        // Only run in admin and if not already migrated
        if (!is_admin()) {
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-migrate-usage.php';
        if (!\BeepBeepAI\AltTextGenerator\Migrate_Usage::is_migrated()) {
            // Run migration in background (don't block admin page load)
            // Migration will run on first admin page load after activation
            if (!wp_next_scheduled('beepbeepai_run_migration')) {
                wp_schedule_single_event(time() + 30, 'beepbeepai_run_migration');
            }
        }
    }

    /**
     * Run migration (called by cron).
     */
    public static function run_migration() {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-migrate-usage.php';
        \BeepBeepAI\AltTextGenerator\Migrate_Usage::migrate();
    }

    /**
     * Expose API client for collaborators (REST controller, admin UI, etc.).
     *
     * @return API_Client_V2
     */
    public function get_api_client() {
        return $this->api_client;
    }

    /**
     * Check whether this site is already connected to an account/license.
     *
     * @return bool
     */
    private function has_connected_account_for_trial(): bool {
        if (function_exists('bbai_is_authenticated') && \bbai_is_authenticated()) {
            return true;
        }

        try {
            if ($this->api_client->is_authenticated() || $this->api_client->has_active_license()) {
                return true;
            }
        } catch (\Exception $e) {
            // Fall through to stored-credential checks.
        } catch (\Error $e) {
            // Fall through to stored-credential checks.
        }

        $stored_token = get_option('beepbeepai_jwt_token', '');
        $legacy_token = get_option('opptibbai_jwt_token', '');
        if (!empty($stored_token) || !empty($legacy_token)) {
            return true;
        }

        try {
            $stored_license = $this->api_client->get_license_key();
            if (!empty($stored_license)) {
                return true;
            }
        } catch (\Exception $e) {
            // Ignore and continue.
        } catch (\Error $e) {
            // Ignore and continue.
        }

        return false;
    }

    /**
     * Get local trial status for current site.
     *
     * @return array{site_hash:string,limit:int,used:int,remaining:int,should_gate:bool}
     */
    private function get_trial_status(): array {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';

        $status = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status();
        $should_gate = !$this->has_connected_account_for_trial();
        $limit = max(1, (int) ($status['credits_total'] ?? $status['limit'] ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit()));
        $used = max(0, (int) ($status['credits_used'] ?? $status['used'] ?? 0));
        $remaining = max(0, (int) ($status['credits_remaining'] ?? $status['remaining'] ?? max(0, $limit - $used)));
        $low_credit_threshold = max(1, (int) ($status['low_credit_threshold'] ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_low_credit_threshold()));

        $status['credits_total'] = $limit;
        $status['limit'] = $limit;
        $status['credits_used'] = $used;
        $status['used'] = $used;
        $status['credits_remaining'] = $remaining;
        $status['remaining'] = $remaining;
        $status['remaining_free_images'] = $remaining;
        $status['auth_state'] = 'anonymous';
        $status['quota_type'] = 'trial';
        $status['quota_state'] = (string) ($status['quota_state'] ?? ($remaining <= 0 ? 'exhausted' : ($remaining <= $low_credit_threshold ? 'near_limit' : 'active')));
        $status['signup_required'] = !empty($status['signup_required']) || $remaining <= 0;
        $status['upgrade_required'] = false;
        $status['is_trial'] = true;
        $status['trial_exhausted'] = !empty($status['exhausted']) || $remaining <= 0;
        $status['low_credit_threshold'] = $low_credit_threshold;
        $status['trial_near_limit'] = $remaining > 0 && $remaining <= $low_credit_threshold;
        $status['free_plan_offer'] = max(0, (int) ($status['free_plan_offer'] ?? 50));

        if ( ! $should_gate ) {
            $status['auth_state'] = 'authenticated';
            $status['signup_required'] = false;
            $status['upgrade_required'] = false;
            $status['quota_state'] = 'active';
        }

        $status['should_gate'] = $should_gate;

        return $status;
    }

    /**
     * Build a normalized anonymous trial usage payload.
     *
     * @param array<string, mixed> $overrides Additional response fields.
     * @return array<string, mixed>
     */
    private function get_trial_usage_payload(array $overrides = []): array {
        $trial = $this->get_trial_status();

        return array_merge($trial, [
            'auth_state' => (string) ($trial['auth_state'] ?? 'anonymous'),
            'quota_type' => (string) ($trial['quota_type'] ?? 'trial'),
            'quota_state' => (string) ($trial['quota_state'] ?? 'active'),
            'signup_required' => !empty($trial['signup_required']),
            'upgrade_required' => false,
            'is_trial' => true,
            'trial_exhausted' => !empty($trial['trial_exhausted']),
            'trial_near_limit' => !empty($trial['trial_near_limit']),
            'credits_total' => max(1, (int) ($trial['credits_total'] ?? $trial['limit'] ?? 5)),
            'credits_used' => max(0, (int) ($trial['credits_used'] ?? $trial['used'] ?? 0)),
            'credits_remaining' => max(0, (int) ($trial['credits_remaining'] ?? $trial['remaining'] ?? 0)),
            'limit' => max(1, (int) ($trial['credits_total'] ?? $trial['limit'] ?? 5)),
            'used' => max(0, (int) ($trial['credits_used'] ?? $trial['used'] ?? 0)),
            'remaining' => max(0, (int) ($trial['credits_remaining'] ?? $trial['remaining'] ?? 0)),
            'remaining_free_images' => max(0, (int) ($trial['credits_remaining'] ?? $trial['remaining'] ?? 0)),
            'free_plan_offer' => max(0, (int) ($trial['free_plan_offer'] ?? 50)),
            'low_credit_threshold' => max(0, (int) ($trial['low_credit_threshold'] ?? 2)),
        ], $overrides);
    }

    /**
     * Resolve the current usage payload from the connected account or anonymous trial.
     *
     * @return array<string, mixed>
     */
    private function get_connected_usage_payload(): array {
        $auth_state = Auth_State::resolve($this->api_client);

        return Usage_Helper::get_usage(
            $this->api_client,
            !empty($auth_state['has_connected_account'])
        );
    }

    /**
     * Build common trial exhausted payload for JSON responses.
     *
     * @return array
     */
    private function get_trial_exhausted_payload(): array {
        $trial = $this->get_trial_usage_payload();
        $limit = max(1, (int) ($trial['credits_total'] ?? $trial['limit'] ?? 5));
        $free_plan_offer = max(0, (int) ($trial['free_plan_offer'] ?? 50));

        return array_merge($trial, [
            'message' => sprintf(
                /* translators: 1: anonymous trial limit, 2: signed-up free plan offer. */
                __('You’ve used your %1$d free trial generations. Create a free account to unlock %2$d generations per month.', 'beepbeep-ai-alt-text-generator'),
                $limit,
                $free_plan_offer
            ),
            'code' => 'bbai_trial_exhausted',
            'remaining' => 0,
            'remaining_free_images' => 0,
            'trial_exhausted' => true,
            'limit' => $limit,
            'used' => (int) ($trial['credits_used'] ?? $trial['used'] ?? 0),
            'credits_remaining' => 0,
            'signup_required' => true,
            'quota_state' => 'exhausted',
        ]);
    }

    /**
     * Build trial exhausted WP_Error.
     *
     * @return \WP_Error
     */
    private function get_trial_exhausted_error(): \WP_Error {
        $payload = $this->get_trial_exhausted_payload();

        return new \WP_Error(
            'bbai_trial_exhausted',
            $payload['message'],
            $payload
        );
    }

    public function default_usage(){
        return [
            'prompt'      => 0,
            'completion'  => 0,
            'total'       => 0,
            'requests'    => 0,
            'last_request'=> null,
        ];
    }

    private function is_upload_generation_enabled(?array $opts = null): bool {
        $opts = is_array($opts) ? $opts : get_option(self::OPTION_KEY, []);
        if (!is_array($opts) || !array_key_exists('enable_on_upload', $opts)) {
            return true;
        }
        return !empty($opts['enable_on_upload']);
    }

    private function ensure_upload_generation_default(): void {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts) || array_key_exists('enable_on_upload', $opts)) {
            return;
        }

        $opts['enable_on_upload'] = true;
        update_option(self::OPTION_KEY, $opts, false);
    }

    /**
     * Get post meta with backward compatibility for old ai_alt_ keys.
     * Automatically migrates old keys to new beepbeepai_ keys.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @param bool   $single  Whether to return a single value.
     * @return mixed Meta value.
     */
    private function get_meta_with_compat($post_id, $key, $single = true) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;
        
        // Check for new key first
        $value = get_post_meta($post_id, $new_key, $single);
        if ($value !== '' && $value !== false && $value !== null) {
            return $value;
        }
        
        // Check for old key and migrate if found
        $old_value = get_post_meta($post_id, $old_key, $single);
        if ($old_value !== '' && $old_value !== false && $old_value !== null) {
            // Migrate to new key
            update_post_meta($post_id, $new_key, $old_value);
            // Delete old key after migration
            delete_post_meta($post_id, $old_key);
            return $old_value;
        }
        
        return $single ? '' : [];
    }
    
    /**
     * Update post meta using new beepbeepai_ prefix.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @param mixed  $value   Meta value.
     * @return bool|int Result of update_post_meta.
     */
    private function update_meta_with_compat($post_id, $key, $value) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;
        
        // Update new key
        $result = update_post_meta($post_id, $new_key, $value);
        
        // Delete old key if it exists (migration cleanup)
        if (metadata_exists('post', $post_id, $old_key)) {
            delete_post_meta($post_id, $old_key);
        }
        
        return $result;
    }
    
    /**
     * Delete post meta from both old and new keys.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @return bool Result of delete_post_meta.
     */
    private function delete_meta_with_compat($post_id, $key) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;
        
        $result1 = delete_post_meta($post_id, $new_key);
        $result2 = delete_post_meta($post_id, $old_key);
        
        return $result1 || $result2;
    }

    private function record_usage($usage){
        $prompt     = isset($usage['prompt']) ? max(0, intval($usage['prompt'])) : 0;
        $completion = isset($usage['completion']) ? max(0, intval($usage['completion'])) : 0;
        $total      = isset($usage['total']) ? max(0, intval($usage['total'])) : ($prompt + $completion);

        if (!$prompt && !$completion && !$total){
            return;
        }

        $opts = get_option(self::OPTION_KEY, []);
        $current = $opts['usage'] ?? $this->default_usage();
        $current['prompt']     += $prompt;
        $current['completion'] += $completion;
        $current['total']      += $total;
        $current['requests']   += 1;
        $current['last_request'] = current_time('mysql');

        $opts['usage'] = $current;
        $opts['token_alert_sent'] = $opts['token_alert_sent'] ?? false;
        $opts['token_limit'] = $opts['token_limit'] ?? 0;

        if (!empty($opts['token_limit']) && !$opts['token_alert_sent'] && $current['total'] >= $opts['token_limit']){
            $opts['token_alert_sent'] = true;
            set_transient('beepbeepai_token_notice', [
                'total' => $current['total'],
                'limit' => $opts['token_limit'],
            ], DAY_IN_SECONDS);
            $this->send_notification(
                __('AI Alt Text token usage alert', 'beepbeep-ai-alt-text-generator'),
                sprintf(
                    /* translators: 1: total tokens, 2: token limit */
                    __('Cumulative token usage has reached %1$d (threshold %2$d). Consider reviewing your OpenAI usage.', 'beepbeep-ai-alt-text-generator'),
                    $current['total'],
                    $opts['token_limit']
                )
            );
        }

        update_option(self::OPTION_KEY, $opts, false);
        $this->stats_cache = null;
    }

    /**
     * Refresh usage snapshot from backend when a site license is active.
     * Throttled to avoid hammering the API during bulk jobs.
     */
    private function refresh_license_usage_snapshot($force = false) {
        if (!$this->api_client->has_active_license()) {
            return;
        }

        $cache_key = 'bbai_usage_refresh_lock';
        if (!$force) {
            $last_refresh = get_transient($cache_key);
            if (!empty($last_refresh)) {
                $elapsed = time() - intval($last_refresh);
                if ($elapsed < 60) {
                    return;
                }
            }
        }

        $latest_usage = $this->api_client->get_usage();
        if (is_wp_error($latest_usage) || !is_array($latest_usage)) {
            return;
        }

        Usage_Tracker::update_usage($latest_usage);
        set_transient($cache_key, time(), MINUTE_IN_SECONDS);
    }

    /**
     * Debug Logs are visible to administrators or when WP_DEBUG is enabled.
     *
     * @return bool
     */
    private function can_show_debug_logs_tab(): bool {
        return (defined('WP_DEBUG') && WP_DEBUG) || current_user_can('manage_options');
    }

    /**
     * Internal UI Kit preview (Phase 8). Not for production shoppers.
     *
     * @return bool
     */
    private function can_show_ui_kit_page(): bool {
        if (!current_user_can('manage_options') && !current_user_can(self::CAPABILITY)) {
            return false;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        if (defined('BBAI_UI_KIT') && BBAI_UI_KIT) {
            return true;
        }
        return false;
    }

    /**
     * Build a default debug payload that mirrors Debug_Log::get_logs() shape.
     *
     * @param array<string, mixed> $args Query args.
     * @return array<string, mixed>
     */
    private function get_default_debug_payload(array $args = []): array {
        $page = max(1, intval($args['page'] ?? 1));
        $per_page = max(1, intval($args['per_page'] ?? 10));

        return [
            'logs' => [],
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => 1,
                'total_items' => 0,
            ],
            'stats' => [
                'total' => 0,
                'warnings' => 0,
                'errors' => 0,
                'last_event' => null,
                'last_api' => null,
            ],
        ];
    }

    /**
     * Return default API status values when logs are unavailable.
     *
     * @return array<string, mixed>
     */
    private function get_default_debug_service_status(): array {
        return [
            'connection_status' => 'failed',
            'last_api_request' => null,
            'last_api_request_timestamp' => null,
            'average_response_time_ms' => null,
            'last_api_error' => null,
            'last_api_error_timestamp' => null,
            'last_api_error_message' => null,
        ];
    }

    /**
     * Build system metadata for support copy/paste and quick diagnostics.
     *
     * @return array<string, string>
     */
    private function get_debug_system_status(): array {
        $theme_name = '';
        $theme = wp_get_theme();
        if ($theme instanceof \WP_Theme) {
            $theme_name = (string) $theme->get('Name');
        }
        if ($theme_name === '') {
            $theme_name = 'Unknown';
        }

        $plugin_version = 'unknown';
        if (defined('BEEPBEEP_AI_VERSION') && is_string(BEEPBEEP_AI_VERSION) && BEEPBEEP_AI_VERSION !== '') {
            $plugin_version = BEEPBEEP_AI_VERSION;
        } elseif (defined('BBAI_VERSION') && is_string(BBAI_VERSION) && BBAI_VERSION !== '') {
            $plugin_version = BBAI_VERSION;
        }

        return [
            'plugin_version' => (string) $plugin_version,
            'wordpress_version' => (string) get_bloginfo('version'),
            'php_version' => (string) PHP_VERSION,
            'active_theme' => $theme_name,
            'site_url' => esc_url_raw(site_url()),
        ];
    }

    /**
     * Attach support/troubleshooting sections to the base debug payload.
     *
     * @param array<string, mixed> $payload Base logs payload.
     * @return array<string, mixed>
     */
    private function enrich_debug_payload(array $payload): array {
        $system_status = $this->get_debug_system_status();
        $service_status = class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')
            ? Debug_Log::get_api_service_status()
            : $this->get_default_debug_service_status();
        $recent_errors = class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')
            ? Debug_Log::get_recent_errors(5)
            : [];

        $payload['system_status'] = $system_status;
        $payload['service_status'] = $service_status;
        $payload['recent_errors'] = $recent_errors;
        $payload['copy_debug_info'] = [
            'plugin_version' => $system_status['plugin_version'] ?? 'unknown',
            'wordpress_version' => $system_status['wordpress_version'] ?? '',
            'php_version' => $system_status['php_version'] ?? '',
            'last_api_error' => $service_status['last_api_error_message'] ?? '',
            'last_api_request' => $service_status['last_api_request'] ?? '',
            'site_url' => $system_status['site_url'] ?? '',
        ];

        return $payload;
    }

    /**
     * Public accessor used by AJAX/REST/script bootstrap for Debug page payload.
     *
     * @param array<string, mixed> $args Log query args.
     * @return array<string, mixed>
     */
    public function get_debug_payload(array $args = []): array {
        $defaults = [
            'level' => '',
            'search' => '',
            'date' => '',
            'date_from' => '',
            'date_to' => '',
            'per_page' => 10,
            'page' => 1,
        ];
        $query_args = wp_parse_args($args, $defaults);

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            return $this->enrich_debug_payload($this->get_default_debug_payload($query_args));
        }

        return $this->enrich_debug_payload(Debug_Log::get_logs($query_args));
    }

    private function get_debug_bootstrap($force_refresh = false) {
        if ($force_refresh || $this->debug_bootstrap === null) {
            $this->debug_bootstrap = $this->get_debug_payload([
                'per_page' => 10,
                'page' => 1,
            ]);
        }

        return $this->debug_bootstrap;
    }

    private function send_notification($subject, $message){
        $opts = get_option(self::OPTION_KEY, []);
        $email = $opts['notify_email'] ?? get_option('admin_email');
        $email = is_email($email) ? $email : get_option('admin_email');
        if (!$email){
            return false;
        }
        $result = wp_mail($email, $subject, $message);
        if (!$result && class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Email notification failed to send', [
                'email' => $email,
                'subject' => $subject
            ]);
        }
        return $result;
    }

    public function ensure_capability(){
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)){
            $role->add_cap(self::CAPABILITY);
        }
    }

    public function maybe_display_threshold_notice(){
        if (!$this->user_can_manage()){
            return;
        }
        $data = get_transient('beepbeepai_token_notice');
        if (!$data) {
            // Fallback to legacy transient name during transition
            $data = get_transient('bbai_token_notice');
        }
        if ($data){
            $this->token_notice = $data;
            add_action('admin_notices', [$this, 'render_token_notice']);
        }
    }

    /**
     * Allow direct checkout links to create Stripe sessions without JavaScript
     */
	    public function maybe_handle_direct_checkout() {
	        if (!is_admin()) { return; }
	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	        $page = $bbai_page_input;
	        if ($page !== 'bbai-checkout') { return; }

	        $action = 'bbai_direct_checkout';	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_bbai_nonce'] ?? '' ) ), $action ) ) {
	            wp_die(esc_html__('Security check failed. Please try again from the dashboard.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator'));
	        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $plan_input = isset($_GET['plan']) ? sanitize_key(wp_unslash($_GET['plan'])) : (isset($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : '');
        $valid_plan_ids = array_keys($this->get_checkout_price_ids());
        $plan_param = in_array($plan_input, $valid_plan_ids, true) ? $plan_input : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $price_id = isset($_GET['price_id']) && $_GET['price_id'] !== null ? sanitize_text_field(wp_unslash($_GET['price_id'])) : '';
        $fallback = Usage_Tracker::get_upgrade_url();

        if ($plan_param) {
            $mapped_price = $this->get_checkout_price_id($plan_param);
            if (!empty($mapped_price)) {
                $price_id = $mapped_price;
            }
        }

        if (empty($price_id)) {
            wp_safe_redirect($fallback);
            exit;
        }

        $success_url = admin_url('admin.php?page=bbai&checkout=success');
        $cancel_url  = admin_url('admin.php?page=bbai&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);
        $resolved_checkout_url = $this->resolve_checkout_url_from_result($result, $plan_param, $price_id);

        if (is_wp_error($result) || $resolved_checkout_url === '') {
            $fallback_checkout_url = $this->get_checkout_fallback_url($plan_param, $price_id);
            if ($fallback_checkout_url !== '') {
                $this->redirect_to_checkout_url($fallback_checkout_url);
            }

            $message = $this->get_checkout_error_message($result);
            $query_args = [
                'page'            => 'beepbeep-ai-alt-text-generator',
                'checkout_error'  => rawurlencode($message),
            ];
            if (!empty($plan_param)) {
                $query_args['plan'] = $plan_param;
            }
            $redirect = add_query_arg($query_args, admin_url('upload.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        // Redirect to Stripe checkout
        $this->redirect_to_checkout_url($resolved_checkout_url);
    }

    /**
     * Retrieve checkout price IDs sourced from the backend
     */
    public function get_checkout_price_ids() {
        if (is_array($this->checkout_price_cache)) {
            return $this->checkout_price_cache;
        }

        $prices = self::DEFAULT_CHECKOUT_PRICE_IDS;

        $cached = get_transient('bbai_remote_price_ids');
        if (!is_array($cached)) {
            $plans = $this->api_client->get_plans();
            if (!is_wp_error($plans) && !empty($plans)) {
                $remote = [];
                foreach ($plans as $bbai_plan) {
                    if (!is_array($bbai_plan)) {
                        continue;
                    }
                    $plan_id = isset($bbai_plan['id']) && is_string($bbai_plan['id']) ? sanitize_key($bbai_plan['id']) : '';
                    $price_id = !empty($bbai_plan['priceId']) && is_string($bbai_plan['priceId']) ? sanitize_text_field($bbai_plan['priceId']) : '';
                    if ($plan_id && $price_id) {
                        $remote[$plan_id] = $price_id;
                    }
                }
                if (!empty($remote)) {
                    set_transient('bbai_remote_price_ids', $remote, 10 * MINUTE_IN_SECONDS);
                    $cached = $remote;
                }
            }
        }

        if (is_array($cached)) {
            foreach ($cached as $plan_id => $price_id) {
                $plan_id = is_string($plan_id) ? sanitize_key($plan_id) : '';
                $price_id = is_string($price_id) ? sanitize_text_field($price_id) : '';
                if ($plan_id && $price_id) {
                    $prices[$plan_id] = $price_id;
                }
            }
        }

        // Backwards compatibility: use saved overrides when a plan is missing a mapped price.
        $stored = get_option('bbai_checkout_prices', []);
        if (is_array($stored) && !empty($stored)) {
            foreach ($stored as $key => $value) {
                $key = is_string($key) ? sanitize_key($key) : '';
                $value = is_string($value) ? sanitize_text_field($value) : '';
                if ($key && $value && empty($prices[$key])) {
                    $prices[$key] = $value;
                }
            }
        }

        $prices = apply_filters('bbai_checkout_price_ids', $prices);
        $this->checkout_price_cache = $prices;
        return $prices;
    }

    /**
     * Helper to grab a single price ID
     */
    public function get_checkout_price_id($bbai_plan) {
        $prices = $this->get_checkout_price_ids();
        $bbai_plan = is_string($bbai_plan) ? sanitize_key($bbai_plan) : '';
        $price_id = $prices[$bbai_plan] ?? '';
        return apply_filters('bbai_checkout_price_id', $price_id, $bbai_plan, $prices);
    }

    /**
     * Resolve the plan key for a Stripe price ID.
     */
    private function get_checkout_plan_from_price_id(string $price_id): string {
        $normalized_price_id = sanitize_text_field($price_id);
        if ($normalized_price_id === '') {
            return '';
        }

        foreach ($this->get_checkout_price_ids() as $plan_id => $mapped_price_id) {
            $plan_id = is_string($plan_id) ? sanitize_key($plan_id) : '';
            $mapped_price_id = is_string($mapped_price_id) ? sanitize_text_field($mapped_price_id) : '';

            if ($plan_id !== '' && $mapped_price_id !== '' && hash_equals($mapped_price_id, $normalized_price_id)) {
                return $plan_id;
            }
        }

        return '';
    }

    /**
     * Resolve the direct Stripe checkout fallback URL for a plan or price ID.
     */
    private function get_checkout_fallback_url(string $plan_id = '', string $price_id = ''): string {
        $normalized_plan_id = sanitize_key($plan_id);
        if ($normalized_plan_id === '' && $price_id !== '') {
            $normalized_plan_id = $this->get_checkout_plan_from_price_id($price_id);
        }

        $fallback_links = apply_filters('bbai_checkout_stripe_links', self::DEFAULT_STRIPE_LINKS);
        if (!is_array($fallback_links) || $normalized_plan_id === '') {
            return '';
        }

        $fallback_url = $fallback_links[$normalized_plan_id] ?? '';
        return is_string($fallback_url) ? esc_url_raw($fallback_url) : '';
    }

    /**
     * Detect Stripe-hosted Checkout Session URLs.
     */
    private function is_stripe_hosted_checkout_session_url(string $url): bool {
        $normalized_url = esc_url_raw($url);
        if ($normalized_url === '') {
            return false;
        }

        $host = wp_parse_url($normalized_url, PHP_URL_HOST);
        $path = wp_parse_url($normalized_url, PHP_URL_PATH);
        if (!is_string($host) || !is_string($path) || $host === '' || $path === '') {
            return false;
        }

        return strtolower($host) === 'checkout.stripe.com' && strpos($path, '/c/pay/') === 0;
    }

    /**
     * Normalize billing API checkout results and fall back when the hosted-session payload is incomplete.
     */
    private function resolve_checkout_url_from_result($result, string $plan_id = '', string $price_id = ''): string {
        if (is_wp_error($result) || !is_array($result)) {
            return '';
        }

        $checkout_url = isset($result['url']) && is_string($result['url'])
            ? esc_url_raw($result['url'])
            : '';
        if ($checkout_url === '') {
            return '';
        }

        $session_id = isset($result['sessionId']) && is_string($result['sessionId'])
            ? sanitize_text_field($result['sessionId'])
            : '';

        if ($this->is_stripe_hosted_checkout_session_url($checkout_url) && $session_id === '') {
            $fallback_url = $this->get_checkout_fallback_url($plan_id, $price_id);
            if ($fallback_url !== '') {
                return $fallback_url;
            }
        }

        return $checkout_url;
    }

    /**
     * Normalize checkout failures into a user-safe message.
     */
    private function get_checkout_error_message($result): string {
        if (!is_wp_error($result)) {
            return __('Unable to create checkout session. Please try again or contact support.', 'beepbeep-ai-alt-text-generator');
        }

        $error_message = $result->get_error_message();
        $error_code = $result->get_error_code();
        $error_message_lower = is_string($error_message) ? strtolower($error_message) : '';

        if ($error_code === 'auth_required' ||
            $error_code === 'license_required' ||
            $error_code === 'invalid_license' ||
            $error_code === 'trial_backend_auth' ||
            $error_code === 'checkout_failed' ||
            strpos($error_message_lower, 'session') !== false ||
            strpos($error_message_lower, 'log in') !== false ||
            strpos($error_message_lower, 'license') !== false) {
            return __('Unable to start hosted checkout right now. Please try again or contact support.', 'beepbeep-ai-alt-text-generator');
        }

        return is_string($error_message) && $error_message !== ''
            ? $error_message
            : __('Unable to create checkout session. Please try again or contact support.', 'beepbeep-ai-alt-text-generator');
    }

    /**
     * Determine whether a checkout redirect target is an allowed Stripe host.
     */
    private function is_allowed_external_checkout_url(string $url): bool {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        return (bool) preg_match('/(^|\\.)stripe\\.com$/', $host);
    }

    /**
     * Redirect to a checkout URL, allowing Stripe-hosted pages.
     */
    private function redirect_to_checkout_url(string $url): void {
        $target_url = esc_url_raw($url);
        if ($target_url === '') {
            wp_die(esc_html__('Invalid checkout URL.', 'beepbeep-ai-alt-text-generator'));
        }

        if ($this->is_allowed_external_checkout_url($target_url)) {
            wp_redirect($target_url);
            exit;
        }

        wp_safe_redirect($target_url);
        exit;
    }

    /**
     * Surface checkout success/error notices in WP Admin
     */
	    public function maybe_render_checkout_notices() {
	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	        $page = $bbai_page_input;
	        if ($page !== 'beepbeep-ai-alt-text-generator') {
	            return;
	        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $checkout_error = isset($_GET['checkout_error']) ? sanitize_text_field(wp_unslash($_GET['checkout_error'])) : '';
        if (!empty($checkout_error)) {
            $message = $checkout_error;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
            $plan_input = isset($_GET['plan']) ? sanitize_key(wp_unslash($_GET['plan'])) : '';
            $valid_plan_ids = array_keys($this->get_checkout_price_ids());
            $bbai_plan = in_array($plan_input, $valid_plan_ids, true) ? $plan_input : '';
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Unable to start checkout', 'beepbeep-ai-alt-text-generator'); ?>:</strong>
                    <?php echo esc_html($message); ?>
                    <?php if ($bbai_plan) : ?>
                        (<?php
                        /* translators: 1: plan name */
                        echo esc_html(sprintf(__('Plan: %s', 'beepbeep-ai-alt-text-generator'), $bbai_plan));
                        ?>)
                    <?php endif; ?>
                </p>
                <p><?php esc_html_e('Please check your account connection and try again. If the problem persists, contact support.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
            $checkout = isset($_GET['checkout']) ? sanitize_key(wp_unslash($_GET['checkout'])) : '';
            if ($checkout === 'success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Redirecting to secure checkout... Complete your payment to unlock up to 1,000 alt text generations per month with Growth.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                <?php
            } elseif ($checkout === 'cancel') {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php esc_html_e('Checkout cancelled. Your plan remains unchanged. Upgrade anytime to unlock 1,000 generations per month with Growth.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                <?php
            }
        }

        // Password reset notices
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $password_reset = isset($_GET['password_reset']) ? sanitize_key(wp_unslash($_GET['password_reset'])) : '';
        if ($password_reset === 'requested') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Email Sent', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Check your email inbox (and spam folder) for password reset instructions. The link will expire in 1 hour.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        } elseif ($password_reset === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Successful', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your password has been updated. You can now sign in with your new password.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        // Subscription update notices
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $subscription_updated = isset($_GET['subscription_updated']) ? sanitize_key(wp_unslash($_GET['subscription_updated'])) : '';
        if (!empty($subscription_updated)) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Subscription Updated', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your subscription information has been refreshed.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $portal_return = isset($_GET['portal_return']) ? sanitize_key(wp_unslash($_GET['portal_return'])) : '';
        if ($portal_return === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Billing Updated', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your billing information has been updated successfully. Changes may take a few moments to reflect.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        }
    }

    public function render_token_notice(){
        if (empty($this->token_notice)){
            return;
        }
        delete_transient('beepbeepai_token_notice');
        delete_transient('bbai_token_notice');
        $total = number_format_i18n($this->token_notice['total'] ?? 0);
        $limit = number_format_i18n($this->token_notice['limit'] ?? 0);
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(sprintf(
            /* translators: 1: tokens used, 2: token threshold */
            __('BeepBeep AI – Alt Text Generator has used %1$s tokens (threshold %2$s). Consider reviewing usage.', 'beepbeep-ai-alt-text-generator'),
            $total,
            $limit
        )) . '</p></div>';
        $this->token_notice = null;
    }

    public function maybe_render_queue_notice(){
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        if (!isset($_GET['bbai_queued'])) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $count = isset($_GET['bbai_queued']) ? absint(wp_unslash($_GET['bbai_queued'])) : 0;
        if ($count <= 0) {
            return;
        }
        $message = $count === 1
            ? __('1 image queued for background optimisation. The alt text will appear shortly.', 'beepbeep-ai-alt-text-generator')
            : sprintf(
                /* translators: 1: number of images queued */
                __('Queued %d images for background optimisation. Alt text will be generated shortly.', 'beepbeep-ai-alt-text-generator'),
                $count
            );
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Display external API compliance modal (WordPress.org requirement).
     * Shows once as a popup after activation to inform users about external service usage.
     * Rendered in admin_footer so it appears as a modal overlay.
     */
    public function maybe_render_external_api_notice() {
        // Only show on plugin admin pages
        $screen = get_current_screen();
        if (!$screen || !isset($screen->id) || !is_string($screen->id)) {
            return;
        }
        $screen_id = (string)$screen->id;
        if (strpos($screen_id, 'bbai') === false && strpos($screen_id, 'ai-alt') === false) {
            return;
        }

        // Migrate legacy option to plugin-prefixed name (site-wide).
        $legacy_dismissed = get_option('wp_alt_text_api_notice_dismissed', null);
        if (null !== $legacy_dismissed) {
            update_option('bbai_api_notice_dismissed', (bool) $legacy_dismissed, false);
            delete_option('wp_alt_text_api_notice_dismissed');
        }

        // Check if modal has been dismissed (site-wide option, shows once for all users)
        $dismissed = get_option('bbai_api_notice_dismissed', false);
        if ($dismissed) {
            return;
        }

        // Show modal popup if not dismissed
        $api_url = 'https://alttext-ai-backend.onrender.com';
        $privacy_url = 'https://oppti.dev/privacy';
        $terms_url = 'https://oppti.dev/terms';
        $nonce = wp_create_nonce('beepbeepai_nonce');
        ?>
        <div id="bbai-api-notice-modal" class="bbai-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="bbai-api-notice-title" aria-describedby="bbai-api-notice-desc">
            <div class="bbai-upgrade-modal__content bbai-api-notice-modal-content">
                <div class="bbai-upgrade-modal__header">
                    <div class="bbai-upgrade-modal__header-content">
                        <h2 id="wp-alt-text-api-notice-title"><?php esc_html_e('External Service Notice', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    </div>
                    <button type="button" class="bbai-modal-close" onclick="bbaiCloseApiNotice();" aria-label="<?php esc_attr_e('Close notice', 'beepbeep-ai-alt-text-generator'); ?>">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="bbai-upgrade-modal__body bbai-api-notice-body" id="bbai-api-notice-desc">
                    <p class="bbai-api-notice-text">
                        <?php esc_html_e('This plugin connects to an external API service to generate alt text. Image data and site/account identifiers may be transmitted for processing, and account/usage data may be stored locally. See the Privacy Policy for details.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>

                    <div class="bbai-api-notice-box">
                        <p class="bbai-api-notice-label">
                            <?php esc_html_e('API Endpoint:', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-api-notice-value">
                            <?php echo esc_html($api_url); ?>
                        </p>

                        <p class="bbai-api-notice-label">
                            <?php esc_html_e('Privacy Policy:', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-api-notice-value">
                            <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener" class="bbai-api-notice-link">
                                <?php echo esc_html($privacy_url); ?>
                            </a>
                        </p>

                        <p class="bbai-api-notice-label">
                            <?php esc_html_e('Terms of Service:', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-api-notice-value">
                            <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener" class="bbai-api-notice-link">
                                <?php echo esc_html($terms_url); ?>
                            </a>
                        </p>
                    </div>
                </div>

                <div class="bbai-upgrade-modal__footer bbai-api-notice-footer">
                    <button type="button" class="button button-primary" onclick="bbaiCloseApiNotice();">
                        <?php esc_html_e('Got it', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function deactivate(){
        wp_clear_scheduled_hook(Queue::CRON_HOOK);
    }

    public function activate() {
        global $wpdb;

        Queue::create_table();
        Queue::schedule_processing(10);
        Debug_Log::create_table();
        update_option('bbai_logs_ready', true, false);

        // Create credit usage table
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::create_table();

        // Create usage logs table for multi-user visualization
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
        \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::create_table();
        
        // Create contact submissions table
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';
        \BeepBeepAI\AltTextGenerator\Contact_Submissions::create_table();
        
        // Schema migrations and indexes are handled by DB_Schema::install()
        // which runs on activation and admin_init upgrade check.

        // Generate site fingerprint (one-time per site)
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
        \BeepBeepAI\AltTextGenerator\Site_Fingerprint::generate();

        // Ensure site identifier exists
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        \BeepBeepAI\AltTextGenerator\get_site_identifier();

        if ( function_exists( 'bbai_telemetry_emit' ) && ! get_option( 'bbai_telemetry_plugin_installed_logged', false ) ) {
            update_option( 'bbai_telemetry_plugin_installed_logged', '1', false );
            bbai_telemetry_emit( 'plugin_installed', [] );
        }

        $bbai_growth_file = BEEPBEEP_AI_PLUGIN_DIR . 'includes/growth/class-bbai-growth-engine.php';
        if ( is_readable( $bbai_growth_file ) ) {
            require_once $bbai_growth_file;
            \BeepBeepAI\AltTextGenerator\Growth_Engine::record_install_timestamp();
        }

        $defaults = [
            'api_url'          => 'https://alttext-ai-backend.onrender.com',
            'model'            => 'gpt-4o-mini',
            'max_words'        => 16,
            'language'         => 'en-GB',
            'language_custom'  => '',
            'enable_on_upload' => true,
            'tone'             => 'professional, accessible',
            'force_overwrite'  => false,
            'token_limit'      => 0,
            'token_alert_sent' => false,
            'dry_run'          => false,
            'custom_prompt'    => '',
            'notify_email'     => get_option('admin_email'),
            'usage'            => $this->default_usage(),
        ];
        $existing = get_option(self::OPTION_KEY, []);
        $updated = wp_parse_args($existing, $defaults);

        // ALWAYS force production API URL
        $updated['api_url'] = 'https://alttext-ai-backend.onrender.com';

        update_option(self::OPTION_KEY, $updated, false);

        // Clear any invalid cached tokens
        delete_option('bbai_jwt_token');
        delete_option('bbai_user_data');
        delete_transient('bbai_token_last_check');

        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)){
            $role->add_cap(self::CAPABILITY);
        }
    }
    
    // create_performance_indexes() and migrate_usage_logs_table() have been
    // consolidated into DB_Schema::install() — see includes/class-bbai-db.php.

    /**
     * Add body class on plugin admin screens that use the dashboard shell so CSS can break out of core .wrap width.
     *
     * @param string $classes Space-separated body classes.
     * @return string
     */
    public function filter_admin_body_class( $classes ) {
        if ( ! is_admin() ) {
            return $classes;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $dashboard_shell_pages = [
            self::MENU_SLUG_DASHBOARD,
            self::MENU_SLUG_LIBRARY,
            'bbai-analytics',
            'bbai-credit-usage',
            'bbai-settings',
            'bbai-guide',
            'bbai-debug',
            'bbai-ui-kit',
            self::MENU_SLUG_ONBOARDING,
        ];
        if ( ! in_array( $page, $dashboard_shell_pages, true ) ) {
            return $classes;
        }
        $classes = is_string( $classes ) ? $classes : '';
        $bbai_body = 'bbai-dashboard';
        if ( 'bbai-ui-kit' === $page ) {
            $bbai_body .= ' bbai-ui-kit-page';
        }
        return trim( $classes . ' ' . $bbai_body );
    }

    public function add_settings_page() {
        $cap = current_user_can(self::CAPABILITY) ? self::CAPABILITY : 'manage_options';

        $bbai_auth = Auth_State::resolve($this->api_client);
        $bbai_has_connected_account = !empty($bbai_auth['has_connected_account']);

        // Top-level menu uses the brand name; the first submenu is "Dashboard".
        add_menu_page(
            __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
            __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
            $cap,
            self::MENU_SLUG_DASHBOARD,
            [$this, 'render_settings_page'],
            'dashicons-format-image',
            30
        );

        // Sidebar submenus are visible only for connected accounts.
        if ($bbai_has_connected_account) {
            // Explicit first submenu replaces the auto-generated duplicate.
            // Order matches the top header nav.
            add_submenu_page(
                self::MENU_SLUG_DASHBOARD,
                __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_DASHBOARD,
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                self::MENU_SLUG_DASHBOARD,
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_LIBRARY,
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('Analytics', 'beepbeep-ai-alt-text-generator'),
                __('Analytics', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-analytics',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('Usage', 'beepbeep-ai-alt-text-generator'),
                __('Usage', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-credit-usage',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('Settings', 'beepbeep-ai-alt-text-generator'),
                __('Settings', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-settings',
                [$this, 'render_settings_page']
            );
        } else {
            // Keep trial link destinations routable without exposing sidebar navigation.
            add_submenu_page(
                '',
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_LIBRARY,
                [$this, 'render_settings_page']
            );

            foreach (['bbai-analytics', 'bbai-credit-usage', 'bbai-settings'] as $bbai_hidden_guest_route) {
                add_submenu_page(
                    '',
                    ucfirst(str_replace(['bbai-', '-'], ['', ' '], $bbai_hidden_guest_route)),
                    ucfirst(str_replace(['bbai-', '-'], ['', ' '], $bbai_hidden_guest_route)),
                    $cap,
                    $bbai_hidden_guest_route,
                    [$this, 'render_settings_page']
                );
            }

            // Remove the auto-generated duplicate submenu under the top-level menu.
            remove_submenu_page(self::MENU_SLUG_DASHBOARD, self::MENU_SLUG_DASHBOARD);
        }

        // Keep utility routes addressable without exposing them in the primary submenu stack.
        add_submenu_page(
            '',
            __('Help', 'beepbeep-ai-alt-text-generator'),
            __('Help', 'beepbeep-ai-alt-text-generator'),
            $cap,
            'bbai-guide',
            [$this, 'render_settings_page']
        );

        if ($this->can_show_debug_logs_tab()) {
            add_submenu_page(
                '',
                __('Debug Logs', 'beepbeep-ai-alt-text-generator'),
                __('Debug Logs', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-debug',
                [$this, 'render_settings_page']
            );
        }

        if ($this->can_show_ui_kit_page()) {
            add_submenu_page(
                '',
                __('UI Kit (dev)', 'beepbeep-ai-alt-text-generator'),
                __('UI Kit (dev)', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-ui-kit',
                [$this, 'render_settings_page']
            );
        }

	        // Prevent PHP 8.1+ strip_tags() null deprecation on hidden pages.
	        add_action('current_screen', static function () {
	            global $title;
	            if ( ! isset( $title ) || $title === null ) {
	                $title = '';
	            }
	        });

	        // Hidden checkout redirect page
	        add_submenu_page(
	            '', // No parent = hidden from menu (avoid PHP 8.1+ deprecations in plugin_basename()).
	            'Checkout',
	            'Checkout',
	            $cap,
	            'bbai-checkout',
	            [$this, 'handle_checkout_redirect']
	        );
	        
	        // Hidden onboarding/guide page (accessible but not shown in menu)
	        add_submenu_page(
	            '', // No parent = hidden from menu
                __('Getting Started', 'beepbeep-ai-alt-text-generator'),
                __('Getting Started', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_ONBOARDING,
                [$this, 'render_bbai_onboarding_step2']
        );
	    }

	    public function handle_checkout_redirect() {
	        // Verify nonce for CSRF protection (first).
	        $action = 'bbai_checkout_redirect';	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), $action ) ) {
	            wp_die(esc_html__('Security check failed.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
	        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $price_id = isset($_GET['price_id']) ? sanitize_text_field(wp_unslash($_GET['price_id'])) : '';
        if (empty($price_id)) {
            wp_die(esc_html__('Invalid checkout request.', 'beepbeep-ai-alt-text-generator'));
        }

        $success_url = admin_url('admin.php?page=bbai&checkout=success');
        $cancel_url = admin_url('admin.php?page=bbai&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);
        $resolved_checkout_url = $this->resolve_checkout_url_from_result($result, '', $price_id);

        if (is_wp_error($result)) {
            $fallback_checkout_url = $this->get_checkout_fallback_url('', $price_id);
            if ($fallback_checkout_url !== '') {
                $this->redirect_to_checkout_url($fallback_checkout_url);
            }

            $error_message = sanitize_text_field($this->get_checkout_error_message($result));
            wp_die(esc_html(sprintf(
                /* translators: 1: error message */
                __('Checkout error: %s', 'beepbeep-ai-alt-text-generator'),
                $error_message
            )));
        }

        if ($resolved_checkout_url !== '') {
            $this->redirect_to_checkout_url($resolved_checkout_url);
        }

        $fallback_checkout_url = $this->get_checkout_fallback_url('', $price_id);
        if ($fallback_checkout_url !== '') {
            $this->redirect_to_checkout_url($fallback_checkout_url);
        }

        wp_die(esc_html__('Failed to create checkout session.', 'beepbeep-ai-alt-text-generator'));
    }

    public function maybe_redirect_to_onboarding(): void {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        if (function_exists('is_network_admin') && is_network_admin()) {
            return;
        }

        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!$this->user_can_manage()) {
            return;
        }

	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	        $bbai_current_page = $bbai_page_input;
	        $is_onboarding_page = ($bbai_current_page === 'bbai-onboarding');
        $is_plugin_page = (!empty($bbai_current_page) && strpos($bbai_current_page, 'bbai') === 0);

        // Never hijack unrelated wp-admin pages. Keep onboarding redirect scoped to plugin screens.
        if (!$is_plugin_page) {
            return;
        }

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Auth_State') || !class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            return;
        }

        $bbai_auth = \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client);
        if (empty($bbai_auth['has_connected_account'])) {
            return;
        }

        $completed = \BeepBeepAI\AltTextGenerator\Onboarding::is_completed();

        if ($completed) {
            // Allow onboarding pages to be viewed even if completed (user may want to revisit the guide)
            // and don't force redirects once setup is done.
            return;
        }

        // Redirect only from the dashboard entrypoint, not every wp-admin page.
        if (!$is_onboarding_page && $bbai_current_page === self::MENU_SLUG_DASHBOARD) {
            wp_safe_redirect(admin_url('admin.php?page=bbai-onboarding'));
            exit;
        }
    }

    public function register_settings() {
        register_setting('bbai_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => function($input){
                $existing = get_option(self::OPTION_KEY, []);
                $input = is_array($input) ? $input : [];
                $out = [];
                // ALWAYS force production API URL - no user input allowed
                $production_url = 'https://alttext-ai-backend.onrender.com';
                $out['api_url'] = $production_url;
                $model = isset($input['model']) ? (string)$input['model'] : 'gpt-4o-mini';
                $out['model'] = $model ? sanitize_text_field($model) : 'gpt-4o-mini';
                $out['max_words']        = max(4, intval($input['max_words'] ?? 16));
                $lang_input_input = isset($input['language']) ? (string)$input['language'] : 'en-GB';
                $lang_input = $lang_input_input ? sanitize_text_field($lang_input_input) : 'en-GB';
                $custom_input_input = isset($input['language_custom']) ? (string)$input['language_custom'] : '';
                $custom_input = $custom_input_input ? sanitize_text_field($custom_input_input) : '';
                if ($lang_input === 'custom'){
                    $out['language'] = $custom_input ?: 'en-GB';
                    $out['language_custom'] = $custom_input;
                } else {
                    $out['language'] = $lang_input ?: 'en-GB';
                    $out['language_custom'] = '';
                }
                $out['enable_on_upload'] = !empty($input['enable_on_upload']);
                $tone = isset($input['tone']) ? (string)$input['tone'] : 'professional, accessible';
                $out['tone'] = $tone ? sanitize_text_field($tone) : 'professional, accessible';
                $out['force_overwrite']  = !empty($input['force_overwrite']);
                $out['token_limit']      = max(0, intval($input['token_limit'] ?? 0));
                if ($out['token_limit'] === 0){
                    $out['token_alert_sent'] = false;
                } elseif (intval($existing['token_limit'] ?? 0) !== $out['token_limit']){
                    $out['token_alert_sent'] = false;
                } else {
                    $out['token_alert_sent'] = !empty($existing['token_alert_sent']);
                }
                $out['dry_run'] = !empty($input['dry_run']);
                $custom_prompt = isset($input['custom_prompt']) ? (string)$input['custom_prompt'] : '';
                $out['custom_prompt'] = $custom_prompt ? wp_kses_post($custom_prompt) : '';
                $notify_input = $input['notify_email'] ?? ($existing['notify_email'] ?? get_option('admin_email'));
                $notify = is_string($notify_input) ? sanitize_text_field($notify_input) : '';
                $out['notify_email'] = $notify && is_email($notify) ? $notify : ($existing['notify_email'] ?? get_option('admin_email'));
                $out['usage']            = $existing['usage'] ?? $this->default_usage();

                return $out;
            }
        ]);
    }

    /**
     * Render Step 1 of onboarding - Welcome page
     */
    private function render_bbai_onboarding_step1() {
        $dashboard_url = admin_url('admin.php?page=bbai');
        $step2_url = admin_url('admin.php?page=bbai-onboarding&step=2');
        ?>
        <div class="wrap bbai-wrap bbai-modern bbai-onboarding" data-bbai-onboarding-step="1">
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <a class="bbai-logo" href="<?php echo esc_url($dashboard_url); ?>">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon" aria-hidden="true">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient-s1)"/>
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient-s1" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('AI-Powered Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </a>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <a class="bbai-nav-link active" href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>">
                            <?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="bbai-container">
                <div class="bbai-page-header bbai-mb-6">
                    <div class="bbai-page-header-content">
                        <h1 class="bbai-page-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('BeepBeep AI will scan your WordPress media library and find images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 1 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Scan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>

                        <div class="bbai-onboarding-features bbai-mt-4">
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Step 1: Scan all uploads', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Find images in your media library that need alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></strong>
                                    <p><?php esc_html_e('Create clear alt text for each image missing it.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Step 3: Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Review the generated results before publishing.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bbai-btn-group bbai-mt-6">
                            <a href="<?php echo esc_url($step2_url); ?>" class="bbai-btn bbai-btn-primary">
                                <?php echo esc_html(bbai_copy_cta_scan_media_library()); ?>
                            </a>
                            <button type="button" class="bbai-btn bbai-btn-secondary" data-bbai-onboarding-action="skip">
                                <?php esc_html_e('Skip setup', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                        <div class="bbai-onboarding-status" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <div class="bbai-grid bbai-grid-3 bbai-mb-6">
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon bbai-onboarding-step-icon--active" aria-hidden="true">
                                <span class="bbai-step-number">1</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                <span class="bbai-step-number">2</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></p>
                        </div>
                    </div>
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                <span class="bbai-step-number">3</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_bbai_onboarding_step2() {
        if (!$this->user_can_manage()) {
            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
        }

	        // Route based on step query param (default to step 1)
	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $step = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : 1;

        if ($step === 1) {
            $this->render_bbai_onboarding_step1();
            return;
        }

        if ($step === 3) {
            $this->render_bbai_onboarding_step3();
            return;
        }

        // Step 2 continues below

        $dashboard_url = admin_url('admin.php?page=bbai');
        $upgrade_url = class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')
            ? \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_upgrade_url()
            : $dashboard_url;
        ?>
        <div class="wrap bbai-wrap bbai-modern bbai-onboarding" data-bbai-onboarding-step="2">
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <a class="bbai-logo" href="<?php echo esc_url($dashboard_url); ?>">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon" aria-hidden="true">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient)"/>
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </a>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php
                        // Only show Dashboard tab if onboarding is completed
                        if (\BeepBeepAI\AltTextGenerator\Onboarding::is_completed()) :
                        ?>
                            <a class="bbai-nav-link" href="<?php echo esc_url($dashboard_url); ?>">
                                <?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="bbai-nav-link active" href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>">
                            <?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="bbai-container">
                <div class="bbai-page-header bbai-mb-6">
                    <div class="bbai-page-header-content">
                        <h1 class="bbai-page-title"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Use the scan results to create AI descriptions for images that are missing ALT text. You stay in control—review before anything goes live.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 2 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <p class="bbai-card-subtitle bbai-mb-0"><?php esc_html_e('When you continue, we queue ALT text generation for images that need it. Progress appears below; then you can review results in the next step.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider bbai-mt-4" aria-hidden="true"></div>
                        <div class="bbai-btn-group bbai-mt-4">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-bbai-onboarding-action="start-scan">
                                <?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?>
                            </button>
                            <button type="button" class="bbai-btn bbai-btn-secondary" data-bbai-onboarding-action="skip">
                                <?php esc_html_e('Skip setup', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                        <div class="bbai-onboarding-scan-meta bbai-mt-4" data-bbai-scan-meta hidden>
                            <p class="bbai-onboarding-scan-count" data-bbai-scan-count><?php esc_html_e('Queuing 0 images', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <p class="bbai-onboarding-scan-time" data-bbai-scan-time><?php esc_html_e('Estimated time: ~0 seconds', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <div class="bbai-onboarding-scan-progress bbai-mt-3" data-bbai-scan-progress hidden>
                            <div class="bbai-onboarding-scan-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e('Generation progress', 'beepbeep-ai-alt-text-generator'); ?>">
                                <span class="bbai-onboarding-scan-progress-fill" data-bbai-scan-progress-fill style="width: 0%;"></span>
                            </div>
                            <p class="bbai-onboarding-scan-progress-text" data-bbai-scan-progress-text><?php esc_html_e('Preparing generation...', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <p class="bbai-onboarding-reassurance"><?php esc_html_e('Only images missing ALT text are queued. You can edit or reject suggestions before publishing.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-onboarding-helper"><?php esc_html_e('Up to 500 images are included in this first run. Run again anytime from the Dashboard.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-status" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <div class="bbai-mb-6">
                    <h2 class="bbai-section-title"><?php esc_html_e('Your setup path', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <div class="bbai-grid bbai-grid-3 bbai-gap-4">
                        <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                            <div class="bbai-card-body">
                                <span class="bbai-onboarding-step-icon bbai-onboarding-step-icon--done" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                        <circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="2" />
                                        <path d="M20 20l-4.2-4.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </span>
                                <h3 class="bbai-card-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <p class="bbai-onboarding-step-text"><?php esc_html_e('Done — we know which images need ALT text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                            <div class="bbai-card-body">
                                <span class="bbai-onboarding-step-icon bbai-onboarding-step-icon--active" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                        <path d="M12 3l1.6 3.6L17 8l-3.4 1.4L12 13l-1.6-3.6L7 8l3.4-1.4L12 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <h3 class="bbai-card-title"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></h3>
                                <p class="bbai-onboarding-step-text"><?php esc_html_e('Create descriptions for the images your scan flagged.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                            <div class="bbai-card-body">
                                <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                        <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2" />
                                        <path d="M8.5 12.5l2.5 2.5 4.5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <h3 class="bbai-card-title"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <p class="bbai-onboarding-step-text"><?php esc_html_e('Check wording, then save when you are happy.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--compact bbai-onboarding-upsell">
                    <div class="bbai-card-body bbai-onboarding-upsell-body">
                        <div>
                            <h3 class="bbai-card-title"><?php esc_html_e('Want faster processing?', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-card-subtitle"><?php esc_html_e('Upgrade to Growth for priority queue and bulk optimisation across your full media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <button type="button" class="bbai-btn bbai-btn-outline-primary" data-action="show-upgrade-modal" data-upgrade-trigger="true">
                            <?php esc_html_e('View plans & pricing', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Include upgrade modal for "View plans & pricing" button.
        $bbai_checkout_prices = $this->get_checkout_price_ids();
        $bbai_upgrade_modal = dirname(__DIR__) . '/templates/upgrade-modal.php';
        if (file_exists($bbai_upgrade_modal)) {
            include $bbai_upgrade_modal;
        }
    }

    /**
     * Render Onboarding Step 3 - Review ready screen
     * Shown after scan started or skip, marks onboarding completed on view.
     */
    public function render_bbai_onboarding_step3() {
        if (!$this->user_can_manage()) {
            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
        }

        // Note: Onboarding completion is now triggered via AJAX when queue finishes
        // (pending + processing === 0). This prevents premature completion while work is running.
        // The JS calls bbai_complete_onboarding when stats show queue is empty.

        $dashboard_url = admin_url('admin.php?page=bbai');
        $library_url = admin_url('admin.php?page=' . self::MENU_SLUG_LIBRARY);
        $library_needs_review_url = function_exists('bbai_alt_library_needs_review_url') ? bbai_alt_library_needs_review_url() : $library_url;
        $bbai_is_authenticated = bbai_is_authenticated();

        // Mark onboarding complete as soon as Step 3 is reached to prevent redirect loops.
        if ($bbai_is_authenticated && class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            \BeepBeepAI\AltTextGenerator\Onboarding::mark_completed();
            \BeepBeepAI\AltTextGenerator\Onboarding::update_last_seen();
        }
        ?>
        <div class="wrap bbai-wrap bbai-modern bbai-onboarding bbai-onboarding-step3" data-bbai-onboarding-step="3">
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <a class="bbai-logo" href="<?php echo esc_url($dashboard_url); ?>">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon" aria-hidden="true">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient-s3)"/>
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient-s3" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </a>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php
                        // Only show Dashboard tab if onboarding is completed
                        if (\BeepBeepAI\AltTextGenerator\Onboarding::is_completed()) :
                        ?>
                            <a class="bbai-nav-link" href="<?php echo esc_url($dashboard_url); ?>">
                                <?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="bbai-nav-link active" href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>">
                            <?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="bbai-container">
                <div class="bbai-page-header bbai-mb-6">
                    <div class="bbai-page-header-content">
                        <h1 class="bbai-page-title"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Open the review workspace to check generated ALT text, tweak wording, and save when you are ready to publish.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 3 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Review', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$bbai_is_authenticated) : ?>
                <!-- Unauthenticated state: Show sign-in CTA -->
                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php esc_html_e('Sign in to start generating', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e('Sign in to start generating and reviewing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>
                        <div class="bbai-btn-group bbai-mt-4">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                                <?php esc_html_e('Sign in', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                            <button type="button" class="bbai-btn bbai-btn-outline-primary" data-action="show-upgrade-modal" data-upgrade-trigger="true">
                                <?php esc_html_e('View plans & pricing', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                <!-- Authenticated state: Show queue stats and CTAs -->
                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <p class="bbai-card-subtitle bbai-mb-0"><?php esc_html_e('Your images are being processed. When the queue clears, everything below is up to date. Use the review workspace to approve or edit ALT text before it goes live.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider bbai-mt-4" aria-hidden="true"></div>

                        <!-- KPI Stats Row -->
                        <div class="bbai-onboarding-kpi-row bbai-mt-4" data-bbai-step3-stats>
                            <div class="bbai-onboarding-kpi">
                                <span class="bbai-onboarding-kpi-label"><?php esc_html_e('In queue', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-onboarding-kpi-value" data-stat="queued">
                                    <span class="bbai-onboarding-kpi-loading">&hellip;</span>
                                </span>
                            </div>
                            <div class="bbai-onboarding-kpi">
                                <span class="bbai-onboarding-kpi-label"><?php esc_html_e('Processed', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-onboarding-kpi-value" data-stat="processed">
                                    <span class="bbai-onboarding-kpi-loading">&hellip;</span>
                                </span>
                            </div>
                            <div class="bbai-onboarding-kpi bbai-onboarding-kpi--errors" data-stat-errors-wrapper style="display: none;">
                                <span class="bbai-onboarding-kpi-label"><?php esc_html_e('Errors', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-onboarding-kpi-value" data-stat="errors">0</span>
                            </div>
                        </div>

                        <div class="bbai-btn-group bbai-mt-4">
                            <a href="<?php echo esc_url($library_needs_review_url); ?>" class="bbai-btn bbai-btn-primary" data-bbai-navigation="review-results">
                                <?php esc_html_e('Open review workspace', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                            <a href="<?php echo esc_url($dashboard_url); ?>" class="bbai-btn bbai-btn-secondary">
                                <?php esc_html_e('Go to Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bbai-card bbai-card--compact bbai-mb-6">
                    <div class="bbai-card-body">
                        <h3 class="bbai-card-title"><?php esc_html_e('Example result', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <div class="bbai-onboarding-result-example">
                            <div class="bbai-onboarding-result-example__item">
                                <p class="bbai-onboarding-result-example__label"><?php esc_html_e('Before', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <p class="bbai-onboarding-result-example__filename">image.jpg</p>
                                <p class="bbai-onboarding-result-example__alt"><?php esc_html_e('ALT: empty', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                            <div class="bbai-onboarding-result-example__item bbai-onboarding-result-example__item--after">
                                <p class="bbai-onboarding-result-example__label"><?php esc_html_e('After', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <p class="bbai-onboarding-result-example__generated"><?php esc_html_e('Generated alt text example', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- What happens next card -->
                <div class="bbai-card bbai-card--compact bbai-mb-6">
                    <div class="bbai-card-body">
                        <h3 class="bbai-card-title"><?php esc_html_e('What happens next', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <ul class="bbai-onboarding-next-steps">
                            <li><?php esc_html_e('We generate drafts for images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('You review and edit anything before publishing.', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('You can re-run scans anytime from the Dashboard.', 'beepbeep-ai-alt-text-generator'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Upsell banner -->
                <div class="bbai-card bbai-card--compact bbai-onboarding-upsell">
                    <div class="bbai-card-body bbai-onboarding-upsell-body">
                        <div>
                            <h3 class="bbai-card-title"><?php esc_html_e('Want faster processing?', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-card-subtitle"><?php esc_html_e('Upgrade to Growth for priority queue and bulk optimisation across your full media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <button type="button" class="bbai-btn bbai-btn-outline-primary" data-action="show-upgrade-modal" data-upgrade-trigger="true">
                            <?php esc_html_e('View plans & pricing', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Include upgrade modal for "View plans & pricing" button.
        $bbai_checkout_prices = $this->get_checkout_price_ids();
        $bbai_upgrade_modal = dirname(__DIR__) . '/templates/upgrade-modal.php';
        if (file_exists($bbai_upgrade_modal)) {
            include $bbai_upgrade_modal;
        }
    }

    public function render_settings_page() {
        if (!$this->user_can_manage()) return;

        // Allocate free credits on first dashboard view so usage displays correctly
        Usage_Tracker::allocate_free_credits_if_needed();

        $opts  = get_option(self::OPTION_KEY, []);
        $bbai_stats = $this->get_media_stats();
        $nonce = wp_create_nonce(self::NONCE_KEY);
        
	        $bbai_auth = \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client);
	        $bbai_is_authenticated = !empty($bbai_auth['is_authenticated']);
	        $bbai_has_license = !empty($bbai_auth['has_license']);
	        $bbai_has_stored_token = !empty($bbai_auth['has_stored_token']);
	        $bbai_has_stored_license = !empty($bbai_auth['has_stored_license']);
	        $bbai_has_registered_user = !empty($bbai_auth['has_registered_user']);
            $bbai_has_connected_account = !empty($bbai_auth['has_connected_account']);
            $bbai_is_anonymous_trial = !empty($bbai_auth['is_anonymous_trial']);
            $bbai_auth_state = isset($bbai_auth['auth_state']) ? (string) $bbai_auth['auth_state'] : ($bbai_is_anonymous_trial ? 'anonymous' : 'authenticated');
		        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		        $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		        $bbai_page_slug = $bbai_page_input;

        if ($bbai_page_slug === 'bbai-ui-kit' && ! $this->can_show_ui_kit_page()) {
            wp_die(
                esc_html__('You do not have permission to view the UI Kit preview.', 'beepbeep-ai-alt-text-generator'),
                esc_html__('Forbidden', 'beepbeep-ai-alt-text-generator'),
                ['response' => 403]
            );
        }
        
        // Primary nav is limited to the product workflow. Utility routes stay accessible but hidden.
        $bbai_tabs = [];
        $bbai_active_nav_tab = '';
        $bbai_help_is_active = false;
        $bbai_settings_section = 'general';
        
        // Anonymous / trial users: keep them inside the real product shell with a reduced nav.
        if ($bbai_is_anonymous_trial) {
            $bbai_tabs = [
                'dashboard' => __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                'library'   => __('ALT Library', 'beepbeep-ai-alt-text-generator'),
            ];
            $bbai_allowed_tabs = $bbai_tabs;
            $bbai_page_to_tab = [
                'bbai'           => 'dashboard',
                'bbai-library'   => 'library',
            ];
            $bbai_tab_aliases = [
                'credit-usage' => 'dashboard',
            ];
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
            $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
            $bbai_current_page = $bbai_page_input ?: 'bbai';
            $tab_from_page = $bbai_page_to_tab[$bbai_current_page] ?? 'dashboard';
            $tab_from_page = $bbai_tab_aliases[$tab_from_page] ?? $tab_from_page;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
            $bbai_tab_input = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
            $bbai_requested_tab = $bbai_tab_input !== '' ? $bbai_tab_input : $tab_from_page;
            $bbai_requested_tab = $bbai_tab_aliases[$bbai_requested_tab] ?? $bbai_requested_tab;
            $bbai_route_is_allowed = isset($bbai_page_to_tab[$bbai_current_page]) && in_array($bbai_requested_tab, array_keys($bbai_allowed_tabs), true);
            if (!$bbai_route_is_allowed) {
                wp_safe_redirect(admin_url('admin.php?page=bbai'));
                exit;
            }
            $bbai_tab = $bbai_requested_tab;
            $bbai_is_pro_for_admin = false;
            $bbai_is_agency_for_admin = false;
            $bbai_can_show_debug_tab = false;
            $bbai_active_nav_tab = in_array($bbai_tab, ['dashboard', 'library'], true) ? $bbai_tab : '';
            $bbai_help_is_active = false;
            $bbai_settings_section = 'general';
        } else {
            // Determine if agency license
            // Check API first, then include stored credentials
            $bbai_has_license_api = $this->api_client->has_active_license();
            $bbai_has_license = $bbai_has_license_api || $bbai_has_stored_license;
            $bbai_license_data = $this->api_client->get_license_data();
            $bbai_plan_slug = 'free'; // Default to free
            
            // If using license, check license plan
            if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization'])) {
                $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
                if ($bbai_license_plan !== 'free') {
                    $bbai_plan_slug = $bbai_license_plan;
                }
            } elseif ($bbai_is_authenticated) {
                // For authenticated users without license, try to get plan from usage stats
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $bbai_usage_stats = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display(false);
                if (isset($bbai_usage_stats['plan']) && $bbai_usage_stats['plan'] !== 'free') {
                    $bbai_plan_slug = $bbai_usage_stats['plan'];
                }
            }
            
            $bbai_is_agency = ($bbai_plan_slug === 'agency');
            $bbai_is_pro = ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'agency');

            // Visible primary navigation only includes the main product workflow areas.
            $bbai_tabs = [
                'dashboard' => __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                'library'   => __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                'analytics' => __('Analytics', 'beepbeep-ai-alt-text-generator'),
                'usage'     => __('Usage', 'beepbeep-ai-alt-text-generator'),
                'settings'  => __('Settings', 'beepbeep-ai-alt-text-generator'),
            ];

            $bbai_can_show_debug_tab = $this->can_show_debug_logs_tab();
            $bbai_allowed_tabs = $bbai_tabs + [
                'help' => __('Help', 'beepbeep-ai-alt-text-generator'),
            ];

            if ($bbai_can_show_debug_tab) {
                $bbai_allowed_tabs['debug'] = __('Debug Logs', 'beepbeep-ai-alt-text-generator');
            }

            // Keep internal/admin routes available without promoting them into primary nav.
            if ($bbai_is_pro) {
                $bbai_allowed_tabs['admin'] = __('Admin', 'beepbeep-ai-alt-text-generator');
            }

            if ($bbai_is_agency) {
                $bbai_allowed_tabs['agency-overview'] = __('Agency Overview', 'beepbeep-ai-alt-text-generator');
            }

            $bbai_can_show_ui_kit_page = $this->can_show_ui_kit_page();
            if ($bbai_can_show_ui_kit_page) {
                $bbai_allowed_tabs['ui-kit'] = __('UI Kit (dev)', 'beepbeep-ai-alt-text-generator');
            }

            // Normalize legacy page slugs and tab params into the simplified nav model.
            $bbai_page_to_tab = [
                'bbai'              => 'dashboard',
                'bbai-library'      => 'library',
                'bbai-analytics'    => 'analytics',
                'bbai-credit-usage' => 'usage',
                'bbai-guide'        => 'help',
                'bbai-settings'     => 'settings',
                'bbai-debug'        => 'debug',
                'bbai-agency-overview' => 'agency-overview',
                'bbai-ui-kit'       => 'ui-kit',
            ];
            $bbai_tab_aliases = [
                'credit-usage' => 'usage',
                'guide'        => 'help',
            ];
            
	            // Determine current tab from URL
	            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	            $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
	            $bbai_current_page = $bbai_page_input ?: 'bbai';
	            $tab_from_page = $bbai_page_to_tab[$bbai_current_page] ?? 'dashboard';
                $tab_from_page = $bbai_tab_aliases[$tab_from_page] ?? $tab_from_page;
	            
	            // Use tab from URL parameter if provided, otherwise use page slug mapping
	            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	            $bbai_tab_input = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
	            $bbai_requested_tab = $bbai_tab_input !== '' ? $bbai_tab_input : $tab_from_page;
                $bbai_requested_tab = $bbai_tab_aliases[$bbai_requested_tab] ?? $bbai_requested_tab;

                if ('debug' === $bbai_requested_tab && $bbai_can_show_debug_tab) {
                    $bbai_tab = 'settings';
                    $bbai_settings_section = 'debug';
                } else {
                    $bbai_tab = $bbai_requested_tab;
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
                $bbai_section_input = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
                if ('settings' === $bbai_tab && 'debug' === $bbai_section_input && $bbai_can_show_debug_tab) {
                    $bbai_settings_section = 'debug';
                }

            // If trying to access restricted tabs, redirect to dashboard
            if (!isset($bbai_allowed_tabs) || !is_array($bbai_allowed_tabs) || !in_array($bbai_requested_tab, array_keys($bbai_allowed_tabs), true)) {
                $bbai_tab = 'dashboard';
                $bbai_settings_section = 'general';
            }

            $bbai_active_nav_map = [
                'dashboard'       => 'dashboard',
                'library'         => 'library',
                'analytics'       => 'analytics',
                'usage'           => 'usage',
                'settings'        => 'settings',
                'debug'           => 'settings',
                'admin'           => 'settings',
                'agency-overview' => 'settings',
            ];
            $bbai_active_nav_tab = $bbai_active_nav_map[$bbai_requested_tab] ?? ($bbai_active_nav_map[$bbai_tab] ?? '');
            $bbai_help_is_active = ('help' === $bbai_requested_tab);
            
            // Set variables for Admin tab access (used later in template)
            $bbai_is_pro_for_admin = $bbai_is_pro;
            $bbai_is_agency_for_admin = $bbai_is_agency;
        }
        $bbai_export_url = wp_nonce_url(admin_url('admin-post.php?action=beepbeepai_usage_export'), 'bbai_usage_export');
        $bbai_audit_rows = $bbai_stats['audit'] ?? [];
        $bbai_debug_bootstrap = $this->get_debug_bootstrap();
        ?>
        <div class="wrap bbai-wrap bbai-modern">
            <!-- Dark Header -->
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <div class="bbai-logo">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient)"/>
                            <!-- AI/Sparkle icon representing intelligence -->
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <!-- Image frame representing image optimization -->
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($bbai_tabs)) : ?>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <div class="bbai-nav__primary">
                        <?php
                        if (!isset($bbai_tabs) || !is_array($bbai_tabs)) {
                            $bbai_tabs = [];
                        }

                        $bbai_tab_to_page = [
                            'dashboard' => 'bbai',
                            'library'   => 'bbai-library',
                            'analytics' => 'bbai-analytics',
                            'usage'     => 'bbai-credit-usage',
                            'settings'  => 'bbai-settings',
                        ];

                        foreach ($bbai_tabs as $slug => $label) :
                            $page_slug = $bbai_tab_to_page[$slug] ?? 'bbai';
                            $url = admin_url('admin.php?page=' . $page_slug);
                            $active = (isset($bbai_active_nav_tab) && $bbai_active_nav_tab === $slug) ? ' active' : '';
                        ?>
                            <a href="<?php echo esc_url($url); ?>" class="bbai-nav-link<?php echo esc_attr($active); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                        <?php if ($bbai_has_connected_account) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-guide')); ?>" class="bbai-header-guide-link<?php echo !empty($bbai_help_is_active) ? ' active' : ''; ?>" title="<?php esc_attr_e('Help and troubleshooting', 'beepbeep-ai-alt-text-generator'); ?>"<?php echo !empty($bbai_help_is_active) ? ' aria-current="page"' : ''; ?>>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 11.5V11M8 9C8 7.5 9.5 7.5 9.5 6C9.5 5.17 8.83 4.5 8 4.5C7.17 4.5 6.5 5.17 6.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span class="bbai-header-guide-text"><?php esc_html_e('Help', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                    <!-- Auth & Subscription Actions -->
                    <div class="bbai-header-actions">
                        <?php
                        if ($bbai_has_connected_account) :
                            $bbai_usage_stats = Usage_Tracker::get_stats_display();
                            $bbai_account_summary = $bbai_is_authenticated ? $this->get_account_summary($bbai_usage_stats) : null;
                            $bbai_plan_slug  = $bbai_usage_stats['plan'] ?? 'free';
                            $plan_label = isset($bbai_usage_stats['plan_label']) ? (string)$bbai_usage_stats['plan_label'] : ucfirst($bbai_plan_slug);
                            $connected_email = isset($bbai_account_summary['email']) ? (string)$bbai_account_summary['email'] : '';
                            $billing_portal = Usage_Tracker::get_billing_portal_url();

                            // If license-only mode (no personal login), show license info
                            if ($bbai_has_license && !$bbai_is_authenticated) {
                                $bbai_license_data = $this->api_client->get_license_data();
                                $org_name = isset($bbai_license_data['organization']['name']) ? (string)$bbai_license_data['organization']['name'] : '';
                                $connected_email = $org_name ?: __('License Active', 'beepbeep-ai-alt-text-generator');
                            }
                        ?>
                            <!-- Compact Account Bar in Header -->
                            <div class="bbai-header-account-bar">
                                <span class="bbai-header-account-email"><?php echo esc_html(is_string($connected_email) ? $connected_email : __('Connected', 'beepbeep-ai-alt-text-generator')); ?></span>
                                <span class="bbai-header-plan-badge"><?php echo esc_html(is_string($plan_label) ? $plan_label : ucfirst($bbai_plan_slug ?? 'free')); ?></span>
                                <?php if ($bbai_plan_slug === 'free' && !$bbai_has_license && $bbai_tab !== 'dashboard') : ?>
                                    <button type="button" class="bbai-header-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php elseif (!empty($billing_portal) && $bbai_is_authenticated) : ?>
                                    <button type="button" class="bbai-header-manage-btn" data-action="open-billing-portal">
                                        <?php esc_html_e('Manage', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($bbai_is_authenticated || $bbai_has_license) : ?>
                                <button type="button" class="bbai-header-logout-btn" data-action="logout">
                                    <?php esc_html_e('Logout', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <?php
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing for login fallback URL.
                            $bbai_header_auth_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
                            if ('' === $bbai_header_auth_page || 0 !== strpos($bbai_header_auth_page, 'bbai')) {
                                $bbai_header_auth_page = 'bbai';
                            }
                            $bbai_header_signup_href = add_query_arg(
                                'bbai_open_auth',
                                '1',
                                admin_url('admin.php?page=' . $bbai_header_auth_page)
                            );
                            $bbai_header_login_href = add_query_arg(
                                'bbai_open_auth',
                                '1',
                                admin_url('admin.php?page=' . $bbai_header_auth_page)
                            );
                            ?>
                            <a
                                href="<?php echo esc_url($bbai_header_signup_href); ?>"
                                class="bbai-header-upgrade-btn"
                                role="button"
                                data-action="show-auth-modal"
                                data-auth-tab="register"
                            >
                                <span><?php esc_html_e('Create free account', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </a>
                            <a
                                href="<?php echo esc_url($bbai_header_login_href); ?>"
                                class="bbai-header-login-btn"
                                role="button"
                                data-action="show-auth-modal"
                                data-auth-tab="login"
                            >
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
                                    <path d="M10 14H13C13.5523 14 14 13.5523 14 13V3C14 2.44772 13.5523 2 13 2H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M5 11L2 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 8H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span><?php esc_html_e('Login', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </a>
                        <?php endif; ?>
                </div>
                </div>
            </div>
            
            <!-- Main Content Container - uniform width across all tabs -->
            <div class="bbai-container bbai-content-shell<?php echo ( isset( $bbai_tab ) && $bbai_tab === 'dashboard' ) ? ' bbai-dashboard-shell' : ''; ?>">

            <?php
            // Ensure usage stats for banner when on dashboard.
            if (
                ( $bbai_tab === 'dashboard' || $bbai_tab === 'library' || $bbai_tab === 'analytics' || $bbai_tab === 'usage' ) &&
                ( ! isset( $bbai_usage_stats ) || ! is_array( $bbai_usage_stats ) )
            ) {
                $bbai_usage_stats = Usage_Helper::get_usage($this->api_client, (bool) $bbai_has_connected_account);
            }
            // Usage limit banner - dashboard only, when monthly limit reached.
            $bbai_banner_limit_reached = false;
            if (
                $bbai_tab === 'dashboard' &&
                isset( $bbai_usage_stats ) &&
                is_array( $bbai_usage_stats ) &&
                $bbai_has_connected_account
            ) {
                $bbai_banner_used = max( 0, (int) ( $bbai_usage_stats['used'] ?? 0 ) );
                $bbai_banner_limit = max( 1, (int) ( $bbai_usage_stats['limit'] ?? 50 ) );
                $bbai_banner_remaining = isset( $bbai_usage_stats['remaining'] )
                    ? max( 0, (int) $bbai_usage_stats['remaining'] )
                    : max( 0, $bbai_banner_limit - $bbai_banner_used );
                $bbai_banner_limit_reached = ( $bbai_banner_remaining <= 0 );
            }
            /* Banner is rendered inside dashboard-body.php when on dashboard tab */
            ?>

            <?php if ($bbai_tab === 'dashboard') : ?>
    <?php
    $bbai_dashboard_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-tab.php';
    bbai_render_layout_template(
        $bbai_dashboard_partial,
        get_defined_vars(),
        __('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>

<?php elseif ($bbai_tab === 'library' && ($bbai_has_connected_account || $bbai_is_anonymous_trial)) : ?>
    <?php
    $bbai_library_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-tab.php';
    bbai_render_layout_template(
        $bbai_library_partial,
        get_defined_vars(),
        __('Library content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>

<?php elseif ($bbai_tab === 'help' || $bbai_page_slug === 'bbai-guide') : ?>
            <?php
            $bbai_guide_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/guide-tab.php';
            bbai_render_layout_template(
                $bbai_guide_partial,
                get_defined_vars(),
                __('Help content unavailable.', 'beepbeep-ai-alt-text-generator'),
                $this
            );
            ?>

<?php elseif ($bbai_tab === 'usage' && ($bbai_is_authenticated || $bbai_has_license)) : ?>
    <?php
    $bbai_credit_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/credit-usage-tab.php';
    bbai_render_layout_template(
        $bbai_credit_partial,
        get_defined_vars(),
        __('Usage content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>
<?php elseif ($bbai_tab === 'agency-overview' && $bbai_is_agency) : ?>
    <?php
    $bbai_agency_overview_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/agency-overview-tab.php';
    if (file_exists($bbai_agency_overview_partial)) {
        include $bbai_agency_overview_partial;
    } else {
        esc_html_e('Agency overview content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>
<?php elseif ($bbai_tab === 'analytics' && ($bbai_is_authenticated || $bbai_has_license)) : ?>
    <?php
    // Ensure usage_stats is available for analytics tab
    if (!isset($bbai_usage_stats)) {
        $bbai_usage_stats = Usage_Tracker::get_stats_display();
    }
    $bbai_analytics_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/analytics-tab.php';
    bbai_render_layout_template(
        $bbai_analytics_partial,
        get_defined_vars(),
        __('Analytics content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>

<?php elseif ($bbai_tab === 'ui-kit' && $this->can_show_ui_kit_page()) : ?>
    <?php
    $bbai_ui_kit_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/ui-kit-tab.php';
    if (file_exists($bbai_ui_kit_partial)) {
        include $bbai_ui_kit_partial;
    } else {
        esc_html_e('UI Kit preview is unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>

<?php elseif ($bbai_tab === 'settings') : ?>
            <?php
            $bbai_settings_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-tab.php';
            bbai_render_layout_template(
                $bbai_settings_partial,
                get_defined_vars(),
                __('Settings content unavailable.', 'beepbeep-ai-alt-text-generator'),
                $this
            );
            ?>

            <?php elseif ($bbai_tab === 'admin' && $bbai_is_pro_for_admin) : ?>
            <!-- Admin Tab - Debug Logs and Settings for Pro and Agency -->
            <?php
            // Check if user is authenticated via API (JWT token or license) OR has admin session
            $api_authenticated = $this->api_client->is_authenticated();
            $has_active_license = $this->api_client->has_active_license();
            $admin_session_authenticated = $this->is_admin_authenticated();

            // Grant access if authenticated via any method
            $admin_authenticated = $api_authenticated || $has_active_license || $admin_session_authenticated;
            ?>
            <?php if (!$admin_authenticated) : ?>
                <!-- Admin Login Required -->
                <div class="bbai-admin-login">
                    <div class="bbai-admin-login-content">
                        <div class="bbai-admin-login-header">
                            <h2 class="bbai-admin-login-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="margin-right: 12px; vertical-align: middle;">
                                    <path d="M12 1L23 12L12 23L1 12L12 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="12" cy="12" r="3" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Access', 'beepbeep-ai-alt-text-generator'); ?>
                            </h2>
                            <p class="bbai-admin-login-subtitle">
                                <?php 
                                if ($bbai_is_agency_for_admin) {
                                    esc_html_e('Enter your agency credentials to access Debug Logs and Settings.', 'beepbeep-ai-alt-text-generator');
                                } else {
                                    esc_html_e('Enter your pro credentials to access Debug Logs and Settings.', 'beepbeep-ai-alt-text-generator');
                                }
                                ?>
                            </p>
                        </div>
                        
                        <form id="bbai-admin-login-form" class="bbai-admin-login-form">
                            <div id="bbai-admin-login-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                            
                            <div class="bbai-admin-login-field">
                                <label for="admin-login-email" class="bbai-admin-login-label">
                                    <?php esc_html_e('Email', 'beepbeep-ai-alt-text-generator'); ?>
                                </label>
                                <input type="email" 
                                       id="admin-login-email" 
                                       name="email" 
                                       class="bbai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('your-email@example.com', 'beepbeep-ai-alt-text-generator'); ?>"
                                       required>
                            </div>
                            
                            <div class="bbai-admin-login-field">
                                <label for="admin-login-password" class="bbai-admin-login-label">
                                    <?php esc_html_e('Password', 'beepbeep-ai-alt-text-generator'); ?>
                                </label>
                                <input type="password" 
                                       id="admin-login-password" 
                                       name="password" 
                                       class="bbai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('Enter your password', 'beepbeep-ai-alt-text-generator'); ?>"
                                       required>
                            </div>
                            
                            <button type="submit" id="admin-login-submit-btn" class="bbai-admin-login-btn">
                                <span class="bbai-btn__text"><?php esc_html_e('Log In', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-btn__spinner" style="display: none;">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2" stroke-dasharray="43.98" stroke-dashoffset="10.99" fill="none" opacity="0.5">
                                            <animateTransform attributeName="transform" type="rotate" from="0 8 8" to="360 8 8" dur="1s" repeatCount="indefinite"/>
                                        </circle>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else : ?>
                <!-- Admin Content: Debug Logs and Settings -->
                <?php $bbai_can_show_admin_debug = $this->can_show_debug_logs_tab(); ?>
                <div class="bbai-admin-content">
                    <!-- Admin Header with Logout -->
                    <div class="bbai-admin-header">
                        <div class="bbai-admin-header-info">
                            <h2 class="bbai-admin-header-title">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="margin-right: 10px; vertical-align: middle;">
                                    <path d="M10 1L19 10L10 19L1 10L10 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="10" cy="10" r="2.5" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Panel', 'beepbeep-ai-alt-text-generator'); ?>
                            </h2>
                            <p class="bbai-admin-header-subtitle">
                                <?php
                                if ($bbai_can_show_admin_debug) {
                                    esc_html_e('Debug Logs and Settings', 'beepbeep-ai-alt-text-generator');
                                } else {
                                    esc_html_e('Settings', 'beepbeep-ai-alt-text-generator');
                                }
                                ?>
                            </p>
                        </div>
                        <button type="button" class="bbai-admin-logout-btn" id="bbai-admin-logout-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M10 11L13 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Log Out', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <!-- Admin Tabs Navigation -->
                    <div class="bbai-admin-tabs">
                        <?php if ($bbai_can_show_admin_debug) : ?>
                            <button type="button" class="bbai-admin-tab active" data-admin-tab="debug">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="bbai-admin-tab <?php echo esc_attr($bbai_can_show_admin_debug ? '' : 'active'); ?>" data-admin-tab="settings">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <?php if ($bbai_can_show_admin_debug) : ?>
                        <!-- Debug Logs Section -->
                        <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="debug">
                            <div class="bbai-admin-section-header">
                                <h3 class="bbai-admin-section-title"><?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            </div>
                            <?php
                            $bbai_debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
                            if (file_exists($bbai_debug_partial)) {
                                include $bbai_debug_partial;
                            } else {
                                esc_html_e('Debug content unavailable.', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Settings Section -->
                    <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="settings" style="<?php echo esc_attr($bbai_can_show_admin_debug ? 'display: none;' : 'display: block;'); ?>">
                        <div class="bbai-admin-section-header">
                            <h3 class="bbai-admin-section-title"><?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        </div>
                        <?php
                        $bbai_settings_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-tab.php';
                        if (file_exists($bbai_settings_partial)) {
                            include $bbai_settings_partial;
                        } else {
                            esc_html_e('Settings content unavailable.', 'beepbeep-ai-alt-text-generator');
                        }
                        ?>
                    </div>
            <?php endif; ?>
            </div><!-- .bbai-container -->
            
            <!-- Footer -->
            <div class="bbai-footer">
                <?php esc_html_e('BeepBeep AI • WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?> — <a href="<?php echo esc_url('https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('WordPress.org Plugin', 'beepbeep-ai-alt-text-generator'); ?></a>
            <?php else : ?>
                <!-- Fallback: No tab matched -->
                <div class="bbai-container bbai-unauth-container">
                    <h2><?php esc_html_e('Tab not found', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p><?php
                    printf(
                        /* translators: 1: requested tab, 2: available tabs list */
                        esc_html__('The requested tab "%1$s" could not be loaded. Available tabs: %2$s', 'beepbeep-ai-alt-text-generator'),
                        esc_html($bbai_tab),
                        esc_html(implode(', ', array_keys($bbai_tabs ?? [])))
                    );
                    ?></p>
                    <p><strong><?php esc_html_e('Debug info:', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                    <ul class="bbai-unauth-list">
                        <li><?php
                        /* translators: 1: tab identifier */
                        printf(esc_html__('Tab: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_tab));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Is Authenticated: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_is_authenticated ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has License: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_has_license ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has Stored Token: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_has_stored_token ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has Stored License: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_has_stored_license ? 'Yes' : 'No'));
                        ?></li>
                    </ul>
                </div>
            </div><!-- .bbai-container -->

            <!-- Footer -->
            <div class="bbai-footer">
                <?php esc_html_e('BeepBeep AI • WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?> — <a href="<?php echo esc_url('https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('WordPress.org Plugin', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </div>
        
        <?php endif; // End tab check (dashboard/library/help/usage/settings/admin views)
        
        // Include upgrade modal OUTSIDE of tab conditionals so it's always available
        // Set up currency for upgrade modal - Always use GBP (£) with Stripe prices
        // GBP prices: Growth £12.99, Agency £49.99, Credits £9.99 (matching Stripe payment links)
        $bbai_currency = ['symbol' => '£', 'code' => 'GBP', 'free' => 0, 'growth' => 12.99, 'pro' => 12.99, 'agency' => 49.99, 'credits' => 9.99];
        
        // Include upgrade modal - always available for all tabs.
        $bbai_checkout_prices = $this->get_checkout_price_ids();
        $bbai_upgrade_modal = dirname(__DIR__) . '/templates/upgrade-modal.php';
        if (file_exists($bbai_upgrade_modal)) {
            include $bbai_upgrade_modal;
        }
        $bbai_feature_modal = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/feature-unlock-modal.php';
        if ( file_exists( $bbai_feature_modal ) ) {
            include $bbai_feature_modal;
        }
    }

    /**
     * Sanitize error messages to prevent exposing sensitive API information
     */
    private function sanitize_error_message($message) {
        if (!is_string($message)) {
            return $message;
        }
        
        // Remove URLs, tokens, API keys, and other sensitive data
        $sanitized = preg_replace(
            [
                '/https?:\/\/[^\s]+/i',  // Remove URLs
                '/Bearer\s+[A-Za-z0-9\-_\.]+/i',  // Remove Bearer tokens
                '/token[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove token values
                '/api[_-]?key[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove API keys
                '/secret[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove secrets
                '/password[=:]\s*[^\s]+/i',  // Remove passwords
            ],
            '[REDACTED]',
            $message
        );
        
        return $sanitized;
    }

    private function build_prompt($attachment_id, $opts, $existing_alt = '', bool $is_retry = false, array $feedback = []){
        $file     = get_attached_file($attachment_id);
        $title_input = get_the_title($attachment_id);
        $filename = $file ? wp_basename($file) : (is_string($title_input) ? $title_input : '');
        $title    = is_string($title_input) ? $title_input : '';
        $caption  = wp_get_attachment_caption($attachment_id);
        $parent_input = get_post_field('post_title', wp_get_post_parent_id($attachment_id));
        $parent   = is_string($parent_input) ? $parent_input : '';
        $lang_input = $opts['language'] ?? 'en-GB';
        if ($lang_input === 'custom' && !empty($opts['language_custom'])){
            $lang = sanitize_text_field($opts['language_custom']);
        } else {
            $lang = $lang_input;
        }
        $tone     = $opts['tone'] ?? 'professional, accessible';
        $max      = max(4, intval($opts['max_words'] ?? 16));

        $existing_alt = is_string($existing_alt) ? trim($existing_alt) : '';
        $context_bits = array_filter([$title, $caption, $parent, $existing_alt ? ('Existing ALT: ' . $existing_alt) : '']);
        $context = $context_bits ? ("Context: " . implode(' | ', $context_bits)) : '';

        $custom = trim($opts['custom_prompt'] ?? '');
        $instruction = "Write concise, descriptive ALT text in {$lang} for the provided image. "
               . "Limit to {$max} words. Tone: {$tone}. "
               . "Describe the primary subject with concrete nouns; include one visible colour/texture and any clearly visible background. "
               . "Only describe what is visible; no guessing about intent, brand, or location unless unmistakable. "
               . "If the image is a text/wordmark/logo (e.g., filename/title contains 'logo', 'icon', 'wordmark', or the image is mostly text), respond with a short accurate phrase like 'Red “TEST” wordmark' rather than a scene description. "
               . "Avoid 'image of' / 'photo of' and never output placeholders like 'test' or 'sample'. "
               . "Return only the ALT text sentence.";

        if ($existing_alt){
            $instruction .= " The previous ALT text is provided for context and must be improved upon.";
        }

        if ($is_retry){
            $instruction .= " The previous attempt was rejected; ensure this version corrects the issues listed below and adds concrete, specific detail.";
        }

        $feedback_lines = array_filter(array_map('trim', $feedback));
        $feedback_block = '';
        if ($feedback_lines){
            $feedback_block = "\nReviewer feedback:";
            foreach ($feedback_lines as $line){
                $feedback_block .= "\n- " . sanitize_text_field($line);
            }
            $feedback_block .= "\n";
        }

        $prompt = ($custom ? $custom . "\n\n" : '')
               . $instruction
               . "\nFilename: {$filename}\n{$context}\n" . $feedback_block;
        return apply_filters('bbai_prompt', $prompt, $attachment_id, $opts);
    }

    private function is_image($attachment_id){
        $mime = get_post_mime_type($attachment_id);
        return strpos((string)$mime, 'image/') === 0;
    }

    public function invalidate_stats_cache(){
        wp_cache_delete('bbai_stats', 'bbai');
        delete_transient('bbai_stats_v3');
        delete_transient(self::ALT_COVERAGE_TRANSIENT_KEY);
        $this->stats_cache = null;
        BBAI_Cache::bump( 'library' );
        BBAI_Cache::bump( 'stats' );
    }

    public function get_media_stats(){
        try {
            // Check in-memory cache first
            if (is_array($this->stats_cache)){
                return $this->stats_cache;
            }

            // Check object cache (Redis/Memcached if available)
            $cache_key = 'bbai_stats';
            $cache_group = 'bbai';
            $cached = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached && is_array($cached)){
                $this->stats_cache = $cached;
                return $cached;
            }

            // Check transient cache (15 minute TTL for DB queries - optimized for performance)
            $transient_key = 'bbai_stats_v3';
            $cached = get_transient($transient_key);
            if (false !== $cached && is_array($cached)){
                // Also populate object cache for next request
                wp_cache_set($cache_key, $cached, $cache_group, 15 * MINUTE_IN_SECONDS);
                $this->stats_cache = $cached;
                return $cached;
	            }
	
	            global $wpdb;
		
		            $image_mime_like = $wpdb->esc_like('image/') . '%';
		
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $total = (int) $wpdb->get_var($wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
		                'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
		                'attachment', 'inherit', $image_mime_like
	            ));

	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $with_alt = (int) $wpdb->get_var($wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND m.meta_key = %s AND TRIM(m.meta_value) <> %s',
	                'attachment',
	                'inherit',
	                $image_mime_like,
	                '_wp_attachment_image_alt',
	                ''
	            ));
	
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $generated = (int) $wpdb->get_var($wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                'SELECT COUNT(DISTINCT post_id) FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s',
	                '_bbai_generated_at'
	            ));

            $coverage = $total ? round(($with_alt / $total) * 100, 1) : 0;
            $bbai_missing  = max(0, $total - $with_alt);

            // Cache date/time format to avoid duplicate get_option() calls
            $date_format_input = get_option('date_format');
            $time_format_input = get_option('time_format');
            $date_format = is_string($date_format_input) ? $date_format_input : '';
            $time_format = is_string($time_format_input) ? $time_format_input : '';
            $datetime_format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';

            $opts = get_option(self::OPTION_KEY, []);
            $usage = $opts['usage'] ?? $this->default_usage();
	            if (!empty($usage['last_request'])){
	                $usage['last_request_formatted'] = mysql2date($datetime_format, $usage['last_request']);
	            }
	
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $latest_generated_input = $wpdb->get_var($wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 1',
	                '_bbai_generated_at'
	            ));
	            $latest_generated = $latest_generated_input ? mysql2date($datetime_format, $latest_generated_input) : '';
	
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $top_source_row = $wpdb->get_row(
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                    'SELECT meta_value AS source, COUNT(*) AS count FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value <> %s GROUP BY meta_value ORDER BY COUNT(*) DESC LIMIT 1',
	                    '_beepbeepai_source',
	                    ''
	                ),
	                ARRAY_A
	            );
	            $top_source_key = sanitize_key($top_source_row['source'] ?? '');
	            $top_source_count = intval($top_source_row['count'] ?? 0);

            $this->stats_cache = [
                'total'     => $total,
                'with_alt'  => $with_alt,
                'missing'   => $bbai_missing,
                'generated' => $generated,
                'coverage'  => $coverage,
                'usage'     => $usage,
                'token_limit' => intval($opts['token_limit'] ?? 0),
                'latest_generated' => $latest_generated,
                'latest_generated_raw' => $latest_generated_input,
                'top_source_key' => $top_source_key,
                'top_source_count' => $top_source_count,
                'dry_run_enabled' => !empty($opts['dry_run']),
                'audit' => $this->get_usage_rows(10),
            ];

            // Cache for 15 minutes (optimized - stats don't change frequently)
            wp_cache_set($cache_key, $this->stats_cache, $cache_group, 15 * MINUTE_IN_SECONDS);
            set_transient($transient_key, $this->stats_cache, 15 * MINUTE_IN_SECONDS);

            return $this->stats_cache;
        } catch ( \Exception $e ) {
            // If stats query fails, return empty stats array to prevent breaking REST responses
            // Silent failure - stats are non-critical
            return [
                'total' => 0,
                'with_alt' => 0,
                'missing_alt' => 0,
                'ai_generated' => 0,
                'manual' => 0,
                'coverage' => 0,
            ];
        }
    }

    /**
     * Get site-wide ALT text coverage scan data.
     *
     * @param bool $force_refresh Whether to bypass cache and run a fresh scan.
     * @return array<string, int>
     */
    private function get_empty_alt_coverage_data(): array {
        return [
            'total_images' => 0,
            'images_with_alt' => 0,
            'images_missing_alt' => 0,
            'coverage_percent' => 0,
            'ai_source_count' => 0,
            'needs_review_count' => 0,
            'optimized_count' => 0,
            'approved_count' => 0,
            'filename_only_count' => 0,
            'duplicate_alt_count' => 0,
            'free_plan_limit' => self::FREE_PLAN_IMAGE_LIMIT,
            'scanned_at' => time(),
        ];
    }

    private function normalize_alt_coverage_compare_value(string $value): string {
        $value = strtolower((string) $value);
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function get_alt_coverage_image_total(): int {
        global $wpdb;

        $image_mime_like = $wpdb->esc_like('image/') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifier comes from trusted core $wpdb.
                'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
                'attachment',
                'inherit',
                $image_mime_like
            )
        );
    }

    private function get_alt_coverage_batch_rows(int $limit, int $after_attachment_id = 0): array {
        global $wpdb;

        $limit = max(1, min(250, $limit));
        $after_attachment_id = max(0, $after_attachment_id);
        $image_mime_like = $wpdb->esc_like('image/') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT p.ID,
                        MAX(CASE WHEN m.meta_key = %s THEN m.meta_value ELSE NULL END) AS alt_text,
                        MAX(CASE WHEN m.meta_key = %s THEN m.meta_value ELSE NULL END) AS approved_hash,
                        MAX(CASE WHEN m.meta_key = %s THEN m.meta_value ELSE NULL END) AS source_value
                 FROM ' . $wpdb->posts . ' p
                 LEFT JOIN ' . $wpdb->postmeta . ' m
                    ON p.ID = m.post_id
                   AND m.meta_key IN (%s, %s, %s)
                 WHERE p.post_type = %s
                   AND p.post_status = %s
                   AND p.post_mime_type LIKE %s
                   AND (%d = 0 OR p.ID < %d)
                 GROUP BY p.ID
                 ORDER BY p.ID DESC
                 LIMIT %d',
                '_wp_attachment_image_alt',
                '_bbai_user_approved_hash',
                '_bbai_source',
                '_wp_attachment_image_alt',
                '_bbai_user_approved_hash',
                '_bbai_source',
                'attachment',
                'inherit',
                $image_mime_like,
                $after_attachment_id,
                $after_attachment_id,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function get_alt_coverage_duplicate_count(): int {
        global $wpdb;

        $image_mime_like = $wpdb->esc_like('image/') . '%';
        $duplicate_alt_count = 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $duplicate_rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT alt.meta_value AS alt_text, COUNT(*) AS cnt
                 FROM ' . $wpdb->posts . ' p
                 INNER JOIN ' . $wpdb->postmeta . ' alt
                    ON p.ID = alt.post_id
                   AND alt.meta_key = %s
                   AND TRIM(alt.meta_value) <> %s
                 WHERE p.post_type = %s
                   AND p.post_status = %s
                   AND p.post_mime_type LIKE %s
                 GROUP BY alt.meta_value
                 HAVING cnt > 1',
                '_wp_attachment_image_alt',
                '',
                'attachment',
                'inherit',
                $image_mime_like
            ),
            ARRAY_A
        );

        foreach ((array) $duplicate_rows as $duplicate_row) {
            $duplicate_alt_count += (int) ($duplicate_row['cnt'] ?? 0);
        }

        return max(0, $duplicate_alt_count);
    }

    private function get_alt_coverage_scan_job_key(string $job_id): string {
        return self::ALT_COVERAGE_SCAN_JOB_PREFIX . sanitize_key($job_id);
    }

    private function get_alt_coverage_scan_stage_message(int $processed_images, int $total_images, string $status = 'running'): string {
        if ($status === 'complete') {
            return __('Scan complete.', 'beepbeep-ai-alt-text-generator');
        }

        if ($status === 'finalizing') {
            return __('Preparing your coverage summary.', 'beepbeep-ai-alt-text-generator');
        }

        if ($total_images <= 0) {
            return __('Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator');
        }

        $progress_percent = (int) floor(($processed_images / max(1, $total_images)) * 100);

        if ($progress_percent < 35) {
            return __('Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator');
        }

        if ($progress_percent < 70) {
            return __('Reviewing existing ALT descriptions.', 'beepbeep-ai-alt-text-generator');
        }

        return __('Checking description quality and review signals.', 'beepbeep-ai-alt-text-generator');
    }

    private function build_alt_coverage_scan_job_payload(array $scan_state, array $coverage_payload = []): array {
        $total_images = max(0, (int) ($scan_state['total_images'] ?? 0));
        $processed_images = max(0, min($total_images, (int) ($scan_state['processed_images'] ?? 0)));
        $progress_percent = $total_images > 0
            ? (int) round(($processed_images / $total_images) * 100)
            : 100;
        $status = (string) ($scan_state['status'] ?? 'running');
        $stage_message = (string) ($scan_state['stage_message'] ?? $this->get_alt_coverage_scan_stage_message($processed_images, $total_images, $status));

        return [
            'job_id' => (string) ($scan_state['job_id'] ?? ''),
            'done' => $status === 'complete',
            'status' => $status,
            'progress_percent' => max(0, min(100, $progress_percent)),
            'processed_images' => $processed_images,
            'total_images' => $total_images,
            'stage_message' => $stage_message,
            'message' => $status === 'complete'
                ? __('Media library scan complete.', 'beepbeep-ai-alt-text-generator')
                : __('Scanning your media library.', 'beepbeep-ai-alt-text-generator'),
            'payload' => $status === 'complete' ? $coverage_payload : [],
        ];
    }

    private function persist_alt_coverage_scan_job(array $scan_state): void {
        $job_id = (string) ($scan_state['job_id'] ?? '');
        if ($job_id === '') {
            return;
        }

        set_transient($this->get_alt_coverage_scan_job_key($job_id), $scan_state, self::ALT_COVERAGE_SCAN_JOB_TTL);
    }

    private function get_alt_coverage_scan_job(string $job_id): ?array {
        if ($job_id === '') {
            return null;
        }

        $scan_state = get_transient($this->get_alt_coverage_scan_job_key($job_id));
        return is_array($scan_state) ? $scan_state : null;
    }

    private function start_alt_coverage_scan_job(): array {
        $job_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('bbai_scan_', true);
        $total_images = $this->get_alt_coverage_image_total();
        $scan_state = [
            'job_id' => $job_id,
            'user_id' => get_current_user_id(),
            'status' => $total_images > 0 ? 'running' : 'complete',
            'stage_message' => $this->get_alt_coverage_scan_stage_message(0, $total_images, $total_images > 0 ? 'running' : 'complete'),
            'total_images' => $total_images,
            'processed_images' => 0,
            'images_with_alt' => 0,
            'images_missing_alt' => 0,
            'ai_source_count' => 0,
            'needs_review_count' => 0,
            'approved_count' => 0,
            'filename_only_count' => 0,
            'last_attachment_id' => 0,
            'updated_at' => time(),
            'scanned_at' => time(),
        ];

        if ($total_images <= 0) {
            $coverage_data = $this->get_empty_alt_coverage_data();
            set_transient(self::ALT_COVERAGE_TRANSIENT_KEY, $coverage_data, self::ALT_COVERAGE_TRANSIENT_TTL);
            $scan_state['payload'] = $coverage_data;
        }

        $this->persist_alt_coverage_scan_job($scan_state);

        return $scan_state;
    }

    private function process_alt_coverage_scan_job_batch(array $scan_state): array {
        $status = (string) ($scan_state['status'] ?? 'running');
        if ($status === 'complete') {
            return $scan_state;
        }

        $total_images = max(0, (int) ($scan_state['total_images'] ?? 0));
        if ($total_images <= 0) {
            $scan_state['status'] = 'complete';
            $scan_state['payload'] = $this->get_empty_alt_coverage_data();
            return $scan_state;
        }

        $rows = $this->get_alt_coverage_batch_rows(
            self::ALT_COVERAGE_SCAN_BATCH_SIZE,
            (int) ($scan_state['last_attachment_id'] ?? 0)
        );

        foreach ($rows as $row) {
            $attachment_id = isset($row['ID']) ? (int) $row['ID'] : 0;
            $alt_text = isset($row['alt_text']) ? trim((string) $row['alt_text']) : '';
            $source_value = strtolower(trim((string) ($row['source_value'] ?? '')));

            if ($attachment_id <= 0) {
                continue;
            }

            $scan_state['processed_images'] = max(0, (int) ($scan_state['processed_images'] ?? 0)) + 1;
            $scan_state['last_attachment_id'] = $attachment_id;

            if ($alt_text === '') {
                $scan_state['images_missing_alt'] = max(0, (int) ($scan_state['images_missing_alt'] ?? 0)) + 1;
                continue;
            }

            $scan_state['images_with_alt'] = max(0, (int) ($scan_state['images_with_alt'] ?? 0)) + 1;

            if (in_array($source_value, ['ai', 'openai'], true)) {
                $scan_state['ai_source_count'] = max(0, (int) ($scan_state['ai_source_count'] ?? 0)) + 1;
            }

            $row_state_obj = (object) [
                'ID'       => $attachment_id,
                'alt_text' => $alt_text,
            ];
            $row_state = $this->get_library_workspace_row_state($row_state_obj);
            if (!empty($row_state['user_approved'])) {
                $scan_state['approved_count'] = max(0, (int) ($scan_state['approved_count'] ?? 0)) + 1;
            }
            if (($row_state['status'] ?? '') === 'weak') {
                $scan_state['needs_review_count'] = max(0, (int) ($scan_state['needs_review_count'] ?? 0)) + 1;
            }

            $attached_file = get_attached_file($attachment_id);
            if ($attached_file) {
                $base_name = pathinfo($attached_file, PATHINFO_FILENAME);
                if (
                    $base_name !== ''
                    && $this->normalize_alt_coverage_compare_value($alt_text) === $this->normalize_alt_coverage_compare_value($base_name)
                ) {
                    $scan_state['filename_only_count'] = max(0, (int) ($scan_state['filename_only_count'] ?? 0)) + 1;
                }
            }
        }

        $scan_state['updated_at'] = time();

        if (empty($rows) || (int) ($scan_state['processed_images'] ?? 0) >= $total_images) {
            $scan_state['status'] = 'finalizing';
            $scan_state['stage_message'] = $this->get_alt_coverage_scan_stage_message(
                (int) ($scan_state['processed_images'] ?? 0),
                $total_images,
                'finalizing'
            );

            $coverage_data = [
                'total_images' => $total_images,
                'images_with_alt' => max(0, (int) ($scan_state['images_with_alt'] ?? 0)),
                'images_missing_alt' => max(0, (int) ($scan_state['images_missing_alt'] ?? 0)),
                'coverage_percent' => $total_images > 0
                    ? (int) round((max(0, (int) ($scan_state['images_with_alt'] ?? 0)) / $total_images) * 100)
                    : 0,
                'ai_source_count' => max(0, (int) ($scan_state['ai_source_count'] ?? 0)),
                'needs_review_count' => max(0, (int) ($scan_state['needs_review_count'] ?? 0)),
                'optimized_count' => max(0, (int) ($scan_state['images_with_alt'] ?? 0) - (int) ($scan_state['needs_review_count'] ?? 0)),
                'approved_count' => max(0, (int) ($scan_state['approved_count'] ?? 0)),
                'filename_only_count' => max(0, (int) ($scan_state['filename_only_count'] ?? 0)),
                'duplicate_alt_count' => $this->get_alt_coverage_duplicate_count(),
                'free_plan_limit' => self::FREE_PLAN_IMAGE_LIMIT,
                'scanned_at' => time(),
            ];

            $scan_state['status'] = 'complete';
            $scan_state['processed_images'] = $total_images;
            $scan_state['payload'] = $coverage_data;
            $scan_state['stage_message'] = __('Scan complete.', 'beepbeep-ai-alt-text-generator');

            set_transient(self::ALT_COVERAGE_TRANSIENT_KEY, $coverage_data, self::ALT_COVERAGE_TRANSIENT_TTL);
        } else {
            $scan_state['stage_message'] = $this->get_alt_coverage_scan_stage_message(
                (int) ($scan_state['processed_images'] ?? 0),
                $total_images,
                'running'
            );
        }

        return $scan_state;
    }

    public function get_alt_text_coverage_scan(bool $force_refresh = false): array {
        if (!$force_refresh) {
            $cached = get_transient(self::ALT_COVERAGE_TRANSIENT_KEY);
            if (
                is_array($cached)
                && isset($cached['total_images'], $cached['images_with_alt'], $cached['images_missing_alt'], $cached['coverage_percent'])
            ) {
                if (!isset($cached['ai_source_count']) || !isset($cached['needs_review_count'])) {
                    delete_transient(self::ALT_COVERAGE_TRANSIENT_KEY);
                } else {
                    $cached['ai_source_count'] = (int) $cached['ai_source_count'];
                    $cached['needs_review_count'] = (int) $cached['needs_review_count'];
                    $cached['optimized_count'] = (int) ($cached['optimized_count'] ?? max(0, $cached['images_with_alt'] - $cached['needs_review_count']));
                    $cached['approved_count'] = (int) ($cached['approved_count'] ?? 0);
                    $cached['filename_only_count'] = (int) ($cached['filename_only_count'] ?? 0);
                    $cached['duplicate_alt_count'] = (int) ($cached['duplicate_alt_count'] ?? 0);
                    return $cached;
                }
            }
        }

        try {
            global $wpdb;

            $image_mime_like = $wpdb->esc_like('image/') . '%';
            $total_images = $this->get_alt_coverage_image_total();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $images_with_alt = (int) $wpdb->get_var(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb.
                    'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND m.meta_key = %s AND TRIM(m.meta_value) <> %s',
                    'attachment',
                    'inherit',
                    $image_mime_like,
                    '_wp_attachment_image_alt',
                    ''
                )
            );

            $images_missing_alt = max(0, $total_images - $images_with_alt);
            $coverage_percent = $total_images > 0 ? (int) round(($images_with_alt / $total_images) * 100) : 0;

            // AI-generated count: images with alt and _bbai_source = ai or openai.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $ai_source_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' alt ON p.ID = alt.post_id AND alt.meta_key = %s AND TRIM(alt.meta_value) <> %s INNER JOIN ' . $wpdb->postmeta . ' src ON p.ID = src.post_id AND src.meta_key = %s AND LOWER(src.meta_value) IN (%s, %s) WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s',
                    '_wp_attachment_image_alt',
                    '',
                    '_bbai_source',
                    'ai',
                    'openai',
                    'attachment',
                    'inherit',
                    $image_mime_like
                )
            );

            // Needs-review count: same classification as ALT Library rows (get_library_workspace_row_state → status weak).
            $needs_review_count = 0;
            $approved_count = 0;
            $filename_only_count = 0;
            if ( $images_with_alt > 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT p.ID, alt.meta_value AS alt_text, approved.meta_value AS approved_hash FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' alt ON p.ID = alt.post_id AND alt.meta_key = %s AND TRIM(alt.meta_value) <> %s LEFT JOIN ' . $wpdb->postmeta . ' approved ON p.ID = approved.post_id AND approved.meta_key = %s WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s',
                        '_wp_attachment_image_alt',
                        '',
                        '_bbai_user_approved_hash',
                        'attachment',
                        'inherit',
                        $image_mime_like
                    ),
                    ARRAY_A
                );
                foreach ( $rows as $row ) {
                    $alt = isset( $row['alt_text'] ) ? (string) $row['alt_text'] : '';
                    $id  = isset( $row['ID'] ) ? (int) $row['ID'] : 0;
                    $image_row = (object) [
                        'ID'       => $id,
                        'alt_text' => $alt,
                    ];
                    $norm_state = $this->get_library_workspace_row_state( $image_row );
                    if ( ! empty( $norm_state['user_approved'] ) ) {
                        $approved_count++;
                    }
                    if ( ( $norm_state['status'] ?? '' ) === 'weak' ) {
                        $needs_review_count++;
                    }
                    if ( $id > 0 && $alt !== '' ) {
                        $file = get_attached_file( $id );
                        if ( $file ) {
                            $base      = pathinfo( $file, PATHINFO_FILENAME );
                            $norm_alt  = $this->normalize_alt_coverage_compare_value( $alt );
                            $norm_base = $this->normalize_alt_coverage_compare_value( $base );
                            if ( $norm_base !== '' && $norm_alt === $norm_base ) {
                                $filename_only_count++;
                            }
                        }
                    }
                }
            }

            $duplicate_alt_count = $images_with_alt > 0 ? $this->get_alt_coverage_duplicate_count() : 0;

            $optimized_count = max( 0, $images_with_alt - $needs_review_count );

            $coverage_data = [
                'total_images' => $total_images,
                'images_with_alt' => $images_with_alt,
                'images_missing_alt' => $images_missing_alt,
                'coverage_percent' => max(0, min(100, $coverage_percent)),
                'ai_source_count' => $ai_source_count,
                'needs_review_count' => $needs_review_count,
                'optimized_count' => $optimized_count,
                'approved_count' => $approved_count,
                'filename_only_count' => $filename_only_count,
                'duplicate_alt_count' => $duplicate_alt_count,
                'free_plan_limit' => self::FREE_PLAN_IMAGE_LIMIT,
                'scanned_at' => time(),
            ];

            set_transient(self::ALT_COVERAGE_TRANSIENT_KEY, $coverage_data, self::ALT_COVERAGE_TRANSIENT_TTL);

            return $coverage_data;
        } catch (\Exception $e) {
            return $this->get_empty_alt_coverage_data();
        }
    }

    public function get_dashboard_stats_payload(bool $force_refresh = false): array {
        if ($force_refresh) {
            $this->invalidate_stats_cache();
        }

        $stats = $this->get_media_stats();
        $coverage = $this->get_alt_text_coverage_scan($force_refresh);

        $total_images = max(0, (int) ($coverage['total_images'] ?? ($stats['total'] ?? 0)));
        $images_with_alt = max(0, (int) ($coverage['images_with_alt'] ?? ($stats['with_alt'] ?? 0)));
        $images_missing_alt = max(0, (int) ($coverage['images_missing_alt'] ?? ($stats['missing'] ?? 0)));
        $needs_review_count = max(0, (int) ($coverage['needs_review_count'] ?? 0));
        $optimized_count = max(0, (int) ($coverage['optimized_count'] ?? max(0, $images_with_alt - $needs_review_count)));
        $coverage_percent = max(
            0,
            min(
                100,
                (int) ($coverage['coverage_percent'] ?? ($total_images > 0 ? round(($images_with_alt / $total_images) * 100) : 0))
            )
        );

        $payload = is_array($stats) ? $stats : [];
        $payload['total'] = $total_images;
        $payload['with_alt'] = $images_with_alt;
        $payload['missing'] = $images_missing_alt;
        $payload['coverage'] = $total_images > 0 ? round(($images_with_alt / $total_images) * 100, 1) : 0;
        $payload['total_images'] = $total_images;
        $payload['images_with_alt'] = $images_with_alt;
        $payload['images_missing_alt'] = $images_missing_alt;
        $payload['coverage_percent'] = $coverage_percent;
        $payload['needs_review_count'] = $needs_review_count;
        $payload['optimized_count'] = $optimized_count;
        $payload['ai_source_count'] = max(0, (int) ($coverage['ai_source_count'] ?? 0));
        $payload['approved_count'] = max(0, (int) ($coverage['approved_count'] ?? 0));
        $payload['filename_only_count'] = max(0, (int) ($coverage['filename_only_count'] ?? 0));
        $payload['duplicate_alt_count'] = max(0, (int) ($coverage['duplicate_alt_count'] ?? 0));
        $payload['free_plan_limit'] = max(0, (int) ($coverage['free_plan_limit'] ?? self::FREE_PLAN_IMAGE_LIMIT));
        $payload['scanned_at'] = max(0, (int) ($coverage['scanned_at'] ?? time()));

        return $payload;
    }

    /**
     * AJAX handler: start a chunked ALT coverage scan job.
     */
    public function ajax_start_alt_coverage_scan() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error([ 'message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error([ 'message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        $this->invalidate_stats_cache();

        try {
            $scan_state = $this->start_alt_coverage_scan_job();

            if ((string) ($scan_state['status'] ?? '') !== 'complete') {
                $scan_state = $this->process_alt_coverage_scan_job_batch($scan_state);
                $this->persist_alt_coverage_scan_job($scan_state);
            }

            $payload = $this->build_alt_coverage_scan_job_payload(
                $scan_state,
                is_array($scan_state['payload'] ?? null) ? $scan_state['payload'] : []
            );

            wp_send_json_success($payload);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Unable to start the media library scan right now.', 'beepbeep-ai-alt-text-generator'),
            ], 500);
        }
    }

    /**
     * AJAX handler: poll a running ALT coverage scan job.
     */
    public function ajax_poll_alt_coverage_scan() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error([ 'message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error([ 'message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        $job_id = sanitize_text_field(wp_unslash($_POST['job_id'] ?? ''));
        if ($job_id === '') {
            wp_send_json_error([ 'message' => __('Missing scan job.', 'beepbeep-ai-alt-text-generator') ], 400);
            return;
        }

        $scan_state = $this->get_alt_coverage_scan_job($job_id);
        if (!$scan_state) {
            wp_send_json_error([ 'message' => __('Scan session expired. Please start again.', 'beepbeep-ai-alt-text-generator') ], 404);
            return;
        }

        if ((int) ($scan_state['user_id'] ?? 0) !== get_current_user_id()) {
            wp_send_json_error([ 'message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        try {
            if ((string) ($scan_state['status'] ?? '') !== 'complete') {
                $scan_state = $this->process_alt_coverage_scan_job_batch($scan_state);
                $this->persist_alt_coverage_scan_job($scan_state);
            }

            $payload = $this->build_alt_coverage_scan_job_payload(
                $scan_state,
                is_array($scan_state['payload'] ?? null) ? $scan_state['payload'] : []
            );

            wp_send_json_success($payload);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Unable to continue the media library scan right now.', 'beepbeep-ai-alt-text-generator'),
            ], 500);
        }
    }

    /**
     * AJAX handler: force-refresh the ALT text coverage scan.
     */
    public function ajax_rescan_alt_coverage() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error([ 'message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error([ 'message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator') ], 403);
            return;
        }

        $this->invalidate_stats_cache();
        $coverage_data = $this->get_alt_text_coverage_scan(true);

        wp_send_json_success(array_merge(
            [
                'message' => __('Media library scan complete.', 'beepbeep-ai-alt-text-generator'),
            ],
            $coverage_data
        ));
    }

    public function prepare_attachment_snapshot($attachment_id){
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0){
            return [];
        }

        $alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $tokens = intval(get_post_meta($attachment_id, '_bbai_tokens_total', true));
        $prompt = intval(get_post_meta($attachment_id, '_bbai_tokens_prompt', true));
        $completion = intval(get_post_meta($attachment_id, '_bbai_tokens_completion', true));
        $generated_input = get_post_meta($attachment_id, '_bbai_generated_at', true);
        $date_format_input = get_option('date_format');
        $time_format_input = get_option('time_format');
        $date_format = is_string($date_format_input) && !empty($date_format_input) ? $date_format_input : 'Y-m-d';
        $time_format = is_string($time_format_input) && !empty($time_format_input) ? $time_format_input : 'H:i:s';
        $generated = $generated_input ? mysql2date($date_format . ' ' . $time_format, $generated_input) : '';
        $source_key = sanitize_key(get_post_meta($attachment_id, '_bbai_source', true) ?: 'unknown');
        if (!$source_key){
            $source_key = 'unknown';
        }

        $row       = $this->get_library_workspace_row_state( (object) [ 'ID' => $attachment_id, 'alt_text' => $alt ] );
        $analysis  = $row['analysis'];
        $issues    = ( is_array( $analysis ) && isset( $analysis['issues'] ) && is_array( $analysis['issues'] ) ) ? $analysis['issues'] : [];
        $grade     = is_array( $analysis ) && isset( $analysis['grade'] ) ? (string) $analysis['grade'] : __( 'Missing', 'beepbeep-ai-alt-text-generator' );
        $rev_stat  = is_array( $analysis ) && isset( $analysis['status'] ) ? sanitize_key( (string) $analysis['status'] ) : 'critical';
        $rev_sum   = '';
        if ( is_array( $analysis ) && isset( $analysis['review'] ) && is_array( $analysis['review'] ) && isset( $analysis['review']['summary'] ) ) {
            $rev_sum = (string) $analysis['review']['summary'];
        }

        return [
            'id' => $attachment_id,
            'alt' => $alt,
            'tokens' => $tokens,
            'prompt' => $prompt,
            'completion' => $completion,
            'generated_raw' => $generated_input,
            'generated' => $generated,
            'source_key' => $source_key,
            'source_label' => $this->format_source_label($source_key),
            'source_description' => $this->format_source_description($source_key),
            'score' => (int) $row['quality_score'],
            'row_status' => (string) $row['status'],
            'score_grade' => $grade,
            'score_status' => $rev_stat,
            'score_issues' => $issues,
            'score_summary' => $rev_sum,
            'user_approved' => ! empty( $row['user_approved'] ),
            'user_approved_at' => is_array( $analysis ) && isset( $analysis['approved_at'] ) ? (string) $analysis['approved_at'] : '',
            'analysis' => $analysis,
        ];
    }

    public function mark_attachment_reviewed(int $attachment_id): array {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return [];
        }

        $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
        if ($alt === '') {
            return [];
        }

        $approved_at = current_time('mysql');
        update_post_meta($attachment_id, '_bbai_user_approved_hash', $this->hash_alt_text($alt));
        update_post_meta($attachment_id, '_bbai_user_approved_at', $approved_at);
        $this->invalidate_stats_cache();

        return [
            'approved' => true,
            'approved_at' => $approved_at,
            'alt' => $alt,
        ];
    }

    public function mark_attachments_reviewed(array $attachment_ids): array {
        $approved_ids = [];
        $approved_at = current_time('mysql');

        foreach (array_values(array_unique(array_map('absint', $attachment_ids))) as $attachment_id) {
            if ($attachment_id <= 0) {
                continue;
            }

            $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
            if ($alt === '') {
                continue;
            }

            update_post_meta($attachment_id, '_bbai_user_approved_hash', $this->hash_alt_text($alt));
            update_post_meta($attachment_id, '_bbai_user_approved_at', $approved_at);
            $approved_ids[] = $attachment_id;
        }

        if (!empty($approved_ids)) {
            $this->invalidate_stats_cache();
        }

        return [
            'approved_ids' => array_map('intval', $approved_ids),
            'approved_count' => count($approved_ids),
            'approved_at' => $approved_at,
        ];
    }

    private function hash_alt_text(string $alt): string{
        $alt = strtolower(trim((string) $alt));
        $alt = preg_replace('/\s+/', ' ', $alt);
        return wp_hash($alt);
    }

    private function clear_user_approval_meta(int $attachment_id): void{
        delete_post_meta($attachment_id, '_bbai_user_approved_hash');
        delete_post_meta($attachment_id, '_bbai_user_approved_at');
    }

    private function get_user_approval_snapshot(int $attachment_id, string $current_alt = ''): ?array{
        $approved_hash = (string) get_post_meta($attachment_id, '_bbai_user_approved_hash', true);
        $approved_at = (string) get_post_meta($attachment_id, '_bbai_user_approved_at', true);

        if ($approved_hash === '' || $approved_at === '') {
            return null;
        }

        if ($current_alt !== '') {
            $current_hash = $this->hash_alt_text($current_alt);
            if (!hash_equals($approved_hash, $current_hash)) {
                $this->clear_user_approval_meta($attachment_id);
                return null;
            }
        }

        return [
            'approved' => true,
            'approved_at' => $approved_at,
            'hash' => $approved_hash,
        ];
    }

    /**
     * Single source of truth for ALT Library table row filter state (must match workspace row markup).
     *
     * @param object $image Row object (expects ID, alt_text like DB row).
     * @return array{status:string,status_label:string,quality_class:string,quality_label:string,quality_score:int,score_tier:string,score_tier_label:string,analysis:?array,user_approved:bool,has_alt:bool,clean_alt:string}
     */
    public function get_library_workspace_row_state(object $image): array {
        $attachment_id = isset($image->ID) ? (int) $image->ID : 0;
        $current_alt   = $image->alt_text ?? '';
        $clean_alt     = is_string($current_alt) ? trim($current_alt) : '';
        $has_alt       = '' !== $clean_alt;

        if ($attachment_id <= 0 || ! $has_alt) {
            return [
                'status'            => 'missing',
                'status_label'      => __('Missing', 'beepbeep-ai-alt-text-generator'),
                'quality_class'     => 'poor',
                'quality_label'     => __('Weak', 'beepbeep-ai-alt-text-generator'),
                'quality_score'     => 0,
                'score_tier'        => 'missing',
                'score_tier_label'  => '',
                'analysis'          => null,
                'user_approved'     => false,
                'has_alt'           => $has_alt,
                'clean_alt'         => $clean_alt,
            ];
        }

        $bbai_analysis = $this->evaluate_alt_health($attachment_id, $clean_alt);
        $bbai_is_user_approved = ! empty($bbai_analysis['user_approved']);
        $bbai_quality_score      = (is_array($bbai_analysis) && isset($bbai_analysis['score']))
            ? (int) $bbai_analysis['score']
            : (function_exists('bbai_calculate_alt_quality_score')
                ? bbai_calculate_alt_quality_score($clean_alt)
                : 50);
        $bbai_quality_score = max(0, min(100, $bbai_quality_score));

        if ($bbai_quality_score >= 85) {
            $bbai_score_tier       = 'good';
            $bbai_score_tier_label = __('Good', 'beepbeep-ai-alt-text-generator');
            $bbai_quality_class    = 'excellent';
            $bbai_quality_label    = __('Good', 'beepbeep-ai-alt-text-generator');
        } elseif ($bbai_quality_score >= 70) {
            $bbai_score_tier       = 'review';
            $bbai_score_tier_label = __('Review', 'beepbeep-ai-alt-text-generator');
            $bbai_quality_class    = 'needs-review';
            $bbai_quality_label    = __('Review', 'beepbeep-ai-alt-text-generator');
        } else {
            $bbai_score_tier       = 'weak';
            $bbai_score_tier_label = bbai_copy_score_band_needs_review();
            $bbai_quality_class    = 'poor';
            $bbai_quality_label    = bbai_copy_score_band_needs_review();
        }

        $bbai_hard_fail = ! empty( $bbai_analysis['hard_fail'] );
        $bbai_breakdown = isset( $bbai_analysis['breakdown'] ) && is_array( $bbai_analysis['breakdown'] ) ? $bbai_analysis['breakdown'] : null;
        $bbai_optimized_eligible = class_exists( '\BBAI_Alt_Quality_Scorer' )
            ? \BBAI_Alt_Quality_Scorer::passes_optimized_row_gates( $clean_alt, $bbai_quality_score, $bbai_breakdown, $bbai_hard_fail )
            : false;

        // User approval should move a row out of the review queue, while the score badge
        // still reflects the underlying ALT quality.
        $bbai_row_optimized = $bbai_is_user_approved || ( $bbai_optimized_eligible && $bbai_quality_score >= 70 );

        if ( $bbai_row_optimized ) {
            $status       = 'optimized';
            $status_label = __('Optimized', 'beepbeep-ai-alt-text-generator');
        } else {
            $status       = 'weak';
            $status_label = __('Needs review', 'beepbeep-ai-alt-text-generator');
        }

        return [
            'status'           => $status,
            'status_label'     => $status_label,
            'quality_class'    => $bbai_quality_class,
            'quality_label'    => $bbai_quality_label,
            'quality_score'    => $bbai_quality_score,
            'score_tier'       => $bbai_score_tier,
            'score_tier_label' => $bbai_score_tier_label,
            'analysis'         => $bbai_analysis,
            'user_approved'    => $bbai_is_user_approved,
            'has_alt'          => true,
            'clean_alt'        => $clean_alt,
        ];
    }

	    public function get_missing_attachment_ids($limit = 5, $offset = 0){
	        global $wpdb;
	        $limit = intval($limit);
	        $offset = max(0, intval($offset));
	        if ($limit <= 0){ $limit = 5; }
	        $image_mime_like = $wpdb->esc_like('image/') . '%';
	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
	            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	            'SELECT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' m ON (p.ID = m.post_id AND m.meta_key = %s) WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND (m.meta_value IS NULL OR TRIM(m.meta_value) = %s) ORDER BY p.ID DESC LIMIT %d OFFSET %d',
	            '_wp_attachment_image_alt', 'attachment', 'inherit', $image_mime_like, '', $limit, $offset
	        )));
	    }

	    public function get_needs_review_attachment_ids($limit = 5, $offset = 0){
	        global $wpdb;
	        $limit  = max(1, intval($limit));
	        $offset = max(0, intval($offset));
	        $image_mime_like = $wpdb->esc_like('image/') . '%';

	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	        $rows = $wpdb->get_results($wpdb->prepare(
	            'SELECT p.ID, alt.meta_value AS alt_text FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' alt ON p.ID = alt.post_id AND alt.meta_key = %s AND TRIM(alt.meta_value) <> %s WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s ORDER BY p.ID DESC',
	            '_wp_attachment_image_alt',
	            '',
	            'attachment',
	            'inherit',
	            $image_mime_like
	        ), ARRAY_A);

	        if (empty($rows)) {
	            return [];
	        }

	        $needs_review_ids = [];
	        foreach ((array) $rows as $row) {
	            $attachment_id = isset($row['ID']) ? intval($row['ID']) : 0;
	            $alt_text = isset($row['alt_text']) ? (string) $row['alt_text'] : '';

	            if ($attachment_id <= 0 || $alt_text === '') {
	                continue;
	            }

	            $img = (object) [
	                'ID'       => $attachment_id,
	                'alt_text' => $alt_text,
	            ];
	            $st = $this->get_library_workspace_row_state($img);
	            if (($st['status'] ?? '') === 'weak') {
	                $needs_review_ids[] = $attachment_id;
	            }
	        }

	        if ($offset > 0) {
	            $needs_review_ids = array_slice($needs_review_ids, $offset);
	        }

	        return array_map('intval', array_slice($needs_review_ids, 0, $limit));
	    }

	    public function get_all_attachment_ids($limit = 5, $offset = 0){
	        global $wpdb;
	        $limit  = max(1, intval($limit));
	        $offset = max(0, intval($offset));
	        $image_mime_like = $wpdb->esc_like('image/') . '%';
	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	        $rows = $wpdb->get_col($wpdb->prepare(
	            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	            'SELECT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, p.ID DESC LIMIT %d OFFSET %d',
	            '_bbai_generated_at', 'attachment', 'inherit', $image_mime_like, $limit, $offset
	        ));
	        return array_map('intval', (array) $rows);
	    }

	    private function get_usage_rows($limit = 10, $include_all = false){
	        global $wpdb;
	        $limit = max(1, intval($limit));

	        $image_mime_like = $wpdb->esc_like('image/') . '%';

	        $cache_key = 'bbai_usage_rows_' . md5($limit . '|' . ($include_all ? 'all' : 'slice'));
	        if (!$include_all) {
	            $cached = wp_cache_get($cache_key, 'bbai');
	            if ($cached !== false) {
	                return $cached;
	            }
	        }
	        $prepare_params = [
	            '_bbai_tokens_total',
	            '_bbai_tokens_prompt',
	            '_bbai_tokens_completion',
	            '_wp_attachment_image_alt',
	            '_bbai_source',
	            '_bbai_model',
	            '_bbai_generated_at',
	            '_wp_attachment_metadata',
	            'attachment',
	            $image_mime_like
	        ];

	        if (!$include_all){
	            $query_params   = $prepare_params;
	            $query_params[] = $limit;
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $rows = $wpdb->get_results(
	                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                    'SELECT p.ID, p.post_title, p.guid, tokens.meta_value AS tokens_total, prompt.meta_value AS tokens_prompt, completion.meta_value AS tokens_completion, alt.meta_value AS alt_text, src.meta_value AS source, model.meta_value AS model, gen.meta_value AS generated_at, thumb.meta_value AS thumbnail_metadata FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' tokens ON tokens.post_id = p.ID AND tokens.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' prompt ON prompt.post_id = p.ID AND prompt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' completion ON completion.post_id = p.ID AND completion.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' alt ON alt.post_id = p.ID AND alt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' src ON src.post_id = p.ID AND src.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' model ON model.post_id = p.ID AND model.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' thumb ON thumb.post_id = p.ID AND thumb.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, CAST(tokens.meta_value AS UNSIGNED) DESC LIMIT %d',
	                    $query_params
	                ),
	                ARRAY_A
	            );
	        } else {
	            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	            $rows = $wpdb->get_results(
	                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                    'SELECT p.ID, p.post_title, p.guid, tokens.meta_value AS tokens_total, prompt.meta_value AS tokens_prompt, completion.meta_value AS tokens_completion, alt.meta_value AS alt_text, src.meta_value AS source, model.meta_value AS model, gen.meta_value AS generated_at, thumb.meta_value AS thumbnail_metadata FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' tokens ON tokens.post_id = p.ID AND tokens.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' prompt ON prompt.post_id = p.ID AND prompt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' completion ON completion.post_id = p.ID AND completion.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' alt ON alt.post_id = p.ID AND alt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' src ON src.post_id = p.ID AND src.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' model ON model.post_id = p.ID AND model.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' thumb ON thumb.post_id = p.ID AND thumb.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, CAST(tokens.meta_value AS UNSIGNED) DESC',
	                    $prepare_params
	                ),
	                ARRAY_A
	            );
	        }
	        if (empty($rows)){
	            if (!$include_all) {
	                wp_cache_set($cache_key, [], 'bbai', MINUTE_IN_SECONDS * 2);
	            }
	            return [];
	        }

        // Cache date format to avoid repeated get_option() calls
        $date_format_input = get_option('date_format');
        $time_format_input = get_option('time_format');
        $date_format = is_string($date_format_input) && !empty($date_format_input) ? $date_format_input : 'Y-m-d';
        $time_format = is_string($time_format_input) && !empty($time_format_input) ? $time_format_input : 'H:i:s';
        $date_time_format = $date_format . ' ' . $time_format;
        $upload_dir = wp_upload_dir();

        $formatted = array_map(function($row) use ($date_time_format, $upload_dir){
            $generated = $row['generated_at'] ?? '';
            if ($generated){
                $generated = mysql2date($date_time_format, $generated);
            }

            $source = sanitize_key($row['source'] ?? 'unknown');
            if (!$source){ $source = 'unknown'; }

            // Get thumbnail URL from metadata instead of separate query
            $thumb_url = '';
            if (!empty($row['thumbnail_metadata'])) {
                $metadata = maybe_unserialize($row['thumbnail_metadata']);
                if (isset($metadata['sizes']['thumbnail']['file'])) {
                    $dir = dirname($metadata['file']);
                    $thumb_url = $upload_dir['baseurl'] . '/' . ($dir !== '.' ? $dir . '/' : '') . $metadata['sizes']['thumbnail']['file'];
                } elseif (!empty($row['guid'])) {
                    $thumb_url = $row['guid'];
                }
            } elseif (!empty($row['guid'])) {
                $thumb_url = $row['guid'];
            }

            return [
                'id'         => intval($row['ID']),
                'title'      => $row['post_title'] ?? '',
                'alt'        => $row['alt_text'] ?? '',
                'tokens'     => intval($row['tokens_total'] ?? 0),
                'prompt'     => intval($row['tokens_prompt'] ?? 0),
                'completion' => intval($row['tokens_completion'] ?? 0),
                'source'     => $source,
                'source_label' => $this->format_source_label($source),
                'source_description' => $this->format_source_description($source),
                'model'      => $row['model'] ?? '',
                'generated'  => $generated,
                'thumb'      => $thumb_url,
                'details_url'=> add_query_arg('item', $row['ID'], admin_url('upload.php')) . '#attachment_alt',
                'view_url'   => get_permalink($row['ID']) ?: $row['guid'],
            ];
        }, $rows);

        if (!$include_all) {
            wp_cache_set($cache_key, $formatted, 'bbai', MINUTE_IN_SECONDS * 5);
        }

        return $formatted;
    }

    private function get_source_meta_map(){
        return [
            'auto'     => [
                'label' => __('Auto (upload)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated automatically when the image was uploaded.', 'beepbeep-ai-alt-text-generator'),
            ],
            'ajax'     => [
                'label' => __('Media Library (single)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Triggered from the Media Library row action or attachment details screen.', 'beepbeep-ai-alt-text-generator'),
            ],
            'bulk'     => [
                'label' => __('Media Library (bulk)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via the Media Library bulk action.', 'beepbeep-ai-alt-text-generator'),
            ],
            'dashboard' => [
                'label' => __('Dashboard quick actions', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated from the dashboard buttons.', 'beepbeep-ai-alt-text-generator'),
            ],
            'wpcli'    => [
                'label' => __('WP-CLI', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via the wp ai-alt CLI command.', 'beepbeep-ai-alt-text-generator'),
            ],
            'manual'   => [
                'label' => __('Manual / custom', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated by custom code or integration.', 'beepbeep-ai-alt-text-generator'),
            ],
            'unknown'  => [
                'label' => __('Unknown', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Source not recorded for this ALT text.', 'beepbeep-ai-alt-text-generator'),
            ],
        ];
    }

    private function format_source_label($key){
        $map = $this->get_source_meta_map();
        $key = sanitize_key($key ?: 'unknown');
        return $map[$key]['label'] ?? $map['unknown']['label'];
    }

    private function format_source_description($key){
        $map = $this->get_source_meta_map();
        $key = sanitize_key($key ?: 'unknown');
        return $map[$key]['description'] ?? $map['unknown']['description'];
    }

    /**
     * Ensure a direct WP_Filesystem instance is available for plugin file reads.
     *
     * @return \WP_Filesystem_Base|null
     */
    private function bbai_init_wp_filesystem() {
        global $wp_filesystem;

        if (is_object($wp_filesystem) && isset($wp_filesystem->method) && $wp_filesystem->method === 'direct') {
            return $wp_filesystem;
        }

        if (!class_exists('\WP_Filesystem_Direct')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $direct = new \WP_Filesystem_Direct(null);

        // Only populate the global if nothing else has been set.
        if (!is_object($wp_filesystem)) {
            $wp_filesystem = $direct;
        }

        return $direct;
    }

    private function redact_api_token($message){
        if (!is_string($message) || $message === ''){
            return $message;
        }

        $mask = function($token){
            $len = strlen($token);
            if ($len <= 8){
                return str_repeat('*', $len);
            }
            return substr($token, 0, 4) . str_repeat('*', $len - 8) . substr($token, -4);
        };

        $message = preg_replace_callback('/(Incorrect API key provided:\s*)(\S+)/i', function($matches) use ($mask){
            return $matches[1] . $mask($matches[2]);
        }, $message);

        $message = preg_replace_callback('/(sk-[A-Za-z0-9]{4})([A-Za-z0-9]{10,})([A-Za-z0-9]{4})/i', function($matches){
            return $matches[1] . str_repeat('*', strlen($matches[2])) . $matches[3];
        }, $message);

        return $message;
    }

    private function extract_json_object(string $content){
        $content = trim($content);
        if ($content === ''){
            return null;
        }

        if (stripos((string)$content, '```') !== false){
            $content = preg_replace('/```json/i', '', (string)$content);
            $content = str_replace('```', '', (string)$content);
            $content = trim($content);
        }

        if ($content !== '' && is_string($content) && isset($content[0]) && $content[0] !== '{'){
            $start = strpos((string)$content, '{');
            $end   = strrpos((string)$content, '}');
            if ($start !== false && $end !== false && $end > $start){
                $content = substr($content, $start, $end - $start + 1);
            }
        }

        if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
        }
        $decoded = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $content ) : null;
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        return [];
    }

    private function should_retry_without_image($error){
        if (!is_wp_error($error)){
            return false;
        }

        if ($error->get_error_code() !== 'api_error'){
            return false;
        }

        $error_message = $error->get_error_message();
        if (!is_string($error_message) || empty($error_message)) {
            return false;
        }
        $message = strtolower($error_message);
        $needles = ['error while downloading', 'failed to download', 'unsupported image url'];
        foreach ($needles as $needle){
            if (is_string($message) && strpos((string)$message, (string)$needle) !== false){
                return true;
            }
        }

        $data = $error->get_error_data();
        if (is_array($data)){
            if (!empty($data['message']) && is_string($data['message'])){
                $msg = strtolower($data['message']);
                foreach ($needles as $needle){
                    if (is_string($msg) && strpos((string)$msg, (string)$needle) !== false){
                        return true;
                    }
                }
            }
            if (!empty($data['body']['error']['message']) && is_string($data['body']['error']['message'])){
                $msg = strtolower($data['body']['error']['message']);
                foreach ($needles as $needle){
                    if (is_string($msg) && strpos((string)$msg, (string)$needle) !== false){
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function build_inline_image_payload($attachment_id){
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)){
            return new \WP_Error('inline_image_missing', __('Unable to locate the image file for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $size = filesize($file);
        if ($size === false || $size <= 0){
            return new \WP_Error('inline_image_size', __('Unable to read the image size for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $limit = apply_filters('bbai_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file);
        if ($size > $limit){
            return new \WP_Error('inline_image_too_large', __('Image exceeds the inline embedding size limit.', 'beepbeep-ai-alt-text-generator'), ['size' => $size, 'limit' => $limit]);
        }

        $fs = $this->bbai_init_wp_filesystem();
        $contents = (is_object($fs) && method_exists($fs, 'get_contents')) ? $fs->get_contents($file) : false;
        if ($contents === false){
            return new \WP_Error('inline_image_read_failed', __('Unable to read the image file for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (empty($mime)){
            $mime = function_exists('mime_content_type') ? mime_content_type($file) : 'image/jpeg';
        }

        $base64 = base64_encode($contents);
        if (!$base64){
            return new \WP_Error('inline_image_encode_failed', __('Failed to encode the image for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        unset($contents);

        return [
            'payload' => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime . ';base64,' . $base64,
                ],
            ],
        ];
    }

    private function review_alt_text_with_model(int $attachment_id, string $alt, string $image_strategy, $image_payload_used, array $opts, string $api_key){
        $alt = trim((string) $alt);
        if ($alt === ''){
            return new \WP_Error('review_skipped', __('ALT text is empty; skipped review.', 'beepbeep-ai-alt-text-generator'));
        }

        $review_model = $opts['review_model'] ?? ($opts['model'] ?? 'gpt-4o-mini');
        $review_model = apply_filters('bbai_review_model', $review_model, $attachment_id, $opts);
        if (!$review_model){
            return new \WP_Error('review_model_missing', __('No review model configured.', 'beepbeep-ai-alt-text-generator'));
        }

        $image_payload = $image_payload_used;
        if (!$image_payload) {
            if ($image_strategy === 'inline') {
                $inline = $this->build_inline_image_payload($attachment_id);
                if (!is_wp_error($inline)) {
                    $image_payload = $inline['payload'];
                }
            } else {
                $url = wp_get_attachment_url($attachment_id);
                if ($url) {
                    $image_payload = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $url,
                        ],
                    ];
                }
            }
        }

        $title = get_the_title($attachment_id);
        $file_path = get_attached_file($attachment_id);
        $filename = $file_path ? wp_basename($file_path) : '';

        $context_lines = [];
        if ($title){
            /* translators: 1: media title */
            $context_lines[] = sprintf(__('Media title: %s', 'beepbeep-ai-alt-text-generator'), $title);
        }
        if ($filename){
            /* translators: 1: filename */
            $context_lines[] = sprintf(__('Filename: %s', 'beepbeep-ai-alt-text-generator'), $filename);
        }

        $quoted_alt = str_replace('"', '\"', (string)($alt ?? ''));

        $instructions = "You are an accessibility QA assistant. Review the provided ALT text for the accompanying image. "
            . "Flag hallucinated details, inaccurate descriptions, missing primary subjects, demographic assumptions, or awkward phrasing. "
            . "Confirm the sentence mentions the main subject and at least one visible attribute such as colour, texture, motion, or background context. "
            . "Score strictly: reward ALT text only when it accurately and concisely describes the image. "
            . "If the ALT text contains placeholder wording (for example ‘test’, ‘sample’, ‘dummy text’, ‘image’, ‘photo’) anywhere in the sentence, or omits the primary subject, score it 10 or lower. "
            . "Extremely short descriptions (fewer than six words) should rarely exceed a score of 30.";

        $text_block = $instructions . "\n\n"
            . "ALT text candidate: \"" . $quoted_alt . "\"\n";

        if ($context_lines){
            $text_block .= implode("\n", $context_lines) . "\n";
        }

        $text_block .= "\nReturn valid JSON with keys: "
            . "score (integer 0-100), verdict (excellent, good, review, or critical), "
            . "summary (short sentence), and issues (array of short strings). "
            . "Do not include any additional keys or explanatory prose.";

        $user_content = [
            [
                'type' => 'text',
                'text' => $text_block,
            ],
        ];

        if ($image_payload){
            $user_content[] = $image_payload;
        }

        $bbai_body = [
            'model' => $review_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an impartial accessibility QA reviewer. Always return strict JSON and be conservative when scoring.',
                ],
                [
                    'role' => 'user',
                    'content' => $user_content,
                ],
            ],
            'temperature' => 0.1,
            'max_tokens' => 280,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body'    => wp_json_encode($bbai_body),
        ]);

        if (is_wp_error($response)){
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
        }
        $decoded = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $raw_body ) : null;
        $data = is_array( $decoded ) ? $decoded : [];

        if ($code >= 300 || empty($data['choices'][0]['message']['content'])){
            $api_message = isset($data['error']['message']) ? $data['error']['message'] : ($raw_body ?: 'OpenAI review failed.');
            $api_message = $this->redact_api_token($api_message);
            return new \WP_Error('review_api_error', $api_message, ['status' => $code, 'body' => $data]);
        }

        $content = $data['choices'][0]['message']['content'];
        $parsed = $this->extract_json_object($content);
        if (!$parsed){
            return new \WP_Error('review_parse_failed', __('Unable to parse review response.', 'beepbeep-ai-alt-text-generator'), ['response' => $content]);
        }

        $score = isset($parsed['score']) ? intval($parsed['score']) : 0;
        $score = max(0, min(100, $score));

        $verdict = isset($parsed['verdict']) ? strtolower(trim((string) $parsed['verdict'])) : '';
        $status_map = [
            'excellent' => 'great',
            'great'     => 'great',
            'good'      => 'good',
            'strong'    => 'good',
            'review'    => 'review',
            'needs review' => 'review',
            'warning'   => 'review',
            'critical'  => 'critical',
            'fail'      => 'critical',
            'poor'      => 'critical',
        ];
        $status = $status_map[$verdict] ?? null;
        if (!$status){
            $status = $this->status_from_score($score);
        }

        $summary = isset($parsed['summary']) ? sanitize_text_field($parsed['summary']) : '';
        if (!$summary && isset($parsed['justification'])){
            $summary = sanitize_text_field($parsed['justification']);
        }

        $issues = [];
        if (!empty($parsed['issues']) && is_array($parsed['issues'])){
            foreach ($parsed['issues'] as $issue){
                $issue = sanitize_text_field($issue);
                if ($issue !== ''){
                    $issues[] = $issue;
                }
            }
        }

        $issues = array_values(array_unique($issues));

        $usage_summary = [
            'prompt'     => intval($data['usage']['prompt_tokens'] ?? 0),
            'completion' => intval($data['usage']['completion_tokens'] ?? 0),
            'total'      => intval($data['usage']['total_tokens'] ?? 0),
        ];

        return [
            'score'   => $score,
            'status'  => $status,
            'grade'   => $this->grade_from_status($status),
            'summary' => $summary,
            'issues'  => $issues,
            'model'   => $review_model,
            'usage'   => $usage_summary,
            'verdict' => $verdict,
        ];
    }

    public function persist_generation_result(int $attachment_id, string $alt, array $usage_summary, string $source, string $model, string $image_strategy, $review_result): void{
        $this->clear_user_approval_meta($attachment_id);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags($alt));
        update_post_meta($attachment_id, '_bbai_source', $source);
        update_post_meta($attachment_id, '_bbai_model', $model);
        update_post_meta($attachment_id, '_bbai_generated_at', current_time('mysql'));
        update_post_meta($attachment_id, '_bbai_tokens_prompt', $usage_summary['prompt']);
        update_post_meta($attachment_id, '_bbai_tokens_completion', $usage_summary['completion']);
        update_post_meta($attachment_id, '_bbai_tokens_total', $usage_summary['total']);

        if ($image_strategy === 'remote'){
            delete_post_meta($attachment_id, '_bbai_image_reference');
        } else {
            update_post_meta($attachment_id, '_bbai_image_reference', $image_strategy);
        }

        if (!is_wp_error($review_result) && is_array($review_result)){
            // Never persist a remote/API score above the deterministic local cap (hard-fails, thin ALT, etc.).
            if (class_exists('\BBAI_Alt_Quality_Scorer')) {
                $ctx = $this->build_generation_context_for_attachment($attachment_id);
                $local = \BBAI_Alt_Quality_Scorer::score($alt, $ctx);
                $api_score = (int) ($review_result['score'] ?? 0);
                $review_result['score'] = min($api_score, (int) $local['score']);
            }
            update_post_meta($attachment_id, '_bbai_review_score', $review_result['score']);
            update_post_meta($attachment_id, '_bbai_review_status', $review_result['status']);
            update_post_meta($attachment_id, '_bbai_review_grade', $review_result['grade']);
            update_post_meta($attachment_id, '_bbai_review_summary', $review_result['summary']);
            update_post_meta($attachment_id, '_bbai_review_issues', wp_json_encode($review_result['issues']));
            update_post_meta($attachment_id, '_bbai_review_model', $review_result['model']);
            update_post_meta($attachment_id, '_bbai_reviewed_at', current_time('mysql'));
            update_post_meta($attachment_id, '_bbai_review_alt_hash', $this->hash_alt_text($alt));
            // Keep in sync with Core_Review::REVIEW_SCORING_VERSION (invalidates stale cached LLM scores).
            update_post_meta($attachment_id, '_bbai_review_version', 3);
            delete_post_meta($attachment_id, '_bbai_review_error');
            if (!empty($review_result['usage'])){
                $this->record_usage($review_result['usage']);
            }
        } elseif (is_wp_error($review_result)) {
            update_post_meta($attachment_id, '_bbai_review_error', $review_result->get_error_message());
        }

        // Invalidate stats cache after persisting all generation data
        $this->invalidate_stats_cache();
    }

    /**
     * Build API generation context for an attachment.
     *
     * Includes optional WooCommerce product context when enabled.
     *
     * @param int $attachment_id Attachment ID.
     * @return array<string, mixed>
     */
    private function build_generation_context_for_attachment(int $attachment_id): array {
        $post = get_post($attachment_id);
        $file_path = get_attached_file($attachment_id);
        $filename = $file_path ? basename($file_path) : '';
        $title = get_the_title($attachment_id);

        $context = [
            'filename' => $filename,
            'title' => is_string($title) ? $title : '',
            'caption' => $post && isset($post->post_excerpt) ? (string) $post->post_excerpt : '',
            'post_title' => '',
        ];

        if ($post && $post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent && isset($parent->post_title)) {
                $context['post_title'] = is_string($parent->post_title) ? $parent->post_title : '';
            }
        }

        $woo_context = $this->get_woocommerce_context_for_attachment($attachment_id);
        if (!empty($woo_context)) {
            $context = array_merge($context, $woo_context);
        }

        return $context;
    }

    /**
     * Check whether WooCommerce product context is enabled in plugin settings.
     *
     * @return bool
     */
    private function is_woocommerce_context_enabled(): bool {
        if (!class_exists('\WooCommerce')) {
            return false;
        }

        $opts = get_option(self::OPTION_KEY, []);
        return !empty($opts['woocommerce_context_enabled']);
    }

    /**
     * Resolve WooCommerce context for an attachment.
     *
     * @param int $attachment_id Attachment ID.
     * @return array<string, mixed>
     */
    private function get_woocommerce_context_for_attachment(int $attachment_id): array {
        if (!$this->is_woocommerce_context_enabled()) {
            return [];
        }

        if (!function_exists('wc_get_product')) {
            return [];
        }

        $product_id = 0;
        $attachment = get_post($attachment_id);

        if ($attachment instanceof \WP_Post) {
            $parent_id = (int) $attachment->post_parent;
            if ($parent_id > 0) {
                $parent_type = get_post_type($parent_id);
                if ($parent_type === 'product') {
                    $product_id = $parent_id;
                } elseif ($parent_type === 'product_variation') {
                    $product_id = (int) wp_get_post_parent_id($parent_id);
                }
            }
        }

        if ($product_id <= 0) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $thumb_owner = $wpdb->get_var(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifier comes from trusted core $wpdb.
                    'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value = %d LIMIT 1',
                    '_thumbnail_id',
                    $attachment_id
                )
            );
            $thumb_owner = absint($thumb_owner);
            if ($thumb_owner > 0 && get_post_type($thumb_owner) === 'product') {
                $product_id = $thumb_owner;
            }
        }

        if ($product_id <= 0) {
            return [];
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return [];
        }

        $attribute_chunks = [];
        $attributes = $product->get_attributes();
        if (is_array($attributes)) {
            foreach ($attributes as $attribute) {
                if (!is_object($attribute) || !method_exists($attribute, 'get_name')) {
                    continue;
                }

                $label = wc_attribute_label($attribute->get_name());
                $values = [];
                if (method_exists($attribute, 'is_taxonomy') && $attribute->is_taxonomy()) {
                    $values = wc_get_product_terms($product_id, $attribute->get_name(), ['fields' => 'names']);
                } elseif (method_exists($attribute, 'get_options')) {
                    $values = $attribute->get_options();
                }

                $values = array_values(array_filter(array_map('sanitize_text_field', (array) $values)));
                if (empty($values)) {
                    continue;
                }

                $attribute_chunks[] = sanitize_text_field($label) . ': ' . implode(', ', $values);
            }
        }

        return [
            'context_type' => 'woocommerce_product',
            'product_id' => $product_id,
            'product_title' => sanitize_text_field($product->get_name()),
            'product_sku' => sanitize_text_field((string) $product->get_sku()),
            'product_attributes' => implode(' | ', $attribute_chunks),
        ];
    }

    public function maybe_generate_on_upload($attachment_id){
        $opts = get_option(self::OPTION_KEY, []);
        if (!$this->is_upload_generation_enabled($opts)) return;
        if (!$this->is_image($attachment_id)) return;
        $this->invalidate_stats_cache();
        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($existing && empty($opts['force_overwrite'])) return;
        // Respect monthly limit and surface upgrade prompt as admin notice
        if ($this->api_client->has_reached_limit()){
            set_transient('beepbeepai_limit_notice', 1, MINUTE_IN_SECONDS * 10);
            return;
        }
        $this->generate_and_save($attachment_id, 'auto');
    }

    public function generate_and_save($attachment_id, $source='manual', int $retry_count = 0, array $feedback = [], $regenerate = false, bool $skip_trial_gate = false){
        $opts = get_option(self::OPTION_KEY, []);

        if ( ! $skip_trial_gate ) {
            // Trial quota gate: block unauthenticated users who have exhausted their free trial.
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
            $bbai_trial_check = \BeepBeepAI\AltTextGenerator\Trial_Quota::check();
            if ( is_wp_error( $bbai_trial_check ) ) {
                return $bbai_trial_check;
            }
        }

        // Allocate free credits on first generation request (one-time per site)
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
        \BeepBeepAI\AltTextGenerator\Usage_Tracker::allocate_free_credits_if_needed();

        // Capture current user ID for credit tracking
        // Use 0 for anonymous/system users (auto-upload, queue processing, etc.)
        $user_id = get_current_user_id();
        
        // For AJAX/REST calls, try to get user from nonce/authentication
        if ($user_id <= 0 && ($source === 'ajax' || $source === 'inline' || $source === 'manual')) {
            // Check if we're in a REST API context
            if (defined('REST_REQUEST') && REST_REQUEST) {
                // REST API should have authenticated user via cookie/nonce
                $user_id = get_current_user_id();
            }
            // For AJAX calls, get_current_user_id() should work if user is logged in
            // If still 0, it means user is not authenticated
        }
        
        // Set to 0 for system/automated operations only
        if ($user_id <= 0 || $source === 'auto' || $source === 'queue' || $source === 'wpcli') {
            $user_id = 0; // Track as "System" for anonymous/automated operations
        }

        // Skip authentication check in local development mode
        $bbai_has_license = $this->api_client->has_active_license();
        if (!$bbai_has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            // Check site-wide quota before generation
            // Wrap in try-catch to prevent PHP errors from breaking REST responses
            // Use has_reached_limit() instead of Token_Quota_Service for consistency
            // has_reached_limit() already includes proper cache checking and fallback logic
            // This prevents false "quota exhausted" errors from stale cache data
            try {
                if ($this->api_client->has_reached_limit()) {
                    // Get usage data for the error response
                    $usage = $this->api_client->get_usage();
                    if (is_wp_error($usage)) {
                        // Fall back to cached usage for display
                        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                        $usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_local_usage_snapshot();
                    }
                    
                    $bbai_reset_date = isset($usage['resetDate']) ? $usage['resetDate'] : null;
                    $reset_message = __('Monthly quota exhausted. Upgrade to Growth for 1,000 generations per month, or wait for your quota to reset. You can manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator');
                    
                    if ($bbai_reset_date) {
                        try {
                            $reset_ts = strtotime($bbai_reset_date);
                            if ($reset_ts !== false) {
                                $bbai_formatted_date = date_i18n('F j, Y', $reset_ts);
                                $reset_message = sprintf(
                                    /* translators: 1: reset date */
                                    __('Monthly quota exhausted. Your quota will reset on %s. Upgrade to Growth for 1,000 generations per month, or manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator'),
                                    $bbai_formatted_date
                                );
                            }
                        } catch (\Exception $e) {
                            // Keep default message if date parsing fails
                        }
                    }
                    
                    return new \WP_Error(
                        'limit_reached',
                        $reset_message,
                        ['code' => 'quota_exhausted', 'usage' => is_array($usage) ? $usage : null]
                    );
                }
            } catch ( \Exception $e ) {
                // If quota check fails due to error, don't block generation
                // Backend will handle usage limits
                // Silent failure - generation will proceed
            }
        }

        if (!$this->is_image($attachment_id)) {
            return new \WP_Error('not_image', __('Attachment is not an image.', 'beepbeep-ai-alt-text-generator'));
        }

        // Prefer higher-quality default for better accuracy
        $model = apply_filters('bbai_model', $opts['model'] ?? 'gpt-4o', $attachment_id, $opts);
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $prompt = $this->build_prompt($attachment_id, $opts, $existing_alt, $retry_count > 0, $feedback);

        if (!empty($opts['dry_run'])){
            update_post_meta($attachment_id, '_bbai_last_prompt', $prompt);
            update_post_meta($attachment_id, '_bbai_source', 'dry-run');
            update_post_meta($attachment_id, '_bbai_model', $model);
            update_post_meta($attachment_id, '_bbai_generated_at', current_time('mysql'));
            $this->stats_cache = null;
            return new \WP_Error('bbai_dry_run', __('Dry run enabled. Prompt stored for review; ALT text not updated.', 'beepbeep-ai-alt-text-generator'), ['prompt' => $prompt]);
        }

        $context = $this->build_generation_context_for_attachment((int) $attachment_id);

        // Always call the real API to generate actual alt text
        // (Mock mode disabled - we want real AI-generated descriptions)
        $api_response = $this->api_client->generate_alt_text($attachment_id, $context, $regenerate);

        if (is_wp_error($api_response)) {
            $error_code = $api_response->get_error_code();
            $error_message = $api_response->get_error_message();
            $error_data = $api_response->get_error_data();
            
            // Check if this is a quota/limit error - verify against cached usage
            // The backend API might incorrectly report quota exhausted when credits are available
            $error_message_lower = strtolower($error_message);
            $api_error_code = '';
            if ( is_array( $error_data ) ) {
                if ( isset( $error_data['code'] ) && is_scalar( $error_data['code'] ) ) {
                    $api_error_code = strtolower( (string) $error_data['code'] );
                } elseif (
                    isset( $error_data['api_response'] ) &&
                    is_array( $error_data['api_response'] ) &&
                    isset( $error_data['api_response']['code'] ) &&
                    is_scalar( $error_data['api_response']['code'] )
                ) {
                    $api_error_code = strtolower( (string) $error_data['api_response']['code'] );
                }
            }
            $status_code = ( is_array( $error_data ) && isset( $error_data['status_code'] ) ) ? intval( $error_data['status_code'] ) : 0;
            $is_quota_error = (
                $error_code === 'limit_reached' ||
                $error_code === 'quota_exhausted' ||
                $error_code === 'quota_check_mismatch' ||
                in_array( $api_error_code, [ 'quota_exhausted', 'quota_exceeded', 'limit_reached', 'quota_check_mismatch' ], true ) ||
                strpos( $error_message_lower, 'quota exceeded' ) !== false ||
                strpos( $error_message_lower, 'quota exhausted' ) !== false ||
                strpos( $error_message_lower, 'monthly limit' ) !== false ||
                strpos( $error_message_lower, 'monthly quota' ) !== false ||
                strpos( $error_message_lower, 'limit reached' ) !== false ||
                in_array( $status_code, [ 402, 429 ], true )
            );
            
            // If it's a quota_check_mismatch error (from API client cache check), allow retry
            if ($error_code === 'quota_check_mismatch') {
                // This is from our cache validation - suggest retry but still return the error
                // The frontend should handle retry based on the error code
            } elseif ($is_quota_error) {
                // Check cached usage before accepting the backend's quota error
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_local_usage_snapshot();
                
                if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] > 0) {
                    // Cached usage shows credits available - backend error might be incorrect
                    // Clear cache and do a fresh check to see actual status
                    \BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
                    $fresh_usage = $this->api_client->get_usage();
                    
                    if (!is_wp_error($fresh_usage) && is_array($fresh_usage) && isset($fresh_usage['remaining']) && is_numeric($fresh_usage['remaining']) && $fresh_usage['remaining'] > 0) {
                        // Fresh API check shows credits available - backend quota error was wrong
                        // Update cache and return a retry error instead of blocking
                        \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($fresh_usage);
                        
                        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                            Debug_Log::log('warning', 'Backend reported quota exhausted but cache and fresh API check show credits available', [
                                'attachment_id' => $attachment_id,
                                'cached_remaining' => $cached_usage['remaining'],
                                'api_remaining' => $fresh_usage['remaining'],
                                'backend_error' => $error_message,
                                'error_code' => $error_code,
                            ], 'generation');
                        }
                        
                        // Return a retry error instead of blocking
                        return new \WP_Error(
                            'quota_check_mismatch',
                            __('Backend reported quota limit, but credits appear available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator'),
                            ['code' => 'quota_check_mismatch', 'retry_after' => 3, 'usage' => $fresh_usage]
                        );
                    }
                }
            }
            
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                // Get error data for detailed logging
                // Sanitize error message to prevent exposing sensitive API information
                $sanitized_message = $this->sanitize_error_message($error_message);
                
                // Build detailed context for logging
                $log_context = [
                    'attachment_id' => $attachment_id,
                    'code' => $error_code,
                    'message' => $sanitized_message,
                ];
                
                // Include additional error data if available (but sanitize it)
                if (is_array($error_data)) {
                    if (isset($error_data['status_code'])) {
                        $log_context['status_code'] = $error_data['status_code'];
                    }
                    if (isset($error_data['api_response']) && is_array($error_data['api_response'])) {
                        // Include API response details but sanitize sensitive fields
                        $api_resp = $error_data['api_response'];
                        $log_context['api_error_code'] = $api_resp['code'] ?? null;
                        $log_context['api_error_message'] = isset($api_resp['message']) ? $this->sanitize_error_message($api_resp['message']) : null;
                    }
                }
                if ( '' !== $api_error_code ) {
                    $log_context['normalized_api_error_code'] = $api_error_code;
                }
                $log_context['quota_event'] = $is_quota_error;
                $is_quota_mismatch = ( $error_code === 'quota_check_mismatch' || $api_error_code === 'quota_check_mismatch' );
                if ( $is_quota_mismatch ) {
                    $log_level = 'warning';
                    $log_message = 'Quota status mismatch detected; retry advised';
                } else {
                    $log_level = $is_quota_error ? 'warning' : 'error';
                    $log_message = $is_quota_error ? 'Alt text generation blocked by quota limit' : 'Alt text generation failed';
                }
                
                Debug_Log::log(
                    $log_level,
                    $log_message,
                    $log_context,
                    'generation'
                );
            }
            return $api_response;
        }

        // The api_response is $response['data'] from generate_alt_text()
        // If generate_alt_text() returns WP_Error, it's already handled above
        // So at this point, api_response should be the data object
        // Validate that altText (camelCase) or alt_text (snake_case) exists in the response
        // Backend returns altText (camelCase), but support both for compatibility (and nested payloads)
        $alt_source = null;
        $alt_text = $this->extract_alt_text_from_response($api_response, $alt_source);
        if (empty($alt_text)) {
            $error_message = __('Backend API returned response but no alt text was generated.', 'beepbeep-ai-alt-text-generator');
            
            // Log this error with full response structure for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('error', 'Alt text generation failed - missing altText/alt_text in response', [
                    'attachment_id' => $attachment_id,
                    'response_keys' => is_array($api_response) ? array_keys($api_response) : 'not array',
                    'response_type' => gettype($api_response),
                    'has_altText' => isset($api_response['altText']),
                    'has_alt_text' => isset($api_response['alt_text']),
                    'alt_text_source' => $alt_source ?? 'none',
                    'has_usage' => isset($api_response['usage']),
                    'has_tokens' => isset($api_response['tokens']),
                    'response_preview' => is_array($api_response) ? wp_json_encode(array_slice($api_response, 0, 5)) : 'not array',
                ], 'generation');
            }
            
            // DO NOT update usage or log credits if alt_text is missing
            // The backend may have consumed credits, but we shouldn't record it as successful usage
            return new \WP_Error('missing_alt_text', $error_message, ['code' => 'api_response_invalid']);
        }
        
        // Normalize to alt_text for consistent usage throughout the codebase
        $api_response['alt_text'] = $alt_text;

        // Refresh license usage data when backend returns updated organization details
        if ($bbai_has_license && !empty($api_response['organization'])) {
            $existing_license = $this->api_client->get_license_data() ?? [];
            $updated_license  = $existing_license;
            $updated_license['organization'] = array_merge(
                $existing_license['organization'] ?? [],
                $api_response['organization']
            );
            if (!empty($api_response['site'])) {
                $updated_license['site'] = $api_response['site'];
            }
            $updated_license['updated_at'] = current_time('mysql');
            $this->api_client->set_license_data($updated_license);
            Usage_Tracker::clear_cache();
        }

        // CRITICAL: Only update usage AFTER we've confirmed alt_text exists
        // This ensures we NEVER log credits for failed generations, even if backend consumed them
        // The backend may have consumed credits when calling OpenAI, but if alt_text is missing,
        // we should NOT record it as successful usage locally
        
        // Normalize any refreshed post-generation usage fields into the shared
        // usage shape used by the plugin UI. Credit counters may still arrive at
        // the response root, while nested `usage` carries model-token metadata.
        $bbai_usage_data = [];
        
        // Get credits from root level (primary source)
        if (isset($api_response['credits_used'])) {
            $bbai_usage_data['used'] = intval($api_response['credits_used']);
        }
        if (isset($api_response['credits_remaining'])) {
            $bbai_usage_data['remaining'] = intval($api_response['credits_remaining']);
        }
        // Get limit from root level if provided (check both 'total_limit' and 'limit' for compatibility)
        if (isset($api_response['total_limit'])) {
            $bbai_usage_data['limit'] = intval($api_response['total_limit']);
        } elseif (isset($api_response['limit'])) {
            $bbai_usage_data['limit'] = intval($api_response['limit']);
        } elseif (isset($bbai_usage_data['used']) && isset($bbai_usage_data['remaining'])) {
            $bbai_usage_data['limit'] = $bbai_usage_data['used'] + $bbai_usage_data['remaining'];
        }
        
        // Get token info from nested usage object if available
        if (!empty($api_response['usage']) && is_array($api_response['usage'])) {
            if (isset($api_response['usage']['prompt_tokens'])) {
                $bbai_usage_data['prompt_tokens'] = intval($api_response['usage']['prompt_tokens']);
            }
            if (isset($api_response['usage']['completion_tokens'])) {
                $bbai_usage_data['completion_tokens'] = intval($api_response['usage']['completion_tokens']);
            }
            if (isset($api_response['usage']['total_tokens'])) {
                $bbai_usage_data['total_tokens'] = intval($api_response['usage']['total_tokens']);
            }
        }
        
        // Log token usage prominently for each generation
        if (!empty($bbai_usage_data) && (isset($bbai_usage_data['prompt_tokens']) || isset($bbai_usage_data['completion_tokens']) || isset($bbai_usage_data['total_tokens']))) {
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                $prompt_tokens = isset($bbai_usage_data['prompt_tokens']) ? intval($bbai_usage_data['prompt_tokens']) : 0;
                $completion_tokens = isset($bbai_usage_data['completion_tokens']) ? intval($bbai_usage_data['completion_tokens']) : 0;
                $total_tokens = isset($bbai_usage_data['total_tokens']) ? intval($bbai_usage_data['total_tokens']) : 0;
                
                $token_summary = sprintf(
                    'Token Usage: %s prompt + %s completion = %s total tokens',
                    $prompt_tokens > 0 ? number_format($prompt_tokens) : 'N/A',
                    $completion_tokens > 0 ? number_format($completion_tokens) : 'N/A',
                    $total_tokens > 0 ? number_format($total_tokens) : 'N/A'
                );
                
                Debug_Log::log('info', $token_summary, [
                    'attachment_id' => $attachment_id,
                    'prompt_tokens' => $bbai_usage_data['prompt_tokens'] ?? 0,
                    'completion_tokens' => $bbai_usage_data['completion_tokens'] ?? 0,
                    'total_tokens' => $bbai_usage_data['total_tokens'] ?? 0,
                    'alt_text_length' => strlen($alt_text ?? ''),
                    'model' => (isset($api_response['meta']) && is_array($api_response['meta'])) ? ($api_response['meta']['modelUsed'] ?? 'unknown') : 'unknown',
                    'generation_time_ms' => (isset($api_response['meta']) && is_array($api_response['meta'])) ? ($api_response['meta']['generation_time_ms'] ?? null) : null,
                ], 'generation');
            }
        }
        
        // Update usage if we have credits information from generation response
        if (!empty($bbai_usage_data) && (isset($bbai_usage_data['used']) || isset($bbai_usage_data['remaining']))) {
            // Log what we're updating for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Updating usage cache after generation', [
                    'usage_data' => $bbai_usage_data,
                    'has_used' => isset($bbai_usage_data['used']),
                    'has_remaining' => isset($bbai_usage_data['remaining']),
                    'has_limit' => isset($bbai_usage_data['limit']),
                    'api_response_keys' => array_keys($api_response),
                    'credits_used_in_response' => $api_response['credits_used'] ?? 'not set',
                    'credits_remaining_in_response' => $api_response['credits_remaining'] ?? 'not set',
                    'total_limit_in_response' => $api_response['total_limit'] ?? 'not set',
                    'limit_in_response' => $api_response['limit'] ?? 'not set',
                ], 'generation');
            }

            Usage_Tracker::update_usage($bbai_usage_data);
        } else {
            // Generation response didn't include credits info - fetch fresh usage from API
            // This ensures the dashboard shows accurate counts even if the backend doesn't
            // return credits in the generation response
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Generation response missing credits info, fetching fresh usage from API', [
                    'api_response_keys' => is_array($api_response) ? array_keys($api_response) : 'not array',
                ], 'generation');
            }

            // Clear the cached usage to force a fresh fetch
            Usage_Tracker::clear_cache();

            // Fetch fresh usage from the API
            $fresh_usage = $this->api_client->get_usage();
            if (!is_wp_error($fresh_usage) && is_array($fresh_usage)) {
                Usage_Tracker::update_usage($fresh_usage);
                $bbai_usage_data = $fresh_usage;

                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    Debug_Log::log('info', 'Fresh usage fetched and cached after generation', [
                        'used' => $fresh_usage['used'] ?? 'not set',
                        'remaining' => $fresh_usage['remaining'] ?? 'not set',
                        'limit' => $fresh_usage['limit'] ?? 'not set',
                    ], 'generation');
                }
            }
        }

        // Also log what was actually cached (runs after either path updates usage)
        $cached_after = Usage_Tracker::get_cached_usage(false);
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Usage cache updated - verifying', [
                'cached_usage' => $cached_after,
                'cached_used' => $cached_after['used'] ?? 'not set',
                'cached_remaining' => $cached_after['remaining'] ?? 'not set',
                'cached_limit' => $cached_after['limit'] ?? 'not set',
            ], 'generation');
        }

        // Update license data if user has a license
        if ($bbai_has_license && !empty($bbai_usage_data)) {
            $existing_license = $this->api_client->get_license_data() ?? [];
            $updated_license  = $existing_license ?: [];
            $organization     = $updated_license['organization'] ?? [];

            // Persist the normalized quota model used by the backend /usage endpoint.
            if (isset($bbai_usage_data['limit'])) {
                $organization['limit'] = intval($bbai_usage_data['limit']);
            } elseif (isset($bbai_usage_data['used']) && isset($bbai_usage_data['remaining'])) {
                $organization['limit'] = intval($bbai_usage_data['used']) + intval($bbai_usage_data['remaining']);
            }

            if (isset($bbai_usage_data['used'])) {
                $organization['used'] = max(0, intval($bbai_usage_data['used']));
            }

            if (isset($bbai_usage_data['remaining'])) {
                $organization['remaining'] = max(0, intval($bbai_usage_data['remaining']));
            } elseif (isset($bbai_usage_data['used']) && isset($organization['limit'])) {
                $organization['remaining'] = max(0, intval($organization['limit']) - intval($bbai_usage_data['used']));
            } elseif (isset($bbai_usage_data['limit']) && isset($bbai_usage_data['used'])) {
                $organization['remaining'] = max(0, intval($bbai_usage_data['limit']) - intval($bbai_usage_data['used']));
                if (!isset($organization['limit'])) {
                    $organization['limit'] = intval($bbai_usage_data['limit']);
                }
            }

            if (isset($organization['used'])) {
                $organization['creditsUsed'] = intval($organization['used']);
            }
            if (isset($organization['limit'])) {
                $organization['creditsTotal'] = intval($organization['limit']);
                $organization['creditsLimit'] = intval($organization['limit']);
            }
            if (isset($organization['remaining'])) {
                $organization['creditsRemaining'] = max(0, intval($organization['remaining']));
            }

            unset($organization['token' . 'Limit'], $organization['tokens' . 'Remaining']);

            // Log the update for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Updating license organization data with usage', [
                    'used' => $organization['used'] ?? 'not set',
                    'limit' => $organization['limit'] ?? 'not set',
                    'remaining' => $organization['remaining'] ?? 'not set',
                    'usage_data' => $bbai_usage_data,
                ], 'generation');
            }

            // Get reset date from api_response root level if available
            if (isset($api_response['resetDate']) && !empty($api_response['resetDate'])) {
                $organization['resetDate'] = sanitize_text_field($api_response['resetDate']);
            } elseif (isset($api_response['reset_date']) && !empty($api_response['reset_date'])) {
                $organization['resetDate'] = sanitize_text_field($api_response['reset_date']);
            } elseif (!empty($api_response['usage']['resetDate'])) {
                $organization['resetDate'] = sanitize_text_field($api_response['usage']['resetDate']);
            } elseif (!empty($api_response['usage']['nextReset'])) {
                $organization['resetDate'] = sanitize_text_field($api_response['usage']['nextReset']);
            }

            if (!empty($api_response['usage']['plan'])) {
                $organization['plan'] = sanitize_key($api_response['usage']['plan']);
            }

            $updated_license['organization'] = $organization;
            $updated_license['updated_at'] = current_time('mysql');
            $this->api_client->set_license_data($updated_license);
        }
        
        $alt = trim($api_response['alt_text']);
        $usage_summary = $api_response['tokens'] ?? ['prompt' => 0, 'completion' => 0, 'total' => 0];
        
        $result = [
            'alt' => $alt,
            'usage' => [
                'prompt' => intval($usage_summary['prompt_tokens'] ?? 0),
                'completion' => intval($usage_summary['completion_tokens'] ?? 0),
                'total' => intval($usage_summary['total_tokens'] ?? 0),
            ]
        ];

        $image_strategy = 'api-proxy';

        $review_result = null;
        if (!empty($api_response['review']) && is_array($api_response['review'])) {
            $review = $api_response['review'];
            $issues = [];
            if (!empty($review['issues']) && is_array($review['issues'])) {
                foreach ($review['issues'] as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $issues[] = sanitize_text_field($issue);
                    }
                }
            }

            $review_usage = [
                'prompt' => intval($review['usage']['prompt_tokens'] ?? 0),
                'completion' => intval($review['usage']['completion_tokens'] ?? 0),
                'total' => intval($review['usage']['total_tokens'] ?? 0),
            ];

            $review_result = [
                'score' => intval($review['score'] ?? 0),
                'status' => sanitize_key($review['status'] ?? ''),
                'grade' => sanitize_text_field($review['grade'] ?? ''),
                'summary' => isset($review['summary']) ? sanitize_text_field($review['summary']) : '',
                'issues' => $issues,
                'model' => sanitize_text_field($review['model'] ?? ''),
                'usage' => $review_usage,
            ];
        }

        // Check if generated alt is same as existing (unlikely but possible)
        // Skip this check when regenerating - user explicitly wants to regenerate
            if (!is_wp_error($result) && $existing_alt && !$regenerate){
                $generated = trim($result['alt']);
                if (strcasecmp($generated, trim($existing_alt)) === 0){
                $result = new \WP_Error(
                    'duplicate_alt',
                    __('Generated ALT text matched the existing value.', 'beepbeep-ai-alt-text-generator'),
                    [
                        'existing' => $existing_alt,
                        'generated' => $generated,
                    ]
                );
            }
        }

        if (is_wp_error($result)){
            return $result;
        }

        $usage_summary = $result['usage'];
        $alt = $result['alt'];

        // Log credit usage for this generation
        // Backend tracks 1 credit per generation, not based on tokens
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        // Always use 1 credit per generation (backend API charges 1 credit per alt text generation)
        $credits_used = 1;
        // Try to get token cost from usage summary if available (for reporting)
        $token_cost = null;
        if (isset($usage_summary['cost']) && is_numeric($usage_summary['cost'])) {
            $token_cost = floatval($usage_summary['cost']);
        }
        // Get model from usage summary or use default
        $model_used = isset($usage_summary['model']) ? sanitize_text_field($usage_summary['model']) : $model;
        \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::log_usage(
            $attachment_id,
            $user_id,
            $credits_used,
            $token_cost,
            $model_used,
            $source
        );

        // Log usage event for multi-user visualization
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';
        $action_type = $regenerate ? 'regenerate' : ($source === 'bulk' || $source === 'bulk-regenerate' ? 'bulk' : 'generate');
        $tokens_used = isset($usage_summary['total']) ? intval($usage_summary['total']) : (isset($usage_summary['total_tokens']) ? intval($usage_summary['total_tokens']) : 1);
        $context = [
            'image_id' => $attachment_id,
            'post_id'  => null,
        ];
        // Get post ID if attachment has a parent
        $attachment = get_post($attachment_id);
        if ($attachment && $attachment->post_parent > 0) {
            $context['post_id'] = $attachment->post_parent;
        }
        \BeepBeepAI\AltTextGenerator\Usage\record_usage_event($user_id, $tokens_used, $action_type, $context);

        $this->record_usage($usage_summary);
        
        // Note: We do NOT call Token_Quota_Service::record_local_usage() here because:
        // 1. We already update usage correctly via Usage_Tracker::update_usage() above (line 3303)
        // 2. Usage_Tracker gets the correct credits from the backend API response
        // 3. Calling the legacy quota cache shim here would double-count and treat request units as credits
        // 4. The backend API response already contains the accurate credits_used value
        
        if ($bbai_has_license) {
            $this->refresh_license_usage_snapshot();
        }

        // Note: QA review is disabled for API proxy version (quality handled server-side)
        // Persist the generated alt text
        $this->persist_generation_result($attachment_id, $alt, $usage_summary, $source, $model, $image_strategy, $review_result);
        $this->maybe_emit_alt_generated_event(
            (int) $attachment_id,
            (string) $alt,
            (string) $source,
            (string) $model,
            is_array($context) ? $context : [],
            isset($api_response['meta']) && is_array($api_response['meta']) ? $api_response['meta'] : []
        );

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Alt text updated', [
                'attachment_id' => $attachment_id,
                'source' => $source,
                'regenerate' => (bool) $regenerate,
            ], 'generation');
        }

        // Trial-source requests pre-claim quota in AJAX handlers; avoid double counting here.
        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() && 'trial' !== $source ) {
            \BeepBeepAI\AltTextGenerator\Trial_Quota::increment();
        }

        return $alt;
    }

    private function queue_attachment($attachment_id, $source = 'auto'){
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0 || !$this->is_image($attachment_id)) {
            return false;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted() ) {
            return false;
        }

        $source_key = $source ? sanitize_key((string) $source) : 'auto';
        $opts = get_option(self::OPTION_KEY, []);
        if (
            in_array($source_key, ['auto', 'upload', 'metadata', 'update', 'save'], true) &&
            !$this->is_upload_generation_enabled($opts)
        ) {
            return false;
        }

        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($existing && empty($opts['force_overwrite'])) {
            return false;
        }

        return Queue::enqueue($attachment_id, $source_key);
    }

    public function register_bulk_action($bulk_actions){
        // Media Library bulk action is intentionally disabled.
        if (isset($bulk_actions['bbai_generate'])) {
            unset($bulk_actions['bbai_generate']);
        }
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids){
        // Media Library bulk action is intentionally disabled.
        return $redirect_to;
    }

    public function row_action_link($actions, $post){
        // Media Library row action is intentionally disabled.
        if (isset($actions['bbai_generate_single'])) {
            unset($actions['bbai_generate_single']);
        }
        return $actions;
    }

    public function attachment_fields_to_edit($fields, $post){
        // Media Library attachment panel button is intentionally disabled.
        if (isset($fields['bbai_regenerate'])) {
            unset($fields['bbai_regenerate']);
        }
        return $fields;
    }

	/**
	 * @deprecated 4.3.0 Use REST_Controller::register_routes().
	 */
	public function register_rest_routes(){
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\REST_Controller' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-rest-controller.php';
		}

		( new REST_Controller( $this ) )->register_routes();
	}

    /**
     * Whitelist and sanitize API user data before exposing it to JS via wp_localize_script().
     *
     * The API response can include sensitive fields (for example license keys). Only
     * include the minimal safe subset needed by the UI.
     *
     * @param mixed $user_data Raw user data from API client / options.
     * @return array Sanitized user data safe for client-side consumption.
     */
	    private function sanitize_api_user_data_for_localize($user_data): array {
        if (!is_array($user_data)) {
            return [];
        }

        $sanitized = [];

        // Flat allowlist: key => sanitizer.
        $allowed = [
            'id'              => 'sanitize_text_field',
            '_id'             => 'sanitize_text_field',
            'email'           => 'sanitize_email',
            'firstName'       => 'sanitize_text_field',
            'lastName'        => 'sanitize_text_field',
            'plan'            => 'sanitize_key',
            'planSlug'        => 'sanitize_key',
            'plan_type'       => 'sanitize_key',
            'used'            => 'absint',
            'limit'           => 'absint',
            'remaining'       => 'absint',
            'creditsUsed'     => 'absint',
            'creditsTotal'    => 'absint',
            'creditsLimit'    => 'absint',
            'creditsRemaining'=> 'absint',
            'resetDate'       => 'sanitize_text_field',
            'reset_date'      => 'sanitize_text_field',
        ];

        foreach ($allowed as $key => $sanitizer) {
            if (!array_key_exists($key, $user_data)) {
                continue;
            }

            $value = $user_data[$key];
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($sanitizer === 'absint') {
                $sanitized[$key] = absint($value);
                continue;
            }

            $sanitized[$key] = call_user_func($sanitizer, (string) $value);
        }

        // Optional nested org summary (still allowlisted).
        if (isset($user_data['organization']) && is_array($user_data['organization'])) {
            $org_allowed = [
                'id'              => 'sanitize_text_field',
                '_id'             => 'sanitize_text_field',
                'name'            => 'sanitize_text_field',
                'plan'            => 'sanitize_key',
                'plan_type'       => 'sanitize_key',
                'used'            => 'absint',
                'limit'           => 'absint',
                'remaining'       => 'absint',
                'creditsUsed'     => 'absint',
                'creditsTotal'    => 'absint',
                'creditsLimit'    => 'absint',
                'creditsRemaining'=> 'absint',
                'resetDate'       => 'sanitize_text_field',
                'reset_date'      => 'sanitize_text_field',
            ];

            $org_sanitized = [];
            foreach ($org_allowed as $key => $sanitizer) {
                if (!array_key_exists($key, $user_data['organization'])) {
                    continue;
                }

                $value = $user_data['organization'][$key];
                if (is_array($value) || is_object($value)) {
                    continue;
                }

                if ($sanitizer === 'absint') {
                    $org_sanitized[$key] = absint($value);
                    continue;
                }

                $org_sanitized[$key] = call_user_func($sanitizer, (string) $value);
            }

            if (!empty($org_sanitized)) {
                $sanitized['organization'] = $org_sanitized;
            }
        }

	        return $sanitized;
	    }

    /**
     * Build setup wizard configuration exposed to admin scripts.
     *
     * @return array<string, mixed>
     */
    private function get_setup_wizard_bootstrap(): array {
        $wizard_completed = class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')
            ? \BeepBeepAI\AltTextGenerator\Onboarding::is_completed()
            : false;

        $opts = get_option(self::OPTION_KEY, []);

        return [
            'completed' => (bool) $wizard_completed,
            'wooActive' => class_exists('\WooCommerce'),
            'wooContextEnabled' => !empty($opts['woocommerce_context_enabled']),
            'previewLimit' => 3,
            'applyLimit' => 10,
            'candidateLimit' => 25,
        ];
    }

	    public function enqueue_admin($hook){
        $this->trait_enqueue_admin($hook);
    }

    public function wpcli_command($args, $assoc){
        if (!class_exists('WP_CLI')) return;

        if (isset($args[0]) && $args[0] === 'reset-usage') {
            $this->wpcli_reset_usage();
            return;
        }

        $id  = isset($assoc['post_id']) ? intval($assoc['post_id']) : 0;
        if (!$id){
            \WP_CLI::error('Provide --post_id=<attachment_id> or use: wp beepbeepai reset-usage');
        }

        $res = $this->generate_and_save($id, 'wpcli');
        if (is_wp_error($res)) {
            if ($res->get_error_code() === 'bbai_dry_run') {
                \WP_CLI::success("ID $id dry-run: " . $res->get_error_message());
            } else {
                \WP_CLI::error($res->get_error_message());
            }
        } else {
            \WP_CLI::success("Generated ALT for $id: $res");
        }
    }

    /**
     * WP-CLI: Reset local usage caches and free-credits allocation.
     * Usage is authoritative on the backend; this clears local state so the site
     * refetches from the API. For free-plan testing, also resets allocation so
     * the allocation flow can run again.
     */
    public function wpcli_reset_usage() {
        if (!class_exists('WP_CLI')) return;

        delete_option('beepbeepai_free_credits_allocated');
        delete_transient('bbai_usage_cache');
        delete_transient('bbai_quota_cache');
        delete_option('bbai_usage_stats_cache');
        delete_option('beepbeep_ai_usage_cache');
        if (class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
            \BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
        }
        if (class_exists('\BeepBeepAI\AltTextGenerator\Token_Quota_Service')) {
            \BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
        }

        \WP_CLI::success('Usage caches cleared. Free-credits allocation reset. Next dashboard load will refetch from API.');
    }
    
    /**
     * AJAX handler: Dismiss upgrade notice
     */
    /**
     * Handle AJAX request to dismiss external API notice.
     * Uses site option so it shows only once for all users.
     */
    /**
     * Check onboarding status
     */
    public function ajax_check_onboarding() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $completed = false;
        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            $completed = \BeepBeepAI\AltTextGenerator\Onboarding::is_completed();
        }

        wp_send_json_success(['completed' => $completed]);
    }

    /**
     * Check milestone
     */
    public function ajax_check_milestone() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $milestone = isset($_POST['milestone']) ? absint(wp_unslash($_POST['milestone'])) : 0;
        if ($milestone <= 0) {
            wp_send_json_error(['message' => __('Invalid milestone.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            $user_milestones = \BeepBeepAI\AltTextGenerator\Onboarding::get_milestones();
            $new_milestone = !in_array($milestone, $user_milestones, true);
            wp_send_json_success(['new_milestone' => $new_milestone]);
        } else {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * Track milestone
     */
    public function ajax_track_milestone() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $milestone = isset($_POST['milestone']) ? absint(wp_unslash($_POST['milestone'])) : 0;
        if ($milestone <= 0) {
            wp_send_json_error(['message' => __('Invalid milestone.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            \BeepBeepAI\AltTextGenerator\Onboarding::add_milestone($milestone);
            wp_send_json_success(['message' => __('Milestone tracked.', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * Complete onboarding
     */
    public function ajax_complete_onboarding() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Only mark completed if user is authenticated with BeepBeep
        if (!bbai_is_authenticated()) {
            wp_send_json_error(['message' => __('Authentication required.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            // Idempotent: safe to call multiple times
            \BeepBeepAI\AltTextGenerator\Onboarding::mark_completed();
            \BeepBeepAI\AltTextGenerator\Onboarding::update_last_seen();
            wp_send_json_success(['message' => __('Onboarding completed.', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * Dismiss the monthly reset insight modal for the current billing period.
     */
    public function ajax_dismiss_reset_modal() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        \BeepBeepAI\AltTextGenerator\Usage_Tracker::mark_reset_shown();
        wp_send_json_success( [ 'message' => __( 'Reset modal dismissed.', 'beepbeep-ai-alt-text-generator' ) ] );
    }

    /**
     * Start onboarding scan (queue missing images).
     */
    public function ajax_start_scan() {
        $action = "bbai_onboarding";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $limit = apply_filters('bbai_onboarding_scan_limit', 500);
        $limit = max(1, min(500, absint($limit)));

        $ids = $this->get_missing_attachment_ids($limit);
        if (empty($ids)) {
            // No images need alt text - still proceed to Step 3 for full onboarding experience
            // Onboarding will be marked complete when Step 3 is viewed
            $step3_url = admin_url('admin.php?page=bbai-onboarding&step=3');
            if ( function_exists( 'bbai_telemetry_emit' ) ) {
                $bbai_uid = get_current_user_id();
                if ( $bbai_uid && ! get_user_meta( $bbai_uid, 'bbai_telemetry_first_scan', true ) ) {
                    update_user_meta( $bbai_uid, 'bbai_telemetry_first_scan', time() );
                    bbai_telemetry_emit(
                        'first_scan_triggered',
                        [
                            'queued'             => 0,
                            'total_candidates'   => 0,
                            'library_pre_empty' => true,
                        ]
                    );
                }
            }
            wp_send_json_success([
                'message' => __('Your media library is already optimized! All images have alt text.', 'beepbeep-ai-alt-text-generator'),
                'queued'  => 0,
                'total'   => 0,
                'redirect' => $step3_url,
            ]);
        }

        $queued = Queue::enqueue_many($ids, 'onboarding');
        if ($queued > 0) {
            Queue::schedule_processing();
        }

        // Note: We no longer mark completed here - Step 3 will mark it when viewed
        // This ensures user always sees Step 3 at least once

        // Redirect to Step 3 review screen
        $step3_url = admin_url('admin.php?page=bbai-onboarding&step=3');

        if ( function_exists( 'bbai_telemetry_emit' ) ) {
            $bbai_uid = get_current_user_id();
            if ( $bbai_uid && ! get_user_meta( $bbai_uid, 'bbai_telemetry_first_scan', true ) ) {
                update_user_meta( $bbai_uid, 'bbai_telemetry_first_scan', time() );
                bbai_telemetry_emit(
                    'first_scan_triggered',
                    [
                        'queued'           => (int) $queued,
                        'total_candidates' => count( $ids ),
                    ]
                );
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: number of images queued */
                _n('%d image queued for processing.', '%d images queued for processing.', $queued, 'beepbeep-ai-alt-text-generator'),
                intval($queued)
            ),
            'queued'   => intval($queued),
            'total'    => count($ids),
            'redirect' => $step3_url,
        ]);
    }

    /**
     * Skip onboarding step and redirect to Step 3.
     * Note: Onboarding is marked completed when Step 3 is viewed, not here.
     */
    public function ajax_onboarding_skip() {
        $action = "bbai_onboarding";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Note: We no longer mark completed here - Step 3 will mark it when viewed
        // This ensures user always sees Step 3 at least once

        // Redirect to Step 3 review screen so user learns where to review
        $step3_url = admin_url('admin.php?page=bbai-onboarding&step=3');

        wp_send_json_success([
            'message'  => __('Onboarding skipped.', 'beepbeep-ai-alt-text-generator'),
            'redirect' => $step3_url,
        ]);
    }

    public function ajax_dismiss_api_notice() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        
        // Store as site option so it shows only once globally, not per user
        update_option('bbai_api_notice_dismissed', true, false);
        delete_option('wp_alt_text_api_notice_dismissed');
        wp_send_json_success(['message' => __('Notice dismissed', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_dismiss_upgrade() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        Usage_Tracker::dismiss_upgrade_notice();
        setcookie('bbai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }
    
    /**
     * AJAX handler: Refresh usage data
     */
    public function ajax_queue_retry_job() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        $job_id = isset($_POST['job_id']) ? absint(wp_unslash($_POST['job_id'])) : 0;
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        Queue::retry_job($job_id);
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_queue_retry_failed() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        Queue::retry_failed();
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_queue_clear_completed() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_queue_stats() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        
        $bbai_stats = Queue::get_stats();
        $failures = Queue::get_failures();
        
        wp_send_json_success([
            'stats' => $bbai_stats,
            'failures' => $failures
        ]);
    }

    public function ajax_track_upgrade() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $source_input = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'dashboard';
        $event_name_input = isset($_POST['event_name']) ? sanitize_key(wp_unslash($_POST['event_name'])) : 'upgrade_cta_clicked';
        $trigger_input = isset($_POST['trigger']) ? sanitize_key(wp_unslash($_POST['trigger'])) : 'unknown';
        $plan_input = isset($_POST['plan']) ? sanitize_key(wp_unslash($_POST['plan'])) : '';
        $allowed_sources = [
            'dashboard',
            'bulk',
            'bulk-regenerate',
            'library',
            'manual',
            'onboarding',
            'queue',
            'unknown',
            'bbai_upgrade_modal_opened',
            'bbai_locked_cta_clicked',
            'bbai_limit_state_viewed',
            'upgrade-trigger',
            'limit-reached',
            'checkout',
            'upgrade-modal',
        ];
        $allowed_event_names = [
            'upgrade_modal_viewed',
            'upgrade_cta_clicked',
            'upgrade_completed',
        ];
        $allowed_triggers = [
            'scan_completion',
            'new_image_upload',
            'usage_80',
            'limit_reached',
            'upgrade_required',
            'default',
            'manual',
            'unknown',
        ];
        $source = in_array($source_input, $allowed_sources, true) ? $source_input : 'dashboard';
        $event_name = in_array($event_name_input, $allowed_event_names, true) ? $event_name_input : 'upgrade_cta_clicked';
        $trigger = in_array($trigger_input, $allowed_triggers, true) ? $trigger_input : 'unknown';
        $event  = [
            'event_name' => $event_name,
            'source' => $source,
            'trigger' => $trigger,
            'plan' => $plan_input,
            'user_id' => get_current_user_id(),
            'time'   => current_time('mysql'),
        ];

        update_option('bbai_last_upgrade_event', $event, false);

        if ($event_name === 'upgrade_cta_clicked') {
            update_option('bbai_last_upgrade_click', $event, false);
            do_action('bbai_upgrade_clicked', $event);
        }

        do_action('bbai_upgrade_event_tracked', $event);

        if ( function_exists( 'bbai_telemetry_emit' ) ) {
            $telemetry_event = 'upgrade_modal_viewed' === $event_name ? 'upgrade_modal_opened' : $event_name;
            $telemetry_props = [
                'source'  => $source,
                'trigger' => $trigger,
            ];
            if ( '' !== $plan_input ) {
                $telemetry_props['plan_selected'] = $plan_input;
            }
            bbai_telemetry_emit( $telemetry_event, $telemetry_props );
        }

        wp_send_json_success(['recorded' => true]);
    }

    /**
     * Batch telemetry from the admin client (UI events, navigation, filters).
     */
    public function ajax_telemetry() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $raw = isset( $_POST['events'] ) ? wp_unslash( $_POST['events'] ) : '';
        if ( ! is_string( $raw ) || '' === $raw ) {
            wp_send_json_success( [ 'recorded' => 0 ] );
            return;
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid payload.', 'beepbeep-ai-alt-text-generator' ) ], 400 );
            return;
        }

        $recorded = 0;
        foreach ( array_slice( $decoded, 0, 25 ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $name = isset( $row['event'] ) ? sanitize_key( (string) $row['event'] ) : '';
            if ( ! preg_match( '/^[a-z0-9_]{1,80}$/', $name ) ) {
                continue;
            }
            $props = isset( $row['properties'] ) && is_array( $row['properties'] ) ? $row['properties'] : [];
            if ( function_exists( 'bbai_telemetry_emit' ) ) {
                bbai_telemetry_emit( $name, $props );
                ++$recorded;
            }
        }

        wp_send_json_success( [ 'recorded' => $recorded ] );
    }

    public function ajax_refresh_usage() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        // Clear cache and return the normalized usage contract for the current auth state.
        Usage_Tracker::clear_cache();
        $bbai_stats = $this->get_connected_usage_payload();

        if (is_array($bbai_stats) && !empty($bbai_stats)) {
            wp_send_json_success($bbai_stats);
        }

        if ( function_exists( 'bbai_telemetry_emit' ) ) {
            bbai_telemetry_emit(
                'api_error',
                [
                    'error_type'   => 'usage_fetch_failed',
                    'endpoint'     => 'get_usage',
                    'recoverable'  => true,
                ]
            );
        }
        wp_send_json_error(['message' => __('Failed to fetch usage data', 'beepbeep-ai-alt-text-generator')]);
    }

    /**
     * AJAX handler: Regenerate single image alt text
     */
    public function ajax_regenerate_single() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        $attachment_id = isset($_POST['attachment_id']) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        if ( 0 === $attachment_id ) {
            wp_send_json_error(['message' => __('Invalid attachment ID', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        $request_key = isset($_POST['request_key']) ? sanitize_text_field(wp_unslash($_POST['request_key'])) : '';

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted() ) {
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        $this->maybe_log_regenerate_debug(
            'Regenerate request received',
            [
                'request_key'    => $request_key,
                'attachment_id'  => $attachment_id,
                'attachment_url' => wp_get_attachment_url($attachment_id),
            ]
        );
        
        $bbai_has_license = $this->api_client->has_active_license();

        // Check if user has reached their limit (skip in local dev mode and for license accounts)
        // Use has_reached_limit() which includes cached usage fallback for better reliability
        if (!$bbai_has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            if ($this->api_client->has_reached_limit()) {
                // Get usage data for the error response (prefer cached if API failed)
                $usage = $this->api_client->get_usage();
                if (is_wp_error($usage)) {
                    // Fall back to cached usage for display
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                    $usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_local_usage_snapshot();
                }
                
                wp_send_json_error([
                    'message' => 'Monthly limit reached',
                    'code' => 'limit_reached',
                    'usage' => is_array($usage) ? $usage : null
                ]);
                return;
            }
        }
        
        $result = $this->generate_and_save($attachment_id, 'ajax', 1, [], true);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            
            // Provide more user-friendly error messages
            $user_message = $error_message;
            
            // Handle specific error codes with better messages
            if ($error_code === 'missing_alt_text') {
                $user_message = __('The API returned a response but no alt text was generated. This may be a temporary issue. Please try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'api_response_invalid') {
                $user_message = __('The API response was invalid. Please try again in a moment.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'quota_check_mismatch') {
                $user_message = __('Credits appear available but the backend reported a limit. Please try again in a moment.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'bbai_trial_exhausted') {
                $user_message = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_exhausted_message();
            } elseif ($error_code === 'limit_reached' || $error_code === 'quota_exhausted') {
                $bbai_reset_date = null;
                if (is_array($error_data) && isset($error_data['usage']) && is_array($error_data['usage'])) {
                    $bbai_reset_date = $error_data['usage']['resetDate'] ?? null;
                }
                
                $user_message = __('Monthly quota exhausted. Your quota will reset on the first of next month. Upgrade to Growth for 1,000 generations per month, or manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator');
                
                if ($bbai_reset_date) {
                    try {
                        $reset_ts = strtotime($bbai_reset_date);
                        if ($reset_ts !== false) {
                            $bbai_formatted_date = date_i18n('F j, Y', $reset_ts);
                            $user_message = sprintf(
                                /* translators: 1: reset date */
                                __('Monthly quota exhausted. Your quota will reset on %s. Upgrade to Growth for 1,000 generations per month, or manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator'),
                                $bbai_formatted_date
                            );
                        }
                    } catch (\Exception $e) {
                        // Keep default message if date parsing fails
                    }
                }
            } elseif ($error_code === 'api_timeout') {
                $user_message = __('The request timed out. Please try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'api_unreachable') {
                $user_message = __('Unable to reach the server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator');
            }

            $retryable_codes = [
                'missing_alt_text',
                'api_response_invalid',
                'quota_check_mismatch',
                'api_timeout',
                'api_unreachable',
                'network_error',
                'server_error',
            ];
            $is_retryable = in_array($error_code, $retryable_codes, true);
            $retry_after = 0;
            if (is_array($error_data) && isset($error_data['retry_after'])) {
                $retry_after = absint($error_data['retry_after']);
            } elseif ($error_code === 'quota_check_mismatch') {
                $retry_after = 3;
            }

            if ( function_exists( 'bbai_telemetry_emit' ) ) {
                $err_kind = 'generation_error';
                if ( in_array( $error_code, [ 'api_timeout', 'api_unreachable', 'network_error' ], true ) ) {
                    $err_kind = 'network_error';
                } elseif ( strpos( (string) $error_code, 'api' ) === 0 || $error_code === 'server_error' ) {
                    $err_kind = 'api_error';
                }
                bbai_telemetry_emit(
                    $err_kind,
                    [
                        'error_type'  => (string) $error_code,
                        'endpoint'    => 'regenerate_single',
                        'recoverable' => $is_retryable,
                    ]
                );
            }
            
            wp_send_json_error([
                'message' => $user_message,
                'code'    => $error_code,
                'remaining' => is_array($error_data) && isset($error_data['remaining']) ? max(0, intval($error_data['remaining'])) : null,
                'request_key' => $request_key,
                'retryable' => $is_retryable,
                'retry_after' => $retry_after > 0 ? $retry_after : null,
                'data'    => $error_data,
            ]);
            return;
        }

        // Get updated usage AFTER generation
        // generate_and_save() updates the cache, but we need to ensure we have the latest data
        // The cache is updated from the authoritative /usage response in generate_and_save().
        // Read it back here instead of rebuilding quota from legacy license fields.
        $updated_usage = Usage_Tracker::get_cached_usage(false);

        if (!$updated_usage || !is_array($updated_usage) || !isset($updated_usage['used'])) {
            Usage_Tracker::clear_cache();

            $fresh_usage = $this->api_client->get_usage();
            if (!is_wp_error($fresh_usage) && is_array($fresh_usage)) {
                Usage_Tracker::update_usage($fresh_usage);
                $updated_usage = Usage_Tracker::get_cached_usage(false);

                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    Debug_Log::log('info', 'Fetched fresh usage from API after regeneration', [
                        'used' => $updated_usage['used'] ?? 'not set',
                        'remaining' => $updated_usage['remaining'] ?? 'not set',
                        'limit' => $updated_usage['limit'] ?? 'not set',
                    ], 'generation');
                }
            } else {
                $updated_usage = Usage_Tracker::get_local_usage_snapshot();

                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    Debug_Log::log('warning', 'API usage fetch failed, using local usage snapshot', [
                        'api_error' => is_wp_error($fresh_usage) ? $fresh_usage->get_error_message() : 'unknown error',
                        'cached_usage' => $updated_usage,
                    ], 'generation');
                }
            }
        }
        
        // Only clear stats cache, not usage cache (we want to keep the fresh usage)
        $this->invalidate_stats_cache();
        
        // Ensure we have valid usage data to return
        if (!$updated_usage || !is_array($updated_usage) || !isset($updated_usage['used'])) {
            // Last resort: return the local snapshot so the response stays shape-safe.
            $updated_usage = Usage_Tracker::get_local_usage_snapshot();
            
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('warning', 'No usage data available after regeneration, using cached fallback', [
                    'updated_usage_was_null' => $updated_usage === null,
                    'final_usage' => $updated_usage,
                ], 'generation');
            }
        }
        
        // Log final usage data being sent to frontend
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Sending usage data in AJAX response', [
                'usage' => $updated_usage,
                'has_used' => isset($updated_usage['used']),
                'has_remaining' => isset($updated_usage['remaining']),
                'has_limit' => isset($updated_usage['limit']),
                'request_key' => $request_key,
                'attachment_id' => $attachment_id,
            ], 'generation');
        }

        $response_meta = $this->prepare_attachment_snapshot($attachment_id);
        $response_stats = $this->get_dashboard_stats_payload(true);

        if ( function_exists( 'bbai_telemetry_emit' ) ) {
            bbai_telemetry_emit(
                'alt_regenerated',
                [
                    'number_of_images' => 1,
                    'success'          => true,
                ]
            );
            \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::bump_session_images_processed( 1 );
        }

        wp_send_json_success([
            'message'        => __('Alt text generated successfully.', 'beepbeep-ai-alt-text-generator'),
            'alt_text'       => $result,
            'altText'        => $result, // Also include camelCase for compatibility
            'attachment_id'  => $attachment_id,
            'request_key'    => $request_key,
            'usage'          => $updated_usage ?: null, // Include updated usage in response
            'meta'           => $response_meta,
            'stats'          => $response_stats,
            'data'           => [
                'alt_text' => $result,
                'altText'  => $result,
                'request_key' => $request_key,
                'usage'    => $updated_usage ?: null,
                'meta'     => $response_meta,
                'stats'    => $response_stats,
            ],
        ]);
    }

    /**
     * AJAX handler: Bulk queue images for processing
     */
    public function ajax_bulk_queue() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        $attachment_ids = isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids']) ? array_map('absint', wp_unslash($_POST['attachment_ids'])) : [];
        $source_input = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'bulk';
        $skip_schedule_input = isset($_POST['skip_schedule']) ? sanitize_text_field(wp_unslash($_POST['skip_schedule'])) : '';
        $skip_schedule = in_array($skip_schedule_input, ['1', 'true', 'yes'], true);
        $allowed_sources = [ 'bulk', 'bulk-regenerate', 'dashboard', 'library', 'manual', 'onboarding', 'queue', 'unknown' ];
        $source = in_array($source_input, $allowed_sources, true) ? $source_input : 'bulk';
        
	        if (empty($attachment_ids)) {
	            wp_send_json_error(['message' => __('Invalid attachment IDs', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        // Sanitize all IDs
        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0 && $this->is_image($id);
        });
        
        if (empty($ids)) {
            wp_send_json_error(['message' => __('No valid images found', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted() ) {
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        $bbai_has_license = $this->api_client->has_active_license();

        // Check if user has remaining usage (skip in local dev mode or when license active)
        if (!$bbai_has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            $usage = $this->api_client->get_usage();
            
            // If usage check fails due to authentication, allow queueing but warn user
            if (is_wp_error($usage)) {
                $error_code = $usage->get_error_code();
                // If it's an auth error, allow queueing to proceed (backend will handle it)
                // Don't block queueing on temporary auth issues
                if ($error_code === 'auth_required' || $error_code === 'user_not_found') {
                    // Allow queueing - authentication can be handled later during processing
                } else {
                    // For other errors (server issues, etc.), still allow queueing
                    // The backend will handle usage limits during processing
                }
            } elseif (!$usage || ($usage['remaining'] ?? 0) <= 0) {
                // Only block if we have a valid usage response showing limit reached
                wp_send_json_error([
                    'message' => 'Monthly limit reached',
                    'code' => 'limit_reached',
                    'usage' => $usage
                ]);
                return;
            } else {
                // Check how many we can queue
                $remaining = $usage['remaining'] ?? 0;
                if (count($ids) > $remaining) {
                    wp_send_json_error([
                        'message' => sprintf(
                            /* translators: 1: remaining generations */
                            __('You only have %d generations remaining. Please upgrade or select fewer images.', 'beepbeep-ai-alt-text-generator'),
                            $remaining
                        ),
                        'code' => 'insufficient_credits',
                        'remaining' => $remaining
                    ]);
                    return;
                }
            }
        }
        
        try {
            // Queue images (will clear existing entries for bulk-regenerate)
            $queued = Queue::enqueue_many($ids, $source);
            
            // Log bulk queue operation
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Bulk queue operation', [
                    'queued' => $queued,
                    'requested' => count($ids),
                    'source' => $source,
                    'skip_schedule' => $skip_schedule,
                ], 'bulk');
            }
            
            if ($queued > 0) {
                // Inline flows queue IDs for dedupe/visibility but should not race cron processing.
                if (!$skip_schedule) {
                    Queue::schedule_processing();
                }

                if ( function_exists( 'bbai_telemetry_emit' ) ) {
                    bbai_telemetry_emit(
                        'bulk_generate_triggered',
                        [
                            'number_of_images' => (int) $queued,
                            'source'           => $source,
                        ]
                    );
                }
                
                wp_send_json_success([
                    'message' => sprintf(
                        /* translators: 1: number of images queued */
                        __('%d image(s) queued for processing', 'beepbeep-ai-alt-text-generator'),
                        $queued
                    ),
                    'queued' => $queued,
                    'total' => count($ids),
                    'scheduled' => !$skip_schedule,
                ]);
            } else {
                // For regeneration, if nothing was queued, it might mean they're already completed
                // Check if images already have alt text and suggest direct regeneration instead
                
	                if ($source === 'bulk-regenerate') {
	                    wp_send_json_error([
	                        'message' => __('No images queued. Images may already be processing or have alt text. Refresh the page to see current status.', 'beepbeep-ai-alt-text-generator'),
	                        'code' => 'already_queued'
	                    ]);
	                    return;
	                } else {
	                    wp_send_json_error([
	                        'message' => __('Failed to queue images. They may already be queued or processing.', 'beepbeep-ai-alt-text-generator')
	                    ]);
	                    return;
	                }
	            }
	        } catch ( \Exception $e ) {
	            // Return proper JSON error instead of letting WordPress output HTML
	            wp_send_json_error([
	                'message' => __('Failed to queue images due to a database error. Please try again.', 'beepbeep-ai-alt-text-generator'),
	                'code' => 'queue_failed'
	            ]);
	            return;
	        }
	    }

    /**
     * AJAX handler: Return attachment IDs for bulk actions without relying on REST routes.
     */
    public function ajax_get_attachment_ids() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

	        $scope_input = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'missing';
	        $scope       = in_array( $scope_input, [ 'missing', 'all', 'needs-review' ], true ) ? $scope_input : 'missing';

        $limit_input  = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 500;
        $offset_input = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;

        $limit  = max( 1, min( 500, $limit_input ) );
        $offset = max( 0, $offset_input );

	        if ( 'all' === $scope ) {
	            $ids = $this->get_all_attachment_ids( $limit, $offset );
	        } elseif ( 'needs-review' === $scope ) {
	            $ids = $this->get_needs_review_attachment_ids( $limit, $offset );
	        } else {
	            $ids = $this->get_missing_attachment_ids( $limit, $offset );
	        }

        wp_send_json_success(
            [
                'ids'        => array_map( 'intval', (array) $ids ),
                'scope'      => $scope,
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                ],
            ]
        );
    }

    /**
     * Build scan payload for setup wizard.
     *
     * @param int $sample_limit Number of sample thumbnails to include.
     * @param int $candidate_limit Number of candidate IDs to include.
     * @return array<string, mixed>
     */
    private function get_setup_wizard_scan_payload(int $sample_limit = 3, int $candidate_limit = 25): array {
        global $wpdb;

        $sample_limit = max(1, min(6, $sample_limit));
        $candidate_limit = max(3, min(100, $candidate_limit));
        $image_mime_like = $wpdb->esc_like('image/') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_scanned = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifier comes from trusted core $wpdb.
                'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
                'attachment',
                'inherit',
                $image_mime_like
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $missing_alt_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb.
                'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' m ON (p.ID = m.post_id AND m.meta_key = %s) WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND (m.meta_value IS NULL OR TRIM(m.meta_value) = %s)',
                '_wp_attachment_image_alt',
                'attachment',
                'inherit',
                $image_mime_like,
                ''
            )
        );

        $candidate_ids = $this->get_missing_attachment_ids($candidate_limit, 0);
        $sample_ids = array_slice($candidate_ids, 0, $sample_limit);
        $samples = [];

        foreach ($sample_ids as $id) {
            $thumb = wp_get_attachment_image_url($id, 'thumbnail');
            $samples[] = [
                'id' => (int) $id,
                'thumb' => $thumb ? esc_url_raw($thumb) : '',
                'title' => sanitize_text_field(get_the_title($id) ?: sprintf(/* translators: %d: attachment ID */ __('Image #%d', 'beepbeep-ai-alt-text-generator'), $id)),
            ];
        }

        return [
            'total_scanned' => $total_scanned,
            'missing_alt_count' => max(0, $missing_alt_count),
            'samples' => $samples,
            'candidate_ids' => array_map('intval', (array) $candidate_ids),
            'sample_limit' => $sample_limit,
            'candidate_limit' => $candidate_limit,
        ];
    }

    /**
     * Generate preview alt text without persisting metadata.
     *
     * @param int $attachment_id Attachment ID.
     * @return string|\WP_Error
     */
    private function generate_preview_alt_for_attachment(int $attachment_id) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        $trial_check = \BeepBeepAI\AltTextGenerator\Trial_Quota::check();
        if (is_wp_error($trial_check)) {
            return $trial_check;
        }

        $has_license = $this->api_client->has_active_license();
        if (!$has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV) && $this->api_client->has_reached_limit()) {
            $usage = $this->api_client->get_usage();
            return new \WP_Error(
                'limit_reached',
                __('Monthly quota exhausted. Upgrade to continue generating alt text.', 'beepbeep-ai-alt-text-generator'),
                ['code' => 'quota_exhausted', 'usage' => is_array($usage) ? $usage : null]
            );
        }

        if (!$this->is_image($attachment_id)) {
            return new \WP_Error('not_image', __('Attachment is not an image.', 'beepbeep-ai-alt-text-generator'));
        }

        $context = $this->build_generation_context_for_attachment($attachment_id);
        $api_response = $this->api_client->generate_alt_text($attachment_id, $context, false);
        if (is_wp_error($api_response)) {
            return $api_response;
        }

        $alt_source = null;
        $alt_text = $this->extract_alt_text_from_response($api_response, $alt_source);
        if ($alt_text === '') {
            return new \WP_Error(
                'missing_alt_text',
                __('The API response did not include preview alt text.', 'beepbeep-ai-alt-text-generator'),
                ['code' => 'api_response_invalid']
            );
        }

        return $alt_text;
    }

    /**
     * AJAX handler: scan media library for images missing alt text.
     */
    public function ajax_scan_missing_alt() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        $sample_limit = isset($_POST['sample_limit']) ? absint(wp_unslash($_POST['sample_limit'])) : 3;
        $candidate_limit = isset($_POST['candidate_limit']) ? absint(wp_unslash($_POST['candidate_limit'])) : 25;
        $payload = $this->get_setup_wizard_scan_payload($sample_limit, $candidate_limit);

        wp_send_json_success($payload);
    }

    /**
     * AJAX handler: generate preview alt text for selected image IDs without writing metadata.
     */
    public function ajax_generate_preview_alt() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        $ids_input = isset($_POST['attachment_ids']) ? array_map('absint', (array) wp_unslash($_POST['attachment_ids'])) : [];
        $ids = array_values(array_unique(array_filter($ids_input)));
        if (empty($ids)) {
            $ids = array_slice($this->get_missing_attachment_ids(3, 0), 0, 3);
        }

        $ids = array_slice($ids, 0, 3);
        if (empty($ids)) {
            wp_send_json_success([
                'previews' => [],
                'count' => 0,
                'message' => __('No images found for preview.', 'beepbeep-ai-alt-text-generator'),
            ]);
            return;
        }

        $previews = [];
        $errors = [];

        foreach ($ids as $attachment_id) {
            $preview = $this->generate_preview_alt_for_attachment((int) $attachment_id);
            if (is_wp_error($preview)) {
                $errors[] = [
                    'id' => (int) $attachment_id,
                    'message' => $preview->get_error_message(),
                    'code' => $preview->get_error_code(),
                    'data' => $preview->get_error_data(),
                ];
                continue;
            }

            $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $previews[] = [
                'id' => (int) $attachment_id,
                'thumb' => $thumb ? esc_url_raw($thumb) : '',
                'title' => sanitize_text_field(get_the_title($attachment_id) ?: sprintf(/* translators: %d: attachment ID */ __('Image #%d', 'beepbeep-ai-alt-text-generator'), $attachment_id)),
                'alt_text' => wp_strip_all_tags((string) $preview),
            ];
        }

        if (empty($previews) && !empty($errors)) {
            $first_error = $errors[0];
            wp_send_json_error([
                'message' => $first_error['message'] ?? __('Failed to generate preview alt text.', 'beepbeep-ai-alt-text-generator'),
                'code' => $first_error['code'] ?? 'preview_failed',
                'errors' => $errors,
                'data' => $first_error['data'] ?? null,
            ]);
            return;
        }

        wp_send_json_success([
            'previews' => $previews,
            'count' => count($previews),
            'errors' => $errors,
        ]);
    }

    /**
     * AJAX handler: apply alt text in batches for wizard onboarding.
     */
    public function ajax_apply_alt_batch() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        $ids_input = isset($_POST['attachment_ids']) ? array_map('absint', (array) wp_unslash($_POST['attachment_ids'])) : [];
        $ids = array_values(array_unique(array_filter($ids_input)));

        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 10;
        $limit = max(1, min(10, $limit));

        if (empty($ids)) {
            $ids = $this->get_missing_attachment_ids($limit, 0);
        }

        $target_ids = array_slice($ids, 0, $limit);
        $total = count($target_ids);
        if ($total <= 0) {
            wp_send_json_success([
                'processed_in_batch' => 0,
                'updated_in_batch' => 0,
                'next_offset' => 0,
                'total' => 0,
                'done' => true,
                'failed' => [],
            ]);
            return;
        }

        $offset = isset($_POST['offset']) ? absint(wp_unslash($_POST['offset'])) : 0;
        $offset = max(0, min($offset, $total));

        $batch_size = isset($_POST['batch_size']) ? absint(wp_unslash($_POST['batch_size'])) : 1;
        $batch_size = max(1, min(5, $batch_size));

        $batch_ids = array_slice($target_ids, $offset, $batch_size);
        $updated_in_batch = 0;
        $failed = [];

        foreach ($batch_ids as $attachment_id) {
            $result = $this->generate_and_save((int) $attachment_id, 'onboarding', 1, [], false);
            if (is_wp_error($result)) {
                $failed[] = [
                    'id' => (int) $attachment_id,
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                    'data' => $result->get_error_data(),
                ];
                continue;
            }
            $updated_in_batch++;
        }

        $next_offset = min($total, $offset + count($batch_ids));
        $done = $next_offset >= $total;

        wp_send_json_success([
            'processed_in_batch' => count($batch_ids),
            'updated_in_batch' => $updated_in_batch,
            'next_offset' => $next_offset,
            'total' => $total,
            'done' => $done,
            'failed' => $failed,
        ]);
    }

    /**
     * AJAX handler: save WooCommerce context preference for generation prompts.
     */
    public function ajax_set_woocommerce_context() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!class_exists('\WooCommerce')) {
            wp_send_json_error(['message' => __('WooCommerce is not active on this site.', 'beepbeep-ai-alt-text-generator')], 400);
            return;
        }

        $enabled_raw = sanitize_text_field(wp_unslash($_POST['enabled'] ?? ''));
        $enabled = in_array(strtolower($enabled_raw), ['1', 'true', 'yes', 'on'], true);

        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }
        $opts['woocommerce_context_enabled'] = $enabled ? 1 : 0;
        update_option(self::OPTION_KEY, $opts, false);

        wp_send_json_success([
            'enabled' => $enabled,
            'message' => $enabled
                ? __('WooCommerce product context enabled.', 'beepbeep-ai-alt-text-generator')
                : __('WooCommerce product context disabled.', 'beepbeep-ai-alt-text-generator'),
        ]);
    }

    /**
     * AJAX handler: mark setup wizard as completed for current user.
     */
    public function ajax_complete_setup_wizard() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')], 403);
            return;
        }

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')], 500);
            return;
        }

        \BeepBeepAI\AltTextGenerator\Onboarding::mark_completed();
        \BeepBeepAI\AltTextGenerator\Onboarding::update_last_seen();

        wp_send_json_success([
            'completed' => true,
            'message' => __('Setup wizard completed.', 'beepbeep-ai-alt-text-generator'),
        ]);
    }

    public function process_queue() {
        $batch_size = apply_filters('bbai_queue_batch_size', 3);
        $max_attempts = apply_filters('bbai_queue_max_attempts', 3);
        $bbai_trial_blocked = false;
        $bbai_trial_message = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_exhausted_message();

        Queue::reset_stale(apply_filters('bbai_queue_stale_timeout', 10 * MINUTE_IN_SECONDS));

        $jobs = Queue::claim_batch($batch_size);
        if (empty($jobs)) {
            Queue::purge_completed(apply_filters('bbai_queue_purge_age', DAY_IN_SECONDS * 2));
            return;
        }

        foreach ($jobs as $job) {
            $attachment_id = intval($job->attachment_id);
            if ($attachment_id <= 0 || !$this->is_image($attachment_id)) {
                Queue::mark_complete($job->id);
                continue;
            }

            $result = $this->generate_and_save($attachment_id, $job->source ?? 'queue', max(0, intval($job->attempts) - 1));

            if (is_wp_error($result)) {
                $code    = $result->get_error_code();
                $message = $result->get_error_message();

                if ($code === 'limit_reached') {
                    Queue::mark_retry($job->id, $message);
                    Queue::schedule_processing(apply_filters('bbai_queue_limit_delay', HOUR_IN_SECONDS));
                    break;
                }

                if ($code === 'bbai_trial_exhausted') {
                    Queue::mark_failed($job->id, $message ?: $bbai_trial_message);
                    $bbai_trial_blocked = true;
                    break;
                }

                if (intval($job->attempts) >= $max_attempts) {
                    Queue::mark_failed($job->id, $message);
                } else {
                    Queue::mark_retry($job->id, $message);
                }
                continue;
            }

            Queue::mark_complete($job->id);
        }

        // If trial was exhausted mid-batch, fail any remaining pending jobs now to avoid a stuck queue.
        if ($bbai_trial_blocked) {
            $bbai_safety_loops = 0;
            while ($bbai_safety_loops < 20) {
                $pending_jobs = Queue::claim_batch(50);
                if (empty($pending_jobs)) {
                    break;
                }

                foreach ($pending_jobs as $pending_job) {
                    Queue::mark_failed($pending_job->id, $bbai_trial_message);
                }

                $bbai_safety_loops++;
            }
        }

        Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();
        $bbai_stats = Queue::get_stats();
        if (!empty($bbai_stats['pending']) && !$bbai_trial_blocked) {
            Queue::schedule_processing(apply_filters('bbai_queue_next_delay', 45));
        }

        Queue::purge_completed(apply_filters('bbai_queue_purge_age', DAY_IN_SECONDS * 2));
    }

    public function handle_media_change($attachment_id = 0) {
        $this->invalidate_stats_cache();

        if (current_filter() === 'delete_attachment') {
            Queue::schedule_processing(30);
            return;
        }

        if ($this->is_image($attachment_id)) {
            $this->queue_media_upload_upgrade_trigger((int) $attachment_id);
        }

        if ($this->queue_attachment($attachment_id, 'upload')) {
            Queue::schedule_processing(15);
        }
    }

    private function get_media_upload_trigger_transient_key(int $user_id): string {
        return self::MEDIA_UPLOAD_TRIGGER_TRANSIENT_PREFIX . max(0, $user_id);
    }

    private function queue_media_upload_upgrade_trigger(int $attachment_id = 0): void {
        $user_id = get_current_user_id();
        if ($user_id <= 0 || !$this->user_can_manage()) {
            return;
        }

        $transient_key = $this->get_media_upload_trigger_transient_key($user_id);
        $existing = get_transient($transient_key);
        $upload_count = 1;

        if (is_array($existing)) {
            $upload_count += max(0, (int) ($existing['uploadCount'] ?? 0));
        }

        set_transient(
            $transient_key,
            [
                'uploadCount' => min(25, $upload_count),
                'attachmentId' => max(0, $attachment_id),
                'createdAt' => time(),
            ],
            HOUR_IN_SECONDS
        );
    }

    private function consume_media_upload_upgrade_trigger(): array {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return [
                'uploadCount' => 0,
                'attachmentId' => 0,
                'createdAt' => 0,
            ];
        }

        $transient_key = $this->get_media_upload_trigger_transient_key($user_id);
        $payload = get_transient($transient_key);
        delete_transient($transient_key);

        if (!is_array($payload)) {
            return [
                'uploadCount' => 0,
                'attachmentId' => 0,
                'createdAt' => 0,
            ];
        }

        return [
            'uploadCount' => max(0, (int) ($payload['uploadCount'] ?? 0)),
            'attachmentId' => max(0, (int) ($payload['attachmentId'] ?? 0)),
            'createdAt' => max(0, (int) ($payload['createdAt'] ?? 0)),
        ];
    }

    public function handle_media_metadata_update($data, $post_id) {
        $this->invalidate_stats_cache();
        if ($this->queue_attachment($post_id, 'metadata')) {
            Queue::schedule_processing(20);
        }
        return $data;
    }

    public function handle_attachment_updated($post_id, $post_after, $post_before) {
        $this->invalidate_stats_cache();
        if ($this->queue_attachment($post_id, 'update')) {
            Queue::schedule_processing(20);
        }
    }

    public function handle_post_save($post_ID, $post, $update) {
        if ($post instanceof \WP_Post && $post->post_type === 'attachment') {
            $this->invalidate_stats_cache();
            if ($update) {
                if ($this->queue_attachment($post_ID, 'save')) {
                    Queue::schedule_processing(20);
                }
            }
        }
    }

    private function get_account_summary(?array $bbai_usage_stats = null) {
        if ($this->account_summary !== null) {
            return $this->account_summary;
        }

        $summary = [
            'email'      => '',
            'name'       => '',
            'plan'       => $bbai_usage_stats['plan'] ?? '',
            'plan_label' => $bbai_usage_stats['plan_label'] ?? '',
        ];

        if (!$this->api_client->is_authenticated()) {
            $this->account_summary = $summary;
            return $this->account_summary;
        }

        $user = $this->api_client->get_user_data();
        if ((!is_array($user) || empty($user['email']))) {
            $fresh = $this->api_client->get_user_info();
            if (!is_wp_error($fresh) && is_array($fresh)) {
                $user = $fresh;
                $this->api_client->set_user_data($fresh);
            }
        }

        if (is_array($user)) {
            $summary['email'] = $user['email'] ?? '';
            $summary['name']  = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        }

        $this->account_summary = $summary;
        return $this->account_summary;
    }

    /**
     * Phase 2 Authentication AJAX Handlers
     * NOTE: ajax_register and ajax_login are defined in Core_Ajax_Auth trait
     */

    /**
     * AJAX handler: User login (duplicate removed - using trait version)
     */
    public function ajax_login() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

	        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	        $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
	        $password = is_string($password_input) ? $password_input : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_data = $result->get_error_data();
            if ($error_code === 'site_has_license' || $error_code === 'SITE_HAS_LICENSE') {
                $existing_email = '';
                if (is_array($error_data) && isset($error_data['existing_email'])) {
                    $existing_email = sanitize_email((string) $error_data['existing_email']);
                }

                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'code' => 'site_has_license',
                    'existing_email' => $existing_email,
                ]);
                return;
            }

            if ($error_code === 'invite_required') {
                $invite_url = '';
                if (is_array($error_data) && isset($error_data['invite_url'])) {
                    $invite_url = esc_url_raw((string) $error_data['invite_url']);
                }

                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'code' => 'invite_required',
                    'invite_url' => $invite_url,
                ]);
                return;
            }

            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => is_string($error_code) ? strtolower($error_code) : '',
            ]);
            return;
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'beepbeep-ai-alt-text-generator'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     * Clears authentication token, license key, and all cached data
     */
    public function ajax_logout() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Clear JWT token (for authenticated users)
        $this->api_client->clear_token();
        
        // Clear license key (for agency/license-based users)
        $this->api_client->clear_license_key();
        
        // Clear user data
        delete_option('opptibbai_user_data');
        delete_option('opptibbai_site_id');
        
        // Clear usage cache
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');
        delete_transient('opptibbai_token_last_check');

        wp_send_json_success([
            'message' => __('Logged out successfully', 'beepbeep-ai-alt-text-generator'),
            'redirect' => admin_url('admin.php?page=bbai')
        ]);
    }

    /**
     * Handle logout via form submission (admin-post handler)
     */
		    public function handle_logout() {
		        \bbai_debug_log( 'handle_logout called' );

	        // Verify nonce
	        $action = 'bbai_logout_action';
		        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbai_logout_nonce'] ?? '' ) ), $action ) ) {
			            \bbai_debug_log( 'handle_logout nonce verification failed' );
			            wp_die(esc_html__('Security check failed', 'beepbeep-ai-alt-text-generator'));
			        }

	        // Check permissions
		        if (!$this->user_can_manage()) {
		            \bbai_debug_log( 'handle_logout permission check failed' );
		            wp_die(esc_html__('Unauthorized', 'beepbeep-ai-alt-text-generator'));
		        }

	        // Clear token and user data
		        \bbai_debug_log( 'handle_logout clearing token' );
	        $this->api_client->clear_token();

	        // ALSO clear license key (otherwise user stays authenticated via license)
		        \bbai_debug_log( 'handle_logout clearing license key' );
	        $this->api_client->clear_license_key();

        // Also clear any usage cache
        delete_transient('bbai_usage_cache');
	        delete_transient('opptibbai_usage_cache');

	        // Verify everything was cleared (debug mode only).
		        $remaining_token   = get_option('beepbeepai_jwt_token', '');
		        $remaining_license = get_option('beepbeepai_license_key', '');
		        \bbai_debug_log(
		            'handle_logout post-clear status',
		            [
		                'token_cleared'   => empty( $remaining_token ),
		                'license_cleared' => empty( $remaining_license ),
		            ]
		        );

	        // Redirect to dashboard with cache buster.
		        \bbai_debug_log( 'handle_logout redirecting to dashboard' );
	        wp_safe_redirect(add_query_arg('nocache', time(), admin_url('admin.php?page=bbai')));
	        exit;
	    }

    public function ajax_disconnect_account() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Clear JWT token (for authenticated users)
        $this->api_client->clear_token();
        
        // Clear license key (for agency/license-based users)
        // This prevents automatic reconnection when using license keys
        $this->api_client->clear_license_key();
        
        // Clear user data
        delete_option('opptibbai_user_data');
        delete_option('opptibbai_site_id');
        
        // Clear usage cache
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');
        delete_transient('opptibbai_token_last_check');

        wp_send_json_success([
            'message' => __('Account disconnected. Please sign in again to reconnect.', 'beepbeep-ai-alt-text-generator'),
        ]);
    }

    /**
     * AJAX handler: Activate license key
     */
    public function ajax_activate_license() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $bbai_license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

        if (empty($bbai_license_key)) {
            wp_send_json_error(['message' => __('License key is required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Validate UUID format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $bbai_license_key)) {
            wp_send_json_error(['message' => __('Invalid license key format', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->activate_license($bbai_license_key);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        // Clear cached usage data
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');

        wp_send_json_success([
            'message' => __('License activated successfully', 'beepbeep-ai-alt-text-generator'),
            'organization' => $result['organization'] ?? null,
            'site' => $result['site'] ?? null
        ]);
    }

    /**
     * AJAX handler: Deactivate license key
     */
    public function ajax_deactivate_license() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->deactivate_license();

        // Clear cached usage data
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => __('License deactivated successfully', 'beepbeep-ai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Get license site usage
     */
    public function ajax_get_license_sites() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Must be authenticated to view license site usage
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view license site usage', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Fetch license site usage from API
        $result = $this->api_client->get_license_sites();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to fetch license site usage', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler: Disconnect a site from the license
     */
    public function ajax_disconnect_license_site() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Must be authenticated to disconnect license sites
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to disconnect license sites', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $site_id = isset($_POST['site_id']) ? sanitize_text_field(wp_unslash($_POST['site_id'])) : '';
        if (empty($site_id)) {
            wp_send_json_error([
                'message' => __('Site ID is required', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Disconnect the site from the license
        $result = $this->api_client->disconnect_license_site($site_id);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to disconnect site', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        wp_send_json_success([
            'message' => __('Site disconnected successfully', 'beepbeep-ai-alt-text-generator'),
            'data' => $result
        ]);
    }

    /**
     * Check if admin is authenticated (separate from regular user auth)
     */
    private function is_admin_authenticated() {
        // Check if we have a valid admin session
        $admin_session = get_transient('bbai_admin_session_' . get_current_user_id());
        if ($admin_session === false || empty($admin_session)) {
            return false;
        }
        
        // Verify session hasn't expired (24 hours)
        $session_time = get_transient('bbai_admin_session_time_' . get_current_user_id());
        if ($session_time === false || (time() - intval($session_time)) > (24 * HOUR_IN_SECONDS)) {
            $this->clear_admin_session();
            return false;
        }
        
        return true;
    }

    /**
     * Set admin session
     */
    private function set_admin_session() {
        $user_id = get_current_user_id();
        set_transient('bbai_admin_session_' . $user_id, 'authenticated', DAY_IN_SECONDS);
        set_transient('bbai_admin_session_time_' . $user_id, time(), DAY_IN_SECONDS);
    }

    /**
     * Clear admin session
     */
    private function clear_admin_session() {
        $user_id = get_current_user_id();
        delete_transient('bbai_admin_session_' . $user_id);
        delete_transient('bbai_admin_session_time_' . $user_id);
    }

    /**
     * AJAX handler: Admin login for agency users
     */
    public function ajax_admin_login() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Verify agency license
        $bbai_has_license = $this->api_client->has_active_license();
        $bbai_license_data = $this->api_client->get_license_data();
        $bbai_is_agency = false;
        
        if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization'])) {
            $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
            $bbai_is_agency = ($bbai_license_plan === 'agency');
        }
        
        if (!$bbai_is_agency) {
            wp_send_json_error([
                'message' => __('Admin access is only available for agency licenses', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

	        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	        $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
	        $password = is_string($password_input) ? $password_input : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (empty($password)) {
            wp_send_json_error([
                'message' => __('Please enter your password', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Attempt login
        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Login failed. Please check your credentials.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Set admin session
        $this->set_admin_session();

        wp_send_json_success([
            'message' => __('Successfully logged in', 'beepbeep-ai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }

    /**
     * AJAX handler: Admin logout
     */
    public function ajax_admin_logout() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $this->clear_admin_session();

        wp_send_json_success([
            'message' => __('Logged out successfully', 'beepbeep-ai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        $user_info = $this->api_client->get_user_info();
        $usage = $this->api_client->get_usage();

        if (is_wp_error($user_info)) {
            wp_send_json_error(['message' => $user_info->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'user' => $user_info,
            'usage' => is_wp_error($usage) ? null : $usage
        ]);
    }

    /**
     * AJAX handler: Create Stripe checkout session
     */
    public function ajax_create_checkout() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Allow checkout without authentication - users can create account during checkout
        // Authentication is optional for checkout, backend will handle account creation

        $price_id = isset($_POST['price_id']) ? sanitize_text_field(wp_unslash($_POST['price_id'])) : '';

        // Resolve plan_id to a Stripe price ID when price_id is not provided directly.
        if (empty($price_id)) {
            $plan_id = isset($_POST['plan_id']) ? sanitize_key(wp_unslash($_POST['plan_id'])) : '';
            $valid_plan_ids = array_keys($this->get_checkout_price_ids());
            if (!in_array($plan_id, $valid_plan_ids, true)) {
                $plan_id = '';
            }
            if (!empty($plan_id)) {
                $price_id = $this->get_checkout_price_id($plan_id);
            }
        }

        if (empty($price_id)) {
            wp_send_json_error(['message' => __('Price ID is required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $success_url = admin_url('admin.php?page=bbai&checkout=success');
        $cancel_url = admin_url('admin.php?page=bbai&checkout=cancel');

        // Create checkout session - works for both authenticated and unauthenticated users
        // If token is invalid, it will retry without token for guest checkout
        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);
        $plan_hint = isset($_POST['plan_id']) ? sanitize_key(wp_unslash($_POST['plan_id'])) : '';
        $resolved_checkout_url = $this->resolve_checkout_url_from_result($result, $plan_hint, $price_id);
        $raw_checkout_url = (!is_wp_error($result) && is_array($result) && isset($result['url']) && is_string($result['url']))
            ? esc_url_raw($result['url'])
            : '';
        $fallback_used = $resolved_checkout_url !== '' && $resolved_checkout_url !== $raw_checkout_url;

        if (is_wp_error($result) || $resolved_checkout_url === '') {
            $fallback_checkout_url = $this->get_checkout_fallback_url($plan_hint, $price_id);
            if ($fallback_checkout_url !== '') {
                wp_send_json_success([
                    'url' => $fallback_checkout_url,
                    'session_id' => '',
                    'fallback' => true,
                ]);
                return;
            }

            wp_send_json_error(['message' => $this->get_checkout_error_message($result)]);
            return;
        }

        wp_send_json_success([
            'url' => $resolved_checkout_url,
            'session_id' => $fallback_used ? '' : ($result['sessionId'] ?? ''),
            'fallback' => $fallback_used,
        ]);
    }

    /**
     * AJAX handler: Create customer portal session
     */
    public function ajax_create_portal() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Check if user is authenticated via JWT token OR admin session with agency license
        $bbai_is_authenticated = $this->api_client->is_authenticated();
        $is_admin_authenticated = $this->is_admin_authenticated();
        $has_agency_license = false;
        
        if ($is_admin_authenticated || !$bbai_is_authenticated) {
            // Check if there's an agency license active
            $bbai_has_license = $this->api_client->has_active_license();
            if ($bbai_has_license) {
                $bbai_license_data = $this->api_client->get_license_data();
                if ($bbai_license_data && isset($bbai_license_data['organization'])) {
                    $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
                    $has_agency_license = ($bbai_license_plan === 'agency' || $bbai_license_plan === 'pro');
                }
            }
        }

        // Allow if authenticated via JWT OR admin-authenticated with agency/pro license
        if (!$bbai_is_authenticated && !($is_admin_authenticated && $has_agency_license)) {
            wp_send_json_error([
                'message' => __('Please log in to manage billing', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        // For admin-authenticated users with license, try using stored portal URL first
        if ($is_admin_authenticated && $has_agency_license && !$bbai_is_authenticated) {
            $stored_portal_url = Usage_Tracker::get_billing_portal_url();
            if (!empty($stored_portal_url)) {
                wp_send_json_success([
                    'url' => $stored_portal_url
                ]);
                return;
            }
        }

        $return_url = admin_url('upload.php?page=bbai');
        $result = $this->api_client->create_customer_portal_session($return_url);

        if (is_wp_error($result)) {
            // If backend doesn't support license key auth for portal, provide helpful message
            $error_message = $result->get_error_message();
            $error_message = is_string($error_message) ? $error_message : '';
            if (is_string($error_message) && $error_message && (strpos((string)$error_message, 'Authentication required') !== false || 
                strpos((string)$error_message, 'Unauthorized') !== false ||
                strpos((string)$error_message, 'not_authenticated') !== false)) {
                wp_send_json_error([
                    'message' => __('To manage your subscription, please log in with your account credentials (not just admin access). If you have an agency license, contact support to access billing management.', 'beepbeep-ai-alt-text-generator'),
                    'code' => 'not_authenticated'
                ]);
                return;
            }
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        wp_send_json_success([
            'url' => $result['url'] ?? ''
        ]);
    }

    /**
     * AJAX handler: Forgot password request
     */
    public function ajax_forgot_password() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $result = $this->api_client->forgot_password($email);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }

        // Pass through all data from backend, including reset link if provided
        $response_data = [
            'message' => __('Password reset link has been sent to your email. Please check your inbox and spam folder.', 'beepbeep-ai-alt-text-generator'),
        ];
        
        // Include reset link if provided (for development/testing when email service isn't configured)
        if (isset($result['resetLink'])) {
            $response_data['resetLink'] = $result['resetLink'];
            $response_data['note'] = $result['note'] ?? __('Email service is in development mode. Use this link to reset your password.', 'beepbeep-ai-alt-text-generator');
        }
        
        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler: Reset password with token
     */
    public function ajax_reset_password() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

	        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
	        $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
	        $password = is_string($password_input) ? $password_input : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (empty($token)) {
            wp_send_json_error([
                'message' => __('Reset token is required', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (empty($password) || strlen($password) < 8) {
            wp_send_json_error([
                'message' => __('Password must be at least 8 characters long', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $result = $this->api_client->reset_password($email, $token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now sign in with your new password.', 'beepbeep-ai-alt-text-generator'),
            'redirect' => admin_url('upload.php?page=bbai&password_reset=success')
        ]);
    }

    /**
     * AJAX handler: Get subscription information
     */
    public function ajax_get_subscription_info() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view subscription information', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        $subscription_info = $this->api_client->get_subscription_info();

        if (is_wp_error($subscription_info)) {
            wp_send_json_error([
                'message' => $subscription_info->get_error_message()
            ]);
            return;
        }

        wp_send_json_success($subscription_info);
    }

    /**
     * AJAX handler: Inline generation for selected attachment IDs (used by progress modal)
     */
    public function ajax_inline_generate() {
        // Start output buffering for AJAX to prevent any output from breaking JSON response
        // This is critical - any echo, warning, or error before wp_send_json_success() will break the response
        if (ob_get_level() === 0) {
            ob_start();
        } else {
            ob_clean();
        }
        
        // Fix nonce check - use beepbeepai_nonce to match JavaScript
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $attachment_ids = isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids']) ? array_map('absint', wp_unslash($_POST['attachment_ids'])) : [];
        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No attachment IDs provided.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            wp_send_json_error(['message' => __('Invalid attachment IDs.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $bbai_gen_started = microtime( true );

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted() ) {
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        // Avoid duplicate processing: these IDs were usually queued just before inline generation.
        // Clearing queue entries here ensures each image is generated once per action.
        Queue::clear_for_attachments($ids);

        $results = [];
        foreach ($ids as $id) {
            if (!$this->is_image($id)) {
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => __('Attachment is not an image.', 'beepbeep-ai-alt-text-generator'),
                ];
                continue;
            }

            try {
                // CRITICAL: generate_and_save() will only log credits if alt_text is successfully generated
                // It validates alt_text exists before updating usage or logging credits
                $generation = $this->generate_and_save($id, 'inline', 1, [], true);
                
                if (is_wp_error($generation)) {
                    // Generation failed - credits should NOT be logged (handled in generate_and_save)
                    $error_data = $generation->get_error_data();
                    $results[] = [
                        'attachment_id' => $id,
                        'success' => false,
                        'message' => $generation->get_error_message(),
                        'code'    => $generation->get_error_code(),
                        'remaining' => is_array($error_data) && isset($error_data['remaining']) ? $error_data['remaining'] : null,
                        'retry_after' => is_array($error_data) && isset($error_data['retry_after']) ? $error_data['retry_after'] : null,
                        'usage' => is_array($error_data) && isset($error_data['usage']) && is_array($error_data['usage']) ? $error_data['usage'] : null,
                    ];
                } else {
                    // Generation succeeded - credits were already logged in generate_and_save()
                    $results[] = [
                        'attachment_id' => $id,
                        'success' => true,
                        'alt_text' => $generation,
                        'title'    => get_the_title($id),
                    ];
                }
            } catch (\Exception $e) {
                // Catch any unexpected errors during generation
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: error message */
                        __('Unexpected error during generation: %s', 'beepbeep-ai-alt-text-generator'),
                        $e->getMessage()
                    ),
                    'code'    => 'generation_exception',
                ];
            } catch (\Error $e) {
                // Catch PHP 7+ fatal errors
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: error message */
                        __('Fatal error during generation: %s', 'beepbeep-ai-alt-text-generator'),
                        $e->getMessage()
                    ),
                    'code'    => 'generation_fatal',
                ];
            }
        }

        // Clean any output that might have been generated during processing
        // This is critical - any output before wp_send_json_success() will break the JSON response
        if (ob_get_level() > 0) {
            $ob_contents = ob_get_contents();
            if (!empty($ob_contents)) {
                // Log what was output (for debugging) but don't send it
                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Output detected before JSON response in ajax_inline_generate', [
                        'output_length' => strlen($ob_contents),
                        'output_preview' => substr($ob_contents, 0, 200),
                    ], 'ajax');
                }
            }
            ob_clean();
        }

        Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        // Ensure headers haven't been sent (which would break JSON response)
        if (headers_sent($file, $line)) {
            // Headers already sent - this is a critical error
            // Log it and try to send error response anyway
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'Headers already sent in ajax_inline_generate', [
                    'file' => $file,
                    'line' => $line,
                ], 'ajax');
            }
            // Still try to send JSON - wp_send_json_success handles this
        }
        
        // wp_send_json_success() will send headers and output, then exit
        // This ensures no output interferes with the JSON response
        if ( function_exists( 'bbai_telemetry_emit' ) ) {
            $bbai_duration_ms = (int) round( ( microtime( true ) - $bbai_gen_started ) * 1000 );
            $bbai_ok          = 0;
            $bbai_fail        = 0;
            foreach ( $results as $bbai_r ) {
                if ( ! empty( $bbai_r['success'] ) ) {
                    ++$bbai_ok;
                } else {
                    ++$bbai_fail;
                }
            }
            if ( $bbai_ok > 0 ) {
                bbai_telemetry_emit(
                    'alt_generated_success',
                    [
                        'number_of_images'   => $bbai_ok,
                        'processing_time_ms' => $bbai_duration_ms,
                        'failure_count'      => $bbai_fail,
                    ]
                );
                \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::bump_session_images_processed( $bbai_ok );
                $uid = get_current_user_id();
                if ( $uid > 0 && ! get_user_meta( $uid, 'bbai_telemetry_first_alt_at', true ) ) {
                    update_user_meta( $uid, 'bbai_telemetry_first_alt_at', time() );
                    bbai_telemetry_emit( 'first_alt_generated', [ 'number_of_images' => $bbai_ok ] );
                }
            }
            if ( $bbai_fail > 0 ) {
                bbai_telemetry_emit(
                    'alt_generated_failed',
                    [
                        'number_of_images'   => $bbai_fail,
                        'processing_time_ms' => $bbai_duration_ms,
                        'success_count'      => $bbai_ok,
                    ]
                );
            }
            if ( $bbai_duration_ms > 45000 ) {
                bbai_telemetry_emit(
                    'slow_operation_flag',
                    [
                        'operation'          => 'inline_generate',
                        'processing_time_ms' => $bbai_duration_ms,
                    ]
                );
            }
        }

        wp_send_json_success([
            'results' => $results,
        ]);
        
        // This line should never be reached (wp_send_json_success exits)
        // But included for safety
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Resolve the current site-wide missing ALT count for the guest trial UI.
     *
     * Priority:
     * 1. Dashboard stats / cached coverage scan
     * 2. Existing missing-attachment query
     * 3. Direct SQL fallback
     */
    private function get_guest_trial_missing_alt_count(bool $force_refresh = false): int {
        $dashboard_stats = $this->get_dashboard_stats_payload($force_refresh);
        if (is_array($dashboard_stats)) {
            foreach (['images_missing_alt', 'missing', 'missing_alt'] as $key) {
                if (isset($dashboard_stats[$key]) && is_numeric($dashboard_stats[$key])) {
                    return max(0, (int) $dashboard_stats[$key]);
                }
            }
        }

        $batch_size = 250;
        $offset = 0;
        $count_from_query = 0;

        do {
            $batch = $this->get_missing_attachment_ids($batch_size, $offset);
            $batch_count = is_array($batch) ? count($batch) : 0;
            $count_from_query += $batch_count;
            $offset += $batch_count;
        } while ($batch_count === $batch_size);

        if ($count_from_query > 0) {
            return max(0, $count_from_query);
        }

        global $wpdb;
        if (!isset($wpdb->posts, $wpdb->postmeta)) {
            return 0;
        }

        $image_mime_like = $wpdb->esc_like('image/') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- final fallback when dashboard stats and query counts are unavailable
        $missing_alt_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- trusted core table names, values are prepared
                'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' m ON (p.ID = m.post_id AND m.meta_key = %s) WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND (m.meta_id IS NULL OR TRIM(m.meta_value) = %s)',
                '_wp_attachment_image_alt',
                'attachment',
                'inherit',
                $image_mime_like,
                ''
            )
        );

        return max(0, $missing_alt_count);
    }

    /**
     * AJAX handler: Get missing image IDs for trial generation.
     * Returns up to N image IDs that have no alt text, where N = trial remaining.
     */
    public function ajax_trial_get_missing() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        $remaining = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_remaining();
        $is_trial  = \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user();

        if ( ! $is_trial ) {
            // Authenticated users should use the normal dashboard, not trial flow.
            wp_send_json_error( [ 'message' => __( 'Not a trial user.', 'beepbeep-ai-alt-text-generator' ), 'code' => 'not_trial' ] );
            return;
        }

        if ( $remaining <= 0 ) {
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        $limit = min( $remaining, \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit() );
        $ids   = $this->get_missing_attachment_ids( $limit );

        $missing_alt_count = $this->get_guest_trial_missing_alt_count(false);

        // Get thumbnail URLs for onboarding preview.
        $images = [];
        foreach ( $ids as $id ) {
            $thumb     = wp_get_attachment_image_url( $id, 'thumbnail' );
            $title     = get_the_title( $id );
            $file_path = get_attached_file( $id );
            $filename  = $file_path ? wp_basename( $file_path ) : '';
            $images[] = [
                'id'       => $id,
                'thumb'    => $thumb ? $thumb : '',
                'title'    => $title ? $title : sprintf( 'Image #%d', $id ),
                'filename' => $filename,
            ];
        }

        wp_send_json_success( array_merge(
            $this->get_trial_usage_payload(
                [
                    'remaining' => $remaining,
                    'credits_remaining' => $remaining,
                    'remaining_free_images' => $remaining,
                ]
            ),
            [
                'images'            => $images,
                'count'             => count( $images ),
                'missing_alt_count' => $missing_alt_count,
            ]
        ) );
    }

    /**
     * Run one trial generation through the standard pipeline with transient retry handling.
     *
     * @param int $attachment_id Attachment ID.
     * @return array{result:mixed,retry_attempts:int}
     */
    private function generate_trial_single_with_retry( int $attachment_id, bool $quota_claimed = false ): array {
        $retry_attempts = 0;
        $max_retries    = 2;
        $claimed_slots  = 0;
        if ( ! $quota_claimed ) {
            $claimed_slots = \BeepBeepAI\AltTextGenerator\Trial_Quota::claim( 1 );
            if ( $claimed_slots <= 0 ) {
                return [
                    'result'         => $this->get_trial_exhausted_error(),
                    'retry_attempts' => 0,
                ];
            }
        }
        \BeepBeepAI\AltTextGenerator\Trial_Quota::begin_claimed_generation();
        try {
            $result = $this->generate_and_save( $attachment_id, 'trial', 1, [], false, true );

            while ( is_wp_error( $result ) && 'quota_check_mismatch' === $result->get_error_code() && $retry_attempts < $max_retries ) {
                $retry_attempts++;
                $retry_data = $result->get_error_data();
                $retry_after_seconds = 0;
                if ( is_array( $retry_data ) && isset( $retry_data['retry_after'] ) ) {
                    $retry_after_seconds = absint( $retry_data['retry_after'] );
                }

                $retry_delay_us = $retry_after_seconds > 0 ? $retry_after_seconds * 1000000 : 500000;
                $retry_delay_us = max( 250000, min( 2000000, $retry_delay_us ) );
                usleep( $retry_delay_us );

                $result = $this->generate_and_save( $attachment_id, 'trial', 1, [], false, true );
            }
        } finally {
            \BeepBeepAI\AltTextGenerator\Trial_Quota::end_claimed_generation();
        }

        if ( ! $quota_claimed && is_wp_error( $result ) && $claimed_slots > 0 ) {
            \BeepBeepAI\AltTextGenerator\Trial_Quota::release( $claimed_slots );
        }

        return [
            'result'         => $result,
            'retry_attempts' => $retry_attempts,
        ];
    }

    /**
     * AJAX handler: Generate demo batch alt text for trial users.
     *
     * Finds up to `limit` images with missing alt text and runs the same
     * generation pipeline used by single-image trial generation.
     */
    public function ajax_trial_demo_generate_batch() {
        if ( ob_get_level() === 0 ) {
            ob_start();
        } else {
            ob_clean();
        }

        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( ! \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() ) {
            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_error( [
                'message' => __( 'Not a trial user.', 'beepbeep-ai-alt-text-generator' ),
                'code'    => 'not_trial',
            ] );
            return;
        }

        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted() ) {
            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        $requested = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 3;
        $requested = max( 1, min( \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit(), $requested ) );

        // Atomically claim allowance before any processing starts.
        $accepted_count = \BeepBeepAI\AltTextGenerator\Trial_Quota::claim( $requested );
        if ( $accepted_count <= 0 ) {
            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        $batch_limit      = $accepted_count;
        $ids              = $this->get_missing_attachment_ids( $batch_limit );

        if ( empty( $ids ) ) {
            $missing_alt_count = $this->get_guest_trial_missing_alt_count(false);

            \BeepBeepAI\AltTextGenerator\Trial_Quota::release( $accepted_count );
            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_success( [
                'requested'         => $requested,
                'accepted_count'    => 0,
                'attempted'         => 0,
                'generated_count'   => 0,
                'summary'           => [],
                'errors'            => [],
                'remaining'         => \BeepBeepAI\AltTextGenerator\Trial_Quota::get_remaining(),
                'used'              => \BeepBeepAI\AltTextGenerator\Trial_Quota::get_used(),
                'limit'             => \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit(),
                'missing_alt_count' => $missing_alt_count,
                'no_missing_images' => true,
                'trial_exhausted'   => false,
                'upload_url'        => admin_url( 'media-new.php' ),
                'library_url'       => admin_url( 'admin.php?page=bbai-library' ),
                'message'           => __( 'No images are currently missing alt text. Upload an image to try the demo.', 'beepbeep-ai-alt-text-generator' ),
            ] );
            return;
        }

        $summary         = [];
        $errors          = [];
        $trial_exhausted = false;

        foreach ( $ids as $attachment_id_raw ) {
            $attachment_id = absint( $attachment_id_raw );
            if ( $attachment_id <= 0 || ! $this->is_image( $attachment_id ) ) {
                continue;
            }

            $previous_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            $file_path    = get_attached_file( $attachment_id );
            $filename     = $file_path ? wp_basename( $file_path ) : '';
            if ( '' === $filename ) {
                $filename = sprintf(
                    /* translators: %d: attachment ID. */
                    __( 'Image #%d', 'beepbeep-ai-alt-text-generator' ),
                    $attachment_id
                );
            }

            $generation     = $this->generate_trial_single_with_retry( $attachment_id, true );
            $result         = $generation['result'];
            $retry_attempts = isset( $generation['retry_attempts'] ) ? absint( $generation['retry_attempts'] ) : 0;

            if ( is_wp_error( $result ) ) {
                $error_code = (string) $result->get_error_code();
                if ( 'bbai_trial_exhausted' === $error_code ) {
                    $trial_exhausted = true;
                    break;
                }

                $errors[] = [
                    'attachment_id' => $attachment_id,
                    'filename'      => $filename,
                    'code'          => $error_code,
                    'message'       => $result->get_error_message(),
                    'retry_attempts'=> $retry_attempts,
                ];
                continue;
            }

            $summary[] = [
                'attachment_id' => $attachment_id,
                'filename'      => $filename,
                'previous_alt'  => $previous_alt,
                'new_alt'       => (string) $result,
            ];
        }

        $missing_alt_count = $this->get_guest_trial_missing_alt_count(true);

        $generated_count = count( $summary );
        $attempted_count = $generated_count + count( $errors );
        $unused_claimed  = max( 0, $accepted_count - $generated_count );
        $response_code   = 'success';
        if ( $unused_claimed > 0 ) {
            \BeepBeepAI\AltTextGenerator\Trial_Quota::release( $unused_claimed );
        }
        $remaining_after = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_remaining();
        $trial_exhausted = $trial_exhausted || $remaining_after <= 0;

        $auth_error_codes   = [ 'auth_required', 'user_not_found', 'trial_backend_auth', 'license_required' ];
        $all_auth_related   = ! empty( $errors );
        $first_auth_message = '';
        foreach ( $errors as $error_item ) {
            $item_code = isset( $error_item['code'] ) ? (string) $error_item['code'] : '';
            if ( ! in_array( $item_code, $auth_error_codes, true ) ) {
                $all_auth_related = false;
                break;
            }
            if ( '' === $first_auth_message && ! empty( $error_item['message'] ) ) {
                $first_auth_message = (string) $error_item['message'];
            }
        }

        if ( $generated_count > 0 ) {
            $message = sprintf(
                /* translators: %d: number of images */
                _n(
                    'Generated alt text for %d image.',
                    'Generated alt text for %d images.',
                    $generated_count,
                    'beepbeep-ai-alt-text-generator'
                ),
                $generated_count
            );
        } elseif ( $trial_exhausted ) {
            $message = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_exhausted_message();
            $response_code = 'bbai_trial_exhausted';
        } elseif ( $all_auth_related ) {
            $message = '' !== $first_auth_message
                ? $first_auth_message
                : __( 'Create a free account to continue fixing these images.', 'beepbeep-ai-alt-text-generator' );
            $response_code = 'trial_backend_auth';
        } else {
            $message = __( 'No images were updated. Please try again.', 'beepbeep-ai-alt-text-generator' );
            $response_code = 'trial_generation_failed';
        }

        if ( ob_get_level() > 0 ) { ob_clean(); }
        wp_send_json_success( array_merge(
            $this->get_trial_usage_payload(
                [
                    'remaining' => $remaining_after,
                    'credits_remaining' => $remaining_after,
                    'remaining_free_images' => $remaining_after,
                    'trial_exhausted' => $trial_exhausted,
                    'quota_state' => $trial_exhausted ? 'exhausted' : ( $remaining_after <= max( 1, (int) \BeepBeepAI\AltTextGenerator\Trial_Quota::get_low_credit_threshold() ) ? 'near_limit' : 'active' ),
                ]
            ),
            [
                'requested'         => $requested,
                'accepted_count'    => $accepted_count,
                'attempted'         => $attempted_count,
                'generated_count'   => $generated_count,
                'summary'           => $summary,
                'errors'            => $errors,
                'missing_alt_count' => $missing_alt_count,
                'no_missing_images' => false,
                'upload_url'        => admin_url( 'media-new.php' ),
                'library_url'       => admin_url( 'admin.php?page=bbai-library' ),
                'code'              => $response_code,
                'message'           => $message,
            ]
        ) );
    }

    /**
     * AJAX handler: Generate alt text for a single image (trial-safe).
     * Lightweight wrapper around generate_and_save for trial modal one-at-a-time generation.
     */
    public function ajax_trial_generate_single() {
        if ( ob_get_level() === 0 ) {
            ob_start();
        } else {
            ob_clean();
        }

        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_exhausted() ) {
            wp_send_json_error( $this->get_trial_exhausted_payload() );
            return;
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        if ( 0 === $attachment_id || ! $this->is_image( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid image.', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        try {
            $accepted_count = \BeepBeepAI\AltTextGenerator\Trial_Quota::claim( 1 );
            if ( $accepted_count <= 0 ) {
                if ( ob_get_level() > 0 ) { ob_clean(); }
                wp_send_json_error( $this->get_trial_exhausted_payload() );
                return;
            }

            $generation     = $this->generate_trial_single_with_retry( $attachment_id, true );
            $result         = $generation['result'];
            $retry_attempts = isset( $generation['retry_attempts'] ) ? absint( $generation['retry_attempts'] ) : 0;

            if ( is_wp_error( $result ) ) {
                \BeepBeepAI\AltTextGenerator\Trial_Quota::release( 1 );
                $code = $result->get_error_code();
                $data = [
                    'message' => $result->get_error_message(),
                    'code'    => $code,
                ];

                if ( 'quota_check_mismatch' === $code ) {
                    $data['message'] = __( 'Temporary quota sync issue. Please try again in a few seconds.', 'beepbeep-ai-alt-text-generator' );
                    $data['retry_after'] = 2;
                }

                // Pass through trial exhausted data.
                if ( 'bbai_trial_exhausted' === $code ) {
                    $data = array_merge( $data, $result->get_error_data() ?: [] );
                }

                if ( $retry_attempts > 0 ) {
                    $data['retry_attempts'] = $retry_attempts;
                }

                if ( ob_get_level() > 0 ) { ob_clean(); }
                wp_send_json_error( $data );
                return;
            }

            // Success — get updated trial status.
            $trial_remaining = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_remaining();
            $trial_used      = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_used();

            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_success( array_merge(
                $this->get_trial_usage_payload(
                    [
                        'remaining' => $trial_remaining,
                        'credits_remaining' => $trial_remaining,
                        'remaining_free_images' => $trial_remaining,
                        'used' => $trial_used,
                        'credits_used' => $trial_used,
                        'trial_exhausted' => $trial_remaining <= 0,
                        'quota_state' => $trial_remaining <= 0 ? 'exhausted' : ( $trial_remaining <= max( 1, (int) \BeepBeepAI\AltTextGenerator\Trial_Quota::get_low_credit_threshold() ) ? 'near_limit' : 'active' ),
                    ]
                ),
                [
                    'attachment_id' => $attachment_id,
                    'alt_text'      => $result,
                    'title'         => get_the_title( $attachment_id ),
                    'accepted_count' => 1,
                ]
            ) );
        } catch ( \Exception $e ) {
            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_error( [ 'message' => $e->getMessage(), 'code' => 'generation_exception' ] );
        } catch ( \Error $e ) {
            if ( ob_get_level() > 0 ) { ob_clean(); }
            wp_send_json_error( [ 'message' => $e->getMessage(), 'code' => 'generation_fatal' ] );
        }
    }

    /**
     * AJAX handler: Get recent activity for timeline
     */
    public function ajax_debug_logs() {
        $nonce_input = '';
        if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
            $nonce_input = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
        } elseif ( isset( $_REQUEST['nonce'] ) ) {
            $nonce_input = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
        }

        if ( ! wp_verify_nonce( $nonce_input, 'wp_rest' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->can_show_debug_logs_tab() ) {
            wp_send_json_error( [ 'message' => __( 'Debug logs are not available for this account.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        $level = isset( $_REQUEST['level'] ) ? sanitize_key( wp_unslash( $_REQUEST['level'] ) ) : '';
        if ( 'warn' === $level ) {
            $level = 'warning';
        } elseif ( 'err' === $level || 'fatal' === $level ) {
            $level = 'error';
        }
        if ( ! in_array( $level, [ '', 'debug', 'info', 'warning', 'error' ], true ) ) {
            $level = '';
        }

        $args = [
            'level' => $level,
            'search' => isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '',
            'date' => isset( $_REQUEST['date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date'] ) ) : '',
            'date_from' => isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '',
            'date_to' => isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '',
            'per_page' => isset( $_REQUEST['per_page'] ) ? max( 1, absint( wp_unslash( $_REQUEST['per_page'] ) ) ) : 10,
            'page' => isset( $_REQUEST['page'] ) ? max( 1, absint( wp_unslash( $_REQUEST['page'] ) ) ) : 1,
        ];

        wp_send_json( $this->get_debug_payload( $args ) );
    }

    public function ajax_debug_logs_clear() {
        $nonce_input = '';
        if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
            $nonce_input = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
        } elseif ( isset( $_REQUEST['nonce'] ) ) {
            $nonce_input = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
        }

        if ( ! wp_verify_nonce( $nonce_input, 'wp_rest' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->can_show_debug_logs_tab() ) {
            wp_send_json_error( [ 'message' => __( 'Debug logs are not available for this account.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
            wp_send_json( [ 'cleared' => false, 'stats' => [] ] );
            return;
        }

        $older_than = isset( $_REQUEST['older_than'] ) ? absint( wp_unslash( $_REQUEST['older_than'] ) ) : 0;
        if ( $older_than > 0 ) {
            Debug_Log::delete_older_than( $older_than );
        } else {
            Debug_Log::clear_logs();
        }

        wp_send_json( [
            'cleared' => true,
            'stats' => Debug_Log::get_stats(),
        ] );
    }

    public function ajax_get_activity() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Get recent activity from usage logs
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
        
        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 10;
        $filters = [
            'per_page' => min($limit, 50), // Max 50 items
            'page' => 1,
            'skip_site_filter' => true, // Skip site_id filter for activity timeline (display only)
        ];

        $result = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_usage_events($filters);
        $events = isset($result['events']) ? $result['events'] : [];

        // Format events for the timeline display
        $activities = [];
        foreach ($events as $event) {
            $action_type = isset($event['action_type']) ? sanitize_text_field($event['action_type']) : 'generate';
            $image_id = isset($event['image_id']) ? absint($event['image_id']) : 0;
            $user_name = isset($event['display_name']) ? sanitize_text_field($event['display_name']) : __('System', 'beepbeep-ai-alt-text-generator');
            $created_at = isset($event['created_at']) ? $event['created_at'] : '';
            
            // Determine activity type and message
            $type = 'generate';
            $title = '';
            $description = '';
            
            if (strpos($action_type, 'bulk') !== false) {
                $type = 'bulk';
                $title = sprintf(__('Bulk alt text generation', 'beepbeep-ai-alt-text-generator'));
                $description = sprintf(
                    /* translators: 1: user name */
                    __('Processed by %s', 'beepbeep-ai-alt-text-generator'),
                    $user_name
                );
            } elseif (strpos($action_type, 'regenerate') !== false || strpos($action_type, 'reopt') !== false) {
                $type = 'regenerate';
                $title = sprintf(__('Alt text regenerated', 'beepbeep-ai-alt-text-generator'));
                if ($image_id > 0) {
                    $image_title = get_the_title($image_id);
                    $description = $image_title
                        ? sprintf(
                            /* translators: 1: image title */
                            __('Image: %s', 'beepbeep-ai-alt-text-generator'),
                            $image_title
                        )
                        : sprintf(
                            /* translators: 1: image ID */
                            __('Image ID: %d', 'beepbeep-ai-alt-text-generator'),
                            $image_id
                        );
                }
            } else {
                $type = 'generate';
                $title = sprintf(__('Alt text generated', 'beepbeep-ai-alt-text-generator'));
                if ($image_id > 0) {
                    $image_title = get_the_title($image_id);
                    $description = $image_title
                        ? sprintf(
                            /* translators: 1: image title */
                            __('Image: %s', 'beepbeep-ai-alt-text-generator'),
                            $image_title
                        )
                        : sprintf(
                            /* translators: 1: image ID */
                            __('Image ID: %d', 'beepbeep-ai-alt-text-generator'),
                            $image_id
                        );
                }
            }

            $activities[] = [
                'type' => $type,
                'action' => $action_type,
                'title' => $title,
                'description' => $description,
                'details' => $description,
                'timestamp' => $created_at,
                'timeAgo' => $this->format_time_ago($created_at),
            ];
        }

        wp_send_json_success($activities);
    }

    /**
     * Format timestamp as "time ago" string
     */
    private function format_time_ago($timestamp) {
        if (empty($timestamp)) {
            return __('Just now', 'beepbeep-ai-alt-text-generator');
        }

        $time = strtotime($timestamp);
        if ($time === false || $time <= 0) {
            return __('Just now', 'beepbeep-ai-alt-text-generator');
        }

        $diff = time() - $time;
        $minutes = floor($diff / 60);
        $hours = floor($diff / 3600);
        $days = floor($diff / 86400);

        if ($minutes < 1) {
            return __('Just now', 'beepbeep-ai-alt-text-generator');
        } elseif ($minutes < 60) {
            return sprintf(
                /* translators: 1: number of minutes */
                _n('%d minute ago', '%d minutes ago', $minutes, 'beepbeep-ai-alt-text-generator'),
                $minutes
            );
        } elseif ($hours < 24) {
            return sprintf(
                /* translators: 1: number of hours */
                _n('%d hour ago', '%d hours ago', $hours, 'beepbeep-ai-alt-text-generator'),
                $hours
            );
        } elseif ($days < 7) {
            return sprintf(
                /* translators: 1: number of days */
                _n('%d day ago', '%d days ago', $days, 'beepbeep-ai-alt-text-generator'),
                $days
            );
        } else {
            return date_i18n(get_option('date_format'), $time);
        }
    }

    /**
     * AJAX handler: Send contact form via Resend
     */
    public function ajax_send_contact_form() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Rate limiting check (3 submissions per hour per user)
        $user_id = get_current_user_id();
        $current_hour = wp_date('Y-m-d-H');
        $rate_limit_key = 'bbai_contact_limit_' . $user_id . '_' . $current_hour;
        $submission_count = get_transient($rate_limit_key);
        
        if ($submission_count !== false && intval($submission_count) >= 3) {
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please wait before submitting another message.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Sanitize and validate input
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $wp_version = isset($_POST['wp_version']) ? sanitize_text_field(wp_unslash($_POST['wp_version'])) : get_bloginfo('version');
        $plugin_version = isset($_POST['plugin_version']) ? sanitize_text_field(wp_unslash($_POST['plugin_version'])) : BEEPBEEP_AI_VERSION;

        // Validate required fields
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            wp_send_json_error([
                'message' => __('Please fill in all required fields.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error([
                'message' => __('Invalid email address format.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Prepare contact data
        $contact_data = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'wp_version' => $wp_version,
            'plugin_version' => $plugin_version
        ];

        // Send via backend API (backend has Resend configured)
        $backend_response = $this->api_client->send_contact_email($contact_data);
        
        if (is_wp_error($backend_response)) {
            // Backend API failed - show user-friendly error
            $error_code = $backend_response->get_error_code();
            $error_message = $backend_response->get_error_message();
            
            // Provide more helpful error messages based on error type
            if ($error_code === 'auth_required' || $error_code === 'license_required') {
                $user_message = __('Unable to send message. Please ensure you are logged in and try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'api_unreachable' || $error_code === 'api_timeout') {
                $user_message = __('Unable to connect to the server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'contact_email_failed') {
                $user_message = $error_message ?: __('Failed to send message. Please try again later.', 'beepbeep-ai-alt-text-generator');
            } else {
                $user_message = sprintf(
                    /* translators: 1: error message */
                    __('Unable to send message: %s', 'beepbeep-ai-alt-text-generator'),
                    $error_message ?: __('Unknown error', 'beepbeep-ai-alt-text-generator')
                );
            }
            
            wp_send_json_error([
                'message' => $user_message
            ]);
            return;
        }
        
        // Success via backend
        $result = $backend_response;

        // Save to database for viewing in admin
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        
        $site_hash = \BeepBeepAI\AltTextGenerator\get_site_identifier();
        $bbai_license_key = $this->api_client->get_license_key();
        
        $contact_data['site_url'] = get_site_url();
        $contact_data['site_hash'] = $site_hash;
        $contact_data['license_key'] = $bbai_license_key ?: null;
        
        \BeepBeepAI\AltTextGenerator\Contact_Submissions::save_submission($contact_data);

        // Increment rate limit counter
        if ($submission_count === false) {
            set_transient($rate_limit_key, 1, HOUR_IN_SECONDS);
        } else {
            set_transient($rate_limit_key, intval($submission_count) + 1, HOUR_IN_SECONDS);
        }

        // Success
        wp_send_json_success([
            'message' => $result['message'] ?? __('Your message has been sent successfully. We\'ll get back to you soon!', 'beepbeep-ai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Get contact form submissions
     */
    public function ajax_get_contact_submissions() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';

        $status_input = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $allowed_statuses = ['', 'new', 'read', 'replied'];
        $status = in_array($status_input, $allowed_statuses, true) ? $status_input : '';
        $orderby_input = isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : 'created_at';
        $allowed_orderby = ['created_at', 'name', 'email', 'status'];
        $orderby = in_array($orderby_input, $allowed_orderby, true) ? $orderby_input : 'created_at';
        $order_input = isset($_POST['order']) ? strtoupper(sanitize_key(wp_unslash($_POST['order']))) : 'DESC';
        $allowed_order = ['ASC', 'DESC'];
        $order = in_array($order_input, $allowed_order, true) ? $order_input : 'DESC';

        $args = [
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'page'     => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'status'   => $status,
            'search'   => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'orderby'  => $orderby,
            'order'    => $order,
        ];

        // If ID is provided, get single submission
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = absint(wp_unslash($_POST['id']));
            $submission = \BeepBeepAI\AltTextGenerator\Contact_Submissions::get_submission($id);
            if ($submission) {
                wp_send_json_success([
                    'items' => [$submission],
                    'total' => 1,
                    'pages' => 1
                ]);
            } else {
                wp_send_json_error(['message' => __('Submission not found', 'beepbeep-ai-alt-text-generator')]);
                return;
            }
            return;
        }

        $result = \BeepBeepAI\AltTextGenerator\Contact_Submissions::get_submissions($args);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler: Update contact submission status
     */
    public function ajax_update_contact_submission_status() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $status_input = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $allowed_statuses = ['new', 'read', 'replied'];
        $status = in_array($status_input, $allowed_statuses, true) ? $status_input : 'new';

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid submission ID', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = \BeepBeepAI\AltTextGenerator\Contact_Submissions::update_status($id, $status);

        if ($result) {
            wp_send_json_success(['message' => __('Status updated successfully', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update status', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * AJAX handler: Delete contact submission
     */
    public function ajax_delete_contact_submission() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid submission ID', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = \BeepBeepAI\AltTextGenerator\Contact_Submissions::delete_submission($id);

        if ($result) {
            wp_send_json_success(['message' => __('Submission deleted successfully', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete submission', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

	    public function ajax_export_analytics() {
	        $action = 'beepbeepai_nonce';
	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
	            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
	            return;
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('Permission denied.', 'beepbeep-ai-alt-text-generator'));
	        }

		        global $wpdb;
		        $posts_table    = esc_sql( $wpdb->posts );
		        $postmeta_table = esc_sql( $wpdb->postmeta );
		        $image_mime_like = $wpdb->esc_like('image/') . '%';

		        // Get stats (reuse library cache from BBAI_Cache).
		        $bbai_total_images = BBAI_Cache::get( 'library', 'total' );
		        if ( false === $bbai_total_images ) {
		            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		            $bbai_total_images = (int) $wpdb->get_var($wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		                "SELECT COUNT(*) FROM `{$posts_table}` WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s",
		                'attachment', 'inherit', $image_mime_like
		            ));
		            BBAI_Cache::set( 'library', 'total', $bbai_total_images, BBAI_Cache::DEFAULT_TTL );
		        }
		        $bbai_total_images = (int) $bbai_total_images;

		        $with_alt_count = BBAI_Cache::get( 'library', 'with_alt' );
		        if ( false === $with_alt_count ) {
		            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		            $with_alt_count = (int) $wpdb->get_var($wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		                "SELECT COUNT(DISTINCT p.ID) FROM `{$posts_table}` p INNER JOIN `{$postmeta_table}` pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s AND pm.meta_key = %s AND TRIM(pm.meta_value) <> %s",
		                'attachment', $image_mime_like, 'inherit', '_wp_attachment_image_alt', ''
		            ));
		            BBAI_Cache::set( 'library', 'with_alt', $with_alt_count, BBAI_Cache::DEFAULT_TTL );
		        }
		        $with_alt_count = (int) $with_alt_count;

	        $missing_count = $bbai_total_images - $with_alt_count;
        $bbai_coverage_percent = $bbai_total_images > 0 ? round(($with_alt_count / $bbai_total_images) * 100) : 0;

        // Get usage stats
        $bbai_usage_stats = $this->api_client->get_usage_stats();
	        $bbai_alt_texts_generated = isset($bbai_usage_stats['used']) ? (int) $bbai_usage_stats['used'] : 0;
	
	        // Generate CSV
	        $filename = 'beepbeep-ai-analytics-' . wp_date('Y-m-d') . '.csv';
	        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->bbai_prepare_download_stream();
        // BOM for Excel compatibility
        $this->bbai_output_contents(chr(0xEF).chr(0xBB).chr(0xBF));

	        // Rows
	        $this->bbai_output_contents($this->bbai_csv_row(['Metric', 'Value']));
	        $this->bbai_output_contents($this->bbai_csv_row(['Total Images', $this->bbai_csv_safe_cell($bbai_total_images)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Images with Alt Text', $this->bbai_csv_safe_cell($with_alt_count)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Images Missing Alt Text', $this->bbai_csv_safe_cell($missing_count)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Coverage Percentage', $this->bbai_csv_safe_cell($bbai_coverage_percent . '%')]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Alt Texts Generated', $this->bbai_csv_safe_cell($bbai_alt_texts_generated)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Export Date', $this->bbai_csv_safe_cell(current_time('Y-m-d H:i:s'))]));
	        exit;
	    }

    /**
     * Extract ALT text from a backend response, supporting nested payloads.
     *
     * @param mixed       $api_response Response data array.
     * @param string|null $source       Output: where the alt text was found.
     * @return string
     */
    private function extract_alt_text_from_response($api_response, &$source = null): string {
        if (!is_array($api_response)) {
            return '';
        }

        $alt_text = $this->extract_alt_text_from_array($api_response, $source);
        if ($alt_text !== '') {
            return $alt_text;
        }

        $nested_keys = ['data', 'result', 'output', 'response'];
        foreach ($nested_keys as $key) {
            if (isset($api_response[$key]) && is_array($api_response[$key])) {
                $nested_source = null;
                $alt_text = $this->extract_alt_text_from_array($api_response[$key], $nested_source);
                if ($alt_text !== '') {
                    $source = $key . ($nested_source ? ':' . $nested_source : '');
                    return $alt_text;
                }
            }
        }

        // OpenAI-style payloads (if backend returns raw response)
        if (isset($api_response['choices'][0]['message']['content']) && is_string($api_response['choices'][0]['message']['content'])) {
            $source = 'choices.message.content';
            return $this->normalize_alt_text($api_response['choices'][0]['message']['content'], $source);
        }
        if (isset($api_response['choices'][0]['text']) && is_string($api_response['choices'][0]['text'])) {
            $source = 'choices.text';
            return $this->normalize_alt_text($api_response['choices'][0]['text'], $source);
        }

        return '';
    }

    /**
     * Extract ALT text from a flat associative array.
     *
     * @param array       $data   Response data.
     * @param string|null $source Output: which key matched.
     * @return string
     */
    private function extract_alt_text_from_array(array $data, &$source = null): string {
        $keys = ['altText', 'alt_text', 'alttext', 'alt', 'description', 'text'];
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $source = $key;
                return $this->normalize_alt_text($data[$key], $source);
            }
        }
        return '';
    }

    /**
     * Normalize ALT text and unwrap JSON if it contains alt text fields.
     *
     * @param string      $value  Raw text value.
     * @param string|null $source Output: updated source if JSON unwrap happens.
     * @return string
     */
    private function normalize_alt_text(string $value, &$source = null): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // If the response is JSON, attempt to pull alt text from it.
        if (isset($value[0]) && $value[0] === '{') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $nested_source = null;
                $alt_text = $this->extract_alt_text_from_array($decoded, $nested_source);
                if ($alt_text !== '') {
                    $source = 'json:' . ($source ?? 'text') . ($nested_source ? ':' . $nested_source : '');
                    return $alt_text;
                }
            }
        }

        // Some models return a quoted string literal (e.g. "\"A red car on a street.\"").
        // Decode/unwrap once so quotes are not persisted into the final alt text.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                if ($first === '"') {
                    $decoded_string = json_decode($value, true);
                    if (is_string($decoded_string) && trim($decoded_string) !== '') {
                        $value = trim($decoded_string);
                        $source = 'json-string:' . ($source ?? 'text');
                    }
                }

                if (strlen($value) >= 2) {
                    $first = $value[0];
                    $last  = $value[strlen($value) - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $unwrapped = trim(substr($value, 1, -1));
                        if ($unwrapped !== '') {
                            $value = $unwrapped;
                            $source = 'quoted:' . ($source ?? 'text');
                        }
                    }
                }
            }
        }

        return $value;
    }
}

// Class instantiation moved to class-bbai-admin.php bootstrap_core()
// to prevent duplicate menu registration

// Attachment edit screen behaviour handled via enqueued scripts; inline scripts removed for compliance.
