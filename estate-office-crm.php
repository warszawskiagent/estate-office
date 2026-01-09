<?php
/**
 * Plugin Name: Estate Office CRM
 * Description: CRM dla biura nieruchomości z dedykowanym menu administracyjnym i rolami dostępu.
 * Version: 0.0.1
 * Author: Estate Office
 * License: GPLv2 or later
 * Text Domain: estate-office-crm
 */

if (!defined('ABSPATH')) {
    exit;
}

class Estate_Office_CRM {
    private const ROLE_AGENT = 'estate_agent';
    private const CAP_ACCESS = 'estate_office_access';
    private const CAP_ADMIN = 'estate_office_admin';
    private const NONCE_ACTION = 'estate_office_crm_save';

    public function register(): void {
        register_activation_hook(__FILE__, [self::class, 'activate']);
        register_deactivation_hook(__FILE__, [self::class, 'deactivate']);

        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public static function activate(): void {
        add_role(
            self::ROLE_AGENT,
            'Agent',
            [
                'read' => true,
                self::CAP_ACCESS => true,
            ]
        );

        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap(self::CAP_ACCESS);
            $administrator->add_cap(self::CAP_ADMIN);
        }
    }

    public static function deactivate(): void {
        remove_role(self::ROLE_AGENT);

        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->remove_cap(self::CAP_ACCESS);
            $administrator->remove_cap(self::CAP_ADMIN);
        }
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __('Estate Office CRM', 'estate-office-crm'),
            __('Estate Office CRM', 'estate-office-crm'),
            self::CAP_ACCESS,
            'estate-office-crm',
            [$this, 'render_dashboard'],
            'dashicons-building',
            30
        );

        add_submenu_page(
            'estate-office-crm',
            __('Licencja', 'estate-office-crm'),
            __('Licencja', 'estate-office-crm'),
            self::CAP_ADMIN,
            'estate-office-crm-license',
            [$this, 'render_license']
        );

        add_submenu_page(
            'estate-office-crm',
            __('Agenci', 'estate-office-crm'),
            __('Agenci', 'estate-office-crm'),
            self::CAP_ACCESS,
            'estate-office-crm-agents',
            [$this, 'render_agents']
        );

        add_submenu_page(
            'estate-office-crm',
            __('Ustawienia', 'estate-office-crm'),
            __('Ustawienia', 'estate-office-crm'),
            self::CAP_ADMIN,
            'estate-office-crm-settings',
            [$this, 'render_settings']
        );

        add_submenu_page(
            'estate-office-crm',
            __('About', 'estate-office-crm'),
            __('About', 'estate-office-crm'),
            self::CAP_ADMIN,
            'estate-office-crm-about',
            [$this, 'render_about']
        );
    }

    private function render_section_heading(string $title, string $description): void {
        echo '<h2>' . esc_html($title) . '</h2>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }

    private function render_table_header(array $headers): void {
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead>';
    }

    private function render_table_rows(array $rows): void {
        echo '<tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . wp_kses_post($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
    }

    private function render_nonce(): void {
        wp_nonce_field(self::NONCE_ACTION, 'estate_office_crm_nonce');
    }

    public function render_dashboard(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Estate Office CRM', 'estate-office-crm') . '</h1>';
        echo '<p>' . esc_html__('Główne menu zarządzania CRM dla biura nieruchomości.', 'estate-office-crm') . '</p>';

        $this->render_section_heading(
            'Dostęp i role',
            'Administratorzy mają pełny dostęp do ustawień i licencji. Agenci widzą część operacyjną CRM.'
        );

        echo '<table class="widefat striped">';
        $this->render_table_header(['Rola', 'Uprawnienia']);
        $this->render_table_rows([
            ['Administrator', 'Pełny dostęp do wszystkich podstron (licencja, ustawienia, about, agenci).'],
            ['Agent', 'Dostęp do CRM i sekcji Agenci (bez ustawień oraz licencji).'],
        ]);
        echo '</table>';

        echo '</div>';
    }

    public function render_license(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Licencja', 'estate-office-crm') . '</h1>';

        $this->render_section_heading('Dane licencji', 'Wprowadź dane licencyjne dla aktywacji wtyczki.');

        echo '<form method="post" action="">';
        $this->render_nonce();
        echo '<table class="form-table">';
        echo '<tr><th scope="row">Klucz licencji</th><td><input type="text" class="regular-text" placeholder="XXXX-XXXX-XXXX" /></td></tr>';
        echo '<tr><th scope="row">E-mail właściciela</th><td><input type="email" class="regular-text" placeholder="biuro@example.com" /></td></tr>';
        echo '<tr><th scope="row">Status</th><td><select><option>Nieaktywna</option><option>Aktywna</option><option>Wersja trial</option></select></td></tr>';
        echo '</table>';
        echo '<p><button class="button button-primary" type="submit">Zapisz licencję</button></p>';
        echo '</form>';

        echo '<hr />';

        $this->render_section_heading('Historia aktywacji', 'Podgląd aktywacji i wykorzystania licencji.');

        echo '<table class="widefat striped">';
        $this->render_table_header(['Data', 'Akcja', 'Użytkownik', 'Status']);
        $this->render_table_rows([
            ['2024-01-01', 'Aktywacja', 'admin', 'Aktywna'],
            ['2024-03-15', 'Odnowienie', 'admin', 'Aktywna'],
        ]);
        echo '</table>';

        echo '</div>';
    }

    public function render_agents(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Agenci', 'estate-office-crm') . '</h1>';

        $this->render_section_heading('Lista agentów', 'Zarządzaj agentami mającymi dostęp do CRM.');

        echo '<table class="widefat striped">';
        $this->render_table_header(['Agent', 'E-mail', 'Telefon', 'Status', 'Akcje']);
        $this->render_table_rows([
            ['Anna Kowalska', 'anna@example.com', '+48 600 000 000', 'Aktywna', '<a href="#">Edytuj</a>'],
            ['Piotr Nowak', 'piotr@example.com', '+48 600 111 111', 'Aktywna', '<a href="#">Edytuj</a>'],
        ]);
        echo '</table>';

        echo '<hr />';

        $this->render_section_heading('Nowy agent', 'Dodaj nowego agenta i nadaj mu uprawnienia.');

        echo '<form method="post" action="">';
        $this->render_nonce();
        echo '<table class="form-table">';
        echo '<tr><th scope="row">Zdjęcie</th><td><input type="file" /></td></tr>';
        echo '<tr><th scope="row">Imię i nazwisko</th><td><input type="text" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">E-mail</th><td><input type="email" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">Telefon</th><td><input type="text" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">Adres</th><td><input type="text" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">Opis/Biografia</th><td><textarea class="large-text" rows="4"></textarea></td></tr>';
        echo '<tr><th scope="row">Rola</th><td><select><option>Agent</option><option>Administrator</option></select></td></tr>';
        echo '</table>';
        echo '<p><button class="button button-primary" type="submit">Dodaj agenta</button></p>';
        echo '</form>';

        echo '</div>';
    }

    public function render_settings(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Ustawienia', 'estate-office-crm') . '</h1>';

        $this->render_section_heading('Integracje i branding', 'Skonfiguruj podstawowe integracje i elementy wizualne.');

        echo '<form method="post" action="">';
        $this->render_nonce();
        echo '<table class="form-table">';
        echo '<tr><th scope="row">API Map Google</th><td><input type="text" class="regular-text" placeholder="Wklej klucz API" /></td></tr>';
        echo '<tr><th scope="row">Znak wodny</th><td><input type="file" /><p class="description">Znak wodny nanoszony na zdjęcia nieruchomości.</p></td></tr>';
        echo '<tr><th scope="row">Logo biura</th><td><input type="file" /></td></tr>';
        echo '</table>';

        echo '<hr />';

        $this->render_section_heading('Pola nieruchomości', 'Dodaj lub usuń pola oferty nieruchomości.');
        echo '<table class="widefat striped">';
        $this->render_table_header(['Nazwa pola', 'Typ', 'Aktywne', 'Akcje']);
        $this->render_table_rows([
            ['Rok budowy', 'Tekst', 'Tak', '<a href="#">Edytuj</a>'],
            ['Piętro', 'Liczba', 'Tak', '<a href="#">Edytuj</a>'],
            ['Liczba pokoi', 'Liczba', 'Tak', '<a href="#">Edytuj</a>'],
        ]);
        echo '</table>';
        echo '<p><button class="button">Dodaj pole nieruchomości</button></p>';

        echo '<hr />';

        $this->render_section_heading('Pola umów', 'Dodaj lub usuń pola umów.');
        echo '<table class="widefat striped">';
        $this->render_table_header(['Nazwa pola', 'Typ', 'Aktywne', 'Akcje']);
        $this->render_table_rows([
            ['Wysokość prowizji', 'Liczba', 'Tak', '<a href="#">Edytuj</a>'],
            ['Data zakończenia', 'Data', 'Tak', '<a href="#">Edytuj</a>'],
            ['Typ transakcji', 'Lista', 'Tak', '<a href="#">Edytuj</a>'],
        ]);
        echo '</table>';
        echo '<p><button class="button">Dodaj pole umowy</button></p>';

        echo '<hr />';

        $this->render_section_heading('Pola klientów', 'Dodaj lub usuń pola profilu klienta.');
        echo '<table class="widefat striped">';
        $this->render_table_header(['Nazwa pola', 'Typ', 'Aktywne', 'Akcje']);
        $this->render_table_rows([
            ['Numer dokumentu', 'Tekst', 'Tak', '<a href="#">Edytuj</a>'],
            ['Adres korespondencyjny', 'Tekst', 'Tak', '<a href="#">Edytuj</a>'],
            ['PESEL / NIP', 'Tekst', 'Tak', '<a href="#">Edytuj</a>'],
        ]);
        echo '</table>';
        echo '<p><button class="button">Dodaj pole klienta</button></p>';

        echo '<p><button class="button button-primary" type="submit">Zapisz ustawienia</button></p>';
        echo '</form>';

        echo '</div>';
    }

    public function render_about(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('About', 'estate-office-crm') . '</h1>';

        $this->render_section_heading('Opis wtyczki', 'Estate Office CRM to narzędzie do obsługi biura nieruchomości w WordPress.');

        echo '<div class="notice notice-info"><p>';
        echo esc_html__('Wtyczka umożliwia zarządzanie nieruchomościami, umowami, klientami i agentami w jednym miejscu.', 'estate-office-crm');
        echo '</p></div>';

        $this->render_section_heading('Roadmapa wersji', 'Najważniejsze etapy rozwoju wtyczki.');

        echo '<table class="widefat striped">';
        $this->render_table_header(['Wersja', 'Zakres', 'Status']);
        $this->render_table_rows([
            ['0.1', 'Menu administracyjne i role', 'W przygotowaniu'],
            ['0.5', 'Obsługa CRM oraz formularzy', 'Planowane'],
            ['1.0', 'Pełna integracja i eksport', 'Planowane'],
        ]);
        echo '</table>';

        echo '</div>';
    }
}

$estate_office_crm = new Estate_Office_CRM();
$estate_office_crm->register();
