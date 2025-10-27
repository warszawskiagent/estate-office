<?php
/**
 * Searches view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

use EstateOfficeCRM\Capabilities;

defined( 'ABSPATH' ) || exit;

$is_edit          = ! empty( $current_search );
$search_id        = $current_search['id'] ?? 0;
$contract_id      = $current_search['contract_id'] ?? 0;
$agent_id         = $current_search['agent_id'] ?? 0;
$transaction_type = $current_search['transaction_type'] ?? 'KUPNO';
$budget_min       = $current_search['budget_min'] ?? '';
$budget_max       = $current_search['budget_max'] ?? '';
$size_min         = $current_search['size_min'] ?? '';
$size_max         = $current_search['size_max'] ?? '';
$rooms_min        = $current_search['rooms_min'] ?? '';
$rooms_max        = $current_search['rooms_max'] ?? '';
$description      = $current_search['description'] ?? '';
$criteria         = $current_search['criteria'] ?? [];

settings_errors( 'estate-office-crm-searches' );
?>
<div class="wrap estate-office-crm-searches">
    <h1><?php esc_html_e( 'Poszukiwania', 'estate-office-crm' ); ?></h1>
    <div class="eo-crm-flex">
        <section class="eo-crm-form">
            <h2><?php echo esc_html( $is_edit ? __( 'Edytuj poszukiwanie', 'estate-office-crm' ) : __( 'Dodaj poszukiwanie', 'estate-office-crm' ) ); ?></h2>
            <?php if ( $is_edit ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm-searches' ) ); ?>"><?php esc_html_e( 'Dodaj nowe poszukiwanie', 'estate-office-crm' ); ?></a>
            <?php endif; ?>
            <form method="post" action="" class="eo-crm-search-form">
                <?php wp_nonce_field( 'eo_crm_search_action', 'eo_crm_search_nonce' ); ?>
                <input type="hidden" name="search_id" value="<?php echo esc_attr( $search_id ); ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="contract_id"><?php esc_html_e( 'Umowa', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="contract_id" name="contract_id">
                                    <option value="0"><?php esc_html_e( '— wybierz umowę —', 'estate-office-crm' ); ?></option>
                                    <?php foreach ( $contracts as $contract ) : ?>
                                        <option value="<?php echo esc_attr( $contract['id'] ); ?>" <?php selected( (int) $contract_id, (int) $contract['id'] ); ?>><?php echo esc_html( $contract['contract_number'] ?? '' ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="agent_id"><?php esc_html_e( 'Opiekun', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="agent_id" name="agent_id">
                                    <option value="0"><?php esc_html_e( '— bez przypisania —', 'estate-office-crm' ); ?></option>
                                    <?php foreach ( $agents as $agent ) : ?>
                                        <option value="<?php echo esc_attr( $agent['id'] ); ?>" <?php selected( (int) $agent_id, (int) $agent['id'] ); ?>><?php echo esc_html( trim( ( $agent['first_name'] ?? '' ) . ' ' . ( $agent['last_name'] ?? '' ) ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="transaction_type"><?php esc_html_e( 'Typ transakcji', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="transaction_type" name="transaction_type">
                                    <option value="KUPNO" <?php selected( $transaction_type, 'KUPNO' ); ?>><?php esc_html_e( 'Kupno', 'estate-office-crm' ); ?></option>
                                    <option value="NAJEM" <?php selected( $transaction_type, 'NAJEM' ); ?>><?php esc_html_e( 'Najem', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Budżet', 'estate-office-crm' ); ?></th>
                            <td class="eo-crm-inline">
                                <input type="number" step="0.01" name="budget_min" value="<?php echo esc_attr( $budget_min ); ?>" placeholder="<?php esc_attr_e( 'Od', 'estate-office-crm' ); ?>">
                                <input type="number" step="0.01" name="budget_max" value="<?php echo esc_attr( $budget_max ); ?>" placeholder="<?php esc_attr_e( 'Do', 'estate-office-crm' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Metraż', 'estate-office-crm' ); ?></th>
                            <td class="eo-crm-inline">
                                <input type="number" step="0.01" name="size_min" value="<?php echo esc_attr( $size_min ); ?>" placeholder="<?php esc_attr_e( 'Od', 'estate-office-crm' ); ?>">
                                <input type="number" step="0.01" name="size_max" value="<?php echo esc_attr( $size_max ); ?>" placeholder="<?php esc_attr_e( 'Do', 'estate-office-crm' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Liczba pokoi', 'estate-office-crm' ); ?></th>
                            <td class="eo-crm-inline">
                                <input type="number" name="rooms_min" value="<?php echo esc_attr( $rooms_min ); ?>" placeholder="<?php esc_attr_e( 'Od', 'estate-office-crm' ); ?>">
                                <input type="number" name="rooms_max" value="<?php echo esc_attr( $rooms_max ); ?>" placeholder="<?php esc_attr_e( 'Do', 'estate-office-crm' ); ?>">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php esc_html_e( 'Opis poszukiwania', 'estate-office-crm' ); ?></h3>
                <?php wp_editor( $description, 'search_description', [ 'textarea_name' => 'description', 'textarea_rows' => 5 ] ); ?>
                <h3><?php esc_html_e( 'Dodatkowe kryteria', 'estate-office-crm' ); ?></h3>
                <div class="eo-crm-grid">
                    <p><label><span><?php esc_html_e( 'Budynek', 'estate-office-crm' ); ?></span><input type="text" name="criteria_building" value="<?php echo esc_attr( $criteria['building'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Media', 'estate-office-crm' ); ?></span><input type="text" name="criteria_media" value="<?php echo esc_attr( $criteria['media'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Udogodnienia', 'estate-office-crm' ); ?></span><input type="text" name="criteria_amenities" value="<?php echo esc_attr( $criteria['amenities'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Wyposażenie', 'estate-office-crm' ); ?></span><input type="text" name="criteria_equipment" value="<?php echo esc_attr( $criteria['equipment'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Powierzchnie dodatkowe', 'estate-office-crm' ); ?></span><input type="text" name="criteria_extra_areas" value="<?php echo esc_attr( $criteria['extra_areas'] ?? '' ); ?>"></label></p>
                </div>
                <?php submit_button( $is_edit ? __( 'Zapisz poszukiwanie', 'estate-office-crm' ) : __( 'Dodaj poszukiwanie', 'estate-office-crm' ) ); ?>
            </form>
        </section>
        <section class="eo-crm-list">
            <h2><?php esc_html_e( 'Lista poszukiwań', 'estate-office-crm' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Umowa', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Typ transakcji', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Budżet', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Metraż', 'estate-office-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $searches ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'Brak poszukiwań do wyświetlenia.', 'estate-office-crm' ); ?></td></tr>
                    <?php else : ?>
                        <?php
                        $contracts_map = [];
                        foreach ( $contracts as $contract ) {
                            $contracts_map[ (int) $contract['id'] ] = $contract['contract_number'] ?? '';
                        }
                        ?>
                        <?php foreach ( $searches as $search ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $contracts_map[ (int) ( $search['contract_id'] ?? 0 ) ] ?? '—' ); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'search' => (int) $search['id'] ], admin_url( 'admin.php?page=estate-office-crm-searches' ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office-crm' ); ?></a></span>
                                        <?php if ( current_user_can( Capabilities::DELETE_RECORDS ) ) : ?>
                                            <span class="delete"><a class="delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'search' => (int) $search['id'] ], admin_url( 'admin.php?page=estate-office-crm-searches' ) ), 'eo_crm_delete_search_' . (int) $search['id'] ) ); ?>"><?php esc_html_e( 'Usuń', 'estate-office-crm' ); ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $search['transaction_type'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $search['budget_min'] || $search['budget_max'] ? sprintf( '%s – %s', $search['budget_min'] ? number_format_i18n( (float) $search['budget_min'], 2 ) : '—', $search['budget_max'] ? number_format_i18n( (float) $search['budget_max'], 2 ) : '—' ) : '—' ); ?></td>
                                <td><?php echo esc_html( $search['size_min'] || $search['size_max'] ? sprintf( '%s – %s m²', $search['size_min'] ? number_format_i18n( (float) $search['size_min'], 2 ) : '—', $search['size_max'] ? number_format_i18n( (float) $search['size_max'], 2 ) : '—' ) : '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
