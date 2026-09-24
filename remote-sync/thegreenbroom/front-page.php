<?php
/*
 * Template Name: Front Page (Sales Funnel Pro)
 */

get_header();

// --- 1. DATA RETRIEVAL (Fully Dynamic with Fallbacks) ---

// A. Hero Section
$hero_headline = get_post_meta(get_the_ID(), '_gb_hero_headline', true) ?: 'Spotless Homes, Cleaner Planet.';
$hero_sub      = get_post_meta(get_the_ID(), '_gb_hero_sub', true)      ?: "The UK's trusted platform for finding eco-friendly cleaners.";
$hero_bg       = get_post_meta(get_the_ID(), '_gb_hero_bg', true)       ?: 'https://images.unsplash.com/photo-1581578731117-10d52143b1e8?auto=format&fit=crop&w=1920&q=80';
$hero_btn_1    = get_post_meta(get_the_ID(), '_gb_hero_btn_1', true)    ?: 'Find a Cleaner';
$hero_link_1   = get_post_meta(get_the_ID(), '_gb_hero_link_1', true)   ?: '/cleaner-listings';
$hero_btn_2    = get_post_meta(get_the_ID(), '_gb_hero_btn_2', true)    ?: 'Become a Cleaner';
$hero_link_2   = get_post_meta(get_the_ID(), '_gb_hero_link_2', true)   ?: '/join';

// B. Trust Bar (UPDATED to match the new Dashboard Field)
$trust_text    = get_post_meta(get_the_ID(), '_gb_trust_banner', true)  ?: 'Trusted by 500+ Homes Across the UK • 100% Vetted Cleaners';

// C. Split Section (Value Prop)
$split_head    = get_post_meta(get_the_ID(), '_gb_split_head', true)    ?: "Clean homes shouldn't cost the Earth.";
$split_body    = get_post_meta(get_the_ID(), '_gb_split_body', true)    ?: "Traditional cleaning products are full of harsh chemicals that can harm your family and pets. Plus, finding a reliable cleaner is often a headache of texts and cash payments.";
$split_img     = get_post_meta(get_the_ID(), '_gb_split_img', true)     ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png';
// Checklist
$split_list_1  = get_post_meta(get_the_ID(), '_gb_split_list_1', true)  ?: '100% Eco-Friendly Products Only';
$split_list_2  = get_post_meta(get_the_ID(), '_gb_split_list_2', true)  ?: 'Vetted & Verified Professionals';
$split_list_3  = get_post_meta(get_the_ID(), '_gb_split_list_3', true)  ?: 'Secure Cashless Payments';
$split_list_4  = get_post_meta(get_the_ID(), '_gb_split_list_4', true)  ?: 'Satisfaction Guaranteed';

// D. How It Works
$hiw_head      = get_post_meta(get_the_ID(), '_gb_hiw_head', true)      ?: 'How It Works';
$hiw_sub       = get_post_meta(get_the_ID(), '_gb_hiw_sub', true)       ?: 'Booking a cleaner has never been this simple or sustainable.';

$step1_title   = get_post_meta(get_the_ID(), '_gb_step1_title', true)   ?: 'Search & Filter';
$step1_text    = get_post_meta(get_the_ID(), '_gb_step1_text', true)    ?: 'Enter your postcode and browse profiles of vetted eco-cleaners in your area.';

$step2_title   = get_post_meta(get_the_ID(), '_gb_step2_title', true)   ?: 'Book Securely';
$step2_text    = get_post_meta(get_the_ID(), '_gb_step2_text', true)    ?: 'Select a time that works for you and pay securely through the platform.';

$step3_title   = get_post_meta(get_the_ID(), '_gb_step3_title', true)   ?: 'Relax & Enjoy';
$step3_text    = get_post_meta(get_the_ID(), '_gb_step3_text', true)    ?: "Come home to a spotless house knowing you've made a green choice.";

// E. Features Grid
$feat_head     = get_post_meta(get_the_ID(), '_gb_feat_head', true)     ?: 'Why The Green Broom?';

$feat1_title   = get_post_meta(get_the_ID(), '_gb_feat1_title', true)   ?: 'Eco-Friendly';
$feat1_text    = get_post_meta(get_the_ID(), '_gb_feat1_text', true)    ?: 'Every cleaner uses non-toxic, sustainable products safe for pets and kids.';

$feat2_title   = get_post_meta(get_the_ID(), '_gb_feat2_title', true)   ?: 'Fully Vetted';
$feat2_text    = get_post_meta(get_the_ID(), '_gb_feat2_text', true)    ?: 'We check IDs and references personally. Safety is our #1 priority.';

$feat3_title   = get_post_meta(get_the_ID(), '_gb_feat3_title', true)   ?: 'Direct Chat';
$feat3_text    = get_post_meta(get_the_ID(), '_gb_feat3_text', true)    ?: 'Message your cleaner directly to discuss specific needs or access details.';

// F. Middle Image
$mid_img       = get_post_meta(get_the_ID(), '_gb_mid_img', true)       ?: 'https://thegreenbroom.uk/wp-content/uploads/2025/12/rectangle-1000x500-1.png';

// G. Testimonials
$testi_head    = get_post_meta(get_the_ID(), '_gb_testi_head', true)    ?: 'Meet some of our cleaners';
$testi_sub     = get_post_meta(get_the_ID(), '_gb_testi_sub', true)     ?: 'Every one of them ID-checked, DBS-checked and reference-checked before they appear here.';
$testi_btn     = get_post_meta(get_the_ID(), '_gb_testi_btn', true)     ?: 'Browse all cleaners';
$testi_link    = get_post_meta(get_the_ID(), '_gb_testi_link', true)    ?: '/cleaner-listings';

// H. Bottom CTA
$cta_heading   = get_post_meta(get_the_ID(), '_gb_cta_heading', true)   ?: 'Ready to make money cleaning?';
$cta_sub       = get_post_meta(get_the_ID(), '_gb_cta_sub', true)       ?: 'Join The Green Broom today.';
$cta_btn_1     = get_post_meta(get_the_ID(), '_gb_cta_btn_1', true)     ?: 'Get Started Now';
$cta_link_1    = get_post_meta(get_the_ID(), '_gb_cta_link_1', true)    ?: '/register';

// Prefer WordPress-generated image sizes so a phone never downloads an original.
$gb_image_source = static function( $url, $size, $fallback_width, $fallback_height ) {
    $attachment_id = attachment_url_to_postid( $url );
    $image = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;

    if ( $image ) {
        return array( 'url' => $image[0], 'width' => (int) $image[1], 'height' => (int) $image[2] );
    }

    return array( 'url' => $url, 'width' => $fallback_width, 'height' => $fallback_height );
};
$hero_image_desktop = $gb_image_source( $hero_bg, 'large', 1920, 1080 );
$hero_image_mobile  = $gb_image_source( $hero_bg, 'medium_large', 768, 432 );
$split_image = $gb_image_source( $split_img, 'large', 600, 900 );
$mid_image = $gb_image_source( $mid_img, 'large', 1200, 675 );
?>

<style>
    /* RESET & LAYOUT */
    #gb-front-page { width: 100vw; position: relative; left: 50%; right: 50%; margin-left: -50vw; margin-right: -50vw; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; overflow-x: hidden; color: #333; }
    .gb-container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
    .gb-text-center { text-align: center; }
    .gb-section { padding: 80px 0; }
    .gb-bg-light { background-color: #f9f9f9; }
    .gb-bg-white { background-color: white; }
    
    /* TYPOGRAPHY */
    .gb-heading-lg { font-size: 2.5rem; font-weight: 800; margin-bottom: 20px; color: #1b5e20; line-height: 1.1; }
    .gb-heading-md { font-size: 2rem; font-weight: 700; margin-bottom: 15px; color: #333; }
    .gb-text-lg { font-size: 1.15rem; color: #555; line-height: 1.6; margin-bottom: 30px; }

    /* COMPONENTS */
    .gb-btn { padding: 15px 35px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; transition: transform 0.2s, box-shadow 0.2s; }
    .gb-btn-primary { background-color: #2e7d32; color: white !important; border: 2px solid #2e7d32; }
    .gb-btn-primary:hover { background-color: #1b5e20; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
    .gb-btn-outline { background: transparent; color: #1b5e20 !important; border: 2px solid #1b5e20; }
    .gb-btn-outline:hover { background: #1b5e20; color: white !important; }

    /* HERO */
    .gb-hero { position:relative; isolation:isolate; overflow:hidden; background:#1b5e20; color:white; padding:160px 20px; text-align:center; }
    .gb-hero-media, .gb-hero-overlay { position:absolute; inset:0; }
    .gb-hero-media img { width:100%; height:100%; object-fit:cover; }
    .gb-hero-media { z-index:0; }
    .gb-hero-overlay { z-index:1; background:rgba(0, 0, 0, 0.58); }
    .gb-hero-content { position:relative; z-index:2; }
    .gb-hero h1 { font-size: 3.5rem; font-weight: 800; color: white !important; margin-bottom: 20px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
    .gb-hero p { font-size: 1.4rem; color: rgba(255,255,255,0.95); margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto; }

    /* TRUST BAR */
    .gb-trust-bar { background: #e8f5e9; padding: 20px 0; text-align: center; border-bottom: 1px solid #c8e6c9; }
    .gb-trust-text { font-weight:700; color:#1b5e20; font-size:0.9rem; text-transform:uppercase; letter-spacing:1px; }

    /* SPLIT SECTION */
    .gb-split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
    .gb-split-img { width: 100%; height: auto; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .gb-check-list { list-style: none; padding: 0; margin: 0; }
    .gb-check-list li { margin-bottom: 15px; padding-left: 30px; position: relative; font-size: 1.1rem; color: #444; }
    .gb-check-list li:before { content: '✓'; position: absolute; left: 0; color: #2e7d32; font-weight: 800; }

    /* GRIDS */
    .gb-steps-grid, .gb-feat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; text-align: center; margin-top: 50px; }
    .gb-step-num { background: #e8f5e9; color: #2e7d32; width: 50px; height: 50px; border-radius: 50%; display: inline-flex; justify-content: center; align-items: center; font-weight: 800; font-size: 1.2rem; margin-bottom: 20px; }
    .gb-feat-card { background: white; padding: 40px 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid #eee; transition: transform 0.2s; }
    .gb-feat-card:hover { transform: translateY(-5px); border-color: #2e7d32; }
    .gb-feat-icon { font-size: 3rem; margin-bottom: 20px; display: block; }

    /* TESTIMONIALS & CTA */
    .gb-home-cleaner-sub { font-size: 1.15rem; color: #555; line-height: 1.6; text-align: center; max-width: 640px; margin: 0 auto; }
    .gb-home-cleaner-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 26px; margin-top: 40px; }
    .gb-home-cleaner { display: flex; flex-direction: column; background: #fff; border: 1px solid #eee; border-radius: 14px; overflow: hidden; text-decoration: none; color: inherit; transition: transform .2s, box-shadow .2s; }
    .gb-home-cleaner:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(0,0,0,.09); border-color: #2e7d32; }
    .gb-home-cleaner-photo { height: 190px; background: #f0fdf4; overflow: hidden; }
    .gb-home-cleaner-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gb-home-cleaner-body { padding: 18px 20px 22px; display: flex; flex-direction: column; gap: 5px; }
    .gb-home-cleaner-name { font-weight: 800; color: #0f172a; font-size: 1.05rem; }
    .gb-home-cleaner-loc { color: #64748b; font-size: .88rem; }
    .gb-home-cleaner-badge { align-self: flex-start; margin-top: 4px; background: #ecfdf3; color: #166534; border-radius: 20px; padding: 4px 11px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
    .gb-home-cleaner-rate { margin-top: 6px; color: #2e7d32; font-weight: 700; font-size: .92rem; }
    .gb-testi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; }
    .gb-testi-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #eee; }
    .gb-stars { color: #f57f17; margin-bottom: 10px; display: block; }
    .gb-testi-text { font-style: italic; color: #555; font-size: 1.1rem; line-height: 1.6; }
    .gb-testi-author { margin-top: 15px; font-weight: 700; color: #333; display: block; }

    /* UPDATED CTA BANNER STYLES */
    .gb-cta-banner { 
        background: transparent; /* Section is transparent */
        padding: 80px 20px;      /* Spacing around the card */
    }
    .gb-cta-banner .gb-container {
        background: #1b5e20;     /* The rounded green rectangle */
        color: white;            /* All text inside is white */
        text-align: center;
        padding: 80px 40px;      /* Good spacing inside */
        border-radius: 24px;     /* Rounded corners */
        box-shadow: 0 20px 40px -10px rgba(27, 94, 32, 0.2); /* Nice shadow */
    }
    .gb-btn-white { background: white; color: #1b5e20 !important; border: 2px solid white; }
    .gb-btn-white:hover { background: #f1f1f1; transform: translateY(-2px); }

    @media (max-width: 900px) {
        .gb-hero h1 { font-size: 2.5rem; }
        .gb-split-grid, .gb-steps-grid, .gb-feat-grid, .gb-testi-grid { grid-template-columns: 1fr; gap: 40px; }
        .gb-hero { padding: 100px 20px; }
        .gb-split-img { order: -1; }
        .gb-cta-banner .gb-container { padding: 60px 30px; } /* Adjust padding on mobile */
    }
</style>

<main id="primary" class="site-main">
<div id="gb-front-page">
    <section class="gb-hero">
        <picture class="gb-hero-media" aria-hidden="true">
            <?php gb_picture_sources( $hero_image_mobile['url'], '(max-width: 700px)' ); ?>
            <source media="(max-width: 700px)" srcset="<?php echo esc_url( $hero_image_mobile['url'] ); ?>">
            <?php gb_picture_sources( $hero_image_desktop['url'] ); ?>
            <img src="<?php echo esc_url( $hero_image_desktop['url'] ); ?>" width="<?php echo esc_attr( $hero_image_desktop['width'] ); ?>" height="<?php echo esc_attr( $hero_image_desktop['height'] ); ?>" alt="" fetchpriority="high" decoding="async">
        </picture>
        <div class="gb-hero-overlay" aria-hidden="true"></div>
        <div class="gb-container gb-hero-content">
            <h1><?php echo esc_html($hero_headline); ?></h1>
            <p><?php echo esc_html($hero_sub); ?></p>
            <div style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap;">
                <a href="<?php echo esc_url($hero_link_1); ?>" class="gb-btn gb-btn-primary"><?php echo esc_html($hero_btn_1); ?></a>
                <a href="<?php echo esc_url($hero_link_2); ?>" class="gb-btn" style="background:rgba(15,23,42,0.84); color:white; border:2px solid white;"><?php echo esc_html($hero_btn_2); ?></a>
            </div>
        </div>
    </section>

    <div class="gb-trust-bar"><span class="gb-trust-text"><?php echo esc_html($trust_text); ?></span></div>

    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <div class="gb-split-grid">
                <div>
                    <h2 class="gb-heading-lg"><?php echo esc_html($split_head); ?></h2>
                    <p class="gb-text-lg"><?php echo esc_html($split_body); ?></p>
                    <ul class="gb-check-list">
                        <li><strong><?php echo esc_html($split_list_1); ?></strong></li>
                        <li><strong><?php echo esc_html($split_list_2); ?></strong></li>
                        <li><strong><?php echo esc_html($split_list_3); ?></strong></li>
                        <li><strong><?php echo esc_html($split_list_4); ?></strong></li>
                    </ul>
                    <a href="/cleaner-listings" class="gb-btn gb-btn-outline" style="margin-top:30px;">Browse Cleaners &rarr;</a>
                </div>
                <picture>
                    <?php gb_picture_sources( $split_image['url'] ); ?>
                <img src="<?php echo esc_url( $split_image['url'] ); ?>" width="<?php echo esc_attr( $split_image['width'] ); ?>" height="<?php echo esc_attr( $split_image['height'] ); ?>" alt="Eco Cleaning" class="gb-split-img" loading="lazy" decoding="async">
                </picture>
            </div>
        </div>
    </section>

    <?php
    /*
     * Membership pricing, straight after the problem the split section sets up.
     * Wording, prices and the button all come from Settings, and the same panel
     * appears on Find a Cleaner, so the figures are stated once.
     */
    if ( function_exists( 'gb_render_pricing_panel' ) ) {
        gb_render_pricing_panel( 'customer' );
    }
    ?>

    <section class="gb-section gb-bg-light">
        <div class="gb-container">
            <div class="gb-text-center">
                <h2 class="gb-heading-md"><?php echo esc_html($hiw_head); ?></h2>
                <p class="gb-text-lg" style="max-width:600px; margin:0 auto;"><?php echo esc_html($hiw_sub); ?></p>
            </div>
            <div class="gb-steps-grid">
                <div class="gb-step-card"><span class="gb-step-num">1</span><h3><?php echo esc_html($step1_title); ?></h3><p><?php echo esc_html($step1_text); ?></p></div>
                <div class="gb-step-card"><span class="gb-step-num">2</span><h3><?php echo esc_html($step2_title); ?></h3><p><?php echo esc_html($step2_text); ?></p></div>
                <div class="gb-step-card"><span class="gb-step-num">3</span><h3><?php echo esc_html($step3_title); ?></h3><p><?php echo esc_html($step3_text); ?></p></div>
            </div>
        </div>
    </section>

    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <div class="gb-text-center"><h2 class="gb-heading-md"><?php echo esc_html($feat_head); ?></h2></div>
            <div class="gb-feat-grid">
                <div class="gb-feat-card"><span class="gb-feat-icon">🌱</span><h3><?php echo esc_html($feat1_title); ?></h3><p><?php echo esc_html($feat1_text); ?></p></div>
                <div class="gb-feat-card"><span class="gb-feat-icon">🛡️</span><h3><?php echo esc_html($feat2_title); ?></h3><p><?php echo esc_html($feat2_text); ?></p></div>
                <div class="gb-feat-card"><span class="gb-feat-icon">💬</span><h3><?php echo esc_html($feat3_title); ?></h3><p><?php echo esc_html($feat3_text); ?></p></div>
            </div>
        </div>
    </section>

    <section class="gb-section" style="padding:0;">
        <div class="gb-container" style="max-width:1200px; padding:0;">
             <picture>
                 <?php gb_picture_sources( $mid_image['url'] ); ?>
             <img src="<?php echo esc_url( $mid_image['url'] ); ?>" width="<?php echo esc_attr( $mid_image['width'] ); ?>" height="<?php echo esc_attr( $mid_image['height'] ); ?>" alt="Cleaner" style="width:100%; height:auto; display:block;" loading="lazy" decoding="async">
             </picture>
        </div>
    </section>

    <?php
    /*
     * Real cleaners, not invented reviews.
     *
     * This section previously held two made-up testimonials from customers who
     * do not exist. It now shows up to four published cleaners, newest first,
     * and disappears entirely when there are none - so it fills out on its own
     * as cleaners join, and never shows a placeholder.
     */
    $gb_home_cleaners = get_posts([
        'post_type'      => 'gb_cleaner',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    ?>
    <?php if ($gb_home_cleaners): ?>
    <section class="gb-section gb-bg-light">
        <div class="gb-container">
            <h2 class="gb-heading-md gb-text-center"><?php echo esc_html($testi_head); ?></h2>
            <?php if ($testi_sub !== ''): ?>
                <p class="gb-home-cleaner-sub"><?php echo esc_html($testi_sub); ?></p>
            <?php endif; ?>
            <div class="gb-home-cleaner-grid">
                <?php foreach ($gb_home_cleaners as $gb_c):
                    $gb_loc  = function_exists('gb_get_cleaner_location') ? gb_get_cleaner_location($gb_c->ID) : '';
                    $gb_rate = function_exists('gb_get_cleaner_rate') ? gb_get_cleaner_rate($gb_c->ID) : '';
                    $gb_ver  = function_exists('gb_cleaner_is_fully_verified') && gb_cleaner_is_fully_verified($gb_c->ID);
                ?>
                    <a class="gb-home-cleaner" href="<?php echo esc_url(get_permalink($gb_c->ID)); ?>">
                        <div class="gb-home-cleaner-photo">
                            <?php if (has_post_thumbnail($gb_c->ID)): ?>
                                <?php echo get_the_post_thumbnail($gb_c->ID, 'medium', ['alt' => esc_attr(get_the_title($gb_c->ID)) . ' profile photo', 'loading' => 'lazy', 'decoding' => 'async']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="gb-home-cleaner-body">
                            <span class="gb-home-cleaner-name"><?php echo esc_html(get_the_title($gb_c->ID)); ?></span>
                            <?php if ($gb_loc): ?><span class="gb-home-cleaner-loc"><?php echo esc_html($gb_loc); ?></span><?php endif; ?>
                            <?php if ($gb_ver): ?><span class="gb-home-cleaner-badge">Verified</span><?php endif; ?>
                            <?php if ($gb_rate): ?><span class="gb-home-cleaner-rate">&pound;<?php echo esc_html($gb_rate); ?> per hour</span><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="gb-text-center" style="margin-top:34px;">
                <a href="<?php echo esc_url($testi_link); ?>" class="gb-btn gb-btn-primary"><?php echo esc_html($testi_btn); ?></a>
            </p>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $gb_reviews_embed = get_post_meta((int) get_option('page_on_front'), '_gb_reviews_embed', true);
    if (trim((string) $gb_reviews_embed) !== ''):
    ?>
    <section class="gb-section gb-bg-white">
        <div class="gb-container">
            <h2 class="gb-heading-md gb-text-center">Our Google Reviews</h2>
            <div style="margin-top:30px;"><?php echo do_shortcode($gb_reviews_embed); ?></div>
        </div>
    </section>
    <?php endif; ?>

    <section class="gb-cta-banner">
        <div class="gb-container">
            <h2 style="font-size:3rem; margin-bottom:20px; font-weight:800; color:white"><?php echo esc_html($cta_heading); ?></h2>
            <p style="font-size:1.3rem; margin-bottom:40px; opacity:0.9;"><?php echo esc_html($cta_sub); ?></p>
            <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">
                <a href="<?php echo esc_url($cta_link_1); ?>" class="gb-btn gb-btn-white"><?php echo esc_html($cta_btn_1); ?></a>
            </div>
        </div>
    </section>
</div>
</main>

<?php get_footer(); ?>
