<?php
/**
 * Publiczna lista agentów EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Frontend_Agents
 */
class Estate_Office_Frontend_Agents {

    /**
     * Repozytorium agentów.
     *
     * @var Estate_Office_Agent_Repository
     */
    private Estate_Office_Agent_Repository $repository;

    /**
     * Opcja przechowująca ID strony z katalogiem agentów.
     */
    private const OPTION_PAGE_ID = 'estate_office_agents_page_id';

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Agent_Repository|null $repository Repozytorium agentów.
     */
    public function __construct( ?Estate_Office_Agent_Repository $repository = null ) {
        $this->repository = $repository ?? new Estate_Office_Agent_Repository();
    }

    /**
     * Rejestruje hooki WordPress.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
    }

    /**
     * Zapewnia istnienie strony katalogu agentów.
     *
     * @return void
     */
    public static function ensure_agents_page() : void {
        $page_id = (int) get_option( self::OPTION_PAGE_ID, 0 );
        $page    = $page_id > 0 ? get_post( $page_id ) : null;

        $page_data = [
            'post_title'   => __( 'Nasi agenci', 'estate-office' ),
            'post_content' => '[estate_office_agents]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ];

        if ( $page instanceof \WP_Post ) {
            if ( 'trash' === $page->post_status ) {
                wp_untrash_post( $page->ID );
            }

            $page_data['ID'] = $page->ID;
            wp_update_post( $page_data );
            update_option( self::OPTION_PAGE_ID, (int) $page->ID );

            return;
        }

        $new_page_id = wp_insert_post( $page_data );

        if ( is_wp_error( $new_page_id ) ) {
            Estate_Office_Plugin::log_debug( 'Nie udało się utworzyć strony katalogu agentów.', [ 'error' => $new_page_id->get_error_message() ] );

            return;
        }

        update_option( self::OPTION_PAGE_ID, (int) $new_page_id );
    }

    /**
     * Rejestruje shortcode katalogu agentów.
     *
     * @return void
     */
    public function register_shortcodes() : void {
        add_shortcode( 'estate_office_agents', [ $this, 'render_agents_directory' ] );
    }

    /**
     * Rejestruje zasoby frontowe.
     *
     * @return void
     */
    public function register_assets() : void {
        wp_register_style(
            'estate-office-agents-directory',
            ESTATE_OFFICE_URL . 'assets/css/estate-office-portal.css',
            [],
            ESTATE_OFFICE_VERSION
        );
    }

    /**
     * Dodaje zmienną zapytania używaną do nawigacji po katalogu agentów.
     *
     * @param array<int,string> $vars Lista zmiennych.
     *
     * @return array<int,string>
     */
    public function register_query_vars( array $vars ) : array {
        $vars[] = 'estate_office_agent';

        return $vars;
    }

    /**
     * Renderuje katalog lub profil agenta.
     *
     * @param array<string,string> $atts Atrybuty shortcode.
     *
     * @return string
     */
    public function render_agents_directory( array $atts = [] ) : string {
        $atts = shortcode_atts(
            [
                'columns' => 3,
            ],
            $atts,
            'estate_office_agents'
        );

        wp_enqueue_style( 'estate-office-agents-directory' );

        $agent_id = $this->resolve_agent_id();
        if ( $agent_id > 0 ) {
            $agent = $this->repository->find( $agent_id );
            if ( $agent ) {
                return $this->render_agent_profile( $agent );
            }
        }

        $columns = max( 1, min( 4, (int) $atts['columns'] ) );

        return $this->render_agents_grid( $columns );
    }

    /**
     * Rejestruje ID agenta z parametrów zapytania.
     *
     * @return int
     */
    private function resolve_agent_id() : int {
        $from_query = get_query_var( 'estate_office_agent' );

        if ( is_string( $from_query ) && '' !== $from_query ) {
            return absint( $from_query );
        }

        if ( isset( $_GET['agent_id'] ) ) {
            return absint( $_GET['agent_id'] );
        }

        return 0;
    }

    /**
     * Renderuje widok listy agentów.
     *
     * @param int $columns Liczba kolumn.
     *
     * @return string
     */
    private function render_agents_grid( int $columns ) : string {
        $agents = $this->repository->all();

        if ( empty( $agents ) ) {
            return '<div class="estate-office-agents"><p class="estate-office-agents__empty">' . esc_html__( 'Aktualnie brak aktywnych agentów.', 'estate-office' ) . '</p></div>';
        }

        $column_class = 'estate-office-agents__grid--cols-' . $columns;
        $output       = '<div class="estate-office-agents">';
        $output      .= '<div class="estate-office-agents__grid ' . esc_attr( $column_class ) . '">';
        $has_cards    = false;

        foreach ( $agents as $agent ) {
            $card = $this->prepare_agent_card( $agent );
            if ( null === $card ) {
                continue;
            }

            $output .= $card;
            $has_cards = true;
        }

        $output .= '</div>';

        if ( ! $has_cards ) {
            $output .= '<p class="estate-office-agents__empty">' . esc_html__( 'Aktualnie brak aktywnych agentów.', 'estate-office' ) . '</p>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Buduje kartę agenta do listy.
     *
     * @param object $agent Rekord agenta.
     *
     * @return string|null
     */
    private function prepare_agent_card( object $agent ) : ?string {
        $user = get_userdata( (int) $agent->user_id );
        if ( ! $user instanceof \WP_User ) {
            return null;
        }

        $meta      = $this->decode_meta( $agent->meta ?? '' );
        $photo     = $this->get_agent_photo( (int) $agent->photo_id );
        $location  = $this->format_location( $meta );
        $permalink = $this->build_agent_link( (int) $agent->id );

        $output  = '<article class="estate-office-agents__card">';
        $output .= $photo;
        $output .= '<div class="estate-office-agents__body">';
        $output .= '<h3 class="estate-office-agents__name"><a href="' . esc_url( $permalink ) . '">' . esc_html( $user->display_name ) . '</a></h3>';

        if ( ! empty( $agent->title ) ) {
            $output .= '<p class="estate-office-agents__title">' . esc_html( $agent->title ) . '</p>';
        }

        if ( $location ) {
            $output .= '<p class="estate-office-agents__location">' . esc_html( $location ) . '</p>';
        }

        $contact_items = $this->build_contact_items( $agent, $meta );
        if ( ! empty( $contact_items ) ) {
            $output .= '<ul class="estate-office-agents__contact">' . implode( '', $contact_items ) . '</ul>';
        }

        $output .= '<a class="estate-office-agents__more" href="' . esc_url( $permalink ) . '">' . esc_html__( 'Zobacz profil', 'estate-office' ) . '</a>';
        $output .= '</div></article>';

        return $output;
    }

    /**
     * Renderuje widok szczegółowy agenta.
     *
     * @param object $agent Rekord agenta.
     *
     * @return string
     */
    private function render_agent_profile( object $agent ) : string {
        $user = get_userdata( (int) $agent->user_id );
        if ( ! $user instanceof \WP_User ) {
            return '';
        }

        $meta     = $this->decode_meta( $agent->meta ?? '' );
        $photo    = $this->get_agent_photo( (int) $agent->photo_id, 'large' );
        $location = $this->format_location( $meta, true );
        $back_url = $this->build_agent_link();

        $output  = '<div class="estate-office-agents estate-office-agents--single">';
        $output .= '<article class="estate-office-agents__profile">';

        if ( $photo ) {
            $output .= $photo;
        }

        $output .= '<div class="estate-office-agents__profile-body">';
        $output .= '<h2 class="estate-office-agents__profile-name">' . esc_html( $user->display_name ) . '</h2>';

        if ( ! empty( $agent->title ) ) {
            $output .= '<p class="estate-office-agents__profile-title">' . esc_html( $agent->title ) . '</p>';
        }

        if ( $location ) {
            $output .= '<p class="estate-office-agents__profile-location">' . esc_html( $location ) . '</p>';
        }

        $contact_items = $this->build_contact_items( $agent, $meta );
        if ( ! empty( $contact_items ) ) {
            $output .= '<ul class="estate-office-agents__profile-contact">' . implode( '', $contact_items ) . '</ul>';
        }

        if ( ! empty( $agent->bio ) ) {
            $output .= '<div class="estate-office-agents__profile-bio">' . wp_kses_post( wpautop( $agent->bio ) ) . '</div>';
        }

        $output .= '<a class="estate-office-agents__back" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Powrót do listy agentów', 'estate-office' ) . '</a>';
        $output .= '</div></article></div>';

        return $output;
    }

    /**
     * Buduje elementy kontaktowe agenta.
     *
     * @param object               $agent Rekord agenta.
     * @param array<string,string> $meta  Metadane.
     *
     * @return array<int,string>
     */
    private function build_contact_items( object $agent, array $meta ) : array {
        $items = [];

        if ( ! empty( $agent->phone ) ) {
            $tel      = preg_replace( '/\s+/', '', $agent->phone );
            $tel_attr = is_string( $tel ) ? $tel : $agent->phone;
            $items[]  = '<li><a href="tel:' . esc_attr( $tel_attr ) . '">' . esc_html( $agent->phone ) . '</a></li>';
        }

        if ( ! empty( $meta['phone_secondary'] ?? '' ) ) {
            $secondary      = preg_replace( '/\s+/', '', $meta['phone_secondary'] );
            $secondary_attr = is_string( $secondary ) ? $secondary : $meta['phone_secondary'];
            $items[]        = '<li><a href="tel:' . esc_attr( $secondary_attr ) . '">' . esc_html( $meta['phone_secondary'] ) . '</a></li>';
        }

        if ( ! empty( $meta['office_phone'] ?? '' ) ) {
            $office      = preg_replace( '/\s+/', '', $meta['office_phone'] );
            $office_attr = is_string( $office ) ? $office : $meta['office_phone'];
            $items[]     = '<li><a href="tel:' . esc_attr( $office_attr ) . '">' . esc_html( $meta['office_phone'] ) . '</a></li>';
        }

        if ( ! empty( $agent->email ) ) {
            $safe_email = antispambot( $agent->email );
            $items[]    = '<li><a href="mailto:' . esc_attr( $safe_email ) . '">' . esc_html( $safe_email ) . '</a></li>';
        }

        if ( ! empty( $meta['office_website'] ?? '' ) ) {
            $items[] = '<li><a href="' . esc_url( $meta['office_website'] ) . '" rel="noopener" target="_blank">' . esc_html__( 'Strona agenta', 'estate-office' ) . '</a></li>';
        }

        if ( $address = $this->format_address( $meta ) ) {
            $items[] = '<li>' . esc_html( $address ) . '</li>';
        }

        return $items;
    }

    /**
     * Formatuje adres agenta z metadanych.
     *
     * @param array<string,string> $meta Metadane.
     *
     * @return string
     */
    private function format_address( array $meta ) : string {
        $street = $meta['office_street'] ?? '';
        $city   = $meta['office_city'] ?? '';
        $zip    = $meta['office_postcode'] ?? '';
        $country = $meta['office_country'] ?? '';

        $lines = array_filter(
            [
                trim( $street ),
                trim( implode( ' ', array_filter( [ $zip, $city ] ) ) ),
                trim( $country ),
            ],
            static function ( $value ) {
                return '' !== $value;
            }
        );

        return implode( ', ', $lines );
    }

    /**
     * Zwraca fragment HTML ze zdjęciem agenta.
     *
     * @param int    $photo_id ID załącznika.
     * @param string $size     Rozmiar obrazka.
     *
     * @return string
     */
    private function get_agent_photo( int $photo_id, string $size = 'medium' ) : string {
        if ( $photo_id <= 0 ) {
            return '<div class="estate-office-agents__placeholder" aria-hidden="true"></div>';
        }

        $image = wp_get_attachment_image( $photo_id, $size, false, [ 'class' => 'estate-office-agents__photo' ] );

        return $image ?: '<div class="estate-office-agents__placeholder" aria-hidden="true"></div>';
    }

    /**
     * Buduje adres URL powrotu lub profilu agenta.
     *
     * @param int|null $agent_id Opcjonalne ID agenta.
     *
     * @return string
     */
    private function build_agent_link( ?int $agent_id = null ) : string {
        $base_url = remove_query_arg( [ 'estate_office_agent', 'agent_id' ] );

        if ( null === $agent_id ) {
            return $base_url;
        }

        return add_query_arg( 'estate_office_agent', $agent_id, $base_url );
    }

    /**
     * Formatuje lokalizację agenta.
     *
     * @param array<string,string> $meta       Metadane agenta.
     * @param bool                 $include_street Czy dołączyć ulicę.
     *
     * @return string
     */
    private function format_location( array $meta, bool $include_street = false ) : string {
        $parts = [];

        if ( $include_street && ! empty( $meta['office_street'] ?? '' ) ) {
            $parts[] = $meta['office_street'];
        }

        if ( ! empty( $meta['office_city'] ?? '' ) || ! empty( $meta['office_postcode'] ?? '' ) ) {
            $parts[] = trim( implode( ' ', array_filter( [ $meta['office_postcode'] ?? '', $meta['office_city'] ?? '' ] ) ) );
        }

        if ( ! empty( $meta['office_country'] ?? '' ) ) {
            $parts[] = $meta['office_country'];
        }

        $parts = array_filter(
            array_map( 'trim', $parts ),
            static function ( $value ) {
                return '' !== $value;
            }
        );

        return implode( ', ', $parts );
    }

    /**
     * Dekoduje metadane JSON.
     *
     * @param string $meta Metadane w formacie JSON.
     *
     * @return array<string,string>
     */
    private function decode_meta( string $meta ) : array {
        if ( '' === $meta ) {
            return [];
        }

        $decoded = json_decode( $meta, true );

        if ( ! is_array( $decoded ) ) {
            return [];
        }

        return array_map( 'strval', $decoded );
    }
}
