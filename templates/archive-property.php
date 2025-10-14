<?php
/**
 * Archive template for properties.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main estate-office-archive-property">
    <?php if ( have_posts() ) : ?>
        <header class="page-header">
            <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
        </header>
        <?php
        while ( have_posts() ) :
            the_post();
            include ESTATE_OFFICE_PLUGIN_DIR . '/public/partials/property-card.php';
        endwhile;

        the_posts_navigation();
        ?>
    <?php else : ?>
        <p><?php esc_html_e( 'Brak nieruchomości do wyświetlenia.', 'estate-office' ); ?></p>
    <?php endif; ?>
</main>
<?php
get_footer();
