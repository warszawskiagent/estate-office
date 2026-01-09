<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Contracts {
    private const ACTION_SAVE = 'eoc_save_contract';

    public function register(): void {
        add_action('admin_post_' . self::ACTION_SAVE, array($this, 'handle_save'));
    }

    public function handle_save(): void {
        if (!current_user_can('eoc_access')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_save_contract', 'eoc_contract_nonce');

        $data = isset($_POST['eoc_contract']) ? wp_unslash($_POST['eoc_contract']) : array();
        $contract_number = isset($data['contract_number']) ? sanitize_text_field($data['contract_number']) : '';
        $transaction_type = isset($data['transaction_type']) ? sanitize_text_field($data['transaction_type']) : '';
        $start_date = isset($data['start_date']) ? sanitize_text_field($data['start_date']) : '';
        $end_date = isset($data['end_date']) ? sanitize_text_field($data['end_date']) : '';
        $is_open_ended = !empty($data['is_open_ended']) ? 1 : 0;
        $commission_amount = isset($data['commission_amount']) ? $this->sanitize_decimal($data['commission_amount']) : null;
        $commission_unit = isset($data['commission_unit']) ? sanitize_text_field($data['commission_unit']) : '';

        $allowed_transactions = array('SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM');
        $allowed_units = array('%', 'PLN', 'EUR', 'USD');

        if ($contract_number === '' || !in_array($transaction_type, $allowed_transactions, true)) {
            $this->redirect_with_error('invalid');
        }

        if ($commission_unit !== '' && !in_array($commission_unit, $allowed_units, true)) {
            $this->redirect_with_error('invalid');
        }

        if ($is_open_ended) {
            $end_date = '';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eoc_contracts';

        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE contract_number = %s", $contract_number)
        );

        if ($existing) {
            $this->redirect_with_error('duplicate');
        }

        $now = current_time('mysql');
        $result = $wpdb->insert(
            $table,
            array(
                'contract_number' => $contract_number,
                'transaction_type' => $transaction_type,
                'start_date' => $start_date ?: null,
                'end_date' => $end_date ?: null,
                'is_open_ended' => $is_open_ended,
                'commission_amount' => $commission_amount !== null ? $commission_amount : null,
                'commission_unit' => $commission_unit ?: null,
                'status_stage' => __('Umowa pośrednictwa', 'estate-office-crm'),
                'agent_user_id' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            $this->redirect_with_error('db');
        }

        wp_safe_redirect(add_query_arg('eoc_success', '1', admin_url('admin.php?page=estate-office-crm-contracts')));
        exit;
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
