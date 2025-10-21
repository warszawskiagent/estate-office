<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Meta\Keys;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Multi-step wizard for creating contracts.
 */
class ContractWizardPage extends AbstractPage {
    private const STEP_CONTRACT = 'contract';
    private const STEP_CLIENTS  = 'clients';
    private const STEP_PROPERTY = 'property';
    private const STEP_SEARCH   = 'search';

    /** @var array<string, string> */
    private array $stage_options = [
        'umowa_posrednictwa' => 'Umowa pośrednictwa',
        'publikacja_mls'     => 'Publikacja w MLS',
        'przygotowanie'      => 'Przygotowanie oferty',
        'publikacja'         => 'Publikacja oferty',
        'marketing'          => 'Marketing i prezentacje',
        'oferta_kupna'       => 'Oferta kupna',
        'negocjacje'         => 'Negocjacje',
        'umowa_przedwstepna' => 'Umowa przedwstępna',
        'umowa_przyrzeczona' => 'Umowa przyrzeczona',
        'przekazanie'        => 'Przekazanie lokalu',
        'umowa_zakonczona'   => 'Umowa zakończona',
    ];

    public function render(): void {
        $step        = $this->get_step();
        $contract_id = $this->get_contract_id();

        switch ( $step ) {
            case self::STEP_CLIENTS:
                $this->render_clients_step( $contract_id );
                break;
            case self::STEP_PROPERTY:
                $this->render_property_step( $contract_id );
                break;
            case self::STEP_SEARCH:
                $this->render_search_step( $contract_id );
                break;
            case self::STEP_CONTRACT:
            default:
                $this->render_contract_step();
        }
    }
    private function render_contract_step(): void {
        $this->handle_contract_submission();

        $this->render_header(
            __( 'Nowa umowa', 'estate-office' ),
            __( 'Wprowadź podstawowe parametry umowy przed przypisaniem klientów.', 'estate-office' )
        );

        settings_errors( 'estate-office-contract-wizard' );

        $commission_units = [
            'percent' => '%',
            'pln'     => 'PLN',
            'eur'     => 'EUR',
            'usd'     => 'USD',
        ];

        ?>
        <form method="post" class="estate-office-card estate-office-wizard">
            <?php wp_nonce_field( 'estate_office_contract_step', 'estate_office_contract_step_nonce' ); ?>
            <input type="hidden" name="estate_office_wizard_step" value="contract" />
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="contract_number"><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></label>
                    <input type="text" id="contract_number" name="contract_number" required />
                </p>
                <p>
                    <label class="estate-office-label" for="contract_type"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                    <select id="contract_type" name="contract_type" required>
                        <option value="sprzedaz"><?php esc_html_e( 'SPRZEDAŻ', 'estate-office' ); ?></option>
                        <option value="kupno"><?php esc_html_e( 'KUPNO', 'estate-office' ); ?></option>
                        <option value="wynajem"><?php esc_html_e( 'WYNAJEM', 'estate-office' ); ?></option>
                        <option value="najem"><?php esc_html_e( 'NAJEM', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <label class="estate-office-label" for="contract_start"><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></label>
                    <input type="date" id="contract_start" name="contract_start" required />
                </p>
                <p>
                    <label class="estate-office-label" for="contract_end"><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></label>
                    <input type="date" id="contract_end" name="contract_end" data-contract-end />
                </p>
            </div>
            <p>
                <label>
                    <input type="checkbox" name="contract_indefinite" value="1" data-contract-indefinite />
                    <?php esc_html_e( 'Umowa bezterminowa', 'estate-office' ); ?>
                </label>
            </p>
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="commission_amount"><?php esc_html_e( 'Wysokość prowizji', 'estate-office' ); ?></label>
                    <input type="number" id="commission_amount" name="commission_amount" step="0.01" min="0" />
                </p>
                <p>
                    <label class="estate-office-label" for="commission_unit"><?php esc_html_e( 'Jednostka', 'estate-office' ); ?></label>
                    <select id="commission_unit" name="commission_unit">
                        <?php foreach ( $commission_units as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Dalej', 'estate-office' ); ?></button>
            </p>
        </form>
        <?php

        $this->render_footer();
    }

    private function handle_contract_submission(): void {
        if ( empty( $_POST['estate_office_wizard_step'] ) || 'contract' !== $_POST['estate_office_wizard_step'] ) {
            return;
        }

        check_admin_referer( 'estate_office_contract_step', 'estate_office_contract_step_nonce' );

        $number     = sanitize_text_field( wp_unslash( $_POST['contract_number'] ?? '' ) );
        $type       = sanitize_key( wp_unslash( $_POST['contract_type'] ?? '' ) );
        $start      = sanitize_text_field( wp_unslash( $_POST['contract_start'] ?? '' ) );
        $end        = sanitize_text_field( wp_unslash( $_POST['contract_end'] ?? '' ) );
        $indefinite = ! empty( $_POST['contract_indefinite'] );
        $commission = isset( $_POST['commission_amount'] ) ? (float) wp_unslash( $_POST['commission_amount'] ) : 0.0;
        $unit       = sanitize_key( wp_unslash( $_POST['commission_unit'] ?? 'percent' ) );

        if ( ! $number ) {
            add_settings_error( 'estate-office-contract-wizard', 'missing-number', __( 'Numer umowy jest wymagany.', 'estate-office' ) );
            return;
        }

        if ( $this->contract_exists( $number ) ) {
            add_settings_error( 'estate-office-contract-wizard', 'duplicate-number', __( 'Umowa o podanym numerze już istnieje.', 'estate-office' ) );
            return;
        }

        $contract_id = wp_insert_post(
            [
                'post_type'   => 'estate_contract',
                'post_title'  => $number,
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            ]
        );

        if ( $contract_id instanceof WP_Error ) {
            add_settings_error( 'estate-office-contract-wizard', 'contract-create', $contract_id->get_error_message() );
            return;
        }

        update_post_meta( $contract_id, Keys::CONTRACT_NUMBER, $number );
        update_post_meta( $contract_id, Keys::CONTRACT_TYPE, $type );
        update_post_meta( $contract_id, Keys::CONTRACT_START_DATE, $start );
        update_post_meta( $contract_id, Keys::CONTRACT_END_DATE, $indefinite ? '' : $end );
        update_post_meta( $contract_id, Keys::CONTRACT_INDEFINITE, $indefinite ? 1 : 0 );
        update_post_meta( $contract_id, Keys::CONTRACT_COMMISSION, $commission );
        update_post_meta( $contract_id, Keys::CONTRACT_COMMISSION_UNIT, $unit );

        $stage = [
            'name' => __( 'Umowa pośrednictwa', 'estate-office' ),
            'date' => $start ?: wp_date( 'Y-m-d' ),
        ];

        update_post_meta( $contract_id, Keys::CONTRACT_STAGE, $stage );
        update_post_meta( $contract_id, Keys::CONTRACT_STAGE_HISTORY, [ $stage ] );

        add_settings_error( 'estate-office-contract-wizard', 'contract-created', __( 'Umowa zapisana. Dodaj klientów powiązanych.', 'estate-office' ), 'updated' );

        $redirect = add_query_arg(
            [
                'page'        => 'estate-office-contract-wizard',
                'step'        => self::STEP_CLIENTS,
                'contract_id' => $contract_id,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }
    private function render_clients_step( int $contract_id ): void {
        $contract = get_post( $contract_id );
        if ( ! $contract || 'estate_contract' !== $contract->post_type ) {
            wp_die( esc_html__( 'Nie znaleziono wskazanej umowy.', 'estate-office' ) );
        }

        $this->handle_clients_submission( $contract );

        $this->render_header(
            __( 'Dodawanie klienta', 'estate-office' ),
            __( 'Wyszukaj istniejących klientów lub wprowadź nowych.', 'estate-office' )
        );

        settings_errors( 'estate-office-contract-wizard' );

        $clients      = $this->get_contract_clients( $contract_id );
        $search_term  = isset( $_GET['client_search'] ) ? sanitize_text_field( wp_unslash( $_GET['client_search'] ) ) : '';
        $search_query = $this->search_clients( $search_term );

        $contract_meta = [
            'number' => get_post_meta( $contract_id, Keys::CONTRACT_NUMBER, true ),
            'type'   => get_post_meta( $contract_id, Keys::CONTRACT_TYPE, true ),
        ];

        ?>
        <div class="estate-office-card">
            <h2><?php echo esc_html( sprintf( __( 'Umowa #%s', 'estate-office' ), $contract_meta['number'] ) ); ?></h2>
            <p><?php echo esc_html( sprintf( __( 'Typ transakcji: %s', 'estate-office' ), $this->human_contract_type( $contract_meta['type'] ) ) ); ?></p>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Przypisani klienci', 'estate-office' ); ?></h2>
            <?php if ( empty( $clients ) ) : ?>
                <p><?php esc_html_e( 'Nie przypisano jeszcze żadnych klientów.', 'estate-office' ); ?></p>
            <?php else : ?>
                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Klient', 'estate-office' ); ?></th>
                            <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                            <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $clients as $client ) : ?>
                            <tr>
                                <td><?php echo esc_html( $this->format_client_name( $client ) ); ?></td>
                                <td><?php echo esc_html( get_post_meta( $client->ID, Keys::CLIENT_EMAIL, true ) ); ?></td>
                                <td><?php echo esc_html( get_post_meta( $client->ID, Keys::CLIENT_PHONE, true ) ); ?></td>
                                <td>
                                    <form method="post">
                                        <?php wp_nonce_field( 'estate_office_client_detach', 'estate_office_client_detach_nonce' ); ?>
                                        <input type="hidden" name="estate_office_client_action" value="detach" />
                                        <input type="hidden" name="client_id" value="<?php echo esc_attr( $client->ID ); ?>" />
                                        <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>" />
                                        <button type="submit" class="button-link-delete" <?php disabled( ! current_user_can( 'delete_posts' ) ); ?>><?php esc_html_e( 'Usuń powiązanie', 'estate-office' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Wyszukaj klienta', 'estate-office' ); ?></h2>
            <form method="get" class="estate-office-crm__search estate-office-crm__search--inline">
                <input type="hidden" name="page" value="estate-office-contract-wizard" />
                <input type="hidden" name="step" value="clients" />
                <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>" />
                <label class="screen-reader-text" for="estate-office-client-search"><?php esc_html_e( 'Szukaj klientów', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-client-search" name="client_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Imię, nazwisko, telefon lub e-mail…', 'estate-office' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>

            <?php if ( $search_term && ! empty( $search_query->posts ) ) : ?>
                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Klient', 'estate-office' ); ?></th>
                            <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                            <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $search_query->posts as $result ) : ?>
                            <tr>
                                <td><?php echo esc_html( $this->format_client_name( $result ) ); ?></td>
                                <td><?php echo esc_html( get_post_meta( $result->ID, Keys::CLIENT_EMAIL, true ) ); ?></td>
                                <td><?php echo esc_html( get_post_meta( $result->ID, Keys::CLIENT_PHONE, true ) ); ?></td>
                                <td>
                                    <form method="post">
                                        <?php wp_nonce_field( 'estate_office_client_attach', 'estate_office_client_attach_nonce' ); ?>
                                        <input type="hidden" name="estate_office_client_action" value="attach" />
                                        <input type="hidden" name="client_id" value="<?php echo esc_attr( $result->ID ); ?>" />
                                        <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>" />
                                        <button type="submit" class="button button-secondary"><?php esc_html_e( 'Przypisz', 'estate-office' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ( $search_term ) : ?>
                <p><?php esc_html_e( 'Brak wyników dla wprowadzonych danych.', 'estate-office' ); ?></p>
            <?php endif; ?>
        </div>

        <?php $this->render_new_client_form( $contract_id ); ?>

        <div class="estate-office-card">
            <?php $next_step = $this->determine_next_step( $contract_id ); ?>
            <?php if ( ! empty( $clients ) ) : ?>
                <p><?php esc_html_e( 'Czy chcesz dodać kolejnego klienta?', 'estate-office' ); ?></p>
                <p class="submit">
                    <a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-contract-wizard', 'step' => 'clients', 'contract_id' => $contract_id ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'TAK', 'estate-office' ); ?></a>
                    <a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-contract-wizard', 'step' => $next_step, 'contract_id' => $contract_id ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'NIE, przejdź dalej', 'estate-office' ); ?></a>
                </p>
            <?php else : ?>
                <p><?php esc_html_e( 'Dodaj co najmniej jednego klienta, aby przejść do kolejnego etapu.', 'estate-office' ); ?></p>
            <?php endif; ?>
        </div>
        <?php

        $this->render_footer();
    }
    private function render_new_client_form( int $contract_id ): void {
        ?>
        <form method="post" class="estate-office-card estate-office-wizard" data-client-form>
            <h2><?php esc_html_e( 'Dodaj nowego klienta', 'estate-office' ); ?></h2>
            <?php wp_nonce_field( 'estate_office_client_create', 'estate_office_client_create_nonce' ); ?>
            <input type="hidden" name="estate_office_client_action" value="create" />
            <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>" />
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="client_type"><?php esc_html_e( 'Typ klienta', 'estate-office' ); ?></label>
                    <select id="client_type" name="client[type]" data-client-type>
                        <option value="osoba"><?php esc_html_e( 'Osoba fizyczna', 'estate-office' ); ?></option>
                        <option value="firma"><?php esc_html_e( 'Firma', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p data-client-individual>
                    <label class="estate-office-label" for="client_first_name"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                    <input type="text" id="client_first_name" name="client[first_name]" />
                </p>
                <p data-client-individual>
                    <label class="estate-office-label" for="client_last_name"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                    <input type="text" id="client_last_name" name="client[last_name]" />
                </p>
                <p data-client-company class="hidden">
                    <label class="estate-office-label" for="client_company_name"><?php esc_html_e( 'Nazwa firmy', 'estate-office' ); ?></label>
                    <input type="text" id="client_company_name" name="client[company_name]" />
                </p>
                <p data-client-company class="hidden">
                    <label class="estate-office-label" for="client_company_rep"><?php esc_html_e( 'Imię i nazwisko reprezentanta', 'estate-office' ); ?></label>
                    <input type="text" id="client_company_rep" name="client[company_rep]" />
                </p>
            </div>
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="client_phone"><?php esc_html_e( 'Numer telefonu', 'estate-office' ); ?></label>
                    <input type="text" id="client_phone" name="client[phone]" />
                </p>
                <p>
                    <label class="estate-office-label" for="client_email"><?php esc_html_e( 'Adres e-mail', 'estate-office' ); ?></label>
                    <input type="email" id="client_email" name="client[email]" />
                </p>
                <p data-client-company class="hidden">
                    <label class="estate-office-label" for="client_website"><?php esc_html_e( 'Strona WWW', 'estate-office' ); ?></label>
                    <input type="url" id="client_website" name="client[website]" />
                </p>
            </div>
            <div class="estate-office-grid" data-client-individual>
                <p>
                    <label class="estate-office-label" for="client_pesel"><?php esc_html_e( 'PESEL', 'estate-office' ); ?></label>
                    <input type="text" id="client_pesel" name="client[pesel]" />
                </p>
                <p>
                    <label class="estate-office-label" for="client_document_type"><?php esc_html_e( 'Rodzaj dokumentu', 'estate-office' ); ?></label>
                    <select id="client_document_type" name="client[document_type]">
                        <option value="dowod"><?php esc_html_e( 'Dowód osobisty', 'estate-office' ); ?></option>
                        <option value="paszport"><?php esc_html_e( 'Paszport', 'estate-office' ); ?></option>
                        <option value="karta_pobytu"><?php esc_html_e( 'Karta pobytu', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <label class="estate-office-label" for="client_document_number"><?php esc_html_e( 'Numer dokumentu', 'estate-office' ); ?></label>
                    <input type="text" id="client_document_number" name="client[document_number]" />
                </p>
            </div>
            <div class="estate-office-grid hidden" data-client-company>
                <p>
                    <label class="estate-office-label" for="client_nip"><?php esc_html_e( 'NIP', 'estate-office' ); ?></label>
                    <input type="text" id="client_nip" name="client[nip]" />
                </p>
                <p>
                    <label class="estate-office-label" for="client_krs"><?php esc_html_e( 'KRS', 'estate-office' ); ?></label>
                    <input type="text" id="client_krs" name="client[krs]" />
                </p>
                <p>
                    <label class="estate-office-label" for="client_regon"><?php esc_html_e( 'REGON', 'estate-office' ); ?></label>
                    <input type="text" id="client_regon" name="client[regon]" />
                </p>
            </div>

            <?php $this->render_address_fields( 'client[address_main]', __( 'Adres zamieszkania/rejestrowy', 'estate-office' ) ); ?>

            <p>
                <label>
                    <input type="checkbox" name="client[address_same]" value="1" checked data-client-mailing-toggle />
                    <?php esc_html_e( 'Adres korespondencyjny taki sam', 'estate-office' ); ?>
                </label>
            </p>

            <div data-client-mailing class="hidden">
                <?php $this->render_address_fields( 'client[address_mail]', __( 'Adres korespondencyjny', 'estate-office' ) ); ?>
            </div>

            <p class="submit">
                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Dodaj klienta', 'estate-office' ); ?></button>
            </p>
        </form>
        <?php
    }
    private function handle_clients_submission( WP_Post $contract ): void {
        if ( empty( $_POST['estate_office_client_action'] ) ) {
            return;
        }

        $action      = sanitize_key( wp_unslash( $_POST['estate_office_client_action'] ) );
        $contract_id = (int) ( $_POST['contract_id'] ?? $contract->ID );

        if ( $contract_id !== $contract->ID ) {
            return;
        }

        switch ( $action ) {
            case 'attach':
                $this->attach_existing_client( $contract->ID );
                break;
            case 'detach':
                $this->detach_client( $contract->ID );
                break;
            case 'create':
                $this->create_client( $contract->ID );
                break;
        }

        $redirect = add_query_arg(
            [
                'page'        => 'estate-office-contract-wizard',
                'step'        => self::STEP_CLIENTS,
                'contract_id' => $contract->ID,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    private function attach_existing_client( int $contract_id ): void {
        check_admin_referer( 'estate_office_client_attach', 'estate_office_client_attach_nonce' );

        $client_id = isset( $_POST['client_id'] ) ? (int) $_POST['client_id'] : 0;
        $client    = get_post( $client_id );

        if ( ! $client || 'estate_client' !== $client->post_type ) {
            add_settings_error( 'estate-office-contract-wizard', 'client-not-found', __( 'Nie znaleziono klienta.', 'estate-office' ) );
            return;
        }

        $clients = $this->get_contract_client_ids( $contract_id );

        if ( in_array( $client_id, $clients, true ) ) {
            add_settings_error( 'estate-office-contract-wizard', 'client-duplicate', __( 'Klient jest już przypisany do tej umowy.', 'estate-office' ) );
            return;
        }

        $clients[] = $client_id;
        update_post_meta( $contract_id, Keys::CONTRACT_CLIENTS, $clients );

        $client_contracts   = get_post_meta( $client_id, Keys::CLIENT_CONTRACTS, true );
        $client_contracts   = is_array( $client_contracts ) ? $client_contracts : [];
        $client_contracts[] = $contract_id;
        update_post_meta( $client_id, Keys::CLIENT_CONTRACTS, array_values( array_unique( $client_contracts ) ) );

        add_settings_error( 'estate-office-contract-wizard', 'client-attached', __( 'Klient został przypisany do umowy.', 'estate-office' ), 'updated' );
    }

    private function detach_client( int $contract_id ): void {
        check_admin_referer( 'estate_office_client_detach', 'estate_office_client_detach_nonce' );

        $client_id = isset( $_POST['client_id'] ) ? (int) $_POST['client_id'] : 0;
        $clients   = $this->get_contract_client_ids( $contract_id );

        $clients = array_filter(
            $clients,
            static function ( int $id ) use ( $client_id ) {
                return $id !== $client_id;
            }
        );

        update_post_meta( $contract_id, Keys::CONTRACT_CLIENTS, $clients );

        $client_contracts = get_post_meta( $client_id, Keys::CLIENT_CONTRACTS, true );
        if ( is_array( $client_contracts ) ) {
            $client_contracts = array_filter(
                $client_contracts,
                static function ( int $id ) use ( $contract_id ) {
                    return $id !== $contract_id;
                }
            );
            update_post_meta( $client_id, Keys::CLIENT_CONTRACTS, $client_contracts );
        }

        add_settings_error( 'estate-office-contract-wizard', 'client-detached', __( 'Powiązanie z klientem zostało usunięte.', 'estate-office' ), 'updated' );
    }

    private function create_client( int $contract_id ): void {
        check_admin_referer( 'estate_office_client_create', 'estate_office_client_create_nonce' );

        $data = isset( $_POST['client'] ) ? (array) wp_unslash( $_POST['client'] ) : [];

        $type       = sanitize_key( $data['type'] ?? 'osoba' );
        $first_name = sanitize_text_field( $data['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $data['last_name'] ?? '' );
        $company    = sanitize_text_field( $data['company_name'] ?? '' );

        if ( 'osoba' === $type && ( ! $first_name || ! $last_name ) ) {
            add_settings_error( 'estate-office-contract-wizard', 'client-missing', __( 'Imię i nazwisko są wymagane.', 'estate-office' ) );
            return;
        }

        if ( 'firma' === $type && ! $company ) {
            add_settings_error( 'estate-office-contract-wizard', 'client-company-missing', __( 'Nazwa firmy jest wymagana.', 'estate-office' ) );
            return;
        }

        $post_title = 'osoba' === $type ? trim( $first_name . ' ' . $last_name ) : $company;

        $client_id = wp_insert_post(
            [
                'post_type'   => 'estate_client',
                'post_status' => 'publish',
                'post_title'  => $post_title,
                'post_author' => get_current_user_id(),
            ]
        );

        if ( $client_id instanceof WP_Error ) {
            add_settings_error( 'estate-office-contract-wizard', 'client-create', $client_id->get_error_message() );
            return;
        }

        update_post_meta( $client_id, Keys::CLIENT_TYPE, $type );
        update_post_meta( $client_id, Keys::CLIENT_FIRST_NAME, $first_name );
        update_post_meta( $client_id, Keys::CLIENT_LAST_NAME, $last_name );
        update_post_meta( $client_id, Keys::CLIENT_COMPANY_NAME, $company );
        update_post_meta( $client_id, Keys::CLIENT_COMPANY_REP, sanitize_text_field( $data['company_rep'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_PHONE, sanitize_text_field( $data['phone'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_EMAIL, sanitize_email( $data['email'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_WEBSITE, esc_url_raw( $data['website'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_PESEL, sanitize_text_field( $data['pesel'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_DOCUMENT_TYPE, sanitize_key( $data['document_type'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_DOCUMENT_NUMBER, sanitize_text_field( $data['document_number'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_NIP, sanitize_text_field( $data['nip'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_KRS, sanitize_text_field( $data['krs'] ?? '' ) );
        update_post_meta( $client_id, Keys::CLIENT_REGON, sanitize_text_field( $data['regon'] ?? '' ) );

        $address_main = $this->sanitize_address( $data['address_main'] ?? [] );
        $address_mail = ! empty( $data['address_same'] ) ? $address_main : $this->sanitize_address( $data['address_mail'] ?? [] );

        update_post_meta( $client_id, Keys::CLIENT_ADDRESS_MAIN, $address_main );
        update_post_meta( $client_id, Keys::CLIENT_ADDRESS_MAIL, $address_mail );
        update_post_meta( $client_id, Keys::CLIENT_CONTRACTS, [ $contract_id ] );

        $clients   = $this->get_contract_client_ids( $contract_id );
        $clients[] = $client_id;
        update_post_meta( $contract_id, Keys::CONTRACT_CLIENTS, array_values( array_unique( $clients ) ) );

        add_settings_error( 'estate-office-contract-wizard', 'client-created', __( 'Klient został dodany i przypisany do umowy.', 'estate-office' ), 'updated' );
    }
    private function render_property_step( int $contract_id ): void {
        $contract = get_post( $contract_id );
        if ( ! $contract ) {
            wp_die( esc_html__( 'Nie znaleziono wskazanej umowy.', 'estate-office' ) );
        }

        $type = get_post_meta( $contract_id, Keys::CONTRACT_TYPE, true );
        if ( ! in_array( $type, [ 'sprzedaz', 'wynajem' ], true ) ) {
            wp_die( esc_html__( 'Ten etap dotyczy wyłącznie umów sprzedaży lub wynajmu.', 'estate-office' ) );
        }

        $this->handle_property_submission( $contract_id, $type );

        $this->render_header(
            __( 'Dodaj nieruchomość', 'estate-office' ),
            __( 'Uzupełnij dane nieruchomości powiązanej z umową.', 'estate-office' )
        );

        settings_errors( 'estate-office-contract-wizard' );

        $transaction_label = $this->human_contract_type( $type );
        ?>
        <div class="estate-office-card">
            <p><strong><?php esc_html_e( 'Typ transakcji:', 'estate-office' ); ?></strong> <?php echo esc_html( $transaction_label ); ?></p>
        </div>
        <form method="post" class="estate-office-card estate-office-wizard" data-property-form>
            <?php wp_nonce_field( 'estate_office_property_create', 'estate_office_property_create_nonce' ); ?>
            <input type="hidden" name="estate_office_property_action" value="create" />
            <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>" />
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="property_reference"><?php esc_html_e( 'Numer oferty', 'estate-office' ); ?></label>
                    <input type="text" id="property_reference" name="property[reference]" required />
                </p>
                <p>
                    <label class="estate-office-label" for="property_kind"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                    <select id="property_kind" name="property[kind]" data-property-kind>
                        <option value="mieszkanie"><?php esc_html_e( 'MIESZKANIE', 'estate-office' ); ?></option>
                        <option value="dom"><?php esc_html_e( 'DOM', 'estate-office' ); ?></option>
                        <option value="dzialka"><?php esc_html_e( 'DZIAŁKA', 'estate-office' ); ?></option>
                        <option value="lokal"><?php esc_html_e( 'LOKAL H/U', 'estate-office' ); ?></option>
                    </select>
                </p>
            </div>

            <h3><?php esc_html_e( 'Dane adresowe', 'estate-office' ); ?></h3>
            <div data-property-address>
                <?php $this->render_address_fields( 'property[address]', __( 'Adres nieruchomości', 'estate-office' ), true ); ?>
            </div>
            <p>
                <label>
                    <input type="checkbox" name="property[no_kw]" value="1" data-property-no-kw />
                    <?php esc_html_e( 'Brak numeru księgi wieczystej', 'estate-office' ); ?>
                </label>
            </p>
            <p>
                <label class="estate-office-label" for="property_ekw"><?php esc_html_e( 'Numer Księgi Wieczystej', 'estate-office' ); ?></label>
                <input type="text" id="property_ekw" name="property[ekw]" data-property-kw />
            </p>
            <p>
                <label class="estate-office-label" for="property_legal"><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></label>
                <select id="property_legal" name="property[legal_status]">
                    <option value="wlasnosc"><?php esc_html_e( 'Własność', 'estate-office' ); ?></option>
                    <option value="wspolwlasnosc"><?php esc_html_e( 'Współwłasność', 'estate-office' ); ?></option>
                    <option value="spoldzielcze"><?php esc_html_e( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ); ?></option>
                    <option value="dzierzawa"><?php esc_html_e( 'Dzierżawa', 'estate-office' ); ?></option>
                    <option value="inne"><?php esc_html_e( 'Inne', 'estate-office' ); ?></option>
                </select>
            </p>

            <h3><?php esc_html_e( 'Dane nieruchomości', 'estate-office' ); ?></h3>
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="property_price"><?php esc_html_e( 'Cena', 'estate-office' ); ?></label>
                    <input type="number" id="property_price" name="property[price]" min="0" step="0.01" data-property-price />
                </p>
                <p>
                    <label class="estate-office-label" for="property_admin_fee"><?php esc_html_e( 'Czynsz administracyjny', 'estate-office' ); ?></label>
                    <input type="number" id="property_admin_fee" name="property[admin_fee]" min="0" step="0.01" />
                </p>
                <p>
                    <label class="estate-office-label" for="property_area"><?php esc_html_e( 'Powierzchnia (m²)', 'estate-office' ); ?></label>
                    <input type="number" id="property_area" name="property[area]" min="0" step="0.01" data-property-area />
                </p>
                <p>
                    <label class="estate-office-label" for="property_price_m2"><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></label>
                    <input type="number" id="property_price_m2" name="property[price_m2]" min="0" step="0.01" data-property-price-m2 readonly />
                </p>
            </div>
            <div class="estate-office-grid" data-property-building>
                <p data-property-not="dzialka">
                    <label class="estate-office-label" for="property_year"><?php esc_html_e( 'Rok budowy', 'estate-office' ); ?></label>
                    <input type="number" id="property_year" name="property[year_built]" min="1800" max="<?php echo esc_attr( date( 'Y' ) ); ?>" />
                </p>
                <p data-property-not="dzialka|dom">
                    <label class="estate-office-label" for="property_floor"><?php esc_html_e( 'Piętro', 'estate-office' ); ?></label>
                    <input type="number" id="property_floor" name="property[floor]" min="0" max="99" />
                </p>
                <p data-property-not="dzialka">
                    <label class="estate-office-label" for="property_floors"><?php esc_html_e( 'Liczba pięter', 'estate-office' ); ?></label>
                    <input type="number" id="property_floors" name="property[floors]" min="0" max="99" />
                </p>
                <p data-property-not="dzialka">
                    <label class="estate-office-label" for="property_rooms"><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></label>
                    <input type="number" id="property_rooms" name="property[rooms]" min="0" max="20" />
                </p>
                <p data-property-not="dzialka">
                    <label class="estate-office-label" for="property_bedrooms"><?php esc_html_e( 'Liczba sypialni', 'estate-office' ); ?></label>
                    <input type="number" id="property_bedrooms" name="property[bedrooms]" min="0" max="20" />
                </p>
                <p data-property-not="dzialka">
                    <label class="estate-office-label" for="property_bathrooms"><?php esc_html_e( 'Liczba łazienek', 'estate-office' ); ?></label>
                    <input type="number" id="property_bathrooms" name="property[bathrooms]" min="0" max="10" />
                </p>
                <p data-property-not="dzialka">
                    <label class="estate-office-label" for="property_toilets"><?php esc_html_e( 'Liczba toalet', 'estate-office' ); ?></label>
                    <input type="number" id="property_toilets" name="property[toilets]" min="0" max="10" />
                </p>
            </div>

            <div data-property-only="dzialka" class="hidden">
                <p>
                    <label class="estate-office-label" for="property_plot_shape"><?php esc_html_e( 'Kształt działki', 'estate-office' ); ?></label>
                    <select id="property_plot_shape" name="property[plot_shape]" data-property-plot-shape>
                        <option value="regularny"><?php esc_html_e( 'Regularny', 'estate-office' ); ?></option>
                        <option value="nieregularny"><?php esc_html_e( 'Nieregularny', 'estate-office' ); ?></option>
                    </select>
                </p>
                <div class="estate-office-grid" data-property-plot-regular>
                    <p>
                        <label class="estate-office-label" for="property_plot_length"><?php esc_html_e( 'Długość (m)', 'estate-office' ); ?></label>
                        <input type="number" id="property_plot_length" name="property[plot_length]" min="0" step="0.01" />
                    </p>
                    <p>
                        <label class="estate-office-label" for="property_plot_width"><?php esc_html_e( 'Szerokość (m)', 'estate-office' ); ?></label>
                        <input type="number" id="property_plot_width" name="property[plot_width]" min="0" step="0.01" />
                    </p>
                </div>
                <p class="hidden" data-property-plot-irregular>
                    <label class="estate-office-label" for="property_plot_desc"><?php esc_html_e( 'Opis kształtu', 'estate-office' ); ?></label>
                    <textarea id="property_plot_desc" name="property[plot_description]"></textarea>
                </p>
            </div>

            <h3><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></h3>
            <?php
            wp_editor( '', 'estate_office_property_description', [
                'textarea_name' => 'property[description]',
                'media_buttons' => false,
                'textarea_rows' => 8,
            ] );
            ?>

            <h3><?php esc_html_e( 'Szczegóły budynku', 'estate-office' ); ?></h3>
            <div class="estate-office-grid" data-property-not="dzialka">
                <p>
                    <label class="estate-office-label" for="property_finish"><?php esc_html_e( 'Stan wykończenia', 'estate-office' ); ?></label>
                    <select id="property_finish" name="property[building][finish]">
                        <option value="do_wykonczenia"><?php esc_html_e( 'Do wykończenia', 'estate-office' ); ?></option>
                        <option value="do_zamieszkania"><?php esc_html_e( 'Do zamieszkania', 'estate-office' ); ?></option>
                        <option value="pod_klucz"><?php esc_html_e( 'Pod klucz', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <span class="estate-office-label"><?php esc_html_e( 'Ekspozycja', 'estate-office' ); ?></span>
                    <label><input type="checkbox" name="property[building][exposure][]" value="polnoc" /> <?php esc_html_e( 'Północ', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][exposure][]" value="poludnie" /> <?php esc_html_e( 'Południe', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][exposure][]" value="wschod" /> <?php esc_html_e( 'Wschód', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][exposure][]" value="zachod" /> <?php esc_html_e( 'Zachód', 'estate-office' ); ?></label>
                </p>
                <p>
                    <span class="estate-office-label"><?php esc_html_e( 'Widok', 'estate-office' ); ?></span>
                    <label><input type="checkbox" name="property[building][view][]" value="park" /> <?php esc_html_e( 'Park', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][view][]" value="miasto" /> <?php esc_html_e( 'Miasto', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][view][]" value="inne" /> <?php esc_html_e( 'Inne', 'estate-office' ); ?></label>
                </p>
            </div>
            <div data-property-not="dzialka">
                <p>
                    <label>
                        <input type="checkbox" name="property[building][attic]" value="1" />
                        <?php esc_html_e( 'Poddasze', 'estate-office' ); ?>
                    </label>
                </p>
                <p data-property-not="dom">
                    <label>
                        <input type="checkbox" name="property[building][multi_level]" value="1" />
                        <?php esc_html_e( 'Wielopoziomowe', 'estate-office' ); ?>
                    </label>
                </p>
                <p>
                    <span class="estate-office-label"><?php esc_html_e( 'Rozkład', 'estate-office' ); ?></span>
                    <label><input type="checkbox" name="property[building][layout][]" value="dwustronne" /> <?php esc_html_e( 'Dwustronne', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][layout][]" value="rozkladowe" /> <?php esc_html_e( 'Rozkładowe', 'estate-office' ); ?></label>
                </p>
                <p>
                    <label class="estate-office-label" for="property_kitchen"><?php esc_html_e( 'Kuchnia', 'estate-office' ); ?></label>
                    <select id="property_kitchen" name="property[building][kitchen]">
                        <option value="aneks"><?php esc_html_e( 'Aneks', 'estate-office' ); ?></option>
                        <option value="oddzielna"><?php esc_html_e( 'Oddzielna', 'estate-office' ); ?></option>
                        <option value="z_salonem"><?php esc_html_e( 'Z salonem', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="property[building][parking][has]" value="1" data-property-parking />
                        <?php esc_html_e( 'Miejsce parkingowe', 'estate-office' ); ?>
                    </label>
                </p>
                <div class="hidden" data-property-parking-options>
                    <label><input type="checkbox" name="property[building][parking][types][]" value="naziemne" /> <?php esc_html_e( 'Najemne', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][parking][types][]" value="podziemne" /> <?php esc_html_e( 'Podziemne', 'estate-office' ); ?></label><br />
                    <label><input type="checkbox" name="property[building][parking][types][]" value="garaz" /> <?php esc_html_e( 'Garaż wolnostojący/przylegający', 'estate-office' ); ?></label>
                </div>
            </div>

            <h3><?php esc_html_e( 'Media', 'estate-office' ); ?></h3>
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="property_heat"><?php esc_html_e( 'Ogrzewanie', 'estate-office' ); ?></label>
                    <select id="property_heat" name="property[media][heating]">
                        <option value="miejskie"><?php esc_html_e( 'Miejskie', 'estate-office' ); ?></option>
                        <option value="gazowe"><?php esc_html_e( 'Gazowe', 'estate-office' ); ?></option>
                        <option value="inne"><?php esc_html_e( 'Inne', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <label class="estate-office-label" for="property_water"><?php esc_html_e( 'Woda', 'estate-office' ); ?></label>
                    <select id="property_water" name="property[media][water]">
                        <option value="miejskie"><?php esc_html_e( 'Miejskie', 'estate-office' ); ?></option>
                        <option value="studnia"><?php esc_html_e( 'Studnia', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <label class="estate-office-label" for="property_sewage"><?php esc_html_e( 'Kanalizacja', 'estate-office' ); ?></label>
                    <select id="property_sewage" name="property[media][sewage]">
                        <option value="miejskie"><?php esc_html_e( 'Miejskie', 'estate-office' ); ?></option>
                        <option value="szambo"><?php esc_html_e( 'Szambo', 'estate-office' ); ?></option>
                    </select>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="property[media][gas]" value="1" />
                        <?php esc_html_e( 'Gaz', 'estate-office' ); ?>
                    </label>
                </p>
            </div>
            <h3><?php esc_html_e( 'Udogodnienia', 'estate-office' ); ?></h3>
            <div class="estate-office-grid">
                <label><input type="checkbox" name="property[amenities][]" value="winda" /> <?php esc_html_e( 'Winda', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[amenities][]" value="umeblowanie_pelne" /> <?php esc_html_e( 'Umeblowanie', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[amenities][]" value="klimatyzacja" /> <?php esc_html_e( 'Klimatyzacja', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[amenities][]" value="monitoring" /> <?php esc_html_e( 'Monitoring/Ochrona', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[amenities][]" value="recepcja" /> <?php esc_html_e( 'Recepcja', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[amenities][]" value="teren_zamkniety" /> <?php esc_html_e( 'Teren zamknięty', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[amenities][]" value="domofon" /> <?php esc_html_e( 'Domofon', 'estate-office' ); ?></label>
            </div>

            <h3><?php esc_html_e( 'Wyposażenie', 'estate-office' ); ?></h3>
            <div class="estate-office-grid">
                <label><input type="checkbox" name="property[equipment][]" value="pralka" /> <?php esc_html_e( 'Pralka', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[equipment][]" value="zmywarka" /> <?php esc_html_e( 'Zmywarka', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[equipment][]" value="lodowka" /> <?php esc_html_e( 'Lodówka', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[equipment][]" value="kuchenka" /> <?php esc_html_e( 'Kuchenka', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[equipment][]" value="piekarnik" /> <?php esc_html_e( 'Piekarnik', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[equipment][]" value="telewizor" /> <?php esc_html_e( 'Telewizor', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[equipment][]" value="mikrofala" /> <?php esc_html_e( 'Mikrofala', 'estate-office' ); ?></label>
            </div>

            <h3><?php esc_html_e( 'Powierzchnie dodatkowe', 'estate-office' ); ?></h3>
            <div class="estate-office-extra-spaces">
                <?php $this->render_extra_space_field( 'balcony', __( 'Balkon', 'estate-office' ) ); ?>
                <?php $this->render_extra_space_field( 'taras', __( 'Taras', 'estate-office' ) ); ?>
                <?php $this->render_extra_space_field( 'piwnica', __( 'Piwnica', 'estate-office' ) ); ?>
                <?php $this->render_extra_space_field( 'komorka', __( 'Komórka lokatorska', 'estate-office' ) ); ?>
                <?php $this->render_extra_space_field( 'ogrodek', __( 'Ogródek', 'estate-office' ), true ); ?>
            </div>

            <h3><?php esc_html_e( 'Galeria i multimedia', 'estate-office' ); ?></h3>
            <p>
                <label class="estate-office-label" for="property_video"><?php esc_html_e( 'Link do filmu', 'estate-office' ); ?></label>
                <input type="url" id="property_video" name="property[video]" />
            </p>
            <p>
                <label class="estate-office-label" for="property_vr"><?php esc_html_e( 'Link do wirtualnego spaceru', 'estate-office' ); ?></label>
                <input type="url" id="property_vr" name="property[vr]" />
            </p>

            <h3><?php esc_html_e( 'Znaczniki', 'estate-office' ); ?></h3>
            <div class="estate-office-grid">
                <label><input type="checkbox" name="property[badges][]" value="nowa_oferta" /> <?php esc_html_e( 'Nowa oferta', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="wylacznosc" /> <?php esc_html_e( 'Wyłączność', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="sprzedane" /> <?php esc_html_e( 'Sprzedane', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="wynajete" /> <?php esc_html_e( 'Wynajęte', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="nowa_cena" /> <?php esc_html_e( 'Nowa cena', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="bez_prowizji" /> <?php esc_html_e( 'Bez prowizji', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="mls" /> <?php esc_html_e( 'Oferta MLS', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="premium" /> <?php esc_html_e( 'Premium', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="export_www" /> <?php esc_html_e( 'Eksport na WWW', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="property[badges][]" value="export_portals" disabled /> <?php esc_html_e( 'Eksport na Portale (wkrótce)', 'estate-office' ); ?></label>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj nieruchomość', 'estate-office' ); ?></button>
            </p>
        </form>
        <?php

        $this->render_footer();
    }
    private function handle_property_submission( int $contract_id, string $transaction_type ): void {
        if ( empty( $_POST['estate_office_property_action'] ) ) {
            return;
        }

        check_admin_referer( 'estate_office_property_create', 'estate_office_property_create_nonce' );

        $data      = isset( $_POST['property'] ) ? (array) wp_unslash( $_POST['property'] ) : [];
        $reference = sanitize_text_field( $data['reference'] ?? '' );
        $kind      = sanitize_key( $data['kind'] ?? 'mieszkanie' );

        if ( ! $reference ) {
            add_settings_error( 'estate-office-contract-wizard', 'property-reference', __( 'Numer oferty jest wymagany.', 'estate-office' ) );
            return;
        }

        $address  = $this->sanitize_address( $data['address'] ?? [] );
        $price    = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
        $area     = isset( $data['area'] ) ? (float) $data['area'] : 0.0;
        $price_m2 = $area > 0 ? $price / $area : 0.0;

        $post_title = $reference;
        if ( $address['street'] && $address['city'] ) {
            $post_title .= ' – ' . $address['street'] . ', ' . $address['city'];
        }

        $property_id = wp_insert_post(
            [
                'post_type'   => 'estate_property',
                'post_status' => 'publish',
                'post_title'  => $post_title,
                'post_author' => get_current_user_id(),
            ]
        );

        if ( $property_id instanceof WP_Error ) {
            add_settings_error( 'estate-office-contract-wizard', 'property-create', $property_id->get_error_message() );
            return;
        }

        update_post_meta( $property_id, Keys::PROPERTY_REFERENCE, $reference );
        update_post_meta( $property_id, Keys::PROPERTY_TRANSACTION, $transaction_type );
        update_post_meta( $property_id, Keys::PROPERTY_KIND, $kind );
        update_post_meta( $property_id, Keys::PROPERTY_ADDRESS, $address );
        update_post_meta( $property_id, Keys::PROPERTY_LEGAL_STATUS, sanitize_key( $data['legal_status'] ?? '' ) );
        update_post_meta( $property_id, Keys::PROPERTY_PRICE, $price );
        update_post_meta( $property_id, Keys::PROPERTY_ADMIN_FEE, isset( $data['admin_fee'] ) ? (float) $data['admin_fee'] : 0.0 );
        update_post_meta( $property_id, Keys::PROPERTY_AREA, $area );
        update_post_meta( $property_id, Keys::PROPERTY_PRICE_PER_M2, isset( $data['price_m2'] ) ? (float) $data['price_m2'] : $price_m2 );
        update_post_meta( $property_id, Keys::PROPERTY_YEAR_BUILT, isset( $data['year_built'] ) ? (int) $data['year_built'] : 0 );
        update_post_meta( $property_id, Keys::PROPERTY_FLOOR, isset( $data['floor'] ) ? (int) $data['floor'] : 0 );
        update_post_meta( $property_id, Keys::PROPERTY_FLOORS, isset( $data['floors'] ) ? (int) $data['floors'] : 0 );
        update_post_meta( $property_id, Keys::PROPERTY_ROOMS, isset( $data['rooms'] ) ? (int) $data['rooms'] : 0 );
        update_post_meta( $property_id, Keys::PROPERTY_BEDROOMS, isset( $data['bedrooms'] ) ? (int) $data['bedrooms'] : 0 );
        update_post_meta( $property_id, Keys::PROPERTY_BATHROOMS, isset( $data['bathrooms'] ) ? (int) $data['bathrooms'] : 0 );
        update_post_meta( $property_id, Keys::PROPERTY_TOILETS, isset( $data['toilets'] ) ? (int) $data['toilets'] : 0 );

        $plot_shape = sanitize_key( $data['plot_shape'] ?? '' );
        $plot       = [
            'shape'       => $plot_shape,
            'length'      => isset( $data['plot_length'] ) ? (float) $data['plot_length'] : 0.0,
            'width'       => isset( $data['plot_width'] ) ? (float) $data['plot_width'] : 0.0,
            'description' => sanitize_textarea_field( $data['plot_description'] ?? '' ),
        ];
        update_post_meta( $property_id, Keys::PROPERTY_PLOT_SHAPE, $plot );

        $description = wp_kses_post( $data['description'] ?? '' );
        update_post_meta( $property_id, Keys::PROPERTY_DESCRIPTION, $description );
        update_post_meta( $property_id, Keys::PROPERTY_BUILDING, $this->sanitize_nested_array( $data['building'] ?? [] ) );
        update_post_meta( $property_id, Keys::PROPERTY_MEDIA, $this->sanitize_nested_array( $data['media'] ?? [] ) );
        update_post_meta( $property_id, Keys::PROPERTY_AMENITIES, $this->sanitize_array_values( $data['amenities'] ?? [] ) );
        update_post_meta( $property_id, Keys::PROPERTY_EQUIPMENT, $this->sanitize_array_values( $data['equipment'] ?? [] ) );
        update_post_meta( $property_id, Keys::PROPERTY_EXTRA_SPACES, $this->sanitize_nested_array( $data['extra_spaces'] ?? [] ) );
        update_post_meta( $property_id, Keys::PROPERTY_VIDEO, esc_url_raw( $data['video'] ?? '' ) );
        update_post_meta( $property_id, Keys::PROPERTY_VR, esc_url_raw( $data['vr'] ?? '' ) );
        update_post_meta( $property_id, Keys::PROPERTY_BADGES, $this->sanitize_array_values( $data['badges'] ?? [] ) );
        update_post_meta( $property_id, Keys::PROPERTY_CONTRACT, $contract_id );

        update_post_meta( $contract_id, Keys::CONTRACT_PROPERTY, $property_id );

        wp_set_object_terms( $property_id, $transaction_type, 'estate_property_transaction', false );
        wp_set_object_terms( $property_id, $kind, 'estate_property_kind', false );
        if ( $address['city'] ) {
            wp_set_object_terms( $property_id, $address['city'], 'estate_property_city', false );
        }
        if ( $address['district'] ) {
            wp_set_object_terms( $property_id, $address['district'], 'estate_property_district', false );
        }

        add_settings_error( 'estate-office-contract-wizard', 'property-created', __( 'Nieruchomość została dodana.', 'estate-office' ), 'updated' );

        $redirect = get_edit_post_link( $property_id, 'url' );
        wp_safe_redirect( $redirect );
        exit;
    }
    private function render_search_step( int $contract_id ): void {
        $contract = get_post( $contract_id );
        if ( ! $contract ) {
            wp_die( esc_html__( 'Nie znaleziono wskazanej umowy.', 'estate-office' ) );
        }

        $type = get_post_meta( $contract_id, Keys::CONTRACT_TYPE, true );
        if ( ! in_array( $type, [ 'kupno', 'najem' ], true ) ) {
            wp_die( esc_html__( 'Ten etap dotyczy wyłącznie umów kupna lub najmu.', 'estate-office' ) );
        }

        $this->handle_search_submission( $contract_id, $type );

        $this->render_header(
            __( 'Dodaj poszukiwanie', 'estate-office' ),
            __( 'Zdefiniuj kryteria poszukiwania nieruchomości dla klienta.', 'estate-office' )
        );

        settings_errors( 'estate-office-contract-wizard' );

        ?>
        <form method="post" class="estate-office-card estate-office-wizard">
            <?php wp_nonce_field( 'estate_office_search_create', 'estate_office_search_create_nonce' ); ?>
            <input type="hidden" name="estate_office_search_action" value="create" />
            <input type="hidden" name="contract_id" value="<?php echo esc_attr( $contract_id ); ?>" />
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="search_reference"><?php esc_html_e( 'Numer poszukiwania', 'estate-office' ); ?></label>
                    <input type="text" id="search_reference" name="search[reference]" required />
                </p>
                <p>
                    <label class="estate-office-label" for="search_kind"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                    <select id="search_kind" name="search[property_kind]">
                        <option value="mieszkanie"><?php esc_html_e( 'MIESZKANIE', 'estate-office' ); ?></option>
                        <option value="dom"><?php esc_html_e( 'DOM', 'estate-office' ); ?></option>
                        <option value="dzialka"><?php esc_html_e( 'DZIAŁKA', 'estate-office' ); ?></option>
                        <option value="lokal"><?php esc_html_e( 'LOKAL H/U', 'estate-office' ); ?></option>
                    </select>
                </p>
            </div>
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="search_price_min"><?php esc_html_e( 'Budżet od', 'estate-office' ); ?></label>
                    <input type="number" id="search_price_min" name="search[price_min]" min="0" step="0.01" />
                </p>
                <p>
                    <label class="estate-office-label" for="search_price_max"><?php esc_html_e( 'Budżet do', 'estate-office' ); ?></label>
                    <input type="number" id="search_price_max" name="search[price_max]" min="0" step="0.01" />
                </p>
                <p>
                    <label class="estate-office-label" for="search_area_min"><?php esc_html_e( 'Metraż od', 'estate-office' ); ?></label>
                    <input type="number" id="search_area_min" name="search[area_min]" min="0" step="0.01" />
                </p>
                <p>
                    <label class="estate-office-label" for="search_area_max"><?php esc_html_e( 'Metraż do', 'estate-office' ); ?></label>
                    <input type="number" id="search_area_max" name="search[area_max]" min="0" step="0.01" />
                </p>
                <p>
                    <label class="estate-office-label" for="search_rooms_min"><?php esc_html_e( 'Liczba pokoi od', 'estate-office' ); ?></label>
                    <input type="number" id="search_rooms_min" name="search[rooms_min]" min="0" max="20" />
                </p>
                <p>
                    <label class="estate-office-label" for="search_rooms_max"><?php esc_html_e( 'Liczba pokoi do', 'estate-office' ); ?></label>
                    <input type="number" id="search_rooms_max" name="search[rooms_max]" min="0" max="20" />
                </p>
            </div>
            <p>
                <label class="estate-office-label" for="search_location"><?php esc_html_e( 'Preferowana lokalizacja', 'estate-office' ); ?></label>
                <input type="text" id="search_location" name="search[location]" />
            </p>
            <h3><?php esc_html_e( 'Opis poszukiwania', 'estate-office' ); ?></h3>
            <?php
            wp_editor( '', 'estate_office_search_description', [
                'textarea_name' => 'search[description]',
                'media_buttons' => false,
                'textarea_rows' => 6,
            ] );
            ?>

            <h3><?php esc_html_e( 'Dodatkowe kryteria', 'estate-office' ); ?></h3>
            <div class="estate-office-grid">
                <label><input type="checkbox" name="search[building][needs_elevator]" value="1" /> <?php esc_html_e( 'Winda', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="search[amenities][]" value="balkon" /> <?php esc_html_e( 'Balkon', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="search[amenities][]" value="parking" /> <?php esc_html_e( 'Miejsce parkingowe', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="search[amenities][]" value="ogrodek" /> <?php esc_html_e( 'Ogródek', 'estate-office' ); ?></label>
                <label><input type="checkbox" name="search[media][]" value="gaz" /> <?php esc_html_e( 'Gaz', 'estate-office' ); ?></label>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj poszukiwanie', 'estate-office' ); ?></button>
            </p>
        </form>
        <?php

        $this->render_footer();
    }

    private function handle_search_submission( int $contract_id, string $transaction_type ): void {
        if ( empty( $_POST['estate_office_search_action'] ) ) {
            return;
        }

        check_admin_referer( 'estate_office_search_create', 'estate_office_search_create_nonce' );

        $data      = isset( $_POST['search'] ) ? (array) wp_unslash( $_POST['search'] ) : [];
        $reference = sanitize_text_field( $data['reference'] ?? '' );

        if ( ! $reference ) {
            add_settings_error( 'estate-office-contract-wizard', 'search-reference', __( 'Numer poszukiwania jest wymagany.', 'estate-office' ) );
            return;
        }

        $post_title = sprintf( __( 'Poszukiwanie %s', 'estate-office' ), $reference );
        $search_id  = wp_insert_post(
            [
                'post_type'   => 'estate_search',
                'post_status' => 'publish',
                'post_title'  => $post_title,
                'post_author' => get_current_user_id(),
            ]
        );

        if ( $search_id instanceof WP_Error ) {
            add_settings_error( 'estate-office-contract-wizard', 'search-create', $search_id->get_error_message() );
            return;
        }

        $criteria = [
            'reference'        => $reference,
            'transaction_type' => $transaction_type,
            'property_kind'    => sanitize_key( $data['property_kind'] ?? '' ),
            'price_min'        => isset( $data['price_min'] ) ? (float) $data['price_min'] : 0.0,
            'price_max'        => isset( $data['price_max'] ) ? (float) $data['price_max'] : 0.0,
            'area_min'         => isset( $data['area_min'] ) ? (float) $data['area_min'] : 0.0,
            'area_max'         => isset( $data['area_max'] ) ? (float) $data['area_max'] : 0.0,
            'rooms_min'        => isset( $data['rooms_min'] ) ? (int) $data['rooms_min'] : 0,
            'rooms_max'        => isset( $data['rooms_max'] ) ? (int) $data['rooms_max'] : 0,
            'location'         => sanitize_text_field( $data['location'] ?? '' ),
            'description'      => wp_kses_post( $data['description'] ?? '' ),
            'building'         => $this->sanitize_nested_array( $data['building'] ?? [] ),
            'amenities'        => $this->sanitize_array_values( $data['amenities'] ?? [] ),
            'media'            => $this->sanitize_array_values( $data['media'] ?? [] ),
        ];

        update_post_meta( $search_id, Keys::SEARCH_CRITERIA, $criteria );
        update_post_meta( $search_id, Keys::SEARCH_CONTRACT, $contract_id );
        update_post_meta( $contract_id, Keys::CONTRACT_SEARCH, $search_id );

        add_settings_error( 'estate-office-contract-wizard', 'search-created', __( 'Poszukiwanie zostało dodane.', 'estate-office' ), 'updated' );

        $redirect = get_edit_post_link( $search_id, 'url' );
        wp_safe_redirect( $redirect );
        exit;
    }
    private function render_extra_space_field( string $key, string $label, bool $has_area = false ): void {
        ?>
        <div class="estate-office-extra-space" data-extra-space>
            <label>
                <input type="checkbox" name="property[extra_spaces][<?php echo esc_attr( $key ); ?>][enabled]" value="1" data-extra-space-toggle />
                <?php echo esc_html( $label ); ?>
            </label>
            <div class="estate-office-grid hidden" data-extra-space-fields>
                <?php if ( $key === 'balcony' || $key === 'taras' ) : ?>
                    <p>
                        <label class="estate-office-label" for="extra_<?php echo esc_attr( $key ); ?>_count"><?php esc_html_e( 'Ilość', 'estate-office' ); ?></label>
                        <input type="number" id="extra_<?php echo esc_attr( $key ); ?>_count" name="property[extra_spaces][<?php echo esc_attr( $key ); ?>][count]" min="0" />
                    </p>
                <?php endif; ?>
                <?php if ( $has_area || in_array( $key, [ 'balcony', 'taras', 'piwnica', 'komorka', 'ogrodek' ], true ) ) : ?>
                    <p>
                        <label class="estate-office-label" for="extra_<?php echo esc_attr( $key ); ?>_area"><?php esc_html_e( 'Powierzchnia (m²)', 'estate-office' ); ?></label>
                        <input type="number" id="extra_<?php echo esc_attr( $key ); ?>_area" name="property[extra_spaces][<?php echo esc_attr( $key ); ?>][area]" min="0" step="0.01" />
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_address_fields( string $base, string $heading, bool $property = false ): void {
        ?>
        <fieldset class="estate-office-address" <?php echo $property ? 'data-property-address-fields' : ''; ?>>
            <legend><?php echo esc_html( $heading ); ?></legend>
            <div class="estate-office-grid">
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_street"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_street" name="<?php echo esc_attr( $base ); ?>[street]" <?php echo $property ? 'required' : ''; ?> />
                </p>
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_number"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_number" name="<?php echo esc_attr( $base ); ?>[number]" <?php echo $property ? 'required' : ''; ?> />
                </p>
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_unit"><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_unit" name="<?php echo esc_attr( $base ); ?>[unit]" />
                </p>
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_postal"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_postal" name="<?php echo esc_attr( $base ); ?>[postal_code]" <?php echo $property ? 'required' : ''; ?> />
                </p>
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_district"><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_district" name="<?php echo esc_attr( $base ); ?>[district]" />
                </p>
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_city" name="<?php echo esc_attr( $base ); ?>[city]" <?php echo $property ? 'required' : ''; ?> />
                </p>
                <?php if ( $property ) : ?>
                    <p>
                        <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_county"><?php esc_html_e( 'Powiat', 'estate-office' ); ?></label>
                        <input type="text" id="<?php echo esc_attr( $base ); ?>_county" name="<?php echo esc_attr( $base ); ?>[county]" />
                    </p>
                    <p>
                        <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_region"><?php esc_html_e( 'Obręb', 'estate-office' ); ?></label>
                        <input type="text" id="<?php echo esc_attr( $base ); ?>_region" name="<?php echo esc_attr( $base ); ?>[region]" />
                    </p>
                    <p>
                        <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_plot"><?php esc_html_e( 'Numer działki', 'estate-office' ); ?></label>
                        <input type="text" id="<?php echo esc_attr( $base ); ?>_plot" name="<?php echo esc_attr( $base ); ?>[plot_number]" />
                    </p>
                <?php endif; ?>
                <p>
                    <label class="estate-office-label" for="<?php echo esc_attr( $base ); ?>_country"><?php esc_html_e( 'Kraj', 'estate-office' ); ?></label>
                    <input type="text" id="<?php echo esc_attr( $base ); ?>_country" name="<?php echo esc_attr( $base ); ?>[country]" />
                </p>
            </div>
        </fieldset>
        <?php
    }

    private function get_step(): string {
        $step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : self::STEP_CONTRACT;
        return in_array( $step, [ self::STEP_CONTRACT, self::STEP_CLIENTS, self::STEP_PROPERTY, self::STEP_SEARCH ], true ) ? $step : self::STEP_CONTRACT;
    }

    private function get_contract_id(): int {
        return isset( $_GET['contract_id'] ) ? (int) $_GET['contract_id'] : 0;
    }

    private function contract_exists( string $number ): bool {
        $query = new WP_Query(
            [
                'post_type'  => 'estate_contract',
                'meta_key'   => Keys::CONTRACT_NUMBER,
                'meta_value' => $number,
                'fields'     => 'ids',
            ]
        );

        return ! empty( $query->posts );
    }

    /**
     * @return WP_Post[]
     */
    private function get_contract_clients( int $contract_id ): array {
        $ids = $this->get_contract_client_ids( $contract_id );

        if ( empty( $ids ) ) {
            return [];
        }

        $query = new WP_Query(
            [
                'post_type' => 'estate_client',
                'post__in'  => $ids,
                'orderby'   => 'post__in',
            ]
        );

        return $query->posts;
    }

    /**
     * @return int[]
     */
    private function get_contract_client_ids( int $contract_id ): array {
        $ids = get_post_meta( $contract_id, Keys::CONTRACT_CLIENTS, true );
        return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
    }

    private function search_clients( string $term ): WP_Query {
        if ( ! $term ) {
            return new WP_Query( [ 'post_type' => 'estate_client', 'posts_per_page' => 0 ] );
        }

        return new WP_Query(
            [
                'post_type'      => 'estate_client',
                'posts_per_page' => 10,
                's'              => $term,
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => Keys::CLIENT_FIRST_NAME,
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => Keys::CLIENT_LAST_NAME,
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => Keys::CLIENT_PHONE,
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => Keys::CLIENT_EMAIL,
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );
    }

    private function format_client_name( WP_Post $client ): string {
        $type    = get_post_meta( $client->ID, Keys::CLIENT_TYPE, true );
        $first   = get_post_meta( $client->ID, Keys::CLIENT_FIRST_NAME, true );
        $last    = get_post_meta( $client->ID, Keys::CLIENT_LAST_NAME, true );
        $company = get_post_meta( $client->ID, Keys::CLIENT_COMPANY_NAME, true );

        if ( 'firma' === $type && $company ) {
            return $company;
        }

        $name = trim( $first . ' ' . $last );
        return $name ?: $client->post_title;
    }

    private function determine_next_step( int $contract_id ): string {
        $type = get_post_meta( $contract_id, Keys::CONTRACT_TYPE, true );
        if ( in_array( $type, [ 'sprzedaz', 'wynajem' ], true ) ) {
            return self::STEP_PROPERTY;
        }
        return self::STEP_SEARCH;
    }

    private function human_contract_type( string $type ): string {
        $map = [
            'sprzedaz' => __( 'SPRZEDAŻ', 'estate-office' ),
            'kupno'    => __( 'KUPNO', 'estate-office' ),
            'wynajem'  => __( 'WYNAJEM', 'estate-office' ),
            'najem'    => __( 'NAJEM', 'estate-office' ),
        ];

        $type = strtolower( $type );
        return $map[ $type ] ?? strtoupper( $type );
    }

    private function sanitize_address( array $address ): array {
        $fields = [ 'street', 'number', 'unit', 'postal_code', 'district', 'city', 'county', 'region', 'plot_number', 'country' ];
        $sanitized = [];

        foreach ( $fields as $field ) {
            $value               = $address[ $field ] ?? '';
            $sanitized[ $field ] = sanitize_text_field( wp_unslash( $value ) );
        }

        return $sanitized;
    }

    private function sanitize_nested_array( array $data ): array {
        $sanitized = [];

        foreach ( $data as $key => $value ) {
            $key = sanitize_key( $key );
            if ( is_array( $value ) ) {
                $sanitized[ $key ] = $this->sanitize_nested_array( wp_unslash( $value ) );
            } else {
                $value              = wp_unslash( $value );
                $sanitized[ $key ] = is_bool( $value ) ? (bool) $value : sanitize_text_field( (string) $value );
            }
        }

        return $sanitized;
    }

    private function sanitize_array_values( array $values ): array {
        $clean = [];
        foreach ( $values as $value ) {
            $clean[] = sanitize_key( wp_unslash( $value ) );
        }

        return array_values( array_unique( $clean ) );
    }
}
