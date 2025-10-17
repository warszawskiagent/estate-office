<?php
/**
 * Menedżer eksportów na portale dla EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Class Estate_Office_Portal_Export_Manager
 */
class Estate_Office_Portal_Export_Manager {

    private const OPTION_KEY = 'estate_office_portal_exports';

    private const CRON_HOOK = 'estate_office_generate_portal_exports';

    /**
     * Repozytorium nieruchomości.
     *
     * @var Estate_Office_Property_Repository
     */
    private Estate_Office_Property_Repository $properties;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Property_Repository|null $properties Opcjonalne repozytorium.
     */
    public function __construct( ?Estate_Office_Property_Repository $properties = null ) {
        $this->properties = $properties ?? new Estate_Office_Property_Repository();
    }

    /**
     * Rejestruje hooki obsługujące eksporty.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( self::CRON_HOOK, [ $this, 'generate_exports' ] );
        add_filter( 'query_vars', [ $this, 'register_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_feed' ] );
    }

    /**
     * Harmonogramuje cykliczne eksporty.
     *
     * @return void
     */
    public function schedule_events() : void {
        $schedule  = $this->get_schedule();
        $schedules = wp_get_schedules();

        if ( ! isset( $schedules[ $schedule ] ) ) {
            $schedule = 'twicedaily';
        }

        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( false === $timestamp ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, self::CRON_HOOK );
            return;
        }

        $current_schedule = wp_get_schedule( self::CRON_HOOK );
        if ( $current_schedule !== $schedule ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, self::CRON_HOOK );
        }
    }

    /**
     * Czyści zaplanowane eksporty.
     *
     * @return void
     */
    public function clear_scheduled_events() : void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        while ( false !== $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
    }

    /**
     * Rejestruje zmienną zapytania dla feedów eksportowych.
     *
     * @param array<int,string> $vars Lista zmiennych.
     *
     * @return array<int,string>
     */
    public function register_query_var( array $vars ) : array {
        $vars[] = 'estate_office_export';

        return $vars;
    }

    /**
     * Obsługuje żądanie feedu eksportowego.
     *
     * @return void
     */
    public function maybe_render_feed() : void {
        $slug = get_query_var( 'estate_office_export' );
        if ( empty( $slug ) ) {
            return;
        }

        $slug     = sanitize_key( (string) $slug );
        $settings = $this->get_settings();
        $portals  = $settings['portals'] ?? [];

        if ( ! isset( $portals[ $slug ] ) ) {
            $this->render_feed_error( 404, __( 'Nie znaleziono konfiguracji eksportu.', 'estate-office' ) );
        }

        $portal = $this->ensure_portal_defaults( $slug, $portals[ $slug ] );
        $token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['token'] ) ) : '';

        if ( empty( $portal['token'] ) || ! hash_equals( $portal['token'], $token ) ) {
            $this->render_feed_error( 403, __( 'Niepoprawny token eksportu.', 'estate-office' ) );
        }

        $content      = '';
        $content_type = $this->resolve_content_type( $portal );

        if ( ! empty( $portal['file_path'] ) && file_exists( $portal['file_path'] ) ) {
            $content = (string) file_get_contents( $portal['file_path'] );
        }

        if ( '' === $content ) {
            $this->generate_exports( $slug );
            $settings = $this->get_settings();
            $portal   = $this->ensure_portal_defaults( $slug, $settings['portals'][ $slug ] ?? [] );
            if ( ! empty( $portal['file_path'] ) && file_exists( $portal['file_path'] ) ) {
                $content      = (string) file_get_contents( $portal['file_path'] );
                $content_type = $this->resolve_content_type( $portal );
            }
        }

        if ( '' === $content ) {
            $this->render_feed_error( 204, __( 'Brak danych do eksportu.', 'estate-office' ) );
        }

        nocache_headers();
        header( 'Content-Type: ' . $content_type . '; charset=utf-8' );
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Uruchamia generowanie eksportów.
     *
     * @param string|null $portal_slug Opcjonalny slug portalu.
     *
     * @return void
     */
    public function generate_exports( ?string $portal_slug = null ) : void {
        $settings = $this->get_settings();
        $portals  = $settings['portals'] ?? [];

        if ( empty( $portals ) ) {
            return;
        }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            Estate_Office_Plugin::log_debug( 'Błąd katalogu eksportu', [ 'error' => $uploads['error'] ] );
            return;
        }

        $base_path = trailingslashit( $uploads['basedir'] ) . 'estate-office/exports';
        $base_url  = trailingslashit( $uploads['baseurl'] ) . 'estate-office/exports';

        if ( ! wp_mkdir_p( $base_path ) ) {
            Estate_Office_Plugin::log_debug( 'Nie można utworzyć katalogu eksportów.', [ 'path' => $base_path ] );
            return;
        }

        $now = current_time( 'mysql' );

        foreach ( $portals as $slug => $portal ) {
            if ( null !== $portal_slug && $slug !== $portal_slug ) {
                continue;
            }

            $portal = $this->ensure_portal_defaults( $slug, $portal );

            if ( empty( $portal['enabled'] ) ) {
                $portals[ $slug ] = $portal;
                continue;
            }

            try {
                $filters    = [
                    'transaction_types' => $portal['transaction_types'] ?? [],
                    'property_types'    => $portal['property_types'] ?? [],
                    'cities'            => $portal['cities'] ?? [],
                    'districts'         => $portal['districts'] ?? [],
                ];
                $properties = $this->properties->get_portal_export_payload( $filters );
                $payload    = [
                    'generated_at' => $now,
                    'portal'       => [
                        'slug'  => $slug,
                        'name'  => $portal['name'],
                        'format'=> $portal['format'],
                    ],
                    'total'        => count( $properties ),
                    'properties'   => $properties,
                ];

                if ( 'xml' === $portal['format'] ) {
                    $content    = $this->convert_payload_to_xml( $payload );
                    $extension  = 'xml';
                    $mime_type  = 'application/xml';
                    if ( '' === $content ) {
                        throw new RuntimeException( __( 'Nie udało się zbudować pliku XML eksportu.', 'estate-office' ) );
                    }
                } else {
                    $content    = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                    $extension  = 'json';
                    $mime_type  = 'application/json';
                    if ( false === $content ) {
                        throw new RuntimeException( __( 'Nie udało się zakodować danych eksportu.', 'estate-office' ) );
                    }
                }

                $filename = $slug . '.' . $extension;
                $path     = trailingslashit( $base_path ) . $filename;

                if ( false === file_put_contents( $path, $content ) ) {
                    throw new RuntimeException( __( 'Nie udało się zapisać pliku eksportu.', 'estate-office' ) );
                }

                $portal['last_generated'] = $now;
                $portal['last_total']     = count( $properties );
                $portal['last_result']    = 'success';
                $portal['last_message']   = '';
                $portal['file_url']       = trailingslashit( $base_url ) . $filename;
                $portal['file_path']      = $path;
                $portal['content_type']   = $mime_type;
            } catch ( Throwable $throwable ) {
                $portal['last_generated'] = $now;
                $portal['last_result']    = 'error';
                $portal['last_message']   = $throwable->getMessage();
                Estate_Office_Plugin::log_debug(
                    'Błąd eksportu portalu',
                    [
                        'portal' => $slug,
                        'error'  => $throwable->getMessage(),
                    ]
                );
            }

            $portals[ $slug ] = $this->ensure_portal_defaults( $slug, $portal );
        }

        $settings['portals'] = $portals;
        update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Zwraca konfigurację portali.
     *
     * @return array<string,mixed>
     */
    public function get_settings() : array {
        $defaults = [
            'schedule' => 'twicedaily',
            'portals'  => [],
        ];

        $settings = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Zwraca listę portali wraz z domyślnymi wartościami.
     *
     * @return array<string,array<string,mixed>>
     */
    public function get_portals() : array {
        $settings = $this->get_settings();
        $portals  = [];

        foreach ( $settings['portals'] as $slug => $portal ) {
            $portals[ $slug ] = $this->ensure_portal_defaults( $slug, $portal );
        }

        return $portals;
    }

    /**
     * Zwraca pojedynczą konfigurację portalu.
     *
     * @param string $slug Slug portalu.
     *
     * @return array<string,mixed>|null
     */
    public function get_portal( string $slug ) : ?array {
        $slug    = sanitize_key( $slug );
        $portals = $this->get_portals();

        return $portals[ $slug ] ?? null;
    }

    /**
     * Zapisuje konfigurację portalu.
     *
     * @param array<string,mixed> $data Dane formularza.
     *
     * @return void
     */
    public function save_portal( array $data ) : void {
        $settings = $this->get_settings();
        $portals  = $settings['portals'];

        $name  = sanitize_text_field( $data['name'] ?? '' );
        $slug  = sanitize_key( $data['slug'] ?? sanitize_title( $name ) );
        $slug  = $slug ?: sanitize_key( uniqid( 'portal_', true ) );
        $format = in_array( $data['format'] ?? 'json', [ 'json', 'xml' ], true ) ? $data['format'] : 'json';

        $portal = $portals[ $slug ] ?? [];

        $portal['name']               = $name ?: $slug;
        $portal['slug']               = $slug;
        $portal['format']             = $format;
        $portal['enabled']            = ! empty( $data['enabled'] );
        $portal['transaction_types']  = $this->sanitize_string_list( $data['transaction_types'] ?? [] );
        $portal['property_types']     = $this->sanitize_string_list( $data['property_types'] ?? [] );
        $portal['cities']             = $this->sanitize_string_list( $data['cities'] ?? [] );
        $portal['districts']          = $this->sanitize_string_list( $data['districts'] ?? [] );
        $portal['token']              = $portal['token'] ?? wp_generate_password( 32, false );

        $portals[ $slug ] = $this->ensure_portal_defaults( $slug, $portal );
        $settings['portals'] = $portals;

        update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Usuwa portal.
     *
     * @param string $slug Slug portalu.
     *
     * @return void
     */
    public function delete_portal( string $slug ) : void {
        $settings = $this->get_settings();
        $slug     = sanitize_key( $slug );

        if ( isset( $settings['portals'][ $slug ] ) ) {
            unset( $settings['portals'][ $slug ] );
            update_option( self::OPTION_KEY, $settings );
        }
    }

    /**
     * Aktualizuje harmonogram.
     *
     * @param string $schedule Nowe ustawienie.
     *
     * @return void
     */
    public function update_schedule( string $schedule ) : void {
        $schedule  = sanitize_key( $schedule );
        $settings  = $this->get_settings();
        $schedules = wp_get_schedules();

        if ( ! isset( $schedules[ $schedule ] ) ) {
            $schedule = 'twicedaily';
        }

        $settings['schedule'] = $schedule;
        update_option( self::OPTION_KEY, $settings );
        $this->schedule_events();
    }

    /**
     * Zwraca aktualny harmonogram eksportów.
     *
     * @return string
     */
    public function get_schedule() : string {
        $settings = $this->get_settings();

        return isset( $settings['schedule'] ) ? (string) $settings['schedule'] : 'twicedaily';
    }

    /**
     * Zwraca URL feedu eksportowego.
     *
     * @param string $slug Slug portalu.
     *
     * @return string|null
     */
    public function get_feed_url( string $slug ) : ?string {
        $portal = $this->get_portal( $slug );

        if ( null === $portal ) {
            return null;
        }

        return add_query_arg(
            [
                'estate_office_export' => $portal['slug'],
                'token'                => $portal['token'],
            ],
            home_url( '/' )
        );
    }

    /**
     * Zwraca dane eksportowe.
     *
     * @param array<string,mixed> $filters Filtry portalu.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_export_data( array $filters = [] ) : array {
        return $this->properties->get_portal_export_payload( $filters );
    }

    /**
     * Zapewnia domyślne wartości konfiguracji portalu.
     *
     * @param string               $slug   Slug portalu.
     * @param array<string,mixed>  $portal Konfiguracja.
     *
     * @return array<string,mixed>
     */
    private function ensure_portal_defaults( string $slug, array $portal ) : array {
        $portal['slug']    = sanitize_key( $portal['slug'] ?? $slug );
        $portal['name']    = sanitize_text_field( $portal['name'] ?? $portal['slug'] );
        $portal['format']  = in_array( $portal['format'] ?? 'json', [ 'json', 'xml' ], true ) ? $portal['format'] : 'json';
        $portal['enabled'] = ! empty( $portal['enabled'] );
        $portal['token']   = ! empty( $portal['token'] ) ? sanitize_text_field( $portal['token'] ) : wp_generate_password( 32, false );

        foreach ( [ 'transaction_types', 'property_types', 'cities', 'districts' ] as $list_key ) {
            $portal[ $list_key ] = $this->sanitize_string_list( $portal[ $list_key ] ?? [] );
        }

        $portal['last_generated'] = isset( $portal['last_generated'] ) ? sanitize_text_field( (string) $portal['last_generated'] ) : '';
        $portal['last_total']     = isset( $portal['last_total'] ) ? (int) $portal['last_total'] : 0;
        $portal['last_result']    = isset( $portal['last_result'] ) ? sanitize_key( (string) $portal['last_result'] ) : '';
        $portal['last_message']   = isset( $portal['last_message'] ) ? sanitize_text_field( (string) $portal['last_message'] ) : '';
        $portal['file_url']       = isset( $portal['file_url'] ) ? esc_url_raw( (string) $portal['file_url'] ) : '';
        $portal['file_path']      = isset( $portal['file_path'] ) ? (string) $portal['file_path'] : '';
        $portal['content_type']   = isset( $portal['content_type'] ) ? sanitize_text_field( (string) $portal['content_type'] ) : $this->resolve_content_type( $portal );

        return $portal;
    }

    /**
     * Czyści i normalizuje listę wartości tekstowych.
     *
     * @param mixed $values Wartości wejściowe.
     *
     * @return array<int,string>
     */
    private function sanitize_string_list( $values ) : array {
        if ( is_string( $values ) ) {
            $values = preg_split( '/[\r\n,]+/', $values );
        }

        if ( ! is_array( $values ) ) {
            return [];
        }

        $clean = [];
        foreach ( $values as $value ) {
            $value = sanitize_text_field( (string) $value );
            if ( '' !== $value ) {
                $clean[] = $value;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Konwertuje payload na XML.
     *
     * @param array<string,mixed> $payload Dane.
     *
     * @return string
     */
    private function convert_payload_to_xml( array $payload ) : string {
        $xml = new SimpleXMLElement( '<estateOfficeExport/>' );
        $xml->addAttribute( 'generated_at', (string) ( $payload['generated_at'] ?? '' ) );
        $xml->addAttribute( 'portal', (string) ( $payload['portal']['name'] ?? '' ) );

        foreach ( $payload['properties'] as $property ) {
            $node = $xml->addChild( 'property' );
            $this->append_property_to_xml( $node, $property );
        }

        return $xml->asXML() ?: '';
    }

    /**
     * Dodaje pojedynczą nieruchomość do węzła XML.
     *
     * @param SimpleXMLElement          $node     Węzeł XML.
     * @param array<string,mixed>       $property Dane nieruchomości.
     *
     * @return void
     */
    private function append_property_to_xml( SimpleXMLElement $node, array $property ) : void {
        foreach ( [ 'id', 'listing_number', 'title', 'transaction_type', 'property_type', 'description', 'updated_at' ] as $field ) {
            if ( isset( $property[ $field ] ) ) {
                $node->addChild( $field, htmlspecialchars( (string) $property[ $field ], ENT_XML1 | ENT_COMPAT, 'UTF-8' ) );
            }
        }

        if ( isset( $property['pricing'] ) && is_array( $property['pricing'] ) ) {
            $pricing = $node->addChild( 'pricing' );
            if ( isset( $property['pricing']['amount'] ) ) {
                $price = $pricing->addChild( 'amount', (string) $property['pricing']['amount'] );
                if ( isset( $property['pricing']['currency'] ) ) {
                    $price->addAttribute( 'currency', (string) $property['pricing']['currency'] );
                }
                if ( isset( $property['pricing']['period'] ) && '' !== $property['pricing']['period'] ) {
                    $price->addAttribute( 'period', (string) $property['pricing']['period'] );
                }
            }
            if ( isset( $property['pricing']['administrative_rent'] ) && '' !== $property['pricing']['administrative_rent'] ) {
                $pricing->addChild( 'administrative_rent', (string) $property['pricing']['administrative_rent'] );
            }
        }

        if ( isset( $property['address'] ) && is_array( $property['address'] ) ) {
            $address = $node->addChild( 'address' );
            foreach ( $property['address'] as $key => $value ) {
                if ( '' !== $value ) {
                    $address->addChild( $key, htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' ) );
                }
            }
        }

        if ( isset( $property['dimensions'] ) && is_array( $property['dimensions'] ) ) {
            $dimensions = $node->addChild( 'dimensions' );
            foreach ( $property['dimensions'] as $key => $value ) {
                if ( '' !== $value && null !== $value ) {
                    $dimensions->addChild( $key, (string) $value );
                }
            }
        }

        foreach ( [ 'media', 'amenities', 'equipment', 'additional_areas', 'gallery', 'labels' ] as $list_key ) {
            if ( empty( $property[ $list_key ] ) || ! is_array( $property[ $list_key ] ) ) {
                continue;
            }

            $list_node = $node->addChild( $list_key );
            foreach ( $property[ $list_key ] as $item ) {
                if ( is_array( $item ) ) {
                    $item_node = $list_node->addChild( 'item' );
                    foreach ( $item as $item_key => $item_value ) {
                        if ( '' !== $item_value ) {
                            $item_node->addChild( $item_key, htmlspecialchars( (string) $item_value, ENT_XML1 | ENT_COMPAT, 'UTF-8' ) );
                        }
                    }
                } else {
                    $list_node->addChild( 'item', htmlspecialchars( (string) $item, ENT_XML1 | ENT_COMPAT, 'UTF-8' ) );
                }
            }
        }

        if ( isset( $property['flags'] ) && is_array( $property['flags'] ) ) {
            $flags = $node->addChild( 'flags' );
            foreach ( $property['flags'] as $flag => $value ) {
                $flag_node = $flags->addChild( $flag, $value ? '1' : '0' );
                if ( 'new_offer' === $flag && ! empty( $property['flags_meta']['new_offer_until'] ) ) {
                    $flag_node->addAttribute( 'until', (string) $property['flags_meta']['new_offer_until'] );
                }
            }
        }

        if ( isset( $property['media_links'] ) && is_array( $property['media_links'] ) ) {
            $links = $node->addChild( 'media_links' );
            foreach ( $property['media_links'] as $key => $value ) {
                if ( '' !== $value ) {
                    $links->addChild( $key, htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' ) );
                }
            }
        }
    }

    /**
     * Zwraca typ MIME na podstawie formatu.
     *
     * @param array<string,mixed> $portal Konfiguracja.
     *
     * @return string
     */
    private function resolve_content_type( array $portal ) : string {
        return ( isset( $portal['format'] ) && 'xml' === $portal['format'] ) ? 'application/xml' : 'application/json';
    }

    /**
     * Renderuje odpowiedź błędu feedu.
     *
     * @param int    $status  Kod statusu.
     * @param string $message Wiadomość.
     *
     * @return void
     */
    private function render_feed_error( int $status, string $message ) : void {
        status_header( $status );
        wp_die( esc_html( $message ), '', [ 'response' => $status ] );
    }
}
