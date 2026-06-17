<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Clients
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
        add_action('init', [$this, 'handle_frontend_create_client']);
        add_action('init', [$this, 'handle_frontend_update_client']);
        add_action('init', [$this, 'handle_frontend_delete_client']);
    }

    public function handle_frontend_create_client(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'create_client') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_clients') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do dodawania klientow.');
        }

        check_admin_referer('eocrm_create_client', 'eocrm_nonce');

        $result = $this->create_client_from_request();
        if (is_wp_error($result)) {
            $this->redirect_back('client_error', $result->get_error_message(), true);
        }

        $this->redirect_back('client_created', 'Klient zostal dodany.', false);
    }

    public function handle_frontend_update_client(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_client') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_clients') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do edycji klientow.');
        }

        check_admin_referer('eocrm_update_client', 'eocrm_client_update_nonce');

        $result = $this->update_client_from_request();
        if (is_wp_error($result)) {
            $client_id = isset($_POST['client_id']) ? absint((string) wp_unslash($_POST['client_id'])) : 0;
            $this->redirect_to_profile($client_id, 'client_error', $result->get_error_message(), true);
        }

        $client_id = (int) $result;
        $this->redirect_to_profile($client_id, 'client_updated', 'Dane klienta zostaly zaktualizowane.', false);
    }

    public function handle_frontend_delete_client(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'delete_client') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_delete_records') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do usuwania klientow.');
        }

        check_admin_referer('eocrm_delete_client', 'eocrm_client_delete_nonce');

        $client_id = isset($_POST['client_id']) ? absint((string) wp_unslash($_POST['client_id'])) : 0;
        if ($client_id <= 0) {
            $this->redirect_back('client_error', 'Brak identyfikatora klienta.', false);
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = $this->get_client_owner_user_id($client_id);
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                $this->redirect_back('client_error', 'Brak uprawnien do usuniecia tego klienta.', false);
            }
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->tables['clients'],
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $client_id,
                'is_active' => 1,
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $this->redirect_to_profile($client_id, 'client_error', 'Nie udalo sie usunac klienta.', false);
        }

        $this->redirect_back('client_deleted', 'Klient zostal usuniety.', false);
    }

    /**
     * @return int|WP_Error
     */
    private function create_client_from_request()
    {
        global $wpdb;

        $client_type_raw = isset($_POST['client_type']) ? sanitize_key((string) wp_unslash($_POST['client_type'])) : '';
        $client_type = $client_type_raw === 'company' ? 'company' : 'person';

        $first_name = $this->post_text('first_name');
        $last_name = $this->post_text('last_name');
        $company_name = $this->post_text('company_name');
        $representative_name = $this->post_text('representative_name');

        $phone = $this->post_text('phone');
        $email = $this->post_text('email');
        $website = isset($_POST['website']) ? esc_url_raw((string) wp_unslash($_POST['website'])) : '';

        $pesel = $this->post_text('pesel');
        $document_type = $this->sanitize_document_type($this->post_text('document_type'));
        $document_number = $this->post_text('document_number');
        $nip = $this->post_text('nip');
        $krs = $this->post_text('krs');
        $regon = $this->post_text('regon');

        $main_address = [
            'street' => $this->post_text('address_street'),
            'building_no' => $this->post_text('address_building_no'),
            'apartment_no' => $this->post_text('address_apartment_no'),
            'postal_code' => $this->post_text('address_postal_code'),
            'city' => $this->post_text('address_city'),
            'country' => $this->post_text('address_country'),
        ];

        if ($main_address['country'] === '') {
            $main_address['country'] = 'Polska';
        }

        $is_correspondence_same = isset($_POST['correspondence_same']) && (string) wp_unslash($_POST['correspondence_same']) === '1';

        $corr_address = [
            'street' => $this->post_text('corr_street'),
            'building_no' => $this->post_text('corr_building_no'),
            'apartment_no' => $this->post_text('corr_apartment_no'),
            'postal_code' => $this->post_text('corr_postal_code'),
            'city' => $this->post_text('corr_city'),
            'country' => $this->post_text('corr_country'),
        ];

        if ($is_correspondence_same) {
            $corr_address = $main_address;
        } elseif ($corr_address['country'] === '') {
            $corr_address['country'] = 'Polska';
        }

        $validation_error = $this->validate_client_payload(
            $client_type,
            $first_name,
            $last_name,
            $company_name,
            $phone,
            $email,
            $main_address,
            $is_correspondence_same,
            $corr_address
        );

        if ($validation_error !== '') {
            return new WP_Error('eocrm_client_validation', $validation_error);
        }

        $now = current_time('mysql');
        $owner_user_id = get_current_user_id();
        $clients_table = $this->tables['clients'];
        $addresses_table = $this->tables['client_addresses'];

        $insert_ok = $wpdb->insert(
            $clients_table,
            [
                'client_type' => $client_type,
                'first_name' => $client_type === 'person' ? $first_name : '',
                'last_name' => $client_type === 'person' ? $last_name : '',
                'company_name' => $client_type === 'company' ? $company_name : '',
                'representative_name' => $client_type === 'company' ? $representative_name : '',
                'phone' => $phone,
                'email' => $email,
                'website' => $client_type === 'company' ? $website : '',
                'pesel' => $client_type === 'person' ? $pesel : '',
                'document_type' => $client_type === 'person' ? $document_type : '',
                'document_number' => $client_type === 'person' ? $document_number : '',
                'nip' => $client_type === 'company' ? $nip : '',
                'krs' => $client_type === 'company' ? $krs : '',
                'regon' => $client_type === 'company' ? $regon : '',
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
            return new WP_Error('eocrm_client_insert', 'Nie udalo sie zapisac klienta.');
        }

        $client_id = (int) $wpdb->insert_id;
        if ($client_id <= 0) {
            return new WP_Error('eocrm_client_insert', 'Brak identyfikatora nowego klienta.');
        }

        $main_inserted = $this->insert_address($client_id, 'main', $main_address, $now);
        if (! $main_inserted) {
            $wpdb->delete($clients_table, ['id' => $client_id], ['%d']);
            return new WP_Error('eocrm_address_insert', 'Nie udalo sie zapisac adresu glownego klienta.');
        }

        $corr_inserted = $this->insert_address($client_id, 'correspondence', $corr_address, $now);
        if (! $corr_inserted) {
            $wpdb->delete($addresses_table, ['client_id' => $client_id], ['%d']);
            $wpdb->delete($clients_table, ['id' => $client_id], ['%d']);
            return new WP_Error('eocrm_address_insert', 'Nie udalo sie zapisac adresu korespondencyjnego klienta.');
        }

        return $client_id;
    }

    /**
     * @return int|WP_Error
     */
    private function update_client_from_request()
    {
        global $wpdb;

        $client_id = isset($_POST['client_id']) ? absint((string) wp_unslash($_POST['client_id'])) : 0;
        if ($client_id <= 0) {
            return new WP_Error('eocrm_client_update', 'Brak identyfikatora klienta.');
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, owner_user_id FROM {$this->tables['clients']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $client_id
            ),
            ARRAY_A
        );
        if (! is_array($existing)) {
            return new WP_Error('eocrm_client_update', 'Klient nie istnieje lub jest nieaktywny.');
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = isset($existing['owner_user_id']) ? (int) $existing['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                return new WP_Error('eocrm_client_update', 'Brak uprawnien do edycji tego klienta.');
            }
        }

        $owner_user_id_for_update = null;
        if (current_user_can('manage_options')) {
            $owner_user_id_for_update = isset($_POST['owner_user_id']) ? absint((string) wp_unslash($_POST['owner_user_id'])) : 0;
            if ($owner_user_id_for_update <= 0) {
                return new WP_Error('eocrm_client_update', 'Wybierz opiekuna klienta.');
            }

            if (! $this->is_valid_owner_user($owner_user_id_for_update)) {
                return new WP_Error('eocrm_client_update', 'Wybrany opiekun jest nieprawidlowy.');
            }
        }

        $client_type_raw = isset($_POST['client_type']) ? sanitize_key((string) wp_unslash($_POST['client_type'])) : '';
        $client_type = $client_type_raw === 'company' ? 'company' : 'person';

        $first_name = $this->post_text('first_name');
        $last_name = $this->post_text('last_name');
        $company_name = $this->post_text('company_name');
        $representative_name = $this->post_text('representative_name');

        $phone = $this->post_text('phone');
        $email = $this->post_text('email');
        $website = isset($_POST['website']) ? esc_url_raw((string) wp_unslash($_POST['website'])) : '';

        $pesel = $this->post_text('pesel');
        $document_type = $this->sanitize_document_type($this->post_text('document_type'));
        $document_number = $this->post_text('document_number');
        $nip = $this->post_text('nip');
        $krs = $this->post_text('krs');
        $regon = $this->post_text('regon');

        $main_address = [
            'street' => $this->post_text('address_street'),
            'building_no' => $this->post_text('address_building_no'),
            'apartment_no' => $this->post_text('address_apartment_no'),
            'postal_code' => $this->post_text('address_postal_code'),
            'city' => $this->post_text('address_city'),
            'country' => $this->post_text('address_country'),
        ];

        if ($main_address['country'] === '') {
            $main_address['country'] = 'Polska';
        }

        $is_correspondence_same = isset($_POST['correspondence_same']) && (string) wp_unslash($_POST['correspondence_same']) === '1';

        $corr_address = [
            'street' => $this->post_text('corr_street'),
            'building_no' => $this->post_text('corr_building_no'),
            'apartment_no' => $this->post_text('corr_apartment_no'),
            'postal_code' => $this->post_text('corr_postal_code'),
            'city' => $this->post_text('corr_city'),
            'country' => $this->post_text('corr_country'),
        ];

        if ($is_correspondence_same) {
            $corr_address = $main_address;
        } elseif ($corr_address['country'] === '') {
            $corr_address['country'] = 'Polska';
        }

        $validation_error = $this->validate_client_payload(
            $client_type,
            $first_name,
            $last_name,
            $company_name,
            $phone,
            $email,
            $main_address,
            $is_correspondence_same,
            $corr_address
        );

        if ($validation_error !== '') {
            return new WP_Error('eocrm_client_validation', $validation_error);
        }

        $now = current_time('mysql');
        $client_update_payload = [
            'client_type' => $client_type,
            'first_name' => $client_type === 'person' ? $first_name : '',
            'last_name' => $client_type === 'person' ? $last_name : '',
            'company_name' => $client_type === 'company' ? $company_name : '',
            'representative_name' => $client_type === 'company' ? $representative_name : '',
            'phone' => $phone,
            'email' => $email,
            'website' => $client_type === 'company' ? $website : '',
            'pesel' => $client_type === 'person' ? $pesel : '',
            'document_type' => $client_type === 'person' ? $document_type : '',
            'document_number' => $client_type === 'person' ? $document_number : '',
            'nip' => $client_type === 'company' ? $nip : '',
            'krs' => $client_type === 'company' ? $krs : '',
            'regon' => $client_type === 'company' ? $regon : '',
            'updated_at' => $now,
        ];

        $client_update_format = [
            '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s',
        ];

        if (is_int($owner_user_id_for_update) && $owner_user_id_for_update > 0) {
            $client_update_payload['owner_user_id'] = $owner_user_id_for_update;
            $client_update_format[] = '%d';
        }

        $updated = $wpdb->update(
            $this->tables['clients'],
            $client_update_payload,
            ['id' => $client_id],
            $client_update_format,
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('eocrm_client_update', 'Nie udalo sie zaktualizowac klienta.');
        }

        if (! $this->upsert_address($client_id, 'main', $main_address, $now)) {
            return new WP_Error('eocrm_client_update', 'Nie udalo sie zaktualizowac adresu glownego klienta.');
        }

        if (! $this->upsert_address($client_id, 'correspondence', $corr_address, $now)) {
            return new WP_Error('eocrm_client_update', 'Nie udalo sie zaktualizowac adresu korespondencyjnego klienta.');
        }

        return $client_id;
    }

    /**
     * @param array<string, string> $address
     */
    private function insert_address(int $client_id, string $address_type, array $address, string $now): bool
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

    /**
     * @param array<string, string> $address
     */
    private function upsert_address(int $client_id, string $address_type, array $address, string $now): bool
    {
        global $wpdb;

        $table = $this->tables['client_addresses'];
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE client_id = %d AND address_type = %s LIMIT 1",
                $client_id,
                $address_type
            )
        );

        $payload = [
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
            'updated_at' => $now,
        ];

        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $table,
                $payload,
                ['id' => $existing_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        return (bool) $wpdb->insert(
            $table,
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

    /**
     * @param array<string, string> $main_address
     * @param array<string, string> $corr_address
     */
    private function validate_client_payload(
        string $client_type,
        string $first_name,
        string $last_name,
        string $company_name,
        string $phone,
        string $email,
        array $main_address,
        bool $is_correspondence_same,
        array $corr_address
    ): string {
        if ($client_type === 'person') {
            if ($first_name === '' || $last_name === '') {
                return 'Dla osoby fizycznej wymagane sa: imie i nazwisko.';
            }
        } else {
            if ($company_name === '') {
                return 'Dla firmy wymagana jest nazwa firmy.';
            }
        }

        if ($phone === '') {
            return 'Numer telefonu jest wymagany.';
        }

        if ($email !== '' && ! is_email($email)) {
            return 'Podany adres e-mail ma niepoprawny format.';
        }

        if (($main_address['street'] ?? '') === '' || ($main_address['building_no'] ?? '') === '' || ($main_address['postal_code'] ?? '') === '' || ($main_address['city'] ?? '') === '') {
            return 'Adres glowny: wymagane sa pola ulica, numer, kod pocztowy i miasto.';
        }

        if (! $is_correspondence_same) {
            if (($corr_address['street'] ?? '') === '' || ($corr_address['building_no'] ?? '') === '' || ($corr_address['postal_code'] ?? '') === '' || ($corr_address['city'] ?? '') === '') {
                return 'Adres korespondencyjny: wymagane sa pola ulica, numer, kod pocztowy i miasto.';
            }
        }

        return '';
    }

    private function post_text(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field((string) wp_unslash($_POST[$key])) : '';
    }

    private function sanitize_document_type(string $value): string
    {
        $allowed = ['dowod_osobisty', 'paszport', 'karta_pobytu'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function is_valid_owner_user(int $user_id): bool
    {
        return EstateOfficeCRM_Access::is_valid_owner_user($user_id);
    }

    private function can_access_owner_user_id(int $owner_user_id): bool
    {
        return EstateOfficeCRM_Access::can_access_owner_user($owner_user_id, $this->tables, get_current_user_id());
    }

    private function get_client_owner_user_id(int $client_id): int
    {
        global $wpdb;

        if ($client_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT owner_user_id FROM {$this->tables['clients']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $client_id
            )
        );
    }

    private function redirect_back(string $notice, string $message = '', bool $stay_on_form = false): void
    {
        $args = [
            'crm' => 'clients',
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_form) {
            $args['mode'] = 'new-client';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('clients')));
        exit;
    }

    private function redirect_to_profile(int $client_id, string $notice, string $message = '', bool $stay_on_edit_form = false): void
    {
        $args = [
            'crm' => 'clients',
            'client_id' => max(0, $client_id),
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_edit_form) {
            $args['mode'] = 'edit-client';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('clients')));
        exit;
    }

    private function get_section_url(string $section): string
    {
        $page_ids = get_option(EstateOfficeCRM_Installer::OPTION_PAGE_IDS, []);
        if (is_array($page_ids)) {
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
