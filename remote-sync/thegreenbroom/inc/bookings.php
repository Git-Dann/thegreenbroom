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

function gb_process_booking_submission() {
    if (!check_ajax_referer('gb_booking_nonce', 'security', false)) {
        wp_send_json_error(['message' => 'Security session expired. Please refresh the page.']);
    }

    $rate_identity = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
    $rate_state = gb_rate_limit_check('booking_ajax', 8, HOUR_IN_SECONDS, $rate_identity);
    if ($rate_state['blocked']) {
        wp_send_json_error(['message' => gb_get_rate_limit_message($rate_state)]);
    }
    gb_record_rate_limit_hit('booking_ajax', HOUR_IN_SECONDS, $rate_identity);

    $cleaner_id = isset($_POST['cleaner_id']) ? absint($_POST['cleaner_id']) : 0;
    $service_identifier = isset($_POST['service']) ? sanitize_text_field(wp_unslash($_POST['service'])) : '';
    $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
    $time_str = isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '';
    $name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
    $email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
    $phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
    $address = isset($_POST['customer_address']) ? sanitize_textarea_field(wp_unslash($_POST['customer_address'])) : '';

    if (
        !$cleaner_id ||
        get_post_type($cleaner_id) !== 'gb_cleaner' ||
        get_post_status($cleaner_id) !== 'publish'
    ) {
        wp_send_json_error(['message' => 'This cleaner is not currently available for booking.']);
    }

    $service_post = gb_find_service_post($service_identifier);
    if (!$service_post) {
        wp_send_json_error(['message' => 'Please choose an available service.']);
    }

    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    $date_errors = DateTimeImmutable::getLastErrors();
    if (
        !$date_object ||
        ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0)) ||
        $date_object->format('Y-m-d') !== $date ||
        $date < current_time('Y-m-d')
    ) {
        wp_send_json_error(['message' => 'Please choose a valid future booking date.']);
    }

    if (!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $time_str)) {
        wp_send_json_error(['message' => 'Please choose a valid booking time.']);
    }

    if (!$name || !is_email($email) || !$phone || !$address) {
        wp_send_json_error(['message' => 'Please complete all booking details.']);
    }

    $service_name = sanitize_text_field($service_post->post_title);

    // Duration is always read from the selected, published service.
    $duration = gb_get_service_duration_hours($service_post->ID, 2);

    // Double Booking Check
    $new_start = strtotime("$date $time_str");
    $new_end = $new_start + ($duration * 3600);
    $existing = new WP_Query([
        'post_type' => 'gb_booking',
        'post_status' => ['publish'], 
        'posts_per_page' => -1,
        'meta_query' => [ 'relation' => 'AND', ['key' => '_gb_booking_cleaner_id', 'value' => $cleaner_id], ['key' => '_gb_booking_date', 'value' => $date] ]
    ]);

    if ($existing->have_posts()) {
        while ($existing->have_posts()) {
            $existing->the_post();
            $ex_time = get_post_meta(get_the_ID(), '_gb_booking_time', true);
            $ex_dur = get_post_meta(get_the_ID(), '_gb_booking_duration', true);
            $ex_start = strtotime("$date $ex_time");
            $ex_end = $ex_start + ($ex_dur * 3600);
            if ($new_start < $ex_end && $new_end > $ex_start) {
                wp_send_json_error(['message' => '⚠️ Time slot taken. Please select another time.']);
            }
        }
        wp_reset_postdata();
    }

    // Create Booking
    $booking_title = sprintf('%s - %s (%s)', $name, $date, $time_str);
    $booking_id = wp_insert_post(['post_type'=>'gb_booking', 'post_title'=>$booking_title, 'post_status'=>'publish']);

    if ($booking_id) {
        update_post_meta($booking_id, '_gb_booking_cleaner_id', $cleaner_id);
        update_post_meta($booking_id, '_gb_booking_service', $service_name);
        update_post_meta($booking_id, '_gb_booking_date', $date);
        update_post_meta($booking_id, '_gb_booking_time', $time_str);
        update_post_meta($booking_id, '_gb_booking_duration', $duration);
        update_post_meta($booking_id, '_gb_customer_name', $name);
        update_post_meta($booking_id, '_gb_customer_email', $email);
        update_post_meta($booking_id, '_gb_customer_phone', $phone);
        update_post_meta($booking_id, '_gb_customer_address', $address);
        update_post_meta($booking_id, '_gb_booking_status', 'confirmed');

        $access_token = gb_generate_booking_access_token($booking_id);
        wp_send_json_success(['message'=>'Booking confirmed!', 'redirect'=>home_url('/booking-success?bid='.$booking_id.'&t='.rawurlencode($access_token))]);
    } else {
        wp_send_json_error(['message'=>'System error.']);
    }
}
add_action('wp_ajax_gb_process_booking', 'gb_process_booking_submission');
add_action('wp_ajax_nopriv_gb_process_booking', 'gb_process_booking_submission');
