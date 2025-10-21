<?php
/**
 * Agreements page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$action       = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
$agreement_id = isset( $_GET['agreement_id'] ) ? absint( $_GET['agreement_id'] ) : 0;
$view_id      = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

$stage_options = [
    'umowa_posrednictwa'   => __( 'Umowa Pośrednictwa', 'estate-office' ),
    'publikacja_mls'       => __( 'Publikacja w MLS', 'estate-office' ),
    'przygotowanie_oferty' => __( 'Przygotowanie oferty', 'estate-office' ),
    'publikacja_oferty'    => __( 'Publikacja oferty', 'estate-office' ),
    'marketing'            => __( 'Marketing i prezentacje', 'estate-office' ),
    'oferta_kupna'         => __( 'Oferta kupna', 'estate-office' ),
    'negocjacje'           => __( 'Negocjacje', 'estate-office' ),
    'umowa_przedwstepna'   => __( 'Umowa przedwstępna', 'estate-office' ),
    'umowa_przyrzeczona'   => __( 'Umowa przyrzeczona', 'estate-office' ),
    'przekazanie_lokalu'   => __( 'Przekazanie lokalu', 'estate-office' ),
    'umowa_zakonczona'     => __( 'Umowa zakończona', 'estate-office' ),
];

$agreement = null;
if ( $agreement_id ) {
    $agreement = EstateOffice_Database::instance()->get_agreement( $agreement_id );
}
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Umowy', 'estate-office' ); ?></h1>

    <?php if ( 'new' === $action ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-form" data-current-step="0">
            <?php wp_nonce_field( 'estate_office_agreement_nonce', 'estate_office_agreement_nonce' ); ?>
            <input type="hidden" name="action" value="estate_office_save_agreement">

            <div class="estate-office-steps">
                <div class="estate-office-step is-active">
                    <div class="estate-office-step-number">1</div>
                    <div>
                        <strong><?php esc_html_e( 'Nowa Umowa', 'estate-office' ); ?></strong>
                        <p><?php esc_html_e( 'Wprowadź szczegóły umowy.', 'estate-office' ); ?></p>
                    </div>
                </div>
                <div class="estate-office-step">
                    <div class="estate-office-step-number">2</div>
                    <div>
                        <strong><?php esc_html_e( 'Klient', 'estate-office' ); ?></strong>
                        <p><?php esc_html_e( 'Dodaj lub wybierz klienta.', 'estate-office' ); ?></p>
                    </div>
                </div>
                <div class="estate-office-step">
                    <div class="estate-office-step-number">3</div>
                    <div>
                        <strong><?php esc_html_e( 'Oferta', 'estate-office' ); ?></strong>
                        <p><?php esc_html_e( 'Dodaj nieruchomość lub poszukiwanie.', 'estate-office' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="estate-office-form-step is-active">
                <div class="estate-office-form-row">
                    <div>
                        <label for="contract_number"><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></label>
                        <input type="text" name="contract_number" id="contract_number" required>
                    </div>
                    <div>
                        <label for="transaction_type"><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></label>
                        <select name="transaction_type" id="transaction_type" required>
                            <option value="SPRZEDAŻ"><?php esc_html_e( 'Sprzedaż', 'estate-office' ); ?></option>
                            <option value="KUPNO"><?php esc_html_e( 'Kupno', 'estate-office' ); ?></option>
                            <option value="WYNAJEM"><?php esc_html_e( 'Wynajem', 'estate-office' ); ?></option>
                            <option value="NAJEM"><?php esc_html_e( 'Najem', 'estate-office' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <label for="start_date"><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></label>
                        <input type="date" name="start_date" id="start_date" required>
                    </div>
                    <div>
                        <label for="end_date"><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></label>
                        <input type="date" name="end_date" id="end_date" data-toggle-target>
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Umowa bezterminowa', 'estate-office' ); ?></label>
                        <label><input type="checkbox" name="open_ended" value="1" data-toggle="open-ended" data-target="#end_date"> <?php esc_html_e( 'Tak', 'estate-office' ); ?></label>
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <label for="commission_amount"><?php esc_html_e( 'Wysokość prowizji', 'estate-office' ); ?></label>
                        <input type="number" step="0.01" min="0" name="commission_amount" id="commission_amount">
                    </div>
                    <div>
                        <label for="commission_unit"><?php esc_html_e( 'Jednostka', 'estate-office' ); ?></label>
                        <select name="commission_unit" id="commission_unit">
                            <option value="%">%</option>
                            <option value="PLN">PLN</option>
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <button type="button" class="button button-secondary" data-action="next-step"><?php esc_html_e( 'Dalej', 'estate-office' ); ?></button>
                    </div>
                </div>
            </div>
        </form>
    <?php elseif ( 'add-client' === $action && $agreement ) :
        $clients = $wpdb->get_results( "SELECT id, first_name, last_name, company_name FROM {$wpdb->prefix}eo_clients ORDER BY created_at DESC", ARRAY_A );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-form">
            <?php wp_nonce_field( 'estate_office_client_nonce', 'estate_office_client_nonce' ); ?>
            <input type="hidden" name="action" value="estate_office_save_client">
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr( $agreement_id ); ?>">

            <div class="estate-office-form-step is-active">
                <h2><?php esc_html_e( 'Dodaj klienta do umowy', 'estate-office' ); ?></h2>
                <p><?php printf( esc_html__( 'Umowa nr %s', 'estate-office' ), esc_html( $agreement['contract_number'] ) ); ?></p>

                <div class="estate-office-form-row">
                    <div>
                        <label for="existing_client_id"><?php esc_html_e( 'Wybierz istniejącego klienta', 'estate-office' ); ?></label>
                        <select name="existing_client_id" id="existing_client_id">
                            <option value="0"><?php esc_html_e( 'Dodaj nowego klienta', 'estate-office' ); ?></option>
                            <?php foreach ( $clients as $client ) :
                                $label = $client['company_name'] ?: trim( $client['first_name'] . ' ' . $client['last_name'] );
                                ?>
                                <option value="<?php echo esc_attr( $client['id'] ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <label for="client_type"><?php esc_html_e( 'Typ klienta', 'estate-office' ); ?></label>
                        <select name="client_type" id="client_type" data-client-type>
                            <option value="person"><?php esc_html_e( 'Osoba fizyczna', 'estate-office' ); ?></option>
                            <option value="company"><?php esc_html_e( 'Firma', 'estate-office' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="estate-office-form-row" data-client-fields="person">
                    <div>
                        <label for="first_name"><?php esc_html_e( 'Imię', 'estate-office' ); ?></label>
                        <input type="text" name="first_name" id="first_name">
                    </div>
                    <div>
                        <label for="last_name"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?></label>
                        <input type="text" name="last_name" id="last_name">
                    </div>
                </div>

                <div class="estate-office-form-row" data-client-fields="company" style="display:none;">
                    <div>
                        <label for="company_name"><?php esc_html_e( 'Nazwa firmy', 'estate-office' ); ?></label>
                        <input type="text" name="company_name" id="company_name">
                    </div>
                    <div>
                        <label for="representative"><?php esc_html_e( 'Imię i nazwisko reprezentanta', 'estate-office' ); ?></label>
                        <input type="text" name="representative" id="representative">
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <label for="phone"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label>
                        <input type="text" name="phone" id="phone">
                    </div>
                    <div>
                        <label for="email"><?php esc_html_e( 'E-mail', 'estate-office' ); ?></label>
                        <input type="email" name="email" id="email">
                    </div>
                    <div data-client-fields="company" style="display:none;">
                        <label for="website"><?php esc_html_e( 'Strona WWW', 'estate-office' ); ?></label>
                        <input type="url" name="website" id="website">
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <label><?php esc_html_e( 'Dane identyfikacyjne', 'estate-office' ); ?></label>
                        <textarea name="identification[details]" rows="3"></textarea>
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Adres zamieszkania', 'estate-office' ); ?></label>
                        <textarea name="address[primary]" rows="3"></textarea>
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Adres korespondencyjny', 'estate-office' ); ?></label>
                        <textarea name="correspondence[alt]" rows="3"></textarea>
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <div>
                        <label><?php esc_html_e( 'Czy dodać kolejnego klienta?', 'estate-office' ); ?></label>
                        <label><input type="radio" name="add_another" value="yes"> <?php esc_html_e( 'Tak', 'estate-office' ); ?></label>
                        <label><input type="radio" name="add_another" value="no" checked> <?php esc_html_e( 'Nie', 'estate-office' ); ?></label>
                    </div>
                </div>

                <div class="estate-office-form-row">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Zapisz klienta', 'estate-office' ); ?></button>
                </div>
            </div>
        </form>
    <?php elseif ( 'add-property' === $action && $agreement ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-form">
            <?php wp_nonce_field( 'estate_office_property_nonce', 'estate_office_property_nonce' ); ?>
            <input type="hidden" name="action" value="estate_office_save_property">
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr( $agreement_id ); ?>">
            <input type="hidden" name="transaction_type" value="<?php echo esc_attr( $agreement['transaction_type'] ); ?>">

            <h2><?php esc_html_e( 'Dodaj nieruchomość', 'estate-office' ); ?></h2>
            <p><?php printf( esc_html__( 'Umowa nr %s (%s)', 'estate-office' ), esc_html( $agreement['contract_number'] ), esc_html( $agreement['transaction_type'] ) ); ?></p>

            <div class="estate-office-form-row">
                <div>
                    <label for="property_type"><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></label>
                    <select name="property_type" id="property_type" data-property-type>
                        <option value="MIESZKANIE"><?php esc_html_e( 'Mieszkanie', 'estate-office' ); ?></option>
                        <option value="DOM"><?php esc_html_e( 'Dom', 'estate-office' ); ?></option>
                        <option value="DZIAŁKA"><?php esc_html_e( 'Działka', 'estate-office' ); ?></option>
                        <option value="LOKAL"><?php esc_html_e( 'Lokal H/U', 'estate-office' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="legal_status"><?php esc_html_e( 'Stan prawny', 'estate-office' ); ?></label>
                    <select name="legal_status" id="legal_status">
                        <option value="wlasnosc"><?php esc_html_e( 'Własność', 'estate-office' ); ?></option>
                        <option value="wspolwlasnosc"><?php esc_html_e( 'Współwłasność', 'estate-office' ); ?></option>
                        <option value="spoldzielcze"><?php esc_html_e( 'Spółdzielcze własnościowe', 'estate-office' ); ?></option>
                        <option value="dzierzawa"><?php esc_html_e( 'Dzierżawa', 'estate-office' ); ?></option>
                        <option value="inne"><?php esc_html_e( 'Inne', 'estate-office' ); ?></option>
                    </select>
                </div>
            </div>

            <fieldset>
                <legend><?php esc_html_e( 'Adres', 'estate-office' ); ?></legend>
                <div class="estate-office-form-row">
                    <div>
                        <label><?php esc_html_e( 'Ulica', 'estate-office' ); ?></label>
                        <input type="text" name="address[street]">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Numer', 'estate-office' ); ?></label>
                        <input type="text" name="address[number]">
                    </div>
                    <div data-property-type-visible="MIESZKANIE,LOKAL">
                        <label><?php esc_html_e( 'Lokal', 'estate-office' ); ?></label>
                        <input type="text" name="address[flat]">
                    </div>
                </div>
                <div class="estate-office-form-row">
                    <div>
                        <label><?php esc_html_e( 'Kod pocztowy', 'estate-office' ); ?></label>
                        <input type="text" name="address[zip]">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Miasto', 'estate-office' ); ?></label>
                        <input type="text" name="address[city]">
                    </div>
                    <div data-property-type-visible="MIESZKANIE,LOKAL">
                        <label><?php esc_html_e( 'Dzielnica', 'estate-office' ); ?></label>
                        <input type="text" name="address[district]">
                    </div>
                    <div data-property-type-visible="DOM,DZIAŁKA" style="display:none;">
                        <label><?php esc_html_e( 'Powiat', 'estate-office' ); ?></label>
                        <input type="text" name="address[county]">
                    </div>
                </div>
                <div class="estate-office-form-row" data-property-type-visible="DOM,DZIAŁKA" style="display:none;">
                    <div>
                        <label><?php esc_html_e( 'Obręb', 'estate-office' ); ?></label>
                        <input type="text" name="address[precinct]">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Numer działki', 'estate-office' ); ?></label>
                        <input type="text" name="address[parcel]">
                    </div>
                </div>
            </fieldset>

            <div class="estate-office-form-row">
                <div>
                    <label><?php esc_html_e( 'Numer Księgi Wieczystej', 'estate-office' ); ?></label>
                    <input type="text" name="registry_number">
                </div>
                <div>
                    <label><input type="checkbox" name="details[no_kw]" value="1"> <?php esc_html_e( 'Brak KW', 'estate-office' ); ?></label>
                </div>
            </div>

            <fieldset>
                <legend><?php esc_html_e( 'Parametry finansowe', 'estate-office' ); ?></legend>
                <div class="estate-office-form-row">
                    <div>
                        <label><?php esc_html_e( 'Cena', 'estate-office' ); ?></label>
                        <input type="number" step="0.01" name="price" data-calc="price">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Czynsz administracyjny', 'estate-office' ); ?></label>
                        <input type="number" step="0.01" name="rent">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Powierzchnia (m²)', 'estate-office' ); ?></label>
                        <input type="number" step="0.01" name="area" data-calc="area">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></label>
                        <input type="number" step="0.01" name="price_per_sqm" data-calc="price-per-sqm" readonly>
                    </div>
                </div>
            </fieldset>

            <fieldset data-property-type-visible="DOM,MIESZKANIE,LOKAL">
                <legend><?php esc_html_e( 'Szczegóły', 'estate-office' ); ?></legend>
                <div class="estate-office-form-row">
                    <div>
                        <label><?php esc_html_e( 'Rok budowy', 'estate-office' ); ?></label>
                        <input type="number" name="details[year]">
                    </div>
                    <div data-property-type-visible="MIESZKANIE,LOKAL">
                        <label><?php esc_html_e( 'Piętro', 'estate-office' ); ?></label>
                        <input type="number" name="details[floor]">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></label>
                        <input type="number" name="details[rooms]">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Liczba łazienek', 'estate-office' ); ?></label>
                        <input type="number" name="details[bathrooms]">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e( 'Opis', 'estate-office' ); ?></legend>
                <?php
                wp_editor( '', 'property_description', [
                    'textarea_name' => 'description',
                    'textarea_rows' => 6,
                ] );
                ?>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e( 'Znaczniki', 'estate-office' ); ?></legend>
                <div class="estate-office-form-row">
                    <label><input type="checkbox" name="tags[new_offer]" value="1"> <?php esc_html_e( 'Nowa oferta', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[exclusive]" value="1"> <?php esc_html_e( 'Wyłączność', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[sold]" value="1"> <?php esc_html_e( 'Sprzedane', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[rented]" value="1"> <?php esc_html_e( 'Wynajęte', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[new_price]" value="1"> <?php esc_html_e( 'Nowa cena', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[no_commission]" value="1"> <?php esc_html_e( 'Bez prowizji', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[mls]" value="1"> <?php esc_html_e( 'Oferta MLS', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="tags[premium]" value="1"> <?php esc_html_e( 'Premium', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="export_www" value="1"> <?php esc_html_e( 'Eksport na WWW', 'estate-office' ); ?></label>
                    <label><input type="checkbox" name="export_portals" value="1" disabled> <?php esc_html_e( 'Eksport na portale (wkrótce)', 'estate-office' ); ?></label>
                </div>
            </fieldset>

            <div class="estate-office-form-row">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj nieruchomość', 'estate-office' ); ?></button>
            </div>
        </form>
    <?php elseif ( 'add-search' === $action && $agreement ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-form">
            <?php wp_nonce_field( 'estate_office_search_nonce', 'estate_office_search_nonce' ); ?>
            <input type="hidden" name="action" value="estate_office_save_search">
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr( $agreement_id ); ?>">
            <input type="hidden" name="transaction_type" value="<?php echo esc_attr( $agreement['transaction_type'] ); ?>">

            <h2><?php esc_html_e( 'Dodaj poszukiwanie', 'estate-office' ); ?></h2>
            <p><?php printf( esc_html__( 'Umowa nr %s (%s)', 'estate-office' ), esc_html( $agreement['contract_number'] ), esc_html( $agreement['transaction_type'] ) ); ?></p>

            <div class="estate-office-form-row">
                <div>
                    <label><?php esc_html_e( 'Budżet (od)', 'estate-office' ); ?></label>
                    <input type="number" name="criteria[budget_from]" step="0.01">
                </div>
                <div>
                    <label><?php esc_html_e( 'Budżet (do)', 'estate-office' ); ?></label>
                    <input type="number" name="criteria[budget_to]" step="0.01">
                </div>
                <div>
                    <label><?php esc_html_e( 'Metraż (od)', 'estate-office' ); ?></label>
                    <input type="number" name="criteria[area_from]" step="0.01">
                </div>
                <div>
                    <label><?php esc_html_e( 'Metraż (do)', 'estate-office' ); ?></label>
                    <input type="number" name="criteria[area_to]" step="0.01">
                </div>
            </div>

            <div class="estate-office-form-row">
                <div>
                    <label><?php esc_html_e( 'Liczba pokoi (od)', 'estate-office' ); ?></label>
                    <input type="number" name="criteria[rooms_from]">
                </div>
                <div>
                    <label><?php esc_html_e( 'Liczba pokoi (do)', 'estate-office' ); ?></label>
                    <input type="number" name="criteria[rooms_to]">
                </div>
                <div>
                    <label><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></label>
                    <input type="text" name="criteria[location]">
                </div>
            </div>

            <fieldset>
                <legend><?php esc_html_e( 'Opis poszukiwania', 'estate-office' ); ?></legend>
                <?php
                wp_editor( '', 'search_description', [
                    'textarea_name' => 'description',
                    'textarea_rows' => 6,
                ] );
                ?>
            </fieldset>

            <div class="estate-office-form-row">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj poszukiwanie', 'estate-office' ); ?></button>
            </div>
        </form>
    <?php elseif ( $view_id ) :
        $current_agreement = EstateOffice_Database::instance()->get_agreement( $view_id );
        if ( ! $current_agreement ) : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'Nie znaleziono wybranej umowy.', 'estate-office' ); ?></p></div>
        <?php else :
        $clients_q = $wpdb->get_results( $wpdb->prepare( "SELECT c.* FROM {$wpdb->prefix}eo_clients c INNER JOIN {$wpdb->prefix}eo_agreement_clients ac ON ac.client_id = c.id WHERE ac.agreement_id = %d", $view_id ), ARRAY_A );
        $properties_q = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eo_properties WHERE agreement_id = %d", $view_id ), ARRAY_A );
        $searches_q = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eo_searches WHERE agreement_id = %d", $view_id ), ARRAY_A );
        $history = json_decode( $current_agreement['stage_history'] ?? '[]', true );
        ?>
        <div class="estate-office-section">
            <h2><?php esc_html_e( 'Szczegóły umowy', 'estate-office' ); ?></h2>
            <div class="estate-office-grid">
                <div class="estate-office-card">
                    <p><strong><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?>:</strong> <?php echo esc_html( $current_agreement['contract_number'] ); ?></p>
                    <p><strong><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?>:</strong> <?php echo esc_html( $current_agreement['transaction_type'] ); ?></p>
                    <p><strong><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?>:</strong> <?php echo esc_html( $current_agreement['start_date'] ); ?></p>
                    <p><strong><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?>:</strong> <?php echo esc_html( $current_agreement['end_date'] ?: '—' ); ?></p>
                    <p><strong><?php esc_html_e( 'Prowizja', 'estate-office' ); ?>:</strong> <?php echo esc_html( $current_agreement['commission_amount'] ); ?> <?php echo esc_html( $current_agreement['commission_unit'] ); ?></p>
                </div>
                <div class="estate-office-card estate-office-stage">
                    <form class="estate-office-stage-form" data-agreement-id="<?php echo esc_attr( $view_id ); ?>">
                        <div class="estate-office-form-row">
                            <div>
                                <label for="stage"><?php esc_html_e( 'Aktualny etap', 'estate-office' ); ?></label>
                                <select name="stage">
                                    <?php foreach ( $stage_options as $stage_key => $stage_label ) : ?>
                                        <option value="<?php echo esc_attr( $stage_key ); ?>" <?php selected( $current_agreement['stage'], $stage_key ); ?>><?php echo esc_html( $stage_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="stage_date"><?php esc_html_e( 'Data etapu', 'estate-office' ); ?></label>
                                <input type="date" name="stage_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                            </div>
                        </div>
                        <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Aktualizuj etap', 'estate-office' ); ?></button></p>
                    </form>
                    <div class="estate-office-stage-history">
                        <h4><?php esc_html_e( 'Historia etapów', 'estate-office' ); ?></h4>
                        <ul>
                            <?php if ( ! empty( $history ) ) : ?>
                                <?php foreach ( $history as $entry ) : ?>
                                    <li><?php echo esc_html( $entry['date'] ?? '' ); ?> — <?php echo esc_html( $stage_options[ $entry['stage'] ] ?? $entry['stage'] ); ?></li>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <li><?php esc_html_e( 'Brak historii etapów.', 'estate-office' ); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="estate-office-section">
            <h2><?php esc_html_e( 'Klienci powiązani', 'estate-office' ); ?></h2>
            <ul>
                <?php foreach ( $clients_q as $client ) :
                    $label = $client['type'] === 'company' ? $client['company_name'] : trim( $client['first_name'] . ' ' . $client['last_name'] );
                    ?>
                    <li><?php echo esc_html( $label ); ?> — <?php echo esc_html( $client['email'] ); ?></li>
                <?php endforeach; ?>
                <?php if ( empty( $clients_q ) ) : ?>
                    <li><?php esc_html_e( 'Brak klientów powiązanych z umową.', 'estate-office' ); ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="estate-office-section">
            <h2><?php esc_html_e( 'Powiązane oferty', 'estate-office' ); ?></h2>
            <ul>
                <?php foreach ( $properties_q as $property ) : ?>
                    <li><?php echo esc_html( estate_office_format_address( $property['address'] ) ); ?></li>
                <?php endforeach; ?>
                <?php foreach ( $searches_q as $search ) :
                    $criteria = json_decode( $search['criteria'], true );
                    ?>
                    <li><?php echo esc_html( sprintf( '%s — %s', $search['transaction_type'], $criteria['location'] ?? '-' ) ); ?></li>
                <?php endforeach; ?>
                <?php if ( empty( $properties_q ) && empty( $searches_q ) ) : ?>
                    <li><?php esc_html_e( 'Brak powiązanych ofert.', 'estate-office' ); ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <p><a class="estate-office-button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agreements' ) ); ?>"><?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a></p>
        <?php endif; ?>
    <?php else :
        $agreements = $wpdb->get_results(
            "SELECT a.*, p.property_type, p.address, p.id AS property_id
            FROM {$wpdb->prefix}eo_agreements a
            LEFT JOIN {$wpdb->prefix}eo_properties p ON p.agreement_id = a.id
            GROUP BY a.id
            ORDER BY a.created_at DESC",
            ARRAY_A
        );
        ?>
        <div class="estate-office-toolbar">
            <a class="estate-office-button-primary" href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-agreements', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?></a>
            <input type="search" class="regular-text search-field" placeholder="<?php esc_attr_e( 'Szukaj...', 'estate-office' ); ?>" data-action="filter-table" data-target="#estate-office-agreements-table">
        </div>

        <table class="estate-office-table" id="estate-office-agreements-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Aktualny etap', 'estate-office' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $agreements ) : ?>
                <?php foreach ( $agreements as $row ) :
                    $address = $row['address'] ? estate_office_format_address( $row['address'] ) : '-';
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-agreements', 'view' => (int) $row['id'] ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $row['contract_number'] ); ?></a></td>
                        <td><?php echo esc_html( strtoupper( $row['transaction_type'] ) ); ?></td>
                        <td><?php echo esc_html( $row['property_type'] ?? '-' ); ?></td>
                        <td>
                            <?php if ( $row['property_id'] ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-properties', 'view' => (int) $row['property_id'] ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $address ); ?></a>
                            <?php else : ?>
                                <?php echo esc_html( $address ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row['start_date'] ); ?></td>
                        <td><?php echo esc_html( $row['end_date'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( $stage_options[ $row['stage'] ] ?? $row['stage'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'Brak umów do wyświetlenia.', 'estate-office' ); ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
