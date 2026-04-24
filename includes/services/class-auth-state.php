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
    * @return array{
    *   is_authenticated:bool,
    *   has_license:bool,
    *   has_stored_token:bool,
    *   has_stored_license:bool,
    *   has_registered_user:bool,
    *   has_connected_account:bool,
    *   is_anonymous_trial:bool,
    *   auth_state:string
    * }
    */
    public static function resolve($api_client): array {
        $is_authenticated = false;
        $has_license = false;

        try {
            $is_authenticated = $api_client->is_authenticated();
            $has_license = $api_client->has_active_license();
		} catch (\Exception $e) {
			\bbai_debug_log(
				'Auth_State authentication check failed',
				[
					'error' => $e->getMessage(),
				]
			);
            $is_authenticated = false;
            $has_license = false;
		} catch (\Error $e) {
			\bbai_debug_log(
				'Auth_State authentication fatal error',
				[
					'error' => $e->getMessage(),
				]
			);
            $is_authenticated = false;
            $has_license = false;
        }

        $stored_token = get_option('beepbeepai_jwt_token', '');
        $legacy_token = get_option('opptibbai_jwt_token', '');
        $has_stored_token = !empty($stored_token) || !empty($legacy_token);

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

        $has_connected_account = $is_authenticated || $has_license || $has_stored_token || $has_stored_license;

        $dashboard_truth_fixture = get_option('bbai_e2e_dashboard_state_truth_fixture', null);
        if (is_string($dashboard_truth_fixture) && '' !== trim($dashboard_truth_fixture)) {
            $dashboard_truth_fixture = json_decode($dashboard_truth_fixture, true);
        }
        if (
            is_array($dashboard_truth_fixture)
            && !empty($dashboard_truth_fixture['site'])
            && is_array($dashboard_truth_fixture['site'])
            && !empty($dashboard_truth_fixture['site']['has_connected_account'])
        ) {
            $has_connected_account = true;
        }

        $is_anonymous_trial = !$has_connected_account;

        // Keep the historical meaning of "registered user" for wp-admin rendering.
        $has_registered_user = is_user_logged_in() && $has_connected_account;

        return [
            'is_authenticated'   => $is_authenticated,
            'has_license'        => $has_license,
            'has_stored_token'   => $has_stored_token,
            'has_stored_license' => $has_stored_license,
            'has_registered_user'=> $has_registered_user,
            'has_connected_account' => $has_connected_account,
            'is_anonymous_trial' => $is_anonymous_trial,
            'auth_state' => $is_anonymous_trial ? 'anonymous' : 'authenticated',
        ];
    }
}
