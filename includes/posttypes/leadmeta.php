<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use EstateOffice\Roles\Manager as RolesManager;
use WP_Post;

use DateTime;
use Exception;

use function __;
use function absint;
use function add_action;
use function add_meta_box;
use function array_slice;
use function current_time;
use function current_user_can;
use function delete_post_meta;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function esc_url;
use function esc_url_raw;
use function get_edit_post_link;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_userdata;
use function is_array;
use function nl2br;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function selected;
use function sprintf;
use function strtotime;
use function wp_date;
use function wp_dropdown_users;
use function wp_nonce_field;
use function wp_timezone;
use function wp_unslash;
use function wp_verify_nonce;
use function update_post_meta;
use function register_post_meta;

use const DOING_AUTOSAVE;

defined('ABSPATH') || exit;

final class LeadMeta
{
    public const META_NAME           = 'estate_lead_name';
    public const META_EMAIL          = 'estate_lead_email';
    public const META_PHONE          = 'estate_lead_phone';
    public const META_MESSAGE        = 'estate_lead_message';
    public const META_CONTEXT        = 'estate_lead_context';
    public const META_RECORD         = 'estate_lead_record_id';
    public const META_SOURCE         = 'estate_lead_source';
    public const META_STATUS         = 'estate_lead_status';
    public const META_ASSIGNED       = 'estate_lead_assigned_to';
    public const META_RECIPIENT      = 'estate_lead_recipient';
    public const META_RECIPIENT_NAME = 'estate_lead_recipient_name';
    public const META_SUBJECT        = 'estate_lead_subject';
    public const META_STATUS_HISTORY = 'estate_lead_status_history';
    public const META_NOTES              = 'estate_lead_notes';
    public const META_FOLLOW_UP          = 'estate_lead_follow_up';
    public const META_FOLLOW_UP_REMINDER = 'estate_lead_follow_up_reminded';

    private const NONCE_ACTION = 'estate_office_save_lead';
    private const NONCE_NAME   = 'estate_office_lead_nonce';

    private const STATUS_DEFAULT = 'new';

    private const HISTORY_LIMIT = 50;
    private const NOTES_LIMIT   = 100;

    /** @var array<string,string> */
    private static array $statuses;

    /**
     * Registers meta boxes and metadata.
     */
    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerMeta']);
        add_action('add_meta_boxes_' . LeadRegister::POST_TYPE, [self::class, 'registerMetaBoxes']);
        add_action('save_post_' . LeadRegister::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    public static function activate(): void
    {
        self::registerMeta();
    }

    public static function deactivate(): void
    {
    }

    public static function registerMeta(): void
    {
        $definitions = [
            self::META_NAME => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_EMAIL => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            self::META_PHONE => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_MESSAGE => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            self::META_CONTEXT => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_RECORD => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_SOURCE => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
            self::META_STATUS => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeStatus']],
            self::META_ASSIGNED => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_RECIPIENT => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            self::META_RECIPIENT_NAME => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_SUBJECT => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            self::META_STATUS_HISTORY => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitizeStatusHistory']],
            self::META_NOTES => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitizeNotes']],
            self::META_FOLLOW_UP => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeFollowUp']],
            self::META_FOLLOW_UP_REMINDER => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeFollowUp']],
        ];

        foreach ($definitions as $metaKey => $args) {
            register_post_meta(
                LeadRegister::POST_TYPE,
                $metaKey,
                [
                    'single'            => true,
                    'type'              => $args['type'],
                    'sanitize_callback' => $args['sanitize_callback'],
                    'show_in_rest'      => false,
                ]
            );
        }
    }

    public static function registerMetaBoxes(): void
    {
        add_meta_box(
            'estate-office-lead-details',
            esc_html__('Szczegóły zgłoszenia', 'estate-office'),
            [self::class, 'renderDetailsBox'],
            LeadRegister::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'estate-office-lead-management',
            esc_html__('Zarządzanie leadem', 'estate-office'),
            [self::class, 'renderManagementBox'],
            LeadRegister::POST_TYPE,
            'side'
        );

        add_meta_box(
            'estate-office-lead-notes',
            esc_html__('Notatki i follow-up', 'estate-office'),
            [self::class, 'renderNotesBox'],
            LeadRegister::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function renderDetailsBox(WP_Post $post): void
    {
        $name    = (string) get_post_meta($post->ID, self::META_NAME, true);
        $email   = (string) get_post_meta($post->ID, self::META_EMAIL, true);
        $phone   = (string) get_post_meta($post->ID, self::META_PHONE, true);
        $message = (string) get_post_meta($post->ID, self::META_MESSAGE, true);

        echo '<table class="form-table estate-office-lead__table">';
        echo '<tr><th>' . esc_html__('Imię i nazwisko', 'estate-office') . '</th><td>' . esc_html($name) . '</td></tr>';
        echo '<tr><th>' . esc_html__('E-mail', 'estate-office') . '</th><td>';
        if ($email !== '') {
            echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        } else {
            echo '&mdash;';
        }
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__('Telefon', 'estate-office') . '</th><td>';
        if ($phone !== '') {
            echo '<a href="tel:' . esc_attr(self::sanitizeTelHref($phone)) . '">' . esc_html($phone) . '</a>';
        } else {
            echo '&mdash;';
        }
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Wiadomość', 'estate-office') . '</th><td>';
        if ($message !== '') {
            echo '<textarea rows="6" readonly class="widefat" style="resize:none">' . esc_textarea($message) . '</textarea>';
        } else {
            echo '&mdash;';
        }
        echo '</td></tr>';
        echo '</table>';
    }

    public static function renderManagementBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $status    = (string) get_post_meta($post->ID, self::META_STATUS, true);
        $status    = $status !== '' ? $status : self::STATUS_DEFAULT;
        $status    = self::sanitizeStatus($status);
        $assigned  = absint((int) get_post_meta($post->ID, self::META_ASSIGNED, true));
        $context   = (string) get_post_meta($post->ID, self::META_CONTEXT, true);
        $recordId  = absint((int) get_post_meta($post->ID, self::META_RECORD, true));
        $source    = (string) get_post_meta($post->ID, self::META_SOURCE, true);
        $subject   = (string) get_post_meta($post->ID, self::META_SUBJECT, true);
        $recipient = (string) get_post_meta($post->ID, self::META_RECIPIENT, true);
        $recipientName = (string) get_post_meta($post->ID, self::META_RECIPIENT_NAME, true);

        echo '<p><label for="estate_lead_status"><strong>' . esc_html__('Status', 'estate-office') . '</strong></label></p>';
        echo '<select id="estate_lead_status" name="estate_lead_status" class="widefat">';
        foreach (self::getStatuses() as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($status, $value, false), esc_html($label));
        }
        echo '</select>';

        echo '<p><label for="estate_lead_assigned_to"><strong>' . esc_html__('Przypisany agent', 'estate-office') . '</strong></label></p>';
        wp_dropdown_users([
            'name'              => 'estate_lead_assigned_to',
            'id'                => 'estate_lead_assigned_to',
            'selected'          => $assigned,
            'show_option_none'  => esc_html__('— Brak —', 'estate-office'),
            'option_none_value' => '0',
            'role__in'          => [RolesManager::AGENT_ROLE, 'administrator'],
            'class'             => 'widefat',
            'include_selected'  => true,
        ]);

        if ($context !== '' || $recordId > 0) {
            $recordTitle = '';
            $recordEdit  = '';
            $recordView  = '';
            if ($recordId > 0) {
                $record = get_post($recordId);
                if ($record instanceof WP_Post) {
                    $recordTitle = $record->post_title;
                    $recordEdit  = get_edit_post_link($recordId) ?: '';
                    $recordView  = get_permalink($recordId) ?: '';
                }
            }

            echo '<hr />';
            echo '<p><strong>' . esc_html__('Kontekst zgłoszenia', 'estate-office') . '</strong></p>';
            echo '<ul class="estate-office-lead__details">';
            echo '<li>' . esc_html__('Typ', 'estate-office') . ': ' . esc_html(self::getContextLabel($context)) . '</li>';
            if ($recordTitle !== '') {
                echo '<li>' . esc_html__('Powiązany rekord', 'estate-office') . ': ' . esc_html($recordTitle);
                if ($recordEdit !== '') {
                    echo '<br /><a href="' . esc_url($recordEdit) . '">' . esc_html__('Edytuj w CRM', 'estate-office') . '</a>';
                }
                if ($recordView !== '') {
                    echo '<br /><a href="' . esc_url($recordView) . '" target="_blank" rel="noopener">' . esc_html__('Zobacz na stronie', 'estate-office') . '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        if ($subject !== '' || $recipient !== '' || $source !== '') {
            echo '<hr />';
            echo '<p><strong>' . esc_html__('Źródło zgłoszenia', 'estate-office') . '</strong></p>';
            echo '<ul class="estate-office-lead__details">';
            if ($subject !== '') {
                echo '<li>' . esc_html__('Temat wiadomości', 'estate-office') . ': ' . esc_html($subject) . '</li>';
            }
            if ($recipient !== '') {
                $label = $recipientName !== '' ? sprintf('%s (%s)', $recipientName, $recipient) : $recipient;
                echo '<li>' . esc_html__('Adresat wiadomości', 'estate-office') . ': ' . esc_html($label) . '</li>';
            }
            if ($source !== '') {
                echo '<li>' . esc_html__('Strona źródłowa', 'estate-office') . ': <a href="' . esc_url($source) . '" target="_blank" rel="noopener">' . esc_html($source) . '</a></li>';
            }
            echo '</ul>';
        }
    }

    public static function renderNotesBox(WP_Post $post): void
    {
        $followUp      = self::getFollowUp($post->ID);
        $followUpValue = self::formatFollowUpInputValue($followUp);
        $notes         = self::getNotes($post->ID);

        echo '<div class="estate-office-lead__notes-box">';
        if ($followUp !== '') {
            $followUpTimestamp = strtotime($followUp);
            $followUpLabel     = $followUpTimestamp !== false
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $followUpTimestamp, wp_timezone())
                : '';
            echo '<p class="estate-office-lead__follow-up">';
            echo '<strong>' . esc_html__('Najbliższy follow-up', 'estate-office') . ':</strong> ' . esc_html($followUpLabel);
            echo '</p>';
        }

        if ($notes !== []) {
            echo '<ul class="estate-office-lead__notes-list">';
            foreach (array_reverse($notes) as $entry) {
                $timestamp = $entry['timestamp'];
                $userId    = $entry['user'];
                $note      = $entry['note'];

                $user      = $userId > 0 ? get_userdata($userId) : null;
                $userLabel = $user !== null ? $user->display_name : esc_html__('System', 'estate-office');
                $dateTimestamp = $timestamp !== '' ? strtotime($timestamp) : false;
                $dateLabel = $dateTimestamp !== false
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $dateTimestamp, wp_timezone())
                    : '';

                echo '<li class="estate-office-lead__note">';
                echo '<div class="estate-office-lead__note-meta">';
                if ($dateLabel !== '') {
                    echo '<span class="estate-office-lead__note-date">' . esc_html($dateLabel) . '</span>';
                }
                if ($userLabel !== '') {
                    echo '<span class="estate-office-lead__note-author">' . esc_html($userLabel) . '</span>';
                }
                echo '</div>';
                echo '<p class="estate-office-lead__note-text">' . nl2br(esc_html($note)) . '</p>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('Brak zapisanych notatek dla tego leadu.', 'estate-office') . '</p>';
        }

        echo '<p><label for="estate_lead_new_note"><strong>' . esc_html__('Dodaj notatkę', 'estate-office') . '</strong></label></p>';
        echo '<textarea id="estate_lead_new_note" name="estate_lead_new_note" class="widefat" rows="4"></textarea>';

        echo '<p><label for="estate_lead_follow_up"><strong>' . esc_html__('Przypomnienie follow-up', 'estate-office') . '</strong></label></p>';
        echo '<input type="datetime-local" id="estate_lead_follow_up" name="estate_lead_follow_up" class="widefat" value="' . esc_attr($followUpValue) . '">';
        echo '<p class="description">' . esc_html__('Pozostaw puste, aby usunąć przypomnienie follow-up.', 'estate-office') . '</p>';
        echo '</div>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== LeadRegister::POST_TYPE) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $previousStatus = (string) get_post_meta($postId, self::META_STATUS, true);
        $previousStatus = $previousStatus !== '' ? self::sanitizeStatus($previousStatus) : '';

        $status = isset($_POST['estate_lead_status']) ? sanitize_text_field(wp_unslash((string) $_POST['estate_lead_status'])) : '';
        $status = self::sanitizeStatus($status);
        update_post_meta($postId, self::META_STATUS, $status);

        if ($previousStatus === '' || $previousStatus !== $status) {
            self::appendStatusHistory(
                $postId,
                $status,
                get_current_user_id(),
                __('Aktualizacja statusu w kokpicie administratora.', 'estate-office')
            );
        }

        $assigned = isset($_POST['estate_lead_assigned_to']) ? absint(wp_unslash((string) $_POST['estate_lead_assigned_to'])) : 0;
        if ($assigned > 0) {
            update_post_meta($postId, self::META_ASSIGNED, $assigned);
        } else {
            delete_post_meta($postId, self::META_ASSIGNED);
        }

        $followUpRaw = isset($_POST['estate_lead_follow_up']) ? wp_unslash((string) $_POST['estate_lead_follow_up']) : '';
        $followUp    = self::sanitizeFollowUp($followUpRaw);
        self::updateFollowUp($postId, $followUp);

        $note = isset($_POST['estate_lead_new_note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['estate_lead_new_note'])) : '';
        if ($note !== '') {
            self::appendNote($postId, $note, get_current_user_id());
        }
    }

    /**
     * @return array<string,string>
     */
    public static function getStatuses(): array
    {
        if (!isset(self::$statuses)) {
            self::$statuses = [
                'new'         => esc_html__('Nowy', 'estate-office'),
                'contacted'   => esc_html__('Skontaktowano', 'estate-office'),
                'in_progress' => esc_html__('W trakcie', 'estate-office'),
                'completed'   => esc_html__('Zamknięty', 'estate-office'),
                'rejected'    => esc_html__('Odrzucony', 'estate-office'),
            ];
        }

        return self::$statuses;
    }

    /**
     * @return array<int,array{status:string,user:int,timestamp:string,note:string}>
     */
    public static function getStatusHistory(int $postId): array
    {
        $history = get_post_meta($postId, self::META_STATUS_HISTORY, true);
        if (!is_array($history)) {
            return [];
        }

        return self::sanitizeStatusHistory($history);
    }

    public static function appendStatusHistory(int $postId, string $status, int $userId = 0, string $note = ''): void
    {
        if ($postId <= 0) {
            return;
        }

        $status = self::sanitizeStatus($status);
        $userId = absint($userId);
        $history = self::getStatusHistory($postId);

        $entry = [
            'status'    => $status,
            'user'      => $userId,
            'timestamp' => current_time('mysql'),
            'note'      => $note !== '' ? sanitize_text_field($note) : '',
        ];

        $history[] = $entry;

        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, -self::HISTORY_LIMIT);
        }

        update_post_meta($postId, self::META_STATUS_HISTORY, $history);
    }

    /**
     * @param mixed $value
     * @return array<int,array{status:string,user:int,timestamp:string,note:string}>
     */
    public static function sanitizeStatusHistory($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $status = isset($item['status']) ? self::sanitizeStatus((string) $item['status']) : self::STATUS_DEFAULT;
            $user   = isset($item['user']) ? absint($item['user']) : 0;
            $timestamp = isset($item['timestamp']) ? sanitize_text_field((string) $item['timestamp']) : '';
            if ($timestamp === '') {
                $timestamp = current_time('mysql');
            }

            $note = isset($item['note']) ? sanitize_text_field((string) $item['note']) : '';

            $sanitized[] = [
                'status'    => $status,
                'user'      => $user,
                'timestamp' => $timestamp,
                'note'      => $note,
            ];
        }

        return $sanitized;
    }

    /**
     * @return array<int,array{user:int,timestamp:string,note:string}>
     */
    public static function getNotes(int $postId): array
    {
        $notes = get_post_meta($postId, self::META_NOTES, true);
        if (!is_array($notes)) {
            return [];
        }

        return self::sanitizeNotes($notes);
    }

    public static function appendNote(int $postId, string $note, int $userId = 0): void
    {
        $note = trim($note);
        if ($postId <= 0 || $note === '') {
            return;
        }

        $notes   = self::getNotes($postId);
        $notes[] = [
            'user'      => absint($userId),
            'timestamp' => current_time('mysql'),
            'note'      => sanitize_textarea_field($note),
        ];

        if (count($notes) > self::NOTES_LIMIT) {
            $notes = array_slice($notes, -self::NOTES_LIMIT);
        }

        update_post_meta($postId, self::META_NOTES, $notes);
    }

    public static function updateFollowUp(int $postId, string $followUp): void
    {
        if ($postId <= 0) {
            return;
        }

        if ($followUp !== '') {
            update_post_meta($postId, self::META_FOLLOW_UP, $followUp);
            delete_post_meta($postId, self::META_FOLLOW_UP_REMINDER);
        } else {
            delete_post_meta($postId, self::META_FOLLOW_UP);
            delete_post_meta($postId, self::META_FOLLOW_UP_REMINDER);
        }
    }

    public static function getFollowUp(int $postId): string
    {
        $value = (string) get_post_meta($postId, self::META_FOLLOW_UP, true);

        return self::sanitizeFollowUp($value);
    }

    public static function getFollowUpReminder(int $postId): string
    {
        $value = (string) get_post_meta($postId, self::META_FOLLOW_UP_REMINDER, true);

        return self::sanitizeFollowUp($value);
    }

    public static function markFollowUpReminded(int $postId, string $followUp): void
    {
        if ($postId <= 0) {
            return;
        }

        $followUp = self::sanitizeFollowUp($followUp);
        if ($followUp === '') {
            delete_post_meta($postId, self::META_FOLLOW_UP_REMINDER);

            return;
        }

        update_post_meta($postId, self::META_FOLLOW_UP_REMINDER, $followUp);
    }

    private static function formatFollowUpInputValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTime($value, wp_timezone());
        } catch (Exception $exception) {
            return '';
        }

        return $date->format('Y-m-d\TH:i');
    }

    /**
     * @param mixed $value
     * @return array<int,array{user:int,timestamp:string,note:string}>
     */
    public static function sanitizeNotes($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $userId    = isset($item['user']) ? absint($item['user']) : 0;
            $timestamp = isset($item['timestamp']) ? sanitize_text_field((string) $item['timestamp']) : '';
            if ($timestamp === '') {
                $timestamp = current_time('mysql');
            }

            $note = isset($item['note']) ? sanitize_textarea_field((string) $item['note']) : '';
            if ($note === '') {
                continue;
            }

            $sanitized[] = [
                'user'      => $userId,
                'timestamp' => $timestamp,
                'note'      => $note,
            ];
        }

        return $sanitized;
    }

    /**
     * @param mixed $value
     */
    public static function sanitizeFollowUp($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        $value = str_replace('T', ' ', $value);

        try {
            $date = new DateTime($value, wp_timezone());
        } catch (Exception $exception) {
            return '';
        }

        return $date->format('Y-m-d H:i:s');
    }

    public static function sanitizeStatus(string $status): string
    {
        $statuses = array_keys(self::getStatuses());
        if (!in_array($status, $statuses, true)) {
            return self::STATUS_DEFAULT;
        }

        return $status;
    }

    public static function getStatusLabel(string $status): string
    {
        $statuses = self::getStatuses();

        return $statuses[$status] ?? $statuses[self::STATUS_DEFAULT];
    }

    public static function getContextLabel(string $context): string
    {
        $map = [
            'offer'         => __('Oferta', 'estate-office'),
            'agent-profile' => __('Agent', 'estate-office'),
            'crm'           => __('Panel CRM', 'estate-office'),
        ];

        if ($context === '') {
            return __('Inne', 'estate-office');
        }

        return $map[$context] ?? $context;
    }

    private static function sanitizeTelHref(string $value): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $value);

        return $digits !== '' ? $digits : $value;
    }
}
