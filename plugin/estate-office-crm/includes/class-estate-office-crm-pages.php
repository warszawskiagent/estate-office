<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Pages
{
    /** @var array<string, string> */
    private array $tables;
    private bool $frontend_width_style_applied = false;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
    }

    public function register_shortcodes(): void
    {
        add_shortcode('eocrm_crm', [$this, 'render_crm_shortcode']);
        add_shortcode('eocrm_offers', [$this, 'render_offers_shortcode']);
        add_shortcode('eocrm_offer_single', [$this, 'render_offer_single_shortcode']);
        add_shortcode('eocrm_offices_agents', [$this, 'render_offices_agents_shortcode']);
        add_shortcode('eocrm_agent_login', [$this, 'render_agent_login_shortcode']);
        add_action('wp_ajax_eocrm_get_wibor_rates', [$this, 'handle_wibor_rates_ajax']);
        add_action('wp_ajax_nopriv_eocrm_get_wibor_rates', [$this, 'handle_wibor_rates_ajax']);
        if ($this->should_register_public_title_filters()) {
            add_filter('document_title_parts', [$this, 'filter_offer_document_title_parts'], 20, 1);
            add_filter('the_title', [$this, 'filter_offer_page_heading_title'], 20, 2);
        }
    }

    public function handle_frontend_agents_offices_actions(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if (! in_array($action, ['create_agent', 'update_agent', 'create_office', 'update_office'], true)) {
            return;
        }

        if (! is_user_logged_in() || ! $this->can_access_crm()) {
            wp_die(esc_html__('Brak dostepu do Panelu CRM.', 'estate-office-crm'), '', ['response' => 403]);
        }

        if (in_array($action, ['create_office', 'update_office'], true) && ! current_user_can('manage_options')) {
            wp_die(esc_html__('Tylko administrator moze zarzadzac biurami.', 'estate-office-crm'), '', ['response' => 403]);
        }

        if (in_array($action, ['create_agent', 'update_agent'], true) && ! $this->current_user_can_manage_frontend_agents()) {
            wp_die(esc_html__('Brak uprawnien do zarzadzania agentami.', 'estate-office-crm'), '', ['response' => 403]);
        }

        check_admin_referer('eocrm_' . $action);

        if ($action === 'create_agent') {
            $this->handle_frontend_create_agent();
        } elseif ($action === 'update_agent') {
            $this->handle_frontend_update_agent();
        } elseif ($action === 'create_office') {
            $this->handle_frontend_create_office();
        } elseif ($action === 'update_office') {
            $this->handle_frontend_update_office();
        }
    }

    private function current_user_can_manage_frontend_agents(): bool
    {
        return current_user_can('manage_options') || $this->is_current_user_manager();
    }

    private function current_user_can_manage_frontend_offices(): bool
    {
        return current_user_can('manage_options');
    }

    private function handle_frontend_create_agent(): void
    {
        $is_admin_user = current_user_can('manage_options');
        $manager_office_id = $is_admin_user ? 0 : EstateOfficeCRM_Access::get_user_office_id(get_current_user_id(), $this->tables);
        if (! $is_admin_user && $manager_office_id <= 0) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Menedzer musi byc przypisany do biura, aby dodawac agentow.');
        }

        $username = isset($_POST['agent_username']) ? sanitize_user((string) wp_unslash($_POST['agent_username']), true) : '';
        $email = isset($_POST['agent_email']) ? sanitize_email((string) wp_unslash($_POST['agent_email'])) : '';
        $display_name = isset($_POST['agent_display_name']) ? sanitize_text_field((string) wp_unslash($_POST['agent_display_name'])) : '';
        $password_raw = isset($_POST['agent_password']) ? (string) wp_unslash($_POST['agent_password']) : '';
        $crm_role = $is_admin_user
            ? $this->sanitize_frontend_crm_user_role(isset($_POST['agent_role']) ? (string) wp_unslash($_POST['agent_role']) : EstateOfficeCRM_Installer::ROLE_AGENT)
            : EstateOfficeCRM_Installer::ROLE_AGENT;

        if ($username === '' || username_exists($username)) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Niepoprawny login albo login juz istnieje.');
        }

        if (! is_email($email) || email_exists($email)) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Niepoprawny e-mail albo e-mail juz istnieje.');
        }

        if ($display_name === '') {
            $display_name = $username;
        }

        $password = $password_raw !== '' ? $password_raw : wp_generate_password(16, true, true);
        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $display_name,
            'role' => $crm_role,
        ]);

        if (is_wp_error($user_id) || (int) $user_id <= 0) {
            $message = is_wp_error($user_id) ? $user_id->get_error_message() : 'Nie udalo sie utworzyc agenta.';
            $this->redirect_frontend_crm_notice('agents', 'agent_error', $message);
        }

        $office_id = $is_admin_user
            ? $this->normalize_frontend_office_id(isset($_POST['agent_office_id']) ? absint((string) wp_unslash($_POST['agent_office_id'])) : 0)
            : $manager_office_id;
        $photo_id = isset($_POST['agent_photo_id']) ? absint((string) wp_unslash($_POST['agent_photo_id'])) : 0;

        $this->upsert_frontend_agent_profile((int) $user_id, [
            'phone' => isset($_POST['agent_phone']) ? sanitize_text_field((string) wp_unslash($_POST['agent_phone'])) : '',
            'email' => $email,
            'address_line' => isset($_POST['agent_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['agent_address_line'])) : '',
            'city' => isset($_POST['agent_city']) ? sanitize_text_field((string) wp_unslash($_POST['agent_city'])) : '',
            'postal_code' => isset($_POST['agent_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['agent_postal_code'])) : '',
            'bio' => isset($_POST['agent_bio']) ? wp_kses_post((string) wp_unslash($_POST['agent_bio'])) : '',
            'photo_id' => $this->normalize_frontend_image_attachment_id($photo_id),
            'office_id' => $office_id,
            'is_public' => isset($_POST['agent_is_public']) ? 1 : 0,
            'display_order' => isset($_POST['agent_display_order']) ? $this->sanitize_frontend_display_order((string) wp_unslash($_POST['agent_display_order'])) : 100,
        ]);

        $notice = $crm_role === EstateOfficeCRM_Installer::ROLE_MANAGER ? 'Menedzer zostal utworzony.' : 'Agent zostal utworzony.';
        if ($password_raw === '') {
            $notice .= ' Haslo wygenerowano automatycznie.';
        }
        $notice .= $this->send_frontend_onboarding_email_to_user((int) $user_id)
            ? ' E-mail onboardingowy zostal wyslany.'
            : ' E-mail onboardingowy nie zostal wyslany.';

        $this->redirect_frontend_crm_notice('agents', 'agent_created', $notice);
    }

    private function handle_frontend_update_agent(): void
    {
        $user_id = isset($_POST['agent_id']) ? absint((string) wp_unslash($_POST['agent_id'])) : 0;
        if ($user_id <= 0 || ! $this->can_current_user_edit_frontend_agent($user_id)) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Nie mozna edytowac tego agenta.');
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Nie znaleziono agenta.');
        }

        $is_admin_user = current_user_can('manage_options');
        $display_name = isset($_POST['agent_display_name']) ? sanitize_text_field((string) wp_unslash($_POST['agent_display_name'])) : '';
        $email = isset($_POST['agent_email']) ? sanitize_email((string) wp_unslash($_POST['agent_email'])) : '';
        if ($display_name === '') {
            $display_name = (string) $user->display_name;
        }

        if (! is_email($email)) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Niepoprawny e-mail agenta.', ['agent_user_id' => $user_id]);
        }

        $existing_with_email = email_exists($email);
        if ($existing_with_email && (int) $existing_with_email !== $user_id) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', 'Ten e-mail jest juz przypisany do innego uzytkownika.', ['agent_user_id' => $user_id]);
        }

        $result = wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $email,
        ]);

        if (is_wp_error($result)) {
            $this->redirect_frontend_crm_notice('agents', 'agent_error', $result->get_error_message(), ['agent_user_id' => $user_id]);
        }

        $crm_role = $this->extract_frontend_crm_role_from_user($user);
        if ($is_admin_user) {
            $crm_role = $this->sanitize_frontend_crm_user_role(isset($_POST['agent_role']) ? (string) wp_unslash($_POST['agent_role']) : $crm_role);
            foreach ([EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER] as $role_to_clear) {
                if (in_array($role_to_clear, $user->roles, true)) {
                    $user->remove_role($role_to_clear);
                }
            }
            $user->add_role($crm_role);
        }

        $office_id = $is_admin_user
            ? $this->normalize_frontend_office_id(isset($_POST['agent_office_id']) ? absint((string) wp_unslash($_POST['agent_office_id'])) : 0)
            : EstateOfficeCRM_Access::get_user_office_id(get_current_user_id(), $this->tables);
        $photo_id = isset($_POST['agent_photo_id']) ? absint((string) wp_unslash($_POST['agent_photo_id'])) : 0;

        $this->upsert_frontend_agent_profile($user_id, [
            'phone' => isset($_POST['agent_phone']) ? sanitize_text_field((string) wp_unslash($_POST['agent_phone'])) : '',
            'email' => $email,
            'address_line' => isset($_POST['agent_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['agent_address_line'])) : '',
            'city' => isset($_POST['agent_city']) ? sanitize_text_field((string) wp_unslash($_POST['agent_city'])) : '',
            'postal_code' => isset($_POST['agent_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['agent_postal_code'])) : '',
            'bio' => isset($_POST['agent_bio']) ? wp_kses_post((string) wp_unslash($_POST['agent_bio'])) : '',
            'photo_id' => $this->normalize_frontend_image_attachment_id($photo_id),
            'office_id' => $office_id,
            'is_public' => isset($_POST['agent_is_public']) ? 1 : 0,
            'display_order' => isset($_POST['agent_display_order']) ? $this->sanitize_frontend_display_order((string) wp_unslash($_POST['agent_display_order'])) : 100,
        ]);

        $this->redirect_frontend_crm_notice('agents', 'agent_updated', 'Profil agenta zostal zaktualizowany.', ['agent_user_id' => $user_id]);
    }

    private function handle_frontend_create_office(): void
    {
        global $wpdb;

        $office_name = isset($_POST['office_name']) ? sanitize_text_field((string) wp_unslash($_POST['office_name'])) : '';
        $office_email = isset($_POST['office_email']) ? sanitize_email((string) wp_unslash($_POST['office_email'])) : '';
        if ($office_name === '') {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Nazwa biura jest wymagana.');
        }
        if ($office_email !== '' && ! is_email($office_email)) {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Niepoprawny e-mail biura.');
        }

        $logo_id = isset($_POST['office_logo_id']) ? absint((string) wp_unslash($_POST['office_logo_id'])) : 0;
        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            $this->tables['offices'],
            [
                'office_name' => $office_name,
                'office_email' => $office_email,
                'office_phone' => isset($_POST['office_phone']) ? sanitize_text_field((string) wp_unslash($_POST['office_phone'])) : '',
                'address_line' => isset($_POST['office_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['office_address_line'])) : '',
                'city' => isset($_POST['office_city']) ? sanitize_text_field((string) wp_unslash($_POST['office_city'])) : '',
                'postal_code' => isset($_POST['office_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['office_postal_code'])) : '',
                'description' => isset($_POST['office_description']) ? wp_kses_post((string) wp_unslash($_POST['office_description'])) : '',
                'logo_id' => $this->normalize_frontend_image_attachment_id($logo_id),
                'is_active' => 1,
                'is_public' => isset($_POST['office_is_public']) ? 1 : 0,
                'display_order' => isset($_POST['office_display_order']) ? $this->sanitize_frontend_display_order((string) wp_unslash($_POST['office_display_order'])) : 100,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Nie udalo sie utworzyc biura.');
        }

        $this->redirect_frontend_crm_notice('offices', 'office_created', 'Biuro zostalo utworzone.');
    }

    private function handle_frontend_update_office(): void
    {
        global $wpdb;

        $office_id = isset($_POST['office_id']) ? absint((string) wp_unslash($_POST['office_id'])) : 0;
        $office = $this->get_frontend_office_for_edit($office_id);
        if (! is_array($office)) {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Nie znaleziono biura.');
        }

        $office_name = isset($_POST['office_name']) ? sanitize_text_field((string) wp_unslash($_POST['office_name'])) : '';
        $office_email = isset($_POST['office_email']) ? sanitize_email((string) wp_unslash($_POST['office_email'])) : '';
        if ($office_name === '') {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Nazwa biura jest wymagana.', ['office_id' => $office_id]);
        }
        if ($office_email !== '' && ! is_email($office_email)) {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Niepoprawny e-mail biura.', ['office_id' => $office_id]);
        }

        $logo_id = isset($_POST['office_logo_id']) ? absint((string) wp_unslash($_POST['office_logo_id'])) : 0;
        $updated = $wpdb->update(
            $this->tables['offices'],
            [
                'office_name' => $office_name,
                'office_email' => $office_email,
                'office_phone' => isset($_POST['office_phone']) ? sanitize_text_field((string) wp_unslash($_POST['office_phone'])) : '',
                'address_line' => isset($_POST['office_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['office_address_line'])) : '',
                'city' => isset($_POST['office_city']) ? sanitize_text_field((string) wp_unslash($_POST['office_city'])) : '',
                'postal_code' => isset($_POST['office_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['office_postal_code'])) : '',
                'description' => isset($_POST['office_description']) ? wp_kses_post((string) wp_unslash($_POST['office_description'])) : '',
                'logo_id' => $this->normalize_frontend_image_attachment_id($logo_id),
                'is_active' => isset($_POST['office_is_active']) ? 1 : 0,
                'is_public' => isset($_POST['office_is_public']) ? 1 : 0,
                'display_order' => isset($_POST['office_display_order']) ? $this->sanitize_frontend_display_order((string) wp_unslash($_POST['office_display_order'])) : 100,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $office_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            $this->redirect_frontend_crm_notice('offices', 'office_error', 'Nie udalo sie zaktualizowac biura.', ['office_id' => $office_id]);
        }

        $this->redirect_frontend_crm_notice('offices', 'office_updated', 'Biuro zostalo zaktualizowane.', ['office_id' => $office_id]);
    }

    /**
     * @param array<string, scalar|null> $extra_args
     */
    private function redirect_frontend_crm_notice(string $section, string $notice, string $message = '', array $extra_args = []): void
    {
        $target = wp_get_referer();
        if (! is_string($target) || $target === '') {
            $target = $this->resolve_plugin_page_url('crm', 'crm');
        }
        if ($target === '') {
            $target = home_url('/crm/');
        }

        $target = remove_query_arg(['crm_notice', 'crm_message'], $target);
        $args = array_merge(
            [
                'crm' => $section,
                'crm_notice' => $notice,
            ],
            $extra_args
        );
        if ($message !== '') {
            $args['crm_message'] = $message;
        }

        wp_safe_redirect(add_query_arg($args, $target));
        exit;
    }

    public function render_agent_login_shortcode(): string
    {
        wp_enqueue_style('eocrm-frontend');

        $login_panel_url = $this->resolve_plugin_page_url('agent_login', 'panel-logowania-agenta');
        $crm_url = $this->resolve_plugin_page_url('crm', 'crm');
        if ($crm_url === '') {
            $crm_url = admin_url('admin.php?page=eocrm-admin');
        }

        ob_start();

        echo '<section class="eocrm-agent-login-panel" aria-labelledby="eocrm-agent-login-title">';
        echo '<div class="eocrm-agent-login-panel__card">';
        echo '<div class="eocrm-agent-login-panel__intro">';
        echo '<span class="eocrm-agent-login-panel__eyebrow">Estate Office CRM</span>';
        echo '<h2 id="eocrm-agent-login-title">Panel logowania Agenta</h2>';
        echo '<p>Zaloguj si&#281;, aby przej&#347;&#263; do bezpiecznego panelu CRM dla agent&#243;w, mened&#380;er&#243;w i administrator&#243;w biura nieruchomo&#347;ci.</p>';
        echo '</div>';

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $display_name = $current_user instanceof WP_User && $current_user->display_name !== ''
                ? $current_user->display_name
                : (string) ($current_user->user_login ?? '');

            echo '<div class="eocrm-agent-login-panel__status">';
            echo '<strong>Jeste&#347; zalogowany jako:</strong> ' . esc_html($display_name);
            echo '</div>';

            echo '<div class="eocrm-agent-login-panel__actions" style="display:flex !important;align-items:center;gap:14px;flex-wrap:wrap;margin-top:18px;">';
            echo '<a class="eocrm-agent-login-panel__button eocrm-agent-login-panel__button--crm" style="display:inline-flex !important;align-items:center;justify-content:center;min-height:46px;padding:12px 22px;border-radius:999px;background:#0057b8;color:#fff;text-decoration:none;font-weight:800;" href="' . esc_url($crm_url) . '">Przejd&#378; do Panelu CRM</a>';
            echo '<a class="eocrm-agent-login-panel__link" href="' . esc_url(wp_logout_url($login_panel_url !== '' ? $login_panel_url : home_url('/'))) . '">Wyloguj</a>';
            echo '</div>';

            if (! $this->can_access_crm()) {
                echo '<p class="eocrm-agent-login-panel__notice">To konto nie ma uprawnie&#324; do Estate Office CRM. Zaloguj si&#281; kontem Agenta, Mened&#380;era lub Administratora.</p>';
            }
        } else {
            echo '<div class="eocrm-agent-login-panel__form">';
            echo '<form name="eocrm-agent-loginform" action="' . esc_url(wp_login_url()) . '" method="post">';
            echo '<p class="login-username"><label for="eocrm-user-login">Login lub e-mail</label><input type="text" name="log" id="eocrm-user-login" autocomplete="username" required></p>';
            echo '<p class="login-password"><label for="eocrm-user-pass">Has&#322;o</label><input type="password" name="pwd" id="eocrm-user-pass" autocomplete="current-password" required></p>';
            echo '<p class="login-remember"><label><input name="rememberme" type="checkbox" value="forever"> Zapami&#281;taj mnie</label></p>';
            echo '<p class="login-submit eocrm-agent-login-panel__submit-row" style="display:flex !important;align-items:center;margin:14px 0 16px;">';
            echo '<input type="submit" id="eocrm-agent-login-submit" name="wp-submit" class="eocrm-agent-login-panel__button eocrm-agent-login-panel__button--submit" style="display:inline-flex !important;align-items:center;justify-content:center;min-height:46px;min-width:180px;padding:12px 22px;border:0;border-radius:999px;background:#0057b8;color:#fff;cursor:pointer;font-weight:800;" value="Zaloguj">';
            echo '</p>';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr($crm_url) . '">';
            echo '<input type="hidden" name="testcookie" value="1">';
            echo '</form>';
            echo '<a class="eocrm-agent-login-panel__lost" href="' . esc_url(wp_lostpassword_url($login_panel_url !== '' ? $login_panel_url : home_url('/'))) . '">Nie pami&#281;tasz has&#322;a?</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    public function handle_crm_xml_export(): void
    {
        if (is_admin()) {
            return;
        }

        $export_mode = isset($_GET['eocrm_export']) ? sanitize_key((string) wp_unslash($_GET['eocrm_export'])) : '';
        if ($export_mode !== 'xml') {
            return;
        }

        if (! is_user_logged_in() || ! current_user_can('manage_options') || ! $this->can_access_crm()) {
            wp_die('Brak uprawnien do eksportu CRM.', 'Estate Office CRM', ['response' => 403]);
        }

        $nonce = isset($_GET['eocrm_export_nonce']) ? sanitize_text_field((string) wp_unslash($_GET['eocrm_export_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'eocrm_export_xml')) {
            wp_die('Nieprawidlowy token eksportu.', 'Estate Office CRM', ['response' => 403]);
        }

        $section = isset($_GET['crm']) ? sanitize_key((string) wp_unslash($_GET['crm'])) : '';
        $export_table_key = $this->resolve_export_table_key_from_section($section);
        if ($export_table_key === '') {
            wp_die('Wybierz sekcje tabeli CRM do eksportu.', 'Estate Office CRM', ['response' => 400]);
        }

        $xml_payload = $this->build_crm_export_xml($export_table_key);
        if (is_wp_error($xml_payload)) {
            wp_die(
                esc_html($xml_payload->get_error_message()),
                'Estate Office CRM',
                ['response' => 500]
            );
        }

        $filename = 'estate-office-crm-export-' . $export_table_key . '-' . gmdate('Ymd-His') . '.xml';
        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exportowany payload XML jest generowany przez plugin i wysylany jako plik.
        echo $xml_payload;
        exit;
    }

    public function handle_crm_pdf_export(): void
    {
        if (is_admin()) {
            return;
        }

        $export_mode = isset($_GET['eocrm_export']) ? sanitize_key((string) wp_unslash($_GET['eocrm_export'])) : '';
        if ($export_mode !== 'pdf_property') {
            return;
        }

        if (! is_user_logged_in() || ! $this->can_access_crm()) {
            wp_die('Brak uprawnien do eksportu PDF.', 'Estate Office CRM', ['response' => 403]);
        }

        $nonce = isset($_GET['eocrm_export_nonce']) ? sanitize_text_field((string) wp_unslash($_GET['eocrm_export_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'eocrm_export_pdf_property')) {
            wp_die('Nieprawidlowy token eksportu PDF.', 'Estate Office CRM', ['response' => 403]);
        }

        $property_id = isset($_GET['property_id']) ? absint((string) wp_unslash($_GET['property_id'])) : 0;
        if ($property_id <= 0) {
            wp_die('Brak identyfikatora nieruchomosci.', 'Estate Office CRM', ['response' => 400]);
        }

        $property = $this->get_property_profile($property_id);
        if (! is_array($property)) {
            wp_die('Nie znaleziono nieruchomosci lub brak uprawnien.', 'Estate Office CRM', ['response' => 404]);
        }

        $parse_pdf_toggle = static function (string $query_key, bool $default = true): bool {
            if (! isset($_GET[$query_key])) {
                return $default;
            }

            $raw = sanitize_text_field((string) wp_unslash($_GET[$query_key]));
            $key = strtolower(trim($raw));
            if ($key === '') {
                return $default;
            }

            if (in_array($key, ['0', 'false', 'off', 'nie', 'no'], true)) {
                return false;
            }

            if (in_array($key, ['1', 'true', 'on', 'tak', 'yes'], true)) {
                return true;
            }

            return $default;
        };

        $include_owner_contact = $parse_pdf_toggle('pdf_contact', true);
        $include_atuty = $parse_pdf_toggle('pdf_atuty', true);
        $include_udogodnienia = $parse_pdf_toggle('pdf_udogodnienia', true);
        $include_metrics = $parse_pdf_toggle('pdf_metrics', true);
        $include_details = $parse_pdf_toggle('pdf_details', true);
        $include_description = $parse_pdf_toggle('pdf_description', true);
        $include_plans = $parse_pdf_toggle('pdf_plans', true);
        $include_qr = $parse_pdf_toggle('pdf_qr', true);
        $include_offer_link = $parse_pdf_toggle('pdf_offer_link', true);
        $include_features_section = $include_atuty && $include_udogodnienia;
        $watermark_url = $this->get_public_watermark_url();
        $apply_hero_watermark = $include_owner_contact && $watermark_url !== '';

        $owner_user_id = isset($property['owner_user_id']) ? (int) $property['owner_user_id'] : 0;
        $owner_user = get_userdata($owner_user_id);
        $owner_office_logo_url = '';
        $owner_contact_rows = $this->get_agent_contact_rows_for_pdf(
            $owner_user_id,
            $owner_user instanceof WP_User ? $owner_user : null,
            $owner_office_logo_url
        );

        $media = $this->get_property_profile_media($property_id);
        $agreement = null;
        $clients = [];

        $agreement_id = isset($property['agreement_id']) ? (int) $property['agreement_id'] : 0;
        if ($agreement_id > 0) {
            $agreement = $this->get_agreement_profile($agreement_id);
            $clients = $this->get_agreement_profile_clients($agreement_id, 100);
        }

        $currency = strtoupper(trim((string) ($property['price_currency'] ?? 'PLN')));
        if (! in_array($currency, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
            $currency = 'PLN';
        }

        $property_type = strtoupper(trim((string) ($property['property_type'] ?? '')));
        $show_building_no = ! in_array($property_type, ['DOM', 'DZIALKA'], true);
        $street = trim((string) ($property['street'] ?? ''));
        $building_no = trim((string) ($property['building_no'] ?? ''));
        $city = trim((string) ($property['city'] ?? ''));
        $district = trim((string) ($property['district'] ?? ''));
        $county = trim((string) ($property['county'] ?? ''));
        $gmina = trim((string) ($property['gmina'] ?? ''));

        $address = trim($street . ($show_building_no && $building_no !== '' ? (' ' . $building_no) : ''));
        if ($city !== '') {
            $address = $address !== '' ? ($address . ', ' . $city) : $city;
        }
        if ($address === '') {
            $address = '-';
        }

        $location = trim($city . ($district !== '' ? ' / ' . $district : ''));
        if ($location === '') {
            $location = '-';
        }

        $normalize_money = static function ($value, string $currency_code): string {
            if (! is_numeric($value)) {
                return '';
            }

            return number_format((float) $value, 0, ',', ' ') . ' ' . $currency_code;
        };

        $price_per_area = static function ($value, string $currency_code, string $target_unit = 'm2'): string {
            return EstateOfficeCRM_Units::format_price_per_area($value, $currency_code, $target_unit);
        };

        $render_list = static function ($items): string {
            if (! is_array($items) || empty($items)) {
                return '';
            }

            $clean = [];
            foreach ($items as $item) {
                $item = trim(sanitize_text_field((string) $item));
                if ($item !== '') {
                    $clean[] = $item;
                }
            }

            return empty($clean) ? '' : implode(', ', $clean);
        };

        $add_row = static function (array &$rows, string $label, $value): void {
            if ($value === null) {
                return;
            }
            if (is_bool($value)) {
                $value = $value ? 'Tak' : 'Nie';
            }
            if (is_numeric($value)) {
                $value = (string) $value;
            }
            $value = trim((string) $value);
            if ($value === '' || $value === '-') {
                return;
            }
            $rows[] = ['label' => $label, 'value' => $value];
        };

        $summary_payload = [
            'transaction_type' => (string) ($property['transaction_type'] ?? ''),
            'address' => $address,
            'price' => $normalize_money($property['price'] ?? null, $currency),
            'location' => $location,
            'property_type' => (string) ($property['property_type'] ?? ''),
            'offer_number' => (string) ($property['offer_number'] ?? ''),
        ];

        $metrics_rows = [];
        $details_rows = [];
        $features_rows = [];
        $amenities_items = [];
        if (! empty($property['amenities']) && is_array($property['amenities'])) {
            foreach ($property['amenities'] as $amenity_item) {
                $amenity_item = trim(sanitize_text_field((string) $amenity_item));
                if ($amenity_item !== '') {
                    $amenities_items[] = $amenity_item;
                }
            }
        }
        $equipment_items = [];
        if (! empty($property['equipment']) && is_array($property['equipment'])) {
            foreach ($property['equipment'] as $equipment_item) {
                $equipment_item = trim(sanitize_text_field((string) $equipment_item));
                if ($equipment_item !== '') {
                    $equipment_items[] = $equipment_item;
                }
            }
        }

        $unit_settings = EstateOfficeCRM_Units::load_settings(function (string $key): string {
            return $this->get_setting_value($key);
        });
        $area_unit = EstateOfficeCRM_Units::get_area_unit_for_property($property_type, $unit_settings);
        $area_unit_label = EstateOfficeCRM_Units::unit_label($area_unit);
        $area_text = EstateOfficeCRM_Units::format_area_value($property['area'] ?? null, $area_unit);
        if ($property_type !== 'DZIALKA') {
            $add_row($metrics_rows, 'Metraz', $area_text !== '' ? ($area_text . ' ' . $area_unit_label) : '');
        }

        $plot_area_text = '';
        if ($property_type === 'DOM' && isset($property['plot_area']) && is_numeric((string) $property['plot_area'])) {
            $plot_area_text = EstateOfficeCRM_Units::format_area_value($property['plot_area'], $area_unit);
        } elseif ($property_type === 'DZIALKA' && isset($property['area']) && is_numeric((string) $property['area'])) {
            $plot_area_text = EstateOfficeCRM_Units::format_area_value($property['area'], $area_unit);
        }
        if ($plot_area_text !== '') {
            $add_row($metrics_rows, 'Wielkosc dzialki', $plot_area_text . ' ' . $area_unit_label);
        }

        $add_row($metrics_rows, 'Pokoje', $property['rooms'] ?? null);
        $add_row($metrics_rows, 'Sypialnie', $property['bedrooms'] ?? null);
        $add_row($metrics_rows, 'Lazienki', $property['bathrooms'] ?? null);
        $add_row($metrics_rows, 'Toalety', $property['toilets'] ?? null);
        $add_row($metrics_rows, 'Pietro', $property['floor_no'] ?? null);
        $add_row($metrics_rows, 'Liczba pieter', $property['floors_total'] ?? null);

        $add_row($details_rows, 'Cena za m2', $price_per_area($property['price_per_m2'] ?? null, $currency, $area_unit));
        if ($property_type !== 'DZIALKA') {
            $add_row($details_rows, 'Czynsz', $property['admin_rent'] ?? null);
            $add_row($details_rows, 'Rok budowy', $property['year_built'] ?? null);
        }
        $add_row($details_rows, 'Stan prawny', $property['legal_status'] ?? '');
        $add_row($details_rows, 'Numer KW', ! empty($property['no_land_registry']) ? 'Brak KW' : (string) ($property['land_registry_no'] ?? ''));
        $add_row($details_rows, 'Powiat', $county);
        $add_row($details_rows, 'Gmina', $gmina);
        if ($include_owner_contact) {
            $add_row($details_rows, 'Opiekun', $owner_user instanceof WP_User ? (string) $owner_user->display_name : '');
        }

        if ($include_features_section) {
            $add_row($features_rows, 'Udogodnienia', implode(', ', $amenities_items));
            $add_row($features_rows, 'Wyposazenie', implode(', ', $equipment_items));
        }

        if (is_array($agreement)) {
            $add_row($details_rows, 'Numer umowy', $agreement['agreement_number'] ?? '');
            $add_row($details_rows, 'Etap umowy', $agreement['current_stage'] ?? '');
        }

        $public_offer_url = $this->get_property_export_page_url($property_id);
        if ($include_offer_link && $public_offer_url !== '') {
            $add_row($features_rows, 'Link oferty WWW', $public_offer_url);
        }

        $resolve_media_url = static function (array $media_row, array $sizes = ['large']): string {
            $attachment_id = isset($media_row['attachment_id']) ? absint((string) $media_row['attachment_id']) : 0;
            if ($attachment_id > 0) {
                foreach ($sizes as $size) {
                    $size = trim((string) $size);
                    if ($size === '') {
                        continue;
                    }
                    $attachment_url = wp_get_attachment_image_url($attachment_id, $size);
                    if (is_string($attachment_url) && $attachment_url !== '') {
                        return $attachment_url;
                    }
                }

                $attachment_original_url = wp_get_attachment_url($attachment_id);
                if (is_string($attachment_original_url) && $attachment_original_url !== '') {
                    return esc_url_raw($attachment_original_url);
                }
            }

            $raw_url = isset($media_row['media_url']) ? trim((string) $media_row['media_url']) : '';
            return $raw_url !== '' ? esc_url_raw($raw_url) : '';
        };

        $hero_image_url = '';
        if (! empty($media['photos']) && is_array($media['photos'])) {
            foreach ($media['photos'] as $photo_row) {
                if (! is_array($photo_row)) {
                    continue;
                }
                $photo_url = $resolve_media_url($photo_row, ['large', 'medium_large', 'medium', 'full']);
                if ($photo_url !== '') {
                    $hero_image_url = $photo_url;
                    break;
                }
            }
        }

        $floor_plan_labels_by_position = [];
        $floor_plan_meta = isset($property['media']['floor_plans']) && is_array($property['media']['floor_plans']) ? $property['media']['floor_plans'] : [];
        foreach ($floor_plan_meta as $meta_index => $meta_row) {
            if (! is_array($meta_row)) {
                continue;
            }
            $label = trim((string) ($meta_row['label'] ?? ''));
            if ($label !== '') {
                $floor_plan_labels_by_position[(int) $meta_index] = $label;
            }
        }

        $floor_plan_payload = [];
        if (! empty($media['floor_plans']) && is_array($media['floor_plans'])) {
            foreach ($media['floor_plans'] as $floor_index => $floor_row) {
                if (! is_array($floor_row)) {
                    continue;
                }
                $floor_url = $resolve_media_url($floor_row, ['large', 'medium_large', 'medium', 'thumbnail', 'full']);
                if ($floor_url === '') {
                    continue;
                }

                $floor_label = trim((string) ($floor_row['label'] ?? ''));
                if ($floor_label === '' && isset($floor_plan_labels_by_position[(int) $floor_index])) {
                    $floor_label = (string) $floor_plan_labels_by_position[(int) $floor_index];
                }
                if ($floor_label === '') {
                    $floor_label = 'Poziom ' . (string) ((int) $floor_index + 1);
                }

                $fallback_floor_url = $resolve_media_url($floor_row, ['thumbnail', 'medium', 'large', 'full']);
                if ($fallback_floor_url === $floor_url) {
                    $fallback_floor_url = '';
                }

                $floor_plan_payload[] = [
                    'label' => $floor_label,
                    'url' => $floor_url,
                    'fallback_url' => $fallback_floor_url,
                ];
            }
        }

        if (empty($floor_plan_payload)) {
            $floor2d = isset($media['floor_2d']) && is_array($media['floor_2d']) ? $media['floor_2d'] : null;
            $floor3d = isset($media['floor_3d']) && is_array($media['floor_3d']) ? $media['floor_3d'] : null;
            if (is_array($floor2d)) {
                $floor2d_url = $resolve_media_url($floor2d, ['large', 'medium_large', 'medium', 'thumbnail', 'full']);
                if ($floor2d_url !== '') {
                    $floor_plan_payload[] = ['label' => 'Poziom 1', 'url' => $floor2d_url];
                }
            }
            if (is_array($floor3d)) {
                $floor3d_url = $resolve_media_url($floor3d, ['large', 'medium_large', 'medium', 'thumbnail', 'full']);
                if ($floor3d_url !== '') {
                    $floor_plan_payload[] = ['label' => 'Poziom 2', 'url' => $floor3d_url];
                }
            }
        }

        $first_gallery_photo_url = '';
        if (! empty($media['photos']) && is_array($media['photos'])) {
            foreach ($media['photos'] as $photo_row) {
                if (! is_array($photo_row)) {
                    continue;
                }
                $candidate = $resolve_media_url($photo_row, ['large', 'medium_large', 'medium', 'thumbnail', 'full']);
                if ($candidate !== '') {
                    $first_gallery_photo_url = $candidate;
                    break;
                }
            }
        }

        $property_media_data = isset($property['media']) && is_array($property['media']) ? $property['media'] : [];
        $virtual_tour_link = isset($property_media_data['virtual_tour_link']) ? esc_url_raw((string) $property_media_data['virtual_tour_link']) : '';
        $video_link = isset($property_media_data['video_link']) ? esc_url_raw((string) $property_media_data['video_link']) : '';
        $multimedia_direct_url = $virtual_tour_link !== '' ? $virtual_tour_link : $video_link;
        $has_public_offer = $public_offer_url !== '';
        $has_multimedia = $multimedia_direct_url !== '';

        $gallery_qr_url = '';
        $multimedia_qr_url = '';
        $offer_qr_url = '';

        if ($include_qr && $has_public_offer && $has_multimedia) {
            if ($first_gallery_photo_url !== '') {
                $gallery_qr_url = add_query_arg(
                    [
                        'eocrm_gallery' => '1',
                        'eocrm_gallery_index' => 0,
                    ],
                    $public_offer_url
                );
            }

            $multimedia_qr_url = $multimedia_direct_url;
            $offer_qr_url = $public_offer_url;
        }

        $qr_items = [];
        if ($gallery_qr_url !== '') {
            $qr_items[] = [
                'label' => 'Pelna galeria zdjec',
                'url' => $gallery_qr_url,
            ];
        }
        if ($multimedia_qr_url !== '') {
            $qr_items[] = [
                'label' => 'Multimedia',
                'url' => $multimedia_qr_url,
            ];
        }
        if ($offer_qr_url !== '') {
            $qr_items[] = [
                'label' => 'Oferta WWW',
                'url' => $offer_qr_url,
            ];
        }

        $rows = [];
        $add_row($rows, 'Adres', $address);
        $add_row($rows, 'Typ transakcji', $property['transaction_type'] ?? '');
        $add_row($rows, 'Rodzaj nieruchomosci', $property['property_type'] ?? '');
        $add_row($rows, 'Cena', $summary_payload['price'] ?? '');
        foreach ($metrics_rows as $row) {
            $rows[] = $row;
        }
        foreach ($details_rows as $row) {
            $rows[] = $row;
        }
        foreach ($features_rows as $row) {
            $rows[] = $row;
        }

        $pdf_template_variant = $this->get_setting_value('pdf_template_variant');
        if (! in_array($pdf_template_variant, ['pdf_v1'], true)) {
            $pdf_template_variant = 'pdf_v1';
        }
        $rows[] = ['label' => 'Szablon PDF', 'value' => strtoupper($pdf_template_variant)];

        $pdf_title = $address !== '-' ? $address : ('Oferta ' . (string) ($property['offer_number'] ?? $property_id));
        $pdf_payload = EstateOfficeCRM_PDF::build_property_offer_pdf([
            'title' => $pdf_title,
            'offer_number' => (string) ($property['offer_number'] ?? ''),
            'generated_at' => current_time('Y-m-d H:i:s'),
            'rows' => $rows,
            'summary' => $summary_payload,
            'metrics_rows' => $metrics_rows,
            'details_rows' => $details_rows,
            'features_rows' => $features_rows,
            'amenities_items' => $amenities_items,
            'equipment_items' => $equipment_items,
            'offer_public_url' => $include_offer_link ? $public_offer_url : '',
            'description' => (string) ($property['description'] ?? ''),
            'hero_image_url' => $hero_image_url,
            'floor_plans' => $floor_plan_payload,
            'show_owner_contact' => $include_owner_contact,
            'show_features_section' => $include_features_section,
            'show_metrics_section' => $include_metrics,
            'show_details_section' => $include_details,
            'show_description_section' => $include_description,
            'show_plans_section' => $include_plans,
            'show_qr_section' => $include_qr,
            'show_offer_link' => $include_offer_link,
            'owner_contact_rows' => $owner_contact_rows,
            'owner_office_logo_url' => $owner_office_logo_url,
            'qr_items' => $qr_items,
            'watermark_image_url' => $watermark_url,
            'apply_hero_watermark' => $apply_hero_watermark,
        ]);

        $file_slug = sanitize_file_name((string) ($property['offer_number'] ?? 'oferta-' . $property_id));
        if ($file_slug === '') {
            $file_slug = 'oferta-' . $property_id;
        }
        $filename = 'estate-office-crm-' . $file_slug . '.pdf';

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exportowany payload PDF jest binarny i wysylany jako plik.
        echo $pdf_payload;
        exit;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function get_agent_contact_rows_for_pdf(int $owner_user_id, ?WP_User $owner_user, string &$office_logo_url = ''): array
    {
        global $wpdb;

        $rows = [];
        $office_logo_url = '';

        $add = static function (array &$target, string $label, string $value): void {
            $value = trim(sanitize_text_field($value));
            if ($value === '') {
                return;
            }
            $target[] = [
                'label' => $label,
                'value' => $value,
            ];
        };

        if ($owner_user instanceof WP_User) {
            $add($rows, 'Imie i nazwisko', (string) $owner_user->display_name);
        }

        if ($owner_user_id <= 0) {
            return $rows;
        }

        $agent_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    p.phone,
                    p.email,
                    p.city,
                    o.office_name,
                    o.logo_id
                FROM {$this->tables['agent_profiles']} p
                LEFT JOIN {$this->tables['offices']} o ON o.id = p.office_id
                WHERE p.user_id = %d
                LIMIT 1",
                $owner_user_id
            ),
            ARRAY_A
        );

        if (! is_array($agent_row)) {
            return $rows;
        }

        $add($rows, 'Biuro', (string) ($agent_row['office_name'] ?? ''));
        $add($rows, 'Lokalizacja', (string) ($agent_row['city'] ?? ''));
        $add($rows, 'Telefon', (string) ($agent_row['phone'] ?? ''));

        $office_logo_id = isset($agent_row['logo_id']) ? absint((string) $agent_row['logo_id']) : 0;
        if ($office_logo_id > 0) {
            $resolved_logo_url = wp_get_attachment_image_url($office_logo_id, 'medium_large');
            if (! is_string($resolved_logo_url) || $resolved_logo_url === '') {
                $resolved_logo_url = wp_get_attachment_image_url($office_logo_id, 'large');
            }
            if (! is_string($resolved_logo_url) || $resolved_logo_url === '') {
                $resolved_logo_url = wp_get_attachment_url($office_logo_id);
            }
            if (is_string($resolved_logo_url) && $resolved_logo_url !== '') {
                $office_logo_url = esc_url_raw($resolved_logo_url);
            }
        }

        $email = sanitize_email((string) ($agent_row['email'] ?? ''));
        if ($email === '' && $owner_user instanceof WP_User) {
            $email = sanitize_email((string) $owner_user->user_email);
        }
        $add($rows, 'E-mail', $email);

        return $rows;
    }

    private function resolve_export_table_key_from_section(string $section): string
    {
        $map = [
            'properties' => 'properties',
            'searches' => 'searches',
            'agreements' => 'agreements',
            'clients' => 'clients',
        ];

        return isset($map[$section]) ? (string) $map[$section] : '';
    }

    /**
     * @return string|WP_Error
     */
    private function build_crm_export_xml(string $table_key)
    {
        global $wpdb;

        $table_name = isset($this->tables[$table_key]) ? (string) $this->tables[$table_key] : '';
        if ($table_name === '') {
            return new WP_Error('eocrm_xml_export', 'Nieznana tabela eksportu: ' . $table_key);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name comes from strict internal whitelist in resolve_export_table_key_from_section().
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id ASC", ARRAY_A);
        if (! is_array($rows)) {
            return new WP_Error('eocrm_xml_export', 'Nie udalo sie odczytac tabeli: ' . $table_key);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name comes from strict internal whitelist in resolve_export_table_key_from_section().
        $columns_meta_rows = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
        if (! is_array($columns_meta_rows)) {
            $columns_meta_rows = [];
        }

        $ordered_columns = [];
        $columns_meta = [];
        foreach ($columns_meta_rows as $column_meta_row) {
            if (! is_array($column_meta_row)) {
                continue;
            }

            $column_name = isset($column_meta_row['Field']) ? trim((string) $column_meta_row['Field']) : '';
            if ($column_name === '') {
                continue;
            }

            $ordered_columns[] = $column_name;
            $columns_meta[$column_name] = [
                'type' => isset($column_meta_row['Type']) ? (string) $column_meta_row['Type'] : '',
                'nullable' => isset($column_meta_row['Null']) && strtoupper((string) $column_meta_row['Null']) === 'YES' ? 'yes' : 'no',
                'default' => array_key_exists('Default', $column_meta_row) ? $column_meta_row['Default'] : null,
                'key' => isset($column_meta_row['Key']) ? (string) $column_meta_row['Key'] : '',
                'extra' => isset($column_meta_row['Extra']) ? (string) $column_meta_row['Extra'] : '',
            ];
        }

        if (empty($ordered_columns) && ! empty($rows)) {
            $ordered_columns = array_map(
                static function ($column_name): string {
                    return (string) $column_name;
                },
                array_keys($rows[0])
            );
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $row_column_name => $_row_column_value) {
                $row_column_name = (string) $row_column_name;
                if (! in_array($row_column_name, $ordered_columns, true)) {
                    $ordered_columns[] = $row_column_name;
                }
            }
        }

        $escape = static function (string $value): string {
            return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        };

        $to_text = static function ($value): string {
            if ($value === null) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_scalar($value)) {
                return (string) $value;
            }
            $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        };

        $column_to_tag = static function (string $column_name): string {
            $tag = strtolower(trim($column_name));
            $tag = preg_replace('/[^a-z0-9_-]/', '_', $tag);
            if (! is_string($tag)) {
                $tag = '';
            }
            $tag = trim($tag, '_');
            if ($tag === '' || preg_match('/^[^a-z_]/', $tag)) {
                $tag = 'col_' . $tag;
            }
            $tag = preg_replace('/_+/', '_', $tag);
            if (! is_string($tag) || $tag === '') {
                return 'col';
            }
            return $tag;
        };

        $column_tags = [];
        $used_tags = [];
        foreach ($ordered_columns as $column_name) {
            $base_tag = $column_to_tag((string) $column_name);
            $tag = $base_tag;
            $suffix = 2;
            while (isset($used_tags[$tag])) {
                $tag = $base_tag . '_' . (string) $suffix;
                $suffix++;
            }
            $used_tags[$tag] = true;
            $column_tags[(string) $column_name] = $tag;
        };

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<eocrm_export generated_at_gmt="' . $escape(gmdate('c')) . '" site_url="' . $escape((string) home_url('/')) . '" version="' . $escape((string) EOCRM_VERSION) . '" scope="single_table" format="tabular_columns_v2">';
        $xml[] = '  <table name="' . $escape((string) $table_key) . '" db_table="' . $escape($table_name) . '" rows="' . (string) count($rows) . '" columns="' . (string) count($ordered_columns) . '">';
        $xml[] = '    <columns>';
        foreach ($ordered_columns as $column_name) {
            $column_name = (string) $column_name;
            $column_tag = isset($column_tags[$column_name]) ? (string) $column_tags[$column_name] : 'col';
            $meta = isset($columns_meta[$column_name]) && is_array($columns_meta[$column_name]) ? $columns_meta[$column_name] : [];
            $column_type = isset($meta['type']) ? (string) $meta['type'] : '';
            $column_nullable = isset($meta['nullable']) ? (string) $meta['nullable'] : '';
            $column_default = array_key_exists('default', $meta) && $meta['default'] !== null ? (string) $meta['default'] : '';
            $column_key = isset($meta['key']) ? (string) $meta['key'] : '';
            $column_extra = isset($meta['extra']) ? (string) $meta['extra'] : '';

            $xml[] = '      <column name="' . $escape($column_name) . '" tag="' . $escape($column_tag) . '" type="' . $escape($column_type) . '" nullable="' . $escape($column_nullable) . '" default="' . $escape($column_default) . '" key="' . $escape($column_key) . '" extra="' . $escape($column_extra) . '" />';
        }
        $xml[] = '    </columns>';
        $xml[] = '    <records>';
        foreach ($rows as $row_index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $xml[] = '      <record row_no="' . (string) ((int) $row_index + 1) . '">';
            foreach ($ordered_columns as $column_name) {
                $column_name = (string) $column_name;
                $column_tag = isset($column_tags[$column_name]) ? (string) $column_tags[$column_name] : 'col';
                $column_value = array_key_exists($column_name, $row) ? $row[$column_name] : null;
                if ($column_value === null) {
                    $xml[] = '        <' . $column_tag . ' is_null="1" />';
                    continue;
                }
                $xml[] = '        <' . $column_tag . '>' . $escape((string) $to_text($column_value)) . '</' . $column_tag . '>';
            }
            $xml[] = '      </record>';
        }
        $xml[] = '    </records>';
        $xml[] = '  </table>';
        $xml[] = '</eocrm_export>';
        return implode("\n", $xml);
    }

    public function render_crm_shortcode(array $atts = []): string
    {
        if (! is_user_logged_in()) {
            return $this->translate_output('<div class="eocrm-alert">Aby korzystac z CRM, musisz byc zalogowany.</div>');
        }

        if (! $this->can_access_crm()) {
            return $this->translate_output('<div class="eocrm-alert">Nie masz uprawnien do modulu CRM.</div>');
        }

        $atts = shortcode_atts(['section' => 'dashboard'], $atts, 'eocrm_crm');
        $section = isset($_GET['crm']) ? sanitize_key((string) wp_unslash($_GET['crm'])) : sanitize_key((string) $atts['section']);

        $can_manage_frontend_offices = $this->current_user_can_manage_frontend_offices();
        $can_manage_frontend_agents = $this->current_user_can_manage_frontend_agents();
        $allowed_sections = ['dashboard', 'properties', 'searches', 'agreements', 'transactions', 'clients'];
        if ($can_manage_frontend_offices) {
            $allowed_sections[] = 'offices';
        }
        if ($can_manage_frontend_agents) {
            $allowed_sections[] = 'agents';
        }
        if (! in_array($section, $allowed_sections, true)) {
            $section = 'dashboard';
        }

        $search = isset($_GET['q']) ? sanitize_text_field((string) wp_unslash($_GET['q'])) : '';

        $clients_rows = $section === 'clients' ? $this->list_clients($search, 250) : [];
        $agreements_rows = $section === 'agreements' ? $this->list_agreements($search, 250) : [];
        $transactions_rows = $section === 'transactions' ? $this->list_transactions($search, 250) : [];
        $properties_rows = $section === 'properties' ? $this->list_properties($search, 250) : [];
        if (! empty($properties_rows)) {
            $property_ids_for_thumbs = [];
            foreach ($properties_rows as $properties_row_for_thumb) {
                $property_ids_for_thumbs[] = (int) ($properties_row_for_thumb['id'] ?? 0);
            }
            $properties_thumb_urls = $this->get_primary_photo_urls($property_ids_for_thumbs);
            foreach ($properties_rows as &$properties_row_to_enrich) {
                $properties_row_id = (int) ($properties_row_to_enrich['id'] ?? 0);
                $properties_row_to_enrich['primary_photo_url'] = isset($properties_thumb_urls[$properties_row_id])
                    ? (string) $properties_thumb_urls[$properties_row_id]
                    : '';
            }
            unset($properties_row_to_enrich);
        }
        $searches_rows = $section === 'searches' ? $this->list_searches($search, 250) : [];
        $frontend_offices_rows = $section === 'offices' ? $this->get_frontend_manageable_offices(true) : [];
        $frontend_agents_rows = $section === 'agents' ? $this->get_frontend_manageable_agents() : [];
        $frontend_agent_edit_id = $section === 'agents' ? absint((string) (isset($_GET['agent_user_id']) ? wp_unslash($_GET['agent_user_id']) : '0')) : 0;
        $frontend_office_edit_id = $section === 'offices' ? absint((string) (isset($_GET['office_id']) ? wp_unslash($_GET['office_id']) : '0')) : 0;
        $frontend_agent_edit = $frontend_agent_edit_id > 0 ? $this->get_frontend_agent_for_edit($frontend_agent_edit_id) : null;
        $frontend_office_edit = $frontend_office_edit_id > 0 ? $this->get_frontend_office_for_edit($frontend_office_edit_id) : null;
        $frontend_office_options = ($section === 'agents' || $section === 'offices') ? $this->get_frontend_manageable_offices(true) : [];
        if (in_array($section, ['agents', 'offices'], true)) {
            wp_enqueue_media();
        }

        $client_id = $section === 'clients' ? absint((string) (isset($_GET['client_id']) ? wp_unslash($_GET['client_id']) : '0')) : 0;
        $agreement_id = $section === 'agreements' ? absint((string) (isset($_GET['agreement_id']) ? wp_unslash($_GET['agreement_id']) : '0')) : 0;
        $transaction_id = $section === 'transactions' ? absint((string) (isset($_GET['transaction_id']) ? wp_unslash($_GET['transaction_id']) : '0')) : 0;
        $property_id = $section === 'properties' ? absint((string) (isset($_GET['property_id']) ? wp_unslash($_GET['property_id']) : '0')) : 0;
        $search_id = $section === 'searches' ? absint((string) (isset($_GET['search_id']) ? wp_unslash($_GET['search_id']) : '0')) : 0;

        $client_profile = null;
        $client_profile_addresses = ['main' => null, 'correspondence' => null];
        $client_profile_agreements = [];
        $client_profile_properties = [];
        $client_profile_searches = [];

        $agreement_profile = null;
        $agreement_profile_stage_history = [];
        $agreement_profile_clients = [];
        $agreement_profile_properties = [];
        $agreement_profile_searches = [];
        $agreement_profile_transactions = [];

        $transaction_profile = null;
        $transaction_form_agreement = null;
        $transaction_form_properties = [];
        $transaction_agent_options = [];

        $property_profile = null;
        $property_profile_media = ['photos' => [], 'floor_plans' => [], 'floor_2d' => null, 'floor_3d' => null];
        $property_profile_clients = [];
        $property_profile_agreement = null;
        $property_portal_export_status = null;

        $search_profile = null;
        $search_profile_clients = [];
        $search_profile_agreement = null;

        if ($section === 'clients' && $client_id > 0) {
            $client_profile = $this->get_client_profile($client_id);
            if (is_array($client_profile)) {
                $client_profile_addresses = $this->get_client_profile_addresses($client_id);
                $client_profile_agreements = $this->get_client_profile_agreements($client_id, 200);
                $client_profile_properties = $this->get_client_profile_properties($client_id, 200);
                $client_profile_searches = $this->get_client_profile_searches($client_id, 200);
            }
        }

        if ($section === 'agreements' && $agreement_id > 0) {
            $agreement_profile = $this->get_agreement_profile($agreement_id);
            if (is_array($agreement_profile)) {
                $agreement_profile_stage_history = $this->get_agreement_stage_history($agreement_id, 200);
                $agreement_profile_clients = $this->get_agreement_profile_clients($agreement_id, 200);
                $agreement_profile_properties = $this->get_agreement_profile_properties($agreement_id, 200);
                $agreement_profile_searches = $this->get_agreement_profile_searches($agreement_id, 200);
                $agreement_profile_transactions = $this->get_agreement_profile_transactions($agreement_id, 200);

                if (! empty($agreement_profile_properties)) {
                    $agreement_property_ids_for_thumbs = [];
                    foreach ($agreement_profile_properties as $agreement_property_for_thumb) {
                        $agreement_property_ids_for_thumbs[] = (int) ($agreement_property_for_thumb['id'] ?? 0);
                    }
                    $agreement_property_thumb_urls = $this->get_primary_photo_urls($agreement_property_ids_for_thumbs);
                    foreach ($agreement_profile_properties as &$agreement_property_to_enrich) {
                        $agreement_property_id_enrich = (int) ($agreement_property_to_enrich['id'] ?? 0);
                        $agreement_property_to_enrich['primary_photo_url'] = isset($agreement_property_thumb_urls[$agreement_property_id_enrich])
                            ? (string) $agreement_property_thumb_urls[$agreement_property_id_enrich]
                            : '';
                    }
                    unset($agreement_property_to_enrich);
                }
            }
        }

        if ($section === 'transactions' && $transaction_id > 0) {
            $transaction_profile = $this->get_transaction_profile($transaction_id);
        }

        if ($section === 'properties' && $property_id > 0) {
            $property_profile = $this->get_property_profile($property_id);
            if (is_array($property_profile)) {
                $property_profile_media = $this->get_property_profile_media($property_id);
                $property_portal_export_status = $this->get_property_portal_export_status($property_id);
                $profile_agreement_id = isset($property_profile['agreement_id']) ? (int) $property_profile['agreement_id'] : 0;
                if ($profile_agreement_id > 0) {
                    $property_profile_clients = $this->get_agreement_profile_clients($profile_agreement_id, 200);
                    $property_profile_agreement = $this->get_agreement_profile($profile_agreement_id);
                }
            }
        }

        // Stage timeline for property profile (pulled from linked agreement).
        $property_profile_agreement_stages = [];
        $property_profile_agreement_stage_history = [];
        if ($section === 'properties' && is_array($property_profile_agreement)) {
            $property_profile_agreement_stages = EstateOfficeCRM_Stages::load_for_transaction(
                (string) ($property_profile_agreement['transaction_type'] ?? ''),
                function (string $key): string {
                    return $this->get_setting_value($key);
                }
            );
            $profile_agreement_id_for_history = (int) ($property_profile_agreement['id'] ?? 0);
            if ($profile_agreement_id_for_history > 0) {
                $property_profile_agreement_stage_history = $this->get_agreement_stage_history($profile_agreement_id_for_history, 200);
            }
        }

        if ($section === 'searches' && $search_id > 0) {
            $search_profile = $this->get_search_profile($search_id);
            if (is_array($search_profile)) {
                $search_profile_clients = $this->get_search_profile_clients($search_id, 200);
                $profile_agreement_id = isset($search_profile['agreement_id']) ? (int) $search_profile['agreement_id'] : 0;
                if ($profile_agreement_id > 0) {
                    $search_profile_agreement = $this->get_search_profile_agreement($profile_agreement_id);
                }
            }
        }

        // Stage timeline for search profile (pulled from linked agreement).
        $search_profile_agreement_stages = [];
        $search_profile_agreement_stage_history = [];
        if ($section === 'searches' && is_array($search_profile_agreement)) {
            $search_profile_agreement_stages = EstateOfficeCRM_Stages::load_for_transaction(
                (string) ($search_profile_agreement['transaction_type'] ?? ''),
                function (string $key): string {
                    return $this->get_setting_value($key);
                }
            );
            $search_agreement_id_for_history = (int) ($search_profile_agreement['id'] ?? 0);
            if ($search_agreement_id_for_history > 0) {
                $search_profile_agreement_stage_history = $this->get_agreement_stage_history($search_agreement_id_for_history, 200);
            }
        }

        $is_admin_user = current_user_can('manage_options');
        $current_user_id = get_current_user_id();
        $owner_scope = $this->owner_scope('owner_user_id');
        $manager_search_scope = $this->is_current_user_manager()
            ? $this->owner_scope('owner_user_id')
            : ['sql' => '', 'params' => []];

        $dashboard_stats = [
            'properties' => $this->count_records(
                $this->tables['properties'],
                'is_active = 1 AND export_www = 1 AND is_sold = 0 AND is_rented = 0' . $owner_scope['sql'],
                $owner_scope['params']
            ),
            'agreements' => $this->count_records(
                $this->tables['agreements'],
                'is_active = 1 AND ' . $this->agreement_not_finished_sql('current_stage') . $owner_scope['sql'],
                $owner_scope['params']
            ),
            'searches' => $this->count_active_searches($manager_search_scope['sql'], $manager_search_scope['params']),
            'clients' => $this->count_records($this->tables['clients'], 'is_active = 1' . $owner_scope['sql'], $owner_scope['params']),
        ];

        // Role-aware dashboard data (Agent / Manager / Administrator).
        $dashboard_role = 'agent';
        if ($is_admin_user) {
            $dashboard_role = 'admin';
        } elseif ($this->is_current_user_manager()) {
            $dashboard_role = 'manager';
        }

        $current_user_object = $current_user_id > 0 ? get_userdata($current_user_id) : null;
        $dashboard_user_first_name = '';
        $dashboard_user_display_name = '';
        if ($current_user_object instanceof WP_User) {
            $dashboard_user_first_name = trim((string) $current_user_object->first_name);
            $dashboard_user_display_name = trim((string) $current_user_object->display_name);
            if ($dashboard_user_first_name === '') {
                $dashboard_user_first_name = $dashboard_user_display_name;
            }
        }

        $dashboard_office_name = '';
        $dashboard_office_user_ids = [];
        if (in_array($dashboard_role, ['agent', 'manager'], true) && $current_user_id > 0) {
            $office_id_for_user = EstateOfficeCRM_Access::get_user_office_id($current_user_id, $this->tables);
            if ($office_id_for_user > 0) {
                global $wpdb;
                $offices_table = $this->tables['offices'];
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared, table from internal map.
                $office_row = $wpdb->get_row($wpdb->prepare("SELECT office_name FROM {$offices_table} WHERE id = %d LIMIT 1", $office_id_for_user), ARRAY_A);
                if (is_array($office_row) && isset($office_row['office_name'])) {
                    $dashboard_office_name = (string) $office_row['office_name'];
                }
                $dashboard_office_user_ids = EstateOfficeCRM_Access::get_office_user_ids($office_id_for_user, $this->tables);
                if (! in_array($current_user_id, $dashboard_office_user_ids, true)) {
                    $dashboard_office_user_ids[] = $current_user_id;
                }
                $dashboard_office_user_ids = array_values(array_unique(array_filter(array_map('absint', $dashboard_office_user_ids))));
            }
        }

        $top_agents = ! empty($dashboard_office_user_ids)
            ? $this->get_top_agents(500, '', [], $dashboard_office_user_ids)
            : $this->get_top_agents(5, $owner_scope['sql'], $owner_scope['params']);

        $dashboard_recent_agreements = [];
        $dashboard_recent_properties = [];
        $dashboard_recent_searches = [];
        $dashboard_transaction_breakdown = [];
        $dashboard_export_breakdown = [
            'www_only' => 0,
            'portals_only' => 0,
            'both' => 0,
            'none' => 0,
        ];
        $dashboard_gross_remuneration = [];

        if ($section === 'dashboard') {
            $dashboard_recent_agreements = $this->list_agreements('', 5);
            $dashboard_recent_properties = $this->list_properties('', 5);
            $dashboard_recent_searches = $this->list_searches('', 5);
            $dashboard_gross_remuneration = $this->get_dashboard_gross_remuneration();

            global $wpdb;
            $agreements_table = $this->tables['agreements'];
            $properties_table = $this->tables['properties'];

            $tx_sql = "SELECT transaction_type, COUNT(*) AS total
                FROM {$agreements_table}
                WHERE is_active = 1 AND " . $this->agreement_not_finished_sql('current_stage') . $owner_scope['sql'] . "
                GROUP BY transaction_type
                ORDER BY total DESC";
            if (! empty($owner_scope['params'])) {
                $tx_sql = $wpdb->prepare($tx_sql, $owner_scope['params']);
            }
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared, owner_scope is internal.
            $tx_rows = $wpdb->get_results($tx_sql, ARRAY_A);
            if (is_array($tx_rows)) {
                foreach ($tx_rows as $tx_row) {
                    $tx_type = isset($tx_row['transaction_type']) ? strtoupper(trim((string) $tx_row['transaction_type'])) : '';
                    $tx_total = isset($tx_row['total']) ? (int) $tx_row['total'] : 0;
                    if ($tx_type !== '' && $tx_total > 0) {
                        $dashboard_transaction_breakdown[$tx_type] = $tx_total;
                    }
                }
            }

            $exp_sql = "SELECT
                    SUM(CASE WHEN export_www = 1 AND export_portals = 0 THEN 1 ELSE 0 END) AS www_only,
                    SUM(CASE WHEN export_www = 0 AND export_portals = 1 THEN 1 ELSE 0 END) AS portals_only,
                    SUM(CASE WHEN export_www = 1 AND export_portals = 1 THEN 1 ELSE 0 END) AS both,
                    SUM(CASE WHEN export_www = 0 AND export_portals = 0 THEN 1 ELSE 0 END) AS none
                FROM {$properties_table}
                WHERE is_active = 1 AND is_sold = 0 AND is_rented = 0" . $owner_scope['sql'];
            if (! empty($owner_scope['params'])) {
                $exp_sql = $wpdb->prepare($exp_sql, $owner_scope['params']);
            }
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared, owner_scope is internal.
            $exp_row = $wpdb->get_row($exp_sql, ARRAY_A);
            if (is_array($exp_row)) {
                $dashboard_export_breakdown['www_only'] = (int) ($exp_row['www_only'] ?? 0);
                $dashboard_export_breakdown['portals_only'] = (int) ($exp_row['portals_only'] ?? 0);
                $dashboard_export_breakdown['both'] = (int) ($exp_row['both'] ?? 0);
                $dashboard_export_breakdown['none'] = (int) ($exp_row['none'] ?? 0);
            }
        }

        $current_url = get_permalink() ?: home_url('/crm/');
        $crm_logout_redirect_url = $this->resolve_plugin_page_url('agent_login', 'panel-logowania-agenta');
        if ($crm_logout_redirect_url === '') {
            $crm_logout_redirect_url = home_url('/');
        }
        $export_xml_url = add_query_arg(
            [
                'crm' => $section,
                'eocrm_export' => 'xml',
                'eocrm_export_nonce' => wp_create_nonce('eocrm_export_xml'),
            ],
            $current_url
        );

        $form_mode = isset($_GET['mode']) ? sanitize_key((string) wp_unslash($_GET['mode'])) : '';
        if (! in_array($form_mode, ['new-client', 'edit-client', 'new-agreement', 'edit-agreement', 'copy-agreement', 'new-property', 'edit-property', 'copy-property', 'new-search', 'edit-search', 'new-transaction', 'edit-transaction', 'new'], true)) {
            $form_mode = '';
        }
        if (in_array($form_mode, ['copy-agreement', 'copy-property'], true) && ! current_user_can('manage_options')) {
            $form_mode = '';
        }

        $owner_user_options = [];
        if (
            current_user_can('manage_options')
            && in_array($section, ['clients', 'agreements', 'properties'], true)
            && in_array($form_mode, ['edit-client', 'edit-agreement', 'copy-agreement', 'edit-property', 'copy-property'], true)
        ) {
            $owner_user_options = $this->get_owner_user_options();
        }

        $clients_mode = in_array($form_mode, ['new-client', 'edit-client'], true) ? $form_mode : '';
        if ($form_mode === 'new') {
            $agreements_mode = 'new-agreement';
        } elseif (in_array($form_mode, ['new-agreement', 'edit-agreement', 'copy-agreement'], true)) {
            $agreements_mode = $form_mode;
        } else {
            $agreements_mode = '';
        }
        $properties_mode = in_array($form_mode, ['new-property', 'edit-property', 'copy-property'], true) ? $form_mode : '';
        $searches_mode = in_array($form_mode, ['new-search', 'edit-search'], true) ? $form_mode : '';
        $transactions_mode = in_array($form_mode, ['new-transaction', 'edit-transaction'], true) ? $form_mode : '';

        $property_form_draft_new = [];
        $property_form_draft_edit = [];
        if ($section === 'properties' && $current_user_id > 0) {
            if (in_array($properties_mode, ['new-property', 'copy-property'], true)) {
                $draft_new = get_user_meta($current_user_id, EstateOfficeCRM_Properties::USERMETA_NEW_FORM_DRAFT, true);
                if (is_array($draft_new)) {
                    $property_form_draft_new = $draft_new;
                }
                delete_user_meta($current_user_id, EstateOfficeCRM_Properties::USERMETA_NEW_FORM_DRAFT);
            } elseif ($properties_mode === 'edit-property' && $property_id > 0) {
                $draft_key = EstateOfficeCRM_Properties::USERMETA_EDIT_FORM_DRAFT_PREFIX . $property_id;
                $draft_edit = get_user_meta($current_user_id, $draft_key, true);
                if (is_array($draft_edit)) {
                    $property_form_draft_edit = $draft_edit;
                }
                delete_user_meta($current_user_id, $draft_key);
            }
        }

        $crm_notice = isset($_GET['crm_notice']) ? sanitize_key((string) wp_unslash($_GET['crm_notice'])) : '';
        $crm_message = isset($_GET['crm_message']) ? sanitize_text_field((string) wp_unslash($_GET['crm_message'])) : '';
        $default_agreement_numbers_by_type = [
            'SPRZEDAZ' => $this->build_default_number_for_entity('agreement', 'sprzedaz'),
            'KUPNO' => $this->build_default_number_for_entity('agreement', 'kupno'),
            'WYNAJEM' => $this->build_default_number_for_entity('agreement', 'wynajem'),
            'NAJEM' => $this->build_default_number_for_entity('agreement', 'najem'),
        ];
        $default_agreement_number = (string) ($default_agreement_numbers_by_type['SPRZEDAZ'] ?? '');
        $default_offer_number = $this->build_default_number_for_entity('offer');
        $default_search_number = $this->build_default_number_for_entity('search');
        $agreement_stage_options = [];
        if (is_array($agreement_profile)) {
            $agreement_stage_options = EstateOfficeCRM_Stages::load_for_transaction(
                (string) ($agreement_profile['transaction_type'] ?? ''),
                function (string $key): string {
                    return $this->get_setting_value($key);
                }
            );
        }
        if (empty($agreement_stage_options)) {
            $agreement_stage_options = EstateOfficeCRM_Stages::default_for_transaction('SPRZEDAZ');
        }
        $agreement_stage_options_by_type = EstateOfficeCRM_Stages::load_all(
            function (string $key): string {
                return $this->get_setting_value($key);
            }
        );

        $agreement_client_options = [];
        if ($section === 'agreements' && in_array($agreements_mode, ['new-agreement', 'edit-agreement', 'copy-agreement'], true)) {
            $client_rows_for_select = $this->list_clients('', 500);
            foreach ($client_rows_for_select as $client_row) {
                $search_chunks = [];
                foreach (['display_name', 'first_name', 'last_name', 'company_name', 'phone', 'email', 'address_street', 'address_building_no', 'address_city'] as $search_field) {
                    $search_value = trim((string) ($client_row[$search_field] ?? ''));
                    if ($search_value !== '') {
                        $search_chunks[] = $search_value;
                    }
                }
                $search_index = trim(implode(' ', $search_chunks));
                $agreement_client_options[] = [
                    'id' => (int) ($client_row['id'] ?? 0),
                    'label' => (string) ($client_row['display_name'] ?? ''),
                    'search' => $search_index,
                ];
            }
        }

        $property_agreement_options = [];
        if ($section === 'properties' && in_array($properties_mode, ['new-property', 'edit-property', 'copy-property'], true)) {
            if ($properties_mode === 'new-property') {
                $property_agreement_options = $this->list_agreements_for_property_form(500);
            }

            $maps_api_key = $this->get_setting_value('google_maps_api_key');
            if ($maps_api_key !== '') {
                wp_enqueue_script(
                    'eocrm-google-maps',
                    add_query_arg(
                        [
                            'key' => $maps_api_key,
                            'libraries' => 'places',
                        ],
                        'https://maps.googleapis.com/maps/api/js'
                    ),
                    [],
                    null,
                    true
                );

                if (wp_script_is('eocrm-frontend', 'registered')) {
                    wp_deregister_script('eocrm-frontend');
                    wp_register_script('eocrm-frontend', EOCRM_URL . 'assets/js/frontend.js', ['eocrm-google-maps'], EOCRM_VERSION, true);
                }
            }

            wp_localize_script('eocrm-frontend', 'eocrmFrontendConfig', [
                'mapsApiKey' => $maps_api_key,
                'mapsDefaultLat' => '52.229676',
                'mapsDefaultLng' => '21.012229',
            ]);

            wp_enqueue_media();
            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
            }
        }

        $search_agreement_options = [];
        if ($section === 'searches' && in_array($searches_mode, ['new-search', 'edit-search'], true)) {
            if ($searches_mode === 'new-search') {
                $search_agreement_options = $this->list_agreements_for_search_form(500);
            }
            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
            }
        }

        if ($section === 'transactions' && $transactions_mode === 'new-transaction') {
            $transaction_form_agreement_id = isset($_GET['agreement_id']) ? absint((string) wp_unslash($_GET['agreement_id'])) : 0;
            if ($transaction_form_agreement_id > 0) {
                $transaction_form_agreement = $this->get_transaction_form_agreement($transaction_form_agreement_id);
                if (is_array($transaction_form_agreement)) {
                    $transaction_form_properties = $this->get_transaction_form_properties($transaction_form_agreement_id, 200);
                    $transaction_form_paid_commission_stage_names = $this->get_transaction_form_paid_commission_stage_names($transaction_form_agreement_id, 0);
                }
            }
        }
        if ($section === 'transactions' && $transactions_mode === 'edit-transaction' && is_array($transaction_profile)) {
            $transaction_form_agreement_id = (int) ($transaction_profile['agreement_id'] ?? 0);
            if ($transaction_form_agreement_id > 0) {
                $transaction_form_agreement = $this->get_transaction_form_agreement($transaction_form_agreement_id);
                if (is_array($transaction_form_agreement)) {
                    $transaction_form_properties = $this->get_transaction_form_properties($transaction_form_agreement_id, 200);
                    $transaction_form_paid_commission_stage_names = $this->get_transaction_form_paid_commission_stage_names($transaction_form_agreement_id, (int) ($transaction_profile['id'] ?? 0));
                }
            }
        }
        if ($section === 'transactions' && in_array($transactions_mode, ['new-transaction', 'edit-transaction'], true)) {
            $transaction_agent_options = $this->get_transaction_agent_options(500);
        }

        $property_additional_fields = EstateOfficeCRM_Property_Custom_Fields::load_definitions(function (string $key): string {
            return $this->get_setting_value($key);
        });

        $resolve_owner_photo_url = function (int $owner_user_id, string $size = 'thumbnail'): string {
            return $this->get_agent_photo_url($owner_user_id, $size);
        };

        wp_enqueue_style('eocrm-frontend');
        $this->apply_frontend_width_style();

        ob_start();
        include EOCRM_PATH . 'templates/frontend-crm.php';

        return $this->translate_output((string) ob_get_clean());
    }

    public function render_offers_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['transaction' => 'SPRZEDAZ'], $atts, 'eocrm_offers');
        $transaction = strtoupper(sanitize_text_field((string) $atts['transaction']));

        if (! in_array($transaction, ['SPRZEDAZ', 'WYNAJEM'], true)) {
            $transaction = 'SPRZEDAZ';
        }

        $filters = [
            'property_type' => isset($_GET['property_type']) ? sanitize_text_field((string) wp_unslash($_GET['property_type'])) : '',
            'city' => isset($_GET['city']) ? sanitize_text_field((string) wp_unslash($_GET['city'])) : '',
            'district' => isset($_GET['district']) ? sanitize_text_field((string) wp_unslash($_GET['district'])) : '',
            'price_min' => isset($_GET['price_min']) ? $this->sanitize_public_decimal_filter((string) wp_unslash($_GET['price_min'])) : '',
            'price_max' => isset($_GET['price_max']) ? $this->sanitize_public_decimal_filter((string) wp_unslash($_GET['price_max'])) : '',
            'area_min' => isset($_GET['area_min']) ? $this->sanitize_public_decimal_filter((string) wp_unslash($_GET['area_min'])) : '',
            'area_max' => isset($_GET['area_max']) ? $this->sanitize_public_decimal_filter((string) wp_unslash($_GET['area_max'])) : '',
            'rooms_min' => isset($_GET['rooms_min']) ? $this->sanitize_public_integer_filter((string) wp_unslash($_GET['rooms_min'])) : '',
            'rooms_max' => isset($_GET['rooms_max']) ? $this->sanitize_public_integer_filter((string) wp_unslash($_GET['rooms_max'])) : '',
        ];
        $filter_options = $this->get_public_filter_options($transaction);
        $filters = $this->sanitize_public_offer_filters($filters, $filter_options);

        $offers_page_url = '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        if ($request_uri !== '') {
            $candidate_url = home_url($request_uri);
            if (is_string($candidate_url) && $candidate_url !== '') {
                $offers_page_url = $candidate_url;
            }
        }

        if ($offers_page_url === '') {
            $queried_object_id = get_queried_object_id();
            if (is_numeric($queried_object_id) && (int) $queried_object_id > 0) {
                $candidate_url = get_permalink((int) $queried_object_id);
                if (is_string($candidate_url) && $candidate_url !== '') {
                    $offers_page_url = $candidate_url;
                }
            }
        }

        if ($offers_page_url === '') {
            $page_ids = get_option(EstateOfficeCRM_Installer::OPTION_PAGE_IDS, []);
            $page_key = $transaction === 'WYNAJEM' ? 'rent_offers' : 'sale_offers';
            $candidate_id = is_array($page_ids) && isset($page_ids[$page_key]) ? (int) $page_ids[$page_key] : 0;
            if ($candidate_id > 0) {
                $candidate_url = get_permalink($candidate_id);
                if (is_string($candidate_url) && $candidate_url !== '') {
                    $offers_page_url = $candidate_url;
                }
            }
        }

        if ($offers_page_url === '') {
            $offers_page_url = home_url('/');
        }

        $offers_page_url = remove_query_arg(
            ['eocrm_page', 'property_type', 'city', 'district', 'price_min', 'price_max', 'area_min', 'area_max', 'rooms_min', 'rooms_max'],
            $offers_page_url
        );
        if (! is_string($offers_page_url) || $offers_page_url === '') {
            $offers_page_url = home_url('/');
        }

        $per_page = $this->get_public_offers_per_page_setting();
        $current_page = isset($_GET['eocrm_page']) ? absint((string) wp_unslash($_GET['eocrm_page'])) : 1;
        if ($current_page <= 0) {
            $current_page = 1;
        }

        $total_offers = $this->count_public_offers($transaction, $filters);
        $total_pages = max(1, (int) ceil($total_offers / $per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }

        $offset = ($current_page - 1) * $per_page;
        $offers = $this->get_public_offers($transaction, $filters, $per_page, $offset);
        $unit_settings = EstateOfficeCRM_Units::load_settings(function (string $key): string {
            return $this->get_setting_value($key);
        });
        $public_offers_per_page = $per_page;
        $offer_watermark_url = $this->get_public_watermark_url();
        $offer_open_in_new_window = $this->get_setting_value('offer_template_open_in_new_window') === '1';

        $maps_api_key = $this->get_setting_value('google_maps_api_key');
        if ($maps_api_key !== '') {
            wp_enqueue_script(
                'eocrm-google-maps',
                add_query_arg(
                    [
                        'key' => $maps_api_key,
                        'libraries' => 'places',
                    ],
                    'https://maps.googleapis.com/maps/api/js'
                ),
                [],
                null,
                true
            );

            if (wp_script_is('eocrm-frontend', 'registered')) {
                wp_deregister_script('eocrm-frontend');
                wp_register_script('eocrm-frontend', EOCRM_URL . 'assets/js/frontend.js', ['eocrm-google-maps'], EOCRM_VERSION, true);
            }
        }

        wp_enqueue_style('eocrm-frontend');
        $this->apply_frontend_width_style();
        wp_localize_script('eocrm-frontend', 'eocrmFrontendConfig', [
            'mapsApiKey' => $maps_api_key,
            'mapsDefaultLat' => '52.229676',
            'mapsDefaultLng' => '21.012229',
        ]);
        wp_enqueue_script('eocrm-frontend');

        ob_start();
        include EOCRM_PATH . 'templates/public-offers.php';

        return $this->translate_output((string) ob_get_clean());
    }

    public function render_offer_single_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['property_id' => 0], $atts, 'eocrm_offer_single');
        $property_id = absint((string) ($atts['property_id'] ?? '0'));

        if ($property_id <= 0) {
            return $this->translate_output('<div class="eocrm-alert">Brak identyfikatora oferty.</div>');
        }

        $offer = $this->get_public_offer_by_property_id($property_id);
        if (! is_array($offer)) {
            return $this->translate_output('<div class="eocrm-alert">Oferta nie istnieje lub nie jest udostepniona na WWW.</div>');
        }

        $this->ensure_current_offer_page_title($property_id, $offer);
        $media = $this->get_property_profile_media($property_id);
        $offer_agent = $this->get_public_agent_by_user_id(isset($offer['owner_user_id']) ? (int) $offer['owner_user_id'] : 0);
        $contact_notice = ['type' => '', 'message' => ''];
        $contact_form_values = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'preferred_date' => '',
            'message' => '',
        ];
        $mortgage_contact_notice = ['type' => '', 'message' => ''];
        $mortgage_contact_form_values = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
        ];
        $mortgage_advisor_enabled = $this->get_setting_value('mortgage_advisor_enabled') !== '0';
        $mortgage_advisor_logo_size_percent = $this->sanitize_mortgage_advisor_logo_size_setting($this->get_setting_value('mortgage_advisor_logo_size_percent'));
        $mortgage_advisor_logo_align_x = $this->sanitize_mortgage_advisor_logo_align_setting($this->get_setting_value('mortgage_advisor_logo_align_x'));
        $mortgage_advisor_attachment_id = absint($this->get_setting_value('mortgage_advisor_attachment_id'));
        $mortgage_advisor_image_url = $mortgage_advisor_attachment_id > 0 ? wp_get_attachment_image_url($mortgage_advisor_attachment_id, 'medium') : '';

        if (
            isset($_POST['eocrm_offer_contact_action'])
            && sanitize_key((string) wp_unslash($_POST['eocrm_offer_contact_action'])) === 'schedule_visit'
            && isset($_POST['eocrm_offer_contact_property_id'])
            && absint((string) wp_unslash($_POST['eocrm_offer_contact_property_id'])) === $property_id
        ) {
            $contact_form_values['name'] = isset($_POST['contact_name']) ? sanitize_text_field((string) wp_unslash($_POST['contact_name'])) : '';
            $contact_form_values['email'] = isset($_POST['contact_email']) ? sanitize_email((string) wp_unslash($_POST['contact_email'])) : '';
            $contact_form_values['phone'] = isset($_POST['contact_phone']) ? sanitize_text_field((string) wp_unslash($_POST['contact_phone'])) : '';
            $contact_form_values['preferred_date'] = isset($_POST['contact_preferred_date']) ? sanitize_text_field((string) wp_unslash($_POST['contact_preferred_date'])) : '';
            $contact_form_values['message'] = isset($_POST['contact_message']) ? sanitize_textarea_field((string) wp_unslash($_POST['contact_message'])) : '';

            $nonce = isset($_POST['eocrm_offer_contact_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['eocrm_offer_contact_nonce'])) : '';

            if (! wp_verify_nonce($nonce, 'eocrm_offer_contact_' . $property_id)) {
                $contact_notice = [
                    'type' => 'error',
                    'message' => 'Nie udało się wysłać formularza. Odśwież stronę i spróbuj ponownie.',
                ];
            } elseif ($contact_form_values['name'] === '') {
                $contact_notice = [
                    'type' => 'error',
                    'message' => 'Podaj imię i nazwisko.',
                ];
            } elseif ($contact_form_values['email'] === '' || ! is_email($contact_form_values['email'])) {
                $contact_notice = [
                    'type' => 'error',
                    'message' => 'Podaj poprawny adres e-mail.',
                ];
            } else {
                $recipient = '';
                if (is_array($offer_agent)) {
                    $agent_email = isset($offer_agent['email']) ? sanitize_email((string) $offer_agent['email']) : '';
                    if ($agent_email !== '' && is_email($agent_email)) {
                        $recipient = $agent_email;
                    }
                }

                if ($recipient === '') {
                    $admin_email = (string) get_option('admin_email');
                    if ($admin_email !== '' && is_email($admin_email)) {
                        $recipient = $admin_email;
                    }
                }

                if ($recipient === '') {
                    $contact_notice = [
                        'type' => 'error',
                        'message' => 'Brak adresu e-mail do wysyłki formularza.',
                    ];
                } else {
                    $offer_number = trim((string) ($offer['offer_number'] ?? ''));
                    if ($offer_number === '') {
                        $offer_number = '#' . (string) $property_id;
                    }

                    $offer_url = get_permalink();
                    if (! is_string($offer_url)) {
                        $offer_url = '';
                    }

                    $subject = 'Zapytanie o wizyte - oferta ' . $offer_number;
                    $body_lines = [
                        'Nowe zapytanie o umowienie wizyty.',
                        '',
                        'Oferta: ' . $offer_number,
                    ];
                    if ($offer_url !== '') {
                        $body_lines[] = 'URL: ' . $offer_url;
                    }
                    $body_lines[] = '';
                    $body_lines[] = 'Imię i nazwisko: ' . $contact_form_values['name'];
                    $body_lines[] = 'E-mail: ' . $contact_form_values['email'];
                    $body_lines[] = 'Telefon: ' . ($contact_form_values['phone'] !== '' ? $contact_form_values['phone'] : '-');
                    $body_lines[] = 'Preferowany termin: ' . ($contact_form_values['preferred_date'] !== '' ? $contact_form_values['preferred_date'] : '-');
                    $body_lines[] = '';
                    $body_lines[] = 'Wiadomosc:';
                    $body_lines[] = $contact_form_values['message'] !== '' ? $contact_form_values['message'] : '-';

                    $headers = ['Content-Type: text/plain; charset=UTF-8'];
                    if ($contact_form_values['email'] !== '' && is_email($contact_form_values['email'])) {
                        $headers[] = 'Reply-To: ' . $contact_form_values['name'] . ' <' . $contact_form_values['email'] . '>';
                    }

                    $sent = wp_mail($recipient, $subject, implode("\n", $body_lines), $headers);
                    if ($sent) {
                        $contact_notice = [
                            'type' => 'success',
                            'message' => 'Dziękujemy. Wiadomość została wysłana do opiekuna oferty.',
                        ];
                        $contact_form_values = [
                            'name' => '',
                            'email' => '',
                            'phone' => '',
                            'preferred_date' => '',
                            'message' => '',
                        ];
                    } else {
                        $contact_notice = [
                            'type' => 'error',
                            'message' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie za chwilę.',
                        ];
                    }
                }
            }
        }

        if (
            $mortgage_advisor_enabled
            &&
            isset($_POST['eocrm_offer_contact_action'])
            && sanitize_key((string) wp_unslash($_POST['eocrm_offer_contact_action'])) === 'mortgage_advisor_contact'
            && isset($_POST['eocrm_offer_contact_property_id'])
            && absint((string) wp_unslash($_POST['eocrm_offer_contact_property_id'])) === $property_id
        ) {
            $mortgage_contact_form_values['first_name'] = isset($_POST['mortgage_contact_first_name']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_contact_first_name'])) : '';
            $mortgage_contact_form_values['last_name'] = isset($_POST['mortgage_contact_last_name']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_contact_last_name'])) : '';
            $mortgage_contact_form_values['email'] = isset($_POST['mortgage_contact_email']) ? sanitize_email((string) wp_unslash($_POST['mortgage_contact_email'])) : '';
            $mortgage_contact_form_values['phone'] = isset($_POST['mortgage_contact_phone']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_contact_phone'])) : '';

            $calc_export_values = [
                'loan' => isset($_POST['mortgage_calc_loan']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_loan'])) : '',
                'rate' => isset($_POST['mortgage_calc_rate']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_rate'])) : '',
                'monthly' => isset($_POST['mortgage_calc_monthly']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_monthly'])) : '',
                'first' => isset($_POST['mortgage_calc_first']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_first'])) : '',
                'last' => isset($_POST['mortgage_calc_last']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_last'])) : '',
                'interest' => isset($_POST['mortgage_calc_interest']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_interest'])) : '',
                'commission' => isset($_POST['mortgage_calc_commission']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_commission'])) : '',
                'total' => isset($_POST['mortgage_calc_total']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_total'])) : '',
                'price' => isset($_POST['mortgage_calc_price']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_price'])) : '',
                'down_payment' => isset($_POST['mortgage_calc_down_payment']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_down_payment'])) : '',
                'down_payment_percent' => isset($_POST['mortgage_calc_down_payment_percent']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_down_payment_percent'])) : '',
                'years' => isset($_POST['mortgage_calc_years']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_years'])) : '',
                'tenor' => isset($_POST['mortgage_calc_tenor']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_tenor'])) : '',
                'wibor_rate' => isset($_POST['mortgage_calc_wibor_rate']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_wibor_rate'])) : '',
                'margin' => isset($_POST['mortgage_calc_margin']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_margin'])) : '',
                'commission_percent' => isset($_POST['mortgage_calc_commission_percent']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_commission_percent'])) : '',
                'installment_type' => isset($_POST['mortgage_calc_installment_type']) ? sanitize_key((string) wp_unslash($_POST['mortgage_calc_installment_type'])) : 'equal',
                'monthly_fees' => isset($_POST['mortgage_calc_monthly_fees']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_monthly_fees'])) : '',
                'finance_commission' => isset($_POST['mortgage_calc_finance_commission']) ? sanitize_text_field((string) wp_unslash($_POST['mortgage_calc_finance_commission'])) : '',
            ];

            $nonce = isset($_POST['eocrm_mortgage_contact_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['eocrm_mortgage_contact_nonce'])) : '';
            if (! wp_verify_nonce($nonce, 'eocrm_mortgage_contact_' . $property_id)) {
                $mortgage_contact_notice = [
                    'type' => 'error',
                    'message' => 'Nie udało się wysłać formularza. Odśwież stronę i spróbuj ponownie.',
                ];
            } elseif ($mortgage_contact_form_values['first_name'] === '' || $mortgage_contact_form_values['last_name'] === '') {
                $mortgage_contact_notice = [
                    'type' => 'error',
                    'message' => 'Podaj imię i nazwisko.',
                ];
            } elseif ($mortgage_contact_form_values['email'] === '' || ! is_email($mortgage_contact_form_values['email'])) {
                $mortgage_contact_notice = [
                    'type' => 'error',
                    'message' => 'Podaj poprawny adres e-mail.',
                ];
            } else {
                $recipient = sanitize_email($this->get_setting_value('mortgage_advisor_email'));
                if ($recipient === '' || ! is_email($recipient)) {
                    $admin_email = (string) get_option('admin_email');
                    if ($admin_email !== '' && is_email($admin_email)) {
                        $recipient = $admin_email;
                    }
                }

                if ($recipient === '') {
                    $mortgage_contact_notice = [
                        'type' => 'error',
                        'message' => 'Brak adresu e-mail do wysyłki formularza doradcy.',
                    ];
                } else {
                    $offer_number = trim((string) ($offer['offer_number'] ?? ''));
                    if ($offer_number === '') {
                        $offer_number = '#' . (string) $property_id;
                    }

                    $offer_url = get_permalink();
                    if (! is_string($offer_url)) {
                        $offer_url = '';
                    }

                    $installment_type = $calc_export_values['installment_type'] === 'decreasing' ? 'Raty malejące' : 'Raty równe';
                    $subject = 'Prośba o kontakt kredytowy - oferta ' . $offer_number;
                    $body_lines = [
                        'Nowa prośba o kontakt z doradcą kredytowym.',
                        '',
                        'Oferta: ' . $offer_number,
                    ];
                    if ($offer_url !== '') {
                        $body_lines[] = 'URL: ' . $offer_url;
                    }
                    $body_lines[] = '';
                    $body_lines[] = 'Dane klienta:';
                    $body_lines[] = 'Imię i nazwisko: ' . $mortgage_contact_form_values['first_name'] . ' ' . $mortgage_contact_form_values['last_name'];
                    $body_lines[] = 'E-mail: ' . $mortgage_contact_form_values['email'];
                    $body_lines[] = 'Telefon: ' . ($mortgage_contact_form_values['phone'] !== '' ? $mortgage_contact_form_values['phone'] : '-');
                    $body_lines[] = '';
                    $body_lines[] = 'Parametry kalkulatora:';
                    $body_lines[] = 'Cena nieruchomości: ' . ($calc_export_values['price'] !== '' ? $calc_export_values['price'] : '-');
                    $body_lines[] = 'Wkład własny: ' . ($calc_export_values['down_payment'] !== '' ? $calc_export_values['down_payment'] : '-');
                    $body_lines[] = 'Wkład własny (%): ' . ($calc_export_values['down_payment_percent'] !== '' ? $calc_export_values['down_payment_percent'] : '-');
                    $body_lines[] = 'Okres kredytu (lata): ' . ($calc_export_values['years'] !== '' ? $calc_export_values['years'] : '-');
                    $body_lines[] = 'WIBOR tenor: ' . ($calc_export_values['tenor'] !== '' ? $calc_export_values['tenor'] : '-');
                    $body_lines[] = 'WIBOR (%): ' . ($calc_export_values['wibor_rate'] !== '' ? $calc_export_values['wibor_rate'] : '-');
                    $body_lines[] = 'Marża banku (%): ' . ($calc_export_values['margin'] !== '' ? $calc_export_values['margin'] : '-');
                    $body_lines[] = 'Prowizja banku (%): ' . ($calc_export_values['commission_percent'] !== '' ? $calc_export_values['commission_percent'] : '-');
                    $body_lines[] = 'Raty: ' . $installment_type;
                    $body_lines[] = 'Dodatkowe opłaty miesięczne: ' . ($calc_export_values['monthly_fees'] !== '' ? $calc_export_values['monthly_fees'] : '-');
                    $body_lines[] = 'Dolicz prowizję do kredytu: ' . ($calc_export_values['finance_commission'] !== '' ? $calc_export_values['finance_commission'] : '-');
                    $body_lines[] = '';
                    $body_lines[] = 'Wyniki kalkulatora:';
                    $body_lines[] = 'Kwota kredytu: ' . ($calc_export_values['loan'] !== '' ? $calc_export_values['loan'] : '-');
                    $body_lines[] = 'Oprocentowanie roczne: ' . ($calc_export_values['rate'] !== '' ? $calc_export_values['rate'] : '-');
                    $body_lines[] = 'Rata miesięczna (szac.): ' . ($calc_export_values['monthly'] !== '' ? $calc_export_values['monthly'] : '-');
                    $body_lines[] = 'Pierwsza rata (malejące): ' . ($calc_export_values['first'] !== '' ? $calc_export_values['first'] : '-');
                    $body_lines[] = 'Ostatnia rata (malejące): ' . ($calc_export_values['last'] !== '' ? $calc_export_values['last'] : '-');
                    $body_lines[] = 'Koszt odsetek: ' . ($calc_export_values['interest'] !== '' ? $calc_export_values['interest'] : '-');
                    $body_lines[] = 'Prowizja banku: ' . ($calc_export_values['commission'] !== '' ? $calc_export_values['commission'] : '-');
                    $body_lines[] = 'Łączny koszt kredytu: ' . ($calc_export_values['total'] !== '' ? $calc_export_values['total'] : '-');

                    $headers = ['Content-Type: text/plain; charset=UTF-8'];
                    if ($mortgage_contact_form_values['email'] !== '' && is_email($mortgage_contact_form_values['email'])) {
                        $headers[] = 'Reply-To: ' . $mortgage_contact_form_values['first_name'] . ' ' . $mortgage_contact_form_values['last_name'] . ' <' . $mortgage_contact_form_values['email'] . '>';
                    }

                    $sent = wp_mail($recipient, $subject, implode("\n", $body_lines), $headers);
                    if ($sent) {
                        $mortgage_contact_notice = [
                            'type' => 'success',
                            'message' => 'Dziękujemy. Prośba o kontakt została wysłana do doradcy kredytowego.',
                        ];
                        $mortgage_contact_form_values = [
                            'first_name' => '',
                            'last_name' => '',
                            'email' => '',
                            'phone' => '',
                        ];
                    } else {
                        $mortgage_contact_notice = [
                            'type' => 'error',
                            'message' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie za chwilę.',
                        ];
                    }
                }
            }
        }

        wp_enqueue_style('eocrm-frontend');
        $this->apply_frontend_width_style();
        wp_localize_script('eocrm-frontend', 'eocrmFrontendConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'wiborAction' => 'eocrm_get_wibor_rates',
            'wiborRates' => $this->get_wibor_rates_payload(),
        ]);
        wp_enqueue_script('eocrm-frontend');
        $unit_settings = EstateOfficeCRM_Units::load_settings(function (string $key): string {
            return $this->get_setting_value($key);
        });

        $offer_template_variant = $this->get_setting_value('offer_template_variant');
        if (! in_array($offer_template_variant, ['modern_v1', 'modern_v2', 'modern_v3', 'modern_v4', 'modern_v5', 'modern_v6'], true)) {
            $offer_template_variant = 'modern_v1';
        }

        $offer_template_file = EOCRM_PATH . 'templates/public-offer-single.php';
        if ($offer_template_variant === 'modern_v2') {
            $offer_template_file = EOCRM_PATH . 'templates/public-offer-single-modern-v2.php';
        } elseif ($offer_template_variant === 'modern_v3') {
            $offer_template_file = EOCRM_PATH . 'templates/public-offer-single-modern-v3.php';
        } elseif ($offer_template_variant === 'modern_v4') {
            $offer_template_file = EOCRM_PATH . 'templates/public-offer-single-modern-v4.php';
        } elseif ($offer_template_variant === 'modern_v5') {
            $offer_template_file = EOCRM_PATH . 'templates/public-offer-single-modern-v5.php';
        } elseif ($offer_template_variant === 'modern_v6') {
            $offer_template_file = EOCRM_PATH . 'templates/public-offer-single-modern-v6.php';
        }

        if (! file_exists($offer_template_file)) {
            $offer_template_file = EOCRM_PATH . 'templates/public-offer-single.php';
        }

        $property_additional_fields_public = EstateOfficeCRM_Property_Custom_Fields::load_definitions(function (string $key): string {
            return $this->get_setting_value($key);
        });
        $offer_watermark_url = $this->get_public_watermark_url();
        $mortgage_advisor_block_html = $mortgage_advisor_enabled
            ? $this->build_mortgage_advisor_contact_block(
                $property_id,
                $mortgage_contact_notice,
                $mortgage_contact_form_values,
                is_string($mortgage_advisor_image_url) ? $mortgage_advisor_image_url : '',
                $mortgage_advisor_logo_size_percent,
                $mortgage_advisor_logo_align_x
            )
            : '';

        ob_start();
        include $offer_template_file;

        return $this->translate_output((string) ob_get_clean());
    }

    /**
     * @param array<string, string> $notice
     * @param array<string, string> $values
     */
    private function build_mortgage_advisor_contact_block(
        int $property_id,
        array $notice,
        array $values,
        string $image_url,
        int $logo_size_percent,
        string $logo_align_x
    ): string
    {
        if ($property_id <= 0) {
            return '';
        }

        $notice_type = isset($notice['type']) ? sanitize_key((string) $notice['type']) : '';
        if (! in_array($notice_type, ['success', 'error'], true)) {
            $notice_type = '';
        }
        $notice_message = isset($notice['message']) ? trim(sanitize_text_field((string) $notice['message'])) : '';

        $first_name = isset($values['first_name']) ? sanitize_text_field((string) $values['first_name']) : '';
        $last_name = isset($values['last_name']) ? sanitize_text_field((string) $values['last_name']) : '';
        $email = isset($values['email']) ? sanitize_email((string) $values['email']) : '';
        $phone = isset($values['phone']) ? sanitize_text_field((string) $values['phone']) : '';
        $logo_size_percent = $this->sanitize_mortgage_advisor_logo_size_setting((string) $logo_size_percent);
        $logo_align_x = $this->sanitize_mortgage_advisor_logo_align_setting($logo_align_x);
        $logo_media_class = 'eocrm-mortgage-advisor-media eocrm-mortgage-advisor-media--' . $logo_align_x;
        $logo_media_style = '--eocrm-advisor-logo-max-height: ' . (string) $logo_size_percent . '%;';

        $nonce_field = wp_nonce_field('eocrm_mortgage_contact_' . $property_id, 'eocrm_mortgage_contact_nonce', true, false);

        ob_start();
        ?>
        <section class="eocrm-mortgage-advisor" id="eocrm-mortgage-advisor-contact">
            <div class="<?php echo esc_attr($logo_media_class); ?>" style="<?php echo esc_attr($logo_media_style); ?>">
                <?php if ($image_url !== '') : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="Doradca kredytowy">
                <?php else : ?>
                    <div class="eocrm-mortgage-advisor-media-placeholder">Doradca kredytowy</div>
                <?php endif; ?>
            </div>

            <div class="eocrm-mortgage-advisor-content">
                <h4>Umów się z naszym doradcą kredytowym</h4>
                <?php if ($notice_message !== '') : ?>
                    <p class="eocrm-alert <?php echo $notice_type === 'success' ? 'eocrm-alert-success' : 'eocrm-alert-error'; ?>">
                        <?php echo esc_html($notice_message); ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="#eocrm-mortgage-advisor-contact" class="eocrm-offer-contact-form eocrm-mortgage-advisor-form" data-eocrm-mortgage-advisor-form>
                    <?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <input type="hidden" name="eocrm_offer_contact_action" value="mortgage_advisor_contact">
                    <input type="hidden" name="eocrm_offer_contact_property_id" value="<?php echo esc_attr((string) $property_id); ?>">

                    <input type="hidden" name="mortgage_calc_loan" data-eocrm-calc-export-field="loan" value="">
                    <input type="hidden" name="mortgage_calc_rate" data-eocrm-calc-export-field="rate" value="">
                    <input type="hidden" name="mortgage_calc_monthly" data-eocrm-calc-export-field="monthly" value="">
                    <input type="hidden" name="mortgage_calc_first" data-eocrm-calc-export-field="first" value="">
                    <input type="hidden" name="mortgage_calc_last" data-eocrm-calc-export-field="last" value="">
                    <input type="hidden" name="mortgage_calc_interest" data-eocrm-calc-export-field="interest" value="">
                    <input type="hidden" name="mortgage_calc_commission" data-eocrm-calc-export-field="commission" value="">
                    <input type="hidden" name="mortgage_calc_total" data-eocrm-calc-export-field="total" value="">
                    <input type="hidden" name="mortgage_calc_price" data-eocrm-calc-export-field="price" value="">
                    <input type="hidden" name="mortgage_calc_down_payment" data-eocrm-calc-export-field="down_payment" value="">
                    <input type="hidden" name="mortgage_calc_down_payment_percent" data-eocrm-calc-export-field="down_payment_percent" value="">
                    <input type="hidden" name="mortgage_calc_years" data-eocrm-calc-export-field="years" value="">
                    <input type="hidden" name="mortgage_calc_tenor" data-eocrm-calc-export-field="tenor" value="">
                    <input type="hidden" name="mortgage_calc_wibor_rate" data-eocrm-calc-export-field="wibor_rate" value="">
                    <input type="hidden" name="mortgage_calc_margin" data-eocrm-calc-export-field="margin" value="">
                    <input type="hidden" name="mortgage_calc_commission_percent" data-eocrm-calc-export-field="commission_percent" value="">
                    <input type="hidden" name="mortgage_calc_installment_type" data-eocrm-calc-export-field="installment_type" value="">
                    <input type="hidden" name="mortgage_calc_monthly_fees" data-eocrm-calc-export-field="monthly_fees" value="">
                    <input type="hidden" name="mortgage_calc_finance_commission" data-eocrm-calc-export-field="finance_commission" value="">

                    <p class="eocrm-form-field">
                        <label>Imię</label>
                        <input type="text" name="mortgage_contact_first_name" value="<?php echo esc_attr($first_name); ?>" required>
                    </p>
                    <p class="eocrm-form-field">
                        <label>Nazwisko</label>
                        <input type="text" name="mortgage_contact_last_name" value="<?php echo esc_attr($last_name); ?>" required>
                    </p>
                    <p class="eocrm-form-field">
                        <label>E-mail</label>
                        <input type="email" name="mortgage_contact_email" value="<?php echo esc_attr($email); ?>" required>
                    </p>
                    <p class="eocrm-form-field">
                        <label>Telefon</label>
                        <input type="text" name="mortgage_contact_phone" value="<?php echo esc_attr($phone); ?>">
                    </p>
                    <p class="eocrm-offer-contact-actions"><button class="eocrm-btn eocrm-btn-primary" type="submit">Poproś o kontakt</button></p>
                </form>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filter_offer_document_title_parts(array $parts): array
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $parts;
        }

        $property_id = $this->get_current_offer_page_property_id();
        if ($property_id <= 0) {
            return $parts;
        }

        $offer = $this->get_public_offer_by_property_id($property_id);
        if (! is_array($offer)) {
            return $parts;
        }

        $title = $this->build_offer_page_title($offer);
        if ($title === '') {
            return $parts;
        }

        $parts['title'] = $title;

        return $parts;
    }

    /**
     * Drugi argument moze byc pominiety przez zewnetrzne wywolania filtra.
     *
     * @param int|string $post_id
     */
    public function filter_offer_page_heading_title(string $title, $post_id = 0): string
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || ! is_singular('page')) {
            return $title;
        }

        $post_id = is_numeric($post_id) ? (int) $post_id : 0;
        if ($post_id <= 0) {
            return $title;
        }

        $queried_id = get_queried_object_id();
        if (! is_numeric($queried_id) || (int) $queried_id <= 0 || (int) $queried_id !== $post_id) {
            return $title;
        }

        $property_id = absint((string) get_post_meta($post_id, '_eocrm_property_id', true));
        if ($property_id <= 0) {
            return $title;
        }

        $offer = $this->get_public_offer_by_property_id($property_id);
        if (! is_array($offer)) {
            return $title;
        }

        $calculated_title = $this->build_offer_page_title($offer);
        return $calculated_title !== '' ? $calculated_title : $title;
    }

    private function should_register_public_title_filters(): bool
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        return true;
    }

    public function render_offices_agents_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([], $atts, 'eocrm_offices_agents');
        unset($atts);

        $offices = $this->get_public_offices(200);
        $agents = $this->get_public_agents(500);
        $office_name = $this->get_setting_value('office_name');
        $office_logo_attachment_id = absint($this->get_setting_value('office_logo_attachment_id'));
        $office_logo_url = $office_logo_attachment_id > 0 ? wp_get_attachment_image_url($office_logo_attachment_id, 'thumbnail') : '';
        $offices_agents_page_title = trim($this->get_setting_value('offices_agents_page_title'));
        $offices_agents_page_intro = trim($this->get_setting_value('offices_agents_page_intro'));

        if ($offices_agents_page_title === '') {
            $offices_agents_page_title = 'Biura i Agenci';
        }
        if ($offices_agents_page_intro === '') {
            $offices_agents_page_intro = 'Poznaj nasz zespol doradcow nieruchomosci i sprawdz, kto prowadzi oferty w Twojej okolicy.';
        }

        if ($office_name === '') {
            $office_name = sanitize_text_field((string) get_bloginfo('name'));
        }

        $office_ids = [];
        foreach ($offices as $office) {
            $office_id = isset($office['id']) ? (int) $office['id'] : 0;
            if ($office_id > 0) {
                $office_ids[$office_id] = true;
            }
        }

        $grouped_agents = [];
        $unassigned_agents = [];
        foreach ($agents as $agent) {
            $office_id = isset($agent['office_id']) ? (int) $agent['office_id'] : 0;
            if ($office_id <= 0 || ! isset($office_ids[$office_id])) {
                $unassigned_agents[] = $agent;
                continue;
            }

            if (! isset($grouped_agents[$office_id])) {
                $grouped_agents[$office_id] = [];
            }
            $grouped_agents[$office_id][] = $agent;
        }

        foreach ($grouped_agents as &$office_agents) {
            if (! is_array($office_agents)) {
                $office_agents = [];
                continue;
            }
            usort($office_agents, [$this, 'sort_public_agents_by_role_and_order']);
        }
        unset($office_agents);
        usort($unassigned_agents, [$this, 'sort_public_agents_by_role_and_order']);

        wp_enqueue_style('eocrm-frontend');
        $this->apply_frontend_width_style();

        ob_start();
        include EOCRM_PATH . 'templates/public-offices-agents.php';

        return $this->translate_output((string) ob_get_clean());
    }

    private function get_current_offer_page_property_id(): int
    {
        if (! is_singular('page')) {
            return 0;
        }

        $page_id = get_queried_object_id();
        if (! is_numeric($page_id) || (int) $page_id <= 0) {
            return 0;
        }

        return absint((string) get_post_meta((int) $page_id, '_eocrm_property_id', true));
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function build_offer_page_title(array $offer): string
    {
        $street = sanitize_text_field((string) ($offer['street'] ?? ''));
        $building_no = sanitize_text_field((string) ($offer['building_no'] ?? ''));
        $property_type = strtoupper(sanitize_text_field((string) ($offer['property_type'] ?? '')));
        $city = sanitize_text_field((string) ($offer['city'] ?? ''));
        $offer_number = sanitize_text_field((string) ($offer['offer_number'] ?? ''));
        $show_offer_number_in_title = $this->should_show_offer_number_in_title();
        if (! $show_offer_number_in_title) {
            $offer_number = '';
        }

        $show_building_no = ! in_array($property_type, ['DOM', 'DZIALKA'], true);
        $address = trim($street . ($show_building_no && $building_no !== '' ? ' ' . $building_no : ''));
        if ($city !== '') {
            $address = $address !== '' ? ($address . ', ' . $city) : $city;
        }

        if ($address === '') {
            return $offer_number !== '' ? ('Oferta (' . $offer_number . ')') : 'Oferta';
        }

        return $offer_number !== '' ? ($address . ' (' . $offer_number . ')') : $address;
    }

    private function should_show_offer_number_in_title(): bool
    {
        return $this->get_setting_value('offer_template_show_offer_number_in_title') !== '0';
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function ensure_current_offer_page_title(int $property_id, array $offer): void
    {
        $page_id = get_queried_object_id();
        if (! is_numeric($page_id) || (int) $page_id <= 0) {
            return;
        }
        $page_id = (int) $page_id;

        $meta_property_id = absint((string) get_post_meta($page_id, '_eocrm_property_id', true));
        if ($meta_property_id <= 0 || $meta_property_id !== $property_id) {
            return;
        }

        $target_title = $this->build_offer_page_title($offer);
        if ($target_title === '') {
            return;
        }

        $current_title = get_post_field('post_title', $page_id);
        if (! is_string($current_title) || $current_title === $target_title) {
            return;
        }

        wp_update_post(
            wp_slash([
                'ID' => $page_id,
                'post_title' => $target_title,
            ]),
            true
        );
    }

    /**
     * @param array<int, WP_Post> $items
     * @return array<int, WP_Post>
     */
    public function filter_public_menu_items(array $items, $args): array
    {
        unset($args);

        if (is_admin() || wp_doing_ajax() || is_user_logged_in() || ! $this->should_hide_crm_menu_for_guests()) {
            return $items;
        }

        $restricted_pages = $this->get_restricted_crm_pages();
        $restricted_page_ids = $restricted_pages['ids'];
        $restricted_urls = $restricted_pages['urls'];

        if (empty($restricted_page_ids) && empty($restricted_urls)) {
            return $items;
        }

        $hidden_item_ids = [];
        foreach ($items as $item) {
            if (! $item instanceof WP_Post) {
                continue;
            }

            $object_id = isset($item->object_id) ? (int) $item->object_id : 0;
            $should_hide = in_array($object_id, $restricted_page_ids, true);

            if (! $should_hide && ! empty($restricted_urls) && isset($item->url) && is_string($item->url)) {
                foreach ($restricted_urls as $restricted_url) {
                    if ($this->is_same_menu_url((string) $item->url, $restricted_url)) {
                        $should_hide = true;
                        break;
                    }
                }
            }

            if ($should_hide) {
                $hidden_item_ids[(int) $item->ID] = true;
            }
        }

        if (empty($hidden_item_ids)) {
            return $items;
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($items as $item) {
                if (! $item instanceof WP_Post) {
                    continue;
                }

                $item_id = (int) $item->ID;
                $parent_id = isset($item->menu_item_parent) ? (int) $item->menu_item_parent : 0;

                if (isset($hidden_item_ids[$item_id])) {
                    continue;
                }

                if ($parent_id > 0 && isset($hidden_item_ids[$parent_id])) {
                    $hidden_item_ids[$item_id] = true;
                    $changed = true;
                }
            }
        }

        $filtered = [];
        foreach ($items as $item) {
            if (! $item instanceof WP_Post) {
                continue;
            }

            if (isset($hidden_item_ids[(int) $item->ID])) {
                continue;
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * @param int[] $exclude
     * @return int[]
     */
    public function filter_public_page_list_excludes(array $exclude, $args = null): array
    {
        unset($args);

        if (is_admin() || wp_doing_ajax() || is_user_logged_in() || ! $this->should_hide_crm_menu_for_guests()) {
            return $exclude;
        }

        $restricted_pages = $this->get_restricted_crm_pages();
        if (empty($restricted_pages['ids'])) {
            return $exclude;
        }

        $normalized_exclude = [];
        foreach ($exclude as $exclude_id) {
            $exclude_id = (int) $exclude_id;
            if ($exclude_id > 0) {
                $normalized_exclude[] = $exclude_id;
            }
        }

        return array_values(array_unique(array_merge($normalized_exclude, $restricted_pages['ids'])));
    }

    /**
     * @param array<string, mixed> $block
     */
    public function filter_public_navigation_blocks(string $block_content, array $block): string
    {
        if (is_admin() || wp_doing_ajax() || is_user_logged_in() || ! $this->should_hide_crm_menu_for_guests()) {
            return $block_content;
        }

        $block_name = isset($block['blockName']) ? (string) $block['blockName'] : '';
        if ($block_name !== 'core/navigation-link') {
            return $block_content;
        }

        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        if (empty($attrs)) {
            return $block_content;
        }

        $restricted_pages = $this->get_restricted_crm_pages();
        $restricted_page_ids = $restricted_pages['ids'];
        $restricted_urls = $restricted_pages['urls'];

        $menu_page_id = isset($attrs['id']) ? (int) $attrs['id'] : 0;
        if ($menu_page_id > 0 && in_array($menu_page_id, $restricted_page_ids, true)) {
            return '';
        }

        $menu_url = isset($attrs['url']) ? (string) $attrs['url'] : '';
        if ($menu_url !== '' && ! empty($restricted_urls)) {
            foreach ($restricted_urls as $restricted_url) {
                if ($this->is_same_menu_url($menu_url, $restricted_url)) {
                    return '';
                }
            }
        }

        return $block_content;
    }

    /**
     * @return array{ids: array<int, int>, urls: array<int, string>}
     */
    private function get_restricted_crm_pages(): array
    {
        $restricted_keys = ['crm', 'dashboard', 'properties', 'searches', 'agreements', 'transactions', 'clients'];
        $slug_by_key = [
            'crm' => 'crm',
            'dashboard' => 'crm-pulpit',
            'properties' => 'crm-nieruchomosci',
            'searches' => 'crm-poszukiwania',
            'agreements' => 'crm-umowy',
            'transactions' => 'crm-transakcje',
            'clients' => 'crm-klienci',
        ];

        $page_ids = get_option(EstateOfficeCRM_Installer::OPTION_PAGE_IDS, []);
        $restricted_page_ids = [];

        foreach ($restricted_keys as $restricted_key) {
            $candidate_id = is_array($page_ids) && isset($page_ids[$restricted_key]) ? (int) $page_ids[$restricted_key] : 0;

            if ($candidate_id <= 0 && isset($slug_by_key[$restricted_key])) {
                $legacy_page = get_page_by_path((string) $slug_by_key[$restricted_key], OBJECT, 'page');
                if ($legacy_page instanceof WP_Post) {
                    $candidate_id = (int) $legacy_page->ID;
                }
            }

            if ($candidate_id > 0) {
                $restricted_page_ids[] = $candidate_id;
            }
        }

        $restricted_page_ids = array_values(array_unique($restricted_page_ids));

        $restricted_urls = [];
        foreach ($restricted_page_ids as $restricted_page_id) {
            $page_url = get_permalink($restricted_page_id);
            if (is_string($page_url) && $page_url !== '') {
                $restricted_urls[] = $page_url;
            }
        }

        return [
            'ids' => $restricted_page_ids,
            'urls' => array_values(array_unique($restricted_urls)),
        ];
    }

    private function should_hide_crm_menu_for_guests(): bool
    {
        return (bool) apply_filters('eocrm_hide_crm_menu_for_guests', false);
    }

    private function is_same_menu_url(string $left, string $right): bool
    {
        $left_path = (string) wp_parse_url($left, PHP_URL_PATH);
        $right_path = (string) wp_parse_url($right, PHP_URL_PATH);

        if ($left_path === '' || $right_path === '') {
            return false;
        }

        return untrailingslashit($left_path) === untrailingslashit($right_path);
    }

    private function resolve_plugin_page_url(string $page_key, string $slug_fallback): string
    {
        $page_ids = get_option(EstateOfficeCRM_Installer::OPTION_PAGE_IDS, []);
        if (! is_array($page_ids)) {
            $page_ids = [];
        }

        $page_id = isset($page_ids[$page_key]) ? (int) $page_ids[$page_key] : 0;

        if ($page_id <= 0 && $slug_fallback !== '') {
            $page_object = get_page_by_path($slug_fallback, OBJECT, 'page');
            if ($page_object instanceof WP_Post) {
                $page_id = (int) $page_object->ID;
            }
        }

        if ($page_id <= 0) {
            return '';
        }

        $permalink = get_permalink($page_id);
        return is_string($permalink) ? $permalink : '';
    }

    private function can_access_crm(): bool
    {
        return current_user_can('eocrm_access_crm') || current_user_can('manage_options');
    }

    /**
     * @return array{sql:string,params:array<int,int>}
     */
    private function owner_scope(string $owner_column = 'owner_user_id'): array
    {
        return EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, $owner_column, get_current_user_id());
    }

    private function can_access_owner_user_id(int $owner_user_id): bool
    {
        return EstateOfficeCRM_Access::can_access_owner_user($owner_user_id, $this->tables, get_current_user_id());
    }

    private function is_current_user_manager(): bool
    {
        return EstateOfficeCRM_Access::is_manager_user(get_current_user_id());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_frontend_manageable_offices(bool $include_inactive = true): array
    {
        global $wpdb;

        $offices_table = $this->tables['offices'];
        $profiles_table = $this->tables['agent_profiles'];
        $params = [];
        $sql = "SELECT id, office_name, office_email, office_phone, address_line, city, postal_code, description, logo_id, is_active, is_public, display_order
            FROM {$offices_table}
            WHERE 1=1";

        if (! current_user_can('manage_options')) {
            $manager_office_id = EstateOfficeCRM_Access::get_user_office_id(get_current_user_id(), $this->tables);
            if ($manager_office_id <= 0) {
                return [];
            }
            $sql .= ' AND id = %d';
            $params[] = $manager_office_id;
        }

        if (! $include_inactive) {
            $sql .= ' AND is_active = %d';
            $params[] = 1;
        }

        $sql .= ' ORDER BY display_order ASC, office_name ASC, id ASC';
        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL uses internal table names and prepared dynamic values.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- internal table name, aggregate without user input.
        $count_rows = $wpdb->get_results("SELECT office_id, COUNT(*) AS total FROM {$profiles_table} WHERE office_id IS NOT NULL GROUP BY office_id", ARRAY_A);
        $agent_counts = [];
        if (is_array($count_rows)) {
            foreach ($count_rows as $count_row) {
                $agent_counts[(int) ($count_row['office_id'] ?? 0)] = (int) ($count_row['total'] ?? 0);
            }
        }

        foreach ($rows as &$row) {
            $office_id = (int) ($row['id'] ?? 0);
            $logo_id = isset($row['logo_id']) ? absint((string) $row['logo_id']) : 0;
            $row['logo_url'] = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'medium') : '';
            $row['agent_count'] = $agent_counts[$office_id] ?? 0;
            $row['display_order'] = isset($row['display_order']) ? (int) $row['display_order'] : 100;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_frontend_manageable_agents(): array
    {
        global $wpdb;

        $is_admin_user = current_user_can('manage_options');
        $manager_office_id = $is_admin_user ? 0 : EstateOfficeCRM_Access::get_user_office_id(get_current_user_id(), $this->tables);
        if (! $is_admin_user && $manager_office_id <= 0) {
            return [];
        }

        $users = get_users([
            'role__in' => [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 1000,
        ]);
        if (empty($users)) {
            return [];
        }

        $user_ids = [];
        foreach ($users as $user) {
            if ($user instanceof WP_User) {
                $user_ids[] = (int) $user->ID;
            }
        }
        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        $profiles_by_user_id = [];
        $profiles_table = $this->tables['agent_profiles'];
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $profile_sql = $wpdb->prepare("SELECT * FROM {$profiles_table} WHERE user_id IN ({$placeholders})", $user_ids);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- placeholders prepared from absint user ids.
        $profile_rows = $wpdb->get_results($profile_sql, ARRAY_A);
        if (is_array($profile_rows)) {
            foreach ($profile_rows as $profile_row) {
                $profile_user_id = isset($profile_row['user_id']) ? (int) $profile_row['user_id'] : 0;
                if ($profile_user_id > 0) {
                    $profiles_by_user_id[$profile_user_id] = $profile_row;
                }
            }
        }

        $offices_by_id = [];
        foreach ($this->get_frontend_manageable_offices(true) as $office_row) {
            $office_id = isset($office_row['id']) ? (int) $office_row['id'] : 0;
            if ($office_id > 0) {
                $offices_by_id[$office_id] = $office_row;
            }
        }

        $result = [];
        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            $profile = $profiles_by_user_id[$user_id] ?? [];
            $office_id = isset($profile['office_id']) ? (int) $profile['office_id'] : 0;
            if (! $is_admin_user && $office_id !== $manager_office_id) {
                continue;
            }

            $photo_id = isset($profile['photo_id']) ? absint((string) $profile['photo_id']) : 0;
            $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'medium') : '';
            if ((! is_string($photo_url) || $photo_url === '') && $photo_id > 0) {
                $photo_url = wp_get_attachment_image_url($photo_id, 'full');
            }

            $crm_role = $this->extract_frontend_crm_role_from_user($user);
            $result[] = [
                'id' => $user_id,
                'user_login' => (string) $user->user_login,
                'display_name' => (string) $user->display_name,
                'user_email' => (string) $user->user_email,
                'crm_role' => $crm_role,
                'role_label' => $this->get_frontend_crm_role_label($crm_role),
                'phone' => isset($profile['phone']) ? (string) $profile['phone'] : '',
                'address_line' => isset($profile['address_line']) ? (string) $profile['address_line'] : '',
                'city' => isset($profile['city']) ? (string) $profile['city'] : '',
                'postal_code' => isset($profile['postal_code']) ? (string) $profile['postal_code'] : '',
                'bio' => isset($profile['bio']) ? (string) $profile['bio'] : '',
                'photo_id' => $photo_id,
                'photo_url' => is_string($photo_url) ? $photo_url : '',
                'office_id' => $office_id,
                'office_name' => isset($offices_by_id[$office_id]) ? (string) ($offices_by_id[$office_id]['office_name'] ?? '') : '',
                'is_public' => isset($profile['is_public']) ? (int) $profile['is_public'] : 1,
                'display_order' => isset($profile['display_order']) ? (int) $profile['display_order'] : 100,
                'can_edit' => $this->can_current_user_edit_frontend_agent($user_id),
            ];
        }

        usort($result, static function (array $a, array $b): int {
            $office_cmp = ((int) ($a['office_id'] ?? 0)) <=> ((int) ($b['office_id'] ?? 0));
            if ($office_cmp !== 0) {
                return $office_cmp;
            }

            $manager_cmp = ((string) ($b['crm_role'] ?? '') === EstateOfficeCRM_Installer::ROLE_MANAGER ? 1 : 0)
                <=> ((string) ($a['crm_role'] ?? '') === EstateOfficeCRM_Installer::ROLE_MANAGER ? 1 : 0);
            if ($manager_cmp !== 0) {
                return $manager_cmp;
            }

            $order_cmp = ((int) ($a['display_order'] ?? 100)) <=> ((int) ($b['display_order'] ?? 100));
            if ($order_cmp !== 0) {
                return $order_cmp;
            }

            return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get_frontend_agent_for_edit(int $user_id): ?array
    {
        if ($user_id <= 0 || ! $this->can_current_user_edit_frontend_agent($user_id)) {
            return null;
        }

        foreach ($this->get_frontend_manageable_agents() as $agent_row) {
            if ((int) ($agent_row['id'] ?? 0) === $user_id) {
                return $agent_row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get_frontend_office_for_edit(int $office_id): ?array
    {
        if ($office_id <= 0 || ! current_user_can('manage_options')) {
            return null;
        }

        foreach ($this->get_frontend_manageable_offices(true) as $office_row) {
            if ((int) ($office_row['id'] ?? 0) === $office_id) {
                return $office_row;
            }
        }

        return null;
    }

    private function can_current_user_edit_frontend_agent(int $user_id): bool
    {
        if ($user_id <= 0 || ! EstateOfficeCRM_Access::is_agent_or_manager_user($user_id)) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        if (! $this->is_current_user_manager()) {
            return false;
        }

        $manager_office_id = EstateOfficeCRM_Access::get_user_office_id(get_current_user_id(), $this->tables);
        $agent_office_id = EstateOfficeCRM_Access::get_user_office_id($user_id, $this->tables);

        return $manager_office_id > 0 && $agent_office_id > 0 && $manager_office_id === $agent_office_id;
    }

    private function normalize_frontend_image_attachment_id(int $attachment_id): int
    {
        if ($attachment_id <= 0) {
            return 0;
        }

        $post = get_post($attachment_id);
        if (! $post instanceof WP_Post || $post->post_type !== 'attachment') {
            return 0;
        }

        $mime_type = get_post_mime_type($attachment_id);
        if (! is_string($mime_type) || strpos($mime_type, 'image/') !== 0) {
            return 0;
        }

        return $attachment_id;
    }

    private function normalize_frontend_office_id(int $office_id): int
    {
        if ($office_id <= 0) {
            return 0;
        }

        global $wpdb;
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['offices']} WHERE id = %d LIMIT 1",
                $office_id
            )
        );

        return $exists ? $office_id : 0;
    }

    private function sanitize_frontend_display_order(string $value): int
    {
        $value = trim($value);
        if ($value === '' || ! is_numeric($value)) {
            return 100;
        }

        return max(0, min(9999, (int) $value));
    }

    private function sanitize_frontend_crm_user_role(string $role): string
    {
        $role = sanitize_key($role);
        if (in_array($role, [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER], true)) {
            return $role;
        }

        return EstateOfficeCRM_Installer::ROLE_AGENT;
    }

    private function extract_frontend_crm_role_from_user(WP_User $user): string
    {
        $roles = is_array($user->roles) ? $user->roles : [];
        if (in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true)) {
            return EstateOfficeCRM_Installer::ROLE_MANAGER;
        }

        return EstateOfficeCRM_Installer::ROLE_AGENT;
    }

    private function get_frontend_crm_role_label(string $role): string
    {
        return $role === EstateOfficeCRM_Installer::ROLE_MANAGER ? 'Menedzer' : 'Agent';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsert_frontend_agent_profile(int $user_id, array $data): void
    {
        if ($user_id <= 0) {
            return;
        }

        global $wpdb;

        $table = $this->tables['agent_profiles'];
        $now = current_time('mysql');
        $payload = [
            'phone' => (string) ($data['phone'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'address_line' => (string) ($data['address_line'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'postal_code' => (string) ($data['postal_code'] ?? ''),
            'bio' => (string) ($data['bio'] ?? ''),
            'photo_id' => isset($data['photo_id']) ? (int) $data['photo_id'] : 0,
            'office_id' => isset($data['office_id']) ? (int) $data['office_id'] : 0,
            'is_public' => isset($data['is_public']) && (int) $data['is_public'] === 1 ? 1 : 0,
            'display_order' => isset($data['display_order']) ? $this->sanitize_frontend_display_order((string) $data['display_order']) : 100,
            'updated_at' => $now,
        ];

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        if ($exists) {
            $wpdb->update(
                $table,
                $payload,
                ['user_id' => $user_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'],
                ['%d']
            );
            return;
        }

        $payload['user_id'] = $user_id;
        $payload['created_at'] = $now;
        $wpdb->insert(
            $table,
            $payload,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s']
        );
    }

    private function send_frontend_onboarding_email_to_user(int $user_id): bool
    {
        $user = $user_id > 0 ? get_user_by('id', $user_id) : null;
        if (! $user instanceof WP_User) {
            return false;
        }

        $email = sanitize_email((string) $user->user_email);
        if ($email === '' || ! is_email($email)) {
            return false;
        }

        $role = $this->extract_frontend_crm_role_from_user($user);
        $links = $this->build_frontend_onboarding_links($user, $role);
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $display_name = trim((string) $user->display_name);
        if ($display_name === '') {
            $display_name = (string) $user->user_login;
        }

        $links_markup = '';
        foreach ($links as $link) {
            $label = sanitize_text_field((string) ($link['label'] ?? ''));
            $url = esc_url((string) ($link['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $links_markup .= '<tr><td style="padding:7px 0;"><a href="' . $url . '" style="display:block;padding:12px 15px;border-radius:12px;background:#f1f7ff;border:1px solid #d7e7f8;color:#0b5cad;text-decoration:none;font-weight:700;">' . esc_html($label) . '</a></td></tr>';
        }

        if ($links_markup === '') {
            $links_markup = '<tr><td style="padding:10px 0;color:#5d6d85;">Administrator nie wlaczyl jeszcze linkow onboardingowych dla tej roli.</td></tr>';
        }

        $login_url = $this->resolve_plugin_page_url('agent_login', 'panel-logowania-agenta');
        if ($login_url === '') {
            $login_url = wp_login_url();
        }

        $body = '<!doctype html><html><body style="margin:0;padding:0;background:#eef4fb;font-family:Arial,sans-serif;color:#10243d;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef4fb;padding:28px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #dbe7f4;">'
            . '<tr><td style="padding:24px 28px 10px;"><img src="' . esc_url(EOCRM_URL . 'assets/images/eocrm-logo-horizontal.png') . '" alt="Estate Office CRM" style="max-width:260px;max-height:72px;width:auto;height:auto;display:block;margin:0 0 18px;">'
            . '<p style="margin:0 0 8px;color:#0b5cad;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Onboarding ' . esc_html($this->get_frontend_crm_role_label($role)) . '</p>'
            . '<h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#10243d;">Witaj w Estate Office CRM</h1>'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#40536a;">Czesc ' . esc_html($display_name) . ', przygotowalismy dla Ciebie najwazniejsze linki startowe do pracy w systemie CRM dla strony <strong>' . esc_html($site_name) . '</strong>.</p>'
            . '</td></tr><tr><td style="padding:0 28px 8px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $links_markup . '</table></td></tr>'
            . '<tr><td style="padding:12px 28px 22px;"><div style="border-top:1px solid #e1e9f3;padding-top:16px;">'
            . '<p style="margin:0 0 8px;font-size:14px;color:#40536a;"><strong>Login:</strong> ' . esc_html((string) $user->user_login) . '</p>'
            . '<p style="margin:0;"><a href="' . esc_url($login_url) . '" style="display:inline-block;margin-right:8px;padding:10px 14px;border-radius:999px;background:#0b5cad;color:#fff;text-decoration:none;font-weight:700;">Przejdz do logowania</a>'
            . '<a href="' . esc_url(wp_lostpassword_url()) . '" style="display:inline-block;padding:10px 14px;border-radius:999px;background:#edf4ff;color:#0b5cad;text-decoration:none;font-weight:700;border:1px solid #cfe0f2;">Ustaw / zresetuj haslo</a></p>'
            . '</div></td></tr></table></td></tr></table></body></html>';

        return (bool) wp_mail(
            $email,
            '[' . $site_name . '] Onboarding Estate Office CRM',
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    /**
     * @return array<int, array{label:string,url:string}>
     */
    private function build_frontend_onboarding_links(WP_User $user, string $role): array
    {
        $role_key = $role === EstateOfficeCRM_Installer::ROLE_MANAGER ? 'manager' : 'agent';
        $config = $this->load_frontend_onboarding_config();
        $links = [];

        foreach ($this->frontend_onboarding_default_items() as $item_key => $item_label) {
            $item_config = isset($config['items'][$item_key]) && is_array($config['items'][$item_key])
                ? $config['items'][$item_key]
                : ['agent' => true, 'manager' => true];
            if (empty($item_config[$role_key])) {
                continue;
            }

            $url = $this->resolve_frontend_onboarding_item_url((string) $item_key, (int) $user->ID);
            if ($url !== '') {
                $links[] = ['label' => (string) $item_label, 'url' => $url];
            }
        }

        $custom_links = isset($config['custom_links']) && is_array($config['custom_links']) ? $config['custom_links'] : [];
        foreach ($custom_links as $custom_link) {
            if (! is_array($custom_link) || empty($custom_link[$role_key])) {
                continue;
            }
            $label = sanitize_text_field((string) ($custom_link['label'] ?? ''));
            $url = esc_url_raw((string) ($custom_link['url'] ?? ''));
            if ($label !== '' && $url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * @return array<string, string>
     */
    private function frontend_onboarding_default_items(): array
    {
        return [
            'agent_login' => 'Panel logowania',
            'agent_profile' => 'Profil Agenta',
            'offices_agents' => 'Agenci i biura',
            'crm_dashboard' => 'Panel CRM',
            'properties' => 'Nieruchomosci',
            'searches' => 'Poszukiwania',
            'clients' => 'Klienci',
            'agreements' => 'Umowy',
        ];
    }

    /**
     * @return array{items:array<string,array{agent:bool,manager:bool}>,custom_links:array<int,array{label:string,url:string,agent:bool,manager:bool}>}
     */
    private function load_frontend_onboarding_config(): array
    {
        $config = ['items' => [], 'custom_links' => []];
        foreach ($this->frontend_onboarding_default_items() as $key => $label) {
            $config['items'][(string) $key] = ['agent' => true, 'manager' => true];
        }

        $raw_config = $this->get_setting_value('onboarding_config_json');
        if ($raw_config === '') {
            return $config;
        }

        $decoded = json_decode($raw_config, true);
        if (! is_array($decoded)) {
            return $config;
        }

        $decoded_items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : [];
        foreach ($this->frontend_onboarding_default_items() as $key => $label) {
            $item_row = isset($decoded_items[$key]) && is_array($decoded_items[$key]) ? $decoded_items[$key] : [];
            $config['items'][(string) $key] = [
                'agent' => array_key_exists('agent', $item_row) ? ! empty($item_row['agent']) : true,
                'manager' => array_key_exists('manager', $item_row) ? ! empty($item_row['manager']) : true,
            ];
        }

        $decoded_links = isset($decoded['custom_links']) && is_array($decoded['custom_links']) ? $decoded['custom_links'] : [];
        foreach ($decoded_links as $link_row) {
            if (! is_array($link_row)) {
                continue;
            }
            $label = sanitize_text_field((string) ($link_row['label'] ?? ''));
            $url = esc_url_raw((string) ($link_row['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $config['custom_links'][] = [
                'label' => $label,
                'url' => $url,
                'agent' => array_key_exists('agent', $link_row) ? ! empty($link_row['agent']) : true,
                'manager' => array_key_exists('manager', $link_row) ? ! empty($link_row['manager']) : true,
            ];
        }

        return $config;
    }

    private function resolve_frontend_onboarding_item_url(string $item_key, int $user_id): string
    {
        $crm_url = $this->resolve_plugin_page_url('crm', 'crm');
        switch ($item_key) {
            case 'agent_login':
                $url = $this->resolve_plugin_page_url('agent_login', 'panel-logowania-agenta');
                return $url !== '' ? $url : wp_login_url();
            case 'agent_profile':
                $url = $this->resolve_plugin_page_url('offices_agents', 'biura-i-agenci');
                return $url !== '' ? $url . '#eocrm-agent-' . max(0, $user_id) : '';
            case 'offices_agents':
                return $this->resolve_plugin_page_url('offices_agents', 'biura-i-agenci');
            case 'crm_dashboard':
                return $crm_url;
            case 'properties':
            case 'searches':
            case 'clients':
            case 'agreements':
                return $crm_url !== '' ? add_query_arg('crm', $item_key, $crm_url) : '';
            default:
                return '';
        }
    }

    private function count_records(string $table, string $where = '1=1', array $params = []): int
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL uses internal table names and controlled where fragments.
        return max(0, (int) $wpdb->get_var($sql));
    }

    /**
     * @param array<int, scalar|null> $owner_scope_params
     */
    private function count_active_searches(string $owner_scope_sql = '', array $owner_scope_params = []): int
    {
        global $wpdb;

        $searches_table = $this->tables['searches'];
        $agreements_table = $this->tables['agreements'];
        $params = $owner_scope_params;

        $sql = "SELECT COUNT(*)
            FROM {$searches_table} s
            LEFT JOIN {$agreements_table} a ON a.id = s.agreement_id
            WHERE s.is_active = 1
                AND (a.id IS NULL OR (a.is_active = 1 AND " . $this->agreement_not_finished_sql('a.current_stage') . "))";

        if ($owner_scope_sql !== '') {
            $sql .= str_replace('owner_user_id', 's.owner_user_id', $owner_scope_sql);
        }

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL uses internal table names and controlled where fragments.
        return max(0, (int) $wpdb->get_var($sql));
    }

    private function agreement_not_finished_sql(string $column): string
    {
        return "COALESCE(LOWER(TRIM({$column})), '') NOT LIKE 'umowa zako%czona'";
    }

    /**
     * @return array<int, array{key:string,label:string,period:string,transactions:int,totals:array<string,float>,agents:array<int,array{user_id:int,name:string,transactions:int,totals:array<string,float>}>}>
     */
    private function get_dashboard_gross_remuneration(): array
    {
        global $wpdb;

        $table = $this->tables['transactions'];
        $users_table = $wpdb->users;
        $owner_scope = $this->owner_scope('t.owner_user_id');
        $month_start = current_datetime()->modify('first day of this month')->setTime(0, 0, 0);
        $labels = [
            'Bie&#380;&#261;cy miesi&#261;c',
            'Poprzedni miesi&#261;c',
            '2 miesi&#261;ce temu',
        ];
        $result = [];

        for ($offset = 0; $offset < 3; $offset++) {
            $period_start = $month_start->modify('-' . $offset . ' months');
            $period_end = $period_start->modify('last day of this month')->setTime(23, 59, 59);
            $start_date = $period_start->format('Y-m-d');
            $end_date = $period_end->format('Y-m-d');
            $params = [$start_date, $end_date];

            $sql = "SELECT
                    t.transaction_price,
                    t.price_currency,
                    t.commission_amount,
                    t.commission_unit,
                    t.owner_user_id,
                    u.display_name AS owner_display_name
                FROM {$table} t
                LEFT JOIN {$users_table} u ON u.ID = t.owner_user_id
                WHERE t.is_active = 1
                AND t.transaction_date >= %s
                AND t.transaction_date <= %s" . $owner_scope['sql'];
            $params = array_merge($params, $owner_scope['params']);
            $sql = $wpdb->prepare($sql, $params);

            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
            $rows = $wpdb->get_results($sql, ARRAY_A);
            $rows = is_array($rows) ? $rows : [];
            $totals = [];
            $agent_totals = [];
            $transaction_count = 0;

            foreach ($rows as $row) {
                $transaction_price = isset($row['transaction_price']) && is_numeric((string) $row['transaction_price']) ? (float) $row['transaction_price'] : 0.0;
                $commission_amount = isset($row['commission_amount']) && is_numeric((string) $row['commission_amount']) ? (float) $row['commission_amount'] : 0.0;
                $commission_unit = strtoupper(trim((string) ($row['commission_unit'] ?? '')));
                if ($commission_amount <= 0 || $commission_unit === '') {
                    continue;
                }

                $currency = strtoupper(trim((string) ($row['price_currency'] ?? 'PLN')));
                if (! in_array($currency, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
                    $currency = 'PLN';
                }

                if ($commission_unit === '%') {
                    if ($transaction_price <= 0) {
                        continue;
                    }
                    $amount = ($transaction_price * $commission_amount) / 100;
                } elseif (in_array($commission_unit, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
                    $currency = $commission_unit;
                    $amount = $commission_amount;
                } else {
                    continue;
                }

                if (! isset($totals[$currency])) {
                    $totals[$currency] = 0.0;
                }
                $totals[$currency] += round($amount, 2);
                $transaction_count++;

                $agent_user_id = isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0;
                $agent_key = $agent_user_id > 0 ? $agent_user_id : 0;
                if (! isset($agent_totals[$agent_key])) {
                    $agent_name = trim((string) ($row['owner_display_name'] ?? ''));
                    $agent_totals[$agent_key] = [
                        'user_id' => $agent_user_id,
                        'name' => $agent_name !== '' ? $agent_name : 'Brak opiekuna',
                        'transactions' => 0,
                        'totals' => [],
                    ];
                }
                if (! isset($agent_totals[$agent_key]['totals'][$currency])) {
                    $agent_totals[$agent_key]['totals'][$currency] = 0.0;
                }
                $agent_totals[$agent_key]['totals'][$currency] += round($amount, 2);
                $agent_totals[$agent_key]['transactions']++;
            }

            ksort($totals);
            foreach ($agent_totals as &$agent_total) {
                ksort($agent_total['totals']);
            }
            unset($agent_total);
            uasort(
                $agent_totals,
                static function (array $left, array $right): int {
                    $left_sum = array_sum(array_map('floatval', $left['totals']));
                    $right_sum = array_sum(array_map('floatval', $right['totals']));
                    if ($left_sum !== $right_sum) {
                        return $right_sum <=> $left_sum;
                    }

                    return strcasecmp((string) $left['name'], (string) $right['name']);
                }
            );
            $result[] = [
                'key' => $period_start->format('Y-m'),
                'label' => $labels[$offset],
                'period' => date_i18n('F Y', $period_start->getTimestamp()),
                'transactions' => $transaction_count,
                'totals' => $totals,
                'agents' => array_values($agent_totals),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_top_agents(int $limit, string $owner_scope_sql = '', array $owner_scope_params = [], array $visible_user_ids = []): array
    {
        global $wpdb;

        $agreements_table = $this->tables['agreements'];
        $users_table = $wpdb->users;
        $visible_user_ids = array_values(array_unique(array_filter(array_map('absint', $visible_user_ids))));
        $limit = max(1, min(! empty($visible_user_ids) ? 500 : 20, $limit));
        if (! empty($visible_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($visible_user_ids), '%d'));
            $params = $visible_user_ids;
            $sql = "SELECT u.display_name, COUNT(a.id) AS total
                FROM {$users_table} u
                LEFT JOIN {$agreements_table} a
                    ON a.owner_user_id = u.ID
                    AND a.is_active = 1
                    AND " . $this->agreement_not_finished_sql('a.current_stage') . "
                WHERE u.ID IN ({$placeholders})
                GROUP BY u.ID, u.display_name
                ORDER BY total DESC, u.display_name ASC
                LIMIT %d";
            $params[] = $limit;
            $sql = $wpdb->prepare($sql, $params);

            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
            $rows = $wpdb->get_results($sql, ARRAY_A);

            return is_array($rows) ? $rows : [];
        }

        $params = $owner_scope_params;
        $sql = "SELECT u.display_name, COUNT(a.id) AS total
            FROM {$agreements_table} a
            LEFT JOIN {$users_table} u ON u.ID = a.owner_user_id
            WHERE a.is_active = 1
                AND " . $this->agreement_not_finished_sql('a.current_stage') . "{$owner_scope_sql}
            GROUP BY a.owner_user_id
            ORDER BY total DESC, u.display_name ASC
            LIMIT %d";
        $params[] = $limit;
        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list_properties(string $search, int $limit): array
    {
        global $wpdb;

        $table = $this->tables['properties'];
        $agreements_table = $this->tables['agreements'];
        $limit = max(1, min(500, $limit));

        $sql = "SELECT
                p.id,
                p.offer_number,
                p.transaction_type,
                p.property_type,
                p.house_type,
                p.street,
                p.building_no,
                p.apartment_no,
                p.city,
                p.district,
                p.price,
                p.price_currency,
                p.price_per_m2,
                p.area,
                p.rooms,
                p.floor_no,
                p.floors_total,
                p.owner_user_id,
                p.export_www,
                p.export_portals,
                p.is_sold,
                p.is_rented,
                p.is_premium,
                p.is_exclusive,
                p.tags_json,
                p.updated_at,
                p.agreement_id,
                a.date_signed AS agreement_date_signed,
                a.current_stage AS agreement_stage,
                a.is_active AS agreement_is_active
            FROM {$table} p
            LEFT JOIN {$agreements_table} a ON a.id = p.agreement_id
            WHERE p.is_active = 1";

        $params = [];

        $owner_scope = $this->owner_scope('p.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        if ($search !== '') {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (
                p.offer_number LIKE %s
                OR p.street LIKE %s
                OR p.building_no LIKE %s
                OR p.apartment_no LIKE %s
                OR p.city LIKE %s
                OR CAST(p.price AS CHAR) LIKE %s
                OR CAST(p.price_per_m2 AS CHAR) LIKE %s
                OR CAST(p.area AS CHAR) LIKE %s
                OR CAST(p.rooms AS CHAR) LIKE %s
            )";
            for ($i = 0; $i < 9; $i++) {
                $params[] = $search_like;
            }
        }

        $sql .= ' ORDER BY COALESCE(a.date_signed, DATE(p.created_at)) DESC, p.id DESC LIMIT %d';
        $params[] = $limit;

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list_searches(string $search, int $limit): array
    {
        global $wpdb;

        $table = $this->tables['searches'];
        $agreements_table = $this->tables['agreements'];
        $users_table = $wpdb->users;
        $limit = max(1, min(500, $limit));

        $sql = "SELECT
                s.id,
                s.search_number,
                s.property_type,
                s.budget_from,
                s.budget_to,
                s.area_from,
                s.area_to,
                s.rooms_from,
                s.rooms_to,
                s.floor_from,
                s.floor_to,
                s.location_text,
                s.transaction_type,
                s.agreement_id,
                s.owner_user_id,
                s.created_at,
                s.updated_at,
                u.display_name AS owner_display_name,
                a.date_signed AS agreement_date_signed,
                a.current_stage AS agreement_stage,
                a.is_active AS agreement_is_active,
                a.agreement_number AS agreement_number
            FROM {$table} s
            LEFT JOIN {$users_table} u ON u.ID = s.owner_user_id
            LEFT JOIN {$agreements_table} a ON a.id = s.agreement_id
            WHERE s.is_active = 1";

        $params = [];

        if ($search !== '') {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (
                s.search_number LIKE %s
                OR s.property_type LIKE %s
                OR CAST(s.budget_from AS CHAR) LIKE %s
                OR CAST(s.budget_to AS CHAR) LIKE %s
                OR s.location_text LIKE %s
                OR s.transaction_type LIKE %s
                OR u.display_name LIKE %s
            )";
            for ($i = 0; $i < 7; $i++) {
                $params[] = $search_like;
            }
        }

        $sql .= ' ORDER BY COALESCE(a.date_signed, DATE(s.created_at)) DESC, s.id DESC LIMIT %d';
        $params[] = $limit;

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list_agreements(string $search, int $limit): array
    {
        global $wpdb;

        $agreements = $this->tables['agreements'];
        $properties = $this->tables['properties'];
        $searches = $this->tables['searches'];
        $limit = max(1, min(500, $limit));

        $sql = "SELECT
                a.id,
                a.agreement_number,
                a.transaction_type,
                a.date_signed,
                a.date_end,
                a.is_indefinite,
                a.is_exclusive,
                a.commission_amount,
                a.commission_unit,
                a.current_stage,
                a.owner_user_id,
                a.created_at,
                a.updated_at,
                p.id AS property_id,
                COALESCE(p.property_type, s.property_type) AS property_type,
                s.criteria_json AS search_criteria_json,
                CONCAT_WS(' ', p.street, p.building_no) AS property_address_line,
                p.city AS property_city
            FROM {$agreements} a
            LEFT JOIN {$properties} p
                ON p.id = (
                    SELECT p2.id
                    FROM {$properties} p2
                    WHERE p2.agreement_id = a.id
                    ORDER BY p2.id ASC
                    LIMIT 1
                )
            LEFT JOIN {$searches} s
                ON s.id = (
                    SELECT s2.id
                    FROM {$searches} s2
                    WHERE s2.agreement_id = a.id
                    AND s2.is_active = 1
                    ORDER BY s2.id ASC
                    LIMIT 1
                )
            WHERE a.is_active = 1";

        $params = [];

        $owner_scope = $this->owner_scope('a.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        if ($search !== '') {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (
                a.agreement_number LIKE %s
                OR a.transaction_type LIKE %s
                OR COALESCE(p.property_type, s.property_type) LIKE %s
                OR p.street LIKE %s
                OR p.building_no LIKE %s
                OR p.city LIKE %s
                OR a.current_stage LIKE %s
            )";
            for ($i = 0; $i < 7; $i++) {
                $params[] = $search_like;
            }
        }

        $sql .= ' ORDER BY a.date_signed DESC, a.id DESC LIMIT %d';
        $params[] = $limit;

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $property_type = isset($row['property_type']) ? sanitize_text_field((string) $row['property_type']) : '';
            if ($property_type === '') {
                $criteria = json_decode((string) ($row['search_criteria_json'] ?? ''), true);
                if (is_array($criteria) && isset($criteria['property_type'])) {
                    $property_type = sanitize_text_field((string) $criteria['property_type']);
                }
            }

            $row['property_type'] = $property_type;
            unset($row['search_criteria_json']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list_transactions(string $search, int $limit): array
    {
        global $wpdb;

        $transactions = $this->tables['transactions'];
        $agreements = $this->tables['agreements'];
        $properties = $this->tables['properties'];
        $users_table = $wpdb->users;
        $limit = max(1, min(500, $limit));

        $sql = "SELECT
                t.*,
                a.current_stage AS agreement_stage,
                a.is_active AS agreement_is_active,
                p.offer_number,
                p.street,
                p.building_no,
                p.apartment_no,
                p.city,
                u.display_name AS owner_display_name,
                cu.display_name AS cooperation_user_display_name
            FROM {$transactions} t
            LEFT JOIN {$agreements} a ON a.id = t.agreement_id
            LEFT JOIN {$properties} p ON p.id = t.property_id
            LEFT JOIN {$users_table} u ON u.ID = t.owner_user_id
            LEFT JOIN {$users_table} cu ON cu.ID = t.cooperation_agent_user_id
            WHERE t.is_active = 1";

        $params = [];
        $owner_scope = $this->owner_scope('t.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        if ($search !== '') {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (
                t.agreement_number LIKE %s
                OR t.property_label LIKE %s
                OR t.transaction_type LIKE %s
                OR p.offer_number LIKE %s
                OR p.street LIKE %s
                OR p.city LIKE %s
                OR u.display_name LIKE %s
                OR cu.display_name LIKE %s
                OR t.cooperation_office_name LIKE %s
                OR t.cooperation_agent_name LIKE %s
            )";
            for ($i = 0; $i < 10; $i++) {
                $params[] = $search_like;
            }
        }

        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC LIMIT %d';
        $params[] = $limit;

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list_agreements_for_property_form(int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(1000, $limit));
        $table = $this->tables['agreements'];

        $params = [];
        $sql = "SELECT id, agreement_number, transaction_type
            FROM {$table}
            WHERE is_active = 1
            AND transaction_type IN ('SPRZEDAZ', 'WYNAJEM')";

        $owner_scope = $this->owner_scope('owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        $sql .= ' ORDER BY id DESC LIMIT %d';
        $params[] = $limit;

        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function list_agreements_for_search_form(int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(1000, $limit));
        $table = $this->tables['agreements'];

        $params = [];
        $sql = "SELECT id, agreement_number, transaction_type
            FROM {$table}
            WHERE is_active = 1
            AND transaction_type IN ('KUPNO', 'NAJEM')";

        $owner_scope = $this->owner_scope('owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        $sql .= ' ORDER BY id DESC LIMIT %d';
        $params[] = $limit;

        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
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

    private function sanitize_export_retention_days_setting(string $value, int $default = 30): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return max(0, min(365, $default));
        }

        $days = (int) $raw;
        if ($days < 0) {
            return 0;
        }
        if ($days > 365) {
            return 365;
        }

        return $days;
    }

    /**
     * @return array{sql:string, params:array<int, string>}
     */
    private function build_sold_rented_www_visibility_condition(string $column_prefix = ''): array
    {
        $prefix = trim($column_prefix);
        if ($prefix !== '' && substr($prefix, -1) !== '.') {
            $prefix .= '.';
        }

        $days = $this->sanitize_export_retention_days_setting($this->get_setting_value('sold_rented_www_retention_days'), 30);
        if ($days <= 0) {
            return [
                'sql' => " AND {$prefix}is_sold = 0 AND {$prefix}is_rented = 0",
                'params' => [],
            ];
        }

        $cutoff = current_datetime()->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        return [
            'sql' => " AND (
                ({$prefix}is_sold = 0 AND {$prefix}is_rented = 0)
                OR (
                    ({$prefix}is_sold = 1 OR {$prefix}is_rented = 1)
                    AND {$prefix}updated_at >= %s
                )
            )",
            'params' => [$cutoff],
        ];
    }

    private function get_public_watermark_url(): string
    {
        $attachment_id = absint($this->get_setting_value('watermark_attachment_id'));
        if ($attachment_id <= 0) {
            return '';
        }

        $watermark_url = wp_get_attachment_image_url($attachment_id, 'full');
        if (! is_string($watermark_url) || $watermark_url === '') {
            $watermark_url = wp_get_attachment_url($attachment_id);
        }
        if (! is_string($watermark_url) || $watermark_url === '') {
            return '';
        }

        return esc_url_raw($watermark_url);
    }

    private function apply_frontend_width_style(): void
    {
        if ($this->frontend_width_style_applied) {
            return;
        }

        if (! wp_style_is('eocrm-frontend', 'enqueued')) {
            return;
        }

        $mode = $this->sanitize_frontend_width_mode_setting($this->get_setting_value('frontend_width_mode'));
        $percent = $this->sanitize_frontend_width_percent_setting($this->get_setting_value('frontend_width_percent'));
        $half_percent = $this->format_half_percent_for_css($percent);
        $selector = '.eocrm-wrap.eocrm-public-wrap, .eocrm-wrap[data-section]';

        if ($mode === 'plugin') {
            $css = $selector . ' { width: ' . (string) $percent . '% !important; max-width: ' . (string) $percent . '% !important; margin-left: auto !important; margin-right: auto !important; }';
        } else {
            $css = $selector . ' { width: ' . (string) $percent . 'vw !important; max-width: ' . (string) $percent . 'vw !important; margin-left: calc(50% - ' . $half_percent . 'vw) !important; margin-right: calc(50% - ' . $half_percent . 'vw) !important; border-left: 0 !important; border-right: 0 !important; border-radius: 0 !important; }';
            $css .= '@supports (width: 1dvw) { ' . $selector . ' { width: ' . (string) $percent . 'dvw !important; max-width: ' . (string) $percent . 'dvw !important; margin-left: calc(50% - ' . $half_percent . 'dvw) !important; margin-right: calc(50% - ' . $half_percent . 'dvw) !important; border-left: 0 !important; border-right: 0 !important; border-radius: 0 !important; } }';
        }

        wp_add_inline_style('eocrm-frontend', $css);
        $this->frontend_width_style_applied = true;
    }

    private function sanitize_frontend_width_mode_setting(string $value): string
    {
        $mode = strtolower(trim($value));

        return in_array($mode, ['plugin', 'screen'], true) ? $mode : 'screen';
    }

    private function sanitize_frontend_width_percent_setting(string $value): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 100;
        }

        $number = (int) $raw;
        if ($number < 60) {
            $number = 60;
        }
        if ($number > 100) {
            $number = 100;
        }

        return $number;
    }

    private function sanitize_mortgage_advisor_logo_size_setting(string $value): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 70;
        }

        $number = (int) $raw;
        if ($number < 30) {
            $number = 30;
        }
        if ($number > 100) {
            $number = 100;
        }

        return $number;
    }

    private function sanitize_mortgage_advisor_logo_align_setting(string $value): string
    {
        $align = strtolower(trim($value));

        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
    }

    private function format_half_percent_for_css(int $percent): string
    {
        $half = $percent / 2;
        $formatted = number_format($half, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function get_current_language(): string
    {
        return EstateOfficeCRM_I18n::normalize_language($this->get_setting_value('language'));
    }

    private function translate_output(string $html): string
    {
        return EstateOfficeCRM_I18n::translate_html($html, $this->get_current_language());
    }

    private function build_default_number_for_entity(string $entity, string $agreement_scope = ''): string
    {
        $settings = EstateOfficeCRM_Numbering::load_entity_settings(
            $entity,
            function (string $key): string {
                return $this->get_setting_value($key);
            },
            $agreement_scope
        );

        return EstateOfficeCRM_Numbering::build_number_preview($settings, current_time('timestamp'));
    }

    private function build_default_agreement_number(string $transaction_type = 'SPRZEDAZ'): string
    {
        return $this->build_default_number_for_entity('agreement', $this->agreement_scope_from_transaction_type($transaction_type));
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

    /**
     * @return array{prefix:string,separator:string,parts:array<int,array{type:string,value:string}>}
     */
    private function get_agreement_number_pattern_settings(): array
    {
        $prefix = $this->get_setting_value('default_agreement_number_prefix');
        if ($prefix === '') {
            $prefix = 'UM-';
        }

        $separator = $this->get_setting_value('default_agreement_number_separator');
        if (! in_array($separator, ['-', '/', '_', '.'], true)) {
            $separator = '-';
        }

        $parts_count = absint($this->get_setting_value('default_agreement_number_parts'));
        if ($parts_count <= 0) {
            $parts_count = 1;
        }
        if ($parts_count > 4) {
            $parts_count = 4;
        }

        $parts = [];
        for ($part_index = 1; $part_index <= $parts_count; $part_index++) {
            $part_type = sanitize_key($this->get_setting_value('default_agreement_number_part' . (string) $part_index . '_type'));
            if (! in_array($part_type, ['counter', 'date', 'text'], true)) {
                $part_type = $part_index === 1 ? 'counter' : 'text';
            }

            $part_value = sanitize_text_field($this->get_setting_value('default_agreement_number_part' . (string) $part_index . '_value'));
            if ($part_type === 'counter') {
                $pad = absint($part_value);
                if ($pad <= 0) {
                    $pad = 4;
                }
                if ($pad > 12) {
                    $pad = 12;
                }
                $part_value = (string) $pad;
            } elseif ($part_type === 'date') {
                $part_value = $this->sanitize_agreement_date_format($part_value);
            }

            $parts[] = [
                'type' => $part_type,
                'value' => $part_value,
            ];
        }

        if (empty($parts)) {
            $parts[] = [
                'type' => 'counter',
                'value' => '4',
            ];
        }

        return [
            'prefix' => $prefix,
            'separator' => $separator,
            'parts' => $parts,
        ];
    }

    private function sanitize_agreement_date_format(string $value): string
    {
        $allowed = ['Y', 'y', 'm', 'd', 'Ymd', 'dmY', 'd-m-Y', 'm-Y'];
        return in_array($value, $allowed, true) ? $value : 'Y';
    }

    private function list_clients(string $search, int $limit): array
    {
        global $wpdb;

        $clients = $this->tables['clients'];
        $addresses = $this->tables['client_addresses'];
        $agreement_clients = $this->tables['agreement_clients'];
        $agreements_table = $this->tables['agreements'];
        $search_clients = $this->tables['search_clients'];
        $searches_table = $this->tables['searches'];
        $properties_table = $this->tables['properties'];
        $limit = max(1, min(500, $limit));

        $sql = "SELECT
                c.id,
                c.client_type,
                c.first_name,
                c.last_name,
                c.company_name,
                c.phone,
                c.email,
                c.website,
                c.owner_user_id,
                c.created_at,
                c.updated_at,
                a.street AS address_street,
                a.building_no AS address_building_no,
                a.city AS address_city,
                a.postal_code AS address_postal_code,
                (SELECT COUNT(*) FROM {$agreement_clients} ac
                    INNER JOIN {$agreements_table} ag ON ag.id = ac.agreement_id
                    WHERE ac.client_id = c.id AND ag.is_active = 1) AS agreements_count,
                (SELECT COUNT(DISTINCT p.id) FROM {$agreement_clients} ac2
                    INNER JOIN {$agreements_table} ag2 ON ag2.id = ac2.agreement_id
                    INNER JOIN {$properties_table} p ON p.agreement_id = ag2.id
                    WHERE ac2.client_id = c.id AND ag2.is_active = 1 AND p.is_active = 1) AS properties_count,
                (SELECT COUNT(*) FROM {$search_clients} sc
                    INNER JOIN {$searches_table} se ON se.id = sc.search_id
                    WHERE sc.client_id = c.id AND se.is_active = 1) AS searches_count
            FROM {$clients} c
            LEFT JOIN {$addresses} a ON a.client_id = c.id AND a.address_type = 'main'
            WHERE c.is_active = 1";

        $params = [];

        $owner_scope = $this->owner_scope('c.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        if ($search !== '') {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (
                c.first_name LIKE %s
                OR c.last_name LIKE %s
                OR c.company_name LIKE %s
                OR c.phone LIKE %s
                OR c.email LIKE %s
                OR a.street LIKE %s
                OR a.city LIKE %s
            )";
            for ($i = 0; $i < 7; $i++) {
                $params[] = $search_like;
            }
        }

        $sql .= ' ORDER BY c.id DESC LIMIT %d';
        $params[] = $limit;

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $is_company = isset($row['client_type']) && $row['client_type'] === 'company';
            $full_name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $row['display_name'] = $is_company ? (string) ($row['company_name'] ?? '') : $full_name;
        }

        unset($row);

        return $rows;
    }

    private function build_client_display_name(array $row): string
    {
        $is_company = isset($row['client_type']) && (string) $row['client_type'] === 'company';
        $full_name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));

        return $is_company ? (string) ($row['company_name'] ?? '') : $full_name;
    }

    private function get_client_profile(int $client_id): ?array
    {
        global $wpdb;

        $table = $this->tables['clients'];
        $params = [$client_id];
        $sql = "SELECT * FROM {$table} WHERE id = %d AND is_active = 1";
        $owner_scope = $this->owner_scope('owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);
        $sql .= ' LIMIT 1';

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{main: array<string, mixed>|null, correspondence: array<string, mixed>|null}
     */
    private function get_client_profile_addresses(int $client_id): array
    {
        global $wpdb;

        $table = $this->tables['client_addresses'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE client_id = %d ORDER BY id ASC",
                $client_id
            ),
            ARRAY_A
        );

        $result = ['main' => null, 'correspondence' => null];
        if (! is_array($rows)) {
            return $result;
        }

        foreach ($rows as $row) {
            $type = isset($row['address_type']) ? (string) $row['address_type'] : '';
            if ($type === 'main') {
                $result['main'] = $row;
            } elseif ($type === 'correspondence') {
                $result['correspondence'] = $row;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_client_profile_agreements(int $client_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $agreements = $this->tables['agreements'];
        $agreement_clients = $this->tables['agreement_clients'];

        $params = [$client_id];
        $sql = "SELECT a.*
            FROM {$agreements} a
            INNER JOIN {$agreement_clients} ac ON ac.agreement_id = a.id
            WHERE ac.client_id = %d
            AND a.is_active = 1";

        $owner_scope = $this->owner_scope('a.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        $sql .= ' ORDER BY a.id DESC LIMIT %d';
        $params[] = $limit;

        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function get_owner_user_options(): array
    {
        $users = get_users([
            'role__in' => ['administrator', EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 500,
        ]);

        if (empty($users) || ! is_array($users)) {
            return [];
        }

        $options = [];
        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            if ($user_id <= 0 || isset($options[$user_id])) {
                continue;
            }

            $label = trim((string) $user->display_name);
            if ($label === '') {
                $label = (string) $user->user_login;
            }
            if ($label === '') {
                $label = 'Uzytkownik #' . (string) $user_id;
            }

            $options[$user_id] = [
                'id' => $user_id,
                'label' => $label,
            ];
        }

        return array_values($options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_client_profile_properties(int $client_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $properties = $this->tables['properties'];
        $agreement_clients = $this->tables['agreement_clients'];

        $params = [$client_id];
        $sql = "SELECT DISTINCT p.*
            FROM {$properties} p
            INNER JOIN {$agreement_clients} ac ON ac.agreement_id = p.agreement_id
            WHERE ac.client_id = %d
            AND p.is_active = 1";

        $owner_scope = $this->owner_scope('p.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        $sql .= ' ORDER BY p.id DESC LIMIT %d';
        $params[] = $limit;

        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_client_profile_searches(int $client_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $searches = $this->tables['searches'];
        $search_clients = $this->tables['search_clients'];

        $sql = $wpdb->prepare(
            "SELECT s.*
            FROM {$searches} s
            INNER JOIN {$search_clients} sc ON sc.search_id = s.id
            WHERE sc.client_id = %d
            AND s.is_active = 1
            ORDER BY s.id DESC
            LIMIT %d",
            $client_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function get_agreement_profile(int $agreement_id): ?array
    {
        global $wpdb;

        $table = $this->tables['agreements'];
        $params = [$agreement_id];
        $sql = "SELECT * FROM {$table} WHERE id = %d AND is_active = 1";
        $owner_scope = $this->owner_scope('owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);
        $sql .= ' LIMIT 1';

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_agreement_stage_history(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = $this->tables['agreement_stages'];

        $sql = $wpdb->prepare(
            "SELECT id, stage_name, stage_date, created_at
            FROM {$table}
            WHERE agreement_id = %d
            ORDER BY stage_date DESC, id DESC
            LIMIT %d",
            $agreement_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_agreement_profile_clients(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $clients = $this->tables['clients'];
        $agreement_clients = $this->tables['agreement_clients'];

        $sql = $wpdb->prepare(
            "SELECT c.*
            FROM {$clients} c
            INNER JOIN {$agreement_clients} ac ON ac.client_id = c.id
            WHERE ac.agreement_id = %d
            AND c.is_active = 1
            ORDER BY c.id DESC
            LIMIT %d",
            $agreement_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['display_name'] = $this->build_client_display_name($row);
        }

        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_agreement_profile_properties(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = $this->tables['properties'];

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE agreement_id = %d
            AND is_active = 1
            ORDER BY id DESC
            LIMIT %d",
            $agreement_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_agreement_profile_searches(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = $this->tables['searches'];

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE agreement_id = %d
            AND is_active = 1
            ORDER BY id DESC
            LIMIT %d",
            $agreement_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_agreement_profile_transactions(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = $this->tables['transactions'];

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE agreement_id = %d
            AND is_active = 1
            ORDER BY transaction_date DESC, id DESC
            LIMIT %d",
            $agreement_id,
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function get_transaction_profile(int $transaction_id): ?array
    {
        global $wpdb;

        $transactions = $this->tables['transactions'];
        $agreements = $this->tables['agreements'];
        $properties = $this->tables['properties'];
        $users_table = $wpdb->users;

        $params = [$transaction_id];
        $sql = "SELECT
                t.*,
                a.current_stage AS agreement_stage,
                a.date_signed AS agreement_date_signed,
                a.commission_amount AS agreement_commission_amount,
                a.commission_unit AS agreement_commission_unit,
                a.commission_split_enabled AS agreement_commission_split_enabled,
                a.commission_stages_json AS agreement_commission_stages_json,
                p.offer_number,
                p.street,
                p.building_no,
                p.apartment_no,
                p.city,
                cu.display_name AS cooperation_user_display_name
            FROM {$transactions} t
            LEFT JOIN {$agreements} a ON a.id = t.agreement_id
            LEFT JOIN {$properties} p ON p.id = t.property_id
            LEFT JOIN {$users_table} cu ON cu.ID = t.cooperation_agent_user_id
            WHERE t.id = %d
            AND t.is_active = 1";

        $owner_scope = $this->owner_scope('t.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);
        $sql .= ' LIMIT 1';

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private function get_transaction_form_agreement(int $agreement_id): ?array
    {
        global $wpdb;

        $table = $this->tables['agreements'];
        $params = [$agreement_id];
        $sql = "SELECT id, agreement_number, transaction_type, commission_amount, commission_unit, commission_split_enabled, commission_stages_json, owner_user_id
            FROM {$table}
            WHERE id = %d
            AND is_active = 1";

        $owner_scope = $this->owner_scope('owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);
        $sql .= ' LIMIT 1';

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_transaction_form_properties(int $agreement_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = $this->tables['properties'];
        $sql = $wpdb->prepare(
            "SELECT id, offer_number, property_type, price, price_currency, street, building_no, apartment_no, city
            FROM {$table}
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

    /**
     * @return string[]
     */
    private function get_transaction_form_paid_commission_stage_names(int $agreement_id, int $exclude_transaction_id = 0): array
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

        $names = [];
        foreach ($rows as $row) {
            $covered_names = $this->parse_commission_stage_payment_names((string) ($row['commission_stages_json'] ?? ''));
            if (empty($covered_names)) {
                $covered_names[] = (string) ($row['commission_stage_name'] ?? '');
            }

            foreach ($covered_names as $name) {
                $name = trim(sanitize_text_field((string) $name));
                if ($name !== '' && ! in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @return string[]
     */
    private function parse_commission_stage_payment_names(string $json): array
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

    /**
     * @return array<int, array{user_id:int,label:string,office:string}>
     */
    private function get_transaction_agent_options(int $limit): array
    {
        global $wpdb;

        $profiles_table = $this->tables['agent_profiles'];
        $offices_table = $this->tables['offices'];
        $users_table = $wpdb->users;
        $limit = max(1, min(1000, $limit));
        $params = [];
        $where = "WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s)";
        $params[] = '%"' . $wpdb->esc_like(EstateOfficeCRM_Installer::ROLE_AGENT) . '"%';
        $params[] = '%"' . $wpdb->esc_like(EstateOfficeCRM_Installer::ROLE_MANAGER) . '"%';

        if (! current_user_can('manage_options')) {
            $current_office_id = EstateOfficeCRM_Access::get_user_office_id(get_current_user_id(), $this->tables);
            if ($current_office_id > 0) {
                $where .= ' AND p.office_id = %d';
                $params[] = $current_office_id;
            } else {
                $where .= ' AND u.ID = %d';
                $params[] = get_current_user_id();
            }
        }

        $sql = "SELECT
                u.ID AS user_id,
                u.display_name,
                o.office_name
            FROM {$users_table} u
            INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
            LEFT JOIN {$profiles_table} p ON p.user_id = u.ID
            LEFT JOIN {$offices_table} o ON o.id = p.office_id
            {$where}
            ORDER BY COALESCE(o.office_name, '') ASC, u.display_name ASC
            LIMIT %d";
        array_splice($params, 0, 0, [$wpdb->get_blog_prefix() . 'capabilities']);
        $params[] = $limit;
        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $office_name = trim((string) ($row['office_name'] ?? ''));
            $display_name = trim((string) ($row['display_name'] ?? ''));
            $result[] = [
                'user_id' => $user_id,
                'label' => $display_name !== '' ? $display_name : ('Uzytkownik #' . $user_id),
                'office' => $office_name,
            ];
        }

        return $result;
    }

    private function get_property_profile(int $property_id): ?array
    {
        global $wpdb;

        $table = $this->tables['properties'];
        $params = [$property_id];
        $sql = "SELECT * FROM {$table} WHERE id = %d AND is_active = 1";
        $owner_scope = $this->owner_scope('owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);
        $sql .= ' LIMIT 1';

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        if (! is_array($row)) {
            return null;
        }

        $row['exposure'] = $this->decode_json_array($row['exposure_json'] ?? null);
        $row['view'] = $this->decode_json_array($row['view_json'] ?? null);
        $row['layout'] = $this->decode_json_array($row['layout_json'] ?? null);
        $row['amenities'] = $this->decode_json_array($row['amenities_json'] ?? null);
        $row['equipment'] = $this->decode_json_array($row['equipment_json'] ?? null);
        $row['tags'] = $this->decode_json_array($row['tags_json'] ?? null);

        $parking = json_decode((string) ($row['parking_json'] ?? ''), true);
        $media = json_decode((string) ($row['media_json'] ?? ''), true);
        $extra = json_decode((string) ($row['extra_areas_json'] ?? ''), true);
        $custom_fields = json_decode((string) ($row['custom_fields_json'] ?? ''), true);

        $row['parking'] = is_array($parking) ? $parking : [];
        $row['media'] = is_array($media) ? $media : [];
        $row['extra_areas'] = is_array($extra) ? $extra : [];
        $row['custom_fields'] = is_array($custom_fields) ? $custom_fields : [];

        return $row;
    }

    /**
     * @return array{photos: array<int, array<string, mixed>>, floor_plans: array<int, array<string, mixed>>, floor_2d: array<string, mixed>|null, floor_3d: array<string, mixed>|null}
     */
    private function get_property_profile_media(int $property_id): array
    {
        global $wpdb;

        $table = $this->tables['property_media'];
        $sql = $wpdb->prepare(
            "SELECT id, media_type, attachment_id, media_url, position, is_primary
            FROM {$table}
            WHERE property_id = %d
            ORDER BY media_type ASC, position ASC, id ASC",
            $property_id
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return ['photos' => [], 'floor_plans' => [], 'floor_2d' => null, 'floor_3d' => null];
        }

        $photos = [];
        $floor_plans = [];
        $floor2d = null;
        $floor3d = null;

        foreach ($rows as $row) {
            $media_type = isset($row['media_type']) ? (string) $row['media_type'] : '';
            if ($media_type === 'photo') {
                $photos[] = $row;
                continue;
            }

            if ($media_type === 'floor_plan') {
                $floor_plans[] = $row;
                continue;
            }

            if ($media_type === 'floor_2d' && ! is_array($floor2d)) {
                $floor2d = $row;
                continue;
            }

            if ($media_type === 'floor_3d' && ! is_array($floor3d)) {
                $floor3d = $row;
            }
        }

        usort($photos, static function (array $left, array $right): int {
            $leftPrimary = (int) ($left['is_primary'] ?? 0);
            $rightPrimary = (int) ($right['is_primary'] ?? 0);
            if ($leftPrimary !== $rightPrimary) {
                return $rightPrimary <=> $leftPrimary;
            }

            return ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0));
        });

        usort($floor_plans, static function (array $left, array $right): int {
            return ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0));
        });

        if (empty($floor_plans)) {
            if (is_array($floor2d)) {
                $floor2d['position'] = 0;
                $floor2d['label'] = 'Poziom 1';
                $floor_plans[] = $floor2d;
            }
            if (is_array($floor3d)) {
                $floor3d['position'] = count($floor_plans);
                $floor3d['label'] = 'Poziom 2';
                $floor_plans[] = $floor3d;
            }
        }

        return [
            'photos' => $photos,
            'floor_plans' => $floor_plans,
            'floor_2d' => $floor2d,
            'floor_3d' => $floor3d,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get_property_portal_export_status(int $property_id): ?array
    {
        if ($property_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->tables['exports_queue'];
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT payload_json, export_status, attempts, last_error, created_at, updated_at, export_target
                FROM {$table}
                WHERE property_id = %d
                ORDER BY id DESC
                LIMIT 1",
                $property_id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $event = isset($payload['event']) ? sanitize_key((string) $payload['event']) : '';
        if (! in_array($event, ['insert', 'update', 'delete'], true)) {
            $event = '';
        }

        return [
            'event' => $event,
            'status' => sanitize_key((string) ($row['export_status'] ?? 'pending')),
            'target' => EstateOfficeCRM_Portal_Export::sanitize_provider((string) ($row['export_target'] ?? '')),
            'attempts' => isset($row['attempts']) ? (int) $row['attempts'] : 0,
            'last_error' => sanitize_text_field((string) ($row['last_error'] ?? '')),
            'created_at' => sanitize_text_field((string) ($row['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($row['updated_at'] ?? '')),
        ];
    }

    private function get_search_profile(int $search_id): ?array
    {
        global $wpdb;

        $table = $this->tables['searches'];
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND is_active = 1 LIMIT 1",
                $search_id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        $criteria = json_decode((string) ($row['criteria_json'] ?? ''), true);
        $row['criteria'] = is_array($criteria) ? $criteria : [];

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_search_profile_clients(int $search_id, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $clients = $this->tables['clients'];
        $search_clients = $this->tables['search_clients'];

        $params = [$search_id];
        $sql = "SELECT c.*
            FROM {$clients} c
            INNER JOIN {$search_clients} sc ON sc.client_id = c.id
            WHERE sc.search_id = %d
            AND c.is_active = 1";

        $owner_scope = $this->owner_scope('c.owner_user_id');
        $sql .= $owner_scope['sql'];
        $params = array_merge($params, $owner_scope['params']);

        $sql .= ' ORDER BY c.id DESC LIMIT %d';
        $params[] = $limit;

        $sql = $wpdb->prepare($sql, $params);

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['display_name'] = $this->build_client_display_name($row);
        }

        unset($row);

        return $rows;
    }

    private function get_search_profile_agreement(int $agreement_id): ?array
    {
        return $this->get_agreement_profile($agreement_id);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function decode_json_array($value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $item) {
            if (is_scalar($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $result[] = $text;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function get_public_offers(string $transaction, array $filters, int $limit, int $offset = 0): array
    {
        global $wpdb;

        $properties = $this->tables['properties'];
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        $sql = "SELECT
                id,
                offer_number,
                transaction_type,
                property_type,
                city,
                district,
                county,
                gmina,
                street,
                building_no,
                latitude,
                longitude,
                price,
                price_currency,
                area,
                plot_area,
                rooms,
                bedrooms,
                bathrooms,
                toilets,
                description,
                is_new_offer,
                is_mls_offer,
                is_premium,
                tags_json
            FROM {$properties}
            WHERE is_active = 1
            AND export_www = 1
            AND transaction_type = %s";

        $params = [$transaction];
        $www_visibility_condition = $this->build_sold_rented_www_visibility_condition();
        $sql .= $www_visibility_condition['sql'];
        $params = array_merge($params, $www_visibility_condition['params']);

        if (($filters['property_type'] ?? '') !== '') {
            $sql .= ' AND property_type = %s';
            $params[] = $filters['property_type'];
        }

        if (($filters['city'] ?? '') !== '') {
            $sql .= ' AND city = %s';
            $params[] = $filters['city'];
        }

        if (($filters['district'] ?? '') !== '') {
            $sql .= ' AND district = %s';
            $params[] = $filters['district'];
        }

        if (($filters['price_min'] ?? '') !== '' && is_numeric((string) $filters['price_min'])) {
            $sql .= ' AND price >= %f';
            $params[] = (float) $filters['price_min'];
        }

        if (($filters['price_max'] ?? '') !== '' && is_numeric((string) $filters['price_max'])) {
            $sql .= ' AND price <= %f';
            $params[] = (float) $filters['price_max'];
        }

        if (($filters['area_min'] ?? '') !== '' && is_numeric((string) $filters['area_min'])) {
            $sql .= ' AND area >= %f';
            $params[] = (float) $filters['area_min'];
        }

        if (($filters['area_max'] ?? '') !== '' && is_numeric((string) $filters['area_max'])) {
            $sql .= ' AND area <= %f';
            $params[] = (float) $filters['area_max'];
        }

        if (($filters['rooms_min'] ?? '') !== '' && is_numeric((string) $filters['rooms_min'])) {
            $sql .= ' AND rooms >= %d';
            $params[] = (int) $filters['rooms_min'];
        }

        if (($filters['rooms_max'] ?? '') !== '' && is_numeric((string) $filters['rooms_max'])) {
            $sql .= ' AND rooms <= %d';
            $params[] = (int) $filters['rooms_max'];
        }

        $sql .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $property_ids = [];
        foreach ($rows as $row) {
            $property_ids[] = isset($row['id']) ? (int) $row['id'] : 0;
        }
        $photo_urls = $this->get_primary_photo_urls($property_ids);

        foreach ($rows as &$row) {
            $tags = json_decode((string) ($row['tags_json'] ?? ''), true);
            $row['tags'] = is_array($tags) ? $tags : [];
            if (! empty($row['is_mls_offer']) && ! in_array('oferta_mls', $row['tags'], true)) {
                $row['tags'][] = 'oferta_mls';
            }
            if (! empty($row['is_premium']) && ! in_array('premium', $row['tags'], true)) {
                $row['tags'][] = 'premium';
            }
            $row['is_new_offer'] = ! empty($row['is_new_offer']) ? 1 : 0;
            if (empty($row['is_new_offer'])) {
                $row['tags'] = array_values(array_diff($row['tags'], ['nowa_oferta']));
            }
            $row['detail_url'] = $this->get_property_export_page_url((int) ($row['id'] ?? 0));
            $row_id = isset($row['id']) ? (int) $row['id'] : 0;
            $row['preview_photo_url'] = $row_id > 0 ? (string) ($photo_urls[$row_id] ?? '') : '';
        }

        unset($row);

        return $rows;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function get_public_filter_options(string $transaction): array
    {
        global $wpdb;

        $properties = $this->tables['properties'];
        $www_visibility_condition = $this->build_sold_rented_www_visibility_condition();

        $types_query = "SELECT DISTINCT property_type
            FROM {$properties}
            WHERE is_active = 1
            AND export_www = 1
            AND transaction_type = %s
            AND property_type <> ''" . $www_visibility_condition['sql'] . "
            ORDER BY property_type ASC";
        $types_sql = $wpdb->prepare(
            $types_query,
            array_merge([$transaction], $www_visibility_condition['params'])
        );

        $cities_query = "SELECT DISTINCT city
            FROM {$properties}
            WHERE is_active = 1
            AND export_www = 1
            AND transaction_type = %s
            AND city <> ''" . $www_visibility_condition['sql'] . "
            ORDER BY city ASC";
        $cities_sql = $wpdb->prepare(
            $cities_query,
            array_merge([$transaction], $www_visibility_condition['params'])
        );

        $districts_query = "SELECT DISTINCT district
            FROM {$properties}
            WHERE is_active = 1
            AND export_www = 1
            AND transaction_type = %s
            AND district <> ''" . $www_visibility_condition['sql'] . "
            ORDER BY district ASC";
        $districts_sql = $wpdb->prepare(
            $districts_query,
            array_merge([$transaction], $www_visibility_condition['params'])
        );

        $types = $wpdb->get_col($types_sql);
        $cities = $wpdb->get_col($cities_sql);
        $districts = $wpdb->get_col($districts_sql);

        return [
            'property_type' => array_values(array_filter(array_map('strval', is_array($types) ? $types : []))),
            'city' => array_values(array_filter(array_map('strval', is_array($cities) ? $cities : []))),
            'district' => array_values(array_filter(array_map('strval', is_array($districts) ? $districts : []))),
        ];
    }

    private function get_public_offer_by_property_id(int $property_id): ?array
    {
        global $wpdb;

        if ($property_id <= 0) {
            return null;
        }

        $table = $this->tables['properties'];
        $www_visibility_condition = $this->build_sold_rented_www_visibility_condition();
        $query = "SELECT
                    id,
                    offer_number,
                    owner_user_id,
                    transaction_type,
                    property_type,
                    house_type,
                    city,
                    district,
                    street,
                    building_no,
                    apartment_no,
                    postal_code,
                    county,
                    gmina,
                    precinct,
                    plot_number,
                    land_registry_no,
                    no_land_registry,
                    legal_status,
                    latitude,
                    longitude,
                    price,
                    price_currency,
                    admin_rent,
                    area,
                    plot_area,
                    plot_shape,
                    plot_length,
                    plot_width,
                    plot_dimensions_text,
                    price_per_m2,
                    year_built,
                    floor_no,
                    floors_total,
                    rooms,
                    bedrooms,
                    bathrooms,
                    toilets,
                    description,
                    building_finish,
                    exposure_json,
                    view_json,
                    layout_json,
                    kitchen_type,
                    parking_json,
                    media_json,
                    amenities_json,
                    equipment_json,
                    extra_areas_json,
                    custom_fields_json,
                    is_new_offer,
                    is_mls_offer,
                    is_premium,
                    tags_json
                FROM {$table}
                WHERE id = %d
                AND is_active = 1
                AND export_www = 1" . $www_visibility_condition['sql'] . "
                LIMIT 1";
        $query_params = array_merge([$property_id], $www_visibility_condition['params']);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                $query,
                $query_params
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        $tags = json_decode((string) ($row['tags_json'] ?? ''), true);
        $parking = json_decode((string) ($row['parking_json'] ?? ''), true);
        $media_json = json_decode((string) ($row['media_json'] ?? ''), true);
        $extra = json_decode((string) ($row['extra_areas_json'] ?? ''), true);
        $custom_fields = json_decode((string) ($row['custom_fields_json'] ?? ''), true);

        $row['tags'] = is_array($tags) ? $tags : [];
        if (! empty($row['is_mls_offer']) && ! in_array('oferta_mls', $row['tags'], true)) {
            $row['tags'][] = 'oferta_mls';
        }
        if (! empty($row['is_premium']) && ! in_array('premium', $row['tags'], true)) {
            $row['tags'][] = 'premium';
        }
        $row['is_new_offer'] = ! empty($row['is_new_offer']) ? 1 : 0;
        if (empty($row['is_new_offer'])) {
            $row['tags'] = array_values(array_diff($row['tags'], ['nowa_oferta']));
        }
        $row['exposure'] = $this->decode_json_array($row['exposure_json'] ?? null);
        $row['view'] = $this->decode_json_array($row['view_json'] ?? null);
        $row['layout'] = $this->decode_json_array($row['layout_json'] ?? null);
        $row['amenities'] = $this->decode_json_array($row['amenities_json'] ?? null);
        $row['equipment'] = $this->decode_json_array($row['equipment_json'] ?? null);
        $row['parking'] = is_array($parking) ? $parking : [];
        $row['media'] = is_array($media_json) ? $media_json : [];
        $row['extra_areas'] = is_array($extra) ? $extra : [];
        $row['custom_fields'] = is_array($custom_fields) ? $custom_fields : [];
        $row['detail_url'] = $this->get_property_export_page_url($property_id);
        $preview = $this->get_primary_photo_urls([$property_id]);
        $row['preview_photo_url'] = (string) ($preview[$property_id] ?? '');
        $row['owner_user_id'] = isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0;

        return $row;
    }

    private function get_property_export_page_url(int $property_id): string
    {
        global $wpdb;

        if ($property_id <= 0) {
            return '';
        }

        $page_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'page'
                AND p.post_status = 'publish'
                AND pm.meta_key = %s
                AND pm.meta_value = %s
                ORDER BY p.ID DESC
                LIMIT 1",
                '_eocrm_property_id',
                (string) $property_id
            )
        );

        if ($page_id <= 0) {
            return '';
        }

        $url = get_permalink($page_id);

        return is_string($url) ? $url : '';
    }

    /**
     * @param int[] $property_ids
     * @return array<int, string>
     */
    private function get_primary_photo_urls(array $property_ids): array
    {
        global $wpdb;

        $ids = [];
        foreach ($property_ids as $property_id) {
            $property_id = (int) $property_id;
            if ($property_id > 0) {
                $ids[] = $property_id;
            }
        }
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $table = $this->tables['property_media'];
        $sql = $wpdb->prepare(
            "SELECT property_id, attachment_id, media_url, position, is_primary, id
            FROM {$table}
            WHERE media_type = 'photo'
            AND property_id IN ({$placeholders})
            ORDER BY property_id ASC, is_primary DESC, position ASC, id ASC",
            $ids
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $property_id = isset($row['property_id']) ? (int) $row['property_id'] : 0;
            if ($property_id <= 0 || isset($result[$property_id])) {
                continue;
            }

            $attachment_id = isset($row['attachment_id']) ? absint((string) $row['attachment_id']) : 0;
            $url = $attachment_id > 0 ? (string) wp_get_attachment_image_url($attachment_id, 'large') : '';
            if ($url === '') {
                $url = isset($row['media_url']) ? (string) $row['media_url'] : '';
            }

            if ($url !== '') {
                $result[$property_id] = $url;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_public_offices(int $limit): array
    {
        global $wpdb;

        $table = $this->tables['offices'];
        $limit = max(1, min(500, $limit));

        $sql = $wpdb->prepare(
            "SELECT
                id,
                office_name,
                office_email,
                office_phone,
                address_line,
                city,
                postal_code,
                description,
                logo_id,
                display_order
            FROM {$table}
            WHERE is_active = 1
            AND is_public = 1
            ORDER BY display_order ASC, office_name ASC, id ASC
            LIMIT %d",
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $logo_id = isset($row['logo_id']) ? absint((string) $row['logo_id']) : 0;
            $row['logo_url'] = $logo_id > 0 ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
            $row['display_order'] = isset($row['display_order']) ? (int) $row['display_order'] : 100;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function count_public_offers(string $transaction, array $filters): int
    {
        global $wpdb;

        $properties = $this->tables['properties'];

        $sql = "SELECT COUNT(*)
            FROM {$properties}
            WHERE is_active = 1
            AND export_www = 1
            AND transaction_type = %s";
        $params = [$transaction];
        $www_visibility_condition = $this->build_sold_rented_www_visibility_condition();
        $sql .= $www_visibility_condition['sql'];
        $params = array_merge($params, $www_visibility_condition['params']);

        if (($filters['property_type'] ?? '') !== '') {
            $sql .= ' AND property_type = %s';
            $params[] = $filters['property_type'];
        }

        if (($filters['city'] ?? '') !== '') {
            $sql .= ' AND city = %s';
            $params[] = $filters['city'];
        }

        if (($filters['district'] ?? '') !== '') {
            $sql .= ' AND district = %s';
            $params[] = $filters['district'];
        }

        if (($filters['price_min'] ?? '') !== '' && is_numeric((string) $filters['price_min'])) {
            $sql .= ' AND price >= %f';
            $params[] = (float) $filters['price_min'];
        }

        if (($filters['price_max'] ?? '') !== '' && is_numeric((string) $filters['price_max'])) {
            $sql .= ' AND price <= %f';
            $params[] = (float) $filters['price_max'];
        }

        if (($filters['area_min'] ?? '') !== '' && is_numeric((string) $filters['area_min'])) {
            $sql .= ' AND area >= %f';
            $params[] = (float) $filters['area_min'];
        }

        if (($filters['area_max'] ?? '') !== '' && is_numeric((string) $filters['area_max'])) {
            $sql .= ' AND area <= %f';
            $params[] = (float) $filters['area_max'];
        }

        if (($filters['rooms_min'] ?? '') !== '' && is_numeric((string) $filters['rooms_min'])) {
            $sql .= ' AND rooms >= %d';
            $params[] = (int) $filters['rooms_min'];
        }

        if (($filters['rooms_max'] ?? '') !== '' && is_numeric((string) $filters['rooms_max'])) {
            $sql .= ' AND rooms <= %d';
            $params[] = (int) $filters['rooms_max'];
        }

        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $count = $wpdb->get_var($prepared);

        return max(0, (int) $count);
    }

    /**
     * @return string
     */
    private function get_agent_photo_url(int $user_id, string $size = 'thumbnail'): string
    {
        static $cache = [];

        $user_id = absint((string) $user_id);
        if ($user_id <= 0) {
            return '';
        }

        $allowed_sizes = ['thumbnail', 'medium', 'medium_large', 'large', 'full'];
        if (! in_array($size, $allowed_sizes, true)) {
            $size = 'thumbnail';
        }

        $cache_key = (string) $user_id . '|' . $size;
        if (isset($cache[$cache_key])) {
            return (string) $cache[$cache_key];
        }

        global $wpdb;
        $profiles_table = $this->tables['agent_profiles'];
        $photo_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT photo_id FROM {$profiles_table} WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        if ($photo_id <= 0) {
            $cache[$cache_key] = '';
            return '';
        }

        $photo_url = wp_get_attachment_image_url($photo_id, $size);
        if ((! is_string($photo_url) || $photo_url === '') && $size !== 'full') {
            $photo_url = wp_get_attachment_image_url($photo_id, 'full');
        }
        if (! is_string($photo_url) || $photo_url === '') {
            $photo_url = wp_get_attachment_url($photo_id);
        }

        $cache[$cache_key] = is_string($photo_url) ? esc_url_raw($photo_url) : '';
        return (string) $cache[$cache_key];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_public_agents(int $limit): array
    {
        global $wpdb;

        $profiles_table = $this->tables['agent_profiles'];
        $users_table = $wpdb->users;
        $limit = max(1, min(1000, $limit));

        $sql = $wpdb->prepare(
            "SELECT
                u.ID,
                u.display_name,
                u.user_email,
                p.phone,
                p.address_line,
                p.city,
                p.postal_code,
                p.bio,
                p.photo_id,
                p.office_id,
                p.display_order,
                CASE WHEN um.meta_value LIKE %s THEN 1 ELSE 0 END AS is_manager
            FROM {$users_table} u
            LEFT JOIN {$profiles_table} p ON p.user_id = u.ID
            INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
            WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s)
            AND COALESCE(p.is_public, 1) = 1
            AND (
                p.office_id IS NULL
                OR p.office_id = 0
                OR EXISTS (
                    SELECT 1
                    FROM {$this->tables['offices']} o2
                    WHERE o2.id = p.office_id
                    AND o2.is_active = 1
                    AND o2.is_public = 1
                )
            )
            ORDER BY
                COALESCE(p.office_id, 0) ASC,
                CASE WHEN um.meta_value LIKE %s THEN 0 ELSE 1 END ASC,
                COALESCE(p.display_order, 100) ASC,
                u.display_name ASC
            LIMIT %d",
            '%"' . EstateOfficeCRM_Installer::ROLE_MANAGER . '"%',
            $wpdb->prefix . 'capabilities',
            '%"' . EstateOfficeCRM_Installer::ROLE_AGENT . '"%',
            '%"' . EstateOfficeCRM_Installer::ROLE_MANAGER . '"%',
            '%"' . EstateOfficeCRM_Installer::ROLE_MANAGER . '"%',
            $limit
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared/controlled; dynamic identifiers come from internal plugin mappings.
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $photo_id = isset($row['photo_id']) ? absint((string) $row['photo_id']) : 0;
            $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'large') : '';
            if ((! is_string($photo_url) || $photo_url === '') && $photo_id > 0) {
                $photo_url = wp_get_attachment_image_url($photo_id, 'full');
            }
            $row['photo_url'] = is_string($photo_url) ? $photo_url : '';
            $row['office_id'] = isset($row['office_id']) ? (int) $row['office_id'] : 0;
            $row['display_order'] = isset($row['display_order']) ? (int) $row['display_order'] : 100;
            $row['is_manager'] = ! empty($row['is_manager']) ? 1 : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function get_public_agent_by_user_id(int $user_id): array
    {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $users_table = $wpdb->users;
        $profiles_table = $this->tables['agent_profiles'];
        $offices_table = $this->tables['offices'];

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    u.ID,
                    u.display_name,
                    u.user_email,
                    p.phone,
                    p.email AS profile_email,
                    p.address_line,
                    p.city,
                    p.postal_code,
                    p.bio,
                    p.photo_id,
                    p.office_id,
                    o.office_name
                FROM {$users_table} u
                LEFT JOIN {$profiles_table} p ON p.user_id = u.ID
                LEFT JOIN {$offices_table} o ON o.id = p.office_id AND o.is_active = 1
                WHERE u.ID = %d
                AND COALESCE(p.is_public, 1) = 1
                AND (
                    p.office_id IS NULL
                    OR p.office_id = 0
                    OR (
                        o.id IS NOT NULL
                        AND o.is_public = 1
                    )
                )
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return [];
        }

        $photo_id = isset($row['photo_id']) ? absint((string) $row['photo_id']) : 0;
        $email = isset($row['profile_email']) ? sanitize_email((string) $row['profile_email']) : '';
        if ($email === '') {
            $email = isset($row['user_email']) ? sanitize_email((string) $row['user_email']) : '';
        }

        $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'large') : '';
        if ((! is_string($photo_url) || $photo_url === '') && $photo_id > 0) {
            $photo_url = wp_get_attachment_image_url($photo_id, 'full');
        }
        $row['photo_url'] = is_string($photo_url) ? $photo_url : '';
        $row['office_id'] = isset($row['office_id']) ? (int) $row['office_id'] : 0;
        $row['email'] = $email;

        return $row;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private function sort_public_agents_by_role_and_order(array $left, array $right): int
    {
        $left_is_manager = ! empty($left['is_manager']) ? 1 : 0;
        $right_is_manager = ! empty($right['is_manager']) ? 1 : 0;
        if ($left_is_manager !== $right_is_manager) {
            return $right_is_manager <=> $left_is_manager;
        }

        $left_order = isset($left['display_order']) ? (int) $left['display_order'] : 100;
        $right_order = isset($right['display_order']) ? (int) $right['display_order'] : 100;
        if ($left_order !== $right_order) {
            return $left_order <=> $right_order;
        }

        $left_name = (string) ($left['display_name'] ?? '');
        $right_name = (string) ($right['display_name'] ?? '');
        return strcasecmp($left_name, $right_name);
    }

    public function handle_wibor_rates_ajax(): void
    {
        wp_send_json_success($this->get_wibor_rates_payload());
    }

    public function refresh_wibor_rates_cache(): void
    {
        $this->get_wibor_rates_payload(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function get_wibor_rates_payload(bool $force_refresh = false): array
    {
        $cache_key = 'eocrm_wibor_rates_v2';
        $last_valid_option_key = 'eocrm_wibor_rates_last_valid_v1';
        $cached = get_transient($cache_key);
        $last_valid_payload = get_option($last_valid_option_key, []);
        if (! $force_refresh && $this->is_valid_wibor_payload($cached)) {
            return $cached;
        }

        $payload = $this->fetch_wibor_rates_from_bankier_pl();
        if (! $this->is_valid_wibor_payload($payload)) {
            $payload = $this->fetch_wibor_rates_from_pap_biznes();
        }
        if (! $this->is_valid_wibor_payload($payload)) {
            $payload = $this->fetch_wibor_rates_from_stooq();
        }

        if (! $this->is_valid_wibor_payload($payload) && $this->is_valid_wibor_payload($cached)) {
            $payload = $cached;
            $payload['source'] = 'cached_last_valid';
            $payload['sourceLabel'] = 'Cache (ostatni prawidlowy odczyt)';
            $payload['updatedAt'] = current_time('mysql');
        }

        if (! $this->is_valid_wibor_payload($payload) && $this->is_valid_wibor_payload($last_valid_payload)) {
            $payload = $last_valid_payload;
            $payload['source'] = 'option_last_valid';
            $payload['sourceLabel'] = 'Ostatni prawidlowy odczyt';
            $payload['updatedAt'] = current_time('mysql');
        }

        if (! $this->is_valid_wibor_payload($payload)) {
            $payload = [
                'rates' => [
                    '1M' => 3.84,
                    '3M' => 3.84,
                    '6M' => 3.88,
                ],
                'source' => 'fallback_static',
                'sourceLabel' => 'Fallback awaryjny',
                'quoteDate' => '',
                'updatedAt' => current_time('mysql'),
            ];
        }

        $payload['rates'] = [
            '1M' => round((float) ($payload['rates']['1M'] ?? 0), 2),
            '3M' => round((float) ($payload['rates']['3M'] ?? 0), 2),
            '6M' => round((float) ($payload['rates']['6M'] ?? 0), 2),
        ];

        if (! isset($payload['updatedAt']) || ! is_string($payload['updatedAt']) || trim((string) $payload['updatedAt']) === '') {
            $payload['updatedAt'] = current_time('mysql');
        }

        set_transient($cache_key, $payload, DAY_IN_SECONDS + HOUR_IN_SECONDS);
        if ($this->is_valid_wibor_payload($payload)) {
            update_option($last_valid_option_key, $payload, false);
        }

        return $payload;
    }

    /**
     * @param mixed $payload
     */
    private function is_valid_wibor_payload($payload): bool
    {
        if (! is_array($payload) || ! isset($payload['rates']) || ! is_array($payload['rates'])) {
            return false;
        }

        foreach (['1M', '3M', '6M'] as $tenor) {
            $value = $payload['rates'][$tenor] ?? null;
            if (! is_numeric($value)) {
                return false;
            }

            $numeric = (float) $value;
            if ($numeric <= 0 || $numeric > 30) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch_wibor_rates_from_bankier_pl(): ?array
    {
        $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 EstateOfficeCRM/' . EOCRM_VERSION;
        $response = wp_remote_get('https://www.bankier.pl/mieszkaniowe/stopy-procentowe/wibor', [
            'timeout' => 12,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'pl-PL,pl;q=0.9,en;q=0.8',
                'Referer' => home_url('/'),
                'User-Agent' => $user_agent,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if (trim($body) === '') {
            return null;
        }

        $rates = [];
        foreach (['1M', '3M', '6M'] as $tenor) {
            $rate = $this->extract_wibor_rate_from_html($body, $tenor);
            if ($rate === null) {
                return null;
            }
            $rates[$tenor] = $rate;
        }

        $quote_date = $this->extract_wibor_quote_date_from_html($body);

        return [
            'rates' => $rates,
            'source' => 'bankier_gpw',
            'sourceLabel' => 'Bankier.pl / GPW Benchmark',
            'quoteDate' => $quote_date,
            'updatedAt' => current_time('mysql'),
        ];
    }

    private function extract_wibor_rate_from_html(string $html, string $tenor): ?float
    {
        $tenor = strtoupper(trim($tenor));
        if (! in_array($tenor, ['1M', '3M', '6M'], true)) {
            return null;
        }

        $text = $this->normalize_html_text_for_search($html);
        $text_pattern = '/WIBOR\s*' . preg_quote($tenor, '/') . '(?![A-Z0-9])[^0-9]{0,60}([0-9]{1,2}(?:[,.][0-9]{1,4})?)\s*%/iu';
        if (preg_match($text_pattern, $text, $matches)) {
            $normalized = str_replace(',', '.', trim((string) ($matches[1] ?? '')));
            if (is_numeric($normalized)) {
                $value = (float) $normalized;
                if ($value > 0 && $value <= 30) {
                    return $value;
                }
            }
        }

        $html_pattern = '/WIBOR\s*' . preg_quote($tenor, '/') . '(?![A-Z0-9])[\s\S]{0,400}?([0-9]{1,2}(?:[,.][0-9]{1,4})?)\s*%/iu';
        if (! preg_match($html_pattern, $html, $matches)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) ($matches[1] ?? '')));
        if (! is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;
        if ($value <= 0 || $value > 30) {
            return null;
        }

        return $value;
    }

    private function extract_wibor_quote_date_from_html(string $html): string
    {
        $text = $this->normalize_html_text_for_search($html);

        if (preg_match('/Data\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/iu', $text, $matches)) {
            return sanitize_text_field((string) ($matches[1] ?? ''));
        }

        if (preg_match('/Data\s*([0-3][0-9])\.([01][0-9])\.(20[0-9]{2})/iu', $text, $matches)) {
            $day = str_pad((string) ($matches[1] ?? ''), 2, '0', STR_PAD_LEFT);
            $month = str_pad((string) ($matches[2] ?? ''), 2, '0', STR_PAD_LEFT);
            $year = (string) ($matches[3] ?? '');
            if ($day !== '' && $month !== '' && $year !== '') {
                return sanitize_text_field($year . '-' . $month . '-' . $day);
            }
        }

        return '';
    }

    private function normalize_html_text_for_search(string $html): string
    {
        $stripped = wp_strip_all_tags($html, true);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $decoded);

        return is_string($normalized) ? trim($normalized) : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch_wibor_rates_from_pap_biznes(): ?array
    {
        $response = wp_remote_get('https://biznes.pap.pl/wiadomosci/gospodarka/zlotowe-depozyty-miedzybankowe-wibid-wibor-176', [
            'timeout' => 12,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'EstateOfficeCRM/' . EOCRM_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if (trim($body) === '') {
            return null;
        }

        $rates = [];
        foreach (['1M', '3M', '6M'] as $tenor) {
            $rate = $this->extract_wibor_rate_from_pap_table($body, $tenor);
            if ($rate === null) {
                return null;
            }
            $rates[$tenor] = $rate;
        }

        $quote_date = $this->extract_date_from_html($body);

        return [
            'rates' => $rates,
            'source' => 'pap_gpw',
            'sourceLabel' => 'PAP Biznes / GPW Benchmark',
            'quoteDate' => $quote_date,
            'updatedAt' => current_time('mysql'),
        ];
    }

    private function extract_wibor_rate_from_pap_table(string $html, string $tenor): ?float
    {
        $tenor = strtoupper(trim($tenor));
        if (! in_array($tenor, ['1M', '3M', '6M'], true)) {
            return null;
        }

        $pattern = '/\b' . preg_quote($tenor, '/') . '\s*\|\s*[0-9]{1,2}(?:[,.][0-9]{1,4})?\s*\|\s*([0-9]{1,2}(?:[,.][0-9]{1,4})?)/iu';
        if (! preg_match($pattern, $html, $matches)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) ($matches[1] ?? '')));
        if (! is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;
        if ($value <= 0 || $value > 30) {
            return null;
        }

        return $value;
    }

    private function extract_date_from_html(string $html): string
    {
        if (! preg_match('/\b(20[0-9]{2}-[01][0-9]-[0-3][0-9])\b/', $html, $matches)) {
            return '';
        }

        return sanitize_text_field((string) ($matches[1] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch_wibor_rates_from_stooq(): ?array
    {
        $rates = [
            '1M' => null,
            '3M' => null,
            '6M' => null,
        ];

        $tenor_symbols = [
            '1M' => ['wibor1m', 'wibor1m.pl'],
            '3M' => ['wibor3m', 'wibor3m.pl'],
            '6M' => ['wibor6m', 'wibor6m.pl'],
        ];

        foreach ($tenor_symbols as $tenor => $symbols) {
            foreach ($symbols as $symbol) {
                $value = $this->fetch_wibor_rate_for_symbol_from_stooq($symbol);
                if ($value !== null) {
                    $rates[$tenor] = $value;
                    break;
                }
            }
        }

        if (! is_numeric($rates['1M']) || ! is_numeric($rates['3M']) || ! is_numeric($rates['6M'])) {
            return null;
        }

        return [
            'rates' => [
                '1M' => (float) $rates['1M'],
                '3M' => (float) $rates['3M'],
                '6M' => (float) $rates['6M'],
            ],
            'source' => 'stooq_fallback',
            'sourceLabel' => 'Stooq fallback',
            'quoteDate' => '',
            'updatedAt' => current_time('mysql'),
        ];
    }

    private function fetch_wibor_rate_for_symbol_from_stooq(string $symbol): ?float
    {
        $symbol = strtolower(trim($symbol));
        if ($symbol === '') {
            return null;
        }

        $urls = [
            'https://stooq.com/q/l/?s=' . rawurlencode($symbol) . '&f=sd2t2ohlcv&h&e=csv',
            'https://stooq.pl/q/l/?s=' . rawurlencode($symbol) . '&f=sd2t2ohlcv&h&e=csv',
            'https://stooq.com/q/d/l/?s=' . rawurlencode($symbol) . '&i=d',
            'https://stooq.pl/q/d/l/?s=' . rawurlencode($symbol) . '&i=d',
        ];

        foreach ($urls as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 8,
                'redirection' => 3,
                'headers' => [
                    'Accept' => 'text/csv,text/plain,*/*',
                    'User-Agent' => 'EstateOfficeCRM/' . EOCRM_VERSION,
                ],
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status < 200 || $status >= 300) {
                continue;
            }

            $body = trim((string) wp_remote_retrieve_body($response));
            if ($body === '') {
                continue;
            }

            $rate = $this->extract_wibor_rate_from_csv($body);
            if ($rate !== null) {
                return $rate;
            }
        }

        return null;
    }

    private function extract_wibor_rate_from_csv(string $csv): ?float
    {
        $rows = preg_split('/\r\n|\r|\n/', trim($csv));
        if (! is_array($rows) || count($rows) < 2) {
            return null;
        }

        $last_row = trim((string) $rows[count($rows) - 1]);
        if ($last_row === '') {
            return null;
        }

        $cols = str_getcsv($last_row);
        if (! is_array($cols) || empty($cols)) {
            return null;
        }

        $numeric_candidates = [];
        foreach ($cols as $col_index => $col_value) {
            if ($col_index === 0) {
                continue;
            }

            $normalized = str_replace(',', '.', trim((string) $col_value));
            if (! is_numeric($normalized)) {
                continue;
            }
            $number = (float) $normalized;
            if ($number <= 0 || $number > 100) {
                continue;
            }
            $numeric_candidates[] = $number;
        }

        if (empty($numeric_candidates)) {
            return null;
        }

        return (float) $numeric_candidates[count($numeric_candidates) - 1];
    }

    private function get_public_offers_per_page_setting(): int
    {
        return $this->sanitize_public_offers_per_page_setting($this->get_setting_value('public_offers_per_page'));
    }

    private function sanitize_public_offers_per_page_setting(string $value): int
    {
        $raw = trim($value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 6;
        }

        $number = (int) $raw;
        if ($number < 2 || $number > 20) {
            return 6;
        }

        if (($number % 2) !== 0) {
            $number = $number - 1;
        }

        if ($number < 2) {
            $number = 2;
        }

        return $number;
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, array<int, string>> $filter_options
     * @return array<string, string>
     */
    private function sanitize_public_offer_filters(array $filters, array $filter_options): array
    {
        $sanitize_list_value = static function (string $value, array $options): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }

            $allowed = array_values(array_filter(array_map(static function ($option): string {
                return trim((string) $option);
            }, $options), static function (string $option): bool {
                return $option !== '';
            }));

            return in_array($value, $allowed, true) ? $value : '';
        };

        $normalized = [
            'property_type' => $sanitize_list_value((string) ($filters['property_type'] ?? ''), (array) ($filter_options['property_type'] ?? [])),
            'city' => $sanitize_list_value((string) ($filters['city'] ?? ''), (array) ($filter_options['city'] ?? [])),
            'district' => $sanitize_list_value((string) ($filters['district'] ?? ''), (array) ($filter_options['district'] ?? [])),
            'price_min' => (string) ($filters['price_min'] ?? ''),
            'price_max' => (string) ($filters['price_max'] ?? ''),
            'area_min' => (string) ($filters['area_min'] ?? ''),
            'area_max' => (string) ($filters['area_max'] ?? ''),
            'rooms_min' => (string) ($filters['rooms_min'] ?? ''),
            'rooms_max' => (string) ($filters['rooms_max'] ?? ''),
        ];

        $normalize_range = static function (string &$minValue, string &$maxValue, bool $isInteger = false): void {
            if ($minValue === '' || $maxValue === '') {
                return;
            }

            $minNumber = $isInteger ? (int) $minValue : (float) $minValue;
            $maxNumber = $isInteger ? (int) $maxValue : (float) $maxValue;
            if ($minNumber <= $maxNumber) {
                return;
            }

            [$minValue, $maxValue] = [$maxValue, $minValue];
        };

        $normalize_range($normalized['price_min'], $normalized['price_max'], false);
        $normalize_range($normalized['area_min'], $normalized['area_max'], false);
        $normalize_range($normalized['rooms_min'], $normalized['rooms_max'], true);

        return $normalized;
    }

    private function sanitize_public_decimal_filter(string $value): string
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return '';
        }

        $number = (float) $normalized;
        if ($number < 0) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function sanitize_public_integer_filter(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '' || ! preg_match('/^\d+$/', $normalized)) {
            return '';
        }

        return (string) max(0, (int) $normalized);
    }
}
