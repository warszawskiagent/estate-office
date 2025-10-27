<?php
/**
 * Dynamic fields partial.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

defined( 'ABSPATH' ) || exit;

$field_key    = $field_key ?? 'property_fields';
$field_values = $field_values ?? [];
$field_name   = 'estate_office_crm_settings[' . $field_key . ']';
$index        = 0;
?>
<div class="eo-crm-dynamic-fields-wrapper" data-field-key="<?php echo esc_attr( $field_key ); ?>">
<div class="eo-crm-dynamic-fields-list">
<?php foreach ( $field_values as $field ) : ?>
<?php
$index++;
$key   = esc_attr( $field['key'] ?? '' );
$label = esc_attr( $field['label'] ?? '' );
$type  = esc_attr( $field['type'] ?? 'text' );
?>
<div class="eo-crm-dynamic-field" data-index="<?php echo esc_attr( $index ); ?>">
<p>
<label>
<span><?php esc_html_e( 'Klucz', 'estate-office-crm' ); ?></span>
<input type="text" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo $key; ?>" required>
</label>
</p>
<p>
<label>
<span><?php esc_html_e( 'Etykieta', 'estate-office-crm' ); ?></span>
<input type="text" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo $label; ?>" required>
</label>
</p>
<p>
<label>
<span><?php esc_html_e( 'Typ pola', 'estate-office-crm' ); ?></span>
<select name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $index ); ?>][type]">
<option value="text" <?php selected( $type, 'text' ); ?>><?php esc_html_e( 'Tekst', 'estate-office-crm' ); ?></option>
<option value="number" <?php selected( $type, 'number' ); ?>><?php esc_html_e( 'Liczba', 'estate-office-crm' ); ?></option>
<option value="select" <?php selected( $type, 'select' ); ?>><?php esc_html_e( 'Lista wyboru', 'estate-office-crm' ); ?></option>
<option value="checkbox" <?php selected( $type, 'checkbox' ); ?>><?php esc_html_e( 'Checkbox', 'estate-office-crm' ); ?></option>
<option value="textarea" <?php selected( $type, 'textarea' ); ?>><?php esc_html_e( 'Pole tekstowe', 'estate-office-crm' ); ?></option>
</select>
</label>
</p>
<button type="button" class="button-link-delete eo-crm-remove-field" aria-label="<?php esc_attr_e( 'Usuń pole', 'estate-office-crm' ); ?>">&times;</button>
</div>
<?php endforeach; ?>
</div>
<button type="button" class="button button-secondary eo-crm-add-field" data-field-name="<?php echo esc_attr( $field_name ); ?>"><?php esc_html_e( 'Dodaj pole', 'estate-office-crm' ); ?></button>
</div>
