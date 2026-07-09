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
 * Ensure standalone, editable legal pages exist (Cookie Policy, GDPR).
 * Previously these were footer links that only redirected into the Privacy
 * page, so they could not be edited in the dashboard. Provision them as real
 * pages so they get the same editing treatment as Terms & Conditions.
 */
function gb_ensure_legal_pages() {
    if ( get_option( 'gb_legal_pages_created' ) ) {
        return;
    }

    $legal_pages = array(
        'cookies' => array(
            'title'   => 'Cookie Policy',
            'content' => "This is your Cookie Policy. Replace this text with your full policy from the manager dashboard (Pages → Cookie Policy).\n\nWe use cookies to operate this website, remember your preferences, and understand how it is used.",
        ),
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
 * Email the manager when a new support/contact message arrives.
 * Destination is the "Support Notification Email" setting, falling back to
 * the WordPress admin email.
 */
function gb_notify_support_message( $message_id, $context = 'Contact' ) {
    $settings_id = (int) get_option( 'page_on_front' );
    $to = $settings_id ? get_post_meta( $settings_id, '_gb_support_email', true ) : '';
    if ( ! is_email( $to ) ) {
        $to = get_option( 'admin_email' );
    }
    if ( ! is_email( $to ) ) {
        return;
    }

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

    wp_mail( $to, $subject, $body, $headers );
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
           && get_post_meta( $cleaner_id, "_gb_ref{$n}_phone", true );
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
        . "5. Payments. Fees are handled through the Platform, subject to the applicable platform fee and an active monthly subscription.\n\n"
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
    
    // 2. Load Dashicons (Required for Dashboard Icons)
    wp_enqueue_style( 'dashicons' );

    // 3. Load Navigation Logic
    wp_enqueue_script( 'gb-nav-js', get_template_directory_uri() . '/nav.js', array(), $nav_version, true );

    // 4. Pass Data to JS (Useful for AJAX)
    wp_localize_script( 'gb-nav-js', 'gbData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'site_url' => home_url()
    ));
}
add_action( 'wp_enqueue_scripts', 'gb_load_assets' );

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

function gb_get_email_settings() {
    $settings_id = gb_get_site_settings_id();
    $verification_setting = get_post_meta( $settings_id, '_gb_require_email_verification', true );

    return [
        'verification_required' => $verification_setting === '' ? true : $verification_setting === 'yes',
        'from_name'             => sanitize_text_field( (string) get_post_meta( $settings_id, '_gb_email_from_name', true ) ),
        'from_address'          => sanitize_email( (string) get_post_meta( $settings_id, '_gb_email_from_address', true ) ),
        'reply_to'              => sanitize_email( (string) get_post_meta( $settings_id, '_gb_email_reply_to', true ) ),
        'verification_subject'  => sanitize_text_field( (string) get_post_meta( $settings_id, '_gb_email_verification_subject', true ) ),
    ];
}

function gb_get_email_from_name() {
    $settings = gb_get_email_settings();

    return $settings['from_name'] !== '' ? $settings['from_name'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

function gb_get_email_from_address() {
    $settings = gb_get_email_settings();
    if ( $settings['from_address'] !== '' ) {
        return $settings['from_address'];
    }

    $admin_email = get_option( 'admin_email' );

    return is_email( $admin_email ) ? $admin_email : '';
}

function gb_get_email_reply_to() {
    $settings = gb_get_email_settings();
    if ( $settings['reply_to'] !== '' ) {
        return $settings['reply_to'];
    }

    return gb_get_email_from_address();
}

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

    $sent = wp_mail( $user->user_email, $subject, $message, $headers );
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

    // Note: 'cookies' and 'gdpr' are now standalone editable pages (see
    // gb_ensure_legal_pages), so they are intentionally NOT redirected here.
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
    add_role( 'gb_manager', 'Manager', array('read'=>true, 'edit_posts'=>true, 'upload_files'=>true));
    add_role( 'gb_cleaner', 'Cleaner', array('read'=>true, 'upload_files'=>true, 'edit_posts'=>false));
    add_role( 'gb_customer', 'Customer', array('read'=>true, 'upload_files'=>false, 'edit_posts'=>false));

    // add_role() is a no-op if the role already exists in the DB, so any
    // capability added here later would never reach a site whose roles were
    // created before this line was added. Sync the required caps explicitly.
    $required_caps = array(
        'gb_manager'  => array('read', 'edit_posts', 'upload_files'),
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

// Redirect Non-Admins away from /wp-admin/
function gb_security_redirect() {
    // Allow selected unauthenticated admin-post actions.
    if (is_admin() && isset($_REQUEST['action'])) {
        $public_actions = [
            'gb_stripe_webhook',
            'gb_stripe_storefront_checkout',
        ];
        if (in_array(sanitize_key(wp_unslash($_REQUEST['action'])), $public_actions, true)) {
            return;
        }
    }

    if ( is_admin() && ! defined('DOING_AJAX') && ! current_user_can('administrator') ) {
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
        if ($service_post && $service_post->post_type === 'gb_service') {
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
        if ($service_post && $service_post->post_type === 'gb_service') {
            return $service_post;
        }
    }

    $service_query = new WP_Query([
        'post_type'      => 'gb_service',
        'post_status'    => ['publish', 'draft', 'pending'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'title'          => $name,
        'no_found_rows'  => true,
    ]);
    if (!empty($service_query->posts[0])) {
        $service_post = get_post((int) $service_query->posts[0]);
        if ($service_post && $service_post->post_type === 'gb_service') {
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

    $cleaner_id = intval($_POST['cleaner_id']);
    $service_name = sanitize_text_field($_POST['service']);
    $date = sanitize_text_field($_POST['date']);
    $time_str = sanitize_text_field($_POST['time']);
    $name = sanitize_text_field($_POST['customer_name']);
    $email = sanitize_email($_POST['customer_email']);
    $phone = sanitize_text_field($_POST['customer_phone']);
    $address = sanitize_textarea_field($_POST['customer_address']);

    // Default Duration (pulled from Services config when possible).
    $duration = gb_get_service_duration_hours($service_name, 2);

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

    $rate_state = gb_rate_limit_check('cleaner_message', 5, HOUR_IN_SECONDS, $email);
    if ($rate_state['blocked']) {
        wp_send_json_error(['message' => gb_get_rate_limit_message($rate_state)]);
    }
    gb_record_rate_limit_hit('cleaner_message', HOUR_IN_SECONDS, $email);
    
    if (!$name || !$email || !$body) wp_send_json_error(['message'=>'Please fill in all fields.']);

    $msg_id = wp_insert_post(['post_type'=>'gb_message', 'post_title'=>'Inquiry from '.$name, 'post_content'=>$body, 'post_status'=>'publish']);

    if ($msg_id) {
        update_post_meta($msg_id, '_gb_related_cleaner', $cleaner_id);
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

        $rate_state = gb_rate_limit_check('contact_form', 5, HOUR_IN_SECONDS, $email);
        if ($rate_state['blocked']) {
            $referer = wp_get_referer() ? wp_get_referer() : gb_get_contact_page_url();
            wp_safe_redirect(add_query_arg('contact_error', '1', $referer));
            exit;
        }
        gb_record_rate_limit_hit('contact_form', HOUR_IN_SECONDS, $email);

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
            gb_notify_support_message($ticket_id, 'Support');

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
        'platform_fee'    => get_post_meta($settings_id, '_gb_platform_fee', true) ?: '10',
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
    $from_email = get_option('admin_email');
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
        wp_mail($customer_email, $subject, $body);
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
            wp_mail($cleaner_user->user_email, $subject, $body);
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
