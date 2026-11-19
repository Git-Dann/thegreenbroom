<?php
/**
 * Tracking: the Meta Pixel, Meta's Conversions API, and the site's own
 * cookieless visit counts that feed Manager OS > Analytics.
 *
 * Two separate things live here, on purpose.
 *
 * 1. OUR OWN COUNTS. Every page sends one tiny request back to the site
 *    saying which page it was and where the visitor came from. Nothing is
 *    stored in the browser and nothing identifies anyone: the server adds one
 *    to a daily total. Key actions (sign-ups, enquiries, payments) are counted
 *    the same way, on the server, when they happen. Browsers that send Global
 *    Privacy Control or Do Not Track are not counted at all. This is what the
 *    Analytics screen shows, and it covers every visitor.
 *
 * 2. META. The Meta Pixel and the Conversions API only ever run for a visitor
 *    who has allowed marketing cookies in the banner, which is what UK law
 *    (PECR) requires. Without that, nothing goes to Meta. The pixel is loaded
 *    by script only after consent; there is no <noscript> image, because that
 *    would fire with no consent at all.
 *
 * Events that happen on the server (a sign-up, an enquiry, a checkout) reach
 * the pixel through a short-lived first-party cookie that is only set for a
 * visitor who has already allowed marketing; the script fires them on the
 * next page and clears it. With a Conversions API token saved, the same
 * events are also sent from the server with the same event ID, so Meta counts
 * each one once even when an ad blocker stops the pixel.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_TRACK_TABLE_VERSION = '1';
const GB_TRACK_QUEUE_COOKIE  = 'gb_px_q';
// Meta retires Graph API versions about two years after release. If the test
// button in Analytics starts reporting an unsupported version, raise this.
const GB_META_GRAPH_VERSION  = 'v23.0';

/* ------------------------------------------------------------------------
 * Settings
 * --------------------------------------------------------------------- */

function gb_tracking_setting( $key ) {
    $settings_id = (int) get_option( 'page_on_front' );
    return $settings_id ? trim( (string) get_post_meta( $settings_id, $key, true ) ) : '';
}

function gb_meta_pixel_id() {
    $id = preg_replace( '/\D/', '', gb_tracking_setting( '_gb_meta_pixel_id' ) );
    return strlen( $id ) >= 10 ? $id : '';
}

/** On unless switched off, once an ID is saved. */
function gb_meta_pixel_enabled() {
    return gb_meta_pixel_id() !== '' && gb_tracking_setting( '_gb_meta_pixel_enabled' ) !== '0';
}

function gb_meta_capi_token() {
    return gb_tracking_setting( '_gb_meta_capi_token' );
}

function gb_meta_test_event_code() {
    return preg_replace( '/[^A-Za-z0-9]/', '', gb_tracking_setting( '_gb_meta_test_event_code' ) );
}

/** Whether Meta may be told about this visitor: pixel on, and marketing allowed. */
function gb_meta_may_track() {
    return gb_meta_pixel_enabled()
        && function_exists( 'gb_cookie_consent_allows' )
        && gb_cookie_consent_allows( 'marketing' );
}

/**
 * Everything the site measures: our own name for it, what it is called in
 * Meta, and where it happens. The Analytics screen is built from this, so it
 * always says exactly what is being tracked.
 */
function gb_tracking_catalogue() {
    return array(
        'page_view'          => array( 'label' => 'Page views',              'meta' => 'PageView',             'where' => 'Every page of the website' ),
        'profile_view'       => array( 'label' => 'Cleaner profile views',   'meta' => 'ViewContent',          'where' => "Opening a cleaner's profile" ),
        'join_view'          => array( 'label' => 'Become a Cleaner views',  'meta' => 'ViewContent',          'where' => 'Opening the Become a Cleaner page (/join/)' ),
        'listing_search'     => array( 'label' => 'Cleaner searches',        'meta' => 'Search',               'where' => 'Find a Cleaner, searching by area or filtering by service' ),
        'customer_signup'    => array( 'label' => 'Customer sign-ups',       'meta' => 'CompleteRegistration', 'where' => 'Creating a customer account' ),
        'cleaner_signup'     => array( 'label' => 'Cleaner sign-ups',        'meta' => 'CompleteRegistration', 'where' => 'Creating a cleaner account' ),
        'cleaner_enquiry'    => array( 'label' => 'Enquiries to cleaners',   'meta' => 'Lead',                 'where' => "Sending a message from a cleaner's profile" ),
        'contact_form'       => array( 'label' => 'Contact form messages',   'meta' => 'Contact',              'where' => 'Sending the Contact Us form' ),
        'booking_started'    => array( 'label' => 'Bookings started',        'meta' => 'InitiateCheckout',     'where' => 'Going to pay for a booking' ),
        'booking_paid'       => array( 'label' => 'Bookings paid',           'meta' => 'Purchase',             'where' => 'Stripe confirming a booking payment (with the amount)' ),
        'membership_started' => array( 'label' => 'Memberships started',     'meta' => 'InitiateCheckout',     'where' => 'Going to pay for a membership or cleaner listing' ),
        'membership_paid'    => array( 'label' => 'Customer memberships',    'meta' => 'Subscribe',            'where' => 'Stripe confirming a customer membership (with the amount)' ),
        'listing_paid'       => array( 'label' => 'Cleaner listings',        'meta' => 'StartTrial',           'where' => "Stripe confirming a cleaner's listing (the free months start a trial)" ),
    );
}

/* ------------------------------------------------------------------------
 * Our own counts
 * --------------------------------------------------------------------- */

function gb_analytics_table() {
    global $wpdb;
    return $wpdb->prefix . 'gb_analytics';
}

function gb_analytics_ready() {
    return get_option( 'gb_analytics_table_version' ) === GB_TRACK_TABLE_VERSION;
}

/**
 * One row per day, per thing counted, per label (a page, a source, a search).
 * The keys are kept short so the primary key fits older MySQL index limits.
 */
function gb_analytics_install() {
    global $wpdb;
    if ( gb_analytics_ready() ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = gb_analytics_table();
    $charset = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE {$table} (
  day date NOT NULL,
  metric varchar(32) NOT NULL,
  label varchar(150) NOT NULL DEFAULT '',
  hits int(10) unsigned NOT NULL DEFAULT 0,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,metric,label)
) {$charset};" );

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
        update_option( 'gb_analytics_table_version', GB_TRACK_TABLE_VERSION, false );
    }
}

function gb_analytics_record( $metric, $label = '', $amount = 0.0 ) {
    global $wpdb;
    if ( ! gb_analytics_ready() ) {
        return;
    }

    $label = function_exists( 'mb_substr' ) ? mb_substr( (string) $label, 0, 150 ) : substr( (string) $label, 0, 150 );

    $wpdb->query( $wpdb->prepare(
        'INSERT INTO ' . gb_analytics_table() . ' (day, metric, label, hits, amount) VALUES (%s, %s, %s, 1, %f)
         ON DUPLICATE KEY UPDATE hits = hits + 1, amount = amount + VALUES(amount)',
        current_time( 'Y-m-d' ),
        substr( (string) $metric, 0, 32 ),
        $label,
        (float) $amount
    ) );
}

/** Keep about 13 months, so a year can be compared with the one before. */
function gb_analytics_prune() {
    global $wpdb;
    if ( gb_analytics_ready() ) {
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . gb_analytics_table() . ' WHERE day < %s', gmdate( 'Y-m-d', time() - 400 * DAY_IN_SECONDS ) ) );
    }
}
add_action( 'gb_analytics_prune', 'gb_analytics_prune' );
add_action( 'init', function () {
    if ( gb_analytics_ready() && ! wp_next_scheduled( 'gb_analytics_prune' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gb_analytics_prune' );
    }
}, 40 );

/* ------------------------------------------------------------------------
 * Sending events
 * --------------------------------------------------------------------- */

/**
 * The one call the rest of the site makes when something worth knowing
 * happens: count it, and tell Meta if this visitor has allowed it.
 *
 * $opts: label (for our counts), amount (pounds), event_id, email, user_id.
 */
function gb_track( $metric, $meta_event, array $params = array(), array $opts = array() ) {
    gb_analytics_record( $metric, isset( $opts['label'] ) ? $opts['label'] : '', isset( $opts['amount'] ) ? (float) $opts['amount'] : 0 );
    gb_meta_event( $meta_event, $params, $opts + array( 'event_id' => $metric . '-' . wp_generate_uuid4() ) );
}

/** Meta only: for a browser-side echo of something already counted. */
function gb_meta_event( $event, array $params = array(), array $opts = array() ) {
    if ( ! gb_meta_may_track() ) {
        return;
    }

    $event_id = isset( $opts['event_id'] ) ? (string) $opts['event_id'] : 'gb-' . wp_generate_uuid4();
    gb_meta_queue_for_browser( $event, $params, $event_id );
    gb_meta_capi_send( $event, $params, $event_id, $opts );
}

/**
 * Hand an event to the pixel. Before any output it goes in a cookie, so it
 * survives the redirect that follows a form; once the page has started, it is
 * printed into this page's footer instead.
 */
function gb_meta_queue_for_browser( $event, array $params, $event_id ) {
    $item = array( 'e' => (string) $event, 'p' => $params, 'id' => (string) $event_id );

    if ( headers_sent() ) {
        $GLOBALS['gb_px_inline'][] = $item;
        return;
    }

    static $queue = null;
    if ( $queue === null ) {
        $queue = array();
        if ( ! empty( $_COOKIE[ GB_TRACK_QUEUE_COOKIE ] ) ) {
            $existing = json_decode( wp_unslash( $_COOKIE[ GB_TRACK_QUEUE_COOKIE ] ), true );
            $queue    = is_array( $existing ) ? $existing : array();
        }
    }
    $queue[] = $item;
    $queue   = array_slice( $queue, -5 );

    setcookie( GB_TRACK_QUEUE_COOKIE, wp_json_encode( $queue ), array(
        'expires'  => time() + 30 * MINUTE_IN_SECONDS,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => false, // the pixel script reads it
        'samesite' => 'Lax',
    ) );
}

/**
 * Meta's Conversions API: the same event, sent from the server, so it still
 * counts when an ad blocker or browser privacy setting stops the pixel. Only
 * runs with a token saved, and only for a visitor who allowed marketing.
 * The email address is hashed (SHA-256) before it leaves the site.
 */
function gb_meta_capi_send( $event, array $params, $event_id, array $opts = array(), $blocking = false ) {
    $token = gb_meta_capi_token();
    $pixel = gb_meta_pixel_id();
    if ( $token === '' || $pixel === '' ) {
        return null;
    }

    $user_data = array(
        'client_ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
        'client_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
    );
    foreach ( array( '_fbp' => 'fbp', '_fbc' => 'fbc' ) as $cookie => $field ) {
        if ( ! empty( $_COOKIE[ $cookie ] ) ) {
            $user_data[ $field ] = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) );
        }
    }

    $email   = isset( $opts['email'] ) ? (string) $opts['email'] : '';
    $user_id = isset( $opts['user_id'] ) ? (int) $opts['user_id'] : get_current_user_id();
    if ( $email === '' && $user_id ) {
        $user  = get_userdata( $user_id );
        $email = $user ? (string) $user->user_email : '';
    }
    if ( is_email( $email ) ) {
        $user_data['em'] = array( hash( 'sha256', strtolower( trim( $email ) ) ) );
    }
    if ( $user_id ) {
        $user_data['external_id'] = array( hash( 'sha256', 'gb-user-' . $user_id ) );
    }

    $source_url = wp_get_referer();
    if ( ! $source_url ) {
        $source_url = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
    }

    $payload = array(
        'data' => array( array(
            'event_name'       => (string) $event,
            'event_time'       => time(),
            'event_id'         => (string) $event_id,
            'action_source'    => 'website',
            'event_source_url' => esc_url_raw( $source_url ),
            'user_data'        => array_filter( $user_data ),
            'custom_data'      => (object) $params,
        ) ),
    );
    $test_code = gb_meta_test_event_code();
    if ( $test_code !== '' ) {
        $payload['test_event_code'] = $test_code;
    }

    return wp_remote_post(
        'https://graph.facebook.com/' . GB_META_GRAPH_VERSION . '/' . rawurlencode( $pixel ) . '/events?access_token=' . rawurlencode( $token ),
        array(
            'body'     => wp_json_encode( $payload ),
            'headers'  => array( 'Content-Type' => 'application/json' ),
            'timeout'  => $blocking ? 10 : 3,
            'blocking' => (bool) $blocking,
        )
    );
}

/**
 * The "Send a test event" button in Analytics. Waits for Meta's answer so the
 * screen can say plainly whether the connection works.
 */
function gb_meta_capi_test() {
    if ( gb_meta_capi_token() === '' ) {
        return array( 'ok' => false, 'message' => 'Save a Conversions API access token first.' );
    }

    $response = gb_meta_capi_send( 'PageView', array(), 'gb-test-' . wp_generate_uuid4(), array( 'user_id' => 0 ), true );
    if ( is_wp_error( $response ) ) {
        $result = array( 'ok' => false, 'message' => 'Could not reach Meta: ' . $response->get_error_message() );
    } else {
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( $code === 200 && ! empty( $body['events_received'] ) ) {
            $result = array( 'ok' => true, 'message' => 'Meta received the test event.' . ( gb_meta_test_event_code() !== '' ? ' It will show under Test events in Events Manager.' : '' ) );
        } else {
            $detail = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'HTTP ' . $code;
            $result = array( 'ok' => false, 'message' => 'Meta said no: ' . $detail );
        }
    }

    update_option( 'gb_meta_capi_last_test', $result + array( 'at' => current_time( 'mysql' ) ), false );
    return $result;
}

/* ------------------------------------------------------------------------
 * Money: memberships and bookings, counted once whatever confirms them
 * --------------------------------------------------------------------- */

function gb_tracking_plan_price( $plan_key ) {
    $catalogue = function_exists( 'gb_subscription_catalogue' ) ? gb_subscription_catalogue() : array();
    if ( empty( $catalogue[ $plan_key ]['price_field'] ) ) {
        return 0.0;
    }
    $text = gb_tracking_setting( $catalogue[ $plan_key ]['price_field'] );
    return (float) preg_replace( '/[^0-9.]/', '', $text );
}

function gb_tracking_plan_for_audience( $user_id, $audience ) {
    $catalogue = function_exists( 'gb_subscription_catalogue' ) ? gb_subscription_catalogue() : array();
    if ( $audience === 'cleaner' ) {
        return 'cleaner_monthly';
    }
    $plan = function_exists( 'gb_get_membership' ) ? (string) gb_get_membership( $user_id )['plan'] : '';
    foreach ( $catalogue as $key => $row ) {
        if ( $row['audience'] === 'customer' && $row['plan'] === $plan ) {
            return $key;
        }
    }
    return 'customer_quarterly';
}

/**
 * A booking turning "paid" (signed webhook, return from Stripe, or a manager
 * by hand) is counted here, once, with its value.
 */
function gb_tracking_watch_booking_payment( $check, $object_id, $meta_key, $meta_value ) {
    if ( $meta_key !== '_gb_payment_status' || $meta_value !== 'paid' || get_post_type( $object_id ) !== 'gb_booking' ) {
        return $check;
    }
    if ( get_post_meta( $object_id, '_gb_payment_status', true ) === 'paid' || get_post_meta( $object_id, '_gb_analytics_paid', true ) ) {
        return $check;
    }

    update_post_meta( $object_id, '_gb_analytics_paid', current_time( 'mysql' ) );
    gb_analytics_record( 'booking_paid', (string) get_post_meta( $object_id, '_gb_booking_service', true ), (float) get_post_meta( $object_id, '_gb_booking_total_gbp', true ) );
    return $check;
}
add_filter( 'update_post_metadata', 'gb_tracking_watch_booking_payment', 10, 4 );
add_filter( 'add_post_metadata', 'gb_tracking_watch_booking_payment', 10, 4 );

/** A membership or cleaner listing becoming active, counted once per start. */
function gb_tracking_watch_membership( $check, $object_id, $meta_key, $meta_value ) {
    $watched = array(
        defined( 'GB_MEMBER_STATUS' ) ? GB_MEMBER_STATUS : 'gb_member_status' => 'customer',
        '_gb_listing_status' => 'cleaner',
    );
    if ( ! isset( $watched[ $meta_key ] ) || $meta_value !== 'active' ) {
        return $check;
    }
    if ( get_user_meta( $object_id, $meta_key, true ) === 'active' ) {
        return $check;
    }

    $audience = $watched[ $meta_key ];
    $plan_key = gb_tracking_plan_for_audience( $object_id, $audience );
    gb_analytics_record( $audience === 'cleaner' ? 'listing_paid' : 'membership_paid', $plan_key, gb_tracking_plan_price( $plan_key ) );
    return $check;
}
add_filter( 'update_user_metadata', 'gb_tracking_watch_membership', 10, 4 );
add_filter( 'add_user_metadata', 'gb_tracking_watch_membership', 10, 4 );

/**
 * Called by the booking confirmation page once payment is confirmed: tells
 * the pixel about the purchase, once per booking, with its value.
 */
function gb_tracking_booking_confirmed( $booking_id ) {
    $booking_id = (int) $booking_id;
    if ( ! $booking_id || get_post_meta( $booking_id, '_gb_px_purchase_sent', true ) ) {
        return;
    }
    update_post_meta( $booking_id, '_gb_px_purchase_sent', current_time( 'mysql' ) );

    $value = (float) get_post_meta( $booking_id, '_gb_booking_total_gbp', true );
    gb_meta_event( 'Purchase', array(
        'value'        => round( $value, 2 ),
        'currency'     => 'GBP',
        'content_name' => (string) get_post_meta( $booking_id, '_gb_booking_service', true ),
        'content_type' => 'product',
        'content_ids'  => array( (string) get_post_meta( $booking_id, '_gb_booking_cleaner_id', true ) ),
    ), array(
        'event_id' => 'booking-' . $booking_id,
        'email'    => (string) get_post_meta( $booking_id, '_gb_customer_email', true ),
    ) );
}

/** Called by the membership success page once the membership is live. */
function gb_tracking_membership_confirmed( $user_id, $plan_key, $audience ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) {
        return;
    }
    $plan_key = $plan_key !== '' ? $plan_key : gb_tracking_plan_for_audience( $user_id, $audience );
    $marker   = $plan_key . '|' . ( $audience === 'cleaner' ? get_user_meta( $user_id, '_gb_listing_renews', true ) : ( function_exists( 'gb_get_membership' ) ? gb_get_membership( $user_id )['started'] : '' ) );
    if ( get_user_meta( $user_id, '_gb_px_subscription_sent', true ) === $marker ) {
        return;
    }
    update_user_meta( $user_id, '_gb_px_subscription_sent', $marker );

    $catalogue = function_exists( 'gb_subscription_catalogue' ) ? gb_subscription_catalogue() : array();
    $trial     = ! empty( $catalogue[ $plan_key ]['trial_days'] );
    $price     = gb_tracking_plan_price( $plan_key );

    gb_meta_event( $trial ? 'StartTrial' : 'Subscribe', array(
        'value'         => $trial ? 0 : round( $price, 2 ),
        'currency'      => 'GBP',
        'predicted_ltv' => round( $price * 12, 2 ),
        'content_name'  => isset( $catalogue[ $plan_key ]['label'] ) ? $catalogue[ $plan_key ]['label'] : $plan_key,
    ), array(
        'event_id' => 'sub-' . $user_id . '-' . md5( $marker ),
        'user_id'  => $user_id,
    ) );
}

/* ------------------------------------------------------------------------
 * The browser side
 * --------------------------------------------------------------------- */

/**
 * Events that belong to the page itself rather than to a visitor, so they
 * are safe on a cached page: every visitor to this URL gets the same ones.
 */
function gb_tracking_page_events() {
    $events = array();

    if ( is_singular( 'gb_cleaner' ) ) {
        $id       = get_queried_object_id();
        $events[] = array( 'm' => 'profile_view', 'l' => get_the_title( $id ), 'e' => 'ViewContent', 'p' => array(
            'content_type'     => 'product',
            'content_ids'      => array( (string) $id ),
            'content_name'     => get_the_title( $id ),
            'content_category' => 'Cleaner',
        ) );
    } elseif ( is_page( 'join' ) ) {
        $events[] = array( 'm' => 'join_view', 'l' => '', 'e' => 'ViewContent', 'p' => array(
            'content_name'     => 'Become a Cleaner',
            'content_category' => 'Recruitment',
        ) );
    } elseif ( is_page( 'cleaner-listings' ) || is_page_template( 'page-cleaner-listings.php' ) ) {
        $area    = isset( $_GET['k'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) ) : '';
        $service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';
        $search  = trim( $area . ( $area !== '' && $service !== '' ? ' / ' : '' ) . $service );
        if ( $search !== '' ) {
            $events[] = array( 'm' => 'listing_search', 'l' => $search, 'e' => 'Search', 'p' => array(
                'search_string'    => $search,
                'content_category' => 'Cleaner',
            ) );
        }
    }

    return $events;
}

function gb_tracking_print_footer_script() {
    if ( is_admin() || is_page_template( 'page-manager-dashboard.php' ) || is_page( 'manager-dashboard' ) ) {
        return;
    }

    $config = array(
        'pixel'   => gb_meta_pixel_enabled() ? gb_meta_pixel_id() : '',
        'ajax'    => admin_url( 'admin-ajax.php' ),
        'host'    => wp_parse_url( home_url(), PHP_URL_HOST ),
        'is404'   => is_404(),
        'page'    => gb_tracking_page_events(),
        'inline'  => isset( $GLOBALS['gb_px_inline'] ) ? array_values( $GLOBALS['gb_px_inline'] ) : array(),
        'consent' => defined( 'GB_CONSENT_COOKIE' ) ? GB_CONSENT_COOKIE : 'gb_cookie_consent',
        'version' => defined( 'GB_CONSENT_VERSION' ) ? GB_CONSENT_VERSION : 1,
        'queue'   => GB_TRACK_QUEUE_COOKIE,
    );
    ?>
    <script>
    (function () {
        var C = <?php echo wp_json_encode( $config ); ?>;

        function cookie(name) {
            var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : null;
        }

        /* Our own counts: no cookies, no storage, nothing that identifies anyone. */
        var optedOut = navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || window.doNotTrack === '1';
        function hit(metric, label) {
            if (optedOut) { return; }
            try {
                var fd = new FormData();
                fd.append('action', 'gb_hit');
                fd.append('m', metric);
                fd.append('l', label || '');
                if (navigator.sendBeacon) { navigator.sendBeacon(C.ajax, fd); }
                else { fetch(C.ajax, { method: 'POST', body: fd, keepalive: true, credentials: 'omit' }); }
            } catch (e) { /* counting must never break the page */ }
        }

        function source() {
            var q = new URLSearchParams(location.search);
            if (q.get('utm_source')) {
                return (q.get('utm_source') + (q.get('utm_campaign') ? ' / ' + q.get('utm_campaign') : '')).slice(0, 140);
            }
            if (q.get('fbclid')) { return 'Facebook or Instagram ad'; }
            if (q.get('gclid')) { return 'Google ad'; }
            var ref = '';
            try { ref = document.referrer ? new URL(document.referrer).hostname.replace(/^www\./, '') : ''; } catch (e) {}
            if (ref === C.host || ref === C.host.replace(/^www\./, '')) { return null; }
            if (!ref) { return 'Direct or bookmark'; }
            if (/(^|\.)facebook\.com$|(^|\.)fb\.com$/.test(ref)) { return 'Facebook'; }
            if (/instagram\.com$/.test(ref)) { return 'Instagram'; }
            if (/(^|\.)google\./.test(ref)) { return 'Google'; }
            if (/bing\.com$/.test(ref)) { return 'Bing'; }
            if (/(^|\.)t\.co$|twitter\.com$|x\.com$/.test(ref)) { return 'X (Twitter)'; }
            if (/linkedin\.com$/.test(ref)) { return 'LinkedIn'; }
            if (/mail\.|outlook\.|gmail/.test(ref)) { return 'Email'; }
            return ref.slice(0, 140);
        }

        hit('page_view', C.is404 ? '(page not found)' : location.pathname);
        var src = source();
        if (src) { hit('landing', src); }
        C.page.forEach(function (ev) { hit(ev.m, ev.l); });

        /* Meta: only after the visitor has allowed marketing. */
        var loaded = false;
        function allowed() {
            try {
                var raw = cookie(C.consent) || window.localStorage.getItem(C.consent);
                var c = raw ? JSON.parse(raw) : null;
                return !!(c && c.v === C.version && c.marketing);
            } catch (e) { return false; }
        }
        function flushQueue() {
            var raw = cookie(C.queue);
            if (!raw || !loaded) { return; }
            document.cookie = C.queue + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
            try {
                JSON.parse(raw).forEach(function (ev) { fbq('track', ev.e, ev.p || {}, { eventID: ev.id }); });
            } catch (e) {}
        }
        function loadPixel() {
            if (loaded || !C.pixel) { return; }
            loaded = true;
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', C.pixel);
            fbq('track', 'PageView');
            C.page.forEach(function (ev) { fbq('track', ev.e, ev.p || {}); });
            C.inline.forEach(function (ev) { fbq('track', ev.e, ev.p || {}, { eventID: ev.id }); });
            flushQueue();
        }

        // After an enquiry is sent without leaving the page.
        window.gbFlushTracking = flushQueue;

        if (allowed()) { loadPixel(); }
        document.addEventListener('gb:consent', function (e) {
            // Choices already stored fire before this script runs, so every
            // event heard here is a fresh decision on this page.
            var d = e.detail || {};
            hit('consent', d.marketing ? 'Allowed marketing' : 'Essential only');
            if (d.marketing) { loadPixel(); }
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'gb_tracking_print_footer_script', 20 );

/**
 * The count request from the script above. Only known measures are accepted,
 * labels are cut down to size, and obvious robots are ignored.
 */
function gb_tracking_handle_hit() {
    $allowed = array( 'page_view', 'landing', 'profile_view', 'join_view', 'listing_search', 'consent' );
    $metric  = isset( $_POST['m'] ) ? sanitize_key( wp_unslash( $_POST['m'] ) ) : '';
    $label   = isset( $_POST['l'] ) ? sanitize_text_field( wp_unslash( $_POST['l'] ) ) : '';
    $agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : ( isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '' );
    $same   = $origin !== '' && wp_parse_url( $origin, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST );

    if ( $same && in_array( $metric, $allowed, true ) && ! preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse/i', $agent ) ) {
        if ( $metric === 'page_view' && $label !== '(page not found)' ) {
            $label = '/' . ltrim( (string) wp_parse_url( $label, PHP_URL_PATH ), '/' );
        }
        if ( $metric === 'consent' && ! in_array( $label, array( 'Allowed marketing', 'Essential only' ), true ) ) {
            $label = 'Essential only';
        }
        gb_analytics_record( $metric, $label );
    }

    status_header( 204 );
    exit;
}
add_action( 'wp_ajax_gb_hit', 'gb_tracking_handle_hit' );
add_action( 'wp_ajax_nopriv_gb_hit', 'gb_tracking_handle_hit' );

/* ------------------------------------------------------------------------
 * One-off setup
 * --------------------------------------------------------------------- */

/** The pixel Nick sent over (November 2026), switched on. */
function gb_seed_meta_pixel_id() {
    if ( get_option( 'gb_meta_pixel_seeded' ) ) {
        return;
    }
    $settings_id = (int) get_option( 'page_on_front' );
    if ( $settings_id && gb_tracking_setting( '_gb_meta_pixel_id' ) === '' ) {
        update_post_meta( $settings_id, '_gb_meta_pixel_id', '1123172770640020' );
    }
    update_option( 'gb_meta_pixel_seeded', 1, false );
}

/**
 * The cookie policy said, truthfully until now, that there was no Meta pixel.
 * Replace those passages with what the site does now. Only the original
 * wording is replaced; if it has been rewritten by hand, a clearly headed
 * section is added instead so nothing anyone wrote is lost.
 */
function gb_update_cookie_policy_for_meta() {
    // v2: the first pass only matched paragraphs stored with their <p> tags.
    // A page saved through the Manager OS editor keeps paragraphs as plain
    // text separated by blank lines, so most of the old wording stayed.
    if ( get_option( 'gb_cookie_policy_meta_v2' ) ) {
        return;
    }
    $page = get_page_by_path( 'cookies', OBJECT, 'page' );
    if ( ! $page ) {
        update_option( 'gb_cookie_policy_meta_v2', 1, false );
        return;
    }

    $original = (string) $page->post_content;
    $content  = gb_cookie_policy_apply_meta_wording( $original );

    // Still promising there is no pixel (the wording was rewritten by hand):
    // add a clearly headed section rather than leave the policy wrong.
    if ( stripos( $content, 'no Meta pixel' ) !== false || strpos( $content, 'Meta Pixel' ) === false ) {
        $content .= "\n\n<h2>Advertising: the Meta Pixel</h2>\n" . gb_cookie_policy_meta_section();
    }

    if ( $content !== $original ) {
        wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ) );
    }
    update_option( 'gb_cookie_policy_meta_v2', 1, false );
}

/**
 * Swap each old passage for the new one, whether or not it sits in <p> tags
 * and however its lines were wrapped.
 */
function gb_cookie_policy_apply_meta_wording( $content ) {
    foreach ( gb_cookie_policy_meta_replacements() as $old => $new ) {
        $is_para = strpos( $old, '<p>' ) === 0;
        $inner   = $is_para ? substr( $old, 3, -4 ) : $old;
        $pattern = preg_quote( $inner, '#' );
        $pattern = preg_replace( '/\\ |\s+/', '\\s+', $pattern );
        $pattern = $is_para ? '#(?:<p>)?\s*' . $pattern . '\s*(?:</p>)?#u' : '#' . $pattern . '#u';
        $content = preg_replace_callback( $pattern, static function () use ( $new ) {
            return $new;
        }, $content );
    }
    return $content;
}

function gb_cookie_policy_meta_section() {
    return '<p>Only if you allow <strong>marketing</strong> cookies, we use the <strong>Meta Pixel</strong> (Meta runs Facebook and Instagram) to see which of our adverts work and to show them to people likely to find us useful. It sets cookies such as <em>_fbp</em>, kept for up to 90 days, and tells Meta when someone views a cleaner\'s profile, searches, creates an account, sends an enquiry, books or joins.</p>'
        . '<p>For the same reasons, and again only if you allow marketing cookies, we may send those actions to Meta directly from our server. If you are signed in, your email address is scrambled (hashed) before it is sent, so Meta can match it to an account it already knows but cannot read it. We set a short-lived cookie, <em>gb_px_q</em>, to pass an action you have just taken to the pixel on the next page; it lasts 30 minutes.</p>'
        . '<p>Meta\'s <a href="https://www.facebook.com/privacy/policy/" rel="noopener">privacy policy</a> explains how it uses this information. Choose <strong>Essential only</strong>, or untick Marketing under <strong>Cookie settings</strong>, and none of this happens.</p>';
}

function gb_cookie_policy_meta_replacements() {
    return array(
        '<p>Browsing The Green Broom sets no cookies at all. We only store anything once you sign in, or once you answer the cookie banner. We do not use advertising or analytics cookies, and we do not sell or share your browsing data.</p>'
            => '<p>Browsing The Green Broom sets no cookies until you sign in or answer the cookie banner. If you allow marketing cookies, we use the Meta Pixel to measure our adverts on Facebook and Instagram. We never sell your data.</p>',
        '<p>None at present. We do not run Google Analytics, Tag Manager, or any similar measurement tool. The banner asks about analytics so that, if we add one, your answer is already recorded and honoured from the outset rather than applied after the fact.</p>'
            => '<p>We do not use analytics cookies, and we do not run Google Analytics or any similar tool. We count visits ourselves, without cookies: each page tells our own server which page it was and where you came from (for example, from Facebook or a search engine), and the server adds one to a daily total. Nothing is stored on your device, nothing identifies you, and no other company is involved. If your browser sends a Global Privacy Control or Do Not Track signal, you are not counted at all.</p>',
        '<p>None at present. There is no Meta pixel, no TikTok pixel, and no advertising tag of any kind on this site. As with analytics, the banner asks in advance so that nothing is ever set before you have agreed to it.</p>'
            => gb_cookie_policy_meta_section(),
        'These are ordinary links &mdash; nothing is loaded from those companies while you are on our site, and they cannot see your visit unless you click through.'
            => 'These are ordinary links. Apart from the Meta Pixel described above, which only runs if you allow marketing cookies, nothing is loaded from those companies while you are on our site.',
        // The same sentence if the dash was stored as the character itself.
        "These are ordinary links \u{2014} nothing is loaded from those companies while you are on our site, and they cannot see your visit unless you click through."
            => 'These are ordinary links. Apart from the Meta Pixel described above, which only runs if you allow marketing cookies, nothing is loaded from those companies while you are on our site.',
    );
}

/**
 * The banner's old wording only talked about measuring the site. If it was
 * never changed by hand, drop the stored copy so the new default, which names
 * advertising and Meta, is shown.
 */
function gb_refresh_cookie_bar_wording() {
    if ( get_option( 'gb_cookie_bar_wording_v2' ) ) {
        return;
    }
    $settings_id = (int) get_option( 'page_on_front' );
    $old         = 'We use essential cookies to keep the site working and you signed in. With your permission we would also like to measure how the site is used so we can improve it.';
    if ( $settings_id && trim( (string) get_post_meta( $settings_id, '_gb_cookie_bar_body', true ) ) === $old ) {
        delete_post_meta( $settings_id, '_gb_cookie_bar_body' );
    }
    update_option( 'gb_cookie_bar_wording_v2', 1, false );
}

/** Remove the two rows left by the checks run when this was deployed. */
function gb_analytics_remove_deploy_checks() {
    global $wpdb;
    if ( get_option( 'gb_analytics_deploy_checks_removed' ) || ! gb_analytics_ready() ) {
        return;
    }
    $wpdb->query( $wpdb->prepare(
        'DELETE FROM ' . gb_analytics_table() . ' WHERE (metric = %s AND label = %s) OR (metric = %s AND label = %s)',
        'landing', 'test / deploy-check', 'page_view', '/deploy-check/'
    ) );
    update_option( 'gb_analytics_deploy_checks_removed', 1, false );
}
