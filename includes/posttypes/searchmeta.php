<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post;

use function esc_url;
use function get_edit_post_link;
use function get_post;
use function get_post_meta;
use function get_the_title;
use function get_user_by;
use function wp_dropdown_users;

defined('ABSPATH') || exit;

final class SearchMeta
{
    public const TRANSACTION_TYPES = [
        'sale'  => 'Sprzedaż',
        'buy'   => 'Kupno',
        'rent'  => 'Wynajem',
        'lease' => 'Najem',
    ];

    public const PROPERTY_TYPES = [
        'apartment'  => 'Mieszkanie',
        'house'      => 'Dom',
        'land'       => 'Działka',
        'commercial' => 'Lokal H/U',
    ];

    private const FINISHES = [
        'shell'      => 'Stan deweloperski',
        'to_finish'  => 'Do wykończenia',
        'ready'      => 'Wykończone',
        'premium'    => 'Podwyższony standard',
    ];

    private const EXPOSURES = [
        'north' => 'Północ',
        'south' => 'Południe',
        'east'  => 'Wschód',
        'west'  => 'Zachód',
    ];

    private const VIEWS = [
        'garden'   => 'Na ogród',
        'city'     => 'Na miasto',
        'park'     => 'Na park',
        'panorama' => 'Panorama',
        'courtyard'=> 'Na podwórze',
    ];

    private const LAYOUTS = [
        'separate'     => 'Rozkładowe',
        'open_plan'    => 'Otwarty plan',
        'adjustable'   => 'Możliwość aranżacji',
        'two_level'    => 'Dwupoziomowe',
    ];

    private const KITCHEN_TYPES = [
        'annex'       => 'Aneks kuchenny',
        'separate'    => 'Oddzielna',
        'with_living' => 'Z salonem',
    ];

    private const HEATING_TYPES = [
        'district'   => 'Miejskie',
        'gas'        => 'Gazowe',
        'electric'   => 'Elektryczne',
        'solid_fuel' => 'Na paliwo stałe',
        'heat_pump'  => 'Pompa ciepła',
        'other'      => 'Inne',
    ];

    private const WATER_TYPES = [
        'municipal' => 'Miejska',
        'well'      => 'Studnia',
        'other'     => 'Inne',
    ];

    private const SEWER_TYPES = [
        'municipal' => 'Miejska',
        'septic'    => 'Szambo',
        'treatment' => 'Przydomowa oczyszczalnia',
        'other'     => 'Inne',
    ];

    private const AMENITIES = [
        'lift'         => 'Winda',
        'security'     => 'Monitoring/Ochrona',
        'reception'    => 'Recepcja',
        'gated'        => 'Teren zamknięty',
        'intercom'     => 'Domofon',
        'air_condition'=> 'Klimatyzacja',
    ];

    private const FURNISHING = [
        'yes'     => 'Tak',
        'partial' => 'Częściowe',
        'no'      => 'Nie',
    ];

    private const EQUIPMENT = [
        'washing_machine' => 'Pralka',
        'dishwasher'      => 'Zmywarka',
        'fridge'          => 'Lodówka',
        'cooker'          => 'Kuchenka',
        'oven'            => 'Piekarnik',
        'tv'              => 'Telewizor',
        'microwave'       => 'Mikrofala',
    ];

    private const EXTRA_SPACES = [
        'balcony'  => 'Balkon',
        'terrace'  => 'Taras',
        'basement' => 'Piwnica',
        'storage'  => 'Komórka lokatorska',
        'garden'   => 'Ogródek',
        'parking'  => 'Miejsce parkingowe',
    ];

    private const META_FIELDS = [
        'estate_search_reference'         => ['type' => 'string'],
        'estate_search_transaction_type'  => ['type' => 'enum', 'values' => self::TRANSACTION_TYPES],
        'estate_search_property_type'     => ['type' => 'enum', 'values' => self::PROPERTY_TYPES],
        'estate_search_manager'           => ['type' => 'user'],
        'estate_search_price_min'         => ['type' => 'decimal', 'precision' => 2],
        'estate_search_price_max'         => ['type' => 'decimal', 'precision' => 2],
        'estate_search_area_min'          => ['type' => 'decimal', 'precision' => 2],
        'estate_search_area_max'          => ['type' => 'decimal', 'precision' => 2],
        'estate_search_rooms_min'         => ['type' => 'integer'],
        'estate_search_rooms_max'         => ['type' => 'integer'],
        'estate_search_location'          => ['type' => 'string'],
        'estate_search_description'       => ['type' => 'textarea'],
        'estate_search_building_finish'   => ['type' => 'enum', 'values' => self::FINISHES],
        'estate_search_exposure'          => ['type' => 'set', 'values' => self::EXPOSURES],
        'estate_search_views'             => ['type' => 'set', 'values' => self::VIEWS],
        'estate_search_attic'             => ['type' => 'boolean'],
        'estate_search_multilevel'        => ['type' => 'boolean'],
        'estate_search_layout'            => ['type' => 'set', 'values' => self::LAYOUTS],
        'estate_search_kitchen'           => ['type' => 'enum', 'values' => self::KITCHEN_TYPES],
        'estate_search_heating'           => ['type' => 'enum', 'values' => self::HEATING_TYPES],
        'estate_search_water'             => ['type' => 'enum', 'values' => self::WATER_TYPES],
        'estate_search_sewer'             => ['type' => 'enum', 'values' => self::SEWER_TYPES],
        'estate_search_gas'               => ['type' => 'boolean'],
        'estate_search_amenities'         => ['type' => 'set', 'values' => self::AMENITIES],
        'estate_search_furnishing'        => ['type' => 'enum', 'values' => self::FURNISHING],
        'estate_search_equipment'         => ['type' => 'set', 'values' => self::EQUIPMENT],
        'estate_search_extra_spaces'      => ['type' => 'set', 'values' => self::EXTRA_SPACES],
    ];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerMeta']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . SearchRegister::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    public static function registerMeta(): void
    {
        foreach (self::META_FIELDS as $key => $definition) {
            register_post_meta(
                SearchRegister::POST_TYPE,
                $key,
                [
                    'type'              => self::resolveRestType($definition),
                    'single'            => true,
                    'show_in_rest'      => true,
                    'auth_callback'     => [self::class, 'canEditMeta'],
                    'sanitize_callback' => self::buildSanitizer($definition),
                ]
            );
        }

        register_post_meta(
            SearchRegister::POST_TYPE,
            self::AGREEMENTS_META_KEY,
            [
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                'auth_callback'     => [self::class, 'canEditMeta'],
                'sanitize_callback' => static fn($value) => self::sanitizeAgreementRelations($value),
            ]
        );
    }

    private static function resolveRestType(array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'boolean'        => 'boolean',
            'decimal'        => 'number',
            'integer',
            'user'           => 'integer',
            'set'            => 'array',
            default          => 'string',
        };
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'estate-office-search-crm',
            __('Informacje CRM', 'estate-office'),
            [self::class, 'renderCrmBox'],
            SearchRegister::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'estate-office-search-criteria',
            __('Kryteria poszukiwania', 'estate-office'),
            [self::class, 'renderCriteriaBox'],
            SearchRegister::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'estate-office-search-preferences',
            __('Preferencje dodatkowe', 'estate-office'),
            [self::class, 'renderPreferencesBox'],
            SearchRegister::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function renderCrmBox(WP_Post $post): void
    {
        wp_nonce_field('estate_office_search_meta', 'estate_office_search_meta_nonce');

        $reference       = esc_attr(get_post_meta($post->ID, 'estate_search_reference', true));
        $transactionType = esc_attr(get_post_meta($post->ID, 'estate_search_transaction_type', true));
        $propertyType    = esc_attr(get_post_meta($post->ID, 'estate_search_property_type', true));
        $manager         = (int) get_post_meta($post->ID, 'estate_search_manager', true);

        echo '<p>';
        echo '<label for="estate_search_reference"><strong>' . esc_html__('Numer poszukiwania', 'estate-office') . '</strong></label>';
        printf('<input type="text" id="estate_search_reference" name="estate_search_reference" value="%s" class="widefat" />', $reference);
        echo '<span class="description">' . esc_html__('Wewnętrzny identyfikator zgłoszenia poszukiwania.', 'estate-office') . '</span>';
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_search_transaction_type"><strong>' . esc_html__('Typ transakcji', 'estate-office') . '</strong></label>';
        echo '<select id="estate_search_transaction_type" name="estate_search_transaction_type" class="widefat">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::TRANSACTION_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($transactionType, $value, false), esc_html($label));
        }
        echo '</select>';
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_search_property_type"><strong>' . esc_html__('Rodzaj nieruchomości', 'estate-office') . '</strong></label>';
        echo '<select id="estate_search_property_type" name="estate_search_property_type" class="widefat">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::PROPERTY_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($propertyType, $value, false), esc_html($label));
        }
        echo '</select>';
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_search_manager"><strong>' . esc_html__('Opiekun', 'estate-office') . '</strong></label>';
        wp_dropdown_users([
            'name'              => 'estate_search_manager',
            'id'                => 'estate_search_manager',
            'selected'          => $manager,
            'role__in'          => ['estate_agent', 'administrator', 'editor'],
            'show_option_none'  => __('— Nie przypisano —', 'estate-office'),
            'option_none_value' => '',
            'include_selected'  => true,
        ]);
        echo '<span class="description">' . esc_html__('Osoba odpowiedzialna za obsługę klienta poszukującego.', 'estate-office') . '</span>';
        echo '</p>';

        self::renderAgreementsSummary($post);
    }

    private static function renderAgreementsSummary(WP_Post $post): void
    {
        $agreements = self::sanitizeAgreementRelations(get_post_meta($post->ID, self::AGREEMENTS_META_KEY, true));

        echo '<hr />';
        echo '<strong>' . esc_html__('Powiązane umowy', 'estate-office') . '</strong>';

        if (empty($agreements)) {
            echo '<p class="description">' . esc_html__('Brak przypisanych umów. Powiąż poszukiwanie podczas edycji umowy.', 'estate-office') . '</p>';

            return;
        }

        echo '<ul class="estate-office-related-agreements">';

        foreach ($agreements as $agreementId) {
            $label = self::formatAgreementLabel($agreementId);

            if ($label === '') {
                continue;
            }

            $editLink = get_edit_post_link($agreementId);

            if ($editLink) {
                printf('<li><a href="%s">%s</a></li>', esc_url($editLink), esc_html($label));
            } else {
                printf('<li>%s</li>', esc_html($label));
            }
        }

        echo '</ul>';
    }

    private static function formatAgreementLabel(int $agreementId): string
    {
        if ($agreementId <= 0) {
            return '';
        }

        $agreement = get_post($agreementId);

        if (!$agreement || $agreement->post_type !== AgreementRegister::POST_TYPE) {
            return '';
        }

        $number = trim((string) get_post_meta($agreementId, 'estate_agreement_number', true));

        if ($number !== '') {
            return $number;
        }

        $title = trim((string) get_the_title($agreement));

        if ($title !== '') {
            return $title;
        }

        return sprintf(__('Umowa #%d', 'estate-office'), $agreementId);
    }

    private static function sanitizeAgreementRelations($value): array
    {
        if (!is_array($value)) {
            $value = $value === '' ? [] : [$value];
        }

        $ids = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                continue;
            }

            $id = (int) $item;

            if ($id <= 0) {
                continue;
            }

            $agreement = get_post($id);

            if (!$agreement || $agreement->post_type !== AgreementRegister::POST_TYPE) {
                continue;
            }

            $ids[] = $agreement->ID;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    public static function renderCriteriaBox(WP_Post $post): void
    {
        $priceMin   = esc_attr(get_post_meta($post->ID, 'estate_search_price_min', true));
        $priceMax   = esc_attr(get_post_meta($post->ID, 'estate_search_price_max', true));
        $areaMin    = esc_attr(get_post_meta($post->ID, 'estate_search_area_min', true));
        $areaMax    = esc_attr(get_post_meta($post->ID, 'estate_search_area_max', true));
        $roomsMin   = esc_attr(get_post_meta($post->ID, 'estate_search_rooms_min', true));
        $roomsMax   = esc_attr(get_post_meta($post->ID, 'estate_search_rooms_max', true));
        $location   = esc_attr(get_post_meta($post->ID, 'estate_search_location', true));
        $notes      = esc_textarea(get_post_meta($post->ID, 'estate_search_description', true));

        echo '<table class="form-table estate-office-meta-table">';
        echo '<tr><th><label for="estate_search_price_min">' . esc_html__('Budżet od', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_search_price_min" name="estate_search_price_min" value="%s" class="regular-text" /> <span class="suffix">PLN</span></td></tr>', $priceMin);

        echo '<tr><th><label for="estate_search_price_max">' . esc_html__('Budżet do', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_search_price_max" name="estate_search_price_max" value="%s" class="regular-text" /> <span class="suffix">PLN</span></td></tr>', $priceMax);

        echo '<tr><th><label for="estate_search_area_min">' . esc_html__('Metraż od (m²)', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_search_area_min" name="estate_search_area_min" value="%s" class="regular-text" /></td></tr>', $areaMin);

        echo '<tr><th><label for="estate_search_area_max">' . esc_html__('Metraż do (m²)', 'estate-office') . '</label></th>';
        printf('<td><input type="number" step="0.01" min="0" id="estate_search_area_max" name="estate_search_area_max" value="%s" class="regular-text" /></td></tr>', $areaMax);

        echo '<tr><th><label for="estate_search_rooms_min">' . esc_html__('Liczba pokoi od', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_search_rooms_min" name="estate_search_rooms_min" value="%s" class="small-text" /></td></tr>', $roomsMin);

        echo '<tr><th><label for="estate_search_rooms_max">' . esc_html__('Liczba pokoi do', 'estate-office') . '</label></th>';
        printf('<td><input type="number" min="0" id="estate_search_rooms_max" name="estate_search_rooms_max" value="%s" class="small-text" /></td></tr>', $roomsMax);

        echo '<tr><th><label for="estate_search_location">' . esc_html__('Preferowana lokalizacja', 'estate-office') . '</label></th>';
        printf('<td><input type="text" id="estate_search_location" name="estate_search_location" value="%s" class="regular-text" /></td></tr>', $location);

        echo '<tr><th><label for="estate_search_description">' . esc_html__('Opis poszukiwania', 'estate-office') . '</label></th>';
        printf('<td><textarea id="estate_search_description" name="estate_search_description" rows="4" class="large-text">%s</textarea></td></tr>', $notes);
        echo '</table>';
    }

    public static function renderPreferencesBox(WP_Post $post): void
    {
        $finish      = esc_attr(get_post_meta($post->ID, 'estate_search_building_finish', true));
        $exposure    = (array) get_post_meta($post->ID, 'estate_search_exposure', true);
        $views       = (array) get_post_meta($post->ID, 'estate_search_views', true);
        $attic       = (bool) get_post_meta($post->ID, 'estate_search_attic', true);
        $multilevel  = (bool) get_post_meta($post->ID, 'estate_search_multilevel', true);
        $layout      = (array) get_post_meta($post->ID, 'estate_search_layout', true);
        $kitchen     = esc_attr(get_post_meta($post->ID, 'estate_search_kitchen', true));
        $heating     = esc_attr(get_post_meta($post->ID, 'estate_search_heating', true));
        $water       = esc_attr(get_post_meta($post->ID, 'estate_search_water', true));
        $sewer       = esc_attr(get_post_meta($post->ID, 'estate_search_sewer', true));
        $gas         = (bool) get_post_meta($post->ID, 'estate_search_gas', true);
        $amenities   = (array) get_post_meta($post->ID, 'estate_search_amenities', true);
        $furnishing  = esc_attr(get_post_meta($post->ID, 'estate_search_furnishing', true));
        $equipment   = (array) get_post_meta($post->ID, 'estate_search_equipment', true);
        $extraSpaces = (array) get_post_meta($post->ID, 'estate_search_extra_spaces', true);

        echo '<table class="form-table estate-office-meta-table">';

        echo '<tr><th><label for="estate_search_building_finish">' . esc_html__('Stan wykończenia', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_search_building_finish" name="estate_search_building_finish">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::FINISHES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($finish, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th>' . esc_html__('Ekspozycja', 'estate-office') . '</th><td>';
        self::renderCheckboxGroup('estate_search_exposure', self::EXPOSURES, $exposure);
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Widok', 'estate-office') . '</th><td>';
        self::renderCheckboxGroup('estate_search_views', self::VIEWS, $views);
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Poddasze / Wielopoziomowe', 'estate-office') . '</th><td>';
        printf('<label><input type="checkbox" name="estate_search_attic" value="1" %s /> %s</label><br />', checked($attic, true, false), esc_html__('Z poddaszem', 'estate-office'));
        printf('<label><input type="checkbox" name="estate_search_multilevel" value="1" %s /> %s</label>', checked($multilevel, true, false), esc_html__('Układ wielopoziomowy', 'estate-office'));
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Rozkład pomieszczeń', 'estate-office') . '</th><td>';
        self::renderCheckboxGroup('estate_search_layout', self::LAYOUTS, $layout);
        echo '</td></tr>';

        echo '<tr><th><label for="estate_search_kitchen">' . esc_html__('Kuchnia', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_search_kitchen" name="estate_search_kitchen">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::KITCHEN_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($kitchen, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="estate_search_heating">' . esc_html__('Ogrzewanie', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_search_heating" name="estate_search_heating">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::HEATING_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($heating, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="estate_search_water">' . esc_html__('Woda', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_search_water" name="estate_search_water">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::WATER_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($water, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="estate_search_sewer">' . esc_html__('Kanalizacja', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_search_sewer" name="estate_search_sewer">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::SEWER_TYPES as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($sewer, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th>' . esc_html__('Dostęp do gazu', 'estate-office') . '</th>';
        printf('<td><label><input type="checkbox" name="estate_search_gas" value="1" %s /> %s</label></td></tr>', checked($gas, true, false), esc_html__('Wymagany', 'estate-office'));

        echo '<tr><th>' . esc_html__('Udogodnienia', 'estate-office') . '</th><td>';
        self::renderCheckboxGroup('estate_search_amenities', self::AMENITIES, $amenities);
        echo '</td></tr>';

        echo '<tr><th><label for="estate_search_furnishing">' . esc_html__('Umeblowanie', 'estate-office') . '</label></th>';
        echo '<td><select id="estate_search_furnishing" name="estate_search_furnishing">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::FURNISHING as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($furnishing, $value, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th>' . esc_html__('Wyposażenie', 'estate-office') . '</th><td>';
        self::renderCheckboxGroup('estate_search_equipment', self::EQUIPMENT, $equipment);
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Powierzchnie dodatkowe', 'estate-office') . '</th><td>';
        self::renderCheckboxGroup('estate_search_extra_spaces', self::EXTRA_SPACES, $extraSpaces);
        echo '</td></tr>';

        echo '</table>';
    }

    private static function renderCheckboxGroup(string $field, array $options, array $selected): void
    {
        $selected = array_map('strval', $selected);
        foreach ($options as $value => $label) {
            $isChecked = in_array((string) $value, $selected, true);
            printf(
                '<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label><br />',
                esc_attr($field),
                esc_attr((string) $value),
                checked($isChecked, true, false),
                esc_html((string) $label)
            );
        }
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== SearchRegister::POST_TYPE) {
            return;
        }

        if (!isset($_POST['estate_office_search_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST['estate_office_search_meta_nonce']));

        if (!wp_verify_nonce($nonce, 'estate_office_search_meta')) {
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
            $type = $definition['type'] ?? 'string';

            if ($type === 'boolean') {
                $values[$key] = self::sanitizeBoolean(isset($_POST[$key]) ? '1' : '0');
                continue;
            }

            if ($type === 'set') {
                $values[$key] = self::sanitizeSet($_POST[$key] ?? [], (array) ($definition['values'] ?? []));
                continue;
            }

            $raw = $_POST[$key] ?? '';
            if (is_array($raw)) {
                $raw = '';
            }
            $values[$key] = self::sanitizeValue(wp_unslash((string) $raw), $definition);
        }

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }
    }

    private static function persistMeta(int $postId, string $key, $value, array $definition): void
    {
        $type = $definition['type'] ?? 'string';

        if ($type === 'boolean') {
            update_post_meta($postId, $key, $value ? 1 : 0);
            return;
        }

        if ($type === 'user') {
            if ($value === '') {
                delete_post_meta($postId, $key);
                return;
            }

            update_post_meta($postId, $key, (int) $value);
            return;
        }

        if ($type === 'set') {
            if (empty($value)) {
                delete_post_meta($postId, $key);
                return;
            }

            update_post_meta($postId, $key, array_values($value));
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
        return match ($definition['type'] ?? 'string') {
            'decimal' => self::sanitizeDecimal($value, (int) ($definition['precision'] ?? 2)),
            'integer' => self::sanitizeInteger($value),
            'user'    => self::sanitizeUser($value),
            'textarea'=> self::sanitizeTextarea($value),
            'enum'    => self::sanitizeEnum($value, (array) ($definition['values'] ?? [])),
            default   => self::sanitizeLine($value),
        };
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

        if (!is_finite($float) || $float < 0) {
            return '';
        }

        return number_format($float, $precision, '.', '');
    }

    private static function sanitizeInteger(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^0-9]/', '', $value);

        if ($value === '') {
            return '';
        }

        return (string) abs((int) $value);
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

    private static function sanitizeSet($value, array $allowed): array
    {
        if (!is_array($value)) {
            if ($value === null || $value === '') {
                return [];
            }

            $value = [(string) $value];
        }

        $sanitized = [];
        foreach ($value as $item) {
            $key = sanitize_key((string) $item);
            if (array_key_exists($key, $allowed)) {
                $sanitized[$key] = $key;
            }
        }

        return array_values($sanitized);
    }

    private static function sanitizeUser(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $userId = absint($value);

        if ($userId <= 0 || !get_user_by('id', $userId)) {
            return '';
        }

        return (string) $userId;
    }

    private static function buildSanitizer(array $definition): callable
    {
        return static function ($value) use ($definition) {
            $type = $definition['type'] ?? 'string';

            if ($type === 'boolean') {
                $value = is_scalar($value) ? (string) $value : '0';
                return SearchMeta::sanitizeBoolean($value);
            }

            if ($type === 'set') {
                return SearchMeta::sanitizeSet($value, (array) ($definition['values'] ?? []));
            }

            $value = is_scalar($value) ? (string) $value : '';

            return SearchMeta::sanitizeValue($value, $definition);
        };
    }

    public static function canEditMeta(bool $allowed, string $metaKey, int $postId): bool
    {
        unset($allowed, $metaKey);

        return current_user_can('edit_post', $postId);
    }
}
