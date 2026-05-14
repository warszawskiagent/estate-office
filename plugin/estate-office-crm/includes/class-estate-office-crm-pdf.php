<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_PDF
{
    public static function build_property_offer_pdf(array $payload): string
    {
        $title = self::safe_text((string) ($payload['title'] ?? 'Estate Office CRM'));
        if ($title === '') {
            $title = 'Estate Office CRM';
        }
        $offer_number = self::safe_text((string) ($payload['offer_number'] ?? ''));
        $generated_at = self::safe_text((string) ($payload['generated_at'] ?? gmdate('Y-m-d H:i:s') . ' UTC'));

        $modern = self::build_modern_pdf($payload, $title, $offer_number, $generated_at);
        if (is_string($modern) && $modern !== '') {
            return $modern;
        }

        $rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
        $lines = ['Estate Office CRM - Karta oferty'];
        if ($offer_number !== '') {
            $lines[] = 'Numer oferty: ' . $offer_number;
        }
        $lines[] = 'Wygenerowano: ' . $generated_at;
        $lines[] = '';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = self::safe_text((string) ($row['label'] ?? ''));
            $value = self::safe_text((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $lines[] = $label . ': ' . $value;
        }
        return self::build_single_page_text_pdf($title, $lines);
    }

    private static function build_modern_pdf(array $payload, string $title, string $offer_number, string $generated_at): ?string
    {
        $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
        $metrics = self::sanitize_rows($payload['metrics_rows'] ?? []);
        $details = self::sanitize_rows($payload['details_rows'] ?? []);
        $features = self::sanitize_rows($payload['features_rows'] ?? []);
        $amenities_items = self::sanitize_text_items($payload['amenities_items'] ?? []);
        $equipment_items = self::sanitize_text_items($payload['equipment_items'] ?? []);
        $plans_raw = self::sanitize_plans($payload['floor_plans'] ?? []);
        $qr_items_raw = self::sanitize_qr_items($payload['qr_items'] ?? []);
        $owner_contact_rows = self::sanitize_rows($payload['owner_contact_rows'] ?? []);
        $owner_office_logo_url = esc_url_raw((string) ($payload['owner_office_logo_url'] ?? ''));
        $watermark_image_url = esc_url_raw((string) ($payload['watermark_image_url'] ?? ''));
        $show_owner_contact = ! empty($payload['show_owner_contact']) && ! empty($owner_contact_rows);
        $apply_hero_watermark = ! empty($payload['apply_hero_watermark']) && $show_owner_contact && $watermark_image_url !== '';
        $show_metrics_section = ! array_key_exists('show_metrics_section', $payload) || ! empty($payload['show_metrics_section']);
        $show_details_section = ! array_key_exists('show_details_section', $payload) || ! empty($payload['show_details_section']);
        $show_description_section = ! array_key_exists('show_description_section', $payload) || ! empty($payload['show_description_section']);
        $show_plans_section = ! array_key_exists('show_plans_section', $payload) || ! empty($payload['show_plans_section']);
        $show_qr_section = ! array_key_exists('show_qr_section', $payload) || ! empty($payload['show_qr_section']);
        $show_features_section = ! array_key_exists('show_features_section', $payload) || ! empty($payload['show_features_section']);
        $show_offer_link = ! array_key_exists('show_offer_link', $payload) || ! empty($payload['show_offer_link']);
        $offer_public_url = $show_offer_link ? esc_url_raw((string) ($payload['offer_public_url'] ?? '')) : '';
        $desc = self::normalize_multiline_text((string) ($payload['description'] ?? ''));
        if ($desc === '') {
            $desc = 'Brak opisu.';
        }
        if (! $show_description_section) {
            $desc = '';
        }

        $hero_url = esc_url_raw((string) ($payload['hero_image_url'] ?? ''));
        $has_payload = $hero_url !== '' || ! empty($summary) || ! empty($metrics) || ! empty($details) || ! empty($features) || ! empty($amenities_items) || ! empty($equipment_items) || $offer_public_url !== '' || ! empty($plans_raw) || ! empty($qr_items_raw) || $desc !== '';
        if (! $has_payload) {
            return null;
        }

        $address = self::safe_text((string) ($summary['address'] ?? ''));
        if ($address === '') {
            $address = $title;
        }
        $price = self::safe_text((string) ($summary['price'] ?? '-'));
        $location = self::safe_text((string) ($summary['location'] ?? '-'));
        $transaction = self::safe_text((string) ($summary['transaction_type'] ?? 'OFERTA'));
        $ptype = self::safe_text((string) ($summary['property_type'] ?? ''));
        $no = self::safe_text((string) ($summary['offer_number'] ?? $offer_number));

        $img_registry = [];
        $hero = self::register_image($img_registry, $hero_url);
        if ($apply_hero_watermark && is_array($hero)) {
            $watermarked_hero = self::register_watermarked_variant(
                $img_registry,
                $hero,
                $watermark_image_url,
                'hero'
            );
            if (is_array($watermarked_hero)) {
                $hero = $watermarked_hero;
            }
        }
        $plans = [];
        foreach ($plans_raw as $plan) {
            $img = self::register_image($img_registry, (string) ($plan['url'] ?? ''));
            if (! is_array($img)) {
                $img = self::register_image($img_registry, (string) ($plan['fallback_url'] ?? ''));
            }
            $plans[] = [
                'label' => self::safe_text((string) ($plan['label'] ?? 'Rzut')),
                'image' => $img,
            ];
        }
        $owner_office_logo = self::register_image($img_registry, $owner_office_logo_url);
        $qr_items = [];
        if ($show_qr_section) {
            foreach ($qr_items_raw as $qr_item) {
                $qr_url = isset($qr_item['url']) ? (string) $qr_item['url'] : '';
                if ($qr_url === '') {
                    continue;
                }
                $qr_service_url = self::build_qr_service_url($qr_url);
                $qr_image = self::register_image($img_registry, $qr_service_url);
                $qr_items[] = [
                    'label' => self::safe_text((string) ($qr_item['label'] ?? 'Kod QR')),
                    'url' => self::safe_text($qr_url),
                    'image' => $qr_image,
                ];
            }
        }

        $w = 595.0;
        $h = 842.0;
        $m = 28.0;
        $cw = $w - 2 * $m;

        $pages = [['commands' => [], 'images' => [], 'links' => []]];
        $pi = 0;

        $new_page = static function () use (&$pages, &$pi): void {
            $pages[] = ['commands' => [], 'images' => [], 'links' => []];
            $pi = count($pages) - 1;
        };
        $cmd = static function (string $s) use (&$pages, &$pi): void {
            $s = trim($s);
            if ($s !== '') {
                $pages[$pi]['commands'][] = $s;
            }
        };
        $use_img = static function (string $name) use (&$pages, &$pi): void {
            if ($name !== '') {
                $pages[$pi]['images'][$name] = true;
            }
        };
        $draw_rect = static function (float $x, float $y, float $rw, float $rh, array $fill, ?array $stroke = null, float $lw = 0.8) use ($cmd): void {
            if ($rw <= 0 || $rh <= 0) {
                return;
            }
            $s = 'q ' . EstateOfficeCRM_PDF::pdf_color($fill, 'rg');
            if (is_array($stroke)) {
                $s .= ' ' . EstateOfficeCRM_PDF::pdf_color($stroke, 'RG') . ' ' . EstateOfficeCRM_PDF::n($lw) . ' w ';
                $s .= EstateOfficeCRM_PDF::n($x) . ' ' . EstateOfficeCRM_PDF::n($y) . ' ' . EstateOfficeCRM_PDF::n($rw) . ' ' . EstateOfficeCRM_PDF::n($rh) . ' re B Q';
            } else {
                $s .= ' ' . EstateOfficeCRM_PDF::n($x) . ' ' . EstateOfficeCRM_PDF::n($y) . ' ' . EstateOfficeCRM_PDF::n($rw) . ' ' . EstateOfficeCRM_PDF::n($rh) . ' re f Q';
            }
            $cmd($s);
        };
        $draw_line = static function (float $x1, float $y1, float $x2, float $y2, array $stroke = [0.88, 0.9, 0.95], float $lw = 0.8) use ($cmd): void {
            $cmd(
                'q ' . EstateOfficeCRM_PDF::pdf_color($stroke, 'RG') . ' ' . EstateOfficeCRM_PDF::n($lw) . ' w '
                . EstateOfficeCRM_PDF::n($x1) . ' ' . EstateOfficeCRM_PDF::n($y1) . ' m '
                . EstateOfficeCRM_PDF::n($x2) . ' ' . EstateOfficeCRM_PDF::n($y2) . ' l S Q'
            );
        };
        $draw_text = static function (string $text, float $x, float $y, float $fs = 11.0, string $font = 'F1', array $rgb = [0.16, 0.2, 0.27]) use ($cmd): void {
            $text = EstateOfficeCRM_PDF::safe_text($text);
            if ($text === '') {
                return;
            }
            $cmd(
                'BT /' . $font . ' ' . EstateOfficeCRM_PDF::n($fs) . ' Tf ' . EstateOfficeCRM_PDF::pdf_color($rgb, 'rg')
                . ' 1 0 0 1 ' . EstateOfficeCRM_PDF::n($x) . ' ' . EstateOfficeCRM_PDF::n($y) . ' Tm '
                . '(' . EstateOfficeCRM_PDF::pdf_escape($text) . ') Tj ET'
            );
        };
        $draw_img = static function (array $img, float $x, float $y, float $iw, float $ih) use ($cmd, $use_img): void {
            if ($iw <= 0 || $ih <= 0) {
                return;
            }
            $name = isset($img['name']) ? (string) $img['name'] : '';
            if ($name === '') {
                return;
            }
            $use_img($name);
            $cmd('q ' . EstateOfficeCRM_PDF::n($iw) . ' 0 0 ' . EstateOfficeCRM_PDF::n($ih) . ' ' . EstateOfficeCRM_PDF::n($x) . ' ' . EstateOfficeCRM_PDF::n($y) . ' cm /' . $name . ' Do Q');
        };
        $draw_link = static function (float $x, float $y, float $rw, float $rh, string $url) use (&$pages, &$pi): void {
            $url = esc_url_raw(trim($url));
            if ($url === '' || $rw <= 0 || $rh <= 0) {
                return;
            }

            $pages[$pi]['links'][] = [
                'x' => $x,
                'y' => $y,
                'w' => $rw,
                'h' => $rh,
                'url' => $url,
            ];
        };
        $wrap = static function (string $text, float $tw, float $fs, int $max): array {
            return EstateOfficeCRM_PDF::wrap_for_pdf($text, $tw, $fs, $max);
        };
        $wrap_unlimited = static function (string $text, float $tw, float $fs): array {
            $tw = max(40.0, $tw);
            $fs = max(6.0, $fs);
            $chars = max(8, (int) floor($tw / ($fs * 0.52)));
            return EstateOfficeCRM_PDF::wrap_text($text, $chars);
        };
        $card_h = static function (array $rows, float $tw, float $min, float $max) use ($wrap): float {
            if (empty($rows)) {
                return $min;
            }
            $lc = 0;
            $aw = max(60.0, $tw - 20.0);
            foreach ($rows as $row) {
                $t = trim((string) ($row['label'] ?? '') . ': ' . (string) ($row['value'] ?? ''));
                if ($t === '') {
                    continue;
                }
                $lc += max(1, count($wrap($t, $aw, 10.0, 2)));
            }
            $hh = 34.0 + $lc * 12.5 + max(0, count($rows) - 1) * 2.5;
            return min($max, max($min, $hh));
        };
        $render_card = static function (float $x, float $top, float $tw, float $th, string $title_card, array $rows) use ($draw_rect, $draw_text, $wrap): void {
            $b = $top - $th;
            $draw_rect($x, $b, $tw, $th, [1, 1, 1], [0.86, 0.89, 0.94], 0.8);
            $draw_text($title_card, $x + 10.0, $top - 18.0, 11.0, 'F2', [0.09, 0.14, 0.23]);
            $aw = max(60.0, $tw - 20.0);
            $y = $top - 34.0;
            $min_y = $b + 8.0;
            if (empty($rows)) {
                $draw_text('Brak danych.', $x + 10.0, $y, 10.0, 'F1', [0.45, 0.5, 0.57]);
                return;
            }
            foreach ($rows as $row) {
                $label = EstateOfficeCRM_PDF::safe_text((string) ($row['label'] ?? ''));
                $value = EstateOfficeCRM_PDF::safe_text((string) ($row['value'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $lines = $wrap($label . ': ' . $value, $aw, 10.0, 2);
                foreach ($lines as $line) {
                    if ($y < $min_y) {
                        return;
                    }
                    $draw_text($line, $x + 10.0, $y, 10.0, 'F1', [0.16, 0.2, 0.27]);
                    $y -= 12.5;
                }
                $y -= 2.5;
                if ($y < $min_y) {
                    return;
                }
            }
        };
        $list_card_h = static function (array $items, float $tw, float $min, float $max) use ($wrap_unlimited): float {
            if (empty($items)) {
                return $min;
            }
            $aw = max(60.0, $tw - 20.0);
            $lc = 0;
            foreach ($items as $item) {
                $item = EstateOfficeCRM_PDF::safe_text((string) $item);
                if ($item === '') {
                    continue;
                }
                $lines = $wrap_unlimited($item, $aw, 10.0);
                $lc += max(1, count($lines));
            }
            $hh = 34.0 + ($lc * 12.5) + max(0, count($items) - 1) * 2.0;
            return min($max, max($min, $hh));
        };
        $render_list_card = static function (float $x, float $top, float $tw, float $th, string $title_card, array $items) use ($draw_rect, $draw_text, $wrap_unlimited): void {
            $b = $top - $th;
            $draw_rect($x, $b, $tw, $th, [1, 1, 1], [0.86, 0.89, 0.94], 0.8);
            $draw_text($title_card, $x + 10.0, $top - 18.0, 11.0, 'F2', [0.09, 0.14, 0.23]);
            $aw = max(60.0, $tw - 20.0);
            $y = $top - 34.0;
            $min_y = $b + 8.0;
            if (empty($items)) {
                $draw_text('Brak danych.', $x + 10.0, $y, 10.0, 'F1', [0.45, 0.5, 0.57]);
                return;
            }
            foreach ($items as $item) {
                $item = EstateOfficeCRM_PDF::safe_text((string) $item);
                if ($item === '') {
                    continue;
                }
                $lines = $wrap_unlimited($item, $aw, 10.0);
                foreach ($lines as $line) {
                    if ($y < $min_y) {
                        return;
                    }
                    $draw_text('- ' . $line, $x + 10.0, $y, 10.0, 'F1', [0.16, 0.2, 0.27]);
                    $y -= 12.5;
                }
                $y -= 2.0;
                if ($y < $min_y) {
                    return;
                }
            }
        };
        $render_contact_card = static function (float $x, float $top, float $tw, float $th, array $rows, ?array $logo) use ($draw_rect, $draw_text, $wrap, $draw_img): void {
            $b = $top - $th;
            $draw_rect($x, $b, $tw, $th, [1, 1, 1], [0.86, 0.89, 0.94], 0.8);
            $draw_text('Kontakt do opiekuna', $x + 10.0, $top - 18.0, 11.0, 'F2', [0.09, 0.14, 0.23]);

            $inner_x = $x + 10.0;
            $inner_w = max(80.0, $tw - 20.0);
            $content_top = $top - 34.0;
            $content_bottom = $b + 8.0;
            $content_h = max(24.0, $content_top - $content_bottom);
            $logo_area_top = $top - 8.0;
            $logo_area_bottom = $b + 8.0;
            $logo_area_h = max(24.0, $logo_area_top - $logo_area_bottom);

            $logo_gap = 12.0;
            $logo_w = 0.0;
            $left_w = $inner_w;
            $logo_x = 0.0;
            if (is_array($logo)) {
                $logo_w = min(170.0, max(96.0, $inner_w * 0.32));
                if ($inner_w - $logo_w - $logo_gap >= 120.0) {
                    $left_w = $inner_w - $logo_w - $logo_gap;
                    $logo_x = $inner_x + $left_w + $logo_gap;
                } else {
                    $logo_w = 0.0;
                }
            }

            $y = $content_top;
            $min_y = $content_bottom;
            if (empty($rows)) {
                $draw_text('Brak danych.', $inner_x, $y, 10.0, 'F1', [0.45, 0.5, 0.57]);
            } else {
                foreach ($rows as $row) {
                    $label = EstateOfficeCRM_PDF::safe_text((string) ($row['label'] ?? ''));
                    $value = EstateOfficeCRM_PDF::safe_text((string) ($row['value'] ?? ''));
                    if ($label === '' || $value === '') {
                        continue;
                    }
                    $lines = $wrap($label . ': ' . $value, max(60.0, $left_w), 10.0, 2);
                    foreach ($lines as $line) {
                        if ($y < $min_y) {
                            break 2;
                        }
                        $draw_text($line, $inner_x, $y, 10.0, 'F1', [0.16, 0.2, 0.27]);
                        $y -= 12.5;
                    }
                    $y -= 2.5;
                    if ($y < $min_y) {
                        break;
                    }
                }
            }

            if ($logo_w > 0.0 && is_array($logo)) {
                $fit = EstateOfficeCRM_PDF::fit(
                    (float) ($logo['width'] ?? 1.0),
                    (float) ($logo['height'] ?? 1.0),
                    $logo_w,
                    $logo_area_h
                );
                $draw_img(
                    $logo,
                    $logo_x + (($logo_w - $fit['width']) / 2.0),
                    $logo_area_bottom + (($logo_area_h - $fit['height']) / 2.0),
                    $fit['width'],
                    $fit['height']
                );
            }
        };
        $draw_footer = static function () use ($draw_text, $m, $w, $no, $generated_at): void {
            if ($no !== '') {
                $draw_text('Numer oferty: ' . $no, $m, 18.0, 9.0, 'F1', [0.47, 0.53, 0.6]);
            }
            $draw_text('Wygenerowano: ' . $generated_at, $w - $m - 175.0, 18.0, 9.0, 'F1', [0.47, 0.53, 0.6]);
        };
        $start_content_page = static function (string $heading = '') use ($new_page, $draw_text, $m, $h): float {
            $new_page();
            $top = $h - $m;
            if ($heading !== '') {
                $draw_text($heading, $m, $top, 16.0, 'F2', [0.09, 0.14, 0.23]);
                $top -= 26.0;
            }
            return $top;
        };

        $top = $h - $m;
        $draw_text('Estate Office CRM - Karta oferty', $m, $top, 9.5, 'F1', [0.47, 0.53, 0.6]);
        $draw_text('Wygenerowano: ' . $generated_at, $w - $m - 170.0, $top, 9.0, 'F1', [0.47, 0.53, 0.6]);
        $top -= 18.0;

        $hero_w = $w;
        $hero_x = 0.0;
        $hero_h = round($hero_w * 9.0 / 16.0, 2);
        $hero_b = $top - $hero_h;
        if (is_array($hero)) {
            $fit = self::fit((float) ($hero['width'] ?? 1.0), (float) ($hero['height'] ?? 1.0), $hero_w, $hero_h);
            $draw_img($hero, $hero_x + (($hero_w - $fit['width']) / 2.0), $hero_b + (($hero_h - $fit['height']) / 2.0), $fit['width'], $fit['height']);
        } else {
            $draw_rect($hero_x, $hero_b, $hero_w, $hero_h, [0.95, 0.96, 0.98], [0.86, 0.89, 0.94], 0.8);
            $draw_text('Brak zdjecia glownego', $m + 16.0, $hero_b + ($hero_h / 2.0), 12.0, 'F1', [0.47, 0.53, 0.6]);
        }
        $top = $hero_b - 10.0;

        $sum_h = 98.0;
        $sum_b = $top - $sum_h;
        $draw_rect($m, $sum_b, $cw, $sum_h, [0.96, 0.97, 1.0], [0.86, 0.89, 0.94], 0.8);
        $draw_text($transaction !== '' ? $transaction : 'OFERTA', $m + 12.0, $top - 17.0, 10.0, 'F2', [0.17, 0.33, 0.65]);
        $draw_text($address !== '' ? $address : '-', $m + 12.0, $top - 37.0, 16.0, 'F2', [0.1, 0.15, 0.23]);
        $draw_text($price !== '' ? $price : '-', $m + 12.0, $top - 61.0, 22.0, 'F2', [0.05, 0.36, 0.75]);
        $draw_text($location !== '' ? $location : '-', $m + 12.0, $top - 79.0, 10.0, 'F1', [0.23, 0.28, 0.35]);
        if ($ptype !== '') {
            $draw_text('Rodzaj: ' . $ptype, $w - $m - 210.0, $top - 35.0, 10.0, 'F1', [0.23, 0.28, 0.35]);
        }
        if ($no !== '') {
            $draw_text('Numer oferty: ' . $no, $w - $m - 210.0, $top - 51.0, 10.0, 'F1', [0.23, 0.28, 0.35]);
        }
        $top = $sum_b - 10.0;

        if ($show_owner_contact) {
            $owner_h_min = is_array($owner_office_logo) ? 104.0 : 76.0;
            $owner_h_max = is_array($owner_office_logo) ? 176.0 : 136.0;
            $owner_h = $card_h($owner_contact_rows, $cw, $owner_h_min, $owner_h_max);
            if ($top - $owner_h < ($m + 100.0)) {
                $draw_footer();
                $top = $start_content_page('Kontakt do opiekuna');
                $owner_h = min($owner_h_max, max($owner_h_min, $top - ($m + 110.0)));
            }
            $render_contact_card($m, $top, $cw, $owner_h, $owner_contact_rows, is_array($owner_office_logo) ? $owner_office_logo : null);
            $top -= ($owner_h + 10.0);
        }

        $link_rows = [];
        if ($show_offer_link && $offer_public_url !== '') {
            $link_rows[] = ['label' => 'URL', 'value' => $offer_public_url];
        }
        $link_h = ! empty($link_rows) ? max(56.0, $card_h($link_rows, $cw, 56.0, 88.0)) : 0.0;
        $link_rendered = false;

        if ($show_features_section) {
            $features_gap = 12.0;
            $features_col_w = ($cw - $features_gap) / 2.0;
            $amenities_h = $list_card_h($amenities_items, $features_col_w, 84.0, 180.0);
            $equipment_h = $list_card_h($equipment_items, $features_col_w, 84.0, 180.0);
            $features_h = max($amenities_h, $equipment_h);

            $required_h = $features_h + ($link_h > 0 ? ($link_h + 10.0) : 0.0);
            if ($top - $required_h < ($m + 110.0)) {
                $draw_footer();
                $top = $start_content_page('Udogodnienia i wyposazenie');
            }

            $render_list_card($m, $top, $features_col_w, $features_h, 'Udogodnienia', $amenities_items);
            $render_list_card($m + $features_col_w + $features_gap, $top, $features_col_w, $features_h, 'Wyposazenie', $equipment_items);
            $top -= ($features_h + 10.0);

            if ($link_h > 0) {
                if ($top - $link_h < ($m + 80.0)) {
                    $draw_footer();
                    $top = $start_content_page('Udogodnienia i wyposazenie');
                }
                $render_card($m, $top, $cw, $link_h, 'Link do oferty WWW', $link_rows);
                $top -= ($link_h + 10.0);
                $link_rendered = true;
            }
        }

        if (! $link_rendered && $link_h > 0) {
            if ($top - $link_h < ($m + 80.0)) {
                $draw_footer();
                $top = $start_content_page('Dane oferty');
            }
            $render_card($m, $top, $cw, $link_h, 'Link do oferty WWW', $link_rows);
            $top -= ($link_h + 10.0);
        }

        if ($show_metrics_section || $show_details_section) {
            if ($show_metrics_section && $show_details_section) {
                $gap = 12.0;
                $col_w = ($cw - $gap) / 2.0;
                $mh = $card_h($metrics, $col_w, 82.0, 150.0);
                $dh = $card_h($details, $col_w, 82.0, 150.0);
                $ih = max($mh, $dh);
                if ($top - $ih < ($m + 120.0)) {
                    $draw_footer();
                    $top = $start_content_page('Dane oferty');
                }
                $render_card($m, $top, $col_w, $ih, 'Podstawowe informacje', $metrics);
                $render_card($m + $col_w + $gap, $top, $col_w, $ih, 'Szczegoly oferty', $details);
                $top = $top - $ih - 10.0;
            } else {
                $single_rows = $show_metrics_section ? $metrics : $details;
                $single_title = $show_metrics_section ? 'Podstawowe informacje' : 'Szczegoly oferty';
                $single_h = $card_h($single_rows, $cw, 82.0, 190.0);
                if ($top - $single_h < ($m + 120.0)) {
                    $draw_footer();
                    $top = $start_content_page('Dane oferty');
                }
                $render_card($m, $top, $cw, $single_h, $single_title, $single_rows);
                $top = $top - $single_h - 10.0;
            }
        }

        if ($show_description_section) {
            $description_lines = $wrap_unlimited($desc, $cw - 20.0, 10.0);
            if (empty($description_lines)) {
                $description_lines = ['Brak opisu.'];
            }
            $description_index = 0;
            $description_page_index = 0;
            while ($description_index < count($description_lines)) {
                $footer_y = 18.0;
                $minimum_content_h = 76.0;
                if (($top - ($footer_y + 20.0)) < $minimum_content_h) {
                    $draw_footer();
                    $top = $start_content_page();
                }

                $desc_h = max($minimum_content_h, $top - ($footer_y + 16.0));
                $desc_b = $top - $desc_h;
                $draw_rect($m, $desc_b, $cw, $desc_h, [1, 1, 1], [0.86, 0.89, 0.94], 0.8);
                $description_title = $description_page_index === 0 ? 'Opis oferty' : 'Opis oferty (cd.)';
                $draw_text($description_title, $m + 10.0, $top - 18.0, 11.0, 'F2', [0.09, 0.14, 0.23]);

                $yy = $top - 34.0;
                $min_yy = $desc_b + 8.0;
                while ($description_index < count($description_lines) && $yy >= $min_yy) {
                    $draw_text((string) $description_lines[$description_index], $m + 10.0, $yy, 10.0, 'F1', [0.16, 0.2, 0.27]);
                    $description_index++;
                    $yy -= 12.5;
                }

                $draw_footer();
                $description_page_index++;
                $top = $desc_b - 10.0;

                if ($description_index < count($description_lines)) {
                    $top = $start_content_page();
                }
            }
        }

        if ($show_plans_section && ! empty($plans)) {
            $pt = $start_content_page('Rzuty nieruchomosci');
            $plan_pref_h = min(420.0, round($cw * 9.0 / 16.0, 2));

            foreach ($plans as $plan_index => $plan_item) {
                $remaining_h = $pt - ($m + 26.0);
                if ($remaining_h < 280.0) {
                    $draw_footer();
                    $pt = $start_content_page('Rzuty nieruchomosci (cd.)');
                    $remaining_h = $pt - ($m + 26.0);
                }

                $plan_h = min($plan_pref_h, max(230.0, $remaining_h - 24.0));
                $plan_b = $pt - 20.0 - $plan_h;
                if ($plan_b < ($m + 16.0)) {
                    $draw_footer();
                    $pt = $start_content_page('Rzuty nieruchomosci (cd.)');
                    $plan_h = min($plan_pref_h, max(230.0, $pt - ($m + 50.0)));
                    $plan_b = $pt - 20.0 - $plan_h;
                }

                $draw_text((string) ($plan_item['label'] ?? ('Rzut ' . (string) ($plan_index + 1))), $m, $pt - 2.0, 10.5, 'F2', [0.23, 0.28, 0.35]);
                $draw_rect($m, $plan_b, $cw, $plan_h, [0.98, 0.99, 1.0], [0.86, 0.89, 0.94], 0.8);

                $img = isset($plan_item['image']) && is_array($plan_item['image']) ? $plan_item['image'] : null;
                if (is_array($img)) {
                    $fit = self::fit(
                        (float) ($img['width'] ?? 1.0),
                        (float) ($img['height'] ?? 1.0),
                        $cw - 8.0,
                        $plan_h - 8.0
                    );
                    $draw_img(
                        $img,
                        $m + (($cw - $fit['width']) / 2.0),
                        $plan_b + (($plan_h - $fit['height']) / 2.0),
                        $fit['width'],
                        $fit['height']
                    );
                } else {
                    $draw_text('Brak podgladu rzutu (nieobslugiwany format).', $m + 10.0, $plan_b + ($plan_h / 2.0), 10.0, 'F1', [0.47, 0.53, 0.6]);
                }

                $pt = $plan_b - 14.0;
            }

            $draw_footer();
        }

        if ($show_qr_section && ! empty($qr_items)) {
            $qt = $start_content_page('Kody QR');
            $draw_line($m, $qt - 4.0, $w - $m, $qt - 4.0, [0.84, 0.88, 0.94], 0.8);
            $qt -= 20.0;

            $qr_count = count($qr_items);
            $qr_count = max(1, min(3, $qr_count));
            $qr_gap = 12.0;
            $qr_card_w = ($cw - (($qr_count - 1) * $qr_gap)) / $qr_count;
            $qr_card_h = 226.0;

            for ($i = 0; $i < $qr_count; $i++) {
                $item = $qr_items[$i];
                $qx = $m + ($i * ($qr_card_w + $qr_gap));
                $qb = $qt - $qr_card_h;
                $draw_rect($qx, $qb, $qr_card_w, $qr_card_h, [1, 1, 1], [0.86, 0.89, 0.94], 0.8);
                $draw_text((string) ($item['label'] ?? 'Kod QR'), $qx + 10.0, $qt - 16.0, 10.0, 'F2', [0.15, 0.21, 0.31]);

                $img = isset($item['image']) && is_array($item['image']) ? $item['image'] : null;
                $qr_box = min(142.0, $qr_card_w - 20.0);
                $qr_x = $qx + (($qr_card_w - $qr_box) / 2.0);
                $qr_y = $qt - 26.0 - $qr_box;
                $draw_rect($qr_x, $qr_y, $qr_box, $qr_box, [0.98, 0.99, 1.0], [0.9, 0.92, 0.96], 0.6);

                if (is_array($img)) {
                    $fit = self::fit((float) ($img['width'] ?? 1.0), (float) ($img['height'] ?? 1.0), $qr_box - 6.0, $qr_box - 6.0);
                    $draw_img($img, $qr_x + (($qr_box - $fit['width']) / 2.0), $qr_y + (($qr_box - $fit['height']) / 2.0), $fit['width'], $fit['height']);
                } else {
                    $draw_text('Brak obrazu QR', $qr_x + 10.0, $qr_y + ($qr_box / 2.0), 9.0, 'F1', [0.47, 0.53, 0.6]);
                }
                $qr_url_text = isset($item['url']) ? (string) $item['url'] : '';
                if ($qr_url_text !== '') {
                    $draw_link($qr_x, $qr_y, $qr_box, $qr_box, $qr_url_text);
                }
            }
            $draw_footer();
        }

        return self::build_pdf_document($pages, $img_registry);
    }

    private static function sanitize_rows($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = self::safe_text((string) ($row['label'] ?? ''));
            $value = self::safe_text((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '' || $value === '-') {
                continue;
            }
            $out[] = ['label' => $label, 'value' => $value];
        }
        return $out;
    }

    private static function sanitize_text_items($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            $item = self::safe_text((string) $item);
            if ($item !== '' && $item !== '-') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private static function sanitize_plans($items): array
    {
        if (! is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = esc_url_raw((string) ($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $label = self::safe_text((string) ($item['label'] ?? ''));
            if ($label === '') {
                $label = 'Poziom ' . (string) (((int) $i) + 1);
            }
            $fallback_url = esc_url_raw((string) ($item['fallback_url'] ?? ''));
            $out[] = [
                'label' => $label,
                'url' => $url,
                'fallback_url' => $fallback_url,
            ];
        }
        return $out;
    }

    private static function sanitize_qr_items($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = esc_url_raw((string) ($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $label = self::safe_text((string) ($item['label'] ?? 'Kod QR'));
            if ($label === '') {
                $label = 'Kod QR';
            }

            $out[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $out;
    }

    private static function build_qr_service_url(string $target_url): string
    {
        $target_url = esc_url_raw(trim($target_url));
        if ($target_url === '') {
            return '';
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=8&data=' . rawurlencode($target_url);
    }

    private static function register_image(array &$registry, string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (isset($registry[$url]) && is_array($registry[$url])) {
            return $registry[$url];
        }
        $img = self::prepare_image($url);
        if (! is_array($img)) {
            return null;
        }
        $name = 'Im' . (string) (count($registry) + 1);
        $registry[$url] = ['name' => $name, 'bytes' => $img['bytes'], 'width' => (int) $img['width'], 'height' => (int) $img['height']];
        return $registry[$url];
    }

    private static function register_image_from_bytes(array &$registry, string $registry_key, string $bytes): ?array
    {
        $registry_key = trim($registry_key);
        if ($registry_key === '' || $bytes === '') {
            return null;
        }
        if (isset($registry[$registry_key]) && is_array($registry[$registry_key])) {
            return $registry[$registry_key];
        }

        $img = self::prepare_image_bytes($bytes);
        if (! is_array($img)) {
            return null;
        }

        $name = 'Im' . (string) (count($registry) + 1);
        $registry[$registry_key] = ['name' => $name, 'bytes' => $img['bytes'], 'width' => (int) $img['width'], 'height' => (int) $img['height']];
        return $registry[$registry_key];
    }

    private static function register_watermarked_variant(array &$registry, array $base_image, string $watermark_url, string $variant_label = 'hero'): ?array
    {
        $base_bytes = isset($base_image['bytes']) ? (string) $base_image['bytes'] : '';
        if ($base_bytes === '') {
            return null;
        }

        $watermark_bytes = self::fetch_image_bytes($watermark_url);
        if (! is_string($watermark_bytes) || $watermark_bytes === '') {
            return null;
        }

        $variant_bytes = self::apply_watermark_to_image_bytes($base_bytes, $watermark_bytes);
        if (! is_string($variant_bytes) || $variant_bytes === '') {
            return null;
        }

        $base_name = isset($base_image['name']) ? (string) $base_image['name'] : 'img';
        $registry_key = '__wm__' . $variant_label . '__' . md5($base_name . '|' . $watermark_url . '|' . strlen($variant_bytes));

        return self::register_image_from_bytes($registry, $registry_key, $variant_bytes);
    }

    private static function apply_watermark_to_image_bytes(string $base_bytes, string $watermark_bytes): ?string
    {
        if (
            ! function_exists('imagecreatefromstring')
            || ! function_exists('imagecreatetruecolor')
            || ! function_exists('imagecopyresampled')
            || ! function_exists('imagejpeg')
            || ! function_exists('imagesx')
            || ! function_exists('imagesy')
            || ! function_exists('imagecopy')
        ) {
            return null;
        }

        $base = @imagecreatefromstring($base_bytes);
        $watermark = @imagecreatefromstring($watermark_bytes);
        if (! $base || ! $watermark) {
            if ($base) {
                @imagedestroy($base);
            }
            if ($watermark) {
                @imagedestroy($watermark);
            }
            return null;
        }

        $base_w = imagesx($base);
        $base_h = imagesy($base);
        $wm_w = imagesx($watermark);
        $wm_h = imagesy($watermark);
        if ($base_w <= 0 || $base_h <= 0 || $wm_w <= 0 || $wm_h <= 0) {
            @imagedestroy($base);
            @imagedestroy($watermark);
            return null;
        }

        $max_w = max(1, (int) floor($base_w / 6));
        $max_h = max(1, (int) floor($base_h / 6));
        $scale = min(1.0, $max_w / max(1, $wm_w), $max_h / max(1, $wm_h));
        $target_w = max(1, (int) round($wm_w * $scale));
        $target_h = max(1, (int) round($wm_h * $scale));

        $wm_scaled = @imagecreatetruecolor($target_w, $target_h);
        if (! $wm_scaled) {
            @imagedestroy($base);
            @imagedestroy($watermark);
            return null;
        }

        imagealphablending($wm_scaled, false);
        imagesavealpha($wm_scaled, true);
        $transparent = imagecolorallocatealpha($wm_scaled, 0, 0, 0, 127);
        imagefilledrectangle($wm_scaled, 0, 0, $target_w, $target_h, $transparent);
        @imagecopyresampled($wm_scaled, $watermark, 0, 0, 0, 0, $target_w, $target_h, $wm_w, $wm_h);

        if (function_exists('imagefilter') && defined('IMG_FILTER_COLORIZE')) {
            @imagefilter($wm_scaled, IMG_FILTER_COLORIZE, 0, 0, 0, 82);
        }

        imagealphablending($base, true);
        imagesavealpha($base, false);

        $margin = max(8, (int) round(min($base_w, $base_h) * 0.02));
        $pos_x = max(0, $base_w - $target_w - $margin);
        $pos_y = $margin;
        @imagecopy($base, $wm_scaled, $pos_x, $pos_y, 0, 0, $target_w, $target_h);

        ob_start();
        $ok = @imagejpeg($base, null, 86);
        $jpg = ob_get_clean();

        @imagedestroy($wm_scaled);
        @imagedestroy($watermark);
        @imagedestroy($base);

        if (! $ok || ! is_string($jpg) || $jpg === '') {
            return null;
        }

        return $jpg;
    }

    private static function prepare_image(string $url): ?array
    {
        $bytes = self::fetch_image_bytes($url);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return self::prepare_image_bytes($bytes);
    }

    private static function prepare_image_bytes(string $bytes): ?array
    {
        if ($bytes === '') {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if (! is_array($info)) {
            return null;
        }
        $w = isset($info[0]) ? (int) $info[0] : 0;
        $h = isset($info[1]) ? (int) $info[1] : 0;
        if ($w <= 0 || $h <= 0) {
            return null;
        }
        $mime = strtolower((string) ($info['mime'] ?? ''));

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor') || ! function_exists('imagecopyresampled') || ! function_exists('imagejpeg')) {
            return ($mime === 'image/jpeg' || $mime === 'image/jpg') ? ['bytes' => $bytes, 'width' => $w, 'height' => $h] : null;
        }

        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return ($mime === 'image/jpeg' || $mime === 'image/jpg') ? ['bytes' => $bytes, 'width' => $w, 'height' => $h] : null;
        }

        $scale = min(1.0, 1800.0 / max(1, $w), 1800.0 / max(1, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $canvas = @imagecreatetruecolor($tw, $th);
        if (! $canvas) {
            @imagedestroy($src);
            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $tw, $th, $white);
        @imagecopyresampled($canvas, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        if (function_exists('imageinterlace')) {
            @imageinterlace($canvas, false);
        }

        ob_start();
        $ok = @imagejpeg($canvas, null, 86);
        $jpg = ob_get_clean();
        @imagedestroy($canvas);
        @imagedestroy($src);
        if (! $ok || ! is_string($jpg) || $jpg === '') {
            return null;
        }
        return ['bytes' => $jpg, 'width' => $tw, 'height' => $th];
    }

    private static function fetch_image_bytes(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (strpos($url, 'data:image/') === 0) {
            $parts = explode(',', $url, 2);
            if (count($parts) === 2 && strpos($parts[0], ';base64') !== false) {
                $dec = base64_decode($parts[1], true);
                if (is_string($dec) && $dec !== '') {
                    return $dec;
                }
            }
        }

        $url = esc_url_raw($url);
        if ($url === '') {
            return null;
        }

        $uploads = wp_get_upload_dir();
        if (is_array($uploads) && ! empty($uploads['baseurl']) && ! empty($uploads['basedir']) && strpos($url, (string) $uploads['baseurl']) === 0) {
            $rel = ltrim(substr($url, strlen((string) $uploads['baseurl'])), '/');
            $rel = str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $path = rtrim((string) $uploads['basedir'], '\\/') . DIRECTORY_SEPARATOR . $rel;
            if (is_readable($path)) {
                $bin = @file_get_contents($path);
                if (is_string($bin) && $bin !== '') {
                    return $bin;
                }
            }
        }

        if (! wp_http_validate_url($url)) {
            return null;
        }

        $r = wp_remote_get($url, [
            'timeout' => 14,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'image/*,*/*;q=0.8',
                'User-Agent' => 'EstateOfficeCRM/' . (defined('EOCRM_VERSION') ? EOCRM_VERSION : '1.0'),
            ],
        ]);
        if (is_wp_error($r)) {
            return null;
        }
        $st = (int) wp_remote_retrieve_response_code($r);
        if ($st < 200 || $st >= 300) {
            return null;
        }
        $body = wp_remote_retrieve_body($r);
        return is_string($body) && $body !== '' ? $body : null;
    }

    private static function build_pdf_document(array $pages, array $registry): string
    {
        if (empty($pages)) {
            $pages = [['commands' => ['BT /F1 12 Tf 48 780 Td (Brak danych PDF) Tj ET'], 'images' => [], 'links' => []]];
        }

        $catalog = 1;
        $pages_id = 2;
        $encoding = 3;
        $f1 = 4;
        $f2 = 5;
        $next = 6;

        $images = array_values($registry);
        $img_obj = [];
        foreach ($images as $k => $img) {
            $oid = $next++;
            $images[$k]['oid'] = $oid;
            $name = isset($img['name']) ? (string) $img['name'] : '';
            if ($name !== '') {
                $img_obj[$name] = $oid;
            }
        }

        $pentries = [];
        foreach ($pages as $p) {
            $pid = $next++;
            $cid = $next++;
            $links = [];
            $raw_links = isset($p['links']) && is_array($p['links']) ? $p['links'] : [];
            foreach ($raw_links as $raw_link) {
                if (! is_array($raw_link)) {
                    continue;
                }
                $url = esc_url_raw((string) ($raw_link['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $x = (float) ($raw_link['x'] ?? 0.0);
                $y = (float) ($raw_link['y'] ?? 0.0);
                $rw = (float) ($raw_link['w'] ?? 0.0);
                $rh = (float) ($raw_link['h'] ?? 0.0);
                if ($rw <= 0.0 || $rh <= 0.0) {
                    continue;
                }
                $links[] = [
                    'id' => $next++,
                    'url' => $url,
                    'x' => $x,
                    'y' => $y,
                    'w' => $rw,
                    'h' => $rh,
                ];
            }
            $pentries[] = [
                'pid' => $pid,
                'cid' => $cid,
                'cmd' => isset($p['commands']) && is_array($p['commands']) ? $p['commands'] : [],
                'img' => isset($p['images']) && is_array($p['images']) ? array_keys($p['images']) : [],
                'links' => $links,
            ];
        }

        $max = $next - 1;
        $obj = array_fill(0, $max + 1, '');

        $kids = [];
        foreach ($pentries as $p) {
            $kids[] = (string) $p['pid'] . ' 0 R';
        }
        $obj[$catalog] = '<< /Type /Catalog /Pages ' . $pages_id . ' 0 R >>';
        $obj[$pages_id] = '<< /Type /Pages /Count ' . (string) count($pentries) . ' /Kids [' . implode(' ', $kids) . '] >>';
        $obj[$encoding] = '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [128 /Aogonek /aogonek /Cacute /cacute /Eogonek /eogonek /Lslash /lslash /Nacute /nacute /Oacute /oacute /Sacute /sacute /Zacute /zacute /Zdotaccent /zdotaccent] >>';
        $obj[$f1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding ' . $encoding . ' 0 R >>';
        $obj[$f2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding ' . $encoding . ' 0 R >>';

        foreach ($images as $img) {
            $oid = isset($img['oid']) ? (int) $img['oid'] : 0;
            if ($oid <= 0) {
                continue;
            }
            $bytes = isset($img['bytes']) ? (string) $img['bytes'] : '';
            $iw = max(1, (int) ($img['width'] ?? 1));
            $ih = max(1, (int) ($img['height'] ?? 1));
            $obj[$oid] = '<< /Type /XObject /Subtype /Image /Width ' . $iw . ' /Height ' . $ih . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($bytes) . " >>\nstream\n" . $bytes . "\nendstream";
        }

        foreach ($pentries as $p) {
            $pid = (int) ($p['pid'] ?? 0);
            $cid = (int) ($p['cid'] ?? 0);
            if ($pid <= 0 || $cid <= 0) {
                continue;
            }
            $stream = implode("\n", is_array($p['cmd']) ? $p['cmd'] : []) . "\n";
            $obj[$cid] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
            $xobj = [];
            foreach ((array) ($p['img'] ?? []) as $name) {
                $name = trim((string) $name);
                if ($name === '' || ! isset($img_obj[$name])) {
                    continue;
                }
                $xobj[] = '/' . $name . ' ' . (string) $img_obj[$name] . ' 0 R';
            }
            $res = '<< /Font << /F1 ' . $f1 . ' 0 R /F2 ' . $f2 . ' 0 R >>';
            if (! empty($xobj)) {
                $res .= ' /XObject << ' . implode(' ', $xobj) . ' >>';
            }
            $res .= ' >>';

            $annot_refs = [];
            $page_links = isset($p['links']) && is_array($p['links']) ? $p['links'] : [];
            foreach ($page_links as $page_link) {
                if (! is_array($page_link)) {
                    continue;
                }

                $aid = isset($page_link['id']) ? (int) $page_link['id'] : 0;
                $url = isset($page_link['url']) ? trim((string) $page_link['url']) : '';
                $x = isset($page_link['x']) ? (float) $page_link['x'] : 0.0;
                $y = isset($page_link['y']) ? (float) $page_link['y'] : 0.0;
                $rw = isset($page_link['w']) ? (float) $page_link['w'] : 0.0;
                $rh = isset($page_link['h']) ? (float) $page_link['h'] : 0.0;

                if ($aid <= 0 || $url === '' || $rw <= 0.0 || $rh <= 0.0) {
                    continue;
                }

                $x1 = max(0.0, min(595.0, $x));
                $y1 = max(0.0, min(842.0, $y));
                $x2 = max(0.0, min(595.0, $x + $rw));
                $y2 = max(0.0, min(842.0, $y + $rh));
                if ($x2 <= $x1 || $y2 <= $y1) {
                    continue;
                }

                $obj[$aid] = '<< /Type /Annot /Subtype /Link /Rect [' . self::n($x1) . ' ' . self::n($y1) . ' ' . self::n($x2) . ' ' . self::n($y2) . '] /Border [0 0 0] /A << /S /URI /URI (' . self::pdf_escape($url) . ') >> >>';
                $annot_refs[] = (string) $aid . ' 0 R';
            }

            $page_obj = '<< /Type /Page /Parent ' . $pages_id . ' 0 R /MediaBox [0 0 595 842] /Resources ' . $res . ' /Contents ' . $cid . ' 0 R';
            if (! empty($annot_refs)) {
                $page_obj .= ' /Annots [' . implode(' ', $annot_refs) . ']';
            }
            $page_obj .= ' >>';
            $obj[$pid] = $page_obj;
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $off = [0];
        for ($i = 1; $i <= $max; $i++) {
            $body = isset($obj[$i]) ? (string) $obj[$i] : '<< >>';
            $off[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 ' . (string) ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf('%010d 00000 n ', (int) ($off[$i] ?? 0)) . "\n";
        }
        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . (string) ($max + 1) . ' /Root ' . $catalog . " 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= (string) $xref . "\n";
        $pdf .= "%%EOF";
        return $pdf;
    }

    private static function fit(float $iw, float $ih, float $bw, float $bh): array
    {
        $iw = max(1.0, $iw);
        $ih = max(1.0, $ih);
        $bw = max(1.0, $bw);
        $bh = max(1.0, $bh);
        $r = min($bw / $iw, $bh / $ih);
        return ['width' => max(1.0, $iw * $r), 'height' => max(1.0, $ih * $r)];
    }

    private static function wrap_for_pdf(string $text, float $width, float $font_size, int $max_lines): array
    {
        $width = max(40.0, $width);
        $font_size = max(6.0, $font_size);
        $max_lines = max(1, $max_lines);
        $chars = max(8, (int) floor($width / ($font_size * 0.52)));
        $lines = self::wrap_text($text, $chars);
        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, 0, $max_lines);
            $last = $max_lines - 1;
            $lines[$last] = rtrim((string) ($lines[$last] ?? ''), '.') . '...';
        }
        return $lines;
    }

    private static function n(float $v): string
    {
        $s = number_format($v, 3, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return ($s === '' || $s === '-0') ? '0' : $s;
    }

    private static function pdf_color(array $rgb, string $op): string
    {
        $r = isset($rgb[0]) ? max(0.0, min(1.0, (float) $rgb[0])) : 0.0;
        $g = isset($rgb[1]) ? max(0.0, min(1.0, (float) $rgb[1])) : 0.0;
        $b = isset($rgb[2]) ? max(0.0, min(1.0, (float) $rgb[2])) : 0.0;
        return self::n($r) . ' ' . self::n($g) . ' ' . self::n($b) . ' ' . $op;
    }

    private static function build_single_page_text_pdf(string $title, array $lines): string
    {
        $title_lines = self::wrap_text($title, 64);
        $body_lines = [];
        foreach ($lines as $line) {
            $wrapped = self::wrap_text($line, 96);
            foreach ($wrapped as $wrapped_line) {
                $body_lines[] = $wrapped_line;
            }
        }
        if (count($body_lines) > 46) {
            $body_lines = array_slice($body_lines, 0, 46);
            $body_lines[] = '...';
        }

        $stream_parts = ['BT', '/F1 16 Tf', '48 804 Td'];
        foreach ($title_lines as $index => $line) {
            if ($index > 0) {
                $stream_parts[] = '0 -20 Td';
            }
            $stream_parts[] = '(' . self::pdf_escape($line) . ') Tj';
        }
        $stream_parts[] = 'ET';
        $stream_parts[] = '0.86 0.89 0.94 RG';
        $stream_parts[] = '1 w';
        $stream_parts[] = '48 770 m';
        $stream_parts[] = '547 770 l';
        $stream_parts[] = 'S';
        $stream_parts[] = 'BT';
        $stream_parts[] = '/F1 11 Tf';
        $stream_parts[] = '48 752 Td';
        foreach ($body_lines as $index => $line) {
            if ($index > 0) {
                $stream_parts[] = '0 -14 Td';
            }
            $stream_parts[] = '(' . self::pdf_escape($line) . ') Tj';
        }
        $stream_parts[] = 'ET';
        $stream = implode("\n", $stream_parts) . "\n";

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Count 1 /Kids [3 0 R] >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $object_id = $index + 1;
            $offsets[$object_id] = strlen($pdf);
            $pdf .= $object_id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref_offset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 ' . (string) (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($object_id = 1; $object_id <= count($objects); $object_id++) {
            $pdf .= sprintf('%010d 00000 n ', (int) ($offsets[$object_id] ?? 0)) . "\n";
        }
        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . (string) (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n";
        $pdf .= (string) $xref_offset . "\n";
        $pdf .= "%%EOF";
        return $pdf;
    }

    private static function wrap_text(string $text, int $max_length): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/\s+/', $text);
        if (! is_array($words)) {
            return [$text];
        }
        $lines = [];
        $current_line = '';
        foreach ($words as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }
            $candidate = $current_line === '' ? $word : ($current_line . ' ' . $word);
            if (strlen($candidate) <= $max_length) {
                $current_line = $candidate;
                continue;
            }
            if ($current_line !== '') {
                $lines[] = $current_line;
            }
            if (strlen($word) > $max_length) {
                $lines[] = substr($word, 0, $max_length);
                $current_line = '';
            } else {
                $current_line = $word;
            }
        }
        if ($current_line !== '') {
            $lines[] = $current_line;
        }
        return empty($lines) ? [''] : $lines;
    }

    private static function normalize_multiline_text(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/<\s*br\s*\/?>/iu', "\n", $text);
        $text = (string) preg_replace('/<\/p>/iu', "\n\n", $text);
        $text = (string) preg_replace('/<\/li>/iu', "\n", $text);
        $text = wp_strip_all_tags($text, true);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);

        $lines = explode("\n", $text);
        $normalized = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                $normalized[] = '';
                continue;
            }
            $normalized[] = $line;
        }

        return trim(implode("\n", $normalized));
    }

    private static function safe_text(string $text): string
    {
        $text = wp_strip_all_tags($text, true);
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        if (! is_string($text)) {
            return '';
        }

        $polish_map = [
            'Ą' => chr(128),
            'ą' => chr(129),
            'Ć' => chr(130),
            'ć' => chr(131),
            'Ę' => chr(132),
            'ę' => chr(133),
            'Ł' => chr(134),
            'ł' => chr(135),
            'Ń' => chr(136),
            'ń' => chr(137),
            'Ó' => chr(138),
            'ó' => chr(139),
            'Ś' => chr(140),
            'ś' => chr(141),
            'Ź' => chr(142),
            'ź' => chr(143),
            'Ż' => chr(144),
            'ż' => chr(145),
        ];

        $text = (string) preg_replace_callback('/./u', static function (array $match) use ($polish_map): string {
            $char = isset($match[0]) ? (string) $match[0] : '';
            if ($char === '') {
                return '';
            }

            if (isset($polish_map[$char])) {
                return $polish_map[$char];
            }

            if (strlen($char) === 1) {
                $ord = ord($char);
                if ($ord >= 32 && $ord <= 126) {
                    return $char;
                }
            }

            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $char);
            if (! is_string($converted) || $converted === '') {
                return '';
            }

            $converted = preg_replace('/[^\x20-\x7E]/', '', $converted);
            if (! is_string($converted)) {
                return '';
            }

            return $converted;
        }, $text);

        $text = preg_replace('/[^\x20-\x7E\x80-\x91]/', '', $text);
        return is_string($text) ? trim($text) : '';
    }

    private static function pdf_escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return $text;
    }
}
