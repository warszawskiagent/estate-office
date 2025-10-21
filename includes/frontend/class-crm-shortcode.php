<?php
namespace EstateOffice\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Front-end CRM shortcode.
 */
class CRM_Shortcode {
    /**
     * Constructor.
     */
    public function __construct() {
        add_shortcode( 'estate_office_crm', [ $this, 'render_shortcode' ] );
    }

    /**
     * Renders shortcode output.
     */
    public function render_shortcode(): string {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return '<div class="estate-office-crm-message">' . esc_html__( 'Dostęp do CRM jest ograniczony do administratorów.', 'estate-office' ) . '</div>';
        }

        ob_start();
        $this->render_dashboard();

        return ob_get_clean();
    }

    /**
     * Renders CRM dashboard markup.
     */
    private function render_dashboard(): void {
        $tabs       = [
            'dashboard'  => __( 'Pulpit', 'estate-office' ),
            'properties' => __( 'Nieruchomości', 'estate-office' ),
            'contracts'  => __( 'Umowy', 'estate-office' ),
            'clients'    => __( 'Klienci', 'estate-office' ),
            'searches'   => __( 'Poszukiwania', 'estate-office' ),
        ];
        $properties = $this->get_properties();
        $contracts  = $this->get_contracts();
        $clients    = $this->get_clients();
        $searches   = $this->get_searches();
        $counts     = [
            'properties' => count( $properties ),
            'contracts'  => count( $contracts ),
            'clients'    => count( $clients ),
            'searches'   => count( $searches ),
        ];
        ?>
        <div class="estate-office-crm" data-nonce="<?php echo esc_attr( wp_create_nonce( 'estate_office_frontend' ) ); ?>">
            <div class="estate-office-crm-header">
                <h2><?php esc_html_e( 'Panel CRM', 'estate-office' ); ?></h2>
                <button type="button" class="button button-primary estate-office-new-contract">
                    <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
                </button>
            </div>
            <nav class="estate-office-crm-tabs" role="tablist">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <button type="button" class="estate-office-crm-tab<?php echo 'dashboard' === $key ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </button>
                <?php endforeach; ?>
            </nav>
            <div class="estate-office-crm-search">
                <label for="estate-office-crm-search-field" class="screen-reader-text"><?php esc_html_e( 'Wyszukaj', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-crm-search-field" placeholder="<?php esc_attr_e( 'Wyszukaj we wszystkich kolumnach', 'estate-office' ); ?>" />
            </div>
            <div class="estate-office-crm-content">
                <?php $this->render_overview_panel( $counts ); ?>
                <?php $this->render_table( 'properties', __( 'Brak nieruchomości do wyświetlenia.', 'estate-office' ), [
                    __( 'Numer oferty', 'estate-office' ),
                    __( 'Adres', 'estate-office' ),
                    __( 'Cena', 'estate-office' ),
                    __( 'Cena za m²', 'estate-office' ),
                    __( 'Metraż', 'estate-office' ),
                    __( 'Liczba pokoi', 'estate-office' ),
                    __( 'Opiekun', 'estate-office' ),
                ], $properties ); ?>
                <?php $this->render_table( 'searches', __( 'Brak poszukiwań.', 'estate-office' ), [
                    __( 'Numer poszukiwania', 'estate-office' ),
                    __( 'Rodzaj nieruchomości', 'estate-office' ),
                    __( 'Budżet', 'estate-office' ),
                    __( 'Lokalizacja', 'estate-office' ),
                    __( 'Typ transakcji', 'estate-office' ),
                ], $searches ); ?>
                <?php $this->render_table( 'contracts', __( 'Brak umów.', 'estate-office' ), [
                    __( 'Numer umowy', 'estate-office' ),
                    __( 'Typ transakcji', 'estate-office' ),
                    __( 'Rodzaj nieruchomości', 'estate-office' ),
                    __( 'Adres', 'estate-office' ),
                    __( 'Data zawarcia', 'estate-office' ),
                    __( 'Data zakończenia', 'estate-office' ),
                    __( 'Aktualny etap', 'estate-office' ),
                    __( 'Opiekun', 'estate-office' ),
                ], $contracts ); ?>
                <?php $this->render_table( 'clients', __( 'Brak klientów.', 'estate-office' ), [
                    __( 'Imię i nazwisko/Nazwa', 'estate-office' ),
                    __( 'Adres', 'estate-office' ),
                    __( 'Telefon', 'estate-office' ),
                    __( 'E-mail', 'estate-office' ),
                    __( 'Opiekun', 'estate-office' ),
                ], $clients ); ?>
            </div>
        </div>
        <?php $this->render_contract_modal(); ?>
        <?php $this->render_profile_drawer(); ?>
        <?php
    }

    /**
     * Renders overview panel.
     */
    private function render_overview_panel( array $counts ): void {
        ?>
        <section class="estate-office-crm-panel is-active" data-panel="dashboard">
            <div class="estate-office-crm-overview">
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['properties'] ) ); ?></span>
                </div>
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Umowy', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['contracts'] ) ); ?></span>
                </div>
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['clients'] ) ); ?></span>
                </div>
                <div class="overview-card">
                    <span class="label"><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></span>
                    <span class="value"><?php echo esc_html( number_format_i18n( $counts['searches'] ) ); ?></span>
                </div>
            </div>
            <p class="overview-description">
                <?php esc_html_e( 'W kolejnych wydaniach panel zostanie rozszerzony o wskaźniki skuteczności, harmonogram zadań oraz listę najaktywniejszych agentów.', 'estate-office' ); ?>
            </p>
        </section>
        <?php
    }

    /**
     * Renders data table panel.
     */
    private function render_table( string $slug, string $empty_message, array $headers, array $rows ): void {
        ?>
        <section class="estate-office-crm-panel" data-panel="<?php echo esc_attr( $slug ); ?>">
            <table class="estate-office-crm-table">
                <thead>
                    <tr>
                        <?php foreach ( $headers as $header ) : ?>
                            <th><?php echo esc_html( $header ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr class="estate-office-empty">
                            <td colspan="<?php echo esc_attr( count( $headers ) ); ?>"><?php echo esc_html( $empty_message ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <?php foreach ( $row as $cell ) : ?>
                                    <td><?php echo wp_kses_post( $cell ); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
    }

    /**
     * Renders the contract creation modal wizard.
     */
    private function render_contract_modal(): void {
        $transaction_options = $this->get_transaction_options();
        $property_types      = $this->get_property_type_options();
        $commission_units    = $this->get_commission_units();
        $agents              = $this->get_agents();
        $document_types      = $this->get_document_types();
        $house_types         = $this->get_house_types();
        $legal_statuses      = $this->get_legal_statuses();
        $flag_options        = $this->get_flag_options();
        ?>
        <div class="estate-office-modal" aria-hidden="true" role="dialog" aria-label="<?php esc_attr_e( 'Nowa umowa', 'estate-office' ); ?>">
            <div class="estate-office-modal__dialog" role="document">
                <button type="button" class="estate-office-modal__close" aria-label="<?php esc_attr_e( 'Zamknij', 'estate-office' ); ?>">&times;</button>
                <div class="estate-office-wizard" data-step="1">
                    <ol class="estate-office-wizard__progress">
                        <li class="is-active" data-step="1"><?php esc_html_e( 'Umowa', 'estate-office' ); ?></li>
                        <li data-step="2"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></li>
                        <li data-step="3"><?php esc_html_e( 'Nieruchomość / Poszukiwanie', 'estate-office' ); ?></li>
                    </ol>

                    <div class="estate-office-wizard__body">
                        <section class="estate-office-wizard__step is-active" data-step="1">
                            <h3><?php esc_html_e( 'Etap 1: Nowa umowa', 'estate-office' ); ?></h3>
                            <p class="estate-office-wizard__intro"><?php esc_html_e( 'Uzupełnij podstawowe dane umowy. Numer umowy musi być unikalny.', 'estate-office' ); ?></p>
                            <form class="estate-office-wizard__form" data-action="create-contract">
                                <div class="estate-office-field-grid">
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-number"><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></label>
                                        <input type="text" id="estate-office-contract-number" name="contract_number" required />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-transaction"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                                        <select id="estate-office-contract-transaction" name="transaction_type" required>
                                            <?php foreach ( $transaction_options as $value => $label ) : ?>
                                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-start"><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></label>
                                        <input type="date" id="estate-office-contract-start" name="start_date" required />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-end"><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></label>
                                        <input type="date" id="estate-office-contract-end" name="end_date" data-disabled-by="contract_no_end" />
                                    </div>
                                    <div class="estate-office-field estate-office-checkbox">
                                        <label>
                                            <input type="checkbox" name="no_end_date" id="estate-office-contract-no-end" value="1" data-toggle="contract_no_end" />
                                            <?php esc_html_e( 'Umowa bezterminowa', 'estate-office' ); ?>
                                        </label>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-commission"><?php esc_html_e( 'Wysokość prowizji', 'estate-office' ); ?></label>
                                        <input type="number" id="estate-office-contract-commission" name="commission" min="0" step="0.01" />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-unit"><?php esc_html_e( 'Jednostka prowizji', 'estate-office' ); ?></label>
                                        <select id="estate-office-contract-unit" name="commission_unit">
                                            <?php foreach ( $commission_units as $value => $label ) : ?>
                                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-property-type"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                                        <select id="estate-office-contract-property-type" name="property_type">
                                            <option value=""><?php esc_html_e( 'Wybierz', 'estate-office' ); ?></option>
                                            <?php foreach ( $property_types as $value => $label ) : ?>
                                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-contract-agent"><?php esc_html_e( 'Opiekun umowy', 'estate-office' ); ?></label>
                                        <select id="estate-office-contract-agent" name="agent_id">
                                            <option value="0"><?php esc_html_e( 'Brak', 'estate-office' ); ?></option>
                                            <?php foreach ( $agents as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="estate-office-wizard__actions">
                                    <span class="estate-office-wizard__error" aria-live="polite"></span>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Dalej', 'estate-office' ); ?></button>
                                </div>
                            </form>
                        </section>

                        <section class="estate-office-wizard__step" data-step="2">
                            <h3><?php esc_html_e( 'Etap 2: Dodawanie klienta', 'estate-office' ); ?></h3>
                            <p class="estate-office-wizard__intro"><?php esc_html_e( 'Wyszukaj istniejącego klienta lub uzupełnij dane nowego kontaktu.', 'estate-office' ); ?></p>
                            <div class="estate-office-wizard__clients">
                                <div class="estate-office-clients__column">
                                    <form class="estate-office-client-search" data-action="search-clients">
                                        <label for="estate-office-client-query"><?php esc_html_e( 'Wyszukaj klienta', 'estate-office' ); ?></label>
                                        <input type="search" id="estate-office-client-query" name="search" placeholder="<?php esc_attr_e( 'Imię, nazwisko, telefon lub e-mail', 'estate-office' ); ?>" />
                                        <button type="submit" class="button"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
                                    </form>
                                    <ul class="estate-office-client-results" aria-live="polite"></ul>
                                </div>
                                <div class="estate-office-clients__column">
                                    <form class="estate-office-client-form" data-action="create-client">
                                        <h4><?php esc_html_e( 'Dodaj nowego klienta', 'estate-office' ); ?></h4>
                                        <div class="estate-office-field-grid">
                                            <div class="estate-office-field">
                                                <label for="estate-office-client-type"><?php esc_html_e( 'Typ klienta', 'estate-office' ); ?></label>
                                                <select id="estate-office-client-type" name="client_type">
                                                    <option value="osoba"><?php esc_html_e( 'Osoba fizyczna', 'estate-office' ); ?></option>
                                                    <option value="firma"><?php esc_html_e( 'Firma', 'estate-office' ); ?></option>
                                                </select>
                                            </div>
                                            <div class="estate-office-field" data-visible="osoba">
                                                <label for="estate-office-client-first-name"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-first-name" name="first_name" />
                                            </div>
                                            <div class="estate-office-field" data-visible="osoba">
                                                <label for="estate-office-client-last-name"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-last-name" name="last_name" />
                                            </div>
                                            <div class="estate-office-field" data-visible="firma">
                                                <label for="estate-office-client-company"><?php esc_html_e( 'Nazwa firmy', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-company" name="company_name" />
                                            </div>
                                            <div class="estate-office-field" data-visible="firma">
                                                <label for="estate-office-client-representative"><?php esc_html_e( 'Reprezentant', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-representative" name="company_representative" />
                                            </div>
                                        </div>
                                        <h5><?php esc_html_e( 'Dane kontaktowe', 'estate-office' ); ?></h5>
                                        <div class="estate-office-field-grid">
                                            <div class="estate-office-field">
                                                <label for="estate-office-client-phone"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-phone" name="phone" />
                                            </div>
                                            <div class="estate-office-field">
                                                <label for="estate-office-client-email"><?php esc_html_e( 'E-mail', 'estate-office' ); ?></label>
                                                <input type="email" id="estate-office-client-email" name="email" />
                                            </div>
                                            <div class="estate-office-field" data-visible="firma">
                                                <label for="estate-office-client-website"><?php esc_html_e( 'Strona WWW', 'estate-office' ); ?></label>
                                                <input type="url" id="estate-office-client-website" name="website" />
                                            </div>
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-client-agent"><?php esc_html_e( 'Opiekun klienta', 'estate-office' ); ?></label>
                                            <select id="estate-office-client-agent" name="agent_id">
                                                <option value="0"><?php esc_html_e( 'Brak', 'estate-office' ); ?></option>
                                                <?php foreach ( $agents as $id => $name ) : ?>
                                                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <h5><?php esc_html_e( 'Dane identyfikacyjne', 'estate-office' ); ?></h5>
                                        <div class="estate-office-field-grid">
                                            <div class="estate-office-field" data-visible="osoba">
                                                <label for="estate-office-client-pesel"><?php esc_html_e( 'PESEL', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-pesel" name="pesel" />
                                            </div>
                                            <div class="estate-office-field" data-visible="osoba">
                                                <label for="estate-office-client-document"><?php esc_html_e( 'Rodzaj dokumentu', 'estate-office' ); ?></label>
                                                <select id="estate-office-client-document" name="document_type">
                                                    <?php foreach ( $document_types as $value => $label ) : ?>
                                                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="estate-office-field" data-visible="osoba">
                                                <label for="estate-office-client-document-number"><?php esc_html_e( 'Numer dokumentu', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-document-number" name="document_number" />
                                            </div>
                                            <div class="estate-office-field" data-visible="firma">
                                                <label for="estate-office-client-nip"><?php esc_html_e( 'NIP', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-nip" name="nip" />
                                            </div>
                                            <div class="estate-office-field" data-visible="firma">
                                                <label for="estate-office-client-krs"><?php esc_html_e( 'KRS', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-krs" name="krs" />
                                            </div>
                                            <div class="estate-office-field" data-visible="firma">
                                                <label for="estate-office-client-regon"><?php esc_html_e( 'REGON', 'estate-office' ); ?></label>
                                                <input type="text" id="estate-office-client-regon" name="regon" />
                                            </div>
                                        </div>
                                        <h5><?php esc_html_e( 'Adres zamieszkania / rejestrowy', 'estate-office' ); ?></h5>
                                        <div class="estate-office-field-grid estate-office-address-group">
                                            <?php $this->render_address_fields( 'address' ); ?>
                                        </div>
                                        <div class="estate-office-field estate-office-checkbox">
                                            <label>
                                                <input type="checkbox" name="same_correspondence" value="1" checked />
                                                <?php esc_html_e( 'Adres korespondencyjny taki sam', 'estate-office' ); ?>
                                            </label>
                                        </div>
                                        <div class="estate-office-address-group estate-office-correspondence" hidden>
                                            <h5><?php esc_html_e( 'Adres korespondencyjny', 'estate-office' ); ?></h5>
                                            <?php $this->render_address_fields( 'correspondence' ); ?>
                                        </div>
                                        <div class="estate-office-wizard__actions">
                                            <span class="estate-office-wizard__error" aria-live="polite"></span>
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Zapisz klienta', 'estate-office' ); ?></button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="estate-office-selected-clients">
                                <h4><?php esc_html_e( 'Wybrani klienci', 'estate-office' ); ?></h4>
                                <ul class="estate-office-selected-clients__list"></ul>
                            </div>
                            <div class="estate-office-wizard__actions">
                                <button type="button" class="button estate-office-wizard__back" data-direction="prev"><?php esc_html_e( 'Wstecz', 'estate-office' ); ?></button>
                                <div class="estate-office-wizard__cta" hidden>
                                    <span><?php esc_html_e( 'Czy chcesz dodać kolejnego klienta?', 'estate-office' ); ?></span>
                                    <button type="button" class="button estate-office-add-next-client" data-answer="yes"><?php esc_html_e( 'Tak', 'estate-office' ); ?></button>
                                    <button type="button" class="button button-primary estate-office-add-next-client" data-answer="no"><?php esc_html_e( 'Nie', 'estate-office' ); ?></button>
                                </div>
                            </div>
                        </section>

                        <section class="estate-office-wizard__step" data-step="3">
                            <h3><?php esc_html_e( 'Etap 3: Szczegóły oferty', 'estate-office' ); ?></h3>
                            <p class="estate-office-wizard__intro"><?php esc_html_e( 'Uzupełnij informacje o nieruchomości lub kryteriach poszukiwania w zależności od typu transakcji.', 'estate-office' ); ?></p>
                            <form class="estate-office-wizard__form estate-office-property-form" data-transaction="sprzedaz wynajem" data-action="create-property">
                                <input type="hidden" name="contract_id" />
                                <div class="estate-office-field-grid">
                                    <div class="estate-office-field">
                                        <label for="estate-office-offer-number"><?php esc_html_e( 'Numer oferty', 'estate-office' ); ?></label>
                                        <input type="text" id="estate-office-offer-number" name="offer_number" />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-offer-transaction"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                                        <select id="estate-office-offer-transaction" name="transaction_type" required>
                                            <option value="sprzedaz"><?php esc_html_e( 'Sprzedaż', 'estate-office' ); ?></option>
                                            <option value="wynajem"><?php esc_html_e( 'Wynajem', 'estate-office' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-offer-property-type"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                                        <select id="estate-office-offer-property-type" name="property_type" required>
                                            <?php foreach ( $property_types as $value => $label ) : ?>
                                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-offer-price"><?php esc_html_e( 'Cena', 'estate-office' ); ?></label>
                                        <input type="number" id="estate-office-offer-price" name="price" min="0" step="0.01" />
                                    </div>
                                    <div class="estate-office-field" data-transaction-visible="wynajem">
                                        <label for="estate-office-offer-rent"><?php esc_html_e( 'Czynsz administracyjny', 'estate-office' ); ?></label>
                                        <input type="number" id="estate-office-offer-rent" name="rent" min="0" step="0.01" />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-offer-area"><?php esc_html_e( 'Powierzchnia (m²)', 'estate-office' ); ?></label>
                                        <input type="number" id="estate-office-offer-area" name="area" min="0" step="0.01" />
                                    </div>
                                    <div class="estate-office-field" data-visible="mieszkanie,dom,lokal">
                                        <label for="estate-office-offer-rooms"><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></label>
                                        <input type="number" id="estate-office-offer-rooms" name="rooms" min="0" />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-offer-agent"><?php esc_html_e( 'Opiekun oferty', 'estate-office' ); ?></label>
                                        <select id="estate-office-offer-agent" name="agent_id">
                                            <option value="0"><?php esc_html_e( 'Brak', 'estate-office' ); ?></option>
                                            <?php foreach ( $agents as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <fieldset class="estate-office-fieldset">
                                    <legend><?php esc_html_e( 'Dane adresowe', 'estate-office' ); ?></legend>
                                    <div class="estate-office-field-grid">
                                        <div class="estate-office-field" data-property-visible="mieszkanie,lokal,dom">
                                            <label for="estate-office-offer-street"><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-street" name="location[street]" />
                                        </div>
                                        <div class="estate-office-field" data-property-visible="mieszkanie,lokal,dom">
                                            <label for="estate-office-offer-number"><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-number" name="location[number]" />
                                        </div>
                                        <div class="estate-office-field" data-property-visible="mieszkanie,lokal">
                                            <label for="estate-office-offer-unit"><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-unit" name="location[unit]" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-offer-postal"><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-postal" name="location[postal_code]" />
                                        </div>
                                        <div class="estate-office-field" data-property-visible="mieszkanie,lokal">
                                            <label for="estate-office-offer-district"><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-district" name="location[district]" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-offer-city"><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-city" name="location[city]" required />
                                        </div>
                                        <div class="estate-office-field" data-property-visible="dom,dzialka">
                                            <label for="estate-office-offer-county"><?php esc_html_e( 'Powiat', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-county" name="location[county]" />
                                        </div>
                                        <div class="estate-office-field" data-property-visible="dom,dzialka">
                                            <label for="estate-office-offer-district-area"><?php esc_html_e( 'Obręb', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-district-area" name="location[district_area]" />
                                        </div>
                                        <div class="estate-office-field" data-property-visible="dom,dzialka">
                                            <label for="estate-office-offer-plot"><?php esc_html_e( 'Numer działki', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-plot" name="location[plot_number]" />
                                        </div>
                                    </div>
                                    <div class="estate-office-field-grid">
                                        <div class="estate-office-field" data-property-visible="dom">
                                            <label for="estate-office-offer-house-type"><?php esc_html_e( 'Typ domu', 'estate-office' ); ?></label>
                                            <select id="estate-office-offer-house-type" name="house_type">
                                                <option value=""><?php esc_html_e( 'Wybierz', 'estate-office' ); ?></option>
                                                <?php foreach ( $house_types as $value => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-offer-lmr"><?php esc_html_e( 'Numer księgi wieczystej', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-offer-lmr" name="land_and_mortgage_register" />
                                        </div>
                                        <div class="estate-office-field estate-office-checkbox">
                                            <label>
                                                <input type="checkbox" name="no_lmr" value="1" />
                                                <?php esc_html_e( 'Brak księgi wieczystej', 'estate-office' ); ?>
                                            </label>
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-offer-legal"><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></label>
                                            <select id="estate-office-offer-legal" name="legal_status">
                                                <?php foreach ( $legal_statuses as $value => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="estate-office-field" data-property-visible="dzialka">
                                            <label for="estate-office-offer-lot-shape"><?php esc_html_e( 'Kształt działki', 'estate-office' ); ?></label>
                                            <select id="estate-office-offer-lot-shape" name="lot_shape">
                                                <option value="regularny"><?php esc_html_e( 'Regularny', 'estate-office' ); ?></option>
                                                <option value="nieregularny"><?php esc_html_e( 'Nieregularny', 'estate-office' ); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="estate-office-field-grid estate-office-lot-fields" data-lot-shape="regularny" data-property-visible="dzialka">
                                        <div class="estate-office-field">
                                            <label for="estate-office-lot-length"><?php esc_html_e( 'Długość działki (m)', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-lot-length" name="lot_length" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-lot-width"><?php esc_html_e( 'Szerokość działki (m)', 'estate-office' ); ?></label>
                                            <input type="text" id="estate-office-lot-width" name="lot_width" />
                                        </div>
                                    </div>
                                    <div class="estate-office-field" data-lot-shape="nieregularny" data-property-visible="dzialka" hidden>
                                        <label for="estate-office-lot-notes"><?php esc_html_e( 'Opis kształtu działki', 'estate-office' ); ?></label>
                                        <textarea id="estate-office-lot-notes" name="lot_notes"></textarea>
                                    </div>
                                </fieldset>
                                <div class="estate-office-field">
                                    <label for="estate-office-offer-description"><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></label>
                                    <textarea id="estate-office-offer-description" name="description" rows="6"></textarea>
                                </div>
                                <fieldset class="estate-office-fieldset">
                                    <legend><?php esc_html_e( 'Znaczniki', 'estate-office' ); ?></legend>
                                    <div class="estate-office-field estate-office-checkboxes estate-office-flag-list">
                                        <?php foreach ( $flag_options as $value => $label ) : ?>
                                            <label data-transaction="<?php echo esc_attr( $label['transaction'] ); ?>">
                                                <input type="checkbox" name="flags[]" value="<?php echo esc_attr( $value ); ?>" />
                                                <span><?php echo esc_html( $label['label'] ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                                <div class="estate-office-wizard__actions">
                                    <button type="button" class="button estate-office-wizard__back" data-direction="prev"><?php esc_html_e( 'Wstecz', 'estate-office' ); ?></button>
                                    <span class="estate-office-wizard__error" aria-live="polite"></span>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj nieruchomość', 'estate-office' ); ?></button>
                                </div>
                            </form>

                            <form class="estate-office-wizard__form estate-office-search-form" data-transaction="kupno najem" data-action="create-search" hidden>
                                <input type="hidden" name="contract_id" />
                                <div class="estate-office-field-grid">
                                    <div class="estate-office-field">
                                        <label for="estate-office-search-number"><?php esc_html_e( 'Numer poszukiwania', 'estate-office' ); ?></label>
                                        <input type="text" id="estate-office-search-number" name="search_number" />
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-search-transaction"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                                        <select id="estate-office-search-transaction" name="transaction_type" required>
                                            <option value="kupno"><?php esc_html_e( 'Kupno', 'estate-office' ); ?></option>
                                            <option value="najem"><?php esc_html_e( 'Najem', 'estate-office' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="estate-office-field">
                                        <label for="estate-office-search-property-type"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                                        <select id="estate-office-search-property-type" name="property_type" required>
                                            <?php foreach ( $property_types as $value => $label ) : ?>
                                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <fieldset class="estate-office-fieldset">
                                    <legend><?php esc_html_e( 'Budżet', 'estate-office' ); ?></legend>
                                    <div class="estate-office-field-grid">
                                        <div class="estate-office-field">
                                            <label for="estate-office-search-budget-min"><?php esc_html_e( 'Cena od', 'estate-office' ); ?></label>
                                            <input type="number" id="estate-office-search-budget-min" name="budget_min" min="0" step="0.01" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-search-budget-max"><?php esc_html_e( 'Cena do', 'estate-office' ); ?></label>
                                            <input type="number" id="estate-office-search-budget-max" name="budget_max" min="0" step="0.01" />
                                        </div>
                                    </div>
                                </fieldset>
                                <fieldset class="estate-office-fieldset">
                                    <legend><?php esc_html_e( 'Metraż i pokoje', 'estate-office' ); ?></legend>
                                    <div class="estate-office-field-grid">
                                        <div class="estate-office-field">
                                            <label for="estate-office-search-area-min"><?php esc_html_e( 'Metraż od', 'estate-office' ); ?></label>
                                            <input type="number" id="estate-office-search-area-min" name="area_min" min="0" step="0.01" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-search-area-max"><?php esc_html_e( 'Metraż do', 'estate-office' ); ?></label>
                                            <input type="number" id="estate-office-search-area-max" name="area_max" min="0" step="0.01" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-search-rooms-min"><?php esc_html_e( 'Liczba pokoi od', 'estate-office' ); ?></label>
                                            <input type="number" id="estate-office-search-rooms-min" name="rooms_min" min="0" />
                                        </div>
                                        <div class="estate-office-field">
                                            <label for="estate-office-search-rooms-max"><?php esc_html_e( 'Liczba pokoi do', 'estate-office' ); ?></label>
                                            <input type="number" id="estate-office-search-rooms-max" name="rooms_max" min="0" />
                                        </div>
                                    </div>
                                </fieldset>
                                <div class="estate-office-field">
                                    <label for="estate-office-search-location"><?php esc_html_e( 'Preferowana lokalizacja', 'estate-office' ); ?></label>
                                    <input type="text" id="estate-office-search-location" name="location" />
                                </div>
                                <div class="estate-office-field">
                                    <label for="estate-office-search-description"><?php esc_html_e( 'Opis poszukiwania', 'estate-office' ); ?></label>
                                    <textarea id="estate-office-search-description" name="description" rows="6"></textarea>
                                </div>
                                <div class="estate-office-field">
                                    <label for="estate-office-search-agent"><?php esc_html_e( 'Opiekun poszukiwania', 'estate-office' ); ?></label>
                                    <select id="estate-office-search-agent" name="agent_id">
                                        <option value="0"><?php esc_html_e( 'Brak', 'estate-office' ); ?></option>
                                        <?php foreach ( $agents as $id => $name ) : ?>
                                            <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="estate-office-wizard__actions">
                                    <button type="button" class="button estate-office-wizard__back" data-direction="prev"><?php esc_html_e( 'Wstecz', 'estate-office' ); ?></button>
                                    <span class="estate-office-wizard__error" aria-live="polite"></span>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj poszukiwanie', 'estate-office' ); ?></button>
                                </div>
                            </form>

                            <div class="estate-office-wizard__summary" hidden>
                                <div class="estate-office-wizard__success" role="status"></div>
                                <div class="estate-office-wizard__links"></div>
                                <div class="estate-office-wizard__actions">
                                    <button type="button" class="button button-primary estate-office-wizard__close"><?php esc_html_e( 'Zamknij kreator', 'estate-office' ); ?></button>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders a standard address group for wizard forms.
     */
    private function render_address_fields( string $prefix ): void {
        $fields = [
            'street'      => __( 'Ulica', 'estate-office' ),
            'number'      => __( 'Numer', 'estate-office' ),
            'unit'        => __( 'Lokal', 'estate-office' ),
            'postal_code' => __( 'Kod pocztowy', 'estate-office' ),
            'city'        => __( 'Miasto', 'estate-office' ),
            'country'     => __( 'Kraj', 'estate-office' ),
        ];

        foreach ( $fields as $key => $label ) {
            $id = sprintf( 'estate-office-%s-%s', sanitize_key( $prefix ), $key );
            ?>
            <div class="estate-office-field">
                <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
                <input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[<?php echo esc_attr( $key ); ?>]" />
            </div>
            <?php
        }
    }

    /**
     * Returns transaction labels used in the wizard.
     */
    private function get_transaction_options(): array {
        return [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Returns property type labels.
     */
    private function get_property_type_options(): array {
        return [
            'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
            'dom'        => __( 'Dom', 'estate-office' ),
            'dzialka'    => __( 'Działka', 'estate-office' ),
            'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];
    }

    /**
     * Returns commission unit labels.
     */
    private function get_commission_units(): array {
        return [
            'percent' => '%',
            'pln'     => __( 'PLN', 'estate-office' ),
            'eur'     => __( 'EUR', 'estate-office' ),
            'usd'     => __( 'USD', 'estate-office' ),
        ];
    }

    /**
     * Returns agent names for dropdowns.
     */
    private function get_agents(): array {
        $users = get_users(
            [
                'role__in' => [ 'estate_agent', 'administrator' ],
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            ]
        );

        $options = [];
        foreach ( $users as $user ) {
            $options[ $user->ID ] = $user->display_name;
        }

        return $options;
    }

    /**
     * Returns document type options.
     */
    private function get_document_types(): array {
        return [
            ''            => __( 'Wybierz', 'estate-office' ),
            'dowod'       => __( 'Dowód osobisty', 'estate-office' ),
            'paszport'    => __( 'Paszport', 'estate-office' ),
            'karta_pobytu'=> __( 'Karta pobytu', 'estate-office' ),
        ];
    }

    /**
     * Returns house type labels for detached houses.
     */
    private function get_house_types(): array {
        return [
            'wolnostojacy'  => __( 'Wolnostojący', 'estate-office' ),
            'blizniak'      => __( 'Bliźniak', 'estate-office' ),
            'szeregowiec'   => __( 'Szeregowiec', 'estate-office' ),
            'wielorodzinny' => __( 'Wielorodzinny', 'estate-office' ),
        ];
    }

    /**
     * Returns legal status labels.
     */
    private function get_legal_statuses(): array {
        return [
            ''              => __( 'Wybierz', 'estate-office' ),
            'wlasnosc'      => __( 'Własność', 'estate-office' ),
            'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
            'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
            'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
            'inne'          => __( 'Inne', 'estate-office' ),
        ];
    }

    /**
     * Returns available property flags with optional transaction restrictions.
     */
    private function get_flag_options(): array {
        return [
            'new_offer'  => [
                'label'       => __( 'Nowa oferta', 'estate-office' ),
                'transaction' => '',
            ],
            'exclusive'  => [
                'label'       => __( 'Wyłączność', 'estate-office' ),
                'transaction' => '',
            ],
            'sold'       => [
                'label'       => __( 'Sprzedane', 'estate-office' ),
                'transaction' => 'sprzedaz',
            ],
            'rented'     => [
                'label'       => __( 'Wynajęte', 'estate-office' ),
                'transaction' => 'wynajem',
            ],
            'new_price'  => [
                'label'       => __( 'Nowa cena', 'estate-office' ),
                'transaction' => '',
            ],
            'no_commision' => [
                'label'       => __( 'Bez prowizji', 'estate-office' ),
                'transaction' => '',
            ],
            'mls'        => [
                'label'       => __( 'Oferta MLS', 'estate-office' ),
                'transaction' => '',
            ],
            'premium'    => [
                'label'       => __( 'Premium', 'estate-office' ),
                'transaction' => '',
            ],
            'export_www' => [
                'label'       => __( 'Eksport na WWW', 'estate-office' ),
                'transaction' => '',
            ],
        ];
    }

    /**
     * Collects property data rows.
     *
     * @return array<int, array<int, string>>
     */
    private function get_properties(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_property',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $number     = $this->get_meta_value( $post->ID, 'offer_number', $post->post_title );
            $address    = $this->format_property_address( $post->ID );
            $price      = $this->get_meta_value( $post->ID, 'price' );
            $sqm        = $this->get_meta_value( $post->ID, 'price_sqm' );
            $area       = $this->get_meta_value( $post->ID, 'area' );
            $rooms      = $this->get_meta_value( $post->ID, 'rooms', '-' );
            $agent_name = $this->get_agent_name( (int) $this->get_meta_value( $post->ID, 'agent_id', 0 ) );

            $rows[] = [
                $this->format_profile_link( $post, $number ?: $post->post_title, 'property' ),
                esc_html( $address ?: __( 'Brak adresu', 'estate-office' ) ),
                esc_html( $this->format_price( $price ) ),
                esc_html( $this->format_price( $sqm ) ),
                esc_html( $this->format_area( $area ) ),
                esc_html( $rooms ?: '-' ),
                esc_html( $agent_name ),
            ];
        }

        return $rows;
    }

    /**
     * Collects contract data rows.
     */
    private function get_contracts(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_contract',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $number        = $this->get_meta_value( $post->ID, 'contract_number', $post->post_title );
            $transaction   = $this->translate_transaction_type( $this->get_meta_value( $post->ID, 'transaction_type' ) );
            $property_type = $this->translate_property_type( $this->get_meta_value( $post->ID, 'property_type' ) );
            $property_id   = (int) $this->get_meta_value( $post->ID, 'related_property', 0 );
            $address       = $property_id ? $this->format_property_address( $property_id ) : '';
            $start_date    = $this->get_meta_value( $post->ID, 'start_date' );
            $end_date      = $this->get_meta_value( $post->ID, 'end_date' );
            $stage_key     = $this->get_meta_value( $post->ID, 'stage' );
            $stage_label   = $stage_key ? $this->get_contract_stage_label( $stage_key ) : __( 'Umowa pośrednictwa', 'estate-office' );
            $agent_name    = $this->get_agent_name( (int) $this->get_meta_value( $post->ID, 'agent_id', 0 ) );

            $rows[] = [
                $this->format_profile_link( $post, $number ?: $post->post_title, 'contract' ),
                esc_html( $transaction ?: '-' ),
                esc_html( $property_type ?: '-' ),
                esc_html( $address ?: '-' ),
                esc_html( $this->format_date( $start_date ) ),
                esc_html( $this->format_date( $end_date ) ),
                esc_html( $stage_label ),
                esc_html( $agent_name ),
            ];
        }

        return $rows;
    }

    /**
     * Collects client data rows.
     */
    private function get_clients(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_client',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $address    = $this->format_client_address( $post->ID );
            $phone      = $this->get_meta_value( $post->ID, 'phone', '-' );
            $email      = $this->get_meta_value( $post->ID, 'email', '-' );
            $agent_name = $this->get_agent_name( (int) $this->get_meta_value( $post->ID, 'agent_id', 0 ) );

            $rows[] = [
                $this->format_profile_link( $post, $post->post_title, 'client' ),
                esc_html( $address ?: '-' ),
                esc_html( $phone ?: '-' ),
                esc_html( $email ?: '-' ),
                esc_html( $agent_name ),
            ];
        }

        return $rows;
    }

    /**
     * Collects search data rows.
     */
    private function get_searches(): array {
        $posts = get_posts(
            [
                'post_type'      => 'estate_search',
                'posts_per_page' => 50,
                'post_status'    => [ 'publish', 'draft' ],
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $number        = $this->get_meta_value( $post->ID, 'search_number', $post->post_title );
            $property_type = $this->translate_property_type( $this->get_meta_value( $post->ID, 'property_type' ) );
            $budget        = $this->format_budget( $this->get_meta_value( $post->ID, 'budget_min' ), $this->get_meta_value( $post->ID, 'budget_max' ) );
            $location      = $this->get_meta_value( $post->ID, 'location', '-' );
            $transaction   = $this->translate_transaction_type( $this->get_meta_value( $post->ID, 'transaction_type' ) );

            $rows[] = [
                $this->format_profile_link( $post, $number ?: $post->post_title, 'search' ),
                esc_html( $property_type ?: '-' ),
                esc_html( $budget ),
                esc_html( $location ?: '-' ),
                esc_html( $transaction ?: '-' ),
            ];
        }

        return $rows;
    }

    /**
     * Creates a button-like link that triggers the profile drawer.
     */
    private function format_profile_link( \WP_Post $post, string $label, string $type ): string {
        $attributes = [
            'href'          => '#',
            'class'         => 'estate-office-profile-link',
            'data-entity'   => $type,
            'data-entity-id'=> (string) $post->ID,
        ];

        $edit_link = get_edit_post_link( $post, 'raw' );
        if ( $edit_link ) {
            $attributes['data-edit-link'] = esc_url( $edit_link );
        }

        $attr_html = '';
        foreach ( $attributes as $key => $value ) {
            $attr_html .= sprintf( ' %1$s="%2$s"', esc_attr( $key ), esc_attr( $value ) );
        }

        return sprintf( '<a%2$s>%1$s</a>', esc_html( $label ), $attr_html );
    }

    /**
     * Renders the empty profile drawer container.
     */
    private function render_profile_drawer(): void {
        ?>
        <div class="estate-office-crm-drawer" aria-hidden="true">
            <div class="estate-office-crm-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Szczegóły rekordu CRM', 'estate-office' ); ?>">
                <button type="button" class="estate-office-crm-drawer__close" data-action="close-profile" aria-label="<?php esc_attr_e( 'Zamknij szczegóły', 'estate-office' ); ?>">&times;</button>
                <div class="estate-office-crm-drawer__header">
                    <div class="estate-office-crm-drawer__titles">
                        <h3 class="estate-office-crm-drawer__title"></h3>
                        <p class="estate-office-crm-drawer__subtitle"></p>
                        <ul class="estate-office-crm-badges" aria-live="polite"></ul>
                    </div>
                    <div class="estate-office-crm-drawer__status" aria-live="polite">
                        <span class="estate-office-crm-drawer__loading" hidden><?php esc_html_e( 'Ładowanie szczegółów…', 'estate-office' ); ?></span>
                        <span class="estate-office-crm-drawer__error" hidden></span>
                    </div>
                </div>
                <div class="estate-office-crm-drawer__body">
                    <div class="estate-office-crm-drawer__main">
                        <dl class="estate-office-crm-summary"></dl>
                        <div class="estate-office-crm-sections"></div>
                        <div class="estate-office-crm-description"></div>
                    </div>
                    <aside class="estate-office-crm-drawer__aside">
                        <div class="estate-office-crm-stage" hidden></div>
                        <div class="estate-office-crm-timeline"></div>
                        <div class="estate-office-crm-relations"></div>
                        <div class="estate-office-crm-actions"></div>
                    </aside>
                </div>
                <footer class="estate-office-crm-drawer__footer">
                    <button type="button" class="button estate-office-crm-drawer__dismiss" data-action="close-profile"><?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></button>
                </footer>
            </div>
        </div>
        <?php
    }

    /**
     * Formats price values.
     */
    private function format_price( $price ): string {
        if ( '' === $price || null === $price ) {
            return '-';
        }

        if ( is_numeric( $price ) ) {
            return number_format_i18n( (float) $price, 2 );
        }

        return (string) $price;
    }

    /**
     * Formats area values.
     */
    private function format_area( $area ): string {
        if ( '' === $area || null === $area ) {
            return '-';
        }

        $value = is_numeric( $area ) ? number_format_i18n( (float) $area, 2 ) : (string) $area;

        return sprintf( '%s m²', $value );
    }

    /**
     * Formats date values.
     */
    private function format_date( $date ): string {
        if ( ! $date ) {
            return '-';
        }

        $timestamp = strtotime( (string) $date );
        if ( false === $timestamp ) {
            return (string) $date;
        }

        return date_i18n( get_option( 'date_format', 'Y-m-d' ), $timestamp );
    }

    /**
     * Formats budget range information.
     */
    private function format_budget( $min, $max ): string {
        $has_min = '' !== $min && null !== $min;
        $has_max = '' !== $max && null !== $max;

        if ( ! $has_min && ! $has_max ) {
            return '-';
        }

        if ( $has_min && $has_max ) {
            return sprintf( '%s – %s', $this->format_price( $min ), $this->format_price( $max ) );
        }

        if ( $has_min ) {
            return sprintf( __( 'Od %s', 'estate-office' ), $this->format_price( $min ) );
        }

        return sprintf( __( 'Do %s', 'estate-office' ), $this->format_price( $max ) );
    }

    /**
     * Returns sanitized meta value with estate_office_ prefix.
     *
     * @param int    $post_id Post identifier.
     * @param string $key     Meta key without prefix.
     * @param mixed  $default Default value.
     *
     * @return mixed
     */
    private function get_meta_value( int $post_id, string $key, $default = '' ) {
        $meta_key = 'estate_office_' . $key;
        $value    = get_post_meta( $post_id, $meta_key, true );

        if ( is_array( $value ) && empty( $value ) ) {
            return $default;
        }

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Creates human readable property address.
     */
    private function format_property_address( int $post_id ): string {
        $street  = $this->get_meta_value( $post_id, 'street' );
        $number  = $this->get_meta_value( $post_id, 'building_number' );
        $unit    = $this->get_meta_value( $post_id, 'unit_number' );
        $city    = $this->get_meta_value( $post_id, 'city' );
        $postal  = $this->get_meta_value( $post_id, 'postal_code' );
        $district = $this->get_meta_value( $post_id, 'district' );
        $plot    = $this->get_meta_value( $post_id, 'plot_number' );

        $parts = [];
        $line  = trim( $street . ' ' . $number );
        if ( $unit ) {
            $line = $line ? $line . '/' . $unit : $unit;
        }
        if ( $line ) {
            $parts[] = $line;
        } elseif ( $plot ) {
            $parts[] = sprintf( __( 'Działka %s', 'estate-office' ), $plot );
        }

        if ( $district ) {
            $parts[] = $district;
        }

        $city_line = array_filter( [ $postal, $city ] );
        if ( ! empty( $city_line ) ) {
            $parts[] = implode( ' ', $city_line );
        }

        return implode( ', ', array_filter( $parts ) );
    }

    /**
     * Formats client address from meta array.
     */
    private function format_client_address( int $post_id ): string {
        $address = get_post_meta( $post_id, 'estate_office_address', true );
        if ( ! is_array( $address ) ) {
            return '';
        }

        $line = trim( ( $address['street'] ?? '' ) . ' ' . ( $address['number'] ?? '' ) );
        if ( ! empty( $address['unit'] ) ) {
            $line = $line ? $line . '/' . $address['unit'] : $address['unit'];
        }

        $parts = array_filter( [ $line, $address['postal_code'] ?? '', $address['city'] ?? '', $address['country'] ?? '' ] );

        return implode( ', ', $parts );
    }

    /**
     * Returns agent display name or fallback.
     */
    private function get_agent_name( int $user_id ): string {
        if ( $user_id <= 0 ) {
            return __( 'Nieprzypisany', 'estate-office' );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return __( 'Nieprzypisany', 'estate-office' );
        }

        return $user->display_name ?: __( 'Nieprzypisany', 'estate-office' );
    }

    /**
     * Converts transaction type slug to label.
     */
    private function translate_transaction_type( string $type ): string {
        $map = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        return $map[ $type ] ?? $type;
    }

    /**
     * Converts property type slug to label.
     */
    private function translate_property_type( string $type ): string {
        $map = [
            'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
            'dom'        => __( 'Dom', 'estate-office' ),
            'dzialka'    => __( 'Działka', 'estate-office' ),
            'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];

        return $map[ $type ] ?? $type;
    }

    /**
     * Returns label for contract stage slug.
     */
    private function get_contract_stage_label( string $stage ): string {
        $stages = [
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

        return $stages[ $stage ] ?? $stage;
    }
}
