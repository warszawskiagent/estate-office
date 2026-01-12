<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Searches {
    private const ACTION_SAVE = 'eoc_save_search';

    public function register(): void {
        add_action('admin_post_' . self::ACTION_SAVE, array($this, 'handle_save'));
    }

    public function handle_save(): void {
        if (!current_user_can('eoc_access')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_save_search', 'eoc_search_nonce');

        $data = isset($_POST['eoc_search']) ? wp_unslash($_POST['eoc_search']) : array();
        $contract_id = isset($data['contract_id']) ? absint($data['contract_id']) : 0;

        if (!$contract_id) {
            $this->redirect_with_error('missing_contract');
        }

        $contract = $this->get_contract($contract_id);
        if (!$contract) {
            $this->redirect_with_error('missing_contract');
        }

        $price_min = isset($data['price_min']) ? $this->sanitize_decimal($data['price_min']) : null;
        $price_max = isset($data['price_max']) ? $this->sanitize_decimal($data['price_max']) : null;
        $area_min = isset($data['area_min']) ? $this->sanitize_decimal($data['area_min']) : null;
        $area_max = isset($data['area_max']) ? $this->sanitize_decimal($data['area_max']) : null;
        $rooms_min = isset($data['rooms_min']) ? absint($data['rooms_min']) : null;
        $rooms_max = isset($data['rooms_max']) ? absint($data['rooms_max']) : null;
        $property_type = isset($data['property_type']) ? sanitize_text_field($data['property_type']) : '';
        $city = isset($data['city']) ? sanitize_text_field($data['city']) : '';
        $district = isset($data['district']) ? sanitize_text_field($data['district']) : '';
        $description = isset($data['description']) ? wp_kses_post($data['description']) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'eoc_searches';
        $now = current_time('mysql');

        $result = $wpdb->insert(
            $table,
            array(
                'contract_id' => $contract_id,
                'transaction_type' => $contract['transaction_type'],
                'property_type' => $property_type ?: null,
                'budget_min' => $price_min !== null ? $price_min : null,
                'budget_max' => $price_max !== null ? $price_max : null,
                'area_min' => $area_min !== null ? $area_min : null,
                'area_max' => $area_max !== null ? $area_max : null,
                'rooms_min' => $rooms_min ?: null,
                'rooms_max' => $rooms_max ?: null,
                'city' => $city ?: null,
                'district' => $district ?: null,
                'description' => $description ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            $this->redirect_with_error('db');
        }

        wp_safe_redirect(add_query_arg('eoc_success', '1', admin_url('admin.php?page=estate-office-crm-searches')));
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
