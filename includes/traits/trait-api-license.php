<?php
/**
 * API License Trait
 * Handles license activation and management
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Api_License {

    /**
     * Activate a license key
     */
    public function activate_license($license_key) {
        $site_fingerprint = $this->get_site_fingerprint();

        $response = $this->make_request('/license/activate', 'POST', [
            'license_key' => $license_key,
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
            'site_fingerprint' => $site_fingerprint,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $this->set_license_key($license_key);

        if (isset($response['organization']) || isset($response['site'])) {
            $this->set_license_data([
                'organization' => $response['organization'] ?? null,
                'site' => $response['site'] ?? null,
            ]);
        }

        return $response;
    }

    /**
     * Deactivate current license
     */
    public function deactivate_license() {
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            return new \WP_Error('no_license', __('No license key to deactivate', 'beepbeep-ai-alt-text-generator'));
        }

        $site_fingerprint = $this->get_site_fingerprint();

        $response = $this->make_request('/license/deactivate', 'POST', [
            'license_key' => $license_key,
            'site_fingerprint' => $site_fingerprint,
        ]);

        $this->clear_license_key();

        return is_wp_error($response) ? $response : ['success' => true];
    }

    /**
     * Get sites using this license
     */
    public function get_license_sites() {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Authentication required', 'beepbeep-ai-alt-text-generator'));
        }

        $response = $this->make_request('/license/sites', 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Disconnect a site from license
     */
    public function disconnect_license_site($site_id) {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Authentication required', 'beepbeep-ai-alt-text-generator'));
        }

        $response = $this->make_request('/license/sites/' . $site_id, 'DELETE');

        return $response;
    }
}
