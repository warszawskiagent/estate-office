<?php
/**
 * Portal export manager handling queues, cron and adapters.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/Portals/class-estateoffice-portal-adapter.php';
require_once ESTATE_OFFICE_PATH . 'includes/Portals/class-estateoffice-portal-file-adapter.php';

class EstateOffice_Portal_Manager {

    public const CRON_HOOK = 'estate_office_portal_export';

    protected const CRON_INTERVAL = 'estate_office_five_minutes';

    protected const MAX_ATTEMPTS = 5;

    protected const RETRY_DELAY = 15 * MINUTE_IN_SECONDS;

    protected const MAX_RETRY_DELAY = 3 * HOUR_IN_SECONDS;

    /**
     * Cached adapter instances by portal slug.
     *
     * @var array<string,EstateOffice_Portal_Adapter>
     */
    protected static $adapters = [];

    /**
     * Register hooks used by the portal manager.
     */
    public static function hooks(): void {
        add_filter( 'cron_schedules', [ __CLASS__, 'register_schedule' ] );
        add_action( 'init', [ __CLASS__, 'ensure_schedule' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'process_queue' ] );
        add_action( 'estate_office_property_saved', [ __CLASS__, 'handle_property_saved' ], 10, 3 );
        add_action( 'estate_office_property_deleted', [ __CLASS__, 'handle_property_deleted' ] );
    }

    /**
     * Register custom cron interval for queued exports.
     */
    public static function register_schedule( array $schedules ): array {
        if ( ! isset( $schedules[ self::CRON_INTERVAL ] ) ) {
            $schedules[ self::CRON_INTERVAL ] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Co 5 minut (EstateOffice)', 'estate-office' ),
            ];
        }

        return $schedules;
    }

    /**
     * Ensure cron event exists when exports are enabled.
     */
    public static function ensure_schedule( bool $force = false ): void {
        if ( ! $force && ! self::has_pending_queue() ) {
            self::clear_schedule();
            return;
        }

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::CRON_HOOK );
        }
    }

    /**
     * Handle property save hook to sync queue records.
     *
     * @param int                   $property_id     Property identifier.
     * @param array<string,mixed>   $record          Persisted record fields.
     * @param array<int,string>     $portal_slugs    Selected portal slugs.
     */
    public static function handle_property_saved( int $property_id, array $record, array $portal_slugs ): void {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return;
        }

        $export_enabled = ! empty( $record['export_portals'] );
        self::sync_queue_for_property( $property_id, $portal_slugs, $export_enabled );

        if ( $export_enabled && ! empty( $portal_slugs ) ) {
            self::ensure_schedule();
        }
    }

    /**
     * Clean queue and logs when property is removed.
     */
    public static function handle_property_deleted( int $property_id ): void {
        global $wpdb;

        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return;
        }

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $logs_table  = $wpdb->prefix . 'eo_portal_logs';

        $wpdb->delete( $queue_table, [ 'property_id' => $property_id ], [ '%d' ] );
        $wpdb->delete( $logs_table, [ 'property_id' => $property_id ], [ '%d' ] );

        self::maybe_clear_schedule();
    }

    /**
     * Unschedule cron hook.
     */
    protected static function clear_schedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        while ( false !== $timestamp ) {
            wp_unschedule_event( (int) $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
    }

    /**
     * Queue processing triggered by cron.
     */
    public static function process_queue( int $limit = 5 ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );
        $limit       = max( 1, (int) $limit );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$queue_table} WHERE status IN ('pending','retry','throttled') AND (scheduled_at IS NULL OR scheduled_at <= %s) ORDER BY COALESCE(scheduled_at, created_at) ASC, id ASC LIMIT %d",
                $now,
                $limit
            )
        );

        if ( empty( $rows ) ) {
            return;
        }

        foreach ( $rows as $row ) {
            self::process_queue_row( $row );
        }

        if ( self::has_pending_queue() ) {
            self::ensure_schedule( true );
        } else {
            self::clear_schedule();
        }
    }

    /**
     * Retry specific queue item manually.
     */
    public static function retry_queue_item( int $queue_id ): bool {
        global $wpdb;

        $queue_id = absint( $queue_id );
        if ( ! $queue_id ) {
            return false;
        }

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );

        $updated = $wpdb->update(
            $queue_table,
            [
                'status'       => 'pending',
                'attempts'     => 0,
                'last_error'   => null,
                'scheduled_at' => $now,
                'processed_at' => null,
                'updated_at'   => $now,
            ],
            [ 'id' => $queue_id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false !== $updated ) {
            self::ensure_schedule();
        }

        return false !== $updated;
    }

    /**
     * Force processing of specific queue item.
     */
    public static function process_queue_item( int $queue_id ): bool {
        global $wpdb;

        $queue_id = absint( $queue_id );
        if ( ! $queue_id ) {
            return false;
        }

        $queue_table = $wpdb->prefix . 'eo_portal_queue';

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$queue_table} WHERE id = %d", $queue_id )
        );

        if ( ! $row ) {
            return false;
        }

        self::process_queue_row( $row );

        if ( self::has_pending_queue() ) {
            self::ensure_schedule( true );
        } else {
            self::clear_schedule();
        }

        return true;
    }

    /**
     * Return queue entries for admin listing.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_queue_items( string $status = '' ): array {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $portals     = $wpdb->prefix . 'eo_portals';
        $properties  = $wpdb->prefix . 'eo_properties';
        $logs_table  = $wpdb->prefix . 'eo_portal_logs';

        $where  = 'WHERE 1=1';
        $params = [];

        if ( '' !== $status ) {
            $where   .= ' AND q.status = %s';
            $params[] = sanitize_text_field( $status );
        }

        $sql = "SELECT q.*, p.name AS portal_name, p.slug AS portal_slug, p.is_enabled, prop.transaction_type, prop.property_type, prop.export_portals, prop.contract_id,
                    log.status AS last_log_status, log.message AS last_log_message, log.created_at AS last_log_created_at
                FROM {$queue_table} q
                INNER JOIN {$portals} p ON p.id = q.portal_id
                LEFT JOIN {$properties} prop ON prop.id = q.property_id
                LEFT JOIN (
                    SELECT l1.* FROM {$logs_table} l1
                    INNER JOIN (
                        SELECT queue_id, MAX(id) AS max_id FROM {$logs_table} GROUP BY queue_id
                    ) latest ON latest.queue_id = l1.queue_id AND latest.max_id = l1.id
                ) log ON log.queue_id = q.id
                {$where}
                ORDER BY q.updated_at DESC, q.id DESC
                LIMIT 100";

        $prepared = $params ? $wpdb->prepare( $sql, ...$params ) : $sql;
        $results  = $wpdb->get_results( $prepared, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Retrieve queue statuses for given property.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_property_statuses( int $property_id ): array {
        global $wpdb;

        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return [];
        }

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $portals     = $wpdb->prefix . 'eo_portals';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.*, p.name AS portal_name, p.slug AS portal_slug FROM {$queue_table} q INNER JOIN {$portals} p ON p.id = q.portal_id WHERE q.property_id = %d ORDER BY p.name ASC",
                $property_id
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Retrieve logs for queue entry.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_logs_for_queue( int $queue_id ): array {
        global $wpdb;

        $queue_id   = absint( $queue_id );
        $logs_table = $wpdb->prefix . 'eo_portal_logs';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$logs_table} WHERE queue_id = %d ORDER BY id DESC LIMIT 20",
                $queue_id
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Determine if pending items remain in the queue.
     */
    protected static function has_pending_queue(): bool {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$queue_table} WHERE status IN ('pending','retry','throttled')" );

        return $count > 0;
    }

    /**
     * Unschedule cron if no pending items remain.
     */
    protected static function maybe_clear_schedule(): void {
        if ( ! self::has_pending_queue() ) {
            self::clear_schedule();
        }
    }

    /**
     * Process a single queue row.
     *
     * @param object $row Queue record.
     */
    protected static function process_queue_row( $row ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );
        $queue_id    = (int) $row->id;

        $wpdb->update(
            $queue_table,
            [
                'status'     => 'processing',
                'updated_at' => $now,
            ],
            [ 'id' => $queue_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        $portal = self::get_portal_by_id( (int) $row->portal_id );
        if ( ! $portal ) {
            self::mark_failed( $row, __( 'Portal został usunięty.', 'estate-office' ) );
            return;
        }

        if ( empty( $portal['is_enabled'] ) ) {
            self::mark_skipped( $row, __( 'Portal jest wyłączony w konfiguracji.', 'estate-office' ) );
            return;
        }

        $property = EstateOffice_Admin_Properties::get_property( (int) $row->property_id );
        if ( ! $property ) {
            self::mark_cancelled( $row, __( 'Nieruchomość została usunięta.', 'estate-office' ) );
            return;
        }

        if ( empty( $property->export_portals ) ) {
            self::mark_cancelled( $row, __( 'Eksport na portale został wyłączony dla nieruchomości.', 'estate-office' ) );
            return;
        }

        $portal_slug = sanitize_title( $portal['slug'] ?? '' );
        $assigned    = EstateOffice_Admin_Properties::get_property_portal_slugs( (int) $property->id );
        if ( $portal_slug && ! in_array( $portal_slug, $assigned, true ) ) {
            self::mark_cancelled( $row, __( 'Portal został odznaczony przy nieruchomości.', 'estate-office' ) );
            return;
        }

        $payload = estate_office_build_portal_payload( (int) $property->id, $portal );
        if ( empty( $payload ) ) {
            self::mark_failed( $row, __( 'Nie udało się przygotować danych do eksportu.', 'estate-office' ) );
            return;
        }

        $adapter = self::get_adapter_for_portal( $portal );
        if ( ! $adapter ) {
            self::mark_failed( $row, __( 'Brak adaptera eksportu dla wybranego portalu.', 'estate-office' ) );
            return;
        }

        $result = $adapter->send( $payload );

        if ( $result->is_success() ) {
            self::mark_success( $row, $result );
        } else {
            self::mark_retry( $row, $result );
        }
    }

    /**
     * Mark queue row as successfully exported.
     */
    protected static function mark_success( $row, EstateOffice_Portal_Result $result ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );

        $wpdb->update(
            $queue_table,
            [
                'status'       => 'sent',
                'attempts'     => (int) $row->attempts + 1,
                'last_error'   => null,
                'processed_at' => $now,
                'scheduled_at' => null,
                'updated_at'   => $now,
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        self::log_event( (int) $row->id, (int) $row->property_id, (int) $row->portal_id, 'sent', $result->get_message(), $result->get_context() );

        self::maybe_clear_schedule();
    }

    /**
     * Mark queue row as skipped when portal disabled.
     */
    protected static function mark_skipped( $row, string $message ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );

        $wpdb->update(
            $queue_table,
            [
                'status'       => 'skipped',
                'last_error'   => $message,
                'processed_at' => $now,
                'scheduled_at' => null,
                'updated_at'   => $now,
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        self::log_event( (int) $row->id, (int) $row->property_id, (int) $row->portal_id, 'skipped', $message );

        self::maybe_clear_schedule();
    }

    /**
     * Mark queue row as cancelled when property/portal removed.
     */
    protected static function mark_cancelled( $row, string $message ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );

        $wpdb->update(
            $queue_table,
            [
                'status'       => 'cancelled',
                'last_error'   => $message,
                'processed_at' => $now,
                'scheduled_at' => null,
                'updated_at'   => $now,
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        self::log_event( (int) $row->id, (int) $row->property_id, (int) $row->portal_id, 'cancelled', $message );

        self::maybe_clear_schedule();
    }

    /**
     * Mark queue row as failed after exceeding attempts.
     */
    protected static function mark_failed( $row, string $message ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );

        $wpdb->update(
            $queue_table,
            [
                'status'       => 'failed',
                'attempts'     => (int) $row->attempts + 1,
                'last_error'   => $message,
                'processed_at' => $now,
                'scheduled_at' => null,
                'updated_at'   => $now,
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        self::log_event( (int) $row->id, (int) $row->property_id, (int) $row->portal_id, 'failed', $message );

        self::notify_failure( $row, $message );

        self::maybe_clear_schedule();
    }

    /**
     * Mark queue row for retry.
     */
    protected static function mark_retry( $row, EstateOffice_Portal_Result $result ): void {
        global $wpdb;

        $queue_table    = $wpdb->prefix . 'eo_portal_queue';
        $now_timestamp  = current_time( 'timestamp' );
        $attempts       = (int) $row->attempts + 1;
        $message        = $result->get_message();
        $severity       = $result->get_severity();
        $retry_after    = $result->get_retry_after();

        if ( $attempts >= self::MAX_ATTEMPTS ) {
            self::mark_failed( $row, $message ?: __( 'Osiągnięto limit prób eksportu.', 'estate-office' ) );
            return;
        }

        if ( 'permanent' === $severity ) {
            self::mark_failed( $row, $message ?: __( 'Eksport został przerwany przez błąd konfiguracji portalu.', 'estate-office' ) );
            return;
        }

        if ( 'throttled' === $severity ) {
            $delay = $retry_after ? min( self::MAX_RETRY_DELAY, max( 5 * MINUTE_IN_SECONDS, $retry_after ) ) : self::RETRY_DELAY * 2;
            self::mark_throttled( $row, $message ?: __( 'Portal zwrócił limit zapytań. Zaplanowano ponowną próbę.', 'estate-office' ), $delay, $result->get_context() );
            return;
        }

        $retry_after = $retry_after ? min( self::MAX_RETRY_DELAY, max( 5 * MINUTE_IN_SECONDS, $retry_after ) ) : self::RETRY_DELAY;
        $next_timestamp = $now_timestamp + $retry_after;

        $wpdb->update(
            $queue_table,
            [
                'status'       => 'retry',
                'attempts'     => $attempts,
                'last_error'   => $message,
                'scheduled_at' => date_i18n( 'Y-m-d H:i:s', $next_timestamp ),
                'processed_at' => null,
                'updated_at'   => date_i18n( 'Y-m-d H:i:s', $now_timestamp ),
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        self::log_event( (int) $row->id, (int) $row->property_id, (int) $row->portal_id, 'retry', $message, $result->get_context() );

        self::ensure_schedule();
    }

    /**
     * Mark queue row as throttled when portal rate limits the request.
     */
    protected static function mark_throttled( $row, string $message, int $delay, array $context = [] ): void {
        global $wpdb;

        $queue_table   = $wpdb->prefix . 'eo_portal_queue';
        $now_timestamp = current_time( 'timestamp' );
        $next_run      = $now_timestamp + max( 5 * MINUTE_IN_SECONDS, $delay );

        $wpdb->update(
            $queue_table,
            [
                'status'       => 'throttled',
                'attempts'     => (int) $row->attempts + 1,
                'last_error'   => $message,
                'scheduled_at' => date_i18n( 'Y-m-d H:i:s', $next_run ),
                'processed_at' => null,
                'updated_at'   => date_i18n( 'Y-m-d H:i:s', $now_timestamp ),
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        self::log_event( (int) $row->id, (int) $row->property_id, (int) $row->portal_id, 'throttled', $message, $context );

        self::ensure_schedule();
    }

    /**
     * Log export attempt in dedicated table.
     */
    protected static function log_event( int $queue_id, int $property_id, int $portal_id, string $status, string $message = '', array $context = [] ): void {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'eo_portal_logs';
        $now        = current_time( 'mysql' );

        $wpdb->insert(
            $logs_table,
            [
                'queue_id'    => $queue_id ?: null,
                'property_id' => $property_id,
                'portal_id'   => $portal_id,
                'status'      => sanitize_key( $status ),
                'message'     => $message,
                'context'     => ! empty( $context ) ? wp_json_encode( $context ) : null,
                'created_at'  => $now,
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Notify administrators about failed exports.
     *
     * @param object $row     Queue record.
     * @param string $message Failure message.
     */
    protected static function notify_failure( $row, string $message ): void {
        $property_id = (int) ( $row->property_id ?? 0 );
        $portal_id   = (int) ( $row->portal_id ?? 0 );
        $queue_id    = (int) ( $row->id ?? 0 );
        $portal      = self::get_portal_by_id( $portal_id ) ?: [];
        $portal_name = $portal['name'] ?? ( $portal['slug'] ?? '' );

        if ( function_exists( 'estate_office_add_portal_alert' ) ) {
            estate_office_add_portal_alert(
                [
                    'queue_id'    => $queue_id,
                    'property_id' => $property_id,
                    'portal_id'   => $portal_id,
                    'portal_name' => $portal_name,
                    'message'     => $message,
                ]
            );
        }

        $admin_email = get_option( 'admin_email' );
        if ( $admin_email && is_email( $admin_email ) ) {
            $subject = sprintf(
                /* translators: %s: portal name */
                __( '[EstateOffice] Błąd eksportu na portal %s', 'estate-office' ),
                $portal_name ?: ( $portal_id ? '#' . $portal_id : __( 'portal', 'estate-office' ) )
            );

            $property_url = $property_id
                ? admin_url( 'admin.php?page=' . EstateOffice_Admin_Properties::SLUG . '&action=edit&property=' . $property_id )
                : '';
            $queue_url = admin_url( 'admin.php?page=' . EstateOffice_Admin_Exports::SLUG );

            $body_lines = [
                sprintf(
                    /* translators: 1: property id, 2: portal name */
                    __( 'Podczas eksportu nieruchomości #%1$05d na portal %2$s wystąpił błąd.', 'estate-office' ),
                    $property_id,
                    $portal_name ?: ( $portal_id ? '#' . $portal_id : __( 'portal', 'estate-office' ) )
                ),
            ];

            if ( $message ) {
                $body_lines[] = __( 'Komunikat portalu:', 'estate-office' ) . "\n" . $message;
            }

            if ( $property_url ) {
                $body_lines[] = __( 'Edytuj nieruchomość:', 'estate-office' ) . "\n" . $property_url;
            }

            $body_lines[] = __( 'Kolejka eksportów:', 'estate-office' ) . "\n" . $queue_url;

            wp_mail( $admin_email, $subject, implode( "\n\n", $body_lines ) );
        }
    }

    /**
     * Synchronize queue assignments for saved property.
     *
     * @param int               $property_id  Property identifier.
     * @param array<int,string> $portal_slugs Selected portal slugs.
     * @param bool              $enabled      Whether export is enabled.
     */
    protected static function sync_queue_for_property( int $property_id, array $portal_slugs, bool $enabled ): void {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'eo_portal_queue';
        $now         = current_time( 'mysql' );

        $portal_slugs = array_values( array_filter( array_map( 'sanitize_title', $portal_slugs ) ) );

        if ( ! $enabled || empty( $portal_slugs ) ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$queue_table} SET status = 'cancelled', last_error = %s, scheduled_at = NULL, processed_at = NULL, updated_at = %s
                     WHERE property_id = %d AND status NOT IN ('sent','cancelled')",
                    __( 'Eksport wyłączony dla nieruchomości.', 'estate-office' ),
                    $now,
                    $property_id
                )
            );
            self::maybe_clear_schedule();
            return;
        }

        $available = estate_office_get_portals( false );
        if ( empty( $available ) ) {
            return;
        }

        $selected_ids = [];
        foreach ( $available as $portal ) {
            $slug = sanitize_title( $portal['slug'] ?? '' );
            if ( '' === $slug || ! in_array( $slug, $portal_slugs, true ) ) {
                continue;
            }

            $portal_id = (int) $portal['id'];
            if ( ! $portal_id ) {
                continue;
            }

            $selected_ids[] = $portal_id;

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$queue_table} (property_id, portal_id, status, attempts, last_error, scheduled_at, processed_at, created_at, updated_at)
                     VALUES (%d, %d, 'pending', 0, NULL, %s, NULL, %s, %s)
                     ON DUPLICATE KEY UPDATE status = 'pending', attempts = 0, last_error = NULL, scheduled_at = VALUES(scheduled_at), processed_at = NULL, updated_at = VALUES(updated_at)",
                    $property_id,
                    $portal_id,
                    $now,
                    $now,
                    $now
                )
            );
        }

        if ( empty( $selected_ids ) ) {
            self::maybe_clear_schedule();
            return;
        }

        $placeholders = implode( ',', array_fill( 0, count( $selected_ids ), '%d' ) );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$queue_table} SET status = 'cancelled', last_error = %s, scheduled_at = NULL, processed_at = NULL, updated_at = %s
                 WHERE property_id = %d AND portal_id NOT IN ({$placeholders}) AND status NOT IN ('sent','cancelled')",
                __( 'Portal został odznaczony przy nieruchomości.', 'estate-office' ),
                $now,
                $property_id,
                ...$selected_ids
            )
        );
    }

    /**
     * Retrieve adapter instance for portal.
     *
     * @param array<string,mixed> $portal Portal record.
     */
    protected static function get_adapter_for_portal( array $portal ): ?EstateOffice_Portal_Adapter {
        $slug = sanitize_title( $portal['slug'] ?? '' );
        if ( '' === $slug ) {
            return null;
        }

        if ( isset( self::$adapters[ $slug ] ) ) {
            return self::$adapters[ $slug ];
        }

        $class = self::resolve_adapter_class( $slug );
        if ( ! $class || ! class_exists( $class ) ) {
            return null;
        }

        $adapter = new $class( $portal );
        if ( ! $adapter instanceof EstateOffice_Portal_Adapter ) {
            return null;
        }

        self::$adapters[ $slug ] = $adapter;

        return $adapter;
    }

    /**
     * Resolve adapter class name for portal slug.
     */
    protected static function resolve_adapter_class( string $slug ): ?string {
        static $map = null;

        if ( null === $map ) {
            $defaults = [];
            $portals  = estate_office_get_portals( false );
            foreach ( $portals as $portal ) {
                $portal_slug = sanitize_title( $portal['slug'] ?? '' );
                if ( '' === $portal_slug ) {
                    continue;
                }
                $defaults[ $portal_slug ] = EstateOffice_Portal_File_Adapter::class;
            }

            if ( empty( $defaults ) ) {
                $defaults['default'] = EstateOffice_Portal_File_Adapter::class;
            }

            $filtered = apply_filters( 'estate_office_portal_adapters', $defaults );
            $map      = is_array( $filtered ) ? $filtered : $defaults;
        }

        if ( isset( $map[ $slug ] ) && is_string( $map[ $slug ] ) ) {
            return $map[ $slug ];
        }

        if ( isset( $map['default'] ) && is_string( $map['default'] ) ) {
            return $map['default'];
        }

        return EstateOffice_Portal_File_Adapter::class;
    }

    /**
     * Fetch portal data.
     *
     * @return array<string,mixed>|null
     */
    protected static function get_portal_by_id( int $portal_id ): ?array {
        global $wpdb;

        $portal_id = absint( $portal_id );
        if ( ! $portal_id ) {
            return null;
        }

        $table = $wpdb->prefix . 'eo_portals';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $portal_id ), ARRAY_A );

        return is_array( $row ) ? $row : null;
    }
}
