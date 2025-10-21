<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$notice_type = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
$message     = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>
<div class="wrap estate-office-agents">
    <h1><?php esc_html_e( 'Agenci Estate Office', 'estate-office' ); ?></h1>

    <?php if ( $message ) : ?>
        <div class="notice notice-<?php echo $notice_type === 'error' ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <div class="estate-office-agent-form" id="estate-office-agent-form">
        <h2><?php esc_html_e( 'Dodaj nowego agenta', 'estate-office' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'estate_office_agents_action', 'estate_office_nonce' ); ?>
            <input type="hidden" name="action" value="estate_office_add_agent" />

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="agent_username"><?php esc_html_e( 'Nazwa użytkownika', 'estate-office' ); ?></label></th>
                        <td><input type="text" name="agent_username" id="agent_username" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_email"><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label></th>
                        <td><input type="email" name="agent_email" id="agent_email" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_first_name"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label></th>
                        <td><input type="text" name="agent_first_name" id="agent_first_name" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_last_name"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label></th>
                        <td><input type="text" name="agent_last_name" id="agent_last_name" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_phone"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label></th>
                        <td><input type="tel" name="agent_phone" id="agent_phone" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_bio"><?php esc_html_e( 'Opis/Biografia', 'estate-office' ); ?></label></th>
                        <td><textarea name="agent_bio" id="agent_bio" rows="4" class="large-text"></textarea></td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( __( 'Dodaj agenta', 'estate-office' ) ); ?>
        </form>
    </div>

    <hr />

    <h2><?php esc_html_e( 'Lista agentów', 'estate-office' ); ?></h2>
    <?php if ( ! empty( $agents ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Kontakt', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Biografia', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $agents as $agent ) :
                    $phone = get_user_meta( $agent->ID, '_estate_office_phone', true );
                    $bio   = get_user_meta( $agent->ID, '_estate_office_bio', true );
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $agent->display_name ); ?></strong><br />
                            <small><?php echo esc_html( $agent->user_email ); ?></small>
                        </td>
                        <td>
                            <?php if ( $phone ) : ?>
                                <span class="estate-office-agent-phone"><?php echo esc_html( $phone ); ?></span><br />
                            <?php endif; ?>
                            <a href="mailto:<?php echo esc_attr( $agent->user_email ); ?>"><?php esc_html_e( 'Wyślij e-mail', 'estate-office' ); ?></a>
                        </td>
                        <td><?php echo wpautop( wp_kses_post( $bio ) ); ?></td>
                        <td>
                            <button type="button" class="button-link estate-office-toggle" data-target="agent-edit-<?php echo esc_attr( $agent->ID ); ?>">
                                <?php esc_html_e( 'Edytuj', 'estate-office' ); ?>
                            </button>
                            <a class="button-link" href="<?php echo esc_url( get_edit_user_link( $agent->ID ) ); ?>">
                                <?php esc_html_e( 'Profil użytkownika', 'estate-office' ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr id="agent-edit-<?php echo esc_attr( $agent->ID ); ?>" class="estate-office-agent-edit" style="display: none;">
                        <td colspan="4">
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'estate_office_agents_action', 'estate_office_nonce' ); ?>
                                <input type="hidden" name="action" value="estate_office_update_agent" />
                                <input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->ID ); ?>" />
                                <table class="form-table">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><label for="agent_first_name_<?php echo esc_attr( $agent->ID ); ?>"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label></th>
                                            <td><input type="text" name="agent_first_name" id="agent_first_name_<?php echo esc_attr( $agent->ID ); ?>" value="<?php echo esc_attr( $agent->first_name ); ?>" class="regular-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="agent_last_name_<?php echo esc_attr( $agent->ID ); ?>"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label></th>
                                            <td><input type="text" name="agent_last_name" id="agent_last_name_<?php echo esc_attr( $agent->ID ); ?>" value="<?php echo esc_attr( $agent->last_name ); ?>" class="regular-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="agent_email_<?php echo esc_attr( $agent->ID ); ?>"><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label></th>
                                            <td><input type="email" name="agent_email" id="agent_email_<?php echo esc_attr( $agent->ID ); ?>" value="<?php echo esc_attr( $agent->user_email ); ?>" class="regular-text" required /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="agent_phone_<?php echo esc_attr( $agent->ID ); ?>"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label></th>
                                            <td><input type="tel" name="agent_phone" id="agent_phone_<?php echo esc_attr( $agent->ID ); ?>" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="agent_bio_<?php echo esc_attr( $agent->ID ); ?>"><?php esc_html_e( 'Opis/Biografia', 'estate-office' ); ?></label></th>
                                            <td><textarea name="agent_bio" id="agent_bio_<?php echo esc_attr( $agent->ID ); ?>" rows="4" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php submit_button( __( 'Zapisz', 'estate-office' ) ); ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e( 'Brak agentów do wyświetlenia.', 'estate-office' ); ?></p>
    <?php endif; ?>
</div>
