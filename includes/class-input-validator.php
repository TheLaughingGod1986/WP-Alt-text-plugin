<?php
/**
 * Input Validator Class
 * Centralized input validation and sanitization
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

class Input_Validator {

    /**
     * Validate and sanitize integer from REST request
     *
     * @param \WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @param int $default Default value
     * @param int|null $min Minimum value (null = no minimum)
     * @param int|null $max Maximum value (null = no maximum)
     * @return int Validated integer
     */
    public static function int_param(\WP_REST_Request $request, $param, $default = 0, $min = null, $max = null) {
        $value = $request->get_param($param);
        $value = $value !== null ? absint($value) : $default;

        if ($min !== null) {
            $value = max($min, $value);
        }
        if ($max !== null) {
            $value = min($max, $value);
        }

        return $value;
    }

    /**
     * Validate and sanitize string from REST request
     *
     * @param \WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @param string $default Default value
     * @param array $allowed Allowed values (empty = any)
     * @return string Validated string
     */
    public static function string_param(\WP_REST_Request $request, $param, $default = '', $allowed = []) {
        $value = $request->get_param($param);

        if ($value === null || !is_string($value)) {
            return $default;
        }

        $value = sanitize_text_field($value);

        if (!empty($allowed) && !in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }

    /**
     * Validate and sanitize key/slug from REST request
     *
     * @param \WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @param string $default Default value
     * @param array $allowed Allowed values (empty = any)
     * @return string Validated key
     */
    public static function key_param(\WP_REST_Request $request, $param, $default = '', $allowed = []) {
        $value = $request->get_param($param);

        if ($value === null || !is_string($value)) {
            return $default;
        }

        $value = sanitize_key($value);

        if (!empty($allowed) && !in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }

    /**
     * Validate boolean from REST request
     *
     * @param \WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @param bool $default Default value
     * @return bool Validated boolean
     */
    public static function bool_param(\WP_REST_Request $request, $param, $default = false) {
        $value = $request->get_param($param);

        if ($value === null) {
            return $default;
        }

        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }

    /**
     * Validate array from REST request
     *
     * @param \WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @param array $default Default value
     * @return array Validated array
     */
    public static function array_param(\WP_REST_Request $request, $param, $default = []) {
        $value = $request->get_param($param);

        if (!is_array($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Validate attachment ID
     *
     * @param int $id Attachment ID
     * @return int|false Validated ID or false if invalid
     */
    public static function attachment_id($id) {
        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        if (!wp_attachment_is_image($id)) {
            return false;
        }

        return $id;
    }

    /**
     * Validate pagination parameters
     *
     * @param \WP_REST_Request $request Request object
     * @param int $default_per_page Default per page
     * @param int $max_per_page Maximum per page
     * @return array ['page' => int, 'per_page' => int, 'offset' => int]
     */
    public static function pagination(\WP_REST_Request $request, $default_per_page = 50, $max_per_page = 100) {
        $page = self::int_param($request, 'page', 1, 1);
        $per_page = self::int_param($request, 'per_page', $default_per_page, 1, $max_per_page);
        $offset = self::int_param($request, 'offset', 0, 0);

        // If offset not explicitly set, calculate from page
        if ($request->get_param('offset') === null && $page > 1) {
            $offset = ($page - 1) * $per_page;
        }

        return [
            'page' => $page,
            'per_page' => $per_page,
            'offset' => $offset,
        ];
    }

    /**
     * Sanitize log level
     *
     * @param string $level Log level
     * @return string Validated log level
     */
    public static function log_level($level) {
        $allowed = ['debug', 'info', 'warning', 'error'];
        $level = sanitize_key($level);

        return in_array($level, $allowed, true) ? $level : 'info';
    }

    /**
     * Sanitize period parameter
     *
     * @param string $period Period string
     * @return string Validated period
     */
    public static function period($period) {
        $allowed = ['day', 'week', 'month', 'year', 'all'];
        $period = sanitize_key($period);

        return in_array($period, $allowed, true) ? $period : 'month';
    }

    /**
     * Sanitize scope parameter
     *
     * @param string $scope Scope string
     * @return string Validated scope
     */
    public static function scope($scope) {
        $allowed = ['missing', 'all', 'generated', 'manual', 'reviewed'];
        $scope = sanitize_key($scope);

        return in_array($scope, $allowed, true) ? $scope : 'missing';
    }
}
