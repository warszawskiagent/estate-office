<?php
/**
 * Capabilities map for EstateOffice CRM.
 *
 * @package EstateOfficeCRM
 */

namespace EstateOfficeCRM;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for registering and synchronising custom capabilities.
 */
class Capabilities {
    public const ACCESS_DASHBOARD   = 'eo_crm_access_dashboard';
    public const MANAGE_AGENTS      = 'eo_crm_manage_agents';
    public const MANAGE_CLIENTS     = 'eo_crm_manage_clients';
    public const MANAGE_CONTRACTS   = 'eo_crm_manage_contracts';
    public const MANAGE_PROPERTIES  = 'eo_crm_manage_properties';
    public const MANAGE_SEARCHES    = 'eo_crm_manage_searches';
    public const MANAGE_SETTINGS    = 'eo_crm_manage_settings';
    public const DELETE_RECORDS     = 'eo_crm_delete_records';

    /**
     * Capabilities available for administrator role.
     */
    public static function administrator_capabilities(): array {
        return [
            self::ACCESS_DASHBOARD,
            self::MANAGE_AGENTS,
            self::MANAGE_CLIENTS,
            self::MANAGE_CONTRACTS,
            self::MANAGE_PROPERTIES,
            self::MANAGE_SEARCHES,
            self::MANAGE_SETTINGS,
            self::DELETE_RECORDS,
        ];
    }

    /**
     * Capabilities granted to agents.
     */
    public static function agent_capabilities(): array {
        return [
            self::ACCESS_DASHBOARD,
            self::MANAGE_CLIENTS,
            self::MANAGE_CONTRACTS,
            self::MANAGE_PROPERTIES,
            self::MANAGE_SEARCHES,
        ];
    }

    /**
     * Default capabilities array for the agent role.
     */
    public static function agent_role_defaults(): array {
        $caps = [
            'read'           => true,
            'upload_files'   => true,
            'edit_posts'     => false,
            'delete_posts'   => false,
            'publish_posts'  => false,
            'list_users'     => false,
            'promote_users'  => false,
            'delete_users'   => false,
            'create_users'   => false,
            'edit_users'     => false,
            'assign_terms'   => false,
        ];

        foreach ( self::agent_capabilities() as $capability ) {
            $caps[ $capability ] = true;
        }

        return $caps;
    }

    /**
     * Ensure capabilities are synced to administrator and agent roles.
     */
    public static function ensure_roles_have_caps(): void {
        $administrator = get_role( 'administrator' );

        if ( $administrator ) {
            foreach ( self::administrator_capabilities() as $capability ) {
                $administrator->add_cap( $capability );
            }
        }

        $agent_role = get_role( 'estate_agent' );

        if ( $agent_role ) {
            foreach ( self::agent_capabilities() as $capability ) {
                $agent_role->add_cap( $capability );
            }
        }
    }
}
