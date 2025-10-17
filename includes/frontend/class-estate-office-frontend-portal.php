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
        wp_enqueue_style(
            $style_handle,
            ESTATE_OFFICE_URL . 'assets/css/estate-office-portal.css',
            [],
            ESTATE_OFFICE_VERSION
        );

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
        $search        = isset( $_GET['properties_search'] ) ? sanitize_text_field( wp_unslash( $_GET['properties_search'] ) ) : '';
        $transaction   = isset( $_GET['properties_transaction'] ) ? sanitize_text_field( wp_unslash( $_GET['properties_transaction'] ) ) : '';
        $property_type = isset( $_GET['properties_type'] ) ? sanitize_text_field( wp_unslash( $_GET['properties_type'] ) ) : '';
        $paged         = isset( $_GET['properties_page'] ) ? max( 1, absint( $_GET['properties_page'] ) ) : 1;

        if ( $transaction ) {
            $transaction = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $transaction, 'UTF-8' ) : strtoupper( $transaction );
        }

        if ( $property_type ) {
            $property_type = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $property_type, 'UTF-8' ) : strtoupper( $property_type );
        }

        $query = $this->property_repository->paginate(
            [
                'paged'            => $paged,
                'per_page'         => 10,
                'search'           => $search,
                'transaction_type' => $transaction,
                'property_type'    => $property_type,
            ]
        );

        $transactions = $this->get_property_transaction_types();
        $types        = $this->get_property_type_options();
        $add_url      = add_query_arg(
            [
                'crm_tab' => 'properties',
                'view'    => 'new-property',
            ],
            $base_url
        );

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-properties">';
        echo '<div class="estate-office-crm-portal__section-header">';
        echo '<h2>' . esc_html__( 'Nieruchomości', 'estate-office' ) . '</h2>';
        echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Dodaj nieruchomość', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '<form class="estate-office-crm-portal__filters" method="get" action="' . esc_url( $base_url ) . '">';
        echo '<input type="hidden" name="crm_tab" value="properties" />';
        echo '<input type="search" name="properties_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Szukaj w tabeli…', 'estate-office' ) . '" />';
        echo '<select name="properties_transaction">';
        echo '<option value="">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</option>';
        foreach ( $transactions as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $transaction, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<select name="properties_type">';
        echo '<option value="">' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</option>';
        foreach ( $types as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $property_type, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Filtruj', 'estate-office' ) . '</button>';
        if ( $search || $transaction || $property_type ) {
            echo '<a class="estate-office-crm-portal__filters-reset" href="' . esc_url( add_query_arg( 'crm_tab', 'properties', $base_url ) ) . '">' . esc_html__( 'Wyczyść', 'estate-office' ) . '</a>';
        }
        echo '</form>';

        echo '<div class="estate-office-crm-portal__table-wrapper">';
        echo '<table class="estate-office-crm-portal__table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer oferty', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Cena', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Cena za m²', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Metraż', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Liczba pokoi', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="7">' . esc_html__( 'Brak nieruchomości spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'properties',
                        'view'    => 'property',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $price_display = $this->format_price( $item['price'] ?? null, '', $item['price_currency'] ?? 'PLN' );

                $price_sqm = $item['price_per_sqm'] ?? null;
                if ( ( null === $price_sqm || '' === $price_sqm ) && ! empty( $item['price'] ) && ! empty( $item['area_total'] ) ) {
                    $area = (float) $item['area_total'];
                    if ( $area > 0 ) {
                        $price_sqm = (float) $item['price'] / $area;
                    }
                }

                $price_sqm_display = ( null === $price_sqm || '' === $price_sqm )
                    ? '—'
                    : $this->format_price( $price_sqm, __( 'm²', 'estate-office' ), $item['price_currency'] ?? 'PLN' );

                $address = trim( $this->format_property_address( $item ) );
                if ( '' === $address ) {
                    $address = $item['city'] ?? '—';
                }

                $rooms = isset( $item['rooms'] ) && '' !== $item['rooms'] ? (string) (int) $item['rooms'] : '—';

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $item['listing_number'] ?? '' ) . '</a></td>';
                echo '<td>' . esc_html( $address ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $price_display ) . '</td>';
                echo '<td>' . esc_html( $price_sqm_display ) . '</td>';
                echo '<td>' . esc_html( $this->format_area( $item['area_total'] ?? null ) ) . '</td>';
                echo '<td>' . esc_html( $rooms ) . '</td>';
                echo '<td>' . esc_html__( '—', 'estate-office' ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $pagination = $this->render_pagination(
            (int) ( $query['total_page'] ?? 1 ),
            $paged,
            $base_url,
            [
                'crm_tab'               => 'properties',
                'properties_search'     => $search,
                'properties_transaction'=> $transaction,
                'properties_type'       => $property_type,
            ],
            'properties_page'
        );

        if ( $pagination ) {
            echo $pagination;
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
        $search        = isset( $_GET['searches_search'] ) ? sanitize_text_field( wp_unslash( $_GET['searches_search'] ) ) : '';
        $transaction   = isset( $_GET['searches_transaction'] ) ? sanitize_text_field( wp_unslash( $_GET['searches_transaction'] ) ) : '';
        $property_type = isset( $_GET['searches_type'] ) ? sanitize_text_field( wp_unslash( $_GET['searches_type'] ) ) : '';
        $paged         = isset( $_GET['searches_page'] ) ? max( 1, absint( $_GET['searches_page'] ) ) : 1;

        if ( $transaction ) {
            $transaction = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $transaction, 'UTF-8' ) : strtoupper( $transaction );
        }

        if ( $property_type ) {
            $property_type = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $property_type, 'UTF-8' ) : strtoupper( $property_type );
        }

        $query = $this->search_repository->paginate(
            [
                'paged'            => $paged,
                'per_page'         => 10,
                'search'           => $search,
                'transaction_type' => $transaction,
                'property_type'    => $property_type,
            ]
        );

        $transactions = $this->get_property_transaction_types();
        $types        = $this->get_property_type_options();
        $add_url      = add_query_arg(
            [
                'crm_tab' => 'searches',
                'view'    => 'new-search',
            ],
            $base_url
        );

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-searches">';
        echo '<div class="estate-office-crm-portal__section-header">';
        echo '<h2>' . esc_html__( 'Poszukiwania klientów', 'estate-office' ) . '</h2>';
        echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Dodaj poszukiwanie', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '<form class="estate-office-crm-portal__filters" method="get" action="' . esc_url( $base_url ) . '">';
        echo '<input type="hidden" name="crm_tab" value="searches" />';
        echo '<input type="search" name="searches_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Szukaj w tabeli…', 'estate-office' ) . '" />';
        echo '<select name="searches_transaction">';
        echo '<option value="">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</option>';
        foreach ( $transactions as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $transaction, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<select name="searches_type">';
        echo '<option value="">' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</option>';
        foreach ( $types as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $property_type, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Filtruj', 'estate-office' ) . '</button>';
        if ( $search || $transaction || $property_type ) {
            echo '<a class="estate-office-crm-portal__filters-reset" href="' . esc_url( add_query_arg( 'crm_tab', 'searches', $base_url ) ) . '">' . esc_html__( 'Wyczyść', 'estate-office' ) . '</a>';
        }
        echo '</form>';

        echo '<div class="estate-office-crm-portal__table-wrapper">';
        echo '<table class="estate-office-crm-portal__table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Numer poszukiwania', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Budżet', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Lokalizacja', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Brak poszukiwań spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'searches',
                        'view'    => 'search',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $budget = $this->format_range( $item['price_min'] ?? null, $item['price_max'] ?? null );
                if ( '—' !== $budget ) {
                    /* translators: suffix for currency symbol */
                    $budget .= ' ' . __( 'PLN', 'estate-office' );
                }

                $location = $this->format_search_location( $item );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $item['search_number'] ?? '' ) . '</a></td>';
                echo '<td>' . esc_html( $this->map_property_type( $item['property_type'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $budget ) . '</td>';
                echo '<td>' . esc_html( $location ) . '</td>';
                echo '<td>' . esc_html( $this->map_portal_transaction_label( $item['transaction_type'] ?? '' ) ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $pagination = $this->render_pagination(
            (int) ( $query['total_page'] ?? 1 ),
            $paged,
            $base_url,
            [
                'crm_tab'            => 'searches',
                'searches_search'    => $search,
                'searches_transaction'=> $transaction,
                'searches_type'      => $property_type,
            ],
            'searches_page'
        );

        if ( $pagination ) {
            echo $pagination;
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
        $search      = isset( $_GET['contracts_search'] ) ? sanitize_text_field( wp_unslash( $_GET['contracts_search'] ) ) : '';
        $transaction = isset( $_GET['contracts_transaction'] ) ? sanitize_key( wp_unslash( $_GET['contracts_transaction'] ) ) : '';
        $status      = isset( $_GET['contracts_status'] ) ? sanitize_key( wp_unslash( $_GET['contracts_status'] ) ) : '';
        $paged       = isset( $_GET['contracts_page'] ) ? max( 1, absint( $_GET['contracts_page'] ) ) : 1;

        $query = $this->contract_repository->paginate(
            [
                'paged'           => $paged,
                'per_page'        => 10,
                'search'          => $search,
                'transaction_type'=> $transaction,
                'status'          => $status,
            ]
        );

        $transactions = $this->get_contract_transaction_types();
        $statuses     = $this->get_contract_statuses();
        $add_url      = add_query_arg(
            [
                'crm_tab' => 'contracts',
                'view'    => 'new-contract',
            ],
            $base_url
        );

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-contracts">';
        echo '<div class="estate-office-crm-portal__section-header">';
        echo '<h2>' . esc_html__( 'Umowy', 'estate-office' ) . '</h2>';
        echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Dodaj umowę', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '<form class="estate-office-crm-portal__filters" method="get" action="' . esc_url( $base_url ) . '">';
        echo '<input type="hidden" name="crm_tab" value="contracts" />';
        echo '<input type="search" name="contracts_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Szukaj w tabeli…', 'estate-office' ) . '" />';
        echo '<select name="contracts_transaction">';
        echo '<option value="">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</option>';
        foreach ( $transactions as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $transaction, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<select name="contracts_status">';
        echo '<option value="">' . esc_html__( 'Status', 'estate-office' ) . '</option>';
        foreach ( $statuses as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $status, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Filtruj', 'estate-office' ) . '</button>';
        if ( $search || $transaction || $status ) {
            echo '<a class="estate-office-crm-portal__filters-reset" href="' . esc_url( add_query_arg( 'crm_tab', 'contracts', $base_url ) ) . '">' . esc_html__( 'Wyczyść', 'estate-office' ) . '</a>';
        }
        echo '</form>';

        echo '<div class="estate-office-crm-portal__table-wrapper">';
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
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="8">' . esc_html__( 'Brak umów spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'contracts',
                        'view'    => 'contract',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $asset = $this->resolve_contract_asset_links( (int) $item['id'], $base_url );
                $end   = ! empty( $item['is_open_ended'] ) ? esc_html__( 'Bezterminowa', 'estate-office' ) : $this->format_date( $item['end_date'] ?? '' );

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $item['contract_number'] ?? '' ) . '</a></td>';
                echo '<td>' . esc_html( $this->map_transaction_type( $item['transaction_type'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $asset['type'] ) . '</td>';
                if ( $asset['url'] ) {
                    echo '<td><a href="' . esc_url( $asset['url'] ) . '">' . esc_html( $asset['address'] ) . '</a></td>';
                } else {
                    echo '<td>' . esc_html( $asset['address'] ) . '</td>';
                }
                echo '<td>' . esc_html( $this->format_date( $item['start_date'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html( $end ) . '</td>';
                echo '<td>' . esc_html( $this->map_stage( $item['current_stage'] ?? '' ) ) . '</td>';
                echo '<td>' . esc_html__( '—', 'estate-office' ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $pagination = $this->render_pagination(
            (int) ( $query['total_page'] ?? 1 ),
            $paged,
            $base_url,
            [
                'crm_tab'             => 'contracts',
                'contracts_search'    => $search,
                'contracts_transaction'=> $transaction,
                'contracts_status'    => $status,
            ],
            'contracts_page'
        );

        if ( $pagination ) {
            echo $pagination;
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
        $search      = isset( $_GET['clients_search'] ) ? sanitize_text_field( wp_unslash( $_GET['clients_search'] ) ) : '';
        $client_type = isset( $_GET['clients_type'] ) ? sanitize_key( wp_unslash( $_GET['clients_type'] ) ) : '';
        $paged       = isset( $_GET['clients_page'] ) ? max( 1, absint( $_GET['clients_page'] ) ) : 1;

        $query = $this->client_repository->paginate(
            [
                'paged'      => $paged,
                'per_page'   => 10,
                'search'     => $search,
                'client_type'=> $client_type,
            ]
        );

        $add_url = add_query_arg(
            [
                'crm_tab' => 'clients',
                'view'    => 'new-client',
            ],
            $base_url
        );

        ob_start();
        echo '<section class="estate-office-crm-portal__section is-active" id="crm-clients">';
        echo '<div class="estate-office-crm-portal__section-header">';
        echo '<h2>' . esc_html__( 'Klienci', 'estate-office' ) . '</h2>';
        echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Dodaj klienta', 'estate-office' ) . '</a>';
        echo '</div>';

        echo '<form class="estate-office-crm-portal__filters" method="get" action="' . esc_url( $base_url ) . '">';
        echo '<input type="hidden" name="crm_tab" value="clients" />';
        echo '<input type="search" name="clients_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Szukaj w tabeli…', 'estate-office' ) . '" />';
        echo '<select name="clients_type">';
        echo '<option value="">' . esc_html__( 'Typ klienta', 'estate-office' ) . '</option>';
        $client_types = [
            'person'  => __( 'Osoba fizyczna', 'estate-office' ),
            'company' => __( 'Firma', 'estate-office' ),
        ];
        foreach ( $client_types as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $client_type, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Filtruj', 'estate-office' ) . '</button>';
        if ( $search || $client_type ) {
            echo '<a class="estate-office-crm-portal__filters-reset" href="' . esc_url( add_query_arg( 'crm_tab', 'clients', $base_url ) ) . '">' . esc_html__( 'Wyczyść', 'estate-office' ) . '</a>';
        }
        echo '</form>';

        echo '<div class="estate-office-crm-portal__table-wrapper">';
        echo '<table class="estate-office-crm-portal__table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Imię i nazwisko / Nazwa', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Adres', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Telefon', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'E-mail', 'estate-office' ) . '</th>';
        echo '<th>' . esc_html__( 'Opiekun', 'estate-office' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Brak klientów spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $detail_url = add_query_arg(
                    [
                        'crm_tab' => 'clients',
                        'view'    => 'client',
                        'item_id' => (int) $item['id'],
                    ],
                    $base_url
                );

                $address = $this->resolve_client_primary_address( $item, $base_url );
                $phone   = $item['phone'] ?? '';
                $email   = $item['email'] ?? '';

                echo '<tr>';
                echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( $this->format_client_name( $item ) ) . '</a></td>';
                if ( $address['url'] ) {
                    echo '<td><a href="' . esc_url( $address['url'] ) . '">' . esc_html( $address['address'] ) . '</a></td>';
                } else {
                    echo '<td>' . esc_html( $address['address'] ) . '</td>';
                }
                echo '<td>' . esc_html( $phone ?: '—' ) . '</td>';
                if ( $email ) {
                    echo '<td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td>';
                } else {
                    echo '<td>—</td>';
                }
                echo '<td>' . esc_html__( '—', 'estate-office' ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $pagination = $this->render_pagination(
            (int) ( $query['total_page'] ?? 1 ),
            $paged,
            $base_url,
            [
                'crm_tab'        => 'clients',
                'clients_search' => $search,
                'clients_type'   => $client_type,
            ],
            'clients_page'
        );

        if ( $pagination ) {
            echo $pagination;
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
     * Dostępne typy transakcji dla formularzy nieruchomości.
     *
     * @return array<string,string>
     */
    private function get_property_transaction_types() : array {
        return [
            'SPRZEDAŻ' => __( 'Sprzedaż', 'estate-office' ),
            'KUPNO'    => __( 'Kupno', 'estate-office' ),
            'WYNAJEM'  => __( 'Wynajem', 'estate-office' ),
            'NAJEM'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Dostępne typy nieruchomości dla formularzy.
     *
     * @return array<string,string>
     */
    private function get_property_type_options() : array {
        return [
            'MIESZKANIE' => __( 'Mieszkanie', 'estate-office' ),
            'DOM'        => __( 'Dom', 'estate-office' ),
            'DZIAŁKA'    => __( 'Działka', 'estate-office' ),
            'LOKAL'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];
    }

    /**
     * Lista umów dostępnych dla wyboru w formularzach.
     *
     * @return array<string,string>
     */
    private function get_contract_select_options() : array {
        $options = [ '0' => __( 'Brak powiązanej umowy', 'estate-office' ) ];

        $contracts = $this->contract_repository->paginate(
            [
                'paged'    => 1,
                'per_page' => 100,
            ]
        );

        if ( isset( $contracts['items'] ) && is_array( $contracts['items'] ) ) {
            foreach ( $contracts['items'] as $contract ) {
                $number = $contract['contract_number'] ?? ( '#' . ( $contract['id'] ?? '' ) );
                $label  = $number . ' · ' . $this->map_transaction_type( $contract['transaction_type'] ?? '' );
                $options[ (string) $contract['id'] ] = $label;
            }
        }

        return $options;
    }

    /**
     * Mapuje transakcję z umowy na format formularza nieruchomości/poszukiwania.
     *
     * @param string $transaction Typ transakcji z umowy.
     *
     * @return string
     */
    private function map_contract_transaction_to_property( string $transaction ) : string {
        $normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $transaction ) : strtolower( $transaction );
        $map        = [
            'sprzedaz' => 'SPRZEDAŻ',
            'kupno'    => 'KUPNO',
            'wynajem'  => 'WYNAJEM',
            'najem'    => 'NAJEM',
        ];

        return $map[ $normalized ] ?? '';
    }

    /**
     * Mapuje typ transakcji niezależnie od formatowania.
     *
     * @param string $type Typ transakcji.
     *
     * @return string
     */
    private function map_portal_transaction_label( string $type ) : string {
        if ( '' === $type ) {
            return '—';
        }

        $normalized = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $type, 'UTF-8' ) : strtoupper( $type );
        $map_upper  = $this->get_property_transaction_types();

        if ( isset( $map_upper[ $normalized ] ) ) {
            return $map_upper[ $normalized ];
        }

        $lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $type ) : strtolower( $type );
        $mapped = $this->map_transaction_type( $lower );

        return $mapped ?: $type;
    }

    /**
     * Formatuje lokalizację poszukiwania.
     *
     * @param array<string,mixed> $search Dane poszukiwania.
     *
     * @return string
     */
    private function format_search_location( array $search ) : string {
        $parts = array_filter( [ $search['location_city'] ?? '', $search['location_district'] ?? '' ] );

        if ( empty( $parts ) ) {
            return '—';
        }

        return implode( ', ', $parts );
    }

    /**
     * Renderuje paginację list portalu.
     *
     * @param int                  $total_pages Liczba stron.
     * @param int                  $current_page Bieżąca strona.
     * @param string               $base_url     Bazowy adres URL.
     * @param array<string,string> $query_args   Dodatkowe parametry.
     * @param string               $page_param   Nazwa parametru paginacji.
     *
     * @return string
     */
    private function render_pagination( int $total_pages, int $current_page, string $base_url, array $query_args, string $page_param ) : string {
        if ( $total_pages <= 1 ) {
            return '';
        }

        $base = add_query_arg( array_merge( $query_args, [ $page_param => '%#%' ] ), $base_url );
        $links = paginate_links(
            [
                'base'      => esc_url_raw( $base ),
                'format'    => '',
                'current'   => max( 1, $current_page ),
                'total'     => max( 1, $total_pages ),
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]
        );

        if ( empty( $links ) ) {
            return '';
        }

        return '<nav class="estate-office-crm-portal__pagination" aria-label="' . esc_attr__( 'Paginacja', 'estate-office' ) . '">' . wp_kses_post( $links ) . '</nav>';
    }

    /**
     * Zwraca skrót danych nieruchomości lub poszukiwania powiązanych z umową.
     *
     * @param int    $contract_id ID umowy.
     * @param string $base_url    Bazowy URL portalu.
     *
     * @return array<string,string>
     */
    private function resolve_contract_asset_links( int $contract_id, string $base_url ) : array {
        $properties = $this->contract_repository->get_properties( $contract_id );
        if ( ! empty( $properties ) ) {
            $property = $properties[0];
            $address  = trim( $this->format_property_address( $property ) );
            if ( '' === $address ) {
                $address = $property['city'] ?? '—';
            }

            return [
                'type'    => $this->map_property_type( $property['property_type'] ?? '' ),
                'address' => $address ?: '—',
                'url'     => add_query_arg(
                    [
                        'crm_tab' => 'properties',
                        'view'    => 'property',
                        'item_id' => (int) $property['id'],
                    ],
                    $base_url
                ),
            ];
        }

        $searches = $this->contract_repository->get_searches( $contract_id );
        if ( ! empty( $searches ) ) {
            $search = $searches[0];

            return [
                'type'    => $this->map_property_type( $search['property_type'] ?? '' ),
                'address' => $this->format_search_location( $search ),
                'url'     => add_query_arg(
                    [
                        'crm_tab' => 'searches',
                        'view'    => 'search',
                        'item_id' => (int) $search['id'],
                    ],
                    $base_url
                ),
            ];
        }

        return [
            'type'    => '—',
            'address' => '—',
            'url'     => '',
        ];
    }

    /**
     * Ustala adres klienta z powiązań lub danych własnych.
     *
     * @param array<string,mixed> $client   Dane klienta.
     * @param string              $base_url Bazowy URL portalu.
     *
     * @return array{address:string,url:string}
     */
    private function resolve_client_primary_address( array $client, string $base_url ) : array {
        $contracts = $this->client_repository->get_contracts_for_client( (int) $client['id'] );

        foreach ( $contracts as $contract ) {
            $asset = $this->resolve_contract_asset_links( (int) $contract['id'], $base_url );
            if ( '—' !== $asset['address'] ) {
                return [
                    'address' => $asset['address'],
                    'url'     => $asset['url'],
                ];
            }
        }

        $address = trim( $this->format_client_address( $client ) );
        if ( '' === $address ) {
            $address = '—';
        }

        return [
            'address' => $address,
            'url'     => '',
        ];
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
                return $this->render_property_creation_form( $base_url );
            case 'new-search':
                return $this->render_search_creation_form( $base_url );
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
     * Formularz dodawania nieruchomości w portalu.
     *
     * @param string $base_url Bazowy URL portalu.
     *
     * @return string
     */
    private function render_property_creation_form( string $base_url ) : string {
        $message          = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $status           = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';
        $contract_id      = isset( $_GET['contract_id'] ) ? absint( $_GET['contract_id'] ) : 0;
        $transaction      = isset( $_GET['transaction'] ) ? sanitize_key( wp_unslash( $_GET['transaction'] ) ) : '';
        $wizard           = isset( $_GET['wizard'] ) ? sanitize_key( wp_unslash( $_GET['wizard'] ) ) : '';
        $wizard_step      = isset( $_GET['wizard_step'] ) ? sanitize_key( wp_unslash( $_GET['wizard_step'] ) ) : '';
        $wizard_contract  = isset( $_GET['wizard_contract_id'] ) ? absint( $_GET['wizard_contract_id'] ) : 0;
        $in_wizard        = (
            'contract' === $wizard
            && $wizard_contract > 0
            && ( '' === $wizard_step || 'property' === $wizard_step )
        );

        if ( $in_wizard && 0 === $contract_id ) {
            $contract_id = $wizard_contract;
        }

        $contract = null;
        if ( $contract_id > 0 ) {
            $contract = $this->contract_repository->find( $contract_id );
            if ( $contract ) {
                $transaction = $contract['transaction_type'] ?? $transaction;
            } else {
                $contract_id = 0;
            }
        }

        $transaction_value = $this->map_contract_transaction_to_property( $transaction );
        if ( '' === $transaction_value ) {
            $transaction_value = 'SPRZEDAŻ';
        }

        $redirect_args = [ 'crm_tab' => 'properties', 'view' => 'property' ];
        if ( $in_wizard && $contract_id > 0 ) {
            $redirect_args = [
                'crm_tab'     => 'contracts',
                'view'        => 'new-contract',
                'step'        => 'property',
                'contract_id' => $contract_id,
            ];
        }

        $redirect   = add_query_arg( $redirect_args, $base_url );
        $types      = $this->get_property_type_options();
        $currencies = [ 'PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD' ];
        $contracts  = $this->get_contract_select_options();

        ob_start();

        echo '<section class="estate-office-crm-portal__section is-active estate-office-crm-portal__form" id="crm-property-create">';
        echo '<h2>' . esc_html__( 'Dodaj nieruchomość', 'estate-office' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Uzupełnij kluczowe informacje o ofercie, aby powiązać ją z aktywną umową i rozpocząć proces publikacji.', 'estate-office' ) . '</p>';
        if ( $in_wizard && $contract_id > 0 ) {
            echo '<p class="description">' . esc_html__( 'Po zapisaniu nieruchomości wrócisz do trzeciego etapu kreatora umowy.', 'estate-office' ) . '</p>';
        }

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-property-form">';
        wp_nonce_field( 'estate_office_save_property', 'estate_office_nonce' );
        echo '<input type="hidden" name="action" value="estate_office_save_property" />';
        echo '<input type="hidden" name="property_id" value="0" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '" />';
        if ( $in_wizard && $contract_id > 0 ) {
            echo '<input type="hidden" name="wizard" value="contract" />';
            echo '<input type="hidden" name="wizard_step" value="property" />';
            echo '<input type="hidden" name="wizard_contract_id" value="' . esc_attr( $contract_id ) . '" />';
        }

        if ( $contract ) {
            echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
            echo '<input type="hidden" name="transaction_type" value="' . esc_attr( $transaction_value ) . '" />';
            echo '<p class="notice notice-info"><strong>' . esc_html__( 'Powiązana umowa:', 'estate-office' ) . '</strong> ' . esc_html( $contract['contract_number'] ?? '' ) . ' · ' . esc_html( $this->map_transaction_type( $contract['transaction_type'] ?? '' ) ) . '</p>';
        } else {
            echo '<div class="estate-office-crm-portal__form-grid">';
            echo '<p><label for="portal-property-contract">' . esc_html__( 'Powiązana umowa', 'estate-office' ) . '<br />';
            echo '<select name="contract_id" id="portal-property-contract">';
            foreach ( $contracts as $key => $label ) {
                printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( (string) $contract_id, (string) $key, false ), esc_html( $label ) );
            }
            echo '</select></label></p>';
            echo '<p><label for="portal-property-transaction">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '<br />';
            echo '<select name="transaction_type" id="portal-property-transaction">';
            foreach ( $this->get_property_transaction_types() as $key => $label ) {
                printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $transaction_value, $key, false ), esc_html( $label ) );
            }
            echo '</select></label></p>';
            echo '</div>';
        }

        echo '<div class="estate-office-crm-portal__form-grid">';
        echo '<p><label for="portal-property-listing">' . esc_html__( 'Numer oferty', 'estate-office' ) . '<br /><input type="text" name="listing_number" id="portal-property-listing" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-title">' . esc_html__( 'Tytuł oferty', 'estate-office' ) . '<br /><input type="text" name="title" id="portal-property-title" class="regular-text" required /></label></p>';
        echo '<p><label for="portal-property-type">' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '<br /><select name="property_type" id="portal-property-type">';
        foreach ( $types as $key => $label ) {
            printf( '<option value="%1$s">%2$s</option>', esc_attr( $key ), esc_html( $label ) );
        }
        echo '</select></label></p>';
        echo '<p><label for="portal-property-price">' . esc_html__( 'Cena', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="price" id="portal-property-price" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-currency">' . esc_html__( 'Waluta', 'estate-office' ) . '<br /><select name="price_currency" id="portal-property-currency">';
        foreach ( $currencies as $code => $label ) {
            printf( '<option value="%1$s">%2$s</option>', esc_attr( $code ), esc_html( $label ) );
        }
        echo '</select></label></p>';
        echo '<p><label for="portal-property-rent">' . esc_html__( 'Czynsz administracyjny', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="administrative_rent" id="portal-property-rent" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-area">' . esc_html__( 'Powierzchnia (m²)', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="area_total" id="portal-property-area" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-rooms">' . esc_html__( 'Liczba pokoi', 'estate-office' ) . '<br /><input type="number" min="0" name="rooms" id="portal-property-rooms" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-year">' . esc_html__( 'Rok budowy', 'estate-office' ) . '<br /><input type="number" min="1800" max="' . esc_attr( gmdate( 'Y' ) ) . '" name="year_built" id="portal-property-year" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-floor">' . esc_html__( 'Piętro', 'estate-office' ) . '<br /><input type="number" min="-2" max="60" name="floor" id="portal-property-floor" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-floors">' . esc_html__( 'Liczba pięter budynku', 'estate-office' ) . '<br /><input type="number" min="0" name="total_floors" id="portal-property-floors" class="regular-text" /></label></p>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__form-grid">';
        echo '<p><label for="portal-property-ownership">' . esc_html__( 'Stan prawny', 'estate-office' ) . '<br /><input type="text" name="ownership_status" id="portal-property-ownership" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-period">' . esc_html__( 'Okres rozliczeniowy', 'estate-office' ) . '<br /><input type="text" name="price_period" id="portal-property-period" class="regular-text" placeholder="' . esc_attr__( 'np. miesięcznie', 'estate-office' ) . '" /></label></p>';
        echo '<p><label for="portal-property-bedrooms">' . esc_html__( 'Liczba sypialni', 'estate-office' ) . '<br /><input type="number" min="0" name="bedrooms" id="portal-property-bedrooms" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-bathrooms">' . esc_html__( 'Liczba łazienek', 'estate-office' ) . '<br /><input type="number" min="0" name="bathrooms" id="portal-property-bathrooms" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-toilets">' . esc_html__( 'Liczba toalet', 'estate-office' ) . '<br /><input type="number" min="0" name="toilets" id="portal-property-toilets" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-area-plot">' . esc_html__( 'Powierzchnia działki (m²)', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="area_plot" id="portal-property-area-plot" class="regular-text" /></label></p>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__form-grid">';
        echo '<p><label for="portal-property-street">' . esc_html__( 'Ulica', 'estate-office' ) . '<br /><input type="text" name="street" id="portal-property-street" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-number">' . esc_html__( 'Numer', 'estate-office' ) . '<br /><input type="text" name="street_number" id="portal-property-number" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-apartment">' . esc_html__( 'Lokal', 'estate-office' ) . '<br /><input type="text" name="apartment_number" id="portal-property-apartment" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-postal">' . esc_html__( 'Kod pocztowy', 'estate-office' ) . '<br /><input type="text" name="postal_code" id="portal-property-postal" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-district">' . esc_html__( 'Dzielnica', 'estate-office' ) . '<br /><input type="text" name="district" id="portal-property-district" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-city">' . esc_html__( 'Miasto', 'estate-office' ) . '<br /><input type="text" name="city" id="portal-property-city" class="regular-text" required /></label></p>';
        echo '</div>';

        echo '<p><label for="portal-property-description">' . esc_html__( 'Opis nieruchomości', 'estate-office' ) . '<br /><textarea name="description" id="portal-property-description" rows="6" class="large-text"></textarea></label></p>';

        echo '<div class="estate-office-crm-portal__form-grid">';
        echo '<p><label for="portal-property-video">' . esc_html__( 'Link do filmu', 'estate-office' ) . '<br /><input type="url" name="video_url" id="portal-property-video" class="regular-text" /></label></p>';
        echo '<p><label for="portal-property-tour">' . esc_html__( 'Link do wirtualnego spaceru', 'estate-office' ) . '<br /><input type="url" name="virtual_tour_url" id="portal-property-tour" class="regular-text" /></label></p>';
        echo '</div>';

        echo '<fieldset class="estate-office-crm-portal__form-fieldset">';
        echo '<legend>' . esc_html__( 'Opcje publikacji', 'estate-office' ) . '</legend>';
        $flags = [
            'export_web'      => __( 'Eksport na WWW', 'estate-office' ),
            'export_portals'  => __( 'Eksport na portale', 'estate-office' ),
            'new_offer'       => __( 'Nowa oferta', 'estate-office' ),
            'exclusive_offer' => __( 'Wyłączność', 'estate-office' ),
            'new_price'       => __( 'Nowa cena', 'estate-office' ),
            'commission_free' => __( 'Bez prowizji', 'estate-office' ),
            'mls_offer'       => __( 'Oferta MLS', 'estate-office' ),
            'premium_offer'   => __( 'Premium', 'estate-office' ),
        ];
        foreach ( $flags as $name => $label ) {
            printf( '<label><input type="checkbox" name="%1$s" value="1" /> %2$s</label>', esc_attr( $name ), esc_html( $label ) );
        }
        echo '</fieldset>';

        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Zapisz nieruchomość', 'estate-office' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( add_query_arg( 'crm_tab', 'properties', $base_url ) ) . '">' . esc_html__( 'Anuluj', 'estate-office' ) . '</a>';
        echo '</p>';

        echo '</form>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    /**
     * Formularz dodawania poszukiwania w portalu.
     *
     * @param string $base_url Bazowy URL portalu.
     *
     * @return string
     */
    private function render_search_creation_form( string $base_url ) : string {
        $message          = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $status           = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';
        $contract_id      = isset( $_GET['contract_id'] ) ? absint( $_GET['contract_id'] ) : 0;
        $transaction      = isset( $_GET['transaction'] ) ? sanitize_key( wp_unslash( $_GET['transaction'] ) ) : '';
        $wizard           = isset( $_GET['wizard'] ) ? sanitize_key( wp_unslash( $_GET['wizard'] ) ) : '';
        $wizard_step      = isset( $_GET['wizard_step'] ) ? sanitize_key( wp_unslash( $_GET['wizard_step'] ) ) : '';
        $wizard_contract  = isset( $_GET['wizard_contract_id'] ) ? absint( $_GET['wizard_contract_id'] ) : 0;
        $in_wizard        = (
            'contract' === $wizard
            && $wizard_contract > 0
            && ( '' === $wizard_step || 'search' === $wizard_step )
        );

        if ( $in_wizard && 0 === $contract_id ) {
            $contract_id = $wizard_contract;
        }

        $contract = null;
        if ( $contract_id > 0 ) {
            $contract = $this->contract_repository->find( $contract_id );
            if ( $contract ) {
                $transaction = $contract['transaction_type'] ?? $transaction;
            } else {
                $contract_id = 0;
            }
        }

        $transaction_value = $this->map_contract_transaction_to_property( $transaction );
        if ( '' === $transaction_value ) {
            $transaction_value = 'KUPNO';
        }

        $redirect_args = [ 'crm_tab' => 'searches', 'view' => 'search' ];
        if ( $in_wizard && $contract_id > 0 ) {
            $redirect_args = [
                'crm_tab'     => 'contracts',
                'view'        => 'new-contract',
                'step'        => 'search',
                'contract_id' => $contract_id,
            ];
        }

        $redirect  = add_query_arg( $redirect_args, $base_url );
        $types     = $this->get_property_type_options();
        $contracts = $this->get_contract_select_options();

        ob_start();

        echo '<section class="estate-office-crm-portal__section is-active estate-office-crm-portal__form" id="crm-search-create">';
        echo '<h2>' . esc_html__( 'Dodaj poszukiwanie', 'estate-office' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Zapisz kryteria, których poszukuje Twój klient, aby łatwiej dopasować ofertę.', 'estate-office' ) . '</p>';
        if ( $in_wizard && $contract_id > 0 ) {
            echo '<p class="description">' . esc_html__( 'Po zapisaniu poszukiwania wrócisz do trzeciego etapu kreatora umowy.', 'estate-office' ) . '</p>';
        }

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-search-form">';
        wp_nonce_field( 'estate_office_save_search', 'estate_office_nonce' );
        echo '<input type="hidden" name="action" value="estate_office_save_search" />';
        echo '<input type="hidden" name="search_id" value="0" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '" />';
        if ( $in_wizard && $contract_id > 0 ) {
            echo '<input type="hidden" name="wizard" value="contract" />';
            echo '<input type="hidden" name="wizard_step" value="search" />';
            echo '<input type="hidden" name="wizard_contract_id" value="' . esc_attr( $contract_id ) . '" />';
        }

        if ( $contract ) {
            echo '<input type="hidden" name="contract_id" value="' . esc_attr( $contract_id ) . '" />';
            echo '<input type="hidden" name="transaction_type" value="' . esc_attr( $transaction_value ) . '" />';
            echo '<p class="notice notice-info"><strong>' . esc_html__( 'Powiązana umowa:', 'estate-office' ) . '</strong> ' . esc_html( $contract['contract_number'] ?? '' ) . ' · ' . esc_html( $this->map_transaction_type( $contract['transaction_type'] ?? '' ) ) . '</p>';
        } else {
            echo '<div class="estate-office-crm-portal__form-grid">';
            echo '<p><label for="portal-search-contract">' . esc_html__( 'Powiązana umowa', 'estate-office' ) . '<br />';
            echo '<select name="contract_id" id="portal-search-contract">';
            foreach ( $contracts as $key => $label ) {
                printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( (string) $contract_id, (string) $key, false ), esc_html( $label ) );
            }
            echo '</select></label></p>';
            echo '<p><label for="portal-search-transaction">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '<br />';
            echo '<select name="transaction_type" id="portal-search-transaction">';
            foreach ( $this->get_property_transaction_types() as $key => $label ) {
                printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $transaction_value, $key, false ), esc_html( $label ) );
            }
            echo '</select></label></p>';
            echo '</div>';
        }

        echo '<div class="estate-office-crm-portal__form-grid">';
        echo '<p><label for="portal-search-number">' . esc_html__( 'Numer poszukiwania', 'estate-office' ) . '<br /><input type="text" name="search_number" id="portal-search-number" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-type">' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '<br /><select name="property_type" id="portal-search-type">';
        foreach ( $types as $key => $label ) {
            printf( '<option value="%1$s">%2$s</option>', esc_attr( $key ), esc_html( $label ) );
        }
        echo '</select></label></p>';
        echo '<p><label for="portal-search-price-min">' . esc_html__( 'Budżet od', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="price_min" id="portal-search-price-min" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-price-max">' . esc_html__( 'Budżet do', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="price_max" id="portal-search-price-max" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-area-min">' . esc_html__( 'Metraż od', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="area_min" id="portal-search-area-min" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-area-max">' . esc_html__( 'Metraż do', 'estate-office' ) . '<br /><input type="number" step="0.01" min="0" name="area_max" id="portal-search-area-max" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-rooms-min">' . esc_html__( 'Liczba pokoi od', 'estate-office' ) . '<br /><input type="number" min="0" name="rooms_min" id="portal-search-rooms-min" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-rooms-max">' . esc_html__( 'Liczba pokoi do', 'estate-office' ) . '<br /><input type="number" min="0" name="rooms_max" id="portal-search-rooms-max" class="regular-text" /></label></p>';
        echo '</div>';

        echo '<div class="estate-office-crm-portal__form-grid">';
        echo '<p><label for="portal-search-city">' . esc_html__( 'Miasto', 'estate-office' ) . '<br /><input type="text" name="location_city" id="portal-search-city" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-district">' . esc_html__( 'Dzielnica', 'estate-office' ) . '<br /><input type="text" name="location_district" id="portal-search-district" class="regular-text" /></label></p>';
        echo '<p><label for="portal-search-keywords">' . esc_html__( 'Słowa kluczowe', 'estate-office' ) . '<br /><input type="text" name="location_keywords" id="portal-search-keywords" class="regular-text" /></label></p>';
        echo '</div>';

        echo '<p><label for="portal-search-description">' . esc_html__( 'Opis poszukiwania', 'estate-office' ) . '<br /><textarea name="description" id="portal-search-description" rows="6" class="large-text"></textarea></label></p>';

        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Zapisz poszukiwanie', 'estate-office' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( add_query_arg( 'crm_tab', 'searches', $base_url ) ) . '">' . esc_html__( 'Anuluj', 'estate-office' ) . '</a>';
        echo '</p>';

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
                    'wizard'         => 'contract',
                    'wizard_step'    => 'property',
                    'wizard_contract_id' => $contract_id,
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
                    'wizard'         => 'contract',
                    'wizard_step'    => 'search',
                    'wizard_contract_id' => $contract_id,
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
