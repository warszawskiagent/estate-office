<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Query;

use function add_action;
use function add_filter;
use function admin_url;
use function esc_html__;
use function esc_html;
use function esc_url;
use function get_post_meta;
use function get_userdata;
use function is_admin;
use function number_format_i18n;
use function sprintf;

defined('ABSPATH') || exit();

final class PropertyColumns
{
    public static function bootstrap(): void
    {
        add_filter('manage_edit-' . PropertyRegister::POST_TYPE . '_columns', [self::class, 'registerColumns']);
        add_action('manage_' . PropertyRegister::POST_TYPE . '_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . PropertyRegister::POST_TYPE . '_sortable_columns', [self::class, 'registerSortableColumns']);
        add_action('pre_get_posts', [self::class, 'handleSorting']);
    }

    /**
     * @param array<string,string> $columns
     *
     * @return array<string,string>
     */
    public static function registerColumns(array $columns): array
    {
        $newColumns = [
            'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
            'reference'      => esc_html__('Numer oferty', 'estate-office'),
            'title'          => esc_html__('Tytuł', 'estate-office'),
            'address'        => esc_html__('Adres', 'estate-office'),
            'price'          => esc_html__('Cena', 'estate-office'),
            'price_per_sqm'  => esc_html__('Cena za m²', 'estate-office'),
            'area'           => esc_html__('Metraż', 'estate-office'),
            'rooms'          => esc_html__('Pokoje', 'estate-office'),
            'manager'        => esc_html__('Opiekun', 'estate-office'),
            'date'           => esc_html__('Data', 'estate-office'),
        ];

        return $newColumns;
    }

    public static function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'reference':
                self::renderReferenceColumn($postId);
                break;
            case 'address':
                self::renderAddressColumn($postId);
                break;
            case 'price':
                self::renderPriceColumn($postId);
                break;
            case 'price_per_sqm':
                self::renderPricePerSqmColumn($postId);
                break;
            case 'area':
                self::renderAreaColumn($postId);
                break;
            case 'rooms':
                self::renderRoomsColumn($postId);
                break;
            case 'manager':
                self::renderManagerColumn($postId);
                break;
        }
    }

    /**
     * @param array<string,string> $columns
     *
     * @return array<string,string>
     */
    public static function registerSortableColumns(array $columns): array
    {
        $columns['reference']     = 'estate_property_reference';
        $columns['price']         = 'estate_property_price';
        $columns['price_per_sqm'] = 'estate_property_price_per_sqm';
        $columns['area']          = 'estate_property_area';
        $columns['rooms']         = 'estate_property_rooms';

        return $columns;
    }

    public static function handleSorting($query): void
    {
        if (!$query instanceof WP_Query || !is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== PropertyRegister::POST_TYPE) {
            return;
        }

        $orderby = (string) $query->get('orderby');

        $numericMeta = [
            'estate_property_price',
            'estate_property_price_per_sqm',
            'estate_property_area',
            'estate_property_rooms',
        ];

        if ($orderby === 'estate_property_reference') {
            $query->set('meta_key', 'estate_property_reference');
            $query->set('orderby', 'meta_value');
        } elseif (in_array($orderby, $numericMeta, true)) {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value_num');
        }
    }

    private static function renderReferenceColumn(int $postId): void
    {
        $reference = get_post_meta($postId, 'estate_property_reference', true);

        if ($reference === '') {
            echo '—';

            return;
        }

        $url = admin_url('post.php?post=' . $postId . '&action=edit');

        printf('<a href="%s"><strong>%s</strong></a>', esc_url($url), esc_html((string) $reference));
    }

    private static function renderAddressColumn(int $postId): void
    {
        $street = (string) get_post_meta($postId, 'estate_property_street', true);
        $number = (string) get_post_meta($postId, 'estate_property_number', true);
        $city   = (string) get_post_meta($postId, 'estate_property_city', true);

        if ($street === '' && $number === '' && $city === '') {
            echo '—';

            return;
        }

        $parts = array_filter([$street, $number ? $number : null]);
        $address = implode(' ', $parts);

        if ($city !== '') {
            $address = $address !== '' ? sprintf('%s, %s', $address, $city) : $city;
        }

        echo esc_html($address);
    }

    private static function renderPriceColumn(int $postId): void
    {
        $price = (float) get_post_meta($postId, 'estate_property_price', true);

        if ($price <= 0.0) {
            echo '—';

            return;
        }

        printf('%s PLN', esc_html(number_format_i18n($price, 2)));
    }

    private static function renderPricePerSqmColumn(int $postId): void
    {
        $value = (float) get_post_meta($postId, 'estate_property_price_per_sqm', true);

        if ($value <= 0.0) {
            echo '—';

            return;
        }

        printf('%s PLN', esc_html(number_format_i18n($value, 2)));
    }

    private static function renderAreaColumn(int $postId): void
    {
        $area = (float) get_post_meta($postId, 'estate_property_area', true);

        if ($area <= 0.0) {
            echo '—';

            return;
        }

        printf('%s m²', esc_html(number_format_i18n($area, 2)));
    }

    private static function renderRoomsColumn(int $postId): void
    {
        $rooms = (int) get_post_meta($postId, 'estate_property_rooms', true);

        if ($rooms <= 0) {
            echo '—';

            return;
        }

        echo esc_html((string) $rooms);
    }

    private static function renderManagerColumn(int $postId): void
    {
        $managerId = (int) get_post_meta($postId, 'estate_property_manager', true);

        if ($managerId <= 0) {
            echo '—';

            return;
        }

        $user = get_userdata($managerId);

        if (!$user) {
            echo '—';

            return;
        }

        echo esc_html($user->display_name ?: $user->user_login);
    }
}
