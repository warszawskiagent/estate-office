<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use EstateOffice\Settings\GeneralSettings;

use function esc_html__;
use function get_option;
use function get_post_meta;
use function is_array;
use function plugins_url;
use function rawurlencode;
use function sprintf;
use function trim;
use function wp_enqueue_script;
use function wp_localize_script;
use function wp_register_script;
use function wp_script_is;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

defined('ABSPATH') || exit;

final class Maps
{
    private static ?string $apiKey = null;
    private static bool $localized = false;

    public static function bootstrap(): void
    {
        // No hooks required at bootstrap time yet. The class exposes helper methods on demand.
    }

    public static function hasApiKey(): bool
    {
        return self::getApiKey() !== '';
    }

    public static function enqueue(): bool
    {
        $apiKey = self::getApiKey();

        if ($apiKey === '') {
            return false;
        }

        if (!wp_script_is('estate-office-google-maps', 'registered')) {
            wp_register_script(
                'estate-office-google-maps',
                sprintf('https://maps.googleapis.com/maps/api/js?key=%s&libraries=places', rawurlencode($apiKey)),
                [],
                null,
                true
            );
        }

        if (!wp_script_is('estate-office-maps', 'registered')) {
            wp_register_script(
                'estate-office-maps',
                plugins_url('assets/js/maps.js', ESTATE_OFFICE_PLUGIN_FILE),
                ['estate-office-google-maps'],
                ESTATE_OFFICE_PLUGIN_VERSION,
                true
            );
        }

        if (!self::$localized) {
            wp_localize_script(
                'estate-office-maps',
                'EstateOfficeMaps',
                [
                    'i18n' => [
                        'noCoordinates' => esc_html__('Brak danych lokalizacji do wyświetlenia na mapie.', 'estate-office'),
                        'markerTitle'   => esc_html__('Lokalizacja nieruchomości', 'estate-office'),
                        'viewOnMap'     => esc_html__('Zobacz na Mapach Google', 'estate-office'),
                    ],
                ]
            );

            self::$localized = true;
        }

        wp_enqueue_script('estate-office-google-maps');
        wp_enqueue_script('estate-office-maps');

        return true;
    }

    /**
     * @return array{address:string,lat:float|null,lng:float|null,embed:string,interactive:bool,has_coordinates:bool}
     */
    public static function prepareMapData(int $postId): array
    {
        $latitude  = trim((string) get_post_meta($postId, 'estate_property_latitude', true));
        $longitude = trim((string) get_post_meta($postId, 'estate_property_longitude', true));
        $address   = trim((string) get_post_meta($postId, 'estate_property_map_address', true));

        $hasCoordinates = $latitude !== '' && $longitude !== '';
        $lat            = $hasCoordinates ? (float) $latitude : null;
        $lng            = $hasCoordinates ? (float) $longitude : null;

        $embed = '';
        if ($hasCoordinates) {
            $embed = self::buildEmbedUrl((float) $latitude, (float) $longitude);
        }

        return [
            'address'         => $address,
            'lat'             => $lat,
            'lng'             => $lng,
            'embed'           => $embed,
            'interactive'     => $hasCoordinates && self::hasApiKey(),
            'has_coordinates' => $hasCoordinates,
        ];
    }

    public static function getMissingApiMessage(): string
    {
        return esc_html__('Podgląd mapy wymaga wprowadzenia klucza API Map Google w ustawieniach wtyczki.', 'estate-office');
    }

    private static function buildEmbedUrl(float $lat, float $lng): string
    {
        $formattedLat = self::formatCoordinate($lat);
        $formattedLng = self::formatCoordinate($lng);
        $apiKey       = self::getApiKey();

        if ($apiKey !== '') {
            return sprintf(
                'https://www.google.com/maps/embed/v1/view?key=%s&center=%s,%s&zoom=15&maptype=roadmap',
                rawurlencode($apiKey),
                rawurlencode($formattedLat),
                rawurlencode($formattedLng)
            );
        }

        return sprintf(
            'https://www.google.com/maps?q=%s,%s&z=15&output=embed',
            rawurlencode($formattedLat),
            rawurlencode($formattedLng)
        );
    }

    private static function formatCoordinate(float $value): string
    {
        return sprintf('%.6f', $value);
    }

    private static function getApiKey(): string
    {
        if (self::$apiKey !== null) {
            return self::$apiKey;
        }

        $settings = get_option(GeneralSettings::OPTION);
        $apiKey   = '';

        if (is_array($settings) && !empty($settings['google_maps_api_key'])) {
            $apiKey = (string) $settings['google_maps_api_key'];
        }

        self::$apiKey = trim($apiKey);

        return self::$apiKey;
    }
}
