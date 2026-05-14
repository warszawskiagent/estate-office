<?php
if (! defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('eocrm-frontend');

$current_url = isset($offers_page_url) && is_string($offers_page_url) && $offers_page_url !== ''
    ? $offers_page_url
    : (get_permalink() ?: home_url('/'));
$offer_watermark_url = isset($offer_watermark_url) && is_string($offer_watermark_url) ? trim($offer_watermark_url) : '';
$offer_open_in_new_window = ! empty($offer_open_in_new_window);
$unit_settings = isset($unit_settings) && is_array($unit_settings)
    ? $unit_settings
    : EstateOfficeCRM_Units::default_settings();
$map_markers = [];
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
$normalize_currency = static function ($currency): string {
    $currency_code = strtoupper(trim((string) $currency));
    $allowed = ['PLN', 'EUR', 'USD', 'GBP'];

    return in_array($currency_code, $allowed, true) ? $currency_code : 'PLN';
};
$format_price = static function ($value, $currency) use ($normalize_currency): string {
    if (! is_numeric($value)) {
        return '-';
    }

    return number_format((float) $value, 0, ',', ' ') . ' ' . $normalize_currency($currency);
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
$format_offer_description_line = static function (string $raw_description): string {
    $plain_text = trim((string) wp_strip_all_tags($raw_description));
    if ($plain_text === '') {
        return '';
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $plain_text);
    $lines = explode("\n", $normalized);
    $first_line = trim((string) ($lines[0] ?? ''));
    if ($first_line === '') {
        $first_line = $plain_text;
    }

    $first_line = trim((string) preg_replace('/\s+/u', ' ', $first_line));
    $max_length = 100;

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($first_line) > $max_length) {
            return rtrim((string) mb_substr($first_line, 0, $max_length)) . '...';
        }

        return $first_line;
    }

    if (strlen($first_line) > $max_length) {
        return rtrim(substr($first_line, 0, $max_length)) . '...';
    }

    return $first_line;
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

foreach ($offers as $offer_item_for_map) {
    $candidate_lat = isset($offer_item_for_map['latitude']) ? (float) $offer_item_for_map['latitude'] : 0.0;
    $candidate_lng = isset($offer_item_for_map['longitude']) ? (float) $offer_item_for_map['longitude'] : 0.0;
    if ($candidate_lat === 0.0 && $candidate_lng === 0.0) {
        continue;
    }

    $candidate_property_type = strtoupper(trim((string) ($offer_item_for_map['property_type'] ?? '')));
    $candidate_show_building_no = ! in_array($candidate_property_type, ['DOM', 'DZIALKA'], true);
    $candidate_address = trim((string) ($offer_item_for_map['street'] ?? '') . ($candidate_show_building_no && trim((string) ($offer_item_for_map['building_no'] ?? '')) !== '' ? (' ' . (string) ($offer_item_for_map['building_no'] ?? '')) : ''));
    $candidate_district = trim((string) ($offer_item_for_map['district'] ?? ''));
    $candidate_location = trim((string) ($offer_item_for_map['city'] ?? '') . ($candidate_district !== '' ? (' / ' . $candidate_district) : ''));

    $map_markers[] = [
        'lat' => $candidate_lat,
        'lng' => $candidate_lng,
        'address' => $candidate_address,
        'location' => $candidate_location,
        'property_type' => (string) ($offer_item_for_map['property_type'] ?? ''),
        'price' => $format_price($offer_item_for_map['price'] ?? null, $offer_item_for_map['price_currency'] ?? 'PLN'),
        'offer_number' => (string) ($offer_item_for_map['offer_number'] ?? ''),
        'detail_url' => (string) ($offer_item_for_map['detail_url'] ?? ''),
        'preview_photo_url' => (string) ($offer_item_for_map['preview_photo_url'] ?? ''),
        'open_new_window' => $offer_open_in_new_window ? 1 : 0,
    ];
}
$map_markers_json = wp_json_encode($map_markers);
if (! is_string($map_markers_json)) {
    $map_markers_json = '[]';
}
?>
<div class="eocrm-wrap eocrm-public-wrap eocrm-offers-wrap" data-eocrm-offers-transaction="<?php echo esc_attr((string) $transaction); ?>">
    <form class="eocrm-filters eocrm-offers-filters" method="get" action="<?php echo esc_url($current_url); ?>" data-eocrm-offers-filters-form data-eocrm-auto-submit-delay="380">
        <input type="hidden" name="eocrm_page" value="1">
        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-type">Rodzaj nieruchomosci</label>
            <select id="eocrm-filter-type" name="property_type">
                <option value="">Wszystkie</option>
                <?php foreach (($filter_options['property_type'] ?? []) as $option) : ?>
                    <option value="<?php echo esc_attr($option); ?>" <?php selected($filters['property_type'], $option); ?>><?php echo esc_html($option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-city">Miasto</label>
            <select id="eocrm-filter-city" name="city">
                <option value="">Wszystkie</option>
                <?php foreach (($filter_options['city'] ?? []) as $option) : ?>
                    <option value="<?php echo esc_attr($option); ?>" <?php selected($filters['city'], $option); ?>><?php echo esc_html($option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-district">Dzielnica</label>
            <select id="eocrm-filter-district" name="district">
                <option value="">Wszystkie</option>
                <?php foreach (($filter_options['district'] ?? []) as $option) : ?>
                    <option value="<?php echo esc_attr($option); ?>" <?php selected($filters['district'], $option); ?>><?php echo esc_html($option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-price-min">Cena od</label>
            <input id="eocrm-filter-price-min" type="number" name="price_min" min="0" step="0.01" inputmode="decimal" value="<?php echo esc_attr((string) ($filters['price_min'] ?? '')); ?>">
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-price-max">Cena do</label>
            <input id="eocrm-filter-price-max" type="number" name="price_max" min="0" step="0.01" inputmode="decimal" value="<?php echo esc_attr((string) ($filters['price_max'] ?? '')); ?>">
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-area-min">Metraz od</label>
            <input id="eocrm-filter-area-min" type="number" name="area_min" min="0" step="0.01" inputmode="decimal" value="<?php echo esc_attr((string) ($filters['area_min'] ?? '')); ?>">
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-area-max">Metraz do</label>
            <input id="eocrm-filter-area-max" type="number" name="area_max" min="0" step="0.01" inputmode="decimal" value="<?php echo esc_attr((string) ($filters['area_max'] ?? '')); ?>">
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-rooms-min">Liczba pokoi od</label>
            <input id="eocrm-filter-rooms-min" type="number" name="rooms_min" min="0" step="1" inputmode="numeric" value="<?php echo esc_attr((string) ($filters['rooms_min'] ?? '')); ?>">
        </div>

        <div class="eocrm-offers-filter-field">
            <label for="eocrm-filter-rooms-max">Liczba pokoi do</label>
            <input id="eocrm-filter-rooms-max" type="number" name="rooms_max" min="0" step="1" inputmode="numeric" value="<?php echo esc_attr((string) ($filters['rooms_max'] ?? '')); ?>">
        </div>

        <div class="eocrm-filter-actions">
            <span class="eocrm-live-filter-pill" data-eocrm-live-status>Filtrowanie na zywo</span>
            <a class="eocrm-btn" data-eocrm-filter-clear href="<?php echo esc_url($current_url); ?>">Wyczysc</a>
        </div>
    </form>

    <div class="eocrm-offers-meta">
        <span class="eocrm-offers-meta-item">Wynikow: <strong><?php echo esc_html((string) ((int) ($total_offers ?? 0))); ?></strong></span>
        <span class="eocrm-offers-meta-separator" aria-hidden="true">|</span>
        <span class="eocrm-offers-meta-item">Na stronie: <strong><?php echo esc_html((string) ((int) ($public_offers_per_page ?? 6))); ?></strong></span>
    </div>

    <div class="eocrm-offers-layout">
        <aside class="eocrm-offers-map-panel">
            <h3>Mapa ofert</h3>
            <?php if (! empty($map_markers)) : ?>
                <div
                    class="eocrm-offers-map-canvas"
                    data-eocrm-offers-map
                    data-eocrm-map-markers="<?php echo esc_attr($map_markers_json); ?>"
                ></div>
                <p class="eocrm-muted" data-eocrm-offers-map-status></p>
            <?php else : ?>
                <p>Brak wspolrzednych GPS w aktywnych ofertach.</p>
            <?php endif; ?>
        </aside>

        <section class="eocrm-offers-list">
            <?php if (empty($offers)) : ?>
                <div class="eocrm-card">
                    <p>Brak ofert spelniajacych kryteria.</p>
                </div>
            <?php else : ?>
                <?php foreach ($offers as $offer) : ?>
                    <?php
                    $preview_photo_url = isset($offer['preview_photo_url']) ? (string) $offer['preview_photo_url'] : '';
                    $price = $format_price($offer['price'] ?? null, $offer['price_currency'] ?? 'PLN');
                    $area = isset($offer['area']) ? (string) $offer['area'] : '';
                    $rooms = isset($offer['rooms']) ? (string) $offer['rooms'] : '';
                    $bedrooms = isset($offer['bedrooms']) ? (string) $offer['bedrooms'] : '';
                    $bathrooms = isset($offer['bathrooms']) ? (string) $offer['bathrooms'] : '';
                    $toilets = isset($offer['toilets']) ? (string) $offer['toilets'] : '';
                    $location = trim((string) ($offer['city'] ?? '') . ' / ' . (string) ($offer['district'] ?? ''));
                    $offer_number = isset($offer['offer_number']) ? (string) $offer['offer_number'] : '';
                    $property_type = isset($offer['property_type']) ? (string) $offer['property_type'] : '';
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
                    $property_type_normalized = strtoupper(trim($property_type));
                    $show_building_no = ! in_array($property_type_normalized, ['DOM', 'DZIALKA'], true);
                    $address = trim((string) ($offer['street'] ?? '') . ($show_building_no && trim((string) ($offer['building_no'] ?? '')) !== '' ? (' ' . (string) ($offer['building_no'] ?? '')) : ''));
                    $county = trim((string) ($offer['county'] ?? ''));
                    $gmina = trim((string) ($offer['gmina'] ?? ''));
                    $description_line = $format_offer_description_line((string) ($offer['description'] ?? ''));
                    $area_unit = EstateOfficeCRM_Units::get_area_unit_for_property($property_type_normalized, $unit_settings);
                    $area_value = EstateOfficeCRM_Units::format_area_value($area, $area_unit);
                    $plot_area_value = null;
                    if ($property_type_normalized === 'DOM' && isset($offer['plot_area']) && is_numeric((string) $offer['plot_area'])) {
                        $plot_area_value = (float) $offer['plot_area'];
                    } elseif ($property_type_normalized === 'DZIALKA' && is_numeric((string) ($offer['area'] ?? ''))) {
                        $plot_area_value = (float) $offer['area'];
                    }
                    $plot_area_formatted = EstateOfficeCRM_Units::format_area_value($plot_area_value, $area_unit);
                    $detail_target_attrs = $offer_open_in_new_window ? ' target="_blank" rel="noopener noreferrer"' : '';

                    $summary_metrics = [];
                    if ($property_type_normalized === 'DZIALKA') {
                        $summary_metrics[] = $render_metric('lot', $plot_area_formatted, EstateOfficeCRM_Units::unit_label($area_unit));
                    } else {
                        $summary_metrics[] = $render_metric('area', $area_value, EstateOfficeCRM_Units::unit_label($area_unit));
                        if ($property_type_normalized === 'DOM') {
                            $summary_metrics[] = $render_metric('lot', $plot_area_formatted, EstateOfficeCRM_Units::unit_label($area_unit));
                        }
                    }

                    $summary_metrics[] = $render_metric('rooms', $rooms, 'pok.');
                    $summary_metrics[] = $render_metric('bedrooms', $bedrooms, 'syp.');
                    $summary_metrics[] = $render_metric('bathrooms', $bathrooms, 'laz.');
                    $summary_metrics[] = $render_metric('toilets', $toilets, 'toal.');
                    $summary_metrics = array_values(array_filter($summary_metrics, static function (string $item): bool {
                        return $item !== '';
                    }));
                    ?>
                    <article class="eocrm-offer-row-card">
                        <div class="eocrm-offer-row-media">
                            <?php if ($preview_photo_url !== '') : ?>
                                <img src="<?php echo esc_url($preview_photo_url); ?>" alt="Zdjecie oferty">
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
                                <?php if ($offer_watermark_url !== '') : ?>
                                    <span class="eocrm-offer-watermark" aria-hidden="true">
                                        <img src="<?php echo esc_url($offer_watermark_url); ?>" alt="">
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <div class="eocrm-offer-media-placeholder">Brak zdjecia</div>
                            <?php endif; ?>
                        </div>
                        <div class="eocrm-offer-row-body">
                            <div class="eocrm-offer-row-top">
                                <h3 class="eocrm-offer-address-title"><?php echo esc_html($address !== '' ? $address : '-'); ?></h3>
                            </div>
                            <p class="eocrm-offer-price"><?php echo esc_html($price); ?></p>
                            <p class="eocrm-offer-location"><?php echo esc_html($location !== '/' ? $location : '-'); ?></p>
                            <?php if (in_array($property_type_normalized, ['DOM', 'DZIALKA'], true) && ($county !== '' || $gmina !== '')) : ?>
                                <p class="eocrm-offer-location">
                                    <?php echo esc_html('Powiat: ' . ($county !== '' ? $county : '-') . ' | Gmina: ' . ($gmina !== '' ? $gmina : '-')); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($property_type !== '') : ?>
                                <p class="eocrm-offer-property-type">Rodzaj: <?php echo esc_html($property_type); ?></p>
                            <?php endif; ?>
                            <p class="eocrm-offer-number-line">Numer oferty: <?php echo esc_html($offer_number !== '' ? $offer_number : '-'); ?></p>
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
                            <?php if ($description_line !== '') : ?>
                                <div class="eocrm-description"><?php echo esc_html($description_line); ?></div>
                            <?php endif; ?>
                            <?php if (! empty($offer['detail_url'])) : ?>
                                <p><a class="eocrm-btn eocrm-btn-primary" href="<?php echo esc_url((string) $offer['detail_url']); ?>"<?php echo $detail_target_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kontrolowane atrybuty target/rel ?> >Zobacz oferte</a></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <?php if (isset($total_pages) && isset($current_page) && (int) $total_pages > 1) : ?>
        <?php
        $base_args = [];
        if (($filters['property_type'] ?? '') !== '') {
            $base_args['property_type'] = (string) $filters['property_type'];
        }
        if (($filters['city'] ?? '') !== '') {
            $base_args['city'] = (string) $filters['city'];
        }
        if (($filters['district'] ?? '') !== '') {
            $base_args['district'] = (string) $filters['district'];
        }
        if (($filters['price_min'] ?? '') !== '') {
            $base_args['price_min'] = (string) $filters['price_min'];
        }
        if (($filters['price_max'] ?? '') !== '') {
            $base_args['price_max'] = (string) $filters['price_max'];
        }
        if (($filters['area_min'] ?? '') !== '') {
            $base_args['area_min'] = (string) $filters['area_min'];
        }
        if (($filters['area_max'] ?? '') !== '') {
            $base_args['area_max'] = (string) $filters['area_max'];
        }
        if (($filters['rooms_min'] ?? '') !== '') {
            $base_args['rooms_min'] = (string) $filters['rooms_min'];
        }
        if (($filters['rooms_max'] ?? '') !== '') {
            $base_args['rooms_max'] = (string) $filters['rooms_max'];
        }

        $current_page = (int) $current_page;
        $total_pages = (int) $total_pages;
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        ?>
        <nav class="eocrm-pagination" aria-label="Paginacja ofert">
            <span class="eocrm-pagination-summary">Strona <?php echo esc_html((string) $current_page); ?> z <?php echo esc_html((string) $total_pages); ?></span>
            <?php if ($current_page > 1) : ?>
                <?php $prev_url = add_query_arg(array_merge($base_args, ['eocrm_page' => $current_page - 1]), $current_url); ?>
                <a class="eocrm-page-link" href="<?php echo esc_url($prev_url); ?>">&laquo; Poprzednia</a>
            <?php endif; ?>

            <?php for ($page_no = $start_page; $page_no <= $end_page; $page_no++) : ?>
                <?php $page_url = add_query_arg(array_merge($base_args, ['eocrm_page' => $page_no]), $current_url); ?>
                <a class="eocrm-page-link<?php echo $page_no === $current_page ? ' is-active' : ''; ?>" href="<?php echo esc_url($page_url); ?>"><?php echo esc_html((string) $page_no); ?></a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages) : ?>
                <?php $next_url = add_query_arg(array_merge($base_args, ['eocrm_page' => $current_page + 1]), $current_url); ?>
                <a class="eocrm-page-link" href="<?php echo esc_url($next_url); ?>">Nastepna &raquo;</a>
                <?php if ($current_page + 1 < $total_pages) : ?>
                    <?php $last_url = add_query_arg(array_merge($base_args, ['eocrm_page' => $total_pages]), $current_url); ?>
                    <a class="eocrm-page-link eocrm-page-link-last" href="<?php echo esc_url($last_url); ?>">Ostatnia (<?php echo esc_html((string) $total_pages); ?>) &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
