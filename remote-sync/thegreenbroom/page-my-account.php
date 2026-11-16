<?php
/* Template Name: Customer Dashboard */

if ( ! is_user_logged_in() ) { wp_redirect( home_url('/login') ); exit; }
get_header(); 

$current_user = wp_get_current_user();
$user_email   = $current_user->user_email;
$user_id      = (int) $current_user->ID;
$first_name   = get_user_meta( $current_user->ID, 'first_name', true ) ?: $current_user->display_name;
$msg = '';

$gb_resolve_booking_flow = static function($booking_id, $post_status = '') {
    $flow_status = sanitize_key((string) get_post_meta($booking_id, '_gb_booking_status', true));
    if ($flow_status !== '') {
        return $flow_status;
    }

    if ($post_status === 'publish') {
        return 'confirmed';
    }
    if ($post_status === 'trash') {
        return 'cancelled';
    }
    return 'pending_payment';
};

$gb_flow_label = static function($flow_status, $job_ts) {
    if ($flow_status === 'cancelled') {
        return ['label' => 'Cancelled', 'class' => 'gb-badge-danger'];
    }
    if ($flow_status === 'pending_payment') {
        return ['label' => 'Pending Payment', 'class' => 'gb-badge-warning'];
    }
    if ($job_ts < current_time('timestamp')) {
        return ['label' => 'Completed', 'class' => 'gb-badge-success'];
    }
    return ['label' => 'Confirmed', 'class' => 'gb-badge-success'];
};

// Cancel Action
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_cancel_id']) ) {
    if (empty($_POST['gb_cancel_nonce']) || !wp_verify_nonce($_POST['gb_cancel_nonce'], 'gb_cancel_booking')) {
        $msg = '<div class="gb-card" style="background:#fee2e2; color:#991b1b; padding:15px; margin-bottom:20px;">Security check failed. Please refresh and try again.</div>';
    } else {
        $bid = intval($_POST['gb_cancel_id']);
        $booking_owner_id = (int) get_post_field('post_author', $bid);
        $booking_email = sanitize_email((string) get_post_meta($bid, '_gb_customer_email', true));
        $can_cancel = ($booking_owner_id === $user_id) || ($booking_email !== '' && $booking_email === $user_email);

        /*
         * The refund the customer was shown next to the Cancel button. The
         * quote is worked out again here, from Stripe, and if it has gone
         * DOWN since the page was drawn (the page sat open past the window,
         * or past the start) nothing is cancelled: they are shown the new
         * figure and asked again, with a button carrying that figure. A
         * customer should never get less back than the page told them.
         */
        $gb_c_expected = isset($_POST['gb_cancel_expected']) && is_numeric($_POST['gb_cancel_expected']) ? (int) $_POST['gb_cancel_expected'] : -1;
        $gb_c_quote    = null;
        if (get_post_type($bid) === 'gb_booking' && $can_cancel && function_exists('gb_booking_refund_quote')) {
            $gb_c_quote = gb_booking_refund_quote($bid);
        }

        if ($gb_c_quote && $gb_c_expected >= 0 && $gb_c_quote['amount_pence'] < $gb_c_expected && get_post_meta($bid, '_gb_booking_status', true) !== 'cancelled') {
            $msg = '<div class="gb-card" style="background:#fffbeb; color:#92400e; padding:15px; margin-bottom:20px;">'
                . '<strong>Nothing has been cancelled yet.</strong> The refund for this booking has changed since the page was opened. '
                . esc_html(gb_refund_quote_summary($gb_c_quote))
                . '<form method="post" action="' . esc_url(home_url('/my-account/')) . '" style="margin-top:12px;">'
                . '<input type="hidden" name="gb_cancel_id" value="' . esc_attr($bid) . '">'
                . '<input type="hidden" name="gb_cancel_expected" value="' . esc_attr($gb_c_quote['amount_pence']) . '">'
                . wp_nonce_field('gb_cancel_booking', 'gb_cancel_nonce', true, false)
                . '<button class="gb-btn gb-btn-danger gb-btn-sm">Cancel anyway</button>'
                . '</form></div>';
        } elseif (get_post_type($bid) === 'gb_booking' && $can_cancel && get_post_meta($bid, '_gb_booking_status', true) !== 'cancelled') {
            /*
             * Marked as the customer's own cancellation BEFORE the status
             * changes. inc/refunds.php watches for bookings becoming
             * cancelled and refunds team cancellations in full; this mark
             * tells it to leave this one alone, as the policy refund is made
             * just below. Without it a customer inside the window would get
             * their partial refund and then a second refund of the rest.
             */
            update_post_meta($bid, '_gb_cancelled_by', 'customer');
            update_post_meta($bid, '_gb_cancelled_at', current_time('mysql'));
            update_post_meta($bid, '_gb_booking_status', 'cancelled');

            $gb_c_refund_due    = $gb_c_quote ? (int) $gb_c_quote['amount_pence'] : 0;
            $gb_c_refund_error  = '';
            $gb_c_refunded      = 0;
            // Settings, Payment switches: with automatic refunds off, what is
            // due is worked out and recorded, and the team sends it by hand.
            $gb_c_refund_manual = $gb_c_refund_due > 0 && function_exists('gb_auto_refunds_enabled') && !gb_auto_refunds_enabled();
            if ($gb_c_refund_manual) {
                update_post_meta($bid, '_gb_refund_note', 'Refund of ' . gb_refund_money($gb_c_refund_due) . ' due (' . gb_refund_quote_reason_text($gb_c_quote) . '). Automatic refunds are switched off, so it has not been sent yet.');
            } elseif ($gb_c_refund_due > 0) {
                $gb_c_result = gb_refund_booking($bid, $gb_c_refund_due, 'Cancelled by the customer: ' . gb_refund_quote_reason_text($gb_c_quote));
                if (is_wp_error($gb_c_result)) {
                    $gb_c_refund_error = $gb_c_result->get_error_message();
                } else {
                    $gb_c_refunded = (int) $gb_c_result['amount_pence'];
                }
            }

            /*
             * Tell the cleaner and the team. This used to change one field and
             * nothing else: the cleaner still turned up, and a paid booking
             * stayed paid with nobody prompted to refund it.
             */
            $gb_c_date    = (string) get_post_meta($bid, '_gb_booking_date', true);
            $gb_c_time    = (string) get_post_meta($bid, '_gb_booking_time', true);
            $gb_c_service = (string) get_post_meta($bid, '_gb_booking_service', true);
            $gb_c_cleaner = (int) get_post_meta($bid, '_gb_booking_cleaner_id', true);
            $gb_c_paid    = get_post_meta($bid, '_gb_payment_status', true) === 'paid';
            $gb_c_when    = trim($gb_c_service . ' on ' . $gb_c_date . ' at ' . $gb_c_time);
            $gb_c_subject = 'Booking #' . $bid . ' cancelled by the customer';

            if ($gb_c_refund_manual) {
                $gb_c_money = 'REFUND DUE: ' . gb_refund_money($gb_c_refund_due) . ' (' . gb_refund_quote_reason_text($gb_c_quote) . "). Automatic refunds are switched off in Settings, so nothing has been sent yet.\n\nTo send it: refund " . gb_refund_money($gb_c_refund_due) . " on this payment in Stripe (Payments, open the payment, Refund), choosing to reverse the transfer to the cleaner if Stripe offers it. If the whole remaining amount is owed, \"Refund the rest in full\" on the booking in Manager OS does it in one step. The customer has been told the team will send it.";
            } elseif ($gb_c_refunded > 0) {
                $gb_c_money = 'It was paid through Stripe and ' . gb_refund_money($gb_c_refunded) . ' has been refunded automatically (' . gb_refund_quote_reason_text($gb_c_quote) . '). The cleaner\'s share of that has been taken back from their Stripe account.';
            } elseif ($gb_c_refund_error !== '') {
                $gb_c_money = 'REFUND FAILED. ' . gb_refund_money($gb_c_refund_due) . ' was due (' . gb_refund_quote_reason_text($gb_c_quote) . "), but Stripe did not accept it:\n\n" . $gb_c_refund_error . "\n\nPlease refund it by hand in Stripe, or use \"Refund the rest in full\" on the booking once the problem is fixed. The customer has been told the team will sort it.";
            } elseif ($gb_c_paid && $gb_c_quote) {
                $gb_c_money = 'It was paid through Stripe. No refund was due under the cancellation policy (' . gb_refund_quote_reason_text($gb_c_quote) . ').';
            } else {
                $gb_c_money = $gb_c_paid ? 'It was paid through Stripe. Nothing has been refunded automatically: check it in Stripe.' : 'It was not paid.';
            }

            $gb_c_cleaner_user = $gb_c_cleaner ? get_userdata((int) get_post_field('post_author', $gb_c_cleaner)) : false;
            if ($gb_c_cleaner_user && is_email($gb_c_cleaner_user->user_email)) {
                wp_mail(
                    $gb_c_cleaner_user->user_email,
                    $gb_c_subject,
                    "Hi,\n\nThe customer has cancelled this booking:\n\n{$gb_c_when}\n\nThere is no need to attend. The team will be in touch if anything else is needed.\n\nThe Green Broom"
                );
            }

            if (function_exists('gb_get_support_email') && is_email(gb_get_support_email())) {
                wp_mail(
                    gb_get_support_email(),
                    $gb_c_subject . ($gb_c_refund_manual ? ' - REFUND DUE ' . gb_refund_money($gb_c_refund_due) . ', please send it' : ($gb_c_refund_error !== '' ? ' - REFUND FAILED, action needed' : ($gb_c_refunded > 0 ? ' - refunded ' . gb_refund_money($gb_c_refunded) : ''))),
                    "Booking #{$bid} ({$gb_c_when}) was cancelled by the customer from their account.\n\n"
                    . $gb_c_money . "\n\n"
                    . 'Open it in Manager OS: ' . home_url('/manager-dashboard/?tab=bookings&action=edit&id=' . $bid)
                );
            }

            // The customer's own word on the money, by email as well as on
            // the page, so they have it in writing.
            if ($gb_c_refund_manual) {
                $gb_c_notice = 'Booking cancelled. A refund of ' . gb_refund_money($gb_c_refund_due) . ' is due (' . gb_refund_quote_reason_text($gb_c_quote) . '). Our team will send it to your card shortly; it usually takes 5 to 10 working days to show after that. You do not need to do anything.';
            } elseif ($gb_c_refunded > 0) {
                gb_refund_email_customer($bid, $gb_c_refunded, gb_refund_customer_why($gb_c_quote));
                $gb_c_notice = 'Booking cancelled. We have refunded ' . gb_refund_money($gb_c_refunded) . ' to your card (' . gb_refund_quote_reason_text($gb_c_quote) . '). It usually takes 5 to 10 working days to show. We have emailed you a copy.';
            } elseif ($gb_c_refund_error !== '') {
                $gb_c_notice = 'Booking cancelled. We could not send your ' . gb_refund_money($gb_c_refund_due) . ' refund automatically, so our team has been told and will sort it out for you. You do not need to do anything.';
            } elseif ($gb_c_paid && $gb_c_quote && $gb_c_quote['reason'] === 'none') {
                $gb_c_notice = 'Booking cancelled. No refund is due (' . gb_refund_quote_reason_text($gb_c_quote) . ').';
            } else {
                $gb_c_notice = 'Booking cancelled.';
            }
            $msg = $gb_c_refund_error !== ''
                ? '<div class="gb-card" style="background:#fffbeb; color:#92400e; padding:15px; margin-bottom:20px;">' . esc_html($gb_c_notice) . '</div>'
                : '<div class="gb-card" style="background:var(--color-success); color:var(--text-success); padding:15px; margin-bottom:20px;">' . esc_html($gb_c_notice) . '</div>';
        }
    }
}

// Fetch Bookings
$booking_ids = [];
$by_author = get_posts([
    'post_type' => 'gb_booking',
    'author' => $user_id,
    'posts_per_page' => -1,
    'post_status' => ['publish', 'pending'],
    'fields' => 'ids',
]);
$by_email = get_posts([
    'post_type' => 'gb_booking',
    'meta_key' => '_gb_customer_email',
    'meta_value' => $user_email,
    'posts_per_page' => -1,
    'post_status' => ['publish', 'pending'],
    'fields' => 'ids',
]);

$booking_ids = array_values(array_unique(array_merge($by_author, $by_email)));
$upcoming = [];
$history = [];

if (!empty($booking_ids)) {
    foreach ($booking_ids as $id) {
        $post_status = get_post_status($id);
        if ($post_status === 'trash') {
            continue;
        }

        $ts = strtotime(get_post_meta($id, '_gb_booking_date', true) . ' ' . get_post_meta($id, '_gb_booking_time', true));
        $flow_status = $gb_resolve_booking_flow($id, $post_status ?: '');
        $status_display = $gb_flow_label($flow_status, $ts);

        $data = [
            'id'=>$id, 
            'service'=>get_post_meta($id, '_gb_booking_service', true),
            'cleaner'=>get_the_title(get_post_meta($id, '_gb_booking_cleaner_id', true)),
            'date'=>date('M j, Y', $ts), 'time'=>date('g:i A', $ts),
            'status'=> $flow_status,
            'status_label' => $status_display['label'],
            'status_class' => $status_display['class'],
            'ts'=>$ts,
            'refunded' => (float) get_post_meta($id, '_gb_refund_amount_gbp', true),
            'refund_status' => (string) get_post_meta($id, '_gb_refund_status', true),
        ];

        if($ts >= current_time('timestamp')) {
            $upcoming[] = $data;
        } else {
            $history[] = $data;
        }
    }
}
usort($upcoming, function($a, $b) { return $a['ts'] - $b['ts']; });
usort($history, function($a, $b) { return $b['ts'] - $a['ts']; });

/*
 * Membership.
 *
 * Until now a paying member had nowhere to see what they were paying
 * for, and no way to change a card or stop - which is not a reasonable
 * position to take real money from somebody in. Managing it happens in
 * Stripe's billing portal rather than in screens built here: cancelling
 * is the one thing that must not be subtly wrong.
 */
$gb_acc_user_id = get_current_user_id();
$gb_acc_member  = function_exists('gb_get_membership') ? gb_get_membership($gb_acc_user_id) : ['status' => 'none', 'plan' => '', 'expires' => ''];
$gb_acc_active  = $gb_acc_member['status'] === 'active';
$gb_acc_plans   = function_exists('gb_membership_plans') ? gb_membership_plans() : [];
$gb_acc_plan    = isset($gb_acc_plans[$gb_acc_member['plan']]) ? $gb_acc_plans[$gb_acc_member['plan']]['label'] : '';
$gb_acc_renews  = $gb_acc_member['expires'] !== '' && strtotime($gb_acc_member['expires'])
    ? date_i18n('j F Y', strtotime($gb_acc_member['expires']))
    : '';

// Cancelled, but paid up until the period ends. Still active, and must
// not be described as renewing.
$gb_acc_cancels_raw = isset($gb_acc_member['cancels_at']) ? $gb_acc_member['cancels_at'] : '';
$gb_acc_cancels     = $gb_acc_cancels_raw !== '' && strtotime($gb_acc_cancels_raw)
    ? date_i18n('j F Y', strtotime($gb_acc_cancels_raw))
    : '';
$gb_acc_ending      = $gb_acc_active && $gb_acc_cancels !== '';
$gb_acc_pending = function_exists('gb_membership_get_pending_plan') ? gb_membership_get_pending_plan($gb_acc_user_id) : '';
$gb_acc_cat     = function_exists('gb_subscription_catalogue') ? gb_subscription_catalogue() : [];

$gb_billing_notice = isset($_GET['gb_billing']) ? sanitize_key(wp_unslash($_GET['gb_billing'])) : '';
$gb_billing_detail = isset($_GET['gb_billing_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['gb_billing_msg']))) : '';
$gb_billing_copy   = [
    'already' => ['info',  'You already have an active membership. You can change or cancel it below.'],
    'expired' => ['error', 'That page had been open a while, so we stopped before anything was changed. Please try again.'],
    'error'   => ['error', $gb_billing_detail !== '' ? $gb_billing_detail : 'We could not open your billing page.'],
];

/*
 * Notices are built before anything is drawn, because whether there are any
 * decides the layout.
 *
 * The cards are deliberately pulled up 50px so the first one overlaps the
 * green banner - that is the design. A notice landing in that slot inherits
 * the overlap and ends up sitting half on the green and half off it, which is
 * where "You already have an active membership" was appearing.
 *
 * So notices get their own row below the banner, and the overlap moves to
 * whichever block is actually first.
 */
$gb_notices = trim(
    (string) $msg
    . (function_exists('gb_password_notice_html') ? gb_password_notice_html() : '')
);

if (isset($gb_billing_copy[$gb_billing_notice])) {
    ob_start(); ?>
    <div id="gb-billing" class="gb-membership-notice gb-membership-notice-<?php echo esc_attr($gb_billing_copy[$gb_billing_notice][0]); ?>" style="margin-bottom:0;">
        <?php echo esc_html($gb_billing_copy[$gb_billing_notice][1]); ?>
        <?php if ($gb_billing_notice === 'error'): ?>
            <br><strong>Nothing has been charged or changed.</strong>
            Try again, and if it keeps happening
            <a href="<?php echo esc_url(home_url('/contact-support/')); ?>" style="color:inherit; text-decoration:underline;">tell us</a>
            and we will sort your membership out for you.
        <?php endif; ?>
    </div>
    <?php
    $gb_notices .= ob_get_clean();
}

$gb_has_notices = trim($gb_notices) !== '';
?>

<div class="gb-page-wrapper" style="padding-top:0;">
    <div class="gb-hero-sm">
        <div class="gb-container text-center">
            <h1>Welcome, <?php echo esc_html($first_name); ?></h1>
            <p>Manage your home cleaning appointments.</p>
        </div>
    </div>

    <?php if ($gb_has_notices): ?>
        <div class="gb-container" style="margin-top:24px; position:relative; z-index:10;">
            <?php echo $gb_notices; ?>
        </div>
    <?php endif; ?>

    <div class="gb-container" style="margin-top:<?php echo $gb_has_notices ? '24px' : '-50px'; ?>; position:relative; z-index:10;">
        <div class="gb-card mb-20">
            <div class="gb-card-head">
                <span class="gb-card-title">Membership</span>
                <?php if ($gb_acc_ending): ?>
                    <span class="gb-badge gb-badge-warning">Ending</span>
                <?php elseif ($gb_acc_active): ?>
                    <span class="gb-badge gb-badge-success">Active</span>
                <?php endif; ?>
            </div>
            <div style="padding:20px;">
                <?php if ($gb_acc_active): ?>
                    <p style="margin:0 0 16px; color:#475569;">
                        <?php if ($gb_acc_plan !== ''): ?>You are on the <strong><?php echo esc_html($gb_acc_plan); ?></strong> plan.<?php else: ?>Your membership is active.<?php endif; ?>
                        <?php if ($gb_acc_ending): ?>
                            You have cancelled it, so it will <strong>not</strong> renew. You keep everything you have paid for until <strong><?php echo esc_html($gb_acc_cancels); ?></strong>, and it stops then.
                        <?php elseif ($gb_acc_renews !== ''): ?>
                            It renews on <strong><?php echo esc_html($gb_acc_renews); ?></strong>.
                        <?php endif; ?>
                    </p>
                    <form method="post" action="<?php echo esc_url(home_url('/my-account/')); ?>" style="margin:0;">
                        <input type="hidden" name="gb_membership_action" value="portal">
                        <?php wp_nonce_field('gb_membership_portal', 'gb_membership_nonce'); ?>
                        <button type="submit" class="gb-btn gb-btn-primary gb-btn-sm"><?php echo $gb_acc_ending ? 'Restart or manage' : 'Manage or cancel'; ?></button>
                    </form>
                    <p style="margin:10px 0 0; font-size:.82rem; color:#94a3b8;">
                        <?php if ($gb_acc_ending): ?>
                            Changed your mind? Turn renewal back on, or update your card, from the same page.
                        <?php else: ?>
                            Change your card, switch plan, download a receipt or stop your membership.
                        <?php endif; ?>
                    </p>

                <?php elseif ($gb_acc_pending !== '' && isset($gb_acc_cat[$gb_acc_pending])): ?>
                    <p style="margin:0 0 16px; color:#475569;">
                        You picked <strong><?php echo esc_html($gb_acc_cat[$gb_acc_pending]['label']); ?></strong> before creating your account. Finish whenever you are ready.
                    </p>
                    <form method="post" action="<?php echo esc_url(home_url('/my-account/')); ?>" style="margin:0;">
                        <input type="hidden" name="gb_membership_action" value="checkout">
                        <input type="hidden" name="gb_plan" value="<?php echo esc_attr($gb_acc_pending); ?>">
                        <?php wp_nonce_field('gb_membership_checkout', 'gb_membership_nonce'); ?>
                        <button type="submit" class="gb-btn gb-btn-primary gb-btn-sm">Finish signing up</button>
                    </form>

                <?php else: ?>
                    <p style="margin:0 0 16px; color:#475569;">
                        <?php if ($gb_acc_member['status'] === 'expired'): ?>
                            Your membership has ended<?php echo $gb_acc_renews !== '' ? ' (' . esc_html($gb_acc_renews) . ')' : ''; ?>. You can start again whenever you like.
                        <?php elseif ($gb_acc_member['status'] === 'cancelled'): ?>
                            Your membership was cancelled. You can start again whenever you like.
                        <?php else: ?>
                            You do not have a membership yet.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo esc_url(home_url('/cleaner-listings#membership')); ?>" class="gb-btn gb-btn-primary gb-btn-sm">See membership options</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="gb-card mb-20">
            <div class="gb-card-head">
                <span class="gb-card-title">Upcoming Appointments</span>
                <a href="<?php echo home_url('/cleaner-listings'); ?>" class="gb-btn gb-btn-primary gb-btn-sm">+ New Booking</a>
            </div>
            
            <?php if(!empty($upcoming)): ?>
                <div class="gb-table-responsive">
                    <table class="gb-table">
                        <thead><tr><th>Date</th><th>Service</th><th>Professional</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach($upcoming as $job): ?>
                            <tr>
                                <td><strong><?php echo $job['date']; ?></strong><br><small><?php echo $job['time']; ?></small></td>
                                <td><?php echo esc_html($job['service']); ?></td>
                                <td><?php echo esc_html($job['cleaner']); ?></td>
                                <td>
                                    <span class="gb-badge <?php echo esc_attr($job['status_class']); ?>"><?php echo esc_html($job['status_label']); ?></span>
                                    <?php if ($job['status'] === 'cancelled' && $job['refunded'] > 0): ?>
                                        <br><small style="color:#166534;">Refunded £<?php echo esc_html(number_format($job['refunded'], 2)); ?></small>
                                    <?php elseif ($job['status'] === 'cancelled' && $job['refund_status'] === 'failed'): ?>
                                        <br><small style="color:#92400e;">Refund being sorted by our team</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!in_array($job['status'], ['cancelled'], true)): ?>
                                        <?php
                                        /*
                                         * What they get back, said before they press it.
                                         * Worked out from the booking's own records
                                         * (no call to Stripe for every row); the cancel
                                         * itself checks Stripe and will not give back
                                         * less than this without asking again.
                                         */
                                        $gb_job_quote = function_exists('gb_booking_refund_quote') ? gb_booking_refund_quote($job['id'], null, false) : null;
                                        $gb_job_offer = $gb_job_quote ? gb_refund_quote_summary($gb_job_quote) : '';
                                        ?>
                                        <form method="post" action="<?php echo esc_url(home_url('/my-account/')); ?>" onsubmit="return confirm('<?php echo esc_js(trim('Cancel this booking? ' . $gb_job_offer)); ?>');">
                                            <input type="hidden" name="gb_cancel_id" value="<?php echo esc_attr($job['id']); ?>">
                                            <?php if ($gb_job_quote): ?>
                                                <input type="hidden" name="gb_cancel_expected" value="<?php echo esc_attr($gb_job_quote['amount_pence']); ?>">
                                            <?php endif; ?>
                                            <?php wp_nonce_field('gb_cancel_booking', 'gb_cancel_nonce'); ?>
                                            <button class="gb-btn gb-btn-danger gb-btn-sm">Cancel</button>
                                            <?php if ($gb_job_offer !== ''): ?>
                                                <small style="display:block; margin-top:6px; max-width:220px; color:#64748b; line-height:1.4;"><?php echo esc_html($gb_job_offer); ?></small>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No upcoming bookings.</p>
            <?php endif; ?>
        </div>

        <div class="gb-card">
            <div class="gb-card-head"><span class="gb-card-title">History</span></div>
            <?php if(!empty($history)): ?>
                <div class="gb-table-responsive">
                    <table class="gb-table">
                        <thead><tr><th>Date</th><th>Service</th><th>Professional</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach($history as $job): ?>
                            <tr>
                                <td><?php echo $job['date']; ?></td>
                                <td><?php echo esc_html($job['service']); ?></td>
                                <td><?php echo esc_html($job['cleaner']); ?></td>
                                <td>
                                    <span class="gb-badge <?php echo esc_attr($job['status_class']); ?>"><?php echo esc_html($job['status_label']); ?></span>
                                    <?php if ($job['status'] === 'cancelled' && $job['refunded'] > 0): ?>
                                        <br><small style="color:#166534;">Refunded £<?php echo esc_html(number_format($job['refunded'], 2)); ?></small>
                                    <?php elseif ($job['status'] === 'cancelled' && $job['refund_status'] === 'failed'): ?>
                                        <br><small style="color:#92400e;">Refund being sorted by our team</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No booking history yet.</p>
            <?php endif; ?>
        </div>

        <div class="gb-card" style="margin-top:20px;">
            <div class="gb-card-head"><span class="gb-card-title">Customer Information</span></div>
            <p class="text-muted" style="margin-top:0;">Helpful information for preparing for an eco-cleaning visit.</p>
            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <a href="<?php echo esc_url(function_exists('gb_get_resource_url') ? gb_get_resource_url('make-the-most-of-your-eco-cleaner') : '#'); ?>" target="_blank" rel="noopener" class="gb-btn gb-btn-secondary gb-btn-sm">Make the Most of Your Eco-Cleaner</a>
                <a href="<?php echo esc_url(function_exists('gb_get_resource_url') ? gb_get_resource_url('certificate-of-insurance') : '#'); ?>" target="_blank" rel="noopener" class="gb-btn gb-btn-secondary gb-btn-sm">Insurance Certificate</a>
                <a href="<?php echo esc_url(function_exists('gb_get_resource_url') ? gb_get_resource_url('subscribers-terms-of-association') : '#'); ?>" target="_blank" rel="noopener" class="gb-btn gb-btn-secondary gb-btn-sm">Subscribers' Terms of Association</a>
            </div>
        </div>

        <div class="gb-card mb-20" style="margin-top:20px;">
            <div class="gb-card-head"><span class="gb-card-title">Change Password</span></div>
            <form method="post" action="<?php echo esc_url(home_url('/my-account/')); ?>" style="padding:20px; max-width:420px;">
                <input type="hidden" name="gb_pw_action" value="change_password">
                <input type="hidden" name="gb_pw_redirect" value="<?php echo esc_url(home_url('/my-account/')); ?>">
                <?php wp_nonce_field('gb_change_password', 'gb_pw_nonce'); ?>
                <div style="margin-bottom:12px;">
                    <input type="password" name="gb_current_password" placeholder="Current Password" required autocomplete="current-password" style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:8px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <input type="password" name="gb_new_password" placeholder="New Password (min. 8 characters)" required minlength="8" autocomplete="new-password" style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:8px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <input type="password" name="gb_confirm_password" placeholder="Confirm New Password" required minlength="8" autocomplete="new-password" style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:8px; box-sizing:border-box;">
                </div>
                <button type="submit" class="gb-btn gb-btn-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>
<?php get_footer(); ?>
