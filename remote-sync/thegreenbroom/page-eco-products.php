<?php
/* Template Name: Eco Products */

get_header();

$resources = function_exists( 'gb_get_resource_definitions' ) ? gb_get_resource_definitions() : array();
?>

<style>
    #gb-eco-products-page { width:100vw; position:relative; left:50%; margin-left:-50vw; background:#f8fafc; color:#334155; }
    #gb-eco-products-page .gb-eco-hero { background:#1b5e20; color:#fff; padding:80px 20px; text-align:center; }
    #gb-eco-products-page .gb-eco-hero h1 { color:#fff; margin:0 0 14px; font-size:clamp(2.2rem, 5vw, 3.5rem); }
    #gb-eco-products-page .gb-eco-hero p { max-width:680px; margin:0 auto; line-height:1.7; color:rgba(255,255,255,.9); }
    #gb-eco-products-page .gb-eco-container { max-width:1000px; margin:0 auto; padding:60px 20px 90px; }
    #gb-eco-products-page .gb-eco-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }
    #gb-eco-products-page .gb-eco-item { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:28px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
    #gb-eco-products-page .gb-eco-item h2 { margin:0 0 10px; color:#166534; font-size:1.25rem; }
    #gb-eco-products-page .gb-eco-item p { margin:0 0 22px; line-height:1.6; color:#64748b; }
    #gb-eco-products-page .gb-eco-link { display:inline-block; background:#2e7d32; color:#fff; padding:11px 16px; border-radius:6px; text-decoration:none; font-weight:700; }
    @media (max-width:760px) { #gb-eco-products-page .gb-eco-grid { grid-template-columns:1fr; } }
</style>

<main id="gb-eco-products-page">
    <section class="gb-eco-hero">
        <h1>Eco Products</h1>
        <p>Practical guidance for choosing and using effective, lower-impact cleaning products at home and on every job.</p>
    </section>
    <div class="gb-eco-container">
        <div class="gb-eco-grid">
            <?php if ( isset( $resources['eco-cleaning-products'] ) ) : ?>
                <article class="gb-eco-item">
                    <h2>Eco Cleaning Products Guide</h2>
                    <p>A simple guide to plant-based products and easy alternatives for everyday cleaning.</p>
                    <a class="gb-eco-link" target="_blank" rel="noopener" href="<?php echo esc_url( gb_get_resource_url( 'eco-cleaning-products' ) ); ?>">View guide</a>
                </article>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php get_footer(); ?>
