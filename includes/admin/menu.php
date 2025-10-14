<?php

declare(strict_types=1);

namespace EstateOffice\Admin;

defined('ABSPATH') || exit;

use EstateOffice\Admin\Pages\AboutPage;
use EstateOffice\Admin\Pages\AgentsPage;
use EstateOffice\Admin\Pages\LicensePage;
use EstateOffice\Admin\Pages\SettingsPage;

final class Menu
{
    public static function register(): void
    {
        add_menu_page(
            __('Estate Office CRM', 'estate-office'),
            __('Estate Office CRM', 'estate-office'),
            'manage_options',
            'estate-office-crm',
            [self::class, 'render_dashboard'],
            'dashicons-admin-multisite',
            26
        );

        add_submenu_page(
            'estate-office-crm',
            __('Licencja', 'estate-office'),
            __('Licencja', 'estate-office'),
            'manage_options',
            'estate-office-license',
            [LicensePage::class, 'render']
        );

        add_submenu_page(
            'estate-office-crm',
            __('Agenci', 'estate-office'),
            __('Agenci', 'estate-office'),
            'list_users',
            'estate-office-agents',
            [AgentsPage::class, 'render']
        );

        add_submenu_page(
            'estate-office-crm',
            __('Ustawienia', 'estate-office'),
            __('Ustawienia', 'estate-office'),
            'manage_options',
            'estate-office-settings',
            [SettingsPage::class, 'render']
        );

        add_submenu_page(
            'estate-office-crm',
            __('O wtyczce', 'estate-office'),
            __('O wtyczce', 'estate-office'),
            'manage_options',
            'estate-office-about',
            [AboutPage::class, 'render']
        );
    }

    public static function render_dashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nie masz uprawnień do przeglądania tej strony.', 'estate-office'));
        }

        echo '<div class="wrap"><h1>' . esc_html__('Estate Office CRM', 'estate-office') . '</h1>';
        echo '<p>' . esc_html__('Wybierz jedną z zakładek w menu, aby rozpocząć konfigurację wtyczki.', 'estate-office') . '</p></div>';
    }
}
