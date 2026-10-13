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

        /*
         * All three location keys, the same set the manager dashboard writes.
         * This screen used to write only two, so _gb_location kept whatever a
         * manager had typed months earlier - and the search matches on every
         * key, which is how a Liverpool cleaner was still turning up under
         * "Manchester".
         */
        gb_set_cleaner_location($cleaner_id, $loc);

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

        /*
         * Availability. Passed through unconditionally, including when nothing
         * is ticked: a cleaner who has stopped working Saturdays has to be able
         * to say so, and unchecked boxes submit nothing at all.
         */
        if (function_exists('gb_save_availability_slots')) {
            gb_save_availability_slots($cleaner_id, isset($_POST['gb_availability_slots']) ? $_POST['gb_availability_slots'] : []);
        }

        /*
         * Profile photo. Optional, and only touched when a file was chosen, so
         * saving the rest of the form never removes a photo. A failed upload
         * still saves everything else; the cleaner is told what went wrong.
         */
        $gb_photo_error = '';
        if (!empty($_FILES['gb_profile_photo']['name']) && function_exists('gb_save_cleaner_profile_photo')) {
            $gb_photo_result = gb_save_cleaner_profile_photo($cleaner_id, $_FILES['gb_profile_photo']);
            if (is_wp_error($gb_photo_result)) {
                $gb_photo_error = $gb_photo_result->get_error_message();
            }
        }

        if ($gb_photo_error !== '') {
            $msg = '<div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px;">Your other changes are saved, but your photo was not: ' . esc_html($gb_photo_error) . '</div>';
        } else {
            $msg = '<div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px;">✅ Saved successfully!</div>';
        }
    }
}

// 3. Get Data
$bio = get_post_field('post_content', $cleaner_id);
$rate = get_post_meta($cleaner_id, 'hourly_rate', true);
$loc = gb_get_cleaner_location($cleaner_id);
$gb_photo_url = get_the_post_thumbnail_url($cleaner_id, 'medium') ?: '';

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
    .gb-photo-row { display: flex; gap: 20px; align-items: center; margin-bottom: 24px; padding: 18px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
    .gb-photo-row.missing { background: #f0fdf4; border-color: #bbf7d0; }
    .gb-photo-preview { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; flex: 0 0 auto; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.08); background: #e2e8f0; display: flex; align-items: center; justify-content: center; }

    @media (max-width: 640px) {
        .gb-edit-wrap { margin: 28px auto; padding: 14px; }
        .gb-card { padding: 22px 18px; }
        .gb-profile-grid { grid-template-columns: 1fr; gap: 0; }
        .gb-btn { width: 100%; }
        .gb-photo-row { flex-direction: column; text-align: center; }
    }
</style>

<div class="gb-edit-wrap">
    <a href="<?php echo home_url('/cleaner-dashboard'); ?>" style="text-decoration:none; color:#666;">← Back to Dashboard</a>
    <br><br>
    <?php echo $msg; ?>
    <div class="gb-card">
        <h1 style="margin-top:0;">Edit Profile</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="gb_save" value="1">
            <?php wp_nonce_field('gb_edit_profile', 'gb_edit_profile_nonce'); ?>

            <div id="photo" class="gb-photo-row <?php echo $gb_photo_url ? '' : 'missing'; ?>">
                <?php if ($gb_photo_url): ?>
                    <img src="<?php echo esc_url($gb_photo_url); ?>" class="gb-photo-preview" alt="Your profile photo">
                <?php else: ?>
                    <div class="gb-photo-preview"><span class="dashicons dashicons-camera" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span></div>
                <?php endif; ?>
                <div style="flex:1 1 auto;">
                    <label style="display:block; font-weight:700; margin-bottom:4px;">Profile photo</label>
                    <?php if ($gb_photo_url): ?>
                        <p style="margin:0 0 10px; color:#64748b; font-size:13px;">This is the photo customers see on your listing. Choose a new one to replace it.</p>
                    <?php else: ?>
                        <p style="margin:0 0 10px; color:#166534; font-size:13px; line-height:1.55;">
                            You have not added a photo yet. It is optional, but listings with a photo look far better and get more bookings.
                            A clear, friendly photo of your face works best.
                        </p>
                    <?php endif; ?>
                    <input type="file" name="gb_profile_photo" accept="image/jpeg,image/png,image/webp">
                    <p style="margin:6px 0 0; color:#94a3b8; font-size:12px;">JPG, PNG or WebP, up to 8MB.</p>
                </div>
            </div>

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

            <?php if (function_exists('gb_render_availability_editor')): ?>
                <label>When you are usually free</label>
                <p style="margin:-4px 0 12px; color:#64748b; font-size:13px;">
                    Customers see this on your profile. Leave it all unticked and it simply will not show.
                </p>
                <?php gb_render_availability_editor($cleaner_id); ?>
            <?php endif; ?>

            <button type="submit" class="gb-btn">Save Changes</button>
        </form>
    </div>
</div>
<?php get_footer(); ?>
