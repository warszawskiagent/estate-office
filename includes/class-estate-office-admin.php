<?php
/**
 * Klasa odpowiedzialna za elementy panelu administratora w EstateOffice.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Estate_Office_Admin')) {
    /**
     * Obsługa panelu administratora.
     */
    class Estate_Office_Admin
    {
        /**
         * Rejestruje menu administratora.
         */
        public function register_admin_menus(): void
        {
            add_menu_page(
                __('Estate Office CRM', 'estate-office'),
                __('Estate Office CRM', 'estate-office'),
                'manage_options',
                'estate-office',
                [$this, 'render_about_page'],
                'dashicons-building',
                26
            );

            add_submenu_page(
                'estate-office',
                __('O wtyczce', 'estate-office'),
                __('O wtyczce', 'estate-office'),
                'manage_options',
                'estate-office',
                [$this, 'render_about_page']
            );

            add_submenu_page(
                'estate-office',
                __('Agenci', 'estate-office'),
                __('Agenci', 'estate-office'),
                'manage_options',
                'estate-office-agents',
                [$this, 'render_agents_page']
            );

            add_submenu_page(
                'estate-office',
                __('Ustawienia', 'estate-office'),
                __('Ustawienia', 'estate-office'),
                'manage_options',
                'estate-office-settings',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'estate-office',
                __('Licencja', 'estate-office'),
                __('Licencja', 'estate-office'),
                'manage_options',
                'estate-office-license',
                [$this, 'render_license_page']
            );
        }

        /**
         * Renderuje stronę "O wtyczce".
         */
        public function render_about_page(): void
        {
            $this->render_admin_view('about');
        }

        /**
         * Renderuje stronę "Agenci".
         */
        public function render_agents_page(): void
        {
            $this->render_admin_view('agents');
        }

        /**
         * Renderuje stronę "Ustawienia".
         */
        public function render_settings_page(): void
        {
            $this->render_admin_view('settings');
        }

        /**
         * Renderuje stronę "Licencja".
         */
        public function render_license_page(): void
        {
            $this->render_admin_view('license');
        }

        /**
         * Ładuje widok z katalogu partials.
         */
        private function render_admin_view(string $view): void
        {
            $file = ESTATE_OFFICE_PLUGIN_DIR . 'admin/partials/' . sanitize_key($view) . '.php';

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[EstateOffice] Ładowanie widoku administratora: %s', $file));
            }

            if (file_exists($file)) {
                include $file;
            } else {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Nie można załadować widoku.', 'estate-office'));
            }
        }
    }
}
