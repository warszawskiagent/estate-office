<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post;
use WP_Query;
use WP_User;

use function absint;
use function add_action;
use function add_filter;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_edit_post_link;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_user_by;
use function is_admin;
use function sprintf;

defined('ABSPATH') || exit;

final class LeadColumns
{
    public static function bootstrap(): void
    {
        add_filter('manage_edit-' . LeadRegister::POST_TYPE . '_columns', [self::class, 'columns']);
        add_action('manage_' . LeadRegister::POST_TYPE . '_posts_custom_column', [self::class, 'render'], 10, 2);
        add_filter('manage_edit-' . LeadRegister::POST_TYPE . '_sortable_columns', [self::class, 'sortable']);
        add_action('pre_get_posts', [self::class, 'handleSorting']);
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public static function columns(array $columns): array
    {
        $ordered = [];
        $ordered['cb']      = $columns['cb'] ?? '<input type="checkbox" />';
        $ordered['title']   = esc_html__('Lead', 'estate-office');
        $ordered['contact'] = esc_html__('Dane kontaktowe', 'estate-office');
        $ordered['status']  = esc_html__('Status', 'estate-office');
        $ordered['assigned']= esc_html__('Przypisany do', 'estate-office');
        $ordered['context'] = esc_html__('Kontekst', 'estate-office');
        $ordered['date']    = $columns['date'] ?? esc_html__('Data', 'estate-office');

        return $ordered;
    }

    public static function render(string $column, int $postId): void
    {
        switch ($column) {
            case 'contact':
                self::renderContactColumn($postId);
                break;
            case 'status':
                self::renderStatusColumn($postId);
                break;
            case 'assigned':
                self::renderAssignedColumn($postId);
                break;
            case 'context':
                self::renderContextColumn($postId);
                break;
        }
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public static function sortable(array $columns): array
    {
        $columns['status']   = LeadMeta::META_STATUS;
        $columns['assigned'] = LeadMeta::META_ASSIGNED;

        return $columns;
    }

    public static function handleSorting(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');
        if ($postType !== LeadRegister::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === LeadMeta::META_STATUS) {
            $query->set('meta_key', LeadMeta::META_STATUS);
            $query->set('orderby', 'meta_value');
        }

        if ($orderby === LeadMeta::META_ASSIGNED) {
            $query->set('meta_key', LeadMeta::META_ASSIGNED);
            $query->set('orderby', 'meta_value_num');
        }
    }

    private static function renderContactColumn(int $postId): void
    {
        $name  = (string) get_post_meta($postId, LeadMeta::META_NAME, true);
        $email = (string) get_post_meta($postId, LeadMeta::META_EMAIL, true);
        $phone = (string) get_post_meta($postId, LeadMeta::META_PHONE, true);

        if ($name !== '') {
            echo '<strong>' . esc_html($name) . '</strong><br />';
        }

        if ($email !== '') {
            echo esc_html($email) . '<br />';
        }

        if ($phone !== '') {
            echo esc_html($phone);
            return;
        }

        if ($name === '' && $email === '' && $phone === '') {
            echo '&mdash;';
        }
    }

    private static function renderStatusColumn(int $postId): void
    {
        $status = (string) get_post_meta($postId, LeadMeta::META_STATUS, true);
        if ($status === '') {
            $status = 'new';
        }

        $status = LeadMeta::sanitizeStatus($status);

        echo esc_html(LeadMeta::getStatusLabel($status));
    }

    private static function renderAssignedColumn(int $postId): void
    {
        $assigned = absint((int) get_post_meta($postId, LeadMeta::META_ASSIGNED, true));
        if ($assigned <= 0) {
            echo '&mdash;';
            return;
        }

        $user = get_user_by('id', $assigned);

        if ($user instanceof WP_User) {
            echo esc_html($user->display_name);
            return;
        }

        echo '&mdash;';
    }

    private static function renderContextColumn(int $postId): void
    {
        $context  = (string) get_post_meta($postId, LeadMeta::META_CONTEXT, true);
        $recordId = absint((int) get_post_meta($postId, LeadMeta::META_RECORD, true));

        $parts = [];
        if ($context !== '') {
            $parts[] = esc_html(LeadMeta::getContextLabel($context));
        }

        if ($recordId > 0) {
            $record = get_post($recordId);
            if ($record instanceof WP_Post) {
                $link = get_edit_post_link($recordId) ?: get_permalink($recordId);
                $parts[] = sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($record->post_title));
            }
        }

        if ($parts) {
            echo implode('<br />', $parts);
            return;
        }

        echo '&mdash;';
    }
}
