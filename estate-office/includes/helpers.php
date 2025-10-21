<?php
/**
 * Helper functions.
 *
 * @package EstateOffice
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'estateoffice_asset_version' ) ) {
/**
 * Generate asset version based on file modification time.
 *
 * @param string $path Optional relative path for asset.
 * @return string
 */
function estateoffice_asset_version( $path = '' ) {
$version = ESTATEOFFICE_VERSION;

if ( $path ) {
$file_path = ESTATEOFFICE_DIR . ltrim( $path, '/' );
if ( file_exists( $file_path ) ) {
$version = (string) filemtime( $file_path );
}
}

return $version;
}
}
