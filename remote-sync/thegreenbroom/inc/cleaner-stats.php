<?php
/**
 * Completed cleans and repeat customers, counted from real bookings.
 *
 * Nobody types these in. A cleaner cannot inflate them and a manager cannot
 * seed them, which is the only reason they are worth printing on a platform
 * whose pitch is that it checks things.
 *
 * "Completed" means a confirmed booking whose date has passed. There is no
 * separate completed status in the booking flow yet, and inventing one here
 * would mean guessing at bookings nobody has marked either way. A "repeat
 * customer" is an email address with two or more such bookings for the same
 * cleaner.
 *
 * Both numbers start at zero and the profile hides them until they are not,
 * so a brand new cleaner does not advertise having done nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_CLEANER_STATS_TTL = HOUR_IN_SECONDS;

function gb_cleaner_stats_cache_key( $cleaner_id ) {
    return 'gb_cleaner_stats_' . (int) $cleaner_id;
}

/**
 * @return array{completed:int,repeat:int}
 */
function gb_get_cleaner_stats( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $empty      = array( 'completed' => 0, 'repeat' => 0 );

    if ( $cleaner_id < 1 ) {
        return $empty;
    }

    $cached = get_transient( gb_cleaner_stats_cache_key( $cleaner_id ) );
    if ( is_array( $cached ) && isset( $cached['completed'], $cached['repeat'] ) ) {
        return array(
            'completed' => (int) $cached['completed'],
            'repeat'    => (int) $cached['repeat'],
        );
    }

    $bookings = get_posts( array(
        'post_type'        => 'gb_booking',
        'post_status'      => array( 'publish', 'private' ),
        'posts_per_page'   => 500,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => false,
        'meta_query'       => array(
            array(
                'key'   => '_gb_booking_cleaner_id',
                'value' => $cleaner_id,
            ),
        ),
    ) );

    if ( empty( $bookings ) ) {
        set_transient( gb_cleaner_stats_cache_key( $cleaner_id ), $empty, GB_CLEANER_STATS_TTL );
        return $empty;
    }

    $today     = current_time( 'Y-m-d' );
    $completed = 0;
    $per_email = array();

    foreach ( $bookings as $booking_id ) {
        if ( sanitize_key( (string) get_post_meta( $booking_id, '_gb_booking_status', true ) ) !== 'confirmed' ) {
            continue;
        }

        $date = trim( (string) get_post_meta( $booking_id, '_gb_booking_date', true ) );
        if ( $date === '' || $date >= $today ) {
            continue;
        }

        $completed++;

        // Case and stray whitespace are the difference between one customer
        // who came back and two who each booked once.
        $email = strtolower( trim( (string) get_post_meta( $booking_id, '_gb_customer_email', true ) ) );
        if ( $email !== '' ) {
            $per_email[ $email ] = isset( $per_email[ $email ] ) ? $per_email[ $email ] + 1 : 1;
        }
    }

    $stats = array(
        'completed' => $completed,
        'repeat'    => count( array_filter( $per_email, static function ( $count ) {
            return $count > 1;
        } ) ),
    );

    set_transient( gb_cleaner_stats_cache_key( $cleaner_id ), $stats, GB_CLEANER_STATS_TTL );

    return $stats;
}

/**
 * Drop a cleaner's cached figures the moment one of their bookings changes,
 * so a manager who confirms a booking does not have to wait out the hour to
 * see the profile agree with the booking list.
 */
function gb_flush_cleaner_stats_for_booking( $post_id ) {
    if ( get_post_type( $post_id ) !== 'gb_booking' ) {
        return;
    }

    $cleaner_id = (int) get_post_meta( $post_id, '_gb_booking_cleaner_id', true );
    if ( $cleaner_id > 0 ) {
        delete_transient( gb_cleaner_stats_cache_key( $cleaner_id ) );
    }
}
add_action( 'save_post', 'gb_flush_cleaner_stats_for_booking' );
// before_delete_post, not deleted_post: by the time the post is gone so is the
// meta that says which cleaner to flush.
add_action( 'before_delete_post', 'gb_flush_cleaner_stats_for_booking' );
add_action( 'trashed_post', 'gb_flush_cleaner_stats_for_booking' );
