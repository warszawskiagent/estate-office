<?php
namespace EstateOffice\Setup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin activation.
 */
class Activator {
    /**
     * Runs on plugin activation.
     */
    public static function activate(): void {
        self::create_roles();
        self::flush_rewrite();
    }

    /**
     * Create custom roles and capabilities.
     */
    private static function create_roles(): void {
        $capabilities = [
            'read'                   => true,
            'edit_posts'             => false,
            'delete_posts'           => false,
            'publish_posts'          => false,
            'upload_files'           => true,
            'edit_estate_items'      => true,
            'read_estate_items'      => true,
            'assign_estate_items'    => true,
        ];

        add_role( 'estate_agent', __( 'Agent nieruchomości', 'estate-office' ), $capabilities );
    }

    /**
     * Flush rewrite rules after registering post types.
     */
    private static function flush_rewrite(): void {
        // Register post types before flushing.
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/post-types/class-registrar.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-listings.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-agents.php';

        $registrar = new \EstateOffice\Post_Types\Registrar();
        $registrar->register_post_types();
        \EstateOffice\Frontend\Listings::add_rewrite_rules();
        \EstateOffice\Frontend\Agents::add_rewrite_rules();
        flush_rewrite_rules();
    }
}
