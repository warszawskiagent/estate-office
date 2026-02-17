<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Admin
{
    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_post_estateoffice_save_settings', [self::class, 'handle_save_settings']);
        add_action('admin_post_estateoffice_create_agent', [self::class, 'handle_create_agent']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('Estate Office CRM', 'estateoffice'),
            __('Estate Office CRM', 'estateoffice'),
            'manage_options',
            'estateoffice-crm',
            [self::class, 'render_about_page'],
            'dashicons-building',
            26
        );

        add_submenu_page(
            'estateoffice-crm',
            __('About', 'estateoffice'),
            __('About', 'estateoffice'),
            'manage_options',
            'estateoffice-crm',
            [self::class, 'render_about_page']
        );

        add_submenu_page(
            'estateoffice-crm',
            __('Agenci', 'estateoffice'),
            __('Agenci', 'estateoffice'),
            'manage_options',
            'estateoffice-crm-agents',
            [self::class, 'render_agents_page']
        );

        add_submenu_page(
            'estateoffice-crm',
            __('Ustawienia', 'estateoffice'),
            __('Ustawienia', 'estateoffice'),
            'manage_options',
            'estateoffice-crm-settings',
            [self::class, 'render_settings_page']
        );

        add_submenu_page(
            'estateoffice-crm',
            __('Licencja', 'estateoffice'),
            __('Licencja', 'estateoffice'),
            'manage_options',
            'estateoffice-crm-license',
            [self::class, 'render_license_page']
        );
    }

    public static function handle_save_settings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'estateoffice'));
        }

        check_admin_referer('estateoffice_save_settings');

        $stringFields = [
            'google_maps_api_key',
            'watermark_url',
            'office_logo_url',
        ];

        foreach ($stringFields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash((string) $_POST[$field])) : '';
            EstateOffice_Settings::set($field, $value);
        }

        $jsonFields = [
            'property_custom_fields',
            'agreement_custom_fields',
            'client_custom_fields',
        ];

        foreach ($jsonFields as $field) {
            $raw = isset($_POST[$field]) ? wp_unslash((string) $_POST[$field]) : '[]';
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $decoded = [];
            }

            $safe = [];
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $safe[] = [
                    'key' => sanitize_key((string) ($item['key'] ?? '')),
                    'label' => sanitize_text_field((string) ($item['label'] ?? '')),
                    'type' => sanitize_key((string) ($item['type'] ?? 'text')),
                    'required' => ! empty($item['required']),
                ];
            }

            EstateOffice_Settings::set($field, wp_json_encode($safe));
        }

        $redirect = add_query_arg([
            'page' => 'estateoffice-crm-settings',
            'updated' => '1',
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_create_agent(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'estateoffice'));
        }

        check_admin_referer('estateoffice_create_agent');

        $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash((string) $_POST['first_name'])) : '';
        $lastName = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash((string) $_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash((string) $_POST['phone'])) : '';
        $bio = isset($_POST['bio']) ? sanitize_textarea_field(wp_unslash((string) $_POST['bio'])) : '';

        if (! is_email($email)) {
            self::redirect_agents_with_notice('error', 'Niepoprawny adres e-mail.');
        }

        if (email_exists($email)) {
            self::redirect_agents_with_notice('error', 'Podany e-mail jest już używany przez innego użytkownika.');
        }

        $login = sanitize_user(current(explode('@', $email)), true);
        if ($login === '') {
            $login = 'agent-' . wp_generate_password(8, false, false);
        }

        if (username_exists($login)) {
            $login .= '-' . wp_generate_password(4, false, false);
        }

        $password = wp_generate_password(20, true, true);

        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => trim($firstName . ' ' . $lastName),
            'role' => 'estateoffice_agent',
        ]);

        if (is_wp_error($userId)) {
            self::redirect_agents_with_notice('error', 'Nie udało się utworzyć agenta.');
        }

        update_user_meta($userId, 'estateoffice_phone', $phone);
        update_user_meta($userId, 'description', $bio);
        update_user_meta($userId, 'estateoffice_must_change_password', '1');

        wp_mail(
            $email,
            __('Dane logowania agenta EstateOffice CRM', 'estateoffice'),
            sprintf(
                "Konto agenta zostało utworzone.\nLogin: %s\nHasło tymczasowe: %s",
                $login,
                $password
            )
        );

        self::redirect_agents_with_notice('success', 'Agent został utworzony poprawnie.');
    }

    private static function redirect_agents_with_notice(string $status, string $message): void
    {
        $redirect = add_query_arg([
            'page' => 'estateoffice-crm-agents',
            'status' => $status,
            'message' => rawurlencode($message),
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public static function render_about_page(): void
    {
        include ESTATEOFFICE_PATH . 'templates/admin-about.php';
    }

    public static function render_agents_page(): void
    {
        include ESTATEOFFICE_PATH . 'templates/admin-agents.php';
    }

    public static function render_settings_page(): void
    {
        include ESTATEOFFICE_PATH . 'templates/admin-settings.php';
    }

    public static function render_license_page(): void
    {
        include ESTATEOFFICE_PATH . 'templates/admin-license.php';
    }
}
