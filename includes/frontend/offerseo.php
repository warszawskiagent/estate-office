<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\PropertyRegister;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Sitemaps_Provider;
use WP_Sitemaps;
use WP_User;

use function esc_attr;
use function esc_url;
use function get_post;
use function get_permalink;
use function get_post_meta;
use function get_post_type_object;
use function get_the_excerpt;
use function get_user_meta;
use function get_userdata;
use function is_singular;
use function sanitize_text_field;
use function time;
use function trim;
use function wp_date;
use function wp_get_attachment_image_url;
use function wp_get_document_title;
use function wp_json_encode;
use function wp_strip_all_tags;
use function wp_trim_words;

defined('ABSPATH') || exit;

final class OfferSeo
{
    private const EXPORT_META_KEY = 'estate_property_flag_export_www';
    private const POSTS_PER_SITEMAP = 2000;

    public static function bootstrap(): void
    {
        add_action('wp_head', [self::class, 'renderMetaTags'], 1);
        add_action('wp_sitemaps_register_providers', [self::class, 'registerSitemapProvider']);
    }

    public static function renderMetaTags(): void
    {
        if (!is_singular(PropertyRegister::POST_TYPE)) {
            return;
        }

        $post = get_post();

        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
            return;
        }

        if (!self::isExported($post->ID)) {
            return;
        }

        $title       = wp_get_document_title();
        $description = self::prepareDescription($post);
        $image       = self::resolvePrimaryImageUrl($post->ID);
        $schema      = self::buildSchema($post, $description, $image);

        if ($title !== '') {
            printf("\n<meta property=\"og:title\" content=\"%s\" />\n", esc_attr($title));
            printf("<meta name=\"twitter:title\" content=\"%s\" />\n", esc_attr($title));
        }

        if ($description !== '') {
            printf("\n<meta name=\"description\" content=\"%s\" />\n", esc_attr($description));
            printf("<meta property=\"og:description\" content=\"%s\" />\n", esc_attr($description));
            printf("<meta name=\"twitter:description\" content=\"%s\" />\n", esc_attr($description));
        }

        printf("<meta property=\"og:type\" content=\"article\" />\n");
        printf("<meta property=\"og:url\" content=\"%s\" />\n", esc_url(get_permalink($post)));

        if ($image !== '') {
            printf("<meta property=\"og:image\" content=\"%s\" />\n", esc_url($image));
            printf("<meta name=\"twitter:card\" content=\"summary_large_image\" />\n");
            printf("<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url($image));
        }

        if ($schema !== '') {
            printf("<script type=\"application/ld+json\">%s</script>\n", $schema);
        }
    }

    private static function isExported(int $postId): bool
    {
        return (bool) get_post_meta($postId, self::EXPORT_META_KEY, true);
    }

    private static function prepareDescription(WP_Post $post): string
    {
        $excerpt = get_the_excerpt($post);

        if (is_string($excerpt) && trim($excerpt) !== '') {
            return self::normaliseExcerpt($excerpt);
        }

        return self::normaliseExcerpt(wp_trim_words(wp_strip_all_tags($post->post_content), 40, '…'));
    }

    private static function normaliseExcerpt(string $value): string
    {
        $value = sanitize_text_field($value);

        return trim($value);
    }

    private static function resolvePrimaryImageUrl(int $postId): string
    {
        $primary = (int) get_post_meta($postId, PropertyMeta::GALLERY_PRIMARY_META_KEY, true);

        if ($primary > 0) {
            $url = wp_get_attachment_image_url($primary, 'full');

            if (is_string($url)) {
                return $url;
            }
        }

        $gallery = get_post_meta($postId, 'estate_property_gallery', true);

        if (is_array($gallery)) {
            foreach ($gallery as $attachmentId) {
                $url = wp_get_attachment_image_url((int) $attachmentId, 'full');

                if (is_string($url)) {
                    return $url;
                }
            }
        }

        return '';
    }

    private static function buildSchema(WP_Post $post, string $description, string $imageUrl): string
    {
        $address = self::buildAddress($post->ID);
        $price   = get_post_meta($post->ID, 'estate_property_price', true);
        $area    = get_post_meta($post->ID, 'estate_property_area', true);
        $manager = self::resolveManager($post->ID);

        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'RealEstateListing',
            'name'        => $post->post_title,
            'description' => $description,
            'url'         => get_permalink($post),
            'datePosted'  => wp_date('c', strtotime($post->post_date_gmt) ?: time()),
        ];

        if ($imageUrl !== '') {
            $data['image'] = $imageUrl;
        }

        if ($address !== []) {
            $data['address'] = $address;
        }

        if ($manager !== null) {
            $seller = [
                '@type' => 'RealEstateAgent',
                'name'  => $manager['user']->display_name,
                'email' => $manager['user']->user_email,
            ];

            if ($manager['phone'] !== '') {
                $seller['telephone'] = $manager['phone'];
            }

            $data['seller'] = $seller;
        }

        if ($price !== '') {
            $offer = [
                '@type'         => 'Offer',
                'price'         => (float) $price,
                'priceCurrency' => 'PLN',
            ];

            if ($area !== '') {
                $offer['areaServed'] = [
                    '@type' => 'QuantitativeValue',
                    'value' => (float) $area,
                    'unitCode' => 'MTK',
                ];
            }

            $data['offers'] = $offer;
        }

        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildAddress(int $postId): array
    {
        $street = get_post_meta($postId, 'estate_property_street', true);
        $number = get_post_meta($postId, 'estate_property_number', true);
        $postal = get_post_meta($postId, 'estate_property_postal_code', true);
        $city   = get_post_meta($postId, 'estate_property_city', true);

        $addressParts = [];

        if ($street !== '' || $number !== '') {
            $addressParts['streetAddress'] = trim($street . ' ' . $number);
        }

        if ($postal !== '') {
            $addressParts['postalCode'] = $postal;
        }

        if ($city !== '') {
            $addressParts['addressLocality'] = $city;
        }

        if ($addressParts === []) {
            return [];
        }

        $addressParts['@type'] = 'PostalAddress';

        return $addressParts;
    }

    /**
     * @return array{user: WP_User, phone: string}|null
     */
    private static function resolveManager(int $postId): ?array
    {
        $managerId = (int) get_post_meta($postId, 'estate_property_manager', true);

        if ($managerId <= 0) {
            return null;
        }

        $user = get_userdata($managerId);

        if (!$user instanceof WP_User) {
            return null;
        }

        $phonePrimary = (string) get_user_meta($managerId, AgentProfile::META_PHONE, true);
        $phoneAlt     = (string) get_user_meta($managerId, AgentProfile::META_PHONE_ALT, true);
        $phoneOffice  = (string) get_user_meta($managerId, AgentProfile::META_OFFICE_PHONE, true);
        $phone        = $phonePrimary !== '' ? $phonePrimary : ($phoneAlt !== '' ? $phoneAlt : $phoneOffice);

        return [
            'user'  => $user,
            'phone' => $phone,
        ];
    }

    public static function registerSitemapProvider(WP_Sitemaps $sitemaps): void
    {
        if (!self::isSitemapsEnabled()) {
            return;
        }

        $provider = new class() extends WP_Sitemaps_Provider {
            public function __construct()
            {
                parent::__construct('post', PropertyRegister::POST_TYPE);
            }

            public function get_max_num_pages(string $subtype = ''): int
            {
                $query = new WP_Query($this->prepareQueryArgs([
                    'fields'         => 'ids',
                    'posts_per_page' => 1,
                ]));

                $count = (int) $query->found_posts;

                return $count === 0 ? 0 : (int) ceil($count / OfferSeo::POSTS_PER_SITEMAP);
            }

            public function get_object_subtypes(): array
            {
                $object = get_post_type_object(PropertyRegister::POST_TYPE);

                if (!$object instanceof WP_Post_Type) {
                    return [];
                }

                return [
                    PropertyRegister::POST_TYPE => [
                        'name'  => $object->label ?: $object->name,
                        'label' => $object->labels->singular_name ?? $object->label ?? $object->name,
                    ],
                ];
            }

            public function get_url_list(int $page_num, string $subtype = ''): array
            {
                $query = new WP_Query($this->prepareQueryArgs([
                    'paged'          => $page_num,
                    'posts_per_page' => OfferSeo::POSTS_PER_SITEMAP,
                ]));

                $entries = [];

                foreach ($query->posts as $post) {
                    if (!$post instanceof WP_Post) {
                        $post = get_post($post);
                    }

                    if (!$post instanceof WP_Post) {
                        continue;
                    }

                    $entries[] = [
                        'loc'     => get_permalink($post),
                        'lastmod' => wp_date('c', strtotime($post->post_modified_gmt) ?: time()),
                    ];
                }

                return $entries;
            }

            /**
             * @param array<string, mixed> $args
             *
             * @return array<string, mixed>
             */
            private function prepareQueryArgs(array $args): array
            {
                $base = [
                    'post_type'           => PropertyRegister::POST_TYPE,
                    'post_status'         => 'publish',
                    'orderby'             => 'date',
                    'order'               => 'DESC',
                    'meta_key'            => OfferSeo::EXPORT_META_KEY,
                    'meta_value'          => '1',
                    'no_found_rows'       => false,
                    'ignore_sticky_posts' => true,
                ];

                return array_merge($base, $args);
            }
        };

        $sitemaps->register_provider('estate-office-properties', $provider);
    }

    private static function isSitemapsEnabled(): bool
    {
        return function_exists('wp_sitemaps_get_server');
    }
}
