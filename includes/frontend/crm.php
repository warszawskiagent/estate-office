<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\PostTypes\AgreementMeta;
use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\ClientMeta;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchMeta;
use EstateOffice\PostTypes\SearchRegister;
use WP_Post;
use WP_Query;
use WP_User;

use function absint;
use function add_action;
use function add_query_arg;
use function add_shortcode;
use function admin_url;
use function array_slice;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_edit_post_link;
use function get_permalink;
use function get_post_meta;
use function get_the_ID;
use function get_the_terms;
use function get_user_by;
use function is_array;
use function is_scalar;
use function is_string;
use function is_user_logged_in;
use function number_format_i18n;
use function plugins_url;
use function preg_replace;
use function remove_query_arg;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_starts_with;
use function uasort;
use function wp_count_posts;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;
use function wp_reset_postdata;
use function wp_unslash;
use function __;

use const ARRAY_A;
use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

final class CRM
{
    private const SHORTCODE = 'estate_office_crm';
    private const SEARCH_PARAM = 'estate_office_search';
    private const SECTION_PARAM = 'estate_office_section';

    /**
     * @var array<string,string>
     */
    private const SECTIONS = [
        'dashboard'  => 'Pulpit',
        'properties' => 'Nieruchomości',
        'agreements' => 'Umowy',
        'searches'   => 'Poszukiwania',
        'clients'    => 'Klienci',
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
            'estate-office-frontend-crm',
            plugins_url('assets/css/frontend-crm.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            'estate-office-frontend-crm',
            plugins_url('assets/js/frontend-crm.js', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );
    }

    public static function renderShortcode(): string
    {
        if (!is_user_logged_in() || !self::currentUserCanAccessCrm()) {
            return '<div class="estate-office-crm__notice">' . esc_html__('Dostęp do CRM jest ograniczony do agentów nieruchomości.', 'estate-office') . '</div>';
        }

        wp_enqueue_style('estate-office-frontend-crm');
        wp_enqueue_script('estate-office-frontend-crm');

        $section    = self::resolveSection();
        $searchTerm = self::getSearchTerm();

        ob_start();

        echo '<div class="estate-office-crm">';
        self::renderHeader($section);

        if ($section !== 'dashboard') {
            self::renderSearchForm($section, $searchTerm);
        }

        switch ($section) {
            case 'properties':
                self::renderProperties($searchTerm);
                break;
            case 'agreements':
                self::renderAgreements($searchTerm);
                break;
            case 'searches':
                self::renderSearches($searchTerm);
                break;
            case 'clients':
                self::renderClients($searchTerm);
                break;
            default:
                self::renderDashboard();
                break;
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    private static function currentUserCanAccessCrm(): bool
    {
        return current_user_can('edit_estate_properties')
            || current_user_can('edit_estate_agreements')
            || current_user_can('edit_estate_clients')
            || current_user_can('edit_estate_searches');
    }

    private static function resolveSection(): string
    {
        $requested = '';
        if (isset($_GET[self::SECTION_PARAM])) {
            $requested = sanitize_key((string) wp_unslash($_GET[self::SECTION_PARAM]));
        }

        if ($requested !== '' && isset(self::SECTIONS[$requested])) {
            return $requested;
        }

        return 'dashboard';
    }

    private static function getSearchTerm(): string
    {
        if (!isset($_GET[self::SEARCH_PARAM])) {
            return '';
        }

        return sanitize_text_field((string) wp_unslash($_GET[self::SEARCH_PARAM]));
    }

    private static function renderHeader(string $section): void
    {
        $baseUrl = self::getBaseUrl();
        echo '<header class="estate-office-crm__header">';
        echo '<div class="estate-office-crm__header-actions">';
        if (current_user_can('publish_estate_agreements')) {
            echo '<a class="estate-office-crm__primary" href="' . esc_url(admin_url('post-new.php?post_type=' . AgreementRegister::POST_TYPE)) . '">' . esc_html__('Dodaj nową umowę', 'estate-office') . '</a>';
        }
        echo '</div>';
        echo '<nav class="estate-office-crm__nav">';
        foreach (self::SECTIONS as $slug => $label) {
            $url = esc_url(add_query_arg(self::SECTION_PARAM, $slug, $baseUrl));
            $classes = ['estate-office-crm__nav-link'];
            if ($slug === $section) {
                $classes[] = 'is-active';
            }
            echo '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . $url . '">' . esc_html__($label, 'estate-office') . '</a>';
        }
        echo '</nav>';
        echo '</header>';
    }

    private static function renderSearchForm(string $section, string $searchTerm): void
    {
        $tableId = self::getTableId($section);
        $action  = esc_url(self::getBaseUrl());
        echo '<form class="estate-office-crm__search" method="get" action="' . $action . '">';
        foreach (self::getRetainedQueryArgs() as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
        }
        echo '<input type="hidden" name="' . esc_attr(self::SECTION_PARAM) . '" value="' . esc_attr($section) . '" />';
        echo '<label class="estate-office-crm__search-label">';
        echo '<span>' . esc_html__('Wyszukaj w tabeli', 'estate-office') . '</span>';
        echo '<input type="search" name="' . esc_attr(self::SEARCH_PARAM) . '" value="' . esc_attr($searchTerm) . '" placeholder="' . esc_attr__('Wpisz szukaną frazę...', 'estate-office') . '" data-eo-crm-search="' . esc_attr($tableId) . '" />';
        echo '</label>';
        echo '<button type="submit" class="estate-office-crm__button">' . esc_html__('Szukaj', 'estate-office') . '</button>';
        echo '</form>';
    }

    private static function getBaseUrl(): string
    {
        $permalink = get_permalink();
        if (!is_string($permalink)) {
            $permalink = '';
        }

        return remove_query_arg([self::SECTION_PARAM, self::SEARCH_PARAM], $permalink);
    }

    /**
     * @return array<string,string>
     */
    private static function getRetainedQueryArgs(): array
    {
        $args = [];
        foreach ($_GET as $key => $value) {
            if (!is_string($key) || str_starts_with($key, 'estate_office_')) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }

            $args[$key] = sanitize_text_field((string) wp_unslash($value));
        }

        return $args;
    }

    private static function renderDashboard(): void
    {
        $stats     = self::getDashboardStats();
        $topAgents = self::getTopAgents();

        echo '<section class="estate-office-crm__dashboard">';
        echo '<h2>' . esc_html__('Zestawienie CRM', 'estate-office') . '</h2>';

        if ($stats) {
            echo '<div class="estate-office-crm__tiles">';
            foreach ($stats as $stat) {
                echo '<article class="estate-office-crm__tile">';
                echo '<span class="estate-office-crm__tile-label">' . esc_html($stat['label']) . '</span>';
                echo '<strong class="estate-office-crm__tile-value">' . esc_html(number_format_i18n($stat['count'])) . '</strong>';
                echo '</article>';
            }
            echo '</div>';
        }

        echo '<div class="estate-office-crm__panel">';
        echo '<h3>' . esc_html__('Najaktywniejsi agenci', 'estate-office') . '</h3>';
        if ($topAgents) {
            echo '<table class="estate-office-crm__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Agent', 'estate-office') . '</th>';
            echo '<th>' . esc_html__('Łącznie rekordów', 'estate-office') . '</th>';
            echo '<th>' . esc_html__('Szczegóły', 'estate-office') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($topAgents as $agent) {
                /** @var WP_User $user */
                $user = $agent['user'];
                echo '<tr>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . esc_html(number_format_i18n($agent['total'])) . '</td>';
                echo '<td>';
                $details = [];
                foreach ($agent['breakdown'] as $label => $count) {
                    $details[] = sprintf('%s: %s', esc_html($label), esc_html(number_format_i18n($count)));
                }
                echo implode('<br />', $details);
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . esc_html__('Brak danych o aktywności agentów. Przypisz opiekunów do rekordów, aby zobaczyć zestawienie.', 'estate-office') . '</p>';
        }
        echo '</div>';
        echo '</section>';
    }

    /**
     * @return array<int,array{label:string,count:int}>
     */
    private static function getDashboardStats(): array
    {
        $postTypes = [
            [
                'type'  => PropertyRegister::POST_TYPE,
                'label' => __('Nieruchomości', 'estate-office'),
            ],
            [
                'type'  => AgreementRegister::POST_TYPE,
                'label' => __('Umowy', 'estate-office'),
            ],
            [
                'type'  => SearchRegister::POST_TYPE,
                'label' => __('Poszukiwania', 'estate-office'),
            ],
            [
                'type'  => ClientRegister::POST_TYPE,
                'label' => __('Klienci', 'estate-office'),
            ],
        ];

        $stats = [];
        foreach ($postTypes as $postType) {
            $counts    = wp_count_posts($postType['type']);
            $published = $counts && isset($counts->publish) ? (int) $counts->publish : 0;
            $stats[]   = [
                'label' => $postType['label'],
                'count' => $published,
            ];
        }

        return $stats;
    }

    /**
     * @return array<int,array{user:WP_User,total:int,breakdown:array<string,int>}>|array{}
     */
    private static function getTopAgents(): array
    {
        global $wpdb;

        $sources = [
            __('Nieruchomości', 'estate-office') => [
                'meta_key'  => 'estate_property_manager',
                'post_type' => PropertyRegister::POST_TYPE,
            ],
            __('Poszukiwania', 'estate-office') => [
                'meta_key'  => 'estate_search_manager',
                'post_type' => SearchRegister::POST_TYPE,
            ],
            __('Klienci', 'estate-office') => [
                'meta_key'  => 'estate_client_manager',
                'post_type' => ClientRegister::POST_TYPE,
            ],
        ];

        $agents = [];

        foreach ($sources as $label => $source) {
            $query = $wpdb->prepare(
                "SELECT CAST(pm.meta_value AS UNSIGNED) AS user_id, COUNT(pm.post_id) AS total
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND CAST(pm.meta_value AS UNSIGNED) > 0
                 GROUP BY user_id",
                $source['meta_key'],
                $source['post_type']
            );

            /** @var array<int,array{user_id:string,total:string}> $results */
            $results = $wpdb->get_results($query, ARRAY_A);

            foreach ($results as $row) {
                $userId = absint($row['user_id']);
                if ($userId <= 0) {
                    continue;
                }

                $user = get_user_by('ID', $userId);
                if (!$user instanceof WP_User) {
                    continue;
                }

                if (!isset($agents[$userId])) {
                    $agents[$userId] = [
                        'user'      => $user,
                        'total'     => 0,
                        'breakdown' => [],
                    ];
                }

                $count = (int) $row['total'];
                $agents[$userId]['total']                 += $count;
                $agents[$userId]['breakdown'][$label] = $count;
            }
        }

        uasort($agents, static function (array $a, array $b): int {
            return $b['total'] <=> $a['total'];
        });

        return array_slice($agents, 0, 5, true);
    }

    private static function renderProperties(string $searchTerm): void
    {
        $rows = self::queryProperties($searchTerm);
        $tableId = self::getTableId('properties');
        echo '<section class="estate-office-crm__section">';
        echo '<h2>' . esc_html__('Lista nieruchomości', 'estate-office') . '</h2>';
        $headers = [
            __('Numer oferty', 'estate-office'),
            __('Adres', 'estate-office'),
            __('Cena', 'estate-office'),
            __('Cena za m²', 'estate-office'),
            __('Metraż', 'estate-office'),
            __('Liczba pokoi', 'estate-office'),
            __('Opiekun', 'estate-office'),
        ];
        self::renderTableHeader($headers, $tableId);

        if ($rows) {
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td><a href="' . esc_url($row['link']) . '">' . esc_html($row['reference']) . '</a></td>';
                echo '<td>' . esc_html($row['address']) . '</td>';
                echo '<td>' . esc_html($row['price']) . '</td>';
                echo '<td>' . esc_html($row['price_per_sqm']) . '</td>';
                echo '<td>' . esc_html($row['area']) . '</td>';
                echo '<td>' . esc_html($row['rooms']) . '</td>';
                echo '<td>' . esc_html($row['manager']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            self::renderEmptyTableMessage(count($headers));
        }

        echo '</table>';
        echo '</section>';
    }

    /**
     * @return array<int,array{link:string,reference:string,address:string,price:string,price_per_sqm:string,area:string,rooms:string,manager:string}>
     */
    private static function queryProperties(string $searchTerm): array
    {
        $args = [
            'post_type'      => PropertyRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($searchTerm !== '') {
            $args['s'] = $searchTerm;
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'estate_property_reference',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_property_street',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_property_city',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new WP_Query($args);
        $rows  = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId    = (int) get_the_ID();
            $reference = (string) get_post_meta($postId, 'estate_property_reference', true);
            if ($reference === '') {
                $reference = sprintf('#%d', $postId);
            }

            $rows[] = [
                'link'           => (string) get_edit_post_link($postId) ?: '#',
                'reference'      => $reference,
                'address'        => self::formatPropertyAddress($postId),
                'price'          => self::formatCurrencyMeta($postId, 'estate_property_price'),
                'price_per_sqm'  => self::formatCurrencyMeta($postId, 'estate_property_price_per_sqm'),
                'area'           => self::formatNumberMeta($postId, 'estate_property_area'),
                'rooms'          => self::formatIntegerMeta($postId, 'estate_property_rooms'),
                'manager'        => self::getManagerName((int) get_post_meta($postId, 'estate_property_manager', true)),
            ];
        }

        wp_reset_postdata();

        return $rows;
    }

    private static function renderAgreements(string $searchTerm): void
    {
        $rows    = self::queryAgreements($searchTerm);
        $tableId = self::getTableId('agreements');
        echo '<section class="estate-office-crm__section">';
        echo '<h2>' . esc_html__('Lista umów', 'estate-office') . '</h2>';
        $headers = [
            __('Numer umowy', 'estate-office'),
            __('Typ transakcji', 'estate-office'),
            __('Rodzaj nieruchomości', 'estate-office'),
            __('Adres', 'estate-office'),
            __('Data zawarcia', 'estate-office'),
            __('Data zakończenia', 'estate-office'),
            __('Aktualny etap', 'estate-office'),
            __('Opiekun', 'estate-office'),
        ];
        self::renderTableHeader($headers, $tableId);

        if ($rows) {
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td><a href="' . esc_url($row['link']) . '">' . esc_html($row['number']) . '</a></td>';
                echo '<td>' . esc_html($row['transaction']) . '</td>';
                echo '<td>' . esc_html($row['property_type']) . '</td>';
                echo '<td>' . esc_html($row['address']) . '</td>';
                echo '<td>' . esc_html($row['start_date']) . '</td>';
                echo '<td>' . esc_html($row['end_date']) . '</td>';
                echo '<td>' . esc_html($row['stage']) . '</td>';
                echo '<td>' . esc_html($row['manager']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            self::renderEmptyTableMessage(count($headers));
        }

        echo '</table>';
        echo '</section>';
    }

    /**
     * @return array<int,array{link:string,number:string,transaction:string,property_type:string,address:string,start_date:string,end_date:string,stage:string,manager:string}>
     */
    private static function queryAgreements(string $searchTerm): array
    {
        $args = [
            'post_type'      => AgreementRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($searchTerm !== '') {
            $args['s'] = $searchTerm;
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'estate_agreement_number',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_agreement_transaction_type',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new WP_Query($args);
        $rows  = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId    = (int) get_the_ID();
            $number    = (string) get_post_meta($postId, 'estate_agreement_number', true);
            if ($number === '') {
                $number = sprintf('#%d', $postId);
            }
            $transactionKey = (string) get_post_meta($postId, 'estate_agreement_transaction_type', true);
            $properties     = self::sanitizeIdArray(get_post_meta($postId, 'estate_agreement_properties', true));
            $firstProperty  = $properties[0] ?? 0;
            $manager        = '';
            if ($firstProperty > 0) {
                $manager = self::getManagerName((int) get_post_meta($firstProperty, 'estate_property_manager', true));
            }

            $rows[] = [
                'link'          => (string) get_edit_post_link($postId) ?: '#',
                'number'        => $number,
                'transaction'   => self::getAgreementTransactionLabel($transactionKey),
                'property_type' => self::getPropertyTypeFromAgreement($firstProperty),
                'address'       => $firstProperty > 0 ? self::formatPropertyAddress($firstProperty) : '—',
                'start_date'    => self::formatDateMeta($postId, 'estate_agreement_start_date'),
                'end_date'      => self::formatAgreementEndDate($postId),
                'stage'         => self::getAgreementStageLabel((string) get_post_meta($postId, 'estate_agreement_stage', true)),
                'manager'       => $manager,
            ];
        }

        wp_reset_postdata();

        return $rows;
    }

    private static function renderSearches(string $searchTerm): void
    {
        $rows    = self::querySearches($searchTerm);
        $tableId = self::getTableId('searches');
        echo '<section class="estate-office-crm__section">';
        echo '<h2>' . esc_html__('Lista poszukiwań', 'estate-office') . '</h2>';
        $headers = [
            __('Numer poszukiwania', 'estate-office'),
            __('Rodzaj nieruchomości', 'estate-office'),
            __('Budżet', 'estate-office'),
            __('Lokalizacja', 'estate-office'),
            __('Typ transakcji', 'estate-office'),
            __('Opiekun', 'estate-office'),
        ];
        self::renderTableHeader($headers, $tableId);

        if ($rows) {
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td><a href="' . esc_url($row['link']) . '">' . esc_html($row['reference']) . '</a></td>';
                echo '<td>' . esc_html($row['property_type']) . '</td>';
                echo '<td>' . esc_html($row['budget']) . '</td>';
                echo '<td>' . esc_html($row['location']) . '</td>';
                echo '<td>' . esc_html($row['transaction']) . '</td>';
                echo '<td>' . esc_html($row['manager']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            self::renderEmptyTableMessage(count($headers));
        }

        echo '</table>';
        echo '</section>';
    }

    /**
     * @return array<int,array{link:string,reference:string,property_type:string,budget:string,location:string,transaction:string,manager:string}>
     */
    private static function querySearches(string $searchTerm): array
    {
        $args = [
            'post_type'      => SearchRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($searchTerm !== '') {
            $args['s'] = $searchTerm;
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'estate_search_reference',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_search_location',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new WP_Query($args);
        $rows  = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId = (int) get_the_ID();
            $rows[] = [
                'link'          => (string) get_edit_post_link($postId) ?: '#',
                'reference'     => self::resolveReference($postId, 'estate_search_reference'),
                'property_type' => self::mapValue(SearchMeta::PROPERTY_TYPES, (string) get_post_meta($postId, 'estate_search_property_type', true)),
                'budget'        => self::formatBudgetRange($postId),
                'location'      => (string) get_post_meta($postId, 'estate_search_location', true),
                'transaction'   => self::mapValue(SearchMeta::TRANSACTION_TYPES, (string) get_post_meta($postId, 'estate_search_transaction_type', true)),
                'manager'       => self::getManagerName((int) get_post_meta($postId, 'estate_search_manager', true)),
            ];
        }

        wp_reset_postdata();

        return $rows;
    }

    private static function renderClients(string $searchTerm): void
    {
        $rows    = self::queryClients($searchTerm);
        $tableId = self::getTableId('clients');
        echo '<section class="estate-office-crm__section">';
        echo '<h2>' . esc_html__('Lista klientów', 'estate-office') . '</h2>';
        $headers = [
            __('Imię i nazwisko / Nazwa', 'estate-office'),
            __('Adres', 'estate-office'),
            __('Telefon', 'estate-office'),
            __('E-mail', 'estate-office'),
            __('Opiekun', 'estate-office'),
        ];
        self::renderTableHeader($headers, $tableId);

        if ($rows) {
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td><a href="' . esc_url($row['link']) . '">' . esc_html($row['name']) . '</a></td>';
                echo '<td>' . esc_html($row['address']) . '</td>';
                echo '<td>' . $row['phone'] . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . esc_html($row['manager']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            self::renderEmptyTableMessage(count($headers));
        }

        echo '</table>';
        echo '</section>';
    }

    /**
     * @return array<int,array{link:string,name:string,address:string,phone:string,email:string,manager:string}>
     */
    private static function queryClients(string $searchTerm): array
    {
        $args = [
            'post_type'      => ClientRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($searchTerm !== '') {
            $args['s'] = $searchTerm;
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'estate_client_first_name',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_client_last_name',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_client_company_name',
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new WP_Query($args);
        $rows  = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId = (int) get_the_ID();
            $rows[] = [
                'link'    => (string) get_edit_post_link($postId) ?: '#',
                'name'    => self::resolveClientName($postId),
                'address' => self::formatClientAddress($postId),
                'phone'   => self::formatPhone((string) get_post_meta($postId, 'estate_client_phone', true)),
                'email'   => self::formatEmail((string) get_post_meta($postId, 'estate_client_email', true)),
                'manager' => self::getManagerName((int) get_post_meta($postId, 'estate_client_manager', true)),
            ];
        }

        wp_reset_postdata();

        return $rows;
    }

    /**
     * @param array<int,string> $headers
     */
    private static function renderTableHeader(array $headers, string $tableId): void
    {
        echo '<table class="estate-office-crm__table" id="' . esc_attr($tableId) . '">';
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead>';
    }

    private static function renderEmptyTableMessage(int $columns): void
    {
        echo '<tbody><tr><td colspan="' . esc_attr((string) $columns) . '">' . esc_html__('Brak danych do wyświetlenia.', 'estate-office') . '</td></tr></tbody>';
    }

    private static function getTableId(string $section): string
    {
        return 'estate-office-crm-table-' . $section;
    }

    private static function resolveReference(int $postId, string $metaKey): string
    {
        $reference = (string) get_post_meta($postId, $metaKey, true);
        if ($reference !== '') {
            return $reference;
        }

        return sprintf('#%d', $postId);
    }

    private static function formatPropertyAddress(int $postId): string
    {
        $street = (string) get_post_meta($postId, 'estate_property_street', true);
        $number = (string) get_post_meta($postId, 'estate_property_number', true);
        $unit   = (string) get_post_meta($postId, 'estate_property_unit', true);
        $postal = (string) get_post_meta($postId, 'estate_property_postal_code', true);
        $city   = (string) get_post_meta($postId, 'estate_property_city', true);

        $parts = [];
        if ($street !== '') {
            $streetLine = $street;
            if ($number !== '') {
                $streetLine .= ' ' . $number;
            }
            if ($unit !== '') {
                $streetLine .= '/' . $unit;
            }
            $parts[] = $streetLine;
        }

        if ($postal !== '') {
            $parts[] = $postal;
        }
        if ($city !== '') {
            $parts[] = $city;
        }

        if (!$parts) {
            return __('Brak adresu', 'estate-office');
        }

        return implode(', ', $parts);
    }

    private static function formatCurrencyMeta(int $postId, string $metaKey): string
    {
        $value = (string) get_post_meta($postId, $metaKey, true);
        if ($value === '') {
            return '—';
        }

        $number = (float) $value;
        return number_format_i18n($number, 2) . ' PLN';
    }

    private static function formatNumberMeta(int $postId, string $metaKey): string
    {
        $value = (string) get_post_meta($postId, $metaKey, true);
        if ($value === '') {
            return '—';
        }

        return number_format_i18n((float) $value, 2) . ' m²';
    }

    private static function formatIntegerMeta(int $postId, string $metaKey): string
    {
        $value = (string) get_post_meta($postId, $metaKey, true);
        if ($value === '') {
            return '—';
        }

        return number_format_i18n((int) $value);
    }

    private static function getManagerName(int $userId): string
    {
        if ($userId <= 0) {
            return '—';
        }

        $user = get_user_by('ID', $userId);
        if (!$user instanceof WP_User) {
            return '—';
        }

        return $user->display_name;
    }

    private static function getAgreementTransactionLabel(string $key): string
    {
        return self::mapValue(AgreementMeta::TRANSACTION_TYPES, $key);
    }

    private static function getAgreementStageLabel(string $key): string
    {
        return self::mapValue(AgreementMeta::STAGES, $key);
    }

    private static function getPropertyTypeFromAgreement(int $propertyId): string
    {
        if ($propertyId <= 0) {
            return '—';
        }

        $terms = get_the_terms($propertyId, 'estate_property_type');
        if (is_array($terms) && $terms !== []) {
            return $terms[0]->name;
        }

        return '—';
    }

    private static function formatDateMeta(int $postId, string $metaKey): string
    {
        $value = (string) get_post_meta($postId, $metaKey, true);
        if ($value === '') {
            return '—';
        }

        return $value;
    }

    private static function formatAgreementEndDate(int $postId): string
    {
        $isIndefinite = (bool) get_post_meta($postId, 'estate_agreement_is_indefinite', true);
        if ($isIndefinite) {
            return __('Bezterminowa', 'estate-office');
        }

        return self::formatDateMeta($postId, 'estate_agreement_end_date');
    }

    private static function formatBudgetRange(int $postId): string
    {
        $min = (string) get_post_meta($postId, 'estate_search_price_min', true);
        $max = (string) get_post_meta($postId, 'estate_search_price_max', true);

        if ($min === '' && $max === '') {
            return '—';
        }

        $parts = [];
        if ($min !== '') {
            $parts[] = sprintf('%s PLN', number_format_i18n((float) $min, 2));
        }
        if ($max !== '') {
            $parts[] = sprintf('%s PLN', number_format_i18n((float) $max, 2));
        }

        return implode(' - ', $parts);
    }

    private static function resolveClientName(int $postId): string
    {
        $type = (string) get_post_meta($postId, 'estate_client_type', true);
        if ($type === 'company') {
            $company = (string) get_post_meta($postId, 'estate_client_company_name', true);
            if ($company !== '') {
                return $company;
            }
        }

        $first = (string) get_post_meta($postId, 'estate_client_first_name', true);
        $last  = (string) get_post_meta($postId, 'estate_client_last_name', true);
        $name  = trim($first . ' ' . $last);
        if ($name !== '') {
            return $name;
        }

        return sprintf(__('Klient #%d', 'estate-office'), $postId);
    }

    private static function formatClientAddress(int $postId): string
    {
        $street = (string) get_post_meta($postId, 'estate_client_address_street', true);
        $number = (string) get_post_meta($postId, 'estate_client_address_number', true);
        $unit   = (string) get_post_meta($postId, 'estate_client_address_unit', true);
        $postal = (string) get_post_meta($postId, 'estate_client_address_postal_code', true);
        $city   = (string) get_post_meta($postId, 'estate_client_address_city', true);

        $parts = [];
        if ($street !== '') {
            $streetLine = $street;
            if ($number !== '') {
                $streetLine .= ' ' . $number;
            }
            if ($unit !== '') {
                $streetLine .= '/' . $unit;
            }
            $parts[] = $streetLine;
        }

        if ($postal !== '') {
            $parts[] = $postal;
        }
        if ($city !== '') {
            $parts[] = $city;
        }

        if ($parts) {
            return implode(', ', $parts);
        }

        return __('Brak adresu', 'estate-office');
    }

    private static function formatPhone(string $phone): string
    {
        if ($phone === '') {
            return '—';
        }

        $tel = preg_replace('/[^0-9+]/', '', $phone);
        if (!is_string($tel) || $tel === '') {
            return esc_html($phone);
        }

        return '<a href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a>';
    }

    private static function formatEmail(string $email): string
    {
        if ($email === '') {
            return '—';
        }

        return '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }

    /**
     * @template TValue of string
     * @param array<string,TValue> $map
     * @param string $key
     * @return string
     */
    private static function mapValue(array $map, string $key): string
    {
        return $map[$key] ?? '—';
    }

    /**
     * @param mixed $value
     * @return array<int,int>
     */
    private static function sanitizeIdArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $id = absint($item);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
