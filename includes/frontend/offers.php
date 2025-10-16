<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\Frontend\AgentPublic;
use EstateOffice\Frontend\Maps;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\PropertyRegister;
use WP_Post;
use WP_Query;
use WP_Term;

use function absint;
use function add_action;
use function add_shortcode;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_permalink;
use function get_post_meta;
use function get_terms;
use function get_the_post_thumbnail_url;
use function get_the_terms;
use function get_user_by;
use function get_user_meta;
use function is_array;
use function is_scalar;
use function is_wp_error;
use function number_format_i18n;
use function plugins_url;
use function preg_replace;
use function remove_query_arg;
use function sanitize_email;
use function sanitize_html_class;
use function sanitize_title;
use function sanitize_text_field;
use function selected;
use function shortcode_atts;
use function sprintf;
use function trim;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_list_pluck;
use function wp_register_script;
use function wp_register_style;
use function wp_reset_postdata;
use function wp_json_encode;
use function wp_strip_all_tags;
use function wp_trim_words;
use function _n;
use function __;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit();

/**
 * Front-end property catalogue for public visitors.
 */
final class Offers
{
    private const SHORTCODE = 'estate_office_offers';
    private const DEFAULT_LIMIT = 12;

    private static bool $requiresInteractiveMap = false;

    /**
     * @var array<int,array<string,mixed>>
     */
    private static array $markers = [];

    /**
     * @var array<string,array{label:string,taxonomy:string,query_var:string,placeholder:string}>
     */
    private const FILTERS = [
        'transaction' => [
            'label'       => 'Typ transakcji',
            'taxonomy'    => 'estate_transaction_type',
            'query_var'   => 'estate_office_offer_transaction',
            'placeholder' => 'Wszystkie typy transakcji',
        ],
        'property' => [
            'label'       => 'Rodzaj nieruchomości',
            'taxonomy'    => 'estate_property_type',
            'query_var'   => 'estate_office_offer_property_type',
            'placeholder' => 'Wszystkie rodzaje nieruchomości',
        ],
        'city' => [
            'label'       => 'Miasto',
            'taxonomy'    => 'estate_city',
            'query_var'   => 'estate_office_offer_city',
            'placeholder' => 'Wszystkie miasta',
        ],
        'district' => [
            'label'       => 'Dzielnica',
            'taxonomy'    => 'estate_district',
            'query_var'   => 'estate_office_offer_district',
            'placeholder' => 'Wszystkie dzielnice',
        ],
    ];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerShortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);
    }

    public static function registerShortcode(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'renderShortcode']);
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            'estate-office-offers',
            plugins_url('assets/css/offers.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            'estate-office-offers',
            plugins_url('assets/js/offers.js', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public static function renderShortcode(array $attributes = []): string
    {
        $attributes = shortcode_atts([
            'limit'       => (string) self::DEFAULT_LIMIT,
            'show_legend' => 'yes',
        ], $attributes, self::SHORTCODE);

        $limit   = absint((string) $attributes['limit']);
        $filters = self::getFilterValues();

        $query = self::buildQuery($filters, $limit > 0 ? $limit : -1);

        self::$markers = [];
        self::$requiresInteractiveMap = false;

        $groups = [];
        if ($query->have_posts()) {
            $groups = self::groupByTransaction($query);
        }

        if (self::$requiresInteractiveMap) {
            Maps::enqueue();
        }

        wp_enqueue_style('estate-office-offers');
        wp_enqueue_script('estate-office-offers');

        ob_start();

        echo '<div class="estate-office-offers">';
        self::renderFilters($filters);

        if ($attributes['show_legend'] !== 'no') {
            self::renderLegend();
        }

        self::renderCatalogueMap();

        if (!empty($groups)) {
            foreach ($groups as $group) {
                self::renderGroup($group['label'], $group['cards']);
            }
        } else {
            echo '<div class="estate-office-offers__empty">' . esc_html__('Aktualnie brak ofert spełniających wybrane kryteria.', 'estate-office') . '</div>';
        }

        echo '</div>';

        wp_reset_postdata();

        return (string) ob_get_clean();
    }

    /**
     * @return array<string,string>
     */
    private static function getFilterValues(): array
    {
        $values = [];

        foreach (self::FILTERS as $key => $definition) {
            $param         = $definition['query_var'];
            $rawValue      = $_GET[$param] ?? '';
            $values[$key]  = is_string($rawValue) ? sanitize_title($rawValue) : '';
        }

        return $values;
    }

    /**
     * @param array<string,string> $filters
     */
    private static function renderFilters(array $filters): void
    {
        $filterKeys = array_map(static fn(array $definition): string => $definition['query_var'], self::FILTERS);
        $action     = remove_query_arg($filterKeys);

        echo '<form method="get" class="estate-office-offers__filters" action="' . esc_url($action) . '">';

        foreach (self::FILTERS as $key => $definition) {
            $options = self::getFilterOptions($definition['taxonomy']);
            $value   = $filters[$key] ?? '';

            echo '<label>';
            echo '<span>' . esc_html__($definition['label'], 'estate-office') . '</span>';
            echo '<select name="' . esc_attr($definition['query_var']) . '">';
            echo '<option value="">' . esc_html__($definition['placeholder'], 'estate-office') . '</option>';

            foreach ($options as $slug => $label) {
                echo '<option value="' . esc_attr($slug) . '"' . selected($value, $slug, false) . '>' . esc_html($label) . '</option>';
            }

            echo '</select>';
            echo '</label>';
        }

        self::renderPreservedQueryArgs();

        echo '<button type="submit">' . esc_html__('Filtruj oferty', 'estate-office') . '</button>';
        if (self::hasActiveFilters($filters)) {
            echo '<a class="estate-office-offers__reset" href="' . esc_url($action) . '">' . esc_html__('Wyczyść filtry', 'estate-office') . '</a>';
        }
        echo '</form>';
    }

    private static function renderPreservedQueryArgs(): void
    {
        $reserved = wp_list_pluck(self::FILTERS, 'query_var');

        foreach ($_GET as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $sanitized = sanitize_text_field((string) $value);
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($sanitized) . '" />';
        }
    }

    private static function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $value) {
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,string>
     */
    private static function getFilterOptions(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $options = [];

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $options[$term->slug] = $term->name;
        }

        return $options;
    }

    /**
     * @param array<string,string> $filters
     */
    private static function buildQuery(array $filters, int $limit): WP_Query
    {
        $taxQuery = [];

        foreach (self::FILTERS as $key => $definition) {
            $value = $filters[$key] ?? '';

            if ($value === '') {
                continue;
            }

            $taxQuery[] = [
                'taxonomy' => $definition['taxonomy'],
                'field'    => 'slug',
                'terms'    => $value,
            ];
        }

        $args = [
            'post_type'      => PropertyRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => 'estate_property_flag_export_www',
                    'value' => 1,
                ],
            ],
        ];

        if (!empty($taxQuery)) {
            $args['tax_query'] = $taxQuery;
        }

        return new WP_Query($args);
    }

    /**
     * @return array<int,array{label:string,cards:array<int,array<string,mixed>>}>
     */
    private static function groupByTransaction(WP_Query $query): array
    {
        $groups = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $term = self::getPrimaryTerm($post->ID, 'estate_transaction_type');
            $slug = $term instanceof WP_Term ? $term->slug : 'other';
            $name = $term instanceof WP_Term ? $term->name : __('Inne transakcje', 'estate-office');

            if (!isset($groups[$slug])) {
                $groups[$slug] = [
                    'label' => $name,
                    'cards' => [],
                ];
            }

            $groups[$slug]['cards'][] = self::buildPropertyCard($post, $term);
        }

        return $groups;
    }

    private static function renderLegend(): void
    {
        $definitions = PropertyMeta::getFlagDefinitions();

        echo '<div class="estate-office-offers__legend">';
        foreach ($definitions as $metaKey => $definition) {
            if (($definition['group'] ?? '') !== 'flag') {
                continue;
            }

            $modifier = isset($definition['badge']) ? trim((string) $definition['badge']) : '';
            $className = 'estate-office-offers__legend-item';
            if ($modifier !== '') {
                $className .= ' estate-office-offers__legend-item--' . sanitize_html_class($modifier);
            }

            echo '<div class="' . esc_attr($className) . '">';
            echo '<span></span>';
            echo '<strong>' . esc_html($definition['label']) . '</strong>';
            echo '</div>';
        }
        echo '</div>';
    }

    private static function renderCatalogueMap(): void
    {
        if (empty(self::$markers)) {
            return;
        }

        echo '<div class="estate-office-offers__map-wrapper">';

        if (self::$requiresInteractiveMap && Maps::hasApiKey()) {
            $markers = wp_json_encode(self::$markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

            if ($markers !== false) {
                echo '<div class="estate-office-offers__map">';
                echo '<div class="estate-office-map" data-markers="' . esc_attr($markers) . '" data-zoom="12"></div>';
                echo '</div>';
            } else {
                echo '<div class="estate-office-offers__map-notice">' . esc_html__('Nie udało się zainicjować mapy wyników.', 'estate-office') . '</div>';
            }
        } else {
            echo '<div class="estate-office-offers__map-notice">' . esc_html(Maps::getMissingApiMessage()) . '</div>';
        }

        echo '</div>';
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     */
    private static function renderGroup(string $label, array $cards): void
    {
        if (empty($cards)) {
            return;
        }

        echo '<section class="estate-office-offers__group">';
        echo '<h2 class="estate-office-offers__group-title">' . esc_html(sprintf(__('Oferty: %s', 'estate-office'), $label)) . '</h2>';
        echo '<div class="estate-office-offers__grid">';

        foreach ($cards as $card) {
            self::renderCard($card);
        }

        echo '</div>';
        echo '</section>';
    }

    /**
     * @param array<string,mixed> $card
     */
    private static function renderCard(array $card): void
    {
        echo '<article class="estate-office-offers__card">';

        echo '<div class="estate-office-offers__image">';
        if ($card['image']) {
            echo '<img src="' . esc_url((string) $card['image']) . '" alt="" loading="lazy" />';
        }

        if (!empty($card['badges'])) {
            echo '<div class="estate-office-offers__badges">';
            foreach ($card['badges'] as $badge) {
                $className = 'estate-office-offers__badge';
                if (!empty($badge['class'])) {
                    $className .= ' estate-office-offers__badge--' . sanitize_html_class((string) $badge['class']);
                }

                echo '<span class="' . esc_attr($className) . '">' . esc_html((string) $badge['label']) . '</span>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="estate-office-offers__content">';
        if ($card['price']) {
            echo '<div class="estate-office-offers__price">' . esc_html((string) $card['price']);
            if ($card['price_per_sqm']) {
                echo ' <small>(' . esc_html((string) $card['price_per_sqm']) . ')</small>';
            }
            echo '</div>';
        }

        echo '<h3 class="estate-office-offers__title">';
        if ($card['permalink']) {
            echo '<a href="' . esc_url((string) $card['permalink']) . '">';
        }
        echo esc_html((string) $card['title']);
        if ($card['permalink']) {
            echo '</a>';
        }
        echo '</h3>';

        if ($card['address']) {
            echo '<div class="estate-office-offers__summary-item">';
            echo '<strong>' . esc_html__('Adres', 'estate-office') . ':</strong> ' . esc_html((string) $card['address']);
            echo '</div>';
        }

        echo '<div class="estate-office-offers__summary">';
        if ($card['area']) {
            echo '<span class="estate-office-offers__summary-item"><strong>' . esc_html__('Metraż', 'estate-office') . ':</strong> ' . esc_html((string) $card['area']) . '</span>';
        }
        if ($card['rooms']) {
            echo '<span class="estate-office-offers__summary-item"><strong>' . esc_html__('Pokoje', 'estate-office') . ':</strong> ' . esc_html((string) $card['rooms']) . '</span>';
        }
        if ($card['transaction']) {
            echo '<span class="estate-office-offers__summary-item"><strong>' . esc_html__('Transakcja', 'estate-office') . ':</strong> ' . esc_html((string) $card['transaction']) . '</span>';
        }
        if ($card['property_type']) {
            echo '<span class="estate-office-offers__summary-item"><strong>' . esc_html__('Typ', 'estate-office') . ':</strong> ' . esc_html((string) $card['property_type']) . '</span>';
        }
        if ($card['reference']) {
            echo '<span class="estate-office-offers__summary-item"><strong>' . esc_html__('Nr oferty', 'estate-office') . ':</strong> ' . esc_html((string) $card['reference']) . '</span>';
        }
        echo '</div>';

        if (!empty($card['map']['has_coordinates'])) {
            $mapClasses = ['estate-office-offers__card-map'];
            if (empty($card['map']['interactive'])) {
                $mapClasses[] = 'estate-office-offers__card-map--iframe';
            }

            echo '<div class="' . esc_attr(implode(' ', $mapClasses)) . '">';

            if (!empty($card['map']['interactive'])) {
                echo '<div class="estate-office-map" data-lat="' . esc_attr(number_format((float) $card['map']['lat'], 6, '.', '')) . '" data-lng="' . esc_attr(number_format((float) $card['map']['lng'], 6, '.', '')) . '" data-title="' . esc_attr($card['map']['title']) . '" data-address="' . esc_attr($card['map']['address']) . '" data-url="' . esc_attr((string) $card['permalink']) . '" data-zoom="14"></div>';
            } elseif (!empty($card['map']['embed'])) {
                echo '<iframe src="' . esc_url((string) $card['map']['embed']) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="' . esc_attr($card['map']['title']) . '"></iframe>';
            }

            echo '</div>';
        }

        if ($card['excerpt']) {
            echo '<p class="estate-office-offers__excerpt">' . esc_html((string) $card['excerpt']) . '</p>';
        }

        if (!empty($card['manager'])) {
            $manager = $card['manager'];
            echo '<div class="estate-office-offers__meta">';
            if (!empty($manager['name'])) {
                echo '<div class="estate-office-offers__meta-item"><strong>' . esc_html__('Opiekun', 'estate-office') . '</strong><span>' . esc_html((string) $manager['name']) . '</span></div>';
            }
            if (!empty($manager['phone'])) {
                $rawPhone   = (string) $manager['phone'];
                $cleanPhone = preg_replace('/[^0-9+]/', '', $rawPhone);
                echo '<div class="estate-office-offers__meta-item"><strong>' . esc_html__('Telefon', 'estate-office') . '</strong>';
                if ($cleanPhone !== '') {
                    echo '<a href="' . esc_url('tel:' . $cleanPhone) . '">' . esc_html($rawPhone) . '</a>';
                } else {
                    echo '<span>' . esc_html($rawPhone) . '</span>';
                }
                echo '</div>';
            }
            if (!empty($manager['email'])) {
                $email = (string) $manager['email'];
                if ($email !== '') {
                    echo '<div class="estate-office-offers__meta-item"><strong>' . esc_html__('E-mail', 'estate-office') . '</strong><a href="' . esc_url('mailto:' . $email) . '">' . esc_html($email) . '</a></div>';
                }
            }
            if (!empty($manager['profile_url'])) {
                echo '<div class="estate-office-offers__meta-item"><a class="estate-office-offers__agent-link" href="' . esc_url((string) $manager['profile_url']) . '">' . esc_html__('Profil agenta', 'estate-office') . '</a></div>';
            }
            echo '</div>';
        }

        if ($card['permalink']) {
            echo '<div class="estate-office-offers__cta">';
            echo '<a href="' . esc_url((string) $card['permalink']) . '">' . esc_html__('Zobacz ofertę', 'estate-office') . '</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</article>';
    }

    private static function buildPropertyCard(WP_Post $post, ?WP_Term $transaction): array
    {
        $postId   = $post->ID;
        $permalink = get_permalink($postId);
        $image     = get_the_post_thumbnail_url($postId, 'large');
        $excerpt   = self::generateExcerpt($post);

        $managerId = (int) get_post_meta($postId, 'estate_property_manager', true);
        $manager   = $managerId > 0 ? get_user_by('id', $managerId) : null;

        $mapData = Maps::prepareMapData($postId);
        $mapTitle = trim($post->post_title);
        if ($mapTitle === '') {
            $mapTitle = sprintf(__('Nieruchomość #%d', 'estate-office'), $postId);
        }
        $mapData['title'] = $mapTitle;

        if (!empty($mapData['interactive'])) {
            self::$requiresInteractiveMap = true;
        }

        if (!empty($mapData['has_coordinates'])) {
            self::$markers[] = [
                'lat'     => $mapData['lat'],
                'lng'     => $mapData['lng'],
                'title'   => $mapData['title'],
                'address' => $mapData['address'],
                'url'     => $permalink,
                'zoom'    => 13,
            ];
        }

        $managerData = [];
        if ($manager) {
            $managerData['name']  = $manager->display_name;
            $managerData['email'] = sanitize_email($manager->user_email);
            $phone = (string) get_user_meta($manager->ID, AgentProfile::META_PHONE, true);
            if ($phone === '') {
                $phone = (string) get_user_meta($manager->ID, AgentProfile::META_OFFICE_PHONE, true);
            }
            $managerData['phone'] = $phone;
            $profileUrl = AgentPublic::getProfileUrl($manager->ID);
            if ($profileUrl !== '') {
                $managerData['profile_url'] = $profileUrl;
            }
        }

        return [
            'title'         => $post->post_title,
            'permalink'     => $permalink,
            'image'         => $image ?: '',
            'price'         => self::formatPrice((string) get_post_meta($postId, 'estate_property_price', true)),
            'price_per_sqm' => self::formatPricePerSqm((string) get_post_meta($postId, 'estate_property_price_per_sqm', true)),
            'area'          => self::formatArea((string) get_post_meta($postId, 'estate_property_area', true)),
            'rooms'         => self::formatRooms((string) get_post_meta($postId, 'estate_property_rooms', true)),
            'transaction'   => $transaction instanceof WP_Term ? $transaction->name : '',
            'property_type' => self::getPrimaryTermName($postId, 'estate_property_type'),
            'address'       => self::formatAddress($postId),
            'reference'     => (string) get_post_meta($postId, 'estate_property_reference', true),
            'badges'        => self::getBadges($postId),
            'excerpt'       => $excerpt,
            'manager'       => $managerData,
            'map'           => $mapData,
        ];
    }

    private static function formatPrice(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $amount = (float) $value;
        if ($amount <= 0) {
            return '';
        }

        return sprintf('%s zł', number_format_i18n($amount, 0));
    }

    private static function formatPricePerSqm(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $amount = (float) $value;
        if ($amount <= 0) {
            return '';
        }

        return sprintf('%s zł/m²', number_format_i18n($amount, 0));
    }

    private static function formatArea(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $area = (float) $value;
        if ($area <= 0) {
            return '';
        }

        return sprintf('%s m²', number_format_i18n($area, 2));
    }

    private static function formatRooms(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $rooms = (int) $value;
        if ($rooms <= 0) {
            return '';
        }

        return sprintf(_n('%d pokój', '%d pokoje', $rooms, 'estate-office'), $rooms);
    }

    private static function formatAddress(int $postId): string
    {
        $street = (string) get_post_meta($postId, 'estate_property_street', true);
        $number = (string) get_post_meta($postId, 'estate_property_number', true);
        $city   = (string) get_post_meta($postId, 'estate_property_city', true);
        $district = (string) get_post_meta($postId, 'estate_property_district', true);

        $parts = [];

        if ($street !== '') {
            $parts[] = trim($street . ' ' . $number);
        }

        if ($district !== '') {
            $parts[] = $district;
        }

        if ($city !== '') {
            $parts[] = $city;
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * @return array<int,array{label:string,class:string}>
     */
    private static function getBadges(int $postId): array
    {
        $definitions = PropertyMeta::getFlagDefinitions();
        $badges      = [];

        foreach ($definitions as $metaKey => $definition) {
            if (($definition['group'] ?? '') !== 'flag') {
                continue;
            }

            if ((int) get_post_meta($postId, $metaKey, true) !== 1) {
                continue;
            }

            $badges[] = [
                'label' => $definition['label'],
                'class' => $definition['badge'] ?? '',
            ];
        }

        return $badges;
    }

    private static function generateExcerpt(WP_Post $post): string
    {
        $source = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
        $source = wp_strip_all_tags($source);

        if ($source === '') {
            return '';
        }

        return wp_trim_words($source, 30, '…');
    }

    private static function getPrimaryTermName(int $postId, string $taxonomy): string
    {
        $term = self::getPrimaryTerm($postId, $taxonomy);

        return $term instanceof WP_Term ? $term->name : '';
    }

    private static function getPrimaryTerm(int $postId, string $taxonomy): ?WP_Term
    {
        $terms = get_the_terms($postId, $taxonomy);

        if (!is_array($terms) || empty($terms)) {
            return null;
        }

        $term = reset($terms);

        return $term instanceof WP_Term ? $term : null;
    }
}
