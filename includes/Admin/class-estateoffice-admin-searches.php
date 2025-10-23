<?php
/**
 * Searches management page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Searches extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-searches';

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Poszukiwania', 'estate-office' );
        $this->page_title = __( 'Poszukiwania', 'estate-office' );
        $this->capability = 'eo_manage_searches';
    }

    public function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $search_id = isset( $_GET['search'] ) ? absint( $_GET['search'] ) : 0;

        if ( 'new' === $action || ( 'edit' === $action && $search_id ) ) {
            $search    = $search_id ? self::get_search( $search_id ) : null;
            $contracts = EstateOffice_Admin_Contracts::get_contracts();
            $agents    = EstateOffice_Admin_Agents::get_agents();
            $dynamic   = EstateOffice_Admin_Settings::get_dynamic_fields( 'contract' );
            $this->render_form( $search, $contracts, $dynamic, $agents );
            return;
        }

        $query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $searches = self::get_searches( $query );
        $this->render_list( $searches, $query );
    }

    protected function render_list( array $searches, string $query ): void {
        ?>
        <div class="wrap estate-office-wrap estate-office-searches">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <?php $this->render_notice(); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Dodaj poszukiwanie', 'estate-office' ); ?></a>

            <form method="get" class="estate-office-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <label for="estate-office-search-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj poszukiwań', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-search-search" name="s" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php esc_attr_e( 'Szukaj w dowolnej kolumnie', 'estate-office' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Numer poszukiwania', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Budżet', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $searches ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'Brak poszukiwań.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $searches as $row ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&search=' . absint( $row->id ) ) ); ?>"><?php echo esc_html( sprintf( '#S%04d', $row->id ) ); ?></a></td>
                                <td><?php echo esc_html( $row->property_type ?: '—' ); ?></td>
                                <td><?php echo esc_html( self::format_budget( $row->price_min, $row->price_max ) ); ?></td>
                                <td><?php echo esc_html( $row->location ?: '—' ); ?></td>
                                <td><?php echo esc_html( $row->transaction_type ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $row ) ?: '—' ); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&search=' . absint( $row->id ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office' ); ?></a>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=edit&contract=' . absint( $row->contract_id ) ) ); ?>"><?php esc_html_e( 'Umowa', 'estate-office' ); ?></a>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć to poszukiwanie?', 'estate-office' ) ); ?>');">
                                            <?php wp_nonce_field( 'estate_office_delete_search' ); ?>
                                            <input type="hidden" name="action" value="estate_office_delete_search" />
                                            <input type="hidden" name="search_id" value="<?php echo esc_attr( $row->id ); ?>" />
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

    protected function render_form( $search, array $contracts, array $dynamic_fields, array $agents ): void {
        $criteria = $search && $search->criteria ? json_decode( $search->criteria, true ) : [];
        if ( ! is_array( $criteria ) ) {
            $criteria = [];
        }
        $contract_index = [];
        foreach ( $contracts as $contract_row ) {
            $contract_index[ $contract_row->id ] = [
                'transaction' => $contract_row->transaction_type,
                'agent'       => (int) ( $contract_row->agent_id ?? 0 ),
            ];
        }
        $selected_contract = $search ? (int) $search->contract_id : 0;
        $transaction_type  = $search->transaction_type ?? '';
        $search_agent      = (int) ( $search->agent_id ?? 0 );
        if ( $selected_contract && isset( $contract_index[ $selected_contract ] ) ) {
            if ( empty( $transaction_type ) ) {
                $transaction_type = $contract_index[ $selected_contract ]['transaction'];
            }
            if ( ! $search_agent && ! empty( $contract_index[ $selected_contract ]['agent'] ) ) {
                $search_agent = $contract_index[ $selected_contract ]['agent'];
            }
        }

        $building  = isset( $criteria['building'] ) && is_array( $criteria['building'] ) ? $criteria['building'] : [];
        $utilities = isset( $criteria['utilities'] ) && is_array( $criteria['utilities'] ) ? $criteria['utilities'] : [];
        $amenities = isset( $criteria['amenities'] ) && is_array( $criteria['amenities'] ) ? $criteria['amenities'] : [];
        $equipment = isset( $criteria['equipment'] ) && is_array( $criteria['equipment'] ) ? $criteria['equipment'] : [];
        $surfaces  = isset( $criteria['surfaces'] ) && is_array( $criteria['surfaces'] ) ? $criteria['surfaces'] : [];
        $plot      = isset( $criteria['plot'] ) && is_array( $criteria['plot'] ) ? $criteria['plot'] : [];

        $property_types = [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ];
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
            'balcony'  => __( 'Balkon', 'estate-office' ),
            'terrace'  => __( 'Taras', 'estate-office' ),
            'basement' => __( 'Piwnica', 'estate-office' ),
            'storage'  => __( 'Komórka lokatorska', 'estate-office' ),
            'garden'   => __( 'Ogródek', 'estate-office' ),
        ];
        ?>
        <div class="wrap estate-office-wrap estate-office-search-edit">
            <h1><?php echo esc_html( $search ? __( 'Edytuj poszukiwanie', 'estate-office' ) : __( 'Dodaj poszukiwanie', 'estate-office' ) ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-search-form">
                <?php wp_nonce_field( 'estate_office_save_search' ); ?>
                <input type="hidden" name="action" value="estate_office_save_search" />
                <?php if ( $search ) : ?>
                    <input type="hidden" name="search_id" value="<?php echo esc_attr( $search->id ); ?>" />
                <?php endif; ?>

                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="search_contract" class="required"><?php esc_html_e( 'Powiązana umowa', 'estate-office' ); ?></label>
                        <select id="search_contract" name="contract_id" required>
                            <option value="">&mdash;</option>
                            <?php foreach ( $contracts as $contract ) : ?>
                                <option value="<?php echo esc_attr( $contract->id ); ?>" data-transaction="<?php echo esc_attr( $contract->transaction_type ); ?>" data-agent="<?php echo esc_attr( (int) ( $contract->agent_id ?? 0 ) ); ?>" <?php selected( $selected_contract, (int) $contract->id ); ?>><?php echo esc_html( $contract->contract_number ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="search_agent"><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></label>
                        <select id="search_agent" name="agent_id" data-fallback="<?php echo esc_attr( $search_agent ); ?>">
                            <option value=""><?php esc_html_e( 'Wybierz opiekuna', 'estate-office' ); ?></option>
                            <?php foreach ( $agents as $agent_row ) :
                                $label = EstateOffice_Admin_Agents::format_agent_name( $agent_row );
                                if ( '' === $label ) {
                                    $label = sprintf( __( 'Agent #%d', 'estate-office' ), (int) $agent_row->id );
                                }
                                ?>
                                <option value="<?php echo esc_attr( $agent_row->id ); ?>" <?php selected( $search_agent, (int) $agent_row->id ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="search_transaction_display"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                        <input type="text" id="search_transaction_display" class="regular-text" value="<?php echo esc_attr( $transaction_type ); ?>" readonly />
                        <input type="hidden" id="search_transaction" name="transaction_type" value="<?php echo esc_attr( $transaction_type ); ?>" data-fallback="<?php echo esc_attr( $transaction_type ); ?>" />
                        <span class="description"><?php esc_html_e( 'Typ transakcji jest kopiowany z umowy i nie podlega ręcznej zmianie.', 'estate-office' ); ?></span>
                    </p>
                </div>

                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="search_price_from"><?php esc_html_e( 'Cena od', 'estate-office' ); ?></label>
                        <input type="number" id="search_price_from" name="criteria[price_min]" value="<?php echo esc_attr( $criteria['price_min'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="search_price_to"><?php esc_html_e( 'Cena do', 'estate-office' ); ?></label>
                        <input type="number" id="search_price_to" name="criteria[price_max]" value="<?php echo esc_attr( $criteria['price_max'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="search_area_from"><?php esc_html_e( 'Metraż od', 'estate-office' ); ?></label>
                        <input type="number" id="search_area_from" name="criteria[area_min]" value="<?php echo esc_attr( $criteria['area_min'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="search_area_to"><?php esc_html_e( 'Metraż do', 'estate-office' ); ?></label>
                        <input type="number" id="search_area_to" name="criteria[area_max]" value="<?php echo esc_attr( $criteria['area_max'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="search_rooms_from"><?php esc_html_e( 'Liczba pokoi od', 'estate-office' ); ?></label>
                        <input type="number" id="search_rooms_from" name="criteria[rooms_min]" value="<?php echo esc_attr( $criteria['rooms_min'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="search_rooms_to"><?php esc_html_e( 'Liczba pokoi do', 'estate-office' ); ?></label>
                        <input type="number" id="search_rooms_to" name="criteria[rooms_max]" value="<?php echo esc_attr( $criteria['rooms_max'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="search_property_type"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                        <select id="search_property_type" name="criteria[property_type]">
                            <option value="">&mdash;</option>
                            <?php foreach ( $property_types as $type ) { printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $type ), esc_html( $type ), selected( $criteria['property_type'] ?? '', $type, false ) ); } ?>
                        </select>
                    </p>
                    <p>
                        <label for="search_location"><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></label>
                        <input type="text" id="search_location" name="criteria[location]" value="<?php echo esc_attr( $criteria['location'] ?? '' ); ?>" />
                    </p>
                </div>

                <fieldset class="estate-office-fieldset" data-property-types="MIESZKANIE,DOM,LOKAL H/U">
                    <legend><?php esc_html_e( 'Budynek i układ', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label for="search_finish"><?php esc_html_e( 'Stan wykończenia', 'estate-office' ); ?></label>
                            <select id="search_finish" name="criteria[building][finish_state]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $finish_states as $state ) : ?>
                                    <option value="<?php echo esc_attr( $state ); ?>" <?php selected( $building['finish_state'] ?? '', $state ); ?>><?php echo esc_html( $state ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="search_kitchen"><?php esc_html_e( 'Rodzaj kuchni', 'estate-office' ); ?></label>
                            <select id="search_kitchen" name="criteria[building][kitchen]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $kitchen_types as $kitchen ) : ?>
                                    <option value="<?php echo esc_attr( $kitchen ); ?>" <?php selected( $building['kitchen'] ?? '', $kitchen ); ?>><?php echo esc_html( ucfirst( strtolower( $kitchen ) ) ); ?></option>
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
                                    printf( '<label><input type="checkbox" name="criteria[building][exposure][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $building['exposure'][ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                        <div>
                            <span class="label"><?php esc_html_e( 'Widok', 'estate-office' ); ?></span>
                            <div class="estate-office-toggle-group">
                                <?php
                                $views = [
                                    'panorama' => __( 'Panorama miasta', 'estate-office' ),
                                    'park'     => __( 'Park / Zieleń', 'estate-office' ),
                                    'street'   => __( 'Ulica', 'estate-office' ),
                                    'water'    => __( 'Woda', 'estate-office' ),
                                ];
                                foreach ( $views as $key => $label ) {
                                    printf( '<label><input type="checkbox" name="criteria[building][view][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $building['view'][ $key ] ), true, false ) );
                                }
                                ?>
                            </div>
                        </div>
                        <p>
                            <label><input type="checkbox" name="criteria[building][attic]" value="1" <?php checked( ! empty( $building['attic'] ) ); ?> /> <?php esc_html_e( 'Poddasze', 'estate-office' ); ?></label>
                        </p>
                        <p data-property-types="MIESZKANIE,LOKAL H/U">
                            <label><input type="checkbox" name="criteria[building][multilevel]" value="1" <?php checked( ! empty( $building['multilevel'] ) ); ?> /> <?php esc_html_e( 'Wielopoziomowe', 'estate-office' ); ?></label>
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Media', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label for="search_heating"><?php esc_html_e( 'Ogrzewanie', 'estate-office' ); ?></label>
                            <select id="search_heating" name="criteria[utilities][heating]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $heating_types as $heating ) : ?>
                                    <option value="<?php echo esc_attr( $heating ); ?>" <?php selected( $utilities['heating'] ?? '', $heating ); ?>><?php echo esc_html( $heating ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="search_water"><?php esc_html_e( 'Woda', 'estate-office' ); ?></label>
                            <select id="search_water" name="criteria[utilities][water]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $water_types as $water ) : ?>
                                    <option value="<?php echo esc_attr( $water ); ?>" <?php selected( $utilities['water'] ?? '', $water ); ?>><?php echo esc_html( $water ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="search_sewage"><?php esc_html_e( 'Kanalizacja', 'estate-office' ); ?></label>
                            <select id="search_sewage" name="criteria[utilities][sewage]">
                                <option value="">&mdash;</option>
                                <?php foreach ( $sewage_types as $sewage ) : ?>
                                    <option value="<?php echo esc_attr( $sewage ); ?>" <?php selected( $utilities['sewage'] ?? '', $sewage ); ?>><?php echo esc_html( $sewage ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label><input type="checkbox" name="criteria[utilities][gas]" value="1" <?php checked( ! empty( $utilities['gas'] ) ); ?> /> <?php esc_html_e( 'Gaz', 'estate-office' ); ?></label>
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Udogodnienia i wyposażenie', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <div class="estate-office-toggle-group">
                            <span class="label"><?php esc_html_e( 'Udogodnienia', 'estate-office' ); ?></span>
                            <?php foreach ( $amenity_keys as $key => $label ) :
                                if ( 'umeblowanie' === $key ) {
                                    continue;
                                }
                                printf( '<label><input type="checkbox" name="criteria[amenities][%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $key ), esc_html( $label ), checked( ! empty( $amenities[ $key ] ), true, false ) );
                            endforeach; ?>
                        </div>
                        <p>
                            <label for="search_furnished"><?php esc_html_e( 'Umeblowanie', 'estate-office' ); ?></label>
                            <select id="search_furnished" name="criteria[amenities][umeblowanie]">
                                <option value="">&mdash;</option>
                                <option value="TAK" <?php selected( $amenities['umeblowanie'] ?? '', 'TAK' ); ?>><?php esc_html_e( 'Tak', 'estate-office' ); ?></option>
                                <option value="NIE" <?php selected( $amenities['umeblowanie'] ?? '', 'NIE' ); ?>><?php esc_html_e( 'Nie', 'estate-office' ); ?></option>
                                <option value="CZĘŚCIOWE" <?php selected( $amenities['umeblowanie'] ?? '', 'CZĘŚCIOWE' ); ?>><?php esc_html_e( 'Częściowe', 'estate-office' ); ?></option>
                            </select>
                        </p>
                        <div class="estate-office-toggle-group">
                            <span class="label"><?php esc_html_e( 'Wyposażenie', 'estate-office' ); ?></span>
                            <?php foreach ( $equipment_keys as $key => $label ) : ?>
                                <label><input type="checkbox" name="criteria[equipment][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $equipment[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Powierzchnie dodatkowe', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <?php foreach ( $surface_keys as $key => $label ) :
                            $surface = isset( $surfaces[ $key ] ) && is_array( $surfaces[ $key ] ) ? $surfaces[ $key ] : [];
                            $checkbox_id = 'search_surface_' . $key;
                            ?>
                            <div>
                                <label><input type="checkbox" id="<?php echo esc_attr( $checkbox_id ); ?>" name="criteria[surfaces][<?php echo esc_attr( $key ); ?>][enabled]" value="1" data-toggle-target="#<?php echo esc_attr( $checkbox_id ); ?>_details" <?php checked( ! empty( $surface['enabled'] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
                                <div id="<?php echo esc_attr( $checkbox_id ); ?>_details">
                                    <input type="number" step="1" name="criteria[surfaces][<?php echo esc_attr( $key ); ?>][min_count]" value="<?php echo esc_attr( $surface['min_count'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Minimalna liczba', 'estate-office' ); ?>" />
                                    <input type="number" step="0.01" name="criteria[surfaces][<?php echo esc_attr( $key ); ?>][min_area]" value="<?php echo esc_attr( $surface['min_area'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Minimalna powierzchnia (m²)', 'estate-office' ); ?>" />
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset" data-property-types="DZIAŁKA">
                    <legend><?php esc_html_e( 'Kryteria działki', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label for="search_plot_shape"><?php esc_html_e( 'Preferowany kształt', 'estate-office' ); ?></label>
                            <select id="search_plot_shape" name="criteria[plot][shape]">
                                <option value="">&mdash;</option>
                                <option value="REGULARNY" <?php selected( $plot['shape'] ?? '', 'REGULARNY' ); ?>><?php esc_html_e( 'Regularny', 'estate-office' ); ?></option>
                                <option value="NIEREGULARNY" <?php selected( $plot['shape'] ?? '', 'NIEREGULARNY' ); ?>><?php esc_html_e( 'Nieregularny', 'estate-office' ); ?></option>
                            </select>
                        </p>
                        <p>
                            <label for="search_plot_description"><?php esc_html_e( 'Dodatkowe wymagania', 'estate-office' ); ?></label>
                            <textarea id="search_plot_description" name="criteria[plot][description]" rows="3"><?php echo esc_textarea( $plot['description'] ?? '' ); ?></textarea>
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Opis poszukiwania', 'estate-office' ); ?></legend>
                    <?php
                    wp_editor(
                        $search ? wp_kses_post( $search->description ) : '',
                        'search_description_editor',
                        [
                            'textarea_name' => 'description',
                            'textarea_rows' => 6,
                        ]
                    );
                    ?>
                </fieldset>

                <?php if ( ! empty( $dynamic_fields ) ) : ?>
                    <fieldset class="estate-office-fieldset">
                        <legend><?php esc_html_e( 'Dodatkowe kryteria', 'estate-office' ); ?></legend>
                        <div class="estate-office-grid two-cols">
                            <?php foreach ( $dynamic_fields as $field ) : ?>
                                <p>
                                    <label for="search_custom_<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                                    <?php echo EstateOffice_Admin_Clients::render_dynamic_input( $field, $criteria[ $field['key'] ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <?php submit_button( $search ? __( 'Zapisz poszukiwanie', 'estate-office' ) : __( 'Dodaj poszukiwanie', 'estate-office' ) ); ?>
            </form>
        </div>
        <?php
    }

    protected function render_notice(): void {
        if ( isset( $_GET['status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['status'] ) );
            if ( 'saved' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Poszukiwanie zapisane.', 'estate-office' ) . '</p></div>';
            } elseif ( 'deleted' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Poszukiwanie usunięte.', 'estate-office' ) . '</p></div>';
            } elseif ( 'error' === $status ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Wystąpił błąd podczas zapisu poszukiwania.', 'estate-office' ) . '</p></div>';
            }
        }
    }

    public static function get_searches( string $query = '' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_searches';
        $contracts_table = $wpdb->prefix . 'eo_contracts';
        $agents_table    = $wpdb->prefix . 'eo_agents';

        $select = "SELECT s.*, c.contract_number,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_min')) AS price_min,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_max')) AS price_max,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.property_type')) AS property_type,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.location')) AS location,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.area_min')) AS area_min,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.area_max')) AS area_max,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.rooms_min')) AS rooms_min,
                JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.rooms_max')) AS rooms_max,
                a.first_name AS agent_first_name, a.last_name AS agent_last_name, a.email AS agent_email, a.phone AS agent_phone, a.slug AS agent_slug
                FROM {$table} s
                LEFT JOIN {$contracts_table} c ON c.id = s.contract_id
                LEFT JOIN {$agents_table} a ON a.id = s.agent_id";

        if ( empty( $query ) ) {
            return $wpdb->get_results( $select . ' ORDER BY s.created_at DESC' );
        }

        $like       = '%' . $wpdb->esc_like( $query ) . '%';
        $conditions = [
            "CONCAT('#S', LPAD(s.id, 4, '0')) LIKE %s",
            'CAST(s.id AS CHAR) LIKE %s',
            's.transaction_type LIKE %s',
            'c.contract_number LIKE %s',
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.property_type')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.location')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.city')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.district')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.voivodeship')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_min')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_max')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.area_min')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.area_max')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.rooms_min')) LIKE %s",
            "JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.rooms_max')) LIKE %s",
            "JSON_EXTRACT(s.criteria, '$.building') LIKE %s",
            "JSON_EXTRACT(s.criteria, '$.utilities') LIKE %s",
            "JSON_EXTRACT(s.criteria, '$.amenities') LIKE %s",
            "JSON_EXTRACT(s.criteria, '$.equipment') LIKE %s",
            "JSON_EXTRACT(s.criteria, '$.surfaces') LIKE %s",
            "JSON_EXTRACT(s.criteria, '$.plot') LIKE %s",
            'CONCAT_WS(\' \', a.first_name, a.last_name) LIKE %s',
            'a.email LIKE %s',
            'a.phone LIKE %s',
        ];

        $params = array_fill( 0, count( $conditions ), $like );
        array_unshift( $params, $select . ' WHERE ' . implode( ' OR ', $conditions ) . ' ORDER BY s.created_at DESC' );
        $sql = call_user_func_array( [ $wpdb, 'prepare' ], $params );

        return $wpdb->get_results( $sql );
    }

    public static function get_search( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_searches WHERE id = %d', $id ) );
    }

    protected static function format_budget( $from, $to ): string {
        if ( '' === $from && '' === $to ) {
            return '';
        }
        $parts = [];
        if ( '' !== $from && null !== $from ) {
            $parts[] = sprintf( '%s %s', esc_html__( 'od', 'estate-office' ), number_format_i18n( (float) $from, 0 ) . ' PLN' );
        }
        if ( '' !== $to && null !== $to ) {
            $parts[] = sprintf( '%s %s', esc_html__( 'do', 'estate-office' ), number_format_i18n( (float) $to, 0 ) . ' PLN' );
        }
        return implode( ' ', $parts );
    }
}
