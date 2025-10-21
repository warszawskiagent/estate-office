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
            $number     = $this->get_meta_value( $post->ID, 'offer_number', $post->post_title );
            $address    = $this->format_property_address( $post->ID );
            $price      = $this->get_meta_value( $post->ID, 'price' );
            $sqm        = $this->get_meta_value( $post->ID, 'price_sqm' );
            $area       = $this->get_meta_value( $post->ID, 'area' );
            $rooms      = $this->get_meta_value( $post->ID, 'rooms', '-' );
            $agent_name = $this->get_agent_name( (int) $this->get_meta_value( $post->ID, 'agent_id', 0 ) );

            $rows[] = [
                $this->format_link( $post, $number ?: $post->post_title ),
                esc_html( $address ?: __( 'Brak adresu', 'estate-office' ) ),
                esc_html( $this->format_price( $price ) ),
                esc_html( $this->format_price( $sqm ) ),
                esc_html( $this->format_area( $area ) ),
                esc_html( $rooms ?: '-' ),
                esc_html( $agent_name ),
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
            $number        = $this->get_meta_value( $post->ID, 'contract_number', $post->post_title );
            $transaction   = $this->translate_transaction_type( $this->get_meta_value( $post->ID, 'transaction_type' ) );
            $property_type = $this->translate_property_type( $this->get_meta_value( $post->ID, 'property_type' ) );
            $property_id   = (int) $this->get_meta_value( $post->ID, 'related_property', 0 );
            $address       = $property_id ? $this->format_property_address( $property_id ) : '';
            $start_date    = $this->get_meta_value( $post->ID, 'start_date' );
            $end_date      = $this->get_meta_value( $post->ID, 'end_date' );
            $stage_key     = $this->get_meta_value( $post->ID, 'stage' );
            $stage_label   = $stage_key ? $this->get_contract_stage_label( $stage_key ) : __( 'Umowa pośrednictwa', 'estate-office' );
            $agent_name    = $this->get_agent_name( (int) $this->get_meta_value( $post->ID, 'agent_id', 0 ) );

            $rows[] = [
                $this->format_link( $post, $number ?: $post->post_title ),
                esc_html( $transaction ?: '-' ),
                esc_html( $property_type ?: '-' ),
                esc_html( $address ?: '-' ),
                esc_html( $this->format_date( $start_date ) ),
                esc_html( $this->format_date( $end_date ) ),
                esc_html( $stage_label ),
                esc_html( $agent_name ),
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
            $address    = $this->format_client_address( $post->ID );
            $phone      = $this->get_meta_value( $post->ID, 'phone', '-' );
            $email      = $this->get_meta_value( $post->ID, 'email', '-' );
            $agent_name = $this->get_agent_name( (int) $this->get_meta_value( $post->ID, 'agent_id', 0 ) );

            $rows[] = [
                $this->format_link( $post, $post->post_title ),
                esc_html( $address ?: '-' ),
                esc_html( $phone ?: '-' ),
                esc_html( $email ?: '-' ),
                esc_html( $agent_name ),
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
            $number        = $this->get_meta_value( $post->ID, 'search_number', $post->post_title );
            $property_type = $this->translate_property_type( $this->get_meta_value( $post->ID, 'property_type' ) );
            $budget        = $this->format_budget( $this->get_meta_value( $post->ID, 'budget_min' ), $this->get_meta_value( $post->ID, 'budget_max' ) );
            $location      = $this->get_meta_value( $post->ID, 'location', '-' );
            $transaction   = $this->translate_transaction_type( $this->get_meta_value( $post->ID, 'transaction_type' ) );

            $rows[] = [
                $this->format_link( $post, $number ?: $post->post_title ),
                esc_html( $property_type ?: '-' ),
                esc_html( $budget ),
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
     * Formats budget range information.
     */
    private function format_budget( $min, $max ): string {
        $has_min = '' !== $min && null !== $min;
        $has_max = '' !== $max && null !== $max;

        if ( ! $has_min && ! $has_max ) {
            return '-';
        }

        if ( $has_min && $has_max ) {
            return sprintf( '%s – %s', $this->format_price( $min ), $this->format_price( $max ) );
        }

        if ( $has_min ) {
            return sprintf( __( 'Od %s', 'estate-office' ), $this->format_price( $min ) );
        }

        return sprintf( __( 'Do %s', 'estate-office' ), $this->format_price( $max ) );
    }

    /**
     * Returns sanitized meta value with estate_office_ prefix.
     *
     * @param int    $post_id Post identifier.
     * @param string $key     Meta key without prefix.
     * @param mixed  $default Default value.
     *
     * @return mixed
     */
    private function get_meta_value( int $post_id, string $key, $default = '' ) {
        $meta_key = 'estate_office_' . $key;
        $value    = get_post_meta( $post_id, $meta_key, true );

        if ( is_array( $value ) && empty( $value ) ) {
            return $default;
        }

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Creates human readable property address.
     */
    private function format_property_address( int $post_id ): string {
        $street  = $this->get_meta_value( $post_id, 'street' );
        $number  = $this->get_meta_value( $post_id, 'building_number' );
        $unit    = $this->get_meta_value( $post_id, 'unit_number' );
        $city    = $this->get_meta_value( $post_id, 'city' );
        $postal  = $this->get_meta_value( $post_id, 'postal_code' );
        $district = $this->get_meta_value( $post_id, 'district' );
        $plot    = $this->get_meta_value( $post_id, 'plot_number' );

        $parts = [];
        $line  = trim( $street . ' ' . $number );
        if ( $unit ) {
            $line = $line ? $line . '/' . $unit : $unit;
        }
        if ( $line ) {
            $parts[] = $line;
        } elseif ( $plot ) {
            $parts[] = sprintf( __( 'Działka %s', 'estate-office' ), $plot );
        }

        if ( $district ) {
            $parts[] = $district;
        }

        $city_line = array_filter( [ $postal, $city ] );
        if ( ! empty( $city_line ) ) {
            $parts[] = implode( ' ', $city_line );
        }

        return implode( ', ', array_filter( $parts ) );
    }

    /**
     * Formats client address from meta array.
     */
    private function format_client_address( int $post_id ): string {
        $address = get_post_meta( $post_id, 'estate_office_address', true );
        if ( ! is_array( $address ) ) {
            return '';
        }

        $line = trim( ( $address['street'] ?? '' ) . ' ' . ( $address['number'] ?? '' ) );
        if ( ! empty( $address['unit'] ) ) {
            $line = $line ? $line . '/' . $address['unit'] : $address['unit'];
        }

        $parts = array_filter( [ $line, $address['postal_code'] ?? '', $address['city'] ?? '', $address['country'] ?? '' ] );

        return implode( ', ', $parts );
    }

    /**
     * Returns agent display name or fallback.
     */
    private function get_agent_name( int $user_id ): string {
        if ( $user_id <= 0 ) {
            return __( 'Nieprzypisany', 'estate-office' );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return __( 'Nieprzypisany', 'estate-office' );
        }

        return $user->display_name ?: __( 'Nieprzypisany', 'estate-office' );
    }

    /**
     * Converts transaction type slug to label.
     */
    private function translate_transaction_type( string $type ): string {
        $map = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        return $map[ $type ] ?? $type;
    }

    /**
     * Converts property type slug to label.
     */
    private function translate_property_type( string $type ): string {
        $map = [
            'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
            'dom'        => __( 'Dom', 'estate-office' ),
            'dzialka'    => __( 'Działka', 'estate-office' ),
            'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];

        return $map[ $type ] ?? $type;
    }

    /**
     * Returns label for contract stage slug.
     */
    private function get_contract_stage_label( string $stage ): string {
        $stages = [
            ''             => __( 'Umowa pośrednictwa', 'estate-office' ),
            'mls'          => __( 'Publikacja w MLS', 'estate-office' ),
            'preparation'  => __( 'Przygotowanie oferty', 'estate-office' ),
            'publication'  => __( 'Publikacja oferty', 'estate-office' ),
            'marketing'    => __( 'Marketing i prezentacje', 'estate-office' ),
            'offer'        => __( 'Oferta kupna', 'estate-office' ),
            'negotiations' => __( 'Negocjacje', 'estate-office' ),
            'preliminary'  => __( 'Umowa przedwstępna', 'estate-office' ),
            'final'        => __( 'Umowa przyrzeczona', 'estate-office' ),
            'handover'     => __( 'Przekazanie lokalu', 'estate-office' ),
            'completed'    => __( 'Umowa zakończona', 'estate-office' ),
        ];

        return $stages[ $stage ] ?? $stage;
    }
}
