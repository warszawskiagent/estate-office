<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Settings {
    public const OPTION_KEY = 'eoc_settings';

    public static function register(): void {
        add_action('admin_post_eoc_save_settings', array(__CLASS__, 'handle_save'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'estate-office-crm_page_estate-office-crm-settings') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'eoc-settings',
            EOC_PLUGIN_URL . 'assets/admin/settings.js',
            array('jquery'),
            EOC_PLUGIN_VERSION,
            true
        );
        wp_enqueue_style(
            'eoc-admin',
            EOC_PLUGIN_URL . 'assets/admin/admin.css',
            array(),
            EOC_PLUGIN_VERSION
        );
    }

    public static function get_settings(): array {
        $defaults = array(
            'google_maps_api_key' => '',
            'watermark_attachment_id' => '',
            'office_logo_attachment_id' => '',
            'property_fields' => array('Numer oferty', 'Adres', 'Cena', 'Metraż', 'Liczba pokoi'),
            'contract_fields' => array('Numer umowy', 'Typ transakcji', 'Data zawarcia'),
            'client_fields' => array('Imię i nazwisko', 'Telefon', 'E-mail'),
        );

        $settings = get_option(self::OPTION_KEY, array());

        return wp_parse_args($settings, $defaults);
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Ustawienia Estate Office CRM', 'estate-office-crm') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_save_settings', 'eoc_settings_nonce');
        echo '<input type="hidden" name="action" value="eoc_save_settings" />';

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-google-maps">' . esc_html__('API Map Google', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-google-maps" name="eoc_settings[google_maps_api_key]" class="regular-text" value="' . esc_attr($settings['google_maps_api_key']) . '" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Znak wodny', 'estate-office-crm') . '</th>';
        echo '<td>';
        self::render_media_field('watermark_attachment_id', $settings['watermark_attachment_id'], __('Wybierz znak wodny', 'estate-office-crm'));
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Logo biura', 'estate-office-crm') . '</th>';
        echo '<td>';
        self::render_media_field('office_logo_attachment_id', $settings['office_logo_attachment_id'], __('Wybierz logo', 'estate-office-crm'));
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '<h2>' . esc_html__('Pola nieruchomości', 'estate-office-crm') . '</h2>';
        self::render_dynamic_field_list('property_fields', $settings['property_fields']);

        echo '<h2>' . esc_html__('Pola umów', 'estate-office-crm') . '</h2>';
        self::render_dynamic_field_list('contract_fields', $settings['contract_fields']);

        echo '<h2>' . esc_html__('Pola klientów', 'estate-office-crm') . '</h2>';
        self::render_dynamic_field_list('client_fields', $settings['client_fields']);

        submit_button(__('Zapisz ustawienia', 'estate-office-crm'));

        echo '</form>';
        echo '</div>';
    }

    public static function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_save_settings', 'eoc_settings_nonce');

        $input = isset($_POST['eoc_settings']) ? wp_unslash($_POST['eoc_settings']) : array();

        $sanitized = array(
            'google_maps_api_key' => isset($input['google_maps_api_key']) ? sanitize_text_field($input['google_maps_api_key']) : '',
            'watermark_attachment_id' => isset($input['watermark_attachment_id']) ? absint($input['watermark_attachment_id']) : '',
            'office_logo_attachment_id' => isset($input['office_logo_attachment_id']) ? absint($input['office_logo_attachment_id']) : '',
            'property_fields' => self::sanitize_field_list($input['property_fields'] ?? array()),
            'contract_fields' => self::sanitize_field_list($input['contract_fields'] ?? array()),
            'client_fields' => self::sanitize_field_list($input['client_fields'] ?? array()),
        );

        update_option(self::OPTION_KEY, $sanitized);

        wp_safe_redirect(add_query_arg('updated', 'true', wp_get_referer()));
        exit;
    }

    private static function sanitize_field_list($fields): array {
        if (!is_array($fields)) {
            return array();
        }

        $sanitized = array();
        foreach ($fields as $field) {
            $field = sanitize_text_field($field);
            if ($field !== '') {
                $sanitized[] = $field;
            }
        }

        return $sanitized;
    }

    private static function render_dynamic_field_list(string $name, array $values): void {
        echo '<div class="eoc-field-list" data-field-list="' . esc_attr($name) . '">';
        echo '<ul class="eoc-field-list__items">';

        if (empty($values)) {
            $values = array('');
        }

        foreach ($values as $value) {
            echo '<li class="eoc-field-list__item">';
            echo '<input type="text" name="eoc_settings[' . esc_attr($name) . '][]" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '<button type="button" class="button eoc-remove-field">' . esc_html__('Usuń', 'estate-office-crm') . '</button>';
            echo '</li>';
        }

        echo '</ul>';
        echo '<button type="button" class="button button-secondary eoc-add-field">' . esc_html__('Dodaj pole', 'estate-office-crm') . '</button>';
        echo '</div>';
    }

    private static function render_media_field(string $field_key, $attachment_id, string $button_label): void {
        $input_name = 'eoc_settings[' . $field_key . ']';
        $preview = '';

        if (!empty($attachment_id)) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            if ($image_url) {
                $preview = '<img src="' . esc_url($image_url) . '" alt="" class="eoc-media-preview" />';
            }
        }

        echo '<div class="eoc-media-field" data-target="' . esc_attr($field_key) . '">';
        echo '<input type="hidden" name="' . esc_attr($input_name) . '" value="' . esc_attr($attachment_id) . '" />';
        echo '<div class="eoc-media-preview-wrapper">' . $preview . '</div>';
        echo '<button type="button" class="button eoc-media-select">' . esc_html($button_label) . '</button>';
        echo '<button type="button" class="button eoc-media-remove">' . esc_html__('Usuń', 'estate-office-crm') . '</button>';
        echo '</div>';
    }
}
