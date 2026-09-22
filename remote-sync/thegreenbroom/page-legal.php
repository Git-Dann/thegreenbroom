<?php
/**
 * Template Name: Legal Page (Text Heavy)
 * Description: A clean, centered template designed for Privacy, Terms, and GDPR content.
 */

get_header();

$legal_slug = get_post_field('post_name', get_the_ID());
$contact_url = function_exists('gb_get_contact_page_url') ? gb_get_contact_page_url() : home_url('/contact/');
/*
 * These pages used to render from a hardcoded array that always beat the
 * page's own content, so editing them in the manager dashboard saved
 * successfully and changed nothing. The page content is now the source of
 * truth, with the old copy kept in inc/legal-content.php purely as a fallback
 * for an empty page - a legal page must never render blank.
 */
$legal_body  = trim( (string) get_post_field( 'post_content', get_the_ID() ) );
$legal_seed  = function_exists( 'gb_get_legal_seed_content' ) ? gb_get_legal_seed_content( $legal_slug ) : '';
$legal_title = get_the_title();

if ( $legal_body === '' && $legal_seed !== '' && function_exists( 'gb_get_legal_seed_title' ) ) {
    $seed_title = gb_get_legal_seed_title( $legal_slug );
    if ( $seed_title !== '' ) {
        $legal_title = $seed_title;
    }
}
?>

<style>
    /* Legal Layout Styles */
    .gb-legal-hero { 
        background: #f8fafc; 
        padding: 80px 0 60px; 
        text-align: center; 
        border-bottom: 1px solid #e2e8f0; 
    }
    .gb-legal-container { 
        max-width: 800px; 
        margin: 0 auto; 
        padding: 60px 20px 100px; 
    }
    
    /* Typography for Text-Heavy Content */
    .gb-legal-content {
        font-size: 16px;
        line-height: 1.8;
        color: #334155; /* Slate 700 */
    }
    .gb-legal-content h1, 
    .gb-legal-content h2, 
    .gb-legal-content h3 { 
        color: #0f172a; /* Slate 900 */
        margin-top: 2em;
        margin-bottom: 0.5em;
        letter-spacing: -0.02em;
    }
    .gb-legal-content h2 { font-size: 24px; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    .gb-legal-content h3 { font-size: 18px; font-weight: 700; }
    
    .gb-legal-content p { margin-bottom: 1.5em; }
    
    .gb-legal-content ul, 
    .gb-legal-content ol { 
        margin-bottom: 2em; 
        padding-left: 20px; 
    }
    .gb-legal-content li { margin-bottom: 0.8em; }

    /* Green OS Accent */
    .gb-legal-accent {
        color: #2e7d32;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.1em;
        display: block;
        margin-bottom: 10px;
    }

    .gb-last-updated {
        font-size: 13px;
        color: #94a3b8;
        margin-top: 10px;
        font-style: italic;
    }
</style>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    
    <header class="gb-legal-hero">
        <div class="gb-container">
            <span class="gb-legal-accent">Legal Documentation</span>
            <h1 style="font-size: 42px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -1px;">
                <?php echo esc_html($legal_title); ?>
            </h1>
            <p class="gb-last-updated">Last Updated: <?php echo get_the_modified_date('F j, Y'); ?></p>
        </div>
    </header>

    <div class="gb-legal-container">
        <div class="gb-legal-content">
            <?php if ( $legal_body !== '' ) : ?>
                <?php
                while ( have_posts() ) :
                    the_post();
                    the_content();
                endwhile;
                ?>
            <?php else : ?>
                <?php echo wp_kses_post( $legal_seed ); ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 60px; padding-top: 40px; border-top: 1px solid #f1f5f9;">
            <?php if ( $legal_slug === 'subscribers-terms-of-association' && function_exists( 'gb_get_resource_url' ) ) : ?>
                <p><a href="<?php echo esc_url( gb_get_resource_url( 'subscribers-terms-of-association' ) ); ?>" target="_blank" rel="noopener" style="color: #2e7d32; font-weight: 700; text-decoration: none;">Download a PDF copy</a></p>
            <?php endif; ?>
            <p style="font-size: 14px; color: #64748b;">
                Have questions about our legal policies? 
                <a href="<?php echo esc_url($contact_url); ?>" style="color: #2e7d32; font-weight: 700; text-decoration: none;">
                    Contact Management &rarr;
                </a>
            </p>
        </div>
    </div>

</article>

<?php get_footer(); ?>
