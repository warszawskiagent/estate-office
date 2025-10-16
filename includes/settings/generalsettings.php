<?php

declare(strict_types=1);

namespace EstateOffice\Settings;

defined('ABSPATH') || exit;

final class GeneralSettings
{
    public const OPTION = 'estate_office_settings';

    public static function register(): void
    {
        register_setting(
            'estate_office_settings_group',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default'           => [
                    'google_maps_api_key'  => '',
                    'watermark_attachment' => 0,
                    'office_logo'          => 0,
                    'property_fields'      => [],
                    'agreement_fields'     => [],
                    'client_fields'        => [],
                    'search_fields'        => [],
                    'lead_notifications'   => self::getDefaultNotifications(),
                    'client_roles'         => self::getDefaultClientRolesOption(),
                ],
            ]
        );

        add_settings_section(
            'estate_office_integrations',
            __('Integracje', 'estate-office'),
            static fn () => printf('<p>%s</p>', esc_html__('Skonfiguruj integracje i materiały graficzne wykorzystywane w CRM.', 'estate-office')),
            'estate-office-settings'
        );

        add_settings_field(
            'estate_office_google_maps_api_key',
            __('Klucz API Map Google', 'estate-office'),
            [self::class, 'renderMapsField'],
            'estate-office-settings',
            'estate_office_integrations'
        );

        add_settings_field(
            'estate_office_watermark',
            __('Znak wodny', 'estate-office'),
            [self::class, 'renderWatermarkField'],
            'estate-office-settings',
            'estate_office_integrations'
        );

        add_settings_field(
            'estate_office_logo',
            __('Logo biura', 'estate-office'),
            [self::class, 'renderLogoField'],
            'estate-office-settings',
            'estate_office_integrations'
        );

        add_settings_section(
            'estate_office_dynamic_fields',
            __('Pola dynamiczne', 'estate-office'),
            static fn () => printf('<p>%s</p>', esc_html__('Zdefiniuj dodatkowe pola dla nieruchomości, umów i klientów. Lista pól będzie rozwijana w kolejnych etapach.', 'estate-office')),
            'estate-office-settings'
        );

        add_settings_field(
            'estate_office_property_fields',
            __('Pola nieruchomości', 'estate-office'),
            [self::class, 'renderPropertyFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_field(
            'estate_office_agreement_fields',
            __('Pola umów', 'estate-office'),
            [self::class, 'renderAgreementFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_field(
            'estate_office_client_fields',
            __('Pola klientów', 'estate-office'),
            [self::class, 'renderClientFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_field(
            'estate_office_search_fields',
            __('Pola poszukiwań', 'estate-office'),
            [self::class, 'renderSearchFields'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_field(
            'estate_office_client_roles',
            __('Role klientów w umowach', 'estate-office'),
            [self::class, 'renderClientRolesField'],
            'estate-office-settings',
            'estate_office_dynamic_fields'
        );

        add_settings_section(
            'estate_office_notifications',
            __('Powiadomienia', 'estate-office'),
            static fn () => printf('<p>%s</p>', esc_html__('Skonfiguruj powiadomienia e-mail wysyłane po utworzeniu leadu.', 'estate-office')),
            'estate-office-settings'
        );

        add_settings_field(
            'estate_office_lead_notify_agent',
            __('Powiadom przypisanego agenta', 'estate-office'),
            [self::class, 'renderNotifyAgentField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_agent_subject',
            __('Temat wiadomości do agenta', 'estate-office'),
            [self::class, 'renderAgentSubjectField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_include_message',
            __('Dołącz treść zapytania', 'estate-office'),
            [self::class, 'renderIncludeMessageField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_office_email',
            __('Kopia do biura', 'estate-office'),
            [self::class, 'renderOfficeEmailField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_office_subject',
            __('Temat wiadomości do biura', 'estate-office'),
            [self::class, 'renderOfficeSubjectField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_reminder_enabled',
            __('Przypomnienia follow-up', 'estate-office'),
            [self::class, 'renderReminderEnabledField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_reminder_hours',
            __('Wyślij przed terminem', 'estate-office'),
            [self::class, 'renderReminderHoursField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_reminder_subject',
            __('Temat przypomnienia', 'estate-office'),
            [self::class, 'renderReminderSubjectField'],
            'estate-office-settings',
            'estate_office_notifications'
        );

        add_settings_field(
            'estate_office_lead_reminder_recipients',
            __('Adresaci przypomnień', 'estate-office'),
            [self::class, 'renderReminderRecipientsField'],
            'estate-office-settings',
            'estate_office_notifications'
        );
    }

    public static function sanitize($value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'google_maps_api_key'  => isset($value['google_maps_api_key']) ? sanitize_text_field($value['google_maps_api_key']) : '',
            'watermark_attachment' => isset($value['watermark_attachment']) ? absint($value['watermark_attachment']) : 0,
            'office_logo'          => isset($value['office_logo']) ? absint($value['office_logo']) : 0,
            'property_fields'      => self::sanitizeList($value['property_fields'] ?? []),
            'agreement_fields'     => self::sanitizeList($value['agreement_fields'] ?? []),
            'client_fields'        => self::sanitizeList($value['client_fields'] ?? []),
            'search_fields'        => self::sanitizeList($value['search_fields'] ?? []),
            'lead_notifications'   => self::sanitizeNotifications($value['lead_notifications'] ?? []),
            'client_roles'         => self::sanitizeRoles($value['client_roles'] ?? []),
        ];
    }

    private static function sanitizeList($value): array
    {
        $value = is_array($value) ? $value : [];

        $sanitized = [];

        foreach ($value as $item) {
            $item = sanitize_text_field((string) $item);
            if ($item !== '') {
                $sanitized[] = $item;
            }
        }

        return array_values(array_unique($sanitized));
    }

    public static function renderMapsField(): void
    {
        $option = get_option(self::OPTION);
        $value  = isset($option['google_maps_api_key']) ? $option['google_maps_api_key'] : '';

        printf(
            '<input type="text" id="estate_office_google_maps_api_key" name="%1$s[google_maps_api_key]" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr(self::OPTION),
            esc_attr($value)
        );
        echo '<p class="description">' . esc_html__('Klucz API zostanie wykorzystany do integracji z Mapami Google w formularzach i na frontendzie.', 'estate-office') . '</p>';
    }

    public static function renderWatermarkField(): void
    {
        self::renderMediaField('watermark_attachment', __('Wybierz plik znaku wodnego', 'estate-office'));
    }

    public static function renderLogoField(): void
    {
        self::renderMediaField('office_logo', __('Wybierz logo biura', 'estate-office'));
    }

    private static function renderMediaField(string $key, string $buttonLabel): void
    {
        $option = get_option(self::OPTION);
        $id     = isset($option[$key]) ? (int) $option[$key] : 0;
        $url    = $id ? wp_get_attachment_url($id) : '';
        $field  = esc_attr(self::OPTION . '[' . $key . ']');

        printf('<input type="hidden" id="%1$s" name="%2$s" value="%3$d" />', esc_attr($key), $field, $id);
        echo '<div class="estate-office-media-field">';
        if ($url) {
            printf('<div class="preview"><img src="%s" alt="" style="max-width:150px;height:auto;" /></div>', esc_url($url));
        }
        printf(
            '<button type="button" class="button estate-office-media-upload" data-target="%1$s">%2$s</button> ',
            esc_attr($key),
            esc_html($buttonLabel)
        );
        printf(
            '<button type="button" class="button secondary estate-office-media-remove" data-target="%1$s"%2$s>%3$s</button>',
            esc_attr($key),
            $id ? '' : ' disabled',
            esc_html__('Usuń', 'estate-office')
        );
        echo '</div>';
        echo '<p class="description">' . esc_html__('Pliki są przechowywane w bibliotece mediów WordPress. Wybierz obraz w formacie PNG o przezroczystym tle, aby uzyskać najlepszy efekt.', 'estate-office') . '</p>';
    }

    public static function renderPropertyFields(): void
    {
        self::renderDynamicFields('property_fields', __('Dodaj etykietę pola (np. "Rynek wtórny") i naciśnij Enter.', 'estate-office'));
    }

    public static function renderAgreementFields(): void
    {
        self::renderDynamicFields('agreement_fields', __('Dodaj etykietę pola umowy (np. "Data prezentacji") i naciśnij Enter.', 'estate-office'));
    }

    public static function renderClientFields(): void
    {
        self::renderDynamicFields('client_fields', __('Dodaj etykietę pola klienta (np. "Preferowane godziny kontaktu") i naciśnij Enter.', 'estate-office'));
    }

    public static function renderSearchFields(): void
    {
        self::renderDynamicFields('search_fields', __('Dodaj etykietę pola poszukiwania (np. "Preferowany standard") i naciśnij Enter.', 'estate-office'));
    }

    public static function renderClientRolesField(): void
    {
        $roles     = self::getClientRoleDefinitions();
        $fieldBase = self::OPTION . '[client_roles]';
        $nextIndex = count($roles);

        echo '<div class="estate-office-role-manager" data-field="' . esc_attr($fieldBase) . '" data-next-index="' . esc_attr((string) $nextIndex) . '">';
        echo '<div class="estate-office-role-manager__list">';

        foreach ($roles as $index => $role) {
            self::renderClientRoleRow((string) $index, $role['key'], $role['label']);
        }

        echo '</div>';
        echo '<button type="button" class="button estate-office-role-manager__add">' . esc_html__('Dodaj rolę', 'estate-office') . '</button>';
        echo '<p class="description">' . esc_html__('Zdefiniuj listę ról klientów wykorzystywanych w umowach i raportach. Klucze powinny być unikalne – są używane w automatyzacjach oraz integracjach.', 'estate-office') . '</p>';
        echo '</div>';

        if (!has_action('admin_print_footer_scripts', [self::class, 'renderClientRoleTemplate'])) {
            add_action('admin_print_footer_scripts', [self::class, 'renderClientRoleTemplate']);
        }
    }

    private static function renderDynamicFields(string $key, string $placeholder): void
    {
        $option = get_option(self::OPTION);
        $values = isset($option[$key]) && is_array($option[$key]) ? $option[$key] : [];

        echo '<div class="estate-office-tags-input" data-field="' . esc_attr($key) . '">';
        foreach ($values as $value) {
            printf(
                '<span class="tag">%1$s<button type="button" class="dashicons dashicons-no-alt" aria-label="%2$s"></button><input type="hidden" name="%3$s[%4$s][]" value="%5$s" /></span>',
                esc_html($value),
                esc_attr__('Usuń', 'estate-office'),
                esc_attr(self::OPTION),
                esc_attr($key),
                esc_attr($value)
            );
        }
        printf(
            '<input type="text" class="tag-input" placeholder="%1$s" data-name="%2$s[%3$s][]" autocomplete="off" />',
            esc_attr($placeholder),
            esc_attr(self::OPTION),
            esc_attr($key)
        );
        echo '</div>';
    }

    public static function renderNotifyAgentField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][notify_agent]');

        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            $field,
            checked($notifications['notify_agent'], true, false),
            esc_html__('Wyślij powiadomienie e-mail do agenta przypisanego do leadu.', 'estate-office')
        );
        echo '<p class="description">' . esc_html__('Jeżeli lead powstał z formularza kontaktowego, agent otrzyma dodatkowe powiadomienie systemowe.', 'estate-office') . '</p>';
    }

    public static function renderAgentSubjectField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][agent_subject]');

        printf(
            '<input type="text" id="estate_office_lead_agent_subject" name="%1$s" value="%2$s" class="regular-text" />',
            $field,
            esc_attr($notifications['agent_subject'])
        );
        echo '<p class="description">' . esc_html__('Pozostaw puste, aby użyć domyślnego tematu „Nowy lead przypisany do Ciebie”.', 'estate-office') . '</p>';
    }

    public static function renderIncludeMessageField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][include_message]');

        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            $field,
            checked($notifications['include_message'], true, false),
            esc_html__('Dodaj treść wiadomości klienta do powiadomień.', 'estate-office')
        );
    }

    public static function renderOfficeEmailField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][office_email]');

        printf(
            '<input type="email" id="estate_office_lead_office_email" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
            $field,
            esc_attr($notifications['office_email'])
        );
        echo '<p class="description">' . esc_html__('Opcjonalny adres biura, który otrzyma kopię każdego nowego leadu.', 'estate-office') . '</p>';
    }

    public static function renderOfficeSubjectField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][office_subject]');

        printf(
            '<input type="text" id="estate_office_lead_office_subject" name="%1$s" value="%2$s" class="regular-text" />',
            $field,
            esc_attr($notifications['office_subject'])
        );
        echo '<p class="description">' . esc_html__('Pozostaw puste, aby użyć domyślnego tematu „Nowe zgłoszenie leadu”.', 'estate-office') . '</p>';
    }

    public static function renderReminderEnabledField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][reminder_enabled]');

        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            $field,
            checked(!empty($notifications['reminder_enabled']), true, false),
            esc_html__('Wyślij automatyczne przypomnienia e-mail przed terminem follow-up.', 'estate-office')
        );
        echo '<p class="description">' . esc_html__('Przypomnienia są wysyłane cyklicznie na podstawie zaplanowanych terminów follow-up leadów.', 'estate-office') . '</p>';
    }

    public static function renderReminderHoursField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][reminder_hours]');
        $value         = isset($notifications['reminder_hours']) ? (int) $notifications['reminder_hours'] : 24;

        printf(
            '<input type="number" id="estate_office_lead_reminder_hours" name="%1$s" value="%2$d" class="small-text" min="1" max="168" step="1" />',
            $field,
            $value
        );
        echo '<p class="description">' . esc_html__('Określ liczbę godzin przed terminem follow-up (zakres 1–168), w których zostanie wysłane przypomnienie.', 'estate-office') . '</p>';
    }

    public static function renderReminderSubjectField(): void
    {
        $notifications = self::getNotificationsOption();
        $field         = esc_attr(self::OPTION . '[lead_notifications][reminder_subject]');

        printf(
            '<input type="text" id="estate_office_lead_reminder_subject" name="%1$s" value="%2$s" class="regular-text" />',
            $field,
            esc_attr($notifications['reminder_subject'])
        );
        echo '<p class="description">' . esc_html__('Pozostaw puste, aby użyć domyślnego tematu „Przypomnienie follow-up: {nazwa leadu}”.', 'estate-office') . '</p>';
    }

    public static function renderReminderRecipientsField(): void
    {
        $notifications = self::getNotificationsOption();
        $agentField    = esc_attr(self::OPTION . '[lead_notifications][reminder_notify_agent]');
        $officeField   = esc_attr(self::OPTION . '[lead_notifications][reminder_notify_office]');

        echo '<label style="display:block">';
        printf(
            '<input type="checkbox" name="%1$s" value="1" %2$s /> %3$s',
            $agentField,
            checked(!empty($notifications['reminder_notify_agent']), true, false),
            esc_html__('Powiadom agenta przypisanego do leadu.', 'estate-office')
        );
        echo '</label>';

        echo '<label style="display:block">';
        printf(
            '<input type="checkbox" name="%1$s" value="1" %2$s /> %3$s',
            $officeField,
            checked(!empty($notifications['reminder_notify_office']), true, false),
            esc_html__('Wyślij kopię przypomnienia na adres biura.', 'estate-office')
        );
        echo '</label>';
        echo '<p class="description">' . esc_html__('Adres biura jest pobierany z pola „Kopia do biura”.', 'estate-office') . '</p>';
    }

    public static function renderClientRoleTemplate(): void
    {
        static $rendered = false;

        if ($rendered) {
            return;
        }

        $rendered = true;

        echo '<script type="text/template" id="estate-office-role-template">';
        ob_start();
        self::renderClientRoleRow('__index__', '', '', true);
        $template = ob_get_clean();
        echo $template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</script>';
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    public static function getPropertyDynamicFields(): array
    {
        return self::prepareDynamicFields('property_fields');
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    public static function getAgreementDynamicFields(): array
    {
        return self::prepareDynamicFields('agreement_fields');
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    public static function getClientDynamicFields(): array
    {
        return self::prepareDynamicFields('client_fields');
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    public static function getSearchDynamicFields(): array
    {
        return self::prepareDynamicFields('search_fields');
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    private static function prepareDynamicFields(string $optionKey): array
    {
        $option = get_option(self::OPTION);
        $values = isset($option[$optionKey]) && is_array($option[$optionKey]) ? $option[$optionKey] : [];

        $definitions = [];
        $usedKeys    = [];

        foreach ($values as $label) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            $base = sanitize_key($label);

            if ($base === '') {
                $base = 'field_' . substr(md5($label), 0, 8);
            }

            $key    = $base;
            $suffix = 2;

            while (in_array($key, $usedKeys, true)) {
                $key = $base . '_' . $suffix;
                $suffix++;
            }

            $usedKeys[]    = $key;
            $definitions[] = [
                'key'   => $key,
                'label' => $label,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    public static function getClientRoleDefinitions(): array
    {
        $option = get_option(self::OPTION);
        $roles  = isset($option['client_roles']) ? $option['client_roles'] : [];

        $roles = self::sanitizeRoles($roles);

        if ($roles === []) {
            return self::getDefaultClientRolesOption();
        }

        return $roles;
    }

    /**
     * @return array<string,string>
     */
    public static function getClientRoleOptions(): array
    {
        $options = [];

        foreach (self::getClientRoleDefinitions() as $role) {
            $options[$role['key']] = $role['label'];
        }

        return $options;
    }

    private static function sanitizeNotifications($value): array
    {
        $value = is_array($value) ? $value : [];

        $hours = isset($value['reminder_hours']) ? absint($value['reminder_hours']) : 24;
        if ($hours < 1) {
            $hours = 1;
        }
        if ($hours > 168) {
            $hours = 168;
        }

        return [
            'notify_agent'           => ! empty($value['notify_agent']),
            'include_message'        => ! empty($value['include_message']),
            'office_email'           => isset($value['office_email']) ? sanitize_email((string) $value['office_email']) : '',
            'office_subject'         => isset($value['office_subject']) ? sanitize_text_field((string) $value['office_subject']) : '',
            'agent_subject'          => isset($value['agent_subject']) ? sanitize_text_field((string) $value['agent_subject']) : '',
            'reminder_enabled'       => ! empty($value['reminder_enabled']),
            'reminder_hours'         => $hours,
            'reminder_subject'       => isset($value['reminder_subject']) ? sanitize_text_field((string) $value['reminder_subject']) : '',
            'reminder_notify_agent'  => ! empty($value['reminder_notify_agent']),
            'reminder_notify_office' => ! empty($value['reminder_notify_office']),
        ];
    }

    private static function getNotificationsOption(): array
    {
        $option = get_option(self::OPTION);
        $stored = isset($option['lead_notifications']) && is_array($option['lead_notifications']) ? $option['lead_notifications'] : [];

        return array_merge(self::getDefaultNotifications(), $stored);
    }

    private static function getDefaultNotifications(): array
    {
        return [
            'notify_agent'           => true,
            'include_message'        => true,
            'office_email'           => '',
            'office_subject'         => '',
            'agent_subject'          => '',
            'reminder_enabled'       => false,
            'reminder_hours'         => 24,
            'reminder_subject'       => '',
            'reminder_notify_agent'  => true,
            'reminder_notify_office' => false,
        ];
    }

    /**
     * @return array{notify_agent:bool,include_message:bool,office_email:string,office_subject:string,agent_subject:string}
     */
    public static function getLeadNotificationSettings(): array
    {
        $option = self::getNotificationsOption();

        return [
            'notify_agent'    => (bool) $option['notify_agent'],
            'include_message' => (bool) $option['include_message'],
            'office_email'    => (string) $option['office_email'],
            'office_subject'  => (string) $option['office_subject'],
            'agent_subject'   => (string) $option['agent_subject'],
        ];
    }

    /**
     * @return array{enabled:bool,hours:int,subject:string,notify_agent:bool,notify_office:bool}
     */
    public static function getLeadReminderSettings(): array
    {
        $option = self::getNotificationsOption();

        $hours = isset($option['reminder_hours']) ? (int) $option['reminder_hours'] : 24;
        if ($hours < 1) {
            $hours = 1;
        }
        if ($hours > 168) {
            $hours = 168;
        }

        return [
            'enabled'       => !empty($option['reminder_enabled']),
            'hours'         => $hours,
            'subject'       => (string) ($option['reminder_subject'] ?? ''),
            'notify_agent'  => !empty($option['reminder_notify_agent']),
            'notify_office' => !empty($option['reminder_notify_office']),
        ];
    }

    /**
     * @param mixed $value
     *
     * @return array<int,array{key:string,label:string}>
     */
    private static function sanitizeRoles($value): array
    {
        $value = is_array($value) ? array_values($value) : [];

        $roles    = [];
        $usedKeys = [];

        foreach ($value as $role) {
            $label = '';
            $key   = '';

            if (is_array($role)) {
                $label = isset($role['label']) ? sanitize_text_field((string) $role['label']) : '';
                $key   = isset($role['key']) ? sanitize_key((string) $role['key']) : '';
            } elseif (is_scalar($role)) {
                $label = sanitize_text_field((string) $role);
            }

            $label = trim($label);

            if ($label === '') {
                continue;
            }

            $baseKey = $key !== '' ? $key : sanitize_key(\remove_accents($label));

            if ($baseKey === '') {
                $baseKey = 'role_' . substr(md5($label), 0, 8);
            }

            $uniqueKey = $baseKey;
            $suffix    = 2;
            while (in_array($uniqueKey, $usedKeys, true)) {
                $uniqueKey = $baseKey . '_' . $suffix;
                $suffix++;
            }

            $usedKeys[] = $uniqueKey;
            $roles[]    = [
                'key'   => $uniqueKey,
                'label' => $label,
            ];
        }

        if ($roles === []) {
            return self::getDefaultClientRolesOption();
        }

        return $roles;
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    private static function getDefaultClientRolesOption(): array
    {
        return [
            ['key' => 'seller', 'label' => __('Sprzedający', 'estate-office')],
            ['key' => 'buyer', 'label' => __('Kupujący', 'estate-office')],
            ['key' => 'landlord', 'label' => __('Wynajmujący', 'estate-office')],
            ['key' => 'tenant', 'label' => __('Najemca', 'estate-office')],
            ['key' => 'other', 'label' => __('Inna rola', 'estate-office')],
        ];
    }

    private static function renderClientRoleRow(string $index, string $key, string $label, bool $isTemplate = false): void
    {
        $fieldBase = self::OPTION . '[client_roles][' . $index . ']';
        $autoAttr  = $isTemplate || $key === '' ? ' data-auto="1"' : ' data-auto="0"';

        echo '<div class="estate-office-role-manager__row">';
        echo '<div class="estate-office-role-manager__col estate-office-role-manager__col--label">';
        echo '<label>';
        echo '<span class="screen-reader-text">' . esc_html__('Nazwa roli', 'estate-office') . '</span>';
        printf(
            '<input type="text" class="regular-text estate-office-role-label" name="%1$s[label]" value="%2$s" placeholder="%3$s" autocomplete="off" />',
            esc_attr($fieldBase),
            esc_attr($label),
            esc_attr__('Np. Sprzedający', 'estate-office')
        );
        echo '</label>';
        echo '</div>';

        echo '<div class="estate-office-role-manager__col estate-office-role-manager__col--key">';
        echo '<label>';
        echo '<span class="screen-reader-text">' . esc_html__('Klucz roli', 'estate-office') . '</span>';
        printf(
            '<input type="text" class="regular-text estate-office-role-key" name="%1$s[key]" value="%2$s" placeholder="%3$s" autocomplete="off"%4$s />',
            esc_attr($fieldBase),
            esc_attr($key),
            esc_attr__('np. seller', 'estate-office'),
            $autoAttr
        );
        echo '</label>';
        echo '<p class="description">' . esc_html__('Klucz jest wykorzystywany do raportowania i integracji.', 'estate-office') . '</p>';
        echo '</div>';

        echo '<div class="estate-office-role-manager__col estate-office-role-manager__col--actions">';
        printf(
            '<button type="button" class="button-link delete estate-office-role-remove" aria-label="%1$s">%2$s</button>',
            esc_attr__('Usuń rolę', 'estate-office'),
            esc_html__('Usuń', 'estate-office')
        );
        echo '</div>';
        echo '</div>';
    }
}
