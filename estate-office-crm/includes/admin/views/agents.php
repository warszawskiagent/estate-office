<?php
/**
 * Agents view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

defined( 'ABSPATH' ) || exit;

settings_errors( 'estate-office-crm-agents' );
?>
<div class="wrap estate-office-crm-agents">
<h1><?php esc_html_e( 'Agenci', 'estate-office-crm' ); ?></h1>
<div class="eo-crm-flex">
<section class="eo-crm-form">
<h2><?php esc_html_e( 'Dodaj nowego agenta', 'estate-office-crm' ); ?></h2>
<form method="post" action="">
<?php wp_nonce_field( 'eo_crm_agent_action', 'eo_crm_agent_nonce' ); ?>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th scope="row"><label for="first_name"><?php esc_html_e( 'Imię', 'estate-office-crm' ); ?></label></th>
<td><input name="first_name" id="first_name" type="text" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="last_name"><?php esc_html_e( 'Nazwisko', 'estate-office-crm' ); ?></label></th>
<td><input name="last_name" id="last_name" type="text" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="email"><?php esc_html_e( 'Adres e-mail', 'estate-office-crm' ); ?></label></th>
<td><input name="email" id="email" type="email" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="phone"><?php esc_html_e( 'Telefon', 'estate-office-crm' ); ?></label></th>
<td><input name="phone" id="phone" type="text" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Zdjęcie profilowe', 'estate-office-crm' ); ?></th>
<td>
<div class="eo-crm-media-control" data-target="avatar_id">
<button type="button" class="button eo-crm-open-media"><?php esc_html_e( 'Wybierz zdjęcie', 'estate-office-crm' ); ?></button>
<input type="hidden" name="avatar_id" id="avatar_id" value="">
<div class="eo-crm-media-preview" id="avatar_preview"></div>
</div>
</td>
</tr>
<tr>
<th scope="row"><label for="biography"><?php esc_html_e( 'Biografia', 'estate-office-crm' ); ?></label></th>
<td><?php wp_editor( '', 'biography', [ 'textarea_name' => 'biography', 'textarea_rows' => 6 ] ); ?></td>
</tr>
</tbody>
</table>
<h3><?php esc_html_e( 'Adres', 'estate-office-crm' ); ?></h3>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th scope="row"><label for="address_line1"><?php esc_html_e( 'Ulica i numer', 'estate-office-crm' ); ?></label></th>
<td><input name="address_line1" id="address_line1" type="text" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="address_line2"><?php esc_html_e( 'Dodatkowe informacje', 'estate-office-crm' ); ?></label></th>
<td><input name="address_line2" id="address_line2" type="text" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="city"><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></label></th>
<td><input name="city" id="city" type="text" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="postal_code"><?php esc_html_e( 'Kod pocztowy', 'estate-office-crm' ); ?></label></th>
<td><input name="postal_code" id="postal_code" type="text" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="country"><?php esc_html_e( 'Kraj', 'estate-office-crm' ); ?></label></th>
<td><input name="country" id="country" type="text" class="regular-text"></td>
</tr>
</tbody>
</table>
<?php submit_button( __( 'Zapisz agenta', 'estate-office-crm' ) ); ?>
</form>
</section>
<section class="eo-crm-list">
<h2><?php esc_html_e( 'Lista agentów', 'estate-office-crm' ); ?></h2>
<table class="widefat fixed striped">
<thead>
<tr>
<th><?php esc_html_e( 'Imię i nazwisko', 'estate-office-crm' ); ?></th>
<th><?php esc_html_e( 'Telefon', 'estate-office-crm' ); ?></th>
<th><?php esc_html_e( 'E-mail', 'estate-office-crm' ); ?></th>
<th><?php esc_html_e( 'Miasto', 'estate-office-crm' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $agents ) ) : ?>
<tr>
<td colspan="4"><?php esc_html_e( 'Brak agentów do wyświetlenia.', 'estate-office-crm' ); ?></td>
</tr>
<?php else : ?>
<?php foreach ( $agents as $agent ) : ?>
<tr>
<td>
<strong><?php echo esc_html( trim( $agent->first_name . ' ' . $agent->last_name ) ); ?></strong>
<?php if ( ! empty( $agent->biography ) ) : ?>
<div class="description"><?php echo wp_kses_post( wp_trim_words( $agent->biography, 20 ) ); ?></div>
<?php endif; ?>
</td>
<td><?php echo esc_html( $agent->phone ); ?></td>
<td><a href="mailto:<?php echo esc_attr( $agent->email ); ?>"><?php echo esc_html( $agent->email ); ?></a></td>
<td><?php echo esc_html( $agent->city ); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</section>
</div>
</div>
