<?php
namespace EstateOffice;

/**
 * Plugin deactivation handler.
 */
class Deactivator {
/**
 * Execute deactivation routine.
 *
 * @return void
 */
public static function deactivate() {
flush_rewrite_rules();
}
}
