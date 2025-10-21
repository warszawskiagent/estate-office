<?php
/**
 * Single offer template for exported properties.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$offer = get_query_var( 'estate_office_offer_data' );
if ( ! is_array( $offer ) ) {
    get_footer();
    return;
}

$meta                 = $offer['meta'] ?? [];
$transaction_labels   = [
    'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
    'wynajem'  => __( 'Wynajem', 'estate-office' ),
    'kupno'    => __( 'Kupno', 'estate-office' ),
    'najem'    => __( 'Najem', 'estate-office' ),
];
$property_type_labels = [
    'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
    'dom'        => __( 'Dom', 'estate-office' ),
    'dzialka'    => __( 'Działka', 'estate-office' ),
    'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
];
$legal_status_labels  = [
    'wlasnosc'      => __( 'Własność', 'estate-office' ),
    'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
    'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
    'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
    'inne'          => __( 'Inne', 'estate-office' ),
];
$house_types          = [
    'wolnostojacy'  => __( 'Dom wolnostojący', 'estate-office' ),
    'blizniak'      => __( 'Bliźniak', 'estate-office' ),
    'szeregowiec'   => __( 'Szeregowiec', 'estate-office' ),
    'wielorodzinny' => __( 'Dom wielorodzinny', 'estate-office' ),
];
$lot_shapes           = [
    'regularny'    => __( 'Regularny', 'estate-office' ),
    'nieregularny' => __( 'Nieregularny', 'estate-office' ),
];
$kitchen_types        = [
    'aneks'     => __( 'Kuchnia w aneksie', 'estate-office' ),
    'oddzielna' => __( 'Kuchnia oddzielna', 'estate-office' ),
    'z_salonem' => __( 'Kuchnia z salonem', 'estate-office' ),
];
$flag_labels          = [
    'new_offer'    => __( 'Nowa oferta', 'estate-office' ),
    'exclusive'    => __( 'Wyłączność', 'estate-office' ),
    'sold'         => __( 'Sprzedane', 'estate-office' ),
    'rented'       => __( 'Wynajęte', 'estate-office' ),
    'new_price'    => __( 'Nowa cena', 'estate-office' ),
    'no_commision' => __( 'Bez prowizji', 'estate-office' ),
    'mls'          => __( 'Oferta MLS', 'estate-office' ),
    'premium'      => __( 'Premium', 'estate-office' ),
];

$transaction_label  = $transaction_labels[ $offer['transaction'] ] ?? '';
$property_type_name = $property_type_labels[ $offer['property_type'] ] ?? '';
$address_parts      = array_filter(
    [
        $meta['street'] ?? '',
        $meta['building_number'] ?? '',
        $meta['unit_number'] ?? '',
        $meta['city'] ?? '',
    ],
    static function ( $part ) {
        return '' !== trim( (string) $part );
    }
);
$address_line = implode( ', ', $address_parts );
$postal_line  = trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) );
$price        = ! empty( $meta['price'] ) ? number_format_i18n( (float) $meta['price'], 0 ) . ' ' . __( 'PLN', 'estate-office' ) : '';
$rent         = ! empty( $meta['rent'] ) ? number_format_i18n( (float) $meta['rent'], 0 ) . ' ' . __( 'PLN', 'estate-office' ) : '';
$area         = ! empty( $meta['area'] ) ? number_format_i18n( (float) $meta['area'], 2 ) . ' m²' : '';
$price_sqm    = ! empty( $meta['price_sqm'] ) ? number_format_i18n( (float) $meta['price_sqm'], 2 ) . ' ' . __( 'PLN/m²', 'estate-office' ) : '';
$rooms        = ! empty( $meta['rooms'] ) ? number_format_i18n( (int) $meta['rooms'] ) : '';
$bedrooms     = ! empty( $meta['bedrooms'] ) ? number_format_i18n( (int) $meta['bedrooms'] ) : '';
$bathrooms    = ! empty( $meta['bathrooms'] ) ? number_format_i18n( (int) $meta['bathrooms'] ) : '';
$toilets      = ! empty( $meta['toilets'] ) ? number_format_i18n( (int) $meta['toilets'] ) : '';

$stage_features = [
    __( 'Stan prawny', 'estate-office' )        => $legal_status_labels[ $meta['legal_status'] ?? '' ] ?? '',
    __( 'Typ domu', 'estate-office' )          => $house_types[ $meta['house_type'] ?? '' ] ?? '',
    __( 'Rok budowy', 'estate-office' )        => ! empty( $meta['year_built'] ) ? number_format_i18n( (int) $meta['year_built'] ) : '',
    __( 'Piętro', 'estate-office' )            => ! empty( $meta['floor'] ) ? number_format_i18n( (int) $meta['floor'] ) : '',
    __( 'Liczba pięter', 'estate-office' )     => ! empty( $meta['floors'] ) ? number_format_i18n( (int) $meta['floors'] ) : '',
    __( 'Liczba pokoi', 'estate-office' )      => $rooms,
    __( 'Liczba sypialni', 'estate-office' )   => $bedrooms,
    __( 'Liczba łazienek', 'estate-office' )   => $bathrooms,
    __( 'Liczba toalet', 'estate-office' )     => $toilets,
    __( 'Kuchnia', 'estate-office' )           => $kitchen_types[ $meta['kitchen_type'] ?? '' ] ?? '',
    __( 'Numer księgi wieczystej', 'estate-office' ) => empty( $meta['no_lmr'] ) ? ( $meta['land_and_mortgage_register'] ?? '' ) : __( 'Brak', 'estate-office' ),
    __( 'Kształt działki', 'estate-office' )   => $lot_shapes[ $meta['lot_shape'] ?? '' ] ?? '',
];

$exposure_labels = [
    'polnoc'   => __( 'Północ', 'estate-office' ),
    'poludnie' => __( 'Południe', 'estate-office' ),
    'wschod'   => __( 'Wschód', 'estate-office' ),
    'zachod'   => __( 'Zachód', 'estate-office' ),
];
$view_labels = [
    'miasto'   => __( 'Widok na miasto', 'estate-office' ),
    'panorama' => __( 'Panorama', 'estate-office' ),
    'park'     => __( 'Park', 'estate-office' ),
    'inne'     => __( 'Inne', 'estate-office' ),
];
$layout_labels = [
    'dwustronne'      => __( 'Dwustronne', 'estate-office' ),
    'jednostronne'    => __( 'Jednostronne', 'estate-office' ),
    'przechodnie'     => __( 'Układ przechodni', 'estate-office' ),
    'otwarta_kuchnia' => __( 'Otwarta kuchnia', 'estate-office' ),
];
$parking_labels = [
    'naziemne' => __( 'Miejsce naziemne', 'estate-office' ),
    'podziemne'=> __( 'Parking podziemny', 'estate-office' ),
    'garaz'    => __( 'Garaż', 'estate-office' ),
];
$facility_labels = [
    'winda'                 => __( 'Winda', 'estate-office' ),
    'umeblowanie_pelne'     => __( 'Umeblowanie pełne', 'estate-office' ),
    'umeblowanie_czesciowe' => __( 'Umeblowanie częściowe', 'estate-office' ),
    'klimatyzacja'          => __( 'Klimatyzacja', 'estate-office' ),
    'monitoring'            => __( 'Monitoring/Ochrona', 'estate-office' ),
    'recepcja'              => __( 'Recepcja', 'estate-office' ),
    'teren_zamkniety'       => __( 'Teren zamknięty', 'estate-office' ),
    'domofon'               => __( 'Domofon', 'estate-office' ),
];
$equipment_labels = [
    'pralka'    => __( 'Pralka', 'estate-office' ),
    'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
    'lodowka'   => __( 'Lodówka', 'estate-office' ),
    'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
    'piekarnik' => __( 'Piekarnik', 'estate-office' ),
    'telewizor' => __( 'Telewizor', 'estate-office' ),
    'mikrofala' => __( 'Mikrofalówka', 'estate-office' ),
];

$summary_cards = array_filter(
    [
        __( 'Cena', 'estate-office' )                   => $price,
        __( 'Cena za m²', 'estate-office' )             => $price_sqm,
        __( 'Metraż', 'estate-office' )                 => $area,
        __( 'Czynsz administracyjny', 'estate-office' ) => $rent,
        __( 'Liczba pokoi', 'estate-office' )           => $rooms ? sprintf( _n( '%s pokój', '%s pokoje', (int) $meta['rooms'], 'estate-office' ), $rooms ) : '',
    ],
    static function ( $value ) {
        return '' !== $value && null !== $value;
    }
);

$exposure_list  = isset( $meta['exposure'] ) && is_array( $meta['exposure'] ) ? array_intersect_key( $exposure_labels, array_flip( $meta['exposure'] ) ) : [];
$view_list      = isset( $meta['view'] ) && is_array( $meta['view'] ) ? array_intersect_key( $view_labels, array_flip( $meta['view'] ) ) : [];
$layout_list    = isset( $meta['layout'] ) && is_array( $meta['layout'] ) ? array_intersect_key( $layout_labels, array_flip( $meta['layout'] ) ) : [];
$parking_list   = isset( $meta['parking_options'] ) && is_array( $meta['parking_options'] ) ? array_intersect_key( $parking_labels, array_flip( $meta['parking_options'] ) ) : [];
$facility_list  = isset( $meta['facilities'] ) && is_array( $meta['facilities'] ) ? array_intersect_key( $facility_labels, array_flip( $meta['facilities'] ) ) : [];
$equipment_list = isset( $meta['equipment'] ) && is_array( $meta['equipment'] ) ? array_intersect_key( $equipment_labels, array_flip( $meta['equipment'] ) ) : [];

$additional_surfaces = [];
foreach (
    [
        'balcony' => __( 'Balkon', 'estate-office' ),
        'terrace' => __( 'Taras', 'estate-office' ),
        'basement' => __( 'Piwnica', 'estate-office' ),
        'storage' => __( 'Komórka lokatorska', 'estate-office' ),
        'garden'  => __( 'Ogródek', 'estate-office' ),
    ] as $key => $label
) {
    if ( empty( $meta[ $key ]['enabled'] ) ) {
        continue;
    }

    $value_parts = [];
    if ( isset( $meta[ $key ]['count'] ) && '' !== $meta[ $key ]['count'] ) {
        $value_parts[] = sprintf( __( 'Ilość: %s', 'estate-office' ), esc_html( $meta[ $key ]['count'] ) );
    }
    if ( isset( $meta[ $key ]['area'] ) && '' !== $meta[ $key ]['area'] ) {
        $value_parts[] = sprintf( __( 'Powierzchnia: %s m²', 'estate-office' ), esc_html( $meta[ $key ]['area'] ) );
    }

    $additional_surfaces[ $label ] = implode( ' • ', $value_parts );
}

$flags = [];
if ( ! empty( $offer['flags'] ) && is_array( $offer['flags'] ) ) {
    foreach ( $offer['flags'] as $flag ) {
        if ( 'export_www' === $flag ) {
            continue;
        }
        if ( isset( $flag_labels[ $flag ] ) ) {
            $flags[] = $flag_labels[ $flag ];
        }
    }
}

$agent = null;
if ( ! empty( $meta['agent_id'] ) ) {
    $agent = get_user_by( 'id', (int) $meta['agent_id'] );
}
$agent_phone = $agent ? get_user_meta( $agent->ID, '_estate_office_phone', true ) : '';
$agent_email = $agent ? $agent->user_email : get_bloginfo( 'admin_email' );
$agent_name  = $agent ? $agent->display_name : get_bloginfo( 'name' );

$map_link = '';
if ( ! empty( $meta['map_lat'] ) && ! empty( $meta['map_lng'] ) ) {
    $map_link = sprintf(
        'https://www.google.com/maps/search/?api=1&query=%1$s,%2$s',
        rawurlencode( $meta['map_lat'] ),
        rawurlencode( $meta['map_lng'] )
    );
}
?>

<main class="estate-office-offer-single">
    <header>
        <?php if ( $transaction_label || $property_type_name ) : ?>
            <div class="offer-eyebrow">
                <?php echo esc_html( trim( $transaction_label . ' · ' . $property_type_name ) ); ?>
            </div>
        <?php endif; ?>
        <h1><?php echo esc_html( $offer['title'] ); ?></h1>
        <?php if ( $offer['offer_number'] ) : ?>
            <p><strong><?php esc_html_e( 'Numer oferty', 'estate-office' ); ?>:</strong> <?php echo esc_html( $offer['offer_number'] ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $flags ) ) : ?>
            <div class="offer-flags">
                <?php foreach ( $flags as $flag_label ) : ?>
                    <span class="offer-flag"><?php echo esc_html( $flag_label ); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if ( ! empty( $summary_cards ) ) : ?>
        <section class="offer-summary">
            <?php foreach ( $summary_cards as $label => $value ) : ?>
                <div class="summary-card">
                    <span class="label"><?php echo esc_html( $label ); ?></span>
                    <span class="value"><?php echo wp_kses_post( $value ); ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="offer-section">
        <h2><?php esc_html_e( 'Adres nieruchomości', 'estate-office' ); ?></h2>
        <div class="offer-details">
            <?php if ( $address_line ) : ?>
                <div class="offer-detail">
                    <span class="label"><?php esc_html_e( 'Adres', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( $address_line ); ?></span>
                </div>
            <?php endif; ?>
            <?php if ( $postal_line ) : ?>
                <div class="offer-detail">
                    <span class="label"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( $postal_line ); ?></span>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $meta['district'] ) ) : ?>
                <div class="offer-detail">
                    <span class="label"><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( $meta['district'] ); ?></span>
                </div>
            <?php endif; ?>
            <?php if ( $map_link ) : ?>
                <div class="offer-detail">
                    <span class="label"><?php esc_html_e( 'Mapa', 'estate-office' ); ?></span>
                    <span class="value"><a href="<?php echo esc_url( $map_link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Zobacz na mapie Google', 'estate-office' ); ?></a></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php
    $filtered_stage_features = array_filter( $stage_features, static function ( $value ) {
        return '' !== $value && null !== $value;
    } );
    if ( ! empty( $filtered_stage_features ) ) :
        ?>
        <section class="offer-section">
            <h2><?php esc_html_e( 'Kluczowe parametry', 'estate-office' ); ?></h2>
            <div class="offer-details">
                <?php foreach ( $filtered_stage_features as $label => $value ) : ?>
                    <div class="offer-detail">
                        <span class="label"><?php echo esc_html( $label ); ?></span>
                        <span class="value"><?php echo esc_html( $value ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $offer['content'] ) ) : ?>
        <section class="offer-section">
            <h2><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></h2>
            <div class="offer-description">
                <?php echo wp_kses_post( $offer['content'] ); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $exposure_list ) || ! empty( $view_list ) || ! empty( $layout_list ) || ! empty( $parking_list ) || ! empty( $facility_list ) || ! empty( $equipment_list ) || ! empty( $additional_surfaces ) ) : ?>
        <section class="offer-section">
            <h2><?php esc_html_e( 'Udogodnienia i wyposażenie', 'estate-office' ); ?></h2>
            <div class="offer-list">
                <?php if ( ! empty( $exposure_list ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Ekspozycja', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $exposure_list as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $view_list ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Widok', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $view_list as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $layout_list ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Rozkład', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $layout_list as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $parking_list ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Miejsca parkingowe', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $parking_list as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $facility_list ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Udogodnienia', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $facility_list as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $equipment_list ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Wyposażenie', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $equipment_list as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $additional_surfaces ) ) : ?>
                    <div>
                        <h3><?php esc_html_e( 'Powierzchnie dodatkowe', 'estate-office' ); ?></h3>
                        <ul>
                            <?php foreach ( $additional_surfaces as $label => $value ) : ?>
                                <li>
                                    <strong><?php echo esc_html( $label ); ?>:</strong>
                                    <?php echo esc_html( $value ?: __( 'Dostępne', 'estate-office' ) ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $gallery_ids = $offer['gallery'];
    if ( ! empty( $gallery_ids ) ) :
        ?>
        <section class="offer-section">
            <h2><?php esc_html_e( 'Galeria zdjęć', 'estate-office' ); ?></h2>
            <div class="offer-gallery">
                <?php foreach ( $gallery_ids as $attachment_id ) : ?>
                    <figure>
                        <?php echo wp_get_attachment_image( $attachment_id, 'large' ); ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $meta['video_url'] ) || ! empty( $meta['virtual_tour_url'] ) || ! empty( $meta['plan2d'] ) || ! empty( $meta['plan3d'] ) ) : ?>
        <section class="offer-section">
            <h2><?php esc_html_e( 'Materiały dodatkowe', 'estate-office' ); ?></h2>
            <div class="offer-details">
                <?php if ( ! empty( $meta['video_url'] ) ) : ?>
                    <div class="offer-detail">
                        <span class="label"><?php esc_html_e( 'Film', 'estate-office' ); ?></span>
                        <span class="value"><a href="<?php echo esc_url( $meta['video_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Zobacz wideo', 'estate-office' ); ?></a></span>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $meta['virtual_tour_url'] ) ) : ?>
                    <div class="offer-detail">
                        <span class="label"><?php esc_html_e( 'Wirtualny spacer', 'estate-office' ); ?></span>
                        <span class="value"><a href="<?php echo esc_url( $meta['virtual_tour_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Przejdź do spaceru', 'estate-office' ); ?></a></span>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $meta['plan2d'] ) ) : ?>
                    <div class="offer-detail">
                        <span class="label"><?php esc_html_e( 'Rzut 2D', 'estate-office' ); ?></span>
                        <span class="value"><?php echo wp_get_attachment_image( (int) $meta['plan2d'], 'large' ); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $meta['plan3d'] ) ) : ?>
                    <div class="offer-detail">
                        <span class="label"><?php esc_html_e( 'Rzut 3D', 'estate-office' ); ?></span>
                        <span class="value"><?php echo wp_get_attachment_image( (int) $meta['plan3d'], 'large' ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="offer-section">
        <h2><?php esc_html_e( 'Skontaktuj się z opiekunem', 'estate-office' ); ?></h2>
        <div class="offer-contact">
            <strong><?php echo esc_html( $agent_name ); ?></strong>
            <?php if ( $agent_phone ) : ?>
                <span><?php printf( '%s: %s', esc_html__( 'Telefon', 'estate-office' ), esc_html( $agent_phone ) ); ?></span>
            <?php endif; ?>
            <?php if ( $agent_email ) : ?>
                <span><?php printf( '%s: %s', esc_html__( 'E-mail', 'estate-office' ), esc_html( $agent_email ) ); ?></span>
            <?php endif; ?>
            <?php if ( ! empty( $meta['map_notes'] ) ) : ?>
                <span><?php echo esc_html( $meta['map_notes'] ); ?></span>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
get_footer();
