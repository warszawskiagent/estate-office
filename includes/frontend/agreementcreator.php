<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\PostTypes\AgreementMeta;
use EstateOffice\PostTypes\AgreementRegister;
use EstateOffice\PostTypes\ClientMeta;
use EstateOffice\PostTypes\ClientRegister;
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
use function is_user_logged_in;
use function plugins_url;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_insert_post;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_update_post;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

final class AgreementCreator
{
    private const NONCE_ACTION = 'estate_office_agreement_creator';
    private const STYLE_HANDLE = 'estate-office-agreement-creator';
    private const SCRIPT_HANDLE = 'estate-office-agreement-creator';

    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);

        add_action('wp_ajax_estate_office_create_agreement', [self::class, 'handleCreateAgreement']);
        add_action('wp_ajax_estate_office_search_clients', [self::class, 'handleSearchClients']);
        add_action('wp_ajax_estate_office_attach_client', [self::class, 'handleAttachClient']);
        add_action('wp_ajax_estate_office_detach_client', [self::class, 'handleDetachClient']);
        add_action('wp_ajax_estate_office_create_client', [self::class, 'handleCreateClient']);
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
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce(self::NONCE_ACTION),
            'baseUrl'      => CRM::getCrmBaseUrl(),
            'sectionParam' => CRM::SECTION_PARAM,
            'recordParam'  => CRM::RECORD_PARAM,
            'recordIdParam'=> CRM::RECORD_ID_PARAM,
            'messages'     => [
                'step1Success'  => esc_html__('Umowa została zapisana. Dodaj klientów, aby kontynuować.', 'estate-office'),
                'step1Error'    => esc_html__('Nie udało się zapisać danych umowy. Sprawdź poprawność pól.', 'estate-office'),
                'clientAdded'   => esc_html__('Klient został przypisany do umowy.', 'estate-office'),
                'clientRemoved' => esc_html__('Klient został odłączony od umowy.', 'estate-office'),
                'clientCreated' => esc_html__('Nowy klient został zapisany i przypisany do umowy.', 'estate-office'),
                'finalizeSuccess' => esc_html__('Umowa została utworzona. Możesz przejść do jej szczegółów.', 'estate-office'),
                'genericError'  => esc_html__('Wystąpił błąd podczas przetwarzania żądania. Spróbuj ponownie.', 'estate-office'),
                'missingClients'=> esc_html__('Dodaj przynajmniej jednego klienta, aby zakończyć proces.', 'estate-office'),
                'emptyClients'  => esc_html__('Brak przypisanych klientów. Dodaj klienta, aby kontynuować.', 'estate-office'),
                'noResults'     => esc_html__('Brak dopasowanych klientów.', 'estate-office'),
                'viewAgreement' => esc_html__('Przejdź do szczegółów umowy', 'estate-office'),
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
        echo '<div class="estate-office-agreement-creator__form" data-eo-agreement-step="3" hidden>';
        echo '<h2>' . esc_html__('Kolejne kroki', 'estate-office') . '</h2>';
        echo '<p class="description">' . esc_html__('Kreator umów wkrótce pozwoli na bezpośrednie dodawanie nieruchomości lub poszukiwań. Na tym etapie możesz przejść do szczegółów umowy, aby kontynuować pracę w CRM.', 'estate-office') . '</p>';
        echo '<div class="estate-office-agreement-creator__summary" data-eo-agreement-summary></div>';
        echo '<div class="estate-office-agreement-creator__actions">';
        echo '<button type="button" class="estate-office-agreement-creator__primary" data-eo-agreement-finish>' . esc_html__('Przejdź do umowy', 'estate-office') . '</button>';
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

            AgreementMeta::persistValues($agreementId, $values);
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
                [],
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

        $url = add_query_arg(
            [
                CRM::SECTION_PARAM     => 'agreements',
                CRM::RECORD_PARAM      => 'agreements',
                CRM::RECORD_ID_PARAM   => (string) $agreementId,
            ],
            CRM::getCrmBaseUrl()
        );

        wp_send_json_success([
            'redirect' => $url,
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

