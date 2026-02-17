<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Clients
{
    public static function boot(): void
    {
        add_action('admin_post_estateoffice_create_client', [self::class, 'handle_create_client']);
    }

    public static function handle_create_client(): void
    {
        if (! is_user_logged_in() || ! current_user_can('estateoffice_manage_clients')) {
            wp_die(esc_html__('Brak uprawnień do dodawania klientów.', 'estateoffice'));
        }

        check_admin_referer('estateoffice_create_client');

        $clientType = isset($_POST['client_type']) ? sanitize_key(wp_unslash((string) $_POST['client_type'])) : 'person';
        if (! in_array($clientType, ['person', 'company'], true)) {
            $clientType = 'person';
        }

        $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash((string) $_POST['first_name'])) : '';
        $lastName = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash((string) $_POST['last_name'])) : '';
        $companyName = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash((string) $_POST['company_name'])) : '';
        $representativeName = isset($_POST['representative_name']) ? sanitize_text_field(wp_unslash((string) $_POST['representative_name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash((string) $_POST['phone'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '';
        $website = isset($_POST['website']) ? esc_url_raw(wp_unslash((string) $_POST['website'])) : '';
        $pesel = isset($_POST['pesel']) ? sanitize_text_field(wp_unslash((string) $_POST['pesel'])) : '';
        $documentType = isset($_POST['document_type']) ? sanitize_text_field(wp_unslash((string) $_POST['document_type'])) : '';
        $documentNumber = isset($_POST['document_number']) ? sanitize_text_field(wp_unslash((string) $_POST['document_number'])) : '';
        $nip = isset($_POST['nip']) ? sanitize_text_field(wp_unslash((string) $_POST['nip'])) : '';
        $krs = isset($_POST['krs']) ? sanitize_text_field(wp_unslash((string) $_POST['krs'])) : '';
        $regon = isset($_POST['regon']) ? sanitize_text_field(wp_unslash((string) $_POST['regon'])) : '';

        if ($clientType === 'person' && ($firstName === '' || $lastName === '')) {
            self::redirect_with_notice('error', 'Dla osoby fizycznej wymagane są imię i nazwisko.');
        }

        if ($clientType === 'company' && ($companyName === '' || $representativeName === '')) {
            self::redirect_with_notice('error', 'Dla firmy wymagana jest nazwa firmy i reprezentant.');
        }

        if ($email !== '' && ! is_email($email)) {
            self::redirect_with_notice('error', 'Nieprawidłowy adres e-mail klienta.');
        }

        $residentialAddress = self::read_address_from_post('residential');
        $sameAddress = isset($_POST['same_correspondence']) && $_POST['same_correspondence'] === '1';
        $correspondenceAddress = $sameAddress ? $residentialAddress : self::read_address_from_post('correspondence');

        global $wpdb;
        $table = $wpdb->prefix . 'eo_clients';

        $result = $wpdb->insert(
            $table,
            [
                'client_type' => $clientType,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company_name' => $companyName,
                'representative_name' => $representativeName,
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'pesel' => $pesel,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'nip' => $nip,
                'krs' => $krs,
                'regon' => $regon,
                'residential_address' => wp_json_encode($residentialAddress),
                'correspondence_address' => wp_json_encode($correspondenceAddress),
                'owner_user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                '%d', '%s', '%s',
            ]
        );

        if ($result === false) {
            self::redirect_with_notice('error', 'Nie udało się zapisać klienta.');
        }

        self::redirect_with_notice('success', 'Klient został dodany.');
    }

    public static function get_clients(string $search = ''): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_clients';

        if ($search === '') {
            $sql = "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200";
            return $wpdb->get_results($sql, ARRAY_A) ?: [];
        }

        $needle = '%' . $wpdb->esc_like($search) . '%';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE first_name LIKE %s
            OR last_name LIKE %s
            OR company_name LIKE %s
            OR representative_name LIKE %s
            OR phone LIKE %s
            OR email LIKE %s
            ORDER BY created_at DESC LIMIT 200",
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function read_address_from_post(string $prefix): array
    {
        return [
            'street' => isset($_POST[$prefix . '_street']) ? sanitize_text_field(wp_unslash((string) $_POST[$prefix . '_street'])) : '',
            'number' => isset($_POST[$prefix . '_number']) ? sanitize_text_field(wp_unslash((string) $_POST[$prefix . '_number'])) : '',
            'apartment' => isset($_POST[$prefix . '_apartment']) ? sanitize_text_field(wp_unslash((string) $_POST[$prefix . '_apartment'])) : '',
            'postal_code' => isset($_POST[$prefix . '_postal_code']) ? sanitize_text_field(wp_unslash((string) $_POST[$prefix . '_postal_code'])) : '',
            'city' => isset($_POST[$prefix . '_city']) ? sanitize_text_field(wp_unslash((string) $_POST[$prefix . '_city'])) : '',
            'country' => isset($_POST[$prefix . '_country']) ? sanitize_text_field(wp_unslash((string) $_POST[$prefix . '_country'])) : '',
        ];
    }

    private static function redirect_with_notice(string $status, string $message): void
    {
        $pages = get_option('estateoffice_crm_pages', []);
        $clientsPageId = isset($pages['estateoffice-crm-klienci']) ? (int) $pages['estateoffice-crm-klienci'] : 0;
        $target = $clientsPageId > 0 ? get_permalink($clientsPageId) : home_url('/estateoffice-crm-klienci/');

        $redirect = add_query_arg([
            'eo_status' => $status,
            'eo_message' => rawurlencode($message),
        ], $target);

        wp_safe_redirect($redirect);
        exit;
    }
}
