<?php
/**
 * Publiczna prezentacja ofert EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Frontend_Offers
 */
class Estate_Office_Frontend_Offers {

    /**
     * Repozytorium nieruchomości.
     *
     * @var Estate_Office_Property_Repository
     */
    private Estate_Office_Property_Repository $property_repository;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Property_Repository|null $property_repository Repozytorium nieruchomości.
     */
    public function __construct( ?Estate_Office_Property_Repository $property_repository = null ) {
        $this->property_repository = $property_repository ?? new Estate_Office_Property_Repository();
    }

    /**
     * Rejestruje hooki frontendowe.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'init', [ __CLASS__, 'maybe_restore_pages' ], 5 );
    }

    /**
     * Rejestruje assety wykorzystywane na stronach ofert.
     *
     * @return void
     */
    public function register_assets() : void {
        wp_register_style( 'estate-office-offers', false, [], ESTATE_OFFICE_VERSION );

        $css = '.estate-office-offers{font-family:var(--eo-font,inherit);margin:2rem 0;}'
            . '.estate-office-offers__filters{display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;}'
            . '.estate-office-offers__filters select{min-width:160px;padding:0.5rem;border-radius:6px;border:1px solid #d0d7de;}'
            . '.estate-office-offers__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.5rem;}'
            . '.estate-office-offers__card{background:#fff;border-radius:12px;box-shadow:0 8px 20px rgba(15,23,42,.08);overflow:hidden;display:flex;flex-direction:column;}'
            . '.estate-office-offers__image{aspect-ratio:4/3;background:#f3f4f6;position:relative;overflow:hidden;}'
            . '.estate-office-offers__image img{width:100%;height:100%;object-fit:cover;display:block;}'
            . '.estate-office-offers__body{padding:1rem 1.25rem;display:flex;flex-direction:column;gap:0.5rem;}'
            . '.estate-office-offers__title{font-size:1.1rem;font-weight:600;margin:0;color:#111827;}'
            . '.estate-office-offers__meta{font-size:0.9rem;color:#4b5563;display:flex;flex-wrap:wrap;gap:0.5rem;}'
            . '.estate-office-offers__price{font-size:1rem;font-weight:700;color:#0f172a;}'
            . '.estate-office-offers__link{margin-top:auto;display:inline-flex;align-items:center;gap:0.35rem;font-weight:600;color:#2563eb;text-decoration:none;}'
            . '.estate-office-offers__link:hover{color:#1d4ed8;}'
            . '.estate-office-offers__empty{padding:2rem;border-radius:12px;background:#f9fafb;text-align:center;color:#6b7280;}'
            . '.estate-office-offers__detail{margin-top:2rem;padding:2rem;border-radius:16px;background:#ffffff;box-shadow:0 12px 30px rgba(15,23,42,.1);}'
            . '.estate-office-offers__detail h2{margin-top:0;font-size:1.5rem;}'
            . '.estate-office-offers__detail dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;}'
            . '.estate-office-offers__detail dt{font-weight:600;color:#111827;}'
            . '.estate-office-offers__detail dd{margin:0;color:#4b5563;}'
            . '.estate-office-offers__gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:1.5rem;}'
            . '.estate-office-offers__gallery img{width:100%;border-radius:12px;object-fit:cover;aspect-ratio:4/3;}'
            . '.estate-office-offers__filters button{padding:0.6rem 1.4rem;border-radius:8px;border:none;background:#2563eb;color:#fff;font-weight:600;cursor:pointer;}'
            . '.estate-office-offers__filters button:hover{background:#1d4ed8;}'
            . '.estate-office-offers__filters .estate-office-offers__reset{background:#e5e7eb;color:#1f2937;}'
            . '.estate-office-offers__filters .estate-office-offers__reset:hover{background:#d1d5db;}'
            . '.estate-office-offers__badges{display:flex;flex-wrap:wrap;gap:0.5rem;font-size:0.75rem;}'
            . '.estate-office-offers__badge{background:#f3f4f6;color:#1f2937;padding:0.25rem 0.65rem;border-radius:999px;text-transform:uppercase;letter-spacing:.05em;}'
            . '.estate-office-offers__pagination{display:flex;gap:0.5rem;justify-content:center;margin-top:1.5rem;}'
            . '.estate-office-offers__pagination .estate-office-offers__page{padding:0.45rem 0.85rem;border-radius:8px;background:#f3f4f6;color:#1f2937;text-decoration:none;font-weight:600;}'
            . '.estate-office-offers__pagination .estate-office-offers__page.is-active{background:#2563eb;color:#fff;}'
            . '.estate-office-offers__description{margin-top:1.5rem;color:#374151;font-size:0.95rem;line-height:1.7;}'
            . '.estate-office-offers__calculators{display:grid;gap:2rem;margin-top:2.5rem;}'
            . '.estate-office-offers__calculators .estate-office-calculators{margin:0;}'
            . '@media (max-width:640px){.estate-office-offers__filters{flex-direction:column;align-items:stretch;}.estate-office-offers__filters select,.estate-office-offers__filters button{width:100%;}}';

        wp_add_inline_style( 'estate-office-offers', $css );
    }

    /**
     * Rejestruje shortcody.
     *
     * @return void
     */
    public function register_shortcodes() : void {
        add_shortcode( 'estate_office_offers', [ $this, 'render_offers_shortcode' ] );
    }

    /**
     * Renderuje listę ofert.
     *
     * @param array<string,string> $atts Atrybuty shortcode.
     *
     * @return string
     */
    public function render_offers_shortcode( array $atts ) : string {
        $atts = shortcode_atts(
            [
                'context'  => 'sale',
                'per_page' => 12,
            ],
            $atts,
            'estate_office_offers'
        );

        $context      = in_array( $atts['context'], [ 'sale', 'rent' ], true ) ? $atts['context'] : 'sale';
        $per_page     = max( 1, (int) $atts['per_page'] );
        $base_filters = 'rent' === $context ? [ 'WYNAJEM' ] : [ 'SPRZEDAŻ' ];

        $available_filters = $this->property_repository->get_offer_filters(
            [
                'transaction_types' => $base_filters,
            ]
        );

        $selected_transaction = $this->filter_input_value( 'transaction_type', $available_filters['transaction_type'] );
        $selected_type         = $this->filter_input_value( 'property_type', $available_filters['property_type'] );
        $selected_city         = $this->filter_input_value( 'city', $available_filters['city'] );
        $selected_district     = $this->filter_input_value( 'district', $available_filters['district'] );

        $transaction_scope = ! empty( $selected_transaction ) ? [ $selected_transaction ] : $base_filters;

        $paged = isset( $_GET['eo_page'] ) ? max( 1, (int) $_GET['eo_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $results = $this->property_repository->get_exported_offers(
            [
                'transaction_types' => $transaction_scope,
                'property_type'     => $selected_type,
                'city'              => $selected_city,
                'district'          => $selected_district,
                'paged'             => $paged,
                'per_page'          => $per_page,
            ]
        );

        $offer_id   = isset( $_GET['estate_office_offer'] ) ? absint( $_GET['estate_office_offer'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $detail     = $offer_id ? $this->property_repository->find_exported_offer( $offer_id ) : null;
        $detail     = $detail && in_array( $detail['transaction_type'], $transaction_scope, true ) ? $detail : null;
        $detail_url = $this->current_url_without_query( [ 'estate_office_offer' ] );

        ob_start();

        wp_enqueue_style( 'estate-office-offers' );

        $form_action = esc_url( $this->current_url_without_query( [ 'eo_page', 'estate_office_offer' ] ) );
        ?>
        <div class="estate-office-offers" data-context="<?php echo esc_attr( $context ); ?>">
            <form class="estate-office-offers__filters" method="get" action="<?php echo $form_action; ?>">
                <?php $this->render_hidden_query_inputs( [ 'transaction_type', 'property_type', 'city', 'district', 'eo_page', 'estate_office_offer', 'estate_office_reset' ] ); ?>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></span>
                    <select name="transaction_type">
                        <option value=""><?php esc_html_e( 'Wszystkie transakcje', 'estate-office' ); ?></option>
                        <?php foreach ( $available_filters['transaction_type'] as $value ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_transaction, $value ); ?>>
                                <?php echo esc_html( $value ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></span>
                    <select name="property_type">
                        <option value=""><?php esc_html_e( 'Wszystkie typy', 'estate-office' ); ?></option>
                        <?php foreach ( $available_filters['property_type'] as $value ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_type, $value ); ?>>
                                <?php echo esc_html( $value ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></span>
                    <select name="city">
                        <option value=""><?php esc_html_e( 'Wszystkie miasta', 'estate-office' ); ?></option>
                        <?php foreach ( $available_filters['city'] as $value ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_city, $value ); ?>>
                                <?php echo esc_html( $value ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></span>
                    <select name="district">
                        <option value=""><?php esc_html_e( 'Wszystkie dzielnice', 'estate-office' ); ?></option>
                        <?php foreach ( $available_filters['district'] as $value ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_district, $value ); ?>>
                                <?php echo esc_html( $value ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit"><?php esc_html_e( 'Filtruj', 'estate-office' ); ?></button>
                <button type="submit" name="estate_office_reset" value="1" class="estate-office-offers__reset"><?php esc_html_e( 'Wyczyść', 'estate-office' ); ?></button>
            </form>

            <?php if ( empty( $results['items'] ) ) : ?>
                <div class="estate-office-offers__empty">
                    <?php esc_html_e( 'Brak ofert spełniających wybrane kryteria.', 'estate-office' ); ?>
                </div>
            <?php else : ?>
                <div class="estate-office-offers__grid">
                    <?php foreach ( $results['items'] as $item ) : ?>
                        <?php $card_url = esc_url( add_query_arg( 'estate_office_offer', (int) $item['id'], $detail_url ) ); ?>
                        <article class="estate-office-offers__card">
                            <?php $this->render_card_image( $item ); ?>
                            <div class="estate-office-offers__body">
                                <h3 class="estate-office-offers__title"><?php echo esc_html( $this->resolve_card_title( $item ) ); ?></h3>
                                <div class="estate-office-offers__badges">
                                    <?php foreach ( $this->resolve_badges( $item ) as $badge ) : ?>
                                        <span class="estate-office-offers__badge"><?php echo esc_html( $badge ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="estate-office-offers__price"><?php echo esc_html( $this->format_price_label( $item ) ); ?></div>
                                <div class="estate-office-offers__meta">
                                    <span><?php echo esc_html( $item['city'] ); ?></span>
                                    <?php if ( ! empty( $item['district'] ) ) : ?>
                                        <span><?php echo esc_html( $item['district'] ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $item['area_total'] ) ) : ?>
                                        <span><?php echo esc_html( number_format_i18n( (float) $item['area_total'], 2 ) ); ?> m²</span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $item['rooms'] ) ) : ?>
                                        <span><?php echo esc_html( sprintf( _n( '%s pokój', '%s pokoje', (int) $item['rooms'], 'estate-office' ), (int) $item['rooms'] ) ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="estate-office-offers__link" href="<?php echo $card_url; ?>">
                                    <?php esc_html_e( 'Zobacz szczegóły', 'estate-office' ); ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php $this->render_pagination( $results ); ?>
            <?php endif; ?>

            <?php if ( $detail ) : ?>
                <section class="estate-office-offers__detail" id="estate-office-offer">
                    <h2><?php echo esc_html( $detail['title'] ?: $detail['listing_number'] ); ?></h2>
                    <div class="estate-office-offers__badges">
                        <?php foreach ( $this->resolve_badges( $detail ) as $badge ) : ?>
                            <span class="estate-office-offers__badge"><?php echo esc_html( $badge ); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <dl>
                        <?php $this->render_detail_pair( __( 'Numer oferty', 'estate-office' ), $detail['listing_number'] ); ?>
                        <?php $this->render_detail_pair( __( 'Typ transakcji', 'estate-office' ), $detail['transaction_type'] ); ?>
                        <?php $this->render_detail_pair( __( 'Rodzaj nieruchomości', 'estate-office' ), $detail['property_type'] ); ?>
                        <?php $this->render_detail_pair( __( 'Miasto', 'estate-office' ), $detail['city'] ); ?>
                        <?php $this->render_detail_pair( __( 'Dzielnica', 'estate-office' ), $detail['district'] ); ?>
                        <?php $this->render_detail_pair( __( 'Cena', 'estate-office' ), $this->format_price_label( $detail ) ); ?>
                        <?php $this->render_detail_pair( __( 'Metraż', 'estate-office' ), ! empty( $detail['area_total'] ) ? number_format_i18n( (float) $detail['area_total'], 2 ) . ' m²' : '' ); ?>
                        <?php $this->render_detail_pair( __( 'Liczba pokoi', 'estate-office' ), $detail['rooms'] ); ?>
                        <?php $this->render_detail_pair( __( 'Opiekun', 'estate-office' ), $detail['agent_name'] ?? '' ); ?>
                    </dl>
                    <?php if ( ! empty( $detail['description'] ) ) : ?>
                        <div class="estate-office-offers__description">
                            <?php echo wp_kses_post( wpautop( $detail['description'] ) ); ?>
                        </div>
                    <?php endif; ?>
                    <?php $this->render_gallery( $detail ); ?>
                    <div class="estate-office-offers__calculators">
                        <?php
                        $price_value           = isset( $detail['price'] ) ? (float) $detail['price'] : 0.0;
                        $transaction_context   = isset( $detail['transaction_type'] ) ? sanitize_text_field( $detail['transaction_type'] ) : 'SPRZEDAŻ';
                        $default_down_payment  = $price_value > 0 ? $price_value * 0.2 : 200000.0;

                        echo do_shortcode( sprintf(
                            '[estate_office_notary_calculator price="%1$s" transaction="%2$s" show_hint="yes"]',
                            esc_attr( number_format( $price_value, 2, '.', '' ) ),
                            esc_attr( $transaction_context )
                        ) );

                        echo do_shortcode( sprintf(
                            '[estate_office_mortgage_calculator price="%1$s" down_payment="%2$s" interest="6.5" years="25"]',
                            esc_attr( number_format( $price_value, 2, '.', '' ) ),
                            esc_attr( number_format( $default_down_payment, 2, '.', '' ) )
                        ) );
                        ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Upewnia się, że strony ofert istnieją.
     *
     * @return void
     */
    public static function ensure_offer_pages() : void {
        self::ensure_offer_page(
            'estate_office_offers_sale_page_id',
            [
                'title'     => __( 'Oferty na sprzedaż', 'estate-office' ),
                'slug'      => 'oferty-na-sprzedaz',
                'shortcode' => '[estate_office_offers context="sale"]',
            ]
        );

        self::ensure_offer_page(
            'estate_office_offers_rent_page_id',
            [
                'title'     => __( 'Oferty na wynajem', 'estate-office' ),
                'slug'      => 'oferty-na-wynajem',
                'shortcode' => '[estate_office_offers context="rent"]',
            ]
        );
    }

    /**
     * Sprawdza, czy strony ofert są dostępne i w razie potrzeby je odtwarza.
     *
     * @return void
     */
    public static function maybe_restore_pages() : void {
        $sale_page_id = (int) get_option( 'estate_office_offers_sale_page_id', 0 );
        $rent_page_id = (int) get_option( 'estate_office_offers_rent_page_id', 0 );

        if ( $sale_page_id <= 0 || ! self::is_page_valid( $sale_page_id ) ) {
            self::ensure_offer_pages();
            return;
        }

        if ( $rent_page_id <= 0 || ! self::is_page_valid( $rent_page_id ) ) {
            self::ensure_offer_pages();
        }
    }

    /**
     * Renderuje ukryte pola zachowujące pozostałe parametry zapytania.
     *
     * @param array<int,string> $excluded Klucze wykluczone.
     *
     * @return void
     */
    private function render_hidden_query_inputs( array $excluded ) : void {
        foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( in_array( $key, $excluded, true ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                continue;
            }

            printf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr( $key ),
                esc_attr( sanitize_text_field( wp_unslash( $value ) ) )
            );
        }
    }

    /**
     * Renderuje pojedynczą parę danych w widoku szczegółów.
     *
     * @param string $label Etykieta.
     * @param mixed  $value Wartość.
     *
     * @return void
     */
    private function render_detail_pair( string $label, $value ) : void {
        if ( empty( $value ) ) {
            return;
        }

        ?>
        <div>
            <dt><?php echo esc_html( $label ); ?></dt>
            <dd><?php echo esc_html( (string) $value ); ?></dd>
        </div>
        <?php
    }

    /**
     * Renderuje galerię zdjęć.
     *
     * @param array<string,mixed> $offer Dane oferty.
     *
     * @return void
     */
    private function render_gallery( array $offer ) : void {
        $gallery = $this->extract_gallery( $offer['gallery'] ?? null );

        if ( empty( $gallery ) ) {
            return;
        }

        echo '<div class="estate-office-offers__gallery">';

        foreach ( $gallery as $image_url ) {
            printf(
                '<img src="%1$s" alt="%2$s" loading="lazy" />',
                esc_url( $image_url ),
                esc_attr( $offer['title'] ?? $offer['listing_number'] )
            );
        }

        echo '</div>';
    }

    /**
     * Renderuje obrazek karty.
     *
     * @param array<string,mixed> $item Dane oferty.
     *
     * @return void
     */
    private function render_card_image( array $item ) : void {
        $gallery = $this->extract_gallery( $item['gallery'] ?? null );
        $image   = $gallery[0] ?? '';

        echo '<div class="estate-office-offers__image">';

        if ( $image ) {
            printf( '<img src="%1$s" alt="%2$s" loading="lazy" />', esc_url( $image ), esc_attr( $this->resolve_card_title( $item ) ) );
        }

        echo '</div>';
    }

    /**
     * Renderuje paginację wyników.
     *
     * @param array<string,mixed> $results Dane wyniku.
     * @param string              $context Kontekst (sale/rent).
     *
     * @return void
     */
    private function render_pagination( array $results ) : void {
        $total_pages = (int) ( $results['total_page'] ?? 0 );

        if ( $total_pages <= 1 ) {
            return;
        }

        $current = (int) ( $results['current_page'] ?? 1 );

        echo '<nav class="estate-office-offers__pagination" aria-label="' . esc_attr__( 'Nawigacja ofert', 'estate-office' ) . '">';

        $base_url = $this->current_url_without_query( [ 'estate_office_offer', 'eo_page' ] );

        for ( $page = 1; $page <= $total_pages; $page++ ) {
            $url = esc_url( add_query_arg( 'eo_page', $page, $base_url ) );
            printf(
                '<a href="%1$s" class="estate-office-offers__page%3$s">%2$s</a>',
                $url,
                esc_html( (string) $page ),
                $page === $current ? ' is-active' : ''
            );
        }

        echo '</nav>';
    }

    /**
     * Zwraca aktualny adres URL bez wybranych parametrów.
     *
     * @param array<int,string> $excluded Klucze do usunięcia.
     *
     * @return string
     */
    private function current_url_without_query( array $excluded ) : string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $current_url = esc_url_raw( $scheme . $host . $uri );

        return remove_query_arg( $excluded, $current_url );
    }

    /**
     * Pobiera i waliduje wartość z zapytania.
     *
     * @param string             $param_name  Nazwa parametru.
     * @param array<int,string>  $allowed     Dozwolone wartości.
     *
     * @return string
     */
    private function filter_input_value( string $param_name, array $allowed ) : string {
        if ( isset( $_GET['estate_office_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }

        if ( empty( $_GET[ $param_name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }

        $value = sanitize_text_field( wp_unslash( (string) $_GET[ $param_name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return in_array( $value, $allowed, true ) ? $value : '';
    }

    /**
     * Formatuje cenę wraz z walutą.
     *
     * @param array<string,mixed> $item Dane oferty.
     *
     * @return string
     */
    private function format_price_label( array $item ) : string {
        if ( empty( $item['price'] ) ) {
            return __( 'Cena na zapytanie', 'estate-office' );
        }

        $price     = number_format_i18n( (float) $item['price'], 0 );
        $currency  = ! empty( $item['price_currency'] ) ? $item['price_currency'] : 'PLN';
        $suffix    = '';

        if ( ! empty( $item['price_period'] ) && in_array( $item['price_period'], [ 'miesięcznie', 'm-c', 'msc', 'monthly' ], true ) ) {
            $suffix = ' / ' . __( 'miesięcznie', 'estate-office' );
        }

        return trim( sprintf( '%s %s%s', $price, $currency, $suffix ) );
    }

    /**
     * Wyznacza tytuł karty.
     *
     * @param array<string,mixed> $item Dane oferty.
     *
     * @return string
     */
    private function resolve_card_title( array $item ) : string {
        if ( ! empty( $item['title'] ) ) {
            return (string) $item['title'];
        }

        $parts = array_filter(
            [
                $item['transaction_type'] ?? '',
                $item['property_type'] ?? '',
                $item['city'] ?? '',
            ]
        );

        if ( ! empty( $parts ) ) {
            return implode( ' – ', array_map( 'sanitize_text_field', $parts ) );
        }

        return (string) ( $item['listing_number'] ?? __( 'Oferta nieruchomości', 'estate-office' ) );
    }

    /**
     * Zwraca listę odznak dla oferty.
     *
     * @param array<string,mixed> $item Dane oferty.
     *
     * @return array<int,string>
     */
    private function resolve_badges( array $item ) : array {
        $badges = [];

        $labels = $item['labels'] ?? '';
        if ( is_string( $labels ) && ! empty( $labels ) ) {
            $decoded = json_decode( $labels, true );
            if ( is_array( $decoded ) ) {
                $labels = $decoded;
            }
        }

        if ( is_array( $labels ) ) {
            foreach ( $labels as $label ) {
                if ( is_string( $label ) && '' !== $label ) {
                    $badges[] = sanitize_text_field( $label );
                }
            }
        }

        if ( ! empty( $item['exclusive_offer'] ) ) {
            $badges[] = __( 'Wyłączność', 'estate-office' );
        }

        if ( ! empty( $item['premium_offer'] ) ) {
            $badges[] = __( 'Premium', 'estate-office' );
        }

        if ( ! empty( $item['new_offer'] ) ) {
            $badges[] = __( 'Nowa oferta', 'estate-office' );
        }

        if ( ! empty( $item['new_price'] ) ) {
            $badges[] = __( 'Nowa cena', 'estate-office' );
        }

        if ( ! empty( $item['commission_free'] ) ) {
            $badges[] = __( 'Bez prowizji', 'estate-office' );
        }

        return array_unique( $badges );
    }

    /**
     * Odczytuje galerię zdjęć.
     *
     * @param mixed $gallery Dane galerii.
     *
     * @return array<int,string>
     */
    private function extract_gallery( $gallery ) : array {
        if ( empty( $gallery ) ) {
            return [];
        }

        if ( is_string( $gallery ) ) {
            $decoded = json_decode( $gallery, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $gallery = $decoded;
            }
        }

        $urls = [];

        if ( is_array( $gallery ) ) {
            foreach ( $gallery as $item ) {
                if ( is_string( $item ) ) {
                    $urls[] = esc_url_raw( $item );
                } elseif ( is_array( $item ) && ! empty( $item['url'] ) ) {
                    $urls[] = esc_url_raw( (string) $item['url'] );
                }
            }
        }

        return array_values( array_filter( $urls ) );
    }

    /**
     * Upewnia się, że strona o zadanym shortcode istnieje.
     *
     * @param string               $option_key Klucz opcji.
     * @param array<string,string> $definition Definicja strony.
     *
     * @return void
     */
    private static function ensure_offer_page( string $option_key, array $definition ) : void {
        $page_id = (int) get_option( $option_key, 0 );

        if ( $page_id > 0 ) {
            $page = get_post( $page_id );
            if ( self::is_page_valid( $page ) ) {
                if ( false === strpos( (string) $page->post_content, $definition['shortcode'] ) ) {
                    wp_update_post(
                        [
                            'ID'           => $page->ID,
                            'post_content' => $definition['shortcode'],
                        ]
                    );
                }

                return;
            }
        }

        $existing = get_page_by_path( $definition['slug'] );
        if ( $existing instanceof WP_Post ) {
            if ( false === strpos( (string) $existing->post_content, $definition['shortcode'] ) ) {
                wp_update_post(
                    [
                        'ID'           => $existing->ID,
                        'post_content' => $definition['shortcode'],
                    ]
                );
            }

            if ( 'publish' !== $existing->post_status ) {
                wp_update_post(
                    [
                        'ID'          => $existing->ID,
                        'post_status' => 'publish',
                    ]
                );
            }

            update_option( $option_key, (int) $existing->ID );
            Estate_Office_Plugin::log_debug( 'Odnaleziono istniejącą stronę ofert.', [ 'page_id' => (int) $existing->ID ] );

            return;
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => $definition['title'],
                'post_name'    => sanitize_title( $definition['slug'] ),
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => $definition['shortcode'],
            ],
            true
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            Estate_Office_Plugin::log_debug(
                'Nie udało się utworzyć strony ofert.',
                [ 'error' => $page_id instanceof WP_Error ? $page_id->get_error_message() : 'unknown' ]
            );

            return;
        }

        update_option( $option_key, (int) $page_id );
        Estate_Office_Plugin::log_debug( 'Utworzono stronę ofert.', [ 'page_id' => (int) $page_id ] );
    }

    /**
     * Sprawdza, czy przekazany obiekt strony jest poprawny.
     *
     * @param int|WP_Post|null $page Strona lub jej ID.
     *
     * @return bool
     */
    private static function is_page_valid( $page ) : bool {
        if ( $page instanceof WP_Post ) {
            return 'trash' !== $page->post_status;
        }

        if ( is_numeric( $page ) && $page > 0 ) {
            $post = get_post( (int) $page );

            return $post instanceof WP_Post && 'trash' !== $post->post_status;
        }

        return false;
    }
}
