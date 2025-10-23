<?php
/**
 * Calculators widget section.
 *
 * @var string      $notary_markup
 * @var string      $mortgage_markup
 * @var string|null $notary_link
 * @var string|null $mortgage_link
 */
if ( ! $notary_markup && ! $mortgage_markup ) {
    return;
}
?>
<section class="estate-office-offer-calculators" aria-label="<?php esc_attr_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?>">
    <h2><?php esc_html_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?></h2>
    <div class="estate-office-offer-calculators-grid">
        <?php if ( $notary_markup ) : ?>
            <?php echo wp_kses_post( $notary_markup ); ?>
        <?php endif; ?>
        <?php if ( $mortgage_markup ) : ?>
            <?php echo wp_kses_post( $mortgage_markup ); ?>
        <?php endif; ?>
    </div>
    <?php if ( $notary_link || $mortgage_link ) : ?>
        <p class="estate-office-offer-calculators-links">
            <?php if ( $notary_link ) : ?>
                <a class="estate-office-button tertiary" href="<?php echo esc_url( $notary_link ); ?>"><?php esc_html_e( 'Pełny kalkulator notarialny', 'estate-office' ); ?></a>
            <?php endif; ?>
            <?php if ( $mortgage_link ) : ?>
                <a class="estate-office-button tertiary" href="<?php echo esc_url( $mortgage_link ); ?>"><?php esc_html_e( 'Pełny kalkulator kredytowy', 'estate-office' ); ?></a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
