<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post_Type;

use function _x;
use function __;
use function add_action;
use function do_action;
use function flush_rewrite_rules;
use function register_post_type;

defined('ABSPATH') || exit;

final class LeadRegister
{
    public const POST_TYPE = 'estate_lead';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerPostType']);
    }

    public static function registerPostType(): void
    {
        $labels = [
            'name'                     => _x('Leady', 'post type general name', 'estate-office'),
            'singular_name'            => _x('Lead', 'post type singular name', 'estate-office'),
            'menu_name'                => _x('Leady', 'admin menu', 'estate-office'),
            'name_admin_bar'           => _x('Lead', 'add new on admin bar', 'estate-office'),
            'add_new'                  => __('Dodaj nowy', 'estate-office'),
            'add_new_item'             => __('Dodaj nowy lead', 'estate-office'),
            'new_item'                 => __('Nowy lead', 'estate-office'),
            'edit_item'                => __('Edytuj lead', 'estate-office'),
            'view_item'                => __('Zobacz lead', 'estate-office'),
            'all_items'                => __('Wszystkie leady', 'estate-office'),
            'search_items'             => __('Szukaj leadów', 'estate-office'),
            'not_found'                => __('Nie znaleziono leadów.', 'estate-office'),
            'not_found_in_trash'       => __('Nie znaleziono leadów w koszu.', 'estate-office'),
            'items_list'               => __('Lista leadów', 'estate-office'),
            'items_list_navigation'    => __('Nawigacja listy leadów', 'estate-office'),
            'filter_items_list'        => __('Filtruj listę leadów', 'estate-office'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_nav_menus'  => false,
            'show_in_admin_bar'  => false,
            'capability_type'    => ['estate_lead', 'estate_leads'],
            'map_meta_cap'       => true,
            'supports'           => ['title'],
            'has_archive'        => false,
            'rewrite'            => false,
            'publicly_queryable' => false,
            'menu_icon'          => 'dashicons-email-alt2',
        ];

        $postType = register_post_type(self::POST_TYPE, $args);

        if ($postType instanceof WP_Post_Type) {
            do_action('estate_office/lead/post_type_registered', $postType);
        }
    }

    public static function activate(): void
    {
        self::registerPostType();
        flush_rewrite_rules();
    }
}
