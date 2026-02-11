<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Agreements
{
    public static function boot(): void
    {
        add_action('admin_post_estateoffice_agreement_step1', [self::class, 'handle_step1']);
        add_action('admin_post_estateoffice_agreement_step2_existing_client', [self::class, 'handle_step2_existing_client']);
        add_action('admin_post_estateoffice_agreement_step2_new_client', [self::class, 'handle_step2_new_client']);
        add_action('admin_post_estateoffice_agreement_step2_continue', [self::class, 'handle_step2_continue']);
    }

    public static function handle_step1(): void
    {
        self::assert_access();
        check_admin_referer('estateoffice_agreement_step1');

        $agreementNumber = isset($_POST['agreement_number']) ? sanitize_text_field(wp_unslash((string) $_POST['agreement_number'])) : '';
        $transactionType = isset($_POST['transaction_type']) ? sanitize_text_field(wp_unslash((string) $_POST['transaction_type'])) : '';
        $startDate = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash((string) $_POST['start_date'])) : '';
        $isOpenEnded = isset($_POST['is_open_ended']) && $_POST['is_open_ended'] === '1';
        $endDate = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash((string) $_POST['end_date'])) : '';
        $commissionValue = isset($_POST['commission_value']) ? (float) wp_unslash((string) $_POST['commission_value']) : 0.0;
        $commissionUnit = isset($_POST['commission_unit']) ? sanitize_text_field(wp_unslash((string) $_POST['commission_unit'])) : '%';

        $allowedTransactions = ['SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM'];
        if (! in_array($transactionType, $allowedTransactions, true)) {
            self::redirect_with_notice('error', 'Nieprawidłowy typ transakcji.');
        }

        $allowedUnits = ['%', 'PLN', 'EUR', 'USD'];
        if (! in_array($commissionUnit, $allowedUnits, true)) {
            $commissionUnit = '%';
        }

        if ($agreementNumber === '' || $startDate === '') {
            self::redirect_with_notice('error', 'Numer umowy i data zawarcia są wymagane.');
        }

        if (! $isOpenEnded && $endDate === '') {
            self::redirect_with_notice('error', 'Dla umowy terminowej data zakończenia jest wymagana.');
        }

        global $wpdb;
        $agreementsTable = $wpdb->prefix . 'eo_agreements';

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$agreementsTable} WHERE agreement_number = %s LIMIT 1", $agreementNumber)
        );

        if ($exists) {
            self::redirect_with_notice('error', 'Podany numer umowy już istnieje.');
        }

        $inserted = $wpdb->insert(
            $agreementsTable,
            [
                'agreement_number' => $agreementNumber,
                'transaction_type' => $transactionType,
                'start_date' => $startDate,
                'end_date' => $isOpenEnded ? null : $endDate,
                'is_open_ended' => $isOpenEnded ? 1 : 0,
                'commission_value' => $commissionValue,
                'commission_unit' => $commissionUnit,
                'current_stage' => 'Umowa Pośrednictwa',
                'owner_user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            self::redirect_with_notice('error', 'Nie udało się utworzyć umowy.');
        }

        $agreementId = (int) $wpdb->insert_id;

        $stagesTable = $wpdb->prefix . 'eo_agreement_stages';
        $wpdb->insert(
            $stagesTable,
            [
                'agreement_id' => $agreementId,
                'stage_name' => 'Umowa Pośrednictwa',
                'stage_date' => $startDate,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        self::redirect_to_step(2, $agreementId, 'success', 'Etap 1 zakończony. Dodaj klienta do umowy.');
    }

    public static function handle_step2_existing_client(): void
    {
        self::assert_access();
        check_admin_referer('estateoffice_agreement_step2_existing_client');

        $agreementId = isset($_POST['agreement_id']) ? (int) $_POST['agreement_id'] : 0;
        $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;

        if ($agreementId <= 0 || $clientId <= 0) {
            self::redirect_to_step(2, $agreementId, 'error', 'Wybierz poprawnego klienta.');
        }

        self::attach_client($agreementId, $clientId);
        self::redirect_to_step(2, $agreementId, 'success', 'Klient został przypisany do umowy.');
    }

    public static function handle_step2_new_client(): void
    {
        self::assert_access();
        check_admin_referer('estateoffice_agreement_step2_new_client');

        $agreementId = isset($_POST['agreement_id']) ? (int) $_POST['agreement_id'] : 0;
        if ($agreementId <= 0) {
            self::redirect_with_notice('error', 'Brak identyfikatora umowy.');
        }

        $clientType = isset($_POST['client_type']) ? sanitize_key(wp_unslash((string) $_POST['client_type'])) : 'person';
        $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash((string) $_POST['first_name'])) : '';
        $lastName = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash((string) $_POST['last_name'])) : '';
        $companyName = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash((string) $_POST['company_name'])) : '';
        $representativeName = isset($_POST['representative_name']) ? sanitize_text_field(wp_unslash((string) $_POST['representative_name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash((string) $_POST['phone'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '';

        if ($clientType === 'person' && ($firstName === '' || $lastName === '')) {
            self::redirect_to_step(2, $agreementId, 'error', 'Dla osoby fizycznej wymagane są imię i nazwisko.');
        }

        if ($clientType === 'company' && ($companyName === '' || $representativeName === '')) {
            self::redirect_to_step(2, $agreementId, 'error', 'Dla firmy wymagana jest nazwa i reprezentant.');
        }

        global $wpdb;
        $clientsTable = $wpdb->prefix . 'eo_clients';
        $inserted = $wpdb->insert(
            $clientsTable,
            [
                'client_type' => $clientType,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company_name' => $companyName,
                'representative_name' => $representativeName,
                'phone' => $phone,
                'email' => $email,
                'residential_address' => wp_json_encode([]),
                'correspondence_address' => wp_json_encode([]),
                'owner_user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            self::redirect_to_step(2, $agreementId, 'error', 'Nie udało się dodać nowego klienta.');
        }

        $clientId = (int) $wpdb->insert_id;
        self::attach_client($agreementId, $clientId);

        self::redirect_to_step(2, $agreementId, 'success', 'Nowy klient został dodany i przypisany do umowy.');
    }

    public static function handle_step2_continue(): void
    {
        self::assert_access();
        check_admin_referer('estateoffice_agreement_step2_continue');

        $agreementId = isset($_POST['agreement_id']) ? (int) $_POST['agreement_id'] : 0;
        $addAnother = isset($_POST['add_another_client']) ? sanitize_key((string) $_POST['add_another_client']) : 'no';

        if ($agreementId <= 0) {
            self::redirect_with_notice('error', 'Brak identyfikatora umowy.');
        }

        if ($addAnother === 'yes') {
            self::redirect_to_step(2, $agreementId, 'success', 'Dodaj kolejnego klienta.');
        }

        $agreement = self::get_agreement($agreementId);
        if (! $agreement) {
            self::redirect_with_notice('error', 'Nie znaleziono umowy.');
        }

        $transactionType = (string) $agreement['transaction_type'];
        $next = in_array($transactionType, ['SPRZEDAŻ', 'WYNAJEM'], true)
            ? 'Etap 3a: Dodawanie nieruchomości (wdrożenie od 0.5).'
            : 'Etap 3b: Dodawanie poszukiwania (wdrożenie w 0.6+).';

        self::redirect_to_step(2, $agreementId, 'success', 'Etap 2 zakończony. ' . $next);
    }

    public static function get_agreement(int $agreementId): ?array
    {
        if ($agreementId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eo_agreements';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $agreementId),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function get_linked_clients(int $agreementId): array
    {
        global $wpdb;
        $linkTable = $wpdb->prefix . 'eo_agreement_clients';
        $clientsTable = $wpdb->prefix . 'eo_clients';

        $sql = $wpdb->prepare(
            "SELECT c.* FROM {$linkTable} ac
            INNER JOIN {$clientsTable} c ON c.id = ac.client_id
            WHERE ac.agreement_id = %d
            ORDER BY ac.created_at ASC",
            $agreementId
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function search_clients(string $needle = ''): array
    {
        return EstateOffice_Clients::get_clients($needle);
    }

    private static function attach_client(int $agreementId, int $clientId): void
    {
        if (! self::get_agreement($agreementId)) {
            self::redirect_with_notice('error', 'Nie znaleziono umowy.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eo_agreement_clients';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (agreement_id, client_id, created_at) VALUES (%d, %d, %s)",
                $agreementId,
                $clientId,
                current_time('mysql')
            )
        );
    }

    private static function assert_access(): void
    {
        if (! is_user_logged_in() || ! current_user_can('estateoffice_manage_agreements')) {
            wp_die(esc_html__('Brak uprawnień do zarządzania umowami.', 'estateoffice'));
        }
    }

    private static function redirect_with_notice(string $status, string $message): void
    {
        $pages = get_option('estateoffice_crm_pages', []);
        $agreementsPageId = isset($pages['estateoffice-crm-umowy']) ? (int) $pages['estateoffice-crm-umowy'] : 0;
        $target = $agreementsPageId > 0 ? get_permalink($agreementsPageId) : home_url('/estateoffice-crm-umowy/');

        $redirect = add_query_arg([
            'eo_status' => $status,
            'eo_message' => rawurlencode($message),
        ], $target);

        wp_safe_redirect($redirect);
        exit;
    }

    private static function redirect_to_step(int $step, int $agreementId, string $status, string $message): void
    {
        $pages = get_option('estateoffice_crm_pages', []);
        $agreementsPageId = isset($pages['estateoffice-crm-umowy']) ? (int) $pages['estateoffice-crm-umowy'] : 0;
        $target = $agreementsPageId > 0 ? get_permalink($agreementsPageId) : home_url('/estateoffice-crm-umowy/');

        $redirect = add_query_arg([
            'eo_step' => $step,
            'agreement_id' => $agreementId,
            'eo_status' => $status,
            'eo_message' => rawurlencode($message),
        ], $target);

        wp_safe_redirect($redirect);
        exit;
    }
}
