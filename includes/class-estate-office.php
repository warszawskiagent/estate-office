<?php
/**
 * Core plugin class.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

use EstateOffice\Admin\Admin;
use EstateOffice\PublicSite\Frontend;
use EstateOffice\Rest\Rest_API;
use EstateOffice\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class responsible for bootstrapping functionality.
 */
class Estate_Office {
    /**
     * Initialize class dependencies.
     *
     * @return void
     */
    public function run(): void {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_rest_hooks();
        $this->init_security_layers();
    }

    /**
     * Loads required files.
     *
     * @return void
     */
    private function load_dependencies(): void {
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/class-i18n.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/class-security.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/admin/class-admin.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/public/class-frontend.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/database/class-db.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/database/class-migrations.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/models/class-client.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/models/class-contract.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/models/class-property.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/models/class-search.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/controllers/class-client-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/controllers/class-contract-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/controllers/class-property-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/controllers/class-search-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/rest/class-base-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/rest/class-rest-api.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/rest/class-properties-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/rest/class-clients-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/rest/class-contracts-controller.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/rest/class-searches-controller.php';
    }

    /**
     * Load plugin textdomain.
     *
     * @return void
     */
    private function set_locale(): void {
        $i18n = new I18n();
        add_action( 'plugins_loaded', [ $i18n, 'load_textdomain' ] );
    }

    /**
     * Register admin specific hooks.
     *
     * @return void
     */
    private function define_admin_hooks(): void {
        $admin = new Admin();
        add_action( 'admin_menu', [ $admin, 'register_menu_pages' ] );
        add_action( 'admin_init', [ $admin, 'register_settings' ] );
    }

    /**
     * Register frontend hooks.
     *
     * @return void
     */
    private function define_public_hooks(): void {
        $frontend = new Frontend();
        add_action( 'init', [ $frontend, 'register_post_types' ] );
        add_action( 'init', [ $frontend, 'register_rewrite_tags' ] );
        add_action( 'init', [ $frontend, 'register_rewrite_rules' ] );
        add_filter( 'query_vars', [ $frontend, 'register_query_vars' ] );
        add_filter( 'template_include', [ $frontend, 'maybe_use_crm_template' ] );
        add_filter( 'single_template', [ $frontend, 'filter_single_property_template' ] );
        add_filter( 'archive_template', [ $frontend, 'filter_archive_property_template' ] );
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    private function define_rest_hooks(): void {
        $rest = new Rest_API();
        add_action( 'rest_api_init', [ $rest, 'register_routes' ] );
    }

    /**
     * Boot security layer.
     *
     * @return void
     */
    private function init_security_layers(): void {
        $security = new Security();
        add_action( 'send_headers', [ $security, 'register_content_security_policy' ] );
        add_filter( 'upload_mimes', [ $security, 'restrict_mime_types' ] );
        add_filter( 'wp_handle_upload_prefilter', [ $security, 'validate_upload' ] );
    }
}
