<?php
/**
 * Ekran zarządzania licencją EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Admin_License_Page
 */
class Estate_Office_Admin_License_Page {

    private const PAGE_SLUG = 'estate-office-license';

    /**
     * @var Estate_Office_License_Manager
     */
    private Estate_Office_License_Manager $license_manager;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_License_Manager $license_manager Menedżer licencji.
     */
    public function __construct( Estate_Office_License_Manager $license_manager ) {
        $this->license_manager = $license_manager;
    }

    /**
     * Rejestruje hooki formularzy licencji.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_activate_license', [ $this, 'handle_activate_license' ] );
        add_action( 'admin_post_estate_office_deactivate_license', [ $this, 'handle_deactivate_license' ] );
        add_action( 'admin_post_estate_office_check_license', [ $this, 'handle_check_license' ] );
    }

    /**
     * Renderuje stronę zarządzania licencją.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_license' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do zarządzania licencją.', 'estate-office' ) );
        }

        $license = $this->license_manager->get_license();

        echo '<div class="wrap estate-office-license">';
        echo '<h1>' . esc_html__( 'Licencja EstateOffice', 'estate-office' ) . '</h1>';
        echo '<p>' . esc_html__( 'Aktywuj licencję, aby odblokować integracje premium oraz wsparcie techniczne.', 'estate-office' ) . '</p>';

        $this->render_notices( $license );

        echo '<div class="estate-office-license__status">';
        echo '<h2>' . esc_html__( 'Status licencji', 'estate-office' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'Aktualny status:', 'estate-office' ) . '</strong> ' . esc_html( $this->get_status_label( $license['status'] ) ) . '</p>';

        if ( ! empty( $license['last_message'] ) ) {
            echo '<p>' . esc_html( $license['last_message'] ) . '</p>';
        }

        if ( ! empty( $license['last_error'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $license['last_error'] ) . '</p></div>';
        }

        echo '<table class="widefat striped" style="max-width:640px;margin-top:1em;">';
        echo '<tbody>';
        echo '<tr><th>' . esc_html__( 'Klucz licencji', 'estate-office' ) . '</th><td>' . esc_html( $this->mask_license_key( $license['license_key'] ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Ostatnia kontrola', 'estate-office' ) . '</th><td>' . esc_html( $license['last_checked'] ? $license['last_checked'] : __( 'Brak danych', 'estate-office' ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Data wygaśnięcia', 'estate-office' ) . '</th><td>' . esc_html( $license['expires_at'] ?: __( 'Brak informacji', 'estate-office' ) ) . '</td></tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $this->render_forms( $license );

        if ( ! empty( $license['activations'] ) ) {
            echo '<h2>' . esc_html__( 'Aktywne instalacje', 'estate-office' ) . '</h2>';
            echo '<table class="widefat striped" style="max-width:640px;">';
            echo '<thead><tr>'; 
            echo '<th>' . esc_html__( 'Adres witryny', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Aktywowano', 'estate-office' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $license['activations'] as $activation ) {
                echo '<tr>';
                echo '<td>';
                if ( $activation['site_url'] ) {
                    echo '<a href="' . esc_url( $activation['site_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $activation['site_url'] ) . '</a>';
                } else {
                    echo esc_html__( 'Brak danych', 'estate-office' );
                }
                echo '</td>';
                echo '<td>' . esc_html( $this->get_status_label( $activation['status'] ?: 'unknown' ) ) . '</td>';
                $activated_at = $activation['activated_at'] ?: __( 'Brak danych', 'estate-office' );
                echo '<td>' . esc_html( $activated_at ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * Obsługuje aktywację licencji.
     *
     * @return void
     */
    public function handle_activate_license() : void {
        $this->verify_permissions();
        check_admin_referer( 'estate_office_activate_license' );

        $license_key = isset( $_POST['license_key'] ) ? wp_unslash( $_POST['license_key'] ) : '';
        $result      = $this->license_manager->activate( $license_key );

        $this->redirect_with_notice( 'activate', $result );
    }

    /**
     * Obsługuje dezaktywację licencji.
     *
     * @return void
     */
    public function handle_deactivate_license() : void {
        $this->verify_permissions();
        check_admin_referer( 'estate_office_deactivate_license' );

        $result = $this->license_manager->deactivate();

        $this->redirect_with_notice( 'deactivate', $result );
    }

    /**
     * Wymusza ponowną weryfikację licencji.
     *
     * @return void
     */
    public function handle_check_license() : void {
        $this->verify_permissions();
        check_admin_referer( 'estate_office_check_license' );

        $result = $this->license_manager->check_status();

        $this->redirect_with_notice( 'check', $result );
    }

    /**
     * Zabezpiecza akcje wymagając odpowiednich uprawnień.
     *
     * @return void
     */
    private function verify_permissions() : void {
        if ( ! current_user_can( 'manage_estate_office_license' ) ) {
            wp_die( esc_html__( 'Brak wymaganych uprawnień.', 'estate-office' ) );
        }
    }

    /**
     * Wyświetla komunikaty po akcjach formularzy.
     *
     * @param array<string,mixed> $license Dane licencji.
     *
     * @return void
     */
    private function render_notices( array $license ) : void {
        $action = isset( $_GET['action_completed'] ) ? sanitize_key( wp_unslash( $_GET['action_completed'] ) ) : '';
        if ( '' === $action ) {
            return;
        }

        $notice_type = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : 'success';
        $message     = 'error' === $notice_type ? ( $license['last_error'] ?: $license['last_message'] ) : $license['last_message'];

        if ( '' === $message ) {
            $message = 'error' === $notice_type
                ? __( 'Operacja zakończyła się niepowodzeniem.', 'estate-office' )
                : __( 'Operacja zakończyła się powodzeniem.', 'estate-office' );
        }

        $class = 'notice notice-' . ( 'error' === $notice_type ? 'error' : 'success' );

        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
    }

    /**
     * Renderuje formularze zarządzania licencją.
     *
     * @param array<string,mixed> $license Dane licencji.
     *
     * @return void
     */
    private function render_forms( array $license ) : void {
        echo '<div class="estate-office-license__forms">';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form card estate-office-license-card">';
        echo '<h2>' . esc_html__( 'Aktywacja licencji', 'estate-office' ) . '</h2>';
        wp_nonce_field( 'estate_office_activate_license' );
        echo '<input type="hidden" name="action" value="estate_office_activate_license" />';
        echo '<p>' . esc_html__( 'Wprowadź klucz licencyjny w formacie EO-XXXX-XXXX-XXXX-XXXX.', 'estate-office' ) . '</p>';
        echo '<p><label for="estate-office-license-key">' . esc_html__( 'Klucz licencyjny', 'estate-office' ) . '</label><br />';
        echo '<input type="text" id="estate-office-license-key" name="license_key" class="regular-text" value="' . esc_attr( $license['license_key'] ) . '" autocomplete="off" /></p>';
        submit_button( __( 'Aktywuj licencję', 'estate-office' ), 'primary', 'submit', false );
        echo '</form>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form card estate-office-license-card">';
        echo '<h2>' . esc_html__( 'Dezaktywacja', 'estate-office' ) . '</h2>';
        wp_nonce_field( 'estate_office_deactivate_license' );
        echo '<input type="hidden" name="action" value="estate_office_deactivate_license" />';
        echo '<p>' . esc_html__( 'Usuń klucz licencyjny z tej instalacji. Możesz go ponownie użyć na innej stronie.', 'estate-office' ) . '</p>';
        $confirm = sprintf( "onclick=\"return confirm('%s');\"", esc_js( __( 'Czy na pewno chcesz dezaktywować licencję?', 'estate-office' ) ) );
        submit_button( __( 'Dezaktywuj licencję', 'estate-office' ), 'secondary', 'submit', false, $confirm );
        echo '</form>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form card estate-office-license-card">';
        echo '<h2>' . esc_html__( 'Sprawdź status', 'estate-office' ) . '</h2>';
        wp_nonce_field( 'estate_office_check_license' );
        echo '<input type="hidden" name="action" value="estate_office_check_license" />';
        echo '<p>' . esc_html__( 'Wymuś natychmiastową weryfikację z serwerem licencji.', 'estate-office' ) . '</p>';
        submit_button( __( 'Odśwież status', 'estate-office' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '</div>';
    }

    /**
     * Maskuje klucz licencyjny.
     *
     * @param string $license_key Klucz licencyjny.
     *
     * @return string
     */
    private function mask_license_key( string $license_key ) : string {
        if ( '' === $license_key ) {
            return __( 'Brak klucza', 'estate-office' );
        }

        if ( strlen( $license_key ) <= 7 ) {
            return $license_key;
        }

        return substr( $license_key, 0, 7 ) . '••••-••••-••';
    }

    /**
     * Zwraca etykietę statusu.
     *
     * @param string $status Status techniczny.
     *
     * @return string
     */
    private function get_status_label( string $status ) : string {
        $labels = [
            'active'   => __( 'Aktywna', 'estate-office' ),
            'inactive' => __( 'Nieaktywna', 'estate-office' ),
            'pending'  => __( 'Oczekuje na weryfikację', 'estate-office' ),
            'expired'  => __( 'Wygasła', 'estate-office' ),
            'invalid'  => __( 'Nieprawidłowa', 'estate-office' ),
            'unknown'  => __( 'Nieznany', 'estate-office' ),
        ];

        $status = strtolower( $status );

        return $labels[ $status ] ?? $labels['unknown'];
    }

    /**
     * Przekierowuje do strony licencji z odpowiednim komunikatem.
     *
     * @param string               $action Nazwa akcji.
     * @param array<string,mixed>  $license Dane licencji.
     *
     * @return void
     */
    private function redirect_with_notice( string $action, array $license ) : void {
        $status      = $license['status'] ?? 'inactive';
        $notice_type = in_array( $status, [ 'active', 'pending' ], true ) ? 'success' : 'error';

        $url = add_query_arg(
            [
                'page'             => self::PAGE_SLUG,
                'action_completed' => $action,
                'notice'           => $notice_type,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }
}
