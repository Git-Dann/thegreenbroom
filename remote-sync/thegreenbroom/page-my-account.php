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
                                        <form method="post" onsubmit="return confirm('Cancel this booking?');">
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
    </div>
</div>
<?php get_footer(); ?>
