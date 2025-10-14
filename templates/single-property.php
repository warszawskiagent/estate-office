<?php
/**
 * Template for single property.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main estate-office-single-property">
    <?php
    while ( have_posts() ) :
        the_post();
        include ESTATE_OFFICE_PLUGIN_DIR . '/public/partials/property-card.php';
        the_content();
    endwhile;
    ?>
</main>
<?php
get_footer();
