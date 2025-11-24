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
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $this->page_title ); ?></h1>
            </div>
            <div class="card">
                <h2><?php esc_html_e( 'EstateOffice CRM', 'estate-office' ); ?></h2>
                <p><?php esc_html_e( 'EstateOffice to zaawansowany system CRM dla biur nieruchomości. Umożliwia pełne zarządzanie nieruchomościami, klientami, umowami, poszukiwaniami i agentami oraz integruje się z mapami Google i eksportem ofert.', 'estate-office' ); ?></p>
            </div>
            <div class="card">
                <h2><?php esc_html_e( 'Roadmapa', 'estate-office' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Wersja 0.8.2 – Odświeżone, czytelniejsze frontowe formularze umów, nieruchomości, poszukiwań i klientów (wdrożone).', 'estate-office' ); ?></li>
                    <li><?php esc_html_e( 'Wersja 0.9.0 – Moduł licencji (UI + backend): aktywacja klucza, walidacja cykliczna, komunikaty w panelu i shortcode’ach (do wdrożenia).', 'estate-office' ); ?></li>
                    <li><?php esc_html_e( 'Wersja 1.0.0 – Egzekwowanie licencji i release: blokada eksportów, tryb „read-only” CRM, komunikaty administracyjne oraz pakiet wydaniowy (planowane).', 'estate-office' ); ?></li>
                </ol>
            </div>
            <div class="card">
                <h2><?php esc_html_e( 'Shortcode’y', 'estate-office' ); ?></h2>
                <ul>
                    <li><code>[estate_office_crm]</code> – <?php esc_html_e( 'Frontowy panel CRM dostępny po zalogowaniu użytkowników z uprawnieniem `eo_view_crm`.', 'estate-office' ); ?></li>
                    <li><code>[estate_office_offers transaction="SPRZEDAŻ"]</code> – <?php esc_html_e( 'Lista ofert eksportowanych na WWW z filtrem typu transakcji.', 'estate-office' ); ?></li>
                    <li><code>[estate_office_offer id="123"]</code> – <?php esc_html_e( 'Pełna strona pojedynczej oferty z galerią, mapą, kalkulatorami i kartą agenta.', 'estate-office' ); ?></li>
                    <li><code>[estate_office_notary_calculator]</code> – <?php esc_html_e( 'Kalkulator kosztów notarialnych dostępny na dedykowanej stronie lub w treści oferty.', 'estate-office' ); ?></li>
                    <li><code>[estate_office_mortgage_calculator]</code> – <?php esc_html_e( 'Kalkulator kredytowy dla kupujących, który można osadzić na dowolnej stronie.', 'estate-office' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
