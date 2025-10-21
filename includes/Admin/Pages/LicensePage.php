<?php
namespace EstateOffice\Admin\Pages;

/**
 * Placeholder for future licensing logic.
 */
class LicensePage extends AbstractPage {
    public function render(): void {
        $this->render_header(
            __( 'Licencja Estate Office', 'estate-office' ),
            __( 'Moduł licencyjny zostanie dodany w późniejszej wersji.', 'estate-office' )
        );
        ?>
        <div class="estate-office-card">
            <p><?php esc_html_e( 'Obecnie licencjonowanie nie jest jeszcze dostępne. Zachowujemy to miejsce na integrację z systemem aktywacji kluczy w wersji 1.0.', 'estate-office' ); ?></p>
        </div>
        <?php
        $this->render_footer();
    }
}
