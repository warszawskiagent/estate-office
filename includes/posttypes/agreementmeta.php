<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use DateTimeImmutable;
use WP_Post;

defined('ABSPATH') || exit;

final class AgreementMeta
{
    private const META_FIELDS = [
        'estate_agreement_number'           => ['type' => 'string'],
        'estate_agreement_transaction_type' => ['type' => 'enum', 'values' => self::TRANSACTION_TYPES],
        'estate_agreement_start_date'       => ['type' => 'date'],
        'estate_agreement_end_date'         => ['type' => 'date'],
        'estate_agreement_is_indefinite'    => ['type' => 'boolean'],
        'estate_agreement_commission_amount'=> ['type' => 'decimal', 'precision' => 2],
        'estate_agreement_commission_unit'  => ['type' => 'enum', 'values' => self::COMMISSION_UNITS],
        'estate_agreement_stage'            => ['type' => 'enum', 'values' => self::STAGES],
        'estate_agreement_stage_history'    => ['type' => 'array'],
    ];

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
            'estate-office-agreement-stage',
            __('Etap umowy', 'estate-office'),
            [self::class, 'renderStageBox'],
            AgreementRegister::POST_TYPE,
            'side'
        );
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

        $values = [];

        foreach (self::META_FIELDS as $key => $definition) {
            if ($key === 'estate_agreement_stage_history') {
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

        if (!empty($values['estate_agreement_is_indefinite'])) {
            $values['estate_agreement_end_date'] = '';
        }

        foreach ($values as $key => $value) {
            self::persistMeta($postId, $key, $value, self::META_FIELDS[$key]);
        }

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

        if ($value === '') {
            delete_post_meta($postId, $key);
            return;
        }

        update_post_meta($postId, $key, $value);
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

    private static function buildSanitizer(array $definition): callable
    {
        if (($definition['type'] ?? '') === 'boolean') {
            return static fn($value) => self::sanitizeBoolean(is_scalar($value) ? (string) $value : '');
        }

        if (($definition['type'] ?? '') === 'array') {
            return static fn($value) => self::sanitizeStageHistory($value);
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
