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

// Search Logic
if ( ! empty($_GET['k']) ) { $args['s'] = sanitize_text_field($_GET['k']); }

$cleaners = new WP_Query($args);

$listings_title = get_post_meta(get_the_ID(), '_gb_listings_title', true) ?: 'Find Your Professional';
$listings_sub = get_post_meta(get_the_ID(), '_gb_listings_sub', true) ?: 'Trusted, vetted, and verified cleaners ready to help.';
$listings_none = get_post_meta(get_the_ID(), '_gb_listings_none', true) ?: 'No professionals found. Try adjusting your search criteria.';
?>

<style>
    /* PAGE LAYOUT & HERO */
    .gb-dir-hero { 
        background: #2e7d32; /* Brand Green */
        padding: 80px 20px 100px; 
        text-align: center; 
        color: white; 
        position: relative; 
    }
    
    /* FIX: Force title to be white to override global h1 styles */
    .gb-dir-title { 
        font-size: 42px; 
        font-weight: 800; 
        margin: 0; 
        letter-spacing: -1px; 
        color: #ffffff !important; 
    }
    
    .gb-dir-sub { color: rgba(255,255,255,0.9); margin-top: 15px; font-size: 18px; margin-bottom: 40px; }
    
    /* SEARCH BAR */
    .gb-search-wrapper { max-width: 800px; margin: 0 auto; background: white; padding: 10px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); display: flex; gap: 10px; flex-wrap: wrap; }
    .gb-search-input { flex: 1; padding: 15px 20px; border: none; outline: none; font-size: 16px; color: #333; min-width: 200px; }
    .gb-search-select { flex: 0.5; padding: 15px 20px; border-left: 1px solid #eee; border-top: none; border-right: none; border-bottom: none; outline: none; font-size: 16px; color: #333; background: white; min-width: 150px; }
    
    /* Search Button (Emerald) */
    .gb-search-btn { background: #10b981; color: white; border: none; padding: 0 40px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 16px; }
    .gb-search-btn:hover { background: #059669; }

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

<div class="gb-dir-hero">
    <h1 class="gb-dir-title"><?php echo esc_html($listings_title); ?></h1>
    <p class="gb-dir-sub"><?php echo esc_html($listings_sub); ?></p>
    
    <form class="gb-search-wrapper" action="<?php echo home_url('/cleaner-listings'); ?>" method="get">
        <input type="text" name="k" class="gb-search-input" placeholder="Search by name..." value="<?php echo isset($_GET['k']) ? esc_attr($_GET['k']) : ''; ?>">
        <select name="service" class="gb-search-select">
            <option value="">Any Service</option>
            <option value="standard">Standard Clean</option>
            <option value="deep">Deep Clean</option>
            <option value="moving">Move In/Out</option>
        </select>
        <button type="submit" class="gb-search-btn">Search</button>
    </form>
</div>

<div class="gb-dir-container">
    
    <?php if ( ! empty($_GET['k']) ) : ?>
        <p style="margin-bottom: 30px; color: #64748b;">Found <strong><?php echo $cleaners->found_posts; ?></strong> results for "<?php echo esc_html($_GET['k']); ?>"</p>
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
                    <a href="<?php the_permalink(); ?>">
                        <?php if(has_post_thumbnail()): ?>
                            <?php the_post_thumbnail('medium', ['class' => 'gb-card-img']); ?>
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#cbd5e1; font-size:48px;">
                                <span class="dashicons dashicons-id"></span>
                            </div>
                        <?php endif; ?>
                    </a>
                    
                    <?php if($is_verified): ?>
                        <span class="gb-card-verified">
                            <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-icon-logo.png" style="height:14px; width:auto;"> 
                            Verified
                        </span>
                    <?php endif; ?>
                    
                </div>
                
                <div class="gb-card-body">
                    <div class="gb-card-header">
                        <h3 class="gb-card-name"><a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;"><?php the_title(); ?></a></h3>
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
                <h3><?php echo esc_html($listings_none); ?></h3>
            </div>
        <?php endif; wp_reset_postdata(); ?>
    </div>

    <div class="gb-pagination">
        <?php echo paginate_links(array('total' => $cleaners->max_num_pages, 'current' => $paged, 'prev_text' => '←', 'next_text' => '→')); ?>
    </div>
</div>

<?php get_footer(); ?>
