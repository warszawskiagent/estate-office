<?php
/**
 * Clients view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

use EstateOfficeCRM\Capabilities;

defined( 'ABSPATH' ) || exit;

$meta               = $current_client['meta'] ?? [];
$address            = $current_client['address'] ?? [];
$correspondence     = $current_client['correspondence'] ?? [];
$is_edit            = ! empty( $current_client );
$client_id          = $current_client['id'] ?? 0;
$client_type        = $current_client['client_type'] ?? 'person';
$first_name         = $meta['first_name'] ?? '';
$last_name          = $meta['last_name'] ?? '';
$company_name       = $current_client['company_name'] ?? '';
$representative     = $current_client['representative'] ?? '';
$phone              = $current_client['contact_phone'] ?? '';
$email              = $current_client['contact_email'] ?? '';
$website            = $meta['website'] ?? '';
$pesel              = $meta['pesel'] ?? '';
$document_type      = $meta['document_type'] ?? '';
$document_number    = $meta['document_number'] ?? '';
$nip                = $meta['nip'] ?? '';
$krs                = $meta['krs'] ?? '';
$regon              = $meta['regon'] ?? '';
$correspondence_same = array_key_exists( 'correspondence_same', $meta ) ? (bool) $meta['correspondence_same'] : true;

settings_errors( 'estate-office-crm-clients' );
?>
<div class="wrap estate-office-crm-clients">
    <h1><?php esc_html_e( 'Klienci', 'estate-office-crm' ); ?></h1>
    <div class="eo-crm-flex">
        <section class="eo-crm-form">
            <h2><?php echo esc_html( $is_edit ? __( 'Edytuj klienta', 'estate-office-crm' ) : __( 'Dodaj klienta', 'estate-office-crm' ) ); ?></h2>
            <?php if ( $is_edit ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm-clients' ) ); ?>"><?php esc_html_e( 'Dodaj nowego klienta', 'estate-office-crm' ); ?></a>
            <?php endif; ?>
            <form method="post" action="" class="eo-crm-client-form">
                <?php wp_nonce_field( 'eo_crm_client_action', 'eo_crm_client_nonce' ); ?>
                <input type="hidden" name="client_id" value="<?php echo esc_attr( $client_id ); ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="client_type"><?php esc_html_e( 'Typ klienta', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select name="client_type" id="client_type">
                                    <option value="person" <?php selected( $client_type, 'person' ); ?>><?php esc_html_e( 'Osoba fizyczna', 'estate-office-crm' ); ?></option>
                                    <option value="company" <?php selected( $client_type, 'company' ); ?>><?php esc_html_e( 'Firma', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--person">
                            <th scope="row"><label for="first_name"><?php esc_html_e( 'Imię', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="first_name" name="first_name" class="regular-text" value="<?php echo esc_attr( $first_name ); ?>" ></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--person">
                            <th scope="row"><label for="last_name"><?php esc_html_e( 'Nazwisko', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="last_name" name="last_name" class="regular-text" value="<?php echo esc_attr( $last_name ); ?>" ></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--company">
                            <th scope="row"><label for="company_name"><?php esc_html_e( 'Nazwa firmy', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="company_name" name="company_name" class="regular-text" value="<?php echo esc_attr( $company_name ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--company">
                            <th scope="row"><label for="representative"><?php esc_html_e( 'Reprezentant', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="representative" name="representative" class="regular-text" value="<?php echo esc_attr( $representative ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="contact_phone"><?php esc_html_e( 'Telefon', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="contact_phone" name="contact_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="contact_email"><?php esc_html_e( 'Adres e-mail', 'estate-office-crm' ); ?></label></th>
                            <td><input type="email" id="contact_email" name="contact_email" class="regular-text" value="<?php echo esc_attr( $email ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--company">
                            <th scope="row"><label for="website"><?php esc_html_e( 'Strona WWW', 'estate-office-crm' ); ?></label></th>
                            <td><input type="url" id="website" name="website" class="regular-text" value="<?php echo esc_attr( $website ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--person">
                            <th scope="row"><label for="pesel"><?php esc_html_e( 'PESEL', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="pesel" name="pesel" class="regular-text" value="<?php echo esc_attr( $pesel ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--person">
                            <th scope="row"><label for="document_type"><?php esc_html_e( 'Rodzaj dokumentu', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="document_type" name="document_type">
                                    <option value="" <?php selected( $document_type, '' ); ?>><?php esc_html_e( 'Wybierz', 'estate-office-crm' ); ?></option>
                                    <option value="dowod" <?php selected( $document_type, 'dowod' ); ?>><?php esc_html_e( 'Dowód osobisty', 'estate-office-crm' ); ?></option>
                                    <option value="paszport" <?php selected( $document_type, 'paszport' ); ?>><?php esc_html_e( 'Paszport', 'estate-office-crm' ); ?></option>
                                    <option value="karta_pobytu" <?php selected( $document_type, 'karta_pobytu' ); ?>><?php esc_html_e( 'Karta pobytu', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--person">
                            <th scope="row"><label for="document_number"><?php esc_html_e( 'Numer dokumentu', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="document_number" name="document_number" class="regular-text" value="<?php echo esc_attr( $document_number ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--company">
                            <th scope="row"><label for="nip"><?php esc_html_e( 'NIP', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="nip" name="nip" class="regular-text" value="<?php echo esc_attr( $nip ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--company">
                            <th scope="row"><label for="krs"><?php esc_html_e( 'KRS', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="krs" name="krs" class="regular-text" value="<?php echo esc_attr( $krs ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-client-section eo-crm-client-section--company">
                            <th scope="row"><label for="regon"><?php esc_html_e( 'REGON', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="regon" name="regon" class="regular-text" value="<?php echo esc_attr( $regon ); ?>"></td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php esc_html_e( 'Adres zamieszkania / rejestrowy', 'estate-office-crm' ); ?></h3>
                <div class="eo-crm-grid">
                    <p><label><span><?php esc_html_e( 'Ulica', 'estate-office-crm' ); ?></span><input type="text" name="address_street" value="<?php echo esc_attr( $address['street'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Numer', 'estate-office-crm' ); ?></span><input type="text" name="address_number" value="<?php echo esc_attr( $address['number'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Lokal', 'estate-office-crm' ); ?></span><input type="text" name="address_unit" value="<?php echo esc_attr( $address['unit'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Kod pocztowy', 'estate-office-crm' ); ?></span><input type="text" name="address_postal_code" value="<?php echo esc_attr( $address['postal_code'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></span><input type="text" name="address_city" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Kraj', 'estate-office-crm' ); ?></span><input type="text" name="address_country" value="<?php echo esc_attr( $address['country'] ?? '' ); ?>"></label></p>
                </div>
                <h3><?php esc_html_e( 'Adres korespondencyjny', 'estate-office-crm' ); ?></h3>
                <p>
                    <label><input type="checkbox" name="correspondence_same" value="1" <?php checked( $correspondence_same ); ?>> <?php esc_html_e( 'Adres korespondencyjny taki sam', 'estate-office-crm' ); ?></label>
                </p>
                <div class="eo-crm-grid eo-crm-correspondence" <?php echo $correspondence_same ? 'style="display:none"' : ''; ?>>
                    <p><label><span><?php esc_html_e( 'Ulica', 'estate-office-crm' ); ?></span><input type="text" name="correspondence_street" value="<?php echo esc_attr( $correspondence['street'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Numer', 'estate-office-crm' ); ?></span><input type="text" name="correspondence_number" value="<?php echo esc_attr( $correspondence['number'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Lokal', 'estate-office-crm' ); ?></span><input type="text" name="correspondence_unit" value="<?php echo esc_attr( $correspondence['unit'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Kod pocztowy', 'estate-office-crm' ); ?></span><input type="text" name="correspondence_postal_code" value="<?php echo esc_attr( $correspondence['postal_code'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></span><input type="text" name="correspondence_city" value="<?php echo esc_attr( $correspondence['city'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Kraj', 'estate-office-crm' ); ?></span><input type="text" name="correspondence_country" value="<?php echo esc_attr( $correspondence['country'] ?? '' ); ?>"></label></p>
                </div>
                <?php submit_button( $is_edit ? __( 'Zapisz zmiany', 'estate-office-crm' ) : __( 'Zapisz klienta', 'estate-office-crm' ) ); ?>
            </form>
        </section>
        <section class="eo-crm-list">
            <h2><?php esc_html_e( 'Lista klientów', 'estate-office-crm' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nazwa klienta', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Telefon', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'E-mail', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Typ', 'estate-office-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $clients ) ) : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'Brak klientów do wyświetlenia.', 'estate-office-crm' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $clients as $client ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $client['primary_name'] ?? '' ); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'client' => (int) $client['id'] ], admin_url( 'admin.php?page=estate-office-crm-clients' ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office-crm' ); ?></a></span>
                                        <?php if ( current_user_can( Capabilities::DELETE_RECORDS ) ) : ?>
                                            <span class="delete">
                                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'client' => (int) $client['id'] ], admin_url( 'admin.php?page=estate-office-crm-clients' ) ), 'eo_crm_delete_client_' . (int) $client['id'] ) ); ?>" class="delete"><?php esc_html_e( 'Usuń', 'estate-office-crm' ); ?></a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $client['contact_phone'] ?? '' ); ?></td>
                                <td>
                                    <?php if ( ! empty( $client['contact_email'] ) ) : ?>
                                        <a href="mailto:<?php echo esc_attr( $client['contact_email'] ); ?>"><?php echo esc_html( $client['contact_email'] ); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( 'company' === ( $client['client_type'] ?? '' ) ? __( 'Firma', 'estate-office-crm' ) : __( 'Osoba fizyczna', 'estate-office-crm' ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
