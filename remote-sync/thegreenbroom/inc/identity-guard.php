<?php
/**
 * One free trial per person, and only paid listings on show.
 *
 * Cleaners pay a monthly listing subscription with a free three months at the
 * start. Two holes made that free period repeatable:
 *
 *   - every new checkout got the trial, so cancelling and subscribing again
 *     started another three months;
 *   - a second account with a slightly different email (j.smith@gmail.com
 *     and jsmith+2@gmail.com reach the same inbox) was a stranger as far as
 *     the site knew, and got its own three months.
 *
 * And nothing enforced the subscription at all: a cleaner whose listing was
 * cancelled, or never paid for, stayed listed and bookable.
 *
 * This file closes both. It works in three layers:
 *
 * 1. Fingerprints. The things that identify a person (email, phone, name with
 *    postcode area, the card they pay with, the IP they signed up from) are
 *    normalised so trivial variations collapse to one value, then hashed with
 *    the site's secret salt. Only the hashes are stored: a hash can be
 *    compared with another hash, but it cannot be read back into a phone
 *    number, so the registry is not a second copy of anybody's details.
 *
 * 2. A registry of everyone who has had a cleaner free trial, kept per Stripe
 *    mode (test trials say nothing about live ones). A checkout only offers
 *    the trial when none of this person's fingerprints are in it.
 *
 * 3. A listing gate, off until the client switches it on at go-live, which
 *    hides cleaners without a paid (or trialing) listing in the CURRENT
 *    Stripe mode from every public list and profile, and stops them being
 *    booked.
 *
 * What each signal does:
 *
 *   email (normalised)     refuses a new cleaner registration outright: it is
 *                          the same inbox, so it is the same person, and they
 *                          are pointed at log in / reset password instead.
 *   phone, name+postcode   allow the account, but flag it in Manager OS as a
 *   area, card, name+IP    possible duplicate of the other cleaner and remove
 *                          the free trial. Shared phones and namesakes exist,
 *                          so these never block on their own; a manager can
 *                          clear the flag.
 *   IP on its own          nothing. Flats, offices and mobile networks share
 *                          addresses; it only counts alongside the name.
 *   card, after checkout   if the card has already paid for (or started) a
 *                          free trial on another account, that trial is ended
 *                          at once so the listing is charged, both accounts
 *                          are flagged and support is emailed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------------------------------
   NORMALISING
   -------------------------------------------------------------------------- */

/**
 * One inbox, one value.
 *
 * Gmail ignores dots in the local part and everything after a plus, and
 * googlemail.com is the same service. Most other providers honour plus
 * addressing too, so the tag is dropped everywhere; dots are only dropped for
 * Gmail, because elsewhere j.smith and jsmith can be two different people.
 */
function gb_identity_normalise_email( $email ) {
    $email = strtolower( trim( (string) $email ) );

    if ( $email === '' || strpos( $email, '@' ) === false ) {
        return '';
    }

    $at     = strrpos( $email, '@' );
    $local  = substr( $email, 0, $at );
    $domain = rtrim( substr( $email, $at + 1 ), '.' );

    if ( $local === '' || $domain === '' ) {
        return '';
    }

    $plus = strpos( $local, '+' );
    if ( $plus !== false ) {
        $local = substr( $local, 0, $plus );
    }

    if ( $domain === 'googlemail.com' ) {
        $domain = 'gmail.com';
    }

    if ( $domain === 'gmail.com' ) {
        $local = str_replace( '.', '', $local );
    }

    return $local === '' ? '' : $local . '@' . $domain;
}

/**
 * A UK phone number in one shape: 07700 900123, +44 7700 900123,
 * 0044 (0)7700-900123 and 7700900123 all become 07700900123.
 *
 * Numbers from elsewhere are kept as their digits, which still matches the
 * same number typed twice. Anything too short to be a phone number gives ''
 * so that "n/a" or "0" never matches everybody else who typed the same.
 */
function gb_identity_normalise_phone( $phone ) {
    $digits = preg_replace( '/\D+/', '', (string) $phone );

    if ( strpos( $digits, '00' ) === 0 ) {
        $digits = substr( $digits, 2 );
    }

    if ( strpos( $digits, '44' ) === 0 && strlen( $digits ) >= 11 ) {
        $rest = substr( $digits, 2 );
        // +44 (0)7700... carries the trunk zero as well.
        if ( strpos( $rest, '0' ) === 0 ) {
            $rest = substr( $rest, 1 );
        }
        $digits = '0' . $rest;
    } elseif ( strlen( $digits ) === 10 && strpos( $digits, '7' ) === 0 ) {
        // A mobile typed without its leading zero.
        $digits = '0' . $digits;
    }

    return strlen( $digits ) >= 9 ? $digits : '';
}

/**
 * A name in one shape: lower case, accents and punctuation gone, hyphens
 * read as spaces, titles dropped, spaces collapsed. "Mrs Mary-Jane O'Neil"
 * and "mary jane  oneil" are the same.
 */
function gb_identity_normalise_name( $name ) {
    $name = remove_accents( wp_strip_all_tags( (string) $name ) );
    $name = strtolower( $name );
    $name = str_replace( array( '-', '_', '.' ), ' ', $name );
    $name = preg_replace( '/[^a-z0-9 ]+/', '', $name );
    $name = trim( preg_replace( '/\s+/', ' ', $name ) );

    $words = array_values( array_filter( explode( ' ', $name ), static function ( $word ) {
        return ! in_array( $word, array( 'mr', 'mrs', 'ms', 'miss', 'mx', 'dr' ), true );
    } ) );

    return implode( ' ', $words );
}

/**
 * The postcode district (outward code) in a location, or ''.
 *
 * Cleaners record a service area such as "Mossley Hill, Liverpool, L18" or a
 * full postcode "L18 3HQ"; the district is the part both share. Without a
 * district there is no area signal: "Liverpool" alone covers half a million
 * people, and a common name there is not evidence of anything.
 */
function gb_identity_postcode_area( $location ) {
    $text = strtoupper( (string) $location );

    if ( preg_match( '/\b([A-Z]{1,2}[0-9][A-Z0-9]?)\s*[0-9][A-Z]{2}\b/', $text, $m ) ) {
        return $m[1];
    }

    if ( preg_match( '/\b([A-Z]{1,2}[0-9][A-Z0-9]?)\b/', $text, $m ) ) {
        return $m[1];
    }

    return '';
}

/**
 * The stored form of a signal. Keyed with the site's secret salt, so the
 * hashes mean nothing outside this site and cannot be reversed by guessing
 * phone numbers against a plain SHA-256.
 */
function gb_identity_hash( $signal, $value ) {
    $value = (string) $value;

    if ( $value === '' ) {
        return '';
    }

    return hash_hmac( 'sha256', $signal . ':' . $value, wp_salt( 'auth' ) );
}

/* --------------------------------------------------------------------------
   WHO SOMEBODY IS
   -------------------------------------------------------------------------- */

/**
 * The signals, in the words a manager reads.
 */
function gb_identity_signal_labels() {
    return array(
        'email'     => 'Email address',
        'phone'     => 'Phone number',
        'name_area' => 'Name and postcode area',
        'card'      => 'Payment card',
        'name_ip'   => 'Name and sign-up IP address',
    );
}

function gb_identity_signal_list( array $signals ) {
    $labels = gb_identity_signal_labels();
    $words  = array();

    foreach ( $signals as $signal ) {
        $words[] = isset( $labels[ $signal ] ) ? $labels[ $signal ] : $signal;
    }

    return implode( ', ', array_unique( $words ) );
}

/**
 * The stored hashes, one user meta row each so they can be looked up with an
 * ordinary meta query rather than by loading every account.
 */
function gb_identity_meta_keys() {
    return array(
        'email'     => '_gb_fp_email',
        'phone'     => '_gb_fp_phone',
        'name'      => '_gb_fp_name',
        'name_area' => '_gb_fp_name_area',
        'ip'        => '_gb_fp_ip',
        'card'      => '_gb_fp_card',
    );
}

function gb_identity_is_staff_user( $user_id ) {
    $user = get_userdata( (int) $user_id );

    if ( ! $user ) {
        return false;
    }

    return user_can( $user, 'administrator' ) || in_array( 'gb_manager', (array) $user->roles, true );
}

/**
 * The cleaner profile a user owns, or 0.
 */
function gb_identity_cleaner_post_for_user( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id < 1 ) {
        return 0;
    }

    $ids = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'author'         => $user_id,
        'post_status'    => array( 'publish', 'pending', 'draft' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ) );

    return empty( $ids ) ? 0 : (int) $ids[0];
}

function gb_identity_is_cleaner_user( $user_id ) {
    $user = get_userdata( (int) $user_id );

    if ( ! $user || gb_identity_is_staff_user( $user->ID ) ) {
        return false;
    }

    return in_array( 'gb_cleaner', (array) $user->roles, true ) || gb_identity_cleaner_post_for_user( $user->ID ) > 0;
}

/**
 * Work out the signals that can be recomputed from what is on file now, and
 * store their hashes. The sign-up IP and the card cannot be recomputed (they
 * are only seen at the moment they happen) so they are left as they are.
 *
 * @return array signal => hash, for every signal this user has
 */
function gb_identity_refresh_user( $user_id ) {
    $user = get_userdata( (int) $user_id );

    if ( ! $user ) {
        return array();
    }

    $keys    = gb_identity_meta_keys();
    $cleaner = gb_identity_cleaner_post_for_user( $user->ID );

    $name_source = $cleaner ? get_the_title( $cleaner ) : '';
    if ( trim( (string) $name_source ) === '' ) {
        $name_source = trim( $user->first_name . ' ' . $user->last_name ) !== '' ? $user->first_name . ' ' . $user->last_name : $user->display_name;
    }

    $name  = gb_identity_normalise_name( $name_source );
    $area  = $cleaner && function_exists( 'gb_get_cleaner_location' ) ? gb_identity_postcode_area( gb_get_cleaner_location( $cleaner ) ) : '';
    $phone = $cleaner ? gb_identity_normalise_phone( get_post_meta( $cleaner, '_gb_cleaner_phone', true ) ) : '';

    // A name has to be more than one word to say anything on its own.
    $name_ok = strpos( $name, ' ' ) !== false;

    $computed = array(
        'email'     => gb_identity_hash( 'email', gb_identity_normalise_email( $user->user_email ) ),
        'phone'     => gb_identity_hash( 'phone', $phone ),
        'name'      => $name_ok ? gb_identity_hash( 'name', $name ) : '',
        'name_area' => $name_ok && $area !== '' ? gb_identity_hash( 'name_area', $name . '|' . $area ) : '',
    );

    foreach ( $computed as $signal => $hash ) {
        if ( $hash === '' ) {
            delete_user_meta( $user->ID, $keys[ $signal ] );
        } else {
            update_user_meta( $user->ID, $keys[ $signal ], $hash );
        }
    }

    return gb_identity_fingerprints( $user->ID );
}

/**
 * Every stored hash for a user.
 */
function gb_identity_fingerprints( $user_id ) {
    $out = array();

    foreach ( gb_identity_meta_keys() as $signal => $key ) {
        $value = (string) get_user_meta( (int) $user_id, $key, true );

        if ( $value !== '' ) {
            $out[ $signal ] = $value;
        }
    }

    return $out;
}

/**
 * Other accounts holding the same hash for a signal.
 */
function gb_identity_users_with( $signal, $hash, $exclude = 0 ) {
    $keys = gb_identity_meta_keys();

    if ( $hash === '' || ! isset( $keys[ $signal ] ) ) {
        return array();
    }

    $ids = get_users( array(
        'meta_key'   => $keys[ $signal ],
        'meta_value' => $hash,
        'exclude'    => $exclude ? array( (int) $exclude ) : array(),
        'fields'     => 'ID',
        'number'     => 25,
    ) );

    return array_map( 'intval', $ids );
}

/* --------------------------------------------------------------------------
   POSSIBLE DUPLICATES
   -------------------------------------------------------------------------- */

/**
 * Other cleaner accounts that look like the same person, with the signals
 * that matched. IP only ever counts together with the name.
 *
 * @return array other_user_id => list of signals
 */
function gb_identity_find_duplicates( $user_id ) {
    $user_id = (int) $user_id;
    $fps     = gb_identity_fingerprints( $user_id );
    $found   = array();

    foreach ( array( 'email', 'phone', 'name_area', 'card' ) as $signal ) {
        if ( empty( $fps[ $signal ] ) ) {
            continue;
        }

        foreach ( gb_identity_users_with( $signal, $fps[ $signal ], $user_id ) as $other ) {
            $found[ $other ][] = $signal;
        }
    }

    if ( ! empty( $fps['name'] ) && ! empty( $fps['ip'] ) ) {
        foreach ( gb_identity_users_with( 'name', $fps['name'], $user_id ) as $other ) {
            if ( (string) get_user_meta( $other, '_gb_fp_ip', true ) === $fps['ip'] ) {
                $found[ $other ][] = 'name_ip';
            }
        }
    }

    foreach ( array_keys( $found ) as $other ) {
        if ( ! gb_identity_is_cleaner_user( $other ) ) {
            unset( $found[ $other ] );
        }
    }

    return $found;
}

/**
 * The flags on an account.
 *
 * Keyed by the other account's user id:
 *   kind     'duplicate' (looks like the same person) or 'abuse' (a card that
 *            already had a free trial was used again)
 *   signals  which signals matched
 *   newer    true when THIS is the later account, which is the one that loses
 *            the free trial; the earlier one is only told about it
 *   at       when it was first raised
 *   deleted  the other account no longer exists (a registry match)
 */
function gb_identity_get_flags( $user_id ) {
    $flags = get_user_meta( (int) $user_id, '_gb_identity_flags', true );

    return is_array( $flags ) ? $flags : array();
}

/**
 * Pairs a manager has looked at and said are different people.
 */
function gb_identity_dismissed( $user_id ) {
    $dismissed = get_user_meta( (int) $user_id, '_gb_identity_dismissed', true );

    return is_array( $dismissed ) ? array_map( 'intval', $dismissed ) : array();
}

/**
 * Raise or extend a flag. Flags stick: changing the phone number afterwards
 * does not make an account look new again. Only a manager clears one.
 */
function gb_identity_add_flag( $user_id, $other_id, $kind, array $signals, $newer, $deleted = false ) {
    $user_id  = (int) $user_id;
    $other_id = (int) $other_id;

    if ( $user_id < 1 || $other_id < 1 || $user_id === $other_id || in_array( $other_id, gb_identity_dismissed( $user_id ), true ) ) {
        return false;
    }

    $flags    = gb_identity_get_flags( $user_id );
    $existing = isset( $flags[ $other_id ] ) ? $flags[ $other_id ] : array();
    $merged   = array_values( array_unique( array_merge( isset( $existing['signals'] ) ? (array) $existing['signals'] : array(), $signals ) ) );

    $flags[ $other_id ] = array(
        // Abuse outranks duplicate and is never downgraded.
        'kind'    => ( isset( $existing['kind'] ) && $existing['kind'] === 'abuse' ) ? 'abuse' : $kind,
        'signals' => $merged,
        'newer'   => isset( $existing['newer'] ) ? (bool) $existing['newer'] : (bool) $newer,
        'at'      => isset( $existing['at'] ) ? $existing['at'] : current_time( 'mysql' ),
        'deleted' => (bool) $deleted,
    );

    if ( $existing == $flags[ $other_id ] ) {
        return false;
    }

    update_user_meta( $user_id, '_gb_identity_flags', $flags );

    return true;
}

/**
 * Look for duplicates of a cleaner and flag both sides.
 *
 * The later account (higher user id) is the one marked as the possible
 * duplicate and loses the free trial; the earlier one is flagged too, so a
 * manager opening either record sees the other.
 */
function gb_identity_evaluate( $user_id ) {
    $user_id = (int) $user_id;

    if ( ! gb_identity_is_cleaner_user( $user_id ) ) {
        return array();
    }

    $found = gb_identity_find_duplicates( $user_id );

    foreach ( $found as $other => $signals ) {
        gb_identity_add_flag( $user_id, $other, 'duplicate', $signals, $user_id > $other );
        gb_identity_add_flag( $other, $user_id, 'duplicate', $signals, $other > $user_id );
    }

    /*
     * A free trial on an account that has since been deleted is still a
     * free trial. Those are only in the registry, so they are checked there.
     */
    $fps = gb_identity_fingerprints( $user_id );

    foreach ( gb_identity_registry_matches( $fps, gb_stripe_site_mode(), $user_id ) as $match ) {
        if ( ! get_userdata( $match['user'] ) ) {
            gb_identity_add_flag( $user_id, $match['user'], 'duplicate', $match['signals'], true, true );
            $found[ $match['user'] ] = $match['signals'];
        }
    }

    return $found;
}

/**
 * The flags that cost this account its free trial: any abuse flag, and any
 * duplicate flag where this is the later account.
 */
function gb_identity_trial_blocking_flags( $user_id ) {
    $out = array();

    foreach ( gb_identity_get_flags( $user_id ) as $other => $flag ) {
        if ( ( isset( $flag['kind'] ) && $flag['kind'] === 'abuse' ) || ! empty( $flag['newer'] ) ) {
            $out[ (int) $other ] = $flag;
        }
    }

    return $out;
}

/* --------------------------------------------------------------------------
   REGISTRATION AND PROFILE CHANGES
   -------------------------------------------------------------------------- */

/**
 * The account an email address already belongs to, comparing normalised
 * addresses. 0 when it is genuinely new.
 */
function gb_identity_email_belongs_to_account( $email ) {
    $normal = gb_identity_normalise_email( $email );

    if ( $normal === '' ) {
        return 0;
    }

    $exact = email_exists( $email );
    if ( $exact ) {
        return (int) $exact;
    }

    $ids = gb_identity_users_with( 'email', gb_identity_hash( 'email', $normal ) );

    return empty( $ids ) ? 0 : (int) $ids[0];
}

/**
 * Called once a cleaner account and its draft profile exist.
 *
 * The sign-up IP is recorded here because it is the only moment it is known.
 */
function gb_identity_on_cleaner_registered( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id < 1 ) {
        return array();
    }

    $ip = function_exists( 'gb_get_request_ip' ) ? gb_get_request_ip() : '';
    if ( $ip !== '' && $ip !== '0.0.0.0' ) {
        update_user_meta( $user_id, '_gb_fp_ip', gb_identity_hash( 'ip', $ip ) );
    }

    gb_identity_refresh_user( $user_id );

    return gb_identity_evaluate( $user_id );
}

/**
 * Keep the email hash current for every account, cleaner or not, so the
 * registration check can see customers and managers too.
 */
function gb_identity_on_user_saved( $user_id ) {
    $user = get_userdata( (int) $user_id );

    if ( ! $user ) {
        return;
    }

    $hash = gb_identity_hash( 'email', gb_identity_normalise_email( $user->user_email ) );

    if ( $hash !== '' ) {
        update_user_meta( $user->ID, '_gb_fp_email', $hash );
    }
}
add_action( 'user_register', 'gb_identity_on_user_saved', 20 );
add_action( 'profile_update', 'gb_identity_on_user_saved', 20 );

/**
 * Phone and service area arrive later, on Get Verified and Edit Profile (or
 * from a manager). Watching the meta rather than editing those screens means
 * every route that writes them is covered, including ones added later.
 */
function gb_identity_on_cleaner_meta( $meta_id, $post_id, $meta_key ) {
    static $done = array();

    if ( ! in_array( $meta_key, array( '_gb_cleaner_phone', 'cleaner_location', 'location', '_gb_location' ), true ) ) {
        return;
    }

    if ( get_post_type( $post_id ) !== 'gb_cleaner' ) {
        return;
    }

    $author = (int) get_post_field( 'post_author', $post_id );
    // gb_set_cleaner_location() writes three keys in a row; the first one is
    // enough, the rest would only repeat the same lookups.
    $marker = $author . '|' . $meta_key . '|' . md5( (string) get_post_meta( $post_id, $meta_key, true ) );

    if ( $author < 1 || isset( $done[ $marker ] ) || ! gb_identity_is_cleaner_user( $author ) ) {
        return;
    }

    $done[ $marker ] = true;

    gb_identity_refresh_user( $author );
    gb_identity_evaluate( $author );
}
add_action( 'added_post_meta', 'gb_identity_on_cleaner_meta', 20, 3 );
add_action( 'updated_post_meta', 'gb_identity_on_cleaner_meta', 20, 3 );

/* --------------------------------------------------------------------------
   THE FREE TRIAL REGISTRY
   -------------------------------------------------------------------------- */

/**
 * One option per Stripe mode, never autoloaded: it is only read at checkout,
 * on a webhook and in Manager OS, and has no business on every page load.
 */
function gb_identity_registry_key( $mode ) {
    return 'gb_trial_registry_' . ( $mode === 'live' ? 'live' : 'test' );
}

function gb_identity_registry( $mode ) {
    $stored = get_option( gb_identity_registry_key( $mode ), array() );

    return is_array( $stored ) ? $stored : array();
}

/**
 * Record a free trial. One entry per subscription, so a webhook arriving
 * twice replaces its own entry rather than adding a second.
 *
 * @param array $fps signal => hash
 */
function gb_identity_registry_add( $user_id, $mode, $subscription_id, array $fps, $note = '' ) {
    $registry = gb_identity_registry( $mode );
    $key      = $subscription_id !== '' ? 'sub_' . $subscription_id : 'user_' . (int) $user_id;
    $previous = isset( $registry[ $key ] ) ? $registry[ $key ] : array();

    $registry[ $key ] = array(
        'user'    => (int) $user_id,
        'at'      => isset( $previous['at'] ) ? $previous['at'] : current_time( 'mysql' ),
        'sub'     => (string) $subscription_id,
        // Keep anything learned earlier (a card seen on the first event).
        'fp'      => array_filter( array_merge( isset( $previous['fp'] ) ? (array) $previous['fp'] : array(), $fps ) ),
        'signals' => array_keys( array_filter( array_merge( isset( $previous['fp'] ) ? (array) $previous['fp'] : array(), $fps ) ) ),
        'note'    => $note !== '' ? $note : ( isset( $previous['note'] ) ? $previous['note'] : '' ),
    );

    update_option( gb_identity_registry_key( $mode ), $registry, false );
    update_user_meta( (int) $user_id, '_gb_cleaner_trial_used_' . ( $mode === 'live' ? 'live' : 'test' ), $registry[ $key ]['at'] );

    return $registry[ $key ];
}

/**
 * Registry entries that share a signal with these fingerprints.
 *
 * @return array list of array( 'user' => id, 'signals' => list, 'at' => date )
 */
function gb_identity_registry_matches( array $fps, $mode, $exclude_user = 0 ) {
    $out = array();

    foreach ( gb_identity_registry( $mode ) as $entry ) {
        $entry_user = isset( $entry['user'] ) ? (int) $entry['user'] : 0;

        if ( $exclude_user && $entry_user === (int) $exclude_user ) {
            continue;
        }

        $theirs  = isset( $entry['fp'] ) ? (array) $entry['fp'] : array();
        $signals = array();

        foreach ( array( 'email', 'phone', 'name_area', 'card' ) as $signal ) {
            if ( ! empty( $fps[ $signal ] ) && ! empty( $theirs[ $signal ] ) && hash_equals( (string) $theirs[ $signal ], (string) $fps[ $signal ] ) ) {
                $signals[] = $signal;
            }
        }

        if ( ! empty( $fps['name'] ) && ! empty( $fps['ip'] ) && ! empty( $theirs['name'] ) && ! empty( $theirs['ip'] )
            && hash_equals( (string) $theirs['name'], (string) $fps['name'] ) && hash_equals( (string) $theirs['ip'], (string) $fps['ip'] ) ) {
            $signals[] = 'name_ip';
        }

        if ( $signals ) {
            $out[] = array(
                'user'    => $entry_user,
                'signals' => $signals,
                'at'      => isset( $entry['at'] ) ? $entry['at'] : '',
            );
        }
    }

    return $out;
}

/**
 * Has this account already had a cleaner subscription in this mode, going by
 * what is on file here? Every cleaner checkout used to include the trial, so
 * having had a subscription at all means having had the free months.
 */
function gb_identity_user_had_cleaner_subscription( $user_id, $mode ) {
    $user_id = (int) $user_id;

    if ( (string) get_user_meta( $user_id, '_gb_cleaner_trial_used_' . $mode, true ) !== '' ) {
        return true;
    }

    $listing_mode = (string) get_user_meta( $user_id, '_gb_listing_mode', true ) === 'live' ? 'live' : 'test';

    return $listing_mode === $mode && (string) get_user_meta( $user_id, '_gb_listing_stripe_status', true ) !== '';
}

/**
 * Ask Stripe whether this customer has had a cleaner subscription before.
 * Only when we already have a customer id: a new customer has no history,
 * and creating one just to ask would be a wasted call.
 */
function gb_identity_stripe_had_cleaner_subscription( $user_id ) {
    if ( ! function_exists( 'gb_membership_customer_meta_key' ) || ! function_exists( 'gb_stripe_api_request' ) ) {
        return false;
    }

    $customer = (string) get_user_meta( (int) $user_id, gb_membership_customer_meta_key(), true );

    if ( $customer === '' ) {
        return false;
    }

    $list = gb_stripe_api_request( 'subscriptions', array(
        'customer' => $customer,
        'status'   => 'all',
        'limit'    => 20,
    ), 'GET' );

    if ( is_wp_error( $list ) || empty( $list['data'] ) || ! is_array( $list['data'] ) ) {
        return false;
    }

    $catalogue = gb_subscription_catalogue();

    foreach ( $list['data'] as $subscription ) {
        $plan = is_array( $subscription ) ? gb_membership_plan_key_from_subscription( $subscription ) : '';

        if ( $plan !== '' && isset( $catalogue[ $plan ] ) && $catalogue[ $plan ]['audience'] === 'cleaner' ) {
            return true;
        }
    }

    return false;
}

/**
 * May this account have the free months on its next cleaner checkout?
 *
 * @param bool $check_stripe also ask Stripe for the customer's history (one
 *                           API call; done at checkout, not on page views)
 * @return array{eligible: bool, reason: string}
 */
function gb_identity_trial_decision( $user_id, $check_stripe = false ) {
    $user_id = (int) $user_id;
    $mode    = gb_stripe_site_mode();

    if ( gb_identity_user_had_cleaner_subscription( $user_id, $mode ) ) {
        return array( 'eligible' => false, 'reason' => 'own_previous' );
    }

    // A manager has looked at the flags and decided this is somebody new.
    $override = (string) get_user_meta( $user_id, '_gb_trial_override', true ) === '1';

    if ( ! $override ) {
        if ( gb_identity_trial_blocking_flags( $user_id ) ) {
            return array( 'eligible' => false, 'reason' => 'duplicate' );
        }

        $fps = gb_identity_refresh_user( $user_id );

        if ( gb_identity_registry_matches( $fps, $mode, $user_id ) ) {
            return array( 'eligible' => false, 'reason' => 'registry' );
        }
    }

    if ( $check_stripe && gb_identity_stripe_had_cleaner_subscription( $user_id ) ) {
        return array( 'eligible' => false, 'reason' => 'stripe_history' );
    }

    return array( 'eligible' => true, 'reason' => $override ? 'override' : 'eligible' );
}

/**
 * The cleaner listing price, as text.
 */
function gb_identity_listing_price_text() {
    if ( function_exists( 'gb_subscription_plan_amount' ) && function_exists( 'gb_format_minor_amount' ) ) {
        $minor = gb_subscription_plan_amount( 'cleaner_monthly' );

        if ( ! is_wp_error( $minor ) ) {
            return html_entity_decode( wp_strip_all_tags( gb_format_minor_amount( $minor ) ), ENT_QUOTES, 'UTF-8' );
        }
    }

    return '';
}

/**
 * What a cleaner is told when the free months are not on offer. Deliberately
 * the same whichever signal matched: telling somebody "your phone number
 * matched" teaches them which detail to change next time.
 */
function gb_identity_trial_used_message() {
    $price = gb_identity_listing_price_text();

    return $price !== ''
        ? 'Your free three months have already been used, so your listing starts at ' . $price . ' a month.'
        : 'Your free three months have already been used, so your listing is charged from the start.';
}

/**
 * The note on the Become a Cleaner panel, for a logged-in cleaner who would
 * not get the trial. Uses only what is on file (no Stripe call on a page
 * view); the checkout itself asks Stripe as well.
 */
function gb_identity_trial_panel_notice() {
    // The page is usually picked by its slug rather than an assigned
    // template, so both are checked.
    if ( ! is_user_logged_in() || ! ( is_page( 'join' ) || is_page_template( 'page-join.php' ) ) ) {
        return '';
    }

    $user_id = get_current_user_id();

    if ( ! gb_identity_is_cleaner_user( $user_id ) || ! function_exists( 'gb_membership_checkout_visible' ) || ! gb_membership_checkout_visible( 'cleaner_monthly' ) ) {
        return '';
    }

    if ( function_exists( 'gb_membership_is_active_for' ) && gb_membership_is_active_for( $user_id, 'cleaner' ) ) {
        return '';
    }

    $decision = gb_identity_trial_decision( $user_id, false );

    if ( $decision['eligible'] ) {
        return '';
    }

    return '<div class="gb-membership-notice gb-membership-notice-info">' . esc_html( gb_identity_trial_used_message() ) . '</div>';
}

/* --------------------------------------------------------------------------
   AFTER CHECKOUT: THE CARD
   -------------------------------------------------------------------------- */

/**
 * The card fingerprint behind a subscription, or ''.
 *
 * Stripe's fingerprint is the same for the same card number on every
 * customer in the account, which is what makes it useful: a new email and a
 * new name do not change the card. It is looked for in order of how directly
 * it belongs to this subscription.
 */
function gb_identity_card_fingerprint( array $subscription, array $session = array() ) {
    $pm_ids = array();

    $pick = static function ( $value ) {
        if ( is_array( $value ) ) {
            return isset( $value['id'] ) ? (string) $value['id'] : '';
        }

        return (string) $value;
    };

    if ( ! empty( $subscription['default_payment_method'] ) ) {
        // Already expanded: the fingerprint is right there.
        if ( is_array( $subscription['default_payment_method'] ) && ! empty( $subscription['default_payment_method']['card']['fingerprint'] ) ) {
            return (string) $subscription['default_payment_method']['card']['fingerprint'];
        }

        $pm_ids[] = $pick( $subscription['default_payment_method'] );
    }

    // A trial checkout collects the card through a SetupIntent.
    foreach ( array( isset( $session['setup_intent'] ) ? $session['setup_intent'] : '', isset( $subscription['pending_setup_intent'] ) ? $subscription['pending_setup_intent'] : '' ) as $intent ) {
        $intent_id = $pick( $intent );

        if ( $intent_id === '' ) {
            continue;
        }

        $setup = gb_stripe_api_request( 'setup_intents/' . rawurlencode( $intent_id ), array(), 'GET' );

        if ( ! is_wp_error( $setup ) && ! empty( $setup['payment_method'] ) ) {
            $pm_ids[] = $pick( $setup['payment_method'] );
        }
    }

    $customer = isset( $subscription['customer'] ) ? $pick( $subscription['customer'] ) : '';

    if ( $customer !== '' && empty( $pm_ids ) ) {
        $cust = gb_stripe_api_request( 'customers/' . rawurlencode( $customer ), array(), 'GET' );

        if ( ! is_wp_error( $cust ) && ! empty( $cust['invoice_settings']['default_payment_method'] ) ) {
            $pm_ids[] = $pick( $cust['invoice_settings']['default_payment_method'] );
        }
    }

    foreach ( array_unique( array_filter( $pm_ids ) ) as $pm_id ) {
        $pm = gb_stripe_api_request( 'payment_methods/' . rawurlencode( $pm_id ), array(), 'GET' );

        if ( ! is_wp_error( $pm ) && ! empty( $pm['card']['fingerprint'] ) ) {
            return (string) $pm['card']['fingerprint'];
        }
    }

    // Last resort: whatever card the customer has on file.
    if ( $customer !== '' ) {
        $list = gb_stripe_api_request( 'payment_methods', array( 'customer' => $customer, 'type' => 'card', 'limit' => 1 ), 'GET' );

        if ( ! is_wp_error( $list ) && ! empty( $list['data'][0]['card']['fingerprint'] ) ) {
            return (string) $list['data'][0]['card']['fingerprint'];
        }
    }

    return '';
}

/**
 * Run once per cleaner subscription, from the webhook (via
 * gb_membership_apply_subscription) and again with the checkout session when
 * that arrives.
 *
 * Idempotent: Stripe retries webhooks and sends several events for one
 * checkout, so each subscription is marked as checked and later calls return
 * straight away. A trialing subscription whose card is not visible yet is
 * left unmarked so the next event can try again, up to a limit.
 *
 * @return string what happened, for tests and the log
 */
function gb_identity_check_cleaner_subscription( array $subscription, array $session = array() ) {
    if ( empty( $subscription['id'] ) || ! function_exists( 'gb_membership_plan_key_from_subscription' ) ) {
        return 'skipped';
    }

    $catalogue = gb_subscription_catalogue();
    $plan_key  = gb_membership_plan_key_from_subscription( $subscription );

    if ( $plan_key === '' || ! isset( $catalogue[ $plan_key ] ) || $catalogue[ $plan_key ]['audience'] !== 'cleaner' ) {
        return 'not_cleaner';
    }

    $user_id = gb_membership_user_from_subscription( $subscription );

    if ( $user_id < 1 ) {
        return 'no_user';
    }

    $sub_id  = sanitize_text_field( (string) $subscription['id'] );
    $mode    = isset( $subscription['livemode'] ) ? ( $subscription['livemode'] ? 'live' : 'test' ) : gb_stripe_site_mode();
    $checked = get_user_meta( $user_id, '_gb_identity_checked_subs', true );
    $checked = is_array( $checked ) ? $checked : array();

    if ( ! empty( $checked[ $sub_id ]['done'] ) ) {
        return 'already_checked';
    }

    $attempts  = isset( $checked[ $sub_id ]['attempts'] ) ? (int) $checked[ $sub_id ]['attempts'] + 1 : 1;
    $status    = isset( $subscription['status'] ) ? (string) $subscription['status'] : '';
    $trialing  = $status === 'trialing' && ! empty( $subscription['trial_end'] ) && (int) $subscription['trial_end'] > time();
    $had_trial = ! empty( $subscription['trial_start'] ) || ! empty( $subscription['trial_end'] );

    /*
     * Look the card up only where it can still matter: a running trial, or a
     * subscription from the last week. "Re-check everything with Stripe" in
     * Members passes every old subscription through here once, and three or
     * four card lookups for each of them would make that button crawl for
     * nothing - an old trial is over whichever card paid for it.
     */
    $recent = ! empty( $subscription['created'] ) && (int) $subscription['created'] > time() - WEEK_IN_SECONDS;
    $card   = ( $trialing || $recent ) ? gb_identity_card_fingerprint( $subscription, $session ) : '';
    $fps    = gb_identity_refresh_user( $user_id );

    if ( $card !== '' ) {
        $fps['card'] = gb_identity_hash( 'card', $card );
        update_user_meta( $user_id, '_gb_fp_card', $fps['card'] );
    }

    $result = 'recorded';

    if ( $card !== '' ) {
        $others = array();

        foreach ( gb_identity_registry_matches( array( 'card' => $fps['card'] ), $mode, $user_id ) as $match ) {
            $others[] = (int) $match['user'];
        }

        $others = array_values( array_unique( array_filter( $others ) ) );

        // Dismissed pairs are people a manager has already said are different.
        $others = array_values( array_diff( $others, gb_identity_dismissed( $user_id ) ) );

        // Paying from day one with a card seen before is not abuse: only a
        // subscription that started on the free months is treated as one.
        if ( $others && $had_trial ) {
            // Marked first: ending the trial makes Stripe send another event,
            // and the re-apply below calls straight back into this function.
            $checked[ $sub_id ] = array( 'done' => true, 'at' => current_time( 'mysql' ), 'result' => 'abuse', 'attempts' => $attempts );
            update_user_meta( $user_id, '_gb_identity_checked_subs', $checked );

            foreach ( $others as $other ) {
                $exists = (bool) get_userdata( $other );
                gb_identity_add_flag( $user_id, $other, 'abuse', array( 'card' ), true, ! $exists );
                if ( $exists ) {
                    gb_identity_add_flag( $other, $user_id, 'abuse', array( 'card' ), false );
                }
            }

            $ended = false;

            // A manager who has already said "let this one have the trial"
            // gets the flag and the email, but the trial is left alone.
            $allowed = (string) get_user_meta( $user_id, '_gb_trial_override', true ) === '1';

            if ( $trialing && ! $allowed ) {
                $updated = gb_stripe_api_request( 'subscriptions/' . rawurlencode( $sub_id ), array( 'trial_end' => 'now' ) );
                $ended   = ! is_wp_error( $updated );

                if ( $ended && is_array( $updated ) && ! empty( $updated['id'] ) && function_exists( 'gb_membership_apply_subscription' ) ) {
                    gb_membership_apply_subscription( $updated );
                }
            }

            update_user_meta( $user_id, '_gb_trial_ended_for_abuse', $ended ? current_time( 'mysql' ) : '' );

            // Still recorded: their own details join the registry, so the
            // next attempt is caught by email or phone before it reaches a card.
            gb_identity_registry_add( $user_id, $mode, $sub_id, $fps, 'card already used for a free trial' );

            gb_identity_alert_support( $user_id, $others, $sub_id, $trialing && ! $allowed, $ended );

            return $ended ? 'abuse_trial_ended' : 'abuse_flagged';
        }
    }

    if ( $had_trial ) {
        gb_identity_registry_add( $user_id, $mode, $sub_id, $fps );
    }

    // Not seen the card yet on a running trial: try again on the next event,
    // but give up after a handful so a card-less test does not retry forever.
    $done = ! ( $trialing && $card === '' && $attempts < 5 );

    $checked[ $sub_id ] = array( 'done' => $done, 'at' => current_time( 'mysql' ), 'result' => $card === '' ? 'no_card' : $result, 'attempts' => $attempts );
    update_user_meta( $user_id, '_gb_identity_checked_subs', $checked );

    return $card === '' ? 'no_card' : $result;
}

/**
 * The checkout session arrives with the SetupIntent that collected the card,
 * which the subscription alone may not show yet.
 */
function gb_identity_check_after_checkout( array $session ) {
    if ( empty( $session['subscription'] ) || ( isset( $session['mode'] ) && $session['mode'] !== 'subscription' ) ) {
        return 'skipped';
    }

    $sub_id = is_array( $session['subscription'] ) ? ( isset( $session['subscription']['id'] ) ? $session['subscription']['id'] : '' ) : (string) $session['subscription'];

    if ( $sub_id === '' ) {
        return 'skipped';
    }

    // Cheap exit before any API call when this subscription is done.
    $user_id = ! empty( $session['metadata']['gb_user_id'] ) ? (int) $session['metadata']['gb_user_id'] : (int) ( isset( $session['client_reference_id'] ) ? $session['client_reference_id'] : 0 );
    if ( $user_id > 0 ) {
        $checked = get_user_meta( $user_id, '_gb_identity_checked_subs', true );
        if ( is_array( $checked ) && ! empty( $checked[ $sub_id ]['done'] ) ) {
            return 'already_checked';
        }
    }

    $subscription = gb_stripe_api_request( 'subscriptions/' . rawurlencode( $sub_id ), array(), 'GET' );

    if ( is_wp_error( $subscription ) || ! is_array( $subscription ) ) {
        return 'fetch_failed';
    }

    return gb_identity_check_cleaner_subscription( $subscription, $session );
}

/**
 * Tell support, once per subscription.
 */
function gb_identity_alert_support( $user_id, array $others, $subscription_id, $was_trialing, $ended ) {
    $sent_for = (string) get_user_meta( $user_id, '_gb_identity_alert_sent', true );

    if ( $sent_for === $subscription_id || ! function_exists( 'gb_get_support_email' ) ) {
        return false;
    }

    $user  = get_userdata( $user_id );
    $lines = array(
        'A cleaner has started a listing subscription with a card that has already had a free trial on another account.',
        '',
        'New account: ' . ( $user ? $user->display_name . ' (' . $user->user_email . ')' : '#' . $user_id ),
        'Manager OS: ' . gb_identity_manager_link_for_user( $user_id ),
        'Stripe subscription: ' . $subscription_id . ' (' . gb_stripe_site_mode() . ' mode)',
        '',
        'Card previously used by:',
    );

    foreach ( $others as $other ) {
        $o       = get_userdata( $other );
        $lines[] = $o
            ? '- ' . $o->display_name . ' (' . $o->user_email . '): ' . gb_identity_manager_link_for_user( $other )
            : '- account #' . $other . ' (since deleted)';
    }

    $lines[] = '';
    if ( $ended ) {
        $lines[] = 'The free trial on the new subscription has been ended, so Stripe is charging the listing now.';
    } elseif ( $was_trialing ) {
        $lines[] = 'The site tried to end the free trial but Stripe refused. Please end it from the Stripe dashboard.';
    } else {
        $lines[] = 'The subscription was not on a free trial, so nothing was changed in Stripe.';
    }
    $lines[] = 'Both accounts are flagged in Manager OS under Cleaners. If they really are different people, clear the flag there.';

    $subject = 'Free trial reused: card already used by another cleaner';
    $to      = gb_get_support_email();

    $sent = function_exists( 'gb_send_brevo_template_email' )
        ? gb_send_brevo_template_email( $to, $subject, implode( "\n", $lines ) )
        : wp_mail( $to, $subject, implode( "\n", $lines ) );

    // Queued for retry by the outbox counts as sent, as for the trial reminder.
    $queued = ! $sent && function_exists( 'gb_outbox_last_status' ) && gb_outbox_last_status() === 'retrying';

    if ( $sent || $queued ) {
        update_user_meta( $user_id, '_gb_identity_alert_sent', $subscription_id );
    }

    return $sent || $queued;
}

function gb_identity_manager_link_for_user( $user_id ) {
    $cleaner = gb_identity_cleaner_post_for_user( $user_id );

    return $cleaner
        ? add_query_arg( array( 'tab' => 'cleaners', 'action' => 'edit', 'id' => $cleaner ), home_url( '/manager-dashboard/' ) )
        : add_query_arg( array( 'tab' => 'members' ), home_url( '/manager-dashboard/' ) );
}

/* --------------------------------------------------------------------------
   THE LISTING GATE
   -------------------------------------------------------------------------- */

/**
 * "Require a paid cleaner listing to appear and take bookings". Off by
 * default, like the customer membership gate: turning it on before cleaners
 * can buy a live listing would empty the directory.
 */
function gb_listing_gate_enabled() {
    $settings_id = function_exists( 'gb_get_site_settings_id' ) ? (int) gb_get_site_settings_id() : (int) get_option( 'page_on_front' );

    return $settings_id > 0 && (string) get_post_meta( $settings_id, '_gb_listing_gate', true ) === '1';
}

/**
 * Is this user's own listing subscription running in the site's current
 * Stripe mode? A test-mode subscription does not count once the site is
 * live, which is the hole the old _gb_subscription_status had.
 */
function gb_identity_user_listing_paid( $user_id ) {
    $user_id = (int) $user_id;
    $mode    = (string) get_user_meta( $user_id, '_gb_listing_mode', true ) === 'live' ? 'live' : 'test';

    return (string) get_user_meta( $user_id, '_gb_listing_status', true ) === 'active' && $mode === gb_stripe_site_mode();
}

/**
 * The full answer for one cleaner profile: their author's subscription, or
 * a free listing a manager has granted, or a profile the team itself owns.
 */
function gb_cleaner_listing_is_paid( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $author     = (int) get_post_field( 'post_author', $cleaner_id );

    $paid = (string) get_post_meta( $cleaner_id, '_gb_listing_comp', true ) === '1'
        || ( $author > 0 && gb_identity_is_staff_user( $author ) )
        || ( $author > 0 && gb_identity_user_listing_paid( $author ) );

    /**
     * Filters whether a cleaner counts as having a paid listing.
     *
     * @param bool $paid
     * @param int  $cleaner_id
     */
    return (bool) apply_filters( 'gb_cleaner_listing_is_paid', $paid, $cleaner_id );
}

/**
 * How a cleaner's listing looks right now, for Manager OS.
 *
 * @return array{paid: bool, label: string, tone: string, detail: string}
 */
function gb_identity_listing_state( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $author     = (int) get_post_field( 'post_author', $cleaner_id );
    $site_mode  = gb_stripe_site_mode();
    $mode_word  = $site_mode === 'live' ? 'live' : 'test';

    if ( (string) get_post_meta( $cleaner_id, '_gb_listing_comp', true ) === '1' ) {
        return array( 'paid' => true, 'label' => 'Free listing', 'tone' => 'active', 'detail' => 'Granted by the team, so no subscription is needed.' );
    }

    if ( $author > 0 && gb_identity_is_staff_user( $author ) ) {
        return array( 'paid' => true, 'label' => 'Team profile', 'tone' => 'active', 'detail' => 'Created by a manager, so it is not tied to a subscription.' );
    }

    $status = (string) get_user_meta( $author, '_gb_listing_status', true );
    $stripe = (string) get_user_meta( $author, '_gb_listing_stripe_status', true );
    $mode   = (string) get_user_meta( $author, '_gb_listing_mode', true ) === 'live' ? 'live' : 'test';
    $renews = (string) get_user_meta( $author, '_gb_listing_renews', true );
    $cancel = (string) get_user_meta( $author, '_gb_listing_cancels_at', true );

    if ( $status === '' ) {
        return array( 'paid' => false, 'label' => 'No subscription', 'tone' => 'none', 'detail' => 'Has never started a listing subscription.' );
    }

    if ( $mode !== $site_mode ) {
        return array(
            'paid'   => false,
            'label'  => 'Test only',
            'tone'   => 'none',
            'detail' => 'Their subscription was made in ' . $mode . ' mode, which does not count while the site is in ' . $mode_word . ' mode.',
        );
    }

    if ( $status !== 'active' ) {
        return array( 'paid' => false, 'label' => 'Cancelled', 'tone' => 'bad', 'detail' => 'Their listing subscription has ended.' );
    }

    $when = static function ( $date ) {
        return $date !== '' ? date_i18n( 'j M Y', strtotime( $date ) ) : '';
    };

    if ( $stripe === 'trialing' ) {
        $label  = 'Free trial';
        $detail = $renews !== '' ? 'First payment due ' . $when( $renews ) . '.' : 'On the free three months.';
    } elseif ( $stripe === 'past_due' ) {
        $label  = 'Payment overdue';
        $detail = 'Stripe is retrying the card. The listing stays up until Stripe gives up.';
    } else {
        $label  = 'Paid';
        $detail = $renews !== '' ? 'Renews ' . $when( $renews ) . '.' : '';
    }

    if ( $cancel !== '' ) {
        $detail = trim( $detail . ' Set to stop on ' . $when( $cancel ) . '.' );
    }

    return array( 'paid' => true, 'label' => $label, 'tone' => $stripe === 'trialing' ? 'trial' : 'active', 'detail' => $detail );
}

/**
 * Authors whose profiles may appear while the gate is on. Worked out once per
 * request: pre_get_posts runs for every cleaner query on the page.
 */
function gb_listing_gate_allowed_author_ids() {
    static $ids = null;

    if ( $ids !== null ) {
        return $ids;
    }

    $ids = array();

    foreach ( get_users( array( 'meta_key' => '_gb_listing_status', 'meta_value' => 'active', 'fields' => 'ID', 'number' => -1 ) ) as $uid ) {
        if ( gb_identity_user_listing_paid( $uid ) ) {
            $ids[] = (int) $uid;
        }
    }

    foreach ( get_users( array( 'role__in' => array( 'administrator', 'gb_manager' ), 'fields' => 'ID', 'number' => -1 ) ) as $uid ) {
        $ids[] = (int) $uid;
    }

    global $wpdb;
    $comped = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT p.post_author FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE p.post_type = %s AND m.meta_key = %s AND m.meta_value = %s",
        'gb_cleaner',
        '_gb_listing_comp',
        '1'
    ) );

    foreach ( (array) $comped as $uid ) {
        $ids[] = (int) $uid;
    }

    $ids = array_values( array_unique( array_filter( $ids ) ) );

    return $ids;
}

/**
 * Hide unpaid cleaners from every public list.
 *
 * Applies to front-end queries for published cleaners: the Find a Cleaner
 * search (its own WP_Query asks for post_status 'publish'), the homepage
 * cleaners, and the review and rating roll-ups. It leaves alone:
 *   - staff, who always see everyone;
 *   - queries for several statuses, which are the dashboards and
 *     maintenance jobs, never a public list;
 *   - queries for one author, which is a cleaner looking at their own;
 *   - the single profile's main query, which gets a proper notice instead
 *     (see gb_listing_gate_single_profile()).
 * A query can opt out with 'gb_skip_listing_gate' => true.
 */
function gb_listing_gate_filter_query( $query ) {
    // Visitors only: scheduled jobs and WP-CLI have no visitor and must see
    // every cleaner to do their work.
    if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! gb_listing_gate_enabled() ) {
        return;
    }

    if ( $query->get( 'gb_skip_listing_gate' ) ) {
        return;
    }

    $type = $query->get( 'post_type' );
    if ( ! ( $type === 'gb_cleaner' || ( is_array( $type ) && array_values( $type ) === array( 'gb_cleaner' ) ) ) ) {
        return;
    }

    $status = $query->get( 'post_status' );
    if ( ! ( $status === '' || $status === 'publish' || ( is_array( $status ) && array_values( $status ) === array( 'publish' ) ) ) ) {
        return;
    }

    if ( $query->get( 'author' ) || $query->get( 'author_name' ) ) {
        return;
    }

    if ( $query->is_main_query() && $query->is_singular() ) {
        return;
    }

    if ( function_exists( 'gb_membership_user_is_staff' ) && gb_membership_user_is_staff() ) {
        return;
    }

    $allowed  = gb_listing_gate_allowed_author_ids();
    $existing = (array) $query->get( 'author__in' );
    $existing = array_filter( array_map( 'intval', $existing ) );

    if ( $existing ) {
        $allowed = array_values( array_intersect( $allowed, $existing ) );
    }

    // author__in with nothing in it means "no restriction", so an empty list
    // has to be a list that matches nobody.
    $query->set( 'author__in', $allowed ? $allowed : array( 0 ) );
}
add_action( 'pre_get_posts', 'gb_listing_gate_filter_query', 20 );

/**
 * A cleaner's own profile page, reached by a saved link or a search engine.
 *
 * Staff and the cleaner themselves still see it. Everybody else gets a plain
 * page saying the cleaner is not taking bookings, with a way to find one who
 * is, and a 404 status so search engines drop it.
 */
function gb_listing_gate_single_profile() {
    if ( ! is_singular( 'gb_cleaner' ) || ! gb_listing_gate_enabled() ) {
        return;
    }

    $cleaner_id = (int) get_queried_object_id();

    if ( $cleaner_id < 1 || gb_cleaner_listing_is_paid( $cleaner_id ) ) {
        return;
    }

    if ( ( function_exists( 'gb_membership_user_is_staff' ) && gb_membership_user_is_staff() )
        || ( is_user_logged_in() && (int) get_post_field( 'post_author', $cleaner_id ) === get_current_user_id() ) ) {
        return;
    }

    status_header( 404 );
    nocache_headers();

    $listings = get_page_by_path( 'cleaner-listings' );
    $find_url = $listings ? get_permalink( $listings->ID ) : home_url( '/cleaner-listings/' );

    get_header();
    ?>
    <main id="main-content" class="gb-auth-wrapper">
        <div class="gb-card gb-auth-card" style="text-align:center;">
            <h1>Not taking bookings right now</h1>
            <p class="text-muted" style="margin:12px 0 22px; line-height:1.6;">
                This cleaner is not currently listed on The Green Broom, so they cannot be booked here.
                There are other verified cleaners in your area.
            </p>
            <a class="gb-btn gb-btn-primary" href="<?php echo esc_url( $find_url ); ?>">Find a cleaner</a>
        </div>
    </main>
    <?php
    get_footer();
    exit;
}
add_action( 'template_redirect', 'gb_listing_gate_single_profile', 5 );

/**
 * For Settings: how many live profiles would be shown with the gate on.
 *
 * @return array{live: int, shown: int, can_buy: bool}
 */
function gb_listing_gate_summary() {
    $live = get_posts( array(
        'post_type'            => 'gb_cleaner',
        'post_status'          => 'publish',
        'posts_per_page'       => -1,
        'fields'               => 'ids',
        'gb_skip_listing_gate' => true,
    ) );

    $shown = 0;
    foreach ( $live as $cleaner_id ) {
        if ( gb_cleaner_listing_is_paid( $cleaner_id ) ) {
            $shown++;
        }
    }

    return array(
        'live'    => count( $live ),
        'shown'   => $shown,
        'can_buy' => function_exists( 'gb_membership_checkout_available' ) && gb_membership_checkout_available( 'cleaner_monthly' ),
    );
}

/* --------------------------------------------------------------------------
   MANAGER OS
   -------------------------------------------------------------------------- */

function gb_identity_manager_action_url( $action, $user_id, $other = 0, $cleaner_id = 0 ) {
    $args = array(
        'gb_identity_action' => $action,
        'user'               => (int) $user_id,
        'other'              => (int) $other,
        'cleaner'            => (int) $cleaner_id,
    );

    return wp_nonce_url( add_query_arg( $args, home_url( '/manager-dashboard/' ) ), 'gb_identity_' . $action . '_' . (int) $user_id . '_' . (int) $other );
}

/**
 * The buttons on the cleaner record. A link rather than a form because the
 * whole record is already one form, and forms cannot be nested.
 *
 *   dismiss      these two are different people: clear the flag on both and
 *                never raise it for this pair again
 *   allow_trial  let this account have the free months despite its flags
 *   undo_trial   take that back
 */
function gb_identity_handle_manager_action() {
    if ( empty( $_GET['gb_identity_action'] ) ) {
        return;
    }

    $action = sanitize_key( wp_unslash( $_GET['gb_identity_action'] ) );
    $user   = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
    $other  = isset( $_GET['other'] ) ? absint( $_GET['other'] ) : 0;
    $back   = isset( $_GET['cleaner'] ) ? absint( $_GET['cleaner'] ) : 0;

    if ( ! in_array( $action, array( 'dismiss', 'allow_trial', 'undo_trial' ), true ) ) {
        return;
    }

    if ( ! function_exists( 'gb_membership_user_is_staff' ) || ! gb_membership_user_is_staff() ) {
        wp_die( 'Only managers can do that.' );
    }

    if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gb_identity_' . $action . '_' . $user . '_' . $other ) ) {
        wp_die( 'That link has expired. Go back and try again.' );
    }

    if ( $action === 'dismiss' && $user && $other ) {
        foreach ( array( array( $user, $other ), array( $other, $user ) ) as $pair ) {
            list( $a, $b ) = $pair;
            $flags = gb_identity_get_flags( $a );
            unset( $flags[ $b ] );
            update_user_meta( $a, '_gb_identity_flags', $flags );

            $dismissed   = gb_identity_dismissed( $a );
            $dismissed[] = $b;
            update_user_meta( $a, '_gb_identity_dismissed', array_values( array_unique( $dismissed ) ) );
        }
    } elseif ( $action === 'allow_trial' && $user ) {
        update_user_meta( $user, '_gb_trial_override', '1' );
    } elseif ( $action === 'undo_trial' && $user ) {
        delete_user_meta( $user, '_gb_trial_override' );
    }

    $target = $back
        ? add_query_arg( array( 'tab' => 'cleaners', 'action' => 'edit', 'id' => $back, 'identity' => $action ), home_url( '/manager-dashboard/' ) )
        : add_query_arg( array( 'tab' => 'cleaners' ), home_url( '/manager-dashboard/' ) );

    wp_safe_redirect( $target );
    exit;
}
add_action( 'init', 'gb_identity_handle_manager_action', 20 );

/**
 * The name of the other account in a flag, linked to its record when it has
 * one.
 */
function gb_identity_other_account_html( $other_id, $deleted = false ) {
    $other = get_userdata( (int) $other_id );

    if ( ! $other || $deleted ) {
        return esc_html( 'Account #' . (int) $other_id . ' (since deleted)' );
    }

    $cleaner = gb_identity_cleaner_post_for_user( $other->ID );
    $name    = $cleaner ? get_the_title( $cleaner ) : $other->display_name;
    $name    = $name !== '' ? $name : $other->user_email;

    if ( ! $cleaner ) {
        return esc_html( $name );
    }

    return '<a href="' . esc_url( add_query_arg( array( 'tab' => 'cleaners', 'action' => 'edit', 'id' => $cleaner ), home_url( '/manager-dashboard/' ) ) ) . '">' . esc_html( $name ) . '</a>';
}

/**
 * Small chips under a cleaner's name in the Cleaners list.
 */
function gb_identity_render_list_badges( $cleaner_id ) {
    $author = (int) get_post_field( 'post_author', (int) $cleaner_id );

    if ( $author < 1 || gb_identity_is_staff_user( $author ) ) {
        return;
    }

    $flags = gb_identity_get_flags( $author );
    $mode  = gb_stripe_site_mode();

    foreach ( $flags as $other => $flag ) {
        $abuse = isset( $flag['kind'] ) && $flag['kind'] === 'abuse';
        $text  = $abuse ? 'Free trial reused' : ( ! empty( $flag['newer'] ) ? 'Possible duplicate' : 'Has a possible duplicate' );
        $title = $text . ' of ' . wp_strip_all_tags( gb_identity_other_account_html( $other, ! empty( $flag['deleted'] ) ) ) . ': ' . gb_identity_signal_list( (array) $flag['signals'] );
        ?>
        <div style="font-size:10.5px; margin-top:3px; font-weight:700; color:<?php echo $abuse ? '#b91c1c' : '#b45309'; ?>;" title="<?php echo esc_attr( $title ); ?>">
            <?php echo esc_html( $text ); ?>: <?php echo gb_identity_other_account_html( $other, ! empty( $flag['deleted'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
            <span style="font-weight:400;">(<?php echo esc_html( gb_identity_signal_list( (array) $flag['signals'] ) ); ?>)</span>
        </div>
        <?php
    }

    if ( gb_identity_user_had_cleaner_subscription( $author, $mode ) ) {
        echo '<div style="font-size:10.5px; color:#64748b; margin-top:2px;">Free trial used</div>';
    }
}

/**
 * The status pill for the Subscription column.
 */
function gb_identity_render_listing_pill( $cleaner_id ) {
    $state = gb_identity_listing_state( $cleaner_id );
    $class = $state['tone'] === 'active' ? 'pill-active' : 'pill-trial';
    $style = $state['tone'] === 'none' ? 'background:#f1f5f9; color:#64748b;' : ( $state['tone'] === 'bad' ? 'background:#fef2f2; color:#991b1b;' : '' );
    ?>
    <span class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $style ); ?>" title="<?php echo esc_attr( $state['detail'] ); ?>"><?php echo esc_html( $state['label'] ); ?></span>
    <?php if ( gb_listing_gate_enabled() && ! $state['paid'] ) : ?>
        <div style="font-size:10px; color:#b91c1c; margin-top:4px;">Hidden from customers</div>
    <?php endif; ?>
    <?php
}

/**
 * The card on the cleaner record: listing per mode, free trial, and flags.
 */
function gb_identity_render_manager_panel( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;

    if ( $cleaner_id < 1 ) {
        return;
    }

    $author    = (int) get_post_field( 'post_author', $cleaner_id );
    $state     = gb_identity_listing_state( $cleaner_id );
    $mode      = gb_stripe_site_mode();
    $gate      = gb_listing_gate_enabled();
    $is_team   = $author > 0 && gb_identity_is_staff_user( $author );
    $flags     = $is_team ? array() : gb_identity_get_flags( $author );
    $override  = (string) get_user_meta( $author, '_gb_trial_override', true ) === '1';
    $trial_on  = gb_identity_user_had_cleaner_subscription( $author, $mode );
    $decision  = ( $is_team || $author < 1 ) ? array( 'eligible' => false, 'reason' => 'team' ) : gb_identity_trial_decision( $author, false );
    $comp      = (string) get_post_meta( $cleaner_id, '_gb_listing_comp', true ) === '1';
    $notice    = isset( $_GET['identity'] ) ? sanitize_key( wp_unslash( $_GET['identity'] ) ) : '';
    $notices   = array(
        'dismiss'     => 'Flag cleared. These two accounts will not be matched again.',
        'allow_trial' => 'This cleaner can now have the free three months on their next checkout.',
        'undo_trial'  => 'Free trial permission taken back.',
    );
    ?>
    <div class="gb-os-card" style="border-top:3px solid <?php echo $flags ? '#f59e0b' : '#10b981'; ?>;">
        <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Listing &amp; identity checks</div></div></div>
        <div class="gb-os-body" style="font-size:12.5px; color:#475569; line-height:1.55;">
            <?php if ( isset( $notices[ $notice ] ) ) : ?>
                <div style="margin-bottom:12px; padding:8px 10px; background:#f0fdf4; border:1px solid #dcfce7; border-radius:6px; color:#166534;"><?php echo esc_html( $notices[ $notice ] ); ?></div>
            <?php endif; ?>

            <label class="gb-os-label">Listing subscription (<?php echo esc_html( $mode === 'live' ? 'live' : 'test' ); ?> mode)</label>
            <div style="font-weight:700; color:#0f172a;"><?php echo esc_html( $state['label'] ); ?></div>
            <?php if ( $state['detail'] !== '' ) : ?>
                <div style="font-size:11.5px; color:#64748b;"><?php echo esc_html( $state['detail'] ); ?></div>
            <?php endif; ?>
            <div style="font-size:11.5px; margin-top:4px; color:<?php echo $state['paid'] ? '#166534' : ( $gate ? '#b91c1c' : '#64748b' ); ?>;">
                <?php
                if ( $state['paid'] ) {
                    echo esc_html( 'Shown to customers and can be booked.' );
                } elseif ( $gate ) {
                    echo esc_html( 'Hidden from customers and cannot be booked, because a paid listing is required.' );
                } else {
                    echo esc_html( 'Still shown, because a paid listing is not required yet (Settings, Customer Membership).' );
                }
                ?>
            </div>

            <label style="display:flex; gap:9px; align-items:flex-start; margin:12px 0 0; cursor:pointer;">
                <input type="hidden" name="_gb_listing_comp" value="0">
                <input type="checkbox" name="_gb_listing_comp" value="1" <?php checked( $comp ); ?> style="margin-top:3px;">
                <span><strong style="color:#0f172a;">Free listing</strong><br><span style="font-size:11.5px; color:#64748b;">Show and allow bookings without a subscription. Saved with the rest of this record.</span></span>
            </label>

            <?php if ( ! $is_team && $author > 0 ) : ?>
                <label class="gb-os-label" style="margin-top:16px;">Free three months</label>
                <?php if ( $trial_on ) : ?>
                    <div><strong style="color:#0f172a;">Used</strong> on this account.</div>
                <?php elseif ( $decision['eligible'] ) : ?>
                    <div><strong style="color:#166534;">Available</strong><?php echo $override ? esc_html( ' (allowed by a manager)' ) : ''; ?>.</div>
                <?php else : ?>
                    <div><strong style="color:#b45309;">Not available</strong>: <?php echo esc_html( $decision['reason'] === 'registry' ? 'their details match somebody who has already had it.' : 'this looks like a second account.' ); ?></div>
                <?php endif; ?>
                <?php if ( ! $trial_on && ( $flags || $override ) ) : ?>
                    <a class="gb-btn-small-os" style="margin-top:8px; justify-content:center;" href="<?php echo esc_url( gb_identity_manager_action_url( $override ? 'undo_trial' : 'allow_trial', $author, 0, $cleaner_id ) ); ?>"><?php echo $override ? 'Take back the free trial' : 'Allow the free trial anyway'; ?></a>
                <?php endif; ?>

                <label class="gb-os-label" style="margin-top:16px;">Duplicate checks</label>
                <?php if ( ! $flags ) : ?>
                    <div>No other cleaner account shares this person&rsquo;s email, phone, name and area, or card.</div>
                <?php else : ?>
                    <?php foreach ( $flags as $other => $flag ) : ?>
                        <?php $abuse = isset( $flag['kind'] ) && $flag['kind'] === 'abuse'; ?>
                        <div style="margin:0 0 10px; padding:9px 10px; border-radius:6px; background:<?php echo $abuse ? '#fef2f2' : '#fffbeb'; ?>; border:1px solid <?php echo $abuse ? '#fecaca' : '#fde68a'; ?>;">
                            <div style="font-weight:700; color:<?php echo $abuse ? '#991b1b' : '#92400e'; ?>;">
                                <?php
                                if ( $abuse ) {
                                    echo esc_html( ! empty( $flag['newer'] ) ? 'Reused a card that already had a free trial on ' : 'Their card was later used for another free trial by ' );
                                } else {
                                    echo esc_html( ! empty( $flag['newer'] ) ? 'Possible duplicate of ' : 'A later account may be a duplicate: ' );
                                }
                                echo gb_identity_other_account_html( $other, ! empty( $flag['deleted'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
                                ?>
                            </div>
                            <div style="font-size:11.5px;">Matched on: <?php echo esc_html( gb_identity_signal_list( (array) $flag['signals'] ) ); ?></div>
                            <?php if ( ! empty( $flag['at'] ) ) : ?>
                                <div style="font-size:11px; color:#94a3b8;">Noticed <?php echo esc_html( date_i18n( 'j M Y', strtotime( $flag['at'] ) ) ); ?></div>
                            <?php endif; ?>
                            <?php if ( empty( $flag['deleted'] ) ) : ?>
                                <a style="font-size:11.5px; font-weight:700; color:#2e7d32;" href="<?php echo esc_url( gb_identity_manager_action_url( 'dismiss', $author, $other, $cleaner_id ) ); ?>" onclick="return confirm('Clear this flag? Only do this if you have checked they are different people.');">They are different people: clear this flag</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ( ! empty( get_user_meta( $author, '_gb_trial_ended_for_abuse', true ) ) ) : ?>
                    <div style="font-size:11.5px; color:#991b1b;">Their free trial was ended automatically on <?php echo esc_html( date_i18n( 'j M Y', strtotime( (string) get_user_meta( $author, '_gb_trial_ended_for_abuse', true ) ) ) ); ?>.</div>
                <?php endif; ?>
                <p style="margin:12px 0 0; font-size:11px; color:#94a3b8;">
                    Details are compared as one-way fingerprints, so no extra copy of anybody&rsquo;s phone or card is kept.
                    A shared phone or a common name can be innocent, which is why these only flag and never block.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* --------------------------------------------------------------------------
   ONE-OFF CATCH-UP
   -------------------------------------------------------------------------- */

/**
 * Fingerprint the accounts that existed before this file did, and record the
 * free trials they have already had, so the very first checkout after this
 * goes live is already checked against them.
 */
function gb_identity_backfill() {
    if ( get_option( 'gb_identity_backfill' ) === '1' ) {
        return;
    }

    // Claimed first so two page loads at once do not both run it.
    update_option( 'gb_identity_backfill', '1', false );

    $users = get_users( array( 'fields' => 'ID', 'number' => 5000 ) );

    foreach ( $users as $user_id ) {
        gb_identity_on_user_saved( $user_id );

        $had_listing = (string) get_user_meta( $user_id, '_gb_listing_stripe_status', true ) !== '';

        // Anybody who has had a cleaner subscription is recorded, whatever
        // their role says today.
        if ( ! $had_listing && ! gb_identity_is_cleaner_user( $user_id ) ) {
            continue;
        }

        $fps = gb_identity_refresh_user( $user_id );

        if ( $had_listing ) {
            $mode = (string) get_user_meta( $user_id, '_gb_listing_mode', true ) === 'live' ? 'live' : 'test';
            gb_identity_registry_add( $user_id, $mode, (string) get_user_meta( $user_id, '_gb_stripe_subscription_id', true ), $fps, 'recorded from an existing subscription' );
        }
    }

    foreach ( $users as $user_id ) {
        gb_identity_evaluate( $user_id );
    }
}
add_action( 'init', 'gb_identity_backfill', 30 );
