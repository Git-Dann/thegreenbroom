<?php
/*
 * Template Name: What We Do (Services)
 */

get_header();

// --- DATA RETRIEVAL (RIGID STRUCTURE) ---

// A. Hero
$hero_bg    = get_post_meta(get_the_ID(), '_gb_svc_hero_bg', true)    ?: 'https://images.unsplash.com/photo-1584622050111-993a426fbf0a?auto=format&fit=crop&w=1920&q=80';
$hero_title = get_post_meta(get_the_ID(), '_gb_svc_hero_title', true) ?: 'More Than Just a Clean Home';
$hero_sub   = get_post_meta(get_the_ID(), '_gb_svc_hero_sub', true)   ?: 'Professional, reliable, and 100% eco-friendly services tailored to your lifestyle.';

// NEW: Trust Bar Meta
$trust_banner = get_post_meta(get_the_ID(), '_gb_trust_banner', true);

// B. Services Grid
$svc_head   = get_post_meta(get_the_ID(), '_gb_svc_head', true)       ?: 'Our Cleaning Services';
$svc_sub    = get_post_meta(get_the_ID(), '_gb_svc_sub', true)        ?: 'Choose the level of clean that fits your needs. All services use plant-based, non-toxic products.';

// Service 1 (Standard)
$s1_title   = get_post_meta(get_the_ID(), '_gb_svc_1_title', true)    ?: 'Standard Clean';
$s1_desc    = get_post_meta(get_the_ID(), '_gb_svc_1_desc', true)     ?: 'Perfect for keeping your home fresh on a weekly or fortnightly basis.';
$s1_list    = get_post_meta(get_the_ID(), '_gb_svc_1_list', true)     ?: "Dusting & wiping surfaces\nVacuuming & mopping floors\nKitchen counters\nBathroom sanitation\nEmptying bins";
$s1_btn     = get_post_meta(get_the_ID(), '_gb_svc_1_btn', true)      ?: 'Find Standard Cleaners';
$s1_link    = get_post_meta(get_the_ID(), '_gb_svc_1_link', true)     ?: '/cleaner-listings?service=standard';

// Service 2 (Deep)
$s2_title   = get_post_meta(get_the_ID(), '_gb_svc_2_title', true)    ?: 'Deep Clean';
$s2_desc    = get_post_meta(get_the_ID(), '_gb_svc_2_desc', true)     ?: 'A thorough top-to-bottom refresh. Ideal for spring cleaning.';
$s2_list    = get_post_meta(get_the_ID(), '_gb_svc_2_list', true)     ?: "Includes everything in Standard\nInside oven & fridge\nSkirting boards\nLimescale removal\nBehind furniture";
$s2_btn     = get_post_meta(get_the_ID(), '_gb_svc_2_btn', true)      ?: 'Find Deep Cleaners';
$s2_link    = get_post_meta(get_the_ID(), '_gb_svc_2_link', true)     ?: '/cleaner-listings?service=deep';

// Service 3 (Move)
$s3_title   = get_post_meta(get_the_ID(), '_gb_svc_3_title', true)    ?: 'Move In / Out';
$s3_desc    = get_post_meta(get_the_ID(), '_gb_svc_3_desc', true)     ?: 'Ensure you get your deposit back. An intensive clean for empty properties.';
$s3_list    = get_post_meta(get_the_ID(), '_gb_svc_3_list', true)     ?: "Deep clean of all rooms\nInside cupboards\nWindow cleaning (interior)\nAppliance deep cleans\nSpot wall cleaning";
$s3_btn     = get_post_meta(get_the_ID(), '_gb_svc_3_btn', true)      ?: 'Find Move Cleaners';
$s3_link    = get_post_meta(get_the_ID(), '_gb_svc_3_link', true)     ?: '/cleaner-listings?service=moving';

// C. Mission Split
$mis_img    = get_post_meta(get_the_ID(), '_gb_svc_mis_img', true)    ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png';
$mis_title  = get_post_meta(get_the_ID(), '_gb_svc_mis_title', true)  ?: 'The "Green" in Green Broom';
$mis_sub    = get_post_meta(get_the_ID(), '_gb_svc_mis_sub', true)    ?: "We believe a clean home shouldn't come at the cost of the environment.";
$mis_body   = get_post_meta(get_the_ID(), '_gb_svc_mis_body', true)   ?: "Traditional cleaning supplies are often packed with volatile organic compounds (VOCs).\n\nWe mandate that every cleaner uses non-toxic, biodegradable products.";
$mis_btn    = get_post_meta(get_the_ID(), '_gb_svc_mis_btn', true)    ?: 'Join Our Mission';
$mis_link   = get_post_meta(get_the_ID(), '_gb_svc_mis_link', true)   ?: '/join';

// D. Bottom CTA
$cta_title  = get_post_meta(get_the_ID(), '_gb_svc_cta_title', true)  ?: 'Ready to book?';
$cta_sub    = get_post_meta(get_the_ID(), '_gb_svc_cta_sub', true)    ?: 'Find a vetted professional in your area today.';
$cta_btn    = get_post_meta(get_the_ID(), '_gb_svc_cta_btn', true)    ?: 'Browse Cleaners';
$cta_link   = get_post_meta(get_the_ID(), '_gb_svc_cta_link', true)   ?: '/cleaner-listings';
?>

<style>
    /* RESET CONTAINER */
    #gb-services-page { width: 100vw; position: relative; left: 50%; right: 50%; margin-left: -50vw; margin-right: -50vw; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; overflow-x: hidden; color: #333; }
    .gb-container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
    .gb-text-center { text-align: center; }
    .gb-section { padding: 90px 0; }
    .gb-bg-light { background-color: #f9f9f9; }
    .gb-bg-green { background-color: #1b5e20; color: white; }
    .gb-heading-lg { font-size: 3rem; font-weight: 800; margin-bottom: 20px; line-height: 1.1; }
    .gb-heading-md { font-size: 2.2rem; font-weight: 700; margin-bottom: 20px; color: #1b5e20; }
    .gb-text-lead { font-size: 1.2rem; color: #555; line-height: 1.6; max-width: 700px; margin: 0 auto 40px; }

    /* HERO */
    .gb-svc-hero { background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?php echo esc_url($hero_bg); ?>'); background-size: cover; background-position: center; padding: 140px 20px; text-align: center; color: white; }
    .gb-svc-hero h1 { font-size: 3.5rem; font-weight: 800; color: white !important; margin-bottom: 20px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }

    /* NEW TRUST BAR STYLE */
    .gb-trust-bar { background: #e8f5e9; padding: 20px 0; text-align: center; border-bottom: 1px solid #c8e6c9; }
    .gb-trust-text { font-weight: 600; color: #2e7d32; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

    /* CARDS */
    .gb-svc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 50px; }
    .gb-svc-card { background: white; border: 1px solid #eee; border-radius: 16px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
    .gb-svc-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-color: #2e7d32; }
    .gb-svc-header { background: #e8f5e9; padding: 40px 20px; text-align: center; }
    .gb-svc-icon { font-size: 3rem; display: block; margin-bottom: 15px; }
    .gb-svc-title { font-size: 1.5rem; font-weight: 700; color: #1b5e20; margin: 0; }
    .gb-svc-body { padding: 30px; flex-grow: 1; }
    .gb-svc-desc { color: #666; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.6; }
    .gb-check-list-sm { list-style: none; padding: 0; margin: 0; }
    .gb-check-list-sm li { margin-bottom: 10px; padding-left: 25px; position: relative; font-size: 0.9rem; color: #444; }
    .gb-check-list-sm li:before { content: '✓'; position: absolute; left: 0; color: #2e7d32; font-weight: 700; }
    .gb-svc-footer { padding: 20px 30px 30px; text-align: center; }

    /* MISSION SPLIT */
    .gb-split-mission { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
    .gb-mission-img { width: 100%; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }

    /* UPDATED FLOATING CTA STYLE */
    .gb-cta-banner { background: transparent; padding: 80px 20px; text-align: center; }
    .gb-cta-banner .gb-container { background: #1b5e20; color: white; padding: 80px 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(27, 94, 32, 0.2); }
    .gb-btn { padding: 12px 30px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; transition: all 0.2s; font-size: 0.95rem; }
    .gb-btn-primary { background: #2e7d32; color: white !important; }
    .gb-btn-primary:hover { background: #1b5e20; transform: translateY(-2px); }
    .gb-btn-outline { border: 2px solid #2e7d32; color: #2e7d32 !important; background: transparent; }
    .gb-btn-outline:hover { background: #2e7d32; color: white !important; }
    .gb-btn-white { background: white; color: #1b5e20 !important; }

    @media (max-width: 900px) {
        .gb-svc-grid, .gb-split-mission { grid-template-columns: 1fr; gap: 40px; }
        .gb-svc-hero h1 { font-size: 2.5rem; }
    }
</style>

<div id="gb-services-page">
    <section class="gb-svc-hero">
        <div class="gb-container">
            <h1><?php echo esc_html($hero_title); ?></h1>
            <p style="font-size:1.3rem; opacity:0.9; max-width:600px; margin:0 auto;"><?php echo esc_html($hero_sub); ?></p>
        </div>
    </section>

    <?php if($trust_banner): ?>
    <div class="gb-trust-bar"><span class="gb-trust-text"><?php echo esc_html($trust_banner); ?></span></div>
    <?php endif; ?>

    <section class="gb-section gb-bg-light">
        <div class="gb-container">
            <div class="gb-text-center"><h2 class="gb-heading-md"><?php echo esc_html($svc_head); ?></h2><p class="gb-text-lead"><?php echo esc_html($svc_sub); ?></p></div>
            <div class="gb-svc-grid">
                <div class="gb-svc-card">
                    <div class="gb-svc-header"><span class="gb-svc-icon">✨</span><h3 class="gb-svc-title"><?php echo esc_html($s1_title); ?></h3></div>
                    <div class="gb-svc-body"><p class="gb-svc-desc"><?php echo esc_html($s1_desc); ?></p>
                        <ul class="gb-check-list-sm">
                            <?php foreach(explode("\n", $s1_list) as $item): if(trim($item)): ?>
                                <li><?php echo esc_html(trim($item)); ?></li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                    <div class="gb-svc-footer"><a href="<?php echo esc_url($s1_link); ?>" class="gb-btn gb-btn-outline" style="width:100%;"><?php echo esc_html($s1_btn); ?></a></div>
                </div>
                <div class="gb-svc-card">
                    <div class="gb-svc-header"><span class="gb-svc-icon">🧽</span><h3 class="gb-svc-title"><?php echo esc_html($s2_title); ?></h3></div>
                    <div class="gb-svc-body"><p class="gb-svc-desc"><?php echo esc_html($s2_desc); ?></p>
                        <ul class="gb-check-list-sm">
                            <?php foreach(explode("\n", $s2_list) as $item): if(trim($item)): ?>
                                <li><?php echo esc_html(trim($item)); ?></li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                    <div class="gb-svc-footer"><a href="<?php echo esc_url($s2_link); ?>" class="gb-btn gb-btn-primary" style="width:100%;"><?php echo esc_html($s2_btn); ?></a></div>
                </div>
                <div class="gb-svc-card">
                    <div class="gb-svc-header"><span class="gb-svc-icon">📦</span><h3 class="gb-svc-title"><?php echo esc_html($s3_title); ?></h3></div>
                    <div class="gb-svc-body"><p class="gb-svc-desc"><?php echo esc_html($s3_desc); ?></p>
                        <ul class="gb-check-list-sm">
                            <?php foreach(explode("\n", $s3_list) as $item): if(trim($item)): ?>
                                <li><?php echo esc_html(trim($item)); ?></li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                    <div class="gb-svc-footer"><a href="<?php echo esc_url($s3_link); ?>" class="gb-btn gb-btn-outline" style="width:100%;"><?php echo esc_html($s3_btn); ?></a></div>
                </div>
            </div>
        </div>
    </section>

    <section class="gb-section">
        <div class="gb-container">
            <div class="gb-split-mission">
                <img src="<?php echo esc_url($mis_img); ?>" class="gb-mission-img" alt="Mission">
                <div>
                    <h2 class="gb-heading-md"><?php echo esc_html($mis_title); ?></h2>
                    <p class="gb-text-lead" style="margin:0 0 20px; text-align:left;"><?php echo esc_html($mis_sub); ?></p>
                    <div style="color:#666; line-height:1.6; margin-bottom:30px; white-space:pre-line;"><?php echo wp_kses_post($mis_body); ?></div>
                    <a href="<?php echo esc_url($mis_link); ?>" class="gb-btn gb-btn-primary"><?php echo esc_html($mis_btn); ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="gb-cta-banner">
        <div class="gb-container">
            <h2 style="color:white; font-size:2.5rem; font-weight:800; margin-bottom:20px;"><?php echo esc_html($cta_title); ?></h2>
            <p style="color:rgba(255,255,255,0.9); font-size:1.2rem; margin-bottom:40px;"><?php echo esc_html($cta_sub); ?></p>
            <a href="<?php echo esc_url($cta_link); ?>" class="gb-btn gb-btn-white"><?php echo esc_html($cta_btn); ?></a>
        </div>
    </section>
</div>

<?php get_footer(); ?>