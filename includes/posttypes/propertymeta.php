<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post;

defined('ABSPATH') || exit;

final class PropertyMeta
{
    private const META_FIELDS = [
        'estate_property_street'              => ['type' => 'string'],
        'estate_property_number'              => ['type' => 'string'],
        'estate_property_unit'                => ['type' => 'string'],
        'estate_property_postal_code'         => ['type' => 'string'],
        'estate_property_district'            => ['type' => 'string'],
        'estate_property_city'                => ['type' => 'string'],
        'estate_property_county'              => ['type' => 'string'],
        'estate_property_precinct'            => ['type' => 'string'],
        'estate_property_plot_number'         => ['type' => 'string'],
        'estate_property_house_type'          => ['type' => 'enum', 'values' => self::HOUSE_TYPES],
        'estate_property_land_register_number'=> ['type' => 'string'],
        'estate_property_legal_status'        => ['type' => 'enum', 'values' => self::LEGAL_STATUSES],
        'estate_property_no_land_register'    => ['type' => 'boolean'],
        'estate_property_price'               => ['type' => 'decimal', 'precision' => 2],
        'estate_property_admin_fee'           => ['type' => 'decimal', 'precision' => 2],
        'estate_property_area'                => ['type' => 'decimal', 'precision' => 2],
        'estate_property_price_per_sqm'       => ['type' => 'decimal', 'precision' => 2, 'computed' => true],
        'estate_property_build_year'          => ['type' => 'year'],
        'estate_property_floor'               => ['type' => 'signed_integer'],
        'estate_property_floors'              => ['type' => 'integer'],
        'estate_property_rooms'               => ['type' => 'integer'],
        'estate_property_bedrooms'            => ['type' => 'integer'],
        'estate_property_bathrooms'           => ['type' => 'integer'],
        'estate_property_toilets'             => ['type' => 'integer'],
        'estate_property_plot_shape'          => ['type' => 'enum', 'values' => self::PLOT_SHAPES],
        'estate_property_plot_length'         => ['type' => 'decimal', 'precision' => 2],
        'estate_property_plot_width'          => ['type' => 'decimal', 'precision' => 2],
        'estate_property_plot_dimensions'     => ['type' => 'textarea'],
    ];

    private const LEGAL_STATUSES = [
        'ownership'      => 'Własność',
        'coownership'    => 'Współwłasność',
        'cooperative'    => 'Spółdzielcze własnościowe prawo do lokalu',
        'lease'          => 'Dzierżawa',
        'other'          => 'Inne',
    ];

    private const PLOT_SHAPES = [
        'regular'   => 'Regularny',
        'irregular' => 'Nieregularny',
    ];

    private const HOUSE_TYPES = [
        'detached'      => 'Wolnostojący',
        'semi_detached' => 'Bliźniak',
        'terraced'      => 'Szeregowiec',
        'multi_family'  => 'Wielorodzinny',
    ];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerMeta']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . PropertyRegister::POST_TYPE, [self::class, 'save']);
    }

    public static function registerMeta(): void
    {
        foreach (self::META_FIELDS as $key => $definition) {
            register_post_meta(
                PropertyRegister::POST_TYPE,
                $key,
                [
                    'type'              => ($definition['type'] ?? 'string') === 'boolean' ? 'boolean' : 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'auth_callback'     => [self::class, 'canEditMeta'],
                    'sanitize_callback' => self::buildSanitizer($definition),
                ]
            );
        }
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'estate-office-property-address',
            __('Dane adresowe', 'estate-office'),
            [self::class, 'renderAddressBox'],
            PropertyRegister::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'estate-office-property-legal',
            __('Informacje prawne', 'estate-office'),
            [self::class, 'renderLegalBox'],
            PropertyRegister::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'estate-office-property-details',
            __('Parametry nieruchomości', 'estate-office'),
            [self::class, 'renderDetailsBox'],
            PropertyRegister::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function renderAddressBox(WP_Post $post): void
    {
        wp_nonce_field('estate_office_property_meta', 'estate_office_property_meta_nonce');

        $street      = esc_attr(get_post_meta($post->ID, 'estate_property_street', true));
        $number      = esc_attr(get_post_meta($post->ID, 'estate_property_number', true));
        $unit        = esc_attr(get_post_meta($post->ID, 'estate_property_unit', true));
        $postalCode  = esc_attr(get_post_meta($post->ID, 'estate_property_postal_code', true));
        $district    = esc_attr(get_post_meta($post->ID, 'estate_property_district', true));
        $city        = esc_attr(get_post_meta($post->ID, 'estate_property_city', true));
        $county      = esc_attr(get_post_meta($post->ID, 'estate_property_county', true));
        $precinct    = esc_attr(get_post_meta($post->ID, 'estate_property_precinct', true));
        $plotNumber  = esc_attr(get_post_meta($post->ID, 'estate_property_plot_number', true));
        $houseType   = esc_attr(get_post_meta($post->ID, 'estate_property_house_type', true));

        echo '<table class="form-table estate-office-meta-table">';
        echo '<tr><th><label for="estate_property_street">' . esc_html__('Ulica', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_street" name="estate_property_street" value="%s" class="regular-text" autocomplete="address-line1" /></td></tr>', $street);

        echo '<tr><th><label for="estate_property_number">' . esc_html__('Numer', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_number" name="estate_property_number" value="%s" class="regular-text" autocomplete="address-line2" /></td></tr>', $number);

        echo '<tr><th><label for="estate_property_unit">' . esc_html__('Lokal', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_unit" name="estate_property_unit" value="%s" class="regular-text" autocomplete="address-line3" /></td></tr>', $unit);

        echo '<tr><th><label for="estate_property_postal_code">' . esc_html__('Kod pocztowy', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_postal_code" name="estate_property_postal_code" value="%s" class="regular-text" autocomplete="postal-code" /></td></tr>', $postalCode);

        echo '<tr><th><label for="estate_property_district">' . esc_html__('Dzielnica', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_district" name="estate_property_district" value="%s" class="regular-text" /></td></tr>', $district);

        echo '<tr><th><label for="estate_property_city">' . esc_html__('Miasto', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_city" name="estate_property_city" value="%s" class="regular-text" autocomplete="address-level2" /></td></tr>', $city);

        echo '<tr><th><label for="estate_property_county">' . esc_html__('Powiat', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_county" name="estate_property_county" value="%s" class="regular-text" /></td></tr>', $county);

        echo '<tr><th><label for="estate_property_precinct">' . esc_html__('Obręb', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_precinct" name="estate_property_precinct" value="%s" class="regular-text" /></td></tr>', $precinct);

        echo '<tr><th><label for="estate_property_plot_number">' . esc_html__('Numer działki', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_plot_number" name="estate_property_plot_number" value="%s" class="regular-text" /></td></tr>', $plotNumber);

        echo '<tr><th><label for="estate_property_house_type">' . esc_html__('Typ domu', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_property_house_type" name="estate_property_house_type">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::HOUSE_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($houseType, $value, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Pole dotyczy wyłącznie nieruchomości typu dom.', 'estate-office') . '</p>';
        echo '</td></tr>';
        echo '</table>';
    }

    public static function renderLegalBox(WP_Post $post): void
    {
        $landRegister    = esc_attr(get_post_meta($post->ID, 'estate_property_land_register_number', true));
        $noLandRegister  = (bool) get_post_meta($post->ID, 'estate_property_no_land_register', true);
        $legalStatus     = esc_attr(get_post_meta($post->ID, 'estate_property_legal_status', true));

        echo '<table class="form-table estate-office-meta-table">';
        echo '<tr><th><label for="estate_property_land_register_number">' . esc_html__('Numer księgi wieczystej', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_property_land_register_number" name="estate_property_land_register_number" value="%s" class="regular-text" /></td></tr>', $landRegister);

        echo '<tr><th><label for="estate_property_no_land_register">' . esc_html__('Brak KW', 'estate-office') . '</label></th>';
        printf('<td><label><input type="checkbox" id="estate_property_no_land_register" name="estate_property_no_land_register" value="1" %s /> %s</label></td></tr>', checked($noLandRegister, true, false), esc_html__('Zaznacz, jeśli nieruchomość nie posiada księgi wieczystej.', 'estate-office'));

        echo '<tr><th><label for="estate_property_legal_status">' . esc_html__('Stan prawny', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_property_legal_status" name="estate_property_legal_status">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::LEGAL_STATUSES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($legalStatus, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';
        echo '</table>';
    }

    public static function renderDetailsBox(WP_Post $post): void
    {
        $price         = esc_attr(get_post_meta($post->ID, 'estate_property_price', true));
        $adminFee      = esc_attr(get_post_meta($post->ID, 'estate_property_admin_fee', true));
        $area          = esc_attr(get_post_meta($post->ID, 'estate_property_area', true));
        $pricePerSqm   = esc_attr(get_post_meta($post->ID, 'estate_property_price_per_sqm', true));
        $buildYear     = esc_attr(get_post_meta($post->ID, 'estate_property_build_year', true));
        $floor         = esc_attr(get_post_meta($post->ID, 'estate_property_floor', true));
        $floors        = esc_attr(get_post_meta($post->ID, 'estate_property_floors', true));
        $rooms         = esc_attr(get_post_meta($post->ID, 'estate_property_rooms', true));
        $bedrooms      = esc_attr(get_post_meta($post->ID, 'estate_property_bedrooms', true));
        $bathrooms     = esc_attr(get_post_meta($post->ID, 'estate_property_bathrooms', true));
        $toilets       = esc_attr(get_post_meta($post->ID, 'estate_property_toilets', true));
        $plotShape     = esc_attr(get_post_meta($post->ID, 'estate_property_plot_shape', true));
        $plotLength    = esc_attr(get_post_meta($post->ID, 'estate_property_plot_length', true));
        $plotWidth     = esc_attr(get_post_meta($post->ID, 'estate_property_plot_width', true));
        $plotNotes     = esc_textarea(get_post_meta($post->ID, 'estate_property_plot_dimensions', true));

        echo '<table class="form-table estate-office-meta-table">';
        echo '<tr><th><label for="estate_property_price">' . esc_html__('Cena', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_property_price" name="estate_property_price" value="%s" class="regular-text" /> <span class="suffix">PLN</span></td></tr>', $price);

        echo '<tr><th><label for="estate_property_admin_fee">' . esc_html__('Czynsz administracyjny', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_property_admin_fee" name="estate_property_admin_fee" value="%s" class="regular-text" /> <span class="suffix">PLN</span></td></tr>', $adminFee);

        echo '<tr><th><label for="estate_property_area">' . esc_html__('Powierzchnia (m²)', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_property_area" name="estate_property_area" value="%s" class="regular-text" /></td></tr>', $area);

        echo '<tr><th><label for="estate_property_price_per_sqm">' . esc_html__('Cena za m²', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_property_price_per_sqm" name="estate_property_price_per_sqm" value="%s" class="regular-text" /> <span class="description">%s</span></td></tr>', $pricePerSqm, esc_html__('Wartość jest wyliczana automatycznie na podstawie ceny i metrażu.', 'estate-office'));

        echo '<tr><th><label for="estate_property_build_year">' . esc_html__('Rok budowy', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_property_build_year" name="estate_property_build_year" value="%s" class="small-text" /></td></tr>', $buildYear);

        echo '<tr><th><label for="estate_property_floor">' . esc_html__('Piętro', 'estate-office') . '</label></th>';
        printf('<td><input type="number" id="estate_property_floor" name="estate_property_floor" value="%s" class="small-text" /></td></tr>', $floor);

        echo '<tr><th><label for="estate_property_floors">' . esc_html__('Liczba pięter budynku', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_property_floors" name="estate_property_floors" value="%s" class="small-text" /></td></tr>', $floors);

        echo '<tr><th><label for="estate_property_rooms">' . esc_html__('Liczba pokoi', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_property_rooms" name="estate_property_rooms" value="%s" class="small-text" /></td></tr>', $rooms);

        echo '<tr><th><label for="estate_property_bedrooms">' . esc_html__('Liczba sypialni', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_property_bedrooms" name="estate_property_bedrooms" value="%s" class="small-text" /></td></tr>', $bedrooms);

        echo '<tr><th><label for="estate_property_bathrooms">' . esc_html__('Liczba łazienek', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_property_bathrooms" name="estate_property_bathrooms" value="%s" class="small-text" /></td></tr>', $bathrooms);

        echo '<tr><th><label for="estate_property_toilets">' . esc_html__('Liczba toalet', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_property_toilets" name="estate_property_toilets" value="%s" class="small-text" /></td></tr>', $toilets);

        echo '<tr><th><label for="estate_property_plot_shape">' . esc_html__('Kształt działki', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_property_plot_shape" name="estate_property_plot_shape">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::PLOT_SHAPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($plotShape, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="estate_property_plot_length">' . esc_html__('Długość działki (m)', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_property_plot_length" name="estate_property_plot_length" value="%s" class="regular-text" /></td></tr>', $plotLength);

        echo '<tr><th><label for="estate_property_plot_width">' . esc_html__('Szerokość działki (m)', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_property_plot_width" name="estate_property_plot_width" value="%s" class="regular-text" /></td></tr>', $plotWidth);

        echo '<tr><th><label for="estate_property_plot_dimensions">' . esc_html__('Opis wymiarów działki', 'estate-office') . '</label></th>';
        printf('<td><textarea id="estate_property_plot_dimensions" name="estate_property_plot_dimensions" rows="3" class="large-text">%s</textarea><p class="description">%s</p></td></tr>', $plotNotes, esc_html__('W przypadku nieregularnych działek podaj najważniejsze informacje o wymiarach.', 'estate-office'));

        echo '</table>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== PropertyRegister::POST_TYPE) {
            return;
        }

        if (!isset($_POST['estate_office_property_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST['estate_office_property_meta_nonce']));

        if (!wp_verify_nonce($nonce, 'estate_office_property_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $values = [];

        foreach (self::META_FIELDS as $key => $definition) {
            if (($definition['type'] ?? '') === 'boolean') {
                $values[$key] = self::sanitizeBoolean(isset($_POST[$key]) ? '1' : '0');
                continue;
            }

            $raw = $_POST[$key] ?? '';
            if (is_array($raw)) {
                $raw = '';
            }
            $values[$key] = self::sanitizeValue(wp_unslash((string) $raw), $definition);
        }

        $price = $values['estate_property_price'] ?? '';
        $area  = $values['estate_property_area'] ?? '';

        if ($price !== '' && $area !== '' && (float) $area > 0.0) {
            $values['estate_property_price_per_sqm'] = number_format((float) $price / (float) $area, 2, '.', '');
        }

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }
    }

    private static function persistMeta(int $postId, string $key, $value, array $definition): void
    {
        if (($definition['type'] ?? '') === 'boolean') {
            update_post_meta($postId, $key, $value ? 1 : 0);
            return;
        }

        if ($value === '') {
            delete_post_meta($postId, $key);
            return;
        }

        update_post_meta($postId, $key, $value);
    }

    private static function sanitizeValue(string $value, array $definition): string
    {
        switch ($definition['type'] ?? 'string') {
            case 'decimal':
                return self::sanitizeDecimal($value, (int) ($definition['precision'] ?? 2));
            case 'integer':
                return self::sanitizeInteger($value);
            case 'signed_integer':
                return self::sanitizeInteger($value, true);
            case 'year':
                return self::sanitizeYear($value);
            case 'textarea':
                return self::sanitizeTextarea($value);
            case 'enum':
                return self::sanitizeEnum($value, (array) ($definition['values'] ?? []));
            default:
                return self::sanitizeLine($value);
        }
    }

    private static function sanitizeLine(string $value): string
    {
        $value = sanitize_text_field($value);

        return trim($value);
    }

    private static function sanitizeTextarea(string $value): string
    {
        $value = sanitize_textarea_field($value);

        return trim($value);
    }

    private static function sanitizeDecimal(string $value, int $precision): string
    {
        $value = trim(str_replace(',', '.', $value));

        if ($value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return '';
        }

        $float = (float) $value;

        if (!is_finite($float)) {
            return '';
        }

        return number_format($float, $precision, '.', '');
    }

    private static function sanitizeInteger(string $value, bool $allowNegative = false): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = str_replace([' ', '+'], '', $value);

        $isNegative = $allowNegative && str_starts_with($value, '-');

        $value = preg_replace('/[^0-9]/', '', $value);

        if ($value === '') {
            return '';
        }

        $int = (int) $value;

        if ($isNegative) {
            $int *= -1;
        }

        if (!$allowNegative && $int < 0) {
            $int = abs($int);
        }

        return (string) $int;
    }

    private static function sanitizeYear(string $value): string
    {
        $value = self::sanitizeInteger($value);

        if ($value === '') {
            return '';
        }

        $year = (int) $value;
        $current = (int) gmdate('Y') + 5;

        if ($year < 1800 || $year > $current) {
            return '';
        }

        return (string) $year;
    }

    private static function sanitizeEnum(string $value, array $allowed): string
    {
        $value = sanitize_key($value);

        return array_key_exists($value, $allowed) ? $value : '';
    }

    private static function sanitizeBoolean(string $value): bool
    {
        return $value === '1' || $value === 'true' || $value === 'yes';
    }

    private static function buildSanitizer(array $definition): callable
    {
        return static function ($value) use ($definition) {
            $value = is_scalar($value) ? (string) $value : '';

            if (($definition['type'] ?? '') === 'boolean') {
                return self::sanitizeBoolean($value);
            }

            return self::sanitizeValue($value, $definition);
        };
    }

    public static function canEditMeta(bool $allowed, string $metaKey, int $postId): bool
    {
        unset($allowed, $metaKey);

        return current_user_can('edit_post', $postId);
    }
}
