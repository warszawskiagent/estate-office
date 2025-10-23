<?php
/**
 * Offers catalog layout.
 *
 * @var string                             $brand_badge_html
 * @var string                             $transaction_label
 * @var string                             $transaction
 * @var array<string,array>                $groups
 * @var string                             $filters_markup
 * @var string                             $notary_markup
 * @var string                             $mortgage_markup
 * @var string|null                        $notary_link
 * @var string|null                        $mortgage_link
 */
?>
<div class="estate-office-offers" data-transaction="<?php echo esc_attr( $transaction ); ?>">
    <div class="estate-office-offers-header">
        <?php echo $brand_badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <h2><?php printf( esc_html__( 'Oferty – %s', 'estate-office' ), esc_html( $transaction_label ) ); ?></h2>
    </div>
    <?php if ( ! empty( $filters_markup ) ) : ?>
        <?php echo $filters_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>
    <?php foreach ( $groups as $type => $cities ) : ?>
        <section class="estate-office-offers-section">
            <h3><?php echo esc_html( $type ); ?></h3>
            <?php foreach ( $cities as $city => $districts ) : ?>
                <div class="estate-office-offers-city">
                    <h4><?php echo esc_html( $city ); ?></h4>
                    <?php foreach ( $districts as $district => $items ) : ?>
                        <div class="estate-office-offers-district">
                            <?php if ( $district ) : ?>
                                <h5><?php echo esc_html( $district ); ?></h5>
                            <?php endif; ?>
                            <div class="estate-office-offers-grid">
                                <?php foreach ( $items as $item ) :
                                    $crm_link  = add_query_arg(
                                        [
                                            'eo_tab'      => 'properties',
                                            'eo_property' => (int) $item['id'],
                                        ],
                                        estate_office_get_crm_page_url()
                                    );
                                    $target_url   = ! empty( $item['page_url'] ) ? $item['page_url'] : $crm_link;
                                    $button_label = ! empty( $item['page_url'] )
                                        ? __( 'Zobacz ofertę', 'estate-office' )
                                        : __( 'Szczegóły w CRM', 'estate-office' );
                                    ?>
                                    <article class="estate-office-offer-card">
                                        <?php if ( ! empty( $item['image_url'] ) ) : ?>
                                            <figure class="estate-office-offer-image">
                                                <img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ); ?>" loading="lazy" />
                                            </figure>
                                        <?php endif; ?>
                                        <header>
                                            <span class="estate-office-offer-number"><?php echo esc_html( sprintf( '#%05d', $item['id'] ) ); ?></span>
                                            <?php if ( ! empty( $item['tags'] ) ) : ?>
                                                <ul class="estate-office-offer-tags">
                                                    <?php foreach ( $item['tags'] as $tag ) : ?>
                                                        <li><?php echo esc_html( $tag ); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </header>
                                        <p class="estate-office-offer-address"><?php echo esc_html( $item['address'] ); ?></p>
                                        <p class="estate-office-offer-price"><?php echo esc_html( $item['price'] ? number_format_i18n( (float) $item['price'], 2 ) . ' PLN' : '' ); ?></p>
                                        <ul class="estate-office-offer-meta">
                                            <?php if ( $item['area'] ) : ?>
                                                <li><?php echo esc_html( $item['area'] . ' m²' ); ?></li>
                                            <?php endif; ?>
                                            <?php if ( $item['rooms'] ) : ?>
                                                <li><?php echo esc_html( sprintf( _n( '%d pokój', '%d pokoje', (int) $item['rooms'], 'estate-office' ), (int) $item['rooms'] ) ); ?></li>
                                            <?php endif; ?>
                                            <?php if ( $item['price_m2'] ) : ?>
                                                <li><?php echo esc_html( sprintf( __( '%s / m²', 'estate-office' ), number_format_i18n( (float) $item['price_m2'], 2 ) . ' PLN' ) ); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                        <a class="estate-office-button secondary" href="<?php echo esc_url( $target_url ); ?>"><?php echo esc_html( $button_label ); ?></a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>
<section class="estate-office-offers-calculators" aria-label="<?php esc_attr_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?>">
    <h2><?php esc_html_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?></h2>
    <div class="estate-office-offers-calculators-grid">
        <?php echo $notary_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php echo $mortgage_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php if ( $notary_link || $mortgage_link ) : ?>
        <p class="estate-office-offers-calculators-links">
            <?php if ( $notary_link ) : ?>
                <a class="estate-office-button tertiary" href="<?php echo esc_url( $notary_link ); ?>"><?php esc_html_e( 'Pełny kalkulator notarialny', 'estate-office' ); ?></a>
            <?php endif; ?>
            <?php if ( $mortgage_link ) : ?>
                <a class="estate-office-button tertiary" href="<?php echo esc_url( $mortgage_link ); ?>"><?php esc_html_e( 'Pełny kalkulator kredytowy', 'estate-office' ); ?></a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
