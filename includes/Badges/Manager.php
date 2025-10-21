<?php
namespace EstateOffice\Badges;

use EstateOffice\Meta\Keys;
use WP_Query;

/**
 * Handles housekeeping for property badges.
 */
class Manager {
    public const CRON_HOOK = 'estate_office_cleanup_badges';

    /**
     * Registers runtime hooks.
     */
    public function register(): void {
        add_action( self::CRON_HOOK, [ $this, 'cleanup_new_offer_badges' ] );
    }

    /**
     * Ensures the cleanup event is scheduled.
     */
    public function schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Removes the scheduled cleanup event.
     */
    public function unschedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Tracks when the "Nowa oferta" badge is applied or removed.
     */
    public function track_new_offer_badge( int $property_id, array $badges ): void {
        $badges = array_map( 'sanitize_key', $badges );

        if ( in_array( 'nowa_oferta', $badges, true ) ) {
            if ( ! get_post_meta( $property_id, Keys::PROPERTY_NEW_BADGE_DATE, true ) ) {
                update_post_meta( $property_id, Keys::PROPERTY_NEW_BADGE_DATE, time() );
            }

            return;
        }

        delete_post_meta( $property_id, Keys::PROPERTY_NEW_BADGE_DATE );
    }

    /**
     * Removes the "Nowa oferta" badge once it expires.
     */
    public function cleanup_new_offer_badges(): void {
        $args  = [
            'post_type'      => 'estate_property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => Keys::PROPERTY_NEW_BADGE_DATE,
                    'compare' => 'EXISTS',
                ],
            ],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return;
        }

        $threshold = strtotime( '-7 days' );

        foreach ( $query->posts as $post_id ) {
            $timestamp = (int) get_post_meta( $post_id, Keys::PROPERTY_NEW_BADGE_DATE, true );

            if ( ! $timestamp || $timestamp > $threshold ) {
                continue;
            }

            $badges = get_post_meta( $post_id, Keys::PROPERTY_BADGES, true );

            if ( ! is_array( $badges ) || empty( $badges ) ) {
                delete_post_meta( $post_id, Keys::PROPERTY_NEW_BADGE_DATE );
                continue;
            }

            $badges = array_values(
                array_filter(
                    array_map( 'sanitize_key', $badges ),
                    static fn ( $badge ) => 'nowa_oferta' !== $badge
                )
            );

            update_post_meta( $post_id, Keys::PROPERTY_BADGES, $badges );
            delete_post_meta( $post_id, Keys::PROPERTY_NEW_BADGE_DATE );

            /**
             * Fires when badges have been refreshed by the scheduler.
             */
            do_action( 'estate_office_property_badges_updated', $post_id, $badges );
        }

        wp_reset_postdata();
    }
}
