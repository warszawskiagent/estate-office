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
     * Menedżer licencji.
     *
     * @var Estate_Office_License_Manager
     */
    private Estate_Office_License_Manager $license_manager;

    /**
     * Pulpit CRM.
     *
     * @var Estate_Office_Admin_Dashboard_Page
     */
    private Estate_Office_Admin_Dashboard_Page $dashboard_page;

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
     * Ekran nieruchomości.
     *
     * @var Estate_Office_Admin_Properties_Page
     */
    private Estate_Office_Admin_Properties_Page $properties_page;

    /**
     * Ekran poszukiwań.
     *
     * @var Estate_Office_Admin_Searches_Page
     */
    private Estate_Office_Admin_Searches_Page $searches_page;

    /**
     * Ekran klientów.
     *
     * @var Estate_Office_Admin_Clients_Page
     */
    private Estate_Office_Admin_Clients_Page $clients_page;

    /**
     * Ekran umów.
     *
     * @var Estate_Office_Admin_Contracts_Page
     */
    private Estate_Office_Admin_Contracts_Page $contracts_page;

    /**
     * Ekran licencji.
     *
     * @var Estate_Office_Admin_License_Page
     */
    private Estate_Office_Admin_License_Page $license_page;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_License_Manager $license_manager Menedżer licencji.
     */
    public function __construct( Estate_Office_License_Manager $license_manager ) {
        $this->license_manager = $license_manager;
        $this->dashboard_page   = new Estate_Office_Admin_Dashboard_Page();
        $this->agents_page      = new Estate_Office_Admin_Agents_Page();
        $this->properties_page  = new Estate_Office_Admin_Properties_Page();
        $this->searches_page    = new Estate_Office_Admin_Searches_Page();
        $this->clients_page     = new Estate_Office_Admin_Clients_Page();
        $this->contracts_page   = new Estate_Office_Admin_Contracts_Page();
        $this->settings_page    = new Estate_Office_Admin_Settings_Page();
        $this->license_page     = new Estate_Office_Admin_License_Page( $this->license_manager );
    }

    /**
     * Rejestracja hooków panelu administratora.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_menu', [ $this, 'register_menu_pages' ] );
        $this->dashboard_page->hooks();
        $this->agents_page->hooks();
        $this->properties_page->hooks();
        $this->searches_page->hooks();
        $this->clients_page->hooks();
        $this->contracts_page->hooks();
        $this->settings_page->hooks();
        $this->license_page->hooks();
    }

    /**
     * Dodanie struktury menu "Estate Office CRM" wraz z podstronami.
     *
     * @return void
     */
    public function register_menu_pages() : void {
        $capability_main        = 'manage_estate_office_crm';
        $capability_settings    = 'manage_estate_office_settings';
        $capability_properties  = 'manage_estate_office_properties';
        $capability_searches    = 'manage_estate_office_searches';
        $capability_clients     = 'manage_estate_office_clients';
        $capability_contracts   = 'manage_estate_office_contracts';
        $capability_license     = 'manage_estate_office_license';

        $menu_slug = 'estate-office-crm';

        add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            $capability_main,
            $menu_slug,
            [ $this->dashboard_page, 'render_page' ],
            'dashicons-admin-multisite',
            56
        );

        add_submenu_page(
            $menu_slug,
            __( 'Nieruchomości', 'estate-office' ),
            __( 'Nieruchomości', 'estate-office' ),
            $capability_properties,
            'estate-office-properties',
            [ $this->properties_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'Poszukiwania', 'estate-office' ),
            __( 'Poszukiwania', 'estate-office' ),
            $capability_searches,
            'estate-office-searches',
            [ $this->searches_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'Umowy', 'estate-office' ),
            __( 'Umowy', 'estate-office' ),
            $capability_contracts,
            'estate-office-contracts',
            [ $this->contracts_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'Klienci', 'estate-office' ),
            __( 'Klienci', 'estate-office' ),
            $capability_clients,
            'estate-office-clients',
            [ $this->clients_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            'manage_estate_office_agents',
            'estate-office-agents',
            [ $this->agents_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            $capability_settings,
            'estate-office-settings',
            [ $this->settings_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            $capability_license,
            'estate-office-license',
            [ $this->license_page, 'render_page' ]
        );

        add_submenu_page(
            $menu_slug,
            __( 'O wtyczce', 'estate-office' ),
            __( 'About', 'estate-office' ),
            $capability_main,
            'estate-office-about',
            [ $this, 'render_about_page' ]
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
