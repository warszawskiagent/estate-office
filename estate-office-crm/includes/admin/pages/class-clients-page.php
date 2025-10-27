<?php
/**
 * Clients management page controller.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Capabilities;
use EstateOfficeCRM\Database\Repositories\Clients_Repository;

/**
 * Handle client CRUD in admin.
 */
class Clients_Page {
    private Clients_Repository $repository;

    private ?array $current_client = null;

    public function __construct() {
        $this->repository = new Clients_Repository();
    }

    public function render(): void {
        $this->handle_delete();
        $this->handle_form_submission();
        $this->current_client = $this->get_current_client();
        $current_client       = $this->current_client;
        $clients              = $this->repository->all();

        require __DIR__ . '/../views/clients.php';
    }

    private function handle_form_submission(): void {
        if ( ! isset( $_POST['eo_crm_client_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_crm_client_nonce'] ) ), 'eo_crm_client_action' ) ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_CLIENTS ) ) {
            return;
        }

        $client_id   = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        $client_type = sanitize_key( wp_unslash( $_POST['client_type'] ?? 'person' ) );

        if ( ! in_array( $client_type, [ 'person', 'company' ], true ) ) {
            $client_type = 'person';
        }

        $first_name      = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name       = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $company_name    = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) );
        $representative  = sanitize_text_field( wp_unslash( $_POST['representative'] ?? '' ) );
        $contact_phone   = sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) );
        $contact_email   = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $website         = esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) );
        $pesel           = sanitize_text_field( wp_unslash( $_POST['pesel'] ?? '' ) );
        $document_type   = sanitize_text_field( wp_unslash( $_POST['document_type'] ?? '' ) );
        $document_number = sanitize_text_field( wp_unslash( $_POST['document_number'] ?? '' ) );
        $nip             = sanitize_text_field( wp_unslash( $_POST['nip'] ?? '' ) );
        $krs             = sanitize_text_field( wp_unslash( $_POST['krs'] ?? '' ) );
        $regon           = sanitize_text_field( wp_unslash( $_POST['regon'] ?? '' ) );

        $address = [
            'street'      => sanitize_text_field( wp_unslash( $_POST['address_street'] ?? '' ) ),
            'number'      => sanitize_text_field( wp_unslash( $_POST['address_number'] ?? '' ) ),
            'unit'        => sanitize_text_field( wp_unslash( $_POST['address_unit'] ?? '' ) ),
            'postal_code' => sanitize_text_field( wp_unslash( $_POST['address_postal_code'] ?? '' ) ),
            'city'        => sanitize_text_field( wp_unslash( $_POST['address_city'] ?? '' ) ),
            'country'     => sanitize_text_field( wp_unslash( $_POST['address_country'] ?? '' ) ),
        ];

        $correspondence_same = isset( $_POST['correspondence_same'] ) && '1' === $_POST['correspondence_same'];

        $correspondence = $correspondence_same ? $address : [
            'street'      => sanitize_text_field( wp_unslash( $_POST['correspondence_street'] ?? '' ) ),
            'number'      => sanitize_text_field( wp_unslash( $_POST['correspondence_number'] ?? '' ) ),
            'unit'        => sanitize_text_field( wp_unslash( $_POST['correspondence_unit'] ?? '' ) ),
            'postal_code' => sanitize_text_field( wp_unslash( $_POST['correspondence_postal_code'] ?? '' ) ),
            'city'        => sanitize_text_field( wp_unslash( $_POST['correspondence_city'] ?? '' ) ),
            'country'     => sanitize_text_field( wp_unslash( $_POST['correspondence_country'] ?? '' ) ),
        ];

        $primary_name = 'person' === $client_type ? trim( $first_name . ' ' . $last_name ) : $company_name;

        if ( empty( $primary_name ) ) {
            add_settings_error( 'estate-office-crm-clients', 'missing_name', __( 'Dla klienta wymagane jest imię i nazwisko lub nazwa firmy.', 'estate-office-crm' ) );
            return;
        }

        $meta = [
            'first_name'         => $first_name,
            'last_name'          => $last_name,
            'website'             => $website,
            'pesel'               => $pesel,
            'document_type'       => $document_type,
            'document_number'     => $document_number,
            'nip'                 => $nip,
            'krs'                 => $krs,
            'regon'               => $regon,
            'correspondence_same' => $correspondence_same,
        ];

        $data = [
            'client_type'     => $client_type,
            'primary_name'    => $primary_name,
            'contact_phone'   => $contact_phone,
            'contact_email'   => $contact_email,
            'company_name'    => $company_name,
            'representative'  => $representative,
            'meta'            => wp_json_encode( array_filter( $meta ) ),
            'address'         => wp_json_encode( array_filter( $address ) ),
            'correspondence'  => wp_json_encode( array_filter( $correspondence ) ),
        ];

        if ( $client_id > 0 ) {
            $this->repository->update( $client_id, $data );
            add_settings_error( 'estate-office-crm-clients', 'updated', __( 'Klient zaktualizowany.', 'estate-office-crm' ), 'updated' );
        } else {
            $this->repository->create( $data );
            add_settings_error( 'estate-office-crm-clients', 'created', __( 'Dodano nowego klienta.', 'estate-office-crm' ), 'updated' );
        }

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function handle_delete(): void {
        if ( ! isset( $_GET['action'], $_GET['client'], $_GET['_wpnonce'] ) || 'delete' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_CLIENTS ) || ! current_user_can( Capabilities::DELETE_RECORDS ) ) {
            return;
        }

        $client_id = absint( $_GET['client'] );

        if ( $client_id <= 0 ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'eo_crm_delete_client_' . $client_id ) ) {
            return;
        }

        $this->repository->delete( $client_id );
        add_settings_error( 'estate-office-crm-clients', 'deleted', __( 'Klient został usunięty.', 'estate-office-crm' ), 'updated' );

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function get_current_client(): ?array {
        if ( isset( $_GET['action'], $_GET['client'] ) && 'edit' === $_GET['action'] ) {
            $client_id = absint( $_GET['client'] );

            if ( $client_id > 0 ) {
                return $this->repository->find( $client_id );
            }
        }

        return null;
    }

    private function get_page_url( array $args = [] ): string {
        $base = admin_url( 'admin.php?page=estate-office-crm-clients' );

        return $args ? add_query_arg( $args, $base ) : $base;
    }

    public function get_current_client_data(): ?array {
        return $this->current_client;
    }
}
