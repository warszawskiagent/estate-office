<?php
/**
 * Contract summary section.
 *
 * @var array<string,mixed> $contract
 */
$rows = $contract['rows'] ?? [];
if ( empty( $rows ) ) {
    return;
}
?>
<section class="estate-office-offer-section">
    <h2><?php esc_html_e( 'Powiązana umowa', 'estate-office' ); ?></h2>
    <dl class="estate-office-info-list">
        <?php foreach ( $rows as $row ) :
            if ( empty( $row['value'] ) ) {
                continue;
            }
            ?>
            <div><dt><?php echo esc_html( $row['label'] ?? '' ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php if ( ! empty( $contract['link'] ) ) : ?>
        <p><a class="estate-office-button tertiary" href="<?php echo esc_url( $contract['link'] ); ?>"><?php esc_html_e( 'Zobacz umowę w CRM', 'estate-office' ); ?></a></p>
    <?php endif; ?>
</section>
