<?php
namespace EstateOffice\Admin;

use EstateOffice\Roles;

/**
 * Manage the Agents admin page actions.
 */
class Agents_Page {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
}

/**
 * Handle agent profile save.
 *
 * @return void
 */
public function handle_form_submission() {
if ( ! isset( $_POST['estate_office_agent_nonce'] ) ) {
return;
}

if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['estate_office_agent_nonce'] ) ), 'estate_office_agent' ) ) {
return;
}

if ( ! current_user_can( 'estate_office_manage_agents' ) ) {
return;
}

$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

$data = array(
'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
'phone'      => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
'bio'        => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
'avatar_id'  => isset( $_POST['avatar_id'] ) ? absint( $_POST['avatar_id'] ) : 0,
);

if ( $user_id ) {
$this->update_agent( $user_id, $data );
} else {
$this->create_agent( $data );
}

wp_safe_redirect( add_query_arg( array( 'page' => 'estate-office-agents', 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
exit;
}

/**
 * Update agent profile.
 *
 * @param int   $user_id User ID.
 * @param array $data    Agent data.
 *
 * @return void
 */
protected function update_agent( $user_id, array $data ) {
wp_update_user(
array(
'ID'         => $user_id,
'first_name' => $data['first_name'],
'last_name'  => $data['last_name'],
'user_email' => $data['email'],
)
);

update_user_meta( $user_id, 'estate_office_phone', $data['phone'] );
update_user_meta( $user_id, 'estate_office_bio', $data['bio'] );
update_user_meta( $user_id, 'estate_office_avatar', $data['avatar_id'] );
}

/**
 * Create new agent user.
 *
 * @param array $data Agent data.
 *
 * @return void
 */
protected function create_agent( array $data ) {
if ( empty( $data['email'] ) ) {
return;
}

$password = wp_generate_password( 16, true );

$user_id = wp_insert_user(
array(
'user_login' => sanitize_user( $data['email'], true ),
'user_email' => $data['email'],
'user_pass'  => $password,
'first_name' => $data['first_name'],
'last_name'  => $data['last_name'],
'role'       => Roles::ROLE_AGENT,
)
);

if ( is_wp_error( $user_id ) ) {
return;
}

update_user_meta( $user_id, 'estate_office_phone', $data['phone'] );
update_user_meta( $user_id, 'estate_office_bio', $data['bio'] );
update_user_meta( $user_id, 'estate_office_avatar', $data['avatar_id'] );

wp_new_user_notification( $user_id, null, 'both' );
}
}
