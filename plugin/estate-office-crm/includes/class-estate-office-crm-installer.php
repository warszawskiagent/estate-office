<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Installer
{
    public const ROLE_AGENT = 'eocrm_agent';
    public const ROLE_MANAGER = 'eocrm_manager';
    public const OPTION_VERSION = 'eocrm_version';
    public const OPTION_PAGE_IDS = 'eocrm_page_ids';
    public const OPTION_DEFAULT_AGENT_USER_ID = 'eocrm_default_agent_user_id';
    public const TRANSIENT_NEW_AGENT_CREDENTIALS = 'eocrm_new_agent_credentials';

    public static function activate(): void
    {
        self::create_or_update_schema();
        self::create_roles();
        self::create_default_agent_user();
        self::create_pages();
        self::seed_default_settings();
        self::ensure_scheduled_events();

        update_option(self::OPTION_VERSION, EOCRM_VERSION, false);
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('eocrm_daily_cleanup');
        wp_clear_scheduled_hook('eocrm_daily_wibor_refresh');
        wp_clear_scheduled_hook(EstateOfficeCRM_Portal_Export::CRON_HOOK);
        flush_rewrite_rules(false);
    }

    public static function maybe_upgrade(): void
    {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_cron()) {
            return;
        }

        $stored_version = (string) get_option(self::OPTION_VERSION, '');
        if ($stored_version === EOCRM_VERSION) {
            return;
        }

        self::create_or_update_schema();
        self::create_roles();
        self::create_default_agent_user();
        self::create_pages();
        self::seed_default_settings();
        self::ensure_scheduled_events();

        update_option(self::OPTION_VERSION, EOCRM_VERSION, false);
        flush_rewrite_rules(false);
    }

    private static function create_or_update_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sql_statements = EstateOfficeCRM_DB_Schema::get_sql($wpdb->prefix, $charset_collate);

        foreach ($sql_statements as $sql) {
            dbDelta($sql);
        }

        self::run_schema_compat_migrations();
    }

    private static function run_schema_compat_migrations(): void
    {
        global $wpdb;

        $tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
        $transactions_table = $tables['transactions'];
        $searches_table = $tables['searches'];

        // dbDelta does not always relax an existing NOT NULL column, so keep this migration explicit.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WP prefix.
        $transaction_date_column = $wpdb->get_row("SHOW COLUMNS FROM {$transactions_table} LIKE 'transaction_date'", ARRAY_A);
        if (is_array($transaction_date_column) && strtoupper((string) ($transaction_date_column['Null'] ?? '')) !== 'YES') {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WP prefix.
            $wpdb->query("ALTER TABLE {$transactions_table} MODIFY transaction_date DATE NULL");
        }

        // Multi-type searches store comma-separated enum codes; older installs used VARCHAR(30).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WP prefix.
        $search_property_type_column = $wpdb->get_row("SHOW COLUMNS FROM {$searches_table} LIKE 'property_type'", ARRAY_A);
        if (is_array($search_property_type_column) && stripos((string) ($search_property_type_column['Type'] ?? ''), 'varchar(120)') === false) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WP prefix.
            $wpdb->query("ALTER TABLE {$searches_table} MODIFY property_type VARCHAR(120) NULL");
        }
    }

    private static function create_roles(): void
    {
        add_role(
            self::ROLE_AGENT,
            __('Agent Nieruchomosci', 'estate-office-crm'),
            [
                'read' => true,
                'upload_files' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
            ]
        );

        add_role(
            self::ROLE_MANAGER,
            __('Menedzer Nieruchomosci', 'estate-office-crm'),
            [
                'read' => true,
                'upload_files' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
            ]
        );

        foreach ([self::ROLE_AGENT, self::ROLE_MANAGER] as $role_key) {
            $role = get_role($role_key);
            if (! $role instanceof WP_Role) {
                continue;
            }

            foreach (EstateOfficeCRM_Plugin::crm_caps() as $cap) {
                $role->add_cap($cap);
            }

            foreach (EstateOfficeCRM_Plugin::admin_caps() as $cap) {
                if ($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
        }

        $admin_role = get_role('administrator');
        if ($admin_role instanceof WP_Role) {
            foreach (array_merge(EstateOfficeCRM_Plugin::crm_caps(), EstateOfficeCRM_Plugin::admin_caps()) as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    private static function create_default_agent_user(): void
    {
        $existing_user_id = (int) get_option(self::OPTION_DEFAULT_AGENT_USER_ID, 0);
        if ($existing_user_id > 0 && get_user_by('id', $existing_user_id) instanceof WP_User) {
            return;
        }

        $existing_agents = get_users([
            'role' => self::ROLE_AGENT,
            'number' => 1,
            'fields' => 'ids',
        ]);

        if (! empty($existing_agents)) {
            update_option(self::OPTION_DEFAULT_AGENT_USER_ID, (int) $existing_agents[0], false);
            self::ensure_agent_profile((int) $existing_agents[0]);
            return;
        }

        $username = self::find_available_username([
            'agent',
            'agent_nieruchomosci',
            'estateoffice_agent',
        ]);

        if ($username === '') {
            return;
        }

        $email = self::build_available_email($username);
        $password = wp_generate_password(20, true, true);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => 'Domyslny Agent CRM',
            'role' => self::ROLE_AGENT,
        ]);

        if (is_wp_error($user_id) || (int) $user_id <= 0) {
            return;
        }

        update_option(self::OPTION_DEFAULT_AGENT_USER_ID, (int) $user_id, false);
        self::ensure_agent_profile((int) $user_id);

        set_transient(
            self::TRANSIENT_NEW_AGENT_CREDENTIALS,
            [
                'username' => $username,
                'password' => $password,
                'created_at' => current_time('mysql'),
            ],
            DAY_IN_SECONDS * 7
        );
    }

    /**
     * @param string[] $candidates
     */
    private static function find_available_username(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = sanitize_user($candidate, true);
            if ($candidate === '') {
                continue;
            }

            if (! username_exists($candidate)) {
                return $candidate;
            }
        }

        $base = 'eocrm_agent';
        for ($i = 1; $i <= 99; $i++) {
            $candidate = $base . $i;
            if (! username_exists($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function build_available_email(string $username): string
    {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $host = 'example.local';
        }

        $host = strtolower((string) preg_replace('/[^a-z0-9.-]/', '', $host));
        if ($host === '') {
            $host = 'example.local';
        }

        $email = $username . '@' . $host;
        if (! email_exists($email)) {
            return $email;
        }

        for ($i = 1; $i <= 99; $i++) {
            $candidate = $username . $i . '@' . $host;
            if (! email_exists($candidate)) {
                return $candidate;
            }
        }

        return wp_generate_password(8, false) . '@' . $host;
    }

    private static function ensure_agent_profile(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        global $wpdb;
        $tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
        $table = $tables['agent_profiles'];

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        if ($exists) {
            return;
        }

        $user = get_user_by('id', $user_id);
        $now = current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'email' => $user instanceof WP_User ? (string) $user->user_email : '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    private static function create_pages(): void
    {
        $saved_page_ids = get_option(self::OPTION_PAGE_IDS, []);
        if (! is_array($saved_page_ids)) {
            $saved_page_ids = [];
        }

        $pages = [
            'crm' => [
                'title' => 'CRM',
                'slug' => 'crm',
                'content' => '[eocrm_crm section="dashboard"]',
                'parent' => 0,
                'status' => 'publish',
            ],
            'properties' => [
                'title' => 'Nieruchomosci CRM',
                'slug' => 'crm-nieruchomosci',
                'content' => '[eocrm_crm section="properties"]',
                'parent_ref' => 'crm',
                'status' => 'publish',
            ],
            'searches' => [
                'title' => 'Poszukiwania CRM',
                'slug' => 'crm-poszukiwania',
                'content' => '[eocrm_crm section="searches"]',
                'parent_ref' => 'crm',
                'status' => 'publish',
            ],
            'agreements' => [
                'title' => 'Umowy CRM',
                'slug' => 'crm-umowy',
                'content' => '[eocrm_crm section="agreements"]',
                'parent_ref' => 'crm',
                'status' => 'publish',
            ],
            'transactions' => [
                'title' => 'Transakcje CRM',
                'slug' => 'crm-transakcje',
                'content' => '[eocrm_crm section="transactions"]',
                'parent_ref' => 'crm',
                'status' => 'publish',
            ],
            'clients' => [
                'title' => 'Klienci CRM',
                'slug' => 'crm-klienci',
                'content' => '[eocrm_crm section="clients"]',
                'parent_ref' => 'crm',
                'status' => 'publish',
            ],
            'sale_offers' => [
                'title' => 'Oferty na Sprzedaz',
                'slug' => 'oferty-sprzedaz',
                'content' => '[eocrm_offers transaction="SPRZEDAZ"]',
                'parent' => 0,
                'status' => 'publish',
            ],
            'rent_offers' => [
                'title' => 'Oferty na Wynajem',
                'slug' => 'oferty-wynajem',
                'content' => '[eocrm_offers transaction="WYNAJEM"]',
                'parent' => 0,
                'status' => 'publish',
            ],
            'offices_agents' => [
                'title' => 'Biura i Agenci',
                'slug' => 'biura-i-agenci',
                'content' => '[eocrm_offices_agents]',
                'parent' => 0,
                'status' => 'publish',
            ],
            'agent_login' => [
                'title' => 'Panel logowania Agenta',
                'slug' => 'panel-logowania-agenta',
                'content' => '[eocrm_agent_login]',
                'parent' => 0,
                'status' => 'publish',
            ],
        ];

        $page_ids = [];

        foreach ($pages as $key => $page) {
            $parent_id = isset($page['parent_ref'])
                ? (int) ($page_ids[$page['parent_ref']] ?? ($saved_page_ids[(string) $page['parent_ref']] ?? 0))
                : (int) ($page['parent'] ?? 0);
            $existing_page_id = isset($saved_page_ids[$key]) ? (int) $saved_page_ids[$key] : 0;
            $page_id = self::upsert_page(
                (string) $page['title'],
                (string) $page['slug'],
                (string) $page['content'],
                $parent_id,
                (string) ($page['status'] ?? 'publish'),
                (string) $key,
                $existing_page_id
            );

            if ($page_id > 0) {
                $page_ids[$key] = $page_id;
            }
        }

        if (! empty($page_ids)) {
            update_option(self::OPTION_PAGE_IDS, $page_ids, false);
        }

        self::deactivate_obsolete_system_page(
            isset($saved_page_ids['dashboard']) ? (int) $saved_page_ids['dashboard'] : 0,
            'crm-pulpit',
            'dashboard'
        );
    }

    private static function upsert_page(string $title, string $slug, string $content, int $parent_id, string $post_status, string $page_key, int $existing_page_id): int
    {
        $allowed_statuses = ['publish', 'private', 'draft', 'pending', 'future'];
        if (! in_array($post_status, $allowed_statuses, true)) {
            $post_status = 'publish';
        }

        $existing = null;

        if ($existing_page_id > 0) {
            $by_saved_id = get_post($existing_page_id);
            if ($by_saved_id instanceof WP_Post && $by_saved_id->post_type === 'page') {
                $existing = $by_saved_id;
            }
        }

        if (! ($existing instanceof WP_Post) && $page_key !== '') {
            $key_matches = get_posts([
                'post_type' => 'page',
                'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
                'posts_per_page' => 1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_key' => '_eocrm_system_page_key',
                'meta_value' => $page_key,
            ]);

            if (is_array($key_matches) && ! empty($key_matches)) {
                $by_key_id = (int) $key_matches[0];
                $by_key = get_post($by_key_id);
                if ($by_key instanceof WP_Post && $by_key->post_type === 'page') {
                    $existing = $by_key;
                }
            }
        }

        if (! ($existing instanceof WP_Post)) {
            $existing = get_page_by_path($slug, OBJECT, 'page');
        }

        if ($existing instanceof WP_Post) {
            $page_id = (int) $existing->ID;
            $existing_content = (string) $existing->post_content;
            $synced_content = self::sync_page_shortcode_content($existing_content, $content);

            if ($synced_content !== $existing_content) {
                wp_update_post(
                    wp_slash([
                        'ID' => $page_id,
                        'post_content' => $synced_content,
                    ]),
                    true
                );
            }
        } else {
            $page_id = wp_insert_post(
                wp_slash([
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_status' => $post_status,
                    'post_type' => 'page',
                    'post_parent' => $parent_id,
                    'post_content' => $content,
                    'post_author' => get_current_user_id() ?: 1,
                ]),
                true
            );

            if (is_wp_error($page_id)) {
                return 0;
            }
        }

        $page_id = (int) $page_id;
        if ($page_id <= 0) {
            return 0;
        }

        update_post_meta($page_id, '_eocrm_system_page', '1');
        if ($page_key !== '') {
            update_post_meta($page_id, '_eocrm_system_page_key', $page_key);
        }

        self::deactivate_duplicate_system_pages($page_id, $page_key);

        return $page_id;
    }

    private static function sync_page_shortcode_content(string $existing_content, string $required_shortcode): string
    {
        $existing_content = (string) $existing_content;
        $required_shortcode = trim($required_shortcode);

        if ($required_shortcode === '') {
            return $existing_content;
        }

        if (trim($existing_content) === '') {
            return $required_shortcode;
        }

        if (strpos($existing_content, $required_shortcode) !== false) {
            return $existing_content;
        }

        if (preg_match('/\[eocrm_[^\]]+\]/', $existing_content) === 1) {
            $replaced = preg_replace('/\[eocrm_[^\]]+\]/', $required_shortcode, $existing_content, 1);
            if (is_string($replaced) && $replaced !== '') {
                return $replaced;
            }
        }

        return rtrim($existing_content) . "\n\n" . $required_shortcode;
    }

    private static function deactivate_obsolete_system_page(int $saved_page_id, string $slug, string $page_key): void
    {
        $page_ids = [];

        if ($saved_page_id > 0) {
            $page_ids[] = $saved_page_id;
        }

        $by_slug = get_page_by_path($slug, OBJECT, 'page');
        if ($by_slug instanceof WP_Post) {
            $page_ids[] = (int) $by_slug->ID;
        }

        if ($page_key !== '') {
            $key_matches = get_posts([
                'post_type' => 'page',
                'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => '_eocrm_system_page_key',
                'meta_value' => $page_key,
            ]);

            if (is_array($key_matches)) {
                foreach ($key_matches as $matched_id) {
                    $matched_id = (int) $matched_id;
                    if ($matched_id > 0) {
                        $page_ids[] = $matched_id;
                    }
                }
            }
        }

        $page_ids = array_values(array_unique($page_ids));
        foreach ($page_ids as $page_id) {
            wp_update_post(
                wp_slash([
                    'ID' => $page_id,
                    'post_status' => 'draft',
                ]),
                true
            );
        }
    }

    private static function deactivate_duplicate_system_pages(int $keep_page_id, string $page_key): void
    {
        if ($keep_page_id <= 0) {
            return;
        }

        $duplicate_ids = [];

        if ($page_key !== '') {
            $key_matches = get_posts([
                'post_type' => 'page',
                'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => '_eocrm_system_page_key',
                'meta_value' => $page_key,
            ]);

            if (is_array($key_matches)) {
                foreach ($key_matches as $matched_id) {
                    $matched_id = (int) $matched_id;
                    if ($matched_id > 0 && $matched_id !== $keep_page_id) {
                        $duplicate_ids[] = $matched_id;
                    }
                }
            }
        }

        $duplicate_ids = array_values(array_unique($duplicate_ids));
        foreach ($duplicate_ids as $duplicate_id) {
            wp_update_post(
                wp_slash([
                    'ID' => $duplicate_id,
                    'post_status' => 'draft',
                ]),
                true
            );

            update_post_meta($duplicate_id, '_eocrm_replaced_by', (string) $keep_page_id);
        }
    }

    private static function seed_default_settings(): void
    {
        global $wpdb;

        $tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
        $table = $tables['settings'];
        $now = current_time('mysql');

        $default_office_name = sanitize_text_field((string) get_bloginfo('name'));
        if ($default_office_name === '') {
            $default_office_name = 'Estate Office CRM';
        }

        $defaults = [
            'google_maps_api_key' => '',
            'watermark_attachment_id' => '',
            'office_logo_attachment_id' => '',
            'office_name' => $default_office_name,
            'mortgage_advisor_enabled' => '1',
            'mortgage_advisor_logo_size_percent' => '70',
            'mortgage_advisor_logo_align_x' => 'center',
            'mortgage_advisor_attachment_id' => '',
            'mortgage_advisor_email' => '',
            'offices_agents_page_title' => 'Biura i Agenci',
            'offices_agents_page_intro' => 'Poznaj nasz zespol doradcow nieruchomosci i sprawdz, kto prowadzi oferty w Twojej okolicy.',
            'language' => self::detect_default_language(),
            'frontend_width_mode' => 'screen',
            'frontend_width_percent' => '100',
            'public_offers_per_page' => '6',
            'new_offer_duration_days' => '7',
            'sold_rented_www_retention_days' => '30',
            'sold_rented_portals_retention_days' => '14',
            'developer_access_enabled' => '0',
            'offer_template_variant' => 'modern_v1',
            'offer_template_sale_heading' => 'Oferty na Sprzedaz',
            'offer_template_rent_heading' => 'Oferty na Wynajem',
            'offer_template_cta_label' => 'Zobacz oferte',
            'offer_template_show_offer_number' => '1',
            'offer_template_show_offer_number_in_title' => '1',
            'offer_template_open_in_new_window' => '0',
            'unit_currency' => 'PLN',
            'unit_area' => 'm2',
            'unit_land_area' => 'm2',
            'unit_distance' => 'km',
            'property_additional_fields_json' => wp_json_encode([]),
        ];

        $defaults = array_merge($defaults, EstateOfficeCRM_Numbering::default_settings());
        $defaults = array_merge($defaults, EstateOfficeCRM_Portal_Export::default_settings());

        foreach ($defaults as $setting_key => $setting_value) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE setting_key = %s LIMIT 1",
                    $setting_key
                )
            );

            if ($exists) {
                continue;
            }

            $wpdb->insert(
                $table,
                [
                    'setting_key' => $setting_key,
                    'setting_value' => (string) $setting_value,
                    'updated_at' => $now,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }

    private static function detect_default_language(): string
    {
        $supported_languages = class_exists('EstateOfficeCRM_I18n')
            ? EstateOfficeCRM_I18n::supported_languages()
            : ['pl_PL', 'en_US', 'de_DE', 'uk_UA'];

        $wp_locale = function_exists('determine_locale')
            ? (string) determine_locale()
            : (string) get_locale();

        if (in_array($wp_locale, $supported_languages, true)) {
            return $wp_locale;
        }

        return 'en_US';
    }

    private static function ensure_scheduled_events(): void
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
}
