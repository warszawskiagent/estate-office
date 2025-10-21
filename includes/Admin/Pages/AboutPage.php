<?php
namespace EstateOffice\Admin\Pages;

/**
 * About page describing plugin features and roadmap.
 */
class AboutPage extends AbstractPage {
    public function render(): void {
        $this->render_header(
            __( 'Estate Office – Informacje', 'estate-office' ),
            __( 'Poznaj funkcjonalności wtyczki i roadmapę rozwoju.', 'estate-office' )
        );
        ?>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Opis wtyczki', 'estate-office' ); ?></h2>
            <p><?php esc_html_e( 'Estate Office to kompleksowy system CRM dla biur nieruchomości. Umożliwia zarządzanie nieruchomościami, klientami, umowami, poszukiwaniami oraz dedykowanymi agentami. Integruje się z Google Maps i pozwala na eksport ofert na stronę WWW.', 'estate-office' ); ?></p>
        </div>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Roadmapa wersji', 'estate-office' ); ?></h2>
            <ul class="estate-office-roadmap">
                <li><strong><?php esc_html_e( '0.1', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Podstawowa struktura wtyczki, role użytkowników, konfiguracja ustawień i panel administracyjny.', 'estate-office' ); ?></li>
                <li><strong><?php esc_html_e( '0.2', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Rejestracja kluczowych typów danych (nieruchomości, klienci, umowy) wraz z podstawowymi formularzami.', 'estate-office' ); ?></li>
                <li><strong><?php esc_html_e( '0.3', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Zaawansowane formularze wieloetapowe i powiązania między rekordami.', 'estate-office' ); ?></li>
                <li><strong><?php esc_html_e( '0.4', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Front-end CRM dla administratorów i agentów z wyszukiwaniem i listami danych.', 'estate-office' ); ?></li>
                <li><strong><?php esc_html_e( '0.5', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Eksport ofert na stronę WWW wraz z szablonami i znacznikami.', 'estate-office' ); ?></li>
                <li><strong><?php esc_html_e( '0.6–0.9', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Integracje z portalami, raportowanie, automatyzacja procesów, kalkulatory finansowe.', 'estate-office' ); ?></li>
                <li><strong><?php esc_html_e( '1.0', 'estate-office' ); ?>:</strong> <?php esc_html_e( 'Pełna funkcjonalność CRM z obsługą eksportu, kalkulatorami i modułami marketingowymi.', 'estate-office' ); ?></li>
            </ul>
        </div>
        <?php
        $this->render_footer();
    }
}
