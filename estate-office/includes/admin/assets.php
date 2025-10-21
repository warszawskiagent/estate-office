<?php
/**
 * Admin assets loader.
 *
 * @package EstateOffice\Admin
 */

namespace EstateOffice\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles enqueuing admin assets.
 */
class Assets {
/**
 * Enqueue admin scripts and styles.
 */
public static function enqueue_admin_assets( $hook ) {
if ( false === strpos( $hook, 'estateoffice' ) ) {
return;
}

wp_enqueue_media();

wp_enqueue_style(
'estateoffice-admin',
ESTATEOFFICE_URL . 'assets/css/admin.css',
[],
estateoffice_asset_version( 'assets/css/admin.css' )
);

wp_enqueue_script(
'estateoffice-admin',
ESTATEOFFICE_URL . 'assets/js/admin.js',
[ 'jquery', 'wp-util' ],
estateoffice_asset_version( 'assets/js/admin.js' ),
true
);

wp_localize_script(
'estateoffice-admin',
'estateOfficeAdmin',
[
'i18n' => [
'addField'   => __( 'Dodaj pole', 'estateoffice' ),
'removeField' => __( 'Usuń', 'estateoffice' ),
'noFile'     => __( 'Brak pliku', 'estateoffice' ),
],
]
);
}
}
