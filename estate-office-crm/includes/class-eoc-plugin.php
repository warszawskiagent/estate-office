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

        EOC_Settings::register();
    }
}
