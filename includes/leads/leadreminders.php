<?php

declare(strict_types=1);

namespace EstateOffice\Leads;

use EstateOffice\PostTypes\LeadMeta;
use EstateOffice\PostTypes\LeadRegister;
use EstateOffice\Settings\GeneralSettings;
use WP_Post;
use WP_Query;
use WP_User;

use function __;
use function absint;
use function add_action;
use function admin_url;
use function apply_filters;
use function current_time;
use function end;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_userdata;
use function is_array;
use function is_email;
use function sanitize_email;
use function sprintf;
use function strtotime;
use function time;
use function wp_clear_scheduled_hook;
use function wp_date;
use function wp_mail;
use function wp_next_scheduled;
use function wp_schedule_event;
use function wp_timezone;

use const HOUR_IN_SECONDS;
use const MINUTE_IN_SECONDS;

defined('ABSPATH') || exit;

final class LeadReminders
{
    private const CRON_HOOK = 'estate_office_lead_follow_up_reminders';
    private const OVERDUE_GRACE = HOUR_IN_SECONDS;

    public static function activate(): void
    {
        self::maybeSchedule();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function bootstrap(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'dispatch']);
        add_action('init', [self::class, 'maybeSchedule']);
        add_action('update_option_' . GeneralSettings::OPTION, [self::class, 'handleSettingsUpdate'], 10, 2);
    }

    public static function handleSettingsUpdate($oldValue, $value): void
    {
        if (!is_array($value)) {
            return;
        }

        $notifications = $value['lead_notifications'] ?? [];
        $enabled       = !empty($notifications['reminder_enabled'])
            && (!empty($notifications['reminder_notify_agent']) || !empty($notifications['reminder_notify_office']));

        if ($enabled) {
            self::schedule();
        } else {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    public static function maybeSchedule(): void
    {
        $settings = GeneralSettings::getLeadReminderSettings();
        if ($settings['enabled'] && ($settings['notify_agent'] || $settings['notify_office'])) {
            self::schedule();

            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function dispatch(): void
    {
        $settings = GeneralSettings::getLeadReminderSettings();
        if (!$settings['enabled']) {
            return;
        }

        if (!$settings['notify_agent'] && !$settings['notify_office']) {
            return;
        }

        $now     = current_time('timestamp');
        $window  = max(1, (int) $settings['hours']) * HOUR_IN_SECONDS;
        $upper   = $now + $window;
        $fromStr = wp_date('Y-m-d H:i:s', $now, wp_timezone());
        $toStr   = wp_date('Y-m-d H:i:s', $upper, wp_timezone());

        $query = new WP_Query([
            'post_type'      => LeadRegister::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => LeadMeta::META_FOLLOW_UP,
                    'value'   => [$fromStr, $toStr],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                ],
            ],
        ]);

        if (empty($query->posts)) {
            return;
        }

        $notificationSettings = GeneralSettings::getLeadNotificationSettings();

        foreach ($query->posts as $leadId) {
            $leadId = absint($leadId);
            if ($leadId <= 0) {
                continue;
            }

            $lead = get_post($leadId);
            if (!$lead instanceof WP_Post) {
                continue;
            }

            $followUp = LeadMeta::getFollowUp($leadId);
            if ($followUp === '') {
                continue;
            }

            $timestamp = strtotime($followUp);
            if ($timestamp === false) {
                continue;
            }

            if ($timestamp > $upper) {
                continue;
            }

            if ($timestamp < $now && ($now - $timestamp) > self::OVERDUE_GRACE) {
                continue;
            }

            $alreadyReminded = LeadMeta::getFollowUpReminder($leadId);
            if ($alreadyReminded === $followUp) {
                continue;
            }

            $subject = $settings['subject'] !== ''
                ? $settings['subject']
                : sprintf(__('Przypomnienie follow-up: %s', 'estate-office'), $lead->post_title);
            $subject = apply_filters('estate_office_lead_follow_up_subject', $subject, $leadId, $followUp, $settings);

            $body = self::buildBody($lead, $followUp);
            $body = apply_filters('estate_office_lead_follow_up_body', $body, $leadId, $followUp, $settings);

            $sent = false;

            if ($settings['notify_agent']) {
                $agentEmail = self::resolveAgentEmail($leadId);
                $agentEmail = apply_filters('estate_office_lead_follow_up_agent_email', $agentEmail, $leadId, $followUp, $settings);

                if ($agentEmail !== '' && is_email($agentEmail)) {
                    wp_mail($agentEmail, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
                    $sent = true;
                }
            }

            if ($settings['notify_office']) {
                $officeEmail = sanitize_email($notificationSettings['office_email']);
                $officeEmail = apply_filters('estate_office_lead_follow_up_office_email', $officeEmail, $leadId, $followUp, $settings);

                if ($officeEmail !== '' && is_email($officeEmail)) {
                    wp_mail($officeEmail, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
                    $sent = true;
                }
            }

            if ($sent) {
                LeadMeta::markFollowUpReminded($leadId, $followUp);
            }
        }
    }

    private static function schedule(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
    }

    private static function buildBody(WP_Post $lead, string $followUp): string
    {
        $leadId = $lead->ID;
        $followUpTime = strtotime($followUp);
        $dateFormat   = get_option('date_format');
        $timeFormat   = get_option('time_format');
        $followUpLabel = $followUpTime !== false
            ? wp_date($dateFormat . ' ' . $timeFormat, $followUpTime, wp_timezone())
            : $followUp;

        $lines   = [];
        $lines[] = sprintf(__('Lead: %s', 'estate-office'), $lead->post_title);
        $lines[] = sprintf(__('Termin follow-up: %s', 'estate-office'), $followUpLabel);
        $lines[] = '';
        $lines[] = __('Status i kontekst', 'estate-office');

        $status = (string) get_post_meta($leadId, LeadMeta::META_STATUS, true);
        $lines[] = sprintf(__('Status: %s', 'estate-office'), LeadMeta::getStatusLabel($status));

        $context = (string) get_post_meta($leadId, LeadMeta::META_CONTEXT, true);
        $lines[] = sprintf(__('Kontekst: %s', 'estate-office'), LeadMeta::getContextLabel($context));

        $recordId = (int) get_post_meta($leadId, LeadMeta::META_RECORD, true);
        if ($recordId > 0) {
            $record = get_post($recordId);
            if ($record instanceof WP_Post) {
                $lines[] = sprintf(__('Powiązany rekord: %s', 'estate-office'), $record->post_title);
            }
        }

        $lines[] = '';
        $lines[] = __('Dane kontaktowe', 'estate-office');
        $name    = (string) get_post_meta($leadId, LeadMeta::META_NAME, true);
        $email   = (string) get_post_meta($leadId, LeadMeta::META_EMAIL, true);
        $phone   = (string) get_post_meta($leadId, LeadMeta::META_PHONE, true);
        $lines[] = sprintf(__('Imię i nazwisko: %s', 'estate-office'), $name !== '' ? $name : '—');
        $lines[] = sprintf(__('E-mail: %s', 'estate-office'), $email !== '' ? $email : '—');
        $lines[] = sprintf(__('Telefon: %s', 'estate-office'), $phone !== '' ? $phone : '—');

        $notes = LeadMeta::getNotes($leadId);
        if (!empty($notes)) {
            $latest = end($notes);
            if (is_array($latest)) {
                $noteTime = isset($latest['timestamp']) ? strtotime((string) $latest['timestamp']) : false;
                $noteDate = $noteTime !== false
                    ? wp_date($dateFormat . ' ' . $timeFormat, $noteTime, wp_timezone())
                    : ($latest['timestamp'] ?? '');

                $userLabel = '';
                $userId    = isset($latest['user']) ? absint($latest['user']) : 0;
                if ($userId > 0) {
                    $user = get_userdata($userId);
                    if ($user instanceof WP_User) {
                        $userLabel = sprintf('%s (%s)', $user->display_name, $user->user_email);
                    }
                }

                $lines[] = '';
                $lines[] = __('Ostatnia notatka', 'estate-office');
                $lines[] = sprintf(__('Data: %s', 'estate-office'), $noteDate !== '' ? $noteDate : '—');
                if ($userLabel !== '') {
                    $lines[] = sprintf(__('Autor: %s', 'estate-office'), $userLabel);
                }
                $lines[] = sprintf(__('Treść: %s', 'estate-office'), $latest['note']);
            }
        }

        $lines[] = '';
        $lines[] = __('Zarządzaj leadem w panelu administracyjnym:', 'estate-office');
        $lines[] = admin_url('post.php?post=' . $leadId . '&action=edit');

        return implode("\n", $lines);
    }

    private static function resolveAgentEmail(int $leadId): string
    {
        $assigned = (int) get_post_meta($leadId, LeadMeta::META_ASSIGNED, true);
        if ($assigned > 0) {
            $user = get_userdata($assigned);
            if ($user instanceof WP_User && $user->user_email !== '' && is_email($user->user_email)) {
                return $user->user_email;
            }
        }

        $recipient = (string) get_post_meta($leadId, LeadMeta::META_RECIPIENT, true);
        $recipient = sanitize_email($recipient);

        return $recipient;
    }
}
