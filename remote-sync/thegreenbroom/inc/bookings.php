<?php
/**
 * Booking engine
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   3. BOOKING ENGINE LOGIC
   ========================================================================== */

/*
 * A booking can hold more than one valid link. One is made when the booking is
 * created (it rides in Stripe's return address) and another for the
 * confirmation email. This used to overwrite the stored hash, so confirming a
 * booking - usually by webhook, before the customer's browser was back from
 * Stripe - broke the link they were on: "No Booking Found", no account form,
 * no calendar file. Each new link is now added alongside the others.
 */
function gb_generate_booking_access_token($booking_id) {
    $raw_token = wp_generate_password(48, false, false);
    add_post_meta($booking_id, '_gb_booking_access_token', wp_hash($raw_token));

    return $raw_token;
}

function gb_booking_token_is_valid($booking_id, $raw_token) {
    if (!$raw_token) {
        return false;
    }

    $given = wp_hash($raw_token);

    foreach ((array) get_post_meta($booking_id, '_gb_booking_access_token', false) as $stored_hash) {
        if ($stored_hash && hash_equals((string) $stored_hash, $given)) {
            return true;
        }
    }

    return false;
}

function gb_find_service_post($identifier) {
    if ($identifier === null || $identifier === '') {
        return null;
    }

    if (is_numeric($identifier)) {
        $service_post = get_post((int) $identifier);
        if ($service_post && $service_post->post_type === 'gb_service' && $service_post->post_status === 'publish') {
            return $service_post;
        }
    }

    $name = sanitize_text_field((string) $identifier);
    if ($name === '') {
        return null;
    }

    $slug = sanitize_title($name);
    if ($slug !== '') {
        $service_post = get_page_by_path($slug, OBJECT, 'gb_service');
        if ($service_post && $service_post->post_type === 'gb_service' && $service_post->post_status === 'publish') {
            return $service_post;
        }
    }

    $service_query = new WP_Query([
        'post_type'      => 'gb_service',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'title'          => $name,
        'no_found_rows'  => true,
    ]);
    if (!empty($service_query->posts[0])) {
        $service_post = get_post((int) $service_query->posts[0]);
        if ($service_post && $service_post->post_type === 'gb_service' && $service_post->post_status === 'publish') {
            return $service_post;
        }
    }

    return null;
}

function gb_get_service_duration_hours($service_identifier, $fallback = 2) {
    $duration = (int) $fallback;
    if ($duration < 1) {
        $duration = 1;
    }

    $service_post = gb_find_service_post($service_identifier);
    if (!$service_post) {
        return $duration;
    }

    $stored_duration = (int) get_post_meta($service_post->ID, '_gb_service_duration', true);
    if ($stored_duration < 1) {
        return $duration;
    }

    return $stored_duration;
}

/*
 * gb_process_booking_submission() used to live here: an AJAX endpoint, open
 * to logged-out visitors, that created confirmed bookings with no payment. It
 * predates Stripe checkout (page-book.php) and nothing linked to it, but it
 * only stayed unreachable because nothing printed its nonce. Removed so it
 * cannot come back to life by accident.
 */
