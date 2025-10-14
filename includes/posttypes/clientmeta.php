<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use WP_Post;

use function esc_url;
use function get_edit_post_link;
use function get_post;
use function get_post_meta;
use function get_the_title;
use function get_user_by;
use function wp_dropdown_users;

defined('ABSPATH') || exit;

final class ClientMeta
{
    public const AGREEMENTS_META_KEY = 'estate_client_agreements';

    public const CLIENT_TYPES = [
        'person'  => 'Osoba fizyczna',
        'company' => 'Firma',
    ];

    public const DOCUMENT_TYPES = [
        'id_card'       => 'Dowód osobisty',
        'passport'      => 'Paszport',
        'residence_card'=> 'Karta pobytu',
    ];

    private const META_FIELDS = [
        'estate_client_reference'                 => ['type' => 'string'],
        'estate_client_type'                      => ['type' => 'enum', 'values' => self::CLIENT_TYPES],
        'estate_client_first_name'                => ['type' => 'string'],
        'estate_client_last_name'                 => ['type' => 'string'],
        'estate_client_company_name'              => ['type' => 'string'],
        'estate_client_company_representative'    => ['type' => 'string'],
        'estate_client_phone'                     => ['type' => 'phone'],
        'estate_client_email'                     => ['type' => 'email'],
        'estate_client_website'                   => ['type' => 'url'],
        'estate_client_manager'                   => ['type' => 'user'],
        'estate_client_pesel'                     => ['type' => 'digits'],
        'estate_client_document_type'             => ['type' => 'enum', 'values' => self::DOCUMENT_TYPES],
        'estate_client_document_number'           => ['type' => 'identifier'],
        'estate_client_tax_id'                    => ['type' => 'digits'],
        'estate_client_krs'                       => ['type' => 'digits'],
        'estate_client_regon'                     => ['type' => 'digits'],
        'estate_client_address_street'            => ['type' => 'string'],
        'estate_client_address_number'            => ['type' => 'string'],
        'estate_client_address_unit'              => ['type' => 'string'],
        'estate_client_address_postal_code'       => ['type' => 'postal'],
        'estate_client_address_city'              => ['type' => 'string'],
        'estate_client_address_country'           => ['type' => 'string'],
        'estate_client_correspondence_same'       => ['type' => 'boolean'],
        'estate_client_correspondence_street'     => ['type' => 'string'],
        'estate_client_correspondence_number'     => ['type' => 'string'],
        'estate_client_correspondence_unit'       => ['type' => 'string'],
        'estate_client_correspondence_postal_code'=> ['type' => 'postal'],
        'estate_client_correspondence_city'       => ['type' => 'string'],
        'estate_client_correspondence_country'    => ['type' => 'string'],
    ];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerMeta']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . ClientRegister::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    public static function registerMeta(): void
    {
        foreach (self::META_FIELDS as $key => $definition) {
            $restType = self::resolveRestType($definition);

            register_post_meta(
                ClientRegister::POST_TYPE,
                $key,
                [
                    'type'              => $restType,
                    'single'            => true,
                    'show_in_rest'      => true,
                    'auth_callback'     => [self::class, 'canEditMeta'],
                    'sanitize_callback' => self::buildSanitizer($definition),
                ]
            );
        }

        register_post_meta(
            ClientRegister::POST_TYPE,
            self::AGREEMENTS_META_KEY,
            [
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                'auth_callback'     => [self::class, 'canEditMeta'],
                'sanitize_callback' => static fn($value) => self::sanitizeAgreementRelations($value),
            ]
        );
    }

    private static function resolveRestType(array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'boolean' => 'boolean',
            'user'    => 'integer',
            default   => 'string',
        };
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'estate-office-client-crm',
            __('Informacje CRM', 'estate-office'),
            [self::class, 'renderCrmBox'],
            ClientRegister::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'estate-office-client-general',
            __('Dane podstawowe', 'estate-office'),
            [self::class, 'renderGeneralBox'],
            ClientRegister::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'estate-office-client-contact',
            __('Kontakt', 'estate-office'),
            [self::class, 'renderContactBox'],
            ClientRegister::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'estate-office-client-identification',
            __('Dane identyfikacyjne', 'estate-office'),
            [self::class, 'renderIdentificationBox'],
            ClientRegister::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'estate-office-client-address',
            __('Adresy', 'estate-office'),
            [self::class, 'renderAddressBox'],
            ClientRegister::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function renderCrmBox(WP_Post $post): void
    {
        wp_nonce_field('estate_office_client_meta', 'estate_office_client_meta_nonce');

        $reference = esc_attr(get_post_meta($post->ID, 'estate_client_reference', true));
        $manager   = (int) get_post_meta($post->ID, 'estate_client_manager', true);

        echo '<p>';
        echo '<label for="estate_client_reference"><strong>' . esc_html__('Numer klienta', 'estate-office') . '</strong></label>';
        printf('<input type="text" id="estate_client_reference" name="estate_client_reference" value="%s" class="widefat" />', $reference);
        echo '<span class="description">' . esc_html__('Unikalny identyfikator wykorzystywany w CRM.', 'estate-office') . '</span>';
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_client_manager"><strong>' . esc_html__('Opiekun', 'estate-office') . '</strong></label>';
        wp_dropdown_users([
            'name'             => 'estate_client_manager',
            'id'               => 'estate_client_manager',
            'selected'         => $manager,
            'show_option_none' => __('— Wybierz opiekuna —', 'estate-office'),
            'class'            => 'widefat',
            'role__in'         => ['estate_agent', 'administrator'],
        ]);
        echo '</p>';

        self::renderAgreementsSummary($post);
    }

    private static function renderAgreementsSummary(WP_Post $post): void
    {
        $agreements = self::sanitizeAgreementRelations(get_post_meta($post->ID, self::AGREEMENTS_META_KEY, true));

        echo '<hr />';
        echo '<strong>' . esc_html__('Powiązane umowy', 'estate-office') . '</strong>';

        if (empty($agreements)) {
            echo '<p class="description">' . esc_html__('Brak przypisanych umów. Powiąż klienta podczas edycji umowy.', 'estate-office') . '</p>';

            return;
        }

        echo '<ul class="estate-office-related-agreements">';

        foreach ($agreements as $agreementId) {
            $label = self::formatAgreementLabel($agreementId);

            if ($label === '') {
                continue;
            }

            $editLink = get_edit_post_link($agreementId);

            if ($editLink) {
                printf('<li><a href="%s">%s</a></li>', esc_url($editLink), esc_html($label));
            } else {
                printf('<li>%s</li>', esc_html($label));
            }
        }

        echo '</ul>';
    }

    private static function formatAgreementLabel(int $agreementId): string
    {
        if ($agreementId <= 0) {
            return '';
        }

        $agreement = get_post($agreementId);

        if (!$agreement || $agreement->post_type !== AgreementRegister::POST_TYPE) {
            return '';
        }

        $number = trim((string) get_post_meta($agreementId, 'estate_agreement_number', true));

        if ($number !== '') {
            return $number;
        }

        $title = trim((string) get_the_title($agreement));

        if ($title !== '') {
            return $title;
        }

        return sprintf(__('Umowa #%d', 'estate-office'), $agreementId);
    }

    private static function sanitizeAgreementRelations($value): array
    {
        if (!is_array($value)) {
            $value = $value === '' ? [] : [$value];
        }

        $ids = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                continue;
            }

            $id = (int) $item;

            if ($id <= 0) {
                continue;
            }

            $agreement = get_post($id);

            if (!$agreement || $agreement->post_type !== AgreementRegister::POST_TYPE) {
                continue;
            }

            $ids[] = $agreement->ID;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    public static function renderGeneralBox(WP_Post $post): void
    {
        $type        = (string) get_post_meta($post->ID, 'estate_client_type', true);
        if ($type === '') {
            $type = 'person';
        }
        $firstName   = esc_attr(get_post_meta($post->ID, 'estate_client_first_name', true));
        $lastName    = esc_attr(get_post_meta($post->ID, 'estate_client_last_name', true));
        $companyName = esc_attr(get_post_meta($post->ID, 'estate_client_company_name', true));
        $represent   = esc_attr(get_post_meta($post->ID, 'estate_client_company_representative', true));

        echo '<p>';
        echo '<label for="estate_client_type"><strong>' . esc_html__('Typ klienta', 'estate-office') . '</strong></label>';
        echo '<select name="estate_client_type" id="estate_client_type" class="widefat">';
        foreach (self::CLIENT_TYPES as $key => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($type, $key, false), esc_html($label));
        }
        echo '</select>';
        echo '</p>';

        echo '<fieldset>';
        echo '<legend>' . esc_html__('Osoba fizyczna', 'estate-office') . '</legend>';
        echo '<p>';
        echo '<label for="estate_client_first_name">' . esc_html__('Imię', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_first_name" name="estate_client_first_name" class="widefat" value="%s" />', $firstName);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_last_name">' . esc_html__('Nazwisko', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_last_name" name="estate_client_last_name" class="widefat" value="%s" />', $lastName);
        echo '</p>';
        echo '</fieldset>';

        echo '<fieldset>';
        echo '<legend>' . esc_html__('Firma', 'estate-office') . '</legend>';
        echo '<p>';
        echo '<label for="estate_client_company_name">' . esc_html__('Nazwa firmy', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_company_name" name="estate_client_company_name" class="widefat" value="%s" />', $companyName);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_company_representative">' . esc_html__('Imię i nazwisko reprezentanta', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_company_representative" name="estate_client_company_representative" class="widefat" value="%s" />', $represent);
        echo '</p>';
        echo '</fieldset>';
    }

    public static function renderContactBox(WP_Post $post): void
    {
        $phone = esc_attr(get_post_meta($post->ID, 'estate_client_phone', true));
        $email = esc_attr(get_post_meta($post->ID, 'estate_client_email', true));
        $web   = esc_attr(get_post_meta($post->ID, 'estate_client_website', true));

        echo '<p>';
        echo '<label for="estate_client_phone"><strong>' . esc_html__('Telefon', 'estate-office') . '</strong></label>';
        printf('<input type="text" id="estate_client_phone" name="estate_client_phone" class="widefat" value="%s" />', $phone);
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_client_email"><strong>' . esc_html__('E-mail', 'estate-office') . '</strong></label>';
        printf('<input type="email" id="estate_client_email" name="estate_client_email" class="widefat" value="%s" />', $email);
        echo '</p>';

        echo '<p>';
        echo '<label for="estate_client_website"><strong>' . esc_html__('Strona WWW', 'estate-office') . '</strong></label>';
        printf('<input type="url" id="estate_client_website" name="estate_client_website" class="widefat" value="%s" />', $web);
        echo '</p>';
    }

    public static function renderIdentificationBox(WP_Post $post): void
    {
        $pesel    = esc_attr(get_post_meta($post->ID, 'estate_client_pesel', true));
        $docType  = (string) get_post_meta($post->ID, 'estate_client_document_type', true);
        $docNo    = esc_attr(get_post_meta($post->ID, 'estate_client_document_number', true));
        $taxId    = esc_attr(get_post_meta($post->ID, 'estate_client_tax_id', true));
        $krs      = esc_attr(get_post_meta($post->ID, 'estate_client_krs', true));
        $regon    = esc_attr(get_post_meta($post->ID, 'estate_client_regon', true));

        echo '<fieldset>';
        echo '<legend>' . esc_html__('Osoba fizyczna', 'estate-office') . '</legend>';
        echo '<p>';
        echo '<label for="estate_client_pesel">' . esc_html__('PESEL', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_pesel" name="estate_client_pesel" class="widefat" value="%s" maxlength="11" />', $pesel);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_document_type">' . esc_html__('Rodzaj dokumentu', 'estate-office') . '</label>';
        echo '<select name="estate_client_document_type" id="estate_client_document_type" class="widefat">';
        echo '<option value="">' . esc_html__('— Wybierz —', 'estate-office') . '</option>';
        foreach (self::DOCUMENT_TYPES as $key => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($docType, $key, false), esc_html($label));
        }
        echo '</select>';
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_document_number">' . esc_html__('Numer dokumentu', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_document_number" name="estate_client_document_number" class="widefat" value="%s" />', $docNo);
        echo '</p>';
        echo '</fieldset>';

        echo '<fieldset>';
        echo '<legend>' . esc_html__('Firma', 'estate-office') . '</legend>';
        echo '<p>';
        echo '<label for="estate_client_tax_id">' . esc_html__('NIP', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_tax_id" name="estate_client_tax_id" class="widefat" value="%s" maxlength="20" />', $taxId);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_krs">' . esc_html__('KRS', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_krs" name="estate_client_krs" class="widefat" value="%s" maxlength="20" />', $krs);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_regon">' . esc_html__('REGON', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_regon" name="estate_client_regon" class="widefat" value="%s" maxlength="20" />', $regon);
        echo '</p>';
        echo '</fieldset>';
    }

    public static function renderAddressBox(WP_Post $post): void
    {
        $street      = esc_attr(get_post_meta($post->ID, 'estate_client_address_street', true));
        $number      = esc_attr(get_post_meta($post->ID, 'estate_client_address_number', true));
        $unit        = esc_attr(get_post_meta($post->ID, 'estate_client_address_unit', true));
        $postal      = esc_attr(get_post_meta($post->ID, 'estate_client_address_postal_code', true));
        $city        = esc_attr(get_post_meta($post->ID, 'estate_client_address_city', true));
        $country     = esc_attr(get_post_meta($post->ID, 'estate_client_address_country', true));
        $same        = (bool) get_post_meta($post->ID, 'estate_client_correspondence_same', true);
        $cStreet     = esc_attr(get_post_meta($post->ID, 'estate_client_correspondence_street', true));
        $cNumber     = esc_attr(get_post_meta($post->ID, 'estate_client_correspondence_number', true));
        $cUnit       = esc_attr(get_post_meta($post->ID, 'estate_client_correspondence_unit', true));
        $cPostal     = esc_attr(get_post_meta($post->ID, 'estate_client_correspondence_postal_code', true));
        $cCity       = esc_attr(get_post_meta($post->ID, 'estate_client_correspondence_city', true));
        $cCountry    = esc_attr(get_post_meta($post->ID, 'estate_client_correspondence_country', true));

        echo '<h4>' . esc_html__('Adres zamieszkania/rejestrowy', 'estate-office') . '</h4>';
        echo '<p>';
        echo '<label for="estate_client_address_street">' . esc_html__('Ulica', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_address_street" name="estate_client_address_street" class="widefat" value="%s" />', $street);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_address_number">' . esc_html__('Numer', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_address_number" name="estate_client_address_number" class="widefat" value="%s" />', $number);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_address_unit">' . esc_html__('Lokal', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_address_unit" name="estate_client_address_unit" class="widefat" value="%s" />', $unit);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_address_postal_code">' . esc_html__('Kod pocztowy', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_address_postal_code" name="estate_client_address_postal_code" class="widefat" value="%s" />', $postal);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_address_city">' . esc_html__('Miasto', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_address_city" name="estate_client_address_city" class="widefat" value="%s" />', $city);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_address_country">' . esc_html__('Kraj', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_address_country" name="estate_client_address_country" class="widefat" value="%s" />', $country);
        echo '</p>';

        echo '<h4>' . esc_html__('Adres korespondencyjny', 'estate-office') . '</h4>';
        echo '<p>';
        printf('<label><input type="checkbox" name="estate_client_correspondence_same" value="1" %s /> %s</label>', checked($same, true, false), esc_html__('Adres korespondencyjny taki sam', 'estate-office'));
        echo '</p>';

        echo '<div class="estate-office-correspondence-fields">';
        echo '<p>';
        echo '<label for="estate_client_correspondence_street">' . esc_html__('Ulica', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_correspondence_street" name="estate_client_correspondence_street" class="widefat" value="%s" />', $cStreet);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_correspondence_number">' . esc_html__('Numer', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_correspondence_number" name="estate_client_correspondence_number" class="widefat" value="%s" />', $cNumber);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_correspondence_unit">' . esc_html__('Lokal', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_correspondence_unit" name="estate_client_correspondence_unit" class="widefat" value="%s" />', $cUnit);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_correspondence_postal_code">' . esc_html__('Kod pocztowy', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_correspondence_postal_code" name="estate_client_correspondence_postal_code" class="widefat" value="%s" />', $cPostal);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_correspondence_city">' . esc_html__('Miasto', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_correspondence_city" name="estate_client_correspondence_city" class="widefat" value="%s" />', $cCity);
        echo '</p>';
        echo '<p>';
        echo '<label for="estate_client_correspondence_country">' . esc_html__('Kraj', 'estate-office') . '</label>';
        printf('<input type="text" id="estate_client_correspondence_country" name="estate_client_correspondence_country" class="widefat" value="%s" />', $cCountry);
        echo '</p>';
        echo '</div>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== ClientRegister::POST_TYPE) {
            return;
        }

        if (!isset($_POST['estate_office_client_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST['estate_office_client_meta_nonce']));

        if (!wp_verify_nonce($nonce, 'estate_office_client_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $values = [];

        foreach (self::META_FIELDS as $key => $definition) {
            if (($definition['type'] ?? '') === 'boolean') {
                $values[$key] = self::sanitizeBoolean(isset($_POST[$key]) ? '1' : '0');
                continue;
            }

            $raw = $_POST[$key] ?? '';
            if (is_array($raw)) {
                $raw = '';
            }

            $values[$key] = self::sanitizeValue(wp_unslash((string) $raw), $definition);
        }

        if ($values['estate_client_type'] === '') {
            $values['estate_client_type'] = 'person';
        }

        if ($values['estate_client_type'] === 'company') {
            $values['estate_client_first_name']      = '';
            $values['estate_client_last_name']       = '';
            $values['estate_client_pesel']           = '';
            $values['estate_client_document_type']   = '';
            $values['estate_client_document_number'] = '';
        } else {
            $values['estate_client_company_name']           = '';
            $values['estate_client_company_representative'] = '';
            $values['estate_client_tax_id']                 = '';
            $values['estate_client_krs']                    = '';
            $values['estate_client_regon']                  = '';
        }

        if (!empty($values['estate_client_correspondence_same'])) {
            $values['estate_client_correspondence_street']      = '';
            $values['estate_client_correspondence_number']      = '';
            $values['estate_client_correspondence_unit']        = '';
            $values['estate_client_correspondence_postal_code'] = '';
            $values['estate_client_correspondence_city']        = '';
            $values['estate_client_correspondence_country']     = '';
        }

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }

        $title = self::buildDisplayName($values);
        self::synchroniseTitle($postId, $title);
    }

    private static function persistMeta(int $postId, string $key, $value, array $definition): void
    {
        if (($definition['type'] ?? '') === 'boolean') {
            update_post_meta($postId, $key, $value ? 1 : 0);
            return;
        }

        if (($definition['type'] ?? '') === 'user') {
            if ($value === '') {
                delete_post_meta($postId, $key);
                return;
            }

            update_post_meta($postId, $key, (int) $value);
            return;
        }

        if ($value === '') {
            delete_post_meta($postId, $key);
            return;
        }

        update_post_meta($postId, $key, $value);
    }

    private static function buildDisplayName(array $values): string
    {
        $type = $values['estate_client_type'] ?? 'person';

        if ($type === 'company') {
            $company = trim((string) ($values['estate_client_company_name'] ?? ''));
            if ($company !== '') {
                return $company;
            }

            $representative = trim((string) ($values['estate_client_company_representative'] ?? ''));
            if ($representative !== '') {
                return $representative;
            }
        } else {
            $first = trim((string) ($values['estate_client_first_name'] ?? ''));
            $last  = trim((string) ($values['estate_client_last_name'] ?? ''));

            $parts = array_filter([$first, $last], static fn (string $part): bool => $part !== '');
            if (!empty($parts)) {
                return implode(' ', $parts);
            }
        }

        $reference = trim((string) ($values['estate_client_reference'] ?? ''));
        if ($reference !== '') {
            return $reference;
        }

        return '';
    }

    private static function synchroniseTitle(int $postId, string $title): void
    {
        $title = trim($title);

        if ($title === '') {
            return;
        }

        remove_action('save_post_' . ClientRegister::POST_TYPE, [self::class, 'save'], 10);

        wp_update_post([
            'ID'         => $postId,
            'post_title' => $title,
            'post_name'  => sanitize_title($title),
        ]);

        add_action('save_post_' . ClientRegister::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    private static function sanitizeValue(string $value, array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'enum'       => self::sanitizeEnum($value, (array) ($definition['values'] ?? [])),
            'phone'      => self::sanitizePhone($value),
            'email'      => self::sanitizeEmail($value),
            'url'        => self::sanitizeUrl($value),
            'user'       => self::sanitizeUser($value),
            'digits'     => self::sanitizeDigits($value),
            'identifier' => self::sanitizeIdentifier($value),
            'postal'     => self::sanitizePostalCode($value),
            default      => self::sanitizeLine($value),
        };
    }

    private static function sanitizeLine(string $value): string
    {
        $value = sanitize_text_field($value);

        return trim($value);
    }

    private static function sanitizeEnum(string $value, array $allowed): string
    {
        $value = sanitize_key($value);

        return array_key_exists($value, $allowed) ? $value : '';
    }

    private static function sanitizePhone(string $value): string
    {
        $value = preg_replace('/[^0-9\+\-\s\(\)]/', '', $value);

        return trim((string) $value);
    }

    private static function sanitizeEmail(string $value): string
    {
        $value = sanitize_email($value);

        return trim((string) $value);
    }

    private static function sanitizeUrl(string $value): string
    {
        $value = esc_url_raw($value);

        return trim((string) $value);
    }

    private static function sanitizeDigits(string $value): string
    {
        $value = preg_replace('/[^0-9]/', '', $value);

        return trim((string) $value);
    }

    private static function sanitizeIdentifier(string $value): string
    {
        $value = preg_replace('/[^0-9A-Z]/i', '', $value);

        return trim((string) $value);
    }

    private static function sanitizePostalCode(string $value): string
    {
        $value = strtoupper((string) preg_replace('/[^0-9A-Z\-]/i', '', $value));

        return trim($value);
    }

    private static function sanitizeBoolean(string $value): bool
    {
        return $value === '1' || $value === 'true' || $value === 'yes';
    }

    private static function sanitizeUser(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $userId = absint($value);

        if ($userId <= 0 || !get_user_by('id', $userId)) {
            return '';
        }

        return (string) $userId;
    }

    private static function buildSanitizer(array $definition): callable
    {
        return static function ($value) use ($definition) {
            $type = $definition['type'] ?? 'string';

            if ($type === 'boolean') {
                $value = is_scalar($value) ? (string) $value : '0';

                return ClientMeta::sanitizeBoolean($value);
            }

            $value = is_scalar($value) ? (string) $value : '';

            return ClientMeta::sanitizeValue($value, $definition);
        };
    }

    public static function canEditMeta(bool $allowed, string $metaKey, int $postId): bool
    {
        unset($allowed, $metaKey);

        return current_user_can('edit_post', $postId);
    }
}
