<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\PostTypes\AgreementMeta;
use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\ClientMeta;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\LeadMeta;
use EstateOffice\PostTypes\LeadRegister;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchMeta;
use EstateOffice\PostTypes\SearchRegister;
use WP_Post;
use WP_Query;
use WP_User;

use function absint;
use function apply_filters;
use function add_action;
use function add_query_arg;
use function add_shortcode;
use function admin_url;
use function array_search;
use function array_slice;
use function get_current_user_id;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_edit_post_link;
use function get_post;
use function get_post_field;
use function get_permalink;
use function get_post_meta;
use function get_post_type_object;
use function get_the_ID;
use function get_the_title;
use function get_the_terms;
use function get_option;
use function get_user_by;
use function is_array;
use function is_scalar;
use function is_string;
use function is_user_logged_in;
use function number_format_i18n;
use function plugins_url;
use function preg_replace;
use function remove_query_arg;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_starts_with;
use function uasort;
use function wp_count_posts;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_kses_post;
use function wp_register_script;
use function wp_register_style;
use function wp_reset_postdata;
use function wp_strip_all_tags;
use function wpautop;
use function wp_list_pluck;
use function wp_unslash;
use function mysql2date;
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
    private const RECORD_PARAM = 'estate_office_record';
    private const RECORD_ID_PARAM = 'estate_office_record_id';

    /**
     * @var array<string,string>
     */
    private const SECTIONS = [
        'dashboard'  => 'Pulpit',
        'properties' => 'Nieruchomości',
        'agreements' => 'Umowy',
        'searches'   => 'Poszukiwania',
        'clients'    => 'Klienci',
        'leads'      => 'Leady',
    ];

    /**
     * @var array<string,string>
     */
    private const SECTION_POST_TYPES = [
        'properties' => PropertyRegister::POST_TYPE,
        'agreements' => AgreementRegister::POST_TYPE,
        'searches'   => SearchRegister::POST_TYPE,
        'clients'    => ClientRegister::POST_TYPE,
        'leads'      => LeadRegister::POST_TYPE,
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
        $canAccess  = self::userCanAccessSection($section);
        $recordId   = $section === 'dashboard' ? 0 : self::resolveRecordId($section);

        ob_start();

        echo '<div class="estate-office-crm">';
        self::renderHeader($section);

        if ($section !== 'dashboard' && $recordId === 0 && $canAccess) {
            self::renderSearchForm($section, $searchTerm);
        }

        if (!$canAccess) {
            self::renderSectionRestricted();
        } elseif ($section !== 'dashboard' && $recordId > 0) {
            self::renderBackLink($section);
            self::renderRecordDetail($section, $recordId);
        } else {
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
                case 'leads':
                    self::renderLeads($searchTerm);
                    break;
                default:
                    self::renderDashboard();
                    break;
            }
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    private static function currentUserCanAccessCrm(): bool
    {
        return current_user_can('edit_estate_properties')
            || current_user_can('edit_estate_agreements')
            || current_user_can('edit_estate_clients')
            || current_user_can('edit_estate_searches')
            || current_user_can('edit_estate_leads');
    }

    private static function userCanAccessSection(string $section): bool
    {
        if ($section === 'dashboard') {
            return true;
        }

        $postType = self::SECTION_POST_TYPES[$section] ?? '';
        if ($postType === '') {
            return false;
        }

        $object = get_post_type_object($postType);
        if (!$object) {
            return false;
        }

        $capability = $object->cap->edit_posts ?? '';
        if (!is_string($capability) || $capability === '') {
            return false;
        }

        return current_user_can($capability);
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

    private static function resolveRecordId(string $section): int
    {
        if (!isset(self::SECTION_POST_TYPES[$section])) {
            return 0;
        }

        if (!isset($_GET[self::RECORD_ID_PARAM])) {
            return 0;
        }

        $requestedSection = $section;
        if (isset($_GET[self::RECORD_PARAM])) {
            $requestedSection = sanitize_key((string) wp_unslash($_GET[self::RECORD_PARAM]));
        }

        if ($requestedSection !== $section) {
            return 0;
        }

        $recordId = absint(wp_unslash((string) $_GET[self::RECORD_ID_PARAM]));

        return $recordId > 0 ? $recordId : 0;
    }

    private static function renderBackLink(string $section): void
    {
        $url = esc_url(add_query_arg(self::SECTION_PARAM, $section, self::getBaseUrl()));
        echo '<p class="estate-office-crm__back">';
        echo '<a class="estate-office-crm__button" href="' . $url . '">' . esc_html__('Powrót do listy', 'estate-office') . '</a>';
        echo '</p>';
    }

    private static function renderRecordDetail(string $section, int $postId): void
    {
        switch ($section) {
            case 'properties':
                self::renderPropertyDetail($postId);
                break;
            case 'agreements':
                self::renderAgreementDetail($postId);
                break;
            case 'searches':
                self::renderSearchDetail($postId);
                break;
            case 'clients':
                self::renderClientDetail($postId);
                break;
            case 'leads':
                self::renderLeadDetail($postId);
                break;
            default:
                self::renderDetailNotFound();
                break;
        }
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
            if (!self::userCanAccessSection($slug)) {
                continue;
            }
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

        return remove_query_arg([
            self::SECTION_PARAM,
            self::SEARCH_PARAM,
            self::RECORD_PARAM,
            self::RECORD_ID_PARAM,
        ], $permalink);
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

    private static function renderSectionRestricted(): void
    {
        echo '<div class="estate-office-crm__notice estate-office-crm__notice--error">' . esc_html__('Brak uprawnień do tej sekcji.', 'estate-office') . '</div>';
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

    private static function renderLeadDetail(int $postId): void
    {
        $post = self::getAccessiblePost($postId, LeadRegister::POST_TYPE);
        if (!$post instanceof WP_Post) {
            self::renderDetailNotFound();

            return;
        }

        $name        = (string) get_post_meta($postId, LeadMeta::META_NAME, true);
        $title       = trim((string) get_the_title($postId));
        if ($title === '') {
            $title = $name !== '' ? $name : sprintf(__('Lead #%d', 'estate-office'), $postId);
        }

        $statusKey   = (string) get_post_meta($postId, LeadMeta::META_STATUS, true);
        $statusKey   = $statusKey !== '' ? $statusKey : 'new';
        $statusKey   = LeadMeta::sanitizeStatus($statusKey);
        $statusLabel = LeadMeta::getStatusLabel($statusKey);
        $assigned    = self::getManagerName((int) get_post_meta($postId, LeadMeta::META_ASSIGNED, true));
        $created     = self::formatPostDateTime($postId);
        $phone       = self::formatPhone((string) get_post_meta($postId, LeadMeta::META_PHONE, true));
        $email       = self::formatEmail((string) get_post_meta($postId, LeadMeta::META_EMAIL, true));
        $contextKey  = (string) get_post_meta($postId, LeadMeta::META_CONTEXT, true);
        $context     = LeadMeta::getContextLabel($contextKey);
        $recordId    = absint((int) get_post_meta($postId, LeadMeta::META_RECORD, true));
        $record      = self::getLeadRecordSummary($recordId);
        $source      = (string) get_post_meta($postId, LeadMeta::META_SOURCE, true);
        $subject     = (string) get_post_meta($postId, LeadMeta::META_SUBJECT, true);
        $recipient   = (string) get_post_meta($postId, LeadMeta::META_RECIPIENT, true);
        $recipientName = (string) get_post_meta($postId, LeadMeta::META_RECIPIENT_NAME, true);

        echo '<section class="estate-office-crm__detail">';
        self::renderDetailHeader($title, '', $postId, [
            [
                'label' => $statusLabel,
                'class' => 'status-' . $statusKey,
            ],
        ]);

        $recordValue = esc_html($record['label']);
        if ($record['link'] !== '') {
            $recordValue = '<a href="' . esc_url($record['link']) . '">' . esc_html($record['label']) . '</a>';
        }

        $cards = [
            [
                'heading' => __('Status i przypisanie', 'estate-office'),
                'rows'    => [
                    ['label' => __('Status', 'estate-office'), 'value' => self::formatLeadStatusBadge($statusKey)],
                    ['label' => __('Data zgłoszenia', 'estate-office'), 'value' => esc_html($created)],
                    ['label' => __('Przypisany agent', 'estate-office'), 'value' => esc_html($assigned)],
                ],
            ],
            [
                'heading' => __('Dane kontaktowe', 'estate-office'),
                'rows'    => [
                    ['label' => __('Imię i nazwisko', 'estate-office'), 'value' => esc_html($name !== '' ? $name : '—')],
                    ['label' => __('E-mail', 'estate-office'), 'value' => $email],
                    ['label' => __('Telefon', 'estate-office'), 'value' => $phone],
                ],
            ],
            [
                'heading' => __('Kontekst zgłoszenia', 'estate-office'),
                'rows'    => [
                    ['label' => __('Kontekst', 'estate-office'), 'value' => esc_html($context)],
                    ['label' => __('Powiązany rekord', 'estate-office'), 'value' => $recordValue],
                    ['label' => __('Źródło zgłoszenia', 'estate-office'), 'value' => $source !== '' ? self::formatWebsite($source) : '—'],
                    ['label' => __('Adresat wiadomości', 'estate-office'), 'value' => self::formatLeadRecipient($recipientName, $recipient)],
                    ['label' => __('Temat', 'estate-office'), 'value' => esc_html($subject !== '' ? $subject : '—')],
                ],
            ],
        ];

        self::renderDetailCards($cards);

        $message = trim($post->post_content);
        if ($message !== '') {
            echo '<section class="estate-office-crm__detail-panel">';
            echo '<h3>' . esc_html__('Treść wiadomości', 'estate-office') . '</h3>';
            echo wp_kses_post(wpautop(esc_html($message)));
            echo '</section>';
        }

        echo '</section>';
    }

    /**
     * @return array<int,array{label:string,count:int}>
     */
    private static function getDashboardStats(): array
    {
        $postTypes = [
            [
                'type'    => PropertyRegister::POST_TYPE,
                'label'   => __('Nieruchomości', 'estate-office'),
                'section' => 'properties',
            ],
            [
                'type'    => AgreementRegister::POST_TYPE,
                'label'   => __('Umowy', 'estate-office'),
                'section' => 'agreements',
            ],
            [
                'type'    => SearchRegister::POST_TYPE,
                'label'   => __('Poszukiwania', 'estate-office'),
                'section' => 'searches',
            ],
            [
                'type'    => ClientRegister::POST_TYPE,
                'label'   => __('Klienci', 'estate-office'),
                'section' => 'clients',
            ],
            [
                'type'    => LeadRegister::POST_TYPE,
                'label'   => __('Leady', 'estate-office'),
                'section' => 'leads',
            ],
        ];

        $stats = [];
        foreach ($postTypes as $postType) {
            if (isset($postType['section']) && !self::userCanAccessSection($postType['section'])) {
                continue;
            }
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
            __('Leady', 'estate-office') => [
                'meta_key'  => LeadMeta::META_ASSIGNED,
                'post_type' => LeadRegister::POST_TYPE,
            ],
        ];

        $agents = [];

        foreach ($sources as $label => $source) {
            $section = array_search($source['post_type'], self::SECTION_POST_TYPES, true);
            if (is_string($section) && !self::userCanAccessSection($section)) {
                continue;
            }

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
                echo '<td>';
                echo '<a href="' . esc_url($row['link']) . '">' . esc_html($row['reference']) . '</a>';
                if (!empty($row['badges'])) {
                    echo '<div class="estate-office-crm__table-badges">';
                    foreach ($row['badges'] as $badge) {
                        $className = 'estate-office-crm__badge';
                        $modifier  = isset($badge['class']) ? trim((string) $badge['class']) : '';
                        if ($modifier !== '') {
                            $className .= ' estate-office-crm__badge--' . sanitize_html_class($modifier);
                        }
                        echo '<span class="' . esc_attr($className) . '">' . esc_html($badge['label']) . '</span>';
                    }
                    echo '</div>';
                }
                echo '</td>';
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
     * @return array<int,array{link:string,reference:string,address:string,price:string,price_per_sqm:string,area:string,rooms:string,manager:string,badges:array<int,array{label:string,class:string}>}>
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
                'link'           => self::getDetailLink('properties', $postId),
                'reference'      => $reference,
                'address'        => self::formatPropertyAddress($postId),
                'price'          => self::formatCurrencyMeta($postId, 'estate_property_price'),
                'price_per_sqm'  => self::formatCurrencyMeta($postId, 'estate_property_price_per_sqm'),
                'area'           => self::formatNumberMeta($postId, 'estate_property_area'),
                'rooms'          => self::formatIntegerMeta($postId, 'estate_property_rooms'),
                'manager'        => self::getManagerName((int) get_post_meta($postId, 'estate_property_manager', true)),
                'badges'         => self::getPropertyBadges($postId),
            ];
        }

        wp_reset_postdata();

        return $rows;
    }

    /**
     * @return array<int,array{link:string,display:string,context:string,phone:string,email:string,status_key:string,assigned:string,created:string,record_label:string,record_link:string}>
     */
    private static function queryLeads(string $searchTerm): array
    {
        $args = [
            'post_type'      => LeadRegister::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $metaQuery = [];
        $currentUserId = get_current_user_id();

        if ($currentUserId > 0 && !current_user_can('edit_others_estate_leads')) {
            $metaQuery[] = [
                'key'     => LeadMeta::META_ASSIGNED,
                'value'   => $currentUserId,
                'compare' => '=',
            ];
        }

        if ($searchTerm !== '') {
            $args['s'] = $searchTerm;
            $metaQuery[] = [
                'relation' => 'OR',
                [
                    'key'     => LeadMeta::META_NAME,
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => LeadMeta::META_EMAIL,
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => LeadMeta::META_PHONE,
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => LeadMeta::META_CONTEXT,
                    'value'   => $searchTerm,
                    'compare' => 'LIKE',
                ],
            ];
        }

        if ($metaQuery !== []) {
            if (count($metaQuery) > 1) {
                $metaQuery = array_merge(['relation' => 'AND'], $metaQuery);
            }

            $args['meta_query'] = $metaQuery;
        }

        $query = new WP_Query($args);
        $rows  = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId     = (int) get_the_ID();
            $name       = (string) get_post_meta($postId, LeadMeta::META_NAME, true);
            $display    = $name !== '' ? $name : (string) get_the_title($postId);
            if ($display === '') {
                $display = sprintf(__('Lead #%d', 'estate-office'), $postId);
            }

            $statusKey    = (string) get_post_meta($postId, LeadMeta::META_STATUS, true);
            $statusKey    = $statusKey !== '' ? $statusKey : 'new';
            $statusKey    = LeadMeta::sanitizeStatus($statusKey);
            $contextKey   = (string) get_post_meta($postId, LeadMeta::META_CONTEXT, true);
            $recordId     = absint((int) get_post_meta($postId, LeadMeta::META_RECORD, true));
            $record       = self::getLeadRecordSummary($recordId);

            $rows[] = [
                'link'         => self::getDetailLink('leads', $postId),
                'display'      => $display,
                'context'      => LeadMeta::getContextLabel($contextKey),
                'phone'        => self::formatPhone((string) get_post_meta($postId, LeadMeta::META_PHONE, true)),
                'email'        => self::formatEmail((string) get_post_meta($postId, LeadMeta::META_EMAIL, true)),
                'status_key'   => $statusKey,
                'assigned'     => self::getManagerName((int) get_post_meta($postId, LeadMeta::META_ASSIGNED, true)),
                'created'      => self::formatPostDateTime($postId),
                'record_label' => $record['label'],
                'record_link'  => $record['link'],
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
                'link'          => self::getDetailLink('agreements', $postId),
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
                'link'          => self::getDetailLink('searches', $postId),
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

    private static function renderLeads(string $searchTerm): void
    {
        $rows    = self::queryLeads($searchTerm);
        $tableId = self::getTableId('leads');
        echo '<section class="estate-office-crm__section">';
        echo '<h2>' . esc_html__('Lista leadów', 'estate-office') . '</h2>';
        $headers = [
            __('Lead', 'estate-office'),
            __('Telefon', 'estate-office'),
            __('E-mail', 'estate-office'),
            __('Status', 'estate-office'),
            __('Opiekun', 'estate-office'),
            __('Data zgłoszenia', 'estate-office'),
            __('Powiązany rekord', 'estate-office'),
        ];
        self::renderTableHeader($headers, $tableId);

        if ($rows) {
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>';
                echo '<a href="' . esc_url($row['link']) . '">' . esc_html($row['display']) . '</a>';
                if ($row['context'] !== '') {
                    echo '<div class="estate-office-crm__meta">' . esc_html($row['context']) . '</div>';
                }
                echo '</td>';
                echo '<td>' . $row['phone'] . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . self::formatLeadStatusBadge($row['status_key']) . '</td>';
                echo '<td>' . esc_html($row['assigned']) . '</td>';
                echo '<td>' . esc_html($row['created']) . '</td>';
                echo '<td>';
                if ($row['record_link'] !== '') {
                    echo '<a href="' . esc_url($row['record_link']) . '">' . esc_html($row['record_label']) . '</a>';
                } else {
                    echo esc_html($row['record_label']);
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            self::renderEmptyTableMessage(count($headers));
        }

        echo '</table>';
        echo '</section>';
    }

    private static function renderPropertyDetail(int $postId): void
    {
        $post = self::getAccessiblePost($postId, PropertyRegister::POST_TYPE);
        if (!$post instanceof WP_Post) {
            self::renderDetailNotFound();

            return;
        }

        $reference    = self::resolveReference($postId, 'estate_property_reference');
        $transaction  = self::getTermsList($postId, 'estate_transaction_type');
        $propertyType = self::getTermsList($postId, 'estate_property_type');
        $manager      = self::getManagerName((int) get_post_meta($postId, 'estate_property_manager', true));
        $title        = trim((string) get_the_title($postId));
        if ($title === '') {
            $title = sprintf(__('Nieruchomość #%d', 'estate-office'), $postId);
        }

        echo '<section class="estate-office-crm__detail">';
        self::renderDetailHeader(
            $title,
            sprintf(__('Numer oferty: %s', 'estate-office'), $reference),
            $postId,
            self::getPropertyBadges($postId)
        );

        $cards = [
            [
                'heading' => __('Informacje CRM', 'estate-office'),
                'rows'    => [
                    ['label' => __('Numer oferty', 'estate-office'), 'value' => esc_html($reference)],
                    ['label' => __('Typ transakcji', 'estate-office'), 'value' => esc_html($transaction)],
                    ['label' => __('Rodzaj nieruchomości', 'estate-office'), 'value' => esc_html($propertyType)],
                    ['label' => __('Opiekun', 'estate-office'), 'value' => esc_html($manager)],
                ],
            ],
            [
                'heading' => __('Finanse', 'estate-office'),
                'rows'    => [
                    ['label' => __('Cena', 'estate-office'), 'value' => esc_html(self::formatCurrencyMeta($postId, 'estate_property_price'))],
                    ['label' => __('Cena za m²', 'estate-office'), 'value' => esc_html(self::formatCurrencyMeta($postId, 'estate_property_price_per_sqm'))],
                    ['label' => __('Czynsz administracyjny', 'estate-office'), 'value' => esc_html(self::formatCurrencyMeta($postId, 'estate_property_admin_fee'))],
                ],
            ],
            [
                'heading' => __('Parametry nieruchomości', 'estate-office'),
                'rows'    => [
                    ['label' => __('Metraż', 'estate-office'), 'value' => esc_html(self::formatNumberMeta($postId, 'estate_property_area'))],
                    ['label' => __('Rok budowy', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_build_year'))],
                    ['label' => __('Piętro', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_floor'))],
                    ['label' => __('Liczba pięter', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_floors'))],
                    ['label' => __('Liczba pokoi', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_rooms'))],
                    ['label' => __('Liczba sypialni', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_bedrooms'))],
                    ['label' => __('Liczba łazienek', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_bathrooms'))],
                    ['label' => __('Liczba toalet', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_toilets'))],
                    ['label' => __('Typ domu', 'estate-office'), 'value' => esc_html(self::formatPropertyHouseType($postId))],
                ],
            ],
            [
                'heading' => __('Adres', 'estate-office'),
                'rows'    => [
                    ['label' => __('Pełny adres', 'estate-office'), 'value' => esc_html(self::formatPropertyAddress($postId))],
                    ['label' => __('Ulica', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_street'))],
                    ['label' => __('Numer', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_number'))],
                    ['label' => __('Lokal', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_unit'))],
                    ['label' => __('Kod pocztowy', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_postal_code'))],
                    ['label' => __('Dzielnica', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_district'))],
                    ['label' => __('Miasto', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_city'))],
                    ['label' => __('Powiat', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_county'))],
                    ['label' => __('Obręb', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_precinct'))],
                    ['label' => __('Numer działki', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_property_plot_number'))],
                ],
            ],
            [
                'heading' => __('Informacje prawne', 'estate-office'),
                'rows'    => [
                    ['label' => __('Numer księgi wieczystej', 'estate-office'), 'value' => esc_html(self::formatPropertyLandRegister($postId))],
                    ['label' => __('Stan prawny', 'estate-office'), 'value' => esc_html(self::formatPropertyLegalStatus($postId))],
                    ['label' => __('Kształt działki', 'estate-office'), 'value' => esc_html(self::formatPropertyPlotShape($postId))],
                ],
            ],
        ];

        self::renderDetailCards($cards);

        $content = apply_filters('the_content', $post->post_content);
        if (trim(wp_strip_all_tags((string) $content)) !== '') {
            echo '<section class="estate-office-crm__detail-panel">';
            echo '<h3>' . esc_html__('Opis nieruchomości', 'estate-office') . '</h3>';
            echo wp_kses_post((string) $content);
            echo '</section>';
        }

        $agreements = self::sanitizeIdArray(get_post_meta($postId, PropertyMeta::AGREEMENTS_META_KEY, true));
        self::renderRelationList(
            __('Powiązane umowy', 'estate-office'),
            'agreements',
            $agreements,
            static fn(int $agreementId): string => self::getAgreementNumberLabel($agreementId)
        );

        echo '</section>';
    }

    /**
     * @return array<int,array{label:string,class:string}>
     */
    private static function getPropertyBadges(int $postId): array
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
                'label' => (string) $definition['label'],
                'class' => isset($definition['badge']) ? (string) $definition['badge'] : '',
            ];
        }

        return $badges;
    }

    private static function renderAgreementDetail(int $postId): void
    {
        $post = self::getAccessiblePost($postId, AgreementRegister::POST_TYPE);
        if (!$post instanceof WP_Post) {
            self::renderDetailNotFound();

            return;
        }

        $number      = self::resolveReference($postId, 'estate_agreement_number');
        $title       = trim((string) get_the_title($postId));
        if ($title === '') {
            $title = sprintf(__('Umowa #%d', 'estate-office'), $postId);
        }
        $transaction = self::getAgreementTransactionLabel((string) get_post_meta($postId, 'estate_agreement_transaction_type', true));
        $stage       = self::getAgreementStageLabel((string) get_post_meta($postId, 'estate_agreement_stage', true));
        $startDate   = self::formatDateMeta($postId, 'estate_agreement_start_date');
        $endDate     = self::formatAgreementEndDate($postId);
        $commission  = self::formatAgreementCommission($postId);

        echo '<section class="estate-office-crm__detail">';
        self::renderDetailHeader(
            $title,
            sprintf(__('Numer umowy: %s', 'estate-office'), $number),
            $postId
        );

        $cards = [
            [
                'heading' => __('Szczegóły umowy', 'estate-office'),
                'rows'    => [
                    ['label' => __('Numer umowy', 'estate-office'), 'value' => esc_html($number)],
                    ['label' => __('Typ transakcji', 'estate-office'), 'value' => esc_html($transaction)],
                    ['label' => __('Data zawarcia', 'estate-office'), 'value' => esc_html($startDate)],
                    ['label' => __('Data zakończenia', 'estate-office'), 'value' => esc_html($endDate)],
                    ['label' => __('Aktualny etap', 'estate-office'), 'value' => esc_html($stage)],
                    ['label' => __('Prowizja', 'estate-office'), 'value' => esc_html($commission)],
                ],
            ],
        ];

        self::renderDetailCards($cards);

        $content = apply_filters('the_content', $post->post_content);
        if (trim(wp_strip_all_tags((string) $content)) !== '') {
            echo '<section class="estate-office-crm__detail-panel">';
            echo '<h3>' . esc_html__('Notatki', 'estate-office') . '</h3>';
            echo wp_kses_post((string) $content);
            echo '</section>';
        }

        $clients    = self::sanitizeIdArray(get_post_meta($postId, 'estate_agreement_clients', true));
        $properties = self::sanitizeIdArray(get_post_meta($postId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdArray(get_post_meta($postId, 'estate_agreement_searches', true));

        self::renderRelationList(
            __('Powiązani klienci', 'estate-office'),
            'clients',
            $clients,
            static fn(int $clientId): string => self::getClientRelationLabel($clientId)
        );

        self::renderRelationList(
            __('Powiązane nieruchomości', 'estate-office'),
            'properties',
            $properties,
            static fn(int $propertyId): string => self::getPropertyRelationLabel($propertyId)
        );

        self::renderRelationList(
            __('Powiązane poszukiwania', 'estate-office'),
            'searches',
            $searches,
            static fn(int $searchId): string => self::getSearchRelationLabel($searchId)
        );

        self::renderStageHistory($postId);

        echo '</section>';
    }

    private static function renderSearchDetail(int $postId): void
    {
        $post = self::getAccessiblePost($postId, SearchRegister::POST_TYPE);
        if (!$post instanceof WP_Post) {
            self::renderDetailNotFound();

            return;
        }

        $reference    = self::resolveReference($postId, 'estate_search_reference');
        $title        = trim((string) get_the_title($postId));
        if ($title === '') {
            $title = sprintf(__('Poszukiwanie #%d', 'estate-office'), $postId);
        }
        $transaction  = self::mapValue(SearchMeta::TRANSACTION_TYPES, (string) get_post_meta($postId, 'estate_search_transaction_type', true));
        $propertyType = self::mapValue(SearchMeta::PROPERTY_TYPES, (string) get_post_meta($postId, 'estate_search_property_type', true));
        $manager      = self::getManagerName((int) get_post_meta($postId, 'estate_search_manager', true));
        $location     = (string) get_post_meta($postId, 'estate_search_location', true);
        if ($location === '') {
            $location = '—';
        }

        echo '<section class="estate-office-crm__detail">';
        self::renderDetailHeader(
            $title,
            sprintf(__('Numer poszukiwania: %s', 'estate-office'), $reference),
            $postId
        );

        $cards = [
            [
                'heading' => __('Informacje ogólne', 'estate-office'),
                'rows'    => [
                    ['label' => __('Numer poszukiwania', 'estate-office'), 'value' => esc_html($reference)],
                    ['label' => __('Typ transakcji', 'estate-office'), 'value' => esc_html($transaction)],
                    ['label' => __('Rodzaj nieruchomości', 'estate-office'), 'value' => esc_html($propertyType)],
                    ['label' => __('Lokalizacja', 'estate-office'), 'value' => esc_html($location)],
                    ['label' => __('Opiekun', 'estate-office'), 'value' => esc_html($manager)],
                ],
            ],
            [
                'heading' => __('Zakres poszukiwań', 'estate-office'),
                'rows'    => [
                    ['label' => __('Budżet', 'estate-office'), 'value' => esc_html(self::formatBudgetRange($postId))],
                    ['label' => __('Metraż', 'estate-office'), 'value' => esc_html(self::formatRange($postId, 'estate_search_area_min', 'estate_search_area_max', 'm²'))],
                    ['label' => __('Liczba pokoi', 'estate-office'), 'value' => esc_html(self::formatIntegerRange($postId, 'estate_search_rooms_min', 'estate_search_rooms_max'))],
                    ['label' => __('Gaz', 'estate-office'), 'value' => esc_html(self::formatBooleanMeta($postId, 'estate_search_gas'))],
                ],
            ],
        ];

        self::renderDetailCards($cards);

        $description = (string) get_post_meta($postId, 'estate_search_description', true);
        if (trim($description) !== '') {
            echo '<section class="estate-office-crm__detail-panel">';
            echo '<h3>' . esc_html__('Opis poszukiwania', 'estate-office') . '</h3>';
            echo wp_kses_post(wpautop(esc_html($description)));
            echo '</section>';
        }

        $agreements = self::sanitizeIdArray(get_post_meta($postId, SearchMeta::AGREEMENTS_META_KEY, true));
        self::renderRelationList(
            __('Powiązane umowy', 'estate-office'),
            'agreements',
            $agreements,
            static fn(int $agreementId): string => self::getAgreementNumberLabel($agreementId)
        );

        echo '</section>';
    }

    private static function renderClientDetail(int $postId): void
    {
        $post = self::getAccessiblePost($postId, ClientRegister::POST_TYPE);
        if (!$post instanceof WP_Post) {
            self::renderDetailNotFound();

            return;
        }

        $name       = self::resolveClientName($postId);
        $title      = trim((string) get_the_title($postId));
        if ($title === '') {
            $title = $name;
        }
        $reference  = (string) get_post_meta($postId, 'estate_client_reference', true);
        $clientType = self::mapValue(ClientMeta::CLIENT_TYPES, (string) get_post_meta($postId, 'estate_client_type', true));
        $manager    = self::getManagerName((int) get_post_meta($postId, 'estate_client_manager', true));
        $phone      = self::formatPhone((string) get_post_meta($postId, 'estate_client_phone', true));
        $email      = self::formatEmail((string) get_post_meta($postId, 'estate_client_email', true));
        $website    = self::formatWebsite((string) get_post_meta($postId, 'estate_client_website', true));

        echo '<section class="estate-office-crm__detail">';
        self::renderDetailHeader(
            $title,
            $reference !== '' ? sprintf(__('Numer klienta: %s', 'estate-office'), $reference) : '',
            $postId
        );

        $cards = [
            [
                'heading' => __('Informacje CRM', 'estate-office'),
                'rows'    => [
                    ['label' => __('Nazwa klienta', 'estate-office'), 'value' => esc_html($name)],
                    ['label' => __('Typ klienta', 'estate-office'), 'value' => esc_html($clientType)],
                    ['label' => __('Opiekun', 'estate-office'), 'value' => esc_html($manager)],
                ],
            ],
            [
                'heading' => __('Kontakt', 'estate-office'),
                'rows'    => [
                    ['label' => __('Telefon', 'estate-office'), 'value' => $phone],
                    ['label' => __('E-mail', 'estate-office'), 'value' => $email],
                    ['label' => __('Strona WWW', 'estate-office'), 'value' => $website],
                ],
            ],
            [
                'heading' => __('Adresy', 'estate-office'),
                'rows'    => [
                    ['label' => __('Adres zamieszkania/rejestrowy', 'estate-office'), 'value' => esc_html(self::formatClientAddress($postId))],
                    ['label' => __('Adres korespondencyjny', 'estate-office'), 'value' => esc_html(self::formatCorrespondenceAddress($postId))],
                ],
            ],
            [
                'heading' => __('Dane identyfikacyjne', 'estate-office'),
                'rows'    => [
                    ['label' => __('PESEL', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_client_pesel'))],
                    ['label' => __('Rodzaj dokumentu', 'estate-office'), 'value' => esc_html(self::mapValue(ClientMeta::DOCUMENT_TYPES, (string) get_post_meta($postId, 'estate_client_document_type', true)))],
                    ['label' => __('Numer dokumentu', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_client_document_number'))],
                    ['label' => __('NIP', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_client_tax_id'))],
                    ['label' => __('KRS', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_client_krs'))],
                    ['label' => __('REGON', 'estate-office'), 'value' => esc_html(self::formatRawMeta($postId, 'estate_client_regon'))],
                ],
            ],
        ];

        self::renderDetailCards($cards);

        $content = apply_filters('the_content', $post->post_content);
        if (trim(wp_strip_all_tags((string) $content)) !== '') {
            echo '<section class="estate-office-crm__detail-panel">';
            echo '<h3>' . esc_html__('Notatki o kliencie', 'estate-office') . '</h3>';
            echo wp_kses_post((string) $content);
            echo '</section>';
        }

        $agreements = self::sanitizeIdArray(get_post_meta($postId, ClientMeta::AGREEMENTS_META_KEY, true));
        self::renderRelationList(
            __('Powiązane umowy', 'estate-office'),
            'agreements',
            $agreements,
            static fn(int $agreementId): string => self::getAgreementNumberLabel($agreementId)
        );

        echo '</section>';
    }

    /**
     * @param array<int,array{label:string,class:string}> $badges
     */
    private static function renderDetailHeader(string $title, string $subtitle, int $postId, array $badges = []): void
    {
        $title = trim($title);
        if ($title === '') {
            $title = sprintf(__('Rekord #%d', 'estate-office'), $postId);
        }

        echo '<header class="estate-office-crm__detail-header">';
        echo '<div class="estate-office-crm__detail-heading">';
        echo '<h2>' . esc_html($title) . '</h2>';
        if ($subtitle !== '') {
            echo '<p class="estate-office-crm__detail-subtitle">' . esc_html($subtitle) . '</p>';
        }
        if (!empty($badges)) {
            echo '<ul class="estate-office-crm__detail-badges">';
            foreach ($badges as $badge) {
                $className = 'estate-office-crm__badge';
                $modifier  = isset($badge['class']) ? trim((string) $badge['class']) : '';
                if ($modifier !== '') {
                    $className .= ' estate-office-crm__badge--' . sanitize_html_class($modifier);
                }
                echo '<li><span class="' . esc_attr($className) . '">' . esc_html($badge['label']) . '</span></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        $editLink = get_edit_post_link($postId);
        if (is_string($editLink) && $editLink !== '') {
            echo '<div class="estate-office-crm__detail-actions">';
            echo '<a class="estate-office-crm__action" href="' . esc_url($editLink) . '">' . esc_html__('Edytuj w kokpicie', 'estate-office') . '</a>';
            echo '</div>';
        }

        echo '</header>';
    }

    /**
     * @param array<int,array{heading:string,rows:array<int,array{label:string,value:string}>}> $cards
     */
    private static function renderDetailCards(array $cards): void
    {
        echo '<div class="estate-office-crm__detail-columns">';
        foreach ($cards as $card) {
            if (empty($card['rows'])) {
                continue;
            }

            echo '<div class="estate-office-crm__detail-card">';
            echo '<h3>' . esc_html($card['heading']) . '</h3>';
            echo '<dl class="estate-office-crm__detail-list">';
            foreach ($card['rows'] as $row) {
                echo '<div class="estate-office-crm__detail-row">';
                echo '<dt>' . esc_html($row['label']) . '</dt>';
                echo '<dd>' . wp_kses_post($row['value']) . '</dd>';
                echo '</div>';
            }
            echo '</dl>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * @param array<int,int> $ids
     * @param callable(int):string $labelCallback
     */
    private static function renderRelationList(string $heading, string $section, array $ids, callable $labelCallback): void
    {
        $postType = self::SECTION_POST_TYPES[$section] ?? '';
        if ($postType === '') {
            return;
        }

        $items = [];
        foreach ($ids as $id) {
            $related = self::getAccessiblePost($id, $postType);
            if (!$related instanceof WP_Post) {
                continue;
            }

            $label = trim((string) $labelCallback($related->ID));
            if ($label === '') {
                continue;
            }

            $items[] = [
                'url'   => self::getDetailLink($section, $related->ID),
                'label' => $label,
            ];
        }

        echo '<section class="estate-office-crm__detail-panel">';
        echo '<h3>' . esc_html($heading) . '</h3>';
        if (!$items) {
            echo '<p>' . esc_html__('Brak powiązanych rekordów.', 'estate-office') . '</p>';
        } else {
            echo '<ul class="estate-office-crm__detail-listing">';
            foreach ($items as $item) {
                echo '<li><a href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    }

    private static function renderStageHistory(int $postId): void
    {
        $history = get_post_meta($postId, 'estate_agreement_stage_history', true);
        if (!is_array($history) || $history === []) {
            return;
        }

        echo '<section class="estate-office-crm__detail-panel">';
        echo '<h3>' . esc_html__('Historia etapów', 'estate-office') . '</h3>';
        echo '<ol class="estate-office-crm__timeline">';
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $stageKey   = (string) ($entry['stage'] ?? '');
            $stageLabel = self::getAgreementStageLabel($stageKey);
            $date       = (string) ($entry['date'] ?? '');

            echo '<li>';
            echo '<span class="estate-office-crm__timeline-stage">' . esc_html($stageLabel) . '</span>';
            if ($date !== '') {
                echo '<span class="estate-office-crm__timeline-date">' . esc_html($date) . '</span>';
            }
            echo '</li>';
        }
        echo '</ol>';
        echo '</section>';
    }

    private static function renderDetailNotFound(): void
    {
        echo '<div class="estate-office-crm__notice estate-office-crm__detail-notice">' . esc_html__('Nie znaleziono rekordu lub brak uprawnień do podglądu.', 'estate-office') . '</div>';
    }

    private static function getAccessiblePost(int $postId, string $postType): ?WP_Post
    {
        if ($postId <= 0 || $postType === '') {
            return null;
        }

        $post = get_post($postId);
        if (!$post instanceof WP_Post || $post->post_type !== $postType) {
            return null;
        }

        if (!current_user_can('read_post', $postId) && !current_user_can('edit_post', $postId)) {
            return null;
        }

        return $post;
    }

    private static function getDetailLink(string $section, int $postId): string
    {
        if ($section === 'dashboard' || !isset(self::SECTIONS[$section])) {
            return '#';
        }

        $url = add_query_arg(self::SECTION_PARAM, $section, self::getBaseUrl());
        $url = add_query_arg(self::RECORD_PARAM, $section, $url);
        $url = add_query_arg(self::RECORD_ID_PARAM, (string) $postId, $url);

        return $url;
    }

    private static function getTermsList(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (!is_array($terms) || $terms === []) {
            return '—';
        }

        $names = array_filter(array_map(static fn($term) => is_object($term) && isset($term->name) ? (string) $term->name : '', $terms));
        if ($names === []) {
            return '—';
        }

        return implode(', ', $names);
    }

    private static function formatRawMeta(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);
        if (is_scalar($value)) {
            $string = (string) $value;
            if ($string !== '') {
                return $string;
            }
        }

        return '—';
    }

    private static function formatPropertyHouseType(int $postId): string
    {
        $value = (string) get_post_meta($postId, 'estate_property_house_type', true);
        if ($value === '') {
            return '—';
        }

        $map = [
            'detached'      => __('Wolnostojący', 'estate-office'),
            'semi_detached' => __('Bliźniak', 'estate-office'),
            'terraced'      => __('Szeregowiec', 'estate-office'),
            'multi_family'  => __('Wielorodzinny', 'estate-office'),
        ];

        return $map[$value] ?? '—';
    }

    private static function formatPropertyLegalStatus(int $postId): string
    {
        $value = (string) get_post_meta($postId, 'estate_property_legal_status', true);
        if ($value === '') {
            return '—';
        }

        $map = [
            'ownership'      => __('Własność', 'estate-office'),
            'coownership'    => __('Współwłasność', 'estate-office'),
            'cooperative'    => __('Spółdzielcze własnościowe prawo do lokalu', 'estate-office'),
            'lease'          => __('Dzierżawa', 'estate-office'),
            'other'          => __('Inne', 'estate-office'),
        ];

        return $map[$value] ?? '—';
    }

    private static function formatPropertyPlotShape(int $postId): string
    {
        $value = (string) get_post_meta($postId, 'estate_property_plot_shape', true);
        if ($value === '') {
            return '—';
        }

        $map = [
            'regular'   => __('Regularny', 'estate-office'),
            'irregular' => __('Nieregularny', 'estate-office'),
        ];

        return $map[$value] ?? '—';
    }

    private static function formatPropertyLandRegister(int $postId): string
    {
        $noRegister = (bool) get_post_meta($postId, 'estate_property_no_land_register', true);
        if ($noRegister) {
            return __('Brak', 'estate-office');
        }

        return self::formatRawMeta($postId, 'estate_property_land_register_number');
    }

    private static function formatBooleanMeta(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);
        $isTrue = false;
        if (is_bool($value)) {
            $isTrue = $value;
        } elseif (is_scalar($value)) {
            $isTrue = (bool) $value;
        }

        return $isTrue ? __('Tak', 'estate-office') : __('Nie', 'estate-office');
    }

    private static function formatAgreementCommission(int $postId): string
    {
        $amount = (string) get_post_meta($postId, 'estate_agreement_commission_amount', true);
        if ($amount === '') {
            return '—';
        }

        $unitKey = (string) get_post_meta($postId, 'estate_agreement_commission_unit', true);
        $unitMap = [
            'percent' => '%',
            'pln'     => 'PLN',
            'eur'     => 'EUR',
            'usd'     => 'USD',
        ];

        $number = number_format_i18n((float) $amount, 2);

        if ($unitKey === 'percent') {
            return sprintf('%s %%', $number);
        }

        $unit = $unitMap[$unitKey] ?? 'PLN';

        return sprintf('%s %s', $number, $unit);
    }

    private static function formatRange(int $postId, string $minKey, string $maxKey, string $unit): string
    {
        $min = (string) get_post_meta($postId, $minKey, true);
        $max = (string) get_post_meta($postId, $maxKey, true);

        if ($min === '' && $max === '') {
            return '—';
        }

        $parts = [];
        if ($min !== '') {
            $parts[] = sprintf('%s %s', number_format_i18n((float) $min, 2), $unit);
        }
        if ($max !== '') {
            $parts[] = sprintf('%s %s', number_format_i18n((float) $max, 2), $unit);
        }

        return implode(' - ', $parts);
    }

    private static function formatIntegerRange(int $postId, string $minKey, string $maxKey): string
    {
        $min = (string) get_post_meta($postId, $minKey, true);
        $max = (string) get_post_meta($postId, $maxKey, true);

        if ($min === '' && $max === '') {
            return '—';
        }

        $parts = [];
        if ($min !== '') {
            $parts[] = number_format_i18n((int) $min);
        }
        if ($max !== '') {
            $parts[] = number_format_i18n((int) $max);
        }

        return implode(' - ', $parts);
    }

    private static function formatCorrespondenceAddress(int $postId): string
    {
        $same = (bool) get_post_meta($postId, 'estate_client_correspondence_same', true);
        if ($same) {
            return __('Taki sam jak główny', 'estate-office');
        }

        $street = (string) get_post_meta($postId, 'estate_client_correspondence_street', true);
        $number = (string) get_post_meta($postId, 'estate_client_correspondence_number', true);
        $unit   = (string) get_post_meta($postId, 'estate_client_correspondence_unit', true);
        $postal = (string) get_post_meta($postId, 'estate_client_correspondence_postal_code', true);
        $city   = (string) get_post_meta($postId, 'estate_client_correspondence_city', true);
        $country = (string) get_post_meta($postId, 'estate_client_correspondence_country', true);

        $parts = [];
        if ($street !== '') {
            $line = $street;
            if ($number !== '') {
                $line .= ' ' . $number;
            }
            if ($unit !== '') {
                $line .= '/' . $unit;
            }
            $parts[] = $line;
        }
        if ($postal !== '') {
            $parts[] = $postal;
        }
        if ($city !== '') {
            $parts[] = $city;
        }
        if ($country !== '') {
            $parts[] = $country;
        }

        if ($parts) {
            return implode(', ', $parts);
        }

        return __('Brak adresu', 'estate-office');
    }

    private static function formatWebsite(string $url): string
    {
        if ($url === '') {
            return '—';
        }

        $sanitized = esc_url($url);
        if ($sanitized === '') {
            return esc_html($url);
        }

        return '<a href="' . $sanitized . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
    }

    private static function formatLeadStatusBadge(string $status): string
    {
        $status = LeadMeta::sanitizeStatus($status);
        $label  = LeadMeta::getStatusLabel($status);

        $class = 'estate-office-crm__badge estate-office-crm__badge--status-' . sanitize_html_class($status);

        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    private static function getAgreementNumberLabel(int $agreementId): string
    {
        $number = self::resolveReference($agreementId, 'estate_agreement_number');
        $stage  = self::getAgreementStageLabel((string) get_post_meta($agreementId, 'estate_agreement_stage', true));

        if ($stage === '—') {
            return $number;
        }

        return sprintf('%s – %s', $number, $stage);
    }

    private static function getClientRelationLabel(int $clientId): string
    {
        return self::resolveClientName($clientId);
    }

    private static function getPropertyRelationLabel(int $propertyId): string
    {
        $reference = self::resolveReference($propertyId, 'estate_property_reference');
        $address   = self::formatPropertyAddress($propertyId);

        return sprintf('%s – %s', $reference, $address);
    }

    private static function getSearchRelationLabel(int $searchId): string
    {
        $reference = self::resolveReference($searchId, 'estate_search_reference');
        $location  = (string) get_post_meta($searchId, 'estate_search_location', true);
        if ($location === '') {
            return $reference;
        }

        return sprintf('%s – %s', $reference, $location);
    }

    /**
     * @return array{label:string,link:string}
     */
    private static function getLeadRecordSummary(int $recordId): array
    {
        if ($recordId <= 0) {
            return [
                'label' => __('Brak powiązania', 'estate-office'),
                'link'  => '',
            ];
        }

        $post = get_post($recordId);
        if (!$post instanceof WP_Post) {
            return [
                'label' => sprintf(__('Rekord #%d', 'estate-office'), $recordId),
                'link'  => '',
            ];
        }

        $title = trim((string) $post->post_title);
        if ($title === '') {
            $title = sprintf(__('Rekord #%d', 'estate-office'), $recordId);
        }

        $section = '';
        foreach (self::SECTION_POST_TYPES as $slug => $postType) {
            if ($postType === $post->post_type) {
                $section = $slug;
                break;
            }
        }

        $link = '';
        if ($section !== '') {
            $accessible = self::getAccessiblePost($recordId, $post->post_type);
            if ($accessible instanceof WP_Post) {
                $link = self::getDetailLink($section, $recordId);
            }
        }

        return [
            'label' => $title,
            'link'  => $link,
        ];
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
                'link'    => self::getDetailLink('clients', $postId),
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

    private static function formatPostDateTime(int $postId): string
    {
        $date = get_post_field('post_date', $postId);
        if (!is_string($date) || $date === '' || $date === '0000-00-00 00:00:00') {
            return '—';
        }

        $dateFormat = (string) get_option('date_format');
        $timeFormat = (string) get_option('time_format');

        if ($dateFormat === '') {
            $dateFormat = 'Y-m-d';
        }
        if ($timeFormat === '') {
            $timeFormat = 'H:i';
        }

        $datePart = mysql2date($dateFormat, $date, true);
        $timePart = mysql2date($timeFormat, $date, true);

        if (!is_string($datePart) || $datePart === '') {
            return '—';
        }

        $timeText = is_string($timePart) && $timePart !== '' ? ' ' . $timePart : '';

        return trim($datePart . $timeText);
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

    private static function formatLeadRecipient(string $name, string $email): string
    {
        $name  = trim($name);
        $email = trim($email);

        if ($email === '') {
            return $name !== '' ? esc_html($name) : '—';
        }

        $link = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';

        if ($name === '') {
            return $link;
        }

        return esc_html($name) . '<br />' . $link;
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
