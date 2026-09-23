<?php
/**
 * Cleaner reviews, ratings and verification checks
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        // The default category was renamed; its old archive URL was indexable.
        'category/uncategorized' => home_url( '/category/eco-living/' ),
    ];

    $path_key = trim( gb_get_current_request_path(), '/' );
    if ( isset( $legacy_redirects[ $path_key ] ) ) {
        wp_safe_redirect( $legacy_redirects[ $path_key ], 301 );
        exit;
    }
}
add_action( 'template_redirect', 'gb_handle_public_route_fixes', 0 );
