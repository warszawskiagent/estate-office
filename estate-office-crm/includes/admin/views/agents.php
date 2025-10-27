<?php
/**
 * Agents view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

use EstateOfficeCRM\Capabilities;

defined( 'ABSPATH' ) || exit;

$is_edit    = ! empty( $current_agent );
$agent_id   = $current_agent['id'] ?? 0;
$first_name = $current_agent['first_name'] ?? '';
$last_name  = $current_agent['last_name'] ?? '';
$email      = $current_agent['email'] ?? '';
$phone      = $current_agent['phone'] ?? '';
$avatar_id  = $current_agent['avatar_id'] ?? 0;
$biography  = $current_agent['biography'] ?? '';
$address1   = $current_agent['address_line1'] ?? '';
$address2   = $current_agent['address_line2'] ?? '';
$city       = $current_agent['city'] ?? '';
$postal     = $current_agent['postal_code'] ?? '';
$country    = $current_agent['country'] ?? '';
$form_title = $is_edit ? __( 'Edytuj agenta', 'estate-office-crm' ) : __( 'Dodaj nowego agenta', 'estate-office-crm' );

settings_errors( 'estate-office-crm-agents' );
?>
<div class="wrap estate-office-crm-agents">
    <h1><?php esc_html_e( 'Agenci', 'estate-office-crm' ); ?></h1>
    <div class="eo-crm-flex">
        <section class="eo-crm-form">
            <h2><?php echo esc_html( $form_title ); ?></h2>
            <?php if ( $is_edit ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm-agents' ) ); ?>">
                    <?php esc_html_e( 'Dodaj nowego agenta', 'estate-office-crm' ); ?>
                </a>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'eo_crm_agent_action', 'eo_crm_agent_nonce' ); ?>
                <input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent_id ); ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="first_name"><?php esc_html_e( 'Imię', 'estate-office-crm' ); ?></label></th>
                            <td><input name="first_name" id="first_name" type="text" class="regular-text" value="<?php echo esc_attr( $first_name ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="last_name"><?php esc_html_e( 'Nazwisko', 'estate-office-crm' ); ?></label></th>
                            <td><input name="last_name" id="last_name" type="text" class="regular-text" value="<?php echo esc_attr( $last_name ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email"><?php esc_html_e( 'Adres e-mail', 'estate-office-crm' ); ?></label></th>
                            <td><input name="email" id="email" type="email" class="regular-text" value="<?php echo esc_attr( $email ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phone"><?php esc_html_e( 'Telefon', 'estate-office-crm' ); ?></label></th>
                            <td><input name="phone" id="phone" type="text" class="regular-text" value="<?php echo esc_attr( $phone ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Zdjęcie profilowe', 'estate-office-crm' ); ?></th>
                            <td>
                                <div class="eo-crm-media-control" data-target="avatar_id">
                                    <button type="button" class="button eo-crm-open-media"><?php esc_html_e( 'Wybierz zdjęcie', 'estate-office-crm' ); ?></button>
                                    <input type="hidden" name="avatar_id" id="avatar_id" value="<?php echo esc_attr( $avatar_id ); ?>">
                                    <div class="eo-crm-media-preview" id="avatar_preview"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="agent_biography"><?php esc_html_e( 'Biografia', 'estate-office-crm' ); ?></label></th>
                            <td><?php wp_editor( $biography, 'agent_biography', [ 'textarea_name' => 'biography', 'textarea_rows' => 6 ] ); ?></td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php esc_html_e( 'Adres', 'estate-office-crm' ); ?></h3>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="address_line1"><?php esc_html_e( 'Ulica i numer', 'estate-office-crm' ); ?></label></th>
                            <td><input name="address_line1" id="address_line1" type="text" class="regular-text" value="<?php echo esc_attr( $address1 ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="address_line2"><?php esc_html_e( 'Dodatkowe informacje', 'estate-office-crm' ); ?></label></th>
                            <td><input name="address_line2" id="address_line2" type="text" class="regular-text" value="<?php echo esc_attr( $address2 ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="city"><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></label></th>
                            <td><input name="city" id="city" type="text" class="regular-text" value="<?php echo esc_attr( $city ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="postal_code"><?php esc_html_e( 'Kod pocztowy', 'estate-office-crm' ); ?></label></th>
                            <td><input name="postal_code" id="postal_code" type="text" class="regular-text" value="<?php echo esc_attr( $postal ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="country"><?php esc_html_e( 'Kraj', 'estate-office-crm' ); ?></label></th>
                            <td><input name="country" id="country" type="text" class="regular-text" value="<?php echo esc_attr( $country ); ?>"></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( $is_edit ? __( 'Zapisz zmiany', 'estate-office-crm' ) : __( 'Zapisz agenta', 'estate-office-crm' ) ); ?>
            </form>
        </section>
        <section class="eo-crm-list">
            <h2><?php esc_html_e( 'Lista agentów', 'estate-office-crm' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Imię i nazwisko', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Telefon', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'E-mail', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $agents ) ) : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'Brak agentów do wyświetlenia.', 'estate-office-crm' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $agents as $agent ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( trim( ( $agent['first_name'] ?? '' ) . ' ' . ( $agent['last_name'] ?? '' ) ) ); ?></strong>
                                    <?php if ( ! empty( $agent['biography'] ) ) : ?>
                                        <div class="description"><?php echo wp_kses_post( wp_trim_words( $agent['biography'], 20 ) ); ?></div>
                                    <?php endif; ?>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'agent' => (int) $agent['id'] ], admin_url( 'admin.php?page=estate-office-crm-agents' ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office-crm' ); ?></a></span>
                                        <?php if ( current_user_can( Capabilities::DELETE_RECORDS ) ) : ?>
                                            <span class="delete">
                                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'agent' => (int) $agent['id'] ], admin_url( 'admin.php?page=estate-office-crm-agents' ) ), 'eo_crm_delete_agent_' . (int) $agent['id'] ) ); ?>" class="delete">
                                                    <?php esc_html_e( 'Usuń', 'estate-office-crm' ); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $agent['phone'] ?? '' ); ?></td>
                                <td>
                                    <?php if ( ! empty( $agent['email'] ) ) : ?>
                                        <a href="mailto:<?php echo esc_attr( $agent['email'] ); ?>"><?php echo esc_html( $agent['email'] ); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $agent['city'] ?? '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
