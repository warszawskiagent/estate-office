<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Post;
use WP_Query;

/**
 * Admin page summarising exported offers.
 */
class OffersPage extends AbstractPage {
    use DataFormattingTrait;

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do przeglądania strony.', 'estate-office' ) );
        }

        $this->render_header(
            __( 'Eksportowane oferty', 'estate-office' ),
            __( 'Lista nieruchomości oznaczonych do publikacji na stronie WWW.', 'estate-office' )
        );

        $this->render_notices();

        $properties = $this->get_exported_properties();

        if ( empty( $properties ) ) {
            echo '<p class="estate-office-admin__empty">' . esc_html__( 'Brak nieruchomości oznaczonych do eksportu.', 'estate-office' ) . '</p>';
            $this->render_footer();
            return;
        }

        $this->render_table( $properties );
        $this->render_footer();
    }

    private function render_notices(): void {
        if ( ! empty( $_GET['synced'] ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Synchronizacja oferty zakończona powodzeniem.', 'estate-office' ) . '</p></div>';
        }
    }

    private function get_exported_properties(): array {
        $query = new WP_Query(
            [
                'post_type'      => 'estate_property',
                'post_status'    => [ 'publish', 'draft', 'pending' ],
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => Keys::PROPERTY_BADGES,
                        'value'   => 'export_www',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        if ( ! $query->have_posts() ) {
            return [];
        }

        $items = [];

        foreach ( $query->posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            $property_id = $post->ID;
            $reference   = get_post_meta( $property_id, Keys::PROPERTY_REFERENCE, true );
            $address     = get_post_meta( $property_id, Keys::PROPERTY_ADDRESS, true );
            $badges      = get_post_meta( $property_id, Keys::PROPERTY_BADGES, true );
            $offer_id    = (int) get_post_meta( $property_id, Keys::PROPERTY_OFFER_POST, true );
            $offer       = $offer_id ? get_post( $offer_id ) : null;
            $new_badge   = (int) get_post_meta( $property_id, Keys::PROPERTY_NEW_BADGE_DATE, true );

            $items[] = [
                'id'              => $property_id,
                'post'            => $post,
                'reference'       => $reference ?: $post->post_title,
                'address'         => $this->format_address( $address ),
                'transaction'     => $this->format_transaction_type( get_post_meta( $property_id, Keys::PROPERTY_TRANSACTION, true ) ),
                'kind'            => $this->format_property_kind( get_post_meta( $property_id, Keys::PROPERTY_KIND, true ) ),
                'price'           => $this->format_money( get_post_meta( $property_id, Keys::PROPERTY_PRICE, true ) ),
                'agent'           => $this->format_agent( $post ),
                'badges'          => is_array( $badges ) ? $badges : [],
                'offer_id'        => $offer_id,
                'offer'           => $offer,
                'offer_url'       => $offer && 'publish' === $offer->post_status ? get_permalink( $offer ) : '',
                'new_badge_until' => $new_badge ? wp_date( get_option( 'date_format' ), $new_badge + WEEK_IN_SECONDS ) : '',
            ];
        }

        wp_reset_postdata();

        return $items;
    }

    private function render_table( array $properties ): void {
        echo '<table class="wp-list-table widefat fixed striped estate-office-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer oferty', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Rodzaj', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Cena', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Status eksportu', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Nowa oferta do', 'estate-office' ) . '</th>';
        echo '<th class="column-actions">' . esc_html__( 'Akcje', 'estate-office' ) . '</th>';
        echo '</tr></thead>';

        echo '<tbody>';

        foreach ( $properties as $item ) {
            $status_label = $this->determine_status_label( $item );
            $row_actions  = $this->build_actions( $item );

            echo '<tr>';
            echo '<td>' . esc_html( $item['reference'] ) . '</td>';
            echo '<td>' . esc_html( $item['address'] ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $item['transaction'] ) . '</td>';
            echo '<td>' . esc_html( $item['kind'] ) . '</td>';
            echo '<td>' . esc_html( $item['price'] ) . '</td>';
            echo '<td>' . esc_html( $item['agent'] ) . '</td>';
            echo '<td>' . esc_html( $status_label ) . '</td>';
            echo '<td>' . ( $item['new_badge_until'] ? esc_html( $item['new_badge_until'] ) : '—' ) . '</td>';
            echo '<td class="estate-office-table__actions">' . $row_actions . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    private function determine_status_label( array $item ): string {
        if ( empty( $item['offer_id'] ) ) {
            return __( 'Brak strony', 'estate-office' );
        }

        if ( ! $item['offer'] ) {
            return __( 'Strona usunięta', 'estate-office' );
        }

        switch ( $item['offer']->post_status ) {
            case 'publish':
                return __( 'Opublikowana', 'estate-office' );
            case 'draft':
                return __( 'Wersja robocza', 'estate-office' );
            case 'trash':
                return __( 'W koszu', 'estate-office' );
            default:
                return __( 'W przygotowaniu', 'estate-office' );
        }
    }

    private function build_actions( array $item ): string {
        $links = [];

        $profile_url = $this->get_profile_url( 'property', $item['id'] );

        if ( $profile_url ) {
            $links[] = '<a href="' . esc_url( $profile_url ) . '">' . esc_html__( 'Profil', 'estate-office' ) . '</a>';
        }

        $edit_link = get_edit_post_link( $item['id'] );
        if ( $edit_link ) {
            $links[] = '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';
        }

        $sync_link = $this->build_sync_link( $item['id'] );
        if ( $sync_link ) {
            $links[] = '<a href="' . esc_url( $sync_link ) . '">' . esc_html__( 'Synchronizuj', 'estate-office' ) . '</a>';
        }

        if ( $item['offer_url'] ) {
            $links[] = '<a href="' . esc_url( $item['offer_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Zobacz na stronie', 'estate-office' ) . '</a>';
        }

        return implode( ' | ', $links );
    }

    private function build_sync_link( int $property_id ): string {
        if ( $property_id <= 0 ) {
            return '';
        }

        return wp_nonce_url(
            add_query_arg(
                [
                    'action'      => 'estate_office_sync_offer',
                    'property_id' => $property_id,
                ],
                admin_url( 'admin-post.php' )
            ),
            'estate_office_sync_offer_' . $property_id
        );
    }
}
