<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! current_user_can( 'estate_office_manage_agents' ) ) {
wp_die( esc_html__( 'Brak uprawnień do zarządzania agentami.', 'estate-office' ) );
}

$agents = get_users(
array(
'role__in' => array( EstateOffice\Roles::ROLE_AGENT ),
'orderby' => 'display_name',
)
);
?>
<div class="wrap estate-office estate-office-agents">
<h1><?php esc_html_e( 'Agenci', 'estate-office' ); ?></h1>
<?php if ( isset( $_GET['updated'] ) ) : ?>
<div class="notice notice-success"><p><?php esc_html_e( 'Agent zapisany pomyślnie.', 'estate-office' ); ?></p></div>
<?php endif; ?>
<div class="estate-office-grid">
<div class="estate-office-card">
<h2><?php esc_html_e( 'Dodaj / Edytuj Agenta', 'estate-office' ); ?></h2>
<form method="post">
<?php wp_nonce_field( 'estate_office_agent', 'estate_office_agent_nonce' ); ?>
<table class="form-table">
<tbody>
<tr>
<th scope="row"><label for="user_id"><?php esc_html_e( 'Wybierz agenta', 'estate-office' ); ?></label></th>
<td>
<select name="user_id" id="user_id">
<option value="0"><?php esc_html_e( 'Nowy agent', 'estate-office' ); ?></option>
<?php foreach ( $agents as $agent ) : ?>
<option value="<?php echo esc_attr( $agent->ID ); ?>"><?php echo esc_html( $agent->display_name ); ?></option>
<?php endforeach; ?>
</select>
</td>
</tr>
<tr>
<th scope="row"><label for="first_name"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label></th>
<td><input type="text" id="first_name" name="first_name" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="last_name"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label></th>
<td><input type="text" id="last_name" name="last_name" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="email"><?php esc_html_e( 'E-mail', 'estate-office' ); ?></label></th>
<td><input type="email" id="email" name="email" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="phone"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label></th>
<td><input type="text" id="phone" name="phone" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="bio"><?php esc_html_e( 'Opis / Biografia', 'estate-office' ); ?></label></th>
<td><?php wp_editor( '', 'bio', array( 'textarea_name' => 'bio', 'textarea_rows' => 6 ) ); ?></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Zdjęcie profilowe', 'estate-office' ); ?></th>
<td>
<input type="hidden" name="avatar_id" id="avatar_id" value="0" />
<button type="button" class="button estate-office-media-upload" data-target="avatar_id"><?php esc_html_e( 'Wybierz zdjęcie', 'estate-office' ); ?></button>
<div class="estate-office-media-preview"></div>
</td>
</tr>
</tbody>
</table>
<?php submit_button( __( 'Zapisz agenta', 'estate-office' ) ); ?>
</form>
</div>
<div class="estate-office-card">
<h2><?php esc_html_e( 'Lista agentów', 'estate-office' ); ?></h2>
<table class="widefat">
<thead>
<tr>
<th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
<th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
<th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ( $agents as $agent ) : ?>
<tr>
<td><?php echo esc_html( $agent->display_name ); ?></td>
<td><a href="mailto:<?php echo esc_attr( $agent->user_email ); ?>"><?php echo esc_html( $agent->user_email ); ?></a></td>
<td><?php echo esc_html( get_user_meta( $agent->ID, 'estate_office_phone', true ) ); ?></td>
</tr>
<?php endforeach; ?>
<?php if ( empty( $agents ) ) : ?>
<tr>
<td colspan="3"><?php esc_html_e( 'Brak dodanych agentów.', 'estate-office' ); ?></td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
