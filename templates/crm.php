<?php
/**
 * CRM front-end template.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="estate-office-crm" class="estate-office-crm-view" role="main">
    <?php
    $partial = ESTATE_OFFICE_PLUGIN_DIR . '/public/partials/crm-app.php';

    if ( file_exists( $partial ) ) {
        include $partial;
    }
    ?>
</main>
<?php
get_footer();
