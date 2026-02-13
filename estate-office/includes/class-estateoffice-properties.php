<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Properties
{
    public static function boot(): void
    {
        add_action('admin_post_estateoffice_create_property', [self::class, 'handle_create_property']);
    }

    public static function handle_create_property(): void
    {
        if (! is_user_logged_in() || ! current_user_can('estateoffice_manage_properties')) {
            wp_die(esc_html__('Brak uprawnień do dodawania nieruchomości.', 'estateoffice'));
        }

        check_admin_referer('estateoffice_create_property');

        $offerNumber = isset($_POST['offer_number']) ? sanitize_text_field(wp_unslash((string) $_POST['offer_number'])) : '';
        $agreementId = isset($_POST['agreement_id']) ? (int) $_POST['agreement_id'] : 0;
        $transactionType = isset($_POST['transaction_type']) ? sanitize_text_field(wp_unslash((string) $_POST['transaction_type'])) : '';
        $propertyType = isset($_POST['property_type']) ? sanitize_text_field(wp_unslash((string) $_POST['property_type'])) : '';
        $legalStatus = isset($_POST['legal_status']) ? sanitize_text_field(wp_unslash((string) $_POST['legal_status'])) : '';
        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash((string) $_POST['description'])) : '';

        $allowedTransaction = ['SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM'];
        $allowedProperty = ['MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U'];
        if (! in_array($transactionType, $allowedTransaction, true) || ! in_array($propertyType, $allowedProperty, true)) {
            self::redirect_with_notice('error', 'Nieprawidłowy typ transakcji lub rodzaj nieruchomości.');
        }

        if ($offerNumber === '') {
            self::redirect_with_notice('error', 'Numer oferty jest wymagany.');
        }

        if ($agreementId > 0) {
            $agreement = EstateOffice_Agreements::get_agreement($agreementId);
            if (! $agreement) {
                self::redirect_with_notice('error', 'Nie znaleziono powiązanej umowy.');
            }

            if ((string) $agreement['transaction_type'] !== $transactionType) {
                self::redirect_with_notice('error', 'Typ transakcji musi być zgodny z powiązaną umową.');
            }
        }

        $addressData = [
            'street' => self::post_text('street'),
            'number' => self::post_text('number'),
            'apartment' => self::post_text('apartment'),
            'postal_code' => self::post_text('postal_code'),
            'district' => self::post_text('district'),
            'city' => self::post_text('city'),
            'county' => self::post_text('county'),
            'plot_number' => self::post_text('plot_number'),
        ];

        if ($addressData['city'] === '' || $addressData['postal_code'] === '') {
            self::redirect_with_notice('error', 'Miasto i kod pocztowy są wymagane.');
        }

        $price = self::post_float('price');
        $rentAdmin = self::post_float('administrative_rent');
        $area = self::post_float('area');
        $pricePerM2 = $area > 0 ? round($price / $area, 2) : 0.0;

        $pricingData = [
            'price' => $price,
            'administrative_rent' => $rentAdmin,
            'area' => $area,
            'price_per_m2' => $pricePerM2,
            'currency' => self::post_text('currency') ?: 'PLN',
        ];

        $detailsData = [
            'rooms' => self::post_int('rooms'),
            'floor' => self::post_int('floor'),
            'floors' => self::post_int('floors'),
            'year_built' => self::post_int('year_built'),
            'plot_shape' => self::post_text('plot_shape'),
            'plot_dimensions' => self::post_text('plot_dimensions'),
            'house_type' => self::post_text('house_type'),
            'land_register_number' => self::post_text('land_register_number'),
            'no_land_register' => isset($_POST['no_land_register']) && $_POST['no_land_register'] === '1',
        ];

        $tagsData = [
            'new_offer' => isset($_POST['tag_new_offer']),
            'exclusive' => isset($_POST['tag_exclusive']),
            'sold' => isset($_POST['tag_sold']),
            'rented' => isset($_POST['tag_rented']),
            'new_price' => isset($_POST['tag_new_price']),
            'no_commission' => isset($_POST['tag_no_commission']),
            'mls' => isset($_POST['tag_mls']),
            'premium' => isset($_POST['tag_premium']),
        ];

        $exportWWW = isset($_POST['export_www']) ? 1 : 0;
        $exportPortals = isset($_POST['export_portals']) ? 1 : 0;

        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE offer_number = %s LIMIT 1", $offerNumber));
        if ($exists) {
            self::redirect_with_notice('error', 'Podany numer oferty już istnieje.');
        }

        $result = $wpdb->insert(
            $table,
            [
                'offer_number' => $offerNumber,
                'agreement_id' => $agreementId > 0 ? $agreementId : 0,
                'transaction_type' => $transactionType,
                'property_type' => $propertyType,
                'legal_status' => $legalStatus,
                'address_data' => wp_json_encode($addressData),
                'location_data' => wp_json_encode([]),
                'pricing_data' => wp_json_encode($pricingData),
                'details_data' => wp_json_encode($detailsData),
                'media_data' => wp_json_encode([]),
                'amenities_data' => wp_json_encode([]),
                'equipment_data' => wp_json_encode([]),
                'extra_spaces_data' => wp_json_encode([]),
                'description' => $description,
                'tags_data' => wp_json_encode($tagsData),
                'export_www' => $exportWWW,
                'export_portals' => $exportPortals,
                'owner_user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );

        if ($result === false) {
            self::redirect_with_notice('error', 'Nie udało się zapisać nieruchomości.');
        }

        self::redirect_with_notice('success', 'Nieruchomość została dodana.');
    }

    public static function get_properties(string $search = ''): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';

        if ($search === '') {
            return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A) ?: [];
        }

        $needle = '%' . $wpdb->esc_like($search) . '%';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE offer_number LIKE %s
            OR transaction_type LIKE %s
            OR property_type LIKE %s
            OR legal_status LIKE %s
            ORDER BY created_at DESC LIMIT 200",
            $needle,
            $needle,
            $needle,
            $needle
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function post_text(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
    }

    private static function post_float(string $key): float
    {
        return isset($_POST[$key]) ? (float) wp_unslash((string) $_POST[$key]) : 0.0;
    }

    private static function post_int(string $key): int
    {
        return isset($_POST[$key]) ? (int) wp_unslash((string) $_POST[$key]) : 0;
    }

    private static function redirect_with_notice(string $status, string $message): void
    {
        $pages = get_option('estateoffice_crm_pages', []);
        $id = isset($pages['estateoffice-crm-nieruchomosci']) ? (int) $pages['estateoffice-crm-nieruchomosci'] : 0;
        $target = $id > 0 ? get_permalink($id) : home_url('/estateoffice-crm-nieruchomosci/');

        wp_safe_redirect(add_query_arg([
            'eo_status' => $status,
            'eo_message' => rawurlencode($message),
        ], $target));
        exit;
    }
}
