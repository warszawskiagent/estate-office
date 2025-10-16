<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\PostTypes\AgreementMeta;
use EstateOffice\PostTypes\ClientMeta;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\PropertyMeta;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchMeta;
use EstateOffice\PostTypes\SearchRegister;
use EstateOffice\Settings\GeneralSettings;
use WP_Error;

use function __;
use function add_action;
use function add_query_arg;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function get_current_user_id;
use function is_user_logged_in;
use function is_array;
use function is_scalar;
use function plugins_url;
use function sanitize_key;
use function sanitize_title;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_insert_post;
use function wp_insert_term;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_set_post_terms;
use function wp_strip_all_tags;
use function wp_unslash;
use function wpautop;
use function wp_kses_post;
use function term_exists;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

final class QuickCreate
{
    private const NONCE_ACTION = 'estate_office_quick_create';
    private const STYLE_HANDLE = 'estate-office-quick-create';
    private const SCRIPT_HANDLE = 'estate-office-quick-create';

    /**
     * @var array<string,array{capability:string,action:string,label:string,description:string}>
     */
    private const TYPES = [
        'property' => [
            'capability'  => 'publish_estate_properties',
            'action'      => 'estate_office_quick_create_property',
            'label'       => 'Dodaj nieruchomość',
            'description' => 'Utwórz nową ofertę nieruchomości bez opuszczania panelu CRM.',
        ],
        'client' => [
            'capability'  => 'publish_estate_clients',
            'action'      => 'estate_office_quick_create_client',
            'label'       => 'Dodaj klienta',
            'description' => 'Dodaj podstawowe dane klienta i przypisz mu opiekuna.',
        ],
        'search' => [
            'capability'  => 'publish_estate_searches',
            'action'      => 'estate_office_quick_create_search',
            'label'       => 'Dodaj poszukiwanie',
            'description' => 'Opisz wymagania klienta, aby utworzyć nowe poszukiwanie.',
        ],
    ];

    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);

        add_action('wp_ajax_estate_office_quick_create_property', [self::class, 'handleCreateProperty']);
        add_action('wp_ajax_estate_office_quick_create_client', [self::class, 'handleCreateClient']);
        add_action('wp_ajax_estate_office_quick_create_search', [self::class, 'handleCreateSearch']);
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            self::STYLE_HANDLE,
            plugins_url('assets/css/quick-create.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            plugins_url('assets/js/quick-create.js', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );
    }

    public static function enqueueAssets(): void
    {
        if (!self::currentUserCanCreateAnything()) {
            return;
        }

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'EstateOfficeQuickCreate',
            [
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce(self::NONCE_ACTION),
                'types'     => self::getAvailableTypes(),
                'messages'  => [
                    'success' => esc_html__('Rekord został utworzony pomyślnie.', 'estate-office'),
                    'error'   => esc_html__('Nie udało się zapisać danych. Uzupełnij wymagane pola i spróbuj ponownie.', 'estate-office'),
                ],
            ]
        );
    }

    public static function renderModal(): void
    {
        if (!self::currentUserCanCreateAnything()) {
            return;
        }

        $types = self::getAvailableTypes();
        if ($types === []) {
            return;
        }

        $propertyTypes = SearchMeta::PROPERTY_TYPES;
        $clientTypes   = ClientMeta::CLIENT_TYPES;
        $propertyDynamic = GeneralSettings::getPropertyDynamicFields();
        $clientDynamic   = GeneralSettings::getClientDynamicFields();
        $searchDynamic   = GeneralSettings::getSearchDynamicFields();

        echo '<div class="estate-office-quick-create" data-eo-quick-create hidden>';
        echo '<div class="estate-office-quick-create__backdrop" data-eo-quick-close></div>';
        echo '<div class="estate-office-quick-create__dialog">';
        echo '<button type="button" class="estate-office-quick-create__close" data-eo-quick-close aria-label="' . esc_attr__('Zamknij okno', 'estate-office') . '">×</button>';
        echo '<div class="estate-office-quick-create__tabs">';
        foreach ($types as $type => $config) {
            echo '<button type="button" class="estate-office-quick-create__tab" data-eo-quick-tab="' . esc_attr($type) . '">' . esc_html($config['label']) . '</button>';
        }
        echo '</div>';
        echo '<div class="estate-office-quick-create__panels">';
        foreach ($types as $type => $config) {
            echo '<section class="estate-office-quick-create__panel" data-eo-quick-panel="' . esc_attr($type) . '" hidden>';
            echo '<header>';
            echo '<h2>' . esc_html($config['label']) . '</h2>';
            echo '<p class="description">' . esc_html($config['description']) . '</p>';
            echo '</header>';
            switch ($type) {
                case 'property':
                    self::renderPropertyForm($config['action'], $propertyTypes, $propertyDynamic);
                    break;
                case 'client':
                    self::renderClientForm($config['action'], $clientTypes, $clientDynamic);
                    break;
                case 'search':
                    self::renderSearchForm($config['action'], $propertyTypes, $searchDynamic);
                    break;
            }
            echo '</section>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<string,string> $propertyTypes
     * @param array<int,array<string,string>> $dynamicFields
     */
    private static function renderPropertyForm(string $action, array $propertyTypes, array $dynamicFields): void
    {
        echo '<form class="estate-office-quick-create__form" data-eo-quick-form="property">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce(self::NONCE_ACTION)) . '" />';
        echo '<div class="estate-office-quick-create__grid">';
        echo '<label>' . esc_html__('Numer oferty', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_reference]" required /></label>';
        echo '<label>' . esc_html__('Typ transakcji', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="property[transaction_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        echo '<option value="sale">' . esc_html__('Sprzedaż', 'estate-office') . '</option>';
        echo '<option value="rent_out">' . esc_html__('Wynajem', 'estate-office') . '</option>';
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Rodzaj nieruchomości', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="property[property_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        foreach ($propertyTypes as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Cena', 'estate-office') . '<input type="number" step="0.01" min="0" name="property[estate_property_price]" /></label>';
        echo '<label>' . esc_html__('Powierzchnia (m²)', 'estate-office') . '<input type="number" step="0.01" min="0" name="property[estate_property_area]" /></label>';
        echo '<label>' . esc_html__('Miasto', 'estate-office') . '<span class="required">*</span><input type="text" name="property[estate_property_city]" required /></label>';
        echo '<label>' . esc_html__('Ulica', 'estate-office') . '<input type="text" name="property[estate_property_street]" /></label>';
        echo '<label>' . esc_html__('Numer', 'estate-office') . '<input type="text" name="property[estate_property_number]" /></label>';
        echo '<label>' . esc_html__('Dzielnica', 'estate-office') . '<input type="text" name="property[estate_property_district]" /></label>';
        echo '<label>' . esc_html__('Kod pocztowy', 'estate-office') . '<input type="text" name="property[estate_property_postal_code]" /></label>';
        echo '</div>';
        echo '<label class="estate-office-quick-create__wide">' . esc_html__('Opis oferty', 'estate-office') . '<textarea name="property[description]" rows="4"></textarea></label>';
        if (!empty($dynamicFields)) {
            echo '<fieldset class="estate-office-quick-create__fieldset">';
            echo '<legend>' . esc_html__('Pola dodatkowe', 'estate-office') . '</legend>';
            echo '<div class="estate-office-quick-create__grid">';
            foreach ($dynamicFields as $field) {
                $key   = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? '');
                if ($key === '' || $label === '') {
                    continue;
                }
                echo '<label>' . esc_html($label) . '<input type="text" name="property[dynamic][' . esc_attr($key) . ']" /></label>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Zarządzaj listą w ustawieniach pola „Pola nieruchomości”.', 'estate-office') . '</p>';
            echo '</fieldset>';
        }
        echo '<div class="estate-office-quick-create__actions">';
        echo '<button type="submit" class="estate-office-quick-create__primary">' . esc_html__('Zapisz nieruchomość', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-quick-create__message" data-eo-quick-message></div>';
        echo '</form>';
    }

    /**
     * @param array<string,string> $clientTypes
     * @param array<int,array<string,string>> $dynamicFields
     */
    private static function renderClientForm(string $action, array $clientTypes, array $dynamicFields): void
    {
        echo '<form class="estate-office-quick-create__form" data-eo-quick-form="client">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce(self::NONCE_ACTION)) . '" />';
        echo '<div class="estate-office-quick-create__grid">';
        echo '<label>' . esc_html__('Typ klienta', 'estate-office');
        echo '<select name="client[estate_client_type]">';
        foreach ($clientTypes as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Imię', 'estate-office') . '<input type="text" name="client[estate_client_first_name]" /></label>';
        echo '<label>' . esc_html__('Nazwisko', 'estate-office') . '<input type="text" name="client[estate_client_last_name]" /></label>';
        echo '<label>' . esc_html__('Nazwa firmy', 'estate-office') . '<input type="text" name="client[estate_client_company_name]" /></label>';
        echo '<label>' . esc_html__('Przedstawiciel firmy', 'estate-office') . '<input type="text" name="client[estate_client_company_representative]" /></label>';
        echo '<label>' . esc_html__('Telefon', 'estate-office') . '<input type="tel" name="client[estate_client_phone]" /></label>';
        echo '<label>' . esc_html__('E-mail', 'estate-office') . '<input type="email" name="client[estate_client_email]" /></label>';
        echo '<label>' . esc_html__('Strona WWW', 'estate-office') . '<input type="url" name="client[estate_client_website]" /></label>';
        echo '</div>';
        echo '<label class="estate-office-quick-create__wide">' . esc_html__('Notatki', 'estate-office') . '<textarea name="client[estate_client_notes]" rows="4"></textarea></label>';
        if (!empty($dynamicFields)) {
            echo '<fieldset class="estate-office-quick-create__fieldset">';
            echo '<legend>' . esc_html__('Pola dodatkowe', 'estate-office') . '</legend>';
            echo '<div class="estate-office-quick-create__grid">';
            foreach ($dynamicFields as $field) {
                $key   = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? '');
                if ($key === '' || $label === '') {
                    continue;
                }
                echo '<label>' . esc_html($label) . '<input type="text" name="client[dynamic][' . esc_attr($key) . ']" /></label>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Listę pól konfigurujesz w sekcji „Pola klientów”.', 'estate-office') . '</p>';
            echo '</fieldset>';
        }
        echo '<div class="estate-office-quick-create__actions">';
        echo '<button type="submit" class="estate-office-quick-create__primary">' . esc_html__('Zapisz klienta', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-quick-create__message" data-eo-quick-message></div>';
        echo '</form>';
    }

    /**
     * @param array<string,string> $propertyTypes
     * @param array<int,array<string,string>> $dynamicFields
     */
    private static function renderSearchForm(string $action, array $propertyTypes, array $dynamicFields): void
    {
        echo '<form class="estate-office-quick-create__form" data-eo-quick-form="search">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce(self::NONCE_ACTION)) . '" />';
        echo '<div class="estate-office-quick-create__grid">';
        echo '<label>' . esc_html__('Numer poszukiwania', 'estate-office') . '<span class="required">*</span><input type="text" name="search[estate_search_reference]" required /></label>';
        echo '<label>' . esc_html__('Typ transakcji', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="search[estate_search_transaction_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        echo '<option value="buy">' . esc_html__('Kupno', 'estate-office') . '</option>';
        echo '<option value="lease">' . esc_html__('Najem', 'estate-office') . '</option>';
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Rodzaj nieruchomości', 'estate-office') . '<span class="required">*</span>';
        echo '<select name="search[estate_search_property_type]" required>';
        echo '<option value="">' . esc_html__('Wybierz', 'estate-office') . '</option>';
        foreach ($propertyTypes as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label>' . esc_html__('Budżet od', 'estate-office') . '<input type="number" step="0.01" min="0" name="search[estate_search_budget_min]" /></label>';
        echo '<label>' . esc_html__('Budżet do', 'estate-office') . '<input type="number" step="0.01" min="0" name="search[estate_search_budget_max]" /></label>';
        echo '<label>' . esc_html__('Lokalizacja', 'estate-office') . '<input type="text" name="search[estate_search_location]" /></label>';
        echo '<label>' . esc_html__('Minimalny metraż', 'estate-office') . '<input type="number" step="0.01" min="0" name="search[estate_search_area_min]" /></label>';
        echo '<label>' . esc_html__('Maksymalny metraż', 'estate-office') . '<input type="number" step="0.01" min="0" name="search[estate_search_area_max]" /></label>';
        echo '</div>';
        echo '<label class="estate-office-quick-create__wide">' . esc_html__('Opis poszukiwania', 'estate-office') . '<textarea name="search[estate_search_description]" rows="4"></textarea></label>';
        if (!empty($dynamicFields)) {
            echo '<fieldset class="estate-office-quick-create__fieldset">';
            echo '<legend>' . esc_html__('Pola dodatkowe', 'estate-office') . '</legend>';
            echo '<div class="estate-office-quick-create__grid">';
            foreach ($dynamicFields as $field) {
                $key   = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? '');
                if ($key === '' || $label === '') {
                    continue;
                }
                echo '<label>' . esc_html($label) . '<input type="text" name="search[dynamic][' . esc_attr($key) . ']" /></label>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Lista pól pochodzi z ustawień sekcji „Pola poszukiwań”.', 'estate-office') . '</p>';
            echo '</fieldset>';
        }
        echo '<div class="estate-office-quick-create__actions">';
        echo '<button type="submit" class="estate-office-quick-create__primary">' . esc_html__('Zapisz poszukiwanie', 'estate-office') . '</button>';
        echo '</div>';
        echo '<div class="estate-office-quick-create__message" data-eo-quick-message></div>';
        echo '</form>';
    }

    private static function handleCreateProperty(): void
    {
        self::verifyAccess('property');

        $input = isset($_POST['property']) && is_array($_POST['property']) ? $_POST['property'] : [];
        $transaction = isset($input['transaction_type']) ? sanitize_key((string) wp_unslash($input['transaction_type'])) : '';
        $propertyType = isset($input['property_type']) ? sanitize_key((string) wp_unslash($input['property_type'])) : '';
        $description = isset($input['description']) && is_scalar($input['description']) ? wpautop(wp_kses_post((string) wp_unslash($input['description']))) : '';

        if (!in_array($transaction, ['sale', 'rent_out'], true)) {
            wp_send_json_error(['message' => esc_html__('Wybierz prawidłowy typ transakcji.', 'estate-office')]);
        }

        if (!isset(SearchMeta::PROPERTY_TYPES[$propertyType])) {
            wp_send_json_error(['message' => esc_html__('Wybierz rodzaj nieruchomości.', 'estate-office')]);
        }

        $values  = PropertyMeta::prepareValues($input);
        $dynamic = PropertyMeta::prepareDynamicValues($input['dynamic'] ?? []);

        if ($values['estate_property_reference'] === '') {
            wp_send_json_error(['message' => esc_html__('Numer oferty jest wymagany.', 'estate-office')]);
        }

        if ($values['estate_property_city'] === '') {
            wp_send_json_error(['message' => esc_html__('Miasto jest wymagane.', 'estate-office')]);
        }

        if ($values['estate_property_manager'] === '') {
            $values['estate_property_manager'] = (string) get_current_user_id();
        }

        $titleParts = [];
        $typeLabel = SearchMeta::PROPERTY_TYPES[$propertyType] ?? '';
        if ($typeLabel !== '') {
            $titleParts[] = $typeLabel;
        }
        if ($values['estate_property_city'] !== '') {
            $titleParts[] = wp_strip_all_tags((string) $values['estate_property_city']);
        }
        if ($values['estate_property_street'] !== '') {
            $street = (string) $values['estate_property_street'];
            if ($values['estate_property_number'] !== '') {
                $street .= ' ' . $values['estate_property_number'];
            }
            $titleParts[] = wp_strip_all_tags($street);
        }

        $postId = wp_insert_post([
            'post_type'   => PropertyRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $titleParts ? implode(' • ', $titleParts) : wp_strip_all_tags($values['estate_property_reference']),
            'post_content'=> $description,
            'post_author' => get_current_user_id(),
        ], true);

        if ($postId instanceof WP_Error) {
            wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć nieruchomości.', 'estate-office')]);
        }

        PropertyMeta::persistValues($postId, $values, $dynamic);

        self::assignTerms($postId, 'estate_transaction_type', $transaction, AgreementMeta::TRANSACTION_TYPES[$transaction] ?? $transaction);
        self::assignTerms($postId, 'estate_property_type', $propertyType, $typeLabel);

        if ($values['estate_property_city'] !== '') {
            $citySlug = sanitize_title((string) $values['estate_property_city']);
            self::assignTerms($postId, 'estate_city', $citySlug, (string) $values['estate_property_city']);
        }
        if ($values['estate_property_district'] !== '') {
            $districtSlug = sanitize_title((string) $values['estate_property_district']);
            self::assignTerms($postId, 'estate_district', $districtSlug, (string) $values['estate_property_district']);
        }

        wp_send_json_success([
            'redirect' => self::buildRecordUrl('properties', (int) $postId),
        ]);
    }

    private static function handleCreateClient(): void
    {
        self::verifyAccess('client');

        $input = isset($_POST['client']) && is_array($_POST['client']) ? $_POST['client'] : [];
        $values  = ClientMeta::prepareValues($input);
        $dynamic = ClientMeta::prepareDynamicValues($input['dynamic'] ?? []);

        if ($values['estate_client_manager'] === '') {
            $values['estate_client_manager'] = (string) get_current_user_id();
        }

        if ($values['estate_client_type'] === 'company') {
            if ($values['estate_client_company_name'] === '' && $values['estate_client_company_representative'] === '') {
                wp_send_json_error(['message' => esc_html__('Podaj nazwę firmy lub reprezentanta.', 'estate-office')]);
            }
        } else {
            if ($values['estate_client_first_name'] === '' && $values['estate_client_last_name'] === '') {
                wp_send_json_error(['message' => esc_html__('Podaj imię lub nazwisko klienta.', 'estate-office')]);
            }
        }

        $postId = wp_insert_post([
            'post_type'   => ClientRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => __('Nowy klient', 'estate-office'),
            'post_author' => get_current_user_id(),
        ], true);

        if ($postId instanceof WP_Error) {
            wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć klienta.', 'estate-office')]);
        }

        ClientMeta::persistValues($postId, $values, $dynamic);

        wp_send_json_success([
            'redirect' => self::buildRecordUrl('clients', (int) $postId),
        ]);
    }

    private static function handleCreateSearch(): void
    {
        self::verifyAccess('search');

        $input = isset($_POST['search']) && is_array($_POST['search']) ? $_POST['search'] : [];

        $values  = SearchMeta::prepareValues($input);
        $dynamic = SearchMeta::prepareDynamicValues($input['dynamic'] ?? []);

        if ($values['estate_search_reference'] === '') {
            wp_send_json_error(['message' => esc_html__('Numer poszukiwania jest wymagany.', 'estate-office')]);
        }

        if ($values['estate_search_transaction_type'] === '' || !isset(SearchMeta::TRANSACTION_TYPES[$values['estate_search_transaction_type']])) {
            wp_send_json_error(['message' => esc_html__('Wybierz prawidłowy typ transakcji.', 'estate-office')]);
        }

        if ($values['estate_search_property_type'] === '' || !isset(SearchMeta::PROPERTY_TYPES[$values['estate_search_property_type']])) {
            wp_send_json_error(['message' => esc_html__('Wybierz rodzaj nieruchomości.', 'estate-office')]);
        }

        if ($values['estate_search_manager'] === '') {
            $values['estate_search_manager'] = (string) get_current_user_id();
        }

        $postId = wp_insert_post([
            'post_type'   => SearchRegister::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => wp_strip_all_tags($values['estate_search_reference']),
            'post_content'=> isset($values['estate_search_description']) ? wpautop(wp_kses_post((string) $values['estate_search_description'])) : '',
            'post_author' => get_current_user_id(),
        ], true);

        if ($postId instanceof WP_Error) {
            wp_send_json_error(['message' => esc_html__('Nie udało się utworzyć poszukiwania.', 'estate-office')]);
        }

        SearchMeta::persistValues($postId, $values, $dynamic);

        wp_send_json_success([
            'redirect' => self::buildRecordUrl('searches', (int) $postId),
        ]);
    }

    /**
     * @return array<string,array{label:string,action:string}>
     */
    public static function getAvailableTypes(): array
    {
        $available = [];
        foreach (self::TYPES as $type => $config) {
            if (self::userCanCreate($type)) {
                $available[$type] = [
                    'label'       => $config['label'],
                    'action'      => $config['action'],
                    'description' => $config['description'],
                ];
            }
        }

        return $available;
    }

    public static function userCanCreate(string $type): bool
    {
        $config = self::TYPES[$type] ?? null;
        if (!$config) {
            return false;
        }

        return current_user_can($config['capability']);
    }

    private static function currentUserCanCreateAnything(): bool
    {
        foreach (self::TYPES as $type => $config) {
            if (current_user_can($config['capability'])) {
                return true;
            }
        }

        return false;
    }

    private static function verifyAccess(string $type): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => esc_html__('Musisz być zalogowany.', 'estate-office')]);
        }

        if (!self::userCanCreate($type)) {
            wp_send_json_error(['message' => esc_html__('Brak uprawnień do utworzenia rekordu.', 'estate-office')]);
        }
    }

    private static function assignTerms(int $postId, string $taxonomy, string $slug, string $label): void
    {
        if ($slug === '') {
            return;
        }

        if (!term_exists($slug, $taxonomy)) {
            wp_insert_term($label !== '' ? $label : $slug, $taxonomy, ['slug' => $slug]);
        }

        wp_set_post_terms($postId, [$slug], $taxonomy, false);
    }

    private static function buildRecordUrl(string $section, int $recordId): string
    {
        return add_query_arg([
            CRM::SECTION_PARAM   => $section,
            CRM::RECORD_PARAM    => $section,
            CRM::RECORD_ID_PARAM => (string) $recordId,
        ], CRM::getCrmBaseUrl());
    }
}
