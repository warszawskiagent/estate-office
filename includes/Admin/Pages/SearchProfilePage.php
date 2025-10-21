<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Post;

class SearchProfilePage extends AbstractPage {
    use DataFormattingTrait;

    public function render(): void {
        $search_id = isset( $_GET['search'] ) ? absint( $_GET['search'] ) : 0;
        $search    = $search_id ? get_post( $search_id ) : null;

        if ( ! $search || 'estate_search' !== $search->post_type ) {
            $this->render_header( __( 'Profil poszukiwania', 'estate-office' ) );
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Nie znaleziono wskazanego poszukiwania.', 'estate-office' ) . '</p></div>';
            $this->render_footer();
            return;
        }

        $this->maybe_handle_delete( $search );

        $criteria = get_post_meta( $search_id, Keys::SEARCH_CRITERIA, true );
        $criteria = is_array( $criteria ) ? $criteria : [];

        $reference = $criteria['reference'] ?? $search->post_title;
        $subtitle  = sprintf( __( 'Poszukiwanie %s', 'estate-office' ), $reference );

        $this->render_header( __( 'Profil poszukiwania', 'estate-office' ), $subtitle );

        settings_errors( 'estate-office-search-profile' );

        $contract_id = (int) get_post_meta( $search_id, Keys::SEARCH_CONTRACT, true );

        echo '<div class="estate-office-profile">';
        echo '<div class="estate-office-profile__main">';
        $this->render_criteria_card( $criteria );
        $this->render_preferences_card( $criteria );
        echo '</div>';

        echo '<div class="estate-office-profile__sidebar">';
        $this->render_description_panel( $criteria['description'] ?? '' );
        $this->render_relations_panel( $contract_id );
        echo '</div>';
        echo '</div>';

        $this->render_actions( $search );
        $this->render_footer();
    }

    private function render_criteria_card( array $criteria ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Kryteria podstawowe', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        $this->render_definition_row( __( 'Typ transakcji', 'estate-office' ), $this->format_transaction_type( $criteria['transaction_type'] ?? '' ) );
        $this->render_definition_row( __( 'Rodzaj nieruchomości', 'estate-office' ), $this->format_property_kind( $criteria['property_kind'] ?? '' ) );
        $this->render_definition_row( __( 'Budżet', 'estate-office' ), $this->format_budget( $criteria ) ?: '—' );
        $this->render_definition_row( __( 'Metraż', 'estate-office' ), $this->format_area_range( $criteria ) );
        $this->render_definition_row( __( 'Liczba pokoi', 'estate-office' ), $this->format_rooms_range( $criteria ) );
        $this->render_definition_row( __( 'Preferowana lokalizacja', 'estate-office' ), $criteria['location'] ?? '—' );
        echo '</dl>';
        echo '</div>';
    }

    private function render_preferences_card( array $criteria ): void {
        $building  = isset( $criteria['building'] ) && is_array( $criteria['building'] ) ? $criteria['building'] : [];
        $amenities = isset( $criteria['amenities'] ) && is_array( $criteria['amenities'] ) ? $criteria['amenities'] : [];
        $media     = isset( $criteria['media'] ) && is_array( $criteria['media'] ) ? $criteria['media'] : [];

        if ( empty( $building ) && empty( $amenities ) && empty( $media ) ) {
            return;
        }

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Preferencje dodatkowe', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        if ( ! empty( $building['needs_elevator'] ) ) {
            $this->render_definition_row( __( 'Winda', 'estate-office' ), __( 'Wymagana', 'estate-office' ) );
        }
        if ( ! empty( $amenities ) ) {
            $this->render_definition_row( __( 'Udogodnienia', 'estate-office' ), $this->implode_labels( $amenities, $this->amenities_map() ) );
        }
        if ( ! empty( $media ) ) {
            $media_map = [ 'gaz' => __( 'Gaz', 'estate-office' ) ];
            $this->render_definition_row( __( 'Media', 'estate-office' ), $this->implode_labels( $media, $media_map ) );
        }
        echo '</dl>';
        echo '</div>';
    }

    private function render_description_panel( string $description ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Opis poszukiwania', 'estate-office' ) . '</h2>';
        if ( $description ) {
            echo wp_kses_post( wpautop( $description ) );
        } else {
            echo '<p>' . esc_html__( 'Brak dodatkowego opisu.', 'estate-office' ) . '</p>';
        }
        echo '</div>';
    }

    private function render_relations_panel( int $contract_id ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Powiązania', 'estate-office' ) . '</h2>';

        if ( ! $contract_id ) {
            echo '<p>' . esc_html__( 'Brak powiązanej umowy.', 'estate-office' ) . '</p>';
            echo '</div>';
            return;
        }

        $contract = get_post( $contract_id );
        if ( ! $contract || 'estate_contract' !== $contract->post_type ) {
            echo '<p>' . esc_html__( 'Brak powiązanej umowy.', 'estate-office' ) . '</p>';
            echo '</div>';
            return;
        }

        $contract_number = get_post_meta( $contract_id, Keys::CONTRACT_NUMBER, true ) ?: $contract->post_title;
        $contract_url    = $this->get_profile_url( 'contract', $contract_id );

        echo '<p><a class="button button-secondary" href="' . esc_url( $contract_url ) . '">' . esc_html( sprintf( __( 'Umowa %s', 'estate-office' ), $contract_number ) ) . '</a></p>';

        $clients = get_post_meta( $contract_id, Keys::CONTRACT_CLIENTS, true );
        if ( is_array( $clients ) && ! empty( $clients ) ) {
            echo '<h3>' . esc_html__( 'Klienci', 'estate-office' ) . '</h3>';
            echo '<ul class="estate-office-list">';
            foreach ( $clients as $client_id ) {
                $client = get_post( (int) $client_id );
                if ( ! $client || 'estate_client' !== $client->post_type ) {
                    continue;
                }
                $client_url = $this->get_profile_url( 'client', $client->ID );
                $name       = $this->format_client_name( $client );
                echo '<li><a class="button button-secondary" href="' . esc_url( $client_url ) . '">' . esc_html( $name ) . '</a></li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    private function render_actions( WP_Post $search ): void {
        $back_url = add_query_arg(
            [
                'page' => 'estate-office-crm-panel',
                'tab'  => 'searches',
            ],
            admin_url( 'admin.php' )
        );

        echo '<div class="estate-office-profile__actions">';
        echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<a class="button button-primary" href="' . esc_url( get_edit_post_link( $search->ID, 'url' ) ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';

        if ( current_user_can( 'delete_post', $search->ID ) && current_user_can( 'manage_options' ) ) {
            echo '<form method="post" class="estate-office-inline-form">';
            wp_nonce_field( 'estate_office_delete_search_' . $search->ID, 'estate_office_delete_search_nonce' );
            echo '<input type="hidden" name="estate_office_search_action" value="delete" />';
            echo '<input type="hidden" name="search_id" value="' . esc_attr( $search->ID ) . '" />';
            echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Czy na pewno chcesz usunąć to poszukiwanie? Tego działania nie można cofnąć.', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń', 'estate-office' ) . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function maybe_handle_delete( WP_Post $search ): void {
        if ( empty( $_POST['estate_office_search_action'] ) || 'delete' !== $_POST['estate_office_search_action'] ) {
            return;
        }

        check_admin_referer( 'estate_office_delete_search_' . $search->ID, 'estate_office_delete_search_nonce' );

        if ( ! current_user_can( 'delete_post', $search->ID ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do usunięcia tego poszukiwania.', 'estate-office' ) );
        }

        wp_trash_post( $search->ID );

        $redirect = add_query_arg(
            [
                'page'    => 'estate-office-crm-panel',
                'tab'     => 'searches',
                'deleted' => 1,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    private function render_definition_row( string $label, string $value ): void {
        echo '<dt class="estate-office-definition__term">' . esc_html( $label ) . '</dt>';
        echo '<dd class="estate-office-definition__value">' . esc_html( $value ) . '</dd>';
    }

    private function implode_labels( array $values, array $map ): string {
        $labels = [];
        foreach ( $values as $value ) {
            if ( isset( $map[ $value ] ) ) {
                $labels[] = $map[ $value ];
            }
        }

        return implode( ', ', $labels );
    }

    private function amenities_map(): array {
        return [
            'winda'             => __( 'Winda', 'estate-office' ),
            'balkon'            => __( 'Balkon', 'estate-office' ),
            'parking'           => __( 'Miejsce parkingowe', 'estate-office' ),
            'ogrodek'           => __( 'Ogródek', 'estate-office' ),
        ];
    }

    private function format_area_range( array $criteria ): string {
        $min = isset( $criteria['area_min'] ) ? (float) $criteria['area_min'] : 0.0;
        $max = isset( $criteria['area_max'] ) ? (float) $criteria['area_max'] : 0.0;

        if ( $min <= 0 && $max <= 0 ) {
            return '—';
        }

        if ( $min > 0 && $max > 0 ) {
            return number_format_i18n( $min, 0 ) . ' - ' . number_format_i18n( $max, 0 ) . ' m²';
        }

        if ( $min > 0 ) {
            return sprintf( __( 'Od %s m²', 'estate-office' ), number_format_i18n( $min, 0 ) );
        }

        return sprintf( __( 'Do %s m²', 'estate-office' ), number_format_i18n( $max, 0 ) );
    }

    private function format_rooms_range( array $criteria ): string {
        $min = isset( $criteria['rooms_min'] ) ? (int) $criteria['rooms_min'] : 0;
        $max = isset( $criteria['rooms_max'] ) ? (int) $criteria['rooms_max'] : 0;

        if ( $min <= 0 && $max <= 0 ) {
            return '—';
        }

        if ( $min > 0 && $max > 0 ) {
            return $min . ' - ' . $max;
        }

        if ( $min > 0 ) {
            return sprintf( __( 'Od %s', 'estate-office' ), $min );
        }

        return sprintf( __( 'Do %s', 'estate-office' ), $max );
    }
}
