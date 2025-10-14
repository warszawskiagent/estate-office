<?php

declare(strict_types=1);

namespace EstateOffice\Settings;

defined('ABSPATH') || exit;

final class GeneralSettings
{
    public const OPTION = 'estate_office_settings';

    public static function register(): void
    {
        register_setting(
            'estate_office_settings_group',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default'           => [
                    'google_maps_api_key'  => '',
                    'watermark_attachment' => 0,
                    'office_logo'          => 0,
                    'property_fields'      => [],
                    'agreement_fields'     => [],
                    'client_fields'        => [],
                ],
            ]
        );

        add_settings_section(
            'estate_office_integrations',
            __('Integracje', 'estate-office'),
            static fn () => printf('<p>%s</p>', esc_html__('Skonfiguruj integracje i materiały graficzne wykorzystywane w CRM.', 'estate-office')),
            'estate-office-settings'
        );

        add_settings_field(
            'estate_office_google_maps_api_key',
            __('Klucz API Map Google', 'estate-office'),
            [self::class, 'renderMapsField'],
            'estate-office-settings',
            'estate_office_integrations'
        );

        add_settings_field(
            'estate_office_watermark',
            __('Znak wodny', 'estate-office'),
            [self::class, 'renderWatermarkField'],
            'estate-office-settings',
            'estate_office_integrations'
        );

        add_settings_field(
            'estate_office_logo',
            __('Logo biura', 'estate-office'),
            [self::class, 'renderLogoField'],
            'estate-office-settings',
            'estate_office_integrations'
        );

        add_settings_section(
            'estate_office_dynamic_fields',
            __('Pola dynamiczne', 'estate-office'),
            static fn () => printf('<p>%s</p>', esc_html__('Zdefiniuj dodatkowe pola dla nieruchomości, umów i klientów. Lista pól będzie rozwijana w kolejnych etapach.', 'estate-office')),
            'estate-office-settings'
        );

        add_settings_field(
            'estate_office_property_fields',
            __('Pola nieruchomości', 'estate-office'),
            [self::class, 'renderPropertyFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_field(
            'estate_office_agreement_fields',
            __('Pola umów', 'estate-office'),
            [self::class, 'renderAgreementFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_field(
            'estate_office_client_fields',
            __('Pola klientów', 'estate-office'),
            [self::class, 'renderClientFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );
    }

    public static function sanitize($value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'google_maps_api_key'  => isset($value['google_maps_api_key']) ? sanitize_text_field($value['google_maps_api_key']) : '',
            'watermark_attachment' => isset($value['watermark_attachment']) ? absint($value['watermark_attachment']) : 0,
            'office_logo'          => isset($value['office_logo']) ? absint($value['office_logo']) : 0,
            'property_fields'      => self::sanitizeList($value['property_fields'] ?? []),
            'agreement_fields'     => self::sanitizeList($value['agreement_fields'] ?? []),
            'client_fields'        => self::sanitizeList($value['client_fields'] ?? []),
        ];
    }

    private static function sanitizeList($value): array
    {
        $value = is_array($value) ? $value : [];

        $sanitized = [];

        foreach ($value as $item) {
            $item = sanitize_text_field((string) $item);
            if ($item !== '') {
                $sanitized[] = $item;
            }
        }

        return array_values(array_unique($sanitized));
    }

    public static function renderMapsField(): void
    {
        $option = get_option(self::OPTION);
        $value  = isset($option['google_maps_api_key']) ? $option['google_maps_api_key'] : '';

        printf(
            '<input type="text" id="estate_office_google_maps_api_key" name="%1$s[google_maps_api_key]" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr(self::OPTION),
            esc_attr($value)
        );
        echo '<p class="description">' . esc_html__('Klucz API zostanie wykorzystany do integracji z Mapami Google w formularzach i na frontendzie.', 'estate-office') . '</p>';
    }

    public static function renderWatermarkField(): void
    {
        self::renderMediaField('watermark_attachment', __('Wybierz plik znaku wodnego', 'estate-office'));
    }

    public static function renderLogoField(): void
    {
        self::renderMediaField('office_logo', __('Wybierz logo biura', 'estate-office'));
    }

    private static function renderMediaField(string $key, string $buttonLabel): void
    {
        $option = get_option(self::OPTION);
        $id     = isset($option[$key]) ? (int) $option[$key] : 0;
        $url    = $id ? wp_get_attachment_url($id) : '';
        $field  = esc_attr(self::OPTION . '[' . $key . ']');

        printf('<input type="hidden" id="%1$s" name="%2$s" value="%3$d" />', esc_attr($key), $field, $id);
        echo '<div class="estate-office-media-field">';
        if ($url) {
            printf('<div class="preview"><img src="%s" alt="" style="max-width:150px;height:auto;" /></div>', esc_url($url));
        }
        printf(
            '<button type="button" class="button estate-office-media-upload" data-target="%1$s">%2$s</button> ',
            esc_attr($key),
            esc_html($buttonLabel)
        );
        printf(
            '<button type="button" class="button secondary estate-office-media-remove" data-target="%1$s"%2$s>%3$s</button>',
            esc_attr($key),
            $id ? '' : ' disabled',
            esc_html__('Usuń', 'estate-office')
        );
        echo '</div>';
        echo '<p class="description">' . esc_html__('Pliki są przechowywane w bibliotece mediów WordPress. Wybierz obraz w formacie PNG o przezroczystym tle, aby uzyskać najlepszy efekt.', 'estate-office') . '</p>';
    }

    public static function renderPropertyFields(): void
    {
        self::renderDynamicFields('property_fields', __('Dodaj etykietę pola (np. "Rynek wtórny") i naciśnij Enter.', 'estate-office'));
    }

    public static function renderAgreementFields(): void
    {
        self::renderDynamicFields('agreement_fields', __('Dodaj etykietę pola umowy (np. "Data prezentacji") i naciśnij Enter.', 'estate-office'));
    }

    public static function renderClientFields(): void
    {
        self::renderDynamicFields('client_fields', __('Dodaj etykietę pola klienta (np. "Preferowane godziny kontaktu") i naciśnij Enter.', 'estate-office'));
    }

    private static function renderDynamicFields(string $key, string $placeholder): void
    {
        $option = get_option(self::OPTION);
        $values = isset($option[$key]) && is_array($option[$key]) ? $option[$key] : [];

        echo '<div class="estate-office-tags-input" data-field="' . esc_attr($key) . '">';
        foreach ($values as $value) {
            printf(
                '<span class="tag">%1$s<button type="button" class="dashicons dashicons-no-alt" aria-label="%2$s"></button><input type="hidden" name="%3$s[%4$s][]" value="%5$s" /></span>',
                esc_html($value),
                esc_attr__('Usuń', 'estate-office'),
                esc_attr(self::OPTION),
                esc_attr($key),
                esc_attr($value)
            );
        }
        printf(
            '<input type="text" class="tag-input" placeholder="%1$s" data-name="%2$s[%3$s][]" autocomplete="off" />',
            esc_attr($placeholder),
            esc_attr(self::OPTION),
            esc_attr($key)
        );
        echo '</div>';
    }
}
