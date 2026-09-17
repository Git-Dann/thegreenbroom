<?php
/* Template Name: Eco Products */

get_header();

$resources = function_exists( 'gb_get_resource_definitions' ) ? gb_get_resource_definitions() : array();

/*
 * Everything below used to be hard-coded, so editing this page in the manager
 * dashboard appeared to do nothing — the template simply ignored the saved
 * content. The copy now comes from the page itself (title + body) and from
 * editable meta, falling back to the previous wording so nothing changes
 * visually until a manager edits it.
 */
$gb_eco_id = get_the_ID();
$gb_eco    = function ( $key, $default ) use ( $gb_eco_id ) {
    $value = get_post_meta( $gb_eco_id, $key, true );
    return ( $value === '' || $value === null ) ? $default : $value;
};

$gb_eco_sub   = $gb_eco( '_gb_eco_hero_sub', 'Practical guidance for choosing and using effective, lower-impact cleaning products at home and on every job.' );
$gb_eco_c1_t  = $gb_eco( '_gb_eco_c1_title', 'Eco Cleaning Products Guide' );
$gb_eco_c1_d  = $gb_eco( '_gb_eco_c1_text', 'A simple guide to plant-based products and easy alternatives for everyday cleaning.' );
$gb_eco_c2_t  = $gb_eco( '_gb_eco_c2_title', 'Make Your Own' );
$gb_eco_c2_d  = $gb_eco( '_gb_eco_c2_text', 'Simple, lower-impact ideas for making your own cleaning products. The full guide is coming soon.' );
$gb_eco_c2_l  = trim( (string) $gb_eco( '_gb_eco_c2_link', '' ) );
$gb_eco_body  = trim( get_the_content() );
?>

<style>
    #gb-eco-products-page { width:100vw; position:relative; left:50%; margin-left:-50vw; background:#f8fafc; color:#334155; }
    #gb-eco-products-page .gb-eco-hero { background:#1b5e20; color:#fff; padding:80px 20px; text-align:center; }
    #gb-eco-products-page .gb-eco-hero h1 { color:#fff; margin:0 0 14px; font-size:clamp(2.2rem, 5vw, 3.5rem); }
    #gb-eco-products-page .gb-eco-hero p { max-width:680px; margin:0 auto; line-height:1.7; color:rgba(255,255,255,.9); }
    #gb-eco-products-page .gb-eco-container { max-width:1000px; margin:0 auto; padding:60px 20px 90px; }
    #gb-eco-products-page .gb-eco-body { max-width:760px; margin:0 auto 46px; line-height:1.75; }
    #gb-eco-products-page .gb-eco-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:22px; }
    #gb-eco-products-page .gb-eco-item { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:28px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
    #gb-eco-products-page .gb-eco-item h2 { margin:0 0 10px; color:#166534; font-size:1.25rem; }
    #gb-eco-products-page .gb-eco-item p { margin:0 0 22px; line-height:1.6; color:#64748b; }
    #gb-eco-products-page .gb-eco-link { display:inline-block; background:#2e7d32; color:#fff; padding:11px 16px; border-radius:6px; text-decoration:none; font-weight:700; }
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
        <div class="gb-eco-grid">
            <?php if ( isset( $resources['eco-cleaning-products'] ) ) : ?>
                <article class="gb-eco-item">
                    <h2><?php echo esc_html( $gb_eco_c1_t ); ?></h2>
                    <p><?php echo esc_html( $gb_eco_c1_d ); ?></p>
                    <a class="gb-eco-link" target="_blank" rel="noopener" href="<?php echo esc_url( gb_get_resource_url( 'eco-cleaning-products' ) ); ?>">View guide</a>
                </article>
            <?php endif; ?>
            <article class="gb-eco-item">
                <h2><?php echo esc_html( $gb_eco_c2_t ); ?></h2>
                <p><?php echo esc_html( $gb_eco_c2_d ); ?></p>
                <?php if ( $gb_eco_c2_l !== '' ) : ?>
                    <a class="gb-eco-link" href="<?php echo esc_url( $gb_eco_c2_l ); ?>">View guide</a>
                <?php else : ?>
                    <span class="gb-eco-link" aria-disabled="true" style="background:#64748b; cursor:default;">Coming soon</span>
                <?php endif; ?>
            </article>
        </div>
    </div>
</main>

<?php get_footer(); ?>
