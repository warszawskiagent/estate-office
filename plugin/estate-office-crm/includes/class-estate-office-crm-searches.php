<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Searches
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
        add_action('init', [$this, 'handle_frontend_create_search']);
        add_action('init', [$this, 'handle_frontend_update_search']);
        add_action('init', [$this, 'handle_frontend_delete_search']);
    }

    public function handle_frontend_create_search(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'create_search') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_searches') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do dodawania poszukiwan.');
        }

        check_admin_referer('eocrm_create_search', 'eocrm_nonce');

        $result = $this->create_search_from_request();
        if (is_wp_error($result)) {
            $this->redirect_back('search_error', $result->get_error_message(), true);
        }

        $search_id = (int) $result;
        $agreement_id = absint((string) $this->post_text('agreement_id'));
        if ($agreement_id > 0) {
            $pending_commission_stage = $this->get_open_current_commission_stage_for_agreement($agreement_id);
            $message = $pending_commission_stage !== ''
                ? 'Poszukiwanie zostalo dodane. W umowie jest zaplanowana prowizja czesciowa dla etapu: ' . $pending_commission_stage . '. Dodaj ja z karty umowy, gdy dane transakcji beda gotowe.'
                : 'Poszukiwanie zostalo dodane. Wrociles do karty utworzonej umowy.';
            $this->redirect_to_agreement_profile_after_create($agreement_id, 'search_created', $message);
        }

        $this->redirect_to_profile($search_id, 'search_created', 'Poszukiwanie zostalo dodane.', false);
    }

    public function handle_frontend_update_search(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_search') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_searches') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do edycji poszukiwan.');
        }

        check_admin_referer('eocrm_update_search', 'eocrm_search_update_nonce');

        $result = $this->update_search_from_request();
        if (is_wp_error($result)) {
            $search_id = isset($_POST['search_id']) ? absint((string) wp_unslash($_POST['search_id'])) : 0;
            $this->redirect_to_profile($search_id, 'search_error', $result->get_error_message(), true);
        }

        $search_id = (int) $result;
        $this->redirect_to_profile($search_id, 'search_updated', 'Poszukiwanie zostalo zaktualizowane.', false);
    }

    public function handle_frontend_delete_search(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'delete_search') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_delete_records') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do usuwania poszukiwan.');
        }

        check_admin_referer('eocrm_delete_search', 'eocrm_search_delete_nonce');

        $search_id = isset($_POST['search_id']) ? absint((string) wp_unslash($_POST['search_id'])) : 0;
        if ($search_id <= 0) {
            $this->redirect_back('search_error', 'Brak identyfikatora poszukiwania.', false);
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = $this->get_search_owner_user_id($search_id);
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                $this->redirect_back('search_error', 'Brak uprawnien do usuniecia tego poszukiwania.', false);
            }
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->tables['searches'],
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $search_id,
                'is_active' => 1,
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $this->redirect_to_profile($search_id, 'search_error', 'Nie udalo sie usunac poszukiwania.', false);
        }

        $this->redirect_back('search_deleted', 'Poszukiwanie zostalo usuniete.', false);
    }

    /**
     * @return int|WP_Error
     */
    private function create_search_from_request()
    {
        global $wpdb;

        $agreement_id = absint((string) $this->post_text('agreement_id'));
        $transaction_type = $this->get_agreement_transaction_type($agreement_id);

        if (! in_array($transaction_type, ['KUPNO', 'NAJEM'], true)) {
            return new WP_Error('eocrm_search_agreement', 'Wybierz poprawna umowe typu KUPNO lub NAJEM.');
        }

        $agreement_owner_user_id = $this->get_agreement_owner_user_id($agreement_id);
        if (! current_user_can('manage_options') && ! $this->can_access_owner_user_id($agreement_owner_user_id)) {
            return new WP_Error('eocrm_search_agreement_owner', 'Brak uprawnien do dodawania poszukiwan dla tej umowy.');
        }

        $search_number = $this->post_text('search_number');
        $next_search_numbering_settings = null;
        $default_search_number = $this->peek_default_search_number();
        if ($search_number === '' || $search_number === $default_search_number) {
            $generated_search_number = $this->generate_next_available_search_number();
            $search_number = $generated_search_number['number'];
            $next_search_numbering_settings = $generated_search_number['settings'];
        }
        $property_types = $this->sanitize_property_types($this->post_array('property_types'));
        if (empty($property_types)) {
            $legacy_property_type = $this->sanitize_property_type($this->post_text('property_type'));
            if ($legacy_property_type !== '') {
                $property_types[] = $legacy_property_type;
            }
        }
        $property_type = implode(',', $property_types);

        $budget_from = $this->sanitize_decimal_nullable($this->post_text('budget_from'));
        $budget_to = $this->sanitize_decimal_nullable($this->post_text('budget_to'));
        $area_from = $this->sanitize_decimal_nullable($this->post_text('area_from'));
        $area_to = $this->sanitize_decimal_nullable($this->post_text('area_to'));
        $rooms_from = $this->sanitize_non_negative_int_nullable($this->post_text('rooms_from'));
        $rooms_to = $this->sanitize_non_negative_int_nullable($this->post_text('rooms_to'));
        $floor_from = $this->sanitize_floor_nullable($this->post_text('floor_from'));
        $floor_to = $this->sanitize_floor_nullable($this->post_text('floor_to'));

        $location_text = $this->post_text('location_text');
        $description = isset($_POST['description']) ? wp_kses_post((string) wp_unslash($_POST['description'])) : '';

        $building_finish = $this->sanitize_building_finish($this->post_text('building_finish'));
        $kitchen_type = $this->sanitize_kitchen_type($this->post_text('kitchen_type'));
        $attic = $this->sanitize_bool_nullable($this->post_text('attic'));
        $multi_level = $this->sanitize_bool_nullable($this->post_text('multi_level'));

        $exposure = $this->sanitize_multi($this->post_array('exposure'), ['polnoc', 'poludnie', 'wschod', 'zachod']);
        $view = $this->sanitize_multi($this->post_array('view'), ['miasto', 'zielec', 'park', 'podworko', 'ulica', 'panorama']);
        $layout = $this->sanitize_multi($this->post_array('layout'), ['oddzielne_pokoje', 'salon_z_aneksem', 'dwustronne', 'narozne']);

        $heating = $this->sanitize_heating($this->post_text('heating'));
        $water = $this->sanitize_water($this->post_text('water'));
        $sewage = $this->sanitize_sewage($this->post_text('sewage'));
        $gas = $this->sanitize_bool_nullable($this->post_text('gas'));

        $amenities = $this->sanitize_multi($this->post_array('amenities'), [
            'winda', 'klimatyzacja', 'monitoring', 'recepcja', 'teren_zamkniety', 'domofon',
            'garaz', 'miejsce_postojowe',
        ]);
        $amenity_counts = [
            'garaz' => $this->sanitize_non_negative_int_nullable($this->post_text('amenity_garage_count')),
            'miejsce_postojowe' => $this->sanitize_non_negative_int_nullable($this->post_text('amenity_parking_space_count')),
        ];
        $furnished_state = $this->sanitize_furnished_state($this->post_text('furnished_state'));

        $equipment = $this->sanitize_multi($this->post_array('equipment'), [
            'pralka', 'zmywarka', 'lodowka', 'kuchenka', 'piekarnik', 'telewizor', 'mikrofala'
        ]);

        $extra_areas = [
            'balcony' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('balcony_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('balcony_area_min')),
            ],
            'terrace' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('terrace_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('terrace_area_min')),
            ],
            'basement' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('basement_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('basement_area_min')),
            ],
            'storage' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('storage_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('storage_area_min')),
            ],
            'garden' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('garden_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('garden_area_min')),
            ],
        ];

        $validation_error = $this->validate_search_payload([
            'search_number' => $search_number,
            'property_type' => $property_type,
            'location_text' => $location_text,
            'budget_from' => $budget_from,
            'budget_to' => $budget_to,
            'area_from' => $area_from,
            'area_to' => $area_to,
            'rooms_from' => $rooms_from,
            'rooms_to' => $rooms_to,
            'floor_from' => $floor_from,
            'floor_to' => $floor_to,
        ]);

        if ($validation_error !== '') {
            return new WP_Error('eocrm_search_validation', $validation_error);
        }

        if ($this->search_number_exists($search_number)) {
            return new WP_Error('eocrm_search_duplicate', 'Numer poszukiwania juz istnieje.');
        }

        $criteria = [
            'building_finish' => $building_finish,
            'kitchen_type' => $kitchen_type,
            'attic' => $attic,
            'multi_level' => $multi_level,
            'exposure' => $exposure,
            'view' => $view,
            'layout' => $layout,
            'heating' => $heating,
            'water' => $water,
            'sewage' => $sewage,
            'gas' => $gas,
            'amenities' => $amenities,
            'amenity_counts' => $amenity_counts,
            'furnished_state' => $furnished_state,
            'equipment' => $equipment,
            'extra_areas' => $extra_areas,
        ];

        $owner_user_id = get_current_user_id();
        $now = current_time('mysql');

        $insert_ok = $wpdb->insert(
            $this->tables['searches'],
            [
                'agreement_id' => $agreement_id,
                'search_number' => $search_number,
                'transaction_type' => $transaction_type,
                'property_type' => $property_type,
                'budget_from' => $budget_from,
                'budget_to' => $budget_to,
                'area_from' => $area_from,
                'area_to' => $area_to,
                'rooms_from' => $rooms_from,
                'rooms_to' => $rooms_to,
                'floor_from' => $floor_from,
                'floor_to' => $floor_to,
                'location_text' => $location_text,
                'description' => $description,
                'criteria_json' => wp_json_encode($criteria),
                'owner_user_id' => $owner_user_id,
                'created_by' => $owner_user_id,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (! $insert_ok) {
            return new WP_Error('eocrm_search_insert', 'Nie udalo sie zapisac poszukiwania.');
        }

        $search_id = (int) $wpdb->insert_id;
        if ($search_id <= 0) {
            return new WP_Error('eocrm_search_insert', 'Brak identyfikatora nowego poszukiwania.');
        }

        if (is_array($next_search_numbering_settings)) {
            $this->persist_numbering_settings('search', $next_search_numbering_settings);
        }

        $linked_ok = $this->link_clients_from_agreement($agreement_id, $search_id, $now);
        if (! $linked_ok) {
            $wpdb->delete($this->tables['searches'], ['id' => $search_id], ['%d']);
            return new WP_Error('eocrm_search_clients', 'Nie udalo sie powiazac klientow z poszukiwaniem.');
        }

        return $search_id;
    }

    /**
     * @return int|WP_Error
     */
    private function update_search_from_request()
    {
        global $wpdb;

        $search_id = isset($_POST['search_id']) ? absint((string) wp_unslash($_POST['search_id'])) : 0;
        if ($search_id <= 0) {
            return new WP_Error('eocrm_search_update', 'Brak identyfikatora poszukiwania.');
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, transaction_type, property_type, criteria_json, owner_user_id
                FROM {$this->tables['searches']}
                WHERE id = %d AND is_active = 1
                LIMIT 1",
                $search_id
            ),
            ARRAY_A
        );

        if (! is_array($existing)) {
            return new WP_Error('eocrm_search_update', 'Poszukiwanie nie istnieje lub jest nieaktywne.');
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = isset($existing['owner_user_id']) ? (int) $existing['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                return new WP_Error('eocrm_search_update', 'Brak uprawnien do edycji tego poszukiwania.');
            }
        }

        $property_types = $this->sanitize_property_types($this->post_array('property_types'));
        if (empty($property_types)) {
            $legacy_property_type = $this->sanitize_property_type($this->post_text('property_type'));
            if ($legacy_property_type !== '') {
                $property_types[] = $legacy_property_type;
            }
        }
        if (empty($property_types) && isset($existing['property_type'])) {
            $property_types = $this->sanitize_property_types(explode(',', (string) $existing['property_type']));
        }
        $property_type = implode(',', $property_types);

        $budget_from = $this->sanitize_decimal_nullable($this->post_text('budget_from'));
        $budget_to = $this->sanitize_decimal_nullable($this->post_text('budget_to'));
        $area_from = $this->sanitize_decimal_nullable($this->post_text('area_from'));
        $area_to = $this->sanitize_decimal_nullable($this->post_text('area_to'));
        $rooms_from = $this->sanitize_non_negative_int_nullable($this->post_text('rooms_from'));
        $rooms_to = $this->sanitize_non_negative_int_nullable($this->post_text('rooms_to'));
        $floor_from = $this->sanitize_floor_nullable($this->post_text('floor_from'));
        $floor_to = $this->sanitize_floor_nullable($this->post_text('floor_to'));
        $location_text = $this->post_text('location_text');
        $description = isset($_POST['description']) ? wp_kses_post((string) wp_unslash($_POST['description'])) : '';

        $building_finish = $this->sanitize_building_finish($this->post_text('building_finish'));
        $kitchen_type = $this->sanitize_kitchen_type($this->post_text('kitchen_type'));
        $attic = $this->sanitize_bool_nullable($this->post_text('attic'));
        $multi_level = $this->sanitize_bool_nullable($this->post_text('multi_level'));

        $exposure = $this->sanitize_multi($this->post_array('exposure'), ['polnoc', 'poludnie', 'wschod', 'zachod']);
        $view = $this->sanitize_multi($this->post_array('view'), ['miasto', 'zielec', 'park', 'podworko', 'ulica', 'panorama']);
        $layout = $this->sanitize_multi($this->post_array('layout'), ['oddzielne_pokoje', 'salon_z_aneksem', 'dwustronne', 'narozne']);

        $heating = $this->sanitize_heating($this->post_text('heating'));
        $water = $this->sanitize_water($this->post_text('water'));
        $sewage = $this->sanitize_sewage($this->post_text('sewage'));
        $gas = $this->sanitize_bool_nullable($this->post_text('gas'));

        $amenities = $this->sanitize_multi($this->post_array('amenities'), [
            'winda', 'klimatyzacja', 'monitoring', 'recepcja', 'teren_zamkniety', 'domofon',
            'garaz', 'miejsce_postojowe',
        ]);
        $amenity_counts = [
            'garaz' => $this->sanitize_non_negative_int_nullable($this->post_text('amenity_garage_count')),
            'miejsce_postojowe' => $this->sanitize_non_negative_int_nullable($this->post_text('amenity_parking_space_count')),
        ];
        $furnished_state = $this->sanitize_furnished_state($this->post_text('furnished_state'));

        $equipment = $this->sanitize_multi($this->post_array('equipment'), [
            'pralka', 'zmywarka', 'lodowka', 'kuchenka', 'piekarnik', 'telewizor', 'mikrofala'
        ]);

        $extra_areas = [
            'balcony' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('balcony_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('balcony_area_min')),
            ],
            'terrace' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('terrace_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('terrace_area_min')),
            ],
            'basement' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('basement_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('basement_area_min')),
            ],
            'storage' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('storage_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('storage_area_min')),
            ],
            'garden' => [
                'has' => $this->sanitize_bool_nullable($this->post_text('garden_has')),
                'area_min' => $this->sanitize_decimal_nullable($this->post_text('garden_area_min')),
            ],
        ];

        $validation_error = $this->validate_search_payload([
            'search_number' => 'X',
            'property_type' => $property_type,
            'location_text' => $location_text,
            'budget_from' => $budget_from,
            'budget_to' => $budget_to,
            'area_from' => $area_from,
            'area_to' => $area_to,
            'rooms_from' => $rooms_from,
            'rooms_to' => $rooms_to,
            'floor_from' => $floor_from,
            'floor_to' => $floor_to,
        ]);

        if ($validation_error !== '') {
            $validation_error = str_replace('Numer poszukiwania jest wymagany.', '', $validation_error);
            $validation_error = trim($validation_error);
            if ($validation_error !== '') {
                return new WP_Error('eocrm_search_validation', $validation_error);
            }
        }

        $criteria = [
            'building_finish' => $building_finish,
            'kitchen_type' => $kitchen_type,
            'attic' => $attic,
            'multi_level' => $multi_level,
            'exposure' => $exposure,
            'view' => $view,
            'layout' => $layout,
            'heating' => $heating,
            'water' => $water,
            'sewage' => $sewage,
            'gas' => $gas,
            'amenities' => $amenities,
            'amenity_counts' => $amenity_counts,
            'furnished_state' => $furnished_state,
            'equipment' => $equipment,
            'extra_areas' => $extra_areas,
        ];

        $updated = $wpdb->update(
            $this->tables['searches'],
            [
                'budget_from' => $budget_from,
                'budget_to' => $budget_to,
                'area_from' => $area_from,
                'area_to' => $area_to,
                'rooms_from' => $rooms_from,
                'rooms_to' => $rooms_to,
                'floor_from' => $floor_from,
                'floor_to' => $floor_to,
                'property_type' => $property_type,
                'location_text' => $location_text,
                'description' => $description,
                'criteria_json' => wp_json_encode($criteria),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $search_id]
        );

        if ($updated === false) {
            return new WP_Error('eocrm_search_update', 'Nie udalo sie zaktualizowac poszukiwania.');
        }

        return $search_id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validate_search_payload(array $payload): string
    {
        if ((string) ($payload['search_number'] ?? '') === '') {
            return 'Numer poszukiwania jest wymagany.';
        }

        if ((string) ($payload['property_type'] ?? '') === '') {
            return 'Rodzaj nieruchomosci jest wymagany.';
        }

        if ((string) ($payload['location_text'] ?? '') === '') {
            return 'Lokalizacja jest wymagana.';
        }

        $range_error = $this->validate_range_order($payload['budget_from'] ?? null, $payload['budget_to'] ?? null, 'Budzet');
        if ($range_error !== '') {
            return $range_error;
        }

        $range_error = $this->validate_range_order($payload['area_from'] ?? null, $payload['area_to'] ?? null, 'Metraz');
        if ($range_error !== '') {
            return $range_error;
        }

        $range_error = $this->validate_range_order($payload['rooms_from'] ?? null, $payload['rooms_to'] ?? null, 'Liczba pokoi');
        if ($range_error !== '') {
            return $range_error;
        }

        $range_error = $this->validate_range_order($payload['floor_from'] ?? null, $payload['floor_to'] ?? null, 'Pietro');
        if ($range_error !== '') {
            return $range_error;
        }

        return '';
    }

    /**
     * @param float|int|null $from
     * @param float|int|null $to
     */
    private function validate_range_order($from, $to, string $label): string
    {
        if (($from === null || $from === '') || ($to === null || $to === '')) {
            return '';
        }

        if ((float) $from > (float) $to) {
            return $label . ': wartosc "od" nie moze byc wieksza niz "do".';
        }

        return '';
    }

    private function search_number_exists(string $search_number): bool
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['searches']} WHERE search_number = %s LIMIT 1",
                $search_number
            )
        );

        return ! empty($exists);
    }

    private function get_agreement_transaction_type(int $agreement_id): string
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return '';
        }

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT transaction_type FROM {$this->tables['agreements']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            )
        );

        $value = is_string($value) ? strtoupper($value) : '';

        return in_array($value, ['SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM'], true) ? $value : '';
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

    private function get_search_owner_user_id(int $search_id): int
    {
        global $wpdb;

        if ($search_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT owner_user_id FROM {$this->tables['searches']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $search_id
            )
        );
    }

    private function can_access_owner_user_id(int $owner_user_id): bool
    {
        return EstateOfficeCRM_Access::can_access_owner_user($owner_user_id, $this->tables, get_current_user_id());
    }

    private function link_clients_from_agreement(int $agreement_id, int $search_id, string $now): bool
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT DISTINCT ac.client_id
            FROM {$this->tables['agreement_clients']} ac
            INNER JOIN {$this->tables['clients']} c ON c.id = ac.client_id
            WHERE ac.agreement_id = %d
            AND c.is_active = 1",
            $agreement_id
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above; agreement_id is sanitized.
        $client_ids = $wpdb->get_col($sql);
        if (! is_array($client_ids) || empty($client_ids)) {
            return true;
        }

        foreach ($client_ids as $client_id_raw) {
            $client_id = absint((string) $client_id_raw);
            if ($client_id <= 0) {
                continue;
            }

            $ok = $wpdb->insert(
                $this->tables['search_clients'],
                [
                    'search_id' => $search_id,
                    'client_id' => $client_id,
                    'created_at' => $now,
                ],
                ['%d', '%d', '%s']
            );

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    private function post_text(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field((string) wp_unslash($_POST[$key])) : '';
    }

    /**
     * @return array{number: string, settings: array<string,mixed>}
     */
    private function generate_next_available_search_number(): array
    {
        global $wpdb;

        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            'search',
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );

        $table = $this->tables['searches'];
        return EstateOfficeCRM_Numbering::generate_unique_number(
            $settings,
            function (string $candidate) use ($wpdb, $table): bool {
                if ($candidate === '') {
                    return true;
                }

                $exists = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE search_number = %s LIMIT 1",
                        $candidate
                    )
                );

                return $exists > 0;
            }
        );
    }

    private function peek_default_search_number(): string
    {
        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            'search',
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );

        return EstateOfficeCRM_Numbering::build_number_preview($settings, current_time('timestamp'));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function persist_numbering_settings(string $entity, array $settings): void
    {
        $updates = EstateOfficeCRM_Numbering::collect_setting_updates($entity, $settings);
        foreach ($updates as $setting_key => $setting_value) {
            $this->upsert_setting_value((string) $setting_key, (string) $setting_value);
        }
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

    /**
     * @return string[]
     */
    private function post_array(string $key): array
    {
        $value = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : [];
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item): string {
            return sanitize_text_field((string) $item);
        }, $value)));
    }

    /**
     * @param string[] $values
     * @param string[] $allowed
     * @return string[]
     */
    private function sanitize_multi(array $values, array $allowed): array
    {
        $allowed_lookup = array_fill_keys($allowed, true);
        $result = [];

        foreach ($values as $value) {
            if (isset($allowed_lookup[$value]) && ! in_array($value, $result, true)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function sanitize_property_type(string $value): string
    {
        $value = strtoupper($value);
        $allowed = ['MIESZKANIE', 'DOM', 'DZIALKA', 'LOKAL_HU'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @param string[] $values
     * @return string[]
     */
    private function sanitize_property_types(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            $type = $this->sanitize_property_type((string) $value);
            if ($type !== '' && ! in_array($type, $result, true)) {
                $result[] = $type;
            }
        }

        return $result;
    }

    private function sanitize_kitchen_type(string $value): string
    {
        if ($value === 'Z salonem') {
            $value = 'Polotwarta';
        }

        $allowed = ['', 'Aneks', 'Oddzielna', 'Polotwarta'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_building_finish(string $value): string
    {
        $allowed = [
            '',
            'Do remontu',
            'Do odswiezenia',
            'Dobry',
            'Bardzo dobry',
            'Deweloperski',
            'Wysoki standard',
        ];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_bool_nullable(string $value): ?bool
    {
        if ($value === '1') {
            return true;
        }

        if ($value === '0') {
            return false;
        }

        return null;
    }

    private function sanitize_furnished_state(string $value): string
    {
        $value = strtolower($value);
        $allowed = ['', 'tak', 'nie', 'czesciowe'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_heating(string $value): string
    {
        $value = strtolower($value);
        $allowed = ['', 'miejskie', 'gazowe', 'elektryczne', 'podlogowe', 'inne'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_water(string $value): string
    {
        $value = strtolower($value);
        $allowed = ['', 'miejska', 'studnia', 'inne'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_sewage(string $value): string
    {
        $value = strtolower($value);
        $allowed = ['', 'miejska', 'szambo', 'oczyszczalnia', 'inne'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_decimal_nullable(string $value, int $precision = 2): ?float
    {
        $value = str_replace(',', '.', $value);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, $precision);
    }

    private function sanitize_int_nullable(string $value): ?int
    {
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function sanitize_non_negative_int_nullable(string $value): ?int
    {
        $value_int = $this->sanitize_int_nullable($value);
        if (! is_int($value_int)) {
            return null;
        }

        return $value_int < 0 ? null : $value_int;
    }

    private function sanitize_floor_nullable(string $value): ?int
    {
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function redirect_back(string $notice, string $message = '', bool $stay_on_form = false): void
    {
        $args = [
            'crm' => 'searches',
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_form) {
            $args['mode'] = 'new-search';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('searches')));
        exit;
    }

    private function redirect_to_profile(int $search_id, string $notice, string $message = '', bool $stay_on_edit_form = false): void
    {
        $args = [
            'crm' => 'searches',
            'search_id' => max(0, $search_id),
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_edit_form) {
            $args['mode'] = 'edit-search';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('searches')));
        exit;
    }

    private function redirect_to_agreement_profile_after_create(int $agreement_id, string $notice, string $message): void
    {
        $args = [
            'crm' => 'agreements',
            'agreement_id' => $agreement_id,
            'crm_notice' => $notice,
            'crm_message' => $message,
        ];

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('agreements')));
        exit;
    }

    private function get_open_current_commission_stage_for_agreement(int $agreement_id): string
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return '';
        }

        $agreement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT current_stage, commission_split_enabled, commission_stages_json
                FROM {$this->tables['agreements']}
                WHERE id = %d
                AND is_active = 1
                LIMIT 1",
                $agreement_id
            ),
            ARRAY_A
        );

        if (! is_array($agreement) || empty($agreement['commission_split_enabled'])) {
            return '';
        }

        $stage_name = trim((string) ($agreement['current_stage'] ?? ''));
        if ($stage_name === '' || ! $this->commission_plan_contains_stage((string) ($agreement['commission_stages_json'] ?? ''), $stage_name)) {
            return '';
        }

        return $this->commission_stage_transaction_exists($agreement_id, $stage_name) ? '' : $stage_name;
    }

    private function commission_plan_contains_stage(string $json, string $stage_name): bool
    {
        $target = $this->normalize_commission_stage_key($stage_name);
        if ($target === '') {
            return false;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return false;
        }

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row_stage = trim(sanitize_text_field((string) ($row['stage_name'] ?? '')));
            if ($row_stage !== '' && $this->normalize_commission_stage_key($row_stage) === $target) {
                return true;
            }
        }

        return false;
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


