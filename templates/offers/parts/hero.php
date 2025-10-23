<?php
/**
 * Offer hero section with badges and highlights.
 *
 * @var string                            $brand_badge_html
 * @var string                            $transaction_label
 * @var string                            $title
 * @var array<int,string>                 $tag_labels
 * @var string                            $address_text
 * @var array<int,array<string,string>>   $highlights
 */
?>
<header class="estate-office-offer-hero">
    <div class="estate-office-offer-hero-meta">
        <?php if ( ! empty( $brand_badge_html ) ) : ?>
            <?php echo $brand_badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
        <?php if ( ! empty( $transaction_label ) ) : ?>
            <p class="estate-office-offer-transaction"><?php echo esc_html( $transaction_label ); ?></p>
        <?php endif; ?>
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php if ( ! empty( $tag_labels ) ) : ?>
            <ul class="estate-office-offer-tags">
                <?php foreach ( $tag_labels as $label ) : ?>
                    <li><?php echo esc_html( $label ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ( ! empty( $address_text ) ) : ?>
            <p class="estate-office-offer-address"><?php echo esc_html( $address_text ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $highlights ) ) : ?>
            <dl class="estate-office-offer-highlights">
                <?php foreach ( $highlights as $row ) :
                    if ( empty( $row['value'] ) ) {
                        continue;
                    }
                    ?>
                    <div><dt><?php echo esc_html( $row['label'] ?? '' ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </div>
</header>
