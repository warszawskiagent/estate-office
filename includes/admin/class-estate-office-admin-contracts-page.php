<?php
/**
 * Ekran administracyjny umów EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/admin/class-estate-office-admin-agent-assignment.php';

/**
 * Class Estate_Office_Admin_Contracts_Page
 */
class Estate_Office_Admin_Contracts_Page {

    use Estate_Office_Admin_Agent_Assignment;

    private const PAGE_SLUG = 'estate-office-contracts';

    /**
     * Repozytorium umów.
     *
     * @var Estate_Office_Contract_Repository
     */
    private Estate_Office_Contract_Repository $repository;

    /**
     * Repozytorium klientów.
     *
     * @var Estate_Office_Client_Repository
     */
    private Estate_Office_Client_Repository $clients_repository;

    /**
     * Repozytorium nieruchomości.
     *
     * @var Estate_Office_Property_Repository
     */
    private Estate_Office_Property_Repository $properties_repository;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Contract_Repository|null $repository           Repozytorium umów.
     * @param Estate_Office_Client_Repository|null   $clients_repository   Repozytorium klientów.
     * @param Estate_Office_Property_Repository|null $properties_repository Repozytorium nieruchomości.
     * @param Estate_Office_Agent_Repository|null    $agent_repository     Repozytorium agentów.
     */
    public function __construct(
        ?Estate_Office_Contract_Repository $repository = null,
        ?Estate_Office_Client_Repository $clients_repository = null,
        ?Estate_Office_Property_Repository $properties_repository = null,
        ?Estate_Office_Agent_Repository $agent_repository = null
    ) {
        $this->repository             = $repository ?? new Estate_Office_Contract_Repository();
        $this->clients_repository     = $clients_repository ?? new Estate_Office_Client_Repository();
        $this->properties_repository  = $properties_repository ?? new Estate_Office_Property_Repository();
        $this->init_agent_repository( $agent_repository );
    }

    /**
     * Rejestruje hooki.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_contract', [ $this, 'handle_save_contract' ] );
        add_action( 'admin_post_estate_office_delete_contract', [ $this, 'handle_delete_contract' ] );
        add_action( 'admin_post_estate_office_contract_add_client', [ $this, 'handle_add_client' ] );
        add_action( 'admin_post_estate_office_contract_remove_client', [ $this, 'handle_remove_client' ] );
        add_action( 'admin_post_estate_office_contract_update_stage', [ $this, 'handle_update_stage' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Rejestruje zasoby.
     *
     * @param string $hook Hook.
     *
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        if ( 'estate-office-crm_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        $handle = 'estate-office-admin-contracts';
        wp_register_script( $handle, false, [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $handle );
        wp_add_inline_script(
            $handle,
            <<<'JS'
jQuery(function($){
    const checkbox = $('#is_open_ended');
    function toggleEndDate(){
        const checked = checkbox.is(':checked');
        $('#end_date').prop('disabled', checked).closest('p').toggle(!checked);
    }
    checkbox.on('change', toggleEndDate);
    toggleEndDate();
});
JS
        );
    }

    /**
     * Renderuje stronę.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_contracts' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do przeglądania umów.', 'estate-office' ) );
        }

        $action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $contract_id = isset( $_GET['contract_id'] ) ? absint( $_GET['contract_id'] ) : 0;
        $step        = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'details';

        if ( 'new' === $action ) {
            if ( $contract_id ) {
                $contract = $this->repository->find( $contract_id );
                if ( null === $contract ) {
                    $this->render_list( __( 'Nie znaleziono wskazanej umowy.', 'estate-office' ), 'error' );

                    return;
                }

                if ( 'clients' === $step ) {
                    $this->render_clients_step( $contract );

                    return;
                }

                if ( in_array( $step, [ 'property', 'search' ], true ) ) {
                    $redirect = $this->get_step_three_url( $contract, $step );
                    if ( $redirect ) {
                        wp_safe_redirect( $redirect );
                        exit;
                    }

                    $this->render_clients_step( $contract );

                    return;
                }
            }

            $this->render_form();

            return;
        }

        if ( 'edit' === $action && $contract_id ) {
            $contract = $this->repository->find( $contract_id );
            if ( null === $contract ) {
                $this->render_list( __( 'Nie znaleziono wskazanej umowy.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_form( $contract );

            return;
        }

        if ( 'manage-clients' === $action && $contract_id ) {
            $contract = $this->repository->find( $contract_id );
            if ( null === $contract ) {
                $this->render_list( __( 'Nie znaleziono wskazanej umowy.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_clients_step( $contract );

            return;
        }

        if ( 'view' === $action && $contract_id ) {
            $contract = $this->repository->find( $contract_id );
            if ( null === $contract ) {
                $this->render_list( __( 'Nie znaleziono wskazanej umowy.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_profile( $contract );

            return;
        }

        $this->render_list();
    }

    /**
     * Wyświetla listę umów.
     *
     * @param string $notice    Komunikat.
     * @param string $notice_id Typ komunikatu.
     *
     * @return void
     */
    private function render_list( string $notice = '', string $notice_id = 'updated' ) : void {
        $search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $transaction     = isset( $_GET['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_type'] ) ) : '';
        $status          = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        $paged           = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $query = $this->repository->paginate(
            [
                'paged'           => $paged,
                'search'          => $search,
                'transaction_type'=> $transaction,
                'status'          => $status,
            ]
        );

        $agent_ids = [];
        if ( isset( $query['items'] ) && is_array( $query['items'] ) ) {
            $agent_ids = array_map( 'intval', wp_list_pluck( $query['items'], 'agent_id' ) );
        }
        $this->prime_agent_labels( $agent_ids );

        $message       = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : $notice;
        $message_class = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : $notice_id;

        echo '<div class="wrap estate-office-contracts">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Umowy', 'estate-office' ) . '</h1>';
        echo ' <a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '" class="page-title-action">' . esc_html__( 'Dodaj umowę', 'estate-office' ) . '</a>';

        if ( ! empty( $message ) ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $message_class ), esc_html( $message ) );
        }

        echo '<form method="get" class="estate-office-contracts-filter">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="estate-office-contracts-search">' . esc_html__( 'Szukaj umów', 'estate-office' ) . '</label>';
        echo '<input type="search" id="estate-office-contracts-search" name="s" value="' . esc_attr( $search ) . '" />';
        echo '<select name="transaction_type">';
        echo '<option value="">' . esc_html__( 'Wszystkie typy transakcji', 'estate-office' ) . '</option>';
        foreach ( $this->get_transaction_types() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $transaction, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<select name="status">';
        echo '<option value="">' . esc_html__( 'Wszystkie statusy', 'estate-office' ) . '</option>';
        foreach ( $this->get_statuses() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Filtruj', 'estate-office' ), '', '', false );
        echo '</p>';
        echo '</form>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer umowy', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Data zawarcia', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Data zakończenia', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Etap', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="8">' . esc_html__( 'Brak umów do wyświetlenia.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $view_link = add_query_arg(
                    [
                        'page'        => self::PAGE_SLUG,
                        'action'      => 'view',
                        'contract_id' => $item['id'],
                    ],
                    admin_url( 'admin.php' )
                );

                $actions = [];
                $actions[] = '<a href="' . esc_url( $view_link ) . '">' . esc_html__( 'Podgląd', 'estate-office' ) . '</a>';
                $actions[] = '<a href="' . esc_url( add_query_arg( [ 'action' => 'manage-clients', 'contract_id' => $item['id'] ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ) . '">' . esc_html__( 'Klienci', 'estate-office' ) . '</a>';
                $actions[] = '<a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'edit', 'contract_id' => $item['id'] ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a>';

                if ( current_user_can( 'delete_estate_office_contracts' ) ) {
                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'      => 'estate_office_delete_contract',
                                'contract_id' => $item['id'],
                            ],
                            admin_url( 'admin-post.php' )
                        ),
                        'estate-office-delete-contract-' . $item['id']
                    );
                    $actions[] = '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Czy na pewno chcesz usunąć umowę?', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń', 'estate-office' ) . '</a>';
                }

                echo '<tr>';
                echo '<td><a href="' . esc_url( $view_link ) . '">' . esc_html( $item['contract_number'] ) . '</a></td>';
                echo '<td>' . esc_html( $this->get_transaction_types()[ $item['transaction_type'] ] ?? strtoupper( $item['transaction_type'] ) ) . '</td>';
                echo '<td>' . esc_html( $item['start_date'] ) . '</td>';
                echo '<td>' . esc_html( $item['end_date'] ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $this->get_stage_label( $item['current_stage'] ) ) . '</td>';
                echo '<td>' . esc_html( $this->get_statuses()[ $item['status'] ] ?? $item['status'] ) . '</td>';
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
     * Formularz szczegółów umowy.
     *
     * @param array<string,mixed> $contract Dane.
     *
     * @return void
     */
    private function render_form( array $contract = [] ) : void {
        $is_edit = ! empty( $contract );

        if ( isset( $contract['custom_fields'] ) && '' !== $contract['custom_fields'] ) {
            $decoded = json_decode( (string) $contract['custom_fields'], true );
            if ( is_array( $decoded ) ) {
                $contract['custom_fields'] = array_filter(
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
                $contract['custom_fields'] = [];
            }
        }

        $defaults = [
            'contract_number'   => $this->repository->generate_contract_number(),
            'transaction_type'  => 'sprzedaz',
            'start_date'        => gmdate( 'Y-m-d' ),
            'end_date'          => '',
            'is_open_ended'     => 0,
            'commission_amount' => '',
            'commission_unit'   => '%',
            'status'            => 'draft',
            'current_stage'     => 'umowa_posrednictwa',
            'current_stage_date'=> gmdate( 'Y-m-d' ),
            'stage_notes'       => '',
            'agent_id'          => 0,
            'custom_fields'     => Estate_Office_Dynamic_Fields::merge_defaults( 'contract', [] ),
        ];

        $data = wp_parse_args( $contract, $defaults );
        $data['agent_id'] = isset( $data['agent_id'] ) ? (int) $data['agent_id'] : 0;
        $data['custom_fields'] = Estate_Office_Dynamic_Fields::merge_defaults(
            'contract',
            is_array( $data['custom_fields'] ) ? $data['custom_fields'] : []
        );

        if ( ! $is_edit && 0 === $data['agent_id'] ) {
            $data['agent_id'] = $this->get_current_user_agent_id();
        }

        if ( $data['agent_id'] > 0 ) {
            $this->prime_agent_labels( [ $data['agent_id'] ] );
        }

        echo '<div class="wrap estate-office-contract-form">';
        echo '<h1>' . ( $is_edit ? esc_html__( 'Edytuj umowę', 'estate-office' ) : esc_html__( 'Nowa umowa – etap 1/3', 'estate-office' ) ) . '</h1>';
        echo '<p>' . esc_html__( 'Uzupełnij dane umowy. Po zapisaniu przejdziesz do przypisywania klientów.', 'estate-office' ) . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-contract-form">';
        wp_nonce_field( 'estate-office-save-contract' );
        echo '<input type="hidden" name="action" value="estate_office_save_contract" />';
        if ( $is_edit ) {
            echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract['id'] ) . '" />';
        }

        echo '<table class="form-table">';
        echo '<tr><th><label for="contract_number">' . esc_html__( 'Numer umowy', 'estate-office' ) . '</label></th><td>';
        printf( '<input type="text" name="contract_number" id="contract_number" value="%s" class="regular-text" required />', esc_attr( $data['contract_number'] ) );
        echo '</td></tr>';

        echo '<tr><th><label for="transaction_type">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</label></th><td>';
        echo '<select name="transaction_type" id="transaction_type">';
        foreach ( $this->get_transaction_types() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $data['transaction_type'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        $agent_options      = $this->get_agent_select_options();
        $agent_description  = '';
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

        echo '<tr><th>' . esc_html__( 'Daty', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Data zawarcia', 'estate-office' ) . '<br /><input type="date" name="start_date" value="' . esc_attr( $data['start_date'] ) . '" required /></label></p>';
        echo '<p><label><input type="checkbox" name="is_open_ended" id="is_open_ended" value="1"' . checked( (int) $data['is_open_ended'], 1, false ) . ' /> ' . esc_html__( 'Umowa bezterminowa', 'estate-office' ) . '</label></p>';
        echo '<p><label>' . esc_html__( 'Data zakończenia', 'estate-office' ) . '<br /><input type="date" name="end_date" id="end_date" value="' . esc_attr( $data['end_date'] ) . '" /></label></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Prowizja', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Kwota', 'estate-office' ) . '<br /><input type="number" step="0.01" name="commission_amount" value="' . esc_attr( $data['commission_amount'] ) . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Jednostka', 'estate-office' ) . '<br /><select name="commission_unit">';
        foreach ( [ '%', 'PLN', 'EUR', 'USD' ] as $unit ) {
            echo '<option value="' . esc_attr( $unit ) . '"' . selected( $data['commission_unit'], $unit, false ) . '>' . esc_html( $unit ) . '</option>';
        }
        echo '</select></label></p>';
        echo '</td></tr>';

        echo '<tr><th><label for="status">' . esc_html__( 'Status umowy', 'estate-office' ) . '</label></th><td>';
        echo '<select name="status" id="status">';
        foreach ( $this->get_statuses() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $data['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        $contract_custom_fields = Estate_Office_Dynamic_Fields::get_field_map( 'contract' );
        if ( ! empty( $contract_custom_fields ) ) {
            echo '<tr><th>' . esc_html__( 'Dodatkowe pola', 'estate-office' ) . '</th><td>';
            foreach ( $contract_custom_fields as $field_key => $label ) {
                $value = $data['custom_fields'][ $field_key ] ?? '';
                echo '<p><label>' . esc_html( $label ) . '<br /><input type="text" class="regular-text" name="custom_fields[' . esc_attr( $field_key ) . ']" value="' . esc_attr( $value ) . '" /></label></p>';
            }
            echo '</td></tr>';
        }

        if ( $is_edit ) {
            echo '<tr><th>' . esc_html__( 'Bieżący etap', 'estate-office' ) . '</th><td>';
            echo '<p>' . esc_html( $this->get_stage_label( $data['current_stage'] ) ) . ' (' . esc_html( $data['current_stage_date'] ) . ')</p>';
            echo '</td></tr>';
        }

        echo '</table>';

        submit_button( $is_edit ? __( 'Zapisz umowę', 'estate-office' ) : __( 'Zapisz i przejdź do klientów', 'estate-office' ) );
        echo ' <a href="' . esc_url( $this->get_contracts_list_url() ) . '" class="button-secondary">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Zwraca adres listy umów w portalu CRM.
     *
     * @return string
     */
    private function get_contracts_list_url() : string {
        $portal_page_id = (int) get_option( 'estate_office_portal_page_id', 0 );

        if ( $portal_page_id > 0 ) {
            $permalink = get_permalink( $portal_page_id );

            if ( $permalink ) {
                return add_query_arg(
                    [
                        'crm_tab' => 'contracts',
                    ],
                    $permalink
                );
            }
        }

        return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
    }

    /**
     * Krok przypisywania klientów.
     *
     * @param array<string,mixed> $contract Dane umowy.
     *
     * @return void
     */
    private function render_clients_step( array $contract ) : void {
        $attached_clients = $this->repository->get_clients( (int) $contract['id'] );
        $search_term      = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
        $search_results   = [];

        if ( ! empty( $search_term ) ) {
            $search_results = $this->clients_repository->search( $search_term, 15 );
        }

        echo '<div class="wrap estate-office-contract-clients">';
        echo '<h1>' . esc_html__( 'Nowa umowa – etap 2/3', 'estate-office' ) . '</h1>';
        echo '<p>' . esc_html__( 'Przypisz klientów do umowy. Możesz wyszukać istniejące rekordy lub dodać nowego klienta.', 'estate-office' ) . '</p>';

        if ( isset( $_GET['estate-office-message'] ) ) {
            $status  = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';
            $message = sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) );
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<h2>' . esc_html__( 'Klienci przypisani do umowy', 'estate-office' ) . '</h2>';
        if ( empty( $attached_clients ) ) {
            echo '<p>' . esc_html__( 'Nie przypisano jeszcze żadnych klientów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Klient', 'estate-office' ) . '</th><th>' . esc_html__( 'Rola', 'estate-office' ) . '</th><th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th></tr></thead><tbody>';
            foreach ( $attached_clients as $client ) {
                $name = 'person' === $client['client_type'] ? trim( $client['first_name'] . ' ' . $client['last_name'] ) : $client['company_name'];
                $remove_url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action'      => 'estate_office_contract_remove_client',
                            'contract_id' => $contract['id'],
                            'client_id'   => $client['id'],
                        ],
                        admin_url( 'admin-post.php' )
                    ),
                    'estate-office-contract-remove-client-' . $contract['id'] . '-' . $client['id']
                );

                echo '<tr>';
                echo '<td>' . esc_html( $name ) . '</td>';
                echo '<td>' . esc_html( $client['role'] ?: __( 'Klient', 'estate-office' ) ) . '</td>';
                echo '<td><a href="' . esc_url( $remove_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Czy usunąć klienta z umowy?', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń powiązanie', 'estate-office' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<hr />';

        echo '<h2>' . esc_html__( 'Wyszukaj istniejącego klienta', 'estate-office' ) . '</h2>';
        echo '<form method="get" class="estate-office-contract-client-search estate-office-inline-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
        echo '<input type="hidden" name="action" value="manage-clients" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract['id'] ) . '" />';
        echo '<p><input type="search" name="term" value="' . esc_attr( $search_term ) . '" placeholder="' . esc_attr__( 'Imię, nazwisko, telefon lub e-mail', 'estate-office' ) . '" class="regular-text" /> ';
        submit_button( __( 'Szukaj', 'estate-office' ), '', '', false );
        echo '</p>';
        echo '</form>';

        if ( ! empty( $search_term ) ) {
            echo '<h3>' . esc_html__( 'Wyniki wyszukiwania', 'estate-office' ) . '</h3>';
            if ( empty( $search_results ) ) {
                echo '<p>' . esc_html__( 'Brak wyników dla podanej frazy.', 'estate-office' ) . '</p>';
            } else {
                echo '<ul class="estate-office-contract-client-results">';
                foreach ( $search_results as $client ) {
                    $name = 'person' === $client['client_type'] ? trim( $client['first_name'] . ' ' . $client['last_name'] ) : $client['company_name'];
                    echo '<li>';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-inline-form">';
                    wp_nonce_field( 'estate-office-contract-add-client' );
                    echo '<input type="hidden" name="action" value="estate_office_contract_add_client" />';
                    echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract['id'] ) . '" />';
                    echo '<input type="hidden" name="client_id" value="' . esc_attr( $client['id'] ) . '" />';
                    echo esc_html( $name ) . ' – ' . esc_html( $client['email'] ) . ' ';
                    echo '<select name="role">';
                    echo '<option value="">' . esc_html__( 'Klient', 'estate-office' ) . '</option>';
                    echo '<option value="sprzedajacy">' . esc_html__( 'Sprzedający', 'estate-office' ) . '</option>';
                    echo '<option value="kupujacy">' . esc_html__( 'Kupujący', 'estate-office' ) . '</option>';
                    echo '<option value="wynajmujacy">' . esc_html__( 'Wynajmujący', 'estate-office' ) . '</option>';
                    echo '<option value="najmujacy">' . esc_html__( 'Najmujący', 'estate-office' ) . '</option>';
                    echo '</select> ';
                    submit_button( __( 'Dodaj', 'estate-office' ), 'secondary', '', false );
                    echo '</form>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }

        echo '<hr />';
        echo '<h2>' . esc_html__( 'Dodaj nowego klienta', 'estate-office' ) . '</h2>';

        $this->enqueue_client_inline_assets();

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-contract-client-create" id="estate-office-contract-add-client">';
        wp_nonce_field( 'estate-office-contract-add-client' );
        echo '<input type="hidden" name="action" value="estate_office_contract_add_client" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract['id'] ) . '" />';
        echo '<input type="hidden" name="create_new_client" value="1" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="contract-client-type">' . esc_html__( 'Typ klienta', 'estate-office' ) . '</label></th><td>';
        echo '<select name="client_type" id="contract-client-type">';
        echo '<option value="person">' . esc_html__( 'Osoba fizyczna', 'estate-office' ) . '</option>';
        echo '<option value="company">' . esc_html__( 'Firma', 'estate-office' ) . '</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '<tr class="estate-office-client-type" data-type="person"><th>' . esc_html__( 'Imię i nazwisko', 'estate-office' ) . '</th><td>';
        echo '<input type="text" name="first_name" placeholder="' . esc_attr__( 'Imię', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="last_name" placeholder="' . esc_attr__( 'Nazwisko', 'estate-office' ) . '" class="regular-text" />';
        echo '</td></tr>';

        echo '<tr class="estate-office-client-type" data-type="company"><th>' . esc_html__( 'Dane firmy', 'estate-office' ) . '</th><td>';
        echo '<input type="text" name="company_name" placeholder="' . esc_attr__( 'Nazwa firmy', 'estate-office' ) . '" class="regular-text" />';
        echo '<p><input type="text" name="representative_name" placeholder="' . esc_attr__( 'Imię i nazwisko reprezentanta', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Kontakt', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Telefon', 'estate-office' ) . '<br /><input type="text" name="phone" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'E-mail', 'estate-office' ) . '<br /><input type="email" name="email" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Strona WWW', 'estate-office' ) . '<br /><input type="url" name="website" class="regular-text" /></label></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Dane identyfikacyjne', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Typ dokumentu', 'estate-office' ) . '<br /><select name="document_type">';
        $doc_types = [
            ''             => __( 'Wybierz', 'estate-office' ),
            'dowod'        => __( 'Dowód osobisty', 'estate-office' ),
            'paszport'     => __( 'Paszport', 'estate-office' ),
            'karta_pobytu' => __( 'Karta pobytu', 'estate-office' ),
        ];
        foreach ( $doc_types as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__( 'Numer dokumentu / NIP', 'estate-office' ) . '<br /><input type="text" name="document_number" class="regular-text" /></label></p>';
        echo '<p class="estate-office-client-type" data-type="person"><label>' . esc_html__( 'PESEL', 'estate-office' ) . '<br /><input type="text" name="pesel" class="regular-text" /></label></p>';
        echo '<div class="estate-office-client-type" data-type="company">';
        echo '<p><label>' . esc_html__( 'NIP', 'estate-office' ) . '<br /><input type="text" name="nip" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'KRS', 'estate-office' ) . '<br /><input type="text" name="krs" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'REGON', 'estate-office' ) . '<br /><input type="text" name="regon" class="regular-text" /></label></p>';
        echo '</div>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Adres zamieszkania / rejestrowy', 'estate-office' ) . '</th><td>';
        echo '<p><input type="text" name="address_street" placeholder="' . esc_attr__( 'Ulica', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_number" placeholder="' . esc_attr__( 'Numer', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="address_unit" placeholder="' . esc_attr__( 'Lokal', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_postal_code" placeholder="' . esc_attr__( 'Kod pocztowy', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="address_city" placeholder="' . esc_attr__( 'Miasto', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_district" placeholder="' . esc_attr__( 'Dzielnica', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="address_country" placeholder="' . esc_attr__( 'Kraj', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Adres korespondencyjny', 'estate-office' ) . '</th><td>';
        echo '<label><input type="checkbox" name="correspondence_same" id="contract-correspondence-same" value="1" checked /> ' . esc_html__( 'Adres korespondencyjny taki sam jak zamieszkania', 'estate-office' ) . '</label>';
        echo '<div class="estate-office-correspondence-fields" style="display:none;">';
        echo '<p><input type="text" name="correspondence_street" placeholder="' . esc_attr__( 'Ulica', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="correspondence_number" placeholder="' . esc_attr__( 'Numer', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="correspondence_unit" placeholder="' . esc_attr__( 'Lokal', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="correspondence_postal_code" placeholder="' . esc_attr__( 'Kod pocztowy', 'estate-office' ) . '" class="regular-text" /> ';
        echo '<input type="text" name="correspondence_city" placeholder="' . esc_attr__( 'Miasto', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '<p><input type="text" name="correspondence_country" placeholder="' . esc_attr__( 'Kraj', 'estate-office' ) . '" class="regular-text" /></p>';
        echo '</div>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Rola w umowie', 'estate-office' ) . '</th><td>';
        echo '<select name="role">';
        echo '<option value="">' . esc_html__( 'Klient', 'estate-office' ) . '</option>';
        echo '<option value="sprzedajacy">' . esc_html__( 'Sprzedający', 'estate-office' ) . '</option>';
        echo '<option value="kupujacy">' . esc_html__( 'Kupujący', 'estate-office' ) . '</option>';
        echo '<option value="wynajmujacy">' . esc_html__( 'Wynajmujący', 'estate-office' ) . '</option>';
        echo '<option value="najmujacy">' . esc_html__( 'Najmujący', 'estate-office' ) . '</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        submit_button( __( 'Dodaj klienta do umowy', 'estate-office' ) );
        echo '</form>';

        $step_three = $this->determine_step_three( $contract );
        if ( $step_three ) {
            $next_url   = $this->get_step_three_url( $contract, $step_three );
            $next_label = 'property' === $step_three
                ? __( 'Nie, przejdź do etapu 3 – dodaj nieruchomość', 'estate-office' )
                : __( 'Nie, przejdź do etapu 3 – dodaj poszukiwanie', 'estate-office' );

            echo '<hr />';
            echo '<div class="estate-office-contract-step-footer">';
            echo '<h2>' . esc_html__( 'Co dalej?', 'estate-office' ) . '</h2>';
            echo '<p>' . esc_html__( 'Czy chcesz dodać kolejnego klienta?', 'estate-office' ) . '</p>';
            echo '<p>';
            echo '<a class="button button-secondary" href="#estate-office-contract-add-client">' . esc_html__( 'Tak, dodaj kolejnego klienta', 'estate-office' ) . '</a> ';
            if ( $next_url ) {
                echo '<a class="button button-primary" href="' . esc_url( $next_url ) . '">' . esc_html( $next_label ) . '</a>';
            }
            echo '</p>';
            echo '</div>';
        }

        echo '<hr />';
        echo '<p><a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'view', 'contract_id' => $contract['id'] ], admin_url( 'admin.php' ) ) ) . '" class="button-secondary">' . esc_html__( 'Przejdź do podsumowania umowy', 'estate-office' ) . '</a></p>';
        echo '</div>';
    }

    /**
     * Dołącza skrypt wspierający formularz klienta w kreatorze.
     *
     * @return void
     */
    private function enqueue_client_inline_assets() : void {
        $handle = 'estate-office-contract-client-form';

        if ( wp_script_is( $handle, 'enqueued' ) ) {
            return;
        }

        wp_register_script( $handle, false, [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $handle );
        wp_add_inline_script(
            $handle,
            <<<'JS'
jQuery(function($){
    const typeField = $('#contract-client-type');
    const toggleType = () => {
        const type = typeField.val();
        $('.estate-office-client-type').hide();
        $('.estate-office-client-type[data-type="'+type+'"]').show();
    };
    typeField.on('change', toggleType);
    toggleType();

    const correspondence = $('#contract-correspondence-same');
    const toggleCorrespondence = () => {
        $('.estate-office-correspondence-fields').toggle(!correspondence.is(':checked'));
    };
    correspondence.on('change', toggleCorrespondence);
    toggleCorrespondence();
});
JS
        );
    }

    /**
     * Określa trzeci etap procesu tworzenia umowy.
     *
     * @param array<string,mixed> $contract Dane umowy.
     *
     * @return string
     */
    private function determine_step_three( array $contract ) : string {
        $type = isset( $contract['transaction_type'] ) ? (string) $contract['transaction_type'] : '';
        $type = function_exists( 'mb_strtolower' ) ? mb_strtolower( $type ) : strtolower( $type );

        if ( in_array( $type, [ 'sprzedaz', 'wynajem' ], true ) ) {
            return 'property';
        }

        if ( in_array( $type, [ 'kupno', 'najem' ], true ) ) {
            return 'search';
        }

        return '';
    }

    /**
     * Generuje adres trzeciego etapu kreatora.
     *
     * @param array<string,mixed> $contract       Dane umowy.
     * @param string              $requested_step Żądany krok.
     *
     * @return string
     */
    private function get_step_three_url( array $contract, string $requested_step = '' ) : string {
        $step        = $requested_step ?: $this->determine_step_three( $contract );
        $contract_id = isset( $contract['id'] ) ? (int) $contract['id'] : 0;

        if ( ! $contract_id || ! in_array( $step, [ 'property', 'search' ], true ) ) {
            return '';
        }

        $base_args = [
            'action'             => 'new',
            'contract_id'        => $contract_id,
            'wizard'             => 'contract',
            'wizard_contract_id' => $contract_id,
        ];

        if ( 'property' === $step ) {
            $args = array_merge(
                $base_args,
                [
                    'page'        => 'estate-office-properties',
                    'wizard_step' => 'property',
                ]
            );
        } else {
            $args = array_merge(
                $base_args,
                [
                    'page'        => 'estate-office-searches',
                    'wizard_step' => 'search',
                ]
            );
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Podgląd umowy.
     *
     * @param array<string,mixed> $contract Dane.
     *
     * @return void
     */
    private function render_profile( array $contract ) : void {
        $clients    = $this->repository->get_clients( (int) $contract['id'] );
        $properties = $this->repository->get_properties( (int) $contract['id'] );
        $searches   = $this->repository->get_searches( (int) $contract['id'] );
        $stages     = $this->repository->get_stages( (int) $contract['id'] );

        $this->prime_agent_labels( [ (int) ( $contract['agent_id'] ?? 0 ) ] );

        echo '<div class="wrap estate-office-contract-profile">';
        echo '<h1>' . esc_html__( 'Umowa', 'estate-office' ) . ': ' . esc_html( $contract['contract_number'] ) . '</h1>';

        echo '<div style="display:flex;flex-wrap:wrap;gap:2rem;">';
        echo '<section style="flex:1 1 320px;min-width:280px;">';
        echo '<h2>' . esc_html__( 'Dane umowy', 'estate-office' ) . '</h2>';
        echo '<table class="widefat fixed striped">';
        $rows = [
            __( 'Numer', 'estate-office' )          => $contract['contract_number'],
            __( 'Typ transakcji', 'estate-office' ) => $this->get_transaction_types()[ $contract['transaction_type'] ] ?? $contract['transaction_type'],
            __( 'Status', 'estate-office' )         => $this->get_statuses()[ $contract['status'] ] ?? $contract['status'],
            __( 'Data zawarcia', 'estate-office' )  => $contract['start_date'],
            __( 'Data zakończenia', 'estate-office' ) => $contract['end_date'] ?: __( 'Bezterminowa', 'estate-office' ),
            __( 'Prowizja', 'estate-office' )       => $contract['commission_amount'] ? $contract['commission_amount'] . ' ' . $contract['commission_unit'] : '—',
            __( 'Opiekun', 'estate-office' )        => $this->format_agent_cell( (int) ( $contract['agent_id'] ?? 0 ) ),
        ];

        $custom_values = [];
        if ( isset( $contract['custom_fields'] ) && '' !== $contract['custom_fields'] ) {
            $decoded = json_decode( (string) $contract['custom_fields'], true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $custom_key => $custom_value ) {
                    if ( is_scalar( $custom_value ) ) {
                        $custom_values[ (string) $custom_key ] = (string) $custom_value;
                    }
                }
            }
        }

        foreach ( Estate_Office_Dynamic_Fields::format_for_display( 'contract', $custom_values ) as $label => $value ) {
            $rows[ $label ] = $value;
        }
        foreach ( $rows as $label => $value ) {
            echo '<tr><th style="width:35%;">' . esc_html( $label ) . '</th><td>' . wp_kses_post( (string) $value ) . '</td></tr>';
        }
        echo '</table>';
        echo '</section>';

        echo '<section style="flex:1 1 320px;min-width:280px;">';
        echo '<h2>' . esc_html__( 'Bieżący etap', 'estate-office' ) . '</h2>';
        echo '<p><strong>' . esc_html( $this->get_stage_label( $contract['current_stage'] ) ) . '</strong><br />' . esc_html__( 'Data', 'estate-office' ) . ': ' . esc_html( $contract['current_stage_date'] ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-contract-stage-form">';
        wp_nonce_field( 'estate-office-contract-update-stage' );
        echo '<input type="hidden" name="action" value="estate_office_contract_update_stage" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract['id'] ) . '" />';
        echo '<p><label>' . esc_html__( 'Zmień etap', 'estate-office' ) . '<br /><select name="stage">';
        foreach ( $this->get_stages() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $contract['current_stage'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__( 'Data etapu', 'estate-office' ) . '<br /><input type="date" name="stage_date" value="' . esc_attr( gmdate( 'Y-m-d' ) ) . '" required /></label></p>';
        echo '<p><label>' . esc_html__( 'Notatki', 'estate-office' ) . '<br />';
        echo '<textarea name="stage_notes" rows="3" class="large-text"></textarea></label></p>';
        submit_button( __( 'Aktualizuj etap', 'estate-office' ) );
        echo '</form>';

        echo '<h3>' . esc_html__( 'Historia etapów', 'estate-office' ) . '</h3>';
        if ( empty( $stages ) ) {
            echo '<p>' . esc_html__( 'Brak historii etapów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul>';
            foreach ( $stages as $stage ) {
                echo '<li><strong>' . esc_html( $this->get_stage_label( $stage['stage'] ) ) . '</strong> – ' . esc_html( $stage['stage_date'] );
                if ( ! empty( $stage['notes'] ) ) {
                    echo '<br /><span class="description">' . wp_kses_post( wpautop( $stage['notes'] ) ) . '</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';
        echo '</div>';

        echo '<hr />';
        echo '<h2>' . esc_html__( 'Klienci powiązani', 'estate-office' ) . '</h2>';
        if ( empty( $clients ) ) {
            echo '<p>' . esc_html__( 'Brak przypisanych klientów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped"><thead><tr><th>' . esc_html__( 'Klient', 'estate-office' ) . '</th><th>' . esc_html__( 'Rola', 'estate-office' ) . '</th><th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th></tr></thead><tbody>';
            foreach ( $clients as $client ) {
                $name = 'person' === $client['client_type'] ? trim( $client['first_name'] . ' ' . $client['last_name'] ) : $client['company_name'];
                $link = add_query_arg(
                    [
                        'page'      => 'estate-office-clients',
                        'action'    => 'view',
                        'client_id' => $client['id'],
                    ],
                    admin_url( 'admin.php' )
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></td>';
                echo '<td>' . esc_html( $client['role'] ?: __( 'Klient', 'estate-office' ) ) . '</td>';
                echo '<td><a href="' . esc_url( $link ) . '">' . esc_html__( 'Przejdź do profilu klienta', 'estate-office' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h2>' . esc_html__( 'Powiązane nieruchomości', 'estate-office' ) . '</h2>';
        if ( empty( $properties ) ) {
            echo '<p>' . esc_html__( 'Brak nieruchomości przypisanych do tej umowy.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul>';
            foreach ( $properties as $property ) {
                $link = add_query_arg(
                    [
                        'page'        => 'estate-office-properties',
                        'action'      => 'edit',
                        'property_id' => $property['id'],
                    ],
                    admin_url( 'admin.php' )
                );
                echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $property['listing_number'] ?: '#' . $property['id'] ) . '</a> – ' . esc_html( $property['title'] ?: $property['city'] ) . '</li>';
            }
            echo '</ul>';
        }

        echo '<h2>' . esc_html__( 'Powiązane poszukiwania', 'estate-office' ) . '</h2>';
        if ( empty( $searches ) ) {
            echo '<p>' . esc_html__( 'Brak poszukiwań przypisanych do tej umowy.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul>';
            foreach ( $searches as $search ) {
                $link = add_query_arg(
                    [
                        'page'      => 'estate-office-searches',
                        'action'    => 'edit',
                        'search_id' => $search['id'],
                    ],
                    admin_url( 'admin.php' )
                );
                echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $search['search_number'] ) . '</a> – ' . esc_html( $search['transaction_type'] ) . '</li>';
            }
            echo '</ul>';
        }

        echo '<p><a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) ) ) . '" class="button">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a> ';
        echo '<a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'edit', 'contract_id' => $contract['id'] ], admin_url( 'admin.php' ) ) ) . '" class="button button-primary">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a></p>';
        echo '</div>';
    }

    /**
     * Obsługuje zapis umowy.
     *
     * @return void
     */
    public function handle_save_contract() : void {
        if ( ! current_user_can( 'edit_estate_office_contracts' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu umowy.', 'estate-office' ) );
        }

        check_admin_referer( 'estate-office-save-contract' );

        $contract_id     = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $contract_number = sanitize_text_field( wp_unslash( $_POST['contract_number'] ?? '' ) );
        $redirect_to     = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
        $is_portal       = ! empty( $redirect_to );
        $agent_input     = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $agent_id        = $this->sanitize_agent_selection( $agent_input );

        if ( empty( $contract_number ) ) {
            $this->redirect_with_message( __( 'Numer umowy jest wymagany.', 'estate-office' ), 'error', $contract_id, $redirect_to );
        }

        if ( ! $contract_id && $this->repository->exists_by_number( $contract_number ) ) {
            $this->redirect_with_message( __( 'Umowa o podanym numerze już istnieje.', 'estate-office' ), 'error', $contract_id, $redirect_to );
        }

        if ( $contract_id ) {
            $existing = $this->repository->find_by_number( $contract_number );
            if ( $existing && (int) $existing['id'] !== $contract_id ) {
                $this->redirect_with_message( __( 'Umowa o podanym numerze już istnieje.', 'estate-office' ), 'error', $contract_id, $redirect_to );
            }
        }

        $is_open_ended = isset( $_POST['is_open_ended'] ) ? 1 : 0;
        $end_date      = $is_open_ended ? '' : sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

        $commission_raw = isset( $_POST['commission_amount'] ) ? str_replace( ',', '.', (string) wp_unslash( $_POST['commission_amount'] ) ) : '';

        $data = [
            'contract_number'   => $contract_number,
            'transaction_type'  => sanitize_key( wp_unslash( $_POST['transaction_type'] ?? 'sprzedaz' ) ),
            'start_date'        => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? gmdate( 'Y-m-d' ) ) ),
            'end_date'          => $end_date,
            'is_open_ended'     => $is_open_ended,
            'commission_amount' => $commission_raw,
            'commission_unit'   => sanitize_text_field( wp_unslash( $_POST['commission_unit'] ?? '%' ) ),
            'status'            => sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) ),
            'agent_id'          => $agent_id,
        ];

        $custom_fields_input = [];
        if ( isset( $_POST['custom_fields'] ) && is_array( $_POST['custom_fields'] ) ) {
            foreach ( $_POST['custom_fields'] as $custom_key => $custom_value ) {
                if ( is_scalar( $custom_value ) ) {
                    $custom_fields_input[ (string) $custom_key ] = (string) wp_unslash( $custom_value );
                }
            }
        }
        $data['custom_fields'] = Estate_Office_Dynamic_Fields::sanitize_values( 'contract', $custom_fields_input );

        if ( $contract_id ) {
            $this->repository->update( $contract_id, $data );

            if ( $is_portal && $redirect_to ) {
                $redirect = wp_validate_redirect( $redirect_to, $this->get_contracts_list_url() );
                $redirect = add_query_arg(
                    [
                        'contract_id'           => $contract_id,
                        'estate-office-message' => __( 'Umowa została zaktualizowana.', 'estate-office' ),
                        'estate-office-status'  => 'updated',
                    ],
                    $redirect
                );
            } else {
                $redirect = add_query_arg(
                    [
                        'page'                  => self::PAGE_SLUG,
                        'action'                => 'view',
                        'contract_id'           => $contract_id,
                        'estate-office-message' => __( 'Umowa została zaktualizowana.', 'estate-office' ),
                        'estate-office-status'  => 'updated',
                    ],
                    admin_url( 'admin.php' )
                );
            }
        } else {
            $contract_id = $this->repository->create( $data );

            if ( $contract_id <= 0 ) {
                $this->redirect_with_message( __( 'Nie udało się utworzyć umowy.', 'estate-office' ), 'error', 0, $redirect_to );
            }

            if ( $is_portal && $redirect_to ) {
                $redirect = wp_validate_redirect( $redirect_to, $this->get_contracts_list_url() );
                $redirect = add_query_arg(
                    [
                        'contract_id'           => $contract_id,
                        'estate-office-message' => __( 'Umowa została utworzona. Dodaj klientów do umowy.', 'estate-office' ),
                        'estate-office-status'  => 'updated',
                    ],
                    $redirect
                );
            } else {
                $redirect = add_query_arg(
                    [
                        'page'        => self::PAGE_SLUG,
                        'action'      => 'manage-clients',
                        'contract_id' => $contract_id,
                        'estate-office-message' => __( 'Umowa została utworzona. Dodaj klientów do umowy.', 'estate-office' ),
                        'estate-office-status'  => 'updated',
                    ],
                    admin_url( 'admin.php' )
                );
            }
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Dodaje klienta do umowy.
     *
     * @return void
     */
    public function handle_add_client() : void {
        if ( ! current_user_can( 'edit_estate_office_contracts' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do modyfikacji umowy.', 'estate-office' ) );
        }

        check_admin_referer( 'estate-office-contract-add-client' );

        $contract_id = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        if ( ! $contract_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            exit;
        }

        $role        = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
        $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
        $is_portal   = ! empty( $redirect_to );

        $attached   = false;
        $client_id  = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

        if ( isset( $_POST['create_new_client'] ) ) {
            $payload = Estate_Office_Client_Request_Helper::sanitize_from_array( $_POST );

            $payload['agent_id'] = 0;
            $contract            = $this->repository->find( $contract_id );
            if ( $contract ) {
                $contract_agent_id = (int) ( $contract['agent_id'] ?? 0 );
                if ( $contract_agent_id > 0 ) {
                    $can_assign_contract_agent = $this->can_assign_all_agents() || $contract_agent_id === $this->get_current_user_agent_id();
                    if ( $can_assign_contract_agent && $this->agent_repository->exists( $contract_agent_id ) ) {
                        $payload['agent_id'] = $contract_agent_id;
                    }
                }
            }

            if ( $payload['agent_id'] <= 0 ) {
                $current_agent = $this->get_current_user_agent_id();
                if ( $current_agent > 0 && $this->agent_repository->exists( $current_agent ) ) {
                    $payload['agent_id'] = $current_agent;
                }
            }

            $client_id = $this->clients_repository->create( $payload );
        }

        if ( $client_id ) {
            $attached = $this->repository->attach_client( $contract_id, $client_id, $role );
        }

        $message = $attached
            ? __( 'Klient został przypisany do umowy.', 'estate-office' )
            : __( 'Nie udało się przypisać klienta do umowy.', 'estate-office' );
        $status  = $attached ? 'updated' : 'error';

        if ( $is_portal && $redirect_to ) {
            $redirect_url = wp_validate_redirect( $redirect_to, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            $redirect_url = add_query_arg(
                [
                    'contract_id'           => $contract_id,
                    'estate-office-message' => $message,
                    'estate-office-status'  => $status,
                ],
                $redirect_url
            );

            wp_safe_redirect( $redirect_url );
            exit;
        }

        $redirect = add_query_arg(
            [
                'page'                  => self::PAGE_SLUG,
                'action'                => 'manage-clients',
                'contract_id'           => $contract_id,
                'estate-office-message' => $message,
                'estate-office-status'  => $status,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Usuwa klienta z umowy.
     *
     * @return void
     */
    public function handle_remove_client() : void {
        if ( ! current_user_can( 'edit_estate_office_contracts' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do modyfikacji umowy.', 'estate-office' ) );
        }

        $contract_id = isset( $_GET['contract_id'] ) ? absint( $_GET['contract_id'] ) : 0;
        $client_id   = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
        check_admin_referer( 'estate-office-contract-remove-client-' . $contract_id . '-' . $client_id );

        if ( $contract_id && $client_id ) {
            $this->repository->detach_client( $contract_id, $client_id );
        }

        if ( $redirect_to ) {
            $redirect = wp_validate_redirect( $redirect_to, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            $redirect = add_query_arg(
                [
                    'contract_id'           => $contract_id,
                    'estate-office-message' => __( 'Klient został usunięty z umowy.', 'estate-office' ),
                    'estate-office-status'  => 'updated',
                ],
                $redirect
            );
        } else {
            $redirect = add_query_arg(
                [
                    'page'        => self::PAGE_SLUG,
                    'action'      => 'manage-clients',
                    'contract_id' => $contract_id,
                    'estate-office-message' => __( 'Klient został usunięty z umowy.', 'estate-office' ),
                    'estate-office-status'  => 'updated',
                ],
                admin_url( 'admin.php' )
            );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Aktualizuje etap umowy.
     *
     * @return void
     */
    public function handle_update_stage() : void {
        if ( ! current_user_can( 'edit_estate_office_contracts' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do aktualizacji etapu.', 'estate-office' ) );
        }

        check_admin_referer( 'estate-office-contract-update-stage' );

        $contract_id = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $stage       = isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : 'umowa_posrednictwa';
        $stage_date  = sanitize_text_field( wp_unslash( $_POST['stage_date'] ?? gmdate( 'Y-m-d' ) ) );
        $notes       = wp_kses_post( wp_unslash( $_POST['stage_notes'] ?? '' ) );

        if ( ! array_key_exists( $stage, $this->get_stages() ) ) {
            $stage = 'umowa_posrednictwa';
        }

        $this->repository->update_current_stage( $contract_id, $stage, $stage_date, $notes );
        $this->repository->add_stage( $contract_id, $stage, $stage_date, get_current_user_id(), $notes );

        $redirect = add_query_arg(
            [
                'page'                  => self::PAGE_SLUG,
                'action'                => 'view',
                'contract_id'           => $contract_id,
                'estate-office-message' => __( 'Etap umowy został zaktualizowany.', 'estate-office' ),
                'estate-office-status'  => 'updated',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Usuwa umowę.
     *
     * @return void
     */
    public function handle_delete_contract() : void {
        if ( ! current_user_can( 'delete_estate_office_contracts' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do usunięcia umowy.', 'estate-office' ) );
        }

        $contract_id = isset( $_GET['contract_id'] ) ? absint( $_GET['contract_id'] ) : 0;
        check_admin_referer( 'estate-office-delete-contract-' . $contract_id );

        if ( $contract_id ) {
            $this->repository->delete( $contract_id );
        }

        $redirect = add_query_arg(
            [
                'page'                  => self::PAGE_SLUG,
                'estate-office-message' => __( 'Umowa została usunięta.', 'estate-office' ),
                'estate-office-status'  => 'updated',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Pomocnicza metoda przekierowania z komunikatem.
     *
     * @param string $message     Treść.
     * @param string $status      Status.
     * @param int    $contract_id ID umowy.
     *
     * @return void
     */
    private function redirect_with_message( string $message, string $status, int $contract_id = 0, string $redirect_to = '' ) : void {
        if ( ! empty( $redirect_to ) ) {
            $redirect = wp_validate_redirect( $redirect_to, $this->get_contracts_list_url() );
            $redirect = add_query_arg(
                [
                    'estate-office-message' => $message,
                    'estate-office-status'  => $status,
                ],
                $redirect
            );

            if ( $contract_id ) {
                $redirect = add_query_arg( 'contract_id', $contract_id, $redirect );
            }

            wp_safe_redirect( $redirect );
            exit;
        }

        $args = [
            'page'                  => self::PAGE_SLUG,
            'estate-office-message' => $message,
            'estate-office-status'  => $status,
        ];

        if ( $contract_id ) {
            $args['action']      = 'edit';
            $args['contract_id'] = $contract_id;
        } else {
            $args['action'] = 'new';
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Dostępne typy transakcji.
     *
     * @return array<string,string>
     */
    private function get_transaction_types() : array {
        return [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Statusy umów.
     *
     * @return array<string,string>
     */
    private function get_statuses() : array {
        return [
            'draft'   => __( 'Szkic', 'estate-office' ),
            'active'  => __( 'Aktywna', 'estate-office' ),
            'closed'  => __( 'Zakończona', 'estate-office' ),
            'paused'  => __( 'Wstrzymana', 'estate-office' ),
        ];
    }

    /**
     * Lista etapów.
     *
     * @return array<string,string>
     */
    private function get_stages() : array {
        return [
            'umowa_posrednictwa' => __( 'Umowa Pośrednictwa', 'estate-office' ),
            'publikacja_mls'     => __( 'Publikacja w MLS', 'estate-office' ),
            'przygotowanie'      => __( 'Przygotowanie oferty', 'estate-office' ),
            'publikacja'         => __( 'Publikacja oferty', 'estate-office' ),
            'marketing'          => __( 'Marketing i prezentacje', 'estate-office' ),
            'oferta_kupna'       => __( 'Oferta kupna', 'estate-office' ),
            'negocjacje'         => __( 'Negocjacje', 'estate-office' ),
            'umowa_przedwstepna' => __( 'Umowa przedwstępna', 'estate-office' ),
            'umowa_przyrzeczona' => __( 'Umowa przyrzeczona', 'estate-office' ),
            'przekazanie_lokalu' => __( 'Przekazanie lokalu', 'estate-office' ),
            'umowa_zakonczona'   => __( 'Umowa zakończona', 'estate-office' ),
        ];
    }

    /**
     * Zwraca etykietę etapu.
     *
     * @param string $stage Klucz.
     *
     * @return string
     */
    private function get_stage_label( string $stage ) : string {
        $stages = $this->get_stages();

        return $stages[ $stage ] ?? $stage;
    }
}
