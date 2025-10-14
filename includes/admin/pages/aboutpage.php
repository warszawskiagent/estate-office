<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

final class AboutPage extends BasePage
{
    protected function canView(): bool
    {
        return current_user_can('manage_options');
    }

    protected function getTitle(): string
    {
        return __('O wtyczce EstateOffice', 'estate-office');
    }

    protected function renderContent(): void
    {
        echo '<p>' . esc_html__('EstateOffice to kompleksowy CRM dla biur nieruchomości, skupiony na bezpieczeństwie danych, ergonomii i efektywności pracy zespołu.', 'estate-office') . '</p>';
        echo '<h2>' . esc_html__('Roadmapa', 'estate-office') . '</h2>';
        echo '<ul class="ul-disc">';
        echo '<li>' . esc_html__('Moduł nieruchomości z eksportem na WWW i portale.', 'estate-office') . '</li>';
        echo '<li>' . esc_html__('Zaawansowane zarządzanie umowami z historią etapów.', 'estate-office') . '</li>';
        echo '<li>' . esc_html__('Obsługa klientów i poszukiwań oraz pełna integracja z Mapami Google.', 'estate-office') . '</li>';
        echo '<li>' . esc_html__('Kalkulatory notarialne i kredytowe.', 'estate-office') . '</li>';
        echo '</ul>';
        echo '<p>' . esc_html__('Autor: Tomasz Obarski — warszawskiagent.pl', 'estate-office') . '</p>';
    }
}
