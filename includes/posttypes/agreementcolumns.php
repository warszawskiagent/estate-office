<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Query;

defined('ABSPATH') || exit;

final class AgreementColumns
{
    public static function bootstrap(): void
    {
        add_filter('manage_edit-' . AgreementRegister::POST_TYPE . '_columns', [self::class, 'registerColumns']);
        add_action('manage_' . AgreementRegister::POST_TYPE . '_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . AgreementRegister::POST_TYPE . '_sortable_columns', [self::class, 'sortableColumns']);
        add_action('pre_get_posts', [self::class, 'handleSorting']);
    }

    public static function registerColumns(array $columns): array
    {
        $newColumns = [
            'cb'                => $columns['cb'] ?? '<input type="checkbox" />',
            'title'             => __('Numer umowy', 'estate-office'),
            'transaction_type'  => __('Typ transakcji', 'estate-office'),
            'start_date'        => __('Data zawarcia', 'estate-office'),
            'end_date'          => __('Data zakończenia', 'estate-office'),
            'stage'             => __('Aktualny etap', 'estate-office'),
        ];

        if (isset($columns['date'])) {
            $newColumns['date'] = $columns['date'];
        }

        return $newColumns;
    }

    public static function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'transaction_type':
                $value = self::formatTransactionType((string) get_post_meta($postId, 'estate_agreement_transaction_type', true));
                echo $value !== '' ? esc_html($value) : '&mdash;';
                break;
            case 'start_date':
                self::renderDate(get_post_meta($postId, 'estate_agreement_start_date', true));
                break;
            case 'end_date':
                self::renderDate(get_post_meta($postId, 'estate_agreement_end_date', true));
                break;
            case 'stage':
                $stage = self::formatStage((string) get_post_meta($postId, 'estate_agreement_stage', true));
                echo $stage !== '' ? esc_html($stage) : '&mdash;';
                break;
        }
    }

    public static function sortableColumns(array $columns): array
    {
        $columns['start_date'] = 'estate_agreement_start_date';
        $columns['end_date']   = 'estate_agreement_end_date';
        $columns['stage']      = 'estate_agreement_stage';

        return $columns;
    }

    public static function handleSorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');

        if ($postType !== AgreementRegister::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'estate_agreement_start_date' || $orderby === 'estate_agreement_end_date') {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
            $query->set('meta_type', 'DATE');
        }

        if ($orderby === 'estate_agreement_stage') {
            $query->set('meta_key', 'estate_agreement_stage');
            $query->set('orderby', 'meta_value');
        }
    }

    private static function renderDate(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            echo '&mdash;';
            return;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            echo '&mdash;';
            return;
        }

        echo esc_html(wp_date(get_option('date_format', 'Y-m-d'), $timestamp));
    }

    private static function formatTransactionType(string $value): string
    {
        $labels = AgreementMeta::TRANSACTION_TYPES;

        return $labels[$value] ?? $value;
    }

    private static function formatStage(string $value): string
    {
        $labels = AgreementMeta::STAGES;

        return $labels[$value] ?? $value;
    }
}
