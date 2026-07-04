<?php
/**
 * Template Name: Cleaner Archive (Wide 80vw Design)
 * Description: Main list view for cleaners
 */

get_header(); 
?>

<style>
    /* Reset & Background */
    body { background-color: #F5F5EF !important; }
    
    /* Container: Matches Single Profile Width */
    .gb-archive-container {
        width: 80vw;
        max-width: 1800px;
        margin: 60px auto;
        padding: 0;
        font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* Page Header */
    .gb-archive-header {
        text-align: center;
        margin-bottom: 60px;
    }
    .gb-page-title {
        font-size: 48px; font-weight: 800; color: #111; margin-bottom: 15px; letter-spacing: -1px;
    }
    .gb-page-subtitle {
        font-size: 20px; color: #666; max-width: 600px; margin: 0 auto; line-height: 1.5;
    }

    /* The Grid */
    .gb-cleaner-grid {
        display: grid;
        /* Responsive columns: Minimum 320px wide */
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 40px;
    }

    /* Individual Card */
    .gb-card-link { text-decoration: none; color: inherit; display: block; height: 100%; }
    
    .gb-cleaner-card {
        background: #fff;
        border-radius: 24px; /* Matches Single Profile */
        border: 1px solid #e5e5e5;
        padding: 25px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .gb-cleaner-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.06);
        border-color: #d0d0d0;
    }

    /* Card Image Area */
    .gb-card-head {
        position: relative;
        margin-bottom: 20px;
    }

    .gb-card-avatar {
        width: 100%;
        aspect-ratio: 1 / 1; /* Square */
        object-fit: cover;
        border-radius: 16px;
        background-color: #f0f0f0;
    }

    /* Verified Badge (Floating Top Right) */
    .gb-card-verified {
        position: absolute;
        top: 10px; right: 10px;
        background: #d1f5d3; color: #1f5726;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px; font-weight: 700;
        display: flex; align-items: center; gap: 4px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        border: 1px solid #fff;
    }

    /* Card Info */
    .gb-card-body {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .gb-card-name {
        font-size: 26px; font-weight: 800; color: #111; margin: 0 0 5px 0;
    }

    .gb-card-loc {
        font-size: 16px; color: #666; font-weight: 500;
        display: flex; align-items: center; gap: 6px;
        margin-bottom: 20px;
    }

    /* Footer Row inside Card */
    .gb-card-footer {
        margin-top: auto;
        padding-top: 20px;
        border-top: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .gb-card-rate {
        font-size: 22px; font-weight: 800; color: #111;
    }
    .gb-card-rate span { font-size: 14px; color: #888; font-weight: 500; }

    .gb-card-btn {
        font-size: 15px; font-weight: 700; color: #2D4A2B;
        background: #f0f7f1; padding: 10px 20px; border-radius: 8px;
        transition: background 0.2s;
    }
    .gb-cleaner-card:hover .gb-card-btn { background: #2D4A2B; color: #fff; }

    /* Pagination */
    .gb-pagination { margin-top: 60px; text-align: center; }
    .gb-pagination .page-numbers {
        display: inline-block; padding: 10px 20px;
        background: #fff; border: 1px solid #ddd;
        border-radius: 8px; margin: 0 5px;
        text-decoration: none; color: #111; font-weight: 600;
    }
    .gb-pagination .current { background: #2D4A2B; color: #fff; border-color: #2D4A2B; }

    /* Responsive */
    @media(max-width: 768px) {
        .gb-archive-container { width: 90vw; }
        .gb-page-title { font-size: 36px; }
        .gb-cleaner-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="gb-archive-container">
    
    <header class="gb-archive-header">
        <h1 class="gb-page-title">Find a Cleaner Near You</h1>
        <p class="gb-page-subtitle">Browse our verified professionals in your area. Use the search filter to narrow down by location.</p>
        
        <div style="margin-top:30px;">
            <?php echo do_shortcode('[gb_cleaner_search]'); ?>
        </div>
    </header>

    <div class="gb-cleaner-grid">
        <?php 
        if ( have_posts() ) : 
            while ( have_posts() ) : the_post(); 
                // Get Meta
                $id = get_the_ID();
                $rate = function_exists('gb_get_cleaner_rate') ? gb_get_cleaner_rate($id) : (get_post_meta($id, 'hourly_rate', true) ?: get_post_meta($id, 'gb_hourly_rate', true));
                $loc  = get_post_meta($id, 'gb_postcode', true);
                $ver  = get_post_meta($id, 'gb_verified', true);
                $img_id = get_post_meta($id, 'gb_avatar_id', true);
                // Use smaller thumbnail size for grid to speed up loading
                $img_url = $img_id ? wp_get_attachment_image_src($img_id, 'large')[0] : 'https://www.gravatar.com/avatar/?d=mp&s=400';
        ?>

            <a href="<?php the_permalink(); ?>" class="gb-card-link">
                <div class="gb-cleaner-card">
                    
                    <div class="gb-card-head">
                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php the_title(); ?>" class="gb-card-avatar">
                        <?php if($ver): ?>
                            <div class="gb-card-verified">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Verified
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="gb-card-body">
                        <h3 class="gb-card-name"><?php the_title(); ?></h3>
                        
                        <div class="gb-card-loc">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <?php echo $loc ? esc_html(strtoupper(explode(' ', $loc)[0])) . ' Area' : 'Location Unknown'; ?>
                        </div>

                        <div class="gb-card-footer">
                            <div class="gb-card-rate">
                                £<?php echo esc_html($rate); ?> <span>/ hr</span>
                            </div>
                            <span class="gb-card-btn">View Profile</span>
                        </div>
                    </div>

                </div>
            </a>

        <?php endwhile; else : ?>
            <p style="text-align:center; font-size:18px; color:#666;">No cleaners found matching your criteria.</p>
        <?php endif; ?>
    </div>

    <div class="gb-pagination">
        <?php the_posts_pagination(array('prev_text' => '←', 'next_text' => '→')); ?>
    </div>

</div>

<?php get_footer(); ?>