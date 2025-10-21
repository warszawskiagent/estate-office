<?php
namespace EstateOffice\Admin;

use EstateOffice\Admin\Pages\AboutPage;
use EstateOffice\Admin\Pages\AgentsPage;
use EstateOffice\Admin\Pages\DashboardPage;
use EstateOffice\Admin\Pages\CRMPage;
use EstateOffice\Admin\Pages\ContractWizardPage;
use EstateOffice\Admin\Pages\ContractProfilePage;
use EstateOffice\Admin\Pages\PropertyProfilePage;
use EstateOffice\Admin\Pages\ClientProfilePage;
use EstateOffice\Admin\Pages\SearchProfilePage;
use EstateOffice\Admin\Pages\SettingsPage;
use EstateOffice\Admin\Pages\OffersPage;
use EstateOffice\Admin\Pages\LicensePage;

/**
 * Registers admin menu pages.
 */
class Menu {
    private DashboardPage $dashboard;
    private CRMPage $crm;
    private AgentsPage $agents;
    private SettingsPage $settings;
    private AboutPage $about;
    private LicensePage $license;
    private ContractWizardPage $wizard;
    private ContractProfilePage $contract_profile;
    private PropertyProfilePage $property_profile;
    private ClientProfilePage $client_profile;
    private SearchProfilePage $search_profile;
    private OffersPage $offers_page;

    public function __construct() {
        $this->dashboard = new DashboardPage();
        $this->crm       = new CRMPage();
        $this->agents    = new AgentsPage();
        $this->settings  = new SettingsPage();
        $this->about     = new AboutPage();
        $this->license   = new LicensePage();
        $this->wizard    = new ContractWizardPage();
        $this->contract_profile = new ContractProfilePage();
        $this->property_profile = new PropertyProfilePage();
        $this->client_profile   = new ClientProfilePage();
        $this->search_profile   = new SearchProfilePage();
        $this->offers_page      = new OffersPage();
    }

    /**
     * Registers admin menu structure.
     */
    public function register(): void {
        $capability = 'manage_options';

        add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            $capability,
            'estate-office-crm',
            [ $this->dashboard, 'render' ],
            'dashicons-building'
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Pulpit CRM', 'estate-office' ),
            __( 'Pulpit', 'estate-office' ),
            $capability,
            'estate-office-crm',
            [ $this->dashboard, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Panel CRM', 'estate-office' ),
            __( 'CRM', 'estate-office' ),
            $capability,
            'estate-office-crm-panel',
            [ $this->crm, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            $capability,
            'estate-office-agents',
            [ $this->agents, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Oferty', 'estate-office' ),
            __( 'Oferty', 'estate-office' ),
            $capability,
            'estate-office-offers',
            [ $this->offers_page, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            $capability,
            'estate-office-settings',
            [ $this->settings, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            $capability,
            'estate-office-license',
            [ $this->license, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'O wtyczce', 'estate-office' ),
            __( 'About', 'estate-office' ),
            $capability,
            'estate-office-about',
            [ $this->about, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Kreator umów', 'estate-office' ),
            __( 'Kreator umów', 'estate-office' ),
            $capability,
            'estate-office-contract-wizard',
            [ $this->wizard, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Profil umowy', 'estate-office' ),
            __( 'Profil umowy', 'estate-office' ),
            $capability,
            'estate-office-contract-profile',
            [ $this->contract_profile, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Profil nieruchomości', 'estate-office' ),
            __( 'Profil nieruchomości', 'estate-office' ),
            $capability,
            'estate-office-property-profile',
            [ $this->property_profile, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Profil klienta', 'estate-office' ),
            __( 'Profil klienta', 'estate-office' ),
            $capability,
            'estate-office-client-profile',
            [ $this->client_profile, 'render' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Profil poszukiwania', 'estate-office' ),
            __( 'Profil poszukiwania', 'estate-office' ),
            $capability,
            'estate-office-search-profile',
            [ $this->search_profile, 'render' ]
        );

        add_action(
            'admin_head',
            static function () {
                remove_submenu_page( 'estate-office-crm', 'estate-office-contract-wizard' );
                remove_submenu_page( 'estate-office-crm', 'estate-office-contract-profile' );
                remove_submenu_page( 'estate-office-crm', 'estate-office-property-profile' );
                remove_submenu_page( 'estate-office-crm', 'estate-office-client-profile' );
                remove_submenu_page( 'estate-office-crm', 'estate-office-search-profile' );
            }
        );
    }
}
