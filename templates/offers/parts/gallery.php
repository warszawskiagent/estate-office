<?php
/**
 * Gallery grid for offer images.
 *
 * @var array<int,array<string,string>> $gallery
 */
if ( empty( $gallery ) ) {
    return;
}
?>
<section class="estate-office-offer-gallery" aria-label="<?php esc_attr_e( 'Galeria nieruchomości', 'estate-office' ); ?>">
    <div class="estate-office-offer-gallery-grid">
        <?php foreach ( $gallery as $image ) :
            $url  = $image['url'] ?? '';
            $full = $image['full'] ?? $url;
            if ( ! $url ) {
                continue;
            }
            ?>
            <figure>
                <a href="<?php echo esc_url( $full ); ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>" loading="lazy" />
                </a>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
