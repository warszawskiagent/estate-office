<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Clients {
    private const ACTION_SAVE = 'eoc_save_client';

    public function register(): void {
        add_action('admin_post_' . self::ACTION_SAVE, array($this, 'handle_save'));
    }

    public function handle_save(): void {
        if (!current_user_can('eoc_access')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_save_client', 'eoc_client_nonce');

        $data = isset($_POST['eoc_client']) ? wp_unslash($_POST['eoc_client']) : array();
        $contract_id = isset($data['contract_id']) ? absint($data['contract_id']) : 0;
        $existing_client_id = isset($data['existing_client_id']) ? absint($data['existing_client_id']) : 0;
        $client_type = isset($data['client_type']) ? sanitize_text_field($data['client_type']) : '';
        $next_step = isset($data['next_step']) ? sanitize_text_field($data['next_step']) : 'add_another';

        if (!$contract_id) {
            $this->redirect_with_error('missing_contract');
        }

        $contract = $this->get_contract($contract_id);
        if (!$contract) {
            $this->redirect_with_error('missing_contract');
        }

        if ($existing_client_id) {
            if (!$this->client_exists($existing_client_id)) {
                $this->redirect_with_error('invalid');
            }
            $this->attach_client($contract_id, $existing_client_id);
            $this->redirect_after_save($contract, $next_step, $contract_id);
        }

        $allowed_types = array('PERSON', 'COMPANY');
        if (!in_array($client_type, $allowed_types, true)) {
            $this->redirect_with_error('invalid');
        }

        $first_name = isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
        $last_name = isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '';
        $company_name = isset($data['company_name']) ? sanitize_text_field($data['company_name']) : '';
        $representative_name = isset($data['representative_name']) ? sanitize_text_field($data['representative_name']) : '';

        if ($client_type === 'PERSON' && ($first_name === '' || $last_name === '')) {
            $this->redirect_with_error('invalid');
        }

        if ($client_type === 'COMPANY' && ($company_name === '' || $representative_name === '')) {
            $this->redirect_with_error('invalid');
        }

        $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
        $email = isset($data['email']) ? sanitize_email($data['email']) : '';
        $website = isset($data['website']) ? esc_url_raw($data['website']) : '';
        $pesel = isset($data['pesel']) ? sanitize_text_field($data['pesel']) : '';
        $id_type = isset($data['id_type']) ? sanitize_text_field($data['id_type']) : '';
        $id_number = isset($data['id_number']) ? sanitize_text_field($data['id_number']) : '';
        $tax_id = isset($data['tax_id']) ? sanitize_text_field($data['tax_id']) : '';
        $krs = isset($data['krs']) ? sanitize_text_field($data['krs']) : '';
        $regon = isset($data['regon']) ? sanitize_text_field($data['regon']) : '';

        $street = isset($data['street']) ? sanitize_text_field($data['street']) : '';
        $building_number = isset($data['building_number']) ? sanitize_text_field($data['building_number']) : '';
        $unit_number = isset($data['unit_number']) ? sanitize_text_field($data['unit_number']) : '';
        $postal_code = isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '';
        $city = isset($data['city']) ? sanitize_text_field($data['city']) : '';
        $country = isset($data['country']) ? sanitize_text_field($data['country']) : '';

        $mailing_same = !empty($data['mailing_same']);
        $mailing_street = isset($data['mailing_street']) ? sanitize_text_field($data['mailing_street']) : '';
        $mailing_building_number = isset($data['mailing_building_number']) ? sanitize_text_field($data['mailing_building_number']) : '';
        $mailing_unit_number = isset($data['mailing_unit_number']) ? sanitize_text_field($data['mailing_unit_number']) : '';
        $mailing_postal_code = isset($data['mailing_postal_code']) ? sanitize_text_field($data['mailing_postal_code']) : '';
        $mailing_city = isset($data['mailing_city']) ? sanitize_text_field($data['mailing_city']) : '';
        $mailing_country = isset($data['mailing_country']) ? sanitize_text_field($data['mailing_country']) : '';

        if ($mailing_same) {
            $mailing_street = $street;
            $mailing_building_number = $building_number;
            $mailing_unit_number = $unit_number;
            $mailing_postal_code = $postal_code;
            $mailing_city = $city;
            $mailing_country = $country;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eoc_clients';
        $now = current_time('mysql');

        $result = $wpdb->insert(
            $table,
            array(
                'client_type' => $client_type,
                'first_name' => $first_name ?: null,
                'last_name' => $last_name ?: null,
                'company_name' => $company_name ?: null,
                'representative_name' => $representative_name ?: null,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'website' => $website ?: null,
                'pesel' => $pesel ?: null,
                'id_number' => $id_number ?: null,
                'id_type' => $id_type ?: null,
                'tax_id' => $tax_id ?: null,
                'krs' => $krs ?: null,
                'regon' => $regon ?: null,
                'street' => $street ?: null,
                'building_number' => $building_number ?: null,
                'unit_number' => $unit_number ?: null,
                'postal_code' => $postal_code ?: null,
                'city' => $city ?: null,
                'country' => $country ?: null,
                'mailing_street' => $mailing_street ?: null,
                'mailing_building_number' => $mailing_building_number ?: null,
                'mailing_unit_number' => $mailing_unit_number ?: null,
                'mailing_postal_code' => $mailing_postal_code ?: null,
                'mailing_city' => $mailing_city ?: null,
                'mailing_country' => $mailing_country ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s'
            )
        );

        if ($result === false) {
            $this->redirect_with_error('db');
        }

        $client_id = (int) $wpdb->insert_id;
        $this->attach_client($contract_id, $client_id);

        $this->redirect_after_save($contract, $next_step, $contract_id);
    }

    private function attach_client(int $contract_id, int $client_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_contract_clients';
        $now = current_time('mysql');

        $wpdb->replace(
            $table,
            array(
                'contract_id' => $contract_id,
                'client_id' => $client_id,
                'created_at' => $now,
            ),
            array('%d', '%d', '%s')
        );
    }

    private function get_contract(int $contract_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_contracts';

        $contract = $wpdb->get_row(
            $wpdb->prepare("SELECT id, transaction_type FROM {$table} WHERE id = %d", $contract_id),
            ARRAY_A
        );

        return $contract ?: null;
    }

    private function client_exists(int $client_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'eoc_clients';

        $found = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $client_id)
        );

        return (bool) $found;
    }

    private function redirect_after_save(array $contract, string $next_step, int $contract_id): void {
        if ($next_step === 'add_another') {
            wp_safe_redirect(add_query_arg(array(
                'contract_id' => $contract_id,
                'eoc_success' => '1',
            ), admin_url('admin.php?page=estate-office-crm-clients-add')));
            exit;
        }

        $transaction_type = strtoupper($contract['transaction_type']);
        if (in_array($transaction_type, array('SPRZEDAZ', 'WYNAJEM'), true)) {
            wp_safe_redirect(add_query_arg('contract_id', $contract_id, admin_url('admin.php?page=estate-office-crm-properties-add')));
            exit;
        }

        wp_safe_redirect(add_query_arg('contract_id', $contract_id, admin_url('admin.php?page=estate-office-crm-searches-add')));
        exit;
    }

    private function redirect_with_error(string $code): void {
        wp_safe_redirect(add_query_arg('eoc_error', $code, wp_get_referer()));
        exit;
    }
}
