<?php
/**
 * Additional media links for offer.
 *
 * @var array<string,string> $media_links
 */
$video   = $media_links['video'] ?? '';
$virtual = $media_links['virtual'] ?? '';

if ( ! $video && ! $virtual ) {
    return;
}
?>
<section class="estate-office-offer-media">
    <h2><?php esc_html_e( 'Materiały dodatkowe', 'estate-office' ); ?></h2>
    <div class="estate-office-offer-media-links">
        <?php if ( $video ) : ?>
            <a class="estate-office-button tertiary" href="<?php echo esc_url( $video ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Zobacz film', 'estate-office' ); ?></a>
        <?php endif; ?>
        <?php if ( $virtual ) : ?>
            <a class="estate-office-button tertiary" href="<?php echo esc_url( $virtual ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Wirtualny spacer', 'estate-office' ); ?></a>
        <?php endif; ?>
    </div>
</section>
