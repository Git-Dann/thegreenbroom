<?php
/**
 * Anti-spam for public message forms
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
