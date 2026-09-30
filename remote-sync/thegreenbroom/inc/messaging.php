<?php
/**
 * Messaging
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   4. MESSAGING SYSTEM
   ========================================================================== */

function gb_send_cleaner_message() {
    if (!empty($_POST['gb_honeypot'])) wp_send_json_error(['message'=>'Spam detected.']);
    check_ajax_referer('gb_msg_nonce', 'security');

    // Messaging cleaners is for members once the gate is on. Enforced here,
    // not just by hiding the button.
    if (function_exists('gb_can_contact_cleaners') && !gb_can_contact_cleaners()) {
        wp_send_json_error(['message' => 'Messaging cleaners is for members. Become a member to get in touch.']);
    }

    $cleaner_id = intval($_POST['cleaner_id']);
    $name = sanitize_text_field($_POST['sender_name']);
    $email = sanitize_email($_POST['sender_email']);
    $body = sanitize_textarea_field($_POST['message_body']);

    // Same anti-spam layers as the contact/helpdesk form, so spam can't simply
    // move to this endpoint instead.
    $rate_state = gb_rate_limit_check('cleaner_message', 3, HOUR_IN_SECONDS, $email);
    $ip_state   = gb_rate_limit_check('cleaner_message_ip', 6, HOUR_IN_SECONDS);
    if ($rate_state['blocked'] || $ip_state['blocked']) {
        wp_send_json_error(['message' => gb_get_rate_limit_message($rate_state['blocked'] ? $rate_state : $ip_state)]);
    }
    gb_record_rate_limit_hit('cleaner_message', HOUR_IN_SECONDS, $email);
    gb_record_rate_limit_hit('cleaner_message_ip', HOUR_IN_SECONDS);

    if (!$name || !$email || !$body) wp_send_json_error(['message'=>'Please fill in all fields.']);

    // Identical payload replayed, or promotional content: acknowledge success
    // so bots get nothing to tune against, but store nothing.
    $subject_line = 'Inquiry from ' . $name;
    if (
        gb_message_is_duplicate($email, $subject_line, $body)
        || gb_message_looks_like_spam($name, $email, $subject_line, $body)
    ) {
        wp_send_json_success(['message' => 'Message sent!']);
    }

    $msg_id = wp_insert_post(['post_type'=>'gb_message', 'post_title'=>'Inquiry from '.$name, 'post_content'=>$body, 'post_status'=>'publish']);

    if ($msg_id) {
        // Only attach a related cleaner when we genuinely have a valid cleaner
        // target. Without this guard a missing/zero cleaner_id (or a manager-
        // directed message) gets bucketed under whichever cleaner post the
        // stray ID resolves to in the inbox, which is how manager messages
        // ended up showing "To: Alston Knight".
        if ($cleaner_id > 0 && get_post_type($cleaner_id) === 'gb_cleaner') {
            update_post_meta($msg_id, '_gb_related_cleaner', $cleaner_id);
        }
        update_post_meta($msg_id, '_gb_sender_name', $name);
        update_post_meta($msg_id, '_gb_sender_email', $email);
        update_post_meta($msg_id, '_gb_responded_date', '');
        gb_notify_support_message($msg_id, 'Cleaner enquiry');
        wp_send_json_success(['message'=>'Message sent!']);
    } else {
        wp_send_json_error(['message'=>'System error.']);
    }
}
add_action('wp_ajax_gb_send_message', 'gb_send_cleaner_message');
add_action('wp_ajax_nopriv_gb_send_message', 'gb_send_cleaner_message');
