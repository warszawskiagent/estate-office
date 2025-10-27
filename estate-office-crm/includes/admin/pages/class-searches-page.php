<?php
/**
 * Searches management controller.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Capabilities;
use EstateOfficeCRM\Database\Repositories\Agents_Repository;
use EstateOfficeCRM\Database\Repositories\Contracts_Repository;
use EstateOfficeCRM\Database\Repositories\Searches_Repository;

/**
 * Manage search criteria CRUD.
 */
class Searches_Page {
    private Searches_Repository $repository;

    private Contracts_Repository $contracts_repository;

    private Agents_Repository $agents_repository;

    private ?array $current_search = null;

    public function __construct() {
        $this->repository           = new Searches_Repository();
        $this->contracts_repository = new Contracts_Repository();
        $this->agents_repository    = new Agents_Repository();
    }

    public function render(): void {
        $this->handle_delete();
        $this->handle_form_submission();
        $this->current_search = $this->get_current_search();
        $current_search       = $this->current_search;
        $searches             = $this->repository->all();
        $contracts            = $this->contracts_repository->all();
        $agents               = $this->agents_repository->all();

        require __DIR__ . '/../views/searches.php';
    }

    private function handle_form_submission(): void {
        if ( ! isset( $_POST['eo_crm_search_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_crm_search_nonce'] ) ), 'eo_crm_search_action' ) ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_SEARCHES ) ) {
            return;
        }

        $search_id         = isset( $_POST['search_id'] ) ? absint( $_POST['search_id'] ) : 0;
        $contract_id       = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $agent_id          = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $transaction_type  = sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? '' ) );
        $budget_min_raw    = isset( $_POST['budget_min'] ) ? wp_unslash( $_POST['budget_min'] ) : '';
        $budget_max_raw    = isset( $_POST['budget_max'] ) ? wp_unslash( $_POST['budget_max'] ) : '';
        $size_min_raw      = isset( $_POST['size_min'] ) ? wp_unslash( $_POST['size_min'] ) : '';
        $size_max_raw      = isset( $_POST['size_max'] ) ? wp_unslash( $_POST['size_max'] ) : '';
        $rooms_min_raw     = isset( $_POST['rooms_min'] ) ? wp_unslash( $_POST['rooms_min'] ) : '';
        $rooms_max_raw     = isset( $_POST['rooms_max'] ) ? wp_unslash( $_POST['rooms_max'] ) : '';
        $budget_min        = '' !== $budget_min_raw ? floatval( $budget_min_raw ) : null;
        $budget_max        = '' !== $budget_max_raw ? floatval( $budget_max_raw ) : null;
        $size_min          = '' !== $size_min_raw ? floatval( $size_min_raw ) : null;
        $size_max          = '' !== $size_max_raw ? floatval( $size_max_raw ) : null;
        $rooms_min         = '' !== $rooms_min_raw ? absint( $rooms_min_raw ) : null;
        $rooms_max         = '' !== $rooms_max_raw ? absint( $rooms_max_raw ) : null;
        $description       = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );

        $criteria = [
            'building'      => sanitize_text_field( wp_unslash( $_POST['criteria_building'] ?? '' ) ),
            'media'         => sanitize_text_field( wp_unslash( $_POST['criteria_media'] ?? '' ) ),
            'amenities'     => sanitize_text_field( wp_unslash( $_POST['criteria_amenities'] ?? '' ) ),
            'equipment'     => sanitize_text_field( wp_unslash( $_POST['criteria_equipment'] ?? '' ) ),
            'extra_areas'   => sanitize_text_field( wp_unslash( $_POST['criteria_extra_areas'] ?? '' ) ),
        ];

        $allowed_transactions = [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ];

        if ( ! in_array( $transaction_type, $allowed_transactions, true ) ) {
            $transaction_type = 'KUPNO';
        }

        if ( empty( $description ) ) {
            add_settings_error( 'estate-office-crm-searches', 'missing_description', __( 'Opis poszukiwania jest wymagany.', 'estate-office-crm' ) );
            return;
        }

        $data = [
            'agent_id'         => $agent_id ?: null,
            'contract_id'      => $contract_id ?: null,
            'transaction_type' => $transaction_type,
            'budget_min'       => $budget_min,
            'budget_max'       => $budget_max,
            'size_min'         => $size_min,
            'size_max'         => $size_max,
            'rooms_min'        => $rooms_min,
            'rooms_max'        => $rooms_max,
            'criteria'         => wp_json_encode( array_filter( $criteria ) ),
            'description'      => $description,
        ];

        if ( $search_id > 0 ) {
            $this->repository->update( $search_id, $data );
            add_settings_error( 'estate-office-crm-searches', 'updated', __( 'Poszukiwanie zaktualizowane.', 'estate-office-crm' ), 'updated' );
        } else {
            $this->repository->create( $data );
            add_settings_error( 'estate-office-crm-searches', 'created', __( 'Dodano nowe poszukiwanie.', 'estate-office-crm' ), 'updated' );
        }

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function handle_delete(): void {
        if ( ! isset( $_GET['action'], $_GET['search'], $_GET['_wpnonce'] ) || 'delete' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_SEARCHES ) || ! current_user_can( Capabilities::DELETE_RECORDS ) ) {
            return;
        }

        $search_id = absint( $_GET['search'] );

        if ( $search_id <= 0 ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'eo_crm_delete_search_' . $search_id ) ) {
            return;
        }

        $this->repository->delete( $search_id );
        add_settings_error( 'estate-office-crm-searches', 'deleted', __( 'Poszukiwanie zostało usunięte.', 'estate-office-crm' ), 'updated' );

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function get_current_search(): ?array {
        if ( isset( $_GET['action'], $_GET['search'] ) && 'edit' === $_GET['action'] ) {
            $search_id = absint( $_GET['search'] );

            if ( $search_id > 0 ) {
                return $this->repository->find( $search_id );
            }
        }

        return null;
    }

    private function get_page_url( array $args = [] ): string {
        $base = admin_url( 'admin.php?page=estate-office-crm-searches' );

        return $args ? add_query_arg( $args, $base ) : $base;
    }

    public function get_current_search_data(): ?array {
        return $this->current_search;
    }
}
