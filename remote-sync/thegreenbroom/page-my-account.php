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

        if (get_post_type($bid) === 'gb_booking' && $can_cancel) {
            update_post_meta($bid, '_gb_booking_status', 'cancelled');
            $msg = '<div class="gb-card" style="background:var(--color-success); color:var(--text-success); padding:15px; margin-bottom:20px;">Booking cancelled.</div>';
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
            'ts'=>$ts
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
?>

<div class="gb-page-wrapper" style="padding-top:0;">
    <div class="gb-hero-sm">
        <div class="gb-container text-center">
            <h1>Welcome, <?php echo esc_html($first_name); ?></h1>
            <p>Manage your home cleaning appointments.</p>
        </div>
    </div>

    <div class="gb-container" style="margin-top:-50px; position:relative; z-index:10;">
        <?php echo $msg; ?>
        <?php echo function_exists('gb_password_notice_html') ? gb_password_notice_html() : ''; ?>

        <?php
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
        $gb_acc_pending = function_exists('gb_membership_get_pending_plan') ? gb_membership_get_pending_plan($gb_acc_user_id) : '';
        $gb_acc_cat     = function_exists('gb_subscription_catalogue') ? gb_subscription_catalogue() : [];

        $gb_billing_notice = isset($_GET['gb_billing']) ? sanitize_key(wp_unslash($_GET['gb_billing'])) : '';
        $gb_billing_detail = isset($_GET['gb_billing_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['gb_billing_msg']))) : '';
        $gb_billing_copy   = [
            'already' => ['info',  'You already have an active membership. You can change or cancel it below.'],
            'expired' => ['error', 'That page had been open a while, so we stopped. Please try again.'],
            'error'   => ['error', $gb_billing_detail !== '' ? $gb_billing_detail : 'We could not open your billing page. Please try again.'],
        ];
        ?>

        <?php if (isset($gb_billing_copy[$gb_billing_notice])): ?>
            <div class="gb-membership-notice gb-membership-notice-<?php echo esc_attr($gb_billing_copy[$gb_billing_notice][0]); ?>" style="margin-bottom:20px;">
                <?php echo esc_html($gb_billing_copy[$gb_billing_notice][1]); ?>
            </div>
        <?php endif; ?>

        <div class="gb-card mb-20">
            <div class="gb-card-head">
                <span class="gb-card-title">Membership</span>
                <?php if ($gb_acc_active): ?>
                    <span class="gb-badge gb-badge-success">Active</span>
                <?php endif; ?>
            </div>
            <div style="padding:20px;">
                <?php if ($gb_acc_active): ?>
                    <p style="margin:0 0 16px; color:#475569;">
                        <?php if ($gb_acc_plan !== ''): ?>You are on the <strong><?php echo esc_html($gb_acc_plan); ?></strong> plan.<?php else: ?>Your membership is active.<?php endif; ?>
                        <?php if ($gb_acc_renews !== ''): ?> It renews on <strong><?php echo esc_html($gb_acc_renews); ?></strong>.<?php endif; ?>
                    </p>
                    <form method="post" action="<?php echo esc_url(home_url('/my-account/')); ?>" style="margin:0;">
                        <input type="hidden" name="gb_membership_action" value="portal">
                        <?php wp_nonce_field('gb_membership_portal', 'gb_membership_nonce'); ?>
                        <button type="submit" class="gb-btn gb-btn-primary gb-btn-sm">Manage or cancel</button>
                    </form>
                    <p style="margin:10px 0 0; font-size:.82rem; color:#94a3b8;">Change your card, switch plan, download a receipt or stop your membership.</p>

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
                                <td><span class="gb-badge <?php echo esc_attr($job['status_class']); ?>"><?php echo esc_html($job['status_label']); ?></span></td>
                                <td>
                                    <?php if(!in_array($job['status'], ['cancelled'], true)): ?>
                                        <form method="post" action="<?php echo esc_url(home_url('/my-account/')); ?>" onsubmit="return confirm('Cancel this booking?');">
                                            <input type="hidden" name="gb_cancel_id" value="<?php echo $job['id']; ?>">
                                            <?php wp_nonce_field('gb_cancel_booking', 'gb_cancel_nonce'); ?>
                                            <button class="gb-btn gb-btn-danger gb-btn-sm">Cancel</button>
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
                                <td><span class="gb-badge <?php echo esc_attr($job['status_class']); ?>"><?php echo esc_html($job['status_label']); ?></span></td>
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
