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
}
