<?php
$hidden_ids = function_exists( 'gb_get_hidden_blog_post_ids' ) ? gb_get_hidden_blog_post_ids() : [];
$blog_posts = new WP_Query(
    [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 6,
        'post__not_in'        => $hidden_ids,
        'ignore_sticky_posts' => true,
    ]
);

get_header();
?>

<style>
    .gb-blog-hero { background: #1b5e20; color: #fff; padding: 90px 20px 110px; }
    .gb-blog-shell { max-width: 1120px; margin: 0 auto; }
    .gb-blog-kicker { display: inline-block; font-size: 12px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,0.72); margin-bottom: 14px; }
    .gb-blog-title { margin: 0; font-size: 46px; font-weight: 800; letter-spacing: -0.04em; color: #fff; }
    .gb-blog-subtitle { max-width: 720px; margin: 16px 0 0; font-size: 18px; line-height: 1.7; color: rgba(255,255,255,0.86); }
    .gb-blog-body { max-width: 1120px; margin: -56px auto 90px; padding: 0 20px; position: relative; z-index: 10; }
    .gb-blog-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
    .gb-blog-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 24px; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08); }
    .gb-blog-card h2 { margin: 0 0 10px; font-size: 24px; line-height: 1.2; }
    .gb-blog-card p { color: #475569; line-height: 1.7; }
    .gb-blog-meta { display: inline-block; margin-bottom: 14px; font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #2e7d32; }
    .gb-blog-link { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; color: #166534; font-weight: 700; text-decoration: none; }
    .gb-blog-empty { display: grid; gap: 18px; }
    .gb-blog-note { background: #fff; border: 1px dashed #cbd5e1; border-radius: 18px; padding: 22px; color: #475569; }

    @media (max-width: 720px) {
        .gb-blog-title { font-size: 36px; }
        .gb-blog-subtitle { font-size: 16px; }
    }
</style>

<section class="gb-blog-hero">
    <div class="gb-blog-shell">
        <span class="gb-blog-kicker">Journal</span>
        <h1 class="gb-blog-title">Eco Blog</h1>
        <p class="gb-blog-subtitle">Updates from The Green Broom, cleaner standards, platform improvements, and practical notes for households and professionals using the service.</p>
    </div>
</section>

<section class="gb-blog-body">
    <?php if ( $blog_posts->have_posts() ) : ?>
        <div class="gb-blog-grid">
            <?php while ( $blog_posts->have_posts() ) : $blog_posts->the_post(); ?>
                <article class="gb-blog-card">
                    <span class="gb-blog-meta"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></span>
                    <h2><?php the_title(); ?></h2>
                    <p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ?: get_the_content() ), 28, '...' ) ); ?></p>
                    <a class="gb-blog-link" href="<?php the_permalink(); ?>">Read update <span aria-hidden="true">-></span></a>
                </article>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <div class="gb-blog-empty">
            <div class="gb-blog-note">
                <strong>Fresh updates are being prepared.</strong>
                We are keeping the journal tidy for launch, so older placeholder posts have been removed from the public feed. New service, onboarding, and platform updates will appear here.
            </div>
            <div class="gb-blog-grid">
                <article class="gb-blog-card">
                    <span class="gb-blog-meta">Platform update</span>
                    <h2>Cleaner verification now gates public listings</h2>
                    <p>New cleaner accounts stay private until verification documents have been submitted and approved, keeping the public directory aligned with the trust promise shown across the site.</p>
                </article>
                <article class="gb-blog-card">
                    <span class="gb-blog-meta">Payments</span>
                    <h2>Stripe Connect sandbox is ready to exercise</h2>
                    <p>The manager dashboard now makes it clear when the platform is in Stripe test mode, so onboarding links, sample storefront checkouts, and webhook validation can all be rehearsed safely.</p>
                </article>
                <article class="gb-blog-card">
                    <span class="gb-blog-meta">Policies</span>
                    <h2>Privacy, cookies, and terms have been consolidated</h2>
                    <p>Core launch policies now resolve cleanly from the live footer and support legacy links, reducing broken routes while keeping the public site coherent.</p>
                </article>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php get_footer(); ?>
