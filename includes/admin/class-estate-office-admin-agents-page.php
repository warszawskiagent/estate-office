<?php
/**
 * Ekran zarządzania agentami EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Admin_Agents_Page
 */
class Estate_Office_Admin_Agents_Page {

    private const PAGE_SLUG = 'estate-office-agents';

    /**
     * Repozytorium agentów.
     *
     * @var Estate_Office_Agent_Repository
     */
    private Estate_Office_Agent_Repository $repository;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Agent_Repository|null $repository Repozytorium agentów.
     */
    public function __construct( ?Estate_Office_Agent_Repository $repository = null ) {
        $this->repository = $repository ?? new Estate_Office_Agent_Repository();
    }

    /**
     * Rejestruje hooki formularzy.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_agent', [ $this, 'handle_save_agent' ] );
    }

    /**
     * Renderuje stronę administratora.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_agents' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do zarządzania agentami.', 'estate-office' ) );
        }

        $action   = $this->get_current_action();
        $messages = $this->collect_messages();

        echo '<div class="wrap estate-office-agents">';
        echo '<h1>' . esc_html__( 'Agenci EstateOffice', 'estate-office' ) . '</h1>';

        foreach ( $messages as $message ) {
            printf(
                '<div class="notice %2$s"><p>%1$s</p></div>',
                esc_html( $message['text'] ),
                esc_attr( $message['type'] )
            );
        }

        if ( in_array( $action, [ 'add', 'edit' ], true ) ) {
            $agent = 'edit' === $action ? $this->get_current_agent() : null;

            if ( 'edit' === $action && null === $agent ) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__( 'Nie znaleziono wskazanego agenta.', 'estate-office' )
                );
                $this->render_actions_toolbar();
                $this->render_list( $this->repository->all() );
            } else {
                $this->render_actions_toolbar( true );
                $this->render_form( $agent );
            }
        } else {
            $this->render_actions_toolbar();
            $this->render_list( $this->repository->all() );
        }

        echo '</div>';
    }

    /**
     * Obsługuje zapis formularza.
     *
     * @return void
     */
    public function handle_save_agent() : void {
        if ( ! current_user_can( 'manage_estate_office_agents' ) ) {
            wp_die( esc_html__( 'Brak wymaganych uprawnień.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_agent' );

        $agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;

        $redirect_args = [
            'page' => self::PAGE_SLUG,
        ];

        if ( $agent_id > 0 ) {
            $result = $this->update_agent( $agent_id );
            $redirect_args['message'] = $result ? 'updated' : 'error';
        } else {
            $result = $this->create_agent();
            $redirect_args['message'] = $result ? 'created' : 'error';
        }

        $redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Tworzy nowego agenta.
     *
     * @return bool
     */
    private function create_agent() : bool {
        $user_login = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ), true );
        $user_email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
        $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );

        if ( empty( $user_login ) || empty( $user_email ) || empty( $first_name ) || empty( $last_name ) ) {
            return false;
        }

        if ( username_exists( $user_login ) || email_exists( $user_email ) ) {
            return false;
        }

        $generated_password = wp_generate_password( 16, true );

        $user_id = wp_insert_user(
            [
                'user_login' => $user_login,
                'user_pass'  => $generated_password,
                'user_email' => $user_email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ),
                'role'         => 'estate_office_agent',
            ]
        );

        if ( is_wp_error( $user_id ) ) {
            Estate_Office_Plugin::log_debug( 'Nie udało się utworzyć użytkownika agenta.', [ 'error' => $user_id->get_error_message() ] );
            return false;
        }

        wp_new_user_notification( $user_id, null, 'both' );

        $record_id = $this->repository->create( $this->collect_agent_payload( (int) $user_id, true ) );

        if ( $record_id <= 0 ) {
            Estate_Office_Plugin::log_debug( 'Nie udało się zapisać rekordu agenta.', [ 'user_id' => $user_id ] );
            return false;
        }

        Estate_Office_Plugin::log_debug( 'Utworzono nowego agenta.', [ 'agent_id' => $record_id, 'user_id' => $user_id ] );

        return true;
    }

    /**
     * Aktualizuje istniejącego agenta.
     *
     * @param int $agent_id ID agenta.
     *
     * @return bool
     */
    private function update_agent( int $agent_id ) : bool {
        $agent = $this->repository->find( $agent_id );

        if ( ! $agent ) {
            return false;
        }

        $user_id = (int) $agent->user_id;

        $user_data = [
            'ID'         => $user_id,
            'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
        ];

        $new_email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
        if ( ! empty( $new_email ) && filter_var( $new_email, FILTER_VALIDATE_EMAIL ) ) {
            $user_data['user_email'] = $new_email;
        }

        $result = wp_update_user( $user_data );

        if ( is_wp_error( $result ) ) {
            Estate_Office_Plugin::log_debug( 'Nie udało się zaktualizować danych użytkownika agenta.', [ 'error' => $result->get_error_message() ] );
            return false;
        }

        $updated = $this->repository->update( $agent_id, $this->collect_agent_payload( $user_id, false ) );

        if ( $updated ) {
            Estate_Office_Plugin::log_debug( 'Zaktualizowano dane agenta.', [ 'agent_id' => $agent_id ] );
        }

        return $updated;
    }

    /**
     * Buduje tablicę danych do zapisu w bazie.
     *
     * @param int  $user_id ID użytkownika.
     * @param bool $is_new  Czy tworzony jest nowy rekord.
     *
     * @return array<string,mixed>
     */
    private function collect_agent_payload( int $user_id, bool $is_new ) : array {
        $phone    = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $email    = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $title    = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $bio      = wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) );
        $meta     = [];
        $photo_id = $this->maybe_handle_photo_upload( $is_new ? 0 : absint( $_POST['current_photo_id'] ?? 0 ) );

        $user = get_userdata( $user_id );
        $default_email = $user instanceof WP_User ? $user->user_email : '';

        if ( ! empty( $_POST['phone_secondary'] ) ) {
            $meta['phone_secondary'] = sanitize_text_field( wp_unslash( $_POST['phone_secondary'] ) );
        }

        $meta_encoded = wp_json_encode( $meta );
        if ( false === $meta_encoded ) {
            $meta_encoded = '';
        }

        return [
            'user_id'  => $user_id,
            'phone'    => $phone,
            'email'    => $email ?: $default_email,
            'photo_id' => $photo_id,
            'title'    => $title,
            'bio'      => $bio,
            'meta'     => $meta_encoded,
        ];
    }

    /**
     * Obsługuje upload zdjęcia agenta.
     *
     * @param int $current_photo_id Aktualne ID zdjęcia.
     *
     * @return int
     */
    private function maybe_handle_photo_upload( int $current_photo_id ) : int {
        if ( ! empty( $_POST['remove_photo'] ) ) {
            return 0;
        }

        if ( empty( $_FILES['photo']['name'] ?? '' ) ) {
            return $current_photo_id;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'photo', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            Estate_Office_Plugin::log_debug( 'Błąd przesyłania zdjęcia agenta.', [ 'error' => $attachment_id->get_error_message() ] );
            return $current_photo_id;
        }

        return (int) $attachment_id;
    }

    /**
     * Pobiera agenta do edycji.
     *
     * @return object|null
     */
    private function get_current_agent() : ?object {
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

        if ( 'edit' !== $action ) {
            return null;
        }

        $agent_id = isset( $_GET['agent_id'] ) ? absint( $_GET['agent_id'] ) : 0;

        if ( $agent_id <= 0 ) {
            return null;
        }

        return $this->repository->find( $agent_id );
    }

    /**
     * Zwraca komunikaty do wyświetlenia.
     *
     * @return array<int,array{text:string,type:string}>
     */
    private function collect_messages() : array {
        $messages = [];
        $code     = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';

        if ( 'created' === $code ) {
            $messages[] = [
                'text' => __( 'Agent został dodany.', 'estate-office' ),
                'type' => 'notice-success',
            ];
        } elseif ( 'updated' === $code ) {
            $messages[] = [
                'text' => __( 'Dane agenta zostały zaktualizowane.', 'estate-office' ),
                'type' => 'notice-success',
            ];
        } elseif ( 'error' === $code ) {
            $messages[] = [
                'text' => __( 'Wystąpił błąd podczas zapisu agenta. Sprawdź poprawność danych.', 'estate-office' ),
                'type' => 'notice-error',
            ];
        }

        return $messages;
    }

    /**
     * Renderuje pasek akcji nad tabelą/formularzem.
     *
     * @param bool $show_back Czy wyświetlić przycisk powrotu.
     *
     * @return void
     */
    private function render_actions_toolbar( bool $show_back = false ) : void {
        $base_url = add_query_arg(
            [
                'page' => self::PAGE_SLUG,
            ],
            admin_url( 'admin.php' )
        );

        echo '<div class="estate-office-agents-actions">';

        if ( $show_back ) {
            echo '<a class="button" href="' . esc_url( $base_url ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        } else {
            $add_url = add_query_arg(
                [
                    'action' => 'add',
                ],
                $base_url
            );

            echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Dodaj agenta', 'estate-office' ) . '</a>';
        }

        echo '</div>';
    }

    /**
     * Renderuje formularz dodawania/edycji agenta.
     *
     * @param object|null $agent Bieżący agent.
     *
     * @return void
     */
    private function render_form( ?object $agent ) : void {
        $is_edit  = null !== $agent;
        $user_obj = $is_edit ? get_userdata( (int) $agent->user_id ) : null;
        $user     = $user_obj instanceof WP_User ? $user_obj : null;
        $photo_id = $is_edit ? (int) $agent->photo_id : 0;
        $photo_tag = $photo_id ? wp_get_attachment_image( $photo_id, 'thumbnail', false, [ 'style' => 'max-width:120px;height:auto;' ] ) : '';

        echo '<div class="estate-office-agent-form">';
        echo '<h2>' . esc_html( $is_edit ? __( 'Edytuj agenta', 'estate-office' ) : __( 'Dodaj nowego agenta', 'estate-office' ) ) . '</h2>';

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-agent-form">';
        wp_nonce_field( 'estate_office_save_agent' );
        echo '<input type="hidden" name="action" value="estate_office_save_agent" />';
        echo '<input type="hidden" name="agent_id" value="' . esc_attr( $is_edit ? (int) $agent->id : 0 ) . '" />';
        echo '<input type="hidden" name="current_photo_id" value="' . esc_attr( $photo_id ) . '" />';

        echo '<table class="form-table">';

        if ( $is_edit && $user ) {
            echo '<tr><th scope="row">' . esc_html__( 'Użytkownik WordPress', 'estate-office' ) . '</th><td>';
            echo '<p>' . esc_html__( 'Login:', 'estate-office' ) . ' <strong>' . esc_html( $user->user_login ) . '</strong></p>';
            echo '<label for="user_email">' . esc_html__( 'Adres e-mail użytkownika', 'estate-office' ) . '</label><br />';
            echo '<input name="user_email" id="user_email" type="email" class="regular-text" value="' . esc_attr( $user->user_email ) . '" required />';
            echo '</td></tr>';
        } else {
            echo '<tr><th scope="row"><label for="user_login">' . esc_html__( 'Nazwa użytkownika', 'estate-office' ) . '</label></th><td>';
            echo '<input name="user_login" id="user_login" type="text" class="regular-text" required />';
            echo '</td></tr>';

            echo '<tr><th scope="row"><label for="user_email">' . esc_html__( 'Adres e-mail użytkownika', 'estate-office' ) . '</label></th><td>';
            echo '<input name="user_email" id="user_email" type="email" class="regular-text" required />';
            echo '</td></tr>';
        }

        echo '<tr><th scope="row"><label for="first_name">' . esc_html__( 'Imię', 'estate-office' ) . '</label></th><td>';
        $first_name = $user instanceof WP_User ? $user->first_name : '';
        echo '<input name="first_name" id="first_name" type="text" class="regular-text" value="' . esc_attr( $is_edit ? $first_name : '' ) . '" required />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="last_name">' . esc_html__( 'Nazwisko', 'estate-office' ) . '</label></th><td>';
        $last_name = $user instanceof WP_User ? $user->last_name : '';
        echo '<input name="last_name" id="last_name" type="text" class="regular-text" value="' . esc_attr( $is_edit ? $last_name : '' ) . '" required />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="contact_email">' . esc_html__( 'E-mail kontaktowy', 'estate-office' ) . '</label></th><td>';
        $contact_email = $is_edit ? $agent->email : '';
        echo '<input name="contact_email" id="contact_email" type="email" class="regular-text" value="' . esc_attr( $contact_email ) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="phone">' . esc_html__( 'Telefon', 'estate-office' ) . '</label></th><td>';
        echo '<input name="phone" id="phone" type="text" class="regular-text" value="' . esc_attr( $is_edit ? $agent->phone : '' ) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="phone_secondary">' . esc_html__( 'Telefon dodatkowy', 'estate-office' ) . '</label></th><td>';
        $meta = $is_edit && ! empty( $agent->meta ) ? json_decode( (string) $agent->meta, true ) : [];
        $secondary_phone = is_array( $meta ) && isset( $meta['phone_secondary'] ) ? $meta['phone_secondary'] : '';
        echo '<input name="phone_secondary" id="phone_secondary" type="text" class="regular-text" value="' . esc_attr( $secondary_phone ) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="title">' . esc_html__( 'Tytuł/Stanowisko', 'estate-office' ) . '</label></th><td>';
        echo '<input name="title" id="title" type="text" class="regular-text" value="' . esc_attr( $is_edit ? $agent->title : '' ) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Zdjęcie profilowe', 'estate-office' ) . '</th><td>';
        if ( $photo_tag ) {
            echo '<div class="agent-photo-preview">' . $photo_tag . '</div>';
        }
        echo '<input type="file" name="photo" accept="image/*" />';
        if ( $photo_id ) {
            echo '<p><label><input type="checkbox" name="remove_photo" value="1" /> ' . esc_html__( 'Usuń bieżące zdjęcie', 'estate-office' ) . '</label></p>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bio">' . esc_html__( 'Opis / Biografia', 'estate-office' ) . '</label></th><td>';
        wp_editor(
            $is_edit ? $agent->bio : '',
            'bio',
            [
                'textarea_name' => 'bio',
                'textarea_rows' => 8,
                'media_buttons' => false,
            ]
        );
        echo '</td></tr>';

        echo '</table>';

        submit_button( $is_edit ? __( 'Zapisz agenta', 'estate-office' ) : __( 'Dodaj agenta', 'estate-office' ) );

        echo '</form>';
        echo '</div>';
    }

    /**
     * Renderuje tabelę agentów.
     *
     * @param array<int,object> $agents Lista agentów.
     *
     * @return void
     */
    private function render_list( array $agents ) : void {
        echo '<hr />';
        echo '<h2>' . esc_html__( 'Lista agentów', 'estate-office' ) . '</h2>';

        if ( empty( $agents ) ) {
            echo '<p>' . esc_html__( 'Brak zarejestrowanych agentów.', 'estate-office' ) . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Agent', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Telefon', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'E-mail', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Tytuł', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Ostatnia aktualizacja', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th>';
        echo '</tr></thead>';

        echo '<tbody>';
        foreach ( $agents as $agent ) {
            $user = get_userdata( (int) $agent->user_id );
            if ( ! $user ) {
                continue;
            }

            $edit_url = add_query_arg(
                [
                    'page'     => self::PAGE_SLUG,
                    'action'   => 'edit',
                    'agent_id' => (int) $agent->id,
                ],
                admin_url( 'admin.php' )
            );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $user->display_name ) . '</strong></td>';
            echo '<td>' . esc_html( $agent->phone ) . '</td>';
            echo '<td><a href="mailto:' . esc_attr( $agent->email ) . '">' . esc_html( $agent->email ) . '</a></td>';
            echo '<td>' . esc_html( $agent->title ) . '</td>';
            echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $agent->updated_at ?: $agent->created_at ) ) . '</td>';
            echo '<td><a class="button button-secondary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Zwraca aktualną akcję widoku.
     *
     * @return string
     */
    private function get_current_action() : string {
        return isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    }
}
