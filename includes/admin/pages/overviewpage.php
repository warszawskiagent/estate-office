<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

final class OverviewPage extends BasePage
{
    protected function canView(): bool
    {
        return current_user_can('edit_estate_properties') || current_user_can('manage_options');
    }

    protected function getTitle(): string
    {
        return __('Estate Office CRM', 'estate-office');
    }

    protected function renderContent(): void
    {
        echo '<p>' . esc_html__('Wszystkie operacje na nieruchomościach, klientach, umowach i poszukiwaniach realizujesz z poziomu frontowego panelu CRM.', 'estate-office') . '</p>';
        echo '<p>' . esc_html__('Podczas aktywacji wtyczka utworzyła zestaw stron z odpowiednimi shortcode’ami oraz element nawigacji „Estate Office”.', 'estate-office') . '</p>';

        echo '<ul class="eo-overview-links">';
        echo '<li><strong>' . esc_html__('Panel CRM:', 'estate-office') . '</strong> ' . esc_html__('strona z shortcode’em [estate_office_crm] – przeznaczona dla zalogowanych agentów.', 'estate-office') . '</li>';
        echo '<li><strong>' . esc_html__('Oferty na sprzedaż i wynajem:', 'estate-office') . '</strong> ' . esc_html__('strony z shortcode’em [estate_office_offers] z predefiniowanym filtrem transakcji.', 'estate-office') . '</li>';
        echo '<li><strong>' . esc_html__('Katalog agentów:', 'estate-office') . '</strong> ' . esc_html__('strona z shortcode’em [estate_office_agents] prezentująca zespół biura.', 'estate-office') . '</li>';
        echo '</ul>';

        echo '<p>' . esc_html__('Jeżeli strony nie istnieją, możesz je ponownie utworzyć uruchamiając procedurę aktywacyjną (dezaktywacja i ponowna aktywacja wtyczki).', 'estate-office') . '</p>';
    }
}
