<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

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
        echo '<p>' . esc_html__('Moduł zarządzania licencją zostanie dodany w jednej z kolejnych iteracji tworzenia wtyczki.', 'estate-office') . '</p>';
        echo '<p>' . esc_html__('Upewnij się, że posiadasz ważną licencję, aby korzystać z pełnego zakresu funkcjonalności EstateOffice.', 'estate-office') . '</p>';
    }
}
