<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('eocrm_daily_cleanup');
delete_option('eocrm_version');
delete_option('eocrm_page_ids');
delete_option('eocrm_default_agent_user_id');
delete_transient('eocrm_new_agent_credentials');
