<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Licencja Estate Office', 'estate-office' ); ?></h1>
    <form method="post">
        <?php wp_nonce_field( 'estate_office_save_license', 'estate_office_license_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="estate_office_license_key"><?php esc_html_e( 'Klucz licencyjny', 'estate-office' ); ?></label>
                    </th>
                    <td>
                        <input name="estate_office_license_key" id="estate_office_license_key" type="text" class="regular-text" value="" />
                        <p class="description"><?php esc_html_e( 'Wprowadź klucz otrzymany po zakupie wtyczki.', 'estate-office' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button( __( 'Aktywuj licencję', 'estate-office' ) ); ?>
    </form>
</div>
