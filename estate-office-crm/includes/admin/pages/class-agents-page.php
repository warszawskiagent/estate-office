<?php
namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Capabilities;
use EstateOfficeCRM\Database\Repositories\Agents_Repository;

/**
 * Render the agents management view.
 */
class Agents_Page {
    private Agents_Repository $repository;

    private ?array $current_agent = null;

    public function __construct() {
        $this->repository = new Agents_Repository();
    }

    /**
     * Render page.
     */
    public function render(): void {
        $this->handle_delete();
        $this->handle_form_submission();
        $this->current_agent = $this->get_current_agent();
        $current_agent       = $this->current_agent;
        $agents              = $this->repository->all();

        require __DIR__ . '/../views/agents.php';
    }

    /**
     * Process add/update forms.
     */
    private function handle_form_submission(): void {
        if ( ! isset( $_POST['eo_crm_agent_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_crm_agent_nonce'] ) ), 'eo_crm_agent_action' ) ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_AGENTS ) ) {
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

        if ( $agent_id > 0 ) {
            $this->repository->update( $agent_id, $data );
            add_settings_error( 'estate-office-crm-agents', 'updated', __( 'Agent zaktualizowany.', 'estate-office-crm' ), 'updated' );
        } else {
            $this->repository->create( $data );
            add_settings_error( 'estate-office-crm-agents', 'created', __( 'Dodano nowego agenta.', 'estate-office-crm' ), 'updated' );
        }

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    /**
     * Handle delete action.
     */
    private function handle_delete(): void {
        if ( ! isset( $_GET['action'], $_GET['agent'], $_GET['_wpnonce'] ) || 'delete' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_AGENTS ) || ! current_user_can( Capabilities::DELETE_RECORDS ) ) {
            return;
        }

        $agent_id = absint( $_GET['agent'] );

        if ( $agent_id <= 0 ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'eo_crm_delete_agent_' . $agent_id ) ) {
            return;
        }

        $this->repository->delete( $agent_id );
        add_settings_error( 'estate-office-crm-agents', 'deleted', __( 'Agent usunięty.', 'estate-office-crm' ), 'updated' );

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function get_current_agent(): ?array {
        if ( isset( $_GET['action'], $_GET['agent'] ) && 'edit' === $_GET['action'] ) {
            $agent_id = absint( $_GET['agent'] );

            if ( $agent_id > 0 ) {
                return $this->repository->find( $agent_id );
            }
        }

        return null;
    }

    private function get_page_url( array $args = [] ): string {
        $base = admin_url( 'admin.php?page=estate-office-crm-agents' );

        return $args ? add_query_arg( $args, $base ) : $base;
    }

    public function get_current_agent_data(): ?array {
        return $this->current_agent;
    }
}
