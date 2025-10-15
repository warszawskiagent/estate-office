<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\Roles\Manager as RolesManager;
use WP_Query;
use WP_User;
use WP_User_Query;

use function absint;
use function add_action;
use function add_query_arg;
use function add_shortcode;
use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function asort;
use function ceil;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_avatar_url;
use function get_user_meta;
use function implode;
use function is_string;
use function mb_strtolower;
use function number_format_i18n;
use function plugins_url;
use function preg_replace;
use function in_array;
use function remove_query_arg;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_title;
use function shortcode_atts;
use function selected;
use function sprintf;
use function strpos;
use function trim;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_attachment_image_url;
use function wp_register_script;
use function wp_register_style;
use function wp_strip_all_tags;
use function wp_trim_words;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit();

/**
 * Public directory of agents with search and filters.
 */
final class AgentDirectory
{
    private const SHORTCODE = 'estate_office_agents';
    private const QUERY_SPECIALISATION = 'estate_office_agents_specialisation';
    private const QUERY_AREA = 'estate_office_agents_area';
    private const QUERY_SEARCH = 'estate_office_agents_search';
    private const QUERY_PAGE = 'estate_office_agents_page';
    private const DEFAULT_PER_PAGE = 12;

    /**
     * @var array<string,string>
     */
    private const RESERVED_QUERY_ARGS = [
        self::QUERY_SPECIALISATION => self::QUERY_SPECIALISATION,
        self::QUERY_AREA           => self::QUERY_AREA,
        self::QUERY_SEARCH         => self::QUERY_SEARCH,
        self::QUERY_PAGE           => self::QUERY_PAGE,
    ];

    /**
     * @var array<string,array{label:string,query_var:string,placeholder:string}>
     */
    private const FILTERS = [
        'specialisation' => [
            'label'       => 'Specjalizacja',
            'query_var'   => self::QUERY_SPECIALISATION,
            'placeholder' => 'Wszystkie specjalizacje',
        ],
        'area' => [
            'label'       => 'Obszar działania',
            'query_var'   => self::QUERY_AREA,
            'placeholder' => 'Wszystkie obszary',
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
            'estate-office-agent-directory',
            plugins_url('assets/css/agent-directory.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            'estate-office-agent-directory',
            plugins_url('assets/js/agent-directory.js', ESTATE_OFFICE_PLUGIN_FILE),
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
            'per_page' => (string) self::DEFAULT_PER_PAGE,
        ], $attributes, self::SHORTCODE);

        $perPage = max(1, absint((string) $attributes['per_page']));

        $agents      = self::prepareAgents();
        $queryValues = self::getQueryValues();
        $filters     = self::collectFilterOptions($agents);
        $filtered    = self::filterAgents($agents, $queryValues);
        $total       = count($filtered);
        $pages       = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $page        = min($queryValues['page'], max(1, $pages));
        $offset      = ($page - 1) * $perPage;
        $visible     = array_slice($filtered, $offset, $perPage);

        wp_enqueue_style('estate-office-agent-directory');
        wp_enqueue_script('estate-office-agent-directory');

        ob_start();

        echo '<div class="estate-office-agents-directory">';
        self::renderFilters($filters, $queryValues);

        if (! empty($visible)) {
            self::renderList($visible);
            if ($pages > 1) {
                self::renderPagination($pages, $queryValues, $page);
            }
        } else {
            echo '<div class="estate-office-agents-directory__empty">' . esc_html__('Brak agentów spełniających wybrane kryteria.', 'estate-office') . '</div>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function prepareAgents(): array
    {
        $userQuery = new WP_User_Query([
            'role__in'    => [RolesManager::AGENT_ROLE, 'administrator'],
            'orderby'     => 'display_name',
            'order'       => 'ASC',
            'number'      => -1,
            'count_total' => false,
        ]);

        $results = array_filter(
            $userQuery->get_results(),
            static fn($user): bool => $user instanceof WP_User
        );

        return array_map([self::class, 'mapAgent'], $results);
    }

    /**
     * @return array<string,mixed>
     */
    private static function mapAgent(WP_User $user): array
    {
        $avatarId  = absint(get_user_meta($user->ID, AgentProfile::META_AVATAR, true));
        $avatarUrl = $avatarId > 0 ? wp_get_attachment_image_url($avatarId, 'medium') : '';

        if ($avatarUrl === '') {
            $avatarUrl = get_avatar_url($user->ID, ['size' => 360]);
        }

        $specialisations = self::mapList(AgentProfile::getListValues($user->ID, AgentProfile::META_SPECIALISATIONS));
        $serviceAreas    = self::mapList(AgentProfile::getListValues($user->ID, AgentProfile::META_SERVICE_AREAS));
        $bio             = (string) get_user_meta($user->ID, AgentProfile::META_BIOGRAPHY, true);
        $bioExcerpt      = $bio !== '' ? wp_trim_words(wp_strip_all_tags($bio), 32, '…') : '';

        $email = sanitize_email($user->user_email);
        $phones = self::collectPhones($user);
        $primaryPhone = $phones[0] ?? '';

        $propertiesCount = self::countExportedProperties($user->ID);

        $searchIndex = self::buildSearchIndex([
            $user->display_name,
            $email,
            implode(' ', $phones),
            implode(' ', array_map(static fn(array $item): string => $item['label'], $specialisations)),
            implode(' ', array_map(static fn(array $item): string => $item['label'], $serviceAreas)),
            wp_strip_all_tags($bio),
        ]);

        return [
            'id'                => $user->ID,
            'name'              => $user->display_name,
            'profile_url'       => AgentPublic::getProfileUrl($user->ID),
            'avatar'            => $avatarUrl,
            'email'             => $email,
            'phone'             => $primaryPhone,
            'phone_href'        => self::formatTelHref($primaryPhone),
            'bio_excerpt'       => $bioExcerpt,
            'specialisations'   => $specialisations,
            'service_areas'     => $serviceAreas,
            'properties_count'  => $propertiesCount,
            'search_index'      => $searchIndex,
        ];
    }

    /**
     * @param array<int,string> $items
     *
     * @return array<int,array{label:string,slug:string}>
     */
    private static function mapList(array $items): array
    {
        $mapped = [];

        foreach ($items as $item) {
            $label = trim($item);
            $slug  = sanitize_title($label);

            if ($label === '' || $slug === '') {
                continue;
            }

            $mapped[] = [
                'label' => $label,
                'slug'  => $slug,
            ];
        }

        return $mapped;
    }

    /**
     * @return array<int,string>
     */
    private static function collectPhones(WP_User $user): array
    {
        $metaKeys = [
            AgentProfile::META_PHONE,
            AgentProfile::META_PHONE_ALT,
            AgentProfile::META_OFFICE_PHONE,
        ];

        $phones = [];

        foreach ($metaKeys as $metaKey) {
            $raw = sanitize_text_field((string) get_user_meta($user->ID, $metaKey, true));

            if ($raw === '') {
                continue;
            }

            $phones[] = $raw;
        }

        return array_values(array_unique($phones));
    }

    private static function formatTelHref(string $value): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', $value);

        if (! is_string($normalized) || $normalized === '') {
            return '';
        }

        return 'tel:' . $normalized;
    }

    private static function countExportedProperties(int $userId): int
    {
        $query = new WP_Query([
            'post_type'      => PropertyRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'meta_query'     => [
                [
                    'key'     => 'estate_property_manager',
                    'value'   => $userId,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'estate_property_flag_export_www',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    /**
     * @param array<int,string> $chunks
     */
    private static function buildSearchIndex(array $chunks): string
    {
        $filtered = array_filter($chunks, static fn(string $chunk): bool => trim($chunk) !== '');

        if (empty($filtered)) {
            return '';
        }

        return mb_strtolower(implode(' ', $filtered), 'UTF-8');
    }

    /**
     * @return array{specialisation:string,area:string,search:string,page:int}
     */
    private static function getQueryValues(): array
    {
        $specialisation = '';
        $area           = '';
        $search         = '';
        $page           = 1;

        if (isset($_GET[self::QUERY_SPECIALISATION]) && is_string($_GET[self::QUERY_SPECIALISATION])) {
            $specialisation = sanitize_title((string) $_GET[self::QUERY_SPECIALISATION]);
        }

        if (isset($_GET[self::QUERY_AREA]) && is_string($_GET[self::QUERY_AREA])) {
            $area = sanitize_title((string) $_GET[self::QUERY_AREA]);
        }

        if (isset($_GET[self::QUERY_SEARCH]) && is_string($_GET[self::QUERY_SEARCH])) {
            $search = sanitize_text_field((string) $_GET[self::QUERY_SEARCH]);
        }

        if (isset($_GET[self::QUERY_PAGE])) {
            $page = max(1, absint((string) $_GET[self::QUERY_PAGE]));
        }

        return [
            'specialisation' => $specialisation,
            'area'           => $area,
            'search'         => $search,
            'page'           => $page,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $agents
     * @param array{specialisation:string,area:string,search:string,page:int} $query
     *
     * @return array<int,array<string,mixed>>
     */
    private static function filterAgents(array $agents, array $query): array
    {
        $needle = $query['search'] !== '' ? mb_strtolower($query['search'], 'UTF-8') : '';

        return array_values(array_filter($agents, static function (array $agent) use ($query, $needle): bool {
            if ($query['specialisation'] !== '' && ! self::agentHasTerm($agent['specialisations'], $query['specialisation'])) {
                return false;
            }

            if ($query['area'] !== '' && ! self::agentHasTerm($agent['service_areas'], $query['area'])) {
                return false;
            }

            if ($needle !== '') {
                if ($agent['search_index'] === '' || strpos($agent['search_index'], $needle) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<int,array{label:string,slug:string}> $terms
     */
    private static function agentHasTerm(array $terms, string $needle): bool
    {
        foreach ($terms as $term) {
            if ($term['slug'] === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $agents
     *
     * @return array<string,array<string,string>>
     */
    private static function collectFilterOptions(array $agents): array
    {
        $specialisations = [];
        $areas           = [];

        foreach ($agents as $agent) {
            foreach ($agent['specialisations'] as $item) {
                $specialisations[$item['slug']] = $item['label'];
            }

            foreach ($agent['service_areas'] as $item) {
                $areas[$item['slug']] = $item['label'];
            }
        }

        asort($specialisations, SORT_NATURAL | SORT_FLAG_CASE);
        asort($areas, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'specialisation' => $specialisations,
            'area'           => $areas,
        ];
    }

    /**
     * @param array<string,array<string,string>> $options
     * @param array{specialisation:string,area:string,search:string,page:int} $query
     */
    private static function renderFilters(array $options, array $query): void
    {
        $reserved = array_keys(self::RESERVED_QUERY_ARGS);
        $action   = remove_query_arg($reserved);

        echo '<form method="get" class="estate-office-agents-directory__filters" action="' . esc_url($action) . '">';
        echo '<div class="estate-office-agents-directory__filters-grid">';

        foreach (self::FILTERS as $key => $definition) {
            $value   = $query[$key] ?? '';
            $optionsList = $options[$key] ?? [];

            echo '<label>';
            echo '<span>' . esc_html__($definition['label'], 'estate-office') . '</span>';
            echo '<select name="' . esc_attr($definition['query_var']) . '">';
            echo '<option value="">' . esc_html__($definition['placeholder'], 'estate-office') . '</option>';

            foreach ($optionsList as $slug => $label) {
                echo '<option value="' . esc_attr($slug) . '"' . selected($value, $slug, false) . '>' . esc_html($label) . '</option>';
            }

            echo '</select>';
            echo '</label>';
        }

        echo '<label class="estate-office-agents-directory__search">';
        echo '<span>' . esc_html__('Szukaj', 'estate-office') . '</span>';
        echo '<input type="search" name="' . esc_attr(self::QUERY_SEARCH) . '" value="' . esc_attr($query['search']) . '" placeholder="' . esc_attr__('Szukaj po imieniu, e-mailu lub specjalizacji', 'estate-office') . '" />';
        echo '</label>';

        echo '</div>';

        self::renderPreservedQueryArgs($reserved);

        echo '<div class="estate-office-agents-directory__filters-actions">';
        echo '<button type="submit">' . esc_html__('Filtruj agentów', 'estate-office') . '</button>';

        if ($query['specialisation'] !== '' || $query['area'] !== '' || $query['search'] !== '') {
            echo '<a class="estate-office-agents-directory__reset" href="' . esc_url($action) . '">' . esc_html__('Wyczyść filtry', 'estate-office') . '</a>';
        }

        echo '</div>';
        echo '</form>';
    }

    /**
     * @param array<int,string> $reserved
     */
    private static function renderPreservedQueryArgs(array $reserved): void
    {
        foreach ($_GET as $key => $value) {
            if (! is_string($key) || in_array($key, $reserved, true)) {
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $sanitized = sanitize_text_field($value);
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($sanitized) . '" />';
        }
    }

    /**
     * @param array<int,array<string,mixed>> $agents
     */
    private static function renderList(array $agents): void
    {
        echo '<ul class="estate-office-agents-directory__list">';

        foreach ($agents as $agent) {
            echo '<li class="estate-office-agents-directory__card">';

            if ($agent['avatar'] !== '') {
                echo '<div class="estate-office-agents-directory__avatar">';
                echo '<img src="' . esc_url($agent['avatar']) . '" alt="" loading="lazy" />';
                echo '</div>';
            }

            echo '<div class="estate-office-agents-directory__body">';
            echo '<h3 class="estate-office-agents-directory__name">' . esc_html($agent['name']) . '</h3>';

            if ($agent['bio_excerpt'] !== '') {
                echo '<p class="estate-office-agents-directory__excerpt">' . esc_html($agent['bio_excerpt']) . '</p>';
            }

            if (! empty($agent['specialisations'])) {
                echo '<div class="estate-office-agents-directory__section">';
                echo '<span class="estate-office-agents-directory__section-title">' . esc_html__('Specjalizacje', 'estate-office') . ':</span>';
                echo '<ul class="estate-office-agents-directory__chips">';
                foreach ($agent['specialisations'] as $item) {
                    echo '<li>' . esc_html($item['label']) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            if (! empty($agent['service_areas'])) {
                echo '<div class="estate-office-agents-directory__section">';
                echo '<span class="estate-office-agents-directory__section-title">' . esc_html__('Obszary działania', 'estate-office') . ':</span>';
                echo '<ul class="estate-office-agents-directory__chips estate-office-agents-directory__chips--outline">';
                foreach ($agent['service_areas'] as $item) {
                    echo '<li>' . esc_html($item['label']) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            echo '<ul class="estate-office-agents-directory__contact">';
            if ($agent['email'] !== '') {
                echo '<li><span>' . esc_html__('E-mail', 'estate-office') . ':</span> <a href="mailto:' . esc_attr($agent['email']) . '">' . esc_html($agent['email']) . '</a></li>';
            }
            if ($agent['phone'] !== '') {
                echo '<li><span>' . esc_html__('Telefon', 'estate-office') . ':</span> ';
                if ($agent['phone_href'] !== '') {
                    echo '<a href="' . esc_url($agent['phone_href']) . '">' . esc_html($agent['phone']) . '</a>';
                } else {
                    echo '<span>' . esc_html($agent['phone']) . '</span>';
                }
                echo '</li>';
            }
            echo '<li><span>' . esc_html__('Aktywne oferty', 'estate-office') . ':</span> <strong>' . esc_html(number_format_i18n((int) $agent['properties_count'])) . '</strong></li>';
            echo '</ul>';

            if ($agent['profile_url'] !== '') {
                echo '<a class="estate-office-agents-directory__cta" href="' . esc_url($agent['profile_url']) . '">' . esc_html__('Zobacz profil agenta', 'estate-office') . '</a>';
            }

            echo '</div>';
            echo '</li>';
        }

        echo '</ul>';
    }

    private static function renderPagination(int $pages, array $query, int $current): void
    {
        echo '<nav class="estate-office-agents-directory__pagination" aria-label="' . esc_attr__('Stronicowanie agentów', 'estate-office') . '">';
        echo '<ul>';

        for ($page = 1; $page <= $pages; $page++) {
            $classes = ['estate-office-agents-directory__page'];
            if ($page === $current) {
                $classes[] = 'is-current';
            }

            $url = esc_url(self::buildPageUrl($page, $query));

            echo '<li class="' . esc_attr(implode(' ', $classes)) . '">';
            if ($page === $current) {
                echo '<span>' . esc_html((string) $page) . '</span>';
            } else {
                echo '<a href="' . $url . '">' . esc_html((string) $page) . '</a>';
            }
            echo '</li>';
        }

        echo '</ul>';
        echo '</nav>';
    }

    /**
     * @param array{specialisation:string,area:string,search:string,page:int} $query
     */
    private static function buildPageUrl(int $page, array $query): string
    {
        $args = [];

        if ($query['specialisation'] !== '') {
            $args[self::QUERY_SPECIALISATION] = $query['specialisation'];
        }

        if ($query['area'] !== '') {
            $args[self::QUERY_AREA] = $query['area'];
        }

        if ($query['search'] !== '') {
            $args[self::QUERY_SEARCH] = $query['search'];
        }

        if ($page > 1) {
            $args[self::QUERY_PAGE] = $page;
        }

        $base = remove_query_arg(array_keys(self::RESERVED_QUERY_ARGS));

        return add_query_arg($args, $base);
    }
}
