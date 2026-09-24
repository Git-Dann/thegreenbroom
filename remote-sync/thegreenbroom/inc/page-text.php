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
