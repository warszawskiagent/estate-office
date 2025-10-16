<?php
/**
 * Rejestracja menu administratora EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Admin_Menu
 */
class Estate_Office_Admin_Menu {

    /**
     * Rejestracja hooków panelu administratora.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_menu', [ $this, 'register_menu_pages' ] );
    }

    /**
     * Dodanie struktury menu "Estate Office CRM" wraz z podstronami.
     *
     * @return void
     */
    public function register_menu_pages() : void {
        $capability = 'manage_options';

        $parent_slug = add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            $capability,
            'estate-office-crm',
            [ $this, 'render_dashboard_placeholder' ],
            'dashicons-admin-multisite',
            56
        );

        add_submenu_page(
            $parent_slug,
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            $capability,
            'estate-office-agents',
            [ $this, 'render_agents_placeholder' ]
        );

        add_submenu_page(
            $parent_slug,
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            $capability,
            'estate-office-settings',
            [ $this, 'render_settings_placeholder' ]
        );

        add_submenu_page(
            $parent_slug,
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            $capability,
            'estate-office-license',
            [ $this, 'render_license_placeholder' ]
        );

        add_submenu_page(
            $parent_slug,
            __( 'O wtyczce', 'estate-office' ),
            __( 'About', 'estate-office' ),
            $capability,
            'estate-office-about',
            [ $this, 'render_about_page' ]
        );
    }

    /**
     * Placeholder pulpitu CRM do czasu wdrożenia wersji 0.7.0.
     *
     * @return void
     */
    public function render_dashboard_placeholder() : void {
        $this->render_placeholder(
            __( 'Pulpit CRM będzie dostępny w wersji 0.7.0.', 'estate-office' ),
            'dashboard'
        );
    }

    /**
     * Placeholder modułu agentów (wersja 0.3.0).
     *
     * @return void
     */
    public function render_agents_placeholder() : void {
        $this->render_placeholder(
            __( 'Moduł zarządzania agentami zostanie wdrożony w wersji 0.3.0.', 'estate-office' ),
            'groups'
        );
    }

    /**
     * Placeholder ustawień (wersja 0.3.0).
     *
     * @return void
     */
    public function render_settings_placeholder() : void {
        $this->render_placeholder(
            __( 'Sekcja ustawień (API Google, pola dynamiczne) pojawi się w wersji 0.3.0.', 'estate-office' ),
            'admin-generic'
        );
    }

    /**
     * Placeholder modułu licencji (wersja 0.9.0).
     *
     * @return void
     */
    public function render_license_placeholder() : void {
        $this->render_placeholder(
            __( 'System licencji zostanie dodany w wersji 0.9.0.', 'estate-office' ),
            'lock'
        );
    }

    /**
     * Strona "About" z roadmapą.
     *
     * @return void
     */
    public function render_about_page() : void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'EstateOffice – informacje', 'estate-office' ) . '</h1>';
        echo '<p>' . esc_html__( 'EstateOffice to CRM dla biur nieruchomości rozwijany iteracyjnie od wersji 0.1.0 do 1.0.0.', 'estate-office' ) . '</p>';

        $roadmap_path = ESTATE_OFFICE_PATH . 'ROADMAP.md';
        if ( file_exists( $roadmap_path ) ) {
            echo '<h2>' . esc_html__( 'Roadmapa', 'estate-office' ) . '</h2>';
            echo '<div class="estate-office-roadmap">';
            $roadmap_content = wp_kses_post( wpautop( esc_html( file_get_contents( $roadmap_path ) ) ) );
            echo $roadmap_content;
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Roadmapa nie jest jeszcze dostępna.', 'estate-office' ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Renderowanie generycznego placeholdera.
     *
     * @param string $message Wiadomość do wyświetlenia.
     * @param string $icon    Nazwa ikony dashicons.
     *
     * @return void
     */
    private function render_placeholder( string $message, string $icon ) : void {
        echo '<div class="wrap estate-office-placeholder">';
        printf(
            '<h1><span class="dashicons dashicons-%2$s"></span> %1$s</h1>',
            esc_html__( 'EstateOffice CRM', 'estate-office' ),
            esc_attr( $icon )
        );
        echo '<p>' . esc_html( $message ) . '</p>';
        echo '<p><em>' . esc_html__( 'Szczegółowe informacje znajdziesz w pliku ROADMAP.md.', 'estate-office' ) . '</em></p>';
        echo '</div>';
    }
}
