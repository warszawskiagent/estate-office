<?php
/**
 * Ekran zarządzania eksportami EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Admin_Exports_Page
 */
class Estate_Office_Admin_Exports_Page {

    private const PAGE_SLUG = 'estate-office-exports';

    /**
     * Menedżer eksportów.
     *
     * @var Estate_Office_Portal_Export_Manager
     */
    private Estate_Office_Portal_Export_Manager $export_manager;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Portal_Export_Manager $export_manager Menedżer eksportów.
     */
    public function __construct( Estate_Office_Portal_Export_Manager $export_manager ) {
        $this->export_manager = $export_manager;
    }

    /**
     * Rejestruje hooki formularzy eksportowych.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_export_portal', [ $this, 'handle_save_portal' ] );
        add_action( 'admin_post_estate_office_delete_export_portal', [ $this, 'handle_delete_portal' ] );
        add_action( 'admin_post_estate_office_run_export_portal', [ $this, 'handle_run_export' ] );
        add_action( 'admin_post_estate_office_save_export_schedule', [ $this, 'handle_save_schedule' ] );
    }

    /**
     * Renderuje stronę eksportów.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_exports' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do zarządzania eksportami.', 'estate-office' ) );
        }

        $portals  = $this->export_manager->get_portals();
        $schedule = $this->export_manager->get_schedule();
        $edit_key = isset( $_GET['portal'] ) ? sanitize_key( wp_unslash( $_GET['portal'] ) ) : '';
        $editing  = $edit_key && isset( $portals[ $edit_key ] ) ? $portals[ $edit_key ] : null;

        echo '<div class="wrap estate-office-exports">';
        echo '<h1>' . esc_html__( 'Eksporty na portale', 'estate-office' ) . '</h1>';

        $this->render_notices();

        echo '<div class="estate-office-export-layout">';
        $this->render_schedule_form( $schedule );
        $this->render_portal_form( $editing );
        echo '</div>';

        $this->render_portals_table( $portals );

        echo '</div>';
    }

    /**
     * Obsługuje zapis portalu.
     *
     * @return void
     */
    public function handle_save_portal() : void {
        $this->ensure_capability();
        check_admin_referer( 'estate_office_save_export_portal' );

        $data = [
            'name'              => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'slug'              => sanitize_text_field( wp_unslash( $_POST['slug'] ?? '' ) ),
            'format'            => sanitize_text_field( wp_unslash( $_POST['format'] ?? 'json' ) ),
            'enabled'           => ! empty( $_POST['enabled'] ),
            'transaction_types' => isset( $_POST['transaction_types'] ) ? (array) $_POST['transaction_types'] : [],
            'property_types'    => isset( $_POST['property_types'] ) ? (array) $_POST['property_types'] : [],
            'cities'            => isset( $_POST['cities'] ) ? wp_unslash( $_POST['cities'] ) : [],
            'districts'         => isset( $_POST['districts'] ) ? wp_unslash( $_POST['districts'] ) : [],
        ];

        $this->export_manager->save_portal( $data );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'    => self::PAGE_SLUG,
                    'updated' => 'true',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Obsługuje usunięcie portalu.
     *
     * @return void
     */
    public function handle_delete_portal() : void {
        $this->ensure_capability();
        check_admin_referer( 'estate_office_delete_export_portal' );

        $slug = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
        if ( $slug ) {
            $this->export_manager->delete_portal( $slug );
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'    => self::PAGE_SLUG,
                    'deleted' => 'true',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Obsługuje ręczne uruchomienie eksportu.
     *
     * @return void
     */
    public function handle_run_export() : void {
        $this->ensure_capability();
        check_admin_referer( 'estate_office_run_export_portal' );

        $slug = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
        if ( $slug ) {
            $this->export_manager->generate_exports( $slug );
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'      => self::PAGE_SLUG,
                    'generated' => $slug ?: 'true',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Zapisuje harmonogram eksportów.
     *
     * @return void
     */
    public function handle_save_schedule() : void {
        $this->ensure_capability();
        check_admin_referer( 'estate_office_save_export_schedule' );

        $schedule = isset( $_POST['schedule'] ) ? sanitize_key( wp_unslash( $_POST['schedule'] ) ) : 'twicedaily';
        $this->export_manager->update_schedule( $schedule );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'      => self::PAGE_SLUG,
                    'schedule'  => 'updated',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Renderuje komunikaty o stanie operacji.
     *
     * @return void
     */
    private function render_notices() : void {
        $notice_map = [
            'updated'   => __( 'Konfiguracja portalu została zapisana.', 'estate-office' ),
            'deleted'   => __( 'Eksport został usunięty.', 'estate-office' ),
            'generated' => __( 'Eksport został wygenerowany ponownie.', 'estate-office' ),
            'schedule'  => __( 'Harmonogram eksportów został zaktualizowany.', 'estate-office' ),
        ];

        foreach ( $notice_map as $key => $message ) {
            if ( isset( $_GET[ $key ] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            }
        }
    }

    /**
     * Renderuje formularz harmonogramu.
     *
     * @param string $schedule Aktualny harmonogram.
     *
     * @return void
     */
    private function render_schedule_form( string $schedule ) : void {
        $schedules = wp_get_schedules();
        $options   = [ 'hourly', 'twicedaily', 'daily' ];

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-export-card">';
        wp_nonce_field( 'estate_office_save_export_schedule' );
        echo '<input type="hidden" name="action" value="estate_office_save_export_schedule" />';
        echo '<h2>' . esc_html__( 'Harmonogram eksportów', 'estate-office' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Wybierz jak często wtyczka generuje pliki eksportowe dla portali.', 'estate-office' ) . '</p>';
        echo '<select name="schedule" class="regular-text">';
        foreach ( $options as $option ) {
            if ( ! isset( $schedules[ $option ] ) ) {
                continue;
            }
            printf(
                '<option value="%1$s" %3$s>%2$s</option>',
                esc_attr( $option ),
                esc_html( $schedules[ $option ]['display'] ?? $option ),
                selected( $schedule, $option, false )
            );
        }
        echo '</select>';
        submit_button( __( 'Zapisz harmonogram', 'estate-office' ), 'primary', 'submit', false );
        echo '</form>';
    }

    /**
     * Renderuje formularz dodawania/edycji portalu.
     *
     * @param array<string,mixed>|null $portal Dane portalu.
     *
     * @return void
     */
    private function render_portal_form( ?array $portal ) : void {
        $transaction_types = [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ];
        $property_types    = [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ];

        $defaults = [
            'name'              => '',
            'slug'              => '',
            'format'            => 'json',
            'enabled'           => true,
            'transaction_types' => [],
            'property_types'    => [],
            'cities'            => [],
            'districts'         => [],
        ];

        $portal = wp_parse_args( $portal ?? [], $defaults );

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-export-card">';
        wp_nonce_field( 'estate_office_save_export_portal' );
        echo '<input type="hidden" name="action" value="estate_office_save_export_portal" />';
        echo '<h2>' . esc_html__( 'Konfiguracja portalu', 'estate-office' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Zdefiniuj portal, do którego będą eksportowane oferty oznaczone flagą „Eksport na portale”.', 'estate-office' ) . '</p>';

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-portal-name">' . esc_html__( 'Nazwa portalu', 'estate-office' ) . '</label></th>';
        echo '<td><input type="text" id="estate-office-portal-name" name="name" value="' . esc_attr( $portal['name'] ) . '" class="regular-text" required /></td>';
        echo '</tr>';

        $slug_attr = $portal['slug'] ? 'readonly' : '';
        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-portal-slug">' . esc_html__( 'Slug', 'estate-office' ) . '</label></th>';
        echo '<td><input type="text" id="estate-office-portal-slug" name="slug" value="' . esc_attr( $portal['slug'] ) . '" class="regular-text" ' . $slug_attr . ' />';
        echo '<p class="description">' . esc_html__( 'Unikalny identyfikator feedu. Pozostaw pusty, aby został wygenerowany automatycznie.', 'estate-office' ) . '</p></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-portal-format">' . esc_html__( 'Format pliku', 'estate-office' ) . '</label></th>';
        echo '<td><select id="estate-office-portal-format" name="format">';
        foreach ( [ 'json' => 'JSON', 'xml' => 'XML' ] as $value => $label ) {
            printf(
                '<option value="%1$s" %3$s>%2$s</option>',
                esc_attr( $value ),
                esc_html( $label ),
                selected( $portal['format'], $value, false )
            );
        }
        echo '</select></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Aktywny eksport', 'estate-office' ) . '</th>';
        echo '<td><label><input type="checkbox" name="enabled" value="1" ' . checked( $portal['enabled'], true, false ) . ' /> ' . esc_html__( 'Uwzględniaj portal w harmonogramie', 'estate-office' ) . '</label></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Typy transakcji', 'estate-office' ) . '</th>';
        echo '<td>';
        foreach ( $transaction_types as $type ) {
            $checked = in_array( $type, (array) $portal['transaction_types'], true ) ? 'checked' : '';
            echo '<label class="estate-office-inline"><input type="checkbox" name="transaction_types[]" value="' . esc_attr( $type ) . '" ' . $checked . ' /> ' . esc_html( $type ) . '</label> ';
        }
        echo '<p class="description">' . esc_html__( 'Pozostaw puste, aby eksportować wszystkie typy transakcji.', 'estate-office' ) . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Rodzaje nieruchomości', 'estate-office' ) . '</th>';
        echo '<td>';
        foreach ( $property_types as $type ) {
            $checked = in_array( $type, (array) $portal['property_types'], true ) ? 'checked' : '';
            echo '<label class="estate-office-inline"><input type="checkbox" name="property_types[]" value="' . esc_attr( $type ) . '" ' . $checked . ' /> ' . esc_html( $type ) . '</label> ';
        }
        echo '<p class="description">' . esc_html__( 'Pozostaw puste, aby eksportować wszystkie rodzaje.', 'estate-office' ) . '</p>';
        echo '</td>';
        echo '</tr>';

        $cities = is_array( $portal['cities'] ) ? implode( "\n", $portal['cities'] ) : '';
        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-portal-cities">' . esc_html__( 'Miasta (jedno w linii)', 'estate-office' ) . '</label></th>';
        echo '<td><textarea id="estate-office-portal-cities" name="cities" rows="3" class="large-text code">' . esc_textarea( $cities ) . '</textarea></td>';
        echo '</tr>';

        $districts = is_array( $portal['districts'] ) ? implode( "\n", $portal['districts'] ) : '';
        echo '<tr>';
        echo '<th scope="row"><label for="estate-office-portal-districts">' . esc_html__( 'Dzielnice (jedno w linii)', 'estate-office' ) . '</label></th>';
        echo '<td><textarea id="estate-office-portal-districts" name="districts" rows="3" class="large-text code">' . esc_textarea( $districts ) . '</textarea></td>';
        echo '</tr>';

        echo '</table>';

        submit_button( __( 'Zapisz portal', 'estate-office' ) );
        echo '</form>';
    }

    /**
     * Renderuje tabelę istniejących portali.
     *
     * @param array<string,array<string,mixed>> $portals Lista portali.
     *
     * @return void
     */
    private function render_portals_table( array $portals ) : void {
        echo '<h2>' . esc_html__( 'Skonfigurowane portale', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Poniżej znajdziesz wszystkie feedy eksportowe wraz z ostatnim stanem generowania.', 'estate-office' ) . '</p>';

        if ( empty( $portals ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Nie dodano jeszcze żadnych portali. Skonfiguruj pierwszy portal powyżej.', 'estate-office' ) . '</p></div>';
            return;
        }

        echo '<table class="widefat fixed striped estate-office-export-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Portal', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Format', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Ostatni eksport', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Liczba rekordów', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres feedu', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $portals as $slug => $portal ) {
            $status_label = ! empty( $portal['enabled'] ) ? __( 'Aktywny', 'estate-office' ) : __( 'Wstrzymany', 'estate-office' );
            $status_class = ! empty( $portal['enabled'] ) ? 'status-active' : 'status-paused';
            $last_result  = $portal['last_result'] ?? '';
            $feed_url     = $this->export_manager->get_feed_url( $slug );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $portal['name'] ) . '</strong><br /><code>' . esc_html( $slug ) . '</code></td>';
            echo '<td>' . esc_html( strtoupper( $portal['format'] ) ) . '</td>';
            echo '<td><span class="estate-office-export-status ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
            echo '<td>' . esc_html( $portal['last_generated'] ?: __( 'Brak danych', 'estate-office' ) );
            if ( 'error' === $last_result && ! empty( $portal['last_message'] ) ) {
                echo '<br /><span class="error-message">' . esc_html( $portal['last_message'] ) . '</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html( (string) ( $portal['last_total'] ?? 0 ) ) . '</td>';
            echo '<td>';
            if ( $feed_url ) {
                echo '<input type="text" readonly class="regular-text" value="' . esc_attr( $feed_url ) . '" onclick="this.select();" />';
                if ( ! empty( $portal['file_url'] ) ) {
                    echo '<br /><a href="' . esc_url( $portal['file_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Pobierz ostatni plik', 'estate-office' ) . '</a>';
                }
            } else {
                echo esc_html__( 'Feed zostanie dostępny po pierwszym zapisie.', 'estate-office' );
            }
            echo '</td>';
            echo '<td class="estate-office-export-actions">';
            $edit_url = add_query_arg(
                [
                    'page'   => self::PAGE_SLUG,
                    'portal' => $slug,
                ],
                admin_url( 'admin.php' )
            );
            echo '<a class="button button-secondary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a> ';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="inline-form">';
            wp_nonce_field( 'estate_office_run_export_portal' );
            echo '<input type="hidden" name="action" value="estate_office_run_export_portal" />';
            echo '<input type="hidden" name="portal" value="' . esc_attr( $slug ) . '" />';
            echo '<button type="submit" class="button button-primary">' . esc_html__( 'Generuj teraz', 'estate-office' ) . '</button>';
            echo '</form> ';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="inline-form" onsubmit="return confirm(\'' . esc_js( __( 'Czy na pewno usunąć konfigurację eksportu?', 'estate-office' ) ) . '\');">';
            wp_nonce_field( 'estate_office_delete_export_portal' );
            echo '<input type="hidden" name="action" value="estate_office_delete_export_portal" />';
            echo '<input type="hidden" name="portal" value="' . esc_attr( $slug ) . '" />';
            echo '<button type="submit" class="button button-link-delete">' . esc_html__( 'Usuń', 'estate-office' ) . '</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Waliduje uprawnienia użytkownika.
     *
     * @return void
     */
    private function ensure_capability() : void {
        if ( ! current_user_can( 'manage_estate_office_exports' ) ) {
            wp_die( esc_html__( 'Brak wymaganych uprawnień.', 'estate-office' ) );
        }
    }
}
