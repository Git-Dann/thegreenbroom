<?php
/*
 * Template Name: About Us (How We Do It)
 */

get_header();

// --- DATA RETRIEVAL ---
// A. Hero
$hero_bg    = get_post_meta(get_the_ID(), '_gb_about_hero_bg', true)    ?: 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1920&q=80';
$hero_title = get_post_meta(get_the_ID(), '_gb_about_hero_title', true) ?: 'Cleaning Up The Cleaning Industry.';
$hero_sub   = get_post_meta(get_the_ID(), '_gb_about_hero_sub', true)   ?: 'We believe in fair pay, non-toxic products, and a spotless finish. No compromises.';

// NEW: Trust Bar
$trust_banner = get_post_meta(get_the_ID(), '_gb_trust_banner', true);

// B. Story Section
$story_head = get_post_meta(get_the_ID(), '_gb_story_head', true)       ?: 'Why We Started';
$story_lead = get_post_meta(get_the_ID(), '_gb_story_lead', true)       ?: 'Finding a reliable cleaner used to be a gamble. Finding one who cared about the environment was nearly impossible.';
$story_body = get_post_meta(get_the_ID(), '_gb_story_body', true)       ?: "We built The Green Broom to solve two massive problems: homeowners were tired of harsh chemicals and unreliable service, and cleaners were tired of low wages and poor treatment.\n\nBy cutting out the expensive agencies and using smart technology, we connect you directly.";
$story_img  = get_post_meta(get_the_ID(), '_gb_story_img', true)        ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png';

// C. Stats
$stat1_num   = get_post_meta(get_the_ID(), '_gb_stat1_num', true)   ?: '100%';
$stat1_label = get_post_meta(get_the_ID(), '_gb_stat1_label', true) ?: 'Eco-Friendly';
$stat2_num   = get_post_meta(get_the_ID(), '_gb_stat2_num', true)   ?: '£15+';
$stat2_label = get_post_meta(get_the_ID(), '_gb_stat2_label', true) ?: 'Avg. Hourly Wage';
$stat3_num   = get_post_meta(get_the_ID(), '_gb_stat3_num', true)   ?: '0';
$stat3_label = get_post_meta(get_the_ID(), '_gb_stat3_label', true) ?: 'Hidden Fees';

// D. Vetting Process
$vet_head     = get_post_meta(get_the_ID(), '_gb_vet_head', true)     ?: 'Trust is Everything.';
$vet_sub      = get_post_meta(get_the_ID(), '_gb_vet_sub', true)      ?: 'We invite less than 10% of applicants to join our platform. Here is how we ensure only the best enter your home.';

$vet_s1_title = get_post_meta(get_the_ID(), '_gb_vet_s1_title', true) ?: 'ID & Right to Work Checks';
$vet_s1_desc  = get_post_meta(get_the_ID(), '_gb_vet_s1_desc', true)  ?: 'We verify government-issued ID and proof of address for every single applicant.';

$vet_s2_title = get_post_meta(get_the_ID(), '_gb_vet_s2_title', true) ?: 'Reference Verification';
$vet_s2_desc  = get_post_meta(get_the_ID(), '_gb_vet_s2_desc', true)  ?: 'We speak to previous employers or clients. If they haven\'t delivered 5-star service before, they won\'t be on The Green Broom.';

$vet_s3_title = get_post_meta(get_the_ID(), '_gb_vet_s3_title', true) ?: 'The "Green" Interview';
$vet_s3_desc  = get_post_meta(get_the_ID(), '_gb_vet_s3_desc', true)  ?: 'We test their knowledge of eco-friendly cleaning methods.';

$vet_s4_title = get_post_meta(get_the_ID(), '_gb_vet_s4_title', true) ?: 'Ongoing Monitoring';
$vet_s4_desc  = get_post_meta(get_the_ID(), '_gb_vet_s4_desc', true)  ?: 'Verification doesn\'t stop at signup. We monitor ratings after every job.';

// E. Bottom CTA
$cta_head   = get_post_meta(get_the_ID(), '_gb_about_cta_head', true)   ?: 'Join the Cleaning Revolution';
$cta_sub    = get_post_meta(get_the_ID(), '_gb_about_cta_sub', true)    ?: 'Whether you want a spotless home or a flexible career.';
$cta_btn1   = get_post_meta(get_the_ID(), '_gb_about_btn1_text', true)  ?: 'Find a Cleaner';
$cta_link1  = get_post_meta(get_the_ID(), '_gb_about_btn1_link', true)  ?: '/cleaner-listings';
$cta_btn2   = get_post_meta(get_the_ID(), '_gb_about_btn2_text', true)  ?: 'Join as a Cleaner';
$cta_link2  = get_post_meta(get_the_ID(), '_gb_about_btn2_link', true)  ?: '/register';
?>

<style>
    /* RESET CONTAINER */
    #gb-about-page { width: 100vw; position: relative; left: 50%; right: 50%; margin-left: -50vw; margin-right: -50vw; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; overflow-x: hidden; color: #333; }
    .gb-container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
    .gb-section { padding: 90px 0; }
    .gb-bg-white { background-color: white; }
    .gb-heading-lg { font-size: 3rem; font-weight: 800; margin-bottom: 25px; line-height: 1.1; color: #1b5e20; }
    .gb-heading-md { font-size: 2.2rem; font-weight: 700; margin-bottom: 20px; color: #333; }
    .gb-text-lead { font-size: 1.2rem; color: #555; line-height: 1.6; margin-bottom: 30px; }

    /* HERO */
    .gb-about-hero { background: linear-gradient(rgba(27, 94, 32, 0.9), rgba(27, 94, 32, 0.8)), url('<?php echo esc_url($hero_bg); ?>'); background-size: cover; background-position: center; padding: 140px 20px; text-align: center; color: white; }
    .gb-about-hero h1 { font-size: 3.5rem; font-weight: 800; color: white !important; margin-bottom: 20px; }
    .gb-about-hero p { font-size: 1.4rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.5; }

    /* NEW TRUST BAR STYLE */
    .gb-trust-bar { background: #e8f5e9; padding: 20px 0; text-align: center; border-bottom: 1px solid #c8e6c9; }
    .gb-trust-text { font-weight: 600; color: #2e7d32; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

    /* STORY */
    .gb-story-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }

    /* STATS */
    .gb-stats-bar { background: #e8f5e9; padding: 60px 0; text-align: center; }
    .gb-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; max-width: 900px; margin: 0 auto; }
    .gb-stat-num { font-size: 3rem; font-weight: 800; color: #2e7d32; display: block; margin-bottom: 5px; }
    .gb-vet-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:24px; }

    /* FLOATING CTA STYLE */
    .gb-cta-banner { background: transparent; padding: 80px 20px; text-align: center; }
    .gb-cta-banner .gb-container { background: #1b5e20; color: white; padding: 80px 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(27, 94, 32, 0.2); }
    .gb-btn { padding: 15px 35px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; transition: transform 0.2s; }
    .gb-btn-white { background: white; color: #1b5e20 !important; }
    .gb-btn-white:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

    @media (max-width: 900px) {
        .gb-story-grid, .gb-stats-grid, .gb-vet-grid { grid-template-columns: 1fr; gap: 40px; }
    }
</style>

<div id="gb-about-page">
    <section class="gb-about-hero">
        <div class="gb-container">
            <h1><?php echo esc_html($hero_title); ?></h1>
            <p><?php echo esc_html($hero_sub); ?></p>
        </div>
    </section>

    <?php if($trust_banner): ?>
    <div class="gb-trust-bar"><span class="gb-trust-text"><?php echo esc_html($trust_banner); ?></span></div>
    <?php endif; ?>

    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <div class="gb-story-grid">
                <div>
                    <h2 class="gb-heading-lg"><?php echo esc_html($story_head); ?></h2>
                    <p class="gb-text-lead"><?php echo esc_html($story_lead); ?></p>
                    <div style="color:#555; line-height:1.7; margin-bottom:30px; white-space: pre-line;">
                        <?php echo wp_kses_post($story_body); ?>
                    </div>
                </div>
                <img src="<?php echo esc_url($story_img); ?>" class="gb-story-img" alt="Our Story" style="width: 100%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            </div>
        </div>
    </section>

    <div class="gb-stats-bar">
        <div class="gb-container">
            <div class="gb-stats-grid">
                <div><span class="gb-stat-num"><?php echo esc_html($stat1_num); ?></span><span style="font-weight:600; text-transform:uppercase; color:#555;"><?php echo esc_html($stat1_label); ?></span></div>
                <div><span class="gb-stat-num"><?php echo esc_html($stat2_num); ?></span><span style="font-weight:600; text-transform:uppercase; color:#555;"><?php echo esc_html($stat2_label); ?></span></div>
                <div><span class="gb-stat-num"><?php echo esc_html($stat3_num); ?></span><span style="font-weight:600; text-transform:uppercase; color:#555;"><?php echo esc_html($stat3_label); ?></span></div>
            </div>
        </div>
    </div>

    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <div style="text-align:center; max-width:780px; margin:0 auto 45px;">
                <h2 class="gb-heading-md"><?php echo esc_html($vet_head); ?></h2>
                <p class="gb-text-lead" style="margin-bottom:0;"><?php echo esc_html($vet_sub); ?></p>
            </div>
            <div class="gb-vet-grid">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
                    <h3 style="margin:0 0 10px; color:#1b5e20; font-size:20px;"><?php echo esc_html($vet_s1_title); ?></h3>
                    <p style="margin:0; color:#475569; line-height:1.6;"><?php echo esc_html($vet_s1_desc); ?></p>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
                    <h3 style="margin:0 0 10px; color:#1b5e20; font-size:20px;"><?php echo esc_html($vet_s2_title); ?></h3>
                    <p style="margin:0; color:#475569; line-height:1.6;"><?php echo esc_html($vet_s2_desc); ?></p>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
                    <h3 style="margin:0 0 10px; color:#1b5e20; font-size:20px;"><?php echo esc_html($vet_s3_title); ?></h3>
                    <p style="margin:0; color:#475569; line-height:1.6;"><?php echo esc_html($vet_s3_desc); ?></p>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
                    <h3 style="margin:0 0 10px; color:#1b5e20; font-size:20px;"><?php echo esc_html($vet_s4_title); ?></h3>
                    <p style="margin:0; color:#475569; line-height:1.6;"><?php echo esc_html($vet_s4_desc); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="gb-cta-banner">
        <div class="gb-container">
            <h2 style="font-size:3rem; margin-bottom:20px; font-weight:800; color:white"><?php echo esc_html($cta_head); ?></h2>
            <p style="font-size:1.3rem; margin-bottom:40px; opacity:0.9;"><?php echo esc_html($cta_sub); ?></p>
            <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">
                <a href="<?php echo esc_url($cta_link1); ?>" class="gb-btn gb-btn-white"><?php echo esc_html($cta_btn1); ?></a>
                <a href="<?php echo esc_url($cta_link2); ?>" class="gb-btn" style="border:2px solid white; color:white;"><?php echo esc_html($cta_btn2); ?></a>
            </div>
        </div>
    </section>
</div>

<?php get_footer(); ?>
