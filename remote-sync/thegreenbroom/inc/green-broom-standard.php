<?php
/**
 * "The Green Broom Standard" - the platform's statement of what it requires.
 *
 * IMPORTANT: unlike the two Eco Product guides, this copy is NOT the client's
 * own. It was drafted here because the page was asked for before the wording
 * arrived, and it is a first draft for the client to replace or amend. It is
 * flagged as such in the round-up sent to them.
 *
 * Every claim in it is deliberately limited to something the platform actually
 * does and can evidence:
 *
 *   - the four checks are the ones the Verified badge is gated on, named
 *     exactly as the badge names them;
 *   - the payment and document handling describe what the code does;
 *   - there are no volume claims, no customer numbers and no ratings.
 *
 * The closing section says plainly what the Standard is not. A public promise
 * a marketplace cannot keep is worse than no promise, and being straight about
 * where the platform's responsibility ends protects the client.
 *
 * Only used to seed the page once. After that the copy lives in the database
 * and is edited from Manager OS > Eco Products like any other guide.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_green_broom_standard_seed_content() {
    $body = <<<'HTML'
<p>The Green Broom Standard is what every cleaner on this platform agrees to before their profile goes live, and what you can expect from every clean booked through us.</p>

<h2>Every cleaner is checked before they appear</h2>

<p>A profile does not go live, and the Verified badge does not appear on it, until these are complete and on file:</p>

<ul>
<li><strong>Identity verified</strong> &mdash; photographic ID, checked against the name on the profile.</li>
<li><strong>Basic DBS certificate verified</strong> &mdash; a current Basic Disclosure, seen and recorded by us.</li>
<li><strong>Two references verified</strong> &mdash; contacted directly, not simply collected.</li>
<li><strong>Insured</strong> &mdash; covered by The Green Broom&rsquo;s own insurance policy, which we provide.</li>
</ul>

<p>We also confirm an address and a contact number, and every cleaner signs our Terms of Association. If a check lapses, the badge comes off. A cleaner who has not completed all four is not listed.</p>

<h2>The products</h2>

<p>Cleaners on The Green Broom work with plant-based, non-toxic products rather than the harsh chemicals in most conventional cleaners. That means:</p>

<ul>
<li>No bleach or ammonia as a default cleaning product.</li>
<li>Formulas that are safe to use around children and pets once dry.</li>
<li>Refillable bottles and reusable cloths wherever a refill system exists, instead of a new plastic spray bottle every time.</li>
</ul>

<p>Our Eco Products guides set out exactly what we recommend and why, including how to make effective cleaning products at home from four everyday ingredients. If you have a surface that needs something specific, or an allergy in the household, tell your cleaner before the first visit.</p>

<h2>How you pay</h2>

<p>Payment goes through the platform, by card. No cash on the doorstep, no chasing an invoice, and a receipt for every booking. You agree the work and the price with the cleaner before anything is booked.</p>

<h2>How cleaners are treated</h2>

<p>The people doing the work are independent local cleaners, not agency staff on a margin. They set their own rates, choose which jobs to accept, and keep the bulk of what you pay. We take no commission on the work itself.</p>

<p>This is deliberate. Cleaning done by someone who is paid properly and chose the job is better cleaning, and a platform that squeezes its cleaners ends up with the cleaners nobody else wanted.</p>

<h2>Your documents and your data</h2>

<p>Identity documents and DBS certificates are held securely, off the public web, and are never attached to a public profile. Every time one is received, viewed or destroyed it is written to an audit trail. Documents are deleted on a published schedule once they are no longer needed.</p>

<p>Our <a href="/privacy/">Privacy Policy</a> sets out exactly what is kept, for how long, and how to ask for a copy or its removal.</p>

<h2>What the Standard is not</h2>

<p>We think it is worth being straight about where this ends.</p>

<p>The Green Broom checks cleaners, handles payment and keeps a record. We are not the cleaner's employer and we do not supervise an individual clean. The cleaner is self-employed and responsible for the work they do.</p>

<p>Our checks tell you that somebody is who they say they are, has a current Basic DBS, has been referenced, and is covered by our insurance. They are not a guarantee about how a particular clean will go. If one goes wrong, tell us &mdash; we would rather know.</p>
HTML;

    return array(
        'title' => 'The Green Broom Standard',
        'desc'  => 'What every cleaner on the platform agrees to before their profile goes live, and what you can expect from every clean booked through us.',
        'body'  => $body,
    );
}

/**
 * Create the page, once.
 *
 * Skipped entirely if a post with this title already exists, so the client can
 * rewrite every word of it and a later deploy will not put the draft back.
 */
function gb_ensure_green_broom_standard_page() {
    if ( get_option( 'gb_green_broom_standard_created' ) ) {
        return;
    }

    $content  = gb_green_broom_standard_seed_content();
    // Not get_page_by_title(): deprecated in WordPress 6.2.
    $existing = get_posts( array(
        'post_type'      => 'gb_eco_product',
        'post_status'    => 'any',
        'title'          => $content['title'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( empty( $existing ) ) {
        wp_insert_post( array(
            'post_type'    => 'gb_eco_product',
            'post_title'   => $content['title'],
            'post_content' => wp_kses_post( $content['body'] ),
            'post_excerpt' => $content['desc'],
            'post_status'  => 'publish',
        ) );

        flush_rewrite_rules( false );
    }

    update_option( 'gb_green_broom_standard_created', 1, false );
}

/**
 * Split the Standard's copy into its sections, on the writer's own <h2>s.
 *
 * Shared by the template and by the Manager OS editor, so the image field a
 * manager fills in is labelled with the heading it will actually sit beside.
 * If the two split differently, the fourth image would appear against the
 * third section and nobody would be able to work out why.
 *
 * @return array{0: string, 1: array<int, array{title: string, body: string}>}
 *         The copy before the first heading, then the sections.
 */
function gb_standard_split_sections( $html ) {
    $html  = (string) $html;
    $parts = preg_split( '/<h2[^>]*>(.*?)<\/h2>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
    $intro = trim( (string) array_shift( $parts ) );

    $sections = array();

    for ( $i = 0; $i < count( $parts ); $i += 2 ) {
        $sections[] = array(
            'title' => isset( $parts[ $i ] ) ? trim( wp_strip_all_tags( $parts[ $i ] ) ) : '',
            'body'  => isset( $parts[ $i + 1 ] ) ? trim( (string) $parts[ $i + 1 ] ) : '',
        );
    }

    return array( $intro, $sections );
}

/**
 * Pull the first list out of a section's body.
 *
 * A section that is mostly a list reads far better as a grid of small cards
 * than as one long column of ticks, but only the list should move - the
 * paragraphs around it stay where the writer put them.
 *
 * The copy on either side is kept apart, not glued together. The first draft
 * concatenated it, which silently moved the closing line of a section above
 * the list it was written to follow - "We also confirm a contact number" ended
 * up introducing the checks rather than rounding them off.
 *
 * @return array{0: string, 1: array<int, string>, 2: string} What came before
 *         the list, its items as raw HTML, and what came after.
 */
function gb_standard_extract_list( $body ) {
    $body = (string) $body;

    if ( ! preg_match( '/<ul[^>]*>(.*?)<\/ul>/is', $body, $match, PREG_OFFSET_CAPTURE ) ) {
        return array( $body, array(), '' );
    }

    if ( ! preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $match[1][0], $items ) ) {
        return array( $body, array(), '' );
    }

    $start = (int) $match[0][1];
    $end   = $start + strlen( $match[0][0] );

    return array(
        trim( substr( $body, 0, $start ) ),
        array_map( 'trim', $items[1] ),
        trim( substr( $body, $end ) )
    );
}

/**
 * Is this eco product's public URL actually served by a page instead?
 *
 * The Standard exists twice: as a gb_eco_product, which is what puts a card on
 * the Eco Products screen, and as the page the menu points at. Its
 * eco-product permalinks 301 to that page, so the page is what the public
 * reads - but the eco product is still fully editable under Manager OS >
 * Eco Products, where rewriting it changes nothing anybody will ever see.
 *
 * Matching on the slug rather than naming the Standard specifically, because
 * the trap is the shape, not the page: any eco product that gets superseded
 * this way later has the same problem, and would otherwise sit there waiting
 * to waste somebody's afternoon.
 *
 * @return WP_Post|null The page that supersedes it, if there is one.
 */
function gb_eco_product_superseded_by_page( $post ) {
    if ( ! $post instanceof WP_Post || $post->post_type !== 'gb_eco_product' ) {
        return null;
    }

    if ( $post->post_name === '' ) {
        return null;
    }

    $page = get_page_by_path( $post->post_name );

    if ( $page instanceof WP_Post && $page->post_status === 'publish' ) {
        return $page;
    }

    return null;
}
