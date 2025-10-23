<?php
/**
 * Long description of property.
 *
 * @var string|null $description
 */
if ( ! $description ) {
    return;
}
?>
<section class="estate-office-offer-description">
    <h2><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></h2>
    <div class="estate-office-offer-description-content"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
</section>
