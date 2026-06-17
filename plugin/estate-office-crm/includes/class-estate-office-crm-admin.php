<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Admin
{
    public const MENU_SLUG = 'eocrm-admin';
    public const AGENT_LOGIN_SLUG = 'eocrm-agent-login';
    public const AGENTS_SLUG = 'eocrm-agents';
    public const SETTINGS_SLUG = 'eocrm-settings';
    public const ABOUT_SLUG = 'eocrm-about';
    public const LICENSE_SLUG = 'eocrm-license';

    private const SETTING_DEVELOPER_ACCESS = 'developer_access_enabled';
    private const SETTING_ONBOARDING_CONFIG = 'onboarding_config_json';
    private const SETTING_COMPLIANCE_DOCUMENTS = 'aml_rodo_uodo_documents_json';
    private const DEVELOPER_ACCESS_PASSWORD_HASH = 'd3992a1758f725460ce2d209d1d7041196d071b10df8c6bff9b6f83d72ce4222';

    /** @var array<string, string> */
    private array $tables;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'Estate Office CRM',
            'Estate Office CRM',
            'eocrm_manage_settings',
            self::MENU_SLUG,
            [$this, 'render_dashboard_page'],
            'dashicons-building',
            26
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Estate Office CRM',
            'Pulpit',
            'eocrm_manage_settings',
            self::MENU_SLUG,
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Agenci CRM',
            'Agenci',
            'eocrm_manage_agents',
            self::AGENTS_SLUG,
            [$this, 'render_agents_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Ustawienia CRM',
            'Ustawienia',
            'eocrm_manage_settings',
            self::SETTINGS_SLUG,
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'O CRM - Estate Office CRM',
            'O CRM',
            'eocrm_manage_settings',
            self::ABOUT_SLUG,
            [$this, 'render_about_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Licencja Estate Office CRM',
            'Licencja',
            'eocrm_manage_license',
            self::LICENSE_SLUG,
            [$this, 'render_license_page']
        );
    }

    public function enqueue_assets(): void
    {
        if (! $this->is_plugin_admin_page()) {
            return;
        }

        wp_enqueue_style('eocrm-admin');
        wp_enqueue_media();
        wp_enqueue_script('eocrm-admin');
        wp_localize_script('eocrm-admin', 'eocrmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'cropNonce' => wp_create_nonce('eocrm_crop_agent_photo'),
            'labels' => [
                'cropTitle' => 'Kadrowanie zdjecia agenta',
                'cropSave' => 'Zapisz kadr',
                'cropCancel' => 'Anuluj',
                'cropX' => 'Pozycja X',
                'cropY' => 'Pozycja Y',
                'cropZoom' => 'Przyblizenie',
                'cropError' => 'Nie udalo sie zapisac kadru.',
            ],
        ]);
    }

    public function handle_crop_agent_photo_ajax(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_send_json_error(['message' => 'Brak uprawnien.'], 403);
        }

        check_ajax_referer('eocrm_crop_agent_photo', 'nonce');

        $attachment_id = isset($_POST['attachment_id']) ? absint((string) wp_unslash($_POST['attachment_id'])) : 0;
        $crop_x = isset($_POST['crop_x']) ? (int) wp_unslash($_POST['crop_x']) : 0;
        $crop_y = isset($_POST['crop_y']) ? (int) wp_unslash($_POST['crop_y']) : 0;
        $crop_size = isset($_POST['crop_size']) ? (int) wp_unslash($_POST['crop_size']) : 0;

        if ($attachment_id <= 0 || $crop_size <= 0) {
            wp_send_json_error(['message' => 'Niepoprawne dane kadrowania.'], 400);
        }

        $original_path = get_attached_file($attachment_id);
        if (! is_string($original_path) || $original_path === '' || ! file_exists($original_path)) {
            wp_send_json_error(['message' => 'Nie znaleziono pliku obrazu.'], 404);
        }

        if (! wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => 'Wybrany zalacznik nie jest obrazem.'], 400);
        }

        $image_size = function_exists('wp_getimagesize') ? wp_getimagesize($original_path) : getimagesize($original_path);
        if (! is_array($image_size) || count($image_size) < 2) {
            wp_send_json_error(['message' => 'Nie mozna odczytac rozmiaru obrazu.'], 400);
        }

        $width = max(1, (int) $image_size[0]);
        $height = max(1, (int) $image_size[1]);
        $max_size = max(1, min($width, $height));
        $crop_size = max(1, min($crop_size, $max_size));
        $crop_x = max(0, min($crop_x, $width - $crop_size));
        $crop_y = max(0, min($crop_y, $height - $crop_size));

        $path_info = pathinfo($original_path);
        $dirname = isset($path_info['dirname']) ? (string) $path_info['dirname'] : '';
        $filename = isset($path_info['filename']) ? sanitize_file_name((string) $path_info['filename']) : 'agent-photo';
        $extension = isset($path_info['extension']) ? (string) $path_info['extension'] : '';
        if ($dirname === '' || $extension === '') {
            wp_send_json_error(['message' => 'Niepoprawna sciezka pliku obrazu.'], 400);
        }

        $target_name = wp_unique_filename($dirname, $filename . '-eocrm-agent-crop.' . $extension);
        $target_path = trailingslashit($dirname) . $target_name;

        if (! copy($original_path, $target_path)) {
            wp_send_json_error(['message' => 'Nie udalo sie utworzyc kopii obrazu do kadrowania.'], 500);
        }

        $editor = wp_get_image_editor($target_path);
        if (is_wp_error($editor)) {
            $this->delete_file_safely($target_path);
            wp_send_json_error(['message' => 'Nie mozna otworzyc edytora obrazu.'], 500);
        }

        $crop_result = $editor->crop($crop_x, $crop_y, $crop_size, $crop_size, 900, 900, false);
        if (is_wp_error($crop_result)) {
            $this->delete_file_safely($target_path);
            wp_send_json_error(['message' => 'Nie udalo sie wykadrowac obrazu.'], 500);
        }

        $saved = $editor->save($target_path);
        if (is_wp_error($saved) || ! is_array($saved)) {
            $this->delete_file_safely($target_path);
            wp_send_json_error(['message' => 'Nie udalo sie zapisac obrazu po kadrowaniu.'], 500);
        }

        $uploads = wp_upload_dir();
        if (! is_array($uploads) || empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            $this->delete_file_safely($target_path);
            wp_send_json_error(['message' => 'Nie mozna odczytac katalogu upload.'], 500);
        }

        $basedir = (string) $uploads['basedir'];
        $baseurl = (string) $uploads['baseurl'];
        $target_path_normalized = wp_normalize_path($target_path);
        $basedir_normalized = wp_normalize_path($basedir);
        if (strpos($target_path_normalized, $basedir_normalized) !== 0) {
            $this->delete_file_safely($target_path);
            wp_send_json_error(['message' => 'Plik kadrowania poza katalogiem upload.'], 500);
        }

        $relative_path = ltrim(substr($target_path_normalized, strlen($basedir_normalized)), '/');
        $attachment_url = trailingslashit($baseurl) . str_replace('\\', '/', $relative_path);
        $mime_type = wp_check_filetype($target_path)['type'] ?? 'image/jpeg';

        $attachment_post = [
            'post_mime_type' => $mime_type,
            'post_title' => sanitize_text_field($filename . ' - kadr agenta'),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $new_attachment_id = wp_insert_attachment($attachment_post, $target_path);
        if (is_wp_error($new_attachment_id) || (int) $new_attachment_id <= 0) {
            $this->delete_file_safely($target_path);
            wp_send_json_error(['message' => 'Nie udalo sie zapisac zalacznika kadru.'], 500);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata((int) $new_attachment_id, $target_path);
        if (is_array($metadata)) {
            wp_update_attachment_metadata((int) $new_attachment_id, $metadata);
        }

        $preview_url = wp_get_attachment_image_url((int) $new_attachment_id, 'medium');
        if (! is_string($preview_url) || $preview_url === '') {
            $preview_url = $attachment_url;
        }

        wp_send_json_success([
            'attachmentId' => (int) $new_attachment_id,
            'url' => $preview_url,
        ]);
    }

    public function handle_actions(): void
    {
        $request_method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';

        if (! is_admin() || $request_method !== 'POST') {
            return;
        }

        $action = isset($_POST['eocrm_action']) ? sanitize_key((string) wp_unslash($_POST['eocrm_action'])) : '';
        if ($action === '') {
            return;
        }

        if ($action === 'save_settings') {
            $this->handle_save_settings();
        }

        if ($action === 'create_agent') {
            $this->handle_create_agent();
        }

        if ($action === 'update_agent') {
            $this->handle_update_agent();
        }

        if ($action === 'create_office') {
            $this->handle_create_office();
        }

        if ($action === 'update_office') {
            $this->handle_update_office();
        }

        if ($action === 'save_offices_agents_page_content') {
            $this->handle_save_offices_agents_page_content();
        }
    }

    private function handle_save_settings(): void
    {
        if (! current_user_can('eocrm_manage_settings')) {
            wp_die('Brak uprawnien.');
        }

        check_admin_referer('eocrm_save_settings');

        $developer_access_enabled = $this->developer_access_enabled();
        $settings_tab = isset($_POST['eocrm_settings_tab']) ? sanitize_key((string) wp_unslash($_POST['eocrm_settings_tab'])) : 'general';
        if (! in_array($settings_tab, $this->settings_tab_keys($developer_access_enabled), true)) {
            $settings_tab = 'general';
        }

        if ($settings_tab === 'offer_templates') {
            $offer_template_variant = isset($_POST['offer_template_variant']) ? sanitize_key((string) wp_unslash($_POST['offer_template_variant'])) : 'modern_v1';
            $pdf_template_variant = isset($_POST['pdf_template_variant']) ? sanitize_key((string) wp_unslash($_POST['pdf_template_variant'])) : 'pdf_v1';
            $offer_template_sale_heading = isset($_POST['offer_template_sale_heading']) ? sanitize_text_field((string) wp_unslash($_POST['offer_template_sale_heading'])) : 'Oferty na Sprzedaz';
            $offer_template_rent_heading = isset($_POST['offer_template_rent_heading']) ? sanitize_text_field((string) wp_unslash($_POST['offer_template_rent_heading'])) : 'Oferty na Wynajem';
            $offer_template_cta_label = isset($_POST['offer_template_cta_label']) ? sanitize_text_field((string) wp_unslash($_POST['offer_template_cta_label'])) : 'Zobacz oferte';
            $offer_template_show_offer_number = isset($_POST['offer_template_show_offer_number']) ? '1' : '0';
            $offer_template_show_offer_number_in_title = isset($_POST['offer_template_show_offer_number_in_title']) ? '1' : '0';
            $offer_template_open_in_new_window = isset($_POST['offer_template_open_in_new_window']) && (string) wp_unslash($_POST['offer_template_open_in_new_window']) === '1' ? '1' : '0';

            if (! in_array($offer_template_variant, ['modern_v1', 'modern_v2', 'modern_v3', 'modern_v4', 'modern_v5', 'modern_v6'], true)) {
                $offer_template_variant = 'modern_v1';
            }
            if (! in_array($pdf_template_variant, ['pdf_v1'], true)) {
                $pdf_template_variant = 'pdf_v1';
            }
            if ($offer_template_sale_heading === '') {
                $offer_template_sale_heading = 'Oferty na Sprzedaz';
            }
            if ($offer_template_rent_heading === '') {
                $offer_template_rent_heading = 'Oferty na Wynajem';
            }
            if ($offer_template_cta_label === '') {
                $offer_template_cta_label = 'Zobacz oferte';
            }

            $this->upsert_setting('offer_template_variant', $offer_template_variant);
            $this->upsert_setting('pdf_template_variant', $pdf_template_variant);
            $this->upsert_setting('offer_template_sale_heading', $offer_template_sale_heading);
            $this->upsert_setting('offer_template_rent_heading', $offer_template_rent_heading);
            $this->upsert_setting('offer_template_cta_label', $offer_template_cta_label);
            $this->upsert_setting('offer_template_show_offer_number', $offer_template_show_offer_number);
            $this->upsert_setting('offer_template_show_offer_number_in_title', $offer_template_show_offer_number_in_title);
            $this->upsert_setting('offer_template_open_in_new_window', $offer_template_open_in_new_window);

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'units') {
            $unit_area_local = isset($_POST['unit_area_local']) ? (string) wp_unslash($_POST['unit_area_local']) : 'm2';
            $unit_area_land = isset($_POST['unit_area_land']) ? (string) wp_unslash($_POST['unit_area_land']) : 'm2';
            $unit_area_local = EstateOfficeCRM_Units::sanitize_local_unit($unit_area_local);
            $unit_area_land = EstateOfficeCRM_Units::sanitize_land_unit($unit_area_land);

            $this->upsert_setting('unit_area_local', $unit_area_local);
            $this->upsert_setting('unit_area_land', $unit_area_land);

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'mortgage_calculator') {
            $advisor_enabled = isset($_POST['mortgage_advisor_enabled']) ? sanitize_key((string) wp_unslash($_POST['mortgage_advisor_enabled'])) : '1';
            if (! in_array($advisor_enabled, ['0', '1'], true)) {
                $advisor_enabled = '1';
            }
            $advisor_logo_size_percent = isset($_POST['mortgage_advisor_logo_size_percent'])
                ? $this->sanitize_mortgage_advisor_logo_size_percent((string) wp_unslash($_POST['mortgage_advisor_logo_size_percent']))
                : 70;
            $advisor_logo_align_x = isset($_POST['mortgage_advisor_logo_align_x'])
                ? $this->sanitize_mortgage_advisor_logo_align_x((string) wp_unslash($_POST['mortgage_advisor_logo_align_x']))
                : 'center';
            $advisor_attachment_id = isset($_POST['mortgage_advisor_attachment_id'])
                ? absint((string) wp_unslash($_POST['mortgage_advisor_attachment_id']))
                : 0;
            $advisor_email = isset($_POST['mortgage_advisor_email'])
                ? sanitize_email((string) wp_unslash($_POST['mortgage_advisor_email']))
                : '';

            if ($advisor_email !== '' && ! is_email($advisor_email)) {
                $advisor_email = '';
            }

            $this->upsert_setting('mortgage_advisor_enabled', $advisor_enabled);
            $this->upsert_setting('mortgage_advisor_logo_size_percent', (string) $advisor_logo_size_percent);
            $this->upsert_setting('mortgage_advisor_logo_align_x', $advisor_logo_align_x);
            $this->upsert_setting('mortgage_advisor_attachment_id', (string) $advisor_attachment_id);
            $this->upsert_setting('mortgage_advisor_email', $advisor_email);

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'onboarding') {
            $onboarding_config = $this->sanitize_onboarding_config_from_post();
            $encoded_config = wp_json_encode($onboarding_config);
            if (! is_string($encoded_config)) {
                $encoded_config = '{}';
            }

            $this->upsert_setting(self::SETTING_ONBOARDING_CONFIG, $encoded_config);

            if (isset($_POST['eocrm_onboarding_send_now'])) {
                $recipient_user_id = isset($_POST['eocrm_onboarding_recipient_user_id'])
                    ? absint((string) wp_unslash($_POST['eocrm_onboarding_recipient_user_id']))
                    : 0;

                if ($recipient_user_id <= 0) {
                    $this->redirect_with_notice(self::SETTINGS_SLUG, 'error', 'Wybierz Agenta lub Menedzera do wysylki e-maila onboardingowego.', ['tab' => $settings_tab]);
                }

                $sent = $this->send_onboarding_email_to_user($recipient_user_id);
                if (! $sent) {
                    $this->redirect_with_notice(self::SETTINGS_SLUG, 'error', 'Nie udalo sie wyslac e-maila onboardingowego. Sprawdz adres e-mail uzytkownika i konfiguracje poczty WordPress.', ['tab' => $settings_tab]);
                }

                $this->redirect_with_notice(self::SETTINGS_SLUG, 'onboarding_email_sent', '', ['tab' => $settings_tab]);
            }

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'aml_rodo_uodo') {
            $compliance_documents = $this->sanitize_compliance_documents_from_post();
            $encoded_documents = wp_json_encode($compliance_documents);
            if (! is_string($encoded_documents)) {
                $encoded_documents = '{}';
            }

            $this->upsert_setting(self::SETTING_COMPLIANCE_DOCUMENTS, $encoded_documents);

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'portal_export') {
            $provider = isset($_POST['portal_export_provider']) ? EstateOfficeCRM_Portal_Export::sanitize_provider((string) wp_unslash($_POST['portal_export_provider'])) : EstateOfficeCRM_Portal_Export::TARGET_NOE;
            if ($provider === '') {
                $provider = EstateOfficeCRM_Portal_Export::TARGET_NOE;
            }
            if (! $developer_access_enabled && $provider === EstateOfficeCRM_Portal_Export::TARGET_OTODOM_OLX) {
                $provider = EstateOfficeCRM_Portal_Export::TARGET_NOE;
            }

            $this->upsert_setting('portal_export_provider', $provider);

            $noe_enabled = isset($_POST['portal_noe_enabled']) ? '1' : '0';
            $noe_ftp_host = isset($_POST['portal_noe_ftp_host']) ? sanitize_text_field((string) wp_unslash($_POST['portal_noe_ftp_host'])) : 'ftp.nieruchomosci-online.pl';
            if ($noe_ftp_host === '') {
                $noe_ftp_host = 'ftp.nieruchomosci-online.pl';
            }
            $noe_ftp_port = isset($_POST['portal_noe_ftp_port'])
                ? EstateOfficeCRM_Portal_Export::sanitize_port((string) wp_unslash($_POST['portal_noe_ftp_port']))
                : 21;
            $noe_ftp_login = isset($_POST['portal_noe_ftp_login']) ? sanitize_text_field((string) wp_unslash($_POST['portal_noe_ftp_login'])) : '';
            $noe_posted_password = isset($_POST['portal_noe_ftp_password']) ? (string) wp_unslash($_POST['portal_noe_ftp_password']) : '';
            $noe_ftp_password = $noe_posted_password !== '' ? trim($noe_posted_password) : $this->get_setting('portal_noe_ftp_password');
            $noe_ftp_path = isset($_POST['portal_noe_ftp_path'])
                ? EstateOfficeCRM_Portal_Export::sanitize_ftp_path((string) wp_unslash($_POST['portal_noe_ftp_path']))
                : '';
            $noe_export_mode = isset($_POST['portal_noe_export_mode'])
                ? EstateOfficeCRM_Portal_Export::sanitize_export_mode((string) wp_unslash($_POST['portal_noe_export_mode']))
                : 'incremental';
            $noe_region_id = isset($_POST['portal_noe_region_id'])
                ? EstateOfficeCRM_Portal_Export::sanitize_region_id((string) wp_unslash($_POST['portal_noe_region_id']))
                : 7;
            $noe_region_name = isset($_POST['portal_noe_region_name']) ? sanitize_text_field((string) wp_unslash($_POST['portal_noe_region_name'])) : '';
            $noe_software_name = isset($_POST['portal_noe_software_name']) ? sanitize_text_field((string) wp_unslash($_POST['portal_noe_software_name'])) : 'Estate Office CRM';
            if ($noe_software_name === '') {
                $noe_software_name = 'Estate Office CRM';
            }
            $noe_use_ssl = isset($_POST['portal_noe_use_ssl']) ? '1' : '0';
            $noe_ftp_timeout = isset($_POST['portal_noe_ftp_timeout'])
                ? EstateOfficeCRM_Portal_Export::sanitize_timeout((string) wp_unslash($_POST['portal_noe_ftp_timeout']))
                : 20;
            $noe_default_agent_phone = isset($_POST['portal_noe_default_agent_phone']) ? sanitize_text_field((string) wp_unslash($_POST['portal_noe_default_agent_phone'])) : '';
            $noe_default_agent_email = isset($_POST['portal_noe_default_agent_email']) ? sanitize_email((string) wp_unslash($_POST['portal_noe_default_agent_email'])) : '';

            $this->upsert_setting('portal_noe_enabled', $noe_enabled);
            $this->upsert_setting('portal_noe_ftp_host', $noe_ftp_host);
            $this->upsert_setting('portal_noe_ftp_port', (string) $noe_ftp_port);
            $this->upsert_setting('portal_noe_ftp_login', $noe_ftp_login);
            $this->upsert_setting('portal_noe_ftp_password', $noe_ftp_password);
            $this->upsert_setting('portal_noe_ftp_path', $noe_ftp_path);
            $this->upsert_setting('portal_noe_export_mode', $noe_export_mode);
            $this->upsert_setting('portal_noe_region_id', (string) $noe_region_id);
            $this->upsert_setting('portal_noe_region_name', $noe_region_name);
            $this->upsert_setting('portal_noe_software_name', $noe_software_name);
            $this->upsert_setting('portal_noe_use_ssl', $noe_use_ssl);
            $this->upsert_setting('portal_noe_ftp_timeout', (string) $noe_ftp_timeout);
            $this->upsert_setting('portal_noe_default_agent_phone', $noe_default_agent_phone);
            $this->upsert_setting('portal_noe_default_agent_email', $noe_default_agent_email);

            $mg_enabled = isset($_POST['portal_mg_enabled']) ? '1' : '0';
            $mg_ftp_host = isset($_POST['portal_mg_ftp_host']) ? sanitize_text_field((string) wp_unslash($_POST['portal_mg_ftp_host'])) : '';
            $mg_ftp_port = isset($_POST['portal_mg_ftp_port'])
                ? EstateOfficeCRM_Portal_Export::sanitize_port((string) wp_unslash($_POST['portal_mg_ftp_port']))
                : 21;
            $mg_ftp_login = isset($_POST['portal_mg_ftp_login']) ? sanitize_text_field((string) wp_unslash($_POST['portal_mg_ftp_login'])) : '';
            $mg_posted_password = isset($_POST['portal_mg_ftp_password']) ? (string) wp_unslash($_POST['portal_mg_ftp_password']) : '';
            $mg_ftp_password = $mg_posted_password !== '' ? trim($mg_posted_password) : $this->get_setting('portal_mg_ftp_password');
            $mg_ftp_path = isset($_POST['portal_mg_ftp_path'])
                ? EstateOfficeCRM_Portal_Export::sanitize_ftp_path((string) wp_unslash($_POST['portal_mg_ftp_path']))
                : '';
            $mg_export_mode = isset($_POST['portal_mg_export_mode'])
                ? EstateOfficeCRM_Portal_Export::sanitize_export_mode((string) wp_unslash($_POST['portal_mg_export_mode']))
                : 'incremental';
            $mg_region_name = isset($_POST['portal_mg_region_name']) ? sanitize_text_field((string) wp_unslash($_POST['portal_mg_region_name'])) : 'mazowieckie';
            if ($mg_region_name === '') {
                $mg_region_name = 'mazowieckie';
            }
            $mg_agency_name = isset($_POST['portal_mg_agency_name']) ? sanitize_text_field((string) wp_unslash($_POST['portal_mg_agency_name'])) : sanitize_text_field((string) get_bloginfo('name'));
            if ($mg_agency_name === '') {
                $mg_agency_name = sanitize_text_field((string) get_bloginfo('name'));
            }
            $mg_information = isset($_POST['portal_mg_information']) ? sanitize_text_field((string) wp_unslash($_POST['portal_mg_information'])) : 'Eksport ofert z Estate Office CRM';
            if ($mg_information === '') {
                $mg_information = 'Eksport ofert z Estate Office CRM';
            }
            $mg_use_ssl = isset($_POST['portal_mg_use_ssl']) ? '1' : '0';
            $mg_ftp_timeout = isset($_POST['portal_mg_ftp_timeout'])
                ? EstateOfficeCRM_Portal_Export::sanitize_timeout((string) wp_unslash($_POST['portal_mg_ftp_timeout']))
                : 20;
            $mg_default_agent_phone = isset($_POST['portal_mg_default_agent_phone']) ? sanitize_text_field((string) wp_unslash($_POST['portal_mg_default_agent_phone'])) : '';
            $mg_default_agent_email = isset($_POST['portal_mg_default_agent_email']) ? sanitize_email((string) wp_unslash($_POST['portal_mg_default_agent_email'])) : '';

            $this->upsert_setting('portal_mg_enabled', $mg_enabled);
            $this->upsert_setting('portal_mg_ftp_host', $mg_ftp_host);
            $this->upsert_setting('portal_mg_ftp_port', (string) $mg_ftp_port);
            $this->upsert_setting('portal_mg_ftp_login', $mg_ftp_login);
            $this->upsert_setting('portal_mg_ftp_password', $mg_ftp_password);
            $this->upsert_setting('portal_mg_ftp_path', $mg_ftp_path);
            $this->upsert_setting('portal_mg_export_mode', $mg_export_mode);
            $this->upsert_setting('portal_mg_region_name', $mg_region_name);
            $this->upsert_setting('portal_mg_agency_name', $mg_agency_name);
            $this->upsert_setting('portal_mg_information', $mg_information);
            $this->upsert_setting('portal_mg_use_ssl', $mg_use_ssl);
            $this->upsert_setting('portal_mg_ftp_timeout', (string) $mg_ftp_timeout);
            $this->upsert_setting('portal_mg_default_agent_phone', $mg_default_agent_phone);
            $this->upsert_setting('portal_mg_default_agent_email', $mg_default_agent_email);

            if ($developer_access_enabled) {
                $oo_enabled = isset($_POST['portal_oo_enabled']) ? '1' : '0';
                $oo_client_id = isset($_POST['portal_oo_client_id']) ? sanitize_text_field((string) wp_unslash($_POST['portal_oo_client_id'])) : '';
                $oo_posted_client_secret = isset($_POST['portal_oo_client_secret']) ? (string) wp_unslash($_POST['portal_oo_client_secret']) : '';
                $oo_client_secret = $oo_posted_client_secret !== '' ? trim($oo_posted_client_secret) : $this->get_setting('portal_oo_client_secret');
                $oo_api_key = isset($_POST['portal_oo_api_key']) ? sanitize_text_field((string) wp_unslash($_POST['portal_oo_api_key'])) : '';
                $oo_partner_urn = isset($_POST['portal_oo_partner_urn'])
                    ? EstateOfficeCRM_Portal_Export::sanitize_generic_urn((string) wp_unslash($_POST['portal_oo_partner_urn']))
                    : '';
                $oo_posted_notification_secret = isset($_POST['portal_oo_notification_secret']) ? (string) wp_unslash($_POST['portal_oo_notification_secret']) : '';
                $oo_notification_secret = $oo_posted_notification_secret !== '' ? trim($oo_posted_notification_secret) : $this->get_setting('portal_oo_notification_secret');
                $oo_site_urn = isset($_POST['portal_oo_site_urn'])
                    ? EstateOfficeCRM_Portal_Export::sanitize_site_urn((string) wp_unslash($_POST['portal_oo_site_urn']))
                    : 'urn:site:otodompl';
                if ($oo_site_urn === '') {
                    $oo_site_urn = 'urn:site:otodompl';
                }
                $oo_olx_site_urn = EstateOfficeCRM_Portal_Export::sanitize_site_urn((string) $this->get_setting('portal_oo_olx_site_urn'));
                if ($oo_olx_site_urn === '') {
                    $oo_olx_site_urn = 'urn:site:olxpl';
                }
                $oo_enable_olx = isset($_POST['portal_oo_enable_olx']) ? '1' : '0';
                $oo_test_account_mode = isset($_POST['portal_oo_test_account_mode']) ? '1' : '0';
                $oo_auth_host = isset($_POST['portal_oo_auth_host']) ? esc_url_raw((string) wp_unslash($_POST['portal_oo_auth_host'])) : 'https://www.otodom.pl';
                if (! is_string($oo_auth_host) || $oo_auth_host === '') {
                    $oo_auth_host = 'https://www.otodom.pl';
                }
                $oo_auth_locale = isset($_POST['portal_oo_auth_locale'])
                    ? EstateOfficeCRM_Portal_Export::sanitize_auth_locale((string) wp_unslash($_POST['portal_oo_auth_locale']))
                    : 'pl';
                if ($oo_auth_locale === '') {
                    $oo_auth_locale = 'pl';
                }
                $oo_user_agent = isset($_POST['portal_oo_user_agent']) ? sanitize_text_field((string) wp_unslash($_POST['portal_oo_user_agent'])) : 'Estate Office CRM';
                if ($oo_user_agent === '') {
                    $oo_user_agent = 'Estate Office CRM';
                }
                $oo_auth_state = isset($_POST['portal_oo_auth_state']) ? sanitize_text_field((string) wp_unslash($_POST['portal_oo_auth_state'])) : $this->get_setting('portal_oo_auth_state');
                if ($oo_auth_state === '') {
                    $oo_auth_state = wp_generate_uuid4();
                }

                $this->upsert_setting('portal_oo_enabled', $oo_enabled);
                $this->upsert_setting('portal_oo_client_id', $oo_client_id);
                $this->upsert_setting('portal_oo_client_secret', $oo_client_secret);
                $this->upsert_setting('portal_oo_api_key', $oo_api_key);
                $this->upsert_setting('portal_oo_partner_urn', $oo_partner_urn);
                $this->upsert_setting('portal_oo_notification_secret', $oo_notification_secret);
                $this->upsert_setting('portal_oo_site_urn', $oo_site_urn);
                $this->upsert_setting('portal_oo_olx_site_urn', $oo_olx_site_urn);
                $this->upsert_setting('portal_oo_enable_olx', $oo_enable_olx);
                $this->upsert_setting('portal_oo_test_account_mode', $oo_test_account_mode);
                $this->upsert_setting('portal_oo_auth_host', $oo_auth_host);
                $this->upsert_setting('portal_oo_auth_locale', $oo_auth_locale);
                $this->upsert_setting('portal_oo_user_agent', $oo_user_agent);
                $this->upsert_setting('portal_oo_auth_state', $oo_auth_state);
            } else {
                $this->upsert_setting('portal_oo_enabled', '0');
            }

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'import_mls') {
            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'agreement_stages') {
            foreach (EstateOfficeCRM_Stages::transaction_options() as $transaction_type => $transaction_label) {
                unset($transaction_label);
                $post_key = 'agreement_stages_' . strtolower($transaction_type);
                $raw_value = isset($_POST[$post_key]) ? (string) wp_unslash($_POST[$post_key]) : '';
                $stages = EstateOfficeCRM_Stages::sanitize_stage_lines($raw_value);
                if (empty($stages)) {
                    $stages = EstateOfficeCRM_Stages::default_for_transaction($transaction_type);
                }

                $setting_key = EstateOfficeCRM_Stages::setting_key($transaction_type);
                if ($setting_key === '') {
                    continue;
                }

                $this->upsert_setting($setting_key, EstateOfficeCRM_Stages::encode_stages($stages));
            }

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'numbering') {
            $numbering_contexts = [];
            foreach (EstateOfficeCRM_Numbering::agreement_scope_options() as $agreement_scope => $agreement_scope_label) {
                $numbering_contexts[] = [
                    'entity' => 'agreement',
                    'scope' => (string) $agreement_scope,
                    'post_prefix' => 'numbering_agreement_' . (string) $agreement_scope,
                ];
            }
            $numbering_contexts[] = [
                'entity' => 'offer',
                'scope' => '',
                'post_prefix' => 'numbering_offer',
            ];
            $numbering_contexts[] = [
                'entity' => 'search',
                'scope' => '',
                'post_prefix' => 'numbering_search',
            ];

            foreach ($numbering_contexts as $numbering_context) {
                $entity = (string) ($numbering_context['entity'] ?? '');
                $scope = (string) ($numbering_context['scope'] ?? '');
                $post_prefix = (string) ($numbering_context['post_prefix'] ?? '');
                if ($entity === '' || $post_prefix === '') {
                    continue;
                }

                $prefix_name = $post_prefix . '_prefix';
                $parts_name = $post_prefix . '_parts';
                $separator_name = $post_prefix . '_separator';

                $prefix = isset($_POST[$prefix_name]) ? sanitize_text_field((string) wp_unslash($_POST[$prefix_name])) : '';
                $parts_count = isset($_POST[$parts_name]) ? EstateOfficeCRM_Numbering::sanitize_parts_count((string) wp_unslash($_POST[$parts_name])) : 1;
                $separator = isset($_POST[$separator_name]) ? EstateOfficeCRM_Numbering::sanitize_separator((string) wp_unslash($_POST[$separator_name])) : '/';

                $parts = [];
                for ($part_index = 1; $part_index <= 3; $part_index++) {
                    $type_key = $post_prefix . '_part' . (string) $part_index . '_type';
                    $date_key = $post_prefix . '_part' . (string) $part_index . '_date_format';
                    $counter_next_key = $post_prefix . '_part' . (string) $part_index . '_counter_next';
                    $counter_step_key = $post_prefix . '_part' . (string) $part_index . '_counter_step';

                    $part_type = isset($_POST[$type_key])
                        ? EstateOfficeCRM_Numbering::sanitize_part_type((string) wp_unslash($_POST[$type_key]), $part_index)
                        : ($part_index === 1 ? 'digits' : 'date');

                    $date_format = isset($_POST[$date_key])
                        ? EstateOfficeCRM_Numbering::sanitize_date_format((string) wp_unslash($_POST[$date_key]))
                        : 'Ymd';

                    $counter_next = isset($_POST[$counter_next_key])
                        ? EstateOfficeCRM_Numbering::sanitize_counter_next((string) wp_unslash($_POST[$counter_next_key]))
                        : '0001';

                    $counter_step = isset($_POST[$counter_step_key])
                        ? EstateOfficeCRM_Numbering::sanitize_counter_step((string) wp_unslash($_POST[$counter_step_key]))
                        : 1;

                    $parts[$part_index] = [
                        'type' => $part_type,
                        'date_format' => $date_format,
                        'counter_next' => $counter_next,
                        'counter_step' => $counter_step,
                    ];
                }

                $settings = [
                    'entity' => $entity,
                    'agreement_scope' => $scope,
                    'prefix' => $prefix,
                    'parts_count' => $parts_count,
                    'separator' => $separator,
                    'parts' => $parts,
                ];

                $updates = EstateOfficeCRM_Numbering::collect_setting_updates($entity, $settings, $scope);
                foreach ($updates as $setting_key => $setting_value) {
                    $this->upsert_setting((string) $setting_key, (string) $setting_value);
                }
            }

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        if ($settings_tab === 'property_custom_fields') {
            $raw_rows = isset($_POST['property_additional_fields']) ? wp_unslash($_POST['property_additional_fields']) : [];
            if (! is_array($raw_rows)) {
                $raw_rows = [];
            }

            $definitions = EstateOfficeCRM_Property_Custom_Fields::sanitize_definition_rows($raw_rows);
            $this->upsert_setting(
                EstateOfficeCRM_Property_Custom_Fields::SETTING_KEY,
                EstateOfficeCRM_Property_Custom_Fields::encode_definitions($definitions)
            );

            $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => $settings_tab]);
        }

        $google_maps_api_key = isset($_POST['google_maps_api_key']) ? sanitize_text_field((string) wp_unslash($_POST['google_maps_api_key'])) : '';
        $watermark_attachment_id = isset($_POST['watermark_attachment_id']) ? absint((string) wp_unslash($_POST['watermark_attachment_id'])) : 0;
        $office_name = isset($_POST['office_name']) ? sanitize_text_field((string) wp_unslash($_POST['office_name'])) : '';
        $office_logo_attachment_id = isset($_POST['office_logo_attachment_id']) ? absint((string) wp_unslash($_POST['office_logo_attachment_id'])) : 0;
        $language = isset($_POST['language']) ? sanitize_text_field((string) wp_unslash($_POST['language'])) : 'en_US';
        $frontend_width_mode = isset($_POST['frontend_width_mode'])
            ? $this->sanitize_frontend_width_mode((string) wp_unslash($_POST['frontend_width_mode']))
            : 'screen';
        $frontend_width_percent = isset($_POST['frontend_width_percent'])
            ? $this->sanitize_frontend_width_percent((string) wp_unslash($_POST['frontend_width_percent']))
            : 100;
        $public_offers_per_page = isset($_POST['public_offers_per_page'])
            ? $this->sanitize_public_offers_per_page((string) wp_unslash($_POST['public_offers_per_page']))
            : 6;
        $new_offer_duration_days = isset($_POST['new_offer_duration_days'])
            ? $this->sanitize_new_offer_duration_days((string) wp_unslash($_POST['new_offer_duration_days']))
            : 7;
        $sold_rented_www_retention_days = isset($_POST['sold_rented_www_retention_days'])
            ? $this->sanitize_export_retention_days((string) wp_unslash($_POST['sold_rented_www_retention_days']), 30)
            : 30;
        $sold_rented_portals_retention_days = isset($_POST['sold_rented_portals_retention_days'])
            ? $this->sanitize_export_retention_days((string) wp_unslash($_POST['sold_rented_portals_retention_days']), 14)
            : 14;
        $developer_access_requested = isset($_POST['developer_access_enabled']) ? '1' : '0';
        $developer_access_password = isset($_POST['developer_access_password']) ? (string) wp_unslash($_POST['developer_access_password']) : '';
        $developer_access_to_save = '0';

        if ($developer_access_requested === '1') {
            if ($developer_access_enabled && $developer_access_password === '') {
                $developer_access_to_save = '1';
            } elseif (hash_equals(self::DEVELOPER_ACCESS_PASSWORD_HASH, hash('sha256', $developer_access_password))) {
                $developer_access_to_save = '1';
            } else {
                $this->upsert_setting(self::SETTING_DEVELOPER_ACCESS, '0');
                $this->upsert_setting('portal_oo_enabled', '0');
                if ($this->get_setting('portal_export_provider') === EstateOfficeCRM_Portal_Export::TARGET_OTODOM_OLX) {
                    $this->upsert_setting('portal_export_provider', EstateOfficeCRM_Portal_Export::TARGET_NOE);
                }
                $this->redirect_with_notice(self::SETTINGS_SLUG, 'error', 'Niepoprawne haslo programisty.', ['tab' => 'general']);
            }
        }

        if ($developer_access_to_save === '0' && $this->get_setting('portal_export_provider') === EstateOfficeCRM_Portal_Export::TARGET_OTODOM_OLX) {
            $this->upsert_setting('portal_export_provider', EstateOfficeCRM_Portal_Export::TARGET_NOE);
            $this->upsert_setting('portal_oo_enabled', '0');
        }

        if ($office_name === '') {
            $office_name = sanitize_text_field((string) get_bloginfo('name'));
        }
        if (! in_array($language, ['pl_PL', 'en_US', 'de_DE', 'uk_UA'], true)) {
            $language = 'en_US';
        }

        $this->upsert_setting('google_maps_api_key', $google_maps_api_key);
        $this->upsert_setting('watermark_attachment_id', (string) $watermark_attachment_id);
        $this->upsert_setting('office_name', $office_name);
        $this->upsert_setting('office_logo_attachment_id', (string) $office_logo_attachment_id);
        $this->upsert_setting('language', $language);
        $this->upsert_setting('frontend_width_mode', $frontend_width_mode);
        $this->upsert_setting('frontend_width_percent', (string) $frontend_width_percent);
        $this->upsert_setting('public_offers_per_page', (string) $public_offers_per_page);
        $this->upsert_setting('new_offer_duration_days', (string) $new_offer_duration_days);
        $this->upsert_setting('sold_rented_www_retention_days', (string) $sold_rented_www_retention_days);
        $this->upsert_setting('sold_rented_portals_retention_days', (string) $sold_rented_portals_retention_days);
        $this->upsert_setting(self::SETTING_DEVELOPER_ACCESS, $developer_access_to_save);

        $crm_style_input = isset($_POST['crm_style']) ? sanitize_key((string) wp_unslash($_POST['crm_style'])) : 'minimal';
        if (! in_array($crm_style_input, ['default', 'minimal'], true)) {
            $crm_style_input = 'minimal';
        }
        $this->upsert_setting('crm_style', $crm_style_input);

        $this->redirect_with_notice(self::SETTINGS_SLUG, 'settings_saved', '', ['tab' => 'general']);
    }

    private function handle_create_agent(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_die('Brak uprawnien.');
        }

        check_admin_referer('eocrm_create_agent');

        $username = isset($_POST['agent_username']) ? sanitize_user((string) wp_unslash($_POST['agent_username']), true) : '';
        $email = isset($_POST['agent_email']) ? sanitize_email((string) wp_unslash($_POST['agent_email'])) : '';
        $display_name = isset($_POST['agent_display_name']) ? sanitize_text_field((string) wp_unslash($_POST['agent_display_name'])) : '';
        $password_raw = isset($_POST['agent_password']) ? (string) wp_unslash($_POST['agent_password']) : '';
        $crm_role = $this->sanitize_crm_user_role(isset($_POST['agent_role']) ? (string) wp_unslash($_POST['agent_role']) : EstateOfficeCRM_Installer::ROLE_AGENT);

        if ($username === '' || username_exists($username)) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Niepoprawny login lub login juz istnieje.', ['tab' => 'agents']);
        }

        if (! is_email($email) || email_exists($email)) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Niepoprawny e-mail lub e-mail juz istnieje.', ['tab' => 'agents']);
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
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', $message, ['tab' => 'agents']);
        }

        $office_id = isset($_POST['agent_office_id']) ? absint((string) wp_unslash($_POST['agent_office_id'])) : 0;
        $photo_id = isset($_POST['agent_photo_id']) ? absint((string) wp_unslash($_POST['agent_photo_id'])) : 0;
        $agent_is_public = isset($_POST['agent_is_public']) ? 1 : 0;
        $agent_display_order = isset($_POST['agent_display_order']) ? $this->sanitize_display_order((string) wp_unslash($_POST['agent_display_order'])) : 100;

        $profile_data = [
            'phone' => isset($_POST['agent_phone']) ? sanitize_text_field((string) wp_unslash($_POST['agent_phone'])) : '',
            'address_line' => isset($_POST['agent_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['agent_address_line'])) : '',
            'city' => isset($_POST['agent_city']) ? sanitize_text_field((string) wp_unslash($_POST['agent_city'])) : '',
            'postal_code' => isset($_POST['agent_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['agent_postal_code'])) : '',
            'bio' => isset($_POST['agent_bio']) ? wp_kses_post((string) wp_unslash($_POST['agent_bio'])) : '',
            'email' => $email,
            'photo_id' => $this->normalize_image_attachment_id($photo_id),
            'office_id' => $this->normalize_office_id($office_id),
            'is_public' => $agent_is_public,
            'display_order' => $agent_display_order,
        ];

        $this->upsert_agent_profile((int) $user_id, $profile_data);

        $notice = $crm_role === EstateOfficeCRM_Installer::ROLE_MANAGER ? 'Menedzer utworzony.' : 'Agent utworzony.';
        if ($password_raw === '') {
            $notice .= ' Haslo wygenerowano automatycznie. Ustaw nowe haslo po pierwszym logowaniu.';
        }
        $notice .= $this->send_onboarding_email_to_user((int) $user_id)
            ? ' E-mail onboardingowy zostal wyslany.'
            : ' Nie udalo sie wyslac e-maila onboardingowego.';

        $this->redirect_with_notice(self::AGENTS_SLUG, 'agent_created', $notice, ['tab' => 'agents']);
    }

    private function handle_update_agent(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_die('Brak uprawnien.');
        }

        check_admin_referer('eocrm_update_agent');

        $user_id = isset($_POST['agent_id']) ? absint((string) wp_unslash($_POST['agent_id'])) : 0;
        if ($user_id <= 0) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Brak identyfikatora agenta.', ['tab' => 'agents']);
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Nie znaleziono agenta.', ['tab' => 'agents']);
        }

        $display_name = isset($_POST['agent_display_name']) ? sanitize_text_field((string) wp_unslash($_POST['agent_display_name'])) : '';
        $email = isset($_POST['agent_email']) ? sanitize_email((string) wp_unslash($_POST['agent_email'])) : '';
        $crm_role = $this->sanitize_crm_user_role(isset($_POST['agent_role']) ? (string) wp_unslash($_POST['agent_role']) : EstateOfficeCRM_Installer::ROLE_AGENT);

        if ($display_name === '') {
            $display_name = (string) $user->display_name;
        }

        if (! is_email($email)) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Niepoprawny e-mail agenta.', ['tab' => 'agents']);
        }

        $existing_with_email = email_exists($email);
        if ($existing_with_email && (int) $existing_with_email !== $user_id) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Ten e-mail jest juz przypisany do innego uzytkownika.', ['tab' => 'agents']);
        }

        $result = wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $email,
        ]);

        if (is_wp_error($result)) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', $result->get_error_message(), ['tab' => 'agents']);
        }

        foreach ([EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER] as $role_to_clear) {
            if (in_array($role_to_clear, $user->roles, true)) {
                $user->remove_role($role_to_clear);
            }
        }
        $user->add_role($crm_role);

        $office_id = isset($_POST['agent_office_id']) ? absint((string) wp_unslash($_POST['agent_office_id'])) : 0;
        $photo_id = isset($_POST['agent_photo_id']) ? absint((string) wp_unslash($_POST['agent_photo_id'])) : 0;
        $agent_is_public = isset($_POST['agent_is_public']) ? 1 : 0;
        $agent_display_order = isset($_POST['agent_display_order']) ? $this->sanitize_display_order((string) wp_unslash($_POST['agent_display_order'])) : 100;

        $profile_data = [
            'phone' => isset($_POST['agent_phone']) ? sanitize_text_field((string) wp_unslash($_POST['agent_phone'])) : '',
            'address_line' => isset($_POST['agent_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['agent_address_line'])) : '',
            'city' => isset($_POST['agent_city']) ? sanitize_text_field((string) wp_unslash($_POST['agent_city'])) : '',
            'postal_code' => isset($_POST['agent_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['agent_postal_code'])) : '',
            'bio' => isset($_POST['agent_bio']) ? wp_kses_post((string) wp_unslash($_POST['agent_bio'])) : '',
            'email' => $email,
            'photo_id' => $this->normalize_image_attachment_id($photo_id),
            'office_id' => $this->normalize_office_id($office_id),
            'is_public' => $agent_is_public,
            'display_order' => $agent_display_order,
        ];

        $this->upsert_agent_profile($user_id, $profile_data);

        $this->redirect_with_notice(self::AGENTS_SLUG, 'agent_updated', '', ['tab' => 'agents']);
    }

    private function handle_create_office(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_die('Brak uprawnien.');
        }

        check_admin_referer('eocrm_create_office');

        $office_name = isset($_POST['office_name']) ? sanitize_text_field((string) wp_unslash($_POST['office_name'])) : '';
        $office_email = isset($_POST['office_email']) ? sanitize_email((string) wp_unslash($_POST['office_email'])) : '';
        $office_phone = isset($_POST['office_phone']) ? sanitize_text_field((string) wp_unslash($_POST['office_phone'])) : '';
        $address_line = isset($_POST['office_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['office_address_line'])) : '';
        $city = isset($_POST['office_city']) ? sanitize_text_field((string) wp_unslash($_POST['office_city'])) : '';
        $postal_code = isset($_POST['office_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['office_postal_code'])) : '';
        $description = isset($_POST['office_description']) ? wp_kses_post((string) wp_unslash($_POST['office_description'])) : '';
        $logo_id = isset($_POST['office_logo_id']) ? absint((string) wp_unslash($_POST['office_logo_id'])) : 0;
        $is_public = isset($_POST['office_is_public']) ? 1 : 0;
        $display_order = isset($_POST['office_display_order']) ? $this->sanitize_display_order((string) wp_unslash($_POST['office_display_order'])) : 100;

        if ($office_name === '') {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Nazwa biura jest wymagana.', ['tab' => 'offices']);
        }

        if ($office_email !== '' && ! is_email($office_email)) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Niepoprawny e-mail biura.', ['tab' => 'offices']);
        }

        global $wpdb;

        $now = current_time('mysql');
        $table = $this->tables['offices'];

        $inserted = $wpdb->insert(
            $table,
            [
                'office_name' => $office_name,
                'office_email' => $office_email,
                'office_phone' => $office_phone,
                'address_line' => $address_line,
                'city' => $city,
                'postal_code' => $postal_code,
                'description' => $description,
                'logo_id' => $this->normalize_image_attachment_id($logo_id),
                'is_active' => 1,
                'is_public' => $is_public,
                'display_order' => $display_order,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Nie udalo sie utworzyc biura.', ['tab' => 'offices']);
        }

        $this->redirect_with_notice(self::AGENTS_SLUG, 'office_created', '', ['tab' => 'offices']);
    }

    private function handle_update_office(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_die('Brak uprawnien.');
        }

        check_admin_referer('eocrm_update_office');

        $office_id = isset($_POST['office_id']) ? absint((string) wp_unslash($_POST['office_id'])) : 0;
        if ($office_id <= 0) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Brak identyfikatora biura.', ['tab' => 'offices']);
        }

        global $wpdb;
        $table = $this->tables['offices'];
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d LIMIT 1",
                $office_id
            )
        );

        if (! $exists) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Nie znaleziono biura.', ['tab' => 'offices']);
        }

        $office_name = isset($_POST['office_name']) ? sanitize_text_field((string) wp_unslash($_POST['office_name'])) : '';
        $office_email = isset($_POST['office_email']) ? sanitize_email((string) wp_unslash($_POST['office_email'])) : '';
        $office_phone = isset($_POST['office_phone']) ? sanitize_text_field((string) wp_unslash($_POST['office_phone'])) : '';
        $address_line = isset($_POST['office_address_line']) ? sanitize_text_field((string) wp_unslash($_POST['office_address_line'])) : '';
        $city = isset($_POST['office_city']) ? sanitize_text_field((string) wp_unslash($_POST['office_city'])) : '';
        $postal_code = isset($_POST['office_postal_code']) ? sanitize_text_field((string) wp_unslash($_POST['office_postal_code'])) : '';
        $description = isset($_POST['office_description']) ? wp_kses_post((string) wp_unslash($_POST['office_description'])) : '';
        $logo_id = isset($_POST['office_logo_id']) ? absint((string) wp_unslash($_POST['office_logo_id'])) : 0;
        $is_active = isset($_POST['office_is_active']) ? 1 : 0;
        $is_public = isset($_POST['office_is_public']) ? 1 : 0;
        $display_order = isset($_POST['office_display_order']) ? $this->sanitize_display_order((string) wp_unslash($_POST['office_display_order'])) : 100;

        if ($office_name === '') {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Nazwa biura jest wymagana.', ['tab' => 'offices']);
        }

        if ($office_email !== '' && ! is_email($office_email)) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Niepoprawny e-mail biura.', ['tab' => 'offices']);
        }

        $updated = $wpdb->update(
            $table,
            [
                'office_name' => $office_name,
                'office_email' => $office_email,
                'office_phone' => $office_phone,
                'address_line' => $address_line,
                'city' => $city,
                'postal_code' => $postal_code,
                'description' => $description,
                'logo_id' => $this->normalize_image_attachment_id($logo_id),
                'is_active' => $is_active,
                'is_public' => $is_public,
                'display_order' => $display_order,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $office_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            $this->redirect_with_notice(self::AGENTS_SLUG, 'error', 'Nie udalo sie zaktualizowac biura.', ['tab' => 'offices']);
        }

        $this->redirect_with_notice(self::AGENTS_SLUG, 'office_updated', '', ['tab' => 'offices']);
    }

    private function handle_save_offices_agents_page_content(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_die('Brak uprawnien.');
        }

        check_admin_referer('eocrm_save_offices_agents_page_content');

        $title = isset($_POST['offices_agents_page_title']) ? sanitize_text_field((string) wp_unslash($_POST['offices_agents_page_title'])) : '';
        $intro = isset($_POST['offices_agents_page_intro']) ? sanitize_textarea_field((string) wp_unslash($_POST['offices_agents_page_intro'])) : '';

        if ($title === '') {
            $title = 'Biura i Agenci';
        }

        if ($intro === '') {
            $intro = 'Poznaj nasz zespol doradcow nieruchomosci i sprawdz, kto prowadzi oferty w Twojej okolicy.';
        }

        $this->upsert_setting('offices_agents_page_title', $title);
        $this->upsert_setting('offices_agents_page_intro', $intro);

        $this->redirect_with_notice(self::AGENTS_SLUG, 'offices_agents_content_updated', '', ['tab' => 'page_content']);
    }

    public function render_dashboard_page(): void
    {
        if (! current_user_can('eocrm_manage_settings')) {
            wp_die('Brak uprawnien.');
        }

        $is_admin_user = current_user_can('manage_options');
        $current_user_id = get_current_user_id();
        $owner_scope = $this->build_owner_scope($is_admin_user, $current_user_id);
        $manager_search_scope = EstateOfficeCRM_Access::is_manager_user($current_user_id)
            ? EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $current_user_id)
            : ['sql' => '', 'params' => []];
        $dashboard_user_ids_scope = [];
        $manager_office_id = 0;
        if (EstateOfficeCRM_Access::is_manager_user($current_user_id)) {
            $manager_office_id = EstateOfficeCRM_Access::get_user_office_id($current_user_id, $this->tables);
            if ($manager_office_id > 0) {
                $dashboard_user_ids_scope = EstateOfficeCRM_Access::get_office_user_ids($manager_office_id, $this->tables);
            }
            if ($current_user_id > 0 && ! in_array($current_user_id, $dashboard_user_ids_scope, true)) {
                $dashboard_user_ids_scope[] = $current_user_id;
            }
            $dashboard_user_ids_scope = array_values(array_unique(array_filter(array_map('absint', $dashboard_user_ids_scope))));
        }
        $current_datetime = current_datetime();
        $today = $current_datetime->format('Y-m-d');
        $expiring_until = $current_datetime->modify('+30 days')->format('Y-m-d');

        $agents_count = 0;
        if (empty($dashboard_user_ids_scope)) {
            $agents_count = count(get_users(['role__in' => [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER], 'fields' => 'ids']));
        } else {
            foreach ($dashboard_user_ids_scope as $dashboard_user_id) {
                $dashboard_user = get_userdata((int) $dashboard_user_id);
                if (! $dashboard_user instanceof WP_User) {
                    continue;
                }
                $roles = is_array($dashboard_user->roles) ? $dashboard_user->roles : [];
                if (in_array(EstateOfficeCRM_Installer::ROLE_AGENT, $roles, true) || in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true)) {
                    $agents_count++;
                }
            }
        }

        $offices_count = $this->count_table($this->tables['offices'], 'is_active = 1');
        if (! $is_admin_user && EstateOfficeCRM_Access::is_manager_user($current_user_id)) {
            if ($manager_office_id > 0) {
                $offices_count = $this->count_table($this->tables['offices'], 'is_active = 1 AND id = %d', [$manager_office_id]);
            } else {
                $offices_count = 0;
            }
        }

        $stats = [
            'properties' => $this->count_published_properties($owner_scope['sql'], $owner_scope['params']),
            'agreements' => $this->count_active_agreements($owner_scope['sql'], $owner_scope['params']),
            'searches' => $this->count_active_searches($manager_search_scope['sql'], $manager_search_scope['params']),
            'clients' => $this->count_table($this->tables['clients'], 'is_active = 1' . $owner_scope['sql'], $owner_scope['params']),
            'agents' => $agents_count,
            'offices' => $offices_count,
            'export_www' => $this->count_table($this->tables['properties'], 'is_active = 1 AND export_www = 1' . $owner_scope['sql'], $owner_scope['params']),
            'expiring_30' => $this->count_table(
                $this->tables['agreements'],
                'is_active = 1 AND is_indefinite = 0 AND date_end IS NOT NULL AND date_end >= %s AND date_end <= %s' . $owner_scope['sql'],
                array_merge([$today, $expiring_until], $owner_scope['params'])
            ),
            'draft_map' => $this->count_table($this->tables['properties'], 'is_active = 1 AND (latitude IS NULL OR longitude IS NULL)' . $owner_scope['sql'], $owner_scope['params']),
            'without_owner' => $this->count_table($this->tables['agreements'], 'is_active = 1 AND (owner_user_id IS NULL OR owner_user_id = 0)'),
        ];

        $agreement_distribution_rows = $this->load_distribution_data(
            $this->tables['agreements'],
            'transaction_type',
            'is_active = 1' . $owner_scope['sql'],
            $owner_scope['params'],
            8
        );
        $agreement_distribution = [
            $this->format_admin_transaction_label('SPRZEDAZ') => 0,
            $this->format_admin_transaction_label('KUPNO') => 0,
            $this->format_admin_transaction_label('WYNAJEM') => 0,
            $this->format_admin_transaction_label('NAJEM') => 0,
        ];
        foreach ($agreement_distribution_rows as $distribution_row) {
            $label = $this->format_admin_transaction_label((string) ($distribution_row['label'] ?? ''));
            $agreement_distribution[$label] = (int) ($distribution_row['total'] ?? 0);
        }

        $property_distribution_rows = $this->load_distribution_data(
            $this->tables['properties'],
            'property_type',
            'is_active = 1' . $owner_scope['sql'],
            $owner_scope['params'],
            8
        );
        $property_distribution = [
            $this->format_admin_property_type_label('MIESZKANIE') => 0,
            $this->format_admin_property_type_label('DOM') => 0,
            $this->format_admin_property_type_label('DZIALKA') => 0,
            $this->format_admin_property_type_label('LOKAL_HU') => 0,
        ];
        foreach ($property_distribution_rows as $distribution_row) {
            $label = $this->format_admin_property_type_label((string) ($distribution_row['label'] ?? ''));
            $property_distribution[$label] = (int) ($distribution_row['total'] ?? 0);
        }

        $stage_distribution_rows = $this->load_distribution_data(
            $this->tables['agreements'],
            'current_stage',
            'is_active = 1' . $owner_scope['sql'],
            $owner_scope['params'],
            8
        );
        $stage_distribution = [];
        foreach ($stage_distribution_rows as $distribution_row) {
            $label = $this->format_admin_stage_label((string) ($distribution_row['label'] ?? ''));
            $stage_distribution[$label] = (int) ($distribution_row['total'] ?? 0);
        }

        $recent_agreements = $this->load_recent_agreements($owner_scope['sql'], $owner_scope['params'], 8);
        $recent_properties = $this->load_recent_properties($owner_scope['sql'], $owner_scope['params'], 8);
        $top_agents = $this->load_agent_kpi_rows(8, $dashboard_user_ids_scope);
        $dashboard_logo_url = EOCRM_URL . 'assets/images/eocrm-logo-horizontal.png';

        echo '<div class="wrap eocrm-admin-wrap eocrm-settings-page eocrm-dashboard-page">';
        echo '<div class="eocrm-dashboard-brand"><img src="' . esc_url($dashboard_logo_url) . '" alt="Estate Office CRM" class="eocrm-dashboard-logo"></div>';
        echo '<h1>Estate Office CRM - Pulpit</h1>';
        $this->render_query_notice();
        echo '<p class="eocrm-dashboard-lead">Panel menedzerski wersji <strong>' . esc_html(EOCRM_VERSION) . '</strong>. Dane sa liczone w oparciu o aktywne rekordy CRM.</p>';

        // Szybkie przejscia - blok przyciskow na samej gorze pulpitu.
        // URL-e front-endu sa rozwiazywane dynamicznie przez get_permalink() z ID strony zapisanego
        // w opcji `eocrm_page_ids` (oraz fallback po slugu) - dzieki temu linki dzialaja niezaleznie
        // od ustawien Permalinks (Plain / Day name / Post name / itd.).
        $crm_frontend_links = [
            'crm' => [
                'label' => 'Panel CRM',
                'desc'  => 'Front-end: pulpit operacyjny',
                'icon'  => 'dashicons-dashboard',
                'slug_fallback' => 'crm',
            ],
            'properties' => [
                'label' => 'Nieruchomosci',
                'desc'  => 'Front-end: lista i profile ofert',
                'icon'  => 'dashicons-building',
                'slug_fallback' => 'crm-nieruchomosci',
            ],
            'searches' => [
                'label' => 'Poszukiwania',
                'desc'  => 'Front-end: zapytania klientow',
                'icon'  => 'dashicons-search',
                'slug_fallback' => 'crm-poszukiwania',
            ],
            'clients' => [
                'label' => 'Klienci',
                'desc'  => 'Front-end: baza klientow',
                'icon'  => 'dashicons-businessperson',
                'slug_fallback' => 'crm-klienci',
            ],
            'agreements' => [
                'label' => 'Umowy',
                'desc'  => 'Front-end: lista umow',
                'icon'  => 'dashicons-media-document',
                'slug_fallback' => 'crm-umowy',
            ],
            'agent_login' => [
                'label' => 'Panel logowania',
                'desc'  => 'Publiczny login dla agentow',
                'icon'  => 'dashicons-lock',
                'slug_fallback' => 'panel-logowania-agenta',
            ],
        ];

        $quick_links = [];
        foreach ($crm_frontend_links as $page_key => $page_meta) {
            $url = $this->resolve_crm_frontend_url((string) $page_key, (string) ($page_meta['slug_fallback'] ?? ''));
            if ($url === '') {
                continue;
            }
            $quick_links[] = [
                'label' => (string) $page_meta['label'],
                'desc'  => (string) $page_meta['desc'],
                'url'   => $url,
                'icon'  => (string) ($page_meta['icon'] ?? 'dashicons-arrow-right-alt2'),
                'group' => 'frontend',
            ];
        }

        // Panel administratora.
        $quick_links[] = [
            'label' => 'Agenci i biura',
            'desc'  => 'Zespol, biura i przypisania',
            'url'   => admin_url('admin.php?page=' . self::AGENTS_SLUG),
            'icon'  => 'dashicons-groups',
            'group' => 'admin',
        ];
        $quick_links[] = [
            'label' => 'Ustawienia CRM',
            'desc'  => 'Konfiguracja, eksport, szablony',
            'url'   => admin_url('admin.php?page=' . self::SETTINGS_SLUG),
            'icon'  => 'dashicons-admin-settings',
            'group' => 'admin',
        ];
        $quick_links[] = [
            'label' => 'O CRM',
            'desc'  => 'Funkcje, kontakt, plany rozwoju',
            'url'   => admin_url('admin.php?page=' . self::ABOUT_SLUG),
            'icon'  => 'dashicons-info',
            'group' => 'admin',
        ];
        $quick_links[] = [
            'label' => 'Licencja',
            'desc'  => 'Status klucza i abonamentu',
            'url'   => admin_url('admin.php?page=' . self::LICENSE_SLUG),
            'icon'  => 'dashicons-admin-network',
            'group' => 'admin',
        ];

        echo '<section class="eocrm-dashboard-quick-links" aria-label="Szybkie przejscia">';
        echo '<h2 class="eocrm-dashboard-quick-links__heading">Szybkie przejscia</h2>';
        echo '<div class="eocrm-dashboard-quick-links__grid">';
        foreach ($quick_links as $link) {
            $icon_class = (string) ($link['icon'] ?? 'dashicons-arrow-right-alt2');
            $group = (string) ($link['group'] ?? 'admin');
            $is_frontend = $group === 'frontend';
            $extra_attrs = $is_frontend ? ' target="_blank" rel="noopener noreferrer"' : '';
            $class_extra = $is_frontend ? ' eocrm-dashboard-quick-link--frontend' : ' eocrm-dashboard-quick-link--admin';
            echo '<a class="eocrm-dashboard-quick-link' . esc_attr($class_extra) . '" href="' . esc_url((string) $link['url']) . '"' . $extra_attrs . '>';
            echo '<span class="eocrm-dashboard-quick-link__icon dashicons ' . esc_attr($icon_class) . '" aria-hidden="true"></span>';
            echo '<span class="eocrm-dashboard-quick-link__body">';
            echo '<span class="eocrm-dashboard-quick-link__label">' . esc_html((string) $link['label']) . '</span>';
            echo '<span class="eocrm-dashboard-quick-link__desc">' . esc_html((string) $link['desc']) . '</span>';
            echo '</span>';
            $chevron_icon = $is_frontend ? 'dashicons-external' : 'dashicons-arrow-right-alt2';
            echo '<span class="eocrm-dashboard-quick-link__chevron dashicons ' . esc_attr($chevron_icon) . '" aria-hidden="true"></span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</section>';

        echo '<div class="eocrm-admin-cards eocrm-dashboard-kpis">';
        echo '<div class="eocrm-admin-card"><h2>Opublikowane nieruchomosci</h2><p>' . esc_html((string) $stats['properties']) . '</p><small>eksport WWW bez sprzedane/wynajete</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Aktywne umowy</h2><p>' . esc_html((string) $stats['agreements']) . '</p><small>bez etapu "Umowa zakonczona"</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Aktywne poszukiwania</h2><p>' . esc_html((string) $stats['searches']) . '</p><small>aktywny popyt</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Aktywni klienci</h2><p>' . esc_html((string) $stats['clients']) . '</p><small>powiazane rekordy</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Aktywni agenci</h2><p>' . esc_html((string) $stats['agents']) . '</p><small>rola Agent + Menedzer</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Aktywne biura</h2><p>' . esc_html((string) $stats['offices']) . '</p><small>widoczne w CRM</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Eksport WWW</h2><p>' . esc_html((string) $stats['export_www']) . '</p><small>oferty online</small></div>';
        echo '<div class="eocrm-admin-card"><h2>Wygasaja do 30 dni</h2><p>' . esc_html((string) $stats['expiring_30']) . '</p><small>umowy terminowe</small></div>';
        echo '</div>';

        echo '<div class="eocrm-admin-grid eocrm-dashboard-grid">';
        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Umowy wg typu transakcji</h2>';
        $this->render_distribution_chart($agreement_distribution);
        echo '</section>';

        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Nieruchomosci wg rodzaju</h2>';
        $this->render_distribution_chart($property_distribution);
        echo '</section>';

        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Lejek etapow umow</h2>';
        $this->render_distribution_chart($stage_distribution, 'Brak etapow do analizy.');
        echo '</section>';

        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Kontrola danych</h2>';
        echo '<ul class="eocrm-dashboard-alerts">';
        echo '<li><strong>Umowy bez opiekuna:</strong> ' . esc_html((string) $stats['without_owner']) . '</li>';
        echo '<li><strong>Oferty bez lokalizacji mapy:</strong> ' . esc_html((string) $stats['draft_map']) . '</li>';
        echo '<li><strong>Umowy wygasajace (30 dni):</strong> ' . esc_html((string) $stats['expiring_30']) . '</li>';
        echo '<li><strong>Oferty z eksportem WWW:</strong> ' . esc_html((string) $stats['export_www']) . '</li>';
        echo '</ul>';
        echo '</section>';
        echo '</div>';

        echo '<div class="eocrm-admin-grid eocrm-dashboard-grid">';
        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Najnowsze umowy</h2>';
        if (empty($recent_agreements)) {
            echo '<p class="description">Brak umow do wyswietlenia.</p>';
        } else {
            echo '<table class="widefat striped eocrm-admin-table eocrm-dashboard-table"><thead><tr><th>Numer</th><th>Typ</th><th>Etap</th><th>Podpisanie</th><th>Koniec</th><th>Opiekun</th></tr></thead><tbody>';
            foreach ($recent_agreements as $row) {
                $owner_name = (string) ($row['owner_name'] ?? '');
                if ($owner_name === '') {
                    $owner_name = '-';
                }
                $date_end = (string) ($row['date_end'] ?? '');
                if ($date_end === '' || $date_end === '0000-00-00') {
                    $date_end = '-';
                }
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) ($row['agreement_number'] ?? '-')) . '</strong></td>';
                echo '<td>' . esc_html($this->format_admin_transaction_label((string) ($row['transaction_type'] ?? ''))) . '</td>';
                echo '<td>' . esc_html($this->format_admin_stage_label((string) ($row['current_stage'] ?? ''))) . '</td>';
                echo '<td>' . esc_html((string) ($row['date_signed'] ?? '-')) . '</td>';
                echo '<td>' . esc_html($date_end) . '</td>';
                echo '<td>' . esc_html($owner_name) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Najnowsze nieruchomosci</h2>';
        if (empty($recent_properties)) {
            echo '<p class="description">Brak nieruchomosci do wyswietlenia.</p>';
        } else {
            echo '<table class="widefat striped eocrm-admin-table eocrm-dashboard-table"><thead><tr><th>Numer oferty</th><th>Adres</th><th>Rodzaj</th><th>Cena</th><th>WWW</th><th>Opiekun</th></tr></thead><tbody>';
            foreach ($recent_properties as $row) {
                $owner_name = (string) ($row['owner_name'] ?? '');
                if ($owner_name === '') {
                    $owner_name = '-';
                }
                $street = trim((string) ($row['street'] ?? ''));
                $building_no = trim((string) ($row['building_no'] ?? ''));
                $city = trim((string) ($row['city'] ?? ''));
                $address = trim($street . ' ' . $building_no);
                if ($address === '') {
                    $address = $city !== '' ? $city : '-';
                } elseif ($city !== '') {
                    $address .= ', ' . $city;
                }
                $currency = trim((string) ($row['price_currency'] ?? 'PLN'));
                if ($currency === '') {
                    $currency = 'PLN';
                }
                $price_raw = isset($row['price']) ? (float) $row['price'] : 0.0;
                $price_text = $price_raw > 0 ? number_format_i18n($price_raw, 0) . ' ' . $currency : '-';
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) ($row['offer_number'] ?? '-')) . '</strong></td>';
                echo '<td>' . esc_html($address) . '</td>';
                echo '<td>' . esc_html($this->format_admin_property_type_label((string) ($row['property_type'] ?? ''))) . '</td>';
                echo '<td>' . esc_html($price_text) . '</td>';
                echo '<td>' . esc_html(((int) ($row['export_www'] ?? 0) === 1) ? 'Tak' : 'Nie') . '</td>';
                echo '<td>' . esc_html($owner_name) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';
        echo '</div>';

        echo '<section class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Skutecznosc agentow</h2>';
        if (empty($top_agents)) {
            echo '<p class="description">Brak danych agentow.</p>';
        } else {
            echo '<table class="widefat striped eocrm-admin-table eocrm-dashboard-table"><thead><tr><th>Agent</th><th>Umowy</th><th>Nieruchomosci</th><th>Klienci</th></tr></thead><tbody>';
            foreach ($top_agents as $agent_row) {
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) ($agent_row['display_name'] ?? '-')) . '</strong></td>';
                echo '<td>' . esc_html((string) ((int) ($agent_row['agreements'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ((int) ($agent_row['properties'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ((int) ($agent_row['clients'] ?? 0))) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p class="description">Wskazowka: liczby na tym pulpicie sa zgodne z frontendowym Pulpitem CRM i bazuja na rekordach aktywnych.</p>';
        echo '</section>';

        echo '<div class="eocrm-admin-panel eocrm-dashboard-panel">';
        echo '<h2>Wnioski dla menedzera</h2>';
        echo '<div class="eocrm-dashboard-insights">';
        echo '<article>';
        echo '<h3>Priorytet operacyjny</h3>';
        echo '<p>Monitoruj umowy wygasajace do 30 dni i oferty bez mapy. Te dwa obszary najczesciej blokuja finalizacje procesu sprzedazy/wynajmu.</p>';
        echo '</article>';
        echo '<article>';
        echo '<h3>Efektywnosc zespolu</h3>';
        echo '<p>Porownuj liczbe aktywnych umow i ofert per agent. Pozwala to szybciej balansowac obciazenie i planowac wsparcie dla najbardziej aktywnych opiekunow.</p>';
        echo '</article>';
        echo '<article>';
        echo '<h3>Widocznosc online</h3>';
        echo '<p>Kontroluj KPI eksportu WWW. Niski udzial ofert z eksportem moze bezposrednio ograniczac naplyw leadow z witryny.</p>';
        echo '</article>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    public function render_agent_login_admin_page(): void
    {
        if (! current_user_can('eocrm_manage_settings')) {
            wp_die('Brak uprawnien.');
        }

        $login_url = $this->resolve_crm_frontend_url('agent_login', 'panel-logowania-agenta');
        $crm_url = $this->resolve_crm_frontend_url('crm', 'crm');

        echo '<div class="wrap eocrm-admin-wrap eocrm-settings-page eocrm-agent-login-admin-page">';
        echo '<h1>Panel logowania Agenta</h1>';
        echo '<div class="eocrm-admin-grid">';

        echo '<section class="eocrm-admin-panel">';
        echo '<h2>Publiczny panel logowania</h2>';
        echo '<p>Ta strona sluzy agentom, menedzerom i administratorom do szybkiego wejscia do front-endowego panelu CRM.</p>';
        echo '<p><code>[eocrm_agent_login]</code></p>';

        if ($login_url !== '') {
            echo '<p><a class="button button-primary" href="' . esc_url($login_url) . '" target="_blank" rel="noopener noreferrer">Otworz panel logowania</a></p>';
        } else {
            echo '<p class="notice notice-warning inline"><span>Nie znaleziono strony panelu logowania. Zostanie utworzona automatycznie przy kolejnej aktywacji/aktualizacji wtyczki lub po zapisaniu struktury stron przez instalator.</span></p>';
        }

        echo '</section>';

        echo '<section class="eocrm-admin-panel">';
        echo '<h2>Szybkie przejscia</h2>';
        echo '<p>Skroty dla administratora, zeby bez szukania przejsc do najwazniejszych miejsc pracy.</p>';
        echo '<div class="eocrm-admin-action-row">';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '">Pulpit administratora CRM</a>';
        if ($crm_url !== '') {
            echo '<a class="button" href="' . esc_url($crm_url) . '" target="_blank" rel="noopener noreferrer">Front-end CRM</a>';
        }
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SETTINGS_SLUG)) . '">Ustawienia</a>';
        echo '</div>';
        echo '</section>';

        echo '</div>';
        echo '</div>';
    }

    public function render_agents_page(): void
    {
        if (! current_user_can('eocrm_manage_agents')) {
            wp_die('Brak uprawnien.');
        }

        $edit_user_id = isset($_GET['agent_id']) ? absint((string) wp_unslash($_GET['agent_id'])) : 0;
        $edit_office_id = isset($_GET['office_id']) ? absint((string) wp_unslash($_GET['office_id'])) : 0;
        $active_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
        if (! in_array($active_tab, ['offices', 'agents', 'page_content'], true)) {
            $active_tab = $edit_user_id > 0 ? 'agents' : 'offices';
        }
        if ($edit_office_id > 0) {
            $active_tab = 'offices';
        }
        if ($edit_user_id > 0) {
            $active_tab = 'agents';
        }
        $agents = $this->get_agents_with_profiles();
        $offices = $this->get_offices(false);
        $edit_agent = null;
        $edit_office = null;

        $offices_by_id = [];
        foreach ($offices as $office_row) {
            $office_row_id = isset($office_row['id']) ? (int) $office_row['id'] : 0;
            if ($office_row_id > 0) {
                $offices_by_id[$office_row_id] = $office_row;
            }
        }

        $agents_by_office = [];
        foreach ($agents as $agent_row) {
            $agent_office_id = isset($agent_row['office_id']) ? (int) $agent_row['office_id'] : 0;
            if (! isset($agents_by_office[$agent_office_id])) {
                $agents_by_office[$agent_office_id] = 0;
            }
            $agents_by_office[$agent_office_id]++;
        }

        if ($edit_user_id > 0) {
            foreach ($agents as $candidate) {
                if ((int) ($candidate['id'] ?? 0) === $edit_user_id) {
                    $edit_agent = $candidate;
                    break;
                }
            }
        }

        if ($edit_office_id > 0) {
            foreach ($offices as $office_candidate) {
                if ((int) ($office_candidate['id'] ?? 0) === $edit_office_id) {
                    $edit_office = $office_candidate;
                    break;
                }
            }
        }

        $offices_tab_url = add_query_arg(
            [
                'page' => self::AGENTS_SLUG,
                'tab' => 'offices',
            ],
            admin_url('admin.php')
        );
        $agents_tab_url = add_query_arg(
            [
                'page' => self::AGENTS_SLUG,
                'tab' => 'agents',
            ],
            admin_url('admin.php')
        );
        $page_content_tab_url = add_query_arg(
            [
                'page' => self::AGENTS_SLUG,
                'tab' => 'page_content',
            ],
            admin_url('admin.php')
        );
        $offices_tab_hidden = $active_tab === 'offices' ? '' : ' eocrm-tab-hidden';
        $agents_tab_hidden = $active_tab === 'agents' ? '' : ' eocrm-tab-hidden';
        $page_content_tab_hidden = $active_tab === 'page_content' ? '' : ' eocrm-tab-hidden';

        $offices_agents_page_title = trim($this->get_setting('offices_agents_page_title'));
        if ($offices_agents_page_title === '') {
            $offices_agents_page_title = 'Biura i Agenci';
        }
        $offices_agents_page_intro = trim($this->get_setting('offices_agents_page_intro'));
        if ($offices_agents_page_intro === '') {
            $offices_agents_page_intro = 'Poznaj nasz zespol doradcow nieruchomosci i sprawdz, kto prowadzi oferty w Twojej okolicy.';
        }

        echo '<div class="wrap eocrm-admin-wrap eocrm-agents-page">';
        echo '<h1>Estate Office CRM - Agenci</h1>';
        $this->render_query_notice();
        echo '<h2 class="nav-tab-wrapper eocrm-settings-tabs eocrm-agents-tabs">';
        echo '<a class="nav-tab ' . ($active_tab === 'offices' ? 'nav-tab-active' : '') . '" href="' . esc_url($offices_tab_url) . '">Biura</a>';
        echo '<a class="nav-tab ' . ($active_tab === 'agents' ? 'nav-tab-active' : '') . '" href="' . esc_url($agents_tab_url) . '">Agenci</a>';
        echo '<a class="nav-tab ' . ($active_tab === 'page_content' ? 'nav-tab-active' : '') . '" href="' . esc_url($page_content_tab_url) . '">Strona Biura i Agenci</a>';
        echo '</h2>';

        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-offices' . esc_attr($offices_tab_hidden) . '">';
        echo '<h2>Lista biur</h2>';
        echo '<table class="widefat striped eocrm-admin-table"><thead><tr><th>ID</th><th>Kolejnosc</th><th>Nazwa biura</th><th>Miasto</th><th>E-mail</th><th>Telefon</th><th>Widoczne publicznie</th><th>Liczba agentow</th><th>Akcje</th></tr></thead><tbody>';

        if (empty($offices)) {
            echo '<tr><td colspan="9">Brak biur.</td></tr>';
        } else {
            foreach ($offices as $office) {
                $office_id = (int) ($office['id'] ?? 0);
                $office_display_order = isset($office['display_order']) ? (int) $office['display_order'] : 100;
                $edit_office_link = add_query_arg(
                    [
                        'page' => self::AGENTS_SLUG,
                        'tab' => 'offices',
                        'office_id' => $office_id,
                    ],
                    admin_url('admin.php')
                );

                echo '<tr>';
                echo '<td>' . esc_html((string) $office_id) . '</td>';
                echo '<td>' . esc_html((string) $office_display_order) . '</td>';
                echo '<td>' . esc_html((string) ($office['office_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($office['city'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($office['office_email'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($office['office_phone'] ?? '')) . '</td>';
                echo '<td>' . esc_html(((int) ($office['is_public'] ?? 0) === 1) ? 'Tak' : 'Nie') . '</td>';
                echo '<td>' . esc_html((string) ($agents_by_office[$office_id] ?? 0)) . '</td>';
                echo '<td><a class="button" href="' . esc_url($edit_office_link) . '">Edytuj</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-agents' . esc_attr($agents_tab_hidden) . '">';
        echo '<h2>Lista agentow</h2>';
        echo '<table class="widefat striped eocrm-admin-table"><thead><tr><th>ID</th><th>Kolejnosc</th><th>Zdjecie</th><th>Login</th><th>Nazwa</th><th>Rola</th><th>E-mail</th><th>Telefon</th><th>Biuro</th><th>Miasto</th><th>Widoczny publicznie</th><th>Akcje</th></tr></thead><tbody>';

        if (empty($agents)) {
            echo '<tr><td colspan="12">Brak agentow.</td></tr>';
        } else {
            foreach ($agents as $agent) {
                $edit_link = add_query_arg(
                    [
                        'page' => self::AGENTS_SLUG,
                        'tab' => 'agents',
                        'agent_id' => (int) $agent['id'],
                    ],
                    admin_url('admin.php')
                );

                $office_id = isset($agent['office_id']) ? (int) $agent['office_id'] : 0;
                $office_name = '';
                if ($office_id > 0 && isset($offices_by_id[$office_id])) {
                    $office_name = (string) ($offices_by_id[$office_id]['office_name'] ?? '');
                }

                $photo_id = isset($agent['photo_id']) ? absint((string) $agent['photo_id']) : 0;
                $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
                $agent_display_order = isset($agent['display_order']) ? (int) $agent['display_order'] : 100;

                echo '<tr>';
                echo '<td>' . esc_html((string) $agent['id']) . '</td>';
                echo '<td>' . esc_html((string) $agent_display_order) . '</td>';
                echo '<td>';
                if (is_string($photo_url) && $photo_url !== '') {
                    echo '<img src="' . esc_url($photo_url) . '" alt="Zdjecie agenta" style="width:44px;height:44px;object-fit:cover;border-radius:50%;border:1px solid #d7e1ef;">';
                } else {
                    echo '<span class="description">Brak</span>';
                }
                echo '</td>';
                echo '<td>' . esc_html((string) $agent['user_login']) . '</td>';
                echo '<td>' . esc_html((string) $agent['display_name']) . '</td>';
                echo '<td>' . esc_html($this->get_crm_role_label((string) ($agent['crm_role'] ?? EstateOfficeCRM_Installer::ROLE_AGENT))) . '</td>';
                echo '<td>' . esc_html((string) $agent['user_email']) . '</td>';
                echo '<td>' . esc_html((string) ($agent['phone'] ?? '')) . '</td>';
                echo '<td>' . esc_html($office_name) . '</td>';
                echo '<td>' . esc_html((string) ($agent['city'] ?? '')) . '</td>';
                echo '<td>' . esc_html(((int) ($agent['is_public'] ?? 0) === 1) ? 'Tak' : 'Nie') . '</td>';
                echo '<td><a class="button" href="' . esc_url($edit_link) . '">Edytuj</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="eocrm-admin-grid">';
        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-offices' . esc_attr($offices_tab_hidden) . '">';
        echo '<h2>Dodaj biuro</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::AGENTS_SLUG . '&tab=offices')) . '">';
        wp_nonce_field('eocrm_create_office');
        echo '<input type="hidden" name="eocrm_action" value="create_office">';
        echo '<p><label>Nazwa biura<br><input class="regular-text" type="text" name="office_name" required></label></p>';
        echo '<p><label>E-mail biura<br><input class="regular-text" type="email" name="office_email"></label></p>';
        echo '<p><label>Telefon biura<br><input class="regular-text" type="text" name="office_phone"></label></p>';
        echo '<p><label>Adres biura<br><input class="regular-text" type="text" name="office_address_line"></label></p>';
        echo '<p><label>Miasto<br><input class="regular-text" type="text" name="office_city"></label></p>';
        echo '<p><label>Kod pocztowy<br><input class="regular-text" type="text" name="office_postal_code"></label></p>';
        echo '<p><label>Opis biura<br><textarea class="large-text" rows="4" name="office_description"></textarea></label></p>';
        echo '<p><label>Kolejnosc wyswietlania (0-9999)<br><input class="small-text" type="number" min="0" max="9999" name="office_display_order" value="100"></label></p>';
        echo '<p><label>Logo biura (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-office-logo-id-new" name="office_logo_id" value=""></label></p>';
        echo '<p><button type="button" class="button" data-eocrm-media="eocrm-office-logo-id-new" data-eocrm-preview="eocrm-office-logo-preview-new">Wybierz logo biura</button></p>';
        echo '<div id="eocrm-office-logo-preview-new"></div>';
        echo '<p><label><input type="checkbox" name="office_is_public" value="1" checked> Pokaz biuro publicznie (strona Biura i Agenci)</label></p>';
        submit_button('Dodaj biuro');
        echo '</form>';
        echo '</div>';

        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-offices' . esc_attr($offices_tab_hidden) . '">';
        echo '<h2>Edycja biura</h2>';

        if (! is_array($edit_office)) {
            echo '<p>Wybierz biuro z tabeli, aby je edytowac.</p>';
        } else {
            $edit_office_logo_id = isset($edit_office['logo_id']) ? absint((string) $edit_office['logo_id']) : 0;
            $edit_office_logo_url = $edit_office_logo_id > 0 ? wp_get_attachment_image_url($edit_office_logo_id, 'thumbnail') : '';
            $edit_office_display_order = isset($edit_office['display_order']) ? (int) $edit_office['display_order'] : 100;

            echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::AGENTS_SLUG . '&tab=offices')) . '">';
            wp_nonce_field('eocrm_update_office');
            echo '<input type="hidden" name="eocrm_action" value="update_office">';
            echo '<input type="hidden" name="office_id" value="' . (int) $edit_office['id'] . '">';
            echo '<p><label>Nazwa biura<br><input class="regular-text" type="text" name="office_name" value="' . esc_attr((string) ($edit_office['office_name'] ?? '')) . '" required></label></p>';
            echo '<p><label>E-mail biura<br><input class="regular-text" type="email" name="office_email" value="' . esc_attr((string) ($edit_office['office_email'] ?? '')) . '"></label></p>';
            echo '<p><label>Telefon biura<br><input class="regular-text" type="text" name="office_phone" value="' . esc_attr((string) ($edit_office['office_phone'] ?? '')) . '"></label></p>';
            echo '<p><label>Adres biura<br><input class="regular-text" type="text" name="office_address_line" value="' . esc_attr((string) ($edit_office['address_line'] ?? '')) . '"></label></p>';
            echo '<p><label>Miasto<br><input class="regular-text" type="text" name="office_city" value="' . esc_attr((string) ($edit_office['city'] ?? '')) . '"></label></p>';
            echo '<p><label>Kod pocztowy<br><input class="regular-text" type="text" name="office_postal_code" value="' . esc_attr((string) ($edit_office['postal_code'] ?? '')) . '"></label></p>';
            echo '<p><label>Opis biura<br><textarea class="large-text" rows="4" name="office_description">' . esc_textarea((string) ($edit_office['description'] ?? '')) . '</textarea></label></p>';
            echo '<p><label>Kolejnosc wyswietlania (0-9999)<br><input class="small-text" type="number" min="0" max="9999" name="office_display_order" value="' . esc_attr((string) $edit_office_display_order) . '"></label></p>';
            echo '<p><label>Logo biura (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-office-logo-id-edit" name="office_logo_id" value="' . esc_attr((string) $edit_office_logo_id) . '"></label></p>';
            echo '<p><button type="button" class="button" data-eocrm-media="eocrm-office-logo-id-edit" data-eocrm-preview="eocrm-office-logo-preview-edit">Wybierz logo biura</button></p>';
            echo '<div id="eocrm-office-logo-preview-edit">';
            if (is_string($edit_office_logo_url) && $edit_office_logo_url !== '') {
                echo '<img src="' . esc_url($edit_office_logo_url) . '" alt="Logo biura" style="max-width:120px;height:auto;">';
            }
            echo '</div>';
            echo '<p><label><input type="checkbox" name="office_is_active" value="1" ' . checked((int) ($edit_office['is_active'] ?? 0), 1, false) . '> Biuro aktywne</label></p>';
            echo '<p><label><input type="checkbox" name="office_is_public" value="1" ' . checked((int) ($edit_office['is_public'] ?? 0), 1, false) . '> Pokaz biuro publicznie (strona Biura i Agenci)</label></p>';
            submit_button('Zapisz zmiany biura');
            echo '</form>';
        }

        echo '</div>';

        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-agents' . esc_attr($agents_tab_hidden) . '">';
        echo '<h2>Dodaj nowego agenta</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::AGENTS_SLUG . '&tab=agents')) . '">';
        wp_nonce_field('eocrm_create_agent');
        echo '<input type="hidden" name="eocrm_action" value="create_agent">';
        echo '<p><label>Login<br><input class="regular-text" type="text" name="agent_username" required></label></p>';
        echo '<p><label>E-mail<br><input class="regular-text" type="email" name="agent_email" required></label></p>';
        echo '<p><label>Display Name<br><input class="regular-text" type="text" name="agent_display_name"></label></p>';
        echo '<p><label>Rola CRM<br><select class="regular-text" name="agent_role">';
        foreach ($this->crm_user_role_options() as $role_key => $role_label) {
            echo '<option value="' . esc_attr((string) $role_key) . '">' . esc_html((string) $role_label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>Haslo (opcjonalne, puste = generowane automatycznie)<br><input class="regular-text" type="text" name="agent_password"></label></p>';
        echo '<p><label>Telefon<br><input class="regular-text" type="text" name="agent_phone"></label></p>';
        echo '<p><label>Adres<br><input class="regular-text" type="text" name="agent_address_line"></label></p>';
        echo '<p><label>Miasto<br><input class="regular-text" type="text" name="agent_city"></label></p>';
        echo '<p><label>Kod pocztowy<br><input class="regular-text" type="text" name="agent_postal_code"></label></p>';
        echo '<p><label>Biuro<br><select class="regular-text" name="agent_office_id"><option value="0">Brak przypisania</option>';
        foreach ($offices as $office) {
            $office_id = isset($office['id']) ? (int) $office['id'] : 0;
            $office_label = (string) ($office['office_name'] ?? '');
            if ((int) ($office['is_active'] ?? 0) !== 1) {
                $office_label .= ' (nieaktywne)';
            }
            echo '<option value="' . (int) $office_id . '">' . esc_html($office_label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>Zdjecie agenta (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-agent-photo-id-new" name="agent_photo_id" value=""></label></p>';
        echo '<p><button type="button" class="button" data-eocrm-media="eocrm-agent-photo-id-new" data-eocrm-preview="eocrm-agent-photo-preview-new" data-eocrm-crop-agent="1">Wybierz i wykadruj zdjecie</button></p>';
        echo '<div id="eocrm-agent-photo-preview-new"></div>';
        echo '<p><label>Kolejnosc wyswietlania (0-9999)<br><input class="small-text" type="number" min="0" max="9999" name="agent_display_order" value="100"></label></p>';
        echo '<p><label>Opis / Biografia<br><textarea class="large-text" rows="5" name="agent_bio"></textarea></label></p>';
        echo '<p><label><input type="checkbox" name="agent_is_public" value="1" checked> Pokaz agenta publicznie (strona Biura i Agenci)</label></p>';
        submit_button('Dodaj agenta');
        echo '</form>';
        echo '</div>';

        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-agents' . esc_attr($agents_tab_hidden) . '">';
        echo '<h2>Edycja agenta</h2>';

        if (! is_array($edit_agent)) {
            echo '<p>Wybierz agenta z tabeli, aby edytowac jego profil.</p>';
        } else {
            $edit_agent_photo_id = isset($edit_agent['photo_id']) ? absint((string) $edit_agent['photo_id']) : 0;
            $edit_agent_photo_url = $edit_agent_photo_id > 0 ? wp_get_attachment_image_url($edit_agent_photo_id, 'medium') : '';
            $edit_agent_office_id = isset($edit_agent['office_id']) ? (int) $edit_agent['office_id'] : 0;
            $edit_agent_display_order = isset($edit_agent['display_order']) ? (int) $edit_agent['display_order'] : 100;
            $edit_agent_crm_role = $this->sanitize_crm_user_role((string) ($edit_agent['crm_role'] ?? EstateOfficeCRM_Installer::ROLE_AGENT));

            echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::AGENTS_SLUG . '&tab=agents')) . '">';
            wp_nonce_field('eocrm_update_agent');
            echo '<input type="hidden" name="eocrm_action" value="update_agent">';
            echo '<input type="hidden" name="agent_id" value="' . (int) $edit_agent['id'] . '">';
            echo '<p><strong>Login:</strong> ' . esc_html((string) $edit_agent['user_login']) . '</p>';
            echo '<p><label>E-mail<br><input class="regular-text" type="email" name="agent_email" value="' . esc_attr((string) $edit_agent['user_email']) . '" required></label></p>';
            echo '<p><label>Display Name<br><input class="regular-text" type="text" name="agent_display_name" value="' . esc_attr((string) $edit_agent['display_name']) . '"></label></p>';
            echo '<p><label>Rola CRM<br><select class="regular-text" name="agent_role">';
            foreach ($this->crm_user_role_options() as $role_key => $role_label) {
                echo '<option value="' . esc_attr((string) $role_key) . '"' . selected($edit_agent_crm_role, (string) $role_key, false) . '>' . esc_html((string) $role_label) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label>Telefon<br><input class="regular-text" type="text" name="agent_phone" value="' . esc_attr((string) ($edit_agent['phone'] ?? '')) . '"></label></p>';
            echo '<p><label>Adres<br><input class="regular-text" type="text" name="agent_address_line" value="' . esc_attr((string) ($edit_agent['address_line'] ?? '')) . '"></label></p>';
            echo '<p><label>Miasto<br><input class="regular-text" type="text" name="agent_city" value="' . esc_attr((string) ($edit_agent['city'] ?? '')) . '"></label></p>';
            echo '<p><label>Kod pocztowy<br><input class="regular-text" type="text" name="agent_postal_code" value="' . esc_attr((string) ($edit_agent['postal_code'] ?? '')) . '"></label></p>';
            echo '<p><label>Biuro<br><select class="regular-text" name="agent_office_id"><option value="0">Brak przypisania</option>';
            foreach ($offices as $office) {
                $office_id = isset($office['id']) ? (int) $office['id'] : 0;
                $office_label = (string) ($office['office_name'] ?? '');
                if ((int) ($office['is_active'] ?? 0) !== 1) {
                    $office_label .= ' (nieaktywne)';
                }
                echo '<option value="' . (int) $office_id . '"' . selected($edit_agent_office_id, $office_id, false) . '>' . esc_html($office_label) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label>Zdjecie agenta (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-agent-photo-id-edit" name="agent_photo_id" value="' . esc_attr((string) $edit_agent_photo_id) . '"></label></p>';
            echo '<p><button type="button" class="button" data-eocrm-media="eocrm-agent-photo-id-edit" data-eocrm-preview="eocrm-agent-photo-preview-edit" data-eocrm-crop-agent="1">Wybierz i wykadruj zdjecie</button></p>';
            echo '<div id="eocrm-agent-photo-preview-edit">';
            if (is_string($edit_agent_photo_url) && $edit_agent_photo_url !== '') {
                echo '<img src="' . esc_url($edit_agent_photo_url) . '" alt="Zdjecie agenta" style="max-width:120px;height:auto;">';
            }
            echo '</div>';
            echo '<p><label>Kolejnosc wyswietlania (0-9999)<br><input class="small-text" type="number" min="0" max="9999" name="agent_display_order" value="' . esc_attr((string) $edit_agent_display_order) . '"></label></p>';
            echo '<p><label>Opis / Biografia<br><textarea class="large-text" rows="5" name="agent_bio">' . esc_textarea((string) ($edit_agent['bio'] ?? '')) . '</textarea></label></p>';
            echo '<p><label><input type="checkbox" name="agent_is_public" value="1" ' . checked((int) ($edit_agent['is_public'] ?? 0), 1, false) . '> Pokaz agenta publicznie (strona Biura i Agenci)</label></p>';
            submit_button('Zapisz zmiany agenta');
            echo '</form>';
        }

        echo '</div>';
        echo '</div>';

        echo '<div class="eocrm-admin-panel eocrm-agents-section eocrm-agents-section-page-content' . esc_attr($page_content_tab_hidden) . '">';
        echo '<h2>Strona publiczna: Biura i Agenci</h2>';
        echo '<p class="description">Tutaj edytujesz glowne tresci naglowka strony publicznej `Biura i Agenci`.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::AGENTS_SLUG . '&tab=page_content')) . '">';
        wp_nonce_field('eocrm_save_offices_agents_page_content');
        echo '<input type="hidden" name="eocrm_action" value="save_offices_agents_page_content">';
        echo '<p><label>Tytul strony<br><input class="regular-text" type="text" name="offices_agents_page_title" value="' . esc_attr($offices_agents_page_title) . '" required></label></p>';
        echo '<p><label>Opis pod tytulem<br><textarea class="large-text" rows="4" name="offices_agents_page_intro" required>' . esc_textarea($offices_agents_page_intro) . '</textarea></label></p>';
        submit_button('Zapisz tresci strony');
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('eocrm_manage_settings')) {
            wp_die('Brak uprawnien.');
        }

        $developer_access_enabled = $this->developer_access_enabled();
        $active_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'general';
        if (! in_array($active_tab, $this->settings_tab_keys($developer_access_enabled), true)) {
            $active_tab = 'general';
        }

        $google_maps_api_key = $this->get_setting('google_maps_api_key');
        $watermark_attachment_id = absint($this->get_setting('watermark_attachment_id'));
        $office_name = $this->get_setting('office_name');
        $office_logo_attachment_id = absint($this->get_setting('office_logo_attachment_id'));
        $language = $this->get_setting('language');
        $frontend_width_mode = $this->sanitize_frontend_width_mode($this->get_setting('frontend_width_mode'));
        $frontend_width_percent = $this->sanitize_frontend_width_percent($this->get_setting('frontend_width_percent'));
        $public_offers_per_page = $this->sanitize_public_offers_per_page($this->get_setting('public_offers_per_page'));
        $new_offer_duration_days = $this->sanitize_new_offer_duration_days($this->get_setting('new_offer_duration_days'));
        $sold_rented_www_retention_days = $this->sanitize_export_retention_days($this->get_setting('sold_rented_www_retention_days'), 30);
        $sold_rented_portals_retention_days = $this->sanitize_export_retention_days($this->get_setting('sold_rented_portals_retention_days'), 14);
        $mortgage_advisor_enabled = $this->get_setting('mortgage_advisor_enabled') !== '0' ? '1' : '0';
        $mortgage_advisor_logo_size_percent = $this->sanitize_mortgage_advisor_logo_size_percent($this->get_setting('mortgage_advisor_logo_size_percent'));
        $mortgage_advisor_logo_align_x = $this->sanitize_mortgage_advisor_logo_align_x($this->get_setting('mortgage_advisor_logo_align_x'));
        $mortgage_advisor_attachment_id = absint($this->get_setting('mortgage_advisor_attachment_id'));
        $mortgage_advisor_email = sanitize_email($this->get_setting('mortgage_advisor_email'));
        $public_offers_per_page_options = $this->public_offers_per_page_options();
        $date_format_options = EstateOfficeCRM_Numbering::date_format_options();
        $agreement_scope_options = EstateOfficeCRM_Numbering::agreement_scope_options();
        $numbering_settings = [
            'agreement' => [],
            'offer' => [],
            'search' => [],
        ];
        foreach ($agreement_scope_options as $agreement_scope => $agreement_scope_label) {
            $numbering_settings['agreement'][(string) $agreement_scope] = EstateOfficeCRM_Numbering::load_entity_settings(
                'agreement',
                function (string $key): string {
                    return $this->get_setting($key);
                },
                (string) $agreement_scope
            );
        }
        $numbering_settings['offer'] = EstateOfficeCRM_Numbering::load_entity_settings(
            'offer',
            function (string $key): string {
                return $this->get_setting($key);
            }
        );
        $numbering_settings['search'] = EstateOfficeCRM_Numbering::load_entity_settings(
            'search',
            function (string $key): string {
                return $this->get_setting($key);
            }
        );

        $offer_template_variant = $this->get_setting('offer_template_variant');
        $pdf_template_variant = $this->get_setting('pdf_template_variant');
        $offer_template_sale_heading = $this->get_setting('offer_template_sale_heading');
        $offer_template_rent_heading = $this->get_setting('offer_template_rent_heading');
        $offer_template_cta_label = $this->get_setting('offer_template_cta_label');
        $offer_template_show_offer_number = $this->get_setting('offer_template_show_offer_number');
        $offer_template_show_offer_number_in_title = $this->get_setting('offer_template_show_offer_number_in_title');
        $offer_template_open_in_new_window = $this->get_setting('offer_template_open_in_new_window');
        $unit_settings = EstateOfficeCRM_Units::load_settings(function (string $key): string {
            return $this->get_setting($key);
        });
        $onboarding_config = $this->load_onboarding_config();
        $onboarding_default_items = $this->onboarding_default_items();
        $compliance_document_definitions = $this->aml_rodo_uodo_document_definitions();
        $compliance_documents = $this->load_compliance_documents();
        $agreement_stage_sets = EstateOfficeCRM_Stages::load_all(function (string $key): string {
            return $this->get_setting($key);
        });
        $portal_export_settings = EstateOfficeCRM_Portal_Export::load_settings(function (string $key): string {
            return $this->get_setting($key);
        });
        $portal_export_noe_settings = EstateOfficeCRM_Portal_Export::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, EstateOfficeCRM_Portal_Export::TARGET_NOE);
        $portal_export_mg_settings = EstateOfficeCRM_Portal_Export::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, EstateOfficeCRM_Portal_Export::TARGET_MORIZON_GRATKA);
        $portal_export_oo_settings = EstateOfficeCRM_Portal_Export::load_settings_for_provider(function (string $key): string {
            return $this->get_setting($key);
        }, EstateOfficeCRM_Portal_Export::TARGET_OTODOM_OLX);

        if ($office_name === '') {
            $office_name = sanitize_text_field((string) get_bloginfo('name'));
        }
        if (! in_array($language, ['pl_PL', 'en_US', 'de_DE', 'uk_UA'], true)) {
            $language = 'en_US';
        }
        if (! in_array($offer_template_variant, ['modern_v1', 'modern_v2', 'modern_v3', 'modern_v4', 'modern_v5', 'modern_v6'], true)) {
            $offer_template_variant = 'modern_v1';
        }
        if (! in_array($pdf_template_variant, ['pdf_v1'], true)) {
            $pdf_template_variant = 'pdf_v1';
        }
        if ($offer_template_sale_heading === '') {
            $offer_template_sale_heading = 'Oferty na Sprzedaz';
        }
        if ($offer_template_rent_heading === '') {
            $offer_template_rent_heading = 'Oferty na Wynajem';
        }
        if ($offer_template_cta_label === '') {
            $offer_template_cta_label = 'Zobacz oferte';
        }
        if ($offer_template_show_offer_number !== '0') {
            $offer_template_show_offer_number = '1';
        }
        if ($offer_template_show_offer_number_in_title !== '0') {
            $offer_template_show_offer_number_in_title = '1';
        }
        if ($offer_template_open_in_new_window !== '1') {
            $offer_template_open_in_new_window = '0';
        }

        $property_additional_fields = EstateOfficeCRM_Property_Custom_Fields::load_definitions(function (string $key): string {
            return $this->get_setting($key);
        });
        $property_additional_section_options = EstateOfficeCRM_Property_Custom_Fields::section_options();
        $property_additional_type_options = EstateOfficeCRM_Property_Custom_Fields::type_options();

        $watermark_url = $watermark_attachment_id > 0 ? wp_get_attachment_image_url($watermark_attachment_id, 'thumbnail') : '';
        $logo_url = $office_logo_attachment_id > 0 ? wp_get_attachment_image_url($office_logo_attachment_id, 'thumbnail') : '';
        $mortgage_advisor_image_url = $mortgage_advisor_attachment_id > 0 ? wp_get_attachment_image_url($mortgage_advisor_attachment_id, 'medium') : '';

        $tab_general_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'general',
            ],
            admin_url('admin.php')
        );
        $tab_onboarding_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'onboarding',
            ],
            admin_url('admin.php')
        );
        $tab_aml_rodo_uodo_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'aml_rodo_uodo',
            ],
            admin_url('admin.php')
        );
        $tab_templates_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'offer_templates',
            ],
            admin_url('admin.php')
        );
        $tab_units_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'units',
            ],
            admin_url('admin.php')
        );
        $tab_mortgage_calculator_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'mortgage_calculator',
            ],
            admin_url('admin.php')
        );
        $tab_property_custom_fields_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'property_custom_fields',
            ],
            admin_url('admin.php')
        );
        $tab_agreement_stages_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'agreement_stages',
            ],
            admin_url('admin.php')
        );
        $tab_numbering_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'numbering',
            ],
            admin_url('admin.php')
        );
        $tab_portal_export_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'portal_export',
            ],
            admin_url('admin.php')
        );
        $tab_import_mls_url = add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'tab' => 'import_mls',
            ],
            admin_url('admin.php')
        );

        echo '<div class="wrap eocrm-admin-wrap eocrm-settings-page">';
        echo '<h1>Estate Office CRM - Ustawienia</h1>';
        $this->render_query_notice();
        echo '<h2 class="nav-tab-wrapper eocrm-settings-tabs">';
        echo '<a href="' . esc_url($tab_general_url) . '" class="nav-tab ' . ($active_tab === 'general' ? 'nav-tab-active' : '') . '">Ogolne</a>';
        echo '<a href="' . esc_url($tab_onboarding_url) . '" class="nav-tab ' . ($active_tab === 'onboarding' ? 'nav-tab-active' : '') . '">Onboarding</a>';
        echo '<a href="' . esc_url($tab_aml_rodo_uodo_url) . '" class="nav-tab ' . ($active_tab === 'aml_rodo_uodo' ? 'nav-tab-active' : '') . '">AML, Rodo, Uodo.</a>';
        echo '<a href="' . esc_url($tab_numbering_url) . '" class="nav-tab ' . ($active_tab === 'numbering' ? 'nav-tab-active' : '') . '">Numeracja Umow</a>';
        echo '<a href="' . esc_url($tab_agreement_stages_url) . '" class="nav-tab ' . ($active_tab === 'agreement_stages' ? 'nav-tab-active' : '') . '">Etapy Umow</a>';
        echo '<a href="' . esc_url($tab_property_custom_fields_url) . '" class="nav-tab ' . ($active_tab === 'property_custom_fields' ? 'nav-tab-active' : '') . '">Dodatkowe pola</a>';
        echo '<a href="' . esc_url($tab_templates_url) . '" class="nav-tab ' . ($active_tab === 'offer_templates' ? 'nav-tab-active' : '') . '">Szablony Ofert</a>';
        echo '<a href="' . esc_url($tab_units_url) . '" class="nav-tab ' . ($active_tab === 'units' ? 'nav-tab-active' : '') . '">Jednostki Miary</a>';
        echo '<a href="' . esc_url($tab_mortgage_calculator_url) . '" class="nav-tab ' . ($active_tab === 'mortgage_calculator' ? 'nav-tab-active' : '') . '">Kalkulator kredytowy</a>';
        echo '<a href="' . esc_url($tab_portal_export_url) . '" class="nav-tab ' . ($active_tab === 'portal_export' ? 'nav-tab-active' : '') . '">Esport na portale</a>';
        if ($developer_access_enabled) {
            echo '<a href="' . esc_url($tab_import_mls_url) . '" class="nav-tab ' . ($active_tab === 'import_mls' ? 'nav-tab-active' : '') . '">Import MLS</a>';
        }
        echo '</h2>';
        echo '<form class="eocrm-settings-form" method="post" action="' . esc_url(admin_url('admin.php?page=' . self::SETTINGS_SLUG)) . '">';
        wp_nonce_field('eocrm_save_settings');
        echo '<input type="hidden" name="eocrm_action" value="save_settings">';
        echo '<input type="hidden" name="eocrm_settings_tab" value="' . esc_attr($active_tab) . '">';

        if ($active_tab === 'general') {
            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Integracje i branding</h2>';
            echo '<p><label>Nazwa biura<br><input class="regular-text" type="text" name="office_name" value="' . esc_attr($office_name) . '"></label></p>';
            echo '<p><label>Jezyk<br>';
            echo '<select name="language">';
            echo '<option value="pl_PL" ' . selected($language, 'pl_PL', false) . '>Polski (pl_PL)</option>';
            echo '<option value="en_US" ' . selected($language, 'en_US', false) . '>English (en_US)</option>';
            echo '<option value="de_DE" ' . selected($language, 'de_DE', false) . '>Deutsch (de_DE)</option>';
            echo '<option value="uk_UA" ' . selected($language, 'uk_UA', false) . '>Ukrainska (uk_UA)</option>';
            echo '</select></label></p>';
            echo '<p><label>Tryb szerokosci stron CRM i ofert<br>';
            echo '<select name="frontend_width_mode">';
            echo '<option value="plugin" ' . selected($frontend_width_mode, 'plugin', false) . '>Wzgledem kontenera wtyczki</option>';
            echo '<option value="screen" ' . selected($frontend_width_mode, 'screen', false) . '>Wzgledem szerokosci monitora</option>';
            echo '</select></label></p>';
            echo '<p><label>Szerokosc (%)<br>';
            echo '<input class="small-text" type="number" min="60" max="100" step="1" name="frontend_width_percent" value="' . esc_attr((string) $frontend_width_percent) . '"> %';
            echo '</label></p>';
            echo '<p class="description">Zakres 60-100%. Ustawienie dziala dla CRM, list ofert, strony oferty oraz Biura i Agenci.</p>';
            echo '<p><label>Liczba ofert na listach ofert (2-20, tylko parzyste)<br>';
            echo '<select name="public_offers_per_page">';
            foreach ($public_offers_per_page_options as $per_page_option) {
                echo '<option value="' . esc_attr((string) $per_page_option) . '"' . selected($public_offers_per_page, $per_page_option, false) . '>' . esc_html((string) $per_page_option) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p class="description">Ustawienie dotyczy stron `Oferty na Sprzedaz` i `Oferty na Wynajem`.</p>';
            echo '<p><label>Czas wyświetlania znacznika Nowa oferta<br>';
            echo '<input class="small-text" type="number" min="1" max="365" step="1" name="new_offer_duration_days" value="' . esc_attr((string) $new_offer_duration_days) . '"> dni';
            echo '</label></p>';
            echo '<p class="description">Po tym czasie znacznik Nowa oferta zostanie automatycznie wyłączony podczas codziennego czyszczenia CRM.</p>';
            echo '<h3>Retencja eksportu dla sprzedanych/wynajetych</h3>';
            echo '<p><label>Eksport WWW - liczba dni po oznaczeniu Sprzedane/Wynajete<br>';
            echo '<input class="small-text" type="number" min="0" max="365" step="1" name="sold_rented_www_retention_days" value="' . esc_attr((string) $sold_rented_www_retention_days) . '"> dni';
            echo '</label></p>';
            echo '<p><label>Eksport na Portale - liczba dni po oznaczeniu Sprzedane/Wynajete<br>';
            echo '<input class="small-text" type="number" min="0" max="365" step="1" name="sold_rented_portals_retention_days" value="' . esc_attr((string) $sold_rented_portals_retention_days) . '"> dni';
            echo '</label></p>';
            echo '<p class="description">`0` oznacza natychmiastowe wylaczenie eksportu po oznaczeniu nieruchomosci jako Sprzedane/Wynajete.</p>';
            echo '<p><label>Google Maps API Key<br><input class="large-text" type="text" name="google_maps_api_key" value="' . esc_attr($google_maps_api_key) . '"></label></p>';

            // --- Styl CRM (frontend) ---
            $crm_style_value = $this->get_setting('crm_style');
            if (! in_array($crm_style_value, ['default', 'minimal'], true)) {
                $crm_style_value = 'minimal';
            }
            echo '<div class="eocrm-crm-style-field">';
            echo '<h4 style="margin:18px 0 6px;">Styl CRM (frontend)</h4>';
            echo '<p class="description" style="margin:0 0 10px;">Wyb&oacute;r aktywnego stylu wizualnego frontu CRM. Domy&#347;lnie aktywny jest styl <strong>Minimalistyczny</strong>.</p>';
            echo '<div class="eocrm-crm-style-grid">';

            $styles = [
                'minimal' => [
                    'label' => 'Minimalistyczny (domy&#347;lny)',
                    'desc' => 'Schludny, oszcz&#281;dny styl &mdash; pastelowe akcenty, p&#322;askie kafelki, monochromatyczne ikony SVG, idealny do skupionej pracy.',
                ],
                'default' => [
                    'label' => 'Modern',
                    'desc' => 'Pe&#322;ny styl z gradientami, kolorowymi paskami akcentu, du&#380;ymi avatarami i bogat&#261; typografi&#261;.',
                ],
            ];

            foreach ($styles as $style_key => $style_meta) {
                $is_checked = $crm_style_value === $style_key;
                echo '<label class="eocrm-crm-style-option' . ($is_checked ? ' is-active' : '') . '">';
                echo '<input type="radio" name="crm_style" value="' . esc_attr($style_key) . '" ' . checked($is_checked, true, false) . '>';
                echo '<span class="eocrm-crm-style-option__preview eocrm-crm-style-option__preview--' . esc_attr($style_key) . '" aria-hidden="true"></span>';
                echo '<span class="eocrm-crm-style-option__label">' . wp_kses_post((string) $style_meta['label']) . '</span>';
                echo '<span class="eocrm-crm-style-option__desc">' . wp_kses_post((string) $style_meta['desc']) . '</span>';
                echo '</label>';
            }

            echo '</div>';
            echo '</div>';

            echo '<div class="eocrm-media-field">';
            echo '<p><label>Znak wodny (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-watermark-id" name="watermark_attachment_id" value="' . esc_attr((string) $watermark_attachment_id) . '"></label></p>';
            echo '<p><button type="button" class="button" data-eocrm-media="eocrm-watermark-id" data-eocrm-preview="eocrm-watermark-preview">Wybierz znak wodny</button></p>';
            echo '<div id="eocrm-watermark-preview">';
            if (is_string($watermark_url) && $watermark_url !== '') {
                echo '<img src="' . esc_url($watermark_url) . '" alt="Watermark" style="max-width:120px;height:auto;">';
            }
            echo '</div>';
            echo '</div>';

            echo '<div class="eocrm-media-field">';
            echo '<p><label>Logo biura (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-logo-id" name="office_logo_attachment_id" value="' . esc_attr((string) $office_logo_attachment_id) . '"></label></p>';
            echo '<p><button type="button" class="button" data-eocrm-media="eocrm-logo-id" data-eocrm-preview="eocrm-logo-preview">Wybierz logo</button></p>';
            echo '<div id="eocrm-logo-preview">';
            if (is_string($logo_url) && $logo_url !== '') {
                echo '<img src="' . esc_url($logo_url) . '" alt="Logo" style="max-width:120px;height:auto;">';
            }
            echo '</div>';
            echo '</div>';

            echo '<div class="eocrm-dev-access-card" data-eocrm-dev-access>';
            echo '<div class="eocrm-dev-access-head">';
            echo '<div>';
            echo '<h3>Dostep programisty</h3>';
            echo '</div>';
            echo '<label class="eocrm-dev-access-toggle ' . ($developer_access_enabled ? 'is-active' : '') . '">';
            echo '<input type="checkbox" name="developer_access_enabled" value="1" data-eocrm-dev-access-toggle ' . checked($developer_access_enabled, true, false) . '>';
            echo '<span>' . ($developer_access_enabled ? 'Aktywny' : 'Wlacz dostep') . '</span>';
            echo '</label>';
            echo '</div>';
            echo '<div class="eocrm-dev-access-password ' . ($developer_access_enabled ? '' : 'is-hidden') . '" data-eocrm-dev-access-password>';
            echo '<p><label>Haslo programisty<br><input class="regular-text" type="password" name="developer_access_password" value="" autocomplete="new-password" placeholder="' . ($developer_access_enabled ? 'Zostaw puste, aby utrzymac dostep' : 'Wpisz haslo programisty') . '"></label></p>';
            echo '<p class="description">Haslo jest sprawdzane tylko po stronie serwera i nie jest publikowane w panelu ani w JavaScript.</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        if ($active_tab === 'onboarding') {
            echo '<div class="eocrm-admin-panel eocrm-onboarding-panel">';
            echo '<h2>Onboarding Agentów i Menedżerów</h2>';
            echo '<p>Wybierz elementy, które w kolejnym etapie będą wysyłane nowemu Agentowi lub Menedżerowi. Na razie konfigurujemy zestaw startowy i własne linki, bez automatycznej wysyłki.</p>';
            echo '<table class="widefat striped eocrm-admin-table eocrm-onboarding-table">';
            echo '<thead><tr><th>Element onboardingu</th><th>Agent</th><th>Menedżer</th></tr></thead>';
            echo '<tbody>';
            foreach ($onboarding_default_items as $item_key => $item_label) {
                $item_settings = isset($onboarding_config['items'][$item_key]) && is_array($onboarding_config['items'][$item_key])
                    ? $onboarding_config['items'][$item_key]
                    : ['agent' => true, 'manager' => true];

                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $item_label) . '</strong></td>';
                echo '<td>';
                $this->render_onboarding_toggle('onboarding_items[' . (string) $item_key . '][agent]', ! empty($item_settings['agent']));
                echo '</td>';
                echo '<td>';
                $this->render_onboarding_toggle('onboarding_items[' . (string) $item_key . '][manager]', ! empty($item_settings['manager']));
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            $custom_links = isset($onboarding_config['custom_links']) && is_array($onboarding_config['custom_links'])
                ? $onboarding_config['custom_links']
                : [];

            echo '<div class="eocrm-admin-panel eocrm-onboarding-links-panel" data-eocrm-onboarding-links>';
            echo '<h2>Własne linki</h2>';
            echo '<p>Dodaj dodatkowe materiały, strony lub instrukcje, które później będzie można dołączyć do onboardingu.</p>';
            echo '<table class="widefat striped eocrm-admin-table eocrm-onboarding-links-table">';
            echo '<thead><tr><th>Nazwa linku</th><th>Adres URL</th><th>Agent</th><th>Menedżer</th><th>Akcje</th></tr></thead>';
            echo '<tbody data-eocrm-onboarding-link-rows>';

            $onboarding_link_index = 0;
            foreach ($custom_links as $custom_link) {
                if (! is_array($custom_link)) {
                    continue;
                }
                $this->render_onboarding_custom_link_row($onboarding_link_index, $custom_link);
                $onboarding_link_index++;
            }

            $this->render_onboarding_custom_link_row($onboarding_link_index, [
                'label' => '',
                'url' => '',
                'agent' => true,
                'manager' => true,
            ]);
            $onboarding_link_index++;

            echo '</tbody>';
            echo '</table>';
            echo '<p><button type="button" class="button" data-eocrm-onboarding-add-link>Dodaj link</button></p>';
            echo '<template data-eocrm-onboarding-link-template>';
            ob_start();
            $this->render_onboarding_custom_link_row('__INDEX__', [
                'label' => '',
                'url' => '',
                'agent' => true,
                'manager' => true,
            ]);
            echo (string) ob_get_clean();
            echo '</template>';
            echo '</div>';

            $onboarding_recipients = $this->get_onboarding_recipients();
            echo '<div class="eocrm-admin-panel eocrm-onboarding-send-panel">';
            echo '<h2>Wyślij onboarding ręcznie</h2>';
            echo '<p>Wyślij aktualny zestaw linków onboardingowych wybranemu Agentowi lub Menedżerowi. Ustawienia z tej strony zostaną zapisane przed wysyłką.</p>';
            echo '<div class="eocrm-onboarding-send-row">';
            echo '<label>Odbiorca<br>';
            echo '<select name="eocrm_onboarding_recipient_user_id">';
            echo '<option value="0">Wybierz odbiorcę</option>';
            foreach ($onboarding_recipients as $recipient) {
                $recipient_id = (int) ($recipient['id'] ?? 0);
                if ($recipient_id <= 0) {
                    continue;
                }
                $recipient_label = trim((string) ($recipient['display_name'] ?? ''));
                $recipient_email = trim((string) ($recipient['user_email'] ?? ''));
                $recipient_role = trim((string) ($recipient['role_label'] ?? 'Agent'));
                if ($recipient_label === '') {
                    $recipient_label = $recipient_email !== '' ? $recipient_email : ('Użytkownik #' . $recipient_id);
                }
                $option_label = $recipient_label . ' - ' . $recipient_role;
                if ($recipient_email !== '') {
                    $option_label .= ' (' . $recipient_email . ')';
                }
                echo '<option value="' . esc_attr((string) $recipient_id) . '">' . esc_html($option_label) . '</option>';
            }
            echo '</select></label>';
            echo '<button type="submit" name="eocrm_onboarding_send_now" value="1" class="button button-primary">Wyślij e-mail onboardingowy</button>';
            echo '</div>';
            if (empty($onboarding_recipients)) {
                echo '<p class="description">Brak Agentów lub Menedżerów do wyboru.</p>';
            } else {
                echo '<p class="description">E-mail będzie zawierał tylko te linki, które są aktywne dla roli wybranego odbiorcy.</p>';
            }
            echo '</div>';
        }

        if ($active_tab === 'aml_rodo_uodo') {
            echo '<div class="eocrm-admin-panel eocrm-compliance-hero">';
            echo '<h2>AML, Rodo, Uodo.</h2>';
            echo '<p>Repozytorium dokumentow zgodnosci dla biura nieruchomosci i posrednikow. Lista zostala przygotowana jako praktyczna checklista na podstawie aktualnych przepisow i komunikatow organow publicznych; przed wdrozeniem dokumenty powinien zweryfikowac prawnik lub inspektor ochrony danych.</p>';
            echo '<p class="description">Status prawny sprawdzony: 2026-05-14. Uklad obejmuje AML/CFT, RODO oraz dokumenty operacyjne zwiazane z Prezesem UODO i ustawa o ochronie danych osobowych.</p>';
            echo '<div class="eocrm-compliance-sources">';
            echo '<a href="https://api.sejm.gov.pl/eli/acts/DU/2025/644/text.pdf" target="_blank" rel="noopener">Ustawa AML - Dz.U. 2025 poz. 644</a>';
            echo '<a href="https://www.gov.pl/web/finanse/przeciwdzialanie-praniu-pieniedzy-i-finansowaniu-terroryzmu" target="_blank" rel="noopener">GIIF / Ministerstwo Finansow</a>';
            echo '<a href="https://www.gov.pl/web/finanse/komunikat-nr-36-w-sprawie-oceny-ryzyka-instytucji-obowiazanej" target="_blank" rel="noopener">GIIF - ocena ryzyka</a>';
            echo '<a href="https://uodo.gov.pl/pl/676/4244" target="_blank" rel="noopener">UODO - rejestry czynnosci</a>';
            echo '<a href="https://bip.uodo.gov.pl/pl/525" target="_blank" rel="noopener">UODO - zglaszanie naruszen</a>';
            echo '</div>';
            echo '</div>';

            $current_category = '';
            foreach ($compliance_document_definitions as $definition) {
                $definition_key = sanitize_key((string) ($definition['key'] ?? ''));
                if ($definition_key === '') {
                    continue;
                }

                $category = (string) ($definition['category'] ?? '');
                $category_label = (string) ($definition['category_label'] ?? $category);
                if ($category !== $current_category) {
                    if ($current_category !== '') {
                        echo '</tbody></table></div>';
                    }

                    $current_category = $category;
                    echo '<div class="eocrm-admin-panel eocrm-compliance-section">';
                    echo '<h3>' . wp_kses_post($category_label) . '</h3>';
                    echo '<table class="widefat striped eocrm-admin-table eocrm-compliance-table">';
                    echo '<thead><tr><th>Dokument</th><th>Zakres / podstawa</th><th>Plik</th><th>Notatki</th></tr></thead>';
                    echo '<tbody>';
                }

                $stored_row = isset($compliance_documents[$definition_key]) && is_array($compliance_documents[$definition_key])
                    ? $compliance_documents[$definition_key]
                    : [];
                $attachment_id = isset($stored_row['attachment_id']) ? absint((string) $stored_row['attachment_id']) : 0;
                $notes = isset($stored_row['notes']) ? sanitize_textarea_field((string) $stored_row['notes']) : '';
                $updated_at = isset($stored_row['updated_at']) ? sanitize_text_field((string) $stored_row['updated_at']) : '';
                $input_id = 'eocrm-compliance-doc-' . $definition_key;
                $preview_id = 'eocrm-compliance-preview-' . $definition_key;
                $attachment_url = $attachment_id > 0 ? wp_get_attachment_url($attachment_id) : '';
                $attachment_title = $attachment_id > 0 ? get_the_title($attachment_id) : '';
                if (! is_string($attachment_url)) {
                    $attachment_url = '';
                }
                if (! is_string($attachment_title) || $attachment_title === '') {
                    $attachment_title = $attachment_id > 0 ? ('Dokument #' . (string) $attachment_id) : '';
                }

                echo '<tr class="eocrm-compliance-row">';
                echo '<td>';
                echo '<strong>' . wp_kses_post((string) ($definition['title'] ?? 'Dokument')) . '</strong>';
                if (! empty($definition['badge'])) {
                    echo '<span class="eocrm-compliance-badge">' . wp_kses_post((string) $definition['badge']) . '</span>';
                }
                echo '</td>';
                echo '<td>';
                echo '<p>' . wp_kses_post((string) ($definition['description'] ?? '')) . '</p>';
                if (! empty($definition['basis'])) {
                    echo '<p class="description">' . wp_kses_post((string) $definition['basis']) . '</p>';
                }
                echo '</td>';
                echo '<td>';
                echo '<input type="hidden" id="' . esc_attr($input_id) . '" name="compliance_documents[' . esc_attr($definition_key) . '][attachment_id]" value="' . esc_attr((string) $attachment_id) . '">';
                echo '<p class="eocrm-compliance-actions">';
                echo '<button type="button" class="button" data-eocrm-media="' . esc_attr($input_id) . '" data-eocrm-preview="' . esc_attr($preview_id) . '" data-eocrm-media-type="document">Wgraj / wybierz</button>';
                echo '<button type="button" class="button button-link-delete" data-eocrm-clear-media="' . esc_attr($input_id) . '" data-eocrm-preview="' . esc_attr($preview_id) . '">Usun plik</button>';
                echo '</p>';
                echo '<div id="' . esc_attr($preview_id) . '" class="eocrm-compliance-file-preview">';
                if ($attachment_url !== '') {
                    echo '<a href="' . esc_url($attachment_url) . '" target="_blank" rel="noopener">' . esc_html($attachment_title) . '</a>';
                } else {
                    echo '<span>Brak pliku</span>';
                }
                echo '</div>';
                if ($updated_at !== '') {
                    echo '<p class="description">Ostatni zapis: ' . esc_html($updated_at) . '</p>';
                }
                echo '</td>';
                echo '<td><textarea rows="3" name="compliance_documents[' . esc_attr($definition_key) . '][notes]" placeholder="Np. wersja dokumentu, osoba odpowiedzialna, termin przegladu">' . esc_textarea($notes) . '</textarea></td>';
                echo '</tr>';
            }

            if ($current_category !== '') {
                echo '</tbody></table></div>';
            }
        }

        if ($active_tab === 'import_mls') {
            echo '<div class="eocrm-admin-panel eocrm-dev-only-panel">';
            echo '<h2>Import MLS</h2>';
            echo '<p>Zakladka techniczna przygotowana pod przyszly import ofert MLS. Widoczna jest tylko po wlaczeniu dostepu programisty.</p>';
            echo '<p class="description">Na tym etapie nie zapisujemy jeszcze danych importu. To bezpieczny placeholder pod dalsza integracje, bez wplywu na obecne formularze CRM.</p>';
            echo '</div>';
        }

        if ($active_tab === 'property_custom_fields') {
            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Dodatkowe pola (Nieruchomosci)</h2>';
            echo '<p>To miejsce sluzy tylko do konfiguracji dodatkowych pol dla <strong>Nieruchomosci</strong>. Kazde pole mozesz przypisac do sekcji formularza oraz zdecydowac, czy ma byc widoczne na publicznej stronie oferty.</p>';
            echo '<p class="description">Sekcje do wyboru: Szczegoly nieruchomosci, Media, Udogodnienia, Wyposazenie.</p>';
            echo '</div>';

            echo '<div class="eocrm-admin-panel">';
            echo '<div class="eocrm-custom-fields-builder" data-eocrm-custom-fields-builder>';
            echo '<table class="widefat striped eocrm-admin-table eocrm-custom-fields-table">';
            echo '<thead><tr><th>Nazwa pola</th><th>Sekcja</th><th>Typ pola</th><th>Pokaz w ofercie WWW</th><th>Akcje</th></tr></thead>';
            echo '<tbody data-eocrm-custom-fields-rows>';

            $row_index = 0;
            foreach ($property_additional_fields as $field_row) {
                if (! is_array($field_row)) {
                    continue;
                }
                $this->render_property_additional_field_row(
                    $row_index,
                    $field_row,
                    $property_additional_section_options,
                    $property_additional_type_options
                );
                $row_index++;
            }

            if ($row_index === 0) {
                $this->render_property_additional_field_row(
                    0,
                    ['label' => '', 'key' => '', 'section' => 'details', 'type' => 'text', 'show_public' => 0],
                    $property_additional_section_options,
                    $property_additional_type_options
                );
                $row_index = 1;
            }

            echo '</tbody>';
            echo '</table>';
            echo '<p><button type="button" class="button" data-eocrm-custom-field-add>Dodaj pole</button></p>';

            echo '<template data-eocrm-custom-field-template>';
            ob_start();
            $this->render_property_additional_field_row(
                '__INDEX__',
                ['label' => '', 'key' => '', 'section' => 'details', 'type' => 'text', 'show_public' => 0],
                $property_additional_section_options,
                $property_additional_type_options
            );
            $template_row_html = (string) ob_get_clean();
            echo $template_row_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</template>';

            echo '</div>';
            echo '</div>';
        }

        if ($active_tab === 'numbering') {
            $render_numbering_fields = function (string $name_prefix, array $entity_settings) use ($date_format_options): void {
                $prefix = (string) ($entity_settings['prefix'] ?? '');
                $parts_count = EstateOfficeCRM_Numbering::sanitize_parts_count((string) ($entity_settings['parts_count'] ?? '1'));
                $separator = EstateOfficeCRM_Numbering::sanitize_separator((string) ($entity_settings['separator'] ?? '/'));
                $parts = isset($entity_settings['parts']) && is_array($entity_settings['parts']) ? $entity_settings['parts'] : [];

                echo '<div class="eocrm-admin-grid">';
                echo '<p><label>Prefiks<br><input class="regular-text" type="text" name="' . esc_attr($name_prefix) . '_prefix" value="' . esc_attr($prefix) . '"></label></p>';
                echo '<p><label>Liczba czlonow (bez prefiksu)<br><select name="' . esc_attr($name_prefix) . '_parts" data-eocrm-numbering-parts>';
                for ($part_count_option = 1; $part_count_option <= 3; $part_count_option++) {
                    echo '<option value="' . (string) $part_count_option . '"' . selected($parts_count, $part_count_option, false) . '>' . (string) $part_count_option . '</option>';
                }
                echo '</select></label></p>';
                echo '<p><label>Separator<br><select name="' . esc_attr($name_prefix) . '_separator">';
                echo '<option value="/" ' . selected($separator, '/', false) . '>/</option>';
                echo '<option value="-" ' . selected($separator, '-', false) . '>-</option>';
                echo '<option value="." ' . selected($separator, '.', false) . '>.</option>';
                echo '</select></label></p>';
                echo '</div>';

                for ($part_index = 1; $part_index <= 3; $part_index++) {
                    $part = isset($parts[$part_index]) && is_array($parts[$part_index]) ? $parts[$part_index] : [];
                    $part_type = EstateOfficeCRM_Numbering::sanitize_part_type((string) ($part['type'] ?? ''), $part_index);
                    $part_date_format = EstateOfficeCRM_Numbering::sanitize_date_format((string) ($part['date_format'] ?? 'Ymd'));
                    $part_counter_next = EstateOfficeCRM_Numbering::sanitize_counter_next((string) ($part['counter_next'] ?? '0001'));
                    $part_counter_step = EstateOfficeCRM_Numbering::sanitize_counter_step((string) ($part['counter_step'] ?? '1'));

                    echo '<div class="eocrm-numbering-part" data-eocrm-numbering-part="' . (string) $part_index . '">';
                    echo '<div class="eocrm-admin-grid">';
                    echo '<p><label>Czlon ' . (string) $part_index . ' - typ<br><select name="' . esc_attr($name_prefix) . '_part' . (string) $part_index . '_type" data-eocrm-numbering-part-type="' . (string) $part_index . '">';
                    echo '<option value="date" ' . selected($part_type, 'date', false) . '>Data</option>';
                    echo '<option value="digits" ' . selected($part_type, 'digits', false) . '>Cyfry</option>';
                    echo '<option value="custom" ' . selected($part_type, 'custom', false) . '>Wlasne</option>';
                    echo '</select></label></p>';

                    echo '<p data-eocrm-numbering-type-block="' . (string) $part_index . ':date"><label>Format daty<br><select name="' . esc_attr($name_prefix) . '_part' . (string) $part_index . '_date_format">';
                    foreach ($date_format_options as $date_format_value => $date_format_label) {
                        echo '<option value="' . esc_attr((string) $date_format_value) . '"' . selected($part_date_format, (string) $date_format_value, false) . '>' . esc_html((string) $date_format_label) . '</option>';
                    }
                    echo '</select></label></p>';

                    echo '<p data-eocrm-numbering-type-block="' . (string) $part_index . ':digits"><label>Poczatek liczenia<br><input class="regular-text" type="text" inputmode="numeric" name="' . esc_attr($name_prefix) . '_part' . (string) $part_index . '_counter_next" value="' . esc_attr($part_counter_next) . '" placeholder="0001"></label></p>';
                    echo '<p data-eocrm-numbering-type-block="' . (string) $part_index . ':digits"><label>Przyrost<br><input class="small-text" type="number" min="1" step="1" name="' . esc_attr($name_prefix) . '_part' . (string) $part_index . '_counter_step" value="' . esc_attr((string) $part_counter_step) . '"></label></p>';
                    echo '<p class="description" data-eocrm-numbering-type-block="' . (string) $part_index . ':custom">Tryb wlasny: pole numeru pozostanie puste przy dodawaniu i trzeba je uzupelnic recznie.</p>';
                    echo '</div>';
                    echo '</div>';
                }
            };

            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Numeracja Umow</h2>';
            echo '<p>Skonfiguruj automatyczne numerowanie dla umow, ofert i poszukiwan. Typ <strong>Wlasne</strong> pozostawia pole numeru puste przy tworzeniu rekordu.</p>';
            echo '</div>';

            echo '<div class="eocrm-numbering-grid">';
            $agreement_settings = isset($numbering_settings['agreement']) && is_array($numbering_settings['agreement']) ? $numbering_settings['agreement'] : [];
            echo '<section class="eocrm-admin-panel eocrm-numbering-box eocrm-numbering-box-agreement">';
            echo '<h3>Umowy</h3>';
            echo '<div class="eocrm-numbering-agreement-tabs" data-eocrm-numbering-agreement-tabs>';
            $agreement_scope_index = 0;
            foreach ($agreement_scope_options as $agreement_scope => $agreement_scope_label) {
                $is_active_scope = $agreement_scope_index === 0;
                echo '<button type="button" class="button ' . ($is_active_scope ? 'button-primary' : '') . '" data-eocrm-numbering-agreement-tab="' . esc_attr((string) $agreement_scope) . '">' . esc_html((string) $agreement_scope_label) . '</button>';
                $agreement_scope_index++;
            }
            echo '</div>';

            $agreement_scope_index = 0;
            foreach ($agreement_scope_options as $agreement_scope => $agreement_scope_label) {
                $scope_settings = isset($agreement_settings[$agreement_scope]) && is_array($agreement_settings[$agreement_scope]) ? $agreement_settings[$agreement_scope] : [];
                $scope_name_prefix = 'numbering_agreement_' . (string) $agreement_scope;
                $is_active_scope = $agreement_scope_index === 0;
                echo '<div class="eocrm-numbering-agreement-panel' . ($is_active_scope ? '' : ' is-hidden') . '" data-eocrm-numbering-agreement-panel="' . esc_attr((string) $agreement_scope) . '">';
                echo '<h4>' . esc_html((string) $agreement_scope_label) . '</h4>';
                echo '<div class="eocrm-numbering-box-inner" data-eocrm-numbering-box="' . esc_attr($scope_name_prefix) . '">';
                $render_numbering_fields($scope_name_prefix, $scope_settings);
                echo '</div>';
                echo '</div>';
                $agreement_scope_index++;
            }
            echo '</section>';

            foreach (['offer' => 'Oferty', 'search' => 'Poszukiwania'] as $numbering_entity => $entity_label) {
                $entity_settings = isset($numbering_settings[$numbering_entity]) && is_array($numbering_settings[$numbering_entity]) ? $numbering_settings[$numbering_entity] : [];
                $name_prefix = 'numbering_' . $numbering_entity;
                echo '<section class="eocrm-admin-panel eocrm-numbering-box" data-eocrm-numbering-box="' . esc_attr($name_prefix) . '">';
                echo '<h3>' . esc_html($entity_label) . '</h3>';
                $render_numbering_fields($name_prefix, $entity_settings);
                echo '</section>';
            }
            echo '</div>';
        }

        if ($active_tab === 'offer_templates') {
            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Szablony Ofert</h2>';
            echo '<p>Ustawienia bazowych etykiet i naglowkow wykorzystywanych na stronach ofert.</p>';
            echo '<div class="eocrm-template-selector-grid">';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="offer_template_variant" value="modern_v1" ' . checked($offer_template_variant, 'modern_v1', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-modern-v1.png') . '" alt="Podglad szablonu Modern v1">';
            echo '</span>';
            echo '<span class="eocrm-template-title">Modern v1</span>';
            echo '<span class="eocrm-template-description">Uklad premium: duze zdjecia, czytelne metryki i sekcje ofert.</span>';
            echo '</label>';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="offer_template_variant" value="modern_v2" ' . checked($offer_template_variant, 'modern_v2', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-modern-v2.png') . '" alt="Podglad szablonu Modern v2">';
            echo '</span>';
            echo '<span class="eocrm-template-title">Modern v2</span>';
            echo '<span class="eocrm-template-description">Galeria 16:9 na gorze + 3 kolumny: Podstawowe informacje, Agent, Umow wizyte.</span>';
            echo '</label>';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="offer_template_variant" value="modern_v3" ' . checked($offer_template_variant, 'modern_v3', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-modern-v3.png') . '" alt="Podglad szablonu Modern v3">';
            echo '</span>';
            echo '<span class="eocrm-template-title">Modern v3</span>';
            echo '<span class="eocrm-template-description">Galeria i podstawowe informacje jak v1, Agent i Umow wizyte pod informacjami.</span>';
            echo '</label>';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="offer_template_variant" value="modern_v4" ' . checked($offer_template_variant, 'modern_v4', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-modern-v4.png') . '" alt="Podglad szablonu Modern v4">';
            echo '</span>';
            echo '<span class="eocrm-template-title">Modern v4</span>';
            echo '<span class="eocrm-template-description">Hero premium z miniaturami pod sliderem i mocnym panelem podsumowania.</span>';
            echo '</label>';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="offer_template_variant" value="modern_v5" ' . checked($offer_template_variant, 'modern_v5', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-modern-v5.png') . '" alt="Podglad szablonu Modern v5">';
            echo '</span>';
            echo '<span class="eocrm-template-title">Modern v5</span>';
            echo '<span class="eocrm-template-description">Uklad magazynowy z pionowym sterowaniem slidera i stale widocznym panelem bocznym.</span>';
            echo '</label>';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="offer_template_variant" value="modern_v6" ' . checked($offer_template_variant, 'modern_v6', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-modern-v6.png') . '" alt="Podglad szablonu Modern v6">';
            echo '</span>';
            echo '<span class="eocrm-template-title">Modern v6</span>';
            echo '<span class="eocrm-template-description">Nowoczesny split layout z auto-sliderm i paskiem postepu zdjec.</span>';
            echo '</label>';
            echo '</div>';

            echo '<h3>Szablony PDF</h3>';
            echo '<p>Wybierz aktywny wyglad eksportu PDF dla profilu nieruchomosci w CRM.</p>';
            echo '<div class="eocrm-template-selector-grid eocrm-template-selector-grid-pdf">';
            echo '<label class="eocrm-template-card">';
            echo '<input type="radio" name="pdf_template_variant" value="pdf_v1" ' . checked($pdf_template_variant, 'pdf_v1', false) . '>';
            echo '<span class="eocrm-template-preview">';
            echo '<img class="eocrm-template-preview-image" src="' . esc_url(EOCRM_URL . 'assets/images/template-preview-pdf-v1.png') . '" alt="Podglad szablonu PDF v1">';
            echo '</span>';
            echo '<span class="eocrm-template-title">PDF v1</span>';
            echo '<span class="eocrm-template-description">Klasyczny dokument PDF z podstawowymi danymi oferty i podsumowaniem.</span>';
            echo '</label>';
            echo '</div>';

            echo '<p><label>Naglowek listy SPRZEDAZ<br><input class="regular-text" type="text" name="offer_template_sale_heading" value="' . esc_attr($offer_template_sale_heading) . '"></label></p>';
            echo '<p><label>Naglowek listy WYNAJEM<br><input class="regular-text" type="text" name="offer_template_rent_heading" value="' . esc_attr($offer_template_rent_heading) . '"></label></p>';
            echo '<p><label>Tekst przycisku oferty<br><input class="regular-text" type="text" name="offer_template_cta_label" value="' . esc_attr($offer_template_cta_label) . '"></label></p>';
            echo '<p><label><input type="checkbox" name="offer_template_show_offer_number" value="1" ' . checked($offer_template_show_offer_number, '1', false) . '> Pokazuj numer oferty na listach ofert</label></p>';
            echo '<p><label><input type="checkbox" name="offer_template_show_offer_number_in_title" value="1" ' . checked($offer_template_show_offer_number_in_title, '1', false) . '> Pokazuj numer oferty w tytule oferty</label></p>';
            echo '<p><label>Czy otwierac oferte z listy w nowym oknie?</label><br>';
            echo '<label style="margin-right:16px;"><input type="radio" name="offer_template_open_in_new_window" value="1" ' . checked($offer_template_open_in_new_window, '1', false) . '> TAK</label>';
            echo '<label><input type="radio" name="offer_template_open_in_new_window" value="0" ' . checked($offer_template_open_in_new_window, '0', false) . '> NIE</label></p>';
            echo '</div>';
        }

        if ($active_tab === 'units') {
            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Jednostki Miary</h2>';
            echo '<p>Jednostki sa stosowane bezposrednio na listach ofert i kartach oferty (metraz i cena za powierzchnie).</p>';
            echo '<p><label>Powierzchnia lokali i domow<br>';
            echo '<select name="unit_area_local">';
            echo '<option value="m2" ' . selected((string) ($unit_settings['area_local'] ?? 'm2'), 'm2', false) . '>m2</option>';
            echo '<option value="ft2" ' . selected((string) ($unit_settings['area_local'] ?? 'm2'), 'ft2', false) . '>ft2</option>';
            echo '</select></label></p>';
            echo '<p><label>Powierzchnia dzialki<br>';
            echo '<select name="unit_area_land">';
            echo '<option value="m2" ' . selected((string) ($unit_settings['area_land'] ?? 'm2'), 'm2', false) . '>m2</option>';
            echo '<option value="ar" ' . selected((string) ($unit_settings['area_land'] ?? 'm2'), 'ar', false) . '>ar</option>';
            echo '<option value="ha" ' . selected((string) ($unit_settings['area_land'] ?? 'm2'), 'ha', false) . '>ha</option>';
            echo '<option value="ft2" ' . selected((string) ($unit_settings['area_land'] ?? 'm2'), 'ft2', false) . '>ft2</option>';
            echo '</select></label></p>';
            echo '<p class="description">Cena za powierzchnie jest automatycznie przeliczana do wybranej jednostki metrazu.</p>';
            echo '</div>';
        }

        if ($active_tab === 'mortgage_calculator') {
            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Kalkulator kredytowy</h2>';
            echo '<p>Ustawienia bloku kontaktowego doradcy kredytowego wyswietlanego pod kalkulatorem na stronie oferty SPRZEDAZ.</p>';
            echo '<p><label>Czy sekcja "Umów się z naszym doradcą kredytowym" ma wyswietlac sie pod kalkulatorem?</label><br>';
            echo '<label style="margin-right:16px;"><input type="radio" name="mortgage_advisor_enabled" value="1" ' . checked($mortgage_advisor_enabled, '1', false) . '> TAK</label>';
            echo '<label><input type="radio" name="mortgage_advisor_enabled" value="0" ' . checked($mortgage_advisor_enabled, '0', false) . '> NIE</label></p>';
            echo '<p class="description">Po ustawieniu na NIE formularz i grafika doradcy nie będą wyświetlane pod kalkulatorem.</p>';

            echo '<div class="eocrm-media-field">';
            echo '<p><label>Logo / zdjecie doradcy (attachment ID)<br><input class="regular-text" type="number" min="0" id="eocrm-mortgage-advisor-id" name="mortgage_advisor_attachment_id" value="' . esc_attr((string) $mortgage_advisor_attachment_id) . '"></label></p>';
            echo '<p><button type="button" class="button" data-eocrm-media="eocrm-mortgage-advisor-id" data-eocrm-preview="eocrm-mortgage-advisor-preview">Wybierz grafike doradcy</button></p>';
            echo '<div id="eocrm-mortgage-advisor-preview">';
            if (is_string($mortgage_advisor_image_url) && $mortgage_advisor_image_url !== '') {
                echo '<img src="' . esc_url($mortgage_advisor_image_url) . '" alt="Doradca kredytowy" style="max-width:180px;height:auto;">';
            }
            echo '</div>';
            echo '</div>';

            echo '<p><label>Maksymalna wysokosc logo wzgledem wysokosci formularza (%)<br><input class="small-text" type="number" min="30" max="100" step="1" name="mortgage_advisor_logo_size_percent" value="' . esc_attr((string) $mortgage_advisor_logo_size_percent) . '"> %</label></p>';
            echo '<p class="description">30% oznacza male logo, 100% oznacza logo o wysokosci zblizonej do wysokosci formularza.</p>';

            echo '<p><label>Wyrownanie logo (os szerokosci)</label><br>';
            echo '<label style="margin-right:16px;"><input type="radio" name="mortgage_advisor_logo_align_x" value="left" ' . checked($mortgage_advisor_logo_align_x, 'left', false) . '> Do lewej</label>';
            echo '<label style="margin-right:16px;"><input type="radio" name="mortgage_advisor_logo_align_x" value="center" ' . checked($mortgage_advisor_logo_align_x, 'center', false) . '> Wycentrowane</label>';
            echo '<label><input type="radio" name="mortgage_advisor_logo_align_x" value="right" ' . checked($mortgage_advisor_logo_align_x, 'right', false) . '> Do prawej</label></p>';
            echo '<p class="description">W osi wysokosci logo zawsze pozostaje wycentrowane.</p>';

            echo '<p><label>E-mail powiadomien z formularza doradcy<br><input class="regular-text" type="email" name="mortgage_advisor_email" value="' . esc_attr($mortgage_advisor_email) . '" placeholder="np. doradca@twojafirma.pl"></label></p>';
            echo '<p class="description">Na ten adres CRM wysyla zapytania z formularza "Umów się z naszym doradcą kredytowym" razem z numerem oferty i wyliczeniami kalkulatora.</p>';
            echo '</div>';
        }

        if ($active_tab === 'portal_export') {
            $portal_options = $this->portal_options_for_access($developer_access_enabled);
            $portal_provider = (string) ($portal_export_settings['provider'] ?? EstateOfficeCRM_Portal_Export::TARGET_NOE);
            if ($portal_provider === '') {
                $portal_provider = EstateOfficeCRM_Portal_Export::TARGET_NOE;
            }
            if (! array_key_exists($portal_provider, $portal_options)) {
                $portal_provider = EstateOfficeCRM_Portal_Export::TARGET_NOE;
            }

            $noe_enabled = (string) ($portal_export_noe_settings['enabled'] ?? '0') === '1';
            $noe_ftp_host = (string) ($portal_export_noe_settings['ftp_host'] ?? 'ftp.nieruchomosci-online.pl');
            $noe_ftp_port = (int) ($portal_export_noe_settings['ftp_port'] ?? 21);
            $noe_ftp_login = (string) ($portal_export_noe_settings['ftp_login'] ?? '');
            $noe_has_password = (string) ($portal_export_noe_settings['ftp_password'] ?? '') !== '';
            $noe_ftp_path = (string) ($portal_export_noe_settings['ftp_path'] ?? '');
            $noe_export_mode = (string) ($portal_export_noe_settings['export_mode'] ?? 'incremental');
            $noe_region_id = (int) ($portal_export_noe_settings['region_id'] ?? 7);
            $noe_region_name = (string) ($portal_export_noe_settings['region_name'] ?? '');
            $noe_software_name = (string) ($portal_export_noe_settings['software_name'] ?? 'Estate Office CRM');
            $noe_use_ssl = (string) ($portal_export_noe_settings['use_ssl'] ?? '0') === '1';
            $noe_timeout = (int) ($portal_export_noe_settings['ftp_timeout'] ?? 20);
            $noe_default_phone = (string) ($portal_export_noe_settings['default_agent_phone'] ?? '');
            $noe_default_email = (string) ($portal_export_noe_settings['default_agent_email'] ?? '');

            $mg_enabled = (string) ($portal_export_mg_settings['enabled'] ?? '0') === '1';
            $mg_ftp_host = (string) ($portal_export_mg_settings['ftp_host'] ?? '');
            $mg_ftp_port = (int) ($portal_export_mg_settings['ftp_port'] ?? 21);
            $mg_ftp_login = (string) ($portal_export_mg_settings['ftp_login'] ?? '');
            $mg_has_password = (string) ($portal_export_mg_settings['ftp_password'] ?? '') !== '';
            $mg_ftp_path = (string) ($portal_export_mg_settings['ftp_path'] ?? '');
            $mg_export_mode = (string) ($portal_export_mg_settings['export_mode'] ?? 'incremental');
            $mg_region_name = (string) ($portal_export_mg_settings['region_name'] ?? 'mazowieckie');
            $mg_agency_name = (string) ($portal_export_mg_settings['agency_name'] ?? '');
            $mg_information = (string) ($portal_export_mg_settings['information'] ?? 'Eksport ofert z Estate Office CRM');
            $mg_use_ssl = (string) ($portal_export_mg_settings['use_ssl'] ?? '0') === '1';
            $mg_timeout = (int) ($portal_export_mg_settings['ftp_timeout'] ?? 20);
            $mg_default_phone = (string) ($portal_export_mg_settings['default_agent_phone'] ?? '');
            $mg_default_email = (string) ($portal_export_mg_settings['default_agent_email'] ?? '');

            $oo_enabled = (string) ($portal_export_oo_settings['enabled'] ?? '0') === '1';
            $oo_client_id = (string) ($portal_export_oo_settings['client_id'] ?? '');
            $oo_has_client_secret = (string) ($portal_export_oo_settings['client_secret'] ?? '') !== '';
            $oo_api_key = (string) ($portal_export_oo_settings['api_key'] ?? '');
            $oo_partner_urn = (string) ($portal_export_oo_settings['partner_urn'] ?? '');
            $oo_has_notification_secret = (string) ($portal_export_oo_settings['notification_secret'] ?? '') !== '';
            $oo_site_urn = (string) ($portal_export_oo_settings['site_urn'] ?? 'urn:site:otodompl');
            $oo_enable_olx = (string) ($portal_export_oo_settings['enable_olx'] ?? '0') === '1';
            $oo_test_account_mode = (string) ($portal_export_oo_settings['test_account_mode'] ?? '0') === '1';
            $oo_auth_host = (string) ($portal_export_oo_settings['auth_host'] ?? 'https://www.otodom.pl');
            $oo_auth_locale = (string) ($portal_export_oo_settings['auth_locale'] ?? 'pl');
            $oo_auth_state = (string) ($portal_export_oo_settings['auth_state'] ?? '');
            $oo_auth_callback_url = (string) ($portal_export_oo_settings['auth_callback_url'] ?? EstateOfficeCRM_Portal_Export::otodom_auth_callback_url());
            $oo_notification_callback_url = (string) ($portal_export_oo_settings['notification_callback_url'] ?? EstateOfficeCRM_Portal_Export::otodom_notification_callback_url());
            $oo_authorization_url = EstateOfficeCRM_Portal_Export::otodom_authorization_url($portal_export_oo_settings);
            $oo_user_agent = (string) ($portal_export_oo_settings['user_agent'] ?? 'Estate Office CRM');
            $oo_access_token = (string) ($portal_export_oo_settings['access_token'] ?? '');
            $oo_refresh_token = (string) ($portal_export_oo_settings['refresh_token'] ?? '');
            $oo_token_expires_at = (string) ($portal_export_oo_settings['token_expires_at'] ?? '');
            $oo_scope = (string) ($portal_export_oo_settings['scope'] ?? '');
            $oo_authorized_at = (string) ($portal_export_oo_settings['authorized_at'] ?? '');
            $oo_last_auth_message = (string) ($portal_export_oo_settings['last_auth_message'] ?? '');
            $oo_last_webhook_at = (string) ($portal_export_oo_settings['last_webhook_at'] ?? '');
            $oo_last_webhook_event = (string) ($portal_export_oo_settings['last_webhook_event'] ?? '');
            $oo_last_webhook_message = (string) ($portal_export_oo_settings['last_webhook_message'] ?? '');
            $oo_has_access = $oo_access_token !== '';
            $oo_has_refresh = $oo_refresh_token !== '';
            $oo_missing_credentials = EstateOfficeCRM_Portal_Export::get_missing_otodom_credentials($portal_export_oo_settings);
            $oo_can_authorize = empty($oo_missing_credentials) && $oo_authorization_url !== '';

            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Esport na portale</h2>';
            echo '<p>Konfiguracja eksportu ofert do zewnetrznych portali. Dostepne integracje: <strong>Nieruchomosci Online (NOE 2.0)</strong>, <strong>Morizon-Gratka (format OFERTY.NET)</strong>' . ($developer_access_enabled ? ' oraz <strong>OtoDom + OLX.pl (OLX Group API)</strong>' : '') . '.</p>';
            if (! $developer_access_enabled) {
                echo '<p class="description">Eksport OtoDom/OLX jest ukryty do czasu wlaczenia dostepu programisty w zakladce Ogolne.</p>';
            }
            echo '</div>';

            echo '<div class="eocrm-admin-panel eocrm-portal-export-layout">';
            echo '<aside class="eocrm-portal-export-sidebar">';
            echo '<h3>Portal</h3>';
            echo '<p><label>Wybierz portal<br>';
            echo '<select name="portal_export_provider" data-eocrm-portal-provider-select>';
            foreach ($portal_options as $provider_key => $provider_label) {
                echo '<option value="' . esc_attr((string) $provider_key) . '"' . selected($portal_provider, (string) $provider_key, false) . '>' . esc_html((string) $provider_label) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p class="description">Zaznaczenie pola <strong>Eksport na Portale</strong> przy nieruchomosci kolejuje wysylke do aktualnie wybranego portalu.</p>';
            echo '</aside>';

            echo '<section class="eocrm-portal-export-content">';
            echo '<section class="eocrm-portal-provider-panel" data-eocrm-portal-provider-panel="' . esc_attr(EstateOfficeCRM_Portal_Export::TARGET_NOE) . '">';
            echo '<h3>Nieruchomosci Online</h3>';
            echo '<p><label><input type="checkbox" name="portal_noe_enabled" value="1" ' . checked($noe_enabled, true, false) . '> Aktywuj eksport na Nieruchomosci Online</label></p>';
            echo '<div class="eocrm-admin-grid">';
            echo '<p><label>FTP host<br><input class="regular-text" type="text" name="portal_noe_ftp_host" value="' . esc_attr($noe_ftp_host) . '"></label></p>';
            echo '<p><label>FTP port<br><input class="small-text" type="number" min="1" max="65535" name="portal_noe_ftp_port" value="' . esc_attr((string) $noe_ftp_port) . '"></label></p>';
            echo '<p><label>FTP login<br><input class="regular-text" type="text" name="portal_noe_ftp_login" value="' . esc_attr($noe_ftp_login) . '"></label></p>';
            echo '<p><label>FTP haslo<br><input class="regular-text" type="password" name="portal_noe_ftp_password" value="" autocomplete="new-password" placeholder="' . ($noe_has_password ? '******' : '') . '"></label><br><span class="description">Zostaw puste, aby nie zmieniac zapisanego hasla.</span></p>';
            echo '<p><label>Katalog na FTP<br><input class="regular-text" type="text" name="portal_noe_ftp_path" value="' . esc_attr($noe_ftp_path) . '" placeholder="np. import/noe"></label></p>';
            echo '<p><label>Timeout FTP (sekundy)<br><input class="small-text" type="number" min="5" max="120" name="portal_noe_ftp_timeout" value="' . esc_attr((string) $noe_timeout) . '"></label></p>';
            echo '<p><label>Tryb eksportu XML<br><select name="portal_noe_export_mode">';
            echo '<option value="incremental" ' . selected($noe_export_mode, 'incremental', false) . '>incremental</option>';
            echo '<option value="full" ' . selected($noe_export_mode, 'full', false) . '>full</option>';
            echo '</select></label></p>';
            echo '<p><label>Wojewodztwo (idRegion 1-16)<br><input class="small-text" type="number" min="1" max="16" name="portal_noe_region_id" value="' . esc_attr((string) $noe_region_id) . '"></label></p>';
            echo '<p><label>Wojewodztwo (nazwa)<br><input class="regular-text" type="text" name="portal_noe_region_name" value="' . esc_attr($noe_region_name) . '" placeholder="np. mazowieckie"></label></p>';
            echo '<p><label>Nazwa oprogramowania<br><input class="regular-text" type="text" name="portal_noe_software_name" value="' . esc_attr($noe_software_name) . '"></label></p>';
            echo '<p><label><input type="checkbox" name="portal_noe_use_ssl" value="1" ' . checked($noe_use_ssl, true, false) . '> Uzywaj ftp_ssl_connect (jezeli serwer obsluguje FTPS)</label></p>';
            echo '<p><label>Domyslny telefon agenta (fallback)<br><input class="regular-text" type="text" name="portal_noe_default_agent_phone" value="' . esc_attr($noe_default_phone) . '"></label></p>';
            echo '<p><label>Domyslny e-mail agenta (fallback)<br><input class="regular-text" type="email" name="portal_noe_default_agent_email" value="' . esc_attr($noe_default_email) . '"></label></p>';
            echo '</div>';
            echo '<p class="description">Po zaznaczeniu opcji <strong>Eksport na Portale</strong> przy nieruchomosci, CRM automatycznie wysyla XML do Nieruchomosci Online.</p>';
            echo '</section>';

            echo '<section class="eocrm-portal-provider-panel" data-eocrm-portal-provider-panel="' . esc_attr(EstateOfficeCRM_Portal_Export::TARGET_MORIZON_GRATKA) . '">';
            echo '<h3>Morizon-Gratka (domy.pl / OFERTY.NET)</h3>';
            echo '<p><label><input type="checkbox" name="portal_mg_enabled" value="1" ' . checked($mg_enabled, true, false) . '> Aktywuj eksport na Morizon-Gratka</label></p>';
            echo '<div class="eocrm-admin-grid">';
            echo '<p><label>FTP host<br><input class="regular-text" type="text" name="portal_mg_ftp_host" value="' . esc_attr($mg_ftp_host) . '" placeholder="np. ftp.domy.pl"></label></p>';
            echo '<p><label>FTP port<br><input class="small-text" type="number" min="1" max="65535" name="portal_mg_ftp_port" value="' . esc_attr((string) $mg_ftp_port) . '"></label></p>';
            echo '<p><label>FTP login<br><input class="regular-text" type="text" name="portal_mg_ftp_login" value="' . esc_attr($mg_ftp_login) . '"></label></p>';
            echo '<p><label>FTP haslo<br><input class="regular-text" type="password" name="portal_mg_ftp_password" value="" autocomplete="new-password" placeholder="' . ($mg_has_password ? '******' : '') . '"></label><br><span class="description">Zostaw puste, aby nie zmieniac zapisanego hasla.</span></p>';
            echo '<p><label>Katalog na FTP<br><input class="regular-text" type="text" name="portal_mg_ftp_path" value="' . esc_attr($mg_ftp_path) . '" placeholder="np. import"></label></p>';
            echo '<p><label>Timeout FTP (sekundy)<br><input class="small-text" type="number" min="5" max="120" name="portal_mg_ftp_timeout" value="' . esc_attr((string) $mg_timeout) . '"></label></p>';
            echo '<p><label>Tryb eksportu pliku<br><select name="portal_mg_export_mode">';
            echo '<option value="incremental" ' . selected($mg_export_mode, 'incremental', false) . '>roznica</option>';
            echo '<option value="full" ' . selected($mg_export_mode, 'full', false) . '>calosc</option>';
            echo '</select></label></p>';
            echo '<p><label>Wojewodztwo (nazwa)<br><input class="regular-text" type="text" name="portal_mg_region_name" value="' . esc_attr($mg_region_name) . '" placeholder="np. mazowieckie"></label></p>';
            echo '<p><label>Nazwa agencji<br><input class="regular-text" type="text" name="portal_mg_agency_name" value="' . esc_attr($mg_agency_name) . '"></label></p>';
            echo '<p><label>Informacje w naglowku pliku<br><input class="regular-text" type="text" name="portal_mg_information" value="' . esc_attr($mg_information) . '" placeholder="Eksport ofert z Estate Office CRM"></label></p>';
            echo '<p><label><input type="checkbox" name="portal_mg_use_ssl" value="1" ' . checked($mg_use_ssl, true, false) . '> Uzywaj ftp_ssl_connect (jezeli serwer obsluguje FTPS)</label></p>';
            echo '<p><label>Domyslny telefon agenta (fallback)<br><input class="regular-text" type="text" name="portal_mg_default_agent_phone" value="' . esc_attr($mg_default_phone) . '"></label></p>';
            echo '<p><label>Domyslny e-mail agenta (fallback)<br><input class="regular-text" type="email" name="portal_mg_default_agent_email" value="' . esc_attr($mg_default_email) . '"></label></p>';
            echo '</div>';
            echo '<p class="description">CRM tworzy paczke <code>oferty_YYYYmmDDHHiiSS.zip</code> z plikiem <code>oferty.xml</code> oraz zdjeciami i wysyla ja na FTP portalu.</p>';
            echo '</section>';

            if ($developer_access_enabled) {
            echo '<section class="eocrm-portal-provider-panel" data-eocrm-portal-provider-panel="' . esc_attr(EstateOfficeCRM_Portal_Export::TARGET_OTODOM_OLX) . '">';
            echo '<h3>OtoDom + OLX.pl (OLX Group API)</h3>';
            echo '<p><label><input type="checkbox" name="portal_oo_enabled" value="1" ' . checked($oo_enabled, true, false) . '> Aktywuj eksport przez OLX Group API</label></p>';
            echo '<div class="eocrm-admin-grid">';
            echo '<p><label>Client ID<br><input class="regular-text" type="text" name="portal_oo_client_id" value="' . esc_attr($oo_client_id) . '"></label></p>';
            echo '<p><label>Client Secret<br><input class="regular-text" type="password" name="portal_oo_client_secret" value="" autocomplete="new-password" placeholder="' . ($oo_has_client_secret ? '******' : '') . '"></label><br><span class="description">Zostaw puste, aby nie zmieniac zapisanego Client Secret.</span></p>';
            echo '<p><label>API Key<br><input class="regular-text" type="text" name="portal_oo_api_key" value="' . esc_attr($oo_api_key) . '"></label></p>';
            echo '<p><label>Partner URN (z panelu aplikacji)<br><input class="regular-text" type="text" name="portal_oo_partner_urn" value="' . esc_attr($oo_partner_urn) . '" placeholder="urn:partner:estate-office-crm"></label><br><span class="description">To pole moze przyjmowac wartosc w rodzaju <code>urn:partner:...</code>. Jest to inny identyfikator niz portalowe <code>Site URN</code>.</span></p>';
            echo '<p><label>Notification Secret<br><input class="regular-text" type="password" name="portal_oo_notification_secret" value="" autocomplete="new-password" placeholder="' . ($oo_has_notification_secret ? '******' : '') . '"></label><br><span class="description">Uzywany do walidacji podpisu <code>x-signature</code> webhooka.</span></p>';
            echo '<p><label>Authorization host<br><input class="regular-text" type="url" name="portal_oo_auth_host" value="' . esc_attr($oo_auth_host) . '" placeholder="https://www.otodom.pl"></label></p>';
            echo '<p><label>Authorization locale (2 litery)<br><input class="small-text" type="text" name="portal_oo_auth_locale" value="' . esc_attr($oo_auth_locale) . '" maxlength="2" placeholder="pl"></label></p>';
            echo '<p><label>Site URN (Polska / OtoDom)<br><input class="regular-text" type="text" name="portal_oo_site_urn" value="' . esc_attr($oo_site_urn) . '" placeholder="urn:site:otodompl"></label><br><span class="description">Dla integracji w Polsce zostaw zwykle <code>urn:site:otodompl</code>. To nie jest to samo co <code>Partner URN</code>.</span></p>';
            echo '<p><label><input type="checkbox" name="portal_oo_enable_olx" value="1" ' . checked($oo_enable_olx, true, false) . '> Wlacz dodatkowe walidacje tresci wymagane przy eksporcie zgodnym z OLX</label></p>';
            echo '<p><label><input type="checkbox" name="portal_oo_test_account_mode" value="1" ' . checked($oo_test_account_mode, true, false) . '> Tryb testowego konta OtoDom (automatycznie doda prefix <code>[qatest-mercury]</code> i testowy opis)</label></p>';
            echo '<p><label>User-Agent naglowek API<br><input class="regular-text" type="text" name="portal_oo_user_agent" value="' . esc_attr($oo_user_agent) . '" placeholder="Estate Office CRM"></label></p>';
            echo '<input type="hidden" name="portal_oo_auth_state" value="' . esc_attr($oo_auth_state) . '">';
            echo '</div>';

            echo '<div class="eocrm-admin-panel" style="margin-top:12px;">';
            echo '<h4>Callback URL (do App Manager)</h4>';
            echo '<p><label>Authentication callback URL<br><input class="large-text code" type="text" readonly value="' . esc_attr($oo_auth_callback_url) . '"></label></p>';
            echo '<p><label>Notification callback URL<br><input class="large-text code" type="text" readonly value="' . esc_attr($oo_notification_callback_url) . '"></label></p>';
            echo '</div>';

            echo '<div class="eocrm-admin-panel" style="margin-top:12px;">';
            echo '<h4>Autoryzacja konta</h4>';
            if (! empty($oo_missing_credentials)) {
                echo '<p class="description">Przed autoryzacja zapisz komplet danych API: <strong>' . esc_html(implode(', ', $oo_missing_credentials)) . '</strong>.</p>';
            }
            if ($oo_can_authorize) {
                echo '<p><a class="button button-primary" href="' . esc_url($oo_authorization_url) . '" target="_blank" rel="noopener noreferrer">Autoryzuj konto OtoDom/OLX</a></p>';
                echo '<p class="description">Po autoryzacji portal przekieruje uzytkownika na Authentication callback URL i CRM zapisze access/refresh token.</p>';
            } else {
                echo '<p class="description">Link autoryzacyjny pojawi sie po zapisaniu kompletnych danych integracji.</p>';
            }
            echo '<p class="description">Dane testowego konta agencyjnego OtoDom wpisujesz dopiero na ekranie logowania OtoDom po kliknieciu przycisku autoryzacji, a nie w formularzu CRM.</p>';
            echo '<p><strong>Status tokenu:</strong> ' . ($oo_has_access ? 'aktywny access token zapisany' : 'brak access tokenu') . '</p>';
            echo '<p><strong>Refresh token:</strong> ' . ($oo_has_refresh ? 'zapisany' : 'brak') . '</p>';
            if ($oo_token_expires_at !== '') {
                echo '<p><strong>Wygasa:</strong> ' . esc_html($oo_token_expires_at) . '</p>';
            }
            if ($oo_authorized_at !== '') {
                echo '<p><strong>Autoryzowano:</strong> ' . esc_html($oo_authorized_at) . '</p>';
            }
            if ($oo_scope !== '') {
                echo '<p><strong>Scope:</strong> <code>' . esc_html($oo_scope) . '</code></p>';
            }
            if ($oo_last_auth_message !== '') {
                echo '<p><strong>Ostatni komunikat OAuth:</strong> ' . esc_html($oo_last_auth_message) . '</p>';
            }
            echo '</div>';

            echo '<div class="eocrm-admin-panel" style="margin-top:12px;">';
            echo '<h4>Webhook status</h4>';
            echo '<p><strong>Ostatni webhook:</strong> ' . ($oo_last_webhook_at !== '' ? esc_html($oo_last_webhook_at) : '-') . '</p>';
            echo '<p><strong>Event:</strong> ' . ($oo_last_webhook_event !== '' ? esc_html($oo_last_webhook_event) : '-') . '</p>';
            echo '<p><strong>Szczegoly:</strong> ' . ($oo_last_webhook_message !== '' ? esc_html($oo_last_webhook_message) : '-') . '</p>';
            echo '</div>';

            echo '<p class="description">Eksport dziala asynchronicznie (kolejka CRM) i wysyla oferty metodami POST/PUT/DELETE do <code>https://api.olxgroup.com/advert/v1</code>.</p>';
            echo '<p class="description">Dla OtoDom lokalizacja jest wysylana z koordynatami oraz dodatkowymi polami <code>city_id</code>, opcjonalnie <code>district_id</code> i <code>street_name</code>, zgodnie z dokumentacja OLX Group.</p>';
            echo '<p class="description">W przypadku zgodnosci z OLX wymagane sa dodatkowe walidacje tresci i jakosci danych oferty.</p>';
            echo '</section>';
            }
            echo '</section>';
            echo '</div>';
        }

        if ($active_tab === 'agreement_stages') {
            echo '<div class="eocrm-admin-panel">';
            echo '<h2>Etapy Umow</h2>';
            echo '<p>Wpisz jeden etap w osobnej linii. Kazdy typ transakcji ma niezalezna liste etapow.</p>';
            echo '</div>';
            echo '<div class="eocrm-admin-grid">';
            foreach (EstateOfficeCRM_Stages::transaction_options() as $transaction_type => $transaction_label) {
                $stages = isset($agreement_stage_sets[$transaction_type]) && is_array($agreement_stage_sets[$transaction_type])
                    ? $agreement_stage_sets[$transaction_type]
                    : EstateOfficeCRM_Stages::default_for_transaction($transaction_type);
                $textarea_value = implode("\n", array_map(static function ($value): string {
                    return sanitize_text_field((string) $value);
                }, $stages));

                echo '<section class="eocrm-admin-panel">';
                echo '<h3>' . esc_html($transaction_label) . '</h3>';
                echo '<textarea class="large-text" rows="12" name="agreement_stages_' . esc_attr(strtolower($transaction_type)) . '">' . esc_textarea($textarea_value) . '</textarea>';
                echo '</section>';
            }
            echo '</div>';
        }

        submit_button('Zapisz ustawienia');
        echo '</form>';
        echo '</div>';
    }

    public function render_about_page(): void
    {
        if (! current_user_can('eocrm_manage_settings')) {
            wp_die('Brak uprawnien.');
        }

        $logo_url = EOCRM_URL . 'assets/images/eocrm-logo.png';
        $working_features = [
            [
                'title' => 'Pełny CRM front-end',
                'text'  => 'Klienci, Umowy, Nieruchomości i Poszukiwania: pełna obsługa rekordów, profile, powiązania i historia pracy.',
            ],
            [
                'title' => 'Role i uprawnienia',
                'text'  => 'Administrator, Menedżer i Agent z kontrolą dostępu do danych, akcji oraz widoków panelu.',
            ],
            [
                'title' => 'Publiczne oferty WWW',
                'text'  => 'Listy sprzedaży i wynajmu, karta oferty, dynamiczne filtry, paginacja, statusy i wyróżnienia ofert.',
            ],
            [
                'title' => 'Multimedia oferty',
                'text'  => 'Galeria zdjęć, rzuty, wideo, spacer wirtualny, mapa lokalizacji i kalkulator kredytowy.',
            ],
            [
                'title' => 'Eksport danych i dokumenty',
                'text'  => 'Eksport XML z tabel CRM, PDF karty oferty oraz eksporty ofert do portali zewnętrznych skonfigurowane w ustawieniach.',
            ],
            [
                'title' => 'Agenci i biura',
                'text'  => 'Profile agentów, przypisania do biur, zdjęcia, kolejność wyświetlania i publiczna strona zespołu.',
            ],
            [
                'title' => 'Ustawienia systemowe',
                'text'  => 'Numeracja, etapy umów, szablony ofert i PDF, jednostki miary, pola dodatkowe oraz konfiguracja widoków.',
            ],
            [
                'title' => 'Pulpit menedżerski',
                'text'  => 'Podsumowanie aktywnych danych CRM, szybkie przejścia do kluczowych sekcji i bieżący kontekst pracy biura.',
            ],
        ];
        $default_planned_features = [
            [
                'title' => 'Eksport na portale',
                'text'  => 'Rozbudowa i stabilizacja eksportu ofert do zewnetrznych portali ogloszeniowych.',
            ],
            [
                'title' => 'Import z MLS WSPON',
                'text'  => 'Import ofert z systemu MLS WSPON do bazy nieruchomosci Estate Office CRM.',
            ],
        ];
        $planned_features = apply_filters('eocrm_planned_features', $default_planned_features);
        if (! is_array($planned_features)) {
            $planned_features = $default_planned_features;
        }
        if (empty($planned_features)) {
            $planned_features = $default_planned_features;
        }

        echo '<div class="wrap eocrm-admin-wrap eocrm-about-page">';
        echo '<div class="eocrm-admin-panel eocrm-about-hero">';
        echo '<div class="eocrm-about-logo-wrap">';
        echo '<img src="' . esc_url($logo_url) . '" alt="Estate Office CRM" class="eocrm-about-logo">';
        echo '</div>';
        echo '<p class="eocrm-about-lead">Estate Office CRM to zaawansowany system CRM dla biur nieruchomo&#347;ci, &#322;&#261;cz&#261;cy zarz&#261;dzanie baz&#261; klient&#243;w, umowami, nieruchomo&#347;ciami i publikacj&#261; ofert WWW w jednym sp&#243;jnym panelu WordPress.</p>';
        echo '<div class="eocrm-about-contact-row">';
        echo '<a class="eocrm-about-contact-pill" href="https://estateofficecrm.pl/" target="_blank" rel="noopener noreferrer"><span>Strona WWW</span><strong>estateofficecrm.pl</strong></a>';
        echo '<a class="eocrm-about-contact-pill" href="mailto:support@estateofficecrm.pl"><span>E-mail</span><strong>support@estateofficecrm.pl</strong></a>';
        echo '<a class="eocrm-about-contact-pill" href="https://estateofficecrm.pl/?page_id=204" target="_blank" rel="noopener noreferrer"><span>Regulamin abonamentu</span><strong>Regulamin</strong></a>';
        echo '<a class="eocrm-about-contact-pill" href="https://estateofficecrm.pl/?page_id=205" target="_blank" rel="noopener noreferrer"><span>Licencja wtyczki</span><strong>EULA</strong></a>';
        echo '<div class="eocrm-about-contact-pill eocrm-about-contact-pill-status"><span>Aktualny stan</span><strong>wersja ' . esc_html(EOCRM_VERSION) . '</strong></div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="eocrm-about-columns">';
        echo '<div class="eocrm-admin-panel">';
        echo '<h2>Dzia&#322;aj&#261;ce funkcje</h2>';
        echo '<ul class="eocrm-about-list eocrm-about-list-features">';
        foreach ($working_features as $feature) {
            echo '<li><span class="eocrm-about-item-title">' . esc_html($feature['title']) . '</span><span class="eocrm-about-item-text">' . esc_html($feature['text']) . '</span></li>';
        }
        echo '</ul>';
        echo '</div>';

        echo '<div class="eocrm-admin-panel">';
        echo '<h2>Funkcje zaplanowane</h2>';
        echo '<ul class="eocrm-about-list eocrm-about-list-planned">';
        foreach ($planned_features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $title = isset($feature['title']) ? (string) $feature['title'] : '';
            $text = isset($feature['text']) ? (string) $feature['text'] : '';
            if ($title === '' && $text === '') {
                continue;
            }
            echo '<li><span class="eocrm-about-item-title">' . esc_html($title) . '</span><span class="eocrm-about-item-text">' . esc_html($text) . '</span></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    public function render_license_page(): void
    {
        EstateOfficeCRM_License::render_admin_page();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_agents_with_profiles(): array
    {
        global $wpdb;

        $agents = get_users([
            'role__in' => [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 500,
        ]);

        if (empty($agents)) {
            return [];
        }

        $profiles_table = $this->tables['agent_profiles'];
        $profile_rows = $wpdb->get_results("SELECT * FROM {$profiles_table}", ARRAY_A);

        $profiles_by_user_id = [];
        if (is_array($profile_rows)) {
            foreach ($profile_rows as $profile_row) {
                $uid = isset($profile_row['user_id']) ? (int) $profile_row['user_id'] : 0;
                if ($uid > 0) {
                    $profiles_by_user_id[$uid] = $profile_row;
                }
            }
        }

        $result = [];

        foreach ($agents as $agent) {
            if (! $agent instanceof WP_User) {
                continue;
            }

            $profile = $profiles_by_user_id[(int) $agent->ID] ?? [];

            $result[] = [
                'id' => (int) $agent->ID,
                'user_login' => (string) $agent->user_login,
                'display_name' => (string) $agent->display_name,
                'user_email' => (string) $agent->user_email,
                'crm_role' => $this->extract_crm_role_from_user($agent),
                'phone' => isset($profile['phone']) ? (string) $profile['phone'] : '',
                'address_line' => isset($profile['address_line']) ? (string) $profile['address_line'] : '',
                'city' => isset($profile['city']) ? (string) $profile['city'] : '',
                'postal_code' => isset($profile['postal_code']) ? (string) $profile['postal_code'] : '',
                'bio' => isset($profile['bio']) ? (string) $profile['bio'] : '',
                'photo_id' => isset($profile['photo_id']) ? (int) $profile['photo_id'] : 0,
                'office_id' => isset($profile['office_id']) ? (int) $profile['office_id'] : 0,
                'is_public' => isset($profile['is_public']) ? (int) $profile['is_public'] : 1,
                'display_order' => isset($profile['display_order']) ? (int) $profile['display_order'] : 100,
            ];
        }

        usort($result, static function (array $a, array $b): int {
            $order_cmp = ((int) ($a['display_order'] ?? 100)) <=> ((int) ($b['display_order'] ?? 100));
            if ($order_cmp !== 0) {
                return $order_cmp;
            }

            return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsert_agent_profile(int $user_id, array $data): void
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
            'is_public' => isset($data['is_public']) ? ((int) $data['is_public'] === 1 ? 1 : 0) : 1,
            'display_order' => isset($data['display_order']) ? $this->sanitize_display_order((string) $data['display_order']) : 100,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_offices(bool $only_active = true): array
    {
        global $wpdb;

        $table = $this->tables['offices'];
        $sql = "SELECT id, office_name, office_email, office_phone, address_line, city, postal_code, description, logo_id, is_active, is_public, display_order
            FROM {$table}";
        $params = [];

        if ($only_active) {
            $sql .= ' WHERE is_active = %d';
            $params[] = 1;
        }

        $sql .= ' ORDER BY display_order ASC, office_name ASC, id ASC';

        $prepared = ! empty($params) ? $wpdb->prepare($sql, $params) : $sql;
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above; table name is internal plugin mapping.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function normalize_image_attachment_id(int $attachment_id): int
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

    private function normalize_office_id(int $office_id): int
    {
        if ($office_id <= 0) {
            return 0;
        }

        global $wpdb;

        $table = $this->tables['offices'];
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d LIMIT 1",
                $office_id
            )
        );

        return $exists ? $office_id : 0;
    }

    private function sanitize_display_order(string $value): int
    {
        $value = trim($value);
        if ($value === '' || ! is_numeric($value)) {
            return 100;
        }

        $order = (int) $value;
        if ($order < 0) {
            return 0;
        }
        if ($order > 9999) {
            return 9999;
        }

        return $order;
    }

    private function sanitize_crm_user_role(string $role): string
    {
        $role = sanitize_key($role);
        if (in_array($role, [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER], true)) {
            return $role;
        }

        return EstateOfficeCRM_Installer::ROLE_AGENT;
    }

    /**
     * @return array<string,string>
     */
    private function crm_user_role_options(): array
    {
        return [
            EstateOfficeCRM_Installer::ROLE_AGENT => 'Agent',
            EstateOfficeCRM_Installer::ROLE_MANAGER => 'Menedzer',
        ];
    }

    private function get_crm_role_label(string $role): string
    {
        $options = $this->crm_user_role_options();
        return isset($options[$role]) ? (string) $options[$role] : 'Agent';
    }

    private function extract_crm_role_from_user(WP_User $user): string
    {
        $roles = is_array($user->roles) ? $user->roles : [];
        if (in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true)) {
            return EstateOfficeCRM_Installer::ROLE_MANAGER;
        }
        if (in_array(EstateOfficeCRM_Installer::ROLE_AGENT, $roles, true)) {
            return EstateOfficeCRM_Installer::ROLE_AGENT;
        }

        return EstateOfficeCRM_Installer::ROLE_AGENT;
    }

    private function get_setting(string $key): string
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

    private function upsert_setting(string $key, string $value): void
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

    private function developer_access_enabled(): bool
    {
        return $this->get_setting(self::SETTING_DEVELOPER_ACCESS) === '1';
    }

    /**
     * @return array<string,string>
     */
    private function onboarding_default_items(): array
    {
        return [
            'agent_login' => 'Panel logowania',
            'agent_profile' => 'Profil Agenta',
            'offices_agents' => 'Agenci i biura',
            'crm_dashboard' => 'Panel CRM',
            'properties' => 'Nieruchomości',
            'searches' => 'Poszukiwania',
            'clients' => 'Klienci',
            'agreements' => 'Umowy',
        ];
    }

    /**
     * @return array{items:array<string,array{agent:bool,manager:bool}>,custom_links:array<int,array{label:string,url:string,agent:bool,manager:bool}>}
     */
    private function onboarding_default_config(): array
    {
        $items = [];
        foreach ($this->onboarding_default_items() as $key => $label) {
            $items[(string) $key] = [
                'agent' => true,
                'manager' => true,
            ];
        }

        return [
            'items' => $items,
            'custom_links' => [],
        ];
    }

    /**
     * @return array{items:array<string,array{agent:bool,manager:bool}>,custom_links:array<int,array{label:string,url:string,agent:bool,manager:bool}>}
     */
    private function load_onboarding_config(): array
    {
        $config = $this->onboarding_default_config();
        $raw_config = $this->get_setting(self::SETTING_ONBOARDING_CONFIG);
        if ($raw_config === '') {
            return $config;
        }

        $decoded = json_decode($raw_config, true);
        if (! is_array($decoded)) {
            return $config;
        }

        $decoded_items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : [];
        foreach ($this->onboarding_default_items() as $key => $label) {
            $item_row = isset($decoded_items[$key]) && is_array($decoded_items[$key]) ? $decoded_items[$key] : [];
            $config['items'][(string) $key] = [
                'agent' => array_key_exists('agent', $item_row) ? ! empty($item_row['agent']) : true,
                'manager' => array_key_exists('manager', $item_row) ? ! empty($item_row['manager']) : true,
            ];
        }

        $decoded_links = isset($decoded['custom_links']) && is_array($decoded['custom_links']) ? $decoded['custom_links'] : [];
        $config['custom_links'] = [];
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

    private function sanitize_onboarding_toggle_value($value, bool $default = true): bool
    {
        if ($value === null) {
            return $default;
        }

        return (string) $value === '1';
    }

    /**
     * @return array{items:array<string,array{agent:bool,manager:bool}>,custom_links:array<int,array{label:string,url:string,agent:bool,manager:bool}>}
     */
    private function sanitize_onboarding_config_from_post(): array
    {
        $config = $this->onboarding_default_config();
        $raw_items = isset($_POST['onboarding_items']) && is_array($_POST['onboarding_items'])
            ? wp_unslash($_POST['onboarding_items'])
            : [];

        foreach ($this->onboarding_default_items() as $key => $label) {
            $item_row = isset($raw_items[$key]) && is_array($raw_items[$key]) ? $raw_items[$key] : [];
            $config['items'][(string) $key] = [
                'agent' => $this->sanitize_onboarding_toggle_value($item_row['agent'] ?? null),
                'manager' => $this->sanitize_onboarding_toggle_value($item_row['manager'] ?? null),
            ];
        }

        $raw_links = isset($_POST['onboarding_custom_links']) && is_array($_POST['onboarding_custom_links'])
            ? wp_unslash($_POST['onboarding_custom_links'])
            : [];
        $config['custom_links'] = [];

        foreach ($raw_links as $link_row) {
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
                'agent' => $this->sanitize_onboarding_toggle_value($link_row['agent'] ?? null),
                'manager' => $this->sanitize_onboarding_toggle_value($link_row['manager'] ?? null),
            ];

            if (count($config['custom_links']) >= 50) {
                break;
            }
        }

        return $config;
    }

    private function render_onboarding_toggle(string $field_name, bool $enabled): void
    {
        echo '<span class="eocrm-onboarding-toggle">';
        echo '<label><input type="radio" name="' . esc_attr($field_name) . '" value="1" ' . checked($enabled, true, false) . '><span>TAK</span></label>';
        echo '<label><input type="radio" name="' . esc_attr($field_name) . '" value="0" ' . checked($enabled, false, false) . '><span>NIE</span></label>';
        echo '</span>';
    }

    private function render_onboarding_custom_link_row($index, array $row): void
    {
        $index_key = (string) $index;
        $base_name = 'onboarding_custom_links[' . $index_key . ']';
        $label = sanitize_text_field((string) ($row['label'] ?? ''));
        $url = esc_url_raw((string) ($row['url'] ?? ''));
        $agent_enabled = array_key_exists('agent', $row) ? ! empty($row['agent']) : true;
        $manager_enabled = array_key_exists('manager', $row) ? ! empty($row['manager']) : true;

        echo '<tr data-eocrm-onboarding-link-row="' . esc_attr($index_key) . '">';
        echo '<td><input class="regular-text" type="text" name="' . esc_attr($base_name . '[label]') . '" value="' . esc_attr($label) . '" placeholder="np. Instrukcja pierwszego logowania"></td>';
        echo '<td><input class="large-text" type="url" name="' . esc_attr($base_name . '[url]') . '" value="' . esc_attr($url) . '" placeholder="https://"></td>';
        echo '<td>';
        $this->render_onboarding_toggle($base_name . '[agent]', $agent_enabled);
        echo '</td>';
        echo '<td>';
        $this->render_onboarding_toggle($base_name . '[manager]', $manager_enabled);
        echo '</td>';
        echo '<td><button type="button" class="button button-link-delete" data-eocrm-onboarding-remove-link>Usuń</button></td>';
        echo '</tr>';
    }

    /**
     * @return array<int,array{id:int,display_name:string,user_email:string,role_label:string}>
     */
    private function get_onboarding_recipients(): array
    {
        $users = get_users([
            'role__in' => [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 500,
        ]);

        $recipients = [];
        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $email = sanitize_email((string) $user->user_email);
            if ($email === '' || ! is_email($email)) {
                continue;
            }

            $role = $this->extract_crm_role_from_user($user);
            $recipients[] = [
                'id' => (int) $user->ID,
                'display_name' => (string) $user->display_name,
                'user_email' => $email,
                'role_label' => $this->get_crm_role_label($role),
            ];
        }

        return $recipients;
    }

    private function send_onboarding_email_to_user(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return false;
        }

        $email = sanitize_email((string) $user->user_email);
        if ($email === '' || ! is_email($email)) {
            return false;
        }

        $crm_role = $this->extract_crm_role_from_user($user);
        if (! in_array($crm_role, [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER], true)) {
            return false;
        }

        $role_label = $this->get_crm_role_label($crm_role);
        $links = $this->build_onboarding_email_links($user, $crm_role);
        $subject = sprintf(
            '[%s] Onboarding Estate Office CRM',
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
        );
        $body = $this->build_onboarding_email_body($user, $role_label, $links);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return (bool) wp_mail($email, $subject, $body, $headers);
    }

    /**
     * @return array<int,array{label:string,url:string}>
     */
    private function build_onboarding_email_links(WP_User $user, string $crm_role): array
    {
        $config = $this->load_onboarding_config();
        $role_key = $crm_role === EstateOfficeCRM_Installer::ROLE_MANAGER ? 'manager' : 'agent';
        $links = [];

        foreach ($this->onboarding_default_items() as $item_key => $item_label) {
            $item_config = isset($config['items'][$item_key]) && is_array($config['items'][$item_key])
                ? $config['items'][$item_key]
                : ['agent' => true, 'manager' => true];
            if (empty($item_config[$role_key])) {
                continue;
            }

            $url = $this->resolve_onboarding_default_item_url((string) $item_key, (int) $user->ID);
            if ($url === '') {
                continue;
            }

            $links[] = [
                'label' => (string) $item_label,
                'url' => $url,
            ];
        }

        $custom_links = isset($config['custom_links']) && is_array($config['custom_links']) ? $config['custom_links'] : [];
        foreach ($custom_links as $custom_link) {
            if (! is_array($custom_link) || empty($custom_link[$role_key])) {
                continue;
            }

            $label = sanitize_text_field((string) ($custom_link['label'] ?? ''));
            $url = esc_url_raw((string) ($custom_link['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        /**
         * @param array<int,array{label:string,url:string}> $links
         */
        $filtered_links = apply_filters('eocrm_onboarding_email_links', $links, $user, $crm_role, $config);
        return is_array($filtered_links) ? $filtered_links : $links;
    }

    private function resolve_onboarding_default_item_url(string $item_key, int $user_id): string
    {
        switch ($item_key) {
            case 'agent_login':
                $url = $this->resolve_crm_frontend_url('agent_login', 'panel-logowania-agenta');
                return $url !== '' ? $url : wp_login_url();
            case 'agent_profile':
                return $this->resolve_agent_public_profile_url($user_id);
            case 'offices_agents':
                return $this->resolve_crm_frontend_url('offices_agents', 'biura-i-agenci');
            case 'crm_dashboard':
                return $this->resolve_crm_frontend_url('crm', 'crm');
            case 'properties':
                return $this->resolve_crm_frontend_url('properties', 'crm-nieruchomosci');
            case 'searches':
                return $this->resolve_crm_frontend_url('searches', 'crm-poszukiwania');
            case 'clients':
                return $this->resolve_crm_frontend_url('clients', 'crm-klienci');
            case 'agreements':
                return $this->resolve_crm_frontend_url('agreements', 'crm-umowy');
            default:
                return '';
        }
    }

    private function resolve_agent_public_profile_url(int $user_id): string
    {
        $offices_agents_url = $this->resolve_crm_frontend_url('offices_agents', 'biura-i-agenci');
        if ($offices_agents_url !== '') {
            return $offices_agents_url . '#eocrm-agent-' . max(0, $user_id);
        }

        return admin_url('profile.php');
    }

    /**
     * @param array<int,array{label:string,url:string}> $links
     */
    private function build_onboarding_email_body(WP_User $user, string $role_label, array $links): string
    {
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $display_name = trim((string) $user->display_name);
        if ($display_name === '') {
            $display_name = (string) $user->user_login;
        }

        $logo_url = EOCRM_URL . 'assets/images/eocrm-logo-horizontal.png';
        $lost_password_url = wp_lostpassword_url();
        $login_url = $this->resolve_crm_frontend_url('agent_login', 'panel-logowania-agenta');
        if ($login_url === '') {
            $login_url = wp_login_url();
        }

        $links_markup = '';
        foreach ($links as $link) {
            $label = sanitize_text_field((string) ($link['label'] ?? ''));
            $url = esc_url((string) ($link['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }

            $links_markup .= '<tr><td style="padding:8px 0;"><a href="' . $url . '" style="display:block;padding:13px 16px;border-radius:12px;background:#f3f8ff;border:1px solid #d8e6f6;color:#0b5cad;text-decoration:none;font-weight:700;">' . esc_html($label) . '</a></td></tr>';
        }

        if ($links_markup === '') {
            $links_markup = '<tr><td style="padding:10px 0;color:#5d6d85;">Administrator nie włączył jeszcze żadnych linków onboardingowych dla tej roli.</td></tr>';
        }

        return '<!doctype html><html><body style="margin:0;padding:0;background:#eef4fb;font-family:Arial,sans-serif;color:#10243d;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef4fb;padding:28px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe7f4;box-shadow:0 16px 36px rgba(15,31,54,0.10);">'
            . '<tr><td style="padding:24px 28px 10px;text-align:left;">'
            . '<img src="' . esc_url($logo_url) . '" alt="Estate Office CRM" style="max-width:260px;max-height:72px;width:auto;height:auto;display:block;margin:0 0 18px;">'
            . '<p style="margin:0 0 8px;color:#0b5cad;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Onboarding ' . esc_html($role_label) . '</p>'
            . '<h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#10243d;">Witaj w Estate Office CRM</h1>'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#40536a;">Cześć ' . esc_html($display_name) . ', przygotowaliśmy dla Ciebie zestaw najważniejszych linków startowych do pracy w systemie CRM dla strony <strong>' . esc_html($site_name) . '</strong>.</p>'
            . '</td></tr>'
            . '<tr><td style="padding:0 28px 8px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $links_markup . '</table>'
            . '</td></tr>'
            . '<tr><td style="padding:12px 28px 22px;">'
            . '<div style="border-top:1px solid #e1e9f3;padding-top:16px;">'
            . '<p style="margin:0 0 8px;font-size:14px;color:#40536a;"><strong>Login:</strong> ' . esc_html((string) $user->user_login) . '</p>'
            . '<p style="margin:0 0 14px;font-size:14px;color:#40536a;">Jeżeli nie masz hasła albo chcesz ustawić nowe, skorzystaj z resetowania hasła WordPress.</p>'
            . '<p style="margin:0;"><a href="' . esc_url($login_url) . '" style="display:inline-block;margin-right:8px;padding:10px 14px;border-radius:999px;background:#0b5cad;color:#ffffff;text-decoration:none;font-weight:700;">Przejdź do logowania</a>'
            . '<a href="' . esc_url($lost_password_url) . '" style="display:inline-block;padding:10px 14px;border-radius:999px;background:#edf4ff;color:#0b5cad;text-decoration:none;font-weight:700;border:1px solid #cfe0f2;">Ustaw / zresetuj hasło</a></p>'
            . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px;background:#f7fbff;border-top:1px solid #e1e9f3;color:#6b7d92;font-size:12px;line-height:1.5;">Ten e-mail został wysłany automatycznie z Estate Office CRM. Jeżeli nie spodziewasz się tej wiadomości, skontaktuj się z administratorem strony.</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</body></html>';
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function aml_rodo_uodo_document_definitions(): array
    {
        return [
            [
                'key' => 'aml_general_risk_assessment',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Og&#243;lna ocena ryzyka AML/CFT biura',
                'badge' => 'Wymagane',
                'description' => 'Dokument identyfikuj&#261;cy i oceniaj&#261;cy ryzyko prania pieni&#281;dzy oraz finansowania terroryzmu w ca&#322;ej dzia&#322;alno&#347;ci biura nieruchomo&#347;ci.',
                'basis' => 'Ustawa AML: art. 27; komunikaty GIIF dotycz&#261;ce oceny ryzyka instytucji obowi&#261;zanej.',
            ],
            [
                'key' => 'aml_internal_procedure',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Wewn&#281;trzna procedura AML/CFT',
                'badge' => 'Wymagane',
                'description' => 'Procedura zasad rozpoznawania ryzyka, identyfikacji klienta, beneficjenta rzeczywistego, PEP, sankcji, monitoringu relacji, archiwizacji i zawiadomie&#324;.',
                'basis' => 'Ustawa AML: art. 50.',
            ],
            [
                'key' => 'aml_anonymous_reporting',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Procedura anonimowego zg&#322;aszania narusze&#324; AML',
                'badge' => 'Wymagane',
                'description' => 'Kana&#322; i zasady poufnego zg&#322;aszania rzeczywistych albo potencjalnych narusze&#324; AML przez pracownik&#243;w i osoby wsp&#243;&#322;pracuj&#261;ce.',
                'basis' => 'Ustawa AML: art. 53.',
            ],
            [
                'key' => 'aml_training_records',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Dokumentacja szkole&#324; AML',
                'badge' => 'Wymagane',
                'description' => 'Programy szkole&#324;, listy obecno&#347;ci, certyfikaty i potwierdzenia zapoznania agent&#243;w oraz pracownik&#243;w z procedur&#261; AML.',
                'basis' => 'Ustawa AML: art. 52.',
            ],
            [
                'key' => 'aml_kyc_client_risk_cards',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Karty KYC i indywidualne oceny ryzyka klienta/transakcji',
                'badge' => 'Wymagane operacyjnie',
                'description' => 'Formularze identyfikacji i weryfikacji klienta, celu transakcji, &#378;r&#243;d&#322;a &#347;rodk&#243;w oraz poziomu ryzyka relacji lub transakcji okazjonalnej.',
                'basis' => 'Ustawa AML: art. 33-35; komunikaty GIIF o ocenie informacji o klientach.',
            ],
            [
                'key' => 'aml_bo_pep_sanctions',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Beneficjent rzeczywisty, PEP i sankcje',
                'badge' => 'Wymagane operacyjnie',
                'description' => 'O&#347;wiadczenia i wyniki weryfikacji beneficjent&#243;w rzeczywistych, statusu PEP, list sankcyjnych oraz struktury klienta instytucjonalnego.',
                'basis' => 'Ustawa AML: &#347;rodki bezpiecze&#324;stwa finansowego i wzmo&#380;one &#347;rodki dla podwy&#380;szonego ryzyka.',
            ],
            [
                'key' => 'aml_giif_notifications',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Rejestr analiz i zawiadomie&#324; do GIIF',
                'badge' => 'Gdy dotyczy',
                'description' => 'Analizy transakcji nietypowych, zawiadomienia do GIIF/prokuratora, potwierdzenia wysy&#322;ki oraz decyzje o odst&#261;pieniu od zg&#322;oszenia.',
                'basis' => 'Ustawa AML: obowi&#261;zki zawiadamiania GIIF i dokumentowania analizy.',
            ],
            [
                'key' => 'aml_responsible_persons',
                'category' => 'aml',
                'category_label' => 'AML / CFT - przeciwdzia&#322;anie praniu pieni&#281;dzy',
                'title' => 'Wyznaczenie os&#243;b odpowiedzialnych za AML',
                'badge' => 'Wymagane organizacyjnie',
                'description' => 'Uchwa&#322;a, zarz&#261;dzenie albo inny dokument wskazuj&#261;cy osob&#281; odpowiedzialn&#261; za obowi&#261;zki AML i raportowanie do kierownictwa.',
                'basis' => 'Ustawa AML: obowi&#261;zki organizacyjne instytucji obowi&#261;zanej.',
            ],
            [
                'key' => 'rodo_processing_register',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Rejestr czynno&#347;ci przetwarzania',
                'badge' => 'Zwykle wymagane',
                'description' => 'Rejestr proces&#243;w przetwarzania danych klient&#243;w, poszukuj&#261;cych, w&#322;a&#347;cicieli, najemc&#243;w, agent&#243;w, kandydat&#243;w i u&#380;ytkownik&#243;w strony.',
                'basis' => 'RODO: art. 30; UODO wyja&#347;nia, &#380;e rejestry udost&#281;pnia si&#281; organowi na &#380;&#261;danie.',
            ],
            [
                'key' => 'rodo_processor_register',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Rejestr kategorii czynno&#347;ci przetwarzania',
                'badge' => 'Gdy dotyczy',
                'description' => 'Rejestr prowadzony wtedy, gdy biuro dzia&#322;a jako podmiot przetwarzaj&#261;cy dane w imieniu innego administratora.',
                'basis' => 'RODO: art. 30 ust. 2.',
            ],
            [
                'key' => 'rodo_information_clauses',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Klauzule informacyjne',
                'badge' => 'Wymagane',
                'description' => 'Klauzule dla klient&#243;w, w&#322;a&#347;cicieli nieruchomo&#347;ci, poszukuj&#261;cych, najemc&#243;w, kontrahent&#243;w, agent&#243;w, kandydat&#243;w i formularzy WWW.',
                'basis' => 'RODO: art. 13-14.',
            ],
            [
                'key' => 'rodo_privacy_cookie_policy',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Polityka prywatno&#347;ci i cookies strony WWW',
                'badge' => 'Wymagane operacyjnie',
                'description' => 'Dokument publiczny opisuj&#261;cy administratora, cele przetwarzania, formularze, narz&#281;dzia analityczne, cookies, marketing i prawa os&#243;b.',
                'basis' => 'RODO: zasada przejrzysto&#347;ci i obowi&#261;zki informacyjne.',
            ],
            [
                'key' => 'rodo_processing_agreements',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Umowy powierzenia przetwarzania danych',
                'badge' => 'Gdy dotyczy',
                'description' => 'Umowy z hostingiem, dostawcami CRM, poczty, ksi&#281;gowo&#347;ci, marketingu, call center, portalami i innymi procesorami.',
                'basis' => 'RODO: art. 28.',
            ],
            [
                'key' => 'rodo_authorizations_register',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Upowa&#380;nienia i ewidencja os&#243;b upowa&#380;nionych',
                'badge' => 'Wymagane operacyjnie',
                'description' => 'Upowa&#380;nienia do przetwarzania danych dla agent&#243;w i pracownik&#243;w, zakresy dost&#281;pu oraz potwierdzenia zachowania poufno&#347;ci.',
                'basis' => 'RODO: art. 29 i zasada rozliczalno&#347;ci.',
            ],
            [
                'key' => 'rodo_risk_dpia',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Analiza ryzyka i DPIA',
                'badge' => 'Wymagane / warunkowe',
                'description' => 'Ocena ryzyk dla praw i wolno&#347;ci os&#243;b oraz ocena skutk&#243;w dla ochrony danych, je&#380;eli proces powoduje wysokie ryzyko.',
                'basis' => 'RODO: art. 24, 25, 32 i 35.',
            ],
            [
                'key' => 'rodo_data_subject_rights',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Procedura realizacji praw os&#243;b',
                'badge' => 'Wymagane operacyjnie',
                'description' => 'Procedura i rejestr wniosk&#243;w o dost&#281;p, sprostowanie, usuni&#281;cie, ograniczenie, przeniesienie, sprzeciw oraz cofni&#281;cie zgody.',
                'basis' => 'RODO: art. 15-22 i art. 12.',
            ],
            [
                'key' => 'rodo_retention_policy',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Polityka retencji i usuwania danych',
                'badge' => 'Wymagane operacyjnie',
                'description' => 'Okresy przechowywania danych klient&#243;w, um&#243;w, ofert, poszukiwa&#324;, dokumentacji AML, korespondencji, lead&#243;w i zg&#243;d marketingowych.',
                'basis' => 'RODO: zasada ograniczenia przechowywania i rozliczalno&#347;ci.',
            ],
            [
                'key' => 'rodo_marketing_consents',
                'category' => 'rodo',
                'category_label' => 'RODO - dokumentacja ochrony danych',
                'title' => 'Zgody marketingowe i zgody na publikacj&#281;',
                'badge' => 'Gdy dotyczy',
                'description' => 'Wzory i rejestr zg&#243;d na kontakt marketingowy, newsletter, publikacj&#281; wizerunku, referencje oraz dokumentacja wycofania zg&#243;d.',
                'basis' => 'RODO: art. 6 i art. 7; przepisy komunikacji elektronicznej, je&#380;eli dotyczy.',
            ],
            [
                'key' => 'uodo_breach_register_procedure',
                'category' => 'uodo',
                'category_label' => 'UODO / ustawa o ochronie danych osobowych',
                'title' => 'Procedura i rejestr narusze&#324; ochrony danych',
                'badge' => 'Wymagane',
                'description' => 'Rejestr wszystkich incydent&#243;w, ocena ryzyka naruszenia, decyzja o zg&#322;oszeniu lub braku zg&#322;oszenia oraz dzia&#322;ania naprawcze.',
                'basis' => 'RODO: art. 33-34; UODO wskazuje termin zg&#322;oszenia naruszenia do 72 godzin, gdy jest wymagane.',
            ],
            [
                'key' => 'uodo_breach_notifications',
                'category' => 'uodo',
                'category_label' => 'UODO / ustawa o ochronie danych osobowych',
                'title' => 'Zg&#322;oszenia do Prezesa UODO i zawiadomienia os&#243;b',
                'badge' => 'Gdy dotyczy',
                'description' => 'Kopie formularzy zg&#322;osze&#324;, potwierdzenia wysy&#322;ki, korespondencja z UODO oraz zawiadomienia os&#243;b, kt&#243;rych dane dotycz&#261;.',
                'basis' => 'RODO: art. 33-34; formularze i instrukcje UODO.',
            ],
            [
                'key' => 'uodo_dpo_appointment',
                'category' => 'uodo',
                'category_label' => 'UODO / ustawa o ochronie danych osobowych',
                'title' => 'IOD / osoba kontaktowa ds. ochrony danych',
                'badge' => 'Gdy dotyczy',
                'description' => 'Dokument wyznaczenia IOD albo osoby odpowiedzialnej za ochron&#281; danych, dane kontaktowe, zakres zada&#324; i potwierdzenie zg&#322;oszenia do UODO, je&#380;eli IOD zosta&#322; wyznaczony.',
                'basis' => 'RODO: art. 37-39; ustawa o ochronie danych osobowych - zg&#322;oszenia IOD do Prezesa UODO.',
            ],
            [
                'key' => 'uodo_authority_correspondence',
                'category' => 'uodo',
                'category_label' => 'UODO / ustawa o ochronie danych osobowych',
                'title' => 'Korespondencja, kontrole i decyzje UODO',
                'badge' => 'Gdy dotyczy',
                'description' => 'Pisma z UODO, skargi, odpowiedzi, protoko&#322;y kontroli, zalecenia, decyzje oraz dokumentacja wykonania zalece&#324;.',
                'basis' => 'Ustawa o ochronie danych osobowych: kompetencje Prezesa UODO i post&#281;powania kontrolne.',
            ],
            [
                'key' => 'uodo_audit_training_evidence',
                'category' => 'uodo',
                'category_label' => 'UODO / ustawa o ochronie danych osobowych',
                'title' => 'Audyty, testy zabezpiecze&#324; i szkolenia RODO',
                'badge' => 'Dobra praktyka',
                'description' => 'Raporty audytowe, testy dost&#281;p&#243;w, przegl&#261;dy zabezpiecze&#324;, listy obecno&#347;ci i potwierdzenia szkole&#324; z ochrony danych.',
                'basis' => 'RODO: zasada rozliczalno&#347;ci, art. 24 i 32.',
            ],
        ];
    }

    /**
     * @return array<string,array{attachment_id:int,notes:string,updated_at:string}>
     */
    private function load_compliance_documents(): array
    {
        $raw = $this->get_setting(self::SETTING_COMPLIANCE_DOCUMENTS);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $documents = [];
        foreach ($decoded as $key => $row) {
            $document_key = sanitize_key((string) $key);
            if ($document_key === '' || ! is_array($row)) {
                continue;
            }

            $documents[$document_key] = [
                'attachment_id' => isset($row['attachment_id']) ? absint((string) $row['attachment_id']) : 0,
                'notes' => isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '',
                'updated_at' => isset($row['updated_at']) ? sanitize_text_field((string) $row['updated_at']) : '',
            ];
        }

        return $documents;
    }

    /**
     * @return array<string,array{attachment_id:int,notes:string,updated_at:string}>
     */
    private function sanitize_compliance_documents_from_post(): array
    {
        $raw_rows = isset($_POST['compliance_documents']) ? wp_unslash($_POST['compliance_documents']) : [];
        if (! is_array($raw_rows)) {
            $raw_rows = [];
        }

        $existing_rows = $this->load_compliance_documents();
        $documents = [];
        foreach ($this->aml_rodo_uodo_document_definitions() as $definition) {
            $definition_key = sanitize_key((string) ($definition['key'] ?? ''));
            if ($definition_key === '') {
                continue;
            }

            $raw_row = isset($raw_rows[$definition_key]) && is_array($raw_rows[$definition_key])
                ? $raw_rows[$definition_key]
                : [];

            $attachment_id = isset($raw_row['attachment_id']) ? absint((string) $raw_row['attachment_id']) : 0;
            $notes = isset($raw_row['notes']) ? sanitize_textarea_field((string) $raw_row['notes']) : '';
            $previous = $existing_rows[$definition_key] ?? ['attachment_id' => 0, 'notes' => '', 'updated_at' => ''];
            $changed = (int) ($previous['attachment_id'] ?? 0) !== $attachment_id || (string) ($previous['notes'] ?? '') !== $notes;
            $updated_at = $changed ? current_time('mysql') : (string) ($previous['updated_at'] ?? '');

            $documents[$definition_key] = [
                'attachment_id' => $attachment_id,
                'notes' => $notes,
                'updated_at' => $updated_at,
            ];
        }

        return $documents;
    }

    /**
     * @return string[]
     */
    private function settings_tab_keys(bool $developer_access_enabled): array
    {
        $tabs = [
            'general',
            'onboarding',
            'aml_rodo_uodo',
            'offer_templates',
            'units',
            'mortgage_calculator',
            'numbering',
            'agreement_stages',
            'property_custom_fields',
            'portal_export',
        ];

        if ($developer_access_enabled) {
            $tabs[] = 'import_mls';
        }

        return $tabs;
    }

    /**
     * @return array<string,string>
     */
    private function portal_options_for_access(bool $developer_access_enabled): array
    {
        $options = EstateOfficeCRM_Portal_Export::portal_options();
        if (! $developer_access_enabled) {
            unset($options[EstateOfficeCRM_Portal_Export::TARGET_OTODOM_OLX]);
        }

        return $options;
    }

    /**
     * @return int[]
     */
    private function public_offers_per_page_options(): array
    {
        return [2, 4, 6, 8, 10, 12, 14, 16, 18, 20];
    }

    private function sanitize_public_offers_per_page(string $value): int
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

    private function sanitize_new_offer_duration_days(string $value): int
    {
        $raw = trim($value);
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

    private function sanitize_export_retention_days(string $value, int $default = 30): int
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

    private function sanitize_frontend_width_mode(string $value): string
    {
        $mode = strtolower(trim($value));

        return in_array($mode, ['plugin', 'screen'], true) ? $mode : 'screen';
    }

    private function sanitize_frontend_width_percent(string $value): int
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

    private function sanitize_mortgage_advisor_logo_size_percent(string $value): int
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

    private function sanitize_mortgage_advisor_logo_align_x(string $value): string
    {
        $align = strtolower(trim($value));

        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
    }

    /**
     * @param int|string $index
     * @param array<string, mixed> $field
     * @param array<string, string> $section_options
     * @param array<string, string> $type_options
     */
    private function render_property_additional_field_row($index, array $field, array $section_options, array $type_options): void
    {
        $row_index = is_int($index) ? (string) $index : (string) $index;
        $field_label = sanitize_text_field((string) ($field['label'] ?? ''));
        $field_key = sanitize_key((string) ($field['key'] ?? ''));
        $field_section = sanitize_key((string) ($field['section'] ?? 'details'));
        $field_type = sanitize_key((string) ($field['type'] ?? 'text'));
        $field_show_public = ! empty($field['show_public']) ? 1 : 0;

        if (! isset($section_options[$field_section])) {
            $field_section = 'details';
        }
        if (! isset($type_options[$field_type])) {
            $field_type = 'text';
        }

        echo '<tr data-eocrm-custom-field-row="' . esc_attr($row_index) . '">';
        echo '<td>';
        echo '<input class="regular-text" type="text" name="property_additional_fields[' . esc_attr($row_index) . '][label]" value="' . esc_attr($field_label) . '" placeholder="Np. Prad 3-fazowy">';
        echo '<input type="hidden" name="property_additional_fields[' . esc_attr($row_index) . '][key]" value="' . esc_attr($field_key) . '">';
        echo '</td>';

        echo '<td><select name="property_additional_fields[' . esc_attr($row_index) . '][section]">';
        foreach ($section_options as $section_key => $section_label) {
            echo '<option value="' . esc_attr((string) $section_key) . '"' . selected($field_section, (string) $section_key, false) . '>' . esc_html((string) $section_label) . '</option>';
        }
        echo '</select></td>';

        echo '<td><select name="property_additional_fields[' . esc_attr($row_index) . '][type]">';
        foreach ($type_options as $type_key => $type_label) {
            echo '<option value="' . esc_attr((string) $type_key) . '"' . selected($field_type, (string) $type_key, false) . '>' . esc_html((string) $type_label) . '</option>';
        }
        echo '</select></td>';

        echo '<td><label><input type="checkbox" name="property_additional_fields[' . esc_attr($row_index) . '][show_public]" value="1" ' . checked($field_show_public, 1, false) . '> Tak</label></td>';
        echo '<td><button type="button" class="button-link-delete" data-eocrm-custom-field-remove>Usun</button></td>';
        echo '</tr>';
    }

    /**
     * @return array{sql:string, params:array<int, int>}
     */
    private function build_owner_scope(bool $is_admin_user, int $current_user_id): array
    {
        if ($is_admin_user || $current_user_id <= 0) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        return EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $current_user_id);
    }

    /**
     * Rozwiazuje URL do strony front-endu CRM niezaleznie od ustawien Permalinks WordPressa.
     *
     * Strategia (od najpewniejszej):
     *  1. ID strony zapisane w opcji `eocrm_page_ids` (zarejestrowane przy aktywacji wtyczki),
     *  2. Wyszukiwanie po slugu fallback (legacy / recznie zalozone strony),
     *  3. Pusty string -> wpis pomijany w siatce przyciskow.
     *
     * `get_permalink( $page_id )` automatycznie zwraca:
     *  - `?page_id=N` przy ustawieniu Permalinks "Plain",
     *  - `/crm/`, `/crm-nieruchomosci/` itd. przy ustawieniu "Post name" / "Custom".
     *
     * @param string $page_key      Klucz w opcji `eocrm_page_ids` (np. `crm`, `properties`, `clients`).
     * @param string $slug_fallback Slug strony do wyszukania, gdy opcja nie ma ID.
     */
    private function resolve_crm_frontend_url(string $page_key, string $slug_fallback): string
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
        if (! is_string($permalink) || $permalink === '') {
            return '';
        }

        return $permalink;
    }

    /**
     * @param array<int, scalar|null> $owner_scope_params
     */
    private function count_published_properties(string $owner_scope_sql = '', array $owner_scope_params = []): int
    {
        return $this->count_table(
            $this->tables['properties'],
            'is_active = 1 AND export_www = 1 AND is_sold = 0 AND is_rented = 0' . $owner_scope_sql,
            $owner_scope_params
        );
    }

    /**
     * @param array<int, scalar|null> $owner_scope_params
     */
    private function count_active_agreements(string $owner_scope_sql = '', array $owner_scope_params = []): int
    {
        $where = 'is_active = 1 AND ' . $this->agreement_not_finished_sql('current_stage') . $owner_scope_sql;

        return $this->count_table($this->tables['agreements'], $where, $owner_scope_params);
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

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses plugin-owned table names and controlled where fragments.
        $count = $wpdb->get_var($sql);

        return max(0, (int) $count);
    }

    private function agreement_not_finished_sql(string $column): string
    {
        return "COALESCE(LOWER(TRIM({$column})), '') NOT LIKE 'umowa zako%czona'";
    }

    /**
     * @param array<int, scalar|null> $params
     */
    private function count_table(string $table, string $where = '1=1', array $params = []): int
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses plugin-owned table names and controlled where fragments.
        $count = $wpdb->get_var($sql);

        return max(0, (int) $count);
    }

    /**
     * @param array<int, scalar|null> $params
     * @return array<int, array{label:string,total:int}>
     */
    private function load_distribution_data(string $table, string $group_column, string $where = '1=1', array $params = [], int $limit = 8): array
    {
        global $wpdb;

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $group_column)) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $sql = "SELECT {$group_column} AS label, COUNT(*) AS total
            FROM {$table}
            WHERE {$where}
            GROUP BY {$group_column}
            ORDER BY total DESC, label ASC
            LIMIT %d";
        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above; table/column names are internal and validated.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, scalar|null> $params
     * @return array<int, array<string, mixed>>
     */
    private function load_recent_agreements(string $owner_sql, array $params, int $limit = 8): array
    {
        global $wpdb;

        $limit = max(1, min(20, $limit));
        $table = $this->tables['agreements'];
        $users_table = $wpdb->users;

        $sql = "SELECT a.id, a.agreement_number, a.transaction_type, a.current_stage, a.date_signed, a.date_end, u.display_name AS owner_name
            FROM {$table} a
            LEFT JOIN {$users_table} u ON u.ID = a.owner_user_id
            WHERE a.is_active = 1{$owner_sql}
            ORDER BY a.updated_at DESC, a.id DESC
            LIMIT %d";
        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above; source tables are internal plugin tables.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, scalar|null> $params
     * @return array<int, array<string, mixed>>
     */
    private function load_recent_properties(string $owner_sql, array $params, int $limit = 8): array
    {
        global $wpdb;

        $limit = max(1, min(20, $limit));
        $table = $this->tables['properties'];
        $users_table = $wpdb->users;

        $sql = "SELECT p.id, p.offer_number, p.property_type, p.street, p.building_no, p.city, p.price, p.price_currency, p.export_www, u.display_name AS owner_name
            FROM {$table} p
            LEFT JOIN {$users_table} u ON u.ID = p.owner_user_id
            WHERE p.is_active = 1{$owner_sql}
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT %d";
        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above; source tables are internal plugin tables.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{display_name:string,agreements:int,properties:int,clients:int}>
     */
    private function load_agent_kpi_rows(int $limit = 8, array $scope_user_ids = []): array
    {
        global $wpdb;

        $limit = max(1, min(20, $limit));
        $query_args = [
            'role__in' => [EstateOfficeCRM_Installer::ROLE_AGENT, EstateOfficeCRM_Installer::ROLE_MANAGER],
            'fields' => ['ID', 'display_name'],
        ];
        $scope_user_ids = array_values(array_unique(array_filter(array_map('absint', $scope_user_ids))));
        if (! empty($scope_user_ids)) {
            $query_args['include'] = $scope_user_ids;
        }
        $users = get_users($query_args);

        if (empty($users)) {
            return [];
        }

        $metrics = [];
        $user_ids = [];
        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }
            $user_id = (int) $user->ID;
            if ($user_id <= 0) {
                continue;
            }
            $user_ids[] = $user_id;
            $metrics[$user_id] = [
                'display_name' => (string) $user->display_name,
                'agreements' => 0,
                'properties' => 0,
                'clients' => 0,
            ];
        }

        if (empty($user_ids)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($user_ids), '%d'));
        $dataset_map = [
            'agreements' => $this->tables['agreements'],
            'properties' => $this->tables['properties'],
            'clients' => $this->tables['clients'],
        ];

        foreach ($dataset_map as $metric_key => $table) {
            $sql = "SELECT owner_user_id AS user_id, COUNT(*) AS total
                FROM {$table}
                WHERE is_active = 1
                    AND owner_user_id IN ({$placeholders})
                GROUP BY owner_user_id";
            $prepared = $wpdb->prepare($sql, $user_ids);
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above and includes only internal plugin tables.
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $user_id = (int) ($row['user_id'] ?? 0);
                if (! isset($metrics[$user_id])) {
                    continue;
                }
                $metrics[$user_id][$metric_key] = (int) ($row['total'] ?? 0);
            }
        }

        $rows = array_values($metrics);
        usort(
            $rows,
            static function (array $left, array $right): int {
                $left_score = ((int) ($left['agreements'] ?? 0) * 1000) + ((int) ($left['properties'] ?? 0) * 100) + (int) ($left['clients'] ?? 0);
                $right_score = ((int) ($right['agreements'] ?? 0) * 1000) + ((int) ($right['properties'] ?? 0) * 100) + (int) ($right['clients'] ?? 0);
                if ($left_score !== $right_score) {
                    return $right_score <=> $left_score;
                }

                return strcasecmp((string) ($left['display_name'] ?? ''), (string) ($right['display_name'] ?? ''));
            }
        );

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<string, int> $distribution
     */
    private function render_distribution_chart(array $distribution, string $empty_text = 'Brak danych.'): void
    {
        if (empty($distribution)) {
            echo '<p class="description">' . esc_html($empty_text) . '</p>';
            return;
        }

        $max = max($distribution);
        if ($max <= 0) {
            echo '<p class="description">' . esc_html($empty_text) . '</p>';
            return;
        }

        echo '<div class="eocrm-dashboard-bars">';
        foreach ($distribution as $label => $value) {
            $count = max(0, (int) $value);
            $width = $max > 0 ? (int) round(($count / $max) * 100) : 0;
            echo '<div class="eocrm-dashboard-bar-row">';
            echo '<div class="eocrm-dashboard-bar-head"><span>' . esc_html((string) $label) . '</span><strong>' . esc_html((string) $count) . '</strong></div>';
            echo '<div class="eocrm-dashboard-bar-track"><span class="eocrm-dashboard-bar-fill" style="width:' . esc_attr((string) $width) . '%"></span></div>';
            echo '</div>';
        }
        echo '</div>';
    }

    private function format_admin_transaction_label(string $value): string
    {
        $map = [
            'SPRZEDAZ' => 'Sprzedaz',
            'KUPNO' => 'Kupno',
            'WYNAJEM' => 'Wynajem',
            'NAJEM' => 'Najem',
        ];

        $values = array_values(array_filter(array_map('trim', explode(',', strtoupper($value)))));
        if (! empty($values)) {
            $labels = [];
            foreach ($values as $single_value) {
                $labels[] = $map[$single_value] ?? $single_value;
            }

            return implode(', ', array_values(array_unique($labels)));
        }

        return trim($value) !== '' ? $value : 'Nieokreslony';
    }

    private function format_admin_property_type_label(string $value): string
    {
        $map = [
            'MIESZKANIE' => 'Mieszkanie',
            'DOM' => 'Dom',
            'DZIALKA' => 'Dzialka',
            'LOKAL_HU' => 'Lokal H/U',
        ];

        $value = strtoupper(trim($value));
        if (isset($map[$value])) {
            return $map[$value];
        }

        return $value !== '' ? $value : 'Nieokreslony';
    }

    private function format_admin_stage_label(string $value): string
    {
        $label = trim($value);
        if ($label === '') {
            return 'Brak etapu';
        }

        return $label;
    }

    private function redirect_with_notice(string $page, string $notice, string $message = '', array $extra_args = []): void
    {
        $args = [
            'page' => $page,
            'eocrm_notice' => $notice,
        ];

        if ($message !== '') {
            $args['eocrm_message'] = $message;
        }

        if (! empty($extra_args)) {
            foreach ($extra_args as $extra_key => $extra_value) {
                $normalized_key = sanitize_key((string) $extra_key);
                if ($normalized_key === '') {
                    continue;
                }

                $args[$normalized_key] = sanitize_text_field((string) $extra_value);
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function render_query_notice(): void
    {
        $notice = isset($_GET['eocrm_notice']) ? sanitize_key((string) wp_unslash($_GET['eocrm_notice'])) : '';
        $message = isset($_GET['eocrm_message']) ? sanitize_text_field((string) wp_unslash($_GET['eocrm_message'])) : '';

        if ($notice === '') {
            return;
        }

        $map = [
            'settings_saved' => 'Ustawienia zostaly zapisane.',
            'agent_created' => 'Agent zostal utworzony.',
            'agent_updated' => 'Dane agenta zostaly zaktualizowane.',
            'office_created' => 'Biuro zostalo utworzone.',
            'office_updated' => 'Dane biura zostaly zaktualizowane.',
            'offices_agents_content_updated' => 'Tresci strony Biura i Agenci zostaly zapisane.',
            'onboarding_email_sent' => 'E-mail onboardingowy zostal wyslany.',
        ];

        if ($notice === 'error') {
            $text = $message !== '' ? $message : 'Wystapil blad.';
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($text) . '</p></div>';
            return;
        }

        $text = $map[$notice] ?? '';
        if ($text === '') {
            return;
        }

        if ($message !== '') {
            $text .= ' ' . $message;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    private function delete_file_safely(string $file_path): void
    {
        $normalized_path = wp_normalize_path($file_path);
        if ($normalized_path === '' || ! file_exists($normalized_path)) {
            return;
        }

        wp_delete_file($normalized_path);
    }

    private function is_plugin_admin_page(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return in_array($page, [
            self::MENU_SLUG,
            self::AGENT_LOGIN_SLUG,
            self::AGENTS_SLUG,
            self::SETTINGS_SLUG,
            self::ABOUT_SLUG,
            self::LICENSE_SLUG,
        ], true);
    }
}



