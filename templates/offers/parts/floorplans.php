<?php
/**
 * Floor plan links section.
 *
 * @var array<int,array<string,string>> $floor_plans
 */
if ( empty( $floor_plans ) ) {
    return;
}
?>
<section class="estate-office-offer-floorplans">
    <h2><?php esc_html_e( 'Rzuty', 'estate-office' ); ?></h2>
    <ul>
        <?php foreach ( $floor_plans as $plan ) :
            $url = $plan['url'] ?? '';
            if ( ! $url ) {
                continue;
            }
            ?>
            <li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $plan['label'] ?? '' ); ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
