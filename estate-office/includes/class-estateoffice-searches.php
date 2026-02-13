<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Searches
{
    public static function boot(): void
    {
        add_action('admin_post_estateoffice_create_search', [self::class, 'handle_create_search']);
    }

    public static function handle_create_search(): void
    {
        if (! is_user_logged_in() || ! current_user_can('estateoffice_manage_searches')) {
            wp_die(esc_html__('Brak uprawnień do dodawania poszukiwań.', 'estateoffice'));
        }

        check_admin_referer('estateoffice_create_search');

        $searchNumber = self::post_text('search_number');
        $agreementId = self::post_int('agreement_id');
        $transactionType = self::post_text('transaction_type');
        $propertyType = self::post_text('property_type');

        $allowedTransactions = ['SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM'];
        $allowedPropertyTypes = ['MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U'];

        if ($searchNumber === '') {
            self::redirect_with_notice('error', 'Numer poszukiwania jest wymagany.');
        }

        if (! in_array($transactionType, $allowedTransactions, true) || ! in_array($propertyType, $allowedPropertyTypes, true)) {
            self::redirect_with_notice('error', 'Nieprawidłowy typ transakcji lub rodzaj nieruchomości.');
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

        global $wpdb;
        $table = $wpdb->prefix . 'eo_searches';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE search_number = %s LIMIT 1", $searchNumber));
        if ($exists) {
            self::redirect_with_notice('error', 'Podany numer poszukiwania już istnieje.');
        }

        $budgetFrom = self::post_float('budget_from');
        $budgetTo = self::post_float('budget_to');
        $areaFrom = self::post_float('area_from');
        $areaTo = self::post_float('area_to');
        $roomsFrom = self::post_int('rooms_from');
        $roomsTo = self::post_int('rooms_to');

        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash((string) $_POST['description'])) : '';

        $criteria = [
            'location' => self::post_text('location'),
            'heating' => self::post_text('heating'),
            'water' => self::post_text('water'),
            'sewage' => self::post_text('sewage'),
            'gas' => isset($_POST['gas']),
            'elevator' => isset($_POST['elevator']),
            'air_conditioning' => isset($_POST['air_conditioning']),
            'monitoring' => isset($_POST['monitoring']),
            'furnished' => self::post_text('furnished'),
            'equipment' => [
                'washing_machine' => isset($_POST['equipment_washing_machine']),
                'dishwasher' => isset($_POST['equipment_dishwasher']),
                'fridge' => isset($_POST['equipment_fridge']),
                'oven' => isset($_POST['equipment_oven']),
            ],
            'extras' => [
                'balcony' => isset($_POST['extra_balcony']),
                'terrace' => isset($_POST['extra_terrace']),
                'basement' => isset($_POST['extra_basement']),
                'garden' => isset($_POST['extra_garden']),
            ],
        ];

        $result = $wpdb->insert(
            $table,
            [
                'search_number' => $searchNumber,
                'agreement_id' => $agreementId > 0 ? $agreementId : 0,
                'transaction_type' => $transactionType,
                'property_type' => $propertyType,
                'budget_from' => $budgetFrom,
                'budget_to' => $budgetTo,
                'area_from' => $areaFrom,
                'area_to' => $areaTo,
                'rooms_from' => $roomsFrom,
                'rooms_to' => $roomsTo,
                'criteria_data' => wp_json_encode($criteria),
                'description' => $description,
                'owner_user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            self::redirect_with_notice('error', 'Nie udało się zapisać poszukiwania.');
        }

        self::redirect_with_notice('success', 'Poszukiwanie zostało dodane.');
    }

    public static function get_searches(string $search = ''): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_searches';

        if ($search === '') {
            return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A) ?: [];
        }

        $needle = '%' . $wpdb->esc_like($search) . '%';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE search_number LIKE %s
            OR transaction_type LIKE %s
            OR property_type LIKE %s
            ORDER BY created_at DESC LIMIT 200",
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

    private static function post_int(string $key): int
    {
        return isset($_POST[$key]) ? (int) wp_unslash((string) $_POST[$key]) : 0;
    }

    private static function post_float(string $key): float
    {
        return isset($_POST[$key]) ? (float) wp_unslash((string) $_POST[$key]) : 0.0;
    }

    private static function redirect_with_notice(string $status, string $message): void
    {
        $pages = get_option('estateoffice_crm_pages', []);
        $id = isset($pages['estateoffice-crm-poszukiwania']) ? (int) $pages['estateoffice-crm-poszukiwania'] : 0;
        $target = $id > 0 ? get_permalink($id) : home_url('/estateoffice-crm-poszukiwania/');

        wp_safe_redirect(add_query_arg([
            'eo_status' => $status,
            'eo_message' => rawurlencode($message),
        ], $target));
        exit;
    }
}
