<?php
namespace EstateOffice\Admin;

use EstateOffice\Admin\Pages\AboutPage;
use EstateOffice\Admin\Pages\AgentsPage;
use EstateOffice\Admin\Pages\DashboardPage;
use EstateOffice\Admin\Pages\CRMPage;
use EstateOffice\Admin\Pages\ContractWizardPage;
use EstateOffice\Admin\Pages\SettingsPage;
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

    public function __construct() {
        $this->dashboard = new DashboardPage();
        $this->crm       = new CRMPage();
        $this->agents    = new AgentsPage();
        $this->settings  = new SettingsPage();
        $this->about     = new AboutPage();
        $this->license   = new LicensePage();
        $this->wizard    = new ContractWizardPage();
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

        add_action(
            'admin_head',
            static function () {
                remove_submenu_page( 'estate-office-crm', 'estate-office-contract-wizard' );
            }
        );
    }
}
