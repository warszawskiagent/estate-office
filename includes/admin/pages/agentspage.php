<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

use EstateOffice\Roles\Manager as RolesManager;

final class AgentsPage extends BasePage
{
    protected function canView(): bool
    {
        return current_user_can('list_users');
    }

    protected function getTitle(): string
    {
        return __('Agenci', 'estate-office');
    }

    protected function renderContent(): void
    {
        $role = esc_html(RolesManager::AGENT_ROLE);
        echo '<p>' . esc_html__('Agenci korzystają z dedykowanej roli użytkownika WordPress.', 'estate-office') . '</p>';
        echo '<p>' . sprintf(
            esc_html__('Przejdź do sekcji %1$sUżytkownicy → Dodaj nowego%2$s i przypisz rolę %3$s, aby nadać dostęp do CRM.', 'estate-office'),
            '<strong>',
            '</strong>',
            '<code>' . $role . '</code>'
        ) . '</p>';
        echo '<p>' . esc_html__('W kolejnych wersjach dodamy panel zarządzania agentami bezpośrednio w tym miejscu.', 'estate-office') . '</p>';
    }
}
