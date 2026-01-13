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
            null,
            __('Podgląd umowy', 'estate-office-crm'),
            __('Podgląd umowy', 'estate-office-crm'),
            $capability,
            'estate-office-crm-contracts-view',
            array($this, 'render_contract_view')
        );

        add_submenu_page(
            null,
            __('Podgląd klienta', 'estate-office-crm'),
            __('Podgląd klienta', 'estate-office-crm'),
            $capability,
            'estate-office-crm-clients-view',
            array($this, 'render_client_view')
        );

        add_submenu_page(
            null,
            __('Podgląd nieruchomości', 'estate-office-crm'),
            __('Podgląd nieruchomości', 'estate-office-crm'),
            $capability,
            'estate-office-crm-properties-view',
            array($this, 'render_property_view')
        );

        add_submenu_page(
            null,
            __('Podgląd poszukiwania', 'estate-office-crm'),
            __('Podgląd poszukiwania', 'estate-office-crm'),
            $capability,
            'estate-office-crm-searches-view',
            array($this, 'render_search_view')
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
        $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $properties = $this->get_properties($search_term);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CRM: Nieruchomości', 'estate-office-crm') . '</h1>';
        echo '<div class="eoc-list-actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=estate-office-crm-contracts-add')) . '" class="button button-primary">' . esc_html__('Dodaj nową Umowę', 'estate-office-crm') . '</a>';
        echo '<form method="get" class="eoc-search-form">';
        echo '<input type="hidden" name="page" value="estate-office-crm-properties" />';
        echo '<input type="search" name="s" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Wyszukaj...', 'estate-office-crm') . '" />';
        echo '<button type="submit" class="button">' . esc_html__('Szukaj', 'estate-office-crm') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped eoc-list-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Numer oferty', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Adres', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Cena', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Cena za m²', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Metraż', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Liczba pokoi', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Opiekun', 'estate-office-crm') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($properties)) {
            echo '<tr><td colspan="7">' . esc_html__('Brak danych do wyświetlenia.', 'estate-office-crm') . '</td></tr>';
        } else {
            foreach ($properties as $property) {
                $view_link = add_query_arg(
                    array('page' => 'estate-office-crm-properties-view', 'property_id' => $property['id']),
                    admin_url('admin.php')
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url($view_link) . '">' . esc_html($property['offer_number']) . '</a></td>';
                echo '<td>' . esc_html($property['address']) . '</td>';
                echo '<td>' . esc_html($property['price']) . '</td>';
                echo '<td>' . esc_html($property['price_per_sqm']) . '</td>';
                echo '<td>' . esc_html($property['area']) . '</td>';
                echo '<td>' . esc_html($property['rooms']) . '</td>';
                echo '<td>' . esc_html($property['agent_name']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public function render_searches(): void {
        $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $searches = $this->get_searches($search_term);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CRM: Poszukiwania', 'estate-office-crm') . '</h1>';
        echo '<div class="eoc-list-actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=estate-office-crm-contracts-add')) . '" class="button button-primary">' . esc_html__('Dodaj nową Umowę', 'estate-office-crm') . '</a>';
        echo '<form method="get" class="eoc-search-form">';
        echo '<input type="hidden" name="page" value="estate-office-crm-searches" />';
        echo '<input type="search" name="s" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Wyszukaj...', 'estate-office-crm') . '" />';
        echo '<button type="submit" class="button">' . esc_html__('Szukaj', 'estate-office-crm') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped eoc-list-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Numer poszukiwania', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Rodzaj nieruchomości', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Budżet', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Lokalizacja', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Typ transakcji', 'estate-office-crm') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($searches)) {
            echo '<tr><td colspan="5">' . esc_html__('Brak danych do wyświetlenia.', 'estate-office-crm') . '</td></tr>';
        } else {
            foreach ($searches as $search) {
                $view_link = add_query_arg(
                    array('page' => 'estate-office-crm-searches-view', 'search_id' => $search['id']),
                    admin_url('admin.php')
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url($view_link) . '">' . esc_html($search['search_number']) . '</a></td>';
                echo '<td>' . esc_html($search['property_type']) . '</td>';
                echo '<td>' . esc_html($search['budget']) . '</td>';
                echo '<td>' . esc_html($search['location']) . '</td>';
                echo '<td>' . esc_html($search['transaction_type']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public function render_contracts(): void {
        $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $contracts = $this->get_contracts($search_term);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CRM: Umowy', 'estate-office-crm') . '</h1>';
        echo '<div class="eoc-list-actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=estate-office-crm-contracts-add')) . '" class="button button-primary">' . esc_html__('Dodaj nową Umowę', 'estate-office-crm') . '</a>';
        echo '<form method="get" class="eoc-search-form">';
        echo '<input type="hidden" name="page" value="estate-office-crm-contracts" />';
        echo '<input type="search" name="s" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Wyszukaj...', 'estate-office-crm') . '" />';
        echo '<button type="submit" class="button">' . esc_html__('Szukaj', 'estate-office-crm') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped eoc-list-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Numer umowy', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Typ transakcji', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Data zawarcia', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Data zakończenia', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Aktualny etap', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Opiekun', 'estate-office-crm') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($contracts)) {
            echo '<tr><td colspan="6">' . esc_html__('Brak danych do wyświetlenia.', 'estate-office-crm') . '</td></tr>';
        } else {
            foreach ($contracts as $contract) {
                $view_link = add_query_arg(
                    array('page' => 'estate-office-crm-contracts-view', 'contract_id' => $contract['id']),
                    admin_url('admin.php')
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url($view_link) . '">' . esc_html($contract['contract_number']) . '</a></td>';
                echo '<td>' . esc_html($contract['transaction_type']) . '</td>';
                echo '<td>' . esc_html($contract['start_date']) . '</td>';
                echo '<td>' . esc_html($contract['end_date']) . '</td>';
                echo '<td>' . esc_html($contract['status_stage']) . '</td>';
                echo '<td>' . esc_html($contract['agent_name']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public function render_clients(): void {
        $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $clients = $this->get_clients($search_term);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CRM: Klienci', 'estate-office-crm') . '</h1>';
        echo '<div class="eoc-list-actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=estate-office-crm-contracts-add')) . '" class="button button-primary">' . esc_html__('Dodaj nową Umowę', 'estate-office-crm') . '</a>';
        echo '<form method="get" class="eoc-search-form">';
        echo '<input type="hidden" name="page" value="estate-office-crm-clients" />';
        echo '<input type="search" name="s" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Wyszukaj...', 'estate-office-crm') . '" />';
        echo '<button type="submit" class="button">' . esc_html__('Szukaj', 'estate-office-crm') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped eoc-list-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Imię i nazwisko / Nazwa', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Adres', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Telefon', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('E-mail', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Opiekun', 'estate-office-crm') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($clients)) {
            echo '<tr><td colspan="5">' . esc_html__('Brak danych do wyświetlenia.', 'estate-office-crm') . '</td></tr>';
        } else {
            foreach ($clients as $client) {
                $view_link = add_query_arg(
                    array('page' => 'estate-office-crm-clients-view', 'client_id' => $client['id']),
                    admin_url('admin.php')
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url($view_link) . '">' . esc_html($client['display_name']) . '</a></td>';
                echo '<td>' . esc_html($client['address']) . '</td>';
                echo '<td>' . esc_html($client['phone']) . '</td>';
                echo '<td>' . esc_html($client['email']) . '</td>';
                echo '<td>' . esc_html($client['agent_name']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
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

    public function render_contract_view(): void {
        $contract_id = isset($_GET['contract_id']) ? absint($_GET['contract_id']) : 0;
        $contract = $contract_id ? $this->get_contract_view($contract_id) : null;
        $error = isset($_GET['eoc_error']) ? sanitize_text_field(wp_unslash($_GET['eoc_error'])) : '';
        $stage_updated = isset($_GET['eoc_stage_updated']) ? sanitize_text_field(wp_unslash($_GET['eoc_stage_updated'])) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Profil umowy', 'estate-office-crm') . '</h1>';

        if (!$contract) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono umowy.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        if ($error === 'invalid') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Uzupełnij poprawnie dane etapu.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'db') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie udało się zapisać etapu. Spróbuj ponownie.', 'estate-office-crm') . '</p></div>';
        } elseif ($stage_updated === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Etap umowy został zaktualizowany.', 'estate-office-crm') . '</p></div>';
        }

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Dane umowy', 'estate-office-crm') . '</h2>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Numer umowy:', 'estate-office-crm') . '</strong> ' . esc_html($contract['contract_number']) . '</li>';
        echo '<li><strong>' . esc_html__('Typ transakcji:', 'estate-office-crm') . '</strong> ' . esc_html($contract['transaction_type']) . '</li>';
        echo '<li><strong>' . esc_html__('Data zawarcia:', 'estate-office-crm') . '</strong> ' . esc_html($contract['start_date']) . '</li>';
        echo '<li><strong>' . esc_html__('Data zakończenia:', 'estate-office-crm') . '</strong> ' . esc_html($contract['end_date']) . '</li>';
        echo '<li><strong>' . esc_html__('Prowizja:', 'estate-office-crm') . '</strong> ' . esc_html($contract['commission']) . '</li>';
        echo '<li><strong>' . esc_html__('Aktualny etap:', 'estate-office-crm') . '</strong> ' . esc_html($contract['status_stage']) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Aktualny etap', 'estate-office-crm') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_update_contract_stage', 'eoc_contract_stage_nonce');
        echo '<input type="hidden" name="action" value="eoc_update_contract_stage" />';
        echo '<input type="hidden" name="eoc_stage[contract_id]" value="' . esc_attr((string) $contract['id']) . '" />';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-stage-name">' . esc_html__('Etap', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-stage-name" name="eoc_stage[stage_name]" required>';
        foreach ($this->get_contract_stage_options() as $stage) {
            $selected = $stage === $contract['status_stage'] ? ' selected' : '';
            echo '<option value="' . esc_attr($stage) . '"' . $selected . '>' . esc_html($stage) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-stage-date">' . esc_html__('Data', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="date" id="eoc-stage-date" name="eoc_stage[stage_date]" /></td>';
        echo '</tr>';
        echo '</table>';
        submit_button(__('Aktualizuj etap', 'estate-office-crm'));
        echo '</form>';
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Historia etapów', 'estate-office-crm') . '</h2>';
        if (empty($contract['stages'])) {
            echo '<p>' . esc_html__('Brak historii etapów.', 'estate-office-crm') . '</p>';
        } else {
            echo '<table class="widefat striped eoc-list-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Data', 'estate-office-crm') . '</th>';
            echo '<th>' . esc_html__('Etap', 'estate-office-crm') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($contract['stages'] as $stage) {
                echo '<tr>';
                echo '<td>' . esc_html($stage['date']) . '</td>';
                echo '<td>' . esc_html($stage['name']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Klienci powiązani', 'estate-office-crm') . '</h2>';
        if (empty($contract['clients'])) {
            echo '<p>' . esc_html__('Brak przypisanych klientów.', 'estate-office-crm') . '</p>';
        } else {
            echo '<ul>';
            foreach ($contract['clients'] as $client_name) {
                echo '<li>' . esc_html($client_name) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązane oferty', 'estate-office-crm') . '</h2>';
        if (empty($contract['offers'])) {
            echo '<p>' . esc_html__('Brak powiązanych nieruchomości lub poszukiwań.', 'estate-office-crm') . '</p>';
        } else {
            echo '<ul>';
            foreach ($contract['offers'] as $offer) {
                echo '<li>' . esc_html($offer) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '</div>';
    }

    public function render_client_view(): void {
        $client_id = isset($_GET['client_id']) ? absint($_GET['client_id']) : 0;
        $client = $client_id ? $this->get_client_view($client_id) : null;
        $offers = $client_id ? $this->get_client_offers($client_id) : array();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Profil klienta', 'estate-office-crm') . '</h1>';

        if (!$client) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono klienta.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Dane klienta', 'estate-office-crm') . '</h2>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Nazwa:', 'estate-office-crm') . '</strong> ' . esc_html($client['display_name']) . '</li>';
        echo '<li><strong>' . esc_html__('Telefon:', 'estate-office-crm') . '</strong> ' . esc_html($client['phone']) . '</li>';
        echo '<li><strong>' . esc_html__('E-mail:', 'estate-office-crm') . '</strong> ' . esc_html($client['email']) . '</li>';
        echo '<li><strong>' . esc_html__('Adres:', 'estate-office-crm') . '</strong> ' . esc_html($client['address']) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązane umowy', 'estate-office-crm') . '</h2>';
        if (empty($client['contracts'])) {
            echo '<p>' . esc_html__('Brak powiązanych umów.', 'estate-office-crm') . '</p>';
        } else {
            echo '<ul>';
            foreach ($client['contracts'] as $contract) {
                $link = add_query_arg(
                    array('page' => 'estate-office-crm-contracts-view', 'contract_id' => $contract['id']),
                    admin_url('admin.php')
                );
                echo '<li><a href="' . esc_url($link) . '">' . esc_html($contract['number']) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązane oferty', 'estate-office-crm') . '</h2>';
        if (empty($offers)) {
            echo '<p>' . esc_html__('Brak powiązanych nieruchomości lub poszukiwań.', 'estate-office-crm') . '</p>';
        } else {
            echo '<ul>';
            foreach ($offers as $offer) {
                $args = array('page' => $offer['page']);
                if ($offer['property_id']) {
                    $args['property_id'] = $offer['property_id'];
                }
                if ($offer['search_id']) {
                    $args['search_id'] = $offer['search_id'];
                }
                $link = add_query_arg($args, admin_url('admin.php'));
                echo '<li><a href="' . esc_url($link) . '">' . esc_html($offer['label']) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '</div>';
    }

    public function render_property_view(): void {
        $property_id = isset($_GET['property_id']) ? absint($_GET['property_id']) : 0;
        $property = $property_id ? $this->get_property_view($property_id) : null;
        $clients = $property_id ? $this->get_property_clients($property_id) : array();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Profil nieruchomości', 'estate-office-crm') . '</h1>';

        if (!$property) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono nieruchomości.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Dane nieruchomości', 'estate-office-crm') . '</h2>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Typ:', 'estate-office-crm') . '</strong> ' . esc_html($property['property_type']) . '</li>';
        echo '<li><strong>' . esc_html__('Adres:', 'estate-office-crm') . '</strong> ' . esc_html($property['address']) . '</li>';
        echo '<li><strong>' . esc_html__('Cena:', 'estate-office-crm') . '</strong> ' . esc_html($property['price']) . '</li>';
        echo '<li><strong>' . esc_html__('Metraż:', 'estate-office-crm') . '</strong> ' . esc_html($property['area']) . '</li>';
        echo '<li><strong>' . esc_html__('Liczba pokoi:', 'estate-office-crm') . '</strong> ' . esc_html($property['rooms']) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązana umowa', 'estate-office-crm') . '</h2>';
        if ($property['contract_number']) {
            echo '<p>' . esc_html($property['contract_number']) . '</p>';
        } else {
            echo '<p>' . esc_html__('Brak powiązanej umowy.', 'estate-office-crm') . '</p>';
        }
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązani klienci', 'estate-office-crm') . '</h2>';
        if (empty($clients)) {
            echo '<p>' . esc_html__('Brak powiązanych klientów.', 'estate-office-crm') . '</p>';
        } else {
            echo '<ul>';
            foreach ($clients as $client) {
                echo '<li>' . esc_html($client) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '</div>';
    }


    public function render_search_view(): void {
        $search_id = isset($_GET['search_id']) ? absint($_GET['search_id']) : 0;
        $search = $search_id ? $this->get_search_view($search_id) : null;
        $clients = $search && $search['contract_id'] ? $this->get_contract_clients($search['contract_id']) : array();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Profil poszukiwania', 'estate-office-crm') . '</h1>';

        if (!$search) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie znaleziono poszukiwania.', 'estate-office-crm') . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Dane poszukiwania', 'estate-office-crm') . '</h2>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Typ transakcji:', 'estate-office-crm') . '</strong> ' . esc_html($search['transaction_type']) . '</li>';
        echo '<li><strong>' . esc_html__('Rodzaj nieruchomości:', 'estate-office-crm') . '</strong> ' . esc_html($search['property_type']) . '</li>';
        echo '<li><strong>' . esc_html__('Budżet:', 'estate-office-crm') . '</strong> ' . esc_html($search['budget']) . '</li>';
        echo '<li><strong>' . esc_html__('Metraż:', 'estate-office-crm') . '</strong> ' . esc_html($search['area']) . '</li>';
        echo '<li><strong>' . esc_html__('Liczba pokoi:', 'estate-office-crm') . '</strong> ' . esc_html($search['rooms']) . '</li>';
        echo '<li><strong>' . esc_html__('Lokalizacja:', 'estate-office-crm') . '</strong> ' . esc_html($search['location']) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązana umowa', 'estate-office-crm') . '</h2>';
        if (!empty($search['contract_number'])) {
            $link = add_query_arg(
                array('page' => 'estate-office-crm-contracts-view', 'contract_id' => $search['contract_id']),
                admin_url('admin.php')
            );
            echo '<p><a href="' . esc_url($link) . '">' . esc_html($search['contract_number']) . '</a></p>';
        } else {
            echo '<p>' . esc_html__('Brak powiązanej umowy.', 'estate-office-crm') . '</p>';
        }
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Powiązani klienci', 'estate-office-crm') . '</h2>';
        if (empty($clients)) {
            echo '<p>' . esc_html__('Brak powiązanych klientów.', 'estate-office-crm') . '</p>';
        } else {
            echo '<ul>';
            foreach ($clients as $client) {
                echo '<li>' . esc_html($client) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="eoc-section">';
        echo '<h2>' . esc_html__('Opis poszukiwania', 'estate-office-crm') . '</h2>';
        echo wp_kses_post($search['description']);
        echo '</div>';

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
        } elseif ($error === 'invalid') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Uzupełnij poprawnie wymagane pola poszukiwania.', 'estate-office-crm') . '</p></div>';
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
        echo '<th scope="row"><label for="eoc-search-property-type">' . esc_html__('Rodzaj nieruchomości', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-search-property-type" name="eoc_search[property_type]">';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office-crm') . '</option>';
        echo '<option value="MIESZKANIE">' . esc_html__('MIESZKANIE', 'estate-office-crm') . '</option>';
        echo '<option value="DOM">' . esc_html__('DOM', 'estate-office-crm') . '</option>';
        echo '<option value="DZIALKA">' . esc_html__('DZIAŁKA', 'estate-office-crm') . '</option>';
        echo '<option value="LOKAL">' . esc_html__('LOKAL H/U', 'estate-office-crm') . '</option>';
        echo '</select></td>';
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
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-search-city">' . esc_html__('Miasto', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-search-city" name="eoc_search[city]" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-search-district">' . esc_html__('Dzielnica', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-search-district" name="eoc_search[district]" class="regular-text" /></td>';
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

    private function get_contracts(string $search_term): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_contracts';
        $like = '%' . $wpdb->esc_like($search_term) . '%';

        if ($search_term === '') {
            $results = $wpdb->get_results(
                "SELECT id, contract_number, transaction_type, start_date, end_date, status_stage, agent_user_id\n"
                . "FROM {$table}\n"
                . "ORDER BY id DESC\n"
                . "LIMIT 50",
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, contract_number, transaction_type, start_date, end_date, status_stage, agent_user_id\n"
                    . "FROM {$table}\n"
                    . "WHERE contract_number LIKE %s\n"
                    . "   OR transaction_type LIKE %s\n"
                    . "   OR status_stage LIKE %s\n"
                    . "ORDER BY id DESC\n"
                    . "LIMIT 50",
                    $like,
                    $like,
                    $like
                ),
                ARRAY_A
            );
        }

        $contracts = array();
        foreach ($results as $contract) {
            $agent_name = '';
            if (!empty($contract['agent_user_id'])) {
                $user = get_user_by('id', (int) $contract['agent_user_id']);
                if ($user) {
                    $agent_name = $user->display_name;
                }
            }

            $contracts[] = array(
                'id' => (int) $contract['id'],
                'contract_number' => $contract['contract_number'],
                'transaction_type' => $contract['transaction_type'],
                'start_date' => $contract['start_date'],
                'end_date' => $contract['end_date'],
                'status_stage' => $contract['status_stage'],
                'agent_name' => $agent_name,
            );
        }

        return $contracts;
    }

    private function get_contract_view(int $contract_id): ?array {
        global $wpdb;
        $contracts_table = $wpdb->prefix . 'eoc_contracts';
        $clients_table = $wpdb->prefix . 'eoc_clients';
        $contract_clients_table = $wpdb->prefix . 'eoc_contract_clients';
        $properties_table = $wpdb->prefix . 'eoc_properties';
        $searches_table = $wpdb->prefix . 'eoc_searches';
        $stages_table = $wpdb->prefix . 'eoc_contract_stages';

        $contract = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, contract_number, transaction_type, start_date, end_date, commission_amount, commission_unit, status_stage\n"
                . "FROM {$contracts_table}\n"
                . "WHERE id = %d",
                $contract_id
            ),
            ARRAY_A
        );

        if (!$contract) {
            return null;
        }

        $clients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.client_type, c.first_name, c.last_name, c.company_name\n"
                . "FROM {$contract_clients_table} cc\n"
                . "JOIN {$clients_table} c ON c.id = cc.client_id\n"
                . "WHERE cc.contract_id = %d",
                $contract_id
            ),
            ARRAY_A
        );

        $client_names = array();
        foreach ($clients as $client) {
            if ($client['client_type'] === 'COMPANY') {
                $client_names[] = $client['company_name'] ?: __('Firma', 'estate-office-crm');
            } else {
                $name = trim($client['first_name'] . ' ' . $client['last_name']);
                $client_names[] = $name !== '' ? $name : __('Klient', 'estate-office-crm');
            }
        }

        $offers = array();
        $properties = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, property_type, city, street, building_number\n"
                . "FROM {$properties_table}\n"
                . "WHERE contract_id = %d",
                $contract_id
            ),
            ARRAY_A
        );
        foreach ($properties as $property) {
            $offers[] = sprintf(
                '%s - %s %s',
                $property['property_type'],
                $property['city'],
                trim($property['street'] . ' ' . $property['building_number'])
            );
        }

        $searches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, budget_min, budget_max, city, district\n"
                . "FROM {$searches_table}\n"
                . "WHERE contract_id = %d",
                $contract_id
            ),
            ARRAY_A
        );
        foreach ($searches as $search) {
            $offers[] = sprintf(
                '%s - %s %s',
                __('Poszukiwanie', 'estate-office-crm'),
                $search['city'],
                $search['district']
            );
        }

        $commission = '';
        if (!empty($contract['commission_amount'])) {
            $commission = $contract['commission_amount'] . ' ' . $contract['commission_unit'];
        }

        $stages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT stage_name, stage_date\n"
                . "FROM {$stages_table}\n"
                . "WHERE contract_id = %d\n"
                . "ORDER BY created_at DESC",
                $contract_id
            ),
            ARRAY_A
        );
        $stage_rows = array();
        foreach ($stages as $stage) {
            $stage_rows[] = array(
                'name' => $stage['stage_name'],
                'date' => $stage['stage_date'],
            );
        }

        return array(
            'id' => (int) $contract['id'],
            'contract_number' => $contract['contract_number'],
            'transaction_type' => $contract['transaction_type'],
            'start_date' => $contract['start_date'],
            'end_date' => $contract['end_date'],
            'status_stage' => $contract['status_stage'],
            'commission' => $commission,
            'clients' => $client_names,
            'offers' => $offers,
            'stages' => $stage_rows,
        );
    }

    private function get_properties(string $search_term): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_properties';
        $like = '%' . $wpdb->esc_like($search_term) . '%';

        if ($search_term === '') {
            $results = $wpdb->get_results(
                "SELECT id, property_type, city, street, building_number, price, area, rooms, manager_user_id\n"
                . "FROM {$table}\n"
                . "ORDER BY id DESC\n"
                . "LIMIT 50",
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, property_type, city, street, building_number, price, area, rooms, manager_user_id\n"
                    . "FROM {$table}\n"
                    . "WHERE property_type LIKE %s\n"
                    . "   OR city LIKE %s\n"
                    . "   OR street LIKE %s\n"
                    . "   OR building_number LIKE %s\n"
                    . "ORDER BY id DESC\n"
                    . "LIMIT 50",
                    $like,
                    $like,
                    $like,
                    $like
                ),
                ARRAY_A
            );
        }

        $properties = array();
        foreach ($results as $property) {
            $agent_name = '';
            if (!empty($property['manager_user_id'])) {
                $user = get_user_by('id', (int) $property['manager_user_id']);
                if ($user) {
                    $agent_name = $user->display_name;
                }
            }

            $address = trim($property['city'] . ' ' . $property['street'] . ' ' . $property['building_number']);
            $price_per_sqm = '';
            if (!empty($property['price']) && !empty($property['area'])) {
                $price_per_sqm = round((float) $property['price'] / (float) $property['area'], 2);
            }

            $properties[] = array(
                'id' => (int) $property['id'],
                'offer_number' => 'OF-' . $property['id'],
                'address' => $address,
                'price' => $property['price'],
                'price_per_sqm' => $price_per_sqm,
                'area' => $property['area'],
                'rooms' => $property['rooms'],
                'agent_name' => $agent_name,
            );
        }

        return $properties;
    }

    private function get_property_view(int $property_id): ?array {
        global $wpdb;
        $properties_table = $wpdb->prefix . 'eoc_properties';
        $contracts_table = $wpdb->prefix . 'eoc_contracts';

        $property = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, contract_id, property_type, city, street, building_number, price, area, rooms\n"
                . "FROM {$properties_table}\n"
                . "WHERE id = %d",
                $property_id
            ),
            ARRAY_A
        );

        if (!$property) {
            return null;
        }

        $contract_number = '';
        if (!empty($property['contract_id'])) {
            $contract_number = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT contract_number FROM {$contracts_table} WHERE id = %d",
                    $property['contract_id']
                )
            );
        }

        return array(
            'id' => (int) $property['id'],
            'property_type' => $property['property_type'],
            'address' => trim($property['city'] . ' ' . $property['street'] . ' ' . $property['building_number']),
            'price' => $property['price'],
            'area' => $property['area'],
            'rooms' => $property['rooms'],
            'contract_number' => $contract_number,
        );
    }

    private function get_property_clients(int $property_id): array {
        global $wpdb;
        $properties_table = $wpdb->prefix . 'eoc_properties';

        $contract_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT contract_id FROM {$properties_table} WHERE id = %d",
                $property_id
            )
        );

        if (!$contract_id) {
            return array();
        }

        return $this->get_contract_clients((int) $contract_id);
    }

    private function get_clients(string $search_term): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_clients';
        $like = '%' . $wpdb->esc_like($search_term) . '%';

        if ($search_term === '') {
            $results = $wpdb->get_results(
                "SELECT id, client_type, first_name, last_name, company_name, phone, email, city, street, building_number\n"
                . "FROM {$table}\n"
                . "ORDER BY id DESC\n"
                . "LIMIT 50",
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, client_type, first_name, last_name, company_name, phone, email, city, street, building_number\n"
                    . "FROM {$table}\n"
                    . "WHERE first_name LIKE %s\n"
                    . "   OR last_name LIKE %s\n"
                    . "   OR company_name LIKE %s\n"
                    . "   OR phone LIKE %s\n"
                    . "   OR email LIKE %s\n"
                    . "   OR city LIKE %s\n"
                    . "   OR street LIKE %s\n"
                    . "ORDER BY id DESC\n"
                    . "LIMIT 50",
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                    $like
                ),
                ARRAY_A
            );
        }

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
                'address' => trim($client['city'] . ' ' . $client['street'] . ' ' . $client['building_number']),
                'agent_name' => '',
            );
        }

        return $clients;
    }

    private function get_client_view(int $client_id): ?array {
        global $wpdb;
        $clients_table = $wpdb->prefix . 'eoc_clients';
        $contracts_table = $wpdb->prefix . 'eoc_contracts';
        $contract_clients_table = $wpdb->prefix . 'eoc_contract_clients';

        $client = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, client_type, first_name, last_name, company_name, phone, email, city, street, building_number\n"
                . "FROM {$clients_table}\n"
                . "WHERE id = %d",
                $client_id
            ),
            ARRAY_A
        );

        if (!$client) {
            return null;
        }

        if ($client['client_type'] === 'COMPANY') {
            $display_name = $client['company_name'] ?: __('Firma', 'estate-office-crm');
        } else {
            $display_name = trim($client['first_name'] . ' ' . $client['last_name']);
            $display_name = $display_name !== '' ? $display_name : __('Klient', 'estate-office-crm');
        }

        $contracts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id, c.contract_number\n"
                . "FROM {$contract_clients_table} cc\n"
                . "JOIN {$contracts_table} c ON c.id = cc.contract_id\n"
                . "WHERE cc.client_id = %d",
                $client_id
            ),
            ARRAY_A
        );

        $contract_numbers = array();
        foreach ($contracts as $contract) {
            $contract_numbers[] = array(
                'id' => (int) $contract['id'],
                'number' => $contract['contract_number'],
            );
        }

        return array(
            'id' => (int) $client['id'],
            'display_name' => $display_name,
            'phone' => $client['phone'],
            'email' => $client['email'],
            'address' => trim($client['city'] . ' ' . $client['street'] . ' ' . $client['building_number']),
            'contracts' => $contract_numbers,
        );
    }

    private function get_client_offers(int $client_id): array {
        global $wpdb;
        $contract_clients_table = $wpdb->prefix . 'eoc_contract_clients';
        $properties_table = $wpdb->prefix . 'eoc_properties';
        $searches_table = $wpdb->prefix . 'eoc_searches';

        $contract_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT contract_id FROM {$contract_clients_table} WHERE client_id = %d",
                $client_id
            )
        );

        if (empty($contract_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($contract_ids), '%d'));

        $offers = array();

        $properties = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, property_type, city, street, building_number\n"
                . "FROM {$properties_table}\n"
                . "WHERE contract_id IN ({$placeholders})",
                $contract_ids
            ),
            ARRAY_A
        );
        foreach ($properties as $property) {
            $offers[] = array(
                'page' => 'estate-office-crm-properties-view',
                'property_id' => (int) $property['id'],
                'search_id' => null,
                'label' => sprintf(
                    '%s - %s %s',
                    $property['property_type'],
                    $property['city'],
                    trim($property['street'] . ' ' . $property['building_number'])
                ),
            );
        }

        $searches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, city, district\n"
                . "FROM {$searches_table}\n"
                . "WHERE contract_id IN ({$placeholders})",
                $contract_ids
            ),
            ARRAY_A
        );
        foreach ($searches as $search) {
            $offers[] = array(
                'page' => 'estate-office-crm-searches-view',
                'property_id' => null,
                'search_id' => (int) $search['id'],
                'label' => sprintf(
                    '%s - %s %s',
                    __('Poszukiwanie', 'estate-office-crm'),
                    $search['city'],
                    $search['district']
                ),
            );
        }

        return $offers;
    }

    private function get_searches(string $search_term): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_searches';
        $like = '%' . $wpdb->esc_like($search_term) . '%';

        if ($search_term === '') {
            $results = $wpdb->get_results(
                "SELECT id, transaction_type, property_type, budget_min, budget_max, area_min, area_max, rooms_min, rooms_max, city, district\n"
                . "FROM {$table}\n"
                . "ORDER BY id DESC\n"
                . "LIMIT 50",
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, transaction_type, property_type, budget_min, budget_max, area_min, area_max, rooms_min, rooms_max, city, district\n"
                    . "FROM {$table}\n"
                    . "WHERE transaction_type LIKE %s\n"
                    . "   OR property_type LIKE %s\n"
                    . "   OR city LIKE %s\n"
                    . "   OR district LIKE %s\n"
                    . "ORDER BY id DESC\n"
                    . "LIMIT 50",
                    $like,
                    $like,
                    $like,
                    $like
                ),
                ARRAY_A
            );
        }

        $searches = array();
        foreach ($results as $search) {
            $budget = $this->format_range($search['budget_min'], $search['budget_max']);
            $area = $this->format_range($search['area_min'], $search['area_max'], 'm²');
            $rooms = $this->format_range($search['rooms_min'], $search['rooms_max']);

            $searches[] = array(
                'id' => (int) $search['id'],
                'search_number' => 'POSZ-' . $search['id'],
                'transaction_type' => $search['transaction_type'],
                'property_type' => $search['property_type'],
                'budget' => $budget,
                'location' => trim($search['city'] . ' ' . $search['district']),
                'area' => $area,
                'rooms' => $rooms,
            );
        }

        return $searches;
    }

    private function get_search_view(int $search_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_searches';
        $contracts_table = $wpdb->prefix . 'eoc_contracts';

        $search = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, contract_id, transaction_type, property_type, budget_min, budget_max, area_min, area_max, rooms_min, rooms_max, city, district, description\n"
                . "FROM {$table}\n"
                . "WHERE id = %d",
                $search_id
            ),
            ARRAY_A
        );

        if (!$search) {
            return null;
        }

        $contract_id = $search['contract_id'] ?? null;
        $contract_number = '';
        if ($contract_id) {
            $contract_number = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT contract_number FROM {$contracts_table} WHERE id = %d",
                    $contract_id
                )
            );
        }

        return array(
            'id' => (int) $search['id'],
            'transaction_type' => $search['transaction_type'],
            'property_type' => $search['property_type'],
            'budget' => $this->format_range($search['budget_min'], $search['budget_max']),
            'area' => $this->format_range($search['area_min'], $search['area_max'], 'm²'),
            'rooms' => $this->format_range($search['rooms_min'], $search['rooms_max']),
            'location' => trim($search['city'] . ' ' . $search['district']),
            'description' => $search['description'] ?: '',
            'contract_id' => $contract_id ? (int) $contract_id : null,
            'contract_number' => $contract_number,
        );
    }

    private function format_range($min, $max, string $unit = ''): string {
        $min = $min !== null && $min !== '' ? (string) $min : '';
        $max = $max !== null && $max !== '' ? (string) $max : '';

        if ($min === '' && $max === '') {
            return '';
        }

        $range = $min !== '' && $max !== '' ? $min . ' - ' . $max : ($min !== '' ? $min : $max);
        if ($unit !== '') {
            $range .= ' ' . $unit;
        }

        return $range;
    }

    private function get_contract_clients(int $contract_id): array {
        global $wpdb;
        $contract_clients_table = $wpdb->prefix . 'eoc_contract_clients';
        $clients_table = $wpdb->prefix . 'eoc_clients';

        $clients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.client_type, c.first_name, c.last_name, c.company_name\n"
                . "FROM {$contract_clients_table} cc\n"
                . "JOIN {$clients_table} c ON c.id = cc.client_id\n"
                . "WHERE cc.contract_id = %d",
                $contract_id
            ),
            ARRAY_A
        );

        $names = array();
        foreach ($clients as $client) {
            if ($client['client_type'] === 'COMPANY') {
                $names[] = $client['company_name'] ?: __('Firma', 'estate-office-crm');
            } else {
                $name = trim($client['first_name'] . ' ' . $client['last_name']);
                $names[] = $name !== '' ? $name : __('Klient', 'estate-office-crm');
            }
        }

        return $names;
    }

    private function get_contract_stage_options(): array {
        return array(
            __('Umowa Pośrednictwa', 'estate-office-crm'),
            __('Publikacja w MLS', 'estate-office-crm'),
            __('Przygotowanie oferty', 'estate-office-crm'),
            __('Publikacja oferty', 'estate-office-crm'),
            __('Marketing i prezentacje', 'estate-office-crm'),
            __('Oferta kupna', 'estate-office-crm'),
            __('Negocjacje', 'estate-office-crm'),
            __('Umowa przedwstępna', 'estate-office-crm'),
            __('Umowa przyrzeczona', 'estate-office-crm'),
            __('Przekazanie lokalu', 'estate-office-crm'),
            __('Umowa zakończona', 'estate-office-crm'),
        );
    }
}
