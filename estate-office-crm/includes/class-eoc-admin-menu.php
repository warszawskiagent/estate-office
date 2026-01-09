<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Admin_Menu {
    public function register(): void {
        add_action('admin_menu', array($this, 'register_menu'));
    }

    public function register_menu(): void {
        $capability = 'eoc_access';

        add_menu_page(
            __('Estate Office CRM', 'estate-office-crm'),
            __('Estate Office CRM', 'estate-office-crm'),
            $capability,
            'estate-office-crm',
            array($this, 'render_dashboard'),
            'dashicons-building',
            26
        );

        add_submenu_page(
            'estate-office-crm',
            __('Licencja', 'estate-office-crm'),
            __('Licencja', 'estate-office-crm'),
            $capability,
            'estate-office-crm-license',
            array($this, 'render_license')
        );

        add_submenu_page(
            'estate-office-crm',
            __('Agenci', 'estate-office-crm'),
            __('Agenci', 'estate-office-crm'),
            $capability,
            'estate-office-crm-agents',
            array($this, 'render_agents')
        );

        add_submenu_page(
            'estate-office-crm',
            __('Ustawienia', 'estate-office-crm'),
            __('Ustawienia', 'estate-office-crm'),
            'manage_options',
            'estate-office-crm-settings',
            array($this, 'render_settings')
        );

        add_submenu_page(
            'estate-office-crm',
            __('About', 'estate-office-crm'),
            __('About', 'estate-office-crm'),
            $capability,
            'estate-office-crm-about',
            array($this, 'render_about')
        );
    }

    public function render_dashboard(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Estate Office CRM', 'estate-office-crm') . '</h1>';
        echo '<p>' . esc_html__('Panel startowy zostanie uzupełniony o metryki CRM w kolejnych wersjach.', 'estate-office-crm') . '</p>';
        echo '</div>';
    }

    public function render_license(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Licencja', 'estate-office-crm') . '</h1>';
        echo '<p>' . esc_html__('Moduł licencji zostanie dodany na końcu procesu tworzenia wtyczki.', 'estate-office-crm') . '</p>';
        echo '</div>';
    }

    public function render_agents(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Agenci', 'estate-office-crm') . '</h1>';
        echo '<p>' . esc_html__('Zarządzanie agentami pojawi się w kolejnych wersjach.', 'estate-office-crm') . '</p>';
        echo '</div>';
    }

    public function render_settings(): void {
        EOC_Settings::render_settings_page();
    }

    public function render_about(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('About', 'estate-office-crm') . '</h1>';
        echo '<p>' . esc_html__('EstateOffice CRM to wtyczka dla biur nieruchomości, obejmująca zarządzanie nieruchomościami, umowami, klientami oraz agentami.', 'estate-office-crm') . '</p>';
        echo '<h2>' . esc_html__('Roadmapa', 'estate-office-crm') . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__('1.0: Obsługa pełnych formularzy ofert, umów i klientów.', 'estate-office-crm') . '</li>';
        echo '<li>' . esc_html__('1.1: Eksport ofert na WWW i automatyczne strony ofert.', 'estate-office-crm') . '</li>';
        echo '<li>' . esc_html__('1.2: Integracja z portalami i kalkulatory.', 'estate-office-crm') . '</li>';
        echo '</ol>';
        echo '</div>';
    }
}
