<?php
/**
 * Ekran administracyjny klientów EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/admin/class-estate-office-admin-agent-assignment.php';

/**
 * Class Estate_Office_Admin_Clients_Page
 */
class Estate_Office_Admin_Clients_Page {

    use Estate_Office_Admin_Agent_Assignment;

    private const PAGE_SLUG = 'estate-office-clients';

    /**
     * Repozytorium klientów.
     *
     * @var Estate_Office_Client_Repository
     */
    private Estate_Office_Client_Repository $repository;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Client_Repository|null $repository        Repozytorium.
     * @param Estate_Office_Agent_Repository|null  $agent_repository Repozytorium agentów.
     */
    public function __construct( ?Estate_Office_Client_Repository $repository = null, ?Estate_Office_Agent_Repository $agent_repository = null ) {
        $this->repository = $repository ?? new Estate_Office_Client_Repository();
        $this->init_agent_repository( $agent_repository );
    }

    /**
     * Rejestracja hooków.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_client', [ $this, 'handle_save_client' ] );
        add_action( 'admin_post_estate_office_delete_client', [ $this, 'handle_delete_client' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Dołącza zasoby JS.
     *
     * @param string $hook Hook strony.
     *
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        if ( 'estate-office-crm_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        $handle = 'estate-office-admin-clients';
        wp_register_script( $handle, false, [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $handle );
        wp_add_inline_script(
            $handle,
            <<<'JS'
jQuery(function($){
    const typeField = $('select[name="client_type"]');
    function toggleClientType(){
        const type = typeField.val();
        $('.estate-office-client-type').hide();
        $('.estate-office-client-type[data-type="'+type+'"]').show();
    }
    typeField.on('change', toggleClientType);
    toggleClientType();

    const correspondence = $('#correspondence_same');
    function toggleCorrespondence(){
        const isSame = correspondence.is(':checked');
        $('.estate-office-correspondence-fields').toggle(!isSame);
    }
    correspondence.on('change', toggleCorrespondence);
    toggleCorrespondence();
});
JS
        );
    }

    /**
     * Renderuje ekran.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_clients' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do przeglądania klientów.', 'estate-office' ) );
        }

        $action    = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;

        if ( 'new' === $action ) {
            $this->render_form();

            return;
        }

        if ( 'edit' === $action && $client_id ) {
            $client = $this->repository->find( $client_id );
            if ( null === $client ) {
                $this->render_list( __( 'Nie znaleziono wskazanego klienta.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_form( $client );

            return;
        }

        if ( 'view' === $action && $client_id ) {
            $client = $this->repository->find( $client_id );
            if ( null === $client ) {
                $this->render_list( __( 'Nie znaleziono wskazanego klienta.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_profile( $client );

            return;
        }

        $this->render_list();
    }

    /**
     * Lista klientów.
     *
     * @param string $notice    Komunikat.
     * @param string $notice_id Typ komunikatu.
     *
     * @return void
     */
    private function render_list( string $notice = '', string $notice_id = 'updated' ) : void {
        $search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $client_type = isset( $_GET['client_type'] ) ? sanitize_text_field( wp_unslash( $_GET['client_type'] ) ) : '';
        $paged       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $query = $this->repository->paginate(
            [
                'paged'       => $paged,
                'search'      => $search,
                'client_type' => $client_type,
            ]
        );

        $agent_ids = [];
        if ( isset( $query['items'] ) && is_array( $query['items'] ) ) {
            $agent_ids = array_map( 'intval', wp_list_pluck( $query['items'], 'agent_id' ) );
        }
        $this->prime_agent_labels( $agent_ids );

        $message       = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : $notice;
        $message_class = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : $notice_id;

        echo '<div class="wrap estate-office-clients">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Klienci', 'estate-office' ) . '</h1>';
        echo ' <a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '" class="page-title-action">' . esc_html__( 'Dodaj klienta', 'estate-office' ) . '</a>';

        if ( ! empty( $message ) ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $message_class ), esc_html( $message ) );
        }

        echo '<form method="get" class="estate-office-clients-filter">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="estate-office-clients-search">' . esc_html__( 'Szukaj klientów', 'estate-office' ) . '</label>';
        echo '<input type="search" id="estate-office-clients-search" name="s" value="' . esc_attr( $search ) . '" />';
        echo '<select name="client_type">';
        echo '<option value="">' . esc_html__( 'Wszyscy klienci', 'estate-office' ) . '</option>';
        echo '<option value="person"' . selected( $client_type, 'person', false ) . '>' . esc_html__( 'Osoby fizyczne', 'estate-office' ) . '</option>';
        echo '<option value="company"' . selected( $client_type, 'company', false ) . '>' . esc_html__( 'Firmy', 'estate-office' ) . '</option>';
        echo '</select>';
        submit_button( __( 'Filtruj', 'estate-office' ), '', '', false );
        echo '</p>';
        echo '</form>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Klient', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Telefon', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'E-mail', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="7">' . esc_html__( 'Brak klientów do wyświetlenia.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $name = 'person' === $item['client_type']
                    ? trim( $item['first_name'] . ' ' . $item['last_name'] )
                    : $item['company_name'];

                $actions = [];
                $base_args = [
                    'page'      => self::PAGE_SLUG,
                    'client_id' => $item['id'],
                ];

                $actions[] = '<a href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'action' => 'view' ] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Podgląd', 'estate-office' ) . '</a>';
                $actions[] = '<a href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'action' => 'edit' ] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';

                if ( current_user_can( 'delete_estate_office_clients' ) ) {
                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action' => 'estate_office_delete_client',
                                'client_id' => $item['id'],
                            ],
                            admin_url( 'admin-post.php' )
                        ),
                        'estate-office-delete-client-' . $item['id']
                    );
                    $actions[] = '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js__( 'Czy na pewno chcesz usunąć klienta?', 'estate-office' ) . '\');">' . esc_html__( 'Usuń', 'estate-office' ) . '</a>';
                }

                echo '<tr>';
                echo '<td><strong>' . esc_html( $name ) . '</strong></td>';
                echo '<td>' . esc_html( $item['address_city'] . ', ' . $item['address_street'] . ' ' . $item['address_number'] ) . '</td>';
                echo '<td>' . esc_html( $item['phone'] ) . '</td>';
                echo '<td><a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a></td>';
                echo '<td>' . esc_html( 'person' === $item['client_type'] ? __( 'Osoba fizyczna', 'estate-office' ) : __( 'Firma', 'estate-office' ) ) . '</td>';
                echo '<td>' . wp_kses_post( $this->format_agent_cell( (int) ( $item['agent_id'] ?? 0 ) ) ) . '</td>';
                echo '<td>' . implode( ' | ', $actions ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        if ( $query['total_page'] > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links(
                [
                    'base'      => add_query_arg( [ 'paged' => '%#%' ] ),
                    'format'    => '',
                    'prev_text' => __( '&laquo;', 'estate-office' ),
                    'next_text' => __( '&raquo;', 'estate-office' ),
                    'total'     => $query['total_page'],
                    'current'   => $paged,
                ]
            );
            echo '</div></div>';
        }

        echo '</div>';
    }

    /**
     * Formularz klienta.
     *
     * @param array<string,mixed> $client Dane klienta.
     *
     * @return void
     */
    private function render_form( array $client = [] ) : void {
        $is_edit = ! empty( $client );

        if ( isset( $client['custom_fields'] ) && '' !== ( $client['custom_fields'] ?? '' ) ) {
            $decoded = json_decode( (string) $client['custom_fields'], true );
            if ( is_array( $decoded ) ) {
                $client['custom_fields'] = array_filter(
                    array_map(
                        static function ( $value ) {
                            return is_scalar( $value ) ? (string) $value : '';
                        },
                        $decoded
                    ),
                    static function ( $value ) {
                        return '' !== $value;
                    }
                );
            } else {
                $client['custom_fields'] = [];
            }
        }

        $defaults = [
            'client_type'               => 'person',
            'first_name'                => '',
            'last_name'                 => '',
            'company_name'              => '',
            'representative_name'       => '',
            'phone'                     => '',
            'email'                     => '',
            'website'                   => '',
            'document_type'             => '',
            'document_number'           => '',
            'pesel'                     => '',
            'nip'                       => '',
            'krs'                       => '',
            'regon'                     => '',
            'address_street'            => '',
            'address_number'            => '',
            'address_unit'              => '',
            'address_postal_code'       => '',
            'address_city'              => '',
            'address_district'          => '',
            'address_country'           => '',
            'correspondence_same'       => 1,
            'correspondence_street'     => '',
            'correspondence_number'     => '',
            'correspondence_unit'       => '',
            'correspondence_postal_code'=> '',
            'correspondence_city'       => '',
            'correspondence_country'    => '',
            'notes'                     => '',
            'agent_id'                  => 0,
            'custom_fields'             => Estate_Office_Dynamic_Fields::merge_defaults( 'client', [] ),
        ];

        $data = wp_parse_args( $client, $defaults );
        $data['agent_id'] = isset( $data['agent_id'] ) ? (int) $data['agent_id'] : 0;
        $data['custom_fields'] = Estate_Office_Dynamic_Fields::merge_defaults(
            'client',
            is_array( $data['custom_fields'] ) ? $data['custom_fields'] : []
        );

        if ( ! $is_edit && 0 === $data['agent_id'] ) {
            $data['agent_id'] = $this->get_current_user_agent_id();
        }

        if ( $data['agent_id'] > 0 ) {
            $this->prime_agent_labels( [ $data['agent_id'] ] );
        }

        echo '<div class="wrap">';
        echo '<h1>' . ( $is_edit ? esc_html__( 'Edytuj klienta', 'estate-office' ) : esc_html__( 'Dodaj klienta', 'estate-office' ) ) . '</h1>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-client-form">';
        wp_nonce_field( 'estate-office-save-client' );
        echo '<input type="hidden" name="action" value="estate_office_save_client" />';
        if ( $is_edit ) {
            echo '<input type="hidden" name="client_id" value="' . esc_attr( $client['id'] ) . '" />';
        }

        echo '<table class="form-table">';
        echo '<tr><th><label for="client_type">' . esc_html__( 'Typ klienta', 'estate-office' ) . '</label></th><td>';
        echo '<select name="client_type" id="client_type">';
        echo '<option value="person"' . selected( $data['client_type'], 'person', false ) . '>' . esc_html__( 'Osoba fizyczna', 'estate-office' ) . '</option>';
        echo '<option value="company"' . selected( $data['client_type'], 'company', false ) . '>' . esc_html__( 'Firma', 'estate-office' ) . '</option>';
        echo '</select>';
        echo '</td></tr>';

        $agent_options     = $this->get_agent_select_options();
        $agent_description = '';
        if ( ! $this->can_assign_all_agents() ) {
            if ( $this->get_current_user_agent_id() > 0 ) {
                $agent_description = __( 'Możesz przypisać jedynie siebie jako opiekuna.', 'estate-office' );
            } else {
                $agent_description = __( 'Twoje konto nie ma przypisanego profilu agenta. Skontaktuj się z administratorem.', 'estate-office' );
            }
        }
        echo '<tr><th><label for="agent_id">' . esc_html__( 'Opiekun (agent)', 'estate-office' ) . '</label></th><td>';
        echo '<select name="agent_id" id="agent_id">';
        echo '<option value="">' . esc_html__( 'Wybierz…', 'estate-office' ) . '</option>';
        foreach ( $agent_options as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $data['agent_id'], (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        if ( '' !== $agent_description ) {
            echo '<p class="description">' . esc_html( $agent_description ) . '</p>';
        }
        echo '</td></tr>';

        echo '<tr class="estate-office-client-type" data-type="person"><th>' . esc_html__( 'Imię i nazwisko', 'estate-office' ) . '</th><td>';
        echo '<input type="text" name="first_name" value="' . esc_attr( $data['first_name'] ) . '" placeholder="' . esc_attr__( 'Imię', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="last_name" value="' . esc_attr( $data['last_name'] ) . '" placeholder="' . esc_attr__( 'Nazwisko', 'estate-office' ) . '" class="regular-text" />';
        echo '</td></tr>';

        echo '<tr class="estate-office-client-type" data-type="company"><th>' . esc_html__( 'Dane firmy', 'estate-office' ) . '</th><td>';
        echo '<input type="text" name="company_name" value="' . esc_attr( $data['company_name'] ) . '" placeholder="' . esc_attr__( 'Nazwa firmy', 'estate-office' ) . '" class="regular-text" />';
        echo '<p><input type="text" name="representative_name" value="' . esc_attr( $data['representative_name'] ) . '" placeholder="' . esc_attr__( 'Imię i nazwisko reprezentanta', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Kontakt', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Telefon', 'estate-office' ) . '<br /><input type="text" name="phone" value="' . esc_attr( $data['phone'] ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'E-mail', 'estate-office' ) . '<br /><input type="email" name="email" value="' . esc_attr( $data['email'] ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Strona WWW', 'estate-office' ) . '<br /><input type="url" name="website" value="' . esc_attr( $data['website'] ) . '" class="regular-text" /></label></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Dane identyfikacyjne', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Typ dokumentu', 'estate-office' ) . '<br /><select name="document_type">';
        $doc_types = [
            ''               => __( 'Wybierz', 'estate-office' ),
            'dowod'          => __( 'Dowód osobisty', 'estate-office' ),
            'paszport'       => __( 'Paszport', 'estate-office' ),
            'karta_pobytu'   => __( 'Karta pobytu', 'estate-office' ),
        ];
        foreach ( $doc_types as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $data['document_type'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__( 'Numer dokumentu / NIP', 'estate-office' ) . '<br /><input type="text" name="document_number" value="' . esc_attr( $data['document_number'] ) . '" class="regular-text" /></label></p>';
        echo '<p class="estate-office-client-type" data-type="person"><label>' . esc_html__( 'PESEL', 'estate-office' ) . '<br /><input type="text" name="pesel" value="' . esc_attr( $data['pesel'] ) . '" class="regular-text" /></label></p>';
        echo '<div class="estate-office-client-type" data-type="company">';
        echo '<p><label>' . esc_html__( 'NIP', 'estate-office' ) . '<br /><input type="text" name="nip" value="' . esc_attr( $data['nip'] ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'KRS', 'estate-office' ) . '<br /><input type="text" name="krs" value="' . esc_attr( $data['krs'] ) . '" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'REGON', 'estate-office' ) . '<br /><input type="text" name="regon" value="' . esc_attr( $data['regon'] ) . '" class="regular-text" /></label></p>';
        echo '</div>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Adres zamieszkania / rejestrowy', 'estate-office' ) . '</th><td>';
        echo '<p><input type="text" name="address_street" value="' . esc_attr( $data['address_street'] ) . '" placeholder="' . esc_attr__( 'Ulica', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_number" value="' . esc_attr( $data['address_number'] ) . '" placeholder="' . esc_attr__( 'Numer', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="address_unit" value="' . esc_attr( $data['address_unit'] ) . '" placeholder="' . esc_attr__( 'Lokal', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_postal_code" value="' . esc_attr( $data['address_postal_code'] ) . '" placeholder="' . esc_attr__( 'Kod pocztowy', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="address_city" value="' . esc_attr( $data['address_city'] ) . '" placeholder="' . esc_attr__( 'Miasto', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_district" value="' . esc_attr( $data['address_district'] ) . '" placeholder="' . esc_attr__( 'Dzielnica', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_country" value="' . esc_attr( $data['address_country'] ) . '" placeholder="' . esc_attr__( 'Kraj', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Adres korespondencyjny', 'estate-office' ) . '</th><td>';
        echo '<label><input type="checkbox" name="correspondence_same" id="correspondence_same" value="1"' . checked( (int) $data['correspondence_same'], 1, false ) . ' /> ' . esc_html__( 'Adres korespondencyjny taki sam jak zamieszkania', 'estate-office' ) . '</label>';
        echo '<div class="estate-office-correspondence-fields">';
        echo '<p><input type="text" name="correspondence_street" value="' . esc_attr( $data['correspondence_street'] ) . '" placeholder="' . esc_attr__( 'Ulica', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="correspondence_number" value="' . esc_attr( $data['correspondence_number'] ) . '" placeholder="' . esc_attr__( 'Numer', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="correspondence_unit" value="' . esc_attr( $data['correspondence_unit'] ) . '" placeholder="' . esc_attr__( 'Lokal', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="correspondence_postal_code" value="' . esc_attr( $data['correspondence_postal_code'] ) . '" placeholder="' . esc_attr__( 'Kod pocztowy', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="correspondence_city" value="' . esc_attr( $data['correspondence_city'] ) . '" placeholder="' . esc_attr__( 'Miasto', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="correspondence_country" value="' . esc_attr( $data['correspondence_country'] ) . '" placeholder="' . esc_attr__( 'Kraj', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '</div>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Notatki', 'estate-office' ) . '</th><td>';
        wp_editor( $data['notes'], 'estate_office_client_notes', [
            'textarea_name' => 'notes',
            'textarea_rows' => 6,
        ] );
        echo '</td></tr>';
        $client_custom_fields = Estate_Office_Dynamic_Fields::get_field_map( 'client' );
        if ( ! empty( $client_custom_fields ) ) {
            echo '<tr><th>' . esc_html__( 'Dodatkowe pola', 'estate-office' ) . '</th><td>';
            foreach ( $client_custom_fields as $field_key => $label ) {
                $value = $data['custom_fields'][ $field_key ] ?? '';
                echo '<p><label>' . esc_html( $label ) . '<br /><input type="text" name="custom_fields[' . esc_attr( $field_key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" /></label></p>';
            }
            echo '</td></tr>';
        }
        echo '</table>';

        submit_button( $is_edit ? __( 'Zapisz klienta', 'estate-office' ) : __( 'Dodaj klienta', 'estate-office' ) );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '" class="button-secondary">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Obsługuje zapis klienta.
     *
     * @return void
     */
    public function handle_save_client() : void {
        if ( ! current_user_can( 'edit_estate_office_clients' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu klienta.', 'estate-office' ) );
        }

        check_admin_referer( 'estate-office-save-client' );

        $client_id  = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        $redirect   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
        $data       = Estate_Office_Client_Request_Helper::sanitize_from_array( $_POST );
        $agent_input = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $data['agent_id'] = $this->sanitize_agent_selection( $agent_input );
        $is_portal  = ! empty( $redirect );
        $message    = '';
        $status     = '';

        if ( $client_id ) {
            $updated = $this->repository->update( $client_id, $data );
            if ( $updated ) {
                $message = __( 'Klient został zaktualizowany.', 'estate-office' );
                $status  = 'updated';
            } else {
                $message = __( 'Nie udało się zaktualizować klienta.', 'estate-office' );
                $status  = 'error';
            }
        } else {
            $client_id = $this->repository->create( $data );
            if ( $client_id ) {
                $message = __( 'Klient został dodany.', 'estate-office' );
                $status  = 'updated';
            } else {
                $message = __( 'Nie udało się dodać klienta.', 'estate-office' );
                $status  = 'error';
            }
        }

        if ( $is_portal && $redirect ) {
            $redirect_url = wp_validate_redirect( $redirect, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            if ( $client_id ) {
                $redirect_url = add_query_arg( 'client_id', $client_id, $redirect_url );
            }
            $redirect_url = add_query_arg(
                [
                    'estate-office-message' => $message,
                    'estate-office-status'  => $status,
                ],
                $redirect_url
            );

            wp_safe_redirect( $redirect_url );
            exit;
        }

        $redirect_args = [
            'page'                  => self::PAGE_SLUG,
            'estate-office-message' => $message,
            'estate-office-status'  => $status,
        ];

        if ( $client_id && 'error' !== $status ) {
            $redirect_args['action']    = 'view';
            $redirect_args['client_id'] = $client_id;
        }

        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Obsługuje usuwanie klienta.
     *
     * @return void
     */
    public function handle_delete_client() : void {
        if ( ! current_user_can( 'delete_estate_office_clients' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do usunięcia klienta.', 'estate-office' ) );
        }

        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        check_admin_referer( 'estate-office-delete-client-' . $client_id );

        if ( $client_id ) {
            $this->repository->delete( $client_id );
        }

        $redirect = add_query_arg(
            [
                'page'                  => self::PAGE_SLUG,
                'estate-office-message' => __( 'Klient został usunięty.', 'estate-office' ),
                'estate-office-status'  => 'updated',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Podgląd profilu klienta.
     *
     * @param array<string,mixed> $client Dane.
     *
     * @return void
     */
    private function render_profile( array $client ) : void {
        $contracts = $this->repository->get_contracts_for_client( (int) $client['id'] );
        $this->prime_agent_labels( [ (int) ( $client['agent_id'] ?? 0 ) ] );

        $name = 'person' === $client['client_type']
            ? trim( $client['first_name'] . ' ' . $client['last_name'] )
            : $client['company_name'];

        echo '<div class="wrap estate-office-client-profile">';
        echo '<h1>' . esc_html( $name ) . '</h1>';

        echo '<div class="estate-office-client-columns" style="display:flex;gap:2rem;flex-wrap:wrap;">';
        echo '<div style="flex:1 1 320px;min-width:280px;">';
        echo '<h2>' . esc_html__( 'Dane klienta', 'estate-office' ) . '</h2>';
        echo '<table class="widefat fixed striped">';
        $agent_cell = $this->format_agent_cell( (int) ( $client['agent_id'] ?? 0 ) );
        $rows = [
            __( 'Typ', 'estate-office' ) => 'person' === $client['client_type'] ? __( 'Osoba fizyczna', 'estate-office' ) : __( 'Firma', 'estate-office' ),
            __( 'Telefon', 'estate-office' ) => $client['phone'],
            __( 'E-mail', 'estate-office' ) => $client['email'],
            __( 'Strona WWW', 'estate-office' ) => $client['website'],
            __( 'PESEL', 'estate-office' ) => $client['pesel'],
            __( 'NIP', 'estate-office' ) => $client['nip'],
            __( 'KRS', 'estate-office' ) => $client['krs'],
            __( 'REGON', 'estate-office' ) => $client['regon'],
            __( 'Opiekun', 'estate-office' ) => $agent_cell,
        ];
        $custom_values = [];
        if ( isset( $client['custom_fields'] ) && '' !== ( $client['custom_fields'] ?? '' ) ) {
            $decoded = json_decode( (string) $client['custom_fields'], true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $custom_key => $custom_value ) {
                    if ( is_scalar( $custom_value ) ) {
                        $custom_values[ (string) $custom_key ] = (string) $custom_value;
                    }
                }
            }
        }

        foreach ( Estate_Office_Dynamic_Fields::format_for_display( 'client', $custom_values ) as $label => $value ) {
            $rows[ $label ] = $value;
        }

        foreach ( $rows as $label => $value ) {
            if ( empty( $value ) ) {
                continue;
            }
            echo '<tr><th style="width:35%;">' . esc_html( $label ) . '</th><td>';
            if ( __( 'E-mail', 'estate-office' ) === $label ) {
                echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
            } elseif ( __( 'Strona WWW', 'estate-office' ) === $label ) {
                echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
            } elseif ( __( 'Opiekun', 'estate-office' ) === $label ) {
                echo wp_kses_post( $agent_cell );
            } else {
                echo esc_html( $value );
            }
            echo '</td></tr>';
        }
        echo '</table>';

        echo '<h3>' . esc_html__( 'Adres zamieszkania / rejestrowy', 'estate-office' ) . '</h3>';
        echo '<p>' . esc_html( $client['address_street'] . ' ' . $client['address_number'] . ( $client['address_unit'] ? '/' . $client['address_unit'] : '' ) ) . '<br />' . esc_html( $client['address_postal_code'] . ' ' . $client['address_city'] ) . '<br />' . esc_html( $client['address_country'] ) . '</p>';

        echo '<h3>' . esc_html__( 'Adres korespondencyjny', 'estate-office' ) . '</h3>';
        echo '<p>' . esc_html( $client['correspondence_street'] . ' ' . $client['correspondence_number'] . ( $client['correspondence_unit'] ? '/' . $client['correspondence_unit'] : '' ) ) . '<br />' . esc_html( $client['correspondence_postal_code'] . ' ' . $client['correspondence_city'] ) . '<br />' . esc_html( $client['correspondence_country'] ) . '</p>';
        echo '</div>';

        echo '<div style="flex:1 1 320px;min-width:280px;">';
        echo '<h2>' . esc_html__( 'Powiązane umowy', 'estate-office' ) . '</h2>';
        if ( empty( $contracts ) ) {
            echo '<p>' . esc_html__( 'Brak przypisanych umów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Numer umowy', 'estate-office' ) . '</th><th>' . esc_html__( 'Typ', 'estate-office' ) . '</th><th>' . esc_html__( 'Rola', 'estate-office' ) . '</th><th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th></tr></thead><tbody>';
            foreach ( $contracts as $contract ) {
                $link = add_query_arg(
                    [
                        'page'        => 'estate-office-contracts',
                        'action'      => 'view',
                        'contract_id' => $contract['id'],
                    ],
                    admin_url( 'admin.php' )
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $contract['contract_number'] ) . '</a></td>';
                echo '<td>' . esc_html( strtoupper( $contract['transaction_type'] ) ) . '</td>';
                echo '<td>' . esc_html( $contract['role'] ) . '</td>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html__( 'Przejdź do umowy', 'estate-office' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        if ( ! empty( $client['notes'] ) ) {
            echo '<h2>' . esc_html__( 'Notatki', 'estate-office' ) . '</h2>';
            echo '<div class="notice notice-info"><p>' . wp_kses_post( wpautop( $client['notes'] ) ) . '</p></div>';
        }

        echo '</div>';
        echo '</div>';

        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '" class="button">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a> ';
        echo '<a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'edit', 'client_id' => $client['id'] ], admin_url( 'admin.php' ) ) ) . '" class="button button-primary">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a></p>';

        echo '</div>';
    }
}
