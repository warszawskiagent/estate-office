<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

use EstateOffice\License\Manager as LicenseManager;

final class LicensePage extends BasePage
{
    protected function canView(): bool
    {
        return current_user_can('manage_options');
    }

    protected function getTitle(): string
    {
        return __('Licencja', 'estate-office');
    }

    protected function renderContent(): void
    {
        if (defined('ESTATE_OFFICE_LICENSE_SUSPENDED') && ESTATE_OFFICE_LICENSE_SUSPENDED) {
            echo '<div class="notice notice-info"><p>';
            echo esc_html__(
                'Moduł licencyjny EstateOffice jest tymczasowo wyłączony do wydania wersji 1.0.1. Bieżące instalacje mogą korzystać z wtyczki bez aktywacji klucza.',
                'estate-office'
            );
            echo '</p></div>';

            echo '<p>';
            echo esc_html__(
                'Po ponownym uruchomieniu modułu w tej zakładce ponownie pojawi się możliwość weryfikacji licencji.',
                'estate-office'
            );
            echo '</p>';

            return;
        }

        $data        = LicenseManager::getData();
        $status      = LicenseManager::describeStatus($data['status']);
        $statusClass = 'eo-license-status ' . $status['class'];

        echo '<p>' . esc_html__('Aktywna licencja umożliwia korzystanie z aktualizacji, wsparcia oraz modułów premium EstateOffice.', 'estate-office') . '</p>';
        echo '<div class="eo-license-overview">';
        echo '<strong>' . esc_html__('Status licencji:', 'estate-office') . '</strong> ';
        echo '<span class="' . esc_attr($statusClass) . '">' . esc_html($status['label']) . '</span>';

        if (!empty($data['expires_at'])) {
            echo '<p><strong>' . esc_html__('Ważna do:', 'estate-office') . '</strong> ' . esc_html($data['expires_at']) . '</p>';
        }

        if (!empty($data['last_check'])) {
            echo '<p><strong>' . esc_html__('Ostatnie sprawdzenie:', 'estate-office') . '</strong> ' . esc_html($data['last_check']) . '</p>';
        }

        if (!empty($data['next_check'])) {
            echo '<p><strong>' . esc_html__('Planowane sprawdzenie:', 'estate-office') . '</strong> ' . esc_html($data['next_check']) . '</p>';
        }

        echo '</div>';

        echo '<form method="post" action="" class="eo-license-form">';
        wp_nonce_field('estate_office_license_manage', 'estate_office_license_nonce');

        echo '<table class="form-table" role="presentation">';
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-license-key">' . esc_html__('Klucz licencyjny', 'estate-office') . '</label></th>';
        echo '<td>';
        printf(
            '<input name="license_key" id="estate-office-license-key" type="text" class="regular-text" value="%s" placeholder="EO-XXXX-XXXX-XXXX-XXXX" autocomplete="off" />',
            esc_attr($data['key'])
        );
        echo '<p class="description">' . esc_html__('Klucz znajdziesz w panelu klienta warszawskiagent.pl. Format: EO-XXXX-XXXX-XXXX-XXXX.', 'estate-office') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-license-email">' . esc_html__('Adres e-mail (opcjonalnie)', 'estate-office') . '</label></th>';
        echo '<td>';
        printf(
            '<input name="license_email" id="estate-office-license-email" type="email" class="regular-text" value="%s" placeholder="biuro@przyklad.pl" autocomplete="email" />',
            esc_attr($data['email'])
        );
        echo '<p class="description">' . esc_html__('Adres e-mail pozwala powiązać licencję z kontem i otrzymywać powiadomienia.', 'estate-office') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary" name="estate_office_license_action" value="activate">' . esc_html__('Zapisz i zweryfikuj licencję', 'estate-office') . '</button> ';

        if (!empty($data['key'])) {
            echo '<button type="submit" class="button" name="estate_office_license_action" value="refresh">' . esc_html__('Sprawdź status', 'estate-office') . '</button> ';
            echo '<button type="submit" class="button button-secondary" name="estate_office_license_action" value="deactivate" onclick="return confirm(\'' . esc_js(__('Czy na pewno chcesz dezaktywować licencję na tej stronie?', 'estate-office')) . '\');">' . esc_html__('Dezaktywuj licencję', 'estate-office') . '</button>';
        }

        echo '</p>';
        echo '</form>';

        $history = LicenseManager::getHistory();

        echo '<h2>' . esc_html__('Historia sprawdzeń licencji', 'estate-office') . '</h2>';

        if (empty($history)) {
            echo '<p>' . esc_html__('Brak zapisanych zdarzeń. Po weryfikacji licencji lub automatycznym sprawdzeniu historia pojawi się w tym miejscu.', 'estate-office') . '</p>';

            return;
        }

        echo '<table class="widefat striped eo-license-history">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Data i czas', 'estate-office') . '</th>';
        echo '<th scope="col">' . esc_html__('Typ zdarzenia', 'estate-office') . '</th>';
        echo '<th scope="col">' . esc_html__('Wynik', 'estate-office') . '</th>';
        echo '<th scope="col">' . esc_html__('Komunikat', 'estate-office') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($history as $entry) {
            $time    = !empty($entry['time']) ? esc_html($entry['time']) : esc_html__('Brak danych', 'estate-office');
            $context = esc_html(LicenseManager::describeContext((string) ($entry['context'] ?? 'manage')));

            $result = isset($entry['result']) && $entry['result'] === 'error'
                ? '<span class="eo-status-badge eo-status-error">' . esc_html__('Błąd', 'estate-office') . '</span>'
                : '<span class="eo-status-badge eo-status-success">' . esc_html__('Sukces', 'estate-office') . '</span>';

            $message = !empty($entry['message']) ? wp_kses_post($entry['message']) : '&mdash;';

            echo '<tr>';
            echo '<td>' . $time . '</td>';
            echo '<td>' . $context . '</td>';
            echo '<td>' . $result . '</td>';
            echo '<td>' . $message . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
