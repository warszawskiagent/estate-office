<?php
namespace EstateOffice\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Front-end listings and offer pages.
 */
class Listings {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_action( 'template_include', [ $this, 'maybe_render_offer_template' ] );
        add_shortcode( 'estate_office_offers', [ $this, 'render_offers_shortcode' ] );
    }

    /**
     * Registers rewrite rules for public offer pages.
     */
    public static function add_rewrite_rules(): void {
        add_rewrite_tag( '%estate_office_offer%', '([^&]+)' );
        add_rewrite_rule( '^oferta/([^/]+)/?$', 'index.php?estate_office_offer=$matches[1]', 'top' );
    }

    /**
     * Registers public assets.
     */
    public function register_assets(): void {
        wp_register_style(
            'estate-office-listings',
            ESTATE_OFFICE_ASSETS_URL . 'css/listings.css',
            [],
            ESTATE_OFFICE_VERSION
        );
    }

    /**
     * Adds custom query vars.
     *
     * @param array<int, string> $vars Query vars.
     * @return array<int, string>
     */
    public function register_query_vars( array $vars ): array {
        $vars[] = 'estate_office_offer';

        return $vars;
    }

    /**
     * Maybe render the public offer template.
     *
     * @param string $template Template path.
     * @return string
     */
    public function maybe_render_offer_template( string $template ): string {
        $slug = get_query_var( 'estate_office_offer' );
        if ( empty( $slug ) ) {
            return $template;
        }

        $offer = $this->get_offer_by_slug( sanitize_title( $slug ) );
        if ( ! $offer ) {
            global $wp_query;
            if ( $wp_query ) {
                $wp_query->set_404();
            }
            status_header( 404 );

            return get_404_template() ?: $template;
        }

        wp_enqueue_style( 'estate-office-listings' );
        set_query_var( 'estate_office_offer_data', $offer );

        return ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/views/single-offer.php';
    }

    /**
     * Renders listing shortcode output.
     *
     * @param array<string, string> $atts Shortcode attributes.
     * @return string
     */
    public function render_offers_shortcode( array $atts ): string {
        $atts = shortcode_atts(
            [
                'transaction' => 'sprzedaz',
                'limit'       => '0',
            ],
            $atts,
            'estate_office_offers'
        );

        $transaction = in_array( $atts['transaction'], [ 'sprzedaz', 'wynajem' ], true ) ? $atts['transaction'] : 'sprzedaz';
        $limit       = max( 0, (int) $atts['limit'] );

        $offers = $this->query_offers( $transaction, $limit );
        wp_enqueue_style( 'estate-office-listings' );

        ob_start();
        $this->render_offers_markup( $transaction, $offers );

        return ob_get_clean();
    }

    /**
     * Outputs the listing markup.
     *
     * @param string                              $transaction Transaction type.
     * @param array<string, array<string, array<string, array<int, array<string, mixed>>>> $offers Grouped offers.
     */
    private function render_offers_markup( string $transaction, array $offers ): void {
        $transaction_label = $this->get_transaction_label( $transaction );
        ?>
        <div class="estate-office-offers" data-transaction="<?php echo esc_attr( $transaction ); ?>">
            <div class="estate-office-offers-header">
                <h2><?php echo esc_html( sprintf( __( 'Oferty na %s', 'estate-office' ), $transaction_label ) ); ?></h2>
                <p class="estate-office-legend">
                    <?php esc_html_e( 'Prezentujemy tylko oferty oznaczone do eksportu na stronę WWW.', 'estate-office' ); ?>
                </p>
            </div>
            <?php if ( empty( $offers ) ) : ?>
                <div class="estate-office-empty">
                    <?php esc_html_e( 'Aktualnie brak dostępnych ofert dla wybranej kategorii.', 'estate-office' ); ?>
                </div>
            <?php else : ?>
                <?php foreach ( $offers as $property_type => $cities ) : ?>
                    <section class="estate-office-property-type">
                        <h3><?php echo esc_html( $this->get_property_type_label( $property_type ) ); ?></h3>
                        <?php foreach ( $cities as $city => $districts ) : ?>
                            <div class="estate-office-city">
                                <h4><?php echo esc_html( $city ); ?></h4>
                                <?php foreach ( $districts as $district => $entries ) : ?>
                                    <div class="estate-office-district">
                                        <?php if ( $district ) : ?>
                                            <h5><?php echo esc_html( $district ); ?></h5>
                                        <?php endif; ?>
                                        <div class="estate-office-property-grid">
                                            <?php foreach ( $entries as $entry ) : ?>
                                                <?php echo $this->get_offer_card_html( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Returns offer card markup.
     *
     * @param array<string, mixed> $entry Offer entry.
     * @return string
     */
    public function get_offer_card_html( array $entry ): string {
        $thumb_id  = $entry['thumbnail_id'] ?? 0;
        $image     = $thumb_id ? wp_get_attachment_image( $thumb_id, 'large' ) : '';
        $flag      = $entry['primary_flag'] ?? '';
        $permalink = $entry['permalink'] ?? '';

        ob_start();
        ?>
        <article class="estate-office-property-card">
            <div class="estate-office-card-media">
                <?php if ( $flag ) : ?>
                    <span class="estate-office-flag"><?php echo esc_html( $flag ); ?></span>
                <?php endif; ?>
                <?php echo $image ? $image : '<span class="screen-reader-text">' . esc_html__( 'Brak zdjęcia', 'estate-office' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="estate-office-card-body">
                <h6 class="estate-office-card-title"><?php echo esc_html( $entry['title'] ?? '' ); ?></h6>
                <div class="estate-office-card-meta">
                    <span>
                        <?php echo $this->get_icon_svg( 'location' ); ?>
                        <span><?php echo esc_html( $entry['address'] ?? '' ); ?></span>
                    </span>
                    <?php if ( ! empty( $entry['area'] ) ) : ?>
                        <span>
                            <?php echo $this->get_icon_svg( 'area' ); ?>
                            <span><?php echo esc_html( $entry['area'] ); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $entry['rooms'] ) ) : ?>
                        <span>
                            <?php echo $this->get_icon_svg( 'rooms' ); ?>
                            <span><?php echo esc_html( $entry['rooms'] ); ?></span>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $entry['price'] ) ) : ?>
                    <div class="estate-office-card-price"><?php echo esc_html( $entry['price'] ); ?></div>
                <?php endif; ?>
                <?php if ( $permalink ) : ?>
                    <div class="estate-office-card-link">
                        <a href="<?php echo esc_url( $permalink ); ?>">
                            <?php esc_html_e( 'Zobacz szczegóły', 'estate-office' ); ?>
                            <?php echo $this->get_icon_svg( 'arrow' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Builds reusable offer card entry for a property post.
     *
     * @param \WP_Post $post Property post.
     * @return array<string, mixed>
     */
    public function prepare_card_entry( \WP_Post $post ): array {
        $meta        = $this->get_post_meta( $post->ID );
        $transaction = $meta['transaction_type'] ?? 'sprzedaz';
        $title       = $meta['offer_number'] ? sprintf( '%s — %s', $meta['offer_number'], $post->post_title ) : $post->post_title;

        return [
            'entry' => [
                'title'        => $title,
                'address'      => $this->format_address( $meta ),
                'price'        => $this->format_price( $meta, $transaction ),
                'area'         => $this->format_area( $meta ),
                'rooms'        => $this->format_rooms( $meta ),
                'thumbnail_id' => $this->get_featured_media( $meta ),
                'primary_flag' => $this->get_primary_flag( $meta ),
                'permalink'    => home_url( user_trailingslashit( 'oferta/' . $post->post_name ) ),
            ],
            'transaction'   => $transaction,
            'property_type' => $meta['property_type'] ?? 'mieszkanie',
            'city'          => $meta['city'] ?? __( 'Nieznane miasto', 'estate-office' ),
            'district'      => $meta['district'] ?? '',
        ];
    }

    /**
     * Returns grouped offers for the listing.
     *
     * @param string $transaction Transaction type.
     * @param int    $limit       Result limit.
     * @return array<string, array<string, array<string, array<int, array<string, mixed>>>>> Grouped offers.
     */
    private function query_offers( string $transaction, int $limit ): array {
        $args = [
            'post_type'      => 'estate_property',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'meta_query'     => [
                [
                    'key'     => 'estate_office_flags',
                    'value'   => 'export_www',
                    'compare' => 'LIKE',
                ],
                [
                    'key'   => 'estate_office_transaction_type',
                    'value' => $transaction,
                ],
            ],
        ];

        $posts   = get_posts( $args );
        $grouped = [];

        foreach ( $posts as $post ) {
            $prepared  = $this->prepare_card_entry( $post );
            $entry     = $prepared['entry'];
            $type      = $prepared['property_type'];
            $city      = $prepared['city'];
            $district  = $prepared['district'];

            $grouped[ $type ][ $city ][ $district ][] = $entry;
        }

        return $grouped;
    }

    /**
     * Retrieves exportable offer by slug.
     *
     * @param string $slug Offer slug.
     * @return array<string, mixed>|null
     */
    private function get_offer_by_slug( string $slug ): ?array {
        if ( '' === $slug ) {
            return null;
        }

        $posts = get_posts(
            [
                'name'           => $slug,
                'post_type'      => 'estate_property',
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'     => 'estate_office_flags',
                        'value'   => 'export_www',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        if ( empty( $posts ) ) {
            return null;
        }

        $offer_post = $posts[0];
        $meta       = $this->get_post_meta( $offer_post->ID );

        global $post;
        $previous_post = $post;
        $post          = $offer_post;
        setup_postdata( $offer_post );
        $content = apply_filters( 'the_content', $offer_post->post_content );
        wp_reset_postdata();
        $post = $previous_post;

        return [
            'ID'             => $offer_post->ID,
            'title'          => $offer_post->post_title,
            'content'        => $content,
            'meta'           => $meta,
            'gallery'        => $this->parse_gallery_ids( $meta['gallery'] ?? '' ),
            'flags'          => $meta['flags'] ?? [],
            'transaction'    => $meta['transaction_type'] ?? '',
            'property_type'  => $meta['property_type'] ?? '',
            'offer_number'   => $meta['offer_number'] ?? '',
            'permalink'      => home_url( user_trailingslashit( 'oferta/' . $offer_post->post_name ) ),
        ];
    }

    /**
     * Returns plugin-formatted post meta.
     *
     * @param int $post_id Post ID.
     * @return array<string, mixed>
     */
    private function get_post_meta( int $post_id ): array {
        $meta  = [];
        $pairs = get_post_meta( $post_id );

        foreach ( $pairs as $key => $values ) {
            if ( 0 !== strpos( $key, 'estate_office_' ) ) {
                continue;
            }

            $meta_key        = substr( $key, strlen( 'estate_office_' ) );
            $meta[ $meta_key ] = maybe_unserialize( $values[0] );
        }

        return $meta;
    }

    /**
     * Builds address line.
     *
     * @param array<string, mixed> $meta Meta.
     * @return string
     */
    private function format_address( array $meta ): string {
        $parts = array_filter(
            [
                $meta['street'] ?? '',
                $meta['building_number'] ?? '',
                $meta['unit_number'] ?? '',
                $meta['city'] ?? '',
            ],
            static function ( $part ) {
                return '' !== trim( (string) $part );
            }
        );

        return implode( ', ', $parts );
    }

    /**
     * Formats price string.
     *
     * @param array<string, mixed> $meta Meta.
     * @param string               $transaction Transaction type.
     * @return string
     */
    private function format_price( array $meta, string $transaction ): string {
        if ( empty( $meta['price'] ) ) {
            return '';
        }

        $price = number_format_i18n( (float) $meta['price'], 0 ) . ' ' . __( 'PLN', 'estate-office' );
        if ( 'wynajem' === $transaction ) {
            $price .= ' / ' . __( 'miesięcznie', 'estate-office' );
        }

        return $price;
    }

    /**
     * Formats area string.
     *
     * @param array<string, mixed> $meta Meta.
     * @return string
     */
    private function format_area( array $meta ): string {
        if ( empty( $meta['area'] ) ) {
            return '';
        }

        return number_format_i18n( (float) $meta['area'], 2 ) . ' m²';
    }

    /**
     * Formats rooms.
     *
     * @param array<string, mixed> $meta Meta.
     * @return string
     */
    private function format_rooms( array $meta ): string {
        if ( empty( $meta['rooms'] ) ) {
            return '';
        }

        return sprintf( _n( '%s pokój', '%s pokoje', (int) $meta['rooms'], 'estate-office' ), number_format_i18n( (int) $meta['rooms'] ) );
    }

    /**
     * Returns featured media ID for property.
     *
     * @param array<string, mixed> $meta Meta.
     * @return int
     */
    private function get_featured_media( array $meta ): int {
        if ( ! empty( $meta['gallery'] ) ) {
            $ids = $this->parse_gallery_ids( $meta['gallery'] );
            if ( ! empty( $ids ) ) {
                return (int) $ids[0];
            }
        }

        return 0;
    }

    /**
     * Parses comma-separated gallery IDs.
     *
     * @param string $value Value.
     * @return array<int, int>
     */
    private function parse_gallery_ids( string $value ): array {
        $parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );

        return array_map( 'absint', $parts );
    }

    /**
     * Returns first significant flag label.
     *
     * @param array<string, mixed> $meta Meta.
     * @return string
     */
    private function get_primary_flag( array $meta ): string {
        if ( empty( $meta['flags'] ) || ! is_array( $meta['flags'] ) ) {
            return '';
        }

        $flag_map = [
            'new_offer'    => __( 'Nowa oferta', 'estate-office' ),
            'exclusive'    => __( 'Wyłączność', 'estate-office' ),
            'new_price'    => __( 'Nowa cena', 'estate-office' ),
            'premium'      => __( 'Premium', 'estate-office' ),
            'no_commision' => __( 'Bez prowizji', 'estate-office' ),
        ];

        foreach ( $meta['flags'] as $flag ) {
            if ( isset( $flag_map[ $flag ] ) ) {
                return $flag_map[ $flag ];
            }
        }

        return '';
    }

    /**
     * Returns transaction label.
     *
     * @param string $transaction Transaction.
     * @return string
     */
    public function get_transaction_label( string $transaction ): string {
        $labels = [
            'sprzedaz' => __( 'sprzedaż', 'estate-office' ),
            'wynajem'  => __( 'wynajem', 'estate-office' ),
        ];

        return $labels[ $transaction ] ?? $transaction;
    }

    /**
     * Returns property type label.
     *
     * @param string $type Type.
     * @return string
     */
    private function get_property_type_label( string $type ): string {
        $labels = [
            'mieszkanie' => __( 'Mieszkania', 'estate-office' ),
            'dom'        => __( 'Domy', 'estate-office' ),
            'dzialka'    => __( 'Działki', 'estate-office' ),
            'lokal'      => __( 'Lokale użytkowe', 'estate-office' ),
        ];

        return $labels[ $type ] ?? ucfirst( $type );
    }

    /**
     * Returns inline SVG icon.
     *
     * @param string $type Icon type.
     * @return string
     */
    private function get_icon_svg( string $type ): string {
        switch ( $type ) {
            case 'location':
                return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>';
            case 'area':
                return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3a2 2 0 0 0-2 2v3h2V5h3V3H5zm11 0v2h3v3h2V5a2 2 0 0 0-2-2h-3zm-6 8a2 2 0 0 0-2 2v6H4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2h-4v-6a2 2 0 0 0-2-2h-4zm-1 8v-6h6v6h-6z"/></svg>';
            case 'rooms':
                return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v2H4zm2 4h12a2 2 0 0 1 2 2v8h-2v-3h-4v3H8v-3H4v3H2v-8a2 2 0 0 1 2-2z"/></svg>';
            case 'arrow':
                return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 5l7 7-7 7-1.41-1.41L16.17 13H4v-2h12.17l-4.58-4.59z"/></svg>';
            default:
                return '';
        }
    }
}
