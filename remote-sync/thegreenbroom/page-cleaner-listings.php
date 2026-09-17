<?php
/* Template Name: Cleaner Listings (Public Search) */
get_header(); 

// --- 1. SETUP & PAGINATION ---
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

// --- 2. QUERY ARGUMENTS ---
$args = array(
    'post_type'      => 'gb_cleaner',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
);

// Search by postcode metadata, not by cleaner name.
$search_term = isset($_GET['k']) ? strtoupper(trim(preg_replace('/\s+/', ' ', sanitize_text_field(wp_unslash($_GET['k']))))) : '';
$search_error = '';
if ( $search_term !== '' && ! preg_match('/^[A-Z0-9 ]{2,10}$/', $search_term) ) {
    $search_error = 'Please enter a valid postcode using letters and numbers.';
    $search_term = '';
}

$meta_filters = [];
if ( $search_term !== '' ) {
    $meta_filters[] = [
        'relation' => 'OR',
        ['key' => 'gb_postcode', 'value' => $search_term, 'compare' => 'LIKE'],
        ['key' => '_gb_location', 'value' => $search_term, 'compare' => 'LIKE'],
        ['key' => 'location', 'value' => $search_term, 'compare' => 'LIKE'],
        ['key' => 'cleaner_location', 'value' => $search_term, 'compare' => 'LIKE'],
    ];
}

$service_filter = isset($_GET['service']) ? sanitize_key(wp_unslash($_GET['service'])) : '';
$service_filters = [
    'standard' => 'Standard Clean',
    'deep'     => 'Deep Clean',
    'moving'   => 'End of Tenancy',
];
if ( isset($service_filters[$service_filter]) ) {
    $meta_filters[] = [
        'key'     => 'gb_services',
        'value'   => $service_filters[$service_filter],
        'compare' => 'LIKE',
    ];
}
if ( count($meta_filters) === 1 ) {
    $args['meta_query'] = $meta_filters[0];
} elseif ( count($meta_filters) > 1 ) {
    $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_filters);
}

$cleaners = new WP_Query($args);

$listings_title = get_post_meta(get_the_ID(), '_gb_listings_title', true) ?: 'Find Your Professional';
$listings_sub = get_post_meta(get_the_ID(), '_gb_listings_sub', true) ?: 'Find trusted local cleaners by entering your postcode.';
$listings_none = get_post_meta(get_the_ID(), '_gb_listings_none', true) ?: 'No professionals found. Try adjusting your search criteria.';

/*
 * Paid-social traffic lands here, so the page opens with a proper hero lockup
 * like the home page rather than dropping straight into a search box. Every
 * string below is editable from Content > Cleaner Listings; the defaults only
 * apply until a manager sets their own.
 */
$listings_hero_bg = trim((string) get_post_meta(get_the_ID(), '_gb_listings_hero_bg', true));
$listings_trust   = trim((string) get_post_meta(get_the_ID(), '_gb_trust_banner', true));
$listings_eyebrow = trim((string) get_post_meta(get_the_ID(), '_gb_listings_eyebrow', true));
$listings_search_head = get_post_meta(get_the_ID(), '_gb_listings_search_head', true) ?: 'Search cleaners near you';

$verified_link  = get_post_meta(get_the_ID(), '_gb_listings_verified_link', true) ?: 'What does Verified mean?';
$verified_title = get_post_meta(get_the_ID(), '_gb_listings_verified_title', true) ?: 'What does Verified mean?';
$verified_body  = trim((string) get_post_meta(get_the_ID(), '_gb_listings_verified_body', true));
if ($verified_body === '') {
    // Defaults describe the checks gb_cleaner_verification_missing() actually
    // enforces before a cleaner can go live, so the promise matches the code.
    $verified_body = "Every cleaner showing the Verified badge has passed the same checks before we let them take a booking:
"
        . "Government-issued photo ID confirmed
"
        . "DBS certificate supplied and checked
"
        . "Proof of address confirmed
"
        . "Two references supplied and contacted
"
        . "Contact number confirmed
"
        . "Signed our Terms of Association
"
        . "
"
        . "A cleaner cannot be listed until all of the above are complete. Once they are approved we permanently delete the ID, address and DBS documents from our systems — we keep the result of the check, not your cleaner's paperwork.";
}
?>

<style>
    /* PAGE LAYOUT & HERO */
    /* Landing-page lockup: matches the home page hero so paid-social traffic
       gets a proper header rather than an immediate search box. */
    .gb-dir-hero {
        background: #2e7d32; /* Brand Green, and the fallback when no image is set */
        padding: 120px 20px;
        text-align: center;
        color: white;
        position: relative;
        isolation: isolate;
        overflow: hidden;
    }
    .gb-dir-hero-media, .gb-dir-hero-overlay { position: absolute; inset: 0; }
    .gb-dir-hero-media { z-index: 0; }
    .gb-dir-hero-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gb-dir-hero-overlay { z-index: 1; background: rgba(0,0,0,0.58); }
    .gb-dir-hero-content { position: relative; z-index: 2; max-width: 820px; margin: 0 auto; }
    .gb-dir-eyebrow { display:inline-block; margin-bottom:18px; padding:7px 15px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.3); color:#fff; font-size:12px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }

    .gb-dir-trust { background:#e8f5e9; padding:18px 20px; text-align:center; border-bottom:1px solid #c8e6c9; }
    .gb-dir-trust span { font-weight:700; color:#1b5e20; font-size:.9rem; text-transform:uppercase; letter-spacing:1px; }

    .gb-dir-search { max-width:1000px; margin:0 auto; padding:54px 20px 10px; text-align:center; }
    .gb-dir-search-head { margin:0 0 22px; color:#1b5e20; font-size:1.6rem; font-weight:800; }

    .gb-verified-cue { margin:18px 0 0; }
    .gb-verified-trigger { background:none; border:0; padding:0; color:#2e7d32; font-size:14px; font-weight:700; text-decoration:underline; text-underline-offset:3px; cursor:pointer; font-family:inherit; }
    .gb-verified-trigger:hover { color:#1b5e20; }
    .gb-verified-trigger:focus-visible { outline:2px solid #2e7d32; outline-offset:3px; border-radius:3px; }

    .gb-verified-modal[hidden] { display:none; }
    .gb-verified-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:20px; }
    .gb-verified-backdrop { position:absolute; inset:0; background:rgba(15,23,42,.6); }
    .gb-verified-panel { position:relative; z-index:1; width:100%; max-width:540px; max-height:85vh; overflow-y:auto; background:#fff; border-radius:14px; padding:32px; box-shadow:0 24px 60px rgba(15,23,42,.3); text-align:left; }
    .gb-verified-panel h2 { margin:0 0 16px; color:#1b5e20; font-size:1.4rem; padding-right:30px; }
    .gb-verified-panel p { margin:0 0 14px; color:#475569; line-height:1.65; font-size:14.5px; }
    .gb-verified-list { list-style:none; margin:0 0 16px; padding:0; }
    .gb-verified-list li { position:relative; padding:7px 0 7px 30px; color:#334155; font-size:14.5px; line-height:1.5; }
    .gb-verified-list li::before { content:""; position:absolute; left:4px; top:12px; width:6px; height:11px; border:solid #2e7d32; border-width:0 2.5px 2.5px 0; transform:rotate(45deg); }
    .gb-verified-close { position:absolute; top:12px; right:14px; width:34px; height:34px; border:0; border-radius:8px; background:#f1f5f9; color:#475569; font-size:22px; line-height:1; cursor:pointer; }
    .gb-verified-close:hover { background:#e2e8f0; color:#0f172a; }
    @media (max-width:600px) { .gb-verified-panel { padding:26px 20px; } }
    
    /* FIX: Force title to be white to override global h1 styles */
    .gb-dir-title { 
        font-size: 42px; 
        font-weight: 800; 
        margin: 0; 
        letter-spacing: -1px; 
        color: #ffffff !important; 
    }
    
    .gb-dir-sub { color: #ffffff; margin-top: 15px; font-size: 18px; margin-bottom: 40px; }
    .gb-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    
    /* SEARCH BAR */
    .gb-search-wrapper { max-width: 800px; margin: 0 auto; background: white; padding: 10px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); display: flex; gap: 10px; flex-wrap: wrap; }
    .gb-search-input { flex: 1; padding: 15px 20px; border: none; outline: none; font-size: 16px; color: #333; min-width: 200px; }
    .gb-search-select { flex: 0.5; padding: 15px 20px; border-left: 1px solid #eee; border-top: none; border-right: none; border-bottom: none; outline: none; font-size: 16px; color: #333; background: white; min-width: 150px; }
    
    /* Search Button (Emerald) */
    .gb-search-btn { background: #047857; color: white; border: none; padding: 0 40px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 16px; }
    .gb-search-btn:hover { background: #065f46; }

    /* GRID LAYOUT */
    .gb-dir-container { max-width: 1200px; margin: -60px auto 60px; padding: 0 20px; position: relative; z-index: 10; }
    .gb-cleaner-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; }
    
    /* CARD DESIGN */
    .gb-cleaner-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: 0.3s; display: flex; flex-direction: column; text-decoration: none; border: 1px solid #f1f5f9; }
    .gb-cleaner-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border-color: #2e7d32; }
    
    .gb-card-img-wrap { height: 220px; background: #f8fafc; overflow: hidden; position: relative; }
    .gb-card-img { width: 100%; height: 100%; object-fit: cover; }
    
    /* VERIFIED BADGE */
    .gb-card-verified { 
        position: absolute; top: 15px; right: 15px; 
        background: #ffffff; 
        color: #2e7d32; 
        font-size: 11px; font-weight: 800; 
        padding: 6px 12px; border-radius: 20px; 
        text-transform: uppercase; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
        display: flex; align-items: center; gap: 6px; 
    }
    
    .gb-card-body { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
    .gb-card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
    .gb-card-name { margin: 0; font-size: 20px; font-weight: 800; color: #1d1d1f; }
    .gb-card-rate { color: #2e7d32; font-weight: 700; font-size: 16px; }
    
    .gb-card-meta { font-size: 13px; color: #64748b; margin-bottom: 15px; display: flex; gap: 15px; }
    .gb-card-excerpt { font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 25px; flex-grow: 1; }
    
    .gb-card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: auto; }
    
    /* Buttons */
    .gb-btn-outline { display: block; text-align: center; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; }
    .gb-btn-outline:hover { background: #f8fafc; color: #2e7d32; border-color: #cbd5e1; }
    
    .gb-btn-solid { display: block; text-align: center; padding: 12px; background: #2e7d32; border-radius: 8px; color: white; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; }
    .gb-btn-solid:hover { background: #1b5e20; }

    /* PAGINATION */
    .gb-pagination { margin-top: 60px; text-align: center; display: flex; justify-content: center; gap: 8px; }
    .gb-pagination a, .gb-pagination span { padding: 10px 16px; border: 1px solid #e2e8f0; background: white; border-radius: 8px; text-decoration: none; color: #1d1d1f; font-weight: 600; font-size: 14px; transition: 0.2s; }
    .gb-pagination span.current { background: #2e7d32; color: white; border-color: #2e7d32; }
    .gb-pagination a:hover { background: #f1f5f9; }

    @media (max-width: 768px) {
        .gb-search-wrapper { flex-direction: column; }
        .gb-search-select { border-left: none; border-top: 1px solid #eee; }
        .gb-search-btn { padding: 15px; }
    }
</style>

<main id="main-content">
    <section class="gb-dir-hero">
        <?php if ($listings_hero_bg !== ''): ?>
            <div class="gb-dir-hero-media" aria-hidden="true"><img src="<?php echo esc_url($listings_hero_bg); ?>" alt=""></div>
            <div class="gb-dir-hero-overlay" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="gb-dir-hero-content">
            <?php if ($listings_eyebrow !== ''): ?>
                <span class="gb-dir-eyebrow"><?php echo esc_html($listings_eyebrow); ?></span>
            <?php endif; ?>
            <h1 class="gb-dir-title"><?php echo esc_html($listings_title); ?></h1>
            <p class="gb-dir-sub"><?php echo esc_html($listings_sub); ?></p>
        </div>
    </section>

    <?php if ($listings_trust !== ''): ?>
        <div class="gb-dir-trust"><span><?php echo esc_html($listings_trust); ?></span></div>
    <?php endif; ?>

    <section class="gb-dir-search" aria-labelledby="gb-dir-search-head">
        <h2 id="gb-dir-search-head" class="gb-dir-search-head"><?php echo esc_html($listings_search_head); ?></h2>
        <form class="gb-search-wrapper" action="<?php echo home_url('/cleaner-listings'); ?>" method="get">
            <label class="gb-sr-only" for="cleaner-search-postcode">Search by postcode</label>
            <input id="cleaner-search-postcode" type="text" name="k" class="gb-search-input" placeholder="Search by postcode..." value="<?php echo esc_attr($search_term); ?>" maxlength="10" autocomplete="postal-code" inputmode="text">
            <label class="gb-sr-only" for="cleaner-search-service">Service type</label>
            <select id="cleaner-search-service" name="service" class="gb-search-select">
            <option value="">Any Service</option>
            <option value="standard" <?php selected($service_filter, 'standard'); ?>>Standard Clean</option>
            <option value="deep" <?php selected($service_filter, 'deep'); ?>>Deep Clean</option>
            <option value="moving" <?php selected($service_filter, 'moving'); ?>>Move In/Out</option>
            </select>
            <button type="submit" class="gb-search-btn">Search</button>
        </form>
        <p class="gb-verified-cue">
            <button type="button" class="gb-verified-trigger" aria-haspopup="dialog" data-gb-open-verified><?php echo esc_html($verified_link); ?></button>
        </p>
    </section>

    <div class="gb-verified-modal" id="gb-verified-modal" role="dialog" aria-modal="true" aria-labelledby="gb-verified-modal-title" hidden>
        <div class="gb-verified-backdrop" data-gb-close-verified></div>
        <div class="gb-verified-panel" role="document">
            <button type="button" class="gb-verified-close" data-gb-close-verified aria-label="Close">&times;</button>
            <h2 id="gb-verified-modal-title"><?php echo esc_html($verified_title); ?></h2>
            <?php
            // A blank line separates paragraphs; consecutive single lines become
            // a tick list, so a manager can edit this as plain text.
            $gb_v_blocks = preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $verified_body));
            foreach ($gb_v_blocks as $gb_v_block) {
                $gb_v_lines = array_values(array_filter(array_map('trim', explode("\n", $gb_v_block)), 'strlen'));
                if (count($gb_v_lines) > 1) {
                    echo '<ul class="gb-verified-list">';
                    foreach ($gb_v_lines as $gb_i => $gb_v_line) {
                        if ($gb_i === 0 && substr($gb_v_line, -1) === ':') {
                            echo '</ul><p>' . esc_html($gb_v_line) . '</p><ul class="gb-verified-list">';
                            continue;
                        }
                        echo '<li>' . esc_html($gb_v_line) . '</li>';
                    }
                    echo '</ul>';
                } elseif (!empty($gb_v_lines)) {
                    echo '<p>' . esc_html($gb_v_lines[0]) . '</p>';
                }
            }
            ?>
        </div>
    </div>

    <div class="gb-dir-container">
    
    <?php if ( $search_error ) : ?>
        <p style="margin-bottom: 30px; color: #b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 14px;"><?php echo esc_html($search_error); ?></p>
    <?php elseif ( $search_term !== '' ) : ?>
        <p style="margin-bottom: 30px; color: #64748b;">Found <strong><?php echo $cleaners->found_posts; ?></strong> results for "<?php echo esc_html($search_term); ?>"</p>
    <?php endif; ?>

    <div class="gb-cleaner-grid">
        <?php if($cleaners->have_posts()): ?>
            <?php while($cleaners->have_posts()): $cleaners->the_post(); 
                $rate = get_post_meta(get_the_ID(), 'hourly_rate', true) ?: '15';
                $location = get_post_meta(get_the_ID(), 'location', true) ?: 'Manchester, UK';
                $is_verified = get_post_meta(get_the_ID(), 'is_verified', true);
            ?>
            <div class="gb-cleaner-card">
                <div class="gb-card-img-wrap">
                    <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf('View profile for %s', get_the_title())); ?>">
                        <?php if(has_post_thumbnail()): ?>
                            <?php the_post_thumbnail('medium', ['class' => 'gb-card-img', 'alt' => '']); ?>
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#cbd5e1; font-size:48px;">
                                <span class="dashicons dashicons-id"></span>
                            </div>
                        <?php endif; ?>
                    </a>
                    
                    <?php if($is_verified): ?>
                        <span class="gb-card-verified">
                            <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-icon-logo.png" style="height:14px; width:auto;" alt="" aria-hidden="true">
                            Verified
                        </span>
                    <?php endif; ?>
                    
                </div>
                
                <div class="gb-card-body">
                    <div class="gb-card-header">
                        <h2 class="gb-card-name"><a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;"><?php the_title(); ?></a></h2>
                        <span class="gb-card-rate">£<?php echo esc_html($rate); ?>/hr</span>
                    </div>
                    
                    <div class="gb-card-meta">
                        <span>📍 <?php echo esc_html($location); ?></span>
                    </div>

                    <div class="gb-card-excerpt">
                        <?php echo wp_trim_words(get_the_content(), 15, '...'); ?>
                    </div>
                    
                    <div class="gb-card-actions">
                        <a href="<?php the_permalink(); ?>" class="gb-btn-outline">View Profile</a>
                        <a href="<?php echo home_url('/book?cleaner=' . get_the_ID()); ?>" class="gb-btn-solid">Book Now</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px; background: white; border-radius: 12px; color: #64748b;">
                <h2><?php echo esc_html($listings_none); ?></h2>
            </div>
        <?php endif; wp_reset_postdata(); ?>
    </div>

    <div class="gb-pagination">
        <?php echo paginate_links(array('total' => $cleaners->max_num_pages, 'current' => $paged, 'prev_text' => '←', 'next_text' => '→')); ?>
    </div>
    </div>
</main>

<script>
(function () {
    var modal = document.getElementById('gb-verified-modal');
    if (!modal) { return; }
    var lastFocused = null;

    function openModal(trigger) {
        lastFocused = trigger || document.activeElement;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var close = modal.querySelector('.gb-verified-close');
        if (close) { close.focus(); }
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-gb-open-verified]');
        if (opener) { e.preventDefault(); openModal(opener); return; }
        if (e.target.closest('[data-gb-close-verified]')) { e.preventDefault(); closeModal(); }
    });

    document.addEventListener('keydown', function (e) {
        if (modal.hidden) { return; }
        if (e.key === 'Escape') { closeModal(); return; }
        if (e.key !== 'Tab') { return; }
        // Keep tabbing inside the dialog while it is open.
        var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) { return; }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
})();
</script>

<?php get_footer(); ?>
