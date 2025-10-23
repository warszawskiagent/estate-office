<?php
/**
 * Equipment list for property.
 *
 * @var array<int,string> $equipment_labels
 */
if ( empty( $equipment_labels ) ) {
    return;
}
?>
<section class="estate-office-offer-section">
    <h2><?php esc_html_e( 'Wyposażenie', 'estate-office' ); ?></h2>
    <ul class="estate-office-offer-list">
        <?php foreach ( $equipment_labels as $label ) : ?>
            <li><?php echo esc_html( $label ); ?></li>
        <?php endforeach; ?>
    </ul>
</section>
