<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Portal_Export
{
    public const TARGET_NOE = 'nieruchomosci_online';
    public const TARGET_MORIZON_GRATKA = 'morizon_gratka';
    public const TARGET_OTODOM_OLX = 'otodom_olx';
    public const CRON_HOOK = 'eocrm_portal_export_queue';

    private const MAX_ATTEMPTS = 5;
    private const MAX_MEDIA_ITEMS_PER_TYPE = 15;
    private const MAX_MEDIA_FILE_BYTES = 3000000;

    /** @var array<string, string> */
    private array $tables;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
    }

    public function register_hooks(): void
    {
        add_action(self::CRON_HOOK, [$this, 'process_pending_queue']);
        add_action('init', [$this, 'maybe_process_queue_on_request'], 30);
        add_action('init', [$this, 'handle_otodom_olx_auth_callback'], 1);
        add_action('init', [$this, 'handle_otodom_olx_notification_callback'], 1);
    }

    /**
     * @return array<string, string>
     */
    public static function default_settings(): array
    {
        return [
            'portal_export_provider' => self::TARGET_NOE,
            'portal_noe_enabled' => '0',
            'portal_noe_ftp_host' => 'ftp.nieruchomosci-online.pl',
            'portal_noe_ftp_port' => '21',
            'portal_noe_ftp_login' => '',
            'portal_noe_ftp_password' => '',
            'portal_noe_ftp_path' => '',
            'portal_noe_export_mode' => 'incremental',
            'portal_noe_region_id' => '7',
            'portal_noe_region_name' => 'mazowieckie',
            'portal_noe_software_name' => 'Estate Office CRM',
            'portal_noe_use_ssl' => '0',
            'portal_noe_ftp_timeout' => '20',
            'portal_noe_default_agent_phone' => '',
            'portal_noe_default_agent_email' => '',
            'portal_mg_enabled' => '0',
            'portal_mg_ftp_host' => '',
            'portal_mg_ftp_port' => '21',
            'portal_mg_ftp_login' => '',
            'portal_mg_ftp_password' => '',
            'portal_mg_ftp_path' => '',
            'portal_mg_export_mode' => 'incremental',
            'portal_mg_region_name' => 'mazowieckie',
            'portal_mg_agency_name' => '',
            'portal_mg_information' => 'Eksport ofert z Estate Office CRM',
            'portal_mg_use_ssl' => '0',
            'portal_mg_ftp_timeout' => '20',
            'portal_mg_default_agent_phone' => '',
            'portal_mg_default_agent_email' => '',
            'portal_oo_enabled' => '0',
            'portal_oo_client_id' => '',
            'portal_oo_client_secret' => '',
            'portal_oo_api_key' => '',
            'portal_oo_partner_urn' => '',
            'portal_oo_notification_secret' => '',
            'portal_oo_site_urn' => 'urn:site:otodompl',
            'portal_oo_olx_site_urn' => 'urn:site:olxpl',
            'portal_oo_enable_olx' => '1',
            'portal_oo_test_account_mode' => '0',
            'portal_oo_auth_host' => 'https://www.otodom.pl',
            'portal_oo_auth_locale' => 'pl',
            'portal_oo_user_agent' => 'Estate Office CRM',
            'portal_oo_access_token' => '',
            'portal_oo_refresh_token' => '',
            'portal_oo_token_expires_at' => '',
            'portal_oo_scope' => '',
            'portal_oo_authorized_at' => '',
            'portal_oo_auth_state' => '',
            'portal_oo_last_auth_code' => '',
            'portal_oo_last_auth_message' => '',
            'portal_oo_last_webhook_at' => '',
            'portal_oo_last_webhook_event' => '',
            'portal_oo_last_webhook_message' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function portal_options(): array
    {
        return [
            self::TARGET_NOE => 'Nieruchomosci Online',
            self::TARGET_MORIZON_GRATKA => 'Morizon-Gratka',
            self::TARGET_OTODOM_OLX => 'OtoDom + OLX.pl (OLX Group API)',
        ];
    }

    public static function provider_label(string $provider): string
    {
        $options = self::portal_options();
        return isset($options[$provider]) ? (string) $options[$provider] : $provider;
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<string, mixed>
     */
    public static function load_settings(callable $get_setting): array
    {
        $defaults = self::default_settings();

        $provider = self::sanitize_provider((string) $get_setting('portal_export_provider'));
        if ($provider === '') {
            $provider = (string) $defaults['portal_export_provider'];
        }
        if ($provider === self::TARGET_OTODOM_OLX && (string) $get_setting('developer_access_enabled') !== '1') {
            $provider = self::TARGET_NOE;
        }

        return self::load_settings_for_provider($get_setting, $provider);
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<string, mixed>
     */
    public static function load_settings_for_provider(callable $get_setting, string $provider): array
    {
        $defaults = self::default_settings();
        $provider_key = self::sanitize_provider($provider);
        if ($provider_key === '') {
            $provider_key = (string) $defaults['portal_export_provider'];
        }

        if ($provider_key === self::TARGET_OTODOM_OLX) {
            $enabled = self::sanitize_yes_no((string) $get_setting('portal_oo_enabled'));
            $client_id = sanitize_text_field((string) $get_setting('portal_oo_client_id'));
            $client_secret = trim((string) $get_setting('portal_oo_client_secret'));
            $api_key = sanitize_text_field((string) $get_setting('portal_oo_api_key'));
            $partner_urn = self::sanitize_generic_urn((string) $get_setting('portal_oo_partner_urn'));
            $notification_secret = trim((string) $get_setting('portal_oo_notification_secret'));
            $site_urn = self::sanitize_site_urn((string) $get_setting('portal_oo_site_urn'));
            if ($site_urn === '') {
                $site_urn = (string) $defaults['portal_oo_site_urn'];
            }
            $olx_site_urn = self::sanitize_site_urn((string) $get_setting('portal_oo_olx_site_urn'));
            if ($olx_site_urn === '') {
                $olx_site_urn = (string) $defaults['portal_oo_olx_site_urn'];
            }
            $enable_olx = self::sanitize_yes_no((string) $get_setting('portal_oo_enable_olx'));
            $test_account_mode = self::sanitize_yes_no((string) $get_setting('portal_oo_test_account_mode'));
            $auth_host = esc_url_raw((string) $get_setting('portal_oo_auth_host'));
            if (! is_string($auth_host) || $auth_host === '') {
                $auth_host = (string) $defaults['portal_oo_auth_host'];
            }
            $auth_locale = self::sanitize_auth_locale((string) $get_setting('portal_oo_auth_locale'));
            if ($auth_locale === '') {
                $auth_locale = (string) $defaults['portal_oo_auth_locale'];
            }
            $user_agent = sanitize_text_field((string) $get_setting('portal_oo_user_agent'));
            if ($user_agent === '') {
                $user_agent = (string) $defaults['portal_oo_user_agent'];
            }
            $access_token = trim((string) $get_setting('portal_oo_access_token'));
            $refresh_token = trim((string) $get_setting('portal_oo_refresh_token'));
            $token_expires_at = sanitize_text_field((string) $get_setting('portal_oo_token_expires_at'));
            $scope = sanitize_text_field((string) $get_setting('portal_oo_scope'));
            $authorized_at = sanitize_text_field((string) $get_setting('portal_oo_authorized_at'));
            $auth_state = sanitize_text_field((string) $get_setting('portal_oo_auth_state'));
            if ($auth_state === '') {
                $auth_state = wp_generate_uuid4();
            }
            $last_auth_code = sanitize_text_field((string) $get_setting('portal_oo_last_auth_code'));
            $last_auth_message = sanitize_text_field((string) $get_setting('portal_oo_last_auth_message'));
            $last_webhook_at = sanitize_text_field((string) $get_setting('portal_oo_last_webhook_at'));
            $last_webhook_event = sanitize_text_field((string) $get_setting('portal_oo_last_webhook_event'));
            $last_webhook_message = sanitize_text_field((string) $get_setting('portal_oo_last_webhook_message'));

            return [
                'provider' => self::TARGET_OTODOM_OLX,
                'enabled' => $enabled,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'api_key' => $api_key,
                'partner_urn' => $partner_urn,
                'notification_secret' => $notification_secret,
                'site_urn' => $site_urn,
                'olx_site_urn' => $olx_site_urn,
                'enable_olx' => $enable_olx,
                'test_account_mode' => $test_account_mode,
                'auth_host' => $auth_host,
                'auth_locale' => $auth_locale,
                'auth_callback_url' => self::otodom_auth_callback_url(),
                'notification_callback_url' => self::otodom_notification_callback_url(),
                'auth_state' => $auth_state,
                'user_agent' => $user_agent,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'token_expires_at' => $token_expires_at,
                'scope' => $scope,
                'authorized_at' => $authorized_at,
                'last_auth_code' => $last_auth_code,
                'last_auth_message' => $last_auth_message,
                'last_webhook_at' => $last_webhook_at,
                'last_webhook_event' => $last_webhook_event,
                'last_webhook_message' => $last_webhook_message,
            ];
        }

        if ($provider_key === self::TARGET_MORIZON_GRATKA) {
            $enabled = self::sanitize_yes_no((string) $get_setting('portal_mg_enabled'));
            $use_ssl = self::sanitize_yes_no((string) $get_setting('portal_mg_use_ssl'));
            $ftp_host = trim((string) $get_setting('portal_mg_ftp_host'));
            $ftp_login = trim((string) $get_setting('portal_mg_ftp_login'));
            $ftp_password = (string) $get_setting('portal_mg_ftp_password');
            $ftp_path = self::sanitize_ftp_path((string) $get_setting('portal_mg_ftp_path'));
            $ftp_port = self::sanitize_port((string) $get_setting('portal_mg_ftp_port'));
            $export_mode = self::sanitize_export_mode((string) $get_setting('portal_mg_export_mode'));
            $timeout = self::sanitize_timeout((string) $get_setting('portal_mg_ftp_timeout'));
            $default_agent_phone = sanitize_text_field((string) $get_setting('portal_mg_default_agent_phone'));
            $default_agent_email = sanitize_email((string) $get_setting('portal_mg_default_agent_email'));
            $region_name = sanitize_text_field((string) $get_setting('portal_mg_region_name'));
            if ($region_name === '') {
                $region_name = (string) $defaults['portal_mg_region_name'];
            }
            $agency_name = sanitize_text_field((string) $get_setting('portal_mg_agency_name'));
            if ($agency_name === '') {
                $agency_name = sanitize_text_field((string) get_bloginfo('name'));
            }
            $information = sanitize_text_field((string) $get_setting('portal_mg_information'));
            if ($information === '') {
                $information = (string) $defaults['portal_mg_information'];
            }

            return [
                'provider' => self::TARGET_MORIZON_GRATKA,
                'enabled' => $enabled,
                'ftp_host' => $ftp_host,
                'ftp_port' => $ftp_port,
                'ftp_login' => $ftp_login,
                'ftp_password' => $ftp_password,
                'ftp_path' => $ftp_path,
                'export_mode' => $export_mode,
                'region_id' => 7,
                'region_name' => $region_name,
                'software_name' => 'Estate Office CRM',
                'use_ssl' => $use_ssl,
                'ftp_timeout' => $timeout,
                'default_agent_phone' => $default_agent_phone,
                'default_agent_email' => $default_agent_email,
                'agency_name' => $agency_name,
                'information' => $information,
            ];
        }

        $enabled = self::sanitize_yes_no((string) $get_setting('portal_noe_enabled'));
        $use_ssl = self::sanitize_yes_no((string) $get_setting('portal_noe_use_ssl'));
        $ftp_host = trim((string) $get_setting('portal_noe_ftp_host'));
        $ftp_login = trim((string) $get_setting('portal_noe_ftp_login'));
        $ftp_password = (string) $get_setting('portal_noe_ftp_password');
        $ftp_path = self::sanitize_ftp_path((string) $get_setting('portal_noe_ftp_path'));
        $export_mode = self::sanitize_export_mode((string) $get_setting('portal_noe_export_mode'));
        $region_id = self::sanitize_region_id((string) $get_setting('portal_noe_region_id'));
        $region_name = sanitize_text_field((string) $get_setting('portal_noe_region_name'));
        $software_name = sanitize_text_field((string) $get_setting('portal_noe_software_name'));
        $timeout = self::sanitize_timeout((string) $get_setting('portal_noe_ftp_timeout'));
        $default_agent_phone = sanitize_text_field((string) $get_setting('portal_noe_default_agent_phone'));
        $default_agent_email = sanitize_email((string) $get_setting('portal_noe_default_agent_email'));

        if ($ftp_host === '') {
            $ftp_host = (string) $defaults['portal_noe_ftp_host'];
        }
        $ftp_port = self::sanitize_port((string) $get_setting('portal_noe_ftp_port'));

        if ($region_name === '') {
            $region_name = self::region_name_from_id($region_id);
        }
        if ($software_name === '') {
            $software_name = (string) $defaults['portal_noe_software_name'];
        }

        return [
            'provider' => self::TARGET_NOE,
            'enabled' => $enabled,
            'ftp_host' => $ftp_host,
            'ftp_port' => $ftp_port,
            'ftp_login' => $ftp_login,
            'ftp_password' => $ftp_password,
            'ftp_path' => $ftp_path,
            'export_mode' => $export_mode,
            'region_id' => $region_id,
            'region_name' => $region_name,
            'software_name' => $software_name,
            'use_ssl' => $use_ssl,
            'ftp_timeout' => $timeout,
            'default_agent_phone' => $default_agent_phone,
            'default_agent_email' => $default_agent_email,
            'agency_name' => '',
            'information' => '',
        ];
    }

    public static function sanitize_provider(string $provider): string
    {
        $value = sanitize_key($provider);
        return in_array($value, array_keys(self::portal_options()), true) ? $value : '';
    }

    public static function sanitize_site_urn(string $value): string
    {
        $urn = strtolower(trim($value));
        if ($urn === '') {
            return '';
        }
        if (! preg_match('/^urn:site:[a-z0-9._-]+$/', $urn)) {
            return '';
        }

        return $urn;
    }

    public static function sanitize_generic_urn(string $value): string
    {
        $urn = strtolower(trim($value));
        if ($urn === '') {
            return '';
        }
        if (! preg_match('/^urn(?::[a-z0-9._-]+){2,}$/', $urn)) {
            return '';
        }

        return $urn;
    }

    public static function sanitize_auth_locale(string $value): string
    {
        $locale = strtolower(trim($value));
        if (! preg_match('/^[a-z]{2}$/', $locale)) {
            return '';
        }

        return $locale;
    }

    public static function otodom_auth_callback_url(): string
    {
        return add_query_arg(
            [
                'eocrm_portal_auth' => self::TARGET_OTODOM_OLX,
            ],
            home_url('/')
        );
    }

    public static function otodom_notification_callback_url(): string
    {
        return add_query_arg(
            [
                'eocrm_portal_notify' => self::TARGET_OTODOM_OLX,
            ],
            home_url('/')
        );
    }

    public static function otodom_authorization_url(array $settings): string
    {
        $client_id = isset($settings['client_id']) ? sanitize_text_field((string) $settings['client_id']) : '';
        $auth_host = isset($settings['auth_host']) ? esc_url_raw((string) $settings['auth_host']) : '';
        $auth_locale = isset($settings['auth_locale']) ? self::sanitize_auth_locale((string) $settings['auth_locale']) : '';
        $auth_state = isset($settings['auth_state']) ? sanitize_text_field((string) $settings['auth_state']) : '';

        if ($client_id === '' || ! empty(self::get_missing_otodom_credentials($settings))) {
            return '';
        }
        if (! is_string($auth_host) || $auth_host === '') {
            $auth_host = 'https://www.otodom.pl';
        }
        if ($auth_locale === '') {
            $auth_locale = 'pl';
        }
        if ($auth_state === '') {
            $auth_state = wp_generate_uuid4();
        }

        return add_query_arg(
            [
                'response_type' => 'code',
                'client_id' => $client_id,
                'state' => $auth_state,
            ],
            trailingslashit(untrailingslashit($auth_host)) . $auth_locale . '/crm/authorization/'
        );
    }

    public static function sanitize_export_mode(string $value): string
    {
        $mode = strtolower(trim($value));
        return in_array($mode, ['full', 'incremental'], true) ? $mode : 'incremental';
    }

    public static function sanitize_port(string $value): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 21;
        }

        $port = (int) $raw;
        if ($port < 1) {
            $port = 1;
        }
        if ($port > 65535) {
            $port = 65535;
        }

        return $port;
    }

    public static function sanitize_timeout(string $value): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 20;
        }

        $timeout = (int) $raw;
        if ($timeout < 5) {
            $timeout = 5;
        }
        if ($timeout > 120) {
            $timeout = 120;
        }

        return $timeout;
    }

    public static function sanitize_region_id(string $value): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 7;
        }

        $region_id = (int) $raw;
        if ($region_id < 1 || $region_id > 16) {
            return 7;
        }

        return $region_id;
    }

    public static function sanitize_yes_no(string $value): string
    {
        return $value === '1' ? '1' : '0';
    }

    public static function sanitize_ftp_path(string $value): string
    {
        $path = trim(str_replace('\\', '/', $value));
        $path = preg_replace('/\s+/', '', (string) $path);
        if (! is_string($path)) {
            return '';
        }

        if ($path === '' || $path === '/') {
            return '';
        }

        return trim($path, '/');
    }

    /**
     * @return array{success:bool, skipped?:bool, queue_id?:int, message:string}
     */
    public function queue_property(int $property_id, string $event = 'update'): array
    {
        if ($property_id <= 0) {
            return [
                'success' => false,
                'message' => 'Brak poprawnego identyfikatora nieruchomosci do eksportu.',
            ];
        }

        $event = strtolower(trim($event));
        if (! in_array($event, ['insert', 'update', 'delete'], true)) {
            $event = 'update';
        }

        $settings = self::load_settings(function (string $key): string {
            return $this->get_setting($key);
        });

        $provider = (string) ($settings['provider'] ?? '');
        $provider_label = self::provider_label($provider);
        if ($provider === '' || (string) ($settings['enabled'] ?? '0') !== '1') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Eksport na portal jest wylaczony w ustawieniach (' . $provider_label . ').',
            ];
        }

        global $wpdb;
        $table = $this->tables['exports_queue'];
        $now = current_time('mysql');
        $payload = [
            'event' => $event,
            'queued_at' => $now,
        ];
        $payload_json = wp_json_encode($payload);
        if (! is_string($payload_json) || $payload_json === '') {
            $payload_json = '{}';
        }

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                FROM {$table}
                WHERE property_id = %d
                    AND export_target = %s
                ORDER BY id DESC
                LIMIT 1",
                $property_id,
                $provider
            )
        );

        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $table,
                [
                    'payload_json' => $payload_json,
                    'export_status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'updated_at' => $now,
                ],
                ['id' => $existing_id],
                ['%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                return [
                    'success' => false,
                    'message' => 'Nie udalo sie odswiezyc pozycji kolejki eksportu.',
                ];
            }

            $queue_id = $existing_id;
        } else {
            $inserted = $wpdb->insert(
                $table,
                [
                    'property_id' => $property_id,
                    'export_target' => $provider,
                    'payload_json' => $payload_json,
                    'export_status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
            );

            if (! $inserted) {
                return [
                    'success' => false,
                    'message' => 'Nie udalo sie dodac pozycji do kolejki eksportu.',
                ];
            }

            $queue_id = (int) $wpdb->insert_id;
        }

        $next_run = wp_next_scheduled(self::CRON_HOOK);
        if (! is_int($next_run) || $next_run > (time() + 120)) {
            wp_schedule_single_event(time() + 30, self::CRON_HOOK);
        }
        $this->trigger_async_cron();

        return [
            'success' => true,
            'queue_id' => $queue_id,
            'message' => 'Eksport na portal (' . $provider_label . ') zostal zakolejkowany do przetworzenia.',
        ];
    }

    public function process_pending_queue(int $limit = 10): void
    {
        $limit = max(1, min(50, $limit));

        global $wpdb;
        $table = $this->tables['exports_queue'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id
                FROM {$table}
                WHERE export_status IN ('pending', 'error')
                    AND attempts < %d
                ORDER BY updated_at ASC
                LIMIT %d",
                self::MAX_ATTEMPTS,
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($rows) || empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $queue_id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($queue_id <= 0) {
                continue;
            }

            $this->process_queue_item($queue_id);
        }
    }

    public function maybe_process_queue_on_request(): void
    {
        if (wp_doing_cron() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (! is_user_logged_in()) {
            return;
        }

        $post_action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        $is_crm_request = isset($_GET['crm']) || $post_action !== '';
        if (! $is_crm_request) {
            return;
        }

        $lock_key = 'eocrm_portal_export_request_lock';
        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, '1', 20);
        $this->process_pending_queue(1);
    }

    private function process_queue_item(int $queue_id): bool
    {
        if ($queue_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $this->tables['exports_queue'];
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, property_id, payload_json, attempts, export_target
                FROM {$table}
                WHERE id = %d
                LIMIT 1",
                $queue_id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return false;
        }

        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $event = isset($payload['event']) ? sanitize_key((string) $payload['event']) : 'update';
        if (! in_array($event, ['insert', 'update', 'delete'], true)) {
            $event = 'update';
        }

        $target = isset($row['export_target']) ? self::sanitize_provider((string) $row['export_target']) : '';
        $attempts = ((int) ($row['attempts'] ?? 0)) + 1;
        if ($target === self::TARGET_MORIZON_GRATKA) {
            $result = $this->send_to_morizon_gratka((int) ($row['property_id'] ?? 0), $event);
        } elseif ($target === self::TARGET_OTODOM_OLX) {
            if ($this->get_setting('developer_access_enabled') !== '1') {
                $result = [
                    'success' => false,
                    'skipped' => true,
                    'message' => 'Eksport OtoDom/OLX jest dostepny tylko w trybie programisty.',
                ];
            } else {
                $result = $this->send_to_otodom_olx((int) ($row['property_id'] ?? 0), $event);
            }
        } elseif ($target === self::TARGET_NOE) {
            $result = $this->send_to_noe((int) ($row['property_id'] ?? 0), $event);
        } else {
            $result = [
                'success' => false,
                'message' => 'Nieznany target eksportu portalu.',
            ];
        }

        $status = 'error';
        $last_error = (string) ($result['message'] ?? 'Nieznany blad eksportu.');
        if (! empty($result['success'])) {
            $status = 'success';
            $last_error = '';
        } elseif (! empty($result['skipped'])) {
            $status = 'skipped';
        } elseif ($attempts >= self::MAX_ATTEMPTS) {
            $status = 'failed';
        }

        $wpdb->update(
            $table,
            [
                'export_status' => $status,
                'attempts' => $attempts,
                'last_error' => $last_error === '' ? null : $last_error,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $queue_id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        return $status === 'success';
    }

    public function handle_otodom_olx_auth_callback(): void
    {
        $provider = isset($_GET['eocrm_portal_auth']) ? self::sanitize_provider((string) wp_unslash($_GET['eocrm_portal_auth'])) : '';
        if ($provider !== self::TARGET_OTODOM_OLX) {
            return;
        }
        if ($this->get_setting('developer_access_enabled') !== '1') {
            status_header(403);
            nocache_headers();
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>Estate Office CRM - OtoDom/OLX callback</title></head><body>';
            echo '<h1>Estate Office CRM - OtoDom/OLX callback</h1>';
            echo '<p>Integracja OtoDom/OLX jest dostepna tylko w trybie programisty.</p>';
            echo '</body></html>';
            exit;
        }

        $settings = self::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, self::TARGET_OTODOM_OLX);

        $code = isset($_GET['code']) ? sanitize_text_field((string) wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field((string) wp_unslash($_GET['state'])) : '';
        $error = isset($_GET['error']) ? sanitize_text_field((string) wp_unslash($_GET['error'])) : '';
        $error_description = isset($_GET['error_description']) ? sanitize_text_field((string) wp_unslash($_GET['error_description'])) : '';
        $expected_state = (string) ($settings['auth_state'] ?? '');
        $state_is_valid = $state !== '' && ($expected_state === '' || hash_equals($expected_state, $state));

        $message = '';
        $success = false;
        if ($error !== '') {
            $message = 'Autoryzacja OtoDom/OLX zostala odrzucona: ' . $error . ($error_description !== '' ? ' (' . $error_description . ')' : '');
        } elseif ($code === '') {
            $message = 'Brak kodu autoryzacyjnego (parameter code).';
        } elseif (! $state_is_valid) {
            $message = 'Bledny parametr state w callbacku OtoDom/OLX. Dla bezpieczenstwa token nie zostal zapisany.';
        } else {
            $token_result = $this->request_otodom_token($settings, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::otodom_auth_callback_url(),
            ]);
            if (is_wp_error($token_result)) {
                $message = $token_result->get_error_message();
            } else {
                $this->persist_otodom_token_bundle($token_result);
                $success = true;
                $message = 'Autoryzacja OtoDom/OLX zakonczona powodzeniem. Token dostepu zostal zapisany.';
            }
        }

        if ($success) {
            $this->upsert_setting('portal_oo_auth_state', wp_generate_uuid4());
        }

        $this->upsert_setting('portal_oo_last_auth_code', $code);
        $this->upsert_setting('portal_oo_last_auth_message', $message);

        status_header($success ? 200 : 400);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');

        $settings_url = add_query_arg(
            [
                'page' => EstateOfficeCRM_Admin::SETTINGS_SLUG,
                'tab' => 'portal_export',
            ],
            admin_url('admin.php')
        );

        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>Estate Office CRM - OtoDom/OLX callback</title></head><body>';
        echo '<h1>Estate Office CRM - OtoDom/OLX callback</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p><a href="' . esc_url($settings_url) . '">Wroc do ustawien eksportu na portale</a></p>';
        echo '</body></html>';
        exit;
    }

    public function handle_otodom_olx_notification_callback(): void
    {
        $provider = isset($_GET['eocrm_portal_notify']) ? self::sanitize_provider((string) wp_unslash($_GET['eocrm_portal_notify'])) : '';
        if ($provider !== self::TARGET_OTODOM_OLX) {
            return;
        }
        if ($this->get_setting('developer_access_enabled') !== '1') {
            status_header(403);
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode(['status' => 'error', 'message' => 'developer_access_required']);
            exit;
        }

        $settings = self::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, self::TARGET_OTODOM_OLX);
        $secret = trim((string) ($settings['notification_secret'] ?? ''));

        $raw_body = file_get_contents('php://input');
        if (! is_string($raw_body)) {
            $raw_body = '';
        }
        $payload = json_decode($raw_body, true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $signature_header = $this->get_request_header_value('x-signature');
        $object_id = isset($payload['object_id']) ? (string) $payload['object_id'] : '';
        $transaction_id = isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : '';
        if ($secret !== '' && $object_id !== '' && $transaction_id !== '') {
            $expected_signature = hash_hmac('sha1', $object_id . ',' . $transaction_id, $secret);
            if ($signature_header === '' || ! hash_equals($expected_signature, strtolower($signature_header))) {
                $this->upsert_setting('portal_oo_last_webhook_at', current_time('mysql'));
                $this->upsert_setting('portal_oo_last_webhook_event', 'signature_error');
                $this->upsert_setting('portal_oo_last_webhook_message', 'Odrzucono webhook: nieprawidlowy podpis x-signature.');
                status_header(403);
                header('Content-Type: application/json; charset=utf-8');
                echo wp_json_encode(['status' => 'error', 'message' => 'invalid_signature']);
                exit;
            }
        }

        $event_type = isset($payload['event_type']) ? sanitize_text_field((string) $payload['event_type']) : '';
        $flow = isset($payload['flow']) ? sanitize_text_field((string) $payload['flow']) : '';
        $error_message = isset($payload['error_message']) ? sanitize_text_field((string) $payload['error_message']) : '';

        $webhook_message = $event_type !== '' ? $event_type : 'brak event_type';
        if ($flow !== '') {
            $webhook_message .= ' | flow: ' . $flow;
        }
        if ($error_message !== '') {
            $webhook_message .= ' | error: ' . $error_message;
        }

        if ($object_id !== '') {
            $match = $this->find_otodom_property_ref_by_advert_uuid($object_id);
            if (is_array($match)) {
                $this->store_otodom_advert_state(
                    (int) ($match['property_id'] ?? 0),
                    (string) ($match['site_urn'] ?? ''),
                    $this->extract_otodom_state_from_notification($payload)
                );
            }
        }

        $this->upsert_setting('portal_oo_last_webhook_at', current_time('mysql'));
        $this->upsert_setting('portal_oo_last_webhook_event', $event_type);
        $this->upsert_setting('portal_oo_last_webhook_message', $webhook_message);

        status_header(200);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['status' => 'ok']);
        exit;
    }

    /**
     * @return array{success:bool, skipped?:bool, message:string}
     */
    private function send_to_otodom_olx(int $property_id, string $event): array
    {
        if ($property_id <= 0) {
            return [
                'success' => false,
                'message' => 'Brak poprawnego identyfikatora nieruchomosci.',
            ];
        }

        $settings = self::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, self::TARGET_OTODOM_OLX);
        if ((string) ($settings['enabled'] ?? '0') !== '1') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Eksport OtoDom/OLX jest wylaczony.',
            ];
        }

        $client_id = trim((string) ($settings['client_id'] ?? ''));
        $client_secret = trim((string) ($settings['client_secret'] ?? ''));
        $api_key = trim((string) ($settings['api_key'] ?? ''));
        if ($client_id === '' || $client_secret === '' || $api_key === '') {
            return [
                'success' => false,
                'message' => 'Uzupelnij Client ID, Client Secret oraz API Key dla OtoDom/OLX.',
            ];
        }

        $token_result = $this->ensure_otodom_access_token($settings);
        if (is_wp_error($token_result)) {
            return [
                'success' => false,
                'message' => $token_result->get_error_message(),
            ];
        }

        $access_token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
        if ($access_token === '') {
            return [
                'success' => false,
                'message' => 'Brak aktywnego access token dla OtoDom/OLX.',
            ];
        }

        $targets = $this->resolve_otodom_targets($settings);
        if (empty($targets)) {
            return [
                'success' => false,
                'message' => 'Brak skonfigurowanych site_urn dla OtoDom/OLX.',
            ];
        }

        $done_targets = [];
        $skipped_targets = [];
        foreach ($targets as $site_urn) {
            $site_urn = self::sanitize_site_urn((string) $site_urn);
            if ($site_urn === '') {
                continue;
            }

            $advert_uuid = $this->load_otodom_advert_uuid($property_id, $site_urn);
            if ($event === 'delete') {
                if ($advert_uuid === '') {
                    $skipped_targets[] = $site_urn;
                    continue;
                }

                $delete_result = $this->request_otodom_api(
                    'DELETE',
                    '/advert/v1/' . rawurlencode($advert_uuid),
                    $settings,
                    $access_token
                );
                if (! $delete_result['success']) {
                    $this->store_otodom_advert_state($property_id, $site_urn, [
                        'last_action_status' => 'delete_error',
                        'last_error' => (string) ($delete_result['message'] ?? 'delete_failed'),
                        'last_synced_at' => current_time('mysql'),
                    ]);
                    return [
                        'success' => false,
                        'message' => 'Delete OtoDom/OLX nieudany (' . $site_urn . '): ' . (string) ($delete_result['message'] ?? 'Nieznany blad'),
                    ];
                }

                $this->store_otodom_advert_state($property_id, $site_urn, [
                    'last_action_status' => 'delete_success',
                    'state_code' => 'deleted',
                    'visible_in_profile' => '0',
                    'last_error' => '',
                    'last_synced_at' => current_time('mysql'),
                ]);
                $this->remove_otodom_advert_uuid($property_id, $site_urn);
                $done_targets[] = $site_urn . ' (delete)';
                continue;
            }

            $payload_result = $this->build_otodom_olx_payload($property_id, $event, $settings, $site_urn);
            if (is_wp_error($payload_result)) {
                return [
                    'success' => false,
                    'message' => $payload_result->get_error_message(),
                ];
            }

            $method = $advert_uuid === '' || $event === 'insert' ? 'POST' : 'PUT';
            $path = $method === 'POST'
                ? '/advert/v1'
                : '/advert/v1/' . rawurlencode($advert_uuid);

            $request_result = $this->request_otodom_api($method, $path, $settings, $access_token, $payload_result);
            if (! $request_result['success'] && $method === 'PUT') {
                // fallback for records without synced UUID on provider side
                $request_result = $this->request_otodom_api('POST', '/advert/v1', $settings, $access_token, $payload_result);
                $method = 'POST';
            }

            if (! $request_result['success']) {
                $this->store_otodom_advert_state($property_id, $site_urn, [
                    'last_action_status' => strtolower($method) . '_error',
                    'last_error' => (string) ($request_result['message'] ?? 'request_failed'),
                    'last_synced_at' => current_time('mysql'),
                ]);
                return [
                    'success' => false,
                    'message' => 'Publikacja OtoDom/OLX nieudana (' . $site_urn . '): ' . (string) ($request_result['message'] ?? 'Nieznany blad'),
                ];
            }

            $response_data = isset($request_result['data']) && is_array($request_result['data']) ? $request_result['data'] : [];
            $new_uuid = '';
            if (isset($response_data['data']) && is_array($response_data['data']) && isset($response_data['data']['uuid'])) {
                $new_uuid = sanitize_text_field((string) $response_data['data']['uuid']);
            } elseif (isset($response_data['uuid'])) {
                $new_uuid = sanitize_text_field((string) $response_data['uuid']);
            }
            if ($new_uuid === '') {
                $new_uuid = $advert_uuid;
            }
            if ($new_uuid !== '') {
                $this->store_otodom_advert_uuid($property_id, $site_urn, $new_uuid);
                if ($event !== 'delete') {
                    $metadata = $this->fetch_otodom_advert_metadata($new_uuid, $settings, $access_token);
                    if (is_array($metadata)) {
                        $this->store_otodom_advert_state($property_id, $site_urn, $metadata);
                    }
                }
            }

            $done_targets[] = $site_urn . ' (' . strtolower($method) . ')';
        }

        if (empty($done_targets) && ! empty($skipped_targets)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Brak advert_uuid dla delete. Pominiete: ' . implode(', ', $skipped_targets),
            ];
        }

        return [
            'success' => true,
            'message' => 'Eksport OtoDom/OLX zakonczony: ' . implode(', ', $done_targets),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, string>
     */
    private function resolve_otodom_targets(array $settings): array
    {
        $targets = [];
        $primary = self::sanitize_site_urn((string) ($settings['site_urn'] ?? ''));
        if ($primary !== '') {
            $targets[] = $primary;
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function build_otodom_olx_payload(int $property_id, string $event, array $settings, string $site_urn)
    {
        $include_inactive = $event === 'delete';
        $property = $this->get_property_row($property_id, $include_inactive);
        if (! is_array($property)) {
            return new WP_Error('eocrm_oo_property', 'Nie znaleziono nieruchomosci do eksportu OtoDom/OLX.');
        }

        if ($event !== 'delete') {
            $is_active = isset($property['is_active']) ? (int) $property['is_active'] : 0;
            $export_portals = isset($property['export_portals']) ? (int) $property['export_portals'] : 0;
            if ($is_active !== 1 || $export_portals !== 1) {
                return new WP_Error('eocrm_oo_property_status', 'Nieruchomosc nie jest aktywna lub nie jest oznaczona do eksportu na portale.');
            }
        }

        $category_urn = $this->map_otodom_category_urn(
            (string) ($property['property_type'] ?? ''),
            (string) ($property['transaction_type'] ?? '')
        );
        if ($category_urn === '') {
            return new WP_Error('eocrm_oo_mapping', 'Nie mozna zmapowac rodzaju nieruchomosci/typu transakcji do OtoDom/OLX.');
        }

        $price = $this->to_float($property['price'] ?? null);
        if ($event !== 'delete' && ($price === null || $price <= 0)) {
            return new WP_Error('eocrm_oo_required', 'Brak ceny oferty wymaganej przez OtoDom/OLX.');
        }
        if ($price === null || $price <= 0) {
            $price = 1.0;
        }

        $currency = strtoupper(trim((string) ($property['price_currency'] ?? 'PLN')));
        if (! in_array($currency, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
            $currency = 'PLN';
        }

        $area = $this->to_float($property['area'] ?? null);
        $plot_area = $this->to_float($property['plot_area'] ?? null);
        if ($area === null || $area <= 0) {
            $area = $plot_area;
        }
        if ($event !== 'delete' && ($area === null || $area <= 0)) {
            return new WP_Error('eocrm_oo_required', 'Brak metrazu/powierzchni wymaganej przez OtoDom/OLX.');
        }
        if ($area === null || $area <= 0) {
            $area = 1.0;
        }

        $latitude = $this->to_float($property['latitude'] ?? null);
        $longitude = $this->to_float($property['longitude'] ?? null);
        if ($event !== 'delete' && ($latitude === null || $longitude === null)) {
            return new WP_Error('eocrm_oo_required', 'Brak wspolrzednych GPS (lat/lon) wymaganych przez OtoDom/OLX.');
        }
        if ($latitude === null) {
            $latitude = 52.2297;
        }
        if ($longitude === null) {
            $longitude = 21.0122;
        }

        $description = $this->sanitize_otodom_description((string) ($property['description'] ?? ''), $property_id);
        $offer_number = sanitize_text_field((string) ($property['offer_number'] ?? ''));
        if ($offer_number === '') {
            $offer_number = 'OF-' . (string) $property_id;
        }
        $title = $this->build_otodom_title($property, $offer_number);
        if ((string) ($settings['test_account_mode'] ?? '0') === '1') {
            $title = $this->build_otodom_test_title($title);
            $description = $this->otodom_test_description();
        }

        $agent = $this->build_agent_payload($property, $settings);
        $contact_name = trim((string) ($agent['name'] ?? '') . ' ' . (string) ($agent['surname'] ?? ''));
        if ($contact_name === '') {
            $contact_name = 'Agent CRM';
        }

        $contact_phone = preg_replace('/\D+/', '', (string) ($agent['phone'] ?? ''));
        if (! is_string($contact_phone) || $contact_phone === '') {
            $contact_phone = '600000000';
        }
        $contact_email = sanitize_email((string) ($agent['email'] ?? ''));
        if ($contact_email === '') {
            $contact_email = sanitize_email((string) get_option('admin_email', 'kontakt@example.com'));
        }
        if ((string) ($settings['enable_olx'] ?? '0') === '1') {
            $olx_validation = $this->validate_otodom_olx_export_fields($title, $description, $contact_name, $contact_phone, (float) $price);
            if (is_wp_error($olx_validation)) {
                return $olx_validation;
            }
        }

        $image_urls = $this->load_property_image_urls($property_id);
        if ($event !== 'delete' && empty($image_urls)) {
            return new WP_Error('eocrm_oo_media', 'OtoDom/OLX wymagaja co najmniej jednego zdjecia oferty.');
        }

        $images_payload = [];
        foreach ($image_urls as $index => $image_url) {
            if ($image_url === '') {
                continue;
            }
            $images_payload[] = [
                'url' => $image_url,
                'order' => $index + 1,
            ];
        }

        $media_json = $this->decode_json_assoc((string) ($property['media_json'] ?? ''));
        $video_link = isset($media_json['video_link']) ? esc_url_raw((string) $media_json['video_link']) : '';
        $virtual_link = isset($media_json['virtual_tour_link']) ? esc_url_raw((string) $media_json['virtual_tour_link']) : '';

        $attributes = [];
        $property_type = strtoupper(trim((string) ($property['property_type'] ?? '')));
        if ($property_type === 'DZIALKA') {
            $terrain_area = $plot_area !== null && $plot_area > 0 ? $plot_area : $area;
            $attributes[] = [
                'urn' => 'urn:concept:terrain-area-m2',
                'value' => (string) $this->format_float((float) $terrain_area, 2),
            ];
        } else {
            $attributes[] = [
                'urn' => 'urn:concept:net-area-m2',
                'value' => (string) $this->format_float((float) $area, 2),
            ];
        }

        $rooms = $this->to_int($property['rooms'] ?? null);
        if ($rooms !== null && $rooms > 0) {
            $attributes[] = [
                'urn' => 'urn:concept:number-of-rooms',
                'value' => (string) $rooms,
            ];
        }

        $custom_id = 'EOCRM-' . (string) $property_id . '-' . substr(md5($site_urn), 0, 8);
        $location_custom_fields = $this->resolve_otodom_location_custom_fields($property, $settings);
        if (is_wp_error($location_custom_fields)) {
            return $location_custom_fields;
        }

        return [
            'title' => $title,
            'description' => $description,
            'category_urn' => $category_urn,
            'site_urn' => $site_urn,
            'contact' => [
                'name' => $contact_name,
                'phone' => $contact_phone,
                'email' => $contact_email,
            ],
            'custom_fields' => [
                'id' => $custom_id,
                'reference_id' => $offer_number,
            ],
            'price' => [
                'value' => (float) $this->format_float((float) $price, 2),
                'currency' => $currency,
            ],
            'images' => $images_payload,
            'location' => [
                'lat' => (float) $this->format_float((float) $latitude, 8),
                'lon' => (float) $this->format_float((float) $longitude, 8),
                'exact' => ! empty($location_custom_fields['street_name']),
                'custom_fields' => $location_custom_fields,
            ],
            'attributes' => $attributes,
            'auto_extend' => true,
            'movie_url' => $video_link,
            'virtual_walk' => $virtual_link,
        ];
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function resolve_otodom_location_custom_fields(array $property, array $settings)
    {
        $custom_fields = [];
        $street_name = sanitize_text_field((string) ($property['street'] ?? ''));
        $city_name = sanitize_text_field((string) ($property['city'] ?? ''));
        $district_name = sanitize_text_field((string) ($property['district'] ?? ''));
        $county_name = sanitize_text_field((string) ($property['county'] ?? ''));

        if ($city_name === '') {
            return new WP_Error('eocrm_oo_city_required', 'Brak miasta potrzebnego do mapowania city_id dla OtoDom.');
        }

        $city_match = $this->lookup_otodom_city($city_name, $county_name, $settings);
        if (is_wp_error($city_match)) {
            return $city_match;
        }

        $city_id = isset($city_match['id']) ? (int) $city_match['id'] : 0;
        if ($city_id <= 0) {
            return new WP_Error('eocrm_oo_city_mapping', 'Nie mozna zmapowac city_id dla miasta "' . $city_name . '" w OtoDom.');
        }

        $custom_fields['city_id'] = $city_id;
        if ($street_name !== '') {
            $custom_fields['street_name'] = $street_name;
        }

        if ($district_name !== '') {
            $district_id = $this->lookup_otodom_district_id($city_id, $district_name, $settings);
            if (is_wp_error($district_id)) {
                return $district_id;
            }
            if (is_int($district_id) && $district_id > 0) {
                $custom_fields['district_id'] = $district_id;
            }
        }

        return $custom_fields;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function lookup_otodom_city(string $city_name, string $county_name, array $settings)
    {
        $path = add_query_arg(
            [
                'search' => $city_name,
                'exact' => 'true',
                'no-districts' => '1',
                'limit' => 20,
            ],
            '/cities'
        );
        $result = $this->request_otodom_location_api($path, $settings);
        if (is_wp_error($result)) {
            return $result;
        }

        $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];
        if (count($data) === 1 && is_array($data[0])) {
            return $data[0];
        }

        $normalized_county = $this->normalize_location_name($county_name);
        if ($normalized_county === '') {
            return [];
        }

        $matched = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $subregion_id = isset($item['subregion_id']) ? (int) $item['subregion_id'] : 0;
            if ($subregion_id <= 0) {
                continue;
            }

            $subregion = $this->lookup_otodom_subregion($subregion_id, $settings);
            if (is_wp_error($subregion) || ! is_array($subregion)) {
                continue;
            }

            $subregion_name = $this->normalize_location_name((string) ($subregion['name'] ?? ''));
            if ($subregion_name !== '' && $subregion_name === $normalized_county) {
                $matched[] = $item;
            }
        }

        if (count($matched) === 1 && is_array($matched[0])) {
            return $matched[0];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function lookup_otodom_subregion(int $subregion_id, array $settings)
    {
        if ($subregion_id <= 0) {
            return [];
        }

        $path = add_query_arg(
            [
                'id' => $subregion_id,
                'limit' => 1,
            ],
            '/subregions'
        );
        $result = $this->request_otodom_location_api($path, $settings);
        if (is_wp_error($result)) {
            return $result;
        }

        $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];
        if (! empty($data) && is_array($data[0])) {
            return $data[0];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $settings
     * @return int|WP_Error
     */
    private function lookup_otodom_district_id(int $city_id, string $district_name, array $settings)
    {
        if ($city_id <= 0 || $district_name === '') {
            return 0;
        }

        $path = add_query_arg(
            [
                'id' => $city_id,
                'children' => 'true',
                'limit' => 200,
            ],
            '/cities'
        );
        $result = $this->request_otodom_location_api($path, $settings);
        if (is_wp_error($result)) {
            return $result;
        }

        $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];
        $normalized_district = $this->normalize_location_name($district_name);
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }
            $districts = isset($item['districts']) && is_array($item['districts']) ? $item['districts'] : [];
            foreach ($districts as $district) {
                if (! is_array($district)) {
                    continue;
                }
                $candidate_name = $this->normalize_location_name((string) ($district['name'] ?? ''));
                if ($candidate_name !== '' && $candidate_name === $normalized_district) {
                    return isset($district['id']) ? (int) $district['id'] : 0;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function request_otodom_location_api(string $path, array $settings)
    {
        $api_key = trim((string) ($settings['api_key'] ?? ''));
        $user_agent = sanitize_text_field((string) ($settings['user_agent'] ?? 'Estate Office CRM'));
        if ($api_key === '') {
            return new WP_Error('eocrm_oo_locations_api_key', 'Brak API Key do wyszukania lokalizacji OtoDom.');
        }

        $site_urn = self::sanitize_site_urn((string) ($settings['site_urn'] ?? 'urn:site:otodompl'));
        if ($site_urn === '') {
            $site_urn = 'urn:site:otodompl';
        }

        $request_path = '/' . ltrim($path, '/');
        $cache_key = 'eocrm_oo_loc_' . md5($site_urn . '|' . $request_path);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.olxgroup.com/locations/v1/' . $site_urn . $request_path,
            [
                'timeout' => 20,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $api_key,
                    'User-Agent' => $user_agent,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('eocrm_oo_locations_request', 'Blad pobierania lokalizacji OtoDom: ' . $response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $decoded = json_decode((string) $body_raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        if ($status_code < 200 || $status_code >= 300) {
            $message = isset($decoded['message']) ? sanitize_text_field((string) $decoded['message']) : ('HTTP ' . $status_code);
            return new WP_Error('eocrm_oo_locations_http', 'API lokalizacji OtoDom zwrocilo blad: ' . $message);
        }

        set_transient($cache_key, $decoded, HOUR_IN_SECONDS * 12);
        return $decoded;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|null
     */
    private function fetch_otodom_advert_metadata(string $advert_uuid, array $settings, string $access_token): ?array
    {
        $uuid = sanitize_text_field($advert_uuid);
        if ($uuid === '') {
            return null;
        }

        $response = $this->request_otodom_api('GET', '/advert/v1/' . rawurlencode($uuid) . '/meta', $settings, $access_token);
        if (! $response['success']) {
            return null;
        }

        $data = isset($response['data']['data']) && is_array($response['data']['data'])
            ? $response['data']['data']
            : [];
        if (empty($data)) {
            return null;
        }

        return $this->extract_otodom_state_from_metadata($data);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function extract_otodom_state_from_metadata(array $metadata): array
    {
        $state = isset($metadata['state']) && is_array($metadata['state']) ? $metadata['state'] : [];

        return [
            'last_action_status' => sanitize_text_field((string) ($metadata['last_action_status'] ?? '')),
            'state_code' => sanitize_text_field((string) ($state['code'] ?? '')),
            'visible_in_profile' => ! empty($state['visible_in_profile']) ? '1' : '0',
            'url' => esc_url_raw((string) ($state['url'] ?? '')),
            'ttl' => sanitize_text_field((string) ($state['ttl'] ?? '')),
            'created_at' => sanitize_text_field((string) ($state['created_at'] ?? '')),
            'activated_at' => sanitize_text_field((string) ($state['activated_at'] ?? '')),
            'modified_at' => sanitize_text_field((string) ($state['modified_at'] ?? '')),
            'last_error' => sanitize_text_field((string) ($metadata['last_error'] ?? '')),
            'last_synced_at' => current_time('mysql'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function extract_otodom_state_from_notification(array $payload): array
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $state_payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return [
            'last_action_status' => sanitize_text_field((string) ($payload['event_type'] ?? '')),
            'state_code' => sanitize_text_field((string) ($state_payload['code'] ?? '')),
            'visible_in_profile' => ! empty($state_payload['visible_in_profile']) ? '1' : '0',
            'url' => esc_url_raw((string) ($state_payload['url'] ?? '')),
            'ttl' => sanitize_text_field((string) ($state_payload['ttl'] ?? '')),
            'created_at' => sanitize_text_field((string) ($state_payload['created_at'] ?? '')),
            'activated_at' => sanitize_text_field((string) ($state_payload['activated_at'] ?? '')),
            'modified_at' => sanitize_text_field((string) ($state_payload['modified_at'] ?? '')),
            'last_error' => sanitize_text_field((string) ($payload['error_message'] ?? '')),
            'last_event_type' => sanitize_text_field((string) ($payload['event_type'] ?? '')),
            'last_synced_at' => current_time('mysql'),
        ];
    }

    /**
     * @param array<string, string> $state
     */
    private function store_otodom_advert_state(int $property_id, string $site_urn, array $state): bool
    {
        $site_key = self::sanitize_site_urn($site_urn);
        if ($property_id <= 0 || $site_key === '') {
            return false;
        }

        $refs = $this->load_otodom_refs($property_id);
        $existing = isset($refs[$site_key]) && is_array($refs[$site_key]) ? $refs[$site_key] : [];
        $merged = array_merge($existing, $state, [
            'updated_at' => current_time('mysql'),
        ]);

        $refs[$site_key] = $this->normalize_otodom_ref_row($merged);
        return $this->write_otodom_refs($property_id, $refs);
    }

    /**
     * @return array{property_id:int, site_urn:string}|null
     */
    private function find_otodom_property_ref_by_advert_uuid(string $advert_uuid): ?array
    {
        $uuid = sanitize_text_field($advert_uuid);
        if ($uuid === '') {
            return null;
        }

        global $wpdb;
        $table = $this->tables['properties'];
        $like = '%' . $wpdb->esc_like($uuid) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, custom_fields_json
                FROM {$table}
                WHERE custom_fields_json LIKE %s",
                $like
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $property_id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($property_id <= 0) {
                continue;
            }

            $refs = $this->load_otodom_refs($property_id);
            foreach ($refs as $site_urn => $site_data) {
                if (! is_array($site_data)) {
                    continue;
                }

                $candidate_uuid = sanitize_text_field((string) ($site_data['advert_uuid'] ?? ''));
                if ($candidate_uuid !== '' && hash_equals($candidate_uuid, $uuid)) {
                    return [
                        'property_id' => $property_id,
                        'site_urn' => (string) $site_urn,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function load_property_image_urls(int $property_id): array
    {
        $result = [];
        if ($property_id <= 0) {
            return $result;
        }

        global $wpdb;
        $table = $this->tables['property_media'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT attachment_id, media_url
                FROM {$table}
                WHERE property_id = %d
                    AND media_type = 'photo'
                ORDER BY position ASC, id ASC
                LIMIT %d",
                $property_id,
                self::MAX_MEDIA_ITEMS_PER_TYPE
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return $result;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            $media_url = isset($row['media_url']) ? esc_url_raw((string) $row['media_url']) : '';
            $image_url = '';
            if ($attachment_id > 0) {
                $resolved = wp_get_attachment_url($attachment_id);
                if (is_string($resolved)) {
                    $image_url = esc_url_raw($resolved);
                }
            }
            if ($image_url === '') {
                $image_url = $media_url;
            }
            if ($image_url !== '') {
                $result[] = $image_url;
            }
        }

        return array_values(array_unique($result));
    }

    private function map_otodom_category_urn(string $property_type, string $transaction_type): string
    {
        $type = $this->normalize_enum_token($property_type);
        $transaction = $this->normalize_enum_token($transaction_type);
        if (! in_array($transaction, ['SPRZEDAZ', 'WYNAJEM'], true)) {
            return '';
        }

        if ($type === 'MIESZKANIE') {
            return $transaction === 'SPRZEDAZ' ? 'urn:concept:apartments-for-sale' : 'urn:concept:apartments-for-rent';
        }
        if ($type === 'DOM') {
            return $transaction === 'SPRZEDAZ' ? 'urn:concept:houses-for-sale' : 'urn:concept:houses-for-rent';
        }
        if ($type === 'DZIALKA') {
            return $transaction === 'SPRZEDAZ' ? 'urn:concept:lots-for-sale' : 'urn:concept:lots-for-rent';
        }
        if (in_array($type, ['LOKAL_HU', 'LOKAL_H_U', 'LOKAL'], true)) {
            return $transaction === 'SPRZEDAZ' ? 'urn:concept:stores-for-sale' : 'urn:concept:stores-for-rent';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $property
     */
    private function build_otodom_title(array $property, string $offer_number): string
    {
        $parts = [];
        $city = sanitize_text_field((string) ($property['city'] ?? ''));
        $district = sanitize_text_field((string) ($property['district'] ?? ''));
        $street = sanitize_text_field((string) ($property['street'] ?? ''));
        $building_no = sanitize_text_field((string) ($property['building_no'] ?? ''));
        if ($street !== '') {
            $parts[] = trim($street . ($building_no !== '' ? ' ' . $building_no : ''));
        }
        if ($district !== '') {
            $parts[] = $district;
        }
        if ($city !== '') {
            $parts[] = $city;
        }

        $title = trim(implode(', ', $parts));
        if ($title === '') {
            $title = 'Oferta nieruchomosci ' . $offer_number;
        }
        $title_length = function_exists('mb_strlen') ? (int) mb_strlen($title) : strlen($title);
        if ($title_length < 5) {
            $title .= ' - oferta';
            $title_length = function_exists('mb_strlen') ? (int) mb_strlen($title) : strlen($title);
        }
        if ($title_length > 70) {
            if (function_exists('mb_substr')) {
                $title = trim((string) mb_substr($title, 0, 67)) . '...';
            } else {
                $title = trim(substr($title, 0, 67)) . '...';
            }
        }

        return $title;
    }

    private function sanitize_otodom_description(string $description, int $property_id): string
    {
        $text = trim((string) wp_strip_all_tags($description, true));
        if ($text === '') {
            $text = 'Oferta nieruchomosci #' . (string) $property_id . '. Zapraszamy do kontaktu z opiekunem oferty.';
        }
        $text_length = function_exists('mb_strlen') ? (int) mb_strlen($text) : strlen($text);
        if ($text_length < 20) {
            $text .= ' Szczegolowe informacje i pelna prezentacja oferty dostepne po kontakcie z agentem.';
            $text_length = function_exists('mb_strlen') ? (int) mb_strlen($text) : strlen($text);
        }
        if ($text_length > 9000) {
            if (function_exists('mb_substr')) {
                $text = (string) mb_substr($text, 0, 9000);
            } else {
                $text = substr($text, 0, 9000);
            }
        }

        return $text;
    }

    private function build_otodom_test_title(string $title): string
    {
        $base = preg_replace('/^\[qatest-mercury\]\s*/i', '', trim($title));
        if (! is_string($base) || $base === '') {
            $base = 'test advert';
        }

        $result = '[qatest-mercury] ' . $base;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($result) > 70) {
                $result = trim((string) mb_substr($result, 0, 67)) . '...';
            }
        } elseif (strlen($result) > 70) {
            $result = trim(substr($result, 0, 67)) . '...';
        }

        return $result;
    }

    private function otodom_test_description(): string
    {
        return 'Czasami musimy dodac takie ogloszenie, zeby zweryfikowac dzialanie niektorych funkcji systemu. Liczymy na Twoja wyrozumialosc :) Radzimy skorzystac ponownie z naszej wyszukiwarki ofert.<br/><br/> Powodzenia w dalszych poszukiwaniach!';
    }

    private function normalize_location_name(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $normalized = remove_accents($normalized);
        }

        $normalized = strtolower($normalized);
        $normalized = str_replace(['powiat ', 'miasto ', 'm. ', 'm ', 'gmina '], '', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        if (! is_string($normalized)) {
            return '';
        }

        return trim($normalized);
    }

    private function validate_otodom_olx_export_fields(string $title, string $description, string $contact_name, string $contact_phone, float $price)
    {
        $title_length = function_exists('mb_strlen') ? (int) mb_strlen($title) : strlen($title);
        if ($title_length < 5 || $title_length > 70) {
            return new WP_Error('eocrm_oo_olx_title', 'Tytul nie spelnia wymagan OLX eksportu (5-70 znakow).');
        }

        $description_length = function_exists('mb_strlen') ? (int) mb_strlen($description) : strlen($description);
        if ($description_length < 20 || $description_length > 9000) {
            return new WP_Error('eocrm_oo_olx_description', 'Opis nie spelnia wymagan OLX eksportu (20-9000 znakow).');
        }

        if ($contact_name === '' || preg_match('/[A-Z]{4,}/', $contact_name)) {
            return new WP_Error('eocrm_oo_olx_contact', 'Kontakt do oferty nie spelnia wymagan OLX eksportu.');
        }

        $phone_length = strlen($contact_phone);
        if ($phone_length < 7 || $phone_length > 14) {
            return new WP_Error('eocrm_oo_olx_phone', 'Telefon nie spelnia wymagan OLX eksportu (7-14 cyfr).');
        }

        if ($price <= 0) {
            return new WP_Error('eocrm_oo_olx_price', 'Cena musi byc wieksza od 0 dla eksportu zgodnego z OLX.');
        }

        foreach ([$title, $description] as $text_value) {
            if (preg_match('/https?:\/\/|www\.|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text_value)) {
                return new WP_Error('eocrm_oo_olx_content', 'Tytul lub opis zawiera link albo adres e-mail, co blokuje eksport do OLX.');
            }
            if (preg_match('/([!?\.,\-=+#%&@*_><:\(\)\|])\1\1\1+/', $text_value)) {
                return new WP_Error('eocrm_oo_olx_symbols', 'Tytul lub opis zawiera zbyt wiele powtorzonych znakow specjalnych dla eksportu OLX.');
            }
        }

        return true;
    }

    private function normalize_enum_token(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $normalized = remove_accents($normalized);
        }
        $normalized = strtoupper($normalized);
        $normalized = str_replace([' ', '/', '-', '\\'], '_', $normalized);
        $normalized = preg_replace('/[^A-Z0-9_]+/', '', $normalized);
        if (! is_string($normalized)) {
            return '';
        }
        $normalized = preg_replace('/_+/', '_', $normalized);
        if (! is_string($normalized)) {
            return '';
        }

        return trim($normalized, '_');
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function ensure_otodom_access_token(array $settings)
    {
        $access_token = trim((string) ($settings['access_token'] ?? ''));
        $refresh_token = trim((string) ($settings['refresh_token'] ?? ''));
        $expires_at_raw = trim((string) ($settings['token_expires_at'] ?? ''));
        $expires_at_ts = $expires_at_raw !== '' ? strtotime($expires_at_raw) : false;
        $now_ts = time();
        $has_valid_access = $access_token !== '' && is_int($expires_at_ts) && $expires_at_ts > ($now_ts + 90);
        if ($has_valid_access) {
            return [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'token_expires_at' => $expires_at_raw,
            ];
        }

        if ($refresh_token === '') {
            return new WP_Error(
                'eocrm_oo_no_token',
                'Brak aktywnego tokenu OtoDom/OLX. Kliknij "Autoryzuj konto OtoDom/OLX" w ustawieniach portalu.'
            );
        }

        $token_result = $this->request_otodom_token($settings, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
        ]);
        if (is_wp_error($token_result)) {
            return $token_result;
        }

        $this->persist_otodom_token_bundle($token_result);
        return $token_result;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string> $payload
     * @return array<string, mixed>|WP_Error
     */
    private function request_otodom_token(array $settings, array $payload)
    {
        $client_id = trim((string) ($settings['client_id'] ?? ''));
        $client_secret = trim((string) ($settings['client_secret'] ?? ''));
        $api_key = trim((string) ($settings['api_key'] ?? ''));
        $user_agent = sanitize_text_field((string) ($settings['user_agent'] ?? 'Estate Office CRM'));

        $missing = self::get_missing_otodom_credentials($settings);
        if (! empty($missing)) {
            return new WP_Error(
                'eocrm_oo_token_settings',
                'Brak zapisanych danych API dla OtoDom/OLX: ' . implode(', ', $missing) . '. Zapisz ustawienia portalu i sprobuj ponownie.'
            );
        }

        $body = wp_json_encode($payload);
        if (! is_string($body) || $body === '') {
            return new WP_Error('eocrm_oo_token_json', 'Nie mozna zbudowac payloadu autoryzacji OtoDom/OLX.');
        }

        $response = wp_remote_post(
            'https://api.olxgroup.com/oauth/v1/token',
            [
                'timeout' => 20,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                    'X-API-KEY' => $api_key,
                    'User-Agent' => $user_agent,
                ],
                'body' => $body,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('eocrm_oo_token_request', 'Blad polaczenia z OAuth API OtoDom/OLX: ' . $response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $decoded = json_decode((string) $raw_body, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        if ($status_code < 200 || $status_code >= 300) {
            $message = isset($decoded['message']) ? sanitize_text_field((string) $decoded['message']) : '';
            if ($message === '') {
                $message = 'HTTP ' . $status_code;
            }
            return new WP_Error('eocrm_oo_token_http', 'OAuth OtoDom/OLX zwrocil blad: ' . $message);
        }

        $access_token = isset($decoded['access_token']) ? sanitize_text_field((string) $decoded['access_token']) : '';
        $refresh_token = isset($decoded['refresh_token']) ? sanitize_text_field((string) $decoded['refresh_token']) : '';
        $scope = isset($decoded['scope']) ? sanitize_text_field((string) $decoded['scope']) : '';
        $expires_in = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600;
        if ($expires_in < 60) {
            $expires_in = 3600;
        }

        if ($access_token === '') {
            return new WP_Error('eocrm_oo_token_empty', 'OAuth OtoDom/OLX nie zwrocil access_token.');
        }

        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'scope' => $scope,
            'token_expires_at' => gmdate('Y-m-d H:i:s', time() + $expires_in),
            'authorized_at' => current_time('mysql'),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $payload
     * @return array{success:bool, status_code:int, message:string, data:array<string, mixed>}
     */
    private function request_otodom_api(string $method, string $path, array $settings, string $access_token, array $payload = []): array
    {
        $api_key = trim((string) ($settings['api_key'] ?? ''));
        $user_agent = sanitize_text_field((string) ($settings['user_agent'] ?? 'Estate Office CRM'));
        if ($api_key === '' || $access_token === '') {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Brak API Key lub access token dla OtoDom/OLX.',
                'data' => [],
            ];
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 25,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-KEY' => $api_key,
                'Authorization' => 'Bearer ' . $access_token,
                'User-Agent' => $user_agent,
            ],
        ];

        if (! empty($payload) && in_array(strtoupper($method), ['POST', 'PUT'], true)) {
            $body = wp_json_encode($payload);
            if (! is_string($body) || $body === '') {
                return [
                    'success' => false,
                    'status_code' => 0,
                    'message' => 'Nie mozna zbudowac JSON payloadu OtoDom/OLX.',
                    'data' => [],
                ];
            }
            $args['body'] = $body;
        }

        $response = wp_remote_request('https://api.olxgroup.com' . $path, $args);
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => $response->get_error_message(),
                'data' => [],
            ];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode((string) $body_raw, true);
        if (! is_array($data)) {
            $data = [];
        }

        $message = isset($data['message']) ? sanitize_text_field((string) $data['message']) : '';
        if ($message === '') {
            $message = 'HTTP ' . $status_code;
        }

        return [
            'success' => $status_code >= 200 && $status_code < 300,
            'status_code' => $status_code,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, string>
     */
    public static function get_missing_otodom_credentials(array $settings): array
    {
        $missing = [];

        $client_id = isset($settings['client_id']) ? trim((string) $settings['client_id']) : '';
        $client_secret = isset($settings['client_secret']) ? trim((string) $settings['client_secret']) : '';
        $api_key = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';

        if ($client_id === '') {
            $missing[] = 'Client ID';
        }
        if ($client_secret === '') {
            $missing[] = 'Client Secret';
        }
        if ($api_key === '') {
            $missing[] = 'API Key';
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $token_data
     */
    private function persist_otodom_token_bundle(array $token_data): void
    {
        $access_token = isset($token_data['access_token']) ? sanitize_text_field((string) $token_data['access_token']) : '';
        $refresh_token = isset($token_data['refresh_token']) ? sanitize_text_field((string) $token_data['refresh_token']) : '';
        $scope = isset($token_data['scope']) ? sanitize_text_field((string) $token_data['scope']) : '';
        $token_expires_at = isset($token_data['token_expires_at']) ? sanitize_text_field((string) $token_data['token_expires_at']) : '';
        $authorized_at = isset($token_data['authorized_at']) ? sanitize_text_field((string) $token_data['authorized_at']) : current_time('mysql');

        $this->upsert_setting('portal_oo_access_token', $access_token);
        if ($refresh_token !== '') {
            $this->upsert_setting('portal_oo_refresh_token', $refresh_token);
        }
        $this->upsert_setting('portal_oo_scope', $scope);
        $this->upsert_setting('portal_oo_token_expires_at', $token_expires_at);
        $this->upsert_setting('portal_oo_authorized_at', $authorized_at);
    }

    private function get_request_header_value(string $header_name): string
    {
        $normalized_name = strtoupper(str_replace('-', '_', $header_name));
        $server_key = 'HTTP_' . $normalized_name;
        if (isset($_SERVER[$server_key])) {
            return strtolower(trim((string) $_SERVER[$server_key]));
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strtolower((string) $key) === strtolower($header_name)) {
                        return strtolower(trim((string) $value));
                    }
                }
            }
        }

        return '';
    }

    private function load_otodom_advert_uuid(int $property_id, string $site_urn): string
    {
        $refs = $this->load_otodom_refs($property_id);
        $site_key = self::sanitize_site_urn($site_urn);
        if ($site_key === '' || ! isset($refs[$site_key]) || ! is_array($refs[$site_key])) {
            return '';
        }

        return sanitize_text_field((string) ($refs[$site_key]['advert_uuid'] ?? ''));
    }

    private function store_otodom_advert_uuid(int $property_id, string $site_urn, string $advert_uuid): bool
    {
        $site_key = self::sanitize_site_urn($site_urn);
        $uuid = sanitize_text_field($advert_uuid);
        if ($property_id <= 0 || $site_key === '' || $uuid === '') {
            return false;
        }

        $refs = $this->load_otodom_refs($property_id);
        $existing = isset($refs[$site_key]) && is_array($refs[$site_key]) ? $refs[$site_key] : [];
        $refs[$site_key] = $this->normalize_otodom_ref_row(array_merge($existing, [
            'advert_uuid' => $uuid,
            'updated_at' => current_time('mysql'),
            'last_synced_at' => current_time('mysql'),
        ]));

        return $this->write_otodom_refs($property_id, $refs);
    }

    private function remove_otodom_advert_uuid(int $property_id, string $site_urn): bool
    {
        $site_key = self::sanitize_site_urn($site_urn);
        if ($property_id <= 0 || $site_key === '') {
            return false;
        }

        $refs = $this->load_otodom_refs($property_id);
        if (! isset($refs[$site_key])) {
            return true;
        }

        $existing = is_array($refs[$site_key]) ? $refs[$site_key] : [];
        $refs[$site_key] = $this->normalize_otodom_ref_row(array_merge($existing, [
            'advert_uuid' => '',
            'updated_at' => current_time('mysql'),
            'last_synced_at' => current_time('mysql'),
        ]));
        return $this->write_otodom_refs($property_id, $refs);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function load_otodom_refs(int $property_id): array
    {
        if ($property_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = $this->tables['properties'];
        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT custom_fields_json
                FROM {$table}
                WHERE id = %d
                LIMIT 1",
                $property_id
            )
        );

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $custom_fields = json_decode($raw, true);
        if (! is_array($custom_fields)) {
            return [];
        }

        $refs_json = isset($custom_fields['_eocrm_portal_oo_refs']) ? (string) $custom_fields['_eocrm_portal_oo_refs'] : '';
        if ($refs_json === '') {
            return [];
        }

        $refs = json_decode($refs_json, true);
        if (! is_array($refs)) {
            return [];
        }

        $normalized = [];
        foreach ($refs as $site_urn => $site_data) {
            if (! is_string($site_urn) || ! is_array($site_data)) {
                continue;
            }

            $site_key = self::sanitize_site_urn($site_urn);
            if ($site_key === '') {
                continue;
            }

            $normalized_row = $this->normalize_otodom_ref_row($site_data);
            if (! empty($normalized_row)) {
                $normalized[$site_key] = $normalized_row;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, string>> $refs
     */
    private function write_otodom_refs(int $property_id, array $refs): bool
    {
        if ($property_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $this->tables['properties'];
        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT custom_fields_json
                FROM {$table}
                WHERE id = %d
                LIMIT 1",
                $property_id
            )
        );

        $custom_fields = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $custom_fields = $decoded;
            }
        }

        $normalized_refs = [];
        foreach ($refs as $site_urn => $site_data) {
            if (! is_string($site_urn) || ! is_array($site_data)) {
                continue;
            }

            $site_key = self::sanitize_site_urn($site_urn);
            if ($site_key === '') {
                continue;
            }

            $normalized_row = $this->normalize_otodom_ref_row($site_data);
            if (! empty($normalized_row)) {
                $normalized_refs[$site_key] = $normalized_row;
            }
        }

        if (empty($normalized_refs)) {
            unset($custom_fields['_eocrm_portal_oo_refs']);
        } else {
            $refs_json = wp_json_encode($normalized_refs);
            if (! is_string($refs_json) || $refs_json === '') {
                return false;
            }
            $custom_fields['_eocrm_portal_oo_refs'] = $refs_json;
        }

        $custom_fields_json = wp_json_encode($custom_fields);
        if (! is_string($custom_fields_json) || $custom_fields_json === '') {
            $custom_fields_json = '{}';
        }

        $updated = $wpdb->update(
            $table,
            [
                'custom_fields_json' => $custom_fields_json,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $property_id],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * @param array<string, mixed> $site_data
     * @return array<string, string>
     */
    private function normalize_otodom_ref_row(array $site_data): array
    {
        $normalized = [
            'advert_uuid' => sanitize_text_field((string) ($site_data['advert_uuid'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($site_data['updated_at'] ?? '')),
            'last_action_status' => sanitize_text_field((string) ($site_data['last_action_status'] ?? '')),
            'state_code' => sanitize_text_field((string) ($site_data['state_code'] ?? '')),
            'visible_in_profile' => ! empty($site_data['visible_in_profile']) ? '1' : '0',
            'url' => esc_url_raw((string) ($site_data['url'] ?? '')),
            'ttl' => sanitize_text_field((string) ($site_data['ttl'] ?? '')),
            'created_at' => sanitize_text_field((string) ($site_data['created_at'] ?? '')),
            'activated_at' => sanitize_text_field((string) ($site_data['activated_at'] ?? '')),
            'modified_at' => sanitize_text_field((string) ($site_data['modified_at'] ?? '')),
            'last_error' => sanitize_text_field((string) ($site_data['last_error'] ?? '')),
            'last_event_type' => sanitize_text_field((string) ($site_data['last_event_type'] ?? '')),
            'last_synced_at' => sanitize_text_field((string) ($site_data['last_synced_at'] ?? '')),
        ];

        $has_content = false;
        foreach ($normalized as $key => $value) {
            if ($key === 'visible_in_profile') {
                if ($value === '1') {
                    $has_content = true;
                    break;
                }
                continue;
            }

            if ($value !== '') {
                $has_content = true;
                break;
            }
        }

        return $has_content ? $normalized : [];
    }

    /**
     * @return array{success:bool, skipped?:bool, message:string}
     */
    private function send_to_noe(int $property_id, string $event): array
    {
        if ($property_id <= 0) {
            return [
                'success' => false,
                'message' => 'Brak poprawnego identyfikatora nieruchomosci.',
            ];
        }

        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $settings = self::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, self::TARGET_NOE);
        if ((string) ($settings['enabled'] ?? '0') !== '1') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Eksport na portal jest wylaczony.',
            ];
        }

        $ftp_login = trim((string) ($settings['ftp_login'] ?? ''));
        $ftp_password = (string) ($settings['ftp_password'] ?? '');
        if ($ftp_login === '' || $ftp_password === '') {
            return [
                'success' => false,
                'message' => 'Uzupelnij login i haslo FTP dla Nieruchomosci Online.',
            ];
        }

        $xml_result = $this->build_noe_xml($property_id, $event, $settings);
        if (is_wp_error($xml_result)) {
            return [
                'success' => false,
                'message' => $xml_result->get_error_message(),
            ];
        }

        $xml_file = isset($xml_result['xml_file']) ? (string) $xml_result['xml_file'] : '';
        $file_name = isset($xml_result['file_name']) ? (string) $xml_result['file_name'] : '';
        if ($xml_file === '' || $file_name === '' || ! file_exists($xml_file)) {
            return [
                'success' => false,
                'message' => 'Nie udalo sie wygenerowac pliku XML eksportu.',
            ];
        }

        try {
            $upload_result = $this->upload_file_via_ftp($xml_file, $file_name, $settings);
            if (! empty($upload_result['success'])) {
                return [
                    'success' => true,
                    'message' => 'Plik XML wyslany na serwer FTP: ' . $file_name,
                ];
            }
        } finally {
            $this->delete_file_safely($xml_file);
        }

        return [
            'success' => false,
            'message' => (string) ($upload_result['message'] ?? 'Nie udalo sie wyslac pliku XML na FTP.'),
        ];
    }

    /**
     * @return array{success:bool, skipped?:bool, message:string}
     */
    private function send_to_morizon_gratka(int $property_id, string $event): array
    {
        if ($property_id <= 0) {
            return [
                'success' => false,
                'message' => 'Brak poprawnego identyfikatora nieruchomosci.',
            ];
        }

        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $settings = self::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, self::TARGET_MORIZON_GRATKA);
        if ((string) ($settings['enabled'] ?? '0') !== '1') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Eksport na portal jest wylaczony.',
            ];
        }

        $ftp_login = trim((string) ($settings['ftp_login'] ?? ''));
        $ftp_password = (string) ($settings['ftp_password'] ?? '');
        if ($ftp_login === '' || $ftp_password === '') {
            return [
                'success' => false,
                'message' => 'Uzupelnij login i haslo FTP dla Morizon-Gratka.',
            ];
        }

        $archive_result = $this->build_morizon_gratka_archive($property_id, $event, $settings);
        if (is_wp_error($archive_result)) {
            return [
                'success' => false,
                'message' => $archive_result->get_error_message(),
            ];
        }

        $archive_file = isset($archive_result['archive_file']) ? (string) $archive_result['archive_file'] : '';
        $file_name = isset($archive_result['file_name']) ? (string) $archive_result['file_name'] : '';
        if ($archive_file === '' || $file_name === '' || ! file_exists($archive_file)) {
            return [
                'success' => false,
                'message' => 'Nie udalo sie wygenerowac paczki ZIP eksportu Morizon-Gratka.',
            ];
        }

        try {
            $upload_result = $this->upload_file_via_ftp($archive_file, $file_name, $settings);
            if (! empty($upload_result['success'])) {
                return [
                    'success' => true,
                    'message' => 'Paczka ZIP wyslana na serwer FTP: ' . $file_name,
                ];
            }
        } finally {
            $this->delete_file_safely($archive_file);
        }

        return [
            'success' => false,
            'message' => (string) ($upload_result['message'] ?? 'Nie udalo sie wyslac paczki ZIP na FTP.'),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{archive_file:string, file_name:string}|WP_Error
     */
    private function build_morizon_gratka_archive(int $property_id, string $event, array $settings)
    {
        if (! class_exists('ZipArchive')) {
            return new WP_Error('eocrm_mg_zip_ext', 'Brak rozszerzenia ZIP w PHP (ZipArchive).');
        }

        $xml_payload = $this->build_morizon_gratka_xml($property_id, $event, $settings);
        if (is_wp_error($xml_payload)) {
            return $xml_payload;
        }

        $xml_content = isset($xml_payload['xml_content']) ? (string) $xml_payload['xml_content'] : '';
        $image_files = isset($xml_payload['image_files']) && is_array($xml_payload['image_files']) ? $xml_payload['image_files'] : [];
        if ($xml_content === '') {
            return new WP_Error('eocrm_mg_xml_empty', 'Nie udalo sie wygenerowac XML dla Morizon-Gratka.');
        }

        $remote_zip_name = 'oferty_' . gmdate('YmdHis') . '.zip';
        $archive_file = $this->create_temp_file_path($remote_zip_name);
        if ($archive_file === '') {
            return new WP_Error('eocrm_mg_zip_temp', 'Nie udalo sie utworzyc pliku tymczasowego ZIP.');
        }

        $zip = new ZipArchive();
        $zip_open = $zip->open($archive_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($zip_open !== true) {
            $this->delete_file_safely($archive_file);
            return new WP_Error('eocrm_mg_zip_open', 'Nie udalo sie utworzyc archiwum ZIP eksportu.');
        }

        $zip->addFromString('oferty.xml', $xml_content);
        foreach ($image_files as $image_file) {
            if (! is_array($image_file)) {
                continue;
            }
            $source_path = isset($image_file['source_path']) ? (string) $image_file['source_path'] : '';
            $file_name = isset($image_file['file_name']) ? (string) $image_file['file_name'] : '';
            if ($source_path === '' || $file_name === '' || ! is_readable($source_path)) {
                continue;
            }
            $zip->addFile($source_path, $file_name);
        }
        $zip->close();

        if (! file_exists($archive_file) || (int) @filesize($archive_file) <= 0) {
            $this->delete_file_safely($archive_file);
            return new WP_Error('eocrm_mg_zip_write', 'Nie udalo sie zapisac paczki ZIP eksportu.');
        }

        return [
            'archive_file' => $archive_file,
            'file_name' => $remote_zip_name,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{xml_content:string, image_files:array<int, array<string, string>>}|WP_Error
     */
    private function build_morizon_gratka_xml(int $property_id, string $event, array $settings)
    {
        $include_inactive = $event === 'delete';
        $property = $this->get_property_row($property_id, $include_inactive);
        if (! is_array($property)) {
            return new WP_Error('eocrm_mg_property', 'Nie znaleziono nieruchomosci do eksportu na Morizon-Gratka.');
        }

        if ($event !== 'delete') {
            $is_active = isset($property['is_active']) ? (int) $property['is_active'] : 0;
            $export_portals = isset($property['export_portals']) ? (int) $property['export_portals'] : 0;
            if ($is_active !== 1 || $export_portals !== 1) {
                return new WP_Error('eocrm_mg_property_status', 'Nieruchomosc nie jest aktywna lub nie jest oznaczona do eksportu na portale.');
            }
        }

        $section_tab = $this->map_mg_section_tab((string) ($property['property_type'] ?? ''));
        $section_type = $this->map_mg_transaction_type((string) ($property['transaction_type'] ?? ''));
        if ($section_tab === '' || $section_type === '') {
            return new WP_Error('eocrm_mg_mapping', 'Nie mozna zmapowac rodzaju nieruchomosci lub typu transakcji do Morizon-Gratka.');
        }

        $offer_id = $this->normalize_offer_identifier((string) ($property['offer_number'] ?? ''), $property_id);
        $city_name = sanitize_text_field((string) ($property['city'] ?? ''));
        if ($event !== 'delete' && $city_name === '') {
            return new WP_Error('eocrm_mg_city', 'Brak miasta nieruchomosci. Morizon-Gratka wymaga poprawnej lokalizacji.');
        }

        $region_name = sanitize_text_field((string) ($settings['region_name'] ?? 'mazowieckie'));
        if ($region_name === '') {
            $region_name = 'mazowieckie';
        }

        $agent = $this->build_agent_payload($property, $settings);
        $agent_name = trim((string) ($agent['name'] ?? '') . ' ' . (string) ($agent['surname'] ?? ''));
        if ($agent_name === '') {
            $agent_name = 'Agent CRM';
        }
        $agent_email = sanitize_email((string) ($agent['email'] ?? ''));
        if ($event !== 'delete' && $agent_email === '') {
            return new WP_Error('eocrm_mg_email', 'Brak adresu e-mail agenta. Parametr agent_email jest wymagany przez Morizon-Gratka.');
        }
        $agent_phone = sanitize_text_field((string) ($agent['phone'] ?? ''));

        $price = $this->to_float($property['price'] ?? null);
        $area = $this->to_float($property['area'] ?? null);
        if ($area === null || $area <= 0) {
            $area = $this->to_float($property['plot_area'] ?? null);
        }
        if ($event !== 'delete' && ($price === null || $price <= 0 || $area === null || $area <= 0)) {
            return new WP_Error('eocrm_mg_required', 'Brak wymaganych danych oferty (cena i powierzchnia) do eksportu Morizon-Gratka.');
        }

        $rooms = $this->to_int($property['rooms'] ?? null);
        if ($section_tab === 'mieszkania' && ($rooms === null || $rooms <= 0) && $event !== 'delete') {
            $rooms = 1;
        }

        $description = trim((string) wp_strip_all_tags((string) ($property['description'] ?? ''), true));
        if ($description === '') {
            $description = 'Oferta nieruchomosci: ' . $offer_id;
        }

        if (! class_exists('DOMDocument')) {
            return new WP_Error('eocrm_mg_xml_dom', 'Brak rozszerzenia DOM w PHP (DOMDocument).');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('plik');
        $dom->appendChild($root);

        $information = sanitize_text_field((string) ($settings['information'] ?? 'Eksport ofert z Estate Office CRM'));
        if ($information === '') {
            $information = 'Eksport ofert z Estate Office CRM';
        }
        $agency_name = sanitize_text_field((string) ($settings['agency_name'] ?? ''));
        if ($agency_name === '') {
            $agency_name = sanitize_text_field((string) get_bloginfo('name'));
        }

        $export_mode = (string) ($settings['export_mode'] ?? 'incremental');
        $file_mode = $export_mode === 'full' ? 'calosc' : 'roznica';
        if ($event === 'delete') {
            $file_mode = 'roznica';
        }

        $this->append_text_node($dom, $root, 'informacje', $information);
        $this->append_text_node($dom, $root, 'agencja', $agency_name);
        $this->append_text_node($dom, $root, 'data', current_time('mysql'));
        $this->append_text_node($dom, $root, 'wersja', '0.4.5');
        $this->append_text_node($dom, $root, 'cel', 'oferty.net');
        $this->append_text_node($dom, $root, 'zawartosc_pliku', $file_mode);

        $offers_node = $root->appendChild($dom->createElement('lista_ofert'));
        $section_node = $offers_node->appendChild($dom->createElement('dzial'));
        $section_node->setAttribute('tab', $section_tab);
        $section_node->setAttribute('typ', $section_type);

        if ($event === 'delete') {
            $delete_node = $section_node->appendChild($dom->createElement('oferta_usun'));
            $this->append_text_node($dom, $delete_node, 'id', $offer_id);

            return [
                'xml_content' => (string) $dom->saveXML(),
                'image_files' => [],
            ];
        }

        $offer_node = $section_node->appendChild($dom->createElement('oferta'));
        $this->append_text_node($dom, $offer_node, 'id', $offer_id);

        $currency = $this->map_mg_currency((string) ($property['price_currency'] ?? 'PLN'));
        $price_node = $offer_node->appendChild($dom->createElement('cena', $this->format_float((float) $price, 2)));
        $price_node->setAttribute('waluta', $currency);

        $this->append_mg_param_node($dom, $offer_node, 'wojewodztwo', 'text', $region_name);
        $this->append_mg_param_node($dom, $offer_node, 'miasto', 'text', $city_name);

        $district = sanitize_text_field((string) ($property['district'] ?? ''));
        if ($district !== '') {
            $this->append_mg_param_node($dom, $offer_node, 'dzielnica', 'text', $district);
            $this->append_mg_param_node($dom, $offer_node, 'okolica', 'text', $district);
        }

        $street_name = sanitize_text_field((string) ($property['street'] ?? ''));
        $building_no = sanitize_text_field((string) ($property['building_no'] ?? ''));
        $street = trim($street_name . ($building_no !== '' ? ' ' . $building_no : ''));
        if ($street !== '') {
            $this->append_mg_param_node($dom, $offer_node, 'ulica', 'text', $street);
        }

        $postal_code = sanitize_text_field((string) ($property['postal_code'] ?? ''));
        if ($postal_code !== '') {
            $this->append_mg_param_node($dom, $offer_node, 'zip_code', 'text', $postal_code);
        }

        $this->append_mg_param_node($dom, $offer_node, 'dataaktualizacji', 'text', current_time('mysql'));
        $this->append_mg_param_node($dom, $offer_node, 'powierzchnia', 'real', $this->format_float((float) $area, 2));
        if ($rooms !== null && $rooms > 0) {
            $this->append_mg_param_node($dom, $offer_node, 'liczbapokoi', 'int', (string) $rooms);
        }

        $floor = $this->to_int($property['floor_no'] ?? null);
        if ($floor !== null) {
            $this->append_mg_param_node($dom, $offer_node, 'pietro', 'int', (string) $floor);
        }
        $floors_total = $this->to_int($property['floors_total'] ?? null);
        if ($floors_total !== null && $floors_total > 0) {
            $this->append_mg_param_node($dom, $offer_node, 'liczbapieter', 'int', (string) $floors_total);
        }
        $year_built = $this->to_int($property['year_built'] ?? null);
        if ($year_built !== null && $year_built > 0) {
            $this->append_mg_param_node($dom, $offer_node, 'rokbudowy', 'int', (string) $year_built);
        }

        $plot_area = $this->to_float($property['plot_area'] ?? null);
        if ($plot_area !== null && $plot_area > 0 && in_array($section_tab, ['domy', 'dzialki'], true)) {
            $this->append_mg_param_node($dom, $offer_node, 'powierzchniadzialki', 'real', $this->format_float($plot_area, 2));
        }

        if ($section_tab === 'domy') {
            $house_type = $this->map_house_type_to_mg((string) ($property['house_type'] ?? ''));
            if ($house_type !== '') {
                $this->append_mg_param_node($dom, $offer_node, 'typzabudowy', 'text', $house_type);
            }
        }
        if ($section_tab === 'dzialki') {
            $this->append_mg_param_node($dom, $offer_node, 'typdzialki', 'text', 'budowlana');
        }

        $latitude = $this->to_float($property['latitude'] ?? null);
        $longitude = $this->to_float($property['longitude'] ?? null);
        if ($latitude !== null && $longitude !== null) {
            $this->append_mg_param_node($dom, $offer_node, 'n_geo_y', 'text', $this->format_float($latitude, 8));
            $this->append_mg_param_node($dom, $offer_node, 'n_geo_x', 'text', $this->format_float($longitude, 8));
        }

        $this->append_mg_param_node($dom, $offer_node, 'opis', 'text', $description);
        $this->append_mg_param_node($dom, $offer_node, 'agent_nazwisko', 'text', $agent_name);
        $this->append_mg_param_node($dom, $offer_node, 'agent_email', 'text', $agent_email);
        if ($agent_phone !== '') {
            $this->append_mg_param_node($dom, $offer_node, 'agent_tel_biuro', 'text', $agent_phone);
            $this->append_mg_param_node($dom, $offer_node, 'agent_tel_kom', 'text', $agent_phone);
        }

        $is_exclusive = isset($property['is_exclusive']) ? (int) $property['is_exclusive'] === 1 : false;
        $no_commission = isset($property['no_commission']) ? (int) $property['no_commission'] === 1 : false;
        $this->append_mg_param_node($dom, $offer_node, 'wylacznosc', 'bool', $is_exclusive ? '1' : '0');
        $this->append_mg_param_node($dom, $offer_node, 'bezprowizji', 'bool', $no_commission ? '1' : '0');

        $media_json = $this->decode_json_assoc((string) ($property['media_json'] ?? ''));
        $virtual_walk = isset($media_json['virtual_walk_link']) ? esc_url_raw((string) $media_json['virtual_walk_link']) : '';
        $video_link = isset($media_json['video_link']) ? esc_url_raw((string) $media_json['video_link']) : '';
        $multimedia_link = $virtual_walk !== '' ? $virtual_walk : $video_link;
        if ($multimedia_link !== '') {
            $this->append_mg_param_node($dom, $offer_node, 'wirtualnawizyta', 'text', $multimedia_link);
        }

        $image_files = $this->load_property_photo_archive_payload($property_id);
        $photo_number = 1;
        foreach ($image_files as $image_file) {
            if ($photo_number > self::MAX_MEDIA_ITEMS_PER_TYPE) {
                break;
            }
            $file_name = isset($image_file['file_name']) ? (string) $image_file['file_name'] : '';
            if ($file_name === '') {
                continue;
            }
            $this->append_mg_param_node($dom, $offer_node, 'zdjecie' . (string) $photo_number, 'text', $file_name);
            $photo_number++;
        }

        return [
            'xml_content' => (string) $dom->saveXML(),
            'image_files' => $image_files,
        ];
    }

    private function append_mg_param_node(DOMDocument $dom, DOMElement $offer_node, string $name, string $type, string $value): void
    {
        $param_name = sanitize_key($name);
        if ($param_name === '') {
            return;
        }

        $param_type = strtolower(trim($type));
        if (! in_array($param_type, ['text', 'int', 'integer', 'float', 'real', 'bool'], true)) {
            $param_type = 'text';
        }

        $param_value = trim($value);
        if ($param_value === '') {
            return;
        }

        if ($param_type === 'bool') {
            $param_value = in_array(strtolower($param_value), ['1', 'true', 'tak', 'yes'], true) ? '1' : '0';
        }

        $param_node = $offer_node->appendChild($dom->createElement('param'));
        $param_node->setAttribute('nazwa', $param_name);
        $param_node->setAttribute('typ', $param_type);
        $param_node->appendChild($dom->createTextNode($param_value));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function load_property_photo_archive_payload(int $property_id): array
    {
        $result = [];
        if ($property_id <= 0) {
            return $result;
        }

        global $wpdb;
        $table = $this->tables['property_media'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT attachment_id, media_url, position
                FROM {$table}
                WHERE property_id = %d
                    AND media_type = 'photo'
                ORDER BY position ASC, id ASC
                LIMIT %d",
                $property_id,
                self::MAX_MEDIA_ITEMS_PER_TYPE
            ),
            ARRAY_A
        );

        if (! is_array($rows) || empty($rows)) {
            return $result;
        }

        $used_names = [];
        $index = 1;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            $media_url = isset($row['media_url']) ? esc_url_raw((string) $row['media_url']) : '';
            $source_path = '';
            if ($attachment_id > 0) {
                $resolved = $this->resolve_attachment_export_file($attachment_id);
                if ($resolved !== '' && is_readable($resolved)) {
                    $source_path = $resolved;
                }
            }

            if ($source_path === '') {
                continue;
            }

            $source_name = basename($source_path);
            if ($source_name === '' && $media_url !== '') {
                $source_name = basename((string) wp_parse_url($media_url, PHP_URL_PATH));
            }

            $extension = strtolower((string) pathinfo($source_name, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = 'jpg';
            }

            $base_name = 'zdjecie_' . (string) $property_id . '_' . (string) $index;
            $archive_name = sanitize_file_name($base_name . '.' . $extension);
            if ($archive_name === '') {
                $archive_name = 'zdjecie_' . (string) $property_id . '_' . (string) $index . '.jpg';
            }

            while (isset($used_names[$archive_name])) {
                $index++;
                $archive_name = sanitize_file_name('zdjecie_' . (string) $property_id . '_' . (string) $index . '.' . $extension);
            }
            $used_names[$archive_name] = true;

            $result[] = [
                'source_path' => $source_path,
                'file_name' => $archive_name,
            ];
            $index++;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{xml_file:string, file_name:string}|WP_Error
     */
    private function build_noe_xml(int $property_id, string $event, array $settings)
    {
        $include_inactive = $event === 'delete';
        $property = $this->get_property_row($property_id, $include_inactive);
        if (! is_array($property)) {
            return new WP_Error('eocrm_noe_property', 'Nie znaleziono nieruchomosci do eksportu na portal.');
        }

        if ($event !== 'delete') {
            $is_active = isset($property['is_active']) ? (int) $property['is_active'] : 0;
            $export_portals = isset($property['export_portals']) ? (int) $property['export_portals'] : 0;
            if ($is_active !== 1 || $export_portals !== 1) {
                return new WP_Error('eocrm_noe_property_status', 'Nieruchomosc nie jest aktywna lub nie jest oznaczona do eksportu na portale.');
            }
        }

        $agent = $this->build_agent_payload($property, $settings);
        $details = $this->build_noe_details_payload($property, $agent, $event, $settings);
        if (is_wp_error($details)) {
            return $details;
        }

        $xml_type = (string) ($settings['export_mode'] ?? 'incremental');
        if ($event === 'delete') {
            $xml_type = 'incremental';
        }
        if (! in_array($xml_type, ['full', 'incremental'], true)) {
            $xml_type = 'incremental';
        }

        $software_name = trim((string) ($settings['software_name'] ?? 'Estate Office CRM'));
        if ($software_name === '') {
            $software_name = 'Estate Office CRM';
        }

        if (! class_exists('DOMDocument')) {
            return new WP_Error('eocrm_noe_xml_dom', 'Brak rozszerzenia DOM w PHP (DOMDocument).');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('xml');
        $dom->appendChild($root);

        $export_node = $root->appendChild($dom->createElement('export'));
        $this->append_text_node($dom, $export_node, 'version', '2.0');
        $this->append_text_node($dom, $export_node, 'type', $xml_type);
        $this->append_text_node($dom, $export_node, 'softwareName', $software_name);

        $agents_node = $root->appendChild($dom->createElement('agents'));
        $agent_node = $agents_node->appendChild($dom->createElement('agent'));
        $this->append_text_node($dom, $agent_node, 'idAgent', (string) ($agent['id'] ?? '1'));
        $this->append_text_node($dom, $agent_node, 'isProAgentDeclaration', '0');
        $this->append_text_node($dom, $agent_node, 'name', (string) ($agent['name'] ?? 'Agent'));
        $this->append_text_node($dom, $agent_node, 'surname', (string) ($agent['surname'] ?? 'CRM'));
        $this->append_text_node($dom, $agent_node, 'phone', (string) ($agent['phone'] ?? '000000000'));
        $this->append_text_node($dom, $agent_node, 'email', (string) ($agent['email'] ?? 'kontakt@example.com'));
        $agent_node->appendChild($dom->createElement('specializations'));

        $ads_node = $root->appendChild($dom->createElement('ads'));
        $ad_node = $ads_node->appendChild($dom->createElement('ad'));

        $details_node = $ad_node->appendChild($dom->createElement('details'));
        foreach ($details as $field_name => $field_value) {
            if ($field_value === '' || $field_value === null) {
                continue;
            }

            $is_cdata = in_array($field_name, ['description', 'videoAdLink'], true);
            $this->append_text_node($dom, $details_node, (string) $field_name, (string) $field_value, $is_cdata);
        }

        if ($event !== 'delete') {
            $latitude = $this->to_float($property['latitude'] ?? null);
            $longitude = $this->to_float($property['longitude'] ?? null);
            if ($latitude !== null && $longitude !== null) {
                $map_node = $ad_node->appendChild($dom->createElement('map'));
                $this->append_text_node($dom, $map_node, 'mapLatitude', $this->format_float($latitude, 8));
                $this->append_text_node($dom, $map_node, 'mapLongitude', $this->format_float($longitude, 8));
            }

            $media_payload = $this->load_property_media_payload($property_id);
            if (! empty($media_payload['photos'])) {
                $photos_node = $ad_node->appendChild($dom->createElement('photos'));
                foreach ($media_payload['photos'] as $photo_item) {
                    if (! is_array($photo_item)) {
                        continue;
                    }
                    $file_name = (string) ($photo_item['file_name'] ?? '');
                    $file_content = (string) ($photo_item['file_content'] ?? '');
                    if ($file_name === '' || $file_content === '') {
                        continue;
                    }

                    $photo_node = $photos_node->appendChild($dom->createElement('photo'));
                    $this->append_text_node($dom, $photo_node, 'fileName', $file_name);
                    $this->append_text_node($dom, $photo_node, 'fileContent', $file_content);
                }
            }

            if (! empty($media_payload['plans'])) {
                $plans_node = $ad_node->appendChild($dom->createElement('plans'));
                foreach ($media_payload['plans'] as $plan_item) {
                    if (! is_array($plan_item)) {
                        continue;
                    }
                    $file_name = (string) ($plan_item['file_name'] ?? '');
                    $file_content = (string) ($plan_item['file_content'] ?? '');
                    if ($file_name === '' || $file_content === '') {
                        continue;
                    }

                    $plan_node = $plans_node->appendChild($dom->createElement('plan'));
                    $this->append_text_node($dom, $plan_node, 'fileName', $file_name);
                    $this->append_text_node($dom, $plan_node, 'fileContent', $file_content);
                }
            }
        }

        $timestamp = gmdate('Ymd_His');
        $safe_event = sanitize_key($event);
        $safe_event = $safe_event === '' ? 'update' : $safe_event;
        $file_name = 'eocrm_noe_' . $timestamp . '_p' . (string) $property_id . '_' . $safe_event . '.xml';
        $temp_file = $this->create_temp_file_path($file_name);
        if (! is_string($temp_file) || $temp_file === '') {
            return new WP_Error('eocrm_noe_xml', 'Nie udalo sie utworzyc pliku tymczasowego XML.');
        }

        $written = $dom->save($temp_file);
        if (! is_int($written) || $written <= 0) {
            $this->delete_file_safely($temp_file);
            return new WP_Error('eocrm_noe_xml', 'Nie udalo sie zapisac dokumentu XML dla eksportu.');
        }

        return [
            'xml_file' => $temp_file,
            'file_name' => $file_name,
        ];
    }

    private function create_temp_file_path(string $file_name): string
    {
        $safe_name = sanitize_file_name($file_name);
        if ($safe_name === '') {
            $safe_name = 'eocrm_export.xml';
        }

        if (function_exists('wp_tempnam')) {
            $temp = wp_tempnam($safe_name);
            if (is_string($temp) && $temp !== '') {
                return $temp;
            }
        }

        if (defined('ABSPATH')) {
            $wp_file = trailingslashit(ABSPATH) . 'wp-admin/includes/file.php';
            if (file_exists($wp_file)) {
                require_once $wp_file;
                if (function_exists('wp_tempnam')) {
                    $temp = wp_tempnam($safe_name);
                    if (is_string($temp) && $temp !== '') {
                        return $temp;
                    }
                }
            }
        }

        $tmp_dir = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        if (is_string($tmp_dir) && $tmp_dir !== '' && is_dir($tmp_dir)) {
            $candidate = trailingslashit($tmp_dir) . 'eocrm-' . wp_generate_uuid4() . '-' . $safe_name;
            $created = @file_put_contents($candidate, '');
            if (is_int($created)) {
                return $candidate;
            }
        }

        $uploads = wp_get_upload_dir();
        $upload_base = (is_array($uploads) && isset($uploads['basedir']) && is_string($uploads['basedir']))
            ? (string) $uploads['basedir']
            : '';
        if ($upload_base !== '' && is_dir($upload_base)) {
            $candidate = trailingslashit($upload_base) . 'eocrm-temp-' . wp_generate_uuid4() . '-' . $safe_name;
            $created = @file_put_contents($candidate, '');
            if (is_int($created)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $settings
     * @return array<string, string>|WP_Error
     */
    private function build_noe_details_payload(array $property, array $agent, string $event, array $settings)
    {
        $category_id = $this->map_category((string) ($property['property_type'] ?? ''));
        $ad_type_id = $this->map_ad_type((string) ($property['transaction_type'] ?? ''));
        if ($category_id <= 0 || $ad_type_id <= 0) {
            return new WP_Error('eocrm_noe_mapping', 'Nie mozna zmapowac rodzaju nieruchomosci lub typu transakcji do NOE.');
        }

        $offer_number = sanitize_text_field((string) ($property['offer_number'] ?? ''));
        if ($offer_number === '') {
            $offer_number = 'EOCRM-' . (string) ($property['id'] ?? '');
        }

        $region_id = isset($settings['region_id']) ? (int) $settings['region_id'] : 7;
        if ($region_id < 1 || $region_id > 16) {
            $region_id = 7;
        }
        $district_name = $this->first_non_empty_text([
            (string) ($property['county'] ?? ''),
            (string) ($property['district'] ?? ''),
            (string) ($property['city'] ?? ''),
        ]);
        if ($district_name === '') {
            $district_name = 'brak';
        }

        $commune_name = $this->first_non_empty_text([
            (string) ($property['gmina'] ?? ''),
            (string) ($property['city'] ?? ''),
        ]);
        if ($commune_name === '') {
            $commune_name = 'brak';
        }

        $city_name = sanitize_text_field((string) ($property['city'] ?? ''));
        if ($city_name === '') {
            $city_name = 'brak';
        }

        $street_name = sanitize_text_field((string) ($property['street'] ?? ''));
        $building_no = sanitize_text_field((string) ($property['building_no'] ?? ''));
        $street = trim($street_name . ($building_no !== '' ? ' ' . $building_no : ''));

        $area = $this->to_float($property['area'] ?? null);
        if ($area === null || $area <= 0) {
            $area = $this->to_float($property['plot_area'] ?? null);
        }

        $price = $this->to_float($property['price'] ?? null);
        $price_per_m2 = $this->to_float($property['price_per_m2'] ?? null);
        if (($price_per_m2 === null || $price_per_m2 <= 0) && $price !== null && $area !== null && $area > 0) {
            $price_per_m2 = $price / $area;
        }

        $description = trim((string) wp_strip_all_tags((string) ($property['description'] ?? ''), true));
        if ($description === '') {
            $description = 'Oferta nieruchomosci: ' . $offer_number;
        }

        if ($area === null || $area <= 0 || $price === null || $price <= 0 || $price_per_m2 === null || $price_per_m2 <= 0) {
            if ($event !== 'delete') {
                return new WP_Error('eocrm_noe_required', 'Brak wymaganych danych oferty (powierzchnia, cena, cena za m2) do eksportu NOE.');
            }
            if ($area === null || $area <= 0) {
                $area = 1.0;
            }
            if ($price === null || $price <= 0) {
                $price = 1.0;
            }
            if ($price_per_m2 === null || $price_per_m2 <= 0) {
                $price_per_m2 = 1.0;
            }
        }

        if ($event === 'delete') {
            return [
                'id' => (string) (int) ($property['id'] ?? 0),
                'action' => 'delete',
            ];
        }

        $details = [
            'id' => (string) (int) ($property['id'] ?? 0),
            'sign' => $offer_number,
            'action' => $event,
            'idRegion' => (string) $region_id,
            'districtName' => $district_name,
            'communeName' => $commune_name,
            'cityName' => $city_name,
            'idCategory' => (string) $category_id,
            'idAdType' => (string) $ad_type_id,
            'idAgent' => (string) (int) ($agent['id'] ?? 1),
            'area' => $this->format_float($area, 2),
            'price' => $this->format_float($price, 2),
            'pricePM' => $this->format_float($price_per_m2, 2),
            'description' => $description,
        ];

        if ($street !== '') {
            $details['street'] = $street;
        }

        $quarter = sanitize_text_field((string) ($property['district'] ?? ''));
        if ($quarter !== '') {
            $details['quarterName'] = $quarter;
        }

        $currency_id = $this->map_currency((string) ($property['price_currency'] ?? 'PLN'));
        if ($currency_id > 0) {
            $details['idCurrency'] = (string) $currency_id;
        }

        $details['idMarketType'] = '1';

        if (! empty($property['no_commission'])) {
            $details['noAgentProvision'] = '1';
        }

        $rooms = $this->to_int($property['rooms'] ?? null);
        if ($rooms !== null && $rooms > 0) {
            $details['rooms'] = (string) $rooms;
        }

        $floor = $this->to_int($property['floor_no'] ?? null);
        if ($floor !== null) {
            $details['floor'] = (string) $floor;
        }

        $total_floors = $this->to_int($property['floors_total'] ?? null);
        if ($total_floors !== null && $total_floors > 0) {
            $details['totalFloors'] = (string) $total_floors;
        }

        $year_built = $this->to_int($property['year_built'] ?? null);
        if ($year_built !== null && $year_built > 0) {
            $details['yearBuilt'] = (string) $year_built;
        }

        $ownership_id = $this->map_ownership((string) ($property['legal_status'] ?? ''));
        if ($ownership_id > 0) {
            $details['idOwnershipType'] = (string) $ownership_id;
        }

        if (isset($property['no_land_registry'])) {
            $details['isKw'] = ((int) $property['no_land_registry'] === 1) ? '2' : '1';
        }

        $admin_rent = $this->to_float($property['admin_rent'] ?? null);
        if ($admin_rent !== null && $admin_rent > 0) {
            $details['rent'] = $this->format_float($admin_rent, 2);
        }

        $media_json = $this->decode_json_assoc((string) ($property['media_json'] ?? ''));
        $video_link = isset($media_json['video_link']) ? esc_url_raw((string) $media_json['video_link']) : '';
        if ($video_link !== '') {
            $details['videoAdLink'] = $video_link;
        }

        return $details;
    }
    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function build_agent_payload(array $property, array $settings): array
    {
        $owner_user_id = isset($property['owner_user_id']) ? (int) $property['owner_user_id'] : 0;
        $agent_id = $owner_user_id > 0 ? $owner_user_id : 1;

        $display_name = '';
        $user_email = '';
        if ($owner_user_id > 0) {
            $user = get_user_by('id', $owner_user_id);
            if ($user instanceof WP_User) {
                $display_name = sanitize_text_field((string) $user->display_name);
                $user_email = sanitize_email((string) $user->user_email);
            }
        }

        $first_name = '';
        $last_name = '';
        if ($owner_user_id > 0) {
            $first_name = sanitize_text_field((string) get_user_meta($owner_user_id, 'first_name', true));
            $last_name = sanitize_text_field((string) get_user_meta($owner_user_id, 'last_name', true));
        }

        if ($first_name === '' && $display_name !== '') {
            $parts = preg_split('/\s+/', trim($display_name));
            if (is_array($parts) && ! empty($parts)) {
                $first_name = (string) array_shift($parts);
                $last_name = trim(implode(' ', $parts));
            }
        }

        if ($first_name === '') {
            $first_name = 'Agent';
        }
        if ($last_name === '') {
            $last_name = 'CRM';
        }

        $phone = '000000000';
        $email = $user_email;

        if ($owner_user_id > 0) {
            global $wpdb;
            $table = $this->tables['agent_profiles'];
            $profile = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT phone, email FROM {$table} WHERE user_id = %d LIMIT 1",
                    $owner_user_id
                ),
                ARRAY_A
            );
            if (is_array($profile)) {
                $profile_phone = sanitize_text_field((string) ($profile['phone'] ?? ''));
                $profile_email = sanitize_email((string) ($profile['email'] ?? ''));
                if ($profile_phone !== '') {
                    $phone = $profile_phone;
                }
                if ($profile_email !== '') {
                    $email = $profile_email;
                }
            }
        }

        if ($phone === '000000000') {
            $fallback_phone = sanitize_text_field((string) ($settings['default_agent_phone'] ?? ''));
            if ($fallback_phone !== '') {
                $phone = $fallback_phone;
            }
        }

        if ($email === '') {
            $fallback_email = sanitize_email((string) ($settings['default_agent_email'] ?? ''));
            if ($fallback_email !== '') {
                $email = $fallback_email;
            }
        }

        if ($email === '') {
            $email = sanitize_email((string) get_option('admin_email', 'kontakt@example.com'));
        }

        return [
            'id' => $agent_id,
            'name' => $first_name,
            'surname' => $last_name,
            'phone' => $phone,
            'email' => $email,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get_property_row(int $property_id, bool $include_inactive): ?array
    {
        global $wpdb;
        $table = $this->tables['properties'];
        if ($include_inactive) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                    $property_id
                ),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d AND is_active = 1 LIMIT 1",
                    $property_id
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{photos:array<int, array<string, string>>, plans:array<int, array<string, string>>}
     */
    private function load_property_media_payload(int $property_id): array
    {
        $result = [
            'photos' => [],
            'plans' => [],
        ];

        if ($property_id <= 0) {
            return $result;
        }

        global $wpdb;
        $table = $this->tables['property_media'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT media_type, attachment_id, media_url, position
                FROM {$table}
                WHERE property_id = %d
                    AND media_type IN ('photo', 'floor_plan')
                ORDER BY media_type ASC, position ASC, id ASC",
                $property_id
            ),
            ARRAY_A
        );

        if (! is_array($rows) || empty($rows)) {
            return $result;
        }

        $photo_index = 1;
        $plan_index = 1;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $media_type = sanitize_key((string) ($row['media_type'] ?? ''));
            if (! in_array($media_type, ['photo', 'floor_plan'], true)) {
                continue;
            }

            if ($media_type === 'photo' && count($result['photos']) >= self::MAX_MEDIA_ITEMS_PER_TYPE) {
                continue;
            }
            if ($media_type === 'floor_plan' && count($result['plans']) >= self::MAX_MEDIA_ITEMS_PER_TYPE) {
                continue;
            }

            $attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            $media_url = isset($row['media_url']) ? esc_url_raw((string) $row['media_url']) : '';
            $index = $media_type === 'photo' ? $photo_index : $plan_index;
            $payload = $this->build_media_item_payload($property_id, $attachment_id, $media_url, $media_type, $index);
            if (! is_array($payload)) {
                continue;
            }

            if ($media_type === 'photo') {
                $result['photos'][] = $payload;
                $photo_index++;
            } else {
                $result['plans'][] = $payload;
                $plan_index++;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>|null
     */
    private function build_media_item_payload(int $property_id, int $attachment_id, string $media_url, string $media_type, int $position): ?array
    {
        $file_path = '';
        $source_name = '';

        if ($attachment_id > 0) {
            $best_file = $this->resolve_attachment_export_file($attachment_id);
            if ($best_file !== '' && file_exists($best_file)) {
                $file_path = $best_file;
                $source_name = basename($best_file);
            }
        }

        if ($file_path === '' && $media_url !== '') {
            $source_name = basename((string) wp_parse_url($media_url, PHP_URL_PATH));
        }

        if ($file_path === '') {
            return null;
        }

        $file_size = @filesize($file_path);
        if (is_int($file_size) && $file_size > self::MAX_MEDIA_FILE_BYTES) {
            return null;
        }

        $raw_content = @file_get_contents($file_path);
        if (! is_string($raw_content) || $raw_content === '') {
            return null;
        }

        $encoded = base64_encode($raw_content);
        unset($raw_content);
        if ($encoded === '') {
            return null;
        }

        $extension = strtolower((string) pathinfo($source_name, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'jpg';
        }

        $prefix = $media_type === 'photo' ? 'ad' : 'plan';
        $file_name = sanitize_file_name($prefix . '_' . (string) $property_id . '_' . (string) $position . '.' . $extension);

        return [
            'file_name' => $file_name,
            'file_content' => $encoded,
        ];
    }

    private function resolve_attachment_export_file(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        $attached_file = get_attached_file($attachment_id);
        $fallback_file = is_string($attached_file) ? $attached_file : '';
        if ($fallback_file === '' || ! file_exists($fallback_file)) {
            return '';
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (! is_array($metadata)) {
            return $fallback_file;
        }

        $relative_main_file = isset($metadata['file']) ? (string) $metadata['file'] : '';
        $sizes = isset($metadata['sizes']) && is_array($metadata['sizes']) ? $metadata['sizes'] : [];
        if ($relative_main_file === '' || empty($sizes)) {
            return $fallback_file;
        }

        $uploads = wp_get_upload_dir();
        $base_dir = (is_array($uploads) && isset($uploads['basedir']) && is_string($uploads['basedir']))
            ? (string) $uploads['basedir']
            : '';
        if ($base_dir === '') {
            return $fallback_file;
        }

        $relative_dir = trim((string) dirname($relative_main_file), '/\\.');
        $preferred_sizes = ['large', 'medium_large', 'medium', 'thumbnail'];
        foreach ($preferred_sizes as $size_key) {
            $size_data = isset($sizes[$size_key]) && is_array($sizes[$size_key]) ? $sizes[$size_key] : null;
            $size_file = $size_data !== null && isset($size_data['file']) ? (string) $size_data['file'] : '';
            if ($size_file === '') {
                continue;
            }

            $candidate = trailingslashit($base_dir);
            if ($relative_dir !== '') {
                $candidate .= $relative_dir . '/';
            }
            $candidate .= $size_file;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $fallback_file;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{success:bool, message:string}
     */
    private function upload_file_via_ftp(string $local_file, string $file_name, array $settings): array
    {
        if (! function_exists('ftp_connect')) {
            return ['success' => false, 'message' => 'Brak rozszerzenia FTP w PHP (ftp_connect).'];
        }

        if ($local_file === '' || ! is_readable($local_file)) {
            return ['success' => false, 'message' => 'Brak gotowego pliku do wysylki.'];
        }

        $ftp_host = trim((string) ($settings['ftp_host'] ?? ''));
        $ftp_port = isset($settings['ftp_port']) ? (int) $settings['ftp_port'] : 21;
        $ftp_login = trim((string) ($settings['ftp_login'] ?? ''));
        $ftp_password = (string) ($settings['ftp_password'] ?? '');
        $ftp_path = self::sanitize_ftp_path((string) ($settings['ftp_path'] ?? ''));
        $use_ssl = (string) ($settings['use_ssl'] ?? '0') === '1';
        $timeout = isset($settings['ftp_timeout']) ? (int) $settings['ftp_timeout'] : 20;

        $warning = '';
        $connection = null;
        if ($use_ssl && function_exists('ftp_ssl_connect')) {
            $connection = $this->with_suppressed_warnings(static function () use ($ftp_host, $ftp_port, $timeout) {
                return ftp_ssl_connect($ftp_host, $ftp_port, $timeout);
            }, $warning);
        }

        if (! $connection) {
            $connection = $this->with_suppressed_warnings(static function () use ($ftp_host, $ftp_port, $timeout) {
                return ftp_connect($ftp_host, $ftp_port, $timeout);
            }, $warning);
        }

        if (! $connection) {
            return ['success' => false, 'message' => 'Nie mozna polaczyc z serwerem FTP: ' . ($warning !== '' ? $warning : $ftp_host)];
        }

        $login_ok = (bool) $this->with_suppressed_warnings(static function () use ($connection, $ftp_login, $ftp_password) {
            return ftp_login($connection, $ftp_login, $ftp_password);
        }, $warning);

        if (! $login_ok) {
            $this->close_ftp_connection($connection);
            return ['success' => false, 'message' => 'Blad logowania FTP: ' . ($warning !== '' ? $warning : 'nieprawidlowe dane logowania')];
        }

        $this->with_suppressed_warnings(static function () use ($connection) {
            ftp_pasv($connection, true);
        }, $warning);

        if ($ftp_path !== '') {
            $changed = (bool) $this->with_suppressed_warnings(static function () use ($connection, $ftp_path) {
                return ftp_chdir($connection, $ftp_path);
            }, $warning);

            if (! $changed) {
                $this->close_ftp_connection($connection);
                return ['success' => false, 'message' => 'Nie mozna przejsc do katalogu FTP `' . $ftp_path . '`'];
            }
        }

        $upload_ok = (bool) $this->with_suppressed_warnings(static function () use ($connection, $file_name, $local_file) {
            return ftp_put($connection, $file_name, $local_file, FTP_BINARY);
        }, $warning);

        $this->close_ftp_connection($connection);

        if (! $upload_ok) {
            return ['success' => false, 'message' => 'Nie udalo sie wyslac pliku XML na FTP: ' . ($warning !== '' ? $warning : $file_name)];
        }

        return ['success' => true, 'message' => 'Wyslano plik na FTP.'];
    }

    /**
     * @deprecated kept for backward compatibility
     * @param array<string, mixed> $settings
     * @return array{success:bool, message:string}
     */
    private function upload_xml_via_ftp(string $xml_file, string $file_name, array $settings): array
    {
        return $this->upload_file_via_ftp($xml_file, $file_name, $settings);
    }

    /**
     * @param mixed $connection
     */
    private function close_ftp_connection($connection): void
    {
        $unused_warning = '';
        $this->with_suppressed_warnings(static function () use ($connection) {
            if ($connection) {
                ftp_close($connection);
            }
        }, $unused_warning);
    }

    private function trigger_async_cron(): void
    {
        if (function_exists('spawn_cron')) {
            $unused_warning = '';
            $this->with_suppressed_warnings(static function (): void {
                spawn_cron(time());
            }, $unused_warning);
            return;
        }

        $cron_url = add_query_arg(
            'doing_wp_cron',
            rawurlencode(sprintf('%.22F', microtime(true))),
            site_url('wp-cron.php')
        );

        wp_remote_post(
            $cron_url,
            [
                'timeout' => 0.01,
                'blocking' => false,
                'sslverify' => apply_filters('https_local_ssl_verify', false),
            ]
        );
    }

    /**
     * @param callable():mixed $callback
     * @return mixed
     */
    private function with_suppressed_warnings(callable $callback, ?string &$warning = null)
    {
        $warning = '';
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            unset($errno);
            $warning = $errstr;
            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_json_assoc(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function map_mg_section_tab(string $property_type): string
    {
        $value = strtoupper(trim($property_type));
        return match ($value) {
            'MIESZKANIE' => 'mieszkania',
            'DOM' => 'domy',
            'DZIALKA' => 'dzialki',
            'LOKAL_HU' => 'lokale',
            default => '',
        };
    }

    private function map_mg_transaction_type(string $transaction_type): string
    {
        $value = strtoupper(trim($transaction_type));
        return match ($value) {
            'SPRZEDAZ' => 'sprzedaz',
            'WYNAJEM' => 'wynajem',
            default => '',
        };
    }

    private function map_mg_currency(string $currency): string
    {
        $value = strtoupper(trim($currency));
        return in_array($value, ['PLN', 'EUR', 'USD'], true) ? $value : 'PLN';
    }

    private function normalize_offer_identifier(string $offer_number, int $property_id): string
    {
        $raw = trim($offer_number);
        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '', $raw);
        if (! is_string($normalized) || $normalized === '') {
            $normalized = 'EOCRM' . (string) max(1, $property_id);
        }

        return substr($normalized, 0, 80);
    }

    private function map_house_type_to_mg(string $house_type): string
    {
        $value = strtoupper(trim($house_type));
        return match ($value) {
            'WOLNOSTOJACY' => 'WOLNO STOJACY',
            'BLIZNIAK' => 'BLIZNIAK',
            'SZEREGOWIEC' => 'SZEREGOWY',
            'WIELORODZINNY' => 'WIELORODZINNY',
            default => '',
        };
    }

    private function map_category(string $property_type): int
    {
        $value = strtoupper(trim($property_type));
        return match ($value) {
            'MIESZKANIE' => 1,
            'DOM' => 2,
            'DZIALKA' => 3,
            'LOKAL_HU' => 4,
            default => 0,
        };
    }

    private function map_ad_type(string $transaction_type): int
    {
        $value = strtoupper(trim($transaction_type));
        return match ($value) {
            'SPRZEDAZ' => 1,
            'WYNAJEM' => 2,
            default => 0,
        };
    }

    private function map_currency(string $currency): int
    {
        $value = strtoupper(trim($currency));
        return match ($value) {
            'PLN' => 1,
            'EUR' => 2,
            'USD' => 3,
            default => 1,
        };
    }

    private function map_ownership(string $legal_status): int
    {
        $value = strtoupper(trim($legal_status));
        return match ($value) {
            'WLASNOSC' => 1,
            'WSPOLWLASNOSC' => 2,
            'SPOLDZIELCZE_WLASNOSCIOWE_PRAWO_DO_LOKALU' => 3,
            'DZIERZAWA' => 4,
            default => 0,
        };
    }

    private static function region_name_from_id(int $region_id): string
    {
        $map = [
            1 => 'dolnoslaskie',
            2 => 'kujawsko-pomorskie',
            3 => 'lubelskie',
            4 => 'lubuskie',
            5 => 'lodzkie',
            6 => 'malopolskie',
            7 => 'mazowieckie',
            8 => 'opolskie',
            9 => 'podkarpackie',
            10 => 'podlaskie',
            11 => 'pomorskie',
            12 => 'slaskie',
            13 => 'swietokrzyskie',
            14 => 'warminsko-mazurskie',
            15 => 'wielkopolskie',
            16 => 'zachodniopomorskie',
        ];

        return isset($map[$region_id]) ? $map[$region_id] : $map[7];
    }

    private function first_non_empty_text(array $values): string
    {
        foreach ($values as $value) {
            $text = sanitize_text_field((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function format_float(float $value, int $precision = 2): string
    {
        return number_format($value, $precision, '.', '');
    }

    private function to_float($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', (string) $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function to_int($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function append_text_node(DOMDocument $dom, DOMElement $parent, string $name, string $value, bool $as_cdata = false): void
    {
        $node = $dom->createElement($name);
        if ($as_cdata) {
            $node->appendChild($dom->createCDATASection($value));
        } else {
            $node->appendChild($dom->createTextNode($value));
        }
        $parent->appendChild($node);
    }

    private function delete_file_safely(string $file_path): void
    {
        $normalized_path = wp_normalize_path($file_path);
        if ($normalized_path === '' || ! file_exists($normalized_path)) {
            return;
        }

        wp_delete_file($normalized_path);
    }

    private function upsert_setting(string $key, string $value): void
    {
        global $wpdb;

        $table = $this->tables['settings'];
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                FROM {$table}
                WHERE setting_key = %s
                LIMIT 1",
                $key
            )
        );

        $payload = [
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing_id > 0) {
            $wpdb->update(
                $table,
                [
                    'setting_value' => $payload['setting_value'],
                    'updated_at' => $payload['updated_at'],
                ],
                ['id' => $existing_id],
                ['%s', '%s'],
                ['%d']
            );
            return;
        }

        $wpdb->insert(
            $table,
            $payload,
            ['%s', '%s', '%s']
        );
    }

    private function get_setting(string $key): string
    {
        global $wpdb;

        $table = $this->tables['settings'];
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT setting_value
                FROM {$table}
                WHERE setting_key = %s
                LIMIT 1",
                $key
            )
        );

        return is_string($value) ? $value : '';
    }
}

