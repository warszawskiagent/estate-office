<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<article <?php post_class( 'estate-office-property-card' ); ?>>
    <header>
        <h2 class="estate-office-property-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>
        <p class="estate-office-property-meta">
            <?php echo esc_html( get_post_meta( get_the_ID(), 'estate_office_city', true ) ); ?>
        </p>
    </header>
    <div class="estate-office-property-excerpt">
        <?php the_excerpt(); ?>
    </div>
</article>
