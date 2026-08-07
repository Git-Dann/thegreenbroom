<?php
/*
 * Template Name: How It Works (Pro Funnel)
 */

get_header();

// --- DATA RETRIEVAL ---
// A. Hero
$hero_title = get_post_meta(get_the_ID(), '_gb_hiw_hero_title', true) ?: 'Simple. Secure. Sustainable.';
$hero_sub   = get_post_meta(get_the_ID(), '_gb_hiw_hero_sub', true)   ?: 'See how The Green Broom makes cleaning better for everyone.';

// NEW: Trust Bar
$trust_banner = get_post_meta(get_the_ID(), '_gb_trust_banner', true);

// B. Homeowner Steps
$s1_title = get_post_meta(get_the_ID(), '_gb_hiw_s1_title', true) ?: 'Search & Filter';
$s1_desc  = get_post_meta(get_the_ID(), '_gb_hiw_s1_desc', true)  ?: 'Enter your postcode and browse profiles. Filter by price, reviews, and specific eco-services.';
$s1_img   = get_post_meta(get_the_ID(), '_gb_hiw_s1_img', true)   ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/rectangle-1000x500-1.png';

$s2_title = get_post_meta(get_the_ID(), '_gb_hiw_s2_title', true) ?: 'Book Instantly';
$s2_desc  = get_post_meta(get_the_ID(), '_gb_hiw_s2_desc', true)  ?: 'Choose a time slot that works for you. No back-and-forth texts. Our secure calendar system locks in your appointment.';
$s2_img   = get_post_meta(get_the_ID(), '_gb_hiw_s2_img', true)   ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png';

$s3_title = get_post_meta(get_the_ID(), '_gb_hiw_s3_title', true) ?: 'Secure Payment';
$s3_desc  = get_post_meta(get_the_ID(), '_gb_hiw_s3_desc', true)  ?: 'Pay securely through the platform using Stripe. Funds are held safely and only released when the job is done.';
$s3_img   = get_post_meta(get_the_ID(), '_gb_hiw_s3_img', true)   ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/rectangle-1000x500-1.png';

// C. Cleaner Steps
$cl_head     = get_post_meta(get_the_ID(), '_gb_hiw_cl_head', true)     ?: 'Earn More on Your Terms';
$cl_s1_title = get_post_meta(get_the_ID(), '_gb_hiw_cl_s1_title', true) ?: '1. Create Profile';
$cl_s1_desc  = get_post_meta(get_the_ID(), '_gb_hiw_cl_s1_desc', true)  ?: 'Showcase your experience, set your rates, and upload photos.';
$cl_s2_title = get_post_meta(get_the_ID(), '_gb_hiw_cl_s2_title', true) ?: '2. Get Verified';
$cl_s2_desc  = get_post_meta(get_the_ID(), '_gb_hiw_cl_s2_desc', true)  ?: 'Pass our quick ID check to get the Verified badge.';
$cl_s3_title = get_post_meta(get_the_ID(), '_gb_hiw_cl_s3_title', true) ?: '3. Accept Jobs';
$cl_s3_desc  = get_post_meta(get_the_ID(), '_gb_hiw_cl_s3_desc', true)  ?: 'Get booking requests and accept the ones that fit your schedule.';
$cl_s4_title = get_post_meta(get_the_ID(), '_gb_hiw_cl_s4_title', true) ?: '4. Earn More';
$cl_s4_desc  = get_post_meta(get_the_ID(), '_gb_hiw_cl_s4_desc', true)  ?: 'Earn more from eco-cleaning with loyal clients who share your values.';
$cl_btn      = get_post_meta(get_the_ID(), '_gb_hiw_cl_btn', true)      ?: 'Become a Cleaner';
$cl_link     = get_post_meta(get_the_ID(), '_gb_hiw_cl_link', true)     ?: '/register';

// D. FAQs
$faq1_q = get_post_meta(get_the_ID(), '_gb_hiw_faq1_q', true) ?: 'Are the cleaning products safe?';
$faq1_a = get_post_meta(get_the_ID(), '_gb_hiw_faq1_a', true) ?: 'Yes! All cleaners on our platform are committed to using non-toxic, eco-friendly products.';
$faq2_q = get_post_meta(get_the_ID(), '_gb_hiw_faq2_q', true) ?: 'How do I pay?';
$faq2_a = get_post_meta(get_the_ID(), '_gb_hiw_faq2_a', true) ?: 'We use a secure cashless system. You pay via credit/debit card when you book.';
$faq3_q = get_post_meta(get_the_ID(), '_gb_hiw_faq3_q', true) ?: 'Are cleaners vetted?';
$faq3_a = get_post_meta(get_the_ID(), '_gb_hiw_faq3_a', true) ?: 'Absolutely. We check ID and references for every professional to ensure they meet our high standards.';
$faq4_q = get_post_meta(get_the_ID(), '_gb_hiw_faq4_q', true) ?: 'What if I need to cancel?';
$faq4_a = get_post_meta(get_the_ID(), '_gb_hiw_faq4_a', true) ?: 'You can cancel or reschedule up to 24 hours before your booking for a full refund.';

// E. Bottom CTA (Synched with Dashboard Keys)
$cta_title = get_post_meta(get_the_ID(), '_gb_hiw_cta_title', true) ?: 'Ready to get started?';
$cta_desc  = get_post_meta(get_the_ID(), '_gb_hiw_cta_desc', true)  ?: "Join the community that's cleaning up the UK, one home at a time.";
$cta_btn   = get_post_meta(get_the_ID(), '_gb_hiw_cta_btn', true)   ?: 'Find a Cleaner';
$cta_link  = get_post_meta(get_the_ID(), '_gb_hiw_cta_link', true)  ?: '/cleaner-listings';
?>

<style>
    /* 1. RESET CONTAINER */
    #gb-hiw-page { width: 100vw; position: relative; left: 50%; right: 50%; margin-left: -50vw; margin-right: -50vw; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; overflow-x: hidden; color: #333; }
    .gb-container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
    .gb-text-center { text-align: center; }
    .gb-section { padding: 80px 0; }
    .gb-bg-light { background-color: #f9f9f9; }
    .gb-bg-green { background-color: #e8f5e9; }
    .gb-heading-md { font-size: 2rem; font-weight: 700; margin-bottom: 15px; color: #333; }
    .gb-text-lead { font-size: 1.25rem; color: #555; line-height: 1.6; max-width: 700px; margin: 0 auto 40px; }

    /* HERO */
    .gb-hiw-hero { background: #1b5e20; color: white; padding: 100px 20px; text-align: center; }
    .gb-hiw-hero h1 { font-size: 3rem; font-weight: 800; color: white !important; margin-bottom: 20px; }
    .gb-hiw-hero p { font-size: 1.3rem; color: rgba(255,255,255,0.9); margin-bottom: 0; }

    /* NEW TRUST BAR STYLE */
    .gb-trust-bar { background: #e8f5e9; padding: 20px 0; text-align: center; border-bottom: 1px solid #c8e6c9; }
    .gb-trust-text { font-weight: 600; color: #2e7d32; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

    /* STEPS */
    .gb-toggle-header { text-align: center; margin-bottom: 60px; }
    .gb-pill-badge { background: #e8f5e9; color: #2e7d32; padding: 8px 16px; border-radius: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem; }
    .gb-step-row { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; margin-bottom: 80px; }
    .gb-step-row.reversed { direction: rtl; } 
    .gb-step-row.reversed .gb-step-text { direction: ltr; }
    .gb-step-img { width: 100%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .gb-step-num { font-size: 4rem; font-weight: 800; color: #e8f5e9; line-height: 1; margin-bottom: -20px; display: block; position: relative; z-index: -1; }
    .gb-step-title { font-size: 1.8rem; font-weight: 700; color: #1b5e20; margin-bottom: 15px; }
    .gb-step-desc { font-size: 1.1rem; color: #555; line-height: 1.6; }
    .gb-cleaner-steps-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:20px; }

    /* FAQ */
    .gb-faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px; text-align: left; }
    .gb-faq-item { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .gb-faq-q { font-weight: 700; color: #1b5e20; margin-bottom: 10px; display: block; }
    .gb-faq-a { color: #666; font-size: 0.95rem; line-height: 1.5; }

    /* UPDATED FLOATING CTA STYLE */
    .gb-cta-banner { background: transparent; padding: 80px 20px; }
    .gb-cta-banner .gb-container { background: #1b5e20; color: white; text-align: center; padding: 80px 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(27, 94, 32, 0.2); }
    .gb-btn { padding: 15px 35px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; transition: transform 0.2s; }
    .gb-btn-white { background: white; color: #1b5e20 !important; }
    .gb-btn-white:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .gb-btn-primary { background: #2e7d32; color: white !important; }

    @media (max-width: 768px) {
        .gb-step-row, .gb-faq-grid, .gb-cleaner-steps-grid { grid-template-columns: 1fr; gap: 40px; }
        .gb-step-row.reversed { direction: ltr; }
        .gb-hiw-hero h1 { font-size: 2.2rem; }
    }
</style>

<div id="gb-hiw-page">
    <section class="gb-hiw-hero">
        <div class="gb-container">
            <h1><?php echo esc_html($hero_title); ?></h1>
            <p><?php echo esc_html($hero_sub); ?></p>
        </div>
    </section>

    <?php if($trust_banner): ?>
    <div class="gb-trust-bar"><span class="gb-trust-text"><?php echo esc_html($trust_banner); ?></span></div>
    <?php endif; ?>

    <section class="gb-section">
        <div class="gb-container">
            <div class="gb-toggle-header"><span class="gb-pill-badge">For Homeowners</span><h2 class="gb-heading-md" style="margin-top:15px;">Your Path to a Spotless Home</h2></div>
            <div class="gb-step-row">
                <div><span class="gb-step-num">01</span><h3 class="gb-step-title"><?php echo esc_html($s1_title); ?></h3><p class="gb-step-desc"><?php echo esc_html($s1_desc); ?></p></div>
                <img src="<?php echo esc_url($s1_img); ?>" class="gb-step-img" alt="Step 1">
            </div>
            <div class="gb-step-row reversed">
                <div class="gb-step-text"><span class="gb-step-num">02</span><h3 class="gb-step-title"><?php echo esc_html($s2_title); ?></h3><p class="gb-step-desc"><?php echo esc_html($s2_desc); ?></p></div>
                <img src="<?php echo esc_url($s2_img); ?>" class="gb-step-img" alt="Step 2">
            </div>
            <div class="gb-step-row">
                <div><span class="gb-step-num">03</span><h3 class="gb-step-title"><?php echo esc_html($s3_title); ?></h3><p class="gb-step-desc"><?php echo esc_html($s3_desc); ?></p></div>
                <img src="<?php echo esc_url($s3_img); ?>" class="gb-step-img" alt="Step 3">
            </div>
        </div>
    </section>

    <section class="gb-section gb-bg-green">
        <div class="gb-container gb-text-center">
            <span class="gb-pill-badge" style="background:white;">For Cleaners</span>
            <h2 class="gb-heading-md" style="margin-top:15px; margin-bottom:40px;"><?php echo esc_html($cl_head); ?></h2>
            <div class="gb-cleaner-steps-grid">
                <div class="gb-bg-light" style="padding:30px; border-radius:12px;">
                    <span style="font-size:2rem;">📝</span><h4 style="margin:10px 0; font-weight:700;"><?php echo esc_html($cl_s1_title); ?></h4><p style="font-size:0.95rem; color:#666;"><?php echo esc_html($cl_s1_desc); ?></p>
                </div>
                <div class="gb-bg-light" style="padding:30px; border-radius:12px;">
                    <span style="font-size:2rem;">✅</span><h4 style="margin:10px 0; font-weight:700;"><?php echo esc_html($cl_s2_title); ?></h4><p style="font-size:0.95rem; color:#666;"><?php echo esc_html($cl_s2_desc); ?></p>
                </div>
                <div class="gb-bg-light" style="padding:30px; border-radius:12px;">
                    <span style="font-size:2rem;">📅</span><h4 style="margin:10px 0; font-weight:700;"><?php echo esc_html($cl_s3_title); ?></h4><p style="font-size:0.95rem; color:#666;"><?php echo esc_html($cl_s3_desc); ?></p>
                </div>
                <div class="gb-bg-light" style="padding:30px; border-radius:12px;">
                    <span style="font-size:2rem;">💷</span><h4 style="margin:10px 0; font-weight:700;"><?php echo esc_html($cl_s4_title); ?></h4><p style="font-size:0.95rem; color:#666;"><?php echo esc_html($cl_s4_desc); ?></p>
                </div>
            </div>
            <a href="<?php echo esc_url($cl_link); ?>" class="gb-btn gb-btn-primary" style="margin-top:30px;"><?php echo esc_html($cl_btn); ?></a>
        </div>
    </section>

    <section class="gb-section">
        <div class="gb-container gb-text-center">
            <h2 class="gb-heading-md">Frequently Asked Questions</h2>
            <div class="gb-faq-grid">
                <div class="gb-faq-item"><span class="gb-faq-q"><?php echo esc_html($faq1_q); ?></span><p class="gb-faq-a"><?php echo esc_html($faq1_a); ?></p></div>
                <div class="gb-faq-item"><span class="gb-faq-q"><?php echo esc_html($faq2_q); ?></span><p class="gb-faq-a"><?php echo esc_html($faq2_a); ?></p></div>
                <div class="gb-faq-item"><span class="gb-faq-q"><?php echo esc_html($faq3_q); ?></span><p class="gb-faq-a"><?php echo esc_html($faq3_a); ?></p></div>
                <div class="gb-faq-item"><span class="gb-faq-q"><?php echo esc_html($faq4_q); ?></span><p class="gb-faq-a"><?php echo esc_html($faq4_a); ?></p></div>
            </div>
        </div>
    </section>

    <section class="gb-cta-banner">
        <div class="gb-container">
            <h2 style="font-size:3rem; margin-bottom:20px; font-weight:800; color:white"><?php echo esc_html($cta_title); ?></h2>
            <p style="font-size:1.3rem; margin-bottom:40px; opacity:0.9;"><?php echo esc_html($cta_desc); ?></p>
            <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">
                <a href="<?php echo esc_url($cta_link); ?>" class="gb-btn gb-btn-white"><?php echo esc_html($cta_btn); ?></a>
            </div>
        </div>
    </section>
</div>

<?php get_footer(); ?>
