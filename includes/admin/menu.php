<?php

declare(strict_types=1);

namespace EstateOffice\Admin;

defined('ABSPATH') || exit;

use EstateOffice\Admin\Pages\AboutPage;
use EstateOffice\Admin\Pages\AgentsPage;
use EstateOffice\Admin\Pages\LicensePage;
use EstateOffice\Admin\Pages\OverviewPage;
use EstateOffice\Admin\Pages\SettingsPage;

final class Menu
{
    public static function register(): void
    {
        add_menu_page(
            __('Estate Office CRM', 'estate-office'),
            __('Estate Office CRM', 'estate-office'),
            'edit_estate_properties',
            'estate-office-crm',
            [OverviewPage::class, 'render'],
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
            'edit_estate_properties',
            'estate-office-about',
            [AboutPage::class, 'render']
        );
    }
}
