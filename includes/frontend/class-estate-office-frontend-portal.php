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
        echo '<a class="estate-office-crm-portal__primary" href="' . esc_url( admin_url( 'admin.php?page=estate-office-contracts&action=new' ) ) . '">' . esc_html__( 'Dodaj nową umowę', 'estate-office' ) . '</a>';
        echo '</div>';

        $this->render_navigation( $active_tab, $base_url );

        echo '<div class="estate-office-crm-portal__body">';
        if ( $view && $item_id ) {
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
        $search = isset( $_GET['properties_search'] ) ? sanitize_text_field( wp_unslash( $_GET['properties_search'] ) ) : '';

        $results = $this->property_repository->paginate(
            [
                'paged'    => 1,
                'per_page' => 10,
                'search'   => $search,
            ]
        );

        $items = $results['items'] ?? [];

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-properties">';
        echo $this->render_search_form( $base_url, 'properties', $search );

        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'Brak nieruchomości spełniających kryteria.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="estate-office-crm-portal__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Numer oferty', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Cena', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Cena za m²', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Metraż', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Liczba pokoi', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $items as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'properties',
                        'view'    => 'property',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $address_parts = array_filter(
                    [
                        $item['street'] ?? '',
                        $item['street_number'] ?? '',
                        $item['apartment_number'] ?? '',
                        $item['city'] ?? '',
                    ]
                );
                $address = implode( ' ', $address_parts );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $item['listing_number'] ?? '' ) . '</a></td>';
                echo '<td>' . esc_html( $address ) . '</td>';
                $currency = $item['price_currency'] ?? 'PLN';
                echo '<td>' . esc_html( $this->format_price( $item['price'] ?? null, '', $currency ) ) . '</td>';
                echo '<td>' . esc_html( $this->format_price( $item['price_per_sqm'] ?? null, __( 'm²', 'estate-office' ), $currency ) ) . '</td>';
                echo '<td>' . esc_html( $this->format_area( $item['area_total'] ?? null ) ) . '</td>';
                echo '<td>' . esc_html( $item['rooms'] ?? '—' ) . '</td>';
                echo '<td>' . esc_html__( 'Nie przypisano', 'estate-office' ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

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
        $search = isset( $_GET['searches_search'] ) ? sanitize_text_field( wp_unslash( $_GET['searches_search'] ) ) : '';

        $results = $this->search_repository->paginate(
            [
                'paged'    => 1,
                'per_page' => 10,
                'search'   => $search,
            ]
        );

        $items = $results['items'] ?? [];

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-searches">';
        echo $this->render_search_form( $base_url, 'searches', $search );

        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'Brak poszukiwań spełniających kryteria.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="estate-office-crm-portal__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Numer poszukiwania', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Budżet', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Lokalizacja', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $items as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'searches',
                        'view'    => 'search',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $location_parts = array_filter( [ $item['location_city'] ?? '', $item['location_district'] ?? '' ] );
                $budget         = $this->format_range( $item['price_min'] ?? null, $item['price_max'] ?? null );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $item['search_number'] ?? '' ) . '</a></td>';
                echo '<td>' . esc_html( $this->map_property_type( $item['property_type'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $budget ) . '</td>';
                echo '<td>' . esc_html( implode( ', ', $location_parts ) ) . '</td>';
                echo '<td>' . esc_html( $this->map_transaction_type( $item['transaction_type'] ?? '' ) ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

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
        $search = isset( $_GET['contracts_search'] ) ? sanitize_text_field( wp_unslash( $_GET['contracts_search'] ) ) : '';

        $results = $this->contract_repository->paginate(
            [
                'paged'    => 1,
                'per_page' => 10,
                'search'   => $search,
            ]
        );

        $items = $results['items'] ?? [];

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-contracts">';
        echo $this->render_search_form( $base_url, 'contracts', $search );

        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'Brak umów spełniających kryteria.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="estate-office-crm-portal__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Numer umowy', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Data zawarcia', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Data zakończenia', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Aktualny etap', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $items as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'contracts',
                        'view'    => 'contract',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $property_summary = $this->get_primary_property_summary( (int) $item['id'] );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $item['contract_number'] ?? '' ) . '</a></td>';
                echo '<td>' . esc_html( $this->map_transaction_type( $item['transaction_type'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $property_summary['type'] ) . '</td>';
                echo '<td>' . esc_html( $property_summary['address'] ) . '</td>';
                echo '<td>' . esc_html( $this->format_date( $item['start_date'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $this->format_date( $item['end_date'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $this->map_stage( $item['current_stage'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html__( 'Nie przypisano', 'estate-office' ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

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
        $search = isset( $_GET['clients_search'] ) ? sanitize_text_field( wp_unslash( $_GET['clients_search'] ) ) : '';

        $results = $this->client_repository->paginate(
            [
                'paged'    => 1,
                'per_page' => 10,
                'search'   => $search,
            ]
        );

        $items = $results['items'] ?? [];

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-clients">';
        echo $this->render_search_form( $base_url, 'clients', $search );

        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'Brak klientów spełniających kryteria.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="estate-office-crm-portal__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Imię i nazwisko/Nazwa', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Telefon', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'E-mail', 'estate-office' ) . '</th>';
            echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $items as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'clients',
                        'view'    => 'client',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $name    = $this->format_client_name( $item );
                $address = $this->format_client_address( $item );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $name ) . '</a></td>';
                echo '<td>' . esc_html( $address ) . '</td>';
                echo '<td>' . esc_html( $item['phone'] ?? '' ) . '</td>';
                $email = $item['email'] ?? '';
                if ( $email ) {
                    echo '<td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td>';
                } else {
                    echo '<td>—</td>';
                }
                echo '<td>' . esc_html__( 'Nie przypisano', 'estate-office' ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

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
    private function map_contract_status( string $status ) : string {
        $map = [
            'draft'  => __( 'Szkic', 'estate-office' ),
            'active' => __( 'Aktywna', 'estate-office' ),
            'closed' => __( 'Zakończona', 'estate-office' ),
            'paused' => __( 'Wstrzymana', 'estate-office' ),
        ];

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
}
