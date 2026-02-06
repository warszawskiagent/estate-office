<?php

if (!defined('ABSPATH')) {
    exit;
}

class EstateOffice_Settings
{
    public const OPTION_GROUP = 'estateoffice_settings_group';
    public const OPTION_NAME = 'estateoffice_settings';

    public function register()
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(),
            )
        );

        add_settings_section(
            'estateoffice_settings_main',
            'Ustawienia podstawowe',
            '__return_false',
            'estateoffice-settings'
        );

        $fields = array(
            'google_maps_api_key' => 'API Map Google',
            'watermark_url' => 'Znak wodny (URL pliku)',
            'office_logo_url' => 'Logo biura (URL pliku)',
        );

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                array($this, 'render_text_field'),
                'estateoffice-settings',
                'estateoffice_settings_main',
                array('key' => $key)
            );
        }
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();
        $sanitized['google_maps_api_key'] = isset($input['google_maps_api_key'])
            ? sanitize_text_field($input['google_maps_api_key'])
            : '';
        $sanitized['watermark_url'] = isset($input['watermark_url'])
            ? esc_url_raw($input['watermark_url'])
            : '';
        $sanitized['office_logo_url'] = isset($input['office_logo_url'])
            ? esc_url_raw($input['office_logo_url'])
            : '';

        return $sanitized;
    }

    public function render_text_field($args)
    {
        $options = get_option(self::OPTION_NAME, array());
        $key = $args['key'] ?? '';
        $value = isset($options[$key]) ? $options[$key] : '';
        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            esc_attr($value)
        );
    }
}
