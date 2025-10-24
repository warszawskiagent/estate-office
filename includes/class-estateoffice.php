<?php
/**
 * Core plugin bootstrap.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-admin.php';
require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-public.php';

class EstateOffice {

    /**
     * Loader for admin logic.
     *
     * @var EstateOffice_Admin
     */
    protected $admin;

    /**
     * Public module handler.
     *
     * @var EstateOffice_Public
     */
    protected $public;

    /**
     * Initialize plugin pieces.
     */
    public function __construct() {
        $this->admin  = new EstateOffice_Admin();
        $this->public = new EstateOffice_Public();
    }

    /**
     * Register hooks.
     */
    public function run(): void {
        EstateOffice_Activator::ensure_role_capabilities();
        EstateOffice_Activator::maybe_upgrade_schema();
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ 'EstateOffice_Activator', 'ensure_role_capabilities' ] );
        add_action( 'init', [ 'EstateOffice_Activator', 'maybe_upgrade_schema' ] );
        add_action( 'init', [ $this, 'expire_new_offer_flags' ], 20 );
        add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 30 );
        add_action( 'admin_init', [ 'EstateOffice_Activator', 'ensure_role_capabilities' ] );
        add_action( 'admin_init', [ 'EstateOffice_Activator', 'maybe_upgrade_schema' ] );
        EstateOffice_Portal_Manager::hooks();
        if ( is_admin() ) {
            $this->admin->hooks();
        }

        $this->public->hooks();
    }

    /**
     * Automatically disable the "new offer" flag after seven days.
     */
    public function expire_new_offer_flags(): void {
        $last_run = (int) get_option( 'estate_office_new_offer_cleanup', 0 );
        $now      = current_time( 'timestamp' );

        if ( $last_run && ( $now - $last_run ) < DAY_IN_SECONDS ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';
        $rows  = $wpdb->get_results( "SELECT id, tags, created_at, updated_at FROM {$table} WHERE tags LIKE '%\"new_offer\"%'" );

        if ( empty( $rows ) ) {
            update_option( 'estate_office_new_offer_cleanup', $now );
            return;
        }

        $threshold = $now - ( 7 * DAY_IN_SECONDS );

        foreach ( $rows as $row ) {
            $tags = json_decode( $row->tags, true );
            if ( ! is_array( $tags ) || ! array_key_exists( 'new_offer', $tags ) ) {
                continue;
            }

            $tag_value = $tags['new_offer'];
            $active    = true;
            $since     = '';

            if ( is_array( $tag_value ) ) {
                if ( array_key_exists( 'active', $tag_value ) ) {
                    $active = (bool) $tag_value['active'];
                }
                $since = $tag_value['since'] ?? '';
            } else {
                $active = ! empty( $tag_value );
            }

            $needs_update = false;

            if ( ! $active ) {
                unset( $tags['new_offer'] );
                $needs_update = true;
            } else {
                if ( ! $since ) {
                    $since        = $row->updated_at ?: $row->created_at ?: current_time( 'mysql' );
                    $needs_update = true;
                }

                $since_timestamp = $since ? strtotime( $since ) : 0;
                if ( ! $since_timestamp ) {
                    $since_timestamp = $now;
                    $since           = current_time( 'mysql' );
                    $needs_update    = true;
                }

                if ( $since_timestamp <= $threshold ) {
                    unset( $tags['new_offer'] );
                    $needs_update = true;
                } else {
                    if ( ! is_array( $tag_value ) || empty( $tag_value['active'] ) || empty( $tag_value['since'] ) || $tag_value['since'] !== $since ) {
                        $tags['new_offer'] = [
                            'active' => 1,
                            'since'  => $since,
                        ];
                        $needs_update = true;
                    }
                }
            }

            if ( $needs_update ) {
                $this->update_property_tags( (int) $row->id, $tags );
            }
        }

        update_option( 'estate_office_new_offer_cleanup', $now );
    }

    /**
     * Persist sanitized tags for property after expiration checks.
     *
     * @param int                   $property_id Property identifier.
     * @param array<string,mixed>   $tags        Tags map to store.
     */
    protected function update_property_tags( int $property_id, array $tags ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';
        $now   = current_time( 'mysql' );

        if ( empty( $tags ) ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET tags = NULL, updated_at = %s WHERE id = %d", $now, $property_id ) );
            return;
        }

        $wpdb->update(
            $table,
            [
                'tags'       => wp_json_encode( $tags ),
                'updated_at' => $now,
            ],
            [ 'id' => $property_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Load localization files.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_FILE ) ) . '/languages/' );
    }

    /**
     * Flush rewrite rules once after upgrades that require it.
     */
    public function maybe_flush_rewrite(): void {
        if ( get_option( 'estate_office_flush_rewrite' ) ) {
            flush_rewrite_rules( false );
            delete_option( 'estate_office_flush_rewrite' );
        }
    }
}
