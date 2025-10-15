<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\PostTypes\LeadMeta;
use EstateOffice\PostTypes\LeadRegister;
use WP_Post;

use function __;
use function absint;
use function add_action;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function is_user_logged_in;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_localize_script;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

defined('ABSPATH') || exit;

final class LeadActions
{
    public const NONCE_ACTION = 'estate_office_update_lead';

    public static function bootstrap(): void
    {
        add_action('wp_ajax_estate_office_update_lead_status', [self::class, 'handleStatusUpdate']);
        add_action('wp_ajax_nopriv_estate_office_update_lead_status', [self::class, 'forbid']);
        add_action('wp_enqueue_scripts', [self::class, 'localizeScriptData']);
    }

    public static function localizeScriptData(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        wp_localize_script(
            'estate-office-frontend-crm',
            'EstateOfficeLeadActions',
            [
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::NONCE_ACTION),
                'messages' => [
                    'success' => __('Status leadu został zaktualizowany.', 'estate-office'),
                    'error'   => __('Nie udało się zaktualizować statusu leadu.', 'estate-office'),
                    'forbidden' => __('Nie masz uprawnień do aktualizacji tego leadu.', 'estate-office'),
                ],
            ]
        );
    }

    public static function handleStatusUpdate(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Musisz być zalogowany, aby zaktualizować lead.', 'estate-office')], 401);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $leadId = isset($_POST['lead_id']) ? absint(wp_unslash((string) $_POST['lead_id'])) : 0;
        if ($leadId <= 0) {
            wp_send_json_error(['message' => __('Nieprawidłowy identyfikator leadu.', 'estate-office')], 400);
        }

        $post = get_post($leadId);
        if (!$post instanceof WP_Post || $post->post_type !== LeadRegister::POST_TYPE) {
            wp_send_json_error(['message' => __('Nie znaleziono wybranego leadu.', 'estate-office')], 404);
        }

        if (!self::canCurrentUserManageLead($leadId)) {
            wp_send_json_error(['message' => __('Brak uprawnień do aktualizacji leadu.', 'estate-office')], 403);
        }

        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash((string) $_POST['status'])) : '';
        $status = LeadMeta::sanitizeStatus($status);
        update_post_meta($leadId, LeadMeta::META_STATUS, $status);

        wp_send_json_success([
            'status'  => $status,
            'label'   => LeadMeta::getStatusLabel($status),
            'message' => __('Status leadu został zaktualizowany.', 'estate-office'),
        ]);
    }

    public static function forbid(): void
    {
        wp_send_json_error(['message' => __('Brak dostępu.', 'estate-office')], 401);
    }

    public static function canCurrentUserManageLead(int $leadId): bool
    {
        if ($leadId <= 0) {
            return false;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return false;
        }

        if (!current_user_can('edit_post', $leadId)) {
            return false;
        }

        if (current_user_can('edit_others_estate_leads')) {
            return true;
        }

        $assigned = (int) get_post_meta($leadId, LeadMeta::META_ASSIGNED, true);
        if ($assigned === 0) {
            return true;
        }

        return $assigned === $userId;
    }
}
