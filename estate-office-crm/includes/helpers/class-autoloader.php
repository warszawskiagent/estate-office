<?php
/**
 * Simple PSR-4 autoloader for the plugin.
 *
 * @package EstateOfficeCRM\Helpers
 */

namespace EstateOfficeCRM;

defined( 'ABSPATH' ) || exit;

/**
 * Autoload plugin classes.
 */
class Autoloader {
    /**
     * Namespace prefix for classes.
     */
    private const PREFIX = 'EstateOfficeCRM\\';

    /**
     * Base directory for class files.
     */
    private const BASE_DIR = __DIR__ . '/../';

    /**
     * Initialise the autoloader.
     */
    public static function init(): void {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Attempt to load the requested class.
     *
     * @param string $class Class to load.
     */
    private static function autoload( string $class ): void {
        if ( str_starts_with( $class, self::PREFIX ) ) {
            $relative = substr( $class, strlen( self::PREFIX ) );
            $parts    = explode( '\\', $relative );
            $parts    = array_map(
                static function ( string $segment ): string {
                    return str_replace( '_', '-', strtolower( $segment ) );
                },
                $parts
            );

            $filename = 'class-' . array_pop( $parts ) . '.php';
            $path     = self::BASE_DIR . ( $parts ? implode( '/', $parts ) . '/' : '' ) . $filename;

            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }
}
