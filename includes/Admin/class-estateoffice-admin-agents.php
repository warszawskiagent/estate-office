<?php
/**
 * Agents management page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Agents extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-agents';

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Agenci', 'estate-office' );
        $this->page_title = __( 'Agenci', 'estate-office' );
        $this->capability = 'eo_manage_agents';
    }

    public function render(): void {
        $agents    = self::get_agents();
        $edit_id   = isset( $_GET['agent'] ) ? absint( $_GET['agent'] ) : 0;
        $edit_data = $edit_id ? self::get_agent( $edit_id ) : null;
        ?>
        <div class="wrap estate-office-wrap estate-office-agents">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <?php $this->render_notice(); ?>
            <?php $this->render_global_action(); ?>
            <h2 class="title"><?php esc_html_e( 'Lista agentów', 'estate-office' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Imię i nazwisko', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $agents ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'Brak agentów.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $agents as $agent ) : ?>
                            <tr>
                                <td><?php echo esc_html( self::format_agent_name( $agent ) ); ?></td>
                                <td><?php echo esc_html( $agent->phone ); ?></td>
                                <td><?php echo esc_html( $agent->email ); ?></td>
                                <td>
                                    <?php
                                    $public_url = ! empty( $agent->slug ) ? estate_office_get_agent_url( $agent->slug ) : '';
                                    if ( $public_url ) :
                                        ?>
                                        <a class="button button-small" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener">
                                            <?php esc_html_e( 'Podgląd strony', 'estate-office' ); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&agent=' . absint( $agent->id ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office' ); ?></a>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć tego agenta?', 'estate-office' ) ); ?>');">
                                            <?php wp_nonce_field( 'estate_office_delete_agent' ); ?>
                                            <input type="hidden" name="action" value="estate_office_delete_agent" />
                                            <input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->id ); ?>" />
                                            <button type="submit" class="button-link delete-link"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 class="title"><?php echo $edit_data ? esc_html__( 'Edytuj agenta', 'estate-office' ) : esc_html__( 'Dodaj nowego agenta', 'estate-office' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-agent-form">
                <?php wp_nonce_field( 'estate_office_save_agent' ); ?>
                <input type="hidden" name="action" value="estate_office_save_agent" />
                <?php if ( $edit_data ) : ?>
                    <input type="hidden" name="agent_id" value="<?php echo esc_attr( $edit_data->id ); ?>" />
                <?php endif; ?>
                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="agent_first_name" class="required"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                        <input type="text" id="agent_first_name" name="first_name" value="<?php echo esc_attr( $edit_data->first_name ?? '' ); ?>" required />
                    </p>
                    <p>
                        <label for="agent_last_name" class="required"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                        <input type="text" id="agent_last_name" name="last_name" value="<?php echo esc_attr( $edit_data->last_name ?? '' ); ?>" required />
                    </p>
                    <p>
                        <label for="agent_slug"><?php esc_html_e( 'Adres publiczny', 'estate-office' ); ?></label>
                        <input type="text" id="agent_slug" name="slug" value="<?php echo esc_attr( $edit_data->slug ?? '' ); ?>" pattern="[a-z0-9\-]+" placeholder="<?php esc_attr_e( 'np. jan-kowalski', 'estate-office' ); ?>" />
                        <span class="description"><?php esc_html_e( 'Pozostaw puste, aby wygenerować adres automatycznie na podstawie imienia i nazwiska.', 'estate-office' ); ?></span>
                    </p>
                    <p>
                        <label for="agent_phone"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label>
                        <input type="text" id="agent_phone" name="phone" value="<?php echo esc_attr( $edit_data->phone ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="agent_email"><?php esc_html_e( 'E-mail', 'estate-office' ); ?></label>
                        <input type="email" id="agent_email" name="email" value="<?php echo esc_attr( $edit_data->email ?? '' ); ?>" />
                    </p>
                    <p class="full">
                        <label for="agent_description"><?php esc_html_e( 'Opis/Biografia', 'estate-office' ); ?></label>
                        <?php
                        wp_editor(
                            wp_kses_post( $edit_data->description ?? '' ),
                            'agent_description',
                            [
                                'textarea_name' => 'description',
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                            ]
                        );
                        ?>
                    </p>
                    <p>
                        <label class="required"><?php esc_html_e( 'Zdjęcie profilowe', 'estate-office' ); ?></label>
                        <?php $this->render_media_field( 'photo_id', (int) ( $edit_data->photo_id ?? 0 ) ); ?>
                    </p>
                    <?php
                    $contact = [];
                    if ( ! empty( $edit_data->contact_data ) ) {
                        $contact = json_decode( $edit_data->contact_data, true );
                        if ( ! is_array( $contact ) ) {
                            $contact = [];
                        }
                    }
                    ?>
                    <p>
                        <label for="agent_phone_alt"><?php esc_html_e( 'Telefon dodatkowy', 'estate-office' ); ?></label>
                        <input type="text" id="agent_phone_alt" name="contact_data[phone_alt]" value="<?php echo esc_attr( $contact['phone_alt'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="agent_email_alt"><?php esc_html_e( 'E-mail dodatkowy', 'estate-office' ); ?></label>
                        <input type="email" id="agent_email_alt" name="contact_data[email_alt]" value="<?php echo esc_attr( $contact['email_alt'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="agent_linkedin"><?php esc_html_e( 'LinkedIn', 'estate-office' ); ?></label>
                        <input type="url" id="agent_linkedin" name="contact_data[linkedin]" value="<?php echo esc_attr( $contact['linkedin'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="agent_facebook"><?php esc_html_e( 'Facebook', 'estate-office' ); ?></label>
                        <input type="url" id="agent_facebook" name="contact_data[facebook]" value="<?php echo esc_attr( $contact['facebook'] ?? '' ); ?>" />
                    </p>
                </div>
                <?php submit_button( $edit_data ? __( 'Zapisz zmiany', 'estate-office' ) : __( 'Dodaj agenta', 'estate-office' ) ); ?>
            </form>
        </div>
        <?php
    }

    protected function render_notice(): void {
        if ( isset( $_GET['status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['status'] ) );
            if ( 'saved' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Agent zapisany.', 'estate-office' ) . '</p></div>';
            } elseif ( 'deleted' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Agent usunięty.', 'estate-office' ) . '</p></div>';
            } elseif ( 'error' === $status ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Wystąpił błąd podczas zapisu.', 'estate-office' ) . '</p></div>';
            }
        }
    }

    protected function render_global_action(): void {
        $url = admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=new' );
        printf( '<a href="%1$s" class="page-title-action">%2$s</a>', esc_url( $url ), esc_html__( 'Dodaj nową umowę', 'estate-office' ) );
    }

    public static function get_agents(): array {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'eo_agents ORDER BY last_name ASC, first_name ASC' );
    }

    public static function get_agent( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_agents WHERE id = %d', $id ) );
    }

    public static function get_agent_by_slug( string $slug ) {
        global $wpdb;
        $slug = sanitize_title( $slug );
        if ( '' === $slug ) {
            return null;
        }

        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_agents WHERE slug = %s', $slug ) );
    }

    public static function format_agent_name( $agent ): string {
        if ( ! $agent ) {
            return '';
        }

        return self::compose_agent_label(
            $agent->first_name ?? '',
            $agent->last_name ?? '',
            $agent->email ?? '',
            $agent->phone ?? '',
            $agent->id ?? 0
        );
    }

    public static function format_agent_from_row( $row ): string {
        if ( ! $row ) {
            return '';
        }

        return self::compose_agent_label(
            $row->agent_first_name ?? '',
            $row->agent_last_name ?? '',
            $row->agent_email ?? '',
            $row->agent_phone ?? '',
            $row->agent_id ?? 0
        );
    }

    /**
     * Gather CRM relations for an agent.
     */
    public static function get_agent_relations( int $agent_id ): array {
        global $wpdb;

        $properties_table = $wpdb->prefix . 'eo_properties';
        $contracts_table  = $wpdb->prefix . 'eo_contracts';
        $clients_table    = $wpdb->prefix . 'eo_clients';
        $searches_table   = $wpdb->prefix . 'eo_searches';

        $properties = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.transaction_type, p.property_type, p.address, p.details, p.tags, p.export_www, p.contract_id, p.updated_at, c.contract_number
                 FROM {$properties_table} p
                 LEFT JOIN {$contracts_table} c ON c.id = p.contract_id
                 WHERE p.agent_id = %d
                 ORDER BY p.updated_at DESC",
                $agent_id
            )
        );

        $searches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, transaction_type, criteria, contract_id, updated_at
                 FROM {$searches_table}
                 WHERE agent_id = %d
                 ORDER BY updated_at DESC",
                $agent_id
            )
        );

        $contracts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, contract_number, transaction_type, start_date, end_date, indefinite, stage, updated_at
                 FROM {$contracts_table}
                 WHERE agent_id = %d
                 ORDER BY updated_at DESC",
                $agent_id
            )
        );

        $clients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, client_type, first_name, last_name, company_name, phone, email
                 FROM {$clients_table}
                 WHERE agent_id = %d
                 ORDER BY updated_at DESC",
                $agent_id
            )
        );

        return [
            'properties' => $properties,
            'searches'   => $searches,
            'contracts'  => $contracts,
            'clients'    => $clients,
        ];
    }

    protected static function compose_agent_label( string $first, string $last, string $email, string $phone, int $id ): string {
        $name = trim( $first . ' ' . $last );
        if ( '' !== $name ) {
            return $name;
        }

        if ( '' !== $email ) {
            return $email;
        }

        if ( '' !== $phone ) {
            return $phone;
        }

        return $id > 0 ? sprintf( __( 'Agent #%d', 'estate-office' ), $id ) : '';
    }

    protected function render_media_field( string $name, int $attachment_id ): void {
        $image = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, [ 'class' => 'estate-office-media-preview' ] ) : '';
        $button_label = $attachment_id ? __( 'Zmień zdjęcie', 'estate-office' ) : __( 'Wybierz zdjęcie', 'estate-office' );
        ?>
        <div class="estate-office-media-field" data-target="<?php echo esc_attr( $name ); ?>">
            <div class="estate-office-media-preview-wrap"><?php echo $image ? wp_kses_post( $image ) : '<span class="placeholder">' . esc_html__( 'Brak zdjęcia', 'estate-office' ) . '</span>'; ?></div>
            <input type="hidden" name="photo_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
            <button type="button" class="button estate-office-media-select"><?php echo esc_html( $button_label ); ?></button>
            <button type="button" class="button-link estate-office-media-remove" data-placeholder="<?php esc_attr_e( 'Brak zdjęcia', 'estate-office' ); ?>" <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
        </div>
        <?php
    }
}
