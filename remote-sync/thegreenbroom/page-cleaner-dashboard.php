<?php
/* Template Name: Cleaner Dashboard */

if ( ! is_user_logged_in() ) { wp_redirect( home_url('/login') ); exit; }
get_header(); 

$current_user = wp_get_current_user();
$first_name   = get_user_meta( $current_user->ID, 'first_name', true ) ?: $current_user->display_name;

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

// Data Query
$args = array('post_type' => 'gb_cleaner', 'author' => $current_user->ID, 'posts_per_page' => 1, 'post_status' => ['publish', 'pending', 'draft']);
$cleaner_query = new WP_Query($args);

$cleaner_id = 0; $profile_link = '#'; $is_verified = false; $earnings = '0.00'; $jobs_done = 0; $rating = '5.0'; $view_count = 0;
$sub_status = 'trial'; 

if ( $cleaner_query->have_posts() ) {
    $post = $cleaner_query->posts[0];
    $cleaner_id = $post->ID;
    $profile_link = get_permalink( $cleaner_id );
    $hourly_rate = get_post_meta($cleaner_id, 'hourly_rate', true) ?: 15;
    $is_verified = ($post->post_status === 'publish');
    
    // Get Subscription Status from profile meta
    $sub_status = get_post_meta($cleaner_id, '_gb_subscription_status', true) ?: 'trial'; 
    
    $rating_value = (float) get_post_meta($cleaner_id, '_gb_rating', true);
    if ($rating_value > 0) {
        $rating = number_format($rating_value, 1, '.', '');
    }

    // Stats Calc
    $bookings = get_posts([
        'post_type' => 'gb_booking',
        'meta_key' => '_gb_booking_cleaner_id',
        'meta_value' => $cleaner_id,
        'numberposts' => -1,
        'post_status' => ['publish', 'pending'],
    ]);
    $now_ts = current_time('timestamp');
    foreach ($bookings as $job) {
        $booking_post_status = get_post_status($job->ID);
        $flow_status = $gb_resolve_booking_flow($job->ID, $booking_post_status ?: '');
        if ($flow_status === 'cancelled') {
            continue;
        }

        $booking_ts = strtotime(get_post_meta($job->ID, '_gb_booking_date', true) . ' ' . get_post_meta($job->ID, '_gb_booking_time', true));
        $payment_status = sanitize_key((string) get_post_meta($job->ID, '_gb_payment_status', true));
        if ($booking_ts < $now_ts && ($flow_status === 'confirmed' || $payment_status === 'paid')) {
            $jobs_done++;

            $cleaner_net = (float) get_post_meta($job->ID, '_gb_cleaner_payout_estimate_gbp', true);
            if ($cleaner_net <= 0) {
                $booking_total = (float) get_post_meta($job->ID, '_gb_booking_total_gbp', true);
                $platform_fee = (float) get_post_meta($job->ID, '_gb_platform_fee_gbp', true);
                if ($booking_total > 0) {
                    $cleaner_net = max(0, $booking_total - $platform_fee);
                } else {
                    $duration = (float) (get_post_meta($job->ID, '_gb_booking_duration', true) ?: 2);
                    $cleaner_net = $duration * $hourly_rate;
                }
            }

            $earnings += $cleaner_net;
        }
    }
    $earnings = number_format($earnings, 2);
    $view_count = (int) get_post_meta($cleaner_id, '_gb_profile_views', true);
}

// Membership Badge Logic
$sub_label = ($sub_status === 'active') ? 'Active Member' : 'Trial Period';
$sub_class = ($sub_status === 'active') ? 'gb-badge-success' : 'gb-badge-warning';
?>

<div class="gb-page-wrapper" style="padding-top:0;">
    
    <div class="gb-hero-sm">
        <div class="gb-container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
            <div style="text-align:left;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <h1 style="margin:0;">Hello, <?php echo esc_html($first_name); ?>!</h1>
                    <span class="gb-badge <?php echo $sub_class; ?>" style="margin-top:5px;"><?php echo $sub_label; ?></span>
                </div>
                <p>Here is what's happening with your business today.</p>
            </div>
            <?php if($is_verified): ?>
                <a href="<?php echo esc_url($profile_link); ?>" target="_blank" class="gb-btn gb-btn-secondary">View Public Profile ↗</a>
            <?php else: ?>
                <span class="gb-badge gb-badge-warning">Profile Pending</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="gb-container" style="margin-top:-50px; position:relative; z-index:10;">
        <div class="gb-grid-4 mb-20">
            <div class="gb-card text-center">
                <span style="font-size:2rem; font-weight:800; color:var(--color-primary); display:block;">£<?php echo $earnings; ?></span>
                <span style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Earnings</span>
            </div>
            <div class="gb-card text-center">
                <span style="font-size:2rem; font-weight:800; color:var(--color-primary); display:block;"><?php echo $jobs_done; ?></span>
                <span style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Jobs Done</span>
            </div>
            <div class="gb-card text-center">
                <span style="font-size:2rem; font-weight:800; color:var(--color-primary); display:block;"><?php echo $rating; ?></span>
                <span style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Rating</span>
            </div>
            <div class="gb-card text-center">
                <span style="font-size:2rem; font-weight:800; color:var(--color-primary); display:block;"><?php echo $view_count; ?></span>
                <span style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Views</span>
            </div>
        </div>

        <h2 class="mb-20">Manage Your Business</h2>
        
        <div class="gb-grid-3">
            <a href="<?php echo home_url('/edit-profile'); ?>" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">👤</div>
                <h3 class="gb-card-title">Edit Profile</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Update bio, photos & rates.</p>
            </a>
            
            <a href="<?php echo home_url('/schedule'); ?>" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">📅</div>
                <h3 class="gb-card-title">Schedule</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Set your availability.</p>
            </a>

            <a href="<?php echo home_url('/inbox'); ?>" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">💬</div>
                <h3 class="gb-card-title">Messages</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Reply to customers.</p>
            </a>

            <a href="<?php echo home_url('/verify-profile'); ?>" class="gb-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:2rem; margin-bottom:15px;">🛡️</div>
                    <?php if($is_verified): ?>
                        <span class="gb-badge gb-badge-success">Verified</span>
                    <?php else: ?>
                        <span class="gb-badge gb-badge-danger">Action Required</span>
                    <?php endif; ?>
                </div>
                <h3 class="gb-card-title">Get Verified</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Upload ID & Proof of Address.</p>
            </a>

            <a href="<?php echo home_url('/settings'); ?>" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">⚙️</div>
                <h3 class="gb-card-title">Settings</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Manage your account and subscription.</p>
            </a>

            <a href="<?php echo home_url('/contact-support'); ?>" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">❓</div>
                <h3 class="gb-card-title">Support</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Contact the team.</p>
            </a>
        </div>

        <h2 class="mb-20" style="margin-top:35px;">Cleaner Resources</h2>
        <div class="gb-grid-3">
            <a href="<?php echo esc_url(home_url('/eco-products')); ?>" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">🌿</div>
                <h3 class="gb-card-title">Eco Cleaning Products Guide</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Open the public guide to eco-friendly cleaning products.</p>
            </a>
            <a href="<?php echo esc_url(function_exists('gb_get_resource_url') ? gb_get_resource_url('cleaner-handbook') : '#'); ?>" target="_blank" rel="noopener" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">📘</div>
                <h3 class="gb-card-title">Cleaner Handbook</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Read the welcome and information booklet.</p>
            </a>
            <a href="<?php echo esc_url(function_exists('gb_get_resource_url') ? gb_get_resource_url('certificate-of-insurance') : '#'); ?>" target="_blank" rel="noopener" class="gb-card">
                <div style="font-size:2rem; margin-bottom:15px;">🛡️</div>
                <h3 class="gb-card-title">Insurance Certificate</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">View The Green Broom insurance certificate.</p>
            </a>
        </div>
    </div>
</div>
<?php get_footer(); ?>
