<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Post;

class PropertyProfilePage extends AbstractPage {
    use DataFormattingTrait;

    public function render(): void {
        $property_id = isset( $_GET['property'] ) ? absint( $_GET['property'] ) : 0;
        $property    = $property_id ? get_post( $property_id ) : null;

        if ( ! $property || 'estate_property' !== $property->post_type ) {
            $this->render_header( __( 'Profil nieruchomości', 'estate-office' ) );
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Nie znaleziono wskazanej nieruchomości.', 'estate-office' ) . '</p></div>';
            $this->render_footer();
            return;
        }

        $this->maybe_handle_delete( $property );

        $reference = get_post_meta( $property_id, Keys::PROPERTY_REFERENCE, true );
        $subtitle  = $reference ? sprintf( __( 'Oferta %s', 'estate-office' ), $reference ) : __( 'Szczegóły nieruchomości.', 'estate-office' );

        $this->render_header( __( 'Profil nieruchomości', 'estate-office' ), $subtitle );

        settings_errors( 'estate-office-property-profile' );

        $transaction = get_post_meta( $property_id, Keys::PROPERTY_TRANSACTION, true );
        $kind        = get_post_meta( $property_id, Keys::PROPERTY_KIND, true );
        $address     = get_post_meta( $property_id, Keys::PROPERTY_ADDRESS, true );
        $building    = get_post_meta( $property_id, Keys::PROPERTY_BUILDING, true );
        $media       = get_post_meta( $property_id, Keys::PROPERTY_MEDIA, true );
        $amenities    = get_post_meta( $property_id, Keys::PROPERTY_AMENITIES, true );
        $equipment    = get_post_meta( $property_id, Keys::PROPERTY_EQUIPMENT, true );
        $extra        = get_post_meta( $property_id, Keys::PROPERTY_EXTRA_SPACES, true );
        $badges       = get_post_meta( $property_id, Keys::PROPERTY_BADGES, true );
        $description  = get_post_meta( $property_id, Keys::PROPERTY_DESCRIPTION, true );
        $video        = get_post_meta( $property_id, Keys::PROPERTY_VIDEO, true );
        $vr           = get_post_meta( $property_id, Keys::PROPERTY_VR, true );
        $gallery      = get_post_meta( $property_id, Keys::PROPERTY_GALLERY, true );
        $floorplan_2d = (int) get_post_meta( $property_id, Keys::PROPERTY_FLOORPLAN_2D, true );
        $floorplan_3d = (int) get_post_meta( $property_id, Keys::PROPERTY_FLOORPLAN_3D, true );
        $contract_id  = (int) get_post_meta( $property_id, Keys::PROPERTY_CONTRACT, true );

        echo '<div class="estate-office-profile">';
        echo '<div class="estate-office-profile__main">';
        $this->render_summary_card( $property, $property_id, $transaction, $kind, $address );
        $this->render_building_card( $building );
        $this->render_media_card( $media, $amenities, $equipment );
        $this->render_extra_spaces_card( $extra );
        $this->render_badges_card( $badges );
        $this->render_gallery_card( $gallery, $floorplan_2d, $floorplan_3d );
        echo '</div>';

        echo '<div class="estate-office-profile__sidebar">';
        $this->render_description_panel( $description, $video, $vr );
        $this->render_relations_panel( $contract_id );
        echo '</div>';
        echo '</div>';

        $this->render_actions( $property );
        $this->render_footer();
    }

    private function render_summary_card( WP_Post $property, int $property_id, string $transaction, string $kind, $address ): void {
        $price      = get_post_meta( $property_id, Keys::PROPERTY_PRICE, true );
        $price_m2   = get_post_meta( $property_id, Keys::PROPERTY_PRICE_PER_M2, true );
        $admin_fee  = get_post_meta( $property_id, Keys::PROPERTY_ADMIN_FEE, true );
        $area       = get_post_meta( $property_id, Keys::PROPERTY_AREA, true );
        $year       = (int) get_post_meta( $property_id, Keys::PROPERTY_YEAR_BUILT, true );
        $floor      = (int) get_post_meta( $property_id, Keys::PROPERTY_FLOOR, true );
        $floors     = (int) get_post_meta( $property_id, Keys::PROPERTY_FLOORS, true );
        $rooms      = (int) get_post_meta( $property_id, Keys::PROPERTY_ROOMS, true );
        $bedrooms   = (int) get_post_meta( $property_id, Keys::PROPERTY_BEDROOMS, true );
        $bathrooms  = (int) get_post_meta( $property_id, Keys::PROPERTY_BATHROOMS, true );
        $toilets    = (int) get_post_meta( $property_id, Keys::PROPERTY_TOILETS, true );
        $legal      = get_post_meta( $property_id, Keys::PROPERTY_LEGAL_STATUS, true );
        $plot       = get_post_meta( $property_id, Keys::PROPERTY_PLOT_SHAPE, true );

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Informacje ogólne', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        $this->render_definition_row( __( 'Typ transakcji', 'estate-office' ), $this->format_transaction_type( $transaction ) );
        $this->render_definition_row( __( 'Rodzaj nieruchomości', 'estate-office' ), $this->format_property_kind( $kind ) );
        $this->render_definition_row( __( 'Opiekun', 'estate-office' ), $this->format_agent( $property ) );
        $this->render_definition_row( __( 'Adres', 'estate-office' ), $this->format_address( $address ) ?: '—' );
        $this->render_definition_row( __( 'Stan prawny', 'estate-office' ), $this->map_legal_status( $legal ) );
        $this->render_definition_row( __( 'Cena', 'estate-office' ), $this->format_property_price( $price, $transaction ) );
        $this->render_definition_row( __( 'Cena za m²', 'estate-office' ), $this->format_money( $price_m2 ) );
        $this->render_definition_row( __( 'Czynsz administracyjny', 'estate-office' ), $this->format_money( $admin_fee ) );
        $this->render_definition_row( __( 'Powierzchnia', 'estate-office' ), $this->format_area( $area ) );
        if ( $year > 0 ) {
            $this->render_definition_row( __( 'Rok budowy', 'estate-office' ), (string) $year );
        }
        if ( $floor > 0 ) {
            $this->render_definition_row( __( 'Piętro', 'estate-office' ), (string) $floor );
        }
        if ( $floors > 0 ) {
            $this->render_definition_row( __( 'Liczba pięter', 'estate-office' ), (string) $floors );
        }
        if ( $rooms > 0 ) {
            $this->render_definition_row( __( 'Liczba pokoi', 'estate-office' ), (string) $rooms );
        }
        if ( $bedrooms > 0 ) {
            $this->render_definition_row( __( 'Liczba sypialni', 'estate-office' ), (string) $bedrooms );
        }
        if ( $bathrooms > 0 ) {
            $this->render_definition_row( __( 'Liczba łazienek', 'estate-office' ), (string) $bathrooms );
        }
        if ( $toilets > 0 ) {
            $this->render_definition_row( __( 'Liczba toalet', 'estate-office' ), (string) $toilets );
        }

        if ( 'dzialka' === $kind && is_array( $plot ) ) {
            $this->render_definition_row( __( 'Kształt działki', 'estate-office' ), $this->map_plot_shape( $plot['shape'] ?? '' ) );
            if ( ! empty( $plot['length'] ) ) {
                $this->render_definition_row( __( 'Długość działki', 'estate-office' ), number_format_i18n( (float) $plot['length'], 2 ) . ' m' );
            }
            if ( ! empty( $plot['width'] ) ) {
                $this->render_definition_row( __( 'Szerokość działki', 'estate-office' ), number_format_i18n( (float) $plot['width'], 2 ) . ' m' );
            }
            if ( ! empty( $plot['description'] ) ) {
                $this->render_definition_row( __( 'Opis działki', 'estate-office' ), $plot['description'] );
            }
        }

        echo '</dl>';
        echo '</div>';
    }

    private function render_building_card( $building ): void {
        if ( ! is_array( $building ) || empty( array_filter( $building ) ) ) {
            return;
        }

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Szczegóły budynku', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';

        if ( ! empty( $building['finish'] ) ) {
            $this->render_definition_row( __( 'Stan wykończenia', 'estate-office' ), $this->map_finish( $building['finish'] ) );
        }
        if ( ! empty( $building['exposure'] ) && is_array( $building['exposure'] ) ) {
            $this->render_definition_row( __( 'Ekspozycja', 'estate-office' ), $this->implode_labels( $building['exposure'], [
                'polnoc'  => __( 'Północ', 'estate-office' ),
                'poludnie'=> __( 'Południe', 'estate-office' ),
                'wschod'  => __( 'Wschód', 'estate-office' ),
                'zachod'  => __( 'Zachód', 'estate-office' ),
            ] ) );
        }
        if ( ! empty( $building['view'] ) && is_array( $building['view'] ) ) {
            $this->render_definition_row( __( 'Widok', 'estate-office' ), $this->implode_labels( $building['view'], [
                'park'  => __( 'Park', 'estate-office' ),
                'miasto'=> __( 'Miasto', 'estate-office' ),
                'inne'  => __( 'Inne', 'estate-office' ),
            ] ) );
        }
        if ( isset( $building['attic'] ) ) {
            $this->render_definition_row( __( 'Poddasze', 'estate-office' ), $building['attic'] ? __( 'Tak', 'estate-office' ) : __( 'Nie', 'estate-office' ) );
        }
        if ( isset( $building['multi_level'] ) ) {
            $this->render_definition_row( __( 'Wielopoziomowe', 'estate-office' ), $building['multi_level'] ? __( 'Tak', 'estate-office' ) : __( 'Nie', 'estate-office' ) );
        }
        if ( ! empty( $building['layout'] ) && is_array( $building['layout'] ) ) {
            $this->render_definition_row( __( 'Rozkład', 'estate-office' ), $this->implode_labels( $building['layout'], [
                'dwustronne' => __( 'Dwustronne', 'estate-office' ),
                'rozkladowe' => __( 'Rozkładowe', 'estate-office' ),
            ] ) );
        }
        if ( ! empty( $building['kitchen'] ) ) {
            $this->render_definition_row( __( 'Kuchnia', 'estate-office' ), $this->map_kitchen( $building['kitchen'] ) );
        }
        if ( ! empty( $building['parking']['has'] ) ) {
            $parking_types = [];
            if ( ! empty( $building['parking']['types'] ) && is_array( $building['parking']['types'] ) ) {
                $parking_types = $this->implode_labels( $building['parking']['types'], [
                    'naziemne' => __( 'Najemne', 'estate-office' ),
                    'podziemne'=> __( 'Podziemne', 'estate-office' ),
                    'garaz'    => __( 'Garaż wolnostojący/przylegający', 'estate-office' ),
                ], false );
            }
            $label = __( 'Tak', 'estate-office' );
            if ( $parking_types ) {
                $label .= ' – ' . $parking_types;
            }
            $this->render_definition_row( __( 'Miejsce parkingowe', 'estate-office' ), $label );
        }

        echo '</dl>';
        echo '</div>';
    }

    private function render_media_card( $media, $amenities, $equipment ): void {
        if ( ! is_array( $media ) ) {
            $media = [];
        }
        if ( ! is_array( $amenities ) ) {
            $amenities = [];
        }
        if ( ! is_array( $equipment ) ) {
            $equipment = [];
        }

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Media i udogodnienia', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-definition">';
        if ( ! empty( $media['heating'] ) ) {
            $this->render_definition_row( __( 'Ogrzewanie', 'estate-office' ), $this->map_heating( $media['heating'] ) );
        }
        if ( ! empty( $media['water'] ) ) {
            $this->render_definition_row( __( 'Woda', 'estate-office' ), $this->map_water( $media['water'] ) );
        }
        if ( ! empty( $media['sewage'] ) ) {
            $this->render_definition_row( __( 'Kanalizacja', 'estate-office' ), $this->map_sewage( $media['sewage'] ) );
        }
        if ( isset( $media['gas'] ) ) {
            $this->render_definition_row( __( 'Gaz', 'estate-office' ), $media['gas'] ? __( 'Tak', 'estate-office' ) : __( 'Nie', 'estate-office' ) );
        }
        if ( ! empty( $amenities ) ) {
            $this->render_definition_row( __( 'Udogodnienia', 'estate-office' ), $this->implode_labels( $amenities, $this->amenities_map() ) );
        }
        if ( ! empty( $equipment ) ) {
            $this->render_definition_row( __( 'Wyposażenie', 'estate-office' ), $this->implode_labels( $equipment, $this->equipment_map() ) );
        }
        echo '</dl>';
        echo '</div>';
    }

    private function render_extra_spaces_card( $extra ): void {
        if ( ! is_array( $extra ) || empty( $extra ) ) {
            return;
        }

        $rows = [];
        foreach ( $extra as $key => $data ) {
            if ( empty( $data['enabled'] ) ) {
                continue;
            }
            $label = $this->extra_spaces_map()[ $key ] ?? $key;
            $details = [];
            if ( isset( $data['count'] ) && $data['count'] ) {
                $details[] = sprintf( __( '%s szt.', 'estate-office' ), (int) $data['count'] );
            }
            if ( isset( $data['area'] ) && $data['area'] ) {
                $details[] = number_format_i18n( (float) $data['area'], 2 ) . ' m²';
            }
            $rows[] = $label . ( $details ? ' (' . implode( ', ', $details ) . ')' : '' );
        }

        if ( empty( $rows ) ) {
            return;
        }

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Powierzchnie dodatkowe', 'estate-office' ) . '</h2>';
        echo '<ul class="estate-office-list">';
        foreach ( $rows as $row ) {
            echo '<li>' . esc_html( $row ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private function render_badges_card( $badges ): void {
        if ( ! is_array( $badges ) || empty( $badges ) ) {
            return;
        }

        $labels = [];
        $map    = $this->badge_map();
        foreach ( $badges as $badge ) {
            if ( isset( $map[ $badge ] ) ) {
                $labels[] = $map[ $badge ];
            }
        }

        if ( empty( $labels ) ) {
            return;
        }

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Znaczniki', 'estate-office' ) . '</h2>';
        echo '<ul class="estate-office-badges">';
        foreach ( $labels as $label ) {
            echo '<li class="estate-office-badge">' . esc_html( $label ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private function render_gallery_card( $gallery, int $floorplan_2d, int $floorplan_3d ): void {
        $gallery_ids = [];
        if ( is_array( $gallery ) ) {
            foreach ( $gallery as $value ) {
                $id = (int) $value;
                if ( $id > 0 ) {
                    $gallery_ids[] = $id;
                }
            }
        }

        $links = [];

        $floorplan_link = $this->format_floorplan_link( $floorplan_2d, __( 'Rzut 2D', 'estate-office' ) );
        if ( $floorplan_link ) {
            $links[] = $floorplan_link;
        }

        $floorplan_link = $this->format_floorplan_link( $floorplan_3d, __( 'Rzut 3D', 'estate-office' ) );
        if ( $floorplan_link ) {
            $links[] = $floorplan_link;
        }

        if ( empty( $gallery_ids ) && empty( $links ) ) {
            return;
        }

        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Galeria i rzuty', 'estate-office' ) . '</h2>';

        if ( ! empty( $gallery_ids ) ) {
            echo '<div class="estate-office-property-gallery">';
            foreach ( $gallery_ids as $attachment_id ) {
                $image = wp_get_attachment_image( $attachment_id, 'medium_large' );
                if ( ! $image ) {
                    continue;
                }
                echo '<figure class="estate-office-property-gallery__item">' . $image . '</figure>';
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Brak dodanych zdjęć.', 'estate-office' ) . '</p>';
        }

        if ( ! empty( $links ) ) {
            echo '<ul class="estate-office-list estate-office-property-gallery__floorplans">';
            foreach ( $links as $link ) {
                echo '<li>' . $link . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    private function render_description_panel( $description, $video, $vr ): void {
        echo '<div class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Opis nieruchomości', 'estate-office' ) . '</h2>';
        if ( $description ) {
            echo wp_kses_post( wpautop( $description ) );
        } else {
            echo '<p>' . esc_html__( 'Brak wprowadzonego opisu.', 'estate-office' ) . '</p>';
        }
        echo '</div>';

        if ( $video || $vr ) {
            echo '<div class="estate-office-card">';
            echo '<h2>' . esc_html__( 'Materiały dodatkowe', 'estate-office' ) . '</h2>';
            echo '<ul class="estate-office-list">';
            if ( $video ) {
                echo '<li><a href="' . esc_url( $video ) . '" target="_blank" rel="noopener">' . esc_html__( 'Zobacz film', 'estate-office' ) . '</a></li>';
            }
            if ( $vr ) {
                echo '<li><a href="' . esc_url( $vr ) . '" target="_blank" rel="noopener">' . esc_html__( 'Wirtualny spacer', 'estate-office' ) . '</a></li>';
            }
            echo '</ul>';
            echo '</div>';
        }
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
                $client_name = $this->format_client_name( $client );
                $client_url  = $this->get_profile_url( 'client', $client->ID );
                echo '<li><a class="button button-secondary" href="' . esc_url( $client_url ) . '">' . esc_html( $client_name ) . '</a></li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    private function render_actions( WP_Post $property ): void {
        $back_url = add_query_arg(
            [
                'page' => 'estate-office-crm-panel',
                'tab'  => 'properties',
            ],
            admin_url( 'admin.php' )
        );

        echo '<div class="estate-office-profile__actions">';
        echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<a class="button button-primary" href="' . esc_url( get_edit_post_link( $property->ID, 'url' ) ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';

        if ( current_user_can( 'delete_post', $property->ID ) && current_user_can( 'manage_options' ) ) {
            echo '<form method="post" class="estate-office-inline-form">';
            wp_nonce_field( 'estate_office_delete_property_' . $property->ID, 'estate_office_delete_property_nonce' );
            echo '<input type="hidden" name="estate_office_property_action" value="delete" />';
            echo '<input type="hidden" name="property_id" value="' . esc_attr( $property->ID ) . '" />';
            echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Czy na pewno chcesz usunąć tę nieruchomość? Tego działania nie można cofnąć.', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń', 'estate-office' ) . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function maybe_handle_delete( WP_Post $property ): void {
        if ( empty( $_POST['estate_office_property_action'] ) || 'delete' !== $_POST['estate_office_property_action'] ) {
            return;
        }

        check_admin_referer( 'estate_office_delete_property_' . $property->ID, 'estate_office_delete_property_nonce' );

        if ( ! current_user_can( 'delete_post', $property->ID ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do usunięcia tej nieruchomości.', 'estate-office' ) );
        }

        wp_trash_post( $property->ID );

        $redirect = add_query_arg(
            [
                'page'    => 'estate-office-crm-panel',
                'tab'     => 'properties',
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

    private function map_legal_status( string $status ): string {
        $map = [
            'wlasnosc'      => __( 'Własność', 'estate-office' ),
            'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
            'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
            'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
            'inne'          => __( 'Inne', 'estate-office' ),
        ];

        if ( isset( $map[ $status ] ) ) {
            return $map[ $status ];
        }

        $fallback = str_replace( '_', ' ', $status );
        return $fallback ? ucfirst( $fallback ) : $status;
    }

    private function map_plot_shape( string $shape ): string {
        $map = [
            'regularny'   => __( 'Regularny', 'estate-office' ),
            'nieregularny'=> __( 'Nieregularny', 'estate-office' ),
        ];

        if ( isset( $map[ $shape ] ) ) {
            return $map[ $shape ];
        }

        $fallback = str_replace( '_', ' ', $shape );
        return $fallback ? ucfirst( $fallback ) : $shape;
    }

    private function map_finish( string $finish ): string {
        $map = [
            'do_wykonczenia'  => __( 'Do wykończenia', 'estate-office' ),
            'do_zamieszkania' => __( 'Do zamieszkania', 'estate-office' ),
            'pod_klucz'       => __( 'Pod klucz', 'estate-office' ),
        ];

        if ( isset( $map[ $finish ] ) ) {
            return $map[ $finish ];
        }

        $fallback = str_replace( '_', ' ', $finish );
        return $fallback ? ucfirst( $fallback ) : $finish;
    }

    private function map_kitchen( string $kitchen ): string {
        $map = [
            'aneks'      => __( 'Aneks', 'estate-office' ),
            'oddzielna'  => __( 'Oddzielna', 'estate-office' ),
            'z_salonem'  => __( 'Z salonem', 'estate-office' ),
        ];

        if ( isset( $map[ $kitchen ] ) ) {
            return $map[ $kitchen ];
        }

        $fallback = str_replace( '_', ' ', $kitchen );
        return $fallback ? ucfirst( $fallback ) : $kitchen;
    }

    private function map_heating( string $heating ): string {
        $map = [
            'miejskie' => __( 'Miejskie', 'estate-office' ),
            'gazowe'   => __( 'Gazowe', 'estate-office' ),
            'inne'     => __( 'Inne', 'estate-office' ),
        ];

        if ( isset( $map[ $heating ] ) ) {
            return $map[ $heating ];
        }

        $fallback = str_replace( '_', ' ', $heating );
        return $fallback ? ucfirst( $fallback ) : $heating;
    }

    private function map_water( string $water ): string {
        $map = [
            'miejskie' => __( 'Miejskie', 'estate-office' ),
            'studnia'  => __( 'Studnia', 'estate-office' ),
        ];

        if ( isset( $map[ $water ] ) ) {
            return $map[ $water ];
        }

        $fallback = str_replace( '_', ' ', $water );
        return $fallback ? ucfirst( $fallback ) : $water;
    }

    private function map_sewage( string $sewage ): string {
        $map = [
            'miejskie' => __( 'Miejskie', 'estate-office' ),
            'szambo'   => __( 'Szambo', 'estate-office' ),
        ];

        if ( isset( $map[ $sewage ] ) ) {
            return $map[ $sewage ];
        }

        $fallback = str_replace( '_', ' ', $sewage );
        return $fallback ? ucfirst( $fallback ) : $sewage;
    }

    private function amenities_map(): array {
        return [
            'winda'              => __( 'Winda', 'estate-office' ),
            'umeblowanie_pelne'  => __( 'Umeblowanie', 'estate-office' ),
            'klimatyzacja'       => __( 'Klimatyzacja', 'estate-office' ),
            'monitoring'         => __( 'Monitoring/Ochrona', 'estate-office' ),
            'recepcja'           => __( 'Recepcja', 'estate-office' ),
            'teren_zamkniety'    => __( 'Teren zamknięty', 'estate-office' ),
            'domofon'            => __( 'Domofon', 'estate-office' ),
        ];
    }

    private function equipment_map(): array {
        return [
            'pralka'    => __( 'Pralka', 'estate-office' ),
            'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
            'lodowka'   => __( 'Lodówka', 'estate-office' ),
            'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
            'piekarnik' => __( 'Piekarnik', 'estate-office' ),
            'telewizor' => __( 'Telewizor', 'estate-office' ),
            'mikrofala' => __( 'Mikrofala', 'estate-office' ),
        ];
    }

    private function extra_spaces_map(): array {
        return [
            'balcony' => __( 'Balkon', 'estate-office' ),
            'taras'   => __( 'Taras', 'estate-office' ),
            'piwnica' => __( 'Piwnica', 'estate-office' ),
            'komorka' => __( 'Komórka lokatorska', 'estate-office' ),
            'ogrodek' => __( 'Ogródek', 'estate-office' ),
        ];
    }

    private function badge_map(): array {
        return [
            'nowa_oferta'   => __( 'Nowa oferta', 'estate-office' ),
            'wylacznosc'    => __( 'Wyłączność', 'estate-office' ),
            'sprzedane'     => __( 'Sprzedane', 'estate-office' ),
            'wynajete'      => __( 'Wynajęte', 'estate-office' ),
            'nowa_cena'     => __( 'Nowa cena', 'estate-office' ),
            'bez_prowizji'  => __( 'Bez prowizji', 'estate-office' ),
            'mls'           => __( 'Oferta MLS', 'estate-office' ),
            'premium'       => __( 'Premium', 'estate-office' ),
            'export_www'    => __( 'Eksport na WWW', 'estate-office' ),
            'export_portals'=> __( 'Eksport na Portale', 'estate-office' ),
        ];
    }

    private function implode_labels( array $values, array $map, bool $escape = true ): string {
        $labels = [];
        foreach ( $values as $value ) {
            if ( isset( $map[ $value ] ) ) {
                $labels[] = $map[ $value ];
            }
        }

        $joined = implode( ', ', $labels );
        return $escape ? esc_html( $joined ) : $joined;
    }

    private function format_property_price( $price, string $transaction ): string {
        $formatted = $this->format_money( $price );
        if ( in_array( strtolower( $transaction ), [ 'wynajem', 'najem' ], true ) && $formatted !== '—' ) {
            $formatted .= ' / ' . __( 'miesięcznie', 'estate-office' );
        }
        return $formatted;
    }

    private function format_floorplan_link( int $attachment_id, string $label ): string {
        if ( $attachment_id <= 0 ) {
            return '';
        }

        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) {
            return '';
        }

        $title     = get_the_title( $attachment_id );
        $link_text = $title ? sprintf( '%s (%s)', $label, $title ) : $label;

        return sprintf(
            '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
            esc_url( $url ),
            esc_html( $link_text )
        );
    }
}
