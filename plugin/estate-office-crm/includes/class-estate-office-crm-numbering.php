<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Numbering
{
    /** @var string[] */
    private const ENTITIES = ['agreement', 'offer', 'search'];

    /** @var string[] */
    private const DATE_FORMATS = [
        'Ymd',
        'dmY',
        'd-m-Y',
        'Y-m-d',
        'd.m.Y',
        'm-Y',
        'Y/m/d',
        'ymd',
    ];

    /** @var string[] */
    private const SEPARATORS = ['/', '-', '.'];

    /** @var array<string, string> */
    private const AGREEMENT_SCOPES = [
        'sprzedaz' => 'SPRZEDAZ',
        'kupno' => 'KUPNO',
        'wynajem' => 'WYNAJEM',
        'najem' => 'NAJEM',
    ];

    public static function is_supported_entity(string $entity): bool
    {
        return in_array($entity, self::ENTITIES, true);
    }

    /**
     * @return array<string, string>
     */
    public static function agreement_scope_options(): array
    {
        return self::AGREEMENT_SCOPES;
    }

    public static function sanitize_agreement_scope(string $scope): string
    {
        $scope = sanitize_key($scope);
        return isset(self::AGREEMENT_SCOPES[$scope]) ? $scope : '';
    }

    /**
     * @return array<string, string>
     */
    public static function date_format_options(): array
    {
        return [
            'Ymd' => 'YYYYMMDD (20260331)',
            'dmY' => 'DDMMYYYY (31032026)',
            'd-m-Y' => 'DD-MM-YYYY (31-03-2026)',
            'Y-m-d' => 'YYYY-MM-DD (2026-03-31)',
            'd.m.Y' => 'DD.MM.YYYY (31.03.2026)',
            'm-Y' => 'MM-YYYY (03-2026)',
            'Y/m/d' => 'YYYY/MM/DD (2026/03/31)',
            'ymd' => 'YYMMDD (260331)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function default_settings(): array
    {
        $defaults = [];

        foreach (['agreement' => 'UM-', 'offer' => 'OF-', 'search' => 'SZ-'] as $entity => $prefix) {
            self::append_default_settings_for_base($defaults, self::get_setting_key_prefix($entity, ''), $prefix);
        }

        foreach (array_keys(self::AGREEMENT_SCOPES) as $agreement_scope) {
            self::append_default_settings_for_base(
                $defaults,
                self::get_setting_key_prefix('agreement', $agreement_scope),
                'UM-'
            );
        }

        return $defaults;
    }

    public static function sanitize_parts_count($value): int
    {
        $count = absint((string) $value);
        if ($count <= 0) {
            $count = 1;
        }

        return min(3, $count);
    }

    public static function sanitize_separator(string $separator): string
    {
        return in_array($separator, self::SEPARATORS, true) ? $separator : '/';
    }

    public static function sanitize_part_type(string $part_type, int $part_index = 1): string
    {
        $part_type = sanitize_key($part_type);
        if ($part_type === 'counter') {
            $part_type = 'digits';
        }
        if ($part_type === 'text') {
            $part_type = 'custom';
        }

        if (! in_array($part_type, ['date', 'digits', 'custom'], true)) {
            return $part_index === 1 ? 'digits' : 'date';
        }

        return $part_type;
    }

    public static function sanitize_date_format(string $format): string
    {
        return in_array($format, self::DATE_FORMATS, true) ? $format : 'Ymd';
    }

    public static function sanitize_counter_next(string $counter_next): string
    {
        $digits = (string) preg_replace('/\D+/', '', trim($counter_next));
        if ($digits === '') {
            return '0001';
        }

        if (strlen($digits) > 18) {
            $digits = substr($digits, 0, 18);
        }

        return $digits;
    }

    public static function sanitize_counter_step($counter_step): int
    {
        $step = absint((string) $counter_step);
        if ($step <= 0) {
            $step = 1;
        }

        return min(1000000, $step);
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<string, mixed>
     */
    public static function load_entity_settings(string $entity, callable $get_setting, string $agreement_scope = ''): array
    {
        if (! self::is_supported_entity($entity)) {
            return [
                'entity' => 'agreement',
                'agreement_scope' => '',
                'prefix' => 'UM-',
                'separator' => '/',
                'parts_count' => 1,
                'parts' => [
                    1 => [
                        'type' => 'digits',
                        'date_format' => 'Ymd',
                        'counter_next' => '0001',
                        'counter_step' => 1,
                    ],
                    2 => [
                        'type' => 'date',
                        'date_format' => 'Ymd',
                        'counter_next' => '0001',
                        'counter_step' => 1,
                    ],
                    3 => [
                        'type' => 'date',
                        'date_format' => 'Ymd',
                        'counter_next' => '0001',
                        'counter_step' => 1,
                    ],
                ],
            ];
        }

        $fallback_prefix_map = [
            'agreement' => 'UM-',
            'offer' => 'OF-',
            'search' => 'SZ-',
        ];

        $agreement_scope = self::sanitize_agreement_scope($agreement_scope);
        $base_key = self::get_setting_key_prefix($entity, $agreement_scope);
        $fallback_base_key = self::get_setting_key_prefix($entity, '');

        $setting_value = function (string $suffix) use ($get_setting, $base_key, $fallback_base_key, $entity, $agreement_scope): string {
            $scoped_value = (string) $get_setting($base_key . '_' . $suffix);
            if ($scoped_value !== '') {
                return $scoped_value;
            }

            if ($entity === 'agreement' && $agreement_scope !== '' && $fallback_base_key !== $base_key) {
                return (string) $get_setting($fallback_base_key . '_' . $suffix);
            }

            return '';
        };

        $prefix = trim($setting_value('prefix'));
        if ($prefix === '') {
            $prefix = (string) ($fallback_prefix_map[$entity] ?? '');
        }

        $separator = self::sanitize_separator($setting_value('separator'));
        $parts_count = self::sanitize_parts_count($setting_value('parts'));
        $legacy_next = absint($setting_value('next'));
        if ($legacy_next <= 0) {
            $legacy_next = 1;
        }

        $parts = [];

        for ($part_index = 1; $part_index <= 3; $part_index++) {
            $part_base = $base_key . '_part' . (string) $part_index;
            $fallback_part_base = $fallback_base_key . '_part' . (string) $part_index;
            $part_suffix_value = function (string $suffix) use ($get_setting, $part_base, $fallback_part_base, $entity, $agreement_scope): string {
                $value = (string) $get_setting($part_base . '_' . $suffix);
                if ($value !== '') {
                    return $value;
                }

                if ($entity === 'agreement' && $agreement_scope !== '' && $fallback_part_base !== $part_base) {
                    return (string) $get_setting($fallback_part_base . '_' . $suffix);
                }

                return '';
            };

            $part_type = self::sanitize_part_type($part_suffix_value('type'), $part_index);

            $date_format_raw = $part_suffix_value('date_format');
            if ($date_format_raw === '') {
                $date_format_raw = $part_suffix_value('value');
            }
            $date_format = self::sanitize_date_format($date_format_raw);

            $counter_next_raw = $part_suffix_value('counter_next');
            if ($counter_next_raw === '') {
                $legacy_part_value = $part_suffix_value('value');
                if ($part_type === 'digits' && preg_match('/^\d+$/', $legacy_part_value) === 1 && strlen($legacy_part_value) <= 18) {
                    $pad_len = max(1, absint($legacy_part_value));
                    $counter_next_raw = str_pad((string) $legacy_next, $pad_len, '0', STR_PAD_LEFT);
                }
            }

            if ($part_index === 1 && $counter_next_raw === '') {
                $counter_next_raw = str_pad((string) $legacy_next, 4, '0', STR_PAD_LEFT);
            }

            $counter_next = self::sanitize_counter_next($counter_next_raw);
            $counter_step = self::sanitize_counter_step($part_suffix_value('counter_step'));

            $parts[$part_index] = [
                'type' => $part_type,
                'date_format' => $date_format,
                'counter_next' => $counter_next,
                'counter_step' => $counter_step,
            ];
        }

        return [
            'entity' => $entity,
            'agreement_scope' => $agreement_scope,
            'prefix' => $prefix,
            'separator' => $separator,
            'parts_count' => $parts_count,
            'parts' => $parts,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function has_custom_part(array $settings): bool
    {
        $parts_count = isset($settings['parts_count']) ? self::sanitize_parts_count((string) $settings['parts_count']) : 1;
        $parts = isset($settings['parts']) && is_array($settings['parts']) ? $settings['parts'] : [];

        for ($part_index = 1; $part_index <= $parts_count; $part_index++) {
            $part = isset($parts[$part_index]) && is_array($parts[$part_index]) ? $parts[$part_index] : [];
            $type = self::sanitize_part_type((string) ($part['type'] ?? ''), $part_index);
            if ($type === 'custom') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function has_digits_part(array $settings): bool
    {
        $parts_count = isset($settings['parts_count']) ? self::sanitize_parts_count((string) $settings['parts_count']) : 1;
        $parts = isset($settings['parts']) && is_array($settings['parts']) ? $settings['parts'] : [];

        for ($part_index = 1; $part_index <= $parts_count; $part_index++) {
            $part = isset($parts[$part_index]) && is_array($parts[$part_index]) ? $parts[$part_index] : [];
            $type = self::sanitize_part_type((string) ($part['type'] ?? ''), $part_index);
            if ($type === 'digits') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function build_number_preview(array $settings, ?int $timestamp = null): string
    {
        if (self::has_custom_part($settings)) {
            return '';
        }

        $parts_count = isset($settings['parts_count']) ? self::sanitize_parts_count((string) $settings['parts_count']) : 1;
        $separator = self::sanitize_separator((string) ($settings['separator'] ?? '/'));
        $prefix = trim((string) ($settings['prefix'] ?? ''));
        $parts = isset($settings['parts']) && is_array($settings['parts']) ? $settings['parts'] : [];

        if ($timestamp === null) {
            $timestamp = current_time('timestamp');
        }

        $segments = [];

        for ($part_index = 1; $part_index <= $parts_count; $part_index++) {
            $part = isset($parts[$part_index]) && is_array($parts[$part_index]) ? $parts[$part_index] : [];
            $type = self::sanitize_part_type((string) ($part['type'] ?? ''), $part_index);

            if ($type === 'date') {
                $date_format = self::sanitize_date_format((string) ($part['date_format'] ?? 'Ymd'));
                $segments[] = wp_date($date_format, $timestamp);
                continue;
            }

            if ($type === 'digits') {
                $counter_next = self::sanitize_counter_next((string) ($part['counter_next'] ?? '0001'));
                $segments[] = self::format_counter_segment($counter_next);
            }
        }

        $body = implode($separator, $segments);
        if ($prefix === '') {
            return $body;
        }
        if ($body === '') {
            return $prefix;
        }

        if (preg_match('/[\/\-.]$/', $prefix) === 1) {
            return $prefix . $body;
        }

        return $prefix . $separator . $body;
    }

    /**
     * @param array<string, mixed> $settings
     * @param callable(string):bool $exists_callback
     * @return array{number:string,settings:array<string,mixed>}
     */
    public static function generate_unique_number(array $settings, callable $exists_callback): array
    {
        if (self::has_custom_part($settings)) {
            return [
                'number' => '',
                'settings' => $settings,
            ];
        }

        $has_digits = self::has_digits_part($settings);
        $working_settings = $settings;

        for ($attempt = 0; $attempt < 5000; $attempt++) {
            $candidate = self::build_number_preview($working_settings);
            if ($candidate === '') {
                return [
                    'number' => '',
                    'settings' => $working_settings,
                ];
            }

            if (! $exists_callback($candidate)) {
                $next_settings = $has_digits ? self::increment_counters($working_settings) : $working_settings;
                return [
                    'number' => $candidate,
                    'settings' => $next_settings,
                ];
            }

            if (! $has_digits) {
                break;
            }

            $working_settings = self::increment_counters($working_settings);
        }

        return [
            'number' => '',
            'settings' => $settings,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function increment_counters(array $settings): array
    {
        $parts_count = isset($settings['parts_count']) ? self::sanitize_parts_count((string) $settings['parts_count']) : 1;
        $parts = isset($settings['parts']) && is_array($settings['parts']) ? $settings['parts'] : [];

        for ($part_index = 1; $part_index <= $parts_count; $part_index++) {
            $part = isset($parts[$part_index]) && is_array($parts[$part_index]) ? $parts[$part_index] : [];
            $type = self::sanitize_part_type((string) ($part['type'] ?? ''), $part_index);
            if ($type !== 'digits') {
                continue;
            }

            $counter_next = self::sanitize_counter_next((string) ($part['counter_next'] ?? '0001'));
            $counter_step = self::sanitize_counter_step((string) ($part['counter_step'] ?? '1'));
            $pad_len = max(1, strlen($counter_next));
            $counter_value = (int) ltrim($counter_next, '0');
            if ($counter_value <= 0) {
                $counter_value = 0;
            }

            $counter_value += $counter_step;
            $parts[$part_index]['counter_next'] = str_pad((string) $counter_value, $pad_len, '0', STR_PAD_LEFT);
            $parts[$part_index]['counter_step'] = $counter_step;
        }

        $settings['parts'] = $parts;
        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    public static function collect_setting_updates(string $entity, array $settings, string $agreement_scope = ''): array
    {
        $updates = [];
        if (! self::is_supported_entity($entity)) {
            return $updates;
        }

        $agreement_scope = self::sanitize_agreement_scope($agreement_scope);
        $base_key = self::get_setting_key_prefix($entity, $agreement_scope);

        $prefix = trim((string) ($settings['prefix'] ?? ''));
        $separator = self::sanitize_separator((string) ($settings['separator'] ?? '/'));
        $parts_count = isset($settings['parts_count']) ? self::sanitize_parts_count((string) $settings['parts_count']) : 1;
        $parts = isset($settings['parts']) && is_array($settings['parts']) ? $settings['parts'] : [];

        $updates[$base_key . '_prefix'] = $prefix;
        $updates[$base_key . '_parts'] = (string) $parts_count;
        $updates[$base_key . '_separator'] = $separator;

        $first_digits_next = '1';

        for ($part_index = 1; $part_index <= 3; $part_index++) {
            $part = isset($parts[$part_index]) && is_array($parts[$part_index]) ? $parts[$part_index] : [];
            $type = self::sanitize_part_type((string) ($part['type'] ?? ''), $part_index);
            $date_format = self::sanitize_date_format((string) ($part['date_format'] ?? 'Ymd'));
            $counter_next = self::sanitize_counter_next((string) ($part['counter_next'] ?? '0001'));
            $counter_step = self::sanitize_counter_step((string) ($part['counter_step'] ?? '1'));

            if ($type === 'digits' && $first_digits_next === '1') {
                $first_digits_next = (string) max(1, (int) ltrim($counter_next, '0'));
            }

            $part_base = $base_key . '_part' . (string) $part_index;
            $updates[$part_base . '_type'] = $type;
            $updates[$part_base . '_date_format'] = $date_format;
            $updates[$part_base . '_counter_next'] = $counter_next;
            $updates[$part_base . '_counter_step'] = (string) $counter_step;

            // Legacy compatibility with older keys still present in previous versions.
            $legacy_type = $type;
            if ($legacy_type === 'digits') {
                $legacy_type = 'counter';
            } elseif ($legacy_type === 'custom') {
                $legacy_type = 'text';
            }

            $legacy_value = '';
            if ($type === 'date') {
                $legacy_value = $date_format;
            } elseif ($type === 'digits') {
                $legacy_value = $counter_next;
            }

            $updates[$part_base . '_type'] = $legacy_type;
            $updates[$part_base . '_value'] = $legacy_value;
        }

        $updates[$base_key . '_next'] = $first_digits_next;

        return $updates;
    }

    /**
     * @param array<string, string> $defaults
     */
    private static function append_default_settings_for_base(array &$defaults, string $base_key, string $prefix): void
    {
        $defaults[$base_key . '_prefix'] = $prefix;
        $defaults[$base_key . '_next'] = '1';
        $defaults[$base_key . '_parts'] = '1';
        $defaults[$base_key . '_separator'] = '/';
        for ($part_index = 1; $part_index <= 3; $part_index++) {
            $part_base = $base_key . '_part' . (string) $part_index;
            $defaults[$part_base . '_type'] = $part_index === 1 ? 'digits' : 'date';
            $defaults[$part_base . '_date_format'] = 'Ymd';
            $defaults[$part_base . '_counter_next'] = '0001';
            $defaults[$part_base . '_counter_step'] = '1';
            $defaults[$part_base . '_value'] = $part_index === 1 ? '0001' : 'Ymd';
        }
    }

    private static function get_setting_key_prefix(string $entity, string $agreement_scope = ''): string
    {
        $entity = sanitize_key($entity);
        if ($entity !== 'agreement') {
            return 'default_' . $entity . '_number';
        }

        $agreement_scope = self::sanitize_agreement_scope($agreement_scope);
        if ($agreement_scope === '') {
            return 'default_agreement_number';
        }

        return 'default_agreement_' . $agreement_scope . '_number';
    }

    private static function format_counter_segment(string $counter_next): string
    {
        $counter_next = self::sanitize_counter_next($counter_next);
        $pad_len = max(1, strlen($counter_next));
        $counter_value = (int) ltrim($counter_next, '0');
        if ($counter_value <= 0) {
            $counter_value = 0;
        }

        return str_pad((string) $counter_value, $pad_len, '0', STR_PAD_LEFT);
    }
}
