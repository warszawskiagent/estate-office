<?php
/**
 * Frontendowy portal CRM EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Frontend_Portal
 */
class Estate_Office_Frontend_Portal {

    /**
     * Repozytorium nieruchomości.
     *
     * @var Estate_Office_Property_Repository
     */
    private Estate_Office_Property_Repository $property_repository;

    /**
     * Repozytorium poszukiwań.
     *
     * @var Estate_Office_Search_Repository
     */
    private Estate_Office_Search_Repository $search_repository;

    /**
     * Repozytorium klientów.
     *
     * @var Estate_Office_Client_Repository
     */
    private Estate_Office_Client_Repository $client_repository;

    /**
     * Repozytorium umów.
     *
     * @var Estate_Office_Contract_Repository
     */
    private Estate_Office_Contract_Repository $contract_repository;

    /**
     * Serwis danych pulpitu.
     *
     * @var Estate_Office_Dashboard_Service
     */
    private Estate_Office_Dashboard_Service $dashboard_service;

    /**
     * ID strony portalu.
     *
     * @var int|null
     */
    private ?int $portal_page_id;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Property_Repository|null $property_repository Repo nieruchomości.
     * @param Estate_Office_Search_Repository|null   $search_repository   Repo poszukiwań.
     * @param Estate_Office_Client_Repository|null   $client_repository   Repo klientów.
     * @param Estate_Office_Contract_Repository|null $contract_repository Repo umów.
     * @param Estate_Office_Dashboard_Service|null   $dashboard_service   Serwis pulpitu.
     */
    public function __construct(
        ?Estate_Office_Property_Repository $property_repository = null,
        ?Estate_Office_Search_Repository $search_repository = null,
        ?Estate_Office_Client_Repository $client_repository = null,
        ?Estate_Office_Contract_Repository $contract_repository = null,
        ?Estate_Office_Dashboard_Service $dashboard_service = null
    ) {
        $this->property_repository  = $property_repository ?? new Estate_Office_Property_Repository();
        $this->search_repository    = $search_repository ?? new Estate_Office_Search_Repository();
        $this->client_repository    = $client_repository ?? new Estate_Office_Client_Repository();
        $this->contract_repository  = $contract_repository ?? new Estate_Office_Contract_Repository();
        $this->dashboard_service    = $dashboard_service ?? new Estate_Office_Dashboard_Service();
        $this->portal_page_id       = $this->load_portal_page_id();
    }

    /**
     * Rejestruje hooki frontendowe.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'template_redirect', [ $this, 'protect_portal_page' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }
    /**
     * Tworzy stronę portalu, jeśli nie istnieje.
     *
     * @return void
     */
    public static function ensure_portal_page() : void {
        $option_key = 'estate_office_portal_page_id';
        $page_id    = (int) get_option( $option_key, 0 );

        if ( $page_id > 0 ) {
            $page = get_post( $page_id );
            if ( $page instanceof WP_Post ) {
                if ( false === strpos( $page->post_content, '[estate_office_crm_portal]' ) ) {
                    wp_update_post(
                        [
                            'ID'           => $page->ID,
                            'post_content' => '[estate_office_crm_portal]',
                        ]
                    );
                }

                return;
            }
        }

        $existing = get_page_by_path( 'crm' );
        if ( $existing instanceof WP_Post ) {
            if ( false === strpos( $existing->post_content, '[estate_office_crm_portal]' ) ) {
                wp_update_post(
                    [
                        'ID'           => $existing->ID,
                        'post_content' => '[estate_office_crm_portal]',
                    ]
                );
            }

            update_option( $option_key, (int) $existing->ID );
            Estate_Office_Plugin::log_debug( 'Odnaleziono istniejącą stronę portalu CRM.', [ 'page_id' => (int) $existing->ID ] );

            return;
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => __( 'CRM', 'estate-office' ),
                'post_name'    => 'crm',
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => '[estate_office_crm_portal]',
            ],
            true
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            Estate_Office_Plugin::log_debug(
                'Nie udało się utworzyć strony portalu CRM.',
                [ 'error' => $page_id instanceof WP_Error ? $page_id->get_error_message() : 'unknown' ]
            );

            return;
        }

        update_option( $option_key, (int) $page_id );
        Estate_Office_Plugin::log_debug( 'Utworzono stronę portalu CRM.', [ 'page_id' => (int) $page_id ] );
    }

    /**
     * Rejestracja shortcode portalu.
     *
     * @return void
     */
    public function register_shortcodes() : void {
        add_shortcode( 'estate_office_crm_portal', [ $this, 'render_portal_shortcode' ] );
    }
    /**
     * Chroni stronę portalu przed dostępem niezalogowanych użytkowników.
     *
     * @return void
     */
    public function protect_portal_page() : void {
        if ( ! $this->is_portal_page() ) {
            return;
        }

        $permalink = $this->get_portal_permalink();

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( $permalink ) );
            exit;
        }

        if ( ! current_user_can( 'access_estate_office_portal' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do korzystania z portalu CRM.', 'estate-office' ), '', [ 'response' => 403 ] );
        }
    }

    /**
     * Dołącza zasoby CSS/JS portalu.
     *
     * @return void
     */
    public function enqueue_assets() : void {
        if ( ! $this->is_portal_page() ) {
            return;
        }

        $style_handle = 'estate-office-crm-portal';
        wp_register_style( $style_handle, false, [], ESTATE_OFFICE_VERSION );
        wp_enqueue_style( $style_handle );
        wp_add_inline_style( $style_handle, $this->get_inline_styles() );

        $script_handle = 'estate-office-crm-portal';
        wp_register_script( $script_handle, false, [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $script_handle );
        wp_add_inline_script(
            $script_handle,
            <<<'JS'
jQuery(function($){
    const $tabs = $('.estate-office-crm-portal__nav a');
    $tabs.on('click', function(){
        const target = $(this).attr('href');
        if (target && target.startsWith('#')) {
            $('.estate-office-crm-portal__section').removeClass('is-active');
            $(target).addClass('is-active');
        }
    });
});
JS
        );
    }
    /**
     * Renderuje zawartość shortcode portalu.
     *
     * @param array<string,mixed> $atts    Atrybuty.
     * @param string              $content Treść.
     *
     * @return string
     */
    public function render_portal_shortcode( array $atts = [], string $content = '' ) : string {
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( $this->get_portal_permalink() );

            return '<div class="estate-office-crm-portal__notice">' . sprintf(
                /* translators: %s: link do logowania */
                esc_html__( 'Aby uzyskać dostęp do CRM, zaloguj się: %s', 'estate-office' ),
                '<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Zaloguj się', 'estate-office' ) . '</a>'
            ) . '</div>';
        }

        if ( ! current_user_can( 'access_estate_office_portal' ) ) {
            return '<div class="estate-office-crm-portal__notice">' . esc_html__( 'Twoje konto nie ma dostępu do portalu CRM.', 'estate-office' ) . '</div>';
        }

        $active_tab = $this->get_active_tab();
        $view       = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
        $item_id    = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        $base_url   = $this->get_base_url();

        ob_start();

        echo '<div class="estate-office-crm-portal">';
        echo '<div class="estate-office-crm-portal__header">';
        echo '<h1>' . esc_html__( 'Estate Office CRM', 'estate-office' ) . '</h1>';
        echo '</div>';

        $this->render_primary_actions( $base_url );

        $this->render_navigation( $active_tab, $base_url );

        echo '<div class="estate-office-crm-portal__body">';
        if ( $view && in_array( $view, [ 'new-client', 'new-property', 'new-search' ], true ) ) {
            echo $this->render_creation_view( $view, $base_url );
        } elseif ( $view && $item_id ) {
            echo $this->render_detail_view( $view, $item_id, $base_url );
        } else {
            echo $this->render_tab_content( $active_tab, $base_url );
        }
        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Renderuje nawigację zakładek.
     *
     * @param string $active_tab Aktywna zakładka.
     * @param string $base_url   URL bazowy.
     *
     * @return void
     */
    private function render_navigation( string $active_tab, string $base_url ) : void {
        $tabs = [
            'dashboard'  => __( 'Pulpit', 'estate-office' ),
            'properties' => __( 'Nieruchomości', 'estate-office' ),
            'searches'   => __( 'Poszukiwania', 'estate-office' ),
            'contracts'  => __( 'Umowy', 'estate-office' ),
            'clients'    => __( 'Klienci', 'estate-office' ),
        ];

        echo '<nav class="estate-office-crm-portal__nav">';
        foreach ( $tabs as $key => $label ) {
            $url = add_query_arg( 'crm_tab', $key, $base_url );
            printf(
                '<a href="%1$s" class="%2$s">%3$s</a>',
                esc_url( $url ),
                esc_attr( $active_tab === $key ? 'is-active' : '' ),
                esc_html( $label )
            );
        }
        echo '</nav>';
    }

    /**
     * Renderuje zawartość aktywnej zakładki.
     *
     * @param string $active_tab Aktywna zakładka.
     * @param string $base_url   URL bazowy.
     *
     * @return string
     */
    private function render_tab_content( string $active_tab, string $base_url ) : string {
        switch ( $active_tab ) {
            case 'properties':
                return $this->render_properties_tab( $base_url );
            case 'searches':
                return $this->render_searches_tab( $base_url );
            case 'contracts':
                return $this->render_contracts_tab( $base_url );
            case 'clients':
                return $this->render_clients_tab( $base_url );
            case 'dashboard':
            default:
                return $this->render_dashboard_tab();
        }
    }

    /**
     * Renderuje dashboard portalu.
     *
     * @return string
     */
    private function render_dashboard_tab() : string {
        $summary          = $this->dashboard_service->get_summary();
        $top_agents       = $this->dashboard_service->get_best_agents();
        $recent_stages    = $this->dashboard_service->get_recent_stages();
        $recent_contracts = $this->dashboard_service->get_recent_contracts();

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-dashboard">';
        echo '<div class="estate-office-crm-portal__cards">';

        $cards = [
            [ __( 'Nieruchomości', 'estate-office' ), $summary['properties_total'] ?? 0 ],
            [ __( 'Aktywne umowy', 'estate-office' ), $summary['contracts_active'] ?? 0 ],
            [ __( 'Poszukiwania', 'estate-office' ), $summary['searches_total'] ?? 0 ],
            [ __( 'Klienci', 'estate-office' ), $summary['clients_total'] ?? 0 ],
        ];

        foreach ( $cards as $card ) {
            echo '<div class="estate-office-crm-portal__card">';
            echo '<span>' . esc_html( $card[0] ) . '</span>';
            echo '<strong>' . esc_html( number_format_i18n( (int) $card[1] ) ) . '</strong>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="estate-office-crm-portal__grid">';

        echo '<div class="estate-office-crm-portal__panel">';
        echo '<h2>' . esc_html__( 'Najaktywniejsi agenci', 'estate-office' ) . '</h2>';
        if ( empty( $top_agents ) ) {
            echo '<p>' . esc_html__( 'Brak danych o aktywnościach agentów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__list">';
            foreach ( $top_agents as $agent ) {
                $count = isset( $agent['activity_count'] ) ? (int) $agent['activity_count'] : 0;
                printf(
                    '<li><span>%1$s</span><strong>%2$s</strong></li>',
                    esc_html( $agent['name'] ?? '' ),
                    esc_html( number_format_i18n( $count ) )
                );
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="estate-office-crm-portal__panel">';
        echo '<h2>' . esc_html__( 'Ostatnie etapy umów', 'estate-office' ) . '</h2>';
        if ( empty( $recent_stages ) ) {
            echo '<p>' . esc_html__( 'Brak historii etapów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__timeline">';
            foreach ( $recent_stages as $stage ) {
                echo '<li>';
                echo '<strong>' . esc_html( $stage['contract_number'] ?? '' ) . '</strong>';
                echo '<span>' . esc_html( $stage['stage_label'] ?? '' ) . '</span>';
                if ( ! empty( $stage['author'] ) ) {
                    echo '<em>' . esc_html( $stage['author'] ) . '</em>';
                }
                if ( ! empty( $stage['stage_date'] ) ) {
                    echo '<time datetime="' . esc_attr( $stage['stage_date'] ) . '">' . esc_html( mysql2date( get_option( 'date_format' ), $stage['stage_date'] ) ) . '</time>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="estate-office-crm-portal__panel">';
        echo '<h2>' . esc_html__( 'Najnowsze umowy', 'estate-office' ) . '</h2>';
        if ( empty( $recent_contracts ) ) {
            echo '<p>' . esc_html__( 'Brak nowych umów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="estate-office-crm-portal__table">';
            echo '<thead><tr><th>' . esc_html__( 'Numer', 'estate-office' ) . '</th><th>' . esc_html__( 'Typ', 'estate-office' ) . '</th><th>' . esc_html__( 'Status', 'estate-office' ) . '</th><th>' . esc_html__( 'Data', 'estate-office' ) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $recent_contracts as $contract ) {
                echo '<tr>';
                echo '<td>' . esc_html( $contract['contract_number'] ?? '' ) . '</td>';
                echo '<td>' . esc_html( $contract['transaction_label'] ?? '' ) . '</td>';
                echo '<td>' . esc_html( $contract['status_label'] ?? '' ) . '</td>';
                echo '<td>' . esc_html( $contract['created_human'] ?? '' ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }
    /**
     * Renderuje zakładkę nieruchomości.
     *
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_properties_tab( string $base_url ) : string {
        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-properties">';
        echo '<h2>' . esc_html__( 'Zarządzanie nieruchomościami', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Widok listy nieruchomości pozostaje w panelu administracyjnym. Z tego miejsca możesz rozpocząć dodawanie nowej oferty w kontekście trwającej umowy.', 'estate-office' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( [ 'crm_tab' => 'properties', 'view' => 'new-property' ], $base_url ) ) . '">' . esc_html__( 'Dodaj nieruchomość', 'estate-office' ) . '</a></p>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Renderuje zakładkę poszukiwań.
     *
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_searches_tab( string $base_url ) : string {
        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-searches">';
        echo '<h2>' . esc_html__( 'Poszukiwania klientów', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Zarządzanie poszukiwaniami odbywa się w panelu administracyjnym. Portal prowadzi Cię przez kreator umowy, gdzie po przypisaniu klientów możesz dodać nowe poszukiwanie.', 'estate-office' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( [ 'crm_tab' => 'searches', 'view' => 'new-search' ], $base_url ) ) . '">' . esc_html__( 'Dodaj poszukiwanie', 'estate-office' ) . '</a></p>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Renderuje zakładkę umów.
     *
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_contracts_tab( string $base_url ) : string {
        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-contracts">';
        echo '<h2>' . esc_html__( 'Umowy', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Pełna lista umów znajduje się w panelu administracyjnym. W portalu możesz szybko rozpocząć proces tworzenia nowej umowy z prowadzącym kreatorem.', 'estate-office' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( [ 'crm_tab' => 'contracts', 'view' => 'new-contract' ], $base_url ) ) . '">' . esc_html__( 'Dodaj umowę', 'estate-office' ) . '</a></p>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Renderuje zakładkę klientów.
     *
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_clients_tab( string $base_url ) : string {
        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-clients">';
        echo '<h2>' . esc_html__( 'Klienci', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Lista klientów jest dostępna w panelu administracyjnym. Użyj przycisku poniżej, aby szybko dodać nowy profil prosto z portalu.', 'estate-office' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( [ 'crm_tab' => 'clients', 'view' => 'new-client' ], $base_url ) ) . '">' . esc_html__( 'Dodaj klienta', 'estate-office' ) . '</a></p>';
        echo '</section>';

        return (string) ob_get_clean();
    }
    /**
     * Renderuje widok szczegółowy.
     *
     * @param string $view     Typ widoku.
     * @param int    $item_id  ID rekordu.
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_detail_view( string $view, int $item_id, string $base_url ) : string {
        switch ( $view ) {
            case 'property':
                return $this->render_property_detail( $item_id, $base_url );
            case 'search':
                return $this->render_search_detail( $item_id, $base_url );
            case 'contract':
                return $this->render_contract_detail( $item_id, $base_url );
            case 'client':
                return $this->render_client_detail( $item_id, $base_url );
            default:
                return '<p>' . esc_html__( 'Nie znaleziono wskazanego rekordu.', 'estate-office' ) . '</p>';
        }
    }

    /**
     * Widok szczegółów nieruchomości.
     *
     * @param int    $item_id  ID nieruchomości.
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_property_detail( int $item_id, string $base_url ) : string {
        $property = $this->property_repository->find( $item_id );
        if ( null === $property ) {
            return '<p>' . esc_html__( 'Nie znaleziono nieruchomości.', 'estate-office' ) . '</p>';
        }

        ob_start();
        echo '<div class="estate-office-crm-portal__detail">';
        echo '<a class="estate-office-crm-portal__back" href="' . esc_url( add_query_arg( 'crm_tab', 'properties', $base_url ) ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<h2>' . esc_html( $property['listing_number'] ?? '' ) . '</h2>';

        echo '<div class="estate-office-crm-portal__detail-grid">';
        echo '<div>';
        echo '<table class="estate-office-crm-portal__table">';
        $rows = [
            __( 'Typ transakcji', 'estate-office' ) => $this->map_transaction_type( $property['transaction_type'] ?? '' ),
            __( 'Rodzaj nieruchomości', 'estate-office' ) => $this->map_property_type( $property['property_type'] ?? '' ),
            __( 'Cena', 'estate-office' ) => $this->format_price( $property['price'] ?? null, '', $property['price_currency'] ?? 'PLN' ),
            __( 'Metraż', 'estate-office' ) => $this->format_area( $property['area_total'] ?? null ),
            __( 'Liczba pokoi', 'estate-office' ) => $property['rooms'] ?? '—',
            __( 'Adres', 'estate-office' ) => $this->format_property_address( $property ),
            __( 'Status prawny', 'estate-office' ) => $property['ownership_status'] ?? '',
        ];
        foreach ( $rows as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__detail-description">';
        echo '<h3>' . esc_html__( 'Opis nieruchomości', 'estate-office' ) . '</h3>';
        echo wp_kses_post( wpautop( (string) ( $property['description'] ?? '' ) ) );
        echo '</div>';
        echo '</div>';

        if ( ! empty( $property['contract_id'] ) ) {
            $contract = $this->contract_repository->find( (int) $property['contract_id'] );
            if ( $contract ) {
                $contract_url = add_query_arg(
                    [
                        'crm_tab' => 'contracts',
                        'view'    => 'contract',
                        'item_id' => (int) $contract['id'],
                    ],
                    $base_url
                );
                echo '<p><strong>' . esc_html__( 'Powiązana umowa:', 'estate-office' ) . '</strong> <a href="' . esc_url( $contract_url ) . '">' . esc_html( $contract['contract_number'] ?? '' ) . '</a></p>';
            }
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Widok szczegółów poszukiwania.
     *
     * @param int    $item_id  ID poszukiwania.
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_search_detail( int $item_id, string $base_url ) : string {
        $search = $this->search_repository->find( $item_id );
        if ( null === $search ) {
            return '<p>' . esc_html__( 'Nie znaleziono poszukiwania.', 'estate-office' ) . '</p>';
        }

        ob_start();
        echo '<div class="estate-office-crm-portal__detail">';
        echo '<a class="estate-office-crm-portal__back" href="' . esc_url( add_query_arg( 'crm_tab', 'searches', $base_url ) ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<h2>' . esc_html( $search['search_number'] ?? '' ) . '</h2>';

        echo '<div class="estate-office-crm-portal__detail-grid">';
        echo '<div>';
        echo '<table class="estate-office-crm-portal__table">';
        $rows = [
            __( 'Typ transakcji', 'estate-office' ) => $this->map_transaction_type( $search['transaction_type'] ?? '' ),
            __( 'Rodzaj nieruchomości', 'estate-office' ) => $this->map_property_type( $search['property_type'] ?? '' ),
            __( 'Budżet', 'estate-office' ) => $this->format_range( $search['price_min'] ?? null, $search['price_max'] ?? null ),
            __( 'Metraż', 'estate-office' ) => $this->format_range( $search['area_min'] ?? null, $search['area_max'] ?? null, __( 'm²', 'estate-office' ) ),
            __( 'Liczba pokoi', 'estate-office' ) => $this->format_range( $search['rooms_min'] ?? null, $search['rooms_max'] ?? null ),
            __( 'Lokalizacja', 'estate-office' ) => implode( ', ', array_filter( [ $search['location_city'] ?? '', $search['location_district'] ?? '' ] ) ),
        ];
        foreach ( $rows as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__detail-description">';
        echo '<h3>' . esc_html__( 'Opis poszukiwania', 'estate-office' ) . '</h3>';
        echo wp_kses_post( wpautop( (string) ( $search['description'] ?? '' ) ) );
        echo '</div>';
        echo '</div>';

        if ( ! empty( $search['contract_id'] ) ) {
            $contract = $this->contract_repository->find( (int) $search['contract_id'] );
            if ( $contract ) {
                $contract_url = add_query_arg(
                    [
                        'crm_tab' => 'contracts',
                        'view'    => 'contract',
                        'item_id' => (int) $contract['id'],
                    ],
                    $base_url
                );
                echo '<p><strong>' . esc_html__( 'Powiązana umowa:', 'estate-office' ) . '</strong> <a href="' . esc_url( $contract_url ) . '">' . esc_html( $contract['contract_number'] ?? '' ) . '</a></p>';
            }
        }

        echo '</div>';

        return (string) ob_get_clean();
    }
    /**
     * Widok szczegółów umowy.
     *
     * @param int    $item_id  ID umowy.
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_contract_detail( int $item_id, string $base_url ) : string {
        $contract = $this->contract_repository->find( $item_id );
        if ( null === $contract ) {
            return '<p>' . esc_html__( 'Nie znaleziono umowy.', 'estate-office' ) . '</p>';
        }

        $clients    = $this->contract_repository->get_clients( $item_id );
        $properties = $this->contract_repository->get_properties( $item_id );
        $searches   = $this->contract_repository->get_searches( $item_id );
        $stages     = $this->contract_repository->get_stages( $item_id );

        ob_start();
        echo '<div class="estate-office-crm-portal__detail">';
        echo '<a class="estate-office-crm-portal__back" href="' . esc_url( add_query_arg( 'crm_tab', 'contracts', $base_url ) ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<h2>' . esc_html( $contract['contract_number'] ?? '' ) . '</h2>';

        echo '<div class="estate-office-crm-portal__detail-grid">';
        echo '<div>';
        echo '<table class="estate-office-crm-portal__table">';
        $rows = [
            __( 'Typ transakcji', 'estate-office' ) => $this->map_transaction_type( $contract['transaction_type'] ?? '' ),
            __( 'Status', 'estate-office' ) => $this->map_contract_status( $contract['status'] ?? '' ),
            __( 'Data zawarcia', 'estate-office' ) => $this->format_date( $contract['start_date'] ?? '' ),
            __( 'Data zakończenia', 'estate-office' ) => $this->format_date( $contract['end_date'] ?? '' ),
            __( 'Prowizja', 'estate-office' ) => $this->format_commission( $contract ),
            __( 'Aktualny etap', 'estate-office' ) => $this->map_stage( $contract['current_stage'] ?? '' ),
        ];
        foreach ( $rows as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__detail-description">';
        echo '<h3>' . esc_html__( 'Historia etapów', 'estate-office' ) . '</h3>';
        if ( empty( $stages ) ) {
            echo '<p>' . esc_html__( 'Brak zarejestrowanych etapów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__timeline">';
            foreach ( $stages as $stage ) {
                echo '<li>';
                echo '<strong>' . esc_html( $this->map_stage( $stage['stage'] ?? '' ) ) . '</strong>';
                if ( ! empty( $stage['stage_date'] ) ) {
                    echo '<time datetime="' . esc_attr( $stage['stage_date'] ) . '">' . esc_html( mysql2date( get_option( 'date_format' ), $stage['stage_date'] ) ) . '</time>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__columns">';

        echo '<div>';
        echo '<h3>' . esc_html__( 'Klienci', 'estate-office' ) . '</h3>';
        if ( empty( $clients ) ) {
            echo '<p>' . esc_html__( 'Brak przypisanych klientów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__list">';
            foreach ( $clients as $client ) {
                $client_url = add_query_arg(
                    [
                        'crm_tab' => 'clients',
                        'view'    => 'client',
                        'item_id' => (int) $client['id'],
                    ],
                    $base_url
                );
                echo '<li><a href="' . esc_url( $client_url ) . '">' . esc_html( $this->format_client_name( $client ) ) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div>';
        echo '<h3>' . esc_html__( 'Nieruchomości', 'estate-office' ) . '</h3>';
        if ( empty( $properties ) ) {
            echo '<p>' . esc_html__( 'Brak powiązanych nieruchomości.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__list">';
            foreach ( $properties as $property ) {
                $property_url = add_query_arg(
                    [
                        'crm_tab' => 'properties',
                        'view'    => 'property',
                        'item_id' => (int) $property['id'],
                    ],
                    $base_url
                );
                echo '<li><a href="' . esc_url( $property_url ) . '">' . esc_html( $property['listing_number'] ?? '' ) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div>';
        echo '<h3>' . esc_html__( 'Poszukiwania', 'estate-office' ) . '</h3>';
        if ( empty( $searches ) ) {
            echo '<p>' . esc_html__( 'Brak powiązanych poszukiwań.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__list">';
            foreach ( $searches as $search ) {
                $search_url = add_query_arg(
                    [
                        'crm_tab' => 'searches',
                        'view'    => 'search',
                        'item_id' => (int) $search['id'],
                    ],
                    $base_url
                );
                echo '<li><a href="' . esc_url( $search_url ) . '">' . esc_html( $search['search_number'] ?? '' ) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Widok szczegółów klienta.
     *
     * @param int    $item_id  ID klienta.
     * @param string $base_url URL bazowy.
     *
     * @return string
     */
    private function render_client_detail( int $item_id, string $base_url ) : string {
        $client = $this->client_repository->find( $item_id );
        if ( null === $client ) {
            return '<p>' . esc_html__( 'Nie znaleziono klienta.', 'estate-office' ) . '</p>';
        }

        $contracts = $this->client_repository->get_contracts_for_client( $item_id );

        ob_start();
        echo '<div class="estate-office-crm-portal__detail">';
        echo '<a class="estate-office-crm-portal__back" href="' . esc_url( add_query_arg( 'crm_tab', 'clients', $base_url ) ) . '">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a>';
        echo '<h2>' . esc_html( $this->format_client_name( $client ) ) . '</h2>';

        echo '<div class="estate-office-crm-portal__detail-grid">';
        echo '<div>';
        echo '<table class="estate-office-crm-portal__table">';
        $rows = [
            __( 'Typ klienta', 'estate-office' ) => $client['client_type'] ?? '',
            __( 'Telefon', 'estate-office' ) => $client['phone'] ?? '',
            __( 'E-mail', 'estate-office' ) => $client['email'] ?? '',
            __( 'Adres', 'estate-office' ) => $this->format_client_address( $client ),
            __( 'Adres korespondencyjny', 'estate-office' ) => $this->format_client_correspondence( $client ),
        ];
        foreach ( $rows as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__detail-description">';
        echo '<h3>' . esc_html__( 'Notatki', 'estate-office' ) . '</h3>';
        if ( empty( $client['notes'] ) ) {
            echo '<p>' . esc_html__( 'Brak dodatkowych notatek.', 'estate-office' ) . '</p>';
        } else {
            echo wp_kses_post( wpautop( (string) $client['notes'] ) );
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__panel">';
        echo '<h3>' . esc_html__( 'Powiązane umowy', 'estate-office' ) . '</h3>';
        if ( empty( $contracts ) ) {
            echo '<p>' . esc_html__( 'Klient nie ma przypisanych umów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-crm-portal__list">';
            foreach ( $contracts as $contract ) {
                $contract_url = add_query_arg(
                    [
                        'crm_tab' => 'contracts',
                        'view'    => 'contract',
                        'item_id' => (int) $contract['id'],
                    ],
                    $base_url
                );
                echo '<li><a href="' . esc_url( $contract_url ) . '">' . esc_html( $contract['contract_number'] ?? '' ) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }
    /**
     * Formularz wyszukiwania dla zakładek.
     *
     * @param string $base_url URL bazowy.
     * @param string $context  Kontekst zakładki.
     * @param string $value    Wartość wyszukiwarki.
     *
     * @return string
     */
    private function render_search_form( string $base_url, string $context, string $value ) : string {
        $action = esc_url( $base_url );
        $input  = esc_attr( $value );
        $tab    = esc_attr( $context );

        return '<form class="estate-office-crm-portal__search" method="get" action="' . $action . '">'
            . '<input type="hidden" name="crm_tab" value="' . esc_attr( $context ) . '" />'
            . '<label class="screen-reader-text" for="estate-office-search-' . $tab . '">' . esc_html__( 'Szukaj', 'estate-office' ) . '</label>'
            . '<input id="estate-office-search-' . $tab . '" type="search" name="' . esc_attr( $context ) . '_search" value="' . $input . '" placeholder="' . esc_attr__( 'Szukaj w tabeli…', 'estate-office' ) . '" />'
            . '<button type="submit">' . esc_html__( 'Szukaj', 'estate-office' ) . '</button>'
            . '</form>';
    }

    /**
     * Formatuje cenę.
     *
     * @param float|null $price  Kwota.
     * @param string     $suffix Sufiks jednostki.
     *
     * @return string
     */
    private function format_price( $price, string $suffix = '', string $currency = 'PLN' ) : string {
        if ( null === $price || '' === $price ) {
            return '—';
        }

        $formatted = number_format_i18n( (float) $price, 2 ) . ' ' . strtoupper( $currency );
        if ( $suffix ) {
            $formatted .= ' / ' . $suffix;
        }

        return $formatted;
    }

    /**
     * Formatuje metraż.
     *
     * @param float|null $area Powierzchnia.
     *
     * @return string
     */
    private function format_area( $area ) : string {
        if ( null === $area || '' === $area ) {
            return '—';
        }

        return number_format_i18n( (float) $area, 2 ) . ' ' . __( 'm²', 'estate-office' );
    }

    /**
     * Formatuje zakres wartości.
     *
     * @param float|int|null $min   Minimum.
     * @param float|int|null $max   Maksimum.
     * @param string         $unit  Jednostka.
     *
     * @return string
     */
    private function format_range( $min, $max, string $unit = '' ) : string {
        $parts = [];

        if ( $min ) {
            $parts[] = sprintf( __( 'od %s', 'estate-office' ), number_format_i18n( (float) $min, 2 ) );
        }

        if ( $max ) {
            $parts[] = sprintf( __( 'do %s', 'estate-office' ), number_format_i18n( (float) $max, 2 ) );
        }

        if ( empty( $parts ) ) {
            return '—';
        }

        $output = implode( ' ', $parts );
        if ( $unit ) {
            $output .= ' ' . $unit;
        }

        return $output;
    }

    /**
     * Mapuje typ nieruchomości na etykietę.
     *
     * @param string $type Typ.
     *
     * @return string
     */
    private function map_property_type( string $type ) : string {
        $normalized = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $type, 'UTF-8' ) : strtoupper( $type );
        $map        = [
            'MIESZKANIE' => __( 'Mieszkanie', 'estate-office' ),
            'DOM'        => __( 'Dom', 'estate-office' ),
            'DZIAŁKA'    => __( 'Działka', 'estate-office' ),
            'DZIALKA'    => __( 'Działka', 'estate-office' ),
            'LOKAL'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];

        return $map[ $normalized ] ?? $type;
    }

    /**
     * Mapuje typ transakcji.
     *
     * @param string $type Typ.
     *
     * @return string
     */
    private function map_transaction_type( string $type ) : string {
        $map = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        return $map[ $type ] ?? $type;
    }

    /**
     * Mapuje etap umowy.
     *
     * @param string $stage Etap.
     *
     * @return string
     */
    private function map_stage( string $stage ) : string {
        $map = [
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

        return $map[ $stage ] ?? $stage;
    }

    /**
     * Mapuje status umowy.
     *
     * @param string $status Status.
     *
     * @return string
     */
    private function get_contract_transaction_types() : array {
        return [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Dostępne statusy umów.
     *
     * @return array<string,string>
     */
    private function get_contract_statuses() : array {
        return [
            'draft'  => __( 'Szkic', 'estate-office' ),
            'active' => __( 'Aktywna', 'estate-office' ),
            'closed' => __( 'Zakończona', 'estate-office' ),
            'paused' => __( 'Wstrzymana', 'estate-office' ),
        ];
    }

    /**
     * Mapuje status umowy.
     *
     * @param string $status Status.
     *
     * @return string
     */
    private function map_contract_status( string $status ) : string {
        $map = $this->get_contract_statuses();

        return $map[ $status ] ?? $status;
    }

    /**
     * Formatuje datę.
     *
     * @param string $date Data.
     *
     * @return string
     */
    private function format_date( string $date ) : string {
        if ( empty( $date ) ) {
            return '—';
        }

        return mysql2date( get_option( 'date_format' ), $date );
    }

    /**
     * Formatuje prowizję umowy.
     *
     * @param array<string,mixed> $contract Dane umowy.
     *
     * @return string
     */
    private function format_commission( array $contract ) : string {
        $amount = $contract['commission_amount'] ?? '';
        if ( '' === $amount || null === $amount ) {
            return '—';
        }

        $unit = strtoupper( $contract['commission_unit'] ?? '' );
        $value = number_format_i18n( (float) $amount, 2 );

        return $value . ' ' . $unit;
    }

    /**
     * Formatuje adres nieruchomości.
     *
     * @param array<string,mixed> $property Dane nieruchomości.
     *
     * @return string
     */
    private function format_property_address( array $property ) : string {
        $parts = array_filter(
            [
                $property['street'] ?? '',
                $property['street_number'] ?? '',
                $property['apartment_number'] ?? '',
                $property['postal_code'] ?? '',
                $property['city'] ?? '',
            ]
        );

        return implode( ' ', $parts );
    }

    /**
     * Podsumowanie pierwszej nieruchomości w umowie.
     *
     * @param int $contract_id ID umowy.
     *
     * @return array<string,string>
     */
    private function get_primary_property_summary( int $contract_id ) : array {
        $properties = $this->contract_repository->get_properties( $contract_id );
        if ( empty( $properties ) ) {
            return [
                'type'    => '—',
                'address' => '—',
            ];
        }

        $property = $properties[0];

        return [
            'type'    => $this->map_property_type( $property['property_type'] ?? '' ),
            'address' => $this->format_property_address( $property ),
        ];
    }

    /**
     * Formatuje nazwę klienta.
     *
     * @param array<string,mixed> $client Dane klienta.
     *
     * @return string
     */
    private function format_client_name( array $client ) : string {
        if ( 'firma' === $client['client_type'] ) {
            return $client['company_name'] ?: ( $client['representative_name'] ?? '' );
        }

        $parts = array_filter( [ $client['first_name'] ?? '', $client['last_name'] ?? '' ] );

        return implode( ' ', $parts );
    }

    /**
     * Formatuje adres klienta.
     *
     * @param array<string,mixed> $client Dane klienta.
     *
     * @return string
     */
    private function format_client_address( array $client ) : string {
        $parts = array_filter(
            [
                $client['address_street'] ?? '',
                $client['address_number'] ?? '',
                $client['address_unit'] ?? '',
                $client['address_postal_code'] ?? '',
                $client['address_city'] ?? '',
            ]
        );

        return implode( ' ', $parts );
    }

    /**
     * Formatuje adres korespondencyjny.
     *
     * @param array<string,mixed> $client Dane klienta.
     *
     * @return string
     */
    private function format_client_correspondence( array $client ) : string {
        if ( ! empty( $client['correspondence_same'] ) ) {
            return __( 'Taki jak adres główny', 'estate-office' );
        }

        $parts = array_filter(
            [
                $client['correspondence_street'] ?? '',
                $client['correspondence_number'] ?? '',
                $client['correspondence_unit'] ?? '',
                $client['correspondence_postal_code'] ?? '',
                $client['correspondence_city'] ?? '',
            ]
        );

        return implode( ' ', $parts );
    }

    /**
     * Style inline portalu.
     *
     * @return string
     */
    private function get_inline_styles() : string {
        return '.estate-office-crm-portal{max-width:1200px;margin:0 auto;padding:20px;background:#fff;border:1px solid #dcdcde;border-radius:8px;box-shadow:0 5px 15px rgba(0,0,0,0.05);}'
            . '.estate-office-crm-portal__header{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;}'
            . '.estate-office-crm-portal__primary{background:#2271b1;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;}'
            . '.estate-office-crm-portal__nav{display:flex;flex-wrap:wrap;gap:10px;margin:20px 0;}'
            . '.estate-office-crm-portal__nav a{padding:8px 14px;border:1px solid #dcdcde;border-radius:4px;text-decoration:none;color:#1d2327;}'
            . '.estate-office-crm-portal__nav a.is-active{background:#2271b1;color:#fff;border-color:#2271b1;}'
            . '.estate-office-crm-portal__body{margin-top:20px;}'
            . '.estate-office-crm-portal__search{display:flex;gap:10px;margin-bottom:15px;}'
            . '.estate-office-crm-portal__search input[type="search"]{flex:1;padding:8px;border:1px solid #dcdcde;border-radius:4px;}'
            . '.estate-office-crm-portal__search button{padding:8px 14px;border:none;background:#3858e9;color:#fff;border-radius:4px;}'
            . '.estate-office-crm-portal__table{width:100%;border-collapse:collapse;margin-bottom:20px;}'
            . '.estate-office-crm-portal__table th,.estate-office-crm-portal__table td{border:1px solid #e2e4e7;padding:8px;text-align:left;font-size:14px;}'
            . '.estate-office-crm-portal__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:15px;margin-bottom:20px;}'
            . '.estate-office-crm-portal__card{background:#f0f6fc;border:1px solid #d0e3f0;border-radius:6px;padding:16px;display:flex;flex-direction:column;gap:6px;}'
            . '.estate-office-crm-portal__card span{font-size:13px;color:#1d2327;}'
            . '.estate-office-crm-portal__card strong{font-size:24px;color:#1d2327;}'
            . '.estate-office-crm-portal__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:15px;}'
            . '.estate-office-crm-portal__panel{border:1px solid #dcdcde;border-radius:6px;padding:16px;background:#fafafa;}'
            . '.estate-office-crm-portal__panel h3,.estate-office-crm-portal__panel h2{margin-top:0;}'
            . '.estate-office-crm-portal__list{list-style:none;margin:0;padding:0;}'
            . '.estate-office-crm-portal__list li{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #ececec;font-size:14px;}'
            . '.estate-office-crm-portal__list li:last-child{border-bottom:none;}'
            . '.estate-office-crm-portal__timeline{list-style:none;margin:0;padding:0;}'
            . '.estate-office-crm-portal__timeline li{border-left:3px solid #2271b1;padding-left:12px;margin-left:10px;margin-bottom:12px;position:relative;}'
            . '.estate-office-crm-portal__timeline li:before{content:"";width:10px;height:10px;background:#2271b1;border-radius:50%;position:absolute;left:-6px;top:0;}'
            . '.estate-office-crm-portal__timeline strong{display:block;font-size:14px;}'
            . '.estate-office-crm-portal__timeline span{font-size:13px;color:#3858e9;}'
            . '.estate-office-crm-portal__timeline time{font-size:12px;color:#6c7781;display:block;margin-top:4px;}'
            . '.estate-office-crm-portal__detail{display:flex;flex-direction:column;gap:20px;}'
            . '.estate-office-crm-portal__detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;}'
            . '.estate-office-crm-portal__detail-description{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;}'
            . '.estate-office-crm-portal__detail-description p{margin-top:0;}'
            . '.estate-office-crm-portal__columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;}'
            . '.estate-office-crm-portal__back{align-self:flex-start;text-decoration:none;color:#2271b1;}'
            . '.estate-office-crm-portal__notice{padding:20px;border:1px solid #dcdcde;border-radius:6px;background:#fff;}';
    }

    /**
     * Ładuje ID strony portalu.
     *
     * @return int|null
     */
    private function load_portal_page_id() : ?int {
        $page_id = (int) get_option( 'estate_office_portal_page_id', 0 );

        return $page_id > 0 ? $page_id : null;
    }

    /**
     * Zwraca ID strony portalu.
     *
     * @return int|null
     */
    private function get_portal_page_id() : ?int {
        if ( ! $this->portal_page_id ) {
            $this->portal_page_id = $this->load_portal_page_id();
        }

        return $this->portal_page_id;
    }

    /**
     * Sprawdza, czy obecne żądanie dotyczy portalu.
     *
     * @return bool
     */
    private function is_portal_page() : bool {
        $page_id = $this->get_portal_page_id();
        if ( ! $page_id ) {
            return false;
        }

        return (int) get_queried_object_id() === $page_id;
    }

    /**
     * Permalink portalu.
     *
     * @return string
     */
    private function get_portal_permalink() : string {
        $page_id = $this->get_portal_page_id();
        if ( ! $page_id ) {
            return home_url( '/' );
        }

        $permalink = get_permalink( $page_id );

        return $permalink ?: home_url( '/' );
    }

    /**
     * Bazowy URL portalu.
     *
     * @return string
     */
    private function get_base_url() : string {
        return $this->get_portal_permalink();
    }

    /**
     * Aktywna zakładka.
     *
     * @return string
     */
    private function get_active_tab() : string {
        $tab    = isset( $_GET['crm_tab'] ) ? sanitize_key( wp_unslash( $_GET['crm_tab'] ) ) : 'dashboard';
        $allowed = [ 'dashboard', 'properties', 'searches', 'contracts', 'clients' ];

        return in_array( $tab, $allowed, true ) ? $tab : 'dashboard';
    }
    /**
     * Renderuje główne akcje w nagłówku.
     *
     * @param string $base_url Bazowy URL portalu.
     *
     * @return void
     */
    private function render_primary_actions( string $base_url ) : void {
        $actions = [
            [
                'label' => __( 'Dodaj nową umowę', 'estate-office' ),
                'url'   => add_query_arg( [ 'crm_tab' => 'contracts', 'view' => 'new-contract' ], $base_url ),
                'class' => 'is-primary',
            ],
            [
                'label' => __( 'Dodaj klienta', 'estate-office' ),
                'url'   => add_query_arg( [ 'crm_tab' => 'clients', 'view' => 'new-client' ], $base_url ),
                'class' => '',
            ],
            [
                'label' => __( 'Dodaj nieruchomość', 'estate-office' ),
                'url'   => add_query_arg( [ 'crm_tab' => 'properties', 'view' => 'new-property' ], $base_url ),
                'class' => '',
            ],
            [
                'label' => __( 'Dodaj poszukiwanie', 'estate-office' ),
                'url'   => add_query_arg( [ 'crm_tab' => 'searches', 'view' => 'new-search' ], $base_url ),
                'class' => '',
            ],
        ];

        echo '<div class="estate-office-crm-portal__actions">';
        foreach ( $actions as $action ) {
            printf(
                '<a class="estate-office-crm-portal__action %3$s" href="%1$s">%2$s</a>',
                esc_url( $action['url'] ),
                esc_html( $action['label'] ),
                esc_attr( $action['class'] )
            );
        }
        echo '</div>';
    }

    /**
     * Renderuje widok tworzenia nowego rekordu.
     *
     * @param string $view     Widok.
     * @param string $base_url Bazowy URL.
     *
     * @return string
     */
    private function render_creation_view( string $view, string $base_url ) : string {
        switch ( $view ) {
            case 'new-client':
                return $this->render_client_creation_form( $base_url );
            case 'new-property':
                return $this->render_property_creation_guidance( $base_url );
            case 'new-search':
                return $this->render_search_creation_guidance( $base_url );
            case 'new-contract':
                return $this->render_contract_creation_prompt( $base_url );
            default:
                return '<p>' . esc_html__( 'Nie znaleziono wskazanego widoku.', 'estate-office' ) . '</p>';
        }
    }

    /**
     * Formularz dodawania klienta na froncie.
     *
     * @param string $base_url Bazowy URL portalu.
     *
     * @return string
     */
    private function render_client_creation_form( string $base_url ) : string {
        $redirect = add_query_arg( [ 'crm_tab' => 'clients' ], $base_url );
        $message  = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $status   = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';

        $this->enqueue_portal_client_assets();

        ob_start();

        echo '<section class="estate-office-crm-portal__section is-active">';
        echo '<h2>' . esc_html__( 'Dodaj klienta', 'estate-office' ) . '</h2>';

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-client-form">';
        wp_nonce_field( 'estate-office-save-client' );
        echo '<input type="hidden" name="action" value="estate_office_save_client" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '" />';

        echo $this->get_client_form_fields_markup();

        echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Zapisz klienta', 'estate-office' ) . '</button>';
        echo ' <a class="button" href="' . esc_url( $redirect ) . '">' . esc_html__( 'Powrót', 'estate-office' ) . '</a></p>';
        echo '</form>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Informacje o rozpoczynaniu kreatora umowy.
     *
     * @param string $base_url Bazowy URL.
     *
     * @return string
     */
    private function render_contract_creation_prompt( string $base_url ) : string {
        $step        = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'details';
        $contract_id = isset( $_GET['contract_id'] ) ? absint( $_GET['contract_id'] ) : 0;
        $message     = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $status      = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';

        switch ( $step ) {
            case 'clients':
                return $this->render_contract_wizard_clients( $base_url, $contract_id, $message, $status );
            case 'property':
            case 'search':
                return $this->render_contract_wizard_stage_three( $base_url, $contract_id, $step, $message, $status );
            case 'details':
            default:
                return $this->render_contract_wizard_details( $base_url, $message, $status, $contract_id );
        }
    }

    /**
     * Etap 1 kreatora – dane umowy.
     *
     * @param string $base_url    Bazowy URL portalu.
     * @param string $message     Komunikat.
     * @param string $status      Status komunikatu.
     * @param int    $contract_id Opcjonalne ID umowy do edycji.
     *
     * @return string
     */
    private function render_contract_wizard_details( string $base_url, string $message, string $status, int $contract_id = 0 ) : string {
        $contract   = null;
        $is_edit    = false;
        $status     = $status ?: 'updated';
        $message    = $message ?: '';

        if ( $contract_id > 0 ) {
            $contract = $this->contract_repository->find( $contract_id );
            if ( $contract ) {
                $is_edit = true;
            } else {
                $message = $message ?: __( 'Nie znaleziono wskazanej umowy. Rozpocznij proces od nowa.', 'estate-office' );
                $status  = 'error';
                $contract_id = 0;
            }
        }

        $defaults = [
            'contract_number'   => $this->contract_repository->generate_contract_number(),
            'transaction_type'  => 'sprzedaz',
            'start_date'        => gmdate( 'Y-m-d' ),
            'end_date'          => '',
            'is_open_ended'     => 0,
            'commission_amount' => '',
            'commission_unit'   => '%',
            'status'            => 'draft',
        ];

        $data = wp_parse_args( $contract ?? [], $defaults );

        $redirect_args = [
            'crm_tab' => 'contracts',
            'view'    => 'new-contract',
            'step'    => 'clients',
        ];

        if ( $is_edit ) {
            $redirect_args['contract_id'] = $contract_id;
        }

        $redirect = add_query_arg( $redirect_args, $base_url );
        $list_url = add_query_arg( [ 'crm_tab' => 'contracts' ], $base_url );

        ob_start();

        echo '<section class="estate-office-crm-portal__section is-active estate-office-crm-portal__wizard" id="crm-contracts-step-details">';
        echo '<h2>' . esc_html( $is_edit ? __( 'Edytuj umowę', 'estate-office' ) : __( 'Nowa umowa – etap 1/3', 'estate-office' ) ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Uzupełnij numer, typ transakcji oraz podstawowe parametry umowy. Po zapisaniu przejdziesz do przypisywania klientów.', 'estate-office' ) . '</p>';

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-contract-form">';
        wp_nonce_field( 'estate-office-save-contract' );
        echo '<input type="hidden" name="action" value="estate_office_save_contract" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '" />';

        if ( $is_edit ) {
            echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
        }

        echo '<table class="form-table">';
        echo '<tr><th><label for="portal-contract-number">' . esc_html__( 'Numer umowy', 'estate-office' ) . '</label></th><td>';
        printf( '<input type="text" name="contract_number" id="portal-contract-number" value="%s" class="regular-text" required />', esc_attr( $data['contract_number'] ) );
        echo '</td></tr>';

        echo '<tr><th><label for="portal-transaction-type">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</label></th><td>';
        echo '<select name="transaction_type" id="portal-transaction-type">';
        foreach ( $this->get_contract_transaction_types() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $data['transaction_type'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Daty', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Data zawarcia', 'estate-office' ) . '<br /><input type="date" name="start_date" value="' . esc_attr( $data['start_date'] ) . '" required /></label></p>';
        echo '<p><label><input type="checkbox" id="portal-is-open-ended" name="is_open_ended" value="1"' . checked( (int) $data['is_open_ended'], 1, false ) . ' /> ' . esc_html__( 'Umowa bezterminowa', 'estate-office' ) . '</label></p>';
        $end_disabled = (int) $data['is_open_ended'] === 1 ? ' disabled' : '';
        echo '<p><label>' . esc_html__( 'Data zakończenia', 'estate-office' ) . '<br /><input type="date" id="portal-contract-end-date" name="end_date" value="' . esc_attr( $data['end_date'] ) . '"' . $end_disabled . ' /></label></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Prowizja', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Kwota', 'estate-office' ) . '<br /><input type="number" step="0.01" name="commission_amount" value="' . esc_attr( $data['commission_amount'] ) . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Jednostka', 'estate-office' ) . '<br /><select name="commission_unit">';
        foreach ( [ '%', 'PLN', 'EUR', 'USD' ] as $unit ) {
            echo '<option value="' . esc_attr( $unit ) . '"' . selected( $data['commission_unit'], $unit, false ) . '>' . esc_html( $unit ) . '</option>';
        }
        echo '</select></label></p>';
        echo '</td></tr>';

        echo '<tr><th><label for="portal-contract-status">' . esc_html__( 'Status umowy', 'estate-office' ) . '</label></th><td>';
        echo '<select name="status" id="portal-contract-status">';
        foreach ( $this->get_contract_statuses() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $data['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        $button_label = $is_edit ? __( 'Zapisz zmiany', 'estate-office' ) : __( 'Zapisz i przejdź do klientów', 'estate-office' );
        echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( $button_label ) . '</button>';
        echo ' <a class="button" href="' . esc_url( $list_url ) . '">' . esc_html__( 'Anuluj', 'estate-office' ) . '</a></p>';
        echo '</form>';

        echo '<script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kontrolowany skrypt inline.
        echo 'document.addEventListener("DOMContentLoaded",function(){const checkbox=document.getElementById("portal-is-open-ended");const endDate=document.getElementById("portal-contract-end-date");if(!checkbox||!endDate){return;}const toggle=()=>{const checked=checkbox.checked;endDate.disabled=checked;if(checked){endDate.value="";}};checkbox.addEventListener("change",toggle);toggle();});';
        echo '</script>';

        echo '<div class="estate-office-crm-portal__wizard-steps">';
        echo '<ol>';
        echo '<li><strong>' . esc_html__( 'Etap 1', 'estate-office' ) . ':</strong> ' . esc_html__( 'Dane umowy', 'estate-office' ) . '</li>';
        echo '<li>' . esc_html__( 'Etap 2: Klienci', 'estate-office' ) . '</li>';
        echo '<li>' . esc_html__( 'Etap 3: Nieruchomość lub poszukiwanie', 'estate-office' ) . '</li>';
        echo '</ol>';
        echo '</div>';

        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Etap 2 kreatora – przypisywanie klientów.
     *
     * @param string $base_url    Bazowy URL.
     * @param int    $contract_id ID umowy.
     * @param string $message     Komunikat.
     * @param string $status      Status komunikatu.
     *
     * @return string
     */
    private function render_contract_wizard_clients( string $base_url, int $contract_id, string $message, string $status ) : string {
        if ( $contract_id <= 0 ) {
            return $this->render_contract_wizard_details(
                $base_url,
                $message ?: __( 'Zanim przypiszesz klientów, zapisz dane umowy.', 'estate-office' ),
                $status ?: 'error'
            );
        }

        $contract = $this->contract_repository->find( $contract_id );
        if ( null === $contract ) {
            return $this->render_contract_wizard_details(
                $base_url,
                __( 'Nie znaleziono umowy. Utwórz ją ponownie.', 'estate-office' ),
                'error'
            );
        }

        $this->enqueue_portal_client_assets();

        $attached_clients = $this->contract_repository->get_clients( $contract_id );
        $search_term      = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
        $search_results   = $search_term ? $this->client_repository->search( $search_term, 15 ) : [];

        $wizard_args = [
            'crm_tab'     => 'contracts',
            'view'        => 'new-contract',
            'step'        => 'clients',
            'contract_id' => $contract_id,
        ];

        $redirect      = add_query_arg( $wizard_args, $base_url );
        $details_url   = add_query_arg( array_merge( $wizard_args, [ 'step' => 'details' ] ), $base_url );
        $transaction   = $contract['transaction_type'] ?? 'sprzedaz';
        $next_step     = in_array( $transaction, [ 'sprzedaz', 'wynajem' ], true ) ? 'property' : 'search';
        $next_url      = add_query_arg( array_merge( $wizard_args, [ 'step' => $next_step ] ), $base_url );

        ob_start();

        echo '<section class="estate-office-crm-portal__section is-active estate-office-crm-portal__wizard" id="crm-contracts-step-clients">';
        echo '<h2>' . esc_html__( 'Nowa umowa – etap 2/3', 'estate-office' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Przypisz klientów do umowy. Możesz wyszukać istniejących lub dodać nowego klienta bezpośrednio z poziomu kreatora.', 'estate-office' ) . '</p>';

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ?: 'updated' ), esc_html( $message ) );
        }

        echo '<div class="estate-office-crm-portal__summary">';
        echo '<p><strong>' . esc_html__( 'Umowa:', 'estate-office' ) . '</strong> ' . esc_html( $contract['contract_number'] ?? '' ) . ' · ' . esc_html( $this->map_transaction_type( $transaction ) ) . '</p>';
        echo '</div>';

        echo '<h3>' . esc_html__( 'Klienci przypisani do umowy', 'estate-office' ) . '</h3>';
        if ( empty( $attached_clients ) ) {
            echo '<p>' . esc_html__( 'Nie przypisano jeszcze żadnych klientów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Klient', 'estate-office' ) . '</th><th>' . esc_html__( 'Rola', 'estate-office' ) . '</th><th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th></tr></thead><tbody>';
            foreach ( $attached_clients as $client ) {
                $name      = 'person' === $client['client_type'] ? trim( ( $client['first_name'] ?? '' ) . ' ' . ( $client['last_name'] ?? '' ) ) : ( $client['company_name'] ?? '' );
                $remove_url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action'      => 'estate_office_contract_remove_client',
                            'contract_id' => $contract_id,
                            'client_id'   => $client['id'],
                            'redirect_to' => $redirect,
                        ],
                        admin_url( 'admin-post.php' )
                    ),
                    'estate-office-contract-remove-client-' . $contract_id . '-' . $client['id']
                );

                echo '<tr>';
                echo '<td>' . esc_html( $name ?: __( 'Bez nazwy', 'estate-office' ) ) . '</td>';
                echo '<td>' . esc_html( $client['role'] ?: __( 'Klient', 'estate-office' ) ) . '</td>';
                echo '<td><a href="' . esc_url( $remove_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Czy usunąć klienta z umowy?', 'estate-office' ) ) . '\');">' . esc_html__( 'Usuń powiązanie', 'estate-office' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<hr />';

        echo '<h3>' . esc_html__( 'Wyszukaj istniejącego klienta', 'estate-office' ) . '</h3>';
        echo '<form method="get" action="' . esc_url( $base_url ) . '" class="estate-office-inline-form estate-office-contract-client-search">';
        echo '<input type="hidden" name="crm_tab" value="contracts" />';
        echo '<input type="hidden" name="view" value="new-contract" />';
        echo '<input type="hidden" name="step" value="clients" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
        echo '<p><input type="search" name="term" value="' . esc_attr( $search_term ) . '" placeholder="' . esc_attr__( 'Imię, nazwisko, telefon lub e-mail', 'estate-office' ) . '" class="regular-text" />';
        echo ' <button type="submit" class="button">' . esc_html__( 'Szukaj', 'estate-office' ) . '</button>';
        if ( $search_term ) {
            echo ' <a class="button" href="' . esc_url( $redirect ) . '">' . esc_html__( 'Wyczyść', 'estate-office' ) . '</a>';
        }
        echo '</p>';
        echo '</form>';

        if ( $search_term ) {
            echo '<h4>' . esc_html__( 'Wyniki wyszukiwania', 'estate-office' ) . '</h4>';
            if ( empty( $search_results ) ) {
                echo '<p>' . esc_html__( 'Brak wyników dla podanej frazy.', 'estate-office' ) . '</p>';
            } else {
                echo '<ul class="estate-office-contract-client-results">';
                foreach ( $search_results as $client ) {
                    $name = 'person' === $client['client_type'] ? trim( ( $client['first_name'] ?? '' ) . ' ' . ( $client['last_name'] ?? '' ) ) : ( $client['company_name'] ?? '' );
                    echo '<li>';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-inline-form">';
                    wp_nonce_field( 'estate-office-contract-add-client' );
                    echo '<input type="hidden" name="action" value="estate_office_contract_add_client" />';
                    echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
                    echo '<input type="hidden" name="client_id" value="' . esc_attr( $client['id'] ) . '" />';
                    echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '" />';
                    echo esc_html( $name ?: __( 'Bez nazwy', 'estate-office' ) ) . ' – ' . esc_html( $client['email'] ?? '' ) . ' ';
                    echo '<select name="role">';
                    echo '<option value="">' . esc_html__( 'Klient', 'estate-office' ) . '</option>';
                    echo '<option value="sprzedajacy">' . esc_html__( 'Sprzedający', 'estate-office' ) . '</option>';
                    echo '<option value="kupujacy">' . esc_html__( 'Kupujący', 'estate-office' ) . '</option>';
                    echo '<option value="wynajmujacy">' . esc_html__( 'Wynajmujący', 'estate-office' ) . '</option>';
                    echo '<option value="najmujacy">' . esc_html__( 'Najmujący', 'estate-office' ) . '</option>';
                    echo '</select> ';
                    echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Przypisz klienta', 'estate-office' ) . '</button>';
                    echo '</form>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }

        echo '<hr />';
        echo '<h3>' . esc_html__( 'Dodaj nowego klienta', 'estate-office' ) . '</h3>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-client-form">';
        wp_nonce_field( 'estate-office-contract-add-client' );
        echo '<input type="hidden" name="action" value="estate_office_contract_add_client" />';
        echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
        echo '<input type="hidden" name="create_new_client" value="1" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '" />';
        echo $this->get_client_form_fields_markup( true );
        echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Zapisz klienta i przypisz', 'estate-office' ) . '</button></p>';
        echo '</form>';

        echo '<div class="estate-office-crm-portal__wizard-nav">';
        echo '<a class="button" href="' . esc_url( $details_url ) . '">' . esc_html__( 'Powrót do etapu 1', 'estate-office' ) . '</a> ';
        echo '<a class="button button-primary" href="' . esc_url( $next_url ) . '">' . esc_html__( 'Przejdź do etapu 3', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Etap 3 kreatora – dodanie nieruchomości lub poszukiwania.
     *
     * @param string $base_url    Bazowy URL.
     * @param int    $contract_id ID umowy.
     * @param string $step        Wybrany etap (property/search).
     * @param string $message     Komunikat.
     * @param string $status      Status komunikatu.
     *
     * @return string
     */
    private function render_contract_wizard_stage_three( string $base_url, int $contract_id, string $step, string $message, string $status ) : string {
        if ( $contract_id <= 0 ) {
            return $this->render_contract_wizard_details(
                $base_url,
                __( 'Najpierw zapisz umowę i przypisz klientów.', 'estate-office' ),
                'error'
            );
        }

        $contract = $this->contract_repository->find( $contract_id );
        if ( null === $contract ) {
            return $this->render_contract_wizard_details(
                $base_url,
                __( 'Nie znaleziono umowy. Utwórz ją ponownie.', 'estate-office' ),
                'error'
            );
        }

        $transaction   = $contract['transaction_type'] ?? 'sprzedaz';
        $expected_step = in_array( $transaction, [ 'sprzedaz', 'wynajem' ], true ) ? 'property' : 'search';
        $step          = in_array( $step, [ 'property', 'search' ], true ) ? $step : $expected_step;

        if ( $step !== $expected_step ) {
            $message = __( 'Wybrany etap nie odpowiada typowi transakcji. Przekierowano do właściwego kroku.', 'estate-office' );
            $status  = 'warning';
            $step    = $expected_step;
        }

        $wizard_args = [
            'crm_tab'     => 'contracts',
            'view'        => 'new-contract',
            'contract_id' => $contract_id,
        ];

        $clients_step = add_query_arg( array_merge( $wizard_args, [ 'step' => 'clients' ] ), $base_url );

        $clients = $this->contract_repository->get_clients( $contract_id );

        if ( 'property' === $step ) {
            $portal_link = add_query_arg(
                [
                    'crm_tab'        => 'properties',
                    'view'           => 'new-property',
                    'contract_id'    => $contract_id,
                    'transaction'    => $transaction,
                ],
                $base_url
            );
            $admin_link = add_query_arg(
                [
                    'page'              => 'estate-office-properties',
                    'action'            => 'new',
                    'wizard'            => 'contract',
                    'wizard_step'       => 'property',
                    'wizard_contract_id'=> $contract_id,
                ],
                admin_url( 'admin.php' )
            );
            $heading = __( 'Nowa umowa – etap 3/3: Dodaj nieruchomość', 'estate-office' );
            $intro   = __( 'Utwórz ofertę nieruchomości powiązaną z umową. Możesz skorzystać z uproszczonego formularza w portalu lub przejść do pełnego widoku w panelu administratora.', 'estate-office' );
        } else {
            $portal_link = add_query_arg(
                [
                    'crm_tab'        => 'searches',
                    'view'           => 'new-search',
                    'contract_id'    => $contract_id,
                    'transaction'    => $transaction,
                ],
                $base_url
            );
            $admin_link = add_query_arg(
                [
                    'page'              => 'estate-office-searches',
                    'action'            => 'new',
                    'wizard'            => 'contract',
                    'wizard_step'       => 'search',
                    'wizard_contract_id'=> $contract_id,
                ],
                admin_url( 'admin.php' )
            );
            $heading = __( 'Nowa umowa – etap 3/3: Dodaj poszukiwanie', 'estate-office' );
            $intro   = __( 'Określ kryteria poszukiwania dla klienta. Możesz skorzystać z szybkiego formularza w portalu lub przejść do pełnego widoku w panelu.', 'estate-office' );
        }

        ob_start();

        echo '<section class="estate-office-crm-portal__section is-active estate-office-crm-portal__wizard" id="crm-contracts-step-final">';
        echo '<h2>' . esc_html( $heading ) . '</h2>';
        echo '<p class="description">' . esc_html( $intro ) . '</p>';

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ?: 'updated' ), esc_html( $message ) );
        }

        echo '<div class="estate-office-crm-portal__summary">';
        echo '<p><strong>' . esc_html__( 'Umowa:', 'estate-office' ) . '</strong> ' . esc_html( $contract['contract_number'] ?? '' ) . ' · ' . esc_html( $this->map_transaction_type( $transaction ) ) . '</p>';
        if ( ! empty( $clients ) ) {
            echo '<p><strong>' . esc_html__( 'Przypisani klienci:', 'estate-office' ) . '</strong> ';
            $client_names = [];
            foreach ( $clients as $client ) {
                $client_names[] = 'person' === $client['client_type'] ? trim( ( $client['first_name'] ?? '' ) . ' ' . ( $client['last_name'] ?? '' ) ) : ( $client['company_name'] ?? '' );
            }
            echo esc_html( implode( ', ', array_filter( $client_names ) ) );
            echo '</p>';
        }
        echo '</div>';

        echo '<div class="estate-office-crm-portal__wizard-actions">';
        echo '<a class="button button-primary" href="' . esc_url( $portal_link ) . '">' . esc_html__( 'Otwórz formularz w portalu', 'estate-office' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $admin_link ) . '">' . esc_html__( 'Pełny formularz w panelu', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '<p>' . esc_html__( 'Po utworzeniu nieruchomości lub poszukiwania możesz wrócić do profilu umowy, aby monitorować dalszy przebieg współpracy.', 'estate-office' ) . '</p>';

        echo '<div class="estate-office-crm-portal__wizard-nav">';
        echo '<a class="button" href="' . esc_url( $clients_step ) . '">' . esc_html__( 'Wróć do klientów', 'estate-office' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( add_query_arg( [ 'crm_tab' => 'contracts' ], $base_url ) ) . '">' . esc_html__( 'Zakończ kreator', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Komunikat dla dodawania nieruchomości.
     *
     * @param string $base_url Bazowy URL.
     *
     * @return string
     */
    private function render_property_creation_guidance( string $base_url ) : string {
        $contract_url = add_query_arg( [ 'crm_tab' => 'contracts', 'view' => 'new-contract' ], $base_url );
        $admin_link   = admin_url( 'admin.php?page=estate-office-properties&action=new' );

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active">';
        echo '<h2>' . esc_html__( 'Dodawanie nieruchomości', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Nowa nieruchomość powinna zostać powiązana z umową w trzecim etapie kreatora. Rozpocznij od utworzenia lub wybrania umowy, a następnie przejdź do kroku dodawania nieruchomości.', 'estate-office' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( $contract_url ) . '">' . esc_html__( 'Przejdź do kreatora umowy', 'estate-office' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $admin_link ) . '">' . esc_html__( 'Otwórz pełny formularz w panelu', 'estate-office' ) . '</a></p>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Komunikat dla dodawania poszukiwania.
     *
     * @param string $base_url Bazowy URL.
     *
     * @return string
     */
    private function render_search_creation_guidance( string $base_url ) : string {
        $contract_url = add_query_arg( [ 'crm_tab' => 'contracts', 'view' => 'new-contract' ], $base_url );
        $admin_link   = admin_url( 'admin.php?page=estate-office-searches&action=new' );

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active">';
        echo '<h2>' . esc_html__( 'Dodawanie poszukiwania', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Poszukiwania klientów są częścią procesu obsługi umowy typu kupno lub najem. Użyj kreatora umowy, aby po przypisaniu klientów przejść do etapu tworzenia poszukiwania.', 'estate-office' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( $contract_url ) . '">' . esc_html__( 'Rozpocznij kreator umowy', 'estate-office' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $admin_link ) . '">' . esc_html__( 'Zaawansowany formularz w panelu', 'estate-office' ) . '</a></p>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Zwraca wspólne pola formularza klienta.
     *
     * @param bool $include_role_row Czy dodać wybór roli.
     *
     * @return string
     */
    private function get_client_form_fields_markup( bool $include_role_row = false ) : string {
        $doc_types = [
            ''             => __( 'Wybierz', 'estate-office' ),
            'dowod'        => __( 'Dowód osobisty', 'estate-office' ),
            'paszport'     => __( 'Paszport', 'estate-office' ),
            'karta_pobytu' => __( 'Karta pobytu', 'estate-office' ),
        ];

        ob_start();

        echo '<table class="form-table">';
        echo '<tr><th><label for="portal-client-type">' . esc_html__( 'Typ klienta', 'estate-office' ) . '</label></th><td>';
        echo '<select name="client_type" id="portal-client-type">';
        echo '<option value="person">' . esc_html__( 'Osoba fizyczna', 'estate-office' ) . '</option>';
        echo '<option value="company">' . esc_html__( 'Firma', 'estate-office' ) . '</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '<tr class="estate-office-client-type" data-type="person"><th>' . esc_html__( 'Imię i nazwisko', 'estate-office' ) . '</th><td>';
        echo '<input type="text" name="first_name" class="regular-text" placeholder="' . esc_attr__( 'Imię', 'estate-office' ) . '" /> ';
        echo '<input type="text" name="last_name" class="regular-text" placeholder="' . esc_attr__( 'Nazwisko', 'estate-office' ) . '" />';
        echo '</td></tr>';

        echo '<tr class="estate-office-client-type" data-type="company"><th>' . esc_html__( 'Dane firmy', 'estate-office' ) . '</th><td>';
        echo '<input type="text" name="company_name" class="regular-text" placeholder="' . esc_attr__( 'Nazwa firmy', 'estate-office' ) . '" />';
        echo '<p><input type="text" name="representative_name" class="regular-text" placeholder="' . esc_attr__( 'Imię i nazwisko reprezentanta', 'estate-office' ) . '" /></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Kontakt', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Telefon', 'estate-office' ) . '<br /><input type="text" name="phone" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'E-mail', 'estate-office' ) . '<br /><input type="email" name="email" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Strona WWW', 'estate-office' ) . '<br /><input type="url" name="website" class="regular-text" /></label></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Dane identyfikacyjne', 'estate-office' ) . '</th><td>';
        echo '<p><label>' . esc_html__( 'Typ dokumentu', 'estate-office' ) . '<br /><select name="document_type">';
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
        echo '<p><input type="text" name="address_street" class="regular-text" placeholder="' . esc_attr__( 'Ulica', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="address_number" class="regular-text" placeholder="' . esc_attr__( 'Numer', 'estate-office' ) . '" /> ';
        echo '<input type="text" name="address_unit" class="regular-text" placeholder="' . esc_attr__( 'Lokal', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="address_postal_code" class="regular-text" placeholder="' . esc_attr__( 'Kod pocztowy', 'estate-office' ) . '" /> ';
        echo '<input type="text" name="address_city" class="regular-text" placeholder="' . esc_attr__( 'Miasto', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="address_district" class="regular-text" placeholder="' . esc_attr__( 'Dzielnica', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="address_country" class="regular-text" placeholder="' . esc_attr__( 'Kraj', 'estate-office' ) . '" /></p>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Adres korespondencyjny', 'estate-office' ) . '</th><td>';
        echo '<label><input type="checkbox" name="correspondence_same" id="portal-correspondence-same" value="1" checked /> ' . esc_html__( 'Adres korespondencyjny taki sam jak zamieszkania', 'estate-office' ) . '</label>';
        echo '<div class="estate-office-correspondence-fields" style="display:none;">';
        echo '<p><input type="text" name="correspondence_street" class="regular-text" placeholder="' . esc_attr__( 'Ulica', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="correspondence_number" class="regular-text" placeholder="' . esc_attr__( 'Numer', 'estate-office' ) . '" /> ';
        echo '<input type="text" name="correspondence_unit" class="regular-text" placeholder="' . esc_attr__( 'Lokal', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="correspondence_postal_code" class="regular-text" placeholder="' . esc_attr__( 'Kod pocztowy', 'estate-office' ) . '" /> ';
        echo '<input type="text" name="correspondence_city" class="regular-text" placeholder="' . esc_attr__( 'Miasto', 'estate-office' ) . '" /></p>';
        echo '<p><input type="text" name="correspondence_country" class="regular-text" placeholder="' . esc_attr__( 'Kraj', 'estate-office' ) . '" /></p>';
        echo '</div>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__( 'Notatki', 'estate-office' ) . '</th><td>';
        echo '<textarea name="notes" rows="4" class="large-text"></textarea>';
        echo '</td></tr>';

        if ( $include_role_row ) {
            echo '<tr><th><label for="portal-client-role">' . esc_html__( 'Rola w umowie', 'estate-office' ) . '</label></th><td>';
            echo '<select name="role" id="portal-client-role">';
            echo '<option value="">' . esc_html__( 'Klient', 'estate-office' ) . '</option>';
            echo '<option value="sprzedajacy">' . esc_html__( 'Sprzedający', 'estate-office' ) . '</option>';
            echo '<option value="kupujacy">' . esc_html__( 'Kupujący', 'estate-office' ) . '</option>';
            echo '<option value="wynajmujacy">' . esc_html__( 'Wynajmujący', 'estate-office' ) . '</option>';
            echo '<option value="najmujacy">' . esc_html__( 'Najmujący', 'estate-office' ) . '</option>';
            echo '</select>';
            echo '</td></tr>';
        }

        echo '</table>';

        return (string) ob_get_clean();
    }

    /**
     * Dołącza skrypty formularza klienta na froncie.
     *
     * @return void
     */
    private function enqueue_portal_client_assets() : void {
        $handle = 'estate-office-portal-client-form';

        if ( wp_script_is( $handle, 'enqueued' ) ) {
            return;
        }

        wp_register_script( $handle, false, [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $handle );
        wp_add_inline_script(
            $handle,
            <<<'JS'
jQuery(function($){
    const typeField = $('#portal-client-type');
    const toggleType = () => {
        const type = typeField.val();
        $('.estate-office-client-type').hide();
        $('.estate-office-client-type[data-type="'+type+'"]').show();
    };
    typeField.on('change', toggleType);
    toggleType();

    const correspondence = $('#portal-correspondence-same');
    const toggleCorrespondence = () => {
        $('.estate-office-correspondence-fields').toggle(!correspondence.is(':checked'));
    };
    correspondence.on('change', toggleCorrespondence);
    toggleCorrespondence();
});
JS
        );
    }


}
