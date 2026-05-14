<?php
if (! defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('eocrm-frontend');
wp_enqueue_script('eocrm-frontend');

$tabs = [
    'dashboard' => 'Pulpit',
    'properties' => 'Nieruchomosci',
    'searches' => 'Poszukiwania',
    'agreements' => 'Umowy',
    'transactions' => 'Transakcje',
    'clients' => 'Klienci',
];
$can_manage_frontend_offices = isset($can_manage_frontend_offices) ? (bool) $can_manage_frontend_offices : current_user_can('manage_options');
$can_manage_frontend_agents = isset($can_manage_frontend_agents) ? (bool) $can_manage_frontend_agents : ($can_manage_frontend_offices || (class_exists('EstateOfficeCRM_Access') && EstateOfficeCRM_Access::is_manager_user(get_current_user_id())));
if ($can_manage_frontend_offices) {
    $tabs['offices'] = 'Biura';
}
if ($can_manage_frontend_agents) {
    $tabs['agents'] = 'Agenci';
}

$format_address = static function (array $parts): string {
    $clean = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $clean[] = $part;
        }
    }

    return implode(', ', $clean);
};
$transaction_icon_svg = '<svg class="eocrm-transaction-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5.4 6.8c0-.8.7-1.5 1.5-1.5h9.2c.8 0 1.5.7 1.5 1.5v1.1h1.1c.8 0 1.5.7 1.5 1.5v7.1c0 1.2-1 2.2-2.2 2.2H8.1c-1.5 0-2.7-1.2-2.7-2.7V6.8Z" fill="currentColor" opacity=".13"/><path d="M6.8 5.6h9.3c.7 0 1.3.6 1.3 1.3v1.2h1.1c.9 0 1.7.8 1.7 1.7v6.7c0 1.2-1 2.1-2.1 2.1H8.1c-1.5 0-2.7-1.2-2.7-2.7V7c0-.8.6-1.4 1.4-1.4Z" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/><path d="M17.4 8.1H8.1c-.7 0-1.3-.5-1.3-1.2" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.1 12.4h4.5M9.1 15.1h7.3M15.4 12.2l1.3 1.3 2.2-2.5" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$transaction_icon_allowed_svg = [
    'svg' => [
        'class' => true,
        'viewBox' => true,
        'viewbox' => true,
        'aria-hidden' => true,
        'focusable' => true,
    ],
    'path' => [
        'd' => true,
        'fill' => true,
        'stroke' => true,
        'stroke-width' => true,
        'stroke-linecap' => true,
        'stroke-linejoin' => true,
        'opacity' => true,
    ],
];
$render_transaction_icon = static function () use ($transaction_icon_svg, $transaction_icon_allowed_svg): void {
    echo wp_kses($transaction_icon_svg, $transaction_icon_allowed_svg);
};
$property_agreement_options = isset($property_agreement_options) && is_array($property_agreement_options) ? $property_agreement_options : [];
$search_agreement_options = isset($search_agreement_options) && is_array($search_agreement_options) ? $search_agreement_options : [];
$transaction_form_agreement = isset($transaction_form_agreement) && is_array($transaction_form_agreement) ? $transaction_form_agreement : null;
$transaction_form_properties = isset($transaction_form_properties) && is_array($transaction_form_properties) ? $transaction_form_properties : [];
$transaction_form_paid_commission_stage_names = isset($transaction_form_paid_commission_stage_names) && is_array($transaction_form_paid_commission_stage_names) ? $transaction_form_paid_commission_stage_names : [];
$transaction_agent_options = isset($transaction_agent_options) && is_array($transaction_agent_options) ? $transaction_agent_options : [];
$transaction_profile = isset($transaction_profile) && is_array($transaction_profile) ? $transaction_profile : null;
$transactions_mode = isset($transactions_mode) && is_string($transactions_mode) ? $transactions_mode : '';
$agreement_profile_transactions = isset($agreement_profile_transactions) && is_array($agreement_profile_transactions) ? $agreement_profile_transactions : [];
$prefill_agreement_id = isset($_GET['agreement_id']) ? absint((string) wp_unslash($_GET['agreement_id'])) : 0;
$current_url = isset($current_url) && is_string($current_url) && $current_url !== '' ? $current_url : (string) (get_permalink() ?: home_url('/crm/'));
$crm_logout_redirect_url = isset($crm_logout_redirect_url) && is_string($crm_logout_redirect_url) && $crm_logout_redirect_url !== ''
    ? $crm_logout_redirect_url
    : home_url('/');
$export_xml_url = isset($export_xml_url) && is_string($export_xml_url) && $export_xml_url !== ''
    ? $export_xml_url
    : (string) add_query_arg(
        [
            'eocrm_export' => 'xml',
            'eocrm_export_nonce' => wp_create_nonce('eocrm_export_xml'),
        ],
        $current_url
    );
$is_admin_user = current_user_can('manage_options');
$is_manager_user = class_exists('EstateOfficeCRM_Access') ? EstateOfficeCRM_Access::is_manager_user(get_current_user_id()) : false;
$show_owner_columns = $is_admin_user || $is_manager_user;
$resolve_owner_photo_url = isset($resolve_owner_photo_url) && is_callable($resolve_owner_photo_url)
    ? $resolve_owner_photo_url
    : static function (int $owner_user_id, string $size = 'thumbnail'): string {
        return '';
    };
$sections_with_export = ['properties', 'searches', 'agreements', 'clients'];
$active_mode = isset($_GET['mode']) ? sanitize_key((string) wp_unslash($_GET['mode'])) : '';
$is_table_listing_view = $active_mode === ''
    && ! isset($_GET['client_id'])
    && ! isset($_GET['agreement_id'])
    && ! isset($_GET['property_id'])
    && ! isset($_GET['search_id']);
$show_export_button = $is_admin_user
    && in_array($section, $sections_with_export, true)
    && $is_table_listing_view;
$owner_user_options = isset($owner_user_options) && is_array($owner_user_options) ? $owner_user_options : [];
$owner_option_ids = [];
foreach ($owner_user_options as $owner_option_row) {
    $owner_option_ids[] = isset($owner_option_row['id']) ? (int) $owner_option_row['id'] : 0;
}
$resolve_owner_label = static function (int $owner_user_id): string {
    if ($owner_user_id <= 0) {
        return '';
    }

    $owner_user = get_userdata($owner_user_id);
    return $owner_user instanceof WP_User ? (string) $owner_user->display_name : '';
};
$agreement_stage_options = isset($agreement_stage_options) && is_array($agreement_stage_options)
    ? array_values(array_filter(array_map(static function ($value): string {
        return trim(sanitize_text_field((string) $value));
    }, $agreement_stage_options), static function (string $value): bool {
        return $value !== '';
    }))
    : EstateOfficeCRM_Stages::default_for_transaction('SPRZEDAZ');
if (empty($agreement_stage_options)) {
    $agreement_stage_options = EstateOfficeCRM_Stages::default_for_transaction('SPRZEDAZ');
}
$agreement_stage_options_by_type = isset($agreement_stage_options_by_type) && is_array($agreement_stage_options_by_type)
    ? $agreement_stage_options_by_type
    : [
        'SPRZEDAZ' => EstateOfficeCRM_Stages::default_for_transaction('SPRZEDAZ'),
        'KUPNO' => EstateOfficeCRM_Stages::default_for_transaction('KUPNO'),
        'WYNAJEM' => EstateOfficeCRM_Stages::default_for_transaction('WYNAJEM'),
        'NAJEM' => EstateOfficeCRM_Stages::default_for_transaction('NAJEM'),
    ];
$agreement_stage_options_by_type_json = wp_json_encode($agreement_stage_options_by_type);
if (! is_string($agreement_stage_options_by_type_json) || $agreement_stage_options_by_type_json === '') {
    $agreement_stage_options_by_type_json = '{}';
}

$render_bool = static function ($value): string {
    if ((string) $value === '1') {
        return 'Tak';
    }
    if ((string) $value === '0') {
        return 'Nie';
    }

    return '-';
};

$render_list = static function ($items): string {
    if (! is_array($items) || empty($items)) {
        return '-';
    }

    $clean = [];
    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return empty($clean) ? '-' : implode(', ', $clean);
};

$is_agreement_stage_finished = static function ($stage): bool {
    $normalized = trim((string) $stage);
    if ($normalized === '') {
        return false;
    }

    $normalized = strtolower((string) preg_replace('/\s+/', ' ', $normalized));

    return strpos($normalized, 'umowa zako') === 0;
};

$format_portal_export_event = static function ($event): string {
    $value = sanitize_key((string) $event);
    return match ($value) {
        'insert' => 'Wysylka nowej oferty (insert)',
        'update' => 'Aktualizacja oferty (update)',
        'delete' => 'Usuniecie oferty z portalu (delete)',
        default => '-',
    };
};

$format_portal_export_status = static function ($status): string {
    $value = sanitize_key((string) $status);
    return match ($value) {
        'success' => 'Wyslano poprawnie',
        'pending' => 'Oczekuje w kolejce',
        'error' => 'Blad wysylki (bedzie ponawiane)',
        'failed' => 'Nie wyslano (limit prob)',
        'skipped' => 'Pominieto (eksport nieaktywny)',
        default => '-',
    };
};

$building_finish_options = [
    'Do remontu' => 'Do remontu',
    'Do odswiezenia' => 'Do od&#347;wie&#380;enia',
    'Dobry' => 'Dobry',
    'Bardzo dobry' => 'Bardzo dobry',
    'Deweloperski' => 'Deweloperski',
    'Wysoki standard' => 'Wysoki standard',
];

$kitchen_type_options = [
    'Aneks' => 'Aneks',
    'Oddzielna' => 'Oddzielna',
    'Polotwarta' => 'P&oacute;&#322;otwarta',
];

$property_price_currency_options = ['PLN', 'EUR', 'USD', 'GBP'];
$property_form_draft_new = isset($property_form_draft_new) && is_array($property_form_draft_new) ? $property_form_draft_new : [];
$property_form_draft_edit = isset($property_form_draft_edit) && is_array($property_form_draft_edit) ? $property_form_draft_edit : [];
$property_form_draft_new_json = wp_json_encode($property_form_draft_new);
if (! is_string($property_form_draft_new_json) || $property_form_draft_new_json === '') {
    $property_form_draft_new_json = '{}';
}
$property_form_draft_edit_json = wp_json_encode($property_form_draft_edit);
if (! is_string($property_form_draft_edit_json) || $property_form_draft_edit_json === '') {
    $property_form_draft_edit_json = '{}';
}

$property_parse_draft_attachment_ids = static function ($value): array {
    if (is_array($value)) {
        $value = implode(',', $value);
    }

    $ids = [];
    foreach (explode(',', (string) $value) as $raw_id) {
        $id = absint(trim((string) $raw_id));
        if ($id <= 0 || in_array($id, $ids, true)) {
            continue;
        }

        $ids[] = $id;
        if (count($ids) >= 80) {
            break;
        }
    }

    return $ids;
};

$property_build_gallery_preview_rows = static function (array $attachment_ids): array {
    $rows = [];
    foreach ($attachment_ids as $attachment_id) {
        $attachment_id = absint((string) $attachment_id);
        if ($attachment_id <= 0) {
            continue;
        }

        $thumb_url = (string) wp_get_attachment_image_url($attachment_id, 'thumbnail');
        if ($thumb_url === '') {
            $thumb_url = (string) wp_get_attachment_url($attachment_id);
        }

        if ($thumb_url === '') {
            continue;
        }

        $rows[] = [
            'attachment_id' => $attachment_id,
            'thumb_url' => $thumb_url,
        ];
    }

    return $rows;
};

$property_parse_draft_floor_plan_items = static function ($value): array {
    if (! is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (! is_array($decoded)) {
        return [];
    }

    $items = [];
    $used_ids = [];
    foreach ($decoded as $entry) {
        if (! is_array($entry)) {
            continue;
        }

        $attachment_id = isset($entry['attachment_id']) ? absint((string) $entry['attachment_id']) : 0;
        if ($attachment_id <= 0 || in_array($attachment_id, $used_ids, true)) {
            continue;
        }

        $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
        $label = trim((string) preg_replace('/\s+/', ' ', $label));
        if ($label === '') {
            $label = 'Poziom ' . (string) (count($items) + 1);
        }

        if (function_exists('mb_substr')) {
            $label = (string) mb_substr($label, 0, 80);
        } else {
            $label = substr($label, 0, 80);
        }

        $preview_url = (string) wp_get_attachment_image_url($attachment_id, 'thumbnail');
        if ($preview_url === '') {
            $preview_url = (string) wp_get_attachment_url($attachment_id);
        }

        $used_ids[] = $attachment_id;
        $items[] = [
            'attachment_id' => $attachment_id,
            'label' => $label,
            'position' => count($items),
            'preview_url' => $preview_url,
        ];

        if (count($items) >= 30) {
            break;
        }
    }

    return $items;
};

$property_floor_plan_items_to_json = static function (array $items): string {
    if (empty($items)) {
        return '';
    }

    $json = wp_json_encode($items);
    return is_string($json) ? $json : '';
};

$property_gallery_ids_new_form = $property_parse_draft_attachment_ids($property_form_draft_new['gallery_attachment_ids'] ?? '');
$property_gallery_preview_new_form = $property_build_gallery_preview_rows($property_gallery_ids_new_form);
$property_floor_plan_items_new_form = $property_parse_draft_floor_plan_items($property_form_draft_new['floor_plan_items_json'] ?? '');
$property_floor_plan_items_new_form_json = $property_floor_plan_items_to_json($property_floor_plan_items_new_form);
$default_agreement_numbers_by_type = isset($default_agreement_numbers_by_type) && is_array($default_agreement_numbers_by_type)
    ? $default_agreement_numbers_by_type
    : [
        'SPRZEDAZ' => isset($default_agreement_number) && is_string($default_agreement_number) ? $default_agreement_number : '',
        'KUPNO' => isset($default_agreement_number) && is_string($default_agreement_number) ? $default_agreement_number : '',
        'WYNAJEM' => isset($default_agreement_number) && is_string($default_agreement_number) ? $default_agreement_number : '',
        'NAJEM' => isset($default_agreement_number) && is_string($default_agreement_number) ? $default_agreement_number : '',
    ];
$agreement_number_defaults_json = wp_json_encode($default_agreement_numbers_by_type);
if (! is_string($agreement_number_defaults_json)) {
    $agreement_number_defaults_json = '{}';
}
$default_search_number = isset($default_search_number) && is_string($default_search_number) ? $default_search_number : 'SZ-1';

$normalize_property_currency = static function ($value) use ($property_price_currency_options): string {
    $currency = strtoupper(trim((string) $value));
    if (! in_array($currency, $property_price_currency_options, true)) {
        return 'PLN';
    }

    return $currency;
};

$format_property_price = static function ($value, $currency) use ($normalize_property_currency): string {
    if (! is_numeric($value)) {
        return '-';
    }

    $currency_code = $normalize_property_currency($currency);
    $formatted_value = number_format((float) $value, 2, '.', ' ');
    $formatted_value = rtrim(rtrim($formatted_value, '0'), '.');

    return $formatted_value . ' ' . $currency_code;
};

$format_optional_transaction_price = static function ($value, $currency) use ($format_property_price): string {
    if (! is_numeric($value) || (float) $value <= 0) {
        return '-';
    }

    return $format_property_price($value, $currency);
};

$format_transaction_remuneration = static function ($transaction_price, $commission_amount, $commission_unit, $currency) use ($normalize_property_currency): string {
    if (! is_numeric($commission_amount) || (float) $commission_amount <= 0) {
        return '-';
    }

    $unit = strtoupper(trim((string) $commission_unit));
    $currency_code = $normalize_property_currency($currency);
    if ($unit === '%') {
        if (! is_numeric($transaction_price) || (float) $transaction_price <= 0) {
            return '-';
        }
        $amount = ((float) $transaction_price * (float) $commission_amount) / 100;
        $formatted_amount = number_format($amount, 2, '.', ' ');
        $formatted_amount = rtrim(rtrim($formatted_amount, '0'), '.');
        return $formatted_amount . ' ' . $currency_code;
    }

    if (in_array($unit, ['PLN', 'EUR', 'USD', 'GBP'], true)) {
        $formatted_amount = number_format((float) $commission_amount, 2, '.', ' ');
        $formatted_amount = rtrim(rtrim($formatted_amount, '0'), '.');
        return $formatted_amount . ' ' . $unit;
    }

    return '-';
};

$parse_commission_stage_rows = static function ($json): array {
    $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : [];
    if (! is_array($decoded)) {
        return [];
    }

    $rows = [];
    foreach ($decoded as $row) {
        if (! is_array($row)) {
            continue;
        }

        $stage_name = trim(sanitize_text_field((string) ($row['stage_name'] ?? '')));
        $amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? (float) $row['amount'] : 0.0;
        $unit = strtoupper(trim(sanitize_text_field((string) ($row['unit'] ?? ''))));
        if ($stage_name === '' || $amount <= 0 || ! in_array($unit, ['%', 'PLN', 'EUR', 'USD'], true)) {
            continue;
        }

        $rows[] = [
            'stage_name' => $stage_name,
            'amount' => $amount,
            'unit' => $unit,
        ];
    }

    return $rows;
};

$format_commission_stage_value = static function (array $row): string {
    $amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? (float) $row['amount'] : 0.0;
    $unit = strtoupper(trim((string) ($row['unit'] ?? '')));
    if ($amount <= 0 || $unit === '') {
        return '-';
    }

    return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') . ' ' . $unit;
};

$normalize_commission_stage_key = static function (string $stage_name): string {
    $normalized = trim((string) preg_replace('/\s+/', ' ', $stage_name));
    if ($normalized === '') {
        return '';
    }

    return strtolower(remove_accents($normalized));
};

$is_settlement_commission_stage = static function (string $stage_name) use ($normalize_commission_stage_key): bool {
    $normalized = $normalize_commission_stage_key($stage_name);
    return strpos($normalized, 'umowa przyrzeczona') === 0 || strpos($normalized, 'umowa najmu') === 0;
};

$parse_commission_stage_payment_payload = static function ($json): array {
    $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : [];
    if (! is_array($decoded) || (string) ($decoded['payload_type'] ?? '') !== 'commission_stage_payment_v2') {
        return [
            'covered_stage_names' => [],
            'rows' => [],
            'effective' => [],
        ];
    }

    $covered_stage_names = [];
    $raw_names = isset($decoded['covered_stage_names']) && is_array($decoded['covered_stage_names']) ? $decoded['covered_stage_names'] : [];
    foreach ($raw_names as $name) {
        $name = trim(sanitize_text_field((string) $name));
        if ($name !== '' && ! in_array($name, $covered_stage_names, true)) {
            $covered_stage_names[] = $name;
        }
    }

    $rows = [];
    $raw_rows = isset($decoded['rows']) && is_array($decoded['rows']) ? $decoded['rows'] : [];
    foreach ($raw_rows as $row) {
        if (! is_array($row)) {
            continue;
        }

        $stage_name = trim(sanitize_text_field((string) ($row['stage_name'] ?? '')));
        $amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? (float) $row['amount'] : 0.0;
        $unit = strtoupper(trim(sanitize_text_field((string) ($row['unit'] ?? ''))));
        if ($stage_name !== '' && $amount > 0 && in_array($unit, ['%', 'PLN', 'EUR', 'USD'], true)) {
            $rows[] = [
                'stage_name' => $stage_name,
                'amount' => $amount,
                'unit' => $unit,
            ];
        }
    }

    $effective = isset($decoded['effective']) && is_array($decoded['effective']) ? $decoded['effective'] : [];

    return [
        'covered_stage_names' => $covered_stage_names,
        'rows' => $rows,
        'effective' => $effective,
    ];
};

$calculate_commission_effective_for_rows = static function (array $agreement, array $rows): array {
    $base_amount = isset($agreement['commission_amount']) && is_numeric((string) $agreement['commission_amount']) ? (float) $agreement['commission_amount'] : 0.0;
    $base_unit = strtoupper(trim((string) ($agreement['commission_unit'] ?? '')));
    $total_amount = 0.0;
    $total_unit = '';

    foreach ($rows as $row) {
        $row_amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? (float) $row['amount'] : 0.0;
        $row_unit = strtoupper(trim((string) ($row['unit'] ?? '')));
        if ($row_amount <= 0 || $row_unit === '') {
            continue;
        }

        if ($row_unit === '%') {
            if ($base_amount <= 0 || $base_unit === '') {
                return [];
            }
            $effective_amount = ($base_amount * $row_amount) / 100;
            $effective_unit = $base_unit;
        } else {
            $effective_amount = $row_amount;
            $effective_unit = $row_unit;
        }

        if ($total_unit === '') {
            $total_unit = $effective_unit;
        } elseif ($total_unit !== $effective_unit) {
            return [];
        }

        $total_amount += $effective_amount;
    }

    return $total_amount > 0 && $total_unit !== ''
        ? ['amount' => round($total_amount, 2), 'unit' => $total_unit]
        : [];
};

$build_commission_payment_options = static function (array $agreement, array $plan_rows, array $paid_stage_names) use ($agreement_stage_options_by_type, $normalize_commission_stage_key, $calculate_commission_effective_for_rows, $format_commission_stage_value, $is_settlement_commission_stage): array {
    $transaction_type = strtoupper(trim((string) ($agreement['transaction_type'] ?? '')));
    $stage_order = isset($agreement_stage_options_by_type[$transaction_type]) && is_array($agreement_stage_options_by_type[$transaction_type])
        ? array_values($agreement_stage_options_by_type[$transaction_type])
        : [];
    $stage_index_map = [];
    foreach ($stage_order as $index => $stage_name) {
        $key = $normalize_commission_stage_key((string) $stage_name);
        if ($key !== '') {
            $stage_index_map[$key] = (int) $index;
        }
    }

    $paid_keys = [];
    foreach ($paid_stage_names as $paid_stage_name) {
        $key = $normalize_commission_stage_key((string) $paid_stage_name);
        if ($key !== '' && ! in_array($key, $paid_keys, true)) {
            $paid_keys[] = $key;
        }
    }

    $options = [];
    foreach ($plan_rows as $target_row) {
        $target_stage_name = (string) ($target_row['stage_name'] ?? '');
        $target_key = $normalize_commission_stage_key($target_stage_name);
        if ($target_key === '' || in_array($target_key, $paid_keys, true)) {
            continue;
        }

        $target_index = isset($stage_index_map[$target_key]) ? (int) $stage_index_map[$target_key] : PHP_INT_MAX;
        $due_rows = [];
        foreach ($plan_rows as $candidate_row) {
            $candidate_stage_name = (string) ($candidate_row['stage_name'] ?? '');
            $candidate_key = $normalize_commission_stage_key($candidate_stage_name);
            if ($candidate_key === '' || in_array($candidate_key, $paid_keys, true)) {
                continue;
            }

            $candidate_index = isset($stage_index_map[$candidate_key]) ? (int) $stage_index_map[$candidate_key] : PHP_INT_MAX;
            if ($candidate_index <= $target_index || $candidate_key === $target_key) {
                $due_rows[] = $candidate_row;
            }
        }

        $effective = $calculate_commission_effective_for_rows($agreement, $due_rows);
        if (empty($effective)) {
            continue;
        }

        $covered_names = array_map(static function (array $row): string {
            return (string) ($row['stage_name'] ?? '');
        }, $due_rows);
        $raw_labels = array_map($format_commission_stage_value, $due_rows);
        $covered_label = count($covered_names) > 1 ? implode(' + ', $covered_names) : $target_stage_name;
        $share_label = count($raw_labels) > 1 ? implode(' + ', $raw_labels) : ($raw_labels[0] ?? '');

        $options[] = [
            'stage_name' => $target_stage_name,
            'amount' => (float) ($effective['amount'] ?? 0),
            'unit' => (string) ($effective['unit'] ?? ''),
            'covered_stage_names' => $covered_names,
            'covered_label' => $covered_label,
            'share_label' => $share_label,
            'requires_date' => $is_settlement_commission_stage($target_stage_name),
        ];
    }

    return $options;
};

$render_agreement_commission_split_fields = static function (array $rows, bool $enabled, string $transaction_type = '') use ($agreement_stage_options_by_type, $agreement_stage_options_by_type_json): void {
    $stage_options = [];
    $transaction_type = strtoupper(trim($transaction_type));
    if ($transaction_type !== '' && isset($agreement_stage_options_by_type[$transaction_type]) && is_array($agreement_stage_options_by_type[$transaction_type])) {
        foreach ($agreement_stage_options_by_type[$transaction_type] as $stage_option) {
            $stage_option = trim(sanitize_text_field((string) $stage_option));
            if ($stage_option !== '') {
                $stage_options[] = $stage_option;
            }
        }
    } else {
        foreach ($agreement_stage_options_by_type as $stage_group) {
            if (! is_array($stage_group)) {
                continue;
            }
            foreach ($stage_group as $stage_option) {
                $stage_option = trim(sanitize_text_field((string) $stage_option));
                if ($stage_option !== '' && ! in_array($stage_option, $stage_options, true)) {
                    $stage_options[] = $stage_option;
                }
            }
        }
    }
    if (empty($stage_options)) {
        $stage_options = EstateOfficeCRM_Stages::default_for_transaction('SPRZEDAZ');
    }

    $render_row = static function (array $row, $index, bool $is_template = false) use ($stage_options): void {
        $index_attr = $is_template ? '__index__' : (string) absint((string) $index);
        $stage_name = (string) ($row['stage_name'] ?? '');
        $amount = isset($row['amount']) && is_numeric((string) $row['amount']) ? rtrim(rtrim(number_format((float) $row['amount'], 2, '.', ''), '0'), '.') : '';
        $unit = (string) ($row['unit'] ?? '');
        ?>
        <div class="eocrm-commission-stage-row" data-eocrm-commission-stage-row>
            <p class="eocrm-form-field">
                <label>Etap umowy</label>
                <select name="commission_stages[<?php echo esc_attr($index_attr); ?>][stage_name]" data-eocrm-commission-stage-select>
                    <option value="">Wybierz etap</option>
                    <?php foreach ($stage_options as $stage_option) : ?>
                        <option value="<?php echo esc_attr($stage_option); ?>" <?php selected($stage_name, $stage_option); ?>><?php echo esc_html($stage_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="eocrm-form-field">
                <label>Wysoko&#347;&#263;</label>
                <input type="number" min="0" step="0.01" name="commission_stages[<?php echo esc_attr($index_attr); ?>][amount]" value="<?php echo esc_attr($amount); ?>" placeholder="np. 50 lub 5000">
            </p>
            <p class="eocrm-form-field">
                <label>Jednostka</label>
                <select name="commission_stages[<?php echo esc_attr($index_attr); ?>][unit]">
                    <option value="">Wybierz</option>
                    <option value="%" <?php selected($unit, '%'); ?>>%</option>
                    <option value="PLN" <?php selected($unit, 'PLN'); ?>>PLN</option>
                    <option value="EUR" <?php selected($unit, 'EUR'); ?>>EUR</option>
                    <option value="USD" <?php selected($unit, 'USD'); ?>>USD</option>
                </select>
            </p>
            <button type="button" class="eocrm-btn eocrm-btn-soft-danger eocrm-commission-stage-remove" data-eocrm-commission-stage-remove>Usu&#324;</button>
        </div>
        <?php
    };
    ?>
    <div class="eocrm-commission-split" data-eocrm-commission-stages-builder data-stage-options-by-type="<?php echo esc_attr($agreement_stage_options_by_type_json); ?>" data-current-transaction-type="<?php echo esc_attr($transaction_type); ?>">
        <p class="eocrm-form-field">
            <label>Podzia&#322; prowizji na etapy</label>
            <span class="eocrm-choice-toggle">
                <label class="eocrm-choice-toggle-item">
                    <input type="radio" name="commission_split_enabled" value="1" data-eocrm-commission-split-toggle <?php checked($enabled); ?>>
                    <span>Tak</span>
                </label>
                <label class="eocrm-choice-toggle-item">
                    <input type="radio" name="commission_split_enabled" value="0" data-eocrm-commission-split-toggle <?php checked(! $enabled); ?>>
                    <span>Nie</span>
                </label>
            </span>
        </p>
        <div class="eocrm-commission-stages-panel" data-eocrm-commission-stages-panel <?php echo $enabled ? '' : 'hidden'; ?>>
            <p class="eocrm-muted">Wska&#380; etapy umowy, przy kt&oacute;rych CRM ma zapyta&#263; o utworzenie transakcji prowizyjnej.</p>
            <div class="eocrm-commission-stages-list" data-eocrm-commission-stages-list>
                <?php foreach ($rows as $row_index => $row) : ?>
                    <?php $render_row(is_array($row) ? $row : [], $row_index); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="eocrm-btn" data-eocrm-commission-stage-add>Dodaj etap prowizji</button>
        </div>
        <template data-eocrm-commission-stage-template>
            <?php $render_row([], '__index__', true); ?>
        </template>
    </div>
    <?php
};

$format_property_price_per_m2 = static function ($value, $currency) use ($normalize_property_currency): string {
    if (! is_numeric($value)) {
        return '-';
    }

    $currency_code = $normalize_property_currency($currency);
    $formatted_value = number_format((float) $value, 2, '.', ' ');
    $formatted_value = rtrim(rtrim($formatted_value, '0'), '.');

    return $formatted_value . ' ' . $currency_code . ' /m2';
};

$render_property_extra_areas = static function ($extra_areas): string {
    if (! is_array($extra_areas) || empty($extra_areas)) {
        return '-';
    }

    $labels = [
        'balcony' => 'Balkon',
        'terrace' => 'Taras',
        'basement' => 'Piwnica',
        'storage' => 'Komorka lokatorska',
        'garden' => 'Ogrodek',
    ];

    $chunks = [];
    foreach ($labels as $key => $label) {
        $row = isset($extra_areas[$key]) && is_array($extra_areas[$key]) ? $extra_areas[$key] : [];
        if (empty($row)) {
            continue;
        }

        $has = ! empty($row['has']);
        if (! $has) {
            continue;
        }

        $parts = ['tak'];
        if (array_key_exists('count', $row) && is_numeric($row['count'])) {
            $parts[] = 'ilosc: ' . (string) ((int) $row['count']);
        }
        if (array_key_exists('area', $row) && is_numeric($row['area'])) {
            $parts[] = 'pow.: ' . rtrim(rtrim(number_format((float) $row['area'], 2, '.', ''), '0'), '.') . ' m2';
        }

        $chunks[] = $label . ': ' . implode(', ', $parts);
    }

    return empty($chunks) ? '-' : implode('; ', $chunks);
};
$render_search_extra_areas = static function ($extra_areas): string {
    if (! is_array($extra_areas) || empty($extra_areas)) {
        return '-';
    }

    $labels = [
        'balcony' => 'Balkon',
        'terrace' => 'Taras',
        'basement' => 'Piwnica',
        'storage' => 'Komorka lokatorska',
        'garden' => 'Ogrodek',
    ];

    $chunks = [];
    foreach ($labels as $key => $label) {
        $row = isset($extra_areas[$key]) && is_array($extra_areas[$key]) ? $extra_areas[$key] : [];
        if (empty($row) || ! array_key_exists('has', $row)) {
            continue;
        }

        $has = ! empty($row['has']);
        if (! $has) {
            continue;
        }

        $parts = ['tak'];
        if (array_key_exists('area_min', $row) && is_numeric($row['area_min'])) {
            $parts[] = 'min. pow.: ' . rtrim(rtrim(number_format((float) $row['area_min'], 2, '.', ''), '0'), '.') . ' m2';
        }

        $chunks[] = $label . ': ' . implode(', ', $parts);
    }

    return empty($chunks) ? '-' : implode('; ', $chunks);
};

$render_search_property_types = static function ($raw_value): string {
    $labels = [
        'MIESZKANIE' => 'Mieszkanie',
        'DOM' => 'Dom',
        'DZIALKA' => 'Dzia&#322;ka',
        'LOKAL_HU' => 'Lokal H/U',
    ];

    $raw_items = is_array($raw_value) ? $raw_value : explode(',', (string) $raw_value);
    $chunks = [];
    foreach ($raw_items as $raw_item) {
        $code = strtoupper(trim((string) $raw_item));
        if ($code === '') {
            continue;
        }

        $chunks[] = $labels[$code] ?? ucfirst(strtolower($code));
    }

    return empty($chunks) ? '-' : implode(', ', array_values(array_unique($chunks)));
};

$render_search_amenities = static function ($items, $counts = []): string {
    if (! is_array($items) || empty($items)) {
        return '-';
    }

    $labels = [
        'winda' => 'Winda',
        'klimatyzacja' => 'Klimatyzacja',
        'monitoring' => 'Monitoring/Ochrona',
        'recepcja' => 'Recepcja',
        'teren_zamkniety' => 'Teren zamkni&#281;ty',
        'domofon' => 'Domofon',
        'garaz' => 'Gara&#380;',
        'miejsce_postojowe' => 'Miejsce postojowe',
    ];

    $counts = is_array($counts) ? $counts : [];
    $chunks = [];
    foreach ($items as $item) {
        $key = trim((string) $item);
        if ($key === '') {
            continue;
        }

        $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        if (isset($counts[$key]) && is_numeric($counts[$key])) {
            $label .= ': ' . (string) ((int) $counts[$key]);
        }

        $chunks[] = $label;
    }

    return empty($chunks) ? '-' : implode(', ', $chunks);
};

$property_additional_fields = isset($property_additional_fields) && is_array($property_additional_fields) ? $property_additional_fields : [];
$property_additional_fields_by_section = [
    'details' => [],
    'media' => [],
    'amenities' => [],
    'equipment' => [],
];
foreach ($property_additional_fields as $property_additional_field_row) {
    if (! is_array($property_additional_field_row)) {
        continue;
    }

    $property_additional_key = sanitize_key((string) ($property_additional_field_row['key'] ?? ''));
    $property_additional_label = sanitize_text_field((string) ($property_additional_field_row['label'] ?? ''));
    $property_additional_section = sanitize_key((string) ($property_additional_field_row['section'] ?? 'details'));
    $property_additional_type = sanitize_key((string) ($property_additional_field_row['type'] ?? 'text'));

    if ($property_additional_key === '' || $property_additional_label === '') {
        continue;
    }
    if (! isset($property_additional_fields_by_section[$property_additional_section])) {
        $property_additional_section = 'details';
    }
    if (! in_array($property_additional_type, ['text', 'number', 'checkbox'], true)) {
        $property_additional_type = 'text';
    }

    $property_additional_fields_by_section[$property_additional_section][] = [
        'key' => $property_additional_key,
        'label' => $property_additional_label,
        'type' => $property_additional_type,
    ];
}

$property_additional_collect_form_values = static function (array $source_values, array $definitions): array {
    $collected = [];

    foreach ($definitions as $definition_row) {
        if (! is_array($definition_row)) {
            continue;
        }

        $definition_key = sanitize_key((string) ($definition_row['key'] ?? ''));
        $definition_type = sanitize_key((string) ($definition_row['type'] ?? 'text'));
        if ($definition_key === '') {
            continue;
        }

        $input_name = EstateOfficeCRM_Property_Custom_Fields::input_name($definition_key);
        if (! array_key_exists($input_name, $source_values)) {
            continue;
        }

        $raw_value = $source_values[$input_name];
        if (is_array($raw_value)) {
            continue;
        }

        $clean_value = EstateOfficeCRM_Property_Custom_Fields::sanitize_value($raw_value, $definition_type);
        if (! EstateOfficeCRM_Property_Custom_Fields::has_value($clean_value, $definition_type)) {
            continue;
        }

        $collected[$definition_key] = $clean_value;
    }

    return $collected;
};

$property_additional_render_form_section = static function (string $section_key, array $definitions_by_section, array $current_values = []): void {
    if (! isset($definitions_by_section[$section_key]) || ! is_array($definitions_by_section[$section_key])) {
        return;
    }

    $fields = $definitions_by_section[$section_key];
    if (empty($fields)) {
        return;
    }

    echo '<div class="eocrm-form-grid eocrm-property-custom-fields">';
    foreach ($fields as $field_row) {
        if (! is_array($field_row)) {
            continue;
        }

        $field_key = sanitize_key((string) ($field_row['key'] ?? ''));
        $field_label = sanitize_text_field((string) ($field_row['label'] ?? ''));
        $field_type = sanitize_key((string) ($field_row['type'] ?? 'text'));
        if ($field_key === '' || $field_label === '') {
            continue;
        }
        if (! in_array($field_type, ['text', 'number', 'checkbox'], true)) {
            $field_type = 'text';
        }

        $input_name = EstateOfficeCRM_Property_Custom_Fields::input_name($field_key);
        $stored_value = array_key_exists($field_key, $current_values) ? $current_values[$field_key] : '';
        $field_value = EstateOfficeCRM_Property_Custom_Fields::value_for_form($stored_value, $field_type);

        if ($field_type === 'checkbox') {
            echo '<p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="' . esc_attr($input_name) . '" value="1" ' . checked(! empty($field_value), true, false) . '>' . esc_html($field_label) . '</label></p>';
            continue;
        }

        $input_type = $field_type === 'number' ? 'number' : 'text';
        $input_step = $field_type === 'number' ? 'any' : '';
        echo '<p class="eocrm-form-field"><label>' . esc_html($field_label) . '</label><input type="' . esc_attr($input_type) . '"' . ($input_step !== '' ? ' step="' . esc_attr($input_step) . '"' : '') . ' name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $field_value) . '"></p>';
    }
    echo '</div>';
};

$property_additional_build_profile_rows = static function (array $definitions_by_section, array $stored_values, string $section_key): array {
    if (! isset($definitions_by_section[$section_key]) || ! is_array($definitions_by_section[$section_key])) {
        return [];
    }

    $rows = [];
    foreach ($definitions_by_section[$section_key] as $field_row) {
        if (! is_array($field_row)) {
            continue;
        }

        $field_key = sanitize_key((string) ($field_row['key'] ?? ''));
        $field_label = sanitize_text_field((string) ($field_row['label'] ?? ''));
        $field_type = sanitize_key((string) ($field_row['type'] ?? 'text'));
        if ($field_key === '' || $field_label === '' || ! array_key_exists($field_key, $stored_values)) {
            continue;
        }

        $value = EstateOfficeCRM_Property_Custom_Fields::sanitize_value($stored_values[$field_key], $field_type);
        if (! EstateOfficeCRM_Property_Custom_Fields::has_value($value, $field_type)) {
            continue;
        }

        $rows[] = [
            'label' => $field_label,
            'value' => EstateOfficeCRM_Property_Custom_Fields::format_value($value, $field_type),
        ];
    }

    return $rows;
};

$property_custom_fields_new_form = [];
if (! empty($property_form_draft_new) && is_array($property_form_draft_new)) {
    $property_custom_fields_new_form = $property_additional_collect_form_values($property_form_draft_new, $property_additional_fields);
}
?>
<div class="eocrm-wrap" data-section="<?php echo esc_attr($section); ?>">
    <header class="eocrm-header">
        <div>
            <h2>Estate Office CRM</h2>
            <p>Frontend CRM dostepny tylko dla zalogowanych agentow i administratorow.</p>
        </div>
        <div class="eocrm-header-actions">
            <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'mode' => 'new-agreement'], $current_url)); ?>">Dodaj now&#261; Umow&#281;</a>
            <?php if ($show_export_button) : ?>
                <a class="eocrm-btn eocrm-btn-export" href="<?php echo esc_url($export_xml_url); ?>" title="Eksportuj biezaca tabele CRM do XML" aria-label="Eksportuj biezaca tabele CRM do XML">
                    <span aria-hidden="true" class="eocrm-export-icon">&#8681;</span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <nav class="eocrm-tabs" aria-label="Nawigacja CRM">
        <?php foreach ($tabs as $tab_key => $tab_label) : ?>
            <a class="eocrm-tab <?php echo $section === $tab_key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('crm', $tab_key, $current_url)); ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($crm_notice === 'license_read_only') : ?>
        <div class="eocrm-alert eocrm-alert-error">
            <?php echo esc_html($crm_message !== '' ? $crm_message : EstateOfficeCRM_License::read_only_message()); ?>
        </div>
    <?php endif; ?>

    <?php if ($section === 'dashboard') : ?>
        <?php
        $dashboard_role = isset($dashboard_role) ? (string) $dashboard_role : 'agent';
        $dashboard_user_first_name = isset($dashboard_user_first_name) ? (string) $dashboard_user_first_name : '';
        $dashboard_user_display_name = isset($dashboard_user_display_name) ? (string) $dashboard_user_display_name : '';
        $dashboard_office_name = isset($dashboard_office_name) ? (string) $dashboard_office_name : '';
        $dashboard_recent_agreements = isset($dashboard_recent_agreements) && is_array($dashboard_recent_agreements) ? $dashboard_recent_agreements : [];
        $dashboard_recent_properties = isset($dashboard_recent_properties) && is_array($dashboard_recent_properties) ? $dashboard_recent_properties : [];
        $dashboard_recent_searches = isset($dashboard_recent_searches) && is_array($dashboard_recent_searches) ? $dashboard_recent_searches : [];
        $dashboard_transaction_breakdown = isset($dashboard_transaction_breakdown) && is_array($dashboard_transaction_breakdown) ? $dashboard_transaction_breakdown : [];
        $dashboard_export_breakdown = isset($dashboard_export_breakdown) && is_array($dashboard_export_breakdown) ? $dashboard_export_breakdown : ['www_only' => 0, 'portals_only' => 0, 'both' => 0, 'none' => 0];
        $dashboard_gross_remuneration = isset($dashboard_gross_remuneration) && is_array($dashboard_gross_remuneration) ? $dashboard_gross_remuneration : [];
        $format_dashboard_money = static function ($value, string $currency): string {
            if (! is_numeric($value)) {
                return '0 ' . $currency;
            }

            $formatted = number_format((float) $value, 2, '.', ' ');
            $formatted = rtrim(rtrim($formatted, '0'), '.');

            return $formatted . ' ' . $currency;
        };

        $dashboard_role_label = match ($dashboard_role) {
            'admin' => 'Administrator',
            'manager' => 'Mened&#380;er Biura',
            default => 'Agent',
        };
        $dashboard_role_subtitle = match ($dashboard_role) {
            'admin' => 'Pe&#322;ny przegl&#261;d aktywno&#347;ci w ca&#322;ej firmie.',
            'manager' => $dashboard_office_name !== ''
                ? 'Pulpit Mened&#380;era - biuro: ' . esc_html($dashboard_office_name) . '.'
                : 'Pulpit Mened&#380;era Biura - przegl&#261;d zespo&#322;u.',
            default => 'Tw&#243;j osobisty pulpit z najwa&#380;niejszymi sprawami.',
        };
        $dashboard_kpi_label_properties = match ($dashboard_role) {
            'admin' => 'Opublikowane nieruchomo&#347;ci',
            'manager' => 'Oferty WWW biura',
            default => 'Moje oferty WWW',
        };
        $dashboard_kpi_label_agreements = match ($dashboard_role) {
            'admin' => 'Aktywne umowy',
            'manager' => 'Aktywne umowy biura',
            default => 'Moje aktywne umowy',
        };
        $dashboard_kpi_label_searches = match ($dashboard_role) {
            'admin' => 'Aktywne poszukiwania',
            'manager' => 'Poszukiwania biura',
            default => 'Moje poszukiwania',
        };
        $dashboard_kpi_label_clients = match ($dashboard_role) {
            'admin' => 'Wszyscy klienci',
            'manager' => 'Klienci biura',
            default => 'Moi klienci',
        };

        $tx_total_count = 0;
        foreach ($dashboard_transaction_breakdown as $tx_count) {
            $tx_total_count += (int) $tx_count;
        }
        $tx_label_map = [
            'SPRZEDAZ' => 'Sprzeda&#380;',
            'KUPNO' => 'Kupno',
            'WYNAJEM' => 'Wynajem',
            'NAJEM' => 'Najem',
        ];

        $exp_total = (int) $dashboard_export_breakdown['www_only']
            + (int) $dashboard_export_breakdown['portals_only']
            + (int) $dashboard_export_breakdown['both']
            + (int) $dashboard_export_breakdown['none'];
        ?>

        <section class="eocrm-dash">
            <header class="eocrm-dash-hero">
                <div class="eocrm-dash-hero__left">
                    <span class="eocrm-dash-hero__role"><?php echo esc_html($dashboard_role_label); ?></span>
                    <h2 class="eocrm-dash-hero__greeting">
                        <?php
                        $greet_name = $dashboard_user_first_name !== '' ? $dashboard_user_first_name : ($dashboard_user_display_name !== '' ? $dashboard_user_display_name : '');
                        if ($greet_name !== '') {
                            echo 'Dzie&#324; dobry, <strong>' . esc_html($greet_name) . '</strong>!';
                        } else {
                            echo 'Dzie&#324; dobry!';
                        }
                        ?>
                    </h2>
                    <p class="eocrm-dash-hero__subtitle"><?php echo esc_html(wp_strip_all_tags($dashboard_role_subtitle)); ?></p>
                </div>
                <div class="eocrm-dash-hero__quick">
                    <a class="eocrm-dash-quick" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'mode' => 'new-agreement'], $current_url)); ?>">
                        <span class="eocrm-dash-quick__icon" aria-hidden="true">+</span>
                        <span class="eocrm-dash-quick__label">Nowa umowa</span>
                    </a>
                    <a class="eocrm-dash-quick" href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'mode' => 'new-property'], $current_url)); ?>">
                        <span class="eocrm-dash-quick__icon" aria-hidden="true">&#127968;</span>
                        <span class="eocrm-dash-quick__label">Nieruchomo&#347;&#263;</span>
                    </a>
                    <a class="eocrm-dash-quick" href="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'mode' => 'new-client'], $current_url)); ?>">
                        <span class="eocrm-dash-quick__icon" aria-hidden="true">&#128100;</span>
                        <span class="eocrm-dash-quick__label">Klient</span>
                    </a>
                    <a class="eocrm-dash-quick" href="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'mode' => 'new-search'], $current_url)); ?>">
                        <span class="eocrm-dash-quick__icon" aria-hidden="true">&#128269;</span>
                        <span class="eocrm-dash-quick__label">Poszukiwanie</span>
                    </a>
                </div>
            </header>

            <div class="eocrm-dash-kpis">
                <a class="eocrm-dash-kpi eocrm-dash-kpi--blue" href="<?php echo esc_url(add_query_arg(['crm' => 'properties'], $current_url)); ?>">
                    <span class="eocrm-dash-kpi__icon" aria-hidden="true">&#127970;</span>
                    <div class="eocrm-dash-kpi__body">
                        <span class="eocrm-dash-kpi__label"><?php echo esc_html(wp_strip_all_tags($dashboard_kpi_label_properties)); ?></span>
                        <span class="eocrm-dash-kpi__value"><?php echo esc_html((string) $dashboard_stats['properties']); ?></span>
                        <span class="eocrm-dash-kpi__sub"><?php echo esc_html($dashboard_role === 'admin' ? 'Wszystkie aktywne oferty WWW' : ($dashboard_role === 'manager' ? 'Aktywne oferty biura' : 'Twoje aktywne oferty')); ?></span>
                    </div>
                </a>
                <a class="eocrm-dash-kpi eocrm-dash-kpi--green" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements'], $current_url)); ?>">
                    <span class="eocrm-dash-kpi__icon" aria-hidden="true">&#128196;</span>
                    <div class="eocrm-dash-kpi__body">
                        <span class="eocrm-dash-kpi__label"><?php echo esc_html(wp_strip_all_tags($dashboard_kpi_label_agreements)); ?></span>
                        <span class="eocrm-dash-kpi__value"><?php echo esc_html((string) $dashboard_stats['agreements']); ?></span>
                        <span class="eocrm-dash-kpi__sub">W trakcie realizacji</span>
                    </div>
                </a>
                <a class="eocrm-dash-kpi eocrm-dash-kpi--purple" href="<?php echo esc_url(add_query_arg(['crm' => 'searches'], $current_url)); ?>">
                    <span class="eocrm-dash-kpi__icon" aria-hidden="true">&#128269;</span>
                    <div class="eocrm-dash-kpi__body">
                        <span class="eocrm-dash-kpi__label"><?php echo esc_html(wp_strip_all_tags($dashboard_kpi_label_searches)); ?></span>
                        <span class="eocrm-dash-kpi__value"><?php echo esc_html((string) $dashboard_stats['searches']); ?></span>
                        <span class="eocrm-dash-kpi__sub">Aktywne zapytania klient&#243;w</span>
                    </div>
                </a>
                <a class="eocrm-dash-kpi eocrm-dash-kpi--orange" href="<?php echo esc_url(add_query_arg(['crm' => 'clients'], $current_url)); ?>">
                    <span class="eocrm-dash-kpi__icon" aria-hidden="true">&#128101;</span>
                    <div class="eocrm-dash-kpi__body">
                        <span class="eocrm-dash-kpi__label"><?php echo esc_html(wp_strip_all_tags($dashboard_kpi_label_clients)); ?></span>
                        <span class="eocrm-dash-kpi__value"><?php echo esc_html((string) $dashboard_stats['clients']); ?></span>
                        <span class="eocrm-dash-kpi__sub"><?php echo esc_html($dashboard_role === 'agent' ? 'Klienci, kt&#243;rych obs&#322;ugujesz' : 'L&#261;cznie w bazie'); ?></span>
                    </div>
                </a>
            </div>

            <?php if (! empty($dashboard_gross_remuneration)) : ?>
                <article class="eocrm-dash-panel eocrm-dash-panel--gross" data-eocrm-dash-remuneration>
                    <header class="eocrm-dash-panel__head">
                        <div>
                            <h3>Wynagrodzenie brutto</h3>
                            <p class="eocrm-dash-panel__hint">Suma prowizji z zapisanych transakcji wg daty transakcji.</p>
                        </div>
                        <a class="eocrm-dash-panel__more" href="<?php echo esc_url(add_query_arg(['crm' => 'transactions'], $current_url)); ?>">Transakcje &rarr;</a>
                    </header>
                    <div class="eocrm-dash-rem-tabs" role="tablist" aria-label="Okres wynagrodzenia brutto">
                        <?php foreach ($dashboard_gross_remuneration as $rem_index => $rem_month) :
                            $rem_key = (string) ($rem_month['key'] ?? ('month-' . $rem_index));
                        ?>
                            <button
                                type="button"
                                class="eocrm-dash-rem-tab <?php echo $rem_index === 0 ? 'is-active' : ''; ?>"
                                data-eocrm-dash-rem-tab="<?php echo esc_attr($rem_key); ?>"
                                aria-selected="<?php echo esc_attr($rem_index === 0 ? 'true' : 'false'); ?>"
                            >
                                <?php echo wp_kses_post((string) ($rem_month['label'] ?? 'Miesi&#261;c')); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="eocrm-dash-rem-panels">
                        <?php foreach ($dashboard_gross_remuneration as $rem_index => $rem_month) :
                            $rem_key = (string) ($rem_month['key'] ?? ('month-' . $rem_index));
                            $rem_totals = isset($rem_month['totals']) && is_array($rem_month['totals']) ? $rem_month['totals'] : [];
                            $rem_agents = isset($rem_month['agents']) && is_array($rem_month['agents']) ? $rem_month['agents'] : [];
                            $rem_transactions = (int) ($rem_month['transactions'] ?? 0);
                        ?>
                            <section class="eocrm-dash-rem-panel <?php echo $rem_index === 0 ? 'is-active' : ''; ?>" data-eocrm-dash-rem-panel="<?php echo esc_attr($rem_key); ?>" <?php echo $rem_index === 0 ? '' : 'hidden'; ?>>
                                <div class="eocrm-dash-rem-panel__meta">
                                    <span><?php echo esc_html((string) ($rem_month['period'] ?? '')); ?></span>
                                    <strong><?php echo esc_html((string) $rem_transactions); ?> transakcji</strong>
                                </div>
                                <?php if (empty($rem_totals)) : ?>
                                    <div class="eocrm-dash-rem-total">
                                        <span class="eocrm-dash-rem-total__value">0 PLN</span>
                                        <span class="eocrm-dash-rem-total__currency">Brak prowizji w tym okresie</span>
                                    </div>
                                <?php else : ?>
                                    <div class="eocrm-dash-rem-totals">
                                        <?php foreach ($rem_totals as $rem_currency => $rem_value) : ?>
                                            <div class="eocrm-dash-rem-total">
                                                <span class="eocrm-dash-rem-total__value"><?php echo esc_html($format_dashboard_money($rem_value, (string) $rem_currency)); ?></span>
                                                <span class="eocrm-dash-rem-total__currency"><?php echo esc_html((string) $rem_currency); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($dashboard_role, ['admin', 'manager'], true)) : ?>
                                    <div class="eocrm-dash-rem-agents">
                                        <h4>Wynagrodzenia agent&#243;w</h4>
                                        <?php if (empty($rem_agents)) : ?>
                                            <p class="eocrm-dash-rem-agents__empty">Brak prowizji agent&#243;w w tym okresie.</p>
                                        <?php else : ?>
                                            <ul>
                                                <?php foreach ($rem_agents as $rem_agent) :
                                                    $agent_totals = isset($rem_agent['totals']) && is_array($rem_agent['totals']) ? $rem_agent['totals'] : [];
                                                    $agent_total_labels = [];
                                                    foreach ($agent_totals as $agent_currency => $agent_value) {
                                                        $agent_total_labels[] = $format_dashboard_money($agent_value, (string) $agent_currency);
                                                    }
                                                ?>
                                                    <li>
                                                        <span class="eocrm-dash-rem-agent__name"><?php echo esc_html((string) ($rem_agent['name'] ?? 'Brak opiekuna')); ?></span>
                                                        <span class="eocrm-dash-rem-agent__meta"><?php echo esc_html((string) ((int) ($rem_agent['transactions'] ?? 0))); ?> transakcji</span>
                                                        <strong><?php echo esc_html(! empty($agent_total_labels) ? implode(' / ', $agent_total_labels) : '0 PLN'); ?></strong>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endif; ?>

            <div class="eocrm-dash-grid">
                <article class="eocrm-dash-panel eocrm-dash-panel--agreements">
                    <header class="eocrm-dash-panel__head">
                        <div>
                            <h3>Najnowsze umowy</h3>
                            <p class="eocrm-dash-panel__hint"><?php echo esc_html($dashboard_role === 'agent' ? 'Twoje ostatnie umowy w CRM' : ($dashboard_role === 'manager' ? 'Ostatnie umowy w biurze' : 'Ostatnio dodane umowy w firmie')); ?></p>
                        </div>
                        <a class="eocrm-dash-panel__more" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements'], $current_url)); ?>">Zobacz wszystkie &rarr;</a>
                    </header>
                    <?php if (empty($dashboard_recent_agreements)) : ?>
                        <p class="eocrm-dash-empty">Brak umow do wyswietlenia.</p>
                    <?php else : ?>
                        <ul class="eocrm-dash-list">
                            <?php foreach (array_slice($dashboard_recent_agreements, 0, 5) as $rec_ag) :
                                $rec_ag_id = (int) ($rec_ag['id'] ?? 0);
                                $rec_ag_number = (string) ($rec_ag['agreement_number'] ?? '');
                                $rec_ag_tx = strtoupper((string) ($rec_ag['transaction_type'] ?? ''));
                                $rec_ag_stage = (string) ($rec_ag['current_stage'] ?? '');
                                $rec_ag_addr_line = trim((string) ($rec_ag['property_address_line'] ?? ''));
                                $rec_ag_city = (string) ($rec_ag['property_city'] ?? '');
                                $rec_ag_address = $format_address([$rec_ag_addr_line, $rec_ag_city]);
                                $rec_ag_url = add_query_arg(['crm' => 'agreements', 'agreement_id' => $rec_ag_id], $current_url);
                                $rec_ag_date_signed = (string) ($rec_ag['date_signed'] ?? '');
                                $rec_ag_finished = $is_agreement_stage_finished($rec_ag_stage);
                                $rec_ag_tx_label = $tx_label_map[$rec_ag_tx] ?? ($rec_ag_tx !== '' ? $rec_ag_tx : '-');
                            ?>
                                <li class="eocrm-dash-list-item">
                                    <a class="eocrm-dash-list-item__main" href="<?php echo esc_url($rec_ag_url); ?>">
                                        <span class="eocrm-dash-list-item__title">
                                            <?php echo esc_html($rec_ag_number !== '' ? $rec_ag_number : ('Umowa #' . $rec_ag_id)); ?>
                                        </span>
                                        <span class="eocrm-dash-list-item__sub">
                                            <?php echo esc_html(wp_strip_all_tags($rec_ag_tx_label)); ?>
                                            <?php if ($rec_ag_address !== '') : ?>
                                                &middot; <?php echo esc_html($rec_ag_address); ?>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                    <span class="eocrm-dash-pill <?php echo $rec_ag_finished ? 'is-done' : 'is-progress'; ?>">
                                        <?php echo esc_html($rec_ag_stage !== '' ? $rec_ag_stage : ($rec_ag_finished ? 'Zakonczona' : 'W trakcie')); ?>
                                    </span>
                                    <?php if ($rec_ag_date_signed !== '' && $rec_ag_date_signed !== '0000-00-00') : ?>
                                        <time class="eocrm-dash-list-item__date" datetime="<?php echo esc_attr($rec_ag_date_signed); ?>">
                                            <?php echo esc_html(date_i18n('d.m.Y', strtotime($rec_ag_date_signed))); ?>
                                        </time>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <aside class="eocrm-dash-panel eocrm-dash-panel--mix">
                    <header class="eocrm-dash-panel__head">
                        <div>
                            <h3>Rozk&#322;ad transakcji</h3>
                            <p class="eocrm-dash-panel__hint">Aktywne umowy wg typu</p>
                        </div>
                    </header>
                    <?php if ($tx_total_count <= 0) : ?>
                        <p class="eocrm-dash-empty">Brak aktywnych um&#243;w.</p>
                    <?php else : ?>
                        <ul class="eocrm-dash-bars">
                            <?php foreach ($tx_label_map as $tx_key => $tx_label) :
                                $tx_count = (int) ($dashboard_transaction_breakdown[$tx_key] ?? 0);
                                if ($tx_count <= 0) {
                                    continue;
                                }
                                $tx_pct = $tx_total_count > 0 ? round(($tx_count / $tx_total_count) * 100) : 0;
                                $tx_class = 'eocrm-dash-bar--' . strtolower($tx_key);
                            ?>
                                <li class="eocrm-dash-bar <?php echo esc_attr($tx_class); ?>">
                                    <div class="eocrm-dash-bar__head">
                                        <span class="eocrm-dash-bar__label"><?php echo wp_kses_post($tx_label); ?></span>
                                        <span class="eocrm-dash-bar__value"><?php echo esc_html((string) $tx_count); ?> <span>(<?php echo esc_html((string) $tx_pct); ?>%)</span></span>
                                    </div>
                                    <div class="eocrm-dash-bar__track">
                                        <span class="eocrm-dash-bar__fill" style="width: <?php echo esc_attr((string) $tx_pct); ?>%;"></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($exp_total > 0) : ?>
                        <div class="eocrm-dash-export">
                            <h4>Status eksportu ofert</h4>
                            <ul class="eocrm-dash-export-grid">
                                <li>
                                    <span class="eocrm-dash-export-dot eocrm-dash-export-dot--www"></span>
                                    <span class="eocrm-dash-export-label">WWW + portale</span>
                                    <strong><?php echo esc_html((string) $dashboard_export_breakdown['both']); ?></strong>
                                </li>
                                <li>
                                    <span class="eocrm-dash-export-dot eocrm-dash-export-dot--www-only"></span>
                                    <span class="eocrm-dash-export-label">Tylko WWW</span>
                                    <strong><?php echo esc_html((string) $dashboard_export_breakdown['www_only']); ?></strong>
                                </li>
                                <li>
                                    <span class="eocrm-dash-export-dot eocrm-dash-export-dot--portals"></span>
                                    <span class="eocrm-dash-export-label">Tylko portale</span>
                                    <strong><?php echo esc_html((string) $dashboard_export_breakdown['portals_only']); ?></strong>
                                </li>
                                <li>
                                    <span class="eocrm-dash-export-dot eocrm-dash-export-dot--none"></span>
                                    <span class="eocrm-dash-export-label">W przygotowaniu</span>
                                    <strong><?php echo esc_html((string) $dashboard_export_breakdown['none']); ?></strong>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </aside>

                <article class="eocrm-dash-panel eocrm-dash-panel--properties">
                    <header class="eocrm-dash-panel__head">
                        <div>
                            <h3>Najnowsze nieruchomo&#347;ci</h3>
                            <p class="eocrm-dash-panel__hint"><?php echo esc_html($dashboard_role === 'agent' ? 'Twoje ostatnio dodane oferty' : ($dashboard_role === 'manager' ? 'Ostatnie oferty biura' : 'Ostatnio dodane oferty w firmie')); ?></p>
                        </div>
                        <a class="eocrm-dash-panel__more" href="<?php echo esc_url(add_query_arg(['crm' => 'properties'], $current_url)); ?>">Zobacz wszystkie &rarr;</a>
                    </header>
                    <?php if (empty($dashboard_recent_properties)) : ?>
                        <p class="eocrm-dash-empty">Brak nieruchomosci do wyswietlenia.</p>
                    <?php else : ?>
                        <ul class="eocrm-dash-list">
                            <?php foreach (array_slice($dashboard_recent_properties, 0, 5) as $rec_pr) :
                                $rec_pr_id = (int) ($rec_pr['id'] ?? 0);
                                $rec_pr_number = (string) ($rec_pr['offer_number'] ?? '');
                                $rec_pr_address = $format_address([trim(((string) ($rec_pr['street'] ?? '')) . ' ' . ((string) ($rec_pr['building_no'] ?? ''))), (string) ($rec_pr['city'] ?? '')]);
                                $rec_pr_tx = strtoupper((string) ($rec_pr['transaction_type'] ?? ''));
                                $rec_pr_url = add_query_arg(['crm' => 'properties', 'property_id' => $rec_pr_id], $current_url);
                                $rec_pr_price = $format_property_price($rec_pr['price'] ?? null, $rec_pr['price_currency'] ?? 'PLN');
                                $rec_pr_export_www = ! empty($rec_pr['export_www']);
                                $rec_pr_status_label = '';
                                if (! empty($rec_pr['is_sold'])) {
                                    $rec_pr_status_label = 'Sprzedane';
                                } elseif (! empty($rec_pr['is_rented'])) {
                                    $rec_pr_status_label = 'Wynajete';
                                }
                            ?>
                                <li class="eocrm-dash-list-item">
                                    <a class="eocrm-dash-list-item__main" href="<?php echo esc_url($rec_pr_url); ?>">
                                        <span class="eocrm-dash-list-item__title"><?php echo esc_html($rec_pr_number !== '' ? $rec_pr_number : ('Oferta #' . $rec_pr_id)); ?></span>
                                        <span class="eocrm-dash-list-item__sub">
                                            <?php echo esc_html($rec_pr_tx !== '' ? ($tx_label_map[$rec_pr_tx] ?? $rec_pr_tx) : ''); ?>
                                            <?php if ($rec_pr_address !== '') : ?>
                                                &middot; <?php echo esc_html($rec_pr_address); ?>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                    <span class="eocrm-dash-list-item__price"><?php echo esc_html($rec_pr_price); ?></span>
                                    <span class="eocrm-dash-pill <?php echo $rec_pr_status_label !== '' ? 'is-done' : ($rec_pr_export_www ? 'is-published' : 'is-progress'); ?>">
                                        <?php echo esc_html($rec_pr_status_label !== '' ? $rec_pr_status_label : ($rec_pr_export_www ? 'Opublikowane' : 'W przygotowaniu')); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <aside class="eocrm-dash-panel eocrm-dash-panel--agents">
                    <header class="eocrm-dash-panel__head">
                        <div>
                            <h3><?php echo esc_html($dashboard_role === 'admin' ? 'Najaktywniejsi agenci' : 'Najaktywniejsi w biurze'); ?></h3>
                            <p class="eocrm-dash-panel__hint">Ranking wg aktywnych um&#243;w</p>
                        </div>
                    </header>
                    <?php if (empty($top_agents)) : ?>
                        <p class="eocrm-dash-empty">Brak danych do rankingu.</p>
                    <?php else :
                        $rank_max = 0;
                        foreach ($top_agents as $rank_row) {
                            $rank_max = max($rank_max, (int) ($rank_row['total'] ?? 0));
                        }
                    ?>
                        <ol class="eocrm-dash-rank">
                            <?php foreach ($top_agents as $rank_index => $rank_row) :
                                $rank_name = (string) ($rank_row['display_name'] ?? 'Brak nazwy');
                                $rank_total = (int) ($rank_row['total'] ?? 0);
                                $rank_pct = $rank_max > 0 ? round(($rank_total / $rank_max) * 100) : 0;
                            ?>
                                <li class="eocrm-dash-rank-item">
                                    <span class="eocrm-dash-rank-position"><?php echo esc_html((string) ($rank_index + 1)); ?></span>
                                    <div class="eocrm-dash-rank-body">
                                        <div class="eocrm-dash-rank-head">
                                            <span class="eocrm-dash-rank-name"><?php echo esc_html($rank_name); ?></span>
                                            <span class="eocrm-dash-rank-total"><?php echo esc_html((string) $rank_total); ?> umow</span>
                                        </div>
                                        <div class="eocrm-dash-rank-track">
                                            <span class="eocrm-dash-rank-fill" style="width: <?php echo esc_attr((string) $rank_pct); ?>%;"></span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </aside>
            </div>
        </section>

    <?php elseif ($section === 'properties') : ?>
        <?php if ($crm_notice === 'property_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nieruchomosc zostala dodana.'); ?></div>
        <?php elseif ($crm_notice === 'property_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nieruchomosc zostala zaktualizowana.'); ?></div>
        <?php elseif ($crm_notice === 'property_deleted') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nieruchomosc zostala usunieta.'); ?></div>
        <?php elseif ($crm_notice === 'property_error') : ?>
            <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac nieruchomosci.'); ?></div>
        <?php endif; ?>

        <section class="eocrm-actions-row">
            <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'mode' => 'new-property'], $current_url)); ?>">Dodaj nieruchomo&#347;&#263;</a>
            <?php if ($properties_mode !== '') : ?>
                <?php
                $close_property_args = ['crm' => 'properties'];
                if (in_array($properties_mode, ['edit-property', 'copy-property'], true) && is_array($property_profile)) {
                    $close_property_args['property_id'] = (int) ($property_profile['id'] ?? 0);
                }
                ?>
                <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg($close_property_args, $current_url)); ?>">Zamknij formularz</a>
            <?php endif; ?>
        </section>

        <?php if ($properties_mode === 'new-property') : ?>
            <section class="eocrm-card">
                <h3>Dodawanie nieruchomo&#347;ci</h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'mode' => 'new-property'], $current_url)); ?>" data-eocrm-property-form data-eocrm-form-draft="<?php echo esc_attr($property_form_draft_new_json); ?>">
                    <?php wp_nonce_field('eocrm_create_property', 'eocrm_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="create_property">

                    <h4>Powi&#261;zanie z umowa</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Umowa</label>
                            <select name="agreement_id" data-eocrm-agreement-select required>
                                <option value="">Wybierz umowe</option>
                                <?php foreach ($property_agreement_options as $ag_row) : ?>
                                    <?php $ag_id = (int) ($ag_row['id'] ?? 0); ?>
                                    <option value="<?php echo esc_attr((string) $ag_id); ?>" data-transaction="<?php echo esc_attr((string) ($ag_row['transaction_type'] ?? '')); ?>" <?php selected($prefill_agreement_id, $ag_id); ?>>
                                        <?php echo esc_html((string) ($ag_row['agreement_number'] ?? '') . ' [' . (string) ($ag_row['transaction_type'] ?? '') . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Typ transakcji (z umowy)</label>
                            <input type="text" data-eocrm-transaction-display value="" readonly>
                        </p>
                    </div>

                    <h4>Dane podstawowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Numer oferty</label>
                            <input type="text" name="offer_number" value="<?php echo esc_attr((string) $default_offer_number); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Rodzaj nieruchomo&#347;ci</label>
                            <select name="property_type" data-eocrm-property-type required>
                                <option value="">Wybierz</option>
                                <option value="MIESZKANIE">MIESZKANIE</option>
                                <option value="DOM">DOM</option>
                                <option value="DZIALKA">DZIALKA</option>
                                <option value="LOKAL_HU">LOKAL H/U</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-house-type-wrap is-hidden">
                            <label>Typ domu</label>
                            <select name="house_type">
                                <option value="">Wybierz</option>
                                <option value="WOLNOSTOJACY">WOLNOSTOJACY</option>
                                <option value="BLIZNIAK">BLIZNIAK</option>
                                <option value="SZEREGOWIEC">SZEREGOWIEC</option>
                                <option value="WIELORODZINNY">WIELORODZINNY</option>
                            </select>
                        </p>
                    </div>

                    <h4>Dane adresowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="street" data-eocrm-addr-street></p>
                        <p class="eocrm-form-field"><label>Numer</label><input type="text" name="building_no" data-eocrm-addr-building></p>
                        <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="apartment_no"></p>
                        <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="postal_code" data-eocrm-addr-postal></p>
                        <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="city" data-eocrm-addr-city></p>
                        <p class="eocrm-form-field"><label>Dzielnica</label><input type="text" name="district"></p>
                        <p class="eocrm-form-field"><label>Powiat</label><input type="text" name="county"></p>
                        <p class="eocrm-form-field eocrm-plot-house is-hidden"><label>Gmina</label><input type="text" name="gmina"></p>
                        <p class="eocrm-form-field eocrm-plot-house is-hidden"><label>Obr&#281;b</label><input type="text" name="precinct"></p>
                        <p class="eocrm-form-field eocrm-plot-house is-hidden"><label>Numer dzia&#322;ki</label><input type="text" name="plot_number"></p>
                    </div>

                    <h4>Stan prawny</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Numer ksi&#281;gi wieczystej</label>
                            <input type="text" name="land_registry_no" data-eocrm-kw-input>
                        </p>
                        <p class="eocrm-form-field-checkbox">
                            <label><input type="checkbox" name="no_land_registry" value="1" data-eocrm-no-kw>Brak KW</label>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Stan prawny</label>
                            <select name="legal_status">
                                <option value="">Wybierz</option>
                                <option value="Wlasnosc">W&#322;asno&#347;&#263;</option>
                                <option value="Wspolwlasnosc">Wsp&#243;&#322;w&#322;asno&#347;&#263;</option>
                                <option value="Spoldzielcze Wlasnosciowe Prawo do Lokalu">Sp&#243;&#322;dzielcze W&#322;asno&#347;ciowe Prawo do Lokalu</option>
                                <option value="Dzierzawa">Dzier&#380;awa</option>
                                <option value="Inne">Inne</option>
                            </select>
                        </p>
                    </div>

                    <h4>Mapa</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Adres / pin mapy</label>
                            <input type="text" name="map_pin_address" data-eocrm-map-address>
                        </p>
                        <p class="eocrm-form-field"><label>Lat</label><input type="text" name="latitude" data-eocrm-latitude placeholder="52.229676"></p>
                        <p class="eocrm-form-field"><label>Lng</label><input type="text" name="longitude" data-eocrm-longitude placeholder="21.012229"></p>
                    </div>
                    <p class="eocrm-inline-checkboxes">
                        <button class="eocrm-btn" type="button" data-eocrm-open-map>Otw&#243;rz Google Maps</button>
                        <button class="eocrm-btn" type="button" data-eocrm-map-geocode>Ustaw pinezk&#281; z adresu</button>
                    </p>
                    <div class="eocrm-map-box">
                        <div class="eocrm-map-canvas" data-eocrm-map-canvas></div>
                        <p class="eocrm-muted">Kliknij na mapie, aby ustawi&#263; pinezk&#281; i wsp&#243;&#322;rz&#281;dne.</p>
                    </div>

                    <h4>Dane nieruchomo&#347;ci</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Cena <span data-eocrm-price-hint class="eocrm-muted"></span></label>
                            <input type="text" name="price" data-eocrm-price required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Waluta</label>
                            <select name="price_currency" required>
                                <?php foreach ($property_price_currency_options as $price_currency_option) : ?>
                                    <option value="<?php echo esc_attr($price_currency_option); ?>" <?php selected($price_currency_option, 'PLN'); ?>><?php echo esc_html($price_currency_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Czynsz administracyjny</label><input type="text" name="admin_rent"></p>
                        <p class="eocrm-form-field"><label data-eocrm-area-label>Metra&#380; (m2)</label><input type="text" name="area" data-eocrm-area required></p>
                        <p class="eocrm-form-field"><label>Cena za m2</label><input type="text" data-eocrm-price-per-m2 readonly></p>
                        <p class="eocrm-form-field eocrm-dom-only is-hidden"><label>Wielkosc dzialki (m2)</label><input type="text" name="plot_area" data-eocrm-plot-area></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Rok budowy</label><input type="number" name="year_built" min="1800" max="2100"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Pi&#281;tro</label><input type="number" name="floor_no"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba pi&#281;ter</label><input type="number" name="floors_total"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba pokoi</label><input type="number" name="rooms"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba sypialni</label><input type="number" name="bedrooms"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba &#322;azienek</label><input type="number" name="bathrooms"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba toalet</label><input type="number" name="toilets"></p>
                    </div>

                    <div class="eocrm-form-grid eocrm-plot-wrap is-hidden">
                        <p class="eocrm-form-field"><label>Kszta&#322;t dzia&#322;ki</label>
                            <select name="plot_shape" data-eocrm-plot-shape>
                                <option value="">Brak</option>
                                <option value="kwadrat">Kwadrat</option>
                                <option value="prostokat">Prostok&#261;t</option>
                                <option value="trojkat">Tr&#243;jk&#261;t</option>
                                <option value="nieregularna">Nieregularna</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-plot-shape-side-a is-hidden"><label>Bok A (m)</label><input type="text" name="plot_length"></p>
                        <p class="eocrm-form-field eocrm-plot-shape-side-b is-hidden"><label>Bok B (m)</label><input type="text" name="plot_width"></p>
                        <p class="eocrm-form-field eocrm-plot-shape-side-c is-hidden"><label>Bok C (m)</label><input type="text" name="plot_side_c"></p>
                        <input type="hidden" name="plot_dimensions_text" value="">
                    </div>

                    <h4>Opis nieruchomo&#347;ci</h4>
                    <?php if (function_exists('wp_editor')) : ?>
                        <?php wp_editor('', 'eocrm_property_description', ['textarea_name' => 'description', 'textarea_rows' => 8, 'media_buttons' => false, 'teeny' => true]); ?>
                    <?php else : ?>
                        <textarea class="large-text" rows="8" name="description"></textarea>
                    <?php endif; ?>

                    <h4>Szczeg&#243;&#322;y nieruchomo&#347;ci</h4>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <p class="eocrm-form-field"><label>Stan wyko&#324;czenia</label>
                            <select name="building_finish">
                                <option value="">Wybierz</option>
                                <?php foreach ($building_finish_options as $building_finish_value => $building_finish_label) : ?>
                                    <option value="<?php echo esc_attr($building_finish_value); ?>"><?php echo wp_kses_post($building_finish_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kuchnia</label>
                            <select name="kitchen_type">
                                <option value="">Wybierz</option>
                                <?php foreach ($kitchen_type_options as $kitchen_type_value => $kitchen_type_label) : ?>
                                    <option value="<?php echo esc_attr($kitchen_type_value); ?>"><?php echo wp_kses_post($kitchen_type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Poddasze</label>
                            <select name="attic">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-not-house">
                            <label>Wielopoziomowe</label>
                            <select name="multi_level">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field-checkbox">
                            <label><input type="checkbox" name="has_parking" value="1" data-eocrm-has-parking>Miejsce parkingowe</label>
                        </p>
                    </div>
                    <?php $property_additional_render_form_section('details', $property_additional_fields_by_section, $property_custom_fields_new_form); ?>

                    <div class="eocrm-checkbox-list eocrm-parking-types is-hidden">
                        <div class="eocrm-checkbox-item eocrm-checkbox-item-stack">
                            <label><input type="checkbox" name="parking_types[]" value="najemne" data-eocrm-parking-type-toggle="najemne"> Naziemny</label>
                            <input type="number" name="parking_rental_count" min="0" placeholder="Ilo&#347;&#263;" data-eocrm-parking-count="najemne" class="is-hidden">
                        </div>
                        <div class="eocrm-checkbox-item eocrm-checkbox-item-stack">
                            <label><input type="checkbox" name="parking_types[]" value="podziemne" data-eocrm-parking-type-toggle="podziemne"> Podziemny</label>
                            <input type="number" name="parking_underground_count" min="0" placeholder="Ilo&#347;&#263;" data-eocrm-parking-count="podziemne" class="is-hidden">
                        </div>
                        <div class="eocrm-checkbox-item eocrm-checkbox-item-stack">
                            <label><input type="checkbox" name="parking_types[]" value="garaz" data-eocrm-parking-type-toggle="garaz"> Gara&#380;</label>
                            <input type="number" name="parking_garage_count" min="0" placeholder="Ilo&#347;&#263;" data-eocrm-parking-count="garaz" class="is-hidden">
                        </div>
                    </div>

                    <h4>Budynek i uk&#322;ad</h4>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <div class="eocrm-form-field">
                            <label>Ekspozycja</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="exposure[]" value="polnoc">P&oacute;&#322;noc</label>
                                <label><input type="checkbox" name="exposure[]" value="poludnie">Po&#322;udnie</label>
                                <label><input type="checkbox" name="exposure[]" value="wschod">Wsch&oacute;d</label>
                                <label><input type="checkbox" name="exposure[]" value="zachod">Zach&#243;d</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Widok</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="view[]" value="miasto">Miasto</label>
                                <label><input type="checkbox" name="view[]" value="zielec">Ziele&#324;</label>
                                <label><input type="checkbox" name="view[]" value="park">Park</label>
                                <label><input type="checkbox" name="view[]" value="podworko">Podw&#243;rko</label>
                                <label><input type="checkbox" name="view[]" value="ulica">Ulica</label>
                                <label><input type="checkbox" name="view[]" value="panorama">Panorama</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Rozk&#322;ad</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="layout[]" value="oddzielne_pokoje">Rozk&#322;adowe</label>
                                <label><input type="checkbox" name="layout[]" value="salon_z_aneksem">Salon z aneksem</label>
                                <label><input type="checkbox" name="layout[]" value="dwustronne">Dwustronne</label>
                                <label><input type="checkbox" name="layout[]" value="narozne">Naro&#380;ne</label>
                            </div>
                        </div>
                    </div>

                    <h4>Media</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ogrzewanie</label>
                            <select name="heating">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="miejskie">Miejskie</option>
                                <option value="gazowe">Gazowe</option>
                                <option value="elektryczne">Elektryczne</option>
                                <option value="podlogowe">Pod&#322;ogowe</option>
                                <option value="inne">Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Woda</label>
                            <select name="water">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="miejska">Miejska</option>
                                <option value="studnia">Studnia</option>
                                <option value="inne">Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kanalizacja</label>
                            <select name="sewage">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="miejska">Miejska</option>
                                <option value="szambo">Szambo</option>
                                <option value="oczyszczalnia">Oczyszczalnia</option>
                                <option value="inne">Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="gas" value="1">Gaz</label></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="electricity" value="1">Pr&#261;d</label></p>
                    </div>
                    <?php $property_additional_render_form_section('media', $property_additional_fields_by_section, $property_custom_fields_new_form); ?>
                    <h4>Udogodnienia</h4>
                    <div class="eocrm-checkbox-list eocrm-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="winda">Winda</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="klimatyzacja">Klimatyzacja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="monitoring">Monitoring/Ochrona</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="recepcja">Recepcja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="teren_zamkniety">Teren zamkni&#281;ty</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="domofon">Domofon</label>
                    </div>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <p class="eocrm-form-field">
                            <label>Umeblowanie</label>
                            <select name="furnished_state">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="tak">Tak</option>
                                <option value="nie">Nie</option>
                                <option value="czesciowe">Cz&#281;&#347;ciowe</option>
                            </select>
                        </p>
                    </div>
                    <?php $property_additional_render_form_section('amenities', $property_additional_fields_by_section, $property_custom_fields_new_form); ?>
                    <h4>Wyposa&#380;enie</h4>
                    <div class="eocrm-checkbox-list eocrm-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="pralka">Pralka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="zmywarka">Zmywarka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="lodowka">Lod&#243;wka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="kuchenka">Kuchenka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="piekarnik">Piekarnik</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="telewizor">Telewizor</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="mikrofala">Mikrofala</label>
                    </div>
                    <?php $property_additional_render_form_section('equipment', $property_additional_fields_by_section, $property_custom_fields_new_form); ?>

                    <h4 class="eocrm-not-plot">Powierzchnie dodatkowe</h4>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="balcony_has" value="1" data-eocrm-extra-toggle="balcony">Balkon</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-balcony is-hidden"><label>Ilo&#347;&#263; balkon&#243;w</label><input type="number" name="balcony_count" min="0"></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-balcony is-hidden"><label>Pow. balkonu (m2)</label><input type="text" name="balcony_area"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="terrace_has" value="1" data-eocrm-extra-toggle="terrace">Taras</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-terrace is-hidden"><label>Ilo&#347;&#263; taras&#243;w</label><input type="number" name="terrace_count" min="0"></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-terrace is-hidden"><label>Pow. tarasu (m2)</label><input type="text" name="terrace_area"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="basement_has" value="1" data-eocrm-extra-toggle="basement">Piwnica</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-basement is-hidden"><label>Pow. piwnicy (m2)</label><input type="text" name="basement_area"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="storage_has" value="1" data-eocrm-extra-toggle="storage">Kom&#243;rka lokatorska</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-storage is-hidden"><label>Pow. kom&#243;rki (m2)</label><input type="text" name="storage_area"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="garden_has" value="1" data-eocrm-extra-toggle="garden">Ogr&#243;dek</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-garden is-hidden"><label>Pow. ogr&#243;dka (m2)</label><input type="text" name="garden_area"></p>
                    </div>

                    <h4>Galeria i media</h4>
                    <input type="hidden" name="gallery_attachment_ids" id="eocrm-gallery-ids" value="<?php echo esc_attr(implode(',', $property_gallery_ids_new_form)); ?>">
                    <p class="eocrm-inline-checkboxes">
                        <button class="eocrm-btn" type="button" data-eocrm-media-gallery data-target-input="eocrm-gallery-ids" data-target-preview="eocrm-gallery-preview">Wybierz zdj&#281;cia galerii</button>
                    </p>
                    <div id="eocrm-gallery-preview" class="eocrm-media-preview">
                        <?php foreach ($property_gallery_preview_new_form as $new_photo_row) : ?>
                            <span class="eocrm-gallery-item" data-eocrm-gallery-id="<?php echo (int) ($new_photo_row['attachment_id'] ?? 0); ?>" draggable="true">
                                <img class="eocrm-thumb" src="<?php echo esc_url((string) ($new_photo_row['thumb_url'] ?? '')); ?>" alt="Zdjecie galerii">
                                <button type="button" class="eocrm-gallery-remove" data-eocrm-gallery-remove aria-label="Usun zdjecie">x</button>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Link do filmu</label><input type="url" name="video_link"></p>
                        <p class="eocrm-form-field"><label>Link do wirtualnego spaceru</label><input type="url" name="virtual_tour_link"></p>
                        <p class="eocrm-form-field eocrm-form-field-full">
                            <label>Rzuty kondygnacji</label>
                            <input type="hidden" name="floor_plan_items_json" id="eocrm-floor-plan-items-json" value="<?php echo esc_attr((string) $property_floor_plan_items_new_form_json); ?>">
                            <div class="eocrm-floor-plan-builder" data-eocrm-floor-plan-builder data-target-input="eocrm-floor-plan-items-json">
                                <div class="eocrm-floor-plan-list" data-eocrm-floor-plan-list></div>
                                <p class="eocrm-inline-checkboxes">
                                    <button class="eocrm-btn" type="button" data-eocrm-floor-plan-add>Dodaj rzut poziomu</button>
                                </p>
                                <p class="eocrm-muted">Dodaj dowolna liczbe rzutow i nazwij poziomy, np. Poziom 1, Poziom -1, Piwnica.</p>
                            </div>
                        </p>
                    </div>

                    <h4>Znaczniki</h4>
                    <div class="eocrm-checkbox-list">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="nowa_oferta">Nowa oferta</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="wylacznosc">Wy&#322;&#261;czno&#347;&#263;</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="sprzedane" data-eocrm-tag-sold>Sprzedane</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="wynajete" data-eocrm-tag-rented>Wynaj&#281;te</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="nowa_cena">Nowa cena</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="bez_prowizji">Bez prowizji</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="oferta_mls">Oferta MLS</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="premium">Premium</label>
                    </div>


                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="export_www" value="1">Eksport na WWW</label></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="export_portals" value="1">Eksport na Portale</label></p>
                    </div>

                    <p><button class="eocrm-btn eocrm-btn-primary" type="submit">Dodaj nieruchomo&#347;&#263;</button></p>
                </form>
            </section>
        <?php endif; ?>

        <?php if (in_array($properties_mode, ['edit-property', 'copy-property'], true) && is_array($property_profile)) : ?>
            <?php
            $is_copy_property_mode = $properties_mode === 'copy-property' && $is_admin_user;
            $edit_property_media = isset($property_profile['media']) && is_array($property_profile['media']) ? $property_profile['media'] : [];
            $edit_property_tags = isset($property_profile['tags']) && is_array($property_profile['tags']) ? $property_profile['tags'] : [];
            $edit_property_draft_source = $is_copy_property_mode ? $property_form_draft_new : $property_form_draft_edit;
            $edit_property_gallery_ids = [];
            $edit_property_gallery_preview_rows = [];
            if (isset($property_profile_media['photos']) && is_array($property_profile_media['photos'])) {
                foreach ($property_profile_media['photos'] as $edit_property_photo_row) {
                    $edit_property_photo_id = isset($edit_property_photo_row['attachment_id']) ? absint((string) $edit_property_photo_row['attachment_id']) : 0;
                    if ($edit_property_photo_id > 0) {
                        $edit_property_gallery_ids[] = $edit_property_photo_id;
                    }

                    $edit_property_photo_url = $edit_property_photo_id > 0 ? (string) wp_get_attachment_image_url($edit_property_photo_id, 'thumbnail') : '';
                    if ($edit_property_photo_url === '') {
                        $edit_property_photo_url = (string) ($edit_property_photo_row['media_url'] ?? '');
                    }
                    if ($edit_property_photo_id > 0 && $edit_property_photo_url !== '') {
                        $edit_property_gallery_preview_rows[] = [
                            'attachment_id' => $edit_property_photo_id,
                            'thumb_url' => $edit_property_photo_url,
                        ];
                    }
                }
            }
            if (is_array($edit_property_draft_source) && array_key_exists('gallery_attachment_ids', $edit_property_draft_source)) {
                $edit_property_gallery_ids = $property_parse_draft_attachment_ids($edit_property_draft_source['gallery_attachment_ids']);
                $edit_property_gallery_preview_rows = $property_build_gallery_preview_rows($edit_property_gallery_ids);
            }
            $edit_property_floor_plan_items = [];
            $edit_property_media_floor_plans = isset($property_profile_media['floor_plans']) && is_array($property_profile_media['floor_plans']) ? $property_profile_media['floor_plans'] : [];
            $edit_property_floor_plans_meta = isset($edit_property_media['floor_plans']) && is_array($edit_property_media['floor_plans']) ? $edit_property_media['floor_plans'] : [];
            $edit_property_floor_plan_labels_by_attachment = [];
            foreach ($edit_property_floor_plans_meta as $floor_plan_meta_row) {
                if (! is_array($floor_plan_meta_row)) {
                    continue;
                }
                $meta_attachment_id = isset($floor_plan_meta_row['attachment_id']) ? absint((string) $floor_plan_meta_row['attachment_id']) : 0;
                if ($meta_attachment_id <= 0) {
                    continue;
                }
                $meta_label = isset($floor_plan_meta_row['label']) ? sanitize_text_field((string) $floor_plan_meta_row['label']) : '';
                if ($meta_label === '') {
                    continue;
                }
                $edit_property_floor_plan_labels_by_attachment[$meta_attachment_id] = $meta_label;
            }

            foreach ($edit_property_media_floor_plans as $floor_plan_index => $floor_plan_row) {
                if (! is_array($floor_plan_row)) {
                    continue;
                }
                $floor_plan_attachment_id = isset($floor_plan_row['attachment_id']) ? absint((string) $floor_plan_row['attachment_id']) : 0;
                if ($floor_plan_attachment_id <= 0) {
                    continue;
                }

                $floor_plan_label = isset($floor_plan_row['label']) ? sanitize_text_field((string) $floor_plan_row['label']) : '';
                if ($floor_plan_label === '' && isset($edit_property_floor_plan_labels_by_attachment[$floor_plan_attachment_id])) {
                    $floor_plan_label = (string) $edit_property_floor_plan_labels_by_attachment[$floor_plan_attachment_id];
                }
                if ($floor_plan_label === '') {
                    $floor_plan_label = 'Poziom ' . (string) ($floor_plan_index + 1);
                }

                $floor_plan_preview_url = (string) wp_get_attachment_image_url($floor_plan_attachment_id, 'thumbnail');
                if ($floor_plan_preview_url === '') {
                    $floor_plan_preview_url = (string) wp_get_attachment_url($floor_plan_attachment_id);
                }

                $edit_property_floor_plan_items[] = [
                    'attachment_id' => $floor_plan_attachment_id,
                    'label' => $floor_plan_label,
                    'position' => count($edit_property_floor_plan_items),
                    'preview_url' => $floor_plan_preview_url,
                ];
            }

            if (empty($edit_property_floor_plan_items)) {
                $legacy_floor2d_id = isset($property_profile_media['floor_2d']['attachment_id']) ? absint((string) $property_profile_media['floor_2d']['attachment_id']) : 0;
                $legacy_floor3d_id = isset($property_profile_media['floor_3d']['attachment_id']) ? absint((string) $property_profile_media['floor_3d']['attachment_id']) : 0;
                if ($legacy_floor2d_id > 0) {
                    $legacy_floor2d_preview_url = (string) wp_get_attachment_image_url($legacy_floor2d_id, 'thumbnail');
                    if ($legacy_floor2d_preview_url === '') {
                        $legacy_floor2d_preview_url = (string) wp_get_attachment_url($legacy_floor2d_id);
                    }
                    $edit_property_floor_plan_items[] = [
                        'attachment_id' => $legacy_floor2d_id,
                        'label' => 'Poziom 1',
                        'position' => 0,
                        'preview_url' => $legacy_floor2d_preview_url,
                    ];
                }
                if ($legacy_floor3d_id > 0) {
                    $legacy_floor3d_preview_url = (string) wp_get_attachment_image_url($legacy_floor3d_id, 'thumbnail');
                    if ($legacy_floor3d_preview_url === '') {
                        $legacy_floor3d_preview_url = (string) wp_get_attachment_url($legacy_floor3d_id);
                    }
                    $edit_property_floor_plan_items[] = [
                        'attachment_id' => $legacy_floor3d_id,
                        'label' => 'Poziom 2',
                        'position' => count($edit_property_floor_plan_items),
                        'preview_url' => $legacy_floor3d_preview_url,
                    ];
                }
            }
            if (is_array($edit_property_draft_source) && array_key_exists('floor_plan_items_json', $edit_property_draft_source)) {
                $edit_property_floor_plan_items = $property_parse_draft_floor_plan_items($edit_property_draft_source['floor_plan_items_json']);
            }

            $edit_property_floor_plan_items_json = $property_floor_plan_items_to_json($edit_property_floor_plan_items);
            $edit_property_owner_id = (int) ($property_profile['owner_user_id'] ?? 0);
            $edit_property_owner_label = $resolve_owner_label($edit_property_owner_id);
            $edit_property_type = (string) ($property_profile['property_type'] ?? '');
            $edit_property_house_wrap_hidden = $edit_property_type === 'DOM' ? '' : ' is-hidden';
            $edit_property_plot_wrap_hidden = in_array($edit_property_type, ['DOM', 'DZIALKA'], true) ? '' : ' is-hidden';
            $edit_property_plot_house_hidden = in_array($edit_property_type, ['DOM', 'DZIALKA'], true) ? '' : ' is-hidden';
            $edit_property_dom_only_hidden = $edit_property_type === 'DOM' ? '' : ' is-hidden';

            $edit_property_kitchen_type = (string) ($property_profile['kitchen_type'] ?? '');
            if ($edit_property_kitchen_type === 'Z salonem') {
                $edit_property_kitchen_type = 'Polotwarta';
            }
            $edit_property_building_finish = (string) ($property_profile['building_finish'] ?? '');

            $edit_property_media_heating = (string) ($edit_property_media['heating'] ?? '');
            $edit_property_media_water = (string) ($edit_property_media['water'] ?? '');
            $edit_property_media_sewage = (string) ($edit_property_media['sewage'] ?? '');
            $edit_property_media_furnished_state = (string) ($edit_property_media['furnished_state'] ?? '');
            $edit_property_media_attic = array_key_exists('attic', $edit_property_media) ? (! empty($edit_property_media['attic']) ? '1' : '0') : '';
            $edit_property_media_multi_level = array_key_exists('multi_level', $edit_property_media) ? (! empty($edit_property_media['multi_level']) ? '1' : '0') : '';
            $edit_property_media_has_gas = ! empty($edit_property_media['gas']);
            $edit_property_media_has_electricity = ! empty($edit_property_media['electricity']);
            $edit_property_price_hint = ((string) ($property_profile['transaction_type'] ?? '') === 'WYNAJEM') ? '(miesi&#281;cznie)' : '';

            $edit_property_exposure = isset($property_profile['exposure']) && is_array($property_profile['exposure']) ? $property_profile['exposure'] : [];
            $edit_property_view = isset($property_profile['view']) && is_array($property_profile['view']) ? $property_profile['view'] : [];
            $edit_property_layout = isset($property_profile['layout']) && is_array($property_profile['layout']) ? $property_profile['layout'] : [];
            $edit_property_amenities = isset($property_profile['amenities']) && is_array($property_profile['amenities']) ? $property_profile['amenities'] : [];
            $edit_property_equipment = isset($property_profile['equipment']) && is_array($property_profile['equipment']) ? $property_profile['equipment'] : [];
            $edit_property_parking = isset($property_profile['parking']) && is_array($property_profile['parking']) ? $property_profile['parking'] : [];
            $edit_property_parking_has = ! empty($edit_property_parking['has_parking']);
            $edit_property_parking_types = isset($edit_property_parking['types']) && is_array($edit_property_parking['types']) ? $edit_property_parking['types'] : [];
            $edit_property_parking_counts = isset($edit_property_parking['counts']) && is_array($edit_property_parking['counts']) ? $edit_property_parking['counts'] : [];

            $edit_property_extra_areas = isset($property_profile['extra_areas']) && is_array($property_profile['extra_areas']) ? $property_profile['extra_areas'] : [];
            $edit_property_extra_balcony = isset($edit_property_extra_areas['balcony']) && is_array($edit_property_extra_areas['balcony']) ? $edit_property_extra_areas['balcony'] : [];
            $edit_property_extra_terrace = isset($edit_property_extra_areas['terrace']) && is_array($edit_property_extra_areas['terrace']) ? $edit_property_extra_areas['terrace'] : [];
            $edit_property_extra_basement = isset($edit_property_extra_areas['basement']) && is_array($edit_property_extra_areas['basement']) ? $edit_property_extra_areas['basement'] : [];
            $edit_property_extra_storage = isset($edit_property_extra_areas['storage']) && is_array($edit_property_extra_areas['storage']) ? $edit_property_extra_areas['storage'] : [];
            $edit_property_extra_garden = isset($edit_property_extra_areas['garden']) && is_array($edit_property_extra_areas['garden']) ? $edit_property_extra_areas['garden'] : [];

            $edit_property_plot_shape = strtolower(trim((string) ($property_profile['plot_shape'] ?? '')));
            if ($edit_property_plot_shape === 'regularny') {
                $edit_property_plot_shape = 'prostokat';
            } elseif ($edit_property_plot_shape === 'nieregularny') {
                $edit_property_plot_shape = 'nieregularna';
            }
            $edit_property_plot_dimensions_text = (string) ($property_profile['plot_dimensions_text'] ?? '');
            $edit_property_plot_dimensions_meta = json_decode($edit_property_plot_dimensions_text, true);
            $edit_property_plot_side_c = '';
            if (is_array($edit_property_plot_dimensions_meta) && isset($edit_property_plot_dimensions_meta['side_c']) && is_numeric((string) $edit_property_plot_dimensions_meta['side_c'])) {
                $edit_property_plot_side_c = (string) $edit_property_plot_dimensions_meta['side_c'];
            }
            $edit_property_plot_side_a_hidden = in_array($edit_property_plot_shape, ['kwadrat', 'prostokat', 'trojkat'], true) ? '' : ' is-hidden';
            $edit_property_plot_side_b_hidden = in_array($edit_property_plot_shape, ['prostokat', 'trojkat'], true) ? '' : ' is-hidden';
            $edit_property_plot_side_c_hidden = $edit_property_plot_shape === 'trojkat' ? '' : ' is-hidden';
            $edit_property_custom_fields = isset($property_profile['custom_fields']) && is_array($property_profile['custom_fields']) ? $property_profile['custom_fields'] : [];
            if (! empty($edit_property_draft_source) && is_array($edit_property_draft_source)) {
                $edit_property_custom_field_draft = $property_additional_collect_form_values($edit_property_draft_source, $property_additional_fields);
                if (! empty($edit_property_custom_field_draft)) {
                    $edit_property_custom_fields = array_merge($edit_property_custom_fields, $edit_property_custom_field_draft);
                }
            }
            ?>
            <section class="eocrm-card">
                <h3><?php echo $is_copy_property_mode ? 'Kopiowanie nieruchomo&#347;ci' : 'Edycja nieruchomo&#347;ci'; ?></h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'mode' => $is_copy_property_mode ? 'copy-property' : 'edit-property', 'property_id' => (int) ($property_profile['id'] ?? 0)], $current_url)); ?>" data-eocrm-property-form data-eocrm-form-draft="<?php echo esc_attr($is_copy_property_mode ? $property_form_draft_new_json : $property_form_draft_edit_json); ?>">
                    <?php if ($is_copy_property_mode) : ?>
                        <?php wp_nonce_field('eocrm_create_property', 'eocrm_nonce'); ?>
                        <input type="hidden" name="eocrm_action" value="create_property">
                        <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) ((int) ($property_profile['agreement_id'] ?? 0))); ?>">
                        <input type="hidden" name="property_type" value="<?php echo esc_attr($edit_property_type); ?>">
                    <?php else : ?>
                        <?php wp_nonce_field('eocrm_update_property', 'eocrm_property_update_nonce'); ?>
                        <input type="hidden" name="eocrm_action" value="update_property">
                        <input type="hidden" name="property_id" value="<?php echo (int) ($property_profile['id'] ?? 0); ?>">
                    <?php endif; ?>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Numer oferty</label>
                            <?php if ($is_copy_property_mode) : ?>
                                <input type="text" name="offer_number" value="<?php echo esc_attr((string) $default_offer_number); ?>" required>
                            <?php else : ?>
                                <input type="text" value="<?php echo esc_attr((string) ($property_profile['offer_number'] ?? '')); ?>" readonly>
                            <?php endif; ?>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Typ transakcji</label>
                            <input type="text" value="<?php echo esc_attr((string) ($property_profile['transaction_type'] ?? '')); ?>" readonly>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Rodzaj nieruchomo&#347;ci</label>
                            <input type="text" data-eocrm-property-type value="<?php echo esc_attr($edit_property_type); ?>" readonly>
                        </p>
                        <?php if ($is_admin_user && ! $is_copy_property_mode) : ?>
                            <p class="eocrm-form-field">
                                <label>Opiekun</label>
                                <select name="owner_user_id" required>
                                    <option value="">Wybierz opiekuna</option>
                                    <?php if ($edit_property_owner_id > 0 && ! in_array($edit_property_owner_id, $owner_option_ids, true)) : ?>
                                        <option value="<?php echo esc_attr((string) $edit_property_owner_id); ?>" selected><?php echo esc_html($edit_property_owner_label !== '' ? $edit_property_owner_label : ('Uzytkownik #' . (string) $edit_property_owner_id)); ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($owner_user_options as $owner_option_row) : ?>
                                        <?php $owner_option_id = (int) ($owner_option_row['id'] ?? 0); ?>
                                        <?php if ($owner_option_id <= 0) {
                                            continue;
                                        } ?>
                                        <option value="<?php echo esc_attr((string) $owner_option_id); ?>" <?php selected($owner_option_id, $edit_property_owner_id); ?>>
                                            <?php echo esc_html((string) ($owner_option_row['label'] ?? ('Uzytkownik #' . (string) $owner_option_id))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        <?php endif; ?>
                        <p class="eocrm-form-field eocrm-house-type-wrap<?php echo esc_attr($edit_property_house_wrap_hidden); ?>">
                            <label>Typ domu</label>
                            <select name="house_type">
                                <option value="">Wybierz</option>
                                <option value="WOLNOSTOJACY" <?php selected((string) ($property_profile['house_type'] ?? ''), 'WOLNOSTOJACY'); ?>>WOLNOSTOJ&#260;CY</option>
                                <option value="BLIZNIAK" <?php selected((string) ($property_profile['house_type'] ?? ''), 'BLIZNIAK'); ?>>BLI&#377;NIAK</option>
                                <option value="SZEREGOWIEC" <?php selected((string) ($property_profile['house_type'] ?? ''), 'SZEREGOWIEC'); ?>>SZEREGOWIEC</option>
                                <option value="WIELORODZINNY" <?php selected((string) ($property_profile['house_type'] ?? ''), 'WIELORODZINNY'); ?>>WIELORODZINNY</option>
                            </select>
                        </p>
                    </div>

                    <h4>Dane adresowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="street" data-eocrm-addr-street value="<?php echo esc_attr((string) ($property_profile['street'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Numer</label><input type="text" name="building_no" data-eocrm-addr-building value="<?php echo esc_attr((string) ($property_profile['building_no'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="apartment_no" value="<?php echo esc_attr((string) ($property_profile['apartment_no'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="postal_code" data-eocrm-addr-postal value="<?php echo esc_attr((string) ($property_profile['postal_code'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="city" data-eocrm-addr-city value="<?php echo esc_attr((string) ($property_profile['city'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Dzielnica</label><input type="text" name="district" value="<?php echo esc_attr((string) ($property_profile['district'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Powiat</label><input type="text" name="county" value="<?php echo esc_attr((string) ($property_profile['county'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-plot-house<?php echo esc_attr($edit_property_plot_house_hidden); ?>"><label>Gmina</label><input type="text" name="gmina" value="<?php echo esc_attr((string) ($property_profile['gmina'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-plot-house<?php echo esc_attr($edit_property_plot_house_hidden); ?>"><label>Obr&#281;b</label><input type="text" name="precinct" value="<?php echo esc_attr((string) ($property_profile['precinct'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-plot-house<?php echo esc_attr($edit_property_plot_house_hidden); ?>"><label>Numer dzia&#322;ki</label><input type="text" name="plot_number" value="<?php echo esc_attr((string) ($property_profile['plot_number'] ?? '')); ?>"></p>
                    </div>

                    <h4>Stan prawny</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Numer ksi&#281;gi wieczystej</label>
                            <input type="text" name="land_registry_no" data-eocrm-kw-input value="<?php echo esc_attr((string) ($property_profile['land_registry_no'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="no_land_registry" value="1" data-eocrm-no-kw <?php checked(! empty($property_profile['no_land_registry'])); ?>>Brak KW</label></p>
                        <p class="eocrm-form-field">
                            <label>Stan prawny</label>
                            <select name="legal_status">
                                <option value="">Wybierz</option>
                                <option value="Wlasnosc" <?php selected((string) ($property_profile['legal_status'] ?? ''), 'Wlasnosc'); ?>>W&#322;asno&#347;&#263;</option>
                                <option value="Wspolwlasnosc" <?php selected((string) ($property_profile['legal_status'] ?? ''), 'Wspolwlasnosc'); ?>>Wsp&#243;&#322;w&#322;asno&#347;&#263;</option>
                                <option value="Spoldzielcze Wlasnosciowe Prawo do Lokalu" <?php selected((string) ($property_profile['legal_status'] ?? ''), 'Spoldzielcze Wlasnosciowe Prawo do Lokalu'); ?>>Sp&#243;&#322;dzielcze W&#322;asno&#347;ciowe Prawo do Lokalu</option>
                                <option value="Dzierzawa" <?php selected((string) ($property_profile['legal_status'] ?? ''), 'Dzierzawa'); ?>>Dzier&#380;awa</option>
                                <option value="Inne" <?php selected((string) ($property_profile['legal_status'] ?? ''), 'Inne'); ?>>Inne</option>
                            </select>
                        </p>
                    </div>

                    <h4>Mapa</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Adres / pin mapy</label><input type="text" name="map_pin_address" data-eocrm-map-address value="<?php echo esc_attr((string) ($edit_property_media['map_pin_address'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Lat</label><input type="text" name="latitude" data-eocrm-latitude value="<?php echo esc_attr((string) ($property_profile['latitude'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Lng</label><input type="text" name="longitude" data-eocrm-longitude value="<?php echo esc_attr((string) ($property_profile['longitude'] ?? '')); ?>"></p>
                    </div>
                    <p class="eocrm-inline-checkboxes">
                        <button class="eocrm-btn" type="button" data-eocrm-open-map>Otw&#243;rz Google Maps</button>
                        <button class="eocrm-btn" type="button" data-eocrm-map-geocode>Ustaw pinezk&#281; z adresu</button>
                    </p>
                    <div class="eocrm-map-box">
                        <div class="eocrm-map-canvas" data-eocrm-map-canvas></div>
                        <p class="eocrm-muted">Kliknij na mapie, aby ustawi&#263; pinezk&#281; i wsp&#243;&#322;rz&#281;dne.</p>
                    </div>

                    <h4>Dane nieruchomo&#347;ci</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Cena <span data-eocrm-price-hint class="eocrm-muted"><?php echo wp_kses_post($edit_property_price_hint); ?></span></label>
                            <input type="text" name="price" data-eocrm-price value="<?php echo esc_attr((string) ($property_profile['price'] ?? '')); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Waluta</label>
                            <?php $edit_property_price_currency = $normalize_property_currency($property_profile['price_currency'] ?? 'PLN'); ?>
                            <select name="price_currency" required>
                                <?php foreach ($property_price_currency_options as $price_currency_option) : ?>
                                    <option value="<?php echo esc_attr($price_currency_option); ?>" <?php selected($price_currency_option, $edit_property_price_currency); ?>><?php echo esc_html($price_currency_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Czynsz administracyjny</label><input type="text" name="admin_rent" value="<?php echo esc_attr((string) ($property_profile['admin_rent'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label data-eocrm-area-label>Metra&#380; (m2)</label><input type="text" name="area" data-eocrm-area value="<?php echo esc_attr((string) ($property_profile['area'] ?? '')); ?>" required></p>
                        <p class="eocrm-form-field"><label>Cena za m2</label><input type="text" data-eocrm-price-per-m2 value="<?php echo esc_attr((string) ($property_profile['price_per_m2'] ?? '')); ?>" readonly></p>
                        <p class="eocrm-form-field eocrm-dom-only<?php echo esc_attr($edit_property_dom_only_hidden); ?>"><label>Wielkosc dzialki (m2)</label><input type="text" name="plot_area" data-eocrm-plot-area value="<?php echo esc_attr((string) ($property_profile['plot_area'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Rok budowy</label><input type="number" name="year_built" value="<?php echo esc_attr((string) ($property_profile['year_built'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Pi&#281;tro</label><input type="number" name="floor_no" value="<?php echo esc_attr((string) ($property_profile['floor_no'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba pi&#281;ter</label><input type="number" name="floors_total" value="<?php echo esc_attr((string) ($property_profile['floors_total'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba pokoi</label><input type="number" name="rooms" value="<?php echo esc_attr((string) ($property_profile['rooms'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba sypialni</label><input type="number" name="bedrooms" value="<?php echo esc_attr((string) ($property_profile['bedrooms'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba &#322;azienek</label><input type="number" name="bathrooms" value="<?php echo esc_attr((string) ($property_profile['bathrooms'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-not-plot"><label>Liczba toalet</label><input type="number" name="toilets" value="<?php echo esc_attr((string) ($property_profile['toilets'] ?? '')); ?>"></p>
                    </div>

                    <div class="eocrm-form-grid eocrm-plot-wrap<?php echo esc_attr($edit_property_plot_wrap_hidden); ?>">
                        <p class="eocrm-form-field"><label>Kszta&#322;t dzia&#322;ki</label>
                            <select name="plot_shape" data-eocrm-plot-shape>
                                <option value="">Brak</option>
                                <option value="kwadrat" <?php selected($edit_property_plot_shape, 'kwadrat'); ?>>Kwadrat</option>
                                <option value="prostokat" <?php selected($edit_property_plot_shape, 'prostokat'); ?>>Prostok&#261;t</option>
                                <option value="trojkat" <?php selected($edit_property_plot_shape, 'trojkat'); ?>>Tr&#243;jk&#261;t</option>
                                <option value="nieregularna" <?php selected($edit_property_plot_shape, 'nieregularna'); ?>>Nieregularna</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-plot-shape-side-a<?php echo esc_attr($edit_property_plot_side_a_hidden); ?>"><label>Bok A (m)</label><input type="text" name="plot_length" value="<?php echo esc_attr((string) ($property_profile['plot_length'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-plot-shape-side-b<?php echo esc_attr($edit_property_plot_side_b_hidden); ?>"><label>Bok B (m)</label><input type="text" name="plot_width" value="<?php echo esc_attr((string) ($property_profile['plot_width'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-plot-shape-side-c<?php echo esc_attr($edit_property_plot_side_c_hidden); ?>"><label>Bok C (m)</label><input type="text" name="plot_side_c" value="<?php echo esc_attr($edit_property_plot_side_c); ?>"></p>
                        <input type="hidden" name="plot_dimensions_text" value="<?php echo esc_attr($edit_property_plot_dimensions_text); ?>">
                    </div>

                    <h4>Szczeg&#243;&#322;y nieruchomo&#347;ci</h4>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <p class="eocrm-form-field"><label>Stan wyko&#324;czenia</label>
                            <select name="building_finish">
                                <option value="">Wybierz</option>
                                <?php foreach ($building_finish_options as $building_finish_value => $building_finish_label) : ?>
                                    <option value="<?php echo esc_attr($building_finish_value); ?>" <?php selected($edit_property_building_finish, $building_finish_value); ?>><?php echo wp_kses_post($building_finish_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kuchnia</label>
                            <select name="kitchen_type">
                                <option value="">Wybierz</option>
                                <?php foreach ($kitchen_type_options as $kitchen_type_value => $kitchen_type_label) : ?>
                                    <option value="<?php echo esc_attr($kitchen_type_value); ?>" <?php selected($edit_property_kitchen_type, $kitchen_type_value); ?>><?php echo wp_kses_post($kitchen_type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Poddasze</label>
                            <select name="attic">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="1" <?php selected($edit_property_media_attic, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_property_media_attic, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-not-house">
                            <label>Wielopoziomowe</label>
                            <select name="multi_level">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="1" <?php selected($edit_property_media_multi_level, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_property_media_multi_level, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field-checkbox">
                            <label><input type="checkbox" name="has_parking" value="1" data-eocrm-has-parking <?php checked($edit_property_parking_has); ?>>Miejsce parkingowe</label>
                        </p>
                    </div>
                    <?php $property_additional_render_form_section('details', $property_additional_fields_by_section, $edit_property_custom_fields); ?>

                    <?php
                    $edit_property_parking_najemne_has = in_array('najemne', $edit_property_parking_types, true);
                    $edit_property_parking_podziemne_has = in_array('podziemne', $edit_property_parking_types, true);
                    $edit_property_parking_garaz_has = in_array('garaz', $edit_property_parking_types, true);
                    ?>
                    <div class="eocrm-checkbox-list eocrm-parking-types<?php echo $edit_property_parking_has ? '' : ' is-hidden'; ?>">
                        <div class="eocrm-checkbox-item eocrm-checkbox-item-stack">
                            <label><input type="checkbox" name="parking_types[]" value="najemne" data-eocrm-parking-type-toggle="najemne" <?php checked($edit_property_parking_najemne_has); ?>> Naziemny</label>
                            <input type="number" name="parking_rental_count" min="0" placeholder="Ilo&#347;&#263;" data-eocrm-parking-count="najemne" class="<?php echo $edit_property_parking_najemne_has && $edit_property_parking_has ? '' : 'is-hidden'; ?>" value="<?php echo esc_attr((string) ($edit_property_parking_counts['najemne'] ?? '')); ?>">
                        </div>
                        <div class="eocrm-checkbox-item eocrm-checkbox-item-stack">
                            <label><input type="checkbox" name="parking_types[]" value="podziemne" data-eocrm-parking-type-toggle="podziemne" <?php checked($edit_property_parking_podziemne_has); ?>> Podziemny</label>
                            <input type="number" name="parking_underground_count" min="0" placeholder="Ilo&#347;&#263;" data-eocrm-parking-count="podziemne" class="<?php echo $edit_property_parking_podziemne_has && $edit_property_parking_has ? '' : 'is-hidden'; ?>" value="<?php echo esc_attr((string) ($edit_property_parking_counts['podziemne'] ?? '')); ?>">
                        </div>
                        <div class="eocrm-checkbox-item eocrm-checkbox-item-stack">
                            <label><input type="checkbox" name="parking_types[]" value="garaz" data-eocrm-parking-type-toggle="garaz" <?php checked($edit_property_parking_garaz_has); ?>> Gara&#380;</label>
                            <input type="number" name="parking_garage_count" min="0" placeholder="Ilo&#347;&#263;" data-eocrm-parking-count="garaz" class="<?php echo $edit_property_parking_garaz_has && $edit_property_parking_has ? '' : 'is-hidden'; ?>" value="<?php echo esc_attr((string) ($edit_property_parking_counts['garaz'] ?? '')); ?>">
                        </div>
                    </div>

                    <h4>Budynek i uk&#322;ad</h4>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <div class="eocrm-form-field">
                            <label>Ekspozycja</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="exposure[]" value="polnoc" <?php checked(in_array('polnoc', $edit_property_exposure, true)); ?>>P&oacute;&#322;noc</label>
                                <label><input type="checkbox" name="exposure[]" value="poludnie" <?php checked(in_array('poludnie', $edit_property_exposure, true)); ?>>Po&#322;udnie</label>
                                <label><input type="checkbox" name="exposure[]" value="wschod" <?php checked(in_array('wschod', $edit_property_exposure, true)); ?>>Wsch&oacute;d</label>
                                <label><input type="checkbox" name="exposure[]" value="zachod" <?php checked(in_array('zachod', $edit_property_exposure, true)); ?>>Zach&#243;d</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Widok</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="view[]" value="miasto" <?php checked(in_array('miasto', $edit_property_view, true)); ?>>Miasto</label>
                                <label><input type="checkbox" name="view[]" value="zielec" <?php checked(in_array('zielec', $edit_property_view, true)); ?>>Ziele&#324;</label>
                                <label><input type="checkbox" name="view[]" value="park" <?php checked(in_array('park', $edit_property_view, true)); ?>>Park</label>
                                <label><input type="checkbox" name="view[]" value="podworko" <?php checked(in_array('podworko', $edit_property_view, true)); ?>>Podw&#243;rko</label>
                                <label><input type="checkbox" name="view[]" value="ulica" <?php checked(in_array('ulica', $edit_property_view, true)); ?>>Ulica</label>
                                <label><input type="checkbox" name="view[]" value="panorama" <?php checked(in_array('panorama', $edit_property_view, true)); ?>>Panorama</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Rozk&#322;ad</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="layout[]" value="oddzielne_pokoje" <?php checked(in_array('oddzielne_pokoje', $edit_property_layout, true)); ?>>Rozk&#322;adowe</label>
                                <label><input type="checkbox" name="layout[]" value="salon_z_aneksem" <?php checked(in_array('salon_z_aneksem', $edit_property_layout, true)); ?>>Salon z aneksem</label>
                                <label><input type="checkbox" name="layout[]" value="dwustronne" <?php checked(in_array('dwustronne', $edit_property_layout, true)); ?>>Dwustronne</label>
                                <label><input type="checkbox" name="layout[]" value="narozne" <?php checked(in_array('narozne', $edit_property_layout, true)); ?>>Naro&#380;ne</label>
                            </div>
                        </div>
                    </div>

                    <h4>Media</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ogrzewanie</label>
                            <select name="heating">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="miejskie" <?php selected($edit_property_media_heating, 'miejskie'); ?>>Miejskie</option>
                                <option value="gazowe" <?php selected($edit_property_media_heating, 'gazowe'); ?>>Gazowe</option>
                                <option value="elektryczne" <?php selected($edit_property_media_heating, 'elektryczne'); ?>>Elektryczne</option>
                                <option value="podlogowe" <?php selected($edit_property_media_heating, 'podlogowe'); ?>>Pod&#322;ogowe</option>
                                <option value="inne" <?php selected($edit_property_media_heating, 'inne'); ?>>Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Woda</label>
                            <select name="water">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="miejska" <?php selected($edit_property_media_water, 'miejska'); ?>>Miejska</option>
                                <option value="studnia" <?php selected($edit_property_media_water, 'studnia'); ?>>Studnia</option>
                                <option value="inne" <?php selected($edit_property_media_water, 'inne'); ?>>Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kanalizacja</label>
                            <select name="sewage">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="miejska" <?php selected($edit_property_media_sewage, 'miejska'); ?>>Miejska</option>
                                <option value="szambo" <?php selected($edit_property_media_sewage, 'szambo'); ?>>Szambo</option>
                                <option value="oczyszczalnia" <?php selected($edit_property_media_sewage, 'oczyszczalnia'); ?>>Oczyszczalnia</option>
                                <option value="inne" <?php selected($edit_property_media_sewage, 'inne'); ?>>Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="gas" value="1" <?php checked($edit_property_media_has_gas); ?>>Gaz</label></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="electricity" value="1" <?php checked($edit_property_media_has_electricity); ?>>Pr&#261;d</label></p>
                    </div>
                    <?php $property_additional_render_form_section('media', $property_additional_fields_by_section, $edit_property_custom_fields); ?>

                    <h4>Udogodnienia</h4>
                    <div class="eocrm-checkbox-list eocrm-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="winda" <?php checked(in_array('winda', $edit_property_amenities, true)); ?>>Winda</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="klimatyzacja" <?php checked(in_array('klimatyzacja', $edit_property_amenities, true)); ?>>Klimatyzacja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="monitoring" <?php checked(in_array('monitoring', $edit_property_amenities, true)); ?>>Monitoring/Ochrona</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="recepcja" <?php checked(in_array('recepcja', $edit_property_amenities, true)); ?>>Recepcja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="teren_zamkniety" <?php checked(in_array('teren_zamkniety', $edit_property_amenities, true)); ?>>Teren zamkni&#281;ty</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="domofon" <?php checked(in_array('domofon', $edit_property_amenities, true)); ?>>Domofon</label>
                    </div>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <p class="eocrm-form-field">
                            <label>Umeblowanie</label>
                            <select name="furnished_state">
                                <option value="">Nie okre&#347;lono</option>
                                <option value="tak" <?php selected($edit_property_media_furnished_state, 'tak'); ?>>Tak</option>
                                <option value="nie" <?php selected($edit_property_media_furnished_state, 'nie'); ?>>Nie</option>
                                <option value="czesciowe" <?php selected($edit_property_media_furnished_state, 'czesciowe'); ?>>Cz&#281;&#347;ciowe</option>
                            </select>
                        </p>
                    </div>
                    <?php $property_additional_render_form_section('amenities', $property_additional_fields_by_section, $edit_property_custom_fields); ?>

                    <h4>Wyposa&#380;enie</h4>
                    <div class="eocrm-checkbox-list eocrm-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="pralka" <?php checked(in_array('pralka', $edit_property_equipment, true)); ?>>Pralka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="zmywarka" <?php checked(in_array('zmywarka', $edit_property_equipment, true)); ?>>Zmywarka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="lodowka" <?php checked(in_array('lodowka', $edit_property_equipment, true)); ?>>Lod&#243;wka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="kuchenka" <?php checked(in_array('kuchenka', $edit_property_equipment, true)); ?>>Kuchenka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="piekarnik" <?php checked(in_array('piekarnik', $edit_property_equipment, true)); ?>>Piekarnik</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="telewizor" <?php checked(in_array('telewizor', $edit_property_equipment, true)); ?>>Telewizor</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="mikrofala" <?php checked(in_array('mikrofala', $edit_property_equipment, true)); ?>>Mikrofala</label>
                    </div>
                    <?php $property_additional_render_form_section('equipment', $property_additional_fields_by_section, $edit_property_custom_fields); ?>

                    <?php
                    $edit_property_balcony_has = ! empty($edit_property_extra_balcony['has']);
                    $edit_property_terrace_has = ! empty($edit_property_extra_terrace['has']);
                    $edit_property_basement_has = ! empty($edit_property_extra_basement['has']);
                    $edit_property_storage_has = ! empty($edit_property_extra_storage['has']);
                    $edit_property_garden_has = ! empty($edit_property_extra_garden['has']);
                    ?>
                    <h4 class="eocrm-not-plot">Powierzchnie dodatkowe</h4>
                    <div class="eocrm-form-grid eocrm-not-plot">
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="balcony_has" value="1" data-eocrm-extra-toggle="balcony" <?php checked($edit_property_balcony_has); ?>>Balkon</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-balcony<?php echo $edit_property_balcony_has ? '' : ' is-hidden'; ?>"><label>Ilo&#347;&#263; balkon&#243;w</label><input type="number" name="balcony_count" min="0" value="<?php echo esc_attr((string) ($edit_property_extra_balcony['count'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-balcony<?php echo $edit_property_balcony_has ? '' : ' is-hidden'; ?>"><label>Pow. balkonu (m2)</label><input type="text" name="balcony_area" value="<?php echo esc_attr((string) ($edit_property_extra_balcony['area'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="terrace_has" value="1" data-eocrm-extra-toggle="terrace" <?php checked($edit_property_terrace_has); ?>>Taras</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-terrace<?php echo $edit_property_terrace_has ? '' : ' is-hidden'; ?>"><label>Ilo&#347;&#263; taras&#243;w</label><input type="number" name="terrace_count" min="0" value="<?php echo esc_attr((string) ($edit_property_extra_terrace['count'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-terrace<?php echo $edit_property_terrace_has ? '' : ' is-hidden'; ?>"><label>Pow. tarasu (m2)</label><input type="text" name="terrace_area" value="<?php echo esc_attr((string) ($edit_property_extra_terrace['area'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="basement_has" value="1" data-eocrm-extra-toggle="basement" <?php checked($edit_property_basement_has); ?>>Piwnica</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-basement<?php echo $edit_property_basement_has ? '' : ' is-hidden'; ?>"><label>Pow. piwnicy (m2)</label><input type="text" name="basement_area" value="<?php echo esc_attr((string) ($edit_property_extra_basement['area'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="storage_has" value="1" data-eocrm-extra-toggle="storage" <?php checked($edit_property_storage_has); ?>>Kom&#243;rka lokatorska</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-storage<?php echo $edit_property_storage_has ? '' : ' is-hidden'; ?>"><label>Pow. kom&#243;rki (m2)</label><input type="text" name="storage_area" value="<?php echo esc_attr((string) ($edit_property_extra_storage['area'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="garden_has" value="1" data-eocrm-extra-toggle="garden" <?php checked($edit_property_garden_has); ?>>Ogr&#243;dek</label></p>
                        <p class="eocrm-form-field eocrm-extra-field eocrm-extra-garden<?php echo $edit_property_garden_has ? '' : ' is-hidden'; ?>"><label>Pow. ogr&#243;dka (m2)</label><input type="text" name="garden_area" value="<?php echo esc_attr((string) ($edit_property_extra_garden['area'] ?? '')); ?>"></p>
                    </div>

                    <h4>Multimedia i opis</h4>
                    <input type="hidden" name="gallery_attachment_ids" id="eocrm-edit-gallery-ids" value="<?php echo esc_attr(implode(',', $edit_property_gallery_ids)); ?>">
                    <p class="eocrm-inline-checkboxes">
                        <button class="eocrm-btn" type="button" data-eocrm-media-gallery data-target-input="eocrm-edit-gallery-ids" data-target-preview="eocrm-edit-gallery-preview">Wybierz zdj&#281;cia galerii</button>
                    </p>
                    <div id="eocrm-edit-gallery-preview" class="eocrm-media-preview">
                        <?php if (! empty($edit_property_gallery_preview_rows)) : ?>
                            <?php foreach ($edit_property_gallery_preview_rows as $edit_photo_row) : ?>
                                <span class="eocrm-gallery-item" data-eocrm-gallery-id="<?php echo (int) ($edit_photo_row['attachment_id'] ?? 0); ?>" draggable="true">
                                    <img class="eocrm-thumb" src="<?php echo esc_url((string) ($edit_photo_row['thumb_url'] ?? '')); ?>" alt="Zdjecie galerii">
                                    <button type="button" class="eocrm-gallery-remove" data-eocrm-gallery-remove aria-label="Usun zdjecie">x</button>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Link do filmu</label><input type="url" name="video_link" value="<?php echo esc_attr((string) ($edit_property_media['video_link'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Link do wirtualnego spaceru</label><input type="url" name="virtual_tour_link" value="<?php echo esc_attr((string) ($edit_property_media['virtual_tour_link'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field eocrm-form-field-full">
                            <label>Rzuty kondygnacji</label>
                            <input type="hidden" name="floor_plan_items_json" id="eocrm-edit-floor-plan-items-json" value="<?php echo esc_attr((string) $edit_property_floor_plan_items_json); ?>">
                            <div class="eocrm-floor-plan-builder" data-eocrm-floor-plan-builder data-target-input="eocrm-edit-floor-plan-items-json">
                                <div class="eocrm-floor-plan-list" data-eocrm-floor-plan-list></div>
                                <p class="eocrm-inline-checkboxes">
                                    <button class="eocrm-btn" type="button" data-eocrm-floor-plan-add>Dodaj rzut poziomu</button>
                                </p>
                                <p class="eocrm-muted">Dodaj dowolna liczbe rzutow i nazwij poziomy, np. Poziom 1, Poziom -1, Piwnica.</p>
                            </div>
                        </p>
                    </div>

                    <h4>Opis nieruchomo&#347;ci</h4>
                    <?php if (function_exists('wp_editor')) : ?>
                        <?php wp_editor((string) ($property_profile['description'] ?? ''), 'eocrm_property_description_edit_' . (int) ($property_profile['id'] ?? 0), ['textarea_name' => 'description', 'textarea_rows' => 8, 'media_buttons' => false, 'teeny' => true]); ?>
                    <?php else : ?>
                        <textarea class="large-text" rows="8" name="description"><?php echo esc_textarea((string) ($property_profile['description'] ?? '')); ?></textarea>
                    <?php endif; ?>

                    <h4>Znaczniki i eksport</h4>
                    <div class="eocrm-checkbox-list">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="nowa_oferta" <?php checked(in_array('nowa_oferta', $edit_property_tags, true)); ?>>Nowa oferta</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="wylacznosc" <?php checked(in_array('wylacznosc', $edit_property_tags, true)); ?>>Wy&#322;&#261;czno&#347;&#263;</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="sprzedane" data-eocrm-tag-sold <?php checked(in_array('sprzedane', $edit_property_tags, true)); ?>>Sprzedane</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="wynajete" data-eocrm-tag-rented <?php checked(in_array('wynajete', $edit_property_tags, true)); ?>>Wynaj&#281;te</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="nowa_cena" <?php checked(in_array('nowa_cena', $edit_property_tags, true)); ?>>Nowa cena</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="bez_prowizji" <?php checked(in_array('bez_prowizji', $edit_property_tags, true)); ?>>Bez prowizji</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="oferta_mls" <?php checked(in_array('oferta_mls', $edit_property_tags, true)); ?>>Oferta MLS</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="tags[]" value="premium" <?php checked(in_array('premium', $edit_property_tags, true)); ?>>Premium</label>
                    </div>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="export_www" value="1" <?php checked(! empty($property_profile['export_www'])); ?>>Eksport na WWW</label></p>
                        <p class="eocrm-form-field-checkbox"><label><input type="checkbox" name="export_portals" value="1" <?php checked(! empty($property_profile['export_portals'])); ?>>Eksport na Portale</label></p>
                    </div>

                    <p><button class="eocrm-btn eocrm-btn-primary" type="submit"><?php echo $is_copy_property_mode ? 'Utworz kopie nieruchomosci' : 'Zapisz zmiany nieruchomo&#347;ci'; ?></button></p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($property_id > 0 && ! is_array($property_profile)) : ?>
            <div class="eocrm-alert eocrm-alert-error">Nie znaleziono profilu nieruchomo&#347;ci.</div>
        <?php endif; ?>

        <?php if (is_array($property_profile)) : ?>
            <?php
            $property_owner = get_userdata((int) ($property_profile['owner_user_id'] ?? 0));
            $property_address_profile = $format_address([
                trim((string) ($property_profile['street'] ?? '') . ' ' . (string) ($property_profile['building_no'] ?? '')),
                (string) ($property_profile['apartment_no'] ?? ''),
                (string) ($property_profile['postal_code'] ?? ''),
                (string) ($property_profile['city'] ?? ''),
                (string) ($property_profile['district'] ?? ''),
            ]);
            $property_parking = isset($property_profile['parking']) && is_array($property_profile['parking']) ? $property_profile['parking'] : [];
            $property_media = isset($property_profile['media']) && is_array($property_profile['media']) ? $property_profile['media'] : [];
            $property_extra = isset($property_profile['extra_areas']) && is_array($property_profile['extra_areas']) ? $property_profile['extra_areas'] : [];
            $parking_counts = isset($property_parking['counts']) && is_array($property_parking['counts']) ? $property_parking['counts'] : [];
            $property_profile_type = strtoupper(trim((string) ($property_profile['property_type'] ?? '')));
            $property_plot_shape = strtolower(trim((string) ($property_profile['plot_shape'] ?? '')));
            if ($property_plot_shape === 'regularny') {
                $property_plot_shape = 'prostokat';
            } elseif ($property_plot_shape === 'nieregularny') {
                $property_plot_shape = 'nieregularna';
            }
            $property_plot_shape_labels = [
                'kwadrat' => 'Kwadrat',
                'prostokat' => 'Prostokat',
                'trojkat' => 'Trojkat',
                'nieregularna' => 'Nieregularna',
            ];
            $property_plot_shape_label = isset($property_plot_shape_labels[$property_plot_shape]) ? (string) $property_plot_shape_labels[$property_plot_shape] : '-';
            $property_plot_side_c = null;
            $property_plot_dimensions_text = trim((string) ($property_profile['plot_dimensions_text'] ?? ''));
            if ($property_plot_dimensions_text !== '') {
                $property_plot_dimensions_meta = json_decode($property_plot_dimensions_text, true);
                if (is_array($property_plot_dimensions_meta) && isset($property_plot_dimensions_meta['side_c']) && is_numeric((string) $property_plot_dimensions_meta['side_c'])) {
                    $property_plot_side_c = (float) $property_plot_dimensions_meta['side_c'];
                }
            }
            $property_plot_dimensions_display = '-';
            $property_plot_side_a = isset($property_profile['plot_length']) ? (float) $property_profile['plot_length'] : 0.0;
            $property_plot_side_b = isset($property_profile['plot_width']) ? (float) $property_profile['plot_width'] : 0.0;
            if ($property_plot_shape === 'kwadrat' && $property_plot_side_a > 0) {
                $property_plot_dimensions_display = 'A: ' . rtrim(rtrim(number_format($property_plot_side_a, 2, '.', ''), '0'), '.') . ' m';
            } elseif ($property_plot_shape === 'prostokat' && $property_plot_side_a > 0 && $property_plot_side_b > 0) {
                $property_plot_dimensions_display = 'A: ' . rtrim(rtrim(number_format($property_plot_side_a, 2, '.', ''), '0'), '.') . ' m, B: ' . rtrim(rtrim(number_format($property_plot_side_b, 2, '.', ''), '0'), '.') . ' m';
            } elseif ($property_plot_shape === 'trojkat' && $property_plot_side_a > 0 && $property_plot_side_b > 0 && is_float($property_plot_side_c) && $property_plot_side_c > 0) {
                $property_plot_dimensions_display = 'A: ' . rtrim(rtrim(number_format($property_plot_side_a, 2, '.', ''), '0'), '.') . ' m, B: ' . rtrim(rtrim(number_format($property_plot_side_b, 2, '.', ''), '0'), '.') . ' m, C: ' . rtrim(rtrim(number_format($property_plot_side_c, 2, '.', ''), '0'), '.') . ' m';
            } elseif ($property_plot_shape === 'nieregularna' && $property_plot_dimensions_text !== '') {
                $property_plot_dimensions_display = $property_plot_dimensions_text;
            }
            $property_plot_area_display = '-';
            $property_plot_area_from_dimensions = null;
            if ($property_plot_shape === 'kwadrat' && $property_plot_side_a > 0) {
                $property_plot_area_from_dimensions = $property_plot_side_a * $property_plot_side_a;
            } elseif ($property_plot_shape === 'prostokat' && $property_plot_side_a > 0 && $property_plot_side_b > 0) {
                $property_plot_area_from_dimensions = $property_plot_side_a * $property_plot_side_b;
            } elseif ($property_plot_shape === 'trojkat' && $property_plot_side_a > 0 && $property_plot_side_b > 0 && is_float($property_plot_side_c) && $property_plot_side_c > 0) {
                $triangle_s = ($property_plot_side_a + $property_plot_side_b + $property_plot_side_c) / 2;
                $triangle_area = $triangle_s * ($triangle_s - $property_plot_side_a) * ($triangle_s - $property_plot_side_b) * ($triangle_s - $property_plot_side_c);
                if ($triangle_area > 0) {
                    $property_plot_area_from_dimensions = sqrt($triangle_area);
                }
            }
            $property_plot_area_direct = null;
            if ($property_profile_type === 'DOM' && isset($property_profile['plot_area']) && is_numeric((string) $property_profile['plot_area'])) {
                $property_plot_area_direct = (float) $property_profile['plot_area'];
            } elseif ($property_profile_type === 'DZIALKA' && isset($property_profile['area']) && is_numeric((string) $property_profile['area'])) {
                $property_plot_area_direct = (float) $property_profile['area'];
            }

            if (is_float($property_plot_area_direct) && $property_plot_area_direct > 0) {
                $property_plot_area_display = rtrim(rtrim(number_format($property_plot_area_direct, 2, '.', ''), '0'), '.') . ' m2';
            } elseif (is_float($property_plot_area_from_dimensions) && $property_plot_area_from_dimensions > 0) {
                $property_plot_area_display = rtrim(rtrim(number_format($property_plot_area_from_dimensions, 2, '.', ''), '0'), '.') . ' m2';
            }
            $property_profile_custom_fields = isset($property_profile['custom_fields']) && is_array($property_profile['custom_fields']) ? $property_profile['custom_fields'] : [];
            $property_profile_custom_detail_rows = $property_additional_build_profile_rows($property_additional_fields_by_section, $property_profile_custom_fields, 'details');
            $property_profile_custom_media_rows = $property_additional_build_profile_rows($property_additional_fields_by_section, $property_profile_custom_fields, 'media');
            $property_profile_custom_amenity_rows = $property_additional_build_profile_rows($property_additional_fields_by_section, $property_profile_custom_fields, 'amenities');
            $property_profile_custom_equipment_rows = $property_additional_build_profile_rows($property_additional_fields_by_section, $property_profile_custom_fields, 'equipment');
            $property_portal_export_status = isset($property_portal_export_status) && is_array($property_portal_export_status) ? $property_portal_export_status : null;
            $property_portal_event = is_array($property_portal_export_status) ? (string) ($property_portal_export_status['event'] ?? '') : '';
            $property_portal_status = is_array($property_portal_export_status) ? (string) ($property_portal_export_status['status'] ?? '') : '';
            $property_portal_attempts = is_array($property_portal_export_status) ? (int) ($property_portal_export_status['attempts'] ?? 0) : 0;
            $property_portal_updated_at = is_array($property_portal_export_status) ? (string) ($property_portal_export_status['updated_at'] ?? '') : '';
            $property_portal_last_error = is_array($property_portal_export_status) ? (string) ($property_portal_export_status['last_error'] ?? '') : '';
            ?>
            <?php
            // ----- Modern property profile header / stages / quick stats -----
            $profile_property_id_int = (int) ($property_profile['id'] ?? 0);
            $profile_offer_number = (string) ($property_profile['offer_number'] ?? '');
            $profile_property_type_code = strtoupper(trim((string) ($property_profile['property_type'] ?? '')));
            $profile_property_type_label_map = [
                'MIESZKANIE' => 'Mieszkanie',
                'DOM' => 'Dom',
                'DZIALKA' => 'Dzia&#322;ka',
                'LOKAL_HU' => 'Lokal H/U',
            ];
            $profile_property_type_label = $profile_property_type_label_map[$profile_property_type_code] ?? ($profile_property_type_code !== '' ? ucfirst(strtolower($profile_property_type_code)) : '-');
            $profile_transaction_code = strtoupper(trim((string) ($property_profile['transaction_type'] ?? '')));
            $profile_transaction_label_map = [
                'SPRZEDAZ' => 'Sprzeda&#380;',
                'KUPNO' => 'Kupno',
                'WYNAJEM' => 'Wynajem',
                'NAJEM' => 'Najem',
            ];
            $profile_transaction_label = $profile_transaction_label_map[$profile_transaction_code] ?? ($profile_transaction_code !== '' ? ucfirst(strtolower($profile_transaction_code)) : '');

            $profile_status_label = 'Aktywna';
            $profile_status_class = 'is-active';
            if (! empty($property_profile['is_sold'])) {
                $profile_status_label = 'Sprzedane';
                $profile_status_class = 'is-sold';
            } elseif (! empty($property_profile['is_rented'])) {
                $profile_status_label = 'Wynaj&#281;te';
                $profile_status_class = 'is-rented';
            } elseif (! empty($property_profile['is_premium'])) {
                $profile_status_label = 'Premium';
                $profile_status_class = 'is-premium';
            } elseif (! empty($property_profile['is_exclusive'])) {
                $profile_status_label = 'Wy&#322;&#261;czno&#347;&#263;';
                $profile_status_class = 'is-exclusive';
            }

            $profile_owner_name = $property_owner instanceof WP_User ? (string) $property_owner->display_name : '';
            $profile_owner_avatar = $property_owner instanceof WP_User ? $resolve_owner_photo_url((int) $property_owner->ID, 'medium') : '';
            $profile_owner_initial = $profile_owner_name !== '' ? mb_strtoupper(mb_substr($profile_owner_name, 0, 1)) : '';

            $profile_property_pdf_url = add_query_arg(
                [
                    'crm' => 'properties',
                    'property_id' => $profile_property_id_int,
                    'eocrm_export' => 'pdf_property',
                    'eocrm_export_nonce' => wp_create_nonce('eocrm_export_pdf_property'),
                ],
                $current_url
            );

            // Stage timeline (only when there's a linked agreement).
            $profile_stage_data = [];
            $profile_stage_current = '';
            $profile_stage_total = 0;
            $profile_stage_completed = 0;
            $profile_stage_progress_pct = 0;
            if (
                isset($property_profile_agreement_stages)
                && is_array($property_profile_agreement_stages)
                && ! empty($property_profile_agreement_stages)
                && is_array($property_profile_agreement)
            ) {
                $profile_stage_current = (string) ($property_profile_agreement['current_stage'] ?? '');
                $profile_history = isset($property_profile_agreement_stage_history) && is_array($property_profile_agreement_stage_history)
                    ? $property_profile_agreement_stage_history
                    : [];
                $profile_stage_dates = [];
                foreach ($profile_history as $hist_row) {
                    if (! is_array($hist_row)) {
                        continue;
                    }
                    $hist_name = (string) ($hist_row['stage_name'] ?? '');
                    $hist_date = (string) ($hist_row['stage_date'] ?? '');
                    if ($hist_name !== '' && $hist_date !== '' && ! isset($profile_stage_dates[$hist_name])) {
                        $profile_stage_dates[$hist_name] = $hist_date;
                    }
                }
                $current_stage_idx = -1;
                foreach ($property_profile_agreement_stages as $stage_idx => $stage_name) {
                    if ((string) $stage_name === $profile_stage_current) {
                        $current_stage_idx = $stage_idx;
                        break;
                    }
                }
                $profile_stage_total = count($property_profile_agreement_stages);
                foreach ($property_profile_agreement_stages as $stage_idx => $stage_name) {
                    $state = 'upcoming';
                    if ($current_stage_idx >= 0 && $stage_idx < $current_stage_idx) {
                        $state = 'completed';
                    } elseif ($current_stage_idx >= 0 && $stage_idx === $current_stage_idx) {
                        $state = 'current';
                    }
                    if ($is_agreement_stage_finished($profile_stage_current) && $stage_idx === $current_stage_idx) {
                        $state = 'completed';
                    }
                    $profile_stage_data[] = [
                        'name' => (string) $stage_name,
                        'state' => $state,
                        'date' => isset($profile_stage_dates[(string) $stage_name]) ? (string) $profile_stage_dates[(string) $stage_name] : '',
                        'index' => $stage_idx + 1,
                    ];
                    if ($state === 'completed') {
                        $profile_stage_completed++;
                    }
                }
                if ($profile_stage_total > 0) {
                    $effective_position = $current_stage_idx >= 0 ? $current_stage_idx : ($profile_stage_completed > 0 ? $profile_stage_completed - 1 : 0);
                    if ($is_agreement_stage_finished($profile_stage_current)) {
                        $profile_stage_progress_pct = 100;
                    } else {
                        $profile_stage_progress_pct = (int) round((max(0, $effective_position) / max(1, $profile_stage_total - 1)) * 100);
                    }
                }
            }

            $profile_address_short = $format_address([
                trim((string) ($property_profile['street'] ?? '') . ' ' . (string) ($property_profile['building_no'] ?? '')),
                (string) ($property_profile['city'] ?? ''),
            ]);
            $profile_price_str = $format_property_price($property_profile['price'] ?? null, $property_profile['price_currency'] ?? 'PLN');
            $profile_price_m2_str = $format_property_price_per_m2($property_profile['price_per_m2'] ?? null, $property_profile['price_currency'] ?? 'PLN');
            $profile_area_value = isset($property_profile['area']) && is_numeric((string) $property_profile['area']) ? (float) $property_profile['area'] : null;
            $profile_rooms_value = isset($property_profile['rooms']) && (string) $property_profile['rooms'] !== '' ? (int) $property_profile['rooms'] : null;
            $profile_floor_no = isset($property_profile['floor_no']) && (string) $property_profile['floor_no'] !== '' ? (int) $property_profile['floor_no'] : null;
            $profile_floors_total = isset($property_profile['floors_total']) && (string) $property_profile['floors_total'] !== '' ? (int) $property_profile['floors_total'] : null;
            ?>

            <section class="eocrm-prop-profile" data-eocrm-prop-profile>

                <header class="eocrm-prop-profile-hero">
                    <div class="eocrm-prop-profile-hero__main">
                        <span class="eocrm-prop-profile-hero__eyebrow">Profil oferty</span>
                        <h2 class="eocrm-prop-profile-hero__title">
                            <?php echo esc_html($profile_offer_number !== '' ? $profile_offer_number : ('Oferta #' . $profile_property_id_int)); ?>
                            <span class="eocrm-prop-status-pill <?php echo esc_attr($profile_status_class); ?>"><?php echo wp_kses_post($profile_status_label); ?></span>
                        </h2>
                        <p class="eocrm-prop-profile-hero__meta">
                            <?php echo wp_kses_post($profile_property_type_label); ?>
                            <?php if ($profile_transaction_label !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                <?php echo wp_kses_post($profile_transaction_label); ?>
                            <?php endif; ?>
                            <?php if ($profile_address_short !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                <?php echo esc_html($profile_address_short); ?>
                            <?php endif; ?>
                            <?php if ($profile_owner_name !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                Opiekun: <strong><?php echo esc_html($profile_owner_name); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="eocrm-prop-profile-hero__actions">
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'properties'], $current_url)); ?>">&larr; Powr&#243;t</a>
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => $profile_property_id_int, 'mode' => 'edit-property'], $current_url)); ?>">Edytuj</a>
                        <?php if ($is_admin_user) : ?>
                            <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => $profile_property_id_int, 'mode' => 'copy-property'], $current_url)); ?>">Kopiuj</a>
                        <?php endif; ?>
                        <a
                            class="eocrm-btn eocrm-btn-pdf"
                            data-eocrm-pdf-choice-open
                            href="<?php echo esc_url($profile_property_pdf_url); ?>"
                            title="Eksportuj t&#281; ofert&#281; do PDF"
                            aria-label="Eksportuj t&#281; ofert&#281; do PDF"
                        >
                            <span aria-hidden="true" class="eocrm-export-icon">&#128196;</span>
                        </a>
                        <?php if ($is_admin_user) : ?>
                            <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => $profile_property_id_int], $current_url)); ?>" onsubmit="return confirm('Czy na pewno usunac nieruchomosc?');" class="eocrm-prop-profile-hero__delete">
                                <?php wp_nonce_field('eocrm_delete_property', 'eocrm_property_delete_nonce'); ?>
                                <input type="hidden" name="eocrm_action" value="delete_property">
                                <input type="hidden" name="property_id" value="<?php echo (int) $profile_property_id_int; ?>">
                                <button class="eocrm-btn eocrm-btn-danger" type="submit">Usu&#324;</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (! empty($profile_stage_data)) : ?>
                    <section class="eocrm-prop-stages" aria-label="Etap umowy">
                        <header class="eocrm-prop-stages__head">
                            <div>
                                <h3>Etap umowy</h3>
                                <p class="eocrm-prop-stages__hint">
                                    Pobrano z umowy
                                    <a href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => (int) ($property_profile_agreement['id'] ?? 0)], $current_url)); ?>">
                                        <?php echo esc_html((string) ($property_profile_agreement['agreement_number'] ?? '')); ?>
                                    </a>
                                    <?php if ($profile_transaction_label !== '') : ?>
                                        <span class="eocrm-prop-stages__sep">&middot;</span>
                                        <?php echo wp_kses_post($profile_transaction_label); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="eocrm-prop-stages__progress">
                                <span class="eocrm-prop-stages__progress-label">Post&#281;p</span>
                                <span class="eocrm-prop-stages__progress-value"><?php echo esc_html((string) $profile_stage_progress_pct); ?>%</span>
                                <span class="eocrm-prop-stages__progress-track">
                                    <span class="eocrm-prop-stages__progress-fill" style="width: <?php echo esc_attr((string) $profile_stage_progress_pct); ?>%;"></span>
                                </span>
                            </div>
                        </header>
                        <ol class="eocrm-prop-stages__list">
                            <?php foreach ($profile_stage_data as $stage_row) :
                                $stage_state = (string) ($stage_row['state'] ?? 'upcoming');
                                $stage_label = (string) ($stage_row['name'] ?? '');
                                $stage_date_raw = (string) ($stage_row['date'] ?? '');
                                $stage_idx_num = (int) ($stage_row['index'] ?? 0);
                                $stage_date_formatted = '';
                                if ($stage_date_raw !== '' && $stage_date_raw !== '0000-00-00') {
                                    $stage_date_ts = strtotime($stage_date_raw);
                                    if ($stage_date_ts !== false) {
                                        $stage_date_formatted = date_i18n('d.m.Y', $stage_date_ts);
                                    }
                                }
                            ?>
                                <li class="eocrm-prop-stage is-<?php echo esc_attr($stage_state); ?>">
                                    <span class="eocrm-prop-stage__indicator" aria-hidden="true">
                                        <?php if ($stage_state === 'completed') : ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php else : ?>
                                            <span class="eocrm-prop-stage__num"><?php echo esc_html((string) $stage_idx_num); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="eocrm-prop-stage__body">
                                        <span class="eocrm-prop-stage__name"><?php echo esc_html($stage_label); ?></span>
                                        <span class="eocrm-prop-stage__sub">
                                            <?php if ($stage_state === 'completed') :
                                                echo esc_html($stage_date_formatted !== '' ? $stage_date_formatted : 'Zako&#324;czony');
                                            elseif ($stage_state === 'current') :
                                                echo esc_html($stage_date_formatted !== '' ? ('Od ' . $stage_date_formatted) : 'W trakcie');
                                            else :
                                                echo 'Oczekuje';
                                            endif; ?>
                                        </span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php elseif (! is_array($property_profile_agreement)) : ?>
                    <section class="eocrm-prop-stages eocrm-prop-stages--empty" aria-label="Etap umowy">
                        <div class="eocrm-prop-stages__empty">
                            <span class="eocrm-prop-stages__empty-icon" aria-hidden="true">&#128196;</span>
                            <div>
                                <strong>Brak powi&#261;zanej umowy</strong>
                                <span>Po powi&#261;zaniu nieruchomo&#347;ci z umow&#261; pojawi si&#281; tutaj timeline etap&oacute;w.</span>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="eocrm-prop-quick-stats">
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--blue">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128181;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Cena</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo esc_html($profile_price_str !== '-' ? $profile_price_str : '-'); ?></span>
                            <?php if ($profile_price_m2_str !== '-' && $profile_price_m2_str !== '') : ?>
                                <span class="eocrm-prop-quick-stat__sub"><?php echo esc_html($profile_price_m2_str); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--green">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128208;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Metra&#380;</span>
                            <span class="eocrm-prop-quick-stat__value">
                                <?php if ($profile_area_value !== null) : ?>
                                    <?php echo esc_html(rtrim(rtrim(number_format($profile_area_value, 2, '.', ''), '0'), '.')); ?> m<sup>2</sup>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </span>
                            <?php if ($profile_floor_no !== null && $profile_floors_total !== null) : ?>
                                <span class="eocrm-prop-quick-stat__sub">pi&#281;tro <?php echo esc_html((string) $profile_floor_no . '/' . (string) $profile_floors_total); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--purple">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128719;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Pokoje</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo esc_html($profile_rooms_value !== null ? (string) $profile_rooms_value : '-'); ?></span>
                            <?php if (isset($property_profile['bedrooms']) && (string) $property_profile['bedrooms'] !== '') : ?>
                                <span class="eocrm-prop-quick-stat__sub">w tym <?php echo esc_html((string) (int) $property_profile['bedrooms']); ?> sypialni</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--orange">
                        <span class="eocrm-prop-quick-stat__avatar" aria-hidden="true">
                            <?php if ($profile_owner_avatar !== '') : ?>
                                <img src="<?php echo esc_url($profile_owner_avatar); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="eocrm-prop-quick-stat__avatar-initial"><?php echo esc_html($profile_owner_initial !== '' ? $profile_owner_initial : '?'); ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Opiekun</span>
                            <span class="eocrm-prop-quick-stat__value eocrm-prop-quick-stat__value--small"><?php echo esc_html($profile_owner_name !== '' ? $profile_owner_name : '-'); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">
                                <?php
                                $profile_export_chips = [];
                                if (! empty($property_profile['export_www'])) {
                                    $profile_export_chips[] = 'WWW';
                                }
                                if (! empty($property_profile['export_portals'])) {
                                    $profile_export_chips[] = 'Portale';
                                }
                                echo esc_html(! empty($profile_export_chips) ? ('Eksport: ' . implode(' + ', $profile_export_chips)) : 'Eksport wy&#322;&#261;czony');
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

            <?php
            // ---------- Helpers + grouped row builders ----------
            $property_has_display_value = static function ($value): bool {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_scalar($item) && trim((string) $item) !== '') {
                            return true;
                        }
                    }
                    return false;
                }
                if (is_int($value) || is_float($value)) {
                    return true;
                }
                if ($value === null) {
                    return false;
                }
                $string_value = trim((string) $value);
                return $string_value !== '' && $string_value !== '-';
            };
            $property_add_row = static function (array &$rows, string $label, $value) use ($property_has_display_value): void {
                if (! $property_has_display_value($value)) {
                    return;
                }
                $rows[] = ['label' => $label, 'value' => (string) $value];
            };

            // LOCATION
            $rows_location = [];
            $property_add_row($rows_location, 'Adres', $property_address_profile);
            $property_add_row($rows_location, 'Dzielnica', (string) ($property_profile['district'] ?? ''));
            $property_add_row($rows_location, 'Powiat', (string) ($property_profile['county'] ?? ''));
            $property_add_row($rows_location, 'Gmina', (string) ($property_profile['gmina'] ?? ''));
            $property_add_row($rows_location, 'Obreb', (string) ($property_profile['precinct'] ?? ''));
            $property_add_row($rows_location, 'Numer dzialki', (string) ($property_profile['plot_number'] ?? ''));
            $property_latitude = isset($property_profile['latitude']) ? trim((string) $property_profile['latitude']) : '';
            $property_longitude = isset($property_profile['longitude']) ? trim((string) $property_profile['longitude']) : '';
            $property_coordinates = '';
            if ($property_latitude !== '' || $property_longitude !== '') {
                $property_coordinates = $property_latitude . ', ' . $property_longitude;
            }
            $property_add_row($rows_location, 'Wsp&#243;&#322;rz&#281;dne (lat, lng)', $property_coordinates);
            $property_add_row($rows_location, 'Adres pinu mapy', (string) ($property_media['map_pin_address'] ?? ''));

            // DIMENSIONS & ROOMS
            $rows_dimensions = [];
            if (isset($property_profile['area']) && is_numeric((string) $property_profile['area'])) {
                $property_add_row($rows_dimensions, 'Metra&#380;', rtrim(rtrim(number_format((float) $property_profile['area'], 2, '.', ''), '0'), '.') . ' m&sup2;');
            }
            if (in_array($property_profile_type, ['DOM', 'DZIALKA'], true)) {
                $property_add_row($rows_dimensions, 'Wielko&#347;&#263; dzia&#322;ki', $property_plot_area_display);
                $property_add_row($rows_dimensions, 'Kszta&#322;t dzia&#322;ki', $property_plot_shape_label);
                $property_add_row($rows_dimensions, 'Wymiary dzia&#322;ki', $property_plot_dimensions_display);
            }
            $property_floor_combined = '';
            if (isset($property_profile['floor_no']) && (string) $property_profile['floor_no'] !== '') {
                $property_floor_combined = (string) (int) $property_profile['floor_no'];
                if (isset($property_profile['floors_total']) && (string) $property_profile['floors_total'] !== '') {
                    $property_floor_combined .= ' / ' . (string) (int) $property_profile['floors_total'];
                }
            }
            $property_add_row($rows_dimensions, 'Pi&#281;tro', $property_floor_combined);
            $property_add_row($rows_dimensions, 'Pokoje', isset($property_profile['rooms']) ? (string) $property_profile['rooms'] : '');
            $property_add_row($rows_dimensions, 'Sypialnie', isset($property_profile['bedrooms']) ? (string) $property_profile['bedrooms'] : '');
            $property_add_row($rows_dimensions, '&#321;azienki', isset($property_profile['bathrooms']) ? (string) $property_profile['bathrooms'] : '');
            $property_add_row($rows_dimensions, 'Toalety', isset($property_profile['toilets']) ? (string) $property_profile['toilets'] : '');
            if ($property_profile_type !== 'DZIALKA') {
                $property_extra_areas_value = $render_property_extra_areas($property_extra);
                $property_add_row($rows_dimensions, 'Dodatkowe powierzchnie', $property_extra_areas_value);
            }

            // CONDITION & LEGAL
            $rows_condition = [];
            if ($property_profile_type !== 'DZIALKA') {
                $property_add_row($rows_condition, 'Rok budowy', isset($property_profile['year_built']) ? (string) $property_profile['year_built'] : '');
            }
            if (array_key_exists('house_type', $property_profile)) {
                $property_add_row($rows_condition, 'Typ domu', (string) ($property_profile['house_type'] ?? ''));
            }
            $property_add_row($rows_condition, 'Stan wyko&#324;czenia', (string) ($property_profile['building_finish'] ?? ''));
            $property_add_row($rows_condition, 'Kuchnia', (string) ($property_profile['kitchen_type'] ?? ''));
            $property_add_row($rows_condition, 'Stan prawny', (string) ($property_profile['legal_status'] ?? ''));
            $property_add_row($rows_condition, 'Numer KW', (string) ($property_profile['land_registry_no'] ?? ''));
            if (array_key_exists('no_land_registry', $property_profile) && (string) ($property_profile['no_land_registry'] ?? '') !== '') {
                $property_add_row($rows_condition, 'Brak KW', $render_bool($property_profile['no_land_registry'] ?? ''));
            }

            // MEDIA & INSTALLATIONS + PARKING
            $rows_media_inst = [];
            $property_add_row($rows_media_inst, 'Ogrzewanie', (string) ($property_media['heating'] ?? ''));
            $property_add_row($rows_media_inst, 'Woda', (string) ($property_media['water'] ?? ''));
            $property_add_row($rows_media_inst, 'Kanalizacja', (string) ($property_media['sewage'] ?? ''));
            if (array_key_exists('gas', $property_media) && $property_media['gas'] !== null && $property_media['gas'] !== '') {
                $property_add_row($rows_media_inst, 'Gaz', $render_bool(! empty($property_media['gas']) ? '1' : '0'));
            }
            if (array_key_exists('electricity', $property_media) && $property_media['electricity'] !== null && $property_media['electricity'] !== '') {
                $property_add_row($rows_media_inst, 'Pr&#261;d', $render_bool(! empty($property_media['electricity']) ? '1' : '0'));
            }
            $property_add_row($rows_media_inst, 'Umeblowanie', (string) ($property_media['furnished_state'] ?? ''));
            if (array_key_exists('attic', $property_media) && $property_media['attic'] !== null && $property_media['attic'] !== '') {
                $property_add_row($rows_media_inst, 'Poddasze', $render_bool(! empty($property_media['attic']) ? '1' : '0'));
            }
            if (array_key_exists('multi_level', $property_media) && $property_media['multi_level'] !== null && $property_media['multi_level'] !== '') {
                $property_add_row($rows_media_inst, 'Wielopoziomowe', $render_bool(! empty($property_media['multi_level']) ? '1' : '0'));
            }
            if (array_key_exists('has_parking', $property_parking) && (string) ($property_parking['has_parking'] ?? '') !== '') {
                $property_add_row($rows_media_inst, 'Miejsce parkingowe', $render_bool($property_parking['has_parking'] ?? ''));
            }
            if (! empty($property_parking['types']) && is_array($property_parking['types'])) {
                $property_add_row($rows_media_inst, 'Typy parkingu', $render_list($property_parking['types']));
            }
            $parking_count_chunks = [];
            foreach (['najemne' => 'Naziemny', 'podziemne' => 'Podziemny', 'garaz' => 'Garaz'] as $parking_key => $parking_label) {
                if (array_key_exists($parking_key, $parking_counts) && $parking_counts[$parking_key] !== null && $parking_counts[$parking_key] !== '') {
                    $parking_count_chunks[] = $parking_label . ': ' . (string) $parking_counts[$parking_key];
                }
            }
            if (! empty($parking_count_chunks)) {
                $property_add_row($rows_media_inst, 'Liczba miejsc parkingowych', implode(', ', $parking_count_chunks));
            }
            foreach ($property_profile_custom_media_rows as $custom_media_row) {
                $property_add_row(
                    $rows_media_inst,
                    (string) ($custom_media_row['label'] ?? 'Dodatkowe media'),
                    (string) ($custom_media_row['value'] ?? '')
                );
            }

            // FEATURES (exposure / view / layout / amenities / equipment)
            $rows_features = [];
            if (! empty($property_profile['exposure']) && is_array($property_profile['exposure'])) {
                $property_add_row($rows_features, 'Ekspozycja', $render_list($property_profile['exposure']));
            }
            if (! empty($property_profile['view']) && is_array($property_profile['view'])) {
                $property_add_row($rows_features, 'Widok', $render_list($property_profile['view']));
            }
            if (! empty($property_profile['layout']) && is_array($property_profile['layout'])) {
                $property_add_row($rows_features, 'Rozk&#322;ad', $render_list($property_profile['layout']));
            }
            foreach ($property_profile_custom_detail_rows as $custom_detail_row) {
                $property_add_row(
                    $rows_features,
                    (string) ($custom_detail_row['label'] ?? 'Pole dodatkowe'),
                    (string) ($custom_detail_row['value'] ?? '')
                );
            }
            if (! empty($property_profile['amenities']) && is_array($property_profile['amenities'])) {
                $property_add_row($rows_features, 'Udogodnienia', $render_list($property_profile['amenities']));
            }
            foreach ($property_profile_custom_amenity_rows as $custom_amenity_row) {
                $property_add_row(
                    $rows_features,
                    (string) ($custom_amenity_row['label'] ?? 'Dodatkowe udogodnienie'),
                    (string) ($custom_amenity_row['value'] ?? '')
                );
            }
            if (! empty($property_profile['equipment']) && is_array($property_profile['equipment'])) {
                $property_add_row($rows_features, 'Wyposa&#380;enie', $render_list($property_profile['equipment']));
            }
            foreach ($property_profile_custom_equipment_rows as $custom_equipment_row) {
                $property_add_row(
                    $rows_features,
                    (string) ($custom_equipment_row['label'] ?? 'Dodatkowe wyposa&#380;enie'),
                    (string) ($custom_equipment_row['value'] ?? '')
                );
            }

            // EXPORT & PORTALS
            $rows_export = [];
            $property_add_row($rows_export, 'Eksport WWW', ! empty($property_profile['export_www']) ? 'Tak' : '');
            $property_add_row($rows_export, 'Eksport Portale', ! empty($property_profile['export_portals']) ? 'Tak' : '');
            if ($property_portal_event !== '') {
                $property_add_row($rows_export, 'Portal &mdash; ostatnie zdarzenie', $format_portal_export_event($property_portal_event));
            }
            if ($property_portal_status !== '') {
                $property_add_row($rows_export, 'Portal &mdash; status', $format_portal_export_status($property_portal_status));
            }
            if ($property_portal_attempts > 0) {
                $property_add_row($rows_export, 'Portal &mdash; liczba pr&oacute;b', (string) $property_portal_attempts);
            }
            $property_add_row($rows_export, 'Portal &mdash; ostatnia aktualizacja', $property_portal_updated_at);
            $property_add_row($rows_export, 'Portal &mdash; b&#322;&#261;d', $property_portal_last_error);
            $property_add_row($rows_export, 'Utworzono', (string) ($property_profile['created_at'] ?? ''));

            // ---------- Hero gallery photo selection ----------
            $profile_gallery_photos = isset($property_profile_media['photos']) && is_array($property_profile_media['photos']) ? $property_profile_media['photos'] : [];
            $profile_gallery_resolved = [];
            foreach ($profile_gallery_photos as $photo_row) {
                if (! is_array($photo_row)) {
                    continue;
                }
                $photo_attachment_id = (int) ($photo_row['attachment_id'] ?? 0);
                $photo_url_full = $photo_attachment_id > 0 ? (string) wp_get_attachment_image_url($photo_attachment_id, 'large') : '';
                if ($photo_url_full === '') {
                    $photo_url_full = (string) ($photo_row['media_url'] ?? '');
                }
                $photo_url_thumb = $photo_attachment_id > 0 ? (string) wp_get_attachment_image_url($photo_attachment_id, 'medium') : '';
                if ($photo_url_thumb === '') {
                    $photo_url_thumb = $photo_url_full;
                }
                if ($photo_url_full === '') {
                    continue;
                }
                $profile_gallery_resolved[] = [
                    'full' => $photo_url_full,
                    'thumb' => $photo_url_thumb,
                    'is_primary' => ! empty($photo_row['is_primary']),
                ];
            }
            $profile_hero_photo = null;
            foreach ($profile_gallery_resolved as $resolved_photo) {
                if (! empty($resolved_photo['is_primary'])) {
                    $profile_hero_photo = $resolved_photo;
                    break;
                }
            }
            if ($profile_hero_photo === null && ! empty($profile_gallery_resolved)) {
                $profile_hero_photo = $profile_gallery_resolved[0];
            }
            $profile_gallery_count = count($profile_gallery_resolved);

            // ---------- Floor plan items resolved ----------
            $floor_plan_rows = isset($property_profile_media['floor_plans']) && is_array($property_profile_media['floor_plans']) ? $property_profile_media['floor_plans'] : [];
            $floor_plan_items = [];
            foreach ($floor_plan_rows as $floor_plan_index => $floor_plan_row) {
                if (! is_array($floor_plan_row)) {
                    continue;
                }
                $floor_plan_attachment_id = isset($floor_plan_row['attachment_id']) ? absint((string) $floor_plan_row['attachment_id']) : 0;
                $floor_plan_url = $floor_plan_attachment_id > 0 ? (string) wp_get_attachment_url($floor_plan_attachment_id) : '';
                $floor_plan_thumb = $floor_plan_attachment_id > 0 ? (string) wp_get_attachment_image_url($floor_plan_attachment_id, 'medium') : '';
                if ($floor_plan_url === '') {
                    $floor_plan_url = (string) ($floor_plan_row['media_url'] ?? '');
                }
                if ($floor_plan_thumb === '') {
                    $floor_plan_thumb = $floor_plan_url;
                }
                if ($floor_plan_thumb === '') {
                    continue;
                }
                $floor_plan_label = isset($floor_plan_row['label']) ? sanitize_text_field((string) $floor_plan_row['label']) : '';
                if ($floor_plan_label === '') {
                    $floor_plan_label = 'Poziom ' . (string) ($floor_plan_index + 1);
                }
                $floor_plan_items[] = [
                    'label' => $floor_plan_label,
                    'url' => $floor_plan_url !== '' ? $floor_plan_url : $floor_plan_thumb,
                    'thumb' => $floor_plan_thumb,
                ];
            }

            // ---------- Themed cards definition ----------
            $profile_themed_cards = [
                ['key' => 'location', 'title' => 'Lokalizacja', 'icon' => '&#128205;', 'rows' => $rows_location, 'class' => 'eocrm-prop-detail-card--location'],
                ['key' => 'dimensions', 'title' => 'Powierzchnia i pomieszczenia', 'icon' => '&#128208;', 'rows' => $rows_dimensions, 'class' => 'eocrm-prop-detail-card--dimensions'],
                ['key' => 'condition', 'title' => 'Stan i wyko&#324;czenie', 'icon' => '&#127968;', 'rows' => $rows_condition, 'class' => 'eocrm-prop-detail-card--condition'],
                ['key' => 'media', 'title' => 'Media i instalacje', 'icon' => '&#128268;', 'rows' => $rows_media_inst, 'class' => 'eocrm-prop-detail-card--media'],
                ['key' => 'features', 'title' => 'Cechy i atuty', 'icon' => '&#9989;', 'rows' => $rows_features, 'class' => 'eocrm-prop-detail-card--features'],
                ['key' => 'export', 'title' => 'Eksport i kana&#322;y', 'icon' => '&#127760;', 'rows' => $rows_export, 'class' => 'eocrm-prop-detail-card--export'],
            ];

            $property_quick_tags = isset($property_profile['tags']) && is_array($property_profile['tags']) ? $property_profile['tags'] : [];
            $property_quick_flag_options = [
                ['type' => 'tag', 'key' => 'nowa_oferta', 'label' => 'Nowa oferta'],
                ['type' => 'tag', 'key' => 'wylacznosc', 'label' => 'Wy&#322;&#261;czno&#347;&#263;'],
                ['type' => 'tag', 'key' => 'sprzedane', 'label' => 'Sprzedane'],
                ['type' => 'tag', 'key' => 'wynajete', 'label' => 'Wynaj&#281;te'],
                ['type' => 'tag', 'key' => 'nowa_cena', 'label' => 'Nowa cena'],
                ['type' => 'tag', 'key' => 'bez_prowizji', 'label' => 'Bez prowizji'],
                ['type' => 'tag', 'key' => 'oferta_mls', 'label' => 'Oferta MLS'],
                ['type' => 'tag', 'key' => 'premium', 'label' => 'Premium'],
                ['type' => 'export', 'key' => 'export_www', 'label' => 'Eksport WWW'],
                ['type' => 'export', 'key' => 'export_portals', 'label' => 'Eksport Portale'],
            ];

            $property_description_html = (string) ($property_profile['description'] ?? '');
            ?>

            <section class="eocrm-prop-detail" data-eocrm-prop-detail>

                <div class="eocrm-prop-detail-hero-row">
                    <?php if ($profile_hero_photo !== null) : ?>
                        <article class="eocrm-prop-detail-gallery eocrm-prop-detail-gallery--hero-only">
                            <a class="eocrm-prop-detail-gallery__hero" href="<?php echo esc_url((string) $profile_hero_photo['full']); ?>" target="_blank" rel="noopener noreferrer" title="Otw&oacute;rz w nowej karcie">
                                <img src="<?php echo esc_url((string) $profile_hero_photo['full']); ?>" alt="G&#322;&oacute;wne zdj&#281;cie oferty" loading="lazy">
                                <span class="eocrm-prop-detail-gallery__count" aria-hidden="true">
                                    <span class="eocrm-prop-detail-gallery__count-icon">&#128247;</span>
                                    <span><?php echo esc_html((string) $profile_gallery_count); ?> <?php echo esc_html($profile_gallery_count === 1 ? 'zdj&#281;cie' : 'zdj&#281;&#263;'); ?></span>
                                </span>
                            </a>
                        </article>
                    <?php else : ?>
                        <article class="eocrm-prop-detail-gallery eocrm-prop-detail-gallery--empty eocrm-prop-detail-gallery--hero-only">
                            <div class="eocrm-prop-detail-gallery__empty">
                                <span class="eocrm-prop-detail-gallery__empty-icon" aria-hidden="true">&#127968;</span>
                                <strong>Brak zdj&#281;&#263; oferty</strong>
                                <span>Dodaj zdj&#281;cia w trybie edycji nieruchomo&#347;ci.</span>
                            </div>
                        </article>
                    <?php endif; ?>

                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--flags eocrm-prop-detail-card--side">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128200;</span>
                            <h3>Szybka edycja</h3>
                            <p class="eocrm-prop-detail-card__hint">Zmie&#324; cen&#281; lub znaczniki, a nast&#281;pnie potwierd&#378; przyciskiem.</p>
                        </header>
                        <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => (int) ($property_profile['id'] ?? 0)], $current_url)); ?>" class="eocrm-property-flags-form">
                            <?php wp_nonce_field('eocrm_update_property_flags', 'eocrm_property_flags_nonce'); ?>
                            <input type="hidden" name="eocrm_action" value="update_property_flags">
                            <input type="hidden" name="property_id" value="<?php echo (int) ($property_profile['id'] ?? 0); ?>">
                            <div class="eocrm-property-quick-price">
                                <p class="eocrm-form-field">
                                    <label>Cena</label>
                                    <input type="text" name="quick_price" value="<?php echo esc_attr((string) ($property_profile['price'] ?? '')); ?>" inputmode="decimal" required>
                                </p>
                                <p class="eocrm-form-field">
                                    <label>Waluta</label>
                                    <?php $quick_price_currency = $normalize_property_currency($property_profile['price_currency'] ?? 'PLN'); ?>
                                    <select name="quick_price_currency" required>
                                        <?php foreach ($property_price_currency_options as $price_currency_option) : ?>
                                            <option value="<?php echo esc_attr($price_currency_option); ?>" <?php selected($price_currency_option, $quick_price_currency); ?>><?php echo esc_html($price_currency_option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                            </div>
                            <div class="eocrm-tag-toggle-grid">
                                <?php foreach ($property_quick_flag_options as $quick_flag) :
                                    $quick_type = (string) ($quick_flag['type'] ?? 'tag');
                                    $quick_key = (string) ($quick_flag['key'] ?? '');
                                    $quick_label = (string) ($quick_flag['label'] ?? $quick_key);
                                    if ($quick_key === '') {
                                        continue;
                                    }
                                    $quick_name = $quick_type === 'export' ? $quick_key : 'tags[]';
                                    $quick_value = $quick_type === 'export' ? '1' : $quick_key;
                                    $quick_checked = $quick_type === 'export'
                                        ? ! empty($property_profile[$quick_key])
                                        : in_array($quick_key, $property_quick_tags, true);
                                ?>
                                    <label class="eocrm-tag-toggle">
                                        <input type="checkbox" name="<?php echo esc_attr($quick_name); ?>" value="<?php echo esc_attr($quick_value); ?>" <?php checked($quick_checked); ?>>
                                        <span><?php echo wp_kses_post($quick_label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="eocrm-actions-row eocrm-property-flags-actions">
                                <button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz zmiany</button>
                            </p>
                        </form>
                    </article>
                </div>

                <div class="eocrm-prop-detail-grid">
                    <?php foreach ($profile_themed_cards as $themed_card) :
                        $card_rows = is_array($themed_card['rows'] ?? null) ? $themed_card['rows'] : [];
                        if (empty($card_rows)) {
                            continue;
                        }
                    ?>
                        <article class="eocrm-prop-detail-card <?php echo esc_attr((string) $themed_card['class']); ?>">
                            <header class="eocrm-prop-detail-card__head">
                                <span class="eocrm-prop-detail-card__icon" aria-hidden="true"><?php echo wp_kses_post((string) $themed_card['icon']); ?></span>
                                <h3><?php echo wp_kses_post((string) $themed_card['title']); ?></h3>
                            </header>
                            <dl class="eocrm-prop-detail-card__defs">
                                <?php foreach ($card_rows as $card_row_item) : ?>
                                    <div>
                                        <dt><?php echo esc_html((string) ($card_row_item['label'] ?? '')); ?></dt>
                                        <dd><?php echo wp_kses_post((string) ($card_row_item['value'] ?? '')); ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </div>

                <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full eocrm-prop-detail-card--description">
                    <header class="eocrm-prop-detail-card__head">
                        <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128221;</span>
                        <h3>Opis nieruchomo&#347;ci</h3>
                    </header>
                    <div class="eocrm-prop-detail-card__richtext">
                        <?php echo $property_description_html !== '' ? wp_kses_post(wpautop($property_description_html)) : '<p class="eocrm-muted">Brak opisu.</p>'; ?>
                    </div>
                </article>

                <?php
                $profile_gallery_extra_thumbs = [];
                foreach ($profile_gallery_resolved as $thumb_row_check) {
                    if ($thumb_row_check === $profile_hero_photo && ! empty($profile_hero_photo['is_primary'])) {
                        continue;
                    }
                    $profile_gallery_extra_thumbs[] = $thumb_row_check;
                }
                ?>
                <?php if (! empty($profile_gallery_extra_thumbs)) : ?>
                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full eocrm-prop-detail-card--gallery">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#127748;</span>
                            <h3>Galeria zdj&#281;&#263;</h3>
                            <p class="eocrm-prop-detail-card__hint">Pozosta&#322;e zdj&#281;cia oferty &mdash; klikni&#281;cie otwiera w nowej karcie.</p>
                        </header>
                        <div class="eocrm-prop-detail-gallery-grid">
                            <?php foreach ($profile_gallery_extra_thumbs as $thumb_row) : ?>
                                <a class="eocrm-prop-detail-gallery__thumb" href="<?php echo esc_url((string) $thumb_row['full']); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo esc_url((string) $thumb_row['thumb']); ?>" alt="" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if (! empty($floor_plan_items)) : ?>
                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128209;</span>
                            <h3>Rzuty</h3>
                        </header>
                        <div class="eocrm-prop-detail-floor-plans">
                            <?php foreach ($floor_plan_items as $floor_plan_item) : ?>
                                <a class="eocrm-prop-detail-floor-plan" href="<?php echo esc_url((string) ($floor_plan_item['url'] ?? '')); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="eocrm-prop-detail-floor-plan__thumb">
                                        <img src="<?php echo esc_url((string) ($floor_plan_item['thumb'] ?? '')); ?>" alt="<?php echo esc_attr((string) ($floor_plan_item['label'] ?? 'Rzut')); ?>" loading="lazy">
                                    </span>
                                    <span class="eocrm-prop-detail-floor-plan__label"><?php echo esc_html((string) ($floor_plan_item['label'] ?? 'Rzut')); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <div class="eocrm-prop-detail-related">
                    <article class="eocrm-prop-detail-card">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128101;</span>
                            <h3>Powi&#261;zani klienci</h3>
                        </header>
                        <?php if (empty($property_profile_clients)) : ?>
                            <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych klient&oacute;w.</p>
                        <?php else : ?>
                            <ul class="eocrm-prop-detail-related-list">
                                <?php foreach ($property_profile_clients as $client_row) :
                                    $client_link_id = (int) ($client_row['id'] ?? 0);
                                    $client_display_name = (string) ($client_row['display_name'] ?? 'Klient');
                                    $client_initial = $client_display_name !== '' ? mb_strtoupper(mb_substr($client_display_name, 0, 1)) : '?';
                                ?>
                                    <li>
                                        <a href="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'client_id' => $client_link_id], $current_url)); ?>">
                                            <span class="eocrm-prop-detail-related-list__avatar"><?php echo esc_html($client_initial); ?></span>
                                            <span class="eocrm-prop-detail-related-list__name"><?php echo esc_html($client_display_name); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>

                    <article class="eocrm-prop-detail-card">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128196;</span>
                            <h3>Umowa powi&#261;zana</h3>
                        </header>
                        <?php if (is_array($property_profile_agreement)) :
                            $linked_agreement_id = (int) ($property_profile_agreement['id'] ?? 0);
                            $linked_agreement_number = (string) ($property_profile_agreement['agreement_number'] ?? '');
                            $linked_agreement_tx = strtoupper((string) ($property_profile_agreement['transaction_type'] ?? ''));
                            $linked_agreement_tx_label = $profile_transaction_label_map[$linked_agreement_tx] ?? $linked_agreement_tx;
                            $linked_agreement_stage = (string) ($property_profile_agreement['current_stage'] ?? '');
                            $linked_agreement_finished = $is_agreement_stage_finished($linked_agreement_stage);
                        ?>
                            <div class="eocrm-prop-detail-agreement">
                                <div class="eocrm-prop-detail-agreement__head">
                                    <span class="eocrm-prop-detail-agreement__number"><?php echo esc_html($linked_agreement_number !== '' ? $linked_agreement_number : ('Umowa #' . $linked_agreement_id)); ?></span>
                                    <span class="eocrm-prop-detail-agreement__tx"><?php echo wp_kses_post($linked_agreement_tx_label); ?></span>
                                </div>
                                <p class="eocrm-prop-detail-agreement__stage <?php echo $linked_agreement_finished ? 'is-done' : 'is-progress'; ?>">
                                    <?php echo esc_html($linked_agreement_stage !== '' ? $linked_agreement_stage : ($linked_agreement_finished ? 'Zako&#324;czona' : 'W trakcie')); ?>
                                </p>
                                <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $linked_agreement_id], $current_url)); ?>">
                                    Otw&oacute;rz umow&#281; &rarr;
                                </a>
                            </div>
                        <?php else : ?>
                            <p class="eocrm-prop-detail-empty">Brak powi&#261;zanej umowy.</p>
                        <?php endif; ?>
                    </article>

                    <article class="eocrm-prop-detail-card">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#127971;</span>
                            <h3>Tagi i znaczniki</h3>
                        </header>
                        <?php if (! empty($property_profile['tags']) && is_array($property_profile['tags'])) : ?>
                            <div class="eocrm-prop-detail-tag-chips">
                                <?php foreach ($property_profile['tags'] as $tag_value) :
                                    $tag_text = trim((string) $tag_value);
                                    if ($tag_text === '') {
                                        continue;
                                    }
                                ?>
                                    <span class="eocrm-prop-detail-tag-chip"><?php echo esc_html(str_replace('_', ' ', $tag_text)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="eocrm-prop-detail-empty">Brak tag&oacute;w. U&#380;yj formularza poni&#380;ej, aby je doda&#263;.</p>
                        <?php endif; ?>
                    </article>
                </div>

            </section>

            <section class="eocrm-prop-detail-modal-host">

                <?php
                $property_pdf_url = add_query_arg(
                    [
                        'crm' => 'properties',
                        'property_id' => (int) ($property_profile['id'] ?? 0),
                        'eocrm_export' => 'pdf_property',
                        'eocrm_export_nonce' => wp_create_nonce('eocrm_export_pdf_property'),
                    ],
                    $current_url
                );
                ?>
                <div class="eocrm-modal eocrm-pdf-choice-modal" data-eocrm-pdf-choice-modal hidden>
                    <div class="eocrm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="eocrm-pdf-choice-title">
                        <button type="button" class="eocrm-modal__close" data-eocrm-pdf-choice-close aria-label="Zamknij">&times;</button>
                        <h3 id="eocrm-pdf-choice-title">Wygenerowac PDF oferty:</h3>
                        <p>Wybierz sekcje, ktore maja byc widoczne w PDF.</p>
                        <form class="eocrm-pdf-config-form" method="get" action="<?php echo esc_url($current_url); ?>">
                            <input type="hidden" name="crm" value="properties">
                            <input type="hidden" name="property_id" value="<?php echo (int) ($property_profile['id'] ?? 0); ?>">
                            <input type="hidden" name="eocrm_export" value="pdf_property">
                            <input type="hidden" name="eocrm_export_nonce" value="<?php echo esc_attr(wp_create_nonce('eocrm_export_pdf_property')); ?>">
                            <?php
                            $pdf_toggle_options = [
                                'pdf_contact' => 'Kontakt do opiekuna',
                                'pdf_atuty' => 'Atuty',
                                'pdf_udogodnienia' => 'Udogodnienia',
                                'pdf_metrics' => 'Podstawowe informacje',
                                'pdf_details' => 'Szczegoly oferty',
                                'pdf_description' => 'Opis oferty',
                                'pdf_plans' => 'Rzuty',
                                'pdf_qr' => 'QR kody',
                                'pdf_offer_link' => 'Link do oferty',
                            ];
                            ?>
                            <div class="eocrm-pdf-config-list">
                                <?php foreach ($pdf_toggle_options as $pdf_toggle_name => $pdf_toggle_label) : ?>
                                    <div class="eocrm-pdf-config-item">
                                        <span class="eocrm-pdf-config-item__label"><?php echo esc_html($pdf_toggle_label); ?></span>
                                        <span class="eocrm-pdf-config-item__switch">
                                            <label><input type="radio" name="<?php echo esc_attr($pdf_toggle_name); ?>" value="1" checked> TAK</label>
                                            <label><input type="radio" name="<?php echo esc_attr($pdf_toggle_name); ?>" value="0"> NIE</label>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="eocrm-modal__actions">
                                <button class="eocrm-btn eocrm-btn-primary" type="submit">Generuj PDF</button>
                                <button class="eocrm-btn" type="button" data-eocrm-pdf-choice-close>Anuluj</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            </section>
        <?php endif; ?>

        <?php
        $property_type_label_map = [
            'MIESZKANIE' => 'Mieszkanie',
            'DOM' => 'Dom',
            'DZIALKA' => 'Dzia&#322;ka',
            'LOKAL_HU' => 'Lokal H/U',
        ];
        $property_transaction_label_map = [
            'SPRZEDAZ' => 'Sprzeda&#380;',
            'KUPNO' => 'Kupno',
            'WYNAJEM' => 'Wynajem',
            'NAJEM' => 'Najem',
        ];
        $properties_total_count = is_array($properties_rows) ? count($properties_rows) : 0;
        ?>

        <section class="eocrm-prop-toolbar" aria-label="Filtry i wyszukiwanie nieruchomo&#347;ci">
            <form method="get" class="eocrm-prop-toolbar__search" role="search">
                <input type="hidden" name="crm" value="properties">
                <span class="eocrm-prop-toolbar__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                </span>
                <input
                    id="eocrm-properties-search"
                    data-live-filter
                    data-target-table="eocrm-properties-table"
                    name="q"
                    type="search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Szukaj po numerze oferty, adresie, cenie..."
                    aria-label="Szukaj nieruchomo&#347;ci"
                >
                <button class="eocrm-prop-toolbar__search-btn" type="submit">Szukaj</button>
            </form>

            <div class="eocrm-prop-filters" aria-label="Filtry">
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-property-filter="transaction" value="SPRZEDAZ">
                    <span>Sprzeda&#380;</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-property-filter="transaction" value="WYNAJEM">
                    <span>Wynajem</span>
                </label>
                <label class="eocrm-table-filter-toggle" title="Pokaz tylko nieruchomosci z aktywna umowa">
                    <input type="checkbox" data-eocrm-property-filter="active_agreement" value="1">
                    <span>Aktywne</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-property-filter="export_www" value="1">
                    <span>Publikacja WWW</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-property-filter="export_portals" value="1">
                    <span>Eksport portale</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-property-filter="sold_rented" value="1">
                    <span>Sprzedane / Wynaj&#281;te</span>
                </label>
            </div>
        </section>

        <div class="eocrm-prop-meta">
            <p class="eocrm-prop-meta__count">
                <strong><?php echo esc_html((string) $properties_total_count); ?></strong>
                <span><?php echo esc_html($properties_total_count === 1 ? 'nieruchomo&#347;&#263;' : 'nieruchomo&#347;ci'); ?></span>
                <?php if ($search !== '') : ?>
                    <em class="eocrm-prop-meta__hint">dla zapytania &laquo;<?php echo esc_html($search); ?>&raquo;</em>
                <?php endif; ?>
            </p>
        </div>

        <div class="eocrm-prop-list-wrap">
            <table class="eocrm-table eocrm-prop-list" id="eocrm-properties-table">
                <thead>
                    <tr>
                        <th class="eocrm-prop-col-main">Nieruchomo&#347;&#263;</th>
                        <th class="eocrm-prop-col-loc">Lokalizacja</th>
                        <th class="eocrm-prop-col-price">Cena</th>
                        <th class="eocrm-prop-col-metric">Metra&#380;</th>
                        <th class="eocrm-prop-col-metric">Pokoje</th>
                        <?php if ($show_owner_columns) : ?>
                            <th class="eocrm-prop-col-owner">Opiekun</th>
                        <?php endif; ?>
                        <th class="eocrm-prop-col-status">Status</th>
                        <th class="eocrm-prop-col-date">Aktualizacja</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($properties_rows)) : ?>
                        <tr class="eocrm-prop-empty-row"><td colspan="<?php echo esc_attr((string) ($show_owner_columns ? 8 : 7)); ?>">
                            <div class="eocrm-prop-empty">
                                <span class="eocrm-prop-empty__icon" aria-hidden="true">&#127968;</span>
                                <strong>Brak nieruchomo&#347;ci do wy&#347;wietlenia</strong>
                                <span>Spr&oacute;buj zmieni&#263; filtry lub doda&#263; now&#261; nieruchomo&#347;&#263;.</span>
                            </div>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ($properties_rows as $row) :
                            $owner = get_userdata((int) ($row['owner_user_id'] ?? 0));

                            $address_number = trim((string) ($row['building_no'] ?? ''));
                            $address_apartment = trim((string) ($row['apartment_no'] ?? ''));
                            if ($address_apartment !== '') {
                                $address_number .= ($address_number !== '' ? '/' : '') . $address_apartment;
                            }
                            $row_street_full = trim(((string) ($row['street'] ?? '')) . ' ' . $address_number);
                            $row_city = (string) ($row['city'] ?? '');
                            $row_district = (string) ($row['district'] ?? '');
                            $row_city_district = $format_address([$row_city, $row_district]);
                            $address_full = $format_address([$row_street_full, $row_city]);

                            $is_www_export = (int) ($row['export_www'] ?? 0) === 1;
                            $is_portal_export = (int) ($row['export_portals'] ?? 0) === 1;
                            $agreement_id_row = (int) ($row['agreement_id'] ?? 0);
                            $agreement_stage = (string) ($row['agreement_stage'] ?? '');
                            $agreement_is_active = (int) ($row['agreement_is_active'] ?? 0) === 1;
                            $is_agreement_active = $agreement_id_row > 0 && $agreement_is_active && ! $is_agreement_stage_finished($agreement_stage);

                            $property_transaction_type = strtoupper(trim((string) ($row['transaction_type'] ?? '')));
                            $property_type_code = strtoupper(trim((string) ($row['property_type'] ?? '')));
                            $property_type_label = $property_type_label_map[$property_type_code] ?? ($property_type_code !== '' ? ucfirst(strtolower($property_type_code)) : '-');
                            $property_transaction_label = $property_transaction_label_map[$property_transaction_type] ?? ($property_transaction_type !== '' ? ucfirst(strtolower($property_transaction_type)) : '');

                            $is_sold = (int) ($row['is_sold'] ?? 0) === 1;
                            $is_rented = (int) ($row['is_rented'] ?? 0) === 1;
                            $is_sold_or_rented = $is_sold || $is_rented;

                            $row_status_label = 'Aktywna';
                            $row_status_class = 'is-active';
                            if ($is_sold) {
                                $row_status_label = 'Sprzedane';
                                $row_status_class = 'is-sold';
                            } elseif ($is_rented) {
                                $row_status_label = 'Wynaj&#281;te';
                                $row_status_class = 'is-rented';
                            } elseif (! empty($row['is_premium'])) {
                                $row_status_label = 'Premium';
                                $row_status_class = 'is-premium';
                            } elseif (! empty($row['is_exclusive'])) {
                                $row_status_label = 'Wy&#322;&#261;czno&#347;&#263;';
                                $row_status_class = 'is-exclusive';
                            }

                            $row_property_url = add_query_arg(['crm' => 'properties', 'property_id' => (int) $row['id']], $current_url);
                            $row_thumb = (string) ($row['primary_photo_url'] ?? '');
                            $row_offer_number = (string) ($row['offer_number'] ?? '');
                            $row_price_str = $format_property_price($row['price'] ?? null, $row['price_currency'] ?? 'PLN');
                            $row_price_m2_str = $format_property_price_per_m2($row['price_per_m2'] ?? null, $row['price_currency'] ?? 'PLN');
                            $row_area = $row['area'] ?? '';
                            $row_rooms = $row['rooms'] ?? '';
                            $row_floor_no = isset($row['floor_no']) && $row['floor_no'] !== null && $row['floor_no'] !== '' ? (int) $row['floor_no'] : null;
                            $row_floors_total = isset($row['floors_total']) && $row['floors_total'] !== null && $row['floors_total'] !== '' ? (int) $row['floors_total'] : null;
                            $row_updated_at = (string) ($row['updated_at'] ?? '');

                            $owner_display_name = $owner instanceof WP_User ? (string) $owner->display_name : '-';
                            $owner_avatar = $owner instanceof WP_User ? $resolve_owner_photo_url((int) $owner->ID, 'thumbnail') : '';
                            $owner_initial = '';
                            if ($owner instanceof WP_User) {
                                $owner_initial = mb_strtoupper(mb_substr($owner_display_name, 0, 1));
                            }
                        ?>
                            <tr
                                class="eocrm-prop-row"
                                data-eocrm-property-row
                                data-transaction="<?php echo esc_attr($property_transaction_type); ?>"
                                data-export-www="<?php echo esc_attr($is_www_export ? '1' : '0'); ?>"
                                data-export-portals="<?php echo esc_attr($is_portal_export ? '1' : '0'); ?>"
                                data-sold-rented="<?php echo esc_attr($is_sold_or_rented ? '1' : '0'); ?>"
                                data-active-agreement="<?php echo esc_attr($is_agreement_active ? '1' : '0'); ?>"
                            >
                                <td class="eocrm-prop-cell-main">
                                    <a class="eocrm-prop-link" href="<?php echo esc_url($row_property_url); ?>">
                                        <span class="eocrm-prop-thumb" aria-hidden="true">
                                            <?php if ($row_thumb !== '') : ?>
                                                <img src="<?php echo esc_url($row_thumb); ?>" alt="" loading="lazy">
                                            <?php else : ?>
                                                <span class="eocrm-prop-thumb__placeholder">&#127968;</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="eocrm-prop-titlewrap">
                                            <span class="eocrm-prop-title">
                                                <?php echo esc_html($row_offer_number !== '' ? $row_offer_number : ('#' . (int) $row['id'])); ?>
                                            </span>
                                            <span class="eocrm-prop-subtitle">
                                                <?php echo wp_kses_post($property_type_label); ?>
                                                <?php if ($property_transaction_label !== '') : ?>
                                                    &middot; <?php echo wp_kses_post($property_transaction_label); ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </a>
                                </td>
                                <td class="eocrm-prop-cell-loc">
                                    <?php if ($row_street_full !== '') : ?>
                                        <span class="eocrm-prop-loc-primary"><?php echo esc_html($row_street_full); ?></span>
                                        <?php if ($row_city_district !== '') : ?>
                                            <span class="eocrm-prop-loc-secondary"><?php echo esc_html($row_city_district); ?></span>
                                        <?php endif; ?>
                                    <?php elseif ($row_city !== '' || $row_district !== '') : ?>
                                        <span class="eocrm-prop-loc-primary"><?php echo esc_html($row_city !== '' ? $row_city : $row_district); ?></span>
                                        <?php if ($row_city !== '' && $row_district !== '') : ?>
                                            <span class="eocrm-prop-loc-secondary"><?php echo esc_html($row_district); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="eocrm-prop-loc-primary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-price">
                                    <span class="eocrm-prop-price"><?php echo esc_html($row_price_str); ?></span>
                                    <?php if ($row_price_m2_str !== '-' && $row_price_m2_str !== '') : ?>
                                        <span class="eocrm-prop-price-m2"><?php echo esc_html($row_price_m2_str); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <?php if ($row_area !== '' && $row_area !== null) : ?>
                                        <span class="eocrm-prop-metric"><?php echo esc_html(rtrim(rtrim(number_format((float) $row_area, 2, '.', ''), '0'), '.')); ?> m<sup>2</sup></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-metric eocrm-prop-metric--empty">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <?php if ($row_rooms !== '' && $row_rooms !== null) : ?>
                                        <span class="eocrm-prop-metric"><?php echo esc_html((string) (int) $row_rooms); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-metric eocrm-prop-metric--empty">-</span>
                                    <?php endif; ?>
                                    <?php if ($row_floor_no !== null && $row_floors_total !== null) : ?>
                                        <span class="eocrm-prop-metric-sub">pi&#281;tro <?php echo esc_html((string) $row_floor_no . '/' . (string) $row_floors_total); ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($show_owner_columns) : ?>
                                    <td class="eocrm-prop-cell-owner">
                                        <span class="eocrm-prop-owner">
                                            <span class="eocrm-prop-owner__avatar" aria-hidden="true">
                                                <?php if ($owner_avatar !== '') : ?>
                                                    <img src="<?php echo esc_url($owner_avatar); ?>" alt="" loading="lazy">
                                                <?php else : ?>
                                                    <span class="eocrm-prop-owner__initial"><?php echo esc_html($owner_initial !== '' ? $owner_initial : '?'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="eocrm-prop-owner__name"><?php echo esc_html($owner_display_name); ?></span>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="eocrm-prop-cell-status">
                                    <?php
                                    $agreement_status_title = $agreement_id_row > 0
                                        ? ($is_agreement_active ? 'Umowa: aktywna' : 'Umowa: zakonczona')
                                        : 'Umowa: brak powiazania';
                                    $is_agreement_indicator_active = $agreement_id_row > 0 && $is_agreement_active;
                                    ?>
                                    <span class="eocrm-prop-status-icons">
                                        <?php if ($is_www_export) : ?>
                                            <span class="eocrm-prop-status-icon is-on" title="Eksport WWW: aktywny" aria-label="Eksport WWW: aktywny">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10"></path><path d="M12 2a15.3 15.3 0 0 0-4 10 15.3 15.3 0 0 0 4 10"></path></svg>
                                            </span>
                                        <?php else : ?>
                                            <span class="eocrm-prop-status-icon is-off" title="Eksport WWW: nieaktywny" aria-label="Eksport WWW: nieaktywny">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10"></path><path d="M12 2a15.3 15.3 0 0 0-4 10 15.3 15.3 0 0 0 4 10"></path><path d="m4.93 4.93 14.14 14.14"></path></svg>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($is_portal_export) : ?>
                                            <span class="eocrm-prop-status-icon is-on" title="Eksport na Portale: aktywny" aria-label="Eksport na Portale: aktywny">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"></path><path d="m2 17 10 5 10-5"></path><path d="m2 12 10 5 10-5"></path></svg>
                                            </span>
                                        <?php else : ?>
                                            <span class="eocrm-prop-status-icon is-off" title="Eksport na Portale: nieaktywny" aria-label="Eksport na Portale: nieaktywny">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"></path><path d="m2 17 10 5 10-5"></path><path d="m2 12 10 5 10-5"></path><path d="m4.93 4.93 14.14 14.14"></path></svg>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($is_agreement_indicator_active) : ?>
                                            <span class="eocrm-prop-status-icon is-on" title="<?php echo esc_attr($agreement_status_title); ?>" aria-label="<?php echo esc_attr($agreement_status_title); ?>">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path></svg>
                                            </span>
                                        <?php else : ?>
                                            <span class="eocrm-prop-status-icon is-off" title="<?php echo esc_attr($agreement_status_title); ?>" aria-label="<?php echo esc_attr($agreement_status_title); ?>">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path><path d="m4.93 4.93 14.14 14.14"></path></svg>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="eocrm-prop-cell-date">
                                    <?php if ($row_updated_at !== '' && $row_updated_at !== '0000-00-00 00:00:00') :
                                        $row_updated_ts = strtotime($row_updated_at);
                                    ?>
                                        <span class="eocrm-prop-date-main"><?php echo esc_html(date_i18n('d.m.Y', $row_updated_ts)); ?></span>
                                        <span class="eocrm-prop-date-sub"><?php echo esc_html(date_i18n('H:i', $row_updated_ts)); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-date-main">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'searches') : ?>
        <?php if ($crm_notice === 'search_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Poszukiwanie zostalo dodane.'); ?></div>
        <?php elseif ($crm_notice === 'search_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Poszukiwanie zostalo zaktualizowane.'); ?></div>
        <?php elseif ($crm_notice === 'search_deleted') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Poszukiwanie zostalo usuniete.'); ?></div>
        <?php elseif ($crm_notice === 'search_error') : ?>
            <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac poszukiwania.'); ?></div>
        <?php endif; ?>

        <section class="eocrm-actions-row">
            <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'mode' => 'new-search'], $current_url)); ?>">Dodaj poszukiwanie</a>
            <?php if ($searches_mode !== '') : ?>
                <?php
                $close_search_args = ['crm' => 'searches'];
                if ($searches_mode === 'edit-search' && is_array($search_profile)) {
                    $close_search_args['search_id'] = (int) ($search_profile['id'] ?? 0);
                }
                ?>
                <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg($close_search_args, $current_url)); ?>">Zamknij formularz</a>
            <?php endif; ?>
        </section>

        <?php if ($searches_mode === 'new-search') : ?>
            <section class="eocrm-card">
                <h3>Dodawanie poszukiwania</h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'mode' => 'new-search'], $current_url)); ?>" data-eocrm-search-form>
                    <?php wp_nonce_field('eocrm_create_search', 'eocrm_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="create_search">

                    <h4>Powi&#261;zanie z umowa</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Umowa (KUPNO / NAJEM)</label>
                            <select name="agreement_id" data-eocrm-search-agreement-select required>
                                <option value="">Wybierz umowe</option>
                                <?php foreach ($search_agreement_options as $search_agreement_row) : ?>
                                    <?php $search_agreement_id = (int) ($search_agreement_row['id'] ?? 0); ?>
                                    <option value="<?php echo esc_attr((string) $search_agreement_id); ?>" data-transaction="<?php echo esc_attr((string) ($search_agreement_row['transaction_type'] ?? '')); ?>" <?php selected($prefill_agreement_id, $search_agreement_id); ?>>
                                        <?php echo esc_html((string) ($search_agreement_row['agreement_number'] ?? '') . ' [' . (string) ($search_agreement_row['transaction_type'] ?? '') . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Typ transakcji (z umowy)</label>
                            <input type="text" data-eocrm-search-transaction-display value="" readonly>
                        </p>
                    </div>

                    <h4>Kryteria podstawowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Numer poszukiwania</label><input type="text" name="search_number" value="<?php echo esc_attr($default_search_number); ?>" required></p>
                        <div class="eocrm-form-field eocrm-form-field--wide">
                            <label>Rodzaj nieruchomo&#347;ci</label>
                            <div class="eocrm-tag-toggle-grid eocrm-search-property-types">
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="MIESZKANIE" data-eocrm-search-property-type><span>Mieszkanie</span></label>
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="DOM" data-eocrm-search-property-type><span>Dom</span></label>
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="DZIALKA" data-eocrm-search-property-type><span>Dzia&#322;ka</span></label>
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="LOKAL_HU" data-eocrm-search-property-type><span>Lokal H/U</span></label>
                            </div>
                        </div>
                        <p class="eocrm-form-field"><label>Budzet od</label><input type="text" name="budget_from" data-eocrm-range-from="budget"></p>
                        <p class="eocrm-form-field"><label>Budzet do</label><input type="text" name="budget_to" data-eocrm-range-to="budget"></p>
                        <p class="eocrm-form-field"><label>Metra&#380; od (m2)</label><input type="text" name="area_from" data-eocrm-range-from="area"></p>
                        <p class="eocrm-form-field"><label>Metra&#380; do (m2)</label><input type="text" name="area_to" data-eocrm-range-to="area"></p>
                        <p class="eocrm-form-field"><label>Pokoje od</label><input type="number" min="0" name="rooms_from" data-eocrm-range-from="rooms"></p>
                        <p class="eocrm-form-field"><label>Pokoje do</label><input type="number" min="0" name="rooms_to" data-eocrm-range-to="rooms"></p>
                        <p class="eocrm-form-field"><label>Pi&#281;tro od</label><input type="number" name="floor_from" data-eocrm-range-from="floor"></p>
                        <p class="eocrm-form-field"><label>Pi&#281;tro do</label><input type="number" name="floor_to" data-eocrm-range-to="floor"></p>
                        <p class="eocrm-form-field"><label>Lokalizacja</label><input type="text" name="location_text" placeholder="Miasto, dzielnica, obszar" required></p>
                    </div>

                    <h4>Opis poszukiwania</h4>
                    <?php if (function_exists('wp_editor')) : ?>
                        <?php wp_editor('', 'eocrm_search_description', ['textarea_name' => 'description', 'textarea_rows' => 6, 'media_buttons' => false, 'teeny' => true]); ?>
                    <?php else : ?>
                        <textarea class="large-text" rows="6" name="description"></textarea>
                    <?php endif; ?>

                    <h4>Budynek i uk&#322;ad</h4>
                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <p class="eocrm-form-field"><label>Stan wyko&#324;czenia</label>
                            <select name="building_finish">
                                <option value="">Nie okre&#347;lono</option>
                                <?php foreach ($building_finish_options as $building_finish_value => $building_finish_label) : ?>
                                    <option value="<?php echo esc_attr($building_finish_value); ?>"><?php echo wp_kses_post($building_finish_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kuchnia</label>
                            <select name="kitchen_type">
                                <option value="">Nie okre&#347;lono</option>
                                <?php foreach ($kitchen_type_options as $kitchen_type_value => $kitchen_type_label) : ?>
                                    <option value="<?php echo esc_attr($kitchen_type_value); ?>"><?php echo wp_kses_post($kitchen_type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Poddasze</label>
                            <select name="attic">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Wielopoziomowe</label>
                            <select name="multi_level">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <div class="eocrm-form-field">
                            <label>Ekspozycja</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="exposure[]" value="polnoc">P&oacute;&#322;noc</label>
                                <label><input type="checkbox" name="exposure[]" value="poludnie">Po&#322;udnie</label>
                                <label><input type="checkbox" name="exposure[]" value="wschod">Wsch&oacute;d</label>
                                <label><input type="checkbox" name="exposure[]" value="zachod">Zach&#243;d</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Widok</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="view[]" value="miasto">Miasto</label>
                                <label><input type="checkbox" name="view[]" value="zielec">Ziele&#324;</label>
                                <label><input type="checkbox" name="view[]" value="park">Park</label>
                                <label><input type="checkbox" name="view[]" value="podworko">Podw&#243;rko</label>
                                <label><input type="checkbox" name="view[]" value="ulica">Ulica</label>
                                <label><input type="checkbox" name="view[]" value="panorama">Panorama</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Rozk&#322;ad</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="layout[]" value="oddzielne_pokoje">Rozk&#322;adowe</label>
                                <label><input type="checkbox" name="layout[]" value="salon_z_aneksem">Salon z aneksem</label>
                                <label><input type="checkbox" name="layout[]" value="dwustronne">Dwustronne</label>
                                <label><input type="checkbox" name="layout[]" value="narozne">Naro&#380;ne</label>
                            </div>
                        </div>
                    </div>

                    <h4>Media</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ogrzewanie</label>
                            <select name="heating">
                                <option value="">Dowolnie</option>
                                <option value="miejskie">Miejskie</option>
                                <option value="gazowe">Gazowe</option>
                                <option value="elektryczne">Elektryczne</option>
                                <option value="podlogowe">Pod&#322;ogowe</option>
                                <option value="inne">Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Woda</label>
                            <select name="water">
                                <option value="">Dowolnie</option>
                                <option value="miejska">Miejska</option>
                                <option value="studnia">Studnia</option>
                                <option value="inne">Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kanalizacja</label>
                            <select name="sewage">
                                <option value="">Dowolnie</option>
                                <option value="miejska">Miejska</option>
                                <option value="szambo">Szambo</option>
                                <option value="oczyszczalnia">Oczyszczalnia</option>
                                <option value="inne">Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Gaz</label>
                            <select name="gas">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                    </div>

                    <h4>Udogodnienia</h4>
                    <div class="eocrm-checkbox-list eocrm-search-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="winda">Winda</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="klimatyzacja">Klimatyzacja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="monitoring">Monitoring/Ochrona</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="recepcja">Recepcja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="teren_zamkniety">Teren zamkni&#281;ty</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="domofon">Domofon</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="garaz" data-eocrm-search-amenity-toggle="garaz">Gara&#380;</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="miejsce_postojowe" data-eocrm-search-amenity-toggle="miejsce_postojowe">Miejsce postojowe</label>
                    </div>

                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <p class="eocrm-form-field is-hidden" data-eocrm-search-amenity-count="garaz"><label>Liczba miejsc w gara&#380;u</label><input type="number" min="0" name="amenity_garage_count" disabled></p>
                        <p class="eocrm-form-field is-hidden" data-eocrm-search-amenity-count="miejsce_postojowe"><label>Liczba miejsc postojowych</label><input type="number" min="0" name="amenity_parking_space_count" disabled></p>
                        <p class="eocrm-form-field"><label>Umeblowanie</label>
                            <select name="furnished_state">
                                <option value="">Dowolnie</option>
                                <option value="tak">Tak</option>
                                <option value="nie">Nie</option>
                                <option value="czesciowe">Cz&#281;&#347;ciowe</option>
                            </select>
                        </p>
                    </div>

                    <h4>Wyposa&#380;enie</h4>
                    <div class="eocrm-checkbox-list eocrm-search-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="pralka">Pralka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="zmywarka">Zmywarka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="lodowka">Lod&#243;wka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="kuchenka">Kuchenka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="piekarnik">Piekarnik</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="telewizor">Telewizor</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="mikrofala">Mikrofala</label>
                    </div>

                    <h4>Powierzchnie dodatkowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Balkon</label>
                            <select name="balcony_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="balcony">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-balcony is-hidden"><label>Min. pow. balkonu (m2)</label><input type="text" name="balcony_area_min"></p>
                        <p class="eocrm-form-field"><label>Taras</label>
                            <select name="terrace_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="terrace">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-terrace is-hidden"><label>Min. pow. tarasu (m2)</label><input type="text" name="terrace_area_min"></p>
                        <p class="eocrm-form-field"><label>Piwnica</label>
                            <select name="basement_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="basement">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-basement is-hidden"><label>Min. pow. piwnicy (m2)</label><input type="text" name="basement_area_min"></p>
                        <p class="eocrm-form-field"><label>Kom&#243;rka lokatorska</label>
                            <select name="storage_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="storage">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-storage is-hidden"><label>Min. pow. kom&#243;rki (m2)</label><input type="text" name="storage_area_min"></p>
                        <p class="eocrm-form-field"><label>Ogr&#243;dek</label>
                            <select name="garden_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="garden">
                                <option value="">Dowolnie</option>
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-garden is-hidden"><label>Min. pow. ogrodka (m2)</label><input type="text" name="garden_area_min"></p>
                    </div>

                    <p><button class="eocrm-btn eocrm-btn-primary" type="submit">Dodaj poszukiwanie</button></p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($searches_mode === 'edit-search' && is_array($search_profile)) : ?>
            <?php
            $edit_search_criteria = isset($search_profile['criteria']) && is_array($search_profile['criteria']) ? $search_profile['criteria'] : [];
            $edit_search_extra = isset($edit_search_criteria['extra_areas']) && is_array($edit_search_criteria['extra_areas']) ? $edit_search_criteria['extra_areas'] : [];
            $edit_search_attic_val = array_key_exists('attic', $edit_search_criteria) ? (! empty($edit_search_criteria['attic']) ? '1' : '0') : '';
            $edit_search_multi_level_val = array_key_exists('multi_level', $edit_search_criteria) ? (! empty($edit_search_criteria['multi_level']) ? '1' : '0') : '';
            $edit_search_gas_val = array_key_exists('gas', $edit_search_criteria) ? (! empty($edit_search_criteria['gas']) ? '1' : '0') : '';
            $edit_search_kitchen_type = (string) ($edit_search_criteria['kitchen_type'] ?? '');
            if ($edit_search_kitchen_type === 'Z salonem') {
                $edit_search_kitchen_type = 'Polotwarta';
            }
            $edit_search_balcony_has = (isset($edit_search_extra['balcony']) && array_key_exists('has', (array) $edit_search_extra['balcony'])) ? (! empty($edit_search_extra['balcony']['has']) ? '1' : '0') : '';
            $edit_search_terrace_has = (isset($edit_search_extra['terrace']) && array_key_exists('has', (array) $edit_search_extra['terrace'])) ? (! empty($edit_search_extra['terrace']['has']) ? '1' : '0') : '';
            $edit_search_basement_has = (isset($edit_search_extra['basement']) && array_key_exists('has', (array) $edit_search_extra['basement'])) ? (! empty($edit_search_extra['basement']['has']) ? '1' : '0') : '';
            $edit_search_storage_has = (isset($edit_search_extra['storage']) && array_key_exists('has', (array) $edit_search_extra['storage'])) ? (! empty($edit_search_extra['storage']['has']) ? '1' : '0') : '';
            $edit_search_garden_has = (isset($edit_search_extra['garden']) && array_key_exists('has', (array) $edit_search_extra['garden'])) ? (! empty($edit_search_extra['garden']['has']) ? '1' : '0') : '';
            $edit_search_property_types = array_values(array_filter(array_map(static function ($value): string {
                return strtoupper(trim((string) $value));
            }, explode(',', (string) ($search_profile['property_type'] ?? '')))));
            $edit_search_amenity_counts = isset($edit_search_criteria['amenity_counts']) && is_array($edit_search_criteria['amenity_counts']) ? $edit_search_criteria['amenity_counts'] : [];
            ?>
            <section class="eocrm-card">
                <h3>Edycja poszukiwania</h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'mode' => 'edit-search', 'search_id' => (int) ($search_profile['id'] ?? 0)], $current_url)); ?>" data-eocrm-search-form>
                    <?php wp_nonce_field('eocrm_update_search', 'eocrm_search_update_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="update_search">
                    <input type="hidden" name="search_id" value="<?php echo (int) ($search_profile['id'] ?? 0); ?>">

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Numer poszukiwania</label><input type="text" value="<?php echo esc_attr((string) ($search_profile['search_number'] ?? '')); ?>" readonly></p>
                        <p class="eocrm-form-field"><label>Typ transakcji</label><input type="text" data-eocrm-search-transaction-display value="<?php echo esc_attr((string) ($search_profile['transaction_type'] ?? '')); ?>" readonly></p>
                        <div class="eocrm-form-field eocrm-form-field--wide">
                            <label>Rodzaj nieruchomo&#347;ci</label>
                            <div class="eocrm-tag-toggle-grid eocrm-search-property-types">
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="MIESZKANIE" data-eocrm-search-property-type <?php checked(in_array('MIESZKANIE', $edit_search_property_types, true)); ?>><span>Mieszkanie</span></label>
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="DOM" data-eocrm-search-property-type <?php checked(in_array('DOM', $edit_search_property_types, true)); ?>><span>Dom</span></label>
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="DZIALKA" data-eocrm-search-property-type <?php checked(in_array('DZIALKA', $edit_search_property_types, true)); ?>><span>Dzia&#322;ka</span></label>
                                <label class="eocrm-tag-toggle"><input type="checkbox" name="property_types[]" value="LOKAL_HU" data-eocrm-search-property-type <?php checked(in_array('LOKAL_HU', $edit_search_property_types, true)); ?>><span>Lokal H/U</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Budzet od</label><input type="text" name="budget_from" data-eocrm-range-from="budget" value="<?php echo esc_attr((string) ($search_profile['budget_from'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Budzet do</label><input type="text" name="budget_to" data-eocrm-range-to="budget" value="<?php echo esc_attr((string) ($search_profile['budget_to'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Metra&#380; od (m2)</label><input type="text" name="area_from" data-eocrm-range-from="area" value="<?php echo esc_attr((string) ($search_profile['area_from'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Metra&#380; do (m2)</label><input type="text" name="area_to" data-eocrm-range-to="area" value="<?php echo esc_attr((string) ($search_profile['area_to'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Pokoje od</label><input type="number" min="0" name="rooms_from" data-eocrm-range-from="rooms" value="<?php echo esc_attr((string) ($search_profile['rooms_from'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Pokoje do</label><input type="number" min="0" name="rooms_to" data-eocrm-range-to="rooms" value="<?php echo esc_attr((string) ($search_profile['rooms_to'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Pi&#281;tro od</label><input type="number" name="floor_from" data-eocrm-range-from="floor" value="<?php echo esc_attr((string) ($search_profile['floor_from'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Pi&#281;tro do</label><input type="number" name="floor_to" data-eocrm-range-to="floor" value="<?php echo esc_attr((string) ($search_profile['floor_to'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Lokalizacja</label><input type="text" name="location_text" value="<?php echo esc_attr((string) ($search_profile['location_text'] ?? '')); ?>" required></p>
                    </div>

                    <h4>Opis poszukiwania</h4>
                    <?php if (function_exists('wp_editor')) : ?>
                        <?php wp_editor((string) ($search_profile['description'] ?? ''), 'eocrm_search_description_edit_' . (int) ($search_profile['id'] ?? 0), ['textarea_name' => 'description', 'textarea_rows' => 6, 'media_buttons' => false, 'teeny' => true]); ?>
                    <?php else : ?>
                        <textarea class="large-text" rows="6" name="description"><?php echo esc_textarea((string) ($search_profile['description'] ?? '')); ?></textarea>
                    <?php endif; ?>

                    <h4>Budynek i uk&#322;ad</h4>
                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <p class="eocrm-form-field"><label>Stan wyko&#324;czenia</label>
                            <select name="building_finish">
                                <option value="">Nie okre&#347;lono</option>
                                <?php foreach ($building_finish_options as $building_finish_value => $building_finish_label) : ?>
                                    <option value="<?php echo esc_attr($building_finish_value); ?>" <?php selected((string) ($edit_search_criteria['building_finish'] ?? ''), $building_finish_value); ?>><?php echo wp_kses_post($building_finish_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kuchnia</label>
                            <select name="kitchen_type">
                                <option value="">Nie okre&#347;lono</option>
                                <?php foreach ($kitchen_type_options as $kitchen_type_value => $kitchen_type_label) : ?>
                                    <option value="<?php echo esc_attr($kitchen_type_value); ?>" <?php selected($edit_search_kitchen_type, $kitchen_type_value); ?>><?php echo wp_kses_post($kitchen_type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Poddasze</label>
                            <select name="attic">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_attic_val, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_attic_val, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Wielopoziomowe</label>
                            <select name="multi_level">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_multi_level_val, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_multi_level_val, '0'); ?>>Nie</option>
                            </select>
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <div class="eocrm-form-field">
                            <label>Ekspozycja</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="exposure[]" value="polnoc" <?php checked(in_array('polnoc', (array) ($edit_search_criteria['exposure'] ?? []), true)); ?>>P&oacute;&#322;noc</label>
                                <label><input type="checkbox" name="exposure[]" value="poludnie" <?php checked(in_array('poludnie', (array) ($edit_search_criteria['exposure'] ?? []), true)); ?>>Po&#322;udnie</label>
                                <label><input type="checkbox" name="exposure[]" value="wschod" <?php checked(in_array('wschod', (array) ($edit_search_criteria['exposure'] ?? []), true)); ?>>Wsch&oacute;d</label>
                                <label><input type="checkbox" name="exposure[]" value="zachod" <?php checked(in_array('zachod', (array) ($edit_search_criteria['exposure'] ?? []), true)); ?>>Zach&#243;d</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Widok</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="view[]" value="miasto" <?php checked(in_array('miasto', (array) ($edit_search_criteria['view'] ?? []), true)); ?>>Miasto</label>
                                <label><input type="checkbox" name="view[]" value="zielec" <?php checked(in_array('zielec', (array) ($edit_search_criteria['view'] ?? []), true)); ?>>Ziele&#324;</label>
                                <label><input type="checkbox" name="view[]" value="park" <?php checked(in_array('park', (array) ($edit_search_criteria['view'] ?? []), true)); ?>>Park</label>
                                <label><input type="checkbox" name="view[]" value="podworko" <?php checked(in_array('podworko', (array) ($edit_search_criteria['view'] ?? []), true)); ?>>Podw&#243;rko</label>
                                <label><input type="checkbox" name="view[]" value="ulica" <?php checked(in_array('ulica', (array) ($edit_search_criteria['view'] ?? []), true)); ?>>Ulica</label>
                                <label><input type="checkbox" name="view[]" value="panorama" <?php checked(in_array('panorama', (array) ($edit_search_criteria['view'] ?? []), true)); ?>>Panorama</label>
                            </div>
                        </div>
                        <div class="eocrm-form-field">
                            <label>Rozk&#322;ad</label>
                            <div class="eocrm-inline-checkboxes">
                                <label><input type="checkbox" name="layout[]" value="oddzielne_pokoje" <?php checked(in_array('oddzielne_pokoje', (array) ($edit_search_criteria['layout'] ?? []), true)); ?>>Rozk&#322;adowe</label>
                                <label><input type="checkbox" name="layout[]" value="salon_z_aneksem" <?php checked(in_array('salon_z_aneksem', (array) ($edit_search_criteria['layout'] ?? []), true)); ?>>Salon z aneksem</label>
                                <label><input type="checkbox" name="layout[]" value="dwustronne" <?php checked(in_array('dwustronne', (array) ($edit_search_criteria['layout'] ?? []), true)); ?>>Dwustronne</label>
                                <label><input type="checkbox" name="layout[]" value="narozne" <?php checked(in_array('narozne', (array) ($edit_search_criteria['layout'] ?? []), true)); ?>>Naro&#380;ne</label>
                            </div>
                        </div>
                    </div>

                    <h4>Media</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ogrzewanie</label>
                            <select name="heating">
                                <option value="">Dowolnie</option>
                                <option value="miejskie" <?php selected((string) ($edit_search_criteria['heating'] ?? ''), 'miejskie'); ?>>Miejskie</option>
                                <option value="gazowe" <?php selected((string) ($edit_search_criteria['heating'] ?? ''), 'gazowe'); ?>>Gazowe</option>
                                <option value="elektryczne" <?php selected((string) ($edit_search_criteria['heating'] ?? ''), 'elektryczne'); ?>>Elektryczne</option>
                                <option value="podlogowe" <?php selected((string) ($edit_search_criteria['heating'] ?? ''), 'podlogowe'); ?>>Pod&#322;ogowe</option>
                                <option value="inne" <?php selected((string) ($edit_search_criteria['heating'] ?? ''), 'inne'); ?>>Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Woda</label>
                            <select name="water">
                                <option value="">Dowolnie</option>
                                <option value="miejska" <?php selected((string) ($edit_search_criteria['water'] ?? ''), 'miejska'); ?>>Miejska</option>
                                <option value="studnia" <?php selected((string) ($edit_search_criteria['water'] ?? ''), 'studnia'); ?>>Studnia</option>
                                <option value="inne" <?php selected((string) ($edit_search_criteria['water'] ?? ''), 'inne'); ?>>Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Kanalizacja</label>
                            <select name="sewage">
                                <option value="">Dowolnie</option>
                                <option value="miejska" <?php selected((string) ($edit_search_criteria['sewage'] ?? ''), 'miejska'); ?>>Miejska</option>
                                <option value="szambo" <?php selected((string) ($edit_search_criteria['sewage'] ?? ''), 'szambo'); ?>>Szambo</option>
                                <option value="oczyszczalnia" <?php selected((string) ($edit_search_criteria['sewage'] ?? ''), 'oczyszczalnia'); ?>>Oczyszczalnia</option>
                                <option value="inne" <?php selected((string) ($edit_search_criteria['sewage'] ?? ''), 'inne'); ?>>Inne</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field"><label>Gaz</label>
                            <select name="gas">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_gas_val, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_gas_val, '0'); ?>>Nie</option>
                            </select>
                        </p>
                    </div>

                    <h4>Udogodnienia</h4>
                    <div class="eocrm-checkbox-list eocrm-search-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="winda" <?php checked(in_array('winda', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Winda</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="klimatyzacja" <?php checked(in_array('klimatyzacja', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Klimatyzacja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="monitoring" <?php checked(in_array('monitoring', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Monitoring/Ochrona</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="recepcja" <?php checked(in_array('recepcja', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Recepcja</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="teren_zamkniety" <?php checked(in_array('teren_zamkniety', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Teren zamkni&#281;ty</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="domofon" <?php checked(in_array('domofon', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Domofon</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="garaz" data-eocrm-search-amenity-toggle="garaz" <?php checked(in_array('garaz', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Gara&#380;</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="amenities[]" value="miejsce_postojowe" data-eocrm-search-amenity-toggle="miejsce_postojowe" <?php checked(in_array('miejsce_postojowe', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>>Miejsce postojowe</label>
                    </div>

                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <p class="eocrm-form-field<?php echo in_array('garaz', (array) ($edit_search_criteria['amenities'] ?? []), true) ? '' : ' is-hidden'; ?>" data-eocrm-search-amenity-count="garaz"><label>Liczba miejsc w gara&#380;u</label><input type="number" min="0" name="amenity_garage_count" value="<?php echo esc_attr((string) ($edit_search_amenity_counts['garaz'] ?? '')); ?>" <?php disabled(! in_array('garaz', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>></p>
                        <p class="eocrm-form-field<?php echo in_array('miejsce_postojowe', (array) ($edit_search_criteria['amenities'] ?? []), true) ? '' : ' is-hidden'; ?>" data-eocrm-search-amenity-count="miejsce_postojowe"><label>Liczba miejsc postojowych</label><input type="number" min="0" name="amenity_parking_space_count" value="<?php echo esc_attr((string) ($edit_search_amenity_counts['miejsce_postojowe'] ?? '')); ?>" <?php disabled(! in_array('miejsce_postojowe', (array) ($edit_search_criteria['amenities'] ?? []), true)); ?>></p>
                        <p class="eocrm-form-field"><label>Umeblowanie</label>
                            <select name="furnished_state">
                                <option value="">Dowolnie</option>
                                <option value="tak" <?php selected((string) ($edit_search_criteria['furnished_state'] ?? ''), 'tak'); ?>>Tak</option>
                                <option value="nie" <?php selected((string) ($edit_search_criteria['furnished_state'] ?? ''), 'nie'); ?>>Nie</option>
                                <option value="czesciowe" <?php selected((string) ($edit_search_criteria['furnished_state'] ?? ''), 'czesciowe'); ?>>Cz&#281;&#347;ciowe</option>
                            </select>
                        </p>
                    </div>

                    <h4>Wyposa&#380;enie</h4>
                    <div class="eocrm-checkbox-list eocrm-search-not-plot">
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="pralka" <?php checked(in_array('pralka', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Pralka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="zmywarka" <?php checked(in_array('zmywarka', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Zmywarka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="lodowka" <?php checked(in_array('lodowka', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Lod&#243;wka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="kuchenka" <?php checked(in_array('kuchenka', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Kuchenka</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="piekarnik" <?php checked(in_array('piekarnik', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Piekarnik</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="telewizor" <?php checked(in_array('telewizor', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Telewizor</label>
                        <label class="eocrm-checkbox-item"><input type="checkbox" name="equipment[]" value="mikrofala" <?php checked(in_array('mikrofala', (array) ($edit_search_criteria['equipment'] ?? []), true)); ?>>Mikrofala</label>
                    </div>

                    <h4 class="eocrm-search-not-plot">Powierzchnie dodatkowe</h4>
                    <div class="eocrm-form-grid eocrm-search-not-plot">
                        <p class="eocrm-form-field"><label>Balkon</label>
                            <select name="balcony_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="balcony">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_balcony_has, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_balcony_has, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-balcony <?php echo $edit_search_balcony_has === '1' ? '' : 'is-hidden'; ?>"><label>Min. pow. balkonu (m2)</label><input type="text" name="balcony_area_min" value="<?php echo esc_attr((string) ($edit_search_extra['balcony']['area_min'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Taras</label>
                            <select name="terrace_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="terrace">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_terrace_has, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_terrace_has, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-terrace <?php echo $edit_search_terrace_has === '1' ? '' : 'is-hidden'; ?>"><label>Min. pow. tarasu (m2)</label><input type="text" name="terrace_area_min" value="<?php echo esc_attr((string) ($edit_search_extra['terrace']['area_min'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Piwnica</label>
                            <select name="basement_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="basement">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_basement_has, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_basement_has, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-basement <?php echo $edit_search_basement_has === '1' ? '' : 'is-hidden'; ?>"><label>Min. pow. piwnicy (m2)</label><input type="text" name="basement_area_min" value="<?php echo esc_attr((string) ($edit_search_extra['basement']['area_min'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Kom&#243;rka lokatorska</label>
                            <select name="storage_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="storage">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_storage_has, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_storage_has, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-storage <?php echo $edit_search_storage_has === '1' ? '' : 'is-hidden'; ?>"><label>Min. pow. kom&#243;rki (m2)</label><input type="text" name="storage_area_min" value="<?php echo esc_attr((string) ($edit_search_extra['storage']['area_min'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Ogr&#243;dek</label>
                            <select name="garden_has" data-eocrm-search-extra-select data-eocrm-search-extra-key="garden">
                                <option value="">Dowolnie</option>
                                <option value="1" <?php selected($edit_search_garden_has, '1'); ?>>Tak</option>
                                <option value="0" <?php selected($edit_search_garden_has, '0'); ?>>Nie</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-search-extra-garden <?php echo $edit_search_garden_has === '1' ? '' : 'is-hidden'; ?>"><label>Min. pow. ogrodka (m2)</label><input type="text" name="garden_area_min" value="<?php echo esc_attr((string) ($edit_search_extra['garden']['area_min'] ?? '')); ?>"></p>
                    </div>

                    <p><button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz zmiany poszukiwania</button></p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($search_id > 0 && ! is_array($search_profile)) : ?>
            <div class="eocrm-alert eocrm-alert-error">Nie znaleziono profilu poszukiwania.</div>
        <?php endif; ?>

        <?php if (is_array($search_profile)) : ?>
            <?php
            $search_owner = get_userdata((int) ($search_profile['owner_user_id'] ?? 0));
            $search_criteria = isset($search_profile['criteria']) && is_array($search_profile['criteria']) ? $search_profile['criteria'] : [];

            // ----- Hero values -----
            $search_profile_id = (int) ($search_profile['id'] ?? 0);
            $search_profile_number = (string) ($search_profile['search_number'] ?? '');
            $search_property_type_label_p = $render_search_property_types((string) ($search_profile['property_type'] ?? ''));
            $search_transaction_code_p = strtoupper(trim((string) ($search_profile['transaction_type'] ?? '')));
            $search_transaction_label_map_p = [
                'KUPNO' => 'Kupno',
                'NAJEM' => 'Najem',
                'SPRZEDAZ' => 'Sprzeda&#380;',
                'WYNAJEM' => 'Wynajem',
            ];
            $search_transaction_label_p = $search_transaction_label_map_p[$search_transaction_code_p] ?? ($search_transaction_code_p !== '' ? ucfirst(strtolower($search_transaction_code_p)) : '');

            $search_profile_owner_name = $search_owner instanceof WP_User ? (string) $search_owner->display_name : '';
            $search_profile_owner_avatar = $search_owner instanceof WP_User ? $resolve_owner_photo_url((int) $search_owner->ID, 'medium') : '';
            $search_profile_owner_initial = $search_profile_owner_name !== '' ? mb_strtoupper(mb_substr($search_profile_owner_name, 0, 1)) : '';
            $search_profile_location = trim((string) ($search_profile['location_text'] ?? ''));

            $search_format_money = static function ($value): string {
                if (! is_numeric((string) $value)) {
                    return '';
                }
                return number_format((float) $value, 0, ',', ' ');
            };
            $search_budget_from_p = (string) ($search_profile['budget_from'] ?? '');
            $search_budget_to_p = (string) ($search_profile['budget_to'] ?? '');
            $search_budget_label_p = '';
            $search_budget_sub_p = '';
            if (is_numeric($search_budget_from_p) && is_numeric($search_budget_to_p)) {
                $search_budget_label_p = $search_format_money($search_budget_from_p) . ' &ndash; ' . $search_format_money($search_budget_to_p) . ' z&#322;';
            } elseif (is_numeric($search_budget_from_p)) {
                $search_budget_label_p = 'od ' . $search_format_money($search_budget_from_p) . ' z&#322;';
            } elseif (is_numeric($search_budget_to_p)) {
                $search_budget_label_p = 'do ' . $search_format_money($search_budget_to_p) . ' z&#322;';
            } else {
                $search_budget_label_p = '-';
            }

            $search_area_from_p = (string) ($search_profile['area_from'] ?? '');
            $search_area_to_p = (string) ($search_profile['area_to'] ?? '');
            $search_area_label_p = '';
            if (is_numeric($search_area_from_p) && is_numeric($search_area_to_p)) {
                $search_area_label_p = rtrim(rtrim(number_format((float) $search_area_from_p, 2, '.', ''), '0'), '.') . '&ndash;' . rtrim(rtrim(number_format((float) $search_area_to_p, 2, '.', ''), '0'), '.');
            } elseif (is_numeric($search_area_from_p)) {
                $search_area_label_p = '&#8805; ' . rtrim(rtrim(number_format((float) $search_area_from_p, 2, '.', ''), '0'), '.');
            } elseif (is_numeric($search_area_to_p)) {
                $search_area_label_p = '&#8804; ' . rtrim(rtrim(number_format((float) $search_area_to_p, 2, '.', ''), '0'), '.');
            }

            $search_rooms_from_p = (string) ($search_profile['rooms_from'] ?? '');
            $search_rooms_to_p = (string) ($search_profile['rooms_to'] ?? '');
            $search_rooms_label_p = '';
            if (is_numeric($search_rooms_from_p) && is_numeric($search_rooms_to_p)) {
                $search_rooms_label_p = (int) $search_rooms_from_p . '&ndash;' . (int) $search_rooms_to_p;
            } elseif (is_numeric($search_rooms_from_p)) {
                $search_rooms_label_p = '&#8805; ' . (int) $search_rooms_from_p;
            } elseif (is_numeric($search_rooms_to_p)) {
                $search_rooms_label_p = '&#8804; ' . (int) $search_rooms_to_p;
            }

            $search_floor_from_p = (string) ($search_profile['floor_from'] ?? '');
            $search_floor_to_p = (string) ($search_profile['floor_to'] ?? '');
            $search_floor_label_p = '';
            if (is_numeric($search_floor_from_p) && is_numeric($search_floor_to_p)) {
                $search_floor_label_p = (int) $search_floor_from_p . '&ndash;' . (int) $search_floor_to_p;
            } elseif (is_numeric($search_floor_from_p)) {
                $search_floor_label_p = '&#8805; ' . (int) $search_floor_from_p;
            } elseif (is_numeric($search_floor_to_p)) {
                $search_floor_label_p = '&#8804; ' . (int) $search_floor_to_p;
            }

            // ----- Stage timeline data (from linked agreement) -----
            $search_stage_data = [];
            $search_stage_current = '';
            $search_stage_total = 0;
            $search_stage_completed = 0;
            $search_stage_progress_pct = 0;
            if (
                isset($search_profile_agreement_stages)
                && is_array($search_profile_agreement_stages)
                && ! empty($search_profile_agreement_stages)
                && is_array($search_profile_agreement)
            ) {
                $search_stage_current = (string) ($search_profile_agreement['current_stage'] ?? '');
                $search_history_p = isset($search_profile_agreement_stage_history) && is_array($search_profile_agreement_stage_history)
                    ? $search_profile_agreement_stage_history
                    : [];
                $search_stage_dates = [];
                foreach ($search_history_p as $hist_row_s) {
                    if (! is_array($hist_row_s)) {
                        continue;
                    }
                    $hn = (string) ($hist_row_s['stage_name'] ?? '');
                    $hd = (string) ($hist_row_s['stage_date'] ?? '');
                    if ($hn !== '' && $hd !== '' && ! isset($search_stage_dates[$hn])) {
                        $search_stage_dates[$hn] = $hd;
                    }
                }
                $search_current_stage_idx = -1;
                foreach ($search_profile_agreement_stages as $stage_idx_s => $stage_name_s) {
                    if ((string) $stage_name_s === $search_stage_current) {
                        $search_current_stage_idx = $stage_idx_s;
                        break;
                    }
                }
                $search_stage_total = count($search_profile_agreement_stages);
                foreach ($search_profile_agreement_stages as $stage_idx_s => $stage_name_s) {
                    $state_s = 'upcoming';
                    if ($search_current_stage_idx >= 0 && $stage_idx_s < $search_current_stage_idx) {
                        $state_s = 'completed';
                    } elseif ($search_current_stage_idx >= 0 && $stage_idx_s === $search_current_stage_idx) {
                        $state_s = 'current';
                    }
                    if ($is_agreement_stage_finished($search_stage_current) && $stage_idx_s === $search_current_stage_idx) {
                        $state_s = 'completed';
                    }
                    $search_stage_data[] = [
                        'name' => (string) $stage_name_s,
                        'state' => $state_s,
                        'date' => isset($search_stage_dates[(string) $stage_name_s]) ? (string) $search_stage_dates[(string) $stage_name_s] : '',
                        'index' => $stage_idx_s + 1,
                    ];
                    if ($state_s === 'completed') {
                        $search_stage_completed++;
                    }
                }
                if ($search_stage_total > 0) {
                    $effective_position_s = $search_current_stage_idx >= 0 ? $search_current_stage_idx : ($search_stage_completed > 0 ? $search_stage_completed - 1 : 0);
                    if ($is_agreement_stage_finished($search_stage_current)) {
                        $search_stage_progress_pct = 100;
                    } else {
                        $search_stage_progress_pct = (int) round((max(0, $effective_position_s) / max(1, $search_stage_total - 1)) * 100);
                    }
                }
            }

            // ----- Themed criteria rows -----
            $search_has_display = static function ($value): bool {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_scalar($item) && trim((string) $item) !== '') {
                            return true;
                        }
                    }
                    return false;
                }
                if (is_int($value) || is_float($value)) {
                    return true;
                }
                if ($value === null) {
                    return false;
                }
                $sv = trim((string) $value);
                return $sv !== '' && $sv !== '-';
            };
            $search_add_row = static function (array &$rows, string $label, $value) use ($search_has_display): void {
                if (! $search_has_display($value)) {
                    return;
                }
                $rows[] = ['label' => $label, 'value' => (string) $value];
            };

            // CRITERIA — basic
            $rows_search_criteria = [];
            $search_add_row($rows_search_criteria, 'Bud&#380;et', $search_budget_label_p);
            $search_add_row($rows_search_criteria, 'Metra&#380; (m&sup2;)', $search_area_label_p);
            $search_add_row($rows_search_criteria, 'Pokoje', $search_rooms_label_p);
            $search_add_row($rows_search_criteria, 'Pi&#281;tro', $search_floor_label_p);

            // LOCATION
            $rows_search_location = [];
            $search_add_row($rows_search_location, 'Lokalizacja', $search_profile_location);

            // CONDITION
            $rows_search_condition = [];
            $search_add_row($rows_search_condition, 'Stan wyko&#324;czenia', (string) ($search_criteria['building_finish'] ?? ''));
            $search_add_row($rows_search_condition, 'Kuchnia', (string) ($search_criteria['kitchen_type'] ?? ''));
            if (array_key_exists('attic', $search_criteria) && (string) $search_criteria['attic'] !== '') {
                $search_add_row($rows_search_condition, 'Poddasze', $render_bool($search_criteria['attic']));
            }
            if (array_key_exists('multi_level', $search_criteria) && (string) $search_criteria['multi_level'] !== '') {
                $search_add_row($rows_search_condition, 'Wielopoziomowe', $render_bool($search_criteria['multi_level']));
            }
            $search_add_row($rows_search_condition, 'Umeblowanie', (string) ($search_criteria['furnished_state'] ?? ''));

            // MEDIA
            $rows_search_media = [];
            $search_add_row($rows_search_media, 'Ogrzewanie', (string) ($search_criteria['heating'] ?? ''));
            $search_add_row($rows_search_media, 'Woda', (string) ($search_criteria['water'] ?? ''));
            $search_add_row($rows_search_media, 'Kanalizacja', (string) ($search_criteria['sewage'] ?? ''));
            if (array_key_exists('gas', $search_criteria) && (string) $search_criteria['gas'] !== '') {
                $search_add_row($rows_search_media, 'Gaz', $render_bool($search_criteria['gas']));
            }

            // FEATURES
            $rows_search_features = [];
            if (! empty($search_criteria['exposure']) && is_array($search_criteria['exposure'])) {
                $search_add_row($rows_search_features, 'Ekspozycja', $render_list($search_criteria['exposure']));
            }
            if (! empty($search_criteria['view']) && is_array($search_criteria['view'])) {
                $search_add_row($rows_search_features, 'Widok', $render_list($search_criteria['view']));
            }
            if (! empty($search_criteria['layout']) && is_array($search_criteria['layout'])) {
                $search_add_row($rows_search_features, 'Rozk&#322;ad', $render_list($search_criteria['layout']));
            }
            if (! empty($search_criteria['amenities']) && is_array($search_criteria['amenities'])) {
                $search_add_row($rows_search_features, 'Udogodnienia', $render_search_amenities($search_criteria['amenities'], $search_criteria['amenity_counts'] ?? []));
            }
            if (! empty($search_criteria['equipment']) && is_array($search_criteria['equipment'])) {
                $search_add_row($rows_search_features, 'Wyposa&#380;enie', $render_list($search_criteria['equipment']));
            }

            // EXTRA AREAS
            $rows_search_extra = [];
            $search_extra_value = $render_search_extra_areas($search_criteria['extra_areas'] ?? []);
            $search_add_row($rows_search_extra, 'Powierzchnie dodatkowe', $search_extra_value);

            // META
            $rows_search_meta = [];
            $search_add_row($rows_search_meta, 'Numer', $search_profile_number);
            $search_add_row($rows_search_meta, 'Typ transakcji', $search_transaction_label_p);
            $search_add_row($rows_search_meta, 'Rodzaj nieruchomo&#347;ci', $search_property_type_label_p);
            $search_add_row($rows_search_meta, 'Utworzono', (string) ($search_profile['created_at'] ?? ''));
            $search_add_row($rows_search_meta, 'Aktualizacja', (string) ($search_profile['updated_at'] ?? ''));

            $search_themed_cards = [
                ['key' => 'criteria', 'title' => 'Kryteria podstawowe', 'icon' => '&#127919;', 'rows' => $rows_search_criteria, 'class' => 'eocrm-prop-detail-card--dimensions'],
                ['key' => 'location', 'title' => 'Lokalizacja', 'icon' => '&#128205;', 'rows' => $rows_search_location, 'class' => 'eocrm-prop-detail-card--location'],
                ['key' => 'condition', 'title' => 'Stan i wyko&#324;czenie', 'icon' => '&#127968;', 'rows' => $rows_search_condition, 'class' => 'eocrm-prop-detail-card--condition'],
                ['key' => 'media', 'title' => 'Media i instalacje', 'icon' => '&#128268;', 'rows' => $rows_search_media, 'class' => 'eocrm-prop-detail-card--media'],
                ['key' => 'features', 'title' => 'Cechy i atuty', 'icon' => '&#9989;', 'rows' => $rows_search_features, 'class' => 'eocrm-prop-detail-card--features'],
                ['key' => 'extra', 'title' => 'Powierzchnie dodatkowe', 'icon' => '&#128208;', 'rows' => $rows_search_extra, 'class' => 'eocrm-prop-detail-card--features'],
                ['key' => 'meta', 'title' => 'Identyfikacja', 'icon' => '&#128203;', 'rows' => $rows_search_meta, 'class' => 'eocrm-prop-detail-card--export'],
            ];

            $search_description_html = (string) ($search_profile['description'] ?? '');
            ?>

            <section class="eocrm-prop-profile" data-eocrm-search-profile>

                <header class="eocrm-prop-profile-hero">
                    <div class="eocrm-prop-profile-hero__main">
                        <span class="eocrm-prop-profile-hero__eyebrow">Profil poszukiwania</span>
                        <h2 class="eocrm-prop-profile-hero__title">
                            <?php echo esc_html($search_profile_number !== '' ? $search_profile_number : ('Poszukiwanie #' . $search_profile_id)); ?>
                            <?php if ($search_transaction_label_p !== '') : ?>
                                <span class="eocrm-prop-status-pill is-active"><?php echo wp_kses_post($search_transaction_label_p); ?></span>
                            <?php endif; ?>
                        </h2>
                        <p class="eocrm-prop-profile-hero__meta">
                            <?php echo wp_kses_post($search_property_type_label_p); ?>
                            <?php if ($search_profile_location !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                <?php echo esc_html($search_profile_location); ?>
                            <?php endif; ?>
                            <?php if ($search_profile_owner_name !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                Opiekun: <strong><?php echo esc_html($search_profile_owner_name); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="eocrm-prop-profile-hero__actions">
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'searches'], $current_url)); ?>">&larr; Powr&#243;t</a>
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'search_id' => $search_profile_id, 'mode' => 'edit-search'], $current_url)); ?>">Edytuj</a>
                        <?php if ($is_admin_user) : ?>
                            <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'search_id' => $search_profile_id], $current_url)); ?>" onsubmit="return confirm('Czy na pewno usunac poszukiwanie?');" class="eocrm-prop-profile-hero__delete">
                                <?php wp_nonce_field('eocrm_delete_search', 'eocrm_search_delete_nonce'); ?>
                                <input type="hidden" name="eocrm_action" value="delete_search">
                                <input type="hidden" name="search_id" value="<?php echo (int) $search_profile_id; ?>">
                                <button class="eocrm-btn eocrm-btn-danger" type="submit">Usu&#324;</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (! empty($search_stage_data)) : ?>
                    <section class="eocrm-prop-stages" aria-label="Etap umowy">
                        <header class="eocrm-prop-stages__head">
                            <div>
                                <h3>Etap umowy</h3>
                                <p class="eocrm-prop-stages__hint">
                                    Pobrano z umowy
                                    <a href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => (int) ($search_profile_agreement['id'] ?? 0)], $current_url)); ?>">
                                        <?php echo esc_html((string) ($search_profile_agreement['agreement_number'] ?? '')); ?>
                                    </a>
                                    <?php if ($search_transaction_label_p !== '') : ?>
                                        <span class="eocrm-prop-stages__sep">&middot;</span>
                                        <?php echo wp_kses_post($search_transaction_label_p); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="eocrm-prop-stages__progress">
                                <span class="eocrm-prop-stages__progress-label">Post&#281;p</span>
                                <span class="eocrm-prop-stages__progress-value"><?php echo esc_html((string) $search_stage_progress_pct); ?>%</span>
                                <span class="eocrm-prop-stages__progress-track">
                                    <span class="eocrm-prop-stages__progress-fill" style="width: <?php echo esc_attr((string) $search_stage_progress_pct); ?>%;"></span>
                                </span>
                            </div>
                        </header>
                        <ol class="eocrm-prop-stages__list">
                            <?php foreach ($search_stage_data as $stage_row_s) :
                                $stage_state_s = (string) ($stage_row_s['state'] ?? 'upcoming');
                                $stage_label_s = (string) ($stage_row_s['name'] ?? '');
                                $stage_date_raw_s = (string) ($stage_row_s['date'] ?? '');
                                $stage_idx_num_s = (int) ($stage_row_s['index'] ?? 0);
                                $stage_date_formatted_s = '';
                                if ($stage_date_raw_s !== '' && $stage_date_raw_s !== '0000-00-00') {
                                    $stage_date_ts_s = strtotime($stage_date_raw_s);
                                    if ($stage_date_ts_s !== false) {
                                        $stage_date_formatted_s = date_i18n('d.m.Y', $stage_date_ts_s);
                                    }
                                }
                            ?>
                                <li class="eocrm-prop-stage is-<?php echo esc_attr($stage_state_s); ?>">
                                    <span class="eocrm-prop-stage__indicator" aria-hidden="true">
                                        <?php if ($stage_state_s === 'completed') : ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php else : ?>
                                            <span class="eocrm-prop-stage__num"><?php echo esc_html((string) $stage_idx_num_s); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="eocrm-prop-stage__body">
                                        <span class="eocrm-prop-stage__name"><?php echo esc_html($stage_label_s); ?></span>
                                        <span class="eocrm-prop-stage__sub">
                                            <?php if ($stage_state_s === 'completed') :
                                                echo esc_html($stage_date_formatted_s !== '' ? $stage_date_formatted_s : 'Zako&#324;czony');
                                            elseif ($stage_state_s === 'current') :
                                                echo esc_html($stage_date_formatted_s !== '' ? ('Od ' . $stage_date_formatted_s) : 'W trakcie');
                                            else :
                                                echo 'Oczekuje';
                                            endif; ?>
                                        </span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php elseif (! is_array($search_profile_agreement)) : ?>
                    <section class="eocrm-prop-stages eocrm-prop-stages--empty" aria-label="Etap umowy">
                        <div class="eocrm-prop-stages__empty">
                            <span class="eocrm-prop-stages__empty-icon" aria-hidden="true">&#128196;</span>
                            <div>
                                <strong>Brak powi&#261;zanej umowy</strong>
                                <span>Po powi&#261;zaniu poszukiwania z umow&#261; pojawi si&#281; tutaj timeline etap&oacute;w.</span>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="eocrm-prop-quick-stats">
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--blue">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128181;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Bud&#380;et</span>
                            <span class="eocrm-prop-quick-stat__value eocrm-prop-quick-stat__value--small"><?php echo wp_kses_post($search_budget_label_p); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">Maks. zaakceptowana cena</span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--green">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128208;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Metra&#380;</span>
                            <span class="eocrm-prop-quick-stat__value">
                                <?php if ($search_area_label_p !== '') : ?>
                                    <?php echo wp_kses_post($search_area_label_p); ?> m<sup>2</sup>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </span>
                            <?php if ($search_floor_label_p !== '') : ?>
                                <span class="eocrm-prop-quick-stat__sub">pi&#281;tro <?php echo wp_kses_post($search_floor_label_p); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--purple">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128719;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Pokoje</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo wp_kses_post($search_rooms_label_p !== '' ? $search_rooms_label_p : '-'); ?></span>
                            <?php if ($search_property_type_label_p !== '-') : ?>
                                <span class="eocrm-prop-quick-stat__sub"><?php echo wp_kses_post($search_property_type_label_p); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--orange">
                        <span class="eocrm-prop-quick-stat__avatar" aria-hidden="true">
                            <?php if ($search_profile_owner_avatar !== '') : ?>
                                <img src="<?php echo esc_url($search_profile_owner_avatar); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="eocrm-prop-quick-stat__avatar-initial"><?php echo esc_html($search_profile_owner_initial !== '' ? $search_profile_owner_initial : '?'); ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Opiekun</span>
                            <span class="eocrm-prop-quick-stat__value eocrm-prop-quick-stat__value--small"><?php echo esc_html($search_profile_owner_name !== '' ? $search_profile_owner_name : '-'); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">
                                <?php
                                $klienci_count = is_array($search_profile_clients) ? count($search_profile_clients) : 0;
                                echo esc_html($klienci_count . ' ' . ($klienci_count === 1 ? 'klient' : ($klienci_count >= 2 && $klienci_count <= 4 ? 'klient&oacute;w' : 'klient&oacute;w')));
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <section class="eocrm-prop-detail" data-eocrm-search-detail>
                    <div class="eocrm-prop-detail-grid">
                        <?php foreach ($search_themed_cards as $themed_card_s) :
                            $card_rows_s = is_array($themed_card_s['rows'] ?? null) ? $themed_card_s['rows'] : [];
                            if (empty($card_rows_s)) {
                                continue;
                            }
                        ?>
                            <article class="eocrm-prop-detail-card <?php echo esc_attr((string) $themed_card_s['class']); ?>">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true"><?php echo wp_kses_post((string) $themed_card_s['icon']); ?></span>
                                    <h3><?php echo wp_kses_post((string) $themed_card_s['title']); ?></h3>
                                </header>
                                <dl class="eocrm-prop-detail-card__defs">
                                    <?php foreach ($card_rows_s as $card_row_item_s) : ?>
                                        <div>
                                            <dt><?php echo esc_html((string) ($card_row_item_s['label'] ?? '')); ?></dt>
                                            <dd><?php echo wp_kses_post((string) ($card_row_item_s['value'] ?? '')); ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full eocrm-prop-detail-card--description">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128221;</span>
                            <h3>Opis poszukiwania</h3>
                        </header>
                        <div class="eocrm-prop-detail-card__richtext">
                            <?php echo $search_description_html !== '' ? wp_kses_post(wpautop($search_description_html)) : '<p class="eocrm-muted">Brak opisu.</p>'; ?>
                        </div>
                    </article>

                    <div class="eocrm-prop-detail-related">
                        <article class="eocrm-prop-detail-card">
                            <header class="eocrm-prop-detail-card__head">
                                <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128101;</span>
                                <h3>Klienci powi&#261;zani</h3>
                            </header>
                            <?php if (empty($search_profile_clients)) : ?>
                                <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych klient&oacute;w.</p>
                            <?php else : ?>
                                <ul class="eocrm-prop-detail-related-list">
                                    <?php foreach ($search_profile_clients as $client_row_s) :
                                        $client_link_id_s = (int) ($client_row_s['id'] ?? 0);
                                        $client_display_name_s = (string) ($client_row_s['display_name'] ?? 'Klient');
                                        $client_initial_s = $client_display_name_s !== '' ? mb_strtoupper(mb_substr($client_display_name_s, 0, 1)) : '?';
                                    ?>
                                        <li>
                                            <a href="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'client_id' => $client_link_id_s], $current_url)); ?>">
                                                <span class="eocrm-prop-detail-related-list__avatar"><?php echo esc_html($client_initial_s); ?></span>
                                                <span class="eocrm-prop-detail-related-list__name"><?php echo esc_html($client_display_name_s); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>

                        <article class="eocrm-prop-detail-card">
                            <header class="eocrm-prop-detail-card__head">
                                <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128196;</span>
                                <h3>Umowa powi&#261;zana</h3>
                            </header>
                            <?php if (is_array($search_profile_agreement)) :
                                $linked_search_agreement_id = (int) ($search_profile_agreement['id'] ?? 0);
                                $linked_search_agreement_number = (string) ($search_profile_agreement['agreement_number'] ?? '');
                                $linked_search_agreement_tx = strtoupper((string) ($search_profile_agreement['transaction_type'] ?? ''));
                                $linked_search_agreement_tx_label = $search_transaction_label_map_p[$linked_search_agreement_tx] ?? $linked_search_agreement_tx;
                                $linked_search_agreement_stage = (string) ($search_profile_agreement['current_stage'] ?? '');
                                $linked_search_agreement_finished = $is_agreement_stage_finished($linked_search_agreement_stage);
                            ?>
                                <div class="eocrm-prop-detail-agreement">
                                    <div class="eocrm-prop-detail-agreement__head">
                                        <span class="eocrm-prop-detail-agreement__number"><?php echo esc_html($linked_search_agreement_number !== '' ? $linked_search_agreement_number : ('Umowa #' . $linked_search_agreement_id)); ?></span>
                                        <span class="eocrm-prop-detail-agreement__tx"><?php echo wp_kses_post($linked_search_agreement_tx_label); ?></span>
                                    </div>
                                    <p class="eocrm-prop-detail-agreement__stage <?php echo $linked_search_agreement_finished ? 'is-done' : 'is-progress'; ?>">
                                        <?php echo esc_html($linked_search_agreement_stage !== '' ? $linked_search_agreement_stage : ($linked_search_agreement_finished ? 'Zako&#324;czona' : 'W trakcie')); ?>
                                    </p>
                                    <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $linked_search_agreement_id], $current_url)); ?>">
                                        Otw&oacute;rz umow&#281; &rarr;
                                    </a>
                                </div>
                            <?php else : ?>
                                <p class="eocrm-prop-detail-empty">Brak powi&#261;zanej umowy.</p>
                            <?php endif; ?>
                        </article>
                    </div>
                </section>
            </section>
        <?php endif; ?>

        <?php
        $search_property_type_label_map = [
            'MIESZKANIE' => 'Mieszkanie',
            'DOM' => 'Dom',
            'DZIALKA' => 'Dzia&#322;ka',
            'LOKAL_HU' => 'Lokal H/U',
        ];
        $search_transaction_label_map = [
            'KUPNO' => 'Kupno',
            'NAJEM' => 'Najem',
            'SPRZEDAZ' => 'Sprzeda&#380;',
            'WYNAJEM' => 'Wynajem',
        ];
        $searches_total_count = is_array($searches_rows) ? count($searches_rows) : 0;
        $format_money_short = static function ($value): string {
            if (! is_numeric((string) $value)) {
                return '';
            }
            $num = (float) $value;
            return number_format($num, 0, ',', ' ');
        };
        ?>

        <section class="eocrm-prop-toolbar" aria-label="Filtry i wyszukiwanie poszukiwa&#324;">
            <form method="get" class="eocrm-prop-toolbar__search" role="search">
                <input type="hidden" name="crm" value="searches">
                <span class="eocrm-prop-toolbar__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                </span>
                <input
                    id="eocrm-searches-search"
                    data-live-filter
                    data-target-table="eocrm-searches-table"
                    name="q"
                    type="search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Szukaj po numerze, lokalizacji, opiekunie..."
                    aria-label="Szukaj poszukiwa&#324;"
                >
                <button class="eocrm-prop-toolbar__search-btn" type="submit">Szukaj</button>
            </form>

            <div class="eocrm-prop-filters" data-eocrm-transaction-filter-group data-target-table="eocrm-searches-table" aria-label="Filtry">
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-transaction-filter value="KUPNO">
                    <span>Kupno</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-transaction-filter value="NAJEM">
                    <span>Najem</span>
                </label>
                <label class="eocrm-table-filter-toggle" title="Pokaz tylko poszukiwania z aktywna umowa">
                    <input type="checkbox" data-eocrm-status-filter="active_agreement" value="1">
                    <span>Aktywne</span>
                </label>
            </div>
        </section>

        <div class="eocrm-prop-meta">
            <p class="eocrm-prop-meta__count">
                <strong><?php echo esc_html((string) $searches_total_count); ?></strong>
                <span><?php
                    if ($searches_total_count === 1) {
                        echo 'poszukiwanie';
                    } elseif ($searches_total_count >= 2 && $searches_total_count <= 4) {
                        echo 'poszukiwania';
                    } else {
                        echo 'poszukiwa&#324;';
                    }
                ?></span>
                <?php if ($search !== '') : ?>
                    <em class="eocrm-prop-meta__hint">dla zapytania &laquo;<?php echo esc_html($search); ?>&raquo;</em>
                <?php endif; ?>
            </p>
        </div>

        <div class="eocrm-prop-list-wrap">
            <table class="eocrm-table eocrm-prop-list eocrm-search-list" id="eocrm-searches-table">
                <thead>
                    <tr>
                        <th class="eocrm-prop-col-main">Poszukiwanie</th>
                        <th class="eocrm-prop-col-loc">Lokalizacja</th>
                        <th class="eocrm-prop-col-price">Bud&#380;et</th>
                        <th class="eocrm-prop-col-metric">Metra&#380;</th>
                        <th class="eocrm-prop-col-metric">Pokoje</th>
                        <?php if ($show_owner_columns) : ?>
                            <th class="eocrm-prop-col-owner">Opiekun</th>
                        <?php endif; ?>
                        <th class="eocrm-prop-col-status">Status</th>
                        <th class="eocrm-prop-col-date">Aktualizacja</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($searches_rows)) : ?>
                        <tr class="eocrm-prop-empty-row"><td colspan="<?php echo esc_attr((string) ($show_owner_columns ? 8 : 7)); ?>">
                            <div class="eocrm-prop-empty">
                                <span class="eocrm-prop-empty__icon" aria-hidden="true">&#128269;</span>
                                <strong>Brak poszukiwa&#324; do wy&#347;wietlenia</strong>
                                <span>Spr&oacute;buj zmieni&#263; filtry lub doda&#263; nowe poszukiwanie.</span>
                            </div>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ($searches_rows as $row) :
                            $search_row_id = (int) ($row['id'] ?? 0);
                            $search_row_number = (string) ($row['search_number'] ?? '');
                            $search_transaction_type = strtoupper(trim((string) ($row['transaction_type'] ?? '')));
                            $search_property_type_label = $render_search_property_types((string) ($row['property_type'] ?? ''));
                            $search_transaction_label = $search_transaction_label_map[$search_transaction_type] ?? ($search_transaction_type !== '' ? ucfirst(strtolower($search_transaction_type)) : '');

                            $search_row_url = add_query_arg(['crm' => 'searches', 'search_id' => $search_row_id], $current_url);

                            $search_loc = trim((string) ($row['location_text'] ?? ''));

                            $search_budget_from = (string) ($row['budget_from'] ?? '');
                            $search_budget_to = (string) ($row['budget_to'] ?? '');
                            $search_budget_label = '';
                            if (is_numeric($search_budget_from) && is_numeric($search_budget_to)) {
                                $search_budget_label = $format_money_short($search_budget_from) . ' &ndash; ' . $format_money_short($search_budget_to) . ' z&#322;';
                            } elseif (is_numeric($search_budget_from)) {
                                $search_budget_label = 'od ' . $format_money_short($search_budget_from) . ' z&#322;';
                            } elseif (is_numeric($search_budget_to)) {
                                $search_budget_label = 'do ' . $format_money_short($search_budget_to) . ' z&#322;';
                            }

                            $search_area_from = (string) ($row['area_from'] ?? '');
                            $search_area_to = (string) ($row['area_to'] ?? '');
                            $search_area_label = '';
                            if (is_numeric($search_area_from) && is_numeric($search_area_to)) {
                                $search_area_label = rtrim(rtrim(number_format((float) $search_area_from, 2, '.', ''), '0'), '.') . '&ndash;' . rtrim(rtrim(number_format((float) $search_area_to, 2, '.', ''), '0'), '.');
                            } elseif (is_numeric($search_area_from)) {
                                $search_area_label = '&#8805; ' . rtrim(rtrim(number_format((float) $search_area_from, 2, '.', ''), '0'), '.');
                            } elseif (is_numeric($search_area_to)) {
                                $search_area_label = '&#8804; ' . rtrim(rtrim(number_format((float) $search_area_to, 2, '.', ''), '0'), '.');
                            }

                            $search_rooms_from = (string) ($row['rooms_from'] ?? '');
                            $search_rooms_to = (string) ($row['rooms_to'] ?? '');
                            $search_rooms_label = '';
                            if (is_numeric($search_rooms_from) && is_numeric($search_rooms_to)) {
                                $search_rooms_label = (int) $search_rooms_from . '&ndash;' . (int) $search_rooms_to;
                            } elseif (is_numeric($search_rooms_from)) {
                                $search_rooms_label = '&#8805; ' . (int) $search_rooms_from;
                            } elseif (is_numeric($search_rooms_to)) {
                                $search_rooms_label = '&#8804; ' . (int) $search_rooms_to;
                            }

                            $search_owner_name_row = trim((string) ($row['owner_display_name'] ?? ''));
                            if ($search_owner_name_row === '') {
                                $search_owner_data = get_userdata((int) ($row['owner_user_id'] ?? 0));
                                $search_owner_name_row = $search_owner_data instanceof WP_User ? (string) $search_owner_data->display_name : '-';
                                $search_owner_avatar_row = $search_owner_data instanceof WP_User ? $resolve_owner_photo_url((int) $search_owner_data->ID, 'thumbnail') : '';
                            } else {
                                $search_owner_avatar_row = $resolve_owner_photo_url((int) ($row['owner_user_id'] ?? 0), 'thumbnail');
                            }
                            $search_owner_initial_row = $search_owner_name_row !== '' && $search_owner_name_row !== '-' ? mb_strtoupper(mb_substr($search_owner_name_row, 0, 1)) : '?';

                            $search_agreement_id_row = (int) ($row['agreement_id'] ?? 0);
                            $search_agreement_stage_row = (string) ($row['agreement_stage'] ?? '');
                            $search_agreement_active_row = (int) ($row['agreement_is_active'] ?? 0) === 1;
                            $search_has_active_agreement = $search_agreement_id_row > 0 && $search_agreement_active_row && ! $is_agreement_stage_finished($search_agreement_stage_row);
                            $search_agreement_status_title = $search_agreement_id_row > 0
                                ? ($search_has_active_agreement ? 'Umowa: aktywna' : 'Umowa: zakonczona')
                                : 'Umowa: brak powiazania';

                            $search_updated_at_raw = (string) ($row['updated_at'] ?? '');
                        ?>
                            <tr
                                class="eocrm-prop-row"
                                data-eocrm-transaction-row
                                data-transaction="<?php echo esc_attr($search_transaction_type); ?>"
                                data-active-agreement="<?php echo esc_attr($search_has_active_agreement ? '1' : '0'); ?>"
                            >
                                <td class="eocrm-prop-cell-main">
                                    <a class="eocrm-prop-link" href="<?php echo esc_url($search_row_url); ?>">
                                        <span class="eocrm-prop-thumb eocrm-prop-thumb--icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                                        </span>
                                        <span class="eocrm-prop-titlewrap">
                                            <span class="eocrm-prop-title">
                                                <?php echo esc_html($search_row_number !== '' ? $search_row_number : ('Poszukiwanie #' . $search_row_id)); ?>
                                            </span>
                                            <span class="eocrm-prop-subtitle">
                                                <?php echo wp_kses_post($search_property_type_label); ?>
                                                <?php if ($search_transaction_label !== '') : ?>
                                                    &middot; <?php echo wp_kses_post($search_transaction_label); ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </a>
                                </td>
                                <td class="eocrm-prop-cell-loc">
                                    <?php if ($search_loc !== '') : ?>
                                        <span class="eocrm-prop-loc-primary"><?php echo esc_html($search_loc); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-loc-primary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-price">
                                    <?php if ($search_budget_label !== '') : ?>
                                        <span class="eocrm-prop-price"><?php echo wp_kses_post($search_budget_label); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-price eocrm-prop-price--empty">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <?php if ($search_area_label !== '') : ?>
                                        <span class="eocrm-prop-metric"><?php echo wp_kses_post($search_area_label); ?> m<sup>2</sup></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-metric eocrm-prop-metric--empty">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <?php if ($search_rooms_label !== '') : ?>
                                        <span class="eocrm-prop-metric"><?php echo wp_kses_post($search_rooms_label); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-metric eocrm-prop-metric--empty">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($show_owner_columns) : ?>
                                    <td class="eocrm-prop-cell-owner">
                                        <span class="eocrm-prop-owner">
                                            <span class="eocrm-prop-owner__avatar" aria-hidden="true">
                                                <?php if (! empty($search_owner_avatar_row)) : ?>
                                                    <img src="<?php echo esc_url((string) $search_owner_avatar_row); ?>" alt="" loading="lazy">
                                                <?php else : ?>
                                                    <span class="eocrm-prop-owner__initial"><?php echo esc_html($search_owner_initial_row); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="eocrm-prop-owner__name"><?php echo esc_html($search_owner_name_row); ?></span>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="eocrm-prop-cell-status">
                                    <span class="eocrm-prop-status-icons">
                                        <?php if ($search_has_active_agreement) : ?>
                                            <span class="eocrm-prop-status-icon is-on" title="<?php echo esc_attr($search_agreement_status_title); ?>" aria-label="<?php echo esc_attr($search_agreement_status_title); ?>">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path></svg>
                                            </span>
                                        <?php else : ?>
                                            <span class="eocrm-prop-status-icon is-off" title="<?php echo esc_attr($search_agreement_status_title); ?>" aria-label="<?php echo esc_attr($search_agreement_status_title); ?>">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path><path d="m4.93 4.93 14.14 14.14"></path></svg>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="eocrm-prop-cell-date">
                                    <?php if ($search_updated_at_raw !== '' && $search_updated_at_raw !== '0000-00-00 00:00:00') :
                                        $search_updated_ts = strtotime($search_updated_at_raw);
                                    ?>
                                        <span class="eocrm-prop-date-main"><?php echo esc_html(date_i18n('d.m.Y', $search_updated_ts)); ?></span>
                                        <span class="eocrm-prop-date-sub"><?php echo esc_html(date_i18n('H:i', $search_updated_ts)); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-date-main">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($section === 'agreements') : ?>
        <?php if ($crm_notice === 'agreement_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Umowa zostala dodana.'); ?></div>
        <?php elseif ($crm_notice === 'agreement_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Dane umowy zostaly zaktualizowane.'); ?></div>
        <?php elseif ($crm_notice === 'agreement_deleted') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Umowa zostala usunieta.'); ?></div>
        <?php elseif ($crm_notice === 'agreement_stage_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Etap umowy zostal zaktualizowany.'); ?></div>
        <?php elseif ($crm_notice === 'property_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nieruchomosc zostala dodana.'); ?></div>
        <?php elseif ($crm_notice === 'search_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Poszukiwanie zostalo dodane.'); ?></div>
        <?php elseif ($crm_notice === 'agreement_error') : ?>
            <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac umowy.'); ?></div>
        <?php endif; ?>

        <section class="eocrm-actions-row">
            <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'mode' => 'new-agreement'], $current_url)); ?>">Dodaj now&#261; Umow&#281;</a>
            <?php if ($agreements_mode !== '') : ?>
                <?php
                $close_agreement_args = ['crm' => 'agreements'];
                if (in_array($agreements_mode, ['edit-agreement', 'copy-agreement'], true) && is_array($agreement_profile)) {
                    $close_agreement_args['agreement_id'] = (int) ($agreement_profile['id'] ?? 0);
                }
                ?>
                <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg($close_agreement_args, $current_url)); ?>">Zamknij formularz</a>
            <?php endif; ?>
        </section>

        <?php if (in_array($agreements_mode, ['new-agreement', 'copy-agreement'], true)) : ?>
            <?php
            $is_copy_agreement_mode = $agreements_mode === 'copy-agreement' && is_array($agreement_profile) && $is_admin_user;
            $agreement_prefill_transaction_type = $is_copy_agreement_mode ? (string) ($agreement_profile['transaction_type'] ?? '') : '';
            if (! in_array($agreement_prefill_transaction_type, ['SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM'], true)) {
                $agreement_prefill_transaction_type = '';
            }
            $agreement_prefill_number = (string) $default_agreement_number;
            if ($is_copy_agreement_mode && $agreement_prefill_transaction_type !== '' && isset($default_agreement_numbers_by_type[$agreement_prefill_transaction_type])) {
                $agreement_prefill_number = (string) ($default_agreement_numbers_by_type[$agreement_prefill_transaction_type] ?? $default_agreement_number);
            }
            $agreement_prefill_is_indefinite = $is_copy_agreement_mode ? ! empty($agreement_profile['is_indefinite']) : false;
            $agreement_prefill_is_exclusive = $is_copy_agreement_mode ? ! empty($agreement_profile['is_exclusive']) : false;
            $agreement_prefilled_client_ids = [];
            if ($is_copy_agreement_mode) {
                foreach ($agreement_profile_clients as $agreement_copy_client_row) {
                    $agreement_prefilled_client_ids[] = (int) ($agreement_copy_client_row['id'] ?? 0);
                }
            }
            $agreement_prefill_commission_split_enabled = $is_copy_agreement_mode ? ! empty($agreement_profile['commission_split_enabled']) : false;
            $agreement_prefill_commission_stage_rows = $agreement_prefill_commission_split_enabled
                ? $parse_commission_stage_rows((string) ($agreement_profile['commission_stages_json'] ?? ''))
                : [];
            ?>
            <section class="eocrm-card">
                <h3><?php echo $is_copy_agreement_mode ? 'Kopiowanie umowy' : 'Nowa Umowa - Etap 1'; ?></h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'mode' => $is_copy_agreement_mode ? 'copy-agreement' : 'new-agreement'], $current_url)); ?>" data-eocrm-agreement-form>
                    <?php wp_nonce_field('eocrm_create_agreement', 'eocrm_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="create_agreement">

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Numer umowy</label>
                            <input type="text" name="agreement_number" value="<?php echo esc_attr($agreement_prefill_number); ?>" data-eocrm-agreement-number data-eocrm-agreement-number-defaults="<?php echo esc_attr($agreement_number_defaults_json); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Typ transakcji</label>
                            <select name="transaction_type" data-eocrm-agreement-transaction-type required>
                                <option value="">Wybierz</option>
                                <option value="SPRZEDAZ" <?php selected($agreement_prefill_transaction_type, 'SPRZEDAZ'); ?>>SPRZEDAZ</option>
                                <option value="KUPNO" <?php selected($agreement_prefill_transaction_type, 'KUPNO'); ?>>KUPNO</option>
                                <option value="WYNAJEM" <?php selected($agreement_prefill_transaction_type, 'WYNAJEM'); ?>>WYNAJEM</option>
                                <option value="NAJEM" <?php selected($agreement_prefill_transaction_type, 'NAJEM'); ?>>NAJEM</option>
                            </select>
                        </p>
                    </div>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Data zawarcia</label>
                            <input type="date" name="date_signed" value="<?php echo esc_attr($is_copy_agreement_mode ? (string) ($agreement_profile['date_signed'] ?? '') : ''); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Data zakonczenia</label>
                            <input type="date" name="date_end" data-eocrm-date-end value="<?php echo esc_attr($is_copy_agreement_mode ? (string) ($agreement_profile['date_end'] ?? '') : ''); ?>" <?php echo $agreement_prefill_is_indefinite ? '' : 'required'; ?>>
                        </p>
                    </div>

                    <p class="eocrm-form-field-checkbox">
                        <label>
                            <input type="checkbox" name="is_indefinite" value="1" data-eocrm-indefinite <?php checked($agreement_prefill_is_indefinite); ?>>
                            Umowa bezterminowa
                        </label>
                    </p>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Wylacznosc</label>
                            <span class="eocrm-choice-toggle">
                                <label class="eocrm-choice-toggle-item">
                                    <input type="radio" name="is_exclusive" value="1" <?php checked($agreement_prefill_is_exclusive); ?>>
                                    <span>Tak</span>
                                </label>
                                <label class="eocrm-choice-toggle-item">
                                    <input type="radio" name="is_exclusive" value="0" <?php checked(! $agreement_prefill_is_exclusive); ?>>
                                    <span>Nie</span>
                                </label>
                            </span>
                        </p>
                    </div>

                    <h4>Wysokosc prowizji</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Kwota</label>
                            <input type="text" name="commission_amount" placeholder="np. 2.5 lub 12000" value="<?php echo esc_attr($is_copy_agreement_mode ? (string) ($agreement_profile['commission_amount'] ?? '') : ''); ?>">
                        </p>
                        <p class="eocrm-form-field">
                            <label>Jednostka</label>
                            <select name="commission_unit">
                                <option value="">Wybierz</option>
                                <option value="%" <?php selected((string) ($is_copy_agreement_mode ? ($agreement_profile['commission_unit'] ?? '') : ''), '%'); ?>>%</option>
                                <option value="PLN" <?php selected((string) ($is_copy_agreement_mode ? ($agreement_profile['commission_unit'] ?? '') : ''), 'PLN'); ?>>PLN</option>
                                <option value="EUR" <?php selected((string) ($is_copy_agreement_mode ? ($agreement_profile['commission_unit'] ?? '') : ''), 'EUR'); ?>>EUR</option>
                                <option value="USD" <?php selected((string) ($is_copy_agreement_mode ? ($agreement_profile['commission_unit'] ?? '') : ''), 'USD'); ?>>USD</option>
                            </select>
                        </p>
                    </div>
                    <?php $render_agreement_commission_split_fields($agreement_prefill_commission_stage_rows, $agreement_prefill_commission_split_enabled, $agreement_prefill_transaction_type); ?>

                    <h4>Klienci powiazani z umowa</h4>
                    <p class="eocrm-form-field">
                        <label for="eocrm-agreement-client-search-new">Wyszukaj klienta</label>
                        <input id="eocrm-agreement-client-search-new" type="text" data-eocrm-agreement-client-search placeholder="Wpisz imie, nazwisko, firme, telefon lub e-mail">
                    </p>
                    <p>Wybierz co najmniej jednego klienta lub dodaj nowego klienta ponizej.</p>
                    <div class="eocrm-checkbox-list" data-eocrm-agreement-client-results>
                        <?php if (empty($agreement_client_options)) : ?>
                            <p>Brak klient&#243;w. Najpierw dodaj klienta w zakladce `Klienci`.</p>
                        <?php else : ?>
                            <?php foreach ($agreement_client_options as $client_option) : ?>
                                <?php $client_option_label = (string) ($client_option['label'] ?? ''); ?>
                                <?php $client_option_search = (string) ($client_option['search'] ?? $client_option_label); ?>
                                <?php $is_new_form_client_selected = in_array((int) ($client_option['id'] ?? 0), $agreement_prefilled_client_ids, true); ?>
                                <label class="eocrm-checkbox-item<?php echo $is_new_form_client_selected ? '' : ' is-hidden'; ?>" data-eocrm-client-option data-eocrm-client-label="<?php echo esc_attr(function_exists('mb_strtolower') ? mb_strtolower($client_option_search) : strtolower($client_option_search)); ?>">
                                    <input type="checkbox" name="client_ids[]" value="<?php echo (int) $client_option['id']; ?>" <?php checked($is_new_form_client_selected); ?>>
                                    <span><?php echo esc_html($client_option_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <p class="eocrm-muted is-hidden" data-eocrm-agreement-client-empty>Brak wynikow wyszukiwania klienta.</p>

                    <h4>Dodaj nowego klienta</h4>
                    <p class="eocrm-form-field-checkbox">
                        <label><input type="checkbox" name="agreement_add_new_client" value="1" data-eocrm-agreement-add-client> Dodaj nowego klienta do tej umowy</label>
                    </p>

                    <section class="eocrm-card eocrm-agreement-new-client-wrap is-hidden">
                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field">
                                <label>Typ klienta</label>
                                <select name="new_client_type" data-eocrm-agreement-client-type>
                                    <option value="person">Osoba fizyczna</option>
                                    <option value="company">Firma</option>
                                </select>
                            </p>
                        </div>

                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field eocrm-agreement-client-person">
                                <label>Imie</label>
                                <input type="text" name="new_client_first_name" data-eocrm-agreement-required-person>
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-person">
                                <label>Nazwisko</label>
                                <input type="text" name="new_client_last_name" data-eocrm-agreement-required-person>
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-company is-hidden">
                                <label>Nazwa firmy</label>
                                <input type="text" name="new_client_company_name" data-eocrm-agreement-required-company>
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-company is-hidden">
                                <label>Reprezentant</label>
                                <input type="text" name="new_client_representative_name">
                            </p>
                        </div>

                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field">
                                <label>Telefon</label>
                                <input type="text" name="new_client_phone" data-eocrm-agreement-required-main>
                            </p>
                            <p class="eocrm-form-field">
                                <label>E-mail</label>
                                <input type="email" name="new_client_email">
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-company is-hidden">
                                <label>WWW</label>
                                <input type="url" name="new_client_website" placeholder="https://">
                            </p>
                        </div>

                        <div class="eocrm-form-grid eocrm-agreement-client-person">
                            <p class="eocrm-form-field"><label>PESEL</label><input type="text" name="new_client_pesel"></p>
                            <p class="eocrm-form-field"><label>Rodzaj dokumentu</label>
                                <select name="new_client_document_type">
                                    <option value="">Wybierz</option>
                                    <option value="dowod_osobisty">Dowod osobisty</option>
                                    <option value="paszport">Paszport</option>
                                    <option value="karta_pobytu">Karta pobytu</option>
                                </select>
                            </p>
                            <p class="eocrm-form-field"><label>Numer dokumentu</label><input type="text" name="new_client_document_number"></p>
                        </div>

                        <div class="eocrm-form-grid eocrm-agreement-client-company is-hidden">
                            <p class="eocrm-form-field"><label>NIP</label><input type="text" name="new_client_nip"></p>
                            <p class="eocrm-form-field"><label>KRS</label><input type="text" name="new_client_krs"></p>
                            <p class="eocrm-form-field"><label>REGON</label><input type="text" name="new_client_regon"></p>
                        </div>

                        <h5>Adres glowny</h5>
                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_client_address_street" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_client_address_building_no" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_client_address_apartment_no"></p>
                            <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_client_address_postal_code" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_client_address_city" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_client_address_country" value="Polska"></p>
                        </div>

                        <h5>Adres korespondencyjny</h5>
                        <p class="eocrm-form-field">
                            <label><input type="checkbox" name="new_client_correspondence_same" value="1" data-eocrm-agreement-corr-same checked> Adres korespondencyjny taki sam</label>
                        </p>
                        <div class="eocrm-form-grid eocrm-agreement-client-corr is-hidden">
                            <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_client_corr_street" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_client_corr_building_no" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_client_corr_apartment_no"></p>
                            <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_client_corr_postal_code" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_client_corr_city" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_client_corr_country" value="Polska"></p>
                        </div>
                        <div class="eocrm-additional-new-clients" data-eocrm-additional-new-clients data-next-index="0"></div>
                        <p class="eocrm-actions-row">
                            <button class="eocrm-btn" type="button" data-eocrm-additional-new-client-add>Dodaj kolejnego nowego klienta</button>
                        </p>
                        <template data-eocrm-additional-new-client-template>
                            <section class="eocrm-card eocrm-agreement-additional-client" data-eocrm-additional-new-client>
                                <header class="eocrm-inline-section-head">
                                    <h5>Kolejny nowy klient</h5>
                                    <button class="eocrm-btn eocrm-btn-soft-danger" type="button" data-eocrm-additional-new-client-remove>Usu&#324;</button>
                                </header>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field">
                                        <label>Typ klienta</label>
                                        <select name="new_clients[__INDEX__][client_type]" data-eocrm-additional-client-type>
                                            <option value="person">Osoba fizyczna</option>
                                            <option value="company">Firma</option>
                                        </select>
                                    </p>
                                </div>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-person"><label>Imi&#281;</label><input type="text" name="new_clients[__INDEX__][first_name]" data-eocrm-additional-required-person></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-person"><label>Nazwisko</label><input type="text" name="new_clients[__INDEX__][last_name]" data-eocrm-additional-required-person></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-company is-hidden"><label>Nazwa firmy</label><input type="text" name="new_clients[__INDEX__][company_name]" data-eocrm-additional-required-company></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-company is-hidden"><label>Reprezentant</label><input type="text" name="new_clients[__INDEX__][representative_name]"></p>
                                </div>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field"><label>Telefon</label><input type="text" name="new_clients[__INDEX__][phone]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>E-mail</label><input type="email" name="new_clients[__INDEX__][email]"></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-company is-hidden"><label>WWW</label><input type="url" name="new_clients[__INDEX__][website]" placeholder="https://"></p>
                                </div>
                                <div class="eocrm-form-grid eocrm-agreement-additional-client-person">
                                    <p class="eocrm-form-field"><label>PESEL</label><input type="text" name="new_clients[__INDEX__][pesel]"></p>
                                    <p class="eocrm-form-field"><label>Rodzaj dokumentu</label><select name="new_clients[__INDEX__][document_type]"><option value="">Wybierz</option><option value="dowod_osobisty">Dow&#243;d osobisty</option><option value="paszport">Paszport</option><option value="karta_pobytu">Karta pobytu</option></select></p>
                                    <p class="eocrm-form-field"><label>Numer dokumentu</label><input type="text" name="new_clients[__INDEX__][document_number]"></p>
                                </div>
                                <div class="eocrm-form-grid eocrm-agreement-additional-client-company is-hidden">
                                    <p class="eocrm-form-field"><label>NIP</label><input type="text" name="new_clients[__INDEX__][nip]"></p>
                                    <p class="eocrm-form-field"><label>KRS</label><input type="text" name="new_clients[__INDEX__][krs]"></p>
                                    <p class="eocrm-form-field"><label>REGON</label><input type="text" name="new_clients[__INDEX__][regon]"></p>
                                </div>
                                <h5>Adres g&#322;&#243;wny</h5>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_clients[__INDEX__][address_street]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_clients[__INDEX__][address_building_no]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_clients[__INDEX__][address_apartment_no]"></p>
                                    <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_clients[__INDEX__][address_postal_code]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_clients[__INDEX__][address_city]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_clients[__INDEX__][address_country]" value="Polska"></p>
                                </div>
                                <h5>Adres korespondencyjny</h5>
                                <p class="eocrm-form-field"><label><input type="checkbox" name="new_clients[__INDEX__][correspondence_same]" value="1" data-eocrm-additional-corr-same checked> Adres korespondencyjny taki sam</label></p>
                                <div class="eocrm-form-grid eocrm-agreement-additional-client-corr is-hidden">
                                    <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_clients[__INDEX__][corr_street]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_clients[__INDEX__][corr_building_no]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_clients[__INDEX__][corr_apartment_no]"></p>
                                    <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_clients[__INDEX__][corr_postal_code]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_clients[__INDEX__][corr_city]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_clients[__INDEX__][corr_country]" value="Polska"></p>
                                </div>
                            </section>
                        </template>
                    </section>

                    <p>
                        <button class="eocrm-btn eocrm-btn-primary" type="submit"><?php echo $is_copy_agreement_mode ? 'Utworz kopie umowy' : 'Zapisz i DALEJ'; ?></button>
                    </p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($agreements_mode === 'edit-agreement' && is_array($agreement_profile)) : ?>
            <?php
            $agreement_selected_client_ids = [];
            foreach ($agreement_profile_clients as $agreement_client_row) {
                $agreement_selected_client_ids[] = (int) ($agreement_client_row['id'] ?? 0);
            }
            $edit_agreement_owner_id = (int) ($agreement_profile['owner_user_id'] ?? 0);
            $edit_agreement_owner_label = $resolve_owner_label($edit_agreement_owner_id);
            $edit_commission_split_enabled = ! empty($agreement_profile['commission_split_enabled']);
            $edit_commission_stage_rows = $edit_commission_split_enabled
                ? $parse_commission_stage_rows((string) ($agreement_profile['commission_stages_json'] ?? ''))
                : [];
            ?>
            <section class="eocrm-card">
                <h3>Edycja umowy</h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'mode' => 'edit-agreement', 'agreement_id' => (int) ($agreement_profile['id'] ?? 0)], $current_url)); ?>" data-eocrm-agreement-form>
                    <?php wp_nonce_field('eocrm_update_agreement', 'eocrm_agreement_update_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="update_agreement">
                    <input type="hidden" name="agreement_id" value="<?php echo (int) ($agreement_profile['id'] ?? 0); ?>">

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Numer umowy</label>
                            <input type="text" name="agreement_number" value="<?php echo esc_attr((string) ($agreement_profile['agreement_number'] ?? '')); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Typ transakcji</label>
                            <input type="text" value="<?php echo esc_attr((string) ($agreement_profile['transaction_type'] ?? '')); ?>" readonly>
                        </p>
                        <?php if ($is_admin_user) : ?>
                            <p class="eocrm-form-field">
                                <label>Opiekun</label>
                                <select name="owner_user_id" required>
                                    <option value="">Wybierz opiekuna</option>
                                    <?php if ($edit_agreement_owner_id > 0 && ! in_array($edit_agreement_owner_id, $owner_option_ids, true)) : ?>
                                        <option value="<?php echo esc_attr((string) $edit_agreement_owner_id); ?>" selected><?php echo esc_html($edit_agreement_owner_label !== '' ? $edit_agreement_owner_label : ('Uzytkownik #' . (string) $edit_agreement_owner_id)); ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($owner_user_options as $owner_option_row) : ?>
                                        <?php $owner_option_id = (int) ($owner_option_row['id'] ?? 0); ?>
                                        <?php if ($owner_option_id <= 0) {
                                            continue;
                                        } ?>
                                        <option value="<?php echo esc_attr((string) $owner_option_id); ?>" <?php selected($owner_option_id, $edit_agreement_owner_id); ?>>
                                            <?php echo esc_html((string) ($owner_option_row['label'] ?? ('Uzytkownik #' . (string) $owner_option_id))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Data zawarcia</label>
                            <input type="date" name="date_signed" value="<?php echo esc_attr((string) ($agreement_profile['date_signed'] ?? '')); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Data zakonczenia</label>
                            <input type="date" name="date_end" data-eocrm-date-end value="<?php echo esc_attr((string) ($agreement_profile['date_end'] ?? '')); ?>" <?php echo ! empty($agreement_profile['is_indefinite']) ? '' : 'required'; ?>>
                        </p>
                    </div>

                    <p class="eocrm-form-field-checkbox">
                        <label>
                            <input type="checkbox" name="is_indefinite" value="1" data-eocrm-indefinite <?php checked(! empty($agreement_profile['is_indefinite'])); ?>>
                            Umowa bezterminowa
                        </label>
                    </p>

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Wylacznosc</label>
                            <span class="eocrm-choice-toggle">
                                <label class="eocrm-choice-toggle-item">
                                    <input type="radio" name="is_exclusive" value="1" <?php checked(! empty($agreement_profile['is_exclusive'])); ?>>
                                    <span>Tak</span>
                                </label>
                                <label class="eocrm-choice-toggle-item">
                                    <input type="radio" name="is_exclusive" value="0" <?php checked(empty($agreement_profile['is_exclusive'])); ?>>
                                    <span>Nie</span>
                                </label>
                            </span>
                        </p>
                    </div>

                    <h4>Wysokosc prowizji</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Kwota</label>
                            <input type="text" name="commission_amount" value="<?php echo esc_attr((string) ($agreement_profile['commission_amount'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field">
                            <label>Jednostka</label>
                            <select name="commission_unit">
                                <option value="">Wybierz</option>
                                <option value="%" <?php selected((string) ($agreement_profile['commission_unit'] ?? ''), '%'); ?>>%</option>
                                <option value="PLN" <?php selected((string) ($agreement_profile['commission_unit'] ?? ''), 'PLN'); ?>>PLN</option>
                                <option value="EUR" <?php selected((string) ($agreement_profile['commission_unit'] ?? ''), 'EUR'); ?>>EUR</option>
                                <option value="USD" <?php selected((string) ($agreement_profile['commission_unit'] ?? ''), 'USD'); ?>>USD</option>
                            </select>
                        </p>
                    </div>
                    <?php $render_agreement_commission_split_fields($edit_commission_stage_rows, $edit_commission_split_enabled, (string) ($agreement_profile['transaction_type'] ?? '')); ?>

                    <h4>Klienci powiazani z umowa</h4>
                    <p class="eocrm-form-field">
                        <label for="eocrm-agreement-client-search-edit">Wyszukaj klienta</label>
                        <input id="eocrm-agreement-client-search-edit" type="text" data-eocrm-agreement-client-search placeholder="Wpisz imie, nazwisko, firme, telefon lub e-mail">
                    </p>
                    <div class="eocrm-checkbox-list" data-eocrm-agreement-client-results>
                        <?php if (empty($agreement_client_options)) : ?>
                            <p>Brak klient&#243;w.</p>
                        <?php else : ?>
                            <?php foreach ($agreement_client_options as $client_option) : ?>
                                <?php $option_client_id = (int) ($client_option['id'] ?? 0); ?>
                                <?php $client_option_label = (string) ($client_option['label'] ?? 'Klient'); ?>
                                <?php $client_option_search = (string) ($client_option['search'] ?? $client_option_label); ?>
                                <?php $is_edit_client_selected = in_array($option_client_id, $agreement_selected_client_ids, true); ?>
                                <label class="eocrm-checkbox-item<?php echo $is_edit_client_selected ? '' : ' is-hidden'; ?>" data-eocrm-client-option data-eocrm-client-label="<?php echo esc_attr(function_exists('mb_strtolower') ? mb_strtolower($client_option_search) : strtolower($client_option_search)); ?>">
                                            <input type="checkbox" name="client_ids[]" value="<?php echo esc_attr((string) $option_client_id); ?>" <?php checked($is_edit_client_selected); ?>>
                                    <span><?php echo esc_html($client_option_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <p class="eocrm-muted is-hidden" data-eocrm-agreement-client-empty>Brak wynikow wyszukiwania klienta.</p>

                    <h4>Dodaj nowego klienta</h4>
                    <p class="eocrm-form-field-checkbox">
                        <label><input type="checkbox" name="agreement_add_new_client" value="1" data-eocrm-agreement-add-client> Dodaj nowego klienta do tej umowy</label>
                    </p>

                    <section class="eocrm-card eocrm-agreement-new-client-wrap is-hidden">
                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field">
                                <label>Typ klienta</label>
                                <select name="new_client_type" data-eocrm-agreement-client-type>
                                    <option value="person">Osoba fizyczna</option>
                                    <option value="company">Firma</option>
                                </select>
                            </p>
                        </div>

                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field eocrm-agreement-client-person">
                                <label>Imie</label>
                                <input type="text" name="new_client_first_name" data-eocrm-agreement-required-person>
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-person">
                                <label>Nazwisko</label>
                                <input type="text" name="new_client_last_name" data-eocrm-agreement-required-person>
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-company is-hidden">
                                <label>Nazwa firmy</label>
                                <input type="text" name="new_client_company_name" data-eocrm-agreement-required-company>
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-company is-hidden">
                                <label>Reprezentant</label>
                                <input type="text" name="new_client_representative_name">
                            </p>
                        </div>

                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field">
                                <label>Telefon</label>
                                <input type="text" name="new_client_phone" data-eocrm-agreement-required-main>
                            </p>
                            <p class="eocrm-form-field">
                                <label>E-mail</label>
                                <input type="email" name="new_client_email">
                            </p>
                            <p class="eocrm-form-field eocrm-agreement-client-company is-hidden">
                                <label>WWW</label>
                                <input type="url" name="new_client_website" placeholder="https://">
                            </p>
                        </div>

                        <div class="eocrm-form-grid eocrm-agreement-client-person">
                            <p class="eocrm-form-field"><label>PESEL</label><input type="text" name="new_client_pesel"></p>
                            <p class="eocrm-form-field"><label>Rodzaj dokumentu</label>
                                <select name="new_client_document_type">
                                    <option value="">Wybierz</option>
                                    <option value="dowod_osobisty">Dowod osobisty</option>
                                    <option value="paszport">Paszport</option>
                                    <option value="karta_pobytu">Karta pobytu</option>
                                </select>
                            </p>
                            <p class="eocrm-form-field"><label>Numer dokumentu</label><input type="text" name="new_client_document_number"></p>
                        </div>

                        <div class="eocrm-form-grid eocrm-agreement-client-company is-hidden">
                            <p class="eocrm-form-field"><label>NIP</label><input type="text" name="new_client_nip"></p>
                            <p class="eocrm-form-field"><label>KRS</label><input type="text" name="new_client_krs"></p>
                            <p class="eocrm-form-field"><label>REGON</label><input type="text" name="new_client_regon"></p>
                        </div>

                        <h5>Adres glowny</h5>
                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_client_address_street" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_client_address_building_no" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_client_address_apartment_no"></p>
                            <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_client_address_postal_code" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_client_address_city" data-eocrm-agreement-required-main></p>
                            <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_client_address_country" value="Polska"></p>
                        </div>

                        <h5>Adres korespondencyjny</h5>
                        <p class="eocrm-form-field">
                            <label><input type="checkbox" name="new_client_correspondence_same" value="1" data-eocrm-agreement-corr-same checked> Adres korespondencyjny taki sam</label>
                        </p>
                        <div class="eocrm-form-grid eocrm-agreement-client-corr is-hidden">
                            <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_client_corr_street" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_client_corr_building_no" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_client_corr_apartment_no"></p>
                            <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_client_corr_postal_code" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_client_corr_city" data-eocrm-agreement-corr-field></p>
                            <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_client_corr_country" value="Polska"></p>
                        </div>
                        <div class="eocrm-additional-new-clients" data-eocrm-additional-new-clients data-next-index="0"></div>
                        <p class="eocrm-actions-row">
                            <button class="eocrm-btn" type="button" data-eocrm-additional-new-client-add>Dodaj kolejnego nowego klienta</button>
                        </p>
                        <template data-eocrm-additional-new-client-template>
                            <section class="eocrm-card eocrm-agreement-additional-client" data-eocrm-additional-new-client>
                                <header class="eocrm-inline-section-head">
                                    <h5>Kolejny nowy klient</h5>
                                    <button class="eocrm-btn eocrm-btn-soft-danger" type="button" data-eocrm-additional-new-client-remove>Usu&#324;</button>
                                </header>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field">
                                        <label>Typ klienta</label>
                                        <select name="new_clients[__INDEX__][client_type]" data-eocrm-additional-client-type>
                                            <option value="person">Osoba fizyczna</option>
                                            <option value="company">Firma</option>
                                        </select>
                                    </p>
                                </div>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-person"><label>Imi&#281;</label><input type="text" name="new_clients[__INDEX__][first_name]" data-eocrm-additional-required-person></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-person"><label>Nazwisko</label><input type="text" name="new_clients[__INDEX__][last_name]" data-eocrm-additional-required-person></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-company is-hidden"><label>Nazwa firmy</label><input type="text" name="new_clients[__INDEX__][company_name]" data-eocrm-additional-required-company></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-company is-hidden"><label>Reprezentant</label><input type="text" name="new_clients[__INDEX__][representative_name]"></p>
                                </div>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field"><label>Telefon</label><input type="text" name="new_clients[__INDEX__][phone]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>E-mail</label><input type="email" name="new_clients[__INDEX__][email]"></p>
                                    <p class="eocrm-form-field eocrm-agreement-additional-client-company is-hidden"><label>WWW</label><input type="url" name="new_clients[__INDEX__][website]" placeholder="https://"></p>
                                </div>
                                <div class="eocrm-form-grid eocrm-agreement-additional-client-person">
                                    <p class="eocrm-form-field"><label>PESEL</label><input type="text" name="new_clients[__INDEX__][pesel]"></p>
                                    <p class="eocrm-form-field"><label>Rodzaj dokumentu</label><select name="new_clients[__INDEX__][document_type]"><option value="">Wybierz</option><option value="dowod_osobisty">Dow&#243;d osobisty</option><option value="paszport">Paszport</option><option value="karta_pobytu">Karta pobytu</option></select></p>
                                    <p class="eocrm-form-field"><label>Numer dokumentu</label><input type="text" name="new_clients[__INDEX__][document_number]"></p>
                                </div>
                                <div class="eocrm-form-grid eocrm-agreement-additional-client-company is-hidden">
                                    <p class="eocrm-form-field"><label>NIP</label><input type="text" name="new_clients[__INDEX__][nip]"></p>
                                    <p class="eocrm-form-field"><label>KRS</label><input type="text" name="new_clients[__INDEX__][krs]"></p>
                                    <p class="eocrm-form-field"><label>REGON</label><input type="text" name="new_clients[__INDEX__][regon]"></p>
                                </div>
                                <h5>Adres g&#322;&#243;wny</h5>
                                <div class="eocrm-form-grid">
                                    <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_clients[__INDEX__][address_street]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_clients[__INDEX__][address_building_no]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_clients[__INDEX__][address_apartment_no]"></p>
                                    <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_clients[__INDEX__][address_postal_code]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_clients[__INDEX__][address_city]" data-eocrm-additional-required-main></p>
                                    <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_clients[__INDEX__][address_country]" value="Polska"></p>
                                </div>
                                <h5>Adres korespondencyjny</h5>
                                <p class="eocrm-form-field"><label><input type="checkbox" name="new_clients[__INDEX__][correspondence_same]" value="1" data-eocrm-additional-corr-same checked> Adres korespondencyjny taki sam</label></p>
                                <div class="eocrm-form-grid eocrm-agreement-additional-client-corr is-hidden">
                                    <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="new_clients[__INDEX__][corr_street]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Numer</label><input type="text" name="new_clients[__INDEX__][corr_building_no]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="new_clients[__INDEX__][corr_apartment_no]"></p>
                                    <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="new_clients[__INDEX__][corr_postal_code]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="new_clients[__INDEX__][corr_city]" data-eocrm-additional-corr-field></p>
                                    <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="new_clients[__INDEX__][corr_country]" value="Polska"></p>
                                </div>
                            </section>
                        </template>
                    </section>

                    <p>
                        <button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz zmiany umowy</button>
                    </p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($agreement_id > 0 && ! is_array($agreement_profile)) : ?>
            <div class="eocrm-alert eocrm-alert-error">Nie znaleziono profilu umowy.</div>
        <?php endif; ?>

        <?php if (is_array($agreement_profile)) : ?>
            <?php
            $agreement_owner = get_userdata((int) ($agreement_profile['owner_user_id'] ?? 0));
            $agreement_profile_id = (int) ($agreement_profile['id'] ?? 0);
            $agreement_profile_number = (string) ($agreement_profile['agreement_number'] ?? '');
            $agreement_profile_tx_code = strtoupper(trim((string) ($agreement_profile['transaction_type'] ?? '')));
            $agreement_profile_tx_label_map = [
                'SPRZEDAZ' => 'Sprzeda&#380;',
                'KUPNO' => 'Kupno',
                'WYNAJEM' => 'Wynajem',
                'NAJEM' => 'Najem',
            ];
            $agreement_profile_tx_label = $agreement_profile_tx_label_map[$agreement_profile_tx_code] ?? ($agreement_profile_tx_code !== '' ? ucfirst(strtolower($agreement_profile_tx_code)) : '-');

            $agreement_profile_current_stage = (string) ($agreement_profile['current_stage'] ?? '');
            $agreement_profile_finished = $is_agreement_stage_finished($agreement_profile_current_stage);
            $agreement_profile_status_label = $agreement_profile_finished ? 'Zako&#324;czona' : 'W trakcie realizacji';
            $agreement_profile_status_class = $agreement_profile_finished ? 'is-done' : 'is-progress';

            $agreement_owner_name = $agreement_owner instanceof WP_User ? (string) $agreement_owner->display_name : '';
            $agreement_owner_avatar = $agreement_owner instanceof WP_User ? $resolve_owner_photo_url((int) $agreement_owner->ID, 'medium') : '';
            $agreement_owner_initial = $agreement_owner_name !== '' ? mb_strtoupper(mb_substr($agreement_owner_name, 0, 1)) : '';

            $agreement_commission_display = '';
            if ((float) ($agreement_profile['commission_amount'] ?? 0) > 0) {
                $agreement_commission_unit = (string) ($agreement_profile['commission_unit'] ?? '');
                $agreement_commission_amount = rtrim(rtrim(number_format((float) ($agreement_profile['commission_amount'] ?? 0), 2, '.', ''), '0'), '.');
                $agreement_commission_display = $agreement_commission_amount . ' ' . $agreement_commission_unit;
            }
            $agreement_commission_stage_rows = ! empty($agreement_profile['commission_split_enabled'])
                ? $parse_commission_stage_rows((string) ($agreement_profile['commission_stages_json'] ?? ''))
                : [];
            $agreement_used_commission_stage_keys = [];
            foreach ($agreement_profile_transactions as $agreement_profile_transaction_row_for_stage) {
                $used_stage_names = [];
                $payment_payload_for_stage = $parse_commission_stage_payment_payload((string) ($agreement_profile_transaction_row_for_stage['commission_stages_json'] ?? ''));
                if (! empty($payment_payload_for_stage['covered_stage_names'])) {
                    $used_stage_names = (array) $payment_payload_for_stage['covered_stage_names'];
                } else {
                    $used_stage_names[] = trim((string) ($agreement_profile_transaction_row_for_stage['commission_stage_name'] ?? ''));
                }
                foreach ($used_stage_names as $used_stage_name) {
                    $used_stage_key = $normalize_commission_stage_key((string) $used_stage_name);
                    if ($used_stage_key !== '' && ! in_array($used_stage_key, $agreement_used_commission_stage_keys, true)) {
                        $agreement_used_commission_stage_keys[] = $used_stage_key;
                    }
                }
            }
            $agreement_commission_stage_names = [];
            foreach ($agreement_commission_stage_rows as $agreement_commission_stage_row_for_prompt) {
                $prompt_stage_name = trim((string) ($agreement_commission_stage_row_for_prompt['stage_name'] ?? ''));
                $prompt_stage_key = $normalize_commission_stage_key($prompt_stage_name);
                if ($prompt_stage_name !== '' && ! in_array($prompt_stage_key, $agreement_used_commission_stage_keys, true)) {
                    $agreement_commission_stage_names[] = $prompt_stage_name;
                }
            }
            $agreement_commission_stage_names_json = wp_json_encode($agreement_commission_stage_names);
            if (! is_string($agreement_commission_stage_names_json) || $agreement_commission_stage_names_json === '') {
                $agreement_commission_stage_names_json = '[]';
            }
            $agreement_commission_has_plan = ! empty($agreement_commission_stage_rows);
            $agreement_commission_all_paid = $agreement_commission_has_plan && empty($agreement_commission_stage_names);
            $agreement_partial_transaction_id = 0;
            foreach ($agreement_profile_transactions as $agreement_profile_transaction_row_for_partial) {
                $partial_stage_name = trim((string) ($agreement_profile_transaction_row_for_partial['commission_stage_name'] ?? ''));
                if ($partial_stage_name !== '' && ! $is_settlement_commission_stage($partial_stage_name)) {
                    $agreement_partial_transaction_id = (int) ($agreement_profile_transaction_row_for_partial['id'] ?? 0);
                    break;
                }
            }
            $agreement_current_open_commission_stage = '';
            $agreement_current_stage_key_for_prompt = $normalize_commission_stage_key($agreement_profile_current_stage);
            if ($agreement_current_stage_key_for_prompt !== '' && ! in_array($agreement_current_stage_key_for_prompt, $agreement_used_commission_stage_keys, true)) {
                foreach ($agreement_commission_stage_rows as $agreement_commission_stage_row_for_current) {
                    $current_prompt_stage_name = trim((string) ($agreement_commission_stage_row_for_current['stage_name'] ?? ''));
                    if ($current_prompt_stage_name !== '' && $normalize_commission_stage_key($current_prompt_stage_name) === $agreement_current_stage_key_for_prompt) {
                        $agreement_current_open_commission_stage = $current_prompt_stage_name;
                        break;
                    }
                }
            }
            $agreement_new_transaction_url = add_query_arg(['crm' => 'transactions', 'mode' => 'new-transaction', 'agreement_id' => $agreement_profile_id], $current_url);
            $agreement_existing_partial_url = '';
            if ($agreement_partial_transaction_id > 0) {
                $agreement_existing_partial_args = [
                    'crm' => 'transactions',
                    'mode' => 'edit-transaction',
                    'transaction_id' => $agreement_partial_transaction_id,
                ];
                if ($agreement_current_open_commission_stage !== '') {
                    $agreement_existing_partial_args['commission_stage'] = $agreement_current_open_commission_stage;
                }
                $agreement_existing_partial_url = add_query_arg($agreement_existing_partial_args, $current_url);
            }

            $agreement_date_signed_p = (string) ($agreement_profile['date_signed'] ?? '');
            $agreement_date_end_p = (string) ($agreement_profile['date_end'] ?? '');
            $agreement_is_indefinite_p = ! empty($agreement_profile['is_indefinite']);
            $agreement_is_exclusive_p = ! empty($agreement_profile['is_exclusive']);

            $agreement_format_date = static function (string $date_raw): string {
                if ($date_raw === '' || $date_raw === '0000-00-00') {
                    return '';
                }
                $ts = strtotime($date_raw);
                return $ts !== false ? (string) date_i18n('d.m.Y', $ts) : '';
            };
            $agreement_date_signed_pretty = $agreement_format_date($agreement_date_signed_p);
            $agreement_date_end_pretty = $agreement_format_date($agreement_date_end_p);

            // Build stage data for timeline
            $agreement_stages_for_render = is_array($agreement_stage_options) && ! empty($agreement_stage_options) ? array_values($agreement_stage_options) : [];
            $agreement_stage_data_render = [];
            $agreement_stage_total_render = 0;
            $agreement_stage_completed_render = 0;
            $agreement_stage_progress_pct = 0;
            if (! empty($agreement_stages_for_render)) {
                $agreement_stage_dates_map = [];
                if (is_array($agreement_profile_stage_history)) {
                    foreach ($agreement_profile_stage_history as $hist_row_a) {
                        if (! is_array($hist_row_a)) {
                            continue;
                        }
                        $hn_a = (string) ($hist_row_a['stage_name'] ?? '');
                        $hd_a = (string) ($hist_row_a['stage_date'] ?? '');
                        if ($hn_a !== '' && $hd_a !== '' && ! isset($agreement_stage_dates_map[$hn_a])) {
                            $agreement_stage_dates_map[$hn_a] = $hd_a;
                        }
                    }
                }
                $agreement_current_stage_idx = -1;
                foreach ($agreement_stages_for_render as $stage_idx_a => $stage_name_a) {
                    if ((string) $stage_name_a === $agreement_profile_current_stage) {
                        $agreement_current_stage_idx = $stage_idx_a;
                        break;
                    }
                }
                $agreement_stage_total_render = count($agreement_stages_for_render);
                foreach ($agreement_stages_for_render as $stage_idx_a => $stage_name_a) {
                    $state_a = 'upcoming';
                    if ($agreement_current_stage_idx >= 0 && $stage_idx_a < $agreement_current_stage_idx) {
                        $state_a = 'completed';
                    } elseif ($agreement_current_stage_idx >= 0 && $stage_idx_a === $agreement_current_stage_idx) {
                        $state_a = 'current';
                    }
                    if ($agreement_profile_finished && $stage_idx_a === $agreement_current_stage_idx) {
                        $state_a = 'completed';
                    }
                    $agreement_stage_data_render[] = [
                        'name' => (string) $stage_name_a,
                        'state' => $state_a,
                        'date' => isset($agreement_stage_dates_map[(string) $stage_name_a]) ? (string) $agreement_stage_dates_map[(string) $stage_name_a] : '',
                        'index' => $stage_idx_a + 1,
                    ];
                    if ($state_a === 'completed') {
                        $agreement_stage_completed_render++;
                    }
                }
                if ($agreement_stage_total_render > 0) {
                    $effective_position_a = $agreement_current_stage_idx >= 0 ? $agreement_current_stage_idx : ($agreement_stage_completed_render > 0 ? $agreement_stage_completed_render - 1 : 0);
                    if ($agreement_profile_finished) {
                        $agreement_stage_progress_pct = 100;
                    } else {
                        $agreement_stage_progress_pct = (int) round((max(0, $effective_position_a) / max(1, $agreement_stage_total_render - 1)) * 100);
                    }
                }
            }
            ?>

            <section class="eocrm-prop-profile" data-eocrm-agreement-profile>

                <header class="eocrm-prop-profile-hero">
                    <div class="eocrm-prop-profile-hero__main">
                        <span class="eocrm-prop-profile-hero__eyebrow">Profil umowy</span>
                        <h2 class="eocrm-prop-profile-hero__title">
                            <?php echo esc_html($agreement_profile_number !== '' ? $agreement_profile_number : ('Umowa #' . $agreement_profile_id)); ?>
                            <span class="eocrm-prop-status-pill <?php echo esc_attr($agreement_profile_status_class); ?>"><?php echo wp_kses_post($agreement_profile_status_label); ?></span>
                        </h2>
                        <p class="eocrm-prop-profile-hero__meta">
                            <?php echo wp_kses_post($agreement_profile_tx_label); ?>
                            <?php if ($agreement_date_signed_pretty !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                Zawarta: <strong><?php echo esc_html($agreement_date_signed_pretty); ?></strong>
                            <?php endif; ?>
                            <?php if ($agreement_is_indefinite_p) : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                <strong>Bezterminowa</strong>
                            <?php elseif ($agreement_date_end_pretty !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                Wa&#380;na do: <strong><?php echo esc_html($agreement_date_end_pretty); ?></strong>
                            <?php endif; ?>
                            <?php if ($agreement_owner_name !== '') : ?>
                                <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                Opiekun: <strong><?php echo esc_html($agreement_owner_name); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="eocrm-prop-profile-hero__actions">
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements'], $current_url)); ?>">&larr; Powr&#243;t</a>
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_profile_id, 'mode' => 'edit-agreement'], $current_url)); ?>">Edytuj</a>
                        <a
                            class="eocrm-btn eocrm-btn-primary"
                            href="<?php echo esc_url($agreement_new_transaction_url); ?>"
                            <?php if ($agreement_existing_partial_url !== '') : ?>
                                data-eocrm-transaction-choice-trigger
                                data-existing-url="<?php echo esc_url($agreement_existing_partial_url); ?>"
                                data-new-url="<?php echo esc_url($agreement_new_transaction_url); ?>"
                            <?php endif; ?>
                        >Dodaj transakcj&#281;</a>
                        <?php if ($is_admin_user) : ?>
                            <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_profile_id, 'mode' => 'copy-agreement'], $current_url)); ?>">Kopiuj</a>
                            <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_profile_id], $current_url)); ?>" onsubmit="return confirm('Czy na pewno usunac umowe?');" class="eocrm-prop-profile-hero__delete">
                                <?php wp_nonce_field('eocrm_delete_agreement', 'eocrm_agreement_delete_nonce'); ?>
                                <input type="hidden" name="eocrm_action" value="delete_agreement">
                                <input type="hidden" name="agreement_id" value="<?php echo (int) $agreement_profile_id; ?>">
                                <button class="eocrm-btn eocrm-btn-danger" type="submit">Usu&#324;</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (! empty($agreement_stage_data_render)) : ?>
                    <section class="eocrm-prop-stages" aria-label="Etapy umowy">
                        <header class="eocrm-prop-stages__head">
                            <div>
                                <h3>Etapy umowy</h3>
                                <p class="eocrm-prop-stages__hint">
                                    Aktualny etap: <strong><?php echo esc_html($agreement_profile_current_stage !== '' ? $agreement_profile_current_stage : '-'); ?></strong>
                                </p>
                            </div>
                            <div class="eocrm-prop-stages__progress">
                                <span class="eocrm-prop-stages__progress-label">Post&#281;p</span>
                                <span class="eocrm-prop-stages__progress-value"><?php echo esc_html((string) $agreement_stage_progress_pct); ?>%</span>
                                <span class="eocrm-prop-stages__progress-track">
                                    <span class="eocrm-prop-stages__progress-fill" style="width: <?php echo esc_attr((string) $agreement_stage_progress_pct); ?>%;"></span>
                                </span>
                            </div>
                        </header>
                        <ol class="eocrm-prop-stages__list">
                            <?php foreach ($agreement_stage_data_render as $stage_row_a) :
                                $stage_state_a = (string) ($stage_row_a['state'] ?? 'upcoming');
                                $stage_label_a = (string) ($stage_row_a['name'] ?? '');
                                $stage_date_raw_a = (string) ($stage_row_a['date'] ?? '');
                                $stage_idx_num_a = (int) ($stage_row_a['index'] ?? 0);
                                $stage_date_pretty_a = $agreement_format_date($stage_date_raw_a);
                            ?>
                                <li class="eocrm-prop-stage is-<?php echo esc_attr($stage_state_a); ?>">
                                    <span class="eocrm-prop-stage__indicator" aria-hidden="true">
                                        <?php if ($stage_state_a === 'completed') : ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php else : ?>
                                            <span class="eocrm-prop-stage__num"><?php echo esc_html((string) $stage_idx_num_a); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="eocrm-prop-stage__body">
                                        <span class="eocrm-prop-stage__name"><?php echo esc_html($stage_label_a); ?></span>
                                        <span class="eocrm-prop-stage__sub">
                                            <?php if ($stage_state_a === 'completed') :
                                                echo esc_html($stage_date_pretty_a !== '' ? $stage_date_pretty_a : 'Zako&#324;czony');
                                            elseif ($stage_state_a === 'current') :
                                                echo esc_html($stage_date_pretty_a !== '' ? ('Od ' . $stage_date_pretty_a) : 'W trakcie');
                                            else :
                                                echo 'Oczekuje';
                                            endif; ?>
                                        </span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <div class="eocrm-agreement-details-row">
                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full eocrm-prop-detail-card--export eocrm-agreement-details-row__main">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128203;</span>
                            <h3>Szczeg&oacute;&#322;y umowy</h3>
                        </header>
                        <dl class="eocrm-prop-detail-card__defs eocrm-agreement-defs-grid">
                            <div><dt>Numer umowy</dt><dd><?php echo esc_html($agreement_profile_number); ?></dd></div>
                            <div><dt>Typ transakcji</dt><dd><?php echo wp_kses_post($agreement_profile_tx_label); ?></dd></div>
                            <div><dt>Data zawarcia</dt><dd><?php echo esc_html($agreement_date_signed_pretty !== '' ? $agreement_date_signed_pretty : '-'); ?></dd></div>
                            <div><dt>Data zako&#324;czenia</dt><dd>
                                <?php if ($agreement_is_indefinite_p) : ?>
                                    Bezterminowa
                                <?php else : ?>
                                    <?php echo esc_html($agreement_date_end_pretty !== '' ? $agreement_date_end_pretty : '-'); ?>
                                <?php endif; ?>
                            </dd></div>
                            <div><dt>Wy&#322;&#261;czno&#347;&#263;</dt><dd><?php echo $agreement_is_exclusive_p ? 'Tak' : 'Nie'; ?></dd></div>
                            <div><dt>Prowizja</dt><dd><?php echo esc_html($agreement_commission_display !== '' ? $agreement_commission_display : '-'); ?></dd></div>
                            <?php if (! empty($agreement_commission_stage_rows)) : ?>
                                <div class="eocrm-agreement-commission-def"><dt>Podzia&#322; prowizji</dt><dd>
                                    <span class="eocrm-agreement-commission-list">
                                        <?php foreach ($agreement_commission_stage_rows as $agreement_commission_stage_row) : ?>
                                            <?php $agreement_commission_stage_name = (string) ($agreement_commission_stage_row['stage_name'] ?? ''); ?>
                                            <span class="eocrm-agreement-commission-line">
                                                <span class="eocrm-agreement-commission-line__stage" title="<?php echo esc_attr($agreement_commission_stage_name); ?>"><?php echo esc_html($agreement_commission_stage_name); ?></span>
                                                <strong><?php echo esc_html($format_commission_stage_value($agreement_commission_stage_row)); ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </span>
                                </dd></div>
                            <?php endif; ?>
                            <div><dt>Aktualny etap</dt><dd>
                                <span class="eocrm-prop-stage-pill <?php echo esc_attr($agreement_profile_status_class); ?>">
                                    <?php echo esc_html($agreement_profile_current_stage !== '' ? $agreement_profile_current_stage : '-'); ?>
                                </span>
                            </dd></div>
                            <div><dt>Opiekun</dt><dd><?php echo esc_html($agreement_owner_name !== '' ? $agreement_owner_name : '-'); ?></dd></div>
                            <div><dt>Utworzono</dt><dd><?php echo esc_html((string) ($agreement_profile['created_at'] ?? '-')); ?></dd></div>
                            <?php if (isset($agreement_profile['updated_at']) && (string) $agreement_profile['updated_at'] !== '') : ?>
                                <div><dt>Ostatnia aktualizacja</dt><dd><?php echo esc_html((string) $agreement_profile['updated_at']); ?></dd></div>
                            <?php endif; ?>
                        </dl>
                    </article>

                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full eocrm-prop-detail-card--history eocrm-agreement-details-row__side">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128337;</span>
                            <h3>Historia etap&oacute;w</h3>
                            <p class="eocrm-prop-detail-card__hint">Wszystkie zarejestrowane zmiany etap&oacute;w umowy.</p>
                        </header>
                        <?php if (empty($agreement_profile_stage_history)) : ?>
                            <p class="eocrm-prop-detail-empty">Brak historii etap&oacute;w.</p>
                        <?php else : ?>
                            <ol class="eocrm-agreement-history">
                                <?php foreach ($agreement_profile_stage_history as $history_row_a) :
                                    $hist_id_a = (int) ($history_row_a['id'] ?? 0);
                                    $hist_name_a = (string) ($history_row_a['stage_name'] ?? '');
                                    $hist_date_raw_a = (string) ($history_row_a['stage_date'] ?? '');
                                    $hist_date_pretty_a = $agreement_format_date($hist_date_raw_a);
                                    $hist_created_a = (string) ($history_row_a['created_at'] ?? '');
                                ?>
                                    <li class="eocrm-agreement-history__item">
                                        <span class="eocrm-agreement-history__dot" aria-hidden="true"></span>
                                        <div class="eocrm-agreement-history__body">
                                            <span class="eocrm-agreement-history__date"><?php echo esc_html($hist_date_pretty_a !== '' ? $hist_date_pretty_a : ($hist_date_raw_a !== '' ? $hist_date_raw_a : '-')); ?></span>
                                            <span class="eocrm-agreement-history__name"><?php echo esc_html($hist_name_a); ?></span>
                                            <?php if ($hist_created_a !== '') : ?>
                                                <span class="eocrm-agreement-history__meta">Zarejestrowano: <?php echo esc_html($hist_created_a); ?></span>
                                            <?php endif; ?>
                                            <?php if ($is_admin_user && $hist_id_a > 0) : ?>
                                                <details class="eocrm-agreement-history__admin">
                                                    <summary>Edytuj etap</summary>
                                                    <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_profile_id], $current_url)); ?>" class="eocrm-agreement-history__form">
                                                        <?php wp_nonce_field('eocrm_update_agreement_stage_history', 'eocrm_stage_history_nonce'); ?>
                                                        <input type="hidden" name="eocrm_action" value="update_agreement_stage_history">
                                                        <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreement_profile_id); ?>">
                                                        <input type="hidden" name="stage_history_id" value="<?php echo esc_attr((string) $hist_id_a); ?>">
                                                        <select name="stage_name" required>
                                                            <?php foreach ($agreement_stage_options as $history_stage_option) : ?>
                                                                <option value="<?php echo esc_attr($history_stage_option); ?>" <?php selected($hist_name_a, $history_stage_option); ?>><?php echo esc_html($history_stage_option); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="date" name="stage_date" value="<?php echo esc_attr($hist_date_raw_a); ?>" required>
                                                        <button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz</button>
                                                    </form>
                                                    <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_profile_id], $current_url)); ?>" class="eocrm-agreement-history__form" onsubmit="return confirm('Czy na pewno usunac ten etap z historii?');">
                                                        <?php wp_nonce_field('eocrm_delete_agreement_stage_history', 'eocrm_stage_history_delete_nonce'); ?>
                                                        <input type="hidden" name="eocrm_action" value="delete_agreement_stage_history">
                                                        <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreement_profile_id); ?>">
                                                        <input type="hidden" name="stage_history_id" value="<?php echo esc_attr((string) $hist_id_a); ?>">
                                                        <button class="eocrm-btn eocrm-btn-soft-danger" type="submit">Usu&#324; etap</button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </article>
                </div>

                <div class="eocrm-prop-quick-stats">
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--blue">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128197;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Daty obowi&#261;zywania</span>
                            <span class="eocrm-prop-quick-stat__value eocrm-prop-quick-stat__value--small">
                                <?php
                                if ($agreement_date_signed_pretty !== '') {
                                    echo esc_html($agreement_date_signed_pretty);
                                    if ($agreement_is_indefinite_p) {
                                        echo ' &rarr; <em>bezterminowa</em>';
                                    } elseif ($agreement_date_end_pretty !== '') {
                                        echo ' &rarr; ' . esc_html($agreement_date_end_pretty);
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                            <span class="eocrm-prop-quick-stat__sub">Okres umowy</span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--green">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128202;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Post&#281;p realizacji</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo esc_html((string) $agreement_stage_progress_pct); ?>%</span>
                            <span class="eocrm-prop-quick-stat__sub">
                                <?php echo esc_html((string) $agreement_stage_completed_render . ' / ' . (string) $agreement_stage_total_render); ?> etap&oacute;w uko&#324;czonych
                            </span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--purple">
                        <span class="eocrm-prop-quick-stat__icon eocrm-transaction-icon-shell" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Prowizja</span>
                            <span class="eocrm-prop-quick-stat__value <?php echo $agreement_commission_display === '' ? 'eocrm-prop-quick-stat__value--small' : ''; ?>">
                                <?php echo esc_html($agreement_commission_display !== '' ? $agreement_commission_display : '-'); ?>
                            </span>
                            <span class="eocrm-prop-quick-stat__sub">
                                <?php echo $agreement_is_exclusive_p ? 'Wy&#322;&#261;czno&#347;&#263;' : 'Bez wy&#322;&#261;czno&#347;ci'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--orange">
                        <span class="eocrm-prop-quick-stat__avatar" aria-hidden="true">
                            <?php if ($agreement_owner_avatar !== '') : ?>
                                <img src="<?php echo esc_url($agreement_owner_avatar); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="eocrm-prop-quick-stat__avatar-initial"><?php echo esc_html($agreement_owner_initial !== '' ? $agreement_owner_initial : '?'); ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Opiekun umowy</span>
                            <span class="eocrm-prop-quick-stat__value eocrm-prop-quick-stat__value--small"><?php echo esc_html($agreement_owner_name !== '' ? $agreement_owner_name : '-'); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">
                                <?php
                                $klienci_count_a = is_array($agreement_profile_clients) ? count($agreement_profile_clients) : 0;
                                $nieruch_count_a = is_array($agreement_profile_properties) ? count($agreement_profile_properties) : 0;
                                echo esc_html($klienci_count_a . ' kl. &middot; ' . $nieruch_count_a . ' nier.');
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <section class="eocrm-prop-detail" data-eocrm-agreement-detail>

                    <div class="eocrm-prop-detail-hero-row eocrm-agreement-main-row">
                        <div class="eocrm-agreement-main-col">

                            <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128101;</span>
                                    <h3>Klienci umowy</h3>
                                    <p class="eocrm-prop-detail-card__hint"><?php echo esc_html((string) (is_array($agreement_profile_clients) ? count($agreement_profile_clients) : 0)); ?> klient&oacute;w powi&#261;zanych z umow&#261;.</p>
                                </header>
                                <?php if (empty($agreement_profile_clients)) : ?>
                                    <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych klient&oacute;w.</p>
                                <?php else : ?>
                                    <div class="eocrm-agreement-client-grid">
                                        <?php foreach ($agreement_profile_clients as $client_row_a) :
                                            $client_id_a = (int) ($client_row_a['id'] ?? 0);
                                            $client_name_a = (string) ($client_row_a['display_name'] ?? 'Klient');
                                            $client_email_a = (string) ($client_row_a['email'] ?? '');
                                            $client_phone_a = (string) ($client_row_a['phone'] ?? '');
                                            $client_type_a = (string) ($client_row_a['client_type'] ?? '');
                                            $client_initial_a = $client_name_a !== '' ? mb_strtoupper(mb_substr($client_name_a, 0, 1)) : '?';
                                            $client_url_a = add_query_arg(['crm' => 'clients', 'client_id' => $client_id_a], $current_url);
                                        ?>
                                            <a class="eocrm-agreement-client-card" href="<?php echo esc_url($client_url_a); ?>">
                                                <span class="eocrm-agreement-client-card__avatar" aria-hidden="true"><?php echo esc_html($client_initial_a); ?></span>
                                                <div class="eocrm-agreement-client-card__body">
                                                    <span class="eocrm-agreement-client-card__name"><?php echo esc_html($client_name_a); ?></span>
                                                    <?php if ($client_type_a !== '') : ?>
                                                        <span class="eocrm-agreement-client-card__type"><?php echo esc_html(str_replace('_', ' ', $client_type_a)); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($client_phone_a !== '' || $client_email_a !== '') : ?>
                                                        <span class="eocrm-agreement-client-card__contact">
                                                            <?php if ($client_phone_a !== '') : ?>
                                                                <span>&#9742; <?php echo esc_html($client_phone_a); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($client_email_a !== '') : ?>
                                                                <span>&#9993; <?php echo esc_html($client_email_a); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>

                            <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#127968;</span>
                                    <h3>Powi&#261;zane nieruchomo&#347;ci</h3>
                                    <p class="eocrm-prop-detail-card__hint"><?php echo esc_html((string) (is_array($agreement_profile_properties) ? count($agreement_profile_properties) : 0)); ?> oferty/ofert powi&#261;zane z umow&#261;.</p>
                                </header>
                                <?php if (empty($agreement_profile_properties)) : ?>
                                    <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych nieruchomo&#347;ci.</p>
                                <?php else : ?>
                                    <div class="eocrm-agreement-property-grid">
                                        <?php foreach ($agreement_profile_properties as $property_row_a) :
                                            $prop_id_a = (int) ($property_row_a['id'] ?? 0);
                                            $prop_offer_a = (string) ($property_row_a['offer_number'] ?? '');
                                            $prop_address_a = $format_address([
                                                trim((string) ($property_row_a['street'] ?? '') . ' ' . (string) ($property_row_a['building_no'] ?? '')),
                                                (string) ($property_row_a['city'] ?? ''),
                                            ]);
                                            $prop_price_a = $format_property_price($property_row_a['price'] ?? null, $property_row_a['price_currency'] ?? 'PLN');
                                            $prop_thumb_a = (string) ($property_row_a['primary_photo_url'] ?? '');
                                            $prop_url_a = add_query_arg(['crm' => 'properties', 'property_id' => $prop_id_a], $current_url);
                                            $prop_type_a = strtoupper((string) ($property_row_a['property_type'] ?? ''));
                                            $prop_type_label_a = $agreement_property_type_label_map[$prop_type_a] ?? ($prop_type_a !== '' ? ucfirst(strtolower($prop_type_a)) : '');
                                        ?>
                                            <a class="eocrm-agreement-property-card" href="<?php echo esc_url($prop_url_a); ?>">
                                                <span class="eocrm-agreement-property-card__thumb" aria-hidden="true">
                                                    <?php if ($prop_thumb_a !== '') : ?>
                                                        <img src="<?php echo esc_url($prop_thumb_a); ?>" alt="" loading="lazy">
                                                    <?php else : ?>
                                                        <span class="eocrm-agreement-property-card__placeholder">&#127968;</span>
                                                    <?php endif; ?>
                                                </span>
                                                <div class="eocrm-agreement-property-card__body">
                                                    <span class="eocrm-agreement-property-card__title"><?php echo esc_html($prop_offer_a !== '' ? $prop_offer_a : ('Oferta #' . $prop_id_a)); ?></span>
                                                    <span class="eocrm-agreement-property-card__sub"><?php echo wp_kses_post($prop_type_label_a); ?></span>
                                                    <?php if ($prop_address_a !== '') : ?>
                                                        <span class="eocrm-agreement-property-card__address"><?php echo esc_html($prop_address_a); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($prop_price_a !== '' && $prop_price_a !== '-') : ?>
                                                        <span class="eocrm-agreement-property-card__price"><?php echo esc_html($prop_price_a); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>

                            <?php if (! empty($agreement_profile_searches)) : ?>
                                <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                                    <header class="eocrm-prop-detail-card__head">
                                        <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128269;</span>
                                        <h3>Powi&#261;zane poszukiwania</h3>
                                    </header>
                                    <ul class="eocrm-prop-detail-related-list eocrm-agreement-search-list">
                                        <?php foreach ($agreement_profile_searches as $search_row_a) :
                                            $search_id_a = (int) ($search_row_a['id'] ?? 0);
                                            $search_number_a = (string) ($search_row_a['search_number'] ?? '');
                                            $search_loc_a = (string) ($search_row_a['location_text'] ?? '');
                                        ?>
                                            <li>
                                                <a href="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'search_id' => $search_id_a], $current_url)); ?>">
                                                    <span class="eocrm-prop-detail-related-list__avatar">&#128269;</span>
                                                    <span class="eocrm-prop-detail-related-list__name">
                                                        <?php echo esc_html($search_number_a !== '' ? $search_number_a : ('Poszukiwanie #' . $search_id_a)); ?>
                                                        <?php if ($search_loc_a !== '') : ?>
                                                            <em>&middot; <?php echo esc_html($search_loc_a); ?></em>
                                                        <?php endif; ?>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </article>
                            <?php endif; ?>

                            <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon eocrm-transaction-icon-shell" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                                    <h3>Powi&#261;zane transakcje</h3>
                                    <p class="eocrm-prop-detail-card__hint"><?php echo esc_html((string) (is_array($agreement_profile_transactions) ? count($agreement_profile_transactions) : 0)); ?> transakcji powi&#261;zanych z umow&#261;.</p>
                                    <a
                                        class="eocrm-btn eocrm-btn-primary eocrm-agreement-related-action"
                                        href="<?php echo esc_url($agreement_new_transaction_url); ?>"
                                        <?php if ($agreement_existing_partial_url !== '') : ?>
                                            data-eocrm-transaction-choice-trigger
                                            data-existing-url="<?php echo esc_url($agreement_existing_partial_url); ?>"
                                            data-new-url="<?php echo esc_url($agreement_new_transaction_url); ?>"
                                        <?php endif; ?>
                                    >Dodaj transakcj&#281;</a>
                                </header>
                                <?php if (empty($agreement_profile_transactions)) : ?>
                                    <p class="eocrm-prop-detail-empty">Brak transakcji dla tej umowy.</p>
                                <?php else : ?>
                                    <ul class="eocrm-prop-detail-related-list eocrm-agreement-search-list">
                                        <?php foreach ($agreement_profile_transactions as $transaction_row_a) :
                                            $transaction_id_a = (int) ($transaction_row_a['id'] ?? 0);
                                            $transaction_price_a = $format_optional_transaction_price($transaction_row_a['transaction_price'] ?? null, $transaction_row_a['price_currency'] ?? 'PLN');
                                            $transaction_date_a = (string) ($transaction_row_a['transaction_date'] ?? '');
                                            $transaction_date_ts_a = $transaction_date_a !== '' ? strtotime($transaction_date_a) : false;
                                            $transaction_date_pretty_a = $transaction_date_ts_a !== false ? (string) date_i18n('d.m.Y', $transaction_date_ts_a) : '';
                                            $transaction_commission_stage_a = trim((string) ($transaction_row_a['commission_stage_name'] ?? ''));
                                            $transaction_is_partial_a = $transaction_commission_stage_a !== '' && ! $is_settlement_commission_stage($transaction_commission_stage_a);
                                        ?>
                                            <li>
                                                <a href="<?php echo esc_url(add_query_arg(['crm' => 'transactions', 'transaction_id' => $transaction_id_a], $current_url)); ?>">
                                                    <span class="eocrm-prop-detail-related-list__avatar eocrm-transaction-related-avatar"><?php $render_transaction_icon(); ?></span>
                                                    <span class="eocrm-prop-detail-related-list__name">
                                                        <?php echo esc_html($transaction_price_a !== '-' ? $transaction_price_a : ('Transakcja #' . $transaction_id_a)); ?>
                                                        <?php if ($transaction_is_partial_a) : ?>
                                                            <em>&middot; Transakcja cz&#281;&#347;ciowa</em>
                                                        <?php endif; ?>
                                                        <?php if ($transaction_date_pretty_a !== '') : ?>
                                                            <em>&middot; <?php echo esc_html($transaction_date_pretty_a); ?></em>
                                                        <?php endif; ?>
                                                        <?php if ($transaction_commission_stage_a !== '') : ?>
                                                            <em>&middot; <?php echo esc_html($transaction_commission_stage_a); ?></em>
                                                        <?php endif; ?>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </article>

                        </div>

                        <aside class="eocrm-agreement-side-col">

                            <article class="eocrm-prop-detail-card eocrm-prop-detail-card--side eocrm-agreement-stage-update">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128203;</span>
                                    <h3>Aktualizacja etapu</h3>
                                    <p class="eocrm-prop-detail-card__hint">Zmiana etapu zostanie zapisana w historii.</p>
                                </header>
                                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_profile_id], $current_url)); ?>" class="eocrm-agreement-stage-form" data-eocrm-agreement-stage-form data-eocrm-commission-transaction-stages="<?php echo esc_attr($agreement_commission_stage_names_json); ?>" data-eocrm-commission-has-plan="<?php echo $agreement_commission_has_plan ? '1' : '0'; ?>" data-eocrm-commission-all-paid="<?php echo $agreement_commission_all_paid ? '1' : '0'; ?>">
                                    <?php wp_nonce_field('eocrm_update_agreement_stage', 'eocrm_stage_nonce'); ?>
                                    <input type="hidden" name="eocrm_action" value="update_agreement_stage">
                                    <input type="hidden" name="agreement_id" value="<?php echo (int) $agreement_profile_id; ?>">
                                    <p class="eocrm-form-field">
                                        <label>Nowy etap</label>
                                        <select name="stage_name" required>
                                            <option value="">Wybierz etap</option>
                                            <?php foreach ($agreement_stage_options as $stage_option) : ?>
                                                <option value="<?php echo esc_attr($stage_option); ?>" <?php selected((string) $agreement_profile_current_stage, $stage_option); ?>>
                                                    <?php echo esc_html($stage_option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p class="eocrm-form-field">
                                        <label>Data etapu</label>
                                        <input type="date" name="stage_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                                    </p>
                                    <p class="eocrm-actions-row">
                                        <button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz etap</button>
                                    </p>
                                </form>
                            </article>

                            <article class="eocrm-prop-detail-card eocrm-prop-detail-card--side">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128100;</span>
                                    <h3>Opiekun umowy</h3>
                                </header>
                                <div class="eocrm-agreement-side-owner">
                                    <span class="eocrm-agreement-side-owner__avatar" aria-hidden="true">
                                        <?php if ($agreement_owner_avatar !== '') : ?>
                                            <img src="<?php echo esc_url($agreement_owner_avatar); ?>" alt="" loading="lazy">
                                        <?php else : ?>
                                            <span class="eocrm-agreement-side-owner__initial"><?php echo esc_html($agreement_owner_initial !== '' ? $agreement_owner_initial : '?'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <div class="eocrm-agreement-side-owner__body">
                                        <span class="eocrm-agreement-side-owner__name"><?php echo esc_html($agreement_owner_name !== '' ? $agreement_owner_name : 'Brak opiekuna'); ?></span>
                                        <?php if ($agreement_owner instanceof WP_User && $agreement_owner->user_email !== '') : ?>
                                            <a class="eocrm-agreement-side-owner__email" href="mailto:<?php echo esc_attr($agreement_owner->user_email); ?>">&#9993; <?php echo esc_html($agreement_owner->user_email); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>

                            <article class="eocrm-prop-detail-card eocrm-prop-detail-card--side eocrm-agreement-side-summary">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128202;</span>
                                    <h3>Podsumowanie</h3>
                                </header>
                                <div class="eocrm-agreement-side-summary__progress" aria-label="Post&#281;p realizacji">
                                    <span class="eocrm-agreement-side-summary__pct"><?php echo esc_html((string) $agreement_stage_progress_pct); ?>%</span>
                                    <span class="eocrm-agreement-side-summary__label">Post&#281;p realizacji</span>
                                    <span class="eocrm-agreement-side-summary__track">
                                        <span class="eocrm-agreement-side-summary__fill" style="width: <?php echo esc_attr((string) $agreement_stage_progress_pct); ?>%;"></span>
                                    </span>
                                </div>
                                <ul class="eocrm-agreement-side-summary__list">
                                    <li>
                                        <span>Etapy</span>
                                        <strong><?php echo esc_html((string) $agreement_stage_completed_render . ' / ' . (string) $agreement_stage_total_render); ?></strong>
                                    </li>
                                    <li>
                                        <span>Klienci</span>
                                        <strong><?php echo esc_html((string) (is_array($agreement_profile_clients) ? count($agreement_profile_clients) : 0)); ?></strong>
                                    </li>
                                    <li>
                                        <span>Nieruchomo&#347;ci</span>
                                        <strong><?php echo esc_html((string) (is_array($agreement_profile_properties) ? count($agreement_profile_properties) : 0)); ?></strong>
                                    </li>
                                    <li>
                                        <span>Poszukiwania</span>
                                        <strong><?php echo esc_html((string) (is_array($agreement_profile_searches) ? count($agreement_profile_searches) : 0)); ?></strong>
                                    </li>
                                    <li>
                                        <span>Transakcje</span>
                                        <strong><?php echo esc_html((string) (is_array($agreement_profile_transactions) ? count($agreement_profile_transactions) : 0)); ?></strong>
                                    </li>
                                </ul>
                            </article>

                        </aside>
                    </div>

                </section>

                <div class="eocrm-modal eocrm-stage-transaction-modal" data-eocrm-stage-transaction-modal hidden>
                    <div class="eocrm-modal__dialog eocrm-stage-transaction-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="eocrm-stage-transaction-title">
                        <button type="button" class="eocrm-modal__close" data-eocrm-stage-transaction-close aria-label="Zamknij">&times;</button>
                        <span class="eocrm-stage-transaction-modal__icon" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                        <h3 id="eocrm-stage-transaction-title">Utworzy&#263; transakcj&#281;?</h3>
                        <p data-eocrm-stage-transaction-message>Etap umowy zostanie zmieniony na <strong>Umowa zako&#324;czona</strong>. Mo&#380;esz od razu przej&#347;&#263; do formularza transakcji powi&#261;zanej z t&#261; umow&#261;.</p>
                        <div class="eocrm-modal__actions">
                            <button class="eocrm-btn eocrm-btn-primary" type="button" data-eocrm-stage-transaction-yes>Tak, utw&#243;rz transakcj&#281;</button>
                            <button class="eocrm-btn" type="button" data-eocrm-stage-transaction-no>Nie, zapisz tylko etap</button>
                        </div>
                    </div>
                </div>
                <?php if ($agreement_existing_partial_url !== '') : ?>
                    <div class="eocrm-modal eocrm-stage-transaction-modal eocrm-transaction-choice-modal" data-eocrm-transaction-choice-modal hidden>
                        <div class="eocrm-modal__dialog eocrm-stage-transaction-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="eocrm-transaction-choice-title">
                            <button type="button" class="eocrm-modal__close" data-eocrm-transaction-choice-close aria-label="Zamknij">&times;</button>
                            <span class="eocrm-stage-transaction-modal__icon" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                            <h3 id="eocrm-transaction-choice-title">Jak doda&#263; transakcj&#281;?</h3>
                            <p>Ta umowa ma ju&#380; transakcj&#281; cz&#281;&#347;ciow&#261;. Mo&#380;esz dopisa&#263; kolejny etap prowizji do istniej&#261;cej transakcji albo utworzy&#263; now&#261; transakcj&#281;.</p>
                            <div class="eocrm-modal__actions">
                                <button class="eocrm-btn eocrm-btn-primary" type="button" data-eocrm-transaction-choice-existing>Dopisz do istniej&#261;cej</button>
                                <button class="eocrm-btn" type="button" data-eocrm-transaction-choice-new>Utw&#243;rz now&#261;</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php
        $agreement_property_type_label_map = [
            'MIESZKANIE' => 'Mieszkanie',
            'DOM' => 'Dom',
            'DZIALKA' => 'Dzia&#322;ka',
            'LOKAL_HU' => 'Lokal H/U',
        ];
        $agreement_transaction_label_map = [
            'SPRZEDAZ' => 'Sprzeda&#380;',
            'KUPNO' => 'Kupno',
            'WYNAJEM' => 'Wynajem',
            'NAJEM' => 'Najem',
        ];
        $agreements_total_count = is_array($agreements_rows) ? count($agreements_rows) : 0;
        ?>

        <section class="eocrm-prop-toolbar" aria-label="Filtry i wyszukiwanie um&oacute;w">
            <form method="get" class="eocrm-prop-toolbar__search" role="search">
                <input type="hidden" name="crm" value="agreements">
                <span class="eocrm-prop-toolbar__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                </span>
                <input
                    id="eocrm-agreements-search"
                    data-live-filter
                    data-target-table="eocrm-agreements-table"
                    name="q"
                    type="search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Szukaj po numerze umowy, kliencie, adresie..."
                    aria-label="Szukaj um&oacute;w"
                >
                <button class="eocrm-prop-toolbar__search-btn" type="submit">Szukaj</button>
            </form>

            <div class="eocrm-prop-filters" data-eocrm-transaction-filter-group data-target-table="eocrm-agreements-table" aria-label="Filtry">
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-transaction-filter value="SPRZEDAZ">
                    <span>Sprzeda&#380;</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-transaction-filter value="KUPNO">
                    <span>Kupno</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-transaction-filter value="WYNAJEM">
                    <span>Wynajem</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-transaction-filter value="NAJEM">
                    <span>Najem</span>
                </label>
                <label class="eocrm-table-filter-toggle" title="Pokaz tylko aktywne umowy">
                    <input type="checkbox" data-eocrm-status-filter="active_agreement" value="1">
                    <span>Aktywne</span>
                </label>
            </div>
        </section>

        <div class="eocrm-prop-meta">
            <p class="eocrm-prop-meta__count">
                <strong><?php echo esc_html((string) $agreements_total_count); ?></strong>
                <span><?php
                    if ($agreements_total_count === 1) {
                        echo 'umowa';
                    } elseif ($agreements_total_count >= 2 && $agreements_total_count <= 4) {
                        echo 'umowy';
                    } else {
                        echo 'um&oacute;w';
                    }
                ?></span>
                <?php if ($search !== '') : ?>
                    <em class="eocrm-prop-meta__hint">dla zapytania &laquo;<?php echo esc_html($search); ?>&raquo;</em>
                <?php endif; ?>
            </p>
        </div>

        <div class="eocrm-prop-list-wrap">
            <table class="eocrm-table eocrm-prop-list eocrm-agreement-list" id="eocrm-agreements-table">
                <thead>
                    <tr>
                        <th class="eocrm-prop-col-main">Umowa</th>
                        <th class="eocrm-prop-col-loc">Adres / Rodzaj</th>
                        <th class="eocrm-prop-col-date">Zawarta</th>
                        <th class="eocrm-prop-col-date">Wa&#380;na do</th>
                        <th class="eocrm-prop-col-loc">Etap</th>
                        <?php if ($show_owner_columns) : ?>
                            <th class="eocrm-prop-col-owner">Opiekun</th>
                        <?php endif; ?>
                        <th class="eocrm-prop-col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agreements_rows)) : ?>
                        <tr class="eocrm-prop-empty-row"><td colspan="<?php echo esc_attr((string) ($show_owner_columns ? 7 : 6)); ?>">
                            <div class="eocrm-prop-empty">
                                <span class="eocrm-prop-empty__icon" aria-hidden="true">&#128196;</span>
                                <strong>Brak um&oacute;w do wy&#347;wietlenia</strong>
                                <span>Spr&oacute;buj zmieni&#263; filtry lub doda&#263; now&#261; umow&#281;.</span>
                            </div>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ($agreements_rows as $row) :
                            $agreement_row_id = (int) ($row['id'] ?? 0);
                            $agreement_row_number = (string) ($row['agreement_number'] ?? '');
                            $agreement_transaction_type = strtoupper(trim((string) ($row['transaction_type'] ?? '')));
                            $agreement_transaction_label = $agreement_transaction_label_map[$agreement_transaction_type] ?? ($agreement_transaction_type !== '' ? ucfirst(strtolower($agreement_transaction_type)) : '');
                            $agreement_property_type_label = $render_search_property_types((string) ($row['property_type'] ?? ''));
                            if ($agreement_property_type_label === '-') {
                                $agreement_property_type_label = '';
                            }

                            $agreement_property_address = $format_address([(string) ($row['property_address_line'] ?? ''), (string) ($row['property_city'] ?? '')]);
                            $agreement_property_id_row = (int) ($row['property_id'] ?? 0);

                            $agreement_row_url = add_query_arg(['crm' => 'agreements', 'agreement_id' => $agreement_row_id], $current_url);

                            $agreement_date_signed = (string) ($row['date_signed'] ?? '');
                            $agreement_date_end = (string) ($row['date_end'] ?? '');
                            $agreement_is_indefinite = ! empty($row['is_indefinite']);

                            $agreement_current_stage = (string) ($row['current_stage'] ?? '');
                            $agreement_finished = $is_agreement_stage_finished($agreement_current_stage);

                            $agreement_owner_data = get_userdata((int) ($row['owner_user_id'] ?? 0));
                            $agreement_owner_name = $agreement_owner_data instanceof WP_User ? (string) $agreement_owner_data->display_name : '-';
                            $agreement_owner_avatar = $agreement_owner_data instanceof WP_User ? $resolve_owner_photo_url((int) $agreement_owner_data->ID, 'thumbnail') : '';
                            $agreement_owner_initial = $agreement_owner_name !== '' && $agreement_owner_name !== '-' ? mb_strtoupper(mb_substr($agreement_owner_name, 0, 1)) : '?';
                        ?>
                            <tr
                                class="eocrm-prop-row"
                                data-eocrm-transaction-row
                                data-transaction="<?php echo esc_attr($agreement_transaction_type); ?>"
                                data-active-agreement="<?php echo esc_attr(! $agreement_finished ? '1' : '0'); ?>"
                            >
                                <td class="eocrm-prop-cell-main">
                                    <a class="eocrm-prop-link" href="<?php echo esc_url($agreement_row_url); ?>">
                                        <span class="eocrm-prop-thumb eocrm-prop-thumb--icon eocrm-prop-thumb--agreement" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg>
                                        </span>
                                        <span class="eocrm-prop-titlewrap">
                                            <span class="eocrm-prop-title"><?php echo esc_html($agreement_row_number !== '' ? $agreement_row_number : ('Umowa #' . $agreement_row_id)); ?></span>
                                            <span class="eocrm-prop-subtitle">
                                                <?php if ($agreement_transaction_label !== '') : ?>
                                                    <?php echo wp_kses_post($agreement_transaction_label); ?>
                                                <?php endif; ?>
                                                <?php if ($agreement_property_type_label !== '') : ?>
                                                    &middot; <?php echo wp_kses_post($agreement_property_type_label); ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </a>
                                </td>
                                <td class="eocrm-prop-cell-loc">
                                    <?php if ($agreement_property_address !== '') : ?>
                                        <?php if ($agreement_property_id_row > 0) : ?>
                                            <a class="eocrm-prop-loc-primary eocrm-prop-loc-primary--link" href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => $agreement_property_id_row], $current_url)); ?>"><?php echo esc_html($agreement_property_address); ?></a>
                                        <?php else : ?>
                                            <span class="eocrm-prop-loc-primary"><?php echo esc_html($agreement_property_address); ?></span>
                                        <?php endif; ?>
                                        <?php if ($agreement_property_type_label !== '') : ?>
                                            <span class="eocrm-prop-loc-secondary"><?php echo wp_kses_post($agreement_property_type_label); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="eocrm-prop-loc-primary eocrm-prop-loc-primary--muted">Bez nieruchomo&#347;ci</span>
                                        <?php if ($agreement_property_type_label !== '') : ?>
                                            <span class="eocrm-prop-loc-secondary"><?php echo wp_kses_post($agreement_property_type_label); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-date">
                                    <?php if ($agreement_date_signed !== '' && $agreement_date_signed !== '0000-00-00') :
                                        $agreement_date_signed_ts = strtotime($agreement_date_signed);
                                    ?>
                                        <span class="eocrm-prop-date-main"><?php echo esc_html(date_i18n('d.m.Y', $agreement_date_signed_ts)); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-date-main">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-date">
                                    <?php if ($agreement_is_indefinite) : ?>
                                        <span class="eocrm-prop-date-main eocrm-prop-date-main--badge">Bezterminowa</span>
                                    <?php elseif ($agreement_date_end !== '' && $agreement_date_end !== '0000-00-00') :
                                        $agreement_date_end_ts = strtotime($agreement_date_end);
                                    ?>
                                        <span class="eocrm-prop-date-main"><?php echo esc_html(date_i18n('d.m.Y', $agreement_date_end_ts)); ?></span>
                                    <?php else : ?>
                                        <span class="eocrm-prop-date-main">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-loc">
                                    <span class="eocrm-prop-stage-pill <?php echo $agreement_finished ? 'is-done' : 'is-progress'; ?>">
                                        <?php echo esc_html($agreement_current_stage !== '' ? $agreement_current_stage : ($agreement_finished ? 'Zako&#324;czona' : 'W trakcie')); ?>
                                    </span>
                                </td>
                                <?php if ($show_owner_columns) : ?>
                                    <td class="eocrm-prop-cell-owner">
                                        <span class="eocrm-prop-owner">
                                            <span class="eocrm-prop-owner__avatar" aria-hidden="true">
                                                <?php if (! empty($agreement_owner_avatar)) : ?>
                                                    <img src="<?php echo esc_url((string) $agreement_owner_avatar); ?>" alt="" loading="lazy">
                                                <?php else : ?>
                                                    <span class="eocrm-prop-owner__initial"><?php echo esc_html($agreement_owner_initial); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="eocrm-prop-owner__name"><?php echo esc_html($agreement_owner_name); ?></span>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="eocrm-prop-cell-status">
                                    <span class="eocrm-prop-status-icons">
                                        <?php if (! $agreement_finished) : ?>
                                            <span class="eocrm-prop-status-icon is-on" title="Umowa: aktywna" aria-label="Umowa: aktywna">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path></svg>
                                            </span>
                                        <?php else : ?>
                                            <span class="eocrm-prop-status-icon is-off" title="Umowa: zakonczona" aria-label="Umowa: zakonczona">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path><path d="m4.93 4.93 14.14 14.14"></path></svg>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($section === 'transactions') : ?>
        <?php
        $transaction_label_map = [
            'SPRZEDAZ' => 'Sprzeda&#380;',
            'KUPNO' => 'Kupno',
            'WYNAJEM' => 'Wynajem',
            'NAJEM' => 'Najem',
        ];
        $format_transaction_date = static function (string $date_raw): string {
            if ($date_raw === '' || $date_raw === '0000-00-00') {
                return '';
            }
            $ts = strtotime($date_raw);
            return $ts !== false ? (string) date_i18n('d.m.Y', $ts) : '';
        };
        $build_transaction_property_label = static function (array $row) use ($format_address): string {
            $stored_label = trim((string) ($row['property_label'] ?? ''));
            if ($stored_label !== '') {
                return $stored_label;
            }

            $offer = trim((string) ($row['offer_number'] ?? ''));
            $address = $format_address([
                trim((string) ($row['street'] ?? '') . ' ' . (string) ($row['building_no'] ?? '') . ((string) ($row['apartment_no'] ?? '') !== '' ? '/' . (string) ($row['apartment_no'] ?? '') : '')),
                (string) ($row['city'] ?? ''),
            ]);

            if ($offer !== '' && $address !== '-' && $address !== '') {
                return $offer . ' - ' . $address;
            }
            if ($offer !== '') {
                return $offer;
            }

            return $address !== '' ? $address : '-';
        };
        ?>

        <?php if ($crm_notice === 'transaction_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Transakcja zostala dodana.'); ?></div>
        <?php elseif ($crm_notice === 'transaction_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Transakcja zostala zaktualizowana.'); ?></div>
        <?php elseif ($crm_notice === 'transaction_deleted') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Transakcja zostala usunieta.'); ?></div>
        <?php elseif ($crm_notice === 'agreement_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Umowa zostala dodana.'); ?></div>
        <?php elseif ($crm_notice === 'property_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nieruchomosc zostala dodana.'); ?></div>
        <?php elseif ($crm_notice === 'search_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Poszukiwanie zostalo dodane.'); ?></div>
        <?php elseif ($crm_notice === 'agreement_stage_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Etap umowy zostal zaktualizowany.'); ?></div>
        <?php elseif ($crm_notice === 'transaction_error') : ?>
            <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac transakcji.'); ?></div>
        <?php endif; ?>

        <?php if (in_array($transactions_mode, ['new-transaction', 'edit-transaction'], true)) : ?>
            <?php if (! is_array($transaction_form_agreement) || ($transactions_mode === 'edit-transaction' && ! is_array($transaction_profile))) : ?>
                <section class="eocrm-card">
                    <h3><?php echo esc_html($transactions_mode === 'edit-transaction' ? 'Edycja transakcji' : 'Dodawanie transakcji'); ?></h3>
                    <div class="eocrm-alert eocrm-alert-error">Nie znaleziono transakcji albo nie masz do niej dost&#281;pu.</div>
                    <p><a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'transactions'], $current_url)); ?>">&larr; Powr&#243;t do transakcji</a></p>
                </section>
            <?php else : ?>
                <?php
                $is_transaction_edit = $transactions_mode === 'edit-transaction';
                $tx_edit_profile = $is_transaction_edit && is_array($transaction_profile) ? $transaction_profile : [];
                $tx_form_agreement_id = (int) ($transaction_form_agreement['id'] ?? 0);
                $tx_form_number = (string) ($transaction_form_agreement['agreement_number'] ?? '');
                $tx_form_type = strtoupper(trim((string) ($transaction_form_agreement['transaction_type'] ?? '')));
                $tx_form_type_label = $transaction_label_map[$tx_form_type] ?? ($tx_form_type !== '' ? ucfirst(strtolower($tx_form_type)) : '-');
                $tx_agreement_commission_stage_rows = ! empty($transaction_form_agreement['commission_split_enabled'])
                    ? $parse_commission_stage_rows((string) ($transaction_form_agreement['commission_stages_json'] ?? ''))
                    : [];
                $tx_payment_stage_options = $build_commission_payment_options($transaction_form_agreement, $tx_agreement_commission_stage_rows, $transaction_form_paid_commission_stage_names);
                $tx_requested_commission_stage = isset($_GET['commission_stage']) ? sanitize_text_field((string) wp_unslash($_GET['commission_stage'])) : '';
                $tx_form_commission_stage_name = $is_transaction_edit
                    ? ($tx_requested_commission_stage !== '' ? $tx_requested_commission_stage : (string) ($tx_edit_profile['commission_stage_name'] ?? ''))
                    : $tx_requested_commission_stage;
                $tx_form_commission_stage_option = null;
                if ($tx_form_commission_stage_name !== '') {
                    foreach ($tx_payment_stage_options as $tx_stage_candidate) {
                        if (trim((string) ($tx_stage_candidate['stage_name'] ?? '')) === $tx_form_commission_stage_name) {
                            $tx_form_commission_stage_option = $tx_stage_candidate;
                            break;
                        }
                    }
                }
                $tx_form_uses_requested_stage_override = $is_transaction_edit && $tx_requested_commission_stage !== '' && is_array($tx_form_commission_stage_option);
                $tx_form_commission_amount = $is_transaction_edit
                    ? ($tx_form_uses_requested_stage_override ? (float) ($tx_form_commission_stage_option['amount'] ?? 0) : (float) ($tx_edit_profile['commission_amount'] ?? 0))
                    : (is_array($tx_form_commission_stage_option) ? (float) ($tx_form_commission_stage_option['amount'] ?? 0) : (float) ($transaction_form_agreement['commission_amount'] ?? 0));
                $tx_form_commission_unit = $is_transaction_edit
                    ? ($tx_form_uses_requested_stage_override ? (string) ($tx_form_commission_stage_option['unit'] ?? '') : (string) ($tx_edit_profile['commission_unit'] ?? ''))
                    : (is_array($tx_form_commission_stage_option) ? (string) ($tx_form_commission_stage_option['unit'] ?? '') : (string) ($transaction_form_agreement['commission_unit'] ?? ''));
                $tx_form_commission_display = $tx_form_commission_amount > 0
                    ? rtrim(rtrim(number_format($tx_form_commission_amount, 2, '.', ''), '0'), '.') . ' ' . $tx_form_commission_unit
                    : '-';
                $tx_form_transaction_id = $is_transaction_edit ? (int) ($tx_edit_profile['id'] ?? 0) : 0;
                $tx_form_selected_property_id = $is_transaction_edit ? (int) ($tx_edit_profile['property_id'] ?? 0) : 0;
                $tx_form_transaction_price = $is_transaction_edit ? (string) ($tx_edit_profile['transaction_price'] ?? '') : '';
                $tx_form_requires_transaction_date = $tx_form_commission_stage_name === '' || ! is_array($tx_form_commission_stage_option) || ! empty($tx_form_commission_stage_option['requires_date']);
                $tx_form_is_partial_commission_stage = $tx_form_commission_stage_name !== '' && ! $tx_form_requires_transaction_date;
                $tx_form_requires_transaction_price = ! $tx_form_is_partial_commission_stage || strtoupper(trim($tx_form_commission_unit)) === '%';
                $tx_form_transaction_price_value = '';
                if ($tx_form_transaction_price !== '' && is_numeric($tx_form_transaction_price)) {
                    $tx_form_transaction_price_float = (float) $tx_form_transaction_price;
                    if ($tx_form_transaction_price_float > 0 || $tx_form_requires_transaction_price) {
                        $tx_form_transaction_price_value = rtrim(rtrim(number_format($tx_form_transaction_price_float, 2, '.', ''), '0'), '.');
                    }
                }
                $tx_form_transaction_date = $is_transaction_edit
                    ? (string) ($tx_edit_profile['transaction_date'] ?? '')
                    : ($tx_form_requires_transaction_date ? current_time('Y-m-d') : '');
                $tx_form_cooperation_agent = ! empty($tx_edit_profile['cooperation_agent']);
                $tx_form_cooperation_type = (string) ($tx_edit_profile['cooperation_type'] ?? 'own_office');
                if (! in_array($tx_form_cooperation_type, ['own_office', 'other_office'], true)) {
                    $tx_form_cooperation_type = 'own_office';
                }
                $tx_form_cooperation_agent_user_id = (int) ($tx_edit_profile['cooperation_agent_user_id'] ?? 0);
                $tx_form_cooperation_office_name = (string) ($tx_edit_profile['cooperation_office_name'] ?? '');
                $tx_form_cooperation_agent_name = (string) ($tx_edit_profile['cooperation_agent_name'] ?? '');
                $tx_form_owner_user_id = $is_transaction_edit
                    ? (int) ($tx_edit_profile['owner_user_id'] ?? 0)
                    : (int) ($transaction_form_agreement['owner_user_id'] ?? 0);
                $tx_form_first_property = ! empty($transaction_form_properties) ? $transaction_form_properties[0] : null;
                if ($tx_form_selected_property_id > 0) {
                    foreach ($transaction_form_properties as $tx_candidate_property) {
                        if ((int) ($tx_candidate_property['id'] ?? 0) === $tx_form_selected_property_id) {
                            $tx_form_first_property = $tx_candidate_property;
                            break;
                        }
                    }
                }
                $tx_form_first_price = is_array($tx_form_first_property) ? ($tx_form_first_property['price'] ?? '') : '';
                $tx_form_first_currency = is_array($tx_form_first_property) ? (string) ($tx_form_first_property['price_currency'] ?? 'PLN') : 'PLN';
                $tx_form_date_label = in_array($tx_form_type, ['WYNAJEM', 'NAJEM'], true)
                    ? 'Data podpisania umowy najmu'
                    : 'Data przeniesienia w&#322;asno&#347;ci';
                ?>
                <section class="eocrm-card">
                    <h3><?php echo esc_html($is_transaction_edit ? 'Edycja transakcji' : 'Dodawanie transakcji'); ?></h3>
                    <form method="post" action="<?php echo esc_url(add_query_arg($is_transaction_edit ? ['crm' => 'transactions', 'mode' => 'edit-transaction', 'transaction_id' => $tx_form_transaction_id] : ['crm' => 'transactions', 'mode' => 'new-transaction', 'agreement_id' => $tx_form_agreement_id], $current_url)); ?>" data-eocrm-transaction-form data-eocrm-transaction-cooperation-form>
                        <?php wp_nonce_field($is_transaction_edit ? 'eocrm_update_transaction' : 'eocrm_create_transaction', 'eocrm_transaction_nonce'); ?>
                        <input type="hidden" name="eocrm_action" value="<?php echo esc_attr($is_transaction_edit ? 'update_transaction' : 'create_transaction'); ?>">
                        <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $tx_form_agreement_id); ?>">
                        <?php if ($is_transaction_edit) : ?>
                            <input type="hidden" name="transaction_id" value="<?php echo esc_attr((string) $tx_form_transaction_id); ?>">
                        <?php endif; ?>

                        <?php if (! empty($tx_agreement_commission_stage_rows)) : ?>
                            <div class="eocrm-alert eocrm-alert-info eocrm-commission-transaction-hint">
                                <strong>Transakcja prowizyjna</strong>
                                <span>Mo&#380;esz powi&#261;za&#263; transakcj&#281; z jednym z etap&oacute;w prowizji zdefiniowanych w umowie.</span>
                                <span>Prowizja jest sum&#261; dotychczasowych kwot ustalonych etap&oacute;w.</span>
                            </div>
                        <?php endif; ?>

                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field">
                                <label>Numer umowy</label>
                                <input type="text" value="<?php echo esc_attr($tx_form_number); ?>" readonly>
                            </p>
                            <p class="eocrm-form-field">
                                <label>Typ transakcji</label>
                                <input type="text" value="<?php echo esc_attr(html_entity_decode(wp_strip_all_tags($tx_form_type_label), ENT_QUOTES, 'UTF-8')); ?>" readonly>
                            </p>
                            <?php if (! empty($tx_payment_stage_options)) : ?>
                                <p class="eocrm-form-field">
                                    <label>Etap prowizji</label>
                                    <select name="commission_stage_name" data-eocrm-transaction-commission-stage>
                                        <option value="">Bez etapu prowizji</option>
                                        <?php foreach ($tx_payment_stage_options as $tx_stage_option) : ?>
                                            <?php
                                            $tx_stage_option_name = (string) ($tx_stage_option['stage_name'] ?? '');
                                            $tx_stage_option_amount = isset($tx_stage_option['amount']) && is_numeric((string) $tx_stage_option['amount']) ? rtrim(rtrim(number_format((float) $tx_stage_option['amount'], 2, '.', ''), '0'), '.') : '';
                                            $tx_stage_option_unit = (string) ($tx_stage_option['unit'] ?? '');
                                            $tx_stage_option_label = (string) ($tx_stage_option['covered_label'] ?? $tx_stage_option_name);
                                            $tx_stage_share_label = (string) ($tx_stage_option['share_label'] ?? '');
                                            ?>
                                            <option
                                                value="<?php echo esc_attr($tx_stage_option_name); ?>"
                                                data-commission-amount="<?php echo esc_attr($tx_stage_option_amount); ?>"
                                                data-commission-unit="<?php echo esc_attr($tx_stage_option_unit); ?>"
                                                data-requires-date="<?php echo ! empty($tx_stage_option['requires_date']) ? '1' : '0'; ?>"
                                                data-requires-price="<?php echo (! empty($tx_stage_option['requires_date']) || strtoupper(trim($tx_stage_option_unit)) === '%') ? '1' : '0'; ?>"
                                                <?php selected($tx_form_commission_stage_name, $tx_stage_option_name); ?>
                                            >
                                                <?php echo esc_html($tx_stage_option_label . ' - ' . $tx_stage_option_amount . ' ' . $tx_stage_option_unit . ($tx_stage_share_label !== '' ? ' (' . $tx_stage_share_label . ')' : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                            <?php else : ?>
                                <input type="hidden" name="commission_stage_name" value="">
                            <?php endif; ?>
                            <p class="eocrm-form-field">
                                <label>Prowizja</label>
                                <input type="number" name="commission_amount" min="0" step="0.01" value="<?php echo esc_attr($tx_form_commission_amount > 0 ? rtrim(rtrim(number_format($tx_form_commission_amount, 2, '.', ''), '0'), '.') : ''); ?>" placeholder="np. 2.5 lub 12000" data-eocrm-transaction-commission-amount>
                            </p>
                            <p class="eocrm-form-field">
                                <label>Jednostka prowizji</label>
                                <select name="commission_unit" data-eocrm-transaction-commission-unit>
                                    <option value="">Wybierz</option>
                                    <option value="%" <?php selected($tx_form_commission_unit, '%'); ?>>%</option>
                                    <option value="PLN" <?php selected($tx_form_commission_unit, 'PLN'); ?>>PLN</option>
                                    <option value="EUR" <?php selected($tx_form_commission_unit, 'EUR'); ?>>EUR</option>
                                    <option value="USD" <?php selected($tx_form_commission_unit, 'USD'); ?>>USD</option>
                                </select>
                            </p>
                            <p class="eocrm-form-field">
                                <label>Cena ofertowa</label>
                                <input type="text" value="<?php echo esc_attr($format_property_price($tx_form_first_price, $tx_form_first_currency)); ?>" readonly data-eocrm-transaction-offer-price>
                            </p>
                        </div>

                        <div class="eocrm-form-grid">
                            <p class="eocrm-form-field">
                                <label>Nieruchomo&#347;&#263;</label>
                                <?php if (count($transaction_form_properties) > 1) : ?>
                                    <select name="property_id" data-eocrm-transaction-property-select>
                                        <?php if ($is_transaction_edit && $tx_form_selected_property_id <= 0) : ?>
                                            <option value="0" data-offer-price="" data-offer-currency="PLN" selected>Bez przypisanej nieruchomo&#347;ci</option>
                                        <?php endif; ?>
                                        <?php foreach ($transaction_form_properties as $tx_property_option) :
                                            $tx_prop_id = (int) ($tx_property_option['id'] ?? 0);
                                            $tx_prop_label = $build_transaction_property_label($tx_property_option);
                                            $tx_prop_price = (string) ($tx_property_option['price'] ?? '');
                                            $tx_prop_currency = (string) ($tx_property_option['price_currency'] ?? 'PLN');
                                        ?>
                                            <option
                                                value="<?php echo esc_attr((string) $tx_prop_id); ?>"
                                                data-offer-price="<?php echo esc_attr($tx_prop_price); ?>"
                                                data-offer-currency="<?php echo esc_attr($tx_prop_currency); ?>"
                                                <?php selected($tx_form_selected_property_id, $tx_prop_id); ?>
                                            >
                                                <?php echo esc_html($tx_prop_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif (count($transaction_form_properties) === 1 && is_array($tx_form_first_property)) : ?>
                                    <input type="hidden" name="property_id" value="<?php echo esc_attr((string) ((int) ($tx_form_first_property['id'] ?? 0))); ?>">
                                    <input type="text" value="<?php echo esc_attr($build_transaction_property_label($tx_form_first_property)); ?>" readonly>
                                <?php else : ?>
                                    <input type="hidden" name="property_id" value="0">
                                    <input type="text" value="Brak nieruchomo&#347;ci powi&#261;zanej z umow&#261;" readonly>
                                <?php endif; ?>
                            </p>
                            <p class="eocrm-form-field">
                                <label>Cena transakcyjna</label>
                                <input type="number" name="transaction_price" min="0" step="0.01" value="<?php echo esc_attr($tx_form_transaction_price_value); ?>" data-eocrm-transaction-price <?php echo $tx_form_requires_transaction_price ? 'required' : ''; ?>>
                                <small class="eocrm-form-hint" data-eocrm-transaction-price-hint <?php echo $tx_form_requires_transaction_price ? 'hidden' : ''; ?>>Cena nie jest wymagana dla cz&#281;&#347;ciowej prowizji kwotowej.</small>
                            </p>
                            <p class="eocrm-form-field" data-eocrm-transaction-date-field <?php echo $tx_form_requires_transaction_date ? '' : 'hidden'; ?>>
                                <label><?php echo wp_kses_post($tx_form_date_label); ?></label>
                                <input type="date" name="transaction_date" value="<?php echo esc_attr($tx_form_transaction_date); ?>" <?php echo $tx_form_requires_transaction_date ? 'required' : ''; ?>>
                            </p>
                            <p class="eocrm-form-field">
                                <label>Wsp&#243;&#322;praca z innym Agentem</label>
                                <span class="eocrm-choice-toggle">
                                    <label class="eocrm-choice-toggle-item">
                                        <input type="radio" name="cooperation_agent" value="1" <?php checked($tx_form_cooperation_agent); ?>>
                                        <span>Tak</span>
                                    </label>
                                    <label class="eocrm-choice-toggle-item">
                                        <input type="radio" name="cooperation_agent" value="0" <?php checked(! $tx_form_cooperation_agent); ?>>
                                        <span>Nie</span>
                                    </label>
                                </span>
                            </p>
                        </div>

                        <div class="eocrm-transaction-cooperation-details" data-eocrm-cooperation-details <?php echo $tx_form_cooperation_agent ? '' : 'hidden'; ?>>
                            <div class="eocrm-form-grid">
                                <p class="eocrm-form-field">
                                    <label>Rodzaj wsp&#243;&#322;pracy</label>
                                    <span class="eocrm-choice-toggle">
                                        <label class="eocrm-choice-toggle-item">
                                            <input type="radio" name="cooperation_type" value="own_office" <?php checked($tx_form_cooperation_type, 'own_office'); ?>>
                                            <span>Biuro w&#322;asne</span>
                                        </label>
                                        <label class="eocrm-choice-toggle-item">
                                            <input type="radio" name="cooperation_type" value="other_office" <?php checked($tx_form_cooperation_type, 'other_office'); ?>>
                                            <span>Inne biuro</span>
                                        </label>
                                    </span>
                                </p>
                                <p class="eocrm-form-field" data-eocrm-cooperation-own>
                                    <label>Agent wsp&#243;&#322;pracuj&#261;cy z CRM</label>
                                    <select name="cooperation_agent_user_id">
                                        <option value="">Wybierz Agenta</option>
                                        <?php foreach ($transaction_agent_options as $tx_agent_option) :
                                            $tx_agent_user_id = (int) ($tx_agent_option['user_id'] ?? 0);
                                            if ($tx_agent_user_id <= 0 || $tx_agent_user_id === $tx_form_owner_user_id) {
                                                continue;
                                            }
                                            $tx_agent_label = (string) ($tx_agent_option['label'] ?? '');
                                            $tx_agent_office = (string) ($tx_agent_option['office'] ?? '');
                                        ?>
                                            <option value="<?php echo esc_attr((string) $tx_agent_user_id); ?>" <?php selected($tx_form_cooperation_agent_user_id, $tx_agent_user_id); ?>>
                                                <?php echo esc_html($tx_agent_label . ($tx_agent_office !== '' ? ' - ' . $tx_agent_office : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p class="eocrm-form-field" data-eocrm-cooperation-other>
                                    <label>Nazwa biura</label>
                                    <input type="text" name="cooperation_office_name" value="<?php echo esc_attr($tx_form_cooperation_office_name); ?>" placeholder="np. Biuro Partnerskie">
                                </p>
                                <p class="eocrm-form-field" data-eocrm-cooperation-other>
                                    <label>Agent wsp&#243;&#322;pracuj&#261;cy</label>
                                    <input type="text" name="cooperation_agent_name" value="<?php echo esc_attr($tx_form_cooperation_agent_name); ?>" placeholder="Imi&#281; i nazwisko">
                                </p>
                            </div>
                        </div>

                        <p>
                            <button class="eocrm-btn eocrm-btn-primary" type="submit" data-eocrm-transaction-submit data-default-label="<?php echo esc_attr($is_transaction_edit ? 'Zapisz transakcję' : 'Dodaj transakcję'); ?>" data-partial-label="<?php echo esc_attr($is_transaction_edit ? 'Zapisz transakcję częściową' : 'Dodaj transakcję częściową'); ?>"><?php echo wp_kses_post($is_transaction_edit ? 'Zapisz transakcj&#281;' : ($tx_form_is_partial_commission_stage ? 'Dodaj transakcj&#281; cz&#281;&#347;ciow&#261;' : 'Dodaj transakcj&#281;')); ?></button>
                            <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $tx_form_agreement_id], $current_url)); ?>">Wr&#243;&#263; do umowy</a>
                        </p>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($transaction_id > 0 && ! is_array($transaction_profile) && $transactions_mode !== 'edit-transaction') : ?>
            <div class="eocrm-alert eocrm-alert-error">Nie znaleziono profilu transakcji.</div>
        <?php endif; ?>

        <?php if (is_array($transaction_profile) && $transactions_mode !== 'edit-transaction') : ?>
            <?php
            $tx_profile_id = (int) ($transaction_profile['id'] ?? 0);
            $tx_profile_agreement_id = (int) ($transaction_profile['agreement_id'] ?? 0);
            $tx_profile_property_id = (int) ($transaction_profile['property_id'] ?? 0);
            $tx_profile_type = strtoupper(trim((string) ($transaction_profile['transaction_type'] ?? '')));
            $tx_profile_type_label = $transaction_label_map[$tx_profile_type] ?? ($tx_profile_type !== '' ? ucfirst(strtolower($tx_profile_type)) : '-');
            $tx_profile_currency = (string) ($transaction_profile['price_currency'] ?? 'PLN');
            $tx_profile_commission_stage_name = trim((string) ($transaction_profile['commission_stage_name'] ?? ''));
            $tx_profile_is_partial_transaction = $tx_profile_commission_stage_name !== '' && ! $is_settlement_commission_stage($tx_profile_commission_stage_name);
            $tx_profile_kind_label = $tx_profile_is_partial_transaction ? 'Transakcja cz&#281;&#347;ciowa' : 'Transakcja';
            $tx_profile_eyebrow_label = $tx_profile_is_partial_transaction ? 'Profil transakcji cz&#281;&#347;ciowej' : 'Profil transakcji';
            $tx_profile_remuneration = $format_transaction_remuneration(
                $transaction_profile['transaction_price'] ?? null,
                $transaction_profile['commission_amount'] ?? null,
                $transaction_profile['commission_unit'] ?? '',
                $tx_profile_currency
            );
            $tx_profile_payment_payload = $parse_commission_stage_payment_payload((string) ($transaction_profile['commission_stages_json'] ?? ''));
            $tx_profile_payment_rows = is_array($tx_profile_payment_payload['rows'] ?? null) ? (array) $tx_profile_payment_payload['rows'] : [];
            $tx_profile_payment_effective = is_array($tx_profile_payment_payload['effective'] ?? null) ? (array) $tx_profile_payment_payload['effective'] : [];
            $tx_profile_payment_effective_label = '-';
            if (isset($tx_profile_payment_effective['amount'], $tx_profile_payment_effective['unit']) && is_numeric((string) $tx_profile_payment_effective['amount'])) {
                $tx_profile_payment_effective_label = rtrim(rtrim(number_format((float) $tx_profile_payment_effective['amount'], 2, '.', ''), '0'), '.') . ' ' . (string) $tx_profile_payment_effective['unit'];
            }
            if ($tx_profile_payment_effective_label === '-' && isset($transaction_profile['commission_amount']) && is_numeric((string) $transaction_profile['commission_amount']) && (float) $transaction_profile['commission_amount'] > 0) {
                $tx_profile_payment_effective_label = rtrim(rtrim(number_format((float) $transaction_profile['commission_amount'], 2, '.', ''), '0'), '.') . ' ' . (string) ($transaction_profile['commission_unit'] ?? '');
            }
            $tx_profile_plan_rows = ! empty($transaction_profile['agreement_commission_split_enabled'])
                ? $parse_commission_stage_rows((string) ($transaction_profile['agreement_commission_stages_json'] ?? ''))
                : [];
            if (empty($tx_profile_plan_rows) && ! empty($tx_profile_payment_rows)) {
                $tx_profile_plan_rows = $tx_profile_payment_rows;
            }
            $tx_profile_stage_order = isset($agreement_stage_options_by_type[$tx_profile_type]) && is_array($agreement_stage_options_by_type[$tx_profile_type])
                ? array_values($agreement_stage_options_by_type[$tx_profile_type])
                : [];
            $tx_profile_stage_index_map = [];
            foreach ($tx_profile_stage_order as $stage_order_index => $stage_order_name) {
                $stage_order_key = $normalize_commission_stage_key((string) $stage_order_name);
                if ($stage_order_key !== '') {
                    $tx_profile_stage_index_map[$stage_order_key] = (int) $stage_order_index;
                }
            }
            if (! empty($tx_profile_plan_rows)) {
                usort($tx_profile_plan_rows, static function (array $left, array $right) use ($normalize_commission_stage_key, $tx_profile_stage_index_map): int {
                    $left_key = $normalize_commission_stage_key((string) ($left['stage_name'] ?? ''));
                    $right_key = $normalize_commission_stage_key((string) ($right['stage_name'] ?? ''));
                    $left_index = isset($tx_profile_stage_index_map[$left_key]) ? (int) $tx_profile_stage_index_map[$left_key] : PHP_INT_MAX;
                    $right_index = isset($tx_profile_stage_index_map[$right_key]) ? (int) $tx_profile_stage_index_map[$right_key] : PHP_INT_MAX;
                    return $left_index <=> $right_index;
                });
            }
            $tx_profile_covered_names = is_array($tx_profile_payment_payload['covered_stage_names'] ?? null)
                ? (array) $tx_profile_payment_payload['covered_stage_names']
                : [];
            if (empty($tx_profile_covered_names) && $tx_profile_commission_stage_name !== '') {
                $tx_profile_covered_names[] = $tx_profile_commission_stage_name;
            }
            $tx_profile_covered_keys = [];
            foreach ($tx_profile_covered_names as $tx_profile_covered_name) {
                $tx_profile_covered_key = $normalize_commission_stage_key((string) $tx_profile_covered_name);
                if ($tx_profile_covered_key !== '' && ! in_array($tx_profile_covered_key, $tx_profile_covered_keys, true)) {
                    $tx_profile_covered_keys[] = $tx_profile_covered_key;
                }
            }
            $tx_profile_current_stage_key = $normalize_commission_stage_key($tx_profile_commission_stage_name);
            $tx_profile_payment_timeline = [];
            foreach ($tx_profile_plan_rows as $tx_profile_plan_index => $tx_profile_plan_row) {
                $tx_profile_plan_stage_name = (string) ($tx_profile_plan_row['stage_name'] ?? '');
                $tx_profile_plan_key = $normalize_commission_stage_key($tx_profile_plan_stage_name);
                if ($tx_profile_plan_key === '') {
                    continue;
                }

                $tx_profile_plan_is_current = $tx_profile_current_stage_key !== '' && $tx_profile_plan_key === $tx_profile_current_stage_key;
                $tx_profile_plan_is_covered = in_array($tx_profile_plan_key, $tx_profile_covered_keys, true);
                $tx_profile_plan_status = $tx_profile_plan_is_covered ? 'completed' : ($tx_profile_plan_is_current ? 'current' : 'upcoming');
                $tx_profile_payment_timeline[] = [
                    'name' => $tx_profile_plan_stage_name,
                    'value' => $format_commission_stage_value(is_array($tx_profile_plan_row) ? $tx_profile_plan_row : []),
                    'status' => $tx_profile_plan_status,
                    'index' => $tx_profile_plan_index + 1,
                ];
            }
            $tx_profile_paid_steps = 0;
            foreach ($tx_profile_payment_timeline as $tx_profile_payment_step) {
                if (in_array((string) ($tx_profile_payment_step['status'] ?? ''), ['completed', 'current'], true)) {
                    $tx_profile_paid_steps++;
                }
            }
            $tx_profile_payment_progress = ! empty($tx_profile_payment_timeline)
                ? min(100, max(0, (int) round(($tx_profile_paid_steps / count($tx_profile_payment_timeline)) * 100)))
                : 0;
            ?>
            <section class="eocrm-prop-profile">
                <header class="eocrm-prop-profile-hero">
                    <div class="eocrm-prop-profile-hero__main">
                        <span class="eocrm-prop-profile-hero__eyebrow"><?php echo wp_kses_post($tx_profile_eyebrow_label); ?></span>
                        <h2 class="eocrm-prop-profile-hero__title">
                            <?php echo esc_html((string) ($transaction_profile['agreement_number'] ?? ('Transakcja #' . $tx_profile_id))); ?>
                        </h2>
                        <p class="eocrm-prop-profile-hero__meta">
                            <?php echo wp_kses_post($tx_profile_kind_label); ?>
                            <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                            <?php echo wp_kses_post($tx_profile_type_label); ?>
                            <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                            Data: <strong><?php echo esc_html($format_transaction_date((string) ($transaction_profile['transaction_date'] ?? '')) ?: '-'); ?></strong>
                        </p>
                    </div>
                    <div class="eocrm-prop-profile-hero__actions">
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'transactions'], $current_url)); ?>">&larr; Powr&#243;t</a>
                        <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'transactions', 'mode' => 'edit-transaction', 'transaction_id' => $tx_profile_id], $current_url)); ?>">Edytuj</a>
                        <?php if ($tx_profile_agreement_id > 0) : ?>
                            <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'agreements', 'agreement_id' => $tx_profile_agreement_id], $current_url)); ?>">Przejd&#378; do umowy</a>
                        <?php endif; ?>
                        <?php if ($is_admin_user) : ?>
                            <form class="eocrm-prop-profile-hero__delete" method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'transactions'], $current_url)); ?>" onsubmit="return confirm('Czy na pewno usunac te transakcje?');">
                                <?php wp_nonce_field('eocrm_delete_transaction', 'eocrm_transaction_delete_nonce'); ?>
                                <input type="hidden" name="eocrm_action" value="delete_transaction">
                                <input type="hidden" name="transaction_id" value="<?php echo esc_attr((string) $tx_profile_id); ?>">
                                <button class="eocrm-btn eocrm-btn-danger" type="submit">Usu&#324;</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (! empty($tx_profile_payment_timeline)) : ?>
                    <section class="eocrm-prop-stages eocrm-commission-payment-stages" aria-label="Etapy platnosci prowizji">
                        <header class="eocrm-prop-stages__head">
                            <div>
                                <h3>Etapy p&#322;atno&#347;ci prowizji</h3>
                                <p class="eocrm-prop-stages__hint">
                                    <?php echo wp_kses_post($tx_profile_is_partial_transaction ? 'Transakcja cz&#281;&#347;ciowa obejmuje' : 'Transakcja obejmuje'); ?>: <strong><?php echo esc_html($tx_profile_payment_effective_label); ?></strong>
                                </p>
                            </div>
                            <div class="eocrm-prop-stages__progress">
                                <span class="eocrm-prop-stages__progress-label">Post&#281;p</span>
                                <span class="eocrm-prop-stages__progress-value"><?php echo esc_html((string) $tx_profile_payment_progress); ?>%</span>
                                <span class="eocrm-prop-stages__progress-track">
                                    <span class="eocrm-prop-stages__progress-fill" style="width: <?php echo esc_attr((string) $tx_profile_payment_progress); ?>%;"></span>
                                </span>
                            </div>
                        </header>
                        <ol class="eocrm-prop-stages__list">
                            <?php foreach ($tx_profile_payment_timeline as $payment_stage_step) :
                                $payment_stage_status = (string) ($payment_stage_step['status'] ?? 'upcoming');
                                $payment_stage_class = $payment_stage_status === 'completed' ? 'is-completed' : ($payment_stage_status === 'current' ? 'is-current' : 'is-upcoming');
                                $payment_stage_name = (string) ($payment_stage_step['name'] ?? '');
                                $payment_stage_value = (string) ($payment_stage_step['value'] ?? '');
                                $payment_stage_index = (int) ($payment_stage_step['index'] ?? 0);
                            ?>
                                <li class="eocrm-prop-stage <?php echo esc_attr($payment_stage_class); ?>">
                                    <span class="eocrm-prop-stage__indicator" aria-hidden="true">
                                        <?php if ($payment_stage_status === 'completed') : ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php else : ?>
                                            <span class="eocrm-prop-stage__num"><?php echo esc_html((string) $payment_stage_index); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="eocrm-prop-stage__body">
                                        <span class="eocrm-prop-stage__name"><?php echo esc_html($payment_stage_name !== '' ? $payment_stage_name : ('Etap ' . (string) $payment_stage_index)); ?></span>
                                        <span class="eocrm-prop-stage__sub"><?php echo esc_html($payment_stage_status === 'completed' ? 'Rozliczono' : ($payment_stage_status === 'current' ? 'Aktualny' : 'Oczekuje')); ?><?php echo $payment_stage_value !== '' ? wp_kses_post(' &middot; ' . esc_html($payment_stage_value)) : ''; ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <div class="eocrm-profile-columns">
                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon eocrm-transaction-icon-shell" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                            <h3>Dane transakcji</h3>
                        </header>
                        <dl class="eocrm-prop-detail-card__defs eocrm-agreement-defs-grid">
                            <div><dt>Numer umowy</dt><dd><?php echo esc_html((string) ($transaction_profile['agreement_number'] ?? '-')); ?></dd></div>
                            <div><dt>Rodzaj wpisu</dt><dd><?php echo wp_kses_post($tx_profile_kind_label); ?></dd></div>
                            <div><dt>Typ transakcji</dt><dd><?php echo wp_kses_post($tx_profile_type_label); ?></dd></div>
                            <div><dt>Nieruchomo&#347;&#263;</dt><dd>
                                <?php if ($tx_profile_property_id > 0) : ?>
                                    <a href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => $tx_profile_property_id], $current_url)); ?>"><?php echo esc_html($build_transaction_property_label($transaction_profile)); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($build_transaction_property_label($transaction_profile)); ?>
                                <?php endif; ?>
                            </dd></div>
                            <div><dt>Cena ofertowa</dt><dd><?php echo esc_html($format_property_price($transaction_profile['offer_price'] ?? null, $tx_profile_currency)); ?></dd></div>
                            <div><dt>Cena transakcyjna</dt><dd><?php echo esc_html($format_optional_transaction_price($transaction_profile['transaction_price'] ?? null, $tx_profile_currency)); ?></dd></div>
                            <div><dt>Prowizja</dt><dd><?php echo esc_html(((float) ($transaction_profile['commission_amount'] ?? 0) > 0) ? rtrim(rtrim(number_format((float) ($transaction_profile['commission_amount'] ?? 0), 2, '.', ''), '0'), '.') . ' ' . (string) ($transaction_profile['commission_unit'] ?? '') : '-'); ?></dd></div>
                            <?php if (! empty($transaction_profile['commission_stage_name'])) : ?>
                                <div><dt>Etap prowizji</dt><dd><?php echo esc_html((string) $transaction_profile['commission_stage_name']); ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Wynagrodzenie</dt><dd><strong><?php echo esc_html($tx_profile_remuneration); ?></strong></dd></div>
                            <div><dt>Data transakcji</dt><dd><?php echo esc_html($format_transaction_date((string) ($transaction_profile['transaction_date'] ?? '')) ?: '-'); ?></dd></div>
                            <div><dt>Wsp&#243;&#322;praca z innym Agentem</dt><dd><?php echo ! empty($transaction_profile['cooperation_agent']) ? 'Tak' : 'Nie'; ?></dd></div>
                            <?php if (! empty($transaction_profile['cooperation_agent'])) :
                                $tx_profile_coop_type = (string) ($transaction_profile['cooperation_type'] ?? '');
                                $tx_profile_coop_agent_name = $tx_profile_coop_type === 'own_office'
                                    ? (string) ($transaction_profile['cooperation_user_display_name'] ?? '')
                                    : (string) ($transaction_profile['cooperation_agent_name'] ?? '');
                                $tx_profile_coop_office = $tx_profile_coop_type === 'other_office'
                                    ? (string) ($transaction_profile['cooperation_office_name'] ?? '')
                                    : 'Biuro w&#322;asne';
                            ?>
                                <div><dt>Typ wsp&#243;&#322;pracy</dt><dd><?php echo wp_kses_post($tx_profile_coop_type === 'other_office' ? 'Inne biuro' : 'Biuro w&#322;asne'); ?></dd></div>
                                <div><dt>Biuro wsp&#243;&#322;pracuj&#261;ce</dt><dd><?php echo wp_kses_post($tx_profile_coop_office !== '' ? $tx_profile_coop_office : '-'); ?></dd></div>
                                <div><dt>Agent wsp&#243;&#322;pracuj&#261;cy</dt><dd><?php echo esc_html($tx_profile_coop_agent_name !== '' ? $tx_profile_coop_agent_name : '-'); ?></dd></div>
                            <?php endif; ?>
                        </dl>
                    </article>
                </div>
            </section>
        <?php elseif (! in_array($transactions_mode, ['new-transaction', 'edit-transaction'], true)) : ?>
            <?php
            $transactions_total_count = is_array($transactions_rows) ? count($transactions_rows) : 0;
            ?>
            <section class="eocrm-prop-toolbar" aria-label="Wyszukiwanie transakcji">
                <form method="get" class="eocrm-prop-toolbar__search" role="search">
                    <input type="hidden" name="crm" value="transactions">
                    <span class="eocrm-prop-toolbar__search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    </span>
                    <input
                        id="eocrm-transactions-search"
                        data-live-filter
                        data-target-table="eocrm-transactions-table"
                        name="q"
                        type="search"
                        value="<?php echo esc_attr($search); ?>"
                        placeholder="Szukaj po numerze umowy, nieruchomo&#347;ci, opiekunie..."
                        aria-label="Szukaj transakcji"
                    >
                    <button class="eocrm-prop-toolbar__search-btn" type="submit">Szukaj</button>
                </form>
            </section>

            <div class="eocrm-prop-meta">
                <p class="eocrm-prop-meta__count">
                    <strong><?php echo esc_html((string) $transactions_total_count); ?></strong>
                    <span>transakcji</span>
                    <?php if ($search !== '') : ?>
                        <em class="eocrm-prop-meta__hint">dla zapytania &laquo;<?php echo esc_html($search); ?>&raquo;</em>
                    <?php endif; ?>
                </p>
            </div>

            <div class="eocrm-prop-list-wrap">
                <table class="eocrm-table eocrm-prop-list" id="eocrm-transactions-table">
                    <thead>
                        <tr>
                            <th class="eocrm-prop-col-main">Transakcja</th>
                            <th class="eocrm-prop-col-loc">Nieruchomo&#347;&#263;</th>
                            <th class="eocrm-prop-col-price">Cena transakcyjna</th>
                            <th class="eocrm-prop-col-price">Wynagrodzenie</th>
                            <th class="eocrm-prop-col-date">Data</th>
                            <th class="eocrm-prop-col-status">Status</th>
                            <th class="eocrm-prop-col-status">Wsp&#243;&#322;praca</th>
                            <?php if ($show_owner_columns) : ?>
                                <th class="eocrm-prop-col-owner">Opiekun</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions_rows)) : ?>
                            <tr class="eocrm-prop-empty-row"><td colspan="<?php echo esc_attr((string) ($show_owner_columns ? 8 : 7)); ?>">
                                <div class="eocrm-prop-empty">
                                    <span class="eocrm-prop-empty__icon eocrm-transaction-empty-icon" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                                    <strong>Brak transakcji do wy&#347;wietlenia</strong>
                                    <span>Transakcje dodasz z profilu umowy.</span>
                                </div>
                            </td></tr>
                        <?php else : ?>
                            <?php foreach ($transactions_rows as $tx_row) :
                                $tx_row_id = (int) ($tx_row['id'] ?? 0);
                                $tx_row_url = add_query_arg(['crm' => 'transactions', 'transaction_id' => $tx_row_id], $current_url);
                                $tx_row_type = strtoupper(trim((string) ($tx_row['transaction_type'] ?? '')));
                                $tx_row_type_label = $transaction_label_map[$tx_row_type] ?? ($tx_row_type !== '' ? ucfirst(strtolower($tx_row_type)) : '-');
                                $tx_row_commission_stage_name = trim((string) ($tx_row['commission_stage_name'] ?? ''));
                                $tx_row_is_partial_transaction = $tx_row_commission_stage_name !== '' && ! $is_settlement_commission_stage($tx_row_commission_stage_name);
                                $tx_row_agreement_stage = trim((string) ($tx_row['agreement_stage'] ?? ''));
                                $tx_row_agreement_finished = $tx_row_agreement_stage !== '' && $is_agreement_stage_finished($tx_row_agreement_stage);
                                $tx_row_agreement_status_label = $tx_row_agreement_stage !== ''
                                    ? $tx_row_agreement_stage
                                    : (! empty($tx_row['agreement_is_active']) ? 'W trakcie' : 'Brak umowy');
                                $tx_row_currency = (string) ($tx_row['price_currency'] ?? 'PLN');
                                $tx_row_remuneration = $format_transaction_remuneration($tx_row['transaction_price'] ?? null, $tx_row['commission_amount'] ?? null, $tx_row['commission_unit'] ?? '', $tx_row_currency);
                                $tx_owner_name = trim((string) ($tx_row['owner_display_name'] ?? ''));
                                if ($tx_owner_name === '') {
                                    $tx_owner = get_userdata((int) ($tx_row['owner_user_id'] ?? 0));
                                    $tx_owner_name = $tx_owner instanceof WP_User ? (string) $tx_owner->display_name : '-';
                                    $tx_owner_avatar = $tx_owner instanceof WP_User ? $resolve_owner_photo_url((int) $tx_owner->ID, 'thumbnail') : '';
                                } else {
                                    $tx_owner_avatar = $resolve_owner_photo_url((int) ($tx_row['owner_user_id'] ?? 0), 'thumbnail');
                                }
                                $tx_owner_initial = $tx_owner_name !== '' && $tx_owner_name !== '-' ? mb_strtoupper(mb_substr($tx_owner_name, 0, 1)) : '?';
                            ?>
                                <tr class="eocrm-prop-row">
                                    <td class="eocrm-prop-cell-main">
                                        <a class="eocrm-prop-link" href="<?php echo esc_url($tx_row_url); ?>">
                                            <span class="eocrm-prop-thumb eocrm-prop-thumb--icon eocrm-prop-thumb--transaction" aria-hidden="true"><?php $render_transaction_icon(); ?></span>
                                            <span class="eocrm-prop-titlewrap">
                                                <span class="eocrm-prop-title"><?php echo esc_html((string) ($tx_row['agreement_number'] ?? ('Transakcja #' . $tx_row_id))); ?></span>
                                                <?php if ($tx_row_is_partial_transaction) : ?>
                                                    <span class="eocrm-prop-subtitle">Transakcja cz&#281;&#347;ciowa</span>
                                                <?php endif; ?>
                                                <span class="eocrm-prop-subtitle"><?php echo wp_kses_post($tx_row_type_label); ?></span>
                                                <?php if ($tx_row_commission_stage_name !== '') : ?>
                                                    <span class="eocrm-prop-subtitle">Etap prowizji: <?php echo esc_html($tx_row_commission_stage_name); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="eocrm-prop-cell-loc">
                                        <?php if ((int) ($tx_row['property_id'] ?? 0) > 0) : ?>
                                            <a class="eocrm-prop-loc-primary eocrm-prop-loc-primary--link" href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => (int) ($tx_row['property_id'] ?? 0)], $current_url)); ?>"><?php echo esc_html($build_transaction_property_label($tx_row)); ?></a>
                                        <?php else : ?>
                                            <span class="eocrm-prop-loc-primary"><?php echo esc_html($build_transaction_property_label($tx_row)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="eocrm-prop-cell-price"><span class="eocrm-prop-price"><?php echo esc_html($format_optional_transaction_price($tx_row['transaction_price'] ?? null, $tx_row_currency)); ?></span></td>
                                    <td class="eocrm-prop-cell-price"><span class="eocrm-prop-price"><?php echo esc_html($tx_row_remuneration); ?></span></td>
                                    <td class="eocrm-prop-cell-date"><span class="eocrm-prop-date-main"><?php echo esc_html($format_transaction_date((string) ($tx_row['transaction_date'] ?? '')) ?: '-'); ?></span></td>
                                    <td class="eocrm-prop-cell-status"><span class="eocrm-prop-stage-pill <?php echo esc_attr($tx_row_agreement_finished ? 'is-done' : 'is-progress'); ?>"><?php echo esc_html($tx_row_agreement_status_label); ?></span></td>
                                    <td class="eocrm-prop-cell-status"><span class="eocrm-prop-client-type-pill <?php echo ! empty($tx_row['cooperation_agent']) ? 'is-company' : 'is-individual'; ?>"><?php echo ! empty($tx_row['cooperation_agent']) ? 'Tak' : 'Nie'; ?></span></td>
                                    <?php if ($show_owner_columns) : ?>
                                        <td class="eocrm-prop-cell-owner">
                                            <span class="eocrm-prop-owner">
                                                <span class="eocrm-prop-owner__avatar" aria-hidden="true">
                                                    <?php if (! empty($tx_owner_avatar)) : ?>
                                                        <img src="<?php echo esc_url((string) $tx_owner_avatar); ?>" alt="" loading="lazy">
                                                    <?php else : ?>
                                                        <span class="eocrm-prop-owner__initial"><?php echo esc_html($tx_owner_initial); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="eocrm-prop-owner__name"><?php echo esc_html($tx_owner_name); ?></span>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($section === 'clients') : ?>
        <?php if ($crm_notice === 'client_created') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Klient zostal dodany.'); ?></div>
        <?php elseif ($crm_notice === 'client_updated') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Dane klienta zostaly zaktualizowane.'); ?></div>
        <?php elseif ($crm_notice === 'client_deleted') : ?>
            <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Klient zostal usuniety.'); ?></div>
        <?php elseif ($crm_notice === 'client_error') : ?>
            <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac klienta.'); ?></div>
        <?php endif; ?>

        <section class="eocrm-actions-row">
            <a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'mode' => 'new-client'], $current_url)); ?>">Dodaj klienta</a>
            <?php if ($clients_mode !== '') : ?>
                <?php
                $close_client_args = ['crm' => 'clients'];
                if ($clients_mode === 'edit-client' && is_array($client_profile)) {
                    $close_client_args['client_id'] = (int) ($client_profile['id'] ?? 0);
                }
                ?>
                <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg($close_client_args, $current_url)); ?>">Zamknij formularz</a>
            <?php endif; ?>
        </section>

        <?php if ($clients_mode === 'new-client') : ?>
            <section class="eocrm-card">
                <h3>Dodawanie klienta</h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'mode' => 'new-client'], $current_url)); ?>" data-eocrm-client-form>
                    <?php wp_nonce_field('eocrm_create_client', 'eocrm_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="create_client">

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label for="eocrm-client-type">Typ klienta</label>
                            <select id="eocrm-client-type" name="client_type" data-eocrm-client-type>
                                <option value="person">Osoba fizyczna</option>
                                <option value="company">Firma</option>
                            </select>
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-client-person-group">
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Imie</label>
                            <input type="text" name="first_name" data-eocrm-required-person required>
                        </p>
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Nazwisko</label>
                            <input type="text" name="last_name" data-eocrm-required-person required>
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-client-company-group is-hidden">
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>Nazwa firmy</label>
                            <input type="text" name="company_name" data-eocrm-required-company>
                        </p>
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>Imie i nazwisko reprezentanta</label>
                            <input type="text" name="representative_name">
                        </p>
                    </div>

                    <h4>Dane kontaktowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Telefon</label>
                            <input type="text" name="phone" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>E-mail</label>
                            <input type="email" name="email">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company is-hidden">
                            <label>Strona WWW</label>
                            <input type="url" name="website">
                        </p>
                    </div>

                    <h4>Dane identyfikacyjne</h4>
                    <div class="eocrm-form-grid eocrm-client-person-group">
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>PESEL</label>
                            <input type="text" name="pesel">
                        </p>
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Rodzaj dokumentu</label>
                            <select name="document_type">
                                <option value="">Wybierz</option>
                                <option value="dowod_osobisty">Dowod osobisty</option>
                                <option value="paszport">Paszport</option>
                                <option value="karta_pobytu">Karta pobytu</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Numer dokumentu</label>
                            <input type="text" name="document_number">
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-client-company-group is-hidden">
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>NIP</label>
                            <input type="text" name="nip">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>KRS</label>
                            <input type="text" name="krs">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>REGON</label>
                            <input type="text" name="regon">
                        </p>
                    </div>

                    <h4>Adres zamieszkania / rejestrowy</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="address_street" required></p>
                        <p class="eocrm-form-field"><label>Numer</label><input type="text" name="address_building_no" required></p>
                        <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="address_apartment_no"></p>
                        <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="address_postal_code" required></p>
                        <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="address_city" required></p>
                        <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="address_country" value="Polska"></p>
                    </div>

                    <h4>Adres korespondencyjny</h4>
                    <p class="eocrm-form-field-checkbox">
                        <label>
                            <input type="checkbox" name="correspondence_same" value="1" checked data-eocrm-correspondence-same>
                            Adres korespondencyjny taki sam
                        </label>
                    </p>

                    <div class="eocrm-form-grid eocrm-client-correspondence is-hidden">
                        <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="corr_street" data-eocrm-corr-field></p>
                        <p class="eocrm-form-field"><label>Numer</label><input type="text" name="corr_building_no" data-eocrm-corr-field></p>
                        <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="corr_apartment_no"></p>
                        <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="corr_postal_code" data-eocrm-corr-field></p>
                        <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="corr_city" data-eocrm-corr-field></p>
                        <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="corr_country" value="Polska"></p>
                    </div>

                    <p>
                        <button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz klienta</button>
                    </p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($clients_mode === 'edit-client' && is_array($client_profile)) : ?>
            <?php
            $edit_client_main_address = isset($client_profile_addresses['main']) && is_array($client_profile_addresses['main']) ? $client_profile_addresses['main'] : [];
            $edit_client_corr_address = isset($client_profile_addresses['correspondence']) && is_array($client_profile_addresses['correspondence']) ? $client_profile_addresses['correspondence'] : [];
            $edit_client_is_company = ((string) ($client_profile['client_type'] ?? '') === 'company');
            $edit_client_owner_id = (int) ($client_profile['owner_user_id'] ?? 0);
            $edit_client_owner_label = $resolve_owner_label($edit_client_owner_id);
            $edit_client_corr_same = true;
            if (! empty($edit_client_main_address) && ! empty($edit_client_corr_address)) {
                $edit_client_corr_same = (
                    (string) ($edit_client_main_address['street'] ?? '') === (string) ($edit_client_corr_address['street'] ?? '')
                    && (string) ($edit_client_main_address['building_no'] ?? '') === (string) ($edit_client_corr_address['building_no'] ?? '')
                    && (string) ($edit_client_main_address['apartment_no'] ?? '') === (string) ($edit_client_corr_address['apartment_no'] ?? '')
                    && (string) ($edit_client_main_address['postal_code'] ?? '') === (string) ($edit_client_corr_address['postal_code'] ?? '')
                    && (string) ($edit_client_main_address['city'] ?? '') === (string) ($edit_client_corr_address['city'] ?? '')
                    && (string) ($edit_client_main_address['country'] ?? '') === (string) ($edit_client_corr_address['country'] ?? '')
                );
            }
            ?>
            <section class="eocrm-card">
                <h3>Edycja klienta</h3>
                <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'mode' => 'edit-client', 'client_id' => (int) ($client_profile['id'] ?? 0)], $current_url)); ?>" data-eocrm-client-form>
                    <?php wp_nonce_field('eocrm_update_client', 'eocrm_client_update_nonce'); ?>
                    <input type="hidden" name="eocrm_action" value="update_client">
                    <input type="hidden" name="client_id" value="<?php echo (int) ($client_profile['id'] ?? 0); ?>">

                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label for="eocrm-client-type-edit">Typ klienta</label>
                            <select id="eocrm-client-type-edit" name="client_type" data-eocrm-client-type>
                                <option value="person" <?php selected($edit_client_is_company, false); ?>>Osoba fizyczna</option>
                                <option value="company" <?php selected($edit_client_is_company, true); ?>>Firma</option>
                            </select>
                        </p>
                        <?php if ($is_admin_user) : ?>
                            <p class="eocrm-form-field">
                                <label>Opiekun</label>
                                <select name="owner_user_id" required>
                                    <option value="">Wybierz opiekuna</option>
                                    <?php if ($edit_client_owner_id > 0 && ! in_array($edit_client_owner_id, $owner_option_ids, true)) : ?>
                                        <option value="<?php echo esc_attr((string) $edit_client_owner_id); ?>" selected><?php echo esc_html($edit_client_owner_label !== '' ? $edit_client_owner_label : ('Uzytkownik #' . (string) $edit_client_owner_id)); ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($owner_user_options as $owner_option_row) : ?>
                                        <?php $owner_option_id = (int) ($owner_option_row['id'] ?? 0); ?>
                                        <?php if ($owner_option_id <= 0) {
                                            continue;
                                        } ?>
                                        <option value="<?php echo esc_attr((string) $owner_option_id); ?>" <?php selected($owner_option_id, $edit_client_owner_id); ?>>
                                            <?php echo esc_html((string) ($owner_option_row['label'] ?? ('Uzytkownik #' . (string) $owner_option_id))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="eocrm-form-grid eocrm-client-person-group <?php echo $edit_client_is_company ? 'is-hidden' : ''; ?>">
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Imie</label>
                            <input type="text" name="first_name" data-eocrm-required-person value="<?php echo esc_attr((string) ($client_profile['first_name'] ?? '')); ?>" <?php echo $edit_client_is_company ? '' : 'required'; ?>>
                        </p>
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Nazwisko</label>
                            <input type="text" name="last_name" data-eocrm-required-person value="<?php echo esc_attr((string) ($client_profile['last_name'] ?? '')); ?>" <?php echo $edit_client_is_company ? '' : 'required'; ?>>
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-client-company-group <?php echo $edit_client_is_company ? '' : 'is-hidden'; ?>">
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>Nazwa firmy</label>
                            <input type="text" name="company_name" data-eocrm-required-company value="<?php echo esc_attr((string) ($client_profile['company_name'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>Imie i nazwisko reprezentanta</label>
                            <input type="text" name="representative_name" value="<?php echo esc_attr((string) ($client_profile['representative_name'] ?? '')); ?>">
                        </p>
                    </div>

                    <h4>Dane kontaktowe</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field">
                            <label>Telefon</label>
                            <input type="text" name="phone" value="<?php echo esc_attr((string) ($client_profile['phone'] ?? '')); ?>" required>
                        </p>
                        <p class="eocrm-form-field">
                            <label>E-mail</label>
                            <input type="email" name="email" value="<?php echo esc_attr((string) ($client_profile['email'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company <?php echo $edit_client_is_company ? '' : 'is-hidden'; ?>">
                            <label>Strona WWW</label>
                            <input type="url" name="website" value="<?php echo esc_attr((string) ($client_profile['website'] ?? '')); ?>">
                        </p>
                    </div>

                    <h4>Dane identyfikacyjne</h4>
                    <div class="eocrm-form-grid eocrm-client-person-group <?php echo $edit_client_is_company ? 'is-hidden' : ''; ?>">
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>PESEL</label>
                            <input type="text" name="pesel" value="<?php echo esc_attr((string) ($client_profile['pesel'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Rodzaj dokumentu</label>
                            <select name="document_type">
                                <option value="">Wybierz</option>
                                <option value="dowod_osobisty" <?php selected((string) ($client_profile['document_type'] ?? ''), 'dowod_osobisty'); ?>>Dowod osobisty</option>
                                <option value="paszport" <?php selected((string) ($client_profile['document_type'] ?? ''), 'paszport'); ?>>Paszport</option>
                                <option value="karta_pobytu" <?php selected((string) ($client_profile['document_type'] ?? ''), 'karta_pobytu'); ?>>Karta pobytu</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field eocrm-client-person">
                            <label>Numer dokumentu</label>
                            <input type="text" name="document_number" value="<?php echo esc_attr((string) ($client_profile['document_number'] ?? '')); ?>">
                        </p>
                    </div>

                    <div class="eocrm-form-grid eocrm-client-company-group <?php echo $edit_client_is_company ? '' : 'is-hidden'; ?>">
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>NIP</label>
                            <input type="text" name="nip" value="<?php echo esc_attr((string) ($client_profile['nip'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>KRS</label>
                            <input type="text" name="krs" value="<?php echo esc_attr((string) ($client_profile['krs'] ?? '')); ?>">
                        </p>
                        <p class="eocrm-form-field eocrm-client-company">
                            <label>REGON</label>
                            <input type="text" name="regon" value="<?php echo esc_attr((string) ($client_profile['regon'] ?? '')); ?>">
                        </p>
                    </div>

                    <h4>Adres zamieszkania / rejestrowy</h4>
                    <div class="eocrm-form-grid">
                        <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="address_street" value="<?php echo esc_attr((string) ($edit_client_main_address['street'] ?? '')); ?>" required></p>
                        <p class="eocrm-form-field"><label>Numer</label><input type="text" name="address_building_no" value="<?php echo esc_attr((string) ($edit_client_main_address['building_no'] ?? '')); ?>" required></p>
                        <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="address_apartment_no" value="<?php echo esc_attr((string) ($edit_client_main_address['apartment_no'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="address_postal_code" value="<?php echo esc_attr((string) ($edit_client_main_address['postal_code'] ?? '')); ?>" required></p>
                        <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="address_city" value="<?php echo esc_attr((string) ($edit_client_main_address['city'] ?? '')); ?>" required></p>
                        <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="address_country" value="<?php echo esc_attr((string) ($edit_client_main_address['country'] ?? 'Polska')); ?>"></p>
                    </div>

                    <h4>Adres korespondencyjny</h4>
                    <p class="eocrm-form-field-checkbox">
                        <label>
                            <input type="checkbox" name="correspondence_same" value="1" data-eocrm-correspondence-same <?php checked($edit_client_corr_same); ?>>
                            Adres korespondencyjny taki sam
                        </label>
                    </p>

                    <div class="eocrm-form-grid eocrm-client-correspondence <?php echo $edit_client_corr_same ? 'is-hidden' : ''; ?>">
                        <p class="eocrm-form-field"><label>Ulica</label><input type="text" name="corr_street" data-eocrm-corr-field value="<?php echo esc_attr((string) ($edit_client_corr_address['street'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Numer</label><input type="text" name="corr_building_no" data-eocrm-corr-field value="<?php echo esc_attr((string) ($edit_client_corr_address['building_no'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Lokal</label><input type="text" name="corr_apartment_no" value="<?php echo esc_attr((string) ($edit_client_corr_address['apartment_no'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Kod pocztowy</label><input type="text" name="corr_postal_code" data-eocrm-corr-field value="<?php echo esc_attr((string) ($edit_client_corr_address['postal_code'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Miasto</label><input type="text" name="corr_city" data-eocrm-corr-field value="<?php echo esc_attr((string) ($edit_client_corr_address['city'] ?? '')); ?>"></p>
                        <p class="eocrm-form-field"><label>Kraj</label><input type="text" name="corr_country" value="<?php echo esc_attr((string) ($edit_client_corr_address['country'] ?? 'Polska')); ?>"></p>
                    </div>

                    <p>
                        <button class="eocrm-btn eocrm-btn-primary" type="submit">Zapisz zmiany klienta</button>
                    </p>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($client_id > 0 && ! is_array($client_profile)) : ?>
            <div class="eocrm-alert eocrm-alert-error">Nie znaleziono profilu klienta.</div>
        <?php endif; ?>

        <?php if (is_array($client_profile)) : ?>
            <?php
            $client_profile_id = (int) ($client_profile['id'] ?? 0);
            $client_owner = get_userdata((int) ($client_profile['owner_user_id'] ?? 0));
            $client_main_address = isset($client_profile_addresses['main']) && is_array($client_profile_addresses['main']) ? $client_profile_addresses['main'] : [];
            $client_corr_address = isset($client_profile_addresses['correspondence']) && is_array($client_profile_addresses['correspondence']) ? $client_profile_addresses['correspondence'] : [];
            $client_profile_type_code = (string) ($client_profile['client_type'] ?? 'individual');
            $client_profile_is_company = $client_profile_type_code === 'company';
            $client_profile_display_name = $client_profile_is_company
                ? (string) ($client_profile['company_name'] ?? '')
                : trim((string) ($client_profile['first_name'] ?? '') . ' ' . (string) ($client_profile['last_name'] ?? ''));
            if ($client_profile_display_name === '') {
                $client_profile_display_name = 'Klient #' . $client_profile_id;
            }
            $client_profile_initial = mb_strtoupper(mb_substr($client_profile_display_name, 0, 1));

            $client_profile_phone = trim((string) ($client_profile['phone'] ?? ''));
            $client_profile_email = trim((string) ($client_profile['email'] ?? ''));
            $client_profile_website = trim((string) ($client_profile['website'] ?? ''));

            $client_owner_name = $client_owner instanceof WP_User ? (string) $client_owner->display_name : '';
            $client_owner_avatar = $client_owner instanceof WP_User ? $resolve_owner_photo_url((int) $client_owner->ID, 'medium') : '';
            $client_owner_initial = $client_owner_name !== '' ? mb_strtoupper(mb_substr($client_owner_name, 0, 1)) : '';

            $client_format_address = static function (array $addr) use ($format_address): string {
                return $format_address([
                    trim((string) ($addr['street'] ?? '') . ' ' . (string) ($addr['building_no'] ?? '') . ((string) ($addr['apartment_no'] ?? '') !== '' ? '/' . (string) ($addr['apartment_no'] ?? '') : '')),
                    (string) ($addr['postal_code'] ?? '') . ' ' . (string) ($addr['city'] ?? ''),
                    (string) ($addr['country'] ?? ''),
                ]);
            };
            $client_main_address_str = $client_format_address($client_main_address);
            $client_corr_address_str = $client_format_address($client_corr_address);

            $client_agreements_count = is_array($client_profile_agreements) ? count($client_profile_agreements) : 0;
            $client_properties_count = is_array($client_profile_properties) ? count($client_profile_properties) : 0;
            $client_searches_count = is_array($client_profile_searches) ? count($client_profile_searches) : 0;

            $client_active_agreements_count = 0;
            if (is_array($client_profile_agreements)) {
                foreach ($client_profile_agreements as $client_ag_row) {
                    $client_ag_stage = (string) ($client_ag_row['current_stage'] ?? '');
                    if (! $is_agreement_stage_finished($client_ag_stage)) {
                        $client_active_agreements_count++;
                    }
                }
            }

            // Build themed identity rows
            $client_has_display_value = static function ($value): bool {
                if ($value === null) return false;
                $sv = trim((string) $value);
                return $sv !== '' && $sv !== '-';
            };
            $client_add_row = static function (array &$rows, string $label, $value) use ($client_has_display_value): void {
                if (! $client_has_display_value($value)) return;
                $rows[] = ['label' => $label, 'value' => (string) $value];
            };

            // Identity card
            $client_rows_identity = [];
            $client_add_row($client_rows_identity, 'Typ klienta', $client_profile_is_company ? 'Firma' : 'Osoba fizyczna');
            if ($client_profile_is_company) {
                $client_add_row($client_rows_identity, 'Nazwa firmy', (string) ($client_profile['company_name'] ?? ''));
                $client_add_row($client_rows_identity, 'Reprezentant', (string) ($client_profile['representative_name'] ?? ''));
                $client_add_row($client_rows_identity, 'NIP', (string) ($client_profile['nip'] ?? ''));
                $client_add_row($client_rows_identity, 'KRS', (string) ($client_profile['krs'] ?? ''));
                $client_add_row($client_rows_identity, 'REGON', (string) ($client_profile['regon'] ?? ''));
            } else {
                $client_add_row($client_rows_identity, 'Imi&#281;', (string) ($client_profile['first_name'] ?? ''));
                $client_add_row($client_rows_identity, 'Nazwisko', (string) ($client_profile['last_name'] ?? ''));
                $client_add_row($client_rows_identity, 'PESEL', (string) ($client_profile['pesel'] ?? ''));
                $client_doc_type_raw = (string) ($client_profile['document_type'] ?? '');
                $client_doc_type_label_map = [
                    'dowod_osobisty' => 'Dow&oacute;d osobisty',
                    'paszport' => 'Paszport',
                    'karta_pobytu' => 'Karta pobytu',
                ];
                $client_doc_type = $client_doc_type_label_map[$client_doc_type_raw] ?? $client_doc_type_raw;
                $client_doc_number = (string) ($client_profile['document_number'] ?? '');
                if ($client_doc_type !== '' || $client_doc_number !== '') {
                    $client_add_row($client_rows_identity, 'Dokument', trim($client_doc_type . ' ' . $client_doc_number));
                }
            }

            // Contact card
            $client_rows_contact = [];
            $client_add_row($client_rows_contact, 'Telefon', $client_profile_phone);
            $client_add_row($client_rows_contact, 'E-mail', $client_profile_email);
            $client_add_row($client_rows_contact, 'Strona WWW', $client_profile_website);

            // Meta card
            $client_rows_meta = [];
            $client_add_row($client_rows_meta, 'Opiekun', $client_owner_name);
            $client_add_row($client_rows_meta, 'Utworzono', (string) ($client_profile['created_at'] ?? ''));
            $client_add_row($client_rows_meta, 'Aktualizacja', (string) ($client_profile['updated_at'] ?? ''));

            $client_themed_cards = [
                ['key' => 'identity', 'title' => 'Dane podstawowe', 'icon' => $client_profile_is_company ? '&#127970;' : '&#128100;', 'rows' => $client_rows_identity, 'class' => 'eocrm-prop-detail-card--location'],
                ['key' => 'contact', 'title' => 'Kontakt', 'icon' => '&#128222;', 'rows' => $client_rows_contact, 'class' => 'eocrm-prop-detail-card--features'],
                ['key' => 'meta', 'title' => 'Identyfikacja', 'icon' => '&#128203;', 'rows' => $client_rows_meta, 'class' => 'eocrm-prop-detail-card--export'],
            ];

            $client_transaction_label_map = [
                'SPRZEDAZ' => 'Sprzeda&#380;',
                'KUPNO' => 'Kupno',
                'WYNAJEM' => 'Wynajem',
                'NAJEM' => 'Najem',
            ];

            $client_format_date = static function (string $date_raw): string {
                if ($date_raw === '' || $date_raw === '0000-00-00') return '';
                $ts = strtotime($date_raw);
                return $ts !== false ? (string) date_i18n('d.m.Y', $ts) : '';
            };
            ?>

            <section class="eocrm-prop-profile" data-eocrm-client-profile>

                <header class="eocrm-prop-profile-hero eocrm-client-hero">
                    <div class="eocrm-client-hero__identity">
                        <span class="eocrm-client-hero__avatar <?php echo $client_profile_is_company ? 'is-company' : 'is-individual'; ?>" aria-hidden="true">
                            <?php if ($client_profile_is_company) : ?>
                                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9h.01"></path><path d="M9 12h.01"></path><path d="M9 15h.01"></path><path d="M9 18h.01"></path></svg>
                            <?php else : ?>
                                <span class="eocrm-client-hero__initial"><?php echo esc_html($client_profile_initial); ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="eocrm-prop-profile-hero__main">
                            <span class="eocrm-prop-profile-hero__eyebrow">Profil klienta</span>
                            <h2 class="eocrm-prop-profile-hero__title">
                                <?php echo esc_html($client_profile_display_name); ?>
                                <span class="eocrm-prop-status-pill <?php echo $client_profile_is_company ? 'is-company' : 'is-active'; ?>">
                                    <?php echo $client_profile_is_company ? 'Firma' : 'Osoba fizyczna'; ?>
                                </span>
                            </h2>
                            <p class="eocrm-prop-profile-hero__meta">
                                <?php if ($client_profile_phone !== '') : ?>
                                    <a href="tel:<?php echo esc_attr($client_profile_phone); ?>">&#9742; <?php echo esc_html($client_profile_phone); ?></a>
                                <?php endif; ?>
                                <?php if ($client_profile_phone !== '' && $client_profile_email !== '') : ?>
                                    <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                <?php endif; ?>
                                <?php if ($client_profile_email !== '') : ?>
                                    <a href="mailto:<?php echo esc_attr($client_profile_email); ?>">&#9993; <?php echo esc_html($client_profile_email); ?></a>
                                <?php endif; ?>
                                <?php if ($client_main_address_str !== '') : ?>
                                    <span class="eocrm-prop-profile-hero__sep">&middot;</span>
                                    <?php echo esc_html($client_main_address_str); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="eocrm-prop-profile-hero__actions">
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'clients'], $current_url)); ?>">&larr; Powr&#243;t</a>
                        <a class="eocrm-btn" href="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'client_id' => $client_profile_id, 'mode' => 'edit-client'], $current_url)); ?>">Edytuj</a>
                        <?php if ($is_admin_user) : ?>
                            <form method="post" action="<?php echo esc_url(add_query_arg(['crm' => 'clients', 'client_id' => $client_profile_id], $current_url)); ?>" onsubmit="return confirm('Czy na pewno usunac klienta?');" class="eocrm-prop-profile-hero__delete">
                                <?php wp_nonce_field('eocrm_delete_client', 'eocrm_client_delete_nonce'); ?>
                                <input type="hidden" name="eocrm_action" value="delete_client">
                                <input type="hidden" name="client_id" value="<?php echo (int) $client_profile_id; ?>">
                                <button class="eocrm-btn eocrm-btn-danger" type="submit">Usu&#324;</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="eocrm-prop-quick-stats">
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--blue">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128196;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Umowy</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo esc_html((string) $client_agreements_count); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">
                                <?php echo esc_html((string) $client_active_agreements_count); ?> aktywne
                            </span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--green">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#127968;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Nieruchomo&#347;ci</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo esc_html((string) $client_properties_count); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">Powi&#261;zane oferty</span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--purple">
                        <span class="eocrm-prop-quick-stat__icon" aria-hidden="true">&#128269;</span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Poszukiwania</span>
                            <span class="eocrm-prop-quick-stat__value"><?php echo esc_html((string) $client_searches_count); ?></span>
                            <span class="eocrm-prop-quick-stat__sub">Aktywne zapytania</span>
                        </div>
                    </div>
                    <div class="eocrm-prop-quick-stat eocrm-prop-quick-stat--orange">
                        <span class="eocrm-prop-quick-stat__avatar" aria-hidden="true">
                            <?php if ($client_owner_avatar !== '') : ?>
                                <img src="<?php echo esc_url($client_owner_avatar); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="eocrm-prop-quick-stat__avatar-initial"><?php echo esc_html($client_owner_initial !== '' ? $client_owner_initial : '?'); ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="eocrm-prop-quick-stat__body">
                            <span class="eocrm-prop-quick-stat__label">Opiekun</span>
                            <span class="eocrm-prop-quick-stat__value eocrm-prop-quick-stat__value--small"><?php echo esc_html($client_owner_name !== '' ? $client_owner_name : '-'); ?></span>
                            <?php if ($client_owner instanceof WP_User && $client_owner->user_email !== '') : ?>
                                <span class="eocrm-prop-quick-stat__sub">
                                    <a href="mailto:<?php echo esc_attr($client_owner->user_email); ?>"><?php echo esc_html($client_owner->user_email); ?></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <section class="eocrm-prop-detail" data-eocrm-client-detail>
                    <div class="eocrm-prop-detail-grid">
                        <?php foreach ($client_themed_cards as $themed_card_c) :
                            $card_rows_c = is_array($themed_card_c['rows'] ?? null) ? $themed_card_c['rows'] : [];
                            if (empty($card_rows_c)) continue;
                        ?>
                            <article class="eocrm-prop-detail-card <?php echo esc_attr((string) $themed_card_c['class']); ?>">
                                <header class="eocrm-prop-detail-card__head">
                                    <span class="eocrm-prop-detail-card__icon" aria-hidden="true"><?php echo wp_kses_post((string) $themed_card_c['icon']); ?></span>
                                    <h3><?php echo wp_kses_post((string) $themed_card_c['title']); ?></h3>
                                </header>
                                <dl class="eocrm-prop-detail-card__defs">
                                    <?php foreach ($card_rows_c as $card_row_item_c) : ?>
                                        <div>
                                            <dt><?php echo esc_html((string) ($card_row_item_c['label'] ?? '')); ?></dt>
                                            <dd><?php echo wp_kses_post((string) ($card_row_item_c['value'] ?? '')); ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($client_main_address_str !== '' || $client_corr_address_str !== '') : ?>
                        <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full eocrm-prop-detail-card--location">
                            <header class="eocrm-prop-detail-card__head">
                                <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128205;</span>
                                <h3>Adresy klienta</h3>
                            </header>
                            <div class="eocrm-client-address-grid">
                                <div class="eocrm-client-address-card">
                                    <span class="eocrm-client-address-card__label">Adres g&#322;&oacute;wny</span>
                                    <p class="eocrm-client-address-card__value">
                                        <?php echo esc_html($client_main_address_str !== '' ? $client_main_address_str : 'Brak danych'); ?>
                                    </p>
                                </div>
                                <div class="eocrm-client-address-card">
                                    <span class="eocrm-client-address-card__label">Adres korespondencyjny</span>
                                    <p class="eocrm-client-address-card__value">
                                        <?php echo esc_html($client_corr_address_str !== '' ? $client_corr_address_str : 'Taki sam jak adres g&#322;&oacute;wny'); ?>
                                    </p>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <article class="eocrm-prop-detail-card eocrm-prop-detail-card--full">
                        <header class="eocrm-prop-detail-card__head">
                            <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128196;</span>
                            <h3>Powi&#261;zane umowy</h3>
                            <p class="eocrm-prop-detail-card__hint"><?php echo esc_html((string) $client_agreements_count); ?> um&oacute;w klienta.</p>
                        </header>
                        <?php if (empty($client_profile_agreements)) : ?>
                            <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych um&oacute;w.</p>
                        <?php else : ?>
                            <div class="eocrm-client-agreement-grid">
                                <?php foreach ($client_profile_agreements as $agreement_row_c) :
                                    $cag_id = (int) ($agreement_row_c['id'] ?? 0);
                                    $cag_number = (string) ($agreement_row_c['agreement_number'] ?? '');
                                    $cag_tx = strtoupper((string) ($agreement_row_c['transaction_type'] ?? ''));
                                    $cag_tx_label = $client_transaction_label_map[$cag_tx] ?? ($cag_tx !== '' ? ucfirst(strtolower($cag_tx)) : '');
                                    $cag_stage = (string) ($agreement_row_c['current_stage'] ?? '');
                                    $cag_finished = $is_agreement_stage_finished($cag_stage);
                                    $cag_date_signed = $client_format_date((string) ($agreement_row_c['date_signed'] ?? ''));
                                    $cag_url = add_query_arg(['crm' => 'agreements', 'agreement_id' => $cag_id], $current_url);
                                ?>
                                    <a class="eocrm-client-agreement-card" href="<?php echo esc_url($cag_url); ?>">
                                        <span class="eocrm-client-agreement-card__icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg>
                                        </span>
                                        <div class="eocrm-client-agreement-card__body">
                                            <div class="eocrm-client-agreement-card__head">
                                                <span class="eocrm-client-agreement-card__number"><?php echo esc_html($cag_number !== '' ? $cag_number : ('Umowa #' . $cag_id)); ?></span>
                                                <?php if ($cag_tx_label !== '') : ?>
                                                    <span class="eocrm-client-agreement-card__tx"><?php echo wp_kses_post($cag_tx_label); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="eocrm-prop-stage-pill <?php echo $cag_finished ? 'is-done' : 'is-progress'; ?>">
                                                <?php echo esc_html($cag_stage !== '' ? $cag_stage : ($cag_finished ? 'Zako&#324;czona' : 'W trakcie')); ?>
                                            </span>
                                            <?php if ($cag_date_signed !== '') : ?>
                                                <span class="eocrm-client-agreement-card__date">Zawarta: <?php echo esc_html($cag_date_signed); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <div class="eocrm-prop-detail-related">
                        <article class="eocrm-prop-detail-card">
                            <header class="eocrm-prop-detail-card__head">
                                <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#127968;</span>
                                <h3>Powi&#261;zane nieruchomo&#347;ci</h3>
                            </header>
                            <?php if (empty($client_profile_properties)) : ?>
                                <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych nieruchomo&#347;ci.</p>
                            <?php else : ?>
                                <ul class="eocrm-prop-detail-related-list">
                                    <?php foreach ($client_profile_properties as $property_row_c) :
                                        $cpr_id = (int) ($property_row_c['id'] ?? 0);
                                        $cpr_number = (string) ($property_row_c['offer_number'] ?? '');
                                    ?>
                                        <li>
                                            <a href="<?php echo esc_url(add_query_arg(['crm' => 'properties', 'property_id' => $cpr_id], $current_url)); ?>">
                                                <span class="eocrm-prop-detail-related-list__avatar" style="background:#e8f7ee;color:#16a34a;">&#127968;</span>
                                                <span class="eocrm-prop-detail-related-list__name">
                                                    <?php echo esc_html($cpr_number !== '' ? $cpr_number : ('Oferta #' . $cpr_id)); ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>

                        <article class="eocrm-prop-detail-card">
                            <header class="eocrm-prop-detail-card__head">
                                <span class="eocrm-prop-detail-card__icon" aria-hidden="true">&#128269;</span>
                                <h3>Powi&#261;zane poszukiwania</h3>
                            </header>
                            <?php if (empty($client_profile_searches)) : ?>
                                <p class="eocrm-prop-detail-empty">Brak powi&#261;zanych poszukiwa&#324;.</p>
                            <?php else : ?>
                                <ul class="eocrm-prop-detail-related-list">
                                    <?php foreach ($client_profile_searches as $search_row_c) :
                                        $csr_id = (int) ($search_row_c['id'] ?? 0);
                                        $csr_number = (string) ($search_row_c['search_number'] ?? '');
                                        $csr_loc = (string) ($search_row_c['location_text'] ?? '');
                                    ?>
                                        <li>
                                            <a href="<?php echo esc_url(add_query_arg(['crm' => 'searches', 'search_id' => $csr_id], $current_url)); ?>">
                                                <span class="eocrm-prop-detail-related-list__avatar" style="background:#ece6fe;color:#6c4cf5;">&#128269;</span>
                                                <span class="eocrm-prop-detail-related-list__name">
                                                    <?php echo esc_html($csr_number !== '' ? $csr_number : ('Poszukiwanie #' . $csr_id)); ?>
                                                    <?php if ($csr_loc !== '') : ?>
                                                        <em>&middot; <?php echo esc_html($csr_loc); ?></em>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    </div>

                </section>
            </section>
        <?php endif; ?>

        <?php
        $clients_total_count = is_array($clients_rows) ? count($clients_rows) : 0;
        $clients_company_count = 0;
        $clients_individual_count = 0;
        if (is_array($clients_rows)) {
            foreach ($clients_rows as $client_row_count) {
                if ((string) ($client_row_count['client_type'] ?? '') === 'company') {
                    $clients_company_count++;
                } else {
                    $clients_individual_count++;
                }
            }
        }
        ?>

        <section class="eocrm-prop-toolbar" aria-label="Filtry i wyszukiwanie klient&oacute;w">
            <form method="get" class="eocrm-prop-toolbar__search" role="search">
                <input type="hidden" name="crm" value="clients">
                <span class="eocrm-prop-toolbar__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                </span>
                <input
                    id="eocrm-clients-search"
                    data-live-filter
                    data-target-table="eocrm-clients-table"
                    name="q"
                    type="search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Szukaj po imieniu, firmie, telefonie, e-mailu..."
                    aria-label="Szukaj klient&oacute;w"
                >
                <button class="eocrm-prop-toolbar__search-btn" type="submit">Szukaj</button>
            </form>

            <div class="eocrm-prop-filters" data-eocrm-client-filter-group data-target-table="eocrm-clients-table" aria-label="Filtry">
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-client-filter="type" value="individual">
                    <span>Os. fizyczna</span>
                </label>
                <label class="eocrm-table-filter-toggle">
                    <input type="checkbox" data-eocrm-client-filter="type" value="company">
                    <span>Firma</span>
                </label>
            </div>
        </section>

        <div class="eocrm-prop-meta">
            <p class="eocrm-prop-meta__count">
                <strong><?php echo esc_html((string) $clients_total_count); ?></strong>
                <span><?php
                    if ($clients_total_count === 1) {
                        echo 'klient';
                    } elseif ($clients_total_count >= 2 && $clients_total_count <= 4) {
                        echo 'klient&oacute;w';
                    } else {
                        echo 'klient&oacute;w';
                    }
                ?></span>
                <?php if ($clients_total_count > 0) : ?>
                    <em class="eocrm-prop-meta__hint">
                        <?php echo esc_html((string) $clients_individual_count); ?> os. fizycznych
                        &middot;
                        <?php echo esc_html((string) $clients_company_count); ?> firm
                    </em>
                <?php endif; ?>
                <?php if ($search !== '') : ?>
                    <em class="eocrm-prop-meta__hint">dla zapytania &laquo;<?php echo esc_html($search); ?>&raquo;</em>
                <?php endif; ?>
            </p>
        </div>

        <div class="eocrm-prop-list-wrap">
            <table class="eocrm-table eocrm-prop-list eocrm-client-list" id="eocrm-clients-table">
                <thead>
                    <tr>
                        <th class="eocrm-prop-col-main">Klient</th>
                        <th class="eocrm-prop-col-loc">Adres</th>
                        <th class="eocrm-prop-col-loc">Kontakt</th>
                        <th class="eocrm-prop-col-metric">Umowy</th>
                        <th class="eocrm-prop-col-metric">Nieruch.</th>
                        <th class="eocrm-prop-col-metric">Poszuk.</th>
                        <?php if ($show_owner_columns) : ?>
                            <th class="eocrm-prop-col-owner">Opiekun</th>
                        <?php endif; ?>
                        <th class="eocrm-prop-col-status">Typ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients_rows)) : ?>
                        <tr class="eocrm-prop-empty-row"><td colspan="<?php echo esc_attr((string) ($show_owner_columns ? 8 : 7)); ?>">
                            <div class="eocrm-prop-empty">
                                <span class="eocrm-prop-empty__icon" aria-hidden="true">&#128101;</span>
                                <strong>Brak klient&oacute;w do wy&#347;wietlenia</strong>
                                <span>Spr&oacute;buj zmieni&#263; filtry lub doda&#263; nowego klienta.</span>
                            </div>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ($clients_rows as $row) :
                            $client_row_id = (int) ($row['id'] ?? 0);
                            $client_row_type = (string) ($row['client_type'] ?? 'individual');
                            $client_is_company = $client_row_type === 'company';
                            $client_row_name = (string) ($row['display_name'] ?? '');
                            if ($client_row_name === '') {
                                $client_row_name = $client_is_company
                                    ? (string) ($row['company_name'] ?? 'Firma')
                                    : trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                            }
                            if ($client_row_name === '') {
                                $client_row_name = 'Klient #' . $client_row_id;
                            }
                            $client_row_initial = mb_strtoupper(mb_substr($client_row_name, 0, 1));

                            $client_row_address = $format_address([
                                trim((string) ($row['address_street'] ?? '') . ' ' . (string) ($row['address_building_no'] ?? '')),
                                (string) ($row['address_city'] ?? ''),
                            ]);
                            $client_row_postal = (string) ($row['address_postal_code'] ?? '');

                            $client_row_phone = trim((string) ($row['phone'] ?? ''));
                            $client_row_email = trim((string) ($row['email'] ?? ''));

                            $client_row_agreements = (int) ($row['agreements_count'] ?? 0);
                            $client_row_properties = (int) ($row['properties_count'] ?? 0);
                            $client_row_searches = (int) ($row['searches_count'] ?? 0);

                            $client_row_owner_data = get_userdata((int) ($row['owner_user_id'] ?? 0));
                            $client_row_owner_name = $client_row_owner_data instanceof WP_User ? (string) $client_row_owner_data->display_name : '-';
                            $client_row_owner_avatar = $client_row_owner_data instanceof WP_User ? $resolve_owner_photo_url((int) $client_row_owner_data->ID, 'thumbnail') : '';
                            $client_row_owner_initial = $client_row_owner_name !== '' && $client_row_owner_name !== '-' ? mb_strtoupper(mb_substr($client_row_owner_name, 0, 1)) : '?';

                            $client_row_url = add_query_arg(['crm' => 'clients', 'client_id' => $client_row_id], $current_url);
                            $client_row_subtitle = $client_is_company ? 'Firma' : 'Osoba fizyczna';
                        ?>
                            <tr
                                class="eocrm-prop-row"
                                data-eocrm-client-row
                                data-client-type="<?php echo esc_attr($client_is_company ? 'company' : 'individual'); ?>"
                            >
                                <td class="eocrm-prop-cell-main">
                                    <a class="eocrm-prop-link" href="<?php echo esc_url($client_row_url); ?>">
                                        <span class="eocrm-prop-thumb eocrm-prop-thumb--client <?php echo $client_is_company ? 'is-company' : 'is-individual'; ?>" aria-hidden="true">
                                            <?php if ($client_is_company) : ?>
                                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9h.01"></path><path d="M9 12h.01"></path><path d="M9 15h.01"></path><path d="M9 18h.01"></path></svg>
                                            <?php else : ?>
                                                <span class="eocrm-prop-thumb__initial"><?php echo esc_html($client_row_initial); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="eocrm-prop-titlewrap">
                                            <span class="eocrm-prop-title"><?php echo esc_html($client_row_name); ?></span>
                                            <span class="eocrm-prop-subtitle"><?php echo esc_html($client_row_subtitle); ?></span>
                                        </span>
                                    </a>
                                </td>
                                <td class="eocrm-prop-cell-loc">
                                    <?php if ($client_row_address !== '') : ?>
                                        <span class="eocrm-prop-loc-primary"><?php echo esc_html($client_row_address); ?></span>
                                        <?php if ($client_row_postal !== '') : ?>
                                            <span class="eocrm-prop-loc-secondary"><?php echo esc_html($client_row_postal); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="eocrm-prop-loc-primary eocrm-prop-loc-primary--muted">Brak adresu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-loc">
                                    <?php if ($client_row_phone !== '') : ?>
                                        <a class="eocrm-prop-loc-primary eocrm-prop-loc-primary--link" href="tel:<?php echo esc_attr($client_row_phone); ?>">&#9742; <?php echo esc_html($client_row_phone); ?></a>
                                    <?php endif; ?>
                                    <?php if ($client_row_email !== '') : ?>
                                        <a class="eocrm-prop-loc-secondary eocrm-prop-loc-primary--link" href="mailto:<?php echo esc_attr($client_row_email); ?>">&#9993; <?php echo esc_html($client_row_email); ?></a>
                                    <?php endif; ?>
                                    <?php if ($client_row_phone === '' && $client_row_email === '') : ?>
                                        <span class="eocrm-prop-loc-primary eocrm-prop-loc-primary--muted">Brak kontaktu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <span class="eocrm-prop-metric <?php echo $client_row_agreements > 0 ? '' : 'eocrm-prop-metric--empty'; ?>">
                                        <?php echo esc_html((string) $client_row_agreements); ?>
                                    </span>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <span class="eocrm-prop-metric <?php echo $client_row_properties > 0 ? '' : 'eocrm-prop-metric--empty'; ?>">
                                        <?php echo esc_html((string) $client_row_properties); ?>
                                    </span>
                                </td>
                                <td class="eocrm-prop-cell-metric">
                                    <span class="eocrm-prop-metric <?php echo $client_row_searches > 0 ? '' : 'eocrm-prop-metric--empty'; ?>">
                                        <?php echo esc_html((string) $client_row_searches); ?>
                                    </span>
                                </td>
                                <?php if ($show_owner_columns) : ?>
                                    <td class="eocrm-prop-cell-owner">
                                        <span class="eocrm-prop-owner">
                                            <span class="eocrm-prop-owner__avatar" aria-hidden="true">
                                                <?php if (! empty($client_row_owner_avatar)) : ?>
                                                    <img src="<?php echo esc_url((string) $client_row_owner_avatar); ?>" alt="" loading="lazy">
                                                <?php else : ?>
                                                    <span class="eocrm-prop-owner__initial"><?php echo esc_html($client_row_owner_initial); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="eocrm-prop-owner__name"><?php echo esc_html($client_row_owner_name); ?></span>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="eocrm-prop-cell-status">
                                    <span class="eocrm-prop-client-type-pill <?php echo $client_is_company ? 'is-company' : 'is-individual'; ?>">
                                        <?php echo $client_is_company ? 'Firma' : 'Os. fizyczna'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'offices') : ?>
        <?php
        $frontend_offices_rows = isset($frontend_offices_rows) && is_array($frontend_offices_rows) ? $frontend_offices_rows : [];
        $frontend_office_edit = isset($frontend_office_edit) && is_array($frontend_office_edit) ? $frontend_office_edit : null;
        $office_form_is_edit = is_array($frontend_office_edit);
        $office_form = $office_form_is_edit ? $frontend_office_edit : [];
        $office_form_action = add_query_arg('crm', 'offices', $current_url);
        $office_logo_id = isset($office_form['logo_id']) ? absint((string) $office_form['logo_id']) : 0;
        $office_logo_url = $office_logo_id > 0 ? wp_get_attachment_image_url($office_logo_id, 'medium') : '';
        ?>
        <section class="eocrm-team-admin">
            <div class="eocrm-team-admin-hero">
                <div>
                    <span class="eocrm-team-admin-eyebrow">Administracja CRM</span>
                    <h3>Biura</h3>
                    <p>Zarzadzaj oddzialami, widocznoscia publiczna i kolejnoscia wyswietlania biur.</p>
                </div>
                <a class="eocrm-btn eocrm-btn-secondary" href="<?php echo esc_url(add_query_arg('crm', 'agents', $current_url)); ?>">Przejdz do agentow</a>
            </div>

            <?php if ($crm_notice === 'office_created' || $crm_notice === 'office_updated') : ?>
                <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Biuro zostalo zapisane.'); ?></div>
            <?php elseif ($crm_notice === 'office_error') : ?>
                <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac biura.'); ?></div>
            <?php endif; ?>

            <div class="eocrm-team-admin-layout">
                <div class="eocrm-team-admin-list">
                    <?php if (empty($frontend_offices_rows)) : ?>
                        <div class="eocrm-empty">Brak biur do wyswietlenia.</div>
                    <?php else : ?>
                        <?php foreach ($frontend_offices_rows as $office_row) : ?>
                            <?php
                            $office_id = (int) ($office_row['id'] ?? 0);
                            $office_name = (string) ($office_row['office_name'] ?? '');
                            $office_logo = (string) ($office_row['logo_url'] ?? '');
                            $office_city = (string) ($office_row['city'] ?? '');
                            $office_address = $format_address([
                                (string) ($office_row['address_line'] ?? ''),
                                $office_city,
                                (string) ($office_row['postal_code'] ?? ''),
                            ]);
                            ?>
                            <article class="eocrm-team-admin-card">
                                <div class="eocrm-team-admin-card__media eocrm-team-admin-card__media--office">
                                    <?php if ($office_logo !== '') : ?>
                                        <img src="<?php echo esc_url($office_logo); ?>" alt="<?php echo esc_attr($office_name); ?>" loading="lazy">
                                    <?php else : ?>
                                        <span><?php echo esc_html(mb_substr($office_name !== '' ? $office_name : 'B', 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="eocrm-team-admin-card__body">
                                    <div class="eocrm-team-admin-card__top">
                                        <div>
                                            <h4><?php echo esc_html($office_name !== '' ? $office_name : 'Biuro #' . $office_id); ?></h4>
                                            <?php if ($office_address !== '') : ?>
                                                <p><?php echo esc_html($office_address); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <a class="eocrm-team-admin-card__edit" href="<?php echo esc_url(add_query_arg(['crm' => 'offices', 'office_id' => $office_id], $current_url)); ?>">Edytuj</a>
                                    </div>
                                    <div class="eocrm-team-admin-meta">
                                        <span><?php echo esc_html((string) ($office_row['office_email'] ?? 'Brak e-mail')); ?></span>
                                        <span><?php echo esc_html((string) ($office_row['office_phone'] ?? 'Brak telefonu')); ?></span>
                                        <span><?php echo esc_html('Agenci: ' . (string) ((int) ($office_row['agent_count'] ?? 0))); ?></span>
                                    </div>
                                    <div class="eocrm-team-admin-pills">
                                        <span class="eocrm-team-admin-pill <?php echo ((int) ($office_row['is_active'] ?? 0) === 1) ? 'is-ok' : 'is-muted'; ?>"><?php echo ((int) ($office_row['is_active'] ?? 0) === 1) ? 'Aktywne' : 'Nieaktywne'; ?></span>
                                        <span class="eocrm-team-admin-pill <?php echo ((int) ($office_row['is_public'] ?? 0) === 1) ? 'is-ok' : 'is-muted'; ?>"><?php echo ((int) ($office_row['is_public'] ?? 0) === 1) ? 'Publiczne' : 'Ukryte'; ?></span>
                                        <span class="eocrm-team-admin-pill">Kolejnosc: <?php echo esc_html((string) ((int) ($office_row['display_order'] ?? 100))); ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <aside class="eocrm-team-admin-panel">
                    <h3><?php echo $office_form_is_edit ? 'Edytuj biuro' : 'Dodaj biuro'; ?></h3>
                    <form method="post" action="<?php echo esc_url($office_form_action); ?>" class="eocrm-team-admin-form">
                        <?php wp_nonce_field($office_form_is_edit ? 'eocrm_update_office' : 'eocrm_create_office'); ?>
                        <input type="hidden" name="eocrm_action" value="<?php echo esc_attr($office_form_is_edit ? 'update_office' : 'create_office'); ?>">
                        <?php if ($office_form_is_edit) : ?>
                            <input type="hidden" name="office_id" value="<?php echo esc_attr((string) ((int) ($office_form['id'] ?? 0))); ?>">
                        <?php endif; ?>
                        <label>Nazwa biura<input type="text" name="office_name" value="<?php echo esc_attr((string) ($office_form['office_name'] ?? '')); ?>" required></label>
                        <div class="eocrm-team-admin-form__grid">
                            <label>E-mail<input type="email" name="office_email" value="<?php echo esc_attr((string) ($office_form['office_email'] ?? '')); ?>"></label>
                            <label>Telefon<input type="text" name="office_phone" value="<?php echo esc_attr((string) ($office_form['office_phone'] ?? '')); ?>"></label>
                        </div>
                        <label>Adres<input type="text" name="office_address_line" value="<?php echo esc_attr((string) ($office_form['address_line'] ?? '')); ?>"></label>
                        <div class="eocrm-team-admin-form__grid">
                            <label>Miasto<input type="text" name="office_city" value="<?php echo esc_attr((string) ($office_form['city'] ?? '')); ?>"></label>
                            <label>Kod pocztowy<input type="text" name="office_postal_code" value="<?php echo esc_attr((string) ($office_form['postal_code'] ?? '')); ?>"></label>
                        </div>
                        <label>Opis biura<textarea name="office_description" rows="4"><?php echo esc_textarea((string) ($office_form['description'] ?? '')); ?></textarea></label>
                        <div class="eocrm-team-admin-form__grid">
                            <label>Kolejnosc<input type="number" min="0" max="9999" name="office_display_order" value="<?php echo esc_attr((string) ((int) ($office_form['display_order'] ?? 100))); ?>"></label>
                            <label>Logo biura ID<input type="number" min="0" id="eocrm-frontend-office-logo-id" name="office_logo_id" value="<?php echo esc_attr((string) $office_logo_id); ?>"></label>
                        </div>
                        <button type="button" class="eocrm-btn eocrm-btn-secondary eocrm-team-media-button" data-eocrm-crm-media="eocrm-frontend-office-logo-id" data-eocrm-preview="eocrm-frontend-office-logo-preview">Wybierz logo</button>
                        <div class="eocrm-team-admin-preview" id="eocrm-frontend-office-logo-preview">
                            <?php if (is_string($office_logo_url) && $office_logo_url !== '') : ?>
                                <img src="<?php echo esc_url($office_logo_url); ?>" alt="Logo biura">
                            <?php endif; ?>
                        </div>
                        <div class="eocrm-team-admin-switches">
                            <?php if ($office_form_is_edit) : ?>
                                <label class="eocrm-team-toggle"><input type="checkbox" name="office_is_active" value="1" <?php checked((int) ($office_form['is_active'] ?? 0), 1); ?>><span>Biuro aktywne</span></label>
                            <?php endif; ?>
                            <label class="eocrm-team-toggle"><input type="checkbox" name="office_is_public" value="1" <?php checked((int) ($office_form['is_public'] ?? 1), 1); ?>><span>Pokaz publicznie</span></label>
                        </div>
                        <div class="eocrm-team-admin-actions">
                            <button type="submit" class="eocrm-btn eocrm-btn-primary"><?php echo $office_form_is_edit ? 'Zapisz biuro' : 'Dodaj biuro'; ?></button>
                            <?php if ($office_form_is_edit) : ?>
                                <a class="eocrm-btn eocrm-btn-secondary" href="<?php echo esc_url(add_query_arg('crm', 'offices', $current_url)); ?>">Anuluj edycje</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </aside>
            </div>
        </section>
    <?php elseif ($section === 'agents') : ?>
        <?php
        $frontend_agents_rows = isset($frontend_agents_rows) && is_array($frontend_agents_rows) ? $frontend_agents_rows : [];
        $frontend_office_options = isset($frontend_office_options) && is_array($frontend_office_options) ? $frontend_office_options : [];
        $frontend_agent_edit = isset($frontend_agent_edit) && is_array($frontend_agent_edit) ? $frontend_agent_edit : null;
        $agent_form_is_edit = is_array($frontend_agent_edit);
        $agent_form = $agent_form_is_edit ? $frontend_agent_edit : [];
        $agent_form_action = add_query_arg('crm', 'agents', $current_url);
        $agent_photo_id = isset($agent_form['photo_id']) ? absint((string) $agent_form['photo_id']) : 0;
        $agent_photo_url = $agent_photo_id > 0 ? wp_get_attachment_image_url($agent_photo_id, 'medium') : '';
        $manager_office_label = '';
        foreach ($frontend_office_options as $office_option_for_label) {
            if ((int) ($office_option_for_label['id'] ?? 0) === (int) ($agent_form['office_id'] ?? 0)) {
                $manager_office_label = (string) ($office_option_for_label['office_name'] ?? '');
            }
        }
        if ($manager_office_label === '' && ! current_user_can('manage_options') && ! empty($frontend_office_options)) {
            $manager_office_label = (string) ($frontend_office_options[0]['office_name'] ?? '');
        }
        ?>
        <section class="eocrm-team-admin">
            <div class="eocrm-team-admin-hero">
                <div>
                    <span class="eocrm-team-admin-eyebrow"><?php echo current_user_can('manage_options') ? 'Administracja CRM' : 'Panel Menedzera'; ?></span>
                    <h3>Agenci</h3>
                    <p><?php echo current_user_can('manage_options') ? 'Zarzadzaj agentami i menedzerami we wszystkich biurach.' : 'Zarzadzaj agentami przypisanymi do Twojego biura.'; ?></p>
                </div>
                <?php if (current_user_can('manage_options')) : ?>
                    <a class="eocrm-btn eocrm-btn-secondary" href="<?php echo esc_url(add_query_arg('crm', 'offices', $current_url)); ?>">Zarzadzaj biurami</a>
                <?php endif; ?>
            </div>

            <?php if ($crm_notice === 'agent_created' || $crm_notice === 'agent_updated') : ?>
                <div class="eocrm-alert eocrm-alert-success"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Agent zostal zapisany.'); ?></div>
            <?php elseif ($crm_notice === 'agent_error') : ?>
                <div class="eocrm-alert eocrm-alert-error"><?php echo esc_html($crm_message !== '' ? $crm_message : 'Nie udalo sie zapisac agenta.'); ?></div>
            <?php endif; ?>

            <?php if (! current_user_can('manage_options') && empty($frontend_office_options)) : ?>
                <div class="eocrm-alert eocrm-alert-error">Twoje konto Menedzera nie jest przypisane do biura. Popros administratora o przypisanie biura, aby zarzadzac agentami.</div>
            <?php endif; ?>

            <div class="eocrm-team-admin-layout">
                <div class="eocrm-team-admin-list">
                    <?php if (empty($frontend_agents_rows)) : ?>
                        <div class="eocrm-empty">Brak agentow do wyswietlenia.</div>
                    <?php else : ?>
                        <?php foreach ($frontend_agents_rows as $agent_row) : ?>
                            <?php
                            $agent_id = (int) ($agent_row['id'] ?? 0);
                            $agent_name = (string) ($agent_row['display_name'] ?? '');
                            $agent_photo = (string) ($agent_row['photo_url'] ?? '');
                            $agent_initial = mb_strtoupper(mb_substr($agent_name !== '' ? $agent_name : 'A', 0, 1));
                            ?>
                            <article class="eocrm-team-admin-card eocrm-team-admin-card--agent">
                                <div class="eocrm-team-admin-card__media eocrm-team-admin-card__media--agent">
                                    <?php if ($agent_photo !== '') : ?>
                                        <img src="<?php echo esc_url($agent_photo); ?>" alt="<?php echo esc_attr($agent_name); ?>" loading="lazy">
                                    <?php else : ?>
                                        <span><?php echo esc_html($agent_initial); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="eocrm-team-admin-card__body">
                                    <div class="eocrm-team-admin-card__top">
                                        <div>
                                            <h4><?php echo esc_html($agent_name !== '' ? $agent_name : 'Agent #' . $agent_id); ?></h4>
                                            <p><?php echo esc_html((string) ($agent_row['role_label'] ?? 'Agent')); ?><?php echo (string) ($agent_row['office_name'] ?? '') !== '' ? ' / ' . esc_html((string) ($agent_row['office_name'] ?? '')) : ''; ?></p>
                                        </div>
                                        <?php if (! empty($agent_row['can_edit'])) : ?>
                                            <a class="eocrm-team-admin-card__edit" href="<?php echo esc_url(add_query_arg(['crm' => 'agents', 'agent_user_id' => $agent_id], $current_url)); ?>">Edytuj</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="eocrm-team-admin-meta">
                                        <span><?php echo esc_html((string) ($agent_row['user_email'] ?? 'Brak e-mail')); ?></span>
                                        <span><?php echo esc_html((string) ($agent_row['phone'] ?? 'Brak telefonu')); ?></span>
                                        <span><?php echo esc_html((string) ($agent_row['city'] ?? 'Brak miasta')); ?></span>
                                    </div>
                                    <div class="eocrm-team-admin-pills">
                                        <span class="eocrm-team-admin-pill <?php echo ((int) ($agent_row['is_public'] ?? 0) === 1) ? 'is-ok' : 'is-muted'; ?>"><?php echo ((int) ($agent_row['is_public'] ?? 0) === 1) ? 'Publiczny' : 'Ukryty'; ?></span>
                                        <span class="eocrm-team-admin-pill">Kolejnosc: <?php echo esc_html((string) ((int) ($agent_row['display_order'] ?? 100))); ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <aside class="eocrm-team-admin-panel">
                    <h3><?php echo $agent_form_is_edit ? 'Edytuj agenta' : 'Dodaj agenta'; ?></h3>
                    <form method="post" action="<?php echo esc_url($agent_form_action); ?>" class="eocrm-team-admin-form">
                        <?php wp_nonce_field($agent_form_is_edit ? 'eocrm_update_agent' : 'eocrm_create_agent'); ?>
                        <input type="hidden" name="eocrm_action" value="<?php echo esc_attr($agent_form_is_edit ? 'update_agent' : 'create_agent'); ?>">
                        <?php if ($agent_form_is_edit) : ?>
                            <input type="hidden" name="agent_id" value="<?php echo esc_attr((string) ((int) ($agent_form['id'] ?? 0))); ?>">
                            <p class="eocrm-team-admin-static"><strong>Login:</strong> <?php echo esc_html((string) ($agent_form['user_login'] ?? '')); ?></p>
                        <?php else : ?>
                            <label>Login<input type="text" name="agent_username" required></label>
                            <label>Haslo <small>(opcjonalnie)</small><input type="text" name="agent_password"></label>
                        <?php endif; ?>
                        <label>Imie i nazwisko<input type="text" name="agent_display_name" value="<?php echo esc_attr((string) ($agent_form['display_name'] ?? '')); ?>" required></label>
                        <label>E-mail<input type="email" name="agent_email" value="<?php echo esc_attr((string) ($agent_form['user_email'] ?? '')); ?>" required></label>
                        <div class="eocrm-team-admin-form__grid">
                            <label>Telefon<input type="text" name="agent_phone" value="<?php echo esc_attr((string) ($agent_form['phone'] ?? '')); ?>"></label>
                            <label>Miasto<input type="text" name="agent_city" value="<?php echo esc_attr((string) ($agent_form['city'] ?? '')); ?>"></label>
                        </div>
                        <div class="eocrm-team-admin-form__grid">
                            <label>Adres<input type="text" name="agent_address_line" value="<?php echo esc_attr((string) ($agent_form['address_line'] ?? '')); ?>"></label>
                            <label>Kod pocztowy<input type="text" name="agent_postal_code" value="<?php echo esc_attr((string) ($agent_form['postal_code'] ?? '')); ?>"></label>
                        </div>
                        <?php if (current_user_can('manage_options')) : ?>
                            <div class="eocrm-team-admin-form__grid">
                                <label>Rola CRM
                                    <select name="agent_role">
                                        <option value="<?php echo esc_attr(EstateOfficeCRM_Installer::ROLE_AGENT); ?>" <?php selected((string) ($agent_form['crm_role'] ?? EstateOfficeCRM_Installer::ROLE_AGENT), EstateOfficeCRM_Installer::ROLE_AGENT); ?>>Agent</option>
                                        <option value="<?php echo esc_attr(EstateOfficeCRM_Installer::ROLE_MANAGER); ?>" <?php selected((string) ($agent_form['crm_role'] ?? ''), EstateOfficeCRM_Installer::ROLE_MANAGER); ?>>Menedzer</option>
                                    </select>
                                </label>
                                <label>Biuro
                                    <select name="agent_office_id">
                                        <option value="0">Brak przypisania</option>
                                        <?php foreach ($frontend_office_options as $office_option) : ?>
                                            <?php $office_option_id = (int) ($office_option['id'] ?? 0); ?>
                                            <option value="<?php echo esc_attr((string) $office_option_id); ?>" <?php selected((int) ($agent_form['office_id'] ?? 0), $office_option_id); ?>><?php echo esc_html((string) ($office_option['office_name'] ?? 'Biuro #' . $office_option_id)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                        <?php else : ?>
                            <p class="eocrm-team-admin-static"><strong>Biuro:</strong> <?php echo esc_html($manager_office_label !== '' ? $manager_office_label : 'Twoje biuro'); ?></p>
                        <?php endif; ?>
                        <div class="eocrm-team-admin-form__grid">
                            <label>Zdjecie ID<input type="number" min="0" id="eocrm-frontend-agent-photo-id" name="agent_photo_id" value="<?php echo esc_attr((string) $agent_photo_id); ?>"></label>
                            <label>Kolejnosc<input type="number" min="0" max="9999" name="agent_display_order" value="<?php echo esc_attr((string) ((int) ($agent_form['display_order'] ?? 100))); ?>"></label>
                        </div>
                        <button type="button" class="eocrm-btn eocrm-btn-secondary eocrm-team-media-button" data-eocrm-crm-media="eocrm-frontend-agent-photo-id" data-eocrm-preview="eocrm-frontend-agent-photo-preview">Wybierz zdjecie</button>
                        <div class="eocrm-team-admin-preview eocrm-team-admin-preview--avatar" id="eocrm-frontend-agent-photo-preview">
                            <?php if (is_string($agent_photo_url) && $agent_photo_url !== '') : ?>
                                <img src="<?php echo esc_url($agent_photo_url); ?>" alt="Zdjecie agenta">
                            <?php endif; ?>
                        </div>
                        <label>Opis / biografia<textarea name="agent_bio" rows="4"><?php echo esc_textarea((string) ($agent_form['bio'] ?? '')); ?></textarea></label>
                        <div class="eocrm-team-admin-switches">
                            <label class="eocrm-team-toggle"><input type="checkbox" name="agent_is_public" value="1" <?php checked((int) ($agent_form['is_public'] ?? 1), 1); ?>><span>Pokaz publicznie</span></label>
                        </div>
                        <div class="eocrm-team-admin-actions">
                            <button type="submit" class="eocrm-btn eocrm-btn-primary"><?php echo $agent_form_is_edit ? 'Zapisz agenta' : 'Dodaj agenta'; ?></button>
                            <?php if ($agent_form_is_edit) : ?>
                                <a class="eocrm-btn eocrm-btn-secondary" href="<?php echo esc_url(add_query_arg('crm', 'agents', $current_url)); ?>">Anuluj edycje</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </aside>
            </div>
        </section>
    <?php endif; ?>

    <footer class="eocrm-crm-footer-actions" aria-label="Akcje konta CRM">
        <a class="eocrm-btn eocrm-btn-secondary eocrm-crm-logout-btn" href="<?php echo esc_url(wp_logout_url($crm_logout_redirect_url)); ?>">Wyloguj</a>
    </footer>
</div>
