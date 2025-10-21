<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Post;

class ClientProfilePage extends AbstractPage {
    use DataFormattingTrait;

    public function render(): void {
        $client_id = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;
        $client    = $client_id ? get_post( $client_id ) : null;

        if ( ! $client || 'estate_client' !== $client->post_type ) {
            $this->render_header( __( 'Profil klienta', 'estate-office' ) );
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Nie znaleziono wskazanego klienta.', 'estate-office' ) . '</p></div>';
            $this->render_footer();
            return;
        }

        $this->maybe_handle_delete( $client );

        $type      = get_post_meta( $client_id, Keys::CLIENT_TYPE, true );
        $full_name = $this->format_client_name( $client );
        $subtitle  = sprintf( __( 'Szczegóły profilu: %s', 'estate-office' ), $full_name );

        $this->render_header( __( 'Profil klienta', 'estate-office' ), $subtitle );

        settings_errors( 'estate-office-client-profile' );

        $contact = [
            'phone'   => get_post_meta( $client_id, Keys::CLIENT_PHONE, true ),
            'email'   => get_post_meta( $client_id, Keys::CLIENT_EMAIL, true ),
            'website' => get_post_meta( $client_id, Keys::CLIENT_WEBSITE, true ),
        ];
        $main_address = get_post_meta( $client_id, Keys::CLIENT_ADDRESS_MAIN, true );
        $mail_address = get_post_meta( $client_id, Keys::CLIENT_ADDRESS_MAIL, true );

        echo '<div class="estate-office-profile">';
        echo '<div class="estate-office-profile__main">';
        $this->render_basic_information( $client, $type );
        $this->render_contact_card( $contact );
        $this->render_identification_card( $client_id, $type );
        $this->render_addresses_card( $main_address, $mail_address );
        echo '</div>';

        echo '<div class="estate-office-profile__sidebar">';
        $contracts = $this->get_contracts( $client_id );
        $this->render_contracts_panel( $contracts );
        $this->render_offers_panel( $contracts );
        echo '</div>';
        echo '</div>';

        $this->render_actions( $client );
        $this->render_footer();
    }

    private function render_basic_information( WP_Post $client, string $type ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Dane podstawowe', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        $this->render_definition_row( __( 'Typ klienta', 'estate-office' ), $type === 'firma' ? __( 'Firma', 'estate-office' ) : __( 'Osoba fizyczna', 'estate-office' ) );
        $this->render_definition_row( __( 'Nazwa/Imię i nazwisko', 'estate-office' ), $this->format_client_name( $client ) );
        if ( 'firma' === $type ) {
            $company_rep = get_post_meta( $client->ID, Keys::CLIENT_COMPANY_REP, true );
            $company     = get_post_meta( $client->ID, Keys::CLIENT_COMPANY_NAME, true );
            $this->render_definition_row( __( 'Nazwa firmy', 'estate-office' ), $company ?: '—' );
            $this->render_definition_row( __( 'Reprezentant', 'estate-office' ), $company_rep ?: '—' );
        }
        echo '</dl>';
        echo '</div>';
    }

    private function render_contact_card( array $contact ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Dane kontaktowe', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        $this->render_definition_row( __( 'Telefon', 'estate-office' ), $contact['phone'] ?: '—' );
        if ( $contact['email'] ) {
            $email_link = '<a href="mailto:' . esc_attr( $contact['email'] ) . '">' . esc_html( $contact['email'] ) . '</a>';
            $this->render_definition_row_html( __( 'E-mail', 'estate-office' ), $email_link );
        } else {
            $this->render_definition_row( __( 'E-mail', 'estate-office' ), '—' );
        }
        if ( $contact['website'] ) {
            $website_link = '<a href="' . esc_url( $contact['website'] ) . '" target="_blank" rel="noopener">' . esc_html( $contact['website'] ) . '</a>';
            $this->render_definition_row_html( __( 'Strona WWW', 'estate-office' ), $website_link );
        } else {
            $this->render_definition_row( __( 'Strona WWW', 'estate-office' ), '—' );
        }
        echo '</dl>';
        echo '</div>';
    }

    private function render_identification_card( int $client_id, string $type ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Dane identyfikacyjne', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        if ( 'firma' === $type ) {
            $this->render_definition_row( __( 'NIP', 'estate-office' ), get_post_meta( $client_id, Keys::CLIENT_NIP, true ) ?: '—' );
            $this->render_definition_row( __( 'KRS', 'estate-office' ), get_post_meta( $client_id, Keys::CLIENT_KRS, true ) ?: '—' );
            $this->render_definition_row( __( 'REGON', 'estate-office' ), get_post_meta( $client_id, Keys::CLIENT_REGON, true ) ?: '—' );
        } else {
            $this->render_definition_row( __( 'PESEL', 'estate-office' ), get_post_meta( $client_id, Keys::CLIENT_PESEL, true ) ?: '—' );
            $document_type = $this->map_document_type( get_post_meta( $client_id, Keys::CLIENT_DOCUMENT_TYPE, true ) );
            $this->render_definition_row( __( 'Dokument tożsamości', 'estate-office' ), $document_type ?: '—' );
            $this->render_definition_row( __( 'Numer dokumentu', 'estate-office' ), get_post_meta( $client_id, Keys::CLIENT_DOCUMENT_NUMBER, true ) ?: '—' );
        }
        echo '</dl>';
        echo '</div>';
    }

    private function render_addresses_card( $main_address, $mail_address ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Adresy', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        $this->render_definition_row( __( 'Adres zamieszkania/rejestrowy', 'estate-office' ), $this->format_address( $main_address ) ?: '—' );
        $this->render_definition_row( __( 'Adres korespondencyjny', 'estate-office' ), $this->format_address( $mail_address ) ?: '—' );
        echo '</dl>';
        echo '</div>';
    }

    private function render_contracts_panel( array $contracts ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Powiązane umowy', 'estate-office' ) . '</h2>';
        if ( empty( $contracts ) ) {
            echo '<p>' . esc_html__( 'Brak powiązanych umów.', 'estate-office' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer umowy', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Data zawarcia', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $contracts as $contract ) {
            $number = get_post_meta( $contract->ID, Keys::CONTRACT_NUMBER, true ) ?: $contract->post_title;
            $type   = $this->format_transaction_type( get_post_meta( $contract->ID, Keys::CONTRACT_TYPE, true ) );
            $date   = $this->format_date( get_post_meta( $contract->ID, Keys::CONTRACT_START_DATE, true ) );
            $url    = $this->get_profile_url( 'contract', $contract->ID );
            echo '<tr>';
            echo '<td>' . esc_html( $number ) . '</td>';
            echo '<td>' . esc_html( $type ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $date ?: '—' ) . '</td>';
            echo '<td><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Przejdź do umowy', 'estate-office' ) . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    private function render_offers_panel( array $contracts ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Powiązane oferty i poszukiwania', 'estate-office' ) . '</h2>';
        $properties = [];
        $searches   = [];

        foreach ( $contracts as $contract ) {
            $property_id = (int) get_post_meta( $contract->ID, Keys::CONTRACT_PROPERTY, true );
            if ( $property_id ) {
                $properties[ $property_id ] = get_post( $property_id );
            }
            $search_id = (int) get_post_meta( $contract->ID, Keys::CONTRACT_SEARCH, true );
            if ( $search_id ) {
                $searches[ $search_id ] = get_post( $search_id );
            }
        }

        if ( empty( array_filter( $properties ) ) && empty( array_filter( $searches ) ) ) {
            echo '<p>' . esc_html__( 'Brak powiązanych ofert ani poszukiwań.', 'estate-office' ) . '</p>';
            echo '</div>';
            return;
        }

        if ( ! empty( $properties ) ) {
            echo '<h3>' . esc_html__( 'Nieruchomości', 'estate-office' ) . '</h3>';
            echo '<ul class="estate-office-list">';
            foreach ( $properties as $property_id => $property ) {
                if ( ! $property ) {
                    continue;
                }
                $reference = get_post_meta( $property_id, Keys::PROPERTY_REFERENCE, true ) ?: $property->post_title;
                $url       = $this->get_profile_url( 'property', $property_id );
                echo '<li><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( $reference ) . '</a></li>';
            }
            echo '</ul>';
        }

        if ( ! empty( $searches ) ) {
            echo '<h3>' . esc_html__( 'Poszukiwania', 'estate-office' ) . '</h3>';
            echo '<ul class="estate-office-list">';
            foreach ( $searches as $search_id => $search ) {
                if ( ! $search ) {
                    continue;
                }
                $criteria = get_post_meta( $search_id, Keys::SEARCH_CRITERIA, true );
                $reference = is_array( $criteria ) ? ( $criteria['reference'] ?? $search->post_title ) : $search->post_title;
                $url       = $this->get_profile_url( 'search', $search_id );
                echo '<li><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( $reference ) . '</a></li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    private function render_actions( WP_Post $client ): void {
        $back_url = add_query_arg(
            [
                'page' => 'estate-office-crm-panel',
                'tab'  => 'clients',
            ],
            admin_url( 'admin.php' )
        );

        echo '<div class="estate-office-profile__actions">';
        echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<a class="button button-primary" href="' . esc_url( get_edit_post_link( $client->ID, 'url' ) ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';

        if ( current_user_can( 'delete_post', $client->ID ) && current_user_can( 'manage_options' ) ) {
            echo '<form method="post" class="estate-office-inline-form">';
            wp_nonce_field( 'estate_office_delete_client_' . $client->ID, 'estate_office_delete_client_nonce' );
            echo '<input type="hidden" name="estate_office_client_action" value="delete" />';
            echo '<input type="hidden" name="client_id" value="' . esc_attr( $client->ID ) . '" />';
            echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Czy na pewno chcesz usunąć tego klienta? Tego działania nie można cofnąć.', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń', 'estate-office' ) . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function maybe_handle_delete( WP_Post $client ): void {
        if ( empty( $_POST['estate_office_client_action'] ) || 'delete' !== $_POST['estate_office_client_action'] ) {
            return;
        }

        check_admin_referer( 'estate_office_delete_client_' . $client->ID, 'estate_office_delete_client_nonce' );

        if ( ! current_user_can( 'delete_post', $client->ID ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do usunięcia tego klienta.', 'estate-office' ) );
        }

        wp_trash_post( $client->ID );

        $redirect = add_query_arg(
            [
                'page'    => 'estate-office-crm-panel',
                'tab'     => 'clients',
                'deleted' => 1,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * @return WP_Post[]
     */
    private function get_contracts( int $client_id ): array {
        $ids = get_post_meta( $client_id, Keys::CLIENT_CONTRACTS, true );
        if ( ! is_array( $ids ) ) {
            return [];
        }

        $contracts = [];
        foreach ( $ids as $id ) {
            $contract = get_post( (int) $id );
            if ( $contract && 'estate_contract' === $contract->post_type ) {
                $contracts[] = $contract;
            }
        }

        return $contracts;
    }

    private function render_definition_row( string $label, string $value ): void {
        echo '<dt class="estate-office-definition__term">' . esc_html( $label ) . '</dt>';
        echo '<dd class="estate-office-definition__value">' . esc_html( $value ) . '</dd>';
    }

    private function render_definition_row_html( string $label, string $html ): void {
        echo '<dt class="estate-office-definition__term">' . esc_html( $label ) . '</dt>';
        echo '<dd class="estate-office-definition__value">' . $html . '</dd>';
    }

    private function map_document_type( string $type ): string {
        $map = [
            'dowod'        => __( 'Dowód osobisty', 'estate-office' ),
            'paszport'     => __( 'Paszport', 'estate-office' ),
            'karta_pobytu' => __( 'Karta pobytu', 'estate-office' ),
        ];

        if ( isset( $map[ $type ] ) ) {
            return $map[ $type ];
        }

        $fallback = str_replace( '_', ' ', $type );
        return $fallback ? ucfirst( $fallback ) : $type;
    }
}
