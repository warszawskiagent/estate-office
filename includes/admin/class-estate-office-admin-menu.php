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
     * Ekran agentów.
     *
     * @var Estate_Office_Admin_Agents_Page
     */
    private Estate_Office_Admin_Agents_Page $agents_page;

    /**
     * Ekran ustawień.
     *
     * @var Estate_Office_Admin_Settings_Page
     */
    private Estate_Office_Admin_Settings_Page $settings_page;

    /**
     * Konstruktor.
     */
    public function __construct() {
        $this->agents_page   = new Estate_Office_Admin_Agents_Page();
        $this->settings_page = new Estate_Office_Admin_Settings_Page();
    }

    /**
     * Rejestracja hooków panelu administratora.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_menu', [ $this, 'register_menu_pages' ] );
        $this->agents_page->hooks();
        $this->settings_page->hooks();
    }

    /**
     * Dodanie struktury menu "Estate Office CRM" wraz z podstronami.
     *
     * @return void
     */
    public function register_menu_pages() : void {
        $capability_main     = 'manage_estate_office_crm';
        $capability_settings = 'manage_estate_office_settings';

        $parent_slug = add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            $capability_main,
            'estate-office-crm',
            [ $this, 'render_dashboard_placeholder' ],
            'dashicons-admin-multisite',
            56
        );

        add_submenu_page(
            $parent_slug,
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            'manage_estate_office_agents',
            'estate-office-agents',
            [ $this->agents_page, 'render_page' ]
        );

        add_submenu_page(
            $parent_slug,
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            $capability_settings,
            'estate-office-settings',
            [ $this->settings_page, 'render_page' ]
        );

        add_submenu_page(
            $parent_slug,
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            $capability_settings,
            'estate-office-license',
            [ $this, 'render_license_placeholder' ]
        );

        add_submenu_page(
            $parent_slug,
            __( 'O wtyczce', 'estate-office' ),
            __( 'About', 'estate-office' ),
            $capability_main,
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
