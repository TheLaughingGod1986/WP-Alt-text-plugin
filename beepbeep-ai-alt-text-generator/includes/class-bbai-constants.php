<?php
/**
 * Plugin Constants
 * Central location for all configuration values and magic numbers
 *
 * @package BeepBeepAI\AltTextGenerator
 * @since 4.2.3
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BbAI_Constants
 *
 * Defines all plugin constants in one place for easy maintenance
 * and configuration.
 */
class BbAI_Constants {

    // ===================================
    // Free Plan Limits
    // ===================================
    const FREE_PLAN_LIMIT = 50;
    const FREE_PLAN_NAME = 'free';

    // ===================================
    // Queue Processing
    // ===================================
    const QUEUE_DELAY_SECONDS = 30;
    const QUEUE_BATCH_SIZE = 10;
    const QUEUE_MAX_ATTEMPTS = 3;

    // ===================================
    // API Configuration
    // ===================================
    const API_TIMEOUT_SECONDS = 30;
    const API_RETRY_ATTEMPTS = 3;
    const API_RETRY_DELAY = 2;

    // ===================================
    // Cache & Transient Durations
    // ===================================
    const CACHE_DURATION_HOUR = 3600;
    const CACHE_DURATION_DAY = 86400;
    const CACHE_DURATION_WEEK = 604800;
    const CACHE_DURATION_MONTH = 2592000;

    // ===================================
    // Database Table Names
    // ===================================
    const TABLE_QUEUE = 'bbai_queue';
    const TABLE_CREDIT_USAGE = 'bbai_credit_usage';

    // ===================================
    // Option Names
    // ===================================
    const OPTION_PREFIX = 'beepbeep_ai_';
    const OPTION_CREDITS_USED = 'bbai_credits_used';
    const OPTION_LICENSE_KEY = 'bbai_license_key';
    const OPTION_API_TOKEN = 'bbai_api_token';

    // ===================================
    // Error Codes
    // ===================================
    const ERROR_LIMIT_REACHED = 'limit_reached';
    const ERROR_INVALID_ATTACHMENT = 'invalid_attachment';
    const ERROR_API_FAILED = 'api_failed';
    const ERROR_UNAUTHORIZED = 'unauthorized';
    const ERROR_QUOTA_EXCEEDED = 'quota_exceeded';

    // ===================================
    // HTTP Status Codes
    // ===================================
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_INTERNAL_ERROR = 500;

    // ===================================
    // Generation Sources
    // ===================================
    const SOURCE_MANUAL = 'manual';
    const SOURCE_AUTO = 'auto';
    const SOURCE_BULK = 'bulk';
    const SOURCE_UPLOAD = 'upload';
    const SOURCE_AJAX = 'ajax';

    // ===================================
    // Queue Statuses
    // ===================================
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // ===================================
    // Plan Types
    // ===================================
    const PLAN_FREE = 'free';
    const PLAN_PRO = 'pro';
    const PLAN_AGENCY = 'agency';

    // ===================================
    // File Size Limits
    // ===================================
    const MAX_IMAGE_SIZE_MB = 10;
    const MAX_IMAGE_SIZE_BYTES = 10485760; // 10MB in bytes

    // ===================================
    // Pagination
    // ===================================
    const DEFAULT_PER_PAGE = 50;
    const MAX_PER_PAGE = 200;

    // ===================================
    // URLs
    // ===================================
    const BACKEND_API_URL = 'https://alttext-ai-backend.onrender.com';
    const PRIVACY_URL = 'https://oppti.dev/privacy';
    const TERMS_URL = 'https://oppti.dev/terms';

    // ===================================
    // Text Domain
    // ===================================
    const TEXT_DOMAIN = 'beepbeep-ai-alt-text-generator';

    // ===================================
    // Asset Versions (for cache busting)
    // ===================================
    const ASSET_VERSION = '4.2.3';

    /**
     * Get a constant value with type safety
     *
     * @param string $constant_name Name of the constant
     * @return mixed The constant value
     * @throws \InvalidArgumentException If constant doesn't exist
     */
    public static function get(string $constant_name) {
        if (!defined("self::$constant_name")) {
            throw new \InvalidArgumentException("Constant $constant_name does not exist");
        }

        return constant("self::$constant_name");
    }

    /**
     * Get all plan types
     *
     * @return array List of plan types
     */
    public static function get_plan_types(): array {
        return [
            self::PLAN_FREE,
            self::PLAN_PRO,
            self::PLAN_AGENCY,
        ];
    }

    /**
     * Get all queue statuses
     *
     * @return array List of queue statuses
     */
    public static function get_queue_statuses(): array {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }

    /**
     * Get all generation sources
     *
     * @return array List of generation sources
     */
    public static function get_generation_sources(): array {
        return [
            self::SOURCE_MANUAL,
            self::SOURCE_AUTO,
            self::SOURCE_BULK,
            self::SOURCE_UPLOAD,
            self::SOURCE_AJAX,
        ];
    }

    /**
     * Check if a plan is premium (Pro or Agency)
     *
     * @param string $plan Plan slug
     * @return bool True if premium plan
     */
    public static function is_premium_plan(string $plan): bool {
        return in_array($plan, [self::PLAN_PRO, self::PLAN_AGENCY], true);
    }

    /**
     * Get cache duration by key
     *
     * @param string $key Duration key (hour, day, week, month)
     * @return int Cache duration in seconds
     */
    public static function get_cache_duration(string $key): int {
        $durations = [
            'hour' => self::CACHE_DURATION_HOUR,
            'day' => self::CACHE_DURATION_DAY,
            'week' => self::CACHE_DURATION_WEEK,
            'month' => self::CACHE_DURATION_MONTH,
        ];

        return $durations[$key] ?? self::CACHE_DURATION_HOUR;
    }
}
