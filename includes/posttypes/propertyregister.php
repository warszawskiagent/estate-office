<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post_Type;

defined('ABSPATH') || exit;

final class PropertyRegister
{
    public const POST_TYPE = 'estate_property';

    /**
     * Registers hooks.
     */
    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerPostType']);
        add_action('init', [self::class, 'registerTaxonomies']);
    }

    /**
     * Registers the property post type used by the CRM.
     */
    public static function registerPostType(): void
    {
        $labels = [
            'name'                     => _x('Nieruchomości', 'post type general name', 'estate-office'),
            'singular_name'            => _x('Nieruchomość', 'post type singular name', 'estate-office'),
            'menu_name'                => _x('Nieruchomości', 'admin menu', 'estate-office'),
            'name_admin_bar'           => _x('Nieruchomość', 'add new on admin bar', 'estate-office'),
            'add_new'                  => __('Dodaj nową', 'estate-office'),
            'add_new_item'             => __('Dodaj nową nieruchomość', 'estate-office'),
            'new_item'                 => __('Nowa nieruchomość', 'estate-office'),
            'edit_item'                => __('Edytuj nieruchomość', 'estate-office'),
            'view_item'                => __('Zobacz nieruchomość', 'estate-office'),
            'all_items'                => __('Wszystkie nieruchomości', 'estate-office'),
            'search_items'             => __('Szukaj nieruchomości', 'estate-office'),
            'parent_item_colon'        => __('Nadrzędne nieruchomości:', 'estate-office'),
            'not_found'                => __('Nie znaleziono nieruchomości.', 'estate-office'),
            'not_found_in_trash'       => __('Nie znaleziono nieruchomości w koszu.', 'estate-office'),
            'featured_image'           => __('Zdjęcie główne', 'estate-office'),
            'set_featured_image'       => __('Ustaw zdjęcie główne', 'estate-office'),
            'remove_featured_image'    => __('Usuń zdjęcie główne', 'estate-office'),
            'use_featured_image'       => __('Użyj jako zdjęcia głównego', 'estate-office'),
            'archives'                 => __('Archiwum nieruchomości', 'estate-office'),
            'insert_into_item'         => __('Wstaw do nieruchomości', 'estate-office'),
            'uploaded_to_this_item'    => __('Przesłane do nieruchomości', 'estate-office'),
            'filter_items_list'        => __('Filtruj listę nieruchomości', 'estate-office'),
            'items_list_navigation'    => __('Nawigacja listy nieruchomości', 'estate-office'),
            'items_list'               => __('Lista nieruchomości', 'estate-office'),
        ];

        $supports = [
            'title',
            'editor',
            'thumbnail',
            'custom-fields',
            'excerpt',
            'revisions',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'estate-office-crm',
            'capability_type'    => ['estate_property', 'estate_properties'],
            'map_meta_cap'       => true,
            'supports'           => $supports,
            'rewrite'            => [
                'slug'       => 'oferty',
                'with_front' => false,
            ],
            'has_archive'        => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-building',
        ];

        $postType = register_post_type(self::POST_TYPE, $args);

        if ($postType instanceof WP_Post_Type) {
            do_action('estate_office/property/post_type_registered', $postType);
        }
    }

    /**
     * Registers taxonomies used to categorise properties on the public site.
     */
    public static function registerTaxonomies(): void
    {
        $taxonomies = [
            'estate_transaction_type' => [
                'singular' => __('Typ transakcji', 'estate-office'),
                'plural'   => __('Typy transakcji', 'estate-office'),
            ],
            'estate_property_type' => [
                'singular' => __('Rodzaj nieruchomości', 'estate-office'),
                'plural'   => __('Rodzaje nieruchomości', 'estate-office'),
            ],
            'estate_city' => [
                'singular' => __('Miasto', 'estate-office'),
                'plural'   => __('Miasta', 'estate-office'),
            ],
            'estate_district' => [
                'singular' => __('Dzielnica', 'estate-office'),
                'plural'   => __('Dzielnice', 'estate-office'),
            ],
        ];

        foreach ($taxonomies as $taxonomy => $labels) {
            $args = [
                'labels'            => [
                    'name'              => $labels['plural'],
                    'singular_name'     => $labels['singular'],
                    'search_items'      => sprintf(__('Szukaj: %s', 'estate-office'), $labels['plural']),
                    'all_items'         => sprintf(__('Wszystkie: %s', 'estate-office'), $labels['plural']),
                    'parent_item'       => sprintf(__('Nadrzędny: %s', 'estate-office'), $labels['singular']),
                    'parent_item_colon' => sprintf(__('Nadrzędny: %s:', 'estate-office'), $labels['singular']),
                    'edit_item'         => sprintf(__('Edytuj %s', 'estate-office'), $labels['singular']),
                    'update_item'       => sprintf(__('Aktualizuj %s', 'estate-office'), $labels['singular']),
                    'add_new_item'      => sprintf(__('Dodaj %s', 'estate-office'), $labels['singular']),
                    'new_item_name'     => sprintf(__('Nowa nazwa: %s', 'estate-office'), $labels['singular']),
                    'menu_name'         => $labels['plural'],
                ],
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_in_menu'      => false,
                'show_in_nav_menus' => false,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'rewrite'           => [
                    'with_front' => false,
                ],
            ];

            register_taxonomy($taxonomy, self::POST_TYPE, $args);
        }
    }

    /**
     * Runs on plugin activation to ensure rewrite rules are updated.
     */
    public static function activate(): void
    {
        self::registerPostType();
        self::registerTaxonomies();
        flush_rewrite_rules();
    }
}
