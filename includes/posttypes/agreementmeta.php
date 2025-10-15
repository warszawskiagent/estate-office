<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use DateTimeImmutable;
use EstateOffice\Settings\GeneralSettings;
use WP_Post;
use function get_post;
use function get_post_meta;
use function get_post_type;
use function get_posts;
use function get_the_title;

defined('ABSPATH') || exit;

final class AgreementMeta
{
    private const META_FIELDS = [
        'estate_agreement_number'             => ['type' => 'string'],
        'estate_agreement_transaction_type'   => ['type' => 'enum', 'values' => self::TRANSACTION_TYPES],
        'estate_agreement_start_date'         => ['type' => 'date'],
        'estate_agreement_end_date'           => ['type' => 'date'],
        'estate_agreement_is_indefinite'      => ['type' => 'boolean'],
        'estate_agreement_commission_amount'  => ['type' => 'decimal', 'precision' => 2],
        'estate_agreement_commission_unit'    => ['type' => 'enum', 'values' => self::COMMISSION_UNITS],
        'estate_agreement_clients'            => ['type' => 'relation', 'post_type' => ClientRegister::POST_TYPE],
        'estate_agreement_properties'         => ['type' => 'relation', 'post_type' => PropertyRegister::POST_TYPE],
        'estate_agreement_searches'           => ['type' => 'relation', 'post_type' => SearchRegister::POST_TYPE],
        'estate_agreement_stage'              => ['type' => 'enum', 'values' => self::STAGES],
        'estate_agreement_stage_history'      => ['type' => 'array'],
    ];

    private const DYNAMIC_FIELDS_META_KEY = 'estate_agreement_dynamic_fields';

    public const TRANSACTION_TYPES = [
        'sale'      => 'Sprzedaż',
        'purchase'  => 'Kupno',
        'rent_out'  => 'Wynajem',
        'lease'     => 'Najem',
    ];

    private const COMMISSION_UNITS = [
        'percent' => '%',
        'pln'     => 'PLN',
        'eur'     => 'EUR',
        'usd'     => 'USD',
    ];

    public const STAGES = [
        'intermediation'      => 'Umowa pośrednictwa',
        'mls_publication'     => 'Publikacja w MLS',
        'offer_preparation'   => 'Przygotowanie oferty',
        'offer_publication'   => 'Publikacja oferty',
        'marketing'           => 'Marketing i prezentacje',
        'purchase_offer'      => 'Oferta kupna',
        'negotiations'        => 'Negocjacje',
        'preliminary'         => 'Umowa przedwstępna',
        'final'               => 'Umowa przyrzeczona',
        'handover'            => 'Przekazanie lokalu',
        'completed'           => 'Umowa zakończona',
    ];

    private const DEFAULT_STAGE = 'intermediation';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerMeta']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . AgreementRegister::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    public static function registerMeta(): void
    {
        foreach (self::META_FIELDS as $key => $definition) {
            $restConfig = self::buildRestConfig($definition);

            register_post_meta(
                AgreementRegister::POST_TYPE,
                $key,
                [
                    'type'              => $restConfig['type'],
                    'single'            => true,
                    'show_in_rest'      => $restConfig['show_in_rest'],
                    'auth_callback'     => [self::class, 'canEditMeta'],
                    'sanitize_callback' => self::buildSanitizer($definition),
                ]
            );
        }

        register_post_meta(
            AgreementRegister::POST_TYPE,
            self::DYNAMIC_FIELDS_META_KEY,
            [
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => [
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'auth_callback'     => [self::class, 'canEditMeta'],
                'sanitize_callback' => [self::class, 'sanitizeDynamicFields'],
            ]
        );
    }

    private static function buildRestConfig(array $definition): array
    {
        if (($definition['type'] ?? '') === 'array') {
            return [
                'type'         => 'array',
                'show_in_rest' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'stage' => [
                                    'type' => 'string',
                                ],
                                'date'  => [
                                    'type'   => 'string',
                                    'format' => 'date',
                                ],
                            ],
                            'required'   => ['stage', 'date'],
                        ],
                    ],
                ],
            ];
        }

        if (($definition['type'] ?? '') === 'relation') {
            return [
                'type'         => 'array',
                'show_in_rest' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ];
        }

        return [
            'type'         => self::resolveRestType($definition),
            'show_in_rest' => true,
        ];
    }

    private static function resolveRestType(array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'boolean' => 'boolean',
            'decimal' => 'number',
            default   => 'string',
        };
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'estate-office-agreement-details',
            __('Szczegóły umowy', 'estate-office'),
            [self::class, 'renderDetailsBox'],
            AgreementRegister::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'estate-office-agreement-relations',
            __('Powiązania CRM', 'estate-office'),
            [self::class, 'renderRelationsBox'],
            AgreementRegister::POST_TYPE,
            'normal',
            'default'
        );

        if (!empty(GeneralSettings::getAgreementDynamicFields())) {
            add_meta_box(
                'estate-office-agreement-dynamic',
                __('Pola dodatkowe', 'estate-office'),
                [self::class, 'renderDynamicFieldsBox'],
                AgreementRegister::POST_TYPE,
                'normal',
                'default'
            );
        }

        add_meta_box(
            'estate-office-agreement-stage',
            __('Etap umowy', 'estate-office'),
            [self::class, 'renderStageBox'],
            AgreementRegister::POST_TYPE,
            'side'
        );
    }

    public static function renderRelationsBox(WP_Post $post): void
    {
        $transactionType = self::sanitizeEnum(
            (string) get_post_meta($post->ID, 'estate_agreement_transaction_type', true),
            self::TRANSACTION_TYPES
        );

        $clients    = self::ensureIntArray(get_post_meta($post->ID, 'estate_agreement_clients', true));
        $properties = self::ensureIntArray(get_post_meta($post->ID, 'estate_agreement_properties', true));
        $searches   = self::ensureIntArray(get_post_meta($post->ID, 'estate_agreement_searches', true));

        $clientOptions    = self::getRelationOptions(ClientRegister::POST_TYPE, $clients);
        $propertyOptions  = self::getRelationOptions(PropertyRegister::POST_TYPE, $properties);
        $searchOptions    = self::getRelationOptions(SearchRegister::POST_TYPE, $searches);

        $showProperties = in_array($transactionType, ['sale', 'rent_out'], true);
        $showSearches   = in_array($transactionType, ['purchase', 'lease'], true);

        echo '<div class="estate-office-agreement-relations">';

        self::renderRelationSelect(
            'estate_agreement_clients',
            __('Powiązani klienci', 'estate-office'),
            __('Wybierz jednego lub kilku klientów powiązanych z umową.', 'estate-office'),
            $clientOptions,
            $clients
        );

        printf(
            '<div data-relation="properties" style="%s">',
            $showProperties ? '' : 'display:none;'
        );

        self::renderRelationSelect(
            'estate_agreement_properties',
            __('Powiązane nieruchomości', 'estate-office'),
            __('Wybierz nieruchomości powiązane z transakcją sprzedaży lub wynajmu.', 'estate-office'),
            $propertyOptions,
            $properties
        );

        echo '</div>';

        printf(
            '<div data-relation="searches" style="%s">',
            $showSearches ? '' : 'display:none;'
        );

        self::renderRelationSelect(
            'estate_agreement_searches',
            __('Powiązane poszukiwania', 'estate-office'),
            __('Wybierz aktywne poszukiwania klientów dla umów kupna lub najmu.', 'estate-office'),
            $searchOptions,
            $searches
        );

        echo '</div>';
        echo '</div>';

        ?>
        <script>
            (function() {
                const typeField = document.getElementById('estate_agreement_transaction_type');
                const propertyGroup = document.querySelector('.estate-office-agreement-relations [data-relation="properties"]');
                const searchGroup = document.querySelector('.estate-office-agreement-relations [data-relation="searches"]');

                if (!typeField || !propertyGroup || !searchGroup) {
                    return;
                }

                const toggle = () => {
                    const value = typeField.value;
                    if (value === 'sale' || value === 'rent_out') {
                        propertyGroup.style.display = '';
                    } else {
                        propertyGroup.style.display = 'none';
                    }

                    if (value === 'purchase' || value === 'lease') {
                        searchGroup.style.display = '';
                    } else {
                        searchGroup.style.display = 'none';
                    }
                };

                typeField.addEventListener('change', toggle);
                toggle();
            })();
        </script>
        <?php
    }

    public static function renderDetailsBox(WP_Post $post): void
    {
        wp_nonce_field('estate_office_agreement_meta', 'estate_office_agreement_meta_nonce');

        $number      = get_post_meta($post->ID, 'estate_agreement_number', true);
        $type        = get_post_meta($post->ID, 'estate_agreement_transaction_type', true);
        $startDate   = get_post_meta($post->ID, 'estate_agreement_start_date', true);
        $endDate     = get_post_meta($post->ID, 'estate_agreement_end_date', true);
        $indefinite  = (bool) get_post_meta($post->ID, 'estate_agreement_is_indefinite', true);
        $commission  = get_post_meta($post->ID, 'estate_agreement_commission_amount', true);
        $commissionUnit = get_post_meta($post->ID, 'estate_agreement_commission_unit', true);

        ?>
        <p>
            <label for="estate_agreement_number"><strong><?php esc_html_e('Numer umowy', 'estate-office'); ?></strong></label>
            <input type="text" class="widefat" id="estate_agreement_number" name="estate_agreement_number" value="<?php echo esc_attr((string) $number); ?>" required />
        </p>
        <p>
            <label for="estate_agreement_transaction_type"><strong><?php esc_html_e('Typ transakcji', 'estate-office'); ?></strong></label>
            <select class="widefat" id="estate_agreement_transaction_type" name="estate_agreement_transaction_type" required>
                <option value=""><?php esc_html_e('Wybierz typ transakcji', 'estate-office'); ?></option>
                <?php foreach (self::TRANSACTION_TYPES as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <div style="display:flex; gap:12px;">
            <p style="flex:1;">
                <label for="estate_agreement_start_date"><strong><?php esc_html_e('Data zawarcia', 'estate-office'); ?></strong></label>
                <input type="date" class="widefat" id="estate_agreement_start_date" name="estate_agreement_start_date" value="<?php echo esc_attr((string) $startDate); ?>" />
            </p>
            <p style="flex:1;">
                <label for="estate_agreement_end_date"><strong><?php esc_html_e('Data zakończenia', 'estate-office'); ?></strong></label>
                <input type="date" class="widefat" id="estate_agreement_end_date" name="estate_agreement_end_date" value="<?php echo esc_attr((string) $endDate); ?>" <?php disabled($indefinite); ?> />
            </p>
        </div>
        <p>
            <label>
                <input type="checkbox" id="estate_agreement_is_indefinite" name="estate_agreement_is_indefinite" value="1" <?php checked($indefinite); ?> />
                <?php esc_html_e('Umowa bezterminowa', 'estate-office'); ?>
            </label>
        </p>
        <div style="display:flex; gap:12px;">
            <p style="flex:1;">
                <label for="estate_agreement_commission_amount"><strong><?php esc_html_e('Wysokość prowizji', 'estate-office'); ?></strong></label>
                <input type="number" class="widefat" step="0.01" min="0" id="estate_agreement_commission_amount" name="estate_agreement_commission_amount" value="<?php echo esc_attr((string) $commission); ?>" />
            </p>
            <p style="width:120px;">
                <label for="estate_agreement_commission_unit"><strong><?php esc_html_e('Jednostka', 'estate-office'); ?></strong></label>
                <select id="estate_agreement_commission_unit" name="estate_agreement_commission_unit" class="widefat">
                    <option value=""><?php esc_html_e('Wybierz', 'estate-office'); ?></option>
                    <?php foreach (self::COMMISSION_UNITS as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($commissionUnit, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
        <p class="description"><?php esc_html_e('W przypadku umowy bezterminowej data zakończenia pozostanie pusta.', 'estate-office'); ?></p>
        <script>
            (function() {
                const checkbox = document.getElementById('estate_agreement_is_indefinite');
                const endDate = document.getElementById('estate_agreement_end_date');
                if (!checkbox || !endDate) {
                    return;
                }
                const toggle = () => {
                    if (checkbox.checked) {
                        endDate.value = '';
                        endDate.setAttribute('disabled', 'disabled');
                    } else {
                        endDate.removeAttribute('disabled');
                    }
                };
                checkbox.addEventListener('change', toggle);
                toggle();
            })();
        </script>
        <?php
    }

    public static function renderDynamicFieldsBox(WP_Post $post): void
    {
        $definitions = GeneralSettings::getAgreementDynamicFields();

        if (empty($definitions)) {
            echo '<p class="description">' . esc_html__('Brak zdefiniowanych pól dodatkowych. Dodaj je w ustawieniach wtyczki.', 'estate-office') . '</p>';

            return;
        }

        $values = get_post_meta($post->ID, self::DYNAMIC_FIELDS_META_KEY, true);
        if (!is_array($values)) {
            $values = [];
        }

        echo '<table class="form-table estate-office-meta-table">';
        foreach ($definitions as $definition) {
            $key   = (string) $definition['key'];
            $label = (string) $definition['label'];
            $id    = 'estate_agreement_dynamic_' . $key;
            $name  = 'estate_agreement_dynamic[' . $key . ']';
            $value = isset($values[$key]) ? esc_attr((string) $values[$key]) : '';

            echo '<tr>';
            echo '<th><label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label></th>';
            printf('<td><input type="text" class="widefat" id="%1$s" name="%2$s" value="%3$s" autocomplete="off" /></td>', esc_attr($id), esc_attr($name), $value);
            echo '</tr>';
        }
        echo '</table>';
        echo '<p class="description">' . esc_html__('Lista pól znajduje się w ustawieniach w sekcji „Pola umów”.', 'estate-office') . '</p>';
    }

    public static function renderStageBox(WP_Post $post): void
    {
        $stage   = get_post_meta($post->ID, 'estate_agreement_stage', true);
        $history = get_post_meta($post->ID, 'estate_agreement_stage_history', true);
        if (!is_array($history)) {
            $history = [];
        }

        ?>
        <p>
            <label for="estate_agreement_stage"><strong><?php esc_html_e('Aktualny etap', 'estate-office'); ?></strong></label>
            <select id="estate_agreement_stage" name="estate_agreement_stage" class="widefat">
                <?php foreach (self::STAGES as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($stage ?: self::DEFAULT_STAGE, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="estate_agreement_stage_date"><strong><?php esc_html_e('Data etapu', 'estate-office'); ?></strong></label>
            <input type="date" id="estate_agreement_stage_date" name="estate_agreement_stage_date" class="widefat" value="" />
        </p>
        <p class="description"><?php esc_html_e('Pozostaw puste, aby użyć dzisiejszej daty.', 'estate-office'); ?></p>
        <?php if ($history) : ?>
            <hr />
            <h4><?php esc_html_e('Historia etapów', 'estate-office'); ?></h4>
            <ul>
                <?php foreach (array_reverse($history) as $entry) :
                    $label = self::STAGES[$entry['stage']] ?? $entry['stage'];
                    $date  = self::formatDateForDisplay($entry['date'] ?? '');
                    ?>
                    <li>
                        <strong><?php echo esc_html($label); ?></strong><br />
                        <span><?php echo esc_html($date); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== AgreementRegister::POST_TYPE) {
            return;
        }

        if (!isset($_POST['estate_office_agreement_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST['estate_office_agreement_meta_nonce']));

        if (!wp_verify_nonce($nonce, 'estate_office_agreement_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $previousClients    = self::ensureIntArray(get_post_meta($postId, 'estate_agreement_clients', true));
        $previousProperties = self::ensureIntArray(get_post_meta($postId, 'estate_agreement_properties', true));
        $previousSearches   = self::ensureIntArray(get_post_meta($postId, 'estate_agreement_searches', true));

        $values = [];

        foreach (self::META_FIELDS as $key => $definition) {
            if ($key === 'estate_agreement_stage_history') {
                continue;
            }

            if (($definition['type'] ?? '') === 'relation') {
                $values[$key] = self::sanitizeRelation($_POST[$key] ?? [], $definition);

                continue;
            }

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

        $dynamicValues = self::sanitizeDynamicInput($_POST['estate_agreement_dynamic'] ?? []);

        if (!empty($values['estate_agreement_is_indefinite'])) {
            $values['estate_agreement_end_date'] = '';
        }

        if (!isset($values['estate_agreement_transaction_type']) || $values['estate_agreement_transaction_type'] === '') {
            $values['estate_agreement_transaction_type'] = self::sanitizeEnum(
                (string) get_post_meta($postId, 'estate_agreement_transaction_type', true),
                self::TRANSACTION_TYPES
            );
        }

        $values['estate_agreement_clients']    = $values['estate_agreement_clients'] ?? [];
        $values['estate_agreement_properties'] = $values['estate_agreement_properties'] ?? [];
        $values['estate_agreement_searches']   = $values['estate_agreement_searches'] ?? [];

        $transactionType       = $values['estate_agreement_transaction_type'] ?? '';
        $propertyTransactions  = ['sale', 'rent_out'];
        $searchTransactions    = ['purchase', 'lease'];
        $isPropertyTransaction = in_array($transactionType, $propertyTransactions, true);
        $isSearchTransaction   = in_array($transactionType, $searchTransactions, true);

        if (!$isPropertyTransaction) {
            $values['estate_agreement_properties'] = [];
        }

        if (!$isSearchTransaction) {
            $values['estate_agreement_searches'] = [];
        }

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }

        self::persistDynamicFields($postId, $dynamicValues);

        self::syncRelations($postId, $previousClients, $values['estate_agreement_clients'], ClientRegister::POST_TYPE, ClientMeta::AGREEMENTS_META_KEY);
        self::syncRelations($postId, $previousProperties, $values['estate_agreement_properties'], PropertyRegister::POST_TYPE, PropertyMeta::AGREEMENTS_META_KEY);
        self::syncRelations($postId, $previousSearches, $values['estate_agreement_searches'], SearchRegister::POST_TYPE, SearchMeta::AGREEMENTS_META_KEY);

        self::updateStage($postId, $values);
        self::synchroniseTitle($postId, $values['estate_agreement_number'] ?? '');
    }

    private static function updateStage(int $postId, array $values): void
    {
        $currentStage = get_post_meta($postId, 'estate_agreement_stage', true);
        $history      = get_post_meta($postId, 'estate_agreement_stage_history', true);
        if (!is_array($history)) {
            $history = [];
        }

        $newStage = self::sanitizeEnum(
            wp_unslash((string) ($_POST['estate_agreement_stage'] ?? ($currentStage ?: self::DEFAULT_STAGE))),
            self::STAGES
        );

        $stageDate = self::sanitizeDate(wp_unslash((string) ($_POST['estate_agreement_stage_date'] ?? '')));
        if ($stageDate === '') {
            $stageDate = $values['estate_agreement_start_date'] ?? gmdate('Y-m-d');
        }

        if ($newStage === '') {
            $newStage = self::DEFAULT_STAGE;
        }

        $shouldPersist = $newStage !== $currentStage;

        if (!$shouldPersist && empty($history)) {
            $shouldPersist = true;
        }

        if ($shouldPersist) {
            $history[] = [
                'stage' => $newStage,
                'date'  => $stageDate,
            ];

            $history = array_slice($history, -50);

            update_post_meta($postId, 'estate_agreement_stage', $newStage);
            update_post_meta($postId, 'estate_agreement_stage_history', $history);
        } elseif (!empty($_POST['estate_agreement_stage_date'])) {
            $lastIndex = array_key_last($history);
            if ($lastIndex !== null) {
                $history[$lastIndex]['date'] = $stageDate;
                update_post_meta($postId, 'estate_agreement_stage_history', $history);
            }
        }
    }

    private static function synchroniseTitle(int $postId, string $number): void
    {
        $number = trim($number);
        if ($number === '') {
            return;
        }

        remove_action('save_post_' . AgreementRegister::POST_TYPE, [self::class, 'save'], 10);

        wp_update_post([
            'ID'         => $postId,
            'post_title' => $number,
            'post_name'  => sanitize_title($number),
        ]);

        add_action('save_post_' . AgreementRegister::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    private static function persistMeta(int $postId, string $key, $value, array $definition): void
    {
        if (($definition['type'] ?? '') === 'boolean') {
            update_post_meta($postId, $key, $value ? 1 : 0);
            return;
        }

        if (($definition['type'] ?? '') === 'relation') {
            if (empty($value)) {
                delete_post_meta($postId, $key);

                return;
            }

            $value = array_values(array_unique(array_map('intval', (array) $value)));

            update_post_meta($postId, $key, $value);

            return;
        }

        if ($value === '') {
            delete_post_meta($postId, $key);
            return;
        }

        update_post_meta($postId, $key, $value);
    }

    private static function renderRelationSelect(string $fieldId, string $label, string $description, array $options, array $selected): void
    {
        $selected = self::ensureIntArray($selected);
        $name     = $fieldId . '[]';
        $size     = max(4, min(10, count($options) ?: 4));

        echo '<p class="estate-office-relation-control">';
        printf('<label for="%s"><strong>%s</strong></label>', esc_attr($fieldId), esc_html($label));

        if ($options) {
            printf(
                '<select id="%1$s" name="%2$s" class="widefat" multiple="multiple" size="%3$d">',
                esc_attr($fieldId),
                esc_attr($name),
                (int) $size
            );

            foreach ($options as $id => $title) {
                $isSelected = in_array((int) $id, $selected, true);

                printf(
                    '<option value="%1$d" %2$s>%3$s</option>',
                    (int) $id,
                    selected($isSelected, true, false),
                    esc_html($title)
                );
            }

            echo '</select>';
            printf('<span class="description">%s</span>', esc_html($description));
        } else {
            echo '<span class="description">' . esc_html__(
                'Brak rekordów do wyboru. Dodaj odpowiednie wpisy w CRM, aby je powiązać z umową.',
                'estate-office'
            ) . '</span>';
        }

        echo '</p>';
    }

    private static function getRelationOptions(string $postType, array $ensureIds = []): array
    {
        $posts = get_posts([
            'post_type'        => $postType,
            'post_status'      => ['publish', 'pending', 'draft', 'private'],
            'numberposts'      => -1,
            'orderby'          => 'title',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ]);

        $options = [];

        foreach ($posts as $post) {
            $options[$post->ID] = self::formatRelationLabel($post, $postType);
        }

        foreach ($ensureIds as $id) {
            $id = (int) $id;

            if ($id <= 0 || isset($options[$id])) {
                continue;
            }

            $post = get_post($id);

            if (!$post || $post->post_type !== $postType) {
                continue;
            }

            $options[$post->ID] = self::formatRelationLabel($post, $postType);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private static function formatRelationLabel(WP_Post $post, string $postType): string
    {
        $title = trim((string) get_the_title($post));

        if ($title === '') {
            $title = sprintf(__('Bez tytułu (#%d)', 'estate-office'), $post->ID);
        }

        if ($postType === ClientRegister::POST_TYPE) {
            $reference = trim((string) get_post_meta($post->ID, 'estate_client_reference', true));

            if ($reference !== '') {
                return sprintf('%s — %s', $reference, $title);
            }
        }

        if ($postType === PropertyRegister::POST_TYPE) {
            $reference = trim((string) get_post_meta($post->ID, 'estate_property_reference', true));

            if ($reference !== '') {
                return sprintf('%s — %s', $reference, $title);
            }
        }

        if ($postType === SearchRegister::POST_TYPE) {
            $reference = trim((string) get_post_meta($post->ID, 'estate_search_reference', true));

            if ($reference !== '') {
                return sprintf('%s — %s', $reference, $title);
            }
        }

        return $title;
    }

    private static function sanitizeRelation($value, array $definition): array
    {
        $postType = (string) ($definition['post_type'] ?? '');

        if ($postType === '') {
            return [];
        }

        $raw = is_array($value) ? $value : [$value];
        $sanitized = [];

        foreach ($raw as $item) {
            if (is_array($item)) {
                continue;
            }

            $id = (int) $item;

            if ($id <= 0) {
                continue;
            }

            $post = get_post($id);

            if (!$post || $post->post_type !== $postType) {
                continue;
            }

            $sanitized[] = $post->ID;
        }

        $sanitized = array_values(array_unique($sanitized));
        sort($sanitized);

        return $sanitized;
    }

    private static function ensureIntArray($value): array
    {
        if (!is_array($value)) {
            $value = $value === '' ? [] : [$value];
        }

        $value = array_filter(
            array_map(
                static fn($item) => is_scalar($item) ? (int) $item : 0,
                $value
            ),
            static fn($item) => $item > 0
        );

        $value = array_values(array_unique($value));
        sort($value);

        return $value;
    }

    private static function syncRelations(int $agreementId, array $previous, array $current, string $postType, string $metaKey): void
    {
        $removed = array_diff($previous, $current);
        $added   = array_diff($current, $previous);

        foreach ($removed as $relatedId) {
            self::mutateRelationMeta(
                (int) $relatedId,
                $postType,
                $metaKey,
                static fn(array $list): array => array_values(array_diff($list, [$agreementId]))
            );
        }

        foreach ($added as $relatedId) {
            self::mutateRelationMeta(
                (int) $relatedId,
                $postType,
                $metaKey,
                static function (array $list) use ($agreementId): array {
                    $list[] = $agreementId;

                    $list = array_values(array_unique(array_map('intval', $list)));
                    sort($list);

                    return $list;
                }
            );
        }
    }

    private static function mutateRelationMeta(int $postId, string $expectedType, string $metaKey, callable $mutator): void
    {
        if ($postId <= 0 || get_post_type($postId) !== $expectedType) {
            return;
        }

        $current = self::ensureIntArray(get_post_meta($postId, $metaKey, true));
        $updated = $mutator($current);

        if (!is_array($updated)) {
            $updated = [];
        }

        $updated = self::ensureIntArray($updated);

        if (empty($updated)) {
            delete_post_meta($postId, $metaKey);

            return;
        }

        update_post_meta($postId, $metaKey, $updated);
    }

    private static function sanitizeValue(string $value, array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'decimal' => self::sanitizeDecimal($value, (int) ($definition['precision'] ?? 2)),
            'enum'    => self::sanitizeEnum($value, (array) ($definition['values'] ?? [])),
            'date'    => self::sanitizeDate($value),
            default   => self::sanitizeLine($value),
        };
    }

    private static function sanitizeLine(string $value): string
    {
        $value = sanitize_text_field($value);

        return trim($value);
    }

    private static function sanitizeDecimal(string $value, int $precision): string
    {
        $value = trim(str_replace(',', '.', $value));

        if ($value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return '';
        }

        $float = (float) $value;

        if (!is_finite($float) || $float < 0) {
            return '';
        }

        return number_format($float, $precision, '.', '');
    }

    private static function sanitizeEnum(string $value, array $allowed): string
    {
        $value = sanitize_key($value);

        return array_key_exists($value, $allowed) ? $value : '';
    }

    private static function sanitizeBoolean(string $value): bool
    {
        return $value === '1' || $value === 'true' || $value === 'yes';
    }

    private static function sanitizeDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if (!$date) {
            return '';
        }

        return $date->format('Y-m-d');
    }

    private static function sanitizeDynamicInput($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $definitions = GeneralSettings::getAgreementDynamicFields();
        if (empty($definitions)) {
            return [];
        }

        $allowed = [];
        foreach ($definitions as $definition) {
            $allowed[(string) $definition['key']] = true;
        }

        $sanitized = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }

            if (is_array($value)) {
                $value = '';
            }

            $clean = self::sanitizeLine(wp_unslash((string) $value));

            if ($clean === '') {
                continue;
            }

            $sanitized[$key] = $clean;
        }

        return $sanitized;
    }

    private static function persistDynamicFields(int $postId, array $values): void
    {
        if (empty($values)) {
            delete_post_meta($postId, self::DYNAMIC_FIELDS_META_KEY);

            return;
        }

        update_post_meta($postId, self::DYNAMIC_FIELDS_META_KEY, $values);
    }

    public static function sanitizeDynamicFields($value, string $metaKey = '', string $objectType = ''): array
    {
        if (!is_array($value)) {
            return [];
        }

        $definitions = GeneralSettings::getAgreementDynamicFields();
        if (empty($definitions)) {
            return [];
        }

        $allowed = [];
        foreach ($definitions as $definition) {
            $allowed[(string) $definition['key']] = true;
        }

        $sanitized = [];

        foreach ($value as $key => $raw) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }

            if (is_array($raw)) {
                $raw = '';
            }

            $clean = self::sanitizeLine((string) $raw);

            if ($clean === '') {
                continue;
            }

            $sanitized[$key] = $clean;
        }

        return $sanitized;
    }

    /**
     * @return array<int,array{label:string,value:string}>
     */
    public static function getDynamicFieldValues(int $postId): array
    {
        $definitions = GeneralSettings::getAgreementDynamicFields();
        if (empty($definitions)) {
            return [];
        }

        $stored = get_post_meta($postId, self::DYNAMIC_FIELDS_META_KEY, true);
        if (!is_array($stored)) {
            $stored = [];
        }

        $values = [];

        foreach ($definitions as $definition) {
            $key = (string) $definition['key'];

            if (!isset($stored[$key])) {
                continue;
            }

            $value = trim((string) $stored[$key]);

            if ($value === '') {
                continue;
            }

            $values[] = [
                'label' => (string) $definition['label'],
                'value' => $value,
            ];
        }

        return $values;
    }

    private static function buildSanitizer(array $definition): callable
    {
        if (($definition['type'] ?? '') === 'boolean') {
            return static fn($value) => self::sanitizeBoolean(is_scalar($value) ? (string) $value : '');
        }

        if (($definition['type'] ?? '') === 'array') {
            return static fn($value) => self::sanitizeStageHistory($value);
        }

        if (($definition['type'] ?? '') === 'relation') {
            return static fn($value) => self::sanitizeRelation($value, $definition);
        }

        return static function ($value) use ($definition) {
            $value = is_scalar($value) ? (string) $value : '';

            return self::sanitizeValue($value, $definition);
        };
    }

    private static function sanitizeStageHistory($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $stage = self::sanitizeEnum((string) ($entry['stage'] ?? ''), self::STAGES);
            $date  = self::sanitizeDate((string) ($entry['date'] ?? ''));

            if ($stage === '' || $date === '') {
                continue;
            }

            $sanitized[] = [
                'stage' => $stage,
                'date'  => $date,
            ];
        }

        return array_slice($sanitized, -50);
    }

    private static function formatDateForDisplay(string $value): string
    {
        $date = self::sanitizeDate($value);
        if ($date === '') {
            return '';
        }

        return wp_date(get_option('date_format', 'Y-m-d'), strtotime($date));
    }

    public static function canEditMeta(bool $allowed, string $metaKey, int $postId): bool
    {
        unset($allowed, $metaKey);

        return current_user_can('edit_post', $postId);
    }
}
