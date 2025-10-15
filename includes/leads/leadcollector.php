<?php

declare(strict_types=1);

namespace EstateOffice\Leads;

use EstateOffice\PostTypes\LeadMeta;
use EstateOffice\PostTypes\LeadRegister;
use WP_Post;

use function __;
use function absint;
use function add_action;
use function do_action;
use function esc_url_raw;
use function get_post;
use function is_wp_error;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sprintf;
use function update_post_meta;
use function wp_insert_post;
use function wp_strip_all_tags;

defined('ABSPATH') || exit;

final class LeadCollector
{
    public static function bootstrap(): void
    {
        add_action('estate_office_contact_submitted', [self::class, 'store'], 10, 1);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function store(array $data): void
    {
        $name          = sanitize_text_field((string) ($data['name'] ?? ''));
        $email         = sanitize_email((string) ($data['email'] ?? ''));
        $phone         = sanitize_text_field((string) ($data['phone'] ?? ''));
        $message       = sanitize_textarea_field((string) ($data['message'] ?? ''));
        $context       = sanitize_text_field((string) ($data['context'] ?? ''));
        $recordId      = absint((int) ($data['record_id'] ?? 0));
        $source        = isset($data['source']) ? esc_url_raw((string) $data['source']) : '';
        $recipient     = sanitize_email((string) ($data['recipient'] ?? ''));
        $recipientName = sanitize_text_field((string) ($data['recipient_name'] ?? ''));
        $subject       = sanitize_text_field((string) ($data['subject'] ?? ''));
        $assignedTo    = absint((int) ($data['assigned_to'] ?? 0));

        $title = self::buildTitle($name, $context, $recordId);

        $postId = wp_insert_post([
            'post_type'   => LeadRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> $message,
        ], true);

        if (is_wp_error($postId) || $postId <= 0) {
            return;
        }

        $status = LeadMeta::sanitizeStatus('new');

        $meta = [
            LeadMeta::META_NAME           => $name,
            LeadMeta::META_EMAIL          => $email,
            LeadMeta::META_PHONE          => $phone,
            LeadMeta::META_MESSAGE        => $message,
            LeadMeta::META_CONTEXT        => $context,
            LeadMeta::META_RECORD         => $recordId,
            LeadMeta::META_SOURCE         => $source,
            LeadMeta::META_STATUS         => $status,
            LeadMeta::META_RECIPIENT      => $recipient,
            LeadMeta::META_RECIPIENT_NAME => $recipientName,
            LeadMeta::META_SUBJECT        => $subject,
        ];

        foreach ($meta as $key => $value) {
            if ($value === '' || $value === 0) {
                continue;
            }

            update_post_meta($postId, $key, $value);
        }

        if ($assignedTo > 0) {
            update_post_meta($postId, LeadMeta::META_ASSIGNED, $assignedTo);
        }

        LeadMeta::appendStatusHistory(
            $postId,
            $status,
            0,
            __('Lead utworzony na podstawie formularza kontaktowego.', 'estate-office')
        );

        do_action('estate_office_lead_stored', $postId, $meta, $data);
    }

    private static function buildTitle(string $name, string $context, int $recordId): string
    {
        $base = $name !== '' ? $name : __('Nowy lead', 'estate-office');

        $contextLabel = LeadMeta::getContextLabel($context);

        if ($recordId > 0) {
            $record = get_post($recordId);
            if ($record instanceof WP_Post) {
                $recordTitle = wp_strip_all_tags($record->post_title);
                if ($recordTitle !== '') {
                    $contextLabel = sprintf('%s – %s', $contextLabel, $recordTitle);
                }
            }
        }

        if ($contextLabel !== '') {
            return sprintf('%s | %s', $base, $contextLabel);
        }

        return $base;
    }
}
