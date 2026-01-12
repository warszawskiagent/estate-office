<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Properties {
    private const ACTION_SAVE = 'eoc_save_property';

    public function register(): void {
        add_action('admin_post_' . self::ACTION_SAVE, array($this, 'handle_save'));
    }

    public function handle_save(): void {
        if (!current_user_can('eoc_access')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_save_property', 'eoc_property_nonce');

        $data = isset($_POST['eoc_property']) ? wp_unslash($_POST['eoc_property']) : array();
        $contract_id = isset($data['contract_id']) ? absint($data['contract_id']) : 0;
        $property_type = isset($data['property_type']) ? sanitize_text_field($data['property_type']) : '';

        if (!$contract_id) {
            $this->redirect_with_error('missing_contract');
        }

        $contract = $this->get_contract($contract_id);
        if (!$contract) {
            $this->redirect_with_error('missing_contract');
        }

        $allowed_types = array('MIESZKANIE', 'DOM', 'DZIALKA', 'LOKAL');
        if (!in_array($property_type, $allowed_types, true)) {
            $this->redirect_with_error('invalid');
        }

        $street = isset($data['street']) ? sanitize_text_field($data['street']) : '';
        $building_number = isset($data['building_number']) ? sanitize_text_field($data['building_number']) : '';
        $unit_number = isset($data['unit_number']) ? sanitize_text_field($data['unit_number']) : '';
        $postal_code = isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '';
        $district = isset($data['district']) ? sanitize_text_field($data['district']) : '';
        $city = isset($data['city']) ? sanitize_text_field($data['city']) : '';

        if (in_array($property_type, array('MIESZKANIE', 'LOKAL'), true)) {
            if ($street === '' || $building_number === '' || $postal_code === '' || $city === '') {
                $this->redirect_with_error('invalid');
            }
        }

        $price = isset($data['price']) ? $this->sanitize_decimal($data['price']) : null;
        $area = isset($data['area']) ? $this->sanitize_decimal($data['area']) : null;
        $rooms = isset($data['rooms']) ? absint($data['rooms']) : null;

        global $wpdb;
        $table = $wpdb->prefix . 'eoc_properties';
        $now = current_time('mysql');

        $result = $wpdb->insert(
            $table,
            array(
                'contract_id' => $contract_id,
                'transaction_type' => $contract['transaction_type'],
                'property_type' => $property_type,
                'city' => $city ?: null,
                'district' => $district ?: null,
                'street' => $street ?: null,
                'building_number' => $building_number ?: null,
                'unit_number' => $unit_number ?: null,
                'postal_code' => $postal_code ?: null,
                'price' => $price !== null ? $price : null,
                'area' => $area !== null ? $area : null,
                'rooms' => $rooms ?: null,
                'manager_user_id' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            $this->redirect_with_error('db');
        }

        wp_safe_redirect(add_query_arg('eoc_success', '1', admin_url('admin.php?page=estate-office-crm-properties')));
        exit;
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

    private function sanitize_decimal($value): ?string {
        $value = sanitize_text_field($value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        return $value !== '' ? $value : null;
    }

    private function redirect_with_error(string $code): void {
        wp_safe_redirect(add_query_arg('eoc_error', $code, wp_get_referer()));
        exit;
    }
}
