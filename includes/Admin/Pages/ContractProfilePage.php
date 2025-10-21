<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Post;

class ContractProfilePage extends AbstractPage {
    use DataFormattingTrait;

    public function render(): void {
        $contract_id = isset( $_GET['contract'] ) ? absint( $_GET['contract'] ) : 0;
        $contract    = $contract_id ? get_post( $contract_id ) : null;

        if ( ! $contract || 'estate_contract' !== $contract->post_type ) {
            $this->render_header( __( 'Profil umowy', 'estate-office' ) );
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Nie znaleziono wskazanej umowy.', 'estate-office' ) . '</p></div>';
            $this->render_footer();
            return;
        }

        $this->maybe_handle_delete( $contract );
        $this->maybe_handle_stage_update( $contract );

        $number   = get_post_meta( $contract_id, Keys::CONTRACT_NUMBER, true );
        $subtitle = $number
            ? sprintf( __( 'Szczegóły umowy #%s.', 'estate-office' ), $number )
            : __( 'Szczegóły wybranej umowy.', 'estate-office' );

        $this->render_header( __( 'Profil umowy', 'estate-office' ), $subtitle );

        settings_errors( 'estate-office-contract-profile' );

        $type        = $this->format_transaction_type( get_post_meta( $contract_id, Keys::CONTRACT_TYPE, true ) );
        $start       = $this->format_date( get_post_meta( $contract_id, Keys::CONTRACT_START_DATE, true ) );
        $indefinite  = (bool) get_post_meta( $contract_id, Keys::CONTRACT_INDEFINITE, true );
        $end         = $indefinite ? __( 'Bezterminowa', 'estate-office' ) : $this->format_date( get_post_meta( $contract_id, Keys::CONTRACT_END_DATE, true ) );
        $commission  = $this->format_commission( $contract_id );
        $stage       = get_post_meta( $contract_id, Keys::CONTRACT_STAGE, true );
        $history     = get_post_meta( $contract_id, Keys::CONTRACT_STAGE_HISTORY, true );
        $clients     = $this->get_clients( $contract_id );
        $property_id = (int) get_post_meta( $contract_id, Keys::CONTRACT_PROPERTY, true );
        $search_id   = (int) get_post_meta( $contract_id, Keys::CONTRACT_SEARCH, true );

        echo '<div class="estate-office-profile">';
        echo '<div class="estate-office-profile__main">';
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Dane umowy', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        $this->render_definition_row( __( 'Numer umowy', 'estate-office' ), $number ?: '—' );
        $this->render_definition_row( __( 'Typ transakcji', 'estate-office' ), $type ?: '—' );
        $this->render_definition_row( __( 'Data zawarcia', 'estate-office' ), $start ?: '—' );
        $this->render_definition_row( __( 'Data zakończenia', 'estate-office' ), $end ?: '—' );
        $this->render_definition_row( __( 'Prowizja', 'estate-office' ), $commission ?: '—' );
        $this->render_definition_row( __( 'Opiekun', 'estate-office' ), $this->format_agent( $contract ) );
        echo '</dl>';
        echo '</div>';
        echo '</div>';

        echo '<div class="estate-office-profile__sidebar">';
        $this->render_stage_panel( $contract, $stage, is_array( $history ) ? $history : [] );
        $this->render_relations_panel( $clients, $property_id, $search_id );
        echo '</div>';
        echo '</div>';

        $this->render_actions( $contract );
        $this->render_footer();
    }

    private function render_stage_panel( WP_Post $contract, $stage, array $history ): void {
        $contract_id = $contract->ID;
        $current     = is_array( $stage ) ? $stage : [];

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Etap umowy', 'estate-office' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'Aktualny etap:', 'estate-office' ) . '</strong> ' . esc_html( $this->format_stage( $current ) ?: __( 'Nieustalony', 'estate-office' ) ) . '</p>';
        echo '<form method="post" class="estate-office-stage-form">';
        wp_nonce_field( 'estate_office_contract_stage', 'estate_office_contract_stage_nonce' );
        echo '<input type="hidden" name="estate_office_contract_action" value="update_stage" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
        echo '<p>';
        echo '<label class="estate-office-label" for="estate_office_stage_select">' . esc_html__( 'Zmień etap', 'estate-office' ) . '</label>';
        $labels = $this->get_stage_labels();

        echo '<select id="estate_office_stage_select" name="contract_stage">';
        foreach ( $labels as $slug => $label ) {
            $selected = ( isset( $current['slug'] ) && $current['slug'] === $slug ) || ( empty( $current['slug'] ) && strtolower( $current['name'] ?? '' ) === strtolower( $label ) ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $slug ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</p>';
        $date_value = isset( $current['date'] ) ? $current['date'] : wp_date( 'Y-m-d' );
        echo '<p>';
        echo '<label class="estate-office-label" for="estate_office_stage_date">' . esc_html__( 'Data etapu', 'estate-office' ) . '</label>';
        echo '<input type="date" id="estate_office_stage_date" name="contract_stage_date" value="' . esc_attr( $date_value ) . '" />';
        echo '</p>';
        echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Aktualizuj etap', 'estate-office' ) . '</button></p>';
        echo '</form>';

        echo '<h3>' . esc_html__( 'Historia etapów', 'estate-office' ) . '</h3>';
        if ( empty( $history ) ) {
            echo '<p>' . esc_html__( 'Brak zapisanej historii etapów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Data', 'estate-office' ) . '</th><th>' . esc_html__( 'Etap', 'estate-office' ) . '</th></tr></thead><tbody>';
            foreach ( $history as $entry ) {
                $date = isset( $entry['date'] ) ? $this->format_date( $entry['date'] ) : '';
                $name = isset( $entry['name'] ) ? $entry['name'] : '';
                echo '<tr><td>' . esc_html( $date ?: '—' ) . '</td><td>' . esc_html( $name ?: '—' ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    private function render_relations_panel( array $clients, int $property_id, int $search_id ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Powiązania', 'estate-office' ) . '</h2>';

        echo '<h3>' . esc_html__( 'Klienci', 'estate-office' ) . '</h3>';
        if ( empty( $clients ) ) {
            echo '<p>' . esc_html__( 'Brak przypisanych klientów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-list">';
            foreach ( $clients as $client ) {
                $url  = $this->get_profile_url( 'client', $client->ID );
                $name = $this->format_client_name( $client );
                echo '<li><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
            }
            echo '</ul>';
        }

        echo '<h3>' . esc_html__( 'Powiązane rekordy', 'estate-office' ) . '</h3>';
        if ( $property_id ) {
            $property = get_post( $property_id );
            if ( $property ) {
                $url   = $this->get_profile_url( 'property', $property_id );
                $label = get_post_meta( $property_id, Keys::PROPERTY_REFERENCE, true ) ?: $property->post_title;
                echo '<p><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'Nieruchomość %s', 'estate-office' ), $label ) ) . '</a></p>';
            }
        } elseif ( $search_id ) {
            $search = get_post( $search_id );
            if ( $search ) {
                $url   = $this->get_profile_url( 'search', $search_id );
                $criteria = get_post_meta( $search_id, Keys::SEARCH_CRITERIA, true );
                $label = is_array( $criteria ) ? ( $criteria['reference'] ?? $search->post_title ) : $search->post_title;
                echo '<p><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'Poszukiwanie %s', 'estate-office' ), $label ) ) . '</a></p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Brak powiązanych nieruchomości lub poszukiwań.', 'estate-office' ) . '</p>';
        }

        echo '</div>';
    }

    private function render_actions( WP_Post $contract ): void {
        $contract_id = $contract->ID;
        $back_url    = add_query_arg(
            [
                'page' => 'estate-office-crm-panel',
                'tab'  => 'contracts',
            ],
            admin_url( 'admin.php' )
        );

        echo '<div class="estate-office-profile__actions">';
        echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<a class="button button-primary" href="' . esc_url( get_edit_post_link( $contract_id, 'url' ) ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';

        if ( current_user_can( 'delete_post', $contract_id ) && current_user_can( 'manage_options' ) ) {
            echo '<form method="post" class="estate-office-inline-form">';
            wp_nonce_field( 'estate_office_delete_contract_' . $contract_id, 'estate_office_delete_contract_nonce' );
            echo '<input type="hidden" name="estate_office_contract_action" value="delete" />';
            echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
            echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Czy na pewno chcesz usunąć tę umowę? Tego działania nie można cofnąć.', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń', 'estate-office' ) . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function maybe_handle_stage_update( WP_Post $contract ): void {
        if ( empty( $_POST['estate_office_contract_action'] ) || 'update_stage' !== $_POST['estate_office_contract_action'] ) {
            return;
        }

        check_admin_referer( 'estate_office_contract_stage', 'estate_office_contract_stage_nonce' );

        $labels = $this->get_stage_labels();
        $slug   = isset( $_POST['contract_stage'] ) ? sanitize_key( wp_unslash( $_POST['contract_stage'] ) ) : '';
        if ( ! isset( $labels[ $slug ] ) ) {
            add_settings_error( 'estate-office-contract-profile', 'invalid-stage', __( 'Wybrany etap jest nieprawidłowy.', 'estate-office' ) );
            return;
        }

        $date = isset( $_POST['contract_stage_date'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_stage_date'] ) ) : '';
        if ( ! $date ) {
            $date = wp_date( 'Y-m-d' );
        }

        $label = $labels[ $slug ];
        $stage = [
            'slug' => $slug,
            'name' => $label,
            'date' => $date,
        ];

        update_post_meta( $contract->ID, Keys::CONTRACT_STAGE, $stage );

        $history = get_post_meta( $contract->ID, Keys::CONTRACT_STAGE_HISTORY, true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        $last = end( $history );
        if ( ! is_array( $last ) || ( $last['slug'] ?? '' ) !== $slug || ( $last['date'] ?? '' ) !== $date ) {
            $history[] = $stage;
            update_post_meta( $contract->ID, Keys::CONTRACT_STAGE_HISTORY, $history );
        }

        add_settings_error( 'estate-office-contract-profile', 'stage-updated', __( 'Etap umowy został zaktualizowany.', 'estate-office' ), 'updated' );
    }

    private function maybe_handle_delete( WP_Post $contract ): void {
        if ( empty( $_POST['estate_office_contract_action'] ) || 'delete' !== $_POST['estate_office_contract_action'] ) {
            return;
        }

        check_admin_referer( 'estate_office_delete_contract_' . $contract->ID, 'estate_office_delete_contract_nonce' );

        if ( ! current_user_can( 'delete_post', $contract->ID ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do usunięcia tej umowy.', 'estate-office' ) );
        }

        wp_trash_post( $contract->ID );

        $redirect = add_query_arg(
            [
                'page'    => 'estate-office-crm-panel',
                'tab'     => 'contracts',
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
    private function get_clients( int $contract_id ): array {
        $ids = get_post_meta( $contract_id, Keys::CONTRACT_CLIENTS, true );
        if ( ! is_array( $ids ) ) {
            return [];
        }

        $clients = [];
        foreach ( $ids as $id ) {
            $client = get_post( (int) $id );
            if ( $client && 'estate_client' === $client->post_type ) {
                $clients[] = $client;
            }
        }

        return $clients;
    }

    private function render_definition_row( string $label, string $value ): void {
        echo '<div class="estate-office-definition__row">';
        echo '<dt>' . esc_html( $label ) . '</dt>';
        echo '<dd>' . esc_html( $value ) . '</dd>';
        echo '</div>';
    }

    private function format_commission( int $contract_id ): string {
        $amount = get_post_meta( $contract_id, Keys::CONTRACT_COMMISSION, true );
        $unit   = get_post_meta( $contract_id, Keys::CONTRACT_COMMISSION_UNIT, true );

        if ( ! is_numeric( $amount ) || $amount <= 0 ) {
            return '';
        }

        switch ( $unit ) {
            case 'percent':
                return number_format_i18n( (float) $amount, 2 ) . ' %';
            case 'eur':
            case 'usd':
                return $this->format_money( $amount, strtoupper( $unit ) );
            case 'pln':
            default:
                return $this->format_money( $amount );
        }
    }

    private function get_stage_labels(): array {
        return [
            'umowa_posrednictwa' => __( 'Umowa pośrednictwa', 'estate-office' ),
            'publikacja_mls'     => __( 'Publikacja w MLS', 'estate-office' ),
            'przygotowanie'      => __( 'Przygotowanie oferty', 'estate-office' ),
            'publikacja'         => __( 'Publikacja oferty', 'estate-office' ),
            'marketing'          => __( 'Marketing i prezentacje', 'estate-office' ),
            'oferta_kupna'       => __( 'Oferta kupna', 'estate-office' ),
            'negocjacje'         => __( 'Negocjacje', 'estate-office' ),
            'umowa_przedwstepna' => __( 'Umowa przedwstępna', 'estate-office' ),
            'umowa_przyrzeczona' => __( 'Umowa przyrzeczona', 'estate-office' ),
            'przekazanie'        => __( 'Przekazanie lokalu', 'estate-office' ),
            'umowa_zakonczona'   => __( 'Umowa zakończona', 'estate-office' ),
        ];
    }
}
