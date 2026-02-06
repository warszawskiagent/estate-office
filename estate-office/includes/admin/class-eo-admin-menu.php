<?php

if (!defined('ABSPATH')) {
    exit;
}

class EstateOffice_Admin_Menu
{
    public function register()
    {
        add_menu_page(
            'EstateOffice CRM',
            'EstateOffice CRM',
            'manage_options',
            'estateoffice-crm',
            array($this, 'render_about'),
            'dashicons-building'
        );

        add_submenu_page(
            'estateoffice-crm',
            'Licencja',
            'Licencja',
            'manage_options',
            'estateoffice-license',
            array($this, 'render_license')
        );

        add_submenu_page(
            'estateoffice-crm',
            'Agenci',
            'Agenci',
            'manage_options',
            'estateoffice-agents',
            array($this, 'render_agents')
        );

        add_submenu_page(
            'estateoffice-crm',
            'Ustawienia',
            'Ustawienia',
            'manage_options',
            'estateoffice-settings',
            array($this, 'render_settings')
        );

        add_submenu_page(
            'estateoffice-crm',
            'About',
            'About',
            'manage_options',
            'estateoffice-about',
            array($this, 'render_about')
        );
    }

    public function render_license()
    {
        echo '<div class="wrap"><h1>Licencja</h1><p>Moduł licencji zostanie dodany w kolejnych wersjach.</p></div>';
    }

    public function render_agents()
    {
        echo '<div class="wrap"><h1>Agenci</h1><p>Wersja 0.0.1 zawiera przygotowanie typu użytkownika Agent Nieruchomości.</p></div>';
    }

    public function render_settings()
    {
        echo '<div class="wrap">';
        echo '<h1>Ustawienia</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(EstateOffice_Settings::OPTION_GROUP);
        do_settings_sections('estateoffice-settings');
        submit_button('Zapisz ustawienia');
        echo '</form>';
        echo '</div>';
    }

    public function render_about()
    {
        echo '<div class="wrap"><h1>EstateOffice CRM</h1><p>Wersja 0.0.3 - rozwój ustawień i usprawnień CRM.</p></div>';
    }
}
