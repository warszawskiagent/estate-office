<?php

declare(strict_types=1);

namespace EstateOffice;

defined('ABSPATH') || exit;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\Admin\Dashboard;
use EstateOffice\Admin\Menu;
use EstateOffice\Roles\Manager as RolesManager;
use EstateOffice\Settings\GeneralSettings;
use EstateOffice\Admin\Pages\SettingsPage;
use EstateOffice\Admin\Pages\AgentsPage;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\AgreementMeta;
use EstateOffice\PostTypes\PropertyColumns;
use EstateOffice\PostTypes\AgreementColumns;
use EstateOffice\PostTypes\SearchRegister;
use EstateOffice\PostTypes\SearchMeta;
use EstateOffice\PostTypes\SearchColumns;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\ClientMeta;
use EstateOffice\PostTypes\ClientColumns;
use EstateOffice\PostTypes\RelationCleanup;
use EstateOffice\PostTypes\LeadRegister;
use EstateOffice\PostTypes\LeadMeta;
use EstateOffice\PostTypes\LeadColumns;
use EstateOffice\PostTypes\ManagerFilters;
use EstateOffice\Frontend\AgentPublic;
use EstateOffice\Frontend\AgentDirectory;
use EstateOffice\Frontend\CRM;
use EstateOffice\Frontend\AgreementCreator;
use EstateOffice\Frontend\LeadActions;
use EstateOffice\Frontend\Offers;
use EstateOffice\Frontend\OfferSingle;
use EstateOffice\Frontend\ContactForms;
use EstateOffice\Frontend\Maps;
use EstateOffice\Leads\LeadCollector;
use EstateOffice\Leads\LeadNotifications;
use EstateOffice\Leads\LeadReminders;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct()
    {
        $this->boot();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function boot(): void
    {
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [RolesManager::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [PropertyRegister::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [PropertyMeta::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [AgreementRegister::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [SearchRegister::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [ClientRegister::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [LeadRegister::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [LeadMeta::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [LeadReminders::class, 'activate']);
        register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, [AgentPublic::class, 'activate']);
        register_deactivation_hook(ESTATE_OFFICE_PLUGIN_FILE, [RolesManager::class, 'deactivate']);
        register_deactivation_hook(ESTATE_OFFICE_PLUGIN_FILE, [PropertyMeta::class, 'deactivate']);
        register_deactivation_hook(ESTATE_OFFICE_PLUGIN_FILE, [AgentPublic::class, 'deactivate']);
        register_deactivation_hook(ESTATE_OFFICE_PLUGIN_FILE, [LeadReminders::class, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [RolesManager::class, 'register']);
        PropertyRegister::bootstrap();
        PropertyMeta::bootstrap();
        PropertyColumns::bootstrap();
        AgreementRegister::bootstrap();
        AgreementMeta::bootstrap();
        AgreementColumns::bootstrap();
        SearchRegister::bootstrap();
        SearchMeta::bootstrap();
        SearchColumns::bootstrap();
        ClientRegister::bootstrap();
        ClientMeta::bootstrap();
        ClientColumns::bootstrap();
        LeadRegister::bootstrap();
        LeadMeta::bootstrap();
        LeadColumns::bootstrap();
        RelationCleanup::bootstrap();
        ManagerFilters::bootstrap();
        AgentProfile::bootstrap();
        Dashboard::bootstrap();
        CRM::bootstrap();
        AgreementCreator::bootstrap();
        LeadActions::bootstrap();
        Offers::bootstrap();
        OfferSingle::bootstrap();
        AgentDirectory::bootstrap();
        AgentPublic::bootstrap();
        ContactForms::bootstrap();
        LeadCollector::bootstrap();
        LeadNotifications::bootstrap();
        LeadReminders::bootstrap();
        Maps::bootstrap();
        add_action('admin_menu', [Menu::class, 'register']);
        add_action('admin_init', [GeneralSettings::class, 'register']);
        add_action('admin_enqueue_scripts', [SettingsPage::class, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [AgentsPage::class, 'enqueueAssets']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('estate-office', false, dirname(plugin_basename(ESTATE_OFFICE_PLUGIN_FILE)) . '/languages');
    }
}
