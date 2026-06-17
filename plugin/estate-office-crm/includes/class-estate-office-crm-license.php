<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_License
{
    public const DEFAULT_PRODUCT_SLUG = 'estate-office-crm';
    public const DEFAULT_SERVER_URL = 'https://estateofficecrm.pl';
    public const OPTION_LICENSE_KEY = 'eocrm_license_key';

    /** @var EOLM_Client|null */
    private static $client = null;

    public function register_hooks(): void
    {
        $client = $this->boot_client();
        if ($client instanceof EOLM_Client) {
            add_action('admin_init', [$client, 'handle_license_form']);
            add_action('admin_notices', [$client, 'maybe_show_read_only_notice']);
            add_filter('pre_set_site_transient_update_plugins', [$client, 'inject_update_data']);
        }

        // Globalna blokada wszystkich zapisow CRM w trybie read-only.
        add_action('init', [$this, 'guard_write_requests'], 0);
    }

    public static function can_modify(): bool
    {
        if (self::$client instanceof EOLM_Client) {
            return (bool) self::$client->can_modify();
        }

        return false;
    }

    public static function is_read_only(): bool
    {
        return ! self::can_modify();
    }

    public static function read_only_message(): string
    {
        return 'Licencja wygasla lub jest nieaktywna. Dostepny jest tylko podglad (odczyt).';
    }

    public static function render_admin_page(): void
    {
        if (! current_user_can('eocrm_manage_license') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien.');
        }

        if (self::$client instanceof EOLM_Client) {
            self::$client->render_license_page();
            echo '<div class="notice notice-info inline"><p>';
            echo 'Serwer licencji: <code>' . esc_html(self::resolve_server_url()) . '</code> | ';
            echo 'Product slug: <code>' . esc_html(self::resolve_product_slug()) . '</code>';
            echo '</p></div>';
            return;
        }

        echo '<div class="wrap eocrm-admin-wrap">';
        echo '<h1>Licencja - Estate Office CRM</h1>';
        echo '<div class="eocrm-admin-panel">';
        echo '<p>Nie udalo sie uruchomic klienta licencji.</p>';
        echo '<p>Sprawdz plik SDK oraz konfiguracje serwera licencji.</p>';
        echo '</div>';
        echo '</div>';
    }

    public function guard_write_requests(): void
    {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if ($this->is_wordpress_media_request()) {
            return;
        }

        if ($this->is_license_form_submission()) {
            return;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action === '' || ! in_array($action, self::protected_actions(), true)) {
            return;
        }

        if (self::can_modify()) {
            return;
        }

        $message = self::read_only_message();

        if (is_admin()) {
            wp_die(esc_html($message), '', ['response' => 403]);
        }

        $target = wp_get_referer();
        if (! is_string($target) || $target === '') {
            $target = home_url('/crm/');
        }

        wp_safe_redirect(add_query_arg([
            'crm_notice' => 'license_read_only',
            'crm_message' => $message,
        ], $target));
        exit;
    }

    /**
     * @return EOLM_Client|null
     */
    private function boot_client()
    {
        if (self::$client instanceof EOLM_Client) {
            return self::$client;
        }

        if (! class_exists('EOLM_Client')) {
            return null;
        }

        $server_url = self::resolve_server_url();
        $product_slug = self::resolve_product_slug();

        if ($server_url === '' || $product_slug === '') {
            return null;
        }

        self::$client = new EOLM_Client([
            'license_server_url' => $server_url,
            'product_slug' => $product_slug,
            'plugin_file' => EOCRM_FILE,
            'current_version' => EOCRM_VERSION,
            'menu_title' => 'Licencja Estate Office CRM',
            'settings_slug' => EstateOfficeCRM_Admin::LICENSE_SLUG,
            'option_key' => self::OPTION_LICENSE_KEY,
            'protected_pages' => [
                EstateOfficeCRM_Admin::MENU_SLUG,
                EstateOfficeCRM_Admin::AGENTS_SLUG,
                EstateOfficeCRM_Admin::SETTINGS_SLUG,
                EstateOfficeCRM_Admin::ABOUT_SLUG,
                EstateOfficeCRM_Admin::LICENSE_SLUG,
            ],
            'protected_actions' => self::protected_actions(),
        ]);

        // Dostep globalny dla zgodnosci z dokumentacja SDK.
        $GLOBALS['eocrm_license_client'] = self::$client;

        return self::$client;
    }

    private function is_license_form_submission(): bool
    {
        $action = isset($_POST['eolm_client_action']) ? sanitize_key((string) wp_unslash($_POST['eolm_client_action'])) : '';
        return in_array($action, ['save_license_key', 'unregister_domain'], true);
    }

    private function is_wordpress_media_request(): bool
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
        $script = strtolower($script);

        if ($script !== '' && (str_contains($script, '/wp-admin/upload.php') || str_contains($script, '/wp-admin/media-new.php') || str_contains($script, '/wp-admin/media.php') || str_contains($script, '/wp-admin/async-upload.php'))) {
            return true;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : '';
        if ($action === '') {
            return false;
        }

        $media_actions = [
            'query-attachments',
            'upload-attachment',
            'save-attachment',
            'save-attachment-compat',
            'delete-post',
            'send-attachment-to-editor',
        ];

        return in_array($action, $media_actions, true);
    }

    /**
     * @return string[]
     */
    private static function protected_actions(): array
    {
        return [
            // Frontend CRM.
            'create_client',
            'update_client',
            'delete_client',
            'create_agreement',
            'update_agreement',
            'update_agreement_stage',
            'delete_agreement',
            'create_property',
            'update_property',
            'update_property_flags',
            'delete_property',
            'create_search',
            'update_search',
            'delete_search',
            'create_transaction',
            'update_transaction',
            'delete_transaction',
            // Backend admin CRM.
            'save_settings',
            'create_agent',
            'update_agent',
            'create_office',
            'update_office',
        ];
    }

    private static function resolve_server_url(): string
    {
        $default = self::DEFAULT_SERVER_URL;
        if (defined('EOCRM_LICENSE_SERVER_URL') && is_string(constant('EOCRM_LICENSE_SERVER_URL'))) {
            $default = (string) constant('EOCRM_LICENSE_SERVER_URL');
        }

        $value = apply_filters('eocrm_license_server_url', $default);
        $value = is_string($value) ? trim($value) : '';

        return untrailingslashit($value);
    }

    private static function resolve_product_slug(): string
    {
        $default = self::DEFAULT_PRODUCT_SLUG;
        if (defined('EOCRM_LICENSE_PRODUCT_SLUG') && is_string(constant('EOCRM_LICENSE_PRODUCT_SLUG'))) {
            $default = (string) constant('EOCRM_LICENSE_PRODUCT_SLUG');
        }

        $value = apply_filters('eocrm_license_product_slug', $default);
        return sanitize_title((string) $value);
    }
}
