<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Agreements
{
    /** @var array<string, string> */
    private array $tables;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'handle_frontend_create_agreement']);
        add_action('init', [$this, 'handle_frontend_update_agreement_stage']);
        add_action('init', [$this, 'handle_frontend_update_agreement_stage_history']);
        add_action('init', [$this, 'handle_frontend_delete_agreement_stage_history']);
        add_action('init', [$this, 'handle_frontend_update_agreement']);
        add_action('init', [$this, 'handle_frontend_delete_agreement']);
    }

    public function handle_frontend_create_agreement(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'create_agreement') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_agreements') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do dodawania umow.');
        }

        check_admin_referer('eocrm_create_agreement', 'eocrm_nonce');

        $result = $this->create_agreement_from_request();
        if (is_wp_error($result)) {
            $this->redirect_back('agreement_error', $result->get_error_message(), true);
        }

        $agreement_id = (int) $result;
        $transaction_type = $this->sanitize_transaction_type($this->post_text('transaction_type'));
        $this->redirect_after_create($agreement_id, $transaction_type);
    }

    public function handle_frontend_update_agreement_stage(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_agreement_stage') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_agreements') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do aktualizacji etapow umow.');
        }

        check_admin_referer('eocrm_update_agreement_stage', 'eocrm_stage_nonce');

        $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
        $requested_stage_name = $this->post_text('stage_name');
        $stage_date = $this->post_text('stage_date');

        if ($agreement_id <= 0) {
            $this->redirect_back('agreement_error', 'Brak identyfikatora umowy.', false);
        }

        if (! $this->is_valid_date($stage_date)) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Podaj poprawna date etapu (RRRR-MM-DD).');
        }

        global $wpdb;
        $agreements_table = $this->tables['agreements'];
        $agreement_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, owner_user_id, transaction_type, commission_split_enabled, commission_stages_json FROM {$agreements_table} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($agreement_row)) {
            $this->redirect_back('agreement_error', 'Umowa nie istnieje lub jest nieaktywna.', false);
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = isset($agreement_row['owner_user_id']) ? (int) $agreement_row['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                $this->redirect_back('agreement_error', 'Brak uprawnien do aktualizacji etapu tej umowy.', false);
            }
        }

        $transaction_type = isset($agreement_row['transaction_type']) ? (string) $agreement_row['transaction_type'] : '';
        $stage_name = $this->sanitize_stage_name($requested_stage_name, $transaction_type);
        if ($stage_name === '') {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Wybierz poprawny etap umowy.');
        }

        $now = current_time('mysql');
        $updated = $wpdb->update(
            $agreements_table,
            [
                'current_stage' => $stage_name,
                'updated_at' => $now,
            ],
            ['id' => $agreement_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Nie udalo sie zaktualizowac aktualnego etapu umowy.');
        }

        $inserted = $wpdb->insert(
            $this->tables['agreement_stages'],
            [
                'agreement_id' => $agreement_id,
                'stage_name' => $stage_name,
                'stage_date' => $stage_date,
                'created_by' => get_current_user_id(),
                'created_at' => $now,
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );

        if (! $inserted) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Etap glowny zapisany, ale nie udalo sie dodac wpisu do historii etapow.');
        }

        $create_transaction_after_stage = isset($_POST['create_transaction_after_stage']) && (string) wp_unslash($_POST['create_transaction_after_stage']) === '1';
        $is_finished_stage = $this->is_finished_stage_name($stage_name);
        $commission_plan_rows = ! empty($agreement_row['commission_split_enabled'])
            ? $this->decode_commission_stages((string) ($agreement_row['commission_stages_json'] ?? ''))
            : [];
        $has_commission_plan = ! empty($commission_plan_rows);
        $unpaid_commission_stage_names = $has_commission_plan
            ? $this->get_unpaid_commission_stage_names($agreement_id, $agreement_row, $commission_plan_rows)
            : [];
        $all_commission_stages_paid = $has_commission_plan && empty($unpaid_commission_stage_names);
        $commission_stage = $this->find_commission_stage_row(
            ! empty($agreement_row['commission_split_enabled']),
            (string) ($agreement_row['commission_stages_json'] ?? ''),
            $stage_name
        );
        $is_commission_stage = is_array($commission_stage);
        $is_commission_transaction_stage = $is_commission_stage
            && ! $this->commission_stage_transaction_exists($agreement_id, $stage_name);
        $finished_stage_needs_transaction = $is_finished_stage && (! $has_commission_plan || ! $all_commission_stages_paid);
        $commission_stage_for_redirect = '';
        if ($is_commission_transaction_stage) {
            $commission_stage_for_redirect = $stage_name;
        } elseif ($is_finished_stage && $has_commission_plan && ! $all_commission_stages_paid) {
            $commission_stage_for_redirect = (string) end($unpaid_commission_stage_names);
        }

        if ($create_transaction_after_stage && ($finished_stage_needs_transaction || $is_commission_transaction_stage)) {
            $redirect_args = [
                'crm' => 'transactions',
                'mode' => 'new-transaction',
                'agreement_id' => $agreement_id,
                'crm_notice' => 'agreement_stage_updated',
                'crm_message' => $commission_stage_for_redirect !== ''
                    ? 'Etap umowy zostal zaktualizowany. Uzupelnij transakcje prowizyjna dla tego etapu.'
                    : 'Etap umowy zostal zaktualizowany. Uzupelnij transakcje powiazana z ta umowa.',
            ];
            if ($commission_stage_for_redirect !== '') {
                $existing_partial_transaction_id = $this->find_open_partial_transaction_id($agreement_id);
                if ($existing_partial_transaction_id > 0) {
                    $redirect_args['mode'] = 'edit-transaction';
                    $redirect_args['transaction_id'] = $existing_partial_transaction_id;
                    unset($redirect_args['agreement_id']);
                    $redirect_args['crm_message'] = 'Etap umowy zostal zaktualizowany. Dopisz etap prowizji do istniejacej transakcji czesciowej.';
                }
                $redirect_args['commission_stage'] = $commission_stage_for_redirect;
            }
            wp_safe_redirect(add_query_arg($redirect_args, $this->get_section_url('transactions')));
            exit;
        }

        $this->redirect_to_agreement_profile($agreement_id, 'agreement_stage_updated', 'Etap umowy zostal zaktualizowany.');
    }

    public function handle_frontend_update_agreement_stage_history(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_agreement_stage_history') {
            return;
        }

        if (! is_user_logged_in() || ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do edycji historii etapow.');
        }

        check_admin_referer('eocrm_update_agreement_stage_history', 'eocrm_stage_history_nonce');

        $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
        $history_id = isset($_POST['stage_history_id']) ? absint((string) wp_unslash($_POST['stage_history_id'])) : 0;
        $stage_date = $this->post_text('stage_date');
        if ($agreement_id <= 0 || $history_id <= 0) {
            $this->redirect_back('agreement_error', 'Brak identyfikatora etapu.', false);
        }

        if (! $this->is_valid_date($stage_date)) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Podaj poprawna date etapu (RRRR-MM-DD).');
        }

        global $wpdb;
        $agreement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, transaction_type FROM {$this->tables['agreements']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($agreement)) {
            $this->redirect_back('agreement_error', 'Umowa nie istnieje lub jest nieaktywna.', false);
        }

        $stage_name = $this->sanitize_stage_name($this->post_text('stage_name'), (string) ($agreement['transaction_type'] ?? ''));
        if ($stage_name === '') {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Wybierz poprawny etap umowy.');
        }

        $updated = $wpdb->update(
            $this->tables['agreement_stages'],
            [
                'stage_name' => $stage_name,
                'stage_date' => $stage_date,
            ],
            [
                'id' => $history_id,
                'agreement_id' => $agreement_id,
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Nie udalo sie zaktualizowac wpisu historii etapu.');
        }

        $this->refresh_current_stage_from_history($agreement_id);
        $this->redirect_to_agreement_profile($agreement_id, 'agreement_stage_updated', 'Historia etapu zostala zaktualizowana.');
    }

    public function handle_frontend_delete_agreement_stage_history(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'delete_agreement_stage_history') {
            return;
        }

        if (! is_user_logged_in() || ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do usuwania historii etapow.');
        }

        check_admin_referer('eocrm_delete_agreement_stage_history', 'eocrm_stage_history_delete_nonce');

        $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
        $history_id = isset($_POST['stage_history_id']) ? absint((string) wp_unslash($_POST['stage_history_id'])) : 0;
        if ($agreement_id <= 0 || $history_id <= 0) {
            $this->redirect_back('agreement_error', 'Brak identyfikatora etapu.', false);
        }

        global $wpdb;
        $history_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['agreement_stages']} WHERE agreement_id = %d",
                $agreement_id
            )
        );
        if ($history_count <= 1) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Nie mozna usunac ostatniego etapu umowy.');
        }

        $deleted = $wpdb->delete(
            $this->tables['agreement_stages'],
            [
                'id' => $history_id,
                'agreement_id' => $agreement_id,
            ],
            ['%d', '%d']
        );

        if (! $deleted) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Nie udalo sie usunac wpisu historii etapu.');
        }

        $this->refresh_current_stage_from_history($agreement_id);
        $this->redirect_to_agreement_profile($agreement_id, 'agreement_stage_updated', 'Wpis historii etapu zostal usuniety.');
    }

    public function handle_frontend_update_agreement(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_agreement') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_agreements') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do edycji umow.');
        }

        check_admin_referer('eocrm_update_agreement', 'eocrm_agreement_update_nonce');

        $result = $this->update_agreement_from_request();
        if (is_wp_error($result)) {
            $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', $result->get_error_message(), true);
        }

        $agreement_id = (int) $result;
        $this->redirect_to_agreement_profile($agreement_id, 'agreement_updated', 'Dane umowy zostaly zaktualizowane.', false);
    }

    public function handle_frontend_delete_agreement(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'delete_agreement') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_delete_records') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do usuwania umow.');
        }

        check_admin_referer('eocrm_delete_agreement', 'eocrm_agreement_delete_nonce');

        $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
        if ($agreement_id <= 0) {
            $this->redirect_back('agreement_error', 'Brak identyfikatora umowy.', false);
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = $this->get_agreement_owner_user_id($agreement_id);
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                $this->redirect_back('agreement_error', 'Brak uprawnien do usuniecia tej umowy.', false);
            }
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->tables['agreements'],
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $agreement_id,
                'is_active' => 1,
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $this->redirect_to_agreement_profile($agreement_id, 'agreement_error', 'Nie udalo sie usunac umowy.', false);
        }

        $this->redirect_back('agreement_deleted', 'Umowa zostala usunieta.', false);
    }

    /**
     * @return int|WP_Error
     */
    private function create_agreement_from_request()
    {
        global $wpdb;

        $transaction_type = $this->sanitize_transaction_type($this->post_text('transaction_type'));
        $agreement_scope = $this->agreement_scope_from_transaction_type($transaction_type);
        $agreement_number = $this->post_text('agreement_number');
        $next_agreement_numbering_settings = null;
        $default_agreement_number = $this->peek_default_agreement_number($agreement_scope);
        if ($agreement_number === '' || $agreement_number === $default_agreement_number) {
            $generated_number = $this->generate_next_available_agreement_number($agreement_scope);
            $agreement_number = $generated_number['number'];
            $next_agreement_numbering_settings = $generated_number['settings'];
        }

        $date_signed = $this->post_text('date_signed');
        $date_end = $this->post_text('date_end');
        $is_indefinite = isset($_POST['is_indefinite']) && (string) wp_unslash($_POST['is_indefinite']) === '1';
        $is_exclusive = $this->post_boolean_choice('is_exclusive');

        $commission_amount = $this->sanitize_decimal($this->post_text('commission_amount'));
        $commission_unit = $this->sanitize_commission_unit($this->post_text('commission_unit'));
        $commission_split = $this->collect_commission_split_from_request($transaction_type, $commission_amount, $commission_unit);
        if (is_wp_error($commission_split)) {
            return $commission_split;
        }

        if ($is_indefinite) {
            $date_end = '';
        }

        $selected_client_ids_raw = isset($_POST['client_ids']) ? wp_unslash($_POST['client_ids']) : [];
        if (! is_array($selected_client_ids_raw)) {
            $selected_client_ids_raw = [];
        }

        $selected_client_ids = array_values(array_unique(array_filter(array_map('absint', $selected_client_ids_raw))));
        $should_create_new_client = isset($_POST['agreement_add_new_client']) && (string) wp_unslash($_POST['agreement_add_new_client']) === '1';
        $new_client_payloads = $should_create_new_client ? $this->collect_new_client_payloads() : [];

        if ($should_create_new_client) {
            if (empty($new_client_payloads)) {
                return new WP_Error('eocrm_agreement_new_client_validation', 'Po wlaczeniu dodawania nowego klienta uzupelnij dane co najmniej jednego klienta.');
            }

            foreach ($new_client_payloads as $new_client_payload) {
                $new_client_validation = $this->validate_new_client_payload($new_client_payload);
                if ($new_client_validation !== '') {
                    return new WP_Error('eocrm_agreement_new_client_validation', $new_client_validation);
                }
            }
        }

        $validation = $this->validate_payload(
            $agreement_number,
            $transaction_type,
            $date_signed,
            $date_end,
            $is_indefinite,
            $commission_amount,
            $commission_unit,
            $selected_client_ids,
            ! empty($new_client_payloads)
        );

        if ($validation !== '') {
            return new WP_Error('eocrm_agreement_validation', $validation);
        }

        $agreements_table = $this->tables['agreements'];
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$agreements_table} WHERE agreement_number = %s LIMIT 1",
                $agreement_number
            )
        );

        if ($existing) {
            return new WP_Error('eocrm_agreement_duplicate', 'Numer umowy juz istnieje.');
        }

        $owner_user_id = get_current_user_id();
        $now = current_time('mysql');

        $initial_stage_name = EstateOfficeCRM_Stages::first_stage(
            $transaction_type,
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );

        $insert_ok = $wpdb->insert(
            $agreements_table,
            [
                'agreement_number' => $agreement_number,
                'transaction_type' => $transaction_type,
                'date_signed' => $date_signed,
                'date_end' => $date_end !== '' ? $date_end : null,
                'is_indefinite' => $is_indefinite ? 1 : 0,
                'is_exclusive' => $is_exclusive ? 1 : 0,
                'commission_amount' => $commission_amount,
                'commission_unit' => $commission_unit,
                'commission_split_enabled' => (int) $commission_split['enabled'],
                'commission_stages_json' => (string) $commission_split['json'],
                'current_stage' => $initial_stage_name,
                'owner_user_id' => $owner_user_id,
                'created_by' => $owner_user_id,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%d',
                '%f', '%s', '%d', '%s', '%s', '%d', '%d',
                '%d', '%s', '%s',
            ]
        );

        if (! $insert_ok) {
            return new WP_Error('eocrm_agreement_insert', 'Nie udalo sie zapisac umowy.');
        }

        $agreement_id = (int) $wpdb->insert_id;
        if ($agreement_id <= 0) {
            return new WP_Error('eocrm_agreement_insert', 'Brak identyfikatora nowej umowy.');
        }

        $stage_inserted = $this->insert_initial_stage($agreement_id, $initial_stage_name, $date_signed, $owner_user_id, $now);
        if (! $stage_inserted) {
            $wpdb->delete($agreements_table, ['id' => $agreement_id], ['%d']);
            return new WP_Error('eocrm_agreement_stage', 'Nie udalo sie zapisac etapu poczatkowego umowy.');
        }

        foreach ($new_client_payloads as $new_client_payload) {
            $new_client_id = $this->create_new_client_record($new_client_payload, $owner_user_id, $now);
            if (is_wp_error($new_client_id)) {
                $wpdb->delete($this->tables['agreement_clients'], ['agreement_id' => $agreement_id], ['%d']);
                $wpdb->delete($this->tables['agreement_stages'], ['agreement_id' => $agreement_id], ['%d']);
                $wpdb->delete($agreements_table, ['id' => $agreement_id], ['%d']);
                return $new_client_id;
            }

            $selected_client_ids[] = (int) $new_client_id;
            $selected_client_ids = array_values(array_unique(array_filter(array_map('absint', $selected_client_ids))));
        }

        $linked_ok = $this->link_clients($agreement_id, $selected_client_ids, $now);
        if (! $linked_ok) {
            $wpdb->delete($this->tables['agreement_stages'], ['agreement_id' => $agreement_id], ['%d']);
            $wpdb->delete($agreements_table, ['id' => $agreement_id], ['%d']);
            return new WP_Error('eocrm_agreement_clients', 'Nie udalo sie powiazac klientow z umowa.');
        }

        if (is_array($next_agreement_numbering_settings)) {
            $this->persist_numbering_settings('agreement', $next_agreement_numbering_settings, $agreement_scope);
        }

        $this->sync_exclusive_flag_to_related_properties($agreement_id, $is_exclusive);

        return $agreement_id;
    }

    /**
     * @return int|WP_Error
     */
    private function update_agreement_from_request()
    {
        global $wpdb;

        $agreement_id = isset($_POST['agreement_id']) ? absint((string) wp_unslash($_POST['agreement_id'])) : 0;
        if ($agreement_id <= 0) {
            return new WP_Error('eocrm_agreement_update', 'Brak identyfikatora umowy.');
        }

        $agreement_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, transaction_type, owner_user_id FROM {$this->tables['agreements']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($agreement_row)) {
            return new WP_Error('eocrm_agreement_update', 'Umowa nie istnieje lub jest nieaktywna.');
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = isset($agreement_row['owner_user_id']) ? (int) $agreement_row['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                return new WP_Error('eocrm_agreement_update', 'Brak uprawnien do edycji tej umowy.');
            }
        }

        $owner_user_id_for_update = null;
        if (current_user_can('manage_options')) {
            $owner_user_id_for_update = isset($_POST['owner_user_id']) ? absint((string) wp_unslash($_POST['owner_user_id'])) : 0;
            if ($owner_user_id_for_update <= 0) {
                return new WP_Error('eocrm_agreement_update', 'Wybierz opiekuna umowy.');
            }

            if (! $this->is_valid_owner_user($owner_user_id_for_update)) {
                return new WP_Error('eocrm_agreement_update', 'Wybrany opiekun jest nieprawidlowy.');
            }
        }

        $agreement_number = $this->post_text('agreement_number');
        $transaction_type = isset($agreement_row['transaction_type']) ? (string) $agreement_row['transaction_type'] : '';
        $date_signed = $this->post_text('date_signed');
        $date_end = $this->post_text('date_end');
        $is_indefinite = isset($_POST['is_indefinite']) && (string) wp_unslash($_POST['is_indefinite']) === '1';
        $is_exclusive = $this->post_boolean_choice('is_exclusive');

        $commission_amount = $this->sanitize_decimal($this->post_text('commission_amount'));
        $commission_unit = $this->sanitize_commission_unit($this->post_text('commission_unit'));
        $commission_split = $this->collect_commission_split_from_request($transaction_type, $commission_amount, $commission_unit);
        if (is_wp_error($commission_split)) {
            return $commission_split;
        }

        if ($is_indefinite) {
            $date_end = '';
        }

        $selected_client_ids_raw = isset($_POST['client_ids']) ? wp_unslash($_POST['client_ids']) : [];
        if (! is_array($selected_client_ids_raw)) {
            $selected_client_ids_raw = [];
        }

        $selected_client_ids = array_values(array_unique(array_filter(array_map('absint', $selected_client_ids_raw))));
        $should_create_new_client = isset($_POST['agreement_add_new_client']) && (string) wp_unslash($_POST['agreement_add_new_client']) === '1';
        $new_client_payloads = $should_create_new_client ? $this->collect_new_client_payloads() : [];

        if ($should_create_new_client) {
            if (empty($new_client_payloads)) {
                return new WP_Error('eocrm_agreement_new_client_validation', 'Po wlaczeniu dodawania nowego klienta uzupelnij dane co najmniej jednego klienta.');
            }

            foreach ($new_client_payloads as $new_client_payload) {
                $new_client_validation = $this->validate_new_client_payload($new_client_payload);
                if ($new_client_validation !== '') {
                    return new WP_Error('eocrm_agreement_new_client_validation', $new_client_validation);
                }
            }
        }

        $validation = $this->validate_payload(
            $agreement_number,
            $transaction_type,
            $date_signed,
            $date_end,
            $is_indefinite,
            $commission_amount,
            $commission_unit,
            $selected_client_ids,
            ! empty($new_client_payloads)
        );

        if ($validation !== '') {
            return new WP_Error('eocrm_agreement_validation', $validation);
        }

        $duplicate_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['agreements']} WHERE agreement_number = %s AND id <> %d LIMIT 1",
                $agreement_number,
                $agreement_id
            )
        );

        if ($duplicate_id > 0) {
            return new WP_Error('eocrm_agreement_duplicate', 'Numer umowy juz istnieje.');
        }

        $now = current_time('mysql');
        $agreement_update_payload = [
            'agreement_number' => $agreement_number,
            'date_signed' => $date_signed,
            'date_end' => $date_end !== '' ? $date_end : null,
            'is_indefinite' => $is_indefinite ? 1 : 0,
            'is_exclusive' => $is_exclusive ? 1 : 0,
            'commission_amount' => $commission_amount,
            'commission_unit' => $commission_unit,
            'commission_split_enabled' => (int) $commission_split['enabled'],
            'commission_stages_json' => (string) $commission_split['json'],
            'updated_at' => $now,
        ];

        $agreement_update_format = ['%s', '%s', '%s', '%d', '%d', '%f', '%s', '%d', '%s', '%s'];
        if (is_int($owner_user_id_for_update) && $owner_user_id_for_update > 0) {
            $agreement_update_payload['owner_user_id'] = $owner_user_id_for_update;
            $agreement_update_format[] = '%d';
        }
        $agreement_owner_for_new_client = isset($agreement_row['owner_user_id']) ? (int) $agreement_row['owner_user_id'] : 0;
        if (is_int($owner_user_id_for_update) && $owner_user_id_for_update > 0) {
            $agreement_owner_for_new_client = $owner_user_id_for_update;
        }
        if ($agreement_owner_for_new_client <= 0) {
            $agreement_owner_for_new_client = get_current_user_id();
        }

        $updated = $wpdb->update(
            $this->tables['agreements'],
            $agreement_update_payload,
            ['id' => $agreement_id],
            $agreement_update_format,
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('eocrm_agreement_update', 'Nie udalo sie zaktualizowac umowy.');
        }

        foreach ($new_client_payloads as $new_client_payload) {
            $new_client_id = $this->create_new_client_record($new_client_payload, $agreement_owner_for_new_client, $now);
            if (is_wp_error($new_client_id)) {
                return $new_client_id;
            }

            $selected_client_ids[] = (int) $new_client_id;
            $selected_client_ids = array_values(array_unique(array_filter(array_map('absint', $selected_client_ids))));
        }

        if (! $this->replace_linked_clients($agreement_id, $selected_client_ids, $now)) {
            return new WP_Error('eocrm_agreement_clients', 'Nie udalo sie zaktualizowac klientow przypisanych do umowy.');
        }

        $this->sync_exclusive_flag_to_related_properties($agreement_id, $is_exclusive);

        return $agreement_id;
    }

    private function post_boolean_choice(string $key): bool
    {
        if (! isset($_POST[$key])) {
            return false;
        }

        $raw_value = strtolower(trim((string) wp_unslash($_POST[$key])));

        return in_array($raw_value, ['1', 'true', 'tak', 'yes'], true);
    }

    /**
     * @return array{enabled:int,json:string}|WP_Error
     */
    private function collect_commission_split_from_request(string $transaction_type, float $base_commission_amount, string $base_commission_unit)
    {
        $enabled = $this->post_boolean_choice('commission_split_enabled');
        if (! $enabled) {
            return [
                'enabled' => 0,
                'json' => '',
            ];
        }

        $raw_rows = isset($_POST['commission_stages']) ? wp_unslash($_POST['commission_stages']) : [];
        if (! is_array($raw_rows)) {
            $raw_rows = [];
        }

        $rows = [];
        $used_stages = [];
        foreach ($raw_rows as $raw_row) {
            if (! is_array($raw_row)) {
                continue;
            }

            $stage_raw = isset($raw_row['stage_name']) ? sanitize_text_field((string) wp_unslash($raw_row['stage_name'])) : '';
            $amount_raw = isset($raw_row['amount']) ? sanitize_text_field((string) wp_unslash($raw_row['amount'])) : '';
            $unit_raw = isset($raw_row['unit']) ? sanitize_text_field((string) wp_unslash($raw_row['unit'])) : '';

            if ($stage_raw === '' && $amount_raw === '' && $unit_raw === '') {
                continue;
            }

            $stage_name = $this->sanitize_stage_name($stage_raw, $transaction_type);
            if ($stage_name === '') {
                return new WP_Error('eocrm_agreement_commission_stage', 'W podziale prowizji wybierz poprawny etap umowy.');
            }

            $stage_key = $this->normalize_commission_stage_key($stage_name);
            if ($stage_key !== '' && in_array($stage_key, $used_stages, true)) {
                return new WP_Error('eocrm_agreement_commission_stage_duplicate', 'Ten sam etap prowizji moze wystapic tylko raz.');
            }

            $amount = $this->sanitize_decimal($amount_raw);
            if ($amount <= 0) {
                return new WP_Error('eocrm_agreement_commission_stage_amount', 'Dla kazdego etapu prowizji podaj kwote lub procent wiekszy od zera.');
            }

            $unit = $this->sanitize_commission_unit($unit_raw);
            if ($unit === '') {
                return new WP_Error('eocrm_agreement_commission_stage_unit', 'Dla kazdego etapu prowizji wybierz jednostke (% / PLN / EUR / USD).');
            }
            if ($unit === '%' && ($base_commission_amount <= 0 || $base_commission_unit === '')) {
                return new WP_Error('eocrm_agreement_commission_stage_base', 'Podzial procentowy wymaga podania pelnej prowizji bazowej umowy.');
            }

            $used_stages[] = $stage_key;
            $rows[] = [
                'stage_name' => $stage_name,
                'amount' => $amount,
                'unit' => $unit,
            ];
        }

        if (empty($rows)) {
            return new WP_Error('eocrm_agreement_commission_stage_empty', 'Po wlaczeniu podzialu prowizji dodaj co najmniej jeden etap.');
        }

        $json = wp_json_encode($rows);
        if (! is_string($json) || $json === '') {
            return new WP_Error('eocrm_agreement_commission_stage_json', 'Nie udalo sie przygotowac podzialu prowizji do zapisu.');
        }

        return [
            'enabled' => 1,
            'json' => $json,
        ];
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

    /**
     * @return array{stage_name:string,amount:float,unit:string}|null
     */
    private function find_commission_stage_row(bool $enabled, string $json, string $stage_name): ?array
    {
        if (! $enabled || $stage_name === '') {
            return null;
        }

        $target = $this->normalize_commission_stage_key($stage_name);
        if ($target === '') {
            return null;
        }

        foreach ($this->decode_commission_stages($json) as $row) {
            if ($this->normalize_commission_stage_key((string) $row['stage_name']) === $target) {
                return $row;
            }
        }

        return null;
    }

    private function commission_stage_transaction_exists(int $agreement_id, string $stage_name): bool
    {
        global $wpdb;

        if ($agreement_id <= 0 || $stage_name === '') {
            return false;
        }

        $target = $this->normalize_commission_stage_key($stage_name);
        if ($target === '') {
            return false;
        }

        $table = $this->tables['transactions'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT commission_stage_name, commission_stages_json FROM {$table} WHERE agreement_id = %d AND is_active = 1 AND commission_stage_name IS NOT NULL AND commission_stage_name <> ''",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            $covered_names = $this->get_covered_commission_stage_names_from_payload((string) ($row['commission_stages_json'] ?? ''));
            if (empty($covered_names)) {
                $covered_names[] = (string) ($row['commission_stage_name'] ?? '');
            }

            foreach ($covered_names as $covered_name) {
                if ($this->normalize_commission_stage_key((string) $covered_name) === $target) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $agreement
     * @param array<int, array<string, mixed>> $plan_rows
     * @return string[]
     */
    private function get_unpaid_commission_stage_names(int $agreement_id, array $agreement, array $plan_rows): array
    {
        if ($agreement_id <= 0 || empty($plan_rows)) {
            return [];
        }

        $stage_index_map = $this->get_stage_index_map((string) ($agreement['transaction_type'] ?? ''));
        $paid_stage_keys = $this->get_paid_commission_stage_keys($agreement_id);
        $unpaid = [];

        foreach ($plan_rows as $row_index => $row) {
            $stage_name = trim((string) ($row['stage_name'] ?? ''));
            $stage_key = $this->normalize_commission_stage_key($stage_name);
            if ($stage_name === '' || $stage_key === '' || in_array($stage_key, $paid_stage_keys, true)) {
                continue;
            }

            $unpaid[] = [
                'name' => $stage_name,
                'index' => isset($stage_index_map[$stage_key]) ? (int) $stage_index_map[$stage_key] : PHP_INT_MAX,
                'row_index' => (int) $row_index,
            ];
        }

        usort($unpaid, static function (array $left, array $right): int {
            $by_stage = ((int) ($left['index'] ?? PHP_INT_MAX)) <=> ((int) ($right['index'] ?? PHP_INT_MAX));
            if ($by_stage !== 0) {
                return $by_stage;
            }

            return ((int) ($left['row_index'] ?? 0)) <=> ((int) ($right['row_index'] ?? 0));
        });

        $names = [];
        foreach ($unpaid as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return string[]
     */
    private function get_paid_commission_stage_keys(int $agreement_id): array
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT commission_stage_name, commission_stages_json
                FROM {$this->tables['transactions']}
                WHERE agreement_id = %d
                AND is_active = 1
                AND commission_stage_name IS NOT NULL
                AND commission_stage_name <> ''",
                $agreement_id
            ),
            ARRAY_A
        );

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
                $key = $this->normalize_commission_stage_key((string) $stage_name);
                if ($key !== '' && ! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * @return array<string, int>
     */
    private function get_stage_index_map(string $transaction_type): array
    {
        $stage_order = EstateOfficeCRM_Stages::load_for_transaction(
            $transaction_type,
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );

        $map = [];
        foreach ($stage_order as $index => $stage_name) {
            $stage_key = $this->normalize_commission_stage_key((string) $stage_name);
            if ($stage_key !== '') {
                $map[$stage_key] = (int) $index;
            }
        }

        return $map;
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

        $raw_names = isset($decoded['covered_stage_names']) && is_array($decoded['covered_stage_names']) ? $decoded['covered_stage_names'] : [];
        $names = [];
        foreach ($raw_names as $name) {
            $name = trim(sanitize_text_field((string) $name));
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function normalize_commission_stage_key(string $stage_name): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $stage_name));
        if ($normalized === '') {
            return '';
        }

        return strtolower(remove_accents($normalized));
    }

    private function sync_exclusive_flag_to_related_properties(int $agreement_id, bool $is_exclusive): void
    {
        global $wpdb;

        if ($agreement_id <= 0 || ! $is_exclusive) {
            return;
        }

        $table = $this->tables['properties'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, tags_json
                FROM {$table}
                WHERE agreement_id = %d
                AND is_active = 1",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($rows) || empty($rows)) {
            return;
        }

        $now = current_time('mysql');
        foreach ($rows as $row) {
            $property_id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($property_id <= 0) {
                continue;
            }

            $tags = json_decode((string) ($row['tags_json'] ?? ''), true);
            if (! is_array($tags)) {
                $tags = [];
            }

            $normalized_tags = [];
            foreach ($tags as $tag) {
                $tag = sanitize_key((string) $tag);
                if ($tag !== '') {
                    $normalized_tags[] = $tag;
                }
            }

            if (! in_array('wylacznosc', $normalized_tags, true)) {
                $normalized_tags[] = 'wylacznosc';
            }
            $normalized_tags = array_values(array_unique($normalized_tags));

            $encoded_tags = wp_json_encode($normalized_tags);
            if (! is_string($encoded_tags) || $encoded_tags === '') {
                $encoded_tags = '[]';
            }

            $wpdb->update(
                $table,
                [
                    'is_exclusive' => 1,
                    'tags_json' => $encoded_tags,
                    'updated_at' => $now,
                ],
                ['id' => $property_id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
    }

    private function refresh_current_stage_from_history(int $agreement_id): void
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return;
        }

        $stage_name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT stage_name
                FROM {$this->tables['agreement_stages']}
                WHERE agreement_id = %d
                ORDER BY stage_date DESC, id DESC
                LIMIT 1",
                $agreement_id
            )
        );

        if (! is_string($stage_name) || trim($stage_name) === '') {
            return;
        }

        $wpdb->update(
            $this->tables['agreements'],
            [
                'current_stage' => sanitize_text_field($stage_name),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $agreement_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * @param int[] $client_ids
     */
    private function validate_payload(
        string $agreement_number,
        string $transaction_type,
        string $date_signed,
        string $date_end,
        bool $is_indefinite,
        float $commission_amount,
        string $commission_unit,
        array $client_ids,
        bool $has_new_client
    ): string {
        if ($agreement_number === '') {
            return 'Numer umowy jest wymagany.';
        }

        if ($transaction_type === '') {
            return 'Typ transakcji jest wymagany.';
        }

        if (! $this->is_valid_date($date_signed)) {
            return 'Data zawarcia ma niepoprawny format.';
        }

        if (! $is_indefinite) {
            if (! $this->is_valid_date($date_end)) {
                return 'Data zakonczenia ma niepoprawny format.';
            }

            if ($date_end < $date_signed) {
                return 'Data zakonczenia nie moze byc wczesniejsza niz data zawarcia.';
            }
        }

        if ($commission_amount < 0) {
            return 'Kwota prowizji nie moze byc ujemna.';
        }

        if ($commission_amount > 0 && $commission_unit === '') {
            return 'Dla prowizji podaj jednostke (% / PLN / EUR / USD).';
        }

        if (empty($client_ids) && ! $has_new_client) {
            return 'Wybierz co najmniej jednego klienta do umowy lub dodaj nowego klienta.';
        }

        if (! empty($client_ids) && ! $this->all_clients_exist($client_ids)) {
            return 'Wybrani klienci nie istnieja lub sa nieaktywni.';
        }

        return '';
    }

    /**
     * @param int[] $client_ids
     */
    private function all_clients_exist(array $client_ids): bool
    {
        global $wpdb;

        if (empty($client_ids)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($client_ids), '%d'));
        $sql = "SELECT id, owner_user_id FROM {$this->tables['clients']} WHERE is_active = 1 AND id IN ({$placeholders})";
        $prepared = $wpdb->prepare($sql, $client_ids);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above; IDs are sanitized integers.
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (! is_array($rows) || count($rows) !== count($client_ids)) {
            return false;
        }

        foreach ($rows as $row) {
            $owner_user_id = isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                return false;
            }
        }

        return true;
    }

    private function is_valid_owner_user(int $user_id): bool
    {
        return EstateOfficeCRM_Access::is_valid_owner_user($user_id);
    }

    private function can_access_owner_user_id(int $owner_user_id): bool
    {
        return EstateOfficeCRM_Access::can_access_owner_user($owner_user_id, $this->tables, get_current_user_id());
    }

    private function get_agreement_owner_user_id(int $agreement_id): int
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT owner_user_id FROM {$this->tables['agreements']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            )
        );
    }


    /**
     * @return array<string, mixed>
     */
    private function collect_new_client_payloads(): array
    {
        $payloads = [];
        $legacy_payload = $this->collect_new_client_payload();
        if ($this->new_client_payload_has_data($legacy_payload)) {
            $payloads[] = $legacy_payload;
        }

        $raw_rows = isset($_POST['new_clients']) ? wp_unslash($_POST['new_clients']) : [];
        if (! is_array($raw_rows)) {
            return $payloads;
        }

        foreach ($raw_rows as $raw_row) {
            if (! is_array($raw_row)) {
                continue;
            }

            $payload = $this->collect_new_client_payload_from_array($raw_row);
            if ($this->new_client_payload_has_data($payload)) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function new_client_payload_has_data(array $payload): bool
    {
        $main_address = is_array($payload['main_address'] ?? null) ? $payload['main_address'] : [];
        $fields = [
            $payload['first_name'] ?? '',
            $payload['last_name'] ?? '',
            $payload['company_name'] ?? '',
            $payload['representative_name'] ?? '',
            $payload['phone'] ?? '',
            $payload['email'] ?? '',
            $main_address['street'] ?? '',
            $main_address['building_no'] ?? '',
            $main_address['postal_code'] ?? '',
            $main_address['city'] ?? '',
        ];

        foreach ($fields as $field) {
            if (trim((string) $field) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function collect_new_client_payload_from_array(array $source): array
    {
        $text = static function (array $row, string $key): string {
            return isset($row[$key]) ? sanitize_text_field((string) $row[$key]) : '';
        };

        $client_type = $this->sanitize_client_type($text($source, 'client_type'));

        $main_address = [
            'street' => $text($source, 'address_street'),
            'building_no' => $text($source, 'address_building_no'),
            'apartment_no' => $text($source, 'address_apartment_no'),
            'postal_code' => $text($source, 'address_postal_code'),
            'city' => $text($source, 'address_city'),
            'country' => $text($source, 'address_country'),
        ];

        if ($main_address['country'] === '') {
            $main_address['country'] = 'Polska';
        }

        $is_correspondence_same = isset($source['correspondence_same']) && (string) $source['correspondence_same'] === '1';

        $corr_address = [
            'street' => $text($source, 'corr_street'),
            'building_no' => $text($source, 'corr_building_no'),
            'apartment_no' => $text($source, 'corr_apartment_no'),
            'postal_code' => $text($source, 'corr_postal_code'),
            'city' => $text($source, 'corr_city'),
            'country' => $text($source, 'corr_country'),
        ];

        if ($is_correspondence_same) {
            $corr_address = $main_address;
        } elseif ($corr_address['country'] === '') {
            $corr_address['country'] = 'Polska';
        }

        return [
            'client_type' => $client_type,
            'first_name' => $text($source, 'first_name'),
            'last_name' => $text($source, 'last_name'),
            'company_name' => $text($source, 'company_name'),
            'representative_name' => $text($source, 'representative_name'),
            'phone' => $text($source, 'phone'),
            'email' => $text($source, 'email'),
            'website' => isset($source['website']) ? esc_url_raw((string) $source['website']) : '',
            'pesel' => $text($source, 'pesel'),
            'document_type' => $this->sanitize_document_type($text($source, 'document_type')),
            'document_number' => $text($source, 'document_number'),
            'nip' => $text($source, 'nip'),
            'krs' => $text($source, 'krs'),
            'regon' => $text($source, 'regon'),
            'is_correspondence_same' => $is_correspondence_same,
            'main_address' => $main_address,
            'corr_address' => $corr_address,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_new_client_payload(): array
    {
        $client_type = $this->sanitize_client_type($this->post_text('new_client_type'));

        $main_address = [
            'street' => $this->post_text('new_client_address_street'),
            'building_no' => $this->post_text('new_client_address_building_no'),
            'apartment_no' => $this->post_text('new_client_address_apartment_no'),
            'postal_code' => $this->post_text('new_client_address_postal_code'),
            'city' => $this->post_text('new_client_address_city'),
            'country' => $this->post_text('new_client_address_country'),
        ];

        if ($main_address['country'] === '') {
            $main_address['country'] = 'Polska';
        }

        $is_correspondence_same = isset($_POST['new_client_correspondence_same']) && (string) wp_unslash($_POST['new_client_correspondence_same']) === '1';

        $corr_address = [
            'street' => $this->post_text('new_client_corr_street'),
            'building_no' => $this->post_text('new_client_corr_building_no'),
            'apartment_no' => $this->post_text('new_client_corr_apartment_no'),
            'postal_code' => $this->post_text('new_client_corr_postal_code'),
            'city' => $this->post_text('new_client_corr_city'),
            'country' => $this->post_text('new_client_corr_country'),
        ];

        if ($is_correspondence_same) {
            $corr_address = $main_address;
        } elseif ($corr_address['country'] === '') {
            $corr_address['country'] = 'Polska';
        }

        return [
            'client_type' => $client_type,
            'first_name' => $this->post_text('new_client_first_name'),
            'last_name' => $this->post_text('new_client_last_name'),
            'company_name' => $this->post_text('new_client_company_name'),
            'representative_name' => $this->post_text('new_client_representative_name'),
            'phone' => $this->post_text('new_client_phone'),
            'email' => $this->post_text('new_client_email'),
            'website' => isset($_POST['new_client_website']) ? esc_url_raw((string) wp_unslash($_POST['new_client_website'])) : '',
            'pesel' => $this->post_text('new_client_pesel'),
            'document_type' => $this->sanitize_document_type($this->post_text('new_client_document_type')),
            'document_number' => $this->post_text('new_client_document_number'),
            'nip' => $this->post_text('new_client_nip'),
            'krs' => $this->post_text('new_client_krs'),
            'regon' => $this->post_text('new_client_regon'),
            'is_correspondence_same' => $is_correspondence_same,
            'main_address' => $main_address,
            'corr_address' => $corr_address,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validate_new_client_payload(array $payload): string
    {
        $client_type = (string) ($payload['client_type'] ?? '');

        if (! in_array($client_type, ['person', 'company'], true)) {
            return 'Wybierz poprawny typ nowego klienta.';
        }

        if ($client_type === 'person') {
            if ((string) ($payload['first_name'] ?? '') === '' || (string) ($payload['last_name'] ?? '') === '') {
                return 'Dla nowego klienta (osoba fizyczna) wymagane sa imie i nazwisko.';
            }
        } else {
            if ((string) ($payload['company_name'] ?? '') === '') {
                return 'Dla nowego klienta (firma) wymagana jest nazwa firmy.';
            }
        }

        $phone = (string) ($payload['phone'] ?? '');
        if ($phone === '') {
            return 'Dla nowego klienta wymagany jest numer telefonu.';
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email !== '' && ! is_email($email)) {
            return 'Dla nowego klienta podany adres e-mail ma niepoprawny format.';
        }

        $main_address = is_array($payload['main_address'] ?? null) ? $payload['main_address'] : [];
        if ((string) ($main_address['street'] ?? '') === '' || (string) ($main_address['building_no'] ?? '') === '' || (string) ($main_address['postal_code'] ?? '') === '' || (string) ($main_address['city'] ?? '') === '') {
            return 'Nowy klient: adres glowny wymaga pol ulica, numer, kod pocztowy i miasto.';
        }

        $is_correspondence_same = (bool) ($payload['is_correspondence_same'] ?? true);
        if (! $is_correspondence_same) {
            $corr_address = is_array($payload['corr_address'] ?? null) ? $payload['corr_address'] : [];
            if ((string) ($corr_address['street'] ?? '') === '' || (string) ($corr_address['building_no'] ?? '') === '' || (string) ($corr_address['postal_code'] ?? '') === '' || (string) ($corr_address['city'] ?? '') === '') {
                return 'Nowy klient: adres korespondencyjny wymaga pol ulica, numer, kod pocztowy i miasto.';
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return int|WP_Error
     */
    private function create_new_client_record(array $payload, int $owner_user_id, string $now)
    {
        global $wpdb;

        $client_type = (string) ($payload['client_type'] ?? '');
        $main_address = is_array($payload['main_address'] ?? null) ? $payload['main_address'] : [];
        $corr_address = is_array($payload['corr_address'] ?? null) ? $payload['corr_address'] : [];

        $insert_ok = $wpdb->insert(
            $this->tables['clients'],
            [
                'client_type' => $client_type,
                'first_name' => $client_type === 'person' ? (string) ($payload['first_name'] ?? '') : '',
                'last_name' => $client_type === 'person' ? (string) ($payload['last_name'] ?? '') : '',
                'company_name' => $client_type === 'company' ? (string) ($payload['company_name'] ?? '') : '',
                'representative_name' => $client_type === 'company' ? (string) ($payload['representative_name'] ?? '') : '',
                'phone' => (string) ($payload['phone'] ?? ''),
                'email' => (string) ($payload['email'] ?? ''),
                'website' => $client_type === 'company' ? (string) ($payload['website'] ?? '') : '',
                'pesel' => $client_type === 'person' ? (string) ($payload['pesel'] ?? '') : '',
                'document_type' => $client_type === 'person' ? (string) ($payload['document_type'] ?? '') : '',
                'document_number' => $client_type === 'person' ? (string) ($payload['document_number'] ?? '') : '',
                'nip' => $client_type === 'company' ? (string) ($payload['nip'] ?? '') : '',
                'krs' => $client_type === 'company' ? (string) ($payload['krs'] ?? '') : '',
                'regon' => $client_type === 'company' ? (string) ($payload['regon'] ?? '') : '',
                'owner_user_id' => $owner_user_id,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%d',
                '%d', '%s', '%s',
            ]
        );

        if (! $insert_ok) {
            return new WP_Error('eocrm_agreement_new_client_insert', 'Nie udalo sie zapisac nowego klienta.');
        }

        $client_id = (int) $wpdb->insert_id;
        if ($client_id <= 0) {
            return new WP_Error('eocrm_agreement_new_client_insert', 'Brak identyfikatora nowego klienta.');
        }

        $main_inserted = $this->insert_client_address($client_id, 'main', $main_address, $now);
        if (! $main_inserted) {
            $wpdb->delete($this->tables['clients'], ['id' => $client_id], ['%d']);
            return new WP_Error('eocrm_agreement_new_client_address', 'Nie udalo sie zapisac adresu glownego nowego klienta.');
        }

        $corr_inserted = $this->insert_client_address($client_id, 'correspondence', $corr_address, $now);
        if (! $corr_inserted) {
            $wpdb->delete($this->tables['client_addresses'], ['client_id' => $client_id], ['%d']);
            $wpdb->delete($this->tables['clients'], ['id' => $client_id], ['%d']);
            return new WP_Error('eocrm_agreement_new_client_address', 'Nie udalo sie zapisac adresu korespondencyjnego nowego klienta.');
        }

        return $client_id;
    }

    /**
     * @param array<string, string> $address
     */
    private function insert_client_address(int $client_id, string $address_type, array $address, string $now): bool
    {
        global $wpdb;

        return (bool) $wpdb->insert(
            $this->tables['client_addresses'],
            [
                'client_id' => $client_id,
                'address_type' => $address_type,
                'street' => (string) ($address['street'] ?? ''),
                'building_no' => (string) ($address['building_no'] ?? ''),
                'apartment_no' => (string) ($address['apartment_no'] ?? ''),
                'postal_code' => (string) ($address['postal_code'] ?? ''),
                'city' => (string) ($address['city'] ?? ''),
                'district' => '',
                'county' => '',
                'precinct' => '',
                'plot_number' => '',
                'country' => (string) ($address['country'] ?? ''),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            ]
        );
    }

    private function insert_initial_stage(int $agreement_id, string $stage_name, string $date_signed, int $owner_user_id, string $now): bool
    {
        global $wpdb;

        return (bool) $wpdb->insert(
            $this->tables['agreement_stages'],
            [
                'agreement_id' => $agreement_id,
                'stage_name' => $stage_name,
                'stage_date' => $date_signed,
                'created_by' => $owner_user_id,
                'created_at' => $now,
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * @param int[] $client_ids
     */
    private function link_clients(int $agreement_id, array $client_ids, string $now): bool
    {
        global $wpdb;

        foreach ($client_ids as $client_id) {
            $inserted = $wpdb->insert(
                $this->tables['agreement_clients'],
                [
                    'agreement_id' => $agreement_id,
                    'client_id' => $client_id,
                    'relation_role' => '',
                    'created_at' => $now,
                ],
                ['%d', '%d', '%s', '%s']
            );

            if (! $inserted) {
                $wpdb->delete($this->tables['agreement_clients'], ['agreement_id' => $agreement_id], ['%d']);
                return false;
            }
        }

        return true;
    }

    /**
     * @param int[] $client_ids
     */
    private function replace_linked_clients(int $agreement_id, array $client_ids, string $now): bool
    {
        global $wpdb;

        $wpdb->delete($this->tables['agreement_clients'], ['agreement_id' => $agreement_id], ['%d']);

        if (empty($client_ids)) {
            return false;
        }

        return $this->link_clients($agreement_id, $client_ids, $now);
    }

    private function post_text(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field((string) wp_unslash($_POST[$key])) : '';
    }

    /**
     * @return array{number: string, settings: array<string,mixed>}
     */
    private function generate_next_available_agreement_number(string $agreement_scope = ''): array
    {
        global $wpdb;

        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            'agreement',
            function (string $key): string {
                return $this->get_setting_value($key);
            },
            $agreement_scope
        );

        $table = $this->tables['agreements'];
        return EstateOfficeCRM_Numbering::generate_unique_number(
            $settings,
            function (string $candidate) use ($wpdb, $table): bool {
                if ($candidate === '') {
                    return true;
                }

                $exists = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE agreement_number = %s LIMIT 1",
                        $candidate
                    )
                );

                return $exists > 0;
            }
        );
    }

    private function peek_default_agreement_number(string $agreement_scope = ''): string
    {
        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            'agreement',
            function (string $key): string {
                return $this->get_setting_value($key);
            },
            $agreement_scope
        );

        return EstateOfficeCRM_Numbering::build_number_preview($settings, current_time('timestamp'));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function persist_numbering_settings(string $entity, array $settings, string $agreement_scope = ''): void
    {
        $updates = EstateOfficeCRM_Numbering::collect_setting_updates($entity, $settings, $agreement_scope);
        foreach ($updates as $setting_key => $setting_value) {
            $this->upsert_setting_value((string) $setting_key, (string) $setting_value);
        }
    }

    private function agreement_scope_from_transaction_type(string $transaction_type): string
    {
        $map = [
            'SPRZEDAZ' => 'sprzedaz',
            'KUPNO' => 'kupno',
            'WYNAJEM' => 'wynajem',
            'NAJEM' => 'najem',
        ];

        return isset($map[$transaction_type]) ? (string) $map[$transaction_type] : '';
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

    private function upsert_setting_value(string $key, string $value): void
    {
        global $wpdb;

        $table = $this->tables['settings'];
        $now = current_time('mysql');

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE setting_key = %s LIMIT 1",
                $key
            )
        );

        if ($exists) {
            $wpdb->update(
                $table,
                [
                    'setting_value' => $value,
                    'updated_at' => $now,
                ],
                ['setting_key' => $key],
                ['%s', '%s'],
                ['%s']
            );
            return;
        }

        $wpdb->insert(
            $table,
            [
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s']
        );
    }

    private function sanitize_transaction_type(string $value): string
    {
        $allowed = ['SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM'];
        $value = strtoupper($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_stage_name(string $value, string $transaction_type = ''): string
    {
        return EstateOfficeCRM_Stages::sanitize_stage_for_transaction(
            $value,
            $transaction_type,
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );
    }

    private function sanitize_commission_unit(string $value): string
    {
        $allowed = ['%', 'PLN', 'EUR', 'USD'];
        $value = strtoupper($value);

        return in_array($value, $allowed, true) ? $value : '';
    }


    private function sanitize_client_type(string $value): string
    {
        $value = sanitize_key($value);
        return in_array($value, ['person', 'company'], true) ? $value : '';
    }

    private function sanitize_document_type(string $value): string
    {
        $value = sanitize_key($value);
        $allowed = ['dowod_osobisty', 'paszport', 'karta_pobytu'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_decimal(string $value): float
    {
        $value = str_replace(',', '.', $value);
        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function is_valid_date(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTime && $date->format('Y-m-d') === $value;
    }

    private function is_finished_stage_name(string $stage_name): bool
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $stage_name));
        if ($normalized === '') {
            return false;
        }

        $normalized = strtolower(remove_accents($normalized));
        return strpos($normalized, 'umowa zakonczona') === 0;
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

    private function is_settlement_commission_stage(string $stage_name): bool
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $stage_name));
        if ($normalized === '') {
            return false;
        }

        $normalized = strtolower(remove_accents($normalized));
        return strpos($normalized, 'umowa przyrzeczona') === 0 || strpos($normalized, 'umowa najmu') === 0;
    }

    private function redirect_after_create(int $agreement_id, string $transaction_type): void
    {
        $target_section = in_array($transaction_type, ['SPRZEDAZ', 'WYNAJEM'], true) ? 'properties' : 'searches';
        $target_mode = $target_section === 'properties' ? 'new-property' : 'new-search';

        $args = [
            'crm' => $target_section,
            'mode' => $target_mode,
            'agreement_id' => $agreement_id,
        ];

        wp_safe_redirect(add_query_arg($args, $this->get_section_url($target_section)));
        exit;
    }

    private function redirect_back(string $notice, string $message = '', bool $stay_on_form = false): void
    {
        $args = [
            'crm' => 'agreements',
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_form) {
            $args['mode'] = 'new-agreement';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('agreements')));
        exit;
    }

    private function redirect_to_agreement_profile(int $agreement_id, string $notice, string $message = '', bool $stay_on_edit_form = false): void
    {
        $args = [
            'crm' => 'agreements',
            'agreement_id' => $agreement_id,
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_edit_form) {
            $args['mode'] = 'edit-agreement';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('agreements')));
        exit;
    }

    private function get_section_url(string $section): string
    {
        $page_ids = get_option(EstateOfficeCRM_Installer::OPTION_PAGE_IDS, []);
        if (is_array($page_ids)) {
            if ($section === 'transactions') {
                $crm_page_id = isset($page_ids['crm']) ? absint((string) $page_ids['crm']) : 0;
                if ($crm_page_id > 0) {
                    $crm_url = get_permalink($crm_page_id);
                    if (is_string($crm_url) && $crm_url !== '') {
                        return $crm_url;
                    }
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
