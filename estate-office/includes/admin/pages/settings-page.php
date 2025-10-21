<?php
/**
 * Settings page renderer.
 *
 * @package EstateOffice\Admin\Pages
 */

namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders plugin settings page.
 */
class SettingsPage {
/**
 * Render settings page.
 */
public static function render() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Brak uprawnień do przeglądania tej strony.', 'estateoffice' ) );
}
?>
<div class="wrap">
<h1><?php esc_html_e( 'Ustawienia EstateOffice', 'estateoffice' ); ?></h1>
<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
<?php
settings_fields( 'estateoffice_settings_group' );
do_settings_sections( 'estateoffice-settings' );
submit_button( __( 'Zapisz ustawienia', 'estateoffice' ) );
?>
</form>
</div>
<?php
}
}
