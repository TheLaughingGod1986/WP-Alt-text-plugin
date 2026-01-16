<?php
/**
 * Auth state helper for BeepBeep AI.
 *
 * Centralizes authentication and stored credential checks used across admin rendering.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class Auth_State {
    /**
    * Resolve current auth/license/stored credential state.
    *
    * @param object $api_client API client instance exposing is_authenticated(), has_active_license(), get_license_key().
    * @return array{is_authenticated:bool, has_license:bool, has_stored_token:bool, has_stored_license:bool, has_registered_user:bool}
    */
    public static function resolve($api_client): array {
        $is_authenticated = false;
        $has_license = false;

        try {
            $is_authenticated = $api_client->is_authenticated();
            $has_license = $api_client->has_active_license();
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BBAI] Auth_State auth check failed: ' . $e->getMessage());
            }
            $is_authenticated = false;
            $has_license = false;
        } catch (\Error $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BBAI] Auth_State auth check fatal error: ' . $e->getMessage());
            }
            $is_authenticated = false;
            $has_license = false;
        }

        $stored_token = get_option('beepbeepai_jwt_token', '');
        $has_stored_token = !empty($stored_token);

        $stored_license = '';
        try {
            $stored_license = $api_client->get_license_key();
        } catch (\Exception $e) {
            $stored_license = '';
        } catch (\Error $e) {
            $stored_license = '';
        }
        $has_stored_license = !empty($stored_license);

        // If we have a stored license, treat it as active for gating UI.
        if (!$has_license && $has_stored_license) {
            $has_license = true;
        }

        // Consider registered only if WP user is logged in AND some credential exists.
        $has_registered_user = is_user_logged_in() && ($is_authenticated || $has_license || $has_stored_token || $has_stored_license);

        return [
            'is_authenticated'   => $is_authenticated,
            'has_license'        => $has_license,
            'has_stored_token'   => $has_stored_token,
            'has_stored_license' => $has_stored_license,
            'has_registered_user'=> $has_registered_user,
        ];
    }
}
