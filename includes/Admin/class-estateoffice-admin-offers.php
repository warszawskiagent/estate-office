<?php
/**
 * Admin page for exported offers.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Offers extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-offers';

    public function __construct( string $parent_slug = '' ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Oferty', 'estate-office' );
        $this->page_title = __( 'Oferty eksportowane na WWW', 'estate-office' );
        $this->capability = 'eo_manage_properties';
    }

    public function render(): void {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $offers = self::get_exported_properties( $search );
        ?>
        <div class="wrap estate-office-offers-page">
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $this->page_title ); ?></h1>
            </div>
            <?php $this->render_notice(); ?>
            <p class="description">
                <?php esc_html_e( 'Lista prezentuje nieruchomości oznaczone do eksportu na stronę WWW wraz z wygenerowanymi stronami ofert.', 'estate-office' ); ?>
            </p>
            <form method="get" class="estate-office-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <label for="estate-office-offer-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj ofert', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-offer-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Wyszukaj po mieście, rodzaju lub tytule strony', 'estate-office' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>
            <form method="post" class="estate-office-offers-sync-all" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'estate_office_sync_offers' ); ?>
                <input type="hidden" name="action" value="estate_office_sync_offers" />
                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Zsynchronizuj wszystkie oferty', 'estate-office' ); ?></button>
            </form>
            <table class="widefat striped estate-office-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Oferta', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Transakcja', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Strona WWW', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Kategorie', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Ostatnia aktualizacja', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $offers ) ) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'Brak nieruchomości oznaczonych do eksportu.', 'estate-office' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $offers as $offer ) :
                            $address = $offer->address ? json_decode( $offer->address, true ) : [];
                            $details = $offer->details ? json_decode( $offer->details, true ) : [];
                            $tags    = $offer->tags ? json_decode( $offer->tags, true ) : [];

                            $page_id   = (int) ( $offer->page_id ?? 0 );
                            $page_link = $page_id ? get_permalink( $page_id ) : '';
                            $edit_link = $page_id ? get_edit_post_link( $page_id ) : '';
                            $page_title = $page_id ? get_the_title( $page_id ) : '';
                            $page_status = $page_id ? get_post_status_object( get_post_status( $page_id ) ) : null;
                            $categories = $page_id ? wp_get_post_terms( $page_id, 'estate_office_offer_category', [ 'fields' => 'names' ] ) : [];
                            $tag_labels = estate_office_map_offer_tag_labels( is_array( $tags ) ? $tags : [] );
                            $address_display = estate_office_format_address( $address );
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( sprintf( '#%05d', (int) $offer->id ) ); ?></strong><br />
                                    <span class="description"><?php echo esc_html( $offer->property_type ); ?></span>
                                    <?php if ( ! empty( $tag_labels ) ) : ?>
                                        <ul class="estate-office-offer-tags">
                                            <?php foreach ( $tag_labels as $label ) : ?>
                                                <li><?php echo esc_html( $label ); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( estate_office_get_transaction_label( (string) $offer->transaction_type ) ); ?></td>
                                <td>
                                    <?php echo esc_html( $address_display ); ?><br />
                                    <?php if ( ! empty( $details['area'] ) ) : ?>
                                        <span class="description"><?php echo esc_html( number_format_i18n( (float) $details['area'], 2 ) . ' m²' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $page_id && $page_link ) : ?>
                                        <a href="<?php echo esc_url( $page_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_title ); ?></a>
                                        <?php if ( $page_status ) : ?>
                                            <span class="description">(<?php echo esc_html( $page_status->label ); ?>)</span>
                                        <?php endif; ?>
                                        <?php if ( $edit_link ) : ?>
                                            <div><a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edytuj stronę', 'estate-office' ); ?></a></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="description"><?php esc_html_e( 'Brak wygenerowanej strony', 'estate-office' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $categories ) ) : ?>
                                        <ul>
                                            <?php foreach ( $categories as $category ) : ?>
                                                <li><?php echo esc_html( $category ); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <span class="description">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $offer->updated_at ) ); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'estate_office_sync_offer' ); ?>
                                        <input type="hidden" name="action" value="estate_office_sync_offer" />
                                        <input type="hidden" name="property_id" value="<?php echo esc_attr( (int) $offer->id ); ?>" />
                                        <button type="submit" class="button button-small"><?php esc_html_e( 'Odśwież stronę', 'estate-office' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected function render_notice(): void {
        if ( empty( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $status = sanitize_key( wp_unslash( $_GET['status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'synced' === $status ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Synchronizacja ofert zakończona.', 'estate-office' ) . '</p></div>';
        } elseif ( 'error' === $status ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Nie udało się zsynchronizować oferty.', 'estate-office' ) . '</p></div>';
        }
    }

    /**
     * Fetch exported offers with related page data.
     *
     * @return array<int,object>
     */
    public static function get_exported_properties( string $search = '' ): array {
        global $wpdb;

        $properties_table = $wpdb->prefix . 'eo_properties';
        $posts_table      = $wpdb->posts;

        $where = "WHERE p.export_www = 1";
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare(
                " AND (pg.post_title LIKE %s OR p.property_type LIKE %s OR p.transaction_type LIKE %s OR JSON_EXTRACT(p.address, '$.city') LIKE %s OR JSON_EXTRACT(p.address, '$.street') LIKE %s)",
                $like,
                $like,
                $like,
                $like,
                $like
            );
        }

        $sql = "SELECT p.*, pg.ID AS page_id, pg.post_title, pg.post_status
                FROM {$properties_table} p
                LEFT JOIN {$posts_table} pg ON pg.ID = p.export_page_id
                {$where}
                ORDER BY p.updated_at DESC";

        return $wpdb->get_results( $sql );
    }
}
