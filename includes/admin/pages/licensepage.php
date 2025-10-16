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
    }
}
