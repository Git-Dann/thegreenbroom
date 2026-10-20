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

// Search by postcode metadata, not by cleaner name.
$search_term = isset($_GET['k']) ? strtoupper(trim(preg_replace('/\s+/', ' ', sanitize_text_field(wp_unslash($_GET['k']))))) : '';
$search_error = '';
if ( $search_term !== '' && ! preg_match('/^[A-Z0-9 ]{2,10}$/', $search_term) ) {
    $search_error = 'Please enter a valid postcode using letters and numbers.';
    $search_term = '';
}

$meta_filters = [];
if ( $search_term !== '' ) {
    /*
     * Search the postcode DISTRICT as well as whatever was typed.
     *
     * The box asks for a postcode, but cleaners record a service area -
     * "Mossley Hill, Liverpool, L18". Matching only the whole string meant a
     * customer entering their actual postcode, "L18 3HQ", matched nothing:
     * that exact text appears in no cleaner's location and never would, so
     * somebody in the cleaner's own street was told nobody served them.
     *
     * A UK postcode splits into an outward code identifying the district
     * (L18) and an inward code identifying the street (3HQ). The district is
     * what corresponds to a service area, so it is searched too.
     */
    $gb_terms = [ $search_term ];

    if ( strpos( $search_term, ' ' ) !== false ) {
        $gb_outward = trim( substr( $search_term, 0, strpos( $search_term, ' ' ) ) );
        if ( $gb_outward !== '' ) {
            $gb_terms[] = $gb_outward;
        }
    } elseif ( preg_match( '/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $search_term ) ) {
        // Typed without a space: the inward code is always the last three.
        $gb_terms[] = substr( $search_term, 0, -3 );
    }

    $gb_terms = array_values( array_unique( array_filter( $gb_terms ) ) );

    /*
     * A postcode district has to match on a boundary, not as a substring.
     * Plain LIKE made a search for L1 return the cleaner in L18, because the
     * one string contains the other - and the same would hold for M1 against
     * M11, B1 against B15, and every other single-digit district.
     *
     * REGEXP with word boundaries on anything that looks like a district;
     * everything else stays LIKE, so a free-text search for "Liverpool" still
     * matches "Mossley Hill, Liverpool, L18".
     *
     * \\b, not the [[:<:]] of MySQL 5.7: this host runs MariaDB 10.11, whose
     * REGEXP is PCRE and does not understand the older syntax at all.
     */
    $gb_is_district = static function ( $term ) {
        return (bool) preg_match( '/^[A-Z]{1,2}[0-9][A-Z0-9]?$/', $term );
    };

    $gb_location_group = [ 'relation' => 'OR' ];
    foreach ( $gb_terms as $gb_term ) {
        $gb_compare = $gb_is_district( $gb_term ) ? 'REGEXP' : 'LIKE';
        $gb_value   = $gb_compare === 'REGEXP'
            ? '\\b' . preg_quote( $gb_term, '/' ) . '\\b'
            : $gb_term;

        foreach ( [ 'gb_postcode', '_gb_location', 'location', 'cleaner_location' ] as $gb_key ) {
            $gb_location_group[] = [ 'key' => $gb_key, 'value' => $gb_value, 'compare' => $gb_compare ];
        }
    }

    $meta_filters[] = $gb_location_group;
}

$service_filter = isset($_GET['service']) ? sanitize_key(wp_unslash($_GET['service'])) : '';

/*
 * The filter reads the real Services catalogue rather than a list kept here.
 *
 * The old hard-coded map doubled as the query value, not just the label, so a
 * service renamed in Manager OS silently stopped matching, and the dropdown
 * could offer services that no longer existed while missing new ones. It also
 * already disagreed with itself: the option read "Move In/Out" while the
 * filter searched for "End of Tenancy".
 *
 * Matching now uses each service's own title, so the two can never drift.
 */
$gb_service_catalogue = get_posts([
    'post_type'   => 'gb_service',
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby'     => 'title',
    'order'       => 'ASC',
]);

// Older links and ad campaigns point at ?service=standard|deep|moving, so keep
// those working by mapping them onto whatever the service is called now.
$gb_service_aliases = [
    'standard' => ['standard-clean', 'regular-clean', 'standard', 'regular', 'weekly-cleaning', 'fortnightly-cleaning'],
    'regular'  => ['regular-clean', 'standard-clean', 'weekly-cleaning', 'fortnightly-cleaning'],
    'deep'     => ['deep-clean', 'deep'],
    'moving'   => ['house-move', 'end-of-tenancy', 'move-in-out', 'moving-clean', 'moving'],
];

$gb_active_service = null;
$gb_service_unmatched = false;
if ( $service_filter !== '' ) {
    foreach ( $gb_service_catalogue as $gb_svc ) {
        if ( $gb_svc->post_name === $service_filter ) {
            $gb_active_service = $gb_svc;
            break;
        }
    }
    if ( ! $gb_active_service && isset( $gb_service_aliases[ $service_filter ] ) ) {
        foreach ( $gb_service_catalogue as $gb_svc ) {
            if ( in_array( $gb_svc->post_name, $gb_service_aliases[ $service_filter ], true ) ) {
                $gb_active_service = $gb_svc;
                break;
            }
        }
    }
    /*
     * A service we cannot resolve used to fall through to an unfiltered list,
     * so a stale campaign link showed every cleaner as though they all offered
     * it. Say so instead, and show the full list on purpose rather than by
     * accident.
     */
    $gb_service_unmatched = ! $gb_active_service;
}

if ( $gb_active_service ) {
    /*
     * gb_services is saved as an array, so it lives in the database as a
     * serialised string. Matching the title wrapped in quotes pins it to a
     * whole element: a bare LIKE '%Deep Clean%' would also match a service
     * called "Deep Cleaning", quietly returning cleaners who do not offer it.
     */
    $meta_filters[] = [
        'key'     => 'gb_services',
        'value'   => '"' . $gb_active_service->post_title . '"',
        'compare' => 'LIKE',
    ];
}
/*
 * Always wrap in a clause list. Assigning a single flat clause directly -
 * ['key' => .., 'value' => .., 'compare' => ..] - is silently discarded:
 * WP_Meta_Query::sanitize_query() skips entries that are not arrays, so every
 * key of a flat clause is thrown away and no filtering happens at all. That
 * is why the service filter never narrowed the results, while the postcode
 * search worked - its ['relation' => 'OR', [..], [..]] entries are arrays.
 */
if ( count($meta_filters) === 1 ) {
    $args['meta_query'] = [ $meta_filters[0] ];
} elseif ( count($meta_filters) > 1 ) {
    $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_filters);
}

$cleaners = new WP_Query($args);

$listings_title = get_post_meta(get_the_ID(), '_gb_listings_title', true) ?: 'Find Your Professional';
$listings_sub = get_post_meta(get_the_ID(), '_gb_listings_sub', true) ?: 'Find trusted local cleaners by entering your postcode.';
/*
 * The no-results state is the most common outcome of the site's main action
 * while the directory is still small, and it used to be a dead end: a line of
 * grey text and nowhere to go. It now offers the way out - clear the filters
 * and see everyone - plus a line for people whose area is not covered yet.
 *
 * "Professionals" was the old wording; the rest of the site says cleaners.
 */
$listings_none = get_post_meta(get_the_ID(), '_gb_listings_none', true) ?: 'No cleaners match that search yet.';
$listings_none_sub = get_post_meta(get_the_ID(), '_gb_listings_none_sub', true) ?: 'We are adding cleaners across Merseyside every week. Try a nearby postcode, or see everyone on the platform.';
// "Leave blank for no button", says the editor, so a saved empty value is kept
// empty. The default is only for a page that has never saved this field.
$listings_none_btn = metadata_exists('post', get_the_ID(), '_gb_listings_none_btn')
    ? (string) get_post_meta(get_the_ID(), '_gb_listings_none_btn', true)
    : 'See all cleaners';

/*
 * Paid-social traffic lands here, so the page opens with a proper hero lockup
 * like the home page rather than dropping straight into a search box. Every
 * string below is editable from Content > Cleaner Listings; the defaults only
 * apply until a manager sets their own.
 */
$listings_hero_bg = trim((string) get_post_meta(get_the_ID(), '_gb_listings_hero_bg', true));
$listings_trust   = trim((string) get_post_meta(get_the_ID(), '_gb_trust_banner', true));
$listings_eyebrow = trim((string) get_post_meta(get_the_ID(), '_gb_listings_eyebrow', true));
$listings_search_head = get_post_meta(get_the_ID(), '_gb_listings_search_head', true) ?: 'Search cleaners near you';

// Search and card labels. Previously fixed in the template, which meant the
// two highest-value buttons on the page could not be reworded without a deploy.
$listings_ph_postcode  = get_post_meta(get_the_ID(), '_gb_listings_ph_postcode', true) ?: 'Search by postcode...';
$listings_any_service  = get_post_meta(get_the_ID(), '_gb_listings_any_service', true) ?: 'Any Service';
$listings_search_btn   = get_post_meta(get_the_ID(), '_gb_listings_search_btn', true) ?: 'Search';
$listings_results_line = get_post_meta(get_the_ID(), '_gb_listings_results', true) ?: 'Found {count} results for "{term}"';
$listings_btn_profile  = get_post_meta(get_the_ID(), '_gb_listings_btn_profile', true) ?: 'View Profile';
$listings_btn_book     = get_post_meta(get_the_ID(), '_gb_listings_btn_book', true) ?: 'Book Now';
$listings_badge        = get_post_meta(get_the_ID(), '_gb_listings_badge', true) ?: 'Verified';

// Selling points and call to action for the hero. Paid traffic arrives cold,
// so the header has to make the case before asking anyone to search.
$listings_usps_raw = trim((string) get_post_meta(get_the_ID(), '_gb_listings_usps', true));
if ($listings_usps_raw === '') {
    $listings_usps_raw = "DBS-checked, ID-verified and fully insured\nPlant-based, non-toxic products\nIndependent local cleaners, paid fairly";
}
$listings_usps = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", $listings_usps_raw))), 'strlen'));

// No hero button by default: the search sits directly below and is already in
// view, so a button that only scrolls to it is noise. Still available if set.
$listings_btn_1  = trim((string) get_post_meta(get_the_ID(), '_gb_listings_btn_1', true));
$listings_link_1 = get_post_meta(get_the_ID(), '_gb_listings_link_1', true) ?: '#gb-dir-search-head';
$listings_btn_2  = trim((string) get_post_meta(get_the_ID(), '_gb_listings_btn_2', true));
$listings_link_2 = get_post_meta(get_the_ID(), '_gb_listings_link_2', true) ?: '/what-cleaners-do';

/*
 * Sales sections below the results. Paid traffic that does not search straight
 * away still needs the case made, so the page carries the pitch itself rather
 * than relying on the visitor going off to read other pages. Every string is
 * editable; a blank heading hides its whole section.
 *
 * Pass $blank_hides for a field the editor says can be cleared to hide
 * something. Its default then only stands in when the field has never been
 * saved; a saved empty value stays empty. Before, an empty heading came back
 * as the default, so "Clear the heading to hide" never hid anything. Fields
 * without that promise still fall back to the default when empty.
 *
 * The step and card fields only count as "cleared on purpose" once the page
 * has been saved with them shown filled in (_gb_listings_cards_set). Earlier
 * saves stored them empty because the editor showed them empty, and those
 * still mean "use the standard wording".
 */
$gb_l = function ($key, $default = '', $blank_hides = false) {
    if ($blank_hides && metadata_exists('post', get_the_ID(), $key)) {
        return trim((string) get_post_meta(get_the_ID(), $key, true));
    }
    $value = trim((string) get_post_meta(get_the_ID(), $key, true));
    return $value === '' ? $default : $value;
};
$gb_cards_set = get_post_meta(get_the_ID(), '_gb_listings_cards_set', true) === '1';

$hiw_head = $gb_l('_gb_listings_hiw_head', 'Booking a cleaner takes two minutes', true);
$hiw_steps = array();
$hiw_defaults = array(
    array('Search your postcode', 'See the verified cleaners who cover your street, with their hourly rate shown up front.'),
    array('Choose your cleaner', 'Read their profile, their checks and their reviews, then pick the day and time that suits you.'),
    array('Sit back', 'They arrive with plant-based products. You pay securely online — no cash, no contracts, no awkwardness.'),
);
foreach ($hiw_defaults as $i => $d) {
    $n = $i + 1;
    $hiw_steps[] = array(
        'title' => $gb_l("_gb_listings_hiw_{$n}_title", $d[0], $gb_cards_set),
        'text'  => $gb_l("_gb_listings_hiw_{$n}_text", $d[1], $gb_cards_set),
    );
}

$why_head = $gb_l('_gb_listings_why_head', 'Why book through The Green Broom', true);
$why_cards = array();
$why_defaults = array(
    array('Properly vetted', 'Every cleaner is ID-checked, DBS-checked and insured before they can accept a single booking.'),
    array('Genuinely eco', 'Plant-based, non-toxic products. Safer for your home, your children and your pets.'),
    array('Fair to cleaners', 'Independent local cleaners set their own rates and keep the bulk of what you pay. Better paid people do better work.'),
    array('No contracts', 'Book a one-off clean or a regular slot. Change it or cancel it whenever you need to.'),
);
foreach ($why_defaults as $i => $d) {
    $n = $i + 1;
    $why_cards[] = array(
        'title' => $gb_l("_gb_listings_why_{$n}_title", $d[0], $gb_cards_set),
        'text'  => $gb_l("_gb_listings_why_{$n}_text", $d[1], $gb_cards_set),
    );
}

/*
 * Social proof from real reviews only. gb_get_recent_reviews() returns
 * genuine approved comments carrying a star rating; if there are none the
 * whole section is skipped. Nothing here is ever invented - a fabricated
 * testimonial on a cleaning marketplace is both a lie and a legal risk.
 */
$reviews_head    = $gb_l('_gb_listings_reviews_head', 'What customers say', true);
$reviews         = function_exists('gb_get_recent_reviews') ? gb_get_recent_reviews(3, 4) : array();
$platform_rating = function_exists('gb_get_platform_rating') ? gb_get_platform_rating() : array('average' => 0, 'count' => 0);

$lcta_title = $gb_l('_gb_listings_cta_title', 'Ready for a cleaner home?', true);
$lcta_sub   = $gb_l('_gb_listings_cta_sub', 'Find a verified, eco-friendly cleaner covering your postcode today.', true);
$lcta_btn   = $gb_l('_gb_listings_cta_btn', 'Search cleaners near you', true);
$lcta_link  = $gb_l('_gb_listings_cta_link', '#gb-dir-search-head');

$verified_link  = get_post_meta(get_the_ID(), '_gb_listings_verified_link', true) ?: 'What does Verified mean?';
$verified_title = get_post_meta(get_the_ID(), '_gb_listings_verified_title', true) ?: '🍃 Green Broom Verified';
$verified_card_raw = get_post_meta(get_the_ID(), '_gb_listings_verified_card', true);
$verified_card  = ($verified_card_raw === '' || $verified_card_raw === null) ? 1 : (int) $verified_card_raw;
$verified_body  = trim((string) get_post_meta(get_the_ID(), '_gb_listings_verified_body', true));
if ($verified_body === '') {
    // Client-supplied wording. Deliberately verbatim: it names exactly the
    // three checks they stand behind, and carries the qualifier that these
    // checks do not guarantee suitability. Do not "improve" this copy.
    $verified_body = "A local cleaner. A cleaner you can trust.\n"
        . "\n"
        . "We believe finding someone to clean your home should be straightforward. That's why The Green Broom brings together local, self-employed cleaners and gives homeowners the information they need to choose confidently.\n"
        . "\n"
        . "A cleaner receives the Green Broom Verified badge when the following checks have been completed:\n"
        . "Government issued I/D uploaded during application, checked and verified\n"
        . "Basic DBS Certificate uploaded during application, checked and verified; DBS status is periodically reviewed.\n"
        . "Two Character Reference contact details uploaded during application. We contact referees and written replies remain on file for the duration of the applicant's membership.\n"
        . "\n"
        . "Green Broom Verified means the cleaner has completed the verification checks shown above. These checks are designed to provide additional confidence but do not guarantee a cleaner's suitability.";
}
?>

<style>
    /* PAGE LAYOUT & HERO */
    /* Landing-page lockup: matches the home page hero so paid-social traffic
       gets a proper header rather than an immediate search box. */
    .gb-dir-hero {
        background: #2e7d32; /* Brand Green, and the fallback when no image is set */
        padding: 86px 20px 78px;
        text-align: center;
        color: white;
        position: relative;
        isolation: isolate;
        overflow: hidden;
    }
    .gb-dir-hero-media, .gb-dir-hero-overlay { position: absolute; inset: 0; }
    .gb-dir-hero-media { z-index: 0; }
    .gb-dir-hero-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gb-dir-hero-overlay { z-index: 1; background: rgba(0,0,0,0.58); }
    .gb-dir-hero-content { position: relative; z-index: 2; max-width: 820px; margin: 0 auto; }
    .gb-dir-eyebrow { display:inline-block; margin-bottom:18px; padding:7px 15px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.3); color:#fff; font-size:12px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }

    .gb-dir-usps { list-style:none; margin:32px auto 0; padding:0; display:flex; flex-wrap:wrap; justify-content:center; gap:14px 30px; max-width:900px; }
    .gb-dir-usps li { position:relative; padding-left:30px; color:rgba(255,255,255,.96); font-size:1.02rem; font-weight:600; text-align:left; }
    .gb-dir-usps li::before { content:""; position:absolute; left:4px; top:7px; width:7px; height:13px; border:solid #86efac; border-width:0 2.5px 2.5px 0; transform:rotate(45deg); }
    .gb-dir-cta { display:flex; flex-wrap:wrap; justify-content:center; gap:14px; margin-top:38px; }
    .gb-dir-btn { display:inline-block; padding:15px 30px; border-radius:9px; font-weight:800; text-decoration:none; font-size:1rem; transition:transform .15s ease; }
    .gb-dir-btn:hover { transform:translateY(-2px); }
    .gb-dir-btn-primary { background:#fff; color:#1b5e20 !important; box-shadow:0 10px 24px rgba(0,0,0,.18); }
    .gb-dir-btn-outline { border:2px solid rgba(255,255,255,.75); color:#fff !important; }
    .gb-dir-btn-outline:hover { background:rgba(255,255,255,.12); }

    @media (max-width:760px) {
        .gb-dir-hero { padding:72px 20px; }
        .gb-dir-usps { flex-direction:column; align-items:flex-start; gap:12px; margin-top:26px; }
        .gb-dir-cta { flex-direction:column; }
        .gb-dir-btn { width:100%; text-align:center; box-sizing:border-box; }
        .gb-dir-search { padding:38px 20px 6px; }
    }

    .gb-dir-trust { background:#e8f5e9; padding:18px 20px; text-align:center; border-bottom:1px solid #c8e6c9; }
    .gb-dir-trust span { font-weight:700; color:#1b5e20; font-size:.9rem; text-transform:uppercase; letter-spacing:1px; }

    .gb-dir-search { max-width:1000px; margin:0 auto; padding:54px 20px 52px; text-align:center; }
    .gb-dir-search-head { margin:0 0 22px; color:#1b5e20; font-size:1.6rem; font-weight:800; }

    .gb-verified-cue { margin:14px 0 0; }

    /* SALES SECTIONS */
    .gb-sell { padding:74px 20px; }
    .gb-sell-inner { max-width:1120px; margin:0 auto; }
    .gb-sell-head { text-align:center; margin:0 0 46px; color:#1b5e20; font-size:clamp(1.7rem, 3.2vw, 2.3rem); font-weight:800; letter-spacing:-.5px; }
    .gb-sell-steps { background:#fff; }
    .gb-steps { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:34px; counter-reset:none; }
    .gb-step { text-align:center; padding:0 10px; }
    .gb-step-num { display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; margin-bottom:18px; border-radius:50%; background:#1b5e20; color:#fff; font-size:1.25rem; font-weight:800; }
    .gb-step h3 { margin:0 0 10px; color:#0f172a; font-size:1.18rem; font-weight:800; }
    .gb-step p { margin:0; color:#64748b; line-height:1.65; font-size:.98rem; }

    .gb-sell-reviews { background:#fff; }
    .gb-reviews-summary { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:9px; margin:-28px 0 40px; color:#475569; font-size:1rem; }
    .gb-reviews-summary strong { color:#0f172a; font-size:1.15rem; }
    .gb-reviews-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:22px; }
    .gb-review { display:flex; flex-direction:column; margin:0; background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:26px; }
    .gb-review-stars { margin-bottom:12px; }
    .gb-review blockquote { margin:0 0 18px; padding:0; border:0; color:#334155; font-size:.98rem; line-height:1.65; }
    .gb-review blockquote::before { content:"\201C"; }
    .gb-review blockquote::after { content:"\201D"; }
    .gb-review figcaption { margin-top:auto; font-size:.86rem; }
    .gb-review-author { display:block; font-weight:800; color:#1b5e20; }
    .gb-review-meta { display:block; margin-top:2px; color:#64748b; }
    .gb-review-meta a { color:#64748b; text-decoration:underline; text-underline-offset:2px; }
    .gb-review-meta a:hover { color:#2e7d32; }

    .gb-sell-why { background:#f0f7f1; }
    .gb-why-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:22px; }
    .gb-why-card { background:#fff; border:1px solid #dcefdf; border-radius:14px; padding:28px 24px; box-shadow:0 8px 22px rgba(15,23,42,.04); }
    .gb-why-tick { display:block; position:relative; width:42px; height:42px; margin-bottom:16px; border-radius:11px; background:#e8f5e9; }
    .gb-why-tick::before { content:""; position:absolute; left:15px; top:12px; width:8px; height:15px; border:solid #2e7d32; border-width:0 3px 3px 0; transform:rotate(45deg); }
    .gb-why-card h3 { margin:0 0 9px; color:#1b5e20; font-size:1.08rem; font-weight:800; }
    .gb-why-card p { margin:0; color:#64748b; line-height:1.6; font-size:.94rem; }

    .gb-sell-cta { background:#fff; padding-bottom:90px; }
    .gb-cta-box { background:#1b5e20; color:#fff; border-radius:22px; padding:64px 36px; text-align:center; box-shadow:0 22px 44px -12px rgba(27,94,32,.35); }
    .gb-cta-box h2 { margin:0 0 14px; color:#fff; font-size:clamp(1.7rem, 3.2vw, 2.3rem); font-weight:800; }
    .gb-cta-box p { margin:0 auto 32px; max-width:560px; color:rgba(255,255,255,.92); line-height:1.65; }

    @media (max-width:760px) {
        .gb-sell { padding:52px 18px; }
        .gb-sell-head { margin-bottom:34px; }
        .gb-steps { gap:30px; }
        .gb-cta-box { padding:44px 22px; border-radius:16px; }
    }
    .gb-verified-trigger { background:none; border:0; padding:0; color:#2e7d32; font-size:13.5px; font-weight:700; text-decoration:underline; text-underline-offset:3px; cursor:pointer; font-family:inherit; text-align:left; }
    .gb-verified-trigger:hover { color:#1b5e20; }
    .gb-verified-trigger:focus-visible { outline:2px solid #2e7d32; outline-offset:3px; border-radius:3px; }

    .gb-verified-modal[hidden] { display:none; }
    .gb-verified-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:20px; }
    .gb-verified-backdrop { position:absolute; inset:0; background:rgba(15,23,42,.6); }
    .gb-verified-panel { position:relative; z-index:1; width:100%; max-width:540px; max-height:85vh; overflow-y:auto; background:#fff; border-radius:14px; padding:32px; box-shadow:0 24px 60px rgba(15,23,42,.3); text-align:left; }
    .gb-verified-panel h2 { margin:0 0 16px; color:#1b5e20; font-size:1.4rem; padding-right:30px; }
    .gb-verified-panel p { margin:0 0 14px; color:#475569; line-height:1.65; font-size:14.5px; }
    .gb-verified-list { list-style:none; margin:0 0 16px; padding:0; }
    .gb-verified-list li { position:relative; padding:7px 0 7px 30px; color:#334155; font-size:14.5px; line-height:1.5; }
    .gb-verified-list li::before { content:""; position:absolute; left:4px; top:12px; width:6px; height:11px; border:solid #2e7d32; border-width:0 2.5px 2.5px 0; transform:rotate(45deg); }
    .gb-verified-close { position:absolute; top:12px; right:14px; width:34px; height:34px; border:0; border-radius:8px; background:#f1f5f9; color:#475569; font-size:22px; line-height:1; cursor:pointer; }
    .gb-verified-close:hover { background:#e2e8f0; color:#0f172a; }
    @media (max-width:600px) { .gb-verified-panel { padding:26px 20px; } }
    
    /* FIX: Force title to be white to override global h1 styles */
    .gb-dir-title { 
        font-size: 42px; 
        font-weight: 800; 
        margin: 0; 
        letter-spacing: -1px; 
        color: #ffffff !important; 
    }
    
    .gb-dir-sub { color: #ffffff; margin-top: 15px; font-size: 18px; margin-bottom: 40px; }
    .gb-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    
    /* SEARCH BAR */
    .gb-search-wrapper { max-width: 800px; margin: 0 auto; background: white; padding: 10px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); display: flex; gap: 10px; flex-wrap: wrap; }
    .gb-search-input { flex: 1; padding: 15px 20px; border: none; outline: none; font-size: 16px; color: #333; min-width: 200px; box-sizing: border-box; }
    /* The 200px floor plus padding overflowed a 320px handset. */
    @media (max-width: 420px) { .gb-search-input { min-width: 0; width: 100%; padding: 14px 16px; } }
    .gb-search-select { flex: 0.5; padding: 15px 20px; border-left: 1px solid #eee; border-top: none; border-right: none; border-bottom: none; outline: none; font-size: 16px; color: #333; background: white; min-width: 150px; }
    
    /* Search Button (Emerald) */
    .gb-search-btn { background: #047857; color: white; border: none; padding: 0 40px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 16px; }
    .gb-search-btn:hover { background: #065f46; }

    /* GRID LAYOUT */
    /* Was margin-top:-60px, to pull the results card up over the green hero
       back when the search sat inside it. With the search now its own section
       that pull dragged the cards over the search box and hid the "What does
       Verified mean?" link underneath. */
    .gb-dir-container { max-width: 1200px; margin: 0 auto 10px; padding: 0 20px; position: relative; z-index: 1; }
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
    .gb-card-header { display: flex; justify-content: space-between; align-items: start; gap: 10px; margin-bottom: 10px; min-width: 0; }
    .gb-card-name { margin: 0; font-size: 20px; font-weight: 800; color: #1d1d1f; }
    .gb-card-rate { color: #2e7d32; font-weight: 700; font-size: 16px; }
    
    .gb-card-meta { font-size: 13px; color: #64748b; margin-bottom: 15px; display: flex; gap: 15px; }
    /* Fixed two-line bio: a long bio used to stretch its card past the others
       in the row. The height is set rather than max-height so a short or empty
       bio still leaves the buttons aligned across every card. */
    .gb-card-excerpt {
        font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 25px;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; height: 3.2em; overflow-wrap: anywhere;
    }
    /* A long business name would otherwise wrap and push the card taller. */
    .gb-card-name { min-width: 0; }
    .gb-card-name a { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gb-card-rate { flex: 0 0 auto; white-space: nowrap; }
    .gb-card-meta span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    
    .gb-card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: auto; }
    .gb-card-actions.is-single { grid-template-columns: 1fr; }
    .gb-card-status { display: flex; align-items: center; gap: 8px; margin: auto 0 12px; font-size: 13px; color: #64748b; }
    .gb-card-status > span { width: 7px; height: 7px; border-radius: 50%; background: #f59e0b; flex: 0 0 auto; }
    .gb-card-status + .gb-card-actions { margin-top: 0; }
    
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

<main id="main-content">
    <section class="gb-dir-hero">
        <?php if ($listings_hero_bg !== ''): ?>
            <div class="gb-dir-hero-media" aria-hidden="true"><img src="<?php echo esc_url($listings_hero_bg); ?>" alt=""></div>
            <div class="gb-dir-hero-overlay" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="gb-dir-hero-content">
            <?php if ($listings_eyebrow !== ''): ?>
                <span class="gb-dir-eyebrow"><?php echo esc_html($listings_eyebrow); ?></span>
            <?php endif; ?>
            <h1 class="gb-dir-title"><?php echo esc_html($listings_title); ?></h1>
            <p class="gb-dir-sub"><?php echo esc_html($listings_sub); ?></p>

            <?php if (!empty($listings_usps)): ?>
                <ul class="gb-dir-usps">
                    <?php foreach ($listings_usps as $listings_usp): ?>
                        <li><?php echo esc_html($listings_usp); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($listings_btn_1 !== '' || $listings_btn_2 !== ''): ?>
                <div class="gb-dir-cta">
                    <?php if ($listings_btn_1 !== ''): ?>
                        <a class="gb-dir-btn gb-dir-btn-primary" href="<?php echo esc_url($listings_link_1); ?>"><?php echo esc_html($listings_btn_1); ?></a>
                    <?php endif; ?>
                    <?php if ($listings_btn_2 !== ''): ?>
                        <a class="gb-dir-btn gb-dir-btn-outline" href="<?php echo esc_url($listings_link_2); ?>"><?php echo esc_html($listings_btn_2); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($listings_trust !== ''): ?>
        <div class="gb-dir-trust"><span><?php echo esc_html($listings_trust); ?></span></div>
    <?php endif; ?>

    <?php
    /*
     * Membership pricing, above the search at the client's request.
     *
     * It sat below the results until now, on the reasoning that browsing is not
     * gated so the offer should come after somebody has found a cleaner they
     * like. The client wants the offer made first; it is their call, and it is
     * one edit to move back.
     *
     * Same panel and same stored figures as the homepage.
     */
    if ( function_exists( 'gb_render_pricing_panel' ) ) {
        gb_render_pricing_panel( 'customer' );
    }

    // Then what makes the subscription worth it, before the search.
    if ( function_exists( 'gb_render_difference_panel' ) ) {
        gb_render_difference_panel( 'white' );
    }
    ?>

    <section class="gb-dir-search" aria-labelledby="gb-dir-search-head">
        <h2 id="gb-dir-search-head" class="gb-dir-search-head"><?php echo esc_html($listings_search_head); ?></h2>
        <form class="gb-search-wrapper" action="<?php echo home_url('/cleaner-listings'); ?>" method="get">
            <label class="gb-sr-only" for="cleaner-search-postcode">Search by postcode</label>
            <input id="cleaner-search-postcode" type="text" name="k" class="gb-search-input" placeholder="<?php echo esc_attr($listings_ph_postcode); ?>" value="<?php echo esc_attr($search_term); ?>" maxlength="10" autocomplete="postal-code" inputmode="text">
            <?php if ( ! empty( $gb_service_catalogue ) ) : ?>
                <label class="gb-sr-only" for="cleaner-search-service">Service type</label>
                <select id="cleaner-search-service" name="service" class="gb-search-select">
                    <option value=""><?php echo esc_html( $listings_any_service ); ?></option>
                    <?php foreach ( $gb_service_catalogue as $gb_svc ) : ?>
                        <option value="<?php echo esc_attr( $gb_svc->post_name ); ?>" <?php selected( $gb_active_service && $gb_active_service->ID === $gb_svc->ID ); ?>>
                            <?php echo esc_html( $gb_svc->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button type="submit" class="gb-search-btn"><?php echo esc_html($listings_search_btn); ?></button>
        </form>
    </section>

    <div class="gb-verified-modal" id="gb-verified-modal" role="dialog" aria-modal="true" aria-labelledby="gb-verified-modal-title" hidden>
        <div class="gb-verified-backdrop" data-gb-close-verified></div>
        <div class="gb-verified-panel" role="document">
            <button type="button" class="gb-verified-close" data-gb-close-verified aria-label="Close">&times;</button>
            <h2 id="gb-verified-modal-title"><?php echo esc_html($verified_title); ?></h2>
            <?php
            // A blank line separates paragraphs; consecutive single lines become
            // a tick list, so a manager can edit this as plain text.
            $gb_v_blocks = preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $verified_body));
            foreach ($gb_v_blocks as $gb_v_block) {
                $gb_v_lines = array_values(array_filter(array_map('trim', explode("\n", $gb_v_block)), 'strlen'));
                if (count($gb_v_lines) > 1) {
                    echo '<ul class="gb-verified-list">';
                    foreach ($gb_v_lines as $gb_i => $gb_v_line) {
                        if ($gb_i === 0 && substr($gb_v_line, -1) === ':') {
                            echo '</ul><p>' . esc_html($gb_v_line) . '</p><ul class="gb-verified-list">';
                            continue;
                        }
                        echo '<li>' . esc_html($gb_v_line) . '</li>';
                    }
                    echo '</ul>';
                } elseif (!empty($gb_v_lines)) {
                    echo '<p>' . esc_html($gb_v_lines[0]) . '</p>';
                }
            }
            ?>
        </div>
    </div>

    <div class="gb-dir-container">
    
    <?php if ( ! empty( $gb_service_unmatched ) ) : ?>
        <p style="margin-bottom: 30px; color: #b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 14px;">
            That service is no longer listed, so we are showing every cleaner. Use the menu above to pick a current service.
        </p>
    <?php endif; ?>

    <?php if ( $search_error ) : ?>
        <p style="margin-bottom: 30px; color: #b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 14px;"><?php echo esc_html($search_error); ?></p>
    <?php elseif ( $search_term !== '' ) : ?>
        <?php
        // {count} and {term} are substituted after escaping, so a manager can
        // reorder or reword the sentence without being able to inject markup.
        $gb_results_text = str_replace(
            array( '{count}', '{term}' ),
            array( '<strong>' . (int) $cleaners->found_posts . '</strong>', esc_html( $search_term ) ),
            esc_html( $listings_results_line )
        );
        ?>
        <p style="margin-bottom: 30px; color: #64748b;"><?php echo $gb_results_text; ?></p>
    <?php endif; ?>

    <div class="gb-cleaner-grid">
        <?php if($cleaners->have_posts()): ?>
            <?php while($cleaners->have_posts()): $cleaners->the_post(); 
                $rate = get_post_meta(get_the_ID(), 'hourly_rate', true) ?: '15';
                /*
                 * This used to fall back to a hardcoded "Manchester, UK", so
                 * every cleaner who had not filled in a location was advertised
                 * as being in Manchester. A blank location now shows nothing,
                 * which is true, rather than a city picked at random.
                 */
                $location = gb_get_cleaner_location(get_the_ID());
                $fully_booked = gb_cleaner_is_fully_booked(get_the_ID());
                // Same rule as the profile: the card badge means all three checks
                // are done, so it must not appear on a partial set.
                $is_verified = function_exists('gb_cleaner_is_fully_verified')
                    ? gb_cleaner_is_fully_verified(get_the_ID())
                    : get_post_meta(get_the_ID(), 'is_verified', true);
            ?>
            <div class="gb-cleaner-card">
                <div class="gb-card-img-wrap">
                    <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf('View profile for %s', get_the_title())); ?>">
                        <?php if(has_post_thumbnail()): ?>
                            <?php /* The uploaded avatars carry no alt text in the media library, and an
                                     empty alt on a link's only content leaves the link unnamed. */ ?>
                            <?php the_post_thumbnail('medium', ['class' => 'gb-card-img', 'alt' => esc_attr(get_the_title()) . ' profile photo']); ?>
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#cbd5e1; font-size:48px;">
                                <span class="dashicons dashicons-id"></span>
                            </div>
                        <?php endif; ?>
                    </a>
                    
                    <?php if($is_verified): ?>
                        <span class="gb-card-verified">
                            <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-icon-logo.png" style="height:14px; width:auto;" alt="" aria-hidden="true">
                            <?php echo esc_html($listings_badge); ?>
                        </span>
                    <?php endif; ?>
                    
                </div>
                
                <div class="gb-card-body">
                    <div class="gb-card-header">
                        <h2 class="gb-card-name"><a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;"><?php the_title(); ?></a></h2>
                        <span class="gb-card-rate">£<?php echo esc_html($rate); ?>/hr</span>
                    </div>
                    
                    <?php if ($location !== '' || $fully_booked): ?>
                    <div class="gb-card-meta">
                        <?php if ($location !== ''): ?><span>📍 <?php echo esc_html($location); ?></span><?php endif; ?>
                        <?php if ($fully_booked): ?><span class="gb-card-booked">Fully booked</span><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="gb-card-excerpt">
                        <?php echo wp_trim_words(get_the_content(), 15, '...'); ?>
                    </div>
                    
                    <?php
                    /*
                     * A cleaner who cannot be booked used to get a faded green
                     * "Not taking bookings" button beside View Profile: it looked
                     * like a broken button and wrapped onto two lines. Now the
                     * profile is the one full-width action, with a quiet status
                     * line above it saying why there is no Book button.
                     */
                    $gb_card_off = ( ! function_exists('gb_can_contact_cleaners') || gb_can_contact_cleaners() )
                        && function_exists('gb_cleaner_can_take_bookings') && ! gb_cleaner_can_take_bookings(get_the_ID());
                    ?>
                    <?php if ( $gb_card_off ) : ?>
                        <p class="gb-card-status"><span aria-hidden="true"></span><?php echo function_exists('gb_bookings_paused') && gb_bookings_paused() ? 'Online booking is paused for now' : 'Not taking online bookings yet'; ?></p>
                    <?php endif; ?>
                    <div class="gb-card-actions<?php echo $gb_card_off ? ' is-single' : ''; ?>">
                        <a href="<?php the_permalink(); ?>" class="<?php echo $gb_card_off ? 'gb-btn-solid' : 'gb-btn-outline'; ?>"><?php echo esc_html($listings_btn_profile); ?></a>
                        <?php if ( function_exists('gb_can_contact_cleaners') && ! gb_can_contact_cleaners() ) : ?>
                            <?php /* Browsing stays open; booking is for members, and the panel is on this page. */ ?>
                            <a href="<?php echo esc_url(gb_membership_join_url()); ?>" class="gb-btn-solid">Join to book</a>
                        <?php elseif ( $gb_card_off ) : ?>
                            <?php /* Said by the status line above; View Profile is the only button. */ ?>
                        <?php else : ?>
                            <a href="<?php echo esc_url(home_url('/book-now/?cleaner=' . get_the_ID())); ?>" class="gb-btn-solid"><?php echo esc_html($listings_btn_book); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 30px; background: white; border-radius: 12px; color: #64748b;">
                <h2 style="margin:0 0 10px; color:#0f172a;"><?php echo esc_html($listings_none); ?></h2>
                <?php if (trim((string) $listings_none_sub) !== ''): ?>
                    <p style="margin:0 auto 24px; max-width:460px; line-height:1.6;"><?php echo esc_html($listings_none_sub); ?></p>
                <?php endif; ?>
                <?php
                // Only worth offering when a filter is actually narrowing things
                // down - with no filters on, "see all cleaners" is this page.
                $gb_filtered = ($search_term !== '' || $service_filter !== '');
                ?>
                <?php if ($gb_filtered && trim((string) $listings_none_btn) !== ''): ?>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="gb-btn gb-btn-primary"><?php echo esc_html($listings_none_btn); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; wp_reset_postdata(); ?>
    </div>

    <div class="gb-pagination">
        <?php echo paginate_links(array('total' => $cleaners->max_num_pages, 'current' => $paged, 'prev_text' => '←', 'next_text' => '→')); ?>
    </div>
    </div>

    <?php
    ?>

    <?php if ($reviews_head !== '' && !empty($reviews)): ?>
    <section class="gb-sell gb-sell-reviews">
        <div class="gb-sell-inner">
            <h2 class="gb-sell-head"><?php echo esc_html($reviews_head); ?></h2>
            <?php if ($platform_rating['count'] > 0): ?>
                <p class="gb-reviews-summary">
                    <?php echo function_exists('gb_render_stars') ? gb_render_stars($platform_rating['average'], 18) : ''; ?>
                    <strong><?php echo esc_html(number_format((float) $platform_rating['average'], 1)); ?></strong>
                    from <?php echo (int) $platform_rating['count']; ?> customer <?php echo $platform_rating['count'] === 1 ? 'review' : 'reviews'; ?>
                </p>
            <?php endif; ?>
            <div class="gb-reviews-grid">
                <?php foreach ($reviews as $review): ?>
                    <figure class="gb-review">
                        <div class="gb-review-stars"><?php echo function_exists('gb_render_stars') ? gb_render_stars($review['stars'], 15) : ''; ?></div>
                        <blockquote><?php echo esc_html(wp_trim_words($review['body'], 34)); ?></blockquote>
                        <figcaption>
                            <span class="gb-review-author"><?php echo esc_html($review['author']); ?></span>
                            <span class="gb-review-meta">on <a href="<?php echo esc_url($review['link']); ?>"><?php echo esc_html($review['cleaner']); ?></a></span>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($hiw_head !== ''): ?>
    <section class="gb-sell gb-sell-steps">
        <div class="gb-sell-inner">
            <h2 class="gb-sell-head"><?php echo esc_html($hiw_head); ?></h2>
            <ol class="gb-steps">
                <?php foreach ($hiw_steps as $i => $step): if ($step['title'] === '') { continue; } ?>
                    <li class="gb-step">
                        <span class="gb-step-num" aria-hidden="true"><?php echo (int) ($i + 1); ?></span>
                        <h3><?php echo esc_html($step['title']); ?></h3>
                        <p><?php echo esc_html($step['text']); ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($why_head !== ''): ?>
    <section class="gb-sell gb-sell-why">
        <div class="gb-sell-inner">
            <h2 class="gb-sell-head"><?php echo esc_html($why_head); ?></h2>
            <div class="gb-why-grid">
                <?php foreach ($why_cards as $card_i => $card): if ($card['title'] === '') { continue; } ?>
                    <article class="gb-why-card">
                        <span class="gb-why-tick" aria-hidden="true"></span>
                        <h3><?php echo esc_html($card['title']); ?></h3>
                        <p><?php echo esc_html($card['text']); ?></p>
                        <?php if ($verified_card === $card_i + 1 && $verified_link !== ''): ?>
                            <p class="gb-verified-cue">
                                <button type="button" class="gb-verified-trigger" aria-haspopup="dialog" data-gb-open-verified><?php echo esc_html($verified_link); ?></button>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($lcta_title !== ''): ?>
    <section class="gb-sell gb-sell-cta">
        <div class="gb-sell-inner">
            <div class="gb-cta-box">
                <h2><?php echo esc_html($lcta_title); ?></h2>
                <?php if ($lcta_sub !== ''): ?><p><?php echo esc_html($lcta_sub); ?></p><?php endif; ?>
                <?php if ($lcta_btn !== ''): ?>
                    <a class="gb-dir-btn gb-dir-btn-primary" href="<?php echo esc_url($lcta_link); ?>"><?php echo esc_html($lcta_btn); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<script>
(function () {
    var modal = document.getElementById('gb-verified-modal');
    if (!modal) { return; }
    var lastFocused = null;

    function openModal(trigger) {
        lastFocused = trigger || document.activeElement;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var close = modal.querySelector('.gb-verified-close');
        if (close) { close.focus(); }
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-gb-open-verified]');
        if (opener) { e.preventDefault(); openModal(opener); return; }
        if (e.target.closest('[data-gb-close-verified]')) { e.preventDefault(); closeModal(); }
    });

    document.addEventListener('keydown', function (e) {
        if (modal.hidden) { return; }
        if (e.key === 'Escape') { closeModal(); return; }
        if (e.key !== 'Tab') { return; }
        // Keep tabbing inside the dialog while it is open.
        var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) { return; }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
})();
</script>

<?php get_footer(); ?>
