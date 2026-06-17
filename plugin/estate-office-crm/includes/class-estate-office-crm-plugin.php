<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Plugin
{
    public function run(): void
    {
        add_action('init', ['EstateOfficeCRM_Installer', 'maybe_upgrade'], 1);
        add_action('init', [$this, 'register_caps_for_runtime']);
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this, 'ensure_scheduled_events']);
        add_filter('show_admin_bar', [$this, 'filter_admin_bar_visibility']);

        // Inject alternative CRM frontend style late so it overrides the default frontend.css
        // even when the base is enqueued during shortcode rendering (the_content).
        add_action('wp_head', [$this, 'print_minimal_style_if_selected'], 999);
        add_action('wp_footer', [$this, 'print_minimal_style_if_selected'], 1);

        add_action('eocrm_daily_cleanup', [$this, 'run_daily_cleanup']);
        add_action('admin_notices', [$this, 'render_new_agent_notice']);
        add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta_links'], 10, 2);

        $license = new EstateOfficeCRM_License();
        $license->register_hooks();

        $portal_export = new EstateOfficeCRM_Portal_Export();
        $portal_export->register_hooks();

        $pages = new EstateOfficeCRM_Pages();
        add_action('eocrm_daily_wibor_refresh', [$pages, 'refresh_wibor_rates_cache']);
        add_action('init', [$pages, 'handle_crm_xml_export'], 1);
        add_action('init', [$pages, 'handle_crm_pdf_export'], 1);
        add_action('init', [$pages, 'handle_frontend_agents_offices_actions'], 1);
        add_action('init', [$pages, 'register_shortcodes']);
        if (! is_admin()) {
            add_filter('wp_nav_menu_objects', [$pages, 'filter_public_menu_items'], 10, 2);
            add_filter('wp_list_pages_excludes', [$pages, 'filter_public_page_list_excludes'], 10, 1);
            add_filter('render_block', [$pages, 'filter_public_navigation_blocks'], 10, 2);
        }

        $clients = new EstateOfficeCRM_Clients();
        $clients->register_hooks();

        $agreements = new EstateOfficeCRM_Agreements();
        $agreements->register_hooks();

        $properties = new EstateOfficeCRM_Properties();
        $properties->register_hooks();

        $searches = new EstateOfficeCRM_Searches();
        $searches->register_hooks();

        $transactions = new EstateOfficeCRM_Transactions();
        $transactions->register_hooks();

        if (is_admin()) {
            $admin = new EstateOfficeCRM_Admin();
            add_action('admin_menu', [$admin, 'register_menu']);
            add_action('admin_init', [$admin, 'handle_actions']);
            add_action('admin_enqueue_scripts', [$admin, 'enqueue_assets']);
            add_action('wp_ajax_eocrm_crop_agent_photo', [$admin, 'handle_crop_agent_photo_ajax']);
        }
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function add_plugin_row_meta_links(array $links, string $file): array
    {
        if ($file !== plugin_basename(EOCRM_FILE)) {
            return $links;
        }

        // WordPress automatycznie dodaje link "Odwiedz witryne wtyczki" gdy `Plugin URI`
        // jest zdefiniowane. Mamy juz wlasny link "Estate Office CRM" prowadzacy do
        // tej samej strony, wiec usuwamy duplikat (rozpoznajemy go po wartosci PluginURI).
        $plugin_uri = 'https://estateofficecrm.pl/';
        $links = array_values(array_filter($links, static function ($entry) use ($plugin_uri): bool {
            if (! is_string($entry)) {
                return true;
            }
            return strpos($entry, $plugin_uri . '"') === false
                && strpos($entry, $plugin_uri . '\'') === false;
        }));

        $links[] = '<a href="https://estateofficecrm.pl/" target="_blank" rel="noopener noreferrer">Estate Office CRM</a>';
        $links[] = '<a href="https://estateofficecrm.pl/?page_id=204" target="_blank" rel="noopener noreferrer">Regulamin abonamentu</a>';
        $links[] = '<a href="https://estateofficecrm.pl/?page_id=205" target="_blank" rel="noopener noreferrer">EULA</a>';

        return $links;
    }

    /**
     * @return string[]
     */
    public static function crm_caps(): array
    {
        return [
            'eocrm_access_crm',
            'eocrm_manage_clients',
            'eocrm_manage_agreements',
            'eocrm_manage_properties',
            'eocrm_manage_searches',
            'eocrm_manage_transactions',
        ];
    }

    /**
     * @return string[]
     */
    public static function admin_caps(): array
    {
        return [
            'eocrm_manage_agents',
            'eocrm_manage_settings',
            'eocrm_delete_records',
            'eocrm_manage_license',
        ];
    }

    public function register_caps_for_runtime(): void
    {
        $admin = get_role('administrator');
        if ($admin instanceof WP_Role) {
            foreach (array_merge(self::crm_caps(), self::admin_caps()) as $cap) {
                if (! $admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }

        foreach ([EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER] as $role_key) {
            $role = get_role($role_key);
            if (! $role instanceof WP_Role) {
                continue;
            }

            foreach (self::crm_caps() as $cap) {
                if (! $role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }

            foreach (self::admin_caps() as $cap) {
                if ($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
        }
    }

    public function register_assets(): void
    {
        wp_register_style('eocrm-frontend', EOCRM_URL . 'assets/css/frontend.css', [], EOCRM_VERSION);
        wp_register_script('eocrm-frontend', EOCRM_URL . 'assets/js/frontend.js', [], EOCRM_VERSION, true);

        // Optional alternative CRM frontend style, layered on top of the default frontend.css.
        wp_register_style('eocrm-frontend-minimal', EOCRM_URL . 'assets/css/frontend-minimal.css', ['eocrm-frontend'], EOCRM_VERSION);

        wp_register_style('eocrm-admin', EOCRM_URL . 'assets/css/admin.css', [], EOCRM_VERSION);
        wp_register_script('eocrm-admin', EOCRM_URL . 'assets/js/admin.js', ['jquery'], EOCRM_VERSION, true);
    }

    /**
     * Output the minimal frontend stylesheet link IF developer access is active
     * and the user picked the minimal style. We emit it at wp_head priority 999
     * and wp_footer priority 1 so it covers both early-enqueued and shortcode-
     * driven (late) renders of the CRM. Each guard ensures the tag is printed
     * exactly once per request.
     */
    public function print_minimal_style_if_selected(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        if (self::get_active_crm_style() !== 'minimal') {
            return;
        }
        // Only useful when the base style is queued for this request.
        if (! wp_style_is('eocrm-frontend', 'enqueued')
            && ! wp_style_is('eocrm-frontend', 'done')
            && ! wp_style_is('eocrm-frontend', 'to_do')
        ) {
            return;
        }
        $url = EOCRM_URL . 'assets/css/frontend-minimal.css?ver=' . rawurlencode(EOCRM_VERSION);
        echo "\n" . '<link rel="stylesheet" id="eocrm-frontend-minimal-css" href="' . esc_url($url) . '" media="all">' . "\n";
        $printed = true;
    }

    /**
     * Return the currently active CRM frontend style key (default | minimal).
     * Reads from the plugin settings table; the new default for any unset /
     * unrecognized value is 'minimal'.
     */
    public static function get_active_crm_style(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        global $wpdb;
        $tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
        $settings_table = isset($tables['settings']) ? (string) $tables['settings'] : '';
        if ($settings_table === '') {
            return $cached = 'minimal';
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared, internal table name.
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT setting_value FROM {$settings_table} WHERE setting_key = %s LIMIT 1",
                'crm_style'
            )
        );
        $value = is_string($value) ? $value : '';
        if (! in_array($value, ['default', 'minimal'], true)) {
            $value = 'minimal';
        }

        return $cached = $value;
    }

    public function ensure_scheduled_events(): void
    {
        if (! wp_next_scheduled('eocrm_daily_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'eocrm_daily_cleanup');
        }

        if (! wp_next_scheduled('eocrm_daily_wibor_refresh')) {
            $next_refresh = strtotime('today 12:15', current_time('timestamp'));
            if (! is_int($next_refresh) || $next_refresh <= current_time('timestamp')) {
                $next_refresh = strtotime('tomorrow 12:15', current_time('timestamp'));
            }
            if (! is_int($next_refresh) || $next_refresh <= 0) {
                $next_refresh = time() + 2 * HOUR_IN_SECONDS;
            }
            wp_schedule_event($next_refresh, 'daily', 'eocrm_daily_wibor_refresh');
        }

        if (! wp_next_scheduled(EstateOfficeCRM_Portal_Export::CRON_HOOK)) {
            wp_schedule_event(time() + 10 * MINUTE_IN_SECONDS, 'hourly', EstateOfficeCRM_Portal_Export::CRON_HOOK);
        }
    }

    public function run_daily_cleanup(): void
    {
        global $wpdb;

        $tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
        $properties_table = $tables['properties'];
        $now = current_time('mysql');
        $www_retention_days = $this->sanitize_export_retention_days_setting($this->get_setting_value($tables, 'sold_rented_www_retention_days'), 30);
        $portal_retention_days = $this->sanitize_export_retention_days_setting($this->get_setting_value($tables, 'sold_rented_portals_retention_days'), 14);

        $expired_new_offer_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, tags_json
                FROM {$properties_table}
                WHERE is_new_offer = 1
                AND new_offer_expires_at IS NOT NULL
                AND new_offer_expires_at < %s",
                $now
            ),
            ARRAY_A
        );

        if (is_array($expired_new_offer_rows)) {
            foreach ($expired_new_offer_rows as $expired_new_offer_row) {
                $property_id = isset($expired_new_offer_row['id']) ? (int) $expired_new_offer_row['id'] : 0;
                if ($property_id <= 0) {
                    continue;
                }

                $tags = json_decode((string) ($expired_new_offer_row['tags_json'] ?? ''), true);
                $tags = is_array($tags) ? array_values(array_diff(array_map('strval', $tags), ['nowa_oferta'])) : [];

                $wpdb->update(
                    $properties_table,
                    [
                        'tags_json' => wp_json_encode($tags),
                        'is_new_offer' => 0,
                        'new_offer_expires_at' => null,
                        'updated_at' => $now,
                    ],
                    ['id' => $property_id]
                );
            }
        }

        $expired_www_ids = $this->find_expired_sold_rented_export_ids($properties_table, 'export_www', $www_retention_days);
        if (! empty($expired_www_ids)) {
            $placeholders = implode(', ', array_fill(0, count($expired_www_ids), '%d'));
            $sql = "UPDATE {$properties_table}
                SET export_www = 0,
                    updated_at = %s
                WHERE id IN ({$placeholders})";
            $params = array_merge([$now], $expired_www_ids);
            $wpdb->query($wpdb->prepare($sql, $params));
        }

        $expired_portal_ids = $this->find_expired_sold_rented_export_ids($properties_table, 'export_portals', $portal_retention_days);
        if (! empty($expired_portal_ids)) {
            $placeholders = implode(', ', array_fill(0, count($expired_portal_ids), '%d'));
            $sql = "UPDATE {$properties_table}
                SET export_portals = 0,
                    updated_at = %s
                WHERE id IN ({$placeholders})";
            $params = array_merge([$now], $expired_portal_ids);
            $wpdb->query($wpdb->prepare($sql, $params));

            $portal_export = new EstateOfficeCRM_Portal_Export();
            foreach ($expired_portal_ids as $property_id) {
                $portal_export->queue_property((int) $property_id, 'delete');
            }
        }
    }

    private function sanitize_export_retention_days_setting(string $value, int $default = 30): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return max(0, min(365, $default));
        }

        $days = (int) $raw;
        if ($days < 0) {
            return 0;
        }
        if ($days > 365) {
            return 365;
        }

        return $days;
    }

    /**
     * @param array<string, string> $tables
     */
    private function get_setting_value(array $tables, string $key): string
    {
        global $wpdb;

        $table = $tables['settings'] ?? '';
        if ($table === '') {
            return '';
        }

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1",
                $key
            )
        );

        return is_string($value) ? $value : '';
    }

    /**
     * @return int[]
     */
    private function find_expired_sold_rented_export_ids(string $properties_table, string $export_column, int $retention_days): array
    {
        global $wpdb;

        if (! in_array($export_column, ['export_www', 'export_portals'], true)) {
            return [];
        }

        $sql = "SELECT id
            FROM {$properties_table}
            WHERE is_active = 1
                AND {$export_column} = 1
                AND (is_sold = 1 OR is_rented = 1)";
        $params = [];

        if ($retention_days > 0) {
            $cutoff = current_datetime()->modify('-' . $retention_days . ' days')->format('Y-m-d H:i:s');
            $sql .= ' AND updated_at < %s';
            $params[] = $cutoff;
        }

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL uses plugin-owned table names and controlled where fragments.
        $ids = $wpdb->get_col($sql);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $ids)));
    }

    public function filter_admin_bar_visibility(bool $show): bool
    {
        if (! is_user_logged_in() || current_user_can('manage_options')) {
            return $show;
        }

        $user = wp_get_current_user();
        $roles = $user instanceof WP_User && is_array($user->roles) ? $user->roles : [];
        if (
            in_array(EstateOfficeCRM_Installer::ROLE_AGENT, $roles, true)
            || in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true)
        ) {
            return false;
        }

        return $show;
    }

    public function render_new_agent_notice(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        $credentials = get_transient(EstateOfficeCRM_Installer::TRANSIENT_NEW_AGENT_CREDENTIALS);
        if (! is_array($credentials)) {
            return;
        }

        $username = isset($credentials['username']) ? sanitize_text_field((string) $credentials['username']) : '';
        $password = isset($credentials['password']) ? sanitize_text_field((string) $credentials['password']) : '';

        if ($username === '' || $password === '') {
            delete_transient(EstateOfficeCRM_Installer::TRANSIENT_NEW_AGENT_CREDENTIALS);
            return;
        }

        echo '<div class="notice notice-success"><p><strong>Estate Office CRM:</strong> utworzono domyslnego uzytkownika Agent.</p>';
        echo '<p>Login: <code>' . esc_html($username) . '</code> | Haslo: <code>' . esc_html($password) . '</code></p>';
        echo '<p>Po pierwszym logowaniu zmien haslo dla bezpieczenstwa danych CRM.</p></div>';

        delete_transient(EstateOfficeCRM_Installer::TRANSIENT_NEW_AGENT_CREDENTIALS);
    }
}
