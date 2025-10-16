<?php

declare(strict_types=1);

namespace EstateOffice\Activation;

defined('ABSPATH') || exit;

use WP_Post;

use function __;
use function get_page_by_path;
use function is_array;
use function is_wp_error;
use function sanitize_title;
use function wp_create_nav_menu;
use function wp_get_nav_menu_items;
use function wp_get_nav_menu_object;
use function wp_insert_post;
use function wp_slash;
use function wp_update_nav_menu_item;
use function wp_update_nav_menu_object;
use function wp_update_post;

final class Pages
{
    private const MENU_SLUG = 'estate-office-navigation';

    /**
     * Creates required public pages and navigation.
     */
    public static function activate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/post.php';
        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

        $pages = [
            'estate-office-crm'           => [
                'title'     => __('Panel CRM', 'estate-office'),
                'shortcode' => '[estate_office_crm]',
            ],
            'estate-office-offers'        => [
                'title'     => __('Oferty nieruchomości', 'estate-office'),
                'shortcode' => '[estate_office_offers]',
            ],
            'estate-office-oferty-sprzedaz' => [
                'title'     => __('Oferty na sprzedaż', 'estate-office'),
                'shortcode' => '[estate_office_offers transaction="sprzedaz" show_legend="no"]',
            ],
            'estate-office-oferty-wynajem' => [
                'title'     => __('Oferty na wynajem', 'estate-office'),
                'shortcode' => '[estate_office_offers transaction="wynajem" show_legend="no"]',
            ],
            'estate-office-agenci'        => [
                'title'     => __('Nasi agenci', 'estate-office'),
                'shortcode' => '[estate_office_agents]',
            ],
        ];

        $createdPages = [];

        foreach ($pages as $slug => $config) {
            $createdPages[$slug] = self::ensurePage($slug, $config['title'], $config['shortcode']);
        }

        self::ensureMenu($createdPages, $pages);
    }

    private static function ensurePage(string $slug, string $title, string $shortcode): int
    {
        $existing = get_page_by_path($slug);

        if ($existing instanceof WP_Post) {
            $content = (string) $existing->post_content;

            if (strpos($content, $shortcode) === false) {
                $content = trim($content);
                $content = $content === ''
                    ? $shortcode
                    : $content . "\n\n" . $shortcode;

                wp_update_post([
                    'ID'           => $existing->ID,
                    'post_content' => wp_slash($content),
                ]);
            }

            return (int) $existing->ID;
        }

        $pageId = wp_insert_post([
            'post_title'   => wp_slash($title),
            'post_name'    => sanitize_title($slug),
            'post_content' => wp_slash($shortcode),
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        return is_wp_error($pageId) ? 0 : (int) $pageId;
    }

    /**
     * @param array<string,int> $pageIds
     * @param array<string,array<string,string>> $pages
     */
    private static function ensureMenu(array $pageIds, array $pages): void
    {
        $menu = wp_get_nav_menu_object(self::MENU_SLUG);

        if (!$menu) {
            $menu = wp_get_nav_menu_object(__('Estate Office', 'estate-office'));
        }

        if (!$menu) {
            $menuId = wp_create_nav_menu(__('Estate Office', 'estate-office'));

            if (is_wp_error($menuId)) {
                return;
            }

            wp_update_nav_menu_object((int) $menuId, [
                'slug'      => self::MENU_SLUG,
                'menu-name' => __('Estate Office', 'estate-office'),
            ]);

            $menu = wp_get_nav_menu_object((int) $menuId);
        }

        if (!$menu) {
            return;
        }

        $menuId = (int) $menu->term_id;

        $existingItems    = wp_get_nav_menu_items($menuId, ['post_status' => 'publish,draft']);
        $existingObjectIds = [];

        if (is_array($existingItems)) {
            foreach ($existingItems as $item) {
                $existingObjectIds[] = (int) $item->object_id;
            }
        }

        foreach ($pageIds as $slug => $pageId) {
            if ($pageId <= 0 || in_array($pageId, $existingObjectIds, true)) {
                continue;
            }

            wp_update_nav_menu_item($menuId, 0, [
                'menu-item-title'     => wp_slash($pages[$slug]['title']),
                'menu-item-object-id' => $pageId,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ]);
        }
    }
}
