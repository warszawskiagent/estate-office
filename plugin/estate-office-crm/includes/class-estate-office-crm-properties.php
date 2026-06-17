<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Properties
{
    public const USERMETA_NEW_FORM_DRAFT = '_eocrm_property_form_draft_new';
    public const USERMETA_EDIT_FORM_DRAFT_PREFIX = '_eocrm_property_form_draft_edit_';

    /** @var array<string, string> */
    private array $tables;
    /** @var EstateOfficeCRM_Portal_Export|null */
    private $portal_export = null;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
        if (class_exists('EstateOfficeCRM_Portal_Export')) {
            $this->portal_export = new EstateOfficeCRM_Portal_Export();
        }
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'handle_frontend_create_property']);
        add_action('init', [$this, 'handle_frontend_update_property']);
        add_action('init', [$this, 'handle_frontend_update_property_flags']);
        add_action('init', [$this, 'handle_frontend_delete_property']);
    }

    public function handle_frontend_create_property(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'create_property') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_properties') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do dodawania nieruchomosci.');
        }

        check_admin_referer('eocrm_create_property', 'eocrm_nonce');

        $result = $this->create_property_from_request();
        if (is_wp_error($result)) {
            $this->store_property_form_draft('new', 0);
            $this->redirect_back('property_error', $result->get_error_message(), true);
        }

        $property_id = (int) $result;
        $this->clear_property_form_draft('new', 0);
        $agreement_id = absint((string) $this->post_text('agreement_id'));
        if ($agreement_id > 0) {
            $pending_commission_stage = $this->get_open_current_commission_stage_for_agreement($agreement_id);
            $message = $pending_commission_stage !== ''
                ? 'Nieruchomosc zostala dodana. W umowie jest zaplanowana prowizja czesciowa dla etapu: ' . $pending_commission_stage . '. Dodaj ja z karty umowy, gdy dane transakcji beda gotowe.'
                : 'Nieruchomosc zostala dodana. Wrociles do karty utworzonej umowy.';
            $this->redirect_to_agreement_profile_after_create($agreement_id, 'property_created', $message);
        }

        $this->redirect_to_profile($property_id, 'property_created', 'Nieruchomosc zostala dodana.', false);
    }

    public function handle_frontend_update_property(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_property') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_properties') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do edycji nieruchomosci.');
        }

        check_admin_referer('eocrm_update_property', 'eocrm_property_update_nonce');

        $result = $this->update_property_from_request();
        if (is_wp_error($result)) {
            $property_id = isset($_POST['property_id']) ? absint((string) wp_unslash($_POST['property_id'])) : 0;
            $this->store_property_form_draft('edit', $property_id);
            $this->redirect_to_profile($property_id, 'property_error', $result->get_error_message(), true);
        }

        $property_id = (int) $result;
        $this->clear_property_form_draft('edit', $property_id);
        $this->redirect_to_profile($property_id, 'property_updated', 'Nieruchomosc zostala zaktualizowana.', false);
    }

    public function handle_frontend_update_property_flags(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'update_property_flags') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_manage_properties') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do edycji znacznikow nieruchomosci.');
        }

        check_admin_referer('eocrm_update_property_flags', 'eocrm_property_flags_nonce');

        global $wpdb;

        $property_id = isset($_POST['property_id']) ? absint((string) wp_unslash($_POST['property_id'])) : 0;
        if ($property_id <= 0) {
            $this->redirect_back('property_error', 'Brak identyfikatora nieruchomosci.', false);
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, agreement_id, owner_user_id, transaction_type, price, price_currency, area, tags_json, is_new_offer, export_www, export_portals, new_offer_expires_at FROM {$this->tables['properties']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $property_id
            ),
            ARRAY_A
        );

        if (! is_array($existing)) {
            $this->redirect_to_profile($property_id, 'property_error', 'Nie znaleziono nieruchomosci.', false);
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = isset($existing['owner_user_id']) ? (int) $existing['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                $this->redirect_to_profile($property_id, 'property_error', 'Brak uprawnien do edycji tej nieruchomosci.', false);
            }
        }

        $tags = $this->sanitize_multi($this->post_array('tags'), [
            'nowa_oferta', 'wylacznosc', 'sprzedane', 'wynajete', 'nowa_cena', 'bez_prowizji', 'oferta_mls', 'premium'
        ]);

        $transaction_type = strtoupper((string) ($existing['transaction_type'] ?? ''));
        if ($transaction_type === 'SPRZEDAZ') {
            $tags = array_values(array_diff($tags, ['wynajete']));
        }
        if ($transaction_type === 'WYNAJEM') {
            $tags = array_values(array_diff($tags, ['sprzedane']));
        }

        $agreement_id = isset($existing['agreement_id']) ? (int) $existing['agreement_id'] : 0;
        if ($this->agreement_has_exclusive_flag($agreement_id) && ! in_array('wylacznosc', $tags, true)) {
            $tags[] = 'wylacznosc';
        }
        $tags = array_values(array_unique($tags));

        $is_new_offer = in_array('nowa_oferta', $tags, true);
        $is_exclusive = in_array('wylacznosc', $tags, true);
        $is_sold = in_array('sprzedane', $tags, true);
        $is_rented = in_array('wynajete', $tags, true);
        $is_new_price = in_array('nowa_cena', $tags, true);
        $no_commission = in_array('bez_prowizji', $tags, true);
        $is_mls_offer = in_array('oferta_mls', $tags, true);
        $is_premium = in_array('premium', $tags, true);

        $export_www = $this->post_checkbox('export_www');
        $export_portals = $this->post_checkbox('export_portals');
        $was_export_portals = isset($existing['export_portals']) ? (int) $existing['export_portals'] === 1 : false;
        $price = $this->sanitize_decimal($this->post_text('quick_price'));
        if ($price <= 0) {
            $this->redirect_to_profile($property_id, 'property_error', 'Cena musi byc wieksza od zera.', false);
        }

        $price_currency = $this->sanitize_price_currency($this->post_text('quick_price_currency'));
        if ($price_currency === '') {
            $price_currency = $this->sanitize_price_currency((string) ($existing['price_currency'] ?? ''));
        }
        if ($price_currency === '') {
            $price_currency = 'PLN';
        }

        $area = isset($existing['area']) && is_numeric((string) $existing['area']) ? (float) $existing['area'] : null;
        $price_per_m2 = $this->calculate_price_per_m2($price, $area);

        $new_offer_expires_at = $this->resolve_new_offer_expires_at(
            $is_new_offer,
            (string) ($existing['new_offer_expires_at'] ?? ''),
            ! empty($existing['is_new_offer'])
        );

        $updated = $wpdb->update(
            $this->tables['properties'],
            [
                'price' => $price,
                'price_currency' => $price_currency,
                'price_per_m2' => $price_per_m2,
                'tags_json' => wp_json_encode($tags),
                'is_new_offer' => $is_new_offer ? 1 : 0,
                'is_exclusive' => $is_exclusive ? 1 : 0,
                'is_sold' => $is_sold ? 1 : 0,
                'is_rented' => $is_rented ? 1 : 0,
                'is_new_price' => $is_new_price ? 1 : 0,
                'no_commission' => $no_commission ? 1 : 0,
                'is_mls_offer' => $is_mls_offer ? 1 : 0,
                'is_premium' => $is_premium ? 1 : 0,
                'export_www' => $export_www ? 1 : 0,
                'export_portals' => $export_portals ? 1 : 0,
                'new_offer_expires_at' => $new_offer_expires_at,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $property_id]
        );

        if ($updated === false) {
            $this->redirect_to_profile($property_id, 'property_error', 'Nie udalo sie zaktualizowac znacznikow.', false);
        }

        $this->sync_public_offer_page($property_id, $export_www);
        if ($export_portals) {
            $this->queue_portal_export($property_id, $was_export_portals ? 'update' : 'insert');
        } elseif ($was_export_portals) {
            $this->queue_portal_export($property_id, 'delete');
        }

        $this->redirect_to_profile($property_id, 'property_updated', 'Cena, znaczniki i eksport nieruchomosci zostaly zaktualizowane.', false);
    }

    public function handle_frontend_delete_property(): void
    {
        if (is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action !== 'delete_property') {
            return;
        }

        if (! is_user_logged_in()) {
            wp_die('Musisz byc zalogowany.');
        }

        if (! current_user_can('eocrm_delete_records') && ! current_user_can('manage_options')) {
            wp_die('Brak uprawnien do usuwania nieruchomosci.');
        }

        check_admin_referer('eocrm_delete_property', 'eocrm_property_delete_nonce');

        $property_id = isset($_POST['property_id']) ? absint((string) wp_unslash($_POST['property_id'])) : 0;
        if ($property_id <= 0) {
            $this->redirect_back('property_error', 'Brak identyfikatora nieruchomosci.', false);
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = $this->get_property_owner_user_id($property_id);
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                $this->redirect_back('property_error', 'Brak uprawnien do usuniecia tej nieruchomosci.', false);
            }
        }
        $should_export_to_portal = $this->is_property_marked_for_portal_export($property_id);

        global $wpdb;
        $updated = $wpdb->update(
            $this->tables['properties'],
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $property_id,
                'is_active' => 1,
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $this->redirect_to_profile($property_id, 'property_error', 'Nie udalo sie usunac nieruchomosci.', false);
        }

        $this->sync_public_offer_page($property_id, false);
        if ($should_export_to_portal) {
            $this->queue_portal_export($property_id, 'delete');
        }
        $this->redirect_back('property_deleted', 'Nieruchomosc zostala usunieta.', false);
    }

    /**
     * @return int|WP_Error
     */
    private function create_property_from_request()
    {
        global $wpdb;

        $agreement_id = absint((string) ($this->post_text('agreement_id')));
        $agreement_transaction = $this->get_agreement_transaction_type($agreement_id);
        if ($agreement_transaction === '') {
            return new WP_Error('eocrm_property_agreement', 'Wybierz poprawna, aktywna umowe.');
        }

        if (! in_array($agreement_transaction, ['SPRZEDAZ', 'WYNAJEM'], true)) {
            return new WP_Error('eocrm_property_transaction', 'Do nieruchomosci wymagana jest umowa typu SPRZEDAZ lub WYNAJEM.');
        }

        if (! current_user_can('manage_options') && ! $this->agreement_is_owned_by_current_user($agreement_id)) {
            return new WP_Error('eocrm_property_agreement_owner', 'Mozesz dodawac nieruchomosci tylko do swoich umow.');
        }
        $agreement_is_exclusive = $this->agreement_has_exclusive_flag($agreement_id);

        $offer_number = $this->post_text('offer_number');
        $next_offer_numbering_settings = null;
        $default_offer_number = $this->peek_default_offer_number();
        if ($offer_number === '' || $offer_number === $default_offer_number) {
            $generated_offer = $this->generate_next_available_offer_number();
            $offer_number = $generated_offer['number'];
            $next_offer_numbering_settings = $generated_offer['settings'];
        }

        $property_type = $this->sanitize_property_type($this->post_text('property_type'));
        $house_type = $this->sanitize_house_type($this->post_text('house_type'));

        $street = $this->post_text('street');
        $building_no = $this->post_text('building_no');
        $apartment_no = $this->post_text('apartment_no');
        $postal_code = $this->post_text('postal_code');
        $city = $this->post_text('city');
        $district = $this->post_text('district');
        $county = $this->post_text('county');
        $gmina = $this->post_text('gmina');
        $precinct = $this->post_text('precinct');
        $plot_number = $this->post_text('plot_number');
        

        if (! in_array($property_type, ['DOM', 'DZIALKA'], true)) {
            $gmina = '';
            $precinct = '';
            $plot_number = '';
        }

        $land_registry_no = $this->post_text('land_registry_no');
        $no_land_registry = $this->post_checkbox('no_land_registry');
        $legal_status = $this->sanitize_legal_status($this->post_text('legal_status'));

        $map_pin_address = $this->post_text('map_pin_address');
        $latitude = $this->sanitize_decimal_nullable($this->post_text('latitude'), 7);
        $longitude = $this->sanitize_decimal_nullable($this->post_text('longitude'), 7);

        $price = $this->sanitize_decimal($this->post_text('price'));
        $price_currency = $this->sanitize_price_currency($this->post_text('price_currency'));
        if ($price_currency === '') {
            $price_currency = 'PLN';
        }
        $admin_rent = $this->sanitize_decimal_nullable($this->post_text('admin_rent'));
        $area = $this->sanitize_decimal_nullable($this->post_text('area'));
        $plot_area = $this->sanitize_decimal_nullable($this->post_text('plot_area'));
        $price_per_m2 = $this->calculate_price_per_m2($price, $area);

        $year_built = $this->sanitize_int_nullable($this->post_text('year_built'));
        $floor_no = $this->sanitize_int_nullable($this->post_text('floor_no'));
        $floors_total = $this->sanitize_int_nullable($this->post_text('floors_total'));
        $rooms = $this->sanitize_int_nullable($this->post_text('rooms'));
        $bedrooms = $this->sanitize_int_nullable($this->post_text('bedrooms'));
        $bathrooms = $this->sanitize_int_nullable($this->post_text('bathrooms'));
        $toilets = $this->sanitize_int_nullable($this->post_text('toilets'));


        if ($property_type === 'DZIALKA') {
            $plot_area = $area;
            $year_built = null;
        } elseif ($property_type !== 'DOM') {
            $plot_area = null;
        }
        $plot_shape = $this->sanitize_plot_shape($this->post_text('plot_shape'));
        $plot_length = $this->sanitize_decimal_nullable($this->post_text('plot_length'));
        $plot_width = $this->sanitize_decimal_nullable($this->post_text('plot_width'));
        $plot_side_c = $this->sanitize_decimal_nullable($this->post_text('plot_side_c'));
        $plot_dimensions_text = $this->post_text('plot_dimensions_text');

        if (! in_array($property_type, ['DOM', 'DZIALKA'], true)) {
            $plot_shape = '';
            $plot_length = null;
            $plot_width = null;
            $plot_side_c = null;
            $plot_dimensions_text = '';
        }

        [$plot_shape, $plot_length, $plot_width, $plot_dimensions_text] = $this->normalize_plot_dimensions(
            $plot_shape,
            $plot_length,
            $plot_width,
            $plot_side_c,
            $plot_dimensions_text
        );
        if (! is_float($plot_side_c) && ! is_int($plot_side_c)) {
            $plot_side_c = $this->extract_plot_side_c_from_dimensions_text($plot_dimensions_text);
        }

        $description = isset($_POST['description']) ? wp_kses_post((string) wp_unslash($_POST['description'])) : '';

        $building_finish = $this->sanitize_building_finish($this->post_text('building_finish'));
        $kitchen_type = $this->sanitize_kitchen_type($this->post_text('kitchen_type'));
        $attic = $this->sanitize_bool_nullable($this->post_text('attic'));
        $multi_level = $this->sanitize_bool_nullable($this->post_text('multi_level'));
        $furnished_state = $this->sanitize_furnished_state($this->post_text('furnished_state'));

        $exposure = $this->sanitize_multi($this->post_array('exposure'), ['polnoc', 'poludnie', 'wschod', 'zachod']);
        $view = $this->sanitize_multi($this->post_array('view'), ['miasto', 'zielec', 'park', 'podworko', 'ulica', 'panorama']);
        $layout = $this->sanitize_multi($this->post_array('layout'), ['oddzielne_pokoje', 'salon_z_aneksem', 'dwustronne', 'narozne']);

        $has_parking = $this->post_checkbox('has_parking');
        $parking_types = $this->sanitize_multi($this->post_array('parking_types'), ['najemne', 'podziemne', 'garaz']);
        $parking_counts = [
            'najemne' => $this->sanitize_non_negative_int_nullable($this->post_text('parking_rental_count')),
            'podziemne' => $this->sanitize_non_negative_int_nullable($this->post_text('parking_underground_count')),
            'garaz' => $this->sanitize_non_negative_int_nullable($this->post_text('parking_garage_count')),
        ];

        if (! $has_parking) {
            $parking_types = [];
            $parking_counts = [
                'najemne' => null,
                'podziemne' => null,
                'garaz' => null,
            ];
        }

        $heating = $this->sanitize_heating($this->post_text('heating'));
        $water = $this->sanitize_water($this->post_text('water'));
        $sewage = $this->sanitize_sewage($this->post_text('sewage'));
        $gas = $this->post_checkbox('gas');
        $electricity = $this->post_checkbox('electricity');

        $amenities = $this->sanitize_multi($this->post_array('amenities'), [
            'winda', 'klimatyzacja', 'monitoring', 'recepcja', 'teren_zamkniety', 'domofon'
        ]);

        $equipment = $this->sanitize_multi($this->post_array('equipment'), [
            'pralka', 'zmywarka', 'lodowka', 'kuchenka', 'piekarnik', 'telewizor', 'mikrofala'
        ]);

        $extra_areas = [
            'balcony' => [
                'has' => $this->post_checkbox('balcony_has'),
                'count' => $this->sanitize_int_nullable($this->post_text('balcony_count')),
                'area' => $this->sanitize_decimal_nullable($this->post_text('balcony_area')),
            ],
            'terrace' => [
                'has' => $this->post_checkbox('terrace_has'),
                'count' => $this->sanitize_int_nullable($this->post_text('terrace_count')),
                'area' => $this->sanitize_decimal_nullable($this->post_text('terrace_area')),
            ],
            'basement' => [
                'has' => $this->post_checkbox('basement_has'),
                'area' => $this->sanitize_decimal_nullable($this->post_text('basement_area')),
            ],
            'storage' => [
                'has' => $this->post_checkbox('storage_has'),
                'area' => $this->sanitize_decimal_nullable($this->post_text('storage_area')),
            ],
            'garden' => [
                'has' => $this->post_checkbox('garden_has'),
                'area' => $this->sanitize_decimal_nullable($this->post_text('garden_area')),
            ],
        ];

        if ($property_type === 'DZIALKA') {
            $admin_rent = null;
            $extra_areas = [
                'balcony' => ['has' => false, 'count' => null, 'area' => null],
                'terrace' => ['has' => false, 'count' => null, 'area' => null],
                'basement' => ['has' => false, 'area' => null],
                'storage' => ['has' => false, 'area' => null],
                'garden' => ['has' => false, 'area' => null],
            ];
        }

        $tags = $this->sanitize_multi($this->post_array('tags'), [
            'nowa_oferta', 'wylacznosc', 'sprzedane', 'wynajete', 'nowa_cena', 'bez_prowizji', 'oferta_mls', 'premium'
        ]);

        if ($agreement_transaction === 'SPRZEDAZ') {
            $tags = array_values(array_diff($tags, ['wynajete']));
        }
        if ($agreement_transaction === 'WYNAJEM') {
            $tags = array_values(array_diff($tags, ['sprzedane']));
        }

        if ($agreement_is_exclusive && ! in_array('wylacznosc', $tags, true)) {
            $tags[] = 'wylacznosc';
        }
        $tags = array_values(array_unique($tags));

        $is_new_offer = in_array('nowa_oferta', $tags, true);
        $is_exclusive = in_array('wylacznosc', $tags, true);
        $is_sold = in_array('sprzedane', $tags, true);
        $is_rented = in_array('wynajete', $tags, true);
        $is_new_price = in_array('nowa_cena', $tags, true);
        $no_commission = in_array('bez_prowizji', $tags, true);
        $is_mls_offer = in_array('oferta_mls', $tags, true);
        $is_premium = in_array('premium', $tags, true);

        $export_www = $this->post_checkbox('export_www');
        $export_portals = $this->post_checkbox('export_portals');

        $gallery_attachment_ids = $this->parse_attachment_ids(isset($_POST['gallery_attachment_ids']) ? (string) wp_unslash($_POST['gallery_attachment_ids']) : '');

        $floor_plan_items_raw = isset($_POST['floor_plan_items_json']) ? (string) wp_unslash($_POST['floor_plan_items_json']) : '';
        $floor_plan_items = $this->parse_floor_plan_items_json($floor_plan_items_raw);
        if (is_wp_error($floor_plan_items)) {
            return $floor_plan_items;
        }

        $floor_2d_raw = absint((string) $this->post_text('floor_2d_attachment_id'));
        $floor_3d_raw = absint((string) $this->post_text('floor_3d_attachment_id'));
        $floor_2d_attachment_id = $this->sanitize_image_attachment_id($floor_2d_raw);
        $floor_3d_attachment_id = $this->sanitize_image_attachment_id($floor_3d_raw);

        if ($floor_2d_raw > 0 && $floor_2d_attachment_id <= 0) {
            return new WP_Error('eocrm_property_floor', 'Rzut 2D musi byc obrazem z biblioteki mediow.');
        }

        if ($floor_3d_raw > 0 && $floor_3d_attachment_id <= 0) {
            return new WP_Error('eocrm_property_floor', 'Rzut 3D musi byc obrazem z biblioteki mediow.');
        }

        if (empty($floor_plan_items)) {
            $legacy_floor_plan_items = [];
            if ($floor_2d_attachment_id > 0) {
                $legacy_floor_plan_items[] = [
                    'attachment_id' => $floor_2d_attachment_id,
                    'label' => 'Poziom 1',
                    'position' => 0,
                ];
            }
            if ($floor_3d_attachment_id > 0) {
                $legacy_floor_plan_items[] = [
                    'attachment_id' => $floor_3d_attachment_id,
                    'label' => 'Poziom 2',
                    'position' => count($legacy_floor_plan_items),
                ];
            }
            $floor_plan_items = $legacy_floor_plan_items;
        }

        $floor_2d_attachment_id = isset($floor_plan_items[0]['attachment_id']) ? (int) $floor_plan_items[0]['attachment_id'] : 0;
        $floor_3d_attachment_id = isset($floor_plan_items[1]['attachment_id']) ? (int) $floor_plan_items[1]['attachment_id'] : 0;

        $video_link = isset($_POST['video_link']) ? esc_url_raw((string) wp_unslash($_POST['video_link'])) : '';
        $virtual_tour_link = isset($_POST['virtual_tour_link']) ? esc_url_raw((string) wp_unslash($_POST['virtual_tour_link'])) : '';
        $property_custom_field_definitions = $this->load_property_additional_field_definitions();
        $property_custom_field_values = $this->collect_property_custom_field_values($property_custom_field_definitions);

        $validation_error = $this->validate_property_payload([
            'offer_number' => $offer_number,
            'property_type' => $property_type,
            'house_type' => $house_type,
            'street' => $street,
            'building_no' => $building_no,
            'postal_code' => $postal_code,
            'city' => $city,
            'land_registry_no' => $land_registry_no,
            'no_land_registry' => $no_land_registry,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'price' => $price,
            'area' => $area,
            'plot_area' => $plot_area,
            'plot_shape' => $plot_shape,
            'plot_length' => $plot_length,
            'plot_width' => $plot_width,
            'plot_dimensions_text' => $plot_dimensions_text,
            'plot_side_c' => $plot_side_c,
        ]);

        if ($validation_error !== '') {
            return new WP_Error('eocrm_property_validation', $validation_error);
        }

        if ($this->offer_number_exists($offer_number)) {
            return new WP_Error('eocrm_property_duplicate', 'Numer oferty juz istnieje.');
        }

        $now = current_time('mysql');
        $owner_user_id = get_current_user_id();

        $parking_json = [
            'has_parking' => $has_parking,
            'types' => $parking_types,
            'counts' => [
                'najemne' => in_array('najemne', $parking_types, true) ? $parking_counts['najemne'] : null,
                'podziemne' => in_array('podziemne', $parking_types, true) ? $parking_counts['podziemne'] : null,
                'garaz' => in_array('garaz', $parking_types, true) ? $parking_counts['garaz'] : null,
            ],
        ];

        $media_json = [
            'map_pin_address' => $map_pin_address,
            'video_link' => $video_link,
            'virtual_tour_link' => $virtual_tour_link,
            'floor_2d_attachment_id' => $floor_2d_attachment_id,
            'floor_3d_attachment_id' => $floor_3d_attachment_id,
            'floor_plans' => $floor_plan_items,
            'heating' => $heating,
            'water' => $water,
            'sewage' => $sewage,
            'gas' => $gas,
            'electricity' => $electricity,
            'attic' => $attic,
            'multi_level' => $multi_level,
            'furnished_state' => $furnished_state,
        ];

        $new_offer_expires_at = $this->resolve_new_offer_expires_at($is_new_offer);

        $insert_data = [
            'agreement_id' => $agreement_id,
            'offer_number' => $offer_number,
            'transaction_type' => $agreement_transaction,
            'property_type' => $property_type,
            'house_type' => $house_type,
            'street' => $street,
            'building_no' => $building_no,
            'apartment_no' => $apartment_no,
            'postal_code' => $postal_code,
            'city' => $city,
            'district' => $district,
            'county' => $county,
            'gmina' => $gmina,
            'precinct' => $precinct,
            'plot_number' => $plot_number,
            'land_registry_no' => $no_land_registry ? '' : $land_registry_no,
            'no_land_registry' => $no_land_registry ? 1 : 0,
            'legal_status' => $legal_status,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'price' => $price,
            'price_currency' => $price_currency,
            'admin_rent' => $admin_rent,
            'area' => $area,
            'plot_area' => $plot_area,
            'price_per_m2' => $price_per_m2,
            'year_built' => $year_built,
            'floor_no' => $floor_no,
            'floors_total' => $floors_total,
            'rooms' => $rooms,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'toilets' => $toilets,
            'plot_shape' => $plot_shape,
            'plot_length' => $plot_length,
            'plot_width' => $plot_width,
            'plot_dimensions_text' => $plot_dimensions_text,
            'description' => $description,
            'building_finish' => $building_finish,
            'exposure_json' => wp_json_encode($exposure),
            'view_json' => wp_json_encode($view),
            'layout_json' => wp_json_encode($layout),
            'kitchen_type' => $kitchen_type,
            'parking_json' => wp_json_encode($parking_json),
            'media_json' => wp_json_encode($media_json),
            'amenities_json' => wp_json_encode($amenities),
            'equipment_json' => wp_json_encode($equipment),
            'extra_areas_json' => wp_json_encode($extra_areas),
            'custom_fields_json' => wp_json_encode($property_custom_field_values),
            'tags_json' => wp_json_encode($tags),
            'is_new_offer' => $is_new_offer ? 1 : 0,
            'is_exclusive' => $is_exclusive ? 1 : 0,
            'is_sold' => $is_sold ? 1 : 0,
            'is_rented' => $is_rented ? 1 : 0,
            'is_new_price' => $is_new_price ? 1 : 0,
            'no_commission' => $no_commission ? 1 : 0,
            'is_mls_offer' => $is_mls_offer ? 1 : 0,
            'is_premium' => $is_premium ? 1 : 0,
            'export_www' => $export_www ? 1 : 0,
            'export_portals' => $export_portals ? 1 : 0,
            'new_offer_expires_at' => $new_offer_expires_at,
            'owner_user_id' => $owner_user_id,
            'created_by' => $owner_user_id,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insert_ok = $wpdb->insert($this->tables['properties'], $insert_data);
        if (! $insert_ok) {
            return new WP_Error('eocrm_property_insert', 'Nie udalo sie zapisac nieruchomosci.');
        }

        $property_id = (int) $wpdb->insert_id;
        if ($property_id <= 0) {
            return new WP_Error('eocrm_property_insert', 'Brak identyfikatora nowej nieruchomosci.');
        }

        if (! $this->insert_gallery_media($property_id, $gallery_attachment_ids, $now)) {
            $wpdb->delete($this->tables['properties'], ['id' => $property_id], ['%d']);
            return new WP_Error('eocrm_property_gallery', 'Nie udalo sie zapisac galerii zdjec.');
        }

        if (! $this->insert_floor_plan_media($property_id, $floor_plan_items, $now)) {
            $wpdb->delete($this->tables['property_media'], ['property_id' => $property_id], ['%d']);
            $wpdb->delete($this->tables['properties'], ['id' => $property_id], ['%d']);
            return new WP_Error('eocrm_property_floor', 'Nie udalo sie zapisac rzutow nieruchomosci.');
        }

        if (is_array($next_offer_numbering_settings)) {
            $this->persist_numbering_settings('offer', $next_offer_numbering_settings);
        }

        $this->sync_public_offer_page($property_id, $export_www);
        if ($export_portals) {
            $this->queue_portal_export($property_id, 'insert');
        }
        return $property_id;
    }

    /**
     * @return int|WP_Error
     */
    private function update_property_from_request()
    {
        global $wpdb;

        $property_id = isset($_POST['property_id']) ? absint((string) wp_unslash($_POST['property_id'])) : 0;
        if ($property_id <= 0) {
            return new WP_Error('eocrm_property_update', 'Brak identyfikatora nieruchomosci.');
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, agreement_id, transaction_type, property_type, house_type, price_currency, media_json, custom_fields_json, owner_user_id, is_new_offer, export_portals, new_offer_expires_at
                FROM {$this->tables['properties']}
                WHERE id = %d AND is_active = 1
                LIMIT 1",
                $property_id
            ),
            ARRAY_A
        );

        if (! is_array($existing)) {
            return new WP_Error('eocrm_property_update', 'Nieruchomosc nie istnieje lub jest nieaktywna.');
        }

        if (! current_user_can('manage_options')) {
            $owner_user_id = isset($existing['owner_user_id']) ? (int) $existing['owner_user_id'] : 0;
            if (! $this->can_access_owner_user_id($owner_user_id)) {
                return new WP_Error('eocrm_property_update', 'Brak uprawnien do edycji tej nieruchomosci.');
            }
        }

        $agreement_id = isset($existing['agreement_id']) ? (int) $existing['agreement_id'] : 0;
        $agreement_is_exclusive = $this->agreement_has_exclusive_flag($agreement_id);

        $owner_user_id_for_update = null;
        if (current_user_can('manage_options')) {
            $owner_user_id_for_update = isset($_POST['owner_user_id']) ? absint((string) wp_unslash($_POST['owner_user_id'])) : 0;
            if ($owner_user_id_for_update <= 0) {
                return new WP_Error('eocrm_property_update', 'Wybierz opiekuna nieruchomosci.');
            }

            if (! $this->is_valid_owner_user($owner_user_id_for_update)) {
                return new WP_Error('eocrm_property_update', 'Wybrany opiekun jest nieprawidlowy.');
            }
        }

        $property_type = isset($existing['property_type']) ? (string) $existing['property_type'] : '';
        $transaction_type = isset($existing['transaction_type']) ? (string) $existing['transaction_type'] : '';
        $house_type = $this->sanitize_house_type($this->post_text('house_type'));
        if ($house_type === '') {
            $house_type = isset($existing['house_type']) ? (string) $existing['house_type'] : '';
        }

        $street = $this->post_text('street');
        $building_no = $this->post_text('building_no');
        $apartment_no = $this->post_text('apartment_no');
        $postal_code = $this->post_text('postal_code');
        $city = $this->post_text('city');
        $district = $this->post_text('district');
        $county = $this->post_text('county');
        $gmina = $this->post_text('gmina');
        $precinct = $this->post_text('precinct');
        $plot_number = $this->post_text('plot_number');

        if (! in_array($property_type, ['DOM', 'DZIALKA'], true)) {
            $gmina = '';
            $precinct = '';
            $plot_number = '';
        }

        $land_registry_no = $this->post_text('land_registry_no');
        $no_land_registry = $this->post_checkbox('no_land_registry');
        $legal_status = $this->sanitize_legal_status($this->post_text('legal_status'));

        $latitude = $this->sanitize_decimal_nullable($this->post_text('latitude'), 7);
        $longitude = $this->sanitize_decimal_nullable($this->post_text('longitude'), 7);
        $map_pin_address = $this->post_text('map_pin_address');

        $price = $this->sanitize_decimal($this->post_text('price'));
        $price_currency = $this->sanitize_price_currency($this->post_text('price_currency'));
        if ($price_currency === '') {
            $price_currency = $this->sanitize_price_currency((string) ($existing['price_currency'] ?? ''));
        }
        if ($price_currency === '') {
            $price_currency = 'PLN';
        }
        $admin_rent = $this->sanitize_decimal_nullable($this->post_text('admin_rent'));
        $area = $this->sanitize_decimal_nullable($this->post_text('area'));
        $plot_area = $this->sanitize_decimal_nullable($this->post_text('plot_area'));
        $price_per_m2 = $this->calculate_price_per_m2($price, $area);

        $year_built = $this->sanitize_int_nullable($this->post_text('year_built'));
        $floor_no = $this->sanitize_int_nullable($this->post_text('floor_no'));
        $floors_total = $this->sanitize_int_nullable($this->post_text('floors_total'));
        $rooms = $this->sanitize_int_nullable($this->post_text('rooms'));
        $bedrooms = $this->sanitize_int_nullable($this->post_text('bedrooms'));
        $bathrooms = $this->sanitize_int_nullable($this->post_text('bathrooms'));
        $toilets = $this->sanitize_int_nullable($this->post_text('toilets'));


        if ($property_type === 'DZIALKA') {
            $plot_area = $area;
        } elseif ($property_type !== 'DOM') {
            $plot_area = null;
        }

        $plot_shape = $this->sanitize_plot_shape($this->post_text('plot_shape'));
        $plot_length = $this->sanitize_decimal_nullable($this->post_text('plot_length'));
        $plot_width = $this->sanitize_decimal_nullable($this->post_text('plot_width'));
        $plot_side_c = $this->sanitize_decimal_nullable($this->post_text('plot_side_c'));
        $plot_dimensions_text = $this->post_text('plot_dimensions_text');

        if (! in_array($property_type, ['DOM', 'DZIALKA'], true)) {
            $plot_shape = '';
            $plot_length = null;
            $plot_width = null;
            $plot_side_c = null;
            $plot_dimensions_text = '';
        }

        [$plot_shape, $plot_length, $plot_width, $plot_dimensions_text] = $this->normalize_plot_dimensions(
            $plot_shape,
            $plot_length,
            $plot_width,
            $plot_side_c,
            $plot_dimensions_text
        );
        if (! is_float($plot_side_c) && ! is_int($plot_side_c)) {
            $plot_side_c = $this->extract_plot_side_c_from_dimensions_text($plot_dimensions_text);
        }

        $building_finish = $this->sanitize_building_finish($this->post_text('building_finish'));
        $kitchen_type = $this->sanitize_kitchen_type($this->post_text('kitchen_type'));
        $attic = $this->sanitize_bool_nullable($this->post_text('attic'));
        $multi_level = $this->sanitize_bool_nullable($this->post_text('multi_level'));
        $furnished_state = $this->sanitize_furnished_state($this->post_text('furnished_state'));

        $exposure = $this->sanitize_multi($this->post_array('exposure'), ['polnoc', 'poludnie', 'wschod', 'zachod']);
        $view = $this->sanitize_multi($this->post_array('view'), ['miasto', 'zielec', 'park', 'podworko', 'ulica', 'panorama']);
        $layout = $this->sanitize_multi($this->post_array('layout'), ['oddzielne_pokoje', 'salon_z_aneksem', 'dwustronne', 'narozne']);

        $has_parking = $this->post_checkbox('has_parking');
        $parking_types = $this->sanitize_multi($this->post_array('parking_types'), ['najemne', 'podziemne', 'garaz']);
        $parking_counts = [
            'najemne' => $this->sanitize_non_negative_int_nullable($this->post_text('parking_rental_count')),
            'podziemne' => $this->sanitize_non_negative_int_nullable($this->post_text('parking_underground_count')),
            'garaz' => $this->sanitize_non_negative_int_nullable($this->post_text('parking_garage_count')),
        ];

        if (! $has_parking) {
            $parking_types = [];
            $parking_counts = [
                'najemne' => null,
                'podziemne' => null,
                'garaz' => null,
            ];
        }

        $heating = $this->sanitize_heating($this->post_text('heating'));
        $water = $this->sanitize_water($this->post_text('water'));
        $sewage = $this->sanitize_sewage($this->post_text('sewage'));
        $gas = $this->post_checkbox('gas');
        $electricity = $this->post_checkbox('electricity');

        $amenities = $this->sanitize_multi($this->post_array('amenities'), [
            'winda', 'klimatyzacja', 'monitoring', 'recepcja', 'teren_zamkniety', 'domofon'
        ]);

        $equipment = $this->sanitize_multi($this->post_array('equipment'), [
            'pralka', 'zmywarka', 'lodowka', 'kuchenka', 'piekarnik', 'telewizor', 'mikrofala'
        ]);

        $extra_areas = [
            'balcony' => [
                'has' => $this->post_checkbox('balcony_has'),
                'count' => $this->sanitize_int_nullable($this->post_text('balcony_count')),
                'area' => $this->sanitize_decimal_nullable($this->post_text('balcony_area')),
            ],
            'terrace' => [
                'has' => $this->post_checkbox('terrace_has'),
                'count' => $this->sanitize_int_nullable($this->post_text('terrace_count')),
                'area' => $this->sanitize_decimal_nullable($this->post_text('terrace_area')),
            ],
            'basement' => [
                'has' => $this->post_checkbox('basement_has'),
                'area' => $this->sanitize_decimal_nullable($this->post_text('basement_area')),
            ],
            'storage' => [
                'has' => $this->post_checkbox('storage_has'),
                'area' => $this->sanitize_decimal_nullable($this->post_text('storage_area')),
            ],
            'garden' => [
                'has' => $this->post_checkbox('garden_has'),
                'area' => $this->sanitize_decimal_nullable($this->post_text('garden_area')),
            ],
        ];

        if ($property_type === 'DZIALKA') {
            $admin_rent = null;
            $year_built = null;
            $building_finish = '';
            $kitchen_type = '';
            $attic = null;
            $multi_level = null;
            $furnished_state = '';
            $exposure = [];
            $view = [];
            $layout = [];
            $has_parking = false;
            $parking_types = [];
            $parking_counts = [
                'najemne' => null,
                'podziemne' => null,
                'garaz' => null,
            ];
            $heating = '';
            $water = '';
            $sewage = '';
            $amenities = [];
            $equipment = [];
            $extra_areas = [
                'balcony' => ['has' => false, 'count' => null, 'area' => null],
                'terrace' => ['has' => false, 'count' => null, 'area' => null],
                'basement' => ['has' => false, 'area' => null],
                'storage' => ['has' => false, 'area' => null],
                'garden' => ['has' => false, 'area' => null],
            ];
        }

        $description = isset($_POST['description']) ? wp_kses_post((string) wp_unslash($_POST['description'])) : '';

        $tags = $this->sanitize_multi($this->post_array('tags'), [
            'nowa_oferta', 'wylacznosc', 'sprzedane', 'wynajete', 'nowa_cena', 'bez_prowizji', 'oferta_mls', 'premium'
        ]);

        if ($transaction_type === 'SPRZEDAZ') {
            $tags = array_values(array_diff($tags, ['wynajete']));
        }
        if ($transaction_type === 'WYNAJEM') {
            $tags = array_values(array_diff($tags, ['sprzedane']));
        }

        if ($agreement_is_exclusive && ! in_array('wylacznosc', $tags, true)) {
            $tags[] = 'wylacznosc';
        }
        $tags = array_values(array_unique($tags));

        $is_new_offer = in_array('nowa_oferta', $tags, true);
        $is_exclusive = in_array('wylacznosc', $tags, true);
        $is_sold = in_array('sprzedane', $tags, true);
        $is_rented = in_array('wynajete', $tags, true);
        $is_new_price = in_array('nowa_cena', $tags, true);
        $no_commission = in_array('bez_prowizji', $tags, true);
        $is_mls_offer = in_array('oferta_mls', $tags, true);
        $is_premium = in_array('premium', $tags, true);

        $export_www = $this->post_checkbox('export_www');
        $export_portals = $this->post_checkbox('export_portals');

        $video_link = isset($_POST['video_link']) ? esc_url_raw((string) wp_unslash($_POST['video_link'])) : '';
        $virtual_tour_link = isset($_POST['virtual_tour_link']) ? esc_url_raw((string) wp_unslash($_POST['virtual_tour_link'])) : '';
        $property_custom_field_definitions = $this->load_property_additional_field_definitions();
        $property_custom_field_values = $this->collect_property_custom_field_values($property_custom_field_definitions);
        $existing_custom_field_values = json_decode((string) ($existing['custom_fields_json'] ?? ''), true);
        if (! is_array($existing_custom_field_values)) {
            $existing_custom_field_values = [];
        }
        $property_custom_field_values = $this->merge_property_custom_field_values(
            $existing_custom_field_values,
            $property_custom_field_values,
            $property_custom_field_definitions
        );
        $gallery_attachment_ids = $this->parse_attachment_ids(isset($_POST['gallery_attachment_ids']) ? (string) wp_unslash($_POST['gallery_attachment_ids']) : '');

        $floor_plan_items_raw = isset($_POST['floor_plan_items_json']) ? (string) wp_unslash($_POST['floor_plan_items_json']) : '';
        $floor_plan_items = $this->parse_floor_plan_items_json($floor_plan_items_raw);
        if (is_wp_error($floor_plan_items)) {
            return $floor_plan_items;
        }

        $floor_2d_raw = absint((string) $this->post_text('floor_2d_attachment_id'));
        $floor_3d_raw = absint((string) $this->post_text('floor_3d_attachment_id'));

        $floor_2d_attachment_id = $this->sanitize_image_attachment_id($floor_2d_raw);
        $floor_3d_attachment_id = $this->sanitize_image_attachment_id($floor_3d_raw);

        if ($floor_2d_raw > 0 && $floor_2d_attachment_id <= 0) {
            return new WP_Error('eocrm_property_floor', 'Rzut 2D musi byc obrazem z biblioteki mediow.');
        }

        if ($floor_3d_raw > 0 && $floor_3d_attachment_id <= 0) {
            return new WP_Error('eocrm_property_floor', 'Rzut 3D musi byc obrazem z biblioteki mediow.');
        }

        if (empty($floor_plan_items)) {
            $legacy_floor_plan_items = [];
            if ($floor_2d_attachment_id > 0) {
                $legacy_floor_plan_items[] = [
                    'attachment_id' => $floor_2d_attachment_id,
                    'label' => 'Poziom 1',
                    'position' => 0,
                ];
            }
            if ($floor_3d_attachment_id > 0) {
                $legacy_floor_plan_items[] = [
                    'attachment_id' => $floor_3d_attachment_id,
                    'label' => 'Poziom 2',
                    'position' => count($legacy_floor_plan_items),
                ];
            }
            $floor_plan_items = $legacy_floor_plan_items;
        }

        $floor_2d_attachment_id = isset($floor_plan_items[0]['attachment_id']) ? (int) $floor_plan_items[0]['attachment_id'] : 0;
        $floor_3d_attachment_id = isset($floor_plan_items[1]['attachment_id']) ? (int) $floor_plan_items[1]['attachment_id'] : 0;

        $validation_error = $this->validate_property_payload([
            'offer_number' => 'X',
            'property_type' => $property_type,
            'house_type' => $house_type,
            'street' => $street,
            'building_no' => $building_no,
            'postal_code' => $postal_code,
            'city' => $city,
            'land_registry_no' => $land_registry_no,
            'no_land_registry' => $no_land_registry,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'price' => $price,
            'area' => $area,
            'plot_area' => $plot_area,
            'plot_shape' => $plot_shape,
            'plot_length' => $plot_length,
            'plot_width' => $plot_width,
            'plot_dimensions_text' => $plot_dimensions_text,
            'plot_side_c' => $plot_side_c,
        ]);

        if ($validation_error !== '') {
            $validation_error = str_replace('Numer oferty jest wymagany.', '', $validation_error);
            $validation_error = trim($validation_error);
            if ($validation_error !== '') {
                return new WP_Error('eocrm_property_update', $validation_error);
            }
        }

        $parking_json = [
            'has_parking' => $has_parking,
            'types' => $parking_types,
            'counts' => [
                'najemne' => in_array('najemne', $parking_types, true) ? $parking_counts['najemne'] : null,
                'podziemne' => in_array('podziemne', $parking_types, true) ? $parking_counts['podziemne'] : null,
                'garaz' => in_array('garaz', $parking_types, true) ? $parking_counts['garaz'] : null,
            ],
        ];

        $media_json = [
            'map_pin_address' => $map_pin_address,
            'video_link' => $video_link,
            'virtual_tour_link' => $virtual_tour_link,
            'floor_2d_attachment_id' => $floor_2d_attachment_id,
            'floor_3d_attachment_id' => $floor_3d_attachment_id,
            'floor_plans' => $floor_plan_items,
            'heating' => $heating,
            'water' => $water,
            'sewage' => $sewage,
            'gas' => $gas,
            'electricity' => $electricity,
            'attic' => $attic,
            'multi_level' => $multi_level,
            'furnished_state' => $furnished_state,
        ];

        $now = current_time('mysql');
        $new_offer_expires_at = $this->resolve_new_offer_expires_at(
            $is_new_offer,
            (string) ($existing['new_offer_expires_at'] ?? ''),
            ! empty($existing['is_new_offer'])
        );

        $property_update_payload = [
            'house_type' => $house_type,
            'street' => $street,
            'building_no' => $building_no,
            'apartment_no' => $apartment_no,
            'postal_code' => $postal_code,
            'city' => $city,
            'district' => $district,
            'county' => $county,
            'gmina' => $gmina,
            'precinct' => $precinct,
            'plot_number' => $plot_number,
            'land_registry_no' => $no_land_registry ? '' : $land_registry_no,
            'no_land_registry' => $no_land_registry ? 1 : 0,
            'legal_status' => $legal_status,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'price' => $price,
            'price_currency' => $price_currency,
            'admin_rent' => $admin_rent,
            'area' => $area,
            'plot_area' => $plot_area,
            'price_per_m2' => $price_per_m2,
            'year_built' => $year_built,
            'floor_no' => $floor_no,
            'floors_total' => $floors_total,
            'rooms' => $rooms,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'toilets' => $toilets,
            'plot_shape' => $plot_shape,
            'plot_length' => $plot_length,
            'plot_width' => $plot_width,
            'plot_dimensions_text' => $plot_dimensions_text,
            'description' => $description,
            'building_finish' => $building_finish,
            'exposure_json' => wp_json_encode($exposure),
            'view_json' => wp_json_encode($view),
            'layout_json' => wp_json_encode($layout),
            'kitchen_type' => $kitchen_type,
            'parking_json' => wp_json_encode($parking_json),
            'media_json' => wp_json_encode($media_json),
            'amenities_json' => wp_json_encode($amenities),
            'equipment_json' => wp_json_encode($equipment),
            'extra_areas_json' => wp_json_encode($extra_areas),
            'custom_fields_json' => wp_json_encode($property_custom_field_values),
            'tags_json' => wp_json_encode($tags),
            'is_new_offer' => $is_new_offer ? 1 : 0,
            'is_exclusive' => $is_exclusive ? 1 : 0,
            'is_sold' => $is_sold ? 1 : 0,
            'is_rented' => $is_rented ? 1 : 0,
            'is_new_price' => $is_new_price ? 1 : 0,
            'no_commission' => $no_commission ? 1 : 0,
            'is_mls_offer' => $is_mls_offer ? 1 : 0,
            'is_premium' => $is_premium ? 1 : 0,
            'export_www' => $export_www ? 1 : 0,
            'export_portals' => $export_portals ? 1 : 0,
            'new_offer_expires_at' => $new_offer_expires_at,
            'updated_at' => $now,
        ];

        if (is_int($owner_user_id_for_update) && $owner_user_id_for_update > 0) {
            $property_update_payload['owner_user_id'] = $owner_user_id_for_update;
        }

        $updated = $wpdb->update(
            $this->tables['properties'],
            $property_update_payload,
            ['id' => $property_id]
        );

        if ($updated === false) {
            return new WP_Error('eocrm_property_update', 'Nie udalo sie zaktualizowac nieruchomosci.');
        }

        if (! $this->replace_property_media($property_id, $gallery_attachment_ids, $floor_plan_items, $now)) {
            return new WP_Error('eocrm_property_media', 'Zapis danych nieruchomosci powiodl sie, ale nie udalo sie zaktualizowac galerii/rzutow.');
        }

        $this->sync_public_offer_page($property_id, $export_www);
        $was_export_portals = isset($existing['export_portals']) ? (int) $existing['export_portals'] === 1 : false;
        if ($export_portals) {
            $this->queue_portal_export($property_id, $was_export_portals ? 'update' : 'insert');
        } elseif ($was_export_portals) {
            $this->queue_portal_export($property_id, 'delete');
        }
        return $property_id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validate_property_payload(array $payload): string
    {
        if ((string) ($payload['offer_number'] ?? '') === '') {
            return 'Numer oferty jest wymagany.';
        }

        $property_type = (string) ($payload['property_type'] ?? '');
        if ($property_type === '') {
            return 'Rodzaj nieruchomosci jest wymagany.';
        }

        if (! in_array($property_type, ['MIESZKANIE', 'DOM', 'DZIALKA', 'LOKAL_HU'], true)) {
            return 'Niepoprawny rodzaj nieruchomosci.';
        }

        if (in_array($property_type, ['MIESZKANIE', 'LOKAL_HU'], true)) {
            if ((string) ($payload['street'] ?? '') === '' || (string) ($payload['building_no'] ?? '') === '' || (string) ($payload['postal_code'] ?? '') === '' || (string) ($payload['city'] ?? '') === '') {
                return 'Dla mieszkania lub lokalu wymagane sa: ulica, numer, kod pocztowy i miasto.';
            }
        }

        if (in_array($property_type, ['DOM', 'DZIALKA'], true) && (string) ($payload['city'] ?? '') === '') {
            return 'Dla domu i dzialki wymagane jest miasto.';
        }

        if ($property_type === 'DOM' && (string) ($payload['house_type'] ?? '') === '') {
            return 'Dla domu wybierz typ domu.';
        }

        $no_land_registry = ! empty($payload['no_land_registry']);
        if (! $no_land_registry && (string) ($payload['land_registry_no'] ?? '') === '') {
            return 'Podaj numer ksiegi wieczystej albo zaznacz "Brak KW".';
        }

        $latitude = $payload['latitude'] ?? null;
        if ((is_float($latitude) || is_int($latitude)) && ((float) $latitude < -90 || (float) $latitude > 90)) {
            return 'Szerokosc geograficzna (lat) musi byc w zakresie -90 do 90.';
        }

        $longitude = $payload['longitude'] ?? null;
        if ((is_float($longitude) || is_int($longitude)) && ((float) $longitude < -180 || (float) $longitude > 180)) {
            return 'Dlugosc geograficzna (lng) musi byc w zakresie -180 do 180.';
        }

        $price = (float) ($payload['price'] ?? 0);
        if ($price <= 0) {
            return 'Cena musi byc wieksza od zera.';
        }

        $area = $payload['area'];
        if (! is_float($area) && ! is_int($area)) {
            return 'Metraz jest wymagany.';
        }

        if ((float) $area <= 0) {
            return 'Metraz musi byc wiekszy od zera.';
        }

        if ($property_type === 'DOM') {
            $plot_area = $payload['plot_area'] ?? null;
            if ((! is_float($plot_area) && ! is_int($plot_area)) || (float) $plot_area <= 0) {
                return 'Dla domu podaj wielkosc dzialki.';
            }
        }

        if (in_array($property_type, ['DOM', 'DZIALKA'], true)) {
            $plot_shape = $this->sanitize_plot_shape((string) ($payload['plot_shape'] ?? ''));

            if ($plot_shape !== '') {
                $plot_length = $payload['plot_length'] ?? null;
                $plot_width = $payload['plot_width'] ?? null;
                $plot_side_c = $payload['plot_side_c'] ?? $this->extract_plot_side_c_from_dimensions_text((string) ($payload['plot_dimensions_text'] ?? ''));

                if ($plot_shape === 'kwadrat') {
                    if ((! is_float($plot_length) && ! is_int($plot_length)) || (float) $plot_length <= 0) {
                        return 'Dla ksztaltu Kwadrat podaj dlugosc boku.';
                    }
                } elseif ($plot_shape === 'prostokat') {
                    if ((! is_float($plot_length) && ! is_int($plot_length)) || (float) $plot_length <= 0) {
                        return 'Dla ksztaltu Prostokat podaj bok A.';
                    }
                    if ((! is_float($plot_width) && ! is_int($plot_width)) || (float) $plot_width <= 0) {
                        return 'Dla ksztaltu Prostokat podaj bok B.';
                    }
                } elseif ($plot_shape === 'trojkat') {
                    if ((! is_float($plot_length) && ! is_int($plot_length)) || (float) $plot_length <= 0) {
                        return 'Dla ksztaltu Trojkat podaj bok A.';
                    }
                    if ((! is_float($plot_width) && ! is_int($plot_width)) || (float) $plot_width <= 0) {
                        return 'Dla ksztaltu Trojkat podaj bok B.';
                    }
                    if ((! is_float($plot_side_c) && ! is_int($plot_side_c)) || (float) $plot_side_c <= 0) {
                        return 'Dla ksztaltu Trojkat podaj bok C.';
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param int[] $gallery_attachment_ids
     */
    private function insert_gallery_media(int $property_id, array $gallery_attachment_ids, string $now): bool
    {
        global $wpdb;

        $table = $this->tables['property_media'];

        $position = 0;
        foreach ($gallery_attachment_ids as $attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            if (! is_string($url) || $url === '') {
                continue;
            }

            $ok = $wpdb->insert(
                $table,
                [
                    'property_id' => $property_id,
                    'media_type' => 'photo',
                    'attachment_id' => $attachment_id,
                    'media_url' => $url,
                    'position' => $position,
                    'is_primary' => $position === 0 ? 1 : 0,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%d', '%d', '%s']
            );

            if (! $ok) {
                return false;
            }

            $position++;
        }

        return true;
    }

    /**
     * @param array<int, array{attachment_id:int, label:string, position:int}> $floor_plan_items
     */
    private function insert_floor_plan_media(int $property_id, array $floor_plan_items, string $now): bool
    {
        global $wpdb;

        foreach ($floor_plan_items as $index => $floor_plan_item) {
            $attachment_id = isset($floor_plan_item['attachment_id']) ? (int) $floor_plan_item['attachment_id'] : 0;
            if ($attachment_id <= 0) {
                continue;
            }

            $url = wp_get_attachment_url($attachment_id);
            if (! is_string($url) || $url === '') {
                return false;
            }

            $insert_ok = $wpdb->insert(
                $this->tables['property_media'],
                [
                    'property_id' => $property_id,
                    'media_type' => 'floor_plan',
                    'attachment_id' => $attachment_id,
                    'media_url' => $url,
                    'position' => (int) $index,
                    'is_primary' => 0,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%d', '%d', '%s']
            );

            if (! $insert_ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int[] $gallery_attachment_ids
     */
    private function replace_property_media(int $property_id, array $gallery_attachment_ids, array $floor_plan_items, string $now): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->tables['property_media'],
            ['property_id' => $property_id],
            ['%d']
        );

        if ($deleted === false) {
            return false;
        }

        if (! $this->insert_gallery_media($property_id, $gallery_attachment_ids, $now)) {
            return false;
        }

        if (! $this->insert_floor_plan_media($property_id, $floor_plan_items, $now)) {
            return false;
        }

        return true;
    }

    private function sync_public_offer_page(int $property_id, bool $should_export): void
    {
        global $wpdb;

        if ($property_id <= 0) {
            return;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, offer_number, street, building_no, city, is_active
                FROM {$this->tables['properties']}
                WHERE id = %d
                LIMIT 1",
                $property_id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return;
        }

        $page_id = $this->find_export_page_id_by_property_id($property_id);
        if (! $should_export || (int) ($row['is_active'] ?? 0) !== 1) {
            if ($page_id > 0) {
                wp_update_post(
                    wp_slash([
                        'ID' => $page_id,
                        'post_status' => 'draft',
                    ])
                );
            }

            return;
        }

        $offer_number = (string) ($row['offer_number'] ?? '');
        $street = sanitize_text_field((string) ($row['street'] ?? ''));
        $building_no = sanitize_text_field((string) ($row['building_no'] ?? ''));
        $city = sanitize_text_field((string) ($row['city'] ?? ''));
        $show_offer_number_in_title = $this->should_show_offer_number_in_public_offer_title();

        $address = trim($street . ($building_no !== '' ? ' ' . $building_no : ''));
        if ($city !== '') {
            $address = $address !== '' ? ($address . ', ' . $city) : $city;
        }

        if ($address !== '') {
            $title = ($show_offer_number_in_title && $offer_number !== '') ? ($address . ' (' . $offer_number . ')') : $address;
        } else {
            $title = ($show_offer_number_in_title && $offer_number !== '') ? ('Oferta ' . $offer_number) : 'Oferta';
        }
        $slug = sanitize_title('oferta-' . ($offer_number !== '' ? $offer_number : (string) $property_id));
        if ($slug === '') {
            $slug = 'oferta-' . $property_id;
        }

        $post_data = [
            'post_title' => $title,
            'post_name' => $slug,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => '[eocrm_offer_single property_id="' . $property_id . '"]',
            'post_parent' => 0,
            'post_author' => get_current_user_id() ?: 1,
        ];

        if ($page_id > 0) {
            $post_data['ID'] = $page_id;
            $updated = wp_update_post(wp_slash($post_data), true);
            if (! is_wp_error($updated) && (int) $updated > 0) {
                update_post_meta((int) $updated, '_eocrm_property_id', (string) $property_id);
                update_post_meta((int) $updated, '_eocrm_offer_number', $offer_number);
            }
            return;
        }

        $inserted = wp_insert_post(wp_slash($post_data), true);
        if (! is_wp_error($inserted) && (int) $inserted > 0) {
            update_post_meta((int) $inserted, '_eocrm_property_id', (string) $property_id);
            update_post_meta((int) $inserted, '_eocrm_offer_number', $offer_number);
        }
    }

    private function find_export_page_id_by_property_id(int $property_id): int
    {
        global $wpdb;

        if ($property_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'page'
                AND p.post_status <> 'trash'
                AND pm.meta_key = %s
                AND pm.meta_value = %s
                ORDER BY p.ID DESC
                LIMIT 1",
                '_eocrm_property_id',
                (string) $property_id
            )
        );
    }

    private function offer_number_exists(string $offer_number): bool
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['properties']} WHERE offer_number = %s LIMIT 1",
                $offer_number
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

    private function agreement_has_exclusive_flag(int $agreement_id): bool
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return false;
        }

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_exclusive FROM {$this->tables['agreements']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            )
        );

        return (int) $value === 1;
    }

    private function agreement_is_owned_by_current_user(int $agreement_id): bool
    {
        global $wpdb;

        if ($agreement_id <= 0) {
            return false;
        }

        $owner_user_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT owner_user_id FROM {$this->tables['agreements']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $agreement_id
            )
        );

        return $this->can_access_owner_user_id($owner_user_id);
    }

    private function get_property_owner_user_id(int $property_id): int
    {
        global $wpdb;

        if ($property_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT owner_user_id FROM {$this->tables['properties']} WHERE id = %d AND is_active = 1 LIMIT 1",
                $property_id
            )
        );
    }

    private function post_text(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field((string) wp_unslash($_POST[$key])) : '';
    }

    private function post_checkbox(string $key): bool
    {
        return isset($_POST[$key]) && (string) wp_unslash($_POST[$key]) === '1';
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

    /**
     * @return array{number: string, settings: array<string,mixed>}
     */
    private function generate_next_available_offer_number(): array
    {
        global $wpdb;

        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            'offer',
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );

        $table = $this->tables['properties'];
        return EstateOfficeCRM_Numbering::generate_unique_number(
            $settings,
            function (string $candidate) use ($wpdb, $table): bool {
                if ($candidate === '') {
                    return true;
                }

                $exists = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE offer_number = %s LIMIT 1",
                        $candidate
                    )
                );

                return $exists > 0;
            }
        );
    }

    private function peek_default_offer_number(): string
    {
        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            'offer',
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

    private function queue_portal_export(int $property_id, string $event): void
    {
        if ($property_id <= 0 || ! $this->portal_export instanceof EstateOfficeCRM_Portal_Export) {
            return;
        }

        $this->portal_export->queue_property($property_id, $event);
    }

    private function is_property_marked_for_portal_export(int $property_id): bool
    {
        if ($property_id <= 0) {
            return false;
        }

        global $wpdb;
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT export_portals
                FROM {$this->tables['properties']}
                WHERE id = %d
                LIMIT 1",
                $property_id
            )
        );

        return (int) $value === 1;
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

    private function should_show_offer_number_in_public_offer_title(): bool
    {
        return $this->get_setting_value('offer_template_show_offer_number_in_title') !== '0';
    }

    private function resolve_new_offer_expires_at(bool $is_new_offer, string $existing_expires_at = '', bool $existing_is_new_offer = false): ?string
    {
        if (! $is_new_offer) {
            return null;
        }

        $existing_expires_at = trim($existing_expires_at);
        $existing_timestamp = $existing_expires_at !== '' ? strtotime($existing_expires_at) : false;
        $now_timestamp = current_time('timestamp');

        if ($existing_is_new_offer && is_int($existing_timestamp) && $existing_timestamp > $now_timestamp) {
            return gmdate('Y-m-d H:i:s', $existing_timestamp);
        }

        return $this->build_new_offer_expires_at();
    }

    private function build_new_offer_expires_at(): string
    {
        $duration_days = $this->get_new_offer_duration_days();
        $timestamp = strtotime(current_time('mysql') . ' +' . (string) $duration_days . ' days');
        if (! is_int($timestamp) || $timestamp <= 0) {
            $timestamp = time() + ($duration_days * DAY_IN_SECONDS);
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function get_new_offer_duration_days(): int
    {
        $raw = trim($this->get_setting_value('new_offer_duration_days'));
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 7;
        }

        $days = (int) $raw;
        if ($days < 1) {
            return 1;
        }
        if ($days > 365) {
            return 365;
        }

        return $days;
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
     * @return array<int, array<string, mixed>>
     */
    private function load_property_additional_field_definitions(): array
    {
        return EstateOfficeCRM_Property_Custom_Fields::load_definitions(function (string $key): string {
            return $this->get_setting_value($key);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     * @return array<string, string|bool>
     */
    private function collect_property_custom_field_values(array $definitions): array
    {
        $values = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $field_key = sanitize_key((string) ($definition['key'] ?? ''));
            if ($field_key === '') {
                continue;
            }

            $field_type = sanitize_key((string) ($definition['type'] ?? 'text'));
            $input_name = EstateOfficeCRM_Property_Custom_Fields::input_name($field_key);

            if ($field_type === 'checkbox') {
                $raw_value = $this->post_checkbox($input_name);
            } else {
                $raw_value = $this->post_text($input_name);
            }

            $clean_value = EstateOfficeCRM_Property_Custom_Fields::sanitize_value($raw_value, $field_type);
            if (! EstateOfficeCRM_Property_Custom_Fields::has_value($clean_value, $field_type)) {
                continue;
            }

            $values[$field_key] = $clean_value;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $existing_values
     * @param array<string, string|bool> $new_values
     * @param array<int, array<string, mixed>> $definitions
     * @return array<string, string|bool>
     */
    private function merge_property_custom_field_values(array $existing_values, array $new_values, array $definitions): array
    {
        $configured_keys = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $definition_key = sanitize_key((string) ($definition['key'] ?? ''));
            if ($definition_key !== '') {
                $configured_keys[$definition_key] = true;
            }
        }

        $preserved_values = [];
        foreach ($existing_values as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $key = sanitize_key($key);
            if ($key === '' || isset($configured_keys[$key])) {
                continue;
            }

            if (is_bool($value) || is_string($value) || is_numeric($value)) {
                $preserved_values[$key] = is_bool($value) ? $value : sanitize_text_field((string) $value);
            }
        }

        return array_merge($preserved_values, $new_values);
    }

    private function sanitize_property_type(string $value): string
    {
        $value = strtoupper($value);
        $allowed = ['MIESZKANIE', 'DOM', 'DZIALKA', 'LOKAL_HU'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_house_type(string $value): string
    {
        $value = strtoupper($value);
        $allowed = ['WOLNOSTOJACY', 'BLIZNIAK', 'SZEREGOWIEC', 'WIELORODZINNY'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_legal_status(string $value): string
    {
        $allowed = [
            'Wlasnosc',
            'Wspolwlasnosc',
            'Spoldzielcze Wlasnosciowe Prawo do Lokalu',
            'Dzierzawa',
            'Inne',
        ];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_price_currency(string $value): string
    {
        $value = strtoupper(trim($value));
        $allowed = ['PLN', 'EUR', 'USD', 'GBP'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_kitchen_type(string $value): string
    {
        if ($value === 'Z salonem') {
            $value = 'Polotwarta';
        }

        $allowed = ['Aneks', 'Oddzielna', 'Polotwarta'];

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

    private function sanitize_plot_shape(string $value): string
    {
        $value = strtolower($value);
        if ($value === 'regularny') {
            $value = 'prostokat';
        } elseif ($value === 'nieregularny') {
            $value = 'nieregularna';
        }
        $allowed = ['kwadrat', 'prostokat', 'trojkat', 'nieregularna'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @return array{0:string,1:?float,2:?float,3:string}
     */
    private function normalize_plot_dimensions(string $shape, ?float $length, ?float $width, ?float $side_c, string $plot_dimensions_text): array
    {
        $shape = $this->sanitize_plot_shape($shape);
        $length = is_float($length) || is_int($length) ? round((float) $length, 2) : null;
        $width = is_float($width) || is_int($width) ? round((float) $width, 2) : null;
        $side_c = is_float($side_c) || is_int($side_c) ? round((float) $side_c, 2) : $this->extract_plot_side_c_from_dimensions_text($plot_dimensions_text);
        $plot_dimensions_text = trim($plot_dimensions_text);

        if ($shape === 'kwadrat') {
            return [$shape, $length, null, ''];
        }

        if ($shape === 'prostokat') {
            return [$shape, $length, $width, ''];
        }

        if ($shape === 'trojkat') {
            $meta = ['side_c' => $side_c];
            $encoded = wp_json_encode($meta);
            if (! is_string($encoded)) {
                $encoded = '';
            }

            return [$shape, $length, $width, $encoded];
        }

        if ($shape === 'nieregularna') {
            return [$shape, null, null, $plot_dimensions_text];
        }

        return ['', null, null, ''];
    }

    private function extract_plot_side_c_from_dimensions_text(string $plot_dimensions_text): ?float
    {
        $plot_dimensions_text = trim($plot_dimensions_text);
        if ($plot_dimensions_text === '') {
            return null;
        }

        $decoded = json_decode($plot_dimensions_text, true);
        if (! is_array($decoded)) {
            return null;
        }

        if (! array_key_exists('side_c', $decoded)) {
            return null;
        }

        $side_c_raw = is_string($decoded['side_c']) ? str_replace(',', '.', $decoded['side_c']) : $decoded['side_c'];
        if (! is_numeric($side_c_raw)) {
            return null;
        }

        $side_c = round((float) $side_c_raw, 2);
        return $side_c > 0 ? $side_c : null;
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
        $allowed = ['tak', 'nie', 'czesciowe'];

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

    private function sanitize_decimal(string $value, int $precision = 2): float
    {
        $value = str_replace(',', '.', $value);
        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, $precision);
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
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function sanitize_non_negative_int_nullable(string $value): ?int
    {
        $int = $this->sanitize_int_nullable($value);
        if (! is_int($int)) {
            return null;
        }

        return $int < 0 ? null : $int;
    }

    private function sanitize_image_attachment_id(int $attachment_id): int
    {
        if ($attachment_id <= 0) {
            return 0;
        }

        return wp_attachment_is_image($attachment_id) ? $attachment_id : 0;
    }

    private function calculate_price_per_m2(float $price, ?float $area): ?float
    {
        if ($price <= 0 || ! is_float($area) || $area <= 0) {
            return null;
        }

        return round($price / $area, 2);
    }

    /**
     * @return int[]
     */
    private function parse_attachment_ids(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = explode(',', $raw);
        $ids = [];

        foreach ($parts as $part) {
            $id = absint(trim($part));
            if ($id <= 0) {
                continue;
            }

            if (! wp_attachment_is_image($id)) {
                continue;
            }

            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array{attachment_id:int, label:string, position:int}>|WP_Error
     */
    private function parse_floor_plan_items_json(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return new WP_Error('eocrm_property_floor', 'Niepoprawny format listy rzutow.');
        }

        $items = [];
        $used_attachment_ids = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $attachment_id = isset($entry['attachment_id']) ? absint((string) $entry['attachment_id']) : 0;
            if ($attachment_id <= 0 || in_array($attachment_id, $used_attachment_ids, true)) {
                continue;
            }

            $attachment_id = $this->sanitize_image_attachment_id($attachment_id);
            if ($attachment_id <= 0) {
                return new WP_Error('eocrm_property_floor', 'Kazdy rzut musi byc obrazem z biblioteki mediow.');
            }

            $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
            $label = trim((string) preg_replace('/\s+/', ' ', $label));
            if ($label === '') {
                $label = 'Poziom ' . (string) (count($items) + 1);
            }

            if (function_exists('mb_substr')) {
                $label = (string) mb_substr($label, 0, 80);
            } else {
                $label = substr($label, 0, 80);
            }

            $used_attachment_ids[] = $attachment_id;
            $items[] = [
                'attachment_id' => $attachment_id,
                'label' => $label,
                'position' => count($items),
            ];

            if (count($items) >= 30) {
                break;
            }
        }

        return $items;
    }

    private function is_valid_owner_user(int $user_id): bool
    {
        return EstateOfficeCRM_Access::is_valid_owner_user($user_id);
    }

    private function can_access_owner_user_id(int $owner_user_id): bool
    {
        return EstateOfficeCRM_Access::can_access_owner_user($owner_user_id, $this->tables, get_current_user_id());
    }

    private function store_property_form_draft(string $mode, int $property_id): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! isset($_POST) || ! is_array($_POST)) {
            return;
        }

        $draft_raw = wp_unslash($_POST);
        if (! is_array($draft_raw) || empty($draft_raw)) {
            return;
        }

        $draft = $this->sanitize_property_form_draft_array($draft_raw);
        if (empty($draft)) {
            return;
        }

        if ($mode === 'edit' && $property_id > 0) {
            update_user_meta($user_id, self::USERMETA_EDIT_FORM_DRAFT_PREFIX . $property_id, $draft);
            return;
        }

        update_user_meta($user_id, self::USERMETA_NEW_FORM_DRAFT, $draft);
    }

    private function clear_property_form_draft(string $mode, int $property_id): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        if ($mode === 'edit' && $property_id > 0) {
            delete_user_meta($user_id, self::USERMETA_EDIT_FORM_DRAFT_PREFIX . $property_id);
            return;
        }

        delete_user_meta($user_id, self::USERMETA_NEW_FORM_DRAFT);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function sanitize_property_form_draft_array(array $source): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (in_array($key, ['eocrm_nonce', 'eocrm_property_update_nonce', 'eocrm_property_delete_nonce', '_wp_http_referer'], true)) {
                continue;
            }

            $result[$key] = $this->sanitize_property_form_draft_value($value, $key);
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitize_property_form_draft_value($value, string $key)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $child_key => $child_value) {
                $child_context = is_string($child_key) ? $child_key : $key;
                $result[] = $this->sanitize_property_form_draft_value($child_value, $child_context);
            }

            return $result;
        }

        $string_value = is_scalar($value) ? (string) $value : '';
        if ($key === 'description') {
            $clean = wp_kses_post($string_value);
        } else {
            $clean = sanitize_text_field($string_value);
        }

        if (function_exists('mb_substr')) {
            return mb_substr($clean, 0, 12000);
        }

        return substr($clean, 0, 12000);
    }

    private function redirect_back(string $notice, string $message = '', bool $stay_on_form = false): void
    {
        $args = [
            'crm' => 'properties',
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_form) {
            $args['mode'] = 'new-property';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('properties')));
        exit;
    }

    private function redirect_to_profile(int $property_id, string $notice, string $message = '', bool $stay_on_edit_form = false): void
    {
        $args = [
            'crm' => 'properties',
            'property_id' => max(0, $property_id),
            'crm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        if ($stay_on_edit_form) {
            $args['mode'] = 'edit-property';
        }

        wp_safe_redirect(add_query_arg($args, $this->get_section_url('properties')));
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



