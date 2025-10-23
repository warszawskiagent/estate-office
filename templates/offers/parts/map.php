<?php
/**
 * Google Maps container for offer.
 *
 * @var array<string,mixed> $map
 */
if ( empty( $map['enabled'] ) ) {
    return;
}
?>
<section class="estate-office-offer-map-section">
    <h2><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></h2>
    <div
        id="estate-office-offer-map"
        class="estate-office-offer-map"
        data-lat="<?php echo esc_attr( $map['lat'] ?? '' ); ?>"
        data-lng="<?php echo esc_attr( $map['lng'] ?? '' ); ?>"
        data-title="<?php echo esc_attr( $map['title'] ?? '' ); ?>"
        data-zoom="<?php echo esc_attr( isset( $map['zoom'] ) ? (int) $map['zoom'] : 15 ); ?>"
        aria-label="<?php esc_attr_e( 'Mapa nieruchomości', 'estate-office' ); ?>"
    ></div>
</section>
