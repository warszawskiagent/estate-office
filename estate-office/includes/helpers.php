<?php
namespace EstateOffice;

/**
 * Get asset version based on file modification time.
 *
 * @param string $relative_path Relative path inside plugin directory.
 *
 * @return string
 */
function estate_office_asset_version( $relative_path ) {
$path = ESTATE_OFFICE_PATH . $relative_path;

if ( file_exists( $path ) ) {
return (string) filemtime( $path );
}

return ESTATE_OFFICE_VERSION;
}

/**
 * Safely retrieve a value from array.
 *
 * @param array  $array Source array.
 * @param string $key   Key.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function array_get( array $array, $key, $default = null ) {
return isset( $array[ $key ] ) ? $array[ $key ] : $default;
}
