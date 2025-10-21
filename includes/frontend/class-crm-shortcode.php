<?php
namespace EstateOffice\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Front-end CRM shortcode.
 */
class CRM_Shortcode {
    /**
     * Constructor.
     */
    public function __construct() {
        add_shortcode( 'estate_office_crm', [ $this, 'render_shortcode' ] );
    }

    /**
     * Renders shortcode output.
     */
    public function render_shortcode(): string {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return '<div class="estate-office-crm-message">' . esc_html__( 'Dostęp do CRM jest ograniczony do administratorów.', 'estate-office' ) . '</div>';
        }

        ob_start();
        $this->render_dashboard();

        return ob_get_clean();
    }

    /**
     * Renders CRM dashboard markup.
     */
    private function render_dashboard(): void {
        $tabs       = [
            'dashboard'  => __( 'Pulpit', 'estate-office' ),
            'properties' => __( 'Nieruchomości', 'estate-office' ),
            'contracts'  => __( 'Umowy', 'estate-office' ),
            'clients'    => __( 'Klienci', 'estate-office' ),
            'searches'   => __( 'Poszukiwania', 'estate-office' ),
        ];
        $properties = $this->get_properties();
        $contracts  = $this->get_contracts();
        $clients    = $this->get_clients();
        $searches   = $this->get_searches();
        $counts     = [
            'properties' => count( $properties ),
            'contracts'  => count( $contracts ),
            'clients'    => count( $clients ),
            'searches'   => count( $searches ),
        ];
        ?>
        <div class="estate-office-crm" data-nonce="<?php echo esc_attr( wp_create_nonce( 'estate_office_frontend' ) ); ?>">
            <div class="estate-office-crm-header">
                <h2><?php esc_html_e( 'Panel CRM', 'estate-office' ); ?></h2>
                <button type="button" class="button button-primary estate-office-new-contract">
                    <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
                </button>
            </div>
            <nav class="estate-office-crm-tabs" role="tablist">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <button type="button" class="estate-office-crm-tab<?php echo 'dashboard' === $key ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </button>
                <?php endforeach; ?>
            </nav>
            <div class="estate-office-crm-search">
                <label for="estate-office-crm-search-field" class="screen-reader-text"><?php esc_html_e( 'Wyszukaj', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-crm-search-field" placeholder="<?php esc_attr_e( 'Wyszukaj we wszystkich kolumnach', 'estate-office' ); ?>" />
            </div>
            <div class="estate-office-crm-content">
                <?php $this->render_overview_panel( $counts ); ?>
                <?php $this->render_table( 'properties', __( 'Brak nieruchomości do wyświetlenia.', 'estate-office' ), [
                    __( 'Numer oferty', 'estate-office' ),
                    __( 'Adres', 'estate-office' ),
                    __( 'Cena', 'estate-office' ),
                    __( 'Cena za m²', 'estate-office' ),
                    __( 'Metraż', 'estate-office' ),
                    __( 'Liczba pokoi', 'estate-office' ),
                    __( 'Opiekun', 'estate-office' ),
                ], $properties ); ?>
                <?php $this->render_table( 'searches', __( 'Brak poszukiwań.', 'estate-office' ), [
                    __( 'Numer poszukiwania', 'estate-office' ),
                    __( 'Rodzaj nieruchomości', 'estate-office' ),
                    __( 'Budżet', 'estate-office' ),
                    __( 'Lokalizacja', 'estate-office' ),
                    __( 'Typ transakcji', 'estate-office' ),
                ], $searches ); ?>
                <?php $this->render_table( 'contracts', __( 'Brak umów.', 'estate-office' ), [
                    __( 'Numer umowy', 'estate-office' ),
                    __( 'Typ transakcji', 'estate-office' ),
                    __( 'Rodzaj nieruchomości', 'estate-office' ),
                    __( 'Adres', 'estate-office' ),
                    __( 'Data zawarcia', 'estate-office' ),
                    __( 'Data zakończenia', 'estate-office' ),
                    __( 'Aktualny etap', 'estate-office' ),
                    __( 'Opiekun', 'estate-office' ),
                ], $contracts ); ?>
                <?php $this->render_table( 'clients', __( 'Brak klientów.', 'estate-office' ), [
                    __( 'Imię i nazwisko/Nazwa', 'estate-office' ),
                    __( 'Adres', 'estate-office' ),
                    __( 'Telefon', 'estate-office' ),
                    __( 'E-mail', 'estate-office' ),
                    __( 'Opiekun', 'estate-office' ),
                ], $clients ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders overview panel.
     */
    private function render_overview_panel( array $counts ): void {
        ?>
        <section class="estate-office-crm-panel is-active" data-panel="dashboard">
            <div class="estate-office-crm-overview">
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['properties'] ) ); ?></span>
                </div>
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Umowy', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['contracts'] ) ); ?></span>
                </div>
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['clients'] ) ); ?></span>
                </div>
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['searches'] ) ); ?></span>
                </div>
            </div>
            <p class="overview-description">
                <?php esc_html_e( 'W kolejnych wydaniach panel zostanie rozszerzony o wskaźniki skuteczności, harmonogram zadań oraz listę najaktywniejszych agentów.', 'estate-office' ); ?>
            </p>
        </section>
        <?php
    }

    /**
     * Renders data table panel.
     */
    private function render_table( string $slug, string $empty_message, array $headers, array $rows ): void {
        ?>
        <section class="estate-office-crm-panel" data-panel="<?php echo esc_attr( $slug ); ?>">
            <table class="estate-office-crm-table">
                <thead>
                    <tr>
                        <?php foreach ( $headers as $header ) : ?>
                            <th><?php echo esc_html( $header ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr class="estate-office-empty">
                            <td colspan="<?php echo esc_attr( count( $headers ) ); ?>"><?php echo esc_html( $empty_message ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <?php foreach ( $row as $cell ) : ?>
                                    <td><?php echo wp_kses_post( $cell ); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
    }

    /**
     * Collects property data rows.
     *
     * @return array<int, array<int, string>>
     */
    private function get_properties(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_property',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $number     = get_post_meta( $post->ID, 'estate_property_number', true );
            $address    = get_post_meta( $post->ID, 'estate_property_address', true );
            $price      = get_post_meta( $post->ID, 'estate_property_price', true );
            $sqm        = get_post_meta( $post->ID, 'estate_property_price_sqm', true );
            $area       = get_post_meta( $post->ID, 'estate_property_area', true );
            $rooms      = get_post_meta( $post->ID, 'estate_property_rooms', true );
            $agent_id   = get_post_meta( $post->ID, 'estate_property_agent', true );
            $agent_name = $agent_id ? get_the_author_meta( 'display_name', $agent_id ) : '';

            $rows[] = [
                $this->format_link( $post, $number ?: $post->post_title ),
                esc_html( $address ?: __( 'Brak adresu', 'estate-office' ) ),
                esc_html( $this->format_price( $price ) ),
                esc_html( $this->format_price( $sqm ) ),
                esc_html( $this->format_area( $area ) ),
                esc_html( $rooms ?: '-' ),
                esc_html( $agent_name ?: __( 'Nieprzypisany', 'estate-office' ) ),
            ];
        }

        return $rows;
    }

    /**
     * Collects contract data rows.
     */
    private function get_contracts(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_contract',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $number        = get_post_meta( $post->ID, 'estate_contract_number', true );
            $type          = get_post_meta( $post->ID, 'estate_contract_type', true );
            $property      = get_post_meta( $post->ID, 'estate_contract_property', true );
            $address       = $property ? get_post_meta( (int) $property, 'estate_property_address', true ) : '';
            $start_date    = get_post_meta( $post->ID, 'estate_contract_start', true );
            $end_date      = get_post_meta( $post->ID, 'estate_contract_end', true );
            $stage         = get_post_meta( $post->ID, 'estate_contract_stage', true );
            $agent_id      = get_post_meta( $post->ID, 'estate_contract_agent', true );
            $agent_name    = $agent_id ? get_the_author_meta( 'display_name', $agent_id ) : '';
            $property_type = get_post_meta( $post->ID, 'estate_contract_property_type', true );

            $rows[] = [
                $this->format_link( $post, $number ?: $post->post_title ),
                esc_html( $type ?: '-' ),
                esc_html( $property_type ?: '-' ),
                esc_html( $address ?: '-' ),
                esc_html( $this->format_date( $start_date ) ),
                esc_html( $this->format_date( $end_date ) ),
                esc_html( $stage ?: __( 'Umowa pośrednictwa', 'estate-office' ) ),
                esc_html( $agent_name ?: __( 'Nieprzypisany', 'estate-office' ) ),
            ];
        }

        return $rows;
    }

    /**
     * Collects client data rows.
     */
    private function get_clients(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_client',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $address          = get_post_meta( $post->ID, 'estate_client_address', true );
            $phone            = get_post_meta( $post->ID, 'estate_client_phone', true );
            $email            = get_post_meta( $post->ID, 'estate_client_email', true );
            $agent_id         = get_post_meta( $post->ID, 'estate_client_agent', true );
            $agent_name       = $agent_id ? get_the_author_meta( 'display_name', $agent_id ) : '';
            $property         = get_post_meta( $post->ID, 'estate_client_property', true );
            $property_address = $property ? get_post_meta( (int) $property, 'estate_property_address', true ) : '';

            $rows[] = [
                $this->format_link( $post, $post->post_title ),
                esc_html( $property_address ?: $address ?: '-' ),
                esc_html( $phone ?: '-' ),
                esc_html( $email ?: '-' ),
                esc_html( $agent_name ?: __( 'Nieprzypisany', 'estate-office' ) ),
            ];
        }

        return $rows;
    }

    /**
     * Collects search data rows.
     */
    private function get_searches(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_search',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $number      = get_post_meta( $post->ID, 'estate_search_number', true );
            $type        = get_post_meta( $post->ID, 'estate_search_type', true );
            $budget      = get_post_meta( $post->ID, 'estate_search_budget', true );
            $location    = get_post_meta( $post->ID, 'estate_search_location', true );
            $transaction = get_post_meta( $post->ID, 'estate_search_transaction', true );

            $rows[] = [
                $this->format_link( $post, $number ?: $post->post_title ),
                esc_html( $type ?: '-' ),
                esc_html( $this->format_price_range( $budget ) ),
                esc_html( $location ?: '-' ),
                esc_html( $transaction ?: '-' ),
            ];
        }

        return $rows;
    }

    /**
     * Creates an edit link for the item.
     */
    private function format_link( \WP_Post $post, string $label ): string {
        $url = get_edit_post_link( $post );

        if ( ! $url ) {
            return esc_html( $label );
        }

        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }

    /**
     * Formats price values.
     */
    private function format_price( $price ): string {
        if ( '' === $price || null === $price ) {
            return '-';
        }

        if ( is_numeric( $price ) ) {
            return number_format_i18n( (float) $price, 2 );
        }

        return (string) $price;
    }

    /**
     * Formats area values.
     */
    private function format_area( $area ): string {
        if ( '' === $area || null === $area ) {
            return '-';
        }

        $value = is_numeric( $area ) ? number_format_i18n( (float) $area, 2 ) : (string) $area;

        return sprintf( '%s m²', $value );
    }

    /**
     * Formats date values.
     */
    private function format_date( $date ): string {
        if ( ! $date ) {
            return '-';
        }

        $timestamp = strtotime( (string) $date );
        if ( false === $timestamp ) {
            return (string) $date;
        }

        return date_i18n( get_option( 'date_format', 'Y-m-d' ), $timestamp );
    }

    /**
     * Formats price range information.
     */
    private function format_price_range( $value ): string {
        if ( is_array( $value ) && isset( $value['min'], $value['max'] ) ) {
            return sprintf( '%s – %s', $this->format_price( $value['min'] ), $this->format_price( $value['max'] ) );
        }

        return $this->format_price( $value );
    }
}
