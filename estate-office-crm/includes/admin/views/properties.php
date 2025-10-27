<?php
/**
 * Properties view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

use EstateOfficeCRM\Capabilities;

defined( 'ABSPATH' ) || exit;

$is_edit          = ! empty( $current_property );
$property_id      = $current_property['id'] ?? 0;
$contract_id      = $current_property['contract_id'] ?? 0;
$agent_id         = $current_property['agent_id'] ?? 0;
$transaction_type = $current_property['transaction_type'] ?? 'SPRZEDAŻ';
$property_type    = $current_property['property_type'] ?? 'MIESZKANIE';
$title            = $current_property['title'] ?? '';
$legal_status     = $current_property['legal_status'] ?? '';
$price            = $current_property['price'] ?? '';
$fee              = $current_property['administration_fee'] ?? '';
$size_total       = $current_property['size_total'] ?? '';
$price_per_sqm    = $current_property['price_per_sqm'] ?? '';
$rooms            = $current_property['rooms'] ?? '';
$bedrooms         = $current_property['bedrooms'] ?? '';
$bathrooms        = $current_property['bathrooms'] ?? '';
$storey           = $current_property['storey'] ?? '';
$floors           = $current_property['floors'] ?? '';
$build_year       = $current_property['build_year'] ?? '';
$lot_shape        = $current_property['lot_shape'] ?? '';
$lot_dimensions   = $current_property['lot_dimensions'] ?? [];
$description      = $current_property['description'] ?? '';
$tags             = $current_property['tags'] ?? [];
$address          = $current_property['address'] ?? [];
$export_web       = ! empty( $current_property['export_web'] );

$tag_options = [
    'nowa_oferta'   => __( 'Nowa oferta', 'estate-office-crm' ),
    'wylacznosc'    => __( 'Wyłączność', 'estate-office-crm' ),
    'sprzedane'     => __( 'Sprzedane', 'estate-office-crm' ),
    'wynajete'      => __( 'Wynajęte', 'estate-office-crm' ),
    'nowa_cena'     => __( 'Nowa cena', 'estate-office-crm' ),
    'bez_prowizji'  => __( 'Bez prowizji', 'estate-office-crm' ),
    'oferta_mls'    => __( 'Oferta MLS', 'estate-office-crm' ),
    'premium'       => __( 'Premium', 'estate-office-crm' ),
];

settings_errors( 'estate-office-crm-properties' );
?>
<div class="wrap estate-office-crm-properties">
    <h1><?php esc_html_e( 'Nieruchomości', 'estate-office-crm' ); ?></h1>
    <div class="eo-crm-flex">
        <section class="eo-crm-form">
            <h2><?php echo esc_html( $is_edit ? __( 'Edytuj nieruchomość', 'estate-office-crm' ) : __( 'Dodaj nieruchomość', 'estate-office-crm' ) ); ?></h2>
            <?php if ( $is_edit ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm-properties' ) ); ?>"><?php esc_html_e( 'Dodaj nową nieruchomość', 'estate-office-crm' ); ?></a>
            <?php endif; ?>
            <form method="post" action="" class="eo-crm-property-form">
                <?php wp_nonce_field( 'eo_crm_property_action', 'eo_crm_property_nonce' ); ?>
                <input type="hidden" name="property_id" value="<?php echo esc_attr( $property_id ); ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="contract_id"><?php esc_html_e( 'Umowa', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="contract_id" name="contract_id">
                                    <option value="0"><?php esc_html_e( '— wybierz umowę —', 'estate-office-crm' ); ?></option>
                                    <?php foreach ( $contracts as $contract ) : ?>
                                        <option value="<?php echo esc_attr( $contract['id'] ); ?>" <?php selected( (int) $contract_id, (int) $contract['id'] ); ?>>
                                            <?php echo esc_html( $contract['contract_number'] ?? '' ); ?>
                                        </option>
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
                                    <option value="SPRZEDAŻ" <?php selected( $transaction_type, 'SPRZEDAŻ' ); ?>><?php esc_html_e( 'Sprzedaż', 'estate-office-crm' ); ?></option>
                                    <option value="KUPNO" <?php selected( $transaction_type, 'KUPNO' ); ?>><?php esc_html_e( 'Kupno', 'estate-office-crm' ); ?></option>
                                    <option value="WYNAJEM" <?php selected( $transaction_type, 'WYNAJEM' ); ?>><?php esc_html_e( 'Wynajem', 'estate-office-crm' ); ?></option>
                                    <option value="NAJEM" <?php selected( $transaction_type, 'NAJEM' ); ?>><?php esc_html_e( 'Najem', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="property_type"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="property_type" name="property_type">
                                    <option value="MIESZKANIE" <?php selected( $property_type, 'MIESZKANIE' ); ?>><?php esc_html_e( 'Mieszkanie', 'estate-office-crm' ); ?></option>
                                    <option value="DOM" <?php selected( $property_type, 'DOM' ); ?>><?php esc_html_e( 'Dom', 'estate-office-crm' ); ?></option>
                                    <option value="DZIAŁKA" <?php selected( $property_type, 'DZIAŁKA' ); ?>><?php esc_html_e( 'Działka', 'estate-office-crm' ); ?></option>
                                    <option value="LOKAL H/U" <?php selected( $property_type, 'LOKAL H/U' ); ?>><?php esc_html_e( 'Lokal handlowo-usługowy', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="title"><?php esc_html_e( 'Tytuł oferty', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="legal_status"><?php esc_html_e( 'Stan prawny', 'estate-office-crm' ); ?></label></th>
                            <td><input type="text" id="legal_status" name="legal_status" class="regular-text" value="<?php echo esc_attr( $legal_status ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="price"><?php esc_html_e( 'Cena', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" step="0.01" id="price" name="price" value="<?php echo esc_attr( $price ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="administration_fee"><?php esc_html_e( 'Czynsz administracyjny', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" step="0.01" id="administration_fee" name="administration_fee" value="<?php echo esc_attr( $fee ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="size_total"><?php esc_html_e( 'Powierzchnia (m²)', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" step="0.01" id="size_total" name="size_total" value="<?php echo esc_attr( $size_total ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="price_per_sqm"><?php esc_html_e( 'Cena za m²', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" step="0.01" id="price_per_sqm" name="price_per_sqm" value="<?php echo esc_attr( $price_per_sqm ); ?>" readonly></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--rooms">
                            <th scope="row"><label for="rooms"><?php esc_html_e( 'Liczba pokoi', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" id="rooms" name="rooms" value="<?php echo esc_attr( $rooms ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--rooms">
                            <th scope="row"><label for="bedrooms"><?php esc_html_e( 'Liczba sypialni', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" id="bedrooms" name="bedrooms" value="<?php echo esc_attr( $bedrooms ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--rooms">
                            <th scope="row"><label for="bathrooms"><?php esc_html_e( 'Liczba łazienek', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" id="bathrooms" name="bathrooms" value="<?php echo esc_attr( $bathrooms ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--storey">
                            <th scope="row"><label for="storey"><?php esc_html_e( 'Piętro', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" id="storey" name="storey" value="<?php echo esc_attr( $storey ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--floors">
                            <th scope="row"><label for="floors"><?php esc_html_e( 'Liczba pięter', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" id="floors" name="floors" value="<?php echo esc_attr( $floors ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--build-year">
                            <th scope="row"><label for="build_year"><?php esc_html_e( 'Rok budowy', 'estate-office-crm' ); ?></label></th>
                            <td><input type="number" id="build_year" name="build_year" value="<?php echo esc_attr( $build_year ); ?>"></td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--lot">
                            <th scope="row"><label for="lot_shape"><?php esc_html_e( 'Kształt działki', 'estate-office-crm' ); ?></label></th>
                            <td>
                                <select id="lot_shape" name="lot_shape">
                                    <option value="" <?php selected( $lot_shape, '' ); ?>><?php esc_html_e( 'Wybierz', 'estate-office-crm' ); ?></option>
                                    <option value="REGULARNY" <?php selected( $lot_shape, 'REGULARNY' ); ?>><?php esc_html_e( 'Regularny', 'estate-office-crm' ); ?></option>
                                    <option value="NIEREGULARNY" <?php selected( $lot_shape, 'NIEREGULARNY' ); ?>><?php esc_html_e( 'Nieregularny', 'estate-office-crm' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--lot eo-crm-property-section--lot-regular">
                            <th scope="row"><?php esc_html_e( 'Wymiary działki', 'estate-office-crm' ); ?></th>
                            <td class="eo-crm-inline">
                                <input type="number" step="0.01" name="lot_length" value="<?php echo esc_attr( $lot_dimensions['length'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Długość', 'estate-office-crm' ); ?>">
                                <input type="number" step="0.01" name="lot_width" value="<?php echo esc_attr( $lot_dimensions['width'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Szerokość', 'estate-office-crm' ); ?>">
                            </td>
                        </tr>
                        <tr class="eo-crm-property-section eo-crm-property-section--lot eo-crm-property-section--lot-irregular">
                            <th scope="row"><label for="lot_description"><?php esc_html_e( 'Opis kształtu', 'estate-office-crm' ); ?></label></th>
                            <td><textarea id="lot_description" name="lot_description" rows="3" class="large-text"><?php echo esc_textarea( $lot_dimensions['notes'] ?? '' ); ?></textarea></td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php esc_html_e( 'Adres', 'estate-office-crm' ); ?></h3>
                <div class="eo-crm-grid">
                    <p><label><span><?php esc_html_e( 'Ulica', 'estate-office-crm' ); ?></span><input type="text" name="address_street" value="<?php echo esc_attr( $address['street'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Numer', 'estate-office-crm' ); ?></span><input type="text" name="address_number" value="<?php echo esc_attr( $address['number'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Lokal', 'estate-office-crm' ); ?></span><input type="text" name="address_unit" value="<?php echo esc_attr( $address['unit'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Kod pocztowy', 'estate-office-crm' ); ?></span><input type="text" name="address_postal_code" value="<?php echo esc_attr( $address['postal_code'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Dzielnica', 'estate-office-crm' ); ?></span><input type="text" name="address_district" value="<?php echo esc_attr( $address['district'] ?? '' ); ?>"></label></p>
                    <p><label><span><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></span><input type="text" name="address_city" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>"></label></p>
                </div>
                <h3><?php esc_html_e( 'Opis', 'estate-office-crm' ); ?></h3>
                <?php wp_editor( $description, 'property_description', [ 'textarea_name' => 'description', 'textarea_rows' => 6 ] ); ?>
                <h3><?php esc_html_e( 'Znaczniki', 'estate-office-crm' ); ?></h3>
                <div class="eo-crm-tags">
                    <?php foreach ( $tag_options as $key => $label ) : ?>
                        <label><input type="checkbox" name="tags[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $tags, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                </div>
                <p><label><input type="checkbox" name="export_web" value="1" <?php checked( $export_web ); ?>> <?php esc_html_e( 'Eksport na WWW', 'estate-office-crm' ); ?></label></p>
                <?php submit_button( $is_edit ? __( 'Zapisz nieruchomość', 'estate-office-crm' ) : __( 'Dodaj nieruchomość', 'estate-office-crm' ) ); ?>
            </form>
        </section>
        <section class="eo-crm-list">
            <h2><?php esc_html_e( 'Lista nieruchomości', 'estate-office-crm' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Tytuł', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Typ', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Cena', 'estate-office-crm' ); ?></th>
                        <th><?php esc_html_e( 'Powierzchnia', 'estate-office-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $properties ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'Brak nieruchomości do wyświetlenia.', 'estate-office-crm' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $properties as $property ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $property['title'] ?? '' ); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'property' => (int) $property['id'] ], admin_url( 'admin.php?page=estate-office-crm-properties' ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office-crm' ); ?></a></span>
                                        <?php if ( current_user_can( Capabilities::DELETE_RECORDS ) ) : ?>
                                            <span class="delete"><a class="delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'property' => (int) $property['id'] ], admin_url( 'admin.php?page=estate-office-crm-properties' ) ), 'eo_crm_delete_property_' . (int) $property['id'] ) ); ?>"><?php esc_html_e( 'Usuń', 'estate-office-crm' ); ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $property['property_type'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $property['price'] ? number_format_i18n( (float) $property['price'], 2 ) : '—' ); ?></td>
                                <td><?php echo esc_html( $property['size_total'] ? number_format_i18n( (float) $property['size_total'], 2 ) . ' m²' : '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
