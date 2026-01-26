<?php
/**
 * Onboarding State Management
 * Tracks user onboarding completion and preferences
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class Onboarding {
    const META_KEY_COMPLETED = 'bbai_onboarding_completed';
    const META_KEY_MILESTONES = 'bbai_milestones';
    const META_KEY_PREFERENCES = 'bbai_preferences';
    const META_KEY_LAST_SEEN = 'bbai_last_seen';

    /**
     * Check if user has completed onboarding
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return bool
     */
    public static function is_completed($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return (bool) get_user_meta($user_id, self::META_KEY_COMPLETED, true);
    }

    /**
     * Mark onboarding as completed
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return bool
     */
    public static function mark_completed($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return update_user_meta($user_id, self::META_KEY_COMPLETED, true);
    }

    /**
     * Reset onboarding (for testing or re-showing)
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return bool
     */
    public static function reset($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return delete_user_meta($user_id, self::META_KEY_COMPLETED);
    }

    /**
     * Get user milestones
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return array
     */
    public static function get_milestones($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        $milestones = get_user_meta($user_id, self::META_KEY_MILESTONES, true);
        return is_array($milestones) ? $milestones : [];
    }

    /**
     * Add milestone
     *
     * @param string $milestone Milestone key (e.g., 'first_generation', '100_images')
     * @param int|null $user_id User ID, defaults to current user
     * @return bool
     */
    public static function add_milestone($milestone, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $milestones = self::get_milestones($user_id);
        if (!in_array($milestone, $milestones, true)) {
            $milestones[] = $milestone;
            return update_user_meta($user_id, self::META_KEY_MILESTONES, $milestones);
        }

        return true;
    }

    /**
     * Get user preferences
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return array
     */
    public static function get_preferences($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        $prefs = get_user_meta($user_id, self::META_KEY_PREFERENCES, true);
        return is_array($prefs) ? $prefs : [];
    }

    /**
     * Update user preference
     *
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @param int|null $user_id User ID, defaults to current user
     * @return bool
     */
    public static function set_preference($key, $value, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $prefs = self::get_preferences($user_id);
        $prefs[$key] = $value;
        return update_user_meta($user_id, self::META_KEY_PREFERENCES, $prefs);
    }

    /**
     * Update last seen timestamp
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return bool
     */
    public static function update_last_seen($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return update_user_meta($user_id, self::META_KEY_LAST_SEEN, current_time('mysql'));
    }
}
