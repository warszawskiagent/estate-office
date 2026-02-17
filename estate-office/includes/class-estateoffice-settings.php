<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Settings
{
    private const ALLOWED_KEYS = [
        'google_maps_api_key',
        'watermark_url',
        'office_logo_url',
        'property_custom_fields',
        'agreement_custom_fields',
        'client_custom_fields',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return $default;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eo_settings';

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1", $key)
        );

        return is_string($value) ? $value : $default;
    }

    public static function set(string $key, string $value): bool
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eo_settings';

        $existingId = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE setting_key = %s LIMIT 1", $key)
        );

        if ($existingId) {
            $updated = $wpdb->update(
                $table,
                [
                    'setting_value' => $value,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) $existingId],
                ['%s', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'setting_key' => $key,
                'setting_value' => $value,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        return $inserted !== false;
    }

    public static function all(): array
    {
        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $settings[$key] = self::get($key, '');
        }

        return $settings;
    }

    public static function allowed_keys(): array
    {
        return self::ALLOWED_KEYS;
    }
}
