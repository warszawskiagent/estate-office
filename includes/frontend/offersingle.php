<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\Frontend\AgentPublic;
use EstateOffice\Frontend\Maps;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\Settings\GeneralSettings;
use WP_Post;
use WP_Query;

use function absint;
use function add_action;
use function add_filter;
use function esc_url;
use function __;
use function get_404_template;
use function get_attached_file;
use function get_avatar_url;
use function get_option;
use function get_permalink;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_post_type_archive_link;
use function get_queried_object;
use function get_the_terms;
use function get_the_title;
use function get_user_by;
use function get_user_meta;
use function is_singular;
use function is_wp_error;
use function number_format_i18n;
use function rawurlencode;
use function status_header;
use function trim;
use function wp_basename;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_attachment_image_url;
use function wp_get_attachment_url;
use function wp_get_post_terms;
use function wp_register_script;
use function wp_register_style;
use function wp_reset_postdata;
use function wp_strip_all_tags;

use const ESTATE_OFFICE_PLUGIN_DIR;
use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

/**
 * Provides a dedicated single template for exported property offers.
 */
final class OfferSingle
{
    private const TEMPLATE = 'templates/single-estate_property.php';

    /**
     * @var array<string,mixed>
     */
    private static array $context = [];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerAssets']);
        add_filter('template_include', [self::class, 'overrideTemplate']);
        add_filter('body_class', [self::class, 'filterBodyClass']);
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            'estate-office-offer-single',
            plugins_url('assets/css/offer-single.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            'estate-office-offer-single',
            plugins_url('assets/js/offer-single.js', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );
    }

    public static function overrideTemplate(string $template): string
    {
        if (! is_singular(PropertyRegister::POST_TYPE)) {
            return $template;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return $template;
        }

        if (! self::isExportEnabled($post->ID)) {
            global $wp_query;

            if ($wp_query instanceof WP_Query) {
                $wp_query->set_404();
            }

            status_header(404);
            $notFound = get_404_template();

            return $notFound ?: $template;
        }

        self::$context = self::buildContext($post);

        if (!empty(self::$context['map']['interactive'])) {
            Maps::enqueue();
        }

        wp_enqueue_style('estate-office-offer-single');

        if ((self::$context['gallery']['count'] ?? 0) > 1) {
            wp_enqueue_script('estate-office-offer-single');
        }

        return ESTATE_OFFICE_PLUGIN_DIR . self::TEMPLATE;
    }

    public static function filterBodyClass(array $classes): array
    {
        if (! is_singular(PropertyRegister::POST_TYPE)) {
            return $classes;
        }

        $post = get_queried_object();
        if ($post instanceof WP_Post && self::isExportEnabled($post->ID)) {
            $classes[] = 'estate-office-offer-single';
        }

        return $classes;
    }

    public static function getContext(): array
    {
        return self::$context;
    }

    private static function isExportEnabled(int $postId): bool
    {
        return (bool) get_post_meta($postId, 'estate_property_flag_export_www', true);
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildContext(WP_Post $post): array
    {
        $meta = static fn (string $key): string => trim((string) get_post_meta($post->ID, $key, true));

        $price       = self::formatMoney($meta('estate_property_price'));
        $adminFee    = self::formatMoney($meta('estate_property_admin_fee'));
        $area        = self::formatMeasurement($meta('estate_property_area'), 'm²');
        $pricePerSqm = self::formatMoney($meta('estate_property_price_per_sqm'));

        $facts = array_values(array_filter([
            self::fact(__('Liczba pokoi', 'estate-office'), self::formatInteger($meta('estate_property_rooms'))),
            self::fact(__('Liczba sypialni', 'estate-office'), self::formatInteger($meta('estate_property_bedrooms'))),
            self::fact(__('Liczba łazienek', 'estate-office'), self::formatInteger($meta('estate_property_bathrooms'))),
            self::fact(__('Liczba toalet', 'estate-office'), self::formatInteger($meta('estate_property_toilets'))),
            self::fact(__('Piętro', 'estate-office'), self::formatInteger($meta('estate_property_floor'), true)),
            self::fact(__('Liczba pięter budynku', 'estate-office'), self::formatInteger($meta('estate_property_floors'))),
            self::fact(__('Rok budowy', 'estate-office'), self::formatInteger($meta('estate_property_build_year'))),
            self::fact(__('Typ domu', 'estate-office'), self::formatHouseType($meta('estate_property_house_type'))),
            self::fact(__('Status prawny', 'estate-office'), self::formatLegalStatus($meta('estate_property_legal_status'))),
            self::fact(__('Numer księgi wieczystej', 'estate-office'), self::formatLandRegister($meta('estate_property_land_register_number'), $meta('estate_property_no_land_register'))),
        ], static fn (?array $fact) => $fact !== null));

        $customFacts = array_values(array_filter(
            array_map(
                static fn(array $field): ?array => self::fact($field['label'], $field['value']),
                PropertyMeta::getDynamicFieldValues($post->ID)
            ),
            static fn (?array $fact) => $fact !== null
        ));

        $plotDetails = array_values(array_filter([
            self::fact(__('Kształt działki', 'estate-office'), self::formatPlotShape($meta('estate_property_plot_shape'))),
            self::fact(__('Długość działki', 'estate-office'), self::formatMeasurement($meta('estate_property_plot_length'), 'm')),
            self::fact(__('Szerokość działki', 'estate-office'), self::formatMeasurement($meta('estate_property_plot_width'), 'm')),
            self::fact(__('Opis wymiarów działki', 'estate-office'), $meta('estate_property_plot_dimensions')),
        ], static fn (?array $fact) => $fact !== null));

        $gallery = self::prepareGallery($post);
        $floorPlans = self::prepareFloorPlans($post);
        $mediaLinks = self::prepareMediaLinks($post);

        $map = self::buildMap($post);

        return [
            'reference'    => $meta('estate_property_reference'),
            'badges'       => self::collectBadges($post),
            'chips'        => self::collectChips($post),
            'price'        => $price,
            'admin_fee'    => $adminFee,
            'area'         => $area,
            'price_per_sqm'=> $pricePerSqm,
            'facts'        => $facts,
            'custom_fields'=> $customFacts,
            'plot'         => $plotDetails,
            'address'      => self::buildAddress($post),
            'map'          => $map,
            'gallery'      => $gallery,
            'floor_plans'  => $floorPlans,
            'media_links'  => $mediaLinks,
            'downloads'    => self::prepareDownloads($post),
            'manager'      => self::buildManager($post),
            'logo'         => self::getOfficeLogo(),
            'archive_link' => get_post_type_archive_link(PropertyRegister::POST_TYPE) ?: '',
            'recommendations' => self::buildRecommendations($post),
        ];
    }

    private static function formatMoney(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return '';
        }

        $number = number_format_i18n((float) $value, 2);

        return sprintf('%s PLN', $number);
    }

    private static function formatMeasurement(string $value, string $unit): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return '';
        }

        $number = number_format_i18n((float) $value, 2);

        return sprintf('%s %s', $number, $unit);
    }

    private static function formatInteger(string $value, bool $allowZero = false): string
    {
        if ($value === '' || (! $allowZero && (int) $value === 0)) {
            return '';
        }

        return number_format_i18n((int) $value);
    }

    private static function formatHouseType(string $value): string
    {
        return $value !== '' ? PropertyMeta::getHouseTypeLabel($value) : '';
    }

    private static function formatPlotShape(string $value): string
    {
        return $value !== '' ? PropertyMeta::getPlotShapeLabel($value) : '';
    }

    private static function formatLegalStatus(string $value): string
    {
        return $value !== '' ? PropertyMeta::getLegalStatusLabel($value) : '';
    }

    private static function formatLandRegister(string $value, string $noRegisterFlag): string
    {
        if ($noRegisterFlag === '1') {
            return __('Brak księgi wieczystej', 'estate-office');
        }

        return $value;
    }

    /**
     * @return array<int,array{label:string,value:string}>
     */
    private static function collectBadges(WP_Post $post): array
    {
        $definitions = PropertyMeta::getFlagDefinitions();
        $badges = [];

        foreach ($definitions as $metaKey => $definition) {
            if (($definition['badge'] ?? '') === '') {
                continue;
            }

            if (! get_post_meta($post->ID, $metaKey, true)) {
                continue;
            }

            $badges[] = [
                'label' => $definition['label'],
                'slug'  => $definition['badge'],
            ];
        }

        return $badges;
    }

    /**
     * @return array<int,string>
     */
    private static function collectChips(WP_Post $post): array
    {
        $taxonomies = [
            'estate_transaction_type',
            'estate_property_type',
            'estate_city',
            'estate_district',
        ];

        $chips = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $chips[] = $term->name;
            }
        }

        return $chips;
    }

    /**
     * @return array{line1:string,line2:string,district:string}
     */
    private static function buildAddress(WP_Post $post): array
    {
        $street   = trim((string) get_post_meta($post->ID, 'estate_property_street', true));
        $number   = trim((string) get_post_meta($post->ID, 'estate_property_number', true));
        $unit     = trim((string) get_post_meta($post->ID, 'estate_property_unit', true));
        $postal   = trim((string) get_post_meta($post->ID, 'estate_property_postal_code', true));
        $city     = trim((string) get_post_meta($post->ID, 'estate_property_city', true));
        $district = trim((string) get_post_meta($post->ID, 'estate_property_district', true));

        $line1 = trim(sprintf('%s %s', $street, $number));

        if ($unit !== '') {
            $line1 = trim($line1 . '/' . $unit);
        }

        $line2Parts = array_filter([$postal, $city], static fn (string $part) => $part !== '');
        $line2      = trim(implode(' ', $line2Parts));

        return [
            'line1'    => $line1,
            'line2'    => $line2,
            'district' => $district,
        ];
    }

    /**
     * @return array{address:string,lat:float|null,lng:float|null,embed:string,interactive:bool,has_coordinates:bool,title:string}
     */
    private static function buildMap(WP_Post $post): array
    {
        $mapData = Maps::prepareMapData($post->ID);

        $title = trim((string) get_the_title($post));
        if ($title === '') {
            $title = sprintf(__('Nieruchomość #%d', 'estate-office'), $post->ID);
        }

        $mapData['title'] = $title;

        return $mapData;
    }

    /**
     * @return array{main:array{url:string,alt:string}|null,items:array<int,array{url:string,alt:string,thumb:string}>,count:int}
     */
    private static function prepareGallery(WP_Post $post): array
    {
        $ids = get_post_meta($post->ID, 'estate_property_gallery', true);
        $ids = is_array($ids) ? $ids : (array) $ids;
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));

        $primary = (int) get_post_meta($post->ID, PropertyMeta::GALLERY_PRIMARY_META_KEY, true);

        if ($primary > 0) {
            $primaryIndex = array_search($primary, $ids, true);

            if ($primaryIndex !== false) {
                unset($ids[$primaryIndex]);
                array_unshift($ids, $primary);
            }
        }

        $captions = get_post_meta($post->ID, PropertyMeta::GALLERY_CAPTIONS_META_KEY, true);
        $captions = is_array($captions) ? $captions : [];

        $items = [];

        foreach ($ids as $attachmentId) {
            if ($attachmentId <= 0) {
                continue;
            }

            $full = wp_get_attachment_image_url($attachmentId, 'large');

            if (! $full) {
                continue;
            }

            $thumb = wp_get_attachment_image_url($attachmentId, 'medium') ?: $full;
            $alt   = trim((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true));

            if ($alt === '') {
                $alt = wp_strip_all_tags(get_the_title($attachmentId));
            }

            $caption = '';

            if (isset($captions[(string) $attachmentId])) {
                $caption = trim((string) $captions[(string) $attachmentId]);
            }

            $items[] = [
                'id'      => $attachmentId,
                'url'     => $full,
                'alt'     => $alt,
                'thumb'   => $thumb,
                'caption' => $caption,
            ];
        }

        if (empty($items)) {
            $featured = get_post_thumbnail_id($post);

            if ($featured) {
                $full = wp_get_attachment_image_url($featured, 'large');

                if ($full) {
                    $thumb = wp_get_attachment_image_url($featured, 'medium') ?: $full;
                    $alt   = trim((string) get_post_meta($featured, '_wp_attachment_image_alt', true));

                    if ($alt === '') {
                        $alt = wp_strip_all_tags(get_the_title($post));
                    }

                    $items[] = [
                        'id'      => $featured,
                        'url'   => $full,
                        'alt'   => $alt,
                        'thumb' => $thumb,
                        'caption' => '',
                    ];
                }
            }
        }

        $main = $items[0] ?? null;

        return [
            'main'  => $main,
            'items' => $items,
            'count' => count($items),
        ];
    }

    /**
     * @return array<int,array{label:string,url:string}>
     */
    private static function prepareFloorPlans(WP_Post $post): array
    {
        $floorPlans = [];

        $plans = [
            'estate_property_floor_plan_2d' => __('Rzut 2D', 'estate-office'),
            'estate_property_floor_plan_3d' => __('Rzut 3D', 'estate-office'),
        ];

        foreach ($plans as $metaKey => $label) {
            $attachmentId = absint(get_post_meta($post->ID, $metaKey, true));

            if ($attachmentId <= 0) {
                continue;
            }

            $url = wp_get_attachment_url($attachmentId);

            if (! $url) {
                continue;
            }

            $floorPlans[] = [
                'label' => $label,
                'url'   => $url,
            ];
        }

        return $floorPlans;
    }

    /**
     * @return array<int,array{label:string,url:string}>
     */
    private static function prepareMediaLinks(WP_Post $post): array
    {
        $links = [];

        $video = trim((string) get_post_meta($post->ID, 'estate_property_video_url', true));
        if ($video !== '') {
            $links[] = [
                'label' => __('Zobacz wideo', 'estate-office'),
                'url'   => esc_url($video),
            ];
        }

        $tour = trim((string) get_post_meta($post->ID, 'estate_property_virtual_tour_url', true));
        if ($tour !== '') {
            $links[] = [
                'label' => __('Wirtualny spacer', 'estate-office'),
                'url'   => esc_url($tour),
            ];
        }

        return $links;
    }

    /**
     * @return array<int,array{label:string,url:string,meta:string}>
     */
    private static function prepareDownloads(WP_Post $post): array
    {
        $ids = get_post_meta($post->ID, 'estate_property_materials', true);
        $ids = is_array($ids) ? $ids : (array) $ids;

        $downloads = [];

        foreach ($ids as $id) {
            $attachmentId = absint($id);

            if ($attachmentId <= 0) {
                continue;
            }

            $url = wp_get_attachment_url($attachmentId);

            if (! $url) {
                continue;
            }

            $label = trim((string) get_the_title($attachmentId));
            if ($label === '') {
                $label = sprintf(__('Materiał #%d', 'estate-office'), $attachmentId);
            }

            $filePath = get_attached_file($attachmentId) ?: '';
            $meta     = $filePath !== '' ? wp_basename($filePath) : '';

            $downloads[] = [
                'label'    => $label,
                'url'      => $url,
                'meta'     => $meta,
                'download' => $meta,
            ];
        }

        return $downloads;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildManager(WP_Post $post): array
    {
        $managerId = absint(get_post_meta($post->ID, 'estate_property_manager', true));

        if ($managerId <= 0) {
            return [];
        }

        $user = get_user_by('id', $managerId);

        if (! $user) {
            return [];
        }

        $phones = [];

        $mainPhone = trim((string) get_user_meta($managerId, AgentProfile::META_PHONE, true));
        if ($mainPhone !== '') {
            $phones[] = [
                'label'   => __('Telefon', 'estate-office'),
                'display' => $mainPhone,
                'href'    => self::formatTelHref($mainPhone),
            ];
        }

        $altPhone = trim((string) get_user_meta($managerId, AgentProfile::META_PHONE_ALT, true));
        if ($altPhone !== '') {
            $phones[] = [
                'label'   => __('Telefon dodatkowy', 'estate-office'),
                'display' => $altPhone,
                'href'    => self::formatTelHref($altPhone),
            ];
        }

        $officePhone = trim((string) get_user_meta($managerId, AgentProfile::META_OFFICE_PHONE, true));
        if ($officePhone !== '') {
            $phones[] = [
                'label'   => __('Telefon biura', 'estate-office'),
                'display' => $officePhone,
                'href'    => self::formatTelHref($officePhone),
            ];
        }

        $avatarId  = absint(get_user_meta($managerId, AgentProfile::META_AVATAR, true));
        $avatarUrl = $avatarId ? wp_get_attachment_image_url($avatarId, 'medium') : '';

        if ($avatarUrl === '') {
            $avatarUrl = get_avatar_url($managerId, ['size' => 320]);
        }

        $whatsappRaw = trim((string) get_user_meta($managerId, AgentProfile::META_WHATSAPP, true));
        $whatsapp    = self::formatWhatsapp($whatsappRaw);

        $bio = (string) get_user_meta($managerId, AgentProfile::META_BIOGRAPHY, true);

        return [
            'id'         => $managerId,
            'name'       => $user->display_name,
            'email'      => $user->user_email,
            'phones'     => $phones,
            'avatar'     => $avatarUrl,
            'whatsapp'   => $whatsapp,
            'bio'        => $bio,
            'profile_url'=> AgentPublic::getProfileUrl($managerId),
        ];
    }

    private static function formatTelHref(string $value): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $value);

        return $digits !== '' ? 'tel:' . $digits : '';
    }

    /**
     * @return array{label:string,url:string}|null
     */
    private static function formatWhatsapp(string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return [
                'label' => __('WhatsApp', 'estate-office'),
                'url'   => esc_url($value),
            ];
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return null;
        }

        $url = sprintf('https://wa.me/%s', rawurlencode($digits));

        return [
            'label' => __('WhatsApp', 'estate-office'),
            'url'   => $url,
        ];
    }

    private static function getOfficeLogo(): string
    {
        $settings = get_option(GeneralSettings::OPTION);

        if (! is_array($settings) || empty($settings['office_logo'])) {
            return '';
        }

        $logoId = absint($settings['office_logo']);

        if ($logoId <= 0) {
            return '';
        }

        $url = wp_get_attachment_image_url($logoId, 'medium');

        return $url ?: '';
    }

    /**
     * @return array<int,array{title:string,permalink:string,thumbnail:string,price:string,area:string,address:string,badges:array<int,array{label:string,slug:string}>,reference:string}>
     */
    private static function buildRecommendations(WP_Post $post): array
    {
        $taxQuery = [];

        $transactionTerms = wp_get_post_terms($post->ID, 'estate_transaction_type', ['fields' => 'ids']);
        if (! is_wp_error($transactionTerms) && ! empty($transactionTerms)) {
            $taxQuery[] = [
                'taxonomy' => 'estate_transaction_type',
                'field'    => 'term_id',
                'terms'    => array_map('absint', $transactionTerms),
            ];
        }

        $propertyTerms = wp_get_post_terms($post->ID, 'estate_property_type', ['fields' => 'ids']);
        if (! is_wp_error($propertyTerms) && ! empty($propertyTerms)) {
            $taxQuery[] = [
                'taxonomy' => 'estate_property_type',
                'field'    => 'term_id',
                'terms'    => array_map('absint', $propertyTerms),
            ];
        }

        $cityTerms = wp_get_post_terms($post->ID, 'estate_city', ['fields' => 'ids']);
        if (! is_wp_error($cityTerms) && ! empty($cityTerms)) {
            $taxQuery[] = [
                'taxonomy' => 'estate_city',
                'field'    => 'term_id',
                'terms'    => array_map('absint', $cityTerms),
            ];
        }

        $args = [
            'post_type'           => PropertyRegister::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => 3,
            'post__not_in'        => [$post->ID],
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'meta_query'          => [
                [
                    'key'   => 'estate_property_flag_export_www',
                    'value' => '1',
                ],
            ],
        ];

        if (! empty($taxQuery)) {
            if (count($taxQuery) > 1) {
                $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            }

            $args['tax_query'] = $taxQuery;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            return [];
        }

        $recommendations = [];

        while ($query->have_posts()) {
            $query->the_post();

            $related = $query->post;

            if (! $related instanceof WP_Post) {
                continue;
            }

            $title = trim((string) get_the_title($related));
            if ($title === '') {
                $title = sprintf(__('Oferta #%d', 'estate-office'), $related->ID);
            }

            $thumbnail = '';
            $thumbId   = get_post_thumbnail_id($related);
            if ($thumbId) {
                $thumbnail = wp_get_attachment_image_url($thumbId, 'medium_large') ?: (wp_get_attachment_image_url($thumbId, 'medium') ?: '');
            }

            $reference = trim((string) get_post_meta($related->ID, 'estate_property_reference', true));

            $recommendations[] = [
                'title'      => $title,
                'permalink'  => get_permalink($related),
                'thumbnail'  => $thumbnail,
                'price'      => self::formatMoney((string) get_post_meta($related->ID, 'estate_property_price', true)),
                'area'       => self::formatMeasurement((string) get_post_meta($related->ID, 'estate_property_area', true), 'm²'),
                'address'    => self::summarizeAddress(self::buildAddress($related)),
                'badges'     => self::collectBadges($related),
                'reference'  => $reference,
            ];
        }

        wp_reset_postdata();

        return $recommendations;
    }

    private static function summarizeAddress(array $address): string
    {
        $parts = [];

        if (! empty($address['line1'])) {
            $parts[] = $address['line1'];
        }

        if (! empty($address['line2'])) {
            $parts[] = $address['line2'];
        }

        if (! empty($address['district'])) {
            $parts[] = $address['district'];
        }

        return implode(', ', array_filter($parts, static fn(string $part) => $part !== ''));
    }

    /**
     * @param string $label
     * @param string $value
     * @return array{label:string,value:string}|null
     */
    private static function fact(string $label, string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        return [
            'label' => $label,
            'value' => $value,
        ];
    }
}
