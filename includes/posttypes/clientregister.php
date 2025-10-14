<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post_Type;

defined('ABSPATH') || exit;

final class ClientRegister
{
    public const POST_TYPE = 'estate_client';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerPostType']);
    }

    public static function registerPostType(): void
    {
        $labels = [
            'name'                     => _x('Klienci', 'post type general name', 'estate-office'),
            'singular_name'            => _x('Klient', 'post type singular name', 'estate-office'),
            'menu_name'                => _x('Klienci', 'admin menu', 'estate-office'),
            'name_admin_bar'           => _x('Klient', 'add new on admin bar', 'estate-office'),
            'add_new'                  => __('Dodaj nowego', 'estate-office'),
            'add_new_item'             => __('Dodaj nowego klienta', 'estate-office'),
            'new_item'                 => __('Nowy klient', 'estate-office'),
            'edit_item'                => __('Edytuj klienta', 'estate-office'),
            'view_item'                => __('Zobacz klienta', 'estate-office'),
            'all_items'                => __('Wszyscy klienci', 'estate-office'),
            'search_items'             => __('Szukaj klientów', 'estate-office'),
            'parent_item_colon'        => __('Nadrzędni klienci:', 'estate-office'),
            'not_found'                => __('Nie znaleziono klientów.', 'estate-office'),
            'not_found_in_trash'       => __('Nie znaleziono klientów w koszu.', 'estate-office'),
            'featured_image'           => __('Avatar', 'estate-office'),
            'set_featured_image'       => __('Ustaw avatar', 'estate-office'),
            'remove_featured_image'    => __('Usuń avatar', 'estate-office'),
            'use_featured_image'       => __('Użyj jako avataru', 'estate-office'),
            'archives'                 => __('Archiwum klientów', 'estate-office'),
            'insert_into_item'         => __('Wstaw do klienta', 'estate-office'),
            'uploaded_to_this_item'    => __('Przesłane do klienta', 'estate-office'),
            'filter_items_list'        => __('Filtruj listę klientów', 'estate-office'),
            'items_list_navigation'    => __('Nawigacja listy klientów', 'estate-office'),
            'items_list'               => __('Lista klientów', 'estate-office'),
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
            'show_in_menu'       => 'estate-office-crm',
            'capability_type'    => ['estate_client', 'estate_clients'],
            'map_meta_cap'       => true,
            'supports'           => $supports,
            'has_archive'        => false,
            'rewrite'            => [
                'slug'       => 'klienci',
                'with_front' => false,
            ],
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-businessperson',
        ];

        $postType = register_post_type(self::POST_TYPE, $args);

        if ($postType instanceof WP_Post_Type) {
            do_action('estate_office/client/post_type_registered', $postType);
        }
    }

    public static function activate(): void
    {
        self::registerPostType();
        flush_rewrite_rules();
    }
}
