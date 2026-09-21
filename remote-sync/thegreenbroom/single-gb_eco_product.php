<?php
/**
 * A single Eco Product guide.
 *
 * Guides used to be links out to PDFs. They can now carry their own write-up,
 * so each one needs a readable page. Content is read off the post object
 * rather than via get_the_content(), because this template renders outside a
 * have_posts()/the_post() loop and that would silently return an empty string.
 */

get_header();

$gb_guide      = get_post( get_the_ID() );
$gb_guide_body = $gb_guide ? trim( (string) $gb_guide->post_content ) : '';
$gb_guide_file = function_exists( 'gb_get_eco_product_link' ) ? gb_get_eco_product_link( get_the_ID() ) : '';
$gb_guide_thumb = get_the_post_thumbnail_url( get_the_ID(), 'large' );
// Only offer a download when there is a real file or external link, not when
// gb_get_eco_product_link() has fallen back to this very page.
$gb_guide_has_file = (int) get_post_meta( get_the_ID(), '_gb_eco_file', true ) > 0
    || trim( (string) get_post_meta( get_the_ID(), '_gb_eco_link', true ) ) !== '';
$gb_eco_page = get_page_by_path( 'eco-products' );
?>

<style>
    #gb-eco-guide { width:100vw; position:relative; left:50%; margin-left:-50vw; background:#f8fafc; color:#334155; }
    #gb-eco-guide .gb-guide-hero { background:#1b5e20; color:#fff; padding:70px 20px; text-align:center; }
    #gb-eco-guide .gb-guide-hero h1 { color:#fff; margin:0 auto; max-width:840px; font-size:clamp(1.9rem, 4vw, 2.9rem); line-height:1.2; }
    #gb-eco-guide .gb-guide-back { display:inline-block; margin-bottom:18px; color:rgba(255,255,255,.85); font-size:13px; font-weight:700; text-decoration:none; }
    #gb-eco-guide .gb-guide-back:hover { color:#fff; }
    #gb-eco-guide .gb-guide-wrap { max-width:820px; margin:0 auto; padding:54px 20px 84px; }
    #gb-eco-guide .gb-guide-thumb { width:100%; border-radius:12px; margin-bottom:34px; display:block; }
    #gb-eco-guide .gb-guide-body { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:44px; box-shadow:0 8px 24px rgba(15,23,42,.05); line-height:1.75; }
    #gb-eco-guide .gb-guide-body h2 { color:#1b5e20; font-size:1.45rem; margin:34px 0 14px; }
    #gb-eco-guide .gb-guide-body h3 { color:#166534; font-size:1.15rem; margin:26px 0 10px; }
    #gb-eco-guide .gb-guide-body > *:first-child { margin-top:0; }
    #gb-eco-guide .gb-guide-body ul, #gb-eco-guide .gb-guide-body ol { padding-left:22px; margin:0 0 18px; }
    #gb-eco-guide .gb-guide-body li { margin-bottom:8px; }
    #gb-eco-guide .gb-guide-body p { margin:0 0 18px; }
    #gb-eco-guide .gb-guide-cta { margin-top:30px; text-align:center; }
    #gb-eco-guide .gb-guide-btn { display:inline-block; background:#2e7d32; color:#fff; padding:13px 24px; border-radius:8px; text-decoration:none; font-weight:700; }
    #gb-eco-guide .gb-guide-btn:hover { background:#1b5e20; color:#fff; }
    @media (max-width:640px) { #gb-eco-guide .gb-guide-body { padding:26px 20px; } #gb-eco-guide .gb-guide-hero { padding:52px 18px; } }
</style>

<main id="gb-eco-guide">
    <section class="gb-guide-hero">
        <?php if ( $gb_eco_page ) : ?>
            <a class="gb-guide-back" href="<?php echo esc_url( get_permalink( $gb_eco_page ) ); ?>">&larr; All eco products</a>
        <?php endif; ?>
        <h1><?php the_title(); ?></h1>
    </section>

    <div class="gb-guide-wrap">
        <?php if ( $gb_guide_thumb ) : ?>
            <img class="gb-guide-thumb" src="<?php echo esc_url( $gb_guide_thumb ); ?>" alt="">
        <?php endif; ?>

        <article class="gb-guide-body">
            <?php
            if ( $gb_guide_body !== '' ) {
                echo apply_filters( 'the_content', $gb_guide_body );
            } else {
                echo '<p>This guide is being written. Please check back shortly.</p>';
            }
            ?>

            <?php if ( $gb_guide_has_file && $gb_guide_file !== '' ) : ?>
                <div class="gb-guide-cta">
                    <a class="gb-guide-btn" href="<?php echo esc_url( $gb_guide_file ); ?>" target="_blank" rel="noopener">Download the full guide</a>
                </div>
            <?php endif; ?>
        </article>
    </div>
</main>

<?php get_footer(); ?>
