<?php
/**
 * Custom post types
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   2. DATA STRUCTURE (POST TYPES)
   ========================================================================== */

function gb_register_cpts() {
    // 1. Cleaners
    register_post_type('gb_cleaner', array(
        'labels' => array('name' => 'Cleaners', 'singular_name' => 'Cleaner'),
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-id',
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'comments'),
        'rewrite' => array('slug' => 'cleaner'),
    ));

    // 2. Services
    register_post_type('gb_service', array(
        'labels' => array('name' => 'Services', 'singular_name' => 'Service'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-list-view',
    ));

    // 2b. Eco Products / guides shown on the Eco Products page.
    // A repeatable post type rather than page meta: the catalogue is expected
    // to grow to dozens or hundreds of entries, which fixed fields cannot hold.
    // Public so each guide gets a real page. The catalogue now holds full
    // write-ups, not just links out to PDFs, so a product with content needs
    // somewhere to be read.
    register_post_type('gb_eco_product', array(
        'labels' => array('name' => 'Eco Products', 'singular_name' => 'Eco Product'),
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_nav_menus' => false,
        'has_archive' => false,
        'exclude_from_search' => false,
        'rewrite' => array('slug' => 'eco-guide', 'with_front' => false),
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes', 'excerpt'),
        'menu_icon' => 'dashicons-carrot',
    ));

    // 3. Bookings
    register_post_type('gb_booking', array(
        'labels' => array('name' => 'Bookings', 'singular_name' => 'Booking'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'custom-fields'),
        'menu_icon' => 'dashicons-calendar-alt',
        'capabilities' => array('create_posts' => 'do_not_allow'),
        'map_meta_cap' => true,
    ));

    // 4. Messages
    register_post_type('gb_message', array(
        'labels' => array('name' => 'Messages', 'singular_name' => 'Message'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'menu_icon' => 'dashicons-email',
    ));
}
add_action('init', 'gb_register_cpts');
