<?php
/**
 * About page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_About extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-about';

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'About', 'estate-office' );
        $this->page_title = __( 'O wtyczce EstateOffice', 'estate-office' );
        $this->capability = 'manage_options';
    }

    public function render(): void {
        ?>
        <div class="wrap estate-office-wrap">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <div class="card">
                <h2><?php esc_html_e( 'EstateOffice CRM', 'estate-office' ); ?></h2>
                <p><?php esc_html_e( 'EstateOffice to zaawansowany system CRM dla biur nieruchomości. Umożliwia pełne zarządzanie nieruchomościami, klientami, umowami, poszukiwaniami i agentami oraz integruje się z mapami Google i eksportem ofert.', 'estate-office' ); ?></p>
            </div>
            <div class="card">
                <h2><?php esc_html_e( 'Roadmapa', 'estate-office' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Wersja 0.0.1 – Fundamenty bazy danych, formularze CRM w panelu administracyjnym.', 'estate-office' ); ?></li>
                    <li><?php esc_html_e( 'Wersja 0.5.0 – Integracja z front-endem, generowanie stron ofert i profile agentów.', 'estate-office' ); ?></li>
                    <li><?php esc_html_e( 'Wersja 0.8.0 – Automatyzacja eksportów na portale oraz rozbudowany marketing ofert.', 'estate-office' ); ?></li>
                    <li><?php esc_html_e( 'Wersja 1.0 – System licencji i moduły kalkulatorów (notarialny i kredytowy).', 'estate-office' ); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
}
