<?php
/**
 * Client SDK for plugin-side license validation and updates.
 * License: GPL-2.0-or-later
 *
 * Usage:
 * require_once __DIR__ . '/sdk/class-eolm-client.php';
 * $license_client = new EOLM_Client(array(
 *     'license_server_url' => 'https://twoja-domena.pl',
 *     'product_slug'       => 'estate-office-crm',
 *     'plugin_file'        => __FILE__,
 *     'current_version'    => '1.0.0',
 *     'menu_title'         => 'Licencja CRM',
 *     'protected_pages'    => array('estate-office-crm'),
 * ));
 * $license_client->boot();
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('EOLM_Client')) {
    class EOLM_Client
    {
        private $config = array();

        public function __construct($config)
        {
            $defaults = array(
                'license_server_url' => '',
                'product_slug'       => '',
                'plugin_file'        => '',
                'current_version'    => '',
                'menu_title'         => 'Licencja',
                'settings_slug'      => '',
                'option_key'         => '',
                'cache_ttl'          => 6 * HOUR_IN_SECONDS,
                'protected_pages'    => array(),
                'protected_actions'  => array(),
            );

            $this->config = wp_parse_args($config, $defaults);

            $this->config['license_server_url'] = untrailingslashit((string) $this->config['license_server_url']);
            $this->config['product_slug']       = sanitize_title((string) $this->config['product_slug']);
            $this->config['plugin_file']        = (string) $this->config['plugin_file'];
            $this->config['current_version']    = (string) $this->config['current_version'];
            $this->config['menu_title']         = sanitize_text_field((string) $this->config['menu_title']);

            if ($this->config['settings_slug'] === '') {
                $this->config['settings_slug'] = $this->config['product_slug'] . '-license';
            }

            if ($this->config['option_key'] === '') {
                $this->config['option_key'] = $this->config['product_slug'] . '_license_key';
            }
        }

        public function boot()
        {
            if ($this->config['license_server_url'] === '' || $this->config['product_slug'] === '') {
                return;
            }

            add_action('admin_menu', array($this, 'register_license_page'));
            add_action('admin_init', array($this, 'handle_license_form'));
            add_action('admin_init', array($this, 'maybe_block_writes_on_expiry'), 50);
            add_action('admin_notices', array($this, 'maybe_show_read_only_notice'));
            add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_update_data'));
        }

        public function can_modify()
        {
            $payload = $this->validate_license();

            return ! empty($payload['license']['can_modify']);
        }

        public function is_read_only()
        {
            return ! $this->can_modify();
        }

        public function assert_can_modify($message = '')
        {
            if ($this->can_modify()) {
                return;
            }

            if ($message === '') {
                $message = __('Licencja wygasla. Edycja jest zablokowana (tylko podglad).', 'estate-office-crm');
            }

            wp_die(esc_html($message), '', array('response' => 403));
        }

        public function register_license_page()
        {
            add_options_page(
                $this->config['menu_title'],
                $this->config['menu_title'],
                'manage_options',
                $this->config['settings_slug'],
                array($this, 'render_license_page')
            );
        }

        public function handle_license_form()
        {
            if (! is_admin() || ! current_user_can('manage_options')) {
                return;
            }

            $action = isset($_POST['eolm_client_action']) ? sanitize_key((string) wp_unslash($_POST['eolm_client_action'])) : '';
            if (! in_array($action, array('save_license_key', 'unregister_domain'), true)) {
                return;
            }

            if ($action === 'unregister_domain') {
                $this->handle_unregister_domain();
                return;
            }

            $posted_nonce = isset($_POST['eolm_client_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['eolm_client_nonce'])) : '';
            if ($posted_nonce === '' || ! wp_verify_nonce($posted_nonce, 'eolm_client_save_license')) {
                return;
            }

            $old_key = $this->get_license_key();
            $new_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

            update_option($this->config['option_key'], $new_key, false);
            delete_transient($this->cache_key());

            if ($old_key !== '' && $old_key !== $new_key) {
                $this->request('deactivate', array('license_key' => $old_key, 'domain' => $this->site_domain()), 'POST');
            }

            if ($new_key !== '') {
                $this->request('activate', array('license_key' => $new_key, 'domain' => $this->site_domain()), 'POST');
            }

            add_settings_error(
                'eolm_client_license',
                'saved',
                __('Klucz licencji zapisany.', 'estate-office-crm'),
                'updated'
            );
        }

        private function handle_unregister_domain()
        {
            $posted_nonce = isset($_POST['eolm_client_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['eolm_client_nonce'])) : '';
            if ($posted_nonce === '' || ! wp_verify_nonce($posted_nonce, 'eolm_client_unregister_domain')) {
                return;
            }

            $license_key = $this->get_license_key();
            $domain = $this->site_domain();
            if ($license_key === '') {
                add_settings_error(
                    'eolm_client_license',
                    'unregister-missing-license',
                    __('Brak zapisanego klucza licencji do wyrejestrowania.', 'estate-office-crm'),
                    'error'
                );

                return;
            }

            $response = $this->request(
                'deactivate',
                array(
                    'license_key' => $license_key,
                    'domain'      => $domain,
                ),
                'POST'
            );

            if (! is_array($response) || empty($response['success'])) {
                $message = __('Nie udalo sie wyrejestrowac domeny. Sprobuj ponownie lub sprawdz polaczenie z serwerem licencji.', 'estate-office-crm');
                if (is_array($response) && ! empty($response['message']) && is_scalar($response['message'])) {
                    $message = sanitize_text_field((string) $response['message']);
                }

                add_settings_error(
                    'eolm_client_license',
                    'unregister-error',
                    $message,
                    'error'
                );

                return;
            }

            update_option($this->config['option_key'], '', false);
            delete_transient($this->cache_key());

            add_settings_error(
                'eolm_client_license',
                'unregistered',
                __('Domena zostala wyrejestrowana, a klucz licencji usuniety z tej instalacji.', 'estate-office-crm'),
                'updated'
            );
        }

        public function render_license_page()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            settings_errors('eolm_client_license');

            $license_key = $this->get_license_key();
            $payload     = $this->validate_license(true);
            $license     = isset($payload['license']) && is_array($payload['license']) ? $payload['license'] : array();

            $status_label = isset($license['status']) ? $license['status'] : 'missing';
            $expires_at   = isset($license['expires_at']) ? $license['expires_at'] : '';
            $domain       = isset($license['activated_domain']) ? $license['activated_domain'] : '';
            $read_only    = ! empty($license['read_only']);
            $error_message = isset($license['error_message']) ? (string) $license['error_message'] : '';

            echo '<div class="wrap eocrm-admin-wrap eocrm-license-page">';
            echo '<section class="eocrm-admin-panel eocrm-license-hero">';
            echo '<div>';
            echo '<span class="eocrm-license-eyebrow">Estate Office CRM</span>';
            echo '<h1>' . esc_html($this->config['menu_title']) . '</h1>';
            echo '<p>Zarzadzaj kluczem licencji, statusem aktywacji i przypisana domena tej instalacji.</p>';
            echo '</div>';
            echo '<span class="eocrm-license-status-pill eocrm-license-status-pill-' . esc_attr(sanitize_html_class((string) $status_label)) . '">' . esc_html($status_label) . '</span>';
            echo '</section>';

            echo '<div class="eocrm-admin-grid eocrm-license-grid">';
            echo '<section class="eocrm-admin-panel eocrm-license-card">';
            echo '<h2>Klucz licencji</h2>';
            echo '<p class="description">Wklej klucz otrzymany po zakupie albo po uruchomieniu Trial.</p>';
            echo '<form method="post" class="eocrm-license-form">';
            wp_nonce_field('eolm_client_save_license', 'eolm_client_nonce');
            echo '<input type="hidden" name="eolm_client_action" value="save_license_key" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="license_key">Klucz licencji</label></th><td><input id="license_key" name="license_key" type="text" class="regular-text" value="' . esc_attr($license_key) . '" /></td></tr>';
            echo '</tbody></table>';
            submit_button(__('Zapisz klucz', 'estate-office-crm'));
            echo '</form>';
            echo '</section>';

            echo '<section class="eocrm-admin-panel eocrm-license-card eocrm-license-status-card">';
            echo '<h2>Status</h2>';
            echo '<dl class="eocrm-license-status-list">';
            echo '<div><dt>Status</dt><dd><strong>' . esc_html($status_label) . '</strong></dd></div>';
            echo '<div><dt>Wygasa</dt><dd>' . esc_html($expires_at !== '' ? $expires_at : '-') . '</dd></div>';
            echo '<div><dt>Aktywna domena</dt><dd>' . esc_html($domain !== '' ? $domain : '-') . '</dd></div>';
            echo '<div><dt>Tryb tylko podgladu</dt><dd>' . ($read_only ? 'Tak' : 'Nie') . '</dd></div>';
            if ($error_message !== '') {
                echo '<div><dt>Blad walidacji</dt><dd>' . esc_html($error_message) . '</dd></div>';
            }
            echo '</dl>';

            if ($license_key !== '' && $domain !== '') {
                echo '<form method="post" class="eocrm-license-unregister-form" onsubmit="return confirm(\'Czy na pewno wyrejestrowac te domene z licencji? Klucz zostanie usuniety z tej instalacji i bedzie mozna aktywowac go na innej domenie.\');">';
                wp_nonce_field('eolm_client_unregister_domain', 'eolm_client_nonce');
                echo '<input type="hidden" name="eolm_client_action" value="unregister_domain" />';
                echo '<button type="submit" class="button eocrm-license-danger-button">Wyrejestruj domen&#281;</button>';
                echo '<p class="description">Po wyrejestrowaniu domena zostanie zwolniona na serwerze licencji, a lokalny klucz zostanie wyczyszczony, zeby ta strona nie aktywowala go ponownie automatycznie.</p>';
                echo '</form>';
            }
            echo '</section>';
            echo '</div>';
            echo '</div>';
        }

        public function maybe_show_read_only_notice()
        {
            if (! is_admin() || ! current_user_can('manage_options')) {
                return;
            }

            if (! $this->is_read_only()) {
                return;
            }

            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Licencja wygasla: tylko podglad. Modyfikacje sa zablokowane.', 'estate-office-crm');
            echo '</p></div>';
        }

        public function maybe_block_writes_on_expiry()
        {
            if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
                return;
            }

            if (! is_admin() || ! current_user_can('manage_options')) {
                return;
            }

            if (! $this->is_read_only()) {
                return;
            }

            if (! $this->is_protected_write_request()) {
                return;
            }

            $message = __('Licencja wygasla. Zmiany sa zablokowane, dostepny jest tylko podglad.', 'estate-office-crm');
            wp_die(esc_html($message), '', array('response' => 403));
        }

        public function inject_update_data($transient)
        {
            if (! is_object($transient) || empty($transient->checked)) {
                return $transient;
            }

            $license_key = $this->get_license_key();
            if ($license_key === '') {
                return $transient;
            }

            $response = $this->request(
                'update',
                array(
                    'license_key'       => $license_key,
                    'domain'            => $this->site_domain(),
                    'product_slug'      => $this->config['product_slug'],
                    'installed_version' => $this->plugin_version(),
                ),
                'GET'
            );

            if (! is_array($response) || empty($response['success']) || empty($response['update_available'])) {
                return $transient;
            }

            $plugin_basename = plugin_basename($this->config['plugin_file']);
            $package_url     = isset($response['package_url']) ? (string) $response['package_url'] : '';
            $new_version     = isset($response['latest_version']) ? (string) $response['latest_version'] : '';

            if ($package_url === '' || $new_version === '') {
                return $transient;
            }

            $transient->response[$plugin_basename] = (object) array(
                'slug'        => $this->config['product_slug'],
                'plugin'      => $plugin_basename,
                'new_version' => $new_version,
                'package'     => $package_url,
                'url'         => isset($response['changelog_url']) ? (string) $response['changelog_url'] : '',
            );

            return $transient;
        }

        public function validate_license($force = false)
        {
            $cached = get_transient($this->cache_key());
            if (! $force && is_array($cached)) {
                return $cached;
            }

            $license_key = $this->get_license_key();
            if ($license_key === '') {
                $fallback = $this->fallback_payload('missing');
                set_transient($this->cache_key(), $fallback, (int) $this->config['cache_ttl']);

                return $fallback;
            }

            $response = $this->request(
                'validate',
                array(
                    'license_key' => $license_key,
                    'domain'      => $this->site_domain(),
                ),
                'GET'
            );

            if (! is_array($response) || empty($response['success']) || empty($response['license'])) {
                $fallback = $this->fallback_payload('invalid');
                if (is_array($response)) {
                    if (! empty($response['code']) && is_scalar($response['code'])) {
                        $fallback['license']['status'] = sanitize_key((string) $response['code']);
                    }
                    if (! empty($response['message']) && is_scalar($response['message'])) {
                        $fallback['license']['error_message'] = sanitize_text_field((string) $response['message']);
                    }
                } else {
                    $fallback['license']['error_message'] = 'Brak odpowiedzi JSON z serwera licencji. Sprawdz URL serwera i dostep do endpointu REST.';
                }
                $failure_ttl = min((int) $this->config['cache_ttl'], 5 * MINUTE_IN_SECONDS);
                if ($failure_ttl < MINUTE_IN_SECONDS) {
                    $failure_ttl = MINUTE_IN_SECONDS;
                }
                set_transient($this->cache_key(), $fallback, $failure_ttl);

                return $fallback;
            }

            set_transient($this->cache_key(), $response, (int) $this->config['cache_ttl']);

            return $response;
        }

        private function get_license_key()
        {
            $license_key = get_option($this->config['option_key'], '');

            return sanitize_text_field((string) $license_key);
        }

        private function request($endpoint, $params, $method = 'GET')
        {
            $endpoint = ltrim((string) $endpoint, '/');

            $pretty_url = $this->config['license_server_url'] . '/wp-json/eolm/v1/' . $endpoint;
            $plain_url  = add_query_arg(
                'rest_route',
                '/eolm/v1/' . $endpoint,
                trailingslashit($this->config['license_server_url'])
            );
            $index_url  = add_query_arg(
                'rest_route',
                '/eolm/v1/' . $endpoint,
                trailingslashit($this->config['license_server_url']) . 'index.php'
            );

            $method = strtoupper((string) $method);

            $candidates = array($pretty_url, $plain_url, $index_url);

            foreach ($candidates as $candidate_url) {
                $json = $this->perform_request_json($candidate_url, $params, $method);
                if (is_array($json)) {
                    return $json;
                }
            }

            return null;
        }

        private function perform_request_json($url, $params, $method)
        {
            $args = array(
                'timeout' => 20,
            );

            if ($method === 'POST') {
                $args['body'] = $params;
                $http         = wp_remote_post($url, $args);
            } else {
                $http = wp_remote_get(add_query_arg($params, $url), $args);
            }

            if (is_wp_error($http)) {
                return null;
            }

            $status = wp_remote_retrieve_response_code($http);
            $body = $this->normalize_json_body(wp_remote_retrieve_body($http));
            $json = json_decode($body, true);
            if ($status < 200 || $status >= 300) {
                return $this->is_license_api_error($json) ? $json : null;
            }

            return is_array($json) ? $json : null;
        }

        private function is_license_api_error($json)
        {
            if (! is_array($json) || empty($json['code']) || ! is_scalar($json['code'])) {
                return false;
            }

            return strpos((string) $json['code'], 'eolm_') === 0;
        }

        private function normalize_json_body($body)
        {
            $body = (string) $body;
            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

            return ltrim($body);
        }

        private function cache_key()
        {
            return 'eolm_client_cache_' . md5($this->config['product_slug'] . '|' . $this->site_domain() . '|' . $this->config['current_version']);
        }

        private function site_domain()
        {
            $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
            if (! is_string($host) || $host === '') {
                return '';
            }

            $host = strtolower($host);
            if (strpos($host, 'www.') === 0) {
                $host = substr($host, 4);
            }

            return $host;
        }

        private function plugin_version()
        {
            if ($this->config['current_version'] !== '') {
                return $this->config['current_version'];
            }

            if (! function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $data = get_plugin_data($this->config['plugin_file'], false, false);

            return isset($data['Version']) ? (string) $data['Version'] : '';
        }

        private function fallback_payload($status)
        {
            return array(
                'success' => false,
                'license' => array(
                    'status'      => $status,
                    'can_modify'  => false,
                    'can_update'  => false,
                    'read_only'   => true,
                    'expires_at'  => '',
                    'error_message' => '',
                ),
            );
        }

        private function is_protected_write_request()
        {
            if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
                return false;
            }

            if ($this->is_wordpress_media_request()) {
                return false;
            }

            $request_method = isset($_SERVER['REQUEST_METHOD'])
                ? strtoupper(sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_METHOD'])))
                : '';
            if ($request_method !== 'POST') {
                return false;
            }

            $requested_page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : '';
            if ($requested_page !== '' && in_array($requested_page, $this->config['protected_pages'], true)) {
                return true;
            }

            $requested_action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
            if ($requested_action !== '' && in_array($requested_action, $this->config['protected_actions'], true)) {
                return true;
            }

            return false;
        }

        private function is_wordpress_media_request()
        {
            $script = isset($_SERVER['SCRIPT_NAME'])
                ? strtolower(sanitize_text_field((string) wp_unslash($_SERVER['SCRIPT_NAME'])))
                : '';
            if ($script !== '') {
                if (strpos($script, '/wp-admin/upload.php') !== false
                    || strpos($script, '/wp-admin/media-new.php') !== false
                    || strpos($script, '/wp-admin/media.php') !== false
                    || strpos($script, '/wp-admin/async-upload.php') !== false) {
                    return true;
                }
            }

            $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
            if ($action === '') {
                return false;
            }

            return in_array(
                $action,
                array(
                    'query-attachments',
                    'upload-attachment',
                    'save-attachment',
                    'save-attachment-compat',
                    'delete-post',
                    'send-attachment-to-editor',
                ),
                true
            );
        }
    }
}
