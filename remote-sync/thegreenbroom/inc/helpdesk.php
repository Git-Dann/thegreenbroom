<?php
/**
 * Utility and helpdesk engine
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   5. UTILITY & HELPDESK ENGINE
   ========================================================================== */

function gb_is_verified_client($email, $cleaner_id) {
    if (!$email || !$cleaner_id) return false;
    $bookings = new WP_Query([
        'post_type' => 'gb_booking', 'post_status' => 'publish',
        'meta_query' => [ 'relation' => 'AND', ['key' => '_gb_booking_cleaner_id', 'value' => $cleaner_id], ['key' => '_gb_customer_email', 'value' => $email] ],
        'fields' => 'ids'
    ]);
    return $bookings->have_posts();
}

function gb_get_meta($pid, $key, $default = '') {
    $val = get_post_meta($pid, $key, true);
    return $val ? $val : $default;
}

/**
 * DIRECT CONTACT FORM PROCESSOR
 */
add_action('init', function() {
    if (isset($_POST['gb_contact_action']) && $_POST['gb_contact_action'] == 'submit_helpdesk') {
        if (
            empty($_POST['gb_contact_nonce']) ||
            !wp_verify_nonce($_POST['gb_contact_nonce'], 'gb_contact_submit')
        ) {
            return;
        }

        if (!empty($_POST['gb_contact_website'])) {
            return;
        }

        $name    = isset($_POST['c_name']) ? sanitize_text_field(wp_unslash($_POST['c_name'])) : '';
        $email   = isset($_POST['c_email']) ? sanitize_email(wp_unslash($_POST['c_email'])) : '';
        $subject = isset($_POST['c_subject']) ? sanitize_text_field(wp_unslash($_POST['c_subject'])) : '';
        $message = isset($_POST['c_message']) ? wp_kses_post(wp_unslash($_POST['c_message'])) : '';

        $contact_referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();

        // Bots post instantly (or replay a scraped page). Report success so they
        // get no feedback to tune against, but store nothing.
        $timing_token = isset($_POST['gb_contact_ts']) ? sanitize_text_field(wp_unslash($_POST['gb_contact_ts'])) : '';
        if (gb_form_timing_too_fast($timing_token)) {
            wp_safe_redirect(add_query_arg('contact_success', '1', $contact_referer));
            exit;
        }

        // Rate limit on (email + IP) AND on IP alone, so rotating the sender
        // address no longer resets the counter — that loophole is why the same
        // spam arrived five times over.
        $rate_state = gb_rate_limit_check('contact_form', 3, HOUR_IN_SECONDS, $email);
        $ip_state   = gb_rate_limit_check('contact_form_ip', 6, HOUR_IN_SECONDS);
        if ($rate_state['blocked'] || $ip_state['blocked']) {
            wp_safe_redirect(add_query_arg('contact_error', '1', $contact_referer));
            exit;
        }
        gb_record_rate_limit_hit('contact_form', HOUR_IN_SECONDS, $email);
        gb_record_rate_limit_hit('contact_form_ip', HOUR_IN_SECONDS);

        // Identical payload replayed — keep the first, drop the repeats.
        if (gb_message_is_duplicate($email, $subject, $message)) {
            wp_safe_redirect(add_query_arg('contact_success', '1', $contact_referer));
            exit;
        }

        // Content scoring. Flagged messages are quarantined rather than
        // discarded, so a false positive is recoverable from the Spam view
        // instead of silently lost.
        $is_spam = gb_message_looks_like_spam($name, $email, $subject, $message);

        if (!$name || !is_email($email) || !$subject || !$message) {
            $referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();
            wp_safe_redirect(add_query_arg('contact_error', '1', $referer));
            exit;
        }

        $ticket_id = wp_insert_post([
            'post_title'   => $subject,
            'post_content' => $message,
            'post_status'  => 'publish',
            'post_type'    => 'gb_message' 
        ]);

        if ($ticket_id) {
            update_post_meta($ticket_id, '_gb_sender_name', $name);
            update_post_meta($ticket_id, '_gb_sender_email', $email);
            // This flag is what tab-helpdesk.php filters by
            update_post_meta($ticket_id, '_gb_is_helpdesk', '1');

            if ($is_spam) {
                // Quarantined: kept out of Active/Resolved and never emailed on,
                // but reviewable (and bulk-deletable) from the Spam view.
                update_post_meta($ticket_id, '_gb_spam', '1');
            } else {
                gb_notify_support_message($ticket_id, 'Support');
            }

            // Redirect with success flag for page-contactus.php
            $referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();
            wp_safe_redirect(add_query_arg('contact_success', '1', $referer));
            exit;
        }
    }
});

/**
 * Redirect Lost Password to Custom Branded Page
 */
add_action('login_form_lostpassword', 'gb_redirect_to_custom_lostpassword');
function gb_redirect_to_custom_lostpassword() {
    if ('GET' == $_SERVER['REQUEST_METHOD']) {
        // Change '/lost-password' to match the actual slug of the page you create in WP
        wp_redirect(home_url('/lost-password'));
        exit;
    }
}

/**
 * Shared front-end "Change Password" handler.
 *
 * Both the cleaner Account Settings page and the manager dashboard post here.
 * It verifies the current password, applies the new one, and — because
 * wp_set_password() invalidates the current session — immediately re-issues an
 * auth cookie so the user is NOT kicked out after changing their password.
 * Result is reported back via a ?pw= flag on the page they came from.
 */
add_action('init', function () {
    if (!isset($_POST['gb_pw_action']) || $_POST['gb_pw_action'] !== 'change_password') {
        return;
    }

    $redirect = '';
    if (!empty($_POST['gb_pw_redirect'])) {
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['gb_pw_redirect'])), '');
    }
    if ($redirect === '') {
        $redirect = wp_get_referer() ? wp_get_referer() : home_url('/');
    }
    $bounce = function ($status) use ($redirect) {
        wp_safe_redirect(add_query_arg('pw', $status, remove_query_arg('pw', $redirect)));
        exit;
    };

    if (!is_user_logged_in()) {
        $bounce('error');
    }
    if (empty($_POST['gb_pw_nonce']) || !wp_verify_nonce($_POST['gb_pw_nonce'], 'gb_change_password')) {
        $bounce('error');
    }

    $user    = wp_get_current_user();
    $current = isset($_POST['gb_current_password']) ? (string) wp_unslash($_POST['gb_current_password']) : '';
    $new     = isset($_POST['gb_new_password']) ? (string) wp_unslash($_POST['gb_new_password']) : '';
    $confirm = isset($_POST['gb_confirm_password']) ? (string) wp_unslash($_POST['gb_confirm_password']) : '';

    if ($current === '' || $new === '' || $confirm === '') {
        $bounce('missing');
    }
    if (!wp_check_password($current, $user->user_pass, $user->ID)) {
        $bounce('wrong');
    }
    if ($new !== $confirm) {
        $bounce('mismatch');
    }
    if (strlen($new) < 8) {
        $bounce('weak');
    }

    wp_set_password($new, $user->ID);
    // Keep the user signed in after the password change.
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    $bounce('success');
}, 5);

/**
 * Render a status banner for the shared password handler's ?pw= flag.
 * Returns an HTML string (empty when there is nothing to show).
 */
function gb_password_notice_html() {
    if (!isset($_GET['pw'])) {
        return '';
    }
    $status = sanitize_key(wp_unslash($_GET['pw']));
    $map = [
        'success'  => ['#f0fdf4', '#dcfce7', '#166534', '✅ Your password has been updated.'],
        'wrong'    => ['#fef2f2', '#fee2e2', '#991b1b', 'Your current password was incorrect.'],
        'mismatch' => ['#fef2f2', '#fee2e2', '#991b1b', 'The new passwords did not match.'],
        'weak'     => ['#fffbeb', '#fde68a', '#92400e', 'Please choose a password with at least 8 characters.'],
        'missing'  => ['#fffbeb', '#fde68a', '#92400e', 'Please fill in all three password fields.'],
        'error'    => ['#fef2f2', '#fee2e2', '#991b1b', 'Could not change your password. Please try again.'],
    ];
    if (!isset($map[$status])) {
        return '';
    }
    list($bg, $border, $color, $text) = $map[$status];
    return '<div style="background:' . $bg . '; border:1px solid ' . $border . '; color:' . $color . '; padding:14px 16px; border-radius:8px; margin-bottom:20px; font-size:14px;">' . esc_html($text) . '</div>';
}

/**
 * Shared in-dashboard message reply handler.
 *
 * Used by BOTH the manager inbox/helpdesk and the cleaner inbox: it emails the
 * message sender and marks the message replied, so the "Replied" status always
 * reflects a reply actually being sent from within the platform (the old flow
 * was a bare mailto: that never recorded anything).
 *
 * Authorization: managers/admins may reply to any message; a cleaner may only
 * reply to a message tied to their own cleaner profile.
 *
 * NOTE: delivery still depends on wp_mail being backed by a working mail
 * service. wp_mail() can report success even when the host silently drops the
 * message, so a "reply_sent" flag means "handed to wp_mail", not "delivered".
 * "reply_queued" means every route refused it on the first try and the email
 * queue will keep trying it, so there is no need to reply again.
 */
add_action('init', function () {
    if (!isset($_POST['gb_reply_action']) || $_POST['gb_reply_action'] !== 'reply_message') {
        return;
    }
    if (empty($_POST['gb_reply_nonce']) || !wp_verify_nonce($_POST['gb_reply_nonce'], 'gb_reply_message')) {
        wp_die('Security check failed.');
    }
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    $mid = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $reply_body = isset($_POST['gb_reply_body']) ? sanitize_textarea_field(wp_unslash($_POST['gb_reply_body'])) : '';

    $redirect = '';
    if (!empty($_POST['gb_reply_redirect'])) {
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['gb_reply_redirect'])), '');
    }
    if ($redirect === '') {
        $redirect = home_url('/manager-dashboard?tab=support&action=view_message&id=' . $mid);
    }
    $bounce = function ($flag) use ($redirect) {
        wp_safe_redirect(add_query_arg('msg', $flag, $redirect));
        exit;
    };

    if (get_post_type($mid) !== 'gb_message' || $reply_body === '') {
        $bounce('reply_error');
    }

    // Authorization.
    $user = wp_get_current_user();
    $can_reply = current_user_can('manage_options') || in_array('gb_manager', (array) $user->roles, true);
    if (!$can_reply) {
        $related = (int) get_post_meta($mid, '_gb_related_cleaner', true);
        if ($related) {
            $owned = get_posts([
                'post_type'   => 'gb_cleaner',
                'author'      => $user->ID,
                'include'     => [$related],
                'post_status' => ['publish', 'pending', 'draft'],
                'fields'      => 'ids',
            ]);
            $can_reply = !empty($owned);
        }
    }
    if (!$can_reply) {
        wp_die('You are not allowed to reply to this message.');
    }

    $to = sanitize_email((string) get_post_meta($mid, '_gb_sender_email', true));
    $sent = false;
    if (is_email($to)) {
        $settings_id = (int) get_option('page_on_front');
        $from_name = $settings_id ? (string) get_post_meta($settings_id, '_gb_email_from_name', true) : '';
        $from_name = $from_name !== '' ? $from_name : get_bloginfo('name');
        $reply_to = $settings_id ? (string) get_post_meta($settings_id, '_gb_email_reply_to', true) : '';
        if (!is_email($reply_to)) {
            $reply_to = $settings_id ? (string) get_post_meta($settings_id, '_gb_support_email', true) : '';
        }
        if (!is_email($reply_to)) {
            $reply_to = function_exists('gb_get_support_email') ? gb_get_support_email() : get_option('admin_email');
        }

        // The raw title, not get_the_title(): that one is dressed for a web
        // page, so "Can't" became "Can&#8217;t" in the customer's inbox.
        $subject = 'Re: ' . html_entity_decode((string) get_post_field('post_title', $mid), ENT_QUOTES, 'UTF-8');
        $body = $reply_body . "\n\n—\n" . $from_name;
        $from_email = function_exists('gb_get_email_from_address') ? gb_get_email_from_address() : get_option('admin_email');
        $headers = ['From: ' . $from_name . ' <' . $from_email . '>'];
        if (is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        $sent = gb_send_brevo_template_email(
            $to,
            $subject,
            $body,
            $headers,
            gb_get_brevo_template_id( 'support_reply' ),
            [
                'first_name'     => gb_brevo_first_name( (string) get_post_meta($mid, '_gb_sender_name', true) ),
                'reply_message'  => $reply_body,
                'inbox_url'      => gb_get_email_recipient_url( $to ),
            ]
        );
    }

    /*
     * Every route refused it just now, but it is in the queue and will be
     * sent automatically. That is not "could not be sent": told that, the
     * manager replies again, and the customer ends up with both.
     */
    $queued = !$sent && is_email($to) && function_exists('gb_outbox_last_status') && gb_outbox_last_status() === 'retrying';

    update_post_meta($mid, '_gb_responded_date', current_time('mysql'));
    update_post_meta($mid, '_gb_reply_body', $reply_body);

    $bounce($sent ? 'reply_sent' : ($queued ? 'reply_queued' : 'replied_nomail'));
}, 5);

/**
 * Bulk message actions for the manager Inbox / Helpdesk.
 *
 * Supports:
 *   - delete_messages : permanently delete the ticked messages
 *   - scan_spam       : score existing messages and flag matches as _gb_spam,
 *                       so an accumulated backlog can be reviewed in one view
 *   - purge_spam      : permanently delete everything flagged as spam
 *
 * Deletes are permanent (these are spam/support messages, not content), and
 * every path is nonce-checked and restricted to managers/admins.
 */
add_action('init', function () {
    if (!isset($_POST['gb_bulk_action'])) {
        return;
    }
    $bulk_action = sanitize_key(wp_unslash($_POST['gb_bulk_action']));
    if (!in_array($bulk_action, ['delete_messages', 'scan_spam', 'purge_spam'], true)) {
        return;
    }
    if (empty($_POST['gb_bulk_nonce']) || !wp_verify_nonce($_POST['gb_bulk_nonce'], 'gb_bulk_messages')) {
        wp_die('Security check failed.');
    }
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }
    $user = wp_get_current_user();
    if (!current_user_can('administrator') && !in_array('gb_manager', (array) $user->roles, true)) {
        wp_die('You are not allowed to do that.');
    }

    $redirect = '';
    if (!empty($_POST['gb_bulk_redirect'])) {
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['gb_bulk_redirect'])), '');
    }
    if ($redirect === '') {
        $redirect = home_url('/manager-dashboard?tab=helpdesk');
    }

    $done = 0;

    if ($bulk_action === 'delete_messages') {
        $ids = isset($_POST['message_ids']) && is_array($_POST['message_ids']) ? array_map('intval', (array) $_POST['message_ids']) : [];
        foreach ($ids as $id) {
            if ($id && get_post_type($id) === 'gb_message') {
                wp_delete_post($id, true);
                $done++;
            }
        }
        $flag = 'bulk_deleted';
    } elseif ($bulk_action === 'scan_spam') {
        $candidates = get_posts([
            'post_type'      => 'gb_message',
            'post_status'    => 'any',
            'posts_per_page' => 2000,
            'fields'         => 'ids',
        ]);
        // First pass: fingerprint every message so identical mass-repeats can be
        // caught on volume alone, whatever the wording.
        $groups = [];
        $rows = [];
        foreach ($candidates as $id) {
            $post = get_post($id);
            if (!$post) {
                continue;
            }
            $fingerprint = md5(strtolower(trim($post->post_title)) . '|' . strtolower(trim($post->post_content)));
            $groups[$fingerprint] = isset($groups[$fingerprint]) ? $groups[$fingerprint] + 1 : 1;
            $rows[$id] = [
                'post'        => $post,
                'fingerprint' => $fingerprint,
            ];
        }

        foreach ($rows as $id => $row) {
            if (get_post_meta($id, '_gb_spam', true) === '1') {
                continue;
            }
            $post = $row['post'];
            $sname = (string) get_post_meta($id, '_gb_sender_name', true);
            $semail = (string) get_post_meta($id, '_gb_sender_email', true);

            $is_bulk_repeat = $groups[$row['fingerprint']] >= 3;

            if ($is_bulk_repeat || gb_message_looks_like_spam($sname, $semail, $post->post_title, $post->post_content)) {
                update_post_meta($id, '_gb_spam', '1');
                $done++;
            }
        }
        $flag = 'spam_scanned';
    } else { // purge_spam
        $spam_ids = get_posts([
            'post_type'      => 'gb_message',
            'post_status'    => 'any',
            'posts_per_page' => 2000,
            'fields'         => 'ids',
            'meta_query'     => [[ 'key' => '_gb_spam', 'value' => '1' ]],
        ]);
        foreach ($spam_ids as $id) {
            wp_delete_post($id, true);
            $done++;
        }
        $flag = 'spam_purged';
    }

    wp_safe_redirect(add_query_arg(['msg' => $flag, 'n' => $done], $redirect));
    exit;
}, 5);

/**
 * One-time cleanup of legacy mis-tagged support messages.
 *
 * Helpdesk / manager-directed messages (_gb_is_helpdesk = '1') should never
 * carry a _gb_related_cleaner. Older code paths sometimes attached one, which
 * made those messages display under a specific cleaner ("To: Alston Knight")
 * in the manager inbox. Clear that stray meta once so historical tickets show
 * correctly. Genuine customer→cleaner enquiries (not helpdesk-flagged) are
 * left untouched. Runs a single time when a manager/admin is on the site.
 */
add_action('init', function () {
    if (get_option('gb_helpdesk_relation_cleanup_v1')) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    $user = wp_get_current_user();
    if (!current_user_can('manage_options') && !in_array('gb_manager', (array) $user->roles, true)) {
        return;
    }

    $ids = get_posts([
        'post_type'      => 'gb_message',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_gb_is_helpdesk', 'value' => '1'],
            ['key' => '_gb_related_cleaner', 'compare' => 'EXISTS'],
        ],
    ]);
    foreach ($ids as $id) {
        delete_post_meta($id, '_gb_related_cleaner');
    }
    update_option('gb_helpdesk_relation_cleanup_v1', 1);
}, 20);
