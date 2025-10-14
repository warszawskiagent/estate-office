<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

final class SettingsPage extends BasePage
{
    protected function canView(): bool
    {
        return current_user_can('manage_options');
    }

    protected function getTitle(): string
    {
        return __('Ustawienia EstateOffice', 'estate-office');
    }

    protected function renderContent(): void
    {
        settings_errors('estate_office_settings_group');

        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('estate_office_settings_group');
        do_settings_sections('estate-office-settings');
        submit_button(__('Zapisz ustawienia', 'estate-office'));
        echo '</form>';

    }

    public static function enqueueAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'estate-office-crm_page_estate-office-settings') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'estate-office-settings',
            plugins_url('assets/js/settings.js', ESTATE_OFFICE_PLUGIN_FILE),
            ['jquery'],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );

        wp_localize_script('estate-office-settings', 'estateOfficeSettings', [
            'choose' => __('Wybierz', 'estate-office'),
            'remove' => __('Usuń', 'estate-office'),
        ]);

        wp_enqueue_style(
            'estate-office-settings',
            plugins_url('assets/css/settings.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );
    }
}
