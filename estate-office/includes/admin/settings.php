<?php
/**
 * Settings registration handler.
 *
 * @package EstateOffice\Admin
 */

namespace EstateOffice\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin settings registration.
 */
class Settings {
/**
 * Settings option name.
 */
const OPTION_NAME = 'estateoffice_settings';

/**
 * Register settings, sections and fields.
 */
public static function register_settings() {
register_setting(
'estateoffice_settings_group',
self::OPTION_NAME,
[
'sanitize_callback' => [ self::class, 'sanitize_settings' ],
'default'           => [],
]
);

add_settings_section(
'estateoffice_settings_general',
__( 'Ustawienia ogólne', 'estateoffice' ),
function () {
printf( '<p>%s</p>', esc_html__( 'Skonfiguruj integracje i podstawowe elementy CRM.', 'estateoffice' ) );
},
'estateoffice-settings'
);

add_settings_field(
'google_maps_api',
__( 'Klucz API Map Google', 'estateoffice' ),
[ self::class, 'render_text_field' ],
'estateoffice-settings',
'estateoffice_settings_general',
[
'label_for'   => 'estateoffice_google_maps_api',
'option_key'  => 'google_maps_api',
'placeholder' => __( 'Wklej klucz API z Google Cloud Console', 'estateoffice' ),
]
);

add_settings_field(
'watermark',
__( 'Znak wodny', 'estateoffice' ),
[ self::class, 'render_media_field' ],
'estateoffice-settings',
'estateoffice_settings_general',
[
'label_for'  => 'estateoffice_watermark_id',
'option_key' => 'watermark_id',
]
);

add_settings_field(
'logo',
__( 'Logo biura', 'estateoffice' ),
[ self::class, 'render_media_field' ],
'estateoffice-settings',
'estateoffice_settings_general',
[
'label_for'  => 'estateoffice_logo_id',
'option_key' => 'logo_id',
]
);

add_settings_section(
'estateoffice_settings_fields',
__( 'Pola konfigurowalne', 'estateoffice' ),
function () {
printf( '<p>%s</p>', esc_html__( 'Definiuj dodatkowe pola dla nieruchomości, umów i klientów. Rozszerzone zarządzanie pojawi się w kolejnych wersjach.', 'estateoffice' ) );
},
'estateoffice-settings'
);

$fields = [
'property_fields' => __( 'Pola nieruchomości', 'estateoffice' ),
'contract_fields' => __( 'Pola umów', 'estateoffice' ),
'client_fields'   => __( 'Pola klientów', 'estateoffice' ),
];

foreach ( $fields as $key => $label ) {
add_settings_field(
$key,
$label,
[ self::class, 'render_repeater_placeholder' ],
'estateoffice-settings',
'estateoffice_settings_fields',
[
'option_key' => $key,
]
);
}
}

/**
 * Sanitize settings values.
 *
 * @param array $value Raw option value.
 * @return array
 */
public static function sanitize_settings( $value ) {
if ( ! is_array( $value ) ) {
return [];
}

$sanitized = [];

$sanitized['google_maps_api'] = isset( $value['google_maps_api'] ) ? sanitize_text_field( $value['google_maps_api'] ) : '';
$sanitized['watermark_id']    = isset( $value['watermark_id'] ) ? absint( $value['watermark_id'] ) : 0;
$sanitized['logo_id']         = isset( $value['logo_id'] ) ? absint( $value['logo_id'] ) : 0;

$repeaters = [ 'property_fields', 'contract_fields', 'client_fields' ];
foreach ( $repeaters as $repeater ) {
if ( empty( $value[ $repeater ] ) || ! is_array( $value[ $repeater ] ) ) {
$sanitized[ $repeater ] = [];
continue;
}

$sanitized[ $repeater ] = array_values(
array_filter(
array_map( 'sanitize_text_field', $value[ $repeater ] ),
function ( $field ) {
return '' !== $field;
}
)
);
}

return $sanitized;
}

/**
 * Render text field.
 *
 * @param array $args Field arguments.
 */
public static function render_text_field( $args ) {
$options = get_option( self::OPTION_NAME, [] );
$value   = isset( $options[ $args['option_key'] ] ) ? $options[ $args['option_key'] ] : '';
printf(
'<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" placeholder="%5$s" />',
esc_attr( $args['label_for'] ),
esc_attr( self::OPTION_NAME ),
esc_attr( $args['option_key'] ),
esc_attr( $value ),
esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
);
}

/**
 * Render media field with uploader button.
 *
 * @param array $args Field arguments.
 */
public static function render_media_field( $args ) {
$options = get_option( self::OPTION_NAME, [] );
$value   = isset( $options[ $args['option_key'] ] ) ? (int) $options[ $args['option_key'] ] : 0;
$image   = $value ? wp_get_attachment_image( $value, 'thumbnail' ) : '';
printf( '<div class="estateoffice-media-field" data-target="%1$s">', esc_attr( $args['label_for'] ) );
printf( '<input type="hidden" id="%1$s" name="%2$s[%3$s]" value="%4$d" />', esc_attr( $args['label_for'] ), esc_attr( self::OPTION_NAME ), esc_attr( $args['option_key'] ), $value );
printf( '<div class="estateoffice-media-preview">%s</div>', $image ? $image : '<span>' . esc_html__( 'Brak pliku', 'estateoffice' ) . '</span>' );
printf( '<button type="button" class="button estateoffice-media-upload">%s</button>', esc_html__( 'Wybierz plik', 'estateoffice' ) );
if ( $value ) {
printf( '<button type="button" class="button link-delete estateoffice-media-remove">%s</button>', esc_html__( 'Usuń', 'estateoffice' ) );
}
echo '</div>';
}

/**
 * Render repeater placeholder.
 *
 * @param array $args Field arguments.
 */
public static function render_repeater_placeholder( $args ) {
$options = get_option( self::OPTION_NAME, [] );
$items   = isset( $options[ $args['option_key'] ] ) ? (array) $options[ $args['option_key'] ] : [];
?>
<div class="estateoffice-repeater" data-field="<?php echo esc_attr( $args['option_key'] ); ?>">
<p class="description"><?php esc_html_e( 'Dodawanie i usuwanie pól zostanie rozbudowane w kolejnych wersjach. Aktualnie możesz dodać listę etykiet.', 'estateoffice' ); ?></p>
<div class="estateoffice-repeater-items">
<?php if ( empty( $items ) ) : ?>
<div class="estateoffice-repeater-item">
<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $args['option_key'] ); ?>][]" value="" class="regular-text" />
</div>
<?php else : ?>
<?php foreach ( $items as $item ) : ?>
<div class="estateoffice-repeater-item">
<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $args['option_key'] ); ?>][]" value="<?php echo esc_attr( $item ); ?>" class="regular-text" />
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<button type="button" class="button estateoffice-repeater-add"><?php esc_html_e( 'Dodaj pole', 'estateoffice' ); ?></button>
</div>
<?php
}
}
