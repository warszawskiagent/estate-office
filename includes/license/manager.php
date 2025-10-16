<?php

declare(strict_types=1);

namespace EstateOffice\License;

defined('ABSPATH') || exit;

final class Manager
{
    private const OPTION_KEY        = 'estate_office_license_key';
    private const OPTION_EMAIL      = 'estate_office_license_email';
    private const OPTION_STATUS     = 'estate_office_license_status';
    private const OPTION_EXPIRATION = 'estate_office_license_expires_at';
    private const OPTION_LAST_CHECK = 'estate_office_license_last_check';
    private const TRANSIENT_NOTICE  = 'estate_office_license_notice';
    private const TRANSIENT_CRON    = 'estate_office_license_cron_error';
    private const CRON_HOOK         = 'estate_office_license_status_check';
    private const API_ENDPOINT      = 'https://warszawskiagent.pl/wp-json/estate-office/v1/license/';

    private function __construct()
    {
    }

    public static function bootstrap(): void
    {
        add_action('admin_init', [self::class, 'handleRequest']);
        add_action('admin_notices', [self::class, 'renderNotice']);
        add_action('admin_notices', [self::class, 'renderStatusAlert']);
        add_action(self::CRON_HOOK, [self::class, 'runScheduledCheck']);
    }

    public static function activatePlugin(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
        }
    }

    public static function deactivatePlugin(): void
    {
        while (($timestamp = wp_next_scheduled(self::CRON_HOOK)) !== false) {
            wp_unschedule_event((int) $timestamp, self::CRON_HOOK);
        }

        delete_transient(self::TRANSIENT_CRON);
    }

    public static function getData(): array
    {
        return [
            'key'        => (string) get_option(self::OPTION_KEY, ''),
            'email'      => (string) get_option(self::OPTION_EMAIL, ''),
            'status'     => (string) get_option(self::OPTION_STATUS, 'inactive'),
            'expires_at' => (string) get_option(self::OPTION_EXPIRATION, ''),
            'last_check' => (string) get_option(self::OPTION_LAST_CHECK, ''),
            'next_check' => self::getNextCheckDate(),
        ];
    }

    public static function describeStatus(string $status): array
    {
        $status = self::normalizeStatus($status);

        return match ($status) {
            'valid' => [
                'label' => __('Aktywna', 'estate-office'),
                'class' => 'status-valid',
            ],
            'expired' => [
                'label' => __('Wygasła', 'estate-office'),
                'class' => 'status-expired',
            ],
            'invalid' => [
                'label' => __('Nieprawidłowa', 'estate-office'),
                'class' => 'status-invalid',
            ],
            'pending' => [
                'label' => __('Oczekuje na weryfikację', 'estate-office'),
                'class' => 'status-pending',
            ],
            default => [
                'label' => __('Nieaktywna', 'estate-office'),
                'class' => 'status-inactive',
            ],
        };
    }

    public static function handleRequest(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['estate_office_license_action'])) {
            return;
        }

        check_admin_referer('estate_office_license_manage', 'estate_office_license_nonce');

        $action = sanitize_key(wp_unslash((string) ($_POST['estate_office_license_action'] ?? '')));
        $key    = sanitize_text_field(wp_unslash((string) ($_POST['license_key'] ?? '')));
        $email  = sanitize_text_field(wp_unslash((string) ($_POST['license_email'] ?? '')));

        try {
            switch ($action) {
                case 'activate':
                    self::activate($key, $email);
                    break;
                case 'refresh':
                    self::refresh($key ?: (string) get_option(self::OPTION_KEY, ''), $email ?: (string) get_option(self::OPTION_EMAIL, ''));
                    break;
                case 'deactivate':
                    self::deactivate();
                    break;
                default:
                    self::addNotice('warning', __('Nieznana akcja licencyjna.', 'estate-office'));
                    break;
            }
        } catch (\RuntimeException $exception) {
            self::addNotice('error', $exception->getMessage());
        }

        $redirect = wp_get_referer();
        if (!$redirect || strpos($redirect, 'page=estate-office-license') === false) {
            $redirect = admin_url('admin.php?page=estate-office-license');
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function renderNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || sanitize_key((string) $_GET['page']) !== 'estate-office-license') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $notice = get_transient(self::TRANSIENT_NOTICE);
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient(self::TRANSIENT_NOTICE);

        $type = isset($notice['type']) ? sanitize_key((string) $notice['type']) : 'info';
        $type = match ($type) {
            'success' => 'notice-success',
            'error' => 'notice-error',
            'warning' => 'notice-warning',
            default => 'notice-info',
        };

        printf('<div class="notice %1$s"><p>%2$s</p></div>', esc_attr($type), esc_html((string) $notice['message']));
    }

    private static function activate(string $key, string $email): void
    {
        self::validateKeyAndEmail($key, $email);

        $response = self::remoteRequest('activate', $key, $email);
        self::persist($key, $email, $response);

        $message = isset($response['message']) ? (string) $response['message'] : __('Licencja została pomyślnie zweryfikowana.', 'estate-office');
        self::addNotice('success', $message);
    }

    private static function refresh(string $key, string $email): void
    {
        self::refreshStatus($key, $email, false);
    }

    private static function deactivate(): void
    {
        $key   = (string) get_option(self::OPTION_KEY, '');
        $email = (string) get_option(self::OPTION_EMAIL, '');

        if ($key !== '') {
            try {
                self::remoteRequest('deactivate', $key, $email);
            } catch (\RuntimeException $exception) {
                self::addNotice('warning', $exception->getMessage());
            }
        }

        delete_option(self::OPTION_KEY);
        delete_option(self::OPTION_EMAIL);
        update_option(self::OPTION_STATUS, 'inactive');
        delete_option(self::OPTION_EXPIRATION);
        update_option(self::OPTION_LAST_CHECK, current_time('mysql'));

        self::addNotice('success', __('Licencja została dezaktywowana na tej stronie.', 'estate-office'));
    }

    private static function validateKeyAndEmail(string $key, string $email): void
    {
        if ($key === '') {
            throw new \RuntimeException(__('Podaj klucz licencyjny, aby kontynuować.', 'estate-office'));
        }

        if (!preg_match('/^EO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key)) {
            throw new \RuntimeException(__('Klucz licencyjny ma nieprawidłowy format. Użyj formatu EO-XXXX-XXXX-XXXX-XXXX.', 'estate-office'));
        }

        if ($email !== '' && !is_email($email)) {
            throw new \RuntimeException(__('Adres e-mail ma nieprawidłowy format.', 'estate-office'));
        }
    }

    private static function remoteRequest(string $endpoint, string $key, string $email): array
    {
        $url = trailingslashit(self::API_ENDPOINT) . $endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'license_key' => $key,
                'email'       => $email,
                'site_url'    => home_url(),
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException(sprintf(
                /* translators: %s: error message */
                __('Nie można połączyć się z serwerem licencji: %s', 'estate-office'),
                $response->get_error_message()
            ));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body       = wp_remote_retrieve_body($response);
        $data       = json_decode((string) $body, true);

        if (!is_array($data)) {
            $data = [];
        }

        if ($statusCode >= 400) {
            $message = isset($data['message']) ? (string) $data['message'] : __('Serwer licencji zwrócił błąd.', 'estate-office');
            throw new \RuntimeException($message);
        }

        $normalizedStatus = self::normalizeStatus(isset($data['status']) ? (string) $data['status'] : 'valid');
        $data['status']    = $normalizedStatus;

        return $data;
    }

    private static function persist(string $key, string $email, array $response): void
    {
        update_option(self::OPTION_KEY, $key);
        update_option(self::OPTION_EMAIL, $email);
        update_option(self::OPTION_STATUS, self::normalizeStatus((string) ($response['status'] ?? 'valid')));
        update_option(self::OPTION_LAST_CHECK, current_time('mysql'));

        if (!empty($response['expires_at'])) {
            update_option(self::OPTION_EXPIRATION, sanitize_text_field((string) $response['expires_at']));
        } else {
            delete_option(self::OPTION_EXPIRATION);
        }
    }

    private static function refreshStatus(string $key, string $email, bool $silent): void
    {
        if ($key === '') {
            if ($silent) {
                return;
            }

            throw new \RuntimeException(__('Wprowadź klucz licencyjny, aby sprawdzić status.', 'estate-office'));
        }

        $response = self::remoteRequest('status', $key, $email);
        self::persist($key, $email, $response);

        if (!$silent) {
            $message = isset($response['message']) ? (string) $response['message'] : __('Status licencji został odświeżony.', 'estate-office');
            self::addNotice('success', $message);
        }
    }

    private static function normalizeStatus(string $status): string
    {
        $status = sanitize_key($status);

        return in_array($status, ['valid', 'inactive', 'pending', 'expired', 'invalid'], true)
            ? $status
            : 'inactive';
    }

    private static function addNotice(string $type, string $message): void
    {
        set_transient(
            self::TRANSIENT_NOTICE,
            [
                'type'    => $type,
                'message' => $message,
            ],
            MINUTE_IN_SECONDS
        );
    }

    public static function runScheduledCheck(): void
    {
        $key   = (string) get_option(self::OPTION_KEY, '');
        $email = (string) get_option(self::OPTION_EMAIL, '');

        if ($key === '') {
            delete_transient(self::TRANSIENT_CRON);
            return;
        }

        try {
            self::refreshStatus($key, $email, true);
            delete_transient(self::TRANSIENT_CRON);
        } catch (\RuntimeException $exception) {
            set_transient(self::TRANSIENT_CRON, $exception->getMessage(), DAY_IN_SECONDS);
        }
    }

    public static function renderStatusAlert(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cronError = get_transient(self::TRANSIENT_CRON);
        if ($cronError) {
            printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html(sprintf(
                /* translators: %s: error message */
                __('Ostatnie automatyczne sprawdzenie licencji zakończyło się błędem: %s', 'estate-office'),
                (string) $cronError
            )));
        }

        if (isset($_GET['page']) && sanitize_key((string) $_GET['page']) === 'estate-office-license') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $data   = self::getData();
        $status = self::normalizeStatus($data['status']);

        if ($status === 'valid') {
            if ($data['expires_at'] === '') {
                return;
            }

            $timestamp = strtotime($data['expires_at']);
            if ($timestamp === false) {
                return;
            }

            $daysLeft = (int) floor(($timestamp - time()) / DAY_IN_SECONDS);
            if ($daysLeft < 0) {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Licencja EstateOffice wygasła. Odśwież status w zakładce Licencja, aby utrzymać dostęp do aktualizacji.', 'estate-office'));
                return;
            }

            if ($daysLeft <= 14) {
                printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html(sprintf(
                    /* translators: %d: number of days */
                    __('Licencja EstateOffice wygaśnie za %d dni. Odnów ją w panelu klienta, aby zachować wsparcie i aktualizacje.', 'estate-office'),
                    max(1, $daysLeft)
                )));
            }

            return;
        }

        if ($status === 'expired') {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Licencja EstateOffice wygasła. Odnów ją, aby odzyskać dostęp do aktualizacji i modułów premium.', 'estate-office'));
            return;
        }

        if ($status === 'invalid' || $status === 'inactive') {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Licencja EstateOffice nie jest aktywna. Przejdź do zakładki Licencja i zweryfikuj klucz, aby korzystać z pełnej funkcjonalności.', 'estate-office'));
        }
    }

    private static function getNextCheckDate(): string
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);

        if (!$timestamp) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', (int) $timestamp);
    }
}
