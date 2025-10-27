<?php
/**
 * Properties management controller.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Capabilities;
use EstateOfficeCRM\Database\Repositories\Agents_Repository;
use EstateOfficeCRM\Database\Repositories\Contracts_Repository;
use EstateOfficeCRM\Database\Repositories\Properties_Repository;

/**
 * Manage property CRUD operations.
 */
class Properties_Page {
    private Properties_Repository $repository;

    private Contracts_Repository $contracts_repository;

    private Agents_Repository $agents_repository;

    private ?array $current_property = null;

    public function __construct() {
        $this->repository           = new Properties_Repository();
        $this->contracts_repository = new Contracts_Repository();
        $this->agents_repository    = new Agents_Repository();
    }

    public function render(): void {
        $this->handle_delete();
        $this->handle_form_submission();
        $this->current_property = $this->get_current_property();
        $current_property       = $this->current_property;
        $properties             = $this->repository->all();
        $contracts              = $this->contracts_repository->all();
        $agents                 = $this->agents_repository->all();

        require __DIR__ . '/../views/properties.php';
    }

    private function handle_form_submission(): void {
        if ( ! isset( $_POST['eo_crm_property_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_crm_property_nonce'] ) ), 'eo_crm_property_action' ) ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_PROPERTIES ) ) {
            return;
        }

        $property_id      = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $contract_id      = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $agent_id         = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $transaction_type = sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? '' ) );
        $property_type    = sanitize_text_field( wp_unslash( $_POST['property_type'] ?? '' ) );
        $title            = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $price_raw        = isset( $_POST['price'] ) ? wp_unslash( $_POST['price'] ) : '';
        $price            = '' !== $price_raw ? floatval( $price_raw ) : null;
        $fee_raw          = isset( $_POST['administration_fee'] ) ? wp_unslash( $_POST['administration_fee'] ) : '';
        $fee              = '' !== $fee_raw ? floatval( $fee_raw ) : null;
        $size_raw         = isset( $_POST['size_total'] ) ? wp_unslash( $_POST['size_total'] ) : '';
        $size             = '' !== $size_raw ? floatval( $size_raw ) : null;
        $rooms_raw        = isset( $_POST['rooms'] ) ? wp_unslash( $_POST['rooms'] ) : '';
        $rooms            = '' !== $rooms_raw ? absint( $rooms_raw ) : null;
        $bedrooms_raw     = isset( $_POST['bedrooms'] ) ? wp_unslash( $_POST['bedrooms'] ) : '';
        $bedrooms         = '' !== $bedrooms_raw ? absint( $bedrooms_raw ) : null;
        $bathrooms_raw    = isset( $_POST['bathrooms'] ) ? wp_unslash( $_POST['bathrooms'] ) : '';
        $bathrooms        = '' !== $bathrooms_raw ? absint( $bathrooms_raw ) : null;
        $storey_raw       = isset( $_POST['storey'] ) ? wp_unslash( $_POST['storey'] ) : '';
        $storey           = '' !== $storey_raw ? absint( $storey_raw ) : null;
        $floors_raw       = isset( $_POST['floors'] ) ? wp_unslash( $_POST['floors'] ) : '';
        $floors           = '' !== $floors_raw ? absint( $floors_raw ) : null;
        $build_year_raw   = isset( $_POST['build_year'] ) ? wp_unslash( $_POST['build_year'] ) : '';
        $build_year       = '' !== $build_year_raw ? absint( $build_year_raw ) : null;
        $legal_status     = sanitize_text_field( wp_unslash( $_POST['legal_status'] ?? '' ) );
        $lot_shape        = sanitize_text_field( wp_unslash( $_POST['lot_shape'] ?? '' ) );
        $lot_length_raw   = isset( $_POST['lot_length'] ) ? wp_unslash( $_POST['lot_length'] ) : '';
        $lot_width_raw    = isset( $_POST['lot_width'] ) ? wp_unslash( $_POST['lot_width'] ) : '';
        $lot_length       = '' !== $lot_length_raw ? floatval( $lot_length_raw ) : null;
        $lot_width        = '' !== $lot_width_raw ? floatval( $lot_width_raw ) : null;
        $lot_description  = sanitize_textarea_field( wp_unslash( $_POST['lot_description'] ?? '' ) );
        $description      = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
        $export_web       = isset( $_POST['export_web'] ) ? 1 : 0;

        $allowed_transactions = [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ];
        $allowed_types        = [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ];

        if ( ! in_array( $transaction_type, $allowed_transactions, true ) ) {
            $transaction_type = 'SPRZEDAŻ';
        }

        if ( ! in_array( $property_type, $allowed_types, true ) ) {
            $property_type = 'MIESZKANIE';
        }

        if ( $size && $price ) {
            $price_per_sqm = $size > 0 ? round( $price / $size, 2 ) : null;
        } else {
            $price_per_sqm = null;
        }

        $address = [
            'street'      => sanitize_text_field( wp_unslash( $_POST['address_street'] ?? '' ) ),
            'number'      => sanitize_text_field( wp_unslash( $_POST['address_number'] ?? '' ) ),
            'unit'        => sanitize_text_field( wp_unslash( $_POST['address_unit'] ?? '' ) ),
            'postal_code' => sanitize_text_field( wp_unslash( $_POST['address_postal_code'] ?? '' ) ),
            'district'    => sanitize_text_field( wp_unslash( $_POST['address_district'] ?? '' ) ),
            'city'        => sanitize_text_field( wp_unslash( $_POST['address_city'] ?? '' ) ),
        ];

        $lot_dimensions = [
            'length' => $lot_length,
            'width'  => $lot_width,
            'notes'  => $lot_description,
        ];

        $tags = [];

        if ( isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ) {
            $tags = array_map( 'sanitize_text_field', wp_unslash( $_POST['tags'] ) );
        }

        if ( empty( $title ) ) {
            add_settings_error( 'estate-office-crm-properties', 'missing_title', __( 'Tytuł oferty jest wymagany.', 'estate-office-crm' ) );
            return;
        }

        $data = [
            'agent_id'         => $agent_id ?: null,
            'contract_id'      => $contract_id ?: null,
            'transaction_type' => $transaction_type,
            'property_type'    => $property_type,
            'title'            => $title,
            'address'          => wp_json_encode( array_filter( $address ) ),
            'legal_status'     => $legal_status,
            'price'            => $price,
            'administration_fee' => $fee,
            'size_total'       => $size,
            'price_per_sqm'    => $price_per_sqm,
            'rooms'            => $rooms,
            'bedrooms'         => $bedrooms,
            'bathrooms'        => $bathrooms,
            'storey'           => $storey,
            'floors'           => $floors,
            'build_year'       => $build_year,
            'lot_shape'        => $lot_shape,
            'lot_dimensions'   => wp_json_encode( array_filter( $lot_dimensions ) ),
            'description'      => $description,
            'tags'             => wp_json_encode( $tags ),
            'export_web'       => $export_web,
        ];

        if ( $property_id > 0 ) {
            $this->repository->update( $property_id, $data );
            add_settings_error( 'estate-office-crm-properties', 'updated', __( 'Nieruchomość zaktualizowana.', 'estate-office-crm' ), 'updated' );
        } else {
            $this->repository->create( $data );
            add_settings_error( 'estate-office-crm-properties', 'created', __( 'Dodano nową nieruchomość.', 'estate-office-crm' ), 'updated' );
        }

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function handle_delete(): void {
        if ( ! isset( $_GET['action'], $_GET['property'], $_GET['_wpnonce'] ) || 'delete' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_PROPERTIES ) || ! current_user_can( Capabilities::DELETE_RECORDS ) ) {
            return;
        }

        $property_id = absint( $_GET['property'] );

        if ( $property_id <= 0 ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'eo_crm_delete_property_' . $property_id ) ) {
            return;
        }

        $this->repository->delete( $property_id );
        add_settings_error( 'estate-office-crm-properties', 'deleted', __( 'Nieruchomość została usunięta.', 'estate-office-crm' ), 'updated' );

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function get_current_property(): ?array {
        if ( isset( $_GET['action'], $_GET['property'] ) && 'edit' === $_GET['action'] ) {
            $property_id = absint( $_GET['property'] );

            if ( $property_id > 0 ) {
                return $this->repository->find( $property_id );
            }
        }

        return null;
    }

    private function get_page_url( array $args = [] ): string {
        $base = admin_url( 'admin.php?page=estate-office-crm-properties' );

        return $args ? add_query_arg( $args, $base ) : $base;
    }

    public function get_current_property_data(): ?array {
        return $this->current_property;
    }
}
