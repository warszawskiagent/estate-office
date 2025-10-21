<?php
namespace EstateOffice\Admin\Pages;

/**
 * Dashboard placeholder for version 0.1.
 */
class DashboardPage extends AbstractPage {
    public function render(): void {
        $this->render_header(
            __( 'Estate Office CRM – Pulpit', 'estate-office' ),
            __( 'Wersja 0.1.0: podstawowa konfiguracja i struktura danych.', 'estate-office' )
        );
        ?>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Najważniejsze wskaźniki', 'estate-office' ); ?></h2>
            <p><?php esc_html_e( 'W przyszłych wersjach w tym miejscu pojawią się szczegółowe statystyki dotyczące nieruchomości, umów i poszukiwań.', 'estate-office' ); ?></p>
        </div>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Szybki start', 'estate-office' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Skonfiguruj podstawowe ustawienia, w tym klucz Google Maps i znaki graficzne.', 'estate-office' ); ?></li>
                <li><?php esc_html_e( 'Dodaj agentów biura i przypisz ich do nieruchomości.', 'estate-office' ); ?></li>
                <li><?php esc_html_e( 'Zdefiniuj dodatkowe pola nieruchomości, umów oraz klientów, aby formularze były w pełni dopasowane do Twoich procesów.', 'estate-office' ); ?></li>
            </ol>
        </div>
        <?php
        $this->render_footer();
    }
}
