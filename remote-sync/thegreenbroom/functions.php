<?php
/* ==========================================================================
   0. THEME SETUP & ASSETS
   ========================================================================== */

function gb_theme_setup() {
    // Enable Featured Images (Critical for Cleaner Profiles)
    add_theme_support( 'post-thumbnails' );
    
    // Let WP manage the document title
    add_theme_support( 'title-tag' );

    // Register Menus
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'greenbroom' ),
    ) );
}
add_action( 'after_setup_theme', 'gb_theme_setup' );

/**
 * Re-send WordPress auth cookies with an explicit SameSite policy.
 *
 * FastHosts/LiteSpeed + modern browsers have been observed omitting the
 * wordpress_logged_in_* cookie on manager dashboard POSTs unless the policy is
 * explicit. WordPress core does not currently expose SameSite for auth cookies,
 * so mirror the core cookie writes with Secure; SameSite=None on HTTPS.
 */
function gb_set_auth_cookie_with_samesite_none( $name, $value, $expiration, $path, $domain = '', $httponly = true ) {
    if ( headers_sent() || ! is_ssl() || $name === '' ) {
        return false;
    }

    $options = array(
        'expires'  => (int) $expiration,
        'path'     => $path ?: '/',
        'secure'   => true,
        'httponly' => (bool) $httponly,
        'samesite' => 'None',
    );

    if ( ! empty( $domain ) ) {
        $options['domain'] = $domain;
    }

    return setcookie( $name, $value, $options );
}

function gb_force_secure_auth_cookies_on_https( $secure, $user_id ) {
    return is_ssl() ? true : $secure;
}
add_filter( 'secure_auth_cookie', 'gb_force_secure_auth_cookies_on_https', 10, 2 );
add_filter( 'secure_logged_in_cookie', 'gb_force_secure_auth_cookies_on_https', 10, 2 );

function gb_reissue_auth_cookie_with_samesite_none( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
    $cookie_name = ( 'secure_auth' === $scheme ) ? SECURE_AUTH_COOKIE : AUTH_COOKIE;

    gb_set_auth_cookie_with_samesite_none( $cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
    gb_set_auth_cookie_with_samesite_none( $cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN );
}
add_action( 'set_auth_cookie', 'gb_reissue_auth_cookie_with_samesite_none', 10, 6 );

function gb_reissue_logged_in_cookie_with_samesite_none( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
    gb_set_auth_cookie_with_samesite_none( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN );

    if ( COOKIEPATH !== SITECOOKIEPATH ) {
        gb_set_auth_cookie_with_samesite_none( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
    }
}
add_action( 'set_logged_in_cookie', 'gb_reissue_logged_in_cookie_with_samesite_none', 10, 6 );

function gb_prevent_duplicate_core_auth_cookie_headers( $send, $expire, $expiration, $user_id, $scheme, $token ) {
    if ( ! headers_sent() && is_ssl() && (int) $user_id > 0 && (int) $expiration > 0 ) {
        return false;
    }

    return $send;
}
add_filter( 'send_auth_cookies', 'gb_prevent_duplicate_core_auth_cookie_headers', 10, 6 );

function gb_refresh_manager_dashboard_auth_cookie_policy() {
    if ( headers_sent() || ! is_user_logged_in() ) {
        return;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( strpos( $request_uri, 'manager-dashboard' ) === false ) {
        return;
    }

    $user = wp_get_current_user();
    if ( ! in_array( 'gb_manager', (array) $user->roles, true ) && ! current_user_can( 'administrator' ) ) {
        return;
    }

    $token = wp_get_session_token();
    if ( $token !== '' ) {
        wp_set_auth_cookie( get_current_user_id(), true, is_ssl(), $token );
    }
}
add_action( 'template_redirect', 'gb_refresh_manager_dashboard_auth_cookie_policy', -200 );

/**
 * Ensure the cleaner-recruitment "Join" page exists.
 * Creates a published page with slug 'join' if one does not already exist,
 * so it appears in the manager dashboard Pages tab and uses page-join.php.
 * Guarded by an option so the lookup only runs until the page is provisioned.
 */
function gb_ensure_join_page() {
    if ( get_option( 'gb_join_page_created' ) ) {
        return;
    }

    $existing = get_posts( array(
        'post_type'      => 'page',
        'name'           => 'join',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( empty( $existing ) ) {
        wp_insert_post( array(
            'post_title'   => 'Join',
            'post_name'    => 'join',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ) );
    }

    update_option( 'gb_join_page_created', 1 );
}

/**
 * Ensure the public Eco Products page exists so it can be linked from the
 * header menu and managed as a normal WordPress page.
 */
function gb_ensure_eco_products_page() {
    if ( get_option( 'gb_eco_products_page_created' ) ) {
        return;
    }

    $existing = get_posts( array(
        'post_type'      => 'page',
        'name'           => 'eco-products',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( empty( $existing ) ) {
        wp_insert_post( array(
            'post_title'   => 'Eco Products',
            'post_name'    => 'eco-products',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ) );
    }

    update_option( 'gb_eco_products_page_created', 1 );
}

/**
 * Keep the homeowner subscription agreement separate from the cleaner
 * agreement, with its own public page and sign-up acceptance record.
 */
function gb_ensure_subscriber_terms_page() {
    if ( get_option( 'gb_subscriber_terms_page_created' ) ) {
        return;
    }

    $existing = get_posts( array(
        'post_type'      => 'page',
        'name'           => 'subscribers-terms-of-association',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( empty( $existing ) ) {
        $page_id = wp_insert_post( array(
            'post_title'   => "Subscribers' Terms of Association",
            'post_name'    => 'subscribers-terms-of-association',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ) );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', 'page-legal.php' );
        }
    }

    update_option( 'gb_subscriber_terms_page_created', 1 );
}

/**
 * Cookie Policy page.
 *
 * Seeded once as an ordinary page on the Legal template, so the manager can
 * edit every word of it from Content -> Pages afterwards. The wording below is
 * only the starting point, and is deliberately specific: it names the cookies
 * the site actually sets rather than reciting the generic list most policies
 * copy from each other. If the platform later gains analytics or advertising
 * tags, this page needs updating to match.
 */
function gb_ensure_cookie_policy_page() {
    if ( get_option( 'gb_cookie_policy_page_created' ) ) {
        return;
    }

    $existing = get_posts( array(
        'post_type'      => 'page',
        'name'           => 'cookies',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( empty( $existing ) ) {
        $page_id = wp_insert_post( array(
            'post_title'   => 'Cookie Policy',
            'post_name'    => 'cookies',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => gb_get_cookie_policy_seed_content(),
        ) );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', 'page-legal.php' );
        }
    }

    update_option( 'gb_cookie_policy_page_created', 1 );
}

/**
 * Make the legal pages genuinely editable.
 *
 * Privacy, Terms and the Subscribers' Terms rendered from a hardcoded array in
 * page-legal.php that always beat the page's own content, so a manager could
 * edit them, get a success message, and see no change on the live site. This
 * copies the existing copy into each page once; from then on the dashboard is
 * the source of truth.
 *
 * It only ever writes into an EMPTY page, so it cannot overwrite an edit, and
 * it is safe to re-run. The option is set per page rather than once for the
 * batch, so a page that fails this time is retried on the next load instead of
 * being silently skipped forever.
 */
function gb_seed_legal_page_content() {
    if ( ! function_exists( 'gb_get_legal_seed_documents' ) ) {
        return;
    }

    foreach ( gb_get_legal_seed_documents() as $slug => $doc ) {
        $option = 'gb_legal_seeded_' . str_replace( '-', '_', $slug );
        if ( get_option( $option ) ) {
            continue;
        }

        $page = get_page_by_path( $slug );
        if ( ! $page instanceof WP_Post ) {
            continue;
        }

        if ( trim( (string) $page->post_content ) !== '' ) {
            update_option( $option, 1 );
            continue;
        }

        $update = array(
            'ID'           => $page->ID,
            'post_content' => $doc['html'],
        );

        // The old array carried its own display title ("Privacy Notice" for a
        // page called "Privacy"). Keep the heading the public already sees, and
        // make it editable along with everything else.
        if ( $doc['title'] !== '' && $doc['title'] !== $page->post_title ) {
            $update['post_title'] = $doc['title'];
        }

        $result = wp_update_post( $update, true );
        if ( ! is_wp_error( $result ) ) {
            update_option( $option, 1 );
        }
    }
}

/**
 * One-off repair for the Terms page.
 *
 * Making the legal pages editable had a consequence I got wrong: the seeding
 * migration only writes into an EMPTY page, and the Terms page was not empty -
 * it held a 37-word, two-section placeholder that had never been visible,
 * because the hardcoded array was covering it. Handing the page back its own
 * content therefore dropped three sections the public had been reading:
 * Cleaner Standards, Cancellations and Support, and Liability.
 *
 * This restores the fuller text that was actually published. It is deliberately
 * narrow: it fires only on the Terms page, only while the content still matches
 * that placeholder's signature, so it cannot touch a page anyone has since
 * edited. WordPress keeps the placeholder in the page's revision history.
 */
function gb_restore_terms_page_content() {
    if ( get_option( 'gb_legal_terms_restored' ) ) {
        return;
    }

    $page = get_page_by_path( 'terms' );
    if ( ! $page instanceof WP_Post ) {
        return;
    }

    $body  = trim( (string) $page->post_content );
    $words = str_word_count( wp_strip_all_tags( $body ) );
    $is_placeholder = $words > 0 && $words < 80 && strpos( $body, 'Acceptance of Terms' ) !== false;

    if ( ! $is_placeholder ) {
        // Already restored, already edited, or never the placeholder. Either
        // way this is not ours to rewrite.
        update_option( 'gb_legal_terms_restored', 1 );
        return;
    }

    $result = wp_update_post( array(
        'ID'           => $page->ID,
        'post_content' => gb_get_legal_seed_content( 'terms' ),
    ), true );

    if ( ! is_wp_error( $result ) ) {
        update_option( 'gb_legal_terms_restored', 1 );
    }
}

/**
 * Audit fixes, one pass, each guarded so it cannot run twice.
 *
 * Found by crawling the live site rather than reading the code, which is the
 * only way these show up: in every case the template was already right and the
 * saved value in the database was stale.
 */
function gb_apply_content_audit_fixes() {
    if ( get_option( 'gb_content_audit_v4' ) ) {
        return;
    }

    /*
     * 1. "Standard Clean" on What We Do.
     *
     * The client renamed this to "Regular Clean". The template defaults were
     * updated at the time but the stored meta was not, and stored meta wins -
     * so the public page still advertised the old name in three places.
     */
    $services_page = get_page_by_path( 'what-we-do' );
    if ( $services_page instanceof WP_Post ) {
        $renames = array(
            '_gb_svc_1_title' => array( 'Standard Clean' => 'Regular Clean' ),
            '_gb_svc_1_btn'   => array( 'Find Standard Cleaners' => 'Find Regular Cleaners' ),
            '_gb_svc_2_list'  => array( 'Includes everything in Standard' => 'Includes everything in Regular Clean' ),
        );
        foreach ( $renames as $key => $map ) {
            $value = (string) get_post_meta( $services_page->ID, $key, true );
            if ( $value === '' ) {
                continue;
            }
            foreach ( $map as $from => $to ) {
                if ( strpos( $value, $from ) !== false ) {
                    update_post_meta( $services_page->ID, $key, str_replace( $from, $to, $value ) );
                }
            }
        }
    }

    /*
     * 2. Blog posts linking to the wrong domain.
     *
     * Two posts link to thegreenbroom.co.uk over plain http. That domain
     * redirects to the home page, so a reader clicking "contact us" inside an
     * article lands on the homepage instead of the contact page.
     */
    $posts = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => -1,
    ) );
    $contact_url = gb_get_contact_page_url();
    foreach ( $posts as $post ) {
        $body = (string) $post->post_content;
        if ( stripos( $body, 'thegreenbroom.co.uk' ) === false ) {
            continue;
        }
        $fixed = str_ireplace(
            array(
                'http://www.thegreenbroom.co.uk/contact/',
                'https://www.thegreenbroom.co.uk/contact/',
                'http://thegreenbroom.co.uk/contact/',
            ),
            $contact_url,
            $body
        );
        $fixed = preg_replace(
            '#https?://(www\.)?thegreenbroom\.co\.uk/?#i',
            trailingslashit( home_url() ),
            $fixed
        );
        if ( $fixed !== $body ) {
            wp_update_post( array( 'ID' => $post->ID, 'post_content' => $fixed ) );
        }
    }

    /*
     * 3. Multiple H1s on Privacy and GDPR.
     *
     * The pasted legal documents use <h1> for their own section headings, so
     * each page ships three or four competing top-level headings. The template
     * already prints the page title as the H1. Demote the ones inside the
     * content to H2 - structure only, not a single word changes.
     */
    foreach ( array( 'privacy', 'gdpr' ) as $legal_slug ) {
        $page = get_page_by_path( $legal_slug );
        if ( ! $page instanceof WP_Post ) {
            continue;
        }
        $body = (string) $page->post_content;
        if ( stripos( $body, '<h1' ) === false ) {
            continue;
        }
        $fixed = preg_replace( '#<h1(\s[^>]*)?>#i', '<h2$1>', $body );
        $fixed = preg_replace( '#</h1>#i', '</h2>', $fixed );
        if ( $fixed !== null && $fixed !== $body ) {
            wp_update_post( array( 'ID' => $page->ID, 'post_content' => $fixed ) );
        }
    }

    /*
     * 4. The four services the page advertises must be the four a customer can
     *    actually filter by. The stored meta held the old three, and stored
     *    meta beats a template default, so the defaults alone would not show.
     */
    $services_page = get_page_by_path( 'what-we-do' );
    if ( $services_page instanceof WP_Post ) {
        $cards = array(
            1 => array(
                'title' => 'Weekly Cleaning',
                'desc'  => 'A regular visit to keep your home fresh week to week.',
                'list'  => "Dusting & wiping surfaces\nVacuuming & mopping floors\nKitchen counters\nBathroom sanitation\nEmptying bins",
                'btn'   => 'Find Weekly Cleaners',
                'link'  => '/cleaner-listings/?service=weekly-cleaning',
            ),
            2 => array(
                'title' => 'Fortnightly Cleaning',
                'desc'  => 'The same thorough clean, every other week.',
                'list'  => "Dusting & wiping surfaces\nVacuuming & mopping floors\nKitchen counters\nBathroom sanitation\nEmptying bins",
                'btn'   => 'Find Fortnightly Cleaners',
                'link'  => '/cleaner-listings/?service=fortnightly-cleaning',
            ),
            3 => array(
                'title' => 'Deep Clean',
                'desc'  => 'A thorough top-to-bottom refresh. Ideal for spring cleaning.',
                'list'  => "Everything in a regular clean\nInside oven & fridge\nSkirting boards\nLimescale removal\nBehind furniture",
                'btn'   => 'Find Deep Cleaners',
                'link'  => '/cleaner-listings/?service=deep-clean',
            ),
            4 => array(
                'title' => 'House Move & End of Tenancy',
                'desc'  => 'An intensive clean for an empty property, to help secure your deposit.',
                'list'  => "Deep clean of all rooms\nInside cupboards\nWindow cleaning (interior)\nAppliance deep cleans\nSpot wall cleaning",
                'btn'   => 'Find Move Cleaners',
                'link'  => '/cleaner-listings/?service=house-move',
            ),
        );
        foreach ( $cards as $n => $card ) {
            foreach ( $card as $field => $value ) {
                update_post_meta( $services_page->ID, "_gb_svc_{$n}_{$field}", $value );
            }
        }
    }

    /*
     * 5. The blog category was still WordPress' default "Uncategorized", which
     *    shows publicly on every post and in the archive URL.
     */
    $default_cat = get_term_by( 'slug', 'uncategorized', 'category' );
    if ( $default_cat && ! is_wp_error( $default_cat ) ) {
        wp_update_term( $default_cat->term_id, 'category', array(
            'name' => 'Eco Living',
            'slug' => 'eco-living',
        ) );
    }

    /*
     * 6. Search snippets. Most pages carry their text in template fields rather
     *    than page content, so there was nothing for the fallback to pick up
     *    and Google was left to invent a snippet. Written per page, and only
     *    where a description has not already been set by hand.
     */
    foreach ( gb_get_default_meta_descriptions() as $slug => $description ) {
        $page = get_page_by_path( $slug );
        if ( ! $page instanceof WP_Post ) {
            continue;
        }
        if ( trim( (string) get_post_meta( $page->ID, '_gb_seo_meta_desc', true ) ) === '' ) {
            update_post_meta( $page->ID, '_gb_seo_meta_desc', $description );
        }
    }

    /*
     * 7. The DBS contradiction.
     *
     * Privacy and GDPR both stated that no criminal records data is processed,
     * while the rest of the site said every cleaner is Basic DBS checked - and
     * page-verify-profile.php has cleaners upload the certificate itself, which
     * is then held on file. So the notice was the inaccurate one, not the
     * marketing copy, and it was inaccurate about Article 10 data.
     *
     * This replaces that sentence with what the platform actually does. It is
     * a factual correction, not legal drafting: the client has been asked to
     * have it reviewed, including the lawful basis relied on for this data.
     */
    $old_dbs = 'We do not request, require, or process criminal records or DBS checks. We also do not collect any data that is classified as special category data under the GDPR.';
    $new_dbs = 'If you apply to join The Green Broom as a cleaner, we ask you to upload a Basic DBS certificate as part of our verification checks, and we keep a copy of it on file together with the date it was verified. We use it only to confirm that a cleaner has been vetted before their profile is made public, and we do not share it with customers. Customers are never asked for criminal records information. We do not collect any other data that is classified as special category data under the UK GDPR.';

    foreach ( array( 'privacy', 'gdpr' ) as $legal_slug ) {
        $page = get_page_by_path( $legal_slug );
        if ( ! $page instanceof WP_Post ) {
            continue;
        }
        $body = (string) $page->post_content;
        if ( strpos( $body, $old_dbs ) === false ) {
            continue;
        }
        wp_update_post( array(
            'ID'           => $page->ID,
            'post_content' => str_replace( $old_dbs, $new_dbs, $body ),
        ) );
    }

    update_option( 'gb_content_audit_v4', 1 );
}

/**
 * Cleaner profiles are directory entries, not blog posts, but WordPress was
 * still printing its own comment form on them - an open, unmoderated posting
 * box on a public profile page. The platform has its own review system; this
 * was only ever a spam target.
 */
function gb_close_comments_on_directory_types( $open, $post_id ) {
    $type = get_post_type( $post_id );
    if ( in_array( $type, array( 'gb_cleaner', 'gb_service', 'gb_eco_product', 'page' ), true ) ) {
        return false;
    }

    return $open;
}
add_filter( 'comments_open', 'gb_close_comments_on_directory_types', 10, 2 );
add_filter( 'pings_open', 'gb_close_comments_on_directory_types', 10, 2 );

function gb_get_cookie_policy_seed_content() {
    $contact = function_exists( 'gb_get_contact_page_url' ) ? gb_get_contact_page_url() : home_url( '/contact/' );
    $privacy = function_exists( 'gb_get_privacy_page_url' ) ? gb_get_privacy_page_url() : home_url( '/privacy/' );

    $html  = '<p><strong>Cookies, and how The Green Broom uses them.</strong></p>';

    $html .= '<h2>The short version</h2>';
    $html .= '<p>Browsing The Green Broom sets no cookies at all. We only store anything once you sign in, or once you answer the cookie banner. We do not use advertising or analytics cookies, and we do not sell or share your browsing data.</p>';

    $html .= '<h2>What a cookie is</h2>';
    $html .= '<p>A cookie is a small text file a website asks your browser to keep. It lets the site recognise the same browser on a later page or a later visit, which is how you stay signed in without typing your password on every screen. Similar technologies, such as your browser\'s local storage, work the same way and are covered by this policy too.</p>';

    $html .= '<h2>The cookies we set</h2>';

    $html .= '<h3>Strictly necessary</h3>';
    $html .= '<p>These keep the site working and keep your account secure. They do not require your consent, because the site cannot function without them, and they are not used to track you.</p>';
    $html .= '<ul>';
    $html .= '<li><strong>WordPress sign-in cookies</strong> (names beginning <em>wordpress_logged_in_</em> and <em>wordpress_sec_</em>) &mdash; set when you log in, so you stay signed in as you move around your dashboard. They are removed when you log out, and otherwise expire within about two weeks.</li>';
    $html .= '<li><strong>Your cookie choice</strong> (<em>gb_cookie_consent</em>) &mdash; records the answer you gave the cookie banner so we do not ask again. Kept for six months. Without it, the banner would reappear on every page.</li>';
    $html .= '<li><strong>Caching and security cookies</strong> &mdash; our hosting serves pages from a cache to keep the site fast, and uses short-lived cookies to make sure a signed-in visitor is never served someone else\'s cached page.</li>';
    $html .= '</ul>';

    $html .= '<h3>Analytics</h3>';
    $html .= '<p>None at present. We do not run Google Analytics, Tag Manager, or any similar measurement tool. The banner asks about analytics so that, if we add one, your answer is already recorded and honoured from the outset rather than applied after the fact.</p>';

    $html .= '<h3>Advertising and marketing</h3>';
    $html .= '<p>None at present. There is no Meta pixel, no TikTok pixel, and no advertising tag of any kind on this site. As with analytics, the banner asks in advance so that nothing is ever set before you have agreed to it.</p>';

    $html .= '<h2>Cookies set by other companies</h2>';
    $html .= '<p>When you pay for a booking, the payment itself is handled by <strong>Stripe</strong>. Stripe sets its own cookies on its own pages to process the payment and detect fraud. We do not control those cookies; Stripe describes them in its own privacy policy.</p>';
    $html .= '<p>Our footer links to our social media profiles. These are ordinary links &mdash; nothing is loaded from those companies while you are on our site, and they cannot see your visit unless you click through.</p>';

    $html .= '<h2>Changing your mind</h2>';
    $html .= '<p>Select <strong>Cookie settings</strong> at the bottom of any page to reopen the banner and change your answer at any time. Withdrawing consent is as easy as giving it.</p>';
    $html .= '<p>You can also clear or block cookies in your browser settings. Blocking the strictly necessary ones will sign you out and may stop parts of the site working.</p>';

    $html .= '<h2>Questions</h2>';
    $html .= '<p>Our <a href="' . esc_url( $privacy ) . '">Privacy Notice</a> explains how we handle personal data more broadly, including your rights under UK GDPR. If anything here is unclear, please <a href="' . esc_url( $contact ) . '">get in touch</a>.</p>';

    return $html;
}

function gb_get_cookie_policy_page_url() {
    $page = get_page_by_path( 'cookies' );
    if ( $page instanceof WP_Post && $page->post_status === 'publish' ) {
        return get_permalink( $page );
    }

    return function_exists( 'gb_get_privacy_page_url' ) ? gb_get_privacy_page_url() : home_url( '/privacy/' );
}

/**
 * Add the supplied Recycling image to the legacy article once. New articles
 * already save their selected featured image through the manager editor.
 */
function gb_ensure_legacy_blog_featured_images() {
    if ( get_option( 'gb_legacy_blog_featured_images_version' ) === '1' ) {
        return;
    }

    $recycling_post = get_page_by_path( 'uk-household-recycling', OBJECT, 'post' );
    $recycling_image = attachment_url_to_postid( 'https://thegreenbroom.uk/wp-content/uploads/2026/07/UK-Recycling.png' );

    if ( $recycling_post && $recycling_image && ! has_post_thumbnail( $recycling_post ) ) {
        set_post_thumbnail( $recycling_post->ID, $recycling_image );
    }

    if ( $recycling_post && ( $recycling_image || has_post_thumbnail( $recycling_post ) ) ) {
        update_option( 'gb_legacy_blog_featured_images_version', '1' );
    }
}

/**
 * Deliver public and portal-only resource files through one controlled route.
 */
function gb_get_resource_definitions() {
    return array(
        'cleaner-handbook' => array(
            'label' => 'Cleaner Information Booklet',
            'file'  => 'cleaner-handbook.pdf',
        ),
        'eco-cleaning-products' => array(
            'label' => 'Eco Cleaning Products Guide',
            'file'  => 'eco-cleaning-products.pdf',
        ),
        'subscribers-terms-of-association' => array(
            'label' => "Subscribers' Terms of Association",
            'file'  => 'subscribers-terms-of-association.pdf',
        ),
        'make-the-most-of-your-eco-cleaner' => array(
            'label' => 'Make the Most of Your Eco-Cleaner',
            'file'  => 'make-the-most-of-your-eco-cleaner.pdf',
        ),
        'certificate-of-insurance' => array(
            'label' => 'Certificate of Insurance',
            'file'  => 'certificate-of-insurance.pdf',
        ),
    );
}

/**
 * Give protected documents a filename-based URL so browser PDF viewers do not
 * label them after the generic admin-post.php delivery script.
 */
function gb_register_resource_rewrite_rules() {
    add_rewrite_rule(
        '^greenbroom-resource/([^/]+)/([^/]+)/?$',
        'index.php?gb_resource=$matches[1]&gb_resource_file=$matches[2]',
        'top'
    );
}
add_action( 'init', 'gb_register_resource_rewrite_rules', 5 );

function gb_register_resource_query_vars( $vars ) {
    $vars[] = 'gb_resource';
    $vars[] = 'gb_resource_file';
    return $vars;
}
add_filter( 'query_vars', 'gb_register_resource_query_vars' );

function gb_maybe_flush_resource_rewrite_rules() {
    if ( get_option( 'gb_resource_rewrite_version' ) === '1' ) {
        return;
    }

    flush_rewrite_rules( false );
    update_option( 'gb_resource_rewrite_version', '1' );
}
add_action( 'init', 'gb_maybe_flush_resource_rewrite_rules', 20 );

/**
 * Attachment ID of a manager-uploaded replacement for a bundled document.
 *
 * Stored as post meta on the settings page so the dashboard's existing "_gb_*"
 * save handler persists it with no extra plumbing.
 */
function gb_get_resource_attachment_id( $resource ) {
    $settings_id = gb_get_site_settings_id();
    if ( ! $settings_id ) {
        return 0;
    }

    return (int) get_post_meta( $settings_id, '_gb_resource_' . sanitize_key( (string) $resource ), true );
}

/**
 * True when a document is actually available (uploaded or bundled).
 */
function gb_resource_is_available( $resource ) {
    if ( gb_get_resource_attachment_id( $resource ) ) {
        return true;
    }

    $resources = gb_get_resource_definitions();
    if ( ! isset( $resources[ $resource ]['file'] ) ) {
        return false;
    }

    return is_readable( trailingslashit( get_template_directory() ) . 'assets/documents/' . $resources[ $resource ]['file'] );
}

/**
 * Published Eco Products for the public grid.
 */
function gb_get_eco_products( $limit = -1 ) {
    return get_posts( array(
        'post_type'      => 'gb_eco_product',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
    ) );
}

/**
 * Resolve the link for an Eco Product: an uploaded file wins, then a manual
 * link, otherwise nothing (the card shows as "coming soon").
 */
function gb_get_eco_product_link( $product_id ) {
    /*
     * A written guide wins over an attached file.
     *
     * The file used to take priority, which meant a guide written in the
     * dashboard was unreachable as long as a document was attached - and the
     * two live guides were serving a .docx and a .zip to visitors. A Word file
     * or an archive is a poor thing to hand someone on a phone and search
     * engines cannot read either, so the page is the destination and the file
     * becomes a download offered on that page.
     */
    if ( gb_eco_product_has_guide( $product_id ) ) {
        return (string) get_permalink( $product_id );
    }

    $file_id = (int) get_post_meta( $product_id, '_gb_eco_file', true );
    if ( $file_id ) {
        $url = wp_get_attachment_url( $file_id );
        if ( $url ) {
            return $url;
        }
    }

    return trim( (string) get_post_meta( $product_id, '_gb_eco_link', true ) );
}

/**
 * Whether an Eco Product carries a real write-up worth its own page.
 *
 * Deliberately a word count rather than a non-empty check: a body holding
 * just a heading line is a stub, and both the card link and the guide page
 * must agree on that, or a card links to a page that redirects straight back.
 */
function gb_eco_product_has_guide( $product_id ) {
    $post = get_post( (int) $product_id );
    if ( ! $post ) {
        return false;
    }

    return str_word_count( wp_strip_all_tags( (string) $post->post_content ) ) >= 60;
}

/**
 * Button/label wording for an Eco Product card. Both were hard-coded strings
 * ("View guide" / "Coming soon"), which meant a manager could publish a
 * product but not say anything else on its button. Stored per product so each
 * card can speak for itself; the defaults keep existing cards unchanged.
 */
function gb_get_eco_product_cta_label( $product_id ) {
    $label = trim( (string) get_post_meta( $product_id, '_gb_eco_cta_label', true ) );
    return $label === '' ? 'View guide' : $label;
}

function gb_get_eco_product_soon_label( $product_id ) {
    $label = trim( (string) get_post_meta( $product_id, '_gb_eco_soon_label', true ) );
    return $label === '' ? 'Coming soon' : $label;
}

/**
 * Seed the catalogue once from the two entries the page used to hard-code, so
 * moving to a repeatable post type does not change what visitors see today.
 * Deliberately not gated on a logged-in manager: the public page renders from
 * this catalogue, so it has to be populated on the first request of any kind.
 * The autoloaded option short-circuits every subsequent page load.
 */
add_action( 'init', function () {
    if ( get_option( 'gb_eco_products_seeded_v1' ) ) {
        return;
    }

    $existing = get_posts( array( 'post_type' => 'gb_eco_product', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
    if ( empty( $existing ) ) {
        // Carry over anything already typed into the old page-meta card fields
        // so a manager's existing wording survives the migration.
        $page      = get_page_by_path( 'eco-products' );
        $page_id   = $page ? (int) $page->ID : 0;
        $legacy    = function ( $key, $default ) use ( $page_id ) {
            if ( ! $page_id ) {
                return $default;
            }
            $value = trim( (string) get_post_meta( $page_id, $key, true ) );
            return $value === '' ? $default : $value;
        };

        $seed = array(
            array(
                'title' => $legacy( '_gb_eco_c1_title', 'Eco Cleaning Products Guide' ),
                'desc'  => $legacy( '_gb_eco_c1_text', 'A simple guide to plant-based products and easy alternatives for everyday cleaning.' ),
                'link'  => function_exists( 'gb_get_resource_url' ) ? gb_get_resource_url( 'eco-cleaning-products' ) : '',
            ),
            array(
                'title' => $legacy( '_gb_eco_c2_title', 'Make Your Own' ),
                'desc'  => $legacy( '_gb_eco_c2_text', 'Simple, lower-impact ideas for making your own cleaning products. The full guide is coming soon.' ),
                'link'  => $legacy( '_gb_eco_c2_link', '' ),
            ),
        );
        $order = 0;
        foreach ( $seed as $item ) {
            $id = wp_insert_post( array(
                'post_type'    => 'gb_eco_product',
                'post_status'  => 'publish',
                'post_title'   => $item['title'],
                'post_content' => $item['desc'],
                'menu_order'   => $order++,
            ) );
            if ( $id && ! is_wp_error( $id ) && $item['link'] !== '' ) {
                update_post_meta( $id, '_gb_eco_link', $item['link'] );
            }
        }
    }

    update_option( 'gb_eco_products_seeded_v1', 1 );
}, 20 );

function gb_get_resource_url( $resource ) {
    $resource = sanitize_key( (string) $resource );
    $resources = gb_get_resource_definitions();
    $filename = isset( $resources[ $resource ]['file'] ) ? $resources[ $resource ]['file'] : $resource . '.pdf';
    $resource_path = '/greenbroom-resource/' . rawurlencode( $resource ) . '/' . rawurlencode( $filename );

    $protected_resources = array( 'cleaner-handbook', 'subscribers-terms-of-association', 'make-the-most-of-your-eco-cleaner', 'certificate-of-insurance' );
    if ( ! in_array( $resource, $protected_resources, true ) ) {
        return home_url( $resource_path );
    }

    return wp_nonce_url(
        home_url( $resource_path ),
        'gb_download_resource_' . $resource
    );
}

function gb_handle_resource_rewrite() {
    $resource = get_query_var( 'gb_resource' );
    if ( $resource ) {
        $_GET['resource'] = $resource;
        gb_handle_resource_download();
    }
}
add_action( 'template_redirect', 'gb_handle_resource_rewrite', 1 );

function gb_handle_resource_download() {
    $resource = isset( $_GET['resource'] ) ? sanitize_key( wp_unslash( $_GET['resource'] ) ) : '';
    $resources = gb_get_resource_definitions();
    if ( ! isset( $resources[ $resource ] ) ) {
        wp_die( 'Resource not found.', 'Resource not found', array( 'response' => 404 ) );
    }

    $protected_resources = array( 'cleaner-handbook', 'subscribers-terms-of-association', 'make-the-most-of-your-eco-cleaner', 'certificate-of-insurance' );
    if ( in_array( $resource, $protected_resources, true ) && ! is_user_logged_in() ) {
        auth_redirect();
    }

    $role_restricted_resources = array(
        'cleaner-handbook' => array( 'gb_cleaner' ),
        'subscribers-terms-of-association' => array( 'gb_customer', 'gb_client' ),
        // gb_client is the legacy customer role used by earlier accounts.
        'make-the-most-of-your-eco-cleaner' => array( 'gb_customer', 'gb_client' ),
    );
    if ( isset( $role_restricted_resources[ $resource ] ) && ! current_user_can( 'manage_options' ) ) {
        $current_user = wp_get_current_user();
        $required_roles = $role_restricted_resources[ $resource ];
        $is_manager = in_array( 'gb_manager', (array) $current_user->roles, true );
        if ( ! $is_manager && ! array_intersect( $required_roles, (array) $current_user->roles ) ) {
            wp_die( 'This resource is available from your account portal.', 'Resource access denied', array( 'response' => 403 ) );
        }
    }

    if ( in_array( $resource, $protected_resources, true ) ) {
        check_admin_referer( 'gb_download_resource_' . $resource );
    }

    // A manager-uploaded document takes precedence over the file bundled with
    // the theme. Without this, replacing any of these PDFs required a code
    // deploy, which is why they could not be updated from the dashboard.
    $file = '';
    $mime = 'application/pdf';
    $download_filename = sanitize_file_name( $resources[ $resource ]['file'] );

    $override_id = gb_get_resource_attachment_id( $resource );
    if ( $override_id ) {
        $override_path = get_attached_file( $override_id );
        if ( $override_path && is_readable( $override_path ) ) {
            $file = $override_path;
            $override_mime = get_post_mime_type( $override_id );
            if ( $override_mime ) {
                $mime = $override_mime;
            }
            $download_filename = sanitize_file_name( basename( $override_path ) );
        }
    }

    if ( $file === '' ) {
        $bundled = trailingslashit( get_template_directory() ) . 'assets/documents/' . $resources[ $resource ]['file'];
        if ( is_readable( $bundled ) ) {
            $file = $bundled;
        }
    }

    if ( $file === '' ) {
        wp_die( 'This resource is not available yet.', 'Resource unavailable', array( 'response' => 404 ) );
    }

    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: inline; filename="' . $download_filename . '"; filename*=UTF-8\'\'' . rawurlencode( $download_filename ) );
    header( 'Content-Location: ' . $download_filename );
    header( 'Content-Length: ' . (string) filesize( $file ) );
    readfile( $file );
    exit;
}
add_action( 'admin_post_gb_download_resource', 'gb_handle_resource_download' );
add_action( 'admin_post_nopriv_gb_download_resource', 'gb_handle_resource_download' );

function gb_get_support_phone() {
    $settings_id = (int) get_option( 'page_on_front' );
    $phone = $settings_id ? sanitize_text_field( (string) get_post_meta( $settings_id, '_gb_support_phone', true ) ) : '';

    return $phone !== '' ? $phone : '07974 903568';
}

function gb_apply_contact_defaults() {
    if ( get_option( 'gb_contact_defaults_v1_applied' ) ) {
        return;
    }

    $settings_id = (int) get_option( 'page_on_front' );
    if ( $settings_id ) {
        update_post_meta( $settings_id, '_gb_support_phone', '07974 903568' );
        update_post_meta( $settings_id, '_gb_support_email', 'info@thegreenbroom.co.uk' );
        update_post_meta( $settings_id, '_gb_email_from_address', 'info@thegreenbroom.co.uk' );
        update_post_meta( $settings_id, '_gb_email_reply_to', 'info@thegreenbroom.co.uk' );
        update_option( 'gb_contact_defaults_v1_applied', 1, false );
    }
}

function gb_get_support_email() {
    $settings_id = (int) get_option( 'page_on_front' );
    $email = $settings_id ? sanitize_email( (string) get_post_meta( $settings_id, '_gb_support_email', true ) ) : '';

    return is_email( $email ) ? $email : 'info@thegreenbroom.co.uk';
}

/**
 * The platform fee is no longer part of the requested business model.
 * Clear the old default once so existing installations do not keep charging 10%.
 */
function gb_remove_platform_fee_setting() {
    if ( get_option( 'gb_platform_fee_removed' ) ) {
        return;
    }

    $settings_id = (int) get_option( 'page_on_front' );
    if ( $settings_id ) {
        update_post_meta( $settings_id, '_gb_platform_fee', '0' );
    }
    update_option( 'gb_platform_fee_removed', 1 );
}

function gb_migrate_mission_title() {
    if ( get_option( 'gb_mission_title_migrated' ) ) {
        return;
    }

    $page_ids = get_posts( array(
        'post_type'      => 'page',
        'name'           => 'what-we-do',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );
    if ( ! empty( $page_ids ) ) {
        $current = get_post_meta( (int) $page_ids[0], '_gb_svc_mis_title', true );
        if ( $current === '' || $current === 'The "Green" in Green Broom' ) {
            update_post_meta( (int) $page_ids[0], '_gb_svc_mis_title', 'A cleaner home, a greener choice' );
        }
    }
    update_option( 'gb_mission_title_migrated', 1 );
}

/**
 * One-time migration: rename "What We Do" to "What Cleaners Do", and restore
 * the solid green hero.
 *
 * The hero lost its green because the page builder offered a stock Unsplash
 * URL as the DEFAULT for _gb_svc_hero_bg, while the template's own fallback is
 * background-color #1b5e20. The two disagreed, so the first "Save Content"
 * wrote the photo in for real and the green never came back. The builder
 * default is now gone; this clears the value it planted.
 *
 * Both changes are conditional: the title is only renamed while it is still
 * the original, and the image is only cleared while it is still that exact
 * stock URL, so a deliberate later edit is never overwritten.
 */
function gb_migrate_what_cleaners_do() {
    if ( get_option( 'gb_what_cleaners_do_migrated' ) ) {
        return;
    }

    $page_ids = get_posts( array(
        'post_type'      => 'page',
        'name'           => 'what-we-do',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $page_ids ) ) {
        $page_id = (int) $page_ids[0];

        // Slug stays 'what-we-do' on purpose: changing it would break every
        // existing link and any search ranking the page has.
        $title = get_the_title( $page_id );
        if ( $title === 'What We Do' || $title === 'What We Do (Services)' ) {
            wp_update_post( array( 'ID' => $page_id, 'post_title' => 'What Cleaners Do' ) );
        }

        $stock_hero = 'https://images.unsplash.com/photo-1584622050111-993a426fbf0a';
        $hero_bg    = trim( (string) get_post_meta( $page_id, '_gb_svc_hero_bg', true ) );
        if ( $hero_bg !== '' && strpos( $hero_bg, $stock_hero ) === 0 ) {
            delete_post_meta( $page_id, '_gb_svc_hero_bg' );
        }
    }

    update_option( 'gb_what_cleaners_do_migrated', 1 );
}

require_once get_template_directory() . '/inc/eco-guide-content.php';
require_once get_template_directory() . '/inc/green-broom-standard.php';
require_once get_template_directory() . '/inc/cookie-consent.php';
require_once get_template_directory() . '/inc/legal-content.php';
require_once get_template_directory() . '/inc/membership.php';
require_once get_template_directory() . '/inc/page-text.php';

/**
 * One-time migration: publish the two client-written Eco Product guides.
 *
 * Renames "Eco Cleaning Products Guide" to "A Quick & Easy Way to Get Started"
 * and gives both it and "Make Your Own" their full write-ups, which also
 * activates "Make Your Own" - a guide with content resolves to its own page,
 * so the card stops reading "Coming soon".
 *
 * Each field is only written while it still holds the seeded placeholder, so
 * anything the client has since edited in Manager OS is never overwritten.
 * Also flushes rewrite rules once, because the post type has just become
 * public and its /eco-guide/ URLs would otherwise 404.
 */
function gb_migrate_eco_guides() {
    if ( get_option( 'gb_eco_guides_seeded_v1' ) ) {
        return;
    }

    $content = gb_eco_guide_seed_content();
    $targets = array(
        'Eco Cleaning Products Guide' => $content['koh'],
        'Make Your Own'               => $content['diy'],
    );

    $products = get_posts( array(
        'post_type'      => 'gb_eco_product',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => -1,
    ) );

    $seeded_descriptions = array(
        'A simple guide to plant-based products and easy alternatives for everyday cleaning.',
        'Simple, lower-impact ideas for making your own cleaning products. The full guide is coming soon.',
    );

    foreach ( $products as $product ) {
        if ( ! isset( $targets[ $product->post_title ] ) ) {
            continue;
        }
        $new = $targets[ $product->post_title ];

        // Only replace the body while it is still the one-line placeholder.
        $body_is_placeholder = in_array( trim( (string) $product->post_content ), $seeded_descriptions, true )
            || trim( (string) $product->post_content ) === '';
        if ( ! $body_is_placeholder ) {
            continue;
        }

        wp_update_post( array(
            'ID'           => $product->ID,
            'post_title'   => $new['title'],
            'post_content' => wp_kses_post( $new['body'] ),
            'post_excerpt' => $new['desc'],
            'post_status'  => 'publish',
        ) );
    }

    flush_rewrite_rules( false );
    update_option( 'gb_eco_guides_seeded_v1', 1 );
}

/**
 * One-time migration: give the two client-written guides their real web pages.
 *
 * The first pass was skipped because the guides had already been edited by
 * hand, so both ended up serving a downloadable document instead - a .docx and
 * then a .zip. The client has since asked for them to be proper pages.
 *
 * Only fills a guide whose body is still a stub, so anything genuinely written
 * in the dashboard is left alone. The uploaded file is kept and appears as a
 * download on the page, so nothing the client uploaded is lost.
 */
function gb_migrate_eco_guides_to_pages() {
    if ( get_option( 'gb_eco_guides_pages_v2' ) ) {
        return;
    }

    $content = gb_eco_guide_seed_content();
    $targets = array(
        'a quick & easy way to get started' => $content['koh'],
        'a quick & easy start'              => $content['koh'],
        'eco cleaning products guide'       => $content['koh'],
        'make your own'                     => $content['diy'],
    );

    $products = get_posts( array(
        'post_type'      => 'gb_eco_product',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => -1,
    ) );

    foreach ( $products as $product ) {
        $key = strtolower( trim( html_entity_decode( $product->post_title, ENT_QUOTES, 'UTF-8' ) ) );
        if ( ! isset( $targets[ $key ] ) ) {
            continue;
        }
        // Never overwrite a real write-up someone has already produced.
        if ( gb_eco_product_has_guide( $product->ID ) ) {
            continue;
        }

        $new = $targets[ $key ];
        wp_update_post( array(
            'ID'           => $product->ID,
            'post_content' => wp_kses_post( $new['body'] ),
            'post_excerpt' => trim( (string) $product->post_excerpt ) !== '' ? $product->post_excerpt : $new['desc'],
            'post_status'  => 'publish',
        ) );
    }

    flush_rewrite_rules( false );
    update_option( 'gb_eco_guides_pages_v2', 1 );
}

/**
 * An Eco Product only deserves its own page when it actually has a write-up.
 *
 * The post type is public so guides written in Manager OS get a real page,
 * but most guides are an uploaded file instead. Without this, those resolve
 * to a near-empty "this guide is being written" page that search engines can
 * index and visitors can land on. Send them somewhere useful instead:
 * the attached file or link if there is one, otherwise the Eco Products page.
 */
function gb_redirect_empty_eco_guides() {
    if ( ! is_singular( 'gb_eco_product' ) ) {
        return;
    }

    $product_id = get_queried_object_id();
    $post       = $product_id ? get_post( $product_id ) : null;

    // Same test the card link uses, so the two can never disagree.
    if ( $post && gb_eco_product_has_guide( $product_id ) ) {
        return;
    }

    $file_id = (int) get_post_meta( $product_id, '_gb_eco_file', true );
    $target  = $file_id ? wp_get_attachment_url( $file_id ) : '';
    if ( ! $target ) {
        $target = trim( (string) get_post_meta( $product_id, '_gb_eco_link', true ) );
    }
    if ( ! $target ) {
        $page   = get_page_by_path( 'eco-products' );
        $target = $page ? get_permalink( $page ) : home_url( '/' );
    }

    /*
     * wp_safe_redirect() refuses off-site hosts and would bounce an external
     * guide link to wp-admin. This target is manager-entered, not visitor
     * input, so an outside host is legitimate here - but keep the safe
     * variant for anything on our own domain.
     */
    $target_host = wp_parse_url( $target, PHP_URL_HOST );
    if ( $target_host && $target_host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
        wp_redirect( esc_url_raw( $target ), 302 );
    } else {
        wp_safe_redirect( $target, 302 );
    }
    exit;
}
add_action( 'template_redirect', 'gb_redirect_empty_eco_guides' );

/**
 * One-time migration: point existing "Join" buttons at the new /join page.
 * Only rewrites values that are empty or still '/register' so any deliberately
 * customised link is preserved. Guarded by an option so it runs just once.
 */
function gb_migrate_join_links() {
    if ( get_option( 'gb_join_links_migrated' ) ) {
        return;
    }

    $targets = array();

    $front_id = (int) get_option( 'page_on_front' );
    if ( $front_id ) {
        $targets[ $front_id ] = '_gb_hero_link_2';
    }

    foreach ( array( 'how-we-do-it' => '_gb_about_btn2_link', 'what-we-do' => '_gb_svc_mis_link' ) as $slug => $meta_key ) {
        $ids = get_posts( array(
            'post_type'      => 'page',
            'name'           => $slug,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
        if ( ! empty( $ids ) ) {
            $targets[ (int) $ids[0] ] = $meta_key;
        }
    }

    foreach ( $targets as $pid => $meta_key ) {
        $current = get_post_meta( $pid, $meta_key, true );
        if ( $current === '' || $current === '/register' ) {
            update_post_meta( $pid, $meta_key, '/join' );
        }
    }

    update_option( 'gb_join_links_migrated', 1 );
}

/**
 * Canonical cleaner hourly rate accessor.
 * Historically two meta keys were used: 'hourly_rate' (theme/frontend/booking)
 * and 'gb_hourly_rate' (plugin admin metabox). This reads the canonical
 * 'hourly_rate' first, falls back to the legacy key, then to a £15 default.
 */
function gb_get_cleaner_rate( $cleaner_id ) {
    $rate = get_post_meta( $cleaner_id, 'hourly_rate', true );
    if ( $rate === '' || $rate === null ) {
        $rate = get_post_meta( $cleaner_id, 'gb_hourly_rate', true );
    }
    $rate = (float) $rate;
    return $rate > 0 ? $rate : 15.0;
}

/**
 * One-time migration: consolidate legacy 'gb_hourly_rate' into 'hourly_rate'
 * so the listing, profile, dashboard and booking calculator all agree.
 */
function gb_migrate_cleaner_rates() {
    if ( get_option( 'gb_cleaner_rates_migrated' ) ) {
        return;
    }

    $cleaners = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'post_status'    => array( 'publish', 'pending', 'draft' ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    foreach ( $cleaners as $cid ) {
        $primary = get_post_meta( $cid, 'hourly_rate', true );
        $legacy  = get_post_meta( $cid, 'gb_hourly_rate', true );
        if ( ( $primary === '' || $primary === null ) && $legacy !== '' && $legacy !== null ) {
            update_post_meta( $cid, 'hourly_rate', $legacy );
        }
    }

    update_option( 'gb_cleaner_rates_migrated', 1 );
}

/**
 * Ensure the standalone editable GDPR page exists. Cookie information lives
 * inside the consolidated legal content rather than on its own page.
 */
function gb_ensure_legal_pages() {
    if ( get_option( 'gb_legal_pages_created' ) ) {
        return;
    }

    $legal_pages = array(
        'gdpr' => array(
            'title'   => 'GDPR',
            'content' => "This is your GDPR / Data Protection statement. Replace this text with your full policy from the manager dashboard (Pages → GDPR).\n\nYou have the right to access, correct, and request deletion of your personal data.",
        ),
    );

    foreach ( $legal_pages as $slug => $data ) {
        $existing = get_posts( array(
            'post_type'      => 'page',
            'name'           => $slug,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
        if ( empty( $existing ) ) {
            wp_insert_post( array(
                'post_title'   => $data['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $data['content'],
            ) );
        }
    }

    update_option( 'gb_legal_pages_created', 1 );
}

/**
 * Retire the standalone Cookie Policy page now that cookie information lives
 * alongside the site's other legal information. The old URL is redirected
 * to the Privacy Policy so saved links remain useful.
 */
function gb_retire_standalone_cookie_page() {
    if ( get_option( 'gb_cookie_page_retired_v1' ) ) {
        return;
    }

    $cookies_page = get_page_by_path( 'cookies', OBJECT, 'page' );
    if ( $cookies_page && $cookies_page->post_status !== 'trash' ) {
        wp_trash_post( $cookies_page->ID );
    }

    update_option( 'gb_cookie_page_retired_v1', 1, false );
}

/**
 * Email the manager when a new support/contact message arrives.
 * Destination is the "Support Notification Email" setting, falling back to
 * the Green Broom support inbox.
 */
function gb_notify_support_message( $message_id, $context = 'Contact' ) {
    $to = gb_get_support_email();

    $sender_name  = (string) get_post_meta( $message_id, '_gb_sender_name', true );
    $sender_email = (string) get_post_meta( $message_id, '_gb_sender_email', true );
    $post         = get_post( $message_id );
    $subject_line = $post ? $post->post_title : 'New message';
    $body_text    = $post ? $post->post_content : '';

    $dashboard_url = home_url( '/manager-dashboard?tab=support&action=view_message&id=' . $message_id );

    $subject = sprintf( '[%s] New %s message: %s', get_bloginfo( 'name' ), $context, $subject_line );
    $body    = "You have a new {$context} message on " . get_bloginfo( 'name' ) . ".\n\n"
             . "From:    {$sender_name}" . ( $sender_email ? " <{$sender_email}>" : '' ) . "\n"
             . "Subject: {$subject_line}\n\n"
             . "Message:\n{$body_text}\n\n"
             . "View & reply in the dashboard:\n{$dashboard_url}\n";

    $headers = array();
    if ( is_email( $sender_email ) ) {
        $headers[] = 'Reply-To: ' . $sender_name . ' <' . $sender_email . '>';
    }

    gb_send_brevo_template_email(
        $to,
        $subject,
        $body,
        $headers,
        gb_get_brevo_template_id( 'manager_message' ),
        [
            'context'       => $context,
            'sender_name'   => $sender_name,
            'sender_email'  => $sender_email,
            'subject'       => $subject_line,
            'message'       => $body_text,
            'dashboard_url' => $dashboard_url,
        ]
    );
}

/**
 * Auto-archive support messages older than 30 days (daily cron).
 * Archived messages drop out of the main inbox but remain viewable under
 * the "Archived" filter and are never deleted automatically.
 */
function gb_archive_old_messages() {
    $old = get_posts( array(
        'post_type'      => 'gb_message',
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'fields'         => 'ids',
        'date_query'     => array(
            array( 'before' => '30 days ago' ),
        ),
        'meta_query'     => array(
            array( 'key' => '_gb_archived', 'compare' => 'NOT EXISTS' ),
        ),
    ) );

    foreach ( $old as $mid ) {
        update_post_meta( $mid, '_gb_archived', '1' );
    }
}
add_action( 'gb_archive_old_messages_event', 'gb_archive_old_messages' );

function gb_schedule_message_archive() {
    if ( ! wp_next_scheduled( 'gb_archive_old_messages_event' ) ) {
        wp_schedule_event( time() + 3600, 'daily', 'gb_archive_old_messages_event' );
    }
}
add_action( 'init', 'gb_schedule_message_archive' );

/**
 * Whether a cleaner has completed mandatory verification: DBS + Government ID
 * + Proof of Address documents, their phone number, and two full references.
 * Returns array of missing item labels (empty array = complete).
 */
function gb_cleaner_verification_missing( $cleaner_id ) {
    $missing = array();

    $doc_labels = array( 'doc_id' => 'Government ID', 'doc_address' => 'Proof of Address', 'doc_dbs' => 'DBS Certificate' );
    if ( class_exists( 'GB_Uploads' ) && method_exists( 'GB_Uploads', 'get_cleaner_verification_document' ) ) {
        foreach ( $doc_labels as $field => $label ) {
            $doc = GB_Uploads::get_cleaner_verification_document( $cleaner_id, $field );
            if ( empty( $doc ) ) {
                $missing[] = $label;
            }
        }
    }

    if ( ! get_post_meta( $cleaner_id, '_gb_cleaner_phone', true ) ) {
        $missing[] = 'Phone number';
    }
    foreach ( array( '1', '2' ) as $n ) {
        $ok = get_post_meta( $cleaner_id, "_gb_ref{$n}_name", true )
           && get_post_meta( $cleaner_id, "_gb_ref{$n}_address", true )
           && is_email( get_post_meta( $cleaner_id, "_gb_ref{$n}_email", true ) );
        if ( ! $ok ) {
            $missing[] = "Reference {$n}";
        }
    }

    if ( get_post_meta( $cleaner_id, '_gb_toa_signed', true ) !== '1' ) {
        $missing[] = 'Signed Terms of Association';
    }

    return $missing;
}

/**
 * The Cleaner Terms of Association text (editable in settings, with a default).
 */
function gb_get_terms_of_association() {
    $settings_id = (int) get_option( 'page_on_front' );
    $text = $settings_id ? get_post_meta( $settings_id, '_gb_terms_of_association', true ) : '';
    if ( trim( (string) $text ) !== '' ) {
        return $text;
    }

    return "TERMS OF ASSOCIATION\n\n"
        . "This agreement is between The Green Broom Ltd (\"the Platform\") and the cleaner named below (\"the Associate\").\n\n"
        . "1. Independent status. The Associate provides cleaning services as a self-employed independent professional and is not an employee of the Platform.\n\n"
        . "2. Conduct & standards. The Associate agrees to carry out all work professionally, honestly, and to the standards expected by the Platform and its customers.\n\n"
        . "3. Insurance & compliance. The Associate confirms the information and documents provided (including DBS certificate and references) are true, and agrees to hold appropriate insurance where required.\n\n"
        . "4. Verification. The Associate agrees to the Platform verifying their identity, DBS status, and references.\n\n"
        . "5. Payments. Payments are handled securely through the Platform, with any applicable charges disclosed before payment.\n\n"
        . "6. Data. The Associate consents to the Platform storing the information provided for the purposes of running the service, in line with the Privacy Policy.\n\n"
        . "By typing my name and title and ticking the box, I confirm I have read and agree to these Terms of Association.";
}

/**
 * Short version fingerprint of the current Terms, recorded at signing time so
 * you can tell which wording a cleaner agreed to.
 */
function gb_get_terms_of_association_version() {
    return substr( md5( (string) gb_get_terms_of_association() ), 0, 8 );
}

function gb_load_assets() {
    $style_version = wp_get_theme()->get( 'Version' );
    $style_path = get_stylesheet_directory() . '/style.css';
    if ( file_exists( $style_path ) ) {
        $style_version = (string) filemtime( $style_path );
    }

    $nav_version = '1.2';
    $nav_path = get_template_directory() . '/nav.js';
    if ( file_exists( $nav_path ) ) {
        $nav_version = (string) filemtime( $nav_path );
    }

    // 1. Load Main Styles with file-based cache busting.
    wp_enqueue_style( 'gb-main-style', get_stylesheet_uri(), array(), $style_version );
    
    // 2. Dashboard icons are not needed by public visitors.
    if ( is_user_logged_in() ) {
        wp_enqueue_style( 'dashicons' );
    }

    // 3. Load Navigation Logic
    wp_enqueue_script( 'gb-nav-js', get_template_directory_uri() . '/nav.js', array(), $nav_version, true );
    // Nothing on the page waits for it, so let the parser carry on.
    wp_script_add_data( 'gb-nav-js', 'strategy', 'defer' );

    // 4. Pass Data to JS (Useful for AJAX)
    wp_localize_script( 'gb-nav-js', 'gbData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'site_url' => home_url()
    ));
}
add_action( 'wp_enqueue_scripts', 'gb_load_assets' );

/**
 * Drop WordPress' block editor CSS on the front end.
 *
 * Every page carried about 14KB of it inline - the block library, the classic
 * theme shim, and the generated global-styles custom properties. The theme
 * renders no blocks at all: the manager dashboard edits with the classic
 * editor, and a crawl of the live site found not one wp-block-* class on any
 * page. It is dropped per request rather than unconditionally, so the moment
 * a post does contain blocks the CSS comes back with it.
 */
function gb_dequeue_unused_block_styles() {
    if ( is_admin() ) {
        return;
    }

    $post = get_queried_object();
    if ( $post instanceof WP_Post && function_exists( 'has_blocks' ) && has_blocks( $post ) ) {
        return;
    }

    foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles' ) as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }

    // The generated :root custom properties, the largest of the four.
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
    remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
}
add_action( 'wp_enqueue_scripts', 'gb_dequeue_unused_block_styles', 100 );

/**
 * Provide a useful search snippet when an editor has not supplied one.
 */
/**
 * Search snippets, written per page.
 *
 * The generic fallback below takes the first 155 characters of a page's
 * content, which most of these pages do not have - their words live in
 * template fields - so Google was left to invent a snippet from whatever it
 * found. These are the starting point only: anything set by hand in the
 * dashboard wins, and the migration never overwrites one.
 */
function gb_get_default_meta_descriptions() {
    return array(
        'what-we-do'       => 'Weekly, fortnightly, deep and end-of-tenancy cleaning across Merseyside, using plant-based, non-toxic products. Find a vetted local cleaner.',
        'how-it-works'     => 'Search by postcode, compare vetted local cleaners, and book in a few minutes. Every cleaner is ID-checked and reference-checked before going live.',
        'how-we-do-it'     => 'How The Green Broom vets its cleaners, why we insist on eco-friendly products, and what our verification badge actually means.',
        'cleaner-listings' => 'Browse vetted eco-friendly cleaners near you. Compare rates, services and reviews, then book directly with the cleaner you choose.',
        'eco-products'     => 'Free guides to eco-friendly cleaning: plant-based products worth buying, and how to make effective cleaners from four everyday ingredients.',
        'join'             => 'Join The Green Broom as a self-employed cleaner. Set your own rates, choose your own hours, and reach customers across Merseyside.',
        'contact'          => 'Get in touch with The Green Broom. Call, email or send us a message, and we will come back to you Monday to Friday, 9am to 5pm.',
        'contact-support'  => 'Contact The Green Broom support team about a booking, a payment or your account.',
        'book-now'         => 'Book an eco-friendly cleaner in Merseyside. Choose your cleaner, pick a time that suits you, and pay securely online.',
        'blog'             => 'Practical advice on eco-friendly cleaning, recycling and greener living at home, from The Green Broom.',
        'cookies'          => 'What cookies The Green Broom uses, what each one does, and how to change your choice at any time.',
        'privacy'          => 'How The Green Broom collects, stores and uses your personal data, and the rights you have over it under UK GDPR.',
        'terms'            => 'The terms that apply when you use The Green Broom to find, book or provide cleaning services.',
        'gdpr'             => 'Your data protection rights under UK GDPR, and how to exercise them with The Green Broom.',
        'subscribers-terms-of-association' => 'The terms of association for Green Broom members, covering membership, member benefits and the arrangement between members and cleaners.',
    );
}

/**
 * Sign-in, account and confirmation pages have no business in search results:
 * they are either private, single-use, or a duplicate of the login screen.
 * Without this they were all indexable, and several shared one title.
 */
function gb_noindex_utility_pages() {
    if ( is_admin() || is_feed() ) {
        return;
    }

    $noindex = false;

    if ( is_page() ) {
        $slug = get_post_field( 'post_name', get_queried_object_id() );
        $noindex = in_array( $slug, array(
            'login', 'register', 'lost-password', 'my-account', 'settings',
            'inbox', 'schedule', 'booking-success', 'verify-profile',
            'edit-profile', 'cleaner-dashboard', 'manager-dashboard',
            'manage-listings', 'edit-listing',
        ), true );
    }

    if ( is_search() || is_404() ) {
        $noindex = true;
    }

    if ( $noindex ) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}
add_action( 'wp_head', 'gb_noindex_utility_pages', 1 );

/**
 * Modern-format variants for an uploaded image.
 *
 * Returns the AVIF/WebP siblings that actually exist on disk next to the
 * original, so a <picture> can offer them and browsers that cannot use them
 * still get the JPEG. Nothing is generated at request time: if a variant has
 * not been produced for an image, this returns nothing and the original is
 * served exactly as before. That is what makes it safe when the manager
 * uploads a new photograph.
 */
function gb_modern_image_variants( $url ) {
    $variants = array();
    $url = strtok( (string) $url, '?' );
    if ( $url === '' ) {
        return $variants;
    }

    $uploads = wp_get_upload_dir();
    if ( empty( $uploads['baseurl'] ) || strpos( $url, $uploads['baseurl'] ) !== 0 ) {
        return $variants;
    }

    $relative = substr( $url, strlen( $uploads['baseurl'] ) );
    $base     = preg_replace( '/\.(jpe?g|png)$/i', '', $relative );
    if ( $base === null || $base === $relative ) {
        return $variants;
    }

    // AVIF first: where both exist it has been chosen as the smaller of the two.
    foreach ( array( 'image/avif' => 'avif', 'image/webp' => 'webp' ) as $mime => $ext ) {
        if ( file_exists( $uploads['basedir'] . $base . '.' . $ext ) ) {
            $variants[ $mime ] = $uploads['baseurl'] . $base . '.' . $ext;
        }
    }

    return $variants;
}

/**
 * <source> elements for an image, optionally scoped to a media query.
 * Prints nothing when no variant exists.
 */
function gb_picture_sources( $url, $media = '' ) {
    foreach ( gb_modern_image_variants( $url ) as $mime => $variant_url ) {
        printf(
            '<source%s type="%s" srcset="%s">',
            $media !== '' ? ' media="' . esc_attr( $media ) . '"' : '',
            esc_attr( $mime ),
            esc_url( $variant_url )
        );
    }
}

/**
 * Offer AVIF/WebP for attachment images across the site.
 *
 * Wraps the <img> WordPress produces in a <picture> and adds a source per
 * available format. The whole srcset is translated, not just the default src:
 * a <picture> source wins outright for a matching browser, so offering a single
 * small URL would hand a large screen a smaller image than the srcset would
 * have. A format is only offered when EVERY candidate in the srcset has a
 * variant on disk, which keeps the responsive behaviour identical.
 */
function gb_attachment_image_modern_formats( $html, $attachment_id = 0, $size = '', $icon = false, $attr = array() ) {
    if ( is_admin() || $html === '' || strpos( $html, '<img' ) === false ) {
        return $html;
    }

    // Already inside a <picture> from a template that handled it itself.
    if ( strpos( $html, '<picture' ) !== false ) {
        return $html;
    }

    if ( ! preg_match( '/\ssrcset="([^"]+)"/i', $html, $m ) ) {
        if ( ! preg_match( '/\ssrc="([^"]+)"/i', $html, $m ) ) {
            return $html;
        }
        $candidates = array( array( $m[1], '' ) );
    } else {
        $candidates = array();
        foreach ( explode( ',', $m[1] ) as $part ) {
            $part = trim( $part );
            if ( $part === '' ) {
                continue;
            }
            $bits = preg_split( '/\s+/', $part, 2 );
            $candidates[] = array( $bits[0], isset( $bits[1] ) ? $bits[1] : '' );
        }
    }

    if ( empty( $candidates ) ) {
        return $html;
    }

    $by_mime = array();
    foreach ( $candidates as $candidate ) {
        $variants = gb_modern_image_variants( $candidate[0] );
        foreach ( array( 'image/avif', 'image/webp' ) as $mime ) {
            if ( isset( $variants[ $mime ] ) ) {
                $by_mime[ $mime ][] = trim( $variants[ $mime ] . ' ' . $candidate[1] );
            }
        }
    }

    $sources = '';
    $sizes   = preg_match( '/\ssizes="([^"]+)"/i', $html, $sm ) ? $sm[1] : '';
    foreach ( array( 'image/avif', 'image/webp' ) as $mime ) {
        // Every candidate must have converted, or the responsive set is lossy.
        if ( ! isset( $by_mime[ $mime ] ) || count( $by_mime[ $mime ] ) !== count( $candidates ) ) {
            continue;
        }
        $sources .= sprintf(
            '<source type="%s" srcset="%s"%s>',
            esc_attr( $mime ),
            esc_attr( implode( ', ', $by_mime[ $mime ] ) ),
            $sizes !== '' ? ' sizes="' . esc_attr( $sizes ) . '"' : ''
        );
    }

    if ( $sources === '' ) {
        return $html;
    }

    return '<picture>' . $sources . $html . '</picture>';
}
add_filter( 'wp_get_attachment_image', 'gb_attachment_image_modern_formats', 10, 5 );

/**
 * How long the verification audit trail is kept.
 *
 * Six years by default, matching the limitation period for a contract claim
 * in England and Wales - the outside window in which this record could still
 * be needed to answer for a verification decision.
 */
function gb_get_audit_retention_years() {
    $settings_id = function_exists( 'gb_get_site_settings_id' ) ? gb_get_site_settings_id() : (int) get_option( 'page_on_front' );
    $years = $settings_id ? (int) get_post_meta( $settings_id, '_gb_audit_retention_years', true ) : 0;

    if ( $years < 1 || $years > 25 ) {
        $years = 6;
    }

    return $years;
}

/**
 * Erase audit trails that have outlived the retention period.
 *
 * The clock runs from the END OF THE RELATIONSHIP, not from each entry: while
 * a cleaner is live on the platform the record of why they were verified is
 * still doing its job, and storage limitation does not require deleting
 * something you currently need. Only once a profile has been taken down does
 * the countdown begin, measured from that cleaner's last recorded event.
 *
 * A tombstone is left behind. Deleting a record and leaving nothing looks
 * identical to never having kept one, or to losing it - the tombstone shows
 * the erasure was deliberate, policy-driven and dated.
 */
function gb_prune_verification_logs() {
    if ( ! class_exists( 'GB_Verification_Log' ) ) {
        return;
    }

    $years  = gb_get_audit_retention_years();
    $cutoff = strtotime( '-' . $years . ' years' );
    if ( ! $cutoff ) {
        return;
    }

    $cleaners = get_posts( array(
        'post_type'      => 'gb_cleaner',
        // Live profiles are excluded entirely: the relationship is ongoing.
        'post_status'    => array( 'draft', 'trash' ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    foreach ( $cleaners as $cleaner_id ) {
        $log = GB_Verification_Log::get( $cleaner_id );
        if ( empty( $log ) ) {
            continue;
        }

        // Already a tombstone: nothing left to erase.
        if ( count( $log ) === 1 && isset( $log[0]['event'] ) && $log[0]['event'] === 'retention_erased' ) {
            continue;
        }

        $last = 0;
        $first = '';
        foreach ( $log as $entry ) {
            $stamp = isset( $entry['at_gmt'] ) ? strtotime( $entry['at_gmt'] ) : 0;
            if ( $stamp && $stamp > $last ) {
                $last = $stamp;
            }
            if ( $first === '' && ! empty( $entry['at'] ) ) {
                $first = (string) $entry['at'];
            }
        }

        if ( ! $last || $last > $cutoff ) {
            continue;
        }

        $tombstone = array(
            array(
                'event'    => 'retention_erased',
                'at'       => current_time( 'mysql' ),
                'at_gmt'   => current_time( 'mysql', true ),
                'actor_id' => 0,
                'actor'    => 'System (retention policy)',
                'detail'   => array(
                    'policy'         => $years . ' years after the profile was taken down',
                    'entries_erased' => (string) count( $log ),
                    'covered_from'   => $first,
                    'covered_to'     => gmdate( 'Y-m-d H:i:s', $last ),
                ),
            ),
        );

        update_post_meta( $cleaner_id, '_gb_verification_log', $tombstone );
    }
}
add_action( 'gb_prune_verification_logs', 'gb_prune_verification_logs' );

function gb_schedule_verification_log_pruning() {
    if ( ! wp_next_scheduled( 'gb_prune_verification_logs' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gb_prune_verification_logs' );
    }
}
add_action( 'init', 'gb_schedule_verification_log_pruning', 30 );

/**
 * Seed a baseline entry for cleaners verified before the log existed.
 *
 * Without this the trail starts today and says nothing about anyone already
 * approved, which is precisely the gap the log is meant to close. The entry is
 * explicitly labelled as reconstructed from the records that exist - it does
 * not claim to be a contemporaneous account of a check nobody logged.
 */
function gb_seed_verification_log_baseline() {
    if ( get_option( 'gb_verification_log_baseline' ) || ! class_exists( 'GB_Verification_Log' ) ) {
        return;
    }

    $cleaners = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'post_status'    => array( 'publish', 'pending', 'draft' ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    foreach ( $cleaners as $cleaner_id ) {
        if ( GB_Verification_Log::get( $cleaner_id ) ) {
            continue;
        }

        $checks = function_exists( 'gb_get_cleaner_checks' ) ? gb_get_cleaner_checks( $cleaner_id ) : array();
        $passed = array();
        foreach ( array( 'id' => 'Identity', 'dbs' => 'Basic DBS certificate', 'refs' => 'Two references' ) as $key => $label ) {
            if ( ! empty( $checks[ $key ] ) ) {
                $passed[] = $label;
            }
        }

        $verified_date = function_exists( 'gb_get_cleaner_verified_date' ) ? gb_get_cleaner_verified_date( $cleaner_id ) : '';

        GB_Verification_Log::record( $cleaner_id, 'baseline', array(
            'note'           => 'Position as at the date this log was introduced, reconstructed from existing records. Not a contemporaneous account of the checks themselves.',
            'checks_passed'  => $passed ? implode( ', ', $passed ) : 'None recorded',
            'profile_status' => get_post_status( $cleaner_id ),
            'verified_date'  => $verified_date ? $verified_date : 'Not recorded',
        ) );
    }

    update_option( 'gb_verification_log_baseline', 1 );
}

/**
 * Export a cleaner's verification audit trail as CSV.
 *
 * The point of the log is to be producible on request - to an insurer, a
 * customer's solicitor, or the ICO. Reading it off a screen is not that, so it
 * comes out as a file. Manager-only, nonce-signed, and it contains no identity
 * data: what was checked, when, by whom, and each document's fingerprint.
 */
function gb_export_verification_log() {
    if ( ! isset( $_GET['gb_action'] ) || $_GET['gb_action'] !== 'export_verification_log' ) {
        return;
    }

    $cleaner_id = isset( $_GET['cleaner'] ) ? (int) $_GET['cleaner'] : 0;

    if (
        $cleaner_id < 1
        || get_post_type( $cleaner_id ) !== 'gb_cleaner'
        || ! class_exists( 'GB_Verification_Log' )
        || ! class_exists( 'GB_Uploads' )
        || ! GB_Uploads::user_can_manage_verification_documents()
        || ! isset( $_GET['_wpnonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gb_export_verification_log_' . $cleaner_id )
    ) {
        wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
    }

    $cleaner  = get_the_title( $cleaner_id );
    $filename = 'verification-log-' . sanitize_title( $cleaner ) . '-' . gmdate( 'Y-m-d' ) . '.csv';

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

    $out = fopen( 'php://output', 'w' );

    fputcsv( $out, array( 'The Green Broom - verification audit trail' ) );
    fputcsv( $out, array( 'Cleaner', $cleaner ) );
    fputcsv( $out, array( 'Profile ID', $cleaner_id ) );
    fputcsv( $out, array( 'Exported', current_time( 'mysql' ) ) );
    fputcsv( $out, array( 'Exported by', wp_get_current_user()->user_email ) );
    fputcsv( $out, array() );
    fputcsv( $out, array( 'Date (local)', 'Date (UTC)', 'Event', 'Details', 'Carried out by' ) );

    foreach ( GB_Verification_Log::get( $cleaner_id ) as $entry ) {
        $details = array();
        foreach ( (array) $entry['detail'] as $key => $value ) {
            if ( $value === '' ) {
                continue;
            }
            $details[] = ucfirst( str_replace( '_', ' ', $key ) ) . ': ' . $value;
        }

        fputcsv( $out, array(
            isset( $entry['at'] ) ? $entry['at'] : '',
            isset( $entry['at_gmt'] ) ? $entry['at_gmt'] : '',
            GB_Verification_Log::describe( $entry['event'] ),
            implode( ' | ', $details ),
            isset( $entry['actor'] ) ? $entry['actor'] : '',
        ) );
    }

    fclose( $out );
    exit;
}
add_action( 'template_redirect', 'gb_export_verification_log', 1 );

/**
 * Open Graph and Twitter Card tags.
 *
 * Without these, a link shared on Facebook, WhatsApp, LinkedIn or a group chat
 * renders as a bare URL with no title, no description and no picture. That
 * matters more here than on most sites, because the cleaner listings page is
 * built as a landing page for paid social - the exact traffic that arrives via
 * a shared link.
 *
 * The description reuses whatever the search snippet resolves to, so there is
 * one thing to edit rather than two.
 */
function gb_output_social_tags() {
    if ( is_admin() || is_feed() || is_robots() ) {
        return;
    }

    $title = wp_get_document_title();
    $url   = home_url( add_query_arg( array() ) );
    $url   = strtok( $url, '?' );
    $type  = is_singular( 'post' ) ? 'article' : 'website';

    $description = gb_resolve_meta_description();

    $image = '';
    if ( is_singular() && has_post_thumbnail() ) {
        $image = (string) get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
    }
    if ( $image === '' ) {
        $settings_id = function_exists( 'gb_get_site_settings_id' ) ? gb_get_site_settings_id() : (int) get_option( 'page_on_front' );
        $image = $settings_id ? trim( (string) get_post_meta( $settings_id, '_gb_social_share_image', true ) ) : '';
        if ( $image === '' && $settings_id ) {
            // The home page hero is the one image guaranteed to exist and to
            // look like the business rather than a logo on white.
            $image = trim( (string) get_post_meta( $settings_id, '_gb_hero_bg', true ) );
        }
    }

    printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
    printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
    printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
    printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
    printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( get_locale() ) );

    if ( $description !== '' ) {
        printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
    }
    if ( $image !== '' ) {
        printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
        printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );
    }

    printf( '<meta name="twitter:card" content="%s">' . "\n", $image !== '' ? 'summary_large_image' : 'summary' );
    printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
    if ( $description !== '' ) {
        printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );
    }
}
add_action( 'wp_head', 'gb_output_social_tags', 2 );

/**
 * Work out this page's description once, so the search snippet and the social
 * tags cannot drift apart.
 */
function gb_resolve_meta_description() {
    $description  = '';
    $descriptions = gb_get_default_meta_descriptions();

    if ( is_front_page() ) {
        $description = 'Find trusted eco-friendly cleaners across Merseyside and West Lancashire with The Green Broom.';
    } elseif ( is_home() || is_post_type_archive( 'post' ) || is_category() || gb_is_virtual_blog_request() ) {
        // The blog is a virtual route rather than a real page, so it never
        // reached the is_singular() branch and carried no snippet at all.
        $description = $descriptions['blog'];
    } elseif ( is_singular() ) {
        $post_id     = (int) get_queried_object_id();
        $description = (string) get_post_meta( $post_id, '_gb_seo_meta_desc', true );
        if ( $description === '' ) {
            $description = get_the_excerpt( $post_id );
        }
        if ( $description === '' ) {
            $description = get_post_field( 'post_content', $post_id );
        }
        // A cleaner who has not written a bio still deserves a snippet.
        if ( $description === '' && get_post_type( $post_id ) === 'gb_cleaner' ) {
            $location = gb_get_cleaner_location( $post_id );
            $description = sprintf(
                '%s is an eco-friendly cleaner%s on The Green Broom. View their services, rates and reviews, and book directly.',
                get_the_title( $post_id ),
                $location !== '' ? ' in ' . $location : ''
            );
        }
    }

    $description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
    if ( $description === '' ) {
        return '';
    }

    return function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 155 ) : substr( $description, 0, 155 );
}

/**
 * Provide a useful search snippet when an editor has not supplied one.
 */
function gb_output_meta_description() {
    if ( is_admin() || is_feed() || is_robots() ) {
        return;
    }

    $description = gb_resolve_meta_description();
    if ( $description === '' ) {
        return;
    }

    echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
}
add_action( 'wp_head', 'gb_output_meta_description', 1 );

/**
 * Modern browsers render the site's emoji natively, so visitors do not need
 * WordPress' compatibility detector, worker, or remote icon requests.
 */
function gb_disable_public_emoji_assets() {
    if ( is_admin() ) {
        return;
    }

    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_head', 'wp_print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
}
add_action( 'wp', 'gb_disable_public_emoji_assets', 1 );

function gb_send_security_headers() {
    if ( headers_sent() ) {
        return;
    }

    $is_login_request = isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php';
    if ( is_admin() && ! $is_login_request ) {
        return;
    }

    header( 'X-Content-Type-Options: nosniff', true );
    header( 'Referrer-Policy: strict-origin-when-cross-origin', true );
    header( 'X-Frame-Options: SAMEORIGIN', true );
    header( 'Permissions-Policy: camera=(), geolocation=(), microphone=()', true );
    header( 'X-Permitted-Cross-Domain-Policies: none', true );
    header( "Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://checkout.stripe.com https://connect.stripe.com;", true );

    if ( is_ssl() ) {
        header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains', true );
    }
}
add_action( 'send_headers', 'gb_send_security_headers' );
add_action( 'login_init', 'gb_send_security_headers' );

function gb_get_request_ip() {
    $candidates = [];
    foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ] as $key ) {
        if ( empty( $_SERVER[ $key ] ) ) {
            continue;
        }

        $parts = array_map( 'trim', explode( ',', (string) wp_unslash( $_SERVER[ $key ] ) ) );
        foreach ( $parts as $part ) {
            if ( filter_var( $part, FILTER_VALIDATE_IP ) ) {
                $candidates[] = $part;
            }
        }
    }

    if ( empty( $candidates ) ) {
        return '0.0.0.0';
    }

    foreach ( $candidates as $candidate ) {
        if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return $candidate;
        }
    }

    return $candidates[0];
}

function gb_get_rate_limit_storage_key( $action, $identity = '' ) {
    $action = sanitize_key( (string) $action );
    $identity = strtolower( trim( (string) $identity ) );

    return 'gb_rl_' . md5( $action . '|' . $identity . '|' . gb_get_request_ip() );
}

function gb_get_rate_limit_state( $action, $identity = '' ) {
    $state = get_transient( gb_get_rate_limit_storage_key( $action, $identity ) );
    if ( ! is_array( $state ) ) {
        return [
            'count'    => 0,
            'reset_at' => 0,
        ];
    }

    return [
        'count'    => isset( $state['count'] ) ? max( 0, (int) $state['count'] ) : 0,
        'reset_at' => isset( $state['reset_at'] ) ? max( 0, (int) $state['reset_at'] ) : 0,
    ];
}

function gb_rate_limit_check( $action, $limit = 5, $window = 900, $identity = '' ) {
    $limit = max( 1, (int) $limit );
    $window = max( 60, (int) $window );
    $state = gb_get_rate_limit_state( $action, $identity );
    $now = time();

    if ( $state['reset_at'] <= $now ) {
        gb_reset_rate_limit( $action, $identity );

        return [
            'blocked'     => false,
            'retry_after' => 0,
            'count'       => 0,
        ];
    }

    $blocked = $state['count'] >= $limit;

    return [
        'blocked'     => $blocked,
        'retry_after' => $blocked ? max( 1, $state['reset_at'] - $now ) : 0,
        'count'       => $state['count'],
    ];
}

function gb_record_rate_limit_hit( $action, $window = 900, $identity = '' ) {
    $window = max( 60, (int) $window );
    $state = gb_get_rate_limit_state( $action, $identity );
    $now = time();

    if ( $state['reset_at'] <= $now ) {
        $state = [
            'count'    => 0,
            'reset_at' => $now + $window,
        ];
    }

    $state['count']++;
    set_transient( gb_get_rate_limit_storage_key( $action, $identity ), $state, max( 1, $state['reset_at'] - $now ) );

    return $state;
}

function gb_reset_rate_limit( $action, $identity = '' ) {
    delete_transient( gb_get_rate_limit_storage_key( $action, $identity ) );
}

function gb_get_rate_limit_message( $state, $default = 'Too many attempts. Please try again shortly.' ) {
    if ( ! is_array( $state ) || empty( $state['blocked'] ) ) {
        return $default;
    }

    $retry_after = isset( $state['retry_after'] ) ? max( 1, (int) $state['retry_after'] ) : 60;
    if ( $retry_after < 90 ) {
        return 'Too many attempts. Please wait about a minute and try again.';
    }

    return sprintf( 'Too many attempts. Please wait about %d minutes and try again.', (int) ceil( $retry_after / 60 ) );
}

/* ==========================================================================
   The rest of the theme's PHP, split by subject.

   functions.php had reached 6,642 lines - about a third of the whole codebase
   in one file. Each of these was lifted out verbatim; none of the code inside
   them was changed in the move.
   ========================================================================== */
require_once get_template_directory() . '/inc/anti-spam.php';
require_once get_template_directory() . '/inc/cleaner-profiles.php';
require_once get_template_directory() . '/inc/roles.php';
require_once get_template_directory() . '/inc/post-types.php';
require_once get_template_directory() . '/inc/bookings.php';
require_once get_template_directory() . '/inc/messaging.php';
require_once get_template_directory() . '/inc/helpdesk.php';
require_once get_template_directory() . '/inc/stripe.php';
require_once get_template_directory() . '/inc/cleaner-availability.php';
require_once get_template_directory() . '/inc/cleaner-stats.php';
require_once get_template_directory() . '/inc/pricing-panels.php';
require_once get_template_directory() . '/inc/stripe-subscriptions.php';
require_once get_template_directory() . '/inc/membership-checkout.php';

/* ==========================================================================
   ONE-TIME MIGRATIONS

   These seed pages, correct stored content and backfill records. Each has run
   already and each carries its own guard, so re-running one is a no-op - but
   they were individually hooked to init, so every page request was calling
   nineteen finished migrations just to have them read a flag and return.

   They now run behind a single version check: one option read per request
   instead of nineteen, and nothing is called at all once the version matches.
   Adding a migration means appending it here and bumping the version, which
   also makes the run order explicit rather than an accident of hook priority.
   ========================================================================== */

const GB_MIGRATION_VERSION = 12;

function gb_run_one_time_migrations() {
    if ( (int) get_option( 'gb_migration_version', 0 ) >= GB_MIGRATION_VERSION ) {
        return;
    }

    $migrations = array(
        'gb_ensure_join_page',
        'gb_ensure_eco_products_page',
        'gb_ensure_subscriber_terms_page',
        'gb_ensure_cookie_policy_page',
        'gb_remove_platform_fee_setting',
        'gb_migrate_mission_title',
        'gb_migrate_what_cleaners_do',
        'gb_migrate_join_links',
        'gb_migrate_cleaner_rates',
        'gb_ensure_legal_pages',
        'gb_seed_legal_page_content',
        'gb_apply_contact_defaults',
        'gb_restore_terms_page_content',
        'gb_apply_content_audit_fixes',
        'gb_ensure_legacy_blog_featured_images',
        'gb_seed_verification_log_baseline',
        'gb_migrate_eco_guides',
        'gb_retire_standalone_cookie_page',
        'gb_migrate_eco_guides_to_pages',
        'gb_apply_client_copy_corrections',
        'gb_correct_cleanco_location',
        'gb_align_join_cta_with_pricing',
        'gb_set_cleanco_location',
        'gb_reconcile_cleaner_locations',
        'gb_apply_products_supplied_wording',
        'gb_ensure_green_broom_standard_page',
        'gb_clear_join_testimonial_meta',
        'gb_clear_home_secondary_cta',
        'gb_update_listings_empty_state',
        'gb_reorder_home_feature_cards',
        'gb_trim_panel_and_listing_wording',
        'gb_apply_structure_changes_2026_09'
    );

    foreach ( $migrations as $migration ) {
        if ( function_exists( $migration ) ) {
            $migration();
        }
    }

    update_option( 'gb_migration_version', GB_MIGRATION_VERSION );
}
add_action( 'init', 'gb_run_one_time_migrations', 9 );
