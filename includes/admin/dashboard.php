<?php

declare(strict_types=1);

namespace EstateOffice\Admin;

use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchRegister;
use WP_Screen;
use WP_User;

use function absint;
use function add_action;
use function admin_url;
use function array_slice;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function get_user_by;
use function number_format_i18n;
use function plugins_url;
use function printf;
use function sprintf;
use function uasort;
use function wp_count_posts;
use function wp_die;
use function wp_enqueue_style;
use function __;

use const ARRAY_A;
use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

final class Dashboard
{
    private const SCREEN = 'toplevel_page_estate-office-crm';

    /**
     * Hook assets for the dashboard screen.
     */
    public static function bootstrap(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || $screen->id !== self::SCREEN) {
            return;
        }

        wp_enqueue_style(
            'estate-office-dashboard',
            plugins_url('assets/css/dashboard.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );
    }

    public static function render(): void
    {
        if (!current_user_can('edit_estate_properties')) {
            wp_die(esc_html__('Nie masz uprawnień do przeglądania tej strony.', 'estate-office'));
        }

        $stats      = self::getStats();
        $topAgents  = self::getTopAgents();
        $shortcuts  = self::getShortcuts();

        echo '<div class="wrap estate-office-dashboard">';
        echo '<h1>' . esc_html__('Estate Office CRM', 'estate-office') . '</h1>';
        echo '<p>' . esc_html__('Witaj w module CRM. Skorzystaj z poniższych skrótów i zestawień, aby szybko przejść do kluczowych danych.', 'estate-office') . '</p>';

        if ($shortcuts) {
            echo '<div class="estate-office-dashboard__shortcuts">';
            foreach ($shortcuts as $shortcut) {
                printf(
                    '<a class="button %1$s" href="%2$s">%3$s</a>',
                    esc_attr($shortcut['class']),
                    esc_url($shortcut['href']),
                    esc_html($shortcut['label'])
                );
            }
            echo '</div>';
        }

        if ($stats) {
            echo '<div class="estate-office-dashboard__stats">';
            foreach ($stats as $stat) {
                echo '<div class="estate-office-dashboard__stat">';
                echo '<span class="estate-office-dashboard__stat-label">' . esc_html($stat['label']) . '</span>';
                echo '<strong class="estate-office-dashboard__stat-value">' . esc_html(number_format_i18n($stat['count'])) . '</strong>';
                if ($stat['link']) {
                    printf('<a class="estate-office-dashboard__stat-link" href="%s">%s</a>', esc_url($stat['link']), esc_html__('Przejdź do listy', 'estate-office'));
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<div class="estate-office-dashboard__panels">';
        echo '<div class="estate-office-dashboard__panel">';
        echo '<h2>' . esc_html__('Najaktywniejsi agenci', 'estate-office') . '</h2>';

        if ($topAgents) {
            echo '<table class="widefat striped">';
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
            echo '<p>' . esc_html__('Brak danych o aktywności agentów. Przypisz opiekunów do nieruchomości, klientów lub poszukiwań, aby zobaczyć ranking.', 'estate-office') . '</p>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * @return array<int, array{label:string,count:int,link:string|null}>
     */
    private static function getStats(): array
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
            $counts = wp_count_posts($postType['type']);
            $published = $counts && isset($counts->publish) ? (int) $counts->publish : 0;
            $stats[] = [
                'label' => $postType['label'],
                'count' => $published,
                'link'  => admin_url('edit.php?post_type=' . $postType['type']),
            ];
        }

        return $stats;
    }

    /**
     * @return array<int, array{user:WP_User,total:int,breakdown:array<string,int>}>
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

    /**
     * @return array<int, array{label:string,href:string,class:string}>
     */
    private static function getShortcuts(): array
    {
        return [
            [
                'label' => __('Dodaj nową umowę', 'estate-office'),
                'href'  => admin_url('post-new.php?post_type=' . AgreementRegister::POST_TYPE),
                'class' => 'button-primary',
            ],
            [
                'label' => __('Przeglądaj nieruchomości', 'estate-office'),
                'href'  => admin_url('edit.php?post_type=' . PropertyRegister::POST_TYPE),
                'class' => '',
            ],
            [
                'label' => __('Przeglądaj poszukiwania', 'estate-office'),
                'href'  => admin_url('edit.php?post_type=' . SearchRegister::POST_TYPE),
                'class' => '',
            ],
            [
                'label' => __('Przeglądaj klientów', 'estate-office'),
                'href'  => admin_url('edit.php?post_type=' . ClientRegister::POST_TYPE),
                'class' => '',
            ],
        ];
    }
}
