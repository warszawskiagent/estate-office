<?php

declare(strict_types=1);

namespace EstateOffice\Admin;

use WP_User;

use function add_action;
use function current_user_can;
use function esc_attr;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function explode;
use function get_user_meta;
use function plugins_url;
use function update_user_meta;
use function wp_editor;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

use function delete_user_meta;
use function sanitize_text_field;
use function str_replace;
use function implode;
use function wp_get_attachment_image_url;
use function wp_kses_post;

/**
 * Extends user profile pages with EstateOffice agent metadata.
 */
final class AgentProfile
{
    private const NONCE_ACTION = 'estate_office_agent_profile';
    private const NONCE_NAME   = '_estate_office_agent_nonce';

    public const META_AVATAR          = 'estate_office_agent_avatar_id';
    public const META_PHONE           = 'estate_office_agent_phone';
    public const META_PHONE_ALT       = 'estate_office_agent_phone_alt';
    public const META_OFFICE_PHONE    = 'estate_office_agent_office_phone';
    public const META_WHATSAPP        = 'estate_office_agent_whatsapp';
    public const META_BIOGRAPHY       = 'estate_office_agent_biography';
    public const META_SPECIALISATIONS = 'estate_office_agent_specialisations';
    public const META_SERVICE_AREAS   = 'estate_office_agent_service_areas';

    /**
     * Bootstraps hooks for agent profile management.
     */
    public static function bootstrap(): void
    {
        add_action('show_user_profile', [self::class, 'render']);
        add_action('edit_user_profile', [self::class, 'render']);
        add_action('personal_options_update', [self::class, 'save']);
        add_action('edit_user_profile_update', [self::class, 'save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    /**
     * Renders the EstateOffice specific fields on the user profile page.
     */
    public static function render(WP_User $user): void
    {
        if (! current_user_can('edit_user', $user->ID)) {
            return;
        }

        $avatarId     = (int) get_user_meta($user->ID, self::META_AVATAR, true);
        $phone        = (string) get_user_meta($user->ID, self::META_PHONE, true);
        $phoneAlt     = (string) get_user_meta($user->ID, self::META_PHONE_ALT, true);
        $officePhone  = (string) get_user_meta($user->ID, self::META_OFFICE_PHONE, true);
        $whatsapp     = (string) get_user_meta($user->ID, self::META_WHATSAPP, true);
        $bio          = (string) get_user_meta($user->ID, self::META_BIOGRAPHY, true);
        $specialsRaw  = (string) get_user_meta($user->ID, self::META_SPECIALISATIONS, true);
        $areasRaw     = (string) get_user_meta($user->ID, self::META_SERVICE_AREAS, true);

        $avatarUrl = $avatarId > 0 ? wp_get_attachment_image_url($avatarId, 'thumbnail') : '';

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <h2><?php esc_html_e('EstateOffice Agent Profile', 'estate-office'); ?></h2>
        <p class="description">
            <?php esc_html_e('Uzupełnij dodatkowe informacje kontaktowe agenta wykorzystywane w CRM i na stronie ofertowej.', 'estate-office'); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-avatar">
                        <?php esc_html_e('Zdjęcie agenta', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <div class="estate-office-agent-avatar" data-placeholder="<?php echo esc_attr__('Dodaj zdjęcie', 'estate-office'); ?>">
                        <?php if ($avatarUrl) : ?>
                            <img src="<?php echo esc_url($avatarUrl); ?>" alt="" />
                        <?php else : ?>
                            <span class="placeholder dashicons dashicons-format-image">
                                <span class="screen-reader-text"><?php esc_html_e('Dodaj zdjęcie', 'estate-office'); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="<?php echo esc_attr(self::META_AVATAR); ?>" id="estate-office-agent-avatar" value="<?php echo esc_attr((string) $avatarId); ?>" />
                    <p class="estate-office-agent-avatar-actions">
                        <button type="button" class="button estate-office-agent-avatar-select">
                            <?php esc_html_e('Wybierz zdjęcie', 'estate-office'); ?>
                        </button>
                        <button type="button" class="button button-link-delete estate-office-agent-avatar-remove" <?php echo $avatarUrl ? '' : 'style="display:none;"'; ?>>
                            <?php esc_html_e('Usuń', 'estate-office'); ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Dodaj reprezentatywne zdjęcie agenta, które będzie wykorzystywane w wizytówce oraz ofertach.', 'estate-office'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-phone">
                        <?php esc_html_e('Telefon główny', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::META_PHONE); ?>" id="estate-office-agent-phone" value="<?php echo esc_attr($phone); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-phone-alt">
                        <?php esc_html_e('Telefon dodatkowy', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::META_PHONE_ALT); ?>" id="estate-office-agent-phone-alt" value="<?php echo esc_attr($phoneAlt); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-office-phone">
                        <?php esc_html_e('Telefon biura', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::META_OFFICE_PHONE); ?>" id="estate-office-agent-office-phone" value="<?php echo esc_attr($officePhone); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-whatsapp">
                        <?php esc_html_e('WhatsApp', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::META_WHATSAPP); ?>" id="estate-office-agent-whatsapp" value="<?php echo esc_attr($whatsapp); ?>" />
                    <p class="description">
                        <?php esc_html_e('Numer telefonu lub link umożliwiający szybki kontakt.', 'estate-office'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-biography">
                        <?php esc_html_e('Biografia / opis', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        $bio,
                        'estate-office-agent-biography',
                        [
                            'textarea_name' => self::META_BIOGRAPHY,
                            'textarea_rows' => 6,
                            'editor_height' => 160,
                            'media_buttons' => false,
                            'teeny' => true,
                        ]
                    );
                    ?>
                    <p class="description">
                        <?php esc_html_e('Krótki opis kompetencji i doświadczenia agenta wyświetlany w CRM i na stronie publicznej.', 'estate-office'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-specialisations">
                        <?php esc_html_e('Specjalizacje', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <textarea class="large-text" rows="3" name="<?php echo esc_attr(self::META_SPECIALISATIONS); ?>" id="estate-office-agent-specialisations"><?php echo esc_textarea($specialsRaw); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Wypisz główne specjalizacje agenta (np. apartamenty premium, rynek pierwotny). Każdą pozycję umieść w osobnej linii.', 'estate-office'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="estate-office-agent-service-areas">
                        <?php esc_html_e('Obsługiwane obszary', 'estate-office'); ?>
                    </label>
                </th>
                <td>
                    <textarea class="large-text" rows="3" name="<?php echo esc_attr(self::META_SERVICE_AREAS); ?>" id="estate-office-agent-service-areas"><?php echo esc_textarea($areasRaw); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Wskaż miasta, dzielnice lub regiony, na których agent koncentruje działania. Każdy obszar wpisz w nowej linii.', 'estate-office'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Persists metadata submitted from the profile form.
     */
    public static function save(int $userId): void
    {
        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        if (! isset($_POST[self::NONCE_NAME])) {
            return;
        }

        $nonce = (string) $_POST[self::NONCE_NAME];
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $fields = [
            self::META_PHONE        => 'sanitize_text_field',
            self::META_PHONE_ALT    => 'sanitize_text_field',
            self::META_OFFICE_PHONE => 'sanitize_text_field',
            self::META_WHATSAPP     => 'sanitize_text_field',
        ];

        foreach ($fields as $metaKey => $callback) {
            if (isset($_POST[$metaKey])) {
                $value = call_user_func($callback, wp_unslash((string) $_POST[$metaKey]));
                update_user_meta($userId, $metaKey, $value);
            }
        }

        if (isset($_POST[self::META_AVATAR])) {
            $attachment = (int) $_POST[self::META_AVATAR];
            if ($attachment > 0) {
                update_user_meta($userId, self::META_AVATAR, $attachment);
            } else {
                delete_user_meta($userId, self::META_AVATAR);
            }
        }

        if (isset($_POST[self::META_BIOGRAPHY])) {
            $bio = wp_kses_post(wp_unslash((string) $_POST[self::META_BIOGRAPHY]));
            update_user_meta($userId, self::META_BIOGRAPHY, $bio);
        }

        foreach ([self::META_SPECIALISATIONS, self::META_SERVICE_AREAS] as $listMetaKey) {
            if (! isset($_POST[$listMetaKey])) {
                continue;
            }

            $listValue = self::sanitizeListField(wp_unslash((string) $_POST[$listMetaKey]));

            if ($listValue === '') {
                delete_user_meta($userId, $listMetaKey);
                continue;
            }

            update_user_meta($userId, $listMetaKey, $listValue);
        }
    }

    /**
     * Returns list-based agent metadata as an array of values.
     *
     * @return array<int,string>
     */
    public static function getListValues(int $userId, string $metaKey): array
    {
        $raw = (string) get_user_meta($userId, $metaKey, true);

        if ($raw === '') {
            return [];
        }

        $raw = str_replace("\r", "\n", $raw);
        $items = array_filter(array_map('trim', explode("\n", $raw)), static fn(string $value): bool => $value !== '');

        return array_map('sanitize_text_field', $items);
    }

    private static function sanitizeListField(string $value): string
    {
        $value = str_replace("\r", "\n", $value);
        $rows  = array_filter(array_map('trim', explode("\n", $value)), static fn(string $row): bool => $row !== '');

        if (empty($rows)) {
            return '';
        }

        $sanitized = array_map('sanitize_text_field', $rows);
        $unique    = array_values(array_unique($sanitized));

        return implode("\n", $unique);
    }

    /**
     * Loads media scripts and custom assets for the profile editor.
     */
    public static function enqueueAssets(string $hook): void
    {
        if (! in_array($hook, ['profile.php', 'user-edit.php'], true)) {
            return;
        }

        wp_enqueue_media();

        $handle = 'estate-office-agent-profile';

        wp_enqueue_style(
            $handle,
            plugins_url('assets/css/agent-profile.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_enqueue_script(
            $handle,
            plugins_url('assets/js/agent-profile.js', ESTATE_OFFICE_PLUGIN_FILE),
            ['jquery'],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            $handle,
            'EstateOfficeAgentProfile',
            [
                'choose'    => esc_html__('Wybierz zdjęcie agenta', 'estate-office'),
                'update'    => esc_html__('Użyj tego zdjęcia', 'estate-office'),
                'remove'    => esc_html__('Usuń zdjęcie', 'estate-office'),
                'placeholder' => esc_html__('Dodaj zdjęcie', 'estate-office'),
            ]
        );
    }
}
