<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Stages
{
    /**
     * @return array<string, string>
     */
    public static function transaction_options(): array
    {
        return [
            'SPRZEDAZ' => 'SPRZEDAZ',
            'KUPNO' => 'KUPNO',
            'WYNAJEM' => 'WYNAJEM',
            'NAJEM' => 'NAJEM',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function default_sets(): array
    {
        $default_sale = [
            'Umowa Posrednictwa',
            'Publikacja w MLS',
            'Przygotowanie oferty',
            'Publikacja oferty',
            'Marketing i prezentacje',
            'Oferta kupna',
            'Negocjacje',
            'Umowa przedwstepna',
            'Umowa przyrzeczona',
            'Przekazanie lokalu',
            'Umowa zakonczona',
        ];

        $default_buy = [
            'Umowa Posrednictwa',
            'Pierwsze oferty',
            'Pierwsze ogladanie',
            'Kolejne ogladanie',
            'Zlozenie oferty',
            'Negocjacje',
            'Umowa przedwstepna',
            'Wniosek kredytowy',
            'Umowa Kredytowa',
            'Umowa przyrzeczona',
            'Uruchomienie Kredytu',
            'Przejecie lokalu',
            'Kwestie wieczysto-ksiegowe',
            'Umowa zakonczona',
        ];

        $default_rent_out = [
            'Umowa Posrednictwa',
            'Publikacja w MLS',
            'Przygotowanie oferty',
            'Publikacja oferty',
            'Marketing i prezentacje',
            'Weryfikacja najemcy',
            'Umowa najmu',
            'Przekazanie lokalu',
            'Umowa zakonczona',
        ];

        $default_rent_in = [
            'Umowa Posrednictwa',
            'Pierwsze oferty',
            'Pierwsze ogladanie',
            'Kolejne ogladanie',
            'Zlozenie oferty',
            'Negocjacje',
            'Umowa najmu',
            'Przejecie lokalu',
            'Umowa zakonczona',
        ];

        return [
            'SPRZEDAZ' => $default_sale,
            'KUPNO' => $default_buy,
            'WYNAJEM' => $default_rent_out,
            'NAJEM' => $default_rent_in,
        ];
    }

    public static function setting_key(string $transaction_type): string
    {
        $normalized = self::normalize_transaction_type($transaction_type);
        if ($normalized === '') {
            return '';
        }

        return 'agreement_stages_' . strtolower($normalized) . '_json';
    }

    /**
     * @return array<int, string>
     */
    public static function default_for_transaction(string $transaction_type): array
    {
        $sets = self::default_sets();
        $normalized = self::normalize_transaction_type($transaction_type);

        if ($normalized !== '' && isset($sets[$normalized]) && is_array($sets[$normalized])) {
            return array_values($sets[$normalized]);
        }

        $first_set = reset($sets);
        return is_array($first_set) ? array_values($first_set) : ['Umowa Posrednictwa'];
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<int, string>
     */
    public static function load_for_transaction(string $transaction_type, callable $get_setting): array
    {
        $normalized = self::normalize_transaction_type($transaction_type);
        if ($normalized === '') {
            return self::all_unique($get_setting);
        }

        $setting_key = self::setting_key($normalized);
        $raw_json = $setting_key !== '' ? (string) $get_setting($setting_key) : '';
        $decoded = self::decode_json_stages($raw_json);

        if (! empty($decoded)) {
            return $decoded;
        }

        return self::default_for_transaction($normalized);
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<string, array<int, string>>
     */
    public static function load_all(callable $get_setting): array
    {
        $result = [];
        foreach (self::transaction_options() as $transaction_type => $label) {
            unset($label);
            $result[$transaction_type] = self::load_for_transaction($transaction_type, $get_setting);
        }

        return $result;
    }

    /**
     * @param callable(string):string $get_setting
     * @return array<int, string>
     */
    public static function all_unique(callable $get_setting): array
    {
        $merged = [];
        foreach (self::transaction_options() as $transaction_type => $label) {
            unset($label);
            $stages = self::load_for_transaction($transaction_type, $get_setting);
            foreach ($stages as $stage) {
                $stage_text = trim((string) $stage);
                if ($stage_text === '') {
                    continue;
                }
                if (! in_array($stage_text, $merged, true)) {
                    $merged[] = $stage_text;
                }
            }
        }

        if (empty($merged)) {
            return self::default_for_transaction('SPRZEDAZ');
        }

        return $merged;
    }

    /**
     * @param callable(string):string $get_setting
     */
    public static function first_stage(string $transaction_type, callable $get_setting): string
    {
        $stages = self::load_for_transaction($transaction_type, $get_setting);
        if (! empty($stages)) {
            return (string) $stages[0];
        }

        return 'Umowa Posrednictwa';
    }

    /**
     * @param callable(string):string $get_setting
     */
    public static function sanitize_stage_for_transaction(string $stage_name, string $transaction_type, callable $get_setting): string
    {
        $stage_name = trim(sanitize_text_field($stage_name));
        if ($stage_name === '') {
            return '';
        }

        $options = self::load_for_transaction($transaction_type, $get_setting);
        return in_array($stage_name, $options, true) ? $stage_name : '';
    }

    /**
     * @return array<int, string>
     */
    public static function sanitize_stage_lines(string $raw_text): array
    {
        $raw_lines = preg_split('/\r\n|\r|\n/', $raw_text);
        if (! is_array($raw_lines)) {
            return [];
        }

        $result = [];
        $dedupe = [];
        foreach ($raw_lines as $line) {
            $line = trim(sanitize_text_field((string) $line));
            if ($line === '') {
                continue;
            }

            if (function_exists('mb_substr')) {
                $line = (string) mb_substr($line, 0, 90);
            } else {
                $line = substr($line, 0, 90);
            }

            $dedupe_key = strtolower($line);
            if (isset($dedupe[$dedupe_key])) {
                continue;
            }

            $dedupe[$dedupe_key] = true;
            $result[] = $line;

            if (count($result) >= 30) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $stages
     */
    public static function encode_stages(array $stages): string
    {
        return (string) wp_json_encode(array_values($stages));
    }

    private static function normalize_transaction_type(string $transaction_type): string
    {
        $transaction_type = strtoupper(trim($transaction_type));
        $allowed = array_keys(self::transaction_options());

        return in_array($transaction_type, $allowed, true) ? $transaction_type : '';
    }

    /**
     * @return array<int, string>
     */
    private static function decode_json_stages(string $raw_json): array
    {
        if ($raw_json === '') {
            return [];
        }

        $decoded = json_decode($raw_json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $value) {
            $line = trim(sanitize_text_field((string) $value));
            if ($line === '') {
                continue;
            }
            if (! in_array($line, $result, true)) {
                $result[] = $line;
            }
        }

        return $result;
    }
}
