<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Query;

use function add_action;
use function add_filter;
use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_post_meta;
use function get_user_by;
use function is_admin;

defined('ABSPATH') || exit;

final class ClientColumns
{
    public static function bootstrap(): void
    {
        add_filter('manage_edit-' . ClientRegister::POST_TYPE . '_columns', [self::class, 'registerColumns']);
        add_action('manage_' . ClientRegister::POST_TYPE . '_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . ClientRegister::POST_TYPE . '_sortable_columns', [self::class, 'sortableColumns']);
        add_action('pre_get_posts', [self::class, 'handleSorting']);
    }

    /**
     * @param array<string,string> $columns
     *
     * @return array<string,string>
     */
    public static function registerColumns(array $columns): array
    {
        unset($columns['title']);

        $newColumns = [
            'cb'        => $columns['cb'] ?? '<input type="checkbox" />',
            'reference' => esc_html__('Numer klienta', 'estate-office'),
            'title'     => esc_html__('Imię i nazwisko / Nazwa', 'estate-office'),
            'address'   => esc_html__('Adres', 'estate-office'),
            'phone'     => esc_html__('Telefon', 'estate-office'),
            'email'     => esc_html__('E-mail', 'estate-office'),
            'manager'   => esc_html__('Opiekun', 'estate-office'),
            'date'      => esc_html__('Data', 'estate-office'),
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
            case 'phone':
                self::renderPhoneColumn($postId);
                break;
            case 'email':
                self::renderEmailColumn($postId);
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
    public static function sortableColumns(array $columns): array
    {
        $columns['reference'] = 'estate_client_reference';
        $columns['phone']     = 'estate_client_phone';
        $columns['email']     = 'estate_client_email';
        $columns['manager']   = 'estate_client_manager';

        return $columns;
    }

    public static function handleSorting($query): void
    {
        if (!is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');

        if ($postType !== ClientRegister::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'estate_client_reference' || $orderby === 'estate_client_phone' || $orderby === 'estate_client_email') {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }

        if ($orderby === 'estate_client_manager') {
            $query->set('meta_key', 'estate_client_manager');
            $query->set('orderby', 'meta_value_num');
        }
    }

    private static function renderReferenceColumn(int $postId): void
    {
        $reference = (string) get_post_meta($postId, 'estate_client_reference', true);

        if ($reference === '') {
            echo '—';

            return;
        }

        $url = admin_url('post.php?post=' . $postId . '&action=edit');

        printf('<a href="%s"><strong>%s</strong></a>', esc_url($url), esc_html($reference));
    }

    private static function renderAddressColumn(int $postId): void
    {
        $street  = (string) get_post_meta($postId, 'estate_client_address_street', true);
        $number  = (string) get_post_meta($postId, 'estate_client_address_number', true);
        $unit    = (string) get_post_meta($postId, 'estate_client_address_unit', true);
        $postal  = (string) get_post_meta($postId, 'estate_client_address_postal_code', true);
        $city    = (string) get_post_meta($postId, 'estate_client_address_city', true);

        if ($street === '' && $number === '' && $postal === '' && $city === '') {
            echo '—';

            return;
        }

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
        } elseif ($number !== '') {
            $parts[] = $number;
        }

        if ($postal !== '' || $city !== '') {
            $cityLine = trim($postal . ' ' . $city);
            if ($cityLine !== '') {
                $parts[] = $cityLine;
            }
        }

        if (empty($parts)) {
            echo '—';

            return;
        }

        echo esc_html(implode(', ', $parts));
    }

    private static function renderPhoneColumn(int $postId): void
    {
        $phone = (string) get_post_meta($postId, 'estate_client_phone', true);

        if ($phone === '') {
            echo '—';

            return;
        }

        echo esc_html($phone);
    }

    private static function renderEmailColumn(int $postId): void
    {
        $email = (string) get_post_meta($postId, 'estate_client_email', true);

        if ($email === '') {
            echo '—';

            return;
        }

        printf('<a href="mailto:%s">%s</a>', esc_attr($email), esc_html($email));
    }

    private static function renderManagerColumn(int $postId): void
    {
        $managerId = (int) get_post_meta($postId, 'estate_client_manager', true);

        if ($managerId <= 0) {
            echo '—';

            return;
        }

        $user = get_user_by('id', $managerId);

        if (!$user) {
            echo '—';

            return;
        }

        echo esc_html($user->display_name ?: $user->user_login);
    }
}
