<?php

declare(strict_types=1);

namespace EstateOffice;

defined('ABSPATH') || exit;

/**
 * Simple PSR-4 compatible autoloader for the plugin.
 */
final class Autoloader
{
    private const PREFIX = __NAMESPACE__ . '\\';

    private function __construct()
    {
    }

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (str_starts_with($class, self::PREFIX) === false) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $relative = str_replace('\\', '/', $relative);
        $path     = ESTATE_OFFICE_PLUGIN_DIR . 'includes/' . strtolower($relative) . '.php';

        if (is_readable($path)) {
            require_once $path;
        }
    }
}
