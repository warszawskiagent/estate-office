<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Brak uprawnień do zarządzania ustawieniami.', 'estate-office' ) );
}
?>
<div class="wrap estate-office estate-office-settings">
<h1><?php esc_html_e( 'Ustawienia Estate Office CRM', 'estate-office' ); ?></h1>
<form method="post" action="options.php">
<?php
settings_fields( 'estate_office_settings' );
do_settings_sections( 'estate_office_settings' );
submit_button();
?>
</form>
</div>
