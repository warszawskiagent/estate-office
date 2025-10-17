<?php
/**
 * Frontendowe kalkulatory EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Frontend_Calculators
 */
class Estate_Office_Frontend_Calculators {

    /**
     * Serwis kalkulacyjny.
     *
     * @var Estate_Office_Calculators_Service
     */
    private Estate_Office_Calculators_Service $service;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Calculators_Service|null $service Serwis kalkulacyjny.
     */
    public function __construct( ?Estate_Office_Calculators_Service $service = null ) {
        $this->service = $service ?? new Estate_Office_Calculators_Service();
    }

    /**
     * Rejestracja hooków frontendowych.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'init', [ __CLASS__, 'maybe_restore_pages' ], 6 );
    }

    /**
     * Rejestruje shortcody kalkulatorów.
     *
     * @return void
     */
    public function register_shortcodes() : void {
        add_shortcode( 'estate_office_notary_calculator', [ $this, 'render_notary_calculator' ] );
        add_shortcode( 'estate_office_mortgage_calculator', [ $this, 'render_mortgage_calculator' ] );
    }

    /**
     * Rejestruje assety kalkulatorów.
     *
     * @return void
     */
    public function register_assets() : void {
        wp_register_style( 'estate-office-calculators', false, [], ESTATE_OFFICE_VERSION );

        $css = '.estate-office-calculators{font-family:var(--eo-font,inherit);margin:2rem 0;padding:1.75rem;border-radius:18px;'
            . 'background:#ffffff;box-shadow:0 10px 28px rgba(15,23,42,.1);display:grid;gap:1.5rem;}'
            . '.estate-office-calculators__title{margin:0;font-size:1.45rem;font-weight:600;color:#0f172a;}'
            . '.estate-office-calculators__form{display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));}'
            . '.estate-office-calculators label{display:flex;flex-direction:column;font-size:0.85rem;color:#475569;gap:0.4rem;}'
            . '.estate-office-calculators input,.estate-office-calculators select{padding:0.6rem 0.75rem;border-radius:10px;bor'
            . 'der:1px solid #cbd5f5;font-size:1rem;box-shadow:0 1px 2px rgba(15,23,42,.04);}'
            . '.estate-office-calculators__results{display:grid;gap:0.75rem;}'
            . '.estate-office-calculators__row{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:0.6'
            . 'rem 0;border-bottom:1px solid #e2e8f0;}'
            . '.estate-office-calculators__row:last-child{border-bottom:none;}'
            . '.estate-office-calculators__label{font-weight:600;color:#1e293b;}'
            . '.estate-office-calculators__value{font-variant-numeric:tabular-nums;font-weight:600;color:#2563eb;}'
            . '.estate-office-calculators__hint{font-size:0.8rem;color:#64748b;margin:0;}'
            . '.estate-office-calculators__section{display:grid;gap:1.25rem;}'
            . '@media (max-width:640px){.estate-office-calculators{padding:1.25rem;border-radius:14px;}}';

        wp_add_inline_style( 'estate-office-calculators', $css );

        wp_register_script(
            'estate-office-calculators',
            ESTATE_OFFICE_URL . 'assets/js/estate-office-calculators.js',
            [],
            ESTATE_OFFICE_VERSION,
            true
        );

        wp_localize_script(
            'estate-office-calculators',
            'estateOfficeCalculators',
            $this->service->get_script_config()
        );
    }

    /**
     * Renderuje kalkulator notarialny.
     *
     * @param array<string,string> $atts Atrybuty shortcode.
     *
     * @return string
     */
    public function render_notary_calculator( array $atts ) : string {
        $atts = shortcode_atts(
            [
                'price'        => '0',
                'transaction'  => 'SPRZEDAŻ',
                'show_hint'    => 'yes',
            ],
            $atts,
            'estate_office_notary_calculator'
        );

        $price       = max( 0.0, (float) $atts['price'] );
        $transaction = strtoupper( sanitize_text_field( $atts['transaction'] ) );
        $result      = $this->service->calculate_notary_fee( $price, $transaction );

        ob_start();

        wp_enqueue_style( 'estate-office-calculators' );
        wp_enqueue_script( 'estate-office-calculators' );

        ?>
        <section class="estate-office-calculators estate-office-calculator estate-office-calculator--notary" data-calculator="notary" data-transaction="<?php echo esc_attr( $transaction ); ?>">
            <div class="estate-office-calculators__section">
                <h3 class="estate-office-calculators__title"><?php esc_html_e( 'Kalkulator kosztów notarialnych', 'estate-office' ); ?></h3>
                <form class="estate-office-calculators__form" method="post" action="#">
                    <label>
                        <span><?php esc_html_e( 'Cena nieruchomości (PLN)', 'estate-office' ); ?></span>
                        <input type="number" step="1000" min="0" value="<?php echo esc_attr( $price ); ?>" data-calculator-input="price" />
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></span>
                        <select data-calculator-input="transaction">
                            <?php foreach ( [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ] as $option ) : ?>
                                <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $transaction, $option ); ?>><?php echo esc_html( $option ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </div>
            <div class="estate-office-calculators__section">
                <div class="estate-office-calculators__results" data-calculator-results>
                    <?php $this->render_result_row( __( 'Taksa notarialna (netto)', 'estate-office' ), $result['base_fee'], 'base_fee' ); ?>
                    <?php $this->render_result_row( __( 'VAT 23%', 'estate-office' ), $result['vat'], 'vat' ); ?>
                    <?php $this->render_result_row( __( 'Taksa notarialna (brutto)', 'estate-office' ), $result['gross_notary'], 'gross_notary' ); ?>
                    <?php $this->render_result_row( __( 'Podatek PCC 2%', 'estate-office' ), $result['pcc'], 'pcc' ); ?>
                    <?php $this->render_result_row( __( 'Wpis do księgi wieczystej', 'estate-office' ), $result['land_register'], 'land_register' ); ?>
                    <?php $this->render_result_row( __( 'Wypisy aktu notarialnego', 'estate-office' ), $result['extracts'], 'extracts' ); ?>
                    <?php $this->render_result_row( __( 'Łączny koszt', 'estate-office' ), $result['total'], 'total', true ); ?>
                </div>
                <?php if ( 'yes' === strtolower( $atts['show_hint'] ) ) : ?>
                    <p class="estate-office-calculators__hint"><?php esc_html_e( 'Wyniki mają charakter poglądowy – ostateczne kwoty ustala notariusz.', 'estate-office' ); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renderuje kalkulator kredytowy.
     *
     * @param array<string,string> $atts Atrybuty shortcode.
     *
     * @return string
     */
    public function render_mortgage_calculator( array $atts ) : string {
        $atts = shortcode_atts(
            [
                'price'        => '0',
                'down_payment' => '200000',
                'interest'     => '6.5',
                'years'        => '25',
            ],
            $atts,
            'estate_office_mortgage_calculator'
        );

        $price        = max( 0.0, (float) $atts['price'] );
        $down_payment = max( 0.0, (float) $atts['down_payment'] );
        $interest     = max( 0.0, (float) $atts['interest'] );
        $years        = max( 1, (int) $atts['years'] );

        $result = $this->service->calculate_mortgage( $price, $down_payment, $interest, $years );

        ob_start();

        wp_enqueue_style( 'estate-office-calculators' );
        wp_enqueue_script( 'estate-office-calculators' );

        ?>
        <section class="estate-office-calculators estate-office-calculator estate-office-calculator--mortgage" data-calculator="mortgage">
            <div class="estate-office-calculators__section">
                <h3 class="estate-office-calculators__title"><?php esc_html_e( 'Kalkulator kredytu hipotecznego', 'estate-office' ); ?></h3>
                <form class="estate-office-calculators__form" method="post" action="#">
                    <label>
                        <span><?php esc_html_e( 'Cena nieruchomości (PLN)', 'estate-office' ); ?></span>
                        <input type="number" step="1000" min="0" value="<?php echo esc_attr( $price ); ?>" data-calculator-input="price" />
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Wkład własny (PLN)', 'estate-office' ); ?></span>
                        <input type="number" step="1000" min="0" value="<?php echo esc_attr( $down_payment ); ?>" data-calculator-input="down_payment" />
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Oprocentowanie (%)', 'estate-office' ); ?></span>
                        <input type="number" step="0.1" min="0" value="<?php echo esc_attr( $interest ); ?>" data-calculator-input="interest" />
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Okres kredytowania (lata)', 'estate-office' ); ?></span>
                        <input type="number" step="1" min="1" max="40" value="<?php echo esc_attr( $years ); ?>" data-calculator-input="years" />
                    </label>
                </form>
            </div>
            <div class="estate-office-calculators__section">
                <div class="estate-office-calculators__results" data-calculator-results>
                    <?php $this->render_result_row( __( 'Kwota kredytu', 'estate-office' ), $result['principal'], 'principal' ); ?>
                    <?php $this->render_result_row( __( 'Rata miesięczna', 'estate-office' ), $result['monthly_payment'], 'monthly_payment' ); ?>
                    <?php $this->render_result_row( __( 'Suma spłat', 'estate-office' ), $result['total_payment'], 'total_payment' ); ?>
                    <?php $this->render_result_row( __( 'Łączne odsetki', 'estate-office' ), $result['total_interest'], 'total_interest', true ); ?>
                </div>
                <p class="estate-office-calculators__hint"><?php esc_html_e( 'Wynik nie stanowi oferty banku. Skonsultuj warunki z doradcą kredytowym.', 'estate-office' ); ?></p>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renderuje wiersz wynikowy.
     *
     * @param string $label   Etykieta.
     * @param float  $amount  Kwota.
     * @param string $context Kontekst (opcjonalny).
     *
     * @return void
     */
    private function render_result_row( string $label, float $amount, string $key, bool $is_summary = false ) : void {
        $classes = [ 'estate-office-calculators__row' ];

        if ( $is_summary ) {
            $classes[] = 'is-summary';
        }

        printf(
            '<div class="%1$s" data-calculator-row="%4$s"><span class="estate-office-calculators__label">%2$s</span><span class="estate-office-calculators__value" data-calculator-value>%3$s</span></div>',
            esc_attr( implode( ' ', $classes ) ),
            esc_html( $label ),
            esc_html( number_format_i18n( $amount, 2 ) ),
            esc_attr( $key )
        );
    }

    /**
     * Upewnia się, że strony z kalkulatorami istnieją.
     *
     * @return void
     */
    public static function ensure_calculator_pages() : void {
        self::ensure_calculator_page(
            'estate_office_notary_calculator_page_id',
            [
                'title'     => __( 'Kalkulator notarialny', 'estate-office' ),
                'slug'      => 'kalkulator-notarialny',
                'shortcode' => '[estate_office_notary_calculator]'
            ]
        );

        self::ensure_calculator_page(
            'estate_office_mortgage_calculator_page_id',
            [
                'title'     => __( 'Kalkulator kredytowy', 'estate-office' ),
                'slug'      => 'kalkulator-kredytowy',
                'shortcode' => '[estate_office_mortgage_calculator]'
            ]
        );
    }

    /**
     * Odtwarza brakujące strony kalkulatorów.
     *
     * @return void
     */
    public static function maybe_restore_pages() : void {
        $notary_page   = (int) get_option( 'estate_office_notary_calculator_page_id', 0 );
        $mortgage_page = (int) get_option( 'estate_office_mortgage_calculator_page_id', 0 );

        if ( $notary_page <= 0 || ! self::is_page_valid( $notary_page ) ) {
            self::ensure_calculator_pages();
            return;
        }

        if ( $mortgage_page <= 0 || ! self::is_page_valid( $mortgage_page ) ) {
            self::ensure_calculator_pages();
        }
    }

    /**
     * Zapewnia istnienie pojedynczej strony kalkulatora.
     *
     * @param string               $option Option name.
     * @param array<string,string> $args   Parametry strony.
     *
     * @return void
     */
    private static function ensure_calculator_page( string $option, array $args ) : void {
        $page_id = (int) get_option( $option, 0 );

        if ( $page_id > 0 && self::is_page_valid( $page_id ) ) {
            return;
        }

        $page_data = [
            'post_title'   => sanitize_text_field( $args['title'] ),
            'post_name'    => sanitize_title( $args['slug'] ),
            'post_content' => wp_kses_post( $args['shortcode'] ),
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ];

        if ( $page_id > 0 ) {
            $page_data['ID'] = $page_id;
            wp_update_post( $page_data );
        } else {
            $page_id = wp_insert_post( $page_data );
        }

        if ( ! is_wp_error( $page_id ) && $page_id > 0 ) {
            update_option( $option, (int) $page_id );
        }
    }

    /**
     * Sprawdza czy wskazana strona istnieje i nie została przeniesiona do kosza.
     *
     * @param int $page_id ID strony.
     *
     * @return bool
     */
    private static function is_page_valid( int $page_id ) : bool {
        $page = get_post( $page_id );

        if ( ! $page || 'trash' === $page->post_status ) {
            return false;
        }

        return 'page' === $page->post_type;
    }
}
