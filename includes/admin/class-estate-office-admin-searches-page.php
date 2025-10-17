<?php
/**
 * Ekran administracyjny zarządzania poszukiwaniami klientów.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/admin/class-estate-office-admin-agent-assignment.php';

/**
 * Class Estate_Office_Admin_Searches_Page
 */
class Estate_Office_Admin_Searches_Page {

    use Estate_Office_Admin_Agent_Assignment;

    private const PAGE_SLUG = 'estate-office-searches';

    /**
     * Repozytorium poszukiwań.
     *
     * @var Estate_Office_Search_Repository
     */
    private Estate_Office_Search_Repository $repository;

    /**
     * Repozytorium umów.
     *
     * @var Estate_Office_Contract_Repository
     */
    private Estate_Office_Contract_Repository $contracts_repository;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Search_Repository|null $repository          Opcjonalne repozytorium.
     * @param Estate_Office_Contract_Repository|null $contracts_repository Repozytorium umów.
     * @param Estate_Office_Agent_Repository|null    $agent_repository     Repozytorium agentów.
     */
    public function __construct( ?Estate_Office_Search_Repository $repository = null, ?Estate_Office_Contract_Repository $contracts_repository = null, ?Estate_Office_Agent_Repository $agent_repository = null ) {
        $this->repository            = $repository ?? new Estate_Office_Search_Repository();
        $this->contracts_repository  = $contracts_repository ?? new Estate_Office_Contract_Repository();
        $this->init_agent_repository( $agent_repository );
    }

    /**
     * Rejestracja hooków.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_search', [ $this, 'handle_save_search' ] );
        add_action( 'admin_post_estate_office_delete_search', [ $this, 'handle_delete_search' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Ładuje zasoby strony.
     *
     * @param string $hook Bieżący hook ekranu.
     *
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        if ( 'estate-office-crm_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        $handle = 'estate-office-admin-searches';
        wp_register_style( $handle, false, [], ESTATE_OFFICE_VERSION );
        wp_enqueue_style( $handle );
        wp_add_inline_style(
            $handle,
            '.estate-office-search-sections h2{margin-top:2em}.estate-office-search-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem}.estate-office-search-grid .field{display:flex;flex-direction:column}.estate-office-search-grid .field label{font-weight:600;margin-bottom:4px}.estate-office-flags{display:flex;flex-wrap:wrap;gap:1rem;margin:1.5rem 0}.estate-office-search-actions{margin-top:1.5rem;display:flex;gap:1rem;align-items:center}.estate-office-search-notice{margin-top:1rem}.estate-office-search-grid .field small{color:#666;font-weight:400}.estate-office-checkbox-group{display:flex;flex-direction:column;gap:.5rem}.estate-office-checkbox-group label{display:flex;align-items:center;gap:.5rem}.estate-office-search-table .column-actions{width:160px;text-align:right}
'
        );

        wp_register_script( $handle, false, [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $handle );
        wp_add_inline_script(
            $handle,
            <<<'JS'
jQuery(function($){
    $('.estate-office-search-generate').on('click', function(event){
        event.preventDefault();
        const generated = $(this).data('generated');
        if ( generated ) {
            $('input[name="search_number"]').val(generated);
        }
    });
});
JS
        );
    }

    /**
     * Renderuje stronę modułu.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_searches' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do przeglądania poszukiwań.', 'estate-office' ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $id     = isset( $_GET['search_id'] ) ? absint( $_GET['search_id'] ) : 0;

        if ( 'new' === $action ) {
            $this->render_form();

            return;
        }

        if ( 'edit' === $action && $id ) {
            $search = $this->repository->find( $id );
            if ( null === $search ) {
                $this->render_list( __( 'Nie znaleziono wskazanego poszukiwania.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_form( $search );

            return;
        }

        $message = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $status  = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'success';

        $this->render_list( $message ?: null, $status ?: 'success' );
    }

    /**
     * Renderuje listę poszukiwań.
     *
     * @param string|null $notice Treść komunikatu.
     * @param string      $status Typ komunikatu.
     *
     * @return void
     */
    private function render_list( ?string $notice, string $status ) : void {
        $search           = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $transaction_type = isset( $_GET['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_type'] ) ) : '';
        $property_type    = isset( $_GET['property_type'] ) ? sanitize_text_field( wp_unslash( $_GET['property_type'] ) ) : '';
        $paged            = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $query = $this->repository->paginate(
            [
                'search'           => $search,
                'transaction_type' => $transaction_type,
                'property_type'    => $property_type,
                'paged'            => $paged,
            ]
        );

        $agent_ids = [];
        if ( isset( $query['items'] ) && is_array( $query['items'] ) ) {
            $agent_ids = array_map( 'intval', wp_list_pluck( $query['items'], 'agent_id' ) );
        }
        $this->prime_agent_labels( $agent_ids );

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Poszukiwania klientów', 'estate-office' ) . '</h1>';
        printf(
            ' <a href="%s" class="page-title-action">%s</a>',
            esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=new' ) ),
            esc_html__( 'Dodaj nowe poszukiwanie', 'estate-office' )
        );

        if ( $notice ) {
            printf( '<div class="notice notice-%1$s is-dismissible estate-office-search-notice"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $notice ) );
        }

        echo '<form method="get" class="estate-office-search-filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        printf(
            '<input type="search" name="s" value="%s" placeholder="%s" />',
            esc_attr( $search ),
            esc_attr__( 'Szukaj numeru, lokalizacji lub słów kluczowych', 'estate-office' )
        );

        echo '<select name="transaction_type">';
        echo '<option value="">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</option>';
        foreach ( $this->get_transaction_types() as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $transaction_type, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        echo '<select name="property_type">';
        echo '<option value="">' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</option>';
        foreach ( $this->get_property_types() as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $property_type, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        submit_button( __( 'Filtruj', 'estate-office' ), 'secondary', '', false );
        echo '</div>';
        echo '<div class="tablenav-pages">';

        $pagination = paginate_links(
            [
                'base'      => add_query_arg( [ 'paged' => '%#%' ] ),
                'format'    => '',
                'prev_text' => __( '&laquo;', 'estate-office' ),
                'next_text' => __( '&raquo;', 'estate-office' ),
                'total'     => max( 1, (int) $query['total_page'] ),
                'current'   => max( 1, $paged ),
            ]
        );

        if ( $pagination ) {
            echo wp_kses_post( $pagination );
        }

        echo '</div>';
        echo '</div>';
        echo '</form>';

        echo '<table class="widefat fixed striped estate-office-search-table">';
        echo '<thead><tr>';
        $columns = [
            'search_number'    => __( 'Numer poszukiwania', 'estate-office' ),
            'property_type'    => __( 'Rodzaj nieruchomości', 'estate-office' ),
            'budget'           => __( 'Budżet', 'estate-office' ),
            'location'         => __( 'Lokalizacja', 'estate-office' ),
            'transaction_type' => __( 'Typ transakcji', 'estate-office' ),
            'agent'            => __( 'Opiekun', 'estate-office' ),
            'updated'          => __( 'Aktualizacja', 'estate-office' ),
            'actions'          => __( 'Akcje', 'estate-office' ),
        ];

        foreach ( $columns as $key => $label ) {
            printf( '<th scope="col" class="column-%1$s">%2$s</th>', esc_attr( $key ), esc_html( $label ) );
        }
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="8">' . esc_html__( 'Brak poszukiwań spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $budget = $this->format_budget( $item['price_min'] ?? null, $item['price_max'] ?? null );
                $location_parts = array_filter(
                    [
                        $item['location_city'] ?? '',
                        $item['location_district'] ?? '',
                        $item['location_voivodeship'] ?? '',
                    ]
                );
                $location = ! empty( $location_parts ) ? implode( ', ', $location_parts ) : ( $item['location_keywords'] ?? '' );
                $updated  = $item['updated_at'] ?: $item['created_at'];

                echo '<tr>';
                printf(
                    '<td><a href="%s">%s</a></td>',
                    esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=edit&search_id=' . absint( $item['id'] ) ) ),
                    esc_html( $item['search_number'] )
                );
                printf( '<td>%s</td>', esc_html( $this->get_property_types()[ $item['property_type'] ] ?? $item['property_type'] ) );
                printf( '<td>%s</td>', esc_html( $budget ) );
                printf( '<td>%s</td>', esc_html( $location ?: __( 'Nie określono', 'estate-office' ) ) );
                printf( '<td>%s</td>', esc_html( $this->get_transaction_types()[ $item['transaction_type'] ] ?? $item['transaction_type'] ) );
                printf( '<td>%s</td>', wp_kses_post( $this->format_agent_cell( (int) ( $item['agent_id'] ?? 0 ) ) ) );
                printf( '<td>%s</td>', esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated ) ) );
                echo '<td class="column-actions">';
                printf( '<a href="%s" class="button button-small">%s</a> ', esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=edit&search_id=' . absint( $item['id'] ) ) ), esc_html__( 'Edytuj', 'estate-office' ) );

                if ( current_user_can( 'delete_estate_office_searches' ) ) {
                    $delete_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=estate_office_delete_search&search_id=' . absint( $item['id'] ) ),
                        'estate_office_delete_search_' . absint( $item['id'] )
                    );
                    printf( '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_attr__( 'Czy na pewno chcesz usunąć to poszukiwanie?', 'estate-office' ), esc_html__( 'Usuń', 'estate-office' ) );
                }

                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Renderuje formularz.
     *
     * @param array<string,mixed>|null $search Dane poszukiwania.
     * @param array<int,string>        $errors Błędy walidacji.
     *
     * @return void
     */
    private function render_form( ?array $search = null, array $errors = [] ) : void {
        $data = $this->prepare_search_for_form( $search );

        $wizard_mode        = isset( $_GET['wizard'] ) ? sanitize_key( wp_unslash( $_GET['wizard'] ) ) : '';
        $wizard_step        = isset( $_GET['wizard_step'] ) ? sanitize_key( wp_unslash( $_GET['wizard_step'] ) ) : '';
        $wizard_contract_id = isset( $_GET['wizard_contract_id'] ) ? absint( $_GET['wizard_contract_id'] ) : 0;
        $wizard_active      = ! $data['id'] && 'contract' === $wizard_mode && 'search' === $wizard_step && $wizard_contract_id > 0;

        if ( $wizard_active && 0 === $data['contract_id'] ) {
            $data['contract_id'] = $wizard_contract_id;
        }

        echo '<div class="wrap">';
        $heading = $data['id'] ? __( 'Edytuj poszukiwanie', 'estate-office' ) : __( 'Dodaj nowe poszukiwanie', 'estate-office' );
        if ( $wizard_active ) {
            $heading = __( 'Nowa umowa – etap 3/3: Dodaj poszukiwanie', 'estate-office' );
        }

        echo '<h1>' . esc_html( $heading ) . '</h1>';
        if ( $wizard_active ) {
            echo '<p class="description">' . esc_html__( 'Określ kryteria poszukiwania, aby zakończyć proces tworzenia umowy.', 'estate-office' ) . '</p>';
        }

        if ( ! empty( $errors ) ) {
            echo '<div class="notice notice-error"><ul>';
            foreach ( $errors as $error ) {
                printf( '<li>%s</li>', esc_html( $error ) );
            }
            echo '</ul></div>';
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-search-form">';
        wp_nonce_field( 'estate_office_save_search', 'estate_office_nonce' );
        echo '<input type="hidden" name="action" value="estate_office_save_search" />';
        echo '<input type="hidden" name="search_id" value="' . esc_attr( $data['id'] ) . '" />';
        if ( $wizard_active ) {
            echo '<input type="hidden" name="wizard" value="contract" />';
            echo '<input type="hidden" name="wizard_step" value="search" />';
            echo '<input type="hidden" name="wizard_contract_id" value="' . esc_attr( $wizard_contract_id ) . '" />';
        }

        echo '<div class="estate-office-search-sections">';

        echo '<h2>' . esc_html__( 'Informacje podstawowe', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-search-grid">';
        echo '<div class="field">';
        echo '<label for="search_number">' . esc_html__( 'Numer poszukiwania', 'estate-office' ) . '</label>';
        printf(
            '<input type="text" name="search_number" id="search_number" value="%s" />',
            esc_attr( $data['search_number'] )
        );
        printf(
            '<small><button class="button estate-office-search-generate" data-generated="%s">%s</button> %s</small>',
            esc_attr( $data['generated_number'] ),
            esc_html__( 'Wstaw numer z generatora', 'estate-office' ),
            esc_html__( 'Pozostaw pole puste aby numer został nadany automatycznie.', 'estate-office' )
        );
        echo '</div>';
        $this->render_select_field(
            'contract_id',
            __( 'Powiązana umowa', 'estate-office' ),
            (string) $data['contract_id'],
            $this->get_contract_select_options( (int) $data['contract_id'] )
        );
        $this->render_select_field( 'transaction_type', __( 'Typ transakcji', 'estate-office' ), $data['transaction_type'], $this->get_transaction_types(), true );
        $this->render_select_field( 'property_type', __( 'Rodzaj nieruchomości', 'estate-office' ), $data['property_type'], $this->get_property_types(), true );
        $agent_description = '';
        if ( ! $this->can_assign_all_agents() ) {
            if ( $this->get_current_user_agent_id() > 0 ) {
                $agent_description = __( 'Możesz przypisać jedynie siebie jako opiekuna.', 'estate-office' );
            } else {
                $agent_description = __( 'Twoje konto nie ma przypisanego profilu agenta. Skontaktuj się z administratorem.', 'estate-office' );
            }
        }
        $this->render_select_field(
            'agent_id',
            __( 'Opiekun (agent)', 'estate-office' ),
            (string) $data['agent_id'],
            $this->get_agent_select_options(),
            false,
            $agent_description
        );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Preferowana lokalizacja', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-search-grid">';
        $this->render_text_input( 'location_city', __( 'Miasto', 'estate-office' ), $data['location_city'] );
        $this->render_text_input( 'location_district', __( 'Dzielnica', 'estate-office' ), $data['location_district'] );
        $this->render_text_input( 'location_voivodeship', __( 'Województwo', 'estate-office' ), $data['location_voivodeship'] );
        $this->render_text_input( 'location_keywords', __( 'Słowa kluczowe lokalizacji', 'estate-office' ), $data['location_keywords'] );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Parametry poszukiwania', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-search-grid">';
        $this->render_number_input( 'price_min', __( 'Budżet od (PLN)', 'estate-office' ), $data['price_min'], [ 'step' => '1000' ] );
        $this->render_number_input( 'price_max', __( 'Budżet do (PLN)', 'estate-office' ), $data['price_max'], [ 'step' => '1000' ] );
        $this->render_number_input( 'area_min', __( 'Metraż od (m²)', 'estate-office' ), $data['area_min'], [ 'step' => '1' ] );
        $this->render_number_input( 'area_max', __( 'Metraż do (m²)', 'estate-office' ), $data['area_max'], [ 'step' => '1' ] );
        $this->render_number_input( 'rooms_min', __( 'Liczba pokoi od', 'estate-office' ), $data['rooms_min'], [ 'step' => '1' ] );
        $this->render_number_input( 'rooms_max', __( 'Liczba pokoi do', 'estate-office' ), $data['rooms_max'], [ 'step' => '1' ] );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Opis poszukiwania', 'estate-office' ) . '</h2>';
        wp_editor( $data['description'], 'estate_office_search_description', [
            'textarea_name' => 'description',
            'textarea_rows' => 8,
        ] );

        echo '<h2>' . esc_html__( 'Preferencje nieruchomości', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-search-grid">';
        $this->render_select_field( 'building_finish_state', __( 'Stan wykończenia', 'estate-office' ), $data['criteria']['building']['finish_state'], $this->get_finish_states(), false );
        $this->render_checkboxes_field( 'building_exposure', __( 'Ekspozycja', 'estate-office' ), $data['criteria']['building']['exposure'], $this->get_exposure_options() );
        $this->render_checkboxes_field( 'building_view', __( 'Widok', 'estate-office' ), $data['criteria']['building']['view'], $this->get_view_options() );
        $this->render_checkbox_field( 'building_attic', __( 'Poddasze', 'estate-office' ), $data['criteria']['building']['attic'] );
        $this->render_checkbox_field( 'building_multi_level', __( 'Wielopoziomowe', 'estate-office' ), $data['criteria']['building']['multi_level'] );
        $this->render_checkboxes_field( 'building_layout', __( 'Rozkład', 'estate-office' ), $data['criteria']['building']['layout'], $this->get_layout_options() );
        $this->render_select_field( 'building_kitchen_type', __( 'Rodzaj kuchni', 'estate-office' ), $data['criteria']['building']['kitchen_type'], $this->get_kitchen_types(), false );
        $this->render_checkbox_field( 'parking_available', __( 'Wymagane miejsce parkingowe', 'estate-office' ), $data['criteria']['building']['parking']['available'] );
        $this->render_checkboxes_field( 'parking_types', __( 'Preferowane rodzaje parkingu', 'estate-office' ), $data['criteria']['building']['parking']['types'], $this->get_parking_types() );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Media i wyposażenie', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-search-grid">';
        $this->render_select_field( 'media_heating', __( 'Ogrzewanie', 'estate-office' ), $data['criteria']['media']['heating'], $this->get_heating_types(), false );
        $this->render_select_field( 'media_water', __( 'Woda', 'estate-office' ), $data['criteria']['media']['water'], $this->get_water_types(), false );
        $this->render_select_field( 'media_sewage', __( 'Kanalizacja', 'estate-office' ), $data['criteria']['media']['sewage'], $this->get_sewage_types(), false );
        $this->render_checkbox_field( 'media_gas', __( 'Dostęp do gazu', 'estate-office' ), $data['criteria']['media']['gas'] );
        $this->render_select_field( 'equipment_level', __( 'Poziom umeblowania', 'estate-office' ), $data['criteria']['equipment']['level'], $this->get_equipment_levels(), false );
        $this->render_checkboxes_field( 'equipment_items', __( 'Kluczowe elementy wyposażenia', 'estate-office' ), $data['criteria']['equipment']['items'], $this->get_equipment_items() );
        $this->render_checkboxes_field( 'amenities', __( 'Udogodnienia', 'estate-office' ), $data['criteria']['amenities'], $this->get_amenities_options() );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Powierzchnie dodatkowe', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-search-grid">';
        $this->render_additional_area_fields( 'balcony', __( 'Balkon', 'estate-office' ), $data['criteria']['additional_areas']['balcony'], true );
        $this->render_additional_area_fields( 'terrace', __( 'Taras', 'estate-office' ), $data['criteria']['additional_areas']['terrace'], true );
        $this->render_additional_area_fields( 'cellar', __( 'Piwnica', 'estate-office' ), $data['criteria']['additional_areas']['cellar'], false );
        $this->render_additional_area_fields( 'storage', __( 'Komórka lokatorska', 'estate-office' ), $data['criteria']['additional_areas']['storage'], false );
        $this->render_additional_area_fields( 'garden', __( 'Ogródek', 'estate-office' ), $data['criteria']['additional_areas']['garden'], false );
        echo '</div>';

        echo '</div>';

        echo '<div class="estate-office-search-actions">';
        submit_button( $data['id'] ? __( 'Zapisz poszukiwanie', 'estate-office' ) : __( 'Dodaj poszukiwanie', 'estate-office' ), 'primary', 'submit', false );

        $back_url   = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        $back_label = __( 'Powrót do listy', 'estate-office' );

        if ( $wizard_active ) {
            $back_url   = add_query_arg(
                [
                    'page'        => 'estate-office-contracts',
                    'action'      => 'manage-clients',
                    'contract_id' => $wizard_contract_id,
                ],
                admin_url( 'admin.php' )
            );
            $back_label = __( 'Powrót do etapu 2 – klienci', 'estate-office' );
        }

        printf( '<a href="%s" class="button button-secondary">%s</a>', esc_url( $back_url ), esc_html( $back_label ) );
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Obsługuje zapis poszukiwania.
     *
     * @return void
     */
    public function handle_save_search() : void {
        if ( ! current_user_can( 'edit_estate_office_searches' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu poszukiwań.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_search', 'estate_office_nonce' );

        $search_id = isset( $_POST['search_id'] ) ? absint( $_POST['search_id'] ) : 0;
        $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
        $data      = $this->collect_search_input();

        if ( $data['contract_id'] > 0 && $data['agent_id'] <= 0 ) {
            $contract = $this->contracts_repository->find( (int) $data['contract_id'] );
            if ( $contract ) {
                $contract_agent_id = (int) ( $contract['agent_id'] ?? 0 );
                if ( $contract_agent_id > 0 ) {
                    if ( $this->can_assign_all_agents() || $contract_agent_id === $this->get_current_user_agent_id() ) {
                        if ( $this->agent_repository->exists( $contract_agent_id ) ) {
                            $data['agent_id'] = $contract_agent_id;
                        }
                    }
                }
            }
        }

        $errors = [];
        if ( empty( $data['transaction_type'] ) ) {
            $errors[] = __( 'Wybierz typ transakcji.', 'estate-office' );
        }
        if ( empty( $data['property_type'] ) ) {
            $errors[] = __( 'Wybierz rodzaj nieruchomości.', 'estate-office' );
        }

        if ( ! empty( $errors ) ) {
            $this->redirect_with_message( $search_id, implode( ' ', $errors ), 'error', $redirect_to );
        }

        if ( empty( $data['search_number'] ) ) {
            $data['search_number'] = $this->repository->generate_search_number();
        } else {
            $existing = $this->repository->find_by_search_number( $data['search_number'] );
            if ( $existing && (int) $existing['id'] !== $search_id ) {
                $this->redirect_with_message( $search_id, __( 'Podany numer poszukiwania jest już w użyciu.', 'estate-office' ), 'error', $redirect_to );
            }
        }

        if ( $search_id ) {
            $result = $this->repository->update( $search_id, $data );
            $this->redirect_with_message(
                $search_id,
                $result ? __( 'Poszukiwanie zostało zaktualizowane.', 'estate-office' ) : __( 'Nie udało się zapisać zmian.', 'estate-office' ),
                $result ? 'success' : 'error',
                $redirect_to
            );
        }

        $new_id = $this->repository->create( $data );
        if ( $new_id ) {
            $this->redirect_with_message( (int) $new_id, __( 'Dodano nowe poszukiwanie.', 'estate-office' ), 'success', $redirect_to );
        }

        $this->redirect_with_message( 0, __( 'Nie udało się dodać poszukiwania.', 'estate-office' ), 'error', $redirect_to );
    }

    /**
     * Obsługuje usuwanie poszukiwania.
     *
     * @return void
     */
    public function handle_delete_search() : void {
        if ( ! current_user_can( 'delete_estate_office_searches' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do usuwania poszukiwań.', 'estate-office' ) );
        }

        $search_id = isset( $_GET['search_id'] ) ? absint( $_GET['search_id'] ) : 0;
        check_admin_referer( 'estate_office_delete_search_' . $search_id );

        if ( ! $search_id ) {
            wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            exit;
        }

        $deleted = $this->repository->delete( $search_id );
        $message = $deleted ? __( 'Poszukiwanie zostało usunięte.', 'estate-office' ) : __( 'Nie udało się usunąć poszukiwania.', 'estate-office' );
        $status  = $deleted ? 'success' : 'error';

        $this->redirect_with_message( 0, $message, $status );
    }

    /**
     * Przygotowuje dane formularza.
     *
     * @param array<string,mixed>|null $search Dane.
     *
     * @return array<string,mixed>
     */
    private function prepare_search_for_form( ?array $search ) : array {
        $defaults = [
            'id'               => 0,
            'search_number'    => '',
            'contract_id'      => 0,
            'generated_number' => $this->repository->generate_search_number(),
            'transaction_type' => 'SPRZEDAŻ',
            'property_type'    => 'MIESZKANIE',
            'location_city'    => '',
            'location_district'=> '',
            'location_voivodeship' => '',
            'location_keywords'=> '',
            'price_min'        => '',
            'price_max'        => '',
            'area_min'         => '',
            'area_max'         => '',
            'rooms_min'        => '',
            'rooms_max'        => '',
            'description'      => '',
            'agent_id'         => 0,
            'criteria'         => [
                'building' => [
                    'finish_state' => '',
                    'exposure'     => [],
                    'view'         => [],
                    'attic'        => false,
                    'multi_level'  => false,
                    'layout'       => [],
                    'kitchen_type' => '',
                    'parking'      => [
                        'available' => false,
                        'types'     => [],
                    ],
                ],
                'media' => [
                    'heating' => '',
                    'water'   => '',
                    'sewage'  => '',
                    'gas'     => false,
                ],
                'amenities' => [],
                'equipment' => [
                    'level' => '',
                    'items' => [],
                ],
                'additional_areas' => [
                    'balcony' => [ 'enabled' => false, 'count' => '', 'area' => '' ],
                    'terrace' => [ 'enabled' => false, 'count' => '', 'area' => '' ],
                    'cellar'  => [ 'enabled' => false, 'area' => '' ],
                    'storage' => [ 'enabled' => false, 'area' => '' ],
                    'garden'  => [ 'enabled' => false, 'area' => '' ],
                ],
            ],
        ];

        if ( null === $search ) {
            if ( isset( $_GET['contract_id'] ) ) {
                $defaults['contract_id'] = absint( $_GET['contract_id'] );
            }

            if ( $defaults['contract_id'] > 0 ) {
                $contract = $this->contracts_repository->find( $defaults['contract_id'] );
                if ( $contract && ! empty( $contract['agent_id'] ) ) {
                    $defaults['agent_id'] = (int) $contract['agent_id'];
                }
            }

            if ( $defaults['agent_id'] <= 0 ) {
                $defaults['agent_id'] = $this->get_current_user_agent_id();
            }

            if ( $defaults['agent_id'] > 0 ) {
                $this->prime_agent_labels( [ $defaults['agent_id'] ] );
            }

            return $defaults;
        }

        foreach ( $search as $key => $value ) {
            if ( array_key_exists( $key, $defaults ) && 'criteria' !== $key ) {
                if ( in_array( $key, [ 'price_min', 'price_max', 'area_min', 'area_max', 'rooms_min', 'rooms_max' ], true ) ) {
                    $defaults[ $key ] = '' !== $value && null !== $value ? (string) $value : '';
                } else {
                    $defaults[ $key ] = $value;
                }
            }
        }

        if ( isset( $search['criteria'] ) && is_array( $search['criteria'] ) ) {
            $defaults['criteria'] = wp_parse_args( $search['criteria'], $defaults['criteria'] );
        }

        $defaults['generated_number'] = $this->repository->generate_search_number();
        $defaults['contract_id']      = absint( $defaults['contract_id'] );
        $defaults['agent_id']         = absint( $defaults['agent_id'] );

        if ( $defaults['agent_id'] > 0 ) {
            $this->prime_agent_labels( [ $defaults['agent_id'] ] );
        }

        return $defaults;
    }

    /**
     * Zbiera dane z formularza.
     *
     * @return array<string,mixed>
     */
    private function collect_search_input() : array {
        $data = [];

        $data['search_number']    = sanitize_text_field( wp_unslash( $_POST['search_number'] ?? '' ) );
        $data['contract_id']      = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $agent_input              = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $data['agent_id']         = $this->sanitize_agent_selection( $agent_input );
        $data['transaction_type'] = $this->sanitize_choice( $_POST['transaction_type'] ?? '', array_keys( $this->get_transaction_types() ) );
        $data['property_type']    = $this->sanitize_choice( $_POST['property_type'] ?? '', array_keys( $this->get_property_types() ) );
        $data['location_city']    = sanitize_text_field( wp_unslash( $_POST['location_city'] ?? '' ) );
        $data['location_district']= sanitize_text_field( wp_unslash( $_POST['location_district'] ?? '' ) );
        $data['location_voivodeship'] = sanitize_text_field( wp_unslash( $_POST['location_voivodeship'] ?? '' ) );
        $data['location_keywords']= sanitize_text_field( wp_unslash( $_POST['location_keywords'] ?? '' ) );
        $data['price_min']        = $this->get_request_float( 'price_min' );
        $data['price_max']        = $this->get_request_float( 'price_max' );
        $data['area_min']         = $this->get_request_float( 'area_min' );
        $data['area_max']         = $this->get_request_float( 'area_max' );
        $data['rooms_min']        = $this->get_request_int( 'rooms_min' );
        $data['rooms_max']        = $this->get_request_int( 'rooms_max' );
        $data['description']      = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );

        $building = [];
        $building['finish_state'] = $this->sanitize_choice( $_POST['building_finish_state'] ?? '', array_keys( $this->get_finish_states() ) );
        $building['exposure']     = $this->sanitize_array_choice( $_POST['building_exposure'] ?? [], array_keys( $this->get_exposure_options() ) );
        $building['view']         = $this->sanitize_array_choice( $_POST['building_view'] ?? [], array_keys( $this->get_view_options() ) );
        $building['attic']        = ! empty( $_POST['building_attic'] );
        $building['multi_level']  = ! empty( $_POST['building_multi_level'] );
        $building['layout']       = $this->sanitize_array_choice( $_POST['building_layout'] ?? [], array_keys( $this->get_layout_options() ) );
        $building['kitchen_type'] = $this->sanitize_choice( $_POST['building_kitchen_type'] ?? '', array_keys( $this->get_kitchen_types() ) );
        $building['parking']      = [
            'available' => ! empty( $_POST['parking_available'] ),
            'types'     => $this->sanitize_array_choice( $_POST['parking_types'] ?? [], array_keys( $this->get_parking_types() ) ),
        ];

        $media = [];
        $media['heating'] = $this->sanitize_choice( $_POST['media_heating'] ?? '', array_keys( $this->get_heating_types() ) );
        $media['water']   = $this->sanitize_choice( $_POST['media_water'] ?? '', array_keys( $this->get_water_types() ) );
        $media['sewage']  = $this->sanitize_choice( $_POST['media_sewage'] ?? '', array_keys( $this->get_sewage_types() ) );
        $media['gas']     = ! empty( $_POST['media_gas'] );

        $equipment = [];
        $equipment['level'] = $this->sanitize_choice( $_POST['equipment_level'] ?? '', array_keys( $this->get_equipment_levels() ) );
        $equipment['items'] = $this->sanitize_array_choice( $_POST['equipment_items'] ?? [], array_keys( $this->get_equipment_items() ) );

        $data['criteria'] = [
            'building'          => $building,
            'media'             => $media,
            'amenities'         => $this->sanitize_array_choice( $_POST['amenities'] ?? [], array_keys( $this->get_amenities_options() ) ),
            'equipment'         => $equipment,
            'additional_areas'  => [
                'balcony' => $this->collect_area_data( 'balcony' ),
                'terrace' => $this->collect_area_data( 'terrace' ),
                'cellar'  => $this->collect_area_data( 'cellar', false ),
                'storage' => $this->collect_area_data( 'storage', false ),
                'garden'  => $this->collect_area_data( 'garden', false ),
            ],
        ];

        return array_filter(
            $data,
            static function ( $value ) {
                return null !== $value;
            }
        );
    }

    /**
     * Zwraca sformatowany budżet.
     *
     * @param float|null $min Minimalna kwota.
     * @param float|null $max Maksymalna kwota.
     *
     * @return string
     */
    private function format_budget( ?float $min, ?float $max ) : string {
        $min_display = null !== $min ? number_format_i18n( (float) $min, 0 ) : '';
        $max_display = null !== $max ? number_format_i18n( (float) $max, 0 ) : '';

        if ( $min_display && $max_display ) {
            return sprintf( /* translators: 1: min budget, 2: max budget */ __( '%1$s – %2$s PLN', 'estate-office' ), $min_display, $max_display );
        }

        if ( $min_display ) {
            return sprintf( /* translators: %s: min budget */ __( 'od %s PLN', 'estate-office' ), $min_display );
        }

        if ( $max_display ) {
            return sprintf( /* translators: %s: max budget */ __( 'do %s PLN', 'estate-office' ), $max_display );
        }

        return __( 'Nie określono', 'estate-office' );
    }

    /**
     * Renderuje pojedyncze pole tekstowe.
     *
     * @param string               $name       Nazwa pola.
     * @param string               $label      Etykieta.
     * @param string               $value      Wartość.
     * @param array<string,string> $attributes Dodatkowe atrybuty.
     *
     * @return void
     */
    private function render_text_input( string $name, string $label, string $value, array $attributes = [] ) : void {
        $attributes['type']  = $attributes['type'] ?? 'text';
        $attributes['name']  = $name;
        $attributes['id']    = $attributes['id'] ?? $name;
        $attributes['value'] = $value;

        echo '<div class="field">';
        printf( '<label for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
        printf( '<input %s />', $this->format_attributes( $attributes ) );
        echo '</div>';
    }

    /**
     * Renderuje pole liczbowe.
     *
     * @param string               $name       Nazwa pola.
     * @param string               $label      Etykieta.
     * @param string|int|float     $value      Wartość.
     * @param array<string,string> $attributes Atrybuty.
     *
     * @return void
     */
    private function render_number_input( string $name, string $label, $value, array $attributes = [] ) : void {
        $attributes['type']  = 'number';
        $attributes['name']  = $name;
        $attributes['id']    = $attributes['id'] ?? $name;
        $attributes['value'] = '' === $value || null === $value ? '' : $value;

        echo '<div class="field">';
        printf( '<label for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
        printf( '<input %s />', $this->format_attributes( $attributes ) );
        echo '</div>';
    }

    /**
     * Renderuje pole wyboru.
     *
     * @param string               $name       Nazwa.
     * @param string               $label      Etykieta.
     * @param string               $selected   Wybrana wartość.
     * @param array<string,string> $options    Dostępne opcje.
     * @param bool                 $required   Czy wymagane.
     *
     * @return void
     */
    private function render_select_field( string $name, string $label, string $selected, array $options, bool $required, string $description = '' ) : void {
        echo '<div class="field">';
        printf( '<label for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
        printf( '<select name="%1$s" id="%1$s" %2$s>', esc_attr( $name ), $required ? 'required' : '' );
        echo '<option value="">' . esc_html__( 'Wybierz...', 'estate-office' ) . '</option>';
        foreach ( $options as $value => $option_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $selected, $value, false ),
                esc_html( $option_label )
            );
        }
        echo '</select>';
        if ( '' !== $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Renderuje pole typu checkbox.
     *
     * @param string $name    Nazwa pola.
     * @param string $label   Etykieta.
     * @param bool   $checked Czy zaznaczone.
     *
     * @return void
     */
    private function render_checkbox_field( string $name, string $label, bool $checked ) : void {
        echo '<div class="field">';
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr( $name ),
            checked( $checked, true, false ),
            esc_html( $label )
        );
        echo '</div>';
    }

    /**
     * Renderuje zestaw checkboxów.
     *
     * @param string               $name    Nazwa pola.
     * @param string               $label   Etykieta.
     * @param array<int,string>    $values  Wybrane wartości.
     * @param array<string,string> $options Opcje.
     *
     * @return void
     */
    private function render_checkboxes_field( string $name, string $label, array $values, array $options ) : void {
        echo '<div class="field">';
        printf( '<label>%s</label>', esc_html( $label ) );
        echo '<div class="estate-office-checkbox-group">';
        foreach ( $options as $value => $option_label ) {
            printf(
                '<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
                esc_attr( $name ),
                esc_attr( $value ),
                checked( in_array( $value, $values, true ), true, false ),
                esc_html( $option_label )
            );
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderuje pola powierzchni dodatkowej.
     *
     * @param string               $prefix Prefiks pola.
     * @param string               $label  Etykieta.
     * @param array<string,mixed>  $data   Dane.
     * @param bool                 $with_qty Czy zawiera ilość.
     *
     * @return void
     */
    private function render_additional_area_fields( string $prefix, string $label, array $data, bool $with_qty ) : void {
        echo '<div class="field">';
        printf( '<label>%s</label>', esc_html( $label ) );
        printf( '<label><input type="checkbox" name="%1$s_enabled" value="1" %2$s /> %3$s</label>', esc_attr( $prefix ), checked( ! empty( $data['enabled'] ), true, false ), esc_html__( 'Wymagane', 'estate-office' ) );
        if ( $with_qty ) {
            printf( '<input type="number" name="%1$s_count" value="%2$s" placeholder="%3$s" min="0" />', esc_attr( $prefix ), esc_attr( $data['count'] ?? '' ), esc_attr__( 'Minimalna liczba', 'estate-office' ) );
        }
        printf( '<input type="number" step="0.1" name="%1$s_area" value="%2$s" placeholder="%3$s" min="0" />', esc_attr( $prefix ), esc_attr( $data['area'] ?? '' ), esc_attr__( 'Minimalna powierzchnia (m²)', 'estate-office' ) );
        echo '</div>';
    }

    /**
     * Sanitizuje pojedynczy wybór.
     *
     * @param mixed        $value   Wartość.
     * @param array<mixed> $allowed Dozwolone opcje.
     *
     * @return string
     */
    private function sanitize_choice( $value, array $allowed ) : string {
        $value = sanitize_text_field( wp_unslash( (string) $value ) );

        return in_array( $value, $allowed, true ) ? $value : '';
    }

    /**
     * Sanitizuje tablicę wyborów.
     *
     * @param mixed        $values  Wartości.
     * @param array<mixed> $allowed Dozwolone opcje.
     *
     * @return array<int,string>
     */
    private function sanitize_array_choice( $values, array $allowed ) : array {
        if ( ! is_array( $values ) ) {
            return [];
        }

        $clean = [];
        foreach ( $values as $value ) {
            $value = sanitize_text_field( wp_unslash( (string) $value ) );
            if ( in_array( $value, $allowed, true ) ) {
                $clean[] = $value;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Pobiera wartość liczbową (float) z żądania.
     *
     * @param string $key Klucz tablicy $_POST.
     *
     * @return float|null
     */
    private function get_request_float( string $key ) : ?float {
        if ( ! isset( $_POST[ $key ] ) || '' === $_POST[ $key ] ) {
            return null;
        }

        return (float) sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
    }

    /**
     * Pobiera wartość całkowitą z żądania.
     *
     * @param string $key Klucz tablicy $_POST.
     *
     * @return int|null
     */
    private function get_request_int( string $key ) : ?int {
        if ( ! isset( $_POST[ $key ] ) || '' === $_POST[ $key ] ) {
            return null;
        }

        return (int) sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
    }

    /**
     * Zbiera dane powierzchni dodatkowej.
     *
     * @param string $prefix   Prefiks.
     * @param bool   $with_qty Czy uwzględnia ilość.
     *
     * @return array<string,mixed>
     */
    private function collect_area_data( string $prefix, bool $with_qty = true ) : array {
        $enabled = ! empty( $_POST[ $prefix . '_enabled' ] );
        $area    = $this->get_request_float( $prefix . '_area' );
        $data    = [
            'enabled' => $enabled,
            'area'    => $area,
        ];

        if ( $with_qty ) {
            $data['count'] = $this->get_request_int( $prefix . '_count' );
        }

        return $data;
    }

    /**
     * Zwraca listę dostępnych umów dla pola wyboru.
     *
     * @param int $selected Aktualnie wybrana umowa.
     *
     * @return array<string,string>
     */
    private function get_contract_select_options( int $selected ) : array {
        $options = [ '0' => __( 'Brak powiązanej umowy', 'estate-office' ) ];

        $contracts = $this->contracts_repository->paginate(
            [
                'per_page' => 100,
            ]
        );

        $transaction_labels = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        if ( isset( $contracts['items'] ) && is_array( $contracts['items'] ) ) {
            foreach ( $contracts['items'] as $contract ) {
                $options[ (string) $contract['id'] ] = sprintf(
                    '%1$s – %2$s',
                    $contract['contract_number'],
                    $transaction_labels[ $contract['transaction_type'] ] ?? strtoupper( (string) $contract['transaction_type'] )
                );
            }
        }

        if ( $selected > 0 && ! isset( $options[ (string) $selected ] ) ) {
            $contract = $this->contracts_repository->find( $selected );
            if ( $contract ) {
                $options[ (string) $selected ] = sprintf(
                    '%1$s – %2$s',
                    $contract['contract_number'],
                    $transaction_labels[ $contract['transaction_type'] ] ?? strtoupper( (string) $contract['transaction_type'] )
                );
            }
        }

        return $options;
    }

    /**
     * Formatuje atrybuty HTML.
     *
     * @param array<string,mixed> $attributes Atrybuty.
     *
     * @return string
     */
    private function format_attributes( array $attributes ) : string {
        $html = '';

        foreach ( $attributes as $key => $value ) {
            if ( null === $value ) {
                continue;
            }

            $html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
        }

        return trim( $html );
    }

    /**
     * Przekierowuje z komunikatem.
     *
     * @param int    $search_id ID poszukiwania.
     * @param string $message   Treść komunikatu.
     * @param string $status    Status.
     *
     * @return void
     */
    private function redirect_with_message( int $search_id, string $message, string $status, string $redirect_to = '' ) : void {
        if ( ! empty( $redirect_to ) ) {
            $redirect_url = wp_validate_redirect( $redirect_to, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );

            if ( $search_id ) {
                $redirect_url = add_query_arg(
                    [
                        'view'    => 'search',
                        'item_id' => $search_id,
                    ],
                    $redirect_url
                );
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

        $args = [
            'page'                   => self::PAGE_SLUG,
            'estate-office-message' => $message,
            'estate-office-status'  => $status,
        ];

        if ( $search_id ) {
            $args['action']    = 'edit';
            $args['search_id'] = $search_id;
        }

        $wizard = isset( $_REQUEST['wizard'] ) ? sanitize_key( wp_unslash( $_REQUEST['wizard'] ) ) : '';
        if ( 'contract' === $wizard ) {
            $args['wizard'] = 'contract';
            $step           = isset( $_REQUEST['wizard_step'] ) ? sanitize_key( wp_unslash( $_REQUEST['wizard_step'] ) ) : '';
            if ( in_array( $step, [ 'search' ], true ) ) {
                $args['wizard_step'] = $step;
            }

            $wizard_contract = isset( $_REQUEST['wizard_contract_id'] ) ? absint( $_REQUEST['wizard_contract_id'] ) : 0;
            if ( $wizard_contract ) {
                $args['wizard_contract_id'] = $wizard_contract;

                if ( ! isset( $args['contract_id'] ) ) {
                    $args['contract_id'] = $wizard_contract;
                }
            }
        }

        wp_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ) );
        exit;
    }

    /**
     * Dostępne typy transakcji.
     *
     * @return array<string,string>
     */
    private function get_transaction_types() : array {
        return [
            'SPRZEDAŻ' => __( 'Sprzedaż', 'estate-office' ),
            'KUPNO'    => __( 'Kupno', 'estate-office' ),
            'WYNAJEM'  => __( 'Wynajem', 'estate-office' ),
            'NAJEM'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Dostępne typy nieruchomości.
     *
     * @return array<string,string>
     */
    private function get_property_types() : array {
        return [
            'MIESZKANIE' => __( 'Mieszkanie', 'estate-office' ),
            'DOM'        => __( 'Dom', 'estate-office' ),
            'DZIAŁKA'    => __( 'Działka', 'estate-office' ),
            'LOKAL'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];
    }

    /**
     * Dostępne stany wykończenia.
     *
     * @return array<string,string>
     */
    private function get_finish_states() : array {
        return [
            'DO_WYKONCZENIA' => __( 'Do wykończenia', 'estate-office' ),
            'DO_REMONTU'     => __( 'Do remontu', 'estate-office' ),
            'DOBRY'          => __( 'Dobry', 'estate-office' ),
            'BARDZO_DOBRY'   => __( 'Bardzo dobry', 'estate-office' ),
            'DEWELOPERSKI'   => __( 'Stan deweloperski', 'estate-office' ),
        ];
    }

    /**
     * Dostępne ekspozycje.
     *
     * @return array<string,string>
     */
    private function get_exposure_options() : array {
        return [
            'PÓŁNOC' => __( 'Północ', 'estate-office' ),
            'POŁUDNIE' => __( 'Południe', 'estate-office' ),
            'WSCHÓD' => __( 'Wschód', 'estate-office' ),
            'ZACHÓD' => __( 'Zachód', 'estate-office' ),
        ];
    }

    /**
     * Dostępne widoki.
     *
     * @return array<string,string>
     */
    private function get_view_options() : array {
        return [
            'PANORAMA' => __( 'Panorama', 'estate-office' ),
            'PARK'     => __( 'Park', 'estate-office' ),
            'ZIELEŃ'   => __( 'Zieleń', 'estate-office' ),
            'ULICA'    => __( 'Ulica', 'estate-office' ),
        ];
    }

    /**
     * Dostępne układy.
     *
     * @return array<string,string>
     */
    private function get_layout_options() : array {
        return [
            'DWUSTRONNE'  => __( 'Dwustronne', 'estate-office' ),
            'JEDNOSTRONNE'=> __( 'Jednostronne', 'estate-office' ),
            'ROZKLADOWE'  => __( 'Rozkładowe', 'estate-office' ),
            'OTWARTE'     => __( 'Open space', 'estate-office' ),
        ];
    }

    /**
     * Typy kuchni.
     *
     * @return array<string,string>
     */
    private function get_kitchen_types() : array {
        return [
            'ANEKS'    => __( 'Aneks', 'estate-office' ),
            'ODDZIELNA'=> __( 'Oddzielna', 'estate-office' ),
            'Z_SALONEM'=> __( 'Z salonem', 'estate-office' ),
        ];
    }

    /**
     * Typy parkingów.
     *
     * @return array<string,string>
     */
    private function get_parking_types() : array {
        return [
            'ZEWNĘTRZNY' => __( 'Miejsce zewnętrzne', 'estate-office' ),
            'PODZIEMNY'  => __( 'Parking podziemny', 'estate-office' ),
            'GARAŻ'      => __( 'Garaż', 'estate-office' ),
        ];
    }

    /**
     * Typy ogrzewania.
     *
     * @return array<string,string>
     */
    private function get_heating_types() : array {
        return [
            'MIEJSKIE' => __( 'Miejskie', 'estate-office' ),
            'GAZOWE'   => __( 'Gazowe', 'estate-office' ),
            'ELEKTRYCZNE' => __( 'Elektryczne', 'estate-office' ),
            'PALIWOWE' => __( 'Na paliwo stałe', 'estate-office' ),
        ];
    }

    /**
     * Typy wody.
     *
     * @return array<string,string>
     */
    private function get_water_types() : array {
        return [
            'MIEJSKA'  => __( 'Sieć miejska', 'estate-office' ),
            'STUDNIA'  => __( 'Studnia', 'estate-office' ),
            'UZDATNIANIE' => __( 'Własna stacja uzdatniania', 'estate-office' ),
        ];
    }

    /**
     * Typy kanalizacji.
     *
     * @return array<string,string>
     */
    private function get_sewage_types() : array {
        return [
            'MIEJSKA' => __( 'Sieć miejska', 'estate-office' ),
            'SZAMBO'  => __( 'Szambo', 'estate-office' ),
            'PRZYDOMOWA_OCZYSZCZALNIA' => __( 'Przydomowa oczyszczalnia', 'estate-office' ),
        ];
    }

    /**
     * Poziomy umeblowania.
     *
     * @return array<string,string>
     */
    private function get_equipment_levels() : array {
        return [
            'PEŁNE'      => __( 'Pełne', 'estate-office' ),
            'CZĘŚCIOWE'  => __( 'Częściowe', 'estate-office' ),
            'BRAK'       => __( 'Brak', 'estate-office' ),
        ];
    }

    /**
     * Elementy wyposażenia.
     *
     * @return array<string,string>
     */
    private function get_equipment_items() : array {
        return [
            'PRALKA'    => __( 'Pralka', 'estate-office' ),
            'ZMYWARKA'  => __( 'Zmywarka', 'estate-office' ),
            'LODÓWKA'   => __( 'Lodówka', 'estate-office' ),
            'KUCHENKA'  => __( 'Kuchenka', 'estate-office' ),
            'PIEKARNIK' => __( 'Piekarnik', 'estate-office' ),
            'TELEWIZOR' => __( 'Telewizor', 'estate-office' ),
            'MIKROFALA' => __( 'Mikrofalówka', 'estate-office' ),
        ];
    }

    /**
     * Udogodnienia.
     *
     * @return array<string,string>
     */
    private function get_amenities_options() : array {
        return [
            'WINDA'        => __( 'Winda', 'estate-office' ),
            'OCHRONA'      => __( 'Monitoring / Ochrona', 'estate-office' ),
            'KLIMATYZACJA' => __( 'Klimatyzacja', 'estate-office' ),
            'RECEPCJA'     => __( 'Recepcja', 'estate-office' ),
            'TEREN_ZAMKNIĘTY' => __( 'Teren zamknięty', 'estate-office' ),
            'DOMOFON'      => __( 'Domofon', 'estate-office' ),
        ];
    }
}
