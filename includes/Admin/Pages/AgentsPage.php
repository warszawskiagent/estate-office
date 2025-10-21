<?php
namespace EstateOffice\Admin\Pages;

use WP_User_Query;

/**
 * Page dedicated to managing agents.
 */
class AgentsPage extends AbstractPage {
    public function render(): void {
        $this->render_header(
            __( 'Agenci biura', 'estate-office' ),
            __( 'Dodawaj i zarządzaj agentami posiadającymi dostęp do funkcji CRM.', 'estate-office' )
        );

        if ( isset( $_POST['estate_office_new_agent'] ) ) {
            $this->handle_agent_creation();
        }

        $this->render_agent_form();
        $this->render_agents_table();

        $this->render_footer();
    }

    /**
     * Handles agent creation submissions.
     */
    private function handle_agent_creation(): void {
        check_admin_referer( 'estate_office_add_agent', 'estate_office_add_agent_nonce' );

        $email     = sanitize_email( wp_unslash( $_POST['agent_email'] ?? '' ) );
        $first     = sanitize_text_field( wp_unslash( $_POST['agent_first_name'] ?? '' ) );
        $last      = sanitize_text_field( wp_unslash( $_POST['agent_last_name'] ?? '' ) );
        $phone     = sanitize_text_field( wp_unslash( $_POST['agent_phone'] ?? '' ) );
        $bio       = sanitize_textarea_field( wp_unslash( $_POST['agent_bio'] ?? '' ) );
        $password  = wp_generate_password( 20, true, true );
        $username  = sanitize_user( wp_unslash( $_POST['agent_username'] ?? '' ), true );

        if ( empty( $username ) || empty( $email ) ) {
            add_settings_error( 'estate-office-agents', 'missing_fields', __( 'Nazwa użytkownika oraz e-mail są wymagane.', 'estate-office' ) );
            return;
        }

        if ( username_exists( $username ) || email_exists( $email ) ) {
            add_settings_error( 'estate-office-agents', 'duplicate', __( 'Podana nazwa użytkownika lub e-mail już istnieją.', 'estate-office' ) );
            return;
        }

        $user_id = wp_insert_user(
            [
                'user_login' => $username,
                'user_pass'  => $password,
                'user_email' => $email,
                'first_name' => $first,
                'last_name'  => $last,
                'role'       => 'estate_office_agent',
            ]
        );

        if ( is_wp_error( $user_id ) ) {
            add_settings_error( 'estate-office-agents', 'create_failed', $user_id->get_error_message() );
            return;
        }

        update_user_meta( $user_id, 'estate_office_phone', $phone );
        update_user_meta( $user_id, 'estate_office_bio', $bio );

        if ( function_exists( 'wp_send_new_user_notifications' ) ) {
            wp_send_new_user_notifications( $user_id, 'both' );
        }

        add_settings_error( 'estate-office-agents', 'created', __( 'Agent został utworzony, dane logowania wysłano e-mailem.', 'estate-office' ), 'updated' );
    }

    /**
     * Renders the agent creation form.
     */
    private function render_agent_form(): void {
        settings_errors( 'estate-office-agents' );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'estate_office_add_agent', 'estate_office_add_agent_nonce' ); ?>
            <input type="hidden" name="estate_office_new_agent" value="1" />
            <div class="estate-office-grid">
                <p>
                    <label for="agent_username" class="estate-office-label"><?php esc_html_e( 'Nazwa użytkownika', 'estate-office' ); ?></label>
                    <input type="text" id="agent_username" name="agent_username" class="regular-text" required />
                </p>
                <p>
                    <label for="agent_email" class="estate-office-label"><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label>
                    <input type="email" id="agent_email" name="agent_email" class="regular-text" required />
                </p>
                <p>
                    <label for="agent_first_name" class="estate-office-label"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                    <input type="text" id="agent_first_name" name="agent_first_name" class="regular-text" />
                </p>
                <p>
                    <label for="agent_last_name" class="estate-office-label"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                    <input type="text" id="agent_last_name" name="agent_last_name" class="regular-text" />
                </p>
                <p>
                    <label for="agent_phone" class="estate-office-label"><?php esc_html_e( 'Telefon kontaktowy', 'estate-office' ); ?></label>
                    <input type="text" id="agent_phone" name="agent_phone" class="regular-text" />
                </p>
            </div>
            <p>
                <label for="agent_bio" class="estate-office-label"><?php esc_html_e( 'Opis/Biografia', 'estate-office' ); ?></label>
                <textarea id="agent_bio" name="agent_bio" rows="5" class="large-text"></textarea>
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj agenta', 'estate-office' ); ?></button>
            </p>
        </form>
        <?php
    }

    /**
     * Renders table with existing agents.
     */
    private function render_agents_table(): void {
        $query = new WP_User_Query(
            [
                'role'   => 'estate_office_agent',
                'fields' => 'all_with_meta',
            ]
        );
        ?>
        <h2><?php esc_html_e( 'Lista agentów', 'estate-office' ); ?></h2>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Imię i nazwisko', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Opis', 'estate-office' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $query->results ) ) : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'Brak agentów do wyświetlenia.', 'estate-office' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $query->results as $user ) : ?>
                        <?php
                        $phone        = get_user_meta( $user->ID, 'estate_office_phone', true );
                        $bio          = get_user_meta( $user->ID, 'estate_office_bio', true );
                        $first_name   = get_user_meta( $user->ID, 'first_name', true );
                        $last_name    = get_user_meta( $user->ID, 'last_name', true );
                        $display_name = $user->display_name ?: trim( $first_name . ' ' . $last_name );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $display_name ); ?></td>
                            <td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
                            <td><?php echo esc_html( $phone ); ?></td>
                            <td><?php echo wp_kses_post( wpautop( $bio ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}
