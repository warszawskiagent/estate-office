<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\Settings\GeneralSettings;
use WP_Query;
use WP_User;

use function absint;
use function add_action;
use function add_filter;
use function add_rewrite_rule;
use function add_rewrite_tag;
use function esc_html__;
use function esc_url_raw;
use function get_404_template;
use function get_avatar_url;
use function get_option;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_query_var;
use function get_the_ID;
use function get_the_title;
use function get_permalink;
use function get_user_by;
use function get_user_meta;
use function home_url;
use function is_array;
use function is_numeric;
use function is_string;
use function number_format_i18n;
use function plugins_url;
use function preg_replace;
use function rawurlencode;
use function sanitize_email;
use function sanitize_title;
use function sprintf;
use function status_header;
use function trim;
use function trailingslashit;
use function flush_rewrite_rules;
use function wp_enqueue_style;
use function wp_get_attachment_image_url;
use function wp_get_attachment_url;
use function wp_register_style;
use function wp_reset_postdata;
use function wp_strip_all_tags;

use const ESTATE_OFFICE_PLUGIN_DIR;
use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

/**
 * Handles public-facing agent profile pages with assigned offers.
 */
final class AgentPublic
{
    private const QUERY_VAR    = 'estate_office_agent';
    private const REWRITE_SLUG = 'agenci';
    private const TEMPLATE     = 'templates/agent-profile.php';

    /**
     * @var array<string,mixed>
     */
    private static array $context = [];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerRewrite']);
        add_action('init', [self::class, 'registerAssets']);
        add_filter('query_vars', [self::class, 'registerQueryVar']);
        add_filter('template_include', [self::class, 'overrideTemplate']);
        add_filter('body_class', [self::class, 'filterBodyClass']);
    }

    public static function activate(): void
    {
        self::registerRewrite();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            'estate-office-agent-public',
            plugins_url('assets/css/agent-public.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );
    }

    public static function registerRewrite(): void
    {
        add_rewrite_tag('%' . self::QUERY_VAR . '%', '([^&]+)');
        add_rewrite_rule(
            self::REWRITE_SLUG . '/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /**
     * @param array<int,string> $vars
     *
     * @return array<int,string>
     */
    public static function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function overrideTemplate(string $template): string
    {
        $slug = (string) get_query_var(self::QUERY_VAR);

        if ($slug === '') {
            return $template;
        }

        $agent = self::resolveAgent($slug);

        if (!$agent) {
            global $wp_query;

            if ($wp_query instanceof WP_Query) {
                $wp_query->set_404();
            }

            status_header(404);
            $notFound = get_404_template();

            return $notFound ?: $template;
        }

        self::$context = self::buildContext($agent);

        wp_enqueue_style('estate-office-agent-public');

        return ESTATE_OFFICE_PLUGIN_DIR . self::TEMPLATE;
    }

    /**
     * @param array<int,string> $classes
     *
     * @return array<int,string>
     */
    public static function filterBodyClass(array $classes): array
    {
        $slug = (string) get_query_var(self::QUERY_VAR);

        if ($slug === '') {
            return $classes;
        }

        $agent = self::resolveAgent($slug);

        if ($agent) {
            $classes[] = 'estate-office-agent-profile';
        }

        return $classes;
    }

    /**
     * Returns context prepared for the template rendering.
     *
     * @return array<string,mixed>
     */
    public static function getContext(): array
    {
        return self::$context;
    }

    public static function getProfileUrl(int $userId): string
    {
        $user = get_user_by('id', $userId);

        if (! $user instanceof WP_User) {
            return '';
        }

        $slug = sanitize_title($user->user_nicename ?: $user->user_login);

        return trailingslashit(home_url(self::REWRITE_SLUG . '/' . $slug));
    }

    private static function resolveAgent(string $slug): ?WP_User
    {
        $slug = sanitize_title($slug);

        if ($slug === '') {
            return null;
        }

        $user = get_user_by('slug', $slug);

        if ($user instanceof WP_User) {
            return $user;
        }

        if (is_numeric($slug)) {
            $user = get_user_by('id', (int) $slug);

            return $user instanceof WP_User ? $user : null;
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildContext(WP_User $agent): array
    {
        $avatarId  = absint(get_user_meta($agent->ID, AgentProfile::META_AVATAR, true));
        $avatarUrl = $avatarId ? wp_get_attachment_image_url($avatarId, 'large') : '';

        if ($avatarUrl === '') {
            $avatarUrl = get_avatar_url($agent->ID, ['size' => 480]);
        }

        $officeLogoId = 0;
        $settings     = get_option(GeneralSettings::OPTION);
        if (is_array($settings) && isset($settings['office_logo'])) {
            $officeLogoId = absint($settings['office_logo']);
        }

        $officeLogo = $officeLogoId ? wp_get_attachment_image_url($officeLogoId, 'medium') : '';
        if ($officeLogo === '') {
            $officeLogo = $officeLogoId ? wp_get_attachment_url($officeLogoId) : '';
        }

        $phones = self::collectPhones($agent);
        $whatsapp = self::formatWhatsapp((string) get_user_meta($agent->ID, AgentProfile::META_WHATSAPP, true));
        $bio      = (string) get_user_meta($agent->ID, AgentProfile::META_BIOGRAPHY, true);

        $properties = self::collectProperties($agent);

        return [
            'name'        => $agent->display_name,
            'email'       => sanitize_email($agent->user_email),
            'avatar'      => $avatarUrl,
            'phones'      => $phones,
            'whatsapp'    => $whatsapp,
            'bio'         => $bio,
            'properties'  => $properties,
            'properties_count' => count($properties),
            'profile_url' => self::getProfileUrl($agent->ID),
            'office_logo' => $officeLogo,
        ];
    }

    /**
     * @return array<int,array{label:string,display:string,href:string}>
     */
    private static function collectPhones(WP_User $agent): array
    {
        $map = [
            AgentProfile::META_PHONE        => esc_html__('Telefon', 'estate-office'),
            AgentProfile::META_PHONE_ALT    => esc_html__('Telefon dodatkowy', 'estate-office'),
            AgentProfile::META_OFFICE_PHONE => esc_html__('Telefon biura', 'estate-office'),
        ];

        $phones = [];

        foreach ($map as $meta => $label) {
            $raw = trim((string) get_user_meta($agent->ID, $meta, true));

            if ($raw === '') {
                continue;
            }

            $phones[] = [
                'label'   => $label,
                'display' => $raw,
                'href'    => self::formatTelHref($raw),
            ];
        }

        return $phones;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function collectProperties(WP_User $agent): array
    {
        $query = new WP_Query([
            'post_type'      => PropertyRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => [
                [
                    'key'     => 'estate_property_manager',
                    'value'   => $agent->ID,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'estate_property_flag_export_www',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        $properties    = [];
        $flagDefs      = PropertyMeta::getFlagDefinitions();

        while ($query->have_posts()) {
            $query->the_post();
            $postId = get_the_ID();

            if (! $postId) {
                continue;
            }

            $price     = self::formatMoney(get_post_meta($postId, 'estate_property_price', true));
            $pricePer  = self::formatMoney(get_post_meta($postId, 'estate_property_price_per_sqm', true));
            if ($pricePer !== '') {
                $pricePer .= ' / m²';
            }

            $properties[] = [
                'title'       => get_the_title(),
                'link'        => get_permalink($postId),
                'thumbnail'   => self::getThumbnail($postId),
                'price'       => $price,
                'price_per'   => $pricePer,
                'area'        => self::formatMeasurement(get_post_meta($postId, 'estate_property_area', true), 'm²'),
                'rooms'       => self::formatInteger(get_post_meta($postId, 'estate_property_rooms', true)),
                'city'        => wp_strip_all_tags((string) get_post_meta($postId, 'estate_property_city', true)),
                'district'    => wp_strip_all_tags((string) get_post_meta($postId, 'estate_property_district', true)),
                'badges'      => self::collectBadges($postId, $flagDefs),
                'reference'   => wp_strip_all_tags((string) get_post_meta($postId, 'estate_property_reference', true)),
            ];
        }

        wp_reset_postdata();

        return $properties;
    }

    /**
     * @param array<string,array{label:string,badge:string}> $definitions
     *
     * @return array<int,array{label:string,slug:string}>
     */
    private static function collectBadges(int $postId, array $definitions): array
    {
        $badges = [];

        foreach ($definitions as $metaKey => $definition) {
            if ($definition['badge'] === '') {
                continue;
            }

            $isActive = (bool) get_post_meta($postId, $metaKey, true);

            if (! $isActive) {
                continue;
            }

            $badges[] = [
                'label' => $definition['label'],
                'slug'  => $definition['badge'],
            ];
        }

        return $badges;
    }

    private static function getThumbnail(int $postId): string
    {
        $thumbId = get_post_thumbnail_id($postId);

        if ($thumbId) {
            $image = wp_get_attachment_image_url($thumbId, 'medium_large');

            if ($image) {
                return $image;
            }

            $image = wp_get_attachment_image_url($thumbId, 'medium');

            if ($image) {
                return $image;
            }

            $fallback = wp_get_attachment_url($thumbId);

            if (is_string($fallback)) {
                return $fallback;
            }
        }

        return '';
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
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return [
                'label' => esc_html__('WhatsApp', 'estate-office'),
                'url'   => esc_url_raw($value),
            ];
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return null;
        }

        return [
            'label' => esc_html__('WhatsApp', 'estate-office'),
            'url'   => sprintf('https://wa.me/%s', rawurlencode($digits)),
        ];
    }

    private static function formatMoney($value): string
    {
        if ($value === '' || ! is_string($value)) {
            return '';
        }

        $clean = preg_replace('/[^0-9.,-]/', '', $value);

        if ($clean === '' || ! is_numeric(str_replace(',', '.', $clean))) {
            return '';
        }

        $normalized = (float) str_replace(',', '.', $clean);

        if ($normalized <= 0.0) {
            return '';
        }

        return number_format_i18n($normalized, 0) . ' zł';
    }

    private static function formatMeasurement($value, string $unit): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = (float) str_replace(',', '.', preg_replace('/[^0-9.,-]/', '', $value));

        if ($normalized <= 0) {
            return '';
        }

        return number_format_i18n($normalized, 2) . ' ' . $unit;
    }

    private static function formatInteger($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = (int) preg_replace('/[^0-9-]/', '', $value);

        if ($normalized <= 0) {
            return '';
        }

        return number_format_i18n($normalized);
    }
}
