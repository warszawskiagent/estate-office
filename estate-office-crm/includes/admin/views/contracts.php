<?php
/**
 * Contracts view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

use EstateOfficeCRM\Capabilities;

defined( 'ABSPATH' ) || exit;

$is_edit          = ! empty( $current_contract );
$contract_id      = $current_contract['id'] ?? 0;
$contract_number  = $current_contract['contract_number'] ?? '';
$transaction_type = $current_contract['transaction_type'] ?? 'SPRZEDAŻ';
$agent_id         = $current_contract['agent_id'] ?? 0;
$start_date       = $current_contract['start_date'] ?? '';
$end_date         = $current_contract['end_date'] ?? '';
$is_open_ended    = ! empty( $current_contract['is_open_ended'] );
$commission_amount = $current_contract['commission_amount'] ?? '';
$commission_unit  = $current_contract['commission_unit'] ?? '%';
$current_stage    = $current_contract['current_stage'] ?? '';
$stage_history    = $current_contract['stage_history'] ?? [];

settings_errors( 'estate-office-crm-contracts' );
?>
<div class="wrap estate-office-crm-contracts">
    <h1><?php esc_html_e( 'Umowy', 'estate-office-crm' ); ?></h1>
    <div class="eo-crm-flex">
        <section class="eo-crm-form">
            <h2><?php echo esc_html( $is_edit ? __( 'Edytuj umowę', 'estate-office-crm' ) : __( 'Dodaj umowę', 'estate-office-crm' ) ); ?></h2>
            <?php if ( $is_edit ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm-contracts' ) ); ?>"><?php esc_html_e( 'Dodaj nową umowę', 'estate-office-crm' ); ?></a>
            <?php endif; ?>
            <form method="post" action="" class="eo-crm-contract-form">
                <?php wp_nonce_field( 'eo_crm_contract_action', 'eo_crm_contract_nonce' ); ?>
                <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="contract_number"><?php esc_html_e( 'Numer umowy', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="contract_number" name="contract_number" class="regular-text" value="<?php echo esc_attr( $contract_number ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="transaction_type"><?php esc_html_e( 'Typ transakcji', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="transaction_type" name="transaction_type">
                                    <option value="SPRZEDAŻ" <?php selected( $transaction_type, 'SPRZEDAŻ' ); ?>><?php esc_html_e( 'Sprzedaż', 'estate-office-crm' ); ?></option>
                                    <option value="KUPNO" <?php selected( $transaction_type, 'KUPNO' ); ?>><?php esc_html_e( 'Kupno', 'estate-office-crm' ); ?></option>
                                    <option value="WYNAJEM" <?php selected( $transaction_type, 'WYNAJEM' ); ?>><?php esc_html_e( 'Wynajem', 'estate-office-crm' ); ?></option>
                                    <option value="NAJEM" <?php selected( $transaction_type, 'NAJEM' ); ?>><?php esc_html_e( 'Najem', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="agent_id"><?php esc_html_e( 'Opiekun', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="agent_id" name="agent_id">
                                    <option value="0"><?php esc_html_e( '— bez przypisania —', 'estate-office-crm' ); ?></option>
                                    <?php foreach ( $agents as $agent ) : ?>
                                        <option value="<?php echo esc_attr( $agent['id'] ); ?>" <?php selected( (int) $agent_id, (int) $agent['id'] ); ?>>
                                            <?php echo esc_html( trim( ( $agent['first_name'] ?? '' ) . ' ' . ( $agent['last_name'] ?? '' ) ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="start_date"><?php esc_html_e( 'Data zawarcia', 'estate-office-crm' ); ?></label></th>
                            <td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="end_date"><?php esc_html_e( 'Data zakończenia', 'estate-office-crm' ); ?></label></th>
                            <td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" <?php disabled( $is_open_ended ); ?>></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="is_open_ended"><?php esc_html_e( 'Umowa bezterminowa', 'estate-office-crm' ); ?></label></th>
                            <td><label><input type="checkbox" id="is_open_ended" name="is_open_ended" value="1" <?php checked( $is_open_ended ); ?>> <?php esc_html_e( 'Brak daty zakończenia', 'estate-office-crm' ); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="commission_amount"><?php esc_html_e( 'Wysokość prowizji', 'estate-office-crm' ); ?></label></th>
                            <td class="eo-crm-inline">
                                <input type="number" step="0.01" id="commission_amount" name="commission_amount" value="<?php echo esc_attr( $commission_amount ); ?>" class="small-text">
                                <select name="commission_unit" id="commission_unit">
                                    <option value="%" <?php selected( $commission_unit, '%' ); ?>>%</option>
                                    <option value="PLN" <?php selected( $commission_unit, 'PLN' ); ?>>PLN</option>
                                    <option value="EUR" <?php selected( $commission_unit, 'EUR' ); ?>>EUR</option>
                                    <option value="USD" <?php selected( $commission_unit, 'USD' ); ?>>USD</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( $is_edit ? __( 'Zapisz umowę', 'estate-office-crm' ) : __( 'Dodaj umowę', 'estate-office-crm' ) ); ?>
            </form>
            <?php if ( $is_edit && ! empty( $stage_history ) ) : ?>
                <h3><?php esc_html_e( 'Historia etapów', 'estate-office-crm' ); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Data', 'estate-office-crm' ); ?></th>
                            <th><?php esc_html_e( 'Etap', 'estate-office-crm' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stage_history as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry['date'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $entry['stage'] ?? '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <section class="eo-crm-list">
            <h2><?php esc_html_e( 'Lista umów', 'estate-office-crm' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Numer umowy', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Typ transakcji', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Data zawarcia', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $contracts ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'Brak umów do wyświetlenia.', 'estate-office-crm' ); ?></td></tr>
                    <?php else : ?>
                        <?php
                        $agents_map = [];
                        foreach ( $agents as $agent ) {
                            $agents_map[ (int) $agent['id'] ] = trim( ( $agent['first_name'] ?? '' ) . ' ' . ( $agent['last_name'] ?? '' ) );
                        }
                        ?>
                        <?php foreach ( $contracts as $contract ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $contract['contract_number'] ?? '' ); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'contract' => (int) $contract['id'] ], admin_url( 'admin.php?page=estate-office-crm-contracts' ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office-crm' ); ?></a></span>
                                        <?php if ( current_user_can( Capabilities::DELETE_RECORDS ) ) : ?>
                                            <span class="delete"><a class="delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'contract' => (int) $contract['id'] ], admin_url( 'admin.php?page=estate-office-crm-contracts' ) ), 'eo_crm_delete_contract_' . (int) $contract['id'] ) ); ?>"><?php esc_html_e( 'Usuń', 'estate-office-crm' ); ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $contract['transaction_type'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $contract['start_date'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $agents_map[ (int) ( $contract['agent_id'] ?? 0 ) ] ?? '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
