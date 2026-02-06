<?php

if (!defined('ABSPATH')) {
    exit;
}

class EstateOffice_Contracts
{
    private const TRANSACTION_TYPES = array('SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM');
    private const COMMISSION_UNITS = array('%', 'PLN', 'EUR', 'USD');

    public function create_contract($data)
    {
        $contract_number = isset($data['contract_number']) ? sanitize_text_field($data['contract_number']) : '';
        $transaction_type = isset($data['transaction_type']) ? strtoupper(sanitize_text_field($data['transaction_type'])) : '';
        $signed_date = isset($data['signed_date']) ? sanitize_text_field($data['signed_date']) : '';
        $end_date = isset($data['end_date']) ? sanitize_text_field($data['end_date']) : '';
        $open_ended = !empty($data['open_ended']);
        $commission_amount = isset($data['commission_amount']) && $data['commission_amount'] !== '' ? floatval($data['commission_amount']) : null;
        $commission_unit = isset($data['commission_unit']) ? sanitize_text_field($data['commission_unit']) : '';

        if (!$contract_number) {
            return new WP_Error('missing_contract_number', 'Numer umowy jest wymagany.');
        }

        if (!$transaction_type || !in_array($transaction_type, self::TRANSACTION_TYPES, true)) {
            return new WP_Error('invalid_transaction_type', 'Nieprawidłowy typ transakcji.');
        }

        if (!$signed_date || !$this->is_valid_date($signed_date)) {
            return new WP_Error('invalid_signed_date', 'Nieprawidłowa data zawarcia.');
        }

        if (!$open_ended && $end_date && !$this->is_valid_date($end_date)) {
            return new WP_Error('invalid_end_date', 'Nieprawidłowa data zakończenia.');
        }

        if ($commission_unit && !in_array($commission_unit, self::COMMISSION_UNITS, true)) {
            return new WP_Error('invalid_commission_unit', 'Nieprawidłowa jednostka prowizji.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'estateoffice_contracts';

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE contract_number = %s LIMIT 1",
                $contract_number
            )
        );

        if ($existing) {
            return new WP_Error('duplicate_contract_number', 'Taki numer umowy już istnieje.');
        }

        $data_to_insert = array(
            'contract_number' => $contract_number,
            'transaction_type' => $transaction_type,
            'signed_date' => $signed_date,
            'end_date' => $open_ended ? null : ($end_date ?: null),
            'is_open_ended' => $open_ended ? 1 : 0,
            'status_stage' => 'Umowa pośrednictwa',
        );

        $formats = array('%s', '%s', '%s', '%s', '%d', '%s');

        if ($commission_amount !== null) {
            $data_to_insert['commission_amount'] = $commission_amount;
            $formats[] = '%f';
        }

        if ($commission_unit) {
            $data_to_insert['commission_unit'] = $commission_unit;
            $formats[] = '%s';
        }

        $inserted = $wpdb->insert($table, $data_to_insert, $formats);

        if ($inserted === false) {
            return new WP_Error('db_insert_failed', 'Nie udało się zapisać umowy.');
        }

        return (int) $wpdb->insert_id;
    }

    private function is_valid_date($date)
    {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return false;
        }

        $formatted = gmdate('Y-m-d', $timestamp);
        return $formatted === $date;
    }
}
