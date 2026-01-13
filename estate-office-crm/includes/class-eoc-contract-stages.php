<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Contract_Stages {
    private const ACTION_SAVE = 'eoc_update_contract_stage';

    public function register(): void {
        add_action('admin_post_' . self::ACTION_SAVE, array($this, 'handle_save'));
    }

    public function handle_save(): void {
        if (!current_user_can('eoc_access')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_update_contract_stage', 'eoc_contract_stage_nonce');

        $data = isset($_POST['eoc_stage']) ? wp_unslash($_POST['eoc_stage']) : array();
        $contract_id = isset($data['contract_id']) ? absint($data['contract_id']) : 0;
        $stage_name = isset($data['stage_name']) ? sanitize_text_field($data['stage_name']) : '';
        $stage_date = isset($data['stage_date']) ? sanitize_text_field($data['stage_date']) : '';

        if (!$contract_id || $stage_name === '') {
            $this->redirect_with_error('invalid');
        }

        global $wpdb;
        $contracts_table = $wpdb->prefix . 'eoc_contracts';
        $stages_table = $wpdb->prefix . 'eoc_contract_stages';

        $contract_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$contracts_table} WHERE id = %d", $contract_id)
        );

        if (!$contract_exists) {
            $this->redirect_with_error('invalid');
        }

        $now = current_time('mysql');

        $inserted = $wpdb->insert(
            $stages_table,
            array(
                'contract_id' => $contract_id,
                'stage_name' => $stage_name,
                'stage_date' => $stage_date ?: null,
                'created_at' => $now,
            ),
            array('%d', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            $this->redirect_with_error('db');
        }

        $wpdb->update(
            $contracts_table,
            array(
                'status_stage' => $stage_name,
                'updated_at' => $now,
            ),
            array('id' => $contract_id),
            array('%s', '%s'),
            array('%d')
        );

        wp_safe_redirect(add_query_arg('eoc_stage_updated', '1', wp_get_referer()));
        exit;
    }

    private function redirect_with_error(string $code): void {
        wp_safe_redirect(add_query_arg('eoc_error', $code, wp_get_referer()));
        exit;
    }
}
