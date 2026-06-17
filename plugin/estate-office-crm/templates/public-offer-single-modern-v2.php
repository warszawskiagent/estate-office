<?php
if (! defined('ABSPATH')) {
    exit;
}

$offer_property_type = strtoupper(trim((string) ($offer['property_type'] ?? '')));
$unit_settings = isset($unit_settings) && is_array($unit_settings)
    ? $unit_settings
    : EstateOfficeCRM_Units::default_settings();
$offer_watermark_url = isset($offer_watermark_url) && is_string($offer_watermark_url) ? trim($offer_watermark_url) : '';
$offer_area_unit = EstateOfficeCRM_Units::get_area_unit_for_property($offer_property_type, $unit_settings);
$offer_area_unit_label = EstateOfficeCRM_Units::unit_label($offer_area_unit);
$show_building_no = ! in_array($offer_property_type, ['DOM', 'DZIALKA'], true);
$offer_address = trim((string) ($offer['street'] ?? '') . ($show_building_no && trim((string) ($offer['building_no'] ?? '')) !== '' ? (' ' . (string) ($offer['building_no'] ?? '')) : ''));
$offer_district = (string) ($offer['district'] ?? '');
$offer_location = trim((string) ($offer['city'] ?? '') . ($offer_district !== '' ? ' / ' . $offer_district : ''));
$offer_county = trim((string) ($offer['county'] ?? ''));
$offer_gmina = trim((string) ($offer['gmina'] ?? ''));
$offer_plot_shape = strtolower(trim((string) ($offer['plot_shape'] ?? '')));
if ($offer_plot_shape === 'regularny') {
    $offer_plot_shape = 'prostokat';
} elseif ($offer_plot_shape === 'nieregularny') {
    $offer_plot_shape = 'nieregularna';
}
$offer_plot_shape_labels = [
    'kwadrat' => 'Kwadrat',
    'prostokat' => 'Prostokat',
    'trojkat' => 'Trojkat',
    'nieregularna' => 'Nieregularna',
];
$offer_plot_shape_label = isset($offer_plot_shape_labels[$offer_plot_shape]) ? (string) $offer_plot_shape_labels[$offer_plot_shape] : '-';
$offer_plot_side_a = isset($offer['plot_length']) && is_numeric((string) $offer['plot_length']) ? (float) $offer['plot_length'] : 0.0;
$offer_plot_side_b = isset($offer['plot_width']) && is_numeric((string) $offer['plot_width']) ? (float) $offer['plot_width'] : 0.0;
$offer_plot_dimensions_text = trim((string) ($offer['plot_dimensions_text'] ?? ''));
$offer_plot_side_c = null;
if ($offer_plot_dimensions_text !== '') {
    $offer_plot_dimensions_meta = json_decode($offer_plot_dimensions_text, true);
    if (is_array($offer_plot_dimensions_meta) && isset($offer_plot_dimensions_meta['side_c']) && is_numeric((string) $offer_plot_dimensions_meta['side_c'])) {
        $offer_plot_side_c = (float) $offer_plot_dimensions_meta['side_c'];
    }
}
$offer_plot_dimensions_display = '-';
if ($offer_plot_shape === 'kwadrat' && $offer_plot_side_a > 0) {
    $offer_plot_dimensions_display = 'A: ' . rtrim(rtrim(number_format($offer_plot_side_a, 2, '.', ''), '0'), '.') . ' m';
} elseif ($offer_plot_shape === 'prostokat' && $offer_plot_side_a > 0 && $offer_plot_side_b > 0) {
    $offer_plot_dimensions_display = 'A: ' . rtrim(rtrim(number_format($offer_plot_side_a, 2, '.', ''), '0'), '.') . ' m, B: ' . rtrim(rtrim(number_format($offer_plot_side_b, 2, '.', ''), '0'), '.') . ' m';
} elseif ($offer_plot_shape === 'trojkat' && $offer_plot_side_a > 0 && $offer_plot_side_b > 0 && is_float($offer_plot_side_c) && $offer_plot_side_c > 0) {
    $offer_plot_dimensions_display = 'A: ' . rtrim(rtrim(number_format($offer_plot_side_a, 2, '.', ''), '0'), '.') . ' m, B: ' . rtrim(rtrim(number_format($offer_plot_side_b, 2, '.', ''), '0'), '.') . ' m, C: ' . rtrim(rtrim(number_format($offer_plot_side_c, 2, '.', ''), '0'), '.') . ' m';
} elseif ($offer_plot_shape === 'nieregularna' && $offer_plot_dimensions_text !== '') {
    $offer_plot_dimensions_display = $offer_plot_dimensions_text;
}
$offer_plot_area_value = null;
if ($offer_property_type === 'DOM' && isset($offer['plot_area']) && is_numeric((string) $offer['plot_area'])) {
    $offer_plot_area_value = (float) $offer['plot_area'];
} elseif ($offer_property_type === 'DZIALKA' && isset($offer['area']) && is_numeric((string) $offer['area'])) {
    $offer_plot_area_value = (float) $offer['area'];
}
if ((! is_float($offer_plot_area_value) && ! is_int($offer_plot_area_value)) || (float) $offer_plot_area_value <= 0) {
    $offer_plot_area_from_dimensions = null;
    if ($offer_plot_shape === 'kwadrat' && $offer_plot_side_a > 0) {
        $offer_plot_area_from_dimensions = $offer_plot_side_a * $offer_plot_side_a;
    } elseif ($offer_plot_shape === 'prostokat' && $offer_plot_side_a > 0 && $offer_plot_side_b > 0) {
        $offer_plot_area_from_dimensions = $offer_plot_side_a * $offer_plot_side_b;
    } elseif ($offer_plot_shape === 'trojkat' && $offer_plot_side_a > 0 && $offer_plot_side_b > 0 && is_float($offer_plot_side_c) && $offer_plot_side_c > 0) {
        $triangle_s = ($offer_plot_side_a + $offer_plot_side_b + $offer_plot_side_c) / 2;
        $triangle_area = $triangle_s * ($triangle_s - $offer_plot_side_a) * ($triangle_s - $offer_plot_side_b) * ($triangle_s - $offer_plot_side_c);
        if ($triangle_area > 0) {
            $offer_plot_area_from_dimensions = sqrt($triangle_area);
        }
    }

    if (is_float($offer_plot_area_from_dimensions) && $offer_plot_area_from_dimensions > 0) {
        $offer_plot_area_value = $offer_plot_area_from_dimensions;
    } elseif ($offer_property_type === 'DZIALKA' && isset($offer['area']) && is_numeric((string) $offer['area'])) {
        $offer_plot_area_value = (float) $offer['area'];
    } else {
        $offer_plot_area_value = null;
    }
}
$offer_plot_area_display = EstateOfficeCRM_Units::format_area_value($offer_plot_area_value, $offer_area_unit);
$offer_has_plot_details = in_array($offer_property_type, ['DOM', 'DZIALKA'], true);
$offer_tags = isset($offer['tags']) && is_array($offer['tags']) ? $offer['tags'] : [];
$offer_tag_keys = array_map(static function ($tag): string {
    return strtolower(trim((string) $tag));
}, $offer_tags);
$offer_status_ribbons = [];
$offer_has_active_new_offer = in_array('nowa_oferta', $offer_tag_keys, true) && (! array_key_exists('is_new_offer', $offer) || (int) ($offer['is_new_offer'] ?? 0) === 1);
foreach ([
    'sprzedane' => ['label' => 'SPRZEDANE', 'class' => 'sold', 'side' => 'right'],
    'wynajete' => ['label' => 'WYNAJĘTE', 'class' => 'rented', 'side' => 'right'],
    'nowa_oferta' => ['label' => 'NOWA OFERTA', 'class' => 'new', 'side' => 'right'],
    'oferta_mls' => ['label' => 'MLS', 'class' => 'mls', 'side' => 'left'],
    'premium' => ['label' => 'PREMIUM', 'class' => 'premium', 'side' => 'left'],
] as $ribbon_tag => $ribbon_data) {
    if ($ribbon_tag === 'nowa_oferta' && ! $offer_has_active_new_offer) {
        continue;
    }
    if (in_array($ribbon_tag, $offer_tag_keys, true)) {
        $offer_status_ribbons[] = $ribbon_data;
    }
}
$photos = isset($media['photos']) && is_array($media['photos']) ? $media['photos'] : [];
$floor_plan_rows = isset($media['floor_plans']) && is_array($media['floor_plans']) ? $media['floor_plans'] : [];
$floor_2d = isset($media['floor_2d']) && is_array($media['floor_2d']) ? $media['floor_2d'] : null;
$floor_3d = isset($media['floor_3d']) && is_array($media['floor_3d']) ? $media['floor_3d'] : null;
$offer_media = isset($offer['media']) && is_array($offer['media']) ? $offer['media'] : [];
$virtual_tour_link = isset($offer_media['virtual_tour_link']) ? trim((string) $offer_media['virtual_tour_link']) : '';
$video_link = isset($offer_media['video_link']) ? trim((string) $offer_media['video_link']) : '';
$has_virtual_tour = $virtual_tour_link !== '';
$has_video = $video_link !== '';
$multimedia_items = [];
if ($has_virtual_tour) {
    $multimedia_items[] = [
        'label' => 'Wirtualny spacer',
        'title' => 'Wirtualny spacer',
        'url' => $virtual_tour_link,
    ];
}
if ($has_video) {
    $multimedia_items[] = [
        'label' => 'Film',
        'title' => 'Film oferty',
        'url' => $video_link,
    ];
}
$amenities = isset($offer['amenities']) && is_array($offer['amenities']) ? $offer['amenities'] : [];
$equipment = isset($offer['equipment']) && is_array($offer['equipment']) ? $offer['equipment'] : [];
$offer_custom_fields = isset($offer['custom_fields']) && is_array($offer['custom_fields']) ? $offer['custom_fields'] : [];
$property_additional_fields_public = isset($property_additional_fields_public) && is_array($property_additional_fields_public) ? $property_additional_fields_public : [];
$offer_custom_public_rows = [
    'details' => [],
    'media' => [],
    'amenities' => [],
    'equipment' => [],
];
foreach ($property_additional_fields_public as $custom_definition_row) {
    if (! is_array($custom_definition_row)) {
        continue;
    }

    if (empty($custom_definition_row['show_public'])) {
        continue;
    }

    $custom_key = sanitize_key((string) ($custom_definition_row['key'] ?? ''));
    $custom_label = sanitize_text_field((string) ($custom_definition_row['label'] ?? ''));
    $custom_section = sanitize_key((string) ($custom_definition_row['section'] ?? 'details'));
    $custom_type = sanitize_key((string) ($custom_definition_row['type'] ?? 'text'));
    if ($custom_key === '' || $custom_label === '') {
        continue;
    }
    if (! isset($offer_custom_public_rows[$custom_section])) {
        $custom_section = 'details';
    }
    if (! in_array($custom_type, ['text', 'number', 'checkbox'], true)) {
        $custom_type = 'text';
    }
    if (! array_key_exists($custom_key, $offer_custom_fields)) {
        continue;
    }

    $custom_value = EstateOfficeCRM_Property_Custom_Fields::sanitize_value($offer_custom_fields[$custom_key], $custom_type);
    if (! EstateOfficeCRM_Property_Custom_Fields::has_value($custom_value, $custom_type)) {
        continue;
    }

    $offer_custom_public_rows[$custom_section][] = [
        'label' => $custom_label,
        'value' => EstateOfficeCRM_Property_Custom_Fields::format_value($custom_value, $custom_type),
    ];
}
if (! empty($offer_custom_public_rows['amenities'])) {
    foreach ($offer_custom_public_rows['amenities'] as $custom_amenity_row) {
        $amenities[] = (string) ($custom_amenity_row['label'] ?? '') . ': ' . (string) ($custom_amenity_row['value'] ?? '');
    }
}
if (! empty($offer_custom_public_rows['equipment'])) {
    foreach ($offer_custom_public_rows['equipment'] as $custom_equipment_row) {
        $equipment[] = (string) ($custom_equipment_row['label'] ?? '') . ': ' . (string) ($custom_equipment_row['value'] ?? '');
    }
}
$offer_agent = isset($offer_agent) && is_array($offer_agent) ? $offer_agent : [];
$tag_labels = [
    'nowa_oferta' => 'Nowa Oferta',
    'wylacznosc' => 'Wylacznosc',
    'sprzedane' => 'Sprzedane',
    'wynajete' => 'Wynajete',
    'nowa_cena' => 'Nowa Cena',
    'bez_prowizji' => 'Bez Prowizji',
    'oferta_mls' => 'Oferta MLS',
    'premium' => 'Premium',
];

$resolve_media_url = static function (array $media_row, string $size = 'large'): string {
    $attachment_id = isset($media_row['attachment_id']) ? absint((string) $media_row['attachment_id']) : 0;
    $resolved_url = $attachment_id > 0 ? (string) wp_get_attachment_image_url($attachment_id, $size) : '';
    if ($resolved_url === '' && $attachment_id > 0) {
        $resolved_url = (string) wp_get_attachment_url($attachment_id);
    }
    if ($resolved_url === '') {
        $resolved_url = isset($media_row['media_url']) ? (string) $media_row['media_url'] : '';
    }

    return $resolved_url;
};

$render_file_embed = static function (string $url, string $title): string {
    $url = trim($url);
    if ($url === '') {
        return '<p class="eocrm-muted">Brak pliku.</p>';
    }

    $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

    if (in_array($extension, $image_extensions, true)) {
        return '<img class="eocrm-offer-embedded-image eocrm-zoomable" src="' . esc_url($url) . '" data-eocrm-zoom-src="' . esc_url($url) . '" alt="' . esc_attr($title) . '">';
    }

    $iframe_src = $extension === 'pdf' ? $url . '#view=FitH' : $url;
    $zoom_button = '<button type="button" class="eocrm-embed-zoom-btn" data-eocrm-zoom-src="' . esc_url($iframe_src) . '" data-eocrm-zoom-kind="iframe" data-eocrm-zoom-title="' . esc_attr($title) . '" aria-label="' . esc_attr('Powieksz ' . $title) . '">Powieksz</button>';

    return '<div class="eocrm-embed-wrap eocrm-embed-zoomable">' . $zoom_button . '<iframe loading="lazy" src="' . esc_url($iframe_src) . '" title="' . esc_attr($title) . '" allowfullscreen></iframe></div>';
};

$slider_photos = [];
foreach ($photos as $photo_row) {
    $photo_url = $resolve_media_url($photo_row, 'large');
    if ($photo_url !== '') {
        $slider_photos[] = $photo_url;
    }
}
if (empty($slider_photos) && ! empty($offer['preview_photo_url'])) {
    $slider_photos[] = (string) $offer['preview_photo_url'];
}

$floor2d_url = is_array($floor_2d) ? $resolve_media_url($floor_2d, 'large') : '';
$floor3d_url = is_array($floor_3d) ? $resolve_media_url($floor_3d, 'large') : '';

$floor_plan_labels_by_attachment = [];
$floor_plan_labels_by_position = [];
$offer_floor_plan_meta = isset($offer_media['floor_plans']) && is_array($offer_media['floor_plans']) ? $offer_media['floor_plans'] : [];
foreach ($offer_floor_plan_meta as $meta_index => $meta_row) {
    if (! is_array($meta_row)) {
        continue;
    }
    $meta_attachment_id = isset($meta_row['attachment_id']) ? absint((string) $meta_row['attachment_id']) : 0;
    $meta_label = isset($meta_row['label']) ? sanitize_text_field((string) $meta_row['label']) : '';
    if ($meta_attachment_id > 0 && $meta_label !== '' && ! isset($floor_plan_labels_by_attachment[$meta_attachment_id])) {
        $floor_plan_labels_by_attachment[$meta_attachment_id] = $meta_label;
    }
    if ($meta_label !== '' && ! isset($floor_plan_labels_by_position[$meta_index])) {
        $floor_plan_labels_by_position[$meta_index] = $meta_label;
    }
}

$floor_plan_items = [];
foreach ($floor_plan_rows as $floor_plan_index => $floor_plan_row) {
    if (! is_array($floor_plan_row)) {
        continue;
    }
    $floor_plan_url = $resolve_media_url($floor_plan_row, 'large');
    if ($floor_plan_url === '') {
        continue;
    }

    $floor_plan_attachment_id = isset($floor_plan_row['attachment_id']) ? absint((string) $floor_plan_row['attachment_id']) : 0;
    $floor_plan_label = isset($floor_plan_row['label']) ? sanitize_text_field((string) $floor_plan_row['label']) : '';
    if ($floor_plan_label === '' && $floor_plan_attachment_id > 0 && isset($floor_plan_labels_by_attachment[$floor_plan_attachment_id])) {
        $floor_plan_label = (string) $floor_plan_labels_by_attachment[$floor_plan_attachment_id];
    }
    if ($floor_plan_label === '' && isset($floor_plan_labels_by_position[$floor_plan_index])) {
        $floor_plan_label = (string) $floor_plan_labels_by_position[$floor_plan_index];
    }
    if ($floor_plan_label === '') {
        $floor_plan_label = 'Poziom ' . (string) ($floor_plan_index + 1);
    }

    $floor_plan_items[] = [
        'label' => $floor_plan_label,
        'url' => $floor_plan_url,
    ];
}

if (empty($floor_plan_items)) {
    if ($floor2d_url !== '') {
        $floor_plan_items[] = [
            'label' => 'Poziom 1',
            'url' => $floor2d_url,
        ];
    }
    if ($floor3d_url !== '') {
        $floor_plan_items[] = [
            'label' => 'Poziom 2',
            'url' => $floor3d_url,
        ];
    }
}

$latitude = isset($offer['latitude']) ? (float) $offer['latitude'] : 0.0;
$longitude = isset($offer['longitude']) ? (float) $offer['longitude'] : 0.0;
$has_map_coordinates = $latitude !== 0.0 || $longitude !== 0.0;

$agent_name = isset($offer_agent['display_name']) ? (string) $offer_agent['display_name'] : '';
$agent_phone = isset($offer_agent['phone']) ? (string) $offer_agent['phone'] : '';
$agent_email = isset($offer_agent['email']) ? (string) $offer_agent['email'] : '';
$agent_photo_url = isset($offer_agent['photo_url']) ? (string) $offer_agent['photo_url'] : '';
$agent_bio = isset($offer_agent['bio']) ? (string) $offer_agent['bio'] : '';
$agent_office = isset($offer_agent['office_name']) ? (string) $offer_agent['office_name'] : '';
$agent_city = isset($offer_agent['city']) ? (string) $offer_agent['city'] : '';
$contact_notice = isset($contact_notice) && is_array($contact_notice) ? $contact_notice : ['type' => '', 'message' => ''];
$contact_form_values = isset($contact_form_values) && is_array($contact_form_values) ? $contact_form_values : [
    'name' => '',
    'email' => '',
    'phone' => '',
    'preferred_date' => '',
    'message' => '',
];
$is_sale_transaction = strtoupper((string) ($offer['transaction_type'] ?? '')) === 'SPRZEDAZ';
$offer_price_raw = isset($offer['price']) && is_numeric($offer['price']) ? (float) $offer['price'] : 0.0;
$default_down_payment = $offer_price_raw > 0 ? round($offer_price_raw * 0.2, 2) : 0.0;

$normalize_currency = static function ($currency): string {
    $currency_code = strtoupper(trim((string) $currency));
    $allowed = ['PLN', 'EUR', 'USD', 'GBP'];

    return in_array($currency_code, $allowed, true) ? $currency_code : 'PLN';
};
$offer_price_currency = $normalize_currency($offer['price_currency'] ?? 'PLN');

$format_price = static function ($value, string $currency_code): string {
    if (! is_numeric($value)) {
        return '-';
    }

    return number_format((float) $value, 0, ',', ' ') . ' ' . $currency_code;
};

$format_price_per_m2 = static function ($value, string $currency_code, string $area_unit): string {
    return EstateOfficeCRM_Units::format_price_per_area($value, $currency_code, $area_unit);
};

$format_tag_label = static function (string $tag) use ($tag_labels): string {
    $tag_key = strtolower(trim($tag));
    if ($tag_key === '') {
        return '';
    }

    if (isset($tag_labels[$tag_key])) {
        return $tag_labels[$tag_key];
    }

    return ucwords(str_replace('_', ' ', $tag_key));
};

$has_metric_value = static function ($value): bool {
    if ($value === null) {
        return false;
    }

    $value_text = trim((string) $value);
    if ($value_text === '' || $value_text === '-') {
        return false;
    }

    $numeric_candidate = str_replace(',', '.', $value_text);
    if (is_numeric($numeric_candidate)) {
        return (float) $numeric_candidate > 0;
    }

    return true;
};

$render_metric = static function (string $metric_type, $value, string $suffix = '') use ($has_metric_value): string {
    if (! $has_metric_value($value)) {
        return '';
    }

    $value_text = trim((string) $value);

    $display_value = $value_text;
    if ($suffix !== '') {
        $display_value .= ' ' . $suffix;
    }

    $icon_class = 'eocrm-offer-metric-icon';
    $icon_svg = '';

    switch ($metric_type) {
        case 'area':
            $icon_class .= ' eocrm-offer-metric-icon-solid';
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 10.5 12 4l8 6.5V19a1 1 0 0 1-1 1h-4.5v-5h-5v5H5a1 1 0 0 1-1-1z"></path></svg>';
            break;
        case 'lot':
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3z"></path><path d="M9 4v13M15 7v13"></path></svg>';
            break;
        case 'rooms':
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><rect x="4" y="4" width="16" height="16" rx="2"></rect><path d="M9 4v3M15 4v3M4 9h3M4 15h3"></path></svg>';
            break;
        case 'bedrooms':
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><path d="M3 11h18v7H3z"></path><path d="M5 11V7h5a2 2 0 0 1 2 2v2"></path><path d="M21 18v2M3 18v2"></path></svg>';
            break;
        case 'bathrooms':
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><path d="M3 12h18"></path><path d="M5 12v3a4 4 0 0 0 4 4h6a4 4 0 0 0 4-4v-3"></path><path d="M7 10V8a2 2 0 0 1 4 0v2"></path><path d="M18 12v-2a1 1 0 0 0-1-1"></path></svg>';
            break;
        case 'toilets':
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><path d="M8 4h8v3H8z"></path><path d="M9 7v4a3 3 0 0 0 3 3h2"></path><path d="M6 13h11a3 3 0 0 1-3 3h-4a4 4 0 0 1-4-3z"></path><path d="M9 16v3h6v-3"></path></svg>';
            break;
        default:
            $icon_svg = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"></circle></svg>';
            break;
    }

    return '<span class="eocrm-offer-metric eocrm-offer-metric-' . esc_attr($metric_type) . '"><span class="' . esc_attr($icon_class) . '" aria-hidden="true">' . $icon_svg . '</span><span>' . esc_html($display_value) . '</span></span>';
};

$summary_metrics = [];
if ($offer_property_type === 'DZIALKA') {
    $summary_metrics[] = $render_metric('lot', $offer_plot_area_display, $offer_area_unit_label);
} else {
    $summary_metrics[] = $render_metric('area', EstateOfficeCRM_Units::format_area_value($offer['area'] ?? null, $offer_area_unit), $offer_area_unit_label);
    if ($offer_property_type === 'DOM') {
        $summary_metrics[] = $render_metric('lot', $offer_plot_area_display, $offer_area_unit_label);
    }
}
$summary_metrics[] = $render_metric('rooms', $offer['rooms'] ?? null, 'pok.');
$summary_metrics[] = $render_metric('bedrooms', $offer['bedrooms'] ?? null, 'syp.');
$summary_metrics[] = $render_metric('bathrooms', $offer['bathrooms'] ?? null, 'laz.');
$summary_metrics[] = $render_metric('toilets', $offer['toilets'] ?? null, 'toal.');
$summary_metrics = array_values(array_filter($summary_metrics, static function (string $item): bool {
    return $item !== '';
}));

$has_offer_detail_value = static function ($value): bool {
    if ($value === null) {
        return false;
    }

    if (is_bool($value)) {
        return true;
    }

    if (is_numeric($value)) {
        return true;
    }

    $normalized = trim((string) $value);
    return $normalized !== '' && $normalized !== '-';
};

$offer_detail_rows = [];
$add_offer_detail_row = static function (array &$rows, string $label, $value) use ($has_offer_detail_value): void {
    if (! $has_offer_detail_value($value)) {
        return;
    }

    $rows[] = [
        'label' => $label,
        'value' => trim((string) $value),
    ];
};

$add_offer_detail_row($offer_detail_rows, 'Cena za m2', $format_price_per_m2($offer['price_per_m2'] ?? null, $offer_price_currency, $offer_area_unit));
if ($offer_property_type !== 'DZIALKA') {
    $add_offer_detail_row($offer_detail_rows, 'Czynsz', $offer['admin_rent'] ?? null);
    $add_offer_detail_row($offer_detail_rows, 'Rok budowy', $offer['year_built'] ?? null);
}
$add_offer_detail_row($offer_detail_rows, 'Pietro', $offer['floor_no'] ?? null);
$add_offer_detail_row($offer_detail_rows, 'Liczba pieter', $offer['floors_total'] ?? null);
$add_offer_detail_row($offer_detail_rows, 'Sypialnie', $offer['bedrooms'] ?? null);
$add_offer_detail_row($offer_detail_rows, 'Toalety', $offer['toilets'] ?? null);
$add_offer_detail_row($offer_detail_rows, 'Stan wykonczenia', $offer['building_finish'] ?? null);
$add_offer_detail_row($offer_detail_rows, 'Kuchnia', $offer['kitchen_type'] ?? null);

$offer_county_gmina_parts = array_values(array_filter(
    [$offer_county, $offer_gmina],
    static function (string $value): bool {
        return trim($value) !== '';
    }
));
$offer_county_gmina = implode(' / ', $offer_county_gmina_parts);
$add_offer_detail_row($offer_detail_rows, 'Powiat / Gmina', $offer_county_gmina);

if ($offer_has_plot_details) {
    $plot_area_detail = $offer_plot_area_display !== '' ? trim($offer_plot_area_display . ' ' . $offer_area_unit_label) : '';
    $add_offer_detail_row($offer_detail_rows, 'Wielkosc dzialki', $plot_area_detail);
    $add_offer_detail_row($offer_detail_rows, 'Ksztalt dzialki', $offer_plot_shape_label);
    $add_offer_detail_row($offer_detail_rows, 'Wymiary dzialki', $offer_plot_dimensions_display);
}
if (! empty($offer_custom_public_rows['details'])) {
    foreach ($offer_custom_public_rows['details'] as $custom_detail_row) {
        if (! is_array($custom_detail_row)) {
            continue;
        }
        $add_offer_detail_row(
            $offer_detail_rows,
            (string) ($custom_detail_row['label'] ?? 'Pole dodatkowe'),
            $custom_detail_row['value'] ?? ''
        );
    }
}

$offer_media_rows = [];
$add_offer_media_row = static function (array &$rows, string $label, $value) use ($has_offer_detail_value): void {
    if (! $has_offer_detail_value($value)) {
        return;
    }

    $rows[] = [
        'label' => $label,
        'value' => trim((string) $value),
    ];
};

$add_offer_media_row($offer_media_rows, 'Ogrzewanie', $offer_media['heating'] ?? '');
$add_offer_media_row($offer_media_rows, 'Woda', $offer_media['water'] ?? '');
$add_offer_media_row($offer_media_rows, 'Kanalizacja', $offer_media['sewage'] ?? '');
if (! empty($offer_media['gas'])) {
    $add_offer_media_row($offer_media_rows, 'Gaz', 'Tak');
}
if (! empty($offer_media['electricity'])) {
    $add_offer_media_row($offer_media_rows, 'Prad', 'Tak');
}
if (! empty($offer_custom_public_rows['media'])) {
    foreach ($offer_custom_public_rows['media'] as $custom_media_row) {
        if (! is_array($custom_media_row)) {
            continue;
        }
        $add_offer_media_row(
            $offer_media_rows,
            (string) ($custom_media_row['label'] ?? 'Dodatkowe media'),
            $custom_media_row['value'] ?? ''
        );
    }
}
?>
<div class="eocrm-wrap eocrm-public-wrap eocrm-offer-single-wrap">
    <header class="eocrm-offer-single-hero eocrm-offer-single-hero-v2">
        <div class="eocrm-offer-single-media eocrm-gallery-slider eocrm-gallery-16x9" data-eocrm-gallery-slider>
            <?php if (empty($slider_photos)) : ?>
                <div class="eocrm-offer-media-placeholder">Brak zdjecia glownego</div>
            <?php else : ?>
                <div class="eocrm-gallery-track">
                    <?php foreach ($slider_photos as $index => $photo_url) : ?>
                                <figure class="eocrm-gallery-slide<?php echo $index === 0 ? ' is-active' : ''; ?>" data-eocrm-gallery-slide="<?php echo (int) $index; ?>">
                                    <img class="eocrm-zoomable" src="<?php echo esc_url($photo_url); ?>" data-eocrm-zoom-src="<?php echo esc_url($photo_url); ?>" data-eocrm-gallery-zoom-index="<?php echo (int) $index; ?>" alt="Zdjecie oferty <?php echo (int) ($index + 1); ?>">
                                    <?php if ($offer_watermark_url !== '') : ?>
                                        <span class="eocrm-offer-watermark" aria-hidden="true">
                                            <img src="<?php echo esc_url($offer_watermark_url); ?>" alt="">
                                        </span>
                                    <?php endif; ?>
                                </figure>
                    <?php endforeach; ?>
                </div>
                <?php if (count($slider_photos) > 1) : ?>
                    <button type="button" class="eocrm-gallery-nav eocrm-gallery-prev" data-eocrm-gallery-prev aria-label="Poprzednie zdjecie">
                        <span class="eocrm-gallery-nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M14.7 5.3a1 1 0 0 1 0 1.4L10.41 11l4.3 4.3a1 1 0 1 1-1.42 1.4l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 0 1 1.41 0Z"></path></svg>
                        </span>
                    </button>
                    <button type="button" class="eocrm-gallery-nav eocrm-gallery-next" data-eocrm-gallery-next aria-label="Nastepne zdjecie">
                        <span class="eocrm-gallery-nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M9.3 5.3a1 1 0 0 1 1.4 0l5 5a1 1 0 0 1 0 1.4l-5 5a1 1 0 1 1-1.4-1.4l4.29-4.3-4.3-4.3a1 1 0 0 1 0-1.4Z"></path></svg>
                        </span>
                    </button>
                    <div class="eocrm-gallery-dots">
                        <?php foreach ($slider_photos as $index => $photo_url) : ?>
                            <button type="button" class="eocrm-gallery-dot<?php echo $index === 0 ? ' is-active' : ''; ?>" data-eocrm-gallery-dot="<?php echo (int) $index; ?>" aria-label="Zdjecie <?php echo (int) ($index + 1); ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (! empty($slider_photos)) : ?>
                <?php $offer_status_ribbon_side_counts = ['right' => 0, 'left' => 0]; ?>
                                <?php foreach ($offer_status_ribbons as $ribbon) : ?>
                                    <?php
                                    $ribbon_side = (string) ($ribbon['side'] ?? 'right');
                                    if (! in_array($ribbon_side, ['right', 'left'], true)) {
                                        $ribbon_side = 'right';
                                    }
                                    $ribbon_side_index = (int) ($offer_status_ribbon_side_counts[$ribbon_side] ?? 0);
                                    $offer_status_ribbon_side_counts[$ribbon_side] = $ribbon_side_index + 1;
                                    ?>
                                    <span class="eocrm-offer-status-ribbon eocrm-offer-status-ribbon-<?php echo esc_attr((string) ($ribbon['class'] ?? 'default')); ?> eocrm-offer-status-ribbon-<?php echo esc_attr($ribbon_side); ?>" style="<?php echo esc_attr('--eocrm-ribbon-index:' . (int) $ribbon_side_index . ';'); ?>">
                                        <?php echo esc_html((string) ($ribbon['label'] ?? '')); ?>
                                    </span>
                                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </header>

    <div class="eocrm-offer-v2-layout">
        <div class="eocrm-offer-v2-main">
            <section class="eocrm-card eocrm-offer-v2-summary-wrap">
                <div class="eocrm-offer-single-summary eocrm-offer-single-summary-v2 eocrm-offer-single-summary-v2-large">
                    <p class="eocrm-team-kicker"><?php echo esc_html((string) ($offer['transaction_type'] ?? '')); ?></p>
                    <h2 class="eocrm-offer-address-title"><?php echo esc_html($offer_address !== '' ? $offer_address : '-'); ?></h2>
                    <p class="eocrm-offer-price"><?php echo esc_html($format_price($offer['price'] ?? null, $offer_price_currency)); ?></p>
                    <p class="eocrm-offer-location"><?php echo esc_html($offer_location !== '' ? $offer_location : '-'); ?></p>
                    <?php if (in_array($offer_property_type, ['DOM', 'DZIALKA'], true) && ($offer_county !== '' || $offer_gmina !== '')) : ?>
                        <p class="eocrm-offer-location"><?php echo esc_html('Powiat: ' . ($offer_county !== '' ? $offer_county : '-') . ' | Gmina: ' . ($offer_gmina !== '' ? $offer_gmina : '-')); ?></p>
                    <?php endif; ?>
                    <?php if (trim((string) ($offer['property_type'] ?? '')) !== '') : ?>
                        <p class="eocrm-offer-property-type">Rodzaj: <?php echo esc_html((string) $offer['property_type']); ?></p>
                    <?php endif; ?>
                    <p class="eocrm-offer-number-line">Numer oferty: <?php echo esc_html((string) ($offer['offer_number'] ?? '-')); ?></p>
                    <?php if (! empty($summary_metrics)) : ?>
                        <div class="eocrm-offer-metrics">
                            <?php foreach ($summary_metrics as $metric_html) : ?>
                                <?php echo $metric_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($offer_tags)) : ?>
                        <p class="eocrm-tags">
                            <?php foreach ($offer_tags as $tag) : ?>
                                <?php $tag_label = $format_tag_label((string) $tag); ?>
                                <?php if ($tag_label !== '') : ?>
                                    <span><?php echo esc_html($tag_label); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </section>

    <section class="eocrm-grid eocrm-grid-cards eocrm-offer-spec-grid">
        <article class="eocrm-card eocrm-offer-details-card">
            <h3>Szczegoly nieruchomosci</h3>
            <?php if (empty($offer_detail_rows)) : ?>
                <p class="eocrm-muted">Brak danych.</p>
            <?php else : ?>
                <?php foreach ($offer_detail_rows as $detail_row) : ?>
                    <p><strong><?php echo esc_html((string) ($detail_row['label'] ?? '')); ?>:</strong> <?php echo esc_html((string) ($detail_row['value'] ?? '')); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </article>

        <article class="eocrm-card">
            <h3>Udogodnienia</h3>
            <?php if (empty($amenities)) : ?>
                <p class="eocrm-muted">Brak danych.</p>
            <?php else : ?>
                <ul class="eocrm-link-list">
                    <?php foreach ($amenities as $amenity) : ?>
                        <li><?php echo esc_html((string) $amenity); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <h4>Wyposazenie</h4>
            <?php if (empty($equipment)) : ?>
                <p class="eocrm-muted">Brak danych.</p>
            <?php else : ?>
                <ul class="eocrm-link-list">
                    <?php foreach ($equipment as $equipment_item) : ?>
                        <li><?php echo esc_html((string) $equipment_item); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

    <section class="eocrm-card eocrm-offer-media-card">
        <h3>Media</h3>
        <?php if (empty($offer_media_rows)) : ?>
            <p class="eocrm-muted">Brak danych.</p>
        <?php else : ?>
            <div class="eocrm-offer-media-grid">
                <?php foreach ($offer_media_rows as $media_row) : ?>
                    <div class="eocrm-offer-media-item">
                        <span class="eocrm-offer-media-label"><?php echo esc_html((string) ($media_row['label'] ?? '')); ?>:</span>
                        <span class="eocrm-offer-media-value"><?php echo esc_html((string) ($media_row['value'] ?? '')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="eocrm-card">
        <h3>Opis oferty</h3>
        <div class="eocrm-description">
            <?php
            $description = (string) ($offer['description'] ?? '');
            echo $description !== '' ? wp_kses_post(wpautop($description)) : '<p>Brak opisu.</p>';
            ?>
        </div>
    </section>

    <?php if (! empty($multimedia_items)) : ?>
        <section class="eocrm-card">
            <h3>Multimedia</h3>
            <div class="eocrm-floor-plan-switcher" data-eocrm-floor-plan-switcher>
                <?php if (count($multimedia_items) > 1) : ?>
                    <div class="eocrm-floor-plan-tabs" data-eocrm-floor-plan-tabs role="tablist" aria-label="Multimedia oferty">
                        <?php foreach ($multimedia_items as $media_index => $media_item) : ?>
                            <button
                                type="button"
                                class="eocrm-floor-plan-tab<?php echo $media_index === 0 ? ' is-active' : ''; ?>"
                                data-eocrm-floor-tab="<?php echo (int) $media_index; ?>"
                                role="tab"
                                aria-selected="<?php echo $media_index === 0 ? 'true' : 'false'; ?>"
                            >
                                <?php echo esc_html((string) ($media_item['label'] ?? 'Multimedia')); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="eocrm-floor-plans-frame">
                    <?php foreach ($multimedia_items as $media_index => $media_item) : ?>
                        <?php
                        $media_url = isset($media_item['url']) ? trim((string) $media_item['url']) : '';
                        $media_title = isset($media_item['title']) ? (string) $media_item['title'] : 'Multimedia';
                        ?>
                        <div class="eocrm-floor-plan-panel<?php echo $media_index === 0 ? ' is-active' : ''; ?>" data-eocrm-floor-panel="<?php echo (int) $media_index; ?>" role="tabpanel">
                            <div class="eocrm-floor-plan-item">
                                <?php if ($media_url !== '') : ?>
                                    <?php $media_embed = wp_oembed_get($media_url); ?>
                                    <?php if (is_string($media_embed) && $media_embed !== '') : ?>
                                        <div class="eocrm-embed-wrap eocrm-offer-media-embed"><?php echo $media_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                    <?php else : ?>
                                        <div class="eocrm-embed-wrap eocrm-offer-media-embed">
                                            <iframe loading="lazy" src="<?php echo esc_url($media_url); ?>" title="<?php echo esc_attr($media_title); ?>" allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </section>
    <?php endif; ?>

    <section class="eocrm-card">
        <h3>Rzuty</h3>
        <?php if (empty($floor_plan_items)) : ?>
            <p class="eocrm-muted">Brak rzutow dla tej oferty.</p>
        <?php else : ?>
            <div class="eocrm-floor-plan-switcher" data-eocrm-floor-plan-switcher>
                <div class="eocrm-floor-plan-tabs" data-eocrm-floor-plan-tabs role="tablist" aria-label="Poziomy rzutow">
                    <?php foreach ($floor_plan_items as $floor_plan_index => $floor_plan_item) : ?>
                        <button
                            type="button"
                            class="eocrm-floor-plan-tab<?php echo $floor_plan_index === 0 ? ' is-active' : ''; ?>"
                            data-eocrm-floor-tab="<?php echo (int) $floor_plan_index; ?>"
                            role="tab"
                            aria-selected="<?php echo $floor_plan_index === 0 ? 'true' : 'false'; ?>"
                        >
                            <?php echo esc_html((string) ($floor_plan_item['label'] ?? ('Poziom ' . (string) ($floor_plan_index + 1)))); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="eocrm-floor-plans-frame">
                    <?php foreach ($floor_plan_items as $floor_plan_index => $floor_plan_item) : ?>
                        <?php
                        $floor_plan_label = isset($floor_plan_item['label']) ? (string) $floor_plan_item['label'] : ('Poziom ' . (string) ($floor_plan_index + 1));
                        $floor_plan_url = isset($floor_plan_item['url']) ? (string) $floor_plan_item['url'] : '';
                        ?>
                        <div class="eocrm-floor-plan-panel<?php echo $floor_plan_index === 0 ? ' is-active' : ''; ?>" data-eocrm-floor-panel="<?php echo (int) $floor_plan_index; ?>" role="tabpanel">
                            <div class="eocrm-floor-plan-item">
                                <?php echo $render_file_embed($floor_plan_url, 'Rzut: ' . $floor_plan_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <h4>Mapa lokalizacji</h4>
        <?php if ($has_map_coordinates) : ?>
            <div class="eocrm-embed-wrap">
                <iframe
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    src="<?php echo esc_url('https://www.google.com/maps?q=' . rawurlencode((string) $latitude . ',' . (string) $longitude) . '&z=14&output=embed'); ?>"
                    title="Mapa lokalizacji oferty"
                ></iframe>
            </div>
        <?php else : ?>
            <p class="eocrm-muted">Brak wspolrzednych GPS dla tej oferty.</p>
        <?php endif; ?>
    </section>

        </div>

        <aside class="eocrm-offer-v3-side eocrm-offer-v2-side">
            <section class="eocrm-card eocrm-offer-v3-agent eocrm-offer-v2-agent">
                <h3>Agent - Opiekun oferty</h3>
                <?php if ($agent_name === '' && $agent_phone === '' && $agent_email === '' && $agent_bio === '') : ?>
                    <p class="eocrm-muted">Brak przypisanego opiekuna oferty.</p>
                <?php else : ?>
                    <div class="eocrm-offer-agent-layout">
                        <div class="eocrm-offer-agent-photo-wrap">
                            <?php if ($agent_photo_url !== '') : ?>
                                <img class="eocrm-offer-agent-photo" src="<?php echo esc_url($agent_photo_url); ?>" alt="Zdjecie opiekuna oferty">
                            <?php else : ?>
                                <div class="eocrm-offer-agent-photo eocrm-team-photo-placeholder"><?php echo esc_html(substr($agent_name !== '' ? $agent_name : 'A', 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="eocrm-offer-agent-details">
                            <h4><?php echo esc_html($agent_name !== '' ? $agent_name : 'Opiekun oferty'); ?></h4>
                            <?php if ($agent_office !== '') : ?>
                                <p><strong>Biuro:</strong> <?php echo esc_html($agent_office); ?></p>
                            <?php endif; ?>
                            <?php if ($agent_city !== '') : ?>
                                <p><strong>Lokalizacja:</strong> <?php echo esc_html($agent_city); ?></p>
                            <?php endif; ?>
                            <?php if ($agent_phone !== '') : ?>
                                <p><strong>Telefon:</strong> <?php echo esc_html($agent_phone); ?></p>
                            <?php endif; ?>
                            <?php if ($agent_email !== '') : ?>
                                <p><strong>E-mail:</strong> <a href="mailto:<?php echo esc_attr($agent_email); ?>"><?php echo esc_html($agent_email); ?></a></p>
                            <?php endif; ?>
                            <?php if ($agent_bio !== '') : ?>
                                <div class="eocrm-description">
                                    <?php echo wp_kses_post(wpautop($agent_bio)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="eocrm-card eocrm-offer-v3-contact eocrm-offer-v2-contact" id="eocrm-offer-contact">
                <h3>Umow sie na wizyte</h3>
                <?php if (! empty($contact_notice['message'])) : ?>
                    <p class="eocrm-alert <?php echo ($contact_notice['type'] ?? '') === 'success' ? 'eocrm-alert-success' : 'eocrm-alert-error'; ?>">
                        <?php echo esc_html((string) $contact_notice['message']); ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="#eocrm-offer-contact" class="eocrm-offer-contact-form">
                    <?php wp_nonce_field('eocrm_offer_contact_' . (int) ($offer['id'] ?? 0), 'eocrm_offer_contact_nonce'); ?>
                    <input type="hidden" name="eocrm_offer_contact_action" value="schedule_visit">
                    <input type="hidden" name="eocrm_offer_contact_property_id" value="<?php echo (int) ($offer['id'] ?? 0); ?>">

                    <p class="eocrm-form-field">
                        <label>Imie i nazwisko</label>
                        <input type="text" name="contact_name" value="<?php echo esc_attr((string) ($contact_form_values['name'] ?? '')); ?>" required>
                    </p>
                    <p class="eocrm-form-field">
                        <label>E-mail</label>
                        <input type="email" name="contact_email" value="<?php echo esc_attr((string) ($contact_form_values['email'] ?? '')); ?>" required>
                    </p>
                    <p class="eocrm-form-field">
                        <label>Telefon</label>
                        <input type="text" name="contact_phone" value="<?php echo esc_attr((string) ($contact_form_values['phone'] ?? '')); ?>">
                    </p>
                    <p class="eocrm-form-field">
                        <label>Preferowany termin</label>
                        <input type="text" name="contact_preferred_date" placeholder="np. wtorek po 18:00" value="<?php echo esc_attr((string) ($contact_form_values['preferred_date'] ?? '')); ?>">
                    </p>
                    <p class="eocrm-form-field eocrm-offer-contact-field-message">
                        <label>Wiadomosc</label>
                        <textarea name="contact_message" rows="3"><?php echo esc_textarea((string) ($contact_form_values['message'] ?? '')); ?></textarea>
                    </p>
                    <p class="eocrm-offer-contact-actions"><button class="eocrm-btn eocrm-btn-primary" type="submit">Wyslij zapytanie</button></p>
                </form>
            </section>
        </aside>
    </div>

    <?php if ($is_sale_transaction) : ?>
        <div class="eocrm-offer-section-divider" aria-hidden="true"></div>
        <section class="eocrm-card eocrm-mortgage-card" data-eocrm-mortgage-calculator>
            <h3>Kalkulator kredytowy</h3>
            <p class="eocrm-muted">Szacunek rat i kosztów kredytu hipotecznego na podstawie ceny oferty oraz WIBOR 1M/3M/6M.</p>

            <div class="eocrm-mortgage-layout">
                <div class="eocrm-mortgage-results">
                    <div class="eocrm-profile-defs">
                        <div><dt>Kwota kredytu</dt><dd data-eocrm-calc-result-loan>0 PLN</dd></div>
                        <div><dt>Oprocentowanie roczne</dt><dd data-eocrm-calc-result-rate>0.00%</dd></div>
                        <div><dt>Rata miesięczna (szac.)</dt><dd data-eocrm-calc-result-monthly>0 PLN</dd></div>
                        <div><dt>Pierwsza rata (malejące)</dt><dd data-eocrm-calc-result-first>0 PLN</dd></div>
                        <div><dt>Ostatnia rata (malejące)</dt><dd data-eocrm-calc-result-last>0 PLN</dd></div>
                        <div><dt>Koszt odsetek</dt><dd data-eocrm-calc-result-interest>0 PLN</dd></div>
                        <div><dt>Prowizja banku</dt><dd data-eocrm-calc-result-commission>0 PLN</dd></div>
                        <div><dt>Łączny koszt kredytu</dt><dd data-eocrm-calc-result-total>0 PLN</dd></div>
                    </div>

                    <p class="eocrm-muted" data-eocrm-calc-wibor-meta>Źródło WIBOR: ładowanie danych...</p>
                </div>

                <div class="eocrm-mortgage-settings">
                    <div class="eocrm-form-grid eocrm-mortgage-form-grid">
                        <p class="eocrm-form-field">
                            <label>Cena nieruchomosci (PLN)</label>
                            <input type="number" min="0" step="0.01" data-eocrm-calc-price value="<?php echo esc_attr((string) $offer_price_raw); ?>" readonly>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Wkład własny (PLN)</label>
                            <input type="number" min="0" step="0.01" data-eocrm-calc-down-payment value="<?php echo esc_attr((string) $default_down_payment); ?>">
                        </p>
                        <p class="eocrm-form-field">
                            <label>Wkład własny (%)</label>
                            <input type="number" min="0" max="100" step="1" data-eocrm-calc-down-payment-percent value="20">
                        </p>
                        <p class="eocrm-form-field">
                            <label>Okres kredytu (lata)</label>
                            <input type="number" min="1" max="45" step="1" data-eocrm-calc-years value="30">
                        </p>
                        <p class="eocrm-form-field">
                            <label>WIBOR</label>
                            <input type="hidden" data-eocrm-calc-wibor-tenor value="3M">
                            <span class="eocrm-wibor-toggle">
                                <button type="button" class="eocrm-wibor-btn" data-eocrm-calc-wibor-btn="1M">WIBOR 1M</button>
                                <button type="button" class="eocrm-wibor-btn is-active" data-eocrm-calc-wibor-btn="3M">WIBOR 3M</button>
                                <button type="button" class="eocrm-wibor-btn" data-eocrm-calc-wibor-btn="6M">WIBOR 6M</button>
                            </span>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Aktualny WIBOR (%)</label>
                            <input type="text" data-eocrm-calc-wibor-rate value="-" readonly>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Marża banku (%)</label>
                            <input type="number" min="0" step="0.01" data-eocrm-calc-margin value="2.00">
                        </p>
                        <p class="eocrm-form-field">
                            <label>Prowizja banku (%)</label>
                            <input type="number" min="0" step="0.01" data-eocrm-calc-commission value="1.50">
                        </p>
                        <p class="eocrm-form-field">
                            <label>Raty</label>
                            <select data-eocrm-calc-installment-type>
                                <option value="equal" selected>Raty równe</option>
                                <option value="decreasing">Raty malejące</option>
                            </select>
                        </p>
                        <p class="eocrm-form-field">
                            <label>Dodatkowe opłaty miesięczne (PLN)</label>
                            <input type="number" min="0" step="0.01" data-eocrm-calc-monthly-fees value="0">
                        </p>
                        <p class="eocrm-form-field eocrm-form-field-checkbox">
                            <label><input type="checkbox" data-eocrm-calc-finance-commission checked>Dolicz prowizję do kwoty kredytu</label>
                        </p>
                    </div>
                </div>
            </div>
            <?php if (! empty($mortgage_advisor_block_html)) : ?>
                <?php echo $mortgage_advisor_block_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="eocrm-lightbox" data-eocrm-lightbox hidden>
        <button type="button" class="eocrm-lightbox-nav eocrm-lightbox-prev" data-eocrm-lightbox-prev aria-label="Poprzednie zdjecie" hidden>&lsaquo;</button>
        <button type="button" class="eocrm-lightbox-nav eocrm-lightbox-next" data-eocrm-lightbox-next aria-label="Nastepne zdjecie" hidden>&rsaquo;</button>
        <button type="button" class="eocrm-lightbox-close" data-eocrm-lightbox-close aria-label="Zamknij powiekszenie">&times;</button>
        <div class="eocrm-lightbox-media" data-eocrm-lightbox-media hidden>
            <img src="" alt="Powiekszone zdjecie oferty" data-eocrm-lightbox-image hidden>
            <?php if ($offer_watermark_url !== '') : ?>
                <img class="eocrm-lightbox-watermark" src="<?php echo esc_url($offer_watermark_url); ?>" alt="" data-eocrm-lightbox-watermark hidden>
            <?php endif; ?>
        </div>
        <iframe loading="lazy" src="" title="Powiekszony rzut oferty" data-eocrm-lightbox-frame hidden allowfullscreen></iframe>
    </div>
</div>
