<?php
/*
 * Template Name: Join (Become a Cleaner)
 */

get_header();

// --- DATA RETRIEVAL ---
// A. Hero
$hero_bg    = get_post_meta(get_the_ID(), '_gb_join_hero_bg', true)    ?: 'https://images.unsplash.com/photo-1581578731117-10d52143b1e8?auto=format&fit=crop&w=1920&q=80';
$hero_title = get_post_meta(get_the_ID(), '_gb_join_hero_title', true) ?: 'Turn cleaning into a career you control.';
$hero_sub   = get_post_meta(get_the_ID(), '_gb_join_hero_sub', true)   ?: 'Set your own rates, choose your own hours, and keep more of what you earn. The Green Broom connects you directly with vetted local customers — no agency taking the biggest cut.';
$hero_btn   = get_post_meta(get_the_ID(), '_gb_join_hero_btn', true)   ?: 'Create Your Free Profile';
$hero_link  = get_post_meta(get_the_ID(), '_gb_join_hero_link', true)  ?: '/register';
$hero_btn2  = get_post_meta(get_the_ID(), '_gb_join_hero_btn2', true)  ?: 'See How It Works';
$hero_link2 = get_post_meta(get_the_ID(), '_gb_join_hero_link2', true) ?: '/what-cleaners-do';

// Trust Bar
$trust_banner = get_post_meta(get_the_ID(), '_gb_trust_banner', true);

// B. Benefits
$ben_head = get_post_meta(get_the_ID(), '_gb_join_ben_head', true) ?: 'Why cleaners choose The Green Broom';
$ben_sub  = get_post_meta(get_the_ID(), '_gb_join_ben_sub', true)  ?: 'We built this platform for cleaners, not agencies. Here is what that means for you.';

$b1_title = get_post_meta(get_the_ID(), '_gb_join_b1_title', true) ?: 'Keep more of what you earn';
$b1_text  = get_post_meta(get_the_ID(), '_gb_join_b1_text', true)  ?: 'No expensive agency middlemen. You set your rates and take home the lion\'s share of every job.';
$b2_title = get_post_meta(get_the_ID(), '_gb_join_b2_title', true) ?: 'Work on your own terms';
$b2_text  = get_post_meta(get_the_ID(), '_gb_join_b2_text', true)  ?: 'Accept only the jobs that fit your schedule. Full-time, part-time, or the odd weekend — it is entirely up to you.';
$b3_title = get_post_meta(get_the_ID(), '_gb_join_b3_title', true) ?: 'Get paid securely, on time';
$b3_text  = get_post_meta(get_the_ID(), '_gb_join_b3_text', true)  ?: 'Customers pay through the platform, so no chasing cash or awkward conversations. Your money lands in your account automatically.';
$b4_title = get_post_meta(get_the_ID(), '_gb_join_b4_title', true) ?: 'Real customers, ready to book';
$b4_text  = get_post_meta(get_the_ID(), '_gb_join_b4_text', true)  ?: 'We bring vetted local homeowners to you. Build your reputation with reviews and let repeat bookings roll in.';

// C. Stats / earnings
$stat1_num   = get_post_meta(get_the_ID(), '_gb_join_stat1_num', true)   ?: '£15+';
$stat1_label = get_post_meta(get_the_ID(), '_gb_join_stat1_label', true) ?: 'Average hourly earnings';
$stat2_num   = get_post_meta(get_the_ID(), '_gb_join_stat2_num', true)   ?: '0%';
$stat2_label = get_post_meta(get_the_ID(), '_gb_join_stat2_label', true) ?: 'Hidden agency fees';
$stat3_num   = get_post_meta(get_the_ID(), '_gb_join_stat3_num', true)   ?: 'You';
$stat3_label = get_post_meta(get_the_ID(), '_gb_join_stat3_label', true) ?: 'Choose your own hours';

// D. Steps
$steps_head = get_post_meta(get_the_ID(), '_gb_join_steps_head', true) ?: 'Start earning in three simple steps';
$s1_title = get_post_meta(get_the_ID(), '_gb_join_s1_title', true) ?: '1. Create your profile';
$s1_desc  = get_post_meta(get_the_ID(), '_gb_join_s1_desc', true)  ?: 'Sign up in minutes. Add your experience, set your rates, and showcase your best work with photos.';
$s2_title = get_post_meta(get_the_ID(), '_gb_join_s2_title', true) ?: '2. Get verified';
$s2_desc  = get_post_meta(get_the_ID(), '_gb_join_s2_desc', true)  ?: 'Pass our quick ID and reference check to earn your Verified badge — the mark customers trust.';
$s3_title = get_post_meta(get_the_ID(), '_gb_join_s3_title', true) ?: '3. Accept jobs & get paid';
$s3_desc  = get_post_meta(get_the_ID(), '_gb_join_s3_desc', true)  ?: 'Receive booking requests from local customers, accept the ones that suit you, and get paid securely.';


// F. Bottom CTA
$cta_head  = get_post_meta(get_the_ID(), '_gb_join_cta_title', true) ?: 'Ready to be your own boss?';
$cta_sub   = get_post_meta(get_the_ID(), '_gb_join_cta_sub', true)   ?: 'Set up your profile in minutes. Nothing to pay for your first three months.';
$cta_btn   = get_post_meta(get_the_ID(), '_gb_join_cta_btn', true)   ?: 'Get Started — It\'s Free';
$cta_link  = get_post_meta(get_the_ID(), '_gb_join_cta_link', true)  ?: '/register';
?>

<style>
    #gb-join-page { width: 100vw; position: relative; left: 50%; right: 50%; margin-left: -50vw; margin-right: -50vw; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; overflow-x: hidden; color: #333; }
    #gb-join-page .gb-container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
    #gb-join-page .gb-section { padding: 90px 0; }
    #gb-join-page .gb-bg-white { background-color: white; }
    #gb-join-page .gb-heading-md { font-size: 2.2rem; font-weight: 700; margin-bottom: 20px; color: #1b5e20; }
    #gb-join-page .gb-text-lead { font-size: 1.2rem; color: #555; line-height: 1.6; margin-bottom: 30px; }

    /* HERO */
    #gb-join-page .gb-join-hero { background: linear-gradient(rgba(27, 94, 32, 0.9), rgba(27, 94, 32, 0.82)), url('<?php echo esc_url($hero_bg); ?>'); background-size: cover; background-position: center; padding: 130px 20px; text-align: center; color: white; }
    #gb-join-page .gb-join-hero h1 { font-size: 3.4rem; font-weight: 800; color: white !important; margin-bottom: 20px; line-height: 1.1; }
    #gb-join-page .gb-join-hero p { font-size: 1.3rem; opacity: 0.95; max-width: 720px; margin: 0 auto 35px; line-height: 1.55; }
    #gb-join-page .gb-hero-btns { display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }

    /* TRUST BAR */
    #gb-join-page .gb-trust-bar { background: #e8f5e9; padding: 20px 0; text-align: center; border-bottom: 1px solid #c8e6c9; }
    #gb-join-page .gb-trust-text { font-weight: 600; color: #2e7d32; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

    /* BENEFITS */
    #gb-join-page .gb-benefits-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 45px; }
    #gb-join-page .gb-benefit-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 30px; }
    #gb-join-page .gb-benefit-card h3 { margin: 0 0 10px; color: #1b5e20; font-size: 1.35rem; }
    #gb-join-page .gb-benefit-card p { margin: 0; color: #475569; line-height: 1.6; }

    /* STATS */
    #gb-join-page .gb-stats-bar { background: #1b5e20; padding: 60px 0; text-align: center; color: white; }
    #gb-join-page .gb-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; max-width: 900px; margin: 0 auto; }
    #gb-join-page .gb-stat-num { font-size: 3rem; font-weight: 800; color: white; display: block; margin-bottom: 8px; }
    #gb-join-page .gb-stat-label { font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; font-size: 0.9rem; }

    /* STEPS */
    #gb-join-page .gb-steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 45px; }
    #gb-join-page .gb-step-card { text-align: center; padding: 20px; }
    #gb-join-page .gb-step-card h3 { color: #1b5e20; font-size: 1.3rem; margin: 0 0 12px; }
    #gb-join-page .gb-step-card p { color: #555; line-height: 1.6; margin: 0; }

    /* TESTIMONIAL */
    #gb-join-page .gb-testi { background: #e8f5e9; text-align: center; }
    #gb-join-page .gb-testi blockquote { font-size: 1.6rem; font-weight: 600; color: #1b5e20; max-width: 800px; margin: 0 auto 20px; line-height: 1.4; }
    #gb-join-page .gb-testi cite { font-style: normal; color: #2e7d32; font-weight: 700; }

    /* CTA */
    #gb-join-page .gb-cta-banner { background: transparent; padding: 80px 20px; text-align: center; }
    #gb-join-page .gb-cta-banner .gb-container { background: #1b5e20; color: white; padding: 80px 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(27, 94, 32, 0.2); }
    #gb-join-page .gb-btn { padding: 15px 35px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; transition: transform 0.2s; }
    #gb-join-page .gb-btn-white { background: white; color: #1b5e20 !important; }
    #gb-join-page .gb-btn-white:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    #gb-join-page .gb-btn-outline { border: 2px solid white; color: white !important; }

    @media (max-width: 900px) {
        #gb-join-page .gb-benefits-grid, #gb-join-page .gb-stats-grid, #gb-join-page .gb-steps-grid { grid-template-columns: 1fr; gap: 24px; }
        #gb-join-page .gb-join-hero h1 { font-size: 2.5rem; }
    }
</style>

<div id="gb-join-page">
    <section class="gb-join-hero">
        <div class="gb-container">
            <h1><?php echo esc_html($hero_title); ?></h1>
            <p><?php echo esc_html($hero_sub); ?></p>
            <div class="gb-hero-btns">
                <a href="<?php echo esc_url($hero_link); ?>" class="gb-btn gb-btn-white"><?php echo esc_html($hero_btn); ?></a>
            </div>
        </div>
    </section>

    <?php if($trust_banner): ?>
    <div class="gb-trust-bar"><span class="gb-trust-text"><?php echo esc_html($trust_banner); ?></span></div>
    <?php endif; ?>

    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <div style="text-align:center; max-width:780px; margin:0 auto;">
                <h2 class="gb-heading-md"><?php echo esc_html($ben_head); ?></h2>
                <p class="gb-text-lead" style="margin-bottom:0;"><?php echo esc_html($ben_sub); ?></p>
            </div>
            <div class="gb-benefits-grid">
                <div class="gb-benefit-card"><h3><?php echo esc_html($b1_title); ?></h3><p><?php echo esc_html($b1_text); ?></p></div>
                <div class="gb-benefit-card"><h3><?php echo esc_html($b2_title); ?></h3><p><?php echo esc_html($b2_text); ?></p></div>
                <div class="gb-benefit-card"><h3><?php echo esc_html($b3_title); ?></h3><p><?php echo esc_html($b3_text); ?></p></div>
                <div class="gb-benefit-card"><h3><?php echo esc_html($b4_title); ?></h3><p><?php echo esc_html($b4_text); ?></p></div>
            </div>
        </div>
    </section>

    <div class="gb-stats-bar">
        <div class="gb-container">
            <div class="gb-stats-grid">
                <div><span class="gb-stat-num"><?php echo esc_html($stat1_num); ?></span><span class="gb-stat-label"><?php echo esc_html($stat1_label); ?></span></div>
                <div><span class="gb-stat-num"><?php echo esc_html($stat2_num); ?></span><span class="gb-stat-label"><?php echo esc_html($stat2_label); ?></span></div>
                <div><span class="gb-stat-num"><?php echo esc_html($stat3_num); ?></span><span class="gb-stat-label"><?php echo esc_html($stat3_label); ?></span></div>
            </div>
        </div>
    </div>

    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <div style="text-align:center; max-width:780px; margin:0 auto;">
                <h2 class="gb-heading-md"><?php echo esc_html($steps_head); ?></h2>
            </div>
            <div class="gb-steps-grid">
                <div class="gb-step-card"><h3><?php echo esc_html($s1_title); ?></h3><p><?php echo esc_html($s1_desc); ?></p></div>
                <div class="gb-step-card"><h3><?php echo esc_html($s2_title); ?></h3><p><?php echo esc_html($s2_desc); ?></p></div>
                <div class="gb-step-card"><h3><?php echo esc_html($s3_title); ?></h3><p><?php echo esc_html($s3_desc); ?></p></div>
            </div>
        </div>
    </section>

    <?php
    /*
     * Cleaner membership pricing, straight before the final call to action so
     * the price is on the page before somebody is asked to sign up.
     */
    if ( function_exists( 'gb_render_pricing_panel' ) ) {
        gb_render_pricing_panel( 'cleaner' );
    }
    ?>

    <section class="gb-cta-banner">
        <div class="gb-container">
            <h2 style="font-size:3rem; margin-bottom:20px; font-weight:800; color:white"><?php echo esc_html($cta_head); ?></h2>
            <p style="font-size:1.3rem; margin-bottom:40px; opacity:0.9;"><?php echo esc_html($cta_sub); ?></p>
            <a href="<?php echo esc_url($cta_link); ?>" class="gb-btn gb-btn-white"><?php echo esc_html($cta_btn); ?></a>
        </div>
    </section>
</div>

<?php get_footer(); ?>
