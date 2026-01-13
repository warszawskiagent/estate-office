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

        $agents = new EOC_Agents();
        $agents->register();

        $crm_pages = new EOC_CRM_Pages();
        $crm_pages->register();

        $contracts = new EOC_Contracts();
        $contracts->register();

        $contract_stages = new EOC_Contract_Stages();
        $contract_stages->register();

        $clients = new EOC_Clients();
        $clients->register();

        $properties = new EOC_Properties();
        $properties->register();

        $searches = new EOC_Searches();
        $searches->register();

        EOC_Settings::register();
    }
}
