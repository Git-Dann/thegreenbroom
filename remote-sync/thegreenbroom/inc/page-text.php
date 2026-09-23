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
