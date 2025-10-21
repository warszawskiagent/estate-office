<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Frontend\Agents as FrontendAgents;
use EstateOffice\Meta\Keys;
use WP_User;
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

        if ( isset( $_POST['estate_office_update_agent'] ) ) {
            $this->handle_agent_update();
        }

        $editing = null;

        if ( isset( $_GET['edit_agent'] ) ) {
            $editing_id = (int) $_GET['edit_agent'];

            if ( $editing_id > 0 ) {
                $editing = get_user_by( 'ID', $editing_id );

                if ( ! $editing || ! in_array( 'estate_office_agent', (array) $editing->roles, true ) ) {
                    add_settings_error( 'estate-office-agents', 'agent_missing', __( 'Nie znaleziono wskazanego agenta.', 'estate-office' ) );
                    $editing = null;
                }
            }
        }

        settings_errors( 'estate-office-agents' );

        if ( $editing instanceof WP_User ) {
            $this->render_agent_edit_form( $editing );
        } else {
            $this->render_agent_form();
        }

        $this->render_agents_table();

        $this->render_footer();
    }

    /**
     * Handles agent creation submissions.
     */
    private function handle_agent_creation(): void {
        check_admin_referer( 'estate_office_add_agent', 'estate_office_add_agent_nonce' );

        $email    = sanitize_email( wp_unslash( $_POST['agent_email'] ?? '' ) );
        $first    = sanitize_text_field( wp_unslash( $_POST['agent_first_name'] ?? '' ) );
        $last     = sanitize_text_field( wp_unslash( $_POST['agent_last_name'] ?? '' ) );
        $phone    = sanitize_text_field( wp_unslash( $_POST['agent_phone'] ?? '' ) );
        $bio      = sanitize_textarea_field( wp_unslash( $_POST['agent_bio'] ?? '' ) );
        $photo_id = isset( $_POST['agent_photo_id'] ) ? (int) $_POST['agent_photo_id'] : 0;
        $password = wp_generate_password( 20, true, true );
        $username = sanitize_user( wp_unslash( $_POST['agent_username'] ?? '' ), true );

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
                'display_name' => trim( $first . ' ' . $last ) ?: $username,
            ]
        );

        if ( is_wp_error( $user_id ) ) {
            add_settings_error( 'estate-office-agents', 'create_failed', $user_id->get_error_message() );
            return;
        }

        update_user_meta( $user_id, Keys::AGENT_PHONE, $phone );
        update_user_meta( $user_id, Keys::AGENT_BIO, $bio );

        if ( $photo_id > 0 ) {
            update_user_meta( $user_id, Keys::AGENT_PHOTO_ID, $photo_id );
        }

        if ( function_exists( 'wp_send_new_user_notifications' ) ) {
            wp_send_new_user_notifications( $user_id, 'both' );
        }

        add_settings_error( 'estate-office-agents', 'created', __( 'Agent został utworzony, dane logowania wysłano e-mailem.', 'estate-office' ), 'updated' );
    }

    /**
     * Handles agent update submissions.
     */
    private function handle_agent_update(): void {
        check_admin_referer( 'estate_office_update_agent', 'estate_office_update_agent_nonce' );

        $user_id = isset( $_POST['agent_id'] ) ? (int) $_POST['agent_id'] : 0;

        if ( $user_id <= 0 ) {
            add_settings_error( 'estate-office-agents', 'missing_agent', __( 'Nieprawidłowy identyfikator agenta.', 'estate-office' ) );
            return;
        }

        $user = get_user_by( 'ID', $user_id );

        if ( ! $user || ! in_array( 'estate_office_agent', (array) $user->roles, true ) ) {
            add_settings_error( 'estate-office-agents', 'missing_agent', __( 'Nie znaleziono wskazanego agenta.', 'estate-office' ) );
            return;
        }

        $email    = sanitize_email( wp_unslash( $_POST['agent_email'] ?? '' ) );
        $first    = sanitize_text_field( wp_unslash( $_POST['agent_first_name'] ?? '' ) );
        $last     = sanitize_text_field( wp_unslash( $_POST['agent_last_name'] ?? '' ) );
        $phone    = sanitize_text_field( wp_unslash( $_POST['agent_phone'] ?? '' ) );
        $bio      = sanitize_textarea_field( wp_unslash( $_POST['agent_bio'] ?? '' ) );
        $photo_id = isset( $_POST['agent_photo_id'] ) ? (int) $_POST['agent_photo_id'] : 0;

        if ( empty( $email ) ) {
            add_settings_error( 'estate-office-agents', 'missing_email', __( 'Adres e-mail jest wymagany.', 'estate-office' ) );
            return;
        }

        $display_name = trim( $first . ' ' . $last );

        if ( '' === $display_name ) {
            $display_name = $user->display_name ?: $user->user_login;
        }

        $updated = wp_update_user(
            [
                'ID'           => $user_id,
                'user_email'   => $email,
                'first_name'   => $first,
                'last_name'    => $last,
                'display_name' => $display_name,
            ]
        );

        if ( is_wp_error( $updated ) ) {
            add_settings_error( 'estate-office-agents', 'update_failed', $updated->get_error_message() );
            return;
        }

        update_user_meta( $user_id, Keys::AGENT_PHONE, $phone );
        update_user_meta( $user_id, Keys::AGENT_BIO, $bio );

        if ( $photo_id > 0 ) {
            update_user_meta( $user_id, Keys::AGENT_PHOTO_ID, $photo_id );
        } else {
            delete_user_meta( $user_id, Keys::AGENT_PHOTO_ID );
        }

        add_settings_error( 'estate-office-agents', 'updated', __( 'Profil agenta został zaktualizowany.', 'estate-office' ), 'updated' );
    }

    /**
     * Renders the agent creation form.
     */
    private function render_agent_form(): void {
        ?>
        <form method="post" class="estate-office-agent-form">
            <?php wp_nonce_field( 'estate_office_add_agent', 'estate_office_add_agent_nonce' ); ?>
            <input type="hidden" name="estate_office_new_agent" value="1" />
            <div class="estate-office-agent-form__layout">
                <div>
                    <?php $this->render_agent_photo_field( 0 ); ?>
                </div>
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
     * Renders edit form for existing agent.
     */
    private function render_agent_edit_form( WP_User $user ): void {
        $phone    = get_user_meta( $user->ID, Keys::AGENT_PHONE, true );
        $bio      = get_user_meta( $user->ID, Keys::AGENT_BIO, true );
        $photo_id = (int) get_user_meta( $user->ID, Keys::AGENT_PHOTO_ID, true );
        $cancel   = admin_url( 'admin.php?page=estate-office-agents' );
        ?>
        <form method="post" class="estate-office-agent-form">
            <h2><?php esc_html_e( 'Edycja agenta', 'estate-office' ); ?></h2>
            <?php wp_nonce_field( 'estate_office_update_agent', 'estate_office_update_agent_nonce' ); ?>
            <input type="hidden" name="estate_office_update_agent" value="1" />
            <input type="hidden" name="agent_id" value="<?php echo esc_attr( $user->ID ); ?>" />
            <div class="estate-office-agent-form__layout">
                <div>
                    <?php $this->render_agent_photo_field( $photo_id ); ?>
                </div>
                <div class="estate-office-grid">
                    <p>
                        <label class="estate-office-label"><?php esc_html_e( 'Nazwa użytkownika', 'estate-office' ); ?></label>
                        <input type="text" value="<?php echo esc_attr( $user->user_login ); ?>" class="regular-text" disabled />
                    </p>
                    <p>
                        <label for="agent_email" class="estate-office-label"><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label>
                        <input type="email" id="agent_email" name="agent_email" class="regular-text" value="<?php echo esc_attr( $user->user_email ); ?>" required />
                    </p>
                    <p>
                        <label for="agent_first_name" class="estate-office-label"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                        <input type="text" id="agent_first_name" name="agent_first_name" class="regular-text" value="<?php echo esc_attr( $user->first_name ); ?>" />
                    </p>
                    <p>
                        <label for="agent_last_name" class="estate-office-label"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                        <input type="text" id="agent_last_name" name="agent_last_name" class="regular-text" value="<?php echo esc_attr( $user->last_name ); ?>" />
                    </p>
                    <p>
                        <label for="agent_phone" class="estate-office-label"><?php esc_html_e( 'Telefon kontaktowy', 'estate-office' ); ?></label>
                        <input type="text" id="agent_phone" name="agent_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>" />
                    </p>
                </div>
            </div>
            <p>
                <label for="agent_bio" class="estate-office-label"><?php esc_html_e( 'Opis/Biografia', 'estate-office' ); ?></label>
                <textarea id="agent_bio" name="agent_bio" rows="5" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea>
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Zapisz zmiany', 'estate-office' ); ?></button>
                <a href="<?php echo esc_url( $cancel ); ?>" class="button button-secondary"><?php esc_html_e( 'Anuluj', 'estate-office' ); ?></a>
            </p>
        </form>
        <?php
    }

    /**
     * Outputs shared photo field used in forms.
     */
    private function render_agent_photo_field( int $photo_id ): void {
        $preview = $photo_id ? wp_get_attachment_image_url( $photo_id, 'medium' ) : '';
        ?>
        <div class="estate-office-media-field estate-office-media-field--portrait">
            <div class="estate-office-media-preview">
                <?php if ( $preview ) : ?>
                    <img src="<?php echo esc_url( $preview ); ?>" alt="" />
                <?php else : ?>
                    <span class="description"><?php esc_html_e( 'Brak zdjęcia', 'estate-office' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="estate-office-media-actions">
                <input type="hidden" name="agent_photo_id" value="<?php echo esc_attr( $photo_id ); ?>" />
                <button type="button" class="button estate-office-media-upload" data-label="<?php esc_attr_e( 'Wybierz zdjęcie', 'estate-office' ); ?>"><?php esc_html_e( 'Wybierz zdjęcie', 'estate-office' ); ?></button>
                <button type="button" class="button button-link-delete estate-office-media-remove"><?php esc_html_e( 'Usuń zdjęcie', 'estate-office' ); ?></button>
            </div>
        </div>
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
        <table class="widefat fixed estate-office-agents">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Kontakt', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Opis', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
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
                        $phone        = get_user_meta( $user->ID, Keys::AGENT_PHONE, true );
                        $bio          = get_user_meta( $user->ID, Keys::AGENT_BIO, true );
                        $photo_id     = (int) get_user_meta( $user->ID, Keys::AGENT_PHOTO_ID, true );
                        $photo_url    = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
                        $first_name   = get_user_meta( $user->ID, 'first_name', true );
                        $last_name    = get_user_meta( $user->ID, 'last_name', true );
                        $display_name = $user->display_name ?: trim( $first_name . ' ' . $last_name );
                        $profile_url  = FrontendAgents::profile_url_from_user( $user );
                        $actions      = [];
                        $edit_link    = add_query_arg(
                            [
                                'page'       => 'estate-office-agents',
                                'edit_agent' => $user->ID,
                            ],
                            admin_url( 'admin.php' )
                        );

                        $actions[] = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html__( 'Edytuj', 'estate-office' ) );

                        if ( $profile_url ) {
                            $actions[] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $profile_url ), esc_html__( 'Zobacz profil', 'estate-office' ) );
                        }

                        $bio_excerpt = $bio ? wp_trim_words( wp_strip_all_tags( $bio ), 40, '…' ) : '';
                        ?>
                        <tr>
                            <td class="estate-office-agent-table__person">
                                <?php if ( $photo_url ) : ?>
                                    <img src="<?php echo esc_url( $photo_url ); ?>" alt="" class="estate-office-agent-table__avatar" />
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo esc_html( $display_name ); ?></strong>
                                    <div class="description"><?php echo esc_html( $user->user_login ); ?></div>
                                </div>
                            </td>
                            <td>
                                <div><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></div>
                                <?php if ( $phone ) : ?>
                                    <div><?php echo esc_html( $phone ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $bio_excerpt ? esc_html( $bio_excerpt ) : '—'; ?></td>
                            <td>
                                <div class="estate-office-table__actions">
                                    <?php echo wp_kses_post( implode( ' | ', $actions ) ); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}
