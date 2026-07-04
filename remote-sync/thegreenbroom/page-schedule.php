<?php
/* Template Name: Dashboard - Schedule */
if ( ! is_user_logged_in() ) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();

// 1. Find this cleaner's profile
$cleaner_query = new WP_Query([
    'post_type'      => 'gb_cleaner',
    'author'         => $current_user->ID,
    'posts_per_page' => 1,
    'post_status'    => ['publish', 'pending', 'draft'],
]);

if (!$cleaner_query->have_posts()) {
    get_header();
    echo '<div style="padding:100px; text-align:center;">Error: No cleaner profile found for your account.</div>';
    get_footer();
    exit;
}
$cleaner_id = $cleaner_query->posts[0]->ID;

$days = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];

// 2. Save logic
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_save_schedule'])) {
    if (empty($_POST['gb_schedule_nonce']) || !wp_verify_nonce($_POST['gb_schedule_nonce'], 'gb_save_schedule')) {
        $msg = '<div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px;">Security check failed. Please refresh and try again.</div>';
    } else {
        $posted = isset($_POST['availability']) && is_array($_POST['availability']) ? wp_unslash($_POST['availability']) : [];
        $schedule = [];
        foreach ($days as $key => $label) {
            $row     = isset($posted[$key]) && is_array($posted[$key]) ? $posted[$key] : [];
            $enabled = !empty($row['enabled']);
            $start   = isset($row['start']) ? sanitize_text_field($row['start']) : '09:00';
            $end     = isset($row['end'])   ? sanitize_text_field($row['end'])   : '17:00';
            // Keep only HH:MM (24h) values, fall back to defaults if malformed.
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) { $start = '09:00'; }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end))   { $end   = '17:00'; }
            $schedule[$key] = [
                'enabled' => $enabled ? 1 : 0,
                'start'   => $start,
                'end'     => $end,
            ];
        }
        update_post_meta($cleaner_id, '_gb_cleaner_schedule', $schedule);
        $msg = '<div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px;">✅ Schedule updated.</div>';
    }
}

// 3. Load current schedule (with sensible defaults: Mon–Fri 09:00–17:00, weekend off)
$saved = get_post_meta($cleaner_id, '_gb_cleaner_schedule', true);
if (!is_array($saved)) { $saved = []; }
$schedule = [];
foreach ($days as $key => $label) {
    $default_enabled = !in_array($key, ['saturday', 'sunday'], true);
    $schedule[$key] = [
        'enabled' => isset($saved[$key]['enabled']) ? (int) $saved[$key]['enabled'] : ($default_enabled ? 1 : 0),
        'start'   => isset($saved[$key]['start']) ? $saved[$key]['start'] : '09:00',
        'end'     => isset($saved[$key]['end'])   ? $saved[$key]['end']   : '17:00',
    ];
}

get_header();
?>

<style>
    #gb-page-wrapper { width: 100vw; position: relative; left: 50%; margin-left: -50vw; background: #f4f6f8; min-height: 100vh; font-family: sans-serif; }
    #gb-page-wrapper .gb-hero-sm { background: #1b5e20; padding: 60px 0 100px; color: white; text-align: center; }
    #gb-page-wrapper .gb-container { max-width: 900px; margin: -50px auto 50px; position: relative; padding: 0 20px; }
    #gb-page-wrapper .gb-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }

    #gb-page-wrapper .gb-back-link { display: inline-flex; align-items: center; color: white; text-decoration: none; font-weight: 600; margin-bottom: 20px; opacity: 0.8; }
    #gb-page-wrapper .gb-back-link:hover { opacity: 1; text-decoration: underline; }

    #gb-page-wrapper .gb-day-row { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #eee; padding: 15px 0; gap: 15px; flex-wrap: wrap; }
    #gb-page-wrapper .gb-day-name { font-weight: 700; width: 120px; }
    #gb-page-wrapper .gb-time-select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; font-family: inherit; }
    #gb-page-wrapper .gb-times.is-off { opacity: 0.4; pointer-events: none; }

    #gb-page-wrapper .gb-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    #gb-page-wrapper .gb-switch input { opacity: 0; width: 0; height: 0; }
    #gb-page-wrapper .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    #gb-page-wrapper .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    #gb-page-wrapper input:checked + .slider { background-color: #2e7d32; }
    #gb-page-wrapper input:checked + .slider:before { transform: translateX(24px); }
</style>

<div id="gb-page-wrapper">
    <div class="gb-hero-sm">
        <div style="max-width:900px; margin:0 auto; text-align:left; padding:0 20px;">
            <a href="/cleaner-dashboard" class="gb-back-link">← Back to Dashboard</a>
        </div>
        <h1>Weekly Schedule</h1>
        <p>Set the days and times you are available to work.</p>
    </div>

    <div class="gb-container">
        <?php echo $msg; ?>
        <form class="gb-card" method="post" action="">
            <?php wp_nonce_field('gb_save_schedule', 'gb_schedule_nonce'); ?>
            <?php foreach($days as $key => $label):
                $row = $schedule[$key];
            ?>
            <div class="gb-day-row">
                <div style="display:flex; align-items:center;">
                    <label class="gb-switch">
                        <input type="checkbox" class="gb-day-toggle" name="availability[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($row['enabled'], 1); ?>>
                        <span class="slider"></span>
                    </label>
                    <span class="gb-day-name" style="margin-left:15px;"><?php echo esc_html($label); ?></span>
                </div>

                <div class="gb-times <?php echo $row['enabled'] ? '' : 'is-off'; ?>" style="display:flex; gap:10px; align-items:center;">
                    <input type="time" class="gb-time-select" name="availability[<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($row['start']); ?>">
                    <span>to</span>
                    <input type="time" class="gb-time-select" name="availability[<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($row['end']); ?>">
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" name="gb_save_schedule" value="1" style="background: #2e7d32; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.gb-day-toggle').forEach(function(t){
    t.addEventListener('change', function(){
        var times = this.closest('.gb-day-row').querySelector('.gb-times');
        if (times) { times.classList.toggle('is-off', !this.checked); }
    });
});
</script>

<?php get_footer(); ?>
