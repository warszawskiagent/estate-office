<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Transactions
{
    /** @var array<string, string> */
    private array $tables;
    private bool $create_request_updated_existing = false;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'handle_frontend_transaction_request']);
    }

    public function handle_frontend_transaction_request(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if (! in_array($action, ['create_transaction', 'update_transaction', 'delete_transaction'], true)) {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if ($action === 'delete_transaction') {
            if (! current_user_can('manage_options')) {
                wp_die('Tylko administrator moze usuwac transakcje.');
            }

            check_admin_referer('eocrm_delete_transaction', 'eocrm_transaction_delete_nonce');
            $transaction_id = isset($_POST['transaction_id']) ? absint((string) wp_unslash($_POST['transaction_id'])) : 0;
            $result = $this->delete_transaction($transaction_id);
            if (is_wp_error($result)) {
                $this->redirect_to_profile($transaction_id, 'transaction_error', $result->get_error_message());
            }

            $this->redirect_to_list('transaction_deleted', 'Transakcja zostala usunieta.');
        }

        if (
            ! current_user_can('eocrm_manage_transactions')
            && ! current_user_can('eocrm_manage_agreements')
            && ! current_user_can('manage_options')
        ) {
            wp_die('Brak uprawnien do zarzadzania transakcjami.');
        }

        if ($action === 'update_transaction') {
            check_admin_referer('eocrm_update_transaction', 'eocrm_transaction_nonce');
            $transaction_id = isset($_POST['transaction_id']) ? absint((string) wp_unslash($_POST['transaction_id'])) : 0;
            $result = $this->update_transaction_from_request($transaction_id);
            if (is_wp_error($result)) {
                $this->redirect_to_edit_form($transaction_id, 'transaction_error', $result->get_error_message());
            }

            $this->redirect_to_profile((int) $result, 'transaction_updated', 'Transakcja zostala zaktualizowana.');
        }

        check_admin_referer('eocrm_create_transaction', 'eocrm_transaction_nonce');

        $this->create_request_updated_existing = false;
        $result = $this->create_transaction_from_request();
        if (is_wp_error($result)) {
            $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
            $this->redirect_to_form($agreement_id, 'transaction_error', $result->get_error_message());
        }

        if ($this->create_request_updated_existing) {
            $this->redirect_to_profile((int) $result, 'transaction_updated', 'Transakcja czesciowa zostala zaktualizowana.');
        }

        $this->redirect_to_profile((int) $result, 'transaction_created', 'Transakcja zostala dodana.');
    }

    /**
     * @return int|WP_Error
     */
    private function create_transaction_from_request()
    {
        global $wpdb;

        $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
        if ($agreement_id <= 0) {
            return new WP_Error('eocrm_transaction_agreement', 'Brak powiazanej umowy.');
        }

        $agreement = $this->get_agreement($agreement_id);
        if (! is_array($agreement)) {
            return new WP_Error('eocrm_transaction_agreement_missing', 'Umowa nie istnieje lub nie masz do niej dostepu.');
        }

        $property_id = isset($_POST['property_id']) ? absint((string) wp_unslash($_POST['property_id'])) : 0;
        $property = null;
        $linked_properties = $this->get_linked_properties($agreement_id, 3);
        if ($property_id <= 0 && count($linked_properties) === 1) {
            $property_id = (int) ($linked_properties[0]['id'] ?? 0);
        }

        if ($property_id > 0) {
            $property = $this->get_property_for_agreement($agreement_id, $property_id);
            if (! is_array($property)) {
                return new WP_Error('eocrm_transaction_property', 'Wybrana nieruchomosc nie jest powiazana z ta umowa.');
            }
        }

        $commission_stage_name = $this->sanitize_commission_stage_name($this->post_text('commission_stage_name'), $agreement);
        $commission_stage_row = $commission_stage_name !== '' ? $this->find_commission_stage_row($agreement, $commission_stage_name) : null;
        $commission_due_rows = $commission_stage_name !== '' ? $this->get_due_commission_stage_rows($agreement_id, $agreement, $commission_stage_name, 0) : [];
        $commission_effective = ! empty($commission_due_rows) ? $this->calculate_effective_commission($agreement, $commission_due_rows) : null;
        if (is_wp_error($commission_effective)) {
            return $commission_effective;
        }
        $transaction_date = $this->post_text('transaction_date');
        $requires_transaction_date = $commission_stage_name === '' || $this->is_settlement_commission_stage($commission_stage_name);
        if ($requires_transaction_date && ! $this->is_valid_date($transaction_date)) {
            return new WP_Error('eocrm_transaction_date', 'Podaj poprawna date transakcji (RRRR-MM-DD).');
        }
        if (! $requires_transaction_date && $transaction_date !== '' && ! $this->is_valid_date($transaction_date)) {
            return new WP_Error('eocrm_transaction_date', 'Podaj poprawna date transakcji (RRRR-MM-DD).');
        }
        $commission_amount_raw = $this->post_text('commission_amount');
        $commission_unit_raw = $this->post_text('commission_unit');
        $commission_amount = $commission_amount_raw !== ''
            ? $this->sanitize_decimal($commission_amount_raw)
            : (is_array($commission_effective)
                ? (float) ($commission_effective['amount'] ?? 0)
                : (is_array($commission_stage_row)
                    ? (float) ($commission_stage_row['amount'] ?? 0)
                    : (isset($agreement['commission_amount']) && is_numeric((string) $agreement['commission_amount']) ? (float) $agreement['commission_amount'] : 0.0)));
        $commission_unit = $commission_unit_raw !== ''
            ? $this->sanitize_commission_unit($commission_unit_raw)
            : (is_array($commission_effective)
                ? $this->sanitize_commission_unit((string) ($commission_effective['unit'] ?? ''))
                : (is_array($commission_stage_row)
                    ? $this->sanitize_commission_unit((string) ($commission_stage_row['unit'] ?? ''))
                    : $this->sanitize_commission_unit((string) ($agreement['commission_unit'] ?? ''))));
        $commission_stage_payload = $commission_stage_name !== ''
            ? $this->build_commission_stage_payload($commission_due_rows, is_array($commission_effective) ? $commission_effective : [])
            : (string) ($agreement['commission_stages_json'] ?? '');
        if ($commission_amount < 0) {
            return new WP_Error('eocrm_transaction_commission', 'Prowizja nie moze byc ujemna.');
        }
        if ($commission_amount > 0 && $commission_unit === '') {
            return new WP_Error('eocrm_transaction_commission', 'Wybierz jednostke prowizji.');
        }
        $transaction_price = $this->sanitize_decimal($this->post_text('transaction_price'));
        $requires_transaction_price = $this->requires_transaction_price($commission_stage_name, $requires_transaction_date, $commission_unit);
        if ($transaction_price < 0) {
            return new WP_Error('eocrm_transaction_price', 'Cena transakcyjna nie moze byc ujemna.');
        }
        if ($requires_transaction_price && $transaction_price <= 0) {
            return new WP_Error('eocrm_transaction_price', 'Podaj cene transakcyjna wieksza od zera.');
        }
        if (! $requires_transaction_price && $transaction_price <= 0) {
            $transaction_price = 0.0;
        }
        if ($commission_stage_name !== '' && ! $requires_transaction_date) {
            $existing_partial_transaction_id = $this->find_open_partial_transaction_id($agreement_id);
            if ($existing_partial_transaction_id > 0) {
                $this->create_request_updated_existing = true;
                return $this->update_transaction_from_request($existing_partial_transaction_id);
            }
        }
        if ($commission_stage_name !== '' && $this->commission_stage_transaction_exists($agreement_id, $commission_stage_name, 0)) {
            return new WP_Error('eocrm_transaction_commission_stage_duplicate', 'Transakcja dla tego etapu prowizji juz istnieje.');
        }

        $owner_user_id = isset($agreement['owner_user_id']) ? (int) $agreement['owner_user_id'] : get_current_user_id();
        $cooperation = $this->sanitize_cooperation_from_request($owner_user_id);
        if (is_wp_error($cooperation)) {
            return $cooperation;
        }

        $now = current_time('mysql');
        $property_label = is_array($property) ? $this->build_property_label($property) : '';
        $offer_price = is_array($property) && isset($property['price']) && is_numeric((string) $property['price']) ? (float) $property['price'] : null;
        $price_currency = is_array($property) && ! empty($property['price_currency']) ? strtoupper((string) $property['price_currency']) : 'PLN';
        if (! in_array($price_currency, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
            $price_currency = 'PLN';
        }

        $inserted = $wpdb->insert(
            $this->tables['transactions'],
            [
                'agreement_id' => $agreement_id,
                'property_id' => $property_id > 0 ? $property_id : null,
                'agreement_number' => (string) ($agreement['agreement_number'] ?? ''),
                'property_label' => $property_label,
                'transaction_type' => (string) ($agreement['transaction_type'] ?? ''),
                'offer_price' => $offer_price,
                'price_currency' => $price_currency,
                'commission_amount' => $commission_amount > 0 ? $commission_amount : null,
                'commission_unit' => $commission_amount > 0 ? $commission_unit : '',
                'commission_stage_name' => $commission_stage_name !== '' ? $commission_stage_name : null,
                'commission_stages_json' => $commission_stage_payload,
                'transaction_price' => $transaction_price,
                'transaction_date' => $transaction_date !== '' ? $transaction_date : null,
                'cooperation_agent' => (int) $cooperation['cooperation_agent'],
                'cooperation_type' => (string) $cooperation['cooperation_type'],
                'cooperation_agent_user_id' => ! empty($cooperation['cooperation_agent_user_id']) ? (int) $cooperation['cooperation_agent_user_id'] : null,
                'cooperation_office_name' => (string) $cooperation['cooperation_office_name'],
                'cooperation_agent_name' => (string) $cooperation['cooperation_agent_name'],
                'owner_user_id' => $owner_user_id,
                'created_by' => get_current_user_id(),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );

        if (! $inserted) {
            if (! empty($wpdb->last_error)) {
                error_log('Estate Office CRM transaction insert failed: ' . $wpdb->last_error);
            }
            return new WP_Error('eocrm_transaction_insert', 'Nie udalo sie zapisac transakcji.');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @return int|WP_Error
     */
    private function update_transaction_from_request(int $transaction_id)
    {
        global $wpdb;

        if ($transaction_id <= 0) {
            return new WP_Error('eocrm_transaction_missing', 'Brak transakcji do edycji.');
        }

        $transaction = $this->get_transaction($transaction_id);
        if (! is_array($transaction)) {
            return new WP_Error('eocrm_transaction_missing', 'Transakcja nie istnieje lub nie masz do niej dostepu.');
        }

        $agreement_id = (int) ($transaction['agreement_id'] ?? 0);
        $agreement = $this->get_agreement($agreement_id);
        if (! is_array($agreement)) {
            return new WP_Error('eocrm_transaction_agreement_missing', 'Umowa transakcji nie istnieje lub nie masz do niej dostepu.');
        }

        $property_id = isset($_POST['property_id']) ? absint((string) wp_unslash($_POST['property_id'])) : 0;
        $property = null;
        $linked_properties = $this->get_linked_properties($agreement_id, 10);
        if ($property_id <= 0 && count($linked_properties) === 1) {
            $property_id = (int) ($linked_properties[0]['id'] ?? 0);
        }

        if ($property_id > 0) {
            $property = $this->get_property_for_agreement($agreement_id, $property_id);
            if (! is_array($property)) {
                return new WP_Error('eocrm_transaction_property', 'Wybrana nieruchomosc nie jest powiazana z ta umowa.');
            }
        }

        $commission_stage_name = $this->sanitize_commission_stage_name($this->post_text('commission_stage_name'), $agreement);
        $commission_due_rows = $commission_stage_name !== '' ? $this->get_due_commission_stage_rows($agreement_id, $agreement, $commission_stage_name, $transaction_id) : [];
        $commission_effective = ! empty($commission_due_rows) ? $this->calculate_effective_commission($agreement, $commission_due_rows) : null;
        if (is_wp_error($commission_effective)) {
            return $commission_effective;
        }
        $transaction_date = $this->post_text('transaction_date');
        $requires_transaction_date = $commission_stage_name === '' || $this->is_settlement_commission_stage($commission_stage_name);
        if ($requires_transaction_date && ! $this->is_valid_date($transaction_date)) {
            return new WP_Error('eocrm_transaction_date', 'Podaj poprawna date transakcji (RRRR-MM-DD).');
        }
        if (! $requires_transaction_date && $transaction_date !== '' && ! $this->is_valid_date($transaction_date)) {
            return new WP_Error('eocrm_transaction_date', 'Podaj poprawna date transakcji (RRRR-MM-DD).');
        }
        $commission_stage_row = $commission_stage_name !== '' ? $this->find_commission_stage_row($agreement, $commission_stage_name) : null;
        $commission_amount_raw = $this->post_text('commission_amount');
        $commission_unit_raw = $this->post_text('commission_unit');
        $commission_amount = $commission_amount_raw !== ''
            ? $this->sanitize_decimal($commission_amount_raw)
            : (is_array($commission_effective)
                ? (float) ($commission_effective['amount'] ?? 0)
                : (is_array($commission_stage_row)
                    ? (float) ($commission_stage_row['amount'] ?? 0)
                    : (isset($agreement['commission_amount']) && is_numeric((string) $agreement['commission_amount']) ? (float) $agreement['commission_amount'] : 0.0)));
        $commission_unit = $commission_unit_raw !== ''
            ? $this->sanitize_commission_unit($commission_unit_raw)
            : (is_array($commission_effective)
                ? $this->sanitize_commission_unit((string) ($commission_effective['unit'] ?? ''))
                : (is_array($commission_stage_row)
                    ? $this->sanitize_commission_unit((string) ($commission_stage_row['unit'] ?? ''))
                    : $this->sanitize_commission_unit((string) ($agreement['commission_unit'] ?? ''))));
        $commission_stage_payload = $commission_stage_name !== ''
            ? $this->build_commission_stage_payload($commission_due_rows, is_array($commission_effective) ? $commission_effective : [])
            : (string) ($agreement['commission_stages_json'] ?? '');
        if ($commission_amount < 0) {
            return new WP_Error('eocrm_transaction_commission', 'Prowizja nie moze byc ujemna.');
        }
        if ($commission_amount > 0 && $commission_unit === '') {
            return new WP_Error('eocrm_transaction_commission', 'Wybierz jednostke prowizji.');
        }
        $transaction_price = $this->sanitize_decimal($this->post_text('transaction_price'));
        $requires_transaction_price = $this->requires_transaction_price($commission_stage_name, $requires_transaction_date, $commission_unit);
        if ($transaction_price < 0) {
            return new WP_Error('eocrm_transaction_price', 'Cena transakcyjna nie moze byc ujemna.');
        }
        if ($requires_transaction_price && $transaction_price <= 0) {
            return new WP_Error('eocrm_transaction_price', 'Podaj cene transakcyjna wieksza od zera.');
        }
        if (! $requires_transaction_price && $transaction_price <= 0) {
            $transaction_price = 0.0;
        }
        if ($commission_stage_name !== '' && $this->commission_stage_transaction_exists($agreement_id, $commission_stage_name, $transaction_id)) {
            return new WP_Error('eocrm_transaction_commission_stage_duplicate', 'Transakcja dla tego etapu prowizji juz istnieje.');
        }

        $owner_user_id = isset($transaction['owner_user_id']) ? (int) $transaction['owner_user_id'] : (int) ($agreement['owner_user_id'] ?? get_current_user_id());
        $cooperation = $this->sanitize_cooperation_from_request($owner_user_id);
        if (is_wp_error($cooperation)) {
            return $cooperation;
        }

        $property_label = is_array($property) ? $this->build_property_label($property) : '';
        $offer_price = is_array($property) && isset($property['price']) && is_numeric((string) $property['price']) ? (float) $property['price'] : null;
        $price_currency = is_array($property) && ! empty($property['price_currency']) ? strtoupper((string) $property['price_currency']) : 'PLN';
        if (! in_array($price_currency, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
            $price_currency = 'PLN';
        }

        $updated = $wpdb->update(
            $this->tables['transactions'],
            [
                'property_id' => $property_id > 0 ? $property_id : null,
                'property_label' => $property_label,
                'offer_price' => $offer_price,
                'price_currency' => $price_currency,
                'commission_amount' => $commission_amount > 0 ? $commission_amount : null,
                'commission_unit' => $commission_amount > 0 ? $commission_unit : '',
                'commission_stage_name' => $commission_stage_name !== '' ? $commission_stage_name : null,
                'commission_stages_json' => $commission_stage_payload,
                'transaction_price' => $transaction_price,
                'transaction_date' => $transaction_date !== '' ? $transaction_date : null,
                'cooperation_agent' => (int) $cooperation['cooperation_agent'],
                'cooperation_type' => (string) $cooperation['cooperation_type'],
                'cooperation_agent_user_id' => ! empty($cooperation['cooperation_agent_user_id']) ? (int) $cooperation['cooperation_agent_user_id'] : null,
                'cooperation_office_name' => (string) $cooperation['cooperation_office_name'],
                'cooperation_agent_name' => (string) $cooperation['cooperation_agent_name'],
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%d', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('eocrm_transaction_update', 'Nie udalo sie zapisac zmian transakcji.');
        }

        return $transaction_id;
    }

    /**
     * @return true|WP_Error
     */
    private function delete_transaction(int $transaction_id)
    {
        global $wpdb;

        if ($transaction_id <= 0) {
            return new WP_Error('eocrm_transaction_missing', 'Brak transakcji do usuniecia.');
        }

        $transaction = $this->get_transaction($transaction_id);
        if (! is_array($transaction)) {
            return new WP_Error('eocrm_transaction_missing', 'Transakcja nie istnieje.');
        }

        $updated = $wpdb->update(
            $this->tables['transactions'],
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('eocrm_transaction_delete', 'Nie udalo sie usunac transakcji.');
        }

        return true;
    }

    private function get_transaction(int $transaction_id): ?array
    {
        global $wpdb;

        if ($transaction_id <= 0) {
            return null;
        }

        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->tables['transactions']}
                WHERE id = %d
                AND is_active = 1
                LIMIT 1",
                $transaction_id
            ),
            ARRAY_A
        );

        if (! is_array($transaction)) {
            return null;
        }

        $owner_user_id = isset($transaction['owner_user_id']) ? (int) $transaction['owner_user_id'] : 0;
        if (! $this->can_access_owner_user_id($owner_user_id)) {
            return null;
        }

        return $transaction;
    }

    private function get_agreement(int $agreement_id): ?array
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return null;
        }

        $agreement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, agreement_number, transaction_type, commission_amount, commission_unit, commission_split_enabled, commission_stages_json, owner_user_id
                FROM {$this->tables['agreements']}
                WHERE id = %d
                AND is_active = 1
                LIMIT 1",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($agreement)) {
            return null;
        }

        $owner_user_id = isset($agreement['owner_user_id']) ? (int) $agreement['owner_user_id'] : 0;
        if (! $this->can_access_owner_user_id($owner_user_id)) {
            return null;
        }

        return $agreement;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_linked_properties(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(10, $limit));
        $sql = $wpdb->prepare(
            "SELECT id, offer_number, price, price_currency, street, building_no, apartment_no, city
            FROM {$this->tables['properties']}
            WHERE agreement_id = %d
            AND is_active = 1
            ORDER BY id ASC
            LIMIT %d",
            $agreement_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function get_property_for_agreement(int $agreement_id, int $property_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, offer_number, price, price_currency, street, building_no, apartment_no, city
                FROM {$this->tables['properties']}
                WHERE id = %d
                AND agreement_id = %d
                AND is_active = 1
                LIMIT 1",
                $property_id,
                $agreement_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $property
     */
    private function build_property_label(array $property): string
    {
        $parts = [];
        $offer_number = trim((string) ($property['offer_number'] ?? ''));
        $street = trim((string) ($property['street'] ?? ''));
        $building = trim((string) ($property['building_no'] ?? ''));
        $apartment = trim((string) ($property['apartment_no'] ?? ''));
        $city = trim((string) ($property['city'] ?? ''));

        $address = trim($street . ($building !== '' ? ' ' . $building : '') . ($apartment !== '' ? '/' . $apartment : ''));
        if ($offer_number !== '') {
            $parts[] = $offer_number;
        }
        if ($address !== '') {
            $parts[] = $address;
        }
        if ($city !== '') {
            $parts[] = $city;
        }

        return implode(' - ', $parts);
    }

    /**
     * @return array{cooperation_agent:int,cooperation_type:string,cooperation_agent_user_id:int,cooperation_office_name:string,cooperation_agent_name:string}|WP_Error
     */
    private function sanitize_cooperation_from_request(int $owner_user_id)
    {
        $cooperation_agent = $this->post_text('cooperation_agent') === '1' ? 1 : 0;
        if ($cooperation_agent !== 1) {
            return [
                'cooperation_agent' => 0,
                'cooperation_type' => '',
                'cooperation_agent_user_id' => 0,
                'cooperation_office_name' => '',
                'cooperation_agent_name' => '',
            ];
        }

        $cooperation_type = sanitize_key($this->post_text('cooperation_type'));
        if (! in_array($cooperation_type, ['own_office', 'other_office'], true)) {
            return new WP_Error('eocrm_transaction_cooperation_type', 'Wybierz rodzaj wspolpracy: Biuro wlasne albo Inne biuro.');
        }

        if ($cooperation_type === 'own_office') {
            $agent_user_id = isset($_POST['cooperation_agent_user_id']) ? absint((string) wp_unslash($_POST['cooperation_agent_user_id'])) : 0;
            if ($agent_user_id <= 0) {
                return new WP_Error('eocrm_transaction_cooperation_agent', 'Wybierz Agenta wspolpracujacego z CRM.');
            }
            if (! $this->can_select_cooperation_agent($agent_user_id, $owner_user_id)) {
                return new WP_Error('eocrm_transaction_cooperation_agent', 'Wybrany Agent nie jest dostepny dla tej wspolpracy.');
            }

            return [
                'cooperation_agent' => 1,
                'cooperation_type' => 'own_office',
                'cooperation_agent_user_id' => $agent_user_id,
                'cooperation_office_name' => '',
                'cooperation_agent_name' => '',
            ];
        }

        $office_name = $this->post_text('cooperation_office_name');
        $agent_name = $this->post_text('cooperation_agent_name');
        if ($office_name === '') {
            return new WP_Error('eocrm_transaction_cooperation_office', 'Podaj nazwe biura wspolpracujacego.');
        }
        if ($agent_name === '') {
            return new WP_Error('eocrm_transaction_cooperation_agent_name', 'Podaj imie i nazwisko Agenta wspolpracujacego.');
        }

        return [
            'cooperation_agent' => 1,
            'cooperation_type' => 'other_office',
            'cooperation_agent_user_id' => 0,
            'cooperation_office_name' => $office_name,
            'cooperation_agent_name' => $agent_name,
        ];
    }

    private function can_select_cooperation_agent(int $agent_user_id, int $owner_user_id): bool
    {
        if ($agent_user_id <= 0 || $agent_user_id === $owner_user_id) {
            return false;
        }

        if (! EstateOfficeCRM_Access::is_agent_or_manager_user($agent_user_id)) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user_id = get_current_user_id();
        $current_office_id = EstateOfficeCRM_Access::get_user_office_id($current_user_id, $this->tables);
        if ($current_office_id <= 0) {
            return false;
        }

        $agent_office_id = EstateOfficeCRM_Access::get_user_office_id($agent_user_id, $this->tables);
        return $agent_office_id > 0 && $agent_office_id === $current_office_id;
    }

    private function post_text(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field((string) wp_unslash($_POST[$key])) : '';
    }

    private function sanitize_decimal(string $value): float
    {
        $value = str_replace([' ', ','], ['', '.'], $value);
        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    /**
     * @param array<string, mixed> $agreement
     */
    private function sanitize_commission_stage_name(string $value, array $agreement): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', sanitize_text_field($value)));
        if ($value === '') {
            return '';
        }

        $stage = $this->find_commission_stage_row($agreement, $value);
        return is_array($stage) ? (string) $stage['stage_name'] : '';
    }

    /**
     * @param array<string, mixed> $agreement
     * @return array{stage_name:string,amount:float,unit:string}|null
     */
    private function find_commission_stage_row(array $agreement, string $stage_name): ?array
    {
        if (empty($agreement['commission_split_enabled']) || $stage_name === '') {
            return null;
        }

        $target = $this->normalize_commission_stage_key($stage_name);
        if ($target === '') {
            return null;
        }

        foreach ($this->decode_commission_stages((string) ($agreement['commission_stages_json'] ?? '')) as $row) {
            if ($this->normalize_commission_stage_key((string) $row['stage_name']) === $target) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{stage_name:string,amount:float,unit:string}>
     */
    private function decode_commission_stages(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stage_name = trim(sanitize_text_field((string) ($row['stage_name'] ?? '')));
            $amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? round((float) $row['amount'], 2) : 0.0;
            $unit = $this->sanitize_commission_unit((string) ($row['unit'] ?? ''));
            if ($stage_name === '' || $amount <= 0 || $unit === '') {
                continue;
            }

            $rows[] = [
                'stage_name' => $stage_name,
                'amount' => $amount,
                'unit' => $unit,
            ];
        }

        return $rows;
    }

    private function normalize_commission_stage_key(string $stage_name): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $stage_name));
        if ($normalized === '') {
            return '';
        }

        return strtolower(remove_accents($normalized));
    }

    /**
     * @param array<string, mixed> $agreement
     * @return array<int, array{stage_name:string,amount:float,unit:string}>
     */
    private function get_due_commission_stage_rows(int $agreement_id, array $agreement, string $selected_stage_name, int $exclude_transaction_id = 0): array
    {
        $plan_rows = $this->decode_commission_stages((string) ($agreement['commission_stages_json'] ?? ''));
        if (empty($plan_rows) || $selected_stage_name === '') {
            return [];
        }

        $stage_order = EstateOfficeCRM_Stages::load_for_transaction(
            (string) ($agreement['transaction_type'] ?? ''),
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );
        $stage_index_map = [];
        foreach ($stage_order as $index => $stage_name) {
            $stage_key = $this->normalize_commission_stage_key((string) $stage_name);
            if ($stage_key !== '') {
                $stage_index_map[$stage_key] = (int) $index;
            }
        }

        $selected_key = $this->normalize_commission_stage_key($selected_stage_name);
        $selected_index = isset($stage_index_map[$selected_key]) ? (int) $stage_index_map[$selected_key] : PHP_INT_MAX;
        $paid_stage_keys = $this->get_paid_commission_stage_keys($agreement_id, $exclude_transaction_id);
        $due_rows = [];

        foreach ($plan_rows as $row) {
            $row_stage_name = (string) ($row['stage_name'] ?? '');
            $row_key = $this->normalize_commission_stage_key($row_stage_name);
            if ($row_key === '' || in_array($row_key, $paid_stage_keys, true)) {
                continue;
            }

            $row_index = isset($stage_index_map[$row_key]) ? (int) $stage_index_map[$row_key] : PHP_INT_MAX;
            if ($row_index <= $selected_index || $row_key === $selected_key) {
                $due_rows[] = $row;
            }
        }

        return $due_rows;
    }

    /**
     * @param array<string, mixed> $agreement
     * @param array<int, array{stage_name:string,amount:float,unit:string}> $rows
     * @return array{amount:float,unit:string}|WP_Error
     */
    private function calculate_effective_commission(array $agreement, array $rows)
    {
        $total_amount = 0.0;
        $total_unit = '';
        $base_amount = isset($agreement['commission_amount']) && is_numeric((string) $agreement['commission_amount']) ? (float) $agreement['commission_amount'] : 0.0;
        $base_unit = $this->sanitize_commission_unit((string) ($agreement['commission_unit'] ?? ''));

        foreach ($rows as $row) {
            $row_amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? (float) $row['amount'] : 0.0;
            $row_unit = $this->sanitize_commission_unit((string) ($row['unit'] ?? ''));
            if ($row_amount <= 0 || $row_unit === '') {
                continue;
            }

            if ($row_unit === '%') {
                if ($base_amount <= 0 || $base_unit === '') {
                    return new WP_Error('eocrm_transaction_commission_stage_base', 'Etap procentowy wymaga prowizji bazowej w umowie.');
                }
                $effective_amount = ($base_amount * $row_amount) / 100;
                $effective_unit = $base_unit;
            } else {
                $effective_amount = $row_amount;
                $effective_unit = $row_unit;
            }

            if ($total_unit === '') {
                $total_unit = $effective_unit;
            } elseif ($total_unit !== $effective_unit) {
                return new WP_Error('eocrm_transaction_commission_stage_mixed_units', 'Nie mozna zsumowac etapow prowizji z roznymi jednostkami po przeliczeniu.');
            }

            $total_amount += $effective_amount;
        }

        return [
            'amount' => round($total_amount, 2),
            'unit' => $total_unit,
        ];
    }

    /**
     * @param array<int, array{stage_name:string,amount:float,unit:string}> $rows
     * @param array<string, mixed> $effective
     */
    private function build_commission_stage_payload(array $rows, array $effective): string
    {
        $covered_stage_names = [];
        foreach ($rows as $row) {
            $stage_name = trim((string) ($row['stage_name'] ?? ''));
            if ($stage_name !== '') {
                $covered_stage_names[] = $stage_name;
            }
        }

        $payload = [
            'payload_type' => 'commission_stage_payment_v2',
            'covered_stage_names' => array_values(array_unique($covered_stage_names)),
            'rows' => array_values($rows),
            'effective' => [
                'amount' => isset($effective['amount']) && is_numeric((string) $effective['amount']) ? round((float) $effective['amount'], 2) : 0,
                'unit' => $this->sanitize_commission_unit((string) ($effective['unit'] ?? '')),
            ],
        ];

        $json = wp_json_encode($payload);
        return is_string($json) ? $json : '';
    }

    /**
     * @return string[]
     */
    private function get_paid_commission_stage_keys(int $agreement_id, int $exclude_transaction_id = 0): array
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return [];
        }

        $table = $this->tables['transactions'];
        $params = [$agreement_id];
        $sql = "SELECT id, commission_stage_name, commission_stages_json
            FROM {$table}
            WHERE agreement_id = %d
            AND is_active = 1
            AND commission_stage_name IS NOT NULL
            AND commission_stage_name <> ''";
        if ($exclude_transaction_id > 0) {
            $sql .= ' AND id <> %d';
            $params[] = $exclude_transaction_id;
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            $covered_names = $this->get_covered_commission_stage_names_from_payload((string) ($row['commission_stages_json'] ?? ''));
            if (empty($covered_names)) {
                $covered_names[] = (string) ($row['commission_stage_name'] ?? '');
            }

            foreach ($covered_names as $stage_name) {
                $key = $this->normalize_commission_stage_key($stage_name);
                if ($key !== '' && ! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * @return string[]
     */
    private function get_covered_commission_stage_names_from_payload(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || (string) ($decoded['payload_type'] ?? '') !== 'commission_stage_payment_v2') {
            return [];
        }

        $names = [];
        $raw_names = isset($decoded['covered_stage_names']) && is_array($decoded['covered_stage_names']) ? $decoded['covered_stage_names'] : [];
        foreach ($raw_names as $name) {
            $name = trim(sanitize_text_field((string) $name));
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function is_settlement_commission_stage(string $stage_name): bool
    {
        $normalized = $this->normalize_commission_stage_key($stage_name);
        return strpos($normalized, 'umowa przyrzeczona') === 0 || strpos($normalized, 'umowa najmu') === 0;
    }

    private function requires_transaction_price(string $commission_stage_name, bool $requires_transaction_date, string $commission_unit): bool
    {
        if ($commission_stage_name === '') {
            return true;
        }

        if ($requires_transaction_date) {
            return true;
        }

        return $this->sanitize_commission_unit($commission_unit) === '%';
    }

    private function find_open_partial_transaction_id(int $agreement_id): int
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return 0;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, commission_stage_name
                FROM {$this->tables['transactions']}
                WHERE agreement_id = %d
                AND is_active = 1
                AND commission_stage_name IS NOT NULL
                AND commission_stage_name <> ''
                ORDER BY id ASC",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return 0;
        }

        foreach ($rows as $row) {
            $stage_name = trim((string) ($row['commission_stage_name'] ?? ''));
            if ($stage_name !== '' && ! $this->is_settlement_commission_stage($stage_name)) {
                return (int) ($row['id'] ?? 0);
            }
        }

        return 0;
    }

    private function commission_stage_transaction_exists(int $agreement_id, string $stage_name, int $exclude_transaction_id = 0): bool
    {
        if ($agreement_id <= 0 || $stage_name === '') {
            return false;
        }

        $target = $this->normalize_commission_stage_key($stage_name);
        if ($target === '') {
            return false;
        }

        $paid_stage_keys = $this->get_paid_commission_stage_keys($agreement_id, $exclude_transaction_id);

        return in_array($target, $paid_stage_keys, true);
    }

    private function get_setting_value(string $key): string
    {
        global $wpdb;

        $table = $this->tables['settings'];
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1",
                $key
            )
        );

        return is_string($value) ? $value : '';
    }

    private function sanitize_commission_unit(string $value): string
    {
        $value = strtoupper(trim($value));
        $allowed = ['%', 'PLN', 'EUR', 'USD'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function is_valid_date(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTime && $date->format('Y-m-d') === $value;
    }

    private function can_access_owner_user_id(int $owner_user_id): bool
    {
        return EstateOfficeCRM_Access::can_access_owner_user($owner_user_id, $this->tables, get_current_user_id());
    }

    private function redirect_to_form(int $agreement_id, string $notice, string $message = ''): void
    {
        $args = [
            'crm' => 'transactions',
            'mode' => 'new-transaction',
            'agreement_id' => max(0, $agreement_id),
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('transactions')));
        exit;
    }

    private function redirect_to_profile(int $transaction_id, string $notice, string $message = ''): void
    {
        $args = [
            'crm' => 'transactions',
            'transaction_id' => max(0, $transaction_id),
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('transactions')));
        exit;
    }

    private function redirect_to_edit_form(int $transaction_id, string $notice, string $message = ''): void
    {
        $args = [
            'crm' => 'transactions',
            'mode' => 'edit-transaction',
            'transaction_id' => max(0, $transaction_id),
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('transactions')));
        exit;
    }

    private function redirect_to_list(string $notice, string $message = ''): void
    {
        $args = [
            'crm' => 'transactions',
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('transactions')));
        exit;
    }

    private function get_section_url(string $section): string
    {
        $page_ids = get_option(EstateOfficeCRM_Installer::OPTION_PAGE_IDS, []);
        if (is_array($page_ids)) {
            $crm_page_id = isset($page_ids['crm']) ? absint((string) $page_ids['crm']) : 0;
            if ($crm_page_id > 0) {
                $crm_url = get_permalink($crm_page_id);
                if (is_string($crm_url) && $crm_url !== '') {
                    return $crm_url;
                }
            }

            $page_id = isset($page_ids[$section]) ? absint((string) $page_ids[$section]) : 0;
            if ($page_id > 0) {
                $url = get_permalink($page_id);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        $referer = wp_get_referer();
        if (is_string($referer) && $referer !== '') {
            $clean = remove_query_arg(['_wp_http_referer', '_wpnonce'], $referer);
            if (is_string($clean) && $clean !== '') {
                return $clean;
            }
        }

        return home_url('/crm/');
    }
}
