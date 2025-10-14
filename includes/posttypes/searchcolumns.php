<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Query;

use function add_action;
use function add_filter;
use function admin_url;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_post_meta;
use function is_admin;
use function number_format_i18n;
use function sprintf;

defined('ABSPATH') || exit;

final class SearchColumns
{
    public static function bootstrap(): void
    {
        add_filter('manage_edit-' . SearchRegister::POST_TYPE . '_columns', [self::class, 'registerColumns']);
        add_action('manage_' . SearchRegister::POST_TYPE . '_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . SearchRegister::POST_TYPE . '_sortable_columns', [self::class, 'sortableColumns']);
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
            'cb'               => $columns['cb'] ?? '<input type="checkbox" />',
            'reference'        => esc_html__('Numer poszukiwania', 'estate-office'),
            'title'            => esc_html__('Tytuł', 'estate-office'),
            'property_type'    => esc_html__('Rodzaj nieruchomości', 'estate-office'),
            'budget'           => esc_html__('Budżet', 'estate-office'),
            'location'         => esc_html__('Lokalizacja', 'estate-office'),
            'transaction_type' => esc_html__('Typ transakcji', 'estate-office'),
            'date'             => esc_html__('Data', 'estate-office'),
        ];

        return $newColumns;
    }

    public static function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'reference':
                self::renderReferenceColumn($postId);
                break;
            case 'property_type':
                self::renderPropertyTypeColumn($postId);
                break;
            case 'budget':
                self::renderBudgetColumn($postId);
                break;
            case 'location':
                self::renderLocationColumn($postId);
                break;
            case 'transaction_type':
                self::renderTransactionTypeColumn($postId);
                break;
        }
    }

    /**
     * @param array<string,string> $columns
     *
     * @return array<string,string>
     */
    public static function sortableColumns(array $columns): array
    {
        $columns['reference']        = 'estate_search_reference';
        $columns['property_type']    = 'estate_search_property_type';
        $columns['budget']           = 'estate_search_price_min';
        $columns['transaction_type'] = 'estate_search_transaction_type';

        return $columns;
    }

    public static function handleSorting($query): void
    {
        if (!is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');

        if ($postType !== SearchRegister::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'estate_search_reference' || $orderby === 'estate_search_transaction_type' || $orderby === 'estate_search_property_type') {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }

        if ($orderby === 'estate_search_price_min') {
            $query->set('meta_key', 'estate_search_price_min');
            $query->set('orderby', 'meta_value_num');
        }
    }

    private static function renderReferenceColumn(int $postId): void
    {
        $reference = (string) get_post_meta($postId, 'estate_search_reference', true);

        if ($reference === '') {
            echo '—';

            return;
        }

        $url = admin_url('post.php?post=' . $postId . '&action=edit');

        printf('<a href="%s"><strong>%s</strong></a>', esc_url($url), esc_html($reference));
    }

    private static function renderPropertyTypeColumn(int $postId): void
    {
        $value = (string) get_post_meta($postId, 'estate_search_property_type', true);

        if ($value === '') {
            echo '—';

            return;
        }

        $labels = SearchMeta::PROPERTY_TYPES;

        echo esc_html($labels[$value] ?? $value);
    }

    private static function renderBudgetColumn(int $postId): void
    {
        $min = (float) get_post_meta($postId, 'estate_search_price_min', true);
        $max = (float) get_post_meta($postId, 'estate_search_price_max', true);

        if ($min <= 0 && $max <= 0) {
            echo '—';

            return;
        }

        if ($min > 0 && $max > 0) {
            printf('%s – %s PLN', esc_html(number_format_i18n($min, 0)), esc_html(number_format_i18n($max, 0)));

            return;
        }

        if ($min > 0) {
            printf('%s+ PLN', esc_html(number_format_i18n($min, 0)));

            return;
        }

        printf('≤ %s PLN', esc_html(number_format_i18n($max, 0)));
    }

    private static function renderLocationColumn(int $postId): void
    {
        $location = (string) get_post_meta($postId, 'estate_search_location', true);

        if ($location === '') {
            echo '—';

            return;
        }

        echo esc_html($location);
    }

    private static function renderTransactionTypeColumn(int $postId): void
    {
        $value = (string) get_post_meta($postId, 'estate_search_transaction_type', true);

        if ($value === '') {
            echo '—';

            return;
        }

        $labels = SearchMeta::TRANSACTION_TYPES;

        echo esc_html($labels[$value] ?? $value);
    }
}
