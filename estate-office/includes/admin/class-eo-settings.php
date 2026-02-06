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
            'google_maps_api_key' => array(
                'label' => 'API Map Google',
                'type' => 'text',
            ),
            'watermark_url' => array(
                'label' => 'Znak wodny',
                'type' => 'media',
            ),
            'office_logo_url' => array(
                'label' => 'Logo biura',
                'type' => 'media',
            ),
        );

        foreach ($fields as $key => $field) {
            add_settings_field(
                $key,
                $field['label'],
                $field['type'] === 'media' ? array($this, 'render_media_field') : array($this, 'render_text_field'),
                'estateoffice-settings',
                'estateoffice_settings_main',
                array(
                    'key' => $key,
                    'label' => $field['label'],
                )
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

    public function render_media_field($args)
    {
        $options = get_option(self::OPTION_NAME, array());
        $key = $args['key'] ?? '';
        $label = $args['label'] ?? '';
        $value = isset($options[$key]) ? $options[$key] : '';

        printf(
            '<div class="estateoffice-media-field" data-field="%1$s">' .
            '<input type="text" class="regular-text" name="%2$s[%1$s]" value="%3$s" /> ' .
            '<button type="button" class="button estateoffice-media-upload" data-title="%4$s">Wybierz plik</button>' .
            '<p class="description">Wgraj plik lub wklej bezpośredni URL.</p>' .
            '</div>',
            esc_attr($key),
            esc_attr(self::OPTION_NAME),
            esc_attr($value),
            esc_attr($label)
        );
    }
}
