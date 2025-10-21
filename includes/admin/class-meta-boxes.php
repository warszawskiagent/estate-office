<?php
namespace EstateOffice\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers and renders meta boxes for plugin post types.
 */
class Meta_Boxes {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_estate_property', [ $this, 'save_property_meta' ], 10, 2 );
        add_action( 'save_post_estate_contract', [ $this, 'save_contract_meta' ], 10, 2 );
        add_action( 'save_post_estate_client', [ $this, 'save_client_meta' ], 10, 2 );
        add_action( 'save_post_estate_search', [ $this, 'save_search_meta' ], 10, 2 );
    }

    /**
     * Registers meta boxes for the supported post types.
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'estate_office_property_details',
            __( 'Szczegóły nieruchomości', 'estate-office' ),
            [ $this, 'render_property_meta_box' ],
            'estate_property',
            'normal',
            'high'
        );

        add_meta_box(
            'estate_office_contract_details',
            __( 'Szczegóły umowy', 'estate-office' ),
            [ $this, 'render_contract_meta_box' ],
            'estate_contract',
            'normal',
            'high'
        );

        add_meta_box(
            'estate_office_client_details',
            __( 'Szczegóły klienta', 'estate-office' ),
            [ $this, 'render_client_meta_box' ],
            'estate_client',
            'normal',
            'high'
        );

        add_meta_box(
            'estate_office_search_details',
            __( 'Szczegóły poszukiwania', 'estate-office' ),
            [ $this, 'render_search_meta_box' ],
            'estate_search',
            'normal',
            'high'
        );
    }

    /**
     * Renders nonce field shared by all meta boxes.
     */
    private function render_nonce( string $action ): void {
        wp_nonce_field( $action, 'estate_office_meta_nonce' );
    }

    /**
     * Returns stored meta for a post.
     */
    private function get_meta( int $post_id ): array {
        $stored = get_post_meta( $post_id );
        $meta   = [];

        foreach ( $stored as $key => $values ) {
            if ( 0 !== strpos( $key, 'estate_office_' ) ) {
                continue;
            }

            $meta_key          = substr( $key, strlen( 'estate_office_' ) );
            $meta[ $meta_key ] = maybe_unserialize( $values[0] );
        }

        return $meta;
    }

    /**
     * Renders property meta box.
     */
    public function render_property_meta_box( \WP_Post $post ): void {
        $this->render_nonce( 'estate_office_save_property' );
        $meta            = $this->get_meta( $post->ID );
        $property_type   = isset( $meta['property_type'] ) ? $meta['property_type'] : 'mieszkanie';
        $lot_shape       = isset( $meta['lot_shape'] ) ? $meta['lot_shape'] : '';
        $flags           = isset( $meta['flags'] ) && is_array( $meta['flags'] ) ? $meta['flags'] : [];
        ?>
        <div class="estate-office-meta estate-office-property-meta" data-property-type="<?php echo esc_attr( $property_type ); ?>">
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'offer_number', __( 'Numer oferty', 'estate-office' ) );
                $this->render_select_field( $meta, 'transaction_type', __( 'Typ transakcji', 'estate-office' ), [
                    'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
                    'kupno'    => __( 'Kupno', 'estate-office' ),
                    'wynajem'  => __( 'Wynajem', 'estate-office' ),
                    'najem'    => __( 'Najem', 'estate-office' ),
                ] );
                $this->render_select_field( $meta, 'property_type', __( 'Rodzaj nieruchomości', 'estate-office' ), [
                    'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
                    'dom'        => __( 'Dom', 'estate-office' ),
                    'dzialka'    => __( 'Działka', 'estate-office' ),
                    'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
                ] );
                $this->render_input_field( $meta, 'google_place_id', __( 'Identyfikator Google Place', 'estate-office' ) );
                ?>
            </div>

            <h4><?php esc_html_e( 'Dane adresowe', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'street', __( 'Ulica', 'estate-office' ), 'text', [ 'data-visible' => 'mieszkanie,lokal,dom' ] );
                $this->render_input_field( $meta, 'building_number', __( 'Numer', 'estate-office' ), 'text', [ 'data-visible' => 'mieszkanie,lokal,dom' ] );
                $this->render_input_field( $meta, 'unit_number', __( 'Lokal', 'estate-office' ), 'text', [ 'data-visible' => 'mieszkanie,lokal' ] );
                $this->render_input_field( $meta, 'postal_code', __( 'Kod pocztowy', 'estate-office' ), 'text', [ 'data-visible' => 'mieszkanie,lokal,dom,dzialka' ] );
                $this->render_input_field( $meta, 'district', __( 'Dzielnica', 'estate-office' ), 'text', [ 'data-visible' => 'mieszkanie,lokal' ] );
                $this->render_input_field( $meta, 'city', __( 'Miasto', 'estate-office' ), 'text', [ 'data-visible' => 'mieszkanie,lokal,dom,dzialka' ] );
                $this->render_input_field( $meta, 'county', __( 'Powiat', 'estate-office' ), 'text', [ 'data-visible' => 'dom,dzialka' ] );
                $this->render_input_field( $meta, 'district_area', __( 'Obręb', 'estate-office' ), 'text', [ 'data-visible' => 'dom,dzialka' ] );
                $this->render_input_field( $meta, 'plot_number', __( 'Numer działki', 'estate-office' ), 'text', [ 'data-visible' => 'dom,dzialka' ] );
                ?>
            </div>

            <div class="estate-office-field-grid">
                <div class="estate-office-field" data-visible="dom">
                    <label for="estate_office_house_type"><?php esc_html_e( 'Typ domu', 'estate-office' ); ?></label>
                    <select id="estate_office_house_type" name="estate_office_meta[house_type]">
                        <?php
                        $house_type = isset( $meta['house_type'] ) ? $meta['house_type'] : '';
                        $options    = [
                            ''             => __( 'Wybierz', 'estate-office' ),
                            'wolnostojacy'  => __( 'Wolnostojący', 'estate-office' ),
                            'blizniak'      => __( 'Bliźniak', 'estate-office' ),
                            'szeregowiec'   => __( 'Szeregowiec', 'estate-office' ),
                            'wielorodzinny' => __( 'Wielorodzinny', 'estate-office' ),
                        ];
                        foreach ( $options as $option_value => $label ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option_value ), esc_html( $label ), selected( $house_type, $option_value, false ) );
                        }
                        ?>
                    </select>
                </div>
                <?php
                $this->render_input_field( $meta, 'land_and_mortgage_register', __( 'Numer księgi wieczystej', 'estate-office' ) );
                ?>
                <div class="estate-office-field estate-office-checkbox">
                    <label>
                        <input type="checkbox" name="estate_office_meta[no_lmr]" value="1" <?php checked( ! empty( $meta['no_lmr'] ) ); ?> />
                        <?php esc_html_e( 'Brak księgi wieczystej', 'estate-office' ); ?>
                    </label>
                </div>
                <div class="estate-office-field">
                    <label for="estate_office_legal_status"><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></label>
                    <select id="estate_office_legal_status" name="estate_office_meta[legal_status]">
                        <?php
                        $legal_status = isset( $meta['legal_status'] ) ? $meta['legal_status'] : '';
                        $options      = [
                            ''              => __( 'Wybierz', 'estate-office' ),
                            'wlasnosc'      => __( 'Własność', 'estate-office' ),
                            'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
                            'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
                            'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
                            'inne'          => __( 'Inne', 'estate-office' ),
                        ];
                        foreach ( $options as $option_value => $label ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option_value ), esc_html( $label ), selected( $legal_status, $option_value, false ) );
                        }
                        ?>
                    </select>
                </div>
            </div>

            <h4><?php esc_html_e( 'Mapa Google', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'map_lat', __( 'Szerokość geograficzna', 'estate-office' ) );
                $this->render_input_field( $meta, 'map_lng', __( 'Długość geograficzna', 'estate-office' ) );
                $this->render_textarea_field( $meta, 'map_notes', __( 'Uwagi do lokalizacji', 'estate-office' ) );
                ?>
            </div>

            <h4><?php esc_html_e( 'Dane nieruchomości', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'price', __( 'Cena', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                $this->render_input_field( $meta, 'rent', __( 'Czynsz administracyjny', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                $this->render_input_field( $meta, 'area', __( 'Powierzchnia (m²)', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                $this->render_input_field( $meta, 'price_sqm', __( 'Cena za m²', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0', 'readonly' => 'readonly' ] );
                $this->render_input_field( $meta, 'year_built', __( 'Rok budowy', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,dom,lokal' ] );
                $this->render_input_field( $meta, 'floor', __( 'Piętro', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,lokal' ] );
                $this->render_input_field( $meta, 'floors', __( 'Liczba pięter', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,dom,lokal' ] );
                $this->render_input_field( $meta, 'rooms', __( 'Liczba pokoi', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,dom,lokal' ] );
                $this->render_input_field( $meta, 'bedrooms', __( 'Liczba sypialni', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,dom' ] );
                $this->render_input_field( $meta, 'bathrooms', __( 'Liczba łazienek', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,dom,lokal' ] );
                $this->render_input_field( $meta, 'toilets', __( 'Liczba toalet', 'estate-office' ), 'number', [ 'data-visible' => 'mieszkanie,dom,lokal' ] );
                ?>
                <div class="estate-office-field" data-visible="dzialka">
                    <label for="estate_office_lot_shape"><?php esc_html_e( 'Kształt działki', 'estate-office' ); ?></label>
                    <select id="estate_office_lot_shape" name="estate_office_meta[lot_shape]">
                        <?php
                        $options = [
                            ''            => __( 'Wybierz', 'estate-office' ),
                            'regularny'   => __( 'Regularny', 'estate-office' ),
                            'nieregularny'=> __( 'Nieregularny', 'estate-office' ),
                        ];
                        foreach ( $options as $option_value => $label ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option_value ), esc_html( $label ), selected( $lot_shape, $option_value, false ) );
                        }
                        ?>
                    </select>
                </div>
                <?php
                $this->render_input_field( $meta, 'lot_length', __( 'Długość działki (m)', 'estate-office' ), 'number', [ 'data-visible' => 'dzialka', 'data-lot-shape' => 'regularny', 'step' => '0.01', 'min' => '0' ] );
                $this->render_input_field( $meta, 'lot_width', __( 'Szerokość działki (m)', 'estate-office' ), 'number', [ 'data-visible' => 'dzialka', 'data-lot-shape' => 'regularny', 'step' => '0.01', 'min' => '0' ] );
                ?>
            </div>
            <div class="estate-office-field" data-visible="dzialka" data-lot-shape="nieregularny">
                <label for="estate_office_lot_notes"><?php esc_html_e( 'Opis wymiarów działki', 'estate-office' ); ?></label>
                <textarea id="estate_office_lot_notes" name="estate_office_meta[lot_notes]" rows="3"><?php echo isset( $meta['lot_notes'] ) ? esc_textarea( $meta['lot_notes'] ) : ''; ?></textarea>
            </div>

            <h4><?php esc_html_e( 'Opiekun oferty', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php $this->render_agent_field( $meta, 'agent_id', __( 'Przypisany agent', 'estate-office' ) ); ?>
            </div>

            <h4><?php esc_html_e( 'Szczegóły budynku', 'estate-office' ); ?></h4>
            <div class="estate-office-field-group" data-visible="mieszkanie,dom,lokal">
                <?php
                $this->render_input_field( $meta, 'finish_status', __( 'Stan wykończenia', 'estate-office' ) );
                $this->render_checkbox_group( 'exposure', [
                    'polnoc'   => __( 'Północ', 'estate-office' ),
                    'poludnie' => __( 'Południe', 'estate-office' ),
                    'wschod'   => __( 'Wschód', 'estate-office' ),
                    'zachod'   => __( 'Zachód', 'estate-office' ),
                ], isset( $meta['exposure'] ) && is_array( $meta['exposure'] ) ? $meta['exposure'] : [], __( 'Ekspozycja', 'estate-office' ) );
                $this->render_checkbox_group( 'view', [
                    'miasto'   => __( 'Miasto', 'estate-office' ),
                    'panorama' => __( 'Panorama', 'estate-office' ),
                    'park'     => __( 'Park', 'estate-office' ),
                    'inne'     => __( 'Inne', 'estate-office' ),
                ], isset( $meta['view'] ) && is_array( $meta['view'] ) ? $meta['view'] : [], __( 'Widok', 'estate-office' ) );
                ?>
                <div class="estate-office-field estate-office-checkbox">
                    <label>
                        <input type="checkbox" name="estate_office_meta[attic]" value="1" <?php checked( ! empty( $meta['attic'] ) ); ?> />
                        <?php esc_html_e( 'Poddasze', 'estate-office' ); ?>
                    </label>
                </div>
                <div class="estate-office-field estate-office-checkbox" data-visible="mieszkanie,lokal">
                    <label>
                        <input type="checkbox" name="estate_office_meta[multi_level]" value="1" <?php checked( ! empty( $meta['multi_level'] ) ); ?> />
                        <?php esc_html_e( 'Wielopoziomowe', 'estate-office' ); ?>
                    </label>
                </div>
                <?php
                $this->render_checkbox_group( 'layout', [
                    'dwustronne'      => __( 'Dwustronne', 'estate-office' ),
                    'jednostronne'    => __( 'Jednostronne', 'estate-office' ),
                    'przechodnie'     => __( 'Przechodnie', 'estate-office' ),
                    'otwarta_kuchnia' => __( 'Otwarta kuchnia', 'estate-office' ),
                ], isset( $meta['layout'] ) && is_array( $meta['layout'] ) ? $meta['layout'] : [], __( 'Rozkład', 'estate-office' ) );
                ?>
                <div class="estate-office-field">
                    <label for="estate_office_kitchen_type"><?php esc_html_e( 'Kuchnia', 'estate-office' ); ?></label>
                    <select id="estate_office_kitchen_type" name="estate_office_meta[kitchen_type]">
                        <?php
                        $kitchen_type = isset( $meta['kitchen_type'] ) ? $meta['kitchen_type'] : '';
                        $options      = [
                            ''          => __( 'Wybierz', 'estate-office' ),
                            'aneks'     => __( 'Aneks', 'estate-office' ),
                            'oddzielna' => __( 'Oddzielna', 'estate-office' ),
                            'z_salonem' => __( 'Z salonem', 'estate-office' ),
                        ];
                        foreach ( $options as $option_value => $label ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option_value ), esc_html( $label ), selected( $kitchen_type, $option_value, false ) );
                        }
                        ?>
                    </select>
                </div>
                <div class="estate-office-field estate-office-checkbox">
                    <label>
                        <input type="checkbox" name="estate_office_meta[parking_available]" value="1" <?php checked( ! empty( $meta['parking_available'] ) ); ?> />
                        <?php esc_html_e( 'Miejsce parkingowe', 'estate-office' ); ?>
                    </label>
                </div>
                <?php
                $this->render_checkbox_group( 'parking_options', [
                    'naziemne' => __( 'Naziemne', 'estate-office' ),
                    'podziemne' => __( 'Podziemne', 'estate-office' ),
                    'garaz'     => __( 'Garaż', 'estate-office' ),
                ], isset( $meta['parking_options'] ) && is_array( $meta['parking_options'] ) ? $meta['parking_options'] : [], __( 'Rodzaj parkingu', 'estate-office' ) );
                ?>
            </div>

            <h4><?php esc_html_e( 'Media i udogodnienia', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'heating', __( 'Ogrzewanie', 'estate-office' ) );
                $this->render_input_field( $meta, 'water', __( 'Woda', 'estate-office' ) );
                $this->render_input_field( $meta, 'sewage', __( 'Kanalizacja', 'estate-office' ) );
                ?>
                <div class="estate-office-field estate-office-checkbox">
                    <label>
                        <input type="checkbox" name="estate_office_meta[gaz]" value="1" <?php checked( ! empty( $meta['gaz'] ) ); ?> />
                        <?php esc_html_e( 'Gaz', 'estate-office' ); ?>
                    </label>
                </div>
            </div>
            <?php
            $this->render_checkbox_group( 'facilities', [
                'winda'                 => __( 'Winda', 'estate-office' ),
                'umeblowanie_pelne'     => __( 'Umeblowanie - tak', 'estate-office' ),
                'umeblowanie_czesciowe' => __( 'Umeblowanie - częściowe', 'estate-office' ),
                'klimatyzacja'          => __( 'Klimatyzacja', 'estate-office' ),
                'monitoring'            => __( 'Monitoring/Ochrona', 'estate-office' ),
                'recepcja'              => __( 'Recepcja', 'estate-office' ),
                'teren_zamkniety'       => __( 'Teren zamknięty', 'estate-office' ),
                'domofon'               => __( 'Domofon', 'estate-office' ),
            ], isset( $meta['facilities'] ) && is_array( $meta['facilities'] ) ? $meta['facilities'] : [], __( 'Udogodnienia', 'estate-office' ) );

            $this->render_checkbox_group( 'equipment', [
                'pralka'    => __( 'Pralka', 'estate-office' ),
                'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
                'lodowka'   => __( 'Lodówka', 'estate-office' ),
                'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
                'piekarnik' => __( 'Piekarnik', 'estate-office' ),
                'telewizor' => __( 'Telewizor', 'estate-office' ),
                'mikrofala' => __( 'Mikrofala', 'estate-office' ),
            ], isset( $meta['equipment'] ) && is_array( $meta['equipment'] ) ? $meta['equipment'] : [], __( 'Wyposażenie', 'estate-office' ) );
            ?>

            <h4><?php esc_html_e( 'Powierzchnie dodatkowe', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid estate-office-toggle-group">
                <?php
                $this->render_toggle_field( $meta, 'balcony', __( 'Balkon', 'estate-office' ), [
                    'count' => __( 'Liczba balkonów', 'estate-office' ),
                    'area'  => __( 'Powierzchnia balkonu (m²)', 'estate-office' ),
                ] );
                $this->render_toggle_field( $meta, 'terrace', __( 'Taras', 'estate-office' ), [
                    'count' => __( 'Liczba tarasów', 'estate-office' ),
                    'area'  => __( 'Powierzchnia tarasu (m²)', 'estate-office' ),
                ] );
                $this->render_toggle_field( $meta, 'basement', __( 'Piwnica', 'estate-office' ), [
                    'area' => __( 'Powierzchnia piwnicy (m²)', 'estate-office' ),
                ] );
                $this->render_toggle_field( $meta, 'storage', __( 'Komórka lokatorska', 'estate-office' ), [
                    'area' => __( 'Powierzchnia komórki (m²)', 'estate-office' ),
                ] );
                $this->render_toggle_field( $meta, 'garden', __( 'Ogródek', 'estate-office' ), [
                    'area' => __( 'Powierzchnia ogródka (m²)', 'estate-office' ),
                ] );
                ?>
            </div>

            <h4><?php esc_html_e( 'Multimedia', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'gallery', __( 'Galeria (ID załączników, przecinki)', 'estate-office' ) );
                $this->render_input_field( $meta, 'plan2d', __( 'Rzut 2D (ID załącznika)', 'estate-office' ) );
                $this->render_input_field( $meta, 'plan3d', __( 'Rzut 3D (ID załącznika)', 'estate-office' ) );
                $this->render_input_field( $meta, 'video_url', __( 'Link do filmu', 'estate-office' ), 'url' );
                $this->render_input_field( $meta, 'virtual_tour_url', __( 'Link do wirtualnego spaceru', 'estate-office' ), 'url' );
                ?>
            </div>

            <h4><?php esc_html_e( 'Znaczniki oferty', 'estate-office' ); ?></h4>
            <div class="estate-office-field estate-office-checkboxes estate-office-flags">
                <?php
                $flag_options = [
                    'new_offer'    => __( 'Nowa oferta', 'estate-office' ),
                    'exclusive'    => __( 'Wyłączność', 'estate-office' ),
                    'sold'         => __( 'Sprzedane', 'estate-office' ),
                    'rented'       => __( 'Wynajęte', 'estate-office' ),
                    'new_price'    => __( 'Nowa cena', 'estate-office' ),
                    'no_commision' => __( 'Bez prowizji', 'estate-office' ),
                    'mls'          => __( 'Oferta MLS', 'estate-office' ),
                    'premium'      => __( 'Premium', 'estate-office' ),
                    'export_www'   => __( 'Eksport na WWW', 'estate-office' ),
                ];

                foreach ( $flag_options as $flag_key => $label ) {
                    $transaction_restriction = '';
                    if ( 'sold' === $flag_key ) {
                        $transaction_restriction = 'sprzedaz';
                    }
                    if ( 'rented' === $flag_key ) {
                        $transaction_restriction = 'wynajem';
                    }

                    printf(
                        '<label data-transaction="%5$s"><input type="checkbox" name="estate_office_meta[flags][]" value="%1$s" %3$s /> %2$s</label>',
                        esc_attr( $flag_key ),
                        esc_html( $label ),
                        checked( in_array( $flag_key, $flags, true ), true, false ),
                        esc_attr( $flag_key ),
                        esc_attr( $transaction_restriction )
                    );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders contract meta box.
     */
    public function render_contract_meta_box( \WP_Post $post ): void {
        $this->render_nonce( 'estate_office_save_contract' );
        $meta          = $this->get_meta( $post->ID );
        $stage_history = get_post_meta( $post->ID, 'estate_office_stage_history', true );
        $stage_history = is_array( $stage_history ) ? $stage_history : [];
        ?>
        <div class="estate-office-meta estate-office-contract-meta">
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'contract_number', __( 'Numer umowy', 'estate-office' ) );
                $this->render_select_field( $meta, 'transaction_type', __( 'Typ transakcji', 'estate-office' ), [
                    'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
                    'kupno'    => __( 'Kupno', 'estate-office' ),
                    'wynajem'  => __( 'Wynajem', 'estate-office' ),
                    'najem'    => __( 'Najem', 'estate-office' ),
                ] );
                $this->render_input_field( $meta, 'start_date', __( 'Data zawarcia', 'estate-office' ), 'date' );
                $this->render_input_field( $meta, 'end_date', __( 'Data zakończenia', 'estate-office' ), 'date', [ 'data-disabled-toggle' => 'no_end_date' ] );
                ?>
                <div class="estate-office-field estate-office-checkbox">
                    <label>
                        <input type="checkbox" name="estate_office_meta[no_end_date]" value="1" <?php checked( ! empty( $meta['no_end_date'] ) ); ?> />
                        <?php esc_html_e( 'Umowa bezterminowa', 'estate-office' ); ?>
                    </label>
                </div>
                <?php
                $this->render_input_field( $meta, 'commission', __( 'Wysokość prowizji', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                $this->render_select_field( $meta, 'commission_unit', __( 'Jednostka prowizji', 'estate-office' ), [
                    'percent' => '%',
                    'pln'     => __( 'PLN', 'estate-office' ),
                    'eur'     => __( 'EUR', 'estate-office' ),
                    'usd'     => __( 'USD', 'estate-office' ),
                ] );
                $this->render_select_field( $meta, 'property_type', __( 'Rodzaj nieruchomości', 'estate-office' ), [
                    ''           => __( 'Wybierz', 'estate-office' ),
                    'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
                    'dom'        => __( 'Dom', 'estate-office' ),
                    'dzialka'    => __( 'Działka', 'estate-office' ),
                    'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
                ] );
                ?>
            </div>

            <div class="estate-office-field-grid">
                <div class="estate-office-field">
                    <label for="estate_office_related_property"><?php esc_html_e( 'Powiązana nieruchomość', 'estate-office' ); ?></label>
                    <?php $this->render_post_select( 'estate_property', 'estate_office_meta[related_property]', isset( $meta['related_property'] ) ? (int) $meta['related_property'] : 0 ); ?>
                </div>
                <div class="estate-office-field">
                    <label for="estate_office_related_search"><?php esc_html_e( 'Powiązane poszukiwanie', 'estate-office' ); ?></label>
                    <?php $this->render_post_select( 'estate_search', 'estate_office_meta[related_search]', isset( $meta['related_search'] ) ? (int) $meta['related_search'] : 0 ); ?>
                </div>
            </div>

            <div class="estate-office-field-grid">
                <?php $this->render_agent_field( $meta, 'agent_id', __( 'Opiekun umowy', 'estate-office' ) ); ?>
            </div>

            <h4><?php esc_html_e( 'Aktualny etap', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_select_field( $meta, 'stage', __( 'Etap umowy', 'estate-office' ), $this->get_contract_stages() );
                $this->render_input_field( $meta, 'stage_date', __( 'Data etapu', 'estate-office' ), 'date' );
                $this->render_textarea_field( $meta, 'stage_notes', __( 'Notatki', 'estate-office' ) );
                ?>
            </div>

            <?php if ( ! empty( $stage_history ) ) : ?>
                <h4><?php esc_html_e( 'Historia etapów', 'estate-office' ); ?></h4>
                <ul class="estate-office-stage-history">
                    <?php foreach ( $stage_history as $item ) : ?>
                        <li>
                            <strong><?php echo esc_html( $item['date'] ?? '' ); ?></strong>
                            <span><?php echo esc_html( $this->get_contract_stages()[ $item['stage'] ] ?? $item['stage'] ); ?></span>
                            <?php if ( ! empty( $item['notes'] ) ) : ?>
                                <em><?php echo esc_html( $item['notes'] ); ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renders client meta box.
     */
    public function render_client_meta_box( \WP_Post $post ): void {
        $this->render_nonce( 'estate_office_save_client' );
        $meta        = $this->get_meta( $post->ID );
        $client_type = isset( $meta['client_type'] ) ? $meta['client_type'] : 'osoba';
        ?>
        <div class="estate-office-meta estate-office-client-meta" data-client-type="<?php echo esc_attr( $client_type ); ?>">
            <div class="estate-office-field-grid">
                <?php
                $this->render_select_field( $meta, 'client_type', __( 'Typ klienta', 'estate-office' ), [
                    'osoba' => __( 'Osoba fizyczna', 'estate-office' ),
                    'firma' => __( 'Firma', 'estate-office' ),
                ] );
                $this->render_input_field( $meta, 'first_name', __( 'Imię', 'estate-office' ), 'text', [ 'data-visible' => 'osoba' ] );
                $this->render_input_field( $meta, 'last_name', __( 'Nazwisko', 'estate-office' ), 'text', [ 'data-visible' => 'osoba' ] );
                $this->render_input_field( $meta, 'company_name', __( 'Nazwa firmy', 'estate-office' ), 'text', [ 'data-visible' => 'firma' ] );
                $this->render_input_field( $meta, 'company_representative', __( 'Reprezentant', 'estate-office' ), 'text', [ 'data-visible' => 'firma' ] );
                ?>
            </div>

            <h4><?php esc_html_e( 'Dane kontaktowe', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'phone', __( 'Telefon', 'estate-office' ) );
                $this->render_input_field( $meta, 'email', __( 'E-mail', 'estate-office' ), 'email' );
                $this->render_input_field( $meta, 'website', __( 'Strona WWW', 'estate-office' ), 'url', [ 'data-visible' => 'firma' ] );
                ?>
            </div>

            <div class="estate-office-field-grid">
                <?php $this->render_agent_field( $meta, 'agent_id', __( 'Opiekun klienta', 'estate-office' ) ); ?>
            </div>

            <h4><?php esc_html_e( 'Dane identyfikacyjne', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'pesel', __( 'PESEL', 'estate-office' ), 'text', [ 'data-visible' => 'osoba' ] );
                ?>
                <div class="estate-office-field" data-visible="osoba">
                    <label for="estate_office_document_type"><?php esc_html_e( 'Rodzaj dokumentu', 'estate-office' ); ?></label>
                    <select id="estate_office_document_type" name="estate_office_meta[document_type]">
                        <?php
                        $document_type = isset( $meta['document_type'] ) ? $meta['document_type'] : '';
                        $options       = [
                            ''            => __( 'Wybierz', 'estate-office' ),
                            'dowod'       => __( 'Dowód osobisty', 'estate-office' ),
                            'paszport'    => __( 'Paszport', 'estate-office' ),
                            'karta_pobytu'=> __( 'Karta pobytu', 'estate-office' ),
                        ];
                        foreach ( $options as $option_value => $label ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option_value ), esc_html( $label ), selected( $document_type, $option_value, false ) );
                        }
                        ?>
                    </select>
                </div>
                <?php
                $this->render_input_field( $meta, 'document_number', __( 'Numer dokumentu', 'estate-office' ), 'text', [ 'data-visible' => 'osoba' ] );
                $this->render_input_field( $meta, 'nip', __( 'NIP', 'estate-office' ), 'text', [ 'data-visible' => 'firma' ] );
                $this->render_input_field( $meta, 'krs', __( 'KRS', 'estate-office' ), 'text', [ 'data-visible' => 'firma' ] );
                $this->render_input_field( $meta, 'regon', __( 'REGON', 'estate-office' ), 'text', [ 'data-visible' => 'firma' ] );
                ?>
            </div>

            <h4><?php esc_html_e( 'Adres zamieszkania/rejestrowy', 'estate-office' ); ?></h4>
            <?php $this->render_address_group( $meta, 'address' ); ?>

            <div class="estate-office-field estate-office-checkbox">
                <label>
                    <input type="checkbox" name="estate_office_meta[same_correspondence]" value="1" <?php checked( ! empty( $meta['same_correspondence'] ) ); ?> />
                    <?php esc_html_e( 'Adres korespondencyjny taki sam', 'estate-office' ); ?>
                </label>
            </div>

            <div class="estate-office-correspondence" data-visible-when="same_correspondence" <?php echo ! empty( $meta['same_correspondence'] ) ? 'style="display:none;"' : ''; ?>>
                <h4><?php esc_html_e( 'Adres korespondencyjny', 'estate-office' ); ?></h4>
                <?php $this->render_address_group( $meta, 'correspondence' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders search meta box.
     */
    public function render_search_meta_box( \WP_Post $post ): void {
        $this->render_nonce( 'estate_office_save_search' );
        $meta = $this->get_meta( $post->ID );
        ?>
        <div class="estate-office-meta estate-office-search-meta">
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'search_number', __( 'Numer poszukiwania', 'estate-office' ) );
                $this->render_select_field( $meta, 'transaction_type', __( 'Typ transakcji', 'estate-office' ), [
                    'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
                    'kupno'    => __( 'Kupno', 'estate-office' ),
                    'wynajem'  => __( 'Wynajem', 'estate-office' ),
                    'najem'    => __( 'Najem', 'estate-office' ),
                ] );
                $this->render_select_field( $meta, 'property_type', __( 'Rodzaj nieruchomości', 'estate-office' ), [
                    ''           => __( 'Wybierz', 'estate-office' ),
                    'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
                    'dom'        => __( 'Dom', 'estate-office' ),
                    'dzialka'    => __( 'Działka', 'estate-office' ),
                    'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
                ] );
                ?>
            </div>

            <h4><?php esc_html_e( 'Budżet', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'budget_min', __( 'Cena od', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                $this->render_input_field( $meta, 'budget_max', __( 'Cena do', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                ?>
            </div>

            <div class="estate-office-field-grid">
                <?php $this->render_agent_field( $meta, 'agent_id', __( 'Opiekun poszukiwania', 'estate-office' ) ); ?>
            </div>

            <h4><?php esc_html_e( 'Metraż', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'area_min', __( 'Metraż od', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                $this->render_input_field( $meta, 'area_max', __( 'Metraż do', 'estate-office' ), 'number', [ 'step' => '0.01', 'min' => '0' ] );
                ?>
            </div>

            <h4><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'rooms_min', __( 'Od', 'estate-office' ), 'number', [ 'min' => '0' ] );
                $this->render_input_field( $meta, 'rooms_max', __( 'Do', 'estate-office' ), 'number', [ 'min' => '0' ] );
                ?>
            </div>

            <h4><?php esc_html_e( 'Pozostałe kryteria', 'estate-office' ); ?></h4>
            <div class="estate-office-field-grid">
                <?php
                $this->render_input_field( $meta, 'location', __( 'Preferowana lokalizacja', 'estate-office' ) );
                $this->render_input_field( $meta, 'preferred_floor', __( 'Preferowane piętro', 'estate-office' ) );
                $this->render_input_field( $meta, 'preferred_heating', __( 'Preferowane ogrzewanie', 'estate-office' ) );
                ?>
            </div>
            <?php
            $this->render_checkbox_group( 'facilities', [
                'winda'                 => __( 'Winda', 'estate-office' ),
                'umeblowanie_pelne'     => __( 'Umeblowanie - tak', 'estate-office' ),
                'umeblowanie_czesciowe' => __( 'Umeblowanie - częściowe', 'estate-office' ),
                'klimatyzacja'          => __( 'Klimatyzacja', 'estate-office' ),
                'monitoring'            => __( 'Monitoring/Ochrona', 'estate-office' ),
                'recepcja'              => __( 'Recepcja', 'estate-office' ),
                'teren_zamkniety'       => __( 'Teren zamknięty', 'estate-office' ),
                'domofon'               => __( 'Domofon', 'estate-office' ),
            ], isset( $meta['facilities'] ) && is_array( $meta['facilities'] ) ? $meta['facilities'] : [], __( 'Udogodnienia', 'estate-office' ) );
            $this->render_checkbox_group( 'equipment', [
                'pralka'    => __( 'Pralka', 'estate-office' ),
                'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
                'lodowka'   => __( 'Lodówka', 'estate-office' ),
                'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
                'piekarnik' => __( 'Piekarnik', 'estate-office' ),
                'telewizor' => __( 'Telewizor', 'estate-office' ),
                'mikrofala' => __( 'Mikrofala', 'estate-office' ),
            ], isset( $meta['equipment'] ) && is_array( $meta['equipment'] ) ? $meta['equipment'] : [], __( 'Wyposażenie', 'estate-office' ) );
            ?>
        </div>
        <?php
    }

    /**
     * Saves property meta.
     */
    public function save_property_meta( int $post_id, \WP_Post $post ): void {
        if ( ! $this->can_save( 'estate_office_save_property' ) ) {
            return;
        }

        $meta = $this->sanitize_meta_array( $_POST['estate_office_meta'] ?? [] );

        if ( isset( $meta['price'], $meta['area'] ) && $meta['price'] && $meta['area'] ) {
            $meta['price_sqm'] = round( (float) $meta['price'] / max( (float) $meta['area'], 0.0001 ), 2 );
        }

        $this->persist_meta( $post_id, $meta );
    }

    /**
     * Saves contract meta.
     */
    public function save_contract_meta( int $post_id, \WP_Post $post ): void {
        if ( ! $this->can_save( 'estate_office_save_contract' ) ) {
            return;
        }

        $meta = $this->sanitize_meta_array( $_POST['estate_office_meta'] ?? [] );

        $stage = $meta['stage'] ?? '';
        if ( $stage ) {
            $history   = get_post_meta( $post_id, 'estate_office_stage_history', true );
            $history   = is_array( $history ) ? $history : [];
            $last_item = end( $history );

            if ( ! $last_item || $last_item['stage'] !== $stage || $last_item['date'] !== ( $meta['stage_date'] ?? '' ) ) {
                $history[] = [
                    'date'  => $meta['stage_date'] ?? current_time( 'Y-m-d' ),
                    'stage' => $stage,
                    'notes' => $meta['stage_notes'] ?? '',
                ];
                update_post_meta( $post_id, 'estate_office_stage_history', $history );
            }
        }

        $this->persist_meta( $post_id, $meta );
    }

    /**
     * Saves client meta.
     */
    public function save_client_meta( int $post_id, \WP_Post $post ): void {
        if ( ! $this->can_save( 'estate_office_save_client' ) ) {
            return;
        }

        $meta = $this->sanitize_meta_array( $_POST['estate_office_meta'] ?? [] );
        $this->persist_meta( $post_id, $meta );

        $title = $meta['client_type'] === 'firma'
            ? ( $meta['company_name'] ?? '' )
            : trim( ( $meta['first_name'] ?? '' ) . ' ' . ( $meta['last_name'] ?? '' ) );

        if ( $title ) {
            remove_action( 'save_post_estate_client', [ $this, 'save_client_meta' ], 10 );
            wp_update_post(
                [
                    'ID'         => $post_id,
                    'post_title' => $title,
                ]
            );
            add_action( 'save_post_estate_client', [ $this, 'save_client_meta' ], 10, 2 );
        }
    }

    /**
     * Saves search meta.
     */
    public function save_search_meta( int $post_id, \WP_Post $post ): void {
        if ( ! $this->can_save( 'estate_office_save_search' ) ) {
            return;
        }

        $meta = $this->sanitize_meta_array( $_POST['estate_office_meta'] ?? [] );
        $this->persist_meta( $post_id, $meta );
    }

    /**
     * Checks whether the current request should trigger saving meta.
     */
    private function can_save( string $action ): bool {
        if ( ! isset( $_POST['estate_office_meta_nonce'] ) ) {
            return false;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['estate_office_meta_nonce'] ) ), $action ) ) {
            return false;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return false;
        }

        $post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
        if ( ! $post_id ) {
            return false;
        }

        return current_user_can( 'edit_post', $post_id );
    }

    /**
     * Sanitizes nested meta array.
     */
    private function sanitize_meta_array( array $input ): array {
        $output = [];

        foreach ( $input as $key => $value ) {
            if ( is_array( $value ) ) {
                $output[ $key ] = $this->sanitize_meta_array( $value );
                continue;
            }

            if ( in_array( $key, [ 'stage_notes', 'map_notes', 'lot_notes' ], true ) ) {
                $output[ $key ] = wp_kses_post( wp_unslash( $value ) );
            } else {
                $output[ $key ] = sanitize_text_field( wp_unslash( $value ) );
            }
        }

        return $output;
    }

    /**
     * Persists the provided meta data and removes missing keys.
     */
    private function persist_meta( int $post_id, array $meta ): void {
        $existing       = $this->get_meta( $post_id );
        $protected_keys = [ 'stage_history' ];

        foreach ( $existing as $key => $value ) {
            if ( in_array( $key, $protected_keys, true ) ) {
                continue;
            }

            if ( ! array_key_exists( $key, $meta ) ) {
                delete_post_meta( $post_id, 'estate_office_' . $key );
            }
        }

        foreach ( $meta as $key => $value ) {
            if ( in_array( $key, $protected_keys, true ) ) {
                continue;
            }
            update_post_meta( $post_id, 'estate_office_' . $key, $value );
        }
    }

    /**
     * Helper to render text inputs.
     */
    private function render_input_field( array $meta, string $key, string $label, string $type = 'text', array $attributes = [] ): void {
        $id      = 'estate_office_' . $key;
        $value   = isset( $meta[ $key ] ) ? $meta[ $key ] : '';
        $attr    = $this->build_attributes( $attributes );
        $visible = isset( $attributes['data-visible'] ) ? ' data-visibility="' . esc_attr( $attributes['data-visible'] ) . '"' : '';

        printf(
            '<div class="estate-office-field"%7$s><label for="%1$s">%2$s</label><input type="%3$s" id="%1$s" name="estate_office_meta[%4$s]" value="%5$s"%6$s /></div>',
            esc_attr( $id ),
            esc_html( $label ),
            esc_attr( $type ),
            esc_attr( $key ),
            esc_attr( $value ),
            $attr,
            $visible
        );
    }

    /**
     * Helper to render textarea.
     */
    private function render_textarea_field( array $meta, string $key, string $label, array $attributes = [] ): void {
        $id      = 'estate_office_' . $key;
        $value   = isset( $meta[ $key ] ) ? $meta[ $key ] : '';
        $attr    = $this->build_attributes( $attributes );
        $visible = isset( $attributes['data-visible'] ) ? ' data-visibility="' . esc_attr( $attributes['data-visible'] ) . '"' : '';

        printf(
            '<div class="estate-office-field"%6$s><label for="%1$s">%2$s</label><textarea id="%1$s" name="estate_office_meta[%3$s]" rows="3"%5$s>%4$s</textarea></div>',
            esc_attr( $id ),
            esc_html( $label ),
            esc_attr( $key ),
            esc_textarea( $value ),
            $attr,
            $visible
        );
    }

    /**
     * Helper to render select field.
     */
    private function render_select_field( array $meta, string $key, string $label, array $options, array $attributes = [] ): void {
        $id      = 'estate_office_' . $key;
        $value   = isset( $meta[ $key ] ) ? $meta[ $key ] : '';
        $attr    = $this->build_attributes( $attributes );
        $visible = isset( $attributes['data-visible'] ) ? ' data-visibility="' . esc_attr( $attributes['data-visible'] ) . '"' : '';

        echo '<div class="estate-office-field"' . $visible . '>';
        printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $label ) );
        printf( '<select id="%1$s" name="estate_office_meta[%2$s]"%3$s>', esc_attr( $id ), esc_attr( $key ), $attr );
        foreach ( $options as $option_value => $option_label ) {
            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option_value ), esc_html( $option_label ), selected( $value, $option_value, false ) );
        }
        echo '</select></div>';
    }

    /**
     * Renders checkbox groups.
     */
    private function render_checkbox_group( string $key, array $options, array $selected, string $label = '' ): void {
        echo '<div class="estate-office-field estate-office-checkboxes" data-group="' . esc_attr( $key ) . '">';
        if ( $label ) {
            echo '<span class="estate-office-label">' . esc_html( $label ) . '</span>';
        }
        foreach ( $options as $option_value => $option_label ) {
            printf(
                '<label><input type="checkbox" name="estate_office_meta[%1$s][]" value="%2$s" %4$s/> %3$s</label>',
                esc_attr( $key ),
                esc_attr( $option_value ),
                esc_html( $option_label ),
                checked( in_array( $option_value, $selected, true ), true, false )
            );
        }
        echo '</div>';
    }

    /**
     * Renders toggle field with optional sub inputs.
     */
    private function render_toggle_field( array $meta, string $key, string $label, array $subfields ): void {
        $enabled     = isset( $meta[ $key ]['enabled'] ) ? (bool) $meta[ $key ]['enabled'] : false;
        $field_id    = 'estate_office_toggle_' . $key;

        echo '<div class="estate-office-field estate-office-toggle-field">';
        printf( '<label><input type="checkbox" id="%1$s" name="estate_office_meta[%2$s][enabled]" value="1" %4$s /> %3$s</label>', esc_attr( $field_id ), esc_attr( $key ), esc_html( $label ), checked( $enabled, true, false ) );
        echo '<div class="estate-office-toggle-target" data-toggle-source="' . esc_attr( $field_id ) . '"' . ( $enabled ? '' : ' style="display:none;"' ) . '>';
        foreach ( $subfields as $sub_key => $sub_label ) {
            $value = isset( $meta[ $key ][ $sub_key ] ) ? $meta[ $key ][ $sub_key ] : '';
            printf(
                '<input type="text" name="estate_office_meta[%1$s][%2$s]" value="%3$s" placeholder="%4$s" />',
                esc_attr( $key ),
                esc_attr( $sub_key ),
                esc_attr( $value ),
                esc_attr( $sub_label )
            );
        }
        echo '</div></div>';
    }

    /**
     * Renders agent selection dropdown.
     */
    private function render_agent_field( array $meta, string $key, string $label ): void {
        $selected = isset( $meta[ $key ] ) ? (int) $meta[ $key ] : 0;
        $users    = get_users(
            [
                'role__in' => [ 'estate_agent', 'administrator' ],
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            ]
        );

        echo '<div class="estate-office-field">';
        printf( '<label for="estate_office_%1$s">%2$s</label>', esc_attr( $key ), esc_html( $label ) );
        printf( '<select id="estate_office_%1$s" name="estate_office_meta[%1$s]">', esc_attr( $key ) );
        echo '<option value="0">' . esc_html__( 'Brak', 'estate-office' ) . '</option>';
        foreach ( $users as $user ) {
            printf( '<option value="%1$d" %3$s>%2$s</option>', (int) $user->ID, esc_html( $user->display_name ), selected( $selected, (int) $user->ID, false ) );
        }
        echo '</select></div>';
    }

    /**
     * Renders post select dropdown.
     */
    private function render_post_select( string $post_type, string $name, int $selected ): void {
        $posts = get_posts(
            [
                'post_type'      => $post_type,
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        printf( '<select id="%1$s" name="%2$s">', esc_attr( sanitize_title( $name ) ), esc_attr( $name ) );
        echo '<option value="0">' . esc_html__( 'Brak', 'estate-office' ) . '</option>';
        foreach ( $posts as $post ) {
            printf( '<option value="%1$d" %3$s>%2$s</option>', $post->ID, esc_html( get_the_title( $post ) ), selected( $selected, $post->ID, false ) );
        }
        echo '</select>';
    }

    /**
     * Returns contract stages.
     */
    private function get_contract_stages(): array {
        return [
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
    }

    /**
     * Renders address fields grid.
     */
    private function render_address_group( array $meta, string $prefix ): void {
        $fields = [
            'street'      => __( 'Ulica', 'estate-office' ),
            'number'      => __( 'Numer', 'estate-office' ),
            'unit'        => __( 'Lokal', 'estate-office' ),
            'postal_code' => __( 'Kod pocztowy', 'estate-office' ),
            'city'        => __( 'Miasto', 'estate-office' ),
            'country'     => __( 'Kraj', 'estate-office' ),
        ];

        echo '<div class="estate-office-field-grid">';
        foreach ( $fields as $key => $label ) {
            $value = isset( $meta[ $prefix ][ $key ] ) ? $meta[ $prefix ][ $key ] : '';
            printf(
                '<div class="estate-office-field"><label>%2$s</label><input type="text" name="estate_office_meta[%1$s][%3$s]" value="%4$s" /></div>',
                esc_attr( $prefix ),
                esc_html( $label ),
                esc_attr( $key ),
                esc_attr( $value )
            );
        }
        echo '</div>';
    }

    /**
     * Builds attribute string from array.
     */
    private function build_attributes( array $attributes ): string {
        $rendered = [];
        foreach ( $attributes as $key => $value ) {
            if ( 'data-visible' === $key ) {
                $rendered[] = 'data-visible="' . esc_attr( $value ) . '"';
            } elseif ( 'data-lot-shape' === $key ) {
                $rendered[] = 'data-lot-shape="' . esc_attr( $value ) . '"';
            } elseif ( 'data-disabled-toggle' === $key ) {
                $rendered[] = 'data-disabled-toggle="' . esc_attr( $value ) . '"';
            } else {
                $rendered[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
            }
        }

        return $rendered ? ' ' . implode( ' ', $rendered ) : '';
    }
}
