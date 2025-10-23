<?php
/**
 * Generic info sections rendered as definition lists.
 *
 * @var array<int,array<string,mixed>> $sections
 */
if ( empty( $sections ) ) {
    return;
}
?>
<?php foreach ( $sections as $section ) :
    $rows = $section['rows'] ?? [];
    if ( empty( $rows ) ) {
        continue;
    }
    ?>
    <section class="estate-office-offer-section">
        <h2><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
        <dl class="estate-office-info-list">
            <?php foreach ( $rows as $row ) :
                if ( empty( $row['value'] ) ) {
                    continue;
                }
                ?>
                <div><dt><?php echo esc_html( $row['label'] ?? '' ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </section>
<?php endforeach; ?>
