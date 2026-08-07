<?php
// --- 1. DATA AGGREGATION ---
$current_user = wp_get_current_user();
$user_name = !empty($current_user->first_name) ? $current_user->first_name : $current_user->display_name;
$current_hour = (int) current_time('G');
$greeting = ($current_hour < 12) ? 'Good Morning' : (($current_hour < 18) ? 'Good Afternoon' : 'Good Evening');

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

// Professional Stats
$cleaner_counts = wp_count_posts('gb_cleaner');
$active_pros = (int) $cleaner_counts->publish;
$pending_pros = (int) $cleaner_counts->pending;

// Subscription Stats
$subs_query = new WP_Query([
    'post_type'      => 'gb_cleaner',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'meta_query'     => [
        ['key' => '_gb_subscription_status', 'value' => 'active'],
    ],
]);
$active_subs_count = count($subs_query->posts);
$mrr_estimate = $active_subs_count * 25; // £25/mo model
wp_reset_postdata();

// Booking, Revenue, and Service Mix Stats
$booking_ids = get_posts([
    'post_type'      => 'gb_booking',
    'post_status'    => ['publish', 'pending'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);
$total_jobs = 0;
$total_revenue = 0.0;
$service_counts = [
    'standard'   => 0,
    'deep'       => 0,
    'move'       => 0,
    'commercial' => 0,
];
$now_ts = current_time('timestamp');

foreach ($booking_ids as $booking_id) {
    $post_status = get_post_status($booking_id) ?: '';
    $flow_status = $gb_resolve_booking_flow($booking_id, $post_status);
    $payment_status = sanitize_key((string) get_post_meta($booking_id, '_gb_payment_status', true));
    $booking_ts = strtotime(get_post_meta($booking_id, '_gb_booking_date', true) . ' ' . get_post_meta($booking_id, '_gb_booking_time', true));
    $is_future = $booking_ts >= $now_ts;
    $is_cancelled = $flow_status === 'cancelled';
    $is_countable_job = !$is_cancelled && !$is_future && ($flow_status === 'confirmed' || $payment_status === 'paid');

    if (!$is_countable_job) {
        continue;
    }

    $total_jobs++;
    $stored_total = get_post_meta($booking_id, '_gb_booking_total_gbp', true);
    if (is_numeric($stored_total) && (float) $stored_total > 0) {
        $total_revenue += (float) $stored_total;
    } else {
        $cleaner_id = (int) get_post_meta($booking_id, '_gb_booking_cleaner_id', true);
        $duration = (float) (get_post_meta($booking_id, '_gb_booking_duration', true) ?: 2);
        $rate = (float) (get_post_meta($cleaner_id, 'hourly_rate', true) ?: 15);
        $total_revenue += ($duration * $rate);
    }

    $service_name = strtolower(trim((string) get_post_meta($booking_id, '_gb_booking_service', true)));
    if (strpos($service_name, 'deep') !== false) {
        $service_counts['deep']++;
    } elseif (strpos($service_name, 'move') !== false || strpos($service_name, 'tenancy') !== false) {
        $service_counts['move']++;
    } elseif (strpos($service_name, 'commercial') !== false) {
        $service_counts['commercial']++;
    } else {
        $service_counts['standard']++;
    }
}

$service_total = array_sum($service_counts);
$job_counts = [0, 0, 0, 0];
if ($service_total > 0) {
    $job_counts = [
        round(($service_counts['standard'] / $service_total) * 100),
        round(($service_counts['deep'] / $service_total) * 100),
        round(($service_counts['move'] / $service_total) * 100),
        round(($service_counts['commercial'] / $service_total) * 100),
    ];
}

// Inbox + Helpdesk Stats
$message_ids = get_posts([
    'post_type'      => 'gb_message',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);
$unread_messages = 0;
$active_helpdesk_tickets = 0;

foreach ($message_ids as $message_id) {
    $is_helpdesk = get_post_meta($message_id, '_gb_is_helpdesk', true) === '1';
    $responded_date = trim((string) get_post_meta($message_id, '_gb_responded_date', true));
    $helpdesk_status = sanitize_key((string) get_post_meta($message_id, '_gb_helpdesk_status', true));

    if ($is_helpdesk) {
        if ($helpdesk_status !== 'resolved') {
            $active_helpdesk_tickets++;
        }
        continue;
    }

    if ($responded_date === '') {
        $unread_messages++;
    }
}

// SEO Widget Stats
$post_ids = get_posts([
    'post_type'      => 'post',
    'post_status'    => ['publish', 'draft', 'pending'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);
$seo_total = 0;
$seo_count = 0;
foreach ($post_ids as $post_id) {
    $seo_total += (int) get_post_meta($post_id, '_gb_seo_score', true);
    $seo_count++;
}
$avg_seo_score = $seo_count ? (int) round($seo_total / $seo_count) : 0;
$seo_bar_width = max(0, min(100, $avg_seo_score));

// Content Draft Widget
$latest_draft = get_posts([
    'post_type'      => 'post',
    'post_status'    => ['draft', 'pending'],
    'posts_per_page' => 1,
    'orderby'        => 'modified',
    'order'          => 'DESC',
]);
$latest_draft_post = !empty($latest_draft) ? $latest_draft[0] : null;
$draft_counts = wp_count_posts('post');
$draft_total = (int) $draft_counts->draft + (int) $draft_counts->pending;

// Satisfaction Widget
$cleaner_ids = get_posts([
    'post_type'      => 'gb_cleaner',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);
$rating_weighted_sum = 0.0;
$rating_review_total = 0;
$rating_simple_sum = 0.0;
$rating_simple_count = 0;
foreach ($cleaner_ids as $cleaner_id) {
    $rating = (float) get_post_meta($cleaner_id, '_gb_rating', true);
    $reviews = (int) get_post_meta($cleaner_id, '_gb_review_count', true);

    if ($rating > 0) {
        $rating_simple_sum += $rating;
        $rating_simple_count++;
    }
    if ($rating > 0 && $reviews > 0) {
        $rating_weighted_sum += ($rating * $reviews);
        $rating_review_total += $reviews;
    }
}

if ($rating_review_total > 0) {
    $avg_rating = round($rating_weighted_sum / $rating_review_total, 1);
} elseif ($rating_simple_count > 0) {
    $avg_rating = round($rating_simple_sum / $rating_simple_count, 1);
} else {
    $avg_rating = 0.0;
}

// Page + Service Widgets
$core_slugs = ['home', 'how-it-works', 'how-we-do-it', 'what-we-do', 'cleaner-listings'];
$front_id = (int) get_option('page_on_front');
$core_pages_live = 0;

if ($front_id && get_post_status($front_id) === 'publish') {
    $core_pages_live++;
}
foreach ($core_slugs as $core_slug) {
    $page = get_page_by_path($core_slug);
    if ($page && $page->post_status === 'publish') {
        if ($front_id && (int) $page->ID === $front_id) {
            continue;
        }
        $core_pages_live++;
    }
}

$service_post_counts = wp_count_posts('gb_service');
$active_services_count = (int) $service_post_counts->publish;
?>

<style>
    /* Compact Dashboard UI */
    .gb-overview-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end; }
    .gb-overview-header h1 { font-size: 24px; color: #1b5e20; margin: 0; font-weight: 800; }
    
    /* 4-Column Balanced Grid */
    .gb-grid-overview { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); 
        gap: 20px; 
    }

    /* Standardized Widget Container */
    .gb-widget { 
        background: #ffffff; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        display: flex; 
        flex-direction: column;
        min-height: 180px; 
        transition: transform 0.2s;
    }
    .gb-widget:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .gb-widget-head { 
        padding: 12px 16px; 
        border-bottom: 1px solid #f1f5f9; 
        font-size: 11px; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: #64748b; 
        letter-spacing: 0.5px;
        display: flex;
        justify-content: space-between;
    }
    .gb-widget-body { padding: 16px; flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }

    /* Compact Stat Layout */
    .gb-stat-val { font-size: 28px; font-weight: 800; color: #1b5e20; line-height: 1; }
    .gb-stat-label { font-size: 12px; color: #94a3b8; margin-top: 5px; font-weight: 500; }

    /* Standardized Buttons */
    .gb-quick-action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .gb-btn-small-os { 
        background: #f8fafc; 
        border: 1px solid #e2e8f0; 
        padding: 8px; 
        border-radius: 6px; 
        font-size: 11px; 
        font-weight: 600; 
        text-align: center; 
        color: #334155; 
        text-decoration: none;
    }
    .gb-btn-small-os:hover { border-color: #10b981; color: #10b981; background: #fff; }

    /* Charts */
    .gb-chart-container { display: flex; align-items: flex-end; gap: 10px; height: 60px; margin-top: 5px; }
    .gb-chart-bar { background: #10b981; width: 100%; border-radius: 4px 4px 0 0; }

    @media (max-width: 1400px) { .gb-grid-overview { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 1000px) { .gb-grid-overview { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="gb-overview-header">
    <div>
        <h1><?php echo esc_html($greeting); ?>, <?php echo esc_html($user_name); ?></h1>
        <p style="font-size: 13px; color: #64748b; margin: 5px 0 0;">System Snapshot for <?php echo date('F j, Y'); ?></p>
    </div>
    <a href="<?php echo home_url(); ?>" target="_blank" class="gb-btn-small-os" style="background: #2e7d32; color: #fff; border: none; padding: 10px 20px;">View Live Site ↗</a>
</div>

<div class="gb-grid-overview">
    
    <div class="gb-widget" style="<?php echo ($active_helpdesk_tickets > 0) ? 'border-color: #f59e0b; background: #fffcf0;' : ''; ?>">
        <div class="gb-widget-head">🆘 Support Alerts <span style="<?php echo ($active_helpdesk_tickets > 0) ? 'color: #92400e;' : ''; ?>"><?php echo $active_helpdesk_tickets; ?> Active</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val" style="<?php echo ($active_helpdesk_tickets > 0) ? 'color: #92400e;' : ''; ?>"><?php echo $active_helpdesk_tickets; ?></div>
            <div class="gb-stat-label">Unresolved helpdesk tickets</div>
            <a href="?tab=helpdesk" class="gb-btn-small-os" style="margin-top: 15px; <?php echo ($active_helpdesk_tickets > 0) ? 'background: #f59e0b; color: #fff; border: none;' : ''; ?>">Handle Tickets</a>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">💳 Subscriptions <span>Active</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $active_subs_count; ?></div>
            <div class="gb-stat-label">Active professional memberships</div>
            <div style="font-size: 11px; color: #10b981; font-weight: 700; margin-top: 10px;">£<?php echo number_format($mrr_estimate, 2); ?> MRR Est.</div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">⚡ Verifications <span><?php echo $pending_pros; ?> Alerts</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $pending_pros; ?></div>
            <div class="gb-stat-label">Cleaners awaiting approval</div>
            <a href="?tab=cleaners" class="gb-btn-small-os" style="margin-top: 15px;">Review Queue</a>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">🧾 Job Volume <span>Live</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $total_jobs; ?></div>
            <div class="gb-stat-label">Total completed bookings</div>
            <div style="font-size: 11px; color: #10b981; font-weight: 700; margin-top: 10px;"><?php echo $total_jobs > 0 ? '🟢 Platform is active' : '🟠 Awaiting first completed job'; ?></div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">💰 Gross Revenue <span>Est.</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val">£<?php echo number_format($total_revenue, 2); ?></div>
            <div class="gb-stat-label">Total processed value</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 10px;">Calculated from booking rates</div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">🚀 Quick Launch</div>
        <div class="gb-widget-body">
            <div class="gb-quick-action-grid">
                <a href="?tab=cleaners&action=edit&id=0" class="gb-btn-small-os">Add Pro</a>
                <a href="?tab=blog" class="gb-btn-small-os">New Post</a>
                <a href="?tab=bookings" class="gb-btn-small-os">Jobs</a>
                <a href="?tab=support" class="gb-btn-small-os">Inbox</a>
            </div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">📈 Service Mix</div>
        <div class="gb-widget-body">
            <div class="gb-chart-container">
                <?php foreach($job_counts as $val): ?>
                    <div class="gb-chart-bar" style="height:<?php echo $val; ?>%;"></div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 8px; color: #94a3b8; font-weight: 700;">
                <span>STD</span><span>DEEP</span><span>MOVE</span><span>COMM</span>
            </div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">📩 Inbox <span><?php echo $unread_messages; ?> New</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $unread_messages; ?></div>
            <div class="gb-stat-label">Messages requiring reply</div>
            <a href="?tab=support" class="gb-btn-small-os" style="margin-top: 15px;">Open Inbox</a>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">🚀 SEO Health</div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $avg_seo_score; ?><small style="font-size: 14px; color: #94a3b8;">/100</small></div>
            <div class="gb-stat-label">Average optimization score</div>
            <div style="width: 100%; height: 6px; background: #f1f5f9; margin-top: 12px; border-radius: 3px;">
                <div style="width: <?php echo $seo_bar_width; ?>%; height: 100%; background: #10b981; border-radius: 3px;"></div>
            </div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">📝 Content <span><?php echo $draft_total; ?> Drafts</span></div>
        <div class="gb-widget-body">
            <div style="font-size: 12px; color: #334155;">
                <?php if ($latest_draft_post): ?>
                    <p style="margin: 0 0 5px; font-weight: 700;">"<?php echo esc_html($latest_draft_post->post_title ?: '(Untitled Draft)'); ?>"</p>
                    <span style="color: #94a3b8; font-size: 10px;">Last edited <?php echo esc_html(human_time_diff((int) get_post_modified_time('U', true, $latest_draft_post), current_time('timestamp', true))); ?> ago</span>
                    <a href="?tab=blog&view=edit&id=<?php echo (int) $latest_draft_post->ID; ?>" style="color: #10b981; display: block; margin-top: 8px; font-weight: 700; text-decoration: none;">Continue Writing →</a>
                <?php else: ?>
                    <p style="margin: 0 0 5px; font-weight: 700;">No drafts in progress</p>
                    <span style="color: #94a3b8; font-size: 10px;">Create a new article to start writing.</span>
                    <a href="?tab=blog" style="color: #10b981; display: block; margin-top: 8px; font-weight: 700; text-decoration: none;">Start New Draft →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">🧹 Fleet Size <span>Active</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $active_pros; ?></div>
            <div class="gb-stat-label">Verified professionals live</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 10px; background: #f0fdf4; padding: 4px; border-radius: 4px; text-align: center;">
                Capacity: <strong>~<?php echo $active_pros * 40; ?> hrs/wk</strong>
            </div>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">⭐ Satisfaction <span>Avg.</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo number_format($avg_rating, 1); ?><small style="font-size: 14px; color: #fbbf24;">★</small></div>
            <div class="gb-stat-label">Based on <?php echo (int) $rating_review_total; ?> client reviews</div>
            <a href="?tab=cleaners" class="gb-btn-small-os" style="margin-top: 15px;">View Feedback</a>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">📄 Page Status <span>Core</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $core_pages_live; ?></div>
            <div class="gb-stat-label">Total system pages live</div>
            <a href="?tab=pages" class="gb-btn-small-os" style="margin-top: 15px;">Manage Content</a>
        </div>
    </div>

    <div class="gb-widget">
        <div class="gb-widget-head">🛠️ Services <span>Active</span></div>
        <div class="gb-widget-body">
            <div class="gb-stat-val"><?php echo $active_services_count; ?></div>
            <div class="gb-stat-label">Available service types</div>
            <a href="?tab=services" class="gb-btn-small-os" style="margin-top: 15px;">Configure Rates</a>
        </div>
    </div>

</div>
