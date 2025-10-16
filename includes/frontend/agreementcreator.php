<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\PostTypes\AgreementMeta;
use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\ClientMeta;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchMeta;
use EstateOffice\PostTypes\SearchRegister;
use EstateOffice\Settings\GeneralSettings;
use WP_Error;
use WP_Post;
use WP_Query;

use function __;
use function absint;
use function add_action;
use function add_query_arg;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_the_title;
use function get_user_by;
use function is_array;
use function is_wp_error;
use function is_user_logged_in;
use function plugins_url;
use function sanitize_text_field;
use function sanitize_title;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_post_terms;
use function wp_insert_term;
use function wp_insert_post;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_kses_post;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_set_post_terms;
use function wp_unslash;
use function wp_update_post;
use function wp_strip_all_tags;
use function term_exists;
use function update_post_meta;
use function wp_list_pluck;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

final class AgreementCreator
{
    private const NONCE_ACTION = 'estate_office_agreement_creator';
    private const STYLE_HANDLE = 'estate-office-agreement-creator';
    private const SCRIPT_HANDLE = 'estate-office-agreement-creator';
    private const PROPERTY_TRANSACTION_TYPES = ['sale', 'rent_out'];
    private const SEARCH_TRANSACTION_TYPES = ['purchase', 'lease'];

    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);

        add_action('wp_ajax_estate_office_create_agreement', [self::class, 'handleCreateAgreement']);
        add_action('wp_ajax_estate_office_search_clients', [self::class, 'handleSearchClients']);
        add_action('wp_ajax_estate_office_attach_client', [self::class, 'handleAttachClient']);
        add_action('wp_ajax_estate_office_detach_client', [self::class, 'handleDetachClient']);
        add_action('wp_ajax_estate_office_create_client', [self::class, 'handleCreateClient']);
        add_action('wp_ajax_estate_office_create_property', [self::class, 'handleCreateProperty']);
        add_action('wp_ajax_estate_office_create_search', [self::class, 'handleCreateSearch']);
        add_action('wp_ajax_estate_office_search_properties', [self::class, 'handleSearchProperties']);
        add_action('wp_ajax_estate_office_attach_property', [self::class, 'handleAttachProperty']);
        add_action('wp_ajax_estate_office_search_searches', [self::class, 'handleSearchSearches']);
        add_action('wp_ajax_estate_office_attach_search', [self::class, 'handleAttachSearch']);
        add_action('wp_ajax_estate_office_detach_record', [self::class, 'handleDetachRecord']);
        add_action('wp_ajax_estate_office_finalize_agreement', [self::class, 'handleFinalize']);
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            self::STYLE_HANDLE,
            plugins_url('assets/css/agreement-creator.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            plugins_url('assets/js/agreement-creator.js', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        $data = [
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce(self::NONCE_ACTION),
            'baseUrl'          => CRM::getCrmBaseUrl(),
            'sectionParam'     => CRM::SECTION_PARAM,
            'recordParam'      => CRM::RECORD_PARAM,
            'recordIdParam'    => CRM::RECORD_ID_PARAM,
            'propertyTransactions' => self::PROPERTY_TRANSACTION_TYPES,
            'searchTransactions'   => self::SEARCH_TRANSACTION_TYPES,
            'transactionLabels'    => AgreementMeta::TRANSACTION_TYPES,
            'step3' => [
                'propertyTitle'       => esc_html__('Dodaj nieruchomość powiązaną z umową', 'estate-office'),
                'propertyDescription' => esc_html__('Uzupełnij kluczowe dane nieruchomości, aby powiązać ją z umową.', 'estate-office'),
                'searchTitle'         => esc_html__('Dodaj poszukiwanie powiązane z umową', 'estate-office'),
                'searchDescription'   => esc_html__('Opisz wymagania klienta, aby zakończyć tworzenie umowy.', 'estate-office'),
            ],
            'messages'         => [
                'step1Success'   => esc_html__('Umowa została zapisana. Dodaj klientów, aby kontynuować.', 'estate-office'),
                'step1Error'     => esc_html__('Nie udało się zapisać danych umowy. Sprawdź poprawność pól.', 'estate-office'),
                'clientAdded'    => esc_html__('Klient został przypisany do umowy.', 'estate-office'),
                'clientRemoved'  => esc_html__('Klient został odłączony od umowy.', 'estate-office'),
                'clientCreated'  => esc_html__('Nowy klient został zapisany i przypisany do umowy.', 'estate-office'),
                'propertySuccess'=> esc_html__('Nieruchomość została utworzona i powiązana z umową.', 'estate-office'),
                'propertyAttached' => esc_html__('Nieruchomość została przypisana do umowy.', 'estate-office'),
                'propertyDetached' => esc_html__('Powiązanie nieruchomości z umową zostało usunięte.', 'estate-office'),
                'propertyError'  => esc_html__('Nie udało się zapisać nieruchomości. Uzupełnij wymagane pola.', 'estate-office'),
                'searchSuccess'  => esc_html__('Poszukiwanie zostało utworzone i powiązane z umową.', 'estate-office'),
                'searchAttached' => esc_html__('Poszukiwanie zostało przypisane do umowy.', 'estate-office'),
                'searchDetached' => esc_html__('Powiązanie poszukiwania z umową zostało usunięte.', 'estate-office'),
                'searchError'    => esc_html__('Nie udało się zapisać poszukiwania. Uzupełnij wymagane pola.', 'estate-office'),
                'finalizeSuccess'=> esc_html__('Umowa została utworzona. Możesz przejść do jej szczegółów.', 'estate-office'),
                'genericError'   => esc_html__('Wystąpił błąd podczas przetwarzania żądania. Spróbuj ponownie.', 'estate-office'),
                'missingClients' => esc_html__('Dodaj przynajmniej jednego klienta, aby kontynuować.', 'estate-office'),
                'emptyClients'   => esc_html__('Brak przypisanych klientów. Dodaj klienta, aby kontynuować.', 'estate-office'),
                'noResults'      => esc_html__('Brak dopasowanych klientów.', 'estate-office'),
                'viewAgreement'  => esc_html__('Przejdź do szczegółów umowy', 'estate-office'),
                'viewRecord'     => esc_html__('Otwórz rekord w CRM', 'estate-office'),
                'recordChange'   => esc_html__('Wybierz inny rekord', 'estate-office'),
                'recordMissingProperty' => esc_html__('Dodaj lub wybierz nieruchomość, aby zakończyć kreator.', 'estate-office'),
                'recordMissingSearch'   => esc_html__('Dodaj lub wybierz poszukiwanie, aby zakończyć kreator.', 'estate-office'),
                'clientsSummarySingular' => esc_html__('%d klient przypisany do umowy.', 'estate-office'),
                'clientsSummaryPlural'   => esc_html__('%d klientów przypisanych do umowy.', 'estate-office'),
                'recordLabelProperty'    => esc_html__('Nieruchomość', 'estate-office'),
                'recordLabelSearch'      => esc_html__('Poszukiwanie', 'estate-office'),
                'recordSelect'           => esc_html__('Wybierz', 'estate-office'),
            ],
            'placeholders'    => [
                'propertySearch' => esc_attr__('Numer oferty, adres lub opiekun', 'estate-office'),
                'searchSearch'   => esc_attr__('Numer poszukiwania, lokalizacja lub opiekun', 'estate-office'),
            ],
        ];

        wp_localize_script(self::SCRIPT_HANDLE, 'EstateOfficeAgreementCreator', $data);
    }

    public static function renderModal(): void
    {
        echo '<div class="estate-office-agreement-creator" data-eo-agreement-creator hidden>';
        echo '<div class="estate-office-agreement-creator__backdrop" data-eo-agreement-close></div>';
        echo '<div class="estate-office-agreement-creator__dialog">';
        echo '<button type="button" class="estate-office-agreement-creator__close" data-eo-agreement-close aria-label="' . esc_html__('Zamknij kreator', 'estate-office') . '">×</button>';
        echo '<div class="estate-office-agreement-creator__steps" data-eo-agreement-steps>';
        echo '<div class="is-active" data-step="1">' . esc_html__('1. Szczegóły umowy', 'estate-office') . '</div>';
        echo '<div data-step="2">' . esc_html__('2. Klienci', 'estate-office') . '</div>';
        echo '<div data-step="3">' . esc_html__('3. Nieruchomość / Poszukiwanie', 'estate-office') . '</div>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__content" data-eo-agreement-content>';
        self::renderStepAgreement();
        self::renderStepClients();
        self::renderStepSummary();
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private static function renderStepAgreement(): void
    {
        $agreementDynamic = GeneralSettings::getAgreementDynamicFields();

        echo '<form class="estate-office-agreement-creator__form is-active" data-eo-agreement-step="1">';
        echo '<h2>' . esc_html__('Szczegóły umowy', 'estate-office') . '</h2>';
        echo '<p class="description">' . esc_html__('Uzupełnij podstawowe dane nowej umowy, a następnie przejdź do dodawania klientów.', 'estate-office') . '</p>';
        echo '<div class="estate-office-agreement-creator__grid">';
        echo '<label>' . esc_html__('Numer umowy', 'estate-office') . '<span class="required">*</span><input type="text" name="estate_agreement_number" required /></label>';
        echo '<label>' . esc_html__('Typ transakcji', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="estate_agreement_transaction_type" required>';
        echo '<option value="">' . esc_html__('Wybierz typ', 'estate-office') . '</option>';
        foreach (AgreementMeta::TRANSACTION_TYPES as $key => $label) {
            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Data zawarcia', 'estate-office') . '<input type="date" name="estate_agreement_start_date" /></label>';
        echo '<label>' . esc_html__('Data zakończenia', 'estate-office') . '<input type="date" name="estate_agreement_end_date" /></label>';
        echo '<label class="estate-office-agreement-creator__checkbox"><input type="checkbox" name="estate_agreement_is_indefinite" value="1" />' . esc_html__('Umowa bezterminowa', 'estate-office') . '</label>';
        echo '<label>' . esc_html__('Wysokość prowizji', 'estate-office') . '<input type="number" name="estate_agreement_commission_amount" step="0.01" min="0" /></label>';
        echo '<label>' . esc_html__('Jednostka prowizji', 'estate-office') . '<select name="estate_agreement_commission_unit">';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        foreach (AgreementMeta::COMMISSION_UNITS as $key => $label) {
            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '</div>';
        if (!empty($agreementDynamic)) {
            echo '<div class="estate-office-agreement-creator__dynamic">';
            echo '<p class="estate-office-agreement-creator__dynamic-title">' . esc_html__('Pola dodatkowe umowy', 'estate-office') . '</p>';
            echo '<div class="estate-office-agreement-creator__grid">';
            foreach ($agreementDynamic as $field) {
                $key   = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? '');

                if ($key === '' || $label === '') {
                    continue;
                }

                echo '<label>' . esc_html($label) . '<input type="text" name="estate_agreement_dynamic[' . esc_attr($key) . ']" autocomplete="off" /></label>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Zarządzaj listą pól w sekcji „Pola umów” ustawień wtyczki.', 'estate-office') . '</p>';
            echo '</div>';
        }

        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="submit" class="estate-office-agreement-creator__primary">' . esc_html__('Zapisz i przejdź dalej', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__message" data-eo-agreement-message></div>';
        echo '</form>';
    }

    private static function renderStepClients(): void
    {
        echo '<div class="estate-office-agreement-creator__form" data-eo-agreement-step="2" hidden>';
        echo '<h2>' . esc_html__('Klienci powiązani z umową', 'estate-office') . '</h2>';
        echo '<p class="description">' . esc_html__('Wyszukaj istniejących klientów lub dodaj nowych. Co najmniej jeden klient jest wymagany.', 'estate-office') . '</p>';
        echo '<div class="estate-office-agreement-creator__clients">';
        echo '<div class="estate-office-agreement-creator__clients-list" data-eo-agreement-selected></div>';
        echo '<div class="estate-office-agreement-creator__search">';
        echo '<label>' . esc_html__('Wyszukaj klienta', 'estate-office') . '<input type="search" placeholder="' . esc_attr__('Imię, nazwisko, telefon lub e-mail', 'estate-office') . '" data-eo-agreement-client-search /></label>';
        echo '<div class="estate-office-agreement-creator__search-results" data-eo-agreement-search-results></div>';
        echo '</div>';
        echo '</div>';
        echo '<details class="estate-office-agreement-creator__new-client">';
        echo '<summary>' . esc_html__('Dodaj nowego klienta', 'estate-office') . '</summary>';
        echo '<form data-eo-agreement-new-client>'; // nested form? can't have form inside form, but step 2 container is div not form so ok.
        echo '<div class="estate-office-agreement-creator__grid">';
        echo '<label>' . esc_html__('Typ klienta', 'estate-office') . '<select name="estate_client_type">';
        foreach (ClientMeta::CLIENT_TYPES as $key => $label) {
            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>' . esc_html__('Imię', 'estate-office') . '<input type="text" name="estate_client_first_name" /></label>';
        echo '<label>' . esc_html__('Nazwisko', 'estate-office') . '<input type="text" name="estate_client_last_name" /></label>';
        echo '<label>' . esc_html__('Nazwa firmy', 'estate-office') . '<input type="text" name="estate_client_company_name" /></label>';
        echo '<label>' . esc_html__('Przedstawiciel firmy', 'estate-office') . '<input type="text" name="estate_client_company_representative" /></label>';
        echo '<label>' . esc_html__('Telefon', 'estate-office') . '<input type="tel" name="estate_client_phone" /></label>';
        echo '<label>' . esc_html__('E-mail', 'estate-office') . '<input type="email" name="estate_client_email" /></label>';
        echo '<label>' . esc_html__('Strona WWW', 'estate-office') . '<input type="url" name="estate_client_website" /></label>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="submit" class="estate-office-agreement-creator__secondary">' . esc_html__('Zapisz klienta', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__message" data-eo-agreement-client-message></div>';
        echo '</form>';
        echo '</details>';
        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="button" class="estate-office-agreement-creator__primary" data-eo-agreement-next>' . esc_html__('Zakończ i przejdź dalej', 'estate-office') . '</button>';
        echo '<button type="button" class="estate-office-agreement-creator__ghost" data-eo-agreement-back>' . esc_html__('Wróć', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__message" data-eo-agreement-message></div>';
        echo '</div>';
    }

    private static function renderStepSummary(): void
    {
        $propertyTypes       = self::getPropertyTypes();
        $propertyDynamic     = GeneralSettings::getPropertyDynamicFields();
        $searchDynamic       = GeneralSettings::getSearchDynamicFields();

        echo '<div class="estate-office-agreement-creator__form" data-eo-agreement-step="3" hidden>';
        echo '<h2 data-eo-agreement-step3-heading>' . esc_html__('Dodaj nieruchomość lub poszukiwanie', 'estate-office') . '</h2>';
        echo '<p class="description" data-eo-agreement-step3-description>' . esc_html__('Po dodaniu klientów wybierz odpowiedni formularz, aby zakończyć proces.', 'estate-office') . '</p>';

        echo '<form class="estate-office-agreement-creator__block" data-eo-agreement-property hidden>';
        echo '<input type="hidden" name="property[transaction_type]" value="" data-eo-agreement-property-transaction />';
        echo '<input type="hidden" name="property[estate_property_manager]" value="' . esc_attr((string) get_current_user_id()) . '" />';
        echo '<div class="estate-office-agreement-creator__existing">';
        echo '<h3>' . esc_html__('Wybierz istniejącą nieruchomość', 'estate-office') . '</h3>';
        echo '<p class="description">' . esc_html__('Wyszukaj numer oferty, adres lub opiekuna, aby powiązać już dodaną nieruchomość.', 'estate-office') . '</p>';
        echo '<div class="estate-office-agreement-creator__search">';
        echo '<input type="search" placeholder="' . esc_attr__('Numer oferty, adres lub opiekun', 'estate-office') . '" data-eo-agreement-property-search />';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__search-results" data-eo-agreement-property-results></div>';
        echo '<div class="estate-office-agreement-creator__divider"><span>' . esc_html__('lub dodaj nową nieruchomość', 'estate-office') . '</span></div>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__grid">';
        echo '<label>' . esc_html__('Numer oferty', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_reference]" required /></label>';
        echo '<label>' . esc_html__('Typ nieruchomości', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="property[property_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        foreach ($propertyTypes as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Ulica', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_street]" required /></label>';
        echo '<label>' . esc_html__('Numer', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_number]" required /></label>';
        echo '<label>' . esc_html__('Lokal', 'estate-office') . '<input type="text" name="property[estate_property_unit]" /></label>';
        echo '<label>' . esc_html__('Kod pocztowy', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_postal_code]" required /></label>';
        echo '<label>' . esc_html__('Miasto', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_city]" required /></label>';
        echo '<label>' . esc_html__('Dzielnica', 'estate-office') . '<input type="text" name="property[estate_property_district]" /></label>';
        echo '<label>' . esc_html__('Cena', 'estate-office') . '<span class="required">*</span><input type="number" name="property[estate_property_price]" step="0.01" min="0" required /></label>';
        echo '<label>' . esc_html__('Metraż (m²)', 'estate-office') . '<span class="required">*</span><input type="number" name="property[estate_property_area]" step="0.01" min="0" required /></label>';
        echo '<label>' . esc_html__('Liczba pokoi', 'estate-office') . '<input type="number" name="property[estate_property_rooms]" min="0" /></label>';
        echo '</div>';
        echo '<label class="estate-office-agreement-creator__textarea">' . esc_html__('Opis nieruchomości', 'estate-office');
        echo '<textarea name="property[description]" rows="4"></textarea>';
        echo '</label>';
        if (!empty($propertyDynamic)) {
            echo '<div class="estate-office-agreement-creator__dynamic">';
            echo '<p class="estate-office-agreement-creator__dynamic-title">' . esc_html__('Pola dodatkowe nieruchomości', 'estate-office') . '</p>';
            echo '<div class="estate-office-agreement-creator__grid">';
            foreach ($propertyDynamic as $field) {
                $key   = (string) $field['key'];
                $label = (string) $field['label'];
                echo '<label>' . esc_html($label) . '<input type="text" name="property[dynamic][' . esc_attr($key) . ']" autocomplete="off" /></label>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Lista pól jest edytowalna w ustawieniach wtyczki.', 'estate-office') . '</p>';
            echo '</div>';
        }
        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="submit" class="estate-office-agreement-creator__primary">' . esc_html__('Dodaj nieruchomość', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__message" data-eo-agreement-property-message></div>';
        echo '</form>';

        echo '<form class="estate-office-agreement-creator__block" data-eo-agreement-search hidden>';
        echo '<input type="hidden" name="search[transaction_type]" value="" data-eo-agreement-search-transaction />';
        echo '<input type="hidden" name="search[estate_search_manager]" value="' . esc_attr((string) get_current_user_id()) . '" />';
        echo '<div class="estate-office-agreement-creator__existing">';
        echo '<h3>' . esc_html__('Wybierz istniejące poszukiwanie', 'estate-office') . '</h3>';
        echo '<p class="description">' . esc_html__('Wyszukaj numer poszukiwania, lokalizację lub opiekuna, aby powiązać zapisane zapytanie.', 'estate-office') . '</p>';
        echo '<div class="estate-office-agreement-creator__search">';
        echo '<input type="search" placeholder="' . esc_attr__('Numer poszukiwania, lokalizacja lub opiekun', 'estate-office') . '" data-eo-agreement-search-search />';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__search-results" data-eo-agreement-search-results></div>';
        echo '<div class="estate-office-agreement-creator__divider"><span>' . esc_html__('lub dodaj nowe poszukiwanie', 'estate-office') . '</span></div>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__grid">';
        echo '<label>' . esc_html__('Numer poszukiwania', 'estate-office') . '<span class="required">*</span><input type="text" name="search[estate_search_reference]" required /></label>';
        echo '<label>' . esc_html__('Rodzaj nieruchomości', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="search[estate_search_property_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        foreach ($propertyTypes as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Budżet od', 'estate-office') . '<input type="number" name="search[estate_search_price_min]" step="0.01" min="0" /></label>';
        echo '<label>' . esc_html__('Budżet do', 'estate-office') . '<input type="number" name="search[estate_search_price_max]" step="0.01" min="0" /></label>';
        echo '<label>' . esc_html__('Metraż od (m²)', 'estate-office') . '<input type="number" name="search[estate_search_area_min]" step="0.01" min="0" /></label>';
        echo '<label>' . esc_html__('Metraż do (m²)', 'estate-office') . '<input type="number" name="search[estate_search_area_max]" step="0.01" min="0" /></label>';
        echo '<label>' . esc_html__('Liczba pokoi od', 'estate-office') . '<input type="number" name="search[estate_search_rooms_min]" min="0" /></label>';
        echo '<label>' . esc_html__('Liczba pokoi do', 'estate-office') . '<input type="number" name="search[estate_search_rooms_max]" min="0" /></label>';
        echo '<label>' . esc_html__('Preferowana lokalizacja', 'estate-office') . '<input type="text" name="search[estate_search_location]" /></label>';
        echo '</div>';
        echo '<label class="estate-office-agreement-creator__textarea">' . esc_html__('Opis poszukiwania', 'estate-office');
        echo '<textarea name="search[estate_search_description]" rows="4"></textarea>';
        echo '</label>';
        if (!empty($searchDynamic)) {
            echo '<div class="estate-office-agreement-creator__dynamic">';
            echo '<p class="estate-office-agreement-creator__dynamic-title">' . esc_html__('Pola dodatkowe poszukiwania', 'estate-office') . '</p>';
            echo '<div class="estate-office-agreement-creator__grid">';
            foreach ($searchDynamic as $field) {
                $key   = (string) $field['key'];
                $label = (string) $field['label'];
                echo '<label>' . esc_html($label) . '<input type="text" name="search[dynamic][' . esc_attr($key) . ']" autocomplete="off" /></label>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Lista pól jest edytowalna w ustawieniach wtyczki.', 'estate-office') . '</p>';
            echo '</div>';
        }
        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="submit" class="estate-office-agreement-creator__primary">' . esc_html__('Dodaj poszukiwanie', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-agreement-creator__message" data-eo-agreement-search-message></div>';
        echo '</form>';

        echo '<div class="estate-office-agreement-creator__summary" data-eo-agreement-summary></div>';
        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="button" class="estate-office-agreement-creator__primary" data-eo-agreement-finish disabled>' . esc_html__('Przejdź do umowy', 'estate-office') . '</button>';
        echo '<button type="button" class="estate-office-agreement-creator__ghost" data-eo-agreement-prev>' . esc_html__('Wróć do klientów', 'estate-office') . '</button>';
        echo '<button type="button" class="estate-office-agreement-creator__ghost" data-eo-agreement-close>' . esc_html__('Zamknij', 'estate-office') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private static function handleCreateAgreement(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        $input = [
            'estate_agreement_number'            => $_POST['estate_agreement_number'] ?? '',
            'estate_agreement_transaction_type'  => $_POST['estate_agreement_transaction_type'] ?? '',
            'estate_agreement_start_date'        => $_POST['estate_agreement_start_date'] ?? '',
            'estate_agreement_end_date'          => $_POST['estate_agreement_end_date'] ?? '',
            'estate_agreement_is_indefinite'     => $_POST['estate_agreement_is_indefinite'] ?? '',
            'estate_agreement_commission_amount' => $_POST['estate_agreement_commission_amount'] ?? '',
            'estate_agreement_commission_unit'   => $_POST['estate_agreement_commission_unit'] ?? '',
        ];

        $values = AgreementMeta::prepareValues($input);
        $dynamicValues = AgreementMeta::prepareDynamicValues($_POST['estate_agreement_dynamic'] ?? []);

        if (empty($values['estate_agreement_number'])) {
            wp_send_json_error(['message' => esc_html__('Numer umowy jest wymagany.', 'estate-office')]);
        }

        if (AgreementMeta::isNumberTaken($values['estate_agreement_number'], $agreementId ?: null)) {
            wp_send_json_error(['message' => esc_html__('Umowa o podanym numerze już istnieje.', 'estate-office')]);
        }

        $stageDate = $values['estate_agreement_start_date'] ?: gmdate('Y-m-d');

        if ($agreementId > 0) {
            $values['estate_agreement_clients']    = get_post_meta($agreementId, 'estate_agreement_clients', true);
            $values['estate_agreement_properties'] = get_post_meta($agreementId, 'estate_agreement_properties', true);
            $values['estate_agreement_searches']   = get_post_meta($agreementId, 'estate_agreement_searches', true);
        }

        if ($agreementId > 0) {
            $post = get_post($agreementId);
            if (!$post instanceof WP_Post || $post->post_type !== AgreementRegister::POST_TYPE) {
                wp_send_json_error(['message' => esc_html__('Nie znaleziono wskazanej umowy.', 'estate-office')]);
            }

            if (!current_user_can('edit_post', $agreementId)) {
                wp_send_json_error(['message' => esc_html__('Brak uprawnień do edycji tej umowy.', 'estate-office')]);
            }

            wp_update_post([
                'ID'         => $agreementId,
                'post_title' => $values['estate_agreement_number'],
            ]);

            AgreementMeta::persistValues($agreementId, $values, $dynamicValues);
        } else {
            $agreementId = wp_insert_post([
                'post_type'   => AgreementRegister::POST_TYPE,
                'post_title'  => $values['estate_agreement_number'],
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            ], true);

            if ($agreementId instanceof WP_Error) {
                wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć umowy.', 'estate-office')]);
            }

            AgreementMeta::persistValues(
                $agreementId,
                $values,
                $dynamicValues,
                [
                    'stage'         => AgreementMeta::getDefaultStage(),
                    'stage_date'    => $stageDate,
                    'stage_history' => [
                        [
                            'stage' => AgreementMeta::getDefaultStage(),
                            'date'  => $stageDate,
                        ],
                    ],
                ]
            );
        }

        wp_send_json_success([
            'agreementId' => (int) $agreementId,
        ]);
    }

    private static function handleSearchClients(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash((string) $_GET['term'])) : '';

        $clients = self::queryClients($term);

        $items = [];
        foreach ($clients as $client) {
            $items[] = self::formatClient($client);
        }

        wp_send_json_success(['clients' => $items]);
    }

    private static function handleAttachClient(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        $clientId    = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;

        if ($agreementId <= 0 || $clientId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nieprawidłowe dane przekazane do przypisania klienta.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji umowy.', 'estate-office')]);
        }

        $agreement = get_post($agreementId);
        $client    = get_post($clientId);

        if (!$agreement instanceof WP_Post || $agreement->post_type !== AgreementRegister::POST_TYPE) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        if (!$client instanceof WP_Post || $client->post_type !== ClientRegister::POST_TYPE) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono klienta.', 'estate-office')]);
        }

        $clients    = AgreementMeta::prepareValues([
            'estate_agreement_clients' => get_post_meta($agreementId, 'estate_agreement_clients', true),
        ])['estate_agreement_clients'] ?? [];

        $clients[] = $clientId;
        $clients   = array_values(array_unique(array_map('intval', $clients)));

        $properties = get_post_meta($agreementId, 'estate_agreement_properties', true);
        $searches   = get_post_meta($agreementId, 'estate_agreement_searches', true);

        AgreementMeta::syncAgreementRelations($agreementId, $clients, $properties ?: [], $searches ?: []);

        wp_send_json_success([
            'clients' => self::getAgreementClients($agreementId),
        ]);
    }

    private static function handleDetachClient(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        $clientId    = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;

        if ($agreementId <= 0 || $clientId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nieprawidłowe dane przekazane do usunięcia klienta.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji umowy.', 'estate-office')]);
        }

        $clients = get_post_meta($agreementId, 'estate_agreement_clients', true);
        if (!is_array($clients)) {
            $clients = [];
        }

        $clients = array_values(array_diff(array_map('intval', $clients), [$clientId]));

        $properties = get_post_meta($agreementId, 'estate_agreement_properties', true);
        $searches   = get_post_meta($agreementId, 'estate_agreement_searches', true);

        AgreementMeta::syncAgreementRelations($agreementId, $clients, $properties ?: [], $searches ?: []);

        wp_send_json_success([
            'clients' => self::getAgreementClients($agreementId),
        ]);
    }

    private static function handleCreateClient(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        if ($agreementId <= 0) {
            wp_send_json_error(['message' => esc_html__('Brak identyfikatora umowy.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji umowy.', 'estate-office')]);
        }

        $clientData = isset($_POST['client']) && is_array($_POST['client']) ? $_POST['client'] : [];
        $values     = ClientMeta::prepareValues($clientData);
        $dynamic    = ClientMeta::prepareDynamicValues($clientData['dynamic'] ?? []);

        $clientId = wp_insert_post([
            'post_type'   => ClientRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => __('Nowy klient', 'estate-office'),
            'post_author' => get_current_user_id(),
        ], true);

        if ($clientId instanceof WP_Error) {
            wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć klienta.', 'estate-office')]);
        }

        ClientMeta::persistValues($clientId, $values, $dynamic);

        $clients    = get_post_meta($agreementId, 'estate_agreement_clients', true);
        if (!is_array($clients)) {
            $clients = [];
        }
        $clients[] = $clientId;

        $properties = get_post_meta($agreementId, 'estate_agreement_properties', true);
        $searches   = get_post_meta($agreementId, 'estate_agreement_searches', true);

        AgreementMeta::syncAgreementRelations($agreementId, $clients, $properties ?: [], $searches ?: []);

        wp_send_json_success([
            'client'  => self::formatClient(get_post($clientId)),
            'clients' => self::getAgreementClients($agreementId),
        ]);
    }

    private static function handleCreateProperty(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('publish_estate_properties')) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do tworzenia nieruchomości.', 'estate-office')]);
        }

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        if ($agreementId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji tej umowy.', 'estate-office')]);
        }

        $input           = isset($_POST['property']) && is_array($_POST['property']) ? $_POST['property'] : [];
        $transactionType = isset($input['transaction_type']) ? sanitize_text_field(wp_unslash((string) $input['transaction_type'])) : '';
        if (!in_array($transactionType, self::PROPERTY_TRANSACTION_TYPES, true)) {
            wp_send_json_error(['message' => esc_html__('Wybrany typ transakcji nie pozwala na dodanie nieruchomości.', 'estate-office')]);
        }

        $propertyType = isset($input['property_type']) ? sanitize_text_field(wp_unslash((string) $input['property_type'])) : '';
        $propertyTypes = self::getPropertyTypes();
        if ($propertyType === '' || !isset($propertyTypes[$propertyType])) {
            wp_send_json_error(['message' => esc_html__('Wybierz prawidłowy rodzaj nieruchomości.', 'estate-office')]);
        }

        $description = isset($input['description']) ? wp_kses_post(wp_unslash((string) $input['description'])) : '';
        $values      = PropertyMeta::prepareValues($input);

        if (empty($values['estate_property_reference'])) {
            wp_send_json_error(['message' => esc_html__('Numer oferty jest wymagany.', 'estate-office')]);
        }

        if (empty($values['estate_property_manager'])) {
            $values['estate_property_manager'] = (string) get_current_user_id();
        }

        $titleParts = [];
        $typeLabel  = $propertyTypes[$propertyType] ?? '';
        if ($typeLabel !== '') {
            $titleParts[] = $typeLabel;
        }
        if (!empty($values['estate_property_city'])) {
            $titleParts[] = wp_strip_all_tags((string) $values['estate_property_city']);
        }
        if (!empty($values['estate_property_street'])) {
            $titleParts[] = wp_strip_all_tags((string) $values['estate_property_street']);
        }

        $title = implode(' • ', array_filter($titleParts));
        if ($title === '') {
            $title = wp_strip_all_tags((string) $values['estate_property_reference']);
        }

        $propertyId = wp_insert_post([
            'post_type'   => PropertyRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> $description,
            'post_author' => get_current_user_id(),
        ], true);

        if ($propertyId instanceof WP_Error) {
            wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć nieruchomości.', 'estate-office')]);
        }

        PropertyMeta::persistValues($propertyId, $values, PropertyMeta::prepareDynamicValues($input['dynamic'] ?? []));

        $transactionLabel = AgreementMeta::TRANSACTION_TYPES[$transactionType] ?? $transactionType;
        self::ensureTermExists('estate_transaction_type', $transactionType, $transactionLabel);
        wp_set_post_terms($propertyId, [$transactionType], 'estate_transaction_type', false);

        self::ensureTermExists('estate_property_type', $propertyType, $typeLabel);
        wp_set_post_terms($propertyId, [$propertyType], 'estate_property_type', false);

        if (!empty($values['estate_property_city'])) {
            $citySlug = sanitize_title((string) $values['estate_property_city']);
            if ($citySlug !== '') {
                self::ensureTermExists('estate_city', $citySlug, (string) $values['estate_property_city']);
                wp_set_post_terms($propertyId, [$citySlug], 'estate_city', false);
            }
        }

        if (!empty($values['estate_property_district'])) {
            $districtSlug = sanitize_title((string) $values['estate_property_district']);
            if ($districtSlug !== '') {
                self::ensureTermExists('estate_district', $districtSlug, (string) $values['estate_property_district']);
                wp_set_post_terms($propertyId, [$districtSlug], 'estate_district', false);
            }
        }

        $clients    = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_clients', true));
        $properties = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_searches', true));

        $properties[] = $propertyId;
        $properties   = array_values(array_unique($properties));

        AgreementMeta::syncAgreementRelations(
            $agreementId,
            $clients,
            $properties,
            $searches
        );

        wp_send_json_success([
            'record'   => self::formatPropertyRecord((int) $propertyId),
            'redirect' => self::buildAgreementRedirect($agreementId),
        ]);
    }

    private static function handleCreateSearch(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('publish_estate_searches')) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do tworzenia poszukiwań.', 'estate-office')]);
        }

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        if ($agreementId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji tej umowy.', 'estate-office')]);
        }

        $input           = isset($_POST['search']) && is_array($_POST['search']) ? $_POST['search'] : [];
        $transactionType = isset($input['transaction_type']) ? sanitize_text_field(wp_unslash((string) $input['transaction_type'])) : '';
        $searchType      = self::mapAgreementToSearchTransaction($transactionType);

        if ($searchType === '') {
            wp_send_json_error(['message' => esc_html__('Wybrany typ transakcji nie pozwala na dodanie poszukiwania.', 'estate-office')]);
        }

        $values = SearchMeta::prepareValues($input);

        if (empty($values['estate_search_reference'])) {
            wp_send_json_error(['message' => esc_html__('Numer poszukiwania jest wymagany.', 'estate-office')]);
        }

        if (empty($values['estate_search_property_type'])) {
            wp_send_json_error(['message' => esc_html__('Wybierz rodzaj nieruchomości.', 'estate-office')]);
        }

        if (empty($values['estate_search_manager'])) {
            $values['estate_search_manager'] = (string) get_current_user_id();
        }

        $values['estate_search_transaction_type'] = $searchType;

        $description = $values['estate_search_description'] ?? '';

        $titleParts = [];
        if (!empty($values['estate_search_location'])) {
            $titleParts[] = wp_strip_all_tags((string) $values['estate_search_location']);
        }
        if (!empty($values['estate_search_property_type'])) {
            $propertyTypes = self::getPropertyTypes();
            $titleParts[]  = $propertyTypes[$values['estate_search_property_type']] ?? '';
        }

        $title = implode(' • ', array_filter($titleParts));
        if ($title === '') {
            $title = wp_strip_all_tags((string) $values['estate_search_reference']);
        }

        $searchId = wp_insert_post([
            'post_type'   => SearchRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> wp_kses_post((string) $description),
            'post_author' => get_current_user_id(),
        ], true);

        if ($searchId instanceof WP_Error) {
            wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć poszukiwania.', 'estate-office')]);
        }

        SearchMeta::persistValues($searchId, $values, SearchMeta::prepareDynamicValues($input['dynamic'] ?? []));

        $clients    = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_clients', true));
        $properties = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_searches', true));

        $searches[] = $searchId;
        $searches   = array_values(array_unique($searches));

        AgreementMeta::syncAgreementRelations(
            $agreementId,
            $clients,
            $properties,
            $searches
        );

        wp_send_json_success([
            'record'   => self::formatSearchRecord((int) $searchId),
            'redirect' => self::buildAgreementRedirect($agreementId),
        ]);
    }

    private static function handleSearchProperties(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_GET['agreement_id']) ? absint($_GET['agreement_id']) : 0;
        $term        = isset($_GET['term']) ? sanitize_text_field(wp_unslash((string) $_GET['term'])) : '';
        $transaction = isset($_GET['transaction_type']) ? sanitize_text_field(wp_unslash((string) $_GET['transaction_type'])) : '';

        if ($agreementId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do przeglądania tej umowy.', 'estate-office')]);
        }

        if ($transaction === '') {
            $transaction = get_post_meta($agreementId, 'estate_agreement_transaction_type', true);
            $transaction = is_string($transaction) ? $transaction : '';
        }

        if (!in_array($transaction, self::PROPERTY_TRANSACTION_TYPES, true)) {
            wp_send_json_error(['message' => esc_html__('Bieżąca umowa nie wymaga powiązania nieruchomości.', 'estate-office')]);
        }

        $properties = self::queryProperties($term, $transaction);
        $records    = [];
        foreach ($properties as $property) {
            $records[] = self::formatPropertyRecord($property->ID);
        }

        wp_send_json_success(['records' => $records]);
    }

    private static function handleAttachProperty(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        $propertyId  = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;

        if ($agreementId <= 0 || $propertyId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nieprawidłowe dane powiązania nieruchomości.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji tej umowy.', 'estate-office')]);
        }

        $agreement = get_post($agreementId);
        if (!$agreement instanceof WP_Post || $agreement->post_type !== AgreementRegister::POST_TYPE) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        $property = get_post($propertyId);
        if (!$property instanceof WP_Post || $property->post_type !== PropertyRegister::POST_TYPE) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono nieruchomości.', 'estate-office')]);
        }

        if ($property->post_status === 'trash') {
            wp_send_json_error(['message' => esc_html__('Nie można powiązać nieruchomości w koszu.', 'estate-office')]);
        }

        $transaction = get_post_meta($agreementId, 'estate_agreement_transaction_type', true);
        $transaction = is_string($transaction) ? $transaction : '';

        if (!in_array($transaction, self::PROPERTY_TRANSACTION_TYPES, true)) {
            wp_send_json_error(['message' => esc_html__('Wybrany typ transakcji nie pozwala na dodanie nieruchomości.', 'estate-office')]);
        }

        $terms = wp_get_post_terms($propertyId, 'estate_transaction_type', ['fields' => 'slugs']);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        if (!in_array($transaction, $terms, true)) {
            self::ensureTermExists('estate_transaction_type', $transaction, AgreementMeta::TRANSACTION_TYPES[$transaction] ?? $transaction);
            wp_set_post_terms($propertyId, [$transaction], 'estate_transaction_type', false);
        }

        $clients    = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_clients', true));
        $properties = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_searches', true));

        $properties[] = $propertyId;

        AgreementMeta::syncAgreementRelations($agreementId, $clients, $properties, $searches);

        wp_send_json_success([
            'record'   => self::formatPropertyRecord($propertyId),
            'redirect' => self::buildAgreementRedirect($agreementId),
        ]);
    }

    private static function handleSearchSearches(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_GET['agreement_id']) ? absint($_GET['agreement_id']) : 0;
        $term        = isset($_GET['term']) ? sanitize_text_field(wp_unslash((string) $_GET['term'])) : '';
        $transaction = isset($_GET['transaction_type']) ? sanitize_text_field(wp_unslash((string) $_GET['transaction_type'])) : '';

        if ($agreementId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do przeglądania tej umowy.', 'estate-office')]);
        }

        if ($transaction === '') {
            $transaction = get_post_meta($agreementId, 'estate_agreement_transaction_type', true);
            $transaction = is_string($transaction) ? $transaction : '';
        }

        $searchTransaction = self::mapAgreementToSearchTransaction($transaction);
        if ($searchTransaction === '') {
            wp_send_json_error(['message' => esc_html__('Bieżąca umowa nie wymaga powiązania poszukiwania.', 'estate-office')]);
        }

        $searches = self::querySearches($term, $searchTransaction);
        $records  = [];
        foreach ($searches as $search) {
            $records[] = self::formatSearchRecord($search->ID);
        }

        wp_send_json_success(['records' => $records]);
    }

    private static function handleAttachSearch(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        $searchId    = isset($_POST['search_id']) ? absint($_POST['search_id']) : 0;

        if ($agreementId <= 0 || $searchId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nieprawidłowe dane powiązania poszukiwania.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji tej umowy.', 'estate-office')]);
        }

        $agreement = get_post($agreementId);
        if (!$agreement instanceof WP_Post || $agreement->post_type !== AgreementRegister::POST_TYPE) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy.', 'estate-office')]);
        }

        $search = get_post($searchId);
        if (!$search instanceof WP_Post || $search->post_type !== SearchRegister::POST_TYPE) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono poszukiwania.', 'estate-office')]);
        }

        if ($search->post_status === 'trash') {
            wp_send_json_error(['message' => esc_html__('Nie można powiązać poszukiwania w koszu.', 'estate-office')]);
        }

        $transaction = get_post_meta($agreementId, 'estate_agreement_transaction_type', true);
        $transaction = is_string($transaction) ? $transaction : '';
        $searchTransaction = self::mapAgreementToSearchTransaction($transaction);

        if ($searchTransaction === '') {
            wp_send_json_error(['message' => esc_html__('Wybrany typ transakcji nie pozwala na dodanie poszukiwania.', 'estate-office')]);
        }

        $currentTransaction = get_post_meta($searchId, 'estate_search_transaction_type', true);
        if ($currentTransaction !== $searchTransaction) {
            update_post_meta($searchId, 'estate_search_transaction_type', $searchTransaction);
        }

        $clients    = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_clients', true));
        $properties = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_searches', true));

        $searches[] = $searchId;

        AgreementMeta::syncAgreementRelations($agreementId, $clients, $properties, $searches);

        wp_send_json_success([
            'record'   => self::formatSearchRecord($searchId),
            'redirect' => self::buildAgreementRedirect($agreementId),
        ]);
    }

    private static function handleDetachRecord(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;
        $recordId    = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $recordType  = isset($_POST['record_type']) ? sanitize_text_field(wp_unslash((string) $_POST['record_type'])) : '';

        if ($agreementId <= 0 || $recordId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nieprawidłowe dane rekordu.', 'estate-office')]);
        }

        if (!in_array($recordType, ['property', 'search'], true)) {
            wp_send_json_error(['message' => esc_html__('Nieobsługiwany typ rekordu.', 'estate-office')]);
        }

        if (!current_user_can('edit_post', $agreementId)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do aktualizacji tej umowy.', 'estate-office')]);
        }

        $clients    = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_clients', true));
        $properties = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_searches', true));

        if ($recordType === 'property') {
            $properties = array_values(array_diff($properties, [$recordId]));
        } else {
            $searches = array_values(array_diff($searches, [$recordId]));
        }

        AgreementMeta::syncAgreementRelations($agreementId, $clients, $properties, $searches);

        wp_send_json_success([
            'record'   => null,
            'redirect' => '',
        ]);
    }

    private static function handleFinalize(): void
    {
        self::verifyPermissions();
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $agreementId = isset($_POST['agreement_id']) ? absint($_POST['agreement_id']) : 0;

        if ($agreementId <= 0) {
            wp_send_json_error(['message' => esc_html__('Nie znaleziono umowy do finalizacji.', 'estate-office')]);
        }

        $clients = get_post_meta($agreementId, 'estate_agreement_clients', true);
        if (!is_array($clients) || $clients === []) {
            wp_send_json_error(['message' => esc_html__('Dodaj przynajmniej jednego klienta.', 'estate-office')]);
        }

        $transaction = get_post_meta($agreementId, 'estate_agreement_transaction_type', true);
        $transaction = is_string($transaction) ? $transaction : '';

        $properties = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::sanitizeIdList(get_post_meta($agreementId, 'estate_agreement_searches', true));

        if (in_array($transaction, self::PROPERTY_TRANSACTION_TYPES, true) && $properties === []) {
            wp_send_json_error(['message' => esc_html__('Dodaj lub wybierz nieruchomość, aby zakończyć kreator.', 'estate-office')]);
        }

        if (in_array($transaction, self::SEARCH_TRANSACTION_TYPES, true) && $searches === []) {
            wp_send_json_error(['message' => esc_html__('Dodaj lub wybierz poszukiwanie, aby zakończyć kreator.', 'estate-office')]);
        }

        wp_send_json_success([
            'redirect' => self::buildAgreementRedirect($agreementId),
        ]);
    }

    private static function verifyPermissions(): void
    {
        if (!is_user_logged_in() || !current_user_can('publish_estate_agreements')) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do tworzenia umów.', 'estate-office')]);
        }
    }

    /**
     * @return array<int,WP_Post>
     */
    private static function queryClients(string $term): array
    {
        $args = [
            'post_type'      => ClientRegister::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($term !== '') {
            $args['s'] = $term;
        }

        $query = new WP_Query($args);
        $posts = $query->posts;

        if ($term !== '') {
            $metaMatches = get_posts([
                'post_type'      => ClientRegister::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'pending'],
                'posts_per_page' => 10,
                'fields'         => 'ids',
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'estate_client_phone',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => 'estate_client_email',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                ],
            ]);

            if (is_array($metaMatches)) {
                $additional = get_posts([
                    'post_type'      => ClientRegister::POST_TYPE,
                    'post_status'    => ['publish', 'draft', 'pending'],
                    'posts_per_page' => 10,
                    'post__in'       => array_map('intval', $metaMatches),
                ]);

                $posts = array_merge($posts, $additional);
            }
        }

        $unique = [];
        $result = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            if (isset($unique[$post->ID])) {
                continue;
            }

            $unique[$post->ID] = true;
            $result[]          = $post;
        }

        return array_slice($result, 0, 10);
    }

    private static function formatClient($client): array
    {
        if (!$client instanceof WP_Post) {
            return [];
        }

        $phone = get_post_meta($client->ID, 'estate_client_phone', true);
        $email = get_post_meta($client->ID, 'estate_client_email', true);

        return [
            'id'    => $client->ID,
            'name'  => get_the_title($client),
            'phone' => is_string($phone) ? $phone : '',
            'email' => is_string($email) ? $email : '',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function getPropertyTypes(): array
    {
        return SearchMeta::PROPERTY_TYPES;
    }

    private static function mapAgreementToSearchTransaction(string $transactionType): string
    {
        return match ($transactionType) {
            'purchase'  => 'buy',
            'rent_out'  => 'rent',
            'sale', 'lease' => $transactionType,
            default     => '',
        };
    }

    private static function buildAgreementRedirect(int $agreementId): string
    {
        return add_query_arg(
            [
                CRM::SECTION_PARAM   => 'agreements',
                CRM::RECORD_PARAM    => 'agreements',
                CRM::RECORD_ID_PARAM => (string) $agreementId,
            ],
            CRM::getCrmBaseUrl()
        );
    }

    private static function buildCrmRecordUrl(string $section, int $recordId): string
    {
        return add_query_arg(
            [
                CRM::SECTION_PARAM   => $section,
                CRM::RECORD_PARAM    => $section,
                CRM::RECORD_ID_PARAM => (string) $recordId,
            ],
            CRM::getCrmBaseUrl()
        );
    }

    private static function ensureTermExists(string $taxonomy, string $slug, string $label): void
    {
        if ($slug === '') {
            return;
        }

        $exists = term_exists($slug, $taxonomy);
        if ($exists === 0 || $exists === null) {
            $label = $label !== '' ? $label : $slug;
            $result = wp_insert_term($label, $taxonomy, ['slug' => $slug]);
            if ($result instanceof WP_Error) {
                // Silently ignore term creation errors to avoid interrupting the workflow.
            }
        }
    }

    /**
     * @param mixed $value
     * @return array<int,int>
     */
    private static function sanitizeIdList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = array_map('intval', $value);
        $ids = array_filter($ids, static fn($id) => $id > 0);

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int,WP_Post>
     */
    private static function queryProperties(string $term, string $transaction): array
    {
        $args = [
            'post_type'      => PropertyRegister::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $taxQuery = [];
        if ($transaction !== '') {
            $taxQuery[] = [
                'taxonomy' => 'estate_transaction_type',
                'field'    => 'slug',
                'terms'    => [$transaction],
            ];
        }

        if ($taxQuery !== []) {
            $args['tax_query'] = $taxQuery;
        }

        if ($term !== '') {
            $args['s'] = $term;
        }

        $query    = new WP_Query($args);
        $posts    = $query->posts;
        $existing = wp_list_pluck($posts, 'ID');

        if ($term !== '') {
            $metaArgs = [
                'post_type'      => PropertyRegister::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'pending'],
                'posts_per_page' => 10,
                'fields'         => 'ids',
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'estate_property_reference',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => 'estate_property_city',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => 'estate_property_street',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ],
                ],
            ];

            if ($taxQuery !== []) {
                $metaArgs['tax_query'] = $taxQuery;
            }

            $metaMatches = get_posts($metaArgs);

            if (is_array($metaMatches) && $metaMatches !== []) {
                $metaArgs = [
                    'post_type'      => PropertyRegister::POST_TYPE,
                    'post_status'    => ['publish', 'draft', 'pending'],
                    'posts_per_page' => 10,
                    'post__in'       => array_map('intval', $metaMatches),
                ];

                if ($taxQuery !== []) {
                    $metaArgs['tax_query'] = $taxQuery;
                }

                if ($existing !== []) {
                    $metaArgs['post__not_in'] = array_map('intval', $existing);
                }

                $additional = get_posts($metaArgs);
                $posts      = array_merge($posts, $additional);
            }
        }

        $unique = [];
        $result = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            if (isset($unique[$post->ID])) {
                continue;
            }

            $unique[$post->ID] = true;
            $result[]          = $post;
        }

        return array_slice($result, 0, 10);
    }

    /**
     * @return array<int,WP_Post>
     */
    private static function querySearches(string $term, string $transaction): array
    {
        $metaQuery = [];
        if ($transaction !== '') {
            $metaQuery[] = [
                'key'   => 'estate_search_transaction_type',
                'value' => $transaction,
            ];
        }

        $args = [
            'post_type'      => SearchRegister::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }

        if ($term !== '') {
            $args['s'] = $term;
        }

        $query = new WP_Query($args);
        $posts = $query->posts;
        $existing = wp_list_pluck($posts, 'ID');

        if ($term !== '') {
            $searchFilters = [
                [
                    'key'     => 'estate_search_reference',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_search_location',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
            ];

            $metaArgs = [
                'post_type'      => SearchRegister::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'pending'],
                'posts_per_page' => 10,
                'fields'         => 'ids',
                'meta_query'     => array_merge([
                    'relation' => 'OR',
                ], $searchFilters),
            ];

            if ($metaQuery !== []) {
                $metaArgs['meta_query'] = [
                    'relation' => 'AND',
                    $metaQuery[0],
                    array_merge([
                        'relation' => 'OR',
                    ], $searchFilters),
                ];
            }

            $metaMatches = get_posts($metaArgs);

            if (is_array($metaMatches) && $metaMatches !== []) {
                $additionalArgs = [
                    'post_type'      => SearchRegister::POST_TYPE,
                    'post_status'    => ['publish', 'draft', 'pending'],
                    'posts_per_page' => 10,
                    'post__in'       => array_map('intval', $metaMatches),
                ];

                if ($metaQuery !== []) {
                    $additionalArgs['meta_query'] = $metaQuery;
                }

                if ($existing !== []) {
                    $additionalArgs['post__not_in'] = array_map('intval', $existing);
                }

                $additional = get_posts($additionalArgs);
                $posts      = array_merge($posts, $additional);
            }
        }

        $unique = [];
        $result = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            if (isset($unique[$post->ID])) {
                continue;
            }

            $unique[$post->ID] = true;
            $result[]          = $post;
        }

        return array_slice($result, 0, 10);
    }

    private static function formatPropertyRecord(int $propertyId): array
    {
        $property = get_post($propertyId);
        if (!$property instanceof WP_Post) {
            return [];
        }

        $reference = get_post_meta($propertyId, 'estate_property_reference', true);
        $street    = get_post_meta($propertyId, 'estate_property_street', true);
        $number    = get_post_meta($propertyId, 'estate_property_number', true);
        $district  = get_post_meta($propertyId, 'estate_property_district', true);
        $city      = get_post_meta($propertyId, 'estate_property_city', true);
        $managerId = (int) get_post_meta($propertyId, 'estate_property_manager', true);

        $managerName = '';
        if ($managerId > 0) {
            $manager = get_user_by('id', $managerId);
            if ($manager && $manager->display_name) {
                $managerName = wp_strip_all_tags((string) $manager->display_name);
            }
        }

        $propertyType = '';
        $typeTerms    = wp_get_post_terms($propertyId, 'estate_property_type', ['fields' => 'names']);
        if (!is_wp_error($typeTerms) && isset($typeTerms[0])) {
            $propertyType = wp_strip_all_tags((string) $typeTerms[0]);
        }

        $transaction = '';
        $transactionTerms = wp_get_post_terms($propertyId, 'estate_transaction_type', ['fields' => 'slugs']);
        if (!is_wp_error($transactionTerms) && isset($transactionTerms[0])) {
            $transaction = (string) $transactionTerms[0];
        }

        $streetLine = '';
        if (is_string($street) && $street !== '') {
            $streetLine = $street;
            if (is_string($number) && $number !== '') {
                $streetLine .= ' ' . $number;
            }
        }

        return [
            'type'         => 'property',
            'id'           => $propertyId,
            'title'        => wp_strip_all_tags(get_the_title($property)),
            'reference'    => is_string($reference) ? wp_strip_all_tags($reference) : '',
            'location'     => self::buildLocation([$streetLine, $district, $city]),
            'manager'      => $managerName,
            'propertyType' => $propertyType,
            'transaction'  => $transaction,
            'url'          => self::buildCrmRecordUrl('properties', $propertyId),
        ];
    }

    private static function formatSearchRecord(int $searchId): array
    {
        $search = get_post($searchId);
        if (!$search instanceof WP_Post) {
            return [];
        }

        $reference   = get_post_meta($searchId, 'estate_search_reference', true);
        $location    = get_post_meta($searchId, 'estate_search_location', true);
        $managerId   = (int) get_post_meta($searchId, 'estate_search_manager', true);
        $propertyKey = get_post_meta($searchId, 'estate_search_property_type', true);
        $transaction = get_post_meta($searchId, 'estate_search_transaction_type', true);

        $managerName = '';
        if ($managerId > 0) {
            $manager = get_user_by('id', $managerId);
            if ($manager && $manager->display_name) {
                $managerName = wp_strip_all_tags((string) $manager->display_name);
            }
        }

        $propertyLabel = '';
        if (is_string($propertyKey) && isset(SearchMeta::PROPERTY_TYPES[$propertyKey])) {
            $propertyLabel = wp_strip_all_tags((string) SearchMeta::PROPERTY_TYPES[$propertyKey]);
        }

        return [
            'type'         => 'search',
            'id'           => $searchId,
            'title'        => wp_strip_all_tags(get_the_title($search)),
            'reference'    => is_string($reference) ? wp_strip_all_tags($reference) : '',
            'location'     => is_string($location) ? wp_strip_all_tags($location) : '',
            'manager'      => $managerName,
            'propertyType' => $propertyLabel,
            'transaction'  => is_string($transaction) ? $transaction : '',
            'url'          => self::buildCrmRecordUrl('searches', $searchId),
        ];
    }

    /**
     * @param array<int,string> $parts
     */
    private static function buildLocation(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            if (!is_string($part)) {
                continue;
            }

            $part = trim(wp_strip_all_tags($part));
            if ($part === '') {
                continue;
            }

            $clean[] = $part;
        }

        return implode(', ', $clean);
    }

    private static function getAgreementClients(int $agreementId): array
    {
        $clientIds = get_post_meta($agreementId, 'estate_agreement_clients', true);
        if (!is_array($clientIds)) {
            $clientIds = [];
        }

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));
        if ($clientIds === []) {
            return [];
        }

        $clients = get_posts([
            'post_type'      => ClientRegister::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => count($clientIds),
            'post__in'       => $clientIds,
        ]);

        $formatted = [];
        foreach ($clients as $client) {
            $data = self::formatClient($client);
            if (!empty($data)) {
                $formatted[] = $data;
            }
        }

        return $formatted;
    }
}

