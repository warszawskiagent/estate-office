<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use EstateOffice\Roles\Manager as RolesManager;
use WP_Post;

use function __;
use function absint;
use function add_action;
use function add_meta_box;
use function current_user_can;
use function delete_post_meta;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function esc_url;
use function esc_url_raw;
use function get_edit_post_link;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function selected;
use function sprintf;
use function wp_dropdown_users;
use function wp_nonce_field;
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

    private const NONCE_ACTION = 'estate_office_save_lead';
    private const NONCE_NAME   = 'estate_office_lead_nonce';

    private const STATUS_DEFAULT = 'new';

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

        $status = isset($_POST['estate_lead_status']) ? sanitize_text_field(wp_unslash((string) $_POST['estate_lead_status'])) : '';
        $status = self::sanitizeStatus($status);
        update_post_meta($postId, self::META_STATUS, $status);

        $assigned = isset($_POST['estate_lead_assigned_to']) ? absint(wp_unslash((string) $_POST['estate_lead_assigned_to'])) : 0;
        if ($assigned > 0) {
            update_post_meta($postId, self::META_ASSIGNED, $assigned);
        } else {
            delete_post_meta($postId, self::META_ASSIGNED);
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
