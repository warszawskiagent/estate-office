<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Post;
use WP_Query;

/**
 * Displays the CRM lists for contracts, properties, clients and searches.
 */
class CRMPage extends AbstractPage {
    use DataFormattingTrait;
    private const TABS = [
        'properties' => 'Nieruchomości',
        'contracts'  => 'Umowy',
        'clients'    => 'Klienci',
        'searches'   => 'Poszukiwania',
    ];

    public function render(): void {
        $current_tab = $this->get_current_tab();

        $this->render_header(
            __( 'Panel CRM', 'estate-office' ),
            __( 'Przeglądaj oferty, umowy, klientów i poszukiwania z poziomu jednej konsoli.', 'estate-office' )
        );

        $this->render_actions_bar();
        $this->render_tabs( $current_tab );
        $this->render_search_form( $current_tab );

        switch ( $current_tab ) {
            case 'properties':
                $this->render_properties_table();
                break;
            case 'contracts':
                $this->render_contracts_table();
                break;
            case 'clients':
                $this->render_clients_table();
                break;
            case 'searches':
                $this->render_searches_table();
                break;
        }

        $this->render_footer();
    }

    private function render_actions_bar(): void {
        $wizard_url = admin_url( 'admin.php?page=estate-office-contract-wizard' );
        echo '<div class="estate-office-crm__actions">';
        echo '<a class="button button-primary" href="' . esc_url( $wizard_url ) . '">' . esc_html__( 'Dodaj nową umowę', 'estate-office' ) . '</a>';
        echo '</div>';
    }

    private function render_tabs( string $current_tab ): void {
        echo '<h2 class="nav-tab-wrapper">';

        foreach ( self::TABS as $slug => $label ) {
            $url  = add_query_arg(
                [
                    'page' => 'estate-office-crm-panel',
                    'tab'  => $slug,
                ],
                admin_url( 'admin.php' )
            );
            $class = 'nav-tab';

            if ( $slug === $current_tab ) {
                $class .= ' nav-tab-active';
            }

            echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
        }

        echo '</h2>';
    }

    private function render_search_form( string $current_tab ): void {
        $term = isset( $_GET['crm_search'] ) ? sanitize_text_field( wp_unslash( $_GET['crm_search'] ) ) : '';

        echo '<form method="get" class="estate-office-crm__search">';
        echo '<input type="hidden" name="page" value="estate-office-crm-panel" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr( $current_tab ) . '" />';
        echo '<label class="screen-reader-text" for="estate-office-crm-search">' . esc_html__( 'Wyszukaj rekordy', 'estate-office' ) . '</label>';
        echo '<input type="search" id="estate-office-crm-search" name="crm_search" value="' . esc_attr( $term ) . '" placeholder="' . esc_attr__( 'Wyszukaj po wszystkich kolumnach…', 'estate-office' ) . '" />';
        echo '<button type="submit" class="button">' . esc_html__( 'Szukaj', 'estate-office' ) . '</button>';
        echo '</form>';
    }

    private function render_properties_table(): void {
        $query = new WP_Query( $this->build_query_args( 'estate_property', [
            Keys::PROPERTY_REFERENCE,
            Keys::PROPERTY_ADDRESS,
            Keys::PROPERTY_PRICE,
            Keys::PROPERTY_PRICE_PER_M2,
            Keys::PROPERTY_AREA,
            Keys::PROPERTY_ROOMS,
        ] ) );

        echo '<table class="widefat fixed striped estate-office-crm-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer oferty', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Cena', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Cena za m²', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Metraż', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Liczba pokoi', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( ! $query->have_posts() ) {
            echo '<tr><td colspan="7">' . esc_html__( 'Brak nieruchomości spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query->posts as $post ) {
                $reference = get_post_meta( $post->ID, Keys::PROPERTY_REFERENCE, true );
                $address   = $this->format_address( get_post_meta( $post->ID, Keys::PROPERTY_ADDRESS, true ) );
                $price     = $this->format_money( get_post_meta( $post->ID, Keys::PROPERTY_PRICE, true ) );
                $price_m2  = $this->format_money( get_post_meta( $post->ID, Keys::PROPERTY_PRICE_PER_M2, true ) );
                $area      = $this->format_area( get_post_meta( $post->ID, Keys::PROPERTY_AREA, true ) );
                $rooms     = get_post_meta( $post->ID, Keys::PROPERTY_ROOMS, true );
                $agent     = $this->format_agent( $post );
                $link      = $this->get_profile_url( 'property', $post->ID );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $reference ?: $post->post_title ) . '</a></td>';
                echo '<td>' . esc_html( $address ) . '</td>';
                echo '<td>' . esc_html( $price ) . '</td>';
                echo '<td>' . esc_html( $price_m2 ) . '</td>';
                echo '<td>' . esc_html( $area ) . '</td>';
                echo '<td>' . esc_html( $rooms ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $agent ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function render_contracts_table(): void {
        $query = new WP_Query( $this->build_query_args( 'estate_contract', [
            Keys::CONTRACT_NUMBER,
            Keys::CONTRACT_TYPE,
            Keys::CONTRACT_START_DATE,
            Keys::CONTRACT_END_DATE,
            Keys::CONTRACT_STAGE,
        ] ) );

        echo '<table class="widefat fixed striped estate-office-crm-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer umowy', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Data zawarcia', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Data zakończenia', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Aktualny etap', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( ! $query->have_posts() ) {
            echo '<tr><td colspan="8">' . esc_html__( 'Brak umów spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query->posts as $post ) {
                $number    = get_post_meta( $post->ID, Keys::CONTRACT_NUMBER, true );
                $type      = $this->format_transaction_type( get_post_meta( $post->ID, Keys::CONTRACT_TYPE, true ) );
                $property  = (int) get_post_meta( $post->ID, Keys::CONTRACT_PROPERTY, true );
                $search_id = (int) get_post_meta( $post->ID, Keys::CONTRACT_SEARCH, true );
                $address   = '';
                $kind      = '';

                if ( $property ) {
                    $address = $this->format_address( get_post_meta( $property, Keys::PROPERTY_ADDRESS, true ) );
                    $kind    = $this->format_property_kind( get_post_meta( $property, Keys::PROPERTY_KIND, true ) );
                } elseif ( $search_id ) {
                    $criteria = get_post_meta( $search_id, Keys::SEARCH_CRITERIA, true );
                    if ( is_array( $criteria ) ) {
                        $address = $criteria['location'] ?? '';
                        $kind    = $this->format_property_kind( $criteria['property_kind'] ?? '' );
                    }
                }

                $start    = $this->format_date( get_post_meta( $post->ID, Keys::CONTRACT_START_DATE, true ) );
                $end      = $this->format_date( get_post_meta( $post->ID, Keys::CONTRACT_END_DATE, true ) );
                $stage    = $this->format_stage( get_post_meta( $post->ID, Keys::CONTRACT_STAGE, true ) );
                $agent    = $this->format_agent( $post );
                $link     = $this->get_profile_url( 'contract', $post->ID );
                $related_link = '';

                if ( $property ) {
                    $related_link = $this->get_profile_url( 'property', $property );
                } elseif ( $search_id ) {
                    $related_link = $this->get_profile_url( 'search', $search_id );
                }

                echo '<tr>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $number ?: $post->post_title ) . '</a></td>';
                echo '<td>' . esc_html( $type ) . '</td>';
                echo '<td>' . esc_html( $kind ?: '—' ) . '</td>';
                echo '<td>';
                if ( $related_link ) {
                    echo '<a href="' . esc_url( $related_link ) . '">' . esc_html( $address ?: '—' ) . '</a>';
                } else {
                    echo esc_html( $address ?: '—' );
                }
                echo '</td>';
                echo '<td>' . esc_html( $start ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $end ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $stage ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $agent ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function render_clients_table(): void {
        $query = new WP_Query( $this->build_query_args( 'estate_client', [
            Keys::CLIENT_FIRST_NAME,
            Keys::CLIENT_LAST_NAME,
            Keys::CLIENT_COMPANY_NAME,
            Keys::CLIENT_PHONE,
            Keys::CLIENT_EMAIL,
            Keys::CLIENT_ADDRESS_MAIN,
        ] ) );

        echo '<table class="widefat fixed striped estate-office-crm-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Imię i nazwisko/Nazwa', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Telefon', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'E-mail', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( ! $query->have_posts() ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Brak klientów spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query->posts as $post ) {
                $name    = $this->format_client_name( $post );
                $address = $this->format_address( get_post_meta( $post->ID, Keys::CLIENT_ADDRESS_MAIN, true ) );
                $phone   = get_post_meta( $post->ID, Keys::CLIENT_PHONE, true );
                $email   = get_post_meta( $post->ID, Keys::CLIENT_EMAIL, true );
                $agent   = $this->infer_client_agent( $post );
                $link    = $this->get_profile_url( 'client', $post->ID );
                $address_link = $this->get_client_property_link( $post );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></td>';
                echo '<td>';
                if ( $address_link ) {
                    echo '<a href="' . esc_url( $address_link ) . '">' . esc_html( $address ?: '—' ) . '</a>';
                } else {
                    echo esc_html( $address ?: '—' );
                }
                echo '</td>';
                echo '<td>' . esc_html( $phone ?: '—' ) . '</td>';
                echo '<td>';
                if ( $email ) {
                    echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                } else {
                    echo esc_html__( '—', 'estate-office' );
                }
                echo '</td>';
                echo '<td>' . esc_html( $agent ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function render_searches_table(): void {
        $query = new WP_Query( $this->build_query_args( 'estate_search', [ Keys::SEARCH_CRITERIA ] ) );

        echo '<table class="widefat fixed striped estate-office-crm-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer poszukiwania', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Budżet', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Lokalizacja', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( ! $query->have_posts() ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Brak zapisanych poszukiwań.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query->posts as $post ) {
                $criteria = get_post_meta( $post->ID, Keys::SEARCH_CRITERIA, true );
                $link     = $this->get_profile_url( 'search', $post->ID );
                $kind     = $this->format_property_kind( $criteria['property_kind'] ?? '' );
                $budget   = $this->format_budget( $criteria );
                $location = $criteria['location'] ?? '—';
                $type     = $this->format_transaction_type( $criteria['transaction_type'] ?? '' );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $criteria['reference'] ?? $post->post_title ) . '</a></td>';
                echo '<td>' . esc_html( $kind ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $budget ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $location ) . '</td>';
                echo '<td>' . esc_html( $type ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function get_current_tab(): string {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'properties';
        return array_key_exists( $tab, self::TABS ) ? $tab : 'properties';
    }

    private function build_query_args( string $post_type, array $meta_keys ): array {
        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => 20,
            'post_status'    => 'publish',
        ];

        $search = isset( $_GET['crm_search'] ) ? sanitize_text_field( wp_unslash( $_GET['crm_search'] ) ) : '';

        if ( $search ) {
            $args['s'] = $search;
            $meta_query = [ 'relation' => 'OR' ];

            foreach ( $meta_keys as $meta_key ) {
                $meta_query[] = [
                    'key'     => $meta_key,
                    'value'   => $search,
                    'compare' => 'LIKE',
                ];
            }

            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    private function get_client_property_link( WP_Post $client ): string {
        $contracts = get_post_meta( $client->ID, Keys::CLIENT_CONTRACTS, true );

        if ( ! is_array( $contracts ) ) {
            return '';
        }

        foreach ( $contracts as $contract_id ) {
            $contract_id = (int) $contract_id;

            if ( ! $contract_id ) {
                continue;
            }

            $property = (int) get_post_meta( $contract_id, Keys::CONTRACT_PROPERTY, true );
            if ( $property ) {
                return $this->get_profile_url( 'property', $property );
            }

            $search = (int) get_post_meta( $contract_id, Keys::CONTRACT_SEARCH, true );
            if ( $search ) {
                return $this->get_profile_url( 'search', $search );
            }
        }

        return '';
    }
}
