<?php
namespace EstateOffice;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roles {
    const CAPABILITY = 'manage_estate_office_crm';
    const AGENT_MANAGE_CAP = 'eo_manage_agents';

    /**
     * Add roles and capabilities required by the plugin.
     */
    public static function add_roles() {
        $agent_caps = array(
            'read'                 => true,
            'upload_files'         => true,
            self::CAPABILITY       => true,
            self::AGENT_MANAGE_CAP => false,
        );

        $role = get_role( 'estate_agent' );

        if ( ! $role ) {
            add_role( 'estate_agent', __( 'Estate Agent', 'estate-office' ), $agent_caps );
        } else {
            foreach ( $agent_caps as $capability => $grant ) {
                if ( $grant ) {
                    $role->add_cap( $capability );
                } else {
                    $role->remove_cap( $capability );
                }
            }
        }

        $administrator = get_role( 'administrator' );

        if ( $administrator ) {
            $administrator->add_cap( self::CAPABILITY );
            $administrator->add_cap( self::AGENT_MANAGE_CAP );
        }
    }

    /**
     * Remove plugin specific capabilities when plugin is deactivated.
     */
    public static function remove_caps() {
        $administrator = get_role( 'administrator' );

        if ( $administrator ) {
            $administrator->remove_cap( self::CAPABILITY );
            $administrator->remove_cap( self::AGENT_MANAGE_CAP );
        }

        $agent = get_role( 'estate_agent' );

        if ( $agent ) {
            $agent->remove_cap( self::CAPABILITY );
            $agent->remove_cap( self::AGENT_MANAGE_CAP );
        }
    }
}
