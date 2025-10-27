<?php
/**
 * Contracts management controller.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Capabilities;
use EstateOfficeCRM\Database\Repositories\Agents_Repository;
use EstateOfficeCRM\Database\Repositories\Contracts_Repository;

/**
 * Manage contracts CRUD.
 */
class Contracts_Page {
    private Contracts_Repository $repository;

    private Agents_Repository $agents_repository;

    private ?array $current_contract = null;

    public function __construct() {
        $this->repository        = new Contracts_Repository();
        $this->agents_repository = new Agents_Repository();
    }

    public function render(): void {
        $this->handle_delete();
        $this->handle_form_submission();
        $this->current_contract = $this->get_current_contract();
        $current_contract       = $this->current_contract;
        $contracts              = $this->repository->all();
        $agents                 = $this->agents_repository->all();

        require __DIR__ . '/../views/contracts.php';
    }

    private function handle_form_submission(): void {
        if ( ! isset( $_POST['eo_crm_contract_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_crm_contract_nonce'] ) ), 'eo_crm_contract_action' ) ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_CONTRACTS ) ) {
            return;
        }

        $contract_id     = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $agent_id        = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $contract_number = sanitize_text_field( wp_unslash( $_POST['contract_number'] ?? '' ) );
        $transaction     = sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? '' ) );
        $start_date      = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
        $end_date        = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
        $open_ended      = isset( $_POST['is_open_ended'] ) ? 1 : 0;
        $commission_raw  = isset( $_POST['commission_amount'] ) ? wp_unslash( $_POST['commission_amount'] ) : '';
        $commission      = '' !== $commission_raw ? floatval( $commission_raw ) : null;
        $commission_unit = sanitize_text_field( wp_unslash( $_POST['commission_unit'] ?? '%' ) );
        $allowed_units    = [ '%', 'PLN', 'EUR', 'USD' ];

        if ( ! in_array( $commission_unit, $allowed_units, true ) ) {
            $commission_unit = '%';
        }

        if ( '' === $contract_number ) {
            add_settings_error( 'estate-office-crm-contracts', 'missing_number', __( 'Numer umowy jest wymagany.', 'estate-office-crm' ) );
            return;
        }

        $allowed_transactions = [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ];

        if ( ! in_array( $transaction, $allowed_transactions, true ) ) {
            $transaction = 'SPRZEDAŻ';
        }

        if ( empty( $start_date ) ) {
            add_settings_error( 'estate-office-crm-contracts', 'missing_start', __( 'Data zawarcia umowy jest wymagana.', 'estate-office-crm' ) );
            return;
        }

        if ( $open_ended ) {
            $end_date = '';
        }

        $existing = $this->repository->find_by_number( $contract_number, $contract_id ?: null );

        if ( $existing ) {
            add_settings_error( 'estate-office-crm-contracts', 'duplicate', __( 'Umowa o tym numerze już istnieje.', 'estate-office-crm' ) );
            return;
        }

        $data = [
            'agent_id'         => $agent_id ?: null,
            'contract_number'  => $contract_number,
            'transaction_type' => $transaction,
            'start_date'       => $start_date,
            'end_date'         => $end_date,
            'is_open_ended'    => $open_ended,
            'commission_amount'=> $commission,
            'commission_unit'  => $commission_unit,
        ];

        if ( $contract_id > 0 ) {
            $this->repository->update( $contract_id, $data );
            add_settings_error( 'estate-office-crm-contracts', 'updated', __( 'Umowa zaktualizowana.', 'estate-office-crm' ), 'updated' );
        } else {
            $data['current_stage']  = __( 'Umowa pośrednictwa', 'estate-office-crm' );
            $data['stage_history']  = wp_json_encode(
                [
                    [
                        'stage' => __( 'Umowa pośrednictwa', 'estate-office-crm' ),
                        'date'  => current_time( 'mysql' ),
                    ],
                ]
            );
            $this->repository->create( $data );
            add_settings_error( 'estate-office-crm-contracts', 'created', __( 'Dodano nową umowę.', 'estate-office-crm' ), 'updated' );
        }

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function handle_delete(): void {
        if ( ! isset( $_GET['action'], $_GET['contract'], $_GET['_wpnonce'] ) || 'delete' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( Capabilities::MANAGE_CONTRACTS ) || ! current_user_can( Capabilities::DELETE_RECORDS ) ) {
            return;
        }

        $contract_id = absint( $_GET['contract'] );

        if ( $contract_id <= 0 ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'eo_crm_delete_contract_' . $contract_id ) ) {
            return;
        }

        $this->repository->delete( $contract_id );
        add_settings_error( 'estate-office-crm-contracts', 'deleted', __( 'Umowa została usunięta.', 'estate-office-crm' ), 'updated' );

        wp_safe_redirect( $this->get_page_url() );
        exit;
    }

    private function get_current_contract(): ?array {
        if ( isset( $_GET['action'], $_GET['contract'] ) && 'edit' === $_GET['action'] ) {
            $contract_id = absint( $_GET['contract'] );

            if ( $contract_id > 0 ) {
                return $this->repository->find( $contract_id );
            }
        }

        return null;
    }

    private function get_page_url( array $args = [] ): string {
        $base = admin_url( 'admin.php?page=estate-office-crm-contracts' );

        return $args ? add_query_arg( $args, $base ) : $base;
    }

    public function get_current_contract_data(): ?array {
        return $this->current_contract;
    }
}
