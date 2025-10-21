<?php
namespace EstateOffice\Admin;

use EstateOffice\Roles;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Agents_Page {
    /**
     * Handle requests and render the agents management page.
     */
    public function render() {
        if ( ! current_user_can( Roles::AGENT_MANAGE_CAP ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do zarządzania agentami.', 'estate-office' ) );
        }

        wp_enqueue_media();

        $message = '';
        $error   = '';

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            check_admin_referer( 'estate_office_agent_action', 'estate_office_agent_nonce' );

            $result = $this->handle_post_request();

            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = $result;
            }
        }

        if ( isset( $_GET['action'], $_GET['agent'] ) && 'delete' === $_GET['action'] ) {
            $result = $this->handle_delete_request( absint( $_GET['agent'] ) );

            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = $result;
            }
        }

        $agent_id   = isset( $_GET['agent'] ) ? absint( $_GET['agent'] ) : 0;
        $agent_data = $agent_id ? $this->get_agent( $agent_id ) : null;

        include __DIR__ . '/views/agents.php';
    }

    /**
     * Process agent add/edit form submissions.
     *
     * @return string|WP_Error
     */
    private function handle_post_request() {
        global $wpdb;

        $agent_id   = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $photo_id   = isset( $_POST['photo_id'] ) ? absint( $_POST['photo_id'] ) : 0;
        $biography  = isset( $_POST['biography'] ) ? wp_kses_post( wp_unslash( $_POST['biography'] ) ) : '';

        if ( empty( $first_name ) || empty( $last_name ) ) {
            return new WP_Error( 'missing_name', __( 'Imię i nazwisko agenta są wymagane.', 'estate-office' ) );
        }

        if ( $email && ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Adres e-mail jest nieprawidłowy.', 'estate-office' ) );
        }

        $table = $wpdb->prefix . 'eo_agents';

        $data = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'phone'      => $phone,
            'email'      => $email,
            'photo_id'   => $photo_id ?: null,
            'biography'  => $biography,
        );

        $formats = array( '%s', '%s', '%s', '%s', '%d', '%s' );

        if ( $agent_id ) {
            $wpdb->update(
                $table,
                $data,
                array( 'id' => $agent_id ),
                $formats,
                array( '%d' )
            );

            return __( 'Agent został zaktualizowany.', 'estate-office' );
        }

        $wpdb->insert(
            $table,
            $data,
            $formats
        );

        return __( 'Agent został dodany.', 'estate-office' );
    }

    /**
     * Handle agent deletion.
     *
     * @param int $agent_id
     *
     * @return string|WP_Error
     */
    private function handle_delete_request( $agent_id ) {
        if ( ! $agent_id ) {
            return new WP_Error( 'invalid_agent', __( 'Nie znaleziono agenta.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_delete_agent_' . $agent_id );

        global $wpdb;

        $deleted = $wpdb->delete( $wpdb->prefix . 'eo_agents', array( 'id' => $agent_id ), array( '%d' ) );

        if ( ! $deleted ) {
            return new WP_Error( 'delete_failed', __( 'Nie udało się usunąć agenta.', 'estate-office' ) );
        }

        return __( 'Agent został usunięty.', 'estate-office' );
    }

    /**
     * Fetch a single agent record.
     *
     * @param int $agent_id
     *
     * @return object|null
     */
    private function get_agent( $agent_id ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eo_agents WHERE id = %d", $agent_id ) );
    }

    /**
     * Fetch all agents.
     *
     * @return array
     */
    public function get_agents() {
        global $wpdb;

        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eo_agents ORDER BY last_name ASC" );
    }
}
