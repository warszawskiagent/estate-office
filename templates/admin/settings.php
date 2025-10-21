<?php
/**
 * Settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Ustawienia Estate Office', 'estate-office' ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'estate_office_save_settings', 'estate_office_nonce' ); ?>
        <input type="hidden" name="action" value="estate_office_save_settings">

        <div class="estate-office-section">
            <h2><?php esc_html_e( 'Integracje', 'estate-office' ); ?></h2>
            <div class="estate-office-form-row">
                <div>
                    <label for="google_maps_api_key"><?php esc_html_e( 'Klucz API Google Maps', 'estate-office' ); ?></label>
                    <input type="text" name="google_maps_api_key" id="google_maps_api_key" value="<?php echo esc_attr( $google_maps_api_key ); ?>">
                </div>
                <div>
                    <label for="watermark_attachment_id"><?php esc_html_e( 'ID znaku wodnego', 'estate-office' ); ?></label>
                    <input type="number" name="watermark_attachment_id" id="watermark_attachment_id" value="<?php echo esc_attr( $watermark_attachment_id ); ?>">
                </div>
                <div>
                    <label for="office_logo_attachment_id"><?php esc_html_e( 'ID logo biura', 'estate-office' ); ?></label>
                    <input type="number" name="office_logo_attachment_id" id="office_logo_attachment_id" value="<?php echo esc_attr( $office_logo_attachment_id ); ?>">
                </div>
            </div>
        </div>

        <div class="estate-office-section">
            <h2><?php esc_html_e( 'Pola dynamiczne', 'estate-office' ); ?></h2>

            <?php
            $field_sets = [
                'property'  => [ 'label' => __( 'Pola nieruchomości', 'estate-office' ), 'value' => $property_fields ],
                'agreement' => [ 'label' => __( 'Pola umów', 'estate-office' ), 'value' => $agreement_fields ],
                'client'    => [ 'label' => __( 'Pola klientów', 'estate-office' ), 'value' => $client_fields ],
            ];
            ?>

            <?php foreach ( $field_sets as $key => $config ) : ?>
                <div class="estate-office-section">
                    <h3><?php echo esc_html( $config['label'] ); ?></h3>
                    <div class="estate-office-dynamic-fields" data-field-set="<?php echo esc_attr( $key ); ?>">
                        <div class="estate-office-dynamic-field-list">
                            <?php if ( ! empty( $config['value'] ) ) : ?>
                                <?php foreach ( $config['value'] as $field ) : ?>
                                    <div class="estate-office-dynamic-field">
                                        <input type="text" data-field="key" value="<?php echo esc_attr( $field['key'] ); ?>" placeholder="<?php esc_attr_e( 'Klucz', 'estate-office' ); ?>">
                                        <input type="text" data-field="label" value="<?php echo esc_attr( $field['label'] ); ?>" placeholder="<?php esc_attr_e( 'Etykieta', 'estate-office' ); ?>">
                                        <select data-field="type">
                                            <option value="text" <?php selected( $field['type'], 'text' ); ?>><?php esc_html_e( 'Tekst', 'estate-office' ); ?></option>
                                            <option value="number" <?php selected( $field['type'], 'number' ); ?>><?php esc_html_e( 'Liczba', 'estate-office' ); ?></option>
                                            <option value="select" <?php selected( $field['type'], 'select' ); ?>><?php esc_html_e( 'Lista', 'estate-office' ); ?></option>
                                        </select>
                                        <button type="button" data-action="remove-field" aria-label="<?php esc_attr_e( 'Usuń pole', 'estate-office' ); ?>">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="<?php echo esc_attr( $key ); ?>_dynamic_fields" value="<?php echo esc_attr( wp_json_encode( $config['value'] ) ); ?>">
                        <p><button type="button" class="button" data-action="add-field"><?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?></button></p>
                        <template>
                            <div class="estate-office-dynamic-field">
                                <input type="text" data-field="key" placeholder="<?php esc_attr_e( 'Klucz', 'estate-office' ); ?>">
                                <input type="text" data-field="label" placeholder="<?php esc_attr_e( 'Etykieta', 'estate-office' ); ?>">
                                <select data-field="type">
                                    <option value="text"><?php esc_html_e( 'Tekst', 'estate-office' ); ?></option>
                                    <option value="number"><?php esc_html_e( 'Liczba', 'estate-office' ); ?></option>
                                    <option value="select"><?php esc_html_e( 'Lista', 'estate-office' ); ?></option>
                                </select>
                                <button type="button" data-action="remove-field" aria-label="<?php esc_attr_e( 'Usuń pole', 'estate-office' ); ?>">&times;</button>
                            </div>
                        </template>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Zapisz ustawienia', 'estate-office' ); ?></button>
        </p>
    </form>
</div>
