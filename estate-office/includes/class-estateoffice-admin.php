<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Admin
{
    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
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
