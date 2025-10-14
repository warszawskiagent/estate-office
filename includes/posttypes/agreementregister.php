<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post_Type;

defined('ABSPATH') || exit;

final class AgreementRegister
{
    public const POST_TYPE = 'estate_agreement';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerPostType']);
    }

    public static function registerPostType(): void
    {
        $labels = [
            'name'                     => _x('Umowy', 'post type general name', 'estate-office'),
            'singular_name'            => _x('Umowa', 'post type singular name', 'estate-office'),
            'menu_name'                => _x('Umowy', 'admin menu', 'estate-office'),
            'name_admin_bar'           => _x('Umowa', 'add new on admin bar', 'estate-office'),
            'add_new'                  => __('Dodaj nową', 'estate-office'),
            'add_new_item'             => __('Dodaj nową umowę', 'estate-office'),
            'new_item'                 => __('Nowa umowa', 'estate-office'),
            'edit_item'                => __('Edytuj umowę', 'estate-office'),
            'view_item'                => __('Zobacz umowę', 'estate-office'),
            'all_items'                => __('Wszystkie umowy', 'estate-office'),
            'search_items'             => __('Szukaj umów', 'estate-office'),
            'parent_item_colon'        => __('Nadrzędne umowy:', 'estate-office'),
            'not_found'                => __('Nie znaleziono umów.', 'estate-office'),
            'not_found_in_trash'       => __('Nie znaleziono umów w koszu.', 'estate-office'),
            'featured_image'           => __('Grafika wyróżniająca', 'estate-office'),
            'set_featured_image'       => __('Ustaw grafikę wyróżniającą', 'estate-office'),
            'remove_featured_image'    => __('Usuń grafikę wyróżniającą', 'estate-office'),
            'use_featured_image'       => __('Użyj jako grafiki wyróżniającej', 'estate-office'),
            'archives'                 => __('Archiwum umów', 'estate-office'),
            'insert_into_item'         => __('Wstaw do umowy', 'estate-office'),
            'uploaded_to_this_item'    => __('Przesłane do umowy', 'estate-office'),
            'filter_items_list'        => __('Filtruj listę umów', 'estate-office'),
            'items_list_navigation'    => __('Nawigacja listy umów', 'estate-office'),
            'items_list'               => __('Lista umów', 'estate-office'),
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
            'capability_type'    => ['estate_agreement', 'estate_agreements'],
            'map_meta_cap'       => true,
            'supports'           => $supports,
            'rewrite'            => [
                'slug'       => 'umowy',
                'with_front' => false,
            ],
            'has_archive'        => false,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-media-document',
        ];

        $postType = register_post_type(self::POST_TYPE, $args);

        if ($postType instanceof WP_Post_Type) {
            do_action('estate_office/agreement/post_type_registered', $postType);
        }
    }

    public static function activate(): void
    {
        self::registerPostType();
        flush_rewrite_rules();
    }
}
