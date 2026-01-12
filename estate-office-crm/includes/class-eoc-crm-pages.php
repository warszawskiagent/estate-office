<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_CRM_Pages {
    public function register(): void {
        add_action('admin_menu', array($this, 'register_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'estate-office-crm_page_estate-office-crm-') !== 0) {
            return;
        }

        wp_enqueue_style(
            'eoc-admin',
            EOC_PLUGIN_URL . 'assets/admin/admin.css',
            array(),
            EOC_PLUGIN_VERSION
        );

        if ($hook === 'estate-office-crm_page_estate-office-crm-contracts-add') {
            wp_enqueue_script(
                'eoc-contracts',
                EOC_PLUGIN_URL . 'assets/admin/contracts.js',
                array('jquery'),
                EOC_PLUGIN_VERSION,
                true
            );
        }

        if ($hook === 'estate-office-crm_page_estate-office-crm-clients-add') {
            wp_enqueue_script(
                'eoc-clients',
                EOC_PLUGIN_URL . 'assets/admin/clients.js',
                array('jquery'),
                EOC_PLUGIN_VERSION,
                true
            );
        }

        if ($hook === 'estate-office-crm_page_estate-office-crm-properties-add') {
            wp_enqueue_script(
                'eoc-properties',
                EOC_PLUGIN_URL . 'assets/admin/properties.js',
                array('jquery'),
                EOC_PLUGIN_VERSION,
                true
            );
        }
    }

    public function register_pages(): void {
        $capability = 'eoc_access';

        add_submenu_page(
            'estate-office-crm',
            __('CRM: Pulpit', 'estate-office-crm'),
            __('CRM: Pulpit', 'estate-office-crm'),
            $capability,
            'estate-office-crm-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'estate-office-crm',
            __('CRM: Nieruchomości', 'estate-office-crm'),
            __('CRM: Nieruchomości', 'estate-office-crm'),
            $capability,
            'estate-office-crm-properties',
            array($this, 'render_properties')
        );

        add_submenu_page(
            'estate-office-crm',
            __('CRM: Poszukiwania', 'estate-office-crm'),
            __('CRM: Poszukiwania', 'estate-office-crm'),
            $capability,
            'estate-office-crm-searches',
            array($this, 'render_searches')
        );

        add_submenu_page(
            'estate-office-crm',
            __('CRM: Umowy', 'estate-office-crm'),
            __('CRM: Umowy', 'estate-office-crm'),
            $capability,
            'estate-office-crm-contracts',
            array($this, 'render_contracts')
        );

        add_submenu_page(
            'estate-office-crm',
            __('CRM: Klienci', 'estate-office-crm'),
            __('CRM: Klienci', 'estate-office-crm'),
            $capability,
            'estate-office-crm-clients',
            array($this, 'render_clients')
        );

        add_submenu_page(
            'estate-office-crm',
            __('Nowa umowa', 'estate-office-crm'),
            __('Nowa umowa', 'estate-office-crm'),
            $capability,
            'estate-office-crm-contracts-add',
            array($this, 'render_contract_add')
        );

        add_submenu_page(
            'estate-office-crm',
            __('Dodaj klienta', 'estate-office-crm'),
            __('Dodaj klienta', 'estate-office-crm'),
            $capability,
            'estate-office-crm-clients-add',
            array($this, 'render_client_add')
        );

        add_submenu_page(
            'estate-office-crm',
            __('Dodaj nieruchomość', 'estate-office-crm'),
            __('Dodaj nieruchomość', 'estate-office-crm'),
            $capability,
            'estate-office-crm-properties-add',
            array($this, 'render_property_add')
        );

        add_submenu_page(
            'estate-office-crm',
            __('Dodaj poszukiwanie', 'estate-office-crm'),
            __('Dodaj poszukiwanie', 'estate-office-crm'),
            $capability,
            'estate-office-crm-searches-add',
            array($this, 'render_search_add')
        );
    }

    public function render_dashboard(): void {
        $metrics = array(
            array('label' => __('Liczba nieruchomości', 'estate-office-crm'), 'value' => '0'),
            array('label' => __('Aktywne umowy', 'estate-office-crm'), 'value' => '0'),
            array('label' => __('Poszukiwania', 'estate-office-crm'), 'value' => '0'),
            array('label' => __('Najlepsi agenci', 'estate-office-crm'), 'value' => __('Wkrótce', 'estate-office-crm')),
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CRM: Pulpit', 'estate-office-crm') . '</h1>';
        echo '<div class="eoc-metrics">';
        foreach ($metrics as $metric) {
            echo '<div class="eoc-metric">';
            echo '<div class="eoc-metric__label">' . esc_html($metric['label']) . '</div>';
            echo '<div class="eoc-metric__value">' . esc_html($metric['value']) . '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<p>' . esc_html__('Pulpit będzie rozbudowany o pełne statystyki po dodaniu danych CRM.', 'estate-office-crm') . '</p>';
        echo '</div>';
    }

    public function render_properties(): void {
        $columns = array(
            __('Numer oferty', 'estate-office-crm'),
            __('Adres', 'estate-office-crm'),
            __('Cena', 'estate-office-crm'),
            __('Cena za m²', 'estate-office-crm'),
            __('Metraż', 'estate-office-crm'),
            __('Liczba pokoi', 'estate-office-crm'),
            __('Opiekun', 'estate-office-crm'),
        );

        $this->render_list_page(__('CRM: Nieruchomości', 'estate-office-crm'), $columns);
    }

    public function render_searches(): void {
        $columns = array(
            __('Numer poszukiwania', 'estate-office-crm'),
            __('Rodzaj nieruchomości', 'estate-office-crm'),
            __('Budżet', 'estate-office-crm'),
            __('Lokalizacja', 'estate-office-crm'),
            __('Typ transakcji', 'estate-office-crm'),
        );

        $this->render_list_page(__('CRM: Poszukiwania', 'estate-office-crm'), $columns);
    }

    public function render_contracts(): void {
        $columns = array(
            __('Numer umowy', 'estate-office-crm'),
            __('Typ transakcji', 'estate-office-crm'),
            __('Rodzaj nieruchomości', 'estate-office-crm'),
            __('Adres', 'estate-office-crm'),
            __('Data zawarcia', 'estate-office-crm'),
            __('Data zakończenia', 'estate-office-crm'),
            __('Aktualny etap', 'estate-office-crm'),
            __('Opiekun', 'estate-office-crm'),
        );

        $this->render_list_page(__('CRM: Umowy', 'estate-office-crm'), $columns);
    }

    public function render_clients(): void {
        $columns = array(
            __('Imię i nazwisko / Nazwa', 'estate-office-crm'),
            __('Adres', 'estate-office-crm'),
            __('Telefon', 'estate-office-crm'),
            __('E-mail', 'estate-office-crm'),
            __('Opiekun', 'estate-office-crm'),
        );

        $this->render_list_page(__('CRM: Klienci', 'estate-office-crm'), $columns);
    }

    public function render_contract_add(): void {
        $error = isset($_GET['eoc_error']) ? sanitize_text_field(wp_unslash($_GET['eoc_error'])) : '';
        $success = isset($_GET['eoc_success']) ? sanitize_text_field(wp_unslash($_GET['eoc_success'])) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Nowa umowa', 'estate-office-crm') . '</h1>';

        if ($error === 'duplicate') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Numer umowy już istnieje. Użyj innego numeru.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'invalid') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Uzupełnij poprawnie wymagane pola umowy.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'db') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Wystąpił błąd zapisu umowy. Spróbuj ponownie.', 'estate-office-crm') . '</p></div>';
        } elseif ($success === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Umowa została zapisana. Przejdź do dodawania klienta.', 'estate-office-crm') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_save_contract', 'eoc_contract_nonce');
        echo '<input type="hidden" name="action" value="eoc_save_contract" />';

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-contract-number">' . esc_html__('Numer umowy', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-contract-number" name="eoc_contract[contract_number]" class="regular-text" required /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-transaction-type">' . esc_html__('Typ transakcji', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-transaction-type" name="eoc_contract[transaction_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office-crm') . '</option>';
        echo '<option value="SPRZEDAZ">' . esc_html__('SPRZEDAŻ', 'estate-office-crm') . '</option>';
        echo '<option value="KUPNO">' . esc_html__('KUPNO', 'estate-office-crm') . '</option>';
        echo '<option value="WYNAJEM">' . esc_html__('WYNAJEM', 'estate-office-crm') . '</option>';
        echo '<option value="NAJEM">' . esc_html__('NAJEM', 'estate-office-crm') . '</option>';
        echo '</select></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-start-date">' . esc_html__('Data zawarcia', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="date" id="eoc-start-date" name="eoc_contract[start_date]" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-end-date">' . esc_html__('Data zakończenia', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="date" id="eoc-end-date" name="eoc_contract[end_date]" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Umowa bezterminowa', 'estate-office-crm') . '</th>';
        echo '<td><label><input type="checkbox" id="eoc-open-ended" name="eoc_contract[is_open_ended]" value="1" /> ' . esc_html__('Brak daty zakończenia', 'estate-office-crm') . '</label></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-commission-amount">' . esc_html__('Wysokość prowizji', 'estate-office-crm') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="eoc-commission-amount" name="eoc_contract[commission_amount]" class="small-text" />';
        echo '<select id="eoc-commission-unit" name="eoc_contract[commission_unit]">';
        echo '<option value="">' . esc_html__('Jednostka', 'estate-office-crm') . '</option>';
        echo '<option value="%">%</option>';
        echo '<option value="PLN">PLN</option>';
        echo '<option value="EUR">EUR</option>';
        echo '<option value="USD">USD</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button(__('Dalej', 'estate-office-crm'));
        echo '</form>';
        echo '</div>';
    }

    public function render_client_add(): void {
        $error = isset($_GET['eoc_error']) ? sanitize_text_field(wp_unslash($_GET['eoc_error'])) : '';
        $success = isset($_GET['eoc_success']) ? sanitize_text_field(wp_unslash($_GET['eoc_success'])) : '';
        $contract_id = isset($_GET['contract_id']) ? absint($_GET['contract_id']) : 0;

        $search_term = isset($_GET['eoc_client_search']) ? sanitize_text_field(wp_unslash($_GET['eoc_client_search'])) : '';
        $existing_clients = $this->get_client_search_results($search_term);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Dodaj klienta', 'estate-office-crm') . '</h1>';

        if (!$contract_id) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Brak powiązanej umowy. Wróć do dodawania umowy.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        if ($error === 'missing_contract') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono powiązanej umowy.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'invalid') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Uzupełnij poprawnie wymagane pola klienta.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'db') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Wystąpił błąd zapisu klienta. Spróbuj ponownie.', 'estate-office-crm') . '</p></div>';
        } elseif ($success === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Klient został przypisany do umowy.', 'estate-office-crm') . '</p></div>';
        }

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Wyszukiwarka klientów', 'estate-office-crm') . '</h2>';
        echo '<form method="get" class="eoc-search-form">';
        echo '<input type="hidden" name="page" value="estate-office-crm-clients-add" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr((string) $contract_id) . '" />';
        echo '<input type="search" name="eoc_client_search" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Imię, nazwisko, telefon lub e-mail', 'estate-office-crm') . '" class="regular-text" />';
        echo '<button type="submit" class="button">' . esc_html__('Szukaj', 'estate-office-crm') . '</button>';
        echo '</form>';

        if (empty($existing_clients) && $search_term !== '') {
            echo '<p>' . esc_html__('Brak wyników dla podanego wyszukiwania.', 'estate-office-crm') . '</p>';
        }
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_save_client', 'eoc_client_nonce');
        echo '<input type="hidden" name="action" value="eoc_save_client" />';
        echo '<input type="hidden" name="eoc_client[contract_id]" value="' . esc_attr((string) $contract_id) . '" />';

        if (!empty($existing_clients)) {
            echo '<div class="eoc-section eoc-existing-clients">';
            echo '<h2>' . esc_html__('Wybierz istniejącego klienta', 'estate-office-crm') . '</h2>';
            echo '<ul>';
            foreach ($existing_clients as $client) {
                $label = sprintf(
                    '%s %s (%s)',
                    esc_html($client['display_name']),
                    $client['phone'] ? esc_html($client['phone']) : esc_html__('brak telefonu', 'estate-office-crm'),
                    $client['email'] ? esc_html($client['email']) : esc_html__('brak e-maila', 'estate-office-crm')
                );
                echo '<li><label><input type="radio" name="eoc_client[existing_client_id]" value="' . esc_attr((string) $client['id']) . '" /> ' . $label . '</label></li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Dodaj nowego klienta', 'estate-office-crm') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-client-type">' . esc_html__('Typ klienta', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-client-type" name="eoc_client[client_type]" required>';
        echo '<option value="PERSON">' . esc_html__('Osoba fizyczna', 'estate-office-crm') . '</option>';
        echo '<option value="COMPANY">' . esc_html__('Firma', 'estate-office-crm') . '</option>';
        echo '</select></td>';
        echo '</tr>';
        echo '</table>';

        echo '<div class="eoc-client-fields eoc-client-fields--person">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-first-name">' . esc_html__('Imię', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-first-name" name="eoc_client[first_name]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-last-name">' . esc_html__('Nazwisko', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-last-name" name="eoc_client[last_name]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<div class="eoc-client-fields eoc-client-fields--company">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-company-name">' . esc_html__('Nazwa firmy', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-company-name" name="eoc_client[company_name]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-representative">' . esc_html__('Imię i nazwisko reprezentanta', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-representative" name="eoc_client[representative_name]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<h3>' . esc_html__('Dane kontaktowe', 'estate-office-crm') . '</h3>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-phone">' . esc_html__('Numer telefonu', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-phone" name="eoc_client[phone]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-email">' . esc_html__('Adres e-mail', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="email" id="eoc-email" name="eoc_client[email]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr class="eoc-client-fields eoc-client-fields--company">'; 
        echo '<th scope="row"><label for="eoc-website">' . esc_html__('Strona WWW', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="url" id="eoc-website" name="eoc_client[website]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';

        echo '<h3>' . esc_html__('Dane identyfikacyjne', 'estate-office-crm') . '</h3>';
        echo '<div class="eoc-client-fields eoc-client-fields--person">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-pesel">' . esc_html__('PESEL', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-pesel" name="eoc_client[pesel]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-id-type">' . esc_html__('Rodzaj dokumentu', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-id-type" name="eoc_client[id_type]">';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office-crm') . '</option>';
        echo '<option value="ID">' . esc_html__('Dowód osobisty', 'estate-office-crm') . '</option>';
        echo '<option value="PASSPORT">' . esc_html__('Paszport', 'estate-office-crm') . '</option>';
        echo '<option value="RESIDENCE">' . esc_html__('Karta pobytu', 'estate-office-crm') . '</option>';
        echo '</select></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-id-number">' . esc_html__('Numer dokumentu', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-id-number" name="eoc_client[id_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<div class="eoc-client-fields eoc-client-fields--company">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-tax-id">' . esc_html__('NIP', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-tax-id" name="eoc_client[tax_id]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-krs">' . esc_html__('KRS', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-krs" name="eoc_client[krs]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-regon">' . esc_html__('REGON', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-regon" name="eoc_client[regon]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<h3>' . esc_html__('Adres zamieszkania / rejestrowy', 'estate-office-crm') . '</h3>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-street">' . esc_html__('Ulica', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-street" name="eoc_client[street]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-building-number">' . esc_html__('Numer', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-building-number" name="eoc_client[building_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-unit-number">' . esc_html__('Lokal', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-unit-number" name="eoc_client[unit_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-postal-code">' . esc_html__('Kod pocztowy', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-postal-code" name="eoc_client[postal_code]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-city">' . esc_html__('Miasto', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-city" name="eoc_client[city]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-country">' . esc_html__('Kraj', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-country" name="eoc_client[country]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';

        echo '<h3>' . esc_html__('Adres korespondencyjny', 'estate-office-crm') . '</h3>';
        echo '<p><label><input type="checkbox" id="eoc-mailing-same" name="eoc_client[mailing_same]" value="1" checked /> ' . esc_html__('Adres korespondencyjny taki sam', 'estate-office-crm') . '</label></p>';

        echo '<div class="eoc-mailing-fields">';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-mailing-street">' . esc_html__('Ulica', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-mailing-street" name="eoc_client[mailing_street]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-mailing-building-number">' . esc_html__('Numer', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-mailing-building-number" name="eoc_client[mailing_building_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-mailing-unit-number">' . esc_html__('Lokal', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-mailing-unit-number" name="eoc_client[mailing_unit_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-mailing-postal-code">' . esc_html__('Kod pocztowy', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-mailing-postal-code" name="eoc_client[mailing_postal_code]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-mailing-city">' . esc_html__('Miasto', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-mailing-city" name="eoc_client[mailing_city]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-mailing-country">' . esc_html__('Kraj', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-mailing-country" name="eoc_client[mailing_country]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<h3>' . esc_html__('Czy chcesz dodać kolejnego klienta?', 'estate-office-crm') . '</h3>';
        echo '<p class="eoc-inline-options">';
        echo '<label><input type="radio" name="eoc_client[next_step]" value="add_another" checked /> ' . esc_html__('TAK', 'estate-office-crm') . '</label>';
        echo '<label><input type="radio" name="eoc_client[next_step]" value="next" /> ' . esc_html__('NIE', 'estate-office-crm') . '</label>';
        echo '</p>';

        submit_button(__('Dalej', 'estate-office-crm'));
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }

    public function render_property_add(): void {
        $error = isset($_GET['eoc_error']) ? sanitize_text_field(wp_unslash($_GET['eoc_error'])) : '';
        $success = isset($_GET['eoc_success']) ? sanitize_text_field(wp_unslash($_GET['eoc_success'])) : '';
        $contract_id = isset($_GET['contract_id']) ? absint($_GET['contract_id']) : 0;

        $transaction_type = '';
        if ($contract_id) {
            $contract = $this->get_contract_summary($contract_id);
            $transaction_type = $contract['transaction_type'] ?? '';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Dodaj nieruchomość', 'estate-office-crm') . '</h1>';

        if (!$contract_id) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Brak powiązanej umowy. Wróć do dodawania klienta.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        if ($error === 'missing_contract') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono powiązanej umowy.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'invalid') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Uzupełnij poprawnie wymagane pola nieruchomości.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'db') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Wystąpił błąd zapisu nieruchomości. Spróbuj ponownie.', 'estate-office-crm') . '</p></div>';
        } elseif ($success === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Nieruchomość została zapisana.', 'estate-office-crm') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_save_property', 'eoc_property_nonce');
        echo '<input type="hidden" name="action" value="eoc_save_property" />';
        echo '<input type="hidden" name="eoc_property[contract_id]" value="' . esc_attr((string) $contract_id) . '" />';

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Typ transakcji', 'estate-office-crm') . '</th>';
        echo '<td><input type="text" class="regular-text" value="' . esc_attr($transaction_type) . '" disabled /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-property-type">' . esc_html__('Rodzaj nieruchomości', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-property-type" name="eoc_property[property_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office-crm') . '</option>';
        echo '<option value="MIESZKANIE">' . esc_html__('MIESZKANIE', 'estate-office-crm') . '</option>';
        echo '<option value="DOM">' . esc_html__('DOM', 'estate-office-crm') . '</option>';
        echo '<option value="DZIALKA">' . esc_html__('DZIAŁKA', 'estate-office-crm') . '</option>';
        echo '<option value="LOKAL">' . esc_html__('LOKAL H/U', 'estate-office-crm') . '</option>';
        echo '</select></td>';
        echo '</tr>';
        echo '</table>';

        echo '<div class="eoc-section eoc-property-section eoc-property-section--apartment">';
        echo '<h2>' . esc_html__('Dane adresowe (mieszkanie / lokal)', 'estate-office-crm') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-street">' . esc_html__('Ulica', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-street" name="eoc_property[street]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-building-number">' . esc_html__('Numer', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-building-number" name="eoc_property[building_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-unit-number">' . esc_html__('Lokal', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-unit-number" name="eoc_property[unit_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-postal-code">' . esc_html__('Kod pocztowy', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-postal-code" name="eoc_property[postal_code]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-district">' . esc_html__('Dzielnica', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-district" name="eoc_property[district]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-city">' . esc_html__('Miasto', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-city" name="eoc_property[city]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<div class="eoc-section eoc-property-section eoc-property-section--house">';
        echo '<h2>' . esc_html__('Dane adresowe (dom / działka)', 'estate-office-crm') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-street-house">' . esc_html__('Ulica', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-street-house" name="eoc_property[street]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-building-number-house">' . esc_html__('Numer', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-building-number-house" name="eoc_property[building_number]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-postal-code-house">' . esc_html__('Kod pocztowy', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-postal-code-house" name="eoc_property[postal_code]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-city-house">' . esc_html__('Miasto', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-city-house" name="eoc_property[city]" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Dane nieruchomości', 'estate-office-crm') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-price">' . esc_html__('Cena', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-price" name="eoc_property[price]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-area">' . esc_html__('Powierzchnia (m²)', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-area" name="eoc_property[area]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-rooms">' . esc_html__('Liczba pokoi', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="number" id="eoc-rooms" name="eoc_property[rooms]" class="small-text" min="0" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        submit_button(__('Dodaj nieruchomość', 'estate-office-crm'));
        echo '</form>';
        echo '</div>';
    }

    public function render_search_add(): void {
        $error = isset($_GET['eoc_error']) ? sanitize_text_field(wp_unslash($_GET['eoc_error'])) : '';
        $success = isset($_GET['eoc_success']) ? sanitize_text_field(wp_unslash($_GET['eoc_success'])) : '';
        $contract_id = isset($_GET['contract_id']) ? absint($_GET['contract_id']) : 0;

        $transaction_type = '';
        if ($contract_id) {
            $contract = $this->get_contract_summary($contract_id);
            $transaction_type = $contract['transaction_type'] ?? '';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Dodaj poszukiwanie', 'estate-office-crm') . '</h1>';

        if (!$contract_id) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Brak powiązanej umowy. Wróć do dodawania klienta.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        if ($error === 'missing_contract') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono powiązanej umowy.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'db') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Wystąpił błąd zapisu poszukiwania. Spróbuj ponownie.', 'estate-office-crm') . '</p></div>';
        } elseif ($success === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Poszukiwanie zostało zapisane.', 'estate-office-crm') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_save_search', 'eoc_search_nonce');
        echo '<input type="hidden" name="action" value="eoc_save_search" />';
        echo '<input type="hidden" name="eoc_search[contract_id]" value="' . esc_attr((string) $contract_id) . '" />';

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Typ transakcji', 'estate-office-crm') . '</th>';
        echo '<td><input type="text" class="regular-text" value="' . esc_attr($transaction_type) . '" disabled /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-price-min">' . esc_html__('Cena od', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-price-min" name="eoc_search[price_min]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-price-max">' . esc_html__('Cena do', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-price-max" name="eoc_search[price_max]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-area-min">' . esc_html__('Metraż od', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-area-min" name="eoc_search[area_min]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-area-max">' . esc_html__('Metraż do', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-area-max" name="eoc_search[area_max]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-rooms-min">' . esc_html__('Liczba pokoi od', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="number" id="eoc-rooms-min" name="eoc_search[rooms_min]" class="small-text" min="0" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-rooms-max">' . esc_html__('Liczba pokoi do', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="number" id="eoc-rooms-max" name="eoc_search[rooms_max]" class="small-text" min="0" /></td>';
        echo '</tr>';
        echo '</table>';

        echo '<h2>' . esc_html__('Opis poszukiwania', 'estate-office-crm') . '</h2>';
        wp_editor('', 'eoc-search-description', array(
            'textarea_name' => 'eoc_search[description]',
            'media_buttons' => false,
            'textarea_rows' => 6,
        ));

        submit_button(__('Dodaj poszukiwanie', 'estate-office-crm'));
        echo '</form>';
        echo '</div>';
    }

    private function render_list_page(string $title, array $columns): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<div class="eoc-list-actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=estate-office-crm-contracts-add')) . '" class="button button-primary">' . esc_html__('Dodaj nową Umowę', 'estate-office-crm') . '</a>';
        echo '<form method="get" class="eoc-search-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page'] ?? '') . '" />';
        echo '<input type="search" name="s" value="' . esc_attr($_GET['s'] ?? '') . '" placeholder="' . esc_attr__('Wyszukaj...', 'estate-office-crm') . '" />';
        echo '<button type="submit" class="button">' . esc_html__('Szukaj', 'estate-office-crm') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped eoc-list-table">';
        echo '<thead><tr>';
        foreach ($columns as $column) {
            echo '<th>' . esc_html($column) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        echo '<tr><td colspan="' . esc_attr((string) count($columns)) . '">' . esc_html__('Brak danych do wyświetlenia.', 'estate-office-crm') . '</td></tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    private function get_client_search_results(string $search_term): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_clients';

        if ($search_term === '') {
            return array();
        }

        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, client_type, first_name, last_name, company_name, phone, email\n"
                . "FROM {$table}\n"
                . "WHERE first_name LIKE %s\n"
                . "   OR last_name LIKE %s\n"
                . "   OR company_name LIKE %s\n"
                . "   OR phone LIKE %s\n"
                . "   OR email LIKE %s\n"
                . "ORDER BY id DESC\n"
                . "LIMIT 10",
                $like,
                $like,
                $like,
                $like,
                $like
            ),
            ARRAY_A
        );

        $clients = array();
        foreach ($results as $client) {
            if ($client['client_type'] === 'COMPANY') {
                $display_name = $client['company_name'] ?: __('Firma', 'estate-office-crm');
            } else {
                $display_name = trim($client['first_name'] . ' ' . $client['last_name']);
                if ($display_name === '') {
                    $display_name = __('Klient', 'estate-office-crm');
                }
            }

            $clients[] = array(
                'id' => (int) $client['id'],
                'display_name' => $display_name,
                'phone' => $client['phone'],
                'email' => $client['email'],
            );
        }

        return $clients;
    }

    private function get_contract_summary(int $contract_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_contracts';

        $contract = $wpdb->get_row(
            $wpdb->prepare("SELECT id, transaction_type FROM {$table} WHERE id = %d", $contract_id),
            ARRAY_A
        );

        return $contract ?: null;
    }
}
