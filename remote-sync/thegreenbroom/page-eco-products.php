<?php
/* Template Name: Eco Products */

get_header();

/*
 * The cards used to be two hard-coded blocks, then two sets of page-meta
 * fields — neither of which scales to a catalogue of dozens or hundreds of
 * documents. Cards now come from the gb_eco_product post type, managed under
 * Eco Products in the manager dashboard. The heading, intro and body still
 * come from the page itself.
 */
$gb_eco_id = get_the_ID();
$gb_eco_sub = get_post_meta( $gb_eco_id, '_gb_eco_hero_sub', true );
if ( $gb_eco_sub === '' || $gb_eco_sub === null ) {
    $gb_eco_sub = 'Practical guidance for choosing and using effective, lower-impact cleaning products at home and on every job.';
}
$gb_eco_body     = trim( get_the_content() );
$gb_eco_products = function_exists( 'gb_get_eco_products' ) ? gb_get_eco_products() : array();
?>

<style>
    #gb-eco-products-page { width:100vw; position:relative; left:50%; margin-left:-50vw; background:#f8fafc; color:#334155; }
    #gb-eco-products-page .gb-eco-hero { background:#1b5e20; color:#fff; padding:80px 20px; text-align:center; }
    #gb-eco-products-page .gb-eco-hero h1 { color:#fff; margin:0 0 14px; font-size:clamp(2.2rem, 5vw, 3.5rem); }
    #gb-eco-products-page .gb-eco-hero p { max-width:680px; margin:0 auto; line-height:1.7; color:rgba(255,255,255,.9); }
    #gb-eco-products-page .gb-eco-container { max-width:1120px; margin:0 auto; padding:60px 20px 90px; }
    #gb-eco-products-page .gb-eco-body { max-width:760px; margin:0 auto 46px; line-height:1.75; }
    #gb-eco-products-page .gb-eco-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:22px; align-items:stretch; }
    #gb-eco-products-page .gb-eco-item { display:flex; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 8px 24px rgba(15,23,42,.05); }
    #gb-eco-products-page .gb-eco-thumb { display:block; height:160px; background:#f0fdf4; border-bottom:1px solid #f1f5f9; overflow:hidden; }
    #gb-eco-products-page .gb-eco-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    #gb-eco-products-page .gb-eco-item-body { display:flex; flex-direction:column; flex:1 1 auto; padding:28px; }
    #gb-eco-products-page .gb-eco-item h2 { margin:0 0 10px; color:#166534; font-size:1.25rem; }
    #gb-eco-products-page .gb-eco-item p { margin:0 0 22px; line-height:1.6; color:#64748b; }
    #gb-eco-products-page .gb-eco-item-cta { margin-top:auto; }
    #gb-eco-products-page .gb-eco-link { display:inline-block; background:#2e7d32; color:#fff; padding:11px 16px; border-radius:6px; text-decoration:none; font-weight:700; }
    #gb-eco-products-page .gb-eco-link:hover { background:#1b5e20; color:#fff; }
    #gb-eco-products-page .gb-eco-empty { padding:50px 20px; text-align:center; color:#64748b; background:#fff; border:1px solid #e2e8f0; border-radius:10px; }
    @media (max-width:760px) { #gb-eco-products-page .gb-eco-grid { grid-template-columns:1fr; } }
</style>

<main id="gb-eco-products-page">
    <section class="gb-eco-hero">
        <h1><?php the_title(); ?></h1>
        <p><?php echo esc_html( $gb_eco_sub ); ?></p>
    </section>
    <div class="gb-eco-container">
        <?php if ( $gb_eco_body !== '' ) : ?>
            <div class="gb-eco-body"><?php the_content(); ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $gb_eco_products ) ) : ?>
            <div class="gb-eco-grid">
                <?php foreach ( $gb_eco_products as $gb_eco_product ) :
                    $gb_eco_link  = function_exists( 'gb_get_eco_product_link' ) ? gb_get_eco_product_link( $gb_eco_product->ID ) : '';
                    $gb_eco_thumb = get_the_post_thumbnail_url( $gb_eco_product->ID, 'medium_large' );
                    $gb_eco_desc  = trim( wp_strip_all_tags( $gb_eco_product->post_content ) );
                    // Uploaded files open in a new tab; an internal path stays in place.
                    $gb_eco_external = $gb_eco_link !== '' && strpos( $gb_eco_link, '/' ) !== 0;
                ?>
                    <article class="gb-eco-item">
                        <?php if ( $gb_eco_thumb ) : ?>
                            <span class="gb-eco-thumb"><img src="<?php echo esc_url( $gb_eco_thumb ); ?>" alt=""></span>
                        <?php endif; ?>
                        <div class="gb-eco-item-body">
                            <h2><?php echo esc_html( get_the_title( $gb_eco_product ) ); ?></h2>
                            <?php if ( $gb_eco_desc !== '' ) : ?>
                                <p><?php echo esc_html( $gb_eco_desc ); ?></p>
                            <?php endif; ?>
                            <div class="gb-eco-item-cta">
                                <?php if ( $gb_eco_link !== '' ) : ?>
                                    <a class="gb-eco-link" href="<?php echo esc_url( $gb_eco_link ); ?>"<?php echo $gb_eco_external ? ' target="_blank" rel="noopener"' : ''; ?>>View guide</a>
                                <?php else : ?>
                                    <span class="gb-eco-link" aria-disabled="true" style="background:#64748b; cursor:default;">Coming soon</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="gb-eco-empty">
                <p style="margin:0;">Our eco product guides are being updated. Please check back shortly.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
