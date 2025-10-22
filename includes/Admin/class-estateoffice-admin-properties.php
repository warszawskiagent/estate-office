<?php
/**
 * Properties management page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Properties extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-properties';

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Nieruchomości', 'estate-office' );
        $this->page_title = __( 'Nieruchomości', 'estate-office' );
        $this->capability = 'eo_manage_properties';
    }

    public function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $property_id = isset( $_GET['property'] ) ? absint( $_GET['property'] ) : 0;

        if ( 'new' === $action || ( 'edit' === $action && $property_id ) ) {
            $property = $property_id ? self::get_property( $property_id ) : null;
            $contracts = EstateOffice_Admin_Contracts::get_contracts();
            $agents    = EstateOffice_Admin_Agents::get_agents();
            $dynamic_fields = EstateOffice_Admin_Settings::get_dynamic_fields( 'property' );
            $this->render_form( $property, $contracts, $dynamic_fields, $agents );
            return;
        }

        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $properties = self::get_properties( $search );
        $this->render_list( $properties, $search );
    }

    protected function render_list( array $properties, string $search ): void {
        ?>
        <div class="wrap estate-office-wrap estate-office-properties">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <?php $this->render_notice(); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Dodaj nieruchomość', 'estate-office' ); ?></a>

            <form method="get" class="estate-office-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <label for="estate-office-property-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj nieruchomości', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-property-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Szukaj po adresie lub numerze umowy', 'estate-office' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Numer oferty', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Cena', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Metraż', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $properties ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'Brak nieruchomości.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $properties as $property ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&property=' . absint( $property->id ) ) ); ?>"><?php echo esc_html( sprintf( '#%05d', $property->id ) ); ?></a></td>
                                <td><?php echo esc_html( $property->address_display ); ?></td>
                                <td><?php echo esc_html( self::format_currency( $property->price ) ); ?></td>
                                <td><?php echo esc_html( self::format_currency( $property->price_m2 ) ); ?></td>
                                <td><?php echo esc_html( $property->area ? $property->area . ' m²' : '' ); ?></td>
                                <td><?php echo esc_html( $property->rooms ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $property ) ?: '—' ); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&property=' . absint( $property->id ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office' ); ?></a>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=edit&contract=' . absint( $property->contract_id ) ) ); ?>"><?php esc_html_e( 'Umowa', 'estate-office' ); ?></a>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć tę nieruchomość?', 'estate-office' ) ); ?>');">
                                            <?php wp_nonce_field( 'estate_office_delete_property' ); ?>
                                            <input type="hidden" name="action" value="estate_office_delete_property" />
                                            <input type="hidden" name="property_id" value="<?php echo esc_attr( $property->id ); ?>" />
                                            <button type="submit" class="button-link delete-link"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected function render_form( $property, array $contracts, array $dynamic_fields, array $agents ): void {
        $details = $property && $property->details ? json_decode( $property->details, true ) : [];
        $address = $property && $property->address ? json_decode( $property->address, true ) : [];
        $legal   = $property && $property->legal ? json_decode( $property->legal, true ) : [];
        $tags    = $property && $property->tags ? json_decode( $property->tags, true ) : [];
        $contract_index = [];
        foreach ( $contracts as $contract_row ) {
            $contract_index[ $contract_row->id ] = [
                'transaction' => $contract_row->transaction_type,
                'agent'       => (int) ( $contract_row->agent_id ?? 0 ),
            ];
        }
        $selected_contract = $property ? (int) $property->contract_id : 0;
        $transaction_type  = $property->transaction_type ?? '';
        $property_agent    = (int) ( $property->agent_id ?? 0 );
        if ( $selected_contract && isset( $contract_index[ $selected_contract ] ) ) {
            if ( empty( $transaction_type ) ) {
                $transaction_type = $contract_index[ $selected_contract ]['transaction'];
            }
            if ( ! $property_agent && ! empty( $contract_index[ $selected_contract ]['agent'] ) ) {
                $property_agent = $contract_index[ $selected_contract ]['agent'];
            }
        }
        if ( ! is_array( $details ) ) {
            $details = [];
        }
        if ( ! is_array( $address ) ) {
            $address = [];
        }
        if ( ! is_array( $legal ) ) {
            $legal = [];
        }
        if ( ! is_array( $tags ) ) {
            $tags = [];
        }

        $maps_key   = get_option( 'estate_office_google_maps_api_key', '' );
        $map_lat    = isset( $address['lat'] ) ? (string) $address['lat'] : '';
        $map_lng    = isset( $address['lng'] ) ? (string) $address['lng'] : '';
        $map_output = ( $map_lat && $map_lng ) ? $map_lat . ', ' . $map_lng : '';

        $media_items = $property ? self::get_property_media( $property->id ) : [];
        $gallery_ids = [];
        $floor_2d    = 0;
        $floor_3d    = 0;
        $video_url   = '';
        $virtual_url = '';
        foreach ( $media_items as $item ) {
            switch ( $item->media_type ) {
                case 'gallery':
                    if ( $item->attachment_id ) {
                        $gallery_ids[] = (int) $item->attachment_id;
                    }
                    break;
                case 'floor_2d':
                    $floor_2d = (int) ( $item->attachment_id ?? 0 );
                    break;
                case 'floor_3d':
                    $floor_3d = (int) ( $item->attachment_id ?? 0 );
                    break;
                case 'video':
                    $video_url = $item->media_url ?? '';
                    break;
                case 'virtual':
                    $virtual_url = $item->media_url ?? '';
                    break;
            }
        }

        $building   = isset( $details['building'] ) && is_array( $details['building'] ) ? $details['building'] : [];
        $plot       = isset( $details['plot'] ) && is_array( $details['plot'] ) ? $details['plot'] : [];
        $utilities  = isset( $details['utilities'] ) && is_array( $details['utilities'] ) ? $details['utilities'] : [];
        $amenities  = isset( $details['amenities'] ) && is_array( $details['amenities'] ) ? $details['amenities'] : [];
        $equipment  = isset( $details['equipment'] ) && is_array( $details['equipment'] ) ? $details['equipment'] : [];
        $surfaces   = isset( $details['surfaces'] ) && is_array( $details['surfaces'] ) ? $details['surfaces'] : [];
        $exposure   = isset( $building['exposure'] ) && is_array( $building['exposure'] ) ? $building['exposure'] : [];
        $views      = isset( $building['view'] ) && is_array( $building['view'] ) ? $building['view'] : [];
        $layout     = isset( $building['layout'] ) && is_array( $building['layout'] ) ? $building['layout'] : [];
        $parking    = isset( $building['parking'] ) && is_array( $building['parking'] ) ? $building['parking'] : [];

        $property_types = [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ];
        $house_types    = [ 'WOLNOSTOJĄCY', 'BLIŹNIAK', 'SZEREGOWIEC', 'WIELORODZINNY' ];
        $finish_states  = [ 'DO WYKOŃCZENIA', 'DO REMONTU', 'PO REMONCIE', 'WYSOKI STANDARD' ];
        $kitchen_types  = [ 'ANEKS', 'ODDZIELNA', 'Z SALONEM' ];
        $heating_types  = [ 'MIEJSKIE', 'GAZOWE', 'ELEKTRYCZNE', 'POMPA CIEPŁA', 'OLEJOWE' ];
        $water_types    = [ 'MIEJSKA', 'STUDNIA', 'UJĘCIE WŁASNE' ];
        $sewage_types   = [ 'MIEJSKA', 'SZAMBO', 'PRZYDOMOWA OCZYSZCZALNIA' ];
        $amenity_keys   = [
            'winda'        => __( 'Winda', 'estate-office' ),
            'umeblowanie'  => __( 'Umeblowanie', 'estate-office' ),
            'klimatyzacja' => __( 'Klimatyzacja', 'estate-office' ),
            'monitoring'   => __( 'Monitoring / Ochrona', 'estate-office' ),
            'recepcja'     => __( 'Recepcja', 'estate-office' ),
            'teren'        => __( 'Teren zamknięty', 'estate-office' ),
            'domofon'      => __( 'Domofon', 'estate-office' ),
        ];
        $equipment_keys = [
            'pralka'    => __( 'Pralka', 'estate-office' ),
            'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
            'lodowka'   => __( 'Lodówka', 'estate-office' ),
            'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
            'piekarnik' => __( 'Piekarnik', 'estate-office' ),
            'telewizor' => __( 'Telewizor', 'estate-office' ),
            'mikrofala' => __( 'Mikrofala', 'estate-office' ),
        ];
        $surface_keys = [
            'balcony'   => __( 'Balkon', 'estate-office' ),
            'terrace'   => __( 'Taras', 'estate-office' ),
            'basement'  => __( 'Piwnica', 'estate-office' ),
            'storage'   => __( 'Komórka lokatorska', 'estate-office' ),
            'garden'    => __( 'Ogródek', 'estate-office' ),
        ];
        ?>
        <div class="wrap estate-office-wrap estate-office-property-edit">
            <h1><?php echo esc_html( $property ? __( 'Edytuj nieruchomość', 'estate-office' ) : __( 'Dodaj nieruchomość', 'estate-office' ) ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-property-form">
                <?php wp_nonce_field( 'estate_office_save_property' ); ?>
                <input type="hidden" name="action" value="estate_office_save_property" />
                <?php if ( $property ) : ?>
                    <input type="hidden" name="property_id" value="<?php echo esc_attr( $property->id ); ?>" />
                <?php endif; ?>

                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="property_contract" class="required"><?php esc_html_e( 'Powiązana umowa', 'estate-office' ); ?></label>
                        <select id="property_contract" name="contract_id" required>
                            <option value="">&mdash;</option>
                            <?php foreach ( $contracts as $contract ) : ?>
                                <option value="<?php echo esc_attr( $contract->id ); ?>" data-transaction="<?php echo esc_attr( $contract->transaction_type ); ?>" data-agent="<?php echo esc_attr( (int) ( $contract->agent_id ?? 0 ) ); ?>" <?php selected( $selected_contract, (int) $contract->id ); ?>><?php echo esc_html( $contract->contract_number ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="property_agent"><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></label>
                        <select id="property_agent" name="agent_id" data-fallback="<?php echo esc_attr( $property_agent ); ?>">
                            <option value=""><?php esc_html_e( 'Wybierz opiekuna', 'estate-office' ); ?></option>
                            <?php foreach ( $agents as $agent_row ) :
                                $label = EstateOffice_Admin_Agents::format_agent_name( $agent_row );
                                if ( '' === $label ) {
                                    $label = sprintf( __( 'Agent #%d', 'estate-office' ), (int) $agent_row->id );
                                }
                                ?>
                                <option value="<?php echo esc_attr( $agent_row->id ); ?>" <?php selected( $property_agent, (int) $agent_row->id ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="property_transaction_display"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                        <input type="text" id="property_transaction_display" class="regular-text" value="<?php echo esc_attr( $transaction_type ); ?>" readonly />
                        <input type="hidden" id="property_transaction" name="transaction_type" value="<?php echo esc_attr( $transaction_type ); ?>" data-fallback="<?php echo esc_attr( $transaction_type ); ?>" />
                        <span class="description"><?php esc_html_e( 'Typ transakcji wynika z wybranej umowy i nie może być edytowany ręcznie.', 'estate-office' ); ?></span>
                    </p>
                    <p>
                        <label for="property_type_basic" class="required"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                        <select id="property_type_basic" name="property_type" required>
                            <?php foreach ( $property_types as $type ) : ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $property->property_type ?? 'MIESZKANIE', $type ); ?>><?php echo esc_html( $type ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Dane nieruchomości', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label for="property_price_basic" class="required"><?php esc_html_e( 'Cena', 'estate-office' ); ?></label>
                            <input type="number" step="0.01" id="property_price_basic" name="details[price]" value="<?php echo esc_attr( $details['price'] ?? '' ); ?>" />
                            <span class="description" data-transaction-types="WYNAJEM"><?php esc_html_e( 'Dla wynajmu wpisz kwotę miesięczną.', 'estate-office' ); ?></span>
                        </p>
                        <p>
                            <label for="property_admin_fee"><?php esc_html_e( 'Czynsz administracyjny', 'estate-office' ); ?></label>
                            <input type="number" step="0.01" id="property_admin_fee" name="details[admin_fee]" value="<?php echo esc_attr( $details['admin_fee'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_area_basic" class="required"><?php esc_html_e( 'Metraż (m²)', 'estate-office' ); ?></label>
                            <input type="number" step="0.01" id="property_area_basic" name="details[area]" value="<?php echo esc_attr( $details['area'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_price_basic_m2"><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></label>
                            <input type="number" step="0.01" id="property_price_basic_m2" name="details[price_m2]" value="<?php echo esc_attr( $details['price_m2'] ?? '' ); ?>" readonly />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U">
                            <label for="property_floor"><?php esc_html_e( 'Piętro', 'estate-office' ); ?></label>
                            <input type="number" id="property_floor" name="details[floor]" value="<?php echo esc_attr( $details['floor'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U,DOM">
                            <label for="property_floors"><?php esc_html_e( 'Liczba pięter', 'estate-office' ); ?></label>
                            <input type="number" id="property_floors" name="details[floors]" value="<?php echo esc_attr( $details['floors'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U,DOM">
                            <label for="property_rooms"><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></label>
                            <input type="number" id="property_rooms" name="details[rooms]" value="<?php echo esc_attr( $details['rooms'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U,DOM">
                            <label for="property_bedrooms"><?php esc_html_e( 'Liczba sypialni', 'estate-office' ); ?></label>
                            <input type="number" id="property_bedrooms" name="details[bedrooms]" value="<?php echo esc_attr( $details['bedrooms'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U,DOM">
                            <label for="property_bathrooms"><?php esc_html_e( 'Liczba łazienek', 'estate-office' ); ?></label>
                            <input type="number" id="property_bathrooms" name="details[bathrooms]" value="<?php echo esc_attr( $details['bathrooms'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U,DOM">
                            <label for="property_toilets"><?php esc_html_e( 'Liczba toalet', 'estate-office' ); ?></label>
                            <input type="number" id="property_toilets" name="details[toilets]" value="<?php echo esc_attr( $details['toilets'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U,DOM">
                            <label for="property_year"><?php esc_html_e( 'Rok budowy', 'estate-office' ); ?></label>
                            <input type="number" id="property_year" name="details[build_year]" value="<?php echo esc_attr( $details['build_year'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="DZIAŁKA">
                            <label for="property_plot_shape"><?php esc_html_e( 'Kształt działki', 'estate-office' ); ?></label>
                            <select id="property_plot_shape" name="details[plot][shape]">
                                <option value="">&mdash;</option>
                                <option value="REGULARNY" <?php selected( $plot['shape'] ?? '', 'REGULARNY' ); ?>><?php esc_html_e( 'Regularny', 'estate-office' ); ?></option>
                                <option value="NIEREGULARNY" <?php selected( $plot['shape'] ?? '', 'NIEREGULARNY' ); ?>><?php esc_html_e( 'Nieregularny', 'estate-office' ); ?></option>
                            </select>
                        </p>
                        <div data-property-types="DZIAŁKA" data-plot-shape="REGULARNY">
                            <label for="property_plot_regular"><?php esc_html_e( 'Wymiary działki (dł./szer.)', 'estate-office' ); ?></label>
                            <div class="estate-office-grid two-cols">
                                <input type="number" step="0.01" id="property_plot_regular" name="details[plot][length]" value="<?php echo esc_attr( $plot['length'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Długość (m)', 'estate-office' ); ?>" />
                                <input type="number" step="0.01" name="details[plot][width]" value="<?php echo esc_attr( $plot['width'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Szerokość (m)', 'estate-office' ); ?>" />
                            </div>
                        </div>
                        <p data-property-types="DZIAŁKA" data-plot-shape="NIEREGULARNY">
                            <label for="property_plot_irregular"><?php esc_html_e( 'Opis wymiarów', 'estate-office' ); ?></label>
                            <textarea id="property_plot_irregular" name="details[plot][description]" rows="3"><?php echo esc_textarea( $plot['description'] ?? '' ); ?></textarea>
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Adres', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid three-cols" data-property-types="MIESZKANIE,LOKAL H/U">
                        <p>
                            <label for="property_address_street"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_street" name="address[street]" value="<?php echo esc_attr( $address['street'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_number"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_number" name="address[number]" value="<?php echo esc_attr( $address['number'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_flat"><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_flat" name="address[flat]" value="<?php echo esc_attr( $address['flat'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_postal"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_postal" name="address[postal]" value="<?php echo esc_attr( $address['postal'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_district"><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_district" name="address[district]" value="<?php echo esc_attr( $address['district'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_city" name="address[city]" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>" />
                        </p>
                    </div>
                    <div class="estate-office-grid three-cols" data-property-types="DOM">
                        <p>
                            <label for="property_house_type"><?php esc_html_e( 'Typ domu', 'estate-office' ); ?></label>
                            <select id="property_house_type" name="details[house_type]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $house_types as $house ) : ?>
                                    <option value="<?php echo esc_attr( $house ); ?>" <?php selected( $details['house_type'] ?? '', $house ); ?>><?php echo esc_html( $house ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="property_dom_street"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_street" name="address[street]" value="<?php echo esc_attr( $address['street'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_dom_number"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_number" name="address[number]" value="<?php echo esc_attr( $address['number'] ?? '' ); ?>" />
                        </p>
                        <p data-property-types="DOM">
                            <label for="property_dom_flat"><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_flat" name="address[flat]" value="<?php echo esc_attr( $address['flat'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_dom_county"><?php esc_html_e( 'Powiat', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_county" name="address[county]" value="<?php echo esc_attr( $address['county'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_dom_district"><?php esc_html_e( 'Obręb', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_district" name="address[precinct]" value="<?php echo esc_attr( $address['precinct'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_dom_plot"><?php esc_html_e( 'Numer działki', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_plot" name="address[plot_number]" value="<?php echo esc_attr( $address['plot_number'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_dom_postal"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_postal" name="address[postal]" value="<?php echo esc_attr( $address['postal'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_dom_city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                            <input type="text" id="property_dom_city" name="address[city]" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>" />
                        </p>
                    </div>
                    <div class="estate-office-grid three-cols" data-property-types="DZIAŁKA">
                        <p>
                            <label for="property_plot_county"><?php esc_html_e( 'Powiat', 'estate-office' ); ?></label>
                            <input type="text" id="property_plot_county" name="address[county]" value="<?php echo esc_attr( $address['county'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_plot_precinct"><?php esc_html_e( 'Obręb', 'estate-office' ); ?></label>
                            <input type="text" id="property_plot_precinct" name="address[precinct]" value="<?php echo esc_attr( $address['precinct'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_plot_number"><?php esc_html_e( 'Numer działki', 'estate-office' ); ?></label>
                            <input type="text" id="property_plot_number" name="address[plot_number]" value="<?php echo esc_attr( $address['plot_number'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_plot_postal"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                            <input type="text" id="property_plot_postal" name="address[postal]" value="<?php echo esc_attr( $address['postal'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_plot_city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                            <input type="text" id="property_plot_city" name="address[city]" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>" />
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label for="property_legal_kw"><?php esc_html_e( 'Numer KW', 'estate-office' ); ?></label>
                            <input type="text" id="property_legal_kw" name="legal[land_register]" value="<?php echo esc_attr( $legal['land_register'] ?? '' ); ?>" />
                            <label><input type="checkbox" id="property_legal_no_kw" name="legal[no_register]" value="1" <?php checked( ! empty( $legal['no_register'] ) ); ?> /> <?php esc_html_e( 'Brak księgi wieczystej', 'estate-office' ); ?></label>
                        </p>
                        <p>
                            <label for="property_legal_state"><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></label>
                            <select id="property_legal_state" name="legal[ownership]">
                                <?php
                                $ownerships = [
                                    'wlasnosc'      => __( 'Własność', 'estate-office' ),
                                    'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
                                    'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
                                    'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
                                    'inne'          => __( 'Inne', 'estate-office' ),
                                ];
                                foreach ( $ownerships as $value => $label ) {
                                    printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $value ), esc_html( $label ), selected( $legal['ownership'] ?? '', $value, false ) );
                                }
                                ?>
                            </select>
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Lokalizacja na mapie', 'estate-office' ); ?></legend>
                    <?php if ( $maps_key ) : ?>
                        <div class="estate-office-map-controls">
                            <button type="button" class="button" id="property_map_trigger"><?php esc_html_e( 'Zaznacz na mapie', 'estate-office' ); ?></button>
                            <span class="description"><?php esc_html_e( 'Kliknij na mapę, aby ustawić pinezkę.', 'estate-office' ); ?></span>
                            <span id="estate-office-map-output" class="estate-office-map-output"><?php echo esc_html( $map_output ); ?></span>
                        </div>
                        <div id="estate-office-map" data-lat="<?php echo esc_attr( $map_lat ); ?>" data-lng="<?php echo esc_attr( $map_lng ); ?>"></div>
                        <input type="hidden" name="address[lat]" id="property_map_lat" value="<?php echo esc_attr( $map_lat ); ?>" />
                        <input type="hidden" name="address[lng]" id="property_map_lng" value="<?php echo esc_attr( $map_lng ); ?>" />
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Dodaj klucz Google Maps w ustawieniach wtyczki, aby korzystać z mapy.', 'estate-office' ); ?></p>
                    <?php endif; ?>
                </fieldset>

                <fieldset class="estate-office-fieldset" data-property-types="MIESZKANIE,DOM,LOKAL H/U">
                    <legend><?php esc_html_e( 'Szczegóły nieruchomości', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label for="property_finish"><?php esc_html_e( 'Stan wykończenia', 'estate-office' ); ?></label>
                            <select id="property_finish" name="details[building][finish_state]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $finish_states as $state ) : ?>
                                    <option value="<?php echo esc_attr( $state ); ?>" <?php selected( $building['finish_state'] ?? '', $state ); ?>><?php echo esc_html( $state ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <div>
                            <span class="label"><?php esc_html_e( 'Ekspozycja', 'estate-office' ); ?></span>
                            <div class="estate-office-toggle-group">
                                <?php
                                $exposures = [
                                    'north' => __( 'Północ', 'estate-office' ),
                                    'south' => __( 'Południe', 'estate-office' ),
                                    'east'  => __( 'Wschód', 'estate-office' ),
                                    'west'  => __( 'Zachód', 'estate-office' ),
                                ];
                                foreach ( $exposures as $key => $label ) {
                                    printf( '<label><input type="checkbox" name="details[building][exposure][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $exposure[ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                        <div>
                            <span class="label"><?php esc_html_e( 'Widok', 'estate-office' ); ?></span>
                            <div class="estate-office-toggle-group">
                                <?php
                                $view_options = [
                                    'panorama' => __( 'Panorama miasta', 'estate-office' ),
                                    'park'     => __( 'Park / Zieleń', 'estate-office' ),
                                    'street'   => __( 'Ulica', 'estate-office' ),
                                    'water'    => __( 'Woda', 'estate-office' ),
                                ];
                                foreach ( $view_options as $key => $label ) {
                                    printf( '<label><input type="checkbox" name="details[building][view][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $views[ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                        <p>
                            <label><input type="checkbox" name="details[building][attic]" value="1" <?php checked( ! empty( $building['attic'] ) ); ?> /> <?php esc_html_e( 'Poddasze', 'estate-office' ); ?></label>
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U">
                            <label><input type="checkbox" name="details[building][multilevel]" value="1" <?php checked( ! empty( $building['multilevel'] ) ); ?> /> <?php esc_html_e( 'Wielopoziomowe', 'estate-office' ); ?></label>
                        </p>
                        <div>
                            <span class="label"><?php esc_html_e( 'Rozkład', 'estate-office' ); ?></span>
                            <div class="estate-office-toggle-group">
                                <?php
                                $layout_options = [
                                    'separate' => __( 'Oddzielne pokoje', 'estate-office' ),
                                    'open'     => __( 'Otwarte przestrzenie', 'estate-office' ),
                                    'walkthrough' => __( 'Pokoje przechodnie', 'estate-office' ),
                                ];
                                foreach ( $layout_options as $key => $label ) {
                                    printf( '<label><input type="checkbox" name="details[building][layout][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $layout[ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                        <p>
                            <label for="property_kitchen"><?php esc_html_e( 'Kuchnia', 'estate-office' ); ?></label>
                            <select id="property_kitchen" name="details[building][kitchen]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $kitchen_types as $kitchen ) : ?>
                                    <option value="<?php echo esc_attr( $kitchen ); ?>" <?php selected( $building['kitchen'] ?? '', $kitchen ); ?>><?php echo esc_html( ucfirst( strtolower( $kitchen ) ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <div>
                            <span class="label"><?php esc_html_e( 'Miejsce parkingowe', 'estate-office' ); ?></span>
                            <div class="estate-office-toggle-group">
                                <?php
                                $parking_options = [
                                    'rented'    => __( 'Najemne', 'estate-office' ),
                                    'underground' => __( 'Podziemne', 'estate-office' ),
                                    'garage'    => __( 'Garaż wolnostojący / przylegający', 'estate-office' ),
                                ];
                                foreach ( $parking_options as $key => $label ) {
                                    printf( '<label><input type="checkbox" name="details[building][parking][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $parking[ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Media', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label for="property_heating"><?php esc_html_e( 'Ogrzewanie', 'estate-office' ); ?></label>
                            <select id="property_heating" name="details[utilities][heating]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $heating_types as $heating ) : ?>
                                    <option value="<?php echo esc_attr( $heating ); ?>" <?php selected( $utilities['heating'] ?? '', $heating ); ?>><?php echo esc_html( $heating ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="property_water"><?php esc_html_e( 'Woda', 'estate-office' ); ?></label>
                            <select id="property_water" name="details[utilities][water]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $water_types as $water ) : ?>
                                    <option value="<?php echo esc_attr( $water ); ?>" <?php selected( $utilities['water'] ?? '', $water ); ?>><?php echo esc_html( $water ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="property_sewage"><?php esc_html_e( 'Kanalizacja', 'estate-office' ); ?></label>
                            <select id="property_sewage" name="details[utilities][sewage]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $sewage_types as $sewage ) : ?>
                                    <option value="<?php echo esc_attr( $sewage ); ?>" <?php selected( $utilities['sewage'] ?? '', $sewage ); ?>><?php echo esc_html( $sewage ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label><input type="checkbox" name="details[utilities][gas]" value="1" <?php checked( ! empty( $utilities['gas'] ) ); ?> /> <?php esc_html_e( 'Gaz', 'estate-office' ); ?></label>
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Udogodnienia i wyposażenie', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <div>
                            <span class="label"><?php esc_html_e( 'Udogodnienia', 'estate-office' ); ?></span>
                            <div class="estate-office-toggle-group">
                                <?php
                                foreach ( $amenity_keys as $key => $label ) {
                                    if ( 'umeblowanie' === $key ) {
                                        continue;
                                    }
                                    printf( '<label><input type="checkbox" name="details[amenities][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $amenities[ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                        <p>
                            <label for="property_furnished"><?php esc_html_e( 'Umeblowanie', 'estate-office' ); ?></label>
                            <select id="property_furnished" name="details[amenities][umeblowanie]">
                                <option value="">&mdash;</option>
                                <option value="TAK" <?php selected( $amenities['umeblowanie'] ?? '', 'TAK' ); ?>><?php esc_html_e( 'Tak', 'estate-office' ); ?></option>
                                <option value="NIE" <?php selected( $amenities['umeblowanie'] ?? '', 'NIE' ); ?>><?php esc_html_e( 'Nie', 'estate-office' ); ?></option>
                                <option value="CZĘŚCIOWE" <?php selected( $amenities['umeblowanie'] ?? '', 'CZĘŚCIOWE' ); ?>><?php esc_html_e( 'Częściowe', 'estate-office' ); ?></option>
                            </select>
                        </p>
                        <div class="estate-office-toggle-group">
                            <span class="label"><?php esc_html_e( 'Wyposażenie', 'estate-office' ); ?></span>
                            <?php foreach ( $equipment_keys as $key => $label ) : ?>
                                <label><input type="checkbox" name="details[equipment][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $equipment[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Powierzchnie dodatkowe', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <?php foreach ( $surface_keys as $key => $label ) :
                            $surface = isset( $surfaces[ $key ] ) && is_array( $surfaces[ $key ] ) ? $surfaces[ $key ] : [];
                            $checkbox_id = 'surface_' . $key;
                            ?>
                            <div>
                                <label><input type="checkbox" id="<?php echo esc_attr( $checkbox_id ); ?>" name="details[surfaces][<?php echo esc_attr( $key ); ?>][enabled]" value="1" data-toggle-target="#<?php echo esc_attr( $checkbox_id ); ?>_details" <?php checked( ! empty( $surface['enabled'] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
                                <div id="<?php echo esc_attr( $checkbox_id ); ?>_details">
                                    <input type="number" step="1" name="details[surfaces][<?php echo esc_attr( $key ); ?>][count]" value="<?php echo esc_attr( $surface['count'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Liczba', 'estate-office' ); ?>" />
                                    <input type="number" step="0.01" name="details[surfaces][<?php echo esc_attr( $key ); ?>][area]" value="<?php echo esc_attr( $surface['area'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Powierzchnia (m²)', 'estate-office' ); ?>" />
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></legend>
                    <?php
                    wp_editor(
                        $property ? wp_kses_post( $property->description ) : '',
                        'property_description_basic',
                        [
                            'textarea_name' => 'description',
                            'textarea_rows' => 6,
                        ]
                    );
                    ?>
                </fieldset>

                <?php if ( ! empty( $dynamic_fields ) ) : ?>
                    <fieldset class="estate-office-fieldset">
                        <legend><?php esc_html_e( 'Dodatkowe pola', 'estate-office' ); ?></legend>
                        <div class="estate-office-grid two-cols">
                            <?php foreach ( $dynamic_fields as $field ) : ?>
                                <p>
                                    <label for="property_custom_basic_<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                                    <?php echo EstateOffice_Admin_Clients::render_dynamic_input( $field, $details[ $field['key'] ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Galeria i materiały dodatkowe', 'estate-office' ); ?></legend>
                    <div class="estate-office-gallery" data-target="media[gallery]">
                        <div class="estate-office-gallery-toolbar">
                            <button type="button" class="button estate-office-gallery-select"><?php esc_html_e( 'Dodaj zdjęcia', 'estate-office' ); ?></button>
                            <p class="description"><?php esc_html_e( 'Zdjęcia otrzymają znak wodny z ustawień.', 'estate-office' ); ?></p>
                        </div>
                        <p class="estate-office-gallery-empty" <?php echo empty( $gallery_ids ) ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Brak zdjęć w galerii.', 'estate-office' ); ?></p>
                        <ul class="estate-office-gallery-list">
                            <?php foreach ( $gallery_ids as $attachment_id ) : ?>
                                <li>
                                    <div class="estate-office-gallery-thumb"><?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?></div>
                                    <input type="hidden" name="media[gallery][]" value="<?php echo esc_attr( $attachment_id ); ?>" />
                                    <button type="button" class="button-link estate-office-gallery-remove"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="estate-office-grid two-cols" style="margin-top:1.5rem;">
                        <div class="estate-office-media-field" data-target="media[floor_2d]">
                            <div class="estate-office-media-preview-wrap">
                                <?php
                                if ( $floor_2d ) {
                                    echo wp_get_attachment_image( $floor_2d, 'thumbnail' );
                                } else {
                                    echo '<span class="placeholder">' . esc_html__( 'Brak podglądu', 'estate-office' ) . '</span>';
                                }
                                ?>
                            </div>
                            <div>
                                <input type="hidden" name="media[floor_2d]" value="<?php echo esc_attr( $floor_2d ); ?>" />
                                <button type="button" class="button estate-office-media-select"><?php esc_html_e( 'Wybierz rzut 2D', 'estate-office' ); ?></button>
                                <button type="button" class="button-link estate-office-media-remove" <?php disabled( ! $floor_2d ); ?>><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                            </div>
                        </div>
                        <div class="estate-office-media-field" data-target="media[floor_3d]">
                            <div class="estate-office-media-preview-wrap">
                                <?php
                                if ( $floor_3d ) {
                                    echo wp_get_attachment_image( $floor_3d, 'thumbnail' );
                                } else {
                                    echo '<span class="placeholder">' . esc_html__( 'Brak podglądu', 'estate-office' ) . '</span>';
                                }
                                ?>
                            </div>
                            <div>
                                <input type="hidden" name="media[floor_3d]" value="<?php echo esc_attr( $floor_3d ); ?>" />
                                <button type="button" class="button estate-office-media-select"><?php esc_html_e( 'Wybierz rzut 3D', 'estate-office' ); ?></button>
                                <button type="button" class="button-link estate-office-media-remove" <?php disabled( ! $floor_3d ); ?>><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                            </div>
                        </div>
                        <p class="full">
                            <label for="property_media_video"><?php esc_html_e( 'Link do filmu', 'estate-office' ); ?></label>
                            <input type="url" id="property_media_video" name="media[video]" value="<?php echo esc_attr( $video_url ); ?>" />
                        </p>
                        <p class="full">
                            <label for="property_media_virtual"><?php esc_html_e( 'Link do wirtualnego spaceru', 'estate-office' ); ?></label>
                            <input type="url" id="property_media_virtual" name="media[virtual]" value="<?php echo esc_attr( $virtual_url ); ?>" />
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Znaczniki', 'estate-office' ); ?></legend>
                    <?php
                    $flags = [
                        'new_offer'   => __( 'Nowa oferta', 'estate-office' ),
                        'exclusive'   => __( 'Wyłączność', 'estate-office' ),
                        'new_price'   => __( 'Nowa cena', 'estate-office' ),
                        'no_commission' => __( 'Bez prowizji', 'estate-office' ),
                        'mls'         => __( 'Oferta MLS', 'estate-office' ),
                        'premium'     => __( 'Premium', 'estate-office' ),
                        'sold'        => __( 'Sprzedane', 'estate-office' ),
                        'rented'      => __( 'Wynajęte', 'estate-office' ),
                    ];
                    foreach ( $flags as $flag => $label ) {
                        $attributes = 'class="estate-office-flag"';
                        if ( 'sold' === $flag ) {
                            $attributes .= ' data-transaction-types="SPRZEDAŻ"';
                        }
                        if ( 'rented' === $flag ) {
                            $attributes .= ' data-transaction-types="WYNAJEM"';
                        }
                        printf( '<label %4$s><input type="checkbox" name="tags[%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $flag ), esc_html( $label ), checked( ! empty( $tags[ $flag ] ), true, false ), $attributes );
                    }
                    ?>
                    <label class="estate-office-flag"><input type="checkbox" name="export_www" value="1" <?php checked( ! empty( $property->export_www ) ); ?> /> <?php esc_html_e( 'Eksport na WWW', 'estate-office' ); ?></label>
                    <label class="estate-office-flag"><input type="checkbox" name="export_portals" value="1" disabled /> <?php esc_html_e( 'Eksport na portale (w przygotowaniu)', 'estate-office' ); ?></label>
                </fieldset>

                <?php submit_button( $property ? __( 'Zapisz nieruchomość', 'estate-office' ) : __( 'Dodaj nieruchomość', 'estate-office' ) ); ?>
            </form>
        </div>
        <?php
    }

    protected function render_notice(): void {
        if ( isset( $_GET['status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['status'] ) );
            if ( 'saved' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Nieruchomość zapisana.', 'estate-office' ) . '</p></div>';
            } elseif ( 'deleted' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Nieruchomość usunięta.', 'estate-office' ) . '</p></div>';
            } elseif ( 'error' === $status ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Wystąpił błąd podczas zapisu nieruchomości.', 'estate-office' ) . '</p></div>';
            }
        }
    }

    public static function get_property_media( int $property_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_property_media';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE property_id = %d ORDER BY id ASC", $property_id ) );
    }

    public static function get_properties( string $search = '' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';
        $contracts_table = $wpdb->prefix . 'eo_contracts';
        $agents_table    = $wpdb->prefix . 'eo_agents';

        if ( empty( $search ) ) {
            $sql = "SELECT p.*, c.contract_number,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.price')) AS price,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.price_m2')) AS price_m2,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.area')) AS area,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.rooms')) AS rooms,
                    CONCAT_WS(', ', JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.street')), JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.city'))) AS address_display,
                    a.first_name AS agent_first_name, a.last_name AS agent_last_name, a.email AS agent_email, a.phone AS agent_phone
                    FROM {$table} p
                    LEFT JOIN {$contracts_table} c ON c.id = p.contract_id
                    LEFT JOIN {$agents_table} a ON a.id = p.agent_id
                    ORDER BY p.created_at DESC";
            return $wpdb->get_results( $sql );
        }

        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql  = $wpdb->prepare(
            "SELECT p.*, c.contract_number,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.price')) AS price,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.price_m2')) AS price_m2,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.area')) AS area,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.rooms')) AS rooms,
                    CONCAT_WS(', ', JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.street')), JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.city'))) AS address_display,
                    a.first_name AS agent_first_name, a.last_name AS agent_last_name, a.email AS agent_email, a.phone AS agent_phone
             FROM {$table} p
             LEFT JOIN {$contracts_table} c ON c.id = p.contract_id
             LEFT JOIN {$agents_table} a ON a.id = p.agent_id
             WHERE c.contract_number LIKE %s OR JSON_EXTRACT(p.address, '$.city') LIKE %s
             ORDER BY p.created_at DESC",
            $like,
            $like
        );
        return $wpdb->get_results( $sql );
    }

    public static function get_property( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_properties WHERE id = %d', $id ) );
    }

    protected static function format_currency( $value ): string {
        if ( '' === $value || null === $value ) {
            return '';
        }
        return number_format_i18n( (float) $value, 2 ) . ' PLN';
    }
}
