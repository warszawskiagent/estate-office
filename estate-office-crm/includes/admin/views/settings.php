<?php
/**
 * Settings view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

defined( 'ABSPATH' ) || exit;

$defaults = [
'google_maps_api_key'  => '',
'watermark_media_id'   => 0,
'office_logo_media_id' => 0,
'property_fields'      => [
[ 'key' => 'rooms', 'label' => __( 'Liczba pokoi', 'estate-office-crm' ), 'type' => 'number' ],
[ 'key' => 'price', 'label' => __( 'Cena', 'estate-office-crm' ), 'type' => 'number' ],
],
'contract_fields' => [
[ 'key' => 'commission_amount', 'label' => __( 'Prowizja', 'estate-office-crm' ), 'type' => 'number' ],
],
'client_fields' => [
[ 'key' => 'phone', 'label' => __( 'Telefon', 'estate-office-crm' ), 'type' => 'text' ],
],
];

$options = wp_parse_args( $options, $defaults );
settings_errors();
?>
<div class="wrap estate-office-crm-settings">
<h1><?php esc_html_e( 'Ustawienia', 'estate-office-crm' ); ?></h1>
<form method="post" action="options.php">
<?php settings_fields( 'estate_office_crm_settings' ); ?>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th scope="row"><label for="google_maps_api_key"><?php esc_html_e( 'Klucz API Map Google', 'estate-office-crm' ); ?></label></th>
<td>
<input name="estate_office_crm_settings[google_maps_api_key]" id="google_maps_api_key" type="text" class="regular-text" value="<?php echo esc_attr( $options['google_maps_api_key'] ?? '' ); ?>">
<p class="description"><?php esc_html_e( 'Używany do integracji z mapami Google.', 'estate-office-crm' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Znak wodny', 'estate-office-crm' ); ?></th>
<td>
<div class="eo-crm-media-control" data-target="watermark_media_id">
<button type="button" class="button eo-crm-open-media"><?php esc_html_e( 'Wybierz znak wodny', 'estate-office-crm' ); ?></button>
<input type="hidden" name="estate_office_crm_settings[watermark_media_id]" id="watermark_media_id" value="<?php echo esc_attr( $options['watermark_media_id'] ?? 0 ); ?>">
<div class="eo-crm-media-preview" id="watermark_media_preview"></div>
</div>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Logo biura', 'estate-office-crm' ); ?></th>
<td>
<div class="eo-crm-media-control" data-target="office_logo_media_id">
<button type="button" class="button eo-crm-open-media"><?php esc_html_e( 'Wybierz logo', 'estate-office-crm' ); ?></button>
<input type="hidden" name="estate_office_crm_settings[office_logo_media_id]" id="office_logo_media_id" value="<?php echo esc_attr( $options['office_logo_media_id'] ?? 0 ); ?>">
<div class="eo-crm-media-preview" id="office_logo_media_preview"></div>
</div>
</td>
</tr>
</tbody>
</table>

<h2><?php esc_html_e( 'Pola nieruchomości', 'estate-office-crm' ); ?></h2>
<?php $field_key = 'property_fields'; $field_values = $options['property_fields']; require __DIR__ . '/partials/dynamic-fields.php'; ?>

<h2><?php esc_html_e( 'Pola umów', 'estate-office-crm' ); ?></h2>
<?php $field_key = 'contract_fields'; $field_values = $options['contract_fields']; require __DIR__ . '/partials/dynamic-fields.php'; ?>

<h2><?php esc_html_e( 'Pola klientów', 'estate-office-crm' ); ?></h2>
<?php $field_key = 'client_fields'; $field_values = $options['client_fields']; require __DIR__ . '/partials/dynamic-fields.php'; ?>

<?php submit_button(); ?>
</form>
</div>
