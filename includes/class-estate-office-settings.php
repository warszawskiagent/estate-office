<?php
/**
 * Settings management.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Settings
 */
class EstateOffice_Settings {

    /**
     * Register settings related hooks.
     *
     * @return void
     */
    public function register_hooks() {
        add_filter( 'estate_office_admin_template_data', [ $this, 'inject_dynamic_fields' ], 10, 2 );
    }

    /**
     * Inject dynamic fields into templates.
     *
     * @param array  $data     Template data.
     * @param string $template Template name.
     *
     * @return array
     */
    public function inject_dynamic_fields( array $data, $template ) {
        if ( in_array( $template, [ 'admin/agreements', 'admin/properties', 'admin/clients', 'admin/searches' ], true ) ) {
            $data['property_fields']  = get_option( 'estate_office_property_dynamic_fields', [] );
            $data['agreement_fields'] = get_option( 'estate_office_agreement_dynamic_fields', [] );
            $data['client_fields']    = get_option( 'estate_office_client_dynamic_fields', [] );
        }

        return $data;
    }

    /**
     * Retrieve option value.
     *
     * @param string $key     Option key without prefix.
     * @param mixed  $default Default value.
     *
     * @return mixed
     */
    public function get_option( $key, $default = false ) {
        return get_option( 'estate_office_' . $key, $default );
    }
}
