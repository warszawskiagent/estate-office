<?php
namespace EstateOffice\Frontend;

use EstateOffice\Settings\Manager;

/**
 * Handles front-end CRM bootstrap.
 */
class CRM {
    private const OPTION_PAGE_ID = 'estate_office_crm_page_id';

    /**
     * Registers shortcodes.
     */
    public function register_shortcodes(): void {
        add_shortcode( 'estate_office_crm_app', [ $this, 'render_shortcode' ] );
    }

    /**
     * Ensures CRM page exists when accessing the admin area.
     */
    public function maybe_restore_page(): void {
        if ( ! is_admin() ) {
            return;
        }

        $this->ensure_page_exists();
    }

    /**
     * Ensures CRM page exists for administrators.
     */
    public function ensure_page_exists(): void {
        $page_id = $this->get_page_id();

        if ( $page_id && get_post( $page_id ) ) {
            return;
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => __( 'Estate Office CRM', 'estate-office' ),
                'post_content' => '[estate_office_crm_app]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]
        );

        if ( ! is_wp_error( $page_id ) ) {
            update_option( self::OPTION_PAGE_ID, (int) $page_id );
        }
    }

    /**
     * Returns CRM page ID.
     */
    public function get_page_id(): int {
        return (int) get_option( self::OPTION_PAGE_ID, 0 );
    }

    /**
     * Renders CRM shortcode.
     */
    public function render_shortcode(): string {
        if ( ! current_user_can( 'manage_options' ) ) {
            return '<div class="estate-office-crm-restricted">' . esc_html__( 'Dostęp do CRM jest ograniczony do administratorów.', 'estate-office' ) . '</div>';
        }

        $manager  = new Manager();
        $settings = $manager->get_settings();

        ob_start();
        ?>
        <div class="estate-office-crm-app" data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>">
            <div class="estate-office-crm-app__header">
                <h1><?php esc_html_e( 'Estate Office CRM', 'estate-office' ); ?></h1>
                <p><?php esc_html_e( 'Panel w przygotowaniu – w kolejnych wersjach pojawi się pełna obsługa list i formularzy.', 'estate-office' ); ?></p>
            </div>
            <div class="estate-office-crm-app__grid">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm' ) ); ?>" class="estate-office-crm-app__tile">
                    <span class="dashicons dashicons-chart-line"></span>
                    <strong><?php esc_html_e( 'Pulpit', 'estate-office' ); ?></strong>
                    <span><?php esc_html_e( 'Wskaźniki i podsumowania procesów.', 'estate-office' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=estate_property' ) ); ?>" class="estate-office-crm-app__tile">
                    <span class="dashicons dashicons-admin-multisite"></span>
                    <strong><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></strong>
                    <span><?php esc_html_e( 'Wersja 0.1 przygotowuje strukturę danych i pola.', 'estate-office' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=estate_contract' ) ); ?>" class="estate-office-crm-app__tile">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <strong><?php esc_html_e( 'Umowy', 'estate-office' ); ?></strong>
                    <span><?php esc_html_e( 'Wieloetapowe formularze zostaną wdrożone w kolejnych wydaniach.', 'estate-office' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=estate_client' ) ); ?>" class="estate-office-crm-app__tile">
                    <span class="dashicons dashicons-groups"></span>
                    <strong><?php esc_html_e( 'Klienci', 'estate-office' ); ?></strong>
                    <span><?php esc_html_e( 'Twórz profile klientów i powiąż je z umowami.', 'estate-office' ); ?></span>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
