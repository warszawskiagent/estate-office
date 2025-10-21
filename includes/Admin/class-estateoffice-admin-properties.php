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
            $dynamic_fields = EstateOffice_Admin_Settings::get_dynamic_fields( 'property' );
            $this->render_form( $property, $contracts, $dynamic_fields );
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
                                <td>&mdash;</td>
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

    protected function render_form( $property, array $contracts, array $dynamic_fields ): void {
        $details = $property && $property->details ? json_decode( $property->details, true ) : [];
        $address = $property && $property->address ? json_decode( $property->address, true ) : [];
        $legal   = $property && $property->legal ? json_decode( $property->legal, true ) : [];
        $tags    = $property && $property->tags ? json_decode( $property->tags, true ) : [];
        $contract_transactions = [];
        foreach ( $contracts as $contract_row ) {
            $contract_transactions[ $contract_row->id ] = $contract_row->transaction_type;
        }
        $selected_contract = $property ? (int) $property->contract_id : 0;
        $transaction_type  = $property->transaction_type ?? '';
        if ( empty( $transaction_type ) && $selected_contract && isset( $contract_transactions[ $selected_contract ] ) ) {
            $transaction_type = $contract_transactions[ $selected_contract ];
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
                                <option value="<?php echo esc_attr( $contract->id ); ?>" data-transaction="<?php echo esc_attr( $contract->transaction_type ); ?>" <?php selected( $selected_contract, (int) $contract->id ); ?>><?php echo esc_html( $contract->contract_number ); ?></option>
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
                            <?php foreach ( [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ] as $type ) : ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $property->property_type ?? 'MIESZKANIE', $type ); ?>><?php echo esc_html( $type ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="property_price_basic" class="required"><?php esc_html_e( 'Cena', 'estate-office' ); ?></label>
                        <input type="number" step="0.01" id="property_price_basic" name="details[price]" value="<?php echo esc_attr( $details['price'] ?? '' ); ?>" />
                    </p>
                <p>
                    <label for="property_area_basic" class="required"><?php esc_html_e( 'Metraż', 'estate-office' ); ?></label>
                    <input type="number" step="0.01" id="property_area_basic" name="details[area]" value="<?php echo esc_attr( $details['area'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="property_price_basic_m2"><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></label>
                    <input type="number" step="0.01" id="property_price_basic_m2" name="details[price_m2]" value="<?php echo esc_attr( $details['price_m2'] ?? '' ); ?>" readonly />
                </p>
                <p>
                    <label for="property_rooms_basic"><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></label>
                    <input type="number" id="property_rooms_basic" name="details[rooms]" value="<?php echo esc_attr( $details['rooms'] ?? '' ); ?>" />
                </p>
                </div>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Adres', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label for="property_address_street"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_street" name="address[street]" value="<?php echo esc_attr( $address['street'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_number"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_number" name="address[number]" value="<?php echo esc_attr( $address['number'] ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="property_address_city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                            <input type="text" id="property_address_city" name="address[city]" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>" />
                        </p>
                    </div>
                </fieldset>

                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label for="property_legal_kw"><?php esc_html_e( 'Numer KW', 'estate-office' ); ?></label>
                            <input type="text" id="property_legal_kw" name="legal[land_register]" value="<?php echo esc_attr( $legal['land_register'] ?? '' ); ?>" />
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
                    <legend><?php esc_html_e( 'Znaczniki', 'estate-office' ); ?></legend>
                    <?php
                    $flags = [
                        'new_offer'   => __( 'Nowa oferta', 'estate-office' ),
                        'exclusive'   => __( 'Wyłączność', 'estate-office' ),
                        'new_price'   => __( 'Nowa cena', 'estate-office' ),
                        'premium'     => __( 'Premium', 'estate-office' ),
                    ];
                    foreach ( $flags as $flag => $label ) {
                        printf( '<label class="estate-office-flag"><input type="checkbox" name="tags[%1$s]" value="1" %3$s /> %2$s</label>', esc_attr( $flag ), esc_html( $label ), checked( ! empty( $tags[ $flag ] ), true, false ) );
                    }
                    ?>
                    <label class="estate-office-flag"><input type="checkbox" name="export_www" value="1" <?php checked( ! empty( $property->export_www ) ); ?> /> <?php esc_html_e( 'Eksport na WWW', 'estate-office' ); ?></label>
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

    public static function get_properties( string $search = '' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';
        $contracts_table = $wpdb->prefix . 'eo_contracts';

        if ( empty( $search ) ) {
            $sql = "SELECT p.*, c.contract_number,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.price')) AS price,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.price_m2')) AS price_m2,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.area')) AS area,
                    JSON_UNQUOTE(JSON_EXTRACT(p.details, '$.rooms')) AS rooms,
                    CONCAT_WS(', ', JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.street')), JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.city'))) AS address_display
                    FROM {$table} p
                    LEFT JOIN {$contracts_table} c ON c.id = p.contract_id
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
                    CONCAT_WS(', ', JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.street')), JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.city'))) AS address_display
             FROM {$table} p
             LEFT JOIN {$contracts_table} c ON c.id = p.contract_id
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
