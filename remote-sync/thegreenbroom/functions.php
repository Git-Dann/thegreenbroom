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
add_action( 'init', 'gb_ensure_join_page' );

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
add_action( 'init', 'gb_ensure_eco_products_page' );

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
add_action( 'init', 'gb_ensure_subscriber_terms_page' );

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
add_action( 'init', 'gb_ensure_cookie_policy_page' );

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
add_action( 'init', 'gb_seed_legal_page_content', 20 );

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
add_action( 'init', 'gb_restore_terms_page_content', 21 );

/**
 * Audit fixes, one pass, each guarded so it cannot run twice.
 *
 * Found by crawling the live site rather than reading the code, which is the
 * only way these show up: in every case the template was already right and the
 * saved value in the database was stale.
 */
function gb_apply_content_audit_fixes() {
    if ( get_option( 'gb_content_audit_v1' ) ) {
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

    update_option( 'gb_content_audit_v1', 1 );
}
add_action( 'init', 'gb_apply_content_audit_fixes', 22 );

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
add_action( 'init', 'gb_ensure_legacy_blog_featured_images', 25 );

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
add_action( 'init', 'gb_apply_contact_defaults', 20 );

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
add_action( 'init', 'gb_remove_platform_fee_setting' );

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
add_action( 'init', 'gb_migrate_mission_title' );

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
add_action( 'init', 'gb_migrate_what_cleaners_do' );

require_once get_template_directory() . '/inc/eco-guide-content.php';
require_once get_template_directory() . '/inc/cookie-consent.php';
require_once get_template_directory() . '/inc/legal-content.php';

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
add_action( 'init', 'gb_migrate_eco_guides', 30 );

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
add_action( 'init', 'gb_migrate_eco_guides_to_pages', 31 );

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
add_action( 'init', 'gb_migrate_join_links' );

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
add_action( 'init', 'gb_migrate_cleaner_rates' );

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
add_action( 'init', 'gb_ensure_legal_pages' );

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
add_action( 'init', 'gb_retire_standalone_cookie_page', 30 );

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

    // 4. Pass Data to JS (Useful for AJAX)
    wp_localize_script( 'gb-nav-js', 'gbData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'site_url' => home_url()
    ));
}
add_action( 'wp_enqueue_scripts', 'gb_load_assets' );

/**
 * Provide a useful search snippet when an editor has not supplied one.
 */
function gb_output_meta_description() {
    if ( is_admin() || is_feed() || is_robots() ) {
        return;
    }

    $description = '';
    if ( is_front_page() ) {
        $description = 'Find trusted eco-friendly cleaners across Merseyside and West Lancashire with The Green Broom.';
    } elseif ( is_singular() ) {
        $post_id     = (int) get_queried_object_id();
        $description = (string) get_post_meta( $post_id, '_gb_seo_meta_desc', true );
        if ( $description === '' ) {
            $description = get_the_excerpt( $post_id );
        }
        if ( $description === '' ) {
            $description = get_post_field( 'post_content', $post_id );
        }
    }

    $description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
    if ( $description === '' ) {
        return;
    }

    $description = function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 155 ) : substr( $description, 0, 155 );
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
   ANTI-SPAM FOR PUBLIC MESSAGE FORMS
   ==========================================================================
   The honeypot + rate limit alone were not enough: the limiter is keyed on
   (action | email | IP), so rotating either resets it, and the old limit of 5
   per hour is exactly why identical spam appeared in the helpdesk five times
   in a row. These helpers add the missing layers — a timing trap, duplicate
   suppression, and content scoring — and are shared by every public message
   entry point (contact/helpdesk form and the cleaner enquiry AJAX handler).
   ========================================================================== */

/**
 * Signed timestamp embedded in public forms. Lets us reject submissions that
 * arrive implausibly fast (bots post instantly) or from very stale pages,
 * without storing per-visitor state.
 */
function gb_form_timing_token() {
    $ts = time();
    return $ts . '|' . hash_hmac( 'sha256', (string) $ts, wp_salt( 'nonce' ) );
}

/**
 * Returns the age of a timing token in seconds, or null if missing/forged.
 */
function gb_form_timing_age( $token ) {
    $token = (string) $token;
    if ( strpos( $token, '|' ) === false ) {
        return null;
    }
    list( $ts, $sig ) = array_map( 'trim', explode( '|', $token, 2 ) );
    if ( ! ctype_digit( $ts ) ) {
        return null;
    }
    $expected = hash_hmac( 'sha256', $ts, wp_salt( 'nonce' ) );
    if ( ! hash_equals( $expected, $sig ) ) {
        return null;
    }

    return time() - (int) $ts;
}

/**
 * True only for the unambiguous bot case: a VALID token submitted faster than a
 * human could fill the form.
 *
 * Deliberately lenient about a missing, forged or stale token — the contact
 * page can be served from a full-page cache, which would hand every visitor the
 * same (eventually old) token, and silently swallowing a genuine enquiry is far
 * worse than letting one through to the rate-limit/duplicate/content checks.
 */
function gb_form_timing_too_fast( $token, $min_seconds = 3 ) {
    $age = gb_form_timing_age( $token );
    if ( $age === null ) {
        return false;
    }

    return $age < (int) $min_seconds;
}

/**
 * Suppress identical resubmissions. Spam bots replay the same payload many
 * times; genuine senders effectively never do within the window.
 */
function gb_message_is_duplicate( $email, $subject, $message, $window = DAY_IN_SECONDS ) {
    $fingerprint = md5( strtolower( trim( (string) $email ) ) . '|' . strtolower( trim( (string) $subject ) ) . '|' . strtolower( trim( (string) $message ) ) );
    $key = 'gb_msgdupe_' . $fingerprint;

    if ( get_transient( $key ) ) {
        return true;
    }

    set_transient( $key, 1, max( 60, (int) $window ) );

    return false;
}

/**
 * Heuristic spam score for a public message. Higher is worse; anything at or
 * above the threshold used by callers (6) is treated as spam.
 *
 * Tuned for a cleaning marketplace: genuine enquiries are short, plain-text
 * and almost never contain links, so links and promotional keywords carry the
 * most weight. A single link on its own is NOT enough to block (a cleaner may
 * legitimately paste a Stripe onboarding URL).
 */
function gb_message_spam_score( $name, $email, $subject, $message ) {
    $score = 0;
    $subject = (string) $subject;
    $message = (string) $message;
    $haystack = strtolower( $name . ' ' . $subject . ' ' . $message );

    // Links: the single strongest signal on this form.
    $link_count = preg_match_all( '#(https?://|www\.)#i', $subject . ' ' . $message );
    if ( $link_count > 0 ) {
        $score += 3;
    }
    if ( $link_count > 1 ) {
        $score += 3 * min( $link_count - 1, 3 );
    }

    // BBCode / anchor markup is never used by real senders here.
    if ( preg_match( '#\[url|\[/url\]|<a\s+href#i', $subject . ' ' . $message ) ) {
        $score += 5;
    }

    // Promotional / scam vocabulary.
    $keywords = [
        'playstation', 'xbox', 'nintendo', 'giveaway', 'sweepstake', 'prize', 'jackpot',
        'casino', 'betting', 'crypto', 'bitcoin', 'forex', 'viagra', 'cialis', 'porn',
        'seo', 'seo boost', 'seo service', 'backlink', 'rank your website', 'rank higher',
        'web traffic', 'buy traffic', 'email list', 'b2b data', 'b2b leads', 'lead list',
        'database of', 'marketing list', 'telegra.ph', 'bit.ly', 'tinyurl', 'qrli',
        'cutt.ly', 'shorturl', 't.me/',
        'promo code', 'promotional code', 'voucher code', 'gift card', 'claim your',
        'you have won', 'congratulations', 'reviewing your website',
        'shutting down', 'final notice', 'act now', 'limited offer', 'work from home',
        'make money', 'earn money', 'investment opportunity', 'loan offer',
    ];
    foreach ( $keywords as $needle ) {
        if ( strpos( $haystack, $needle ) !== false ) {
            $score += 3;
        }
    }

    // Shouty subjects.
    $subject_letters = preg_replace( '/[^a-z]/i', '', $subject );
    if ( strlen( $subject_letters ) > 10 && strtoupper( $subject_letters ) === $subject_letters ) {
        $score += 3;
    }

    // Shouty bodies (e.g. "GET CRACKING WITH A $25,000 PROMO CODE").
    $message_letters = preg_replace( '/[^a-z]/i', '', $message );
    if ( strlen( $message_letters ) > 15 && strtoupper( $message_letters ) === $message_letters ) {
        $score += 4;
    }

    // Large money amounts are a lure, not a cleaning enquiry.
    if ( preg_match( '/[$£€]\s?\d{1,3}(?:,\d{3})+/u', $subject . ' ' . $message ) ) {
        $score += 3;
    }

    // Sender domains with no plausible relevance to a UK-only cleaning service.
    $domain = strtolower( substr( strrchr( (string) $email, '@' ) ?: '', 1 ) );
    if ( $domain !== '' && preg_match( '/\.(ru|su|cn|tk|top|xyz)$/', $domain ) ) {
        $score += 3;
    }

    // Non-Latin scripts are a reliable signal for this UK-only audience.
    if ( preg_match( '/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}]/u', $subject . ' ' . $message ) ) {
        $score += 5;
    }

    return $score;
}

/**
 * Single entry point used by the public forms.
 */
function gb_message_looks_like_spam( $name, $email, $subject, $message, $threshold = 6 ) {
    return gb_message_spam_score( $name, $email, $subject, $message ) >= (int) $threshold;
}

function gb_validate_account_password( $password ) {
    $password = (string) $password;
    if ( strlen( $password ) < 10 ) {
        return new WP_Error( 'gb_weak_password', 'Password must be at least 10 characters long.' );
    }

    if (
        ! preg_match( '/[a-z]/', $password ) ||
        ! preg_match( '/[A-Z]/', $password ) ||
        ! preg_match( '/\d/', $password )
    ) {
        return new WP_Error( 'gb_weak_password', 'Password must include upper and lower case letters plus a number.' );
    }

    return true;
}

function gb_get_cleaner_author_id( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
        return 0;
    }

    return (int) get_post_field( 'post_author', $cleaner_id );
}

function gb_sync_cleaner_verification_state( $cleaner_id, $post_status ) {
    $cleaner_id = (int) $cleaner_id;
    if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
        return;
    }

    $author_id = gb_get_cleaner_author_id( $cleaner_id );
    $is_verified = $post_status === 'publish';
    update_post_meta( $cleaner_id, 'is_verified', $is_verified ? '1' : '0' );

    // Stamp the date the badge was granted, once, so the profile can show
    // "Green Broom Verified - <date>" rather than an unqualified claim.
    if ( $is_verified && ! get_post_meta( $cleaner_id, '_gb_verified_date', true ) ) {
        update_post_meta( $cleaner_id, '_gb_verified_date', current_time( 'mysql' ) );
    }

    if ( $author_id < 1 ) {
        return;
    }

    update_user_meta( $author_id, 'gb_is_verified', $is_verified ? '1' : '0' );

    if ( $post_status === 'pending' ) {
        update_user_meta( $author_id, 'gb_verification_status', 'pending' );
        return;
    }

    delete_user_meta( $author_id, 'gb_verification_status' );
}

/**
 * When the Green Broom Verified badge was granted. Cleaners approved before
 * the date was recorded fall back to the date their profile went live, which
 * is the same moment - approval is what publishes them.
 */
function gb_get_cleaner_verified_date( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    if ( get_post_meta( $cleaner_id, 'is_verified', true ) !== '1' ) {
        return '';
    }
    $date = trim( (string) get_post_meta( $cleaner_id, '_gb_verified_date', true ) );
    if ( $date === '' ) {
        $date = get_post_field( 'post_date', $cleaner_id );
    }
    $ts = $date ? strtotime( $date ) : false;
    return $ts ? date_i18n( 'j F Y', $ts ) : '';
}

function gb_purge_cleaner_verification_documents( $cleaner_id, $fields = [] ) {
    if ( class_exists( 'GB_Uploads' ) && method_exists( 'GB_Uploads', 'purge_cleaner_verification_documents' ) ) {
        GB_Uploads::purge_cleaner_verification_documents( $cleaner_id, $fields );
    }
}

function gb_get_cleaner_verification_documents( $cleaner_id ) {
    if ( class_exists( 'GB_Uploads' ) && method_exists( 'GB_Uploads', 'get_cleaner_verification_documents' ) ) {
        return GB_Uploads::get_cleaner_verification_documents( $cleaner_id );
    }

    return [];
}

function gb_get_cleaner_verification_document_url( $cleaner_id, $field ) {
    if ( class_exists( 'GB_Uploads' ) && method_exists( 'GB_Uploads', 'get_verification_document_url' ) ) {
        return GB_Uploads::get_verification_document_url( $cleaner_id, $field );
    }

    return '';
}

function gb_get_site_settings_id() {
    return (int) get_option( 'page_on_front' );
}

/* ==========================================================================
   CLEANER REVIEWS: REAL STAR RATINGS
   ==========================================================================
   Reviews are WordPress comments on the gb_cleaner post. Previously the
   profile printed a hard-coded five stars on every review, which is both
   misleading and looks fake. A rating (1-5) is now captured with the review
   and stored as comment meta, and the profile shows the real per-review score
   plus a computed average.
   ========================================================================== */

function gb_get_cleaner_rating( $cleaner_id ) {
    $comments = get_comments( array(
        'post_id' => (int) $cleaner_id,
        'status'  => 'approve',
        'type'    => 'comment',
    ) );

    $sum = 0;
    $rated = 0;
    foreach ( $comments as $comment ) {
        $rating = (int) get_comment_meta( $comment->comment_ID, 'gb_rating', true );
        if ( $rating >= 1 && $rating <= 5 ) {
            $sum += $rating;
            $rated++;
        }
    }

    return array(
        'average' => $rated ? round( $sum / $rated, 1 ) : 0.0,
        'rated'   => $rated,
        'total'   => count( $comments ),
    );
}

function gb_refresh_cleaner_rating( $cleaner_id ) {
    $rating = gb_get_cleaner_rating( $cleaner_id );
    update_post_meta( $cleaner_id, '_gb_rating_avg', $rating['average'] );
    update_post_meta( $cleaner_id, '_gb_rating_count', $rating['rated'] );

    return $rating;
}

// Capture the star rating submitted alongside a review.
add_action( 'comment_post', function ( $comment_id, $approved, $commentdata ) {
    $post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
    if ( ! $post_id || get_post_type( $post_id ) !== 'gb_cleaner' ) {
        return;
    }

    $rating = isset( $_POST['gb_rating'] ) ? (int) $_POST['gb_rating'] : 0;
    if ( $rating >= 1 && $rating <= 5 ) {
        add_comment_meta( $comment_id, 'gb_rating', $rating, true );
    }

    gb_refresh_cleaner_rating( $post_id );
}, 10, 3 );

// Keep the average honest when a review is approved, unapproved or trashed.
add_action( 'wp_set_comment_status', function ( $comment_id, $status ) {
    $comment = get_comment( $comment_id );
    if ( $comment && get_post_type( $comment->comment_post_ID ) === 'gb_cleaner' ) {
        gb_refresh_cleaner_rating( (int) $comment->comment_post_ID );
    }
}, 10, 2 );

/**
 * Star markup for a 0-5 value.
 */
/**
 * Real customer reviews across every published cleaner, newest first.
 *
 * Deliberately returns only genuine approved comments carrying a 1-5 rating.
 * If a site has no reviews yet this returns an empty array and the caller must
 * render nothing - social proof that is invented is worse than none.
 *
 * @param int $limit    How many reviews to return.
 * @param int $min_stars Ignore anything below this, so a landing page is not
 *                       led by a one-star review. Set to 1 to include all.
 */
function gb_get_recent_reviews( $limit = 3, $min_stars = 4 ) {
    $cleaner_ids = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    if ( empty( $cleaner_ids ) ) {
        return array();
    }

    $comments = get_comments( array(
        'post__in' => $cleaner_ids,
        'status'   => 'approve',
        'type'     => 'comment',
        'orderby'  => 'comment_date_gmt',
        'order'    => 'DESC',
        'number'   => 60, // Scan a window, then filter by rating below.
    ) );

    $out = array();
    foreach ( $comments as $comment ) {
        $stars = (int) get_comment_meta( $comment->comment_ID, 'gb_rating', true );
        if ( $stars < max( 1, (int) $min_stars ) || $stars > 5 ) {
            continue;
        }
        $body = trim( wp_strip_all_tags( $comment->comment_content ) );
        if ( $body === '' ) {
            continue; // A bare star rating is not a testimonial.
        }

        $out[] = array(
            'stars'   => $stars,
            'body'    => $body,
            'author'  => trim( $comment->comment_author ) !== '' ? $comment->comment_author : 'Verified customer',
            'cleaner' => get_the_title( $comment->comment_post_ID ),
            'link'    => get_permalink( $comment->comment_post_ID ),
            'date'    => $comment->comment_date,
        );

        if ( count( $out ) >= (int) $limit ) {
            break;
        }
    }

    return $out;
}

/**
 * Platform-wide rating across every published cleaner, for a headline figure.
 */
function gb_get_platform_rating() {
    $cleaner_ids = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    $sum = 0;
    $rated = 0;
    foreach ( $cleaner_ids as $cleaner_id ) {
        $r = gb_get_cleaner_rating( $cleaner_id );
        if ( $r['rated'] > 0 ) {
            $sum += $r['average'] * $r['rated'];
            $rated += $r['rated'];
        }
    }

    return array(
        'average' => $rated ? round( $sum / $rated, 1 ) : 0.0,
        'count'   => $rated,
    );
}

function gb_render_stars( $value, $size = 14 ) {
    $value = (float) $value;
    $out = '<span class="gb-stars" style="font-size:' . (int) $size . 'px; letter-spacing:1px; white-space:nowrap;">';
    for ( $i = 1; $i <= 5; $i++ ) {
        $filled = $value >= ( $i - 0.25 );
        $out .= '<span style="color:' . ( $filled ? '#f59e0b' : '#d7dbe0' ) . ';">&#9733;</span>';
    }

    return $out . '</span>';
}

/**
 * The trust checks shown publicly on a cleaner profile.
 *
 * A cleaner cannot be approved until Government ID, proof of address and a DBS
 * certificate have all been supplied (gb_cleaner_verification_missing() hard
 * blocks it), and those documents are then permanently deleted for data
 * protection. So 'is_verified' is the durable record that those checks passed —
 * the badges must never be driven off the documents themselves, which are gone.
 */
/**
 * The three checks the Green Broom Verified badge stands on, per the client's
 * published rule: government ID, Basic DBS certificate, two character
 * references.
 *
 * Each is an explicit manager-set flag, so the badge reflects a decision
 * somebody actually made rather than something inferred. Where a flag has
 * never been set the old derivation is used, so cleaners approved before these
 * controls existed do not suddenly lose badges they legitimately hold.
 */
function gb_get_cleaner_checks( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $verified   = get_post_meta( $cleaner_id, 'is_verified', true ) === '1';

    $refs_ok = true;
    foreach ( array( '1', '2' ) as $n ) {
        if ( ! get_post_meta( $cleaner_id, "_gb_ref{$n}_name", true ) ) {
            $refs_ok = false;
            break;
        }
    }

    $resolve = function ( $key, $fallback ) use ( $cleaner_id ) {
        $stored = get_post_meta( $cleaner_id, $key, true );
        if ( $stored === '1' ) {
            return true;
        }
        if ( $stored === '0' ) {
            return false;
        }
        return (bool) $fallback; // Never set: fall back to how it used to be derived.
    };

    return array(
        'id'   => $resolve( '_gb_check_id', $verified ),
        'dbs'  => $resolve( '_gb_check_dbs', $verified ),
        'refs' => $resolve( '_gb_check_refs', $refs_ok ),
    );
}

/**
 * Whether a cleaner has completed every check the public badge promises.
 * The profile must not show the badge on a partial set - the pop-up tells
 * customers all three are done.
 */
function gb_cleaner_is_fully_verified( $cleaner_id ) {
    if ( get_post_meta( (int) $cleaner_id, 'is_verified', true ) !== '1' ) {
        return false;
    }
    $checks = gb_get_cleaner_checks( $cleaner_id );
    return $checks['id'] && $checks['dbs'] && $checks['refs'];
}

function gb_get_cleaner_verification_badges( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $verified   = get_post_meta( $cleaner_id, 'is_verified', true ) === '1';

    $checks  = gb_get_cleaner_checks( $cleaner_id );
    $refs_ok = $checks['refs'];

    // 'public' marks the checks a customer actually weighs when deciding to let
    // someone into their home. Address/phone/terms are internal vetting admin —
    // they matter to the manager, not to a booker, so they stay off the profile.
    $svg = function ( $paths ) {
        return '<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    };

    $badges = array(
        // Labels and notes are the client's own verification wording, in their
        // order. Note this is a BASIC DBS check, not an Enhanced one - the
        // previous copy overstated it, which matters on a public trust claim.
        array(
            'label' => 'Identity verified', 'done' => $checks['id'], 'public' => true,
            'note'  => 'Government issued I/D uploaded during application, checked and verified',
            'svg'   => $svg( '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><circle cx="9" cy="11" r="2"/><path d="M6 16.2c.5-1.4 1.7-2.1 3-2.1s2.5.7 3 2.1"/><path d="M15 10.5h4M15 13.5h4"/>' ),
        ),
        array(
            'label' => 'Basic DBS certificate verified', 'done' => $checks['dbs'], 'public' => true,
            'note'  => 'Basic DBS Certificate uploaded during application, checked and verified; DBS status is periodically reviewed',
            'svg'   => $svg( '<path d="M12 3l7 3v5.5c0 4.2-2.9 7.8-7 8.9-4.1-1.1-7-4.7-7-8.9V6l7-3z"/><path d="M9 12.2l2.1 2.1L15.2 10"/>' ),
        ),
        array(
            'label' => '2 references verified', 'done' => $refs_ok, 'public' => true,
            'note'  => 'Two character references supplied during application; we contact referees and written replies remain on file',
            'svg'   => $svg( '<circle cx="9" cy="9" r="3"/><path d="M3.5 19c.6-3 2.9-4.6 5.5-4.6S13.9 16 14.5 19"/><path d="M16 6.6a3 3 0 0 1 0 5.5"/><path d="M17.6 14.7c1.9.6 3 2 3.4 4.3"/>' ),
        ),
        // Internal vetting — deliberately not shown on the public profile.
        array( 'label' => 'Address Confirmed', 'done' => $verified, 'public' => false, 'note' => 'Proof of address confirmed', 'svg' => $svg( '<path d="M4 10.5L12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19v-8.5z"/>' ) ),
        array( 'label' => 'Phone Verified', 'done' => (bool) get_post_meta( $cleaner_id, '_gb_cleaner_phone', true ), 'public' => false, 'note' => 'Contact number on file', 'svg' => $svg( '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M11 18.5h2"/>' ) ),
        array( 'label' => 'Terms Signed', 'done' => get_post_meta( $cleaner_id, '_gb_toa_signed', true ) === '1', 'public' => false, 'note' => 'Signed the Terms of Association', 'svg' => $svg( '<path d="M4 18.5c3-1 4-6 6.5-6s1.5 4 4 4 3-3 5.5-3"/>' ) ),
    );

    // Insurance is provided at platform level when configured that way.
    $settings_id = gb_get_site_settings_id();
    $insurance   = $settings_id ? get_post_meta( $settings_id, '_gb_require_insurance', true ) : '';
    if ( $insurance === 'provided' || $insurance === 'yes' ) {
        $badges[] = array(
            'label' => 'Insured', 'done' => $verified, 'public' => true,
            'note'  => $insurance === 'provided'
                ? 'Covered by The Green Broom’s insurance policy'
                : 'Proof of insurance provided',
            'svg'   => $svg( '<path d="M12 3.6c-4.3 0-7.8 3.3-7.8 7.4h15.6c0-4.1-3.5-7.4-7.8-7.4z"/><path d="M12 11v6.3a2.1 2.1 0 0 0 4.2 0"/>' ),
        );
    }

    return $badges;
}

/**
 * Only the completed, customer-relevant checks — what a booker sees.
 *
 * Incomplete checks are omitted rather than shown greyed out: a public profile
 * should display earned trust signals, not advertise gaps.
 */
function gb_get_public_cleaner_badges( $cleaner_id ) {
    $public = array();
    foreach ( gb_get_cleaner_verification_badges( $cleaner_id ) as $badge ) {
        if ( ! empty( $badge['public'] ) && ! empty( $badge['done'] ) ) {
            $public[] = $badge;
        }
    }

    return $public;
}

/**
 * Vetting fields still outstanding on a cleaner, for the manager list.
 *
 * Differs from gb_cleaner_verification_missing() in one important way: the ID,
 * address and DBS documents are permanently purged once a cleaner is approved,
 * so on an APPROVED profile their absence means "checked, then deleted" rather
 * than "never supplied". Reporting those as missing would flag every live
 * cleaner. Documents are therefore only counted before approval; the fields
 * that persist (phone, references, signed terms) are always checked.
 */
function gb_get_cleaner_vetting_gaps( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $verified   = get_post_meta( $cleaner_id, 'is_verified', true ) === '1';
    $gaps       = array();

    if ( ! $verified && class_exists( 'GB_Uploads' ) && method_exists( 'GB_Uploads', 'get_cleaner_verification_document' ) ) {
        $doc_labels = array( 'doc_id' => 'ID', 'doc_address' => 'Address', 'doc_dbs' => 'DBS' );
        foreach ( $doc_labels as $field => $label ) {
            if ( empty( GB_Uploads::get_cleaner_verification_document( $cleaner_id, $field ) ) ) {
                $gaps[] = $label;
            }
        }
    }

    if ( ! get_post_meta( $cleaner_id, '_gb_cleaner_phone', true ) ) {
        $gaps[] = 'Phone';
    }

    foreach ( array( '1', '2' ) as $n ) {
        $ok = get_post_meta( $cleaner_id, "_gb_ref{$n}_name", true )
           && get_post_meta( $cleaner_id, "_gb_ref{$n}_address", true )
           && is_email( get_post_meta( $cleaner_id, "_gb_ref{$n}_email", true ) );
        if ( ! $ok ) {
            $gaps[] = "Ref {$n}";
        }
    }

    if ( get_post_meta( $cleaner_id, '_gb_toa_signed', true ) !== '1' ) {
        $gaps[] = 'Terms';
    }

    return $gaps;
}

function gb_get_email_settings() {
    $settings_id = gb_get_site_settings_id();
    $verification_setting = get_post_meta( $settings_id, '_gb_require_email_verification', true );
    $from_address = sanitize_email( (string) get_post_meta( $settings_id, '_gb_email_from_address', true ) );
    $reply_to = sanitize_email( (string) get_post_meta( $settings_id, '_gb_email_reply_to', true ) );

    return [
        'verification_required' => $verification_setting === '' ? true : $verification_setting === 'yes',
        'from_name'             => sanitize_text_field( (string) get_post_meta( $settings_id, '_gb_email_from_name', true ) ),
        'from_address'          => $from_address !== '' ? $from_address : 'info@thegreenbroom.co.uk',
        'reply_to'              => $reply_to !== '' ? $reply_to : 'info@thegreenbroom.co.uk',
        'verification_subject'  => sanitize_text_field( (string) get_post_meta( $settings_id, '_gb_email_verification_subject', true ) ),
    ];
}

function gb_get_email_from_name() {
    $settings = gb_get_email_settings();

    return $settings['from_name'] !== '' ? $settings['from_name'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

function gb_get_email_from_address() {
    $settings = gb_get_email_settings();
    return is_email( $settings['from_address'] ) ? $settings['from_address'] : 'info@thegreenbroom.co.uk';
}

function gb_get_email_reply_to() {
    $settings = gb_get_email_settings();
    if ( $settings['reply_to'] !== '' ) {
        return $settings['reply_to'];
    }

    return gb_get_email_from_address();
}

/**
 * Read the Brevo transactional email settings stored with the site settings.
 * The API key is deliberately never returned to the dashboard markup.
 */
function gb_get_brevo_settings() {
    $settings_id = gb_get_site_settings_id();
    $enabled = get_post_meta( $settings_id, '_gb_brevo_enabled', true );
    $api_key = trim( (string) get_post_meta( $settings_id, '_gb_brevo_api_key', true ) );
    $email_settings = gb_get_email_settings();

    return [
        'enabled'      => $enabled === 'yes',
        'api_key'      => $api_key,
        'from_name'    => gb_get_email_from_name(),
        'from_address' => $email_settings['from_address'],
        'reply_to'     => $email_settings['reply_to'],
    ];
}

function gb_is_brevo_configured() {
    $settings = gb_get_brevo_settings();

    return ! empty( $settings['enabled'] ) && $settings['api_key'] !== '' && is_email( $settings['from_address'] );
}

/**
 * Make a small authenticated request to Brevo's API. This is used both by
 * the wp_mail bridge and by the manager's connection check.
 */
function gb_brevo_api_request( $endpoint, $body = [], $method = 'GET' ) {
    $settings = gb_get_brevo_settings();
    if ( $settings['api_key'] === '' ) {
        return new WP_Error( 'gb_brevo_missing_key', 'Add a Brevo API key before testing the connection.' );
    }

    $args = [
        'method'  => strtoupper( (string) $method ),
        'timeout' => 20,
        'headers' => [
            'accept'  => 'application/json',
            'api-key' => $settings['api_key'],
        ],
    ];

    if ( strtoupper( (string) $method ) !== 'GET' ) {
        $args['headers']['content-type'] = 'application/json';
        $args['body'] = wp_json_encode( $body );
    }

    $response = wp_remote_request( 'https://api.brevo.com/v3/' . ltrim( (string) $endpoint, '/' ), $args );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'gb_brevo_request_failed', 'Brevo could not be reached: ' . $response->get_error_message() );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $raw_body = (string) wp_remote_retrieve_body( $response );
    $decoded = json_decode( $raw_body, true );
    if ( $status < 200 || $status >= 300 ) {
        $message = is_array( $decoded ) && ! empty( $decoded['message'] )
            ? sanitize_text_field( (string) $decoded['message'] )
            : 'Brevo returned HTTP ' . $status . '.';

        return new WP_Error( 'gb_brevo_api_error', $message, [ 'status' => $status ] );
    }

    return is_array( $decoded ) ? $decoded : [];
}

function gb_brevo_recipient( $recipient ) {
    if ( is_array( $recipient ) ) {
        $email = isset( $recipient['email'] ) ? sanitize_email( $recipient['email'] ) : '';
        $name = isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : '';
    } else {
        $recipient = trim( (string) $recipient );
        $name = '';
        $email = $recipient;
        if ( preg_match( '/^(.*?)\s*<([^>]+)>$/', $recipient, $matches ) ) {
            $name = sanitize_text_field( trim( $matches[1], " \t\"'" ) );
            $email = sanitize_email( $matches[2] );
        } else {
            $email = sanitize_email( $email );
        }
    }

    if ( ! is_email( $email ) ) {
        return [];
    }

    $result = [ 'email' => $email ];
    if ( $name !== '' ) {
        $result['name'] = $name;
    }

    return $result;
}

function gb_brevo_recipients( $recipients ) {
    if ( ! is_array( $recipients ) ) {
        $recipients = preg_split( '/[,;]+/', (string) $recipients );
    }

    $parsed = [];
    foreach ( (array) $recipients as $recipient ) {
        $value = gb_brevo_recipient( $recipient );
        if ( ! empty( $value ) ) {
            $parsed[] = $value;
        }
    }

    return $parsed;
}

function gb_brevo_parse_headers( $headers ) {
    if ( is_array( $headers ) ) {
        $lines = [];
        foreach ( $headers as $key => $header ) {
            if ( is_string( $header ) ) {
                $lines[] = $header;
            } elseif ( is_array( $header ) && isset( $header[0], $header[1] ) ) {
                $lines[] = $header[0] . ': ' . $header[1];
            } elseif ( is_string( $key ) ) {
                $lines[] = $key . ': ' . $header;
            }
        }
    } else {
        $lines = preg_split( "/\r\n|\n|\r/", (string) $headers );
    }

    $parsed = [
        'cc'       => [],
        'bcc'      => [],
        'reply_to' => [],
        'is_html'  => false,
    ];
    foreach ( (array) $lines as $line ) {
        if ( strpos( (string) $line, ':' ) === false ) {
            continue;
        }

        [ $name, $value ] = array_map( 'trim', explode( ':', (string) $line, 2 ) );
        $name = strtolower( $name );
        if ( $name === 'cc' ) {
            $parsed['cc'] = array_merge( $parsed['cc'], gb_brevo_recipients( $value ) );
        } elseif ( $name === 'bcc' ) {
            $parsed['bcc'] = array_merge( $parsed['bcc'], gb_brevo_recipients( $value ) );
        } elseif ( $name === 'reply-to' ) {
            $parsed['reply_to'] = gb_brevo_recipients( $value );
        } elseif ( $name === 'content-type' && stripos( $value, 'text/html' ) !== false ) {
            $parsed['is_html'] = true;
        }
    }

    return $parsed;
}

/**
 * Brevo template IDs for the branded transactional flows.
 * These IDs are account-level Brevo template IDs, not WordPress post IDs.
 */
function gb_get_brevo_template_ids() {
    return [
        'verification'         => 1,
        'password_reset'       => 2,
        'booking_customer'     => 3,
        'booking_cleaner'      => 4,
        'support_reply'        => 5,
        'manager_message'      => 6,
        'subscription_expired' => 7,
    ];
}

function gb_get_brevo_template_id( $key ) {
    $ids = gb_get_brevo_template_ids();
    return isset( $ids[ $key ] ) ? absint( $ids[ $key ] ) : 0;
}

function gb_brevo_first_name( $name, $fallback = 'there' ) {
    $name = trim( wp_strip_all_tags( (string) $name ) );
    if ( $name === '' ) {
        return $fallback;
    }

    $parts = preg_split( '/\s+/', $name );
    return ! empty( $parts[0] ) ? sanitize_text_field( $parts[0] ) : $fallback;
}

function gb_get_email_recipient_url( $email ) {
    $user = get_user_by( 'email', sanitize_email( (string) $email ) );
    if ( $user instanceof WP_User && function_exists( 'gb_get_user_dashboard_url' ) ) {
        return gb_get_user_dashboard_url( $user );
    }

    return home_url( '/contact-support' );
}

/**
 * Send a named Brevo template while retaining wp_mail as the local fallback
 * when Brevo is disabled or not configured.
 */
function gb_send_brevo_template_email( $to, $subject, $plain_message, $headers = [], $template_id = 0, $params = [] ) {
    if ( ! gb_is_brevo_configured() ) {
        return wp_mail( $to, $subject, $plain_message, $headers );
    }

    $settings = gb_get_brevo_settings();
    $recipients = gb_brevo_recipients( $to );
    if ( empty( $recipients ) || absint( $template_id ) < 1 ) {
        return false;
    }

    $parsed_headers = gb_brevo_parse_headers( $headers );
    $payload = [
        'sender'     => [
            'name'  => $settings['from_name'],
            'email' => $settings['from_address'],
        ],
        'to'         => $recipients,
        'templateId' => absint( $template_id ),
        'subject'    => sanitize_text_field( (string) $subject ),
        'params'     => is_array( $params ) ? $params : [],
    ];

    $reply_to = ! empty( $parsed_headers['reply_to'] ) ? $parsed_headers['reply_to'][0] : gb_get_email_reply_to();
    if ( is_array( $reply_to ) && is_email( $reply_to['email'] ?? '' ) ) {
        $payload['replyTo'] = $reply_to;
    } elseif ( is_email( $reply_to ) ) {
        $payload['replyTo'] = [ 'email' => $reply_to ];
    }
    if ( ! empty( $parsed_headers['cc'] ) ) {
        $payload['cc'] = $parsed_headers['cc'];
    }
    if ( ! empty( $parsed_headers['bcc'] ) ) {
        $payload['bcc'] = $parsed_headers['bcc'];
    }

    $result = gb_brevo_api_request( 'smtp/email', $payload, 'POST' );
    return ! is_wp_error( $result );
}

/**
 * WordPress core builds the password-reset message internally. Capture its
 * one-time URL so the pre_wp_mail bridge can use the branded Brevo template.
 */
add_filter( 'retrieve_password_message', function ( $message, $key, $user_login, $user_data = null ) {
    if ( ! ( $user_data instanceof WP_User ) ) {
        $user_data = get_user_by( 'login', $user_login );
    }

    if ( $user_data instanceof WP_User ) {
        $GLOBALS['gb_pending_password_reset'] = [
            'email'      => $user_data->user_email,
            'first_name' => gb_brevo_first_name( $user_data->first_name ?: $user_data->display_name ),
            'reset_url'  => network_site_url(
                'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ),
                'login'
            ),
        ];
    }

    return $message;
}, 10, 4 );

/**
 * Send all existing wp_mail calls through Brevo when enabled. This keeps the
 * site's verification, support, booking, and password email flows together.
 */
function gb_send_wp_mail_through_brevo( $pre_wp_mail, $atts ) {
    if ( $pre_wp_mail !== null || ! gb_is_brevo_configured() ) {
        return $pre_wp_mail;
    }

    $settings = gb_get_brevo_settings();
    $recipients = gb_brevo_recipients( $atts['to'] ?? [] );
    if ( empty( $recipients ) ) {
        return false;
    }

    $headers = gb_brevo_parse_headers( $atts['headers'] ?? [] );
    $subject = sanitize_text_field( (string) ( $atts['subject'] ?? '' ) );

    $pending_reset = $GLOBALS['gb_pending_password_reset'] ?? [];
    if ( ! empty( $pending_reset['reset_url'] ) && stripos( $subject, 'password reset' ) !== false ) {
        unset( $GLOBALS['gb_pending_password_reset'] );
        return gb_send_brevo_template_email(
            $recipients[0]['email'],
            $subject,
            (string) ( $atts['message'] ?? '' ),
            $atts['headers'] ?? [],
            gb_get_brevo_template_id( 'password_reset' ),
            [
                'first_name' => $pending_reset['first_name'] ?? 'there',
                'reset_url'  => $pending_reset['reset_url'],
            ]
        );
    }

    $message = (string) ( $atts['message'] ?? '' );
    $payload = [
        'sender'      => [
            'name'  => $settings['from_name'],
            'email' => $settings['from_address'],
        ],
        'to'          => $recipients,
        'subject'     => $subject,
        'htmlContent' => $headers['is_html'] ? $message : wpautop( esc_html( $message ) ),
        'textContent' => wp_strip_all_tags( $message ),
    ];

    $reply_to = ! empty( $headers['reply_to'] ) ? $headers['reply_to'][0] : gb_get_email_reply_to();
    if ( is_array( $reply_to ) && is_email( $reply_to['email'] ?? '' ) ) {
        $payload['replyTo'] = $reply_to;
    } elseif ( is_email( $reply_to ) ) {
        $payload['replyTo'] = [ 'email' => $reply_to ];
    }
    if ( ! empty( $headers['cc'] ) ) {
        $payload['cc'] = $headers['cc'];
    }
    if ( ! empty( $headers['bcc'] ) ) {
        $payload['bcc'] = $headers['bcc'];
    }

    $attachments = isset( $atts['attachments'] ) ? (array) $atts['attachments'] : [];
    foreach ( $attachments as $attachment ) {
        if ( ! is_string( $attachment ) || ! is_readable( $attachment ) ) {
            continue;
        }

        $contents = file_get_contents( $attachment );
        if ( $contents === false ) {
            continue;
        }

        $payload['attachment'][] = [
            'name'    => basename( $attachment ),
            'content' => base64_encode( $contents ),
        ];
    }

    $result = gb_brevo_api_request( 'smtp/email', $payload, 'POST' );

    return is_wp_error( $result ) ? false : true;
}
add_filter( 'pre_wp_mail', 'gb_send_wp_mail_through_brevo', 10, 2 );

function gb_redirect_manager_settings_with_brevo_notice( $status, $message ) {
    $status = $status === 'ok' ? 'ok' : 'error';
    $message = sanitize_text_field( (string) $message );
    wp_safe_redirect( gb_get_manager_settings_url( [ 'brevo_notice' => $status, 'brevo_msg' => $message ] ) );
    exit;
}

function gb_handle_brevo_test_connection() {
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/login' ) );
        exit;
    }

    $user = wp_get_current_user();
    if ( ! current_user_can( 'manage_options' ) && ! in_array( 'gb_manager', (array) $user->roles, true ) ) {
        wp_die( 'You do not have permission to test the email connection.' );
    }

    check_admin_referer( 'gb_brevo_test' );
    $mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'connection';
    if ( $mode === 'send' ) {
        $settings = gb_get_brevo_settings();
        $recipient = gb_get_support_email();
        $payload = [
            'sender'      => [
                'name'  => $settings['from_name'],
                'email' => $settings['from_address'],
            ],
            'to'          => [ [ 'email' => $recipient ] ],
            'replyTo'     => [ 'email' => $settings['reply_to'] ],
            'subject'     => 'The Green Broom Brevo test email',
            'htmlContent' => '<p>This is a test email from The Green Broom. Brevo email delivery is working.</p>',
            'textContent' => 'This is a test email from The Green Broom. Brevo email delivery is working.',
        ];
        $send_result = gb_brevo_api_request( 'smtp/email', $payload, 'POST' );
        if ( is_wp_error( $send_result ) ) {
            gb_redirect_manager_settings_with_brevo_notice( 'error', $send_result->get_error_message() );
        }

        gb_redirect_manager_settings_with_brevo_notice( 'ok', 'A Brevo test email was sent to ' . $recipient . '.' );
    }

    $result = gb_brevo_api_request( 'account' );
    if ( is_wp_error( $result ) ) {
        gb_redirect_manager_settings_with_brevo_notice( 'error', $result->get_error_message() );
    }

    $account_name = ! empty( $result['companyName'] ) ? sanitize_text_field( $result['companyName'] ) : 'account';
    gb_redirect_manager_settings_with_brevo_notice( 'ok', 'Brevo connection verified for ' . $account_name . '.' );
}
add_action( 'admin_post_gb_brevo_test', 'gb_handle_brevo_test_connection' );

function gb_is_email_verification_enabled() {
    $settings = gb_get_email_settings();

    return ! empty( $settings['verification_required'] );
}

function gb_is_email_verification_candidate( $user ) {
    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return false;
    }

    $roles = (array) $user->roles;
    if ( array_intersect( [ 'administrator', 'gb_manager' ], $roles ) ) {
        return false;
    }

    return (bool) array_intersect( [ 'gb_customer', 'gb_cleaner' ], $roles );
}

function gb_is_user_email_verified( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 ) {
        return false;
    }

    return get_user_meta( $user_id, 'gb_email_verified', true ) !== '0';
}

function gb_user_requires_email_verification( $user ) {
    if ( ! gb_is_email_verification_enabled() || ! gb_is_email_verification_candidate( $user ) ) {
        return false;
    }

    return ! gb_is_user_email_verified( $user->ID );
}

function gb_get_verify_email_url( $args = [] ) {
    $base = home_url( '/verify-email/' );

    return empty( $args ) ? $base : add_query_arg( $args, $base );
}

function gb_normalize_internal_redirect_url( $url, $fallback = '' ) {
    $url = trim( (string) $url );
    if ( $url === '' ) {
        return $fallback;
    }

    if ( strpos( $url, '/' ) === 0 ) {
        $url = home_url( $url );
    }

    $validated = wp_validate_redirect( $url, '' );
    if ( $validated === '' ) {
        return $fallback;
    }

    $home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    $target_host = wp_parse_url( $validated, PHP_URL_HOST );
    if (
        is_string( $home_host ) &&
        $home_host !== '' &&
        is_string( $target_host ) &&
        $target_host !== '' &&
        strtolower( $home_host ) !== strtolower( $target_host )
    ) {
        return $fallback;
    }

    return $validated;
}

function gb_get_user_dashboard_url( $user = null ) {
    if ( ! ( $user instanceof WP_User ) ) {
        $user = wp_get_current_user();
    }

    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return home_url( '/' );
    }

	$roles = (array) $user->roles;
	if ( in_array( 'administrator', $roles, true ) ) {
		return admin_url( 'users.php' );
	}

	if ( in_array( 'gb_manager', $roles, true ) ) {
		return home_url( '/manager-dashboard' );
	}

    if ( in_array( 'gb_cleaner', $roles, true ) ) {
        return home_url( '/cleaner-dashboard' );
    }

    return home_url( '/my-account' );
}

function gb_get_post_login_redirect_url( $user ) {
    if ( gb_user_requires_email_verification( $user ) ) {
        return gb_get_verify_email_url( [ 'email_status' => 'check-inbox' ] );
    }

    return gb_get_user_dashboard_url( $user );
}

function gb_set_email_verification_redirect( $user_id, $url ) {
    $user = get_user_by( 'id', (int) $user_id );
    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return;
    }

    $normalized = gb_normalize_internal_redirect_url( $url, gb_get_user_dashboard_url( $user ) );
    update_user_meta( $user->ID, 'gb_email_verification_redirect', $normalized );
}

function gb_get_email_verification_success_redirect( $user = null ) {
    if ( ! ( $user instanceof WP_User ) ) {
        $user = wp_get_current_user();
    }

    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return home_url( '/' );
    }

    $stored_redirect = (string) get_user_meta( $user->ID, 'gb_email_verification_redirect', true );

    return gb_normalize_internal_redirect_url( $stored_redirect, gb_get_user_dashboard_url( $user ) );
}

function gb_consume_email_verification_success_redirect( $user = null ) {
    if ( ! ( $user instanceof WP_User ) ) {
        $user = wp_get_current_user();
    }

    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return home_url( '/' );
    }

    $redirect = gb_get_email_verification_success_redirect( $user );
    delete_user_meta( $user->ID, 'gb_email_verification_redirect' );

    return $redirect;
}

function gb_mark_user_email_unverified( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 ) {
        return;
    }

    update_user_meta( $user_id, 'gb_email_verified', '0' );
    delete_user_meta( $user_id, 'gb_email_verified_at' );
    delete_user_meta( $user_id, 'gb_email_verification_last_error' );
}

function gb_mark_user_email_verified( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 ) {
        return;
    }

    update_user_meta( $user_id, 'gb_email_verified', '1' );
    update_user_meta( $user_id, 'gb_email_verified_at', current_time( 'mysql' ) );
    delete_user_meta( $user_id, 'gb_email_verification_token_hash' );
    delete_user_meta( $user_id, 'gb_email_verification_expires' );
    delete_user_meta( $user_id, 'gb_email_verification_last_error' );
}

function gb_generate_email_verification_token( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 ) {
        return '';
    }

    $token = wp_generate_password( 48, false, false );
    update_user_meta( $user_id, 'gb_email_verification_token_hash', wp_hash( $token ) );
    update_user_meta( $user_id, 'gb_email_verification_expires', time() + DAY_IN_SECONDS );

    return $token;
}

function gb_send_email_verification( $user_id, $respect_rate_limit = false ) {
    $user = get_user_by( 'id', (int) $user_id );
    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return new WP_Error( 'gb_invalid_user', 'We could not find that account.' );
    }

    if ( ! gb_is_email_verification_candidate( $user ) ) {
        return true;
    }

    if ( $respect_rate_limit ) {
        $rate_state = gb_rate_limit_check( 'email_verification_send', 3, HOUR_IN_SECONDS, $user->user_email );
        if ( $rate_state['blocked'] ) {
            return new WP_Error( 'gb_email_rate_limited', gb_get_rate_limit_message( $rate_state ) );
        }
        gb_record_rate_limit_hit( 'email_verification_send', HOUR_IN_SECONDS, $user->user_email );
    }

    gb_mark_user_email_unverified( $user->ID );
    $token = gb_generate_email_verification_token( $user->ID );
    if ( $token === '' ) {
        return new WP_Error( 'gb_email_token_failed', 'We could not generate a verification token.' );
    }

    $settings = gb_get_email_settings();
    $subject = $settings['verification_subject'] !== '' ? $settings['verification_subject'] : 'Verify your email for The Green Broom';
    $verification_url = gb_get_verify_email_url(
        [
            'uid'   => $user->ID,
            'token' => $token,
        ]
    );

    $message = implode(
        "\n\n",
        [
            'Hi ' . ( $user->first_name ? $user->first_name : $user->display_name ) . ',',
            'Please verify your email address to finish setting up your account on The Green Broom.',
            'Verify your email: ' . $verification_url,
            'This link expires in 24 hours. If you did not create this account, you can ignore this email.',
        ]
    );

    $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

    $sent = gb_send_brevo_template_email(
        $user->user_email,
        $subject,
        $message,
        $headers,
        gb_get_brevo_template_id( 'verification' ),
        [
            'first_name'        => gb_brevo_first_name( $user->first_name ?: $user->display_name ),
            'verification_url'  => $verification_url,
        ]
    );
    update_user_meta( $user->ID, 'gb_email_verification_last_sent_at', current_time( 'mysql' ) );

    if ( ! $sent ) {
        update_user_meta( $user->ID, 'gb_email_verification_last_error', 'wp_mail returned false.' );

        return new WP_Error( 'gb_email_send_failed', 'We could not send the verification email right now.' );
    }

    delete_user_meta( $user->ID, 'gb_email_verification_last_error' );

    return true;
}

function gb_verify_user_email_token( $user_id, $raw_token ) {
    $user_id = (int) $user_id;
    $raw_token = sanitize_text_field( (string) $raw_token );
    if ( $user_id < 1 || $raw_token === '' ) {
        return new WP_Error( 'gb_invalid_verification', 'That verification link is invalid.' );
    }

    $stored_hash = (string) get_user_meta( $user_id, 'gb_email_verification_token_hash', true );
    $expires_at = (int) get_user_meta( $user_id, 'gb_email_verification_expires', true );
    if ( $stored_hash === '' || $expires_at < time() ) {
        return new WP_Error( 'gb_expired_verification', 'That verification link has expired. Please request a new one.' );
    }

    if ( ! hash_equals( $stored_hash, wp_hash( $raw_token ) ) ) {
        return new WP_Error( 'gb_invalid_verification', 'That verification link is invalid.' );
    }

    gb_mark_user_email_verified( $user_id );

    return true;
}

function gb_maybe_start_email_verification( $user_id ) {
    $user = get_user_by( 'id', (int) $user_id );
    if ( ! ( $user instanceof WP_User ) || ! gb_user_requires_email_verification( $user ) ) {
        return true;
    }

    return gb_send_email_verification( $user->ID );
}

function gb_prepare_new_user_email_verification( $user_id, $success_redirect = '' ) {
    $user = get_user_by( 'id', (int) $user_id );
    if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
        return [
            'required'    => false,
            'redirect'    => home_url( '/' ),
            'send_result' => new WP_Error( 'gb_invalid_user', 'We could not find that account.' ),
        ];
    }

    $success_redirect = gb_normalize_internal_redirect_url( $success_redirect, gb_get_user_dashboard_url( $user ) );

    if ( ! gb_is_email_verification_enabled() || ! gb_is_email_verification_candidate( $user ) ) {
        delete_user_meta( $user->ID, 'gb_email_verification_redirect' );

        return [
            'required'    => false,
            'redirect'    => $success_redirect,
            'send_result' => true,
        ];
    }

    gb_set_email_verification_redirect( $user->ID, $success_redirect );
    $send_result = gb_send_email_verification( $user->ID );

    return [
        'required'    => true,
        'redirect'    => gb_get_verify_email_url(
            [
                'email_status' => is_wp_error( $send_result ) ? 'send-error' : 'check-inbox',
            ]
        ),
        'send_result' => $send_result,
    ];
}

function gb_should_gate_account_page_for_unverified_user() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user = wp_get_current_user();
    if ( ! gb_user_requires_email_verification( $user ) ) {
        return false;
    }

    return in_array(
        gb_get_current_request_path(),
        [
            '/cleaner-dashboard',
            '/edit-profile',
            '/inbox',
            '/my-account',
            '/schedule',
            '/settings',
            '/verify-profile',
        ],
        true
    );
}

function gb_filter_mail_from( $from ) {
    $configured_from = gb_get_email_from_address();

    return $configured_from !== '' ? $configured_from : $from;
}
add_filter( 'wp_mail_from', 'gb_filter_mail_from' );

function gb_filter_mail_from_name( $from_name ) {
    $configured_name = gb_get_email_from_name();

    return $configured_name !== '' ? $configured_name : $from_name;
}
add_filter( 'wp_mail_from_name', 'gb_filter_mail_from_name' );

function gb_filter_wp_mail_args( $mail_args ) {
    $reply_to = gb_get_email_reply_to();
    if ( ! is_email( $reply_to ) ) {
        return $mail_args;
    }

    $headers = isset( $mail_args['headers'] ) ? $mail_args['headers'] : [];
    $header_list = is_array( $headers )
        ? $headers
        : preg_split( "/\r\n|\n|\r/", (string) $headers );

    $header_list = is_array( $header_list ) ? array_filter( $header_list ) : [];

    foreach ( $header_list as $header_line ) {
        if ( stripos( (string) $header_line, 'Reply-To:' ) === 0 ) {
            return $mail_args;
        }
    }

    $header_list[] = 'Reply-To: ' . $reply_to;
    $mail_args['headers'] = is_array( $headers ) ? $header_list : implode( "\r\n", $header_list );

    return $mail_args;
}
add_filter( 'wp_mail', 'gb_filter_wp_mail_args' );

function gb_get_page_url_by_slug( $slug, $fallback = '/' ) {
    $page = get_page_by_path( sanitize_title( (string) $slug ) );
    if ( $page instanceof WP_Post && $page->post_status === 'publish' ) {
        return get_permalink( $page );
    }

    return home_url( $fallback );
}

function gb_get_contact_page_url() {
    foreach ( [ 'contact', 'contact-support' ] as $slug ) {
        $page = get_page_by_path( $slug );
        if ( $page instanceof WP_Post && $page->post_status === 'publish' ) {
            return get_permalink( $page );
        }
    }

    return home_url( '/contact/' );
}

function gb_get_privacy_page_url() {
    return gb_get_page_url_by_slug( 'privacy', '/privacy/' );
}

function gb_get_terms_page_url() {
    return gb_get_page_url_by_slug( 'terms', '/terms/' );
}

function gb_get_subscriber_terms_page_url() {
    return gb_get_page_url_by_slug( 'subscribers-terms-of-association', '/subscribers-terms-of-association/' );
}

/*
 * gb_get_subscriber_terms_of_association_sections() used to live here and was
 * the real text of the Subscribers' Terms page. It has moved into
 * inc/legal-content.php as seed copy, and the page's own content is now what
 * renders. Editing a copy here would have changed nothing, so it is gone
 * rather than left looking authoritative.
 */

/**
 * Fingerprint of the subscriber terms as currently published, recorded against
 * each sign-up. Now that the page is editable, this has to hash what the page
 * actually says - hashing the old hardcoded array would pin every future
 * acceptance to a version nobody was ever shown.
 */
function gb_get_subscriber_terms_of_association_version() {
    $page = get_page_by_path( 'subscribers-terms-of-association' );
    $body = $page instanceof WP_Post ? trim( (string) $page->post_content ) : '';

    if ( $body === '' && function_exists( 'gb_get_legal_seed_content' ) ) {
        $body = gb_get_legal_seed_content( 'subscribers-terms-of-association' );
    }

    return substr( md5( $body ), 0, 8 );
}

function gb_get_blog_url() {
    return home_url( '/blog/' );
}

function gb_get_privacy_anchor_url( $anchor = '' ) {
    $url = gb_get_privacy_page_url();
    $anchor = trim( (string) $anchor );

    if ( $anchor === '' ) {
        return $url;
    }

    return $url . '#' . ltrim( $anchor, '#' );
}

function gb_get_current_request_path() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
    $path = wp_parse_url( $request_uri, PHP_URL_PATH );

    if ( ! is_string( $path ) || $path === '' ) {
        return '/';
    }

    $home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
    if ( is_string( $home_path ) && $home_path !== '' && $home_path !== '/' && strpos( $path, $home_path ) === 0 ) {
        $path = substr( $path, strlen( $home_path ) );
    }

    $path = '/' . ltrim( $path, '/' );
    $path = untrailingslashit( $path );

    return $path === '' ? '/' : $path;
}

function gb_request_path_is( $path ) {
    return gb_get_current_request_path() === '/' . trim( (string) $path, '/' );
}

function gb_is_virtual_blog_request() {
    return gb_request_path_is( 'blog' );
}

function gb_is_virtual_email_verification_request() {
    return gb_request_path_is( 'verify-email' );
}

function gb_get_hidden_blog_post_slugs() {
    return [
        'hello-world',
        'this-is-a-new-testing-blog-post',
    ];
}

function gb_get_hidden_blog_post_ids() {
    static $hidden_ids = null;

    if ( is_array( $hidden_ids ) ) {
        return $hidden_ids;
    }

    $hidden_ids = [];
    foreach ( gb_get_hidden_blog_post_slugs() as $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'post' );
        if ( $post instanceof WP_Post ) {
            $hidden_ids[] = (int) $post->ID;
        }
    }

    return $hidden_ids;
}

function gb_filter_post_sitemap_query_args( $args, $post_type ) {
    if ( $post_type !== 'post' ) {
        return $args;
    }

    $hidden_ids = gb_get_hidden_blog_post_ids();
    if ( empty( $hidden_ids ) ) {
        return $args;
    }

    $existing = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : [];
    $args['post__not_in'] = array_values( array_unique( array_merge( $existing, $hidden_ids ) ) );

    return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'gb_filter_post_sitemap_query_args', 10, 2 );

function gb_disable_user_sitemaps( $provider, $name ) {
    if ( $name === 'users' ) {
        return false;
    }

    return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'gb_disable_user_sitemaps', 10, 2 );

function gb_get_restricted_public_rest_route_prefixes() {
    return [
        '/ionos/essentials/',
        '/one-time-login/v1',
        '/wp-site-health/v1',
    ];
}

function gb_is_restricted_public_rest_route( $route ) {
    $route = '/' . ltrim( (string) $route, '/' );

    if ( strpos( $route, '/wp/v2/users' ) === 0 ) {
        return true;
    }

    foreach ( gb_get_restricted_public_rest_route_prefixes() as $prefix ) {
        if ( strpos( $route, $prefix ) === 0 ) {
            return true;
        }
    }

    return false;
}

function gb_filter_public_rest_endpoints( $endpoints ) {
    if ( is_user_logged_in() ) {
        return $endpoints;
    }

    foreach ( array_keys( $endpoints ) as $route ) {
        if ( gb_is_restricted_public_rest_route( $route ) ) {
            unset( $endpoints[ $route ] );
        }
    }

    return $endpoints;
}
add_filter( 'rest_endpoints', 'gb_filter_public_rest_endpoints' );

function gb_block_restricted_public_rest_requests( $result, $server, $request ) {
    if ( is_user_logged_in() ) {
        return $result;
    }

    if ( $request instanceof WP_REST_Request && gb_is_restricted_public_rest_route( $request->get_route() ) ) {
        return new WP_Error(
            'rest_no_route',
            'No route was found matching the URL and request method.',
            [ 'status' => 404 ]
        );
    }

    return $result;
}
add_filter( 'rest_pre_dispatch', 'gb_block_restricted_public_rest_requests', 10, 3 );

function gb_filter_public_rest_index_response( $response, $server, $request ) {
    if (
        is_user_logged_in() ||
        ! ( $request instanceof WP_REST_Request ) ||
        $request->get_route() !== '/' ||
        ! is_object( $response ) ||
        ! method_exists( $response, 'get_data' ) ||
        ! method_exists( $response, 'set_data' )
    ) {
        return $response;
    }

    $data = $response->get_data();
    if ( ! is_array( $data ) ) {
        return $response;
    }

    if ( ! empty( $data['namespaces'] ) && is_array( $data['namespaces'] ) ) {
        $data['namespaces'] = array_values(
            array_filter(
                $data['namespaces'],
                static function ( $namespace ) {
                    return ! gb_is_restricted_public_rest_route( '/' . trim( (string) $namespace, '/' ) );
                }
            )
        );
    }

    if ( ! empty( $data['routes'] ) && is_array( $data['routes'] ) ) {
        foreach ( array_keys( $data['routes'] ) as $route ) {
            if ( gb_is_restricted_public_rest_route( $route ) ) {
                unset( $data['routes'][ $route ] );
            }
        }
    }

    $response->set_data( $data );

    return $response;
}
add_filter( 'rest_post_dispatch', 'gb_filter_public_rest_index_response', 10, 3 );

add_filter( 'wp_is_application_passwords_available', '__return_false' );
add_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );

function gb_filter_document_title_parts( $title_parts ) {
    if ( gb_is_virtual_blog_request() ) {
        $title_parts['title'] = 'Eco Blog';
    } elseif ( gb_is_virtual_email_verification_request() ) {
        $title_parts['title'] = 'Verify Email';
    }

    return $title_parts;
}
add_filter( 'document_title_parts', 'gb_filter_document_title_parts' );

function gb_output_virtual_blog_canonical() {
    if ( gb_is_virtual_blog_request() ) {
        echo '<link rel="canonical" href="' . esc_url( gb_get_blog_url() ) . '">' . "\n";
        return;
    }

    if ( gb_is_virtual_email_verification_request() ) {
        echo '<link rel="canonical" href="' . esc_url( gb_get_verify_email_url() ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'gb_output_virtual_blog_canonical', 1 );

function gb_gate_unverified_users() {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    if ( gb_is_virtual_email_verification_request() ) {
        return;
    }

    if ( ! gb_should_gate_account_page_for_unverified_user() ) {
        return;
    }

    wp_safe_redirect( gb_get_verify_email_url( [ 'email_status' => 'check-inbox' ] ) );
    exit;
}
add_action( 'template_redirect', 'gb_gate_unverified_users', 1 );

/**
 * Never let any caching layer (browser, proxy, or a host-level cache such as
 * LiteSpeed Cache) store a response for these account-gated pages.
 *
 * These templates render completely differently depending on who is asking
 * (a redirect-to-login for a logged-out visitor vs. a personalized dashboard
 * for a logged-in one), so a cached copy of one visitor's response must never
 * be replayed to another. If it is, a fully logged-in user can be served a
 * stale "please log in" response that looks exactly like being logged out,
 * even though their session was never actually invalidated.
 */
function gb_get_account_gated_templates() {
    return array(
        'page-manager-dashboard.php',
        'page-cleaner-dashboard.php',
        // Prints the logged-in user's name and e-mail into the form, so a cached
        // copy would be replayed to the next visitor.
        'page-contact-support.php',
        'page-edit-profile.php',
        'page-schedule.php',
        'page-inbox.php',
        'page-my-account.php',
        'page-settings.php',
        'page-verify-profile.php',
        'page-book.php',
        'page-booking-success.php',
        'page-login.php',
        'page-register.php',
        'page-lost-password.php',
    );
}

/**
 * Emit every "do not cache" signal we can from PHP. This is deliberately
 * belt-and-braces: a stale logged-out redirect being replayed to a logged-in
 * manager (the "randomly logged out when opening Manage Cleaners" bug) is
 * caused by a full-page cache storing the anonymous 302→/login response, so we
 * want to be certain no layer ever stores one of these responses.
 *
 * NOTE: PHP-level headers/flags only stop a response from being *stored* on a
 * cache MISS. They cannot evict a copy already sitting in a server/edge cache,
 * nor do they run at all on a cache HIT (PHP is skipped). After deploying,
 * purge the LiteSpeed/host cache once and add a "Do Not Cache URIs" rule for
 * /manager-dashboard, /cleaner-dashboard, etc. plus "Do Not Cache Logged-in
 * Users" so the exclusion is enforced before the cache is even consulted.
 */
function gb_emit_account_page_nocache() {
    nocache_headers();

    // Respected by WP Super Cache, W3 Total Cache, WP Rocket, Comet Cache, etc.
    if ( ! defined( 'DONOTCACHEPAGE' ) )   { define( 'DONOTCACHEPAGE', true ); }
    if ( ! defined( 'DONOTCACHEOBJECT' ) ) { define( 'DONOTCACHEOBJECT', true ); }
    if ( ! defined( 'DONOTCACHEDB' ) )     { define( 'DONOTCACHEDB', true ); }

    // LiteSpeed Cache's documented opt-out API — a no-op if the plugin isn't active.
    do_action( 'litespeed_control_set_nocache', 'gb-account-page' );

    if ( ! headers_sent() ) {
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
        header( 'Pragma: no-cache', true );
        // Ensure any shared cache that ignores the above at least varies by the
        // auth cookie so a logged-out copy is never served to a logged-in user.
        header( 'Vary: Cookie', false );
    }
}

/**
 * True when the current main-query request is one of the account-gated pages,
 * resolved via the ACTUAL template file WordPress will load (not just the
 * '_wp_page_template' meta) so pages using the "page-{slug}.php" naming
 * convention are caught even when that meta was never set.
 */
function gb_is_account_gated_request() {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return false;
    }
    if ( ! is_page() ) {
        return false;
    }
    $template = get_page_template_slug();
    if ( $template && in_array( basename( $template ), gb_get_account_gated_templates(), true ) ) {
        return true;
    }
    // Fall back to the slug-based convention (e.g. /cleaner-dashboard/ → page-cleaner-dashboard.php).
    $slug = get_post_field( 'post_name', get_queried_object_id() );
    return $slug && in_array( 'page-' . $slug . '.php', gb_get_account_gated_templates(), true );
}

// Fire as early as possible so cache plugins that decide at template_redirect
// see DONOTCACHEPAGE, and so the anonymous redirect-to-login response these
// pages emit is itself never cacheable.
function gb_disable_cache_for_account_pages_early() {
    if ( gb_is_account_gated_request() ) {
        gb_emit_account_page_nocache();
    }
}
add_action( 'template_redirect', 'gb_disable_cache_for_account_pages_early', -100 );

// Belt-and-braces second pass against the resolved template file.
function gb_disable_cache_for_account_pages( $template ) {
    if ( $template && in_array( basename( $template ), gb_get_account_gated_templates(), true ) ) {
        gb_emit_account_page_nocache();
    }

    return $template;
}
add_filter( 'template_include', 'gb_disable_cache_for_account_pages', -10 );

function gb_handle_public_route_fixes() {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    if ( is_author() ) {
        wp_safe_redirect( gb_get_blog_url(), 301 );
        exit;
    }

    if ( is_singular( 'post' ) ) {
        $post_slug = get_post_field( 'post_name', get_queried_object_id() );
        if ( in_array( $post_slug, gb_get_hidden_blog_post_slugs(), true ) ) {
            wp_safe_redirect( gb_get_blog_url(), 301 );
            exit;
        }
    }

    /*
     * My Listings and Edit Listing lost their templates and now fall back to
     * page.php, rendering as an empty titled page that anyone can reach and a
     * search engine can index. They are real published pages returning 200, so
     * this has to run before the 404 gate below.
     */
    if ( is_page() ) {
        $orphans = array( 'manage-listings', 'edit-listing' );
        $slug    = get_post_field( 'post_name', get_queried_object_id() );
        if ( in_array( $slug, $orphans, true ) ) {
            wp_safe_redirect( home_url( '/cleaner-dashboard/' ), 301 );
            exit;
        }
    }

    if ( ! is_404() ) {
        return;
    }

    if ( gb_is_virtual_email_verification_request() ) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );
        include get_template_directory() . '/virtual-verify-email.php';
        exit;
    }

    if ( gb_is_virtual_blog_request() ) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );
        include get_template_directory() . '/virtual-blog.php';
        exit;
    }

    $legacy_redirects = [
        'contact-us'     => gb_get_contact_page_url(),
        'privacy-policy' => gb_get_privacy_page_url(),
        'legal'          => gb_get_terms_page_url(),
    ];

    $path_key = trim( gb_get_current_request_path(), '/' );
    if ( isset( $legacy_redirects[ $path_key ] ) ) {
        wp_safe_redirect( $legacy_redirects[ $path_key ], 301 );
        exit;
    }
}
add_action( 'template_redirect', 'gb_handle_public_route_fixes', 0 );


/* ==========================================================================
   1. CORE ROLES & SECURITY
   ========================================================================== */

// Register Custom Roles
function gb_register_roles() {
    add_role( 'gb_manager', 'Manager', array('read'=>true, 'edit_posts'=>true, 'edit_others_posts'=>true, 'upload_files'=>true));
    add_role( 'gb_cleaner', 'Cleaner', array('read'=>true, 'upload_files'=>true, 'edit_posts'=>false));
    add_role( 'gb_customer', 'Customer', array('read'=>true, 'upload_files'=>false, 'edit_posts'=>false));

    // add_role() is a no-op if the role already exists in the DB, so any
    // capability added here later would never reach a site whose roles were
    // created before this line was added. Sync the required caps explicitly.
    // 'edit_others_posts' lets a Manager crop/edit media-library images that
    // were uploaded by someone else (WordPress checks edit_post on the
    // attachment during image editing).
    $required_caps = array(
        'gb_manager'  => array('read', 'edit_posts', 'edit_others_posts', 'upload_files'),
        'gb_cleaner'  => array('read', 'upload_files'),
        'gb_customer' => array('read'),
    );
    foreach ( $required_caps as $role_key => $caps ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }
}
add_action('init', 'gb_register_roles');

// Hide Admin Bar for Non-Admins
function gb_disable_admin_bar($show) {
    if ( ! current_user_can('administrator') ) {
        return false;
    }
    return $show;
}
add_filter('show_admin_bar', 'gb_disable_admin_bar');

/**
 * Return true for the small set of WordPress media requests that front-end
 * managers and cleaners are allowed to use. The media modal can move between
 * upload.php, attachment edit screens, and the image editor while a single
 * selection is being made. Treating only one of those URLs as media causes an
 * apparent logout when the guard redirects the user back to their dashboard.
 */
function gb_is_allowed_media_admin_request() {
    if (
        ! is_admin()
        || wp_doing_ajax()
        || current_user_can( 'administrator' )
        || ! current_user_can( 'upload_files' )
    ) {
        return false;
    }

    $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    $media_screens = array( 'upload.php', 'media-new.php', 'media.php', 'async-upload.php' );
    if ( in_array( $pagenow, $media_screens, true ) ) {
        return true;
    }

    $target = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
    $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

    // The attachment list is how WordPress opens the gallery browser.
    if ( $pagenow === 'edit.php' && isset( $_REQUEST['post_type'] ) && sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) === 'attachment' ) {
        return true;
    }

    if ( $pagenow !== 'post.php' ) {
        return false;
    }

    // WordPress adds the image-editor flag to the crop/edit URL. Check the
    // flag itself because some hosting/WP combinations do not populate the
    // attachment post type before admin_init runs.
    if ( isset( $_REQUEST['image-editor'] ) || isset( $_REQUEST['image-editor-preview'] ) || $action === 'image-editor' ) {
        return true;
    }

    // Ordinary attachment details/edit screens remain scoped to attachments.
    return $target > 0 && get_post_type( $target ) === 'attachment';
}

/**
 * Keep the auth-cookie policy fresh when WordPress serves a media screen.
 * Media editing can be opened directly from the front-end picker, without a
 * new manager-dashboard page load to refresh the cookie first.
 */
function gb_refresh_media_auth_cookie_policy() {
    if ( ! gb_is_allowed_media_admin_request() || ! is_user_logged_in() ) {
        return;
    }

    $token = wp_get_session_token();
    if ( $token !== '' ) {
        wp_set_auth_cookie( get_current_user_id(), true, is_ssl(), $token );
    }
}
add_action( 'admin_init', 'gb_refresh_media_auth_cookie_policy', 20 );

// Redirect Non-Admins away from /wp-admin/
function gb_security_redirect() {
    // Allow selected unauthenticated admin-post actions.
    if (is_admin() && isset($_REQUEST['action'])) {
        $public_actions = [
            'gb_stripe_webhook',
            'gb_stripe_storefront_checkout',
            'gb_download_resource',
        ];
        if (in_array(sanitize_key(wp_unslash($_REQUEST['action'])), $public_actions, true)) {
            return;
        }
    }

    if ( gb_is_allowed_media_admin_request() ) {
        return;
    }

    if ( is_admin() && ! wp_doing_ajax() && ! current_user_can('administrator') ) {
        $user = wp_get_current_user();
        if ( $user instanceof WP_User && $user->exists() ) {
            wp_redirect( gb_get_post_login_redirect_url( $user ) );
        } else {
            wp_redirect( home_url() );
        }
        exit;
    }
}
add_action('admin_init', 'gb_security_redirect');

// Login Redirects
function gb_custom_login_redirect($redirect_to, $request, $user) {
    if ( $user instanceof WP_User && isset( $user->roles ) && is_array( $user->roles ) ) {
        return gb_get_post_login_redirect_url( $user );
    }

    return $redirect_to;
}
add_filter('login_redirect', 'gb_custom_login_redirect', 10, 3);


/* ==========================================================================
   2. DATA STRUCTURE (POST TYPES)
   ========================================================================== */

function gb_register_cpts() {
    // 1. Cleaners
    register_post_type('gb_cleaner', array(
        'labels' => array('name' => 'Cleaners', 'singular_name' => 'Cleaner'),
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-id',
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'comments'),
        'rewrite' => array('slug' => 'cleaner'),
    ));

    // 2. Services
    register_post_type('gb_service', array(
        'labels' => array('name' => 'Services', 'singular_name' => 'Service'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-list-view',
    ));

    // 2b. Eco Products / guides shown on the Eco Products page.
    // A repeatable post type rather than page meta: the catalogue is expected
    // to grow to dozens or hundreds of entries, which fixed fields cannot hold.
    // Public so each guide gets a real page. The catalogue now holds full
    // write-ups, not just links out to PDFs, so a product with content needs
    // somewhere to be read.
    register_post_type('gb_eco_product', array(
        'labels' => array('name' => 'Eco Products', 'singular_name' => 'Eco Product'),
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_nav_menus' => false,
        'has_archive' => false,
        'exclude_from_search' => false,
        'rewrite' => array('slug' => 'eco-guide', 'with_front' => false),
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes', 'excerpt'),
        'menu_icon' => 'dashicons-carrot',
    ));

    // 3. Bookings
    register_post_type('gb_booking', array(
        'labels' => array('name' => 'Bookings', 'singular_name' => 'Booking'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'custom-fields'),
        'menu_icon' => 'dashicons-calendar-alt',
        'capabilities' => array('create_posts' => 'do_not_allow'),
        'map_meta_cap' => true,
    ));

    // 4. Messages
    register_post_type('gb_message', array(
        'labels' => array('name' => 'Messages', 'singular_name' => 'Message'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'menu_icon' => 'dashicons-email',
    ));
}
add_action('init', 'gb_register_cpts');


/* ==========================================================================
   3. BOOKING ENGINE LOGIC
   ========================================================================== */

function gb_generate_booking_access_token($booking_id) {
    $raw_token = wp_generate_password(48, false, false);
    update_post_meta($booking_id, '_gb_booking_access_token', wp_hash($raw_token));

    return $raw_token;
}

function gb_booking_token_is_valid($booking_id, $raw_token) {
    $stored_hash = get_post_meta($booking_id, '_gb_booking_access_token', true);
    if (!$stored_hash || !$raw_token) {
        return false;
    }

    return hash_equals($stored_hash, wp_hash($raw_token));
}

function gb_find_service_post($identifier) {
    if ($identifier === null || $identifier === '') {
        return null;
    }

    if (is_numeric($identifier)) {
        $service_post = get_post((int) $identifier);
        if ($service_post && $service_post->post_type === 'gb_service' && $service_post->post_status === 'publish') {
            return $service_post;
        }
    }

    $name = sanitize_text_field((string) $identifier);
    if ($name === '') {
        return null;
    }

    $slug = sanitize_title($name);
    if ($slug !== '') {
        $service_post = get_page_by_path($slug, OBJECT, 'gb_service');
        if ($service_post && $service_post->post_type === 'gb_service' && $service_post->post_status === 'publish') {
            return $service_post;
        }
    }

    $service_query = new WP_Query([
        'post_type'      => 'gb_service',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'title'          => $name,
        'no_found_rows'  => true,
    ]);
    if (!empty($service_query->posts[0])) {
        $service_post = get_post((int) $service_query->posts[0]);
        if ($service_post && $service_post->post_type === 'gb_service' && $service_post->post_status === 'publish') {
            return $service_post;
        }
    }

    return null;
}

function gb_get_service_duration_hours($service_identifier, $fallback = 2) {
    $duration = (int) $fallback;
    if ($duration < 1) {
        $duration = 1;
    }

    $service_post = gb_find_service_post($service_identifier);
    if (!$service_post) {
        return $duration;
    }

    $stored_duration = (int) get_post_meta($service_post->ID, '_gb_service_duration', true);
    if ($stored_duration < 1) {
        return $duration;
    }

    return $stored_duration;
}

function gb_process_booking_submission() {
    if (!check_ajax_referer('gb_booking_nonce', 'security', false)) {
        wp_send_json_error(['message' => 'Security session expired. Please refresh the page.']);
    }

    $rate_identity = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
    $rate_state = gb_rate_limit_check('booking_ajax', 8, HOUR_IN_SECONDS, $rate_identity);
    if ($rate_state['blocked']) {
        wp_send_json_error(['message' => gb_get_rate_limit_message($rate_state)]);
    }
    gb_record_rate_limit_hit('booking_ajax', HOUR_IN_SECONDS, $rate_identity);

    $cleaner_id = isset($_POST['cleaner_id']) ? absint($_POST['cleaner_id']) : 0;
    $service_identifier = isset($_POST['service']) ? sanitize_text_field(wp_unslash($_POST['service'])) : '';
    $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
    $time_str = isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '';
    $name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
    $email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
    $phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
    $address = isset($_POST['customer_address']) ? sanitize_textarea_field(wp_unslash($_POST['customer_address'])) : '';

    if (
        !$cleaner_id ||
        get_post_type($cleaner_id) !== 'gb_cleaner' ||
        get_post_status($cleaner_id) !== 'publish'
    ) {
        wp_send_json_error(['message' => 'This cleaner is not currently available for booking.']);
    }

    $service_post = gb_find_service_post($service_identifier);
    if (!$service_post) {
        wp_send_json_error(['message' => 'Please choose an available service.']);
    }

    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    $date_errors = DateTimeImmutable::getLastErrors();
    if (
        !$date_object ||
        ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0)) ||
        $date_object->format('Y-m-d') !== $date ||
        $date < current_time('Y-m-d')
    ) {
        wp_send_json_error(['message' => 'Please choose a valid future booking date.']);
    }

    if (!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $time_str)) {
        wp_send_json_error(['message' => 'Please choose a valid booking time.']);
    }

    if (!$name || !is_email($email) || !$phone || !$address) {
        wp_send_json_error(['message' => 'Please complete all booking details.']);
    }

    $service_name = sanitize_text_field($service_post->post_title);

    // Duration is always read from the selected, published service.
    $duration = gb_get_service_duration_hours($service_post->ID, 2);

    // Double Booking Check
    $new_start = strtotime("$date $time_str");
    $new_end = $new_start + ($duration * 3600);
    $existing = new WP_Query([
        'post_type' => 'gb_booking',
        'post_status' => ['publish'], 
        'posts_per_page' => -1,
        'meta_query' => [ 'relation' => 'AND', ['key' => '_gb_booking_cleaner_id', 'value' => $cleaner_id], ['key' => '_gb_booking_date', 'value' => $date] ]
    ]);

    if ($existing->have_posts()) {
        while ($existing->have_posts()) {
            $existing->the_post();
            $ex_time = get_post_meta(get_the_ID(), '_gb_booking_time', true);
            $ex_dur = get_post_meta(get_the_ID(), '_gb_booking_duration', true);
            $ex_start = strtotime("$date $ex_time");
            $ex_end = $ex_start + ($ex_dur * 3600);
            if ($new_start < $ex_end && $new_end > $ex_start) {
                wp_send_json_error(['message' => '⚠️ Time slot taken. Please select another time.']);
            }
        }
        wp_reset_postdata();
    }

    // Create Booking
    $booking_title = sprintf('%s - %s (%s)', $name, $date, $time_str);
    $booking_id = wp_insert_post(['post_type'=>'gb_booking', 'post_title'=>$booking_title, 'post_status'=>'publish']);

    if ($booking_id) {
        update_post_meta($booking_id, '_gb_booking_cleaner_id', $cleaner_id);
        update_post_meta($booking_id, '_gb_booking_service', $service_name);
        update_post_meta($booking_id, '_gb_booking_date', $date);
        update_post_meta($booking_id, '_gb_booking_time', $time_str);
        update_post_meta($booking_id, '_gb_booking_duration', $duration);
        update_post_meta($booking_id, '_gb_customer_name', $name);
        update_post_meta($booking_id, '_gb_customer_email', $email);
        update_post_meta($booking_id, '_gb_customer_phone', $phone);
        update_post_meta($booking_id, '_gb_customer_address', $address);
        update_post_meta($booking_id, '_gb_booking_status', 'confirmed');

        $access_token = gb_generate_booking_access_token($booking_id);
        wp_send_json_success(['message'=>'Booking confirmed!', 'redirect'=>home_url('/booking-success?bid='.$booking_id.'&t='.rawurlencode($access_token))]);
    } else {
        wp_send_json_error(['message'=>'System error.']);
    }
}
add_action('wp_ajax_gb_process_booking', 'gb_process_booking_submission');
add_action('wp_ajax_nopriv_gb_process_booking', 'gb_process_booking_submission');


/* ==========================================================================
   4. MESSAGING SYSTEM
   ========================================================================== */

function gb_send_cleaner_message() {
    if (!empty($_POST['gb_honeypot'])) wp_send_json_error(['message'=>'Spam detected.']);
    check_ajax_referer('gb_msg_nonce', 'security');
    
    $cleaner_id = intval($_POST['cleaner_id']);
    $name = sanitize_text_field($_POST['sender_name']);
    $email = sanitize_email($_POST['sender_email']);
    $body = sanitize_textarea_field($_POST['message_body']);

    // Same anti-spam layers as the contact/helpdesk form, so spam can't simply
    // move to this endpoint instead.
    $rate_state = gb_rate_limit_check('cleaner_message', 3, HOUR_IN_SECONDS, $email);
    $ip_state   = gb_rate_limit_check('cleaner_message_ip', 6, HOUR_IN_SECONDS);
    if ($rate_state['blocked'] || $ip_state['blocked']) {
        wp_send_json_error(['message' => gb_get_rate_limit_message($rate_state['blocked'] ? $rate_state : $ip_state)]);
    }
    gb_record_rate_limit_hit('cleaner_message', HOUR_IN_SECONDS, $email);
    gb_record_rate_limit_hit('cleaner_message_ip', HOUR_IN_SECONDS);

    if (!$name || !$email || !$body) wp_send_json_error(['message'=>'Please fill in all fields.']);

    // Identical payload replayed, or promotional content: acknowledge success
    // so bots get nothing to tune against, but store nothing.
    $subject_line = 'Inquiry from ' . $name;
    if (
        gb_message_is_duplicate($email, $subject_line, $body)
        || gb_message_looks_like_spam($name, $email, $subject_line, $body)
    ) {
        wp_send_json_success(['message' => 'Message sent!']);
    }

    $msg_id = wp_insert_post(['post_type'=>'gb_message', 'post_title'=>'Inquiry from '.$name, 'post_content'=>$body, 'post_status'=>'publish']);

    if ($msg_id) {
        // Only attach a related cleaner when we genuinely have a valid cleaner
        // target. Without this guard a missing/zero cleaner_id (or a manager-
        // directed message) gets bucketed under whichever cleaner post the
        // stray ID resolves to in the inbox, which is how manager messages
        // ended up showing "To: Alston Knight".
        if ($cleaner_id > 0 && get_post_type($cleaner_id) === 'gb_cleaner') {
            update_post_meta($msg_id, '_gb_related_cleaner', $cleaner_id);
        }
        update_post_meta($msg_id, '_gb_sender_name', $name);
        update_post_meta($msg_id, '_gb_sender_email', $email);
        update_post_meta($msg_id, '_gb_responded_date', '');
        gb_notify_support_message($msg_id, 'Cleaner enquiry');
        wp_send_json_success(['message'=>'Message sent!']);
    } else {
        wp_send_json_error(['message'=>'System error.']);
    }
}
add_action('wp_ajax_gb_send_message', 'gb_send_cleaner_message');
add_action('wp_ajax_nopriv_gb_send_message', 'gb_send_cleaner_message');


/* ==========================================================================
   5. UTILITY & HELPDESK ENGINE
   ========================================================================== */

function gb_is_verified_client($email, $cleaner_id) {
    if (!$email || !$cleaner_id) return false;
    $bookings = new WP_Query([
        'post_type' => 'gb_booking', 'post_status' => 'publish',
        'meta_query' => [ 'relation' => 'AND', ['key' => '_gb_booking_cleaner_id', 'value' => $cleaner_id], ['key' => '_gb_customer_email', 'value' => $email] ],
        'fields' => 'ids'
    ]);
    return $bookings->have_posts();
}

function gb_get_meta($pid, $key, $default = '') {
    $val = get_post_meta($pid, $key, true);
    return $val ? $val : $default;
}

/**
 * DIRECT CONTACT FORM PROCESSOR
 */
add_action('init', function() {
    if (isset($_POST['gb_contact_action']) && $_POST['gb_contact_action'] == 'submit_helpdesk') {
        if (
            empty($_POST['gb_contact_nonce']) ||
            !wp_verify_nonce($_POST['gb_contact_nonce'], 'gb_contact_submit')
        ) {
            return;
        }

        if (!empty($_POST['gb_contact_website'])) {
            return;
        }

        $name    = isset($_POST['c_name']) ? sanitize_text_field(wp_unslash($_POST['c_name'])) : '';
        $email   = isset($_POST['c_email']) ? sanitize_email(wp_unslash($_POST['c_email'])) : '';
        $subject = isset($_POST['c_subject']) ? sanitize_text_field(wp_unslash($_POST['c_subject'])) : '';
        $message = isset($_POST['c_message']) ? wp_kses_post(wp_unslash($_POST['c_message'])) : '';

        $contact_referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();

        // Bots post instantly (or replay a scraped page). Report success so they
        // get no feedback to tune against, but store nothing.
        $timing_token = isset($_POST['gb_contact_ts']) ? sanitize_text_field(wp_unslash($_POST['gb_contact_ts'])) : '';
        if (gb_form_timing_too_fast($timing_token)) {
            wp_safe_redirect(add_query_arg('contact_success', '1', $contact_referer));
            exit;
        }

        // Rate limit on (email + IP) AND on IP alone, so rotating the sender
        // address no longer resets the counter — that loophole is why the same
        // spam arrived five times over.
        $rate_state = gb_rate_limit_check('contact_form', 3, HOUR_IN_SECONDS, $email);
        $ip_state   = gb_rate_limit_check('contact_form_ip', 6, HOUR_IN_SECONDS);
        if ($rate_state['blocked'] || $ip_state['blocked']) {
            wp_safe_redirect(add_query_arg('contact_error', '1', $contact_referer));
            exit;
        }
        gb_record_rate_limit_hit('contact_form', HOUR_IN_SECONDS, $email);
        gb_record_rate_limit_hit('contact_form_ip', HOUR_IN_SECONDS);

        // Identical payload replayed — keep the first, drop the repeats.
        if (gb_message_is_duplicate($email, $subject, $message)) {
            wp_safe_redirect(add_query_arg('contact_success', '1', $contact_referer));
            exit;
        }

        // Content scoring. Flagged messages are quarantined rather than
        // discarded, so a false positive is recoverable from the Spam view
        // instead of silently lost.
        $is_spam = gb_message_looks_like_spam($name, $email, $subject, $message);

        if (!$name || !is_email($email) || !$subject || !$message) {
            $referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();
            wp_safe_redirect(add_query_arg('contact_error', '1', $referer));
            exit;
        }

        $ticket_id = wp_insert_post([
            'post_title'   => $subject,
            'post_content' => $message,
            'post_status'  => 'publish',
            'post_type'    => 'gb_message' 
        ]);

        if ($ticket_id) {
            update_post_meta($ticket_id, '_gb_sender_name', $name);
            update_post_meta($ticket_id, '_gb_sender_email', $email);
            // This flag is what tab-helpdesk.php filters by
            update_post_meta($ticket_id, '_gb_is_helpdesk', '1');

            if ($is_spam) {
                // Quarantined: kept out of Active/Resolved and never emailed on,
                // but reviewable (and bulk-deletable) from the Spam view.
                update_post_meta($ticket_id, '_gb_spam', '1');
            } else {
                gb_notify_support_message($ticket_id, 'Support');
            }

            // Redirect with success flag for page-contactus.php
            $referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();
            wp_safe_redirect(add_query_arg('contact_success', '1', $referer));
            exit;
        }
    }
});

/**
 * Redirect Lost Password to Custom Branded Page
 */
add_action('login_form_lostpassword', 'gb_redirect_to_custom_lostpassword');
function gb_redirect_to_custom_lostpassword() {
    if ('GET' == $_SERVER['REQUEST_METHOD']) {
        // Change '/lost-password' to match the actual slug of the page you create in WP
        wp_redirect(home_url('/lost-password'));
        exit;
    }
}

/**
 * Shared front-end "Change Password" handler.
 *
 * Both the cleaner Account Settings page and the manager dashboard post here.
 * It verifies the current password, applies the new one, and — because
 * wp_set_password() invalidates the current session — immediately re-issues an
 * auth cookie so the user is NOT kicked out after changing their password.
 * Result is reported back via a ?pw= flag on the page they came from.
 */
add_action('init', function () {
    if (!isset($_POST['gb_pw_action']) || $_POST['gb_pw_action'] !== 'change_password') {
        return;
    }

    $redirect = '';
    if (!empty($_POST['gb_pw_redirect'])) {
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['gb_pw_redirect'])), '');
    }
    if ($redirect === '') {
        $redirect = wp_get_referer() ? wp_get_referer() : home_url('/');
    }
    $bounce = function ($status) use ($redirect) {
        wp_safe_redirect(add_query_arg('pw', $status, remove_query_arg('pw', $redirect)));
        exit;
    };

    if (!is_user_logged_in()) {
        $bounce('error');
    }
    if (empty($_POST['gb_pw_nonce']) || !wp_verify_nonce($_POST['gb_pw_nonce'], 'gb_change_password')) {
        $bounce('error');
    }

    $user    = wp_get_current_user();
    $current = isset($_POST['gb_current_password']) ? (string) wp_unslash($_POST['gb_current_password']) : '';
    $new     = isset($_POST['gb_new_password']) ? (string) wp_unslash($_POST['gb_new_password']) : '';
    $confirm = isset($_POST['gb_confirm_password']) ? (string) wp_unslash($_POST['gb_confirm_password']) : '';

    if ($current === '' || $new === '' || $confirm === '') {
        $bounce('missing');
    }
    if (!wp_check_password($current, $user->user_pass, $user->ID)) {
        $bounce('wrong');
    }
    if ($new !== $confirm) {
        $bounce('mismatch');
    }
    if (strlen($new) < 8) {
        $bounce('weak');
    }

    wp_set_password($new, $user->ID);
    // Keep the user signed in after the password change.
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    $bounce('success');
}, 5);

/**
 * Render a status banner for the shared password handler's ?pw= flag.
 * Returns an HTML string (empty when there is nothing to show).
 */
function gb_password_notice_html() {
    if (!isset($_GET['pw'])) {
        return '';
    }
    $status = sanitize_key(wp_unslash($_GET['pw']));
    $map = [
        'success'  => ['#f0fdf4', '#dcfce7', '#166534', '✅ Your password has been updated.'],
        'wrong'    => ['#fef2f2', '#fee2e2', '#991b1b', 'Your current password was incorrect.'],
        'mismatch' => ['#fef2f2', '#fee2e2', '#991b1b', 'The new passwords did not match.'],
        'weak'     => ['#fffbeb', '#fde68a', '#92400e', 'Please choose a password with at least 8 characters.'],
        'missing'  => ['#fffbeb', '#fde68a', '#92400e', 'Please fill in all three password fields.'],
        'error'    => ['#fef2f2', '#fee2e2', '#991b1b', 'Could not change your password. Please try again.'],
    ];
    if (!isset($map[$status])) {
        return '';
    }
    list($bg, $border, $color, $text) = $map[$status];
    return '<div style="background:' . $bg . '; border:1px solid ' . $border . '; color:' . $color . '; padding:14px 16px; border-radius:8px; margin-bottom:20px; font-size:14px;">' . esc_html($text) . '</div>';
}

/**
 * Shared in-dashboard message reply handler.
 *
 * Used by BOTH the manager inbox/helpdesk and the cleaner inbox: it emails the
 * message sender and marks the message replied, so the "Replied" status always
 * reflects a reply actually being sent from within the platform (the old flow
 * was a bare mailto: that never recorded anything).
 *
 * Authorization: managers/admins may reply to any message; a cleaner may only
 * reply to a message tied to their own cleaner profile.
 *
 * NOTE: delivery still depends on wp_mail being backed by a working mail
 * service. wp_mail() can report success even when the host silently drops the
 * message, so a "reply_sent" flag means "handed to wp_mail", not "delivered".
 */
add_action('init', function () {
    if (!isset($_POST['gb_reply_action']) || $_POST['gb_reply_action'] !== 'reply_message') {
        return;
    }
    if (empty($_POST['gb_reply_nonce']) || !wp_verify_nonce($_POST['gb_reply_nonce'], 'gb_reply_message')) {
        wp_die('Security check failed.');
    }
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    $mid = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $reply_body = isset($_POST['gb_reply_body']) ? sanitize_textarea_field(wp_unslash($_POST['gb_reply_body'])) : '';

    $redirect = '';
    if (!empty($_POST['gb_reply_redirect'])) {
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['gb_reply_redirect'])), '');
    }
    if ($redirect === '') {
        $redirect = home_url('/manager-dashboard?tab=support&action=view_message&id=' . $mid);
    }
    $bounce = function ($flag) use ($redirect) {
        wp_safe_redirect(add_query_arg('msg', $flag, $redirect));
        exit;
    };

    if (get_post_type($mid) !== 'gb_message' || $reply_body === '') {
        $bounce('reply_error');
    }

    // Authorization.
    $user = wp_get_current_user();
    $can_reply = current_user_can('manage_options') || in_array('gb_manager', (array) $user->roles, true);
    if (!$can_reply) {
        $related = (int) get_post_meta($mid, '_gb_related_cleaner', true);
        if ($related) {
            $owned = get_posts([
                'post_type'   => 'gb_cleaner',
                'author'      => $user->ID,
                'include'     => [$related],
                'post_status' => ['publish', 'pending', 'draft'],
                'fields'      => 'ids',
            ]);
            $can_reply = !empty($owned);
        }
    }
    if (!$can_reply) {
        wp_die('You are not allowed to reply to this message.');
    }

    $to = sanitize_email((string) get_post_meta($mid, '_gb_sender_email', true));
    $sent = false;
    if (is_email($to)) {
        $settings_id = (int) get_option('page_on_front');
        $from_name = $settings_id ? (string) get_post_meta($settings_id, '_gb_email_from_name', true) : '';
        $from_name = $from_name !== '' ? $from_name : get_bloginfo('name');
        $reply_to = $settings_id ? (string) get_post_meta($settings_id, '_gb_email_reply_to', true) : '';
        if (!is_email($reply_to)) {
            $reply_to = $settings_id ? (string) get_post_meta($settings_id, '_gb_support_email', true) : '';
        }
        if (!is_email($reply_to)) {
            $reply_to = function_exists('gb_get_support_email') ? gb_get_support_email() : get_option('admin_email');
        }

        $subject = 'Re: ' . get_the_title($mid);
        $body = $reply_body . "\n\n—\n" . $from_name;
        $from_email = function_exists('gb_get_email_from_address') ? gb_get_email_from_address() : get_option('admin_email');
        $headers = ['From: ' . $from_name . ' <' . $from_email . '>'];
        if (is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        $sent = gb_send_brevo_template_email(
            $to,
            $subject,
            $body,
            $headers,
            gb_get_brevo_template_id( 'support_reply' ),
            [
                'first_name'     => gb_brevo_first_name( (string) get_post_meta($mid, '_gb_sender_name', true) ),
                'reply_message'  => $reply_body,
                'inbox_url'      => gb_get_email_recipient_url( $to ),
            ]
        );
    }

    update_post_meta($mid, '_gb_responded_date', current_time('mysql'));
    update_post_meta($mid, '_gb_reply_body', $reply_body);

    $bounce($sent ? 'reply_sent' : 'replied_nomail');
}, 5);

/**
 * Bulk message actions for the manager Inbox / Helpdesk.
 *
 * Supports:
 *   - delete_messages : permanently delete the ticked messages
 *   - scan_spam       : score existing messages and flag matches as _gb_spam,
 *                       so an accumulated backlog can be reviewed in one view
 *   - purge_spam      : permanently delete everything flagged as spam
 *
 * Deletes are permanent (these are spam/support messages, not content), and
 * every path is nonce-checked and restricted to managers/admins.
 */
add_action('init', function () {
    if (!isset($_POST['gb_bulk_action'])) {
        return;
    }
    $bulk_action = sanitize_key(wp_unslash($_POST['gb_bulk_action']));
    if (!in_array($bulk_action, ['delete_messages', 'scan_spam', 'purge_spam'], true)) {
        return;
    }
    if (empty($_POST['gb_bulk_nonce']) || !wp_verify_nonce($_POST['gb_bulk_nonce'], 'gb_bulk_messages')) {
        wp_die('Security check failed.');
    }
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }
    $user = wp_get_current_user();
    if (!current_user_can('administrator') && !in_array('gb_manager', (array) $user->roles, true)) {
        wp_die('You are not allowed to do that.');
    }

    $redirect = '';
    if (!empty($_POST['gb_bulk_redirect'])) {
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['gb_bulk_redirect'])), '');
    }
    if ($redirect === '') {
        $redirect = home_url('/manager-dashboard?tab=helpdesk');
    }

    $done = 0;

    if ($bulk_action === 'delete_messages') {
        $ids = isset($_POST['message_ids']) && is_array($_POST['message_ids']) ? array_map('intval', (array) $_POST['message_ids']) : [];
        foreach ($ids as $id) {
            if ($id && get_post_type($id) === 'gb_message') {
                wp_delete_post($id, true);
                $done++;
            }
        }
        $flag = 'bulk_deleted';
    } elseif ($bulk_action === 'scan_spam') {
        $candidates = get_posts([
            'post_type'      => 'gb_message',
            'post_status'    => 'any',
            'posts_per_page' => 2000,
            'fields'         => 'ids',
        ]);
        // First pass: fingerprint every message so identical mass-repeats can be
        // caught on volume alone, whatever the wording.
        $groups = [];
        $rows = [];
        foreach ($candidates as $id) {
            $post = get_post($id);
            if (!$post) {
                continue;
            }
            $fingerprint = md5(strtolower(trim($post->post_title)) . '|' . strtolower(trim($post->post_content)));
            $groups[$fingerprint] = isset($groups[$fingerprint]) ? $groups[$fingerprint] + 1 : 1;
            $rows[$id] = [
                'post'        => $post,
                'fingerprint' => $fingerprint,
            ];
        }

        foreach ($rows as $id => $row) {
            if (get_post_meta($id, '_gb_spam', true) === '1') {
                continue;
            }
            $post = $row['post'];
            $sname = (string) get_post_meta($id, '_gb_sender_name', true);
            $semail = (string) get_post_meta($id, '_gb_sender_email', true);

            $is_bulk_repeat = $groups[$row['fingerprint']] >= 3;

            if ($is_bulk_repeat || gb_message_looks_like_spam($sname, $semail, $post->post_title, $post->post_content)) {
                update_post_meta($id, '_gb_spam', '1');
                $done++;
            }
        }
        $flag = 'spam_scanned';
    } else { // purge_spam
        $spam_ids = get_posts([
            'post_type'      => 'gb_message',
            'post_status'    => 'any',
            'posts_per_page' => 2000,
            'fields'         => 'ids',
            'meta_query'     => [[ 'key' => '_gb_spam', 'value' => '1' ]],
        ]);
        foreach ($spam_ids as $id) {
            wp_delete_post($id, true);
            $done++;
        }
        $flag = 'spam_purged';
    }

    wp_safe_redirect(add_query_arg(['msg' => $flag, 'n' => $done], $redirect));
    exit;
}, 5);

/**
 * One-time cleanup of legacy mis-tagged support messages.
 *
 * Helpdesk / manager-directed messages (_gb_is_helpdesk = '1') should never
 * carry a _gb_related_cleaner. Older code paths sometimes attached one, which
 * made those messages display under a specific cleaner ("To: Alston Knight")
 * in the manager inbox. Clear that stray meta once so historical tickets show
 * correctly. Genuine customer→cleaner enquiries (not helpdesk-flagged) are
 * left untouched. Runs a single time when a manager/admin is on the site.
 */
add_action('init', function () {
    if (get_option('gb_helpdesk_relation_cleanup_v1')) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    $user = wp_get_current_user();
    if (!current_user_can('manage_options') && !in_array('gb_manager', (array) $user->roles, true)) {
        return;
    }

    $ids = get_posts([
        'post_type'      => 'gb_message',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_gb_is_helpdesk', 'value' => '1'],
            ['key' => '_gb_related_cleaner', 'compare' => 'EXISTS'],
        ],
    ]);
    foreach ($ids as $id) {
        delete_post_meta($id, '_gb_related_cleaner');
    }
    update_option('gb_helpdesk_relation_cleanup_v1', 1);
}, 20);

/* ==========================================================================
   6. STRIPE API SETTINGS ENGINE
   ========================================================================== */

function gb_get_stripe_webhook_url() {
    return admin_url('admin-post.php?action=gb_stripe_webhook');
}

function gb_get_allowed_payout_intervals() {
    return ['manual', 'daily', 'weekly', 'monthly'];
}

function gb_normalize_payout_interval($interval) {
    $interval = sanitize_key((string) $interval);
    return in_array($interval, gb_get_allowed_payout_intervals(), true) ? $interval : 'daily';
}

function gb_find_cleaner_by_account_id($account_id) {
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return 0;
    }

    $query = new WP_Query([
        'post_type'      => 'gb_cleaner',
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            ['key' => '_gb_stripe_account_id', 'value' => $account_id],
        ],
        'no_found_rows'  => true,
    ]);

    return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
}

function gb_cleaner_connect_status_label($charges_enabled, $payouts_enabled, $details_submitted, $requirements_due_count) {
    if (!$details_submitted) {
        return 'needs_onboarding';
    }
    if ($requirements_due_count > 0) {
        return 'restricted';
    }
    if ($charges_enabled && $payouts_enabled) {
        return 'active';
    }
    return 'pending_review';
}

function gb_get_connect_country_code() {
    return 'GB';
}

function gb_stripe_unwrap_data_object($payload) {
    if (is_array($payload) && isset($payload['data']) && is_array($payload['data']) && !isset($payload['id'])) {
        return $payload['data'];
    }
    return is_array($payload) ? $payload : [];
}

function gb_get_connect_account_from_stripe($account_id) {
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Missing connected account ID.');
    }

    // Prefer Connect v2 retrieval for onboarding requirement status.
    $account_v2 = gb_stripe_api_request_v2(
        'core/accounts/' . rawurlencode($account_id),
        ['include' => ['configuration.recipient', 'requirements']],
        'GET'
    );
    if (!is_wp_error($account_v2)) {
        $unwrapped = gb_stripe_unwrap_data_object($account_v2);
        if (!empty($unwrapped['id'])) {
            return $unwrapped;
        }
    }

    // Backward-compatible fallback for existing v1 Express accounts.
    return gb_stripe_api_request('accounts/' . rawurlencode($account_id), [], 'GET');
}

function gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account) {
    $cleaner_id = absint($cleaner_id);
    $account = gb_stripe_unwrap_data_object($account);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner' || !is_array($account) || empty($account['id'])) {
        return;
    }

    $is_v2_account = isset($account['configuration']['recipient']) || isset($account['defaults']['responsibilities']);

    if ($is_v2_account) {
        $transfer_status = isset($account['configuration']['recipient']['capabilities']['stripe_balance']['stripe_transfers']['status'])
            ? sanitize_key((string) $account['configuration']['recipient']['capabilities']['stripe_balance']['stripe_transfers']['status'])
            : '';
        $requirements_status = isset($account['requirements']['summary']['minimum_deadline']['status'])
            ? sanitize_key((string) $account['requirements']['summary']['minimum_deadline']['status'])
            : '';

        $charges_enabled = $transfer_status === 'active';
        $payouts_enabled = $transfer_status === 'active';
        $details_submitted = !in_array($requirements_status, ['currently_due', 'past_due'], true);
        $requirements_due = isset($account['requirements']['currently_due']) && is_array($account['requirements']['currently_due'])
            ? $account['requirements']['currently_due']
            : [];
        $requirements_due_count = count($requirements_due);
        if ($requirements_due_count === 0 && in_array($requirements_status, ['currently_due', 'past_due'], true)) {
            $requirements_due_count = 1;
        }
    } else {
        $charges_enabled = !empty($account['charges_enabled']);
        $payouts_enabled = !empty($account['payouts_enabled']);
        $details_submitted = !empty($account['details_submitted']);
        $requirements_due = isset($account['requirements']['currently_due']) && is_array($account['requirements']['currently_due'])
            ? $account['requirements']['currently_due']
            : [];
        $requirements_due_count = count($requirements_due);
    }

    $payout_interval = 'daily';
    if (isset($account['settings']['payouts']['schedule']['interval'])) {
        $payout_interval = gb_normalize_payout_interval($account['settings']['payouts']['schedule']['interval']);
    } elseif (isset($account['balance_settings']['payments']['payouts']['schedule']['interval'])) {
        $payout_interval = gb_normalize_payout_interval($account['balance_settings']['payments']['payouts']['schedule']['interval']);
    } else {
        $payout_interval = gb_normalize_payout_interval(get_post_meta($cleaner_id, '_gb_stripe_payout_interval', true));
    }

    update_post_meta($cleaner_id, '_gb_stripe_account_id', sanitize_text_field($account['id']));
    update_post_meta($cleaner_id, '_gb_stripe_charges_enabled', $charges_enabled ? '1' : '0');
    update_post_meta($cleaner_id, '_gb_stripe_payouts_enabled', $payouts_enabled ? '1' : '0');
    update_post_meta($cleaner_id, '_gb_stripe_details_submitted', $details_submitted ? '1' : '0');
    update_post_meta($cleaner_id, '_gb_stripe_requirements_due_count', (int) $requirements_due_count);
    update_post_meta($cleaner_id, '_gb_stripe_requirements_due_json', wp_json_encode($requirements_due));
    update_post_meta($cleaner_id, '_gb_stripe_payout_interval', $payout_interval);
    $country = isset($account['country']) ? $account['country'] : (isset($account['identity']['country']) ? $account['identity']['country'] : gb_get_connect_country_code());
    update_post_meta($cleaner_id, '_gb_stripe_country', sanitize_text_field((string) $country));
    update_post_meta(
        $cleaner_id,
        '_gb_stripe_connect_status',
        gb_cleaner_connect_status_label($charges_enabled, $payouts_enabled, $details_submitted, $requirements_due_count)
    );
    update_post_meta($cleaner_id, '_gb_stripe_last_sync', current_time('mysql'));
}

function gb_update_connected_account_payout_schedule($account_id, $interval) {
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Missing connected account ID.');
    }

    $interval = gb_normalize_payout_interval($interval);
    $params = ['settings[payouts][schedule][interval]' => $interval];
    if ($interval === 'weekly') {
        $params['settings[payouts][schedule][weekly_anchor]'] = 'monday';
    } elseif ($interval === 'monthly') {
        $params['settings[payouts][schedule][monthly_anchor]'] = 1;
    }

    return gb_stripe_api_request('accounts/' . rawurlencode($account_id), $params, 'POST');
}

function gb_sync_cleaner_connect_account($cleaner_id) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return new WP_Error('gb_invalid_cleaner', 'Invalid cleaner profile.');
    }

    $account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Cleaner has no Stripe Connect account yet.');
    }

    $account = gb_get_connect_account_from_stripe($account_id);
    if (is_wp_error($account)) {
        update_post_meta($cleaner_id, '_gb_stripe_last_error', $account->get_error_message());
        return $account;
    }

    gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account);
    delete_post_meta($cleaner_id, '_gb_stripe_last_error');

    return $account;
}

function gb_create_connect_account_for_cleaner($cleaner_id) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return new WP_Error('gb_invalid_cleaner', 'Invalid cleaner profile.');
    }

    $existing = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($existing !== '') {
        return ['id' => $existing];
    }

    $cleaner_post = get_post($cleaner_id);
    $owner = $cleaner_post ? get_user_by('id', (int) $cleaner_post->post_author) : null;
    $owner_email = $owner ? sanitize_email($owner->user_email) : '';
    $display_name = $cleaner_post ? sanitize_text_field($cleaner_post->post_title) : ('Cleaner ' . $cleaner_id);
    if ($display_name === '') {
        $display_name = 'Cleaner ' . $cleaner_id;
    }
    $payout_interval = gb_normalize_payout_interval(get_post_meta($cleaner_id, '_gb_stripe_payout_interval', true));
    $country_code = strtolower(gb_get_connect_country_code());

    /**
     * Connect v2 account creation (Stripe prompt-aligned):
     * - No top-level "type"
     * - dashboard="express"
     * - platform is responsible for fees/losses
     */
    $v2_payload = [
        'display_name' => $display_name,
        'identity' => [
            'country' => $country_code,
        ],
        'dashboard' => 'express',
        'defaults' => [
            'responsibilities' => [
                'fees_collector' => 'application',
                'losses_collector' => 'application',
            ],
        ],
        'configuration' => [
            'recipient' => [
                'capabilities' => [
                    'stripe_balance' => [
                        'stripe_transfers' => [
                            'requested' => true,
                        ],
                    ],
                ],
            ],
        ],
    ];
    if ($owner_email !== '') {
        $v2_payload['contact_email'] = $owner_email;
    }

    $account = gb_stripe_api_request_v2('core/accounts', $v2_payload, 'POST');

    // Compatibility fallback if v2 is not enabled yet on the Stripe account.
    if (is_wp_error($account)) {
        $legacy_params = [
            'type'                                    => 'express',
            'country'                                 => strtoupper($country_code),
            'business_type'                           => 'individual',
            'capabilities[card_payments][requested]'  => 'true',
            'capabilities[transfers][requested]'      => 'true',
            'metadata[cleaner_id]'                    => (string) $cleaner_id,
            'settings[payouts][schedule][interval]'   => $payout_interval,
        ];
        if ($owner_email !== '') {
            $legacy_params['email'] = $owner_email;
        }
        $account = gb_stripe_api_request('accounts', $legacy_params, 'POST');
    }

    if (is_wp_error($account)) {
        update_post_meta($cleaner_id, '_gb_stripe_last_error', $account->get_error_message());
        return $account;
    }

    $account = gb_stripe_unwrap_data_object($account);
    if (empty($account['id'])) {
        return new WP_Error('gb_connect_create_failed', 'Stripe did not return a connected account ID.');
    }

    update_post_meta($cleaner_id, '_gb_stripe_account_id', sanitize_text_field($account['id']));
    gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account);

    // Keep payouts aligned with manager preference once account exists.
    $schedule_sync = gb_update_connected_account_payout_schedule($account['id'], $payout_interval);
    if (is_wp_error($schedule_sync)) {
        update_post_meta($cleaner_id, '_gb_stripe_last_error', $schedule_sync->get_error_message());
    } else {
        delete_post_meta($cleaner_id, '_gb_stripe_last_error');
    }

    return $account;
}

function gb_connect_user_can_manage_cleaner($cleaner_id, $user) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return false;
    }

    if (current_user_can('administrator') || in_array('gb_manager', (array) $user->roles, true)) {
        return true;
    }

    if (in_array('gb_cleaner', (array) $user->roles, true)) {
        $cleaner_post = get_post($cleaner_id);
        return $cleaner_post && (int) $cleaner_post->post_author === (int) $user->ID;
    }

    return false;
}

function gb_connect_default_redirect_url($cleaner_id, $user) {
    if (current_user_can('administrator') || in_array('gb_manager', (array) $user->roles, true)) {
        return home_url('/manager-dashboard?tab=cleaners&action=edit&id=' . absint($cleaner_id));
    }
    return home_url('/cleaner-dashboard');
}

function gb_connect_redirect_with_notice($redirect_to, $status, $message = '') {
    $redirect_to = esc_url_raw($redirect_to ?: home_url('/cleaner-dashboard'));
    $args = ['connect' => sanitize_key($status)];
    if ($message !== '') {
        $args['connect_msg'] = sanitize_text_field($message);
    }
    wp_safe_redirect(add_query_arg($args, $redirect_to));
    exit;
}

function gb_handle_connect_onboarding() {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    $cleaner_id = isset($_GET['cleaner_id']) ? absint($_GET['cleaner_id']) : 0;
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';

    if ($redirect_to === '') {
        $redirect_to = gb_connect_default_redirect_url($cleaner_id, $user);
    }

    if (!$cleaner_id || !gb_connect_user_can_manage_cleaner($cleaner_id, $user)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'You do not have permission to manage Stripe Connect for this cleaner.');
    }

    if (
        empty($_GET['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gb_connect_onboarding_' . $cleaner_id)
    ) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Security check failed for Stripe Connect onboarding.');
    }

    $account = gb_create_connect_account_for_cleaner($cleaner_id);
    if (is_wp_error($account)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', $account->get_error_message());
    }

    $account_id = sanitize_text_field((string) ($account['id'] ?? get_post_meta($cleaner_id, '_gb_stripe_account_id', true)));
    if ($account_id === '') {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Unable to determine connected account ID.');
    }

    $refresh_url = wp_nonce_url(
        add_query_arg(
            [
                'action'      => 'gb_connect_onboarding',
                'cleaner_id'  => $cleaner_id,
                'redirect_to' => $redirect_to,
            ],
            admin_url('admin-post.php')
        ),
        'gb_connect_onboarding_' . $cleaner_id
    );
    $return_url = add_query_arg('connect', 'returned', $redirect_to);

    $link = gb_stripe_api_request_v2('core/account_links', [
        'account' => $account_id,
        'use_case' => [
            'type' => 'account_onboarding',
            'account_onboarding' => [
                'configurations' => ['recipient'],
                'refresh_url' => $refresh_url,
                'return_url' => $return_url,
            ],
        ],
    ], 'POST');

    if (is_wp_error($link)) {
        // Backward-compatible fallback to v1 account links.
        $link = gb_stripe_api_request('account_links', [
            'account'     => $account_id,
            'refresh_url' => $refresh_url,
            'return_url'  => $return_url,
            'type'        => 'account_onboarding',
        ], 'POST');
    }

    if (!is_wp_error($link)) {
        $link = gb_stripe_unwrap_data_object($link);
    }

    if (is_wp_error($link) || empty($link['url'])) {
        $msg = is_wp_error($link) ? $link->get_error_message() : 'Stripe did not return an onboarding link.';
        gb_connect_redirect_with_notice($redirect_to, 'error', $msg);
    }

    wp_safe_redirect(esc_url_raw($link['url']));
    exit;
}
add_action('admin_post_gb_connect_onboarding', 'gb_handle_connect_onboarding');

function gb_handle_connect_login_link() {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    $cleaner_id = isset($_GET['cleaner_id']) ? absint($_GET['cleaner_id']) : 0;
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
    if ($redirect_to === '') {
        $redirect_to = gb_connect_default_redirect_url($cleaner_id, $user);
    }

    if (!$cleaner_id || !gb_connect_user_can_manage_cleaner($cleaner_id, $user)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'You do not have permission to access this Stripe Connect account.');
    }

    if (
        empty($_GET['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gb_connect_login_' . $cleaner_id)
    ) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Security check failed for Stripe Connect dashboard access.');
    }

    $account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($account_id === '') {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Cleaner is not connected to Stripe yet.');
    }

    $login_link = gb_stripe_api_request('accounts/' . rawurlencode($account_id) . '/login_links', [], 'POST');
    if (is_wp_error($login_link) || empty($login_link['url'])) {
        $msg = is_wp_error($login_link) ? $login_link->get_error_message() : 'Stripe did not return an Express dashboard link.';
        gb_connect_redirect_with_notice($redirect_to, 'error', $msg);
    }

    wp_safe_redirect(esc_url_raw($login_link['url']));
    exit;
}
add_action('admin_post_gb_connect_login_link', 'gb_handle_connect_login_link');

function gb_handle_connect_sync() {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    $cleaner_id = isset($_GET['cleaner_id']) ? absint($_GET['cleaner_id']) : 0;
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
    if ($redirect_to === '') {
        $redirect_to = gb_connect_default_redirect_url($cleaner_id, $user);
    }

    if (!$cleaner_id || !gb_connect_user_can_manage_cleaner($cleaner_id, $user)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'You do not have permission to sync this Stripe Connect account.');
    }

    if (
        empty($_GET['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gb_connect_sync_' . $cleaner_id)
    ) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Security check failed for Stripe Connect sync.');
    }

    $synced = gb_sync_cleaner_connect_account($cleaner_id);
    if (is_wp_error($synced)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', $synced->get_error_message());
    }

    gb_connect_redirect_with_notice($redirect_to, 'synced', 'Stripe Connect status refreshed.');
}
add_action('admin_post_gb_connect_sync', 'gb_handle_connect_sync');

/**
 * Retrieve Global Stripe Configuration
 * Call this anywhere in the theme to get current keys.
 */
function gb_stripe_value_is_placeholder($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return true;
    }

    $markers = ['***', 'your_', 'placeholder', 'change_me', 'replace_me', 'example'];
    $lower = strtolower($value);
    foreach ($markers as $marker) {
        if (strpos($lower, $marker) !== false) {
            return true;
        }
    }

    return false;
}

function gb_get_stripe_config_issues($config, $required_fields = ['secret_key']) {
    $required_fields = is_array($required_fields) ? $required_fields : ['secret_key'];
    $issues = [];

    foreach ($required_fields as $field_key) {
        $value = isset($config[$field_key]) ? (string) $config[$field_key] : '';
        if (gb_stripe_value_is_placeholder($value)) {
            $issues[] = $field_key;
        }
    }

    return $issues;
}

function gb_require_stripe_config($required_fields = ['secret_key']) {
    $config = gb_get_stripe_config();
    $issues = gb_get_stripe_config_issues($config, $required_fields);

    if (!empty($issues)) {
        $labels = [
            'publishable_key' => 'publishable key',
            'secret_key' => 'secret key',
            'webhook_secret' => 'webhook secret',
            'connect_webhook_secret' => 'Connect webhook secret',
        ];
        $missing = [];
        foreach ($issues as $issue) {
            $missing[] = isset($labels[$issue]) ? $labels[$issue] : $issue;
        }

        return new WP_Error(
            'gb_stripe_missing_config',
            'Stripe ' . implode(', ', $missing) . ' is missing. Add it in Manager Dashboard > Settings > Stripe Connect Credentials.'
        );
    }

    return $config;
}

function gb_get_stripe_config() {
    $settings_id = get_option('page_on_front'); // Data is stored on Front Page ID

    $mode = get_post_meta($settings_id, '_gb_stripe_mode', true) ?: 'test';
    if (!in_array($mode, ['test', 'live'], true)) {
        $mode = 'test';
    }

    $publishable_key = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_publishable_key', true)
        : get_post_meta($settings_id, '_gb_stripe_test_publishable_key', true);

    $secret_key = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_secret_key', true)
        : get_post_meta($settings_id, '_gb_stripe_test_secret_key', true);

    $webhook_secret = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_webhook_secret', true)
        : get_post_meta($settings_id, '_gb_stripe_test_webhook_secret', true);
    $connect_webhook_secret = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_connect_webhook_secret', true)
        : get_post_meta($settings_id, '_gb_stripe_test_connect_webhook_secret', true);

    // Backward compatibility with legacy single-key fields.
    if (empty($publishable_key)) {
        $publishable_key = get_post_meta($settings_id, '_gb_stripe_publishable_key', true);
    }
    if (empty($secret_key)) {
        $secret_key = get_post_meta($settings_id, '_gb_stripe_secret_key', true);
    }
    if (empty($webhook_secret)) {
        $webhook_secret = get_post_meta($settings_id, '_gb_stripe_webhook_secret', true);
    }
    if (empty($connect_webhook_secret)) {
        $connect_webhook_secret = get_post_meta($settings_id, '_gb_stripe_connect_webhook_secret', true);
    }

    return [
        'mode'            => $mode,
        'publishable_key' => trim((string) $publishable_key),
        'secret_key'      => trim((string) $secret_key),
        'webhook_secret'  => trim((string) $webhook_secret),
        'connect_webhook_secret' => trim((string) $connect_webhook_secret),
        'platform_fee'    => get_post_meta($settings_id, '_gb_platform_fee', true) !== '' ? get_post_meta($settings_id, '_gb_platform_fee', true) : '0',
        'webhook_url'     => gb_get_stripe_webhook_url(),
    ];
}

function gb_stripe_api_request($endpoint, $params = [], $method = 'POST') {
    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        return $config;
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $method = strtoupper($method);
    $request_args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $config['secret_key'],
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
    ];

    if (!empty($params) && $method === 'GET') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params, '', '&');
    } elseif (!empty($params)) {
        $request_args['body'] = http_build_query($params, '', '&');
    }

    $response = wp_remote_request($url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status >= 400) {
        $error_message = 'Stripe request failed.';
        if (is_array($data) && isset($data['error']['message'])) {
            $error_message = $data['error']['message'];
        }
        return new WP_Error('gb_stripe_api_error', $error_message, ['status' => $status, 'payload' => $data]);
    }

    if (!is_array($data)) {
        return new WP_Error('gb_stripe_invalid_response', 'Stripe returned an invalid response.');
    }

    return $data;
}

/**
 * Lightweight Stripe v2 request helper.
 * This keeps the integration ready for Connect v2 account and thin-event workflows.
 */
function gb_stripe_api_request_v2($endpoint, $params = [], $method = 'POST') {
    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        return $config;
    }

    $url = 'https://api.stripe.com/v2/' . ltrim($endpoint, '/');
    $method = strtoupper($method);
    $request_args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $config['secret_key'],
            'Content-Type'  => 'application/json',
        ],
    ];

    if (!empty($params) && $method === 'GET') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params, '', '&');
    } elseif (!empty($params)) {
        $request_args['body'] = wp_json_encode($params);
    }

    $response = wp_remote_request($url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status >= 400) {
        $error_message = 'Stripe v2 request failed.';
        if (is_array($data) && isset($data['error']['message'])) {
            $error_message = $data['error']['message'];
        }
        return new WP_Error('gb_stripe_v2_api_error', $error_message, ['status' => $status, 'payload' => $data]);
    }

    if (!is_array($data)) {
        return new WP_Error('gb_stripe_v2_invalid_response', 'Stripe v2 returned an invalid response.');
    }

    return $data;
}

function gb_stripe_verify_signature($payload, $signature_header, $secret, $tolerance = 300) {
    if (empty($payload) || empty($signature_header) || empty($secret)) {
        return false;
    }

    $timestamp = null;
    $signatures = [];
    $parts = explode(',', $signature_header);

    foreach ($parts as $part) {
        $segment = explode('=', trim($part), 2);
        if (count($segment) !== 2) {
            continue;
        }
        if ($segment[0] === 't') {
            $timestamp = (int) $segment[1];
        }
        if ($segment[0] === 'v1') {
            $signatures[] = $segment[1];
        }
    }

    if (!$timestamp || empty($signatures)) {
        return false;
    }

    if (abs(time() - $timestamp) > (int) $tolerance) {
        return false;
    }

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function gb_sync_booking_from_checkout_session($booking_id, $session_id) {
    $booking_id = absint($booking_id);
    $session_id = sanitize_text_field($session_id);

    if (!$booking_id || get_post_type($booking_id) !== 'gb_booking') {
        return new WP_Error('gb_invalid_booking', 'Invalid booking for Stripe sync.');
    }
    if (empty($session_id)) {
        return new WP_Error('gb_missing_session', 'Missing Stripe checkout session ID.');
    }

    $session = gb_stripe_api_request('checkout/sessions/' . rawurlencode($session_id), [], 'GET');
    if (is_wp_error($session)) {
        return $session;
    }

    $metadata_booking_id = isset($session['metadata']['booking_id']) ? absint($session['metadata']['booking_id']) : 0;
    if ($metadata_booking_id !== $booking_id) {
        return new WP_Error('gb_mismatch_booking', 'Stripe checkout session does not match this booking.');
    }

    $payment_status = isset($session['payment_status']) ? sanitize_text_field($session['payment_status']) : '';
    $payment_intent_id = isset($session['payment_intent']) ? sanitize_text_field($session['payment_intent']) : '';
    update_post_meta($booking_id, '_gb_payment_provider', 'stripe');
    update_post_meta($booking_id, '_gb_stripe_checkout_session_id', sanitize_text_field($session['id']));
    update_post_meta($booking_id, '_gb_payment_status', $payment_status ?: 'pending');
    update_post_meta($booking_id, '_gb_stripe_payment_intent', $payment_intent_id);

    if ($payment_intent_id !== '') {
        $payment_intent = gb_stripe_api_request('payment_intents/' . rawurlencode($payment_intent_id), [], 'GET');
        if (!is_wp_error($payment_intent) && is_array($payment_intent)) {
            $destination_account = isset($payment_intent['transfer_data']['destination'])
                ? sanitize_text_field($payment_intent['transfer_data']['destination'])
                : '';
            $application_fee_amount = isset($payment_intent['application_fee_amount'])
                ? (int) $payment_intent['application_fee_amount']
                : 0;
            $latest_charge = isset($payment_intent['latest_charge']) ? sanitize_text_field($payment_intent['latest_charge']) : '';

            if ($destination_account !== '') {
                update_post_meta($booking_id, '_gb_stripe_destination_account', $destination_account);
            }
            if ($application_fee_amount > 0) {
                update_post_meta($booking_id, '_gb_platform_fee_gbp', number_format($application_fee_amount / 100, 2, '.', ''));
            }
            if ($latest_charge !== '') {
                update_post_meta($booking_id, '_gb_stripe_charge_id', $latest_charge);
            }
        }
    }

    $booking_total = (float) get_post_meta($booking_id, '_gb_booking_total_gbp', true);
    $platform_fee = (float) get_post_meta($booking_id, '_gb_platform_fee_gbp', true);
    if ($booking_total > 0) {
        $cleaner_net = max(0, $booking_total - $platform_fee);
        update_post_meta($booking_id, '_gb_cleaner_payout_estimate_gbp', number_format($cleaner_net, 2, '.', ''));
    }

    if ($payment_status === 'paid') {
        $was_already_confirmed = get_post_meta($booking_id, '_gb_booking_status', true) === 'confirmed';
        wp_update_post(['ID' => $booking_id, 'post_status' => 'publish']);
        update_post_meta($booking_id, '_gb_booking_status', 'confirmed');
        if (!$was_already_confirmed) {
            gb_send_booking_confirmation_emails($booking_id);
        }
    } else {
        if (get_post_status($booking_id) !== 'pending') {
            wp_update_post(['ID' => $booking_id, 'post_status' => 'pending']);
        }
        if (!get_post_meta($booking_id, '_gb_booking_status', true)) {
            update_post_meta($booking_id, '_gb_booking_status', 'pending_payment');
        }
    }

    return $session;
}

function gb_send_booking_confirmation_emails($booking_id) {
    $booking_id = absint($booking_id);
    if (!$booking_id || get_post_type($booking_id) !== 'gb_booking') {
        return;
    }

    $customer_name    = (string) get_post_meta($booking_id, '_gb_customer_name', true);
    $customer_email   = sanitize_email((string) get_post_meta($booking_id, '_gb_customer_email', true));
    $service          = (string) get_post_meta($booking_id, '_gb_booking_service', true);
    $date             = (string) get_post_meta($booking_id, '_gb_booking_date', true);
    $time             = (string) get_post_meta($booking_id, '_gb_booking_time', true);
    $duration         = (int) get_post_meta($booking_id, '_gb_booking_duration', true);
    $address          = (string) get_post_meta($booking_id, '_gb_customer_address', true);
    $total_gbp        = (float) get_post_meta($booking_id, '_gb_booking_total_gbp', true);
    $cleaner_post_id  = (int) get_post_meta($booking_id, '_gb_booking_cleaner_id', true);
    $cleaner_name     = $cleaner_post_id ? get_the_title($cleaner_post_id) : 'TBC';
    $cleaner_user_id  = $cleaner_post_id ? (int) get_post_field('post_author', $cleaner_post_id) : 0;
    $access_token     = (string) get_post_meta($booking_id, '_gb_booking_access_token_raw', true);

    $site_name  = get_bloginfo('name');
    $date_fmt   = $date ? date_i18n('l j F Y', strtotime($date)) : $date;
    $time_fmt   = $time ? date_i18n('g:i A', strtotime($time)) : $time;
    $total_fmt  = $total_gbp > 0 ? '£' . number_format($total_gbp, 2) : '';
    $booking_url = add_query_arg(
        ['bid' => $booking_id, 't' => gb_generate_booking_access_token($booking_id)],
        home_url('/booking-success')
    );

    // — Customer email —
    if (is_email($customer_email)) {
        $subject = sprintf('[%s] Your booking is confirmed — %s on %s', $site_name, $service, $date_fmt);
        $body    = "Hi {$customer_name},\n\nYour booking is confirmed. Here are the details:\n\n"
                 . "Service:      {$service}\n"
                 . "Professional: {$cleaner_name}\n"
                 . "Date:         {$date_fmt}\n"
                 . "Time:         {$time_fmt}\n"
                 . "Duration:     {$duration} hour" . ($duration === 1 ? '' : 's') . "\n"
                 . "Address:      {$address}\n"
                 . ($total_fmt ? "Total paid:   {$total_fmt}\n" : '')
                 . "\nView your booking: {$booking_url}\n\n"
                 . "Thank you for booking with {$site_name}.";
        $customer_duration = $duration > 0
            ? $duration . ' hour' . ($duration === 1 ? '' : 's')
            : '';
        gb_send_brevo_template_email(
            $customer_email,
            $subject,
            $body,
            [],
            gb_get_brevo_template_id( 'booking_customer' ),
            [
                'first_name'         => gb_brevo_first_name( $customer_name ),
                'booking_url'        => $booking_url,
                'service'            => $service,
                'professional_name'  => $cleaner_name,
                'date'               => $date_fmt,
                'time'               => $time_fmt,
                'duration'           => $customer_duration,
                'address'            => $address,
                'total'              => $total_fmt,
            ]
        );
    }

    // — Cleaner email —
    if ($cleaner_user_id > 0) {
        $cleaner_user = get_userdata($cleaner_user_id);
        if ($cleaner_user && is_email($cleaner_user->user_email)) {
            $subject = sprintf('[%s] New booking confirmed — %s on %s', $site_name, $service, $date_fmt);
            $body    = "Hi {$cleaner_name},\n\nYou have a new confirmed booking:\n\n"
                     . "Service:  {$service}\n"
                     . "Customer: {$customer_name}\n"
                     . "Date:     {$date_fmt}\n"
                     . "Time:     {$time_fmt}\n"
                     . "Duration: {$duration} hour" . ($duration === 1 ? '' : 's') . "\n"
                     . "Address:  {$address}\n\n"
                     . "Log in to your dashboard to view full details: " . home_url('/cleaner-dashboard') . "\n\n"
                     . $site_name;
            $cleaner_duration = $duration > 0
                ? $duration . ' hour' . ($duration === 1 ? '' : 's')
                : '';
            gb_send_brevo_template_email(
                $cleaner_user->user_email,
                $subject,
                $body,
                [],
                gb_get_brevo_template_id( 'booking_cleaner' ),
                [
                    'first_name'     => gb_brevo_first_name( $cleaner_name ),
                    'booking_url'    => home_url( '/cleaner-dashboard' ),
                    'service'        => $service,
                    'customer_name'  => $customer_name,
                    'date'           => $date_fmt,
                    'time'           => $time_fmt,
                    'duration'       => $cleaner_duration,
                    'address'        => $address,
                ]
            );
        }
    }
}

function gb_get_cleaner_connect_status_live($cleaner_id) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return new WP_Error('gb_invalid_cleaner', 'Invalid cleaner profile.');
    }

    $account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Cleaner has no Stripe Connect account yet.');
    }

    $account = gb_get_connect_account_from_stripe($account_id);
    if (is_wp_error($account)) {
        return $account;
    }

    gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account);
    return gb_stripe_unwrap_data_object($account);
}

function gb_get_service_storefront_currency($service_id = 0) {
    $service_id = absint($service_id);
    $currency = $service_id ? sanitize_key((string) get_post_meta($service_id, '_gb_service_currency', true)) : '';
    if ($currency === '') {
        $currency = 'gbp';
    }
    return in_array($currency, ['gbp', 'usd', 'eur'], true) ? $currency : 'gbp';
}

function gb_get_service_storefront_amount_minor($service_id) {
    $service_id = absint($service_id);
    $price = (float) get_post_meta($service_id, 'service_price', true);
    // Keep Stripe prices valid even when surcharge is zero on booking logic.
    return max(50, (int) round(max(0, $price) * 100));
}

function gb_get_service_destination_account_id($service_id) {
    $service_id = absint($service_id);
    if (!$service_id || get_post_type($service_id) !== 'gb_service') {
        return '';
    }

    $cleaner_id = absint(get_post_meta($service_id, '_gb_service_destination_cleaner_id', true));
    if (!$cleaner_id) {
        return '';
    }

    return sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
}

function gb_sync_service_to_stripe_product($service_id, $force_price_refresh = false) {
    $service_id = absint($service_id);
    if (!$service_id || get_post_type($service_id) !== 'gb_service') {
        return new WP_Error('gb_invalid_service', 'Invalid service for Stripe product sync.');
    }

    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        update_post_meta($service_id, '_gb_stripe_product_last_error', $config->get_error_message());
        return $config;
    }

    $service_post = get_post($service_id);
    if (!$service_post) {
        return new WP_Error('gb_missing_service_post', 'Service post not found.');
    }

    $name = sanitize_text_field((string) $service_post->post_title);
    $description = sanitize_textarea_field((string) $service_post->post_content);
    $active = get_post_status($service_id) === 'publish';
    $currency = gb_get_service_storefront_currency($service_id);
    $amount_minor = gb_get_service_storefront_amount_minor($service_id);

    $existing_product_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_product_id', true));
    $existing_price_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_price_id', true));
    $last_amount_minor = (int) get_post_meta($service_id, '_gb_stripe_product_amount_minor', true);
    $last_currency = sanitize_key((string) get_post_meta($service_id, '_gb_stripe_product_currency', true));

    $product_payload = [
        'name' => $name !== '' ? $name : ('Service ' . $service_id),
        'description' => $description,
        'active' => $active ? 'true' : 'false',
        'metadata[gb_service_id]' => (string) $service_id,
        'metadata[gb_service_slug]' => sanitize_title((string) $service_post->post_name),
        'metadata[managed_from]' => 'manager_services_tab',
    ];

    if ($existing_product_id === '') {
        $create_payload = $product_payload;
        $create_payload['default_price_data[unit_amount]'] = $amount_minor;
        $create_payload['default_price_data[currency]'] = $currency;
        $product = gb_stripe_api_request('products', $create_payload, 'POST');
        if (is_wp_error($product) || empty($product['id'])) {
            $msg = is_wp_error($product) ? $product->get_error_message() : 'Stripe did not return a product ID.';
            update_post_meta($service_id, '_gb_stripe_product_last_error', $msg);
            return new WP_Error('gb_service_product_create_failed', $msg);
        }

        $existing_product_id = sanitize_text_field($product['id']);
        update_post_meta($service_id, '_gb_stripe_product_id', $existing_product_id);

        if (!empty($product['default_price']) && is_string($product['default_price'])) {
            $existing_price_id = sanitize_text_field($product['default_price']);
            update_post_meta($service_id, '_gb_stripe_price_id', $existing_price_id);
        }
    } else {
        $update_product = gb_stripe_api_request('products/' . rawurlencode($existing_product_id), $product_payload, 'POST');
        if (is_wp_error($update_product)) {
            update_post_meta($service_id, '_gb_stripe_product_last_error', $update_product->get_error_message());
            return $update_product;
        }
    }

    $needs_new_price = $force_price_refresh || $existing_price_id === '' || $last_amount_minor !== $amount_minor || $last_currency !== $currency;
    if ($needs_new_price) {
        $new_price = gb_stripe_api_request('prices', [
            'unit_amount' => $amount_minor,
            'currency' => $currency,
            'product' => $existing_product_id,
        ], 'POST');
        if (is_wp_error($new_price) || empty($new_price['id'])) {
            $msg = is_wp_error($new_price) ? $new_price->get_error_message() : 'Stripe did not return a price ID.';
            update_post_meta($service_id, '_gb_stripe_product_last_error', $msg);
            return new WP_Error('gb_service_price_create_failed', $msg);
        }

        $existing_price_id = sanitize_text_field($new_price['id']);
        update_post_meta($service_id, '_gb_stripe_price_id', $existing_price_id);

        $set_default = gb_stripe_api_request('products/' . rawurlencode($existing_product_id), [
            'default_price' => $existing_price_id,
        ], 'POST');
        if (is_wp_error($set_default)) {
            update_post_meta($service_id, '_gb_stripe_product_last_error', $set_default->get_error_message());
            return $set_default;
        }
    }

    update_post_meta($service_id, '_gb_stripe_product_amount_minor', $amount_minor);
    update_post_meta($service_id, '_gb_stripe_product_currency', $currency);
    update_post_meta($service_id, '_gb_stripe_product_last_sync', current_time('mysql'));
    delete_post_meta($service_id, '_gb_stripe_product_last_error');

    $destination_account_id = gb_get_service_destination_account_id($service_id);
    $destination_cleaner_id = absint(get_post_meta($service_id, '_gb_service_destination_cleaner_id', true));
    if ($existing_product_id !== '') {
        gb_set_stripe_storefront_mapping($existing_product_id, [
            'connected_account_id' => $destination_account_id,
            'cleaner_id' => $destination_cleaner_id,
            'created_at' => current_time('mysql'),
        ]);
    }

    return [
        'product_id' => $existing_product_id,
        'price_id' => $existing_price_id,
        'amount_minor' => $amount_minor,
        'currency' => $currency,
        'active' => $active,
    ];
}

function gb_archive_service_stripe_product($service_id) {
    $service_id = absint($service_id);
    if (!$service_id || get_post_type($service_id) !== 'gb_service') {
        return new WP_Error('gb_invalid_service', 'Invalid service for Stripe archive.');
    }

    $product_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_product_id', true));
    if ($product_id === '') {
        return true;
    }

    $result = gb_stripe_api_request('products/' . rawurlencode($product_id), ['active' => 'false'], 'POST');
    if (is_wp_error($result)) {
        update_post_meta($service_id, '_gb_stripe_product_last_error', $result->get_error_message());
        return $result;
    }

    update_post_meta($service_id, '_gb_stripe_product_last_sync', current_time('mysql'));
    return true;
}

function gb_get_stripe_storefront_mappings() {
    $mappings = get_option('gb_stripe_product_mappings', []);
    return is_array($mappings) ? $mappings : [];
}

function gb_set_stripe_storefront_mapping($product_id, $mapping) {
    $product_id = sanitize_text_field((string) $product_id);
    if ($product_id === '') {
        return;
    }

    $mappings = gb_get_stripe_storefront_mappings();
    $mappings[$product_id] = [
        'connected_account_id' => sanitize_text_field((string) ($mapping['connected_account_id'] ?? '')),
        'cleaner_id' => absint($mapping['cleaner_id'] ?? 0),
        'created_at' => sanitize_text_field((string) ($mapping['created_at'] ?? current_time('mysql'))),
    ];
    update_option('gb_stripe_product_mappings', $mappings, false);
}

function gb_get_stripe_storefront_mapping($product_id) {
    $product_id = sanitize_text_field((string) $product_id);
    if ($product_id === '') {
        return [];
    }

    $mappings = gb_get_stripe_storefront_mappings();
    return isset($mappings[$product_id]) && is_array($mappings[$product_id]) ? $mappings[$product_id] : [];
}

function gb_get_manager_settings_url($args = []) {
    $base = home_url('/manager-dashboard');
    $defaults = ['tab' => 'settings'];
    return add_query_arg(array_merge($defaults, is_array($args) ? $args : []), $base);
}

function gb_redirect_manager_settings_with_stripe_notice($status, $message) {
    $status = $status === 'ok' ? 'ok' : 'error';
    $message = sanitize_text_field((string) $message);
    wp_safe_redirect(gb_get_manager_settings_url(['stripe_notice' => $status, 'stripe_msg' => $message]));
    exit;
}

function gb_handle_stripe_sample_create_product() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    if (!current_user_can('administrator') && !in_array('gb_manager', (array) $user->roles, true)) {
        wp_die('You do not have permission to create Stripe products.');
    }

    if (
        empty($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gb_stripe_sample_create_product')
    ) {
        wp_die('Security check failed.');
    }

    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        gb_redirect_manager_settings_with_stripe_notice('error', $config->get_error_message());
    }

    $name = isset($_POST['gb_product_name']) ? sanitize_text_field(wp_unslash($_POST['gb_product_name'])) : '';
    $description = isset($_POST['gb_product_description']) ? sanitize_textarea_field(wp_unslash($_POST['gb_product_description'])) : '';
    $unit_amount = isset($_POST['gb_product_amount']) ? absint($_POST['gb_product_amount']) : 0;
    $currency = isset($_POST['gb_product_currency']) ? strtolower(sanitize_key(wp_unslash($_POST['gb_product_currency']))) : 'gbp';
    $cleaner_id = isset($_POST['gb_product_cleaner_id']) ? absint($_POST['gb_product_cleaner_id']) : 0;

    if ($name === '' || $unit_amount < 50) {
        gb_redirect_manager_settings_with_stripe_notice('error', 'Product name is required and amount must be at least 50 (minor units).');
    }

    if (!in_array($currency, ['gbp', 'usd', 'eur'], true)) {
        $currency = 'gbp';
    }

    $connected_account_id = '';
    if ($cleaner_id) {
        $connected_account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
        if ($connected_account_id === '') {
            gb_redirect_manager_settings_with_stripe_notice('error', 'Selected cleaner has no connected Stripe account yet.');
        }
    }

    // Create product on the platform account (never on connected accounts).
    $product = gb_stripe_api_request('products', [
        'name' => $name,
        'description' => $description,
        'default_price_data[unit_amount]' => $unit_amount,
        'default_price_data[currency]' => $currency,
        'metadata[sample_storefront]' => '1',
    ], 'POST');
    if (is_wp_error($product) || empty($product['id'])) {
        $msg = is_wp_error($product) ? $product->get_error_message() : 'Stripe did not return a product ID.';
        gb_redirect_manager_settings_with_stripe_notice('error', $msg);
    }

    gb_set_stripe_storefront_mapping($product['id'], [
        'connected_account_id' => $connected_account_id,
        'cleaner_id' => $cleaner_id,
        'created_at' => current_time('mysql'),
    ]);

    gb_redirect_manager_settings_with_stripe_notice('ok', 'Stripe product created and mapped successfully.');
}
add_action('admin_post_gb_stripe_sample_create_product', 'gb_handle_stripe_sample_create_product');

function gb_get_stripe_platform_products($limit = 100) {
    $limit = max(1, min(100, absint($limit)));
    return gb_stripe_api_request('products', [
        'active' => 'true',
        'limit' => $limit,
        'expand' => ['data.default_price'],
    ], 'GET');
}

function gb_handle_stripe_storefront_checkout() {
    $referer = wp_get_referer();
    if (!$referer) {
        $referer = home_url('/');
    }

    if (
        empty($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gb_stripe_storefront_checkout')
    ) {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'invalid_request', $referer));
        exit;
    }

    $product_id = isset($_POST['product_id']) ? sanitize_text_field(wp_unslash($_POST['product_id'])) : '';
    $quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;
    if ($product_id === '') {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'missing_product', $referer));
        exit;
    }

    $config = gb_require_stripe_config(['secret_key', 'publishable_key']);
    if (is_wp_error($config)) {
        wp_safe_redirect(add_query_arg('stripe_storefront', rawurlencode($config->get_error_message()), $referer));
        exit;
    }

    $mapping = gb_get_stripe_storefront_mapping($product_id);
    $destination_account = sanitize_text_field((string) ($mapping['connected_account_id'] ?? ''));
    if ($destination_account === '') {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'missing_destination', $referer));
        exit;
    }

    $product = gb_stripe_api_request(
        'products/' . rawurlencode($product_id),
        ['expand' => ['default_price']],
        'GET'
    );
    if (is_wp_error($product)) {
        wp_safe_redirect(add_query_arg('stripe_storefront', rawurlencode($product->get_error_message()), $referer));
        exit;
    }

    $default_price = isset($product['default_price']) && is_array($product['default_price']) ? $product['default_price'] : [];
    if (empty($default_price) && !empty($product['default_price'])) {
        $default_price = gb_stripe_api_request('prices/' . rawurlencode($product['default_price']), [], 'GET');
    }
    if (is_wp_error($default_price) || empty($default_price['id'])) {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'missing_price', $referer));
        exit;
    }

    $unit_amount = isset($default_price['unit_amount']) ? (int) $default_price['unit_amount'] : 0;
    $gross_amount = max(0, $unit_amount * $quantity);
    $fee_percent = (float) ($config['platform_fee'] ?? 0);
    $application_fee_amount = (int) round($gross_amount * max(0, $fee_percent) / 100);

    // Destination charge with platform fee collection.
    $session = gb_stripe_api_request('checkout/sessions', [
        'mode' => 'payment',
        'line_items[0][price]' => $default_price['id'],
        'line_items[0][quantity]' => $quantity,
        'payment_intent_data[application_fee_amount]' => $application_fee_amount,
        'payment_intent_data[transfer_data][destination]' => $destination_account,
        'success_url' => add_query_arg('stripe_storefront', 'success', $referer),
        'cancel_url' => add_query_arg('stripe_storefront', 'cancelled', $referer),
    ], 'POST');

    if (is_wp_error($session) || empty($session['url'])) {
        $msg = is_wp_error($session) ? $session->get_error_message() : 'Checkout session URL missing.';
        wp_safe_redirect(add_query_arg('stripe_storefront', rawurlencode($msg), $referer));
        exit;
    }

    wp_safe_redirect(esc_url_raw($session['url']));
    exit;
}
add_action('admin_post_gb_stripe_storefront_checkout', 'gb_handle_stripe_storefront_checkout');
add_action('admin_post_nopriv_gb_stripe_storefront_checkout', 'gb_handle_stripe_storefront_checkout');

function gb_render_stripe_storefront_shortcode($atts = []) {
    $config = gb_require_stripe_config(['secret_key', 'publishable_key']);
    if (is_wp_error($config)) {
        return '<div style="padding:14px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; color:#991b1b;">' . esc_html($config->get_error_message()) . '</div>';
    }

    $products_response = gb_get_stripe_platform_products(100);
    if (is_wp_error($products_response)) {
        return '<div style="padding:14px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; color:#991b1b;">' . esc_html($products_response->get_error_message()) . '</div>';
    }

    $products = isset($products_response['data']) && is_array($products_response['data']) ? $products_response['data'] : [];
    $mappings = gb_get_stripe_storefront_mappings();

    $cleaners = get_posts([
        'post_type' => 'gb_cleaner',
        'post_status' => ['publish', 'pending', 'draft'],
        'numberposts' => -1,
    ]);
    $cleaner_accounts = [];
    foreach ($cleaners as $cleaner) {
        $account_id = sanitize_text_field((string) get_post_meta($cleaner->ID, '_gb_stripe_account_id', true));
        if ($account_id !== '') {
            $cleaner_accounts[] = [
                'name' => $cleaner->post_title,
                'account_id' => $account_id,
            ];
        }
    }

    ob_start();
    ?>
    <div style="border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:20px;">
        <h3 style="margin:0 0 6px; color:#0f172a;">Sample Storefront</h3>
        <p style="margin:0 0 18px; color:#64748b; font-size:13px;">All platform products are shown below. Each product can be mapped to a connected cleaner account for destination charges.</p>

        <?php
        $storefront_state = isset($_GET['stripe_storefront']) ? sanitize_text_field(wp_unslash($_GET['stripe_storefront'])) : '';
        if ($storefront_state === 'success'):
        ?>
            <div style="margin:0 0 14px; padding:10px 12px; background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; color:#166534; font-size:13px;">Payment completed successfully.</div>
        <?php elseif ($storefront_state !== '' && $storefront_state !== 'cancelled'): ?>
            <div style="margin:0 0 14px; padding:10px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; font-size:13px;">
                Storefront error: <?php echo esc_html(rawurldecode($storefront_state)); ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; gap:12px;">
            <?php if (empty($products)): ?>
                <div style="padding:12px; border:1px dashed #cbd5e1; border-radius:8px; color:#64748b;">No Stripe products found yet.</div>
            <?php endif; ?>
            <?php foreach ($products as $product):
                $product_id = sanitize_text_field((string) ($product['id'] ?? ''));
                if ($product_id === '') {
                    continue;
                }
                $mapping = isset($mappings[$product_id]) && is_array($mappings[$product_id]) ? $mappings[$product_id] : [];
                $destination_account = sanitize_text_field((string) ($mapping['connected_account_id'] ?? ''));
                $default_price = isset($product['default_price']) && is_array($product['default_price']) ? $product['default_price'] : [];
                $amount = isset($default_price['unit_amount']) ? (int) $default_price['unit_amount'] : 0;
                $currency = strtoupper((string) ($default_price['currency'] ?? 'GBP'));
            ?>
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:700; color:#0f172a;"><?php echo esc_html((string) ($product['name'] ?? 'Unnamed product')); ?></div>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                <?php echo esc_html($currency); ?> <?php echo esc_html(number_format(($amount / 100), 2)); ?>
                                <?php if ($destination_account !== ''): ?>
                                    · Destination: <?php echo esc_html($destination_account); ?>
                                <?php else: ?>
                                    · No connected account mapping
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="action" value="gb_stripe_storefront_checkout">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                            <?php wp_nonce_field('gb_stripe_storefront_checkout'); ?>
                            <input type="number" name="quantity" min="1" value="1" style="width:70px; border:1px solid #e2e8f0; border-radius:6px; padding:8px;">
                            <button type="submit" <?php disabled($destination_account === ''); ?> style="border:1px solid #2e7d32; background:#2e7d32; color:#fff; border-radius:6px; padding:9px 14px; font-weight:700; cursor:pointer;">Buy</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:18px; border-top:1px solid #f1f5f9; padding-top:14px;">
            <h4 style="margin:0 0 8px; font-size:13px; color:#0f172a;">Connected Accounts</h4>
            <?php if (empty($cleaner_accounts)): ?>
                <div style="font-size:12px; color:#64748b;">No connected cleaner accounts found.</div>
            <?php else: ?>
                <ul style="margin:0; padding-left:18px; color:#334155; font-size:12px;">
                    <?php foreach ($cleaner_accounts as $item): ?>
                        <li><?php echo esc_html($item['name']); ?> · <?php echo esc_html($item['account_id']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}
add_shortcode('gb_stripe_storefront', 'gb_render_stripe_storefront_shortcode');

function gb_extract_connected_account_id_from_event($event) {
    if (!is_array($event)) {
        return '';
    }

    $candidates = [];
    $paths = [
        ['account'],
        ['context', 'account'],
        ['data', 'object', 'id'],
        ['data', 'related_object', 'id'],
        ['related_object', 'id'],
        ['related_object'],
    ];

    foreach ($paths as $path) {
        $value = $event;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = null;
                break;
            }
            $value = $value[$segment];
        }
        if (is_string($value) && $value !== '') {
            $candidates[] = $value;
        }
    }

    foreach ($candidates as $candidate) {
        $candidate = sanitize_text_field($candidate);
        if (strpos($candidate, 'acct_') === 0) {
            return $candidate;
        }
    }

    return '';
}

function gb_handle_stripe_webhook() {
    $config = gb_get_stripe_config();
    $payload = file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

    $secrets = array_filter([
        isset($config['webhook_secret']) ? $config['webhook_secret'] : '',
        isset($config['connect_webhook_secret']) ? $config['connect_webhook_secret'] : '',
    ], function ($secret) {
        return !gb_stripe_value_is_placeholder($secret);
    });
    if (empty($secrets)) {
        status_header(400);
        echo 'Stripe webhook secret not configured.';
        exit;
    }

    $valid_signature = false;
    foreach ($secrets as $secret) {
        if (gb_stripe_verify_signature($payload, $signature, $secret)) {
            $valid_signature = true;
            break;
        }
    }

    if (!$valid_signature) {
        status_header(400);
        echo 'Invalid Stripe signature.';
        exit;
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        status_header(400);
        echo 'Invalid Stripe event payload.';
        exit;
    }

    $event_type = sanitize_text_field((string) $event['type']);
    $event_object = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : null;

    // Connect v2 thin events: fetch and sync account status so dashboard stays current.
    if (
        $event_type === 'v2.core.account[requirements].updated' ||
        strpos($event_type, 'v2.core.account[') === 0
    ) {
        $account_id = gb_extract_connected_account_id_from_event($event);

        if ($account_id === '' && !empty($event['id'])) {
            $full_event = gb_stripe_api_request_v2('core/events/' . rawurlencode($event['id']), [], 'GET');
            if (!is_wp_error($full_event) && is_array($full_event)) {
                $account_id = gb_extract_connected_account_id_from_event($full_event);
            }
        }

        if ($account_id !== '') {
            $cleaner_id = gb_find_cleaner_by_account_id($account_id);
            if ($cleaner_id) {
                gb_sync_cleaner_connect_account($cleaner_id);
            }
        }

        status_header(200);
        echo 'ok';
        exit;
    }

    if (!is_array($event_object)) {
        status_header(400);
        echo 'Invalid Stripe event payload.';
        exit;
    }

    $syncable_events = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    if (in_array($event_type, $syncable_events, true)) {
        $booking_id = isset($event_object['metadata']['booking_id']) ? absint($event_object['metadata']['booking_id']) : 0;
        $session_id = isset($event_object['id']) ? sanitize_text_field($event_object['id']) : '';

        if ($booking_id && $session_id) {
            $synced = gb_sync_booking_from_checkout_session($booking_id, $session_id);
            if (is_wp_error($synced)) {
                status_header(400);
                echo 'Stripe sync error.';
                exit;
            }
        }
    }

    if ($event_type === 'account.updated' && !empty($event_object['id'])) {
        $connected_account_id = sanitize_text_field((string) $event_object['id']);
        $cleaner_id = gb_find_cleaner_by_account_id($connected_account_id);
        if ($cleaner_id) {
            gb_sync_cleaner_connect_meta_from_account($cleaner_id, $event_object);
        }
    }

    status_header(200);
    echo 'ok';
    exit;
}
add_action('admin_post_gb_stripe_webhook', 'gb_handle_stripe_webhook');
add_action('admin_post_nopriv_gb_stripe_webhook', 'gb_handle_stripe_webhook');

/**
 * Initialize Stripe PHP SDK (Optional Helper)
 * Call this before making any direct API calls.
 */
function gb_init_stripe() {
    $config = gb_get_stripe_config();
    if ( ! empty($config['secret_key']) ) {
        // Require the Stripe library if you are using Composer or manual loading
        // \Stripe\Stripe::setApiKey($config['secret_key']);
    }
}
