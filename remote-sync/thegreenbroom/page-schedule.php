<?php
/* Template Name: Dashboard - Schedule */
if ( ! is_user_logged_in() ) {
    wp_redirect(home_url('/login'));
    exit;
}
get_header(); 
?>

<style>
    #gb-page-wrapper { width: 100vw; position: relative; left: 50%; margin-left: -50vw; background: #f4f6f8; min-height: 100vh; font-family: sans-serif; }
    .gb-hero-sm { background: #1b5e20; padding: 60px 0 100px; color: white; text-align: center; }
    .gb-container { max-width: 900px; margin: -50px auto 50px; position: relative; padding: 0 20px; }
    .gb-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    
    .gb-back-link { display: inline-flex; align-items: center; color: white; text-decoration: none; font-weight: 600; margin-bottom: 20px; opacity: 0.8; }
    .gb-back-link:hover { opacity: 1; text-decoration: underline; }

    .gb-day-row { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #eee; padding: 15px 0; }
    .gb-day-name { font-weight: 700; width: 120px; }
    .gb-time-select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
    
    /* Switch Style */
    .gb-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .gb-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #2e7d32; }
    input:checked + .slider:before { transform: translateX(24px); }
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
        <form class="gb-card">
            <?php 
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach($days as $day): 
            ?>
            <div class="gb-day-row">
                <div style="display:flex; align-items:center;">
                    <label class="gb-switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                    <span class="gb-day-name" style="margin-left:15px;"><?php echo $day; ?></span>
                </div>
                
                <div style="display:flex; gap:10px; align-items:center;">
                    <select class="gb-time-select"><option>09:00 AM</option><option>08:00 AM</option></select>
                    <span>to</span>
                    <select class="gb-time-select"><option>05:00 PM</option><option>06:00 PM</option></select>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" style="background: #2e7d32; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">Update Schedule</button>
            </div>
        </form>
    </div>
</div>
<?php get_footer(); ?>
