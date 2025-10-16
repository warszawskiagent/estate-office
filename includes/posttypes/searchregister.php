<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post_Type;

defined('ABSPATH') || exit;

final class SearchRegister
{
    public const POST_TYPE = 'estate_search';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerPostType']);
    }

    public static function registerPostType(): void
    {
        $labels = [
            'name'                     => _x('Poszukiwania', 'post type general name', 'estate-office'),
            'singular_name'            => _x('Poszukiwanie', 'post type singular name', 'estate-office'),
            'menu_name'                => _x('Poszukiwania', 'admin menu', 'estate-office'),
            'name_admin_bar'           => _x('Poszukiwanie', 'add new on admin bar', 'estate-office'),
            'add_new'                  => __('Dodaj nowe', 'estate-office'),
            'add_new_item'             => __('Dodaj nowe poszukiwanie', 'estate-office'),
            'new_item'                 => __('Nowe poszukiwanie', 'estate-office'),
            'edit_item'                => __('Edytuj poszukiwanie', 'estate-office'),
            'view_item'                => __('Zobacz poszukiwanie', 'estate-office'),
            'all_items'                => __('Wszystkie poszukiwania', 'estate-office'),
            'search_items'             => __('Szukaj poszukiwań', 'estate-office'),
            'parent_item_colon'        => __('Nadrzędne poszukiwania:', 'estate-office'),
            'not_found'                => __('Nie znaleziono poszukiwań.', 'estate-office'),
            'not_found_in_trash'       => __('Nie znaleziono poszukiwań w koszu.', 'estate-office'),
            'featured_image'           => __('Grafika wyróżniająca', 'estate-office'),
            'set_featured_image'       => __('Ustaw grafikę wyróżniającą', 'estate-office'),
            'remove_featured_image'    => __('Usuń grafikę wyróżniającą', 'estate-office'),
            'use_featured_image'       => __('Użyj jako grafiki wyróżniającej', 'estate-office'),
            'archives'                 => __('Archiwum poszukiwań', 'estate-office'),
            'insert_into_item'         => __('Wstaw do poszukiwania', 'estate-office'),
            'uploaded_to_this_item'    => __('Przesłane do poszukiwania', 'estate-office'),
            'filter_items_list'        => __('Filtruj listę poszukiwań', 'estate-office'),
            'items_list_navigation'    => __('Nawigacja listy poszukiwań', 'estate-office'),
            'items_list'               => __('Lista poszukiwań', 'estate-office'),
        ];

        $supports = [
            'title',
            'editor',
            'revisions',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => ['estate_search', 'estate_searches'],
            'map_meta_cap'       => true,
            'supports'           => $supports,
            'rewrite'            => [
                'slug'       => 'poszukiwania',
                'with_front' => false,
            ],
            'has_archive'        => false,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-search',
        ];

        $postType = register_post_type(self::POST_TYPE, $args);

        if ($postType instanceof WP_Post_Type) {
            do_action('estate_office/search/post_type_registered', $postType);
        }
    }

    public static function activate(): void
    {
        self::registerPostType();
        flush_rewrite_rules();
    }
}
