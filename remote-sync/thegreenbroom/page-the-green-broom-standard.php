<?php
/**
 * The Green Broom Standard.
 *
 * Picked up automatically by slug, so no template has to be assigned by hand.
 *
 * The copy stays one rich-text field, edited from Manager OS like any other
 * page, because it is a document the client will rewrite and nobody wants to
 * fill in fourteen boxes to do that. The presentation is applied here instead:
 * the body is split on its own <h2> headings and each section is laid out in
 * the same language as How We Do It and What Cleaners Do - green hero, a lead
 * line, alternating sections, cards, a closing banner.
 *
 * So editing stays simple and the page stops looking like a text file.
 */

get_header();

$gb_std_post  = get_post( get_the_ID() );
$gb_std_body  = $gb_std_post ? trim( (string) $gb_std_post->post_content ) : '';
/*
 * The stored excerpt only. get_the_excerpt() invents one from the opening
 * words when none is set, so the hero would have shown a truncated copy of the
 * intro directly above the intro itself, ellipsis and all.
 */
$gb_std_lead  = $gb_std_post ? trim( (string) $gb_std_post->post_excerpt ) : '';

// Run the content through the usual filters first, so shortcodes, smart quotes
// and paragraph wrapping behave exactly as they would anywhere else.
$gb_std_html = apply_filters( 'the_content', $gb_std_body );

/*
 * Split on the writer's own <h2>s. PREG_SPLIT_DELIM_CAPTURE keeps the heading
 * text, so the result is: [intro, heading, body, heading, body, ...]. Anything
 * before the first heading is the intro and is shown as the lead paragraph.
 */
$gb_std_parts = preg_split( '/<h2[^>]*>(.*?)<\/h2>/is', $gb_std_html, -1, PREG_SPLIT_DELIM_CAPTURE );
$gb_std_intro = trim( (string) array_shift( $gb_std_parts ) );

$gb_std_sections = array();
for ( $i = 0; $i < count( $gb_std_parts ); $i += 2 ) {
    $gb_std_sections[] = array(
        'title' => isset( $gb_std_parts[ $i ] ) ? wp_strip_all_tags( $gb_std_parts[ $i ] ) : '',
        'body'  => isset( $gb_std_parts[ $i + 1 ] ) ? trim( (string) $gb_std_parts[ $i + 1 ] ) : '',
    );
}

// No excerpt: promote the opening paragraph into the hero rather than leaving
// the hero bare.
if ( $gb_std_lead === '' && $gb_std_intro !== '' ) {
    $gb_std_lead  = trim( wp_strip_all_tags( $gb_std_intro ) );
    $gb_std_intro = '';
}

/*
 * An excerpt is usually a rewrite of the opening paragraph, which is fine
 * everywhere it is normally used and absurd here: the hero and the band
 * directly beneath it end up saying the same sentence twice in a row. When
 * they are effectively the same thing, only the hero keeps it.
 */
if ( $gb_std_lead !== '' && $gb_std_intro !== '' ) {
    $gb_std_norm = static function ( $text ) {
        $text = strtolower( wp_strip_all_tags( (string) $text ) );
        $text = preg_replace( '/[^a-z0-9 ]+/', '', $text );

        return trim( preg_replace( '/\s+/', ' ', (string) $text ) );
    };

    $gb_a = $gb_std_norm( $gb_std_lead );
    $gb_b = $gb_std_norm( $gb_std_intro );

    if ( $gb_a !== '' && $gb_b !== '' ) {
        $gb_same = strpos( $gb_b, $gb_a ) !== false || strpos( $gb_a, $gb_b ) !== false;

        if ( ! $gb_same ) {
            similar_text( $gb_a, $gb_b, $gb_pct );
            $gb_same = $gb_pct >= 80;
        }

        if ( $gb_same ) {
            $gb_std_intro = '';
        }
    }
}

$gb_std_listings = get_page_by_path( 'cleaner-listings' );
$gb_std_join     = get_page_by_path( 'join' );
?>

<style>
    #gb-standard { width:100vw; position:relative; left:50%; right:50%; margin-left:-50vw; margin-right:-50vw; overflow-x:hidden; color:#334155; }
    #gb-standard .gb-std-container { max-width:1000px; margin:0 auto; padding:0 20px; }

    #gb-standard .gb-std-hero { background:linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color:#fff; padding:86px 0; text-align:center; }
    #gb-standard .gb-std-hero h1 { color:#fff; margin:0 auto 18px; max-width:820px; font-size:clamp(2.1rem, 5vw, 3.2rem); line-height:1.1; font-weight:800; letter-spacing:-1px; }
    #gb-standard .gb-std-lead { margin:0 auto; max-width:760px; font-size:clamp(1.02rem, 2vw, 1.2rem); line-height:1.65; color:rgba(255,255,255,.92); }

    #gb-standard .gb-std-intro { background:#e8f5e9; padding:34px 0; }
    #gb-standard .gb-std-intro p { margin:0 auto; max-width:820px; text-align:center; font-size:1.05rem; line-height:1.7; color:#1b5e20; }
    #gb-standard .gb-std-intro p:last-child { margin-bottom:0; }

    #gb-standard .gb-std-section { padding:66px 0; }
    #gb-standard .gb-std-section:nth-child(even) { background:#f8fafc; }
    /* Centred over the card beneath it. Left-aligned headings above a centred
       card left the section looking off-axis. The body copy stays left: a
       centred paragraph is harder to read down. */
    #gb-standard .gb-std-section h2 { margin:0 auto 24px; max-width:820px; text-align:center; color:#1b5e20; font-size:clamp(1.5rem, 3vw, 2rem); line-height:1.2; font-weight:800; letter-spacing:-.5px; }
    #gb-standard .gb-std-section h2::after { content:""; display:block; width:54px; height:3px; border-radius:2px; background:#2e7d32; margin:16px auto 0; }

    #gb-standard .gb-std-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:34px; box-shadow:0 6px 20px rgba(15,23,42,.04); }
    #gb-standard .gb-std-section:nth-child(even) .gb-std-card { background:#fff; }
    #gb-standard .gb-std-card > *:first-child { margin-top:0; }
    #gb-standard .gb-std-card > *:last-child { margin-bottom:0; }
    #gb-standard .gb-std-card p { margin:0 0 16px; line-height:1.75; font-size:1rem; }
    #gb-standard .gb-std-card h3 { color:#166534; font-size:1.15rem; margin:26px 0 10px; }
    #gb-standard .gb-std-card a { color:#2e7d32; font-weight:700; }

    /* Lists read as the checks they describe rather than as bullets. */
    #gb-standard .gb-std-card ul { list-style:none; margin:0 0 18px; padding:0; }
    #gb-standard .gb-std-card ul li { position:relative; padding:0 0 0 30px; margin-bottom:12px; line-height:1.7; }
    #gb-standard .gb-std-card ul li::before { content:"✓"; position:absolute; left:0; top:0; color:#2e7d32; font-weight:800; }
    #gb-standard .gb-std-card ol { margin:0 0 18px; padding-left:22px; }
    #gb-standard .gb-std-card ol li { margin-bottom:10px; line-height:1.7; }
    #gb-standard .gb-std-card strong { color:#0f172a; }

    #gb-standard .gb-std-cta { padding:70px 20px; text-align:center; }
    #gb-standard .gb-std-cta-inner { background:#1b5e20; color:#fff; border-radius:24px; padding:64px 40px; max-width:1000px; margin:0 auto; box-shadow:0 20px 40px -10px rgba(27,94,32,.2); }
    #gb-standard .gb-std-cta h2 { color:#fff; font-size:clamp(1.7rem, 4vw, 2.6rem); margin:0 0 14px; font-weight:800; }
    #gb-standard .gb-std-cta p { color:rgba(255,255,255,.9); margin:0 0 28px; font-size:1.05rem; }
    #gb-standard .gb-std-btn { display:inline-block; padding:15px 34px; border-radius:50px; font-weight:700; text-decoration:none; margin:6px; }
    #gb-standard .gb-std-btn-white { background:#fff; color:#1b5e20 !important; }
    #gb-standard .gb-std-btn-ghost { border:2px solid rgba(255,255,255,.7); color:#fff !important; }

    @media (max-width:640px) {
        #gb-standard .gb-std-hero { padding:56px 0; }
        #gb-standard .gb-std-section { padding:44px 0; }
        #gb-standard .gb-std-card { padding:24px 20px; }
        #gb-standard .gb-std-cta-inner { padding:44px 22px; }
    }
</style>

<main id="gb-standard">

    <section class="gb-std-hero">
        <div class="gb-std-container">
            <h1><?php the_title(); ?></h1>
            <?php if ( $gb_std_lead !== '' ) : ?>
                <p class="gb-std-lead"><?php echo esc_html( $gb_std_lead ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ( $gb_std_intro !== '' ) : ?>
        <section class="gb-std-intro">
            <div class="gb-std-container"><?php echo wp_kses_post( $gb_std_intro ); ?></div>
        </section>
    <?php endif; ?>

    <?php if ( empty( $gb_std_sections ) ) : ?>
        <?php // No headings to split on - show the copy as one card rather than nothing. ?>
        <section class="gb-std-section">
            <div class="gb-std-container">
                <div class="gb-std-card"><?php echo wp_kses_post( $gb_std_html ); ?></div>
            </div>
        </section>
    <?php else : ?>
        <?php foreach ( $gb_std_sections as $gb_std_section ) : ?>
            <section class="gb-std-section">
                <div class="gb-std-container">
                    <h2><?php echo esc_html( $gb_std_section['title'] ); ?></h2>
                    <div class="gb-std-card"><?php echo wp_kses_post( $gb_std_section['body'] ); ?></div>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <section class="gb-std-cta">
        <div class="gb-std-cta-inner">
            <h2>Find a cleaner who meets it</h2>
            <p>Every cleaner listed has been through all of the above.</p>
            <?php if ( $gb_std_listings ) : ?>
                <a class="gb-std-btn gb-std-btn-white" href="<?php echo esc_url( get_permalink( $gb_std_listings ) ); ?>">Find a Cleaner</a>
            <?php endif; ?>
            <?php if ( $gb_std_join ) : ?>
                <a class="gb-std-btn gb-std-btn-ghost" href="<?php echo esc_url( get_permalink( $gb_std_join ) ); ?>">Become a Cleaner</a>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php get_footer(); ?>
