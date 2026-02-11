<?php
if (! defined('ABSPATH')) {
    exit;
}

$settings = EstateOffice_Settings::all();
?>
<div class="wrap">
    <h1>Ustawienia EstateOffice</h1>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
        <div class="notice notice-success"><p>Ustawienia zapisane.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('estateoffice_save_settings'); ?>
        <input type="hidden" name="action" value="estateoffice_save_settings" />

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="google_maps_api_key">Google Maps API Key</label></th>
                <td>
                    <input type="text" class="regular-text" name="google_maps_api_key" id="google_maps_api_key"
                           value="<?php echo esc_attr((string) $settings['google_maps_api_key']); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="watermark_url">URL znaku wodnego</label></th>
                <td>
                    <input type="url" class="regular-text" name="watermark_url" id="watermark_url"
                           value="<?php echo esc_attr((string) $settings['watermark_url']); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="office_logo_url">URL logo biura</label></th>
                <td>
                    <input type="url" class="regular-text" name="office_logo_url" id="office_logo_url"
                           value="<?php echo esc_attr((string) $settings['office_logo_url']); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_custom_fields">Pola nieruchomości (JSON)</label></th>
                <td>
                    <textarea class="large-text code" rows="6" name="property_custom_fields" id="property_custom_fields"><?php echo esc_textarea((string) $settings['property_custom_fields']); ?></textarea>
                    <p class="description">Format: [{"key":"pole","label":"Etykieta","type":"text","required":true}]</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="agreement_custom_fields">Pola umów (JSON)</label></th>
                <td>
                    <textarea class="large-text code" rows="6" name="agreement_custom_fields" id="agreement_custom_fields"><?php echo esc_textarea((string) $settings['agreement_custom_fields']); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="client_custom_fields">Pola klientów (JSON)</label></th>
                <td>
                    <textarea class="large-text code" rows="6" name="client_custom_fields" id="client_custom_fields"><?php echo esc_textarea((string) $settings['client_custom_fields']); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button('Zapisz ustawienia'); ?>
    </form>
</div>
