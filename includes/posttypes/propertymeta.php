<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use EstateOffice\Settings\GeneralSettings;
use WP_Post;

use function absint;
use function checked;
use function current_time;
use function delete_post_meta;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function get_attached_file;
use function get_current_screen;
use function get_edit_post_link;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_mime_type;
use function get_posts;
use function get_the_title;
use function get_user_by;
use function plugins_url;
use function rawurlencode;
use function time;
use function trailingslashit;
use function update_post_meta;
use function wp_check_filetype;
use function wp_clear_scheduled_hook;
use function wp_dropdown_users;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_attachment_image_url;
use function wp_get_attachment_metadata;
use function wp_get_attachment_url;
use function wp_list_pluck;
use function wp_localize_script;
use function wp_next_scheduled;
use function wp_register_script;
use function wp_register_style;
use function wp_schedule_event;
use function wp_script_is;
use function wp_unslash;
use function wp_update_attachment_metadata;
use function wp_upload_dir;

use const DAY_IN_SECONDS;

defined('ABSPATH') || exit;

final class PropertyMeta
{
    public const AGREEMENTS_META_KEY = 'estate_property_agreements';
    private const DYNAMIC_FIELDS_META_KEY = 'estate_property_dynamic_fields';
    private const NEW_OFFER_EXPIRY_META_KEY = 'estate_property_new_offer_expires';
    private const NEW_OFFER_CRON_HOOK = 'estate_office_expire_new_offer_flags';
    private const NEW_OFFER_DURATION = 7 * DAY_IN_SECONDS;
    private const WATERMARK_META_KEY = '_estate_office_watermark_applied';
    private const SUPPORTED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const SUPPORTED_WATERMARK_MIMES = ['image/png'];

    private const META_FIELDS = [
        'estate_property_reference'          => ['type' => 'string'],
        'estate_property_manager'            => ['type' => 'user'],
        'estate_property_street'              => ['type' => 'string'],
        'estate_property_number'              => ['type' => 'string'],
        'estate_property_unit'                => ['type' => 'string'],
        'estate_property_postal_code'         => ['type' => 'string'],
        'estate_property_district'            => ['type' => 'string'],
        'estate_property_city'                => ['type' => 'string'],
        'estate_property_county'              => ['type' => 'string'],
        'estate_property_precinct'            => ['type' => 'string'],
        'estate_property_plot_number'         => ['type' => 'string'],
        'estate_property_latitude'            => ['type' => 'decimal', 'precision' => 8],
        'estate_property_longitude'           => ['type' => 'decimal', 'precision' => 8],
        'estate_property_map_address'         => ['type' => 'string'],
        'estate_property_map_place_id'        => ['type' => 'string'],
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
        'estate_property_flag_new_offer'      => ['type' => 'boolean'],
        'estate_property_flag_exclusive'      => ['type' => 'boolean'],
        'estate_property_flag_sold'           => ['type' => 'boolean'],
        'estate_property_flag_rented'         => ['type' => 'boolean'],
        'estate_property_flag_price_change'   => ['type' => 'boolean'],
        'estate_property_flag_no_commission'  => ['type' => 'boolean'],
        'estate_property_flag_mls'            => ['type' => 'boolean'],
        'estate_property_flag_premium'        => ['type' => 'boolean'],
        'estate_property_flag_export_www'     => ['type' => 'boolean'],
        'estate_property_flag_export_portals' => ['type' => 'boolean'],
        'estate_property_gallery'             => ['type' => 'array', 'items' => 'attachment'],
        'estate_property_floor_plan_2d'       => ['type' => 'integer'],
        'estate_property_floor_plan_3d'       => ['type' => 'integer'],
        'estate_property_video_url'           => ['type' => 'url'],
        'estate_property_virtual_tour_url'    => ['type' => 'url'],
        self::NEW_OFFER_EXPIRY_META_KEY       => ['type' => 'integer'],
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
        add_action('init', [self::class, 'scheduleNewOfferCleanup']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . PropertyRegister::POST_TYPE, [self::class, 'save']);
        add_action(self::NEW_OFFER_CRON_HOOK, [self::class, 'expireNewOfferFlags']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function registerMeta(): void
    {
        foreach (self::META_FIELDS as $key => $definition) {
            if (($definition['type'] ?? '') === 'array') {
                register_post_meta(
                    PropertyRegister::POST_TYPE,
                    $key,
                    [
                        'type'              => 'array',
                        'single'            => true,
                        'show_in_rest'      => [
                            'schema' => self::buildArraySchema($definition),
                        ],
                        'auth_callback'     => [self::class, 'canEditMeta'],
                        'sanitize_callback' => static fn($value) => self::sanitizeArrayMeta($value, $definition),
                    ]
                );

                continue;
            }

            $restType = self::resolveRestType($definition);

            register_post_meta(
                PropertyRegister::POST_TYPE,
                $key,
                [
                    'type'              => $restType,
                    'single'            => true,
                    'show_in_rest'      => true,
                    'auth_callback'     => [self::class, 'canEditMeta'],
                    'sanitize_callback' => self::buildSanitizer($definition),
                ]
            );
        }

        register_post_meta(
            PropertyRegister::POST_TYPE,
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

        register_post_meta(
            PropertyRegister::POST_TYPE,
            self::DYNAMIC_FIELDS_META_KEY,
            [
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => [
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'auth_callback'     => [self::class, 'canEditMeta'],
                'sanitize_callback' => [self::class, 'sanitizeDynamicFields'],
            ]
        );
    }

    private static function buildArraySchema(array $definition): array
    {
        $itemType = 'string';

        if (($definition['items'] ?? '') === 'attachment') {
            $itemType = 'integer';
        }

        return [
            'type'  => 'array',
            'items' => [
                'type' => $itemType,
            ],
        ];
    }

    private static function resolveRestType(array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'boolean'                       => 'boolean',
            'decimal'                       => 'number',
            'integer', 'signed_integer',
            'year', 'user'                  => 'integer',
            default                         => 'string',
        };
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'estate-office-property-crm',
            __('Informacje CRM', 'estate-office'),
            [self::class, 'renderCrmBox'],
            PropertyRegister::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'estate-office-property-flags',
            __('Znaczniki i eksport', 'estate-office'),
            [self::class, 'renderFlagsBox'],
            PropertyRegister::POST_TYPE,
            'side',
            'default'
        );

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

        if (!empty(GeneralSettings::getPropertyDynamicFields())) {
            add_meta_box(
                'estate-office-property-dynamic',
                __('Pola dodatkowe', 'estate-office'),
                [self::class, 'renderDynamicFieldsBox'],
                PropertyRegister::POST_TYPE,
                'normal',
                'default'
            );
        }

        add_meta_box(
            'estate-office-property-media',
            __('Galeria i multimedia', 'estate-office'),
            [self::class, 'renderMediaBox'],
            PropertyRegister::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function renderCrmBox(WP_Post $post): void
    {
        wp_nonce_field('estate_office_property_meta', 'estate_office_property_meta_nonce');

        $reference = esc_attr(get_post_meta($post->ID, 'estate_property_reference', true));
        $manager   = (int) get_post_meta($post->ID, 'estate_property_manager', true);

        echo '<p>';
        echo '<label for="estate_property_reference"><strong>' . esc_html__('Numer oferty', 'estate-office') . '</strong></label>';
        printf('<input type="text" id="estate_property_reference" name="estate_property_reference" value="%s" class="widefat" />', $reference);
        echo '<span class="description">' . esc_html__('Unikalny identyfikator widoczny w listach CRM.', 'estate-office') . '</span>';
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_property_manager"><strong>' . esc_html__('Opiekun', 'estate-office') . '</strong></label>';
        wp_dropdown_users([
            'name'              => 'estate_property_manager',
            'id'                => 'estate_property_manager',
            'selected'          => $manager,
            'role__in'          => ['estate_agent', 'administrator', 'editor'],
            'show_option_none'  => __('— Nie przypisano —', 'estate-office'),
            'option_none_value' => '',
            'include_selected'  => true,
        ]);
        echo '<span class="description">' . esc_html__('Wybierz agenta odpowiedzialnego za nieruchomość.', 'estate-office') . '</span>';
        echo '</p>';

        self::renderAgreementsSummary($post);
    }

    public static function renderFlagsBox(WP_Post $post): void
    {
        $definitions = self::getFlagDefinitions();
        $groups      = [
            'flag'   => [],
            'export' => [],
        ];

        foreach ($definitions as $key => $definition) {
            $group = $definition['group'] ?? 'flag';
            $groups[$group][$key] = $definition;
        }

        echo '<div class="estate-office-property-flags">';
        self::renderFlagGroup($post, $groups['flag'], esc_html__('Znaczniki', 'estate-office'));
        self::renderFlagGroup($post, $groups['export'], esc_html__('Eksport', 'estate-office'));
        echo '</div>';
    }

    /**
     * @param array<string,array{label:string,description:string,badge:string,group:string}> $definitions
     */
    private static function renderFlagGroup(WP_Post $post, array $definitions, string $legend): void
    {
        if (empty($definitions)) {
            return;
        }

        echo '<fieldset class="estate-office-property-flags__group">';
        echo '<legend><strong>' . esc_html($legend) . '</strong></legend>';

        foreach ($definitions as $key => $definition) {
            $isChecked = (bool) get_post_meta($post->ID, $key, true);
            echo '<label class="estate-office-property-flag">';
            printf(
                '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s />',
                esc_attr($key),
                checked($isChecked, true, false)
            );
            echo '<span class="estate-office-property-flag__label">' . esc_html($definition['label']) . '</span>';
            if ($definition['description'] !== '') {
                echo '<span class="description">' . esc_html($definition['description']) . '</span>';
            }
            echo '</label>';
        }

        echo '</fieldset>';
    }

    /**
     * @return array<string,array{label:string,description:string,badge:string,group:string}>
     */
    public static function getFlagDefinitions(): array
    {
        return [
            'estate_property_flag_new_offer' => [
                'label'       => __('Nowa oferta', 'estate-office'),
                'description' => __('Naklejka i hashtag oferty. Status wygasa automatycznie po 7 dniach.', 'estate-office'),
                'badge'       => 'new',
                'group'       => 'flag',
            ],
            'estate_property_flag_exclusive' => [
                'label'       => __('Wyłączność', 'estate-office'),
                'description' => __('Oznacza umowę na wyłączność i wyróżnia ofertę w CRM.', 'estate-office'),
                'badge'       => 'exclusive',
                'group'       => 'flag',
            ],
            'estate_property_flag_sold' => [
                'label'       => __('Sprzedane', 'estate-office'),
                'description' => __('Naklejka i hashtag dostępne dla transakcji sprzedaży.', 'estate-office'),
                'badge'       => 'sold',
                'group'       => 'flag',
            ],
            'estate_property_flag_rented' => [
                'label'       => __('Wynajęte', 'estate-office'),
                'description' => __('Naklejka i hashtag dostępne dla transakcji wynajmu.', 'estate-office'),
                'badge'       => 'rented',
                'group'       => 'flag',
            ],
            'estate_property_flag_price_change' => [
                'label'       => __('Nowa cena', 'estate-office'),
                'description' => __('Podkreśla niedawną zmianę ceny i dodaje hashtag promocji.', 'estate-office'),
                'badge'       => 'price',
                'group'       => 'flag',
            ],
            'estate_property_flag_no_commission' => [
                'label'       => __('Bez prowizji', 'estate-office'),
                'description' => __('Informuje klientów o braku prowizji po stronie kupującego.', 'estate-office'),
                'badge'       => 'commission',
                'group'       => 'flag',
            ],
            'estate_property_flag_mls' => [
                'label'       => __('Oferta MLS', 'estate-office'),
                'description' => __('Podkreśla, że oferta jest udostępniona w systemie MLS.', 'estate-office'),
                'badge'       => 'mls',
                'group'       => 'flag',
            ],
            'estate_property_flag_premium' => [
                'label'       => __('Premium', 'estate-office'),
                'description' => __('Wyróżnia najbardziej ekskluzywne oferty w portfelu.', 'estate-office'),
                'badge'       => 'premium',
                'group'       => 'flag',
            ],
            'estate_property_flag_export_www' => [
                'label'       => __('Eksport na WWW', 'estate-office'),
                'description' => __('Publikuj nieruchomość jako stronę oferty na witrynie.', 'estate-office'),
                'badge'       => '',
                'group'       => 'export',
            ],
            'estate_property_flag_export_portals' => [
                'label'       => __('Eksport na portale', 'estate-office'),
                'description' => __('Funkcjonalność w przygotowaniu – oznacza ofertę do eksportu zewnętrznego.', 'estate-office'),
                'badge'       => '',
                'group'       => 'export',
            ],
        ];
    }

    public static function getLegalStatusLabel(string $status): string
    {
        return self::LEGAL_STATUSES[$status] ?? '';
    }

    public static function getPlotShapeLabel(string $shape): string
    {
        return self::PLOT_SHAPES[$shape] ?? '';
    }

    public static function getHouseTypeLabel(string $type): string
    {
        return self::HOUSE_TYPES[$type] ?? '';
    }

    private static function renderAgreementsSummary(WP_Post $post): void
    {
        $agreements = self::sanitizeAgreementRelations(get_post_meta($post->ID, self::AGREEMENTS_META_KEY, true));

        echo '<hr />';
        echo '<strong>' . esc_html__('Powiązane umowy', 'estate-office') . '</strong>';

        if (empty($agreements)) {
            echo '<p class="description">' . esc_html__('Brak przypisanych umów. Powiąż nieruchomość podczas edycji umowy.', 'estate-office') . '</p>';

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

    public static function renderAddressBox(WP_Post $post): void
    {
        $street      = esc_attr(get_post_meta($post->ID, 'estate_property_street', true));
        $number      = esc_attr(get_post_meta($post->ID, 'estate_property_number', true));
        $unit        = esc_attr(get_post_meta($post->ID, 'estate_property_unit', true));
        $postalCode  = esc_attr(get_post_meta($post->ID, 'estate_property_postal_code', true));
        $district    = esc_attr(get_post_meta($post->ID, 'estate_property_district', true));
        $city        = esc_attr(get_post_meta($post->ID, 'estate_property_city', true));
        $county      = esc_attr(get_post_meta($post->ID, 'estate_property_county', true));
        $precinct    = esc_attr(get_post_meta($post->ID, 'estate_property_precinct', true));
        $plotNumber  = esc_attr(get_post_meta($post->ID, 'estate_property_plot_number', true));
        $latitudeRaw  = (string) get_post_meta($post->ID, 'estate_property_latitude', true);
        $longitudeRaw = (string) get_post_meta($post->ID, 'estate_property_longitude', true);
        $mapAddress   = (string) get_post_meta($post->ID, 'estate_property_map_address', true);
        $placeId      = (string) get_post_meta($post->ID, 'estate_property_map_place_id', true);
        $latitude     = esc_attr($latitudeRaw);
        $longitude    = esc_attr($longitudeRaw);
        $placeAttr    = esc_attr($placeId);
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

        $displayAddress = $mapAddress !== '' ? esc_html($mapAddress) : esc_html__('Brak wybranej lokalizacji.', 'estate-office');
        $clearDisabled  = $latitudeRaw === '' || $longitudeRaw === '' ? ' disabled' : '';

        echo '<div class="estate-office-map-field" data-lat="' . $latitude . '" data-lng="' . $longitude . '" data-address="' . esc_attr($mapAddress) . '" data-place="' . $placeAttr . '" data-empty-label="' . esc_attr__('Brak wybranej lokalizacji.', 'estate-office') . '">';
        echo '<label for="estate_property_map_search" class="estate-office-map-label">' . esc_html__('Zaznacz lokalizację na mapie', 'estate-office') . '</label>';
        printf('<input type="search" id="estate_property_map_search" class="estate-office-map-search" placeholder="%s" autocomplete="off" />', esc_attr__('Wpisz adres nieruchomości…', 'estate-office'));
        echo '<p class="description">' . esc_html__('Wyszukaj adres lub kliknij na mapie, aby ustawić pinezkę. Współrzędne zostaną zapisane w metadanych nieruchomości.', 'estate-office') . '</p>';
        echo '<div class="estate-office-map-canvas" aria-label="' . esc_attr__('Mapa lokalizacji nieruchomości', 'estate-office') . '"></div>';
        echo '<div class="estate-office-map-footer">';
        echo '<span class="estate-office-map-address" aria-live="polite">' . $displayAddress . '</span>';
        echo '<button type="button" class="button-link estate-office-map-clear"' . $clearDisabled . '>' . esc_html__('Wyczyść lokalizację', 'estate-office') . '</button>';
        echo '</div>';
        echo '<p class="estate-office-map-status" aria-live="polite"></p>';
        echo '</div>';

        printf('<input type="hidden" id="estate_property_latitude" name="estate_property_latitude" value="%s" />', $latitude);
        printf('<input type="hidden" id="estate_property_longitude" name="estate_property_longitude" value="%s" />', $longitude);
        printf('<input type="hidden" id="estate_property_map_address" name="estate_property_map_address" value="%s" />', esc_attr($mapAddress));
        printf('<input type="hidden" id="estate_property_map_place_id" name="estate_property_map_place_id" value="%s" />', $placeAttr);
    }

    public static function enqueueAssets(string $hook = ''): void
    {
        unset($hook);

        $screen = get_current_screen();

        if (!$screen || PropertyRegister::POST_TYPE !== $screen->post_type) {
            return;
        }

        wp_register_style(
            'estate-office-property-meta',
            plugins_url('assets/css/property-meta.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_enqueue_style('estate-office-property-meta');

        wp_enqueue_media();

        $settings = get_option(GeneralSettings::OPTION);
        $apiKey   = is_array($settings) && !empty($settings['google_maps_api_key']) ? $settings['google_maps_api_key'] : '';

        $dependencies = [];

        if ($apiKey !== '') {
            if (!wp_script_is('estate-office-google-maps', 'registered')) {
                wp_register_script(
                    'estate-office-google-maps',
                    sprintf('https://maps.googleapis.com/maps/api/js?key=%s&libraries=places', rawurlencode($apiKey)),
                    [],
                    null,
                    true
                );
            }

            $dependencies[] = 'estate-office-google-maps';
        }

        wp_register_script(
            'estate-office-property-meta',
            plugins_url('assets/js/property-meta.js', ESTATE_OFFICE_PLUGIN_FILE),
            $dependencies,
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'estate-office-property-meta',
            'EstateOfficePropertyMap',
            [
                'apiKey' => $apiKey,
                'i18n'   => [
                    'noApiKey'        => esc_html__('Aby korzystać z mapy, uzupełnij klucz API Map Google w ustawieniach wtyczki.', 'estate-office'),
                    'markerTitle'     => esc_html__('Lokalizacja nieruchomości', 'estate-office'),
                    'searchPlaceholder'=> esc_html__('Wpisz adres nieruchomości…', 'estate-office'),
                    'applyLocation'   => esc_html__('Lokalizacja zaktualizowana.', 'estate-office'),
                    'cleared'         => esc_html__('Lokalizacja została usunięta.', 'estate-office'),
                    'geocodeError'    => esc_html__('Nie udało się pobrać adresu dla wybranych współrzędnych.', 'estate-office'),
                ],
                'media' => [
                    'galleryTitle'   => esc_html__('Wybierz zdjęcia nieruchomości', 'estate-office'),
                    'galleryButton'  => esc_html__('Dodaj do galerii', 'estate-office'),
                    'singleTitle'    => esc_html__('Wybierz obraz', 'estate-office'),
                    'singleButton'   => esc_html__('Użyj obrazu', 'estate-office'),
                    'remove'         => esc_html__('Usuń', 'estate-office'),
                    'emptyGallery'   => esc_html__('Nie dodano jeszcze zdjęć.', 'estate-office'),
                    'dragHint'       => esc_html__('Przeciągnij elementy, aby ustawić kolejność prezentacji. Pierwsze zdjęcie będzie wyróżnione w listach.', 'estate-office'),
                ],
            ]
        );

        if ($apiKey !== '') {
            wp_enqueue_script('estate-office-google-maps');
        }

        wp_enqueue_script('estate-office-property-meta');
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

    public static function renderDynamicFieldsBox(WP_Post $post): void
    {
        $definitions = GeneralSettings::getPropertyDynamicFields();

        if (empty($definitions)) {
            echo '<p class="description">' . esc_html__('Brak zdefiniowanych pól dodatkowych. Dodaj je w ustawieniach wtyczki.', 'estate-office') . '</p>';

            return;
        }

        $values = get_post_meta($post->ID, self::DYNAMIC_FIELDS_META_KEY, true);
        if (!is_array($values)) {
            $values = [];
        }

        echo '<table class="form-table estate-office-meta-table">';

        foreach ($definitions as $definition) {
            $key   = (string) $definition['key'];
            $label = (string) $definition['label'];
            $id    = 'estate_property_dynamic_' . $key;
            $name  = 'estate_property_dynamic[' . $key . ']';
            $value = isset($values[$key]) ? esc_attr((string) $values[$key]) : '';

            echo '<tr>';
            echo '<th><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th>';
            printf('<td><input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="off" /></td>', esc_attr($id), esc_attr($name), $value);
            echo '</tr>';
        }

        echo '</table>';
        echo '<p class="description">' . esc_html__('Zmodyfikuj listę pól w sekcji ustawień „Pola nieruchomości”.', 'estate-office') . '</p>';
    }

    public static function renderMediaBox(WP_Post $post): void
    {
        $gallery = get_post_meta($post->ID, 'estate_property_gallery', true);
        $gallery = is_array($gallery) ? array_values(array_filter(array_map('absint', $gallery))) : [];
        $watermarkId = self::getWatermarkAttachment();
        $hasWatermark = $watermarkId > 0;

        echo '<div class="estate-office-media-box">';
        echo '<p class="description">' . esc_html__('Dodaj multimedia oferty. Pierwsze zdjęcie będzie wykorzystywane jako główna miniatura.', 'estate-office') . '</p>';

        if ($hasWatermark) {
            echo '<p class="description status">' . esc_html__('Po zapisaniu wpisu na obrazy zostanie nałożony skonfigurowany znak wodny.', 'estate-office') . '</p>';
        } else {
            echo '<p class="description warning">' . esc_html__('Aby nakładać znak wodny, wgraj plik w ustawieniach wtyczki w sekcji Integracje.', 'estate-office') . '</p>';
        }

        $emptyLabel   = esc_attr__('Nie dodano jeszcze zdjęć.', 'estate-office');
        $removeLabel  = esc_attr__('Usuń', 'estate-office');
        $frameTitle   = esc_attr__('Galeria nieruchomości', 'estate-office');
        $frameButton  = esc_attr__('Dodaj do galerii', 'estate-office');

        echo '<div class="estate-office-gallery" data-input="estate_property_gallery" data-empty-label="' . $emptyLabel . '" data-remove-label="' . $removeLabel . '" data-frame-title="' . $frameTitle . '" data-frame-button="' . $frameButton . '">';
        $emptyClass = empty($gallery) ? '' : ' hidden';
        echo '<p class="estate-office-gallery-empty' . $emptyClass . '">' . esc_html__('Nie dodano jeszcze zdjęć.', 'estate-office') . '</p>';
        echo '<ul class="estate-office-gallery-list">';

        foreach ($gallery as $attachmentId) {
            $thumb = wp_get_attachment_image_url($attachmentId, 'medium');
            if (!$thumb) {
                $thumb = wp_get_attachment_image_url($attachmentId, 'thumbnail');
            }
            if (!$thumb) {
                $thumb = wp_get_attachment_url($attachmentId);
            }

            $thumbAttr = $thumb ? esc_url($thumb) : '';

            echo '<li class="estate-office-gallery-item" data-id="' . esc_attr((string) $attachmentId) . '" draggable="true">';

            if ($thumbAttr !== '') {
                echo '<div class="estate-office-gallery-thumb"><img src="' . $thumbAttr . '" alt="" /></div>';
            } else {
                echo '<div class="estate-office-gallery-thumb is-placeholder"><span>' . esc_html__('Brak podglądu', 'estate-office') . '</span></div>';
            }

            echo '<div class="estate-office-gallery-actions">';
            echo '<button type="button" class="button-link estate-office-gallery-remove">' . esc_html__('Usuń', 'estate-office') . '</button>';
            echo '</div>';
            printf('<input type="hidden" name="estate_property_gallery[]" value="%d" />', $attachmentId);
            echo '</li>';
        }

        echo '</ul>';
        echo '<p class="description reorder">' . esc_html__('Przeciągnij elementy, aby ustawić kolejność prezentacji. Pierwsze zdjęcie będzie wyróżnione w listach.', 'estate-office') . '</p>';
        echo '<button type="button" class="button estate-office-gallery-add">' . esc_html__('Dodaj zdjęcia', 'estate-office') . '</button>';
        echo '</div>';

        $floor2d = (int) get_post_meta($post->ID, 'estate_property_floor_plan_2d', true);
        $floor3d = (int) get_post_meta($post->ID, 'estate_property_floor_plan_3d', true);

        self::renderSingleMediaField(
            'estate_property_floor_plan_2d',
            $floor2d,
            __('Rzut 2D', 'estate-office'),
            __('Opcjonalny rzut 2D nieruchomości w formacie graficznym.', 'estate-office')
        );

        self::renderSingleMediaField(
            'estate_property_floor_plan_3d',
            $floor3d,
            __('Rzut 3D', 'estate-office'),
            __('Dodaj wizualizację 3D lub plan mieszkania w formacie graficznym.', 'estate-office')
        );

        $videoUrl       = esc_url(get_post_meta($post->ID, 'estate_property_video_url', true));
        $virtualTourUrl = esc_url(get_post_meta($post->ID, 'estate_property_virtual_tour_url', true));

        echo '<p class="estate-office-media-field">';
        echo '<label for="estate_property_video_url"><strong>' . esc_html__('Link do filmu', 'estate-office') . '</strong></label>';
        printf('<input type="url" id="estate_property_video_url" name="estate_property_video_url" value="%s" class="widefat" placeholder="https://" />', esc_attr($videoUrl));
        echo '<span class="description">' . esc_html__('Wklej adres filmu z YouTube lub innego hostingu wideo.', 'estate-office') . '</span>';
        echo '</p>';

        echo '<p class="estate-office-media-field">';
        echo '<label for="estate_property_virtual_tour_url"><strong>' . esc_html__('Link do wirtualnego spaceru', 'estate-office') . '</strong></label>';
        printf('<input type="url" id="estate_property_virtual_tour_url" name="estate_property_virtual_tour_url" value="%s" class="widefat" placeholder="https://" />', esc_attr($virtualTourUrl));
        echo '<span class="description">' . esc_html__('Podaj adres prezentacji 3D (Matterport, spacer 360 itp.).', 'estate-office') . '</span>';
        echo '</p>';

        echo '</div>';
    }

    private static function renderSingleMediaField(string $key, int $attachmentId, string $label, string $description): void
    {
        $image = $attachmentId > 0 ? wp_get_attachment_image_url($attachmentId, 'medium') : '';
        $frameTitle     = esc_attr(sprintf(__('Wybierz obraz – %s', 'estate-office'), $label));
        $frameButton    = esc_attr__('Użyj obrazu', 'estate-office');
        $placeholderTxt = esc_attr__('Brak wybranego pliku.', 'estate-office');

        echo '<div class="estate-office-single-media" data-input="' . esc_attr($key) . '" data-frame-title="' . $frameTitle . '" data-frame-button="' . $frameButton . '" data-placeholder="' . $placeholderTxt . '">';
        echo '<p class="estate-office-single-heading"><strong>' . esc_html($label) . '</strong></p>';
        echo '<div class="estate-office-single-preview">';
        if ($image) {
            echo '<img src="' . esc_url($image) . '" alt="" />';
        } else {
            echo '<span class="placeholder">' . esc_html__('Brak wybranego pliku.', 'estate-office') . '</span>';
        }
        echo '</div>';
        printf('<input type="hidden" name="%1$s" id="%1$s" value="%2$d" />', esc_attr($key), $attachmentId);
        echo '<div class="estate-office-single-actions">';
        echo '<button type="button" class="button estate-office-single-add">' . esc_html__('Wybierz obraz', 'estate-office') . '</button>';
        $disabled = $attachmentId > 0 ? '' : ' disabled';
        echo '<button type="button" class="button-link estate-office-single-remove"' . $disabled . '>' . esc_html__('Usuń', 'estate-office') . '</button>';
        echo '</div>';
        echo '<p class="description">' . esc_html($description) . '</p>';
        echo '</div>';
    }

    public static function prepareValues(array $source): array
    {
        $values = [];

        foreach (self::META_FIELDS as $key => $definition) {
            $type = $definition['type'] ?? 'string';

            if ($type === 'boolean') {
                $values[$key] = self::sanitizeBoolean(!empty($source[$key]) ? '1' : '0');
                continue;
            }

            if ($type === 'array') {
                $values[$key] = self::sanitizeArrayInput($source[$key] ?? [], $definition);
                continue;
            }

            $raw = $source[$key] ?? '';
            if (is_array($raw)) {
                $raw = '';
            }

            $values[$key] = self::sanitizeValue(wp_unslash(is_scalar($raw) ? (string) $raw : ''), $definition);
        }

        $price = $values['estate_property_price'] ?? '';
        $area  = $values['estate_property_area'] ?? '';

        if ($price !== '' && $area !== '' && (float) $area > 0.0) {
            $values['estate_property_price_per_sqm'] = number_format((float) $price / (float) $area, 2, '.', '');
        } else {
            $values['estate_property_price_per_sqm'] = '';
        }

        if (!empty($values['estate_property_flag_new_offer'])) {
            $values[self::NEW_OFFER_EXPIRY_META_KEY] = (string) ((int) current_time('timestamp') + self::NEW_OFFER_DURATION);
        } else {
            $values[self::NEW_OFFER_EXPIRY_META_KEY] = '';
        }

        return $values;
    }

    public static function prepareDynamicValues($raw): array
    {
        return self::sanitizeDynamicInput($raw);
    }

    public static function persistValues(int $postId, array $values, array $dynamicValues = []): void
    {
        $values = array_intersect_key($values, self::META_FIELDS);

        $attachmentsForWatermark = [];
        if (!empty($values['estate_property_gallery']) && is_array($values['estate_property_gallery'])) {
            $attachmentsForWatermark = array_map('intval', $values['estate_property_gallery']);
        }

        if (!empty($values['estate_property_floor_plan_2d'])) {
            $attachmentsForWatermark[] = (int) $values['estate_property_floor_plan_2d'];
        }

        if (!empty($values['estate_property_floor_plan_3d'])) {
            $attachmentsForWatermark[] = (int) $values['estate_property_floor_plan_3d'];
        }

        $attachmentsForWatermark = array_values(array_filter(array_unique($attachmentsForWatermark)));

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }

        self::persistDynamicFields($postId, is_array($dynamicValues) ? $dynamicValues : []);

        if (!empty($attachmentsForWatermark)) {
            self::maybeApplyWatermarks($attachmentsForWatermark);
        }
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

            if (($definition['type'] ?? '') === 'array') {
                $values[$key] = self::sanitizeArrayInput($_POST[$key] ?? [], $definition);
                continue;
            }

            $raw = $_POST[$key] ?? '';
            if (is_array($raw)) {
                $raw = '';
            }
            $values[$key] = self::sanitizeValue(wp_unslash((string) $raw), $definition);
        }

        $dynamicValues = self::sanitizeDynamicInput($_POST['estate_property_dynamic'] ?? []);

        $price = $values['estate_property_price'] ?? '';
        $area  = $values['estate_property_area'] ?? '';

        if ($price !== '' && $area !== '' && (float) $area > 0.0) {
            $values['estate_property_price_per_sqm'] = number_format((float) $price / (float) $area, 2, '.', '');
        }

        if (!empty($values['estate_property_flag_new_offer'])) {
            $values[self::NEW_OFFER_EXPIRY_META_KEY] = (string) ((int) current_time('timestamp') + self::NEW_OFFER_DURATION);
        } else {
            $values[self::NEW_OFFER_EXPIRY_META_KEY] = '';
        }

        $attachmentsForWatermark = $values['estate_property_gallery'] ?? [];
        if (!is_array($attachmentsForWatermark)) {
            $attachmentsForWatermark = [];
        }

        if (!empty($values['estate_property_floor_plan_2d'])) {
            $attachmentsForWatermark[] = (int) $values['estate_property_floor_plan_2d'];
        }

        if (!empty($values['estate_property_floor_plan_3d'])) {
            $attachmentsForWatermark[] = (int) $values['estate_property_floor_plan_3d'];
        }

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }

        self::persistDynamicFields($postId, $dynamicValues);

        self::maybeApplyWatermarks($attachmentsForWatermark);
    }

    public static function activate(): void
    {
        self::scheduleNewOfferCleanup();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::NEW_OFFER_CRON_HOOK);
    }

    public static function scheduleNewOfferCleanup(): void
    {
        if (!wp_next_scheduled(self::NEW_OFFER_CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::NEW_OFFER_CRON_HOOK);
        }
    }

    public static function expireNewOfferFlags(): void
    {
        $now   = (int) current_time('timestamp');
        $posts = get_posts([
            'post_type'      => PropertyRegister::POST_TYPE,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'nopaging'       => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'estate_property_flag_new_offer',
                    'value' => 1,
                ],
                [
                    'key'     => self::NEW_OFFER_EXPIRY_META_KEY,
                    'value'   => $now,
                    'type'    => 'NUMERIC',
                    'compare' => '<=',
                ],
            ],
        ]);

        foreach ($posts as $postId) {
            $id = (int) $postId;
            update_post_meta($id, 'estate_property_flag_new_offer', 0);
            delete_post_meta($id, self::NEW_OFFER_EXPIRY_META_KEY);
        }
    }

    private static function persistMeta(int $postId, string $key, $value, array $definition): void
    {
        if (($definition['type'] ?? '') === 'array') {
            $value = is_array($value) ? array_values(array_filter(array_map('absint', $value))) : [];

            if (empty($value)) {
                delete_post_meta($postId, $key);
                return;
            }

            update_post_meta($postId, $key, $value);
            return;
        }

        if (($definition['type'] ?? '') === 'boolean') {
            update_post_meta($postId, $key, $value ? 1 : 0);
            return;
        }

        if (($definition['type'] ?? '') === 'user') {
            if ($value === '') {
                delete_post_meta($postId, $key);
                return;
            }

            update_post_meta($postId, $key, (int) $value);
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
            case 'user':
                return self::sanitizeUser($value);
            case 'year':
                return self::sanitizeYear($value);
            case 'textarea':
                return self::sanitizeTextarea($value);
            case 'enum':
                return self::sanitizeEnum($value, (array) ($definition['values'] ?? []));
            case 'url':
                return self::sanitizeUrl($value);
            default:
                return self::sanitizeLine($value);
        }
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

    private static function sanitizeUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $sanitized = esc_url_raw($value, ['http', 'https']);

        return $sanitized ?: '';
    }

    private static function sanitizeArrayInput($value, array $definition): array
    {
        return self::sanitizeArray($value, $definition);
    }

    private static function sanitizeArrayMeta($value, array $definition): array
    {
        return self::sanitizeArray($value, $definition);
    }

    private static function sanitizeArray($value, array $definition): array
    {
        if (!is_array($value)) {
            return [];
        }

        $itemsType = $definition['items'] ?? 'string';
        $sanitized = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                continue;
            }

            $item = (string) $item;

            if ($itemsType === 'attachment') {
                $id = absint($item);
                if ($id > 0) {
                    $sanitized[] = $id;
                }
                continue;
            }

            $item = sanitize_text_field($item);

            if ($item !== '') {
                $sanitized[] = $item;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private static function getWatermarkAttachment(): int
    {
        $settings = get_option(GeneralSettings::OPTION);

        if (!is_array($settings) || empty($settings['watermark_attachment'])) {
            return 0;
        }

        $id = (int) $settings['watermark_attachment'];

        return $id > 0 ? $id : 0;
    }

    private static function maybeApplyWatermarks(array $attachments): void
    {
        $attachments = array_values(array_unique(array_filter(array_map('absint', $attachments))));

        if (empty($attachments)) {
            return;
        }

        $watermarkId = self::getWatermarkAttachment();

        if ($watermarkId <= 0) {
            return;
        }

        $watermarkPath = get_attached_file($watermarkId);

        if (!$watermarkPath || !file_exists($watermarkPath) || !is_readable($watermarkPath)) {
            return;
        }

        $filetype = wp_check_filetype($watermarkPath);
        $mime     = $filetype['type'] ?? '';

        if ($mime === '' || !in_array($mime, self::SUPPORTED_WATERMARK_MIMES, true)) {
            return;
        }

        foreach ($attachments as $attachmentId) {
            self::applyWatermark($attachmentId, $watermarkPath, $watermarkId);
        }
    }

    private static function applyWatermark(int $attachmentId, string $watermarkPath, int $watermarkId): void
    {
        if ($attachmentId <= 0) {
            return;
        }

        $applied = (int) get_post_meta($attachmentId, self::WATERMARK_META_KEY, true);

        if ($applied === $watermarkId) {
            return;
        }

        $filePath = get_attached_file($attachmentId);

        if (!$filePath || !file_exists($filePath) || !is_writable($filePath)) {
            return;
        }

        $mime = (string) get_post_mime_type($attachmentId);

        if (!in_array($mime, self::SUPPORTED_IMAGE_MIMES, true)) {
            return;
        }

        if (!self::applyWatermarkToFile($filePath, $watermarkPath, $mime)) {
            return;
        }

        $metadata = wp_get_attachment_metadata($attachmentId);

        if (is_array($metadata) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $uploadDir = wp_upload_dir();
            $baseDir   = isset($uploadDir['basedir']) ? trailingslashit($uploadDir['basedir']) : '';
            $relative  = '';

            if (!empty($metadata['file'])) {
                $directory = trim(dirname((string) $metadata['file']), '/.\\');
                if ($directory !== '') {
                    $relative = trailingslashit($directory);
                }
            }

            foreach ($metadata['sizes'] as $size) {
                $sizeFile = $size['file'] ?? '';
                if ($sizeFile === '') {
                    continue;
                }

                $sizePath = $baseDir . $relative . $sizeFile;

                if (!file_exists($sizePath) || !is_writable($sizePath)) {
                    continue;
                }

                self::applyWatermarkToFile($sizePath, $watermarkPath, $mime);
            }

            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        update_post_meta($attachmentId, self::WATERMARK_META_KEY, $watermarkId);
    }

    private static function applyWatermarkToFile(string $imagePath, string $watermarkPath, string $mime): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        if (!file_exists($imagePath) || !is_readable($imagePath) || !is_writable($imagePath)) {
            return false;
        }

        $image = self::createImageResource($imagePath, $mime);

        if (!$image) {
            return false;
        }

        $watermark = imagecreatefrompng($watermarkPath);

        if (!$watermark) {
            if (is_resource($image) || $image instanceof \GdImage) {
                imagedestroy($image);
            }
            return false;
        }

        imagealphablending($watermark, true);
        imagesavealpha($watermark, true);

        $imageWidth      = imagesx($image);
        $imageHeight     = imagesy($image);
        $watermarkWidth  = imagesx($watermark);
        $watermarkHeight = imagesy($watermark);

        if ($imageWidth <= 0 || $imageHeight <= 0 || $watermarkWidth <= 0 || $watermarkHeight <= 0) {
            imagedestroy($image);
            imagedestroy($watermark);
            return false;
        }

        $maxWidth  = max(1, (int) round($imageWidth * 0.35));
        $maxHeight = max(1, (int) round($imageHeight * 0.35));
        $ratio     = min($maxWidth / $watermarkWidth, $maxHeight / $watermarkHeight, 1.0);

        if ($ratio < 1.0) {
            $targetWidth  = max(1, (int) round($watermarkWidth * $ratio));
            $targetHeight = max(1, (int) round($watermarkHeight * $ratio));
            $resized      = imagecreatetruecolor($targetWidth, $targetHeight);

            if (!$resized) {
                imagedestroy($image);
                imagedestroy($watermark);
                return false;
            }

            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $watermark, 0, 0, 0, 0, $targetWidth, $targetHeight, $watermarkWidth, $watermarkHeight);
            imagedestroy($watermark);
            $watermark       = $resized;
            $watermarkWidth  = $targetWidth;
            $watermarkHeight = $targetHeight;
        }

        $margin = max(10, (int) round(min($imageWidth, $imageHeight) * 0.03));
        $destX  = max(0, $imageWidth - $watermarkWidth - $margin);
        $destY  = max(0, $imageHeight - $watermarkHeight - $margin);

        imagealphablending($image, true);
        imagesavealpha($image, true);
        imagecopy($image, $watermark, $destX, $destY, 0, 0, $watermarkWidth, $watermarkHeight);

        $saved = self::saveImageResource($image, $mime, $imagePath);

        imagedestroy($image);
        imagedestroy($watermark);

        return $saved;
    }

    private static function createImageResource(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            case 'image/jpeg':
            default:
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
        }
    }

    private static function saveImageResource($image, string $mime, string $path): bool
    {
        switch ($mime) {
            case 'image/png':
                if (!function_exists('imagepng')) {
                    return false;
                }

                imagesavealpha($image, true);

                return imagepng($image, $path);
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    return false;
                }

                return imagewebp($image, $path, 90);
            case 'image/jpeg':
            default:
                if (!function_exists('imagejpeg')) {
                    return false;
                }

                return imagejpeg($image, $path, 90);
        }
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

    private static function sanitizeDynamicInput($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $definitions = GeneralSettings::getPropertyDynamicFields();
        if (empty($definitions)) {
            return [];
        }

        $allowed = [];
        foreach ($definitions as $definition) {
            $allowed[(string) $definition['key']] = true;
        }

        $sanitized = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }

            if (is_array($value)) {
                $value = '';
            }

            $clean = self::sanitizeLine(wp_unslash((string) $value));

            if ($clean === '') {
                continue;
            }

            $sanitized[$key] = $clean;
        }

        return $sanitized;
    }

    private static function persistDynamicFields(int $postId, array $values): void
    {
        if (empty($values)) {
            delete_post_meta($postId, self::DYNAMIC_FIELDS_META_KEY);

            return;
        }

        update_post_meta($postId, self::DYNAMIC_FIELDS_META_KEY, $values);
    }

    public static function sanitizeDynamicFields($value, string $metaKey = '', string $objectType = ''): array
    {
        if (!is_array($value)) {
            return [];
        }

        $definitions = GeneralSettings::getPropertyDynamicFields();
        if (empty($definitions)) {
            return [];
        }

        $allowed = [];
        foreach ($definitions as $definition) {
            $allowed[(string) $definition['key']] = true;
        }

        $sanitized = [];

        foreach ($value as $key => $raw) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }

            if (is_array($raw)) {
                $raw = '';
            }

            $clean = self::sanitizeLine((string) $raw);

            if ($clean === '') {
                continue;
            }

            $sanitized[$key] = $clean;
        }

        return $sanitized;
    }

    /**
     * @return array<int,array{key:string,label:string,value:string}>
     */
    public static function getDynamicFieldValues(int $postId): array
    {
        $definitions = GeneralSettings::getPropertyDynamicFields();
        if (empty($definitions)) {
            return [];
        }

        $stored = get_post_meta($postId, self::DYNAMIC_FIELDS_META_KEY, true);
        if (!is_array($stored)) {
            $stored = [];
        }

        $values = [];

        foreach ($definitions as $definition) {
            $key = (string) $definition['key'];

            if (!isset($stored[$key])) {
                continue;
            }

            $value = trim((string) $stored[$key]);

            if ($value === '') {
                continue;
            }

            $values[] = [
                'key'   => $key,
                'label' => (string) $definition['label'],
                'value' => $value,
            ];
        }

        return $values;
    }

    public static function canEditMeta(bool $allowed, string $metaKey, int $postId): bool
    {
        unset($allowed, $metaKey);

        return current_user_can('edit_post', $postId);
    }
}
