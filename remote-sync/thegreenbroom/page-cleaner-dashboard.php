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

$cleaner_id = 0; $profile_link = '#'; $is_verified = false; $earnings = '0.00'; $jobs_done = 0; $rating = '–'; // No reviews yet: a dash, not an invented 5.0. $view_count = 0;
$sub_status = 'trial'; 

if ( $cleaner_query->have_posts() ) {
    $post = $cleaner_query->posts[0];
    $cleaner_id = $post->ID;
    $profile_link = get_permalink( $cleaner_id );
    $hourly_rate = get_post_meta($cleaner_id, 'hourly_rate', true) ?: 15;
    /*
 * Same rule the customer sees. Deriving this from post status alone meant the
 * dashboard said "Verified" while the public badge was withheld, with nothing
 * telling the cleaner which check was outstanding.
 */
$is_live      = ($post->post_status === 'publish');
$is_verified  = function_exists('gb_cleaner_is_fully_verified')
    ? gb_cleaner_is_fully_verified($post->ID)
    : $is_live;
    
    // Get Subscription Status from profile meta
    $sub_status = get_post_meta($cleaner_id, '_gb_subscription_status', true) ?: 'trial'; 
    
    /*
     * The real rating, computed from approved reviews - the same figure the
     * public profile shows.
     *
     * This used to read _gb_rating, a number typed by hand into the manager
     * dashboard. A cleaner could be told they were rated 4.8 while their own
     * public profile said "no reviews yet".
     */
    $rating_data  = function_exists('gb_get_cleaner_rating') ? gb_get_cleaner_rating($cleaner_id) : ['average' => 0.0, 'rated' => 0];
    $rating_value = (float) $rating_data['average'];
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
            <?php if($is_live): ?>
                <?php // Live means the profile exists and is bookable; the badge is separate. ?>
                <a href="<?php echo esc_url($profile_link); ?>" target="_blank" class="gb-btn gb-btn-secondary">View Public Profile ↗</a>
            <?php else: ?>
                <span class="gb-badge gb-badge-warning">Profile Pending</span>
            <?php endif; ?>
        </div>
    </div>

    <?php
    /*
     * A cleaner who cannot be booked has to be told, on the screen they
     * actually open.
     *
     * The listing says "Not taking bookings" to customers and the manager
     * dashboard shows the Connect status, so everybody could see this except
     * the one person who can fix it. A cleaner signs up, completes their
     * profile, gets verified, and then waits for work that cannot reach them,
     * with nothing anywhere telling them why.
     *
     * Only shown while it is true, and it names the next action rather than
     * the internal reason - "Stripe onboarding incomplete" is our vocabulary,
     * not theirs.
     */
    $gb_block = function_exists('gb_cleaner_booking_block_reason')
        ? gb_cleaner_booking_block_reason($cleaner_id)
        : true;
    ?>
    <?php if ($gb_block !== true): ?>
        <div class="gb-container" style="margin-top:-50px; position:relative; z-index:10;">
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:20px 22px; margin-bottom:20px;">
                <div style="font-weight:800; color:#92400e; font-size:16px; margin-bottom:6px;">
                    <?php echo $gb_block === 'paused' ? 'Online booking is paused for now' : 'Customers cannot book you yet'; ?>
                </div>
                <p style="margin:0 0 14px; color:#92400e; font-size:14px; line-height:1.65;">
                    <?php if ($gb_block === 'paused'): ?>
                        The team has paused online booking across The Green Broom for a short while. There is nothing
                        you need to do: your profile stays up, and customers can book you again as soon as it is back on.
                    <?php elseif ($gb_block === 'listing_inactive'): ?>
                        Your listing subscription is not active, so customers cannot find or book you. Restart it
                        from Become a Cleaner and your profile goes straight back on.
                    <?php elseif ($gb_block === 'not_live'): ?>
                        Your profile has not been approved yet, so it is not on the site. We will let you know as soon
                        as your checks are done.
                    <?php elseif ($gb_block === 'fully_booked'): ?>
                        You have marked yourself as fully booked, so new customers cannot book you. Change your
                        availability on Edit Profile when you have space again.
                    <?php elseif ($gb_block === 'not_connected'): ?>
                        Your profile is live, but we have no way to pay you, so the Book Now button is hidden on your
                        listing. Setting up payouts takes a few minutes and is done securely with Stripe, who handle
                        the payments &mdash; we never see your bank details.
                    <?php else: ?>
                        You have started setting up payouts but Stripe still needs something from you. Until that is
                        finished we cannot pay you, so the Book Now button stays hidden on your listing. Picking up
                        where you left off usually takes a couple of minutes.
                    <?php endif; ?>
                </p>
                <?php
                $gb_block_cta = [
                    'listing_inactive' => ['/join/', 'Restart my listing'],
                    'not_live'         => ['/verify-profile/', 'Check my verification'],
                    'fully_booked'     => ['/edit-profile/', 'Update my availability'],
                    'not_connected'    => ['/settings/', 'Set up payouts'],
                ];
                list($gb_cta_url, $gb_cta_label) = isset($gb_block_cta[$gb_block]) ? $gb_block_cta[$gb_block] : ['/settings/', 'Finish setting up payouts'];
                ?>
                <?php if ($gb_block !== 'paused'): ?>
                    <a href="<?php echo esc_url(home_url($gb_cta_url)); ?>" class="gb-btn gb-btn-primary" style="text-decoration:none;"><?php echo esc_html($gb_cta_label); ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="gb-container" style="margin-top:<?php echo $gb_block !== true ? '0' : '-50px'; ?>; position:relative; z-index:10;">
        <?php
        /*
         * Not a requirement, so not a warning: a friendly nudge. Listings
         * without a photo fall back to a grey silhouette and look noticeably
         * worse beside those with one, and the client asked for cleaners to be
         * encouraged to add one. Disappears as soon as a photo is set.
         */
        ?>
        <?php if ($cleaner_id && !has_post_thumbnail($cleaner_id)): ?>
            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:18px 22px; margin-bottom:20px;">
                <div style="font-size:2rem; line-height:1;">📸</div>
                <div style="flex:1 1 260px;">
                    <div style="font-weight:800; color:#166534; font-size:16px; margin-bottom:4px;">Add a profile photo</div>
                    <p style="margin:0; color:#166534; font-size:14px; line-height:1.6;">
                        Your listing does not have a photo yet. Listings with a photo look far better and get more
                        bookings, so it is well worth a minute. A clear, friendly photo of your face works best.
                    </p>
                </div>
                <a href="<?php echo esc_url(home_url('/edit-profile/#photo')); ?>" class="gb-btn gb-btn-primary" style="text-decoration:none; white-space:nowrap;">Add your photo</a>
            </div>
        <?php endif; ?>
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
                    <?php elseif($is_live): ?>
                        <span class="gb-badge" style="background:#fffbeb; color:#92400e; border:1px solid #fde68a;">Checks due</span>
                    <?php else: ?>
                        <span class="gb-badge gb-badge-danger">Action Required</span>
                    <?php endif; ?>
                </div>
                <h3 class="gb-card-title">Get Verified</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Photo ID, a Basic DBS and two references.</p>
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
