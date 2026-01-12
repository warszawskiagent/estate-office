<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Plugin {
    public function run(): void {
        register_activation_hook(EOC_PLUGIN_BASENAME, array('EOC_Activator', 'activate'));
        register_deactivation_hook(EOC_PLUGIN_BASENAME, array('EOC_Activator', 'deactivate'));

        $menu = new EOC_Admin_Menu();
        $menu->register();

        $crm_pages = new EOC_CRM_Pages();
        $crm_pages->register();

        $contracts = new EOC_Contracts();
        $contracts->register();

        $clients = new EOC_Clients();
        $clients->register();

        $properties = new EOC_Properties();
        $properties->register();

        EOC_Settings::register();
    }
}
