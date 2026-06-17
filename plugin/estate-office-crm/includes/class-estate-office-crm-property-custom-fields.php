<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Property_Custom_Fields
{
    public const SETTING_KEY = 'property_additional_fields_json';
    public const LEGACY_SETTING_KEY = 'property_custom_fields_json';

    /**
     * @return array<string, string>
     */
    public static function section_options(): array
    {
        return [
            'details' => 'Szczegoly nieruchomosci',
            'media' => 'Media',
            'amenities' => 'Udogodnienia',
            'equipment' => 'Wyposazenie',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function type_options(): array
    {
        return [
            'text' => 'Tekst',
            'number' => 'Liczba',
            'checkbox' => 'Tak/Nie',
        ];
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<int, array<string, mixed>>
     */
    public static function load_definitions(callable $get_setting): array
    {
        $raw_json = (string) $get_setting(self::SETTING_KEY);
        $decoded = self::decode_definitions($raw_json);
        if (! empty($decoded)) {
            return $decoded;
        }

        $legacy_json = (string) $get_setting(self::LEGACY_SETTING_KEY);
        $legacy = self::decode_legacy_definitions($legacy_json);
        if (! empty($legacy)) {
            return $legacy;
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function decode_definitions(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        return self::sanitize_definition_rows($decoded);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function decode_legacy_definitions(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $legacy_item) {
            $label = sanitize_text_field((string) $legacy_item);
            if ($label === '') {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'section' => 'details',
                'type' => 'text',
                'show_public' => 0,
            ];
        }

        return self::sanitize_definition_rows($rows);
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function sanitize_definition_rows(array $rows): array
    {
        $result = [];
        $dedupe = [];
        $sections = array_keys(self::section_options());
        $types = array_keys(self::type_options());

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = self::normalize_key((string) ($row['key'] ?? ''), $label);
            if ($key === '') {
                continue;
            }

            if (isset($dedupe[$key])) {
                continue;
            }
            $dedupe[$key] = true;

            $section = sanitize_key((string) ($row['section'] ?? 'details'));
            if (! in_array($section, $sections, true)) {
                $section = 'details';
            }

            $type = sanitize_key((string) ($row['type'] ?? 'text'));
            if (! in_array($type, $types, true)) {
                $type = 'text';
            }

            $show_public = ! empty($row['show_public']) ? 1 : 0;

            $result[] = [
                'key' => $key,
                'label' => $label,
                'section' => $section,
                'type' => $type,
                'show_public' => $show_public,
            ];

            if (count($result) >= 60) {
                break;
            }
        }

        return $result;
    }

    public static function encode_definitions(array $definitions): string
    {
        return (string) wp_json_encode(array_values($definitions));
    }

    public static function input_name(string $key): string
    {
        return 'custom_field_' . sanitize_key($key);
    }

    public static function has_value($value, string $type): bool
    {
        $type = sanitize_key($type);

        if ($type === 'checkbox') {
            return ! empty($value);
        }

        if ($type === 'number') {
            $value_text = trim((string) $value);
            if ($value_text === '') {
                return false;
            }

            return is_numeric(str_replace(',', '.', $value_text));
        }

        return trim((string) $value) !== '';
    }

    /**
     * @return string|bool
     */
    public static function sanitize_value($value, string $type)
    {
        $type = sanitize_key($type);

        if ($type === 'checkbox') {
            return ! empty($value);
        }

        if ($type === 'number') {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '';
            }

            $normalized = str_replace(',', '.', $raw);
            if (! is_numeric($normalized)) {
                return '';
            }

            $numeric = (float) $normalized;
            $formatted = number_format($numeric, 4, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * @param string|bool $value
     */
    public static function format_value($value, string $type): string
    {
        $type = sanitize_key($type);

        if ($type === 'checkbox') {
            return ! empty($value) ? 'Tak' : 'Nie';
        }

        return trim((string) $value);
    }

    /**
     * @return string|bool
     */
    public static function value_for_form($value, string $type)
    {
        $type = sanitize_key($type);
        if ($type === 'checkbox') {
            return ! empty($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    public static function normalize_key(string $preferred_key, string $fallback_label): string
    {
        $candidate = $preferred_key !== '' ? $preferred_key : $fallback_label;
        $candidate = remove_accents($candidate);
        $candidate = strtolower($candidate);
        $candidate = (string) preg_replace('/[^a-z0-9]+/', '_', $candidate);
        $candidate = trim($candidate, '_');
        if ($candidate === '') {
            $candidate = 'pole';
        }

        if (strlen($candidate) > 64) {
            $candidate = substr($candidate, 0, 64);
            $candidate = rtrim($candidate, '_');
        }

        return sanitize_key($candidate);
    }
}

