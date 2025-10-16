<?php
/**
 * Pulpit administratora EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Admin_Dashboard_Page
 */
class Estate_Office_Admin_Dashboard_Page {

    private const PAGE_HOOK = 'toplevel_page_estate-office-crm';

    /**
     * Serwis zbierający dane do pulpitu.
     *
     * @var Estate_Office_Dashboard_Service
     */
    private Estate_Office_Dashboard_Service $service;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Dashboard_Service|null $service Serwis danych.
     */
    public function __construct( ?Estate_Office_Dashboard_Service $service = null ) {
        $this->service = $service ?? new Estate_Office_Dashboard_Service();
    }

    /**
     * Rejestruje hooki pulpitu.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Ładuje zasoby wizualne pulpitu.
     *
     * @param string $hook Aktualny hook strony.
     *
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        if ( self::PAGE_HOOK !== $hook ) {
            return;
        }

        $style_handle = 'estate-office-admin-dashboard';
        wp_register_style( $style_handle, false, [], ESTATE_OFFICE_VERSION );
        wp_enqueue_style( $style_handle );
        wp_add_inline_style(
            $style_handle,
            $this->get_inline_styles()
        );
    }

    /**
     * Renderuje ekran pulpitu.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'view_estate_office_dashboard' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do podglądu pulpitu.', 'estate-office' ) );
        }

        $summary       = $this->service->get_summary();
        $top_agents    = $this->service->get_best_agents();
        $recent_stages = $this->service->get_recent_stages();
        $recent_contracts = $this->service->get_recent_contracts();

        echo '<div class="wrap estate-office-dashboard">';
        echo '<h1>' . esc_html__( 'Estate Office CRM – Pulpit', 'estate-office' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Szybki podgląd aktywności i postępów w CRM.', 'estate-office' ) . '</p>';

        $this->render_summary_cards( $summary );
        $this->render_secondary_sections( $top_agents, $recent_stages, $recent_contracts );

        echo '</div>';
    }

    /**
     * Renderuje skrócone metryki.
     *
     * @param array<string,int> $summary Dane podsumowania.
     *
     * @return void
     */
    private function render_summary_cards( array $summary ) : void {
        $cards = [
            [
                'label' => __( 'Nieruchomości', 'estate-office' ),
                'value' => $summary['properties_total'] ?? 0,
                'description' => __( 'Łączna liczba ofert w CRM.', 'estate-office' ),
            ],
            [
                'label' => __( 'Aktywne umowy', 'estate-office' ),
                'value' => $summary['contracts_active'] ?? 0,
                'description' => __( 'Umowy w statusie aktywnym.', 'estate-office' ),
            ],
            [
                'label' => __( 'Poszukiwania', 'estate-office' ),
                'value' => $summary['searches_total'] ?? 0,
                'description' => __( 'Aktywne zlecenia poszukiwań.', 'estate-office' ),
            ],
            [
                'label' => __( 'Klienci', 'estate-office' ),
                'value' => $summary['clients_total'] ?? 0,
                'description' => __( 'Liczba klientów w bazie.', 'estate-office' ),
            ],
        ];

        echo '<div class="estate-office-dashboard__cards">';
        foreach ( $cards as $card ) {
            echo '<div class="estate-office-dashboard__card">';
            echo '<span class="estate-office-dashboard__card-label">' . esc_html( $card['label'] ) . '</span>';
            echo '<span class="estate-office-dashboard__card-value">' . esc_html( number_format_i18n( (int) $card['value'] ) ) . '</span>';
            echo '<span class="estate-office-dashboard__card-desc">' . esc_html( $card['description'] ) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Renderuje sekcje dodatkowe: agenci, aktywności, umowy.
     *
     * @param array<int,array<string,mixed>> $top_agents      Ranking agentów.
     * @param array<int,array<string,mixed>> $recent_stages   Ostatnie aktywności.
     * @param array<int,array<string,mixed>> $recent_contracts Nowe umowy.
     *
     * @return void
     */
    private function render_secondary_sections( array $top_agents, array $recent_stages, array $recent_contracts ) : void {
        echo '<div class="estate-office-dashboard__grid">';

        echo '<div class="estate-office-dashboard__panel">';
        echo '<h2>' . esc_html__( 'Najaktywniejsi agenci', 'estate-office' ) . '</h2>';
        if ( empty( $top_agents ) ) {
            echo '<p>' . esc_html__( 'Brak danych do wyświetlenia. Zacznij rejestrować aktywności umów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-dashboard__list">';
            foreach ( $top_agents as $agent ) {
                $name  = $agent['name'] ?? '';
                $count = isset( $agent['activity_count'] ) ? (int) $agent['activity_count'] : 0;
                echo '<li><strong>' . esc_html( $name ) . '</strong><span>' . esc_html( sprintf( /* translators: %d: liczba aktywności */ _n( '%d aktywność', '%d aktywności', $count, 'estate-office' ), $count ) ) . '</span></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="estate-office-dashboard__panel">';
        echo '<h2>' . esc_html__( 'Ostatnie etapy umów', 'estate-office' ) . '</h2>';
        if ( empty( $recent_stages ) ) {
            echo '<p>' . esc_html__( 'Brak zarejestrowanych zmian etapów.', 'estate-office' ) . '</p>';
        } else {
            echo '<ul class="estate-office-dashboard__timeline">';
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

        echo '<div class="estate-office-dashboard__panel">';
        echo '<h2>' . esc_html__( 'Najnowsze umowy', 'estate-office' ) . '</h2>';
        if ( empty( $recent_contracts ) ) {
            echo '<p>' . esc_html__( 'Brak utworzonych umów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Numer', 'estate-office' ) . '</th><th>' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th><th>' . esc_html__( 'Status', 'estate-office' ) . '</th><th>' . esc_html__( 'Data', 'estate-office' ) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $recent_contracts as $contract ) {
                echo '<tr>';
                echo '<td>' . esc_html( $contract['contract_number'] ?? '' ) . '</td>';
                echo '<td>' . esc_html( $contract['transaction_label'] ?? '' ) . '</td>';
                echo '<td>' . esc_html( $contract['status_label'] ?? '' ) . '</td>';
                echo '<td>' . esc_html( $contract['created_human'] ?? '' ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Definiuje style inline dla pulpitu.
     *
     * @return string
     */
    private function get_inline_styles() : string {
        return '.estate-office-dashboard__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-top:20px;margin-bottom:20px;}'
            . '.estate-office-dashboard__card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:8px;}'
            . '.estate-office-dashboard__card-label{font-size:13px;text-transform:uppercase;color:#50575e;letter-spacing:.03em;}'
            . '.estate-office-dashboard__card-value{font-size:28px;font-weight:600;color:#1d2327;}'
            . '.estate-office-dashboard__card-desc{font-size:12px;color:#6c7781;}'
            . '.estate-office-dashboard__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;}'
            . '.estate-office-dashboard__panel{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,0.05);}'
            . '.estate-office-dashboard__panel h2{margin-top:0;font-size:18px;}'
            . '.estate-office-dashboard__list{list-style:none;margin:0;padding:0;}'
            . '.estate-office-dashboard__list li{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f1;font-size:14px;}'
            . '.estate-office-dashboard__list li:last-child{border-bottom:none;}'
            . '.estate-office-dashboard__timeline{list-style:none;margin:0;padding:0;}'
            . '.estate-office-dashboard__timeline li{border-left:2px solid #dcdcde;margin-left:10px;padding-left:12px;margin-bottom:14px;position:relative;}'
            . '.estate-office-dashboard__timeline li:before{content:"";width:10px;height:10px;background:#2271b1;border-radius:50%;position:absolute;left:-16px;top:4px;}'
            . '.estate-office-dashboard__timeline strong{display:block;font-size:14px;color:#1d2327;}'
            . '.estate-office-dashboard__timeline span{display:block;font-size:13px;color:#3858e9;margin-top:2px;}'
            . '.estate-office-dashboard__timeline em{display:block;font-size:12px;color:#6c7781;font-style:normal;}'
            . '.estate-office-dashboard__timeline time{display:block;font-size:12px;color:#6c7781;margin-top:4px;}'
            . '.estate-office-dashboard__panel table{width:100%;}'
            . '.estate-office-dashboard__panel table th,.estate-office-dashboard__panel table td{font-size:13px;}'
            . '.estate-office-dashboard__panel table td{vertical-align:top;}';
    }
}
