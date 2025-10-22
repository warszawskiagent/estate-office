<?php
/**
 * Clients management page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Clients extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-clients';

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Klienci', 'estate-office' );
        $this->page_title = __( 'Klienci', 'estate-office' );
        $this->capability = 'eo_manage_clients';
    }

    public function render(): void {
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $clients  = self::get_clients( $search );
        $agents   = EstateOffice_Admin_Agents::get_agents();
        $edit_id  = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;
        $client   = $edit_id ? self::get_client( $edit_id ) : null;
        ?>
        <div class="wrap estate-office-wrap estate-office-clients">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <?php $this->render_notice(); ?>
            <?php $this->render_global_action(); ?>

            <form method="get" class="estate-office-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <label for="estate-office-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj klientów', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Szukaj po dowolnej kolumnie', 'estate-office' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nazwa', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $clients ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'Brak klientów.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $clients as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( self::format_client_name( $row ) ); ?></td>
                                <td><?php echo esc_html( self::format_address_column( $row->address ) ); ?></td>
                                <td><?php echo esc_html( $row->phone ); ?></td>
                                <td><?php echo esc_html( $row->email ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $row ) ?: '—' ); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&client=' . absint( $row->id ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office' ); ?></a>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć tego klienta?', 'estate-office' ) ); ?>');">
                                            <?php wp_nonce_field( 'estate_office_delete_client' ); ?>
                                            <input type="hidden" name="action" value="estate_office_delete_client" />
                                            <input type="hidden" name="client_id" value="<?php echo esc_attr( $row->id ); ?>" />
                                            <button type="submit" class="button-link delete-link"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 class="title"><?php echo $client ? esc_html__( 'Edytuj klienta', 'estate-office' ) : esc_html__( 'Dodaj klienta', 'estate-office' ); ?></h2>
            <?php $this->render_form( $client, $agents ); ?>
        </div>
        <?php
    }

    protected function render_notice(): void {
        if ( isset( $_GET['status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['status'] ) );
            if ( 'saved' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Klient zapisany.', 'estate-office' ) . '</p></div>';
            } elseif ( 'deleted' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Klient usunięty.', 'estate-office' ) . '</p></div>';
            } elseif ( 'error' === $status ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Wystąpił błąd podczas zapisu klienta.', 'estate-office' ) . '</p></div>';
            }
        }
    }

    protected function render_global_action(): void {
        $url = admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=new' );
        printf( '<a href="%1$s" class="page-title-action">%2$s</a>', esc_url( $url ), esc_html__( 'Dodaj nową umowę', 'estate-office' ) );
    }

    protected function render_form( $client, array $agents ): void {
        $address        = $client && $client->address ? json_decode( $client->address, true ) : [];
        $correspondence = $client && $client->correspondence_address ? json_decode( $client->correspondence_address, true ) : [];
        $identification = $client && $client->identification ? json_decode( $client->identification, true ) : [];
        $custom_data    = $client && $client->custom_data ? json_decode( $client->custom_data, true ) : [];

        if ( ! is_array( $address ) ) {
            $address = [];
        }
        if ( ! is_array( $correspondence ) ) {
            $correspondence = [];
        }
        if ( ! is_array( $identification ) ) {
            $identification = [];
        }
        if ( ! is_array( $custom_data ) ) {
            $custom_data = [];
        }

        $dynamic_fields = EstateOffice_Admin_Settings::get_dynamic_fields( 'client' );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-client-form" data-client-type="<?php echo esc_attr( $client->client_type ?? 'individual' ); ?>">
            <?php wp_nonce_field( 'estate_office_save_client' ); ?>
            <input type="hidden" name="action" value="estate_office_save_client" />
            <?php if ( $client ) : ?>
                <input type="hidden" name="client_id" value="<?php echo esc_attr( $client->id ); ?>" />
            <?php endif; ?>

            <fieldset class="estate-office-fieldset">
                <legend><?php esc_html_e( 'Typ klienta', 'estate-office' ); ?></legend>
                <label><input type="radio" name="client_type" value="individual" <?php checked( empty( $client ) || 'individual' === $client->client_type ); ?> /> <?php esc_html_e( 'Osoba fizyczna', 'estate-office' ); ?></label>
                <label><input type="radio" name="client_type" value="company" <?php checked( $client && 'company' === $client->client_type ); ?> /> <?php esc_html_e( 'Firma', 'estate-office' ); ?></label>
            </fieldset>

            <div class="estate-office-grid two-cols" data-section="individual">
                <p>
                    <label for="client_first_name" class="required"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                    <input type="text" id="client_first_name" name="first_name" value="<?php echo esc_attr( $client->first_name ?? '' ); ?>" />
                </p>
                <p>
                    <label for="client_last_name" class="required"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                    <input type="text" id="client_last_name" name="last_name" value="<?php echo esc_attr( $client->last_name ?? '' ); ?>" />
                </p>
            </div>

            <div class="estate-office-grid two-cols" data-section="company">
                <p>
                    <label for="client_company_name" class="required"><?php esc_html_e( 'Nazwa firmy', 'estate-office' ); ?></label>
                    <input type="text" id="client_company_name" name="company_name" value="<?php echo esc_attr( $client->company_name ?? '' ); ?>" />
                </p>
                <p>
                    <label for="client_representative"><?php esc_html_e( 'Imię i nazwisko reprezentanta', 'estate-office' ); ?></label>
                    <input type="text" id="client_representative" name="representative_name" value="<?php echo esc_attr( $client->representative_name ?? '' ); ?>" />
                </p>
            </div>

            <div class="estate-office-grid two-cols">
                <p>
                    <label for="client_phone" class="required"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label>
                    <input type="text" id="client_phone" name="phone" value="<?php echo esc_attr( $client->phone ?? '' ); ?>" required />
                </p>
                <p>
                    <label for="client_email"><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label>
                    <input type="email" id="client_email" name="email" value="<?php echo esc_attr( $client->email ?? '' ); ?>" />
                </p>
                <p data-section="company">
                    <label for="client_website"><?php esc_html_e( 'Strona WWW', 'estate-office' ); ?></label>
                    <input type="url" id="client_website" name="website" value="<?php echo esc_attr( $client->website ?? '' ); ?>" />
                </p>
            </div>

            <p>
                <label for="client_agent"><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></label>
                <select id="client_agent" name="agent_id">
                    <option value=""><?php esc_html_e( 'Wybierz opiekuna', 'estate-office' ); ?></option>
                    <?php foreach ( $agents as $agent_row ) :
                        $label = EstateOffice_Admin_Agents::format_agent_name( $agent_row );
                        if ( '' === $label ) {
                            $label = sprintf( __( 'Agent #%d', 'estate-office' ), (int) $agent_row->id );
                        }
                        ?>
                        <option value="<?php echo esc_attr( $agent_row->id ); ?>" <?php selected( (int) ( $client->agent_id ?? 0 ), (int) $agent_row->id ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <fieldset class="estate-office-fieldset" data-section="individual">
                <legend><?php esc_html_e( 'Dane identyfikacyjne (osoba fizyczna)', 'estate-office' ); ?></legend>
                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="client_pesel"><?php esc_html_e( 'PESEL', 'estate-office' ); ?></label>
                        <input type="text" id="client_pesel" name="pesel" value="<?php echo esc_attr( $identification['pesel'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="client_document_type"><?php esc_html_e( 'Rodzaj dokumentu', 'estate-office' ); ?></label>
                        <select id="client_document_type" name="document_type">
                            <?php
                            $options = [
                                ''              => __( 'Wybierz', 'estate-office' ),
                                'dowod'         => __( 'Dowód osobisty', 'estate-office' ),
                                'paszport'      => __( 'Paszport', 'estate-office' ),
                                'karta_pobytu'  => __( 'Karta pobytu', 'estate-office' ),
                            ];
                            foreach ( $options as $value => $label ) {
                                printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $value ), esc_html( $label ), selected( $identification['document_type'] ?? '', $value, false ) );
                            }
                            ?>
                        </select>
                    </p>
                    <p>
                        <label for="client_document_number"><?php esc_html_e( 'Numer dokumentu', 'estate-office' ); ?></label>
                        <input type="text" id="client_document_number" name="document_number" value="<?php echo esc_attr( $identification['document_no'] ?? '' ); ?>" />
                    </p>
                </div>
            </fieldset>

            <fieldset class="estate-office-fieldset" data-section="company">
                <legend><?php esc_html_e( 'Dane identyfikacyjne firmy', 'estate-office' ); ?></legend>
                <div class="estate-office-grid three-cols">
                    <p>
                        <label for="client_nip"><?php esc_html_e( 'NIP', 'estate-office' ); ?></label>
                        <input type="text" id="client_nip" name="nip" value="<?php echo esc_attr( $identification['nip'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="client_krs"><?php esc_html_e( 'KRS', 'estate-office' ); ?></label>
                        <input type="text" id="client_krs" name="krs" value="<?php echo esc_attr( $identification['krs'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="client_regon"><?php esc_html_e( 'REGON', 'estate-office' ); ?></label>
                        <input type="text" id="client_regon" name="regon" value="<?php echo esc_attr( $identification['regon'] ?? '' ); ?>" />
                    </p>
                </div>
            </fieldset>

            <?php $this->render_address_fields( 'address', __( 'Adres zamieszkania/rejestrowy', 'estate-office' ), $address ); ?>

            <?php $this->render_address_fields( 'correspondence_address', __( 'Adres korespondencyjny', 'estate-office' ), $correspondence, true ); ?>

            <?php if ( ! empty( $dynamic_fields ) ) : ?>
                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Dodatkowe pola', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <?php foreach ( $dynamic_fields as $field ) : ?>
                            <p>
                                <label for="client_custom_<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? '<span class="required">*</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <?php echo self::render_dynamic_input( $field, $custom_data[ $field['key'] ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <?php submit_button( $client ? __( 'Zapisz klienta', 'estate-office' ) : __( 'Dodaj klienta', 'estate-office' ) ); ?>
        </form>
        <?php
    }

    protected function render_address_fields( string $name, string $title, array $values, bool $with_toggle = false ): void {
        $toggle_checked = $with_toggle && ( ! empty( $values['same'] ) || empty( $values ) );
        ?>
        <fieldset class="estate-office-fieldset estate-office-address" data-address="<?php echo esc_attr( $name ); ?>">
            <legend><?php echo esc_html( $title ); ?></legend>
            <?php if ( $with_toggle ) : ?>
                <label class="estate-office-address-toggle"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[same]" value="1" <?php checked( $toggle_checked ); ?> /> <?php esc_html_e( 'Adres korespondencyjny taki sam', 'estate-office' ); ?></label>
            <?php endif; ?>
            <div class="estate-office-grid three-cols">
                <p>
                    <label for="<?php echo esc_attr( $name ); ?>_street"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $name ); ?>_street" name="<?php echo esc_attr( $name ); ?>[street]" value="<?php echo esc_attr( $values['street'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="<?php echo esc_attr( $name ); ?>_number"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $name ); ?>_number" name="<?php echo esc_attr( $name ); ?>[number]" value="<?php echo esc_attr( $values['number'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="<?php echo esc_attr( $name ); ?>_unit"><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $name ); ?>_unit" name="<?php echo esc_attr( $name ); ?>[unit]" value="<?php echo esc_attr( $values['unit'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="<?php echo esc_attr( $name ); ?>_postal"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $name ); ?>_postal" name="<?php echo esc_attr( $name ); ?>[postal_code]" value="<?php echo esc_attr( $values['postal_code'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="<?php echo esc_attr( $name ); ?>_city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $name ); ?>_city" name="<?php echo esc_attr( $name ); ?>[city]" value="<?php echo esc_attr( $values['city'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="<?php echo esc_attr( $name ); ?>_country"><?php esc_html_e( 'Kraj', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $name ); ?>_country" name="<?php echo esc_attr( $name ); ?>[country]" value="<?php echo esc_attr( $values['country'] ?? '' ); ?>" />
                </p>
            </div>
        </fieldset>
        <?php
    }

    public static function get_clients( string $search = '' ): array {
        global $wpdb;
        $table        = $wpdb->prefix . 'eo_clients';
        $agents_table = $wpdb->prefix . 'eo_agents';
        $select       = "SELECT c.*, a.first_name AS agent_first_name, a.last_name AS agent_last_name, a.email AS agent_email, a.phone AS agent_phone FROM {$table} c LEFT JOIN {$agents_table} a ON a.id = c.agent_id";

        if ( empty( $search ) ) {
            return $wpdb->get_results( $select . ' ORDER BY c.created_at DESC' );
        }

        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql  = $wpdb->prepare(
            $select . ' WHERE c.first_name LIKE %1$s OR c.last_name LIKE %1$s OR c.company_name LIKE %1$s OR c.phone LIKE %1$s OR c.email LIKE %1$s ORDER BY c.created_at DESC',
            $like
        );
        return $wpdb->get_results( $sql );
    }

    public static function get_client( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_clients WHERE id = %d', $id ) );
    }

    protected static function format_client_name( $row ): string {
        if ( 'company' === $row->client_type ) {
            return $row->company_name ?: __( 'Firma', 'estate-office' );
        }
        return trim( $row->first_name . ' ' . $row->last_name );
    }

    protected static function format_address_column( $json ): string {
        if ( ! $json ) {
            return '';
        }
        $address = json_decode( $json, true );
        if ( empty( $address['city'] ) ) {
            return '';
        }
        $parts = [];
        if ( ! empty( $address['street'] ) ) {
            $street_line = $address['street'];
            if ( ! empty( $address['number'] ) ) {
                $street_line .= ' ' . $address['number'];
            }
            if ( ! empty( $address['unit'] ) ) {
                $street_line .= '/' . $address['unit'];
            }
            $parts[] = $street_line;
        }
        if ( ! empty( $address['postal_code'] ) ) {
            $parts[] = $address['postal_code'];
        }
        $parts[] = $address['city'];
        return implode( ', ', array_filter( $parts ) );
    }

    public static function render_dynamic_input( array $field, $value ): string {
        $name = 'custom_fields[' . sanitize_key( $field['key'] ) . ']';
        $id   = 'client_custom_' . sanitize_key( $field['key'] );
        $required = ! empty( $field['required'] ) ? 'required' : '';
        switch ( $field['type'] ) {
            case 'textarea':
                return sprintf( '<textarea id="%1$s" name="%2$s" rows="3" %4$s>%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ), $required );
            case 'number':
                return sprintf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), $required );
            case 'select':
                $options = array_map( 'trim', explode( ',', $field['options'] ?? '' ) );
                $html    = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" ' . $required . '>';
                $html   .= '<option value="">' . esc_html__( 'Wybierz', 'estate-office' ) . '</option>';
                foreach ( $options as $option ) {
                    if ( '' === $option ) {
                        continue;
                    }
                    $html .= sprintf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option ), esc_html( $option ), selected( $value, $option, false ) );
                }
                $html .= '</select>';
                return $html;
            case 'checkbox':
                return sprintf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %4$s %5$s /> %3$s</label>', esc_attr( $id ), esc_attr( $name ), esc_html__( 'Tak', 'estate-office' ), ! empty( $value ) ? 'checked' : '', $required );
            case 'date':
                return sprintf( '<input type="date" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), $required );
            default:
                return sprintf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), $required );
        }
    }
}
