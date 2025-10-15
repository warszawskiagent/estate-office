<?php

declare(strict_types=1);

namespace EstateOffice\Leads;

use EstateOffice\PostTypes\LeadMeta;
use EstateOffice\Settings\GeneralSettings;
use WP_Post;
use WP_User;

use function add_action;
use function admin_url;
use function apply_filters;
use function __;
use function esc_html__;
use function get_post;
use function get_post_meta;
use function get_userdata;
use function get_permalink;
use function is_email;
use function sanitize_email;
use function sprintf;
use function wp_mail;
use function wp_strip_all_tags;

defined('ABSPATH') || exit;

final class LeadNotifications
{
    public static function bootstrap(): void
    {
        add_action('estate_office_lead_stored', [self::class, 'notify'], 20, 3);
    }

    /**
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public static function notify(int $leadId, array $meta, array $data): void
    {
        if ($leadId <= 0) {
            return;
        }

        $lead = get_post($leadId);
        if (! $lead instanceof WP_Post) {
            return;
        }

        $settings = GeneralSettings::getLeadNotificationSettings();

        $body    = self::buildBody($lead, $settings['include_message']);
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        if ($settings['notify_agent']) {
            $agentEmail = self::resolveAgentEmail($leadId, $meta);
            $agentEmail = apply_filters('estate_office_lead_agent_email', $agentEmail, $leadId, $meta, $data);

            if ($agentEmail !== '' && is_email($agentEmail)) {
                $subject = $settings['agent_subject'] !== ''
                    ? $settings['agent_subject']
                    : esc_html__('Nowy lead został przypisany do Ciebie', 'estate-office');

                $subject = apply_filters('estate_office_lead_agent_subject', $subject, $leadId, $meta, $data);
                $content = apply_filters('estate_office_lead_agent_body', $body, $leadId, $meta, $data);

                wp_mail($agentEmail, $subject, $content, $headers);
            }
        }

        $officeEmail = $settings['office_email'];
        $officeEmail = apply_filters('estate_office_lead_office_email', $officeEmail, $leadId, $meta, $data);

        if ($officeEmail !== '' && is_email($officeEmail)) {
            $subject = $settings['office_subject'] !== ''
                ? $settings['office_subject']
                : esc_html__('Nowe zgłoszenie leadu', 'estate-office');

            $subject = apply_filters('estate_office_lead_office_subject', $subject, $leadId, $meta, $data);
            $content = apply_filters('estate_office_lead_office_body', $body, $leadId, $meta, $data);

            wp_mail($officeEmail, $subject, $content, $headers);
        }
    }

    private static function buildBody(WP_Post $lead, bool $includeMessage): string
    {
        $leadId = $lead->ID;

        $name           = (string) get_post_meta($leadId, LeadMeta::META_NAME, true);
        $email          = (string) get_post_meta($leadId, LeadMeta::META_EMAIL, true);
        $phone          = (string) get_post_meta($leadId, LeadMeta::META_PHONE, true);
        $message        = (string) get_post_meta($leadId, LeadMeta::META_MESSAGE, true);
        $context        = (string) get_post_meta($leadId, LeadMeta::META_CONTEXT, true);
        $recordId       = (int) get_post_meta($leadId, LeadMeta::META_RECORD, true);
        $source         = (string) get_post_meta($leadId, LeadMeta::META_SOURCE, true);
        $status         = (string) get_post_meta($leadId, LeadMeta::META_STATUS, true);
        $assigned       = (int) get_post_meta($leadId, LeadMeta::META_ASSIGNED, true);
        $recipient      = (string) get_post_meta($leadId, LeadMeta::META_RECIPIENT, true);
        $recipientName  = (string) get_post_meta($leadId, LeadMeta::META_RECIPIENT_NAME, true);
        $formSubject    = (string) get_post_meta($leadId, LeadMeta::META_SUBJECT, true);

        $lines   = [];
        $lines[] = sprintf(__('Lead: %s', 'estate-office'), wp_strip_all_tags($lead->post_title));
        $lines[] = sprintf(__('Status: %s', 'estate-office'), LeadMeta::getStatusLabel($status !== '' ? $status : 'new'));
        $lines[] = '';
        $lines[] = __('Dane kontaktowe', 'estate-office');
        $lines[] = sprintf(__('Imię i nazwisko: %s', 'estate-office'), $name !== '' ? $name : '—');
        $lines[] = sprintf(__('E-mail: %s', 'estate-office'), $email !== '' ? $email : '—');
        $lines[] = sprintf(__('Telefon: %s', 'estate-office'), $phone !== '' ? $phone : '—');

        if ($formSubject !== '') {
            $lines[] = sprintf(__('Temat formularza: %s', 'estate-office'), $formSubject);
        }

        if ($includeMessage && $message !== '') {
            $lines[] = '';
            $lines[] = __('Wiadomość klienta:', 'estate-office');
            $lines[] = $message;
        }

        $lines[] = '';
        $lines[] = __('Informacje CRM', 'estate-office');
        $lines[] = sprintf(__('Kontekst: %s', 'estate-office'), LeadMeta::getContextLabel($context));

        if ($recordId > 0) {
            $record = get_post($recordId);
            if ($record instanceof WP_Post) {
                $lines[] = sprintf(__('Powiązany rekord: %s', 'estate-office'), wp_strip_all_tags($record->post_title));
                $lines[] = sprintf(__('Link do rekordu: %s', 'estate-office'), get_permalink($record));
            }
        }

        if ($source !== '') {
            $lines[] = sprintf(__('Źródło zgłoszenia: %s', 'estate-office'), $source);
        }

        if ($recipient !== '') {
            $displayRecipient = $recipientName !== '' ? sprintf('%s <%s>', $recipientName, $recipient) : $recipient;
            $lines[]          = sprintf(__('Adresat formularza: %s', 'estate-office'), $displayRecipient);
        }

        if ($assigned > 0) {
            $user = get_userdata($assigned);
            if ($user instanceof WP_User) {
                $lines[] = sprintf(__('Przypisany agent: %s (%s)', 'estate-office'), $user->display_name, $user->user_email);
            }
        }

        $lines[] = '';
        $lines[] = __('Zarządzaj leadem w panelu administracyjnym:', 'estate-office');
        $lines[] = admin_url('post.php?post=' . $leadId . '&action=edit');

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function resolveAgentEmail(int $leadId, array $meta): string
    {
        $assigned = (int) get_post_meta($leadId, LeadMeta::META_ASSIGNED, true);

        if ($assigned > 0) {
            $user = get_userdata($assigned);
            if ($user instanceof WP_User && $user->user_email !== '' && is_email($user->user_email)) {
                return $user->user_email;
            }
        }

        $recipient = '';

        if (isset($meta[LeadMeta::META_RECIPIENT])) {
            $recipient = sanitize_email((string) $meta[LeadMeta::META_RECIPIENT]);
        }

        if ($recipient === '') {
            $recipient = (string) get_post_meta($leadId, LeadMeta::META_RECIPIENT, true);
            $recipient = sanitize_email($recipient);
        }

        return $recipient;
    }
}
