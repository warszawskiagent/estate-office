<?php
/**
 * Contracts management page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Contracts extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-contracts';

    public const TRANSACTION_TYPES = [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ];

    public const COMMISSION_UNITS = [ '%', 'PLN', 'EUR', 'USD' ];

    protected const PER_PAGE = 20;

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Umowy', 'estate-office' );
        $this->page_title = __( 'Umowy', 'estate-office' );
        $this->capability = 'eo_manage_contracts';
    }

    public function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $contract_id = isset( $_GET['contract'] ) ? absint( $_GET['contract'] ) : 0;

        if ( 'new' === $action || ( 'edit' === $action && $contract_id ) ) {
            $contract      = $contract_id ? self::get_contract( $contract_id ) : null;
            $clients_data  = EstateOffice_Admin_Clients::get_clients( '', 1, 0 );
            $clients       = $clients_data['items'];
            $agents   = EstateOffice_Admin_Agents::get_agents();
            $property = $contract ? self::get_property_for_contract( $contract_id ) : null;
            $search   = $contract ? self::get_search_for_contract( $contract_id ) : null;
            $dynamic_contract_fields = EstateOffice_Admin_Settings::get_dynamic_fields( 'contract' );
            $property_fields         = EstateOffice_Admin_Settings::get_dynamic_fields( 'property' );
            $client_ids = $contract ? self::get_contract_clients( $contract_id ) : [];
            $stage_history = $contract && $contract->stage_history ? json_decode( $contract->stage_history, true ) : [];
            if ( ! is_array( $stage_history ) ) {
                $stage_history = [];
            }
            if ( empty( $stage_history ) ) {
                $default_stage = $contract && $contract->stage ? sanitize_key( $contract->stage ) : 'umowa_posrednictwa';
                $default_date  = $contract && ! empty( $contract->start_date ) ? sanitize_text_field( $contract->start_date ) : '';
                $stage_history[] = [
                    'stage' => $default_stage,
                    'date'  => $default_date,
                ];
            }
            $this->render_form( $contract, $clients, $client_ids, $property, $search, $stage_history, $dynamic_contract_fields, $property_fields, $agents );
            return;
        }

        $search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page     = self::PER_PAGE;
        $results      = self::get_contracts( $search, $current_page, $per_page );
        $this->render_list( $results['items'], $search, $current_page, $per_page, $results['total'] );
    }

    protected function render_list( array $contracts, string $search, int $current_page, int $per_page, int $total ): void {
        ?>
        <div class="wrap estate-office-wrap estate-office-contracts">
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $this->page_title ); ?></h1>
                <div class="estate-office-admin-heading-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?></a>
                </div>
            </div>
            <?php $this->render_notice(); ?>

            <form method="get" class="estate-office-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <label for="estate-office-contract-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj umów', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-contract-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Szukaj w dowolnej kolumnie', 'estate-office' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Aktualny etap', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $contracts ) ) : ?>
                        <tr><td colspan="9"><?php esc_html_e( 'Brak umów.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $contracts as $contract ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&contract=' . absint( $contract->id ) ) ); ?>"><?php echo esc_html( $contract->contract_number ); ?></a></td>
                                <td><?php echo esc_html( $contract->transaction_type ); ?></td>
                                <td><?php echo esc_html( strtoupper( $contract->property_type ?? '' ) ); ?></td>
                                <td><?php echo esc_html( $contract->address ?? '' ); ?></td>
                                <td><?php echo esc_html( $contract->start_date ); ?></td>
                                <td><?php echo esc_html( $contract->end_date ?: __( 'Bezterminowa', 'estate-office' ) ); ?></td>
                                <td><?php echo esc_html( self::get_stage_label( $contract->stage ) ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $contract ) ?: '—' ); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&contract=' . absint( $contract->id ) ) ); ?>"><?php esc_html_e( 'Edytuj', 'estate-office' ); ?></a>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć tę umowę?', 'estate-office' ) ); ?>');">
                                            <?php wp_nonce_field( 'estate_office_delete_contract' ); ?>
                                            <input type="hidden" name="action" value="estate_office_delete_contract" />
                                            <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract->id ); ?>" />
                                            <button type="submit" class="button-link delete-link"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            $this->render_pagination(
                $total,
                $per_page,
                $current_page,
                [
                    'page' => self::SLUG,
                    's'    => $search,
                ]
            );
            ?>
        </div>
        <?php
    }

    protected function render_form( $contract, array $clients, array $selected_clients, $property, $search, array $stage_history, array $contract_fields, array $property_fields, array $agents ): void {
        $transaction_type = $contract->transaction_type ?? 'SPRZEDAŻ';
        $contract_agent   = (int) ( $contract->agent_id ?? 0 );
        $stage_history = array_map( static function ( $entry ) {
            return [
                'stage' => sanitize_key( $entry['stage'] ?? '' ),
                'date'  => sanitize_text_field( $entry['date'] ?? '' ),
            ];
        }, $stage_history );
        ?>
        <div class="wrap estate-office-wrap estate-office-contract-edit">
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $contract ? __( 'Edytuj umowę', 'estate-office' ) : __( 'Nowa umowa', 'estate-office' ) ); ?></h1>
                <div class="estate-office-admin-heading-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a>
                </div>
            </div>
            <?php $this->render_notice(); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-contract-form" data-transaction="<?php echo esc_attr( $transaction_type ); ?>">
                <?php wp_nonce_field( 'estate_office_save_contract' ); ?>
                <input type="hidden" name="action" value="estate_office_save_contract" />
                <?php if ( $contract ) : ?>
                    <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract->id ); ?>" />
                <?php endif; ?>

                <ol class="estate-office-stepper" aria-label="<?php esc_attr_e( 'Proces dodawania umowy', 'estate-office' ); ?>">
                    <li class="active" data-step="1"><span class="number">1</span><span class="label-text"><?php esc_html_e( 'Umowa', 'estate-office' ); ?></span></li>
                    <li data-step="2"><span class="number">2</span><span class="label-text"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></span></li>
                    <li data-step="3"
                        data-label-default="<?php echo esc_attr__( 'Oferta / Poszukiwanie', 'estate-office' ); ?>"
                        data-label-property="<?php echo esc_attr__( 'Nieruchomość', 'estate-office' ); ?>"
                        data-label-search="<?php echo esc_attr__( 'Poszukiwanie', 'estate-office' ); ?>">
                        <span class="number">3</span><span class="label-text"><?php esc_html_e( 'Oferta / Poszukiwanie', 'estate-office' ); ?></span>
                    </li>
                </ol>

                <section class="estate-office-section estate-office-step" data-step="1">
                    <h2><?php esc_html_e( 'Dane umowy', 'estate-office' ); ?></h2>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label for="contract_number" class="required"><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></label>
                            <input type="text" id="contract_number" name="contract_number" value="<?php echo esc_attr( $contract->contract_number ?? '' ); ?>" required />
                        </p>
                        <p>
                            <label for="transaction_type" class="required"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                            <select id="transaction_type" name="transaction_type" required>
                                <?php foreach ( self::TRANSACTION_TYPES as $type ) : ?>
                                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $transaction_type, $type ); ?>><?php echo esc_html( $type ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="contract_stage"><?php esc_html_e( 'Aktualny etap', 'estate-office' ); ?></label>
                            <select id="contract_stage" name="stage">
                                <?php foreach ( self::get_stages() as $stage_key => $label ) : ?>
                                    <option value="<?php echo esc_attr( $stage_key ); ?>" <?php selected( $contract->stage ?? 'umowa_posrednictwa', $stage_key ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="start_date" class="required"><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $contract->start_date ?? '' ); ?>" required />
                        </p>
                        <p>
                            <label for="end_date"><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $contract->end_date ?? '' ); ?>" <?php echo ! empty( $contract->indefinite ) ? 'disabled' : ''; ?> />
                            <label class="estate-office-toggle"><input type="checkbox" name="indefinite" value="1" <?php checked( ! empty( $contract->indefinite ) ); ?> /> <?php esc_html_e( 'Umowa bezterminowa', 'estate-office' ); ?></label>
                        </p>
                        <p>
                            <label for="commission_amount"><?php esc_html_e( 'Wysokość prowizji', 'estate-office' ); ?></label>
                            <input type="number" step="0.01" id="commission_amount" name="commission_amount" value="<?php echo esc_attr( $contract->commission_amount ?? '' ); ?>" />
                        </p>
                        <p>
                            <label for="commission_unit" class="screen-reader-text"><?php esc_html_e( 'Jednostka prowizji', 'estate-office' ); ?></label>
                            <select id="commission_unit" name="commission_unit">
                                <?php foreach ( self::COMMISSION_UNITS as $unit ) : ?>
                                    <option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $contract->commission_unit ?? '', $unit ); ?>><?php echo esc_html( $unit ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="contract_agent"><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></label>
                            <select id="contract_agent" name="agent_id">
                                <option value=""><?php esc_html_e( 'Wybierz opiekuna', 'estate-office' ); ?></option>
                                <?php foreach ( $agents as $agent_row ) :
                                    $label = EstateOffice_Admin_Agents::format_agent_name( $agent_row );
                                    if ( '' === $label ) {
                                        $label = sprintf( __( 'Agent #%d', 'estate-office' ), (int) $agent_row->id );
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr( $agent_row->id ); ?>" <?php selected( $contract_agent, (int) $agent_row->id ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    </div>
                </section>

                <section class="estate-office-section estate-office-stage-history estate-office-step" data-step="1">
                    <h2><?php esc_html_e( 'Historia etapów', 'estate-office' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Pierwszy wpis odzwierciedla etap rozpoczęcia i synchronizuje się z datą zawarcia umowy.', 'estate-office' ); ?></p>
                    <table class="widefat striped estate-office-stage-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Etap', 'estate-office' ); ?></th>
                                <th><?php esc_html_e( 'Data', 'estate-office' ); ?></th>
                                <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $stage_history ) ) : ?>
                                <tr class="no-items"><td colspan="3"><?php esc_html_e( 'Brak historii etapów.', 'estate-office' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $stage_history as $index => $item ) : ?>
                                    <tr>
                                        <td>
                                            <select name="stage_history[<?php echo esc_attr( $index ); ?>][stage]">
                                                <?php foreach ( self::get_stages() as $stage_key => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $stage_key ); ?>" <?php selected( $item['stage'], $stage_key ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="date" name="stage_history[<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( $item['date'] ); ?>" /></td>
                                        <td><button type="button" class="button-link estate-office-remove-row">&times;</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p><button type="button" class="button estate-office-add-stage" data-template="estate-office-stage-row"><?php esc_html_e( 'Dodaj etap', 'estate-office' ); ?></button></p>
                    <input type="hidden" name="stage_history_json" value="<?php echo esc_attr( wp_json_encode( $stage_history ) ); ?>" class="estate-office-stage-history-json" />
                </section>

                <section class="estate-office-section estate-office-step estate-office-clients-step" data-step="2">
                    <h2><?php esc_html_e( 'Klienci', 'estate-office' ); ?></h2>
                    <p><?php esc_html_e( 'Dodaj istniejących klientów lub utwórz nowych uczestników umowy.', 'estate-office' ); ?></p>

                    <div class="estate-office-clients-search">
                        <h3><?php esc_html_e( 'Wyszukaj klienta', 'estate-office' ); ?></h3>
                        <div class="estate-office-client-filter">
                            <input type="search" data-filter="name" placeholder="<?php esc_attr_e( 'Imię lub nazwisko', 'estate-office' ); ?>" />
                            <input type="search" data-filter="phone" placeholder="<?php esc_attr_e( 'Telefon', 'estate-office' ); ?>" />
                            <input type="search" data-filter="email" placeholder="<?php esc_attr_e( 'Adres e-mail', 'estate-office' ); ?>" />
                        </div>
                        <table class="widefat striped estate-office-clients-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Nazwa', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $clients ) ) : ?>
                                    <tr class="no-items"><td colspan="4"><?php esc_html_e( 'Brak klientów w bazie.', 'estate-office' ); ?></td></tr>
                                <?php else : ?>
                                    <?php foreach ( $clients as $client_row ) : ?>
                                        <?php
                                        $name        = EstateOffice_Admin_Clients::format_client_name( $client_row );
                                        $phone       = $client_row->phone ?? '';
                                        $email       = $client_row->email ?? '';
                                        $search_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
                                        $search_phone = function_exists( 'mb_strtolower' ) ? mb_strtolower( $phone ) : strtolower( $phone );
                                        $search_email = function_exists( 'mb_strtolower' ) ? mb_strtolower( $email ) : strtolower( $email );
                                        ?>
                                        <tr data-client-id="<?php echo esc_attr( $client_row->id ); ?>"
                                            data-name="<?php echo esc_attr( $search_name ); ?>"
                                            data-phone="<?php echo esc_attr( $search_phone ); ?>"
                                            data-email="<?php echo esc_attr( $search_email ); ?>">
                                            <td><?php echo esc_html( $name ); ?></td>
                                            <td><?php echo esc_html( $client_row->phone ); ?></td>
                                            <td><?php echo esc_html( $client_row->email ); ?></td>
                                            <td>
                                                <button type="button" class="button estate-office-client-add" data-client-id="<?php echo esc_attr( $client_row->id ); ?>" <?php disabled( in_array( $client_row->id, $selected_clients, true ) ); ?>><?php esc_html_e( 'Dodaj do umowy', 'estate-office' ); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="estate-office-selected-clients" aria-live="polite">
                        <h3><?php esc_html_e( 'Wybrani klienci', 'estate-office' ); ?></h3>
                        <ul data-empty="<?php echo esc_attr__( 'Nie wybrano jeszcze żadnego klienta.', 'estate-office' ); ?>">
                            <?php if ( empty( $selected_clients ) ) : ?>
                                <li class="empty"><?php esc_html_e( 'Nie wybrano jeszcze żadnego klienta.', 'estate-office' ); ?></li>
                            <?php else : ?>
                                <?php foreach ( $selected_clients as $client_id ) : ?>
                                    <?php $client = EstateOffice_Admin_Clients::get_client( $client_id ); ?>
                                    <?php if ( ! $client ) { continue; } ?>
                                    <?php $label = EstateOffice_Admin_Clients::format_client_name( $client ); ?>
                                    <li data-client-id="<?php echo esc_attr( $client_id ); ?>">
                                        <span class="label"><?php echo esc_html( $label ); ?></span>
                                        <button type="button" class="button-link estate-office-remove-selected" aria-label="<?php echo esc_attr( sprintf( __( 'Usuń klienta %s', 'estate-office' ), $label ) ); ?>">&times;</button>
                                        <input type="hidden" name="contract_clients[]" value="<?php echo esc_attr( $client_id ); ?>" />
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="estate-office-new-clients">
                        <h3><?php esc_html_e( 'Dodaj nowego klienta', 'estate-office' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'Wypełnij formularz aby utworzyć nowego klienta i powiązać go z umową.', 'estate-office' ); ?></p>
                        <div class="estate-office-new-client-list" data-empty="<?php echo esc_attr__( 'Brak nowych klientów.', 'estate-office' ); ?>">
                            <p class="empty"><?php esc_html_e( 'Brak nowych klientów.', 'estate-office' ); ?></p>
                        </div>
                        <p class="estate-office-new-client-actions">
                            <button type="button" class="button button-secondary estate-office-add-new-client" data-template="estate-office-new-client-template"><?php esc_html_e( 'Dodaj klienta', 'estate-office' ); ?></button>
                        </p>
                        <div class="estate-office-more-clients" role="group" aria-label="<?php esc_attr_e( 'Czy dodać kolejnego klienta?', 'estate-office' ); ?>">
                            <p><?php esc_html_e( 'Czy chcesz dodać kolejnego klienta?', 'estate-office' ); ?></p>
                            <label><input type="radio" name="add_more_clients" value="yes" /> <?php esc_html_e( 'Tak', 'estate-office' ); ?></label>
                            <label><input type="radio" name="add_more_clients" value="no" checked /> <?php esc_html_e( 'Nie', 'estate-office' ); ?></label>
                        </div>
                    </div>
                </section>

                <?php $this->render_property_section( $transaction_type, $property, $property_fields, $agents, $contract_agent ); ?>
                <?php $this->render_search_section( $transaction_type, $search, $contract_fields, $agents, $contract_agent ); ?>
                <div class="estate-office-step-actions">
                    <button type="button" class="button button-secondary estate-office-prev-step" disabled><?php esc_html_e( 'Wstecz', 'estate-office' ); ?></button>
                    <button type="button" class="button button-primary estate-office-next-step"><?php esc_html_e( 'Dalej', 'estate-office' ); ?></button>
                    <?php submit_button( $contract ? __( 'Zapisz umowę', 'estate-office' ) : __( 'Utwórz umowę', 'estate-office' ), 'primary estate-office-submit-button', 'submit', false ); ?>
                </div>
            </form>
        </div>
        <script type="text/html" id="tmpl-estate-office-stage-row">
            <tr>
                <td>
                    <select name="stage_history[{{data.index}}][stage]">
                        <?php foreach ( self::get_stages() as $stage_key => $label ) : ?>
                            <option value="<?php echo esc_attr( $stage_key ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="date" name="stage_history[{{data.index}}][date]" value="" /></td>
                <td><button type="button" class="button-link estate-office-remove-row">&times;</button></td>
            </tr>
        </script>
        <script type="text/html" id="tmpl-estate-office-new-client-template">
            <div class="estate-office-new-client-card" data-index="{{data.index}}">
                <div class="estate-office-card-header">
                    <strong><?php esc_html_e( 'Nowy klient', 'estate-office' ); ?></strong>
                    <button type="button" class="button-link estate-office-remove-new-client" aria-label="<?php esc_attr_e( 'Usuń nowego klienta', 'estate-office' ); ?>">&times;</button>
                </div>
                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Typ klienta', 'estate-office' ); ?></legend>
                    <label><input type="radio" name="new_clients[{{data.index}}][client_type]" value="individual" checked /> <?php esc_html_e( 'Osoba fizyczna', 'estate-office' ); ?></label>
                    <label><input type="radio" name="new_clients[{{data.index}}][client_type]" value="company" /> <?php esc_html_e( 'Firma', 'estate-office' ); ?></label>
                </fieldset>
                <div class="estate-office-grid two-cols" data-section="individual">
                    <p>
                    <label class="required"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                    <input type="text" name="new_clients[{{data.index}}][first_name]" data-required-for="individual" />
                    </p>
                    <p>
                    <label class="required"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                    <input type="text" name="new_clients[{{data.index}}][last_name]" data-required-for="individual" />
                    </p>
                </div>
                <div class="estate-office-grid two-cols" data-section="company" style="display:none;">
                    <p>
                    <label class="required"><?php esc_html_e( 'Nazwa firmy', 'estate-office' ); ?></label>
                    <input type="text" name="new_clients[{{data.index}}][company_name]" data-required-for="company" />
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Imię i nazwisko reprezentanta', 'estate-office' ); ?></label>
                        <input type="text" name="new_clients[{{data.index}}][representative_name]" />
                    </p>
                </div>
                <div class="estate-office-grid two-cols">
                    <p>
                        <label class="required"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label>
                        <input type="text" name="new_clients[{{data.index}}][phone]" required />
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label>
                        <input type="email" name="new_clients[{{data.index}}][email]" />
                    </p>
                    <p data-section="company" style="display:none;">
                        <label><?php esc_html_e( 'Strona WWW', 'estate-office' ); ?></label>
                        <input type="url" name="new_clients[{{data.index}}][website]" />
                    </p>
                </div>
                <fieldset class="estate-office-fieldset" data-section="individual">
                    <legend><?php esc_html_e( 'Dane identyfikacyjne', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <p>
                            <label><?php esc_html_e( 'PESEL', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][identification][pesel]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Rodzaj dokumentu', 'estate-office' ); ?></label>
                            <select name="new_clients[{{data.index}}][identification][document_type]">
                                <option value="">&mdash;</option>
                                <option value="dowod"><?php esc_html_e( 'Dowód osobisty', 'estate-office' ); ?></option>
                                <option value="paszport"><?php esc_html_e( 'Paszport', 'estate-office' ); ?></option>
                                <option value="karta_pobytu"><?php esc_html_e( 'Karta pobytu', 'estate-office' ); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Numer dokumentu', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][identification][document_no]" />
                        </p>
                    </div>
                </fieldset>
                <fieldset class="estate-office-fieldset" data-section="company" style="display:none;">
                    <legend><?php esc_html_e( 'Dane rejestrowe', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label><?php esc_html_e( 'NIP', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][identification][nip]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'KRS', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][identification][krs]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'REGON', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][identification][regon]" />
                        </p>
                    </div>
                </fieldset>
                <fieldset class="estate-office-fieldset estate-office-address">
                    <legend><?php esc_html_e( 'Adres zamieszkania / rejestrowy', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label class="required"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][address][street]" required />
                        </p>
                        <p>
                            <label class="required"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][address][number]" required />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][address][unit]" />
                        </p>
                        <p>
                            <label class="required"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][address][postal_code]" required />
                        </p>
                        <p>
                            <label class="required"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][address][city]" required />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Kraj', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][address][country]" />
                        </p>
                    </div>
                </fieldset>
                <fieldset class="estate-office-fieldset estate-office-address">
                    <legend><?php esc_html_e( 'Adres korespondencyjny', 'estate-office' ); ?></legend>
                    <label class="estate-office-toggle"><input type="checkbox" name="new_clients[{{data.index}}][correspondence][same]" value="1" checked /> <?php esc_html_e( 'Adres korespondencyjny taki sam', 'estate-office' ); ?></label>
                    <div class="estate-office-grid three-cols">
                        <p>
                            <label><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][correspondence][street]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][correspondence][number]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][correspondence][unit]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][correspondence][postal_code]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][correspondence][city]" />
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Kraj', 'estate-office' ); ?></label>
                            <input type="text" name="new_clients[{{data.index}}][correspondence][country]" />
                        </p>
                    </div>
                </fieldset>
            </div>
        </script>
        <?php
    }

    protected function render_property_section( string $transaction_type, $property, array $property_fields, array $agents, int $default_agent ): void {
        $details = $property && $property->details ? json_decode( $property->details, true ) : [];
        $address = $property && $property->address ? json_decode( $property->address, true ) : [];
        $legal   = $property && $property->legal ? json_decode( $property->legal, true ) : [];
        $tags    = $property && $property->tags ? json_decode( $property->tags, true ) : [];
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

        $available_portals = estate_office_get_portals();
        $selected_portals  = $property ? EstateOffice_Admin_Properties::get_property_portal_slugs( (int) $property->id ) : [];

        $normalized_transaction = strtoupper( $transaction_type );
        $price_label_default    = __( 'Cena', 'estate-office' );
        $price_label_rent       = sprintf( __( 'Cena (%s)', 'estate-office' ), __( 'miesięcznie', 'estate-office' ) );
        $price_label            = 'WYNAJEM' === $normalized_transaction ? $price_label_rent : $price_label_default;
        $property_agent = (int) ( $property->agent_id ?? 0 );
        if ( ! $property_agent ) {
            $property_agent = $default_agent;
        }

        ?>
        <section class="estate-office-section estate-office-property estate-office-step" data-step="3" data-transaction-target="property">
            <h2><?php esc_html_e( 'Nieruchomość', 'estate-office' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Wypełnij dane nieruchomości. Sekcja jest wymagana dla transakcji sprzedaży i wynajmu.', 'estate-office' ); ?></p>
            <input type="hidden" name="property[property_id]" value="<?php echo esc_attr( $property->id ?? 0 ); ?>" />
            <input type="hidden" id="property_transaction_type" name="property[transaction_type]" value="<?php echo esc_attr( $transaction_type ); ?>" />
            <div class="estate-office-grid two-cols">
                <p>
                    <label for="contract_property_agent"><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></label>
                    <select id="contract_property_agent" name="property[agent_id]" data-fallback="<?php echo esc_attr( $property_agent ); ?>">
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
                    <label for="property_type" class="required"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                    <select id="property_type" name="property[property_type]" required>
                        <?php
                        $types = [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ];
                        $selected_type = $property->property_type ?? 'MIESZKANIE';
                        foreach ( $types as $type ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $type ), esc_html( $type ), selected( $selected_type, $type, false ) );
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <label for="property_price" class="required" data-default-label="<?php echo esc_attr( $price_label_default ); ?>" data-rent-label="<?php echo esc_attr( $price_label_rent ); ?>"><?php echo esc_html( $price_label ); ?></label>
                    <input type="number" step="0.01" id="property_price" name="property[details][price]" value="<?php echo esc_attr( $details['price'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="property_rent"><?php esc_html_e( 'Czynsz administracyjny', 'estate-office' ); ?></label>
                    <input type="number" step="0.01" id="property_rent" name="property[details][rent]" value="<?php echo esc_attr( $details['rent'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="property_area" class="required"><?php esc_html_e( 'Powierzchnia (m²)', 'estate-office' ); ?></label>
                    <input type="number" step="0.01" id="property_area" name="property[details][area]" value="<?php echo esc_attr( $details['area'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="property_price_m2"><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></label>
                    <input type="number" step="0.01" id="property_price_m2" name="property[details][price_m2]" value="<?php echo esc_attr( $details['price_m2'] ?? '' ); ?>" readonly />
                </p>
                <p data-property-types="MIESZKANIE,DOM,LOKAL H/U">
                    <label for="property_rooms"><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></label>
                    <input type="number" id="property_rooms" name="property[details][rooms]" value="<?php echo esc_attr( $details['rooms'] ?? '' ); ?>" />
                </p>
                <p data-property-types="MIESZKANIE,DOM">
                    <label for="property_bedrooms"><?php esc_html_e( 'Liczba sypialni', 'estate-office' ); ?></label>
                    <input type="number" id="property_bedrooms" name="property[details][bedrooms]" value="<?php echo esc_attr( $details['bedrooms'] ?? '' ); ?>" />
                </p>
                <p data-property-types="MIESZKANIE,DOM,LOKAL H/U">
                    <label for="property_bathrooms"><?php esc_html_e( 'Liczba łazienek', 'estate-office' ); ?></label>
                    <input type="number" id="property_bathrooms" name="property[details][bathrooms]" value="<?php echo esc_attr( $details['bathrooms'] ?? '' ); ?>" />
                </p>
                <p data-property-types="MIESZKANIE,DOM,LOKAL H/U">
                    <label for="property_toilets"><?php esc_html_e( 'Liczba toalet', 'estate-office' ); ?></label>
                    <input type="number" id="property_toilets" name="property[details][toilets]" value="<?php echo esc_attr( $details['toilets'] ?? '' ); ?>" />
                </p>
                <p data-property-types="DOM">
                    <label for="property_house_type"><?php esc_html_e( 'Typ domu', 'estate-office' ); ?></label>
                    <select id="property_house_type" name="property[details][house_type]">
                        <?php
                        $options = [ 'WOLNOSTOJĄCY', 'BLIŹNIAK', 'SZEREGOWIEC', 'WIELORODZINNY' ];
                        $selected = $details['house_type'] ?? '';
                        echo '<option value="">' . esc_html__( 'Wybierz', 'estate-office' ) . '</option>';
                        foreach ( $options as $option ) {
                            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $option ), esc_html( $option ), selected( $selected, $option, false ) );
                        }
                        ?>
                    </select>
                </p>
                <p data-property-types="DZIAŁKA">
                    <label for="property_plot_shape"><?php esc_html_e( 'Kształt działki', 'estate-office' ); ?></label>
                    <select id="property_plot_shape" name="property[details][plot_shape]">
                        <option value="">&mdash;</option>
                        <option value="regular" <?php selected( $details['plot_shape'] ?? '', 'regular' ); ?>><?php esc_html_e( 'Regularny', 'estate-office' ); ?></option>
                        <option value="irregular" <?php selected( $details['plot_shape'] ?? '', 'irregular' ); ?>><?php esc_html_e( 'Nieregularny', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p data-property-types="DZIAŁKA" data-plot-shape="regular">
                    <label for="property_plot_dimensions"><?php esc_html_e( 'Wymiary działki (długość x szerokość)', 'estate-office' ); ?></label>
                    <input type="text" id="property_plot_dimensions" name="property[details][plot_dimensions]" value="<?php echo esc_attr( $details['plot_dimensions'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'np. 30m x 25m', 'estate-office' ); ?>" />
                </p>
                <p data-property-types="DZIAŁKA" data-plot-shape="irregular">
                    <label for="property_plot_description"><?php esc_html_e( 'Opis kształtu działki', 'estate-office' ); ?></label>
                    <textarea id="property_plot_description" name="property[details][plot_description]" rows="3"><?php echo esc_textarea( $details['plot_description'] ?? '' ); ?></textarea>
                </p>
            </div>

            <fieldset class="estate-office-fieldset">
                <legend><?php esc_html_e( 'Adres nieruchomości', 'estate-office' ); ?></legend>
                <div class="estate-office-grid three-cols" data-property-types="MIESZKANIE,DOM,LOKAL H/U">
                    <p>
                        <label for="property_street" class="required"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                        <input type="text" id="property_street" name="property[address][street]" value="<?php echo esc_attr( $address['street'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_number" class="required"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                        <input type="text" id="property_number" name="property[address][number]" value="<?php echo esc_attr( $address['number'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_unit"><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                        <input type="text" id="property_unit" name="property[address][unit]" value="<?php echo esc_attr( $address['unit'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_postal" class="required"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                        <input type="text" id="property_postal" name="property[address][postal_code]" value="<?php echo esc_attr( $address['postal_code'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_district"><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></label>
                        <input type="text" id="property_district" name="property[address][district]" value="<?php echo esc_attr( $address['district'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_city" class="required"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                        <input type="text" id="property_city" name="property[address][city]" value="<?php echo esc_attr( $address['city'] ?? '' ); ?>" />
                    </p>
                </div>
                <div class="estate-office-grid three-cols" data-property-types="DOM,DZIAŁKA">
                    <p>
                        <label for="property_county"><?php esc_html_e( 'Powiat', 'estate-office' ); ?></label>
                        <input type="text" id="property_county" name="property[address][county]" value="<?php echo esc_attr( $address['county'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_precinct"><?php esc_html_e( 'Obręb', 'estate-office' ); ?></label>
                        <input type="text" id="property_precinct" name="property[address][precinct]" value="<?php echo esc_attr( $address['precinct'] ?? '' ); ?>" />
                    </p>
                    <p>
                        <label for="property_plot_number"><?php esc_html_e( 'Numer działki', 'estate-office' ); ?></label>
                        <input type="text" id="property_plot_number" name="property[address][plot_number]" value="<?php echo esc_attr( $address['plot_number'] ?? '' ); ?>" />
                    </p>
                </div>
            </fieldset>

            <fieldset class="estate-office-fieldset">
                <legend><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></legend>
                <div class="estate-office-grid two-cols">
                    <p>
                        <label for="property_land_register"><?php esc_html_e( 'Numer Księgi Wieczystej', 'estate-office' ); ?></label>
                        <input type="text" id="property_land_register" name="property[legal][land_register]" value="<?php echo esc_attr( $legal['land_register'] ?? '' ); ?>" />
                        <label class="estate-office-toggle"><input type="checkbox" name="property[legal][no_land_register]" value="1" <?php checked( ! empty( $legal['no_land_register'] ) ); ?> /> <?php esc_html_e( 'Brak KW', 'estate-office' ); ?></label>
                    </p>
                    <p>
                        <label for="property_ownership"><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></label>
                        <select id="property_ownership" name="property[legal][ownership]">
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
                <p>
                    <button type="button" class="button estate-office-map-button" data-api-key="<?php echo esc_attr( get_option( 'estate_office_google_maps_api_key', '' ) ); ?>"><?php esc_html_e( 'Zaznacz na mapie', 'estate-office' ); ?></button>
                </p>
            </fieldset>

            <fieldset class="estate-office-fieldset">
                <legend><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></legend>
                <?php
                wp_editor(
                    $property ? wp_kses_post( $property->description ) : '',
                    'property_description',
                    [
                        'textarea_name' => 'property[description]',
                        'textarea_rows' => 6,
                    ]
                );
                ?>
            </fieldset>

            <?php if ( ! empty( $property_fields ) ) : ?>
                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Dodatkowe pola nieruchomości', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <?php foreach ( $property_fields as $field ) : ?>
                            <p>
                                <label for="property_custom_<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? '<span class="required">*</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
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
                    'new_offer'     => [ 'label' => __( 'Nowa oferta', 'estate-office' ) ],
                    'exclusive'     => [ 'label' => __( 'Wyłączność', 'estate-office' ) ],
                    'sold'          => [
                        'label'        => __( 'Sprzedane', 'estate-office' ),
                        'transactions' => [ 'SPRZEDAŻ' ],
                    ],
                    'rented'        => [
                        'label'        => __( 'Wynajęte', 'estate-office' ),
                        'transactions' => [ 'WYNAJEM' ],
                    ],
                    'new_price'     => [ 'label' => __( 'Nowa cena', 'estate-office' ) ],
                    'no_commission' => [ 'label' => __( 'Bez prowizji', 'estate-office' ) ],
                    'mls'           => [ 'label' => __( 'Oferta MLS', 'estate-office' ) ],
                    'premium'       => [ 'label' => __( 'Premium', 'estate-office' ) ],
                ];
                foreach ( $flags as $flag => $config ) {
                    $transactions_attr = '';
                    if ( ! empty( $config['transactions'] ) ) {
                        $transactions_attr = ' data-transaction-types="' . esc_attr( implode( ',', array_map( 'strtoupper', (array) $config['transactions'] ) ) ) . '"';
                    }
                    printf(
                        '<label class="estate-office-flag"%4$s><input type="checkbox" name="property[tags][%1$s]" value="1" %3$s /> %2$s</label>',
                        esc_attr( $flag ),
                        esc_html( $config['label'] ),
                        checked( ! empty( $tags[ $flag ] ), true, false ),
                        $transactions_attr
                    );
                }
                ?>
                <?php $export_portals_checked = ! empty( $property->export_portals ) || ! empty( $selected_portals ); ?>
                <label class="estate-office-flag"><input type="checkbox" name="property[export_www]" value="1" <?php checked( ! empty( $property->export_www ) ); ?> /> <?php esc_html_e( 'Eksport na WWW', 'estate-office' ); ?></label>
                <label class="estate-office-flag"><input type="checkbox" name="property[export_portals]" value="1" <?php checked( $export_portals_checked ); ?> data-toggle-target="#estate-office-contract-portals" /> <?php esc_html_e( 'Eksport na portale', 'estate-office' ); ?></label>
                <div id="estate-office-contract-portals" class="estate-office-portal-targets">
                    <?php if ( empty( $available_portals ) ) : ?>
                        <p class="description"><?php esc_html_e( 'Brak aktywnych portali. Dodaj je w ustawieniach w sekcji „Eksport na portale”.', 'estate-office' ); ?></p>
                    <?php else : ?>
                        <?php foreach ( $available_portals as $portal ) :
                            $slug = sanitize_title( $portal['slug'] ?? '' );
                            if ( '' === $slug ) {
                                continue;
                            }
                            $is_enabled = ! empty( $portal['is_enabled'] );
                            if ( ! $is_enabled && ! in_array( $slug, $selected_portals, true ) ) {
                                continue;
                            }
                            ?>
                            <label class="estate-office-flag<?php echo $is_enabled ? '' : ' estate-office-flag-disabled'; ?>">
                                <input type="checkbox" name="property[portals][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_portals, true ) ); ?> <?php disabled( ! $is_enabled ); ?> />
                                <?php echo esc_html( $portal['name'] ?? $slug ); ?>
                                <?php if ( ! $is_enabled ) : ?>
                                    <span class="description"><?php esc_html_e( 'Portal wyłączony w ustawieniach', 'estate-office' ); ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </fieldset>
        </section>
        <?php
    }

    protected function render_search_section( string $transaction_type, $search, array $contract_fields, array $agents, int $default_agent ): void {
        $criteria = $search && $search->criteria ? json_decode( $search->criteria, true ) : [];
        if ( ! is_array( $criteria ) ) {
            $criteria = [];
        }
        $search_agent = (int) ( $search->agent_id ?? 0 );
        if ( ! $search_agent ) {
            $search_agent = $default_agent;
        }
        ?>
        <section class="estate-office-section estate-office-search estate-office-step" data-step="3" data-transaction-target="search">
            <h2><?php esc_html_e( 'Poszukiwanie', 'estate-office' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Wypełnij, jeżeli umowa dotyczy kupna lub najmu.', 'estate-office' ); ?></p>
            <input type="hidden" name="search[search_id]" value="<?php echo esc_attr( $search->id ?? 0 ); ?>" />
            <input type="hidden" id="search_transaction_type" name="search[transaction_type]" value="<?php echo esc_attr( $transaction_type ); ?>" />
            <p>
                <label for="contract_search_agent"><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></label>
                <select id="contract_search_agent" name="search[agent_id]" data-fallback="<?php echo esc_attr( $search_agent ); ?>">
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
            <div class="estate-office-grid two-cols">
                <p>
                    <label for="search_price_min"><?php esc_html_e( 'Cena od', 'estate-office' ); ?></label>
                    <input type="number" id="search_price_min" name="search[criteria][price_min]" value="<?php echo esc_attr( $criteria['price_min'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="search_price_max"><?php esc_html_e( 'Cena do', 'estate-office' ); ?></label>
                    <input type="number" id="search_price_max" name="search[criteria][price_max]" value="<?php echo esc_attr( $criteria['price_max'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="search_area_min"><?php esc_html_e( 'Metraż od', 'estate-office' ); ?></label>
                    <input type="number" id="search_area_min" name="search[criteria][area_min]" value="<?php echo esc_attr( $criteria['area_min'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="search_area_max"><?php esc_html_e( 'Metraż do', 'estate-office' ); ?></label>
                    <input type="number" id="search_area_max" name="search[criteria][area_max]" value="<?php echo esc_attr( $criteria['area_max'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="search_rooms_min"><?php esc_html_e( 'Liczba pokoi od', 'estate-office' ); ?></label>
                    <input type="number" id="search_rooms_min" name="search[criteria][rooms_min]" value="<?php echo esc_attr( $criteria['rooms_min'] ?? '' ); ?>" />
                </p>
                <p>
                    <label for="search_rooms_max"><?php esc_html_e( 'Liczba pokoi do', 'estate-office' ); ?></label>
                    <input type="number" id="search_rooms_max" name="search[criteria][rooms_max]" value="<?php echo esc_attr( $criteria['rooms_max'] ?? '' ); ?>" />
                </p>
            </div>
            <fieldset class="estate-office-fieldset">
                <legend><?php esc_html_e( 'Opis poszukiwania', 'estate-office' ); ?></legend>
                <?php
                wp_editor(
                    $search ? wp_kses_post( $search->description ) : '',
                    'search_description',
                    [
                        'textarea_name' => 'search[description]',
                        'textarea_rows' => 5,
                    ]
                );
                ?>
            </fieldset>

            <?php if ( ! empty( $contract_fields ) ) : ?>
                <fieldset class="estate-office-fieldset">
                    <legend><?php esc_html_e( 'Dodatkowe kryteria', 'estate-office' ); ?></legend>
                    <div class="estate-office-grid two-cols">
                        <?php foreach ( $contract_fields as $field ) : ?>
                            <p>
                                <label for="search_custom_<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? '<span class="required">*</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <?php echo EstateOffice_Admin_Clients::render_dynamic_input( $field, $criteria[ $field['key'] ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>
        </section>
        <?php
    }

    protected function render_notice(): void {
        if ( isset( $_GET['status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['status'] ) );
            if ( 'saved' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Umowa zapisana.', 'estate-office' ) . '</p></div>';
            } elseif ( 'deleted' === $status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Umowa usunięta.', 'estate-office' ) . '</p></div>';
            } elseif ( 'error' === $status ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Nie udało się zapisać umowy.', 'estate-office' ) . '</p></div>';
            } elseif ( 'duplicate' === $status ) {
                $number = '';
                if ( isset( $_GET['duplicate_number'] ) ) {
                    $number = sanitize_text_field( wp_unslash( $_GET['duplicate_number'] ) );
                }
                $message = $number
                    ? sprintf( __( 'Umowa o numerze %s już istnieje. Wybierz inny identyfikator.', 'estate-office' ), $number )
                    : __( 'Umowa o podanym numerze już istnieje. Wybierz inny identyfikator.', 'estate-office' );
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            }
        }
    }

    public static function get_stages(): array {
        return [
            'umowa_posrednictwa'  => __( 'Umowa pośrednictwa', 'estate-office' ),
            'publikacja_mls'      => __( 'Publikacja w MLS', 'estate-office' ),
            'przygotowanie_oferty' => __( 'Przygotowanie oferty', 'estate-office' ),
            'publikacja_oferty'   => __( 'Publikacja oferty', 'estate-office' ),
            'marketing'           => __( 'Marketing i prezentacje', 'estate-office' ),
            'oferta_kupna'        => __( 'Oferta kupna', 'estate-office' ),
            'negocjacje'          => __( 'Negocjacje', 'estate-office' ),
            'umowa_przedwstepna'  => __( 'Umowa przedwstępna', 'estate-office' ),
            'umowa_przyrzeczona'  => __( 'Umowa przyrzeczona', 'estate-office' ),
            'przekazanie_lokalu'  => __( 'Przekazanie lokalu', 'estate-office' ),
            'umowa_zakonczona'    => __( 'Umowa zakończona', 'estate-office' ),
        ];
    }

    protected static function get_stage_label( string $stage ): string {
        $stages = self::get_stages();
        return $stages[ $stage ] ?? $stage;
    }

    public static function get_contracts( string $search = '', int $page = 1, int $per_page = self::PER_PAGE ): array {
        global $wpdb;
        $table          = $wpdb->prefix . 'eo_contracts';
        $property_table = $wpdb->prefix . 'eo_properties';
        $agents_table   = $wpdb->prefix . 'eo_agents';

        $page     = max( 1, (int) $page );
        $per_page = (int) $per_page;
        $limit    = $per_page > 0;

        $select_fields = "SELECT c.*, p.property_type,
                JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.street')) AS property_street,
                JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.number')) AS property_number,
                JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.unit')) AS property_unit,
                JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.city')) AS address,
                JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.district')) AS property_district,
                a.first_name AS agent_first_name, a.last_name AS agent_last_name, a.email AS agent_email, a.phone AS agent_phone, a.slug AS agent_slug";

        $from = " FROM {$table} c"
            . " LEFT JOIN {$property_table} p ON p.contract_id = c.id"
            . " LEFT JOIN {$agents_table} a ON a.id = c.agent_id";

        $where  = '';
        $params = [];

        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $conditions = [
                "CONCAT('#', LPAD(c.id, 5, '0')) LIKE %s",
                'CAST(c.id AS CHAR) LIKE %s',
                'c.contract_number LIKE %s',
                'c.transaction_type LIKE %s',
                'c.stage LIKE %s',
                'c.commission_unit LIKE %s',
                'CAST(c.commission_amount AS CHAR) LIKE %s',
                'DATE_FORMAT(c.start_date, "%Y-%m-%d") LIKE %s',
                'DATE_FORMAT(c.end_date, "%Y-%m-%d") LIKE %s',
                'p.property_type LIKE %s',
                "JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.street')) LIKE %s",
                "JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.number')) LIKE %s",
                "JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.unit')) LIKE %s",
                "JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.district')) LIKE %s",
                "JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.city')) LIKE %s",
                "JSON_UNQUOTE(JSON_EXTRACT(p.address, '$.postal_code')) LIKE %s",
                "CONCAT_WS(' ', a.first_name, a.last_name) LIKE %s",
                'a.email LIKE %s',
                'a.phone LIKE %s',
            ];
            $where  = ' WHERE ' . implode( ' OR ', $conditions );
            $params = array_fill( 0, count( $conditions ), $like );
        }

        $order_by = ' ORDER BY c.created_at DESC';

        $items_sql    = $select_fields . $from . $where . $order_by;
        $items_params = $params;

        if ( $limit ) {
            $items_sql      .= ' LIMIT %d OFFSET %d';
            $items_params[]  = $per_page;
            $items_params[]  = ( $page - 1 ) * $per_page;
        }

        if ( ! empty( $items_params ) ) {
            $items_sql = $wpdb->prepare( $items_sql, ...$items_params );
        }

        $items = $wpdb->get_results( $items_sql );

        if ( $limit ) {
            $count_sql = 'SELECT COUNT(*)' . $from . $where;
            if ( ! empty( $params ) ) {
                $count_sql = $wpdb->prepare( $count_sql, ...$params );
            }
            $total = (int) $wpdb->get_var( $count_sql );
        } else {
            $total = count( $items );
        }

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $limit ? $page : 1,
            'per_page' => $limit ? $per_page : ( $total ? $total : 0 ),
        ];
    }

    public static function get_contract( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_contracts WHERE id = %d', $id ) );
    }

    public static function get_contract_clients( int $contract_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_contract_clients';
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT client_id FROM {$table} WHERE contract_id = %d", $contract_id ), ARRAY_A );
        if ( empty( $rows ) ) {
            return [];
        }
        return array_map( 'intval', wp_list_pluck( $rows, 'client_id' ) );
    }

    public static function get_property_for_contract( int $contract_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_properties WHERE contract_id = %d', $contract_id ) );
    }

    public static function get_search_for_contract( int $contract_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'eo_searches WHERE contract_id = %d', $contract_id ) );
    }
}
