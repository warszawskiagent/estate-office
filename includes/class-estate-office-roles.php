<?php
/**
 * Zarządzanie rolami i uprawnieniami EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Roles
 */
class Estate_Office_Roles {

    private const ROLE_AGENT = 'estate_office_agent';

    /**
     * Rejestruje role i podstawowe uprawnienia podczas aktywacji.
     *
     * @return void
     */
    public function register_roles() : void {
        $capabilities = $this->get_agent_capabilities();

        add_role(
            self::ROLE_AGENT,
            __( 'Agent EstateOffice', 'estate-office' ),
            $capabilities
        );

        $this->assign_capabilities_to_admin();
    }

    /**
     * Upewnia się, że role i uprawnienia istnieją przy każdym ładowaniu.
     *
     * @return void
     */
    public function ensure_capabilities() : void {
        if ( ! get_role( self::ROLE_AGENT ) ) {
            add_role(
                self::ROLE_AGENT,
                __( 'Agent EstateOffice', 'estate-office' ),
                $this->get_agent_capabilities()
            );
        }

        $this->assign_capabilities_to_admin();
    }

    /**
     * Zwraca listę uprawnień dla roli agenta.
     *
     * @return array<string,bool>
     */
    private function get_agent_capabilities() : array {
        return [
            'read'                          => true,
            'upload_files'                  => true,
            'manage_estate_office_crm'      => true,
            'access_estate_office_portal'   => true,
            'manage_estate_office_properties' => true,
            'manage_estate_office_clients'  => true,
            'manage_estate_office_contracts' => true,
            'edit_estate_office_records'    => true,
            'view_estate_office_dashboard'  => true,
            'read_estate_office_properties' => true,
            'edit_estate_office_properties' => true,
            'manage_estate_office_searches' => true,
            'read_estate_office_searches'   => true,
            'edit_estate_office_searches'   => true,
            'read_estate_office_clients'    => true,
            'edit_estate_office_clients'    => true,
            'read_estate_office_contracts'  => true,
            'edit_estate_office_contracts'  => true,
        ];
    }

    /**
     * Przypisuje wszystkie uprawnienia administratorowi.
     *
     * @return void
     */
    private function assign_capabilities_to_admin() : void {
        $admin_capabilities = [
            'manage_estate_office_crm',
            'manage_estate_office_agents',
            'manage_estate_office_settings',
            'manage_estate_office_license',
            'manage_estate_office_exports',
            'manage_estate_office_properties',
            'manage_estate_office_searches',
            'manage_estate_office_clients',
            'manage_estate_office_contracts',
            'edit_estate_office_records',
            'delete_estate_office_records',
            'view_estate_office_dashboard',
            'read_estate_office_properties',
            'edit_estate_office_properties',
            'delete_estate_office_properties',
            'read_estate_office_searches',
            'edit_estate_office_searches',
            'delete_estate_office_searches',
            'read_estate_office_clients',
            'edit_estate_office_clients',
            'delete_estate_office_clients',
            'read_estate_office_contracts',
            'edit_estate_office_contracts',
            'delete_estate_office_contracts',
            'access_estate_office_portal',
        ];

        $administrator_role = get_role( 'administrator' );
        if ( ! $administrator_role instanceof WP_Role ) {
            return;
        }

        foreach ( $admin_capabilities as $capability ) {
            if ( ! $administrator_role->has_cap( $capability ) ) {
                $administrator_role->add_cap( $capability );
            }
        }

        $agent_role = get_role( self::ROLE_AGENT );
        if ( $agent_role instanceof WP_Role ) {
            foreach ( $this->get_agent_capabilities() as $capability => $enabled ) {
                if ( $enabled && ! $agent_role->has_cap( $capability ) ) {
                    $agent_role->add_cap( $capability );
                }
            }
        }
    }
}
