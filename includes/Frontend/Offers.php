<?php
namespace EstateOffice\Frontend;

use EstateOffice\Badges\Manager as BadgesManager;
use EstateOffice\Meta\Keys;
use WP_Post;
use WP_Query;

/**
 * Handles public offer pages generated from properties.
 */
class Offers {
    private const SALE_PAGE_SLUG = 'oferty-na-sprzedaz';
    private const RENT_PAGE_SLUG = 'oferty-na-wynajem';

    private BadgesManager $badges;

    private Agents $agents;

    public function __construct( BadgesManager $badges, Agents $agents ) {
        $this->badges = $badges;
        $this->agents = $agents;
    }

    /**
     * Registers the custom post type and taxonomies for offers.
     */
    public function register_content_types(): void {
        register_post_type(
            'estate_offer',
            [
                'label'               => __( 'Oferty', 'estate-office' ),
                'labels'              => [
                    'name'          => __( 'Oferty nieruchomości', 'estate-office' ),
                    'singular_name' => __( 'Oferta nieruchomości', 'estate-office' ),
                ],
                'public'              => true,
                'has_archive'         => false,
                'rewrite'             => [ 'slug' => 'oferta' ],
                'show_in_rest'        => true,
                'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
                'show_in_menu'        => false,
                'capability_type'     => 'page',
                'map_meta_cap'        => true,
            ]
        );

        $taxonomies = [
            'estate_offer_transaction' => __( 'Typ transakcji', 'estate-office' ),
            'estate_offer_kind'        => __( 'Rodzaj nieruchomości', 'estate-office' ),
            'estate_offer_city'        => __( 'Miasto', 'estate-office' ),
            'estate_offer_district'    => __( 'Dzielnica', 'estate-office' ),
        ];

        foreach ( $taxonomies as $taxonomy => $label ) {
            register_taxonomy(
                $taxonomy,
                [ 'estate_offer' ],
                [
                    'labels'       => [
                        'name'          => $label,
                        'singular_name' => $label,
                    ],
                    'public'       => true,
                    'hierarchical' => false,
                    'show_ui'      => true,
                    'show_in_rest' => true,
                ]
            );
        }
    }

    /**
     * Registers shortcodes.
     */
    public function register_shortcodes(): void {
        add_shortcode( 'estate_office_offers', [ $this, 'render_offers_shortcode' ] );
    }

    /**
     * Registers actions available from the admin area.
     */
    public function register_admin_actions(): void {
        add_action( 'admin_post_estate_office_sync_offer', [ $this, 'handle_manual_sync' ] );
    }

    /**
     * Ensures the listing pages exist with the shortcodes attached.
     */
    public function ensure_listing_pages(): void {
        $pages = [
            self::SALE_PAGE_SLUG => [
                'title'   => __( 'Oferty na sprzedaż', 'estate-office' ),
                'content' => '[estate_office_offers transaction="sprzedaz"]',
            ],
            self::RENT_PAGE_SLUG => [
                'title'   => __( 'Oferty na wynajem', 'estate-office' ),
                'content' => '[estate_office_offers transaction="wynajem"]',
            ],
        ];

        foreach ( $pages as $slug => $data ) {
            $page = get_page_by_path( $slug );

            if ( $page ) {
                if ( strpos( $page->post_content, $data['content'] ) === false ) {
                    wp_update_post(
                        [
                            'ID'           => $page->ID,
                            'post_content' => $data['content'],
                        ]
                    );
                }

                continue;
            }

            wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $data['title'],
                    'post_name'    => $slug,
                    'post_content' => $data['content'],
                ]
            );
        }
    }

    /**
     * Enqueues assets required by offer listings and singles.
     */
    public function enqueue_assets(): void {
        global $post;

        $should_enqueue = false;

        if ( is_singular( 'estate_offer' ) ) {
            $should_enqueue = true;
        } elseif ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'estate_office_offers' ) ) {
            $should_enqueue = true;
        }

        if ( ! $should_enqueue ) {
            return;
        }

        wp_enqueue_style( 'estate-office-frontend', ESTATE_OFFICE_URL . 'assets/css/frontend.css', [], ESTATE_OFFICE_VERSION );
    }

    /**
     * Synchronises offer pages when a property is saved.
     */
    public function sync_offer( int $post_id, WP_Post $post, bool $update ): void {
        if ( 'estate_property' !== $post->post_type ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $this->refresh_offer( $post_id );
    }

    /**
     * Refreshes the offer for the provided property.
     */
    public function refresh_offer( int $property_id ): void {
        $property = get_post( $property_id );

        if ( ! $property || 'estate_property' !== $property->post_type ) {
            return;
        }

        $badges = get_post_meta( $property_id, Keys::PROPERTY_BADGES, true );
        $badges = is_array( $badges ) ? array_map( 'sanitize_key', $badges ) : [];

        $this->badges->track_new_offer_badge( $property_id, $badges );

        $has_export = in_array( 'export_www', $badges, true );

        if ( 'publish' !== $property->post_status || ! $has_export ) {
            $this->disable_offer( $property_id );
            return;
        }

        $this->upsert_offer( $property_id, $property );
    }

    /**
     * Deletes offers associated with a property when the property is trashed.
     */
    public function handle_property_trashed( int $post_id ): void {
        $post = get_post( $post_id );

        if ( ! $post || 'estate_property' !== $post->post_type ) {
            return;
        }

        $offer_id = (int) get_post_meta( $post_id, Keys::PROPERTY_OFFER_POST, true );

        if ( $offer_id ) {
            wp_trash_post( $offer_id );
        }
    }

    /**
     * Removes offers when the property is deleted.
     */
    public function handle_property_deleted( int $post_id ): void {
        $post = get_post( $post_id );

        if ( ! $post || 'estate_property' !== $post->post_type ) {
            return;
        }

        $offer_id = (int) get_post_meta( $post_id, Keys::PROPERTY_OFFER_POST, true );

        if ( $offer_id ) {
            wp_delete_post( $offer_id, true );
        }
    }

    /**
     * Handles manual synchronisation requests from the admin page.
     */
    public function handle_manual_sync(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do synchronizacji ofert.', 'estate-office' ) );
        }

        $property_id = isset( $_GET['property_id'] ) ? (int) $_GET['property_id'] : 0;

        check_admin_referer( 'estate_office_sync_offer_' . $property_id );

        if ( $property_id > 0 ) {
            $this->refresh_offer( $property_id );
        }

        $redirect = add_query_arg(
            [
                'page'   => 'estate-office-offers',
                'synced' => $property_id,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Filters single offer content to display structured property data.
     */
    public function filter_offer_content( string $content ): string {
        if ( ! is_singular( 'estate_offer' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $offer      = get_post();
        $property_id = (int) get_post_meta( $offer->ID, Keys::OFFER_PROPERTY, true );

        if ( $property_id <= 0 ) {
            return $content;
        }

        $payload = $this->prepare_property_payload( $property_id );

        if ( empty( $payload ) ) {
            return $content;
        }

        ob_start();
        $this->render_offer_layout( $payload );
        $markup = ob_get_clean();

        return $markup ?: $content;
    }

    /**
     * Generates the offers listing grouped by taxonomy terms.
     */
    public function render_offers_shortcode( array $atts ): string {
        $atts = shortcode_atts(
            [
                'transaction' => 'sprzedaz',
            ],
            $atts,
            'estate_office_offers'
        );

        $transaction = sanitize_key( $atts['transaction'] );

        $query = new WP_Query(
            [
                'post_type'      => 'estate_offer',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'tax_query'      => [
                    [
                        'taxonomy' => 'estate_offer_transaction',
                        'field'    => 'slug',
                        'terms'    => $transaction,
                    ],
                ],
            ]
        );

        if ( ! $query->have_posts() ) {
            wp_reset_postdata();
            return '<p class="estate-office-message">' . esc_html__( 'Brak ofert do wyświetlenia.', 'estate-office' ) . '</p>';
        }

        $groups = [];

        foreach ( $query->posts as $post ) {
            $property_id = (int) get_post_meta( $post->ID, Keys::OFFER_PROPERTY, true );

            if ( $property_id <= 0 ) {
                continue;
            }

            $payload = $this->prepare_property_payload( $property_id, $post );

            if ( empty( $payload ) ) {
                continue;
            }

            $kind     = $payload['kind_label'];
            $city     = $payload['address']['city'] ?: __( 'Pozostałe lokalizacje', 'estate-office' );
            $district = $payload['address']['district'] ?: __( 'Pozostałe dzielnice', 'estate-office' );

            if ( ! isset( $groups[ $kind ] ) ) {
                $groups[ $kind ] = [];
            }
            if ( ! isset( $groups[ $kind ][ $city ] ) ) {
                $groups[ $kind ][ $city ] = [];
            }
            if ( ! isset( $groups[ $kind ][ $city ][ $district ] ) ) {
                $groups[ $kind ][ $city ][ $district ] = [];
            }

            $groups[ $kind ][ $city ][ $district ][] = $payload;
        }

        wp_reset_postdata();

        ob_start();

        echo '<div class="estate-office-offers">';

        foreach ( $groups as $kind_label => $cities ) {
            echo '<section class="estate-office-offers__group">';
            echo '<h2 class="estate-office-offers__heading">' . esc_html( $kind_label ) . '</h2>';

            foreach ( $cities as $city_label => $districts ) {
                echo '<div class="estate-office-offers__city">';
                echo '<h3 class="estate-office-offers__subheading">' . esc_html( sprintf( __( 'Miasto: %s', 'estate-office' ), $city_label ) ) . '</h3>';

                foreach ( $districts as $district_label => $items ) {
                    echo '<div class="estate-office-offers__district">';

                    if ( $district_label ) {
                        echo '<h4 class="estate-office-offers__minor-heading">' . esc_html( sprintf( __( 'Dzielnica: %s', 'estate-office' ), $district_label ) ) . '</h4>';
                    }

                    echo '<div class="estate-office-offers__list">';

                    foreach ( $items as $item ) {
                        $this->render_offer_card( $item );
                    }

                    echo '</div>';
                    echo '</div>';
                }

                echo '</div>';
            }

            echo '</section>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Creates or updates the offer post using property data.
     */
    private function upsert_offer( int $property_id, WP_Post $property ): void {
        $offer_id = (int) get_post_meta( $property_id, Keys::PROPERTY_OFFER_POST, true );
        $payload  = $this->prepare_property_payload( $property_id );

        if ( empty( $payload ) ) {
            return;
        }

        $data = [
            'post_type'    => 'estate_offer',
            'post_status'  => 'publish',
            'post_title'   => $property->post_title,
            'post_excerpt' => wp_trim_words( wp_strip_all_tags( $payload['description'] ), 40 ),
        ];

        if ( $offer_id > 0 ) {
            $data['ID'] = $offer_id;
            $offer_id   = wp_update_post( $data );
        } else {
            $offer_id = wp_insert_post( $data );

            if ( ! is_wp_error( $offer_id ) ) {
                update_post_meta( $property_id, Keys::PROPERTY_OFFER_POST, $offer_id );
            }
        }

        if ( is_wp_error( $offer_id ) ) {
            return;
        }

        update_post_meta( $offer_id, Keys::OFFER_PROPERTY, $property_id );

        $content = $this->capture_offer_layout( $payload );

        if ( $content ) {
            wp_update_post(
                [
                    'ID'           => $offer_id,
                    'post_content' => $content,
                ]
            );
        }

        $this->sync_taxonomies( $offer_id, $payload );
    }

    /**
     * Marks the offer as draft when export badge disappears.
     */
    private function disable_offer( int $property_id ): void {
        $offer_id = (int) get_post_meta( $property_id, Keys::PROPERTY_OFFER_POST, true );

        if ( $offer_id ) {
            wp_update_post(
                [
                    'ID'          => $offer_id,
                    'post_status' => 'draft',
                ]
            );
        }
    }

    /**
     * Syncs taxonomy terms for the offer.
     */
    private function sync_taxonomies( int $offer_id, array $payload ): void {
        if ( $payload['transaction_slug'] ) {
            wp_set_object_terms( $offer_id, $payload['transaction_slug'], 'estate_offer_transaction', false );
        }

        if ( $payload['kind_slug'] ) {
            wp_set_object_terms( $offer_id, $payload['kind_slug'], 'estate_offer_kind', false );
        }

        if ( $payload['address']['city'] ) {
            wp_set_object_terms( $offer_id, $payload['address']['city'], 'estate_offer_city', false );
        }

        if ( $payload['address']['district'] ) {
            wp_set_object_terms( $offer_id, $payload['address']['district'], 'estate_offer_district', false );
        }
    }

    /**
     * Builds structured payload for the property.
     */
    private function prepare_property_payload( int $property_id, ?WP_Post $offer = null ): array {
        $property = get_post( $property_id );

        if ( ! $property || 'estate_property' !== $property->post_type ) {
            return [];
        }

        $reference   = get_post_meta( $property_id, Keys::PROPERTY_REFERENCE, true );
        $transaction = sanitize_key( get_post_meta( $property_id, Keys::PROPERTY_TRANSACTION, true ) );
        $kind        = sanitize_key( get_post_meta( $property_id, Keys::PROPERTY_KIND, true ) );
        $address     = get_post_meta( $property_id, Keys::PROPERTY_ADDRESS, true );
        $legal       = sanitize_key( get_post_meta( $property_id, Keys::PROPERTY_LEGAL_STATUS, true ) );
        $price       = (float) get_post_meta( $property_id, Keys::PROPERTY_PRICE, true );
        $admin_fee   = (float) get_post_meta( $property_id, Keys::PROPERTY_ADMIN_FEE, true );
        $area        = (float) get_post_meta( $property_id, Keys::PROPERTY_AREA, true );
        $price_m2    = (float) get_post_meta( $property_id, Keys::PROPERTY_PRICE_PER_M2, true );
        $description = get_post_meta( $property_id, Keys::PROPERTY_DESCRIPTION, true );
        $building    = get_post_meta( $property_id, Keys::PROPERTY_BUILDING, true );
        $media       = get_post_meta( $property_id, Keys::PROPERTY_MEDIA, true );
        $amenities   = get_post_meta( $property_id, Keys::PROPERTY_AMENITIES, true );
        $equipment   = get_post_meta( $property_id, Keys::PROPERTY_EQUIPMENT, true );
        $extra       = get_post_meta( $property_id, Keys::PROPERTY_EXTRA_SPACES, true );
        $video       = get_post_meta( $property_id, Keys::PROPERTY_VIDEO, true );
        $vr          = get_post_meta( $property_id, Keys::PROPERTY_VR, true );
        $badges      = get_post_meta( $property_id, Keys::PROPERTY_BADGES, true );
        $gallery_ids = get_post_meta( $property_id, Keys::PROPERTY_GALLERY, true );
        $floorplan_2d = (int) get_post_meta( $property_id, Keys::PROPERTY_FLOORPLAN_2D, true );
        $floorplan_3d = (int) get_post_meta( $property_id, Keys::PROPERTY_FLOORPLAN_3D, true );

        if ( empty( $reference ) ) {
            return [];
        }

        $address = is_array( $address ) ? $address : [];
        $building = is_array( $building ) ? $building : [];
        $media    = is_array( $media ) ? $media : [];
        $amenities = is_array( $amenities ) ? array_map( 'sanitize_key', $amenities ) : [];
        $equipment = is_array( $equipment ) ? array_map( 'sanitize_key', $equipment ) : [];
        $extra     = is_array( $extra ) ? $extra : [];
        $gallery_ids = is_array( $gallery_ids ) ? array_map( 'intval', $gallery_ids ) : [];
        $badges      = is_array( $badges ) ? array_map( 'sanitize_key', $badges ) : [];

        $address_normalized = [
            'street'      => $address['street'] ?? '',
            'number'      => $address['number'] ?? '',
            'unit'        => $address['unit'] ?? '',
            'postal_code' => $address['postal_code'] ?? '',
            'city'        => $address['city'] ?? '',
            'district'    => $address['district'] ?? '',
            'county'      => $address['county'] ?? '',
            'country'     => $address['country'] ?? '',
        ];

        $offer_id = $offer instanceof WP_Post ? $offer->ID : (int) get_post_meta( $property_id, Keys::PROPERTY_OFFER_POST, true );
        $permalink = $offer_id ? get_permalink( $offer_id ) : get_permalink( $property );

        return [
            'property_id'      => $property_id,
            'reference'        => $reference,
            'transaction_slug' => $transaction,
            'transaction'      => $this->format_transaction( $transaction ),
            'kind_slug'        => $kind,
            'kind_label'       => $this->format_kind( $kind ),
            'address'          => $address_normalized,
            'address_label'    => $this->format_address_label( $address_normalized ),
            'legal_status'     => $this->format_legal_status( $legal ),
            'price'            => $price,
            'price_formatted'  => $this->format_price( $price ),
            'admin_fee'        => $admin_fee,
            'admin_fee_label'  => $this->format_price( $admin_fee, true ),
            'area'             => $area,
            'area_formatted'   => $this->format_area( $area ),
            'price_m2'         => $price_m2,
            'price_m2_label'   => $price_m2 > 0 ? sprintf( __( '%s PLN/m²', 'estate-office' ), number_format_i18n( $price_m2, 2 ) ) : '',
            'rooms'            => (int) get_post_meta( $property_id, Keys::PROPERTY_ROOMS, true ),
            'bedrooms'         => (int) get_post_meta( $property_id, Keys::PROPERTY_BEDROOMS, true ),
            'bathrooms'        => (int) get_post_meta( $property_id, Keys::PROPERTY_BATHROOMS, true ),
            'toilets'          => (int) get_post_meta( $property_id, Keys::PROPERTY_TOILETS, true ),
            'year_built'       => (int) get_post_meta( $property_id, Keys::PROPERTY_YEAR_BUILT, true ),
            'floor'            => (int) get_post_meta( $property_id, Keys::PROPERTY_FLOOR, true ),
            'floors'           => (int) get_post_meta( $property_id, Keys::PROPERTY_FLOORS, true ),
            'plot'             => get_post_meta( $property_id, Keys::PROPERTY_PLOT_SHAPE, true ),
            'description'      => is_string( $description ) ? $description : '',
            'building'         => $building,
            'media'            => $media,
            'amenities'        => $amenities,
            'equipment'        => $equipment,
            'extra_spaces'     => $extra,
            'video'            => esc_url_raw( $video ),
            'vr'               => esc_url_raw( $vr ),
            'badges'           => $badges,
            'permalink'        => $permalink,
            'agent'            => $this->agents->get_agent_context( (int) $property->post_author ),
            'gallery'          => $this->prepare_gallery_items( $gallery_ids ),
            'floorplan_2d'     => $this->prepare_floorplan_link( $floorplan_2d, __( 'Rzut 2D', 'estate-office' ) ),
            'floorplan_3d'     => $this->prepare_floorplan_link( $floorplan_3d, __( 'Rzut 3D', 'estate-office' ) ),
        ];
    }

    /**
     * Maps gallery attachments into an array suitable for rendering.
     */
    private function prepare_gallery_items( array $ids ): array {
        $items = [];

        foreach ( $ids as $id ) {
            $attachment_id = (int) $id;

            if ( $attachment_id <= 0 ) {
                continue;
            }

            $primary = wp_get_attachment_image_url( $attachment_id, 'large' );
            $full    = wp_get_attachment_url( $attachment_id );

            if ( ! $primary && ! $full ) {
                continue;
            }

            $items[] = [
                'id'    => $attachment_id,
                'url'   => $primary ?: $full,
                'full'  => $full ?: $primary,
                'alt'   => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $attachment_id ),
                'title' => get_the_title( $attachment_id ),
            ];
        }

        return $items;
    }

    /**
     * Builds metadata for floorplan links.
     */
    private function prepare_floorplan_link( int $attachment_id, string $label ): array {
        if ( $attachment_id <= 0 ) {
            return [];
        }

        $url = wp_get_attachment_url( $attachment_id );

        if ( ! $url ) {
            return [];
        }

        return [
            'url'   => $url,
            'label' => $label,
            'title' => get_the_title( $attachment_id ) ?: '',
        ];
    }

    /**
     * Captures the offer layout for storage within the offer post.
     */
    private function capture_offer_layout( array $payload ): string {
        ob_start();
        $this->render_offer_layout( $payload );
        return (string) ob_get_clean();
    }

    /**
     * Outputs the offer layout markup.
     */
    private function render_offer_layout( array $payload ): void {
        $badges = $this->map_badges( $payload['badges'] );

        echo '<article class="estate-office-offer">';

        if ( ! empty( $badges ) ) {
            echo '<div class="estate-office-offer__badges">';

            foreach ( $badges as $badge ) {
                printf(
                    '<span class="estate-office-offer__badge estate-office-offer__badge--%1$s">%2$s</span>',
                    esc_attr( $badge['slug'] ),
                    esc_html( $badge['label'] )
                );
            }

            echo '</div>';
        }

        echo '<section class="estate-office-offer__section">';
        echo '<h2>' . esc_html__( 'Podstawowe informacje', 'estate-office' ) . '</h2>';
        echo '<dl class="estate-office-offer__summary">';
        $this->render_summary_row( __( 'Numer oferty', 'estate-office' ), $payload['reference'] );
        $this->render_summary_row( __( 'Typ transakcji', 'estate-office' ), $payload['transaction'] );
        $this->render_summary_row( __( 'Rodzaj nieruchomości', 'estate-office' ), $payload['kind_label'] );
        $this->render_summary_row( __( 'Adres', 'estate-office' ), $payload['address_label'] );
        $this->render_summary_row( __( 'Stan prawny', 'estate-office' ), $payload['legal_status'] );
        $this->render_summary_row( __( 'Cena', 'estate-office' ), $payload['price_formatted'] );

        if ( $payload['admin_fee_label'] ) {
            $this->render_summary_row( __( 'Czynsz administracyjny', 'estate-office' ), $payload['admin_fee_label'] );
        }

        $this->render_summary_row( __( 'Metraż', 'estate-office' ), $payload['area_formatted'] );

        if ( $payload['price_m2_label'] ) {
            $this->render_summary_row( __( 'Cena za m²', 'estate-office' ), $payload['price_m2_label'] );
        }

        if ( $payload['rooms'] ) {
            $this->render_summary_row( __( 'Liczba pokoi', 'estate-office' ), (string) $payload['rooms'] );
        }

        if ( $payload['bedrooms'] ) {
            $this->render_summary_row( __( 'Liczba sypialni', 'estate-office' ), (string) $payload['bedrooms'] );
        }

        if ( $payload['bathrooms'] ) {
            $this->render_summary_row( __( 'Liczba łazienek', 'estate-office' ), (string) $payload['bathrooms'] );
        }

        if ( $payload['toilets'] ) {
            $this->render_summary_row( __( 'Liczba toalet', 'estate-office' ), (string) $payload['toilets'] );
        }

        if ( $payload['year_built'] ) {
            $this->render_summary_row( __( 'Rok budowy', 'estate-office' ), (string) $payload['year_built'] );
        }

        if ( $payload['floor'] ) {
            $this->render_summary_row( __( 'Piętro', 'estate-office' ), (string) $payload['floor'] );
        }

        if ( $payload['floors'] ) {
            $this->render_summary_row( __( 'Liczba pięter', 'estate-office' ), (string) $payload['floors'] );
        }

        echo '</dl>';
        echo '</section>';

        if ( ! empty( $payload['gallery'] ) ) {
            $this->render_gallery_section( $payload['gallery'] );
        }

        if ( ! empty( $payload['floorplan_2d'] ) || ! empty( $payload['floorplan_3d'] ) ) {
            $this->render_floorplan_section( $payload['floorplan_2d'], $payload['floorplan_3d'] );
        }

        if ( $payload['description'] ) {
            echo '<section class="estate-office-offer__section">';
            echo '<h2>' . esc_html__( 'Opis nieruchomości', 'estate-office' ) . '</h2>';
            echo wp_kses_post( wpautop( $payload['description'] ) );
            echo '</section>';
        }

        $this->render_detail_lists( $payload );

        if ( ! empty( $payload['agent']['name'] ) ) {
            $this->render_agent_section( $payload['agent'] );
        }

        if ( $payload['video'] || $payload['vr'] ) {
            echo '<section class="estate-office-offer__section estate-office-offer__section--media">';
            echo '<h2>' . esc_html__( 'Multimedia', 'estate-office' ) . '</h2>';
            echo '<ul class="estate-office-offer__media">';

            if ( $payload['video'] ) {
                printf(
                    '<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
                    esc_url( $payload['video'] ),
                    esc_html__( 'Zobacz film', 'estate-office' )
                );
            }

            if ( $payload['vr'] ) {
                printf(
                    '<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
                    esc_url( $payload['vr'] ),
                    esc_html__( 'Wirtualny spacer', 'estate-office' )
                );
            }

            echo '</ul>';
            echo '</section>';
        }

        echo '</article>';
    }

    /**
     * Renders the gallery section for the single offer view.
     */
    private function render_gallery_section( array $gallery ): void {
        if ( empty( $gallery ) ) {
            return;
        }

        $items   = $gallery;
        $primary = array_shift( $items );

        if ( empty( $primary['url'] ) ) {
            return;
        }

        echo '<section class="estate-office-offer__section estate-office-offer__section--gallery">';
        echo '<h2>' . esc_html__( 'Galeria zdjęć', 'estate-office' ) . '</h2>';

        $primary_link = ! empty( $primary['full'] ) ? $primary['full'] : $primary['url'];
        echo '<figure class="estate-office-offer__gallery-main">';
        echo '<a href="' . esc_url( $primary_link ) . '" target="_blank" rel="noopener">';
        echo '<img src="' . esc_url( $primary['url'] ) . '" alt="' . esc_attr( $primary['alt'] ?? '' ) . '" />';
        echo '</a>';
        echo '</figure>';

        if ( ! empty( $items ) ) {
            echo '<div class="estate-office-offer__gallery-thumbs">';

            foreach ( $items as $item ) {
                if ( empty( $item['url'] ) ) {
                    continue;
                }

                $link = ! empty( $item['full'] ) ? $item['full'] : $item['url'];
                echo '<a class="estate-office-offer__gallery-thumb" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">';
                echo '<img src="' . esc_url( $item['url'] ) . '" alt="' . esc_attr( $item['alt'] ?? '' ) . '" />';
                echo '</a>';
            }

            echo '</div>';
        }

        echo '</section>';
    }

    /**
     * Outputs links to available floor plans.
     */
    private function render_floorplan_section( array $plan_2d, array $plan_3d ): void {
        $links = [];

        foreach ( [ $plan_2d, $plan_3d ] as $plan ) {
            if ( empty( $plan['url'] ) ) {
                continue;
            }

            $label = $plan['label'] ?? '';
            if ( ! empty( $plan['title'] ) ) {
                $label .= ' – ' . $plan['title'];
            }

            $links[] = [
                'url'   => $plan['url'],
                'label' => $label,
            ];
        }

        if ( empty( $links ) ) {
            return;
        }

        echo '<section class="estate-office-offer__section estate-office-offer__section--floorplans">';
        echo '<h2>' . esc_html__( 'Rzuty', 'estate-office' ) . '</h2>';
        echo '<ul class="estate-office-offer__media estate-office-offer__media--floorplans">';

        foreach ( $links as $link ) {
            echo '<li><a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $link['label'] ) . '</a></li>';
        }

        echo '</ul>';
        echo '</section>';
    }

    /**
     * Outputs a card for the offer listing.
     */
    private function render_offer_card( array $payload ): void {
        echo '<article class="estate-office-offer-card">';
        echo '<h5 class="estate-office-offer-card__title"><a href="' . esc_url( $payload['permalink'] ) . '">' . esc_html( $payload['reference'] ) . '</a></h5>';

        if ( $payload['address_label'] ) {
            echo '<p class="estate-office-offer-card__address">' . esc_html( $payload['address_label'] ) . '</p>';
        }

        if ( ! empty( $payload['badges'] ) ) {
            echo '<div class="estate-office-offer-card__badges">';

            foreach ( $this->map_badges( $payload['badges'] ) as $badge ) {
                printf(
                    '<span class="estate-office-offer-card__badge estate-office-offer-card__badge--%1$s">%2$s</span>',
                    esc_attr( $badge['slug'] ),
                    esc_html( $badge['label'] )
                );
            }

            echo '</div>';
        }

        echo '<ul class="estate-office-offer-card__meta">';
        printf( '<li><strong>%s</strong> %s</li>', esc_html__( 'Cena:', 'estate-office' ), esc_html( $payload['price_formatted'] ) );
        printf( '<li><strong>%s</strong> %s</li>', esc_html__( 'Metraż:', 'estate-office' ), esc_html( $payload['area_formatted'] ) );

        if ( $payload['rooms'] ) {
            printf( '<li><strong>%s</strong> %d</li>', esc_html__( 'Pokoje:', 'estate-office' ), $payload['rooms'] );
        }

        echo '</ul>';
        echo '</article>';
    }

    /**
     * Outputs summary row for definition lists.
     */
    private function render_summary_row( string $label, string $value ): void {
        if ( '' === trim( $value ) ) {
            return;
        }

        echo '<div class="estate-office-offer__summary-row">';
        echo '<dt>' . esc_html( $label ) . '</dt>';
        echo '<dd>' . esc_html( $value ) . '</dd>';
        echo '</div>';
    }

    /**
     * Outputs lists for building/media/equipment details.
     */
    private function render_detail_lists( array $payload ): void {
        $building  = $this->format_building_details( $payload['building'] );
        $media     = $this->format_media_details( $payload['media'] );
        $amenities = $this->map_simple_labels( $payload['amenities'] );
        $equipment = $this->map_simple_labels( $payload['equipment'] );
        $extras    = $this->format_extra_spaces( $payload['extra_spaces'] );

        if ( ! empty( $building ) ) {
            $this->render_definition_section( __( 'Szczegóły budynku', 'estate-office' ), $building );
        }

        if ( ! empty( $media ) ) {
            $this->render_definition_section( __( 'Media', 'estate-office' ), $media );
        }

        if ( ! empty( $amenities ) ) {
            $this->render_list_section( __( 'Udogodnienia', 'estate-office' ), $amenities );
        }

        if ( ! empty( $equipment ) ) {
            $this->render_list_section( __( 'Wyposażenie', 'estate-office' ), $equipment );
        }

        if ( ! empty( $extras ) ) {
            $this->render_list_section( __( 'Powierzchnie dodatkowe', 'estate-office' ), $extras );
        }
    }

    /**
     * Renders a section with definition list content.
     */
    private function render_definition_section( string $title, array $rows ): void {
        echo '<section class="estate-office-offer__section">';
        echo '<h2>' . esc_html( $title ) . '</h2>';
        echo '<dl class="estate-office-offer__details">';

        foreach ( $rows as $row ) {
            $this->render_summary_row( $row['label'], $row['value'] );
        }

        echo '</dl>';
        echo '</section>';
    }

    /**
     * Renders a section with unordered list content.
     */
    private function render_list_section( string $title, array $items ): void {
        echo '<section class="estate-office-offer__section">';
        echo '<h2>' . esc_html( $title ) . '</h2>';
        echo '<ul class="estate-office-offer__list">';

        foreach ( $items as $item ) {
            echo '<li>' . esc_html( $item ) . '</li>';
        }

        echo '</ul>';
        echo '</section>';
    }

    /**
     * Renders agent contact section.
     */
    private function render_agent_section( array $agent ): void {
        echo '<section class="estate-office-offer__section estate-office-offer__section--agent">';
        echo '<h2>' . esc_html__( 'Agent prowadzący', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-offer__agent">';

        if ( ! empty( $agent['photo'] ) ) {
            printf(
                '<div class="estate-office-offer__agent-photo"><img src="%1$s" alt="%2$s" /></div>',
                esc_url( $agent['photo'] ),
                esc_attr( $agent['name'] )
            );
        }

        echo '<div class="estate-office-offer__agent-details">';
        echo '<p class="estate-office-offer__agent-name">' . esc_html( $agent['name'] ) . '</p>';

        if ( ! empty( $agent['phone'] ) ) {
            $href = ! empty( $agent['phone_href'] ) ? $agent['phone_href'] : preg_replace( '/[^0-9+]/', '', $agent['phone'] );
            if ( $href ) {
                printf(
                    '<p class="estate-office-offer__agent-contact"><a href="tel:%1$s">%2$s</a></p>',
                    esc_attr( $href ),
                    esc_html( $agent['phone'] )
                );
            } else {
                echo '<p class="estate-office-offer__agent-contact">' . esc_html( $agent['phone'] ) . '</p>';
            }
        }

        if ( ! empty( $agent['email'] ) ) {
            printf(
                '<p class="estate-office-offer__agent-contact"><a href="mailto:%1$s">%1$s</a></p>',
                esc_html( $agent['email'] )
            );
        }

        if ( ! empty( $agent['bio'] ) ) {
            echo '<div class="estate-office-offer__agent-bio">' . wp_kses_post( wpautop( $agent['bio'] ) ) . '</div>';
        }

        if ( ! empty( $agent['profile_url'] ) ) {
            printf(
                '<p class="estate-office-offer__agent-link"><a href="%1$s">%2$s</a></p>',
                esc_url( $agent['profile_url'] ),
                esc_html__( 'Zobacz profil agenta', 'estate-office' )
            );
        }

        echo '</div>';
        echo '</div>';
        echo '</section>';
    }

    /**
     * Formats building/media array into label/value pairs.
     */
    private function format_building_details( array $building ): array {
        $rows = [];

        if ( ! empty( $building['finish'] ) ) {
            $rows[] = [
                'label' => __( 'Stan wykończenia', 'estate-office' ),
                'value' => $this->format_dictionary_label( $building['finish'] ),
            ];
        }

        if ( ! empty( $building['exposure'] ) && is_array( $building['exposure'] ) ) {
            $rows[] = [
                'label' => __( 'Ekspozycja', 'estate-office' ),
                'value' => implode( ', ', array_map( [ $this, 'format_dictionary_label' ], $building['exposure'] ) ),
            ];
        }

        if ( ! empty( $building['view'] ) && is_array( $building['view'] ) ) {
            $rows[] = [
                'label' => __( 'Widok', 'estate-office' ),
                'value' => implode( ', ', array_map( [ $this, 'format_dictionary_label' ], $building['view'] ) ),
            ];
        }

        if ( ! empty( $building['attic'] ) ) {
            $rows[] = [
                'label' => __( 'Poddasze', 'estate-office' ),
                'value' => __( 'Tak', 'estate-office' ),
            ];
        }

        if ( ! empty( $building['multi_level'] ) ) {
            $rows[] = [
                'label' => __( 'Wielopoziomowe', 'estate-office' ),
                'value' => __( 'Tak', 'estate-office' ),
            ];
        }

        if ( ! empty( $building['layout'] ) && is_array( $building['layout'] ) ) {
            $rows[] = [
                'label' => __( 'Rozkład', 'estate-office' ),
                'value' => implode( ', ', array_map( [ $this, 'format_dictionary_label' ], $building['layout'] ) ),
            ];
        }

        if ( ! empty( $building['kitchen'] ) ) {
            $rows[] = [
                'label' => __( 'Kuchnia', 'estate-office' ),
                'value' => $this->format_dictionary_label( $building['kitchen'] ),
            ];
        }

        if ( ! empty( $building['parking'] ) && is_array( $building['parking'] ) && ! empty( $building['parking']['has'] ) ) {
            $types = [];

            if ( ! empty( $building['parking']['types'] ) && is_array( $building['parking']['types'] ) ) {
                $types = array_map( [ $this, 'format_dictionary_label' ], $building['parking']['types'] );
            }

            $rows[] = [
                'label' => __( 'Miejsca parkingowe', 'estate-office' ),
                'value' => $types ? implode( ', ', $types ) : __( 'Dostępne', 'estate-office' ),
            ];
        }

        return $rows;
    }

    private function format_media_details( array $media ): array {
        $rows = [];

        if ( ! empty( $media['heating'] ) ) {
            $rows[] = [
                'label' => __( 'Ogrzewanie', 'estate-office' ),
                'value' => $this->format_dictionary_label( $media['heating'] ),
            ];
        }

        if ( ! empty( $media['water'] ) ) {
            $rows[] = [
                'label' => __( 'Woda', 'estate-office' ),
                'value' => $this->format_dictionary_label( $media['water'] ),
            ];
        }

        if ( ! empty( $media['sewage'] ) ) {
            $rows[] = [
                'label' => __( 'Kanalizacja', 'estate-office' ),
                'value' => $this->format_dictionary_label( $media['sewage'] ),
            ];
        }

        if ( ! empty( $media['gas'] ) ) {
            $rows[] = [
                'label' => __( 'Gaz', 'estate-office' ),
                'value' => __( 'Tak', 'estate-office' ),
            ];
        }

        return $rows;
    }

    /**
     * Formats amenities/equipment labels.
     */
    private function map_simple_labels( array $items ): array {
        $labels = [];

        foreach ( $items as $item ) {
            $labels[] = $this->format_dictionary_label( $item );
        }

        return array_filter( $labels );
    }

    /**
     * Formats additional spaces list.
     */
    private function format_extra_spaces( array $spaces ): array {
        $labels = [];

        foreach ( $spaces as $key => $data ) {
            if ( empty( $data['has'] ) ) {
                continue;
            }

            $label = $this->format_dictionary_label( $key );
            $parts = [];

            if ( ! empty( $data['count'] ) ) {
                $parts[] = sprintf( __( '%d szt.', 'estate-office' ), (int) $data['count'] );
            }

            if ( ! empty( $data['area'] ) ) {
                $parts[] = sprintf( __( '%s m²', 'estate-office' ), number_format_i18n( (float) $data['area'], 2 ) );
            }

            $labels[] = $parts ? sprintf( '%s (%s)', $label, implode( ', ', $parts ) ) : $label;
        }

        return $labels;
    }

    /**
     * Maps raw badge slugs to display labels.
     */
    private function map_badges( array $badges ): array {
        $map = [
            'nowa_oferta' => __( 'Nowa oferta', 'estate-office' ),
            'wylacznosc'  => __( 'Wyłączność', 'estate-office' ),
            'sprzedane'   => __( 'Sprzedane', 'estate-office' ),
            'wynajete'    => __( 'Wynajęte', 'estate-office' ),
            'nowa_cena'   => __( 'Nowa cena', 'estate-office' ),
            'bez_prowizji'=> __( 'Bez prowizji', 'estate-office' ),
            'mls'         => __( 'Oferta MLS', 'estate-office' ),
            'premium'     => __( 'Premium', 'estate-office' ),
        ];

        $labels = [];

        foreach ( $badges as $badge ) {
            if ( isset( $map[ $badge ] ) ) {
                $labels[] = [
                    'slug'  => $badge,
                    'label' => $map[ $badge ],
                ];
            }
        }

        return $labels;
    }

    /**
     * Formats dictionary labels by replacing underscores and translating known keys.
     */
    private function format_dictionary_label( string $value ): string {
        $map = [
            'polnoc'             => __( 'Północ', 'estate-office' ),
            'poludnie'           => __( 'Południe', 'estate-office' ),
            'wschod'             => __( 'Wschód', 'estate-office' ),
            'zachod'             => __( 'Zachód', 'estate-office' ),
            'park'               => __( 'Widok na park', 'estate-office' ),
            'miasto'             => __( 'Widok na miasto', 'estate-office' ),
            'inne'               => __( 'Inne', 'estate-office' ),
            'do_wykonczenia'     => __( 'Do wykończenia', 'estate-office' ),
            'do_zamieszkania'    => __( 'Do zamieszkania', 'estate-office' ),
            'pod_klucz'          => __( 'Pod klucz', 'estate-office' ),
            'dwustronne'         => __( 'Dwustronne', 'estate-office' ),
            'rozkladowe'         => __( 'Rozkładowe', 'estate-office' ),
            'aneks'              => __( 'Aneks', 'estate-office' ),
            'oddzielna'          => __( 'Oddzielna', 'estate-office' ),
            'z_salonem'          => __( 'Z salonem', 'estate-office' ),
            'naziemne'           => __( 'Miejsce naziemne', 'estate-office' ),
            'podziemne'          => __( 'Miejsce podziemne', 'estate-office' ),
            'garaz'              => __( 'Garaż', 'estate-office' ),
            'miejskie'           => __( 'Miejskie', 'estate-office' ),
            'gazowe'             => __( 'Gazowe', 'estate-office' ),
            'studnia'            => __( 'Studnia', 'estate-office' ),
            'szambo'             => __( 'Szambo', 'estate-office' ),
            'winda'              => __( 'Winda', 'estate-office' ),
            'umeblowanie_pelne'  => __( 'Umeblowanie', 'estate-office' ),
            'klimatyzacja'       => __( 'Klimatyzacja', 'estate-office' ),
            'monitoring'         => __( 'Monitoring / Ochrona', 'estate-office' ),
            'recepcja'           => __( 'Recepcja', 'estate-office' ),
            'teren_zamkniety'    => __( 'Teren zamknięty', 'estate-office' ),
            'domofon'            => __( 'Domofon', 'estate-office' ),
            'pralka'             => __( 'Pralka', 'estate-office' ),
            'zmywarka'           => __( 'Zmywarka', 'estate-office' ),
            'lodowka'            => __( 'Lodówka', 'estate-office' ),
            'kuchenka'           => __( 'Kuchenka', 'estate-office' ),
            'piekarnik'          => __( 'Piekarnik', 'estate-office' ),
            'telewizor'          => __( 'Telewizor', 'estate-office' ),
            'mikrofala'          => __( 'Mikrofala', 'estate-office' ),
            'balcony'            => __( 'Balkon', 'estate-office' ),
            'taras'              => __( 'Taras', 'estate-office' ),
            'piwnica'            => __( 'Piwnica', 'estate-office' ),
            'komorka'            => __( 'Komórka lokatorska', 'estate-office' ),
            'ogrodek'            => __( 'Ogródek', 'estate-office' ),
            'wolnostojacy'       => __( 'Wolnostojący', 'estate-office' ),
            'blizniak'           => __( 'Bliźniak', 'estate-office' ),
            'szeregowiec'        => __( 'Szeregowiec', 'estate-office' ),
            'wielorodzinny'      => __( 'Wielorodzinny', 'estate-office' ),
        ];

        if ( isset( $map[ $value ] ) ) {
            return $map[ $value ];
        }

        $value = str_replace( [ '_', '-' ], ' ', $value );
        return ucwords( $value );
    }

    /**
     * Formats transaction slug to label.
     */
    private function format_transaction( string $transaction ): string {
        $map = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        return $map[ $transaction ] ?? ucfirst( $transaction );
    }

    /**
     * Formats property kind slug to label.
     */
    private function format_kind( string $kind ): string {
        $map = [
            'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
            'dom'        => __( 'Dom', 'estate-office' ),
            'dzialka'    => __( 'Działka', 'estate-office' ),
            'lokal'      => __( 'Lokal H/U', 'estate-office' ),
        ];

        return $map[ $kind ] ?? ucfirst( $kind );
    }

    /**
     * Formats legal status slug to label.
     */
    private function format_legal_status( string $status ): string {
        $map = [
            'wlasnosc'    => __( 'Własność', 'estate-office' ),
            'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
            'spoldzielcze' => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
            'dzierzawa'   => __( 'Dzierżawa', 'estate-office' ),
            'inne'        => __( 'Inne', 'estate-office' ),
        ];

        return $map[ $status ] ?? ucfirst( $status );
    }

    /**
     * Formats numeric price to label.
     */
    private function format_price( float $price, bool $allow_empty = false ): string {
        if ( $price <= 0 ) {
            return $allow_empty ? '' : __( 'Do uzgodnienia', 'estate-office' );
        }

        return number_format_i18n( $price, 0 ) . ' PLN';
    }

    /**
     * Formats area value.
     */
    private function format_area( float $area ): string {
        if ( $area <= 0 ) {
            return '—';
        }

        return number_format_i18n( $area, 2 ) . ' m²';
    }

    /**
     * Formats address array into single string.
     */
    private function format_address_label( array $address ): string {
        $parts = [];

        if ( $address['street'] ) {
            $street = $address['street'];

            if ( $address['number'] ) {
                $street .= ' ' . $address['number'];
            }

            if ( $address['unit'] ) {
                $street .= '/' . $address['unit'];
            }

            $parts[] = $street;
        }

        if ( $address['postal_code'] || $address['city'] ) {
            $parts[] = trim( $address['postal_code'] . ' ' . $address['city'] );
        }

        if ( $address['district'] ) {
            $parts[] = $address['district'];
        }

        if ( $address['country'] ) {
            $parts[] = $address['country'];
        }

        return implode( ', ', array_filter( $parts ) );
    }
}
