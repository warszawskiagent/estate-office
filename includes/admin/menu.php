<?php

declare(strict_types=1);

namespace EstateOffice\Admin;

defined('ABSPATH') || exit;

use EstateOffice\Admin\Pages\AboutPage;
use EstateOffice\Admin\Pages\AgentsPage;
use EstateOffice\Admin\Pages\LicensePage;
use EstateOffice\Admin\Pages\SettingsPage;
use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchRegister;

final class Menu
{
    public static function register(): void
    {
        add_menu_page(
            __('Estate Office CRM', 'estate-office'),
            __('Estate Office CRM', 'estate-office'),
            'edit_estate_properties',
            'estate-office-crm',
            [self::class, 'render_dashboard'],
            'dashicons-admin-multisite',
            26
        );

        add_submenu_page(
            'estate-office-crm',
            __('Pulpit', 'estate-office'),
            __('Pulpit', 'estate-office'),
            'edit_estate_properties',
            'estate-office-crm',
            [self::class, 'render_dashboard']
        );

        remove_submenu_page('estate-office-crm', 'estate-office-crm');

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

    public static function render_dashboard(): void
    {
        if (!current_user_can('edit_estate_properties')) {
            wp_die(esc_html__('Nie masz uprawnień do przeglądania tej strony.', 'estate-office'));
        }

        $links = [
            [
                'label' => __('Dodaj nową umowę', 'estate-office'),
                'href'  => admin_url('post-new.php?post_type=' . AgreementRegister::POST_TYPE),
                'class' => 'button button-primary',
            ],
            [
                'label' => __('Przeglądaj nieruchomości', 'estate-office'),
                'href'  => admin_url('edit.php?post_type=' . PropertyRegister::POST_TYPE),
                'class' => 'button',
            ],
            [
                'label' => __('Przeglądaj poszukiwania', 'estate-office'),
                'href'  => admin_url('edit.php?post_type=' . SearchRegister::POST_TYPE),
                'class' => 'button',
            ],
            [
                'label' => __('Przeglądaj klientów', 'estate-office'),
                'href'  => admin_url('edit.php?post_type=' . ClientRegister::POST_TYPE),
                'class' => 'button',
            ],
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Estate Office CRM', 'estate-office') . '</h1>';
        echo '<p>' . esc_html__('Witaj w module CRM. Skorzystaj z poniższych skrótów, aby szybko przejść do najważniejszych zasobów.', 'estate-office') . '</p>';

        echo '<p class="estate-office-quick-actions">';
        foreach ($links as $link) {
            printf(
                '<a class="%1$s" href="%2$s">%3$s</a> ',
                esc_attr($link['class']),
                esc_url($link['href']),
                esc_html($link['label'])
            );
        }
        echo '</p>';

        echo '</div>';
    }
}
