<?php
namespace EstateOffice\Admin;

/**
 * Handles plugin settings registration.
 */
class Settings {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'admin_init', array( $this, 'register_settings' ) );
}

/**
 * Register settings sections and fields.
 *
 * @return void
 */
public function register_settings() {
register_setting( 'estate_office_settings', 'estate_office_google_api', array( $this, 'sanitize_text' ) );
register_setting( 'estate_office_settings', 'estate_office_watermark_id', 'absint' );
register_setting( 'estate_office_settings', 'estate_office_logo_id', 'absint' );
register_setting( 'estate_office_settings', 'estate_office_property_fields', array( $this, 'sanitize_fields_array' ) );
register_setting( 'estate_office_settings', 'estate_office_contract_fields', array( $this, 'sanitize_fields_array' ) );
register_setting( 'estate_office_settings', 'estate_office_client_fields', array( $this, 'sanitize_fields_array' ) );

add_settings_section(
'estate_office_api_section',
__( 'Integracje', 'estate-office' ),
'__return_false',
'estate_office_settings'
);

add_settings_field(
'estate_office_google_api',
__( 'Klucz Google Maps API', 'estate-office' ),
array( $this, 'render_text_field' ),
'estate_office_settings',
'estate_office_api_section',
array(
'id'    => 'estate_office_google_api',
'value' => get_option( 'estate_office_google_api', '' ),
)
);

add_settings_field(
'estate_office_watermark_id',
__( 'Znak wodny', 'estate-office' ),
array( $this, 'render_media_field' ),
'estate_office_settings',
'estate_office_api_section',
array(
'id'    => 'estate_office_watermark_id',
'value' => get_option( 'estate_office_watermark_id', 0 ),
)
);

add_settings_field(
'estate_office_logo_id',
__( 'Logo biura', 'estate-office' ),
array( $this, 'render_media_field' ),
'estate_office_settings',
'estate_office_api_section',
array(
'id'    => 'estate_office_logo_id',
'value' => get_option( 'estate_office_logo_id', 0 ),
)
);

add_settings_section(
'estate_office_fields_section',
__( 'Pola danych', 'estate-office' ),
array( $this, 'render_fields_section_intro' ),
'estate_office_settings'
);

add_settings_field(
'estate_office_property_fields',
__( 'Pola nieruchomości', 'estate-office' ),
array( $this, 'render_dynamic_fields' ),
'estate_office_settings',
'estate_office_fields_section',
array(
'id'    => 'estate_office_property_fields',
'value' => get_option( 'estate_office_property_fields', array() ),
)
);

add_settings_field(
'estate_office_contract_fields',
__( 'Pola umów', 'estate-office' ),
array( $this, 'render_dynamic_fields' ),
'estate_office_settings',
'estate_office_fields_section',
array(
'id'    => 'estate_office_contract_fields',
'value' => get_option( 'estate_office_contract_fields', array() ),
)
);

add_settings_field(
'estate_office_client_fields',
__( 'Pola klientów', 'estate-office' ),
array( $this, 'render_dynamic_fields' ),
'estate_office_settings',
'estate_office_fields_section',
array(
'id'    => 'estate_office_client_fields',
'value' => get_option( 'estate_office_client_fields', array() ),
)
);
}

/**
 * Sanitize simple text settings.
 *
 * @param string $value Input value.
 *
 * @return string
 */
public function sanitize_text( $value ) {
return sanitize_text_field( $value );
}

/**
 * Sanitize dynamic fields array.
 *
 * @param mixed $value Input value.
 *
 * @return array
 */
public function sanitize_fields_array( $value ) {
if ( ! is_array( $value ) ) {
return array();
}

return array_values(
array_filter(
array_map(
static function( $field ) {
return array(
'label' => sanitize_text_field( $field['label'] ?? '' ),
'key'   => sanitize_key( $field['key'] ?? '' ),
'type'  => sanitize_text_field( $field['type'] ?? 'text' ),
);
},
$value
),
static function( $field ) {
return ! empty( $field['label'] ) && ! empty( $field['key'] );
}
)
);
}

/**
 * Render text field markup.
 *
 * @param array $args Arguments.
 *
 * @return void
 */
public function render_text_field( array $args ) {
printf(
'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
esc_attr( $args['id'] ),
esc_attr( $args['value'] )
);
}

/**
 * Render media field with uploader button.
 *
 * @param array $args Arguments.
 *
 * @return void
 */
public function render_media_field( array $args ) {
$value = (int) $args['value'];
$image = $value ? wp_get_attachment_image( $value, array( 80, 80 ) ) : '';
printf(
'<div class="estate-office-media-field"><input type="hidden" id="%1$s" name="%1$s" value="%2$d" /><button type="button" class="button estate-office-media-upload" data-target="%1$s">%3$s</button><button type="button" class="button-link estate-office-media-remove" data-target="%1$s">%4$s</button><div class="estate-office-media-preview">%5$s</div></div>',
esc_attr( $args['id'] ),
$value,
esc_html__( 'Wybierz plik', 'estate-office' ),
esc_html__( 'Usuń', 'estate-office' ),
$image
);
}

/**
 * Render dynamic fields repeater.
 *
 * @param array $args Arguments.
 *
 * @return void
 */
public function render_dynamic_fields( array $args ) {
$fields = is_array( $args['value'] ) ? $args['value'] : array();
?>
<div class="estate-office-dynamic-fields" data-field-name="<?php echo esc_attr( $args['id'] ); ?>">
<table class="widefat">
<thead>
<tr>
<th><?php esc_html_e( 'Etykieta', 'estate-office' ); ?></th>
<th><?php esc_html_e( 'Klucz', 'estate-office' ); ?></th>
<th><?php esc_html_e( 'Typ', 'estate-office' ); ?></th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach ( $fields as $index => $field ) : ?>
<tr>
<td><input type="text" name="<?php echo esc_attr( $args['id'] ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" /></td>
<td><input type="text" name="<?php echo esc_attr( $args['id'] ); ?>[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ?? '' ); ?>" /></td>
<td>
<select name="<?php echo esc_attr( $args['id'] ); ?>[<?php echo esc_attr( $index ); ?>][type]">
<?php foreach ( array( 'text', 'number', 'select', 'checkbox', 'date' ) as $type ) : ?>
<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'] ?? '', $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
<?php endforeach; ?>
</select>
</td>
<td><button type="button" class="button-link estate-office-remove-row">&times;</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<button type="button" class="button estate-office-add-row" data-template="<?php echo esc_attr( wp_json_encode( array( 'label' => '', 'key' => '', 'type' => 'text' ) ) ); ?>"><?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?></button>
</div>
<?php
}

/**
 * Render helper text.
 *
 * @return void
 */
public function render_fields_section_intro() {
echo '<p>' . esc_html__( 'Zarządzaj dodatkowymi polami wykorzystywanymi w ofertach, umowach oraz profilach klientów.', 'estate-office' ) . '</p>';
}
}
