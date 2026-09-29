<?php
/**
 * Page wording helper.
 *
 * Contact, Contact Support, Book Now and the booking confirmation each read
 * their visible strings from page meta with a fallback. That closure was
 * written into all four templates; this is the one copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * A stored value for this page, or the supplied default when it is blank.
 *
 * $page_slug is only needed where the template is not rendering that page as
 * the main query - the booking confirmation, for instance, which reads the
 * book-now record.
 */
function gb_page_text( $key, $default, $page_slug = '' ) {
    $post_id = 0;

    if ( $page_slug !== '' ) {
        $page = get_page_by_path( $page_slug );
        $post_id = $page instanceof WP_Post ? $page->ID : 0;
    } elseif ( in_the_loop() || get_the_ID() ) {
        $post_id = (int) get_the_ID();
    }

    if ( ! $post_id ) {
        return $default;
    }

    $value = trim( (string) get_post_meta( $post_id, $key, true ) );

    return $value === '' ? $default : $value;
}

/**
 * Bring stored page wording in line with the client's copy corrections.
 *
 * The templates read their wording from post meta and fall back to a default
 * when the meta is empty. These pages have all been seeded, so the meta is
 * not empty - changing the defaults alone left the live site unchanged.
 *
 * Each replacement is conditional on the stored value still being the exact
 * string it is replacing. Anything typed by hand since is left alone: this
 * corrects what nobody has touched, and never overwrites somebody's edit.
 */
function gb_apply_client_copy_corrections() {
    if ( get_option( 'gb_copy_corrections_v1_applied' ) ) {
        return;
    }

    $front_id = (int) get_option( 'page_on_front' );
    if ( ! $front_id ) {
        $front = get_page_by_path( 'home' );
        $front_id = $front ? (int) $front->ID : 0;
    }

    $corrections = array(
        $front_id => array(
            '_gb_hero_btn_2' => array( 'Join as a Cleaner', 'Become a Cleaner' ),
            '_gb_testi_head' => array( 'What our customers say', 'Meet some of our cleaners' ),
        ),
        'how-we-do-it' => array(
            '_gb_about_btn2_text' => array( 'Join as a Cleaner', 'Become a Cleaner' ),
        ),
        'how-it-works' => array(
            '_gb_hiw_s2_title' => array( 'Book Instantly', 'Ask the cleaner a question first' ),
            '_gb_hiw_s2_desc'  => array(
                'Choose a time slot that works for you. No back-and-forth texts. Our secure calendar system locks in your appointment.',
                'Message the cleaner through the platform to check they cover your area, agree what you need doing, and settle a price before anything is booked.',
            ),
        ),
        'contact' => array(
            '_gb_contact_title' => array( 'Contact Support', 'Contact' ),
        ),
    );

    foreach ( $corrections as $page => $fields ) {
        if ( is_int( $page ) ) {
            $page_id = $page;
        } else {
            $found   = get_page_by_path( $page );
            $page_id = $found ? (int) $found->ID : 0;
        }
        if ( ! $page_id ) {
            continue;
        }

        foreach ( $fields as $key => $pair ) {
            list( $was, $now ) = $pair;
            if ( (string) get_post_meta( $page_id, $key, true ) === $was ) {
                update_post_meta( $page_id, $key, $now );
            }
        }
    }

    /*
     * The two invented customer reviews are gone from the homepage and from
     * the dashboard, so their stored values have no reader left. Clear them
     * rather than leave "Sarah J., Manchester" sitting in the database for
     * some future field to pick back up.
     */
    if ( $front_id ) {
        foreach ( array( '_gb_testi1_text', '_gb_testi1_auth', '_gb_testi2_text', '_gb_testi2_auth' ) as $stale ) {
            delete_post_meta( $front_id, $stale );
        }
    }

    update_option( 'gb_copy_corrections_v1_applied', 1, false );
}

/**
 * CleanCo Ltd was listed in Manchester. They are a Liverpool business.
 *
 * Conditional on the stored value still being the wrong one, so this cannot
 * overwrite a correction somebody makes in the dashboard first. Both location
 * keys are written, because the listings card reads one and the search matches
 * on either.
 */
function gb_correct_cleanco_location() {
    if ( get_option( 'gb_cleanco_location_fixed' ) ) {
        return;
    }

    $cleaner = get_page_by_path( 'cleanco-ltd', OBJECT, 'gb_cleaner' );

    if ( $cleaner ) {
        $was = 'Manchester, UK';
        $now = 'City Centre, Liverpool, L1';

        foreach ( array( 'cleaner_location', 'location', '_gb_location' ) as $key ) {
            if ( (string) get_post_meta( $cleaner->ID, $key, true ) === $was ) {
                update_post_meta( $cleaner->ID, $key, $now );
            }
        }
    }

    update_option( 'gb_cleanco_location_fixed', 1, false );
}

/**
 * The Become a Cleaner page ended with "It is free to create your profile",
 * directly under a panel that now states it is free for three months and
 * GBP 14.95 a month after that. The two cannot both stand.
 *
 * Conditional on the old line still being in place, so an edit made in the
 * dashboard first is left alone.
 */
function gb_align_join_cta_with_pricing() {
    if ( get_option( 'gb_join_cta_pricing_aligned' ) ) {
        return;
    }

    $page = get_page_by_path( 'join' );

    if ( $page ) {
        $was = 'Join hundreds of cleaners already earning more on their own terms. It is free to create your profile.';
        $now = 'Set up your profile in minutes. Nothing to pay for your first three months.';

        if ( (string) get_post_meta( $page->ID, '_gb_join_cta_sub', true ) === $was ) {
            update_post_meta( $page->ID, '_gb_join_cta_sub', $now );
        }
    }

    update_option( 'gb_join_cta_pricing_aligned', 1, false );
}

/**
 * Set CleanCo Ltd's location for real.
 *
 * The first attempt at this looked for a stored value of "Manchester, UK" and
 * found nothing, because there was none: the listings template was printing a
 * hardcoded "Manchester, UK" for any cleaner with no location at all. The
 * template no longer does that, and this writes the location they should have
 * had, to every key the site reads.
 *
 * Only fills a blank. A location entered in the dashboard in the meantime is
 * the one that should win.
 */
function gb_set_cleanco_location() {
    if ( get_option( 'gb_cleanco_location_set' ) ) {
        return;
    }

    $cleaner = get_page_by_path( 'cleanco-ltd', OBJECT, 'gb_cleaner' );

    if ( $cleaner ) {
        $location = 'City Centre, Liverpool, L1';

        foreach ( array( 'location', 'cleaner_location', '_gb_location' ) as $key ) {
            $current = trim( (string) get_post_meta( $cleaner->ID, $key, true ) );

            if ( $current === '' || $current === 'Manchester, UK' ) {
                update_post_meta( $cleaner->ID, $key, $location );
            }
        }
    }

    update_option( 'gb_cleanco_location_set', 1, false );
}

/**
 * Reconcile the location keys that had drifted apart.
 *
 * A cleaner's location lives under three meta keys. The manager dashboard
 * wrote all three; the cleaner's own Edit Profile screen wrote only two, so
 * _gb_location kept whatever a manager had typed however long ago. The search
 * matches on every key, so a cleaner who had since moved was still turning up
 * under their old town.
 *
 * Edit Profile now writes all three. This clears the drift already stored.
 * The cleaner's own value wins: the two keys their screen writes are the ones
 * that were updated most recently, by definition of how the drift happened.
 */
function gb_reconcile_cleaner_locations() {
    if ( get_option( 'gb_cleaner_locations_reconciled' ) ) {
        return;
    }

    $cleaners = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'post_status'    => 'any',
        'posts_per_page' => 500,
        'fields'         => 'ids',
    ) );

    $keys = array( 'cleaner_location', 'location', '_gb_location' );

    foreach ( $cleaners as $cleaner_id ) {
        $authoritative = '';

        foreach ( $keys as $key ) {
            $value = trim( (string) get_post_meta( $cleaner_id, $key, true ) );
            if ( $value !== '' ) {
                $authoritative = $value;
                break;
            }
        }

        if ( $authoritative === '' ) {
            continue;
        }

        foreach ( $keys as $key ) {
            if ( trim( (string) get_post_meta( $cleaner_id, $key, true ) ) !== $authoritative ) {
                update_post_meta( $cleaner_id, $key, $authoritative );
            }
        }
    }

    update_option( 'gb_cleaner_locations_reconciled', 1, false );
}

/**
 * "Plant-based, non-toxic products as standard" became "supplied" on the
 * cleaner profiles; the client asked for the same change everywhere. Find a
 * Cleaner carried it twice, in the hero selling points and in a "why book
 * through us" card.
 *
 * Both are template defaults unless somebody has edited them, so this is a
 * no-op where they have not been stored. Conditional on the exact old string,
 * as ever.
 */
function gb_apply_products_supplied_wording() {
    if ( get_option( 'gb_products_supplied_wording' ) ) {
        return;
    }

    $page = get_page_by_path( 'cleaner-listings' );

    if ( $page ) {
        $replacements = array(
            '_gb_listings_usps' => array(
                "DBS-checked, ID-verified and fully insured\nPlant-based, non-toxic products as standard\nIndependent local cleaners, paid fairly",
                "DBS-checked, ID-verified and fully insured\nPlant-based, non-toxic products supplied\nIndependent local cleaners, paid fairly",
            ),
            '_gb_listings_why_2_text' => array(
                'Plant-based, non-toxic products as standard. Safer for your home, your children and your pets.',
                'Plant-based, non-toxic products supplied. Safer for your home, your children and your pets.',
            ),
        );

        foreach ( $replacements as $key => $pair ) {
            list( $was, $now ) = $pair;

            if ( (string) get_post_meta( $page->ID, $key, true ) === $was ) {
                update_post_meta( $page->ID, $key, $now );
            }
        }
    }

    update_option( 'gb_products_supplied_wording', 1, false );
}

/**
 * Clear the Become a Cleaner testimonial's stored values.
 *
 * The section was removed at the client's request and the dashboard fields
 * that fed it have gone too, so "Emma R., Cleaner in Bristol" was sitting in
 * the database with nothing left to read it. Same treatment the homepage's
 * two invented reviews got.
 */
function gb_clear_join_testimonial_meta() {
    if ( get_option( 'gb_join_testimonial_cleared' ) ) {
        return;
    }

    $page = get_page_by_path( 'join' );

    if ( $page ) {
        delete_post_meta( $page->ID, '_gb_join_testi_text' );
        delete_post_meta( $page->ID, '_gb_join_testi_auth' );
    }

    update_option( 'gb_join_testimonial_cleared', 1, false );
}

/**
 * Clear the homepage's secondary CTA button.
 *
 * The banner reads "Ready to make money cleaning?" - it is there to recruit
 * cleaners - and the stored second button said "Learn More" pointing at How
 * It Works, which is mostly about booking a clean. The banner has one job and
 * a second button to the wrong audience only dilutes it.
 *
 * The field still works: type into it in Manager OS and the button appears.
 * This only empties it, and the default is gone too so a later save cannot
 * quietly put it back.
 */
function gb_clear_home_secondary_cta() {
    if ( get_option( 'gb_home_secondary_cta_cleared' ) ) {
        return;
    }

    $front_id = (int) get_option( 'page_on_front' );

    if ( $front_id ) {
        if ( (string) get_post_meta( $front_id, '_gb_cta_btn_2', true ) === 'Learn More' ) {
            delete_post_meta( $front_id, '_gb_cta_btn_2' );
            delete_post_meta( $front_id, '_gb_cta_link_2' );
        }
    }

    update_option( 'gb_home_secondary_cta_cleared', 1, false );
}

/**
 * Replace the old no-results wording.
 *
 * "No professionals found. Try adjusting your search criteria." was a dead
 * end, and while the directory is small it is the most common thing a visitor
 * sees. The page now offers a way out; this brings the stored heading in line
 * with the new default, and only when nobody has rewritten it.
 */
function gb_update_listings_empty_state() {
    if ( get_option( 'gb_listings_empty_state_v2' ) ) {
        return;
    }

    $page = get_page_by_path( 'cleaner-listings' );

    if ( $page ) {
        $was = 'No professionals found. Try adjusting your search criteria.';

        if ( (string) get_post_meta( $page->ID, '_gb_listings_none', true ) === $was ) {
            update_post_meta( $page->ID, '_gb_listings_none', 'No cleaners match that search yet.' );
        }
    }

    update_option( 'gb_listings_empty_state_v2', 1, false );
}

/**
 * Put the homepage's three feature cards in the order the client asked for:
 * Fully Verified on the left, Direct Chat in the middle, Eco-Friendly on the
 * right. And "Vetted" becomes "Verified".
 *
 * The values rotate rather than the template, so "Feature 1" in the dashboard
 * still means the left-hand card. Rotating whatever is stored - rather than
 * only the seeded wording - means an edited description travels with its own
 * card instead of being stranded under a different heading.
 */
function gb_reorder_home_feature_cards() {
    if ( get_option( 'gb_home_features_reordered' ) ) {
        return;
    }

    $home_id = (int) get_option( 'page_on_front' );

    if ( ! $home_id ) {
        update_option( 'gb_home_features_reordered', 1, false );

        return;
    }

    $cards = array();

    for ( $i = 1; $i <= 3; $i++ ) {
        $cards[ $i ] = array(
            'title' => (string) get_post_meta( $home_id, "_gb_feat{$i}_title", true ),
            'text'  => (string) get_post_meta( $home_id, "_gb_feat{$i}_text", true ),
        );
    }

    // Nothing stored means the template defaults are already correct.
    $stored = array_filter( array_column( $cards, 'title' ) );

    if ( empty( $stored ) ) {
        update_option( 'gb_home_features_reordered', 1, false );

        return;
    }

    // Eco was first, vetting second, chat third. Vetting moves to the front,
    // chat to the middle, eco to the end.
    $order = array( 1 => $cards[2], 2 => $cards[3], 3 => $cards[1] );

    foreach ( $order as $slot => $card ) {
        $title = $card['title'] === 'Fully Vetted' ? 'Fully Verified' : $card['title'];

        update_post_meta( $home_id, "_gb_feat{$slot}_title", $title );
        update_post_meta( $home_id, "_gb_feat{$slot}_text", $card['text'] );
    }

    update_option( 'gb_home_features_reordered', 1, false );
}

/**
 * Two wording changes the client asked for on the same pass.
 *
 * "Cashless payment, no cash on the doorstep" said the same thing twice, so
 * the first half goes. And "supplied" comes off the non-toxic products line -
 * which it had only just gained, so both the current and the previous wording
 * are matched here rather than assuming which one is in the database.
 *
 * Conditional on the exact stored string throughout: a line the client has
 * since rewritten is theirs, and is left alone.
 */
function gb_trim_panel_and_listing_wording() {
    if ( get_option( 'gb_panel_listing_wording_v3' ) ) {
        return;
    }

    $settings_id = function_exists( 'gb_get_site_settings_id' )
        ? (int) gb_get_site_settings_id()
        : (int) get_option( 'page_on_front' );

    if ( $settings_id ) {
        gb_replace_meta_line( $settings_id, '_gb_price_cust_benefits', array(
            'Cashless payment, no cash on the doorstep' => 'No cash on the doorstep',
        ) );
    }

    $page = get_page_by_path( 'cleaner-listings' );

    if ( $page ) {
        gb_replace_meta_line( $page->ID, '_gb_listings_usps', array(
            'Plant-based, non-toxic products supplied'    => 'Plant-based, non-toxic products',
            'Plant-based, non-toxic products as standard' => 'Plant-based, non-toxic products',
        ) );

        $was = array(
            'Plant-based, non-toxic products supplied. Safer for your home, your children and your pets.',
            'Plant-based, non-toxic products as standard. Safer for your home, your children and your pets.',
        );

        if ( in_array( (string) get_post_meta( $page->ID, '_gb_listings_why_2_text', true ), $was, true ) ) {
            update_post_meta(
                $page->ID,
                '_gb_listings_why_2_text',
                'Plant-based, non-toxic products. Safer for your home, your children and your pets.'
            );
        }
    }

    update_option( 'gb_panel_listing_wording_v3', 1, false );
}

/**
 * Replace whole lines inside a multi-line meta field.
 *
 * The first version of this compared the entire stored blob against the exact
 * seeded string and silently did nothing, because a value saved through a
 * dashboard textarea comes back with CRLF line endings and the comparison was
 * against LF. Working a line at a time fixes that and is better anyway: an
 * unrelated bullet the client has since reworded no longer stops the one line
 * we were asked to change.
 *
 * Still conditional - a line is only rewritten where it still reads exactly as
 * it did - and the field's own line endings are preserved.
 */
function gb_replace_meta_line( $post_id, $meta_key, array $replacements ) {
    $stored = (string) get_post_meta( (int) $post_id, $meta_key, true );

    if ( $stored === '' ) {
        return false;
    }

    $eol   = strpos( $stored, "\r\n" ) !== false ? "\r\n" : "\n";
    $lines = preg_split( '/\r\n|\r|\n/', $stored );
    $hit   = false;

    foreach ( $lines as $i => $line ) {
        $trimmed = trim( $line );

        if ( isset( $replacements[ $trimmed ] ) ) {
            $lines[ $i ] = $replacements[ $trimmed ];
            $hit         = true;
        }
    }

    if ( ! $hit ) {
        return false;
    }

    update_post_meta( (int) $post_id, $meta_key, implode( $eol, $lines ) );

    return true;
}

/**
 * The client's structural changes: the slug in the address bar, the fourth
 * service, the How it Works page, and the Green Broom Standard's home.
 *
 * All four touch real posts rather than wording, so each step is conditional
 * on finding what it expects and none of them destroys anything: the retired
 * page goes to the trash, where it can be restored, and the Standard keeps the
 * post it already had rather than being recreated as a copy.
 */
function gb_apply_structure_changes_2026_09() {
    if ( get_option( 'gb_structure_changes_2026_09' ) ) {
        return;
    }

    /*
     * 1. "What Cleaners Do" was still /what-we-do in the address bar.
     *
     * The slug was left alone last time to protect existing links. Redirecting
     * the old one protects them just as well, so the title and the URL can
     * finally agree. The template is pinned explicitly first: page-what-we-do
     * .php is matched by slug, so renaming without pinning would drop the page
     * onto the generic template.
     */
    $services = get_page_by_path( 'what-we-do' );

    if ( $services instanceof WP_Post ) {
        update_post_meta( $services->ID, '_wp_page_template', 'page-what-we-do.php' );
        wp_update_post( array( 'ID' => $services->ID, 'post_name' => 'what-cleaners-do' ) );
    }

    /*
     * 2. The fourth service card. The template no longer prints it, but stored
     *    meta would keep it alive if the card were ever restored, so it goes.
     */
    $services = get_page_by_path( 'what-cleaners-do' );

    if ( $services instanceof WP_Post ) {
        foreach ( array( 'title', 'desc', 'list', 'btn', 'link' ) as $field ) {
            delete_post_meta( $services->ID, '_gb_svc_4_' . $field );
        }
    }

    /*
     * 3. How it Works is retired. Trashed rather than deleted: the client can
     *    ask for it back, and a redirect keeps the old URL working either way.
     */
    $hiw = get_page_by_path( 'how-it-works' );

    if ( $hiw instanceof WP_Post && $hiw->post_status !== 'trash' ) {
        wp_trash_post( $hiw->ID );
    }

    /*
     * 4. The Green Broom Standard becomes a page of its own instead of an item
     *    inside Eco Products. Converting the existing post keeps whatever the
     *    client has edited into it; only the type and the slug change.
     */
    $standard = get_posts( array(
        'post_type'      => 'gb_eco_product',
        'post_status'    => 'any',
        'title'          => 'The Green Broom Standard',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $standard ) ) {
        wp_update_post( array(
            'ID'          => (int) $standard[0],
            'post_type'   => 'page',
            'post_name'   => 'the-green-broom-standard',
            'post_status' => 'publish',
        ) );
    }

    /*
     * 5. Anything still pointing at the retired page.
     */
    $link_fields = array(
        'cleaner-listings' => '_gb_listings_link_2',
        'join'             => '_gb_join_hero_link2',
    );

    foreach ( $link_fields as $slug => $meta_key ) {
        $page = get_page_by_path( $slug );

        if ( $page instanceof WP_Post && (string) get_post_meta( $page->ID, $meta_key, true ) === '/how-it-works' ) {
            update_post_meta( $page->ID, $meta_key, '/what-cleaners-do' );
        }
    }

    $join = get_page_by_path( 'join' );

    if ( $join instanceof WP_Post && (string) get_post_meta( $join->ID, '_gb_join_hero_btn2', true ) === 'See How It Works' ) {
        update_post_meta( $join->ID, '_gb_join_hero_btn2', 'See What Cleaners Do' );
    }

    /*
     * 6. "Phone support:" before the number.
     */
    foreach ( array( 'contact' => '_gb_contact_phone_label', 'contact-support' => '_gb_csup_phone_label' ) as $slug => $meta_key ) {
        $page = get_page_by_path( $slug );

        if ( $page instanceof WP_Post && (string) get_post_meta( $page->ID, $meta_key, true ) === 'Phone support:' ) {
            update_post_meta( $page->ID, $meta_key, 'Phone:' );
        }
    }

    flush_rewrite_rules( false );

    update_option( 'gb_structure_changes_2026_09', 1, false );
}

/**
 * Keep the URLs that moved working.
 *
 * Three of them, all 301 because all three are permanent. Registered on
 * template_redirect so they run before WordPress decides it has a 404, and
 * only for an exact path match so a deeper URL is left alone.
 */
function gb_redirect_retired_paths() {
    if ( is_admin() ) {
        return;
    }

    $path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

    $moved = array(
        'what-we-do'                       => '/what-cleaners-do/',
        'how-it-works'                     => '/what-cleaners-do/',
        'eco-guide/the-green-broom-standard' => '/the-green-broom-standard/',
    );

    if ( isset( $moved[ $path ] ) ) {
        wp_safe_redirect( home_url( $moved[ $path ] ), 301 );
        exit;
    }
}
add_action( 'template_redirect', 'gb_redirect_retired_paths', 1 );

/**
 * The client changed their mind on this bullet, which is their prerogative.
 *
 * "Cashless payment, no cash on the doorstep" first lost its opening half.
 * They then asked for the opposite: keep the cashless half, drop the
 * doorstep half. So the line ends up as "Cashless payment".
 *
 * Both starting points are handled, because which one is in the database
 * depends on whether the previous change reached a given install: the line as
 * it now reads here, and the original it was cut down from.
 */
function gb_restore_cashless_payment_bullet() {
    if ( get_option( 'gb_cashless_bullet_restored' ) ) {
        return;
    }

    $settings_id = function_exists( 'gb_get_site_settings_id' )
        ? (int) gb_get_site_settings_id()
        : (int) get_option( 'page_on_front' );

    if ( $settings_id ) {
        gb_replace_meta_line( $settings_id, '_gb_price_cust_benefits', array(
            'No cash on the doorstep'                  => 'Cashless payment',
            'Cashless payment, no cash on the doorstep' => 'Cashless payment',
        ) );
    }

    update_option( 'gb_cashless_bullet_restored', 1, false );
}

/**
 * The page Stripe returns a paying customer or cleaner to.
 *
 * Its template is matched by slug, so the slug is fixed and the template is
 * pinned explicitly as well - a page that loses its template here would send
 * somebody who has just paid to a blank generic page, which is the one moment
 * that must not go wrong.
 *
 * Kept out of the menus on purpose: it is a destination, not a page anybody
 * should browse to.
 */
function gb_ensure_membership_success_page() {
    if ( get_option( 'gb_membership_success_page' ) ) {
        return;
    }

    $existing = get_page_by_path( 'membership-success' );

    if ( $existing instanceof WP_Post ) {
        update_post_meta( $existing->ID, '_wp_page_template', 'page-membership-success.php' );

        if ( $existing->post_status !== 'publish' ) {
            wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
        }

        update_option( 'gb_membership_success_page', 1, false );

        return;
    }

    $page_id = wp_insert_post( array(
        'post_type'    => 'page',
        'post_title'   => 'Membership Success',
        'post_name'    => 'membership-success',
        'post_status'  => 'publish',
        'post_content' => '',
    ), true );

    if ( ! is_wp_error( $page_id ) && $page_id ) {
        update_post_meta( $page_id, '_wp_page_template', 'page-membership-success.php' );
        flush_rewrite_rules( false );
    }

    update_option( 'gb_membership_success_page', 1, false );
}

/**
 * Keep the success page out of search results.
 *
 * It only makes sense to the person who has just paid, and a stranger landing
 * on it from Google would be told they are a member when they are not.
 */
function gb_noindex_membership_success( $robots ) {
    if ( is_page( 'membership-success' ) ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
        unset( $robots['index'], $robots['follow'] );
    }

    return $robots;
}
add_filter( 'wp_robots', 'gb_noindex_membership_success' );

/**
 * The Green Broom provides the insurance, so stop asking cleaners for it.
 *
 * The platform already had a setting for this - "Insurance Provided by The
 * Green Broom" - it had simply never been chosen, so the profile badge read
 * "Proof of insurance provided" and a cleaner was told to send theirs in.
 * Setting it flips the badge to "Covered by The Green Broom's insurance
 * policy", which is both the truth and a better thing for a customer to read.
 *
 * Only set where nobody has chosen anything. A manager who has deliberately
 * picked "Mandatory" or "Optional" keeps their choice.
 */
function gb_set_insurance_provided_by_platform() {
    if ( get_option( 'gb_insurance_provided_2026_09' ) ) {
        return;
    }

    $settings_id = function_exists( 'gb_get_site_settings_id' )
        ? (int) gb_get_site_settings_id()
        : (int) get_option( 'page_on_front' );

    if ( $settings_id ) {
        $current = (string) get_post_meta( $settings_id, '_gb_require_insurance', true );

        if ( $current === '' || $current === 'yes' ) {
            update_post_meta( $settings_id, '_gb_require_insurance', 'provided' );
        }
    }

    /*
     * The pricing panel's button, at the client's request, while payments are
     * not yet public. Conditional on the wording it shipped with, so an edit
     * of their own is not overwritten.
     */
    if ( $settings_id && (string) get_post_meta( $settings_id, '_gb_price_cust_btn', true ) === 'Create your account' ) {
        update_post_meta( $settings_id, '_gb_price_cust_btn', 'Create an account now' );
    }

    /*
     * The Green Broom Standard page says cleaners evidence their own cover,
     * which is the opposite of how it works. The client is rewriting this page
     * himself, so this only touches copy that is still word for word the
     * placeholder that was seeded - if he has started, his version stands.
     */
    $standard = get_page_by_path( 'the-green-broom-standard' );

    if ( $standard instanceof WP_Post ) {
        $replacements = array(
            '<li><strong>Insured</strong> &mdash; public liability cover in place and evidenced.</li>'
                => '<li><strong>Insured</strong> &mdash; covered by The Green Broom&rsquo;s own insurance policy, which we provide.</li>',
            'until all four of these are complete and on file:'
                => 'until these are complete and on file:',
            'has been referenced and is insured.'
                => 'has been referenced, and is covered by our insurance.',
        );

        $content = (string) $standard->post_content;
        $updated = $content;

        foreach ( $replacements as $was => $now ) {
            if ( strpos( $updated, $was ) !== false ) {
                $updated = str_replace( $was, $now, $updated );
            }
        }

        if ( $updated !== $content ) {
            wp_update_post( array( 'ID' => $standard->ID, 'post_content' => $updated ) );
        }
    }

    update_option( 'gb_insurance_provided_2026_09', 1, false );
}

/**
 * Finish the insurance line on The Green Broom Standard.
 *
 * The previous pass matched the whole list item, entity and all - and the
 * stored copy has a real em dash, because WordPress decoded &mdash; on the way
 * into the database. Two of the three replacements landed and this one did
 * not, which is what a live check found.
 *
 * Anchored on the part of the sentence that cannot be transformed on the way
 * in: no entities, no punctuation that gets texturised. That is the general
 * lesson - match the words, not the mark-up around them.
 */
function gb_finish_standard_insurance_line() {
    if ( get_option( 'gb_standard_insurance_line_v2' ) ) {
        return;
    }

    $standard = get_page_by_path( 'the-green-broom-standard' );

    if ( $standard instanceof WP_Post ) {
        $was = 'public liability cover in place and evidenced';
        $now = 'covered by The Green Broom&rsquo;s own insurance policy, which we provide';

        if ( strpos( (string) $standard->post_content, $was ) !== false ) {
            wp_update_post( array(
                'ID'           => $standard->ID,
                'post_content' => str_replace( $was, $now, (string) $standard->post_content ),
            ) );
        }
    }

    update_option( 'gb_standard_insurance_line_v2', 1, false );
}
