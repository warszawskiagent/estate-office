<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Units
{
    /**
     * @return array{area_local:string,area_land:string}
     */
    public static function default_settings(): array
    {
        return [
            'area_local' => 'm2',
            'area_land' => 'm2',
        ];
    }

    /**
     * @param callable(string):string $get_setting
     * @return array{area_local:string,area_land:string}
     */
    public static function load_settings(callable $get_setting): array
    {
        $defaults = self::default_settings();

        $raw_local = (string) $get_setting('unit_area_local');
        if ($raw_local === '') {
            $raw_local = (string) $get_setting('unit_area');
        }

        $raw_land = (string) $get_setting('unit_area_land');
        if ($raw_land === '') {
            $raw_land = (string) $get_setting('unit_land_area');
        }

        return [
            'area_local' => self::sanitize_local_unit($raw_local !== '' ? $raw_local : $defaults['area_local']),
            'area_land' => self::sanitize_land_unit($raw_land !== '' ? $raw_land : $defaults['area_land']),
        ];
    }

    public static function sanitize_local_unit(string $unit): string
    {
        $unit = strtolower(trim($unit));
        $allowed = ['m2', 'ft2'];

        return in_array($unit, $allowed, true) ? $unit : 'm2';
    }

    public static function sanitize_land_unit(string $unit): string
    {
        $unit = strtolower(trim($unit));
        $allowed = ['m2', 'ar', 'ha', 'ft2'];

        return in_array($unit, $allowed, true) ? $unit : 'm2';
    }

    public static function is_land_property(string $property_type): bool
    {
        return strtoupper(trim($property_type)) === 'DZIALKA';
    }

    /**
     * @param array{area_local?:string,area_land?:string} $settings
     */
    public static function get_area_unit_for_property(string $property_type, array $settings): string
    {
        $is_land = self::is_land_property($property_type);
        if ($is_land) {
            return self::sanitize_land_unit((string) ($settings['area_land'] ?? 'm2'));
        }

        return self::sanitize_local_unit((string) ($settings['area_local'] ?? 'm2'));
    }

    public static function unit_label(string $unit): string
    {
        $normalized = strtolower(trim($unit));
        $labels = [
            'm2' => 'm2',
            'ft2' => 'ft2',
            'ar' => 'ar',
            'ha' => 'ha',
        ];

        return isset($labels[$normalized]) ? (string) $labels[$normalized] : 'm2';
    }

    /**
     * @param mixed $value
     */
    public static function format_area_value($value, string $target_unit): string
    {
        if (! is_numeric($value)) {
            return '';
        }

        $target_unit = strtolower(trim($target_unit));
        $multiplier = self::area_multiplier_from_m2($target_unit);
        $converted = (float) $value * $multiplier;

        $decimals = 2;
        if ($target_unit === 'ha') {
            $decimals = 4;
        }

        return self::format_decimal($converted, $decimals);
    }

    /**
     * @param mixed $price_per_m2
     */
    public static function format_price_per_area($price_per_m2, string $currency_code, string $target_unit): string
    {
        if (! is_numeric($price_per_m2)) {
            return '-';
        }

        $target_unit = strtolower(trim($target_unit));
        $multiplier = self::area_multiplier_from_m2($target_unit);
        if ($multiplier <= 0) {
            $multiplier = 1;
        }

        $converted = (float) $price_per_m2 / $multiplier;
        $formatted = self::format_decimal($converted, 2);
        $currency = strtoupper(trim($currency_code));
        if ($currency === '') {
            $currency = 'PLN';
        }

        return $formatted . ' /' . self::unit_label($target_unit) . ' ' . $currency;
    }

    private static function area_multiplier_from_m2(string $target_unit): float
    {
        $factors = [
            'm2' => 1.0,
            'ft2' => 10.7639104167,
            'ar' => 0.01,
            'ha' => 0.0001,
        ];

        return isset($factors[$target_unit]) ? (float) $factors[$target_unit] : 1.0;
    }

    private static function format_decimal(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, ',', ' ');
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, ',');

        return $formatted !== '' ? $formatted : '0';
    }
}

