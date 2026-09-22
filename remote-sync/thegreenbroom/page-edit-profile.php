<?php
/* Template Name: Edit Profile */

if (!is_user_logged_in()) { wp_redirect(home_url('/login')); exit; }
$current_user = wp_get_current_user();

// 1. Find Profile Directly (No helper function needed)
$cleaner_query = new WP_Query([
    'post_type' => 'gb_cleaner',
    'author' => $current_user->ID,
    'posts_per_page' => 1,
    'post_status' => ['publish', 'pending', 'draft'],
]);

if (!$cleaner_query->have_posts()) {
    get_header(); echo '<div style="padding:100px; text-align:center;">Error: No profile found.</div>'; get_footer(); exit;
}
$cleaner_id = $cleaner_query->posts[0]->ID;

// 2. Save Logic
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_save'])) {
    if (empty($_POST['gb_edit_profile_nonce']) || !wp_verify_nonce($_POST['gb_edit_profile_nonce'], 'gb_edit_profile')) {
        $msg = '<div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px;">Security check failed. Please refresh and try again.</div>';
    } else {
        $bio = isset($_POST['bio']) ? wp_kses_post(wp_unslash($_POST['bio'])) : '';
        $rate = isset($_POST['rate']) ? sanitize_text_field(wp_unslash($_POST['rate'])) : '';
        $loc = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';

        wp_update_post(['ID' => $cleaner_id, 'post_content' => $bio]);
        update_post_meta($cleaner_id, 'hourly_rate', $rate);
        update_post_meta($cleaner_id, 'cleaner_location', $loc);
        update_post_meta($cleaner_id, 'location', $loc);

        /*
         * Services the cleaner offers. Stored as an array of published
         * gb_service TITLES, because that is exactly what the listings filter
         * matches against and what the public profile renders. Anything not in
         * the current catalogue is discarded, so a stale or hand-crafted POST
         * cannot write a value that will never match.
         */
        $gb_catalogue = wp_list_pluck(get_posts([
            'post_type'   => 'gb_service',
            'post_status' => 'publish',
            'numberposts' => -1,
        ]), 'post_title');
        $gb_chosen = isset($_POST['gb_services']) && is_array($_POST['gb_services'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['gb_services']))
            : [];
        update_post_meta($cleaner_id, 'gb_services', array_values(array_intersect($gb_catalogue, $gb_chosen))); 

        $msg = '<div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px;">✅ Saved successfully!</div>';
    }
}

// 3. Get Data
$bio = get_post_field('post_content', $cleaner_id);
$rate = get_post_meta($cleaner_id, 'hourly_rate', true);
$loc = get_post_meta($cleaner_id, 'cleaner_location', true);

get_header(); 
?>

<style>
    .gb-edit-wrap { max-width: 800px; margin: 60px auto; padding: 20px; font-family: sans-serif; }
    .gb-card { background: white; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .gb-label { display: block; font-weight: 700; margin-bottom: 8px; color: #333; }
    .gb-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-sizing: border-box; }
    .gb-btn { background: #10b981; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; cursor: pointer; }

    /* Rate and Location were an uncollapsing two-column grid, leaving each
       field about 110px wide on a small handset. */
    .gb-profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .gb-service-picker { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; margin-bottom: 22px; }
    .gb-service-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 9px; background: #fff; cursor: pointer; font-size: 14px; }
    .gb-service-option:hover { border-color: #10b981; background: #f0fdf4; }
    .gb-service-option input { width: 17px; height: 17px; flex: 0 0 auto; margin: 0; }

    @media (max-width: 640px) {
        .gb-edit-wrap { margin: 28px auto; padding: 14px; }
        .gb-card { padding: 22px 18px; }
        .gb-profile-grid { grid-template-columns: 1fr; gap: 0; }
        .gb-btn { width: 100%; }
    }
</style>

<div class="gb-edit-wrap">
    <a href="<?php echo home_url('/cleaner-dashboard'); ?>" style="text-decoration:none; color:#666;">← Back to Dashboard</a>
    <br><br>
    <?php echo $msg; ?>
    <div class="gb-card">
        <h1 style="margin-top:0;">Edit Profile</h1>
        <form method="post">
            <input type="hidden" name="gb_save" value="1">
            <?php wp_nonce_field('gb_edit_profile', 'gb_edit_profile_nonce'); ?>
            
            <label>About Me</label>
            <textarea name="bio" class="gb-input" style="height:150px;"><?php echo esc_textarea($bio); ?></textarea>
            
            <div class="gb-profile-grid">
                <div><label>Hourly Rate (£)</label><input type="number" name="rate" class="gb-input" value="<?php echo esc_attr($rate); ?>"></div>
                <div><label>Location</label><input type="text" name="location" class="gb-input" value="<?php echo esc_attr($loc); ?>"></div>
            </div>

            <?php
            $gb_services_all = get_posts([
                'post_type'   => 'gb_service',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
            ]);
            $gb_mine = get_post_meta($cleaner_id, 'gb_services', true);
            $gb_mine = is_array($gb_mine) ? $gb_mine : [];
            ?>
            <?php if (!empty($gb_services_all)): ?>
                <label>Services you offer</label>
                <p style="margin:-4px 0 12px; color:#64748b; font-size:13px;">
                    Customers filter by these. If you tick none, you will not appear when someone searches for a specific service.
                </p>
                <div class="gb-service-picker">
                    <?php foreach ($gb_services_all as $gb_sv): ?>
                        <label class="gb-service-option">
                            <input type="checkbox" name="gb_services[]" value="<?php echo esc_attr($gb_sv->post_title); ?>" <?php checked(in_array($gb_sv->post_title, $gb_mine, true)); ?>>
                            <span><?php echo esc_html($gb_sv->post_title); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="gb-btn">Save Changes</button>
        </form>
    </div>
</div>
<?php get_footer(); ?>
