<?php

declare(strict_types=1);

namespace EstateOffice\License;

defined('ABSPATH') || exit;

use function WP_CLI\Utils\format_items;

/**
 * WP-CLI integration for license management.
 */
final class Cli
{
    private const MASK_FILL = '•';

    public static function bootstrap(): void
    {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        \WP_CLI::add_command('estate-office license', self::class);
    }

    /**
     * Displays current license information.
     *
     * ## EXAMPLES
     *
     *     wp estate-office license status
     */
    public function status(array $args, array $assocArgs): void
    {
        $data   = Manager::getData();
        $status = Manager::describeStatus($data['status']);

        $rows = [
            ['Pole' => __('Status', 'estate-office'), 'Wartość' => $status['label']],
            ['Pole' => __('Klucz', 'estate-office'), 'Wartość' => $this->maskKey($data['key'])],
            ['Pole' => __('Adres e-mail', 'estate-office'), 'Wartość' => $data['email'] !== '' ? $data['email'] : __('(brak)', 'estate-office')],
            ['Pole' => __('Wygasa', 'estate-office'), 'Wartość' => $data['expires_at'] !== '' ? $data['expires_at'] : __('(brak)', 'estate-office')],
            ['Pole' => __('Ostatnie sprawdzenie', 'estate-office'), 'Wartość' => $data['last_check'] !== '' ? $data['last_check'] : __('(brak)', 'estate-office')],
            ['Pole' => __('Następne sprawdzenie', 'estate-office'), 'Wartość' => $data['next_check'] !== '' ? $data['next_check'] : __('(brak)', 'estate-office')],
        ];

        format_items('table', $rows, ['Pole', 'Wartość']);
    }

    /**
     * Activates the license using the provided key.
     *
     * ## OPTIONS
     *
     * --key=<key>
     * : Pełny klucz licencyjny w formacie EO-XXXX-XXXX-XXXX-XXXX.
     *
     * [--email=<email>]
     * : Adres e-mail powiązany z licencją.
     *
     * ## EXAMPLES
     *
     *     wp estate-office license activate --key=EO-XXXX-XXXX-XXXX-XXXX --email=biuro@example.com
     */
    public function activate(array $args, array $assocArgs): void
    {
        $key = isset($assocArgs['key']) ? (string) $assocArgs['key'] : '';

        if ($key === '') {
            \WP_CLI::error(__('Podaj klucz licencyjny przy aktywacji.', 'estate-office'));
        }

        $email  = isset($assocArgs['email']) ? (string) $assocArgs['email'] : '';
        $result = Manager::processAction('activate', $key, $email, true);

        if ($result['status'] !== 'success') {
            \WP_CLI::error($result['message']);
        }

        \WP_CLI::success($result['message']);
    }

    /**
     * Refreshes the license status.
     *
     * ## OPTIONS
     *
     * [--key=<key>]
     * : Opcjonalny klucz licencyjny użyty do odświeżenia.
     *
     * [--email=<email>]
     * : Opcjonalny adres e-mail powiązany z licencją.
     *
     * ## EXAMPLES
     *
     *     wp estate-office license refresh
     */
    public function refresh(array $args, array $assocArgs): void
    {
        $key    = isset($assocArgs['key']) ? (string) $assocArgs['key'] : '';
        $email  = isset($assocArgs['email']) ? (string) $assocArgs['email'] : '';
        $result = Manager::processAction('refresh', $key, $email, true);

        if ($result['status'] !== 'success') {
            \WP_CLI::error($result['message']);
        }

        \WP_CLI::success($result['message']);
    }

    /**
     * Deactivates the license for the current site.
     *
     * ## EXAMPLES
     *
     *     wp estate-office license deactivate
     */
    public function deactivate(array $args, array $assocArgs): void
    {
        $result = Manager::processAction('deactivate', '', '', true);

        if ($result['status'] !== 'success') {
            \WP_CLI::error($result['message']);
        }

        \WP_CLI::success($result['message']);
    }

    /**
     * Displays the log of recent license checks.
     *
     * ## EXAMPLES
     *
     *     wp estate-office license history
     */
    public function history(array $args, array $assocArgs): void
    {
        $history = Manager::getHistory();

        if (empty($history)) {
            \WP_CLI::log(__('Brak zapisanej historii sprawdzeń licencji.', 'estate-office'));
            return;
        }

        $rows = array_map(static function (array $entry): array {
            return [
                'Data'     => $entry['time'] ?? '',
                'Status'   => $entry['result'] === 'error' ? __('Błąd', 'estate-office') : __('Sukces', 'estate-office'),
                'Kontekst' => $entry['context'] ?? '',
                'Wiadomość' => $entry['message'] ?? '',
            ];
        }, $history);

        format_items('table', $rows, ['Data', 'Status', 'Kontekst', 'Wiadomość']);
    }

    private function maskKey(string $key): string
    {
        if ($key === '') {
            return __('(brak)', 'estate-office');
        }

        $length = strlen($key);

        if ($length <= 4) {
            return str_repeat(self::MASK_FILL, $length);
        }

        $visiblePrefix = substr($key, 0, 7);
        $visibleSuffix = substr($key, -4);
        $maskedLength  = max(0, $length - strlen($visiblePrefix) - strlen($visibleSuffix));

        return $visiblePrefix . str_repeat(self::MASK_FILL, $maskedLength) . $visibleSuffix;
    }
}
