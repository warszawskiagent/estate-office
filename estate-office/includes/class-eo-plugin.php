<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once ESTATEOFFICE_PLUGIN_DIR . 'includes/admin/class-eo-admin-menu.php';
require_once ESTATEOFFICE_PLUGIN_DIR . 'includes/admin/class-eo-settings.php';
require_once ESTATEOFFICE_PLUGIN_DIR . 'includes/frontend/class-eo-frontend.php';

class EstateOffice_Plugin
{
    public function run()
    {
        add_action('init', array($this, 'register_assets'));
        add_action('init', array($this, 'register_shortcodes'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_admin_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function register_assets()
    {
        wp_register_style(
            'estateoffice-frontend',
            ESTATEOFFICE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_register_script(
            'estateoffice-frontend',
            ESTATEOFFICE_PLUGIN_URL . 'assets/js/frontend-crm.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_register_script(
            'estateoffice-admin-settings',
            ESTATEOFFICE_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );
    }

    public function register_shortcodes()
    {
        add_shortcode('estateoffice_crm', array('EstateOffice_Frontend', 'render_crm'));
        add_shortcode('estateoffice_offers', array('EstateOffice_Frontend', 'render_offers'));
    }

    public function register_admin_menu()
    {
        $admin_menu = new EstateOffice_Admin_Menu();
        $admin_menu->register();
    }

    public function register_admin_settings()
    {
        $settings = new EstateOffice_Settings();
        $settings->register();
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'estateoffice-crm_page_estateoffice-settings') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('estateoffice-admin-settings');
    }
}
