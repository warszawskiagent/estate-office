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
            $dynamic   = EstateOffice_Admin_Settings::get_dynamic_fields( 'contract' );
            $this->render_form( $search, $contracts, $dynamic );
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
                <input type="search" id="estate-office-search-search" name="s" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php esc_attr_e( 'Szukaj po typie nieruchomości lub lokalizacji', 'estate-office' ); ?>" />
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

    protected function render_form( $search, array $contracts, array $dynamic_fields ): void {
        $criteria = $search && $search->criteria ? json_decode( $search->criteria, true ) : [];
        if ( ! is_array( $criteria ) ) {
            $criteria = [];
        }
        ?>
        <div class="wrap estate-office-wrap estate-office-search-edit">
            <h1><?php echo esc_html( $search ? __( 'Edytuj poszukiwanie', 'estate-office' ) : __( 'Dodaj poszukiwanie', 'estate-office' ) ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-search-form-details">
                <?php wp_nonce_field( 'estate_office_save_search' ); ?>
                <input type="hidden" name="action" value="estate_office_save_search" />
                <?php if ( $search ) : ?>
                    <input type="hidden" name="search_id" value="<?php echo esc_attr( $search->id ); ?>" />
                <?php endif; ?>

                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="search_contract"><?php esc_html_e( 'Powiązana umowa', 'estate-office' ); ?></label>
                        <select id="search_contract" name="contract_id">
                            <option value="">&mdash;</option>
                            <?php foreach ( $contracts as $contract ) : ?>
                                <option value="<?php echo esc_attr( $contract->id ); ?>" <?php selected( $search->contract_id ?? '', $contract->id ); ?>><?php echo esc_html( $contract->contract_number ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="search_transaction"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                        <select id="search_transaction" name="transaction_type">
                            <?php foreach ( EstateOffice_Admin_Contracts::TRANSACTION_TYPES as $type ) : ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $search->transaction_type ?? '', $type ); ?>><?php echo esc_html( $type ); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                            <?php foreach ( [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ] as $type ) {
                                printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $type ), esc_html( $type ), selected( $criteria['property_type'] ?? '', $type, false ) );
                            } ?>
                        </select>
                    </p>
                    <p>
                        <label for="search_location"><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></label>
                        <input type="text" id="search_location" name="criteria[location]" value="<?php echo esc_attr( $criteria['location'] ?? '' ); ?>" />
                    </p>
                </div>

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

        if ( empty( $query ) ) {
            $sql = "SELECT s.*, c.contract_number,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_min')) AS price_min,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_max')) AS price_max,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.property_type')) AS property_type,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.location')) AS location
                    FROM {$table} s
                    LEFT JOIN {$contracts_table} c ON c.id = s.contract_id
                    ORDER BY s.created_at DESC";
            return $wpdb->get_results( $sql );
        }

        $like = '%' . $wpdb->esc_like( $query ) . '%';
        $sql  = $wpdb->prepare(
            "SELECT s.*, c.contract_number,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_min')) AS price_min,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.price_max')) AS price_max,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.property_type')) AS property_type,
                    JSON_UNQUOTE(JSON_EXTRACT(s.criteria, '$.location')) AS location
             FROM {$table} s
             LEFT JOIN {$contracts_table} c ON c.id = s.contract_id
             WHERE JSON_EXTRACT(s.criteria, '$.property_type') LIKE %s OR JSON_EXTRACT(s.criteria, '$.location') LIKE %s
             ORDER BY s.created_at DESC",
            $like,
            $like
        );
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
