<?php
namespace EstateOffice\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the custom agents admin page.
 */
class Agents_Page {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_post_estate_office_add_agent', [ $this, 'handle_add_agent' ] );
        add_action( 'admin_post_estate_office_update_agent', [ $this, 'handle_update_agent' ] );
    }

    /**
     * Handles creating a new agent user.
     */
    public function handle_add_agent(): void {
        $this->verify_request();

        $email = isset( $_POST['agent_email'] ) ? sanitize_email( wp_unslash( $_POST['agent_email'] ) ) : '';
        $username = isset( $_POST['agent_username'] ) ? sanitize_user( wp_unslash( $_POST['agent_username'] ), true ) : '';
        $first_name = isset( $_POST['agent_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_first_name'] ) ) : '';
        $last_name  = isset( $_POST['agent_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_last_name'] ) ) : '';
        $phone      = isset( $_POST['agent_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_phone'] ) ) : '';
        $bio        = isset( $_POST['agent_bio'] ) ? wp_kses_post( wp_unslash( $_POST['agent_bio'] ) ) : '';

        if ( empty( $email ) || empty( $username ) ) {
            $this->redirect_with_message( 'estate-office-agents', 'error', __( 'Nazwa użytkownika i email są wymagane.', 'estate-office' ) );
        }

        $user_id = wp_create_user( $username, wp_generate_password(), $email );

        if ( is_wp_error( $user_id ) ) {
            $this->redirect_with_message( 'estate-office-agents', 'error', $user_id->get_error_message() );
        }

        wp_update_user(
            [
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => 'estate_agent',
            ]
        );

        update_user_meta( $user_id, '_estate_office_phone', $phone );
        update_user_meta( $user_id, '_estate_office_bio', $bio );

        $this->redirect_with_message( 'estate-office-agents', 'success', __( 'Agent został dodany.', 'estate-office' ) );
    }

    /**
     * Handles updating an existing agent.
     */
    public function handle_update_agent(): void {
        $this->verify_request();

        $user_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
            $this->redirect_with_message( 'estate-office-agents', 'error', __( 'Brak uprawnień do edycji agenta.', 'estate-office' ) );
        }

        $first_name = isset( $_POST['agent_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_first_name'] ) ) : '';
        $last_name  = isset( $_POST['agent_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_last_name'] ) ) : '';
        $email      = isset( $_POST['agent_email'] ) ? sanitize_email( wp_unslash( $_POST['agent_email'] ) ) : '';
        $phone      = isset( $_POST['agent_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_phone'] ) ) : '';
        $bio        = isset( $_POST['agent_bio'] ) ? wp_kses_post( wp_unslash( $_POST['agent_bio'] ) ) : '';

        $userdata = [
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'user_email' => $email,
        ];

        $result = wp_update_user( $userdata );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_message( 'estate-office-agents', 'error', $result->get_error_message() );
        }

        $user = get_userdata( $user_id );
        if ( $user && ! in_array( 'estate_agent', (array) $user->roles, true ) ) {
            $user->add_role( 'estate_agent' );
        }

        update_user_meta( $user_id, '_estate_office_phone', $phone );
        update_user_meta( $user_id, '_estate_office_bio', $bio );

        $this->redirect_with_message( 'estate-office-agents', 'success', __( 'Dane agenta zostały zaktualizowane.', 'estate-office' ) );
    }

    /**
     * Verify request integrity.
     */
    private function verify_request(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_agents_action', 'estate_office_nonce' );
    }

    /**
     * Redirect helper.
     */
    private function redirect_with_message( string $page, string $type, string $message ): void {
        $url = add_query_arg(
            [
                'page'    => $page,
                'notice'  => $type,
                'message' => rawurlencode( $message ),
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Returns agent users.
     *
     * @return array
     */
    public function get_agents(): array {
        return get_users(
            [
                'role'    => 'estate_agent',
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ]
        );
    }

    /**
     * Renders the agents page.
     */
    public function render_page(): void {
        $agents = $this->get_agents();
        include ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/views/agents.php';
    }
}
