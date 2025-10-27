<?php
/**
 * Agents management page.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Render the agents management view.
 */
class Agents_Page {
    /**
     * Render page.
     */
    public function render(): void {
        $this->handle_form_submission();
        $agents = $this->get_agents();
        require __DIR__ . '/../views/agents.php';
    }

    /**
     * Process add/update forms.
     */
    private function handle_form_submission(): void {
        if ( ! isset( $_POST['eo_crm_agent_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_crm_agent_nonce'] ) ), 'eo_crm_agent_action' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $agent_id    = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $first_name  = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name   = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $phone       = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $avatar_id   = isset( $_POST['avatar_id'] ) ? absint( $_POST['avatar_id'] ) : 0;
        $biography   = wp_kses_post( wp_unslash( $_POST['biography'] ?? '' ) );
        $address1    = sanitize_text_field( wp_unslash( $_POST['address_line1'] ?? '' ) );
        $address2    = sanitize_text_field( wp_unslash( $_POST['address_line2'] ?? '' ) );
        $city        = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
        $postal_code = sanitize_text_field( wp_unslash( $_POST['postal_code'] ?? '' ) );
        $country     = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );

        if ( empty( $first_name ) || empty( $last_name ) ) {
            add_settings_error( 'estate-office-crm-agents', 'missing_name', __( 'Imię i nazwisko agenta są wymagane.', 'estate-office-crm' ) );
            return;
        }

        global $wpdb;

        $data = [
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'email'         => $email,
            'phone'         => $phone,
            'avatar_id'     => $avatar_id,
            'biography'     => $biography,
            'address_line1' => $address1,
            'address_line2' => $address2,
            'city'          => $city,
            'postal_code'   => $postal_code,
            'country'       => $country,
        ];

        $table = $wpdb->prefix . 'eo_agents';

        if ( $agent_id > 0 ) {
            $wpdb->update( $table, $data, [ 'id' => $agent_id ] );
            add_settings_error( 'estate-office-crm-agents', 'updated', __( 'Agent zaktualizowany.', 'estate-office-crm' ), 'updated' );
        } else {
            $wpdb->insert( $table, $data );
            add_settings_error( 'estate-office-crm-agents', 'created', __( 'Dodano nowego agenta.', 'estate-office-crm' ), 'updated' );
        }
    }

    /**
     * Fetch registered agents.
     */
    private function get_agents(): array {
        global $wpdb;

        return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'eo_agents ORDER BY last_name ASC' );
    }
}
