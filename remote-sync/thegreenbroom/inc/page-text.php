<?php
/**
 * Page wording helper.
 *
 * Contact, Contact Support, Book Now and the booking confirmation each read
 * their visible strings from page meta with a fallback. That closure was
 * written into all four templates; this is the one copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * A stored value for this page, or the supplied default when it is blank.
 *
 * $page_slug is only needed where the template is not rendering that page as
 * the main query - the booking confirmation, for instance, which reads the
 * book-now record.
 */
function gb_page_text( $key, $default, $page_slug = '' ) {
    $post_id = 0;

    if ( $page_slug !== '' ) {
        $page = get_page_by_path( $page_slug );
        $post_id = $page instanceof WP_Post ? $page->ID : 0;
    } elseif ( in_the_loop() || get_the_ID() ) {
        $post_id = (int) get_the_ID();
    }

    if ( ! $post_id ) {
        return $default;
    }

    $value = trim( (string) get_post_meta( $post_id, $key, true ) );

    return $value === '' ? $default : $value;
}

/**
 * Bring stored page wording in line with the client's copy corrections.
 *
 * The templates read their wording from post meta and fall back to a default
 * when the meta is empty. These pages have all been seeded, so the meta is
 * not empty - changing the defaults alone left the live site unchanged.
 *
 * Each replacement is conditional on the stored value still being the exact
 * string it is replacing. Anything typed by hand since is left alone: this
 * corrects what nobody has touched, and never overwrites somebody's edit.
 */
function gb_apply_client_copy_corrections() {
    if ( get_option( 'gb_copy_corrections_v1_applied' ) ) {
        return;
    }

    $front_id = (int) get_option( 'page_on_front' );
    if ( ! $front_id ) {
        $front = get_page_by_path( 'home' );
        $front_id = $front ? (int) $front->ID : 0;
    }

    $corrections = array(
        $front_id => array(
            '_gb_hero_btn_2' => array( 'Join as a Cleaner', 'Become a Cleaner' ),
            '_gb_testi_head' => array( 'What our customers say', 'Meet some of our cleaners' ),
        ),
        'how-we-do-it' => array(
            '_gb_about_btn2_text' => array( 'Join as a Cleaner', 'Become a Cleaner' ),
        ),
        'how-it-works' => array(
            '_gb_hiw_s2_title' => array( 'Book Instantly', 'Ask the cleaner a question first' ),
            '_gb_hiw_s2_desc'  => array(
                'Choose a time slot that works for you. No back-and-forth texts. Our secure calendar system locks in your appointment.',
                'Message the cleaner through the platform to check they cover your area, agree what you need doing, and settle a price before anything is booked.',
            ),
        ),
        'contact' => array(
            '_gb_contact_title' => array( 'Contact Support', 'Contact' ),
        ),
    );

    foreach ( $corrections as $page => $fields ) {
        if ( is_int( $page ) ) {
            $page_id = $page;
        } else {
            $found   = get_page_by_path( $page );
            $page_id = $found ? (int) $found->ID : 0;
        }
        if ( ! $page_id ) {
            continue;
        }

        foreach ( $fields as $key => $pair ) {
            list( $was, $now ) = $pair;
            if ( (string) get_post_meta( $page_id, $key, true ) === $was ) {
                update_post_meta( $page_id, $key, $now );
            }
        }
    }

    /*
     * The two invented customer reviews are gone from the homepage and from
     * the dashboard, so their stored values have no reader left. Clear them
     * rather than leave "Sarah J., Manchester" sitting in the database for
     * some future field to pick back up.
     */
    if ( $front_id ) {
        foreach ( array( '_gb_testi1_text', '_gb_testi1_auth', '_gb_testi2_text', '_gb_testi2_auth' ) as $stale ) {
            delete_post_meta( $front_id, $stale );
        }
    }

    update_option( 'gb_copy_corrections_v1_applied', 1, false );
}
