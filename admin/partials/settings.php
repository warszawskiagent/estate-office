<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Ustawienia Estate Office', 'estate-office' ); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'estate_office_settings' );
        do_settings_sections( 'estate_office_settings' );
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="estate_office_google_api_key"><?php esc_html_e( 'Klucz Google Maps API', 'estate-office' ); ?></label>
                    </th>
                    <td>
                        <input name="estate_office_google_api_key" id="estate_office_google_api_key" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'estate_office_google_api_key', '' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="estate_office_watermark"><?php esc_html_e( 'Znak wodny (ID załącznika)', 'estate-office' ); ?></label>
                    </th>
                    <td>
                        <input name="estate_office_watermark" id="estate_office_watermark" type="number" class="small-text" value="<?php echo esc_attr( get_option( 'estate_office_watermark', '' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="estate_office_logo"><?php esc_html_e( 'Logo (ID załącznika)', 'estate-office' ); ?></label>
                    </th>
                    <td>
                        <input name="estate_office_logo" id="estate_office_logo" type="number" class="small-text" value="<?php echo esc_attr( get_option( 'estate_office_logo', '' ) ); ?>" />
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
